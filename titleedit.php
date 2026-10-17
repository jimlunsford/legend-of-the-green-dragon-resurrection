<?php
//Author: Lonny Luberts - 3/18/2005
//Heavily modified by JT Traub
require_once("common.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

check_su_access(SU_EDIT_USERS);

tlschema("retitle");

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'add', 'edit', 'save', 'delete', 'reset'], '');
	// A save without an ID (1.1.2's add form posts id=0) adds a title.
	$id = in_array($op, ['edit', 'delete'], true) ? resurrection_content_id($_GET, 'id') : ($op == 'save' ? resurrection_content_id($_GET, 'id', true) : 0);
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the title editor'); }
page_header("Title Editor");
$editarray=array(
	"Titles,title",
	//"titleid"=>"Title Id,hidden",
	"dk"=>"Dragon Kills,int|0",
	// "ref"=>"Arbitrary Tag,int",
	"male"=>"Male Title,text|",
	"female"=>"Female Title,text|",
);
addnav("Other");
require_once("lib/superusernav.php");
superusernav();
addnav("Functions");

if ($op=="save") {
	// Ref is currently unused; it is saved as ''.
	if ($id == 0) {
		$note = "`^New title added.`0";
		$errnote = "`\$Unable to add title.`0";
	}else {
		$note = "`^Title modified.`0";
		$errnote = "`\$Unable to modify title.`0";
	}
	// 1.1.2 reported the affected row count, so saving a title unchanged shows the error note.
	if (resurrection_title_save($id) == 0) {
		output($errnote);
	} else {
		output($note);
	}
	$op = "";
} elseif ($op == "delete") {
	resurrection_content_delete("titles", "titleid", "id", "title", "the title editor");
	output("`^Title deleted.`0");
	$op = "";
}

if ($op == ""){
	$sql = "SELECT * FROM ".db_prefix("titles")." ORDER BY dk, titleid";
	$result = db_query($sql);
	if (db_num_rows($result)<1){
		output("");
	}else{
		$row = db_fetch_assoc($result);
	}
	output("`@`c`b-=Title Editor=-`b`c");
	$ops = translate_inline("Ops");
	$dks = translate_inline("Dragon Kills");
	// $ref is currently unused
	// $reftag = translate_inline("Reference Tag");
	$mtit = translate_inline("Male Title");
	$ftit = translate_inline("Female Title");
	$edit = translate_inline("Edit");
	$del = translate_inline("Delete");
	$delconfirm = translate_inline("Are you sure you wish to delete this title?");
	rawoutput("<table border=0 cellspacing=0 cellpadding=2 width='100%' align='center'>");
	// reference tag is currently unused
	// rawoutput("<tr class='trhead'><td>$ops</td><td>$dks</td><td>$reftag</td><td>$mtit</td><td>$ftit</td></tr>");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$dks</td><td>$mtit</td><td>$ftit</td></tr>");
	$result = db_query($sql);
	$i = 0;
	while($row = db_fetch_assoc($result)) {
		$id = (int)$row['titleid'];
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
		rawoutput("<td>[<a href='titleedit.php?op=edit&id=$id'>$edit</a>|" .
			resurrection_content_delete_form("titleedit.php?op=delete&id=$id", "title-del-$id", resurrection_content_context('del', $row), $del, $delconfirm) . "]</td>");
		addnav("","titleedit.php?op=edit&id=$id");
		rawoutput("<td>");
		output_notl("`&%s`0",$row['dk']);
		rawoutput("</td><td>");
		// reftag is currently unused
		// output("`^%s`0", $row['ref']);
		// output("</td><td>");
		output_notl("`2%s`0",$row['male']);
		rawoutput("</td><td>");
		output_notl("`6%s`0",$row['female']);
		rawoutput("</td></tr>");
		$i++;
	}
	rawoutput("</table>");
	//modulehook("titleedit", array());
	addnav("Functions");
	addnav("Add a Title", "titleedit.php?op=add");
	addnav("Refresh List", "titleedit.php");
	addnav("Reset Users Titles", "titleedit.php?op=reset");
	title_help();
} elseif ($op=="edit" || $op=="add") {
	require_once("lib/showform.php");
	if ($op=="edit"){
		$row = resurrection_content_row("titles", "titleid", $id);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Title not found.'), 'the title editor');
		$context = resurrection_content_context('edit', $row);
	} else {
		$row = array('titleid'=>0, 'male'=>'', 'female'=>'', 'dk'=>0);
		$id = 0;
		$context = resurrection_content_context('add', []);
	}
	rawoutput(resurrection_content_form("titleedit.php?op=save&id=$id", "title-edit-$id", $context));
	showform($editarray,$row);
	rawoutput("</form>");
	addnav("Functions");
	addnav("Main Title Editor", "titleedit.php");
	title_help();
} elseif ($op == "reset") {
	// 1.1.2 rebuilt every title on this GET link, in one unguarded pass. The link now shows the protected form;
	// the rebuild runs on its POST (resurrection_title_rebuild()).
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
		try { resurrection_content_fields([]); }
		catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the title editor'); }
		resurrection_consume_action('title-rebuild', resurrection_title_rebuild_context());
		output("`^Rebuilding all titles.`0`n`n");
		$rebuild = resurrection_title_rebuild();
		foreach ($rebuild['skipped'] as $name) {
			output("`\$Not changing `^%s`\$: the new name would be too long.`0`n", $name);
		}
		if ($rebuild['failed']) {
			output("`n`n`\$The rebuild stopped on a failure. %s of %s accounts were checked and %s changed; the rest were not changed.`0`n", $rebuild['checked'], $rebuild['total'], $rebuild['changed']);
			output("`\$Reset the titles again to finish.`0");
		} else {
			output("`n`n`^Done.`0");
		}
	} else {
		output("`^Every account whose title does not match its dragon kills will get a title for its rank.`0`n`n");
	}
	if (!isset($rebuild) || $rebuild['failed']) {
		rawoutput(resurrection_content_form("titleedit.php?op=reset", "title-rebuild", resurrection_title_rebuild_context()));
		$reset = translate_inline("Reset Users Titles");
		rawoutput("<input type='submit' class='button' value='" . htmlspecialchars($reset, ENT_QUOTES, 'UTF-8') . "'></form>");
	}
	addnav("Main Title Editor", "titleedit.php");
}

function title_help()
{
	output("`#You can have multiple titles for a given dragon kill rank.");
	output("If you do, one of those titles will be chosen at random to give to the player when a title is assigned.`n`n");
	output("You can have gaps in the title order.");
	output("If you have a gap, the title given will be for the DK rank less than or equal to the players current number of DKs.`n");
}

page_footer();
?>