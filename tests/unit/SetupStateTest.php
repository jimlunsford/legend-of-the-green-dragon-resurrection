<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * When the web setup page answers (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.2):
 * resurrection_setup_mode() in lib/setup/guard.php, for each state of the table. Only install (no configuration file),
 * install into an existing configuration whose database is empty, and upgrade (a completed install that is behind)
 * answer; every other state is "none", which the page answers with the empty 404. The configuration file follows S12:
 * LOGD_DBCONNECT, when set, is the only place, and a file in the game's folder is ignored.
 *
 * Out of order needs recorded steps the code does not have; tests/tooling/test_web_ops_http.py covers it with fixture
 * steps. Each case runs in its own process, as a request would. It runs on the installed database FreshInstallTest leaves
 * and puts every change back (the empty-database case saves and restores the whole database).
 */
final class SetupStateTest extends TestCase
{
    private static string $directory = '';
    private static string $config = '';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_save.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$directory !== '') {
            foreach (glob(self::$directory . '/{,private/}*', GLOB_BRACE) ?: [] as $file) { if (is_file($file)) unlink($file); }
            if (is_dir(self::$directory . '/private')) rmdir(self::$directory . '/private');
            rmdir(self::$directory);
        }
        self::$directory = self::$config = '';
    }

    private static function configText(string $host): string
    {
        return LegacyConfig::render(['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
            'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '',
            'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']);
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
        self::assertSame('current', resurrection_database_state(), 'The installed test database must start current.');
        if (self::$directory === '') {
            self::$directory = (string)tempnam(sys_get_temp_dir(), 'resurrection-setup-state-');
            unlink(self::$directory);
            mkdir(self::$directory . '/private', 0700, true);
            self::$config = self::$directory . '/private/dbconnect.php';
            file_put_contents(self::$config, self::configText($host));
            chmod(self::$config, 0600);
        }
    }

    /**
     * resurrection_setup_mode() in a new process: the game folder is the given directory, with LOGD_DBCONNECT as given
     * (unset when null).
     *
     * @return array{mode: string, config: string, located: ?string}
     */
    private static function mode(string $gameDirectory, ?string $logd): array
    {
        $script = "define('RESURRECTION_ENTRY', true); chdir(" . var_export(dirname(__DIR__, 2), true) . "); ob_start();"
            . " require 'lib/names.php'; foreach (['constants','datacache','settings','modules','output','sanitize','translator','installer/install_run','upgrade','setup/run'] as \$l) require_once 'lib/'.\$l.'.php';"
            . " \$mode = resurrection_setup_mode(\$argv[1]); while (ob_get_level()) ob_end_clean(); echo json_encode(\$mode);";
        $environment = getenv();
        unset($environment['LOGD_DBCONNECT']);
        if ($logd !== null) $environment['LOGD_DBCONNECT'] = $logd;
        $process = proc_open([PHP_BINARY, '-r', $script, $gameDirectory], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: string, 1: string}> case => [case, expected mode] */
    public static function states(): array
    {
        $cases = ['no configuration file' => 'install', 'configuration file, empty database' => 'install-existing', 'installed and current' => 'none',
            'behind' => 'upgrade', 'ahead' => 'none', 'populated' => 'none', 'unsupported 1.1.2' => 'none', 'damaged configuration' => 'none',
            'incomplete configuration' => 'none', 'database unreachable' => 'none', 'LOGD_DBCONNECT relative' => 'none',
            'LOGD_DBCONNECT missing, a file in the game folder' => 'install', 'LOGD_DBCONNECT installed, the game folder file unusable' => 'none',
            'LOGD_DBCONNECT behind, the game folder file unusable' => 'upgrade'];
        $data = [];
        foreach ($cases as $case => $mode) { $data[$case] = [$case, $mode]; }
        return $data;
    }

    #[DataProvider('states')]
    public function testModeForEveryState(string $case, string $expected): void
    {
        $this->connect();
        $game = self::$directory;
        $host = (string)getenv('RESURRECTION_TEST_DB_HOST');
        $local = $game . '/dbconnect.php';
        $logd = null;
        $config = $local;
        $saved = null;
        $undo = static function (): void {};
        if (is_file($local)) unlink($local);
        $installed = self::configText($host);
        switch ($case) {
            case 'no configuration file':
                break;
            case 'configuration file, empty database':
                file_put_contents($local, $installed);
                $saved = resurrection_database_save();
                foreach (db_query('SHOW TABLES') as $row) { db_query('DROP TABLE ' . db_identifier((string)array_values($row)[0])); }
                break;
            case 'installed and current':
                file_put_contents($local, $installed);
                break;
            case 'behind':
            case 'LOGD_DBCONNECT behind, the game folder file unusable':
                // A bundled module older than the code: the upgrade has work to do.
                db_query("UPDATE modules SET version='0.1' WHERE modulename='findgold'");
                $version = (string)db_query("SELECT version FROM modules WHERE modulename='findgold'")[0]['version'];
                self::assertSame('0.1', $version);
                $undo = static function (): void { db_query("UPDATE modules SET version='1.1' WHERE modulename='findgold'"); };
                if ($case === 'behind') {
                    file_put_contents($local, $installed);
                } else {
                    file_put_contents($local, "<?php \$DB_PASS = 'x' 'y';\n");
                    $logd = $config = self::$config;
                }
                break;
            case 'ahead':
                file_put_contents($local, $installed);
                db_query("INSERT INTO upgrade_steps (step,applied) VALUES ('9999-from-a-newer-version',UTC_TIMESTAMP())");
                $undo = static function (): void { db_query("DELETE FROM upgrade_steps WHERE step='9999-from-a-newer-version'"); };
                break;
            case 'populated':
                file_put_contents($local, $installed);
                db_query("DELETE FROM settings WHERE setting='resurrection_install'");
                $undo = static function (): void { db_query("INSERT INTO settings (setting,value) VALUES ('resurrection_install','complete')"); };
                break;
            case 'unsupported 1.1.2':
                file_put_contents($local, $installed);
                db_query("DELETE FROM settings WHERE setting='resurrection_install'");
                db_query("INSERT INTO settings (setting,value) VALUES ('installer_version','1.1.2 Dragonprime Edition')");
                $undo = static function (): void {
                    db_query("DELETE FROM settings WHERE setting='installer_version'");
                    db_query("INSERT INTO settings (setting,value) VALUES ('resurrection_install','complete')");
                };
                break;
            case 'damaged configuration':
                file_put_contents($local, "<?php \$DB_PASS = 'x' 'y';\n");
                break;
            case 'incomplete configuration':
                file_put_contents($local, "<?php \$DB_HOST = '127.0.0.1:1';\n");
                break;
            case 'database unreachable':
                file_put_contents($local, self::configText('127.0.0.1:1'));
                break;
            case 'LOGD_DBCONNECT relative':
                file_put_contents($local, $installed);
                $logd = 'private/dbconnect.php';
                break;
            case 'LOGD_DBCONNECT missing, a file in the game folder':
                file_put_contents($local, $installed);
                $logd = $config = $game . '/private/not-there.php';
                break;
            case 'LOGD_DBCONNECT installed, the game folder file unusable':
                file_put_contents($local, "<?php \$DB_PASS = 'x' 'y';\n");
                $logd = $config = self::$config;
                break;
            default:
                self::fail('Unknown case ' . $case);
        }
        try {
            $mode = self::mode($game, $logd);
        } finally {
            $undo();
            if ($saved !== null) resurrection_database_restore($saved);
            if (is_file($local)) unlink($local);
        }
        self::assertSame($expected, $mode['mode'], $case);
        self::assertSame($logd, $mode['located'], $case);
        if ($expected !== 'none') self::assertSame($config, $mode['config'], $case);
        self::assertSame('current', resurrection_database_state(), $case . ': the database was put back');
    }
}
