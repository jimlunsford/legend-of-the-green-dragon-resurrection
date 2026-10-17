<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\ForestCombatState;
use Resurrection\Game\ForestBuffState;
use Resurrection\Game\ForestCompanionState;

final class ForestCombatStateTest extends TestCase
{
    private function state(): array
    {
        return ['enemies'=>[['creatureid'=>'1','creaturelevel'=>10,'creaturehealth'=>1000,
            'creatureattack'=>10,'creaturedefense'=>10,'creaturegold'=>10,'creatureexp'=>10,
            'playerstarthp'=>100,'diddamage'=>0,'creaturename'=>'Opponent','creatureweapon'=>'Claws']],
            'options'=>['type'=>'forest','encounter'=>str_repeat('a',32)]];
    }
    public function testProducerShapesAndRewardLedgers(): void
    {
        $state=$this->state();
        self::assertSame($state,ForestCombatState::read(serialize($state)));
        $state['enemies'][0]['creaturename']='An evil doppleganger of Player';
        unset($state['enemies'][0]['creatureid']); // Historical fallback doppleganger.
        self::assertSame($state,ForestCombatState::read(serialize($state)));
        $state['enemies'][0]['creaturehealth']=200;
        self::assertSame($state,ForestCombatState::read(serialize($state)));
        $state['enemies'][]=$state['enemies'][0]+['istarget'=>true];
        $state['enemies'][0]['creaturehealth']=-1;
        $state['enemies'][0]['dead']=true;
        $state['options']['experience']=[10];
        self::assertSame($state,ForestCombatState::read(serialize($state)));
        $state['enemies'][0]['expgained']=true;
        $state['options']['experiencegained']=[5];
        self::assertSame($state,ForestCombatState::read(serialize($state)));
        $state['enemies'][1]['creatureaiscript']='bundled:gypsy-bandit';
        $state['enemies'][1]['spellpoints']=0;
        self::assertSame($state,ForestCombatState::read(serialize($state)));
    }
    public function testInvalidRewardsOptionsAndStructure(): void
    {
        $base=$this->state(); $cases=[[],false,'invalid', ['enemies'=>[],'options'=>['type'=>'forest']]];
        foreach (['creatureid'=>0,'creaturelevel'=>1.5,'creaturehealth'=>false,'creatureexp'=>-1,
            'creaturegold'=>'1e2','creatureattack'=>[],'creaturedefense'=>INF,'dead'=>true,
            'istarget'=>[],'creatureaiscript'=>'arbitrary php','killedplayer'=>true] as $key=>$value) {
            $s=$base;$s['enemies'][0][$key]=$value;$cases[]=$s;
        }
        foreach ([['type'=>'train'],['type'=>'forest','experience'=>[99999]],['type'=>'forest','maxattacks'=>0],
            ['type'=>'forest','encounter'=>'invalid'],['type'=>'forest','unknown'=>1]] as $options) {
            $s=$base;$s['options']=$options;$cases[]=$s;
        }
        foreach ($cases as $s) {
            try { ForestCombatState::read(serialize($s)); self::fail('Accepted invalid authority'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (['','broken','O:8:"stdClass":0:{}',str_repeat('x',1048577)] as $encoded) {
            try { ForestCombatState::read($encoded); self::fail('Accepted invalid serialization'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testBuffConsumerTypesAndExistingProducerOwnership(): void
    {
        $good=['racialbenefit'=>['name'=>'Elven benefit','rounds'=>-1,'atkmod'=>1.1,'schema'=>'module-raceelf']];
        ForestBuffState::validate($good,[]); self::assertTrue(true);
        $companion=['name'=>'Guard','hitpoints'=>10,'maxhitpoints'=>20,'attack'=>2,'defense'=>3,'abilities'=>['fight'=>true]];
        ForestCompanionState::validate(['guard'=>$companion]); self::assertTrue(true);
        foreach (['hitpoints'=>-1,'maxhitpoints'=>0,'attack'=>[],'abilities'=>['fight'=>[]],'unknown'=>true] as $key=>$value) {
            try { ForestCompanionState::validate(['guard'=>array_replace($companion,[$key=>$value])]);self::fail('Accepted malformed companion'); }
            catch (\DomainException) { self::assertTrue(true); }
        }

        foreach ([['rounds'=>1,'atkmod'=>[]],['rounds'=>1,'atkmod'=>'1e2'],['rounds'=>0],
            ['rounds'=>1,'terminal'=>true],['rounds'=>1,'aura'=>[]],['rounds'=>1,'name'=>[]]] as $buff) {
            try { ForestBuffState::validate(['bad'=>$buff],[]);self::fail('Accepted malformed buff'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
