<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/darkhorse_entry.php';
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
        resurrection_darkhorse_refuse(403, 'Game unavailable.');
    }
    try {
        $state=\Resurrection\Game\DarkHorseState::read((string)$session['user']['specialmisc'],(int)$session['user']['acctid']);
        if (($state['active'] ?? false) && $state['game']!==$game) throw new DomainException('Wrong game.');
        return $state;
    } catch (DomainException $error) { resurrection_darkhorse_refuse(409, 'Invalid or conflicting wager.'); }
}
/**
 * A refused game request in the game's frame (Phase 6 task 28, no dead ends, part 2): the status and message, and one
 * way out, the game's own page (runmodule.php?module=<game>, whose GET only shows the game) while the player is in the
 * tavern; else resurrection_darkhorse_refuse()'s way (the Main Room, the forest, or the Shades for the dead).
 */
function resurrection_darkhorse_game_refuse(int $status, string $message, string $game, string $title): never {
    global $session;
    if (!empty($session['user']['alive']) && ($session['user']['specialinc'] ?? '') === 'module:darkhorse' &&
        in_array($_SESSION['darkhorse_return'] ?? '', ['forest.php','travel.php'], true) &&
        in_array($game, ['game_dice','game_stones','game_fivesix'], true)) {
        resurrection_refuse_page($status, $message, $title, $message, ['Return to the game', 'runmodule.php?module=' . $game]);
    }
    resurrection_darkhorse_refuse($status, $message);
}
function resurrection_darkhorse_navigation(): void {
    $return=$_SESSION['darkhorse_return'];
    addnav('Other Games',$return.'?op=oldman');
    addnav('Return to Main Room',$return.'?op=tavern');
}
