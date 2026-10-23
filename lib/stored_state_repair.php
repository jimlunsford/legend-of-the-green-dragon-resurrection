<?php
/**
 * The user editor's repair path (`user.php?op=repair`, SU_EDIT_USERS) for an account locked out by stored state that
 * fails its schema.
 *
 * Six stored values can stop a player: the preferences, the buff list (with its potion entry), the companions and
 * the Dragon Point history are checked on every page, the return page with the navigation, and a stored fight by the
 * route that continues it. Each is judged by the schema the game already applies; this file adds no rule of its own.
 * Only a value that fails is shown, and each has one fixed repair, written in one locked transaction with a debug log
 * line naming the field and the administrator. Nothing else on the account changes.
 */
require_once __DIR__ . '/admin_authority.php';
require_once __DIR__ . '/debuglog.php';
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Security/PreferenceState.php';
require_once __DIR__ . '/../src/Security/NavigationState.php';
require_once __DIR__ . '/../src/Security/PvpState.php';
require_once __DIR__ . '/../src/Game/BuffState.php';
require_once __DIR__ . '/../src/Game/CompanionState.php';
require_once __DIR__ . '/../src/Game/DragonPointState.php';
require_once __DIR__ . '/../src/Game/TransmutationState.php';
require_once __DIR__ . '/../src/Game/ForestCombatState.php';
require_once __DIR__ . '/../src/Game/DragonCombatState.php';

use Resurrection\Game\BuffState;
use Resurrection\Game\CompanionState;
use Resurrection\Game\DragonPointState;
use Resurrection\Security\NavigationState;
use Resurrection\Security\PreferenceState;
use Resurrection\Security\ScalarState;

/** The stored values the repair path judges, in display order, with their labels and fixed repairs. */
const RESURRECTION_REPAIR_FIELDS = [
    'prefs' => ['Preferences', 'Reset the preferences to the defaults, as a new account has them.'],
    'bufflist' => ['Buff list', 'Empty the buff list.'],
    'companions' => ['Companions', 'Empty the companion list.'],
    'restorepage' => ['Return page', 'Set the return page to the village.'],
    'badguy' => ['Stored fight', 'Clear the stored fight.'],
    'dragonpoints' => ['Dragon Point history', 'Rebuild the history so that every point already spent stays spent: each entry that can still be read is kept, and every other spend is recorded as hit points, which grant nothing the account does not already hold.'],
];

/**
 * Whether a stored value fails its schema, by the check the game applies. Null when it passes, else the reason.
 * @param array<string,mixed> $row The account row as stored.
 */
function resurrection_repair_failure(string $field, array $row): ?string {
    try {
        match ($field) {
            'prefs' => PreferenceState::read($row['prefs']),
            'bufflist' => resurrection_repair_buffs($row),
            'companions' => CompanionState::read($row['companions']),
            'restorepage' => NavigationState::restorePage($row['restorepage']),
            'badguy' => resurrection_repair_fight($row),
            'dragonpoints' => DragonPointState::history($row['dragonpoints']),
        };
    } catch (DomainException | TypeError | ValueError $error) {
        // A type error is the route failing on the same value: the player is locked out just the same.
        return $error instanceof DomainException ? $error->getMessage() : 'The stored value cannot be read.';
    }
    return null;
}

/**
 * The failing values of an account, field => reason.
 * @param array<string,mixed> $row
 * @return array<string,string>
 */
function resurrection_repair_failures(array $row): array {
    $failures = [];
    foreach (array_keys(RESURRECTION_REPAIR_FIELDS) as $field) {
        $reason = resurrection_repair_failure($field, $row);
        if ($reason !== null) $failures[$field] = $reason;
    }
    return $failures;
}

/**
 * The session-load check of the buff list (lib/forcednavigation.php): the potion entry, then the list.
 * @param array<string,mixed> $row
 */
function resurrection_repair_buffs(array $row): void {
    $stored = in_array($row['bufflist'], [null, ''], true) ? [] : ScalarState::read($row['bufflist']);
    if (is_array($stored) && array_key_exists('transmute', $stored)) \Resurrection\Game\TransmutationState::read($stored['transmute']);
    BuffState::read($row['bufflist'], $row);
}

/**
 * A stored fight, judged by the route that continues it: the one its options name, a pre-envelope creature by the
 * Dragon (the only route that reads that shape), a pending Dragon victory by the Dragon's victory record. A fight no
 * route reads fails. Training and Graveyard fights also need the player state those routes check them against.
 * @param array<string,mixed> $row
 */
function resurrection_repair_fight(array $row): void {
    $stored = $row['badguy'];
    if ($stored === null || $stored === '') return;
    $state = ScalarState::read($stored);
    if (!is_array($state)) throw new DomainException('The stored fight cannot be read.');
    if (array_key_exists('dragonVictory', $state)) {
        \Resurrection\Game\DragonCombatState::victory($stored, DragonPointState::integer($row['dragonkills']));
        return;
    }
    $type = is_array($state['options'] ?? null) ? ($state['options']['type'] ?? null) : null;
    if ($type === null && isset($state['creaturename'])) $type = 'dragon';
    switch ($type) {
        case 'forest': \Resurrection\Game\ForestCombatState::read($stored); return;
        case 'dragon': \Resurrection\Game\DragonCombatState::read($stored); return;
        case 'pvp': \Resurrection\Security\PvpState::read($stored, (int)$row['acctid']); return;
        case 'graveyard':
            require_once __DIR__ . '/graveyard_combat.php';
            resurrection_graveyard_torment($row);
            return;
        case 'train':
            require_once __DIR__ . '/training_combat.php';
            $player = $row;
            // Training sizes the master from the spent Dragon Points. While the history itself fails, the fight is
            // judged against the history its repair writes, which is what Training will see once it is repaired.
            try { $player['dragonpoints'] = DragonPointState::history($row['dragonpoints']); }
            catch (DomainException $error) { $player['dragonpoints'] = resurrection_repair_dragon_points($row); }
            resurrection_training_fight($player, resurrection_training_masters($player));
            return;
    }
    throw new DomainException('No route continues this stored fight.');
}

/**
 * The repaired Dragon Point history. Every point the account has spent must stay spent: the New Day makes a player
 * spend each unspent point before anything else, and an empty history (1.1.2's reset) would let the player buy every
 * point again on top of the stats it already bought. What each lost spend bought is unknown, so it is recorded as
 * hit points: the Dragon kill carries maximum hit points over whole (10 plus the account's hit points above its level's
 * base), so an "hp" entry adds nothing at a kill, adds no forest fight, and adds nothing to a master's or the Dragon's
 * strength. A value that still reads as a list keeps its length and every valid entry; anything else becomes one spend
 * per Dragon kill.
 * @param array<string,mixed> $row
 * @return list<string>
 */
function resurrection_repair_dragon_points(array $row): array {
    $stored = ScalarState::read($row['dragonpoints']);
    if (is_array($stored)) {
        $points = [];
        foreach ($stored as $point) {
            $points[] = is_string($point) && preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $point) ? $point : 'hp';
        }
        return $points;
    }
    return array_fill(0, DragonPointState::integer($row['dragonkills']), 'hp');
}

/**
 * The fixed repair of a field, as stored.
 * @param array<string,mixed> $row
 */
function resurrection_repair_value(string $field, array $row): string {
    return match ($field) {
        'prefs' => PreferenceState::encode([]),
        'bufflist' => BuffState::encode([], $row),
        'companions' => CompanionState::encode([]),
        'restorepage' => 'village.php',
        'badguy' => '',
        'dragonpoints' => DragonPointState::encode(resurrection_repair_dragon_points($row)),
    };
}

/** The account row, as stored. @return array<string,mixed>|null */
function resurrection_repair_account(int $userid, bool $lock = false): ?array {
    $rows = db_query('SELECT * FROM ' . db_prefix('accounts') . ' WHERE acctid=?' . ($lock ? ' FOR UPDATE' : ''), true, [$userid]);
    return count($rows) === 1 ? $rows[0] : null;
}

/**
 * A repair form is bound to the account, the field and the failing value it was shown with.
 * @param array<string,mixed> $row
 */
function resurrection_repair_context(array $row, string $field): string {
    $value = $row[$field] === null ? null : bin2hex((string)$row[$field]);
    return hash('sha256', json_encode(['repair', (string)$row['acctid'], $field, $value], JSON_THROW_ON_ERROR));
}

/** The repair page: each failing value with its reason and its one repair. */
function resurrection_repair_page(int $userid, string $returnpetition): void {
    $row = resurrection_repair_account($userid);
    if ($row === null) { http_response_code(404); exit('Account not found.'); }
    addnav('Operations');
    addnav('Edit user', "user.php?op=edit&userid=$userid$returnpetition");
    $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    output('`bStored state repair`b`n');
    output('Only the stored values this account fails to load are listed. Each has one fixed repair, which changes nothing else.`n`n');
    $failures = resurrection_repair_failures($row);
    if ($failures === []) {
        output('Every stored value this account loads passes its check. There is nothing to repair.`n');
        return;
    }
    foreach ($failures as $field => $reason) {
        [$label, $repair] = RESURRECTION_REPAIR_FIELDS[$field];
        $url = "user.php?op=repair&userid=$userid&field=$field$returnpetition";
        addnav('', $url);
        $stored = (string)$row[$field];
        $shown = strlen($stored) > 4096 ? mb_strcut($stored, 0, 4096, 'UTF-8') . ' ...' : $stored;
        rawoutput('<div class="repair" id="repair-' . $field . '"><h3>' . $escape(translate_inline($label)) . '</h3>' .
            '<p>' . $escape(translate_inline('Fails its check:')) . ' ' . $escape($reason) . '</p>' .
            '<pre style="white-space:pre-wrap">' . $escape($shown) . '</pre>');
        try { resurrection_repair_value($field, $row); }
        catch (DomainException $error) {
            rawoutput('<p>' . $escape(translate_inline('There is no automatic repair: the repaired value would not fit its column.')) . '</p></div>');
            continue;
        }
        rawoutput('<form method="POST" action="' . $escape($url) . '">' .
            resurrection_action_fields('user-repair-' . $userid . '-' . $field, resurrection_repair_context($row, $field)) .
            '<p>' . $escape(translate_inline($repair)) . '</p><button type="submit" class="button">' . $escape(translate_inline('Repair')) . '</button></form></div>');
    }
}

/**
 * Repair one failing value: POST with CSRF and the one-use intent for that account and value; locked, rechecked
 * (the value must be unchanged and still failing), written alone, and logged.
 */
function resurrection_repair_field(int $userid, string $field): void {
    global $session;
    try {
        if (!array_key_exists($field, RESURRECTION_REPAIR_FIELDS)) throw new InvalidArgumentException('Unknown field.');
        resurrection_admin_fields();
        $row = resurrection_repair_account($userid);
        if ($row === null) throw new OutOfBoundsException('Account not found.');
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the repair page'); }
    resurrection_consume_action('user-repair-' . $userid . '-' . $field, resurrection_repair_context($row, $field));
    try {
        resurrection_admin_mutation(function () use ($userid, $field, $row): void {
            global $session, $companions;
            $locked = resurrection_repair_account($userid, true);
            if ($locked === null || resurrection_repair_context($locked, $field) !== resurrection_repair_context($row, $field)) throw new DomainException('Value changed.');
            // A value that passes its check is never "repaired".
            if (resurrection_repair_failure($field, $locked) === null) throw new DomainException('Value passes its check.');
            $value = resurrection_repair_value($field, $locked);
            db_query('UPDATE ' . db_prefix('accounts') . ' SET ' . db_identifier($field) . '=? WHERE acctid=?', true, [$value, $userid]);
            debuglog('Administrator ' . $session['user']['login'] . ' repaired the stored ' . $field . ': ' . RESURRECTION_REPAIR_FIELDS[$field][1], $userid);
            if ($userid === (int)$session['user']['acctid']) {
                // The administrator's own account: the locked session write must carry the repaired value.
                if ($field === 'bufflist') $session['bufflist'] = [];
                elseif ($field === 'companions') $companions = [];
                elseif ($field === 'prefs') $session['user']['prefs'] = [];
                elseif ($field === 'dragonpoints') $session['user']['dragonpoints'] = DragonPointState::history($value);
                else $session['user'][$field] = $value;
            }
        }, $userid);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the repair page'); }
    output('`@The stored %s was repaired.`0`n`n', translate_inline(RESURRECTION_REPAIR_FIELDS[$field][0]));
}
