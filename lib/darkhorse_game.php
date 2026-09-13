<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/../src/Game/StonesState.php';
require_once __DIR__ . '/../src/Game/DiceGame.php';
require_once __DIR__ . '/../src/Game/FiveSixGame.php';
require_once __DIR__ . '/../src/Game/DarkHorseState.php';

/** @return array<string,mixed> */
function resurrection_darkhorse_game(string $game): array {
    global $session;
    if (empty($session['loggedin']) || !is_module_active('darkhorse') ||
        ($session['user']['specialinc'] ?? '') !== 'module:darkhorse' ||
        !in_array($_SESSION['darkhorse_return'] ?? '', ['forest.php','travel.php'], true)) {
        http_response_code(403); exit('Game unavailable.');
    }
    try {
        $state=\Resurrection\Game\DarkHorseState::read((string)$session['user']['specialmisc'],(int)$session['user']['acctid']);
        if (($state['active'] ?? false) && $state['game']!==$game) throw new DomainException('Wrong game.');
        return $state;
    } catch (DomainException $error) { http_response_code(409); exit('Invalid or conflicting wager.'); }
}
function resurrection_darkhorse_navigation(): void {
    $return=$_SESSION['darkhorse_return'];
    addnav('Other Games',$return.'?op=oldman');
    addnav('Return to Main Room',$return.'?op=tavern');
}
