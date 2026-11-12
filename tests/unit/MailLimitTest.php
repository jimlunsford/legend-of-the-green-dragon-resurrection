<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Account mail's limits (design section 5; Phase 6 task 13a), one test per row of its table. Each is counted in
 * account_mail_log by purpose and by account, address key or requester key (REMOTE_ADDR only: the IPv4 address or the
 * IPv6 /64, never X-Forwarded-For), in its window. Mail limits count only mail handed to the transport (sent or
 * refused); request limits count every request but the ones refused for a limit.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it Account..., which would run before the install).
 */
final class MailLimitTest extends TestCase
{
    use AccountMailFixture;

    private const int NOW = 1800000000;
    private ?array $server = null;

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
    }

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        if ($this->server !== null) $_SERVER = $this->server;
        $this->restoreAccountMailFixture();
        parent::tearDown();
    }

    /** Log $count rows, each $age seconds old, with an address made from $address and the row's number when it has %d. */
    private static function log(int $count, string $purpose, string $result, int $acctid, string $address, int $age = 60): void
    {
        for ($i = 0; $i < $count; $i++) resurrection_account_mail_log($purpose, $result, $acctid, sprintf($address, $i), self::NOW - $age);
    }

    private static function limited(string $purpose, int $acctid = 0, string $address = ''): ?string
    {
        return resurrection_account_mail_limited($purpose, $acctid, $address, self::NOW);
    }

    /** A requester limit: $max requests from one address hold the next back, from that address only, for the hour. */
    private function requesterRow(string $name, string $purpose, int $max): void
    {
        $this->connectAccountMail();
        self::assertSame(['requester', [$purpose], false, 3600, $max], RESURRECTION_ACCOUNT_MAIL_LIMITS[$name]);
        self::log($max - 1, $purpose, 'counted', 0, '');
        self::log(3, $purpose, 'limited', 0, '');
        self::assertNull(self::limited($purpose), 'Requests refused for the limit do not count.');
        self::log(1, $purpose, 'counted', 1, '', 3600);
        self::assertNull(self::limited($purpose), 'A request an hour old does not count.');
        self::log(1, $purpose, 'counted', 1, 'admin@example.org');
        self::assertSame($name, self::limited($purpose));
        // Another requester is not held back; a forwarded-for header changes nothing.
        $_SERVER['REMOTE_ADDR'] = '198.51.100.8';
        self::assertNull(self::limited($purpose));
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
        self::assertSame($name, self::limited($purpose));
    }

    /** An account limit on mail: $max mails to one account in $window hold the next back, for that account only. */
    private function accountMailRow(string $name, string $purpose, int $window, int $max): void
    {
        $this->connectAccountMail();
        self::assertSame(['account', [$purpose], true, $window, $max], RESURRECTION_ACCOUNT_MAIL_LIMITS[$name]);
        // Distinct addresses, so the address limit is not what holds it back; old enough for the day rows to pass the hour.
        $age = $window === 86400 ? 7200 : 60;
        self::log($max - 1, $purpose, 'sent', 1, 'a%d@example.org', $age);
        self::log(4, $purpose, 'limited', 1, 'l%d@example.org', $age);
        self::log(4, $purpose, 'skipped', 1, 's%d@example.org', $age);
        self::assertNull(self::limited($purpose, 1, 'new@example.org'), 'Only mail handed to the transport counts.');
        self::log(1, $purpose, 'sent', 1, 'w@example.org', $window);
        self::assertNull(self::limited($purpose, 1, 'new@example.org'), 'Mail outside the window does not count.');
        self::log(1, $purpose, 'refused', 1, 'r@example.org', $age);
        self::assertSame($name, self::limited($purpose, 1, 'new@example.org'));
        self::assertNull(self::limited($purpose, 2, 'new@example.org'));
    }

    public function testRecoverRequesterRow(): void { $this->requesterRow('recover-requester', 'recover-request', 10); }

    public function testOpenRequesterRow(): void { $this->requesterRow('open-requester', 'open', 30); }

    public function testSignupRequesterRow(): void { $this->requesterRow('signup-requester', 'signup', 5); }

    public function testRecoverAccountHourRow(): void { $this->accountMailRow('recover-account-hour', 'recover', 3600, 3); }

    public function testRecoverAccountDayRow(): void { $this->accountMailRow('recover-account-day', 'recover', 86400, 6); }

    public function testVerifyAccountHourRow(): void { $this->accountMailRow('verify-account-hour', 'verify', 3600, 3); }

    public function testVerifyAccountDayRow(): void { $this->accountMailRow('verify-account-day', 'verify', 86400, 6); }

    public function testMailtestAccountHourRow(): void { $this->accountMailRow('mailtest-account-hour', 'mailtest', 3600, 5); }

    public function testEmailAccountDayRow(): void
    {
        // Address change requests: three a day per account, counted whatever their outcome but a limit refusal.
        $this->connectAccountMail();
        self::assertSame(['account', ['email-request'], false, 86400, 3], RESURRECTION_ACCOUNT_MAIL_LIMITS['email-account-day']);
        self::log(2, 'email-request', 'counted', 1, '', 7200);
        self::log(3, 'email-request', 'limited', 1, '', 7200);
        self::assertNull(self::limited('email-request', 1));
        self::log(1, 'email-request', 'counted', 1, '', 86400);
        self::assertNull(self::limited('email-request', 1));
        self::log(1, 'email-request', 'skipped', 1, '');
        self::assertSame('email-account-day', self::limited('email-request', 1));
        self::assertNull(self::limited('email-request', 2));
    }

    public function testAddressDayRow(): void
    {
        // Five mails a day to one address, whatever the purpose and the account; the address is compared lower-cased.
        $this->connectAccountMail();
        self::assertSame(['address', null, true, 86400, 5], RESURRECTION_ACCOUNT_MAIL_LIMITS['address-day']);
        foreach (['mailtest', 'recover', 'verify', 'notice'] as $i => $purpose) resurrection_account_mail_log($purpose, 'sent', $i + 1, 'Player@Example.org', self::NOW - 7200);
        self::log(5, 'recover-request', 'counted', 0, 'player@example.org');
        self::log(5, 'email', 'limited', 1, 'player@example.org');
        self::assertNull(self::limited('email', 9, 'player@example.org'), 'Requests and mail held back do not count.');
        resurrection_account_mail_log('email', 'refused', 7, 'player@example.org ', self::NOW - 60);
        foreach (['mailtest', 'recover', 'verify', 'email', 'notice'] as $purpose) {
            self::assertSame('address-day', self::limited($purpose, 9, 'PLAYER@example.org'), $purpose);
        }
        self::assertNull(self::limited('email', 9, 'other@example.org'));
        // The log keeps the address's key, never the address.
        self::assertSame([], db_query("SELECT eventid FROM account_mail_log WHERE addresskey LIKE '%@%'"));
        self::assertSame(hash('sha256', 'player@example.org'), db_query("SELECT addresskey FROM account_mail_log WHERE purpose='email' AND result='refused'")[0]['addresskey']);
    }

    public function testGlobalHourRow(): void
    {
        // The account_mail_hourly setting caps all account mail in an hour; reaching it writes one game log line an hour.
        $this->connectAccountMail();
        self::assertSame(['global', null, true, 3600, 0], RESURRECTION_ACCOUNT_MAIL_LIMITS['global-hour']);
        self::settings(['account_mail_hourly' => 10]);
        self::assertSame(10, resurrection_account_mail_limit_max('global-hour'));
        for ($i = 0; $i < 9; $i++) resurrection_account_mail_log(['mailtest', 'recover', 'verify', 'email', 'notice'][$i % 5], 'sent', $i + 1, "g$i@example.org", self::NOW - 60);
        self::log(5, 'notice', 'limited', 1, 'x%d@example.org');
        self::log(5, 'recover-request', 'counted', 0, '');
        self::assertNull(self::limited('notice', 50, 'new@example.org'));
        resurrection_account_mail_log('notice', 'refused', 9, 'g9@example.org', self::NOW - 60);
        foreach (['mailtest', 'recover', 'verify', 'email', 'notice'] as $purpose) self::assertSame('global-hour', self::limited($purpose, 50, 'new@example.org'), $purpose);
        $notices = db_query("SELECT message FROM gamelog WHERE category='account mail'");
        self::assertSame([['message' => 'The hourly account mail limit was reached; more account mail waits for the next hour.']], $notices);
        // The setting is bounded: 10 to 1000 (lib/settings.php), as LOGINTIMEOUT is.
        self::assertSame([10, 1000], resurrection_bounded_settings()['account_mail_hourly']);
    }

    public function testRequestsAreCountedWhetherOrNotAnAccountMatched(): void
    {
        // A request is logged before anything is looked up, with the account when one matched and 0 when none did: the
        // requester limit counts both alike, so its answer cannot tell an attacker which addresses have accounts.
        $this->connectAccountMail();
        for ($i = 0; $i < 9; $i++) resurrection_account_mail_log('recover-request', 'counted', $i % 2, '', self::NOW - 60);
        self::assertNull(self::limited('recover-request'));
        resurrection_account_mail_log('recover-request', 'counted', 0, '', self::NOW - 60);
        self::assertSame('recover-requester', self::limited('recover-request'));
        // Each purpose is checked against its own rows only.
        self::assertSame(['mailtest-account-hour', 'address-day', 'global-hour'], RESURRECTION_ACCOUNT_MAIL_PURPOSE_LIMITS['mailtest']);
        self::assertNull(self::limited('open'));
        self::assertNull(self::limited('signup'));
        // account.php counts every opening of a link, before the token is looked at.
        $page = (string)file_get_contents('account.php');
        self::assertLessThan(strpos($page, 'resurrection_account_token_open('), strpos($page, "resurrection_account_mail_log('open', 'counted');"));
        $this->expectException(\InvalidArgumentException::class);
        self::limited('unknown');
    }

    public function testTheRequesterKeyIsRemoteAddrOnly(): void
    {
        self::assertSame('198.51.100.7', resurrection_account_mail_requester('198.51.100.7'));
        self::assertSame('2001:0db8:0001:0002::/64', resurrection_account_mail_requester('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        self::assertSame(resurrection_account_mail_requester('2001:db8:1:2::1'), resurrection_account_mail_requester('2001:db8:1:2:ffff::9'));
        self::assertNotSame(resurrection_account_mail_requester('2001:db8:1:2::1'), resurrection_account_mail_requester('2001:db8:1:3::1'));
        self::assertSame('', resurrection_account_mail_requester('not an address'));
        self::assertSame('', resurrection_account_mail_requester(''));
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.2';
        self::assertSame('203.0.113.5', resurrection_account_mail_requester());
        self::assertDoesNotMatchRegularExpression('/FORWARDED|X_REAL_IP|CLIENT_IP/', (string)file_get_contents('lib/account_mail.php'));
        self::assertLessThanOrEqual(40, strlen(resurrection_account_mail_requester('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff')));
    }
}
