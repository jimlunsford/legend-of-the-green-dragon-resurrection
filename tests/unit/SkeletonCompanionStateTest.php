<?php
declare(strict_types=1);
namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Game\SkeletonCompanionState;
use Resurrection\Security\ScalarState;

final class SkeletonCompanionStateTest extends TestCase
{
    private function skeleton(): array
    {
        return ['name'=>'`4Skeleton Warrior','hitpoints'=>43.0,'maxhitpoints'=>43.0,
            'attack'=>26.5,'defense'=>14.5,'dyingtext'=>'`$Your skeleton warrior crumbles to dust.`n',
            'abilities'=>['fight'=>true],'ignorelimit'=>true];
    }

    public function testCreationDamageAndRuntimeFlagsRoundTripUnchanged(): void
    {
        foreach ([$this->skeleton(), array_replace($this->skeleton(), ['hitpoints'=>1,'used'=>true,'suspended'=>false])] as $state) {
            $map = ['skeleton_warrior'=>$state];
            self::assertSame($map, SkeletonCompanionState::companions(ScalarState::read(serialize($map))));
        }
        foreach ([[13,5.5,2.5],[17,10.5,2.5],[20,10.5,5.5],[27,13.5,5.5],[60,43.5,27.5]] as [$hp,$attack,$defense]) {
            $state = array_replace($this->skeleton(), ['hitpoints'=>$hp,'maxhitpoints'=>$hp,'attack'=>$attack,'defense'=>$defense]);
            $map = ['skeleton_warrior'=>$state];
            self::assertSame($map, SkeletonCompanionState::companions(ScalarState::read(serialize($map))));
        }
        self::assertSame([], SkeletonCompanionState::companions([]));
    }

    public function testRejectsEveryMissingFieldAndUnsupportedStructure(): void
    {
        $states = [null, false, 'skeleton', []];
        foreach (array_keys($this->skeleton()) as $key) {
            $state = $this->skeleton(); unset($state[$key]); $states[] = $state;
        }
        foreach (['rounds'=>5,'cannotdie'=>true,'expireafterfight'=>true,'allowinpvp'=>true,
            'atkmod'=>100,'extra'=>['nested'=>1], 'used'=>1,'suspended'=>'0',
            'abilities'=>['fight'=>true,'magic'=>100], 'ignorelimit'=>1,
            'name'=>'forged','dyingtext'=>'forged'] as $key=>$value) {
            $states[] = array_replace($this->skeleton(), [$key=>$value]);
        }
        foreach ($states as $state) { $this->reject($state); }
    }

    public function testRejectsInvalidNumbersAndInconsistentCreationFormula(): void
    {
        foreach (['hitpoints','maxhitpoints','attack','defense'] as $key) {
            foreach ([0,-1,'43',true,null,[],INF,NAN,2147483648] as $value) {
                $this->reject(array_replace($this->skeleton(), [$key=>$value]));
            }
        }
        foreach (['hitpoints'=>44,'maxhitpoints'=>44,'attack'=>27.5,'defense'=>15.5] as $key=>$value) {
            $this->reject(array_replace($this->skeleton(), [$key=>$value]));
        }
    }

    public function testRejectsMalformedSerializedRootAndObjectsWithoutRestoration(): void
    {
        foreach (['broken','a:0:{}junk','O:8:"stdClass":0:{}',serialize(['skeleton_warrior'=>(object)$this->skeleton()]),
            serialize(['skeleton_warrior'=>null]),serialize(['skeleton_warrior'=>['nested'=>$this->skeleton()]]),
            serialize(['skeleton_warrior'=>array_replace($this->skeleton(), ['abilities'=>new \stdClass()])]),
            serialize(str_repeat('x',1048577))] as $encoded) {
            try {
                SkeletonCompanionState::companions(ScalarState::read($encoded));
                self::fail('Accepted invalid companion map');
            } catch (\DomainException) { self::assertTrue(true); }
        }
    }

    private function reject(mixed $state): void
    {
        try { SkeletonCompanionState::validate($state); self::fail('Accepted invalid skeleton'); }
        catch (\DomainException) { self::assertTrue(true); }
    }
}
