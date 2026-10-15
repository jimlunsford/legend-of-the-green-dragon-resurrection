<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/systemmail.php';
require_once __DIR__ . '/clan/func.php';

/**
 * A clan membership row read from the database, never from the request.
 * @return array{acctid:int,login:string,name:string,clanid:int,clanrank:int,clanjoindate:?string}|null
 */
function resurrection_clan_account(int $id, bool $lock = false): ?array {
    if ($id < 1) return null;
    $rows = db_query('SELECT acctid,login,name,clanid,clanrank,clanjoindate FROM ' . db_prefix('accounts') . ' WHERE acctid=?' .
        ($lock ? ' FOR UPDATE' : ''), true, [$id]);
    if (count($rows) !== 1) return null;
    $row = $rows[0];
    return ['acctid'=>(int)$row['acctid'], 'login'=>(string)$row['login'], 'name'=>(string)$row['name'],
        'clanid'=>(int)$row['clanid'], 'clanrank'=>(int)$row['clanrank'], 'clanjoindate'=>$row['clanjoindate']];
}

/**
 * The rank operations 1.1.2's membership page offers an actor on one member row, with the rank each
 * would write (capped at the actor's rank, as the historical GREATEST/LEAST update did). Ranks come
 * only from the `clanranks` rank set; the target must be in the actor's clan.
 * @param array<int|string, mixed> $ranks
 * @param array{acctid:int,clanid:int,clanrank:int} $actor
 * @param array{acctid:int,clanid:int,clanrank:int} $target
 * @return array<string, int>
 */
function resurrection_clan_rank_actions(array $ranks, array $actor, array $target): array {
    $mine = $actor['clanrank']; $theirs = $target['clanrank']; $self = $actor['acctid'] === $target['acctid'];
    if ($actor['clanid'] < 1 || $mine <= CLAN_MEMBER || $target['clanid'] !== $actor['clanid']) return [];
    $cap = static fn(int $rank): int => max(0, min($mine, $rank));
    $actions = [];
    if ($theirs < $mine && $theirs < CLAN_FOUNDER) $actions['promote'] = $cap(clan_nextrank($ranks, $theirs));
    $previous = clan_previousrank($ranks, $theirs);
    if ($theirs <= $mine && $theirs > CLAN_APPLICANT && !$self && $previous > 0) $actions['demote'] = $cap($previous);
    elseif ($theirs == CLAN_FOUNDER && $self) $actions['stepdown'] = $cap($previous);
    if ($theirs <= $mine && !$self) $actions['remove'] = CLAN_APPLICANT;
    return $actions;
}

/**
 * @param array<int|string, mixed> $ranks
 * @param array<string, mixed> ...$rows
 */
function resurrection_clan_context(array $ranks, array ...$rows): string {
    $state = [array_map('intval', array_keys($ranks))];
    foreach ($rows as $row) $state[] = [$row['acctid'] ?? 0, $row['clanid'] ?? 0, $row['clanrank'] ?? 0];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

/**
 * One locked player transaction that also owns the systemmail notification queue: notices are written
 * with the transaction and external notification runs only after commit.
 * @param list<int> $participants Other accounts to lock, in ascending ID order with the actor.
 */
function resurrection_clan_mutation(callable $action, array $participants = []): mixed {
    if (isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested clan mutation.');
    $GLOBALS['mail_notifications'] = [];
    try {
        $result = resurrection_player_mutation($action, $participants);
        $notifications = $GLOBALS['mail_notifications'];
    } finally { unset($GLOBALS['mail_notifications']); }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return $result;
}

/**
 * Remove unread system notices with exactly this translated subject (bound, so any name works).
 * @param list<mixed> $subject
 */
function resurrection_clan_delete_notices(array $subject): void {
    db_query('DELETE FROM ' . db_prefix('mail') . ' WHERE msgfrom=0 AND seen=0 AND subject=?', true, [serialize($subject)]);
}

/**
 * Notify every officer and leader of a clan, as the historical callers did.
 * @param list<mixed> $subject
 * @param list<mixed> $body
 */
function resurrection_clan_notify_officers(int $clanid, int $except, array $subject, array $body): void {
    $rows = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE clanid=? AND clanrank>=? AND acctid<>? ORDER BY acctid', true,
        [$clanid, CLAN_OFFICER, $except]);
    foreach ($rows as $row) systemmail((int)$row['acctid'], $subject, $body);
}

function resurrection_clan_form(string $url, string $scope, string $context, string $buttons, string $attributes = ''): void {
    addnav('', $url);
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $attributes . '>' .
        resurrection_action_fields($scope, $context) . $buttons . '</form>');
}

function resurrection_clan_button(string $label, string $name = '', string $value = ''): string {
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<button class="button"' . ($name === '' ? '' : ' name="' . $escape($name) . '" value="' . $escape($value) . '"') .
        '>' . $escape(full_sanitize($label)) . '</button> ';
}

/**
 * POST bodies may carry only the protected fields and the named choices.
 * @param list<string> $extra
 */
function resurrection_clan_fields(array $extra = []): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$extra]) !== []) {
        throw new InvalidArgumentException('Unexpected field.');
    }
}

/** Typed POST outcome codes shared by the clan routes. */
function resurrection_clan_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid clan request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your clan situation changed. Return to the clan halls and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the clan halls and try again.');
}

/** A clan the application list offers: it exists, has a member above applicant and no module hides it. */
function resurrection_clan_offered(int $clanid): bool {
    $rows = db_query('SELECT c.clanid,c.clanname,count(a.acctid) AS c FROM ' . db_prefix('clans') . ' c INNER JOIN ' . db_prefix('accounts') .
        ' a ON c.clanid=a.clanid WHERE c.clanid=? AND a.clanrank>? GROUP BY c.clanid,c.clanname', true, [$clanid, CLAN_APPLICANT]);
    if (count($rows) !== 1) return false;
    $row = modulehook('clan-applymember', $rows[0]);
    return !(isset($row['handled']) && $row['handled']);
}

/** The new-clan form is bound to the actor's clan state, purse and the configured fees. */
function resurrection_clan_new_context(): string {
    global $session;
    $state = [(int)getsetting('goldtostartclan', 10000), (int)getsetting('gemstostartclan', 15)];
    foreach (['acctid','clanid','clanrank','gold','gems'] as $key) $state[] = (int)$session['user'][$key];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}
