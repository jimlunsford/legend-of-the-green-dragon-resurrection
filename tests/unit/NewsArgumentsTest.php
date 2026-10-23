<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\NewsArguments;

final class NewsArgumentsTest extends TestCase
{
    public function testHistoricalArgumentsAndTauntEnvelopes(): void
    {
        self::assertSame([], NewsArguments::read(''));
        self::assertSame([], NewsArguments::read(null));
        self::assertSame([], NewsArguments::read('a:0:{}'));
        $taunt = [true, 'taunts', '`5"`6%s\'s mother wears combat boots`5", screams %s.', 'Joe', 'Hank'];
        $stored = ["`2O'Reilly \\ 雪", 'the Inn', 12, 1.5, null, false, $taunt, ['"Your mother smelt of elderberries," taunted %s.', 'Joe']];
        self::assertSame($stored, NewsArguments::read(serialize($stored)));
        self::assertSame(serialize($stored), NewsArguments::encode($stored));
        // A stray % stays storable and readable: the format count is not checked.
        $stray = [true, 'taunts', '100% %s %', 'Joe'];
        self::assertSame([$stray], NewsArguments::read(NewsArguments::encode([$stray])));
        self::assertSame(['only'], NewsArguments::read(serialize(['only'])));
        $wide = array_fill(0, NewsArguments::MAX_ITEMS, 'x');
        self::assertSame($wide, NewsArguments::read(serialize($wide)));
    }

    public function testMalformedStoredArgumentsAreRefused(): void
    {
        foreach (['broken', 'b:0;', 's:3:"abc";', 'i:5;', serialize([1 => 'x']), serialize(['a' => 'x']), serialize([['x', ['nested']]]),
            serialize([[]]), serialize([[1 => 'x']]), serialize([[true]]), serialize([[true, 'taunts']]), serialize([[false, 'taunts', 'x']]),
            serialize([[true, 5, 'x']]), serialize([[null, 'x']]), serialize([[true, 'taunts', null]]), serialize([[true, 'taunts', false]]),
            serialize(array_fill(0, NewsArguments::MAX_ITEMS + 1, 'x')), serialize([array_fill(0, NewsArguments::MAX_ITEMS + 1, 'x')]),
            serialize([str_repeat('x', NewsArguments::MAX_BYTES)]), 'O:8:"stdClass":0:{}', 'a:1:{i:0;d:NAN;}', 5, false, []] as $encoded) {
            try { NewsArguments::read($encoded); self::fail('Malformed arguments accepted: ' . var_export($encoded, true)); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testWriterRefusesWhatNewsCannotRender(): void
    {
        foreach ([[1 => 'x'], [new \stdClass()], [['x', ['nested']]], [[]], [[true, 'taunts']], [INF], [str_repeat('x', NewsArguments::MAX_BYTES)],
            array_fill(0, NewsArguments::MAX_ITEMS + 1, 'x')] as $arguments) {
            try { NewsArguments::encode($arguments); self::fail('Unrenderable arguments stored.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
