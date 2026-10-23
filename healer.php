<?php
require_once __DIR__ . '/src/Security/ScalarState.php';
// addnews ready
// translator ready
// mail ready
require_once("common.php");
require_once("lib/forest.php");
require_once("lib/http.php");
require_once("lib/villagenav.php");
require_once("lib/service_authority.php");

tlschema("healer");

$config = \Resurrection\Security\ScalarState::read($session['user']['donationconfig']);

// The op, the potion size and the return target are checked before anything runs. 1.1.2 offered "Return whence
// you came" to any URL the request named, which issued that URL as an allowed navigation.
try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'buy', 'companion'], '');
	$return = resurrection_healer_return();
	$pct = $op == "buy" ? resurrection_healer_percent() : 0;
} catch (InvalidArgumentException $error) { resurrection_service_failure($error, 'the healer'); }
$returnline = $return>""?"&return=$return":"";
// Only the protected POST of the confirming form heals; a GET asks, with the server's price. The URL's
// compcost is never read.
$posted = resurrection_service_posted();

page_header("Healer's Hut");
output("`#`b`cHealer's Hut`c`b`n");

$cost = resurrection_healer_cost();

if ($op==""){
  	checkday();
	output("`3You duck into the small smoke-filled grass hut.");
	output("The pungent aroma makes you cough, attracting the attention of a grizzled old person that does a remarkable job of reminding you of a rock, which probably explains why you didn't notice them until now.");
	output("Couldn't be your failure as a warrior.");
	output("Nope, definitely not.`n`n");
	if ($session['user']['hitpoints'] < $session['user']['maxhitpoints']){
		output("\"`6See you, I do.  Before you did see me, I think, hmm?`3\" the old thing remarks.");
		output("\"`6Know you, I do; healing you seek.  Willing to heal am I, but only if willing to pay are you.`3\"`n`n");
		output("\"`5Uh, um.  How much?`3\" you ask, ready to be rid of the smelly old thing.`n`n");
		output("The old being thumps your ribs with a gnarly staff.  \"`6For you... `$`b%s`b`6 gold pieces for a complete heal!!`3\" it says as it bends over and pulls a clay vial from behind a pile of skulls sitting in the corner.", $cost);
		output("The view of the thing bending over to remove the vial almost does enough mental damage to require a larger potion.");
		output("\"`6I also have some, erm... 'bargain' potions available,`3\" it says as it gestures at a pile of dusty, cracked vials.");
		output("\"`6They'll heal a certain percent of your `idamage`i.`3\"");
	}elseif($session['user']['hitpoints'] == $session['user']['maxhitpoints']){
		output("`3The old creature grunts as it looks your way. \"`6Need a potion, you do not.  Wonder why you bother me, I do.`3\" says the hideous thing.");
		output("The aroma of its breath makes you wish you hadn't come in here in the first place.  You think you had best leave.");
	}else{
		output("`3The old creature glances at you, then in a `^whirlwind of movement`3 that catches you completely off guard, brings its gnarled staff squarely in contact with the back of your head.");
		output("You gasp as you collapse to the ground.`n`n");
		output("Slowly you open your eyes and realize the beast is emptying the last drops of a clay vial down your throat.`n`n");
		output("\"`6No charge for that potion.`3\" is all it has to say.");
		output("You feel a strong urge to leave as quickly as you can.");
		$session['user']['hitpoints'] = $session['user']['maxhitpoints'];
	}
}elseif ($op=="buy"){
	$potion = $pct == 100 ? "`^Complete Healing`0" : array("%s%% - %s gold", $pct, round($cost*$pct/100,0));
	$context = resurrection_service_context('healer-buy', (string)$pct, [$cost]);
	if (!$posted) {
		// 1.1.2 sold the potion on this GET; the sale is now this form's protected POST.
		if ($session['user']['hitpoints'] < $session['user']['maxhitpoints']) {
			resurrection_service_form("healer.php?op=buy&pct=$pct$returnline", "healer-buy-$pct", $context, is_array($potion) ? $potion : translate_inline($potion));
		}
	} else {
		try {
			resurrection_service_fields();
			resurrection_consume_action("healer-buy-$pct", $context);
			[$newcost, $diff] = resurrection_player_mutation(fn () => resurrection_healer_potion($pct));
		} catch (Throwable $error) { resurrection_service_failure($error, 'the healer'); }
		if ($diff !== null){
		output("`3With a grimace, you up-end the potion the creature hands you, and despite the foul flavor, you feel a warmth spreading through your veins as your muscles knit back together.");
		if($newcost){
			output("`3With a grimace, you up-end the potion the creature hands you, and despite the foul flavor, you feel a warmth spreading through your veins as your muscles knit back together.");
			output("Staggering some, you hand it your gold and are ready to be out of here.");
		}else{
			output("`3With a grimace, you up-end the potion the creature hands you, and despite the foul flavor, you feel a warmth spreading through your veins.");
			output("Staggering some you are ready to be out of here.");
		}
		if($diff == 1) {
			output("`n`n`#You have been healed for one point!", $diff);
		} else {
			output("`n`n`#You have been healed for %s points!", $diff);
		}
		}else{
		output("`3The old creature pierces you with a gaze hard and cruel.");
		output("Your lightning quick reflexes enable you to dodge the blow from its gnarled staff.");
		output("Perhaps you should get some more money before you attempt to engage in local commerce.`n`n");
		output("You recall that the creature had asked for `b`\$%s`3`b gold.", $newcost);
		}
	}
}elseif ($op=="companion"){
	try {
		$name = resurrection_companion_key();
		$compcost = resurrection_companion_heal_cost($companions[$name]);
		if ($compcost === null) throw new DomainException('Nothing to heal.');
		$context = resurrection_service_context('healer-companion', $name, [$compcost]);
		$paid = null;
		if ($posted) {
			resurrection_service_fields();
			resurrection_consume_action('healer-companion', $context);
			$paid = resurrection_player_mutation(fn () => resurrection_companion_heal($name, false));
		}
	} catch (Throwable $error) { resurrection_service_failure($error, 'the healer'); }
	if($posted ? $paid === null : $session['user']['gold'] < $compcost){
		output("`3The old creature pierces you with a gaze hard and cruel.`n");
		output("Your lightning quick reflexes enable you to dodge the blow from its gnarled staff.`n");
		output("Perhaps you should get some more money before you attempt to engage in local commerce.`n`n");
		output("You recall that the creature had asked for `b`\$%s`3`b gold.", $compcost);
	}elseif(!$posted){
		// 1.1.2 healed on this GET; the healing is now this form's protected POST.
		resurrection_service_form("healer.php?op=companion&name=" . rawurlencode($name) . $returnline, 'healer-companion', $context,
			array("%s`0 (`^%s Gold`0)", $companions[$name]['name'], $compcost));
	}else{
		output("`3With a grimace, %s`3 up-ends the potion from the creature.`n", $companions[$name]['name']);
		output("Muscles knit back together, cuts close and bruises fade. %s`3 is ready to battle once again!`n", $companions[$name]['name']);
		output("You hand the creature your gold and are ready to be out of here.");
	}
}
$playerheal = false;
if($session['user']['hitpoints'] < $session['user']['maxhitpoints']){
	$playerheal = true;
	addnav("Potions");
	addnav("`^Complete Healing`0","healer.php?op=buy&pct=100$returnline");
	for ($i=90;$i>0;$i-=10){
		addnav(array("%s%% - %s gold", $i, round($cost*$i/100,0)),"healer.php?op=buy&pct=$i$returnline");
	}
	modulehook('potion');
}
addnav("`bHeal Companions`b");
$compheal = false;
foreach($companions as $name => $companion){
	if(isset($companion['cannotbehealed']) && $companion['cannotbehealed'] == true){
	}else{
		$points = $companion['maxhitpoints'] - $companion['hitpoints'];
		if($points > 0){
			$compcost = round(log($session['user']['level']+1) * ($points + 10)*1.33);
			// The link no longer carries the price; the companion page works it out again.
			addnav(array("%s`0 (`^%s Gold`0)", $companion['name'], $compcost), "healer.php?op=companion&name=".rawurlencode($name).$returnline);
			$compheal = true;
		}
	}
}
tlschema("nav");
addnav("`bReturn`b");
if ($return==""){
	if($playerheal || $compheal){
		addnav("F?Back to the Forest", "forest.php");
		villagenav();
	}else{
		forest(true);
	}
}elseif ($return=="village.php"){
	villagenav();
}else{
	addnav("R?Return whence you came",$return);
}
tlschema();
output_notl("`0");
page_footer();
?>