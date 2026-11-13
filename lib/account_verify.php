<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Email verification and the email change (Phase 6 task 13c, S11 part 3; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md,
 * sections 4, 6 and 12, with decisions 4, 5 and 7 and the verify prompt Jim added).
 *
 * - An account is verified for an address when accounts.emailverified holds exactly its current emailaddress (4). Any
 *   path that changes the address leaves the account unverified.
 * - Verify (4.1, 4.2): a verify token (24 hours) mailed to the account's current address; confirming it by POST sets
 *   emailverified and, for a pending sign-up, clears the rz-pending marker, in one transaction with the address and
 *   authversion conditions. A new verify token replaces the earlier one.
 * - Sign-up (4.1, decision 4): while requirevalidemail is effectively on, an account is made with emailvalidation
 *   'rz-pending' and cannot sign in while account mail is on until it is confirmed. After a correct password the
 *   refusal offers to send the link again, behind a 10-minute grant from that sign-in.
 * - The email change (4.2, F2): the current password, a valid new address and blockdupeemail. While account mail is on,
 *   nothing changes until the link mailed to the new address is confirmed; a notice goes to the old verified address,
 *   naming neither the new address nor any link, when it is asked and when it is done. The token is bound to the new
 *   address (where its mail goes), and a companion row, purpose 'email-from', holds the address the account had when
 *   it was asked, so the confirmation can require that it still has it. While account mail is off, a change takes
 *   effect at once and leaves the account unverified.
 * - Notification mail (decision 7, F3): while account mail is on, only to a verified address.
 * - The verify prompt: while account mail is on, a signed-in player with an address that is not verified sees one line
 *   pointing to Preferences on every page that links to Preferences, until they dismiss it for that address.
 *
 * Tokens, grants, limits and the transport are account mail's (lib/account_mail.php).
 */
require_once __DIR__ . '/account_mail.php';
require_once __DIR__ . '/../src/Security/Passwords.php';

/** The emailvalidation marker of a sign-up that has not confirmed its address (4.1; never a token). */
const RESURRECTION_ACCOUNT_PENDING = 'rz-pending';
/** How long the grant a correct sign-in to a pending account makes for sending the link again lasts (seconds, 4.1). */
const RESURRECTION_ACCOUNT_RESEND_LIFETIME = 600;
/** The prefs key that holds the address key of the address the verify prompt was dismissed for. */
const RESURRECTION_ACCOUNT_PROMPT_PREF = 'verifyprompt';

/** Whether an address is verified: not empty, and emailverified holds it (letter case aside, as recovery reads it). */
function resurrection_account_address_verified(string $address, string $verified): bool {
    return $address !== '' && $verified !== '' && strtolower($verified) === strtolower($address);
}

/** The host part of the server URL, for a mail that must carry no link (the email change notices). */
function resurrection_account_mail_game_host(): string {
    $base = (string)resurrection_account_mail_base();
    return (string)(parse_url($base, PHP_URL_HOST) ?? '');
}

/**
 * Mark every live token of the account for the given purposes used (a new verify or change request replaces the
 * earlier one; a change of address kills the links for the old one).
 * @param list<string> $purposes
 */
function resurrection_account_tokens_retire(int $acctid, array $purposes, ?int $now = null): void {
    db_query('UPDATE ' . db_identifier(db_prefix('account_tokens')) . ' SET used=? WHERE acctid=? AND used IS NULL AND purpose IN ('
        . implode(',', array_fill(0, count($purposes), '?')) . ')', true, [date('Y-m-d H:i:s', $now ?? time()), $acctid, ...$purposes]);
}

/**
 * Send a verify link to the account's current address (4.1 sign-up and resend, 4.2 Preferences). Returns: off (account
 * mail is not on), no-address, verified (nothing to do), limited, sent or refused.
 */
function resurrection_account_verify_send(int $acctid, ?int $now = null): string {
    $now ??= time();
    if (!resurrection_account_mail_on()) return 'off';
    $rows = db_query('SELECT acctid,login,emailaddress,emailverified,emailvalidation FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=?', true, [$acctid]);
    if (count($rows) !== 1) return 'no-address';
    $account = $rows[0];
    $address = (string)$account['emailaddress'];
    if (!resurrection_mail_address_valid($address)) return 'no-address';
    if (resurrection_account_address_verified($address, (string)$account['emailverified']) && (string)$account['emailvalidation'] !== RESURRECTION_ACCOUNT_PENDING) return 'verified';
    if (resurrection_account_mail_limited('verify', $acctid, $address, $now) !== null) {
        resurrection_account_mail_log('verify', 'limited', $acctid, '', $now);
        return 'limited';
    }
    resurrection_account_tokens_retire($acctid, ['verify'], $now);
    $token = resurrection_account_token_issue($acctid, 'verify', $address, $now);
    $link = resurrection_account_mail_link($token);
    $pending = (string)$account['emailvalidation'] === RESURRECTION_ACCOUNT_PENDING;
    $body = ($pending ? 'An account named ' . $account['login'] . ' was made with this address at ' : 'Someone asked to confirm this address for the account ' . $account['login'] . ' at ')
        . resurrection_account_mail_base() . ".\n\n"
        . "To confirm it, open this link within 24 hours and press Confirm on the page it shows:\n\n" . $link . "\n\n"
        . ($pending ? "The account can sign in once the address is confirmed.\n\n" : '')
        . "If you did not ask for this, ignore this mail. Nothing changes unless the link is opened and confirmed.\n";
    unset($token, $link);
    $sent = resurrection_account_mail_deliver('verify', $acctid, $address, 'Confirm your email address', $body);
    unset($body);
    return $sent ? 'sent' : 'refused';
}

/**
 * The checks every address change makes (4.2, F2): the current password, then the new address. Returns null when the
 * change may go ahead, else the refusal: password, invalid, same, taken (blockdupeemail) or required (requireemail and
 * an empty address). An empty new address (removing it) is allowed only while account mail is off.
 * @param array<string, mixed> $account the row with acctid, password and emailaddress
 */
function resurrection_account_email_change_refusal(array $account, #[\SensitiveParameter] mixed $password, string $new, bool $mailOn): ?string {
    if (!is_string($password) || $password === '' || !\Resurrection\Security\Passwords::verify($password, (string)$account['password'])) return 'password';
    if ($new === '') {
        if ($mailOn) return 'invalid';
        return (int)getsetting('requireemail', 0) === 1 ? 'required' : (((string)$account['emailaddress'] === '') ? 'same' : null);
    }
    if (!resurrection_mail_address_valid($new)) return 'invalid';
    if (strtolower($new) === strtolower((string)$account['emailaddress'])) return 'same';
    if ((int)getsetting('blockdupeemail', 0) === 1) {
        $taken = db_query('SELECT acctid FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE LOWER(emailaddress)=LOWER(?) AND acctid<>? LIMIT 1', true, [$new, (int)$account['acctid']]);
        if ($taken !== []) return 'taken';
    }
    return null;
}

/**
 * Change the address at once (account mail off, 4.2): the current password, the anchored validator and blockdupeemail;
 * the account is left unverified, and every live verify and change link of the account dies. Returns changed or the
 * refusal (see resurrection_account_email_change_refusal()).
 */
function resurrection_account_email_change_now(int $acctid, #[\SensitiveParameter] mixed $password, string $new, ?int $now = null): string {
    $connection = $GLOBALS['dbinfo']['connection'];
    $connection->beginTransaction();
    try {
        $rows = db_query('SELECT acctid,password,emailaddress,authversion FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=? FOR UPDATE', true, [$acctid]);
        if (count($rows) !== 1) throw new DomainException('Account removed.');
        $refusal = resurrection_account_email_change_refusal($rows[0], $password, $new, false);
        if ($refusal !== null) { $connection->rollBack(); return $refusal; }
        db_query('UPDATE ' . db_identifier(db_prefix('accounts')) . " SET emailaddress=?,emailverified='' WHERE acctid=? AND authversion=?", true,
            [$new, $acctid, (int)$rows[0]['authversion']]);
        resurrection_account_tokens_retire($acctid, ['verify', 'email', 'email-from'], $now);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    gamelog('Email address changed in Preferences (account mail off) for account ' . $acctid, 'account mail');
    return 'changed';
}

/**
 * Ask for an address change while account mail is on (4.2): nothing changes yet. After the checks and the limits, an
 * email token (24 hours) for the new address and its companion row holding the current address replace any earlier
 * request; the link goes to the new address, and a notice (no new address, no link) to the old address if it is
 * verified. Returns: off, the refusal (see resurrection_account_email_change_refusal()), limited, sent or refused.
 */
function resurrection_account_email_change_request(int $acctid, #[\SensitiveParameter] mixed $password, string $new, ?int $now = null): string {
    $now ??= time();
    if (!resurrection_account_mail_on()) return 'off';
    $rows = db_query('SELECT acctid,login,password,emailaddress,emailverified FROM ' . db_identifier(db_prefix('accounts')) . ' WHERE acctid=?', true, [$acctid]);
    if (count($rows) !== 1) return 'password';
    $account = $rows[0];
    $refusal = resurrection_account_email_change_refusal($account, $password, $new, true);
    if ($refusal !== null) return $refusal;
    if (resurrection_account_mail_limited('email-request', $acctid, '', $now) !== null) {
        resurrection_account_mail_log('email-request', 'limited', $acctid, '', $now);
        return 'limited';
    }
    resurrection_account_mail_log('email-request', 'counted', $acctid, '', $now);
    if (resurrection_account_mail_limited('email', $acctid, $new, $now) !== null) {
        resurrection_account_mail_log('email', 'limited', $acctid, '', $now);
        return 'limited';
    }
    $old = (string)$account['emailaddress'];
    $tokens = db_identifier(db_prefix('account_tokens'));
    $connection = $GLOBALS['dbinfo']['connection'];
    $connection->beginTransaction();
    try {
        resurrection_account_tokens_retire($acctid, ['email', 'email-from'], $now);
        $token = resurrection_account_token_issue($acctid, 'email', $new, $now);
        $tokenid = db_insert_id();
        db_query('INSERT INTO ' . $tokens . " (acctid,purpose,tokenhash,address,authversion,created,expires,opened,used) SELECT acctid,'email-from',?,?,authversion,created,expires,0,NULL FROM "
            . $tokens . ' WHERE tokenid=?', true, [hash('sha256', random_bytes(32)), $old, $tokenid]);
        if (db_affected_rows() !== 1) throw new DomainException('Token removed.');
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    $link = resurrection_account_mail_link($token);
    unset($token);
    $body = 'Someone asked to make this the email address of the account ' . $account['login'] . ' at ' . resurrection_account_mail_base() . ".\n\n"
        . "To confirm it, open this link within 24 hours and press Confirm on the page it shows:\n\n" . $link . "\n\n"
        . "The address changes only when it is confirmed. If you did not ask for this, ignore this mail.\n";
    unset($link);
    $sent = resurrection_account_mail_deliver('email', $acctid, $new, 'Confirm your new email address', $body);
    unset($body);
    gamelog('Email change requested for account ' . $acctid, 'account mail');
    if (resurrection_account_address_verified($old, (string)$account['emailverified'])) {
        resurrection_account_email_notice($acctid, (string)$account['login'], $old, false, $now);
    }
    return $sent ? 'sent' : 'refused';
}

/**
 * A notice to the old verified address about a change (4.2): asked ($done false) or done. It names neither the new
 * address nor any link, within the address and global limits.
 */
function resurrection_account_email_notice(int $acctid, string $login, string $address, bool $done, ?int $now = null): bool {
    $now ??= time();
    if (resurrection_account_mail_limited('notice', $acctid, $address, $now) !== null) {
        resurrection_account_mail_log('notice', 'limited', $acctid, '', $now);
        return false;
    }
    $when = gmdate('Y-m-d H:i', $now) . ' UTC';
    $game = 'Legend of the Green Dragon (' . resurrection_account_mail_game_host() . ')';
    $body = $done
        ? 'The email address of the account ' . $login . ' in ' . $game . ' was changed to another address at ' . $when . ".\n\n"
            . "Mail for the account now goes to the new address. If this was not you, sign in, change your password and change the address back, or ask the game's administrator for help.\n"
        : 'A change of the email address of the account ' . $login . ' in ' . $game . ' to another address was requested at ' . $when . ".\n\n"
            . "If this was not you, sign in and change your password; that cancels the change.\n";
    return resurrection_account_mail_deliver('notice', $acctid, $address, $done ? 'Your email address was changed' : 'A change of your email address was requested', $body);
}

/**
 * The confirmation page's facts for a verify or change grant whose token is still live: the account's id and login
 * and the address to confirm. Null when the link no longer works.
 * @param array{purpose: string, tokenid: int, hash: string} $grant
 * @return array{acctid: int, login: string, address: string}|null
 */
function resurrection_account_confirm_view(array $grant, ?int $now = null): ?array {
    $rows = db_query('SELECT a.acctid,a.login,t.address FROM ' . db_identifier(db_prefix('account_tokens')) . ' t JOIN ' . db_identifier(db_prefix('accounts'))
        . ' a ON a.acctid=t.acctid AND a.authversion=t.authversion WHERE t.tokenid=? AND t.tokenhash=? AND t.purpose=? AND t.used IS NULL AND t.expires>?', true,
        [$grant['tokenid'], $grant['hash'], $grant['purpose'], date('Y-m-d H:i:s', $now ?? time())]);
    return count($rows) === 1 ? ['acctid' => (int)$rows[0]['acctid'], 'login' => (string)$rows[0]['login'], 'address' => (string)$rows[0]['address']] : null;
}

/**
 * Confirm a verify or change link through its grant (4.1, 4.2), in one transaction: the token locked and live (unused,
 * unexpired, the account's authversion still the token's), account mail on, and
 * - verify: the account still holds the address the mail went to; emailverified is set to it, and a pending sign-up's
 *   marker is cleared;
 * - email: the companion row live and the account still holding the address it had when the change was asked, and
 *   (blockdupeemail) no other account holding the new one; emailaddress and emailverified are set to the new address;
 * on the authversion and address conditions. The token (and its companion) is used up, and so is every other live
 * verify link of the account. Throws DomainException for every reason the link no longer works.
 * @param array{purpose: string, tokenid: int, hash: string} $grant
 * @return array{acctid: int, login: string, purpose: string, address: string, old: string, oldverified: bool, pending: bool}
 */
function resurrection_account_confirm(array $grant, ?int $now = null): array {
    $now ??= time();
    $purpose = $grant['purpose'];
    if (!in_array($purpose, ['verify', 'email'], true) || !resurrection_account_mail_on()) throw new DomainException('Expired link.');
    $accounts = db_identifier(db_prefix('accounts'));
    $tokens = db_identifier(db_prefix('account_tokens'));
    $connection = $GLOBALS['dbinfo']['connection'];
    $connection->beginTransaction();
    try {
        $claimed = resurrection_account_token_claim($grant['tokenid'], $grant['hash'], $purpose, $now);
        $account = $claimed['account'];
        $token = $claimed['token'];
        $acctid = (int)$account['acctid'];
        $state = db_query('SELECT emailvalidation FROM ' . $accounts . ' WHERE acctid=?', true, [$acctid]);
        $pending = (string)($state[0]['emailvalidation'] ?? '') === RESURRECTION_ACCOUNT_PENDING;
        $old = (string)$account['emailaddress'];
        $oldverified = resurrection_account_address_verified($old, (string)$account['emailverified']);
        if ($purpose === 'verify') {
            if ($old !== (string)$token['address']) throw new DomainException('Expired link.');
            db_query('UPDATE ' . $accounts . ' SET emailverified=emailaddress,emailvalidation=IF(emailvalidation=?,\'\',emailvalidation) WHERE acctid=? AND emailaddress=? AND authversion=?', true,
                [RESURRECTION_ACCOUNT_PENDING, $acctid, $old, (int)$account['authversion']]);
            $address = $old;
        } else {
            // A new request retires the earlier pair, so the account has at most one live companion: the one made with this token.
            $from = db_query('SELECT tokenid,address FROM ' . $tokens . " WHERE acctid=? AND purpose='email-from' AND used IS NULL AND expires>? AND authversion=? FOR UPDATE", true,
                [$acctid, date('Y-m-d H:i:s', $now), (int)$token['authversion']]);
            if (count($from) !== 1 || (string)$from[0]['address'] !== $old) throw new DomainException('Expired link.');
            $address = (string)$token['address'];
            if ((int)getsetting('blockdupeemail', 0) === 1
                && db_query('SELECT acctid FROM ' . $accounts . ' WHERE LOWER(emailaddress)=LOWER(?) AND acctid<>? LIMIT 1', true, [$address, $acctid]) !== []) {
                throw new DomainException('Expired link.');
            }
            db_query('UPDATE ' . $accounts . ' SET emailaddress=?,emailverified=? WHERE acctid=? AND emailaddress=? AND authversion=?', true,
                [$address, $address, $acctid, $old, (int)$account['authversion']]);
            if (db_affected_rows() !== 1) throw new DomainException('Expired link.');
            db_query('UPDATE ' . $tokens . ' SET used=? WHERE tokenid=? AND used IS NULL', true, [date('Y-m-d H:i:s', $now), (int)$from[0]['tokenid']]);
        }
        resurrection_account_token_spend((int)$token['tokenid'], $now);
        resurrection_account_tokens_retire($acctid, ['verify'], $now);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
    gamelog(($purpose === 'verify' ? 'Email address verified' : 'Email address changed through a confirmation link') . ' for account ' . $acctid, 'account mail');
    return ['acctid' => $acctid, 'login' => (string)$account['login'], 'purpose' => $purpose, 'address' => $address, 'old' => $old,
        'oldverified' => $oldverified, 'pending' => $pending];
}

/**
 * The 10-minute grant a correct password to a pending account makes (4.1): only someone with the password may ask
 * for the link again. Stored in a new session id.
 */
function resurrection_account_resend_grant_store(int $acctid, ?int $now = null): void {
    resurrection_rotate_session();
    $_SESSION['account_resend'] = ['acctid' => $acctid, 'expires' => ($now ?? time()) + RESURRECTION_ACCOUNT_RESEND_LIFETIME];
}

/** The session's live resend grant's account id, or null. */
function resurrection_account_resend_grant(?int $now = null): ?int {
    $grant = $_SESSION['account_resend'] ?? null;
    if (!is_array($grant) || !is_int($grant['acctid'] ?? null) || !is_int($grant['expires'] ?? null) || $grant['expires'] <= ($now ?? time())) return null;
    return $grant['acctid'];
}

/**
 * The verify prompt's line for the signed-in player, or '' when it is not shown: account mail on, an address that is
 * not empty and not verified, not dismissed for that address, and a Preferences link on the page ($prefsNav, the
 * allowed nav to link to; null when the page has none). Never on account.php or an operator page (the caller).
 * @param array<string, mixed> $user the session's account
 */
function resurrection_account_verify_prompt(array $user, ?string $prefsNav): string {
    if ($prefsNav === null) return '';
    $address = (string)($user['emailaddress'] ?? '');
    if ($address === '' || resurrection_account_address_verified($address, (string)($user['emailverified'] ?? ''))) return '';
    $prefs = is_array($user['prefs'] ?? null) ? $user['prefs'] : [];
    if ((string)($prefs[RESURRECTION_ACCOUNT_PROMPT_PREF] ?? '') === resurrection_account_mail_address_key($address)) return '';
    if (!resurrection_account_mail_on()) return '';
    return '<div class="rz-verify-prompt" role="note"><p>Password recovery by email works only for a verified address, and yours is not verified. '
        . '<a href="' . htmlspecialchars($prefsNav, ENT_QUOTES, 'UTF-8') . '">Verify it in Preferences</a>.</p>'
        . '<form action="prefs.php?op=dismissverify" method="POST">' . resurrection_csrf_field() . '<button class="button">Dismiss</button></form></div>';
}
