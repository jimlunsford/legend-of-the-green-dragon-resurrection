<?php
declare(strict_types=1);
namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Game\SpecialtyBuffState;
use Resurrection\Security\ScalarState;

final class SpecialtyBuffStateTest extends TestCase
{
    private function lifetap(): array
    {
        return ['startmsg'=>'`^Your weapon glows with an unearthly presence.',
            'name'=>'`%Siphon Life','rounds'=>5,'wearoff'=>"Your weapon's aura fades.",
            'lifetap'=>1,'effectmsg'=>'You are healed for {damage} health.',
            'effectnodmgmsg'=>'You feel a tingle as your weapon tries to heal your already healthy body.',
            'effectfailmsg'=>'Your weapon wails as you deal no damage to your opponent.',
            'schema'=>'module-specialtymysticpower'];
    }

    public function testCreationAndFinalActiveRoundPreserveHistoricalText(): void
    {
        foreach ([5,4,1] as $rounds) {
            $buff=$this->lifetap(); $buff['rounds']=$rounds;
            if ($rounds<5) unset($buff['startmsg']);
            $buff += ['used'=>0,'suspended'=>false,'fields_calculated'=>true];
            $map=['mp3'=>$buff];
            self::assertSame($map,SpecialtyBuffState::collection(ScalarState::read(serialize($map))));
        }
        self::assertSame([],SpecialtyBuffState::collection([]));
        self::assertSame(['unrelated'=>['rounds'=>-1]],SpecialtyBuffState::collection(['unrelated'=>['rounds'=>-1]]));
    }

    public function testRequiredFieldsModifiersFlagsAndExpiredStateFailClosed(): void
    {
        foreach (array_keys($this->lifetap()) as $key) {
            if ($key==='startmsg') continue;
            $buff=$this->lifetap(); unset($buff[$key]); $this->reject(['mp3'=>$buff]);
        }
        foreach (['rounds'=>[0,-1,6,'5',1.5,null], 'lifetap'=>[0,2,'1',INF,NAN,[],new \stdClass()],
            'used'=>[true,'1',2], 'suspended'=>[1,'0'], 'atkmod'=>[100], 'tempstat-attack'=>[100],
            'expireafterfight'=>[true], 'schema'=>['module-specialtydarkarts'],
            'effectmsg'=>['<script>alert(1)</script>'], 'fields_calculated'=>[1]] as $field=>$values) {
            foreach ($values as $value) $this->reject(['mp3'=>array_replace($this->lifetap(),[$field=>$value])]);
        }
        $this->reject(['mp99'=>$this->lifetap()]);
        $this->reject(['renamed'=>$this->lifetap()]);
    }

    public function testMalformedSerializationAndObjectsCannotRestore(): void
    {
        $nested=[]; for ($i=0;$i<34;$i++) $nested=[$nested];
        foreach (['broken','a:0:{}trailing','O:8:"stdClass":0:{}',serialize(false),serialize('buffs'),
            serialize(['mp3'=>new \stdClass()]),serialize(['mp3'=>['nested'=>$nested]]),
            serialize(['mp3'=>array_replace($this->lifetap(),['lifetap'=>INF])])] as $encoded) {
            $this->reject(ScalarState::read($encoded));
        }
        $this->reject(['mp3'=>null]);
    }

    private function reject(mixed $state): void
    {
        try { SpecialtyBuffState::collection($state); self::fail('Accepted invalid specialty buff.'); }
        catch (\DomainException) { self::assertTrue(true); }
    }
}
