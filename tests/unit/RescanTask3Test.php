<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Final rescan task 3: referer tracking fitted to its columns, and values the SQL sweep bound. */
final class RescanTask3Test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // lib/names.php loads the database wrapper, which installs the application's error handler.
        require_once 'lib/names.php';
        foreach (['settings', 'datacache', 'e_rand', 'gamelog', 'referer_tracking', 'titles', 'modules', 'output', 'translator', 'http'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: array{uri: string, site: string, dest: string, ip: string}|null}> */
    public static function referers(): array
    {
        $long = str_repeat('a', 70) . '.example';
        $snow = str_repeat('雪', 60) . '.example';
        $path = '/home.php?' . str_repeat('p', 300);
        return [
            'none' => ['', '/home.php', 'game.example', null],
            'own site' => ['http://game.example/village.php', '/home.php', 'game.example', null],
            'own host and port' => ['http://game.example:80/news.php', '/home.php', 'game.example', null],
            'same host' => ['http://other.example/x', '/home.php', 'other.example:80', null],
            'not http' => ['https://other.example/x', '/home.php', 'game.example', null],
            'other site' => ['http://other.example/x?q=1', '/home.php', 'game.example',
                ['uri' => 'http://other.example/x?q=1', 'site' => 'other.example', 'dest' => 'game.example//home.php', 'ip' => '10.0.0.1']],
            'quotes kept' => ["http://o'reilly\\\".example/a'b", '/home.php', 'game.example',
                ['uri' => "http://o'reilly\\\".example/a'b", 'site' => "o'reilly\\\".example", 'dest' => 'game.example//home.php', 'ip' => '10.0.0.1']],
            'host cut to 50' => ['http://' . $long . '/x', $path, 'game.example',
                ['uri' => 'http://' . $long . '/x', 'site' => substr($long, 0, 50), 'dest' => substr('game.example/' . $path, 0, 255), 'ip' => '10.0.0.1']],
            'cut in characters' => ['http://' . $snow . '/雪', '/home.php', 'game.example',
                ['uri' => 'http://' . $snow . '/雪', 'site' => mb_substr($snow, 0, 50), 'dest' => 'game.example//home.php', 'ip' => '10.0.0.1']],
            'not UTF-8' => ["http://bad\xff.example/x", '/home.php', 'game.example', null],
            'path not UTF-8' => ['http://other.example/x', "/home.php?\xff", 'game.example', null],
        ];
    }

    /** @param array{uri: string, site: string, dest: string, ip: string}|null $expected */
    #[DataProvider('referers')]
    public function testRefererRowKeeps112RulesAndFitsTheColumns(string $referer, string $request, string $host, ?array $expected): void
    {
        self::assertSame($expected, resurrection_referer_row($referer, 'game.example', '80', '/home.php', $request, $host, '10.0.0.1'));
    }

    public function testRefererUriIsCutOnACharacterBoundary(): void
    {
        $uri = 'http://other.example/' . str_repeat('雪', 30000);
        $row = resurrection_referer_row($uri, 'game.example', '80', '/', '/', 'game.example', '');
        self::assertNotNull($row);
        self::assertLessThanOrEqual(65535, strlen($row['uri']));
        self::assertTrue(mb_check_encoding($row['uri'], 'UTF-8'));
        self::assertStringStartsWith($row['uri'], $uri);
    }

    private function database(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires installed disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        self::assertTrue(db_connect($host, getenv('RESURRECTION_TEST_DB_USER'), getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        $GLOBALS['session'] = ['loggedin' => true, 'user' => ['acctid' => 0, 'loggedin' => true]];
    }

    /** @param array<string, string> $server */
    private function visit(array $server): void
    {
        $saved = $_SERVER;
        $_SERVER = $server + ['SERVER_NAME' => 'game.example', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/home.php', 'HTTP_HOST' => 'game.example', 'REMOTE_ADDR' => '10.0.0.1'];
        $GLOBALS['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
        try { resurrection_track_referer(); } finally { $_SERVER = $saved; }
    }

    public function testTrackingCountsBoundRowsAndNeverThrows(): void
    {
        $this->database();
        db_query("DELETE FROM referers WHERE uri LIKE 'http://%track3unit%'");
        try {
            $referer = "http://o'reilly.track3unit.example/" . str_repeat('x', 40) . "'";
            $this->visit(['HTTP_REFERER' => $referer]);
            $this->visit(['HTTP_REFERER' => $referer]);
            $rows = db_query("SELECT uri,site,dest,CAST(count AS CHAR) AS count FROM referers WHERE uri LIKE 'http://%track3unit%'");
            self::assertSame([['uri' => $referer, 'site' => "o'reilly.track3unit.example", 'dest' => 'game.example//home.php', 'count' => '2']], $rows);
            // The database refusing the row ends the tracking quietly.
            db_query("ALTER TABLE referers ADD CONSTRAINT fixture_track3_unit CHECK (site <> 'refused.track3unit.example')");
            try { $this->visit(['HTTP_REFERER' => 'http://refused.track3unit.example/x']); }
            finally { db_query('ALTER TABLE referers DROP CONSTRAINT fixture_track3_unit'); }
            self::assertCount(1, db_query("SELECT uri FROM referers WHERE uri LIKE 'http://%track3unit%'"));
        } finally {
            db_query("DELETE FROM referers WHERE uri LIKE 'http://%track3unit%'");
        }
    }

    public function testTrackingKeeps112MonthlyCleanupOnOneNewRowInAHundred(): void
    {
        $this->database();
        db_query("DELETE FROM referers WHERE uri LIKE 'http://%track3unit%'");
        db_query("DELETE FROM gamelog WHERE category='maintenance' AND message LIKE 'Deleted % records from referers older than %'");
        // A seed whose first draw of e_rand(1,100) is 2, the draw 1.1.2 cleaned up on.
        $seed = 0;
        do { mt_srand(++$seed); } while (mt_rand(1, 100) !== 2);
        try {
            db_query("INSERT INTO referers (uri,count,last,site,dest,ip) VALUES ('http://old.track3unit.example/',1,?,'old.track3unit.example','x','')", true, [date('Y-m-d H:i:s', strtotime('-2 months'))]);
            db_query("INSERT INTO referers (uri,count,last,site,dest,ip) VALUES ('http://recent.track3unit.example/',1,?,'recent.track3unit.example','x','')", true, [date('Y-m-d H:i:s', strtotime('-1 week'))]);
            mt_srand($seed);
            $this->visit(['HTTP_REFERER' => 'http://new.track3unit.example/']);
            $uris = array_column(db_query("SELECT uri FROM referers WHERE uri LIKE 'http://%track3unit%' ORDER BY uri"), 'uri');
            self::assertSame(['http://new.track3unit.example/', 'http://recent.track3unit.example/'], $uris);
            self::assertCount(1, db_query("SELECT message FROM gamelog WHERE category='maintenance' AND message LIKE 'Deleted % records from referers older than %'"));
        } finally {
            db_query("DELETE FROM referers WHERE uri LIKE 'http://%track3unit%'");
            db_query("DELETE FROM gamelog WHERE category='maintenance' AND message LIKE 'Deleted % records from referers older than %'");
        }
    }

    public function testGamelogStoresItsTextAsGiven(): void
    {
        $this->database();
        $message = "Track3 O'Reilly \\ 雪 '); DROP TABLE x; --";
        try {
            gamelog($message, "track3'cat");
            self::assertSame([['message' => $message, 'category' => "track3'cat"]],
                db_query("SELECT message,category FROM gamelog WHERE category=?", true, ["track3'cat"]));
        } finally {
            db_query("DELETE FROM gamelog WHERE category=?", true, ["track3'cat"]);
        }
    }

    public function testModulePreferenceApiBindsNamesAndValues(): void
    {
        $this->database();
        $module = 'track3unit';
        $name = "o'reilly \\ pref";
        db_query('DELETE FROM module_userprefs WHERE modulename=?', true, [$module]);
        db_query('DELETE FROM module_objprefs WHERE modulename=?', true, [$module]);
        db_query('DELETE FROM module_settings WHERE modulename=?', true, [$module]);
        try {
            // 1.1.2 pasted the name in unescaped: a quote broke the query.
            increment_module_pref($name, 2, $module, 7);
            increment_module_pref($name, 3, $module, 7);
            self::assertSame([['setting' => $name, 'value' => '5']], db_query('SELECT setting,value FROM module_userprefs WHERE modulename=? AND userid=7', true, [$module]));
            clear_module_pref($name, $module, 7);
            self::assertSame([], db_query('SELECT setting FROM module_userprefs WHERE modulename=?', true, [$module]));
            increment_module_objpref("type'x", 3, $name, 4, $module);
            increment_module_objpref("type'x", 3, $name, 1, $module);
            self::assertSame('5', db_query('SELECT value FROM module_objprefs WHERE modulename=? AND objtype=? AND objid=3', true, [$module, "type'x"])[0]['value']);
            module_delete_objprefs("type'x", 3);
            self::assertSame([], db_query('SELECT value FROM module_objprefs WHERE modulename=?', true, [$module]));
            // A value is a number: text never becomes part of the query. (A setting that does not exist yet is not
            // incremented here: 1.1.2 then adds to an unset cache entry, a PHP warning; no shipped module calls it.)
            set_module_setting($name, 0, $module);
            increment_module_setting($name, "1 WHERE 1=1; --", $module);
            increment_module_setting($name, 2, $module);
            self::assertSame('3', db_query('SELECT value FROM module_settings WHERE modulename=? AND setting=?', true, [$module, $name])[0]['value']);
        } finally {
            db_query('DELETE FROM module_userprefs WHERE modulename=?', true, [$module]);
            db_query('DELETE FROM module_objprefs WHERE modulename=?', true, [$module]);
            db_query('DELETE FROM module_settings WHERE modulename=?', true, [$module]);
        }
    }

    public function testTitlesBindTheReference(): void
    {
        $this->database();
        db_query("DELETE FROM titles WHERE ref=?", true, ["track3'ref"]);
        try {
            db_query("INSERT INTO titles (dk,ref,male,female) VALUES (0,?,'Track3 Sir','Track3 Dame')", true, ["track3'ref"]);
            // 1.1.2 pasted the reference in: a quote broke the query.
            self::assertTrue(valid_dk_title('Track3 Sir', 0, 0));
            self::assertSame('Track3 Dame', get_dk_title(0, 1, "track3'ref"));
        } finally {
            db_query("DELETE FROM titles WHERE ref=?", true, ["track3'ref"]);
        }
    }
}
