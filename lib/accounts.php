<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/../src/Security/Passwords.php';

function resurrection_validate_account(string $login, string $email): void {
    if (!preg_match('/\A[A-Za-z][A-Za-z0-9]{2,24}\z/', $login)) {
        throw new InvalidArgumentException('Use 3 to 25 letters and digits, starting with a letter.');
    }
    if ($email !== '' && (strlen($email) > 128 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        throw new InvalidArgumentException('Invalid email address.');
    }
}

/**
 * Caller owns the transaction; passwords arrive already hashed by the shared policy. $pending makes a sign-up that must
 * confirm its address before it can sign in (Phase 6 task 13c, S11 section 4.1): emailvalidation holds the marker
 * 'rz-pending' (lib/account_verify.php), never a token.
 */
function resurrection_insert_account(string $login, #[\SensitiveParameter] string $hash, string $email, int $privileges = 0, int $sex = 0, bool $pending = false): int {
    resurrection_validate_account($login, $email);
    if (password_get_info($hash)['algo'] === null) { throw new InvalidArgumentException('A modern password hash is required.'); }
    $result = db_query('SELECT male,female FROM ' . db_prefix('titles') . ' WHERE dk=0 ORDER BY titleid LIMIT 1');
    $title = db_fetch_assoc($result);
    $title = $title ? $title[$sex === 1 ? 'female' : 'male'] : 'Farmboy';
    $now = date('Y-m-d H:i:s');
    $values = [
        'login' => $login, 'name' => $title . ' ' . $login, 'title' => $title,
        'password' => $hash, 'emailaddress' => $email, 'superuser' => $privileges,
        'sex' => $sex === 1 ? 1 : 0, 'location' => getsetting('villagename', LOCATION_FIELDS),
        'gold' => (int)getsetting('newplayerstartgold', 50),
        'laston' => $now, 'regdate' => $now, 'restorepage' => 'village.php',
        'bufflist' => serialize([]), 'dragonpoints' => serialize([]), 'prefs' => serialize([]),
        'companions' => serialize([]), 'allowednavs' => serialize(['village.php' => true]),
    ];
    if ($pending) $values['emailvalidation'] = 'rz-pending';
    db_query('INSERT INTO ' . db_prefix('accounts') . ' (' . implode(',', array_map('db_identifier', array_keys($values))) .
        ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')', true, array_values($values));
    $id = db_insert_id();
    db_query('INSERT INTO ' . db_prefix('accounts_output') . ' (acctid,output) VALUES (?,?)', true, [$id, '']);
    return $id;
}

function resurrection_create_account(string $login, #[\SensitiveParameter] string $password, string $email = '', int $sex = 0, bool $pending = false): int {
    $hash = \Resurrection\Security\Passwords::hash($password);
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
        $id = resurrection_insert_account($login, $hash, $email, 0, $sex, $pending);
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

/** Returns the account only after verifying a submitted plaintext password.
 * @return array<string, mixed>|false
 */
function resurrection_authenticate(string $login, #[\SensitiveParameter] mixed $password): array|false {
    return resurrection_sign_in_check($login, $password)['account'];
}

/**
 * The sign-in check: the account only after verifying a submitted plaintext password, refusing a locked account and an
 * account whose emailvalidation is not empty. A pending sign-up (emailvalidation 'rz-pending', Phase 6 task 13c, S11
 * section 4.3) is refused only while account mail is effectively on; when it is, and the password is right, its id
 * comes back as 'pending', so that the sign-in page can say "Confirm your email address first". A wrong password, a
 * missing login and a locked account all come back the same: no account, no pending id.
 * @return array{account: array<string, mixed>|false, pending: int|null}
 */
function resurrection_sign_in_check(string $login, #[\SensitiveParameter] mixed $password): array {
    require_once __DIR__ . '/signin_throttle.php';
    $row = false;
    if (resurrection_signin_name_usable($login)) {
        $result = db_query('SELECT * FROM ' . db_prefix('accounts') . ' WHERE login=? LIMIT 1', true, [$login]);
        $row = db_fetch_assoc($result);
    }
    // Every sign-in checks a password (Phase 6 task 18, F4): a name no account has is checked against a fixed hash of
    // the same cost, and a locked account's own hash is checked too, so the time taken does not show which it was.
    // Until then only an existing login's password was checked.
    $verified = \Resurrection\Security\Passwords::verify($password, $row ? $row['password'] : RESURRECTION_SIGNIN_DUMMY_HASH);
    if (!$row || (int)$row['locked'] !== 0 || !$verified) { return ['account' => false, 'pending' => null]; }
    if ($row['emailvalidation'] !== '') {
        if ($row['emailvalidation'] !== 'rz-pending') { return ['account' => false, 'pending' => null]; }
        if (resurrection_account_pending_blocked()) { return ['account' => false, 'pending' => (int)$row['acctid']]; }
    }
    if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
        $hash = \Resurrection\Security\Passwords::hash($password);
        db_query('UPDATE ' . db_prefix('accounts') . ' SET password=? WHERE acctid=?', true, [$hash, (int)$row['acctid']]);
        $row['password'] = $hash;
    }
    return ['account' => $row, 'pending' => null];
}

/**
 * Whether a pending sign-up is held back from signing in: while account mail is effectively on (decision 4). Where the
 * game's settings cannot be read (a page that does not load them), it is held back.
 */
function resurrection_account_pending_blocked(): bool {
    if (!function_exists('getsetting') || !function_exists('db_query')) { return true; }
    require_once __DIR__ . '/account_mail.php';
    return resurrection_account_mail_on();
}

/** Self-service changes require the current password; the caller rotates its session. */
function resurrection_change_password(int $id, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $next): int {
    $rows = db_query('SELECT password FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$id]);
    $account = db_fetch_assoc($rows);
    if (!$account || !\Resurrection\Security\Passwords::verify($current, $account['password'])) { throw new DomainException('Password change rejected.'); }
    $hash = \Resurrection\Security\Passwords::hash($next);
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
    db_query('UPDATE ' . db_prefix('accounts') . ' SET password=?,authversion=authversion+1 WHERE acctid=? AND password=?', true, [$hash, $id, $account['password']]);
    if (db_affected_rows() !== 1) { throw new DomainException('Password change rejected.'); }
    $rows = db_query('SELECT authversion FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$id]);
    $version = (int)db_fetch_assoc($rows)['authversion'];
    $db->commit();
    return $version;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}

/** Atomically establish a new login generation and revoke earlier sessions. */
function resurrection_begin_login(int $id, #[\SensitiveParameter] string $verifiedHash): int {
    $db = $GLOBALS['dbinfo']['connection'];
    $db->beginTransaction();
    try {
        db_query('UPDATE ' . db_prefix('accounts') . ' SET loggedin=1,laston=?,authversion=authversion+1 WHERE acctid=? AND password=? AND locked=0', true,
            [date('Y-m-d H:i:s'), $id, $verifiedHash]);
        if (db_affected_rows() !== 1) { throw new DomainException('Login state changed. Please sign in again.'); }
        $rows = db_query('SELECT authversion FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$id]);
        $version = (int)db_fetch_assoc($rows)['authversion'];
        $db->commit();
        return $version;
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
}
