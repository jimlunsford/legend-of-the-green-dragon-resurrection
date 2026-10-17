<?php
declare(strict_types=1);
namespace Resurrection\Http;

/** Raw input access. Encoding belongs at the SQL/HTML destination, never here. */
final class Input
{
    /** @param array<string, mixed> $source */
    public static function string(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Expected a string parameter: ' . $key);
        }
        return $value;
    }

    /** @param array<string, mixed> $source */
    public static function integer(array $source, string $key, int $default = 0, int $minimum = 0): int
    {
        if (!isset($source[$key])) { return $default; }
        $value = self::string($source, $key);
        if (!preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value)) {
            throw new \InvalidArgumentException('Expected an integer parameter: ' . $key);
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $minimum) {
            throw new \InvalidArgumentException('Integer parameter out of range: ' . $key);
        }
        return $number;
    }

    /** @param array<string, mixed> $source */
    public static function boolean(array $source, string $key, bool $default = false): bool
    {
        if (!isset($source[$key])) { return $default; }
        return match (self::string($source, $key)) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => throw new \InvalidArgumentException('Expected a boolean parameter: ' . $key),
        };
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $allowed
     */
    public static function choice(array $source, string $key, array $allowed, string $default): string
    {
        $value = self::string($source, $key, $default);
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid parameter choice: ' . $key);
        }
        return $value;
    }
}
