<?php
// addnews ready
// translator ready
// mail ready
require_once("common.php");
require_once("lib/commentary.php");
require_once("lib/pvpwarning.php");
require_once("lib/sanitize.php");
require_once("lib/pvplist.php");
require_once("lib/http.php");
require_once("lib/buffs.php");
require_once("lib/events.php");
require_once("lib/villagenav.php");

tlschema("inn");

addcommentary();
$iname = getsetting("innname", LOCATION_INN);
$vname = getsetting("villagename", LOCATION_FIELDS);
$barkeep = getsetting('barkeep','`tCedrik');

$op = httpget('op');
require_once("lib/inn_economy.php");
try {
	$act = $op == "bartender" ? \Resurrection\Http\Input::choice($_GET, 'act', ['', 'bribe', 'listupstairs', 'colors', 'specialty'], '') : '';
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid inn request.'); }
// A bribe answers only the services offered on that answer; leaving them forfeits it.
if (!in_array($act, ['listupstairs', 'specialty'], true)) unset($_SESSION['inn_bribe']);
$fled = false;
if ($op == "fleedragon") {
	// Only the Dragon encounter the Forest displayed may be fled, once; GET shows the choice.
	if (!resurrection_inn_flee_offered()) { http_response_code(409); exit('There is no dragon to run from.'); }
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
		try {
			resurrection_inn_fields();
			resurrection_consume_action('inn-fleedragon', resurrection_inn_flee_context());
			resurrection_player_mutation(function () use ($vname) {
				global $session;
				if (!resurrection_inn_flee_offered()) throw new DomainException('No dragon encounter.');
				// Correctly reset the location if they fleeing the dragon
				$session['user']['location'] = $vname;
				if ($session['user']['charm'] > 0) $session['user']['charm']--;
			});
		} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid inn request.');
		} catch (DomainException $error) { http_response_code(409); exit('There is no dragon to run from.');
		} catch (Throwable $error) { http_response_code(500); exit('You did not get away. Try again.'); }
		unset($_SESSION['inn_flee']);
		$fled = true;
	}
}

page_header(array("%s",sanitize($iname)));
if ($op == "fleedragon" && !$fled) {
	output("Every instinct in your body wants to run, and run quickly, back to the warm inn.");
	resurrection_inn_form("inn.php?op=fleedragon", 'inn-fleedragon', resurrection_inn_flee_context(), resurrection_inn_button(translate_inline("Run away like a baby")));
	addnav("Enter the cave","dragon.php");
	page_footer();
}
$skipinndesc = handle_event("inn");

if (!$skipinndesc) {
	checkday();
	rawoutput("<span style='color: #9900FF'>");
}

$subop = httpget('subop');

$com = httpget('comscroll');
$comment = httppost('insertcommentary');

require_once("lib/partner.php");
$partner = get_partner();
addnav("Other");
villagenav();
addnav("I?Return to the Inn","inn.php");

switch ($op) {
	case "": case "strolldown": case "fleedragon":
		require("lib/inn/inn_default.php");
		blocknav("inn.php");
		break;
	case "converse":
		commentdisplay("You stroll over to a table, place your foot up on the bench and listen in on the conversation:`n", "inn","Add to the conversation?",20);
		break;
	case "bartender":
		require("lib/inn/inn_bartender.php");
		break;
	case "room":
		require("lib/inn/inn_room.php");
		break;
}

if (!$skipinndesc) rawoutput("</span>");

page_footer();
?>