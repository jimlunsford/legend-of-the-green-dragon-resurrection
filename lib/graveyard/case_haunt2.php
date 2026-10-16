<?php
require_once("lib/bank_authority.php");
try {
	// The bank's recipient search: each typed UTF-8 character in order, LIKE wildcards matching only themselves.
	$string = resurrection_bank_search_pattern(\Resurrection\Http\Input::string($_POST, 'name'));
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid haunt search.'); }
// Bound; the haunter is not offered (the haunt refuses them).
$result = db_query("SELECT login,name,level FROM " . db_prefix("accounts") . " WHERE name LIKE ? ESCAPE '|' AND locked=0 AND acctid<>? ORDER BY level,login", true, [$string, (int)$session['user']['acctid']]);
if (db_num_rows($result)<=0){
	output("`\$%s`) could find no one who matched the name you gave him.",$deathoverlord);
}elseif(db_num_rows($result)>100){
	output("`\$%s`) thinks you should narrow down the number of people you wish to haunt.",$deathoverlord);
	$search = translate_inline("Search");
	rawoutput("<form action='graveyard.php?op=haunt2' method='POST'>");
	addnav("","graveyard.php?op=haunt2");
	output("Who would you like to haunt? ");
	rawoutput("<input name='name' id='name'>");
	rawoutput("<input type='submit' class='button' value='$search'>");
	rawoutput("</form>");
	rawoutput("<script language='JavaScript'>document.getElementById('name').focus()</script>",true);
}else{
	output("`\$%s`) will allow you to try to haunt these people:`n",$deathoverlord);
	$name = translate_inline("Name");
	$lev = translate_inline("Level");
	rawoutput("<table cellpadding='3' cellspacing='0' border='0'>");
	rawoutput("<tr class='trhead'><td>$name</td><td>$lev</td></tr>");
	for ($i=0;$i<db_num_rows($result);$i++){
		$row = db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'><td><a href='graveyard.php?op=haunt3&name=".HTMLEntities($row['login'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."'>");
		output_notl("%s", $row['name']);
		rawoutput("</a></td><td>");
		output_notl("%s", $row['level']);
		rawoutput("</td></tr>",true);
		addnav("","graveyard.php?op=haunt3&name=".HTMLEntities($row['login'], ENT_COMPAT, getsetting("charset", "ISO-8859-1")));
	}
	rawoutput("</table>",true);
}
addnav(array("Question `\$%s`0 about the worth of your soul",$deathoverlord),"graveyard.php?op=question");
$max = $session['user']['level'] * 5 + 50;
$favortoheal = round(10 * ($max-$session['user']['soulpoints'])/$max);
addnav(array("Restore Your Soul (%s favor)",$favortoheal),"graveyard.php?op=restore");
addnav("Places");
addnav("S?Land of the Shades","shades.php");
addnav("G?The Graveyard","graveyard.php");
addnav("M?Return to the Mausoleum","graveyard.php?op=enter");
?>