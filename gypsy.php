<?php
// addnews ready
// translator ready
// mail ready
require_once("common.php");
require_once("lib/commentary.php");
require_once("lib/http.php");
require_once("lib/villagenav.php");
require_once("lib/service_authority.php");

tlschema("gypsy");

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'pay', 'talk'], '');
} catch (InvalidArgumentException $error) { resurrection_service_failure($error, 'the gypsy'); }

$cost = resurrection_gypsy_cost();
// The seance is open to the player who paid for it in this session (until they snap out of it) and to comment
// moderators; 1.1.2 relied on allowed navigation alone.
$seance = (int)($session['resurrection_seance'] ?? 0) === (int)$session['user']['acctid'] ||
	($session['user']['superuser'] & SU_EDIT_COMMENTS);
if ($op=="talk" && !$seance) { http_response_code(403); exit('The seance has not been paid for.'); }

addcommentary();

if ($op=="pay" && !resurrection_service_posted()){
	// 1.1.2 charged on this GET; the payment is now this form's protected POST.
	page_header("Gypsy Seer's tent");
	resurrection_service_form("gypsy.php?op=pay", 'gypsy-pay', resurrection_service_context('gypsy-pay', (string)$cost),
		array("Pay to talk to the dead (%s gold)", $cost));
	addnav("Other");
	addnav("Forget it","village.php");
}elseif ($op=="pay"){
	try {
		resurrection_service_fields();
		resurrection_consume_action('gypsy-pay', resurrection_service_context('gypsy-pay', (string)$cost));
		$paid = resurrection_player_mutation(fn () => resurrection_gypsy_pay());
	} catch (Throwable $error) { resurrection_service_failure($error, 'the gypsy'); }
	if ($paid){ // Gunnar Kreitz
		$session['resurrection_seance'] = (int)$session['user']['acctid'];
		redirect("gypsy.php?op=talk");
	}else{
		page_header("Gypsy Seer's tent");
		villagenav();
		output("`5You offer the old gypsy woman your `^%s`5 gold for your gen-u-wine say-ance, however she informs you that the dead may be dead, but they ain't cheap.", $session['user']['gold']);
	}
}elseif ($op=="talk"){
	page_header("In a deep trance, you talk with the shades");
	commentdisplay("`5While in a deep trance, you are able to talk with the dead:`n", "shade","Project",25,"projects");
	addnav("Snap out of your trance","gypsy.php");
}else{
	unset($session['resurrection_seance']);
	checkday();
	page_header("Gypsy Seer's tent");
	output("`5You duck into a gypsy tent like many you have seen throughout the realm.");
	output("All of them promise to let you talk with the deceased, and most of them surprisingly seem to work.");
	output("There are also rumors that the gypsy have the power to speak over distances other than just those of the afterlife.");
	output("In typical gypsy style, the old woman sitting behind a somewhat smudgy crystal ball informs you that the dead only speak with the paying.");
	output("\"`!For you, %s, the price is a trifling `^%s`! gold.`5\", she rasps.", translate_inline($session['user']['sex']?"my pretty":"my handsome"), $cost);
	addnav("Seance");
	addnav(array("Pay to talk to the dead (%s gold)", $cost),"gypsy.php?op=pay");
	if ($session['user']['superuser'] & SU_EDIT_COMMENTS)
		addnav("Superuser Entry","gypsy.php?op=talk");
	addnav("Other");
	addnav("Forget it","village.php");
	modulehook("gypsy");
}
page_footer();
?>