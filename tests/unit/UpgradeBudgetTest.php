<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;
use Resurrection\Upgrade\StepCatalog;

/**
 * The upgrade's time budget (Phase 6 task 5a; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.3):
 * resurrection_upgrade() and resurrection_upgrade_run() take an optional budget in seconds. Past it, the upgrade stops
 * only between two steps or two module upgrades, after at least one, and reports what remains; the next run picks up
 * where it stopped. With no budget nothing changes.
 *
 * It runs on the installed database FreshInstallTest leaves (with the test-only steps in tests/fixtures/upgrade-steps/)
 * and leaves it exactly as it found it.
 */
final class UpgradeBudgetTest extends TestCase
{
    private const string ORDER = __DIR__ . '/../fixtures/upgrade-steps/order';
    private const array PENDING = ['Pending:', '- step 0001-first: Log step 0001', '- step 0002-second: Log step 0002', '- step 0010-tenth: Log step 0010'];

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_snapshot.php';
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
        $GLOBALS['REQUEST_URI'] = '';
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        self::assertSame('current', resurrection_database_state(), 'The installed test database must start current.');
    }

    /** @return array{0: list<string>, 1: array<string, mixed>} the report lines and the result */
    private static function upgrade(?float $budget): array
    {
        $lines = [];
        $result = resurrection_upgrade((string)realpath(self::ORDER), true, static function (string $line) use (&$lines): void { $lines[] = $line; }, $budget);
        $GLOBALS['settings'] = null;
        return [$lines, $result];
    }

    /** @return list<string> */
    private static function ledger(): array
    {
        return array_column(db_query('SELECT step FROM upgrade_steps ORDER BY step'), 'step');
    }

    private static function log(): ?string
    {
        return db_query("SELECT value FROM settings WHERE setting='upgrade_fixture_log'")[0]['value'] ?? null;
    }

    private static function clean(): void
    {
        foreach (['0001-first', '0002-second', '0010-tenth'] as $step) { db_query('DELETE FROM upgrade_steps WHERE step=?', true, [$step]); }
        db_query("DELETE FROM settings WHERE setting='upgrade_fixture_log'");
        $GLOBALS['settings'] = null;
    }

    public function testNoBudgetChangesNothing(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        try {
            [$lines, $result] = self::upgrade(null);
            self::assertSame([...self::PENDING, 'Applying:', '- applied and recorded step 0001-first', '- applied and recorded step 0002-second', '- applied and recorded step 0010-tenth',
                'Upgrade complete: applied 3 upgrade step(s) and 0 module upgrade(s). The database is current.'], $lines);
            self::assertSame(['steps' => ['0001-first', '0002-second', '0010-tenth'], 'modules' => [], 'state' => 'current'], $result);
            self::assertSame('0001,0002,0010', self::log());
            // A budget too large to reach is the same run.
            self::clean();
            self::assertSame([$lines, $result], self::upgrade(3600.0));
        } finally { self::clean(); }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testStopsBetweenStepsAndTheNextRunResumes(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        try {
            // A budget of 0 is spent at once, yet one step always runs: each run applies exactly one, then stops.
            [$lines, $result] = self::upgrade(0.0);
            self::assertSame([...self::PENDING, 'Applying:', '- applied and recorded step 0001-first',
                'Time budget reached: applied 1 upgrade step(s) and 0 module upgrade(s) in this run; 2 upgrade step(s) and 0 module upgrade(s) remain. Run the upgrade again to continue: recorded steps are skipped.'], $lines);
            self::assertSame(['steps' => ['0001-first'], 'modules' => [], 'state' => 'behind'], $result);
            self::assertSame(['0001-first'], self::ledger());
            self::assertSame('0001', self::log());
            self::assertSame(['0002-second', '0010-tenth'], resurrection_upgrade_state(StepCatalog::ids((string)realpath(self::ORDER)))['pending']);
            [$lines, $result] = self::upgrade(0.0);
            self::assertSame(['Pending:', '- step 0002-second: Log step 0002', '- step 0010-tenth: Log step 0010', 'Applying:', '- applied and recorded step 0002-second',
                'Time budget reached: applied 1 upgrade step(s) and 0 module upgrade(s) in this run; 1 upgrade step(s) and 0 module upgrade(s) remain. Run the upgrade again to continue: recorded steps are skipped.'], $lines);
            self::assertSame(['steps' => ['0002-second'], 'modules' => [], 'state' => 'behind'], $result);
            // The last step: nothing remains, so the run finishes.
            [$lines, $result] = self::upgrade(0.0);
            self::assertSame(['Pending:', '- step 0010-tenth: Log step 0010', 'Applying:', '- applied and recorded step 0010-tenth',
                'Upgrade complete: applied 1 upgrade step(s) and 0 module upgrade(s). The database is current.'], $lines);
            self::assertSame(['steps' => ['0010-tenth'], 'modules' => [], 'state' => 'current'], $result);
            // Each step ran once, in order.
            self::assertSame('0001,0002,0010', self::log());
            self::assertSame(['0001-first', '0002-second', '0010-tenth'], self::ledger());
        } finally { self::clean(); }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testANegativeBudgetIsRefusedBeforeAnything(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        try {
            self::upgrade(-1.0);
            self::fail('A negative budget was accepted.');
        } catch (\InvalidArgumentException $error) {
            self::assertSame('The upgrade budget must be zero or more seconds.', $error->getMessage());
        } finally { self::clean(); }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testTheRunServiceStopsBetweenModuleUpgrades(): void
    {
        // Through resurrection_upgrade_run(), as the upgrade command and the web setup page call it, in its own process:
        // two modules behind, a budget of 0, so each run upgrades one module and reports the other.
        $this->connect();
        $host = (string)getenv('RESURRECTION_TEST_DB_HOST');
        $config = (string)tempnam(sys_get_temp_dir(), 'resurrection-budget-config-');
        file_put_contents($config, LegacyConfig::render(['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
            'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '',
            'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
        chmod($config, 0600);
        $versions = array_column(db_query("SELECT modulename,version FROM modules WHERE modulename IN ('fairy','findgold') ORDER BY modulename"), 'version', 'modulename');
        self::assertSame(['fairy', 'findgold'], array_keys($versions));
        // The two rows as they are, restored whole after (the upgrade records the module's metadata as the fresh install
        // does; a test before this one may have reinstalled a module through the module manager).
        $rows = db_query("SELECT * FROM modules WHERE modulename IN ('fairy','findgold')");
        $before = resurrection_database_snapshot();
        $code = "chdir(" . var_export(dirname(__DIR__, 2), true) . "); error_reporting(E_ALL);"
            . " set_error_handler(static function (int \$s, string \$m, string \$f, int \$l): never { throw new ErrorException(\$m, 0, \$s, \$f, \$l); });"
            . " require 'src/Upgrade/UpgradeRefused.php'; require 'src/Upgrade/UpgradeStopped.php'; ob_start();"
            . " foreach (['dbwrapper_pdo','constants','datacache','settings','modules','output','sanitize','translator','installer/fresh_install','upgrade'] as \$l) require_once 'lib/' . \$l . '.php';"
            . " ob_end_clean(); \$REQUEST_URI = '';"
            . " \$result = resurrection_upgrade_run(\$argv[1], true, static function (string \$line): void { echo \$line, \"\\n\"; }, (float)\$argv[2]);"
            . " echo json_encode(\$result), \"\\n\";";
        $run = static function (string $budget) use ($code, $config): array {
            $process = proc_open([PHP_BINARY, '-r', $code, $config, $budget], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
            return [proc_close($process), (string)$out, (string)$err];
        };
        db_query("UPDATE modules SET version='0.1' WHERE modulename IN ('fairy','findgold')");
        try {
            $pending = ['Pending:', '- module fairy: upgrade from 0.1 to ' . $versions['fairy'], '- module findgold: upgrade from 0.1 to ' . $versions['findgold']];
            self::assertSame([0, implode("\n", [...$pending, 'Applying:', '- upgraded module fairy from 0.1 to ' . $versions['fairy'],
                'Time budget reached: applied 0 upgrade step(s) and 1 module upgrade(s) in this run; 0 upgrade step(s) and 1 module upgrade(s) remain. Run the upgrade again to continue: recorded steps are skipped.',
                json_encode(['steps' => [], 'modules' => ['fairy'], 'state' => 'behind'])]) . "\n", ''], $run('0'));
            self::assertSame(['fairy' => $versions['fairy'], 'findgold' => '0.1'], array_column(db_query("SELECT modulename,version FROM modules WHERE modulename IN ('fairy','findgold') ORDER BY modulename"), 'version', 'modulename'));
            self::assertSame([0, implode("\n", ['Pending:', '- module findgold: upgrade from 0.1 to ' . $versions['findgold'], 'Applying:', '- upgraded module findgold from 0.1 to ' . $versions['findgold'],
                'Upgrade complete: applied 0 upgrade step(s) and 1 module upgrade(s). The database is current.', json_encode(['steps' => [], 'modules' => ['findgold'], 'state' => 'current'])]) . "\n", ''], $run('0'));
            $GLOBALS['settings'] = null;
            self::assertSame('current', resurrection_database_state());
        } finally {
            foreach ($rows as $row) {
                db_query('UPDATE modules SET ' . implode(',', array_map(static fn (string $column): string => db_identifier($column) . '=?', array_keys($row))) . ' WHERE modulename=?', true, [...array_values($row), $row['modulename']]);
            }
            unlink($config);
        }
        self::assertSame($before, resurrection_database_snapshot());
    }
}
