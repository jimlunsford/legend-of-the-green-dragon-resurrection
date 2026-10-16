<?php

declare(strict_types=1);

namespace Resurrection\Compatibility;

/**
 * PHP 5's sprintf() result for formats PHP 8 refuses.
 *
 * Stored text (taunts, news, master speeches, translations) reaches sprintf() as its format. PHP 5 warned and
 * returned false when a directive had no argument, an argument number was 0 or a width or precision did not fit an
 * int, and silently dropped a directive whose conversion character it did not know (the argument was still used).
 * PHP 8 throws ValueError or ArgumentCountError for all of these. A format PHP 8 accepts is formatted by PHP 8
 * unchanged; only a refused one is re-read with PHP 5.2-5.6's parser (ext/standard/formatted_print.c).
 */
final class LegacySprintf
{
    private const CONVERSIONS = 'bcdeEfFgGosuxX';

    /**
     * @param array<array-key, mixed> $values
     * @param-out string|null $warning PHP 5's warning when the result is false.
     */
    public static function format(string $format, array $values, ?string &$warning = null): string|false
    {
        $warning = null;
        $values = array_values($values);
        try {
            return vsprintf($format, $values);
        } catch (\ValueError | \ArgumentCountError) {
        }
        $legacy = self::rewrite($format, count($values), $warning);
        return $legacy === null ? false : vsprintf($legacy, $values);
    }

    /**
     * The format as PHP 5 read it, with every directive it would format numbered explicitly and every directive it
     * would drop removed, or null (and PHP 5's warning) where PHP 5 returned false.
     */
    public static function rewrite(string $format, int $count, ?string &$warning = null): ?string
    {
        $warning = null;
        $length = strlen($format);
        $at = static fn (int $i): string => $i < $length ? $format[$i] : "\0";
        $out = '';
        $next = 1;
        $i = 0;
        while ($i < $length) {
            if ($format[$i] !== '%') {
                $out .= $format[$i++];
                continue;
            }
            if ($at($i + 1) === '%') {
                $out .= '%%';
                $i += 2;
                continue;
            }
            $i++;
            $modifiers = $width = $precision = '';
            if (ord($at($i)) < 128 && !ctype_alpha($at($i))) {
                $end = $i;
                while (ctype_digit($at($end))) $end++;
                if ($at($end) === '$') {
                    $argument = self::number(substr($format, $i, $end - $i));
                    if ($argument <= 0) {
                        $warning = 'Argument number must be greater than zero';
                        return null;
                    }
                    $i = $end + 1;
                } else {
                    $argument = $next++;
                }
                while (true) {
                    $modifier = $at($i);
                    if (in_array($modifier, [' ', '0', '-', '+'], true)) {
                        $modifiers .= $modifier;
                        $i++;
                    } elseif ($modifier === "'" && $i + 1 < $length) {
                        $modifiers .= "'" . $format[$i + 1];
                        $i += 2;
                    } else {
                        break;
                    }
                }
                if (ctype_digit($at($i))) {
                    $end = $i;
                    while (ctype_digit($at($end))) $end++;
                    $number = self::number(substr($format, $i, $end - $i));
                    if ($number < 0) {
                        $warning = 'Width must be greater than zero and less than ' . 2147483647;
                        return null;
                    }
                    $width = (string)$number;
                    $i = $end;
                }
                if ($at($i) === '.') {
                    $i++;
                    // A '.' without digits set no precision in PHP 5.
                    if (ctype_digit($at($i))) {
                        $end = $i;
                        while (ctype_digit($at($end))) $end++;
                        $number = self::number(substr($format, $i, $end - $i));
                        if ($number < 0) {
                            $warning = 'Precision must be greater than zero and less than ' . 2147483647;
                            return null;
                        }
                        $precision = '.' . $number;
                        $i = $end;
                    }
                }
            } else {
                $argument = $next++;
            }
            if ($argument > $count) {
                $warning = 'Too few arguments';
                return null;
            }
            if ($at($i) === 'l') $i++;
            $conversion = $at($i);
            if ($conversion !== "\0" && str_contains(self::CONVERSIONS, $conversion)) {
                $out .= '%' . $argument . '$' . $modifiers . $width . $precision . $conversion;
            } elseif ($conversion === '%') {
                $out .= '%%';
            }
            $i++;
        }
        return $out;
    }

    /** php_sprintf_getnumber(): the digits' value, or -1 when it does not fit below INT_MAX. */
    private static function number(string $digits): int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') return 0;
        return strlen($digits) > 10 || (int)$digits >= 2147483647 ? -1 : (int)$digits;
    }
}
