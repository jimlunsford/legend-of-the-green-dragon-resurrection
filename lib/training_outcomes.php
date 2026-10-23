<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) { http_response_code(404); exit('Not an application endpoint.'); }
// Internal only: historical train.php settlement, called inside the player transaction.
        if ($victory){
            $badguy['creaturelose']=substitute_array($badguy['creaturelose']);
            output_notl("`b`&");
            output($badguy['creaturelose']);
            output_notl("`0`b`n");
            output("`b`\$You have defeated %s!`0`b`n",$badguy['creaturename']);

            $session['user']['level']++;
            $session['user']['maxhitpoints']+=10;
            $session['user']['soulpoints']+=5;
            $session['user']['attack']++;
            $session['user']['defense']++;
            // Fix the multimaster bug
            if (getsetting("multimaster", 1) == 1) {
                $session['user']['seenmaster']=0;
                debuglog("Defeated master, setting seenmaster to 0");
            }
            output("`#You advance to level `^%s`#!`n",$session['user']['level']);
            output("Your maximum hitpoints are now `^%s`#!`n",$session['user']['maxhitpoints']);
            output("You gain an attack point!`n");
            output("You gain a defense point!`n");
            if ($session['user']['level']<15){
                output("You have a new master.`n");
            }else{
                output("None in the land are mightier than you!`n");
            }
            if ($session['user']['referer']>0 && ($session['user']['level']>=getsetting("referminlevel",4) || $session['user']['dragonkills'] > 0) && $session['user']['refererawarded']<1){
                $sql = "UPDATE " . db_prefix("accounts") . " SET donation=donation+".getsetting("refereraward",25)." WHERE acctid={$session['user']['referer']}";
                db_query($sql);
                $session['user']['refererawarded']=1;
                $subj=array("`%One of your referrals advanced!`0");
                $body=array("`&%s`# has advanced to level `^%s`#, and so you have earned `^%s`# points!", $session['user']['name'], $session['user']['level'], getsetting("refereraward", 25));
                systemmail($session['user']['referer'],$subj,$body);
            }
            increment_specialty("`^");

            // Level-Up companions
            // We only get one level per pageload. So we just add the per-level-values.
            // No need to multiply and/or substract anything.
            if (getsetting("companionslevelup", 1) == true) {
                $newcompanions = $companions;
                foreach ($companions as $name => $companion) {
                    $companion['attack'] = $companion['attack'] + ($companion['attackperlevel'] ?? 0);
                    $companion['defense'] = $companion['defense'] + ($companion['defenseperlevel'] ?? 0);
                    $companion['maxhitpoints'] = $companion['maxhitpoints'] + ($companion['maxhitpointsperlevel'] ?? 0);
                    $companion['hitpoints'] = $companion['maxhitpoints'];
                    $newcompanions[$name] = $companion;
                }
                $companions = $newcompanions;
            }

            invalidatedatacache("list.php-warsonline");

            addnav("Question Master","train.php?op=question");
            addnav("M?Challenge Master","train.php?op=challenge");
            villagenav();
            if ($session['user']['age'] == 1) {
                if (getsetting('displaymasternews',1)) addnews("`%%s`3 has defeated ".($session['user']['sex']?"her":"his")." master, `%%s`3 to advance to level `^%s`3 after `^1`3 day!!", $session['user']['name'],$badguy['creaturename'],$session['user']['level']);
            } else {
                if (getsetting('displaymasternews',1)) addnews("`%%s`3 has defeated ".($session['user']['sex']?"her":"his")." master, `%%s`3 to advance to level `^%s`3 after `^%s`3 days!!", $session['user']['name'],$badguy['creaturename'],$session['user']['level'],$session['user']['age']);
            }
            if ($session['user']['hitpoints'] < $session['user']['maxhitpoints'])
                $session['user']['hitpoints'] = $session['user']['maxhitpoints'];
            modulehook("training-victory", $badguy);
        }elseif($defeat){
            $taunt = select_taunt_array();

            if (getsetting('displaymasternews',1)) addnews("`%%s`5 has challenged their master, %s and lost!`n%s",$session['user']['name'],$badguy['creaturename'],$taunt);
            $session['user']['hitpoints']=$session['user']['maxhitpoints'];
            output("`&`bYou have been defeated by `%%s`&!`b`n",$badguy['creaturename']);
            output("`%%s`\$ halts just before delivering the final blow, and instead extends a hand to help you to your feet, and hands you a complementary healing potion.`n",$badguy['creaturename']);
            $badguy['creaturewin']=substitute_array($badguy['creaturewin']);
            output_notl("`^`b");
            output($badguy['creaturewin']);
            output_notl("`b`0`n");
            addnav("Question Master","train.php?op=question");
            addnav("M?Challenge Master","train.php?op=challenge");
            villagenav();
            modulehook("training-defeat", $badguy);
        }
