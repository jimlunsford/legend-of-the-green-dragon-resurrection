<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/systemmail.php';

/** The favor a haunt costs (1.1.2 constant). */
const RESURRECTION_HAUNT_COST = 25;

/** The target login from the URL: a non-empty string. */
function resurrection_haunt_login(): string {
    $login = \Resurrection\Http\Input::string($_GET, 'name');
    if ($login === '' || strlen($login) > 255) throw new InvalidArgumentException('Missing haunt target.');
    return $login;
}

/** @return array<string, mixed>|null An unlocked account offered for haunting, bound by login (the 1.1.2 listing's `locked=0` rule). */
function resurrection_haunt_target(string $login): ?array {
    $rows = db_query('SELECT acctid,login,name,level,hauntedby FROM ' . db_prefix('accounts') . ' WHERE login=? AND locked=0', true, [$login]);
    return count($rows) === 1 ? $rows[0] : null;
}

/**
 * A haunt form is bound to the target and the haunting player's favor, level and life.
 * @param array<string, mixed> $target
 */
function resurrection_haunt_context(array $target): string {
    global $session;
    $state = ['haunt3', (string)$target['acctid'], (string)$target['login']];
    foreach (['acctid','deathpower','level','alive','name'] as $key) $state[] = (string)$session['user'][$key];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The protected POST form that haunts; its button reads like the 1.1.2 target link.
 * @param array<string, mixed> $target
 */
function resurrection_haunt_form(array $target): void {
    $url = 'graveyard.php?op=haunt3&name=' . rawurlencode((string)$target['login']);
    addnav('', $url);
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'>" .
        resurrection_action_fields('graveyard-haunt', resurrection_haunt_context($target)) . "<button type='submit' class='button'>");
    output_notl('%s', sprintf_translate('Haunt %s`0 (%s favor)', $target['name'], RESURRECTION_HAUNT_COST));
    rawoutput('</button></form>');
}

/** A haunt POST carries only the protected fields (graveyard.php's handle_event() always sets `i_am_a_hack`). */
function resurrection_haunt_fields(): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', 'i_am_a_hack']) !== []) throw new InvalidArgumentException('Unexpected field.');
}

/**
 * The haunt and its notice in one locked transaction with the target's account; the
 * notice's external notification runs only after commit.
 */
function resurrection_haunt_mutation(callable $action, int $target): mixed {
    if (isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested haunt mutation.');
    $GLOBALS['mail_notifications'] = [];
    try {
        $result = resurrection_player_mutation($action, [$target]);
        $notifications = $GLOBALS['mail_notifications'];
    } finally { unset($GLOBALS['mail_notifications']); }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return $result;
}

function resurrection_haunt_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid haunt request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your haunt changed. Return to the Mausoleum and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the Mausoleum and try again.');
}
