<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/Security/ScalarState.php';
require_once __DIR__ . '/src/Security/NavigationState.php';
require_once __DIR__ . '/src/Security/PreferenceState.php';
require_once __DIR__ . '/src/Game/BuffState.php';
require_once __DIR__ . '/src/Game/DragonPointState.php';
// Authentication boundary; game hooks and navigation remain procedural.
define('ALLOW_ANONYMOUS', true);
define('OVERRIDE_FORCED_NAV', true);
require_once 'common.php';
require_once 'lib/accounts.php';
require_once 'lib/checkban.php';
require_once 'lib/web_security.php';

use Resurrection\Http\Input;

try {
    $op = Input::choice($_GET, 'op', ['', 'logout'], '');
    if ($op === 'logout') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            page_header('Log out');
            rawoutput('<form action="login.php?op=logout" method="POST">' . resurrection_csrf_field() . '<input class="button" type="submit" value="Log out"></form>');
            // Not kept (Phase 6 task 9): after signing in again, badnav.php shows the page the player left, as 1.1.2
            // did (its logout had no page of its own), not this one.
            page_footer(false);
        }
        resurrection_require_post();
        if ($session['loggedin']) {
            modulehook('player-logout');
            db_query('UPDATE ' . db_prefix('accounts') . ' SET loggedin=0,authversion=authversion+1 WHERE acctid=?', true, [(int)$session['user']['acctid']]);
            invalidatedatacache('charlisthomepage');
            invalidatedatacache('list.php-warsonline');
        }
        resurrection_end_session();
        header('Location: index.php', true, 303);
        exit();
    }
    resurrection_require_post();
    $name = Input::string($_POST, 'name');
    $password = Input::string($_POST, 'password');
    // Sign-in throttling (Phase 6 task 18, F4; lib/signin_throttle.php): over any limit, one message, no password
    // checked, and a signin_throttled row of its own. 1.1.2 had no throttle: after 10 failures in a day from one
    // address on existing accounts it banned the address and mailed the admins.
    require_once 'lib/signin_throttle.php';
    $wait = resurrection_signin_throttled($name);
    if ($wait !== null) {
        unset($password, $_POST['password']);
        resurrection_signin_record(RESURRECTION_SIGNIN_THROTTLED, $name);
        $session['message'] = '`4' . resurrection_signin_throttle_message($wait) . '`0';
        $session['login_incorrect'] = true;
        header('Location: index.php', true, 303);
        exit();
    }
    $check = resurrection_sign_in_check($name, $password);
    unset($password, $_POST['password']);
    $account = $check['account'];
    if (!$account && $check['pending'] !== null) {
        // A sign-up that has not confirmed its address, with the right password, while account mail is on (Phase 6
        // task 13c, S11 section 4.1): "Confirm your email address first", with a way to send the link again behind a
        // 10-minute grant from this sign-in. A wrong password never gets here.
        require_once 'lib/account_verify.php';
        resurrection_account_resend_grant_store($check['pending']);
        header('Location: account.php?op=resend', true, 303);
        exit();
    }
    if (!$account) {
        // Fixed event vocabulary only, never a request/session serialization: the word, the requester key and the
        // sign-in name's key, the same whether or not an account has the name (Phase 6 task 18).
        resurrection_signin_record(RESURRECTION_SIGNIN_FAILED, $name);
        $session['message'] = '`4Error, your login was incorrect`0';
        // home.php links to password recovery beside this message while account mail is on (Phase 6 task 13b).
        $session['login_incorrect'] = true;
        header('Location: index.php', true, 303);
        exit();
    }
    checkban($account['login']);
    checkban();
    // Read the stored preference and navigation state before the login is recorded.
    try { $prefs = \Resurrection\Security\PreferenceState::read($account['prefs']); }
    catch (DomainException $error) { http_response_code(409); exit('Invalid stored preference state. You were not logged in.'); }
    try {
        $allowednavs = \Resurrection\Security\NavigationState::read($account['allowednavs']);
        \Resurrection\Security\NavigationState::restorePage($account['restorepage']);
    } catch (DomainException $error) { http_response_code(409); exit('Invalid stored navigation state. You were not logged in.'); }
    try { $bufflist = \Resurrection\Game\BuffState::read($account['bufflist'], $account); }
    catch (DomainException $error) { http_response_code(409); exit('Invalid stored buff state. You were not logged in.'); }
    try { $dragonpoints = \Resurrection\Game\DragonPointState::history($account['dragonpoints']); }
    catch (DomainException $error) { http_response_code(409); exit('Invalid stored Dragon Point state. You were not logged in.'); }
    $version = resurrection_begin_login((int)$account['acctid'], $account['password']);
    resurrection_rotate_session();
    // A new sign-in holds no earlier action token. The kept page's actions are issued afresh once, on its first
    // replay (badnav.php; Phase 6 task 9).
    unset($_SESSION['actions']);
    $_SESSION['kept_page_reissue'] = true;
    $_SESSION['auth_version'] = $version;
    unset($account['password']);
    $session['user'] = $account;
    $baseaccount = $account;
    $session['user']['prefs'] = $prefs;
    $session['user']['dragonpoints'] = $dragonpoints;
    $session['bufflist'] = $bufflist;
    $session['allowednavs'] = $allowednavs;
    modulehook('check-login');
    $session['loggedin'] = true;
    $session['user']['loggedin'] = true;
    $session['lasthit'] = time();
    $session['sentnotice'] = 0;
    $_SESSION['auth_privileges'] = (int)$account['superuser'];
    invalidatedatacache('charlisthomepage');
    invalidatedatacache('list.php-warsonline');
    modulehook('player-login');
    header('Location: village.php', true, 303);
    exit();
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    exit('Invalid account request.');
}
