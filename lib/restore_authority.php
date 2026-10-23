<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/haunt_authority.php';

/**
 * The 1.1.2 Mausoleum price: round(10 x missing soulpoints / max), max = level x 5 + 50. A deficit
 * under a twentieth of max rounds to 0 and restores for free, as in 1.1.2.
 * @return array{max: int, cost: int}
 */
function resurrection_restore_price(int $level, int $soulpoints): array {
    $max = $level * 5 + 50;
    return ['max' => $max, 'cost' => (int)round(10 * ($max - $soulpoints) / $max)];
}

/** A restore form is bound to the player's favor, soul, level and life. */
function resurrection_restore_context(): string {
    global $session;
    $state = ['restore'];
    foreach (['acctid','deathpower','soulpoints','level','alive'] as $key) $state[] = (string)$session['user'][$key];
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
}

/** The protected POST form that restores; its button reads like the 1.1.2 "Restore Your Soul" link. */
function resurrection_restore_form(int $cost): void {
    $url = 'graveyard.php?op=restore';
    addnav('', $url);
    rawoutput("<form method='POST' action='$url'>" . resurrection_action_fields('graveyard-restore', resurrection_restore_context()) .
        "<button type='submit' class='button'>");
    output_notl('%s', sprintf_translate('Restore Your Soul (%s favor)', $cost));
    rawoutput('</button></form>');
}

/**
 * Charge the favor and fill the soul together in the locked player transaction, rechecked there.
 * @return string 'granted', 'cursed' (too little favor) or 'full'
 */
function resurrection_restore(): string {
    return resurrection_player_mutation(function () {
        global $session;
        // Only the dead restore; graveyard.php sends the living to the village unless an event holds the page.
        if ($session['user']['alive']) throw new DomainException('The living cannot restore their soul.');
        $price = resurrection_restore_price((int)$session['user']['level'], (int)$session['user']['soulpoints']);
        if ((int)$session['user']['soulpoints'] >= $price['max']) return 'full';
        if ((int)$session['user']['deathpower'] < $price['cost']) return 'cursed';
        $session['user']['deathpower'] -= $price['cost'];
        $session['user']['soulpoints'] = $price['max'];
        return 'granted';
    });
}

function resurrection_restore_failure(Throwable $error): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid restoration request.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit('Your soul changed. Return to the Mausoleum and try again.'); }
    http_response_code(500); exit('Nothing was changed. Return to the Mausoleum and try again.');
}
