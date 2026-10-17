<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\DragonPointState as DP;

final class DragonPointStateTest extends TestCase
{
    private function schema(): array { return ['desc'=>['hp'=>'HP','at'=>'Attack','de'=>'Defense','ff'=>'Fights','unknown'=>'Unknown'], 'buy'=>['hp'=>1,'at'=>1,'de'=>1,'ff'=>1,'unknown'=>0]]; }
    private function player(): array { return ['dragonpoints'=>[], 'dragonkills'=>'5', 'maxhitpoints'=>'100', 'attack'=>'10', 'defense'=>'20']; }

    public function testHistoricalEffectsAndVocabulary(): void {
        $counts=DP::counts(['hp'=>'2','at'=>'1','de'=>'1','ff'=>'1'], DP::schema($this->schema())['buy'],5);
        $actual=DP::allocate($this->player(),$counts);
        self::assertSame(110,$actual['maxhitpoints']); self::assertSame(11,$actual['attack']); self::assertSame(21,$actual['defense']);
        self::assertSame(['at','de','ff','hp','hp'],$actual['dragonpoints']);
        self::assertSame(1,count(array_filter($actual['dragonpoints'],static fn($p)=>$p==='ff')));
        self::assertSame(['retired_module','unknown'],DP::read(serialize(['retired_module','unknown']),2));
        self::assertSame(4294967295,DP::integer('4294967295'));
    }

    public function testStoredCorruptionAndIntegerDomain(): void {
        foreach (['broken','b:0;',serialize(['hp',null]),serialize(['hp',1]),serialize(['hp',[]]),serialize(['hp',true]),
            serialize([1=>'hp']),serialize(['<script>']),serialize(['']),serialize(['hp','at'])] as $encoded) {
            try { DP::read($encoded,1); self::fail('Corrupt history accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([-1,'-1',1.0,true,null,'','+1','01','1e2','1junk','4294967296',str_repeat('9',100)] as $kills) {
            try { DP::read('a:0:{}',$kills); self::fail('Invalid kill authority accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testExactTotalsAndInputVocabulary(): void {
        $buy=DP::schema($this->schema())['buy']; $base=['hp'=>'2','at'=>'1','de'=>'1','ff'=>'1'];
        foreach ([-1,'-1',1.5,[],true,null,'','+2','02','2.0','2e0','2junk','4294967296','999999999999999999999','0','3'] as $value) {
            try { DP::counts(array_replace($base,['hp'=>$value]),$buy,5); self::fail('Bad count accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        foreach (['unknown','forged','module'] as $key) {
            try { DP::counts($base+[$key=>'0'],$buy,5); self::fail('Undeclared key accepted'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        unset($base['ff']);
        $this->expectException(\InvalidArgumentException::class); DP::counts($base,$buy,5);
    }

    public function testOverflowAndPersistedCollectionCapacity(): void {
        foreach (['maxhitpoints'=>'hp','attack'=>'at','defense'=>'de'] as $stat=>$type) {
            $p=$this->player(); $p['dragonkills']=1; $p[$stat]=DP::UINT_MAX;
            try { DP::allocate($p,[$type=>1]); self::fail('Overflow accepted'); } catch (\DomainException) { self::assertTrue(true); }
            $p[$stat]-=$type==='hp'?5:1;
            self::assertSame(DP::UINT_MAX,DP::allocate($p,[$type=>1])[$stat]);
        }
        foreach ([10000,4294967295,5000] as $kills) {
            $p=$this->player(); $p['dragonkills']=$kills;
            try { DP::allocate($p,['hp'=>$kills]); self::fail('Unpersistable list accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testCanonicalServerDeclarations(): void {
        $schema=$this->schema();$reversed=['buy'=>array_reverse($schema['buy'],true),'desc'=>array_reverse($schema['desc'],true)];
        self::assertSame(DP::schema($schema),DP::schema($reversed));
        $schema['desc']['module_bonus']='Bonus';$schema['buy']['module_bonus']=true;
        self::assertTrue(DP::schema($schema)['buy']['module_bonus']);
        foreach (['unknown','csrf_token','bad-key'] as $key) {
            $s=$this->schema();$s['desc'][$key]='Bad';$s['buy'][$key]=1;
            try { DP::schema($s); self::fail('Invalid buyability accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testAmbiguousTransportRejected(): void {
        DP::transport('hp=1&at=0',['at'=>'0','hp'=>'1']); self::assertTrue(true);
        foreach (['hp=1&hp=1','hp=1&%68p=1','hp[]=1','h.p=1','hp%00=1','hp','hp=1;at=0'] as $body) {
            try { DP::transport($body,['hp'=>'1']); self::fail('Ambiguous body accepted'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
}
