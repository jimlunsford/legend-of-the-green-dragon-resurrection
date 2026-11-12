<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Password recovery by mail (design sections 3.1 to 3.5 and 5, decisions 2 and 3; Phase 6 task 13b): who can receive a
 * recovery mail, one mail for every account on an address, the limits, the reset in one transaction, the notices, and
 * the page sent before the work. Messages are sent by a child PHP whose mail goes to a capture
 * (tests/support/mail_capture.php). The HTTP side, where every request gets the same page, is
 * tests/tooling/test_account_recovery_http.py.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it AccountRecoveryTest, which would run before the install). Every account it makes is removed again.
 */
final class MailRecoveryTest extends TestCase
{
    use AccountMailFixture;

    private const string PASSWORD = 'Synthetic recovery password';
    private const string LINK = '#https://game\.example\.org/account\.php\?op=open&t=([A-Za-z0-9_-]{43})#';

    /** @var list<int> */
    private array $made = [];
    private ?string $savedStaff = null;

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
        require_once 'lib/account_recovery.php';
        require_once 'lib/accounts.php';
    }

    protected function tearDown(): void
    {
        if ($this->made !== []) {
            $ids = implode(',', $this->made);
            db_query("DELETE FROM mail WHERE msgto IN ($ids) OR msgfrom IN ($ids) OR subject='Staff password reset by email'");
            db_query("DELETE FROM accounts_output WHERE acctid IN ($ids)");
            db_query("DELETE FROM accounts WHERE acctid IN ($ids)");
            db_query("DELETE FROM bans WHERE banner='MailRecoveryTest'");
            $this->made = [];
        }
        if ($this->savedStaff !== null) { self::settings(['account_mail_staff' => $this->savedStaff]); $this->savedStaff = null; }
        db_query('UPDATE accounts SET loggedin=0 WHERE acctid=1');
        $this->restoreAccountMailFixture();
        parent::tearDown();
    }

    /** Account mail effectively on: an https serverurl, a real sender, a confirmed test mail for both, the switch. */
    private function on(): void
    {
        $this->connectAccountMail();
        $this->savedStaff = (string)(db_query("SELECT value FROM settings WHERE setting='account_mail_staff'")[0]['value'] ?? '1');
        self::ready();
        self::settings(['account_mail' => 1, 'account_mail_staff' => 1, 'account_mail_proof' => json_encode(['time' => '2026-11-12 10:00:00', 'acctid' => 1,
            'fp' => resurrection_account_mail_fingerprint('https://game.example.org/', 'game@example.org')])]);
        self::assertTrue(resurrection_account_mail_on());
    }

    /** A new account with a verified address unless told otherwise; removed in tearDown(). */
    private function account(string $login, array $fields = []): int
    {
        $address = strtolower($login) . '@example.org';
        $values = $fields + ['emailaddress' => $address, 'emailverified' => $address, 'superuser' => 0, 'locked' => 0, 'emailvalidation' => '',
            'lastip' => '192.0.2.10', 'uniqueid' => md5($login), 'banoverride' => 0];
        $values += ['login' => $login, 'name' => 'Farmboy ' . $login, 'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            'laston' => date('Y-m-d H:i:s'), 'regdate' => date('Y-m-d H:i:s'), 'restorepage' => 'village.php', 'badguy' => '', 'companions' => 'a:0:{}',
            'allowednavs' => 'a:0:{}', 'specialmisc' => '', 'bufflist' => 'a:0:{}', 'dragonpoints' => 'a:0:{}', 'prefs' => 'a:0:{}', 'donationconfig' => ''];
        db_query('INSERT INTO accounts (' . implode(',', array_map('db_identifier', array_keys($values))) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')',
            true, array_values($values));
        $id = (int)db_insert_id();
        $this->made[] = $id;
        return $id;
    }

    /** Start a new mail capture: the messages a child sends from now on. */
    private function fresh(): void
    {
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = null;
    }

    /** One forgot-password request's work in a child PHP, in a new capture: [its outcome, the messages it sent]. */
    private function request(string $who, int $exit = 0): array
    {
        $this->fresh();
        return $this->child('require_once "lib/account_recovery.php"; echo resurrection_account_recovery_request(' . var_export($who, true) . ');', [], $exit);
    }

    /** @return list<array<string, mixed>> */
    private static function tokens(int $acctid): array
    {
        return db_query("SELECT tokenid,tokenhash,address,authversion,opened,used FROM account_tokens WHERE acctid=? AND purpose='recover' ORDER BY tokenid", true, [$acctid]);
    }

    /** A recovery token for an account, opened as op=open opens it; returns [the token, the grant op=reset holds]. */
    private static function opened(int $acctid, string $address): array
    {
        $token = resurrection_account_token_issue($acctid, 'recover', $address);
        $opened = resurrection_account_token_open($token, ['recover']);
        self::assertNotNull($opened);
        return [$token, ['purpose' => 'recover', 'tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash'], 'expires' => time() + 900]];
    }

    public function testEligibilityMatrix(): void
    {
        $this->on();
        $ok = $this->account('RecoverOk');
        $cases = [
            'no address' => $this->account('RecoverNoAddress', ['emailaddress' => '', 'emailverified' => '']),
            'unverified' => $this->account('RecoverUnverified', ['emailverified' => '']),
            'verified for another address' => $this->account('RecoverOther', ['emailverified' => 'old@example.org']),
            'pending sign-up' => $this->account('RecoverPending', ['emailvalidation' => str_repeat('a', 32)]),
            'locked' => $this->account('RecoverLocked', ['locked' => 1]),
            'banned by IP' => $this->account('RecoverBannedIp', ['lastip' => '198.51.100.7']),
            'banned by ID' => $this->account('RecoverBannedId', ['uniqueid' => 'feedfacefeedfacefeedfacefeedface']),
            'invalid address' => $this->account('RecoverInvalid', ['emailaddress' => 'not an address', 'emailverified' => 'not an address']),
        ];
        db_query("INSERT INTO bans (ipfilter,uniqueid,banexpire,banreason,banner) VALUES ('198.51.100.','',NULL,'test','MailRecoveryTest'),('','feedfacefeedfacefeedfacefeedface',?,'test','MailRecoveryTest')",
            true, [date('Y-m-d', time() + 86400)]);
        // An expired ban and banoverride do not stop recovery; a staff account is never banned, as in checkban().
        $expired = $this->account('RecoverOldBan', ['lastip' => '203.0.113.50']);
        $override = $this->account('RecoverOverride', ['lastip' => '198.51.100.8', 'banoverride' => 1]);
        db_query("INSERT INTO bans (ipfilter,uniqueid,banexpire,banreason,banner) VALUES ('203.0.113.50','','2000-01-01','test','MailRecoveryTest')");
        $staff = $this->account('RecoverStaff', ['superuser' => SU_EDIT_USERS, 'lastip' => '198.51.100.9']);
        $grottoless = $this->account('RecoverDebugOnly', ['superuser' => SU_DEBUG_OUTPUT]);
        foreach ($cases as $why => $acctid) {
            $login = (string)db_query('SELECT login FROM accounts WHERE acctid=?', true, [$acctid])[0]['login'];
            self::assertSame(['none', []], $this->request($login), $why);
            self::assertSame([], self::tokens($acctid), $why);
        }
        foreach (['RecoverOk' => $ok, 'RecoverOldBan' => $expired, 'RecoverOverride' => $override, 'RecoverStaff' => $staff, 'RecoverDebugOnly' => $grottoless] as $login => $acctid) {
            // (Each from a requester with no requests yet: the requester limit is 10 an hour.)
            db_query("DELETE FROM account_mail_log WHERE purpose='recover-request'");
            [$out, $messages] = $this->request($login);
            self::assertSame('sent', $out, $login);
            self::assertCount(1, $messages, $login);
            self::assertCount(1, self::tokens($acctid), $login);
            [$headers, $body] = self::parse($messages[0]);
            self::assertSame([strtolower($login) . '@example.org', 'Choose a new password'], [$headers['To'], $headers['Subject']]);
            self::assertSame(1, preg_match_all(self::LINK, $body, $links));
            self::assertSame(resurrection_account_token_hash($links[1][0]), self::tokens($acctid)[0]['tokenhash']);
            self::assertStringContainsString($login . ":\n" . $links[0][0], $body);
        }
        // Staff excluded (decision 3): a staff account gets nothing; an account whose only flags give no Grotto is not staff.
        db_query('DELETE FROM account_tokens');
        db_query('DELETE FROM account_mail_log');
        self::settings(['account_mail_staff' => 0]);
        self::assertSame(['none', []], $this->request('RecoverStaff'));
        self::assertSame([], self::tokens($staff));
        self::assertSame('sent', $this->request('RecoverDebugOnly')[0]);
        // A login matches exactly; nothing is found by part of one, or by an address the account has not verified.
        self::assertSame(['none', []], $this->request('RecoverO'));
        self::assertSame(['none', []], $this->request('recoverunverified@example.org'));
        // Account mail off (or not in effect): nothing at all.
        db_query('DELETE FROM account_tokens');
        self::settings(['account_mail' => 0]);
        self::assertSame(['off', []], $this->request('RecoverOk'));
        self::settings(['account_mail' => 1, 'serverurl' => 'http://game.example.org/']);
        self::assertSame(['off', []], $this->request('RecoverOk'));
        self::assertSame([], self::tokens($ok));
        // Every request was counted for the requester, matched or not; a request found nothing was recorded otherwise.
        self::assertSame(['counted'], array_values(array_unique(array_column(db_query("SELECT result FROM account_mail_log WHERE purpose='recover-request'"), 'result'))));
        self::assertCount(6, db_query("SELECT eventid FROM account_mail_log WHERE purpose='recover-request'"));
    }

    public function testSeveralAccountsOnOneAddressGetOneMail(): void
    {
        $this->on();
        $ids = [];
        foreach (['ShareOne', 'ShareTwo', 'ShareThree', 'ShareFour', 'ShareFive', 'ShareSix'] as $i => $login) {
            $ids[$login] = $this->account($login, ['emailaddress' => $i === 1 ? 'Shared@Example.org' : 'shared@example.org', 'emailverified' => $i === 1 ? 'shared@example.org' : 'Shared@Example.org']);
        }
        $locked = $this->account('ShareLocked', ['emailaddress' => 'shared@example.org', 'emailverified' => 'shared@example.org', 'locked' => 1]);
        $unverified = $this->account('ShareUnverified', ['emailaddress' => 'shared@example.org', 'emailverified' => '']);
        [$out, $messages] = $this->request('SHARED@example.ORG');
        self::assertSame('sent', $out);
        self::assertCount(1, $messages, 'one mail to the address, whatever the number of accounts');
        [$headers, $body] = self::parse($messages[0]);
        self::assertSame('shared@example.org', $headers['To']);
        // At most 5 accounts, the oldest first: the sixth, the locked and the unverified are not in it.
        self::assertSame(5, preg_match_all('#^(Share[A-Za-z]+):\n(https://\S+)$#m', $body, $entries));
        self::assertSame(['ShareOne', 'ShareTwo', 'ShareThree', 'ShareFour', 'ShareFive'], $entries[1]);
        self::assertCount(5, array_unique($entries[2]));
        foreach ($entries[1] as $i => $login) {
            self::assertMatchesRegularExpression(self::LINK, $entries[2][$i]);
            preg_match(self::LINK, $entries[2][$i], $link);
            self::assertSame(resurrection_account_token_hash($link[1]), self::tokens($ids[$login])[0]['tokenhash'], $login);
        }
        foreach ([$ids['ShareSix'], $locked, $unverified] as $acctid) self::assertSame([], self::tokens($acctid));
        // One log row per account served (each account's limits count it); the address counted once.
        $rows = db_query("SELECT acctid,addresskey,result FROM account_mail_log WHERE purpose='recover' ORDER BY eventid");
        self::assertSame(array_map('strval', array_slice(array_values($ids), 0, 5)), array_map('strval', array_column($rows, 'acctid')));
        self::assertSame([resurrection_account_mail_address_key('shared@example.org'), '', '', '', ''], array_column($rows, 'addresskey'));
        self::assertNull(resurrection_account_mail_limited('recover', $ids['ShareOne'], 'shared@example.org'));
        // The 5-minute rule: a second request makes no new token and sends nothing; the earlier mail stands.
        self::assertSame(['none', []], $this->request('shared@example.org'));
        self::assertCount(5, db_query("SELECT tokenid FROM account_tokens WHERE used IS NULL"));
        self::assertSame(['skipped', 'skipped', 'skipped', 'skipped', 'skipped'], array_column(db_query("SELECT result FROM account_mail_log WHERE purpose='recover' AND result='skipped'"), 'result'));
        // After it, a new request replaces the live link: at most one recovery link per account works.
        db_query("UPDATE account_tokens SET created=created - INTERVAL 301 SECOND WHERE acctid=?", true, [$ids['ShareOne']]);
        $first = self::tokens($ids['ShareOne']);
        self::assertSame('sent', $this->request('ShareOne')[0]);
        $after = self::tokens($ids['ShareOne']);
        self::assertCount(2, $after);
        self::assertNotNull($after[0]['used']);
        self::assertNull($after[1]['used']);
        self::assertSame($first[0]['tokenid'], $after[0]['tokenid']);
    }

    public function testLimitsHoldBeforeAnyTokenAndEveryRequestIsCounted(): void
    {
        $this->on();
        $acctid = $this->account('LimitedPlayer');
        $log = static function (string $purpose, string $result, int $n, int $acct = 0, string $address = '', string $requester = '127.0.0.1'): void {
            for ($i = 0; $i < $n; $i++) {
                db_query('INSERT INTO account_mail_log (date,purpose,acctid,addresskey,requesterkey,result) VALUES (?,?,?,?,?,?)', true,
                    [date('Y-m-d H:i:s', time() - 60), $purpose, $acct, resurrection_account_mail_address_key($address), $requester, $result]);
            }
        };
        $check = function (string $why, string $expected = 'none') use ($acctid): void {
            self::assertSame([$expected, []], $this->request('LimitedPlayer'), $why);
            self::assertSame([], self::tokens($acctid), $why);
            db_query('DELETE FROM account_mail_log');
        };
        // The child's REMOTE_ADDR is unset, so its requester key is ''.
        $log('recover-request', 'counted', 10, 0, '', '');
        $check('10 requests an hour from one requester', 'limited');
        $log('recover', 'sent', 3, $acctid);
        $check('3 recovery mails an hour for one account');
        $log('recover', 'sent', 6, $acctid);
        db_query("UPDATE account_mail_log SET date=? WHERE purpose='recover'", true, [date('Y-m-d H:i:s', time() - 7200)]);
        $check('6 recovery mails a day for one account');
        $log('notice', 'sent', 5, 0, 'limitedplayer@example.org');
        $check('5 account mails a day to one address');
        self::settings(['account_mail_hourly' => 10]);
        $log('mailtest', 'sent', 10, 1, 'admin@example.org');
        $check('the hourly limit for all account mail');
        self::assertSame(['The hourly account mail limit was reached; more account mail waits for the next hour.'],
            array_column(db_query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE 'The hourly%'"), 'message'));
        self::settings(['account_mail_hourly' => 100]);
        // A limited account is recorded as such; refused and limited rows of other requesters do not count against this one.
        $log('recover', 'limited', 20, $acctid);
        $log('recover-request', 'counted', 20, 0, '', '203.0.113.1');
        self::assertSame('sent', $this->request('LimitedPlayer')[0]);
        // Malformed input is counted too and goes no further: not a login, over 128 bytes, blank, a control character.
        db_query('DELETE FROM account_mail_log');
        $this->fresh();
        foreach (['', '   ', str_repeat('a', 129), "Limited\nPlayer", "LimitedPlayer\0"] as $bad) {
            self::assertSame(['malformed', []], $this->child('require_once "lib/account_recovery.php"; echo resurrection_account_recovery_request(' . var_export($bad, true) . ');'));
        }
        foreach (['null', '[]', '["LimitedPlayer"]', '42'] as $bad) {
            self::assertSame(['malformed', []], $this->child('require_once "lib/account_recovery.php"; echo resurrection_account_recovery_request(' . $bad . ');'));
        }
        self::assertCount(9, db_query("SELECT eventid FROM account_mail_log WHERE purpose='recover-request' AND result='counted'"));
        // A refusal by the transport is logged and recorded in the game log, and the token stays (its mail may be retried).
        db_query('DELETE FROM account_tokens');
        self::assertSame(['refused', 1], (function (): array { [$out, $messages] = $this->request('LimitedPlayer', 1); return [$out, count($messages)]; })());
        self::assertSame(['refused'], array_column(db_query("SELECT result FROM account_mail_log WHERE purpose='recover'"), 'result'));
        self::assertSame(['Account mail (recover) could not be handed to the mail transport.'],
            array_column(db_query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE 'Account mail (recover)%'"), 'message'));
    }

    public function testResetUpdatesPasswordAuthversionAndLoggedin(): void
    {
        $this->on();
        $acctid = $this->account('ResetPlayer');
        db_query('UPDATE accounts SET loggedin=1 WHERE acctid=?', true, [$acctid]);
        $before = db_query('SELECT password,authversion FROM accounts WHERE acctid=?', true, [$acctid])[0];
        [$token, $grant] = self::opened($acctid, 'resetplayer@example.org');
        // An older live link of the account is used up with it.
        db_query("INSERT INTO account_tokens (acctid,purpose,tokenhash,address,authversion,created,expires,opened,used) VALUES (?,'recover',?,?,?,NOW(),NOW() + INTERVAL 1 HOUR,0,NULL)",
            true, [$acctid, str_repeat('b', 64), 'resetplayer@example.org', (int)$before['authversion']]);
        // Passwords the rules refuse change nothing and leave the grant usable.
        foreach ([['New password one', 'New password two'], ['short', 'short'], [str_repeat('x', 73), str_repeat('x', 73)], ["New password\0 one", "New password\0 one"]] as [$one, $two]) {
            try { resurrection_account_recovery_reset($grant, $one, $two); self::fail('Accepted ' . json_encode($one)); }
            catch (\InvalidArgumentException $error) { self::assertNotSame('', $error->getMessage()); }
        }
        self::assertSame($before, db_query('SELECT password,authversion FROM accounts WHERE acctid=?', true, [$acctid])[0]);
        $reset = resurrection_account_recovery_reset($grant, 'Chosen new password', 'Chosen new password');
        self::assertSame(['acctid' => $acctid, 'login' => 'ResetPlayer', 'address' => 'resetplayer@example.org', 'staff' => false], $reset);
        $after = db_query('SELECT password,authversion,loggedin FROM accounts WHERE acctid=?', true, [$acctid])[0];
        self::assertTrue(\Resurrection\Security\Passwords::verify('Chosen new password', (string)$after['password']));
        self::assertFalse(\Resurrection\Security\Passwords::verify(self::PASSWORD, (string)$after['password']));
        self::assertSame((int)$before['authversion'] + 1, (int)$after['authversion']);
        self::assertSame(0, (int)$after['loggedin']);
        self::assertSame([], db_query("SELECT tokenid FROM account_tokens WHERE acctid=? AND used IS NULL", true, [$acctid]));
        self::assertSame(['Password reset through a recovery link for account ' . $acctid],
            array_column(db_query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE 'Password reset%'"), 'message'));
        // Nothing of the token or the password reached the game log.
        foreach (db_query("SELECT message FROM gamelog WHERE category='account mail'") as $row) {
            self::assertStringNotContainsString($token, (string)$row['message']);
            self::assertStringNotContainsString('Chosen new password', (string)$row['message']);
        }
        // The notice: to the address, no link, no address of the requester.
        [$out, $messages] = $this->child('require_once "lib/account_recovery.php"; resurrection_account_recovery_notices(' . var_export($reset, true) . ', 1800000000); echo "done";', ['REMOTE_ADDR' => '203.0.113.9']);
        self::assertSame('done', $out);
        self::assertCount(1, $messages);
        [$headers, $body] = self::parse($messages[0]);
        self::assertSame(['resetplayer@example.org', 'Your password was changed'], [$headers['To'], $headers['Subject']]);
        // (mail() ends the message with a line break of its own.)
        self::assertSame("The password for ResetPlayer at https://game.example.org/ was changed by a recovery link at 2027-01-15 08:00 UTC.\n\n"
            . "If you did not do this, ask for a new link or contact the game's administrator.\n\n", $body);
        self::assertSame(['notice', 'sent'], array_values(db_query("SELECT purpose,result FROM account_mail_log WHERE purpose='notice'")[0]));
    }

    public function testSecondResetFindsTheTokenUsed(): void
    {
        $this->on();
        $acctid = $this->account('TwiceReset');
        [, $grant] = self::opened($acctid, 'twicereset@example.org');
        resurrection_account_recovery_reset($grant, 'First new password', 'First new password');
        $version = (int)db_query('SELECT authversion FROM accounts WHERE acctid=?', true, [$acctid])[0]['authversion'];
        try { resurrection_account_recovery_reset($grant, 'Second new password', 'Second new password'); self::fail('A used token reset again.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        $after = db_query('SELECT password,authversion FROM accounts WHERE acctid=?', true, [$acctid])[0];
        self::assertSame($version, (int)$after['authversion']);
        self::assertTrue(\Resurrection\Security\Passwords::verify('First new password', (string)$after['password']));
        // A link that was live when opened but whose account changed since: locked, a new address, staff excluded.
        foreach (['locked' => 'UPDATE accounts SET locked=1 WHERE acctid=?', 'address changed' => "UPDATE accounts SET emailaddress='new@example.org' WHERE acctid=?",
                'staff excluded' => 'UPDATE accounts SET superuser=' . SU_EDIT_USERS . ' WHERE acctid=?'] as $why => $change) {
            $other = $this->account('Changed' . str_replace(' ', '', ucwords($why)));
            $login = (string)db_query('SELECT login FROM accounts WHERE acctid=?', true, [$other])[0]['login'];
            [, $grant] = self::opened($other, strtolower($login) . '@example.org');
            if ($why === 'staff excluded') self::settings(['account_mail_staff' => 0]);
            db_query($change, true, [$other]);
            try { resurrection_account_recovery_reset($grant, 'Another new password', 'Another new password'); self::fail($why); }
            catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage(), $why); }
            self::assertSame([], db_query('SELECT tokenid FROM account_tokens WHERE acctid=? AND used IS NOT NULL', true, [$other]), $why);
        }
        // Account mail turned off between the open and the POST.
        $off = $this->account('OffBetween');
        [, $grant] = self::opened($off, 'offbetween@example.org');
        self::settings(['account_mail' => 0]);
        try { resurrection_account_recovery_reset($grant, 'Another new password', 'Another new password'); self::fail('off'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
    }

    public function testTokensDieOnPasswordChangeAndSignIn(): void
    {
        $this->on();
        // Every path that sets a password or starts or ends a session raises authversion, and that kills a recovery
        // link. Sign-out and the administrator's reset are pages: tests/tooling/test_account_recovery_http.py.
        $changed = $this->account('DiesOnChange');
        [$token, $grant] = self::opened($changed, 'diesonchange@example.org');
        resurrection_change_password($changed, self::PASSWORD, 'Changed by the player');
        self::assertNull(resurrection_account_token_open($token, ['recover']));
        try { resurrection_account_recovery_reset($grant, 'Another new password', 'Another new password'); self::fail('password change'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        $signedIn = $this->account('DiesOnSignIn');
        $token = resurrection_account_token_issue($signedIn, 'recover', 'diesonsignin@example.org');
        resurrection_begin_login($signedIn, (string)db_query('SELECT password FROM accounts WHERE acctid=?', true, [$signedIn])[0]['password']);
        self::assertNull(resurrection_account_token_open($token, ['recover']));
        // The recovery purpose is refused where a caller does not accept it (op=open while account mail is off).
        $live = resurrection_account_token_issue($signedIn, 'recover', 'diesonsignin@example.org');
        self::assertNull(resurrection_account_token_open($live, ['mailtest']));
        self::assertNotNull(resurrection_account_token_open($live, ['mailtest', 'recover']));
    }

    public function testStaffResetNotifiesEveryOtherMegauser(): void
    {
        $this->on();
        $staff = $this->account('StaffReset', ['superuser' => SU_MEGAUSER | SU_EDIT_USERS]);
        $other = $this->account('OtherMega', ['superuser' => SU_MEGAUSER]);
        $editor = $this->account('JustEditor', ['superuser' => SU_EDIT_USERS]);
        [, $grant] = self::opened($staff, 'staffreset@example.org');
        $reset = resurrection_account_recovery_reset($grant, 'Staff new password', 'Staff new password');
        self::assertTrue($reset['staff']);
        [$out, $messages] = $this->child('require_once "lib/account_recovery.php"; resurrection_account_recovery_notices(' . var_export($reset, true) . ', 1800000000); echo "done";');
        self::assertSame('done', $out);
        self::assertCount(1, $messages, 'the notice to the staff account\'s own address');
        $mail = db_query("SELECT msgto,msgfrom,subject,body FROM mail WHERE subject='Staff password reset by email' ORDER BY msgto");
        self::assertSame(['1', (string)$other], array_map('strval', array_column($mail, 'msgto')), 'every other megauser, not the account itself or a non-megauser');
        self::assertNotContains((string)$editor, array_map('strval', array_column($mail, 'msgto')));
        self::assertSame('The password of the staff account StaffReset was reset through a recovery link sent to its address at 2027-01-15 08:00 UTC. If this was not expected, check the account in the user editor.',
            (string)$mail[0]['body']);
        self::assertSame('0', (string)$mail[0]['msgfrom']);
        self::assertSame(['Password reset through a recovery link for account ' . $staff, 'Staff account password reset through a recovery link (account ' . $staff . ')'],
            array_column(db_query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE '%reset through%' ORDER BY logid"), 'message'));
    }

    public function testAfterResponseSendsThePageBeforeTheWork(): void
    {
        $this->connectAccountMail();
        // PHP-FPM: fastcgi_finish_request() completes the response. The CLI has no such function, so the child defines
        // one that records when it ran; the real selection in resurrection_account_response_finisher() picks it.
        $code = 'require_once "lib/account_recovery.php"; $GLOBALS["order"] = [];'
            . ' function fastcgi_finish_request(): bool { $GLOBALS["order"][] = "finish"; return true; }'
            . ' ob_start(static function (string $buffer): string { if ($buffer !== "") $GLOBALS["order"][] = "out:" . $buffer; return $buffer; }, 1);'
            . ' $finisher = resurrection_account_response_finisher();'
            . ' resurrection_account_after_response("PAGE", static function (): void { $GLOBALS["order"][] = "work"; echo "LEAK"; }, microtime(true), 1.5, $finisher);'
            . ' echo "|", $finisher, "|", json_encode($GLOBALS["order"]);';
        $started = microtime(true);
        [$out] = $this->child($code);
        self::assertSame('PAGE|fastcgi_finish_request|["out:PAGE","finish","work"]', $out);
        self::assertLessThan(1.4, microtime(true) - $started, 'no floor where the response is completed early');
        // Without a finisher (mod_php, CGI, the built-in server): none is found.
        [$out] = $this->child('require_once "lib/account_recovery.php"; var_export(resurrection_account_response_finisher());');
        self::assertSame('NULL', $out);
        // The work's failure is one fixed line in the PHP error log, never its message.
        [$out] = $this->child('require_once "lib/account_recovery.php"; ini_set("error_log", "/dev/stdout"); ini_set("log_errors", "1");'
            . ' resurrection_account_after_response("PAGE", static function (): void { throw new RuntimeException("player@example.org"); }, microtime(true), 0.0, null);');
        self::assertStringStartsWith('PAGE', $out);
        self::assertStringContainsString('Account mail: the work after a response failed (RuntimeException).', $out);
        self::assertStringNotContainsString('player@example.org', $out);
    }

    public function testFloorHoldsThePageWhereNothingCanFinishTheResponse(): void
    {
        require_once 'lib/account_recovery.php';
        // With a floor: the work runs first, then the page, no sooner than the floor after the start, whatever the work took.
        foreach ([0.0, 0.2] as $takes) {
            $order = [];
            $start = microtime(true);
            ob_start();
            resurrection_account_after_response('PAGE', static function () use (&$order, $takes): void {
                $order[] = 'work:' . ob_get_length();
                usleep((int)($takes * 1000000));
            }, $start, 0.6, null);
            $page = (string)ob_get_clean();
            $elapsed = microtime(true) - $start;
            self::assertSame('PAGE', $page);
            self::assertSame(['work:0'], $order, 'the work ran before any of the page');
            self::assertGreaterThanOrEqual(0.6, $elapsed, (string)$takes);
            self::assertLessThan(0.9, $elapsed, (string)$takes);
        }
        // The forgot-password floor is 1.5 seconds; the page after a reset has none and goes first.
        self::assertSame(1.5, RESURRECTION_ACCOUNT_RESPONSE_FLOOR);
        $order = [];
        $level = ob_get_level();
        ob_start(static function (string $buffer) use (&$order): string { if ($buffer !== '') $order[] = 'out:' . $buffer; return ''; });
        resurrection_account_after_response('PAGE', static function () use (&$order): void { $order[] = 'work'; }, microtime(true), 0.0, null);
        while (ob_get_level() > $level) ob_end_clean();
        // after_response() flushed every buffer, this test's own included: put PHPUnit's back.
        while (ob_get_level() < $level) ob_start();
        self::assertSame(['out:PAGE', 'work'], $order);
    }
}
