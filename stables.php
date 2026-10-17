<?php
require_once __DIR__ . '/src/Security/ScalarState.php';
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/buffs.php");
require_once("lib/sanitize.php");
require_once("lib/villagenav.php");

tlschema('stables');

$basetext=array(
	"title"=>"Merick's Stables",
	"desc"=>array(
		"`7Behind the inn, and a little to the left of Ye Olde Bank, is as fine a stable as one might expect to find in any village. ",
		"In it, Merick, a burly looking dwarf tends to various beasts.`n`n",
		array("You approach, and he whirls around, pointing a pitchfork in your general direction, \"`&Ach, sorry m'%s, I dinnae hear ya' comin' up on me, an' I thoht fer sure ye were %s`&; he what been tryin' to improve on his dwarf tossin' skills. ",translate_inline($session['user']['sex']?'lass':'lad'),getsetting('barkeep','`tCedrik')),
		"Naahw, wha' can oye do fer ya?`7\" he asks.",
	),
	"nosuchbeast"=>"`7\"`&Ach, thar dinnae be any such beestie here!`7\" shouts the dwarf!",
	"finebeast"=>array(
		"`7\"`&Aye, tha' be a foyne beastie indeed!`7\" comments the dwarf.`n`n",
		"`7\"`&Ye cert'nly have an oye fer quality!`7\" exclaims the dwarf.`n`n",
		"`7\"`&Och, this beastie will serve ye well indeed,`7\" says the dwarf.`n`n",
		"`7\"`&That beastie be one o' me finest!`7\" says the dwarf with pride.`n`n",
		"`7\"`&Ye couldnae hae made a foyner choice o' beasts!`7\" says the dwarf with pride.`n`n"
	),
	"toolittle"=>"`7Merick looks at you sorta sideways.  \"`&'Ere, whadday ya think yeer doin'?  Cannae ye see that %s`& costs `^%s`& gold an' `%%s`& gems?`7\"",
	"replacemount"=>"`7You hand over the reins to %s`7 and the purchase price of your new critter, and Merick leads out a fine new `&%s`7 for you!`n`n",
	"newmount"=>"`7You hand over the purchase price of your new critter, and Merick leads out a fine `&%s`7 for you!`n`n",
	"nofeed"=>"`7\"`&Ach, m'%s, what dae ye think this is, a hostelry?  I cannae feed yer critter here!`7\"`nMerick thumps you on the back good naturedly, and sends you on your way.",
	"nothungry"=>"%s`7 isn't hungry.  Merick hands your gold back.",
	"halfhungry"=>"%s`7 pinches a bit of the given food and leaves the rest alone. %s`7 is fully restored. Because there is still more than half of the food left, Merick gives you 50%% discount.`nYou only pay %s gold.",
	"hungry"=>"%s`7 eats all the food greedily.`n%s`7 is fully restored and you give your %s gold to Merick.",
	"mountfull"=>"`n`7\"`&Aye, there ye go %s, yer %s`& be full o' foyne grub. I willnae be able t' feed 'em again 'til the morrow though.  Well, enjoy ye day!`7\"`nMerick whistles a jaunty tune and heads back to work.",
	"nofeedgold"=>"`7You don't have enough gold with you to pay for the food. Merick refuses to feed your creature and advises you to look for somewhere else to let %s`7 graze for free, such as in the `@Forest`7.",
	"confirmsale"=>"`n`n`7Merick whistles.  \"`&Yer mount shure is a foyne one, %s. Are ye sure ye wish t' part wae it?`7\"`n`nHe waits for your answer.`0",
	"mountsold"=>"`7As sad as it is to do so, you give up your precious %s`7, and a lone tear escapes your eye.`n`nHowever, the moment you spot the %s, you find that you're feeling quite a bit better.",
	"offer"=>"`n`n`&Merick offers you `^%s`& gold and `%%s`& gems for %s`7.",
	"lass"=>"lass",
	"lad"=>"lad",
);
$schemas = array(
	'title'=>'stables',
	'desc'=>'stables',
	'nosuchbeast'=>'stables',
	'finebeast'=>'stables',
	'toolittle'=>'stables',
	'replacemount'=>'stables',
	'newmount'=>'stables',
	'nofeed'=>'stables',
	'nothungry'=>'stables',
	'halfhungry'=>'stables',
	'hungry'=>'stables',
	'mountfull'=>'stables',
	'nofeedgold'=>'stables',
	'confirmsale'=>'stables',
	'mountsold'=>'stables',
	'offer'=>'stables',
);
$basetext['schemas']=$schemas;
$texts = modulehook("stabletext", $basetext);
$schemas = $texts['schemas'];

tlschema($schemas['title']);
page_header($texts['title']);
tlschema();

addnav("Other");
villagenav();
modulehook("stables-nav");

require_once("lib/mountname.php");
list($name, $lcname) = getmountname();

$repaygold = 0;
$repaygems = 0;
$grubprice = 0;

if ($playermount) {
	$repaygold = round($playermount['mountcostgold']*2/3,0);
	$repaygems = round($playermount['mountcostgems']*2/3,0);
	$grubprice = round($session['user']['level']*$playermount['mountfeedcost'], 0);
}
$confirm = 0;

require_once("lib/stables_authority.php");
$op = httpget('op');
// Only the protected POST of a confirming step acts. GET (a link, a restored page, New Day's "Continue") asks.
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if (!$posted && $op=='confirmbuy') $op = 'buymount';
if (!$posted && $op=='confirmsell') $op = 'sellmount';
$id = 0;
if (in_array($op, array('examine','buymount','confirmbuy'), true)) {
	try {
		$id = resurrection_stables_id();
	} catch (InvalidArgumentException $error) { resurrection_stables_failure($error); }
}

global $playermount;

if ($op==""){
	checkday();
	tlschema($schemas['desc']);
  	if (is_array($texts['desc'])) {
  		foreach ($texts['desc'] as $description) {
  			output_notl(sprintf_translate($description));
  		}
  	} else {
  		output($texts['desc']);
  	}
	tlschema();
	modulehook("stables-desc");
}elseif($op=="examine"){
	// Bound; only a mount the listing offers this player is shown (1.1.2 examined any id the URL named).
	$mount = resurrection_stables_mount($id);
	if ($mount === null || !resurrection_stables_offered($mount)){
		tlschema($schemas['nosuchbeast']);
		output($texts['nosuchbeast']);
		tlschema();
	}else{
		// Idea taken from Robert of dragonprime.cawsquad.net
		$t = e_rand(0,count($texts['finebeast'])-1);
		tlschema($schemas['finebeast']);
		output($texts['finebeast'][$t]);
		tlschema();
		output("`7Creature: `&%s`0`n", $mount['mountname']);
		output("`7Description: `&%s`0`n", $mount['mountdesc']);
		output("`7Cost: `^%s`& gold, `%%s`& gems`n`n", $mount['mountcostgold'], $mount['mountcostgems']);
		addnav(array("New %s", $mount['mountname']));
		addnav("Buy this creature","stables.php?op=buymount&id={$mount['mountid']}");
	}
}elseif($op=='buymount'){
	$mount = resurrection_stables_mount($id);
	if ($mount === null || !resurrection_stables_offered($mount)){
		tlschema($schemas['nosuchbeast']);
		output($texts['nosuchbeast']);
		tlschema();
	}elseif ($session['user']['hashorse']) {
		tlschema($schemas['confirmsale']);
		output($texts['confirmsale'],
				($session['user']['sex']?$texts["lass"]:$texts["lad"]));
		tlschema();
		addnav("Confirm trade");
		resurrection_stables_form('confirmbuy', $id, translate_inline("Yes"));
		addnav("No","stables.php");
		$confirm = 1;
	} else {
		// 1.1.2 bought on this GET; the purchase is now this form's protected POST.
		output("`7Creature: `&%s`0`n", $mount['mountname']);
		output("`7Cost: `^%s`& gold, `%%s`& gems`n`n", $mount['mountcostgold'], $mount['mountcostgems']);
		resurrection_stables_form('confirmbuy', $id, translate_inline("Buy this creature"));
	}
}elseif($op == 'confirmbuy') {
	try {
		resurrection_stables_fields();
	} catch (InvalidArgumentException $error) { resurrection_stables_failure($error); }
	resurrection_consume_action('stables-confirmbuy', resurrection_stables_context('confirmbuy', $id));
	$oldlcname = $lcname;
	try {
		// The offer, funds (counting the 2/3 trade-in), mount, purse, buff and hooks change together, under lock.
		$bought = resurrection_player_mutation(function () use ($id) {
			global $session, $playermount;
			$mount = resurrection_stables_mount($id, true);
			if ($mount === null || !resurrection_stables_offered($mount)) return array('outcome'=>'missing');
			$repaygold = $playermount ? round($playermount['mountcostgold']*2/3,0) : 0;
			$repaygems = $playermount ? round($playermount['mountcostgems']*2/3,0) : 0;
			if (($session['user']['gold']+$repaygold) < $mount['mountcostgold'] ||
				($session['user']['gems']+$repaygems) < $mount['mountcostgems']){
				return array('outcome'=>'short', 'mount'=>$mount);
			}
			$buff = resurrection_stables_buff($mount);
			$replaced = $session['user']['hashorse']>0;
			$debugmount1=isset($playermount['mountname'])?$playermount['mountname']:false;
			if ($debugmount1) $debugmount1="a ".$debugmount1;
			$session['user']['hashorse']=$mount['mountid'];
			$debugmount2=$mount['mountname'];
			$goldcost = $repaygold-$mount['mountcostgold'];
			$session['user']['gold']+=$goldcost;
			$gemcost = $repaygems-$mount['mountcostgems'];
			$session['user']['gems']+=$gemcost;
			debuglog(($goldcost <= 0?"spent ":"gained ") . abs($goldcost) . " gold and " . ($gemcost <= 0?"spent ":"gained ") . abs($gemcost) . " gems trading $debugmount1 for a new mount, a $debugmount2");
			apply_buff('mount',$buff);
			// Recalculate so the selling stuff works right
			$playermount = getmount($mount['mountid']);
			// Recalculate the special name as well.
			modulehook("stable-mount", array());
			modulehook("boughtmount");
			return array('outcome'=>'bought', 'replaced'=>$replaced, 'mount'=>$mount);
		});
	} catch (Throwable $error) { resurrection_stables_failure($error); }
	if ($bought['outcome']=='missing'){
		tlschema($schemas['nosuchbeast']);
		output($texts['nosuchbeast']);
		tlschema();
	}elseif ($bought['outcome']=='short'){
		$mount = $bought['mount'];
		tlschema($schemas['toolittle']);
		output($texts['toolittle'], $mount['mountname'], $mount['mountcostgold'], $mount['mountcostgems']);
		tlschema();
	}else{
		$mount = $bought['mount'];
		if ($bought['replaced']){
			tlschema($schemas['replacemount']);
			output($texts['replacemount'], $oldlcname, $mount['mountname']);
			tlschema();
		}else{
			tlschema($schemas['newmount']);
			output($texts['newmount'], $mount['mountname']);
			tlschema();
		}
		$repaygold = round($playermount['mountcostgold']*2/3,0);
		$repaygems = round($playermount['mountcostgems']*2/3,0);
		require_once("lib/mountname.php");
		list($name, $lcname) = getmountname();
		$grubprice = round($session['user']['level']*$playermount['mountfeedcost'], 0);
	}
}elseif($op=='feed'){
	if (getsetting("allowfeed", 0) == 0) {
		tlschema($schemas['nofeed']);
		output($texts['nofeed'],
				($session['user']['sex']?$texts["lass"]:$texts["lad"]));
		tlschema();
	} elseif (!$posted) {
		// 1.1.2 fed on this GET; the feeding is now this form's protected POST.
		if ($session['user']['hashorse']>0 && $session['user']['fedmount']==0) {
			resurrection_stables_form('feed', 0, sprintf_translate("Feed %s`0 (`^%s`0 gold)", $lcname, $grubprice));
		}
	} else {
		try {
			resurrection_stables_fields();
		} catch (InvalidArgumentException $error) { resurrection_stables_failure($error); }
		resurrection_consume_action('stables-feed', resurrection_stables_context('feed'));
		try {
			$fed = resurrection_player_mutation(function () {
				global $session, $playermount;
				if (getsetting("allowfeed", 0) == 0) return array('outcome'=>'nofeed');
				// The player must own a mount; 1.1.2 limited feeding to once a day only by hiding the link.
				if ($session['user']['hashorse']<1 || !$playermount) throw new DomainException('No mount.');
				if ($session['user']['fedmount']!=0) throw new DomainException('Already fed today.');
				$grubprice = round($session['user']['level']*$playermount['mountfeedcost'], 0);
				if ($session['user']['gold']<$grubprice) return array('outcome'=>'nofeedgold');
				$buff = resurrection_stables_buff($playermount);
				if (!isset($buff['schema']) || $buff['schema'] == "") $buff['schema'] = "mounts";
				if (isset($session['bufflist']['mount']) && $session['bufflist']['mount']['rounds'] == $buff['rounds']) {
					return array('outcome'=>'nothungry');
				}
				$outcome = 'hungry';
				if (isset($session['bufflist']['mount']) && $session['bufflist']['mount']['rounds'] > $buff['rounds']*.5) {
					$grubprice=round($grubprice/2,0);
					$outcome = 'halfhungry';
				}
				$session['user']['gold']-=$grubprice;
				debuglog("spent $grubprice feeding their mount");
				apply_buff('mount',$buff);
				$session['user']['fedmount'] = 1;
				return array('outcome'=>$outcome, 'price'=>$grubprice);
			});
		} catch (Throwable $error) { resurrection_stables_failure($error); }
		if ($fed['outcome']=='nofeed') {
			tlschema($schemas['nofeed']);
			output($texts['nofeed'],
					($session['user']['sex']?$texts["lass"]:$texts["lad"]));
			tlschema();
		} elseif ($fed['outcome']=='nofeedgold') {
			tlschema($schemas['nofeedgold']);
			output($texts['nofeedgold'], $lcname);
			tlschema();
		} elseif ($fed['outcome']=='nothungry') {
			tlschema($schemas['nothungry']);
			output($texts['nothungry'],$name);
			tlschema();
		} else {
			tlschema($schemas[$fed['outcome']]);
			output($texts[$fed['outcome']], $name, $name, $fed['price']);
			tlschema();
			tlschema($schemas['mountfull']);
			// No bundled module sets 'basename'; PHP 8 made the missing key fatal after the feeding was logged.
			output($texts['mountfull'],
				($session['user']['sex']?$texts["lass"]:$texts["lad"]),
				(($playermount['basename'] ?? '')?
				 $playermount['basename']:$playermount['mountname']));
			tlschema();
		}
	}
}elseif($op=='sellmount'){
	tlschema($schemas['confirmsale']);
	output($texts['confirmsale'],
			($session['user']['sex']?$texts["lass"]:$texts["lad"]));
	tlschema();
	addnav("Confirm sale");
	if ($session['user']['hashorse']>0) resurrection_stables_form('confirmsell', 0, translate_inline("Yes"));
	addnav("No","stables.php");
	$confirm = 1;
}elseif($op=='confirmsell'){
	try {
		resurrection_stables_fields();
	} catch (InvalidArgumentException $error) { resurrection_stables_failure($error); }
	resurrection_consume_action('stables-confirmsell', resurrection_stables_context('confirmsell'));
	try {
		$sold = resurrection_player_mutation(function () {
			global $session, $playermount;
			// 1.1.2 "sold" without a mount; the player must own one.
			if ($session['user']['hashorse']<1 || !$playermount) throw new DomainException('No mount.');
			$repaygold = round($playermount['mountcostgold']*2/3,0);
			$repaygems = round($playermount['mountcostgems']*2/3,0);
			$session['user']['gold']+=$repaygold;
			$session['user']['gems']+=$repaygems;
			$debugmount=$playermount['mountname'];
			debuglog("gained $repaygold gold and $repaygems gems selling their mount, a $debugmount");
			strip_buff('mount');
			$session['user']['hashorse']=0;
			modulehook("soldmount");
			return array($repaygold, $repaygems);
		});
	} catch (Throwable $error) { resurrection_stables_failure($error); }
	list($repaygold, $repaygems) = $sold;

	$amtstr = "";
	if ($repaygold > 0) {
		$amtstr .= "%s gold";
	}
	if ($repaygems > 0) {
		if ($repaygold) $amtstr .= " and ";
		$amtstr .= "%s gems";
	}
	if ($repaygold > 0 && $repaygems > 0) {
		$amtstr = sprintf_translate($amtstr, $repaygold, $repaygems);
	} elseif ($repaygold > 0) {
		$amtstr = sprintf_translate($amtstr, $repaygold);
	} else {
		$amtstr = sprintf_translate($amtstr, $repaygems);
	}

	tlschema($schemas['mountsold']);
	// No bundled module sets 'newname'; PHP 8 made the missing key fatal after the sale was logged.
	output($texts['mountsold'],
			(($playermount['newname'] ?? '')?
			   $playermount['newname']:$playermount['mountname']),
			$amtstr);
	tlschema();
}

if ($confirm == 0) {
	if ($session['user']['hashorse']>0){
		addnav(array("%s", color_sanitize($name)));
		tlschema($schemas['offer']);
		output($texts['offer'], $repaygold, $repaygems, $lcname);
		tlschema();
		addnav(array("Sell %s`0", $lcname),"stables.php?op=sellmount");
		if (getsetting("allowfeed", 0) && $session['user']['fedmount']==0) {
			addnav(array("Feed %s`0 (`^%s`0 gold)", $lcname, $grubprice),
					"stables.php?op=feed");
		}
	}

	$result = db_query("SELECT mountname,mountid,mountcategory,mountdkcost FROM " . db_prefix("mounts") .  " WHERE mountactive=1 AND mountlocation IN ('all',?) ORDER BY mountcategory,mountcostgems,mountcostgold", true, [(string)$session['user']['location']]);
	$category="";
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		if ($category!=$row['mountcategory']){
			addnav(array("%s", $row['mountcategory']));
			$category = $row['mountcategory'];
		}
		if ($row['mountdkcost'] <= $session['user']['dragonkills'])
			addnav(array("Examine %s`0", $row['mountname']),"stables.php?op=examine&id={$row['mountid']}");
	}
}

page_footer();
?>