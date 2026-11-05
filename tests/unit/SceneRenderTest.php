<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The location scenes on real pages (Phase 5 task 12, docs/PHASE5-TASK12-SCENES-20261105.md): the inn, the bank, the
 * graveyard of a dead player and a module page at the inn (Seth's song) each show their place's scene at the top of the
 * content card, its alt text the place's name: the inn's from its setting (renamed, the new name, on the inn and on the
 * module's page alike), the bank's and the graveyard's translated. Each page is rendered by the PHP CLI as
 * TemplateRenderTest does.
 *
 * The test does not depend on the order it runs in: an installed database is used as it is; an empty one is installed
 * here with scripts/install.php --modules=none (every bundled module installed, none active) and emptied again
 * afterwards. The player it creates is removed; the clock, the inn's name and the song module's state are restored.
 */
final class SceneRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team';
    private const string CARD = '<div class="rz-card rz-content">';

    public function testThePlacesShowTheirScenesWithThePlacesNames(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $root = (string)realpath(self::ROOT);
        $work = sys_get_temp_dir() . '/resurrection-scene-render-' . bin2hex(random_bytes(6));
        $tree = $work . '/tree';
        mkdir($tree . '/.sessions', 0700, true);
        $installed = false;
        $player = 0;
        $saved = null;
        $active = null;
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
                    ['RESURRECTION_ADMIN_LOGIN' => 'SceneRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic scene render password'] + getenv());
                self::assertSame(0, $code, $err . $out);
                $installed = true;
            }

            // The clock (GameClock, as every page-rendering test) and the inn's name, saved and restored afterwards.
            $settings = GameClock::settings();
            $names = var_export(array_merge(array_keys($settings), ['innname']), true);
            $saved = json_decode($this->php($tree, '$s = []; foreach (' . $names . ' as $n) $s[$n] = db_query("SELECT value FROM settings WHERE setting=?", true, [$n]); echo json_encode($s);'), true);
            foreach ($settings as $name => $value) {
                $this->php($tree, 'savesetting(' . var_export($name, true) . ', ' . var_export($value, true) . ');');
            }
            $this->php($tree, 'savesetting("innname", ' . var_export("The Boar's Head Inn", true) . ');');

            $player = (int)$this->php($tree, 'require_once "lib/accounts.php"; $id = resurrection_create_account("SceneRender", "Synthetic scene render player password");'
                . ' db_query("UPDATE accounts SET loggedin=1, laston=?, lasthit=? WHERE acctid=?", true, [date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), $id]); echo $id;');
            self::assertGreaterThan(0, $player);
            $version = (int)$this->php($tree, 'echo db_fetch_assoc(db_query("SELECT authversion FROM accounts WHERE acctid=?", true, [' . $player . ']))["authversion"];');
            $session = ['acctid' => $player, 'authversion' => $version];

            // The inn: its scene, named by the inn's setting.
            $inn = $this->visit($tree, $work, $player, $session, 'inn.php');
            $this->assertScene($inn, 'inn', "The Boar's Head Inn");

            // The bank: its scene, its name translated (English here).
            $bank = $this->visit($tree, $work, $player, $session, 'bank.php');
            $this->assertScene($bank, 'bank', 'Ye Olde Bank');

            // The inn renamed: the new name, on the inn and on a module's page at the inn (Seth's song, made active for
            // the test and put back as it was).
            $this->php($tree, 'savesetting("innname", "`#The Prancing Pony`0");');
            $inn = $this->visit($tree, $work, $player, $session, 'inn.php');
            $this->assertScene($inn, 'inn', 'The Prancing Pony');
            $active = $this->php($tree, '$r = db_query("SELECT active FROM modules WHERE modulename=?", true, ["sethsong"]); echo count($r) === 1 ? $r[0]["active"] : "missing";');
            self::assertContains($active, ['0', '1'], 'The bundled song module is installed.');
            $this->php($tree, 'db_query("UPDATE modules SET active=1 WHERE modulename=?", true, ["sethsong"]);');
            $song = $this->visit($tree, $work, $player, $session, 'runmodule.php', 'module=sethsong');
            self::assertStringContainsString('Listen to ', $song);
            $this->assertScene($song, 'inn', 'The Prancing Pony');

            // Dead, in the graveyard: its scene.
            $this->php($tree, 'db_query("UPDATE accounts SET alive=0, hitpoints=0, soulpoints=60, gravefights=6, deathpower=40 WHERE acctid=?", true, [' . $player . ']);');
            $graveyard = $this->visit($tree, $work, $player, $session, 'graveyard.php');
            $this->assertScene($graveyard, 'graveyard', 'The Graveyard');
        } finally {
            $cleanup = [];
            if ($active === '0' || $active === '1') {
                $cleanup[] = $this->php($tree, 'db_query("UPDATE modules SET active=? WHERE modulename=?", true, [' . (int)$active . ', "sethsong"]); echo "module restored";', false);
            }
            if ($player > 0) {
                $cleanup[] = $this->php($tree, 'db_query("DELETE FROM module_userprefs WHERE userid=?", true, [' . $player . ']); foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $player . ']); echo "removed";', false);
            }
            if (is_array($saved) && !$installed) {
                $restore = '';
                foreach ($saved as $name => $rows) {
                    $restore .= 'db_query("DELETE FROM settings WHERE setting=?", true, [' . var_export($name, true) . ']);';
                    foreach ($rows as $row) {
                        $restore .= ' db_query("INSERT INTO settings (setting, value) VALUES (?, ?)", true, [' . var_export($name, true) . ', ' . var_export($row['value'], true) . ']);';
                    }
                }
                $cleanup[] = $this->php($tree, $restore . ' echo "settings restored";', false);
            }
            // Only an install this test made in the disposable database: empty it again for the tests that follow.
            if ($installed) {
                $cleanup[] = $this->php($tree, 'foreach (array_column(db_query("SHOW TABLES"), "Tables_in_resurrection_test") as $t) db_query("DROP TABLE " . db_identifier($t)); echo resurrection_install_state();', false);
            }
            $this->remove($work);
        }
        self::assertSame(array_merge($active === '0' || $active === '1' ? ['module restored'] : [], $player > 0 ? ['removed'] : [],
            is_array($saved) && !$installed ? ['settings restored'] : [], $installed ? ['empty'] : []), $cleanup);
    }

    /**
     * The player's next page: the page allowed (as the nav that led there would), the last hit a minute ahead, then one
     * GET of it.
     *
     * @param array{acctid: int, authversion: int} $session
     */
    private function visit(string $tree, string $work, int $player, array $session, string $script, string $query = ''): string
    {
        $nav = $script . ($query === '' ? '' : '?' . $query);
        $this->php($tree, 'db_query("UPDATE accounts SET allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true, [serialize([' . var_export($nav, true) . ' => true]),'
            . ' date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
        $page = $this->render($tree, $work, $script, $session, $query);
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $page, $nav);
        self::assertStringNotContainsString('Runtime error', $page, $nav);
        self::assertStringNotContainsString('template part was not found', $page, $nav);
        self::assertSame(1, substr_count($page, self::COPYRIGHT), $nav);
        self::assertStringEndsWith("</html>\n", $page, $nav);
        return $page;
    }

    /** The scene banner: first in the content card, the scene's file with the place's name as its alt text, once. */
    private function assertScene(string $page, string $scene, string $alt): void
    {
        $banner = '<div class="rz-scene"><img class="rz-scene-img" src="assets/scenes/' . $scene . '.svg" alt="'
            . htmlspecialchars($alt, ENT_QUOTES) . '" width="1200" height="320"></div>' . "\n";
        self::assertStringContainsString(self::CARD . $banner, $page, $scene);
        self::assertSame(1, substr_count($page, 'class="rz-scene"'), $scene);
        self::assertSame(1, substr_count($page, '<img class="rz-scene-img"'), $scene);
    }

    /**
     * One GET of a page, rendered by the PHP CLI with output_buffering=0, signed in as the account: the session the game
     * started at login, which do_forced_nav() checks against the account row.
     *
     * @param array{acctid: int, authversion: int} $session
     */
    private function render(string $tree, string $work, string $script, array $session, string $query = ''): string
    {
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        file_put_contents($work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            "session_save_path(" . var_export($tree . '/.sessions', true) . "); session_start();\n" .
            "\$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$session['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$session['authversion']}, 'auth_privileges' => 0];\n" .
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
