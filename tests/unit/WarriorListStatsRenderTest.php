<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * Phase 6 task 2 (B1, docs/PHASE6-TASK2-FIXES-20261106.md): two pages that were a 500 on a working game.
 *
 * - list.php's Warriors Online view and its op=clan view read hitpoints for the Alive column without selecting it, so a
 *   dead player online made the page fail. Each player now shows Yes, Unconscious (dead, hitpoints left) or No, as
 *   1.1.2's full list (which selects hitpoints) always did.
 * - stats.php's op=referers and op=graph used GROUP BY ... DESC, a syntax error on MySQL 8. On MySQL 8.4 both failed; on
 *   MariaDB they passed either way.
 *
 * Rendering is PopupAdminRenderTest's: the PHP CLI over a symlinked tree, a PHP session per account, the URL as the
 * account's one allowed navigation. The class works on its own install (scripts/install.php --modules=none); an install
 * another test left is set aside first and put back, unchanged, after. The game clock is pinned (tests/unit/GameClock.php).
 */
final class WarriorListStatsRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string PASSWORD = 'Synthetic warrior list render password';
    private const int EDIT_CONFIG = 128;
    private const int CLAN = 950001;

    private static string $tree = '';
    private static string $work = '';
    private static string $aside = '';
    private static bool $owned = false;
    /** @var array<string, int> the fixture's accounts, by login */
    private static array $ids = [];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { return; }
        $root = (string)realpath(self::ROOT);
        self::$work = sys_get_temp_dir() . '/resurrection-list-render-' . bin2hex(random_bytes(6));
        self::$tree = self::$work . '/tree';
        mkdir(self::$tree . '/.sessions', 0700, true);
        foreach (scandir($root) ?: [] as $entry) {
            if (in_array($entry, ['.', '..', '.git', 'dbconnect.php'], true)) { continue; }
            symlink($root . '/' . $entry, self::$tree . '/' . $entry);
        }
        file_put_contents(self::$tree . '/dbconnect.php', LegacyConfig::render(['DB_HOST' => $host,
            'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'),
            'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
        chmod(self::$tree . '/dbconnect.php', 0600);
        $state = self::php('echo resurrection_install_state();');
        if ($state === 'installed') {
            self::setAside(self::$work . '/aside.json');
            $state = self::php('echo resurrection_install_state();');
        }
        try {
            if ($state !== 'empty') {
                throw new \RuntimeException('The disposable test database must be empty or installed, not ' . $state);
            }
            self::$owned = true;
            [$code, $out, $err] = self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', self::$tree . '/dbconnect.php'], $root,
                ['RESURRECTION_ADMIN_LOGIN' => 'ListRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic list render admin password'] + getenv());
            if ($code !== 0) { throw new \RuntimeException('Install failed: ' . $err . $out); }
            foreach (GameClock::settings() as $name => $value) { self::php('savesetting(' . var_export($name, true) . ', ' . var_export($value, true) . ');'); }
            self::fixture();
        } catch (\Throwable $e) {
            self::restore();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tree === '') { return; }
        try {
            self::restore();
        } finally {
            self::remove(self::$work);
            self::$tree = '';
        }
    }

    protected function setUp(): void
    {
        if (getenv('RESURRECTION_TEST_DB_HOST') === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
    }

    /**
     * The online list and the clan's online list, with a dead player and an unconscious one online: 200, every online
     * fixture player listed with the state 1.1.2's full list gives them, the one offline left out.
     */
    public function testOnlineListsShowEachPlayersState(): void
    {
        // By level, dragon kills, then login, as the lists order them.
        $expected = ['RzListFaint' => 'Unconscious', 'RzListGhost' => 'No', 'RzListStaff' => 'Yes'];
        foreach (['list.php', 'list.php?op=clan'] as $url) {
            $states = $this->states($this->page('RzListStaff', $url));
            self::assertSame($expected, array_intersect_key($states, self::$ids), $url);
            self::assertArrayNotHasKey('RzListAway', $states, $url . ': only players online');
        }
        // The full list, which 1.1.2 already read hitpoints on, agrees, and lists the player who is offline too.
        $states = $this->states($this->page('RzListStaff', 'list.php?page=1'));
        self::assertSame(['RzListAway' => 'Yes'] + $expected, array_intersect_key($states, self::$ids));
        // The dead player's own view of the list.
        self::assertSame($expected, array_intersect_key($this->states($this->page('RzListGhost', 'list.php')), self::$ids));
    }

    /** The top referers render, on MySQL 8.4 as on MariaDB, in the order their ORDER BY sets. */
    public function testStatsReferersRender(): void
    {
        $referers = $this->document($this->page('RzListStaff', 'stats.php?op=referers'));
        $rows = [];
        foreach ($referers->querySelectorAll('.rz-content table tr.trlight, .rz-content table tr.trdark') as $row) {
            $cells = $row->querySelectorAll('td');
            $rows[] = [trim((string)$cells->item(0)?->textContent), preg_replace('/\s+/', ' ', trim((string)$cells->item(1)?->textContent))];
        }
        // Most referrals first; each referer's players in account order.
        self::assertSame([['RzListStaff', '2: RzListGhost , RzListFaint'], ['RzListGhost', '1: RzListAway']], $rows);
    }

    /** The logon graph renders, on MySQL 8.4 as on MariaDB, newest day first. */
    public function testStatsGraphRenders(): void
    {
        $graph = $this->document($this->page('RzListStaff', 'stats.php?op=graph'));
        $content = (string)$graph->querySelector('.rz-content')?->textContent;
        self::assertStringContainsString('Date accounts last logged on:', $content);
        $dates = [];
        foreach ($graph->querySelectorAll('.rz-content table tr') as $row) {
            $first = trim((string)$row->querySelector('td')?->textContent);
            if (preg_match('/\A\d{4}-\d\d-\d\d\z/', $first)) { $dates[] = $first; }
        }
        self::assertContains('2026-10-20', $dates, 'the offline player\'s day is on the graph');
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates, 'newest day first');
    }

    // Fixture.

    private static function fixture(): void
    {
        self::php('db_query("INSERT INTO clans (clanid, clanname, clanshort, clanmotd, clandesc, customsay) VALUES (?, ?, ?, ?, ?, ?)", true, ['
            . self::CLAN . ', "List Render Fixture Clan", "LRFC", "", "", ""]);');
        $now = date('Y-m-d H:i:s');
        // login => superuser, alive, hitpoints, online, referer, clan
        $accounts = ['RzListStaff' => [self::EDIT_CONFIG, 1, 20, true, '', self::CLAN],
            'RzListGhost' => [0, 0, 0, true, 'RzListStaff', self::CLAN],
            'RzListFaint' => [0, 0, 6, true, 'RzListStaff', self::CLAN],
            'RzListAway' => [0, 1, 20, false, 'RzListGhost', 0]];
        $acctid = 951000;
        foreach ($accounts as $login => [$superuser, $alive, $hitpoints, $online, $referer, $clan]) {
            $acctid++;
            $referer = $referer === '' ? 0 : self::$ids[$referer];
            $laston = $online ? $now : '2026-10-20 12:00:00';
            self::php('require_once "lib/accounts.php"; $id = resurrection_create_account(' . var_export($login, true) . ', ' . var_export(self::PASSWORD, true) . ');'
                . ' db_query("UPDATE accounts SET acctid=? WHERE acctid=?", true, [' . $acctid . ', $id]); db_query("UPDATE accounts_output SET acctid=? WHERE acctid=?", true, [' . $acctid . ', $id]);'
                . ' db_query("UPDATE accounts SET name=?, loggedin=?, laston=?, lasthit=?, superuser=?, alive=?, hitpoints=?, referer=?, clanid=?, clanrank=?, location=?, lastip=?, uniqueid=? WHERE acctid=?", true, ['
                . var_export($login, true) . ', ' . ($online ? 1 : 0) . ', ' . var_export($laston, true) . ', ' . var_export(date('Y-m-d H:i:s', time() + 60), true) . ', '
                . $superuser . ', ' . $alive . ', ' . $hitpoints . ', ' . $referer . ', ' . $clan . ', ' . ($clan > 0 ? 10 : 0) . ', "Degolburg", "127.0.0.1", "rzlist' . $acctid . '", ' . $acctid . ']);');
            self::$ids[$login] = $acctid;
        }
    }

    // Pages.

    /** One GET in the account's own PHP session, with the URL as its one allowed navigation. */
    private function page(string $login, string $url): string
    {
        $acctid = self::$ids[$login];
        $row = json_decode(self::php('db_query("UPDATE accounts SET allowednavs=?, restorepage=?, laston=IF(loggedin=1, ?, laston), lasthit=? WHERE acctid=?", true, [serialize([' . var_export($url, true) . ' => true]), "", '
            . var_export(date('Y-m-d H:i:s'), true) . ', ' . var_export(date('Y-m-d H:i:s', time() + 60), true) . ', ' . $acctid . ']);'
            . ' echo json_encode(db_query("SELECT authversion, superuser FROM accounts WHERE acctid=?", true, [' . $acctid . '])[0]);'), true);
        [$script, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        file_put_contents(self::$work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            "\$_POST = [];\n" .
            "session_save_path(" . var_export(self::$tree . '/.sessions', true) . "); session_id(" . var_export('rzlist' . $acctid, true) . "); session_start();\n" .
            "if (!isset(\$_SESSION['session'])) \$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => $acctid, 'loggedin' => true]],\n" .
            "    'auth_version' => " . (int)$row['authversion'] . ", 'auth_privileges' => " . (int)$row['superuser'] . "];\n" .
            "require '$script';\n");
        [$code, $out, $err] = self::process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'session.save_path=' . self::$tree . '/.sessions', self::$work . '/page.php'], self::$tree);
        self::assertSame(0, $code, $url . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $url);
        self::assertStringContainsString('</html>', $out, $url . ': ' . substr($out, 0, 2000));
        self::assertStringNotContainsString('Internal Server Error', $out, $url);
        return $out;
    }

    /** @return array<string, string> each listed player's Alive column, by login (each row links to the player's bio) */
    private function states(string $page): array
    {
        $logins = array_flip(self::$ids);
        $states = [];
        foreach ($this->document($page)->querySelectorAll('.rz-content table tr') as $row) {
            $bio = $row->querySelector('a[href^="bio.php?char="]');
            if ($bio === null) { continue; }
            $id = (int)substr((string)$bio->getAttribute('href'), strlen('bio.php?char='));
            $states[$logins[$id] ?? (string)$id] = trim((string)$row->querySelector('td')?->textContent);
        }
        return $states;
    }

    private function document(string $page): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString($page, LIBXML_NOERROR);
    }

    // Processes.

    /** Runs PHP code with the tree's dbconnect.php and the database libraries loaded, in a separate process. */
    private static function php(string $code): string
    {
        $preamble = 'require "dbconnect.php"; ob_start(); foreach (["dbwrapper_pdo", "constants", "datacache", "settings", "installer/fresh_install"] as $l) require "lib/" . $l . ".php";'
            . ' ob_end_clean(); $DB_USEDATACACHE = 0; db_connect($DB_HOST, $DB_USER, $DB_PASS) && db_select_db($DB_NAME) or exit(2);';
        [$exit, $out, $err] = self::process([PHP_BINARY, '-r', $preamble . ' ' . $code], self::$tree);
        if ($exit !== 0 || $err !== '') { throw new \RuntimeException("exit $exit: $err$out"); }
        return $out;
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $environment
     * @return array{0: int, 1: string, 2: string}
     */
    private static function process(array $command, string $directory, ?array $environment = null): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
        if (!is_resource($process)) { throw new \RuntimeException('proc_open failed'); }
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    /** Drops this class's install and puts back the install set aside, as it was. */
    private static function restore(): void
    {
        if (self::$owned) self::php('foreach (array_map(fn ($r) => (string)reset($r), db_query("SHOW TABLES")) as $t) db_query("DROP TABLE " . db_identifier($t));');
        self::$owned = false;
        if (self::$aside === '') { return; }
        self::php('foreach (json_decode(file_get_contents(' . var_export(self::$aside, true) . '), true) as $table) {'
            . ' db_query($table["create"]);'
            . ' foreach (array_chunk($table["rows"], 100) as $rows) {'
            . '  $columns = array_keys($rows[0]); $one = "(" . implode(",", array_fill(0, count($columns), "?")) . ")";'
            . '  $values = []; foreach ($rows as $row) foreach ($row as $v) $values[] = $v === null ? null : base64_decode($v);'
            . '  db_query("INSERT INTO " . db_identifier($table["name"]) . " (" . implode(",", array_map("db_identifier", $columns)) . ") VALUES " . implode(",", array_fill(0, count($rows), $one)), true, $values); } }');
        self::$aside = '';
    }

    /** Saves every table of the install another test left (definition and rows) to $file, then drops them. */
    private static function setAside(string $file): void
    {
        self::php('$out = []; foreach (array_map(fn ($r) => (string)reset($r), db_query("SHOW TABLES")) as $t) {'
            . ' $create = db_query("SHOW CREATE TABLE " . db_identifier($t))[0]["Create Table"];'
            . ' $rows = array_map(fn ($row) => array_map(fn ($v) => $v === null ? null : base64_encode((string)$v), $row), db_query("SELECT * FROM " . db_identifier($t)));'
            . ' $out[] = ["name" => $t, "create" => $create, "rows" => $rows]; }'
            . ' file_put_contents(' . var_export($file, true) . ', json_encode($out, JSON_THROW_ON_ERROR)); chmod(' . var_export($file, true) . ', 0600);'
            . ' foreach ($out as $table) db_query("DROP TABLE " . db_identifier($table["name"]));');
        self::$aside = $file;
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { self::remove($path . '/' . $entry); }
        }
        rmdir($path);
    }
}
