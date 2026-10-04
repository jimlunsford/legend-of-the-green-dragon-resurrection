<?php
require_once __DIR__ . '/../../src/Security/MailContent.php';
output("`b`iMail Box`i`b");
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
	$no_subject = translate_inline("`i(No Subject)`i");
	rawoutput("<form action='mail.php?op=process' method='post'>");
    rawoutput(resurrection_csrf_field());
    rawoutput('<table>');
	while($row = db_fetch_assoc($result)){
		rawoutput("<tr>");
		rawoutput("<td nowrap><input type='checkbox' name='msg[]' value='{$row['messageid']}'>");
		rawoutput("<img src='images/".($row['seen']?"old":"new")."scroll.GIF' width='16px' height='16px' alt='".($row['seen']?"Old":"New")."'></td>");
		rawoutput("<td>");
		if ((string)$row['msgfrom'] === '0') {
		    $row['name'] = translate_inline('`i`^System`0`i');
		    foreach (['subject'] as $field) {
		        $payload = \Resurrection\Security\MailContent::stored($row[$field]);
		        if ($payload !== null) $row[$field] = call_user_func_array('sprintf_translate', $payload);
		    }
		} elseif (!ctype_digit((string)$row['msgfrom'])) {
		    $row['name'] = $row['msgfrom'];
		}

		// In one line so the Translator doesn't screw the Html up
		rawoutput("<a href='mail.php?op=read&id={$row['messageid']}'>");
        output_notl('%s', trim($row['subject']) ? $row['subject'] : $no_subject);
        rawoutput('</a>');
		rawoutput("</td><td><a href='mail.php?op=read&id={$row['messageid']}'>");
		output_notl($row['name']);
		rawoutput("</a></td><td><a href='mail.php?op=read&id={$row['messageid']}'>".date("M d, h:i a",strtotime($row['sent']))."</a></td>");
		rawoutput("</tr>");
	}
	rawoutput("</table>");
	$checkall = htmlentities(translate_inline("Check All"), ENT_COMPAT, getsetting("charset", "ISO-8859-1"));
	rawoutput("<input type='button' value=\"$checkall\" class='button' onClick='
		var elements = document.getElementsByName(\"msg[]\");
		for(i = 0; i < elements.length; i++) {
			elements[i].checked = true;
		}
	'>");
	$delchecked = htmlentities(translate_inline("Delete Checked"), ENT_COMPAT, getsetting("charset", "ISO-8859-1"));
	rawoutput("<input type='submit' class='button' value=\"$delchecked\">");
	rawoutput("</form>");
}else{
	output("`iAww, you have no mail, how sad.`i");
}
if (db_num_rows($result) == 1) {
	output("`n`n`iYou currently have 1 message in your inbox.`nYou will no longer be able to receive messages from players if you have more than %s unread messages in your inbox.  `nMessages are automatically deleted (read or unread) after %s days.",getsetting('inboxlimit',50),getsetting("oldmail",14));
} else {
	output("`n`n`iYou currently have %s messages in your inbox.`nYou will no longer be able to receive messages from players if you have more than %s unread messages in your inbox.  `nMessages are automatically deleted (read or unread) after %s days.",db_num_rows($result),getsetting('inboxlimit',50),getsetting("oldmail",14));
}
?>