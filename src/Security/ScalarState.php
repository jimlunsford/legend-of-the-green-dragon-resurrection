<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Compatibility reader for historical scalar/array data, never PHP objects. */
final class ScalarState
{
    public static function read(mixed $encoded): mixed
    {
        if (!is_string($encoded) || $encoded === '' || strlen($encoded) > 1048576) {
            return false;
        }
        set_error_handler(static function (): never {
            throw new \UnexpectedValueException('Invalid stored state.');
        });
        try {
            $value = unserialize($encoded, ['allowed_classes' => false, 'max_depth' => 32]);
            $remaining = 10000;
            if (!self::valid($value, 0, $remaining) || serialize($value) !== $encoded) {
                return false;
            }
            return $value;
        } catch (\Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    private static function valid(mixed $value, int $depth, int &$remaining): bool
    {
        if (--$remaining < 0 || $depth > 32) { return false; }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::valid($item, $depth + 1, $remaining)) { return false; }
            }
            return true;
        }
        return $value === null || is_bool($value) || is_int($value) || is_string($value) ||
            (is_float($value) && is_finite($value));
    }
}
