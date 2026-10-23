<?php
require_once("lib/haunt_authority.php");
output("`)`c`bThe Mausoleum`b`c");
// Only the protected POST haunts. GET (a target link, a restored page, New Day's "Continue") asks.
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
try {
	$name = resurrection_haunt_login();
	if ($posted) resurrection_haunt_fields();
} catch (InvalidArgumentException $error) { resurrection_haunt_failure($error); }
$row = resurrection_haunt_target($name);
if ($posted){
	resurrection_consume_action('graveyard-haunt', $row === null ? '' : resurrection_haunt_context($row));
	try {
		// The favor, target, roll, news and notice change together, under the player's and the target's locks.
		$haunt = resurrection_haunt_mutation(function () use ($row) {
			global $session;
			$locked = db_query('SELECT acctid,login,name,level,hauntedby,locked FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [(int)$row['acctid']]);
			if (count($locked) !== 1 || (string)$locked[0]['login'] !== (string)$row['login'] || (int)$locked[0]['locked'] !== 0 ||
				(int)$locked[0]['acctid'] === (int)$session['user']['acctid']) return array('outcome'=>'missing');
			// Only the dead haunt; graveyard.php sends the living to the village unless an event holds the page.
			if ($session['user']['alive']) throw new DomainException('The living cannot haunt.');
			$row = $locked[0];
			if ($row['hauntedby']!="") return array('outcome'=>'haunted');
			// 1.1.2 charged whatever favor remained; the 25 it costs is now required.
			if ((int)$session['user']['deathpower'] < RESURRECTION_HAUNT_COST) throw new DomainException('Not enough favor.');
			$session['user']['deathpower']-=RESURRECTION_HAUNT_COST;
			$roll1 = e_rand(0,$row['level']);
			$roll2 = e_rand(0,$session['user']['level']);
			if ($roll2>$roll1){
				// hauntedby is varchar(50) and names are varchar(60); strict SQL now refuses what 1.1.2's MySQL truncated.
				db_query('UPDATE ' . db_prefix('accounts') . ' SET hauntedby=? WHERE acctid=?', true, [mb_substr((string)$session['user']['name'], 0, 50, 'UTF-8'), (int)$row['acctid']]);
				addnews("`7%s`) haunted `7%s`)!",$session['user']['name'],$row['name']);
				$subj = array("`)You have been haunted");
				$body = array("`)You have been haunted by `&%s`).",$session['user']['name']);
				if (!systemmail((int)$row['acctid'], $subj, $body)) throw new DomainException('Haunt target no longer exists.');
				return array('outcome'=>'success', 'row'=>$row);
			}
			addnews("`7%s`) unsuccessfully haunted `7%s`)!",$session['user']['name'],$row['name']);
			return array('outcome'=>'failure', 'row'=>$row, 'message'=>e_rand(0,5));
		}, (int)($row['acctid'] ?? 0));
	} catch (Throwable $error) { resurrection_haunt_failure($error); }
	if ($haunt['outcome']=='missing'){
		output("`\$%s`) has lost their concentration on this person, you cannot haunt them now.",$deathoverlord);
	}elseif ($haunt['outcome']=='haunted'){
		output("That person has already been haunted, please select another target");
	}elseif ($haunt['outcome']=='success'){
		output("You have successfully haunted `7%s`)!", $haunt['row']['name']);
	}else{
		switch ($haunt['message']){
		case 0:
			$msg = "Just as you were about to haunt `7%s`) good, they sneezed, and missed it completely.";
			break;
		case 1:
			$msg = "You haunt `7%s`) real good like, but unfortunately they're sleeping and are completely unaware of your presence.";
			break;
		case 2:
			$msg = "You're about to haunt `7%s`), but trip over your ghostly tail and land flat on your, um... face.";
			break;
		case 3:
			$msg = "You go to haunt `7%s`) in their sleep, but they look up at you, and roll over mumbling something about eating sausage just before going to bed.";
			break;
		case 4:
			$msg = "You wake `7%s`) up, who looks at you for a moment before declaring, \"Neat!\" and trying to catch you.";
			break;
		default:
			$msg = "You go to scare `7%s`), but catch a glimpse of yourself in the mirror and panic at the sight of a ghost!";
			break;
		}
		output($msg, $haunt['row']['name']);
	}
}elseif ($row !== null){
	if ($row['hauntedby']!=""){
		output("That person has already been haunted, please select another target");
	}elseif ($session['user']['deathpower'] < RESURRECTION_HAUNT_COST){
		output("`\$%s`) speaks, \"`7I am not yet impressed with your efforts.  Continue my work, and we may speak further.`)\"",$deathoverlord);
	}else{
		// 1.1.2 haunted on this GET; the haunt is now this form's protected POST.
		resurrection_haunt_form($row);
	}
}else{
	output("`\$%s`) has lost their concentration on this person, you cannot haunt them now.",$deathoverlord);
}
addnav(array("Question `\$%s`0 about the worth of your soul",$deathoverlord),"graveyard.php?op=question");
$max = $session['user']['level'] * 5 + 50;
$favortoheal = round(10 * ($max-$session['user']['soulpoints'])/$max);
addnav(array("Restore Your Soul (%s favor)", $favortoheal),"graveyard.php?op=restore");
addnav("Places");
addnav("S?Land of the Shades","shades.php");
addnav("G?The Graveyard","graveyard.php");
addnav("M?Return to the Mausoleum","graveyard.php?op=enter");
?>
