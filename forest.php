<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/autoload.php';
// addnews ready
// translator ready
// mail ready
require_once("common.php");
require_once("lib/forest.php");
require_once("lib/fightnav.php");
require_once("lib/http.php");
require_once("lib/taunt.php");
require_once("lib/events.php");
require_once("lib/battle-skills.php");

require_once __DIR__ . '/lib/specialty_combat.php';
// A GET of the specialty round's page (a kept page: the return page, badnav.php, the new day's Continue) shows what
// forest.php shows: the stored fight, as the search link does since Phase 6 task 24, or the forest when none is stored.
// Nothing is spent. Its POST is the round (Phase 6 task 26, R13: the GET showed only the abilities with uses left, and
// with none left, nothing a player could press).
if (httpget('op') === 'specialty') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') resurrection_forest_specialty();
    $_GET['op'] = $session['user']['badguy'] !== '' && $session['user']['specialinc'] === '' ? 'fight' : '';
}
require_once __DIR__ . '/lib/forest_combat.php';
// Legacy specialty URLs in Forest cannot spend a round, uses or rewards. Refused in the game's frame with the forest's
// way out (Phase 6 task 27, no dead ends): the stored fight, else the forest.
if (array_key_exists('skill', $_GET) || array_key_exists('l', $_GET) ||
    array_key_exists('skill', $_POST) || array_key_exists('l', $_POST)) {
    resurrection_forest_refuse(400, 'Use the current specialty form.');
}

// forest.php with no op while a forest fight is stored shows that fight, as its search link does since Phase 6 task 24
// (Phase 6 task 27, DO 4): the GET presentation of the fight route, so nothing is spent. 1.1.2 showed the forest menu,
// and this page's test, httpget('op') === '', never matched a missing op (httpget() returns false for one). With no
// fight stored, or one the forest's state check refuses (another route's, or the player dead), the forest as before.
if (!array_key_exists('op', $_GET) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && $session['user']['badguy'] !== '' &&
    $session['user']['specialinc'] === '' && resurrection_forest_way() === ['Return to the fight', 'forest.php?op=fight']) {
    $_GET['op'] = '';
}
if ($session['user']['specialinc'] === '' && (in_array(httpget('op'), ['fight','run','newtarget','search','dragon'], true) ||
    (httpget('op') === '' && $session['user']['badguy'] !== ''))) {
    resurrection_forest_combat();
}

tlschema("forest");

$fight = false;
page_header("The Forest");
$dontdisplayforestmessage=handle_event("forest");

$op = httpget("op");

$battle = false;

if ($op==""){
	// Need to pass the variable here so that we show the forest message
	// sometimes, but not others.
	forest($dontdisplayforestmessage);
}
page_footer();
?>
