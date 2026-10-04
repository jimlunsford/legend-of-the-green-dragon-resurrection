<?php
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
if (httpget('op') === 'specialty') resurrection_forest_specialty();
// Legacy specialty URLs in Forest cannot spend a round, uses or rewards.
if (array_key_exists('skill', $_GET) || array_key_exists('l', $_GET) ||
    array_key_exists('skill', $_POST) || array_key_exists('l', $_POST)) {
    http_response_code(400); exit('Use the current specialty form.');
}

require_once __DIR__ . '/lib/forest_combat.php';
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
