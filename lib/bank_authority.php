<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/systemmail.php';

/**
 * A bank amount: empty (the historical "0 or nothing") or a canonical non-negative integer.
 * 1.1.2 used abs((int)$value), which turned "-50" into 50, "abc" into "all" and "12abc" into 12.
 */
function resurrection_bank_amount(string $key = 'amount'): int {
    $value = trim(\Resurrection\Http\Input::string($_POST, $key));
    if ($value === '') return 0;
    if (!preg_match('/\A[0-9]+\z/', $value)) throw new InvalidArgumentException('Invalid amount.');
    return \Resurrection\Http\Input::integer([$key => $value], $key);
}

/** A bank form is bound to its operation, the amount it carries and the actor's purse, balance and daily counters. */
function resurrection_bank_context(string $scope, int $amount = 0): string {
    global $session;
    $state = [$scope, $amount];
    foreach (['acctid','level','dragonkills','gold','goldinbank','amountouttoday','transferredtoday'] as $key) $state[] = (int)$session['user'][$key];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

function resurrection_bank_fields(string $scope, int $amount = 0): string {
    return resurrection_action_fields($scope, resurrection_bank_context($scope, $amount));
}

/**
 * POST bodies may carry only the protected fields and the named inputs.
 * @param list<string> $extra
 */
function resurrection_bank_allow(array $extra): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$extra]) !== []) throw new InvalidArgumentException('Unexpected field.');
}

/** The transfer service, as the 1.1.2 navigation offered it: enabled, and the minimum level or a dragon kill. */
function resurrection_bank_transfers_allowed(): bool {
    global $session;
    return (bool)getsetting('allowgoldtransfer', 1) &&
        ((int)$session['user']['level'] >= (int)getsetting('mintransferlev', 3) || (int)$session['user']['dragonkills'] > 0);
}

/**
 * The 1.1.2 recipient search: every typed character in order, anything between. Characters are UTF-8
 * characters, and LIKE wildcards typed by the player match only themselves.
 */
function resurrection_bank_search_pattern(string $to): string {
    if (!mb_check_encoding($to, 'UTF-8')) throw new InvalidArgumentException('Invalid recipient.');
    $pattern = '%';
    foreach (mb_str_split($to, 1, 'UTF-8') as $character) $pattern .= strtr($character, ['|'=>'||', '%'=>'|%', '_'=>'|_']) . '%';
    return $pattern;
}

/**
 * The 1.1.2 withdrawfinish arithmetic. "0" withdraws the whole balance (or, on the borrow form while in
 * debt, asks for the debt's size); borrowing first withdraws any balance; the total debt may reach
 * level x borrowperlevel. 'refused' is the 1.1.2 inner refusal: its condition is the outer limit negated,
 * so it cannot occur, but it is kept with its message.
 * @return array{outcome:string, amount:int, withdrew:int, borrowed:int, gold:int, bank:int}
 */
function resurrection_bank_withdrawal(int $gold, int $bank, int $amount, bool $borrow, int $maxborrow): array {
    if ($amount === 0) $amount = abs($bank);
    $result = ['outcome'=>'withdraw', 'amount'=>$amount, 'withdrew'=>0, 'borrowed'=>0, 'gold'=>$gold, 'bank'=>$bank];
    if ($amount > $bank && !$borrow) return array_replace($result, ['outcome'=>'short']);
    if ($amount <= $bank) return array_replace($result, ['gold'=>$gold + $amount, 'bank'=>$bank - $amount]);
    $left = $amount;
    if ($left > $bank + $maxborrow) return array_replace($result, ['outcome'=>'limit']);
    if ($bank > 0) {
        $left -= $bank; $gold += $bank; $result['withdrew'] = $bank; $bank = 0;
    }
    if ($left - $bank > $maxborrow) return array_replace($result, ['outcome'=>'refused', 'borrowed'=>$left, 'gold'=>$gold, 'bank'=>$bank]);
    return array_replace($result, ['outcome'=>'borrow', 'borrowed'=>$left, 'gold'=>$gold + $left, 'bank'=>$bank - $left]);
}

/**
 * One locked player transaction that owns the systemmail notification queue: the notice is written with
 * the transaction and external delivery runs only after commit.
 * @param list<int> $participants
 */
function resurrection_bank_mutation(callable $action, array $participants = []): mixed {
    if (isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested bank mutation.');
    $GLOBALS['mail_notifications'] = [];
    try {
        $result = resurrection_player_mutation($action, $participants);
        $notifications = $GLOBALS['mail_notifications'];
    } finally { unset($GLOBALS['mail_notifications']); }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return $result;
}

function resurrection_bank_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid bank request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your account changed. Return to the bank and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the bank and try again.');
}

function resurrection_bank_attribute(mixed $text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
