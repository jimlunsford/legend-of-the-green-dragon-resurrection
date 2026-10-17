<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/admin_authority.php");

tlschema("bio");
check_su_access(SU_EDIT_COMMENTS);

try {
	$op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'block', 'unblock'], '');
	$userid = \Resurrection\Http\Input::integer($_GET, 'userid', 0, 1);
	if ($userid > RESURRECTION_ADMIN_MAX_ID || ($op !== '' && $userid === 0)) throw new InvalidArgumentException('Invalid account.');
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bio request.'); }
// 1.1.2 blocked and unblocked on these GET links, mailing before an unchecked UPDATE. Only the protected
// POST forms below act; the notice and the account write now commit together.
if ($op != "") resurrection_bio_moderate($op, $userid);
$sql = "SELECT name,acctid,bio,biotime FROM " . db_prefix("accounts") . " WHERE biotime<'9999-12-31' AND bio>'' ORDER BY biotime DESC LIMIT 100";
$result = db_query($sql);
page_header("User Bios");
$block = translate_inline("Block");
output("`b`&Player Bios:`0`b`n");
$number=db_num_rows($result);
for ($i=0;$i<$number;$i++){
	$row = db_fetch_assoc($result);
	if ($row['biotime']>$session['user']['recentcomments'])
		rawoutput("<img src='images/new.gif' alt='&gt;' width='3' height='5' align='absmiddle'> ");
	resurrection_bio_form('block', $row, $block);
	output_notl("`&%s`0: `^%s`0`n", $row['name'], soap($row['bio']));
}
db_free_result($result);
require_once("lib/superusernav.php");
superusernav();

addnav("Moderation");

if ($session['user']['superuser'] & SU_EDIT_COMMENTS)
	addnav("Return to Comment Moderation","moderate.php");

addnav("Refresh","bios.php");
$sql = "SELECT name,acctid,bio,biotime FROM " . db_prefix("accounts") . " WHERE biotime>'9000-01-01' AND bio>'' ORDER BY biotime DESC LIMIT 100";
$result = db_query($sql);
output("`n`n`b`&Blocked Bios:`0`b`n");
$unblock = translate_inline("Unblock");
$number=db_num_rows($result);
for ($i=0;$i<$number;$i++){
	$row = db_fetch_assoc($result);

	resurrection_bio_form('unblock', $row, $unblock);
	output_notl("`&%s`0: `^%s`0`n", $row['name'], soap($row['bio']));
}
db_free_result($result);
page_footer();
?>