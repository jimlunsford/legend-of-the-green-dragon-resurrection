<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 2 (B1): MySQL 8 removed `GROUP BY ... ASC` and `GROUP BY ... DESC` (a syntax error there; MariaDB still
 * takes it). 1.1.2's stats.php used it twice, so its referer and graph pages were a 500 on MySQL 8.4. No shipped PHP may
 * order inside a GROUP BY; an ORDER BY sets the order.
 */
final class GroupByOrderTest extends TestCase
{
    /** A GROUP BY clause that reaches ASC or DESC before the clause ends. */
    private const ORDERED_GROUP = '/GROUP\s+BY(?:(?!ORDER\s+BY|HAVING|LIMIT|;|\bUNION\b|WITH\s+ROLLUP).){0,300}?\b(?:ASC|DESC)\b/is';

    public function testThePatternFindsWhatMysql8Refuses(): void
    {
        // 1.1.2's two lines, as stats.php had them.
        foreach (['GROUP BY " . db_prefix("accounts") . ".referer DESC ORDER BY c DESC"',
            'FROM " . db_prefix("accounts") . " GROUP BY d DESC ORDER BY d DESC"', "GROUP BY a,\n b ASC LIMIT 5"] as $sql) {
            self::assertMatchesRegularExpression(self::ORDERED_GROUP, $sql);
        }
        foreach (['GROUP BY d ORDER BY d DESC', 'GROUP BY clanrank ORDER BY clanrank DESC', 'GROUP BY uri ORDER BY uri ASC',
            "GROUP BY site ORDER BY \$order LIMIT 100", 'GROUP BY status"; $x = "ORDER BY a DESC'] as $sql) {
            self::assertDoesNotMatchRegularExpression(self::ORDERED_GROUP, $sql);
        }
    }

    public function testNoShippedPhpOrdersInsideAGroupBy(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $files = SqlInterpolationScanner::shippedFiles($root);
        self::assertContains('stats.php', $files);
        foreach ($files as $file) {
            $text = file_get_contents($root . '/' . $file);
            self::assertIsString($text, $file);
            if (preg_match_all(self::ORDERED_GROUP, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $found[] = $file . ':' . (substr_count($text, "\n", 0, $offset) + 1) . ': ' . $match;
                }
            }
        }
        self::assertSame([], $found, 'GROUP BY ... ASC/DESC is a syntax error on MySQL 8: drop it and keep the ORDER BY');
    }
}
