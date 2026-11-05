<?php
require_once __DIR__ . '/../../src/Security/MailContent.php';
// Phase 5 task 11: the inbox is a list (resurrection_mail_inbox(), lib/mail_view.php) and prints no italics. (1.1.2's
// table, with its scroll images and Check All script, was kept for the 1.1.2 skins until Phase 5 task 15.)
require_once __DIR__ . '/../mail_view.php';
rawoutput("<h2 class='rz-mail-heading'>" . resurrection_mail_plain(translate_inline("`b`iMail Box`i`b")) . "</h2>");
if (isset($session['message'])) {
	output($session['message']);
}
$session['message']="";
$mail = db_prefix("mail");
$accounts = db_prefix("accounts");
$sql = "SELECT subject,messageid,$accounts.name,msgfrom,seen,sent FROM $mail LEFT JOIN $accounts ON $accounts.acctid=$mail.msgfrom WHERE msgto=\"".$session['user']['acctid']."\" ORDER BY seen ASC, sent DESC";
$result = db_query($sql);
$db_num_rows = db_num_rows($result);
if ($db_num_rows>0){
	resurrection_mail_inbox($result);
}else{
	rawoutput("<p class='rz-mail-empty'>" . resurrection_mail_plain(translate_inline("`iAww, you have no mail, how sad.`i")) . "</p>");
}
// The note opens `i and never closes it, so in 1.1.2 everything after it, down to the end of the page, was italic (only
// popup_footer()'s tag closer ended it). The same translated note is a plain muted paragraph (Phase 5 task 11,
// docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md).
if (db_num_rows($result) == 1) {
	$notefmt = "`n`n`iYou currently have 1 message in your inbox.`nYou will no longer be able to receive messages from players if you have more than %s unread messages in your inbox.  `nMessages are automatically deleted (read or unread) after %s days.";
	$noteargs = [getsetting('inboxlimit',50),getsetting("oldmail",14)];
} else {
	$notefmt = "`n`n`iYou currently have %s messages in your inbox.`nYou will no longer be able to receive messages from players if you have more than %s unread messages in your inbox.  `nMessages are automatically deleted (read or unread) after %s days.";
	$noteargs = [db_num_rows($result),getsetting('inboxlimit',50),getsetting("oldmail",14)];
}
rawoutput("<p class='rz-mail-note'>" . resurrection_mail_plain(sprintf_translate($notefmt, ...$noteargs), true) . "</p>");
?>