<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Normal daily-reset numeric and context rules, never early resurrection. */
final class NewDayState
{
    public static function integer(mixed $value, int $min = 0, int $max = 4294967295): int
    {
        // Historical e_rand returns an integral float when both bounds are equal.
        if ((!is_int($value) && !is_string($value) && !(is_float($value) && is_finite($value) && floor($value) === $value)) || !preg_match('/\A-?(0|[1-9][0-9]*)\z/', (string)$value) ||
            strlen((string)$value) > 11 || (float)$value < $min || (float)$value > $max) {
            throw new \DomainException('Invalid daily integer.');
        }
        return (int)$value;
    }

    public static function rate(mixed $value): float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            !preg_match('/\A-?(0|[1-9][0-9]*)(\.[0-9]+)?\z/', (string)$value) ||
            !is_finite((float)$value) || (float)$value < -100 || (float)$value > 2147483547) {
            throw new \DomainException('Invalid interest setting.');
        }
        return (float)$value;
    }

    public static function interest(mixed $balance, float $rate): int
    {
        $balance = self::integer($balance, -2147483648, 2147483647);
        $result = $balance * $rate;
        if (!is_finite($result) || $rate < 0 || $result < -2147483648 || $result > 2147483647) {
            throw new \DomainException('Invalid interest result.');
        }
        // Historical PDO writes the product as decimal text to an INT column,
        // whose conversion rounds halves away from zero on both supported DBs.
        return (int)round($result, 0, PHP_ROUND_HALF_UP);
    }

    /** Canonicalize equivalent associative records while retaining ordered lists.
     * @return mixed
     */
    public static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as &$item) $item = self::canonical($item);
        return $value;
    }
}
