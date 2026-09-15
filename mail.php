<?php
// translator ready
// addnews ready
// mail ready
define("OVERRIDE_FORCED_NAV",true);
require_once("common.php");
require_once("lib/http.php");

tlschema("mail");

$superusermessage = getsetting("superuseryommessage","Asking an admin for gems, gold, weapons, armor, or anything else which you have not earned will not be honored.  If you are experiencing problems with the game, please use the 'Petition for Help' link instead of contacting an admin directly.");

$op = httpget('op');
$id = (int)httpget('id');
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

if($op=="send"){
	require("lib/mail/case_send.php");
}

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