<?php
require_once __DIR__ . '/../inn_economy.php';
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($act==""){
	output("%s`0 looks at you sort-of sideways like.",$barkeep);
	output("He never was the sort who would trust a man any farther than he could throw them, which gave dwarves a decided advantage, except in provinces where dwarf tossing was made illegal.");
	output("%s`0 polishes a glass, holds it up to the light of the door as another patron opens it to stagger out into the street.",$barkeep);
	output("He then makes a face, spits on the glass and goes back to polishing it.");
	output("\"`%What d'ya want?`0\" he asks gruffly.");
	addnav_notl(sanitize($barkeep));
	addnav("Bribe","inn.php?op=bartender&act=bribe");
	addnav("Drinks");
	modulehook("ale", array());
}elseif ($act=="bribe"){
	$g1 = $session['user']['level']*10;
	$g2 = $session['user']['level']*50;
	$g3 = $session['user']['level']*100;
	try {
		// Only the tiers offered for this level, by type; the server knows the amount.
		$type = \Resurrection\Http\Input::choice($_GET, 'type', ['', 'gem', 'gold'], '');
		$amt = $type === '' ? 0 : \Resurrection\Http\Input::integer($_GET, 'amt', 0, 1);
		if ($type !== '' && !in_array($amt, resurrection_inn_bribe_tiers((int)$session['user']['level'])[$type], true)) {
			throw new InvalidArgumentException('Bribe not offered.');
		}
	} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bribe.'); }
	if ($type==""){
		output("While you know that you won't always get what you want, sometimes the way to a man's information is through your purse.");
		output("It's also always been said that more is better.`n`n");
		output("How much would you like to offer him?");
		addnav("1 gem","inn.php?op=bartender&act=bribe&type=gem&amt=1");
		addnav("2 gems","inn.php?op=bartender&act=bribe&type=gem&amt=2");
		addnav("3 gems","inn.php?op=bartender&act=bribe&type=gem&amt=3");
		addnav(array("%s gold", $g1),"inn.php?op=bartender&act=bribe&type=gold&amt=$g1");
		addnav(array("%s gold", $g2),"inn.php?op=bartender&act=bribe&type=gold&amt=$g2");
		addnav(array("%s gold", $g3),"inn.php?op=bartender&act=bribe&type=gold&amt=$g3");
	}elseif (!$post){
		// GET only displays the offer; handing it over is a protected POST.
		$label = $type == "gem" ? ($amt == 1 ? translate_inline("1 gem") : sprintf_translate("%s gems", $amt)) : sprintf_translate("%s gold", $amt);
		resurrection_inn_form("inn.php?op=bartender&act=bribe&type=$type&amt=$amt", 'inn-bribe',
			resurrection_inn_bribe_context()."|$type|$amt", resurrection_inn_button($label));
	}else{
		try {
			resurrection_inn_fields();
			resurrection_consume_action('inn-bribe', resurrection_inn_bribe_context()."|$type|$amt");
			$bribe = resurrection_player_mutation(function () use ($type, $amt, $barkeep) {
				global $session;
				if (!in_array($amt, resurrection_inn_bribe_tiers((int)$session['user']['level'])[$type], true)) throw new DomainException('Bribe not offered.');
				$field = $type == "gem" ? 'gems' : 'gold';
				// Funds are checked on the locked account; a short purse costs nothing, as in 1.1.2.
				if ($session['user'][$field] < $amt) return ['try'=>false];
				$session['user'][$field] -= $amt;
				debuglog($type == "gem" ? "spent $amt gems on bribing $barkeep" : "spent $amt gold bribing $barkeep");
				$chance = resurrection_inn_bribe_chance($type, $amt, (int)$session['user']['level']);
				return ['try'=>true, 'success'=>e_rand(0,100) < $chance];
			});
		} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bribe.');
		} catch (DomainException $error) { http_response_code(409); exit('Your situation changed. Return to the bartender and try again.');
		} catch (Throwable $error) { http_response_code(500); exit('Nothing was paid. Return to the bartender and try again.'); }
		if (!$bribe['try']){
			if ($type=="gem") output("You don't have %s gems!", $amt);
			else output("You don't have %s gold!", $amt);
			output("`n`n%s`0 stands there staring at you blankly.",$barkeep);
			addnav(array("B?Talk to %s`0 the Barkeep",$barkeep),"inn.php?op=bartender");
		}elseif ($bribe['success']){
			// The answer is server state, used by the service it unlocks.
			$_SESSION['inn_bribe'] = (int)$session['user']['acctid'];
			output("%s`0 leans over the counter toward you.  \"`%What can I do for you, kid?`0\" he asks.",$barkeep);
			addnav("What do you want?");
			if (getsetting("pvp",1)) {
				addnav("Who's upstairs?","inn.php?op=bartender&act=listupstairs");
			}
			addnav("Tell me about colors","inn.php?op=bartender&act=colors");
			if (getsetting("allowspecialswitch", true))
				addnav("Switch specialty","inn.php?op=bartender&act=specialty");
		}else{
			output("%s`0 begins to wipe down the counter top, an act that really needed doing a long time ago.",$barkeep);
			if ($type == "gem") {
				if ($amt == 1) {
					output("When he's finished, your gem is gone.");
				} else{
					output("When he's finished, your gems are gone.");
				}
			} else {
				output("When he's finished, your gold is gone.");
			}
			output("You inquire about the loss, and he stares blankly back at you.");
			addnav(array("B?Talk to %s`0 again",$barkeep),"inn.php?op=bartender");
		}
	}
}else if ($act=="listupstairs"){
	if (!resurrection_inn_bribed() || !getsetting("pvp",1)) { http_response_code(403); exit('The bartender has nothing to tell you.'); }
	addnav("Refresh the list","inn.php?op=bartender&act=listupstairs");
	output("%s`0 lays out a set of keys on the counter top, and tells you which key opens whose room.  The choice is yours, you may sneak in and attack any one of them.",$barkeep);
	pvplist($iname,"pvp.php", "?act=attack&inn=1");
}else if($act=="colors"){
	output("%s`0 leans on the bar.  \"`%So you want to know about colors, do you?`0\" he asks.",$barkeep);
	output("You are about to answer when you realize the question was posed in the rhetoric.");
	output("%s`0 continues, \"`%To do colors, here's what you need to do.",$barkeep);
	output(" First, you use a &#0096; mark (found right above the tab key) followed by 1, 2, 3, 4, 5, 6, 7, !, @, #, $, %, ^, &.", true);
	output("Each of those corresponds with a color to look like this:");
	output_notl("`n`1&#0096;1 `2&#0096;2 `3&#0096;3 `4&#0096;4 `5&#0096;5 `6&#0096;6 `7&#0096;7 ",true);
	output_notl("`n`!&#0096;! `@&#0096;@ `#&#0096;# `\$&#0096;\$ `%&#0096;% `^&#0096;^ `&&#0096;& `n",true);
	output("`% Got it?`0\"  You can practice below:");
	// The request URI is attacker-influenced; it is escaped as an attribute.
	rawoutput("<form action=\"".htmlspecialchars($REQUEST_URI, ENT_QUOTES, 'UTF-8')."\" method='POST'>",true);
	$testtext = httppost('testtext');
	output("You entered %s`n", prevent_colors(HTMLEntities($testtext, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))), true);
	output("It looks like %s`n", $testtext);
	$try = translate_inline("Try");
	rawoutput("<input name='testtext' id='input'>");
	rawoutput("<input type='submit' class='button' value='$try'>");
	rawoutput("</form>");
	rawoutput("<script language='javascript'>document.getElementById('input').focus();</script>");
		output("`0`n`nThese colors can be used in your name, and in any conversations you have.");
	addnav("",$REQUEST_URI);
}else if($act=="specialty"){
	// The switch requires a successful bribe, is allowed by the server setting, and only offers active bundled specialties.
	if (!resurrection_inn_bribed() || !getsetting("allowspecialswitch", true)) { http_response_code(403); exit('The bartender has nothing to tell you.'); }
	$choices = resurrection_specialty_choices();
	$specialtyurl = "inn.php?op=bartender&act=specialty";
	if (!$post){
		output("\"`2I want to change my specialty,`0\" you announce to %s`0.`n`n",$barkeep);
		output("With out a word, %s`0 grabs you by the shirt, pulls you over the counter, and behind the barrels behind him.",$barkeep);
		output("There, he rotates the tap on a small keg labeled \"Fine Swill XXX\"`n`n");
		output("You look around for the secret door that you know must be opening nearby when %s`0 rotates the tap back, and lifts up a freshly filled foamy mug of what is apparently his fine swill, blue-green tint and all.`n`n",$barkeep);
		output("\"`3What?  Were you expecting a secret room?`0\" he asks.  \"`3Now then, you must be more careful about how loudly you say that you want to change your specialty, not everyone looks favorably on that sort of thing.`n`n");
		output("`0\"`3What new specialty did you have in mind?`0\"");
		$specialities = modulehook("specialtynames");
		addnav('', $specialtyurl);
		rawoutput('<form method="POST" action="'.htmlspecialchars($specialtyurl, ENT_QUOTES, 'UTF-8').'">'.
			resurrection_action_fields('inn-specialty', resurrection_inn_specialty_context($choices)));
		foreach($specialities as $key=>$name) {
			if (!isset($choices[$key])) continue;
			rawoutput('<button class="button" name="specialty" value="'.htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8').'">');
			output_notl("%s", $name);
			rawoutput('</button> ');
		}
		rawoutput('</form>');
	}else{
		try {
			resurrection_inn_fields(['specialty']);
			$specialty = \Resurrection\Http\Input::choice($_POST, 'specialty', array_keys($choices), '');
			if ($specialty === '') throw new InvalidArgumentException('Specialty required.');
			resurrection_consume_action('inn-specialty', resurrection_inn_specialty_context($choices));
			resurrection_player_mutation(function () use ($specialty, $choices) {
				global $session;
				$locked = resurrection_specialty_choices(true);
				if ($locked !== $choices || !isset($locked[$specialty])) throw new DomainException('Specialty state changed.');
				// Only the selection changes: each specialty keeps its stored skill and uses, as the bartender says.
				$session['user']['specialty'] = $specialty;
			});
		} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid specialty.');
		} catch (DomainException $error) { http_response_code(409); exit('Your situation changed. Return to the bartender and try again.');
		} catch (Throwable $error) { http_response_code(500); exit('Your specialty was not changed. Return to the bartender and try again.'); }
		unset($_SESSION['inn_bribe']);
		output("\"`3Ok then,`0\" %s`0 says, \"`3You're all set.`0\"`n`n\"`2That's it?`0\" you ask him.`n`n",$barkeep);
		output("\"`3Yep.  What'd you expect, some sort of fancy arcane ritual???`0\"  %s`0 begins laughing loudly.",$barkeep);
		output("\"`3You're all right, kid... just don't ever play poker, eh?`0`n`n");
		output("\"`3Oh, one more thing.  Your old use points and skill level still apply to that skill, you'll have to build up some points in this one to be very good at it.`0\"");
	}
}
?>
