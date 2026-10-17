<?php
/** Player mail authority. No gameplay mutation is delegated to request fields. */
require_once __DIR__.'/player_mutation.php';
require_once __DIR__.'/systemmail.php';

/** @return array<string,mixed> */
function resurrection_mail_account(mixed $id, bool $lock = false): array {
    $id = \Resurrection\Security\MailContent::id($id);
    $rows = db_query('SELECT acctid,login,name,superuser,locked,authversion,regdate,prefs,emailaddress FROM '.db_prefix('accounts').' WHERE acctid=?'.($lock ? ' FOR UPDATE' : ''), true, [$id]);
    if (count($rows) !== 1 || (int)$rows[0]['locked'] !== 0) throw new DomainException('Mail account unavailable.');
    return $rows[0];
}

/** @return array<string,int|string> */
function resurrection_mail_settings(bool $lock = false): array {
    // Query authoritative values directly; GET lookup never persists missing defaults.
    $defaults = ['mailsizelimit'=>'1024','inboxlimit'=>'50','onlyunreadmails'=>'1',
        'superuseryommessage'=>'Asking an admin for gems, gold, weapons, armor, or anything else which you have not earned will not be honored.  If you are experiencing problems with the game, please use the Petition for Help link instead of contacting an admin directly.'];
    foreach (db_query('SELECT setting,value FROM '.db_prefix('settings').' WHERE setting IN (?,?,?,?) ORDER BY setting'.($lock ? ' LOCK IN SHARE MODE' : ''), true, array_keys($defaults)) as $row) {
        $defaults[$row['setting']] = $row['value'];
    }
    foreach (['mailsizelimit'=>65535,'inboxlimit'=>10000,'onlyunreadmails'=>1] as $key=>$max) {
        $value = \Resurrection\Security\MailContent::id($defaults[$key], true);
        if ($value > $max || ($key !== 'onlyunreadmails' && $value < 1)) throw new DomainException('Invalid mail configuration.');
        $defaults[$key] = $value;
    }
    \Resurrection\Security\MailContent::text($defaults['superuseryommessage']);
    return $defaults;
}

/** @return array<string,mixed> */
function resurrection_mail_reply(mixed $id, int $owner, bool $lock = false): array {
    $id = \Resurrection\Security\MailContent::id($id);
    $rows = db_query('SELECT messageid,msgfrom,msgto,subject,body,sent FROM '.db_prefix('mail').' WHERE messageid=? AND msgto=?'.($lock ? ' FOR UPDATE' : ''), true, [$id,$owner]);
    if (count($rows) !== 1) throw new DomainException('Reply source unavailable.');
    \Resurrection\Security\MailContent::id($rows[0]['msgfrom']);
    return $rows[0];
}

/** @param array<string,mixed> $draft */
function resurrection_mail_context(array $draft): string {
    return hash('sha256', json_encode($draft, JSON_THROW_ON_ERROR));
}

function resurrection_mail_transport(string $op): void {
    $get = match ($op) {
        'address'=>['op','prepop'], 'write'=>['op','to','replyto','subject','body'], 'send'=>['op'], default=>[]
    };
    \Resurrection\Security\MailContent::transport($_SERVER['QUERY_STRING'] ?? '', $_GET, $get);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET','POST'], true)) throw new InvalidArgumentException('Invalid mail method.');
    $post = match ($op) { 'write'=>['to','from','subject'], 'send'=>['to','subject','body','csrf_token','action_token'], default=>[] };
    if ($method === 'POST' && strtolower(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]) !== 'application/x-www-form-urlencoded') throw new InvalidArgumentException('Invalid mail encoding.');
    $raw = file_get_contents('php://input');
    if (!is_string($raw)) throw new InvalidArgumentException('Unavailable mail transport.');
    \Resurrection\Security\MailContent::transport($raw, $_POST, $method === 'POST' ? $post : []);
    if ($op === 'write' && ((isset($_GET['replyto']) && (count($_GET) !== 2 || $_POST !== [])) || (isset($_GET['to']) && isset($_POST['to'])))) throw new InvalidArgumentException('Ambiguous recipient.');
}

/** Lookup-only form construction, exact login first, literal subsequence name search second.
 * @return array<string,mixed>
 */
function resurrection_mail_draft(): array {
    global $session;
    $actor = resurrection_mail_account($session['user']['acctid']);
    $settings = resurrection_mail_settings();
    $reply = null; $candidates = [];
    if (isset($_GET['replyto'])) {
        $reply = resurrection_mail_reply($_GET['replyto'], (int)$actor['acctid']);
        $candidates[] = resurrection_mail_account($reply['msgfrom']);
    } else {
        $to = \Resurrection\Security\MailContent::text($_GET['to'] ?? $_POST['to'] ?? '');
        if (mb_strlen($to, 'UTF-8') > 255 || $to === '') throw new DomainException('Enter a recipient.');
        $rows = db_query('SELECT acctid FROM '.db_prefix('accounts').' WHERE login=? AND locked=0', true, [$to]);
        if (count($rows) === 0 && !isset($_GET['to'])) {
            $pattern = '%';
            foreach (mb_str_split($to, 1, 'UTF-8') as $char) $pattern .= str_replace(['!','%','_'], ['!!','!%','!_'], $char).'%';
            $rows = db_query("SELECT acctid FROM ".db_prefix('accounts')." WHERE name LIKE ? ESCAPE '!' AND locked=0 ORDER BY login=? DESC,name=? DESC,login LIMIT 101", true, [$pattern,$to,$to]);
        }
        if (count($rows) > 100) throw new DomainException('Too many matches. Narrow your search.');
        foreach ($rows as $row) $candidates[] = resurrection_mail_account($row['acctid']);
    }
    $label = '';
    if (array_key_exists('from', $_POST)) {
        if (((int)$actor['superuser'] & SU_IS_GAMEMASTER) === 0) throw new DomainException('Game Master authority required.');
        $label = trim(\Resurrection\Security\MailContent::text($_POST['from']));
        if ($label !== '') $label = \Resurrection\Security\GameMasterMailSender::resolve($actor, $label)->label;
    }
    // Complete candidate identities live only in the authenticated server session.
    unset($actor['prefs'], $actor['emailaddress']);
    return ['actor'=>$actor, 'candidates'=>$candidates, 'reply'=>$reply, 'settings'=>$settings, 'sender_label'=>$label];
}

/** Consumes before settlement; failure cannot resurrect the intent. */
function resurrection_mail_send(): int {
    global $session, $dbinfo;
    resurrection_require_post();
    $draft = $_SESSION['player_mail_draft'] ?? null;
    if (!is_array($draft)) throw new DomainException('Expired mail form.');
    \Resurrection\Security\ActionToken::consume($_SESSION, 'player-mail', resurrection_mail_context($draft), $_POST['action_token'] ?? null);
    unset($_SESSION['player_mail_draft']);
    if (array_diff(['to','subject','body','csrf_token','action_token'], array_keys($_POST)) !== []) throw new InvalidArgumentException('Incomplete mail form.');
    $recipient = null;
    foreach ($draft['candidates'] as $candidate) if ($candidate['login'] === $_POST['to']) $recipient = $candidate;
    if ($recipient === null) throw new DomainException('Recipient was not offered.');
    $connection = $dbinfo['connection'];
    if ($connection->inTransaction() || isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested mail settlement.');
    $GLOBALS['mail_notifications'] = [];
    $connection->beginTransaction();
    try {
        $ids = array_unique([(int)$session['user']['acctid'], (int)$recipient['acctid']]); sort($ids, SORT_NUMERIC);
        $locked = [];
        foreach ($ids as $account) $locked[$account] = resurrection_mail_account($account, true);
        $actor = $locked[(int)$session['user']['acctid']];
        unset($actor['prefs'], $actor['emailaddress']);
        if ($actor !== $draft['actor']) throw new DomainException('Mail actor changed.');
        if ($locked[(int)$recipient['acctid']] !== $recipient) throw new DomainException('Mail recipient changed.');
        if (resurrection_mail_settings(true) !== $draft['settings']) throw new DomainException('Mail settings changed.');
        if ($draft['reply'] !== null && resurrection_mail_reply($draft['reply']['messageid'], (int)$actor['acctid'], true) !== $draft['reply']) throw new DomainException('Reply source changed.');
        $sender = $draft['sender_label'] === '' ? (int)$actor['acctid'] : \Resurrection\Security\GameMasterMailSender::resolve($actor, $draft['sender_label']);
        // The shared recipient account lock serializes independent senders. Current locking
        // read avoids a repeatable-read snapshot taken before the contender committed.
        $rows = db_query('SELECT messageid FROM '.db_prefix('mail').' WHERE msgto=?'.($draft['settings']['onlyunreadmails'] ? ' AND seen=0' : '').' FOR UPDATE', true, [(int)$recipient['acctid']]);
        if (count($rows) >= $draft['settings']['inboxlimit']) throw new DomainException('Mailbox full.');
        $subject = \Resurrection\Security\MailContent::subject($_POST['subject']);
        $body = \Resurrection\Security\MailContent::body($_POST['body'], $draft['settings']['mailsizelimit']);
        if (!systemmail($recipient['acctid'], $subject, $body, $sender)) throw new DomainException('Recipient unavailable.');
        $connection->commit();
        $notifications = $GLOBALS['mail_notifications'];
    } catch (Throwable $error) {
        resurrection_rollback_player_transaction($connection);
        throw $error;
    } finally { unset($GLOBALS['mail_notifications']); }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return (int)($draft['reply']['messageid'] ?? 0);
}
