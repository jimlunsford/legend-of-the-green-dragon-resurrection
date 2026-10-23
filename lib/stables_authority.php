<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Game/BuffState.php';

/**
 * A mount row, bound by id. Inside a purchase it is read under lock ($lock), so a mount the editor deactivates or
 * deletes meanwhile is seen as it is once that change commits, not as an earlier snapshot.
 * @return array<string, mixed>|null
 */
function resurrection_stables_mount(int $id, bool $lock = false): ?array {
    if ($id < 1) return null;
    $rows = db_query('SELECT * FROM ' . db_prefix('mounts') . ' WHERE mountid=?' . ($lock ? ' FOR UPDATE' : ''), true, [$id]);
    return count($rows) === 1 ? $rows[0] : null;
}

/**
 * A mount the stables offer this player: the 1.1.2 listing's rule (active, stabled here or everywhere,
 * dragon-kill cost met), which only the listing applied.
 * @param array<string, mixed> $mount
 */
function resurrection_stables_offered(array $mount): bool {
    global $session;
    return (int)$mount['mountactive'] === 1 &&
        in_array((string)$mount['mountlocation'], ['all', (string)$session['user']['location']], true) &&
        (int)$mount['mountdkcost'] <= (int)$session['user']['dragonkills'];
}

/**
 * A mount's stored buff, checked and normalized by the mount buff schema (BuffState::mount()); a malformed one stops
 * the action before anything is charged.
 * @param array<string, mixed> $mount
 * @return array<string, mixed>
 */
function resurrection_stables_buff(array $mount): array {
    global $session;
    try {
        return \Resurrection\Game\BuffState::mount(\Resurrection\Security\ScalarState::read($mount['mountbuff']), $session['user']);
    } catch (DomainException $error) {
        throw new UnexpectedValueException('Invalid stored mount buff.');
    }
}

/** A stables form is bound to its action, the mount, and the player's mount, purse, place, feeding and mount buff. */
function resurrection_stables_context(string $op, int $id = 0): string {
    global $session;
    $state = [$op, $id];
    foreach (['acctid','hashorse','gold','gems','location','dragonkills','level','fedmount'] as $key) $state[] = (string)$session['user'][$key];
    $buff = $session['bufflist']['mount'] ?? null;
    $state[] = is_array($buff) ? (string)($buff['rounds'] ?? '') : null;
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/** The protected POST form for a stables action; its button reads like the 1.1.2 link. */
function resurrection_stables_form(string $op, int $id, string $label): void {
    $url = 'stables.php?op=' . $op . ($id > 0 ? '&id=' . $id : '');
    addnav('', $url);
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'>" .
        resurrection_action_fields('stables-' . $op, resurrection_stables_context($op, $id)) . "<button type='submit' class='button'>");
    output_notl('%s', $label);
    rawoutput('</button></form>');
}

/** The mount id from the URL: a positive integer. */
function resurrection_stables_id(): int {
    $id = \Resurrection\Http\Input::integer($_GET, 'id', 0, 1);
    if ($id < 1) throw new InvalidArgumentException('Missing mount.');
    return $id;
}

/** A stables POST carries only the protected fields. */
function resurrection_stables_fields(): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token']) !== []) throw new InvalidArgumentException('Unexpected field.');
}

function resurrection_stables_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid stables request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your stable situation changed. Return to the stables and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the stables and try again.');
}
