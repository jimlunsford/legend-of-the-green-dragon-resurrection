<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\TrainingCombatState;

final class TrainingCombatStateTest extends TestCase
{
    private function player(): array { return ['level'=>1,'maxhitpoints'=>10,'dragonpoints'=>[]]; }
    private function master(): array {
        return ['creatureid'=>'1','creaturelevel'=>'1','creaturename'=>'Mireraband','creatureweapon'=>'Small Dagger',
            'creaturewin'=>'Win','creaturelose'=>'Lose','creatureattack'=>'2','creaturedefense'=>'2','creaturehealth'=>'12','creaturegold'=>null,'creatureexp'=>null];
    }
    private function state(): array {
        return ['enemies'=>[$this->master()+['trainingmaxhp'=>12,'playerstarthp'=>10,'dead'=>false,'istarget'=>true,'diddamage'=>0]],
            'options'=>['type'=>'train','traininglevel'=>1,'encounter'=>str_repeat('a',32)]];
    }
    public function testHistoricalScalingAndInjury(): void {
        self::assertSame(0,TrainingCombatState::budget($this->player()));
        self::assertSame(1,TrainingCombatState::budget(['level'=>1,'maxhitpoints'=>20,'dragonpoints'=>['at','de','hp']]));
        $s=$this->state(); self::assertSame($s,TrainingCombatState::read(serialize($s),$this->player(),$this->master()));
        $s['enemies'][0]['creaturehealth']=1; $s['enemies'][0]['diddamage']=1;
        self::assertSame($s,TrainingCombatState::read(serialize($s),$this->player(),$this->master()));
    }
    /** A fight 1.1.2's train.php stored, after its first round (docs/SERIALIZED-SCHEMAS-PART4-20261022.md). */
    private function legacy(): array {
        // Budget 1 (one attack and one defense point at level 1): no attack or defense boost, five extra hit points.
        return ['enemies'=>[array_replace($this->master(),['creatureattack'=>2,'creaturedefense'=>2,'creaturehealth'=>10])+['dead'=>false,'istarget'=>true,'diddamage'=>1]],
            'options'=>['type'=>'train','maxattacks'=>'4']];
    }
    public function testLegacyInFlightFightIsUpgradedOnRead(): void {
        $player=['level'=>1,'maxhitpoints'=>20,'hitpoints'=>15,'dragonpoints'=>['at','de','hp']];
        $encoded=serialize($this->legacy());
        $expected=$this->legacy();
        $expected['enemies'][0]+=['trainingmaxhp'=>17,'playerstarthp'=>15];
        $expected['options']+=['traininglevel'=>1,'encounter'=>md5($encoded)];
        self::assertSame($expected,TrainingCombatState::read($encoded,$player,$this->master()));
        // The same stored bytes always give the same state; the upgraded state reads unchanged.
        self::assertSame($expected,TrainingCombatState::read($encoded,$player,$this->master()));
        self::assertSame($expected,TrainingCombatState::read(serialize($expected),$player,$this->master()));
        // Before the player is hurt, battle.php has not set diddamage yet.
        $s=$this->legacy(); unset($s['enemies'][0]['diddamage']); $encoded=serialize($s);
        $up=TrainingCombatState::read($encoded,$player,$this->master());
        self::assertSame(0,$up['enemies'][0]['diddamage']); self::assertSame(md5($encoded),$up['options']['encounter']);
        // Upgraded, the fight must still pass every check.
        foreach ([['creatureattack'=>3],['creaturedefense'=>3],['creaturehealth'=>18],['creatureid'=>'2'],['creaturename'=>'Other'],['dead'=>true],['reward'=>1],['creatureattack'=>'x']] as $change) {
            $s=$this->legacy(); $s['enemies'][0]=array_replace($s['enemies'][0],$change);
            try { TrainingCombatState::read(serialize($s),$player,$this->master()); self::fail('Invalid legacy fight accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([['type'=>'forest'],['type'=>'train','reward'=>1],['type'=>'train','traininglevel'=>1]] as $options) {
            $s=$this->legacy(); $s['options']=$options;
            try { TrainingCombatState::read(serialize($s),$player,$this->master()); self::fail('Invalid legacy fight accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
    public function testMalformedAndTerminalStateCannotAdvance(): void {
        $base=$this->state(); $cases=[false,[],['enemies'=>[],'options'=>[]]];
        foreach (['creatureid'=>2,'creaturelevel'=>2,'creaturehealth'=>0,'creatureattack'=>99,'creaturedefense'=>-1,
            'trainingmaxhp'=>999,'creatureexp'=>1000,'dead'=>true,'istarget'=>false,'killedplayer'=>true,'reward'=>1] as $key=>$value) {
            $s=$base;$s['enemies'][0][$key]=$value;$cases[]=$s;
        }
        foreach (['type'=>'forest','traininglevel'=>2,'encounter'=>'bad','experience'=>[1000],'maxattacks'=>0] as $key=>$value) {
            $s=$base;$s['options'][$key]=$value;$cases[]=$s;
        }
        $s=$base;$s['enemies'][]=$s['enemies'][0];$cases[]=$s;
        foreach ($cases as $s) {
            try { TrainingCombatState::read(serialize($s),$this->player(),$this->master()); self::fail('Invalid master accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (['broken','O:8:"stdClass":0:{}'] as $encoded) {
            try { TrainingCombatState::read($encoded,$this->player(),$this->master()); self::fail('Unsafe state accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
