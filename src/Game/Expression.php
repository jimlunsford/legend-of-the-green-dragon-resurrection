<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Finite vocabulary of the expressions actually shipped with the bundled game.
 * No PHP parser, function dispatch, variable interpolation, or mutable callbacks.
 */
final class Expression
{
    /** @param array<string, mixed> $player */
    public static function evaluate(string $expression, array $player = []): float
    {
        if (strlen($expression) > 512) throw new \DomainException('Unsupported game expression.');
        $expression = trim($expression);
        if (str_starts_with($expression, 'debug:')) $expression = substr($expression, 6);
        if (str_starts_with($expression, 'return ')) $expression = substr($expression, 7);
        $expression = rtrim(trim($expression), ';');
        $expression = preg_replace('/\s+/', '', $expression) ?? '';
        if ($expression === 'true') return 1.0;
        if ($expression === 'false') return 0.0;
        if (preg_match('/\A-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)\z/', $expression)) {
            $result = (float)$expression;
            if (is_finite($result)) return $result;
        }
        foreach (['attack', 'defense'] as $stat) {
            if ($expression === '(<' . $stat . '>?(1+((1+floor(<level>/5))/<' . $stat . '>)):0)') {
                $value = self::number($player[$stat] ?? null);
                if ($value == 0) return 0.0; // Retain historical short-circuit division guard.
                $result = 1 + ((1 + floor(self::number($player['level'] ?? null) / 5)) / $value);
                if (is_finite($result)) return $result;
            }
        }
        throw new \DomainException('Unsupported game expression.');
    }

    private static function number(mixed $value): float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || !is_finite((float)$value)) {
            throw new \DomainException('Invalid game expression state.');
        }
        return (float)$value;
    }
}
