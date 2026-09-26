<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** The single shipped Dragon, including its pre-envelope legacy representation. */
final class DragonCombatState
{
    /** @return array<string,mixed> */
    public static function read(mixed $encoded): array
    {
        $state = ScalarState::read($encoded);
        if (!is_array($state)) throw new \DomainException('Invalid Dragon state.');
        if (isset($state['creaturename'])) $state = ['enemies'=>[$state], 'options'=>['type'=>'dragon']];
        if (array_diff(array_keys($state), ['enemies','options']) !== [] ||
            !is_array($state['enemies'] ?? null) || array_keys($state['enemies']) !== [0] ||
            !is_array($state['options'] ?? null)) throw new \DomainException('Invalid Dragon encounter.');
        $options = $state['options'];
        if (($options['type'] ?? null) !== 'dragon' ||
            array_diff(array_keys($options), ['type','maxattacks','didsurprise']) !== []) {
            throw new \DomainException('Invalid Dragon options.');
        }
        if (isset($options['maxattacks'])) self::number($options['maxattacks'], 1);
        if (isset($options['didsurprise'])) self::flag($options['didsurprise']);
        $enemy = $state['enemies'][0];
        if (!is_array($enemy) || array_diff(array_keys($enemy), ['creaturename','creatureweapon',
            'creaturelevel','creatureattack','creaturedefense','creaturehealth','diddamage','type',
            'dead','istarget','playerstarthp','killedplayer']) !== []) throw new \DomainException('Invalid Dragon fields.');
        foreach (['creaturename','creatureweapon'] as $key) {
            if (!is_string($enemy[$key] ?? null) || $enemy[$key] === '' || strlen($enemy[$key]) > 4096) {
                throw new \DomainException('Invalid Dragon text.');
            }
        }
        foreach (['creaturelevel','creaturehealth','creatureattack','creaturedefense'] as $key) {
            self::number($enemy[$key] ?? null, in_array($key,['creaturelevel','creaturehealth'],true) ? 1 : 0);
        }
        if ((float)$enemy['creaturelevel'] !== 18.0 || ($enemy['type'] ?? null) !== 'dragon') {
            throw new \DomainException('Invalid Dragon identity.');
        }
        self::flag($enemy['diddamage'] ?? null);
        foreach (['dead','istarget','killedplayer'] as $key) if (isset($enemy[$key])) self::flag($enemy[$key]);
        if (isset($enemy['playerstarthp'])) self::number($enemy['playerstarthp'], 1);
        if (!empty($enemy['dead']) || !empty($enemy['killedplayer'])) throw new \DomainException('Terminal Dragon.');
        return $state;
    }

    /** @return array{dragonVictory:bool,dragonkills:int} */
    public static function victory(mixed $encoded, int $kills): array
    {
        $state = ScalarState::read($encoded);
        if (!is_array($state) || count($state) !== 2 || !is_bool($state['dragonVictory'] ?? null) ||
            ($state['dragonkills'] ?? null) !== $kills) throw new \DomainException('No pending Dragon victory.');
        return ['dragonVictory'=>$state['dragonVictory'], 'dragonkills'=>$kills];
    }

    private static function number(mixed $value, int $min): void
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value < $min || $value > 2147483647) {
            throw new \DomainException('Invalid Dragon statistic.');
        }
    }

    private static function flag(mixed $value): void
    {
        if (!in_array($value,[false,true,0,1,'0','1'],true)) throw new \DomainException('Invalid Dragon flag.');
    }
}
