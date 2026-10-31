<?php
require_once __DIR__ . '/src/Security/ScalarState.php';
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// addnews ready
// mail ready
// translator ready

// hilarious copy of mounts.php
require_once("common.php");
require_once("lib/http.php");
require_once("lib/showform.php");

check_su_access(SU_EDIT_MOUNTS);

// Companion object preferences are dispatched before the editor, as for mounts and creatures: an installed module,
// its declared preferences and typed values (lib/typed_editor.php). 1.1.2 wrote every posted key into the module named
// in the URL.
if (($_GET['subop'] ?? '') === 'module') {
    require_once 'lib/typed_editor.php';
    try {
        $editorOp=\Resurrection\Http\Input::choice($_GET,'op',['edit','save'],'edit');
        $editorId=\Resurrection\Http\Input::integer($_GET,'id',0,1);
        $editorModule=\Resurrection\Http\Input::string($_GET,'module');
        if (isset($_GET['objtype']) || isset($_GET['objid'])) throw new InvalidArgumentException();
        page_header('Companion Preferences');
        resurrection_object_editor('companions',$editorModule,$editorId,$editorOp==='save');
        page_footer();
    } catch (InvalidArgumentException|DomainException $error) { http_response_code(400); exit('Invalid or stale object preferences.'); }
    catch (Throwable $error) { http_response_code(500); exit('Preferences were not saved.'); }
    exit;
}
require_once("lib/mount_companion_editor.php");

tlschema("companions");

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'add', 'edit', 'save', 'del', 'take', 'activate', 'deactivate'], '');
	// The list and the add form need no companion; a save without one adds (1.1.2's add form posts id=).
	$id = resurrection_content_id($_GET, 'id', in_array($op, ['', 'add', 'save'], true));
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the companion editor'); }

page_header("Companion Editor");

require_once("lib/superusernav.php");
superusernav();

addnav("Companion Editor");
addnav("Add a companion","companions.php?op=add");

// Every change is its protected POST form's (lib/mount_companion_editor.php); GET only displays.
if ($op=="deactivate"){
	// 1.1.2 dropped the cache entry "companionsdata-$id" here, a typo for the edit page's "companiondata-$id".
	resurrection_object_set_active('companions', 'companionid', 'companion', 'companionactive', $id, 0, 'the companion editor');
	$op="";
	httpset("op", "");
} elseif ($op=="activate"){
	resurrection_object_set_active('companions', 'companionid', 'companion', 'companionactive', $id, 1, 'the companion editor');
	$op="";
	httpset("op", "");
} elseif ($op=="del") {
	//drop the companion.
	resurrection_companion_delete($id);
	$op = "";
	httpset("op", "");
} elseif ($op=="take"){
	$taken = resurrection_companion_take($id);
	output("`\$Succesfully taken `^%s`\$ as companion.", $taken);
	$op = "";
	httpset("op", "");
} elseif ($op=="save"){
	$editing = $id > 0;
	[$id, $affected] = resurrection_companion_save($id);
	if ($affected>0){
		output("`^Companion saved!`0`n`n");
	}else{
		// 1.1.2 printed the SQL here.
		output("`^Companion `\$not`^ saved!`0`n`n");
	}
	if ($editing) {
		$op="edit";
	} else {
		$op = "";
	}
	httpset("op", $op);
}

if ($op==""){
	$sql = "SELECT * FROM " . db_prefix("companions") . " ORDER BY category, name";
	$result = db_query($sql);

	$ops = translate_inline("Ops");
	$name = translate_inline("Name");
	$cost = translate_inline("Cost");

	$edit = translate_inline("Edit");
	$del = translate_inline("Del");
	$take = translate_inline("Take");
	$deac = translate_inline("Deactivate");
	$act = translate_inline("Activate");

	rawoutput("<table border=0 cellpadding=2 cellspacing=1".resurrection_presentation("grid").">");
	rawoutput("<tr class='trhead'><td nowrap>$ops</td><td>$name</td><td>$cost</td></tr>");
	$cat = "";
	$count=0;

	// 1.1.2 also counted into $companions here, copied from the mount list's owner count: that is the acting
	// superuser's own companion list, which the page then shows and saves (PHP 8 fails on it), and the delete
	// confirmation it fed was never shown.
	while ($row=db_fetch_assoc($result)) {
		$cid = (int)$row['companionid'];
		if ($cat!=$row['category']){
			rawoutput("<tr class='trlight'><td colspan='5'>");
			output("Category: %s", $row['category']);
			rawoutput("</td></tr>");
			$cat = $row['category'];
			$count=0;
		}
		// The 1.1.2 links, as protected forms bound to the companion as listed.
		rawoutput("<tr class='".($count%2?"trlight":"trdark")."'>");
		rawoutput("<td nowrap>[ <a href='companions.php?op=edit&id=$cid'>$edit</a> |");
		addnav("","companions.php?op=edit&id=$cid");
		if ($row['companionactive']){
			rawoutput("$del |");
		}else{
			rawoutput(resurrection_content_button_form("companions.php?op=del&id=$cid", "companion-del-$cid", resurrection_object_action_context('del', $row), $del) . " |");
		}
		if ($row['companionactive']) {
			rawoutput(resurrection_content_button_form("companions.php?op=deactivate&id=$cid", "companion-deactivate-$cid", resurrection_object_action_context('deactivate', $row), $deac) . " | ");
		}else{
			rawoutput(resurrection_content_button_form("companions.php?op=activate&id=$cid", "companion-activate-$cid", resurrection_object_action_context('activate', $row), $act) . " | ");
		}
		rawoutput(resurrection_content_button_form("companions.php?op=take&id=$cid", "companion-take-$cid", resurrection_object_action_context('take', $row, resurrection_companion_take_state()), $take) . " ]</td>");
		rawoutput("<td>");
		output_notl("`&%s`0", $row['name']);
		rawoutput("</td><td>");
		output("`%%s gems`0, `^%s gold`0",$row['companioncostgems'], $row['companioncostgold']);
		rawoutput("</td></tr>");
		$count++;
	}
	rawoutput("</table>");
	output("`nIf you wish to delete a companion, you have to deactivate it first.");
}elseif ($op=="add"){
	output("Add a companion:`n");
	addnav("Companion Editor Home","companions.php");
	companionform(array(), resurrection_content_context('add', []));
}elseif ($op=="edit"){
	addnav("Companion Editor Home","companions.php");
	$sql = "SELECT * FROM " . db_prefix("companions") . " WHERE companionid=?";
	$result = db_query_cached($sql, "companiondata-$id", 3600, [$id]);
	if (db_num_rows($result)<=0){
		output("`iThis companion was not found.`i");
	}else{
		addnav("Companion properties", "companions.php?op=edit&id=$id");
		// Object preferences (subop=module) are the shared typed editor's, dispatched above.
		module_editor_navs("prefs-companions", "companions.php?op=edit&subop=module&id=$id&module=");
		output("Companion Editor:`n");
		$row = db_fetch_assoc($result);
		$context = resurrection_content_context('edit', $row);
		// Stored abilities the abilities schema refuses are shown empty for repair; PHP 8 refused to turn their false into
		// the form's array.
		try { $row['abilities'] = \Resurrection\Game\CompanionState::abilities(\Resurrection\Security\ScalarState::read($row['abilities'])); }
		catch (DomainException $error) { $row['abilities'] = array(); }
		companionform($row, $context);
	}
}

function companionform($companion, $context){
	// Let's sanitize the data
	// 1.1.2 posted an empty flag for a new companion, which non-strict MySQL stored as 0 and strict SQL refuses.
	if (!isset($companion['companionactive'])) $companion['companionactive'] = 0;
	if (!isset($companion['name'])) $companion['name'] = "";
	if (!isset($companion['companionid'])) $companion['companionid'] = "";
	if (!isset($companion['description'])) $companion['description'] = "";
	if (!isset($companion['dyingtext'])) $companion['dyingtext'] = "";
	if (!isset($companion['jointext'])) $companion['jointext'] = "";
	if (!isset($companion['category'])) $companion['category'] = "";
	if (!isset($companion['companionlocation'])) $companion['companionlocation']  = 'all';
	if (!isset($companion['companioncostdks'])) $companion['companioncostdks']  = 0;

	if (!isset($companion['companioncostgems'])) $companion['companioncostgems']  = 0;
	if (!isset($companion['companioncostgold'])) $companion['companioncostgold']  = 0;

	if (!isset($companion['attack'])) $companion['attack'] = "";
	if (!isset($companion['attackperlevel'])) $companion['attackperlevel'] = "";
	if (!isset($companion['defense'])) $companion['defense'] = "";
	if (!isset($companion['defenseperlevel'])) $companion['defenseperlevel'] = "";
	if (!isset($companion['hitpoints'])) $companion['hitpoints'] = "";
	if (!isset($companion['maxhitpoints'])) $companion['maxhitpoints'] = "";
	if (!isset($companion['maxhitpointsperlevel'])) $companion['maxhitpointsperlevel'] = "";

	if (!isset($companion['abilities']['fight'])) $companion['abilities']['fight'] = 0;
	if (!isset($companion['abilities']['defend'])) $companion['abilities']['defend'] =  0;
	if (!isset($companion['abilities']['heal'])) $companion['abilities']['heal'] =  0;
	if (!isset($companion['abilities']['magic'])) $companion['abilities']['magic'] =  0;

	if (!isset($companion['cannotdie'])) $companion['cannotdie'] = 0;
	if (!isset($companion['cannotbehealed'])) $companion['cannotbehealed'] = 1;
	if (!isset($companion['allowinshades'])) $companion['allowinshades'] = 0;
	if (!isset($companion['allowinpvp'])) $companion['allowinpvp'] = 0;
	if (!isset($companion['allowintrain'])) $companion['allowintrain'] = 0;

	rawoutput(resurrection_content_form("companions.php?op=save&id={$companion['companionid']}", "companion-edit-".(int)$companion['companionid'], $context));
	rawoutput("<input type='hidden' name='companion[companionactive]' value=\"".(int)$companion['companionactive']."\">");
	rawoutput("<table width='100%'>");
	rawoutput("<tr><td nowrap>");
	output("Companion Name:");
	rawoutput("</td><td><input name='companion[name]' value=\"".htmlentities($companion['name'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" maxlength='50'></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Dyingtext:");
	rawoutput("</td><td><input name='companion[dyingtext]' value=\"".htmlentities($companion['dyingtext'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Description:");
	rawoutput("</td><td><textarea cols='25' rows='5' name='companion[description]'>".htmlentities($companion['description'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion join text:");
	rawoutput("</td><td><textarea cols='25' rows='5' name='companion[jointext]'>".htmlentities($companion['jointext'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Category:");
	rawoutput("</td><td><input name='companion[category]' value=\"".htmlentities($companion['category'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" maxlength='50'></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Availability:");
	rawoutput("</td><td nowrap>");
	// Run a modulehook to find out where camps are located.  By default
	// they are located in 'Degolburg' (ie, getgamesetting('villagename'));
	// Some later module can remove them however.
	$locs = resurrection_object_locations("camplocs");
	rawoutput("<select name='companion[companionlocation]'>");
	foreach($locs as $loc=>$name) {
		rawoutput("<option value='".htmlspecialchars((string)$loc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."'".($companion['companionlocation']==$loc?" selected":"").">".htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</option>");
	}
	rawoutput("</select></td></tr>");

	rawoutput("<tr><td nowrap>");
	output("Maxhitpoints / Bonus per level:");
	rawoutput("</td><td><input name='companion[maxhitpoints]' value=\"".htmlentities($companion['maxhitpoints'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"> / <input name='companion[maxhitpointsperlevel]' value=\"".htmlentities($companion['maxhitpointsperlevel'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Attack / Bonus per level:");
	rawoutput("</td><td><input name='companion[attack]' value=\"".htmlentities($companion['attack'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"> / <input name='companion[attackperlevel]' value=\"".htmlentities($companion['attackperlevel'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Defense / Bonus per level:");
	rawoutput("</td><td><input name='companion[defense]' value=\"".htmlentities($companion['defense'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"> / <input name='companion[defenseperlevel]' value=\"".htmlentities($companion['defenseperlevel'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");

	rawoutput("<tr><td nowrap>");
	output("Fighter?:");
	rawoutput("</td><td><input id='fighter' type='checkbox' name='companion[abilities][fight]' value='1'".($companion['abilities']['fight']==true?" checked":"")." onClick='document.getElementById(\"defender\").disabled=document.getElementById(\"fighter\").checked; if(document.getElementById(\"defender\").disabled==true) document.getElementById(\"defender\").checked=false;'></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Defender?:");
	rawoutput("</td><td><input id='defender' type='checkbox' name='companion[abilities][defend]' value='1'".($companion['abilities']['defend']==true?" checked":"")." onClick='document.getElementById(\"fighter\").disabled=document.getElementById(\"defender\").checked; if(document.getElementById(\"fighter\").disabled==true) document.getElementById(\"fighter\").checked=false;'></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Healer level:");
	rawoutput("</td><td valign='top'><select name='companion[abilities][heal]'>");
	for($i=0;$i<=30;$i++) {
		rawoutput("<option value='$i'".($companion['abilities']['heal']==$i?" selected":"").">$i</option>");
	}
	rawoutput("</select></td></tr>");
	rawoutput("<tr><td colspan='2'>");
	output("`iThis value determines the maximum amount of HP healed per round`i");
	rawoutput("</td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Magician?:");
	rawoutput("</td><td valign='top'><select name='companion[abilities][magic]'>");
	for($i=0;$i<=30;$i++) {
		rawoutput("<option value='$i'".($companion['abilities']['magic']==$i?" selected":"").">$i</option>");
	}
	rawoutput("</select></td></tr>");
	rawoutput("<tr><td colspan='2'>");
	output("`iThis value determines the maximum amount of damage caused per round`i");
	rawoutput("</td></tr>");

	rawoutput("<tr><td nowrap>");
	output("Companion cannot die:");
	rawoutput("</td><td><input type='checkbox' name='companion[cannotdie]' value='1'".($companion['cannotdie']==true?" checked":"")."></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion cannot be healed:");
	rawoutput("</td><td><input type='checkbox' name='companion[cannotbehealed]' value='1'".($companion['cannotbehealed']==true?" checked":"")."></td></tr>");
	rawoutput("<tr><td nowrap>");

	output("Companion Cost (DKs):");
	rawoutput("</td><td><input name='companion[companioncostdks]' value=\"".htmlentities((int)$companion['companioncostdks'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Cost (Gems):");
	rawoutput("</td><td><input name='companion[companioncostgems]' value=\"".htmlentities((int)$companion['companioncostgems'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Companion Cost (Gold):");
	rawoutput("</td><td><input name='companion[companioncostgold]' value=\"".htmlentities((int)$companion['companioncostgold'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Allow in shades:");
	rawoutput("</td><td><input type='checkbox' name='companion[allowinshades]' value='1'".($companion['allowinshades']==true?" checked":"")."></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Allow in PvP:");
	rawoutput("</td><td><input type='checkbox' name='companion[allowinpvp]' value='1'".($companion['allowinpvp']==true?" checked":"")."></td></tr>");
	rawoutput("<tr><td nowrap>");
	output("Allow in train:");
	rawoutput("</td><td><input type='checkbox' name='companion[allowintrain]' value='1'".($companion['allowintrain']==true?" checked":"")."></td></tr>");
	rawoutput("</table>");
	$save = translate_inline("Save");
	rawoutput("<input type='submit' class='button' value='$save'></form>");
}

page_footer();
?>
