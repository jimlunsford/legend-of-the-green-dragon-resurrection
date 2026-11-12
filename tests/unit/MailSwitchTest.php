<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Account mail's switch (design section 2.5; Phase 6 task 13a). It can be turned on only while serverurl is a valid
 * https:// address, Admin Email is a usable sender and not the default, and a test mail sent with that serverurl and
 * sender was confirmed through its link. Off is always allowed. Changing serverurl or the sender afterwards turns it
 * off in effect, though the switch stays on, until a new test mail is confirmed.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it Account..., which would run before the install).
 */
final class MailSwitchTest extends TestCase
{
    use AccountMailFixture;

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
    }

    /** Send a test mail (child process, captured) and confirm it through its link's grant. Returns the confirming login. */
    private function sendAndConfirm(): string
    {
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('sent', $out);
        self::assertSame(1, preg_match('#account\.php\?op=open&t=([A-Za-z0-9_-]{43})#', end($messages), $match));
        $opened = resurrection_account_token_open($match[1], ['mailtest']);
        self::assertNotNull($opened);
        return resurrection_account_mail_test_confirm(['tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash']]);
    }

    public function testRefusedWithoutHttpsServerUrl(): void
    {
        $this->connectAccountMail();
        self::ready();
        $this->sendAndConfirm();
        foreach ([null, '', 'http://game.example.org/', 'not a url'] as $url) {
            self::settings(['serverurl' => $url, 'account_mail' => 0]);
            self::assertContains('serverurl', resurrection_account_mail_blockers(), (string)$url);
            self::assertFalse(resurrection_account_mail_turn_on());
            self::assertFalse(resurrection_account_mail_switch());
            self::assertFalse(resurrection_account_mail_on());
        }
    }

    public function testRefusedWithTheDefaultSender(): void
    {
        $this->connectAccountMail();
        self::ready();
        foreach (['postmaster@localhost.com', 'Postmaster@LocalHost.com', 'postmaster@localhost', '', 'not an address', "game@example.org\nBcc: x@example.org"] as $sender) {
            self::settings(['gameadminemail' => $sender]);
            self::assertContains('sender', resurrection_account_mail_blockers(), $sender);
            self::assertFalse(resurrection_account_mail_turn_on());
            [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
            self::assertSame('not-ready', $out);
            self::assertSame([], $messages);
        }
        self::assertFalse(resurrection_account_mail_switch());
    }

    public function testRefusedWithoutAConfirmedTestMail(): void
    {
        $this->connectAccountMail();
        self::ready();
        self::assertSame(['no-proof'], resurrection_account_mail_blockers());
        self::assertFalse(resurrection_account_mail_turn_on());
        // A test mail sent but not confirmed proves nothing yet.
        [$out] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('sent', $out);
        self::assertSame(['no-proof'], resurrection_account_mail_blockers());
        self::assertFalse(resurrection_account_mail_turn_on());
        self::assertSame('', db_query('SELECT emailverified FROM accounts WHERE acctid=1')[0]['emailverified']);
        // Confirmed: the proof is stored, the pending record goes, that account's address is verified; the switch can go on.
        self::assertSame('FixtureAdmin', $this->sendAndConfirm());
        self::assertSame([], resurrection_account_mail_blockers());
        self::assertSame('admin@example.org', db_query('SELECT emailverified FROM accounts WHERE acctid=1')[0]['emailverified']);
        self::assertSame('', resurrection_account_mail_setting('account_mail_pending'));
        $proof = resurrection_account_mail_proof();
        self::assertSame(1, $proof['acctid']);
        self::assertSame(resurrection_account_mail_fingerprint('https://game.example.org/', 'game@example.org'), $proof['fp']);
        self::assertFalse(resurrection_account_mail_on(), 'Confirming does not turn it on.');
        self::assertTrue(resurrection_account_mail_turn_on());
        self::assertTrue(resurrection_account_mail_on());
        // Off is always allowed.
        resurrection_account_mail_turn_off();
        self::assertFalse(resurrection_account_mail_switch());
        self::settings(['serverurl' => 'http://game.example.org/', 'account_mail' => 1]);
        resurrection_account_mail_turn_off();
        self::assertFalse(resurrection_account_mail_switch());
        // Every setting name fits settings.setting, varchar(20).
        foreach ([...RESURRECTION_ACCOUNT_MAIL_SETTINGS, 'account_mail_hourly', 'account_mail_staff'] as $name) self::assertLessThanOrEqual(20, strlen($name), $name);
    }

    public function testServerUrlChangeTurnsItOffInEffect(): void
    {
        $this->connectAccountMail();
        self::ready();
        $this->sendAndConfirm();
        self::assertTrue(resurrection_account_mail_turn_on());
        foreach ([['serverurl' => 'https://other.example.org/'], ['gameadminemail' => 'other@example.org']] as $change) {
            self::ready();
            self::settings(['gameadminemail' => 'game@example.org']);
            self::assertTrue(resurrection_account_mail_on());
            self::settings($change);
            self::assertTrue(resurrection_account_mail_switch(), 'The switch itself stays on.');
            self::assertFalse(resurrection_account_mail_on());
            self::assertSame(['stale-proof'], resurrection_account_mail_blockers());
            // Changing it back restores the proof's match.
            self::ready();
            self::settings(['gameadminemail' => 'game@example.org']);
            self::assertTrue(resurrection_account_mail_on());
        }
        // A new test mail for the new serverurl, confirmed, brings it back in effect.
        self::settings(['serverurl' => 'https://other.example.org/']);
        self::assertFalse(resurrection_account_mail_on());
        $this->sendAndConfirm();
        self::assertTrue(resurrection_account_mail_on());
    }

    public function testAConfirmationIsRefusedWhenAnythingMoved(): void
    {
        $this->connectAccountMail();
        self::ready();
        $send = function (): array {
            [, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
            preg_match('#t=([A-Za-z0-9_-]{43})#', end($messages), $match);
            $opened = resurrection_account_token_open($match[1], ['mailtest']);
            return ['tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash']];
        };
        $refused = function (array $grant, string $why): void {
            try { resurrection_account_mail_test_confirm($grant); self::fail('Confirmed although ' . $why); }
            catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage(), $why); }
            self::assertNull(resurrection_account_mail_proof(), $why);
            self::assertSame('', db_query('SELECT emailverified FROM accounts WHERE acctid=1')[0]['emailverified'], $why);
        };
        // The serverurl changed between the mail and its confirmation.
        $grant = $send();
        self::settings(['serverurl' => 'https://other.example.org/']);
        $refused($grant, 'the serverurl changed');
        // The administrator's address changed.
        self::ready();
        $grant = $send();
        db_query("UPDATE accounts SET emailaddress='new@example.org' WHERE acctid=1");
        $refused($grant, 'the address changed');
        // A newer test mail was sent: only the newest one confirms.
        self::ready();
        $older = $send();
        $newer = $send();
        $refused($older, 'a newer test mail is pending');
        // The administrator signed in again (authversion moved).
        db_query('UPDATE accounts SET authversion=authversion+1 WHERE acctid=1');
        $refused($newer, 'the account signed in again');
        // A grant for a token that is not a test mail's.
        $token = resurrection_account_token_issue(1, 'verify', 'admin@example.org');
        $opened = resurrection_account_token_open($token, ['verify']);
        $refused(['tokenid' => $opened['tokenid'], 'hash' => $opened['tokenhash']], 'the token is not a test mail\'s');
        // Nothing was used up by a refusal: the newest test mail's token is unused (authversion aside).
        self::assertSame(0, (int)db_query('SELECT COUNT(*) AS n FROM account_tokens WHERE used IS NOT NULL')[0]['n']);
    }

    public function testTheTestMailGoesOnlyToTheAdministratorsOwnAddressAndIsLimited(): void
    {
        $this->connectAccountMail();
        self::ready();
        // No address on the account: nothing is sent, nothing is logged as mail.
        db_query("UPDATE accounts SET emailaddress='' WHERE acctid=1");
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame(['no-address', []], [$out, $messages]);
        db_query("UPDATE accounts SET emailaddress='admin@example.org' WHERE acctid=1");
        for ($i = 1; $i <= 5; $i++) {
            [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
            self::assertSame('sent', $out, 'test mail ' . $i);
        }
        self::assertCount(5, $messages);
        foreach ($messages as $message) self::assertSame('admin@example.org', self::parse($message)[0]['To']);
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('limited', $out);
        self::assertCount(5, $messages);
        self::assertSame(['limited' => '1', 'sent' => '5'], array_column(db_query("SELECT result,COUNT(*) AS n FROM account_mail_log WHERE purpose='mailtest' GROUP BY result ORDER BY result"), 'n', 'result'));
        // The section sends only to the signed-in account: its one call passes the session's account ID.
        self::assertStringContainsString("resurrection_account_mail_test_send(\$acctid)", (string)file_get_contents('lib/account_mail_admin.php'));
        self::assertStringContainsString("\$acctid = (int)\$session['user']['acctid'];", (string)file_get_contents('configuration.php'));
    }
}
