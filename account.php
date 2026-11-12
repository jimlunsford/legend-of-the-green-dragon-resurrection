<?php
define('RESURRECTION_ENTRY', true);
/**
 * Account mail's page (Phase 6 task 13a, S11 part 1; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, section 3.3,
 * and its decisions). A link in an account mail opens it with a token:
 *
 * - op=open: the token (query string t) is checked (shape, hash, unused, unexpired, at most 5 openings, the account's
 *   authversion unchanged) and exchanged for a 15-minute grant in a new session id, then a 303 to the clean address
 *   op=confirm, with no body. The token is read and removed from the request before the game starts, so no page, form,
 *   session, log, saved page, referer record or restore page ever holds it.
 * - op=confirm: the grant's page, and its POST (CSRF), which acts on the token in one transaction and uses it up.
 *
 * In this task the one purpose is the test mail from Game Settings (mailtest). Every other op, every refused token and
 * every missing grant get one fixed page, status 400, whatever the reason. Every response is sent with
 * Referrer-Policy: no-referrer, Cache-Control: no-store and X-Robots-Tag: noindex, and no page is kept in
 * accounts_output.
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
$_SERVER['REQUEST_URI'] = 'account.php' . ($resurrection_account_op === 'open' ? '?op=open' : ($resurrection_account_op === 'confirm' ? '?op=confirm' : ''));
$_SERVER['QUERY_STRING'] = $resurrection_account_op === 'open' ? 'op=open' : ($resurrection_account_op === 'confirm' ? 'op=confirm' : '');
define('ALLOW_ANONYMOUS', true);
define('OVERRIDE_FORCED_NAV', true);
define('RESURRECTION_SECRET_URL', true);
define('RESURRECTION_NO_KEPT_PAGE', true);
require_once 'common.php';
require_once 'lib/web_security.php';
require_once 'lib/account_mail.php';

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

/** The one page for a link that cannot be used, whatever the reason. */
function resurrection_account_expired(): never {
    global $session;
    http_response_code(400);
    page_header('Account');
    output('`#This link has expired or was already used. Ask for a new one.`0');
    addnav(!empty($session['loggedin']) ? 'Return to the game' : 'Login page', !empty($session['loggedin']) ? 'village.php' : 'index.php');
    page_footer();
    exit;
}

$op = in_array($resurrection_account_op, ['open', 'confirm'], true) ? $resurrection_account_op : '';
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
    $opened = resurrection_account_token_open($token, ['mailtest']);
    unset($token);
    if ($opened === null) resurrection_account_expired();
    resurrection_account_grant_store($opened);
    header('Location: account.php?op=confirm', true, 303);
    exit;
}
unset($resurrection_account_token);
if ($op !== 'confirm') resurrection_account_expired();

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
