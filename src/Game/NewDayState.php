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

    /** Daily consumer accepts the actual shipped mount/Audrey legacy metadata.
     * The validation copy never changes the stored or carried raw effect.
     * @param array<string,mixed> $player
     */
    public static function buffs(mixed $value, array $player): void
    {
        $copy = SpecialtyBuffState::collection($value);
        foreach ($copy as $id => &$buff) {
            if ($id === 'lover' && is_array($buff['wearoff'] ?? null)) {
                $message = $buff['wearoff'];
                if (!array_is_list($message) || count($message) !== 2 || $message[0] !== '`!You miss %s`!.`0' ||
                    !is_string($message[1]) || strlen($message[1]) > 4096) throw new \DomainException('Invalid lover message.');
                $buff['wearoff'] = $message[0]; // Validate the translated message tuple, retain raw state.
            }
            if (array_key_exists('activate', $buff)) {
                $expected = ['mount'=>'offense', 'crazyaudrey'=>'defense'][$id] ?? null;
                if ($expected === null || $buff['activate'] !== $expected) throw new \DomainException('Invalid legacy buff activation.');
                unset($buff['activate']); // Legacy metadata is not used by the modern battle engine.
            }
            if ($id === 'mount' && is_string($buff['rounds'] ?? null)) {
                $buff['rounds'] = self::integer($buff['rounds'], -1, 100000);
            }
        }
        unset($buff);
        ForestBuffState::validate($copy, $player);
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
