<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;
use Resurrection\Upgrade\StepCatalog;
use Resurrection\Upgrade\UpgradeRefused;
use Resurrection\Upgrade\UpgradeStopped;

/**
 * The upgrade system (lib/upgrade.php, scripts/upgrade.php). The database tests run on the installed database that
 * FreshInstallTest leaves, and each leaves it exactly as it found it: a whole-database snapshot before and after.
 * Test-only steps are in tests/fixtures/upgrade-steps/ and never in upgrades/.
 */
final class UpgradeTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../fixtures/upgrade-steps';
    private const string LANGUAGES = 'en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian';
    private static ?string $config = null;

    public static function setUpBeforeClass(): void
    {
        // lib/names.php loads the database wrapper, which installs the application's error handler.
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_snapshot.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$config !== null && file_exists(self::$config)) { unlink(self::$config); }
        self::$config = null;
    }

    /** Connect to the installed test database, or skip without one. */
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
        self::assertSame('installed', resurrection_install_state());
        self::assertSame('current', resurrection_database_state(), 'The installed test database must start current.');
        if (self::$config === null) {
            self::$config = tempnam(sys_get_temp_dir(), 'resurrection-upgrade-config-');
            file_put_contents(self::$config, LegacyConfig::render(['DB_HOST' => $host, 'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'),
                'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'), 'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '',
                'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
            chmod(self::$config, 0600);
        }
    }

    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr of php scripts/upgrade.php */
    private function command(string ...$arguments): array
    {
        $process = proc_open([PHP_BINARY, 'scripts/upgrade.php', ...$arguments, (string)self::$config], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($process);
        self::assertStringNotContainsString((string)getenv('RESURRECTION_TEST_DB_PASSWORD'), $out . $err);
        return [$code, (string)$out, (string)$err];
    }

    /** @return list<string> the report lines of an upgrade run in this process */
    private function upgrade(string $directory, bool $apply): array
    {
        $lines = [];
        resurrection_upgrade($directory, $apply, static function (string $line) use (&$lines): void { $lines[] = $line; });
        $GLOBALS['settings'] = null;
        return $lines;
    }

    /** @return list<string> */
    private static function ledger(): array
    {
        return array_column(db_query('SELECT step FROM upgrade_steps ORDER BY step'), 'step');
    }

    /** @param list<string> $steps */
    private static function forget(array $steps): void
    {
        foreach ($steps as $step) { db_query('DELETE FROM upgrade_steps WHERE step=?', true, [$step]); }
    }

    /**
     * A snapshot with the ledger's times left out (a step recorded again gets a new time).
     * @return array<string, mixed>
     */
    private static function withoutLedgerTimes(): array
    {
        $snapshot = resurrection_database_snapshot();
        $snapshot['rows']['upgrade_steps'] = array_column(db_query('SELECT step FROM upgrade_steps ORDER BY step'), 'step');
        return $snapshot;
    }

    private static function directory(string $name): string
    {
        return realpath(self::FIXTURES . '/' . $name) ?: throw new \RuntimeException('Missing fixture directory.');
    }

    public function testCatalogOrdersStepsByNumberAndChecksTheirFiles(): void
    {
        self::assertSame([], StepCatalog::ids(dirname(__DIR__, 2) . '/upgrades'), 'No release exists yet, so there are no real steps.');
        self::assertSame(['0001-first', '0002-second', '0010-tenth'], StepCatalog::ids(self::directory('order')));
        $directory = sys_get_temp_dir() . '/resurrection-steps-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $write = static function (string $name, string $body = "<?php return ['description' => 'Fixture', 'apply' => static function (): void {}];\n") use ($directory): void {
            file_put_contents($directory . '/' . $name, $body);
        };
        try {
            foreach (['0010-c.php', '0002-b.php', '0001-a.php', 'README.md'] as $name) { $write($name); }
            self::assertSame(['0001-a', '0002-b', '0010-c'], StepCatalog::ids($directory));
            self::assertSame('Fixture', StepCatalog::load($directory, '0002-b')['description']);
            foreach (['notes.txt', '1-a.php', '0001_a.php', '0003-A.php', '0003-.php', '0003-a--b.php', '00003-a.php', '0003-' . str_repeat('a', 60) . '.php', '0002-again.php'] as $bad) {
                $write($bad);
                try { StepCatalog::ids($directory); self::fail('Accepted ' . $bad); }
                catch (\UnexpectedValueException $error) { self::assertStringContainsString($bad === '0002-again.php' ? 'share the number 0002' : $bad, $error->getMessage()); }
                unlink($directory . '/' . $bad);
            }
            foreach (["<?php return ['apply' => static function (): void {}];", "<?php return ['description' => '', 'apply' => static function (): void {}];",
                      "<?php return ['description' => 'x', 'apply' => 'phpinfo'];", "<?php return ['description' => 'x', 'apply' => static function (): void {}, 'extra' => 1];",
                      '<?php return null;'] as $body) {
                $write('0004-bad.php', $body);
                try { StepCatalog::load($directory, '0004-bad'); self::fail('Loaded ' . $body); }
                catch (\UnexpectedValueException $error) { self::assertSame('Upgrade step 0004-bad must return only a description and an apply function.', $error->getMessage()); }
            }
        } finally {
            array_map('unlink', glob($directory . '/*') ?: []);
            rmdir($directory);
        }
    }

    /**
     * Each bundled module file's version and the infokeys a fresh install records for it, read without a database.
     * @return array<string, array{0: string, 1: string}>
     */
    private static function moduleFiles(): array
    {
        $code = "define('DB_NODB', true); \$session = ['user' => ['superuser' => 0]]; \$REQUEST_URI = ''; ob_start();"
            . " foreach (['dbwrapper_pdo','constants','datacache','settings','modules','output','sanitize','translator','installer/bundled_modules'] as \$l) require_once 'lib/' . \$l . '.php';"
            . " \$v = []; foreach (resurrection_bundled_modules() as \$m) { require_once 'modules/' . \$m . '.php'; \$i = (\$m . '_getmoduleinfo')(); \$v[\$m] = [\$i['version'], '|' . implode('|', array_keys(\$i)) . '|']; }"
            . ' ob_end_clean(); echo json_encode($v);';
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string)$err);
        self::assertSame('', $err);
        return json_decode((string)$out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testBundledModuleVersionsMatchTheModuleFiles(): void
    {
        $files = self::moduleFiles();
        self::assertCount(24, $files);
        self::assertSame(array_map(static fn (array $file): string => $file[0], $files), resurrection_bundled_module_versions(),
            'resurrection_bundled_module_versions() must list each bundled module file\'s version.');
    }

    public function testACurrentDatabaseIsLeftUntouchedAndApplyingNeedsABackup(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        $refusal = 'Upgrade refused. Take a fresh backup of the database first, then run again with --backup-confirmed. Some upgrade changes cannot be rolled back: MySQL and MariaDB commit a table change the moment it runs, so if an upgrade stops partway, the backup is the way back. To see what would change without changing anything, use --dry-run. docs/MODERN-INSTALLATION.md, "Upgrading", says how to take and restore a backup. Nothing was changed.' . "\n";
        self::assertSame([1, '', $refusal], $this->command());
        $current = "The database is current: 0 of 0 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.\n";
        self::assertSame([0, $current, ''], $this->command('--dry-run'));
        self::assertSame([0, $current, ''], $this->command('--backup-confirmed'));
        self::assertSame([1, '', "Upgrade refused. Give --dry-run or --backup-confirmed, not both. Nothing was changed.\n"], $this->command('--dry-run', '--backup-confirmed'));
        self::assertSame([1, '', "Upgrade refused. Unknown option. Use --dry-run or --backup-confirmed (docs/MODERN-INSTALLATION.md, \"Upgrading\"). Nothing was changed.\n"], $this->command('--force'));
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testAMissingLedgerIsBehindAndTheUpgradeCreatesIt(): void
    {
        // A development database installed before the upgrade system has no ledger. With no real steps yet, the ledger
        // of the installed test database is empty, so dropping it loses nothing.
        $this->connect();
        $before = resurrection_database_snapshot();
        self::assertSame([], $before['rows']['upgrade_steps']);
        db_query('DROP TABLE upgrade_steps');
        try {
            self::assertSame('behind', resurrection_database_state());
            $missing = resurrection_database_snapshot();
            self::assertArrayNotHasKey('upgrade_steps', $missing['tables']);
            self::assertSame([0, "Pending:\n- create the upgrade ledger (the upgrade_steps table)\nDry run: nothing was changed.\n", ''], $this->command('--dry-run'));
            self::assertSame($missing, resurrection_database_snapshot());
            self::assertSame([0, "Pending:\n- create the upgrade ledger (the upgrade_steps table)\nApplying:\n- created the upgrade ledger (the upgrade_steps table)\n"
                . "Upgrade complete: applied 0 upgrade step(s) and 0 module upgrade(s). The database is current.\n", ''], $this->command('--backup-confirmed'));
        } finally {
            if (!db_table_exists('upgrade_steps')) { db_query(table_create_from_descriptor('upgrade_steps', resurrection_upgrade_ledger_descriptor())); }
        }
        // The same table as a fresh install's, and nothing else changed.
        self::assertSame($before, resurrection_database_snapshot());
        $GLOBALS['settings'] = null;
        self::assertSame('current', resurrection_database_state());
    }

    public function testAnUpgradeFromAnOlderStepMatchesTheFreshInstall(): void
    {
        // The equivalence test. The installed database is a fresh install at HEAD. With the equivalence steps as HEAD's
        // steps, that install would have recorded all three; each step makes a change the install already has. Undoing
        // them builds the database at an older step, and the upgrade must bring back exactly the fresh install.
        $this->connect();
        $directory = self::directory('equivalence');
        $steps = StepCatalog::ids($directory);
        self::assertSame(['0001-make-accounts-login-unique', '0002-add-accounts-lastnewday', '0003-seed-server-languages'], $steps);
        $original = resurrection_database_snapshot();
        // The fixture is valid only when the undone changes lose nothing: every lastnewday is still its default.
        self::assertSame([], db_query("SELECT acctid FROM accounts WHERE lastnewday<>''"), 'Fixture invalid: an account has a lastnewday value.');
        self::assertSame([['value' => self::LANGUAGES]], db_query("SELECT value FROM settings WHERE setting='serverlanguages'"));
        try {
            foreach ($steps as $step) { resurrection_upgrade_record($step); }
            $fresh = self::withoutLedgerTimes();
            $undo = [
                '0003-seed-server-languages' => static fn () => db_query("DELETE FROM settings WHERE setting='serverlanguages'"),
                '0002-add-accounts-lastnewday' => static fn () => db_query('ALTER TABLE accounts DROP COLUMN lastnewday'),
                '0001-make-accounts-login-unique' => static fn () => db_query('ALTER TABLE accounts DROP INDEX `login`, ADD KEY `login` (`login`)'),
            ];
            // At step 0002: only the last step is pending.
            $undo['0003-seed-server-languages']();
            self::forget(['0003-seed-server-languages']);
            $GLOBALS['settings'] = null;
            self::assertSame(['Pending:', '- step 0003-seed-server-languages: Seed the server languages setting', 'Applying:', '- applied and recorded step 0003-seed-server-languages',
                'Upgrade complete: applied 1 upgrade step(s) and 0 module upgrade(s). The database is current.'], $this->upgrade($directory, true));
            self::assertSame($fresh, self::withoutLedgerTimes());
            // At no step: all three are pending, and the upgrade applies them in order.
            foreach ($undo as $change) { $change(); }
            self::forget($steps);
            $old = resurrection_database_snapshot();
            self::assertNotSame($fresh['tables']['accounts'], $old['tables']['accounts']);
            self::assertSame(['Pending:', '- step 0001-make-accounts-login-unique: Make the accounts login index unique', '- step 0002-add-accounts-lastnewday: Add the accounts lastnewday column',
                '- step 0003-seed-server-languages: Seed the server languages setting', 'Applying:', '- applied and recorded step 0001-make-accounts-login-unique',
                '- applied and recorded step 0002-add-accounts-lastnewday', '- applied and recorded step 0003-seed-server-languages',
                'Upgrade complete: applied 3 upgrade step(s) and 0 module upgrade(s). The database is current.'], $this->upgrade($directory, true));
            self::assertSame($fresh, self::withoutLedgerTimes());
            // Upgrading again changes nothing.
            $upgraded = resurrection_database_snapshot();
            self::assertSame(['The database is current: 3 of 3 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.'], $this->upgrade($directory, true));
            self::assertSame($upgraded, resurrection_database_snapshot());
        } finally {
            self::forget($steps);
        }
        self::assertSame($original, resurrection_database_snapshot());
    }

    public function testStepsRunInNumberOrderAndOnlyPendingOnesRun(): void
    {
        $this->connect();
        $directory = self::directory('order');
        $before = resurrection_database_snapshot();
        $log = static fn () => db_query("SELECT value FROM settings WHERE setting='upgrade_fixture_log'")[0]['value'] ?? null;
        try {
            $this->upgrade($directory, true);
            self::assertSame('0001,0002,0010', $log());
            self::assertSame(['0001-first', '0002-second', '0010-tenth'], self::ledger());
            self::forget(['0010-tenth']);
            db_query("DELETE FROM settings WHERE setting='upgrade_fixture_log'");
            self::assertSame(['0010-tenth'], resurrection_upgrade_state(StepCatalog::ids($directory))['pending']);
            $this->upgrade($directory, true);
            self::assertSame('0010', $log());
        } finally {
            self::forget(['0001-first', '0002-second', '0010-tenth']);
            db_query("DELETE FROM settings WHERE setting='upgrade_fixture_log'");
        }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testAReRunAfterAPartialFailureFinishesTheUpgrade(): void
    {
        $this->connect();
        $directory = self::directory('partial');
        $before = resurrection_database_snapshot();
        $columns = static fn () => array_column(db_query('SHOW COLUMNS FROM upgrade_fixture_probe'), 'Field');
        try {
            db_query("INSERT INTO settings (setting,value) VALUES ('upgrade_fixture_fail','1')");
            $lines = [];
            try {
                resurrection_upgrade($directory, true, static function (string $line) use (&$lines): void { $lines[] = $line; });
                self::fail('The failing step did not stop the upgrade.');
            } catch (UpgradeStopped $stopped) {
                self::assertMatchesRegularExpression('/\AUpgrade stopped: step 0002-two-changes failed \(0002-two-changes\.php:\d+\) and was not recorded\. The database is at the last good step: 0001-create-probe\. Nothing after the failure ran\. '
                    . 'Fix the cause and run the upgrade again: recorded steps are skipped, and every step checks for its own changes before it makes them\. If the cause cannot be fixed, restore the backup taken before this upgrade\.\z/', $stopped->getMessage());
            }
            self::assertSame(['Pending:', '- step 0001-create-probe: Create the probe table', '- step 0002-two-changes: Add two probe columns', '- step 0003-insert-row: Insert the probe row',
                'Applying:', '- applied and recorded step 0001-create-probe'], $lines);
            // The first change of the failed step is committed; its second change and the step after it did not run.
            self::assertSame(['0001-create-probe'], self::ledger());
            self::assertSame(['id', 'first'], $columns());
            self::assertSame([], db_query('SELECT id FROM upgrade_fixture_probe'));
            self::assertSame('behind', resurrection_upgrade_state(StepCatalog::ids($directory))['state']);
            // The cause fixed, the re-run skips the recorded step and the failed step skips its committed change.
            db_query("DELETE FROM settings WHERE setting='upgrade_fixture_fail'");
            self::assertSame(['Pending:', '- step 0002-two-changes: Add two probe columns', '- step 0003-insert-row: Insert the probe row', 'Applying:',
                '- applied and recorded step 0002-two-changes', '- applied and recorded step 0003-insert-row',
                'Upgrade complete: applied 2 upgrade step(s) and 0 module upgrade(s). The database is current.'], $this->upgrade($directory, true));
            self::assertSame(['0001-create-probe', '0002-two-changes', '0003-insert-row'], self::ledger());
            self::assertSame(['id', 'first', 'second'], $columns());
            self::assertSame([['id' => '1', 'first' => '1', 'second' => '2']], db_query('SELECT * FROM upgrade_fixture_probe'));
        } finally {
            db_query('DROP TABLE IF EXISTS upgrade_fixture_probe');
            db_query("DELETE FROM settings WHERE setting='upgrade_fixture_fail'");
            self::forget(['0001-create-probe', '0002-two-changes', '0003-insert-row']);
        }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testADryRunListsWhatIsPendingAndChangesNothing(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        self::assertSame(['Pending:', '- step 0001-create-probe: Create the probe table', '- step 0002-two-changes: Add two probe columns', '- step 0003-insert-row: Insert the probe row',
            'Dry run: nothing was changed.'], $this->upgrade(self::directory('partial'), false));
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testTheLockKeepsUpgradesInstallsAndMaintenanceApart(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        $other = new \PDO('mysql:host=' . explode(':', (string)getenv('RESURRECTION_TEST_DB_HOST'))[0] . ';port=' . (explode(':', (string)getenv('RESURRECTION_TEST_DB_HOST'))[1] ?? '3306') . ';dbname=resurrection_test',
            (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD'));
        $take = static fn (string $lock): string => (string)$other->query('SELECT GET_LOCK(' . $other->quote($lock) . ', 0)')->fetchColumn();
        $release = static fn (string $lock): string => (string)$other->query('SELECT RELEASE_LOCK(' . $other->quote($lock) . ')')->fetchColumn();
        $busy = "Upgrade refused. Another upgrade, an install or maintenance (cron.php) is running on this database. Run the upgrade again once it has finished. Nothing was changed.\n";
        // A running install (its lock) or a running cron.php (the maintenance lock) keeps the upgrade from starting.
        foreach ([resurrection_install_lock_name(), resurrection_maintenance_lock_name()] as $lock) {
            self::assertSame('1', $take($lock));
            try {
                self::assertSame([1, '', $busy], $this->command('--dry-run'));
                self::assertSame([1, '', $busy], $this->command('--backup-confirmed'));
            } finally { self::assertSame('1', $release($lock)); }
        }
        // While an upgrade runs, a second upgrade, an install and cron.php cannot take their locks.
        $directory = sys_get_temp_dir() . '/resurrection-steps-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $GLOBALS['upgrade_fixture_lock'] = ['config' => self::$config, 'php' => PHP_BINARY, 'root' => dirname(__DIR__, 2),
            'install' => resurrection_install_lock_name(), 'maintenance' => resurrection_maintenance_lock_name(), 'other' => $other, 'seen' => []];
        file_put_contents($directory . '/0001-check-the-locks.php', <<<'PHP'
            <?php
            return ['description' => 'Check the locks from outside', 'apply' => static function (): void {
                $fixture =& $GLOBALS['upgrade_fixture_lock'];
                foreach (['install', 'maintenance'] as $name) {
                    $fixture['seen'][$name] = (string)$fixture['other']->query('SELECT GET_LOCK(' . $fixture['other']->quote($fixture[$name]) . ', 0)')->fetchColumn();
                }
                $process = proc_open([$fixture['php'], 'scripts/upgrade.php', '--dry-run', $fixture['config']], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $fixture['root']);
                stream_get_contents($pipes[1]); fclose($pipes[1]);
                $fixture['seen']['upgrade'] = stream_get_contents($pipes[2]); fclose($pipes[2]);
                proc_close($process);
            }];
            PHP);
        try {
            self::assertTrue(resurrection_upgrade_lock());
            try { $this->upgrade($directory, true); } finally { resurrection_upgrade_unlock(); }
            self::assertSame(['install' => '0', 'maintenance' => '0', 'upgrade' => $busy], $GLOBALS['upgrade_fixture_lock']['seen']);
            // Released afterwards: the other connection gets both.
            foreach (['install', 'maintenance'] as $name) {
                self::assertSame('1', $take($GLOBALS['upgrade_fixture_lock'][$name]));
                self::assertSame('1', $release($GLOBALS['upgrade_fixture_lock'][$name]));
            }
        } finally {
            self::forget(['0001-check-the-locks']);
            unlink($directory . '/0001-check-the-locks.php');
            rmdir($directory);
            unset($GLOBALS['upgrade_fixture_lock']);
        }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testANewerDatabaseIsRefusedAndNotChanged(): void
    {
        $this->connect();
        $before = resurrection_database_snapshot();
        // A step this code does not have: a newer version upgraded the database.
        resurrection_upgrade_record('9999-from-a-newer-version');
        try {
            $GLOBALS['settings'] = null;
            self::assertSame('ahead', resurrection_database_state());
            $ahead = resurrection_database_snapshot();
            $refusal = "Upgrade refused. This database is newer than this version of Resurrection: it has upgrade steps this version does not have (9999-from-a-newer-version). A downgrade is refused: run the newer version again, or restore the backup taken before it upgraded this database. Nothing was changed.\n";
            self::assertSame([1, '', $refusal], $this->command('--backup-confirmed'));
            self::assertSame([1, '', $refusal], $this->command('--dry-run'));
            self::assertSame($ahead, resurrection_database_snapshot());
        } finally { self::forget(['9999-from-a-newer-version']); }
        // A bundled module newer than this code's.
        $module = (string)db_query('SELECT modulename FROM modules ORDER BY modulename LIMIT 1')[0]['modulename'];
        $stored = (string)db_query('SELECT version FROM modules WHERE modulename=?', true, [$module])[0]['version'];
        db_query("UPDATE modules SET version='99.0' WHERE modulename=?", true, [$module]);
        try {
            self::assertSame('ahead', resurrection_database_state());
            self::assertSame([1, '', 'Upgrade refused. This database is newer than this version of Resurrection: it has module ' . $module . ' 99.0, newer than this version\'s ' . $stored
                . ". A downgrade is refused: run the newer version again, or restore the backup taken before it upgraded this database. Nothing was changed.\n"], $this->command('--backup-confirmed'));
            self::assertSame('99.0', db_query('SELECT version FROM modules WHERE modulename=?', true, [$module])[0]['version']);
        } finally { db_query('UPDATE modules SET version=? WHERE modulename=?', true, [$stored, $module]); }
        // A step missing before one that is applied.
        $directory = self::directory('out-of-order');
        resurrection_upgrade_record('0002-second');
        try {
            self::assertSame('out-of-order', resurrection_upgrade_state(StepCatalog::ids($directory))['state']);
            $gap = resurrection_database_snapshot();
            try { $this->upgrade($directory, true); self::fail('An out-of-order database was upgraded.'); }
            catch (UpgradeRefused $refusal) {
                self::assertSame('The database\'s upgrade record is out of order: step 0001-first is not applied, but the later step 0002-second is. Steps only run in order, so this is not repaired: restore the backup taken before the last upgrade. Nothing was changed.', $refusal->getMessage());
            }
            self::assertSame($gap, resurrection_database_snapshot());
        } finally { self::forget(['0002-second']); }
        self::assertSame($before, resurrection_database_snapshot());
    }

    public function testABundledModuleUpgradeRunsTheModuleLifecycleAndMatchesTheFreshInstall(): void
    {
        // Every installed bundled module recorded at an older version, as a database from a release before each
        // module's current version would be. The upgrade runs each module's upgrade and ends as the fresh install.
        $this->connect();
        $original = resurrection_database_snapshot();
        $installed = array_column(db_query('SELECT modulename,version FROM modules ORDER BY modulename'), 'version', 'modulename');
        self::assertSame(array_keys(resurrection_bundled_module_versions()), array_keys($installed));
        // The upgrade records a module's metadata as the fresh install does (resurrection_install_bundled_modules()).
        // A module the module manager reinstalled has get_module_info()'s infokeys, which add "description" and
        // "requires"; only the flags in them are ever read (|settings|, |prefs|, |prefs-...|), and they are the same.
        // The fixture starts from the fresh install's rows.
        $infokeys = array_column(db_query('SELECT modulename,infokeys FROM modules ORDER BY modulename'), 'infokeys', 'modulename');
        foreach (self::moduleFiles() as $module => [, $keys]) { db_query('UPDATE modules SET infokeys=? WHERE modulename=?', true, [$keys, $module]); }
        $before = resurrection_database_snapshot();
        db_query("UPDATE modules SET version='0.1'");
        try {
            self::assertSame('behind', resurrection_database_state());
            $pending = array_map(static fn (string $module): string => '- module ' . $module . ': upgrade from 0.1 to ' . $installed[$module], array_keys($installed));
            self::assertSame([0, implode("\n", ['Pending:', ...$pending, 'Dry run: nothing was changed.']) . "\n", ''], $this->command('--dry-run'));
            $done = array_map(static fn (string $module): string => '- upgraded module ' . $module . ' from 0.1 to ' . $installed[$module], array_keys($installed));
            self::assertSame([0, implode("\n", ['Pending:', ...$pending, 'Applying:', ...$done, 'Upgrade complete: applied 0 upgrade step(s) and 24 module upgrade(s). The database is current.']) . "\n", ''],
                $this->command('--backup-confirmed'));
            // Hooks, settings, active state and support tables are exactly as they were.
            self::assertSame($before, resurrection_database_snapshot());
        } finally {
            foreach ($installed as $module => $version) { db_query('UPDATE modules SET version=?, infokeys=? WHERE modulename=?', true, [$version, $infokeys[$module], $module]); }
        }
        self::assertSame($original, resurrection_database_snapshot());
    }
}
