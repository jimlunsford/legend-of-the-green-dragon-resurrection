<?php

//Converted to a module by Zach Lawson, with the addition of settings

/*
Version History:
Version 1.0 - Original public release
Version 2.0 - Added a feature that allows the cost of the potions to vary by day
Version 2.1 - Fixed some broken stuff
Version 2.2 - Really fixed it this time
Version 2.3 - fixed random cost.
*/

function cedrikspotions_getmoduleinfo(){
	$info = array(
		"name"=>"Cedrik's Potion Shop",
		"version"=>"2.6",
		"author"=>"Eric Stevens<br>Modifications by: Chris Vorndran",
		"category"=>"Inn",
		"download"=>"core_module",
		"settings"=>array(
			"Cedrik's Potion Shop - Potion Availability,title",
			"ischarm"=>"Is Charm potion available,bool|1",
			"ismax"=>"Is Vitality potion available,bool|1",
			"istemp"=>"Is Health potion available,bool|1",
			"isforget"=>"Is Forgetfulness potion available,bool|1",
			"istrans"=>"Is Transmutation potion available,bool|1",
			"Cedrik's Potion Shop - Costs,title",
			"charmcost"=>"Cost for Charm Potion,range,1,10,1|2",
			"maxcost"=>"Cost for Vitality Potion,range,1,10,1|2",
			"tempcost"=>"Cost for Health Potion,range,1,10,1|2",
			"forgcost"=>"Cost for Forgetfulness Potion,range,1,10,1|2",
			"transcost"=>"Cost for Transmutation Potion,range,1,10,1|2",
			"random"=>"Is the cost per point of potion random,bool|0",
			"minrand"=>"Minimum cost per point of effect,range,1,9,1|2",
			"maxrand"=>"Maximum cost per point of effect,range,2,10,1|5",
			"randcost"=>"Current random cost,range,1,10,1|2",
			"Note: Each <x> amount of gems spent will give the effect the potion.  The actual effects can vary based on configuration.,note",
			"Cedrik's Potion Shop - Effects,title",
			"transmuteturns"=>"How many turns will the transmutation sickness last?,range,1,20,1|10",
			"defmod"=>"How much is the multiplier for Transmutation Sickness (defense)?,floatrange,.1,2,.05|.75",
			"atkmod"=>"How much is the multiplier for Transmutation Sickness (attack)?,floatrange,.1,2,.05|.75",
			"survive"=>"Will transmutation sickness carry over new days?,bool|1",
			"charmgain"=>"How much charm do you get per potion,int|1",
			"vitalgain"=>"How many maxhp do you get per potion,int|1",
			"tempgain"=>"How many current hp do you get per potion,int|20",
			"carrydk"=>"Do MaxHitpoint (vitality) potions carry across DKs?,bool|1",
		),
		"prefs"=>array(
			"Cedrik's Potion Shop User Preferences,title",
			"extrahps"=>"How many extra hitpoints has the user bought?,int",
		),
	);
	return $info;
}

function cedrikspotions_install(){
	module_addhook("header-inn");
	module_addhook("newday-runonce");
	module_addhook("hprecalc");
	return true;
}

function cedrikspotions_uninstall(){
	return true;
}

function cedrikspotions_dohook($hookname,$args){
	switch($hookname){
	case "header-inn":
		$op = httpget("op");
		$act = httpget("act");
		if($op=="bartender" && $act == "") {
			addnav_notl(sanitize(getsetting("barkeep","`tCedrik")));
			addnav("Gems","runmodule.php?module=cedrikspotions&op=gems");
		}
		break;
	case "newday-runonce":
		if (get_module_setting("random")){
            [$min,$max] = cedrikspotions_random_bounds();
			$randcost = e_rand($min,$max);
			set_module_setting("randcost",$randcost);
		}
		break;
	case "hprecalc":
		$args['total'] -= cedrikspotions_extra_hp();
		if (!get_module_setting("carrydk")) {
			$args['extra'] -= cedrikspotions_extra_hp();
			set_module_pref("extrahps", 0);
		}
	}
	return $args;
}

function cedrikspotions_run(){
    global $session;
    require_once 'lib/player_mutation.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['gemcount'])) {
        resurrection_consume_action('cedrik-purchase',(string)$session['user']['acctid']);
        try {
            $wish=\Resurrection\Http\Input::integer($_POST,'wish',0,1);
            $quantity=\Resurrection\Http\Input::integer($_POST,'gemcount',0,1);
            if ($wish>5 || $wish<1 || $quantity<1 || $quantity>2147483647) throw new InvalidArgumentException();
        } catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid potion request.'); }
        $availability=[1=>'ischarm',2=>'ismax',3=>'istemp',4=>'isforget',5=>'istrans'];
        if (!get_module_setting($availability[$wish],'cedrikspotions')) { http_response_code(400); exit('Potion unavailable.'); }
    }
	$wish = httppost('wish');
	$op = httpget("op");
	$iname = getsetting("innname", LOCATION_INN);
	tlschema("inn");
	page_header($iname);
	rawoutput("<span style='color: #9900FF'>");
	output_notl("`c`b");
	output($iname);
	output_notl("`b`c");
	tlschema();
	$barkeep = getsetting("barkeep","`tCedrik");
	$mincost = 0;
	$maxcost = 0;
	$cost = 0;
	$gemcount = httppost('gemcount');
	if ($gemcount == "") {
		if(get_module_setting("random")) {
			$cost =get_module_setting("randcost");
		} else {
			if (get_module_setting("ischarm")) {
				$cm = get_module_setting("charmcost");
				if ($mincost==0 || $cm < $mincost) $mincost = $cm;
				if ($maxcost==0 || $cm > $maxcost) $maxcost = $cm;
			}
			if (get_module_setting("ismax")) {
				$cm = get_module_setting("maxcost");
				if ($mincost==0 || $cm < $mincost) $mincost = $cm;
				if ($maxcost==0 || $cm > $maxcost) $maxcost = $cm;
			}
			if (get_module_setting("istemp")) {
				$cm = get_module_setting("tempcost");
				if ($mincost==0 || $cm < $mincost) $mincost = $cm;
				if ($maxcost==0 || $cm > $maxcost) $maxcost = $cm;
			}
			if (get_module_setting("isforget")) {
				$cm = get_module_setting("forgcost");
				if ($mincost==0 || $cm < $mincost) $mincost = $cm;
				if ($maxcost==0 || $cm > $maxcost) $maxcost = $cm;
			}
			if (get_module_setting("istrans")) {
				$cm = get_module_setting("transcost");
				if ($mincost==0 || $cm < $mincost) $mincost = $cm;
				if ($maxcost==0 || $cm > $maxcost) $maxcost = $cm;
			}
			if ($mincost == $maxcost) $cost = $mincost;
		}
	}

	if (!get_module_setting("random")){
		switch ($wish){
		case 1:
			$cost = get_module_setting("charmcost");
			break;
		case 2:
			$cost = get_module_setting("maxcost");
			break;
		case 3:
			$cost = get_module_setting("tempcost");
			break;
		case 4:
			$cost = get_module_setting("forgcost");
			break;
		case 5:
			$cost = get_module_setting("transcost");
			break;
		}
	}else{
		$cost = get_module_setting("randcost");
	}

	if($op=="gems"){
		if ($gemcount==""){
			if (get_module_setting("random") || $mincost == $maxcost) {
				output("\"`%You have gems, do ya?`0\" %s`0 asks.  \"`%Well, I'll make you a magic elixir for `^ %s %s`%!`0\"",$barkeep,$cost, translate_inline($cost == 1?"gem" : "gems"));
			} else {
				output("\"`%You have gems, do ya?`0\" %s`0 asks.  \"`%Well, I'll make you a magic elixir for between `^%s and %s gems`%, depending on which one you want!`0\"",$barkeep,$mincost, $maxcost);
			}
			output("`n`nGive him how many gems?");
			$give = translate_inline("Give");
			$link = appendcount("runmodule.php?module=cedrikspotions&op=gems");
			addnav("", $link);
			rawoutput("<form action='$link' method='POST'>");
            rawoutput(resurrection_action_fields('cedrik-purchase',(string)$session['user']['acctid']));
			rawoutput("<input name='gemcount' value='0'>");
			rawoutput("<input type='submit' class='button' value='$give'>");
			output("`nAnd what do you wish for?`n");
			if (get_module_setting("ischarm") == 1) {
				rawoutput("<input type='radio' name='wish' value='1' checked>");
				output("Charm");
				if ($mincost != $maxcost) {
					$cm = get_module_setting("charmcost");
					output("(%s %s for %s charm)", $cm, translate_inline($cm==1?"gem":"gems"), get_module_setting("charmgain"));
				}
				output_notl("`n");
			}
			if (get_module_setting("ismax") == 1) {
				rawoutput("<input type='radio' name='wish' value='2'>");
				output("Vitality");
				if ($mincost != $maxcost) {
					$cm = get_module_setting("maxcost");
					$hm = get_module_setting("vitalgain");
					$hptype = "permanent";
					if (!get_module_setting("carrydk") ||
							(is_module_active("globalhp") &&
							 !get_module_setting("carrydk", "globalhp")))
						$hptype = "temporary";
					$hptype = translate_inline($hptype);

					output("(%s %s for %s %s max %s)", $cm,
							translate_inline($cm==1?"gem":"gems"), $hm,
							$hptype,
							translate_inline($hm==1?"hitpoint":"hitpoints"));
				}
				output_notl("`n");
			}
			if (get_module_setting("istemp") == 1) {
				rawoutput("<input type='radio' name='wish' value='3'>");
				output("Health");
				if ($mincost != $maxcost) {
					$cm = get_module_setting("tempcost");
					$hm = get_module_setting("tempgain");
					output("(%s %s for %s %s)", $cm, translate_inline($cm==1?"gem":"gems"), $hm, translate_inline($hm == 1? "hitpoint":"hitpoints"));
				}
				output_notl("`n");
			}
			if (get_module_setting("isforget") == 1) {
				rawoutput("<input type='radio' name='wish' value='4'>");
				output("Forgetfulness");
				if ($mincost != $maxcost) {
					$cm = get_module_setting("forgcost");
					output_notl("(%s %s)", $cm, translate_inline($cm==1?"gem":"gems"));
				}
				output_notl("`n");
			}
			if (get_module_setting("istrans") == 1) {
				rawoutput("<input type='radio' name='wish' value='5'>");
				output("Transmutation");
				if ($mincost != $maxcost) {
					$cm = get_module_setting("transcost");
					output_notl("(%s %s)", $cm, translate_inline($cm==1?"gem":"gems"));
				}
				output_notl("`n");
			}
			rawoutput("</form>");
		}else{
            $gemcount=$quantity;
            try { $cost = cedrikspotions_price((int)$wish); }
            catch (DomainException $error) { http_response_code(400); exit('Invalid potion cost.'); }
            try {
                resurrection_player_mutation(function () use ($gemcount,$cost,$wish,$barkeep) {
                    global $session;
                    require_once 'lib/typed_editor.php';
                    $schema=\Resurrection\Http\SettingDescriptor::declare(cedrikspotions_getmoduleinfo()['settings']);
                    $configured=resurrection_settings_values('cedrikspotions',true);
                    $effectKeys=[1=>['charmgain'],2=>['vitalgain','carrydk'],3=>['tempgain'],4=>[],5=>['transmuteturns','atkmod','defmod','survive']];
                    foreach ($effectKeys[$wish] as $key) {
                        $value=\Resurrection\Http\SettingDescriptor::value($schema[$key],$configured[$key] ?? $schema[$key]['default']);
                        if (in_array($key,['charmgain','vitalgain','tempgain'],true) && (int)$value<0) throw new DomainException('Negative potion effect.');
                    }
                    $GLOBALS['module_settings']['cedrikspotions']=$configured;
                    $available=[1=>'ischarm',2=>'ismax',3=>'istemp',4=>'isforget',5=>'istrans'];
                    if ((string)get_module_setting($available[$wish],'cedrikspotions')!=='1') throw new DomainException('Potion unavailable.');
                    $cost=cedrikspotions_price((int)$wish);
			if ($gemcount>$session['user']['gems']){
				output("%s`0 stares at you blankly.",$barkeep);
				output("\"`%You don't have that many gems, `bgo get some more gems!`b`0\" he says.");
			}else{
				output("`#You place %s %s on the counter.", $gemcount, translate_inline($gemcount==1?"gem":"gems"));
				if (($wish == 4 || $wish == 5) && $gemcount > $cost) {
					output("%s`0, feeling sorry for you, prevents you from paying for multiple doses of a potion that only needs a single dose.",$barkeep);
					$gemcount = $cost;
				}
				$strength = ($gemcount/$cost);
				if(!is_integer($strength)){
					output("%s`0, knowing about your fundamental misunderstanding of math, hands some of them back to you.",$barkeep);
					$strength = floor($strength);
					$gemcount=($strength * $cost);
				}
				if ($gemcount>0) {
					output("You drink the potion %s`0 hands you in exchange for your %s, and.....`n`n",$barkeep, translate_inline($gemcount==1?"gem":"gems"));
					$session['user']['gems']-=$gemcount;
					switch($wish){
					case 1:
						$session['user']['charm'] += ($strength *
								get_module_setting("charmgain"));
						output("`&You feel charming!");
						output("`^(You gain %s charm %s.)",
								$strength*get_module_setting("charmgain"),
								translate_inline($strength *
									get_module_setting("charmgain")==1 ?
									"point" : "points"));
						$potiontype = "charm";
						break;
					case 2:
						$session['user']['maxhitpoints'] +=
							($strength*get_module_setting("vitalgain"));
						$session['user']['hitpoints'] +=
							($strength*get_module_setting("vitalgain"));
						output("`&You feel vigorous!");
						$hptype = "permanently";
						if (!get_module_setting("carrydk") ||
								(is_module_active("globalhp") &&
								 !get_module_setting("carrydk", "globalhp")))
							$hptype = "temporarily";
						$hptype = translate_inline($hptype);

						output("`^(You %s gain %s max %s.)", $hptype,
								$strength * get_module_setting("vitalgain"),
								translate_inline($strength *
									get_module_setting("vitalgain") == 1 ?
									"hitpoint" : "hitpoints"));
						$potiontype = "vitality";
						set_module_pref("extrahps",
								cedrikspotions_extra_hp() +
								($strength * get_module_setting("vitalgain")));
						break;
					case 3:
						if ($session['user']['hitpoints'] <
								$session['user']['maxhitpoints'])
							$session['user']['hitpoints'] =
								$session['user']['maxhitpoints'];
						$session['user']['hitpoints'] +=
							($strength * get_module_setting("tempgain"));
						output("`&You feel healthy!");
						output("`^(You gain %s temporary %s.)",
								$strength * get_module_setting("tempgain"),
								translate_inline($strength *
									get_module_setting("tempgain") == 1 ?
									"hitpoint" : "hitpoints"));
						$potiontype = "health";
						break;
					case 4:
						$session['user']['specialty']="";
						output("`&You feel completely directionless in life.");
						output("You should rest and make some important decisions about your life!");
						output("`^(Your specialty has been reset.)");
						$potiontype = "forgetfulness";
						break;
					case 5:
						$session['user']['race']=RACE_UNKNOWN;
						output("`@You double over retching from the effects of transformation potion as your bones turn to gelatin!`n");
						output("`^(Your race has been reset and you will be able to chose a new one tomorrow.)");
						strip_buff('racialbenefit');
						$potiontype = "transmutation";
                        require_once 'src/Game/TransmutationState.php';
                        $rounds=(int)get_module_setting('transmuteturns');
                        if (isset($session['bufflist']['transmute'])) {
                            $buff=\Resurrection\Game\TransmutationState::read($session['bufflist']['transmute']);
                            $buff['rounds'] += $rounds;
                            $buff=\Resurrection\Game\TransmutationState::read($buff);
                        } else {
                            $buff=\Resurrection\Game\TransmutationState::create($rounds,(float)get_module_setting('atkmod'),(float)get_module_setting('defmod'),(int)get_module_setting('survive'));
                        }
                        apply_buff('transmute',$buff);
						break;
					}
					debuglog("used $gemcount gems on $potiontype potions");
				}else{
					output("`n`nYou feel as though your gems would be better used elsewhere, not on some smelly potion.");
				}
			}
                    foreach (['gems'=>2147483647,'hitpoints'=>2147483647,'maxhitpoints'=>4294967295] as $field=>$maximum) {
                        if ($session['user'][$field]<0 || $session['user'][$field]>$maximum) throw new DomainException('Potion state exceeds bounds.');
                    }
                    if ($session['user']['charm']<0 || $session['user']['charm']>4294967295) throw new DomainException('Charm exceeds bounds.');
                    if (isset($session['bufflist']['transmute']['rounds']) && $session['bufflist']['transmute']['rounds']>2147483647) throw new DomainException('Potion duration exceeds bounds.');
                });
            } catch (DomainException|InvalidArgumentException $error) { http_response_code(400); exit('Potion purchase rejected.'); }
            catch (Throwable $error) { http_response_code(500); exit('Potion purchase was not completed.'); }
		}
		addnav("I?Return to the Inn","inn.php");
		villagenav();
	}
	rawoutput("</span>");
	page_footer();
}

/** Persisted vitality bookkeeping is a bounded count, never an arbitrary scalar. */
function cedrikspotions_extra_hp(): int {
    $raw=get_module_pref('extrahps','cedrikspotions');
    if ($raw===null || $raw==='') return 0;
    if (!is_int($raw) && !is_string($raw)) throw new DomainException('Invalid vitality state.');
    $value=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>4294967295]]);
    if ($value===false) throw new DomainException('Invalid vitality state.');
    return $value;
}

/** Exact configured vocabulary; quantity still means gems offered, as historically. */
function cedrikspotions_price(int $wish): int {
    $keys=[1=>'charmcost',2=>'maxcost',3=>'tempcost',4=>'forgcost',5=>'transcost'];
    if (!isset($keys[$wish])) throw new DomainException('Unknown potion.');
    $random = get_module_setting('random','cedrikspotions');
    if (!in_array((string)$random,['0','1'],true)) throw new DomainException('Invalid price mode.');
    if ((string)$random === '1') {
        [$min,$max] = cedrikspotions_random_bounds();
        $cost = get_module_setting('randcost','cedrikspotions');
    } else {
        $min=1; $max=10; $cost=get_module_setting($keys[$wish],'cedrikspotions');
    }
    $price=filter_var($cost,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
    if ($price === false) throw new DomainException('Invalid potion price.');
    return $price;
}

/** @return array{int,int} */
function cedrikspotions_random_bounds(): array {
    $min=filter_var(get_module_setting('minrand','cedrikspotions'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>9]]);
    $max=filter_var(get_module_setting('maxrand','cedrikspotions'),FILTER_VALIDATE_INT,['options'=>['min_range'=>2,'max_range'=>10]]);
    if ($min === false || $max === false || $min>$max) throw new DomainException('Invalid random potion bounds.');
    return [$min,$max];
}
?>
