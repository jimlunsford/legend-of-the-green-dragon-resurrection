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
        if (is_array($s) && is_string($encoded)) $s=self::legacy($s,$encoded,$player);
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
    /**
     * A torment 1.1.2's graveyard search stored ({type:'graveyard'} and the whole creatures row with the torment
     * statistics), upgraded on read into the current shape, deterministically: the creature-row columns no torment
     * reads are dropped (gold, the old experience, author, the Forest and Graveyard flags, an empty AI script), the
     * encounter identity is derived from the stored bytes, the level copied as text becomes a number, the starting soul
     * points are the player's (an integer, as the search writes them), and the flags battle.php adds on a first round
     * default as autosettarget() set them.
     * Nothing is relaxed: the result must pass read() as any other torment, so a non-empty AI script still fails. Any
     * other shape is returned unchanged.
     * @param array<mixed> $s
     * @param array<string,mixed> $player
     * @return array<mixed>
     */
    private static function legacy(array $s,string $encoded,array $player): array
    {
        $o=$s['options'] ?? null; $e=$s['enemies'][0] ?? null;
        if (array_diff(array_keys($s),['enemies','options'])!==[] || !is_array($o) || ($o['type'] ?? null)!=='graveyard' ||
            array_diff(array_keys($o),['type','maxattacks','didsurprise'])!==[] || !is_array($s['enemies']) ||
            array_keys($s['enemies'])!==[0] || !is_array($e) || array_key_exists('playerstarthp',$e)) return $s;
        foreach (['creaturegold','oldcreatureexp','createdby','forest','graveyard'] as $key) unset($e[$key]);
        if (array_key_exists('creatureaiscript',$e) && ($e['creatureaiscript']==='' || $e['creatureaiscript']===null)) unset($e['creatureaiscript']);
        // 1.1.2 copied the session's level, which PHP 5 read from the database as text.
        if (is_string($e['creaturelevel'] ?? null) && preg_match('/^[1-9][0-9]{0,2}$/D',$e['creaturelevel'])) $e['creaturelevel']=(int)$e['creaturelevel'];
        $e+=['dead'=>false,'istarget'=>true,'diddamage'=>0];
        // 1.1.2 recorded no starting soul points; nothing in the torment reads them.
        $e['playerstarthp']=(int)$player['soulpoints'];
        $o['encounter']=md5($encoded);
        return ['enemies'=>[$e],'options'=>$o];
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
