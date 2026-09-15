<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Resurrection\Http\Input;

final class InputTest extends TestCase
{
    public function testRawValuesAndMissingValues(): void
    {
        $raw = "O'Reilly \\ dragon 🐉 <tag> & \"quoted\"";
        $source = ['value' => $raw, 'null' => null];
        self::assertSame($raw, Input::string($source, 'value'));
        self::assertSame($raw, $source['value']);
        self::assertSame('', Input::string($source, 'missing'));
        self::assertSame('fallback', Input::string($source, 'null', 'fallback'));
        self::assertSame(7, Input::integer([], 'id', 7));
        self::assertSame(42, Input::integer(['id' => '42'], 'id', 0, 1));
        self::assertFalse(Input::boolean(['flag' => 'false'], 'flag'));
        self::assertTrue(Input::boolean(['flag' => '1'], 'flag'));
        self::assertSame('login', Input::choice([], 'op', ['login', 'logout'], 'login'));
    }
    /** @return list<array{mixed}> */
    public static function invalidStrings(): array { return [[[]], [['x']], [false], [1], [new \stdClass()]]; }
    #[DataProvider('invalidStrings')]
    public function testScalarBoundaryRejectsMalformedValues(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Input::string(['name' => $value], 'name');
    }
    /** @return list<array{string}> */
    public static function invalidIntegers(): array { return [['1 OR 1=1'], ['1.0'], ['1e2'], [' 1'], ['01'], ['-1'], ['9999999999999999999999999']]; }
    #[DataProvider('invalidIntegers')]
    public function testStrictIntegerBoundary(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Input::integer(['id' => $value], 'id', 0, 1);
    }
    public function testBooleanDoesNotUseLooseTruthiness(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Input::boolean(['flag' => 'yes please'], 'flag');
    }
    public function testEnumRejectsUnknownValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Input::choice(['op' => 'destroy'], 'op', ['login', 'logout'], 'login');
    }
}
