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
