<?php
// addnews ready
// translator ready
// mail ready
define("ALLOW_ANONYMOUS",true);
define("OVERRIDE_FORCED_NAV",true);
require_once("common.php");
require_once("lib/commentary.php");
require_once("lib/nltoappon.php");
require_once("lib/http.php");
require_once("lib/motd.php");

tlschema("motd");

$op = httpget('op');
$id = httpget('id');

addcommentary();
popup_header("LoGD Message of the Day (MoTD)");

if ($session['user']['superuser'] & SU_POST_MOTD) {
	$addm = translate_inline("Add MoTD");
	$addp = translate_inline("Add Poll");
	rawoutput(" [ <a href='motd.php?op=add'>$addm</a> | <a href='motd.php?op=addpoll'>$addp</a> ]<br/><br/>");
}

if ($op=="vote"){
	motd_vote();
}
if ($op == "add" || $op == "addpoll" || $op == "del")  {
	if ($session['user']['superuser'] & SU_POST_MOTD) {
		if ($op == "add") motd_form($id);
		elseif ($op == "addpoll") motd_poll_form($id);
		elseif ($op == "del") motd_del($id);
	} else {
		motd_refuse();
	}
}
if ($op=="") {
	$count = getsetting("motditems", 5);
	$newcount = httpget("newcount");
	if (!$newcount || !httppost('proceed')) $newcount=0;
	/*
	motditem("Beta!","Please see the beta message below.","","", "");
	*/
	$m = httpget("month");
	if ($m !== false && $m !== '' && (!is_string($m) || !preg_match('/\A[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $m))) {
		http_response_code(400); exit('Invalid month.');
	}
	$newcount = (int)$newcount; $count = (int)$count;
	if ($m > ""){
		// The whole month, as the archive list counts it. 1.1.2 compared with "{$m}-31", which strict SQL
		// refuses for a 30-day month (error 1525) and which left out the 31st after midnight.
		$sql = "SELECT " . db_prefix("motd") . ".*,name AS motdauthorname FROM " . db_prefix("motd") . " LEFT JOIN " . db_prefix("accounts") . " ON " . db_prefix("accounts") . ".acctid = " . db_prefix("motd") . ".motdauthor WHERE motddate >= ? AND motddate < ? ORDER BY motddate DESC";
		$result = db_query_cached($sql,"motd-$m", 900, ["{$m}-01 00:00:00", date("Y-m-d H:i:s", strtotime("+1 month", strtotime("{$m}-01 00:00:00")))]);
	}else{
		$sql = "SELECT " . db_prefix("motd") . ".*,name AS motdauthorname FROM " . db_prefix("motd") . " LEFT JOIN " . db_prefix("accounts") . " ON " . db_prefix("accounts") . ".acctid = " . db_prefix("motd") . ".motdauthor ORDER BY motddate DESC limit ?,?";
		// Bound: `newcount` is a request value. 1.1.2 pasted it in (a negative one, or one whose sum with the item
		// count passed PHP's integer range, was a database error); the offset and 1.1.2's row count are kept.
		$newcount = max(0, $newcount); $count = max(0, $count);
		$limitparams = [$newcount, $newcount > PHP_INT_MAX - $count ? PHP_INT_MAX : $newcount + $count];
		if ($newcount=0) //cache only the last x items
			$result = db_query_cached($sql,"motd", 900, $limitparams);
			else
			$result = db_query($sql, true, $limitparams);
	}
	while ($row = db_fetch_assoc($result)) {
		if (!isset($session['user']['lastmotd']))
			$session['user']['lastmotd']=0;
		if ($row['motdauthorname']=="")
			$row['motdauthorname']="`@Green Dragon Staff`0";
		if ($row['motdtype']==0){
			motditem($row['motdtitle'], $row['motdbody'],
					$row['motdauthorname'], $row['motddate'],
					$row['motditem']);
		}else{
			pollitem($row['motditem'], $row['motdtitle'], $row['motdbody'],
					$row['motdauthorname'],$row['motddate'],
					$row['motditem']);
		}
	}
	/*
	motditem("Beta!","For those who might be unaware, this website is still in beta mode.  I'm working on it when I have time, which generally means a couple of changes a week.  Feel free to drop suggestions, I'm open to anything :-)","","", "");
	*/

	$result = db_query("SELECT mid(motddate,1,7) AS d, count(*) AS c FROM ".db_prefix("motd")." GROUP BY d ORDER BY d DESC");
	$row = db_fetch_assoc($result);
	rawoutput("<form action='motd.php' method='GET'>");
	output("MoTD Archives:");
	rawoutput("<select name='month' onChange='this.form.submit();' >");
	rawoutput("<option value=''>--Current--</option>");
	while ($row = db_fetch_assoc($result)){
		$time = strtotime("{$row['d']}-01");
		$m = translate_inline(date("M",$time));
		rawoutput ("<option value='{$row['d']}'".(httpget("month")==$row['d']?" selected":"").">$m".date(", Y",$time)." ({$row['c']})</option>");
	}
	rawoutput("</select>".tlbutton_clear());
	rawoutput("<input type='hidden' name='newcount' value='".($count+$newcount)."'>");
	rawoutput("<input type='submit' value='&gt;' name='proceed'  class='button'>");
	rawoutput(" <input type='submit' value='".translate_inline("Submit")."' class='button'>");
	rawoutput("</form>");

	commentdisplay("`n`@Commentary:`0`n", "motd");
}

$session['needtoviewmotd']=false;

$sql = "SELECT motddate FROM " . db_prefix("motd") ." ORDER BY motditem DESC LIMIT 1";
$result = db_query_cached($sql, "motddate");
$row = db_fetch_assoc($result);
// With no MoTD at all, PHP 5 read null here; PHP 8 stopped the page.
$session['user']['lastmotd']=$row['motddate'] ?? null;

popup_footer();
?>