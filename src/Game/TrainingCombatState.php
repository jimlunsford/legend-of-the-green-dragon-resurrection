<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** Single server-selected master. No Forest rewards, target switching or terminal continuation. */
final class TrainingCombatState
{
    /** @param array<string,mixed> $player */
    public static function budget(array $player): int
    {
        $points=$player['dragonpoints'] ?? null;
        if (!is_array($points) || !array_is_list($points)) throw new \DomainException('Invalid training points.');
        $count=0;
        foreach ($points as $point) {
            if (!in_array($point,['hp','at','de','ff'],true)) throw new \DomainException('Invalid training points.');
            if ($point==='at' || $point==='de') $count++;
        }
        $count+=(int)(($player['maxhitpoints']-$player['level']*10)/5);
        $budget=(int)round($count*.33);
        if ($budget<0 || $budget>1000000) throw new \DomainException('Invalid training scale.');
        return $budget;
    }

    /** @param array<string,mixed> $master */
    public static function master(array $master): void
    {
        foreach (['creatureid','creaturelevel','creaturehealth','creatureattack','creaturedefense','creaturegold','creatureexp'] as $key) {
            if (in_array($key,['creaturegold','creatureexp'],true) && array_key_exists($key,$master) && $master[$key]===null) continue;
            self::number($master[$key] ?? null, in_array($key,['creatureid','creaturelevel','creaturehealth'],true)?1:0);
        }
        foreach (['creaturename','creatureweapon','creaturewin','creaturelose'] as $key) {
            if (!is_string($master[$key] ?? null) || strlen($master[$key])>4096) throw new \DomainException('Invalid master text.');
        }
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $master
     * @return array<string,mixed>
     */
    public static function read(mixed $encoded,array $player,array $master): array
    {
        self::master($master);
        $state=ScalarState::read($encoded);
        if (!is_array($state) || array_diff(array_keys($state),['enemies','options'])!==[] ||
            !is_array($state['enemies'] ?? null) || array_keys($state['enemies'])!==[0] ||
            !is_array($state['enemies'][0]) || !is_array($state['options'] ?? null)) throw new \DomainException('Invalid training combat.');
        $o=$state['options']; $e=$state['enemies'][0];
        if (array_diff(array_keys($o),['type','traininglevel','encounter','maxattacks','didsurprise'])!==[] ||
            ($o['type'] ?? null)!=='train' || ($o['traininglevel'] ?? null)!==(int)$player['level'] ||
            !is_string($o['encounter'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$o['encounter'])) throw new \DomainException('Invalid training context.');
        if (isset($o['maxattacks'])) self::number($o['maxattacks'],1,100);
        if (isset($o['didsurprise'])) self::flag($o['didsurprise']);
        if (array_diff(array_keys($e),[...array_keys($master),'dead','istarget','diddamage','playerstarthp','trainingmaxhp'])!==[]) throw new \DomainException('Unexpected training field.');
        foreach ($master as $key=>$value) {
            if (in_array($key,['creaturehealth','creatureattack','creaturedefense'],true)) continue;
            if (!array_key_exists($key,$e) || (!is_scalar($e[$key]) && $e[$key]!==null) || (string)$e[$key] !== (string)$value) throw new \DomainException('Master identity changed.');
        }
        foreach (['creaturehealth','trainingmaxhp','creatureattack','creaturedefense','playerstarthp'] as $key) self::number($e[$key] ?? null,in_array($key,['creaturehealth','trainingmaxhp','playerstarthp'],true)?1:0);
        $budget=self::budget($player);
        $attack=$e['creatureattack']-$master['creatureattack']; $defense=$e['creaturedefense']-$master['creaturedefense'];
        if ($attack<0 || $defense<0 || $attack>round($budget*.25) || $defense>round($budget*.25) ||
            $e['trainingmaxhp']!=$master['creaturehealth']+($budget-$attack-$defense)*5 ||
            $e['creaturehealth']>$e['trainingmaxhp']) throw new \DomainException('Invalid master statistics.');
        foreach (['dead','istarget','diddamage'] as $key) self::flag($e[$key] ?? null);
        if (!empty($e['dead']) || empty($e['istarget'])) throw new \DomainException('Training is already terminal.');
        return $state;
    }

    private static function number(mixed $value,int $min=0,int $max=2147483647): void
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value<$min || $value>$max) throw new \DomainException('Invalid training statistic.');
    }
    private static function flag(mixed $value): void
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new \DomainException('Invalid training flag.');
    }
}
