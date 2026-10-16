<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/equipment_authority.php';
require_once __DIR__ . '/substitute.php';
require_once __DIR__ . '/titles.php';
require_once __DIR__ . '/names.php';

/** The editors' unsigned int(11) IDs. */
const RESURRECTION_CONTENT_MAX_ID = 4294967295;
/** Equipment levels and title dragon kill ranks are signed int(11) columns; dragon kills are never negative. */
const RESURRECTION_CONTENT_MAX_LEVEL = 2147483647;
/** The shop copies an item's name into accounts.weapon or accounts.armor, both varchar(50). */
const RESURRECTION_EQUIPMENT_NAME_LENGTH = 50;
/** titles.male and titles.female, and accounts.title, are varchar(25). */
const RESURRECTION_TITLE_LENGTH = 25;
/** taunts.taunt is a TEXT column. */
const RESURRECTION_TAUNT_BYTES = 65535;
/** Accounts checked per title rebuild transaction. */
const RESURRECTION_TITLE_REBUILD_BATCH = 50;

/**
 * A form's intent is bound to the operation and the row (or an add form's fixed state) it was shown with.
 * @param array<array-key, mixed> $state
 */
function resurrection_content_context(string $op, array $state): string {
    $values = array_map(static fn ($value) => $value === null ? null : (string)$value, $state);
    return hash('sha256', json_encode([$op, $values], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * An ID in the request: a positive integer that fits the column, or 0 when the parameter is absent and allowed to be.
 * @param array<string, mixed> $source
 */
function resurrection_content_id(array $source, string $key, bool $optional = false): int {
    // 1.1.2's add forms post no ID (or 0).
    if (!isset($source[$key]) || $source[$key] === '' || ($optional && $source[$key] === '0')) {
        if ($optional) return 0;
        throw new InvalidArgumentException('Missing ID.');
    }
    $id = \Resurrection\Http\Input::integer($source, $key, 0, 1);
    if ($id > RESURRECTION_CONTENT_MAX_ID) throw new InvalidArgumentException('ID out of range.');
    return $id;
}

/** The page's `level` (equipment): absent is 0, as 1.1.2's integer cast made it; otherwise a non-negative integer. */
function resurrection_content_level(): int {
    $level = \Resurrection\Http\Input::integer($_GET, 'level', 0, 0);
    if ($level > RESURRECTION_CONTENT_MAX_LEVEL) throw new InvalidArgumentException('Level out of range.');
    return $level;
}

/**
 * A POST carries only the protected fields, the named form fields and showform()'s tab index.
 * @param list<string> $fields
 */
function resurrection_content_fields(array $fields): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', 'showFormTabIndex', ...$fields]) !== []) {
        throw new InvalidArgumentException('Unexpected field.');
    }
}

/** A posted text field: a string of valid UTF-8 without NUL, at most $limit characters (or bytes). */
function resurrection_content_text(string $key, int $limit, bool $bytes = false): string {
    $value = \Resurrection\Http\Input::string($_POST, $key);
    if (preg_match('//u', $value) !== 1 || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid text.');
    if (($bytes ? strlen($value) : mb_strlen($value, 'UTF-8')) > $limit) throw new InvalidArgumentException('Text too long.');
    return $value;
}

/** A posted whole number within the field's range. */
function resurrection_content_integer(string $key, int $min, int $max): int {
    if (!isset($_POST[$key])) throw new InvalidArgumentException('Missing number.');
    $value = \Resurrection\Http\Input::integer($_POST, $key, 0, PHP_INT_MIN);
    if ($value < $min || $value > $max) throw new InvalidArgumentException('Number out of range.');
    return $value;
}

/** @return array<string, mixed>|null */
function resurrection_content_row(string $table, string $column, int $id, bool $lock = false): ?array {
    $rows = db_query('SELECT * FROM ' . db_prefix($table) . ' WHERE ' . db_identifier($column) . '=?' . ($lock ? ' FOR UPDATE' : ''), true, [$id]);
    return count($rows) === 1 ? $rows[0] : null;
}

function resurrection_content_failure(Throwable $error, string $page): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid request.'); }
    if ($error instanceof OutOfBoundsException) { http_response_code(404); exit("Not found. Return to $page."); }
    if ($error instanceof DomainException) { http_response_code(409); exit("This changed. Reload $page before acting."); }
    http_response_code(500); exit("Nothing was changed. Reload $page and try again.");
}

/** 1.1.2 changed rows on GET links. Those operations now act only on their protected POST forms. */
function resurrection_content_require_form(string $page): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit("Use the form on $page."); }
}

/** The opening tag of a protected form, with its intent; the action is allowed as a navigation target. */
function resurrection_content_form(string $url, string $scope, string $context, string $attributes = ''): string {
    addnav('', $url);
    return "<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'$attributes>" . resurrection_action_fields($scope, $context);
}

/** The 1.1.2 "Del" link, with its confirmation, as a protected POST form bound to the row shown. */
function resurrection_content_delete_form(string $url, string $scope, string $context, string $label, string $confirm): string {
    $confirm = htmlspecialchars(json_encode($confirm, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
    return resurrection_content_form($url, $scope, $context, " style='display:inline;margin:0' onsubmit='return confirm($confirm);'") .
        "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;color:inherit;cursor:pointer;text-decoration:underline'>" .
        htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>';
}

/**
 * Delete one row through its protected form: the row (its ID in the URL's $key) must still be the one the form showed.
 */
function resurrection_content_delete(string $table, string $column, string $key, string $scope, string $page): void {
    resurrection_content_require_form($page);
    try {
        resurrection_content_fields([]);
        $id = resurrection_content_id($_GET, $key);
        $row = resurrection_content_row($table, $column, $id);
        if ($row === null) throw new OutOfBoundsException('Row not found.');
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action($scope . '-del-' . $id, resurrection_content_context('del', $row));
    try {
        resurrection_player_mutation(function () use ($table, $column, $id, $row) {
            $locked = resurrection_content_row($table, $column, $id, true);
            if ($locked === null || resurrection_content_context('del', $locked) !== resurrection_content_context('del', $row)) throw new DomainException('Row changed.');
            db_query('DELETE FROM ' . db_prefix($table) . ' WHERE ' . db_identifier($column) . '=?', true, [$id]);
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
}

/**
 * The weapon or armor editor's save. 1.1.2's form: the hidden ID (0 adds), the name and the 1-15 damage or defense;
 * the price comes from the editor's fixed table, indexed by that value. An add uses the page's level.
 * @param array<string, string> $shop
 * @param array<int, int> $values
 */
function resurrection_equipment_editor_save(array $shop, int $level, array $values): void {
    $page = 'the ' . $shop['kind'] . ' editor';
    resurrection_content_require_form($page);
    try {
        resurrection_content_fields([$shop['id'], $shop['name'], $shop['stat']]);
        $id = resurrection_content_id($_POST, $shop['id'], true);
        $name = resurrection_content_text($shop['name'], RESURRECTION_EQUIPMENT_NAME_LENGTH);
        $stat = resurrection_content_integer($shop['stat'], 1, 15);
        if (!isset($values[$stat])) throw new InvalidArgumentException('No price for that value.');
        $row = $id > 0 ? resurrection_content_row($shop['table'], $shop['id'], $id) : null;
        if ($id > 0 && $row === null) throw new OutOfBoundsException('Item not found.');
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action($shop['kind'] . '-edit-' . $id, $row === null ? resurrection_content_context('add', [$level]) : resurrection_content_context('edit', $row));
    try {
        resurrection_player_mutation(function () use ($shop, $id, $row, $name, $stat, $values, $level) {
            $table = db_prefix($shop['table']);
            if ($row === null) {
                db_query("INSERT INTO $table (level," . $shop['stat'] . ',' . $shop['name'] . ',value) VALUES (?,?,?,?)', true, [$level, $stat, $name, $values[$stat]]);
                return;
            }
            $locked = resurrection_content_row($shop['table'], $shop['id'], $id, true);
            if ($locked === null || resurrection_content_context('edit', $locked) !== resurrection_content_context('edit', $row)) throw new DomainException('Item changed.');
            db_query("UPDATE $table SET " . $shop['name'] . '=?,' . $shop['stat'] . '=?,value=? WHERE ' . $shop['id'] . '=?', true, [$name, $stat, $values[$stat], $id]);
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
}

/**
 * Whether a taunt formats the way combat news and mail format it: substitute_array() turns the listed codes into
 * arguments and sprintf_translate() escapes the `% colour code; any other % must be a valid sprintf directive
 * with an argument. PHP 5 skipped an invalid one with a warning; PHP 8 throws, so a stored taunt with a stray %
 * makes every page that renders it fail.
 */
function resurrection_taunt_formats(string $taunt): bool {
    return resurrection_taunt_sample($taunt) !== null;
}

/** The taunt formatted against the editor's preview monster, or null when it does not format. */
function resurrection_taunt_sample(string $taunt): ?string {
    global $badguy;
    $saved = $badguy ?? null;
    $badguy = ['creaturename' => 'Baron Munchausen', 'creatureweapon' => 'Bad Puns'];
    try {
        $parts = substitute_array($taunt);
        $format = str_replace('`%', '`%%', (string)array_shift($parts));
        return vsprintf($format, $parts);
    } catch (ValueError | ArgumentCountError) {
        return null;
    } finally {
        $badguy = $saved;
    }
}

/**
 * The taunt editor's save: the taunt text, written with the editing superuser's login as 1.1.2 did.
 */
function resurrection_taunt_save(int $id): void {
    global $session;
    $page = 'the taunt editor';
    resurrection_content_require_form($page);
    try {
        resurrection_content_fields(['taunt']);
        $taunt = resurrection_content_text('taunt', RESURRECTION_TAUNT_BYTES, true);
        if (!resurrection_taunt_formats($taunt)) throw new InvalidArgumentException('Taunt does not format.');
        $row = $id > 0 ? resurrection_content_row('taunts', 'tauntid', $id) : null;
        if ($id > 0 && $row === null) throw new OutOfBoundsException('Taunt not found.');
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action('taunt-edit-' . $id, $row === null ? resurrection_content_context('add', []) : resurrection_content_context('edit', $row));
    try {
        resurrection_player_mutation(function () use ($id, $row, $taunt) {
            global $session;
            if ($row === null) {
                db_query('INSERT INTO ' . db_prefix('taunts') . ' (taunt,editor) VALUES (?,?)', true, [$taunt, $session['user']['login']]);
                return;
            }
            $locked = resurrection_content_row('taunts', 'tauntid', $id, true);
            if ($locked === null || resurrection_content_context('edit', $locked) !== resurrection_content_context('edit', $row)) throw new DomainException('Taunt changed.');
            db_query('UPDATE ' . db_prefix('taunts') . ' SET taunt=?,editor=? WHERE tauntid=?', true, [$taunt, $session['user']['login'], $id]);
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
}

/**
 * The title editor's save: dragon kills and the two titles; `ref` stays unused (''), as in 1.1.2.
 * Returns the affected row count 1.1.2 reported on.
 */
function resurrection_title_save(int $id): int {
    $page = 'the title editor';
    resurrection_content_require_form($page);
    try {
        resurrection_content_fields(['dk', 'male', 'female']);
        $dk = resurrection_content_integer('dk', -RESURRECTION_CONTENT_MAX_LEVEL - 1, RESURRECTION_CONTENT_MAX_LEVEL);
        $male = resurrection_content_text('male', RESURRECTION_TITLE_LENGTH);
        $female = resurrection_content_text('female', RESURRECTION_TITLE_LENGTH);
        $row = $id > 0 ? resurrection_content_row('titles', 'titleid', $id) : null;
        if ($id > 0 && $row === null) throw new OutOfBoundsException('Title not found.');
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action('title-edit-' . $id, $row === null ? resurrection_content_context('add', []) : resurrection_content_context('edit', $row));
    try {
        return resurrection_player_mutation(function () use ($id, $row, $dk, $male, $female) {
            if ($row === null) {
                db_query('INSERT INTO ' . db_prefix('titles') . " (dk,ref,male,female) VALUES (?,'',?,?)", true, [$dk, $male, $female]);
                return db_affected_rows();
            }
            $locked = resurrection_content_row('titles', 'titleid', $id, true);
            if ($locked === null || resurrection_content_context('edit', $locked) !== resurrection_content_context('edit', $row)) throw new DomainException('Title changed.');
            db_query('UPDATE ' . db_prefix('titles') . " SET dk=?,ref='',male=?,female=? WHERE titleid=?", true, [$dk, $male, $female, $id]);
            return db_affected_rows();
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
}

/** The title rebuild form is bound to the title table it was shown with. */
function resurrection_title_rebuild_context(): string {
    return resurrection_content_context('rebuild', array_map(
        static fn (array $row) => json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
        db_query('SELECT titleid,dk,ref,male,female FROM ' . db_prefix('titles') . ' ORDER BY titleid', true)));
}

/**
 * 1.1.2's "Reset Users Titles": every account whose title is not one of the titles of its dragon kill rank gets a
 * new one (a random title of that rank), and its name is rebuilt around it; a custom title stays in the name.
 * Accounts are locked and rewritten in ascending ID order, RESURRECTION_TITLE_REBUILD_BATCH per transaction: each
 * account is fully rewritten or untouched. The acting superuser's own account is written through the session, as
 * 1.1.2 did, inside the same transaction. A name that would not fit accounts.name is left unchanged and listed.
 * @return array{changed: int, checked: int, total: int, skipped: list<string>, failed: bool}
 */
function resurrection_title_rebuild(): array {
    $total = (int)db_query('SELECT COUNT(*) AS n FROM ' . db_prefix('accounts'), true)[0]['n'];
    $columns = db_query('SELECT CHARACTER_MAXIMUM_LENGTH AS length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', true, [db_prefix('accounts'), 'name']);
    $limit = (int)($columns[0]['length'] ?? 60);
    $result = ['changed' => 0, 'checked' => 0, 'total' => $total, 'skipped' => [], 'failed' => false];
    $last = 0;
    while (true) {
        try {
            $batch = resurrection_player_mutation(function () use ($last, $limit) {
                global $session;
                $rows = db_query('SELECT name,title,dragonkills,acctid,sex,ctitle FROM ' . db_prefix('accounts') . ' WHERE acctid>? ORDER BY acctid LIMIT ' . RESURRECTION_TITLE_REBUILD_BATCH . ' FOR UPDATE', true, [$last]);
                $batch = ['changed' => 0, 'checked' => count($rows), 'skipped' => [], 'last' => $rows === [] ? null : (int)$rows[count($rows) - 1]['acctid']];
                foreach ($rows as $row) {
                    $oname = $row['name'];
                    $otitle = $row['title'];
                    $dk = (int)($row['dragonkills']);
                    if (valid_dk_title($otitle, $dk, $row['sex'])) continue;
                    $sex = translate_inline($row['sex'] ? "female" : "male");
                    $newtitle = get_dk_title($dk, (int)$row['sex']);
                    $newname = change_player_title($newtitle, $row);
                    $id = (int)$row['acctid'];
                    if (mb_strlen($newname, 'UTF-8') > $limit || mb_strlen($newtitle, 'UTF-8') > RESURRECTION_TITLE_LENGTH) {
                        $batch['skipped'][] = $oname;
                        continue;
                    }
                    if ($oname != $newname) {
                        output("`@Changing `^%s`@ to `^%s `@(%s`@ [%s,%s])`n", $oname, $newname, $newtitle, $dk, $sex);
                        if ((int)$session['user']['acctid'] === $id) {
                            $session['user']['title'] = $newtitle;
                            $session['user']['name'] = $newname;
                        } else {
                            db_query('UPDATE ' . db_prefix('accounts') . ' SET name=?,title=? WHERE acctid=?', true, [$newname, $newtitle, $id]);
                        }
                        $batch['changed']++;
                    } elseif ($otitle != $newtitle) {
                        output("`@Changing only the title (not the name) of `^%s`@ `@(%s`@ [%s,%s])`n", $oname, $newtitle, $dk, $sex);
                        if ((int)$session['user']['acctid'] === $id) {
                            $session['user']['title'] = $newtitle;
                        } else {
                            db_query('UPDATE ' . db_prefix('accounts') . ' SET title=? WHERE acctid=?', true, [$newtitle, $id]);
                        }
                        $batch['changed']++;
                    }
                }
                return $batch;
            });
        } catch (Throwable $error) {
            $result['failed'] = true;
            return $result;
        }
        if ($batch['last'] === null) return $result;
        $result['changed'] += $batch['changed'];
        $result['checked'] += $batch['checked'];
        array_push($result['skipped'], ...$batch['skipped']);
        $last = $batch['last'];
    }
}
