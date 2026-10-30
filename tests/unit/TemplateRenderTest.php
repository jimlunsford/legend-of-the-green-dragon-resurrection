<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The Resurrection UI's template (templates/resurrection.htm), the default since Phase 5 task 3, renders real pages:
 * a signed-out home page, a signed-in village page and the mail popup (docs/PHASE5-TASK3-TEMPLATE-20261030.md). Each
 * is rendered by the PHP CLI with output_buffering=0, as OutputBufferingTest does, and must finish cleanly, print no
 * template warning, start with the template's own doctype, and carry the copyright line exactly once.
 *
 * The test does not depend on the order it runs in: an installed database is used as it is; an empty one is installed
 * here with scripts/install.php --modules=none and emptied again afterwards. The player it creates is removed.
 */
final class TemplateRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team';

    public function testTheDefaultTemplateRendersAPageASignedInPageAndAPopup(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $root = (string)realpath(self::ROOT);
        $work = sys_get_temp_dir() . '/resurrection-template-render-' . bin2hex(random_bytes(6));
        $tree = $work . '/tree';
        mkdir($tree . '/.sessions', 0700, true);
        $installed = false;
        $player = 0;
        try {
            foreach (scandir($root) ?: [] as $entry) {
                if (in_array($entry, ['.', '..', '.git', 'dbconnect.php'], true)) { continue; }
                symlink($root . '/' . $entry, $tree . '/' . $entry);
            }
            file_put_contents($tree . '/dbconnect.php', LegacyConfig::render(['DB_HOST' => $host,
                'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'),
                'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
            chmod($tree . '/dbconnect.php', 0600);
            $state = $this->php($tree, 'echo resurrection_install_state();');
            self::assertContains($state, ['empty', 'installed'], 'The disposable test database must be empty or installed.');
            if ($state === 'empty') {
                [$code, $out, $err] = $this->process([PHP_BINARY, 'scripts/install.php', '--modules=none', $tree . '/dbconnect.php'], $root,
                    ['RESURRECTION_ADMIN_LOGIN' => 'TemplateRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic template render password'] + getenv());
                self::assertSame(0, $code, $err . $out);
                $installed = true;
            }
            // The game's own default, as a fresh install stores it.
            self::assertSame('resurrection.htm', $this->php($tree, 'echo getsetting("defaultskin", "unset");'));

            $home = $this->render($tree, $work, 'home.php', null);
            $this->assertCleanPage($home, 'home.php');
            self::assertStringContainsString('<form action=\'login.php\' method=\'POST\'>', $home);
            self::assertStringContainsString('<input name="name" id="name"', $home);
            self::assertStringContainsString('class="rz-navitem nav"', $home);
            self::assertStringContainsString('<a href="about.php">Credits</a>', $home);

            // A signed-in player in the village: the account the game creates, signed in through the session the
            // forced-navigation check reads (do_forced_nav()).
            $player = (int)$this->php($tree, 'require_once "lib/accounts.php"; $id = resurrection_create_account("TemplateRender", "Synthetic template render player password");'
                . ' db_query("UPDATE accounts SET loggedin=1, laston=?, lasthit=? WHERE acctid=?", true, [date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), $id]); echo $id;');
            self::assertGreaterThan(0, $player);
            $version = (int)$this->php($tree, 'echo db_fetch_assoc(db_query("SELECT authversion FROM accounts WHERE acctid=?", true, [' . $player . ']))["authversion"];');
            $session = ['acctid' => $player, 'authversion' => $version];

            $village = $this->render($tree, $work, 'village.php', $session);
            $this->assertCleanPage($village, 'village.php');
            self::assertStringContainsString('<section class="rz-card rz-statsection">', $village);
            self::assertStringContainsString('class="rz-navitem nav"', $village);
            self::assertStringContainsString('Ye Olde Mail', $village);

            $mail = $this->render($tree, $work, 'mail.php', $session);
            $this->assertCleanPage($mail, 'mail.php');
            self::assertStringContainsString('<body class="rz-page rz-popup">', $mail);
            self::assertStringNotContainsString('class="rz-columns"', $mail);
        } finally {
            $cleanup = [];
            if ($player > 0) {
                $cleanup[] = $this->php($tree, 'foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $player . ']); echo "removed";', false);
            }
            // Only an install this test made in the disposable database: empty it again for the tests that follow.
            if ($installed) {
                $cleanup[] = $this->php($tree, 'foreach (array_column(db_query("SHOW TABLES"), "Tables_in_resurrection_test") as $t) db_query("DROP TABLE " . db_identifier($t)); echo resurrection_install_state();', false);
            }
            $this->remove($work);
        }
        self::assertSame(array_merge($player > 0 ? ['removed'] : [], $installed ? ['empty'] : []), $cleanup);
    }

    /** A finished page of the new template: no error, no template warning, the copyright line once. */
    private function assertCleanPage(string $page, string $script): void
    {
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $page, $script);
        self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', $page, $script);
        self::assertStringNotContainsString('Runtime error', $page, $script);
        self::assertStringNotContainsString('template part was not found', $page, $script);
        self::assertStringNotContainsString('piece was not found', $page, $script);
        self::assertStringNotContainsString('errors in your template page', $page, $script);
        self::assertSame(1, substr_count($page, self::COPYRIGHT), $script);
        self::assertSame(1, substr_count($page, 'Creative Commons License</a>'), $script);
        self::assertStringEndsWith("</html>\n", $page, $script);
    }

    /**
     * One GET of a page, rendered by the PHP CLI with output_buffering=0. With a session, the request is signed in as
     * that account: the session the game started at login, which do_forced_nav() checks against the account row.
     *
     * @param array{acctid: int, authversion: int}|null $session
     */
    private function render(string $tree, string $work, string $script, ?array $session): string
    {
        $signin = $session === null ? '' :
            "session_save_path(" . var_export($tree . '/.sessions', true) . "); session_start();\n" .
            "\$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$session['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$session['authversion']}, 'auth_privileges' => 0];\n";
        file_put_contents($work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => '/$script', 'QUERY_STRING' => '',\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            $signin .
            "require '$script';\n");
        [$code, $out, $err] = $this->process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'session.save_path=' . $tree . '/.sessions', $work . '/page.php'], $tree);
        self::assertSame(0, $code, $script . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $script);
        return $out;
    }

    /**
     * Runs PHP code with the tree's dbconnect.php and the database libraries loaded, in a separate process. The
     * libraries load lib/all_tables.php, whose trailing newline is discarded as every loader does.
     */
    private function php(string $tree, string $code, bool $assert = true): string
    {
        $preamble = 'require "dbconnect.php"; ob_start(); foreach (["dbwrapper_pdo", "constants", "datacache", "settings", "installer/fresh_install"] as $l) require "lib/" . $l . ".php";'
            . ' ob_end_clean(); $DB_USEDATACACHE = 0; db_connect($DB_HOST, $DB_USER, $DB_PASS) && db_select_db($DB_NAME) or exit(2);';
        [$exit, $out, $err] = $this->process([PHP_BINARY, '-r', $preamble . ' ' . $code], $tree);
        if ($assert) { self::assertSame(0, $exit, $err . $out); }
        return $exit === 0 && $err === '' ? $out : "exit $exit: $err$out";
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $environment
     * @return array{0: int, 1: string, 2: string}
     */
    private function process(array $command, string $directory, ?array $environment = null): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $this->remove($path . '/' . $entry); }
        }
        rmdir($path);
    }
}
