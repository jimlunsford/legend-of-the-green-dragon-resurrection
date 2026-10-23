<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Final rescan task 3: no request value reaches SQL unbound. Every value still put into SQL text in shipped PHP must be on
 * the reviewed list in `docs/RESCAN-TASK3-20261023.md` (file, value, number of sites, class, reason). A new interpolated
 * value, or another site of a listed one, fails here until it is bound or reviewed onto the list.
 */
final class SqlSweepTest extends TestCase
{
    private const DOC = 'docs/RESCAN-TASK3-20261023.md';
    private const CLASSES = ['server', 'unreachable', 'not SQL'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, int> "file\tvalue" => sites */
    private static function scanned(): array
    {
        $found = [];
        foreach (SqlInterpolationScanner::shippedFiles(self::root()) as $file) {
            foreach (SqlInterpolationScanner::scanFile(self::root(), $file) as $site) {
                if (preg_match(SqlInterpolationScanner::BY_RULE, $site['value'])) continue;
                $key = $site['file'] . "\t" . $site['value'];
                $found[$key] = ($found[$key] ?? 0) + 1;
            }
        }
        ksort($found);
        return $found;
    }

    /** @return array<string, array{sites: int, class: string, why: string}> */
    private static function listed(): array
    {
        $text = file_get_contents(self::root() . '/' . self::DOC);
        self::assertIsString($text);
        $start = strpos($text, "<!-- sql-sweep-table -->");
        $end = strpos($text, "<!-- /sql-sweep-table -->");
        self::assertNotFalse($start, 'The reviewed list is missing its start marker.');
        self::assertNotFalse($end, 'The reviewed list is missing its end marker.');
        $listed = [];
        foreach (explode("\n", substr($text, $start, $end - $start)) as $line) {
            if (!str_starts_with($line, '| `')) continue;
            $cells = array_map(static fn (string $cell): string => str_replace('\\|', '|', trim($cell)),
                preg_split('/(?<!\\\\)\|/', trim($line, " |")) ?: []);
            self::assertCount(5, $cells, $line);
            [$file, $value, $sites, $class, $why] = $cells;
            $key = trim($file, '`') . "\t" . preg_replace('/\A`+ ?| ?`+\z/', '', $value);
            self::assertArrayNotHasKey($key, $listed, "Listed twice: $line");
            self::assertMatchesRegularExpression('/\A[1-9][0-9]*\z/', $sites, $line);
            $listed[$key] = ['sites' => (int)$sites, 'class' => $class, 'why' => $why];
        }
        ksort($listed);
        return $listed;
    }

    public function testEveryValueInterpolatedIntoSqlIsOnTheReviewedList(): void
    {
        $found = self::scanned();
        $listed = array_map(static fn (array $entry): int => $entry['sites'], self::listed());
        $unlisted = array_diff_key($found, $listed);
        $stale = array_diff_key($listed, $found);
        $changed = [];
        foreach (array_intersect_key($found, $listed) as $key => $sites) {
            if ($listed[$key] !== $sites) $changed[] = "$key: listed {$listed[$key]}, found $sites";
        }
        self::assertSame([], array_keys($unlisted), 'Interpolated into SQL and not reviewed: bind the value, or review it onto the list in ' . self::DOC);
        self::assertSame([], array_keys($stale), 'Listed but no longer interpolated: remove from ' . self::DOC);
        self::assertSame([], $changed, 'The number of sites changed: review the new sites');
    }

    public function testEachListedValueHasAClassAndAReason(): void
    {
        foreach (self::listed() as $key => $entry) {
            // A request value has no class here: it is bound, never listed.
            self::assertContains($entry['class'], self::CLASSES, $key);
            self::assertGreaterThan(10, strlen($entry['why']), $key);
        }
    }

    public function testTheScannerFindsInterpolatedValuesAndIgnoresBoundOnes(): void
    {
        $root = sys_get_temp_dir() . '/sql-sweep-' . bin2hex(random_bytes(4));
        mkdir($root);
        try {
            file_put_contents("$root/page.php", <<<'PHP'
                <?php
                db_query("SELECT * FROM " . db_prefix("accounts") . " WHERE login='" . httpget('name') . "'");
                $sql = "UPDATE " . db_prefix("accounts") . " SET gold=gold+$amount WHERE acctid={$session['user']['acctid']}";
                db_query("SELECT * FROM " . db_prefix("accounts") . " WHERE login=? LIMIT 1", true, [httpget('name')]);
                $link = "village.php?op=" . $op;
                PHP);
            $values = array_column(SqlInterpolationScanner::scanFile($root, 'page.php'), 'value');
            self::assertSame(["httpget('name')", '$amount', "\$session['user']['acctid']"],
                array_values(array_filter($values, static fn (string $value): bool => !preg_match(SqlInterpolationScanner::BY_RULE, $value))));
        } finally {
            @unlink("$root/page.php");
            @rmdir($root);
        }
    }
}
