<?php
require_once __DIR__ . '/../../src/Security/ScalarState.php';
require_once __DIR__ . '/../inn_economy.php';
$config = \Resurrection\Security\ScalarState::read($session['user']['donationconfig']);
$prices = resurrection_inn_room_prices((int)$session['user']['level']);
$expense = $prices['expense'];
$bankexpense = $prices['bank'];
$fee = $prices['fee'];
try {
	$pay = \Resurrection\Http\Input::choice($_GET, 'pay', ['', '1', '2'], '');
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid room request.'); }
$roomurl = "inn.php?op=room&pay=$pay";
if ($pay !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
	// Room rental, its debit and the logout are one protected, locked player mutation.
	try {
		resurrection_inn_fields();
		resurrection_consume_action('inn-room', resurrection_inn_room_context($pay));
		$paid = resurrection_player_mutation(function () use ($pay, $iname) {
			global $session;
			$prices = resurrection_inn_room_prices((int)$session['user']['level']);
			if (!$session['user']['boughtroomtoday']) {
				// 1.1.2 skipped the funds check for the bank; each path now checks its own balance.
				$balance = $pay === '2' ? 'goldinbank' : 'gold';
				$cost = $pay === '2' ? $prices['bank'] : $prices['expense'];
				if ($session['user'][$balance] < $cost) return false;
				$session['user'][$balance] -= $cost;
				$session['user']['boughtroomtoday'] = 1;
				debuglog("spent $cost gold on an inn room");
			}
			$session['user']['location'] = $iname;
			$session['user']['loggedin'] = 0;
			$session['user']['restorepage'] = "inn.php?op=strolldown";
			// Leaving through the inn ends this login like login.php does.
			$session['user']['authversion'] = (int)$session['user']['authversion'] + 1;
			return true;
		});
	} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid room request.');
	} catch (DomainException $error) { http_response_code(409); exit('Your situation changed. Return to the inn and ask again.');
	} catch (Throwable $error) { http_response_code(500); exit('The room was not rented. Return to the inn and ask again.'); }
	if ($paid) {
		resurrection_end_session();
		header('Location: index.php', true, 303);
		exit();
	}
	output("\"Aah, so that's how it is,\" %s`0 says as he puts the key he had retrieved back on to its hook behind his counter.",$barkeep);
	output("Perhaps you'd like to get sufficient funds before you attempt to engage in local commerce.");
}elseif ($pay !== ''){
	// GET only displays: the historical room links now open the confirmation form.
	$affordable = $session['user']['boughtroomtoday'] ||
		($pay === '2' ? $session['user']['goldinbank'] >= $bankexpense : $session['user']['gold'] >= $expense);
	if ($affordable) {
		if ($session['user']['boughtroomtoday']) $label = translate_inline("Go to room");
		elseif ($pay === '2') $label = sprintf_translate("Pay %s gold from bank", $bankexpense);
		else $label = sprintf_translate("Give him %s gold", $expense);
		resurrection_inn_form($roomurl, 'inn-room', resurrection_inn_room_context($pay), resurrection_inn_button($label, '', '', true));
	}else{
		output("\"Aah, so that's how it is,\" %s`0 says as he puts the key he had retrieved back on to its hook behind his counter.",$barkeep);
		output("Perhaps you'd like to get sufficient funds before you attempt to engage in local commerce.");
	}
}else{
	if ($session['user']['boughtroomtoday']){
		output("You already paid for a room for the day.");
		addnav("Go to room","inn.php?op=room&pay=1");
	}else{
		modulehook("innrooms");
		output("You stroll over to the bartender and request a room.");
		output("He eyes you up and says, \"It will cost `\$%s`0 gold for the night in a standard room.", $expense);
		if ($session['user']['goldinbank'] >= $bankexpense && $bankexpense != $expense) {
			output("And since you are such a fine person, I'll even offer you a rate of `\$%s`0 gold if you pay direct from the bank.", $bankexpense);
			if ($prices['percent']) {
				output("That includes a %s transaction fee.", $fee);
			} else {
				output("That includes a transaction fee of %s gold.",
						$fee);
			}
		}
		$bodyguards = array("Butch","Bruce","Alfonozo","Guido","Bruno","Bubba","Al","Chuck","Brutus","Nunzio","Terrance","Mitch","Rocco","Spike","Gregor","Sven","Draco");
		output("`n`n\"Also, let me tell you about our new 'Bodyguard Assistance Program' &#151; BAP.  You see, you hire one of my guards here, and they'll protect you should anyone happen to, er, pick the locks of your room,\" he says as he gestures to a series of men sitting at one of the inn's tables drinking ale.", true);
		output("They range in size from a skinny shifty-eyed fellow who appears barely able to lift his stein to a great bear of a fellow.");
		output("This bruiser has a tattoo of a heart with \"Mom\" written across it on his huge bicep, and goes to take a sip from his ale, but instead crushes his stein, squirting it all over the skinny fellow who doesn't voice any objection for obvious reasons.");
		output("\"We call it the BAP program because when someone tries to sneak into your room, BAP BAP BAP, our guys go to work.");
		output("There's only two conditions: you pay your fee up front, and the guard you choose gets to keep a portion of the rewards from any fights.\"");
		output("`n`nNot wanting to part with your money when the fields offer a place to sleep, you debate the issue.");
		output("You realize, however, that the inn is a considerably safer place to sleep.");
		output("It is far harder for vagabonds to get you in your room while you sleep.");
		output("Also, those bodyguards sound pretty safe to you.");
		//output("`n`bNote, bodyguard levels not yet implemented`b`n");
		addnav(array("Give him %s gold", $expense),"inn.php?op=room&pay=1");
		if ($session['user']['goldinbank'] >= $bankexpense) {
			addnav(array("Pay %s gold from bank", $bankexpense),"inn.php?op=room&pay=2");
		}
	}
}
?>
