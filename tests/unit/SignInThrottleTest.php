<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Sign-in throttling (Phase 6 task 18, F4; lib/signin_throttle.php, docs/PHASE6-TASK18-20261114.md): the three limits
 * counted from faillog (one name from one address, one address, one name from every address), each a bounded Game
 * Settings value; the requester key is REMOTE_ADDR (the IPv4 address or the IPv6 /64), never X-Forwarded-For; the
 * throttled attempt's own row counts against nothing; an unknown name is checked against a hash of the same cost, so it
 * takes as long as a wrong password; daily maintenance deletes faillog rows older than 30 days; setup.php's and account
 * mail's limits keep their own counts.
 *
 * It runs on the installed test database (after FreshInstallTest). Every row it writes is dated in 2033, after any row
 * another test writes, and removed with every setting it changes.
 */
final class SignInThrottleTest extends TestCase
{
    private const int NOW = 2_000_000_000;
    private const array SETTINGS = ['signin_fail_pair', 'signin_fail_address', 'signin_fail_account'];
    private const string PASSWORD = 'Synthetic throttle password';

    /** @var array<string, string|null> */
    private array $saved = [];
    private int $firstEvent = 0;
    /** @var list<int> */
    private array $accounts = [];
    /** @var array<string, mixed> */
    private array $server = [];

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'datetime', 'modules', 'output', 'sanitize', 'translator', 'account_mail',
                     'signin_throttle', 'accounts', 'setup/guard'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
    }

    protected function setUp(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        $GLOBALS['session'] = ['user' => ['acctid' => 0]];
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        foreach (self::SETTINGS as $name) {
            $rows = db_query('SELECT value FROM settings WHERE setting=?', true, [$name]);
            $this->saved[$name] = $rows === [] ? null : (string)$rows[0]['value'];
        }
        $this->firstEvent = (int)db_query('SELECT COALESCE(MAX(eventid),0) AS n FROM faillog')[0]['n'];
        self::assertSame([], db_query('SELECT eventid FROM faillog WHERE date>?', true, [date('Y-m-d H:i:s', self::NOW - 40 * 86400)]),
            'Fixture invalid: faillog holds rows dated near the test clock.');
        $this->server = $_SERVER;
        self::settings(['signin_fail_pair' => 5, 'signin_fail_address' => 20, 'signin_fail_account' => 50]);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        if ($this->saved !== []) {
            self::settings($this->saved);
            db_query('DELETE FROM faillog WHERE eventid>?', true, [$this->firstEvent]);
            foreach ($this->accounts as $id) {
                db_query('DELETE FROM accounts_output WHERE acctid=?', true, [$id]);
                db_query('DELETE FROM accounts WHERE acctid=?', true, [$id]);
            }
        }
        $this->saved = [];
        $this->accounts = [];
        parent::tearDown();
    }

    /** @param array<string, int|string|null> $values */
    private static function settings(array $values): void
    {
        foreach ($values as $name => $value) {
            if ($value === null) db_query('DELETE FROM settings WHERE setting=?', true, [$name]);
            else db_query('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true, [$name, (string)$value, (string)$value]);
        }
        $GLOBALS['settings'] = null;
    }

    private static function from(string $address, ?string $forwarded = null): void
    {
        $_SERVER['REMOTE_ADDR'] = $address;
        if ($forwarded === null) unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        else $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
    }

    /** $count failures for $login from the current address, one a second, ending at $end. */
    private static function failures(string $login, int $count, int $end = self::NOW): void
    {
        for ($i = $count - 1; $i >= 0; $i--) resurrection_signin_record(RESURRECTION_SIGNIN_FAILED, $login, $end - $i);
    }

    public function testOneNameFromOneAddress(): void
    {
        self::from('203.0.113.5');
        self::failures('PairPlayer', 4);
        self::assertNull(resurrection_signin_throttled('PairPlayer', self::NOW + 1), '4 failures: allowed');
        self::failures('PairPlayer', 1, self::NOW + 1);
        // The fifth failure holds the pair: one more attempt is allowed once the first of the five leaves the window.
        self::assertSame(15, resurrection_signin_throttled('PairPlayer', self::NOW + 2));
        self::assertSame(15, resurrection_signin_throttled('pairplayer ', self::NOW + 2), 'the name as the database compares it');
        self::assertSame(1, resurrection_signin_throttled('PairPlayer', self::NOW + 896));
        self::assertNull(resurrection_signin_throttled('PairPlayer', self::NOW - 3 + 900), 'the first failure has left the window');
        // Another name from the same address, and the same name from another address, are not held.
        self::assertNull(resurrection_signin_throttled('OtherPlayer', self::NOW + 2));
        self::from('203.0.113.6');
        self::assertNull(resurrection_signin_throttled('PairPlayer', self::NOW + 2));
        // The limit is the bounded setting: 3 holds after three; a stored 0 is read as its lowest bound, 3.
        self::from('203.0.113.9');
        self::failures('PairLow', 3);
        self::assertNull(resurrection_signin_throttled('PairLow', self::NOW + 1));
        self::settings(['signin_fail_pair' => 3]);
        self::assertSame(15, resurrection_signin_throttled('PairLow', self::NOW + 1));
        self::settings(['signin_fail_pair' => 0]);
        self::assertSame(3, resurrection_signin_limit_max('pair'));
        self::assertSame(15, resurrection_signin_throttled('PairLow', self::NOW + 1));
    }

    public function testOneAddressWhateverTheName(): void
    {
        self::from('203.0.113.7');
        for ($i = 1; $i <= 19; $i++) self::failures('Guess' . $i, 1, self::NOW - 20 + $i);
        self::assertNull(resurrection_signin_throttled('Guess20', self::NOW));
        self::failures('Guess20', 1, self::NOW);
        self::assertSame(15, resurrection_signin_throttled('Anyone', self::NOW + 1), '20 failures from one address hold every name');
        // X-Forwarded-For is never the address: a forwarded header naming another address changes nothing.
        self::from('203.0.113.7', '198.51.100.1');
        self::assertSame(15, resurrection_signin_throttled('Anyone', self::NOW + 1));
        self::from('198.51.100.1', '203.0.113.7');
        self::assertNull(resurrection_signin_throttled('Anyone', self::NOW + 1));
        // IPv6: one /64 is one address.
        self::from('2001:db8:1:2::10');
        self::failures('SixPlayer', 5);
        self::from('2001:db8:1:2:ffff::1');
        self::assertSame(15, resurrection_signin_throttled('SixPlayer', self::NOW + 1), 'the same /64');
        self::from('2001:db8:1:3::10');
        self::assertNull(resurrection_signin_throttled('SixPlayer', self::NOW + 1), 'another /64');
        self::assertSame('2001:0db8:0001:0002::/64', db_query('SELECT ip FROM faillog WHERE eventid>? ORDER BY eventid DESC LIMIT 1', true, [$this->firstEvent])[0]['ip']);
    }

    public function testOneNameFromEveryAddress(): void
    {
        // 49 addresses, one failure each: allowed; the 50th holds the name from any address, for an hour.
        for ($i = 1; $i <= 49; $i++) {
            self::from('198.51.100.' . $i);
            self::failures('SprayTarget', 1, self::NOW - 50 + $i);
        }
        self::from('198.51.100.200');
        self::assertNull(resurrection_signin_throttled('SprayTarget', self::NOW));
        self::from('198.51.100.50');
        self::failures('SprayTarget', 1, self::NOW);
        self::from('192.0.2.1');
        self::assertSame(60, resurrection_signin_throttled('SprayTarget', self::NOW + 1));
        self::assertNull(resurrection_signin_throttled('SomeoneElse', self::NOW + 1));
        self::assertNull(resurrection_signin_throttled('SprayTarget', self::NOW - 49 + 3600), 'the window is an hour');
        // The lowest bound is 20, so nobody can lock a player out with a handful of failures.
        self::settings(['signin_fail_account' => 1]);
        self::assertSame(20, resurrection_signin_limit_max('account'));
    }

    public function testAThrottledAttemptsOwnRowCountsAgainstNothing(): void
    {
        self::from('203.0.113.8');
        self::failures('RowPlayer', 4);
        for ($i = 1; $i <= 30; $i++) resurrection_signin_record(RESURRECTION_SIGNIN_THROTTLED, 'RowPlayer', self::NOW);
        self::assertNull(resurrection_signin_throttled('RowPlayer', self::NOW + 1));
        $rows = db_query('SELECT post,ip,acctid,id FROM faillog WHERE eventid>? ORDER BY eventid DESC LIMIT 1', true, [$this->firstEvent]);
        self::assertSame(['post' => 'signin_throttled', 'ip' => '203.0.113.8', 'acctid' => '0', 'id' => resurrection_signin_login_key('RowPlayer')], $rows[0]);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', resurrection_signin_login_key('RowPlayer'));
        self::assertSame('Too many attempts. Try again in 15 minutes.', resurrection_signin_throttle_message(15));
        self::assertSame('Too many attempts. Try again in 1 minute.', resurrection_signin_throttle_message(1));
        $this->expectException(\InvalidArgumentException::class);
        resurrection_signin_record('anything else', 'RowPlayer');
    }

    public function testAnUnknownNameChecksAHashOfTheSameCost(): void
    {
        $cost = password_get_info(password_hash('x', PASSWORD_DEFAULT))['options']['cost'] ?? null;
        self::assertSame($cost, password_get_info(RESURRECTION_SIGNIN_DUMMY_HASH)['options']['cost'] ?? 'none');
        self::assertSame(password_get_info(password_hash('x', PASSWORD_DEFAULT))['algo'], password_get_info(RESURRECTION_SIGNIN_DUMMY_HASH)['algo']);
        // A name that cannot be a login (outside printable ASCII) is never looked up, and folds onto no login.
        self::assertTrue(resurrection_signin_name_usable('TimingPlayer'));
        self::assertFalse(resurrection_signin_name_usable("Timing\u{e9}Player"));
        self::assertFalse(resurrection_signin_name_usable("Timing\x01Player"));
        self::assertFalse(resurrection_signin_name_usable(''));
        $this->accounts[] = $id = resurrection_create_account('TimingPlayer', self::PASSWORD);
        self::assertSame($id, (int)resurrection_sign_in_check('TimingPlayer', self::PASSWORD)['account']['acctid']);
        self::assertFalse(resurrection_sign_in_check("Timing\u{e9}Player", self::PASSWORD)['account'], 'no folded match');
        // Wrong password and unknown name, alternated: the medians within a third of each other. Before the dummy
        // check, the unknown name returned after one SELECT, in well under a tenth of a wrong password's time.
        $times = ['wrong' => [], 'unknown' => []];
        for ($i = 0; $i < 5; $i++) {
            foreach (['wrong' => ['TimingPlayer', 'Not the password at all'], 'unknown' => ['NoSuchTimingPlayer', 'Not the password at all']] as $case => [$login, $password]) {
                $start = hrtime(true);
                $check = resurrection_sign_in_check($login, $password);
                $times[$case][] = (hrtime(true) - $start) / 1e9;
                self::assertSame(['account' => false, 'pending' => null], $check, $case);
            }
        }
        $median = static function (array $values): float { sort($values); return $values[intdiv(count($values), 2)]; };
        $wrong = $median($times['wrong']);
        $unknown = $median($times['unknown']);
        self::assertGreaterThanOrEqual(0.67, min($wrong, $unknown) / max($wrong, $unknown), sprintf('wrong %.3f s, unknown %.3f s', $wrong, $unknown));
        // A locked account's password is checked too.
        db_query('UPDATE accounts SET locked=1 WHERE acctid=?', true, [$id]);
        $start = hrtime(true);
        self::assertFalse(resurrection_sign_in_check('TimingPlayer', 'Not the password at all')['account']);
        $locked = (hrtime(true) - $start) / 1e9;
        self::assertGreaterThanOrEqual(0.67, min($wrong, $locked) / max($wrong, $locked), sprintf('wrong %.3f s, locked %.3f s', $wrong, $locked));
    }

    public function testMaintenanceDeletesRowsOlderThanThirtyDays(): void
    {
        self::from('203.0.113.10');
        $old = self::NOW - 30 * 86400 - 1;
        $kept = self::NOW - 30 * 86400 + 60;
        resurrection_signin_record(RESURRECTION_SIGNIN_FAILED, 'OldPlayer', $old);
        resurrection_signin_record(RESURRECTION_SIGNIN_THROTTLED, 'OldPlayer', $old);
        db_query('INSERT INTO faillog (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', true, [date('Y-m-d H:i:s', $old), 'setup_invalid_token', '203.0.113.10', 0, 'abcdef0123456789']);
        resurrection_signin_record(RESURRECTION_SIGNIN_FAILED, 'KeptPlayer', $kept);
        db_query('INSERT INTO faillog (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', true, [date('Y-m-d H:i:s', $kept), 'setup_invalid_token', '203.0.113.10', 0, 'abcdef0123456789']);
        self::assertSame(3, resurrection_faillog_cleanup(self::NOW));
        $left = db_query('SELECT post,id FROM faillog WHERE eventid>? ORDER BY eventid', true, [$this->firstEvent]);
        self::assertSame([['post' => 'invalid_credentials', 'id' => resurrection_signin_login_key('KeptPlayer')], ['post' => 'setup_invalid_token', 'id' => 'abcdef0123456789']], $left);
        // The daily maintenance runs it.
        $runonce = (string)file_get_contents('lib/newday/newday_runonce.php');
        self::assertStringContainsString('require_once("lib/signin_throttle.php");', $runonce);
        self::assertStringContainsString('resurrection_faillog_cleanup();', $runonce);
    }

    public function testSetupAndAccountMailKeepTheirOwnCounts(): void
    {
        // Sign-in failures from an address never count against setup.php's limit, which still blocks at its own 10.
        self::from('203.0.113.11');
        for ($i = 1; $i <= 30; $i++) self::failures('SetupGuess' . $i, 1, self::NOW - 30 + $i);
        $key = str_repeat('c3', 8);
        self::assertFalse(resurrection_setup_blocked('upgrade', $key, '/nonexistent', self::NOW));
        for ($i = 1; $i <= 10; $i++) resurrection_setup_failure('upgrade', $i % 2 ? 'token' : 'credentials', $key, '/nonexistent', self::NOW - 10 + $i);
        self::assertTrue(resurrection_setup_blocked('upgrade', $key, '/nonexistent', self::NOW + 1));
        // And setup.php's failures never count against sign-in: the address's 20 are sign-in rows only.
        self::from('203.0.113.12');
        for ($i = 1; $i <= 10; $i++) resurrection_setup_failure('upgrade', 'credentials', str_repeat('d4', 8), '/nonexistent', self::NOW - 10 + $i);
        self::failures('SetupMixed', 4);
        self::assertNull(resurrection_signin_throttled('SetupMixed', self::NOW + 1));
        // Account mail counts in its own log: its requester limit holds at its own 10 recovery requests in an hour,
        // and the 30 sign-in failures from that address leave it where it was.
        self::from('203.0.113.11');
        self::assertNull(resurrection_account_mail_limited('recover-request', 0, '', self::NOW + 1));
        try {
            for ($i = 1; $i <= 9; $i++) resurrection_account_mail_log('recover-request', 'counted', 0, '', self::NOW - 10 + $i);
            self::assertNull(resurrection_account_mail_limited('recover-request', 0, '', self::NOW + 1));
            resurrection_account_mail_log('recover-request', 'counted', 0, '', self::NOW);
            self::assertSame('recover-requester', resurrection_account_mail_limited('recover-request', 0, '', self::NOW + 1));
            self::from('203.0.113.12');
            self::assertNull(resurrection_account_mail_limited('recover-request', 0, '', self::NOW + 1));
            // And account mail's rows never count against sign-in.
            self::from('203.0.113.11');
            self::assertSame(15, resurrection_signin_throttled('AnyName', self::NOW + 1), 'the 30 sign-in failures alone');
            self::from('203.0.113.13');
            for ($i = 1; $i <= 30; $i++) resurrection_account_mail_log('recover-request', 'counted', 0, '', self::NOW - 30 + $i);
            self::assertNull(resurrection_signin_throttled('AnyName', self::NOW + 1));
        } finally {
            db_query('DELETE FROM account_mail_log');
        }
    }
}
