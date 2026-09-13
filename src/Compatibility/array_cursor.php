<?php

declare(strict_types=1);

/**
 * Consume one item at the caller's current internal array pointer.
 *
 * Historical form parsers consume pairs, cached query readers consume one row,
 * and nested game loops mutate arrays during traversal. foreach would restart
 * iteration and would not advance this pointer. Keep that contract explicit.
 * Exhaustion returns null: PHP 8.5 warns when destructuring false, while null
 * still terminates the loop and clears the list variables as before.
 * Invalid non-array input is an error, not an empty collection.
 *
 * @param array<array-key, mixed> $items
 * @return array{0: int|string, 1: mixed}|null
 */
function resurrection_array_next(array &$items): ?array
{
    $key = key($items);
    if ($key === null) {
        return null;
    }
    $value = current($items);
    next($items);
    return [$key, $value];
}
