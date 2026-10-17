<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** Torment's single enemy and favor domain, independent of Forest rewards. */
final class GraveyardCombatState
{
    /** @param array<string,mixed> $player */
    public static function player(array $player): void
    {
        foreach (['level','soulpoints','deathpower','gravefights'] as $key) self::integer($player[$key] ?? null,$key==='level'?1:0,$key==='level'?255:4294967295);
        if (!in_array($player['alive'] ?? null,[false,0,'0'],true) || !in_array($player['hitpoints'] ?? null,[0,'0'],true)) throw new \DomainException('Invalid dead player.');
    }

    /**
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function read(mixed $encoded,array $player): array
    {
        self::player($player);
        $s=ScalarState::read($encoded);
        if (!is_array($s) || array_diff(array_keys($s),['enemies','options'])!==[] ||
            !is_array($s['enemies'] ?? null) || array_keys($s['enemies'])!==[0] ||
            !is_array($s['enemies'][0]) || !is_array($s['options'] ?? null)) throw new \DomainException('Invalid torment.');
        $e=$s['enemies'][0]; $o=$s['options']; $level=(int)$player['level'];
        if (array_diff(array_keys($o),['type','encounter','maxattacks','didsurprise'])!==[] || ($o['type'] ?? null)!=='graveyard' ||
            !is_string($o['encounter'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$o['encounter'])) throw new \DomainException('Invalid encounter.');
        if (isset($o['maxattacks'])) self::integer($o['maxattacks'],1,100);
        if (isset($o['didsurprise'])) self::flag($o['didsurprise']);
        if (array_diff(array_keys($e),['creatureid','creaturename','creatureweapon','creaturelose','creaturewin','creaturehealth',
            'creatureattack','creaturedefense','creaturelevel','creatureexp','playerstarthp','dead','istarget','diddamage'])!==[]) throw new \DomainException('Unexpected torment field.');
        self::integer($e['creatureid'] ?? null,1,4294967295);
        foreach (['creaturename','creatureweapon','creaturelose','creaturewin'] as $key) if (!is_string($e[$key] ?? null) || strlen($e[$key])>4096 || ($key==='creaturename' && $e[$key]==='')) throw new \DomainException('Invalid enemy text.');
        self::integer($e['creaturelevel'] ?? null,1,255);
        self::integer($e['creaturehealth'] ?? null,1,$level*5+50);
        self::integer($e['playerstarthp'] ?? null,0,4294967295);
        self::integer($e['creatureexp'] ?? null,(int)(10+round($level/3)),(int)(20+round($level/3)));
        $attack=9+($level<5?-1:0)+(int)(($level-1)*1.5);
        if (($e['creaturelevel'] ?? null)!==$level || ($e['creatureattack'] ?? null)!==$attack ||
            ($e['creaturedefense'] ?? null)!==$attack*.7) throw new \DomainException('Invalid enemy scale.');
        foreach (['dead','istarget','diddamage'] as $key) self::flag($e[$key] ?? null);
        if (!empty($e['dead']) || empty($e['istarget']) || $player['soulpoints']<=0) throw new \DomainException('Terminal combat cannot continue.');
        return $s;
    }
    public static function integer(mixed $v,int $min,int $max): void
    {
        if ((!is_int($v) && !is_string($v) && !(is_float($v) && is_finite($v) && floor($v)===$v)) || (is_string($v) && !preg_match('/^(0|[1-9][0-9]*)$/D',$v)) || $v<$min || $v>$max) throw new \DomainException('Invalid torment statistic.');
    }
    private static function flag(mixed $v): void
    {
        if (!in_array($v,[true,false,0,1,'0','1'],true)) throw new \DomainException('Invalid torment flag.');
    }
}
