<?php
declare(strict_types=1);
namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Game\DragonCombatState;

final class DragonCombatStateTest extends TestCase
{
    private function enemy(): array
    {
        return ['creaturename'=>'The Green Dragon','creatureweapon'=>'Great Flaming Maw',
            'creaturelevel'=>18,'creatureattack'=>45,'creaturedefense'=>25,'creaturehealth'=>300,
            'diddamage'=>0,'type'=>'dragon'];
    }

    public function testLegacyAndLiveEnvelopeHaveOneAuthority(): void
    {
        $enemy = $this->enemy();
        $state = ['enemies'=>[$enemy],'options'=>['type'=>'dragon']];
        self::assertSame($state,DragonCombatState::read(serialize($enemy)));
        self::assertSame($state,DragonCombatState::read(serialize($state)));
        $state['enemies'][0] += ['dead'=>false,'istarget'=>true,'playerstarthp'=>150];
        $state['options'] += ['maxattacks'=>'4','didsurprise'=>1];
        self::assertSame($state,DragonCombatState::read(serialize($state)));
    }

    public function testMalformedForeignAndTerminalEncountersReject(): void
    {
        $enemy = $this->enemy(); $cases = [false,[],['enemies'=>[$enemy,$enemy],'options'=>['type'=>'dragon']]];
        foreach (array_keys($enemy) as $key) { $bad=$enemy; unset($bad[$key]); $cases[]=$bad; }
        foreach (['creaturelevel'=>17,'creaturehealth'=>0,'creatureattack'=>-1,'creaturedefense'=>INF,
            'dead'=>true,'killedplayer'=>true,'diddamage'=>[],'creaturegold'=>999,'type'=>'forest'] as $key=>$value) {
            $cases[]=array_replace($enemy,[$key=>$value]);
        }
        foreach ([['type'=>'forest'],['type'=>'dragon','reward'=>100],['type'=>'dragon','maxattacks'=>0]] as $options) {
            $cases[]=['enemies'=>[$enemy],'options'=>$options];
        }
        foreach ($cases as $state) {
            try { DragonCombatState::read(serialize($state)); self::fail('Invalid Dragon accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testVictoryIsTypedBoundToKillCountAndNeverLiveCombat(): void
    {
        foreach ([false,true] as $flawless) {
            $state=['dragonVictory'=>$flawless,'dragonkills'=>2,'dragonEncounter'=>str_repeat('a',32)];
            self::assertSame($state,DragonCombatState::victory(serialize($state),2));
            try { DragonCombatState::read(serialize($state)); self::fail('Outcome accepted as combat'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([['dragonVictory'=>true,'dragonkills'=>1],['dragonVictory'=>1,'dragonkills'=>2],
            ['dragonVictory'=>true,'dragonkills'=>'2'],['dragonVictory'=>false,'dragonkills'=>2,'reward'=>1],$this->enemy()] as $state) {
            try { DragonCombatState::victory(serialize($state),2); self::fail('Invalid outcome accepted'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
