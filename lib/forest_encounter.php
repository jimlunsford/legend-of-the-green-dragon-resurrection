<?php
/** Historical Forest encounter producer, called only inside the ordinary POST transaction. */
function resurrection_forest_encounter(): bool {
    global $session, $playermount;
    $op="search"; $battle=false;
if ($op=="search"){

	if ($session['user']['turns']<=0){
		output("`\$`bYou are too tired to search the forest any longer today.  Perhaps tomorrow you will have more energy.`b`0");
		$op="";
		httpset('op', "");
	}else{
		modulehook("forestsearch", array());
		$args = array(
			'soberval'=>0.9,
			'sobermsg'=>"`&Faced with the prospect of death, you sober up a little.`n",
			'schema'=>'forest');
		modulehook("soberup", $args);
		if (module_events("forest", getsetting("forestchance", 15), false, true) != 0) {
			if (!checknavs()) {
				// If we're showing the forest, make sure to reset the special
				// and the specialmisc
				$session['user']['specialinc'] = "";
				$session['user']['specialmisc'] = "";
				$dontdisplayforestmessage=true;
				$op = "";
				httpset("op", "");
			} else {
				return false;
			}
		}else{
			$session['user']['turns']--;
			$battle=true;
			if (e_rand(0,2)==1){
				$plev = (e_rand(1,5)==1?1:0);
				$nlev = (e_rand(1,3)==1?1:0);
			}else{
				$plev=0;
				$nlev=0;
			}
			$type = httpget('type');
			if ($type=="slum"){
				$nlev++;
				output("`\$You head for the section of forest you know to contain foes that you're a bit more comfortable with.`0`n");
			}
			if ($type=="thrill"){
				$plev++;
				output("`\$You head for the section of forest which contains creatures of your nightmares, hoping to find one of them injured.`0`n");
			}
			$extrabuff = 0;
			if ($type=="suicide"){
				if ($session['user']['level'] <= 7) {
					$plev += 1;
					$extrabuf = .25;
				} elseif ($session['user']['level'] < 14) {
					$plev+=2;
					$extrabuf = 0;
				} else {
					$plev++;
					$extrabuff = .4;
				}
				output("`\$You head for the section of forest which contains creatures of your nightmares, looking for the biggest and baddest ones there.`0`n");
			}
			$multi = 1;
			$targetlevel = ($session['user']['level'] + $plev - $nlev );
			$mintargetlevel = $targetlevel;
			if (getsetting("multifightdk", 10) <= $session['user']['dragonkills']) {
				if (e_rand(1,100) <= getsetting("multichance", 25)) {
					$multi = e_rand(getsetting("multibasemin", 2),getsetting("multibasemax", 3));
					if ($type=="slum") {
						$multi -= e_rand(getsetting("multislummin", 0),getsetting("multislummax", 1));
						if (e_rand(0,1)) {
							$mintargetlevel = $targetlevel - 1;
						} else {
							$mintargetlevel = $targetlevel - 2;
						}
					} else if ($type == "thrill") {
						$multi += e_rand(getsetting("multithrillmin", 1),getsetting("multithrillmax", 2));
						if (e_rand(0,1)) {
							$targetlevel++;
							$mintargetlevel = $targetlevel - 1;
						} else {
							$mintargetlevel = $targetlevel-1;
						}
					} else if ($type == "suicide") {
						$multi += e_rand(getsetting("multisuimin", 2),getsetting("multisuimax", 4));
						if (e_rand(0,1)) {
							$mintargetlevel = $targetlevel - 1;
						} else {
							$targetlevel++;
							$mintargetlevel = $targetlevel - 1;
						}
					}
					$multi = min($multi, $session['user']['level']);
				}
			} else {
				$multi = 1;
			}
			$multi = max(1, $multi);
			if ($targetlevel<1) $targetlevel=1;
			if ($mintargetlevel<1) $mintargetlevel=1;
			if ($mintargetlevel > $targetlevel) $mintargetlevel = $targetlevel;
			if ($targetlevel>17) {
				$multi += $targetlevel - 17;
				$targetlevel=17;
			}
			debug("Creatures: $multi Targetlevel: $targetlevel Mintargetlevel: $mintargetlevel");
			if ($multi > 1) {
				$packofmonsters = (bool)(e_rand(0,5) == 0 && getsetting("allowpackofmonsters", true)); // true or false
				switch($packofmonsters) {
					case false:
						$sql = "SELECT * FROM " . db_prefix("creatures") . " WHERE creaturelevel <= $targetlevel AND creaturelevel >= $mintargetlevel AND forest=1 ORDER BY rand(".e_rand().") LIMIT $multi";
						break;
					case true:
						$sql = "SELECT * FROM " . db_prefix("creatures") . " WHERE creaturelevel <= $targetlevel AND creaturelevel >= $mintargetlevel AND forest=1 ORDER BY rand(".e_rand().") LIMIT 1";
						break;
				}
			} else {
				$sql = "SELECT * FROM " . db_prefix("creatures") . " WHERE creaturelevel <= $targetlevel AND creaturelevel >= $mintargetlevel AND forest=1 ORDER BY rand(".e_rand().") LIMIT 1";
				$packofmonsters = 0;
			}
			$result = db_query($sql);
			restore_buff_fields();
			if (db_num_rows($result) == 0) {
				// There is nothing in the database to challenge you, let's
				// give you a doppleganger.
				$badguy = array();
				$badguy['creaturename']=
					"An evil doppleganger of ".$session['user']['name'];
				$badguy['creatureweapon']=$session['user']['weapon'];
				$badguy['creaturelevel']=$session['user']['level'];
				$badguy['creaturegold']=0;
				$badguy['creatureexp'] =
				round($session['user']['experience']/10, 0);
				$badguy['creaturehealth']=$session['user']['maxhitpoints'];
            $badguy['playerstarthp']=$session['user']['hitpoints'];
            $badguy['diddamage']=0;
				$badguy['creatureattack']=$session['user']['attack'];
				$badguy['creaturedefense']=$session['user']['defense'];
				$stack[] = $badguy;
			} else {
				require_once("lib/forestoutcomes.php");
				if ($packofmonsters == true) {
					$initialbadguy = db_fetch_assoc($result);
					$prefixs = array("Elite","Dangerous","Lethal","Savage","Deadly","Malevolent","Malignant");
					for($i=0;$i<$multi;$i++) {
						$initialbadguy['creaturelevel'] = e_rand($mintargetlevel, $targetlevel);
						$initialbadguy['playerstarthp']=$session['user']['hitpoints'];
						$initialbadguy['diddamage']=0;
						$badguy = buffbadguy($initialbadguy);
						if ($type == "thrill") {
							// 10% more experience
							$badguy['creatureexp'] = round($badguy['creatureexp']*1.1, 0);
							// 10% more gold
							$badguy['creaturegold'] = round($badguy['creaturegold']*1.1, 0);
						}
						if ($type == "suicide") {
							// Okay, suicide fights give even more rewards, but
							// are much harder
							// 25% more experience
							$badguy['creatureexp'] = round($badguy['creatureexp']*1.25, 0);
							// 25% more gold
							$badguy['creaturegold'] = round($badguy['creaturegold']*1.25, 0);
							// Now, make it tougher.
							$mul = 1.25 + $extrabuff;
							$badguy['creatureattack'] = round($badguy['creatureattack']*$mul, 0);
							$badguy['creaturedefense'] = round($badguy['creaturedefense']*$mul, 0);
							$badguy['creaturehealth'] = round($badguy['creaturehealth']*$mul, 0);
							// And mark it as an 'elite' troop.
							$prefixs = translate_inline($prefixs);
							$key = array_rand($prefixs);
							$prefix = $prefixs[$key];
							$badguy['creaturename'] = $prefix . " " . $badguy['creaturename'];
						}
						$stack[$i] = $badguy;
					}
					if ($multi > 1) {
						output("`2You encounter a group of `^%i`2 %s`2.`n`n", $multi, $badguy['creaturename']);
					}
				} else {
					while ($badguy = db_fetch_assoc($result)) {
						$badguy['playerstarthp']=$session['user']['hitpoints'];
						$badguy['diddamage']=0;
						$badguy = buffbadguy($badguy);
						// Okay, they are thrillseeking, let's give them a bit extra
						// exp and gold.
						if ($type == "thrill") {
							// 10% more experience
							$badguy['creatureexp'] = round($badguy['creatureexp']*1.1, 0);
							// 10% more gold
							$badguy['creaturegold'] = round($badguy['creaturegold']*1.1, 0);
						}
						if ($type == "suicide") {
							// Okay, suicide fights give even more rewards, but
							// are much harder
							// 25% more experience
							$badguy['creatureexp'] = round($badguy['creatureexp']*1.25, 0);
							// 25% more gold
							$badguy['creaturegold'] = round($badguy['creaturegold']*1.25, 0);
							// Now, make it tougher.
							$mul = 1.25 + $extrabuff;
							$badguy['creatureattack'] = round($badguy['creatureattack']*$mul, 0);
							$badguy['creaturedefense'] = round($badguy['creaturedefense']*$mul, 0);
							$badguy['creaturehealth'] = round($badguy['creaturehealth']*$mul, 0);
							// And mark it as an 'elite' troop.
							$prefixs = array("Elite","Dangerous","Lethal","Savage","Deadly","Malevolent","Malignant");
							$prefixs = translate_inline($prefixs);
							$key = array_rand($prefixs);
							$prefix = $prefixs[$key];
							$badguy['creaturename'] = $prefix . " " . $badguy['creaturename'];
						}
						$stack[] = $badguy;
					}
				}
			}
			calculate_buff_fields();
			$attackstack = array(
				"enemies"=>$stack,
				"options"=>array(
					"type"=>"forest", "encounter"=>bin2hex(random_bytes(16))
				)
			);
			$session['user']['badguy']=createstring($attackstack);
			// If someone for any reason wanted to add a nav where the user cannot choose the number of rounds anymore
			// because they are already set in the nav itself, we need this here.
			// It will not break anything else. I hope.
			if(httpget('auto') != "") {
				httpset('op', 'fight');
				$op = 'fight';
			}
		}
	}
}

    return $battle;
}
