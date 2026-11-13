<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Password recovery by mail (Phase 6 task 13b, S11 part 2; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, section
 * 3, with decisions 2, 3 and 6).
 *
 * - The request (3.1): one value, a login or an address. Whatever it matches, the requester gets the same page; the
 *   work (the limit counts, the lookup, the token and the mail) runs after that page is complete
 *   (resurrection_account_after_response()).
 * - Only an address verified for the account receives a recovery mail (decision 2). Locked, banned, unverified and
 *   pending accounts get nothing, and staff accounts only while account_mail_staff is on (decision 3).
 * - The reset (3.4): in one transaction, the token and the account locked, the account's authversion still the token's,
 *   the password set, authversion raised (every session of the account ends), every live recovery token used up.
 *   The player is not signed in. A notice mail follows, and after a staff reset a game mail to every other megauser.
 *
 * Tokens, grants, limits and the transport are account mail's (lib/account_mail.php).
 */
require_once __DIR__ . '/account_mail.php';
require_once __DIR__ . '/../src/Security/Passwords.php';

/** How long one forgot-password response is held where the server cannot finish a response early (seconds, 3.1). */
const RESURRECTION_ACCOUNT_RESPONSE_FLOOR = 1.5;
/** No new recovery token while one made less than this long ago is still live (seconds, 3.1). */
const RESURRECTION_ACCOUNT_RECOVERY_QUIET = 300;
/** The most accounts one address can recover in one request (3.1). */
const RESURRECTION_ACCOUNT_RECOVERY_ACCOUNTS = 5;
/** The page every forgot-password request gets, whatever it matched (3.1). */
const RESURRECTION_ACCOUNT_RECOVERY_ANSWER = 'If an account matches and can receive mail here, a message with a link is on its way. It works for 30 minutes. Check your spam folder too.';
/** The page op=forgot shows while account mail is off (section 6). */
const RESURRECTION_ACCOUNT_RECOVERY_OFF = 'Password recovery by email is not available on this game. Use Petition for Help to ask an administrator to reset your password.';

/**
 * Whether an account holds any Grotto flag (a staff account, decision 3); the same test checkban() uses.
 * @param array<string, mixed> $account
 */
function resurrection_account_recovery_staff(array $account): bool {
    return ((int)$account['superuser'] & ~SU_DOESNT_GIVE_GROTTO) !== 0;
}

/**
 * Whether a ban matches the account's last IP or ID: checkban()'s query, without its session and redirect. An account
 * with banoverride, or a staff account, is never banned, as checkban() has it.
 * @param array<string, mixed> $account
 */
function resurrection_account_recovery_banned(array $account): bool {
    if ((int)$account['banoverride'] !== 0 || resurrection_account_recovery_staff($account)) return false;
    $rows = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('bans'))
        . " WHERE ((substring(?,1,length(ipfilter))=ipfilter AND ipfilter<>'') OR (uniqueid=? AND uniqueid<>'')) AND (banexpire IS NULL OR banexpire>=?)", true,
        [(string)$account['lastip'], (string)$account['uniqueid'], date('Y-m-d')]);
    return (int)($rows[0]['n'] ?? 0) > 0;
}

/**
 * Whether an account can receive a recovery mail now (3.1): a valid address verified for it, not locked, not a pending
 * sign-up, not banned, and a staff account only while account_mail_staff is on. Limits are checked by the caller.
 * @param array<string, mixed> $account
 */
function resurrection_account_recovery_eligible(array $account): bool {
    $address = (string)$account['emailaddress'];
    if (!resurrection_mail_address_valid($address) || (string)$account['emailverified'] === ''
        || strtolower((string)$account['emailverified']) !== strtolower($address)) return false;
    if ((int)$account['locked'] !== 0 || (string)$account['emailvalidation'] !== '') return false;
    if (resurrection_account_recovery_staff($account) && !resurrection_account_mail_staff_allowed()) return false;
    return !resurrection_account_recovery_banned($account);
}

/** Whether an account holds a live recovery token made less than 5 minutes ago (the earlier mail stands, 3.1). */
function resurrection_account_recovery_recent(int $acctid, int $now): bool {
    $rows = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('account_tokens')) . ' t JOIN ' . db_identifier(db_prefix('accounts'))
        . " a ON a.acctid=t.acctid AND a.authversion=t.authversion WHERE t.acctid=? AND t.purpose='recover' AND t.used IS NULL AND t.expires>? AND t.created>?", true,
        [$acctid, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now - RESURRECTION_ACCOUNT_RECOVERY_QUIET)]);
    return (int)($rows[0]['n'] ?? 0) > 0;
}

/**
 * The work of one forgot-password request (3.1), run after its page is complete. Every request is counted for the
 * requester first; then one lookup (by login, or by an address verified for up to 5 accounts, oldest first); then, for
 * each eligible account within its limits and the 5-minute rule, a token (replacing any live one); then one mail to the
 * address listing each account's login with its own link. Returns what happened, for tests and logs only: limited,
 * malformed, off, none, sent or refused. Nothing it returns ever reaches the requester.
 */
function resurrection_account_recovery_request(mixed $who, ?int $now = null): string {
    $now ??= time();
    if (resurrection_account_mail_limited('recover-request', 0, '', $now) !== null) {
        resurrection_account_mail_log('recover-request', 'limited', 0, '', $now);
        return 'limited';
    }
    resurrection_account_mail_log('recover-request', 'counted', 0, '', $now);
    if (!is_string($who) || strlen($who) > 128 || trim($who) === '' || preg_match('/[\x00-\x1F\x7F]/', $who)) return 'malformed';
    $who = trim($who);
    if (!resurrection_account_mail_on()) return 'off';
    $columns = 'acctid,login,emailaddress,emailverified,emailvalidation,locked,superuser,banoverride,lastip,uniqueid';
    $accounts = db_identifier(db_prefix('accounts'));
    $found = str_contains($who, '@')
        ? db_query('SELECT ' . $columns . ' FROM ' . $accounts . " WHERE LOWER(emailaddress)=LOWER(?) AND emailverified<>'' AND LOWER(emailverified)=LOWER(emailaddress) ORDER BY acctid LIMIT "
            . RESURRECTION_ACCOUNT_RECOVERY_ACCOUNTS, true, [$who])
        : db_query('SELECT ' . $columns . ' FROM ' . $accounts . ' WHERE login=? LIMIT 1', true, [$who]);
    $address = null;
    $links = [];
    foreach ($found as $account) {
        if (!resurrection_account_recovery_eligible($account)) continue;
        $acctid = (int)$account['acctid'];
        $address ??= (string)$account['emailaddress'];
        if (resurrection_account_recovery_recent($acctid, $now)) {
            resurrection_account_mail_log('recover', 'skipped', $acctid, '', $now);
            continue;
        }
        if (resurrection_account_mail_limited('recover', $acctid, $address, $now) !== null) {
            resurrection_account_mail_log('recover', 'limited', $acctid, '', $now);
            continue;
        }
        $token = resurrection_account_token_issue($acctid, 'recover', (string)$account['emailaddress'], $now);
        $links[$acctid] = [(string)$account['login'], resurrection_account_mail_link($token)];
        unset($token);
    }
    if ($links === [] || $address === null) return 'none';
    $base = (string)resurrection_account_mail_base();
    $body = (count($links) === 1 ? 'Someone asked for a link to choose a new password for this account at ' : 'Someone asked for links to choose a new password for these accounts at ')
        . $base . ":\n\n";
    foreach ($links as [$login, $link]) $body .= $login . ":\n" . $link . "\n\n";
    $body .= "Open the link within 30 minutes and choose a new password on the page it shows. Each link works once.\n\n"
        . "If you did not ask for this, ignore this mail. Your password has not changed.\n";
    $sent = resurrection_account_recovery_deliver(array_keys($links), $address, 'Choose a new password', $body, $now);
    unset($body, $links);
    return $sent ? 'sent' : 'refused';
}

/**
 * Hand one recovery mail to the transport and record it: one row per account it serves (each account's limits count
 * it), the address key on the first row only (the address received one mail).
 * @param non-empty-list<int> $acctids
 */
function resurrection_account_recovery_deliver(array $acctids, string $address, string $subject, #[\SensitiveParameter] string $body, int $now): bool {
    $sender = resurrection_account_mail_sender();
    $sent = $sender !== null && resurrection_send_email($address, $subject, $body, $sender);
    foreach ($acctids as $index => $acctid) {
        resurrection_account_mail_log('recover', $sent ? 'sent' : 'refused', $acctid, $index === 0 ? $address : '', $now);
    }
    if (!$sent) gamelog('Account mail (recover) could not be handed to the mail transport.', 'account mail');
    return $sent;
}

/**
 * The reset (3.4), in one transaction: the grant's token locked and live, its account locked, unlocked, still holding
 * the address the mail went to and the token's authversion, and (a staff account) the staff switch on; the password
 * set, authversion raised and loggedin cleared, on the authversion condition; every live recovery token of the account
 * used up. Throws InvalidArgumentException for a password the rules refuse (nothing changed, the grant stands) and
 * DomainException for every reason the link no longer works.
 * @param array{tokenid: int, hash: string} $grant
 * @return array{acctid: int, login: string, address: string, staff: bool}
 */
function resurrection_account_recovery_reset(array $grant, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $repeat, ?int $now = null): array {
    $now ??= time();
    if (!hash_equals($password, $repeat)) throw new InvalidArgumentException('The two passwords are not the same.');
    $hash = \Resurrection\Security\Passwords::hash($password);
    if (!resurrection_account_mail_on()) throw new DomainException('Expired link.');
    $connection = $GLOBALS['dbinfo']['connection'];
    $connection->beginTransaction();
    try {
        $claimed = resurrection_account_token_claim($grant['tokenid'], $grant['hash'], 'recover', $now);
        $account = $claimed['account'];
        $staff = resurrection_account_recovery_staff($account);
        if ((int)$account['locked'] !== 0 || (string)$account['emailaddress'] !== (string)$claimed['token']['address']
            || ($staff && !resurrection_account_mail_staff_allowed())) throw new DomainException('Expired link.');
        $accounts = db_identifier(db_prefix('accounts'));
        db_query('UPDATE ' . $accounts . ' SET password=?,authversion=authversion+1,loggedin=0 WHERE acctid=? AND authversion=?', true,
            [$hash, (int)$account['acctid'], (int)$account['authversion']]);
        if (db_affected_rows() !== 1) throw new DomainException('Expired link.');
        resurrection_account_token_spend((int)$claimed['token']['tokenid'], $now);
        db_query('UPDATE ' . db_identifier(db_prefix('account_tokens')) . " SET used=? WHERE acctid=? AND purpose='recover' AND used IS NULL", true,
            [date('Y-m-d H:i:s', $now), (int)$account['acctid']]);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    unset($hash);
    gamelog('Password reset through a recovery link for account ' . (int)$account['acctid'], 'account mail');
    if ($staff) gamelog('Staff account password reset through a recovery link (account ' . (int)$account['acctid'] . ')', 'account mail');
    return ['acctid' => (int)$account['acctid'], 'login' => (string)$account['login'], 'address' => (string)$claimed['token']['address'], 'staff' => $staff];
}

/**
 * After a reset (3.4, decision 3): the notice mail to the account's address (no link, no IP), within the address and
 * global limits; and, after a staff reset, a game mail to every other SU_MEGAUSER account.
 * @param array{acctid: int, login: string, address: string, staff: bool} $reset
 */
function resurrection_account_recovery_notices(array $reset, ?int $now = null): void {
    $now ??= time();
    $when = gmdate('Y-m-d H:i', $now) . ' UTC';
    $base = (string)resurrection_account_mail_base();
    if ($reset['staff']) {
        $rows = db_query('SELECT acctid FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE (superuser&?)<>0 AND acctid<>? ORDER BY acctid', true, [SU_MEGAUSER, $reset['acctid']]);
        foreach ($rows as $row) {
            systemmail((int)$row['acctid'], 'Staff password reset by email',
                'The password of the staff account ' . $reset['login'] . ' was reset through a recovery link sent to its address at ' . $when
                . '. If this was not expected, check the account in the user editor.');
        }
    }
    if (resurrection_account_mail_limited('notice', $reset['acctid'], $reset['address'], $now) !== null) {
        resurrection_account_mail_log('notice', 'limited', $reset['acctid'], '', $now);
        return;
    }
    resurrection_account_mail_deliver('notice', $reset['acctid'], $reset['address'], 'Your password was changed',
        'The password for ' . $reset['login'] . ' at ' . $base . ' was changed by a recovery link at ' . $when . ".\n\n"
        . "If you did not do this, ask for a new link or contact the game's administrator.\n");
}

/** The function that completes a response early on this server (PHP-FPM, LiteSpeed), or null where there is none. */
function resurrection_account_response_finisher(): ?string {
    foreach (['fastcgi_finish_request', 'litespeed_finish_request'] as $name) {
        if (function_exists($name)) return $name;
    }
    return null;
}

/**
 * Send a page, and run work after it (3.1, 3.4). The page always goes out with its length. Where the server can
 * complete a response early, the page goes out whole, the response is completed, and then the work runs. Where it
 * cannot: with a floor (a forgot-password request, whose work the requester must not be able to time), the work runs
 * first and the page is held until $floor seconds after $start, so every response takes the same time whatever the
 * work did; with no floor (the notice after a reset, which has nothing to hide), the page is sent and flushed first.
 */
function resurrection_account_after_response(string $page, callable $work, float $start, float $floor, ?callable $finisher): void {
    if (!headers_sent()) header('Content-Length: ' . strlen($page));
    if ($finisher !== null || $floor <= 0) {
        echo $page;
        while (ob_get_level() > 0) ob_end_flush();
        flush();
        if ($finisher !== null) $finisher();
        resurrection_account_quiet_work($work);
        return;
    }
    resurrection_account_quiet_work($work);
    $wait = $start + $floor - microtime(true);
    if ($wait > 0) usleep((int)ceil($wait * 1000000));
    echo $page;
}

/**
 * Run work whose output and failures must not reach the response: anything it prints is dropped, and a failure is one
 * fixed line in the PHP error log (never its message, which could hold an address).
 */
function resurrection_account_quiet_work(callable $work): void {
    ob_start();
    try { $work(); }
    catch (Throwable $error) { error_log('Account mail: the work after a response failed (' . $error::class . ').'); }
    finally { ob_end_clean(); }
}

/**
 * Hold everything the page prints from here on, and when the request ends (page_footer() exits), send it with
 * resurrection_account_after_response(): $work after the page, or, with a floor and no way to complete the response
 * early, before a page held to the floor. The session is written and closed first, so the work never holds it.
 * (account.php's since task 13b; here since task 13c, which uses it for sign-up's verify mail in create.php too.)
 */
function resurrection_account_send_then(callable $work, float $floor): void {
    $level = ob_get_level();
    ob_start();
    register_shutdown_function(static function () use ($work, $floor, $level): void {
        $page = '';
        while (ob_get_level() > $level) $page = (string)ob_get_clean() . $page;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        resurrection_account_after_response($page, $work, (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), $floor, resurrection_account_response_finisher());
    });
}
