<?php
define('RESURRECTION_ENTRY', true);
/**
 * Account mail's page (Phase 6 tasks 13a, 13b and 13c, S11; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md,
 * sections 3 and 4, and its decisions). A link in an account mail opens it with a token:
 *
 * - op=open: the token (query string t) is checked (shape, hash, unused, unexpired, at most 5 openings, the account's
 *   authversion unchanged) and exchanged for a 15-minute grant in a new session id, then a 303 to the clean address
 *   (op=reset for a recovery link, op=confirm for the others), with no body. The token is read and removed from the
 *   request before the game starts, so no page, form, session, log, saved page, referer record or restore page ever
 *   holds it. A recovery, verify or change link opens only while account mail is on.
 * - op=confirm: the grant's page (the test mail, or, task 13c, a verify or change link: the account and the address),
 *   and its POST (CSRF), which acts on the token in one transaction (lib/account_verify.php for verify and change). A
 *   visitor signed in as another account is told to sign out first. After a change, a notice goes to the old verified
 *   address once the page is sent.
 * - op=resend (task 13c): the sign-in refusal of a sign-up that has not confirmed its address, behind the 10-minute
 *   grant a correct password made in login.php; its POST (CSRF) sends the verify link again, within the limits.
 * - op=forgot (task 13b): the request form while account mail is on, one line while it is off; its POST (CSRF) gets
 *   the same page whatever it matched, and the lookup, the token and the mail run after that page is complete
 *   (lib/account_recovery.php).
 * - op=reset (task 13b): the recovery grant's form, and its POST (CSRF), which sets the password in one transaction
 *   and uses up every recovery link of the account; the notice mail follows after the page. A visitor signed in as
 *   another account is told to sign out first, and nothing changes.
 *
 * Every other op, every refused token and every missing grant get one fixed page, status 400, whatever the reason.
 * Every response is sent with Referrer-Policy: no-referrer, Cache-Control: no-store and X-Robots-Tag: noindex, no page
 * is kept in accounts_output, and no footer shows the page-gen time or the time to the next new day, so that every
 * forgot-password response is the same page.
 */
// The token, taken out of the request before anything can record it.
$resurrection_account_token = $_GET['t'] ?? null;
unset($_GET['t'], $_REQUEST['t']);
// Any other copy of the request address a server passes on (REDIRECT_QUERY_STRING, UNENCODED_URL, argv, ...), for a
// value of a token's shape (43 base64url characters), which cannot match anything else by chance.
if (is_string($resurrection_account_token) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $resurrection_account_token) === 1) {
    array_walk_recursive($_SERVER, static function (mixed &$value) use ($resurrection_account_token): void {
        if (is_string($value) && str_contains($value, $resurrection_account_token)) $value = str_replace($resurrection_account_token, '', $value);
    });
}
$resurrection_account_op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
$resurrection_account_clean = in_array($resurrection_account_op, ['open', 'confirm', 'forgot', 'reset', 'resend'], true) ? 'op=' . $resurrection_account_op : '';
$_SERVER['REQUEST_URI'] = 'account.php' . ($resurrection_account_clean === '' ? '' : '?' . $resurrection_account_clean);
$_SERVER['QUERY_STRING'] = $resurrection_account_clean;
define('ALLOW_ANONYMOUS', true);
define('OVERRIDE_FORCED_NAV', true);
define('RESURRECTION_SECRET_URL', true);
define('RESURRECTION_NO_KEPT_PAGE', true);
define('RESURRECTION_QUIET_FOOTER', true);
require_once 'common.php';
require_once 'lib/web_security.php';
require_once 'lib/account_mail.php';
require_once 'lib/account_recovery.php';
require_once 'lib/account_verify.php';

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

/** The one page for a link that cannot be used, whatever the reason. */
function resurrection_account_expired(): never {
    global $session;
    http_response_code(400);
    page_header('Account');
    output('`#This link has expired or was already used. Ask for a new one.`0');
    resurrection_account_nav();
    page_footer();
    exit;
}

/** The navigation every account page ends with. */
function resurrection_account_nav(): void {
    global $session;
    addnav(!empty($session['loggedin']) ? 'Return to the game' : 'Login page', !empty($session['loggedin']) ? 'village.php' : 'index.php');
}

$op = in_array($resurrection_account_op, ['open', 'confirm', 'forgot', 'reset', 'resend'], true) ? $resurrection_account_op : '';
if ($op === 'forgot') {
    unset($resurrection_account_token);
    page_header('Forgot your password');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        // The same page for every request that passes CSRF, whatever it names and whatever happens to it (3.1).
        resurrection_require_post();
        $who = $_POST['who'] ?? null;
        unset($_POST['who'], $_REQUEST['who']);
        rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Check your mail</h2>');
        rawoutput('<p>' . htmlspecialchars(RESURRECTION_ACCOUNT_RECOVERY_ANSWER, ENT_QUOTES, 'UTF-8') . '</p></section>');
        resurrection_account_send_then(static function () use ($who): void { resurrection_account_recovery_request($who); }, RESURRECTION_ACCOUNT_RESPONSE_FLOOR);
    } elseif (!resurrection_account_mail_on()) {
        rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Forgot your password?</h2>');
        rawoutput('<p>' . htmlspecialchars(RESURRECTION_ACCOUNT_RECOVERY_OFF, ENT_QUOTES, 'UTF-8') . '</p></section>');
    } else {
        rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Forgot your password?</h2>');
        rawoutput('<p>Type your login, or the email address of your account. If the account has an address verified here, a link to choose a new password is mailed to that address.</p>');
        rawoutput('<form class="rz-account-form" action="account.php?op=forgot" method="POST">' . resurrection_csrf_field()
            . '<p><label for="rz-forgot-who">Login or email address</label> <input type="text" name="who" id="rz-forgot-who" maxlength="128" autocomplete="username" required></p>'
            . '<p><input type="submit" class="' . resurrection_button_class(true) . '" value="Send me a link"></p></form>');
        rawoutput('<p>No address, or no access to it? Use Petition for Help to ask an administrator to reset your password.</p></section>');
        addnav('', 'account.php?op=forgot');
    }
    resurrection_account_nav();
    page_footer();
}
if ($op === 'open') {
    $token = $resurrection_account_token;
    unset($resurrection_account_token);
    // Every opening is counted for the requester before the token is looked at; one held back by the limit is
    // recorded as such and not counted again. A link is opened with GET only.
    if (resurrection_account_mail_limited('open') !== null) {
        resurrection_account_mail_log('open', 'limited');
        unset($token);
        resurrection_account_expired();
    }
    resurrection_account_mail_log('open', 'counted');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        unset($token);
        resurrection_account_expired();
    }
    // A recovery, verify or change link opens only while account mail is on (section 6: links mailed earlier get the
    // expired page).
    $opened = resurrection_account_token_open($token, resurrection_account_mail_on() ? ['mailtest', 'recover', 'verify', 'email'] : ['mailtest']);
    unset($token);
    if ($opened === null) resurrection_account_expired();
    resurrection_account_grant_store($opened);
    header('Location: account.php?op=' . ($opened['purpose'] === 'recover' ? 'reset' : 'confirm'), true, 303);
    exit;
}
unset($resurrection_account_token);
if ($op === 'reset') {
    $grant = resurrection_account_grant('recover');
    if ($grant === null || !resurrection_account_mail_on()) { resurrection_account_grant_clear(); resurrection_account_expired(); }
    // The grant's token must still be live: unused, unexpired, and its account's authversion still the token's.
    $rows = db_query('SELECT a.acctid,a.login FROM ' . db_prefix('account_tokens') . ' t JOIN ' . db_prefix('accounts')
        . " a ON a.acctid=t.acctid AND a.authversion=t.authversion WHERE t.tokenid=? AND t.tokenhash=? AND t.purpose='recover' AND t.used IS NULL AND t.expires>?", true,
        [$grant['tokenid'], $grant['hash'], date('Y-m-d H:i:s')]);
    if (count($rows) !== 1) { resurrection_account_grant_clear(); resurrection_account_expired(); }
    // Someone signed in as another account (3.3, point 7): nothing happens until they sign out.
    if (!empty($session['loggedin']) && (int)($session['user']['acctid'] ?? 0) !== (int)$rows[0]['acctid']) {
        page_header('Sign out first');
        rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Sign out first</h2>');
        rawoutput('<p>This link is for another account than the one you are signed in as. Sign out, then open the link from the mail again. Nothing has changed.</p></section>');
        resurrection_account_nav();
        page_footer();
    }
    $problem = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        resurrection_require_post();
        $pass1 = is_string($_POST['pass1'] ?? null) ? $_POST['pass1'] : '';
        $pass2 = is_string($_POST['pass2'] ?? null) ? $_POST['pass2'] : '';
        unset($_POST['pass1'], $_POST['pass2'], $_REQUEST['pass1'], $_REQUEST['pass2']);
        try { $reset = resurrection_account_recovery_reset($grant, $pass1, $pass2); }
        catch (InvalidArgumentException $error) { $reset = null; $problem = $error->getMessage() === 'The two passwords are not the same.' ? 'The two passwords are not the same.' : 'A password has 12 to 72 bytes.'; }
        catch (DomainException $error) { unset($pass1, $pass2); resurrection_account_grant_clear(); resurrection_account_expired(); }
        unset($pass1, $pass2);
        if ($reset !== null) {
            resurrection_account_grant_clear();
            resurrection_rotate_session();
            page_header('Password changed');
            rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Password changed</h2>');
            rawoutput('<p>Your password was changed. Sign in with it now.</p>');
            rawoutput('<p>Every other session of this account has ended, and a notice is on its way to the account\'s address.</p></section>');
            resurrection_account_send_then(static function () use ($reset): void { resurrection_account_recovery_notices($reset); }, 0.0);
            addnav('Login page', 'index.php');
            page_footer();
        }
    }
    page_header('Choose a new password');
    rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Choose a new password</h2>');
    if ($problem !== '') rawoutput('<p class="rz-setting-refused" role="alert">' . htmlspecialchars($problem, ENT_QUOTES, 'UTF-8') . '</p>');
    rawoutput('<p>Choose a new password for the account <strong>' . htmlspecialchars((string)$rows[0]['login'], ENT_QUOTES, 'UTF-8') . '</strong>. It needs 12 to 72 bytes. Every session of the account ends when you save it, and you sign in with the new password.</p>');
    rawoutput('<form class="rz-account-form" action="account.php?op=reset" method="POST">' . resurrection_csrf_field()
        . '<p><label for="rz-reset-pass1">New password (12 to 72 bytes)</label> <input type="password" name="pass1" id="rz-reset-pass1" autocomplete="new-password" required></p>'
        . '<p><label for="rz-reset-pass2">The same password again</label> <input type="password" name="pass2" id="rz-reset-pass2" autocomplete="new-password" required></p>'
        . '<p><input type="submit" class="' . resurrection_button_class(true) . '" value="Save the new password"></p></form></section>');
    addnav('', 'account.php?op=reset');
    resurrection_account_nav();
    page_footer();
}
if ($op === 'resend') {
    // The sign-in refusal for a sign-up that has not confirmed its address (task 13c, 4.1): only with the 10-minute
    // grant a correct password made, while account mail is on and the account is still pending.
    $acctid = resurrection_account_resend_grant();
    $rows = $acctid === null ? [] : db_query('SELECT acctid,login,emailvalidation FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$acctid]);
    if ($rows === [] || (string)$rows[0]['emailvalidation'] !== RESURRECTION_ACCOUNT_PENDING || !resurrection_account_mail_on()) {
        unset($_SESSION['account_resend']);
        resurrection_account_expired();
    }
    $said = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        resurrection_require_post();
        $said = match (resurrection_account_verify_send((int)$rows[0]['acctid'])) {
            'sent' => 'A new link is on its way to the address you signed up with. It works for 24 hours. Check your spam folder too.',
            'limited' => 'Too many links were sent for this account. Try again later.',
            default => 'The mail could not be sent. Try again later, or use Petition for Help.',
        };
    }
    page_header('Confirm your email address first');
    rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Confirm your email address first</h2>');
    if ($said !== '') rawoutput('<p class="rz-setting-refused" role="status">' . htmlspecialchars($said, ENT_QUOTES, 'UTF-8') . '</p>');
    rawoutput('<p>The account <strong>' . htmlspecialchars((string)$rows[0]['login'], ENT_QUOTES, 'UTF-8') . '</strong> can sign in once its email address is confirmed. Open the link in the mail sent when the account was made, and press Confirm on the page it shows.</p>');
    rawoutput('<form class="rz-account-form" action="account.php?op=resend" method="POST">' . resurrection_csrf_field()
        . '<p><input type="submit" class="' . resurrection_button_class(true) . '" value="Send the link again"></p></form></section>');
    addnav('', 'account.php?op=resend');
    resurrection_account_nav();
    page_footer();
}
if ($op !== 'confirm') resurrection_account_expired();

// A verify or change link (task 13c, 4.1 and 4.2): its page names the account and the address, and its POST confirms.
foreach (['verify', 'email'] as $purpose) {
    $grant = resurrection_account_grant($purpose);
    if ($grant === null) continue;
    $view = resurrection_account_mail_on() ? resurrection_account_confirm_view($grant) : null;
    if ($view === null) { resurrection_account_grant_clear(); resurrection_account_expired(); }
    if (!empty($session['loggedin']) && (int)($session['user']['acctid'] ?? 0) !== $view['acctid']) {
        page_header('Sign out first');
        rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Sign out first</h2>');
        rawoutput('<p>This link is for another account than the one you are signed in as. Sign out, then open the link from the mail again. Nothing has changed.</p></section>');
        resurrection_account_nav();
        page_footer();
    }
    $login = htmlspecialchars($view['login'], ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($view['address'], ENT_QUOTES, 'UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        resurrection_require_post();
        try { $done = resurrection_account_confirm($grant); }
        catch (DomainException $error) { resurrection_account_grant_clear(); resurrection_account_expired(); }
        resurrection_account_grant_clear();
        resurrection_rotate_session();
        if ($purpose === 'verify') {
            page_header('Address confirmed');
            rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Address confirmed</h2>');
            rawoutput('<p>The address <strong>' . $address . '</strong> is now verified for the account <strong>' . $login . '</strong>. Password recovery by email works for it.</p>');
            if ($done['pending']) rawoutput('<p>The account can sign in now.</p>');
            rawoutput('</section>');
        } else {
            page_header('Address changed');
            rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Address changed</h2>');
            rawoutput('<p>The email address of the account <strong>' . $login . '</strong> is now <strong>' . $address . '</strong>, and it is verified.</p>');
            if ($done['oldverified']) rawoutput('<p>A notice is on its way to the old address.</p>');
            rawoutput('</section>');
            if ($done['oldverified']) {
                resurrection_account_send_then(static function () use ($done): void {
                    resurrection_account_email_notice($done['acctid'], $done['login'], $done['old'], true);
                }, 0.0);
            }
        }
        resurrection_account_nav();
        page_footer();
    }
    page_header('Confirm your email address');
    rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Confirm your email address</h2>');
    rawoutput($purpose === 'verify'
        ? '<p>Confirm <strong>' . $address . '</strong> as the email address of the account <strong>' . $login . '</strong>. Nothing changes until you press Confirm.</p>'
        : '<p>Make <strong>' . $address . '</strong> the email address of the account <strong>' . $login . '</strong>. The address changes only when you press Confirm.</p>');
    rawoutput('<form class="rz-account-form" action="account.php?op=confirm" method="POST">' . resurrection_csrf_field() . '<input type="submit" class="' . resurrection_button_class(true) . '" value="Confirm"></form></section>');
    addnav('', 'account.php?op=confirm');
    resurrection_account_nav();
    page_footer();
}

$grant = resurrection_account_grant('mailtest');
if ($grant === null) resurrection_account_expired();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    resurrection_require_post();
    try { $login = resurrection_account_mail_test_confirm($grant); }
    catch (DomainException $error) { resurrection_account_grant_clear(); resurrection_account_expired(); }
    resurrection_account_grant_clear();
    resurrection_rotate_session();
    page_header('Test mail confirmed');
    rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Test mail confirmed</h2>');
    rawoutput('<p>The test mail reached the inbox of the account <strong>' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '</strong>, and its link reached this game. That account\'s email address is now verified.</p>');
    rawoutput('<p>An administrator can now turn on account mail in Game Settings, under Account mail.</p></section>');
} else {
    $rows = db_query('SELECT a.login FROM ' . db_prefix('account_tokens') . ' t JOIN ' . db_prefix('accounts') . ' a ON a.acctid=t.acctid WHERE t.tokenid=?', true, [$grant['tokenid']]);
    page_header('Confirm the test mail');
    rawoutput('<section class="rz-account" aria-labelledby="rz-account-title"><h2 id="rz-account-title">Confirm the test mail</h2>');
    rawoutput('<p>This link came in the test mail sent from Game Settings to the address of the account <strong>' . htmlspecialchars((string)($rows[0]['login'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>.</p>');
    rawoutput('<p>Confirming it proves that mail from the game arrives and that its links reach the game, and verifies that account\'s email address. Nothing changes until you press Confirm.</p>');
    rawoutput('<form action="account.php?op=confirm" method="POST">' . resurrection_csrf_field() . '<input type="submit" class="' . resurrection_button_class(true) . '" value="Confirm"></form></section>');
}
addnav(!empty($session['loggedin']) ? 'Return to the game' : 'Login page', !empty($session['loggedin']) ? 'village.php' : 'index.php');
page_footer();
