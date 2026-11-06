<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The maintenance service (Phase 6 task 5a): resurrection_maintenance_run() in lib/maintenance.php, the one code path
 * behind cron.php and webcron.php. Each result (complete, already-complete, locked, failed) is returned and recorded in
 * maintenance_last_run with its path; it refuses a database that is behind (an upgrade pending) or an install that never
 * completed, an unknown path, and records nothing for a refusal.
 *
 * Each run is its own process that loads common.php as cron.php does and calls the service (the game's bootstrap is
 * global code). It runs on the installed database FreshInstallTest leaves and puts it back exactly.
 */
final class MaintenanceRunTest extends TestCase
{
    private static ?string $config = null;
    private static ?string $script = null;

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade', 'maintenance'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_snapshot.php';
        require_once __DIR__ . '/../support/database_save.php';
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$config, self::$script] as $file) { if ($file !== null && file_exists($file)) { unlink($file); } }
        self::$config = self::$script = null;
    }

    private function connect(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        self::assertSame('current', resurrection_database_state());
        if (self::$config === null) {
            self::$config = (string)tempnam(sys_get_temp_dir(), 'resurrection-maintenance-config-');
            file_put_contents(self::$config, LegacyConfig::render(['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
                'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '',
                'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
            chmod(self::$config, 0600);
            // cron.php's bootstrap, then the service with the given path. A fixture change (the second argument) is made
            // after the game has started, so the service's own checks are the ones that answer.
            self::$script = (string)tempnam(sys_get_temp_dir(), 'resurrection-maintenance-run-');
            file_put_contents(self::$script, "<?php\ndefine('RESURRECTION_ENTRY', true);\nchdir(" . var_export(dirname(__DIR__, 2), true) . ");\n"
                . "\$_SERVER += ['SCRIPT_NAME' => 'cron.php', 'REQUEST_URI' => 'cron.php', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '', 'REQUEST_METHOD' => ''];\n"
                . "define('ALLOW_ANONYMOUS', true);\nob_start();\n\$moved = false;\n"
                . "try {\n    require 'common.php';\n    require_once 'lib/maintenance.php';\n"
                . "    if (\$argv[2] === 'behind') db_query(\"UPDATE modules SET version='0.1' WHERE modulename='findgold'\");\n"
                . "    if (\$argv[2] === 'incomplete') db_query(\"DELETE FROM settings WHERE setting='resurrection_install'\");\n"
                . "    if (\$argv[2] === 'broken') { db_query('RENAME TABLE bans TO maintenance_fixture_bans'); \$moved = true; }\n"
                . "    \$result = resurrection_maintenance_run(\$argv[1]);\n    while (ob_get_level()) ob_end_clean();\n    echo json_encode(\$result);\n"
                . "} catch (Throwable \$error) {\n    while (ob_get_level()) ob_end_clean();\n    echo json_encode(['error' => get_class(\$error), 'message' => \$error->getMessage()]);\n"
                . "} finally {\n    if (\$moved) db_query('RENAME TABLE maintenance_fixture_bans TO bans');\n}\n");
        }
    }

    /** @return array<string, string> the service's result, or the class and message of what it threw */
    private static function maintenance(string $path, string $fixture = 'none'): array
    {
        $environment = getenv();
        $environment['LOGD_DBCONNECT'] = (string)self::$config;
        $process = proc_open([PHP_BINARY, (string)self::$script, $path, $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string)$err);
        self::assertStringNotContainsString((string)getenv('RESURRECTION_TEST_DB_PASSWORD'), $out . $err);
        $GLOBALS['settings'] = null;
        return json_decode((string)$out, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function setting(string $name): ?string
    {
        return db_query('SELECT value FROM settings WHERE setting=?', true, [$name])[0]['value'] ?? null;
    }

    /** @return array{path: string, result: string}|null maintenance_last_run without its time, which is checked apart */
    private static function lastRun(): ?array
    {
        $stored = self::setting('maintenance_last_run');
        if ($stored === null) { return null; }
        $record = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['time', 'path', 'result'], array_keys($record));
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $record['time']);
        self::assertLessThan(120, abs(strtotime($record['time'] . ' UTC') - time()));
        return ['path' => $record['path'], 'result' => $record['result']];
    }

    public function testEachResultIsReturnedAndRecordedWithItsPath(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        $saved = resurrection_database_save();
        try {
            db_query("DELETE FROM settings WHERE setting IN ('maintenance_day','maintenance_last_run')");
            $complete = self::maintenance('web');
            self::assertSame('complete', $complete['maintenance']);
            self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d$/', $complete['game_day']);
            self::assertSame($complete['game_day'], self::setting('maintenance_day'));
            self::assertSame(['path' => 'web', 'result' => 'complete'], self::lastRun());

            // The same game day again: nothing runs, from either path.
            self::assertSame(['maintenance' => 'already-complete', 'game_day' => $complete['game_day']], self::maintenance('cli'));
            self::assertSame(['path' => 'cli', 'result' => 'already-complete'], self::lastRun());

            // Another holder of the maintenance lock (an upgrade, or another run): no wait, nothing runs.
            $lock = resurrection_maintenance_lock_name();
            self::assertSame('1', (string)db_query('SELECT GET_LOCK(?,0) AS acquired', true, [$lock])[0]['acquired']);
            try {
                db_query("UPDATE settings SET value='' WHERE setting='maintenance_day'");
                self::assertSame(['maintenance' => 'locked', 'game_day' => ''], self::maintenance('web'));
                self::assertSame(['path' => 'web', 'result' => 'locked'], self::lastRun());
                self::assertSame('', self::setting('maintenance_day'));
            } finally { db_query('SELECT RELEASE_LOCK(?)', true, [$lock]); }

            // A job that fails: thrown on, recorded as failed, the day not marked, and the lock released.
            self::assertSame(['error' => 'RuntimeException', 'message' => 'Database operation failed.'], self::maintenance('cli', 'broken'));
            self::assertSame(['path' => 'cli', 'result' => 'failed'], self::lastRun());
            self::assertSame('', self::setting('maintenance_day'));
            self::assertSame('1', (string)db_query('SELECT IS_FREE_LOCK(?) AS free', true, [$lock])[0]['free']);
            self::assertTrue(db_table_exists('bans'));
            self::assertSame(['maintenance' => 'complete', 'game_day' => $complete['game_day']], self::maintenance('cli'));
            self::assertSame(['path' => 'cli', 'result' => 'complete'], self::lastRun());
        } finally { resurrection_database_restore($saved); }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testItRefusesWhileBehindOrIncompleteAndRecordsNothing(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        $saved = resurrection_database_save();
        try {
            db_query("DELETE FROM settings WHERE setting IN ('maintenance_day','maintenance_last_run')");
            $refusals = [
                'behind' => ['error' => 'RuntimeException', 'message' => 'The game cannot run on this database.'],
                'incomplete' => ['error' => 'RuntimeException', 'message' => 'Installation incomplete.'],
            ];
            foreach ($refusals as $fixture => $refusal) {
                self::assertSame($refusal, self::maintenance('web', $fixture), $fixture);
                self::assertNull(self::setting('maintenance_last_run'), $fixture);
                self::assertSame('', (string)self::setting('maintenance_day'), $fixture);
                resurrection_database_restore($saved);
                db_query("DELETE FROM settings WHERE setting IN ('maintenance_day','maintenance_last_run')");
            }
            self::assertSame(['error' => 'InvalidArgumentException', 'message' => 'Unknown maintenance path.'], self::maintenance('ftp'));
            self::assertNull(self::setting('maintenance_last_run'));
        } finally { resurrection_database_restore($saved); }
        self::assertSame($before, resurrection_database_snapshot());
        self::assertSame(['complete', 'already-complete', 'locked', 'failed'], RESURRECTION_MAINTENANCE_RESULTS);
    }
}
