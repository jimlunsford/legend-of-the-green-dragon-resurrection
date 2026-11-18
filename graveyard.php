<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/autoload.php';
// Torment never offers specialties. Hiding navigation is not authorization.
foreach (['skill','l'] as $field) {
    if (array_key_exists($field, $_GET) || array_key_exists($field, $_POST)) {
        http_response_code(400);
        exit('Specialties are unavailable in graveyard combat.');
    }
}
// addnews ready.
// translator ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/buffs.php");
require_once("lib/events.php");

// Favor and ritual GETs must precede event/checkday/header gameplay effects.
if (in_array($_GET['op'] ?? null, ['question','resurrection'], true)) {
    require_once 'lib/ramius_resurrection.php';
    resurrection_ramius_query('graveyard');
    resurrection_ramius($_GET['op']);
}
// Combat is routed before event handling, checkday and legacy presentation mutations.
$op=$_GET['op'] ?? '';
// A GET of the graveyard's own page begins a due new day first, before the torment route checks the player's state, as
// 1.1.2's graveyard.php called checkday() before its search and fights (Phase 6 task 26, the sweep after R12: a page
// shown on the old game day offered the search form, and pressing it gave the bare 409). Nothing is spent. A torment
// stored at the rollover is kept: it shows and plays to its end, as the forest's and training's kept fights do. The
// living, the ritual and the events are as before.
if (($_SERVER['REQUEST_METHOD'] ?? '')==='GET' && in_array($op,['','search','fight','run'],true) && !array_key_exists('eventhandler',$_GET) &&
    !empty($session['user']['loggedin']) && empty($session['user']['alive']) && $session['user']['specialinc']==='' &&
    $session['user']['badguy']==='') resurrection_checkday_on_rollover();
if (in_array($op,['search','fight','run'],true) || ($op==='' && $session['user']['specialinc']==='' && !array_key_exists('eventhandler',$_GET))) {
    require_once 'lib/graveyard_combat.php';
    resurrection_graveyard_combat();
}
tlschema("graveyard");
page_header("The Graveyard");
$skipgraveyardtext=handle_event("graveyard");
$deathoverlord=getsetting('deathoverlord','`$Ramius');
if (!$skipgraveyardtext) {
    if ($session['user']['alive']) redirect('village.php');
    checkday();
}
$op=httpget('op');
switch ($op) {
	case "search": case "run": case "fight":
		break;
	case "enter":
		require_once("lib/graveyard/case_enter.php");
		break;
	case "restore":
		require_once("lib/graveyard/case_restore.php");
		break;
	case "resurrection":
		require_once("lib/graveyard/case_resurrection.php");
		break;
	case "question":
		require_once("lib/graveyard/case_question.php");
		break;
	case "haunt":
		require_once("lib/graveyard/case_haunt.php");
		break;
	case "haunt2":
		require_once("lib/graveyard/case_haunt2.php");
		break;
	case "haunt3":
		require_once("lib/graveyard/case_haunt3.php");
		break;
	default:
		require_once("lib/graveyard/case_default.php");
		break;
}

page_footer();
?>
