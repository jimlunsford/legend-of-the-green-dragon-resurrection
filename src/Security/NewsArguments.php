<?php
declare(strict_types=1);
namespace Resurrection\Security;

require_once __DIR__ . '/ScalarState.php';

/**
 * news.arguments: the sprintf arguments addnews() stores for a news line. Each one is a scalar, or a one-level
 * translation envelope (a taunt: [true, 'taunts', format, args...]) that sprintf_translate() renders on its own.
 * Structure and size only: the number of format directives is not compared with the arguments, because 1.1.2
 * taunts with a stray % must still render.
 */
final class NewsArguments
{
    public const MAX_ITEMS = 64;
    /** news.arguments is TEXT. */
    public const MAX_BYTES = 65535;

    /**
     * A stored value. The empty string (or NULL) is 1.1.2's "no arguments".
     * @return list<scalar|null|non-empty-list<scalar|null>>
     */
    public static function read(mixed $encoded): array
    {
        if ($encoded === '' || $encoded === null) return [];
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES) throw new \DomainException('Invalid news arguments.');
        return self::arguments(ScalarState::read($encoded));
    }

    /** @param array<array-key, mixed> $arguments */
    public static function encode(array $arguments): string
    {
        $encoded = serialize(self::arguments($arguments));
        if (strlen($encoded) > self::MAX_BYTES || ScalarState::read($encoded) !== $arguments) {
            throw new \DomainException('News arguments are too large.');
        }
        return $encoded;
    }

    /** @return list<scalar|null|non-empty-list<scalar|null>> */
    public static function arguments(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_ITEMS) {
            throw new \DomainException('Invalid news arguments.');
        }
        foreach ($value as $argument) {
            if (is_array($argument)) self::envelope($argument);
            elseif (!self::scalar($argument)) throw new \DomainException('Invalid news argument.');
        }
        return $value;
    }

    /** @param array<array-key, mixed> $envelope */
    private static function envelope(array $envelope): void
    {
        if ($envelope === [] || !array_is_list($envelope) || count($envelope) > self::MAX_ITEMS) {
            throw new \DomainException('Invalid news translation.');
        }
        foreach ($envelope as $item) {
            if (!self::scalar($item)) throw new \DomainException('Invalid news translation argument.');
        }
        // sprintf_translate()'s own switch: a leading true names the translation schema, then the format follows.
        $format = 0;
        if (is_bool($envelope[0])) {
            if ($envelope[0] !== true || count($envelope) < 3 || !is_string($envelope[1])) {
                throw new \DomainException('Invalid news translation schema.');
            }
            $format = 2;
        }
        if ($envelope[$format] === null || is_bool($envelope[$format])) throw new \DomainException('Invalid news translation format.');
    }

    private static function scalar(mixed $value): bool
    {
        return $value === null || is_bool($value) || is_int($value) || is_string($value) || (is_float($value) && is_finite($value));
    }
}
