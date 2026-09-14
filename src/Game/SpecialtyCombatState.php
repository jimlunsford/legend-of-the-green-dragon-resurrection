<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** Minimum live Forest state accepted by the specialty action boundary. */
final class SpecialtyCombatState
{
    /** @return array<string,mixed> */
    public static function read(mixed $encoded): array
    {
        $state = ScalarState::read($encoded);
        if (!is_array($state) || array_diff(array_keys($state), ['enemies','options']) !== [] ||
            !is_array($state['enemies'] ?? null) || !is_array($state['options'] ?? null) ||
            count($state['enemies']) < 1 || count($state['enemies']) > 100) {
            throw new \DomainException('Invalid specialty combat.');
        }
        $options = $state['options'];
        if (($options['type'] ?? null) !== 'forest' ||
            array_diff(array_keys($options), ['type','maxattacks','didsurprise','denyflawless','experience','experiencegained']) !== []) {
            throw new \DomainException('Invalid specialty combat options.');
        }
        foreach (['didsurprise'] as $key) if (array_key_exists($key,$options)) self::flag($options[$key]);
        if (isset($options['maxattacks'])) self::number($options['maxattacks'],1,100);
        if (isset($options['denyflawless']) && !is_bool($options['denyflawless']) && !is_string($options['denyflawless'])) throw new \DomainException('Invalid combat message.');
        foreach (['experience','experiencegained'] as $key) {
            if (!array_key_exists($key,$options)) continue;
            if (!is_array($options[$key]) || count($options[$key]) > 100) throw new \DomainException('Invalid combat rewards.');
            foreach ($options[$key] as $index=>$value) {
                if (!is_int($index) || $index < 0 || !isset($state['enemies'][$index])) throw new \DomainException('Invalid combat reward target.');
                self::number($value,0,2147483647);
            }
        }
        $targets = 0; $alive = 0;
        foreach ($state['enemies'] as $index=>$enemy) {
            if (!is_int($index) || $index < 0 || !is_array($enemy)) throw new \DomainException('Invalid combat enemy.');
            $numeric = ['creatureid','creaturelevel','creaturehealth','creatureattack','creaturedefense','creaturegold','creatureexp','playerstarthp'];
            $text = ['creaturename','creatureweapon','creaturelose','creaturewin','createdby','creatureaiscript','type','denyflawless'];
            $flags = ['dead','istarget','diddamage','expgained','killedplayer','alwaysattacks','hidehitpoints','forest','graveyard','cannotbetarget','essentialleader','fleesifalone'];
            if (array_diff(array_keys($enemy), [...$numeric,...$text,...$flags]) !== []) throw new \DomainException('Unexpected combat field.');
            foreach ($numeric as $key) self::number($enemy[$key] ?? null, in_array($key,['creatureid','creaturelevel','playerstarthp'],true)?1:0,2147483647);
            foreach ($text as $key) {
                if (!array_key_exists($key,$enemy)) {
                    if (in_array($key,['creaturename','creatureweapon'],true)) throw new \DomainException('Missing combat name.');
                    continue;
                }
                if ($enemy[$key] === null && !in_array($key,['creaturename','creatureweapon'],true)) continue;
                if (!is_string($enemy[$key]) || strlen($enemy[$key]) > 4096) throw new \DomainException('Invalid combat text.');
            }
            if ($enemy['creaturename'] === '' || (isset($enemy['type']) && $enemy['type'] !== 'forest')) throw new \DomainException('Invalid combat identity.');
            foreach ($flags as $key) if (array_key_exists($key,$enemy)) self::flag($enemy[$key]);
            if ($enemy['creaturehealth'] <= 0 || !empty($enemy['dead']) || !empty($enemy['killedplayer'])) throw new \DomainException('Terminal specialty target.');
            $alive++;
            if (!empty($enemy['istarget'])) {
                if (!empty($enemy['cannotbetarget'])) throw new \DomainException('Ineligible combat target.');
                $targets++;
            }
        }
        // A newly generated encounter may not yet have autosettarget's flag.
        if ($alive === 0 || $targets > 1) throw new \DomainException('Invalid specialty target.');
        if ($targets === 0) {
            $available = array_filter($state['enemies'], static fn(array $enemy): bool => empty($enemy['cannotbetarget']));
            if ($available === []) throw new \DomainException('No specialty target.');
        }
        return $state;
    }

    private static function number(mixed $value, int $min, int $max): void
    {
        // PDO creature rows contain numeric strings; derived combat values are numbers.
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value < $min || $value > $max) {
            throw new \DomainException('Invalid combat statistic.');
        }
    }

    private static function flag(mixed $value): void
    {
        if (!in_array($value,[false,true,0,1,'0','1'],true)) throw new \DomainException('Invalid combat flag.');
    }
}
