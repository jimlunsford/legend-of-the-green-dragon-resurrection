<?php

declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../lib/dbwrapper_pdo.php';

final class DatabaseTest extends TestCase
{
    protected function setUp(): void { $GLOBALS['dbinfo'] = []; }

    public function testCachedRowsPreserveCountAndCursor(): void
    {
        $result = [['id' => '1'], ['id' => '2']];
        self::assertSame(2, db_num_rows($result));
        self::assertSame(['id' => '1'], db_fetch_assoc($result));
        self::assertSame(['id' => '2'], db_fetch_assoc($result));
        self::assertFalse(db_fetch_assoc($result));
        self::assertSame(2, db_num_rows($result));
        reset($result);
        self::assertSame(['id' => '1'], db_fetch_assoc($result));
    }

    public function testDsnInjectionIsRejectedAndPriorConnectionCleared(): void
    {
        $GLOBALS['dbinfo']['connection'] = 'old connection';
        self::assertFalse(db_connect('localhost;dbname=unexpected', 'synthetic', 'FAKE-only'));
        self::assertNull($GLOBALS['dbinfo']['connection']);
        self::assertStringNotContainsString('FAKE-only', db_error());
        self::assertFalse(db_connect('localhost:65536', 'synthetic', 'FAKE-only'));
    }

    public function testIdentifiersAreNotTreatedAsValues(): void
    {
        self::assertSame('`fixture_accounts`', db_identifier('fixture_accounts'));
        $this->expectException(\InvalidArgumentException::class);
        db_identifier('fixture.accounts');
    }

    public function testMissingConnectionHasNoSqlDiagnostic(): void
    {
        self::assertFalse(db_query('SELECT FAKE_SECRET_TEXT', false));
        self::assertStringNotContainsString('FAKE_SECRET_TEXT', db_error());
    }

    public function testLiveDatabaseContract(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires the disposable CI database service.'); }
        self::assertTrue(db_connect($host, getenv('RESURRECTION_TEST_DB_USER'), getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db(getenv('RESURRECTION_TEST_DB_NAME')));
        $version = db_get_server_version();
        fwrite(STDERR, "Driver integration database: $version\n");
        $result = db_query('SELECT @@character_set_connection AS charset, @@sql_mode AS modes');
        $row = db_fetch_assoc($result);
        self::assertSame('utf8mb4', $row['charset']);
        self::assertStringContainsString('STRICT_TRANS_TABLES', $row['modes']);
        self::assertTrue(db_query('CREATE TEMPORARY TABLE resurrection_driver_test (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
        $text = "O'Reilly \\ dragon 🐉";
        self::assertTrue(db_query('INSERT INTO resurrection_driver_test (value) VALUES (?)', true, [$text]));
        self::assertSame(1, db_insert_id());
        self::assertSame(1, db_affected_rows());
        $result = db_query('SELECT id, value FROM resurrection_driver_test WHERE id = ?', true, [1]);
        self::assertSame(1, db_num_rows($result));
        self::assertSame(['id' => '1', 'value' => $text], db_fetch_assoc($result));
        self::assertFalse(db_fetch_assoc($result));
        self::assertTrue(db_table_exists('resurrection_driver_test'));
        self::assertFalse(db_table_exists('resurrection_nonexistent_table'));
        self::assertFalse(db_query('SELECT ?; SELECT 2', false, ['FAKE-only']));
        self::assertFalse(db_query('INSERT INTO resurrection_driver_test (value) VALUES (?)', false, [null]));
        self::assertSame('Database operation failed.', db_error());
        $GLOBALS['dbinfo']['connection']->beginTransaction();
        db_query('INSERT INTO resurrection_driver_test (value) VALUES (?)', true, ['rolled back']);
        $GLOBALS['dbinfo']['connection']->rollBack();
        $result = db_query('SELECT COUNT(*) AS total FROM resurrection_driver_test');
        self::assertSame(['total' => '1'], db_fetch_assoc($result));
        self::assertSame($text, substr($GLOBALS['dbinfo']['connection']->query('SELECT ' . $GLOBALS['dbinfo']['connection']->quote($text))->fetchColumn(), 0));
        self::assertTrue(db_query('DROP TEMPORARY TABLE resurrection_driver_test'));
    }
}
