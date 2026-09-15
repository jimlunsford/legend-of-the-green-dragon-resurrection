<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\PvpState;

final class PvpStateTest extends TestCase
{
    public function testOwnedSingleTargetAndInvalidBusinessState(): void
    {
        $state=['enemies'=>[['acctid'=>2,'creaturelevel'=>5,'creaturehealth'=>10,'creatureattack'=>2,'creaturedefense'=>1,
            'creatureexp'=>1000,'creaturegold'=>25,'playerstarthp'=>50,'fightstartdate'=>1780000000,
            'creaturename'=>"O'Reilly 🐉",'creatureweapon'=>'Fists','location'=>'Degolburg']],
            'options'=>['type'=>'pvp','owner'=>1,'target'=>2,'encounter'=>str_repeat('a',32),'reservation'=>'2026-09-13 00:00:00']];
        self::assertSame($state,PvpState::read(serialize($state),1));
        $bad=[];
        foreach (['owner'=>2,'target'=>1,'type'=>'forest','encounter'=>'bad'] as $k=>$v) {
            $copy=$state; $copy['options'][$k]=$v; $bad[]=$copy;
        }
        foreach (['creaturehealth','creatureattack','creaturedefense','creatureexp','creaturegold','creaturelevel'] as $key) {
            foreach ([[],new \stdClass(),-1,INF,NAN,'1e999','bad'] as $value) {
                $copy=$state; $copy['enemies'][0][$key]=$value; $bad[]=$copy;
            }
        }
        $copy=$state; $copy['enemies'][0]['dead']=true; $bad[]=$copy;
        $copy=$state; $copy['enemies'][0]['acctid']=3; $bad[]=$copy;
        $copy=$state; $copy['enemies'][0]['creaturename']=str_repeat('x',256); $bad[]=$copy;
        $copy=$state; $copy['enemies']=[]; $bad[]=$copy;
        foreach ($bad as $invalid) {
            try { PvpState::read(serialize($invalid),1); self::fail('Invalid combat accepted.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
