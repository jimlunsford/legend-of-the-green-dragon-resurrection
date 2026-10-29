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
            $run = static function (string ...$arguments) use ($config, &$environment): array {
                $arguments = $arguments ?: [$config];
                $process = proc_open([PHP_BINARY, 'scripts/install.php', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
                $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
                return [proc_close($process), $out, $err];
            };
            $secrets = [$password, (string)getenv('RESURRECTION_TEST_DB_PASSWORD')];
            $snapshot = static function (): array {
                $state = [];
                foreach (array_column(db_query('SHOW TABLES'), 'Tables_in_resurrection_test') as $table) {
                    $state[$table] = db_query('SELECT * FROM ' . db_identifier($table));
                }
                return $state;
            };
            // Populated unrelated DB and 1.1.2 databases are preserved.
            db_query('CREATE TABLE unrelated (id INT PRIMARY KEY) ENGINE=InnoDB');
            db_query('INSERT INTO unrelated VALUES (123)');
            self::assertSame('populated', resurrection_install_state());
            [$code, $out, $err] = $run();
            self::assertSame(1, $code);
            self::assertStringContainsString('Installation refused. The database is not empty. A fresh install needs an empty database. Nothing was changed.', $err);
            self::assertSame('123', $GLOBALS['dbinfo']['connection']->query('SELECT id FROM unrelated')->fetchColumn());
            db_query('DROP TABLE unrelated'); // Only our synthetic fixture, never product cleanup.
            db_query('CREATE TABLE settings (setting VARCHAR(50) PRIMARY KEY, value TEXT) ENGINE=InnoDB');
            db_query('CREATE TABLE accounts (acctid INT PRIMARY KEY, login VARCHAR(25), password VARCHAR(255)) ENGINE=InnoDB');
            db_query('INSERT INTO settings VALUES (?,?),(?,?)', true, ['installer_version', '1.1.2 Dragonprime Edition', 'gameadminemail', 'admin@example.invalid']);
            db_query('INSERT INTO accounts VALUES (456,?,?),(457,?,?)', true, ['HistoricalFixture', 'synthetic legacy placeholder', 'HistoricalSecond', 'another placeholder']);
            self::assertSame('unsupported-1.1.2', resurrection_install_state());
            $historical = $snapshot();
            $unsupported = 'Installation refused. This database holds a Legend of the Green Dragon 1.1.2 game, which is unsupported: Resurrection does not upgrade or migrate a 1.1.2 database. The only path is a fresh install into an empty database. Nothing was changed.';
            [$code, $out, $err] = $run();
            self::assertSame(1, $code);
            self::assertSame($unsupported . "\n", $err);
            self::assertSame('', $out); // lib/all_tables.php's trailing newline is discarded (OutputBufferingTest)
            self::assertSame($historical, $snapshot());
            // The config step refuses it too, before writing anything.
            $private = sys_get_temp_dir() . '/resurrection-config-' . bin2hex(random_bytes(8));
            mkdir($private, 0700);
            $written = $private . '/dbconnect.php';
            $environment = array_replace($environment, ['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
                'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => $name, 'DB_PREFIX' => '']);
            [$code, $out, $err] = $run('--write-config', $written);
            self::assertSame(1, $code);
            self::assertSame($unsupported . "\n", $err);
            self::assertFileDoesNotExist($written);
            self::assertSame($historical, $snapshot());
            db_query('DROP TABLE accounts,settings'); // Only this test's synthetic 1.1.2 database.
            // An unknown module choice or option is refused before anything happens.
            foreach ([['--modules=all', $config], ['--force', $config], ['--allow-served-path', $config], ['--write-config']] as $arguments) {
                [$code, $out, $err] = $run(...$arguments);
                self::assertSame(1, $code, implode(' ', $arguments));
                self::assertStringStartsWith('Installation refused. ', $err);
                self::assertSame('empty', resurrection_install_state());
            }
            // A config file inside the served directory is refused unless the operator asks; nothing is written.
            $served = dirname(__DIR__, 2) . '/resurrection-served-config-' . bin2hex(random_bytes(8)) . '.php';
            try {
                [$code, $out, $err] = $run('--write-config', $served);
                self::assertSame(1, $code);
                self::assertSame("Installation refused. The configuration file would be inside the served directory. Choose a private path, or add --allow-served-path to place it there on purpose.\n", $err);
                self::assertFileDoesNotExist($served);
                self::assertSame('empty', resurrection_install_state());
            } finally { if (file_exists($served)) unlink($served); }

            // The default install writes the config from the environment and activates 1.1.2's recommended modules. A
            // step in upgrades/ while it runs is recorded as applied and never run: the fresh schema already has it.
            $step = dirname(__DIR__, 2) . '/upgrades/9998-fixture-recorded-by-a-fresh-install.php';
            file_put_contents($step, "<?php\nreturn ['description' => 'Fixture', 'apply' => static function (): void { throw new LogicException('A fresh install never runs a step.'); }];\n", LOCK_EX);
            try { [$code, $out, $err] = $run('--write-config', $written); } finally { unlink($step); }
            self::assertSame(0, $code, $err . $out);
            self::assertSame(['9998-fixture-recorded-by-a-fresh-install'], array_column(db_query('SELECT step FROM upgrade_steps'), 'step'));
            self::assertSame([], db_query("SELECT value FROM settings WHERE setting='installer_version'"));
            foreach ($secrets as $secret) self::assertStringNotContainsString($secret, $out . $err);
            self::assertSame(['tables' => count(resurrection_fresh_schema()) + 2, 'modules_installed' => 24, 'modules_active' => 23,
                'administrator' => 1, 'state' => 'installed', 'modules' => 'recommended', 'config' => 'written'], json_decode($out, true, 512, JSON_THROW_ON_ERROR));
            self::assertSame('installed', resurrection_install_state());
            clearstatcache();
            self::assertSame(0600, fileperms($written) & 0777);
            $loaded = (static function (string $path): array {
                require $path;
                return compact('DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PREFIX', 'DB_USEDATACACHE', 'DB_DATACACHEPATH');
            })($written);
            self::assertSame(['DB_HOST' => $host, 'DB_USER' => getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => getenv('RESURRECTION_TEST_DB_PASSWORD'),
                'DB_NAME' => $name, 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => ''], $loaded);
            $recommended = resurrection_recommended_modules();
            self::assertSame(['game_fivesix'], array_values(array_diff(resurrection_bundled_modules(), $recommended)));
            $moduleRows = db_query('SELECT modulename,active FROM modules ORDER BY modulename');
            self::assertSame(resurrection_bundled_modules(), array_column($moduleRows, 'modulename'));
            foreach ($moduleRows as $row) self::assertSame(in_array($row['modulename'], $recommended, true) ? '1' : '0', $row['active'], $row['modulename']);
            // Never overwritten: a second write refuses before the database is touched, and the file is unchanged.
            $bytes = file_get_contents($written);
            [$code, $out, $err] = $run('--write-config', $written);
            self::assertSame(1, $code);
            self::assertSame("Installation refused. The configuration file already exists. It is never overwritten.\n", $err);
            self::assertSame($bytes, file_get_contents($written));
            // Asked for, the served directory passes the path check and the install then refuses the installed database.
            try {
                [$code, $out, $err] = $run('--write-config', '--allow-served-path', $served);
                self::assertSame(1, $code);
                self::assertSame("Installation refused. Installation is complete. Fresh installation is locked. Nothing was changed.\n", $err);
                self::assertFileDoesNotExist($served);
            } finally { if (file_exists($served)) unlink($served); }
            foreach ($secrets as $secret) self::assertStringNotContainsString($secret, $out . $err);
            // A failed activation inside the install's transaction leaves none of it behind: game_fivesix activates,
            // the repeated activation fails, and the rollback leaves game_fivesix inactive.
            $activation = proc_open([PHP_BINARY, '-r', implode(' ', [
                "error_reporting(E_ALL); set_error_handler(static function (int \$s, string \$m, string \$f, int \$l): never { throw new ErrorException(\$m, 0, \$s, \$f, \$l); });",
                "require \$argv[1]; foreach (['dbwrapper_pdo','constants','datacache','settings','modules','output','sanitize','translator','installer/fresh_install'] as \$lib) require 'lib/' . \$lib . '.php';",
                "\$REQUEST_URI = ''; db_connect(\$DB_HOST, \$DB_USER, \$DB_PASS); db_select_db(\$DB_NAME); \$db = \$GLOBALS['dbinfo']['connection'];",
                "\$db->beginTransaction(); try { resurrection_activate_installed_modules(['game_fivesix', 'game_fivesix']); echo 'activated'; }",
                "catch (RuntimeException \$e) { echo \$GLOBALS['fresh_install_phase'], ' ', \$e->getMessage(), ' ', db_query(\"SELECT active FROM modules WHERE modulename='game_fivesix'\")[0]['active']; }",
                "finally { \$db->rollBack(); }",
            ]), $written], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
            self::assertSame(0, proc_close($activation), $err);
            self::assertSame('activate-module:game_fivesix Bundled module activation failed. 1', trim($out));
            self::assertSame('0', db_query("SELECT active FROM modules WHERE modulename='game_fivesix'")[0]['active']);
            self::assertSame('23', db_query('SELECT COUNT(*) AS n FROM modules WHERE active=1')[0]['n']);
            // Only this test's disposable database: clear it for the install every later test starts from.
            foreach (array_column(db_query('SHOW TABLES'), 'Tables_in_resurrection_test') as $table) db_query('DROP TABLE ' . db_identifier($table));
            $GLOBALS['settings'] = null;

            // The existing-config mode with --modules=none: all 24 installed and inactive. Later tests start here.
            [$code, $out, $err] = $run('--modules=none', $config);
            self::assertSame(0, $code, $err . $out);
            self::assertStringNotContainsString($password, $out . $err);
            self::assertSame(['tables' => count(resurrection_fresh_schema()) + 2, 'modules_installed' => 24, 'modules_active' => 0,
                'administrator' => 1, 'state' => 'installed', 'modules' => 'none', 'config' => 'existing'], json_decode($out, true, 512, JSON_THROW_ON_ERROR));
            self::assertSame('installed', resurrection_install_state());
            // No step exists yet, so the install's ledger is empty and the database is current.
            self::assertSame([], db_query('SELECT step FROM upgrade_steps'));
            require_once 'lib/modules.php';
            $GLOBALS['settings'] = null;
            self::assertSame('current', resurrection_database_state());
            $result = db_query('SELECT COUNT(*) AS n FROM creatures');
            self::assertGreaterThan(100, (int)db_fetch_assoc($result)['n']);
            self::assertSame('UTF-8', getsetting('charset', ''));
            $tables = db_query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
            self::assertCount(count(resurrection_fresh_schema()) + 2, $tables);
            $moduleRows = db_query("SELECT modulename,active FROM modules ORDER BY modulename");
            self::assertSame(resurrection_bundled_modules(), array_column($moduleRows, "modulename"));
            self::assertSame(["0"], array_values(array_unique(array_column($moduleRows, "active"))));
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
            self::assertSame('15', $GLOBALS['dbinfo']['connection']->query("SELECT COUNT(*) FROM module_hooks WHERE modulename IN ('dag','drinks')")->fetchColumn());
            db_query('INSERT INTO bounty (amount,target,setter) VALUES (50,1,1)');
            $bounty = $GLOBALS['dbinfo']['connection']->query('SELECT setdate,windate FROM bounty')->fetch();
            self::assertNull($bounty['windate']);
            self::assertNotEmpty($bounty['setdate']);
            // Each install function registers its hooks under the module named by $mostrecentmodule: name it again, or
            // dag's hooks would be left registered under drinks for the tests that follow (UpgradeTest found this).
            $GLOBALS['mostrecentmodule'] = 'dag';
            self::assertTrue(dag_install_private());
            $GLOBALS['mostrecentmodule'] = 'drinks';
            self::assertTrue(drinks_install_private());
            self::assertSame('15', $GLOBALS['dbinfo']['connection']->query("SELECT COUNT(*) FROM module_hooks WHERE modulename IN ('dag','drinks')")->fetchColumn());
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
            $firstVersion = resurrection_begin_login($id, $player['password']);
            self::assertSame($firstVersion + 1, resurrection_begin_login($id, $player['password']));
            try { resurrection_change_password($id, 'wrong current password', 'Changed synthetic password'); self::fail('Wrong current password accepted.'); }
            catch (\DomainException $error) { self::assertSame('Password change rejected.', $error->getMessage()); }
            self::assertSame($firstVersion + 2, resurrection_change_password($id, "Synthetic O'Reilly \\ password", 'Changed synthetic password'));
            self::assertFalse(resurrection_authenticate('FixturePlayer', "Synthetic O'Reilly \\ password"));
            self::assertIsArray(resurrection_authenticate('FixturePlayer', 'Changed synthetic password'));

            db_query('UPDATE accounts SET locked=1 WHERE acctid=?', true, [$id]);
            self::assertFalse(resurrection_authenticate('FixturePlayer', 'Changed synthetic password'));
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
            $seedCounts = [];
            foreach (['creatures','weapons','armor','masters','titles','mounts','drinks'] as $table) {
                $counts = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier($table));
                $seedCounts[$table] = (int)db_fetch_assoc($counts)['n'];
            }
            fwrite(STDERR, 'Fresh install: ' . json_encode(['php' => PHP_VERSION, 'database' => db_get_server_version(), 'tables' => count($tables), 'seed_counts' => $seedCounts, 'password_api' => password_get_info($admin['password']), 'sql_mode' => $modes, 'admin' => 'modern hash', 'repeat' => 'locked']) . "\n");
        } finally {
            if (isset($step) && file_exists($step)) unlink($step);
            unlink($config);
            if (isset($written) && file_exists($written)) unlink($written);
            if (isset($private) && is_dir($private)) rmdir($private);
        }
    }
}
