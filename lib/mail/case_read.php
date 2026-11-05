<?php
require_once __DIR__ . '/../../src/Security/MailContent.php';
$mail = db_prefix('mail');
$accounts = db_prefix('accounts');
$sql = "SELECT $mail.*, $accounts.name FROM $mail LEFT JOIN $accounts ON $accounts.acctid=$mail.msgfrom WHERE msgto=? AND messageid=?";
$result = db_query($sql,true,[$session['user']['acctid'],$id]);
if (db_num_rows($result)>0){
	$row = db_fetch_assoc($result);
	if ((string)$row['msgfrom'] === '0') {
	    $row['name'] = translate_inline('`i`^System`0`i');
	    foreach (['subject','body'] as $field) {
	        $payload = \Resurrection\Security\MailContent::stored($row[$field]);
	        if ($payload !== null) $row[$field] = call_user_func_array('sprintf_translate', $payload);
	    }
	} elseif (!ctype_digit((string)$row['msgfrom'])) {
	    $row['name'] = $row['msgfrom'];
	}

	// Phase 5 task 11: the read view (lib/mail_view.php). The same message is marked read, with the same actions and the
	// same Previous and Next. (1.1.2's From, Subject and Sent lines between scroll images, and its table of actions,
	// were kept for the 1.1.2 skins until Phase 5 task 15.)
	require_once __DIR__ . '/../mail_view.php';
	db_query("UPDATE " . db_prefix("mail") . " SET seen=1 WHERE msgto=? AND messageid=?",true,[$session['user']['acctid'],$id]);
	invalidatedatacache("mail-{$session['user']['acctid']}");
	$problem = "Abusive Email Report:\nFrom: {$row['name']}\nSubject: {$row['subject']}\nSent: {$row['sent']}\nID: {$row['messageid']}\nBody:\n{$row['body']}";
	$near = [];
	foreach (['pid' => "messageid < ? ORDER BY messageid DESC", 'nid' => "messageid > ? ORDER BY messageid"] as $key => $where) {
		$nearresult = db_query("SELECT messageid FROM $mail WHERE msgto=? AND $where LIMIT 1", true, [(int)$session['user']['acctid'], (int)$id]);
		$nearrow = db_fetch_assoc($nearresult);
		$near[$key] = $nearrow ? (int)$nearrow['messageid'] : 0;
	}
	resurrection_mail_read($row, $problem, $near['pid'], $near['nid']);
}else{
	output("Eek, no such message was found!");
}
?>