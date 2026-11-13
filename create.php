<?php
define('RESURRECTION_ENTRY', true);
// Keep the historical renderer and account/module boundaries.
define('ALLOW_ANONYMOUS', true);
require_once 'common.php';
require_once 'lib/accounts.php';
require_once 'lib/checkban.php';
require_once 'lib/web_security.php';
require_once 'lib/account_verify.php';
require_once 'lib/account_recovery.php';

use Resurrection\Http\Input;

/** The sign-up limit per requester was reached (Phase 6 task 13c, section 5): no account was made. */
final class ResurrectionSignupLimited extends RuntimeException {}

checkban();
page_header('Create A Character');
try {
    // 1.1.2's password recovery (op=forgot) is removed: an unknown op is refused (400). Resets stay an admin action in
    // the user editor. op=val, the old email validation link, still answers 410.
    $op = Input::choice($_GET, 'op', ['', 'create', 'val'], '');
    if ($op === 'val') {
        http_response_code(410);
        output('Account recovery and the historical email login-link mechanism are disabled until secure recovery is available.');
    } elseif (!getsetting('allowcreation', 1)) {
        output('Account creation is unavailable.');
    } else {
        // Phase 6 task 13c (S11 section 4.1, decision 4): while requirevalidemail is on (it reads as on only while account
        // mail is), the address is required and must pass the account mail address rules, the account is made pending,
        // and the verify mail goes out after the page.
        $verify = (int)getsetting('requirevalidemail', 0) === 1;
        if ($op === 'create') {
            resurrection_require_post();
            $name = Input::string($_POST, 'name');
            $email = Input::string($_POST, 'email');
            $password = Input::string($_POST, 'pass1');
            if ($password !== Input::string($_POST, 'pass2')) { throw new InvalidArgumentException('Passwords must match.'); }
            resurrection_validate_account($name, $email);
            if ((getsetting('requireemail', 0) || $verify) && $email === '') { throw new InvalidArgumentException('Email is required.'); }
            if ($verify && !resurrection_mail_address_valid($email)) { throw new InvalidArgumentException('Invalid email address.'); }
            if (soap($name) !== $name) { throw new InvalidArgumentException('Choose another character name.'); }
            if (getsetting('blockdupeemail', 0) && $email !== '') {
                $duplicate = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE emailaddress=?', true, [$email]);
                if (db_num_rows($duplicate)) { throw new InvalidArgumentException('Account details are unavailable.'); }
            }
            $sex = (int)Input::choice($_POST, 'sex', ['0', '1'], '0');
            $args = ['name' => $name, 'email' => $email, 'sex' => $sex];
            $checked = modulehook('check-create', $args);
            if ($checked['blockaccount'] ?? false) { throw new InvalidArgumentException('Account creation was declined.'); }
            if ($verify && resurrection_account_mail_limited('signup') !== null) {
                // Sign-ups that send mail, per requester (section 5): the account is not made.
                resurrection_account_mail_log('signup', 'limited');
                unset($password, $_POST['pass1'], $_POST['pass2']);
                throw new ResurrectionSignupLimited();
            }
            try { $id = resurrection_create_account($name, $password, $email, $sex, $verify); }
            catch (RuntimeException $error) { throw new InvalidArgumentException('Account details are unavailable.'); }
            unset($password, $_POST['pass1'], $_POST['pass2']);
            $args['acctid'] = $id;
            modulehook('process-create', $args);
            savesetting('newestplayer', $id);
            invalidatedatacache('newest');
            if ($verify) {
                resurrection_account_mail_log('signup', 'counted', $id);
                $days = (int)getsetting('expiretrashacct', 1);
                rawoutput('<section class="rz-account" aria-labelledby="rz-create-title"><h2 id="rz-create-title">Check your inbox</h2>');
                rawoutput('<p>Check your inbox for a link to confirm your address. You can sign in once it is confirmed.'
                    . ($days > 0 ? ' Unconfirmed accounts are removed after ' . $days . ($days === 1 ? ' day.' : ' days.') : '') . ' Check your spam folder too.</p></section>');
                resurrection_account_send_then(static function () use ($id): void { resurrection_account_verify_send($id); }, 0.0);
            } else {
                output('Your account was created. Return to the login page to sign in.');
            }
        } else {
            rawoutput('<form action="create.php?op=create" method="POST">' . resurrection_csrf_field());
            rawoutput('<table class="rz-stack"><tr><td><label for="create-name">Character name:</label></td><td><input name="name" id="create-name" maxlength="25" required></td></tr>');
            rawoutput('<tr><td><label for="create-pass1">Password (12 to 72 bytes):</label></td><td><input type="password" name="pass1" id="create-pass1" autocomplete="new-password" required></td></tr>');
            rawoutput('<tr><td><label for="create-pass2">Confirm password:</label></td><td><input type="password" name="pass2" id="create-pass2" autocomplete="new-password" required></td></tr>');
            rawoutput('<tr><td><label for="create-email">Email:</label></td><td><input type="email" name="email" id="create-email" maxlength="128"' . ($verify ? ' required' : '') . '></td></tr></table>');
            if ($verify) rawoutput('<p>A link to confirm your address is mailed to it. You can sign in once it is confirmed.</p>');
            rawoutput('<label><input type="radio" name="sex" value="1">Female</label> <label><input type="radio" name="sex" value="0" checked>Male</label>');
            modulehook('create-form');
            rawoutput('<input type="submit" class="' . resurrection_button_class(true) . '" value="Create your character"></form>');
        }
    }
} catch (ResurrectionSignupLimited $error) {
    http_response_code(429);
    output('Too many accounts were made from here. Try again later.');
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    output('Invalid account details. Check the name, email, and matching passwords.');
}
addnav('Login', 'index.php');
page_footer();
