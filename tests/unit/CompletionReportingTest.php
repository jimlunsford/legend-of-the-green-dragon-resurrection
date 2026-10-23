<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompletionReportingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once 'lib/db_optimize.php';
        require_once 'lib/server_url.php';
    }

    private const INNODB_NOTE = 'Table does not support optimize, doing recreate + analyze instead';

    /** @return array<string, string> one OPTIMIZE TABLE result row, as both servers return it */
    private static function row(string $table, string $type, string $text): array
    {
        return ['Table' => "resurrection_test.$table", 'Op' => 'optimize', 'Msg_type' => $type, 'Msg_text' => $text];
    }

    /**
     * Result sets captured from MySQL 8.4.11 and MariaDB 11.4.13 (docs/COMPLETION-REPORTING-20261017.md). The two
     * servers return identical rows except the view error text.
     * @return array<string, array{0: list<array<string, string>>, 1: string, 2: list<string>}>
     */
    public static function capturedResults(): array
    {
        return [
            'InnoDB, both servers' => [[self::row('t', 'note', self::INNODB_NOTE), self::row('t', 'status', 'OK')], 'note', [self::INNODB_NOTE]],
            'MyISAM, both servers' => [[self::row('t', 'status', 'OK')], 'done', []],
            'MyISAM unchanged, both servers' => [[self::row('t', 'status', 'Table is already up to date')], 'done', []],
            'MEMORY or CSV, both servers' => [[self::row('t', 'note', "The storage engine for the table doesn't support optimize")], 'note', ["The storage engine for the table doesn't support optimize"]],
            'View, MySQL 8.4' => [[self::row('v', 'Error', "'resurrection_test.v' is not BASE TABLE"), self::row('v', 'status', 'Operation failed')], 'failed', ["'resurrection_test.v' is not BASE TABLE", 'Operation failed']],
            'View, MariaDB 11.4' => [[self::row('v', 'Error', "'resurrection_test.v' is not of type 'BASE TABLE'"), self::row('v', 'status', 'Operation failed')], 'failed', ["'resurrection_test.v' is not of type 'BASE TABLE'", 'Operation failed']],
            'Missing table, both servers' => [[self::row('m', 'Error', "Table 'resurrection_test.m' doesn't exist"), self::row('m', 'status', 'Operation failed')], 'failed', ["Table 'resurrection_test.m' doesn't exist", 'Operation failed']],
        ];
    }

    /**
     * @param list<array<string, string>> $rows
     * @param list<string> $messages
     */
    #[DataProvider('capturedResults')]
    public function testCapturedResultSetsAreClassified(array $rows, string $status, array $messages): void
    {
        self::assertSame([$status, $messages], resurrection_optimize_classify($rows));
    }

    public function testUnexpectedResultSetsFail(): void
    {
        // An error row is a failure even when a later status says OK; so are no rows, no status, or an unknown row.
        self::assertSame('failed', resurrection_optimize_classify([self::row('t', 'error', 'Disk full'), self::row('t', 'status', 'OK')])[0]);
        self::assertSame(['failed', ['The server returned no result.']], resurrection_optimize_classify([]));
        self::assertSame('failed', resurrection_optimize_classify([['Table' => 't']])[0]);
        self::assertSame('failed', resurrection_optimize_classify([self::row('t', 'status', 'Operation failed')])[0]);
        self::assertSame('failed', resurrection_optimize_classify([self::row('t', 'bogus', 'x'), self::row('t', 'status', 'OK')])[0]);
        // Warnings and info rows beside an OK status are kept as notes.
        self::assertSame(['note', ['Table is marked as crashed']], resurrection_optimize_classify([self::row('t', 'warning', 'Table is marked as crashed'), self::row('t', 'status', 'OK')]));
    }

    public function testReportStatesCountsNotesAndFailures(): void
    {
        $results = ['accounts' => ['note', [self::INNODB_NOTE]], 'bans' => ['note', [self::INNODB_NOTE]],
            'legacy' => ['done', []], 'v' => ['failed', ["'resurrection_test.v' is not BASE TABLE", 'Operation failed']]];
        self::assertSame("Optimized tables: 3 of 4 completed in 1.5 seconds.\nCompleted: legacy\n" .
            'Completed with the note "' . self::INNODB_NOTE . "\": accounts, bans\nFailed: 1\n" .
            "v ('resurrection_test.v' is not BASE TABLE; Operation failed)\n" .
            'The optimization was not recorded as run, so the next maintenance run tries again.',
            resurrection_optimize_report($results, 1.5));
        unset($results['v']);
        self::assertSame("Optimized tables: 3 of 3 completed in 0.25 seconds.\nCompleted: legacy\n" .
            'Completed with the note "' . self::INNODB_NOTE . '": accounts, bans', resurrection_optimize_report($results, 0.25));
    }

    public function testServerUrlValidation(): void
    {
        foreach (['http://localhost/' => 'http://localhost/', 'http://127.0.0.1:8080/game' => 'http://127.0.0.1:8080/game/',
            'https://Game.Example.org/logd/' => 'https://Game.Example.org/logd/', 'http://[::1]:8000/' => 'http://[::1]:8000/',
            'http://example.org' => 'http://example.org/', 'http://example.org/a%20b/' => 'http://example.org/a%20b/'] as $url => $expected) {
            self::assertSame($expected, resurrection_valid_server_url($url), $url);
        }
        foreach (['', 'example.org/', 'ftp://example.org/', 'javascript:alert(1)//', "http://example.org/\r\nBcc: x@example.invalid",
            "http://evil.example\n/", 'http://example.org/ x', 'http://user:pass@example.org/', 'http://example.org/?q=1',
            'http://example.org/#x', 'http://example.org/petition.php?op=submit&x=/a', "http://example.org/'><script>",
            'http://example.org:0/', 'http://example.org:65536/', 'http://-bad.example/', 'http://exa_mple.org/', 'http:///x',
            'http://localhost.', 'http://[nothex]/', 'http://999.1.1.1/x' . str_repeat('a', 300)] as $url) {
            self::assertNull(resurrection_valid_server_url($url), json_encode($url, JSON_THROW_ON_ERROR));
        }
    }
}
