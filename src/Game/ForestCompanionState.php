<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Minimum battle-consumer types for historical companion rows; no recruitment authority. */
final class ForestCompanionState
{
    public static function validate(mixed $state): void
    {
        $state=SkeletonCompanionState::companions($state);
        if (count($state)>100) throw new \DomainException('Invalid companion count.');
        foreach ($state as $id=>$companion) {
            if ($id==='skeleton_warrior') continue; // Exact existing producer schema.
            $numbers=['hitpoints','maxhitpoints','attack','defense','companionid','attackperlevel','defenseperlevel',
                'maxhitpointsperlevel','companioncostdks','companioncostgems','companioncostgold'];
            $texts=['name','category','description','companionlocation','jointext','dyingtext','schema',
                'healcompanionmsg','healmsg','magicmsg','magicfailmsg'];
            $flags=['used','suspended','ignorelimit','cannotdie','cannotbehealed','companionactive',
                'allowinshades','allowinpvp','allowintrain','expireafterfight'];
            if (!is_string($id) || $id==='' || strlen($id)>255 ||
                array_diff(array_keys($companion),[...$numbers,...$texts,...$flags,'abilities'])!==[]) throw new \DomainException('Invalid companion fields.');
            foreach (['hitpoints','maxhitpoints','attack','defense','name','abilities'] as $key) if (!array_key_exists($key,$companion)) throw new \DomainException('Missing companion statistic.');
            foreach ($numbers as $key) if (array_key_exists($key,$companion)) self::number($companion[$key]);
            if ($companion['maxhitpoints']<=0 || $companion['hitpoints']>$companion['maxhitpoints'] ||
                ($companion['hitpoints']==0 && empty($companion['cannotdie']))) throw new \DomainException('Invalid companion health.');
            foreach ($texts as $key) if (array_key_exists($key,$companion) && (!is_string($companion[$key]) || strlen($companion[$key])>8192)) throw new \DomainException('Invalid companion text.');
            foreach ($flags as $key) if (array_key_exists($key,$companion)) self::flag($companion[$key]);
            if (!is_array($companion['abilities']) || array_diff(array_keys($companion['abilities']),['fight','defend','heal','magic'])!==[]) throw new \DomainException('Invalid companion abilities.');
            foreach ($companion['abilities'] as $ability=>$value) {
                if (in_array($ability,['fight','defend'],true)) self::flag($value);
                else self::number($value);
            }
        }
    }
    private static function number(mixed $value): void
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value<0 || $value>2147483647) throw new \DomainException('Invalid companion number.');
    }
    private static function flag(mixed $value): void
    {
        if (!in_array($value,[false,true,0,1,'0','1'],true)) throw new \DomainException('Invalid companion flag.');
    }
}
