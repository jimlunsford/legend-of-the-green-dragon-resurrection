<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Consumer schema for ordinary combat; producer-specific specialty rules stay authoritative. */
final class ForestBuffState
{
    /** @param array<string,mixed> $player */
    public static function validate(mixed $buffs, array $player): void
    {
        $buffs = SpecialtyBuffState::collection($buffs);
        if (count($buffs) > 100) throw new \DomainException('Too many combat buffs.');
        $numbers = ['atkmod','defmod','dmgmod','badguyatkmod','badguydefmod','badguydmgmod',
            'lifetap','damageshield','regen','minioncount','minbadguydamage','maxbadguydamage',
            'mingoodguydamage','maxgoodguydamage','compatkmod','compdefmod',
            'tempstat-attack','tempstat-defense','tempstat-maxhitpoints'];
        $flags = ['areadamage','aura','invulnerable','suspended','used','fields_calculated','tempstats_calculated',
            'allowinpvp','allowintrain','allowintravel','expireafterfight','survive_newday','survivenewday','goodmusthit','badmusthit'];
        $texts = ['name','startmsg','roundmsg','wearoff','effectmsg','effectnodmgmsg','effectfailmsg','auramsg','schema','newdaymessage'];
        foreach ($buffs as $id=>$buff) {
            if (!is_string($id) || $id === '' || strlen($id) > 128 ||
                array_diff(array_keys($buff),[...$numbers,...$flags,...$texts,'rounds']) !== []) throw new \DomainException('Invalid combat buff fields.');
            if ($id === 'transmute') { TransmutationState::read($buff); continue; }
            if (!isset($buff['rounds']) || !is_int($buff['rounds']) || $buff['rounds'] < -1 || $buff['rounds'] === 0 || $buff['rounds'] > 100000) throw new \DomainException('Invalid combat duration.');
            if (isset($buff['minioncount']) && (!is_int($buff['minioncount']) && !is_float($buff['minioncount']) || $buff['minioncount'] < 0 || $buff['minioncount'] > 1000 || floor((float)$buff['minioncount']) != $buff['minioncount'])) throw new \DomainException('Invalid minion count.');
            foreach ($texts as $key) if (isset($buff[$key]) && (!is_string($buff[$key]) || strlen($buff[$key]) > 4096)) throw new \DomainException('Invalid combat message.');
            foreach ($flags as $key) if (array_key_exists($key,$buff) && !in_array($buff[$key],[false,true,0,1,'0','1'],true)) throw new \DomainException('Invalid buff flag.');
            foreach ($numbers as $key) {
                if (!array_key_exists($key,$buff)) continue;
                $value=$buff[$key];
                if (is_string($value) && str_contains($value,'<')) $value=Expression::evaluate($value,$player);
                if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
                    (is_string($value) && !preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) || !is_numeric($value) || !is_finite((float)$value) || ($value < -2147483647 || $value > 2147483647) ||
                    (in_array($key,['minioncount','lifetap','damageshield','atkmod','defmod','dmgmod','badguyatkmod','badguydefmod','badguydmgmod'],true) && $value < 0)) throw new \DomainException('Invalid buff statistic.');
            }
        }
    }
}
