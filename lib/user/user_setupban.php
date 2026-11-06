<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once("lib/ban_admin.php");
// "Add a ban" opens the form with no account; 1.1.2 read the missing row as empty.
$row = resurrection_admin_account($userid, 'name,lastip,uniqueid') ?? ['name' => '', 'lastip' => '', 'uniqueid' => ''];
if ($row['name']!="")
	output("Setting up ban information based on `\$%s`0", $row['name']);
rawoutput("<form action='user.php?op=saveban' method='POST'>" . resurrection_ban_fields());
output("Set up a new ban by IP or by ID (recommended IP, though if you have several different users behind a NAT, you can try ID which is easily defeated)`n");
rawoutput("<input type='radio' value='ip' id='ipradio' name='type' checked>" . resurrection_label_for('ipradio', "Ban by IP"));
resurrection_label_open('ip');
output("IP: ");
resurrection_label_close();
rawoutput("<input name='ip' id='ip' value=\"".HTMLEntities((string)$row['lastip'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
output_notl("`n");
rawoutput("<input type='radio' value='id' name='type'".resurrection_control_id('ban-type-id').">" . resurrection_label_for('ban-type-id', "Ban by ID"));
resurrection_label_open('ban-id');
output("ID: ");
resurrection_label_close();
rawoutput("<input name='id'".resurrection_control_id('ban-id')." value=\"".HTMLEntities((string)$row['uniqueid'], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
resurrection_label_open('duration');
output("`nDuration: ");
resurrection_label_close();
rawoutput("<input name='duration' id='duration' size='3' value='14'>");
output("Days (0 for permanent)`n");
$reason = httpget("reason");
if (!is_string($reason) || $reason == "")
	$reason=translate_inline("Don't mess with me.");
resurrection_label_open('ban-reason');
output("Reason for the ban: ");
resurrection_label_close();
rawoutput("<input name='reason'".resurrection_control_id('ban-reason')." size=50 value=\"" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "\">");
output_notl("`n");
$pban = translate_inline("Post ban");
$conf = translate_inline("Are you sure you wish to issue a permanent ban?");
rawoutput("<input type='submit' class='button' value='$pban'".resurrection_confirm_attribute($conf)." data-rz-confirm-when-zero='duration'>");
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
			rawoutput("<a href='#' data-rz-fill='ip' data-rz-fill-value=\"" . resurrection_attribute_value($thisip) . "\" data-rz-fill-check='ipradio'>");
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