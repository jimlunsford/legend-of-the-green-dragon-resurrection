<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Game/CompanionState.php';

/*
 * Paid player services: the mercenary camp (hire, heal a companion), the healer (potions, heal a companion) and the
 * gypsy seance. 1.1.2 charged on a GET, guarded only by allowed navigation, and the camp and the healer's companion
 * heal took the price from the URL. Now a GET shows a confirming form; only its POST, with CSRF and a one-use intent
 * bound to the player's current state, charges, on the locked account inside resurrection_player_mutation(), with
 * the price and eligibility worked out by the server. Prices, formulas, texts and hooks are 1.1.2's.
 */

/**
 * A form's intent context: the action, its target, and the player state that priced and offered it.
 * @param list<int|string> $extra the price the form showed
 */
function resurrection_service_context(string $action, string $target, array $extra = []): string {
    global $session, $companions;
    $state = [$action, $target];
    foreach (['acctid', 'gold', 'gems', 'level', 'dragonkills', 'location', 'hitpoints', 'maxhitpoints'] as $key) {
        $state[] = (string)$session['user'][$key];
    }
    $state[] = \Resurrection\Game\CompanionState::encode(is_array($companions) ? $companions : []);
    return hash('sha256', json_encode([...$state, ...$extra], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The protected POST form for a service; its button reads like the 1.1.2 link.
 * @param string|array<int, mixed> $label translated LoGD text, or a format array as addnav() takes it (translated here).
 */
function resurrection_service_form(string $url, string $scope, string $context, string|array $label, bool $primary = false): void {
    addnav('', $url);
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'>" .
        resurrection_action_fields($scope, $context) . "<button type='submit' class='" . resurrection_button_class($primary) . "'>");
    if (is_array($label)) output(...$label); else output_notl('%s', $label);
    rawoutput('</button></form>');
}

/** A service POST carries only the protected fields. */
function resurrection_service_fields(): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token']) !== []) throw new InvalidArgumentException('Unexpected field.');
}

function resurrection_service_failure(Throwable $error, string $place): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit("Your situation changed. Return to $place and try again."); }
    http_response_code(500); exit("Nothing was changed. Return to $place and try again.");
}

function resurrection_service_posted(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/**
 * A companion's healing price, as the mercenary camp and the healer listed it in 1.1.2, or null when it cannot be
 * healed (cannotbehealed, or not hurt).
 * @param array<string, mixed> $companion
 */
function resurrection_companion_heal_cost(array $companion): ?int {
    global $session;
    if (isset($companion['cannotbehealed']) && $companion['cannotbehealed'] == true) return null;
    $points = $companion['maxhitpoints'] - $companion['hitpoints'];
    if ($points <= 0) return null;
    return (int)round(log($session['user']['level'] + 1) * ($points + 10) * 1.33);
}

/** The companion key from the URL: one the player leads now (1.1.2 created an empty companion for any other). */
function resurrection_companion_key(): string {
    global $companions;
    $name = \Resurrection\Http\Input::string($_GET, 'name');
    if ($name === '' || !is_array($companions) || !array_key_exists($name, $companions)) throw new DomainException('No such companion.');
    return $name;
}

/**
 * Heal a companion to full on the locked account: the server's price, the player's gold. Returns the price, or null
 * when the gold is short (nothing changes).
 */
function resurrection_companion_heal(string $name, bool $log): ?int {
    global $session, $companions;
    if (!isset($companions[$name])) throw new DomainException('No such companion.');
    $cost = resurrection_companion_heal_cost($companions[$name]);
    if ($cost === null) throw new DomainException('Nothing to heal.');
    if ($session['user']['gold'] < $cost) return null;
    $companions[$name]['hitpoints'] = $companions[$name]['maxhitpoints'];
    $session['user']['gold'] -= $cost;
    if ($log) debuglog("spent $cost gold on healing a companion", false, false, "healcompanion", $cost);
    return $cost;
}

/**
 * A companion the camp offers this player, by id: the 1.1.2 listing's rule (active, here or everywhere, dragon-kill
 * cost met), which only the listing applied. Inside a hire the row is read under lock.
 * @return array<string, mixed>|null
 */
function resurrection_mercenary_offered(int $id, bool $lock = false): ?array {
    global $session;
    $rows = db_query('SELECT * FROM ' . db_prefix('companions') . ' WHERE companionid=?' . ($lock ? ' FOR UPDATE' : ''), true, [$id]);
    if (count($rows) !== 1) return null;
    $row = $rows[0];
    if ((int)$row['companionactive'] !== 1 || (int)$row['companioncostdks'] > (int)$session['user']['dragonkills'] ||
        !in_array((string)$row['companionlocation'], ['all', (string)$session['user']['location']], true)) return null;
    return $row;
}

/**
 * The camp's listing rule for a link: affordable and not already following the player.
 * @param array<string, mixed> $row the row after the alter-companion hook
 */
function resurrection_mercenary_affordable(array $row): bool {
    global $session, $companions;
    return $session['user']['gold'] >= $row['companioncostgold'] && $session['user']['gems'] >= $row['companioncostgems'] &&
        !isset($companions[$row['name']]);
}

/**
 * A hire form is bound to the companion, the price it showed and the player state.
 * @param array<string, mixed> $row the row after the alter-companion hook
 */
function resurrection_mercenary_context(int $id, array $row): string {
    return resurrection_service_context('mercenary-hire', (string)$id, [(int)$row['companioncostgold'], (int)$row['companioncostgems']]);
}

/**
 * The 1.1.2 hire, on the locked account: stats scaled by level, the alter-companion hook, the abilities schema, the
 * companion limit, then gold and gems at the price the form showed. Outcomes: hired, toomany (1.1.2's text, nothing
 * changes).
 * @param array{0:int, 1:int} $price gold and gems the form showed
 * @return array{outcome:string, row:array<string, mixed>}
 */
function resurrection_mercenary_hire(int $id, array $price): array {
    global $session;
    $row = resurrection_mercenary_offered($id, true);
    if ($row === null) throw new DomainException('Companion not offered.');
    $row['attack'] = $row['attack'] + $row['attackperlevel'] * $session['user']['level'];
    $row['defense'] = $row['defense'] + $row['defenseperlevel'] * $session['user']['level'];
    $row['maxhitpoints'] = $row['maxhitpoints'] + $row['maxhitpointsperlevel'] * $session['user']['level'];
    $row['hitpoints'] = $row['maxhitpoints'];
    $row = modulehook("alter-companion", $row);
    if ([(int)$row['companioncostgold'], (int)$row['companioncostgems']] !== $price) throw new DomainException('Companion repriced.');
    if (!resurrection_mercenary_affordable($row)) throw new DomainException('Companion not affordable.');
    // The stored abilities and the hired companion must pass the companion schema (src/Game/CompanionState.php)
    // before anything is charged or stored. 1.1.2 applied and saved whatever the row held.
    try {
        $row['abilities'] = \Resurrection\Game\CompanionState::abilities(\Resurrection\Security\ScalarState::read($row['abilities']));
        $hired = apply_companion($row['name'], $row);
    } catch (DomainException $error) {
        throw new UnexpectedValueException('Invalid stored companion.');
    }
    if (!$hired) return ['outcome'=>'toomany', 'row'=>$row];
    $session['user']['gold'] -= $row['companioncostgold'];
    $session['user']['gems'] -= $row['companioncostgems'];
    debuglog("has spent {$row['companioncostgold']} gold and {$row['companioncostgems']} gems on hiring a mercenary ({$row['name']}).");
    return ['outcome'=>'hired', 'row'=>$row];
}

/** The healer's price for a complete heal, 1.1.2's formula with the healmultiply hook. */
function resurrection_healer_cost(): int {
    global $session;
    $cost = log($session['user']['level']) * (($session['user']['maxhitpoints'] - $session['user']['hitpoints']) + 10);
    $result = modulehook("healmultiply", array("alterpct"=>1.0));
    $cost *= $result['alterpct'];
    return (int)round($cost, 0);
}

/** The potion sizes the healer lists: 100% and 90% down to 10%. */
function resurrection_healer_percent(): int {
    return (int)\Resurrection\Http\Input::choice($_GET, 'pct', ['100', '90', '80', '70', '60', '50', '40', '30', '20', '10'], '100');
}

/**
 * The 1.1.2 potion, on the locked account. Returns [price, points healed], or [price, null] when the gold is short
 * (nothing changes). A player who is not hurt is offered no potion.
 * @return array{0:int, 1:int|null}
 */
function resurrection_healer_potion(int $pct): array {
    global $session;
    if ($session['user']['hitpoints'] >= $session['user']['maxhitpoints']) throw new DomainException('Nothing to heal.');
    $newcost = (int)round($pct * resurrection_healer_cost() / 100, 0);
    if ($session['user']['gold'] < $newcost) return [$newcost, null];
    $session['user']['gold'] -= $newcost;
    debuglog("spent gold on healing", false, false, "healing", $newcost);
    $diff = (int)round(($session['user']['maxhitpoints'] - $session['user']['hitpoints']) * $pct / 100, 0);
    $session['user']['hitpoints'] += $diff;
    return [$newcost, $diff];
}

/** The healer's return target: none (the forest) or the village; 1.1.2 also offered any URL the request named. */
function resurrection_healer_return(): string {
    return \Resurrection\Http\Input::choice($_GET, 'return', ['', 'village.php'], '');
}

/** The seance price (1.1.2). */
function resurrection_gypsy_cost(): int {
    global $session;
    return (int)$session['user']['level'] * 20;
}

/** The paid seance, on the locked account: true when paid, false when the gold is short (nothing changes). */
function resurrection_gypsy_pay(): bool {
    global $session;
    $cost = resurrection_gypsy_cost();
    if ($session['user']['gold'] < $cost) return false;
    $session['user']['gold'] -= $cost;
    debuglog("spent $cost gold to speak to the dead");
    return true;
}
