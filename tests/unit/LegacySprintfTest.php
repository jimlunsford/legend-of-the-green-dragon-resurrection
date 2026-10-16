<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resurrection\Compatibility\LegacySprintf;

final class LegacySprintfTest extends TestCase
{
    /**
     * PHP 5.6's sprintf() for each format PHP 8 refuses, measured with php:5.6-cli (5.2's parser is the same here).
     * @return array<string, array{string, list<mixed>, string|false, string|null}>
     */
    public static function refused(): array
    {
        return [
            'stray % before a code' => ['100% sure %s', ['Bob'], false, 'Too few arguments'],
            'stray % alone' => ['50%!', [], false, 'Too few arguments'],
            'trailing %' => ['end 100%', [], false, 'Too few arguments'],
            'trailing % with an argument' => ['end 100%', ['x'], 'end 100', null],
            'unknown specifier, used argument' => ['a %y b', ['Bob'], 'a  b', null],
            'unknown specifier, next argument' => ['a %y b %s', ['Bob', 'Al'], 'a  b Al', null],
            'too few arguments' => ['%s hits %s', ['Bob'], false, 'Too few arguments'],
            'no argument at all' => ['%s', [], false, 'Too few arguments'],
            'directive the taunt editor lists' => ['%d of %s', ['Bob'], false, 'Too few arguments'],
            'positional too far' => ['%1$s and %3$s', ['a', 'b'], false, 'Too few arguments'],
            'argument number 0' => ['%0$s', ['a'], false, 'Argument number must be greater than zero'],
            'empty argument number' => ['%$s', ['a'], false, 'Argument number must be greater than zero'],
            'width too large' => ['%2147483648d', [1], false, 'Width must be greater than zero and less than 2147483647'],
            'precision too large' => ['%.2147483647f', [1], false, 'Precision must be greater than zero and less than 2147483647'],
            'unknown with width and precision' => ['%5.2q x', ['a'], ' x', null],
            'lone % with an argument' => ['%', ['a'], '', null],
            'padding quote at the end' => ["%'", [1], '', null],
            'l modifier at the end' => ['%l', [1], '', null],
            'positional then sequential' => ['%2$s %y %s', ['a', 'b'], 'b  b', null],
            'forest pack line (%i)' => ["`2You encounter a group of `^%i`2 %s`2.`n`n", [3, 'Rat'], "`2You encounter a group of `^`2 Rat`2.`n`n", null],
            'multibyte after %' => ['100%é %s', ['x', 'y'], "100\xA9 y", null],
        ];
    }

    /** @param list<mixed> $values */
    #[DataProvider('refused')]
    public function testRefusedFormatsGetPhp5Results(string $format, array $values, string|false $expected, ?string $warning): void
    {
        try {
            vsprintf($format, $values);
            self::fail('PHP 8 formats this; it is not a legacy case.');
        } catch (\ValueError | \ArgumentCountError) {
        }
        self::assertSame($expected, LegacySprintf::format($format, $values, $actual));
        self::assertSame($warning, $actual);
    }

    /**
     * Formats PHP 8 accepts keep PHP 8's result exactly, including the colour code escape and PHP 8-only directives.
     * @return array<string, array{string, list<mixed>}>
     */
    public static function accepted(): array
    {
        return [
            'plain' => ['plain text', []],
            'doubled' => ['100%% sure %s', ['Bob']],
            'colour code' => ['`%%x %s', ['a']],
            'every conversion' => ['%u %c %x %X %o %b %e %E %f %F %g %G %d %s', [1, 65, 255, 255, 8, 5, 1.5, 1.5, 1.5, 1.5, 1.5, 1.5, -3, 'x']],
            'modifiers' => ["%-10s|%+d|%05.1f|%'*8s|% d", ['a', 3, 2.25, 'b', 4]],
            'positional' => ['%2$s %1$s %2$s', ['a', 'b']],
            'extra arguments' => ['%s', ['a', 'b']],
            'precision without digits' => ['%.f', [1.5]],
            'PHP 8 only %h' => ['%h', [1.5]],
            'news line' => ["`%%s`5 has been slain %s by %s.`n%s", ['Bob', 'in the forest', 'Rat', '']],
        ];
    }

    /** @param list<mixed> $values */
    #[DataProvider('accepted')]
    public function testAcceptedFormatsAreUnchanged(string $format, array $values): void
    {
        self::assertSame(vsprintf($format, $values), LegacySprintf::format($format, $values, $warning));
        self::assertNull($warning);
    }

    public function testFormattersUseIt(): void
    {
        require_once 'lib/constants.php';
        require_once 'lib/settings.php';
        require_once 'lib/translator.php';
        require_once 'lib/output.php';
        $saved = [$GLOBALS['session'] ?? null, $GLOBALS['output'] ?? null, $GLOBALS['block_new_output'] ?? null, $GLOBALS['settings'] ?? null];
        try {
            $GLOBALS['session'] = ['user' => ['superuser' => 0, 'prefs' => ['ihavenocheer' => 1]]];
            $GLOBALS['settings'] = ['enabletranslation' => false];
            $GLOBALS['block_new_output'] = false;
            $GLOBALS['output'] = '';
            // A defeat news row whose stored taunt has a stray %: PHP 5 dropped the taunt and kept the line.
            $taunt = ['"100% sure, %s", says the Rat.', 'Bob'];
            self::assertFalse(sprintf_translate(...$taunt));
            self::assertSame('', sprintf_translate($taunt));
            self::assertSame('`%Bob`5 has been slain in the forest by Rat.`n', sprintf_translate('`%%s`5 has been slain %s by %s.`n%s', 'Bob', 'in the forest', 'Rat', $taunt));
            self::assertSame('a  b', sprintf_translate('a %y b', 'Bob'));
            self::assertSame('`%Bob 100%', sprintf_translate('`%%s 100%%', 'Bob'));
            // A master's speech with a stray % and a code (the trailing true skips the holiday hook): PHP 5 printed nothing for it.
            output_notl('You beat me 100%, %s', 'Bob', true);
            output_notl('You beat me %y, %s', 'Bob', 'Al', true);
            output_notl('Unchanged 100%% %s', 'Bob', true);
            self::assertSame("\nYou beat me , Al\nUnchanged 100% Bob\n", $GLOBALS['output']);
        } finally {
            [$GLOBALS['session'], $GLOBALS['output'], $GLOBALS['block_new_output'], $GLOBALS['settings']] = $saved;
        }
    }
}
