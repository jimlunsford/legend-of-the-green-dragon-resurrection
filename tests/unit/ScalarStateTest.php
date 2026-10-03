<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\ScalarState;
use Resurrection\Game\StonesState;

final class StateObjectProbe
{
    public static bool $invoked = false;
    public function __wakeup(): void { self::$invoked = true; }
    public function __unserialize(array $data): void { self::$invoked = true; }
}

final class ScalarStateTest extends TestCase
{
    public function testHistoricalScalarsAndArraysRoundTrip(): void
    {
        foreach ([[], ['name'=>"`2O'Reilly \\ 🐉", 'buff'=>['rounds'=>10,'atkmod'=>0.75]], true, false, null, 32, 'text'] as $value) {
            self::assertSame($value, ScalarState::read(serialize($value)));
        }
    }

    public function testMaliciousObjectsReferencesAndMalformedBlobsAreRejected(): void
    {
        $cycle = []; $cycle['self'] =& $cycle;
        foreach ([serialize(new StateObjectProbe()), serialize(['nested'=>new StateObjectProbe()]),
            'O:8:"stdClass":0:{}', 'C:8:"stdClass":0:{}', serialize($cycle),
            'a:999999999:{', 'a:1:{i:0;s:2:"a";}', 'a:0:{}junk', str_repeat('x',1048577),
            serialize(array_fill(0,10001,1)), serialize(INF), serialize(NAN)] as $payload) {
            self::assertFalse(ScalarState::read($payload));
        }
        self::assertFalse(StateObjectProbe::$invoked);
    }

    public function testStonesExactSchemaAndConservation(): void
    {
        self::assertSame([], StonesState::decode(''));
        foreach ([[], ['red'=>6,'blue'=>10,'player'=>0,'oldman'=>0],
            ['red'=>2,'blue'=>4,'player'=>6,'oldman'=>4,'side'=>'likepair','bet'=>100]] as $state) {
            self::assertSame($state, StonesState::decode(StonesState::encode($state)));
        }
        foreach (['O:8:"stdClass":0:{}', 'a:0:{}', 'null', '1', '{"red":-1}',
            '{"red":6,"blue":10,"player":2,"oldman":0}',
            '{"red":6,"blue":10,"player":0,"oldman":0,"bet":10}',
            '{"red":6,"blue":10,"player":0,"oldman":0,"side":"forged"}',
            '{"red":6,"blue":10,"player":0,"oldman":0,"extra":0}',
            '{"red":6,"blue":10,"player":0,"oldman":0,"side":"likepair","bet":-1}',
            '{"red":"6","blue":10,"player":0,"oldman":0}'] as $payload) {
            try { StonesState::decode($payload); self::fail('Accepted invalid Stones state'); }
            catch (\DomainException $error) { self::assertSame('Invalid Stones state.', $error->getMessage()); }
        }
        self::assertFalse(StateObjectProbe::$invoked);
    }
}
