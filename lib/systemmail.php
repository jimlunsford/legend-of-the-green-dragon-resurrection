<?php
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Security/MailContent.php';
require_once __DIR__ . '/../src/Security/GameMasterMailSender.php';
require_once 'lib/is_email.php';
require_once 'lib/sanitize.php';

/** Trusted internal API. Routes must establish actor/recipient authority before calling.
 * Missing recipients return false without inserting or notifying. Other invalid inputs reject.
 * A caller-owned transaction MUST supply the explicit post-commit notification queue.
 */
function systemmail($to, $subject, $body, $from=0, $noemail=false) {
    global $session, $dbinfo;
    $to = \Resurrection\Security\MailContent::id($to);
    $gm = $from instanceof \Resurrection\Security\GameMasterMailSender;
    $sender = $gm ? $from->actor : \Resurrection\Security\MailContent::id($from, true);
    $connection = $dbinfo['connection'];
    $ownTransaction = !$connection->inTransaction();
    if (!$ownTransaction && !isset($GLOBALS['mail_notifications'])) throw new LogicException('Mail requires post-commit notification ownership.');
    if ($ownTransaction) $connection->beginTransaction();
    try {
        $ids = array_unique(array_filter([$to, $sender])); sort($ids, SORT_NUMERIC);
        $accounts = [];
        foreach ($ids as $id) {
            $rows = db_query('SELECT acctid,login,name,superuser,locked,prefs,emailaddress FROM '.db_prefix('accounts').' WHERE acctid=? FOR UPDATE', true, [$id]);
            if (count($rows) === 1) $accounts[$id] = $rows[0];
        }
        if (!isset($accounts[$to])) {
            if ($ownTransaction) $connection->rollBack();
            return false;
        }
        if ($sender > 0 && !isset($accounts[$sender])) throw new DomainException('Mail sender no longer exists.');
        if ($gm) {
            $from = \Resurrection\Security\GameMasterMailSender::resolve($accounts[$sender], $from->label)->label;
        } else { $from = $sender; }
        $row = $accounts[$to];
        $prefs = \Resurrection\Security\ScalarState::read($row['prefs']);
        if (!is_array($prefs)) $prefs = [];
        $serialized = 0;
        if ($sender === 0) {
            if (is_array($subject)) { $subject = serialize(\Resurrection\Security\MailContent::translated($subject)); $serialized |= 1; }
            if (is_array($body)) { $body = serialize(\Resurrection\Security\MailContent::translated($body)); $serialized |= 2; }
            $subject = \Resurrection\Security\MailContent::text($subject);
            $body = \Resurrection\Security\MailContent::text($body);
        } else {
            $subject = \Resurrection\Security\MailContent::subject($subject);
            $body = \Resurrection\Security\MailContent::text($body);
            if (empty($prefs['dirtyemail'])) { $subject = soap($subject, false, 'mail'); $body = soap($body, false, 'mail'); }
        }
        if (mb_strlen($subject, 'UTF-8') > 255 || strlen($body) > 65535) throw new DomainException('Mail exceeds storage capacity.');
        db_query('INSERT INTO '.db_prefix('mail').' (msgfrom,msgto,subject,body,sent,originator) VALUES (?,?,?,?,?,?)', true,
            [(string)$from,$to,$subject,$body,date('Y-m-d H:i:s'),(int)($session['user']['acctid'] ?? 0)]);
        $notification = [$to,$subject,$body,$from,$noemail,$row,$serialized,$prefs];
        if ($ownTransaction) $connection->commit();
        else $GLOBALS['mail_notifications'][] = $notification;
    } catch (Throwable $error) {
        if ($ownTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    invalidatedatacache('mail-'.$to);
    if ($ownTransaction) resurrection_systemmail_notification(...$notification);
    return true;
}

// External delivery is best effort after commit, never authority to repeat an INSERT.
function resurrection_systemmail_notification(...$notification) {
    try { return resurrection_systemmail_deliver(...$notification); }
    catch (Throwable $error) { error_log('Mail notification failed after game mail commit.'); return false; }
}

// Shared by player mail, PvP and Training after their owning transaction commits.
function resurrection_systemmail_deliver($to,$subject,$body,$from,$noemail,$row,$serialized,$prefs) {
	$email=false;
	if (isset($prefs['emailonmail']) && $prefs['emailonmail'] && (string)$from !== '0'){
		$email=true;
	}elseif(isset($prefs['emailonmail']) && $prefs['emailonmail'] &&
			(string)$from === '0' && isset($prefs['systemmail']) && $prefs['systemmail']){
		$email=true;
	}
	$emailadd = "";
	if (isset($row['emailaddress'])) $emailadd = $row['emailaddress'];

	if (filter_var($emailadd, FILTER_VALIDATE_EMAIL) === false) $email=false;
	if ($email && !$noemail){
		if ($serialized&2){
			$body = \Resurrection\Security\MailContent::translated(\Resurrection\Security\ScalarState::read($body));
			$body = translate_mail($body,$to);
		}
		if ($serialized&1){
			$subject = \Resurrection\Security\MailContent::translated(\Resurrection\Security\ScalarState::read($subject));
			$subject = translate_mail($subject,$to);
		}

		$sql = "SELECT name FROM " . db_prefix("accounts") . " WHERE acctid=?";
		$result = db_query($sql,true,[(int)$from]);
		$row1=db_fetch_assoc($result);
		db_free_result($result);
		if (!empty($row1['name']))
			$fromline=full_sanitize($row1['name']);
		elseif (!is_numeric($from))
            $fromline=full_sanitize($from);
        else
			$fromline=translate_inline("The Green Dragon","mail");

		$sql = "SELECT name FROM " . db_prefix("accounts") . " WHERE acctid=?";
		$result = db_query($sql,true,[(int)$to]);
		$row1=db_fetch_assoc($result);
		db_free_result($result);
		$toline = full_sanitize($row1['name'] ?? $row['name']);

		// We've inserted it into the database, so.. strip out any formatting
		// codes from the actual email we send out... they make things
		// unreadable
		$body = preg_replace("'[`]n'", "\n", $body);
		$body = full_sanitize($body);
		$subject = htmlentities($subject, ENT_COMPAT, getsetting("charset", "ISO-8859-1"));
		$mailsubj = translate_mail(array("New LoGD Mail (%s)", $subject),$to);
		$mailbody = translate_mail(array("You have received new mail on LoGD at http://%s`n`n"
			."-=-=-=-=-=-=-=-=-=-=-=-=-=-`n"
			."From: %s`n"
			."To: %s`n"
			."Subject: %s`n"
			."Body: `n%s`n"
			."-=-=-=-=-=-=-=-=-=-=-=-=-=-"
			."`nDo not respond directly to this email, it was sent from the game email address, and not the email address of the person who sent you the "
			."message.  If you wish to respond, log into Legend of the Green Dragon at http://%s .`n`n"
			."You may turn off these alerts in your preferences page, available from the village square.",
			$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']),
			$fromline,
			$toline,
			full_sanitize($subject),
			$body,
			$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME'])
		),$to);
		$delivered = @mail($row['emailaddress'],$mailsubj,str_replace("`n","\n",$mailbody),"From: ".getsetting("gameadminemail","postmaster@localhost"));
        if (!$delivered) error_log('Mail notification failed after game mail commit.');
        return $delivered;
	}
	invalidatedatacache("mail-$to");
    return true;
}
