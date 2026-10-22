<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\GraveyardCombatState as G;
final class GraveyardCombatStateTest extends TestCase
{
    private function player(): array { return ['alive'=>0,'hitpoints'=>0,'level'=>10,'soulpoints'=>50,'deathpower'=>100,'gravefights'=>5]; }
    private function state(): array { return ['enemies'=>[['creatureid'=>'1','creaturename'=>'Shade','creatureweapon'=>'Claws','creaturewin'=>'Win','creaturelose'=>'Lose',
        'creaturelevel'=>10,'creatureattack'=>22,'creaturedefense'=>22*.7,'creaturehealth'=>100,'creatureexp'=>13,'playerstarthp'=>50,'dead'=>false,'istarget'=>true,'diddamage'=>0]],
        'options'=>['type'=>'graveyard','encounter'=>str_repeat('a',32)]]; }
    public function testHistoricalScaleAndUnsignedBounds(): void {
        $p=$this->player();$s=$this->state(); self::assertSame($s,G::read(serialize($s),$p));
        foreach ([1,4,5,10,15,255] as $level) {
            $p['level']=$level;$e=&$s['enemies'][0];$e['creaturelevel']=$level;$e['creatureattack']=9+($level<5?-1:0)+(int)(($level-1)*1.5);
            $e['creaturedefense']=$e['creatureattack']*.7;$e['creaturehealth']=$level*5+50;$e['creatureexp']=(int)(10+round($level/3));
            self::assertSame($s,G::read(serialize($s),$p));
        }
        $p=$this->player(); foreach (['soulpoints','deathpower','gravefights'] as $key) $p[$key]='4294967295'; G::player($p);
        $p['soulpoints']=0;G::player($p);self::assertTrue(true);
    }
    /** A torment 1.1.2's graveyard search stored, after its first round: the whole creatures row, level as text. */
    private function legacy(): array {
        return ['enemies'=>[['creatureid'=>'1','creaturename'=>'Shade','creaturelevel'=>'10','creatureweapon'=>'Claws','creaturelose'=>'Lose','creaturewin'=>'Win',
            'creaturegold'=>'0','creatureexp'=>13,'oldcreatureexp'=>'0','creaturehealth'=>80.0,'creatureattack'=>22,'creaturedefense'=>22*.7,'creatureaiscript'=>'',
            'createdby'=>'Author','forest'=>'0','graveyard'=>'1','dead'=>false,'istarget'=>true,'diddamage'=>1]],'options'=>['type'=>'graveyard','maxattacks'=>'4']];
    }
    public function testLegacyInFlightTormentIsUpgradedOnRead(): void {
        $p=$this->player(); $encoded=serialize($this->legacy());
        $e=$this->legacy()['enemies'][0];
        foreach (['creaturegold','oldcreatureexp','creatureaiscript','createdby','forest','graveyard'] as $key) unset($e[$key]);
        $e['creaturelevel']=10; $e['playerstarthp']=50;
        $expected=['enemies'=>[$e],'options'=>['type'=>'graveyard','maxattacks'=>'4','encounter'=>md5($encoded)]];
        self::assertSame($expected,G::read($encoded,$p));
        self::assertSame($expected,G::read($encoded,$p));
        self::assertSame($expected,G::read(serialize($expected),$p));
        // The session reads soul points from the database as text; the search writes them as an integer.
        self::assertSame($expected,G::read($encoded,['soulpoints'=>'50']+$p));
        $s=$this->legacy(); unset($s['enemies'][0]['diddamage'],$s['enemies'][0]['creatureaiscript']); $encoded=serialize($s);
        $up=G::read($encoded,$p); self::assertSame(0,$up['enemies'][0]['diddamage']); self::assertSame(md5($encoded),$up['options']['encounter']);
        // Upgraded, the torment must still pass every check.
        foreach ([['creatureaiscript'=>'bundled:gypsy-bandit'],['creaturelevel'=>'11'],['creatureattack'=>23],['creaturehealth'=>101],['creatureexp'=>40],['dead'=>true],['reward'=>1],['creaturelevel'=>'1e1']] as $change) {
            $s=$this->legacy(); $s['enemies'][0]=array_replace($s['enemies'][0],$change);
            try { G::read(serialize($s),$p); self::fail('Invalid legacy torment accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([['type'=>'forest'],['type'=>'graveyard','reward'=>1]] as $options) {
            $s=$this->legacy(); $s['options']=$options;
            try { G::read(serialize($s),$p); self::fail('Invalid legacy torment accepted'); } catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testMalformedCombatAndPlayerFailClosed(): void {
        $s=$this->state();$cases=[false,[],['enemies'=>[],'options'=>[]]];
        foreach (['creatureid'=>0,'creaturename'=>[],'creaturehealth'=>0,'creatureattack'=>23,'creaturedefense'=>'15.4','creaturelevel'=>11,'creatureexp'=>24,'playerstarthp'=>-1,'dead'=>true,'istarget'=>false,'diddamage'=>2,'creaturegold'=>1,'creaturegem'=>1,'killedplayer'=>true] as $key=>$v) { $x=$s;$x['enemies'][0][$key]=$v;$cases[]=$x; }
        foreach (['type'=>'forest','encounter'=>'bad','maxattacks'=>0,'didsurprise'=>[],'reward'=>1] as $key=>$v) {$x=$s;$x['options'][$key]=$v;$cases[]=$x;}
        $x=$s;$x['enemies'][]=$x['enemies'][0];$cases[]=$x;
        foreach ([...array_map('serialize',$cases),'broken','O:8:"stdClass":0:{}',serialize($s).'tail'] as $encoded) {
            try { G::read($encoded,$this->player());self::fail('Corrupt torment accepted'); } catch (\DomainException) {self::assertTrue(true);}
        }
        foreach (['soulpoints','deathpower','gravefights','level'] as $key) foreach ([-1,4294967296,'1e2',true,[],null] as $v) {
            $p=$this->player();$p[$key]=$v;
            try {G::player($p);self::fail('Corrupt player accepted');} catch (\DomainException) {self::assertTrue(true);}
        }
        foreach ([['alive'=>1],['hitpoints'=>1],['hitpoints'=>-1]] as $change) {
            try {G::player(array_replace($this->player(),$change));self::fail('Living player accepted');} catch (\DomainException) {self::assertTrue(true);}
        }
    }
}
