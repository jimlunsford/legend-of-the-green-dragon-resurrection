<?php
require_once("lib/ban_admin.php");
$subop = httpget("subop");
$none = translate_inline('NONE');
if ($subop=="xml"){
	try {
		$ip = \Resurrection\Http\Input::string($_GET, 'ip');
		$id = \Resurrection\Http\Input::string($_GET, 'id');
	} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid request.'); }
	header("Content-Type: text/xml");
	$sql = "SELECT DISTINCT " . db_prefix("accounts") . ".name FROM " . db_prefix("bans") . ", " . db_prefix("accounts") . " WHERE (ipfilter=? AND " .
		db_prefix("bans") . ".uniqueid=?) AND ((substring(" .
		db_prefix("accounts") . ".lastip,1,length(ipfilter))=ipfilter " .
		"AND ipfilter<>'') OR (" .  db_prefix("bans") . ".uniqueid=" .
		db_prefix("accounts") . ".uniqueid AND " .
		db_prefix("bans") . ".uniqueid<>''))";
	$r = db_query($sql, true, [$ip, $id]);
	echo "<xml>";
	$number=db_num_rows($r);
	for ($x=0;$x<$number;$x++){
		$ro = db_fetch_assoc($r);
		echo "<name name=\"";
		echo urlencode(appoencode("`0{$ro['name']}"));
		echo "\"/>";
	}
	if (db_num_rows($r)==0)
		echo "<name name=\"" . htmlspecialchars($none, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"/>";
	echo "</xml>";
	exit();
}
$duration = resurrection_ban_list_duration(httpget("duration"));
// 1.1.2 deleted expired bans when this list was shown. The New Day purge deletes them now; the list leaves them out.
$since = " WHERE (banexpire IS NULL OR banexpire >= ?)";
$parameters = [date("Y-m-d")];
if ($duration=="") {
	$since .= " AND banexpire <= ? AND banexpire IS NOT NULL";
	$parameters[] = date("Y-m-d H:i:s",strtotime("+2 weeks"));
		output("`bShowing bans that will expire within 2 weeks.`b`n`n");
}else{
	if ($duration=="forever") {
		output("`bShowing all bans`b`n`n");
	}else{
		$since .= " AND banexpire <= ? AND banexpire IS NOT NULL";
		$parameters[] = date("Y-m-d H:i:s",strtotime("+".$duration));
		output("`bShowing bans that will expire within %s.`b`n`n",$duration);
	}
}
addnav("Will Expire Within");
addnav("1 week","user.php?op=removeban&duration=1+week");
addnav("2 weeks","user.php?op=removeban&duration=2+weeks");
addnav("3 weeks","user.php?op=removeban&duration=3+weeks");
addnav("4 weeks","user.php?op=removeban&duration=4+weeks");
addnav("2 months","user.php?op=removeban&duration=2+months");
addnav("3 months","user.php?op=removeban&duration=3+months");
addnav("4 months","user.php?op=removeban&duration=4+months");
addnav("5 months","user.php?op=removeban&duration=5+months");
addnav("6 months","user.php?op=removeban&duration=6+months");
addnav("1 year","user.php?op=removeban&duration=1+year");
addnav("2 years","user.php?op=removeban&duration=2+years");
addnav("4 years","user.php?op=removeban&duration=4+years");
addnav("Forever","user.php?op=removeban&duration=forever");
$sql = "SELECT * FROM " . db_prefix("bans") . " $since ORDER BY banexpire";
$result = db_query($sql, true, $parameters);
rawoutput("<script language='JavaScript'>
function getUserInfo(filename,divid){
	// Browsers no longer load an XML document directly; the request is the link's own registered URL.
	var request = new XMLHttpRequest();
	request.onload = function(){
		var xmldom = request.responseXML;
		var output='';
		for (var x=0; x<xmldom.documentElement.childNodes.length; x++){
			output = output + unescape(xmldom.documentElement.childNodes[x].getAttribute('name').replace(/\\+/g,' ')) +'<br>';
		}
		document.getElementById('user'+divid).innerHTML=output;
	};
	request.open('GET', filename);
	request.send();
}
</script>
");
rawoutput("<table border=0 cellpadding=2 cellspacing=1".resurrection_presentation("grid").">");
$ops = translate_inline("Ops");
$bauth = translate_inline("Ban Author");
$ipd = translate_inline("IP/ID");
$dur = translate_inline("Duration");
$mssg = translate_inline("Message");
$aff = translate_inline("Affects");
$l = translate_inline("Last");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$bauth</td><td>$ipd</td><td>$dur</td><td>$mssg</td><td>$aff</td><td>$l</td></tr>");
$i=0;
while ($row = db_fetch_assoc($result)) {
	$liftban = translate_inline("Lift&nbsp;ban");
	$showuser = translate_inline("Click&nbsp;to&nbsp;show&nbsp;users");
	rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
    rawoutput('<td><form action="user.php?op=delban" method="POST">' . resurrection_csrf_field());
    rawoutput('<input type="hidden" name="ipfilter" value="' . htmlspecialchars($row['ipfilter'], ENT_QUOTES, 'UTF-8') . '">');
    rawoutput('<input type="hidden" name="uniqueid" value="' . htmlspecialchars($row['uniqueid'], ENT_QUOTES, 'UTF-8') . '"><button class="button" type="submit">');
    output_notl('%s', $liftban, true);
    rawoutput('</button></form>');
    addnav('', 'user.php?op=delban');
	rawoutput("</td><td>");
	output_notl("`&%s`0", $row['banner']);
	rawoutput("</td><td>");
	output_notl("%s", $row['ipfilter']);
	output_notl("%s", $row['uniqueid']);
	rawoutput("</td><td>");
		// "43200" used so will basically round to nearest day rather than floor number of days
	$expire= sprintf_translate("%s days",
			round((strtotime($row['banexpire'] ?? '9999-12-31')+43200-strtotime("now"))/86400,0));
	if (substr($expire,0,2)=="1 ")
		$expire= translate_inline("1 day");
	if (date("Y-m-d",strtotime($row['banexpire'] ?? '9999-12-31')) == date("Y-m-d"))
		$expire=translate_inline("Today");
	if (date("Y-m-d",strtotime($row['banexpire'] ?? '9999-12-31')) ==
			date("Y-m-d",strtotime("1 day")))
		$expire=translate_inline("Tomorrow");
	// A permanent ban is NULL now; 1.1.2 compared its zero datetime with a date and never matched.
	if ($row['banexpire'] === null)
		$expire=translate_inline("Never");
	output_notl("%s", $expire);
	rawoutput("</td><td>");
	output_notl("%s", $row['banreason']);
	rawoutput("</td><td>");
	$file = "user.php?op=removeban&subop=xml&ip=" . rawurlencode($row['ipfilter']) . "&id=" . rawurlencode($row['uniqueid']);
	rawoutput("<div id='user$i'><a href='" . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . "' target='_blank' onClick=\"getUserInfo(this.href,$i); return false;\">");
	output_notl("%s", $showuser, true);
	rawoutput("</a></div>");
	addnav("",$file);
	rawoutput("</td><td>");
	output_notl("%s", relativedate($row['lasthit']));
	rawoutput("</td></tr>");
	$i++;
}
rawoutput("</table>");
?>
