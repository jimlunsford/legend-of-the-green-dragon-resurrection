<?php
require_once __DIR__ . '/src/Security/ScalarState.php';
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/villagenav.php");
require_once("lib/service_authority.php");

tlschema("mercenarycamp");

// The op, the companion id and the companion key are checked before anything runs. 1.1.2 decoded the key twice and
// stripped slashes, so a name with % or \ could not be healed; the link now carries it encoded once.
try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'heal', 'buy'], '');
	$id = $op == 'buy' ? \Resurrection\Http\Input::integer($_GET, 'id', 0, 1) : 0;
	if ($op == 'buy' && $id < 1) throw new InvalidArgumentException('Missing companion.');
	$name = \Resurrection\Http\Input::string($_GET, 'name');
} catch (InvalidArgumentException $error) { resurrection_service_failure($error, 'the camp'); }

checkday();
if (isset($companions[$name])) {
	$displayname = $companions[$name]['name'];
} else {
	$displayname = translate_inline("your companion");
}

$basetext=array(
	"title"			=>	"A Mercenary Camp",
	"desc"			=>	array(
		"`n`QYou step out of the gates of the village and stand for a moment to take a look around.",
		"A slight breeze in the air stirs the pennants mounted above your head before it touches your skin.",
		"Sounds of dogs barking draws your attention to the makeshift camp which is set slightly apart from the village.",
		"You walk towards the encampment trying to avoid muddy puddles left from the rainfall the prior night.",
		"The odor of cooking fires permeates the air.`n`n",

		"As you approach you notice two men seated on rough hewn logs in front of a tent.",
		"Propped against one of the logs are a pair of long handled battle axes and a bastard sword.",
		"One of the men turns his weatherbeaten face towards you.",
		"You try to suppress a shudder as you recoil from the sight of his face.",
		"A ragged scar marks his face from forehead to jaw, crossing an empty hole where his eye should be.",
		"He spits into the campfire before him.`n`n",

		"\"`4Are you looking for someone?`Q\", he asks in a gravelly voice that comes from deep within.`n`n",

		"At that moment a slender elfin woman with her golden hair pulled back in a warrior's braid brushes past you.",
		"Strapped across her back is a long bow and a leather quiver full of arrows fletched with turkey feathers.",
		"She gives you a smirk as she passes.".

		"You turn as the elfin archer continues on her way.",
		"That is when you notice a large mangy dog in a tug-of-war with a troll.",
		"Clenched in the dog's teeth is a very large bone with bits of flesh still clinging to it.",
		"You can't tell if the troll is growling louder than the dog as it tries to wrest the bone from its jaw.",
		"Hanging from the troll's wide belt a gnarled club hangs against filthy breeches of animal skins.",

		"The sound of the man's voice brings your attention back to the matter at hand.`n`n",
		"\"`PYes. As a matter of fact I am looking for someone.`Q\"  you reply.",
		"\"`PI have gold in my purse to pay for the best fighter willing to join me in ridding this realm of vermin.`Q\"`n`n",
	),
	"buynav"		=> "Hire a mercenary",
	"healnav"		=> "Heal a companion",
	"healtext"        => array(
		"`QA surgeon takes a careful look at the many wounds of your companion.",
		"After murmuring to himself as he makes the evaluation, he turns to you to name the price to care for the wounds.",
	),
	"healnotenough" => array(
		"`QThe surgeon shakes his head then shrugs before turning away.",
		"You are left standing with your empty purse.",
		"No healing for someone who cannot pay.",
	),
	"healpaid" => array(
		array("`QA surgeon is caring for the wounds of %s`Q and bandages them with learned skill.", $displayname),
		"You gladly hand him the money owed for healing your companion and start heading back to the village.",
    ),
    "toomanycompanions"=> array(
    	"It seems no one is willing to follow you.",
    	"You simply lead too many companions at the moment."
    ),
    "manycompanions" => "Several mercenaries offer to join you:`n`n",
    "onecompanion" => "One mercenary offers to join you:`n`n",
    "nocompanions" => "No mercenaries off to join you.",
);

$schemas = array(
	"title"=>"mercenarycamp",
	"desc"=>"mercenarycamp",
	"buynav"=>"mercenarycamp",
	"healnav"=>"mercenarycamp",
	"healtext"=>"mercenarycamp",
	"healnotenough"=>"mercenarycamp",
	"healpaid"=>"mercenarycamp",
	"toomanycompanions"=>"mercenarycamp",
	"manycompanions"=>"mercenarycamp",
	"onecompanion"=>"mercenarycamp",
	"nocompanions"=>"mercenarycamp",
);

$basetext['schemas'] = $schemas;
$texts = modulehook("mercenarycamptext",$basetext);
$schemas = $texts['schemas'];

tlschema($schemas['title']);
page_header($texts['title']);
tlschema();

// Only the protected POST of the confirming form hires or heals; a GET (a link, a restored page, New Day's
// "Continue") asks, with the price the server works out. The URL's cost is never read.
$posted = resurrection_service_posted();

if ($op==""){
  	if (httpget('skip') != 1) {
		tlschema($schemas['desc']);
	  	if (is_array($texts['desc'])) {
	  		foreach ($texts['desc'] as $description) {
	  			output_notl(sprintf_translate($description));
	  		}
	  	} else {
	  		output($texts['desc']);
	  	}
	  	tlschema();
  	}

	// Bound: 1.1.2 pasted the session location and dragon kills into this query.
	$result = db_query("SELECT * FROM " . db_prefix("companions") . "
				WHERE companioncostdks<=?
				AND (companionlocation = ? OR companionlocation = 'all')
				AND companionactive = 1", true, [(int)$session['user']['dragonkills'], (string)$session['user']['location']]);
  	tlschema($schemas['buynav']);
	addnav($texts['buynav']);
	tlschema();
	switch (db_num_rows($result)) {
		case 0:
			if (is_array($texts['nocompanions'])) {
				foreach ($texts['nocompanions'] as $description) {
					output_notl(sprintf_translate($description));
				}
			} else {
				output($texts['nocompanions']);
			}
			break;
		case 1:
			if (is_array($texts['onecompanion'])) {
				foreach ($texts['onecompanion'] as $description) {
					output_notl(sprintf_translate($description));
				}
			} else {
				output($texts['onecompanion']);
			}
			break;
		default:
			if (is_array($texts['manycompanions'])) {
				foreach ($texts['manycompanions'] as $description) {
					output_notl(sprintf_translate($description));
				}
			} else {
				output($texts['manycompanions']);
			}
			break;
	}
	while ($row = db_fetch_assoc($result)) {
		$row = modulehook("alter-companion", $row);
		if ($row['companioncostgold'] && $row['companioncostgems']) {
			if ($session['user']['gold'] >= $row['companioncostgold'] && $session['user']['gems'] >= $row['companioncostgems'] && !isset($companions[$row['name']])) {
				addnav(array("%s`n`^%s Gold, `%%%s Gems`0",$row['name'], $row['companioncostgold'], $row['companioncostgems']), "mercenarycamp.php?op=buy&id={$row['companionid']}");
			} else {
				addnav(array("%s`n`^%s Gold, `%%%s Gems`0",$row['name'], $row['companioncostgold'], $row['companioncostgems']), "");
			}
		} else if ($row['companioncostgold']) {
			if ($session['user']['gold'] >= $row['companioncostgold'] && !isset($companions[$row['name']])) {
				addnav(array("%s`n`^%s Gold`0",$row['name'], $row['companioncostgold']), "mercenarycamp.php?op=buy&id={$row['companionid']}");
			} else {
				addnav(array("%s`n`^%s Gold`0",$row['name'], $row['companioncostgold']), "");
			}
		} else if ($row['companioncostgems']) {
			if ($session['user']['gems'] >= $row['companioncostgems'] && !isset($companions[$row['name']])) {
				addnav(array("%s`n`%%%s Gems`0",$row['name'], $row['companioncostgems']), "mercenarycamp.php?op=buy&id={$row['companionid']}");
			} else {
				addnav(array("%s`n`%%%s Gems`0",$row['name'], $row['companioncostgems']), "");
			}
		} else if (!isset($companions[$row['name']])) {
			addnav(array("%s",$row['name']), "mercenarycamp.php?op=buy&id={$row['companionid']}");
		}
		output("`#%s`n`7%s`n`n",$row['name'], $row['description']);
	}
	healnav($companions, $texts, $schemas);
} else if ($op == "heal") {
	try {
		$name = resurrection_companion_key();
		$cost = resurrection_companion_heal_cost($companions[$name]);
		if ($cost === null) throw new DomainException('Nothing to heal.');
		$paid = null;
		if ($posted) {
			resurrection_service_fields();
			resurrection_consume_action('mercenary-heal', resurrection_service_context('mercenary-heal', $name, [$cost]));
			$paid = resurrection_player_mutation(fn () => resurrection_companion_heal($name, true));
		}
	} catch (Throwable $error) { resurrection_service_failure($error, 'the camp'); }
	if ($posted ? $paid === null : $session['user']['gold'] < $cost) {
		tlschema($schemas['healpaid']);
	  	if (is_array($texts['healnotenough'])) {
	  		foreach ($texts['healnotenough'] as $healnotenough) {
	  			output_notl(sprintf_translate($healnotenough));
	  		}
	  	} else {
	  		output($texts['healnotenough']);
	  	}
		tlschema();
	} elseif (!$posted) {
		// 1.1.2 healed on this GET; the healing is now this form's protected POST.
		resurrection_service_form("mercenarycamp.php?op=heal&name=" . rawurlencode($name), 'mercenary-heal',
			resurrection_service_context('mercenary-heal', $name, [$cost]), array("%s`0 (`^%s Gold`0)", $companions[$name]['name'], $cost));
	} else {
		tlschema($schemas['healpaid']);
	  	if (is_array($texts['healpaid'])) {
	  		foreach ($texts['healpaid'] as $healpaid) {
	  			output_notl(sprintf_translate($healpaid));
	  		}
	  	} else {
	  		output($texts['healpaid']);
	  	}
		tlschema();
	}
	healnav($companions, $texts, $schemas);
	addnav("Navigation");
	addnav("Return to the camp", "mercenarycamp.php?skip=1");
} else if ($op == "buy") {
	if (!$posted) {
		// 1.1.2 hired on this GET; the hire is now this form's protected POST. The offer is the listing's.
		$row = resurrection_mercenary_offered($id);
		if ($row) {
			$row = modulehook("alter-companion", $row);
			output("`#%s`n`7%s`n`n",$row['name'], $row['description']);
			if (resurrection_mercenary_affordable($row)) {
				tlschema($schemas['buynav']);
				resurrection_service_form("mercenarycamp.php?op=buy&id=$id", "mercenary-hire-$id",
					resurrection_mercenary_context($id, $row), translate_inline($texts['buynav']));
				tlschema();
			}
		}
	} else {
		try {
			resurrection_service_fields();
			// The form is bound to the price it showed; a companion repriced or withdrawn since is refused.
			$row = resurrection_mercenary_offered($id);
			if ($row === null) throw new DomainException('Companion not offered.');
			$row = modulehook("alter-companion", $row);
			resurrection_consume_action("mercenary-hire-$id", resurrection_mercenary_context($id, $row));
			$price = [(int)$row['companioncostgold'], (int)$row['companioncostgems']];
			$hire = resurrection_player_mutation(fn () => resurrection_mercenary_hire($id, $price));
		} catch (Throwable $error) { resurrection_service_failure($error, 'the camp'); }
		$row = $hire['row'];
		if ($hire['outcome'] == 'hired') {
			output("`QYou hand over `^%s gold`Q and `%%s %s`Q.`n`n", (int)$row['companioncostgold'], (int)$row['companioncostgems'],translate_inline($row['companioncostgems'] == 1?"gem":"gems"));
			if (isset($row['jointext']) && $row['jointext'] > "") {
				output($row['jointext']);
			}
		} else {
			// applying the companion failed. Most likely they already have more than enough companions...
			tlschema($schemas['toomanycompanions']);
			if (is_array($texts['toomanycompanions'])) {
				foreach ($texts['toomanycompanions'] as $toomanycompanions) {
					output_notl(sprintf_translate($toomanycompanions));
				}
			} else {
				output($texts['toomanycompanions']);
			}
			tlschema();
		}
	}
	addnav("Navigation");
	addnav("Return to the camp", "mercenarycamp.php?skip=1");
}
addnav("Navigation");
villagenav();
page_footer();


function healnav($companions, $texts, $schemas) {
	global $session;
	tlschema($schemas['healnav']);
	addnav($texts['healnav']);
	tlschema();
	$healable = false;
	foreach ($companions as $name => $companion) {
		if (isset($companion['cannotbehealed']) && $companion['cannotbehealed'] == true) {
		} else {
			$pointstoheal = $companion['maxhitpoints'] - $companion['hitpoints'];
			if ($pointstoheal > 0) {
				$healable = true;
				$costtoheal = round(log($session['user']['level']+1) * ($pointstoheal + 10)*1.33);
				// The link no longer carries the price; the heal page works it out again.
				if ($session['user']['gold'] >= $costtoheal) {
					addnav(array("%s`0 (`^%s Gold`0)", $companion['name'], $costtoheal), "mercenarycamp.php?op=heal&name=".rawurlencode($name));
				} else {
					addnav(array("%s`0 (`\$Not enough gold`0)", $companion['name']), "mercenarycamp.php?op=heal&name=".rawurlencode($name));
				}
			}
		}
	}
	if ($healable == true) {
	  	tlschema($schemas['healtext']);
	  	if (is_array($texts['healtext'])) {
	  		foreach ($texts['healtext'] as $healtext) {
	  			output_notl(sprintf_translate($healtext));
	  		}
	  	} else {
	  		output($texts['healtext']);
	  	}
		tlschema();
	}
}
?>