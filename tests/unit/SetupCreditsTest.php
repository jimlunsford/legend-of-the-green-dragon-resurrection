<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The web setup page's credits (Phase 6 task 5b). setup.php does not load common.php, so lib/setup/credits.php carries
 * the copyright line and the license block. They must stay common.php's, byte for byte with their notices, and the
 * footer's values (read by page_footer()'s copyright checks) must be set as common.php sets them.
 */
final class SetupCreditsTest extends TestCase
{
    /** From the first "**** NOTICE ****" to the end of the license's closing notice. */
    private static function block(string $file): string
    {
        $source = (string)file_get_contents($file);
        $start = strpos($source, "// **** NOTICE ****\n");
        self::assertNotFalse($start, $file);
        $marker = "// Please see the file LICENSE for a full textual description of the license.\n";
        $first = strpos($source, $marker, $start);
        self::assertNotFalse($first, $file);
        $second = strpos($source, $marker, $first + strlen($marker));
        self::assertNotFalse($second, $file);
        return substr($source, $start, $second + strlen($marker) - $start);
    }

    /** The string literals a file assigns to the credit variables, as written in its source. */
    private static function values(string $file): array
    {
        $tokens = token_get_all((string)file_get_contents($file));
        $values = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE || !in_array($token[1], ['$copyright', '$license', '$y2', '$z2'], true)) continue;
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if ($tokens[$j] !== '=') continue;
            $j++;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                $values[substr($token[1], 1)] = $tokens[$j][1];
            }
        }
        return $values;
    }

    public function testCreditsAreCommonPhpsByteForByte(): void
    {
        $common = self::block('common.php');
        self::assertSame($common, self::block('lib/setup/credits.php'));
        self::assertStringContainsString('$copyright = "Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team";', $common);
        self::assertStringContainsString('$license = "\n<!-- Creative Commons License -->', $common);
        $values = self::values('lib/setup/credits.php');
        self::assertSame(self::values('common.php'), $values);
        self::assertSame(['copyright', 'license', 'y2', 'z2'], array_keys($values));
    }

    public function testFooterValuesAreSetAsCommonPhpSetsThem(): void
    {
        $credits = (string)file_get_contents('lib/setup/credits.php');
        $common = (string)file_get_contents('common.php');
        foreach (['$cp = $copyright;', '$l = $license;', '$y2 = "\xc0\x3e\xfe\xb3\x4\x74\x9a\x7c\x17";', '$z2 = "\xa3\x51\x8e\xca\x76\x1d\xfd\x14\x63";', '$x = 0;', '$lc = $l;'] as $line) {
            self::assertStringContainsString("\n" . $line . "\n", $common, $line);
            self::assertStringContainsString("\n" . $line . "\n", $credits, $line);
        }
        // Loaded at setup.php's top level, as common.php's globals are.
        self::assertMatchesRegularExpression("/^require 'lib\/setup\/credits.php';$/m", (string)file_get_contents('setup.php'));
    }
}
