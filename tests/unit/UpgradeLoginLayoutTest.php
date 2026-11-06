<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Decision 1's amendment, threat T22 (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md): the web setup
 * page's upgrade asks for an SU_MEGAUSER login and password, checked once, before any step runs, against the database
 * as the upgrade finds it. This proves the check on the oldest layout an upgrade starts from: a completed install
 * without the upgrade ledger (before the upgrade system existed), with a pending step that changes the accounts table.
 * The check reads only what the accounts table has always had, writes nothing, and works before and after the step.
 *
 * It runs on the installed database FreshInstallTest leaves, saves it whole first and puts it back exactly.
 */
final class UpgradeLoginLayoutTest extends TestCase
{
    private const string PASSWORD = 'Synthetic layout password 42';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'accounts', 'installer/install_run', 'upgrade', 'setup/run'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
        require_once __DIR__ . '/../support/database_save.php';
        require_once __DIR__ . '/../support/database_snapshot.php';
    }

    public function testAuthenticatesAgainstTheOldestUpgradableLayoutBeforeAnyStep(): void
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
        $saved = resurrection_database_save();
        $steps = (string)tempnam(sys_get_temp_dir(), 'resurrection-layout-steps-');
        unlink($steps);
        mkdir($steps, 0700);
        try {
            $mega = resurrection_create_account('LayoutAdmin', self::PASSWORD);
            db_query('UPDATE accounts SET superuser=? WHERE acctid=?', true, [SU_MEGAUSER | SU_EDIT_CONFIG, $mega]);
            $staff = resurrection_create_account('LayoutStaff', self::PASSWORD);
            db_query('UPDATE accounts SET superuser=? WHERE acctid=?', true, [SU_HIDE_FROM_LEADERBOARD & ~SU_MEGAUSER, $staff]);
            // The oldest layout: no upgrade ledger. The code's own steps (none yet) and one pending step that changes
            // the accounts table.
            db_query('DROP TABLE upgrade_steps');
            foreach (glob('upgrades/[0-9][0-9][0-9][0-9]-*.php') ?: [] as $step) { copy($step, $steps . '/' . basename($step)); }
            file_put_contents($steps . '/9901-layout-fixture.php', "<?php\nreturn ['description' => 'Layout fixture', 'apply' => static function (): void {\n"
                . "    \$columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');\n"
                . "    if (!in_array('fixture_layout', \$columns, true)) db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . ' ADD COLUMN fixture_layout int NOT NULL DEFAULT 0');\n"
                . "}];\n");
            self::assertSame('installed', resurrection_install_state());
            $before = resurrection_database_snapshot();
            // The check, before any step: right password with the flag; wrong password, no flag, unknown login refused.
            self::assertTrue(resurrection_setup_administrator('LayoutAdmin', self::PASSWORD));
            self::assertFalse(resurrection_setup_administrator('LayoutAdmin', 'Not the password 99'));
            self::assertFalse(resurrection_setup_administrator('LayoutStaff', self::PASSWORD));
            self::assertFalse(resurrection_setup_administrator('Nobody', self::PASSWORD));
            self::assertFalse(resurrection_setup_administrator('LayoutAdmin', ['x']));
            self::assertFalse(resurrection_setup_administrator('', ''));
            // It changed nothing: no ledger, no new column, every table as it was.
            self::assertSame($before, resurrection_database_snapshot());
            self::assertFalse(db_table_exists('upgrade_steps'));
            // The upgrade then applies the step, and the check still works on the new layout.
            $lines = [];
            resurrection_upgrade($steps, true, static function (string $line) use (&$lines): void { $lines[] = $line; });
            $GLOBALS['settings'] = null;
            self::assertContains('- created the upgrade ledger (the upgrade_steps table)', $lines);
            self::assertContains('fixture_layout', array_column(db_query('SHOW COLUMNS FROM accounts'), 'Field'));
            self::assertSame(['9901-layout-fixture'], array_column(db_query("SELECT step FROM upgrade_steps WHERE step LIKE '99%'"), 'step'));
            self::assertTrue(resurrection_setup_administrator('LayoutAdmin', self::PASSWORD));
        } finally {
            resurrection_database_restore($saved);
            foreach (glob($steps . '/*') ?: [] as $file) { unlink($file); }
            rmdir($steps);
            $GLOBALS['settings'] = null;
        }
        self::assertSame('current', resurrection_database_state());
    }

    public function testTheCheckReadsOnlyTheOriginalAccountColumns(): void
    {
        // The check is resurrection_authenticate() and the SU_MEGAUSER bit: it reads login, password, locked,
        // emailvalidation and superuser, which 1.1.2's accounts table already had, and nothing a step adds.
        $source = (string)file_get_contents('lib/setup/run.php');
        preg_match('/function resurrection_setup_administrator\(.*?\n}/s', $source, $function);
        self::assertStringContainsString('resurrection_authenticate($login, $password)', $function[0]);
        self::assertStringContainsString("& SU_MEGAUSER) === SU_MEGAUSER", $function[0]);
        $schema = (string)shell_exec('git -C ' . escapeshellarg(dirname(__DIR__, 2)) . ' show historical-source-1.1.2:lib/all_tables.php 2>/dev/null');
        if ($schema === '') self::markTestSkipped('The historical tag is not in this clone.');
        preg_match("/'accounts'=>array\((.*?)'accounts_output'=>array\(/s", $schema, $accounts);
        foreach (['login', 'password', 'locked', 'emailvalidation', 'superuser'] as $column) {
            self::assertStringContainsString("'name'=>'" . $column . "'", $accounts[1] ?? '', $column);
        }
        // The upgrade step checks the login before it opens the grant and runs the first part (Continue needs the grant).
        $upgrade = substr($source, (int)strpos($source, 'function resurrection_setup_upgrade('));
        $upgrade = substr($upgrade, 0, (int)strpos($upgrade, "\n}\n"));
        $check = strpos($upgrade, 'resurrection_setup_administrator(');
        self::assertNotFalse($check);
        self::assertLessThan(strpos($upgrade, 'resurrection_setup_grant_new('), $check);
        self::assertLessThan(strrpos($upgrade, 'resurrection_setup_upgrade_part($state'), $check);
    }
}
