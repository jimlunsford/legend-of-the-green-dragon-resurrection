<?php
require_once __DIR__ . '/../../src/Compatibility/array_cursor.php';
function drinks_gettexts() {
	global $session;
	$iname = getsetting("innname", LOCATION_INN);
	$drinktext = array(
			"title"=>"$iname",
			"barkeep"=>getsetting("barkeep", "`tCedrik"),
			"return"=>"",
			"demand"=>"Pounding your fist on the bar, you demand another drink",
			"toodrunk"=>" but {barkeep} continues to clean the glass he was working on.  \"`%You've had enough ".($session['user']['sex']?"lass":"lad").",`0\" he declares.",
			"toomany"=>"{barkeep} eyes you critically. \"`%Ya've had enough of the hard stuff, my friend.  No more of that for you today.`0\"",
			"drinksubs"=>array(),
		);
	$schemas = array(
		'title'=>"module-drinks",
		'barkeep'=>"module-drinks",
		'return'=>"module-drinks",
		'demand'=>"module-drinks",
		'toodrunk'=>"module-drinks",
		'toomany'=>"module-drinks",
		'drinksubs'=>"module-drinks",
		);
	$drinktext['schemas'] = $schemas;
	return $drinktext;
}


// Support functions
function drinks_editor(){
    global $mostrecentmodule, $session;
    require_once 'lib/player_mutation.php';
    require_once 'src/Http/DrinkInput.php';
    if (httpget('subop') !== '') { http_response_code(400); exit('No supported drink object preferences.'); }
    if (empty($session['loggedin'])) { http_response_code(403); exit('Not authorized.'); }
    if (!get_module_pref('canedit','drinks')) check_su_access(SU_EDIT_USERS);
    try {
        $editorOp=\Resurrection\Http\Input::choice($_GET,'op',['','add','edit','save','del','activate','deactivate'],'');
        $editorId=\Resurrection\Http\Input::integer($editorOp === 'save' ? $_POST : $_GET,'drinkid',0,0);
        if ($editorId > 32767 || ($editorOp !== 'save' && in_array($editorOp,['edit','del','activate','deactivate'],true) && $editorId < 1)) throw new InvalidArgumentException();
    } catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid drink editor input.'); }
    if (in_array($editorOp,['save','del','activate','deactivate'],true)) resurrection_consume_action('drinks-editor-'.$editorOp.($editorOp==='save'?'':'-'.$editorId),(string)$editorId);

	page_header("Drink Editor");
	require_once("lib/superusernav.php");
	superusernav();
	addnav("Drink Editor");
	addnav("Add a drink","runmodule.php?module=drinks&act=editor&op=add&admin=true");
	$op = httpget('op');
	$drinkid = $editorId;
	$header = "";
	if ($op != "") {
		addnav("Drink Editor Main","runmodule.php?module=drinks&act=editor&admin=true");
		if ($op == 'add') {
			$header = translate_inline("Adding a new drink");
		} else if ($op == 'edit') {
			$header = translate_inline("Editing a drink");
		}
	} else {
		$header = translate_inline("Current drinks");
	}
	output_notl("`&<h3>$header`0</h3>", true);
	$drinksarray=array(
		"Drink,title",
		"drinkid"=>"Drink ID,hidden",
		"name"=>"Drink Name",
		"costperlevel"=>"Cost per level,int",
		"hpchance"=>"Chance of modifying HP (see below),range,0,10,1",
		"turnchance"=>"Chance of modifying turns (see below),range,0,10,1",
		"alwayshp"=>"Always modify hitpoints,bool",
		"alwaysturn"=>"Always modify turns,bool",
		"drunkeness"=>"Drunkeness,range,1,100,1",
		"harddrink"=>"Is drink hard alchohol?,bool",
		"hpmin"=>"Min HP to add (see below),range,-20,20,1",
		"hpmax"=>"Max HP to add (see below),range,-20,20,1",
		"hppercent"=>"Modify HP by some percent (see below),range,-25,25,5",
		"turnmin"=>"Min turns to add (see below),range,-5,5,1",
		"turnmax"=>"Max turns to add (see below),range,-5,5,1",
		"remarks"=>"Remarks",
		"buffname"=>"Name of the buff",
		"buffrounds"=>"Rounds buff lasts,range,1,20,1",
		"buffroundmsg"=>"Message each round of buff",
		"buffwearoff"=>"Message when buff wears off",
		"buffatkmod"=>"Attack modifier of buff",
		"buffdefmod"=>"Defense modifier of buff",
		"buffdmgmod"=>"Damage modifier of buff",
		"buffdmgshield"=>"Damage shield modifier of buff",
		"buffeffectfailmsg"=>"Effect failure message (see below)",
		"buffeffectnodmgmsg"=>"No damage message (see below)",
		"buffeffectmsg"=>"Effect message (see below)",
	);
	if($op=="del"){
		resurrection_player_mutation(function () use ($drinkid) {
            db_query('DELETE FROM ' . db_prefix('module_objprefs') . ' WHERE objtype=? AND objid=?',true,['drinks',$drinkid]);
            db_query('DELETE FROM ' . db_prefix('drinks') . ' WHERE drinkid=?',true,[$drinkid]);
        });
		$op = "";
		httpset('op', "");
	}
	if($op=="save"){
		$subop = httpget("subop");
		if ($subop=="") {
            try { $values=\Resurrection\Http\DrinkInput::parse($_POST); }
            catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid drink fields.'); }
            resurrection_player_mutation(function () use (&$drinkid,$values) {
                if ($drinkid>0) {
                    $exists=db_query('SELECT drinkid FROM ' . db_prefix('drinks') . ' WHERE drinkid=? FOR UPDATE',true,[$drinkid]);
                    if (!$exists) throw new DomainException('Drink no longer exists.');
                    $sets=array_map(static fn($key)=>db_identifier($key).'=?',array_keys($values));
                    db_query('UPDATE ' . db_prefix('drinks') . ' SET '.implode(',',$sets).' WHERE drinkid=?',true,[...array_values($values),$drinkid]);
                } else {
                    db_query('INSERT INTO ' . db_prefix('drinks') . ' ('.implode(',',array_map('db_identifier',array_keys($values))).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',true,array_values($values));
                    $drinkid=db_insert_id();
                }
            });
            output('`^Drink saved!');
        } else {
            // No bundled module declares prefs-drinks. Unknown namespaces are not writable.
            http_response_code(400); exit('No supported drink object preferences.');
        }
		if ($drinkid) {
			$op = "edit";
			httpset("drinkid", $drinkid, true);
		} else {
			$op = "";
		}
		httpset('op', $op);
	}
	if ($op == "activate") {
		db_query('UPDATE ' . db_prefix('drinks') . ' SET active=? WHERE drinkid=?',true,[1,$drinkid]);
		$op = "";
		httpset('op', "");
	}
	if ($op == "deactivate") {
		db_query('UPDATE ' . db_prefix('drinks') . ' SET active=? WHERE drinkid=?',true,[0,$drinkid]);
		$op = "";
		httpset('op', "");
	}
	if ($op==""){
		$op = translate_inline("Ops");
		$id = translate_inline("Id");
		$nm = translate_inline("Name");
		$dkn = translate_inline("Drunkeness");
		$hard = translate_inline("Hard Alchohol?");
		$edit = translate_inline("Edit");
		$deac = translate_inline("Deactivate");
		$act = translate_inline("Activate");
		$conf = translate_inline("Are you sure you wish to delete this drink?");
		$del = translate_inline("Del");
		rawoutput("<table border=0 cellpadding=2 cellspacing=1 bgcolor='#999999'>");
		rawoutput("<tr class='trhead'>");
		rawoutput("<td>$op</td><td>$id</td><td>$nm</td><td>$dkn</td><td>$hard</td>");
		rawoutput("</tr>");
		$sql = "SELECT drinkid,active,name,drunkeness,harddrink FROM " . db_prefix("drinks") . " ORDER BY drinkid";
		$result= db_query($sql);
		for ($i=0;$i<db_num_rows($result);$i++){
			$row = db_fetch_assoc($result);
			$id = $row['drinkid'];
			rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
			rawoutput("<td nowrap>[ <a href='runmodule.php?module=drinks&act=editor&op=edit&drinkid=$id&admin=true'>$edit</a>");
			addnav("","runmodule.php?module=drinks&act=editor&op=edit&drinkid=$id&admin=true");
			if ($row['active']) {
            drinks_editor_action('deactivate',(int)$id,$deac);
				addnav("","runmodule.php?module=drinks&act=editor&op=deactivate&drinkid=$id&admin=true");
			} else {
            drinks_editor_action('activate',(int)$id,$act);
				addnav("","runmodule.php?module=drinks&act=editor&op=activate&drinkid=$id&admin=true");
			}

            drinks_editor_action('del',(int)$id,$del); rawoutput(' ]</td>');
			addnav("","runmodule.php?module=drinks&act=editor&op=del&drinkid=$id&admin=true");
			output_notl("<td>`^%s</td>`0", $id, true);
			output_notl("<td>`&%s`0</td>", htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8'), true);
			output_notl("<td>`^%s`0</td>", $row['drunkeness'], true);
			$hard = translate_inline("`^No");
			if ($row['harddrink']) $hard = translate_inline("`\$Yes");
			output_notl("<td>%s`0</td>", $hard, true);
			rawoutput("</tr>");
		}
		rawoutput("</table>");
	}
	$subop= httpget("subop");
	if($op=="edit"){
		addnav("Drink properties", "runmodule.php?module=drinks&act=editor&op=edit&drinkid=$drinkid&admin=true");
		module_editor_navs("prefs-drinks", "runmodule.php?module=drinks&act=editor&drinkid=$drinkid&op=edit&subop=module&editmodule=");
		if ($subop=="module") {
			$module = httpget("editmodule");
			$oldmodule = $mostrecentmodule;
			rawoutput("<form action='runmodule.php?module=drinks&act=editor&op=save&subop=module&editmodule=$module&drinkid=$drinkid&admin=true' method='POST'>");
			module_objpref_edit('drinks', $module, $drinkid);
			$mostrecentmodule = $oldmodule;
			rawoutput("</form>");
			addnav("", "runmodule.php?module=drinks&act=editor&op=save&subop=module&editmodule=$module&drinkid=$drinkid&admin=true");
		} elseif ($subop=="") {
				$result = db_query('SELECT * FROM ' . db_prefix('drinks') . ' WHERE drinkid=?',true,[$drinkid]);
				$row = db_fetch_assoc($result);
		}
	}elseif ($op=="add"){
		/* We're adding a new drink, make an empty row */
		$row = array();
		$row['drinkid'] = 0;
	}

	if (($op == "edit" || $op == "add") && $subop=="") {
		rawoutput("<form action='runmodule.php?module=drinks&act=editor&op=save&admin=true' method='POST'>");
        rawoutput(resurrection_action_fields('drinks-editor-save',(string)$row['drinkid']));
		addnav("","runmodule.php?module=drinks&act=editor&op=save&admin=true");
		showform($drinksarray,$row);
		rawoutput("</form>");
		output("`\$NOTE:`7 Make sure that you know what you are doing when modifying or adding drinks.`n");
		output("Just because the drinks have a lot of options, doesn't mean you have to use all of them`n`n");
		output("`2Drink ID: `7This field is used internally and should be unique.`n");
		output("`2Name: `7The name of the drink the user will see.`n");
		output("`2Cost per level: `7This value times the users level is the drink cost.`n");
		output("`2Chance of modifying HP: `7If set, this is the number of chances out of the total of this and the turn chance for HP getting modified.`n");
		output("`2Chance of modifying turns: `7If set, this is the number of chances out of the total of this and the HP chance for turns getting modified.`n");
		output("`2Always modify HP: `7If set, hitpoints will be modified.  Should not be set alongside HP chance above.`n");
		output("`2Always modify turns: `7If set, turns will be modified.  Should not be set alongside turn chance above.`n");
		output("`2Drunkeness: `7How drunk will this make the player.`n");
		output("`2Hard Drink: `7Users are only allowed a certain number of hard drinks per day regardless of drunkeness.`n");
		output("`2Min HP to add: `7If we are modifying hitpoints, and if HP percent isn't set, use this and the HP max value to pick a random amount of HP to add.  Can be negative.`n");
		output("`2Max HP to add: `7If we are modifying hitpoints and if HP percent isn't set, use this and the HP min value to pick a random amount of HP to add.  Can be negative.`n");
		output("`2HP percent: `7If we are modifying hitpoints and if this is set, the users hitpoints are modified by this percentage.  Can be negative.`n");
		output("`2Min turns to add: `7If we are modifying turns, use this and the turn max value to pick a random amount of turns to add.  Can be negative.`n");
		output("`2Max turns to add: `7If we are modifying turns, use this and the turn min value to pick a random amount of turns to add.  Can be negative.`n");
		output("`2Remarks: `7Text displayed to the user when they order the drink.`n");
		output("`2Buff name: `7What is this buff called.`n");
		output("`2Buff rounds: `7How many rounds this buff lasts.`n");
		output("`2Buff round message: `7What message should show as each round occurs.`n");
		output("`2Buff wearoff: `7What message is shown when this buff wears off.`n");
		output("`2Buff attack modifier: `7Multiplier to modify attack points by? 1.0 is no modification, 2.0 doubles their attack points.`n");
		output("`2Buff defense modifier: `7Multiplier to modify defense points by? 1.0 is no modification, 2.0 doubles their defense points.`n");
		output("`2Buff damage modifier: `7Multiplier to modify damage by? 1.0 is no modification, 2.0 doubles their damage points. This is `\$VERY POTENT`7!`n");
		output("`2Buff damage shield modifier: `7When you are hit, deals damage to your opponent based on damage done to you. 1.0 deals identical damage, 2.0 deals double damage back to the opponent.`n");
		output("`2Effect failure message: Message if this buff fails. (Only used with damage shield)`n");
		output("`2Effect no damage message: Message if no damage is done. (Only used with damage shield)`n");
		output("`2Effect message: What shows when this buff has an effect. (Only used with damage shield)`n`n");
	}
	page_footer();
}

function drinks_editor_action(string $op,int $id,string $label): void {
    $url='runmodule.php?module=drinks&act=editor&op='.$op.'&drinkid='.$id.'&admin=true';
    rawoutput('<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.resurrection_action_fields('drinks-editor-'.$op.'-'.$id,(string)$id).'<button class="button">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</button></form>');
}
?>
