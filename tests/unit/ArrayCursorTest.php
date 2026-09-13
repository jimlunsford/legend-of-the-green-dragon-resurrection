<?php

declare(strict_types=1);

namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Compatibility/array_cursor.php';

final class ArrayCursorTest extends TestCase
{
    public function testFalseNullAndZeroAreValuesNotEndMarkers(): void
    {
        $items = ['a' => false, 0 => null, 'zero' => 0, 'empty' => ''];
        self::assertSame(['a', false], resurrection_array_next($items));
        self::assertSame([0, null], resurrection_array_next($items));
        self::assertSame(['zero', 0], resurrection_array_next($items));
        self::assertSame(['empty', ''], resurrection_array_next($items));
        self::assertFalse(resurrection_array_next($items));
        self::assertFalse(resurrection_array_next($items));
    }

    public function testPartialTraversalAndResetUseCallersPointer(): void
    {
        $items = ['label', 'type', 'key', 'value'];
        next($items);
        self::assertSame([1, 'type'], resurrection_array_next($items));
        self::assertSame(2, key($items));
        reset($items);
        self::assertSame([0, 'label'], resurrection_array_next($items));
        self::assertSame([1, 'type'], resurrection_array_next($items));
        self::assertSame([2, 'key'], resurrection_array_next($items));
        self::assertSame([3, 'value'], resurrection_array_next($items));
    }

    public function testMutationAndNestedTraversal(): void
    {
        $items = ['first' => 1, 'second' => 2, 'third' => 3];
        self::assertSame(['first', 1], resurrection_array_next($items));
        unset($items['first']);
        $items['second'] = 20;
        $items['fourth'] = 4;
        self::assertSame(['second', 20], resurrection_array_next($items));
        self::assertSame(['third', 3], resurrection_array_next($items));
        self::assertSame(['fourth', 4], resurrection_array_next($items));
        $outer = [['x' => 1, 'y' => 2], ['z' => 3]];
        $seen = [];
        while ([$key, $inner] = resurrection_array_next($outer)) {
            while ([$innerKey, $value] = resurrection_array_next($inner)) {
                $seen[] = [$key, $innerKey, $value];
            }
        }
        self::assertSame([[0, 'x', 1], [0, 'y', 2], [1, 'z', 3]], $seen);
        self::assertNull(key($outer));
    }

    public function testEmptyArray(): void
    {
        $items = [];
        self::assertFalse(resurrection_array_next($items));
    }

    public function testInvalidInputIsNotSilentlyDiscarded(): void
    {
        $items = false;
        $this->expectException(\TypeError::class);
        resurrection_array_next($items);
    }
}
