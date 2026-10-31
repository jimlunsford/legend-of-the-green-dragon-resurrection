<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/showform.php");
require_once("lib/http.php");
require_once("lib/content_editor.php");

check_su_access(SU_EDIT_EQUIPMENT);

tlschema("armor");

$shop = resurrection_equipment_shop('armor');
try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'add', 'edit', 'save', 'del'], '');
	$armorlevel = resurrection_content_level();
	$id = $op == "edit" || $op == "del" ? resurrection_content_id($_GET, 'id') : 0;
} catch (InvalidArgumentException $error) { resurrection_content_failure($error, 'the armor editor'); }
page_header("Armor Editor");
require_once("lib/superusernav.php");
superusernav();
addnav("Armor Editor");
addnav("Armor Editor Home","armoreditor.php?level=$armorlevel");

addnav("Add armor","armoreditor.php?op=add&level=$armorlevel");
$values = array(1=>48,225,585,990,1575,2250,2790,3420,4230,5040,5850,6840,8010,9000,10350);
output("`&<h3>Armor for %s Dragon Kills</h3>`0",$armorlevel,true);

$armorarray=array(
	"Armor,title",
	"armorid"=>"Armor ID,hidden",
	"armorname"=>"Armor Name",
	"defense"=>"Defense,range,1,15,1");
if($op=="edit" || $op=="add"){
	if ($op=="edit"){
		$row = resurrection_content_row("armor", "armorid", $id);
		if ($row === null) resurrection_content_failure(new OutOfBoundsException('Armor not found.'), 'the armor editor');
		$context = resurrection_content_context('edit', $row);
	}else{
		$rows = db_query("SELECT max(defense+1) AS defense FROM " . db_prefix("armor") . " WHERE level=?", true, [$armorlevel]);
		$row = $rows[0];
		$context = resurrection_content_context('add', [$armorlevel]);
	}
	// 1.1.2 posted to this URL with the hidden armor ID (none for an add); the form now carries the intent for
	// that row as shown, or for an add at this level.
	rawoutput(resurrection_content_form("armoreditor.php?op=save&level=$armorlevel", 'armor-edit-' . $id, $context));
	showform($armorarray,$row);
	rawoutput("</form>");
}else if($op=="del"){
	resurrection_content_delete("armor", "armorid", "id", "armor", "the armor editor");
	$op = "";
	httpset("op", $op);
}else if($op=="save"){
	resurrection_equipment_editor_save($shop, $armorlevel, $values);
	$op = "";
	httpset("op", $op);
}
if ($op==""){
	$sql = "SELECT max(level+1) AS level FROM " . db_prefix("armor");
	$res = db_query($sql);
	$row = db_fetch_assoc($res);
	$max = $row['level'];
	for ($i=0;$i<=$max;$i++){
		if ($i == 1)
			addnav(array("Armor for %s DK",$i),"armoreditor.php?level=$i");
		else
			addnav(array("Armor for %s DKs",$i),"armoreditor.php?level=$i");
	}
	$result = db_query("SELECT * FROM " . db_prefix("armor") . " WHERE level=? ORDER BY defense", true, [$armorlevel]);
	$ops = translate_inline("Ops");
	$name = translate_inline("Name");
	$cost = translate_inline("Cost");
	$defense = translate_inline("Defense");
	$level = translate_inline("Level");
	$edit = translate_inline("Edit");
	$del = translate_inline("Del");
	$delconfirm = translate_inline("Are you sure you wish to delete this armor?");

	rawoutput("<table border=0 cellpadding=2 cellspacing=1".resurrection_presentation("grid").">");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$name</td><td>$cost</td><td>$defense</td><td>$level</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2?"trdark":"trlight")."'>");
		$rowid = (int)$row['armorid'];
		rawoutput("<td>[<a href='armoreditor.php?op=edit&id=$rowid&level=$armorlevel'>$edit</a>|" .
			resurrection_content_delete_form("armoreditor.php?op=del&id=$rowid&level=$armorlevel", "armor-del-$rowid", resurrection_content_context('del', $row), $del, $delconfirm) . "]</td>");
		addnav("","armoreditor.php?op=edit&id=$rowid&level=$armorlevel");
		rawoutput("<td>");
		output_notl($row['armorname']);
		rawoutput("</td><td>");
		output_notl($row['value']);
		rawoutput("</td><td>");
		output_notl($row['defense']);
		rawoutput("</td><td>");
		output_notl($row['level']);
		rawoutput("</td>");
		rawoutput("</tr>");
	}
	rawoutput("</table>");
}
page_footer();
?>