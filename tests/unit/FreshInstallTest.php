<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;
use Resurrection\Security\Passwords;

require_once __DIR__ . '/../../lib/dbwrapper_pdo.php';
require_once __DIR__ . '/../../lib/constants.php';
require_once __DIR__ . '/../../lib/datacache.php';
require_once __DIR__ . '/../../lib/settings.php';
require_once __DIR__ . '/../../lib/installer/fresh_install.php';

final class FreshInstallTest extends TestCase
{
    public function testRealInstallationAndAccountBoundary(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        $name = getenv('RESURRECTION_TEST_DB_NAME');
        // This test intentionally refuses to operate against an arbitrary database.
        self::assertSame('resurrection_test', $name);
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        self::assertTrue(db_connect($host, getenv('RESURRECTION_TEST_DB_USER'), getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db($name));
        self::assertSame('empty', resurrection_install_state());
        $modes = $GLOBALS['dbinfo']['connection']->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        self::assertStringContainsString('STRICT_TRANS_TABLES', $modes);
        self::assertStringContainsString('NO_ZERO_DATE', $modes);
        self::assertStringContainsString('NO_ZERO_IN_DATE', $modes);
        $config = tempnam(sys_get_temp_dir(), 'resurrection-config-');
        file_put_contents($config, LegacyConfig::render([
            'DB_HOST' => $host, 'DB_USER' => getenv('RESURRECTION_TEST_DB_USER'),
            'DB_PASS' => getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => $name,
            'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '',
        ]));
        chmod($config, 0600);
        $password = 'Synthetic administrator password';
        $environment = getenv();
        $environment['RESURRECTION_ADMIN_LOGIN'] = 'FixtureAdmin';
        $environment['RESURRECTION_ADMIN_PASSWORD'] = $password;
        try {
            $run = static function () use ($config, $environment): array {
                $process = proc_open([PHP_BINARY, 'scripts/install.php', $config], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
                $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
                return [proc_close($process), $out, $err];
            };
            // Populated unrelated DB and legacy-upgrade candidates are preserved.
            db_query('CREATE TABLE unrelated (id INT PRIMARY KEY) ENGINE=InnoDB');
            db_query('INSERT INTO unrelated VALUES (123)');
            self::assertSame('populated', resurrection_install_state());
            [$code, $out, $err] = $run();
            self::assertSame(1, $code);
            self::assertSame('123', $GLOBALS['dbinfo']['connection']->query('SELECT id FROM unrelated')->fetchColumn());
            db_query('DROP TABLE unrelated'); // Only our synthetic fixture, never product cleanup.
            [$code, $out, $err] = $run();
            self::assertSame(0, $code, $err . $out);
            self::assertStringNotContainsString($password, $out . $err);
            self::assertSame('installed', resurrection_install_state());
            $result = db_query('SELECT COUNT(*) AS n FROM creatures');
            self::assertGreaterThan(100, (int)db_fetch_assoc($result)['n']);
            self::assertSame('UTF-8', getsetting('charset', ''));
            $tables = db_query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
            self::assertCount(count(resurrection_fresh_schema()), $tables);
            foreach ($tables as $table) {
                self::assertSame('InnoDB', $table['ENGINE']);
                self::assertStringStartsWith('utf8mb4_', $table['TABLE_COLLATION']);
            }
            // Actual bundled installer functions and hook registration, not copied DDL.
            require_once 'lib/output.php';
            require_once 'lib/modules.php';
            require_once 'modules/dag/install.php';
            require_once 'modules/drinks/install.php';
            $GLOBALS['session'] = ['user' => ['superuser' => 0]];
            $GLOBALS['mostrecentmodule'] = 'dag';
            self::assertTrue(dag_install_private());
            $GLOBALS['mostrecentmodule'] = 'drinks';
            self::assertTrue(drinks_install_private());
            self::assertSame('3', $GLOBALS['dbinfo']['connection']->query('SELECT COUNT(*) FROM drinks')->fetchColumn());
            self::assertSame('15', $GLOBALS['dbinfo']['connection']->query('SELECT COUNT(*) FROM module_hooks')->fetchColumn());
            db_query('INSERT INTO bounty (amount,target,setter) VALUES (50,1,1)');
            $bounty = $GLOBALS['dbinfo']['connection']->query('SELECT setdate,windate FROM bounty')->fetch();
            self::assertNull($bounty['windate']);
            self::assertNotEmpty($bounty['setdate']);
            self::assertTrue(dag_install_private());
            self::assertTrue(drinks_install_private());
            self::assertSame('1', $GLOBALS['dbinfo']['connection']->query('SELECT COUNT(*) FROM bounty')->fetchColumn());
            $admin = resurrection_authenticate('FixtureAdmin', $password);
            self::assertIsArray($admin);
            self::assertGreaterThan(0, (int)$admin['superuser']);
            self::assertFalse(resurrection_authenticate('FixtureAdmin', $admin['password']));
            self::assertFalse(resurrection_authenticate('FixtureAdmin', 'wrong password'));
            self::assertFalse(resurrection_authenticate("FixtureAdmin' OR 1=1 --", $password));
            $id = resurrection_create_account('FixturePlayer', "Synthetic O'Reilly \\ password", 'fixture@example.invalid');
            self::assertGreaterThan(0, $id);
            $player = resurrection_authenticate('FixturePlayer', "Synthetic O'Reilly \\ password");
            self::assertIsArray($player);
            self::assertSame('0', $player['superuser']);
            self::assertSame(LOCATION_FIELDS, $player['location']);
            self::assertSame('1', $player['level']);
            self::assertTrue(Passwords::verify("Synthetic O'Reilly \\ password", $player['password']));
            self::assertFalse(resurrection_authenticate('FixturePlayer', $player['password']));
            self::assertFalse(resurrection_authenticate('MissingPlayer', 'synthetic password'));
            db_query('UPDATE accounts SET locked=1 WHERE acctid=?', true, [$id]);
            self::assertFalse(resurrection_authenticate('FixturePlayer', "Synthetic O'Reilly \\ password"));
            try {
                resurrection_create_account('fixtureplayer', 'Another synthetic password');
                self::fail('Case-insensitive duplicate login was allowed.');
            } catch (\RuntimeException $error) {
                self::assertSame('Database operation failed.', $error->getMessage());
            }
            [$code, $out, $err] = $run();
            self::assertSame(1, $code);
            self::assertSame('2', $GLOBALS['dbinfo']['connection']->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
            self::assertSame('installed', resurrection_install_state());
            fwrite(STDERR, 'Fresh install: ' . json_encode(['php' => PHP_VERSION, 'database' => db_get_server_version(), 'tables' => count($tables), 'sql_mode' => $modes, 'admin' => 'modern hash', 'repeat' => 'locked']) . "\n");
        } finally { unlink($config); }
    }
}
