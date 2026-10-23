<?php
require_once __DIR__ . '/specialty_onboarding.php';

/**
 * Historical room prices: expense = round(level * (10 + ln level)); the bank adds the innfee setting,
 * either a percentage of expense ("5%") or a flat amount. Leading-number parsing reproduces the
 * PHP 5 arithmetic without PHP 8's non-numeric warnings.
 * @return array{expense:int, bank:int, fee:string, percent:bool}
 */
function resurrection_inn_room_prices(int $level): array {
    $expense = (int)round($level * (10 + log($level)), 0);
    $fee = (string)getsetting('innfee', '5%');
    $amount = preg_match('/^\s*[+-]?(\d+(\.\d*)?|\.\d+)/', $fee, $match) ? (float)$match[0] : 0.0;
    $percent = (bool)strpos($fee, '%');
    $bank = $percent ? $expense + (int)round($expense * $amount / 100, 0) : (int)($expense + $amount);
    return ['expense'=>$expense, 'bank'=>$bank, 'fee'=>$fee, 'percent'=>$percent];
}

function resurrection_inn_room_context(string $pay): string {
    global $session;
    $state = ['pay'=>$pay, 'fee'=>(string)getsetting('innfee', '5%')];
    foreach (['acctid','level','gold','goldinbank','boughtroomtoday','location','loggedin','alive','authversion'] as $key) {
        $state[$key] = (string)$session['user'][$key];
    }
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

/**
 * The offered bribe tiers for the current level, exactly as 1.1.2 listed them.
 * @return array{gem: list<int>, gold: list<int>}
 */
function resurrection_inn_bribe_tiers(int $level): array {
    return ['gem'=>[1, 2, 3], 'gold'=>[$level * 10, $level * 50, $level * 100]];
}

function resurrection_inn_bribe_context(): string {
    global $session;
    $state = [];
    foreach (['acctid','level','gold','gems','alive'] as $key) $state[$key] = (string)$session['user'][$key];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

/**
 * The bartender's historical chance of answering, for an offered tier only.
 */
function resurrection_inn_bribe_chance(string $type, int $amount, int $level): float {
    if ($type === 'gem') return $amount * 30;
    return ($amount / $level - 10) * (50 / 90) + 25;
}

/** A successful bribe unlocks one visit to the bartender's services; it is bound to the account. */
function resurrection_inn_bribed(): bool {
    global $session;
    return isset($_SESSION['inn_bribe']) && $_SESSION['inn_bribe'] === (int)$session['user']['acctid'];
}

/** @param array<string, string> $choices */
function resurrection_inn_specialty_context(array $choices): string {
    global $session;
    return hash('sha256', json_encode([(int)$session['user']['acctid'], $session['user']['specialty'], $choices], JSON_THROW_ON_ERROR));
}

/**
 * The Dragon encounter the accepted Forest authority displays after `op=dragon` sets seendragon:
 * alive, level 15+, no running fight or special event.
 */
function resurrection_inn_flee_available(): bool {
    global $session;
    return !empty($session['loggedin']) && !empty($session['user']['alive']) && $session['user']['hitpoints'] > 0 &&
        (int)$session['user']['level'] >= 15 && (int)$session['user']['seendragon'] === 1 &&
        $session['user']['badguy'] === '' && $session['user']['specialinc'] === '';
}

function resurrection_inn_flee_context(): string {
    global $session;
    $state = [];
    foreach (['acctid','level','seendragon','badguy','specialinc','alive','dragonkills','charm','location'] as $key) {
        $state[$key] = (string)$session['user'][$key];
    }
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

/** Called by the Forest Dragon encounter: this encounter, and only it, may be fled once. */
function resurrection_inn_flee_offer(): void {
    $_SESSION['inn_flee'] = resurrection_inn_flee_context();
}

function resurrection_inn_flee_offered(): bool {
    return resurrection_inn_flee_available() && isset($_SESSION['inn_flee']) &&
        hash_equals($_SESSION['inn_flee'], resurrection_inn_flee_context());
}

function resurrection_inn_form(string $url, string $scope, string $context, string $buttons): void {
    addnav('', $url);
    rawoutput('<form method="POST" action="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
        resurrection_action_fields($scope, $context) . $buttons . '</form>');
}

function resurrection_inn_button(string $label, string $name = '', string $value = ''): string {
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<button class="button"' . ($name === '' ? '' : ' name="' . $escape($name) . '" value="' . $escape($value) . '"') .
        '>' . $escape($label) . '</button> ';
}

/**
 * POST bodies may carry only the protected fields and the named choice.
 * @param list<string> $extra
 */
function resurrection_inn_fields(array $extra = []): void {
    // handle_event()'s internal dispatch marker is not a player form field (as in the Dark Horse bartender).
    unset($_POST['i_am_a_hack']);
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$extra]) !== []) {
        throw new InvalidArgumentException('Unexpected field.');
    }
}
