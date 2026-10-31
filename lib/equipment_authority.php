<?php
require_once __DIR__ . '/player_mutation.php';

/**
 * The two near-mirror shops (weapons.php, armor.php): table, columns and the player fields each sets.
 * @return array{kind:string, table:string, id:string, name:string, stat:string, item:string, itemstat:string, itemvalue:string, total:string, hook:string, page:string}
 */
function resurrection_equipment_shop(string $kind): array {
    if ($kind === 'weapon') {
        return ['kind'=>'weapon', 'table'=>'weapons', 'id'=>'weaponid', 'name'=>'weaponname', 'stat'=>'damage', 'item'=>'weapon',
            'itemstat'=>'weapondmg', 'itemvalue'=>'weaponvalue', 'total'=>'attack', 'hook'=>'modify-weapon', 'page'=>'weapons.php'];
    }
    return ['kind'=>'armor', 'table'=>'armor', 'id'=>'armorid', 'name'=>'armorname', 'stat'=>'defense', 'item'=>'armor',
        'itemstat'=>'armordef', 'itemvalue'=>'armorvalue', 'total'=>'defense', 'hook'=>'modify-armor', 'page'=>'armor.php'];
}

/**
 * The tier the shop offers: the highest item level at or below the player's dragon kills (1.1.2).
 * @param array<string, string> $shop
 */
function resurrection_equipment_tier(array $shop, int $dragonkills): int {
    $rows = db_query('SELECT max(level) AS level FROM ' . db_prefix($shop['table']) . ' WHERE level<=?', true, [$dragonkills]);
    return (int)($rows[0]['level'] ?? 0);
}

/**
 * 75% of the stored value of the current item, rounded (1.1.2).
 * @param array<string, string> $shop
 */
function resurrection_equipment_tradein(array $shop): int {
    global $session;
    return (int)round($session['user'][$shop['itemvalue']] * .75, 0);
}

/**
 * A purchase form is bound to the item, the offered tier and the player's purse, current item and stats.
 * @param array<string, string> $shop
 */
function resurrection_equipment_context(array $shop, int $id, int $tier): string {
    global $session;
    $state = [$shop['kind'], $id, $tier];
    foreach (['acctid', 'dragonkills', 'gold', $shop['item'], $shop['itemstat'], $shop['itemvalue'], $shop['total']] as $key) {
        $state[] = (string)$session['user'][$key];
    }
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The item as this shop offers it now: it exists, is in the offered tier, and the shop's item hook neither
 * skips nor withholds it (the listing hid or unlinked those).
 * @param array<string, string> $shop
 * @return array<string, mixed>|null
 */
function resurrection_equipment_offered(array $shop, int $id, int $tier): ?array {
    $rows = db_query('SELECT * FROM ' . db_prefix($shop['table']) . ' WHERE ' . $shop['id'] . '=?', true, [$id]);
    if (count($rows) !== 1 || (int)$rows[0]['level'] !== $tier) return null;
    $row = modulehook($shop['hook'], $rows[0]);
    if ((isset($row['skip']) && $row['skip'] === true) || (isset($row['unavailable']) && $row['unavailable'] == true)) return null;
    return $row;
}

/**
 * The 1.1.2 purchase, on the locked account inside resurrection_player_mutation(): funds count the trade-in;
 * gold, item, item stat, item value and the attack or defense total change together.
 * @param array<string, string> $shop
 * @return array{outcome:string, old?:string, row?:array<string, mixed>}
 */
function resurrection_equipment_purchase(array $shop, int $id): array {
    global $session;
    $row = resurrection_equipment_offered($shop, $id, resurrection_equipment_tier($shop, (int)$session['user']['dragonkills']));
    if ($row === null) return ['outcome'=>'missing'];
    $tradeinvalue = resurrection_equipment_tradein($shop);
    if ($row['value'] > ($session['user']['gold'] + $tradeinvalue)) return ['outcome'=>'short', 'row'=>$row];
    $old = (string)$session['user'][$shop['item']];
    debuglog("spent " . ($row['value'] - $tradeinvalue) . " gold on the " . $row[$shop['name']] . " " . $shop['kind']);
    $session['user']['gold'] -= $row['value'];
    $session['user'][$shop['item']] = $row[$shop['name']];
    $session['user']['gold'] += $tradeinvalue;
    $session['user'][$shop['total']] -= $session['user'][$shop['itemstat']];
    $session['user'][$shop['itemstat']] = $row[$shop['stat']];
    $session['user'][$shop['total']] += $session['user'][$shop['itemstat']];
    $session['user'][$shop['itemvalue']] = $row['value'];
    return ['outcome'=>'bought', 'old'=>$old, 'row'=>$row];
}

/**
 * One buy form per offered, affordable item, shown where 1.1.2 drew the item's link.
 * @param array<string, string> $shop
 */
function resurrection_equipment_form_open(array $shop, int $id, int $tier): void {
    $url = $shop['page'] . '?op=buy&id=' . $id;
    addnav('', $url);
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'" . resurrection_presentation('inline-form-flush') . ">" .
        resurrection_action_fields($shop['kind'] . '-buy-' . $id, resurrection_equipment_context($shop, $id, $tier)) .
        "<button type='submit'" . resurrection_presentation('shop-button') . ">");
}

function resurrection_equipment_form_close(): void {
    rawoutput('</button></form>');
}

/** A purchase POST carries only the protected fields; the item is the URL's id. */
function resurrection_equipment_request(): int {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token']) !== []) throw new InvalidArgumentException('Unexpected field.');
    $id = \Resurrection\Http\Input::integer($_GET, 'id', 0, 1);
    if ($id < 1) throw new InvalidArgumentException('Missing item.');
    return $id;
}

function resurrection_equipment_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid purchase request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your purchase changed. Return to the shop and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the shop and try again.');
}
