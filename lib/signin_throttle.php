<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/account_mail.php';

/*
 * Sign-in throttling (Phase 6 task 18, F4; docs/PHASE6-TASK18-20261114.md). Failed sign-ins are faillog rows with the
 * fixed word invalid_credentials, the requester key (REMOTE_ADDR: the IPv4 address or the IPv6 /64, as account mail
 * keys it; never X-Forwarded-For) in ip, and the sign-in name's key in id. Three limits, each a bounded Game Settings
 * value (lib/settings.php, resurrection_bounded_settings()):
 *
 * - pair: failures for one sign-in name from one address in 15 minutes (signin_fail_pair, default 5);
 * - address: failures from one address in 15 minutes, whatever the name (signin_fail_address, default 20);
 * - account: failures for one sign-in name from every address in an hour (signin_fail_account, default 50). It is high
 *   on purpose, so that nobody can keep a player out by failing on purpose; the other two stop one guesser first.
 *
 * The name's key is the same whether or not an account has the name, so the limits never show whether a login exists.
 * An attempt over any limit is refused with one message, checks no password and writes its own row (signin_throttled),
 * which no limit counts. A successful sign-in clears nothing. Daily maintenance deletes faillog rows older than 30 days.
 */

/** Each limit: [its setting, its window in seconds, what it counts by]. */
const RESURRECTION_SIGNIN_LIMITS = [
    'pair' => ['signin_fail_pair', 900, 'pair'],
    'address' => ['signin_fail_address', 900, 'address'],
    'account' => ['signin_fail_account', 3600, 'account'],
];

/** The faillog words of sign-in (fixed vocabulary, never a value). */
const RESURRECTION_SIGNIN_FAILED = 'invalid_credentials';
const RESURRECTION_SIGNIN_THROTTLED = 'signin_throttled';

/** How long faillog rows are kept, in seconds (30 days). */
const RESURRECTION_FAILLOG_KEEP = 30 * 86400;

/**
 * A bcrypt hash of a random password nobody knows, at PASSWORD_DEFAULT's cost on the supported PHP versions (12). A
 * sign-in name no account has is checked against it, so that it takes as long as a wrong password
 * (SignInThrottleTest holds the cost to password_hash()'s).
 */
const RESURRECTION_SIGNIN_DUMMY_HASH = '$2y$12$pOxNy0PGZ3seQxfmbRTyoObkIYkQgSSx4k2O2iDg8BBHkxF.yHao.';

/**
 * Whether a sign-in name can name an account: 1 to 50 printable ASCII characters (accounts.login is varchar(50), and
 * create.php allows letters and digits only). Any other name is looked up nowhere and matches no account, so a name
 * the database's collation would fold onto a login (an accent, a control character) cannot reach one under another key.
 */
function resurrection_signin_name_usable(string $login): bool {
    return preg_match('/\A[\x20-\x7e]{1,50}\z/', $login) === 1;
}

/**
 * The sign-in name's key in faillog.id: 32 hex characters of SHA-256 over the name as the database compares logins
 * (case and trailing spaces ignored). The same for a name no account has, so the limits treat both alike.
 */
function resurrection_signin_login_key(string $login): string {
    return substr(hash('sha256', 'signin:' . strtolower(rtrim($login, ' '))), 0, 32);
}

/** A limit's most failures allowed: its bounded setting. */
function resurrection_signin_limit_max(string $name): int {
    [$setting] = RESURRECTION_SIGNIN_LIMITS[$name] ?? throw new InvalidArgumentException('Unknown sign-in limit.');
    $defaults = ['signin_fail_pair' => 5, 'signin_fail_address' => 20, 'signin_fail_account' => 50];
    return (int)getsetting($setting, $defaults[$setting]);
}

/**
 * Whether a sign-in with this name, from this request's address, is refused for now: null when no limit holds it, or
 * the whole minutes (at least 1) until every limit that holds it lets one more attempt through.
 */
function resurrection_signin_throttled(string $login, ?int $now = null): ?int {
    $now ??= time();
    $address = resurrection_account_mail_requester();
    $key = resurrection_signin_login_key($login);
    $wait = 0;
    foreach (RESURRECTION_SIGNIN_LIMITS as $name => [, $window, $by]) {
        $max = resurrection_signin_limit_max($name);
        $since = date('Y-m-d H:i:s', $now - $window);
        // The window's failures, newest first (at most the highest bound, 1000; failures stop at the limit).
        $rows = match ($by) {
            'pair' => db_query('SELECT date FROM ' . db_prefix('faillog') . ' WHERE post=? AND date>? AND ip=? AND id=? ORDER BY date DESC, eventid DESC LIMIT 1000', true,
                [RESURRECTION_SIGNIN_FAILED, $since, $address, $key]),
            'address' => db_query('SELECT date FROM ' . db_prefix('faillog') . ' WHERE post=? AND date>? AND ip=? ORDER BY date DESC, eventid DESC LIMIT 1000', true,
                [RESURRECTION_SIGNIN_FAILED, $since, $address]),
            'account' => db_query('SELECT date FROM ' . db_prefix('faillog') . ' WHERE post=? AND date>? AND id=? ORDER BY date DESC, eventid DESC LIMIT 1000', true,
                [RESURRECTION_SIGNIN_FAILED, $since, $key]),
        };
        if (count($rows) < $max) continue;
        // One more attempt is allowed once the max-th most recent failure leaves the window.
        $leaves = strtotime((string)$rows[$max - 1]['date']) + $window;
        $wait = max($wait, $leaves - $now, 1);
    }
    return $wait > 0 ? max(1, (int)ceil($wait / 60)) : null;
}

/** The one message every throttled sign-in gets. */
function resurrection_signin_throttle_message(int $minutes): string {
    return 'Too many attempts. Try again in ' . $minutes . ($minutes === 1 ? ' minute.' : ' minutes.');
}

/** Record a failed or throttled sign-in: fixed word, requester key, no account id, the name's key. */
function resurrection_signin_record(string $word, string $login, ?int $now = null): void {
    if (!in_array($word, [RESURRECTION_SIGNIN_FAILED, RESURRECTION_SIGNIN_THROTTLED], true)) {
        throw new InvalidArgumentException('Unknown sign-in log entry.');
    }
    db_query('INSERT INTO ' . db_prefix('faillog') . ' (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', true,
        [date('Y-m-d H:i:s', $now ?? time()), $word, resurrection_account_mail_requester(), 0, resurrection_signin_login_key($login)]);
}

/** Daily maintenance: delete every faillog row older than 30 days (sign-in's and setup.php's). Returns how many. */
function resurrection_faillog_cleanup(?int $now = null): int {
    db_query('DELETE FROM ' . db_prefix('faillog') . ' WHERE date<?', true,
        [date('Y-m-d H:i:s', ($now ?? time()) - RESURRECTION_FAILLOG_KEEP)]);
    return db_affected_rows();
}
