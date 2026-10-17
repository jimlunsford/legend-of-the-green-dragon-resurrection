<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/showform.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

check_su_access(SU_EDIT_EQUIPMENT);

tlschema("weapon");

$shop = resurrection_equipment_shop('weapon');
try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'add', 'edit', 'save', 'del'], '');
	$weaponlevel = resurrection_content_level();
	$id = $op == "edit" || $op == "del" ? resurrection_content_id($_GET, 'id') : 0;
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the weapon editor'); }
page_header("Weapon Editor");
require_once("lib/superusernav.php");
superusernav();

addnav("Editor");
addnav("Weapon Editor Home","weaponeditor.php?level=$weaponlevel");

addnav("Add a weapon","weaponeditor.php?op=add&level=$weaponlevel");
$values = array(1=>48,225,585,990,1575,2250,2790,3420,4230,5040,5850,6840,8010,9000,10350);
rawoutput("<h3>");
if ($weaponlevel == 1) {
	output("`&Weapons for 1 Dragon Kill`0");
} else {
	output("`&Weapons for %s Dragon Kills`0",$weaponlevel);
}
rawoutput("<h3>");

$weaponarray=array(
	"Weapon,title",
	"weaponid"=>"Weapon ID,hidden",
	"weaponname"=>"Weapon Name",
	"damage"=>"Damage,range,1,15,1");
if($op=="edit" || $op=="add"){
	if ($op=="edit"){
		$row = resurrection_content_row("weapons", "weaponid", $id);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Weapon not found.'), 'the weapon editor');
		$context = resurrection_content_context('edit', $row);
	}else{
		$rows = db_query("SELECT max(damage+1) AS damage FROM " . db_prefix("weapons") . " WHERE level=?", true, [$weaponlevel]);
		$row = $rows[0];
		$context = resurrection_content_context('add', [$weaponlevel]);
	}
	// 1.1.2 posted to this URL with the hidden weapon ID (none for an add); the form now carries the intent for
	// that row as shown, or for an add at this level.
	rawoutput(resurrection_content_form("weaponeditor.php?op=save&level=$weaponlevel", 'weapon-edit-' . $id, $context));
	showform($weaponarray,$row);
	rawoutput("</form>");
}else if($op=="del"){
	resurrection_content_delete("weapons", "weaponid", "id", "weapon", "the weapon editor");
	$op = "";
	httpset("op", $op);
}else if($op=="save"){
	resurrection_equipment_editor_save($shop, $weaponlevel, $values);
	$op = "";
	httpset("op", $op);
}
if ($op==""){
	$sql = "SELECT max(level+1) as level FROM " . db_prefix("weapons");
	$res = db_query($sql);
	$row = db_fetch_assoc($res);
	$max = $row['level'];
	for ($i=0;$i<=$max;$i++){
		if ($i == 1)
			addnav("Weapons for 1 DK","weaponeditor.php?level=$i");
		else
			addnav(array("Weapons for %s DKs",$i),"weaponeditor.php?level=$i");
	}
	$result = db_query("SELECT * FROM " . db_prefix("weapons") . " WHERE level=? ORDER BY damage", true, [$weaponlevel]);
	$ops = translate_inline("Ops");
	$name = translate_inline("Name");
	$cost = translate_inline("Cost");
	$damage = translate_inline("Damage");
	$level = translate_inline("Level");
	$edit = translate_inline("Edit");
	$del = translate_inline("Del");
	$delconfirm = translate_inline("Are you sure you wish to delete this weapon?");

	rawoutput("<table border=0 cellpadding=2 cellspacing=1 bgcolor='#999999'>");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$name</td><td>$cost</td><td>$damage</td><td>$level</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2?"trdark":"trlight")."'>");
		$rowid = (int)$row['weaponid'];
		rawoutput("<td>[<a href='weaponeditor.php?op=edit&id=$rowid&level=$weaponlevel'>$edit</a>|" .
			resurrection_content_delete_form("weaponeditor.php?op=del&id=$rowid&level=$weaponlevel", "weapon-del-$rowid", resurrection_content_context('del', $row), $del, "Are you sure you wish to delete this weapon?") . "]</td>");
		addnav("","weaponeditor.php?op=edit&id=$rowid&level=$weaponlevel");
		rawoutput("<td>");
		output_notl($row['weaponname']);
		rawoutput("</td><td>");
		output_notl($row['value']);
		rawoutput("</td><td>");
		output_notl($row['damage']);
		rawoutput("</td><td>");
		output_notl($row['level']);
		rawoutput("</td>");
		rawoutput("</tr>");
	}
	rawoutput("</table>");
}
page_footer();
?>