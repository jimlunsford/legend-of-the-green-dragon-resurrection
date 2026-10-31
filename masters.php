<?php
// Initially written as a module by Chris Vorndran.
// Moved into core by JT Traub

require_once("common.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

check_su_access(SU_EDIT_CREATURES);

tlschema("masters");

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'edit', 'save', 'del'], '');
	// id 0 is a new master, as in 1.1.2; deleting needs one.
	$id = in_array($op, ['edit', 'save', 'del'], true) ? resurrection_content_id($_GET, 'id', $op != 'del') : 0;
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the masters editor'); }

page_header("Masters Editor");
require_once("lib/superusernav.php");
superusernav();

if ($op == "del") {
	resurrection_content_delete("masters", "creatureid", "id", "master", "the masters editor");
	output("`^Master deleted.`0");
	$op = "";
} elseif ($op == "save") {
	$name = resurrection_master_save($id);
	if ($id == 0) {
		output("`^Master %s`^ added.", $name);
	} else {
		output("`^Master %s`^ updated.", $name);
	}
	$op = "";
} elseif ($op == "edit") {
	addnav("Functions");
	addnav("Return to Masters Editor", "masters.php");
	if ($id == 0) {
		$row = array(
			'creaturelevel'=>1,
			'creaturename'=>'',
			'creatureweapon'=>'',
			'creaturewin'=>'',
			'creaturelose'=>''
		);
		$context = resurrection_content_context('add', []);
	} else {
		// 1.1.2 drew an empty form for an unknown ID, whose save then updated nothing and said "updated".
		$row = resurrection_content_row("masters", "creatureid", $id);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Master not found.'), 'the masters editor');
		$context = resurrection_content_context('edit', $row);
		foreach (['creaturelevel', 'creaturename', 'creatureweapon', 'creaturewin', 'creaturelose'] as $key) $row[$key] = (string)$row[$key];
	}
	rawoutput(resurrection_content_form("masters.php?op=save&id=$id", "master-edit-$id", $context));
	output("`^Master's level:`n");
	rawoutput("<input id='input' name='level' value='".htmlentities($row['creaturelevel'], ENT_QUOTES, getsetting("charset", "ISO-8859-1"))."' SIZE=5>");
	output_notl("`n");
	output("`^Master's name:`n");
	rawoutput("<input id='input' name='name' value='".htmlentities($row['creaturename'], ENT_QUOTES, getsetting("charset", "ISO-8859-1"))."'>");
	output_notl("`n");
	output("`^Master's weapon:`n");
	rawoutput("<input id='input' name='weapon' value='".htmlentities($row['creatureweapon'], ENT_QUOTES, getsetting("charset", "ISO-8859-1"))."'>");
	output_notl("`n");
	output("`^Master's speech when player wins:`n");
	rawoutput("<textarea name='lose' rows='5' cols='30' class='input'>".htmlentities($row['creaturelose'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea>");
	output_notl("`n");
	output("`^Master's speech when player loses:`n");
	rawoutput("<textarea name='win' rows='5' cols='30' class='input'>".htmlentities($row['creaturewin'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea>");
	output_notl("`n");
	$submit = translate_inline("Submit");
	rawoutput("<input type='submit' class='button' value='$submit'>");
	rawoutput("</form>");
	output_notl("`n`n");
	output("`#The following codes are supported in both the win and lose speeches (case matters):`n");
	output("%w = The players's name (can be specified as {goodguy}`n");
	output("%W = The masters's name (can be specified as {badguy}`n");
	output("%x = The players's weapon (can be specified as {weapon}`n");
	output("%X = The master's weapon (can be specified as {creatureweapon}`n");
	output("%a = The players's armor (can be specified as {armor}`n");
	output("%s = Subjective pronoun for the player (him her)`n");
	output("%p = Possessive pronoun for the player (his her)`n");
	output("%o = Objective pronoun for the player (he she)`n");
}

if ($op == "") {
	addnav("Functions");
	addnav("Refresh list", "masters.php");
	addnav("Add master", "masters.php?op=edit&id=0");
	$res = db_query("SELECT * FROM ".db_prefix("masters")." ORDER BY creaturelevel", true);
	$ops = translate_inline("Ops");
	$edit = translate_inline("edit");
	$del = translate_inline("del");
	$delconfirm = translate_inline("Are you sure you wish to delete this master.");
	$name = translate_inline("Name");
	$level = translate_inline ("Level");
	$lose = translate_inline("Lose to Master");
	$win = translate_inline("Win against Master");
	$weapon = translate_inline("Weapon");
	rawoutput("<table border='0' cellpadding='2' cellspacing='1' align='center'".resurrection_presentation("grid").">");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$level</td><td>$name</td><td>$weapon</td><td>$win</td><td>$lose</tr>");
	foreach ($res as $i=>$row) {
		$id = (int)$row['creatureid'];
		rawoutput("<tr class='".($i%2?"trdark":"trlight")."'><td nowrap>");
		rawoutput("[ <a href='masters.php?op=edit&id=$id'>");
		output_notl($edit);
		rawoutput("</a> | " . resurrection_content_delete_form("masters.php?op=del&id=$id", "master-del-$id", resurrection_content_context('del', $row), $del, $delconfirm) . " ]");
		addnav("","masters.php?op=edit&id=$id");
		rawoutput("</td><td>");
		output_notl("`%%s`0",$row['creaturelevel']);
		rawoutput("</td><td>");
		output_notl("`#%s`0",stripslashes($row['creaturename']));
		rawoutput("</td><td>");
		output_notl("`!%s`0",stripslashes($row['creatureweapon']));
		rawoutput("</td><td>");
		output_notl("`&%s`0",stripslashes($row['creaturelose']));
		rawoutput("</td><td>");
		output_notl("`^%s`0",stripslashes($row['creaturewin']));
		rawoutput("</td></tr>");
	}
	rawoutput("</table>");
	output("`n`#You can change the names, weapons and messages of all of the Training Masters.");
	output("It is suggested, that you do not toy around with this, unless you know what you are doing.`0`n");
}
page_footer();
?>