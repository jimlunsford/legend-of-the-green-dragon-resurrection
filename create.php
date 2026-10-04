<?php
// Keep the historical renderer and account/module boundaries.
define('ALLOW_ANONYMOUS', true);
require_once 'common.php';
require_once 'lib/accounts.php';
require_once 'lib/checkban.php';
require_once 'lib/web_security.php';

use Resurrection\Http\Input;

checkban();
page_header('Create A Character');
try {
    $op = Input::choice($_GET, 'op', ['', 'create', 'forgot', 'val'], '');
    if ($op === 'forgot' || $op === 'val') {
        http_response_code(410);
        output('Account recovery and the historical email login-link mechanism are disabled until secure recovery is available.');
    } elseif (!getsetting('allowcreation', 1) || getsetting('requirevalidemail', 0)) {
        output('Account creation is unavailable.');
    } else {
        if ($op === 'create') {
            resurrection_require_post();
            $name = Input::string($_POST, 'name');
            $email = Input::string($_POST, 'email');
            $password = Input::string($_POST, 'pass1');
            if ($password !== Input::string($_POST, 'pass2')) { throw new InvalidArgumentException('Passwords must match.'); }
            resurrection_validate_account($name, $email);
            if (getsetting('requireemail', 0) && $email === '') { throw new InvalidArgumentException('Email is required.'); }
            if (soap($name) !== $name) { throw new InvalidArgumentException('Choose another character name.'); }
            if (getsetting('blockdupeemail', 0) && $email !== '') {
                $duplicate = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE emailaddress=?', true, [$email]);
                if (db_num_rows($duplicate)) { throw new InvalidArgumentException('Account details are unavailable.'); }
            }
            $sex = (int)Input::choice($_POST, 'sex', ['0', '1'], '0');
            $args = ['name' => $name, 'email' => $email, 'sex' => $sex];
            $checked = modulehook('check-create', $args);
            if ($checked['blockaccount'] ?? false) { throw new InvalidArgumentException('Account creation was declined.'); }
            try { $id = resurrection_create_account($name, $password, $email, $sex); }
            catch (RuntimeException $error) { throw new InvalidArgumentException('Account details are unavailable.'); }
            unset($password, $_POST['pass1'], $_POST['pass2']);
            $args['acctid'] = $id;
            modulehook('process-create', $args);
            savesetting('newestplayer', $id);
            invalidatedatacache('newest');
            output('Your account was created. Return to the login page to sign in.');
        } else {
            output('`&`c`bCreate a Character`b`c`0');
            rawoutput('<form action="create.php?op=create" method="POST">' . resurrection_csrf_field());
            rawoutput('<table><tr><td>Character name:</td><td><input name="name" maxlength="25" required></td></tr>');
            rawoutput('<tr><td>Password (12 to 72 bytes):</td><td><input type="password" name="pass1" autocomplete="new-password" required></td></tr>');
            rawoutput('<tr><td>Confirm password:</td><td><input type="password" name="pass2" autocomplete="new-password" required></td></tr>');
            rawoutput('<tr><td>Email:</td><td><input type="email" name="email" maxlength="128"></td></tr></table>');
            rawoutput('<label><input type="radio" name="sex" value="1">Female</label> <label><input type="radio" name="sex" value="0" checked>Male</label>');
            modulehook('create-form');
            rawoutput('<input type="submit" class="button" value="Create your character"></form>');
        }
    }
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    output('Invalid account details. Check the name, email, and matching passwords.');
}
addnav('Login', 'index.php');
page_footer();
