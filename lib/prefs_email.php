<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Preferences' email address section (Phase 6 task 13c, S11 section 4.2, F2, and the verify prompt): the address and
 * whether it is verified; while account mail is on, "Verify my address" and "Change my address" (confirmed by a link to
 * the new address); while it is off, "Change my address" taking effect at once. Every change needs POST, CSRF and an
 * action token, and a change the current password. The verify prompt's Dismiss posts here too (POST, CSRF).
 *
 * The address is never part of the preferences form and saveuser() never writes it (lib/saveuser.php).
 */
require_once __DIR__ . '/account_verify.php';
require_once __DIR__ . '/player_mutation.php';

/** The action token context of the verify button: the account and the address it was shown for. */
function resurrection_prefs_verify_context(): string {
    global $session;
    return (int)$session['user']['acctid'] . ':' . resurrection_account_mail_address_key((string)$session['user']['emailaddress']);
}

/** The action token context of the change form: the account and its login generation. */
function resurrection_prefs_change_context(): string {
    global $session;
    return (int)$session['user']['acctid'] . ':' . (int)($_SESSION['auth_version'] ?? 0);
}

/** Handle one of the section's POSTs; returns the line to show above the section. */
function resurrection_prefs_email_op(string $op): string {
    global $session;
    // POST and CSRF for every one, even with an empty body (prefs.php checks CSRF only when something is posted).
    resurrection_require_post();
    $acctid = (int)$session['user']['acctid'];
    if ($op === 'dismissverify') {
        if (array_diff(array_keys($_POST), ['csrf_token']) !== []) { http_response_code(400); exit('Invalid request.'); }
        $session['user']['prefs'][RESURRECTION_ACCOUNT_PROMPT_PREF] = resurrection_account_mail_address_key((string)$session['user']['emailaddress']);
        return 'The reminder is hidden until your address changes.';
    }
    if ($op === 'verifyemail') {
        if (array_diff(array_keys($_POST), ['csrf_token', 'action_token']) !== []) { http_response_code(400); exit('Invalid request.'); }
        resurrection_consume_action('prefs-verify', resurrection_prefs_verify_context());
        return match (resurrection_account_verify_send($acctid)) {
            'sent' => 'A link to confirm your address is on its way to it. It works for 24 hours. Check your spam folder too.',
            'limited' => 'Too many links were sent for this account. Try again later.',
            'verified' => 'Your address is already verified.',
            'no-address' => 'Add an email address first.',
            'off' => 'Email verification is not available on this game.',
            default => 'The mail could not be sent. Try again later.',
        };
    }
    if (array_diff(array_keys($_POST), ['csrf_token', 'action_token', 'emailpassword', 'newemail']) !== []) { http_response_code(400); exit('Invalid request.'); }
    resurrection_consume_action('prefs-email', resurrection_prefs_change_context());
    $password = $_POST['emailpassword'] ?? '';
    $new = trim((string)($_POST['newemail'] ?? ''));
    unset($_POST['emailpassword'], $_REQUEST['emailpassword']);
    $on = resurrection_account_mail_on();
    $outcome = $on ? resurrection_account_email_change_request($acctid, $password, $new) : resurrection_account_email_change_now($acctid, $password, $new);
    unset($password);
    if ($outcome === 'changed') {
        $session['user']['emailaddress'] = $new;
        $session['user']['emailverified'] = '';
    }
    return match ($outcome) {
        'sent' => 'A link to confirm the new address is on its way to it. Your address changes only when you open the link and confirm it.',
        'changed' => $new === '' ? 'Your email address has been removed.' : 'Your email address has been changed. It is not verified.',
        'password' => 'Address change rejected. Check your current password.',
        'invalid' => 'That is not a valid email address.',
        'same' => 'That is already your address.',
        'taken' => 'That address cannot be used.',
        'required' => 'This game requires an email address.',
        'limited' => 'Too many address changes were asked for this account. Try again later.',
        default => 'The mail could not be sent. Try again later.',
    };
}

/** The section, above the preferences form. */
function resurrection_prefs_email_section(string $notice): void {
    global $session;
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $acctid = (int)$session['user']['acctid'];
    $address = (string)$session['user']['emailaddress'];
    $verified = resurrection_account_address_verified($address, (string)($session['user']['emailverified'] ?? ''));
    $on = resurrection_account_mail_on();
    rawoutput('<section class="rz-account rz-prefs-email" aria-labelledby="rz-prefs-email-title"><h2 id="rz-prefs-email-title">Email address</h2>');
    if ($notice !== '') rawoutput('<p class="rz-setting-refused" role="status">' . $escape($notice) . '</p>');
    rawoutput($address === '' ? '<p>Your account has no email address.</p>'
        : '<p>Your address is <strong>' . $escape($address) . '</strong>' . ($on ? ($verified ? ', verified.' : ', not verified.') : '.') . '</p>');
    if ($on) {
        $pending = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('account_tokens')) . ' t JOIN ' . db_identifier(db_prefix('accounts'))
            . " a ON a.acctid=t.acctid AND a.authversion=t.authversion WHERE t.acctid=? AND t.purpose='email' AND t.used IS NULL AND t.expires>?", true, [$acctid, date('Y-m-d H:i:s')]);
        if ((int)($pending[0]['n'] ?? 0) > 0) rawoutput('<p>A change of address is waiting: open the link mailed to the new address to confirm it.</p>');
        if ($address !== '' && !$verified) {
            rawoutput('<p>Password recovery by email works only for a verified address.</p>');
            rawoutput('<form class="rz-account-form" action="prefs.php?op=verifyemail" method="POST">' . resurrection_action_fields('prefs-verify', resurrection_prefs_verify_context())
                . '<p><input type="submit" class="' . resurrection_button_class(true) . '" value="Verify my address"></p></form>');
            addnav('', 'prefs.php?op=verifyemail');
        }
        $help = 'The address changes only when you open the link mailed to the new address and confirm it.' . ($verified ? ' A notice goes to your current address.' : '');
    } else {
        rawoutput('<p>' . $escape(RESURRECTION_ACCOUNT_RECOVERY_OFF_PREFS) . '</p>');
        $help = 'The change takes effect at once.';
    }
    rawoutput('<form class="rz-account-form" action="prefs.php?op=changeemail" method="POST">' . resurrection_action_fields('prefs-email', resurrection_prefs_change_context())
        . '<p><label for="rz-prefs-newemail">New email address</label> <input type="email" name="newemail" id="rz-prefs-newemail" maxlength="128" autocomplete="email"' . ($on ? ' required' : '') . '></p>'
        . '<p><label for="rz-prefs-emailpassword">Current password</label> <input type="password" name="emailpassword" id="rz-prefs-emailpassword" autocomplete="current-password" required></p>'
        . '<p>' . $escape($help) . '</p>'
        . '<p><input type="submit" class="' . resurrection_button_class(false) . '" value="Change my address"></p></form></section>');
    addnav('', 'prefs.php?op=changeemail');
}

/** Preferences' line while account mail is off (section 6). */
const RESURRECTION_ACCOUNT_RECOVERY_OFF_PREFS = 'Password recovery by email is not available on this game. Use Petition for Help to ask an administrator to reset your password.';
