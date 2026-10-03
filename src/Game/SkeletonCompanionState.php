<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Business state of the one companion created by bundled Dark Arts. */
final class SkeletonCompanionState
{
    /** Validate a ScalarState-decoded companion map without filtering invalid entries.
     * Other named companions retain their existing array representation, not certification.
     * @return array<int|string, array<mixed>>
     */
    public static function companions(mixed $state): array
    {
        if (!is_array($state)) { throw new \DomainException('Invalid companion state.'); }
        foreach ($state as $name => $companion) {
            if (!is_array($companion)) { throw new \DomainException('Invalid companion state.'); }
            if ($name === 'skeleton_warrior') { self::validate($companion); }
        }
        return $state;
    }

    public static function validate(mixed $state): void
    {
        $required = ['name','hitpoints','maxhitpoints','attack','defense','dyingtext','abilities','ignorelimit'];
        if (!is_array($state) || array_diff($required, array_keys($state)) !== [] ||
            array_diff(array_keys($state), [...$required, 'used', 'suspended']) !== []) {
            throw new \DomainException('Invalid skeleton companion.');
        }
        if ($state['name'] !== '`4Skeleton Warrior' ||
            $state['dyingtext'] !== '`$Your skeleton warrior crumbles to dust.`n' ||
            $state['abilities'] !== ['fight'=>true] || $state['ignorelimit'] !== true) {
            throw new \DomainException('Invalid skeleton companion.');
        }
        foreach (['used','suspended'] as $flag) {
            if (array_key_exists($flag, $state) && !is_bool($state[$flag])) {
                throw new \DomainException('Invalid skeleton flag.');
            }
        }
        foreach (['hitpoints','maxhitpoints','attack','defense'] as $field) {
            $number = $state[$field];
            if ((!is_int($number) && !is_float($number)) || !is_finite((float)$number) ||
                $number <= 0 || $number > 2147483647) {
                throw new \DomainException('Invalid skeleton statistic.');
            }
        }
        if ($state['hitpoints'] > $state['maxhitpoints']) {
            throw new \DomainException('Invalid skeleton health.');
        }
        // Creation level is recoverable from max HP. Do not bind to today's player
        // level: the skeleton survives training and New Day. Dragon reset removes it.
        $level = round(($state['maxhitpoints'] - 10) / 3.33);
        if ($level < 1 || $state['maxhitpoints'] != round($level * 3.33) + 10 ||
            $state['attack'] != round($level / 4 + 2) * round($level / 3 + 2) + 1.5 ||
            $state['defense'] != floor($level / 3) * ceil($level / 6 + 2) + 2.5) {
            throw new \DomainException('Invalid skeleton creation statistics.');
        }
    }
}
