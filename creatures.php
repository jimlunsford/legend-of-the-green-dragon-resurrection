<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

check_su_access(SU_EDIT_CREATURES);

// Creature object preferences are dispatched before the editor, as for mounts: an installed module, its declared
// preferences and typed values (lib/typed_editor.php). 1.1.2 wrote every posted key into the requested module.
if (($_GET['subop'] ?? '') === 'module') {
    require_once 'lib/typed_editor.php';
    try {
        $editorOp=\Resurrection\Http\Input::choice($_GET,'op',['edit','save'],'edit');
        $editorId=\Resurrection\Http\Input::integer($_GET,'creatureid',0,1);
        $editorModule=\Resurrection\Http\Input::string($_GET,'module');
        if (isset($_GET['objtype']) || isset($_GET['objid'])) throw new InvalidArgumentException();
        page_header('Creature Preferences');
        resurrection_object_editor('creatures',$editorModule,$editorId,$editorOp==='save');
        page_footer();
    } catch (InvalidArgumentException|DomainException $error) { http_response_code(400); exit('Invalid or stale object preferences.'); }
    catch (Throwable $error) { http_response_code(500); exit('Preferences were not saved.'); }
    exit;
}

tlschema("creatures");

$creaturestats = array();

require_once 'lib/creatures.php';
for ($i=1;$i<18;$i++){
	$creaturestats[$i] = creature_stats($i);
}

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'search', 'add', 'edit', 'save', 'del'], '');
	// 1.1.2's "Creature properties" link on an add form has an empty creatureid: it shows the add form again.
	$id = in_array($op, ['edit', 'del'], true) ? resurrection_content_id($_GET, 'creatureid', $op == 'edit') : 0;
	$level = resurrection_content_level();
	if (!$level) $level = 1;
	$q = $op == 'search' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? resurrection_content_text('q', RESURRECTION_TAUNT_BYTES, true) : '';
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the creature editor'); }

page_header("Creature Editor");

require_once("lib/superusernav.php");
superusernav();

if ($op == "save"){
	[$id, $affected] = resurrection_creature_save($creaturestats);
	if ($affected) {
		output("`^Creature saved!`0`n");
	} else {
		output("`^Creature `\$not`^ saved!`0`n");
	}
	// Drop back into the editor for the saved creature.
	$op = "edit";
}elseif ($op=="del"){
	resurrection_content_delete("creatures", "creatureid", "creatureid", "creature", "the creature editor");
	output("Creature deleted`n`n");
	$op="";
}
if ($op=="" || $op=="search"){
	if ($q > "") {
		// As in 1.1.2, % and _ in the search text match anything.
		$like = "%$q%";
		$rows = db_query("SELECT * FROM " . db_prefix("creatures") . " WHERE creaturename LIKE ? OR creatureweapon LIKE ? OR creaturelose LIKE ? OR createdby LIKE ? ORDER BY creaturelevel,creaturename", true, [$like, $like, $like, $like]);
	} else {
		$rows = db_query("SELECT * FROM " . db_prefix("creatures") . " WHERE creaturelevel=? ORDER BY creaturelevel,creaturename", true, [$level]);
	}
	// Search form
	$search = translate_inline("Search");
	rawoutput("<form action='creatures.php?op=search' method='POST'>");
	output("Search by field: ");
	rawoutput("<input name='q' id='q'>");
	rawoutput("<input type='submit' class='button' value='$search'>");
	rawoutput("</form>");
	rawoutput("<script language='JavaScript'>document.getElementById('q').focus();</script>",true);
	addnav("","creatures.php?op=search");

	addnav("Levels");
	$sql1 = "SELECT count(creatureid) AS n,creaturelevel FROM " . db_prefix("creatures") . " group by creaturelevel order by creaturelevel";
	$result1 = db_query($sql1);
	while ($row = db_fetch_assoc($result1)) {
		addnav(array("Level %s: (%s creatures)", $row['creaturelevel'], $row['n']),
				"creatures.php?level={$row['creaturelevel']}");
	}
	// There is no reason to allow players to add creatures to level 17 and 18.
	// Players aren't supposed to stay at level 15 at all.
	if ($level <= 16) {
		addnav("Edit");
		addnav("Add a creature","creatures.php?op=add&level=$level");
	}
	$opshead = translate_inline("Ops");
	$idhead = translate_inline("ID");
	$name = translate_inline("Name");
	$lev = translate_inline("Level");
	$weapon = translate_inline("Weapon");
	$winmsg = translate_inline("Win");
	$diemsg = translate_inline("Die");
	$author = translate_inline("Author");
	$edit = translate_inline("Edit");
	$confirm = translate_inline("Are you sure you wish to delete this creature?");
	$del = translate_inline("Del");

	rawoutput("<table border=0 cellpadding=2 cellspacing=1".resurrection_presentation("grid").">");
	rawoutput("<tr class='trhead'>");
	rawoutput("<td>$opshead</td><td>$idhead</td><td>$name</td><td>$lev</td><td>$weapon</td><td>$winmsg</td><td>$diemsg</td><td>$author</td></tr>");
	addnav("","creatures.php");
	foreach ($rows as $i=>$row){
		$cid = (int)$row['creatureid'];
		$clevel = (int)$row['creaturelevel'];
		rawoutput("<tr class='".($i%2==0?"trdark":"trlight")."'>", true);
		rawoutput("<td>[ <a href='creatures.php?op=edit&creatureid=$cid'>");
		output_notl("%s", $edit);
		rawoutput("</a> | " . resurrection_content_delete_form("creatures.php?op=del&creatureid=$cid&level=$clevel", "creature-del-$cid", resurrection_content_context('del', $row), $del, $confirm) . " ]</td><td>");
		addnav("","creatures.php?op=edit&creatureid=$cid");
		output_notl("%s", $row['creatureid']);
		rawoutput("</td><td>");
		output_notl("%s", $row['creaturename']);
		rawoutput("</td><td>");
		output_notl("%s", $row['creaturelevel']);
		rawoutput("</td><td>");
		output_notl("%s", $row['creatureweapon']);
		rawoutput("</td><td>");
		output_notl("%s", $row['creaturewin']);
		rawoutput("</td><td>");
		output_notl("%s", $row['creaturelose']);
		rawoutput("</td><td>");
		output_notl("%s", $row['createdby']);
		rawoutput("</td></tr>");
	}
	rawoutput("</table>");
}else{
	require_once("lib/showform.php");
	if ($op=="edit" && $id){
		$row = resurrection_content_row("creatures", "creatureid", $id);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Creature not found.'), 'the creature editor');
		$level = (int)$row['creaturelevel'];
		$context = resurrection_content_context('edit', $row);
		// showform() reads its text fields as strings; the columns are nullable.
		foreach (array_keys(RESURRECTION_CREATURE_TEXT) as $key) $row[$key] = (string)$row[$key];
	} else {
		$id = 0;
		$row = array("creatureid"=>0,"creaturelevel"=>$level);
		$context = resurrection_content_context('add', []);
	}
	addnav("Edit");
	addnav("Creature properties", "creatures.php?op=edit&creatureid=".($id ?: ""));
	addnav("Add");
	addnav("Add Another Creature", "creatures.php?op=add&level=$level");
	module_editor_navs("prefs-creatures", "creatures.php?op=edit&subop=module&creatureid=$id&module=");
	$form = array(
		"Creature Properties,title",
		"creatureid"=>"Creature id,hidden",
		"creaturename"=>"Creature Name",
		"creatureweapon"=>"Weapon",
		"creaturewin"=>"Win Message (Displayed when the creature kills the player)",
		"creaturelose"=>"Death Message (Displayed when the creature is killed by the player)",
		// 18 to make a non-forest available monster
		// (ie, graveyard only)_
		"creaturelevel"=>"Level,range,1,18,1",
		"forest"=>"Creature is in forest?,bool",
		"graveyard"=>"Creature is in graveyard?,bool",
	);
	if ($session['user']['superuser'] & SU_MEGAUSER || $session['user']['superuser'] & SU_RAW_SQL) {
		$form["creatureaiscript"] = "Creature's A.I.,textarearesizeable";
	}
	rawoutput(resurrection_content_form("creatures.php?op=save", "creature-edit-$id", $context));
	showform($form, $row);
	rawoutput("</form>");
	addnav("Navigation");
	addnav("Return to the creature editor","creatures.php?level=$level");
}
page_footer();
?>