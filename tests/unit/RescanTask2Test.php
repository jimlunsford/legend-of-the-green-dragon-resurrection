<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Final rescan task 2: the return links, the bound mount lookup and the name length rule after a dragon kill. */
final class RescanTask2Test extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once 'lib/return_links.php';
        // lib/names.php loads the database wrapper, which installs the application's error handler.
        require_once 'lib/names.php';
        require_once 'lib/settings.php';
        require_once 'lib/datacache.php';
        require_once 'lib/mounts.php';
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function bioReturns(): array
    {
        return [
            'none' => ['', 'list.php'], 'not text' => [['village.php'], 'list.php'], 'false' => [false, 'list.php'],
            'commentary page' => ['/village.php', 'village.php'], 'install folder' => ['/logd/inn.php', 'inn.php'],
            'bare name' => ['clan.php', 'clan.php'], 'comment scroll stripped' => ['/village.php?c=12-34', 'village.php'],
            'list' => ['/list.php', 'list.php'],
            'user editor record' => ['/user.php?op=edit&userid=5', 'user.php?op=edit&userid=5'],
            'user editor other query' => ['/user.php?op=edit&userid=5&returnpetition=3', 'user.php'],
            'petition record' => ['/viewpetition.php?op=view&id=7', 'viewpetition.php?op=view&id=7'],
            'query dropped' => ['/pvp.php?act=attack&name=5', 'pvp.php'],
            'nav bypass through a query' => ['/motd.php?x=/forest.php?op=fight', 'motd.php'],
            'unlisted page' => ['/forest.php?op=search', 'list.php'], 'module' => ['/runmodule.php?module=darkhorse', 'list.php'],
            'javascript' => ['/prefs.php?a=/javascript:alert(1)', 'prefs.php'], 'scheme' => ['javascript:alert(1)', 'list.php'],
            'off site' => ['http://evil.example/village.php', 'list.php'], 'protocol relative' => ['//evil.example/village.php', 'list.php'],
            'backslash' => ['\\\\evil.example\\village.php', 'list.php'], 'quote' => ["/village.php'", 'list.php'],
            'upper case' => ['/Village.php', 'list.php'], 'longer name' => ['/xvillage.php', 'list.php'], 'bio itself' => ['/bio.php?char=1', 'list.php'],
        ];
    }

    #[DataProvider('bioReturns')]
    public function testBioReturnAcceptsOnlyKnownOnSitePages(mixed $ret, string $expected): void
    {
        self::assertSame($expected, resurrection_bio_return($ret));
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function newsReturns(): array
    {
        return [
            'default' => ['/news.php', 'news.php'], 'paging' => ['/news.php?offset=1&page=2', 'news.php?offset=1&page=2'],
            'folder' => ['/logd/news.php?offset=3', 'news.php?offset=3'], 'negative offset' => ['news.php?offset=-1', 'news.php?offset=-1'],
            'nav counter' => ['/news.php?offset=1&c=12-213045', 'news.php?offset=1'], 'other key' => ['/news.php?offset=1&op=x', 'news.php'], 'not a number' => ["/news.php?offset=1'", 'news.php'],
            'other page' => ['/village.php', 'news.php'], 'longer name' => ['/xnews.php', 'news.php'], 'off site' => ['http://evil.example/news.php', 'news.php'],
            'protocol relative' => ['//evil.example/news.php', 'news.php'], 'javascript' => ['javascript:alert(1)', 'news.php'],
            'missing' => [false, 'news.php'], 'array' => [['news.php'], 'news.php'],
        ];
    }

    #[DataProvider('newsReturns')]
    public function testNewsDeleteReturnsOnlyToTheNewsPage(mixed $return, string $expected): void
    {
        self::assertSame($expected, resurrection_news_return($return));
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
    }

    public function testGetmountBindsTheStoredMountId(): void
    {
        $this->database();
        $rows = db_query('SELECT * FROM mounts ORDER BY mountid');
        self::assertGreaterThan(1, count($rows), 'The fresh install ships mounts.');
        $first = $rows[0];
        self::assertSame($first, getmount((int)$first['mountid']));
        self::assertSame($first, getmount((string)$first['mountid']));
        // 1.1.2 pasted the value into `mountid='...'`, so a quote made any condition part of the query.
        foreach ([$first['mountid'] . "' OR '1", "0' OR '1", '-1', 'abc', '1.5', '05', '4294967296', null, [1], 0, '0', 999999999] as $id) {
            self::assertSame([], getmount($id), var_export($id, true));
        }
    }

    public function testNameFitsMeasuresTheAccountColumnsInCharacters(): void
    {
        $this->database();
        self::assertTrue(resurrection_name_fits(str_repeat('é', 60), str_repeat('雪', 25)));
        self::assertFalse(resurrection_name_fits(str_repeat('a', 61), 'Farmboy'));
        self::assertFalse(resurrection_name_fits('Name', str_repeat('a', 26)));
    }
}
