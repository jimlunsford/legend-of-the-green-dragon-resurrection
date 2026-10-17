<?php
// translator ready
// addnews ready
// mail ready
define("ALLOW_ANONYMOUS",true);
require_once("common.php");
require_once("lib/http.php");

tlschema("referral");

if ($session['user']['loggedin']){
	page_header("Referral Page");
	if (file_exists("lodge.php")) {
		addnav("L?Return to the Lodge","lodge.php");
	} else {
		require_once("lib/villagenav.php");
		villagenav();
	}
	output("You will automatically receive %s points for each person that you refer to this website who makes it to level %s.`n`n", getsetting("refereraward", 25), getsetting("referminlevel", 4));

	// The configured URL; when it is unset, 1.1.2's request-built URL is shown to this player but never saved.
	require_once("lib/server_url.php");
	$url = resurrection_server_url() ?? resurrection_request_server_url() ?? "";

	output("How does the site know that I referred a person?`n");
	output("Easy!  When you tell your friends about this site, give out the following link:`n`n");
	output_notl("%sreferral.php?r=%s`n`n",$url,rawurlencode($session['user']['login']));
	output("If you do, the site will know that you were the one who sent them here.");
	output("When they reach level %s for the first time, you'll get your points!", getsetting("referminlevel", 4));

	$sql = "SELECT name,level,refererawarded FROM " . db_prefix("accounts") . " WHERE referer={$session['user']['acctid']} ORDER BY dragonkills,level";
	$result = db_query($sql);
	$name=translate_inline("Name");
	$level=translate_inline("Level");
	$awarded=translate_inline("Awarded?");
	$yes=translate_inline("`@Yes!`0");
	$no=translate_inline("`\$No!`0");
	$none=translate_inline("`iNone`i");
	output("`n`nAccounts which you referred:`n");
	rawoutput("<table border='0' cellpadding='3' cellspacing='0'><tr><td>$name</td><td>$level</td><td>$awarded</td></tr>");
	$number=db_num_rows($result);
	for ($i=0;$i<$number;$i++){
		$row = db_fetch_assoc($result);
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'><td>");
		output_notl($row['name']);
		rawoutput("</td><td>");
		output_notl($row['level']);
		rawoutput("</td><td>");
		output_notl($row['refererawarded']?$yes:$no);
		rawoutput("</td></tr>");
	}
	if (db_num_rows($result)==0){
		rawoutput("<tr><td colspan='3' align='center'>");
		output_notl($none);
		rawoutput("</td></tr>");
	}
	rawoutput("</table>",true);
	page_footer();
}else{
	page_header("Welcome to Legend of the Green Dragon");
	output("`@Legend of the Green Dragon is a remake of the classic BBS Door Game Legend of the Red Dragon.");
	output("Adventure into the classic realm that was one of the world's very first multiplayer roleplaying games!");
	addnav("Create a character","create.php?r=".HTMLEntities(httpget('r'), ENT_COMPAT, getsetting("charset", "ISO-8859-1")));
	addnav("Login Page","index.php");
	page_footer();
}
?>