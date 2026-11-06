<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The web setup page's failed-attempt limit (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.9):
 * 10 failures in 15 minutes against one token file, then every attempt against it is refused until the window ends.
 * Install mode has no database yet and counts in a guarded file beside the token file; upgrade mode counts faillog rows
 * with fixed words. The key is the token file's own (the first 16 hex characters of its bytes' SHA-256), so a new token
 * file starts a new count.
 */
final class SetupFailureLimitTest extends TestCase
{
    private string $directory = '';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/install_run', 'upgrade', 'setup/guard'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
    }

    protected function setUp(): void
    {
        $this->directory = (string)tempnam(sys_get_temp_dir(), 'resurrection-setup-limit-');
        unlink($this->directory);
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) { unlink($file); }
        rmdir($this->directory);
    }

    public function testCounterFileIsGuardedAndKeyedByTokenFile(): void
    {
        $now = 1_800_000_000;
        $key = str_repeat('a1', 8);
        for ($i = 1; $i <= 10; $i++) {
            self::assertFalse(resurrection_setup_blocked('install', $key, $this->directory, $now), (string)$i);
            resurrection_setup_failure('install', 'token', $key, $this->directory, $now + $i);
        }
        self::assertTrue(resurrection_setup_blocked('install', $key, $this->directory, $now + 11));
        $path = $this->directory . '/resurrection-setup-failures.php';
        self::assertSame("<?php http_response_code(404); exit; ?>\n" . $key . ' ' . ($now + 1) . " 10\n", file_get_contents($path));
        self::assertSame(0600, fileperms($path) & 0777);
        // Another token file's key is not blocked; the window ends 15 minutes after the first failure.
        self::assertFalse(resurrection_setup_blocked('install', str_repeat('b2', 8), $this->directory, $now + 11));
        self::assertTrue(resurrection_setup_blocked('install', $key, $this->directory, $now + 900));
        self::assertFalse(resurrection_setup_blocked('install', $key, $this->directory, $now + 901));
        // A file without the guard line, or damaged, counts nothing (and is started again on the next failure).
        file_put_contents($path, $key . ' ' . $now . " 10\n");
        self::assertFalse(resurrection_setup_blocked('install', $key, $this->directory, $now + 11));
        resurrection_setup_failure('install', 'token', $key, $this->directory, $now + 12);
        self::assertSame("<?php http_response_code(404); exit; ?>\n" . $key . ' ' . ($now + 12) . " 1\n", file_get_contents($path));
        // No key (the token file could not be read): nothing is counted and nothing is blocked.
        resurrection_setup_failure('install', 'token', null, $this->directory, $now + 13);
        self::assertSame("<?php http_response_code(404); exit; ?>\n" . $key . ' ' . ($now + 12) . " 1\n", file_get_contents($path));
        self::assertFalse(resurrection_setup_blocked('install', null, $this->directory, $now + 13));
    }

    public function testNewTokenFileStartsANewCount(): void
    {
        $guard = "<?php http_response_code(404); exit; ?>\n";
        $first = $this->directory . '/resurrection-setup.php';
        file_put_contents($first, $guard . "rzsetup-abcdefghijklmnopqrstuvwxyz234567\n");
        $old = resurrection_setup_token_read($first, time(), [])['key'];
        file_put_contents($first, $guard . "rzsetup-bcdefghijklmnopqrstuvwxyz234567a\n");
        $new = resurrection_setup_token_read($first, time(), [])['key'];
        self::assertMatchesRegularExpression('/\A[0-9a-f]{16}\z/', (string)$old);
        self::assertNotSame($old, $new);
        $now = 1_800_000_000;
        for ($i = 0; $i < 10; $i++) { resurrection_setup_failure('install', 'token', (string)$old, $this->directory, $now); }
        self::assertTrue(resurrection_setup_blocked('install', (string)$old, $this->directory, $now));
        self::assertFalse(resurrection_setup_blocked('install', (string)$new, $this->directory, $now));
        resurrection_setup_failure('install', 'token', (string)$new, $this->directory, $now + 1);
        self::assertSame($guard . $new . ' ' . ($now + 1) . " 1\n", file_get_contents($this->directory . '/resurrection-setup-failures.php'));
    }

    public function testFaillogCountInUpgradeMode(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        $before = (int)db_query('SELECT COALESCE(MAX(eventid),0) AS n FROM faillog')[0]['n'];
        $key = str_repeat('c3', 8);
        $other = str_repeat('d4', 8);
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $now = time();
        try {
            for ($i = 1; $i <= 10; $i++) {
                self::assertFalse(resurrection_setup_blocked('upgrade', $key, $this->directory, $now), (string)$i);
                resurrection_setup_failure('upgrade', $i % 2 ? 'token' : 'credentials', $key, $this->directory, $now);
            }
            self::assertTrue(resurrection_setup_blocked('upgrade', $key, $this->directory, $now));
            self::assertFalse(resurrection_setup_blocked('upgrade', $other, $this->directory, $now));
            self::assertFalse(resurrection_setup_blocked('upgrade', $key, $this->directory, $now + 901));
            $rows = db_query('SELECT post,ip,acctid,id FROM faillog WHERE eventid>? ORDER BY eventid', true, [$before]);
            self::assertCount(10, $rows);
            foreach ($rows as $i => $row) {
                self::assertSame(['post' => $i % 2 ? 'setup_invalid_credentials' : 'setup_invalid_token', 'ip' => '203.0.113.7', 'acctid' => '0', 'id' => $key],
                    array_map('strval', $row));
            }
            // Upgrade mode writes no counter file.
            self::assertSame([], glob($this->directory . '/*'));
        } finally {
            db_query('DELETE FROM faillog WHERE eventid>?', true, [$before]);
            if ($remote === null) unset($_SERVER['REMOTE_ADDR']); else $_SERVER['REMOTE_ADDR'] = $remote;
        }
    }
}
