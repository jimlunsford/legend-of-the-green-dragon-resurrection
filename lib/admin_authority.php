<?php
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/systemmail.php';
require_once __DIR__ . '/debuglog.php';
require_once __DIR__ . '/bank_authority.php';

const RESURRECTION_ADMIN_MAX_ID = 4294967295;
/** accounts.donation is int(11) unsigned. */
const RESURRECTION_DONATION_MAX_BALANCE = 4294967295;
/** debuglog.value is float(9,2): every logged points value must fit it. */
const RESURRECTION_DONATION_MAX_POINTS = 9999999;
/** The 1.1.2 block marker and its far-future biotime sentinel. */
const RESURRECTION_BIO_BLOCKED = '`iBlocked for inappropriate usage`i';
const RESURRECTION_BIO_BLOCKED_TIME = '9999-12-31 23:59:59';

/**
 * One locked player transaction that also locks the target account and owns the systemmail
 * notification queue: notices are written with the transaction and external delivery runs after commit.
 */
function resurrection_admin_mutation(callable $action, int $target): mixed {
    if (isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested admin mutation.');
    $GLOBALS['mail_notifications'] = [];
    try {
        $result = resurrection_player_mutation($action, [$target]);
        $notifications = $GLOBALS['mail_notifications'];
    } finally { unset($GLOBALS['mail_notifications']); }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return $result;
}

function resurrection_admin_failure(Throwable $error, string $page): never {
    if ($error instanceof InvalidArgumentException) { http_response_code(400); exit('Invalid request.'); }
    if ($error instanceof OutOfBoundsException) { http_response_code(404); exit('Account not found.'); }
    if ($error instanceof DomainException) { http_response_code(409); exit("This changed. Reload $page before acting."); }
    http_response_code(500); exit("Nothing was changed. Reload $page and try again.");
}

/**
 * An action POST carries only the protected fields and the named form values.
 * @param list<string> $fields
 */
function resurrection_admin_fields(array $fields = []): void {
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', ...$fields]) !== []) throw new InvalidArgumentException('Unexpected field.');
}

/** @return array<string, mixed>|null */
function resurrection_admin_account(int $id, string $columns): ?array {
    $rows = db_query('SELECT ' . $columns . ' FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$id]);
    return count($rows) === 1 ? $rows[0] : null;
}

/**
 * prefs.php's rule: a biotime past 9000-01-01 marks a blocked bio. NULL (never set, or unblocked) is not blocked.
 * @param array<string, mixed> $row
 */
function resurrection_bio_blocked(array $row): bool {
    return $row['biotime'] !== null && (string)$row['biotime'] > '9000-01-01';
}

/**
 * A block or unblock form is bound to the account and the bio state it was shown with.
 * @param array<string, mixed> $row
 */
function resurrection_bio_context(string $op, array $row): string {
    return hash('sha256', json_encode([$op, (string)$row['acctid'], (string)$row['bio'], $row['biotime']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * The 1.1.2 "[Block]" / "[Unblock]" link, now a protected POST form.
 * @param array<string, mixed> $row
 */
function resurrection_bio_form(string $op, array $row, string $label): void {
    $url = 'bios.php?op=' . $op . '&userid=' . (int)$row['acctid'];
    addnav('', $url);
    output_notl('`![');
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' style='display:inline'>" .
        resurrection_action_fields('bio-moderation-' . (int)$row['acctid'], resurrection_bio_context($op, $row)) .
        "<button type='submit' class='button'>" . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></form>');
    output_notl(']');
}

/**
 * Block or unblock a bio. The notice and the account write commit together. A repeated or stale form is
 * refused, so a replay neither rewrites nor mails again; block applies only to an unblocked bio and
 * unblock only to a blocked one.
 */
function resurrection_bio_moderate(string $op, int $id): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Use the bio moderation form.'); }
    try {
        resurrection_admin_fields();
        $row = resurrection_admin_account($id, 'acctid,bio,biotime');
        if ($row === null) throw new OutOfBoundsException('Account not found.');
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the bio list'); }
    resurrection_consume_action('bio-moderation-' . $id, resurrection_bio_context($op, $row));
    try {
        resurrection_admin_mutation(function () use ($op, $id, $row) {
            global $session;
            $locked = db_query('SELECT acctid,bio,biotime FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$id]);
            if (count($locked) !== 1 || resurrection_bio_context($op, $locked[0]) !== resurrection_bio_context($op, $row)) throw new DomainException('Bio changed.');
            if (resurrection_bio_blocked($row) === ($op === 'block')) throw new DomainException('Bio already in that state.');
            if ($op === 'block') {
                $bio = RESURRECTION_BIO_BLOCKED; $time = RESURRECTION_BIO_BLOCKED_TIME;
                $subj = array("Your bio has been blocked");
                $msg = array("The system administrators have decided that your bio entry is inappropriate, so it has been blocked.`n`nIf you wish to appeal this decision, you may do so with the petition link.");
            } else {
                // 1.1.2 wrote the zero date, which strict SQL refuses on MySQL 8.4 and MariaDB 11.4.
                // NULL is the fresh schema's "never set" biotime: not blocked, and prefs.php can set it again.
                $bio = ''; $time = null;
                $subj = array("Your bio has been unblocked");
                $msg = array("The system administrators have decided to unblock your bio.  You can once again enter a bio entry.");
            }
            if (!systemmail($id, $subj, $msg)) throw new DomainException('Account no longer exists.');
            db_query('UPDATE ' . db_prefix('accounts') . ' SET bio=?,biotime=? WHERE acctid=?', true, [$bio, $time, $id]);
            // A moderator acting on their own bio: the locked session write must carry the same values.
            if ($id === (int)$session['user']['acctid']) { $session['user']['bio'] = $bio; $session['user']['biotime'] = $time; }
        }, $id);
    } catch (Throwable $error) { resurrection_admin_failure($error, 'the bio list'); }
}

/**
 * The points field: a whole number, not 0, whose magnitude the debug log can hold. Negative numbers remove
 * points, as 1.1.2's payment log offered for reversals. Null when it is not a valid amount.
 */
function resurrection_donation_amount(string $amt): ?int {
    if (!preg_match('/\A-?[1-9][0-9]{0,6}\z/', $amt)) return null;
    return (int)$amt;
}

/**
 * The payment log row a transaction credit settles: exactly one row with that ID, not yet processed.
 * @return array<string, mixed>|null
 */
function resurrection_donation_payment(string $txnid, bool $lock = false): ?array {
    $rows = db_query('SELECT payid,txnid,acctid,processed FROM ' . db_prefix('paylog') . ' WHERE txnid=?' . ($lock ? ' FOR UPDATE' : ''), true, [$txnid]);
    if (count($rows) !== 1 || (int)$rows[0]['processed'] !== 0) return null;
    return $rows[0];
}

/**
 * An add-points form is bound to the account and its balance, the points, reason and transaction it
 * was shown with, and that transaction's payment log row.
 * @param array<string, mixed> $row
 * @param array<string, mixed>|null $payment
 */
function resurrection_donation_context(array $row, int $amt, string $reason, string $txnid, ?array $payment): string {
    $state = ['add2', (string)$row['acctid'], (string)$row['donation'], $amt, $reason, $txnid];
    if ($payment !== null) array_push($state, (string)$payment['payid'], (string)$payment['acctid'], (string)$payment['processed']);
    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * @param array<string, mixed> $row
 */
function resurrection_donation_balance_fits(array $row, int $points): bool {
    $balance = (int)$row['donation'] + $points;
    return $balance >= 0 && $balance <= RESURRECTION_DONATION_MAX_BALANCE;
}

/**
 * The 1.1.2 confirmation link for one search match, now a protected POST form carrying its values.
 * @param array<string, mixed> $row
 * @param array<string, mixed>|null $payment
 */
function resurrection_donation_form(array $row, int $amt, string $reason, string $txnid, ?array $payment, string $ret): void {
    $url = 'donators.php?op=add2&id=' . (int)$row['acctid'] . ($ret !== '' ? '&ret=' . rawurlencode($ret) : '');
    addnav('', $url);
    $hidden = '';
    foreach (['amt' => (string)$amt, 'reason' => $reason, 'txnid' => $txnid] as $key => $value) {
        $hidden .= "<input type='hidden' name='$key' value='" . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>";
    }
    rawoutput("<form method='POST' action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' style='display:inline'>" .
        resurrection_action_fields('donation-add-' . (int)$row['acctid'], resurrection_donation_context($row, $amt, $reason, $txnid, $payment)) .
        $hidden . "<button type='submit' class='button'>");
    output_notl('%s (%s/%s)', $row['name'], $row['donation'], $row['donationspent']);
    rawoutput('</button></form>');
}

/**
 * The search for the account to credit: login or name, with 1.1.2's "%" between characters, through the
 * bank's recipient pattern (UTF-8 characters; typed % and _ match only themselves).
 * @return list<array<string, mixed>>
 */
function resurrection_donation_search(string $name): array {
    $pattern = resurrection_bank_search_pattern($name);
    return db_query('SELECT name,acctid,donation,donationspent FROM ' . db_prefix('accounts') . " WHERE login LIKE ? ESCAPE '|' OR name LIKE ? ESCAPE '|' LIMIT 100", true, [$pattern, $pattern]);
}

/**
 * 1.1.2's "Return whence you came": the referring page's REQUEST_URI, its `c=` counter removed and cut
 * after the last slash. Only the two pages that link here are accepted, in their display forms (the user
 * editor and the petition viewer, with no second `op`); anything else is the historical empty return.
 */
function resurrection_donation_return(string $ret): string {
    $return = cmd_sanitize($ret);
    $return = substr($return, (int)strrpos($return, '/') + 1);
    return preg_match('/\A(?:user\.php\?op=edit|viewpetition\.php\?op=view)(?:&(?!op=)[A-Za-z0-9_]+=[A-Za-z0-9_.-]*)*\z/', $return) ? $return : '';
}

/**
 * Credit (or, with a negative amount, remove) donation points. The account write, the hooks and their
 * writes, the debug log, the payment log row and the notice commit together.
 * @param array<string, mixed> $row
 * @param array<string, mixed>|null $payment
 * @return array{name: mixed, points: int}
 */
function resurrection_donation_credit(array $row, int $amt, string $reason, string $txnid, ?array $payment): array {
    return resurrection_admin_mutation(function () use ($row, $amt, $reason, $txnid, $payment) {
        global $session;
        $id = (int)$row['acctid'];
        $locked = db_query('SELECT acctid,name,donation FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$id]);
        if (count($locked) !== 1 || (string)$locked[0]['donation'] !== (string)$row['donation']) throw new DomainException('Balance changed.');
        if ($txnid > "") {
            $locked_payment = resurrection_donation_payment($txnid, true);
            if ($payment === null || $locked_payment === null || (string)$locked_payment['payid'] !== (string)$payment['payid']) throw new DomainException('Transaction already processed.');
            $result = modulehook("donation_adjustments", array("points"=>$amt,"amount"=>$amt/100,"acctid"=>$id,"messages"=>array()));
            // MySQL rounded a fractional hook result into the integer column; payment.php rounded it explicitly.
            if (!is_numeric($result['points'] ?? null)) throw new UnexpectedValueException('Invalid adjusted points.');
            $points = (int)round((float)$result['points']);
            if (!is_array($result['messages'])) {
                $result['messages'] = array($result['messages']);
            }
            foreach ($result['messages'] as $message) {
                debuglog($message, false, $id, "donation", 0, false);
            }
        } else {
            $points = $amt;
        }
        if (abs($points) > RESURRECTION_DONATION_MAX_POINTS || !resurrection_donation_balance_fits($locked[0], $points)) throw new DomainException('Points out of range.');
        $balance = (int)$locked[0]['donation'] + $points;
        db_query('UPDATE ' . db_prefix('accounts') . ' SET donation=? WHERE acctid=?', true, [$balance, $id]);
        // 1.1.2 added the typed amount to the session and the adjusted points to the database; both now get the points.
        if ($id === (int)$session['user']['acctid']) $session['user']['donation'] = $balance;
        modulehook("donation", array("id"=>$id, "amt"=>$points, "manual"=>($txnid>""?false:true)));
        if ($txnid > "") {
            db_query('UPDATE ' . db_prefix('paylog') . ' SET acctid=?, processed=1 WHERE payid=?', true, [$id, (int)$payment['payid']]);
            debuglog("Received donator points for donating -- Credited manually [$reason]", false, $id, "donation", $points, false);
        } else {
            debuglog("Received donator points -- Manually assigned, not based on a known dollar donation [$reason]", false, $id, "donation", $amt, false);
            // 1.1.2 redirected a transaction credit to the payment log before this notice; only manual entries mail.
            if ($points == 1) {
                $sent = systemmail($id, array("Donation Point Added"), array("`2You have received a donation point for %s.", $reason));
            } else {
                $sent = systemmail($id, array("Donation Points Added"), array("`2You have received %d donation points for %s.", $points, $reason));
            }
            if (!$sent) throw new DomainException('Account no longer exists.');
        }
        return ['name' => $locked[0]['name'], 'points' => $points];
    }, (int)$row['acctid']);
}
