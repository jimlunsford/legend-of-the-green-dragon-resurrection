<?php
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
            page_footer();
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
    $account = resurrection_authenticate($name, $password);
    unset($password, $_POST['password']);
    if (!$account) {
        // Fixed event vocabulary only, never a request/session serialization.
        db_query('INSERT INTO ' . db_prefix('faillog') . ' (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', true,
            [date('Y-m-d H:i:s'), 'invalid_credentials', $_SERVER['REMOTE_ADDR'] ?? '', 0, '']);
        $session['message'] = '`4Error, your login was incorrect`0';
        header('Location: index.php', true, 303);
        exit();
    }
    checkban($account['login']);
    checkban();
    $version = resurrection_begin_login((int)$account['acctid'], $account['password']);
    resurrection_rotate_session();
    $_SESSION['auth_version'] = $version;
    unset($account['password']);
    $session['user'] = $account;
    $baseaccount = $account;
    foreach (['prefs', 'dragonpoints'] as $field) {
        $value = unserialize($account[$field], ['allowed_classes' => false]);
        $session['user'][$field] = is_array($value) ? $value : [];
    }
    $session['bufflist'] = unserialize($account['bufflist'], ['allowed_classes' => false]);
    $session['allowednavs'] = unserialize($account['allowednavs'], ['allowed_classes' => false]);
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
