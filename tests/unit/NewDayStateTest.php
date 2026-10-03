<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\NewDayState;

final class NewDayStateTest extends TestCase
{
    public function testHistoricalBankRoundingAndDebt(): void
    {
        foreach ([[101,1.1,111],[-101,1.1,-111],[105,1.1,116],[-105,1.1,-116],[0,1.1,0],[2147483647,1.0,2147483647]] as [$balance,$rate,$expected]) {
            self::assertSame($expected,NewDayState::interest($balance,$rate));
        }
    }
    public function testRejectInvalidArithmetic(): void
    {
        foreach ([[2147483647,1.1],[-2147483648,1.1],[1,INF],[1,NAN],[1,-1.0],['1.5',1.1],['bad',1.1]] as [$balance,$rate]) {
            try { NewDayState::interest($balance,$rate); self::fail('Accepted invalid bank arithmetic'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testShippedLoverMessageTupleIsValidatedWithoutMutation(): void
    {
        $buff = ['name'=>"`!Lover's Protection",'rounds'=>60,'wearoff'=>['`!You miss %s`!.`0','Violet'],
            'defmod'=>1.2,'roundmsg'=>'Your lover inspires you to keep safe!','schema'=>'module-lovers'];
        $original = $buff;
        NewDayState::buffs(['lover'=>$buff], []);
        self::assertSame($original, $buff);
        foreach ([[], ['wrong','Violet'], ['`!You miss %s`!.`0',[]], ['`!You miss %s`!.`0','Violet','extra']] as $message) {
            try { NewDayState::buffs(['lover'=>array_replace($buff,['wearoff'=>$message])],[]);self::fail('Accepted malformed message tuple'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testNumericAuthorityAndCanonicalRecords(): void
    {
        foreach (['NaN','1e5','bad',[],false,'-101',INF] as $value) {
            try { NewDayState::rate($value);self::fail('Accepted invalid setting'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (['1.5','+1','01',true,[],4294967296,-1] as $value) {
            try { NewDayState::integer($value);self::fail('Accepted invalid integer'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        self::assertSame(NewDayState::canonical(['b'=>['z'=>1,'a'=>2],'a'=>3]),NewDayState::canonical(['a'=>3,'b'=>['a'=>2,'z'=>1]]));
        self::assertNotSame(NewDayState::canonical(['ff','hp']),NewDayState::canonical(['hp','ff']));
    }
}
