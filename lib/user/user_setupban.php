<?php
require_once("lib/ban_admin.php");
// "Add a ban" opens the form with no account; 1.1.2 read the missing row as empty.
$row = resurrection_admin_account($userid, 'name,lastip,uniqueid') ?? ['name' => '', 'lastip' => '', 'uniqueid' => ''];
if ($row['name']!="")
	output("Setting up ban information based on `\$%s`0", $row['name']);
rawoutput("<form action='user.php?op=saveban' method='POST'>" . resurrection_ban_fields());
output("Set up a new ban by IP or by ID (recommended IP, though if you have several different users behind a NAT, you can try ID which is easily defeated)`n");
rawoutput("<input type='radio' value='ip' id='ipradio' name='type' checked>");
output("IP: ");
rawoutput("<input name='ip' id='ip' value=\"".HTMLEntities((string)$row['lastip'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output_notl("`n");
rawoutput("<input type='radio' value='id' name='type'>");
output("ID: ");
rawoutput("<input name='id' value=\"".HTMLEntities((string)$row['uniqueid'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output("`nDuration: ");
rawoutput("<input name='duration' id='duration' size='3' value='14'>");
output("Days (0 for permanent)`n");
$reason = httpget("reason");
if (!is_string($reason) || $reason == "")
	$reason=translate_inline("Don't mess with me.");
output("Reason for the ban: ");
rawoutput("<input name='reason' size=50 value=\"" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "\">");
output_notl("`n");
$pban = translate_inline("Post ban");
$conf = translate_inline("Are you sure you wish to issue a permanent ban?");
rawoutput("<input type='submit' class='button' value='$pban' onClick='if (document.getElementById(\"duration\").value==0) {return confirm(\"$conf\");} else {return true;}'>");
rawoutput("</form>");
output("For an IP ban, enter the beginning part of the IP you wish to ban if you wish to ban a range, or simply a full IP to ban a single IP`n`n");
addnav("","user.php?op=saveban");
if ($row['name']!=""){
	$id = (string)$row['uniqueid'];
	$ip = (string)$row['lastip'];
	$name = $row['name'];
	output("`0To help locate similar users to `@%s`0, here are some other users who are close:`n", $name);
	output("`bSame ID (%s):`b`n", $id);
	$sql = "SELECT name, lastip, uniqueid, laston, gentimecount FROM " . db_prefix("accounts") . " WHERE uniqueid=? ORDER BY lastip";
	$result = db_query($sql, true, [$id]);
	while ($row = db_fetch_assoc($result)){
		output("`0 (%s) `%%s`0 - %s hits, last: %s`n", $row['lastip'],
				$row['name'], $row['gentimecount'],
				reltime(strtotime($row['laston'])));
	}
	output_notl("`n");
		$oip = "";
	$dots = 0;
	output("`bSimilar IP's`b`n");
	for ($x=strlen($ip); $x>0; $x--){
		if ($dots>1) break;
		$thisip = substr($ip,0,$x);
		// The prefix walk is 1.1.2's; a stored % or _ matches only itself.
		$sql = "SELECT name, lastip, uniqueid, laston, gentimecount FROM " . db_prefix("accounts") . " WHERE lastip LIKE ? ESCAPE '|' AND NOT (lastip LIKE ? ESCAPE '|') ORDER BY uniqueid";
		$result = db_query($sql, true, [resurrection_ban_like_prefix($thisip), $oip]);
		if (db_num_rows($result)>0){
			output(" IP Filter: %s ", $thisip);
			rawoutput("<a href='#' onClick=\"document.getElementById('ip').value=" . resurrection_ban_js($thisip) . "; document.getElementById('ipradio').checked = true; return false\">");
			output("Use this filter");
			rawoutput("</a>");
			output_notl("`n");
			while ($row=db_fetch_assoc($result)){
				output("&nbsp;&nbsp;",true);
				output(" (%s) [%s] `%%s`0 - %s hits, last: %s`n",
						$row['lastip'], $row['uniqueid'], $row['name'],
						$row['gentimecount'],
						reltime(strtotime($row['laston'])));
			}
			output_notl("`n");
		}
		if (substr($ip,$x-1,1)==".") {
			$x--;
			$dots++;
		}
		$oip = resurrection_ban_like_prefix($thisip);
	}
}
?>