<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The email change (design section 4.2 and F2; Phase 6 task 13c): the current password with account mail on and off,
 * the anchored validator and blockdupeemail; while account mail is on, nothing changes until the link mailed to the
 * new address is confirmed, the old verified address is told when it is asked and when it is done without the new
 * address or a link, and a stale authversion or a changed address kills the link. Messages are sent by a child PHP
 * whose mail goes to a capture (tests/support/mail_capture.php). The HTTP side is
 * tests/tooling/test_account_verification_http.py.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it EmailChangeTest). Every account it makes is removed again.
 */
final class MailEmailChangeTest extends TestCase
{
    use AccountMailFixture;

    private const string PASSWORD = 'Synthetic email change password';
    private const string LINK = '#https://game\.example\.org/account\.php\?op=open&t=([A-Za-z0-9_-]{43})#';

    /** @var list<int> */
    private array $made = [];
    private ?string $savedDupe = null;

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
        require_once 'lib/accounts.php';
        require_once 'lib/account_verify.php';
    }

    protected function tearDown(): void
    {
        if ($this->made !== []) {
            $ids = implode(',', $this->made);
            db_query("DELETE FROM accounts_output WHERE acctid IN ($ids)");
            db_query("DELETE FROM accounts WHERE acctid IN ($ids)");
            $this->made = [];
        }
        if ($this->savedDupe !== null) { self::settings(['blockdupeemail' => $this->savedDupe === '' ? null : $this->savedDupe]); $this->savedDupe = null; }
        $this->restoreAccountMailFixture();
        parent::tearDown();
    }

    private function on(): void
    {
        $this->connectAccountMail();
        self::ready();
        self::settings(['account_mail' => 1, 'account_mail_proof' => json_encode(['time' => '2026-11-12 10:00:00', 'acctid' => 1,
            'fp' => resurrection_account_mail_fingerprint('https://game.example.org/', 'game@example.org')])]);
        self::assertTrue(resurrection_account_mail_on());
    }

    /** A new account with PASSWORD and a verified address unless told otherwise; removed in tearDown(). */
    private function account(string $login, array $fields = []): int
    {
        $address = strtolower($login) . '@example.org';
        $values = $fields + ['emailaddress' => $address, 'emailverified' => $address, 'emailvalidation' => ''];
        $values += ['login' => $login, 'name' => 'Farmboy ' . $login, 'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            'laston' => date('Y-m-d H:i:s'), 'regdate' => date('Y-m-d H:i:s'), 'restorepage' => 'village.php', 'badguy' => '', 'companions' => 'a:0:{}',
            'allowednavs' => 'a:0:{}', 'specialmisc' => '', 'bufflist' => 'a:0:{}', 'dragonpoints' => 'a:0:{}', 'prefs' => 'a:0:{}', 'donationconfig' => ''];
        db_query('INSERT INTO accounts (' . implode(',', array_map('db_identifier', array_keys($values))) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')',
            true, array_values($values));
        $id = (int)db_insert_id();
        $this->made[] = $id;
        return $id;
    }

    /** @return array{0: string, 1: string} the account's emailaddress and emailverified */
    private static function address(int $acctid): array
    {
        $row = db_query('SELECT emailaddress,emailverified FROM accounts WHERE acctid=?', true, [$acctid])[0];
        return [(string)$row['emailaddress'], (string)$row['emailverified']];
    }

    /** A change request in a child PHP, in a new capture: [its outcome, the messages it sent]. */
    private function request(int $acctid, string $password, string $new): array
    {
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = null;
        return $this->child('require_once "lib/account_verify.php"; echo resurrection_account_email_change_request(' . $acctid . ', ' . var_export($password, true) . ', ' . var_export($new, true) . ');');
    }

    /** The change link's grant, opened as op=open opens it, from the captured link mail. */
    private static function grant(string $message): array
    {
        self::assertMatchesRegularExpression(self::LINK, $message);
        preg_match(self::LINK, $message, $link);
        $opened = resurrection_account_token_open($link[1], ['email']);
        self::assertNotNull($opened);
        return ['purpose' => 'email', 'tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash'], 'expires' => time() + 900];
    }

    /** The headers and body of the one message a list holds for an address. */
    private static function to(array $messages, string $address): array
    {
        $found = array_values(array_filter(array_map([self::class, 'parse'], $messages), static fn (array $m): bool => ($m[0]['To'] ?? '') === $address));
        self::assertCount(1, $found, 'One message to ' . $address);
        return $found[0];
    }

    public function testNeedsCurrentPasswordWithAccountMailOnAndOff(): void
    {
        $this->on();
        $id = $this->account('ChangePassword');
        foreach (['', 'Not the synthetic password', null] as $wrong) {
            [$out, $messages] = $this->request($id, (string)$wrong, 'elsewhere@example.org');
            self::assertSame(['password', []], [$out, $messages]);
        }
        self::settings(['account_mail' => 0]);
        foreach (['', 'Not the synthetic password'] as $wrong) {
            self::assertSame('password', resurrection_account_email_change_now($id, $wrong, 'elsewhere@example.org'));
            self::assertSame(resurrection_account_email_change_now($id, ['not', 'a', 'string'], 'elsewhere@example.org'), 'password');
        }
        self::assertSame(['changepassword@example.org', 'changepassword@example.org'], self::address($id), 'Nothing changed without the current password.');
        self::assertSame([], db_query("SELECT tokenid FROM account_tokens WHERE acctid=?", true, [$id]));
        // With it, while account mail is off: at once, checked, and unverified.
        foreach (['x a@bb.cc y', 'two@example.org,three@example.org', 'a@b', "line@example.org\r\nBcc: x@example.org"] as $invalid) {
            self::assertSame('invalid', resurrection_account_email_change_now($id, self::PASSWORD, $invalid), $invalid);
        }
        self::assertSame('same', resurrection_account_email_change_now($id, self::PASSWORD, 'ChangePassword@example.org'));
        self::assertSame('changed', resurrection_account_email_change_now($id, self::PASSWORD, 'changed@example.org'));
        self::assertSame(['changed@example.org', ''], self::address($id));
    }

    public function testNewAddressConfirmsBeforeItTakesEffect(): void
    {
        $this->on();
        $id = $this->account('ChangeConfirm');
        [$out, $messages] = $this->request($id, self::PASSWORD, 'confirm-new@example.org');
        self::assertSame('sent', $out);
        self::assertCount(2, $messages, 'The link to the new address and the notice to the old one.');
        self::assertSame(['changeconfirm@example.org', 'changeconfirm@example.org'], self::address($id), 'Nothing changes before the confirmation.');
        [$headers, $body] = self::to($messages, 'confirm-new@example.org');
        self::assertSame('Confirm your new email address', $headers['Subject']);
        self::assertStringContainsString('Someone asked to make this the email address of the account ChangeConfirm at https://game.example.org/.', $body);
        $grant = self::grant($messages[0] === '' ? '' : implode("\n", $messages));
        self::assertSame(['acctid' => $id, 'login' => 'ChangeConfirm', 'address' => 'confirm-new@example.org'], resurrection_account_confirm_view($grant));
        $done = resurrection_account_confirm($grant);
        self::assertSame(['acctid' => $id, 'login' => 'ChangeConfirm', 'purpose' => 'email', 'address' => 'confirm-new@example.org', 'old' => 'changeconfirm@example.org',
            'oldverified' => true, 'pending' => false], $done);
        self::assertSame(['confirm-new@example.org', 'confirm-new@example.org'], self::address($id));
        // The link and its companion are used up.
        self::assertSame([], db_query('SELECT tokenid FROM account_tokens WHERE acctid=? AND used IS NULL', true, [$id]));
        try { resurrection_account_confirm($grant); self::fail('A used change link confirmed again.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
    }

    public function testOldAddressIsToldWithoutTheNewOne(): void
    {
        $this->on();
        $id = $this->account('ChangeNotice');
        [$out, $messages] = $this->request($id, self::PASSWORD, 'notice-new@example.org');
        self::assertSame('sent', $out);
        $grant = self::grant(implode("\n", $messages));
        [$headers, $body] = self::to($messages, 'changenotice@example.org');
        self::assertSame('A change of your email address was requested', $headers['Subject']);
        self::assertMatchesRegularExpression('/\AA change of the email address of the account ChangeNotice in Legend of the Green Dragon \(game\.example\.org\) to another address was requested at \d{4}-\d\d-\d\d \d\d:\d\d UTC\.\n\n'
            . 'If this was not you, sign in and change your password; that cancels the change\.\n+\z/', $body);
        $done = resurrection_account_confirm($grant);
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = null;
        [$out, $after] = $this->child('require_once "lib/account_verify.php"; var_export(resurrection_account_email_notice(' . $id . ', "ChangeNotice", ' . var_export($done['old'], true) . ', true));');
        self::assertSame('true', $out);
        [$headers, $second] = self::to($after, 'changenotice@example.org');
        self::assertSame('Your email address was changed', $headers['Subject']);
        self::assertStringContainsString('The email address of the account ChangeNotice in Legend of the Green Dragon (game.example.org) was changed to another address at ', $second);
        foreach ([$body, $second] as $notice) {
            self::assertStringNotContainsString('notice-new', $notice);
            self::assertStringNotContainsString('http', $notice);
            self::assertStringNotContainsString('op=open', $notice);
            self::assertDoesNotMatchRegularExpression('/[A-Za-z0-9_-]{43}/', $notice);
        }
        // No notice goes to an old address that was never verified.
        $unverified = $this->account('ChangeNoNotice', ['emailverified' => '']);
        [$out, $messages] = $this->request($unverified, self::PASSWORD, 'nonotice-new@example.org');
        self::assertSame('sent', $out);
        self::assertCount(1, $messages);
        self::to($messages, 'nonotice-new@example.org');
    }

    public function testAStaleAuthversionOrAChangedAddressKillsTheLink(): void
    {
        $this->on();
        foreach (['signed in since' => 'UPDATE accounts SET authversion=authversion+1 WHERE acctid=?',
            'address changed by another path' => "UPDATE accounts SET emailaddress='third@example.org' WHERE acctid=?",
            'address changed and changed back is still the address' => null] as $label => $change) {
            $id = $this->account('ChangeDead' . count($this->made));
            [$out, $messages] = $this->request($id, self::PASSWORD, 'dead-new' . $id . '@example.org');
            self::assertSame('sent', $out, $label);
            $grant = self::grant(implode("\n", $messages));
            $before = self::address($id);
            if ($change === null) {
                $done = resurrection_account_confirm($grant);
                self::assertSame('dead-new' . $id . '@example.org', $done['address'], $label);
                continue;
            }
            db_query($change, true, [$id]);
            $changed = self::address($id);
            try { resurrection_account_confirm($grant); self::fail('Confirmed after: ' . $label); }
            catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage(), $label); }
            self::assertSame($changed, self::address($id), $label);
            self::assertNotSame('dead-new' . $id . '@example.org', self::address($id)[0], $label);
            unset($before);
        }
        // A second request replaces the first: the first link is dead, the second works.
        $id = $this->account('ChangeTwice');
        [, $first] = $this->request($id, self::PASSWORD, 'twice-a@example.org');
        [, $second] = $this->request($id, self::PASSWORD, 'twice-b@example.org');
        preg_match(self::LINK, implode("\n", $first), $link);
        self::assertNull(resurrection_account_token_open($link[1], ['email']));
        $done = resurrection_account_confirm(self::grant(implode("\n", $second)));
        self::assertSame('twice-b@example.org', $done['address']);
        // Account mail turned off kills the link too.
        $id = $this->account('ChangeOff');
        [, $messages] = $this->request($id, self::PASSWORD, 'off-new@example.org');
        $grant = self::grant(implode("\n", $messages));
        self::settings(['account_mail' => 0]);
        try { resurrection_account_confirm($grant); self::fail('Confirmed while account mail is off.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        self::assertSame(['changeoff@example.org', 'changeoff@example.org'], self::address($id));
    }

    public function testBlockdupeemailOnChangeAndAtConfirmation(): void
    {
        $this->on();
        $this->savedDupe = (string)(db_query("SELECT value FROM settings WHERE setting='blockdupeemail'")[0]['value'] ?? '');
        self::settings(['blockdupeemail' => 1]);
        $id = $this->account('ChangeDupe');
        $this->account('ChangeDupeOther', ['emailaddress' => 'taken@example.org']);
        self::assertSame(['taken', []], $this->request($id, self::PASSWORD, 'Taken@Example.org'));
        self::settings(['account_mail' => 0]);
        self::assertSame('taken', resurrection_account_email_change_now($id, self::PASSWORD, 'taken@example.org'));
        self::settings(['account_mail' => 1]);
        // Taken by another account between the request and the confirmation: the link no longer works.
        [$out, $messages] = $this->request($id, self::PASSWORD, 'later@example.org');
        self::assertSame('sent', $out);
        $grant = self::grant(implode("\n", $messages));
        $this->account('ChangeDupeLater', ['emailaddress' => 'later@example.org']);
        try { resurrection_account_confirm($grant); self::fail('Confirmed an address another account now holds.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        self::assertSame(['changedupe@example.org', 'changedupe@example.org'], self::address($id));
        // With blockdupeemail off, the same address is allowed.
        self::settings(['blockdupeemail' => 0]);
        self::assertSame('sent', $this->request($id, self::PASSWORD, 'taken@example.org')[0]);
    }

    public function testChangeRequestLimits(): void
    {
        $this->on();
        $id = $this->account('ChangeLimit');
        foreach (['a', 'b', 'c'] as $n) self::assertSame('sent', $this->request($id, self::PASSWORD, "limit-$n@example.org")[0]);
        // 3 a day per account (section 5): the fourth is held back, recorded, and sends nothing.
        self::assertSame(['limited', []], $this->request($id, self::PASSWORD, 'limit-d@example.org'));
        self::assertSame(['counted' => 3, 'limited' => 1], array_map('intval', array_column(db_query("SELECT result,COUNT(*) AS n FROM account_mail_log WHERE purpose='email-request' AND acctid=? GROUP BY result ORDER BY result", true, [$id]), 'n', 'result')));
        // While account mail is off, nothing is mailed and the request is refused as off.
        self::settings(['account_mail' => 0]);
        self::assertSame(['off', []], $this->request($id, self::PASSWORD, 'limit-e@example.org'));
    }
}
