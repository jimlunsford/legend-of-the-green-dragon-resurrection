<?php
// translator ready
// addnews ready
// mail ready
define("OVERRIDE_FORCED_NAV",true);
require_once("common.php");
require_once("lib/http.php");

tlschema("mail");

require_once 'lib/player_mail.php';
if (empty($session['loggedin']) || empty($session['user']['acctid'])) {
    http_response_code(403); exit('Mail requires authentication.');
}
$op = httpget('op');
$id = (int)httpget('id');
try {
    if (in_array($op, ['address','write','send'], true)) resurrection_mail_transport($op);
    if ($op === 'write') $mailDraft = resurrection_mail_draft();
    if ($op === 'send') require 'lib/mail/case_send.php';
} catch (InvalidArgumentException $error) {
    http_response_code(400); exit('Invalid mail request.');
} catch (DomainException $error) {
    http_response_code(409); exit('Mail unavailable or form expired. Please reopen the address form.');
} catch (Throwable $error) {
    http_response_code(500); exit('Mail could not be committed. Please open a fresh form.');
}

if (in_array($op, ['del', 'process', 'unread'], true)) {
    require_once 'lib/mail_security.php';
    try {
        resurrection_mutate_mailbox($session, $_SESSION, $_SERVER['REQUEST_METHOD'] ?? '', $_POST, $op);
    } catch (DomainException $error) {
        http_response_code(403); exit('Invalid mailbox submission.');
    } catch (InvalidArgumentException $error) {
        http_response_code(400); exit('Invalid message selection.');
    }
    header('Location: mail.php', true, 303);
    exit();
}

popup_header("Ye Olde Poste Office");
$inbox = translate_inline("Inbox");
$write = translate_inline("Write");

// Build the initial args array
$args = array();
array_push($args, array("mail.php", $inbox));
array_push($args, array("mail.php?op=address",$write));
// to use this hook,
// just call array_push($args, array("pagename", "functionname"));,
// where "pagename" is the name of the page to forward the user to,
// and "functionname" is the name of the mail function to add
$mailfunctions = modulehook("mailfunctions", $args);

if (resurrection_ui()) {
	// Phase 5 task 11: Inbox and Write (and any module's mail function) as tabs, real links; the current one
	// aria-selected. Reading a message belongs to the Inbox, writing to Write.
	$currentmailpage = in_array($op, ['address', 'write'], true) ? 'mail.php?op=address' : 'mail.php';
	rawoutput("<nav class='rz-mailtabs' aria-label='" . htmlspecialchars(translate_inline("Mail"), ENT_QUOTES, 'UTF-8') . "'><ul role='tablist'>");
	foreach ($mailfunctions as $mailfunction) {
		if (!is_array($mailfunction) || count($mailfunction) != 2) continue;
		$selected = $mailfunction[0] === $currentmailpage ? 'true' : 'false';
		rawoutput("<li role='presentation'><a href='{$mailfunction[0]}' class='button rz-tab-button' role='tab' aria-selected='$selected'>{$mailfunction[1]}</a></li>");
	}
	rawoutput("</ul></nav>");
} else {
rawoutput("<table width='50%' border='0' cellpadding='0' cellspacing='2'>");
rawoutput("<tr>");
$count_mailfunctions = count($mailfunctions);
for($i=0;$i<$count_mailfunctions;++$i) {
	if (is_array($mailfunctions[$i])) {
		if (count($mailfunctions[$i])==2) {
			$page = $mailfunctions[$i][0];
			$name = $mailfunctions[$i][1]; // already translated
			rawoutput("<td><a href='$page' class='motd'>$name</a></td>");
			// No need for addnav since mail function pages are (or should be) outside the page nav system.
		}
	}
}
rawoutput("</tr></table>");
output_notl("`n`n");
}


if (!empty($mailSent)) output('Your message was sent!`n');
switch ($op) {
case "read":
	require("lib/mail/case_read.php");
	break;
case "address":
	require("lib/mail/case_address.php");
	break;
case "write":
	require("lib/mail/case_write.php");
	break;
default:
	require("lib/mail/case_default.php");
	break;
}
popup_footer();
?>