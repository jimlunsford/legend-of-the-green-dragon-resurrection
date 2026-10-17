<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\RamiusState;

final class RamiusStateTest extends TestCase
{
    public function testHistoricalFixedAndPercentageArithmetic(): void
    {
        foreach ([[-6,12,-6],[0,12,0],[-3,12,-3],[-12,12,-12],[-13,12,-12],['-999999999999999999',12,-12],
            ['+3',12,3],['0%',12,0],['-50%',13,-7],['-100%',12,-12],['-101%',12,-12],
            ['+50%',13,7],['100%',0,0],['1%',150,2],['-1%',150,-2],['0%',4294967295,0],['5000000000%',1,50000000]] as [$setting,$pool,$expected]) {
            self::assertSame($expected,RamiusState::adjustment($setting,$pool));
        }
    }
    public function testRejectMalformedSettingsAndOverflow(): void
    {
        foreach (['','NaN','INF','1e2','1.5','50.5%','10%garbage','%10',' 10','10 ','01',true,[],null,'4294967296','429496729600%'] as $value) {
            try { RamiusState::setting($value);self::fail('Accepted malformed resurrection setting'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([[1,4294967296],[1,4294967295],['1%',4294967295],['4294967295%',4294967295]] as [$setting,$pool]) {
            try { RamiusState::adjustment($setting,$pool);self::fail('Accepted overflow'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testPersistedFavorDeadStateDayAndNumericBounds(): void
    {
        $base=['alive'=>'0','hitpoints'=>'0','level'=>'6','soulpoints'=>'0','gravefights'=>'0','deathpower'=>'100',
            'lastnewday'=>'2026-10-04','badguy'=>'','specialinc'=>'','maxhitpoints'=>'100','age'=>'1','resurrections'=>'2'];
        foreach ([100,101,113,10000,4294967295] as $favor) {
            RamiusState::eligible(array_replace($base,['deathpower'=>$favor]),'2026-10-04');
            self::assertTrue(true); // The eligible persisted domain is accepted without normalization.
        }
        foreach ([0,24,99,-1,4294967296,'1e3',[],false] as $favor) {
            try { RamiusState::eligible(array_replace($base,['deathpower'=>$favor]),'2026-10-04');self::fail('Accepted invalid favor'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([['alive'=>1],['hitpoints'=>1],['hitpoints'=>-1],['lastnewday'=>''],['lastnewday'=>'2026-10-03'],
            ['badguy'=>'a:0:{}'],['specialinc'=>'event'],['maxhitpoints'=>0],['maxhitpoints'=>2147483648],
            ['age'=>4294967295],['resurrections'=>4294967295],['soulpoints'=>-1],['gravefights'=>4294967296]] as $patch) {
            try { RamiusState::eligible(array_replace($base,$patch),'2026-10-04');self::fail('Accepted invalid death'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        RamiusState::eligible(array_replace($base,['deathpower'=>0]),'2026-10-04',false);
        self::assertTrue(true);
    }
}
