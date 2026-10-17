<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Exact, bounded JSON schema for the bundled Stones game. */
final class StonesState
{
    /** @return array<string, int|string> */
    public static function decode(string $encoded): array
    {
        if ($encoded === '') { return []; }
        if (strlen($encoded) > 512) { throw new \DomainException('Invalid Stones state.'); }
        try {
            $state = json_decode($encoded, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \DomainException('Invalid Stones state.');
        }
        if (!is_array($state)) { throw new \DomainException('Invalid Stones state.'); }
        self::validate($state);
        return $state;
    }

    /** @param array<string, mixed> $state */
    public static function encode(array $state): string
    {
        self::validate($state);
        return json_encode($state, JSON_THROW_ON_ERROR);
    }

    /** @param array<array-key, mixed> $state */
    private static function validate(array $state): void
    {
        if ($state === []) { return; }
        $limits = ['red'=>6, 'blue'=>10, 'player'=>16, 'oldman'=>16];
        if (array_diff(array_keys($state), [...array_keys($limits), 'side', 'bet']) !== []) {
            throw new \DomainException('Invalid Stones state.');
        }
        foreach ($limits as $key => $max) {
            if (!isset($state[$key]) || !is_int($state[$key]) || $state[$key] < 0 || $state[$key] > $max) {
                throw new \DomainException('Invalid Stones state.');
            }
        }
        if ($state['red'] + $state['blue'] + $state['player'] + $state['oldman'] !== 16 ||
            $state['player'] % 2 !== 0 || $state['oldman'] % 2 !== 0 ||
            (isset($state['side']) && !in_array($state['side'], ['likepair', 'unlikepair'], true)) ||
            (isset($state['bet']) && (!isset($state['side']) || !is_int($state['bet']) || $state['bet'] < 1 || $state['bet'] > 2147483647))) {
            throw new \DomainException('Invalid Stones state.');
        }
    }
}
