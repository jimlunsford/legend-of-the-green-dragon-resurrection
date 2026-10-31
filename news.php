<?php
require_once __DIR__ . '/src/Security/NewsArguments.php';
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
define("ALLOW_ANONYMOUS",true);
require_once("common.php");
require_once("lib/http.php");
require_once("lib/villagenav.php");

tlschema("news");

modulehook("news-intercept",array());

if ((int)getsetting("expirecontent",180)>0){
	$sql = "DELETE FROM " . db_prefix("news") . " WHERE newsdate<'".date("Y-m-d H:i:s",strtotime("-".getsetting("expirecontent",180)." days"))."'";
	//echo $sql;
	db_query($sql);
}

if ($session['user']['loggedin']) checkday();
$newsperpage=50;

$offset = (int)httpget('offset');
$timestamp=strtotime((0-$offset)." days");
// Bound: the day and the page come from the request (as integers).
$sql = "SELECT count(newsid) AS c FROM " . db_prefix("news") . " WHERE newsdate=?";
$result = db_query($sql, true, array(date("Y-m-d",$timestamp)));
$row = db_fetch_assoc($result);
$totaltoday=$row['c'];
$page = (int)httpget('page');
// A page below 1 is page 1 (1.1.2 gave a database error for a negative page).
if ($page < 1) $page=1;
$pageoffset = $page;
if ($pageoffset>0) $pageoffset--;
$pageoffset*=$newsperpage;
$sql = "SELECT * FROM " . db_prefix("news") . " WHERE newsdate=? ORDER BY newsid DESC LIMIT ?,?";
$result = db_query($sql, true, array(date("Y-m-d",$timestamp), $pageoffset, $newsperpage));
page_header("LoGD News");
$date=date("D, M j, Y",$timestamp);

$pagestr = "";
if ($totaltoday > $newsperpage) {
	$pagestr = sprintf_translate("(Items %s - %s of %s)", $pageoffset+1,
			min($pageoffset+$newsperpage,$totaltoday), $totaltoday);
}

$sql2 = "SELECT " . db_prefix("motd") . ".*,name AS motdauthorname FROM " . db_prefix("motd") . " LEFT JOIN " . db_prefix("accounts") . " ON " . db_prefix("accounts") . ".acctid = " . db_prefix("motd") . ".motdauthor ORDER BY motddate DESC LIMIT 1";
$result2 = db_query_cached($sql2, "lastmotd");
while ($row = db_fetch_assoc($result2)) {
	require_once("lib/motd.php");
	require_once("lib/nltoappon.php");
	if ($row['motdauthorname']=="")
		$row['motdauthorname']="`@Green Dragon Staff`0";
	if ($row['motdtype']==0){
		motditem($row['motdtitle'], $row['motdbody'],$row['motdauthorname'], $row['motddate'], "");
	}else{
		pollitem($row['motditem'], $row['motdtitle'], $row['motdbody'],$row['motdauthorname'],$row['motddate'], false);
	}
}
output_notl("`n");
output("`c`b`!News for %s %s`0`b`c", $date, $pagestr);

while ($row = db_fetch_assoc($result)) {
	// A stored line whose arguments fail the schema is skipped; a comment moderator still gets its Del link.
	try { $base_arguments = \Resurrection\Security\NewsArguments::read($row['arguments']); }
	catch (DomainException $error) { $base_arguments = null; }
	if ($base_arguments === null && !($session['user']['superuser'] & SU_EDIT_COMMENTS)) continue;
	output_notl("`c`2-=-`@=-=`2-=-`@=-=`2-=-`@=-=`2-=-`0`c");
	if ($session['user']['superuser'] & SU_EDIT_COMMENTS){
		// 1.1.2's Del link deleted on a GET. It is now a protected POST bound to this news line (superuser.php).
		require_once("lib/admin_tools.php");
		$del = htmlspecialchars(translate_inline("Del"), ENT_QUOTES, 'UTF-8');
		$delurl = "superuser.php?op=newsdelete&newsid=".(int)$row['newsid']."&return=".URLEncode($_SERVER['REQUEST_URI']);
		rawoutput("[ <form method='POST' action='$delurl'".resurrection_presentation('inline-form').">".resurrection_action_fields('news-delete-'.(int)$row['newsid'], resurrection_news_delete_context($row))."<button type='submit' class='button'>$del</button></form> ]&nbsp;");
		addnav("",$delurl);
	}
	if ($base_arguments === null) continue;
	tlschema($row['tlschema']);
	if ($row['arguments']>""){
		$arguments = array();
		array_push($arguments,$row['newstext']);
		while (list($key,$val)=resurrection_array_next($base_arguments)){
			array_push($arguments,$val);
		}
		$news = call_user_func_array("sprintf_translate",$arguments);
		rawoutput(tlbutton_clear());
	}else{
		$news = translate_inline($row['newstext']);
	}
	tlschema();
	output_notl($news."`n");
}
if (db_num_rows($result)==0){
	output_notl("`c`2-=-`@=-=`2-=-`@=-=`2-=-`@=-=`2-=-`0`c");
	output("`1`b`c Nothing of note happened this day.  All in all a boring day. `c`b`0");
}
output_notl("`c`2-=-`@=-=`2-=-`@=-=`2-=-`@=-=`2-=-`0`c");
if (!$session['user']['loggedin']) {
	addnav("Login Screen", "index.php");
} else if ($session['user']['alive']){
	villagenav();
}else{
	tlschema("nav");
	if($session['user']['sex'] == 1) {
		addnav("`!`bYou're dead, Jane!`b`0");
	} else {
		addnav("`!`bYou're dead, Jim!`b`0");
	}
	addnav("S?Land of Shades","shades.php");
	addnav("G?The Graveyard","graveyard.php");
	require_once("lib/extended-battle.php");
	suspend_companions("allowinshades", true);
	addnav("Log out","login.php?op=logout");
	tlschema();
}
addnav("News");
addnav("Previous News","news.php?offset=".($offset+1));
if ($offset>0){
	addnav("Next News","news.php?offset=".($offset-1));
}
if ($session['user']['loggedin'])
	addnav("Preferences","prefs.php");
addnav("About this game","about.php");

tlschema("nav");
if ($session['user']['superuser'] & SU_EDIT_COMMENTS){
	addnav("Superuser");
	addnav(",?Comment Moderation","moderate.php");
}
if ($session['user']['superuser']&~SU_DOESNT_GIVE_GROTTO){
	addnav("Superuser");
	addnav("X?Superuser Grotto","superuser.php");
}
if ($session['user']['superuser'] & SU_INFINITE_DAYS){
	addnav("Superuser");
	addnav("/?New Day","newday.php");
}
tlschema();

addnav("","news.php");
if ($totaltoday>$newsperpage){
	addnav("Today's news");
	for ($i=0;$i<$totaltoday;$i+=$newsperpage){
		$pnum = $i/$newsperpage+1;
		if ($pnum == $page) {
			addnav(array("`b`#Page %s`0`b", $pnum),"news.php?offset=$offset&page=$pnum");
		} else {
			addnav(array("Page %s", $pnum),"news.php?offset=$offset&page=$pnum");
		}
	}
}

page_footer();
?>