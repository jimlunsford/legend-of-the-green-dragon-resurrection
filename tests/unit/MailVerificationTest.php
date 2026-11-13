<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\SettingDescriptor;

/**
 * Email verification (design section 4 with decisions 4, 5 and 7, and the verify prompt; Phase 6 task 13c): the
 * pending sign-up and the sign-in check, confirming a verify link, any address change leaving the account unverified,
 * requirevalidemail read as on only while account mail is on, the verify mail's limits, notification mail only to
 * verified addresses while account mail is on (F3), and the verify prompt. Messages are sent by a child PHP whose mail
 * goes to a capture (tests/support/mail_capture.php). The HTTP side is tests/tooling/test_account_verification_http.py.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it EmailVerificationTest, which would run before the install). Every account it makes is removed again.
 */
final class MailVerificationTest extends TestCase
{
    use AccountMailFixture;

    private const string PASSWORD = 'Synthetic verification password';
    private const string LINK = '#https://game\.example\.org/account\.php\?op=open&t=([A-Za-z0-9_-]{43})#';

    /** @var list<int> */
    private array $made = [];
    /** @var array<string, string|null> */
    private array $savedExtra = [];

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
        require_once 'lib/accounts.php';
        require_once 'lib/web_security.php';
        require_once 'lib/account_verify.php';
        require_once 'lib/char_expiration.php';
        require_once 'src/Http/SettingDescriptor.php';
        require_once 'lib/configuration_descriptors.php';
        require_once 'lib/typed_editor.php';
        require_once 'lib/saveuser.php';
    }

    protected function tearDown(): void
    {
        if ($this->made !== []) {
            $ids = implode(',', $this->made);
            db_query("DELETE FROM mail WHERE msgto IN ($ids) OR msgfrom IN ($ids)");
            db_query("DELETE FROM accounts_output WHERE acctid IN ($ids)");
            db_query("DELETE FROM accounts WHERE acctid IN ($ids)");
            $this->made = [];
        }
        if ($this->savedExtra !== []) { self::settings($this->savedExtra); $this->savedExtra = []; }
        $this->restoreAccountMailFixture();
        parent::tearDown();
    }

    /** Keep these settings as they are now, and put them back in tearDown(). */
    private function keep(string ...$names): void
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $this->savedExtra)) continue;
            $rows = db_query('SELECT value FROM settings WHERE setting=?', true, [$name]);
            $this->savedExtra[$name] = $rows === [] ? null : (string)$rows[0]['value'];
        }
    }

    /** Account mail effectively on: an https serverurl, a real sender, a confirmed test mail for both, the switch. */
    private function on(): void
    {
        $this->connectAccountMail();
        self::ready();
        self::settings(['account_mail' => 1, 'account_mail_proof' => json_encode(['time' => '2026-11-12 10:00:00', 'acctid' => 1,
            'fp' => resurrection_account_mail_fingerprint('https://game.example.org/', 'game@example.org')])]);
        self::assertTrue(resurrection_account_mail_on());
    }

    private function off(): void
    {
        self::settings(['account_mail' => 0]);
        self::assertFalse(resurrection_account_mail_on());
    }

    /** A new account with PASSWORD and, unless told otherwise, an unverified address; removed in tearDown(). */
    private function account(string $login, array $fields = []): int
    {
        $address = strtolower($login) . '@example.org';
        $values = $fields + ['emailaddress' => $address, 'emailverified' => '', 'superuser' => 0, 'locked' => 0, 'emailvalidation' => '', 'prefs' => 'a:0:{}'];
        $values += ['login' => $login, 'name' => 'Farmboy ' . $login, 'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            'laston' => date('Y-m-d H:i:s'), 'regdate' => date('Y-m-d H:i:s'), 'restorepage' => 'village.php', 'badguy' => '', 'companions' => 'a:0:{}',
            'allowednavs' => 'a:0:{}', 'specialmisc' => '', 'bufflist' => 'a:0:{}', 'dragonpoints' => 'a:0:{}', 'donationconfig' => ''];
        db_query('INSERT INTO accounts (' . implode(',', array_map('db_identifier', array_keys($values))) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')',
            true, array_values($values));
        $id = (int)db_insert_id();
        $this->made[] = $id;
        return $id;
    }

    /** @return array<string, mixed> */
    private static function row(int $acctid): array
    {
        return db_query('SELECT emailaddress,emailverified,emailvalidation,authversion FROM accounts WHERE acctid=?', true, [$acctid])[0];
    }

    /** A child PHP's code in a new capture: [its output, the messages it sent]. */
    private function verify(string $code, int $exit = 0): array
    {
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = null;
        return $this->child('require_once "lib/account_verify.php"; ' . $code, [], $exit);
    }

    /**
     * A child PHP with the game loaded as cron.php loads it (common.php, through a configuration file for the test
     * database), running $code with lib/char_expiration.php, in a new capture: [its output, the messages it sent].
     * @return array{0: string, 1: list<string>}
     */
    private function game(string $code): array
    {
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = (string)tempnam(sys_get_temp_dir(), 'resurrection-mail-capture-');
        $config = (string)tempnam(sys_get_temp_dir(), 'resurrection-verification-dbconnect-');
        file_put_contents($config, "<?php\n\$DB_HOST = " . var_export((string)getenv('RESURRECTION_TEST_DB_HOST'), true) . ";\n\$DB_USER = " . var_export((string)getenv('RESURRECTION_TEST_DB_USER'), true)
            . ";\n\$DB_PASS = " . var_export((string)getenv('RESURRECTION_TEST_DB_PASSWORD'), true) . ";\n\$DB_NAME = 'resurrection_test';\n\$DB_PREFIX = '';\n\$DB_USEDATACACHE = 0;\n\$DB_DATACACHEPATH = '';\n");
        $transport = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(dirname(__DIR__) . '/support/mail_capture.php') . ' ' . escapeshellarg($this->capture) . ' 0';
        $boot = 'chdir(' . var_export(dirname(__DIR__, 2), true) . '); $_SERVER+=["SCRIPT_NAME"=>"cron.php","REQUEST_URI"=>"cron.php","SERVER_NAME"=>"localhost","SERVER_PORT"=>"80","REMOTE_ADDR"=>"","REQUEST_METHOD"=>""];'
            . ' define("ALLOW_ANONYMOUS", true); ob_start(); require "common.php"; require_once "lib/char_expiration.php"; while (ob_get_level()) ob_end_clean(); ';
        try {
            $process = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . $transport, '-r', $boot . $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
                ['LOGD_DBCONNECT' => $config] + getenv());
            $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
            $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $out . $err);
        } finally {
            unlink($config);
        }
        $GLOBALS['settings'] = null;
        return [trim($out), self::captured($this->capture)];
    }

    /** A verify link mailed to the account, opened as op=open opens it: the grant op=confirm holds. */
    private function verifyGrant(int $acctid): array
    {
        [$out, $messages] = $this->verify('echo resurrection_account_verify_send(' . $acctid . ');');
        self::assertSame('sent', $out);
        self::assertCount(1, $messages);
        self::assertMatchesRegularExpression(self::LINK, $messages[0]);
        preg_match(self::LINK, $messages[0], $link);
        $opened = resurrection_account_token_open($link[1], ['verify']);
        self::assertNotNull($opened);
        return ['purpose' => 'verify', 'tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash'], 'expires' => time() + 900];
    }

    public function testPendingSignupCannotSignInWhileAccountMailIsOn(): void
    {
        $this->on();
        $id = resurrection_create_account('VerifyPending', self::PASSWORD, 'verifypending@example.org', 0, true);
        $this->made[] = $id;
        self::assertSame(RESURRECTION_ACCOUNT_PENDING, self::row($id)['emailvalidation']);
        self::assertSame(['account' => false, 'pending' => $id], resurrection_sign_in_check('VerifyPending', self::PASSWORD));
        self::assertFalse(resurrection_authenticate('VerifyPending', self::PASSWORD));
        // A wrong password gets the usual refusal: the pending state shows only to someone who has the password.
        self::assertSame(['account' => false, 'pending' => null], resurrection_sign_in_check('VerifyPending', 'Not the synthetic password'));
        // Any other emailvalidation value is refused whatever the state, as before.
        db_query("UPDATE accounts SET emailvalidation='xother' WHERE acctid=?", true, [$id]);
        self::assertSame(['account' => false, 'pending' => null], resurrection_sign_in_check('VerifyPending', self::PASSWORD));
        $this->off();
        self::assertSame(['account' => false, 'pending' => null], resurrection_sign_in_check('VerifyPending', self::PASSWORD));
    }

    public function testPendingSignupCanSignInWhileAccountMailIsOff(): void
    {
        $this->on();
        $id = $this->account('VerifyPendingOff', ['emailvalidation' => RESURRECTION_ACCOUNT_PENDING]);
        $this->off();
        $check = resurrection_sign_in_check('VerifyPendingOff', self::PASSWORD);
        self::assertNull($check['pending']);
        self::assertIsArray($check['account']);
        self::assertSame($id, (int)$check['account']['acctid']);
        // Its address stays unverified, and the marker stays: turned on again, account mail holds it back again.
        self::assertSame('', self::row($id)['emailverified']);
        self::settings(['account_mail' => 1]);
        self::assertSame($id, resurrection_sign_in_check('VerifyPendingOff', self::PASSWORD)['pending']);
    }

    public function testConfirmSetsEmailverifiedAndClearsThePendingMarker(): void
    {
        $this->on();
        $id = $this->account('VerifyConfirm', ['emailvalidation' => RESURRECTION_ACCOUNT_PENDING]);
        $grant = $this->verifyGrant($id);
        self::assertSame(['acctid' => $id, 'login' => 'VerifyConfirm', 'address' => 'verifyconfirm@example.org'], resurrection_account_confirm_view($grant));
        $done = resurrection_account_confirm($grant);
        self::assertSame(['acctid' => $id, 'login' => 'VerifyConfirm', 'purpose' => 'verify', 'address' => 'verifyconfirm@example.org', 'old' => 'verifyconfirm@example.org',
            'oldverified' => false, 'pending' => true], $done);
        $row = self::row($id);
        self::assertSame(['verifyconfirm@example.org', ''], [$row['emailverified'], $row['emailvalidation']]);
        self::assertIsArray(resurrection_sign_in_check('VerifyConfirm', self::PASSWORD)['account']);
        // The token is used: a second confirmation, and the page, find it dead.
        self::assertNull(resurrection_account_confirm_view($grant));
        try { resurrection_account_confirm($grant); self::fail('A used verify link confirmed again.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        self::assertSame(1, (int)db_query("SELECT COUNT(*) AS n FROM gamelog WHERE category='account mail' AND message=?", true, ['Email address verified for account ' . $id])[0]['n']);
    }

    public function testAVerifyLinkDiesWithAChangedAddressAStaleAuthversionOrAccountMailOff(): void
    {
        $this->on();
        foreach (['address changed' => "UPDATE accounts SET emailaddress='elsewhere@example.org' WHERE acctid=?",
            'signed in since' => 'UPDATE accounts SET authversion=authversion+1 WHERE acctid=?',
            'account mail off' => null] as $label => $change) {
            $id = $this->account('VerifyDead' . count($this->made));
            $grant = $this->verifyGrant($id);
            if ($change === null) $this->off(); else db_query($change, true, [$id]);
            try { resurrection_account_confirm($grant); self::fail('Confirmed after: ' . $label); }
            catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage(), $label); }
            self::assertSame('', self::row($id)['emailverified'], $label);
            self::settings(['account_mail' => 1]);
        }
    }

    public function testAnyAddressChangeLeavesTheAccountUnverified(): void
    {
        $this->on();
        $id = $this->account('VerifyChanged', ['emailverified' => 'verifychanged@example.org']);
        self::assertTrue(resurrection_account_address_verified('verifychanged@example.org', self::row($id)['emailverified']));
        // A path that writes emailaddress alone (the user editor, a module) leaves the account unverified.
        db_query("UPDATE accounts SET emailaddress='verifychanged2@example.org' WHERE acctid=?", true, [$id]);
        $row = self::row($id);
        self::assertFalse(resurrection_account_address_verified($row['emailaddress'], $row['emailverified']));
        // The change in Preferences while account mail is off clears it and kills the account's live links.
        db_query("UPDATE accounts SET emailaddress='verifychanged@example.org', emailverified='verifychanged@example.org' WHERE acctid=?", true, [$id]);
        $token = resurrection_account_token_issue($id, 'verify', 'verifychanged@example.org');
        $this->off();
        self::assertSame('changed', resurrection_account_email_change_now($id, self::PASSWORD, 'verifychanged3@example.org'));
        self::assertSame(['verifychanged3@example.org', ''], [self::row($id)['emailaddress'], self::row($id)['emailverified']]);
        self::settings(['account_mail' => 1]);
        self::assertNull(resurrection_account_token_open($token, ['verify']));
        // saveuser() never carries an address change, nor emailverified or emailvalidation.
        self::assertSame(['emailaddress', 'emailverified', 'emailvalidation'], RESURRECTION_SAVEUSER_NEVER);
        $source = (string)file_get_contents('lib/saveuser.php');
        self::assertStringContainsString('if (in_array($key, RESURRECTION_SAVEUSER_NEVER, true)) { continue; }', $source);
        // The Dragon kill keeps the verified address (it is on the list it never resets).
        self::assertMatchesRegularExpression('/,"emailverified"=>1/', (string)file_get_contents('lib/dragon_outcomes.php'));
    }

    public function testRequireValidEmailReadsOnOnlyWhileAccountMailIsOn(): void
    {
        $this->on();
        $this->keep('requirevalidemail');
        self::settings(['requirevalidemail' => 1]);
        $layout = static fn (): array => SettingDescriptor::declare(resurrection_core_layout());
        $GLOBALS['session']['user']['superuser'] = 0;
        self::assertSame('1', (string)getsetting('requirevalidemail', 0));
        self::assertSame([], resurrection_locked_settings());
        self::assertArrayHasKey('requirevalidemail', $layout(), 'Game Settings lets it be saved while account mail is on.');
        self::assertSame(['requirevalidemail' => '0'], SettingDescriptor::patch($layout(), ['requirevalidemail' => '0']));
        // The switch off, a new serverurl, a new sender, no confirmed test mail: it reads as off, and it is locked again.
        $proof = resurrection_account_mail_setting('account_mail_proof');
        foreach (['switch off' => ['account_mail' => 0], 'serverurl changed' => ['serverurl' => 'https://other.example.org/'],
            'sender changed' => ['gameadminemail' => 'other@example.org'], 'no test mail' => ['account_mail_proof' => null]] as $label => $change) {
            self::settings($change);
            self::assertSame('0', getsetting('requirevalidemail', 1), $label);
            self::assertSame(['requirevalidemail' => '0'], resurrection_locked_settings(), $label);
            self::assertArrayNotHasKey('requirevalidemail', $layout(), $label);
            try { SettingDescriptor::patch($layout(), ['requirevalidemail' => '1']); self::fail('Saved while locked: ' . $label); }
            catch (\InvalidArgumentException) { self::addToAssertionCount(1); }
            self::settings(['account_mail' => 1, 'serverurl' => 'https://game.example.org/', 'gameadminemail' => 'game@example.org', 'account_mail_proof' => $proof]);
            self::assertSame('1', (string)getsetting('requirevalidemail', 0), $label . ', restored');
        }
        self::assertSame('Locked off. Turn on account mail first (Game Settings, Account mail): a sign-up can confirm its address only while the game can send mail. See "Mail" in the host guide (docs/HOST-GUIDE.md).',
            resurrection_editor_locked_reason('requirevalidemail'));
    }

    public function testVerifyMailLimitsAndReplacement(): void
    {
        $this->on();
        $id = $this->account('VerifyLimits');
        [$out, $first] = $this->verify('echo resurrection_account_verify_send(' . $id . ');');
        self::assertSame('sent', $out);
        [, $body] = self::parse($first[0]);
        self::assertStringContainsString('Someone asked to confirm this address for the account VerifyLimits at https://game.example.org/.', $body);
        self::assertStringContainsString('If you did not ask for this, ignore this mail.', $body);
        preg_match(self::LINK, $first[0], $old);
        [$out] = $this->verify('echo resurrection_account_verify_send(' . $id . ');');
        self::assertSame('sent', $out);
        self::assertNull(resurrection_account_token_open($old[1], ['verify']), 'A new verify link replaces the earlier one.');
        [$out] = $this->verify('echo resurrection_account_verify_send(' . $id . ');');
        self::assertSame('sent', $out);
        // 3 an hour per account (section 5): the fourth is held back and recorded, and nothing is sent.
        [$out, $messages] = $this->verify('echo resurrection_account_verify_send(' . $id . ');');
        self::assertSame(['limited', []], [$out, $messages]);
        self::assertSame(1, (int)db_query("SELECT COUNT(*) AS n FROM account_mail_log WHERE purpose='verify' AND result='limited' AND acctid=?", true, [$id])[0]['n']);
        // A verified account, an account without an address, account mail off: nothing is sent.
        $verified = $this->account('VerifyAlready', ['emailverified' => 'verifyalready@example.org']);
        $none = $this->account('VerifyNone', ['emailaddress' => '']);
        self::assertSame(['verified', []], $this->verify('echo resurrection_account_verify_send(' . $verified . ');'));
        self::assertSame(['no-address', []], $this->verify('echo resurrection_account_verify_send(' . $none . ');'));
        $this->off();
        self::assertSame(['off', []], $this->verify('echo resurrection_account_verify_send(' . $verified . ');'));
    }

    public function testNotificationMailGoesOnlyToVerifiedAddressesWhileAccountMailIsOn(): void
    {
        $this->on();
        $prefs = serialize(['emailonmail' => 1, 'systemmail' => 1]);
        $unverified = $this->account('NotifyUnverified', ['prefs' => $prefs]);
        $verified = $this->account('NotifyVerified', ['prefs' => $prefs, 'emailverified' => 'notifyverified@example.org']);
        $other = $this->account('NotifyOther', ['prefs' => $prefs, 'emailverified' => 'old@example.org']);
        $send = fn (int $to): array => $this->game('require_once "lib/systemmail.php"; var_export(systemmail(' . $to . ', "Hello", "A game mail", 0));');
        foreach ([$unverified, $other] as $to) {
            [$out, $messages] = $send($to);
            self::assertSame(['true', []], [$out, $messages], 'The game mail is delivered in the game, but no email goes to an unverified address.');
        }
        [$out, $messages] = $send($verified);
        self::assertSame('true', $out);
        self::assertCount(1, $messages);
        self::assertSame('notifyverified@example.org', self::parse($messages[0])[0]['To'] ?? null);
        // While account mail is off, as today: the unverified address gets its notification.
        $this->off();
        [$out, $messages] = $send($unverified);
        self::assertSame('true', $out);
        self::assertCount(1, $messages);
        self::assertSame('notifyunverified@example.org', self::parse($messages[0])[0]['To'] ?? null);
        // The expiration warning's selection: verified addresses only while account mail is on.
        $source = (string)file_get_contents('lib/char_expiration.php');
        self::assertStringContainsString("\$verified = resurrection_account_mail_on() ? \" AND emailverified<>'' AND LOWER(emailverified)=LOWER(emailaddress)\" : '';", $source);
    }

    public function testExpirationWarningSkipsUnverifiedAddressesWhileAccountMailIsOn(): void
    {
        $this->on();
        $this->keep('last_char_expire', 'expireoldacct', 'expirenewacct', 'expiretrashacct');
        $old = date('Y-m-d H:i:s', time() - 42 * 86400);
        $unverified = $this->account('WarnUnverified', ['laston' => $old, 'regdate' => $old, 'superuser' => 0]);
        $verified = $this->account('WarnVerified', ['laston' => $old, 'regdate' => $old, 'emailverified' => 'warnverified@example.org']);
        $others = db_query('SELECT acctid,superuser,sentnotice FROM accounts WHERE acctid NOT IN (?,?)', true, [$unverified, $verified]);
        try {
            db_query('UPDATE accounts SET superuser=superuser|? WHERE acctid NOT IN (?,?)', true, [NO_ACCOUNT_EXPIRATION, $unverified, $verified]);
            db_query("INSERT INTO settings (setting,value) VALUES ('last_char_expire','2000-01-01 00:00:00'),('expireoldacct','45'),('expirenewacct','0'),('expiretrashacct','0') ON DUPLICATE KEY UPDATE value=VALUES(value)");
            [$out, $messages] = $this->game('db_query("SELECT GET_LOCK(?,0)", true, [resurrection_maintenance_lock_name()]); resurrection_expire_characters(time()); echo "done";');
            self::assertSame('done', $out);
            self::assertCount(1, $messages);
            self::assertSame('warnverified@example.org', self::parse($messages[0])[0]['To'] ?? null);
            self::assertSame(['0', '1'], array_column(db_query('SELECT sentnotice FROM accounts WHERE acctid IN (?,?) ORDER BY acctid', true, [$unverified, $verified]), 'sentnotice'));
        } finally {
            foreach ($others as $row) db_query('UPDATE accounts SET superuser=? WHERE acctid=?', true, [(int)$row['superuser'], (int)$row['acctid']]);
            db_query("DELETE FROM gamelog WHERE category='char expiration' AND date>?", true, [date('Y-m-d H:i:s', time() - 600)]);
        }
    }

    public function testAPendingSignupIsRemovedByTheTrashRule(): void
    {
        $this->on();
        $two = date('Y-m-d H:i:s', time() - 2 * 86400);
        $pending = $this->account('TrashPending', ['emailvalidation' => RESURRECTION_ACCOUNT_PENDING, 'laston' => $two, 'regdate' => $two]);
        $played = $this->account('TrashPlayed', ['emailvalidation' => RESURRECTION_ACCOUNT_PENDING, 'regdate' => $two, 'laston' => date('Y-m-d H:i:s', time() - 86400)]);
        [$rules, $parameters] = resurrection_expire_rules(0, 0, 1, time());
        $selected = array_map('intval', array_column(db_query('SELECT acctid FROM accounts WHERE acctid IN (?,?) AND ' . $rules, true, [$pending, $played, ...$parameters]), 'acctid'));
        self::assertSame([$pending], $selected, 'A sign-up that never confirmed (and so never signed in) is removed by expiretrashacct; one that signed in while account mail was off is not.');
        [$status] = $this->game('[$status] = resurrection_expire_account(' . $pending . ', ' . var_export($rules, true) . ', ' . var_export($parameters, true) . '); echo $status;');
        self::assertSame('deleted', $status);
        self::assertSame([], db_query('SELECT acctid FROM accounts WHERE acctid=?', true, [$pending]));
    }

    public function testTheVerifyPromptShowsOnlyWhileAccountMailIsOnAndTheAddressIsUnverified(): void
    {
        $this->on();
        $_SESSION = [];
        $user = ['acctid' => 5, 'emailaddress' => 'prompt@example.org', 'emailverified' => '', 'prefs' => []];
        $nav = 'prefs.php?c=3-120000';
        $html = resurrection_account_verify_prompt($user, $nav);
        self::assertStringStartsWith('<div class="rz-verify-prompt" role="note"><p>Password recovery by email works only for a verified address, and yours is not verified. '
            . '<a href="prefs.php?c=3-120000">Verify it in Preferences</a>.</p><form action="prefs.php?op=dismissverify" method="POST"><input type="hidden" name="csrf_token" value="', $html);
        self::assertStringEndsWith('"><button class="button">Dismiss</button></form></div>', $html);
        self::assertDoesNotMatchRegularExpression('/<script|<style|\sstyle=|\son[a-z]+=/i', $html);
        $cases = [
            'no Preferences link on the page' => [$user, null],
            'no address' => [['emailaddress' => ''] + $user, $nav],
            'verified' => [['emailverified' => 'Prompt@Example.org'] + $user, $nav],
        ];
        foreach ($cases as $label => [$who, $link]) self::assertSame('', resurrection_account_verify_prompt($who, $link), $label);
        $this->off();
        self::assertSame('', resurrection_account_verify_prompt($user, $nav), 'Never while account mail is off.');
    }

    public function testDismissHidesThePromptForThatAddressOnly(): void
    {
        $this->on();
        $_SESSION = [];
        $user = ['acctid' => 5, 'emailaddress' => 'prompt@example.org', 'emailverified' => '', 'prefs' => [RESURRECTION_ACCOUNT_PROMPT_PREF => hash('sha256', 'prompt@example.org')]];
        self::assertSame('', resurrection_account_verify_prompt($user, 'prefs.php'));
        $user['emailaddress'] = 'Prompt@example.org ';
        self::assertSame('', resurrection_account_verify_prompt($user, 'prefs.php'), 'The same address, as the mail log keys it.');
        $user['emailaddress'] = 'new@example.org';
        self::assertNotSame('', resurrection_account_verify_prompt($user, 'prefs.php'), 'Shown again for a new address.');
        // The dismissal is stored in the account's preferences, which the preferences form can never write.
        self::assertSame('verifyprompt', RESURRECTION_ACCOUNT_PROMPT_PREF);
        self::assertStringContainsString('RESURRECTION_ACCOUNT_PROMPT_PREF=>1,', (string)file_get_contents('prefs.php'));
    }
}

