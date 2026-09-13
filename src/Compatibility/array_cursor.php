<?php

declare(strict_types=1);

/**
 * Consume one item at the caller's current internal array pointer.
 *
 * Historical form parsers consume pairs, cached query readers consume one row,
 * and nested game loops mutate arrays during traversal. foreach would restart
 * iteration and would not advance this pointer. Keep that contract explicit.
 * Invalid non-array input is an error, not an empty collection.
 *
 * @param array<array-key, mixed> $items
 * @return array{0: int|string, 1: mixed}|false
 */
function resurrection_array_next(array &$items): array|false
{
    $key = key($items);
    if ($key === null) {
        return false;
    }
    $value = current($items);
    next($items);
    return [$key, $value];
}
