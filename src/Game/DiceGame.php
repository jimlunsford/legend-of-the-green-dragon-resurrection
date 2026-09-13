<?php
declare(strict_types=1);
namespace Resurrection\Game;

final class DiceGame
{
    /** @return array{roll:int,tries:int,opponent:int} */
    public static function decode(string $json): array
    {
        try { $s=json_decode($json,true,3,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \DomainException('Invalid dice.'); }
        if (!is_array($s) || count($s)!==3 || array_diff(array_keys($s),['roll','tries','opponent'])!==[]) throw new \DomainException('Invalid dice.');
        foreach (['roll'=>[1,6],'tries'=>[1,3],'opponent'=>[0,6]] as $key=>$bounds) {
            if (!is_int($s[$key]) || $s[$key]<$bounds[0] || $s[$key]>$bounds[1]) throw new \DomainException('Invalid dice.');
        }
        return ['roll'=>$s['roll'],'tries'=>$s['tries'],'opponent'=>$s['opponent']];
    }

    /** @param callable(int,int):int $random */
    public static function roll(callable $random): int
    {
        $r=$random(1,6);
        if ($r<1 || $r>6) throw new \DomainException('Invalid die.');
        return $r;
    }

    /** @param array{roll:int,tries:int,opponent:int} $s
     * @param callable(int,int):int $random
     * @return array{roll:int,tries:int,opponent:int}
     */
    public static function act(array $s, string $action, callable $random): array
    {
        self::decode(json_encode($s,JSON_THROW_ON_ERROR));
        if ($s['opponent']!==0) throw new \DomainException('Already settled.');
        if ($action==='pass' && $s['tries']<3) {
            $s['tries']++; $s['roll']=self::roll($random);
        } elseif ($action==='keep') {
            // Historical stopping policy: first must beat player (or be six), second may tie.
            $r=self::roll($random);
            if ($r<=$s['roll'] && $r!==6) {
                $r=self::roll($random);
                if ($r<$s['roll']) $r=self::roll($random);
            }
            $s['opponent']=$r;
        } else throw new \DomainException('Invalid dice action.');
        return $s;
    }
}
