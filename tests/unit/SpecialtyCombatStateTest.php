<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Game\SpecialtyCombatState;

final class SpecialtyCombatStateTest extends TestCase
{
    private function state(): array
    {
        return ['enemies'=>[['creatureid'=>'1','creaturelevel'=>10,'creaturehealth'=>1000,
            'creatureattack'=>10,'creaturedefense'=>10,'creaturegold'=>10,'creatureexp'=>10,
            'playerstarthp'=>100,'diddamage'=>0,'creaturename'=>'Opponent','creatureweapon'=>'Claws']],
            'options'=>['type'=>'forest']];
    }
    public function testLiveStateRoundTripsWithoutReinterpretation(): void
    {
        $state=$this->state();
        self::assertSame($state,SpecialtyCombatState::read(serialize($state)));
        $state['enemies'][0]['istarget']=true;
        $state['options']['experience']=[0=>10];
        self::assertSame($state,SpecialtyCombatState::read(serialize($state)));
    }
    public function testMissingMalformedAndTerminalFieldsFailClosed(): void
    {
        $cases=[[],false,'wrong root'];
        foreach (array_keys($this->state()['enemies'][0]) as $key) {
            $state=$this->state(); unset($state['enemies'][0][$key]); $cases[]=$state;
        }
        foreach (['creaturehealth'=>0,'creatureattack'=>-1,'creaturedefense'=>[],
            'creaturegold'=>INF,'creatureexp'=>2147483648,'creatureid'=>false,'dead'=>true,
            'terminal'=>true,'istarget'=>[],'cannotbetarget'=>true] as $key=>$value) {
            $state=$this->state(); $state['enemies'][0][$key]=$value; $cases[]=$state;
        }
        foreach (['creatureid','creaturelevel'] as $key) {
            foreach ([1.5,'1.5'] as $value) {
                $state=$this->state(); $state['enemies'][0][$key]=$value; $cases[]=$state;
            }
        }
        foreach ([[],['type'=>'dragon'],['type'=>'forest','maxattacks'=>0],
            ['type'=>'forest','experience'=>[99=>10]],['type'=>'forest','terminal'=>true]] as $options) {
            $state=$this->state(); $state['options']=$options; $cases[]=$state;
        }
        foreach ($cases as $state) $this->reject(serialize($state));
        foreach (['','broken','O:8:"stdClass":0:{}',str_repeat('x',1048577)] as $encoded) $this->reject($encoded);
    }
    private function reject(string $encoded): void
    {
        try { SpecialtyCombatState::read($encoded); self::fail('Invalid combat accepted'); }
        catch (\DomainException) { self::assertTrue(true); }
    }
}
