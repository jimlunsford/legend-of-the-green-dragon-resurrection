<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The web setup page's token file (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.3, and
 * decision 5's amendment, T23): lib/setup/token.php. The file is read with a bounded read and never included; its
 * format, size, age and single use are checked; the typed value is compared with hash_equals().
 */
final class SetupTokenTest extends TestCase
{
    private const string GUARD = '<?php http_response_code(404); exit; ?>';
    private const string VALUE = 'rzsetup-abcdefghijklmnopqrstuvwxyz234567';
    private string $directory = '';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        require_once 'lib/setup/token.php';
    }

    protected function setUp(): void
    {
        $this->directory = (string)tempnam(sys_get_temp_dir(), 'resurrection-setup-token-');
        unlink($this->directory);
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) || is_link($file)) unlink($file);
        }
        rmdir($this->directory);
    }

    /** Write the token file with the given bytes and modification time; returns its path. */
    private function file(string $bytes, int $age = 0): string
    {
        $path = $this->directory . '/resurrection-setup.php';
        file_put_contents($path, $bytes);
        touch($path, time() - $age);
        return $path;
    }

    /** @return array{status: string, key: ?string, token: ?string, upstream: bool} */
    private function read(string $bytes, int $age = 0, array $used = []): array
    {
        return resurrection_setup_token_read($this->file($bytes, $age), time(), $used);
    }

    public function testAcceptsTheExactTwoLineFile(): void
    {
        foreach (['newline' => self::GUARD . "\n" . self::VALUE . "\n", 'no final newline' => self::GUARD . "\n" . self::VALUE,
                  'CRLF' => self::GUARD . "\r\n" . self::VALUE . "\r\n"] as $label => $bytes) {
            $read = $this->read($bytes);
            self::assertSame('ok', $read['status'], $label);
            self::assertSame(self::VALUE, $read['token'], $label);
            self::assertFalse($read['upstream'], $label);
            self::assertSame(substr(hash('sha256', $bytes), 0, 16), $read['key'], $label);
        }
        self::assertSame('missing', resurrection_setup_token_read($this->directory . '/none.php', time(), [])['status']);
        self::assertSame(['status' => 'missing', 'key' => null, 'token' => null, 'upstream' => false],
            resurrection_setup_token_read($this->directory . '/none.php', time(), []));
    }

    public function testRefusesAFileWithoutTheGuardLine(): void
    {
        // T9: the first line is what makes a server that runs PHP answer an empty 404 for the file.
        foreach ([self::VALUE . "\n", "\n" . self::GUARD . "\n" . self::VALUE . "\n", '<?php exit; ?>' . "\n" . self::VALUE . "\n",
                  ' ' . self::GUARD . "\n" . self::VALUE . "\n", self::VALUE . "\n" . self::GUARD . "\n", ''] as $bytes) {
            $read = $this->read($bytes);
            self::assertSame('guard', $read['status'], var_export($bytes, true));
            self::assertNull($read['token']);
        }
    }

    public function testRefusesShortLongOrForeignCharacters(): void
    {
        $bodies = ['rzsetup-abcdefghijklmnopqrstuvwxyz23456', 'rzsetup-abcdefghijklmnopqrstuvwxyz2345677', 'rzsetup-Abcdefghijklmnopqrstuvwxyz234567',
                   'rzsetup-abcdefghijklmnopqrstuvwxyz234501', 'rzsetup-abcdefghijklmnopqrstuvwxyz234589', 'rzsetup_abcdefghijklmnopqrstuvwxyz234567',
                   'abcdefghijklmnopqrstuvwxyz234567', ' ' . self::VALUE, self::VALUE . ' ', 'rzsetup-abcdefghijklmnop-qrstuvwxyz23456', ''];
        foreach ($bodies as $line) {
            self::assertSame('format', $this->read(self::GUARD . "\n" . $line . "\n")['status'], $line);
            self::assertFalse(resurrection_setup_token_well_formed($line), $line);
        }
        // A second trailing newline is a third (empty) line.
        self::assertSame('format', $this->read(self::GUARD . "\n" . self::VALUE . "\n\n")['status']);
        self::assertSame('format', $this->read(self::GUARD . "\n")['status']);
        self::assertTrue(resurrection_setup_token_well_formed(self::VALUE));
    }

    public function testRefusesLowDiversityValues(): void
    {
        $eleven = 'rzsetup-' . substr(str_repeat('abcdefghijk', 3), 0, 32);
        $twelve = 'rzsetup-' . substr(str_repeat('abcdefghijkl', 3), 0, 32);
        self::assertSame(11, count(array_unique(str_split(substr($eleven, 8)))));
        self::assertSame('weak', $this->read(self::GUARD . "\n" . $eleven . "\n")['status']);
        self::assertFalse(resurrection_setup_token_well_formed($eleven));
        self::assertSame('weak', $this->read(self::GUARD . "\n" . 'rzsetup-' . str_repeat('a', 32) . "\n")['status']);
        self::assertSame('ok', $this->read(self::GUARD . "\n" . $twelve . "\n")['status']);
        self::assertTrue(resurrection_setup_token_well_formed($twelve));
    }

    public function testRefusesOversizeFiles(): void
    {
        $file = self::GUARD . "\n" . self::VALUE . "\n";
        self::assertSame('oversize', $this->read($file . str_repeat('x', 129 - strlen($file)))['status']);
        self::assertSame('oversize', $this->read(str_repeat('x', 1 << 20))['status']);
        // At the cap, a file is read and checked (not refused for its size).
        self::assertSame('format', $this->read($file . str_repeat('x', 128 - strlen($file)))['status']);
    }

    public function testRefusesOldAndFutureModificationTimes(): void
    {
        $file = self::GUARD . "\n" . self::VALUE . "\n";
        self::assertSame('ok', $this->read($file, 3590)['status']);
        self::assertSame('old', $this->read($file, 3610)['status']);
        self::assertSame('ok', $this->read($file, -290)['status']);
        self::assertSame('ahead', $this->read($file, -310)['status']);
    }

    public function testRefusesARecordedToken(): void
    {
        $file = self::GUARD . "\n" . self::VALUE . "\n";
        $key = substr(hash('sha256', self::VALUE), 0, 24);
        self::assertSame($key, resurrection_setup_used_key(resurrection_setup_token_hash(self::VALUE)));
        self::assertSame('used', $this->read($file, 0, [substr(hash('sha256', 'other'), 0, 24), $key])['status']);
        self::assertSame('ok', $this->read($file, 0, [substr(hash('sha256', 'other'), 0, 24)])['status']);
        // A full hash in the list is not the stored form, and never matches.
        self::assertSame('ok', $this->read($file, 0, [hash('sha256', self::VALUE)])['status']);
        self::assertSame(hash('sha256', self::VALUE), resurrection_setup_token_hash(self::VALUE));
    }

    public function testRecordsTheLastTenUsedTokens(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        self::assertSame([], db_query("SELECT value FROM settings WHERE setting='setup_tokens_used'"));
        try {
            self::assertSame([], resurrection_setup_used_tokens());
            $hashes = array_map(static fn (int $n): string => hash('sha256', 'token ' . $n), range(1, 12));
            $keys = array_map(static fn (string $hash): string => substr($hash, 0, 24), $hashes);
            foreach ($hashes as $hash) { resurrection_setup_record_used($hash); }
            self::assertSame(array_slice($keys, -10), resurrection_setup_used_tokens());
            // Ten keys fit the settings value (255 characters).
            $stored = (string)db_query("SELECT value FROM settings WHERE setting='setup_tokens_used'")[0]['value'];
            self::assertSame(implode(' ', array_slice($keys, -10)), $stored);
            self::assertSame(249, strlen($stored));
            // Recorded again: moved to the end, never twice.
            resurrection_setup_record_used($hashes[5]);
            $list = resurrection_setup_used_tokens();
            self::assertCount(10, $list);
            self::assertSame($keys[5], end($list));
            self::assertSame(1, count(array_keys($list, $keys[5], true)));
        } finally {
            db_query("DELETE FROM settings WHERE setting='setup_tokens_used'");
        }
    }

    public function testGeneratedValueCarries160Bits(): void
    {
        // RFC 4648's base32 test vectors, in lower case without padding.
        self::assertSame('my', resurrection_setup_base32('f'));
        self::assertSame('mzxw6ytboi', resurrection_setup_base32('foobar'));
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $value = resurrection_setup_token_new();
            self::assertMatchesRegularExpression('/\Arzsetup-[a-z2-7]{32}\z/', $value);
            self::assertTrue(resurrection_setup_token_well_formed($value));
            $seen[$value] = true;
        }
        self::assertCount(200, $seen);
        // 32 characters of 5 bits from 20 random bytes.
        self::assertStringContainsString('random_bytes(20)', (string)file_get_contents('lib/setup/token.php'));
    }

    public function testLocationFollowsLogdDbconnectAndIgnoresTheGameDirectory(): void
    {
        self::assertSame('/srv/private/game', resurrection_setup_token_directory('/srv/private/game/dbconnect.php', '/var/www/game'));
        self::assertSame('/var/www/game', resurrection_setup_token_directory(null, '/var/www/game'));
        // The page reads one place only: the file in the game's folder is not looked at when LOGD_DBCONNECT is set.
        $run = (string)file_get_contents('lib/setup/run.php');
        self::assertSame(1, substr_count($run, 'resurrection_setup_token_directory('));
        self::assertSame(1, substr_count($run, "\$tokenPath = \$directory . '/' . RESURRECTION_SETUP_TOKEN_FILE;"));
    }

    public function testComparisonUsesHashEquals(): void
    {
        $source = (string)file_get_contents('lib/setup/token.php');
        preg_match('/function resurrection_setup_token_matches\(.*?\n}/s', $source, $function);
        self::assertStringContainsString('hash_equals($fileToken, $typed)', $function[0]);
        self::assertStringNotContainsString('===', $function[0]);
        self::assertStringNotContainsString('==', str_replace('hash_equals', '', $function[0]));
        self::assertTrue(resurrection_setup_token_matches(self::VALUE, self::VALUE));
        self::assertTrue(resurrection_setup_token_matches(self::VALUE, ' ' . self::VALUE . "\r\n"));
        self::assertFalse(resurrection_setup_token_matches(self::VALUE, 'rzsetup-abcdefghijklmnopqrstuvwxyz234566'));
        self::assertFalse(resurrection_setup_token_matches(self::VALUE, [self::VALUE]));
        self::assertFalse(resurrection_setup_token_matches(self::VALUE, null));
        self::assertFalse(resurrection_setup_token_matches(self::VALUE, ''));
        $weak = 'rzsetup-' . str_repeat('ab', 16);
        self::assertFalse(resurrection_setup_token_matches($weak, $weak));
    }

    public function testAcceptsTheOptionalThirdLineExactly(): void
    {
        foreach ([self::GUARD . "\n" . self::VALUE . "\nhttps-terminated-upstream\n", self::GUARD . "\n" . self::VALUE . "\nhttps-terminated-upstream",
                  self::GUARD . "\r\n" . self::VALUE . "\r\nhttps-terminated-upstream\r\n"] as $bytes) {
            $read = $this->read($bytes);
            self::assertSame('ok', $read['status']);
            self::assertTrue($read['upstream']);
            self::assertSame(self::VALUE, $read['token']);
        }
    }

    public function testRefusesAnyOtherThirdLine(): void
    {
        foreach (['https', 'HTTPS-TERMINATED-UPSTREAM', ' https-terminated-upstream', 'https-terminated-upstream ', 'https-terminated',
                  "https-terminated-upstream\na", "https-terminated-upstream\n", 'x'] as $line) {
            self::assertSame('format', $this->read(self::GUARD . "\n" . self::VALUE . "\n" . $line . "\n")['status'], var_export($line, true));
        }
    }

    public function testCapHoldsWithTheThirdLine(): void
    {
        $file = self::GUARD . "\n" . self::VALUE . "\nhttps-terminated-upstream\n";
        self::assertLessThanOrEqual(128, strlen($file));
        self::assertSame('ok', $this->read($file)['status']);
        self::assertSame('oversize', $this->read($file . str_repeat(' ', 129 - strlen($file)))['status']);
    }

    public function testRefusesALinkAndIsNeverIncluded(): void
    {
        $target = $this->directory . '/elsewhere.php';
        file_put_contents($target, self::GUARD . "\n" . self::VALUE . "\n");
        symlink($target, $this->directory . '/resurrection-setup.php');
        self::assertSame('unreadable', resurrection_setup_token_read($this->directory . '/resurrection-setup.php', time(), [])['status']);
        // Read as bytes, never run: no include or require of the token file anywhere in the page's code.
        foreach (array_merge(glob('lib/setup/*.php') ?: [], ['setup.php']) as $file) {
            self::assertDoesNotMatchRegularExpression('/(include|require)(_once)?\s*\(?\s*\$(path|tokenPath|file)/', (string)file_get_contents($file), $file);
        }
    }
}
