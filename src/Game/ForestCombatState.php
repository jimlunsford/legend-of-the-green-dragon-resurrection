<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** Ordinary Forest business state. Other callers keep their own schemas. */
final class ForestCombatState
{
    /** @return array<string,mixed> */
    public static function read(mixed $encoded): array
    {
        $state = ScalarState::read($encoded);
        if (!is_array($state) || array_diff(array_keys($state), ['enemies','options']) !== [] ||
            !is_array($state['enemies'] ?? null) || !is_array($state['options'] ?? null) ||
            !array_is_list($state['enemies']) || count($state['enemies']) < 1 || count($state['enemies']) > 100) {
            throw new \DomainException('Invalid Forest combat.');
        }
        $options = $state['options'];
        if (($options['type'] ?? null) !== 'forest' ||
            array_diff(array_keys($options), ['type','maxattacks','didsurprise','denyflawless','experience','experiencegained','encounter']) !== []) {
            throw new \DomainException('Invalid Forest combat options.');
        }
        foreach (['didsurprise'] as $key) if (array_key_exists($key,$options)) self::flag($options[$key]);
        if (array_key_exists('maxattacks',$options)) self::number($options['maxattacks'],1,100);
        if (array_key_exists('denyflawless',$options) && !is_bool($options['denyflawless']) && !is_string($options['denyflawless'])) throw new \DomainException('Invalid combat message.');
        foreach (['experience','experiencegained'] as $key) {
            if (!array_key_exists($key,$options)) continue;
            if (!is_array($options[$key]) || count($options[$key]) > 100) throw new \DomainException('Invalid combat rewards.');
            foreach ($options[$key] as $index=>$value) {
                if (!is_int($index) || $index < 0 || !isset($state['enemies'][$index])) throw new \DomainException('Invalid combat reward target.');
                self::number($value,0,2147483647);
            }
        }
        if (array_key_exists('encounter',$options) && (!is_string($options['encounter']) || !preg_match('/^[a-f0-9]{32}$/D',$options['encounter']))) throw new \DomainException('Invalid encounter identity.');
        $targets = 0;
        $available = 0;
        foreach ($state['enemies'] as $index=>$enemy) {
            if (!is_array($enemy)) throw new \DomainException('Invalid combat enemy.');
            $numeric = ['creatureid','creaturelevel','creaturehealth','creatureattack','creaturedefense','creaturegold','creatureexp','playerstarthp'];
            $text = ['creaturename','creatureweapon','creaturelose','creaturewin','createdby','creatureaiscript','type','denyflawless'];
            $flags = ['dead','istarget','diddamage','expgained','killedplayer','alwaysattacks','hidehitpoints','forest','graveyard','cannotbetarget','essentialleader','fleesifalone','spellpoints'];
            if (array_diff(array_keys($enemy), [...$numeric,...$text,...$flags]) !== []) throw new \DomainException('Unexpected combat field.');
            // The shipped doppleganger has no database identity. Its producer supplies
            // starting HP and damage accounting just like a creature row.
            if (!array_key_exists('creatureid',$enemy)) {
                if (!is_string($enemy['creaturename'] ?? null) || !str_starts_with($enemy['creaturename'],'An evil doppleganger of ')) throw new \DomainException('Missing creature identity.');
                $numeric = array_values(array_diff($numeric,['creatureid']));
            }
            foreach ($numeric as $key) self::number($enemy[$key] ?? null,
                $key === 'creaturehealth' ? -2147483647 : (in_array($key,['creatureid','creaturelevel','playerstarthp'],true)?1:0),2147483647);
            foreach (['creatureid','creaturelevel'] as $key) {
                if (!array_key_exists($key,$enemy)) continue;
                if ((float)$enemy[$key] !== floor((float)$enemy[$key])) throw new \DomainException('Invalid combat identity.');
            }
            foreach ($text as $key) {
                if (!array_key_exists($key,$enemy)) {
                    if (in_array($key,['creaturename','creatureweapon'],true)) throw new \DomainException('Missing combat name.');
                    continue;
                }
                if ($enemy[$key] === null && !in_array($key,['creaturename','creatureweapon'],true)) continue;
                if (!is_string($enemy[$key]) || strlen($enemy[$key]) > 4096) throw new \DomainException('Invalid combat text.');
            }
            if ($enemy['creaturename'] === '' || (isset($enemy['type']) && $enemy['type'] !== 'forest')) throw new \DomainException('Invalid combat identity.');
            if (!empty($enemy['creatureaiscript']) && !CreatureAi::supported($enemy['creatureaiscript'])) throw new \DomainException('Unsupported creature behavior.');
            self::flag($enemy['diddamage'] ?? null);
            foreach ($flags as $key) if (array_key_exists($key,$enemy)) self::flag($enemy[$key]);
            if (array_key_exists('spellpoints',$enemy) && !in_array($enemy['spellpoints'],[0,1,'0','1'],true)) throw new \DomainException('Invalid creature ability state.');
            foreach (['cannotbetarget','essentialleader','fleesifalone','alwaysattacks'] as $key) {
                if (!empty($enemy[$key])) throw new \DomainException('Nonordinary combat flag.');
            }
            if (!empty($enemy['killedplayer'])) throw new \DomainException('Terminal Forest target.');
            // The battle engine retains defeated enemies for final reward settlement.
            // They remain immutable participants, never eligible targets. Both the
            // numeric result and terminal flags must agree before another round.
            if ($enemy['creaturehealth'] <= 0) {
                if (empty($enemy['dead']) || !empty($enemy['istarget'])) throw new \DomainException('Inconsistent defeated target.');
                continue;
            }
            if (!empty($enemy['dead'])) throw new \DomainException('Inconsistent live target.');
            if (empty($enemy['cannotbetarget'])) $available++;
            if (!empty($enemy['istarget'])) {
                if (!empty($enemy['cannotbetarget'])) throw new \DomainException('Ineligible combat target.');
                $targets++;
            }
        }
        // Reward ledgers can contain only defeated, retained participants.
        foreach (['experience','experiencegained'] as $ledger) {
            foreach ($options[$ledger] ?? [] as $index=>$reward) {
                $enemy = $state['enemies'][$index];
                $expected = $ledger === 'experience' ? $enemy['creatureexp'] : round($enemy['creatureexp']/count($state['enemies']));
                if (empty($enemy['dead']) || $enemy['creaturehealth'] > 0 || $reward != $expected ||
                    ($ledger === 'experiencegained' && empty($enemy['expgained']))) throw new \DomainException('Inconsistent combat rewards.');
            }
        }
        // A newly generated encounter may not yet have autosettarget's flag.
        if ($targets > 1) throw new \DomainException('Invalid Forest target.');
        if ($available === 0) throw new \DomainException('No Forest target.');
        return $state;
    }

    private static function number(mixed $value, int $min, int $max): void
    {
        // PDO creature rows contain numeric strings; derived combat values are numbers.
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match($min < 0 ? '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/D' : '/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value < $min || $value > $max) {
            throw new \DomainException('Invalid combat statistic.');
        }
    }

    private static function flag(mixed $value): void
    {
        if (!in_array($value,[false,true,0,1,'0','1'],true)) throw new \DomainException('Invalid combat flag.');
    }
}
