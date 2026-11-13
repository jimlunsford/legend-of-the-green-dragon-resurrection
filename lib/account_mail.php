<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Account mail (Phase 6 task 13a, S11 part 1; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md and its decisions).
 *
 * The foundation the password recovery (13b) and the email verification (13c) build on:
 *
 * - account mail's effective state: a switch in Game Settings that counts as on only while serverurl is a valid
 *   https:// address, the sender (gameadminemail) passes the address rules and is not the default, and a test mail was
 *   confirmed through its link for that serverurl and sender (section 2.5);
 * - tokens (section 3.2): 32 random bytes, base64url (43 characters), only their SHA-256 stored in account_tokens,
 *   bound to an account, a purpose, an address and the account's authversion, with an expiry, at most 5 openings and
 *   single use; a token is exchanged on first open for a short session grant that holds its hash, never the token;
 * - limits (section 5), counted in account_mail_log by purpose, account, address key (SHA-256 of the lower-cased
 *   address) and requester key (REMOTE_ADDR: the IPv4 address or the IPv6 /64), never X-Forwarded-For;
 * - account mail itself, which goes only through resurrection_send_email() (lib/systemmail.php), with its links built
 *   only from an https:// serverurl.
 *
 * A token passes only through parameters marked #[\SensitiveParameter], and is never logged, stored, kept in a page or
 * put in the session.
 */
require_once __DIR__ . '/systemmail.php';
require_once __DIR__ . '/server_url.php';
require_once __DIR__ . '/gamelog.php';

/** The purposes a token can have, and how long each lives (seconds). */
const RESURRECTION_ACCOUNT_TOKEN_LIFETIMES = ['recover' => 1800, 'verify' => 86400, 'email' => 86400, 'mailtest' => 86400];

/** How many times a token's link may be opened. */
const RESURRECTION_ACCOUNT_TOKEN_OPENS = 5;

/** How long the session grant made by opening a link lasts (seconds). */
const RESURRECTION_ACCOUNT_GRANT_LIFETIME = 900;

/** The sender addresses the game ships as defaults, which account mail refuses (finding F5). */
const RESURRECTION_ACCOUNT_MAIL_DEFAULT_SENDERS = ['postmaster@localhost.com', 'postmaster@localhost'];

/** The settings account mail keeps outside the Game Settings form (none can be set through it). */
const RESURRECTION_ACCOUNT_MAIL_SETTINGS = ['account_mail', 'account_mail_proof', 'account_mail_pending'];

/**
 * The limits (section 5): name => [what is counted, the purposes counted (null: every purpose), whether only mail
 * handed to the transport counts (results sent and refused) or every row, the window in seconds, the most allowed].
 * The global limit's most allowed is the account_mail_hourly setting.
 */
const RESURRECTION_ACCOUNT_MAIL_LIMITS = [
    'recover-requester' => ['requester', ['recover-request'], false, 3600, 10],
    'open-requester' => ['requester', ['open'], false, 3600, 30],
    'recover-account-hour' => ['account', ['recover'], true, 3600, 3],
    'recover-account-day' => ['account', ['recover'], true, 86400, 6],
    'verify-account-hour' => ['account', ['verify'], true, 3600, 3],
    'verify-account-day' => ['account', ['verify'], true, 86400, 6],
    'email-account-day' => ['account', ['email-request'], false, 86400, 3],
    'address-day' => ['address', null, true, 86400, 5],
    'signup-requester' => ['requester', ['signup'], false, 3600, 5],
    'mailtest-account-hour' => ['account', ['mailtest'], true, 3600, 5],
    'global-hour' => ['global', null, true, 3600, 0],
];

/** Which limits each purpose is checked against: mail purposes, then request purposes (counted for every request). */
const RESURRECTION_ACCOUNT_MAIL_PURPOSE_LIMITS = [
    'mailtest' => ['mailtest-account-hour', 'address-day', 'global-hour'],
    'recover' => ['recover-account-hour', 'recover-account-day', 'address-day', 'global-hour'],
    'verify' => ['verify-account-hour', 'verify-account-day', 'address-day', 'global-hour'],
    'email' => ['address-day', 'global-hour'],
    'notice' => ['address-day', 'global-hour'],
    'recover-request' => ['recover-requester'],
    'open' => ['open-requester'],
    'email-request' => ['email-account-day'],
    'signup' => ['signup-requester'],
];

/** The results a log row can record. */
const RESURRECTION_ACCOUNT_MAIL_RESULTS = ['sent', 'refused', 'limited', 'skipped', 'counted'];

/**
 * An address the game may send to or from: at most 128 bytes, no control character, PHP's FILTER_VALIDATE_EMAIL, and
 * only the plain dot-atom form, anchored at both ends: letters, digits and !#$%&'*+/=?^_`{|}~.- before the @, and a
 * domain of letters, digits and hyphens with at least one dot. Quoted local parts, comments, IP literals, spaces,
 * commas and angle brackets are refused, so a value can never be read as two recipients or as a header.
 */
function resurrection_mail_address_valid(mixed $address): bool {
    if (!is_string($address) || $address === '' || strlen($address) > 128 || preg_match('/[\x00-\x20\x7F]/', $address)) return false;
    if (!preg_match('/\A[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+\z/', $address)) return false;
    return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
}

/** The base every account mail link starts with: serverurl when it is valid and https://, else null. Never the request. */
function resurrection_account_mail_base(): ?string {
    $url = resurrection_server_url();
    return $url !== null && strncasecmp($url, 'https://', 8) === 0 ? $url : null;
}

/** The sender of account mail: gameadminemail when it passes the address rules and is not a shipped default. */
function resurrection_account_mail_sender(): ?string {
    global $settings;
    if (!is_array($settings)) loadsettings();
    $sender = (string)($settings['gameadminemail'] ?? '');
    return resurrection_mail_address_valid($sender) && !in_array(strtolower($sender), RESURRECTION_ACCOUNT_MAIL_DEFAULT_SENDERS, true) ? $sender : null;
}

/** The fingerprint a test mail proves: the serverurl and sender it was sent with. */
function resurrection_account_mail_fingerprint(string $base, string $sender): string {
    return hash('sha256', $base . "\n" . strtolower($sender));
}

/** A stored setting's value read from the table (never through getsetting(), which writes a default it does not find). */
function resurrection_account_mail_setting(string $name): string {
    $rows = db_query('SELECT value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', true, [$name]);
    return (string)($rows[0]['value'] ?? '');
}

/**
 * The confirmed test mail (account_mail_proof): when, by which account, and the fingerprint it proves; null when there
 * is none or it cannot be read.
 * @return array{time: string, acctid: int, fp: string}|null
 */
function resurrection_account_mail_proof(): ?array {
    $proof = json_decode(resurrection_account_mail_setting('account_mail_proof'), true);
    if (!is_array($proof) || !is_string($proof['time'] ?? null) || !is_int($proof['acctid'] ?? null) || !is_string($proof['fp'] ?? null)
        || !preg_match('/\A[0-9a-f]{64}\z/', $proof['fp'])) return null;
    return ['time' => $proof['time'], 'acctid' => $proof['acctid'], 'fp' => $proof['fp']];
}

/**
 * Why account mail cannot be on now (section 2.5), as fixed reasons; empty when every condition holds. The switch
 * itself is not one of them.
 * @return list<string>
 */
function resurrection_account_mail_blockers(): array {
    $reasons = [];
    $base = resurrection_account_mail_base();
    $sender = resurrection_account_mail_sender();
    if ($base === null) $reasons[] = 'serverurl';
    if ($sender === null) $reasons[] = 'sender';
    $proof = resurrection_account_mail_proof();
    if ($proof === null) $reasons[] = 'no-proof';
    elseif ($base !== null && $sender !== null && !hash_equals($proof['fp'], resurrection_account_mail_fingerprint($base, $sender))) $reasons[] = 'stale-proof';
    return $reasons;
}

/** Whether the switch is on (account_mail), whatever the conditions. */
function resurrection_account_mail_switch(): bool {
    return resurrection_account_mail_setting('account_mail') === '1';
}

/** Account mail's effective state: the switch on and every condition of section 2.5 holding now. */
function resurrection_account_mail_on(): bool {
    return resurrection_account_mail_switch() && resurrection_account_mail_blockers() === [];
}

/** Whether staff accounts may recover their password by mail (account_mail_staff, decision 3 and task 13b; on by default). */
function resurrection_account_mail_staff_allowed(): bool {
    return (int)getsetting('account_mail_staff', 1) === 1;
}

/** Turn the switch on, only when every condition holds; false (nothing changed) otherwise. */
function resurrection_account_mail_turn_on(): bool {
    if (resurrection_account_mail_blockers() !== []) return false;
    savesetting('account_mail', '1');
    return true;
}

/** Turn the switch off; always allowed. */
function resurrection_account_mail_turn_off(): void {
    savesetting('account_mail', '0');
}

/** The requester key for REMOTE_ADDR: the IPv4 address, or the IPv6 /64 prefix; '' when it is neither. */
function resurrection_account_mail_requester(?string $address = null): string {
    $address ??= (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) return $address;
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $packed = inet_pton($address);
        if ($packed !== false && strlen($packed) === 16) {
            return implode(':', str_split(bin2hex(substr($packed, 0, 8)), 4)) . '::/64';
        }
    }
    return '';
}

/** The address key the log keeps instead of an address: SHA-256 of the trimmed, lower-cased address; '' for none. */
function resurrection_account_mail_address_key(string $address): string {
    $address = strtolower(trim($address));
    return $address === '' ? '' : hash('sha256', $address);
}

/** Record one request or mail in account_mail_log. */
function resurrection_account_mail_log(string $purpose, string $result, int $acctid = 0, string $address = '', ?int $now = null): void {
    if (!isset(RESURRECTION_ACCOUNT_MAIL_PURPOSE_LIMITS[$purpose]) || !in_array($result, RESURRECTION_ACCOUNT_MAIL_RESULTS, true)) {
        throw new InvalidArgumentException('Unknown account mail log entry.');
    }
    db_query('INSERT INTO ' . db_identifier(db_prefix('account_mail_log')) . ' (date,purpose,acctid,addresskey,requesterkey,result) VALUES (?,?,?,?,?,?)', true,
        [date('Y-m-d H:i:s', $now ?? time()), $purpose, $acctid, resurrection_account_mail_address_key($address), resurrection_account_mail_requester(), $result]);
}

/** The most allowed for a limit: its own number, or for the global limit the bounded account_mail_hourly setting. */
function resurrection_account_mail_limit_max(string $name): int {
    if ($name === 'global-hour') return (int)getsetting('account_mail_hourly', 100);
    return RESURRECTION_ACCOUNT_MAIL_LIMITS[$name][4];
}

/**
 * The first limit a purpose has reached for this account, address and requester, or null when none.
 */
function resurrection_account_mail_limited(string $purpose, int $acctid = 0, string $address = '', ?int $now = null): ?string {
    $now ??= time();
    foreach (RESURRECTION_ACCOUNT_MAIL_PURPOSE_LIMITS[$purpose] ?? throw new InvalidArgumentException('Unknown account mail purpose.') as $name) {
        [$what, $purposes, $mailOnly, $window, ] = RESURRECTION_ACCOUNT_MAIL_LIMITS[$name];
        $where = ['date>?'];
        $parameters = [date('Y-m-d H:i:s', $now - $window)];
        if ($purposes !== null) {
            $where[] = 'purpose IN (' . implode(',', array_fill(0, count($purposes), '?')) . ')';
            array_push($parameters, ...$purposes);
        } else {
            // Every mail purpose; request rows never count against a mail limit.
            $mail = ['mailtest', 'recover', 'verify', 'email', 'notice'];
            $where[] = 'purpose IN (' . implode(',', array_fill(0, count($mail), '?')) . ')';
            array_push($parameters, ...$mail);
        }
        if ($mailOnly) { $where[] = "result IN ('sent','refused')"; }
        elseif ($what === 'requester' || $what === 'account') { $where[] = "result<>'limited'"; }
        if ($what === 'requester') { $where[] = 'requesterkey=?'; $parameters[] = resurrection_account_mail_requester(); }
        elseif ($what === 'account') { $where[] = 'acctid=?'; $parameters[] = $acctid; }
        elseif ($what === 'address') { $where[] = 'addresskey=?'; $parameters[] = resurrection_account_mail_address_key($address); }
        $rows = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('account_mail_log')) . ' WHERE ' . implode(' AND ', $where), true, $parameters);
        if ((int)($rows[0]['n'] ?? 0) >= resurrection_account_mail_limit_max($name)) {
            if ($name === 'global-hour') resurrection_account_mail_global_notice();
            return $name;
        }
    }
    return null;
}

/** One game log line an hour while the global limit holds mail back (by the clock gamelog() writes its date with). */
function resurrection_account_mail_global_notice(): void {
    $rows = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('gamelog')) . ' WHERE category=? AND message=? AND date>?', true,
        ['account mail', 'The hourly account mail limit was reached; more account mail waits for the next hour.', date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)($rows[0]['n'] ?? 0) === 0) gamelog('The hourly account mail limit was reached; more account mail waits for the next hour.', 'account mail');
}

/** A token's stored form: its SHA-256, hex. A slow hash is not needed for 256 random bits. */
function resurrection_account_token_hash(#[\SensitiveParameter] string $token): string {
    return hash('sha256', $token);
}

/** Whether a value has a token's shape (43 base64url characters); checked before any query. */
function resurrection_account_token_shape(#[\SensitiveParameter] mixed $token): bool {
    return is_string($token) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
}

/**
 * Make a token for an account, a purpose and the address its mail goes to, bound to the account's authversion now.
 * A new recovery token replaces any live one of the account. Returns the token; only its hash is stored.
 */
function resurrection_account_token_issue(int $acctid, string $purpose, string $address, ?int $now = null): string {
    if (!isset(RESURRECTION_ACCOUNT_TOKEN_LIFETIMES[$purpose])) throw new InvalidArgumentException('Unknown token purpose.');
    if (!resurrection_mail_address_valid($address)) throw new InvalidArgumentException('Invalid address.');
    $now ??= time();
    $rows = db_query('SELECT authversion FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=?', true, [$acctid]);
    if (count($rows) !== 1) throw new DomainException('Account removed.');
    if ($purpose === 'recover') {
        db_query('UPDATE ' . db_identifier(db_prefix('account_tokens')) . ' SET used=? WHERE acctid=? AND purpose=? AND used IS NULL', true,
            [date('Y-m-d H:i:s', $now), $acctid, 'recover']);
    }
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    db_query('INSERT INTO ' . db_identifier(db_prefix('account_tokens')) . ' (acctid,purpose,tokenhash,address,authversion,created,expires,opened,used) VALUES (?,?,?,?,?,?,?,0,NULL)', true,
        [$acctid, $purpose, resurrection_account_token_hash($token), $address, (int)$rows[0]['authversion'], date('Y-m-d H:i:s', $now),
            date('Y-m-d H:i:s', $now + RESURRECTION_ACCOUNT_TOKEN_LIFETIMES[$purpose])]);
    return $token;
}

/**
 * Open a token's link: the shape first, then the hash, then whether it is live (unused, unexpired, opened fewer than 5
 * times, and its account's authversion still the one it was made with). A live token's opening is counted. Returns
 * the token's row (never the token), or null for every reason it is refused.
 * @param list<string> $purposes the purposes this caller accepts
 * @return array{tokenid: int, acctid: int, purpose: string, address: string, tokenhash: string}|null
 */
function resurrection_account_token_open(#[\SensitiveParameter] mixed $token, array $purposes, ?int $now = null): ?array {
    if (!resurrection_account_token_shape($token)) return null;
    $now ??= time();
    $at = date('Y-m-d H:i:s', $now);
    $hash = resurrection_account_token_hash($token);
    $tokens = db_identifier(db_prefix('account_tokens'));
    $rows = db_query('SELECT t.tokenid,t.acctid,t.purpose,t.address,t.tokenhash FROM ' . $tokens . ' t JOIN ' . db_identifier(db_prefix('accounts'))
        . ' a ON a.acctid=t.acctid AND a.authversion=t.authversion WHERE t.tokenhash=? AND t.used IS NULL AND t.expires>? AND t.opened<?', true,
        [$hash, $at, RESURRECTION_ACCOUNT_TOKEN_OPENS]);
    if (count($rows) !== 1 || !in_array($rows[0]['purpose'], $purposes, true) || !hash_equals((string)$rows[0]['tokenhash'], $hash)) return null;
    db_query('UPDATE ' . $tokens . ' SET opened=opened+1 WHERE tokenid=? AND used IS NULL AND expires>? AND opened<?', true,
        [(int)$rows[0]['tokenid'], $at, RESURRECTION_ACCOUNT_TOKEN_OPENS]);
    if (db_affected_rows() !== 1) return null;
    return ['tokenid' => (int)$rows[0]['tokenid'], 'acctid' => (int)$rows[0]['acctid'], 'purpose' => (string)$rows[0]['purpose'],
        'address' => (string)$rows[0]['address'], 'tokenhash' => $hash];
}

/**
 * Inside the caller's transaction: lock a token by its id and hash (from a grant) and its account, and check both are
 * still live: unused, unexpired, of the purpose, and the account's authversion the one it was made with. Throws
 * DomainException otherwise. The caller makes its change and then calls resurrection_account_token_spend().
 * @return array{token: array<string, mixed>, account: array<string, mixed>}
 */
function resurrection_account_token_claim(int $tokenid, string $hash, string $purpose, ?int $now = null): array {
    $connection = $GLOBALS['dbinfo']['connection'];
    if (!$connection->inTransaction()) throw new LogicException('A token is claimed inside a transaction.');
    $now ??= time();
    $rows = db_query('SELECT tokenid,acctid,purpose,tokenhash,address,authversion FROM ' . db_identifier(db_prefix('account_tokens'))
        . ' WHERE tokenid=? AND used IS NULL AND expires>? FOR UPDATE', true, [$tokenid, date('Y-m-d H:i:s', $now)]);
    if (count($rows) !== 1 || $rows[0]['purpose'] !== $purpose || !hash_equals((string)$rows[0]['tokenhash'], $hash)) throw new DomainException('Expired link.');
    $accounts = db_query('SELECT acctid,login,emailaddress,emailverified,authversion,locked,superuser FROM ' . db_identifier(db_prefix('accounts'))
        . ' WHERE acctid=? FOR UPDATE', true, [(int)$rows[0]['acctid']]);
    if (count($accounts) !== 1 || (int)$accounts[0]['authversion'] !== (int)$rows[0]['authversion']) throw new DomainException('Expired link.');
    return ['token' => $rows[0], 'account' => $accounts[0]];
}

/** Inside the caller's transaction, after its change: the token is used up. */
function resurrection_account_token_spend(int $tokenid, ?int $now = null): void {
    db_query('UPDATE ' . db_identifier(db_prefix('account_tokens')) . ' SET used=? WHERE tokenid=? AND used IS NULL', true,
        [date('Y-m-d H:i:s', $now ?? time()), $tokenid]);
    if (db_affected_rows() !== 1) throw new DomainException('Expired link.');
}

/**
 * Store the grant an opened link makes, in a new session id: the purpose, the token's id and hash (never the token),
 * and when it expires. One grant at a time.
 * @param array{tokenid: int, purpose: string, tokenhash: string} $opened the row resurrection_account_token_open() returned
 */
function resurrection_account_grant_store(array $opened, ?int $now = null): void {
    resurrection_rotate_session();
    $_SESSION['account_grant'] = ['purpose' => $opened['purpose'], 'tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash'],
        'expires' => ($now ?? time()) + RESURRECTION_ACCOUNT_GRANT_LIFETIME];
}

/**
 * The session's live grant for a purpose, or null.
 * @return array{purpose: string, tokenid: int, hash: string, expires: int}|null
 */
function resurrection_account_grant(string $purpose, ?int $now = null): ?array {
    $grant = $_SESSION['account_grant'] ?? null;
    if (!is_array($grant) || ($grant['purpose'] ?? null) !== $purpose || !is_int($grant['tokenid'] ?? null) || !is_string($grant['hash'] ?? null)
        || !preg_match('/\A[0-9a-f]{64}\z/', $grant['hash']) || !is_int($grant['expires'] ?? null) || $grant['expires'] <= ($now ?? time())) return null;
    return $grant;
}

function resurrection_account_grant_clear(): void {
    unset($_SESSION['account_grant']);
}

/** A link for a token, from the https:// serverurl only; null when there is none. */
function resurrection_account_mail_link(#[\SensitiveParameter] string $token): ?string {
    $base = resurrection_account_mail_base();
    return $base === null ? null : $base . 'account.php?op=open&t=' . $token;
}

/**
 * Hand one account mail to the transport (resurrection_send_email()), from the account mail sender, and record it.
 * The caller checked the limits. True only when the transport accepted it; a refusal writes a fixed game log line.
 */
function resurrection_account_mail_deliver(string $purpose, int $acctid, string $address, string $subject, #[\SensitiveParameter] string $body): bool {
    $sender = resurrection_account_mail_sender();
    $sent = $sender !== null && resurrection_mail_address_valid($address) && resurrection_send_email($address, $subject, $body, $sender);
    resurrection_account_mail_log($purpose, $sent ? 'sent' : 'refused', $acctid, $address);
    if (!$sent) gamelog('Account mail (' . $purpose . ') could not be handed to the mail transport.', 'account mail');
    return $sent;
}

/**
 * Send the test mail (section 2.5) to an administrator's own account address. Returns the outcome: sent, refused,
 * no-address (the account has no valid address), not-ready (no https:// serverurl or no usable sender) or limited.
 */
function resurrection_account_mail_test_send(int $acctid, ?int $now = null): string {
    $now ??= time();
    $rows = db_query('SELECT acctid,login,emailaddress FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=?', true, [$acctid]);
    if (count($rows) !== 1) throw new DomainException('Account removed.');
    $address = (string)$rows[0]['emailaddress'];
    if (!resurrection_mail_address_valid($address)) return 'no-address';
    $base = resurrection_account_mail_base();
    $sender = resurrection_account_mail_sender();
    if ($base === null || $sender === null) return 'not-ready';
    if (resurrection_account_mail_limited('mailtest', $acctid, $address, $now) !== null) {
        resurrection_account_mail_log('mailtest', 'limited', $acctid, $address, $now);
        return 'limited';
    }
    $token = resurrection_account_token_issue($acctid, 'mailtest', $address, $now);
    $tokenid = db_insert_id();
    savesetting('account_mail_pending', json_encode(['tokenid' => $tokenid, 'fp' => resurrection_account_mail_fingerprint($base, $sender)], JSON_THROW_ON_ERROR));
    $link = resurrection_account_mail_link($token);
    $body = "This is a test mail from Legend of the Green Dragon at " . $base . ", sent from Game Settings by the administrator "
        . $rows[0]['login'] . ".\n\n"
        . "To confirm that mail from the game arrives and that its links reach the game, open this link within 24 hours and press Confirm on the page it shows:\n\n"
        . $link . "\n\n"
        . "If you did not ask for this mail, ignore it. Nothing changes unless the link is opened and confirmed.\n";
    unset($token, $link);
    $sent = resurrection_account_mail_deliver('mailtest', $acctid, $address, 'Test mail from the game', $body);
    unset($body);
    gamelog($sent ? 'Test mail handed to the mail transport' : 'Test mail refused by the mail transport', 'account mail');
    return $sent ? 'sent' : 'refused';
}

/**
 * Confirm a test mail through its grant (section 2.5), in one transaction: the token is live and its account still
 * holds the address it went to; the test mail is the pending one, sent with the serverurl and sender in use now. Stores
 * account_mail_proof, marks that account's address verified, and uses the token up. Returns the confirming account's
 * login. Throws DomainException for every reason it is refused.
 * @param array{tokenid: int, hash: string} $grant
 */
function resurrection_account_mail_test_confirm(array $grant, ?int $now = null): string {
    $now ??= time();
    $connection = $GLOBALS['dbinfo']['connection'];
    $connection->beginTransaction();
    try {
        $claimed = resurrection_account_token_claim($grant['tokenid'], $grant['hash'], 'mailtest', $now);
        $account = $claimed['account'];
        if ((string)$account['emailaddress'] !== (string)$claimed['token']['address']) throw new DomainException('Expired link.');
        $pending = json_decode(resurrection_account_mail_setting('account_mail_pending'), true);
        $base = resurrection_account_mail_base();
        $sender = resurrection_account_mail_sender();
        if (!is_array($pending) || ($pending['tokenid'] ?? null) !== (int)$claimed['token']['tokenid'] || $base === null || $sender === null
            || !is_string($pending['fp'] ?? null) || !hash_equals($pending['fp'], resurrection_account_mail_fingerprint($base, $sender))) {
            throw new DomainException('Expired link.');
        }
        db_query('UPDATE ' . db_identifier(db_prefix('accounts')) . ' SET emailverified=emailaddress WHERE acctid=? AND emailaddress=? AND authversion=?', true,
            [(int)$account['acctid'], (string)$claimed['token']['address'], (int)$account['authversion']]);
        resurrection_account_token_spend((int)$claimed['token']['tokenid'], $now);
        db_query('INSERT INTO ' . db_identifier(db_prefix('settings')) . ' (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true,
            array_merge(['account_mail_proof'], array_fill(0, 2, json_encode(['time' => gmdate('Y-m-d H:i:s', $now), 'acctid' => (int)$account['acctid'],
                'fp' => resurrection_account_mail_fingerprint($base, $sender)], JSON_THROW_ON_ERROR))));
        db_query('DELETE FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', true, ['account_mail_pending']);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    $GLOBALS['settings'] = null;
    invalidatedatacache('game-settings');
    gamelog('Test mail confirmed through its link', 'account mail');
    return (string)$account['login'];
}

/**
 * Account mail's status for Game Settings (section 2.5).
 * @return array{switch: bool, on: bool, blockers: list<string>, base: ?string, serverurl: string, sender: string, proof: array{time: string, acctid: int, fp: string}|null, proof_login: string, last_test: array{date: string, result: string}|null, sent: int, refused: int, hourly: int}
 */
function resurrection_account_mail_status(?int $now = null): array {
    global $settings;
    $now ??= time();
    if (!is_array($settings)) loadsettings();
    $blockers = resurrection_account_mail_blockers();
    $proof = resurrection_account_mail_proof();
    $login = '';
    if ($proof !== null) {
        $rows = db_query('SELECT login FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=?', true, [$proof['acctid']]);
        $login = (string)($rows[0]['login'] ?? '');
    }
    $log = db_identifier(db_prefix('account_mail_log'));
    $last = db_query('SELECT date,result FROM ' . $log . " WHERE purpose='mailtest' AND result IN ('sent','refused') ORDER BY eventid DESC LIMIT 1");
    $counts = db_query('SELECT result,COUNT(*) AS n FROM ' . $log . " WHERE date>? AND result IN ('sent','refused') GROUP BY result", true, [date('Y-m-d H:i:s', $now - 86400)]);
    $counts = array_column($counts, 'n', 'result');
    $switch = resurrection_account_mail_switch();
    return ['switch' => $switch, 'on' => $switch && $blockers === [], 'blockers' => $blockers, 'base' => resurrection_account_mail_base(),
        'serverurl' => (string)($settings['serverurl'] ?? ''), 'sender' => (string)($settings['gameadminemail'] ?? ''), 'proof' => $proof, 'proof_login' => $login,
        'last_test' => isset($last[0]) ? ['date' => (string)$last[0]['date'], 'result' => (string)$last[0]['result']] : null, 'sent' => (int)($counts['sent'] ?? 0), 'refused' => (int)($counts['refused'] ?? 0),
        'hourly' => (int)getsetting('account_mail_hourly', 100)];
}

/**
 * Maintenance (section 3.2, 5): tokens used or expired more than 7 days ago, and log rows older than 30 days.
 * @return array{tokens: int, log: int}
 */
function resurrection_account_mail_cleanup(?int $now = null): array {
    $now ??= time();
    $week = date('Y-m-d H:i:s', $now - 7 * 86400);
    db_query('DELETE FROM ' . db_identifier(db_prefix('account_tokens')) . ' WHERE used<? OR expires<?', true, [$week, $week]);
    $tokens = db_affected_rows();
    db_query('DELETE FROM ' . db_identifier(db_prefix('account_mail_log')) . ' WHERE date<?', true, [date('Y-m-d H:i:s', $now - 30 * 86400)]);
    return ['tokens' => $tokens, 'log' => db_affected_rows()];
}

/**
 * Whether a notification mail (in-game mail, the expiration warning) may go to an address (decision 7, F3; Phase 6
 * task 13c): always while account mail is off, as in 1.1.2; while it is on, only to an address verified for the
 * account, so a player cannot point a stranger's inbox at the game.
 */
function resurrection_account_mail_notify_allowed(string $address, string $verified): bool {
    if (!resurrection_account_mail_on()) return true;
    return $address !== '' && $verified !== '' && strtolower($verified) === strtolower($address);
}
