<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The install service (Phase 6 task 5a): resurrection_install_run() in lib/installer/install_run.php is the one install
 * path, and scripts/install.php a front end over it. Installed through the service and through the command, the
 * database is the same (the snapshot that scripts/release-upgrade-check.py compares, normalized), the result is the
 * same, and both refuse an installed database with the same reason.
 *
 * It runs on the installed database FreshInstallTest leaves and puts it back exactly: every table's definition and
 * rows are saved first and restored after, and a whole-database snapshot before and after must match.
 */
final class InstallRunParityTest extends TestCase
{
    private const string PASSWORD = 'Synthetic parity administrator password';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_snapshot.php';
        require_once __DIR__ . '/../support/database_save.php';
    }

    /** @return array{0: int, 1: string, 2: string} */
    private static function process(array $command, array $environment): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), (string)$out, (string)$err];
    }

    /** The service, called as the command calls it, with the command's libraries; prints its result or its refusal. */
    private static function service(string $config, array $environment): array
    {
        $code = "chdir(" . var_export(dirname(__DIR__, 2), true) . "); error_reporting(E_ALL);"
            . " set_error_handler(static function (int \$s, string \$m, string \$f, int \$l): never { throw new ErrorException(\$m, 0, \$s, \$f, \$l); });"
            . " require 'src/Configuration/ConfigFile.php'; require 'src/Configuration/ConfigLocation.php';"
            . " foreach (['dbwrapper_pdo','constants','datacache','settings','modules','output','sanitize','translator'] as \$l) require 'lib/' . \$l . '.php';"
            . " ob_start(); require 'lib/installer/install_run.php'; ob_end_clean(); \$REQUEST_URI = '';"
            . " try { echo json_encode(resurrection_install_run('none', \$argv[1], false, false, getenv('RESURRECTION_ADMIN_LOGIN') ?: null,"
            . " getenv('RESURRECTION_ADMIN_PASSWORD') ?: null, '', getenv(), getcwd())); }"
            . " catch (\\Resurrection\\Installation\\InstallRefused \$e) { echo json_encode(['refused' => \$e->getMessage()]); }"
            . " catch (Throwable \$e) { echo json_encode(['failed' => get_class(\$e), 'message' => \$e->getMessage()]); }";
        return self::process([PHP_BINARY, '-r', $code, $config], $environment);
    }

    /** @return list<string> */
    private static function tables(): array
    {
        return array_column(db_query('SHOW TABLES'), 'Tables_in_resurrection_test');
    }

    private static function dropAll(): void
    {
        foreach (self::tables() as $table) { db_query('DROP TABLE ' . db_identifier($table)); }
        $GLOBALS['settings'] = null;
    }

    public function testTheServiceAndTheCommandInstallTheSameGame(): void
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
        self::assertSame('installed', resurrection_install_state());
        $config = (string)tempnam(sys_get_temp_dir(), 'resurrection-parity-config-');
        file_put_contents($config, LegacyConfig::render(['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
            'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '',
            'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
        chmod($config, 0600);
        $environment = getenv();
        unset($environment['LOGD_DBCONNECT']);
        $environment['RESURRECTION_ADMIN_LOGIN'] = 'ParityAdmin';
        $environment['RESURRECTION_ADMIN_PASSWORD'] = self::PASSWORD;
        $secrets = [self::PASSWORD, (string)getenv('RESURRECTION_TEST_DB_PASSWORD')];

        // Saved exactly: each table's CREATE TABLE (with its AUTO_INCREMENT) and its rows.
        $before = resurrection_database_snapshot();
        $saved = resurrection_database_save();
        try {
            // Both refuse the installed database, with the same reason, and change nothing.
            [$code, $out, $err] = self::service($config, $environment);
            self::assertSame([0, ''], [$code, $err], $out);
            self::assertSame(['refused' => 'Installation is complete. Fresh installation is locked. Nothing was changed.'], json_decode($out, true));
            self::assertSame([1, '', "Installation refused. Installation is complete. Fresh installation is locked. Nothing was changed.\n"],
                self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', $config], $environment));
            self::assertSame($before, resurrection_database_snapshot());

            // Without the administrator's credentials both refuse before anything happens.
            self::dropAll();
            $missing = $environment;
            unset($missing['RESURRECTION_ADMIN_PASSWORD']);
            [$code, $out, $err] = self::service($config, $missing);
            self::assertSame([0, ''], [$code, $err], $out);
            self::assertSame(['failed' => 'RuntimeException', 'message' => 'Administrator credentials must be provided through the environment.'], json_decode($out, true));
            self::assertSame([1, '', "Installation refused or failed (preflight). Existing data is never automatically cleared. Check the target and installation documentation.\n"],
                self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', $config], $missing));
            self::assertSame([], self::tables());

            // The service into the empty database.
            [$code, $out, $err] = self::service($config, $environment);
            self::assertSame([0, ''], [$code, $err], $out);
            foreach ($secrets as $secret) { self::assertStringNotContainsString($secret, $out); }
            $service = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            $GLOBALS['settings'] = null;
            self::assertSame('installed', resurrection_install_state());
            $byService = resurrection_database_snapshot(true);
            self::dropAll();

            // The command into the empty database: the same result (with the command's two keys) and the same game.
            [$code, $out, $err] = self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', $config], $environment);
            self::assertSame([0, ''], [$code, $err], $out);
            foreach ($secrets as $secret) { self::assertStringNotContainsString($secret, $out); }
            self::assertSame(['tables' => count(resurrection_fresh_schema()) + 2, 'modules_installed' => 24, 'modules_active' => 0,
                'administrator' => 1, 'state' => 'installed'], $service);
            self::assertSame($service + ['modules' => 'none', 'config' => 'existing'], json_decode($out, true, 512, JSON_THROW_ON_ERROR));
            $GLOBALS['settings'] = null;
            $byCommand = resurrection_database_snapshot(true);
            self::assertSame(array_keys($byService['tables']), array_keys($byCommand['tables']));
            foreach ($byService['tables'] as $table => $definition) {
                self::assertSame($definition, $byCommand['tables'][$table], $table);
                self::assertSame($byService['rows'][$table], $byCommand['rows'][$table], $table);
            }
            self::assertSame($byService, $byCommand);
        } finally {
            try { resurrection_database_restore($saved); } finally { unlink($config); }
        }
        self::assertSame($before, resurrection_database_snapshot(), 'The database FreshInstallTest left is restored exactly.');
        self::assertSame('current', resurrection_database_state());
    }
}
