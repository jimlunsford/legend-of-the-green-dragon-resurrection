<?php
require_once __DIR__ . '/admin_authority.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/../src/Security/Passwords.php';

/**
 * The editable fields of the historical $userinfo list: key => type, options, bitfield mask. Titles and
 * viewonly fields are not editable. A `hidden` title (edittitles off) still posts its value, as in 1.1.2.
 * @param array<array-key, mixed> $userinfo
 * @return array<string, array{type: string, options: list<string>, mask: int}>
 */
function resurrection_user_editor_schema(array $userinfo): array {
    $schema = [];
    foreach ($userinfo as $key => $declaration) {
        if (!is_string($key)) continue;
        if (is_array($declaration)) $declaration = $declaration[0];
        $parts = explode(',', (string)$declaration);
        $type = trim($parts[1] ?? '');
        if ($type === 'viewonly') continue;
        $field = ['type' => $type, 'options' => [], 'mask' => 0];
        if ($type === '' || $type === 'hidden') {
            $field['type'] = 'text';
        } elseif ($type === 'enum' || $type === 'enumpretrans') {
            $field['type'] = 'enum';
            for ($i = 2; $i < count($parts); $i += 2) $field['options'][] = $parts[$i];
        } elseif ($type === 'bitfield') {
            $field['mask'] = (int)($parts[2] ?? 0);
        } elseif (!in_array($type, ['int', 'bool', 'password'], true)) {
            throw new DomainException('Unsupported user field declaration: ' . $key);
        }
        $schema[$key] = $field;
    }
    return $schema;
}

/**
 * Column limits of the accounts table, so a value that strict SQL would refuse is refused before any write.
 * @return array<string, array{type: string, unsigned: bool, length: int|null, nullable: bool}>
 */
function resurrection_user_editor_columns(): array {
    $columns = [];
    $rows = db_query('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', true, [db_prefix('accounts')]);
    foreach ($rows as $row) {
        $columns[$row['COLUMN_NAME']] = ['type' => strtolower($row['DATA_TYPE']), 'unsigned' => str_contains(strtolower($row['COLUMN_TYPE']), 'unsigned'),
            'length' => $row['CHARACTER_MAXIMUM_LENGTH'] === null ? null : (int)$row['CHARACTER_MAXIMUM_LENGTH'], 'nullable' => $row['IS_NULLABLE'] === 'YES'];
    }
    return $columns;
}

/**
 * A posted field value checked against its descriptor and its column. Returns the value to store.
 * @param array{type: string, options: list<string>, mask: int} $field
 * @param array{type: string, unsigned: bool, length: int|null, nullable: bool}|null $column
 */
function resurrection_user_editor_value(array $field, ?array $column, mixed $value): string|int|null {
    if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid account field.');
    if ($field['type'] === 'password') {
        if ($value !== '') \Resurrection\Security\Passwords::hash($value);
        return $value;
    }
    if ($column === null) throw new InvalidArgumentException('Unknown account column.');
    $integer = [
        'tinyint' => [-128, 127, 255], 'smallint' => [-32768, 32767, 65535], 'mediumint' => [-8388608, 8388607, 16777215],
        'int' => [-2147483648, 2147483647, 4294967295], 'bigint' => [PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MAX],
    ][$column['type']] ?? null;
    if ($field['type'] === 'bool' && !in_array($value, ['0', '1'], true)) throw new InvalidArgumentException('Invalid boolean.');
    if ($field['type'] === 'enum' && !in_array($value, $field['options'], true)) throw new InvalidArgumentException('Invalid choice.');
    if ($field['type'] === 'int' && $integer === null) throw new InvalidArgumentException('Not an integer column.');
    if ($integer !== null) {
        if (!preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value)) throw new InvalidArgumentException('Invalid number.');
        $number = filter_var($value, FILTER_VALIDATE_INT);
        $min = $column['unsigned'] ? 0 : $integer[0];
        $max = $column['unsigned'] ? $integer[2] : $integer[1];
        if ($number === false || $number < $min || $number > $max) throw new InvalidArgumentException('Number out of range.');
        return $number;
    }
    if ($column['type'] === 'datetime' || $column['type'] === 'date') {
        if ($value === '' && $column['nullable']) return null;
        $format = $column['type'] === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date === false || $date->format($format) !== $value || (int)$date->format('Y') < 1000) throw new InvalidArgumentException('Invalid date.');
        return $value;
    }
    $limit = $column['length'] ?? 0;
    if (!in_array($column['type'], ['varchar', 'char', 'text', 'tinytext', 'mediumtext'], true)) throw new InvalidArgumentException('Unsupported column.');
    if (in_array($column['type'], ['text', 'tinytext', 'mediumtext'], true) ? strlen($value) > $limit : mb_strlen($value, 'UTF-8') > $limit) throw new InvalidArgumentException('Text too long.');
    return $value;
}

/**
 * The posted superuser checkboxes: the hidden `[0]` plus one key per single bit, each with the value 1.
 * 1.1.2 added the keys of every truthy entry.
 */
function resurrection_user_editor_bits(mixed $value): int {
    if (!is_array($value)) throw new InvalidArgumentException('Invalid privileges.');
    $bits = 0;
    foreach ($value as $bit => $checked) {
        if ($checked !== '1' || !is_int($bit) || $bit < 0 || $bit > 0x80000000 || ($bit & ($bit - 1)) !== 0) throw new InvalidArgumentException('Invalid privileges.');
        $bits += $bit;
    }
    return $bits;
}

/**
 * The 1.1.2 superuser rules, unchanged: strip requested flags the editor cannot set (flags the account
 * already holds may stay), then put back the account's flags the editor cannot set. A megauser sets all.
 */
function resurrection_user_editor_superuser(int $requested, int $old, int $editor): int {
    $all = $editor & SU_MEGAUSER ? 0xFFFFFFFF : 0;
    //strip off an attempt to set privs that the user doesn't
    //have authority to set.
    $stripfield = ($old | $editor | SU_ANYONE_CAN_SET | $all);
    $value = $requested & $stripfield;
    //put back on privs that the user used to have but the
    //current user can't set.
    $unremovable = ~ ($editor | SU_ANYONE_CAN_SET | $all);
    $filteredunremovable = $old & $unremovable;
    return $value | $filteredunremovable;
}

/**
 * What the edit form showed for each field, as showform() renders it: the name without its title, the
 * selected yes/no or choice (the browser selects the first option when none matches), an empty password.
 * @param array<string, array{type: string, options: list<string>, mask: int}> $schema
 * @param array<string, mixed> $row
 * @return array<string, string|int>
 */
function resurrection_user_editor_displayed(array $schema, array $row): array {
    $shown = [];
    foreach ($schema as $key => $field) {
        $value = $row[$key] ?? null;
        $shown[$key] = match ($field['type']) {
            'password' => '',
            'bool' => $value == 1 ? '1' : '0',
            'bitfield' => (int)$value,
            'enum' => (static function () use ($field, $value): string {
                $selected = $field['options'][0] ?? '';
                foreach ($field['options'] as $option) if ($value !== null && $value == $option) $selected = $option;
                return $selected;
            })(),
            default => $key === 'name' ? get_player_basename($row) : (string)$value,
        };
    }
    return $shown;
}

/**
 * The stored values the edit form was built from. The form's intent is bound to them.
 * @param array<string, array{type: string, options: list<string>, mask: int}> $schema
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function resurrection_user_editor_snapshot(array $schema, array $row): array {
    $snapshot = ['acctid' => (string)$row['acctid']];
    foreach ($schema as $key => $field) if (array_key_exists($key, $row)) $snapshot[$key] = $row[$key] === null ? null : (string)$row[$key];
    return $snapshot;
}

/** @param array<string, mixed> $snapshot */
function resurrection_user_editor_context(array $snapshot): string {
    return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * 1.1.2's `returnpetition`: a petition ID carried through the editor's links. Only a positive integer.
 */
function resurrection_user_editor_petition(mixed $value): string {
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,9}\z/', $value) && (int)$value <= 4294967295 ? $value : '';
}

/**
 * The issued edit form: its intent and the snapshot it was built from, kept in the editor's session.
 * @param array<string, mixed> $snapshot
 */
function resurrection_user_editor_fields(int $id, array $snapshot): string {
    $_SESSION['user_editor_snapshots'][$id] = $snapshot;
    return resurrection_action_fields('user-edit-' . $id, resurrection_user_editor_context($snapshot));
}

/**
 * Save the edit form. Only the fields the editor changed from what the form showed are written; a
 * field the editor changed that changed underneath since the form was shown refuses the whole save.
 * Validation precedes every write; the account update, its debug log rows and the editor's own session
 * commit together.
 * @param array<array-key, mixed> $userinfo
 */
function resurrection_user_editor_save(int $userid, array $userinfo): void {
    global $session;
    $schema = resurrection_user_editor_schema($userinfo);
    try {
        foreach (array_keys($_POST) as $key) {
            if (!isset($schema[$key]) && !in_array($key, ['csrf_token', 'action_token', 'showFormTabIndex'], true)) throw new InvalidArgumentException('Unexpected field.');
        }
        $columns = resurrection_user_editor_columns();
        $values = [];
        foreach ($schema as $key => $field) {
            if (!array_key_exists($key, $_POST)) continue;
            $values[$key] = $field['type'] === 'bitfield' ? resurrection_user_editor_bits($_POST[$key])
                : resurrection_user_editor_value($field, $columns[$key] ?? null, $_POST[$key]);
        }
        if (resurrection_admin_account($userid, 'acctid') === null) throw new OutOfBoundsException('Account not found.');
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
    $snapshot = $_SESSION['user_editor_snapshots'][$userid] ?? null;
    if (!is_array($snapshot)) { http_response_code(409); exit('Expired or repeated action.'); }
    resurrection_consume_action('user-edit-' . $userid, resurrection_user_editor_context($snapshot));
    unset($_SESSION['user_editor_snapshots'][$userid]);
    $shown = resurrection_user_editor_displayed($schema, $snapshot);
    try {
        $updates = resurrection_admin_mutation(function () use ($userid, $snapshot, $shown, $values, $columns) {
            global $session;
            $rows = db_query('SELECT * FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$userid]);
            if (count($rows) !== 1) throw new DomainException('Account removed.');
            $oldvalues = $rows[0];
            $sql = ''; $parameters = []; $updates = 0;
            // A field the editor changed must still hold what the form showed.
            $changed = static function (string $key) use ($oldvalues, $snapshot) {
                $current = $oldvalues[$key] === null ? null : (string)$oldvalues[$key];
                if ($current !== ($snapshot[$key] ?? null)) throw new DomainException('Field changed underneath: ' . $key);
            };
            foreach ($values as $key => $val) {
                if ($key == "newpassword") {
                    if ($val > "") {
                        $sql .= 'password=?,authversion=authversion+1,';
                        $parameters[] = \Resurrection\Security\Passwords::hash($val);
                        $updates++;
                        output("Password value has been updated.`n");
                        debuglog("Administrator changed account password", $userid);
                    }
                } elseif ($key == "superuser") {
                    if (resurrection_user_editor_superuser($val, (int)$snapshot['superuser'], (int)$session['user']['superuser']) === (int)$snapshot['superuser']) continue;
                    $changed($key);
                    $value = resurrection_user_editor_superuser($val, (int)$oldvalues['superuser'], (int)$session['user']['superuser']);
                    $sql .= db_identifier($key) . '=?,'; $parameters[] = $value;
                    $updates++;
                    output("Superuser values have changed.`n");
                    if ($session['user']['acctid'] == $userid) {
                        $session['user']['superuser'] = $value;
                    }
                    debuglog($session['user']['name'] . "`0 changed superuser to " . show_bitfield($value), $userid);
                    debug("superuser has changed to $value");
                } elseif ((string)$val === (string)$shown[$key]) {
                    // Unchanged from what the form showed: the stored value stays, even if it changed since.
                } elseif ($key == "name") {
                    $changed($key);
                    $updates++;
                    $tmp = sanitize_colorname(getsetting("spaceinname", 0), $val, true);
                    $tmp = preg_replace("/[`][cHw]/", "", $tmp);
                    $tmp = sanitize_html($tmp);
                    if ($tmp != $val) {
                        output("`\$Illegal characters removed from player name!`0`n");
                    }
                    if (soap($tmp) != ($tmp)) {
                        output("`^The new name doesn't pass the bad word filter!`0");
                    }
                    $newname = change_player_name($tmp, $oldvalues);
                    $sql .= db_identifier($key) . '=?,'; $parameters[] = $newname;
                    output("Changed player name to %s`0`n", $newname);
                    debuglog($session['user']['name'] . "`0 changed player name to $newname`0", $userid);
                    $oldvalues['name'] = $newname;
                    if ($session['user']['acctid'] == $userid) {
                        $session['user']['name'] = $newname;
                    }
                } elseif ($key == "title" || $key == "ctitle") {
                    $changed($key);
                    $updates++;
                    $tmp = sanitize_colorname(true, $val, true);
                    $tmp = preg_replace("/[`][cHw]/", "", $tmp);
                    $tmp = sanitize_html($tmp);
                    if ($tmp != $val) {
                        output($key == "title" ? "`\$Illegal characters removed from player title!`0`n" : "`\$Illegal characters removed from custom title!`0`n");
                    }
                    if (soap($tmp) != ($tmp)) {
                        output($key == "title" ? "`^The new title doesn't pass the bad word filter!`0" : "`^The new custom title doesn't pass the bad word filter!`0");
                    }
                    $newname = $key == "title" ? change_player_title($tmp, $oldvalues) : change_player_ctitle($tmp, $oldvalues);
                    $sql .= db_identifier($key) . '=?,'; $parameters[] = $val;
                    output($key == "title" ? "Changed player title from %s`0 to %s`0`n" : "Changed player ctitle from %s`0 to %s`0`n", $oldvalues[$key], $tmp);
                    $oldvalues[$key] = $tmp;
                    if ($newname != $oldvalues['name']) {
                        $sql .= 'name=?,'; $parameters[] = $newname;
                        output($key == "title" ? "Changed player name to %s`0 due to changed dragonkill title`n" : "Changed player name to %s`0 due to changed custom title`n", $newname);
                        debuglog($session['user']['name'] . "`0 changed player name to $newname`0 due to " . ($key == "title" ? "changed dragonkill title" : "changed custom title"), $userid);
                        $oldvalues['name'] = $newname;
                        if ($session['user']['acctid'] == $userid) {
                            $session['user']['name'] = $newname;
                        }
                    }
                    if ($session['user']['acctid'] == $userid) {
                        $session['user'][$key] = $tmp;
                    }
                } elseif (array_key_exists($key, $oldvalues)) {
                    $changed($key);
                    $sql .= db_identifier($key) . '=?,'; $parameters[] = $val;
                    $updates++;
                    output("%s has changed to %s.`n", $key, $val);
                    debuglog($session['user']['name'] . "`0 changed $key to $val", $userid);
                    if ($session['user']['acctid'] == $userid) {
                        $session['user'][$key] = $val;
                    }
                }
            }
            $name = $oldvalues['name'];
            if (mb_strlen((string)$name, 'UTF-8') > (int)($columns['name']['length'] ?? 0)) throw new InvalidArgumentException('Name too long.');
            if ($updates > 0) {
                $parameters[] = $userid;
                db_query('UPDATE ' . db_prefix('accounts') . ' SET ' . substr($sql, 0, -1) . ' WHERE acctid=?', true, $parameters);
            }
            return $updates;
        }, $userid);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
    if ($updates > 0) {
        if ((int)$session['user']['acctid'] === $userid) {
            $rows = db_query('SELECT authversion FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$userid]);
            $_SESSION['auth_version'] = (int)db_fetch_assoc($rows)['authversion'];
            resurrection_rotate_session();
        }
        output("%s fields in the user's record were updated.", $updates);
    } else {
        output("No fields were changed in the user's record.");
    }
}

/**
 * The list's Del form is bound to the account it was shown for.
 * @param array<string, mixed> $row
 */
function resurrection_user_delete_context(array $row): string {
    return hash('sha256', json_encode(['del', (string)$row['acctid'], (string)$row['login']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/** @param array<string, mixed> $row */
function resurrection_user_special_context(array $row): string {
    return hash('sha256', json_encode(['special', (string)$row['acctid'], (string)$row['login'], $row['lasthit'], (string)$row['emailvalidation']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * Grant New Day, Fix Broken Navs or Mark Email As Valid: the 1.1.2 writes, bound to the account state the
 * buttons were shown with, in one locked transaction.
 */
function resurrection_user_special(int $userid): void {
    global $session;
    try {
        $actions = array_values(array_intersect(array_keys($_POST), ['newday', 'fixnavs', 'clearvalidation']));
        if (count($actions) !== 1 || !is_string($_POST[$actions[0]]) || $_POST[$actions[0]] === '') throw new InvalidArgumentException('Choose one action.');
        resurrection_admin_fields($actions);
        $row = resurrection_admin_account($userid, 'acctid,login,lasthit,emailvalidation');
        if ($row === null) throw new OutOfBoundsException('Account not found.');
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
    resurrection_consume_action('user-special-' . $userid, resurrection_user_special_context($row));
    try {
        resurrection_admin_mutation(function () use ($userid, $row, $actions) {
            global $session;
            $locked = db_query('SELECT acctid,login,lasthit,emailvalidation FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$userid]);
            if (count($locked) !== 1 || resurrection_user_special_context($locked[0]) !== resurrection_user_special_context($row)) throw new DomainException('Account changed.');
            $self = (int)$session['user']['acctid'] === $userid;
            if ($actions[0] === 'newday') {
                // 1.1.2 wrote the zero date, which strict SQL refuses; NULL is the accepted "no last hit".
                db_query('UPDATE ' . db_prefix('accounts') . ' SET lasthit=NULL WHERE acctid=?', true, [$userid]);
                if ($self) $session['user']['lasthit'] = null;
            } elseif ($actions[0] === 'fixnavs') {
                db_query("UPDATE " . db_prefix('accounts') . " SET allowednavs='', restorepage='', specialinc='' WHERE acctid=?", true, [$userid]);
                db_query('DELETE FROM ' . db_prefix('accounts_output') . ' WHERE acctid=?', true, [$userid]);
                if ($self) { $session['user']['restorepage'] = ''; $session['user']['specialinc'] = ''; }
            } else {
                db_query("UPDATE " . db_prefix('accounts') . " SET emailvalidation='' WHERE acctid=?", true, [$userid]);
                if ($self) $session['user']['emailvalidation'] = '';
            }
        }, $userid);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
}

/**
 * A module's declared user preferences. Undeclared preferences cannot be written; a declaration without
 * a typed parser fails closed.
 * @return array{schema: array<string, array<string, mixed>>, values: array<string, string>}
 */
function resurrection_user_module_state(string $module, int $userid, bool $lock = false): array {
    require_once __DIR__ . '/typed_editor.php';
    if ($lock && !db_query('SELECT modulename FROM ' . db_prefix('modules') . ' WHERE modulename=? FOR UPDATE', true, [$module])) throw new DomainException('Module removed.');
    $info = resurrection_editor_module($module);
    $schema = \Resurrection\Http\SettingDescriptor::declare($info['prefs'] ?? []);
    if (!$schema) throw new DomainException('Undeclared user preferences.');
    $values = [];
    foreach (db_query('SELECT setting,value FROM ' . db_prefix('module_userprefs') . ' WHERE modulename=? AND userid=? ORDER BY setting' . ($lock ? ' FOR UPDATE' : ''), true, [$module, $userid]) as $row) $values[$row['setting']] = $row['value'];
    return ['schema' => $schema, 'values' => $values];
}

/** @param array{schema: array<string, array<string, mixed>>, values: array<string, string>} $state */
function resurrection_user_module_context(int $userid, array $state): string {
    return hash('sha256', $userid . ':' . resurrection_editor_context($state['schema'], $state['values']));
}

/** The module preference form's intent, bound to the account and the module's stored preferences. */
function resurrection_user_module_fields(string $module, int $userid): string {
    try { $state = resurrection_user_module_state($module, $userid); }
    catch (DomainException $error) { return resurrection_csrf_field(); }
    return resurrection_action_fields('user-module-' . $userid . '-' . $module, resurrection_user_module_context($userid, $state));
}

/**
 * Save a module's user preferences: installed modules and declared, typed preferences only. The
 * `validateprefs` hook still sees the posted preferences and can refuse them.
 */
function resurrection_user_module_save(string $module, int $userid): void {
    try {
        if (!preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $module)) throw new InvalidArgumentException('Invalid module.');
        if (resurrection_admin_account($userid, 'acctid') === null) throw new OutOfBoundsException('Account not found.');
        $state = resurrection_user_module_state($module, $userid);
        $post = $_POST;
        unset($post['showFormTabIndex']);
        $patch = \Resurrection\Http\SettingDescriptor::patch($state['schema'], $post);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
    resurrection_consume_action('user-module-' . $userid . '-' . $module, resurrection_user_module_context($userid, $state));
    $post = modulehook("validateprefs", $patch, true, $module);
    if (isset($post['validation_error']) && $post['validation_error']) {
        tlschema("module-$module");
        $post['validation_error'] = translate_inline($post['validation_error']);
        tlschema();
        output("Unable to change settings: `\$%s`0", $post['validation_error']);
        return;
    }
    try {
        // The hook may adjust declared values; it cannot add an undeclared or untyped one.
        $post = \Resurrection\Http\SettingDescriptor::patch($state['schema'], $post);
        resurrection_admin_mutation(function () use ($module, $userid, $state, $post) {
            $locked = resurrection_user_module_state($module, $userid, true);
            if (resurrection_user_module_context($userid, $locked) !== resurrection_user_module_context($userid, $state)) throw new DomainException('Preferences changed.');
            foreach ($post as $key => $val) {
                db_query('REPLACE INTO ' . db_prefix('module_userprefs') . ' (modulename,userid,setting,value) VALUES (?,?,?,?)', true, [$module, $userid, $key, $val]);
                output("Setting %s to %s`n", $key, $val);
            }
        }, $userid);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
    unset($GLOBALS['module_prefs'][$userid][$module]);
    output("`^Preferences for module %s saved.`n", $module);
}
