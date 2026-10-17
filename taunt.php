<?php
// addnews ready
// mail ready
// translator ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

tlschema("taunt");

check_su_access(SU_EDIT_CREATURES);

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'edit', 'save', 'del'], '');
	// An empty tauntid is a new taunt, as in 1.1.2; deleting needs one.
	$tauntid = resurrection_content_id($_GET, 'tauntid', $op != "del");
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the taunt editor'); }
page_header("Taunt Editor");
require_once("lib/superusernav.php");
superusernav();
if ($op=="edit"){
	addnav("Taunts");
	addnav("Return to the taunt editor","taunt.php");
	if ($tauntid > 0){
		$row = resurrection_content_row("taunts", "tauntid", $tauntid);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Taunt not found.'), 'the taunt editor');
		rawoutput(resurrection_content_form("taunt.php?op=save&tauntid=$tauntid", "taunt-edit-$tauntid", resurrection_content_context('edit', $row)));
		require_once("lib/substitute.php");
		$badguy=array('creaturename'=>'Baron Munchausen', 'creatureweapon'=>'Bad Puns');
		// A stored taunt with a stray % is fatal in sprintf on PHP 8; show that instead of failing the editor.
		if (resurrection_taunt_formats((string)$row['taunt'])) {
			$taunt = substitute_array($row['taunt']);
			$taunt = call_user_func_array("sprintf_translate", $taunt);
			output("Preview: %s`0`n`n", $taunt);
		} else {
			output("`\$This taunt cannot be previewed: a percent sign that is not one of the codes below must be doubled.`0`n`n");
		}
	} else {
		$row = array('tauntid'=>0, 'taunt'=>"");
		rawoutput(resurrection_content_form("taunt.php?op=save&tauntid=", "taunt-edit-0", resurrection_content_context('add', [])));
	}
	output("Taunt: ");
	rawoutput("<input name='taunt' value=\"".HTMLEntities((string)$row['taunt'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" size='70'><br>");
	output("The following codes are supported (case matters):`n");
	output("%w = The player's name (also can be specified as {goodguy}`n");
	output("%x = The player's weapon (also can be specified as {weapon}`n");
	output("%a = The player's armor (also can be specified as {armor}`n");
	output("%s = Subjective pronoun for the player (him her)`n");
	output("%p = Possessive pronoun for the player (his her)`n");
	output("%o = Objective pronoun for the player (he she)`n");
	output("%W = The monster's name (also can be specified as {badguy}`n");
	output("%X = The monster's weapon (also can be specified as {creatureweapon}`n");
	$save = translate_inline("Save");
	rawoutput("<input type='submit' class='button' value='$save'>");
	rawoutput("</form>");
}else if($op=="del"){
	resurrection_content_delete("taunts", "tauntid", "tauntid", "taunt", "the taunt editor");
	$op = "";
	httpset("op", "");
}else if($op=="save"){
	resurrection_taunt_save($tauntid);
	$op = "";
	httpset("op", "");
}
if ($op == "") {
	$sql = "SELECT * FROM " . db_prefix("taunts");
	$result = db_query($sql);
	rawoutput("<table border=0 cellpadding=2 cellspacing=1 bgcolor='#999999'>");
	$op = translate_inline("Ops");
	$t = translate_inline("Taunt String");
	$auth = translate_inline("Author");
	rawoutput("<tr class='trhead'><td nowrap>$op</td><td>$t</td><td>$auth</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row=db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2==0?"trdark":"trlight")."'>",true);
		rawoutput("<td nowrap>");
		$edit = translate_inline("Edit");
		$del = translate_inline("Del");
		$conf = translate_inline("Are you sure you wish to delete this taunt?");
		$id = (int)$row['tauntid'];
		rawoutput("[ <a href='taunt.php?op=edit&tauntid=$id'>$edit</a> | " .
			resurrection_content_delete_form("taunt.php?op=del&tauntid=$id", "taunt-del-$id", resurrection_content_context('del', $row), $del, $conf) . " ]");
		addnav("","taunt.php?op=edit&tauntid=$id");
		rawoutput("</td><td>");
		output_notl("%s", $row['taunt']);
		rawoutput("</td><td>");
		output_notl("%s", $row['editor']);
		rawoutput("</td></tr>");
	}
	addnav("","taunt.php?c=".httpget('c'));
	rawoutput("</table>");
	addnav("Taunts");
	addnav("Add a new taunt","taunt.php?op=edit");
}
page_footer();
?>