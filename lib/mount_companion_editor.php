<?php
require_once __DIR__ . '/content_editor.php';
require_once __DIR__ . '/stables_authority.php';
require_once __DIR__ . '/buffs.php';
require_once __DIR__ . '/../src/Game/Expression.php';
require_once __DIR__ . '/../src/Game/TransmutationState.php';
require_once __DIR__ . '/../src/Game/BuffState.php';
require_once __DIR__ . '/../src/Game/NewDayState.php';
require_once __DIR__ . '/../src/Game/SkeletonCompanionState.php';
require_once __DIR__ . '/../src/Game/ForestCompanionState.php';

/*
 * The mount and companion editors (SU_EDIT_MOUNTS). 1.1.2 ran activate, deactivate, delete, give and take on GET
 * links with the ID pasted into SQL, built each save from every posted mount[...] or companion[...] key (any column,
 * any serialized array), and refunded mount owners from a cached row outside any transaction. Every write is now a
 * POST with CSRF and a one-use intent bound to the row as shown; only the form's declared fields are written, each
 * checked against its column and the game's accepted consumer of the value.
 */

/** The mount form's text columns and their lengths: varchar characters, or TEXT bytes. */
const RESURRECTION_MOUNT_TEXT = ['mountname' => [50, false], 'mountdesc' => [65535, true], 'mountcategory' => [50, false],
    'newday' => [65535, true], 'recharge' => [65535, true], 'partrecharge' => [65535, true]];
/**
 * The mount form's whole numbers. The costs are unsigned columns, held to the range of the account balances the
 * stables charge and a delete refunds (lib/player_mutation.php); the forest fight change is a signed column.
 */
const RESURRECTION_MOUNT_INT = ['mountactive' => [0, 1], 'mountdkcost' => [0, 2147483647], 'mountcostgems' => [0, 2147483647],
    'mountcostgold' => [0, 2147483647], 'mountfeedcost' => [0, 2147483647], 'mountforestfights' => [-2147483648, 2147483647]];
/** The mount buff fields of 1.1.2's form, in its order. */
const RESURRECTION_MOUNT_BUFF = ['name', 'roundmsg', 'wearoff', 'effectmsg', 'effectnodmgmsg', 'effectfailmsg', 'rounds',
    'atkmod', 'defmod', 'invulnerable', 'regen', 'minioncount', 'minbadguydamage', 'maxbadguydamage', 'mingoodguydamage',
    'maxgoodguydamage', 'lifetap', 'damageshield', 'badguydmgmod', 'badguyatkmod', 'badguydefmod'];
/**
 * The companion form's text columns. A taken or hired companion is checked by the combat consumer
 * (ForestCompanionState), which holds its texts to 8192 bytes and its name, the key it is stored under, to 255 bytes.
 */
const RESURRECTION_COMPANION_TEXT = ['name' => 255, 'dyingtext' => 255, 'description' => 8192, 'jointext' => 8192, 'category' => 255];
/** The companion form's whole numbers, held to the combat consumer's range (0 to 2147483647). */
const RESURRECTION_COMPANION_INT = ['companionactive' => [0, 1], 'maxhitpoints' => [0, 2147483647], 'maxhitpointsperlevel' => [0, 2147483647],
    'attack' => [0, 2147483647], 'attackperlevel' => [0, 2147483647], 'defense' => [0, 2147483647], 'defenseperlevel' => [0, 2147483647],
    'companioncostdks' => [0, 127], 'companioncostgems' => [0, 2147483647], 'companioncostgold' => [0, 2147483647]];
/** The companion form's checkboxes: absent is 0, as 1.1.2's save made it. */
const RESURRECTION_COMPANION_FLAGS = ['cannotdie', 'cannotbehealed', 'allowinshades', 'allowinpvp', 'allowintrain'];

/** A 1.1.2 action link (activate, give, take, ...) as an inline protected POST form bound to the row shown. */
function resurrection_content_button_form(string $url, string $scope, string $context, string $label): string {
    return resurrection_content_form($url, $scope, $context, " style='display:inline;margin:0'") .
        "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;color:inherit;cursor:pointer;text-decoration:underline'>" .
        htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>';
}

/**
 * Where 1.1.2's form offered a mount (stables) or companion (camps): the village, the hook's places, and everywhere.
 * @return array<int|string, mixed>
 */
function resurrection_object_locations(string $hook): array {
    $vname = getsetting('villagename', LOCATION_FIELDS);
    $locs = array($vname => sprintf_translate("The Village of %s", $vname));
    $locs = modulehook($hook, $locs);
    $locs['all'] = translate_inline("Everywhere");
    ksort($locs);
    return $locs;
}

/**
 * The posted editor array (mount[...] or companion[...]): the POST carries only it and the protected fields, and it
 * carries only the form's fields; $required must all be present, $optional (checkboxes) may be absent.
 * @param list<string> $required
 * @param list<string> $optional
 * @return array<string, mixed>
 */
function resurrection_object_post(string $wrapper, array $required, array $optional = []): array {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', $wrapper]) !== []) throw new InvalidArgumentException('Unexpected field.');
    $posted = $_POST[$wrapper] ?? null;
    if (!is_array($posted) || array_diff($required, array_keys($posted)) !== [] ||
        array_diff(array_keys($posted), [...$required, ...$optional]) !== []) throw new InvalidArgumentException('Unexpected editor fields.');
    return $posted;
}

/** @param array<string, mixed> $posted */
function resurrection_object_text(array $posted, string $key, int $limit, bool $bytes = false): string {
    $value = $posted[$key] ?? null;
    if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid text.');
    if (($bytes ? strlen($value) : mb_strlen($value, 'UTF-8')) > $limit) throw new InvalidArgumentException('Text too long.');
    return $value;
}

/** @param array<string, mixed> $posted */
function resurrection_object_integer(array $posted, string $key, int $min, int $max): int {
    $value = $posted[$key] ?? null;
    if (!is_string($value) || !preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value)) throw new InvalidArgumentException('Invalid number.');
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < $min || $number > $max) throw new InvalidArgumentException('Number out of range.');
    return $number;
}

/**
 * A posted checkbox: absent is 0, checked is the form's value 1.
 * @param array<string, mixed> $posted
 */
function resurrection_object_flag(array $posted, string $key): int {
    if (!array_key_exists($key, $posted)) return 0;
    if ($posted[$key] !== '1') throw new InvalidArgumentException('Invalid checkbox.');
    return 1;
}

/** @param array<string, mixed> $posted */
function resurrection_object_location(array $posted, string $key, string $hook): string {
    $value = resurrection_object_text($posted, $key, 25);
    if (!in_array($value, array_map('strval', array_keys(resurrection_object_locations($hook))), true)) throw new InvalidArgumentException('Unknown location.');
    return $value;
}

/**
 * The serialized mount buff from mount[mountbuff], built as 1.1.2 built it: every non-empty field of the form, as
 * text, and the "mounts" schema (1.1.2 also stripped slashes, a magic-quotes leftover that would now eat backslashes).
 * It must pass the mount buff schema (BuffState::mount()) that the stables, Give and every owner's New Day apply; a
 * buff it refuses would stop every owner's New Day.
 * @param array<string, mixed> $posted
 */
function resurrection_mount_buff(array $posted): string {
    $fields = $posted['mountbuff'] ?? null;
    if (!is_array($fields) || array_diff(RESURRECTION_MOUNT_BUFF, array_keys($fields)) !== [] ||
        array_diff(array_keys($fields), RESURRECTION_MOUNT_BUFF) !== []) throw new InvalidArgumentException('Unexpected buff fields.');
    $buff = [];
    foreach (RESURRECTION_MOUNT_BUFF as $key) {
        $value = resurrection_object_text($fields, $key, 4096, true);
        if ($value !== '') $buff[$key] = $value;
    }
    $buff['schema'] = 'mounts';
    $encoded = serialize($buff);
    try {
        resurrection_stables_buff(['mountbuff' => $encoded]);
    } catch (UnexpectedValueException $error) {
        throw new InvalidArgumentException('Invalid mount buff.');
    }
    return $encoded;
}

/**
 * The serialized companion abilities from companion[abilities], in the shape and order 1.1.2's save gave a browser's
 * submission: a checked Fighter or Defender is "1" and comes first, the healer and magician levels are the form's
 * 0-30, and an unchecked box is appended as false.
 * @param array<string, mixed> $posted
 */
function resurrection_companion_abilities(array $posted): string {
    $fields = $posted['abilities'] ?? null;
    if (!is_array($fields) || array_diff(['heal', 'magic'], array_keys($fields)) !== [] ||
        array_diff(array_keys($fields), ['fight', 'defend', 'heal', 'magic']) !== []) throw new InvalidArgumentException('Unexpected abilities.');
    $abilities = [];
    foreach (['fight', 'defend'] as $flag) if (resurrection_object_flag($fields, $flag)) $abilities[$flag] = '1';
    foreach (['heal', 'magic'] as $level) $abilities[$level] = (string)resurrection_object_integer($fields, $level, 0, 30);
    foreach (['fight', 'defend'] as $flag) if (!isset($abilities[$flag])) $abilities[$flag] = false;
    return serialize($abilities);
}

/**
 * The mount form's values, each checked; the buff serialized.
 * @return array<string, string|int>
 */
function resurrection_mount_values(): array {
    $posted = resurrection_object_post('mount', [...array_keys(RESURRECTION_MOUNT_TEXT), ...array_keys(RESURRECTION_MOUNT_INT), 'mountlocation', 'mountbuff']);
    $values = [];
    foreach (RESURRECTION_MOUNT_TEXT as $key => [$limit, $bytes]) $values[$key] = resurrection_object_text($posted, $key, $limit, $bytes);
    $values['mountlocation'] = resurrection_object_location($posted, 'mountlocation', 'stablelocs');
    foreach (RESURRECTION_MOUNT_INT as $key => [$min, $max]) $values[$key] = resurrection_object_integer($posted, $key, $min, $max);
    $values['mountbuff'] = resurrection_mount_buff($posted);
    return $values;
}

/**
 * The companion form's values, each checked; the abilities serialized.
 * @return array<string, string|int>
 */
function resurrection_companion_values(): array {
    $posted = resurrection_object_post('companion', [...array_keys(RESURRECTION_COMPANION_TEXT), ...array_keys(RESURRECTION_COMPANION_INT), 'companionlocation', 'abilities'],
        RESURRECTION_COMPANION_FLAGS);
    $values = [];
    foreach (RESURRECTION_COMPANION_TEXT as $key => $limit) $values[$key] = resurrection_object_text($posted, $key, $limit, in_array($key, ['description', 'jointext'], true));
    // A companion is stored in its owner's list under its name, which combat requires to be 1-255 bytes.
    if ($values['name'] === '' || strlen($values['name']) > 255) throw new InvalidArgumentException('A companion needs a name.');
    $values['companionlocation'] = resurrection_object_location($posted, 'companionlocation', 'camplocs');
    foreach (RESURRECTION_COMPANION_INT as $key => [$min, $max]) $values[$key] = resurrection_object_integer($posted, $key, $min, $max);
    foreach (RESURRECTION_COMPANION_FLAGS as $key) $values[$key] = resurrection_object_flag($posted, $key);
    $values['abilities'] = resurrection_companion_abilities($posted);
    return $values;
}

/**
 * An editor save: the INSERT (ID 0) or the UPDATE of the row the form showed writes only the declared columns.
 * The cache entry is dropped after the commit. Returns the row's ID and the affected rows 1.1.2 reported on.
 * @param array<string, string|int> $values
 * @return array{int, int}
 */
function resurrection_object_save(string $table, string $column, string $kind, int $id, array $values, string $page): array {
    try {
        $row = $id > 0 ? resurrection_content_row($table, $column, $id) : null;
        if ($id > 0 && $row === null) throw new OutOfBoundsException('Not found.');
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action($kind . '-edit-' . $id, $row === null ? resurrection_content_context('add', []) : resurrection_content_context('edit', $row));
    try {
        $result = resurrection_player_mutation(function () use ($table, $column, $id, $row, $values) {
            $name = db_prefix($table);
            if ($row === null) {
                db_query("INSERT INTO $name (" . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')', true, array_values($values));
                $affected = db_affected_rows();
                return [(int)db_insert_id(), $affected];
            }
            $locked = resurrection_content_row($table, $column, $id, true);
            if ($locked === null || resurrection_content_context('edit', $locked) !== resurrection_content_context('edit', $row)) throw new DomainException('Row changed.');
            db_query("UPDATE $name SET " . implode(',', array_map(static fn (string $key) => "$key=?", array_keys($values))) . " WHERE $column=?", true, [...array_values($values), $id]);
            return [$id, db_affected_rows()];
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    invalidatedatacache($kind . 'data-' . $result[0]);
    return $result;
}

/** @return array{int, int} the mount's ID and the affected rows */
function resurrection_mount_save(int $id): array {
    $page = 'the mount editor';
    resurrection_content_require_form($page);
    try {
        $values = resurrection_mount_values();
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    return resurrection_object_save('mounts', 'mountid', 'mount', $id, $values, $page);
}

/** @return array{int, int} the companion's ID and the affected rows */
function resurrection_companion_save(int $id): array {
    $page = 'the companion editor';
    resurrection_content_require_form($page);
    try {
        $values = resurrection_companion_values();
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    return resurrection_object_save('companions', 'companionid', 'companion', $id, $values, $page);
}

/**
 * A row action form's context: the row as shown, and for a give or take the acting superuser's state it changes.
 * @param array<string, mixed> $row
 * @param array<string, mixed> $extra
 */
function resurrection_object_action_context(string $op, array $row, array $extra = []): string {
    return resurrection_content_context($op, [...$row, ...array_combine(array_map(static fn ($key) => "~$key", array_keys($extra)), $extra)]);
}

/**
 * The acting superuser's mount and buffs, which "Give" replaces.
 * @return array<string, mixed>
 */
function resurrection_mount_give_state(): array {
    global $session;
    return ['hashorse' => $session['user']['hashorse'], 'bufflist' => $session['user']['bufflist']];
}

/**
 * The acting superuser's level and companions, which "Take" uses and changes.
 * @return array<string, mixed>
 */
function resurrection_companion_take_state(): array {
    global $session;
    return ['level' => $session['user']['level'], 'companions' => $session['user']['companions']];
}

/** A mount's owners: the count the list shows and the delete's confirmation names. */
function resurrection_mount_owners(int $id, bool $lock = false): int {
    return count(db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE hashorse=?' . ($lock ? ' FOR UPDATE' : ''), true, [$id]));
}

/**
 * One row action through its protected form: the row must exist (404) before the intent is spent, and inside the
 * locked transaction it must still be the row (and state) the form showed (409). $action runs with the locked row.
 * @param callable(array<string, mixed>, bool): array<string, mixed> $extra the acting state the context includes, given the
 *     row and whether it is the locked recheck
 * @param callable(array<string, mixed>): mixed $action
 */
function resurrection_object_action(string $table, string $column, string $kind, string $op, int $id, string $page, callable $extra, callable $action): mixed {
    resurrection_content_require_form($page);
    try {
        resurrection_content_fields([]);
        $row = resurrection_content_row($table, $column, $id);
        if ($row === null) throw new OutOfBoundsException('Not found.');
        $context = resurrection_object_action_context($op, $row, $extra($row, false));
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    resurrection_consume_action("$kind-$op-$id", $context);
    try {
        $result = resurrection_player_mutation(function () use ($table, $column, $op, $id, $context, $extra, $action) {
            $locked = resurrection_content_row($table, $column, $id, true);
            if ($locked === null || resurrection_object_action_context($op, $locked, $extra($locked, true)) !== $context) throw new DomainException('Row changed.');
            return $action($locked);
        });
    } catch (Throwable $error) { resurrection_content_failure($error, $page); }
    invalidatedatacache($kind . 'data-' . $id);
    return $result;
}

/** Activate or deactivate a mount or companion. */
function resurrection_object_set_active(string $table, string $column, string $kind, string $flag, int $id, int $active, string $page): void {
    resurrection_object_action($table, $column, $kind, $active ? 'activate' : 'deactivate', $id, $page, static fn () => [],
        static function (array $row) use ($table, $column, $flag, $id, $active) {
            db_query('UPDATE ' . db_prefix($table) . " SET $flag=? WHERE $column=?", true, [$active, $id]);
        });
}

/**
 * Delete a mount: 1.1.2's full refund of the mount's price to every owner (gems, and gold to the bank), their mount
 * cleared, the mount and its object preferences deleted, all in one locked transaction. Only an inactive mount has a
 * delete form, and the form names the number of owners, so it is bound to that count too. The acting superuser, if an
 * owner, is refunded through the session so the page's own save keeps it. Owners keep the mount's buff until it wears
 * off or the next New Day, as in 1.1.2.
 */
function resurrection_mount_delete(int $id): void {
    resurrection_object_action('mounts', 'mountid', 'mount', 'del', $id, 'the mount editor',
        static fn (array $row, bool $lock) => ['owners' => resurrection_mount_owners($id, $lock)],
        static function (array $row) use ($id) {
            global $session;
            if ((int)$row['mountactive'] !== 0) throw new DomainException('Deactivate the mount first.');
            $gems = (int)$row['mountcostgems'];
            $gold = (int)$row['mountcostgold'];
            $self = (int)$session['user']['acctid'];
            db_query('UPDATE ' . db_prefix('accounts') . ' SET gems=gems+?, goldinbank=goldinbank+?, hashorse=0 WHERE hashorse=? AND acctid<>?', true, [$gems, $gold, $id, $self]);
            if ((int)$session['user']['hashorse'] === $id) {
                $session['user']['gems'] += $gems;
                $session['user']['goldinbank'] += $gold;
                $session['user']['hashorse'] = 0;
            }
            db_query('DELETE FROM ' . db_prefix('mounts') . ' WHERE mountid=?', true, [$id]);
            db_query('DELETE FROM ' . db_prefix('module_objprefs') . ' WHERE objtype=? AND objid=?', true, ['mounts', $id]);
        });
    massinvalidate("objpref-mounts-$id");
}

/** Delete an inactive companion and its object preferences. Companions players hold are theirs, as in 1.1.2. */
function resurrection_companion_delete(int $id): void {
    resurrection_object_action('companions', 'companionid', 'companion', 'del', $id, 'the companion editor', static fn () => [],
        static function (array $row) use ($id) {
            if ((int)$row['companionactive'] !== 0) throw new DomainException('Deactivate the companion first.');
            db_query('DELETE FROM ' . db_prefix('companions') . ' WHERE companionid=?', true, [$id]);
            db_query('DELETE FROM ' . db_prefix('module_objprefs') . ' WHERE objtype=? AND objid=?', true, ['companions', $id]);
        });
    massinvalidate("objpref-companions-$id");
}

/**
 * "Give": the acting superuser's own mount becomes this one, with its buff, as in 1.1.2 (no cost, no hooks). The
 * stored buff is checked as the stables check it before anything changes.
 */
function resurrection_mount_give(int $id): void {
    global $playermount;
    // The page's character stats show the new mount, as the stables do after a purchase.
    $playermount = resurrection_object_action('mounts', 'mountid', 'mount', 'give', $id, 'the mount editor', static fn () => resurrection_mount_give_state(),
        static function (array $row) use ($id) {
            global $session;
            $buff = resurrection_stables_buff($row);
            $session['user']['hashorse'] = $id;
            if (($buff['schema'] ?? '') == '') $buff['schema'] = 'mounts';
            try {
                apply_buff('mount', $buff);
            } catch (DomainException $error) {
                throw new UnexpectedValueException('Invalid stored mount buff.');
            }
            return $row;
        });
}

/**
 * "Take": the acting superuser gets this companion scaled to their level by 1.1.2's formulas, through the
 * alter-companion hook and apply_companion(). The companion list is checked as combat checks it before it is stored.
 * Returns the companion's name for 1.1.2's message.
 */
function resurrection_companion_take(int $id): string {
    return resurrection_object_action('companions', 'companionid', 'companion', 'take', $id, 'the companion editor', static fn () => resurrection_companion_take_state(),
        static function (array $row) {
            global $session, $companions;
            $row['attack'] = $row['attack'] + $row['attackperlevel'] * $session['user']['level'];
            $row['defense'] = $row['defense'] + $row['defenseperlevel'] * $session['user']['level'];
            $row['maxhitpoints'] = $row['maxhitpoints'] + $row['maxhitpointsperlevel'] * $session['user']['level'];
            $row['hitpoints'] = $row['maxhitpoints'];
            $row = modulehook("alter-companion", $row);
            $row['abilities'] = \Resurrection\Security\ScalarState::read($row['abilities']);
            try {
                if (!is_array($row['abilities'])) throw new DomainException('Invalid stored abilities.');
                apply_companion($row['name'], $row);
                \Resurrection\Game\ForestCompanionState::validate($companions);
            } catch (DomainException $error) {
                // A stored companion combat would refuse: nothing is taken (500), not a stale form (409).
                throw new UnexpectedValueException('Invalid companion.');
            }
            return (string)$row['name'];
        });
}
