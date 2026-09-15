<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Minimum business schema for the single-target shipped PvP route only. */
final class PvpState
{
    /** @return array<string,mixed> */
    public static function read(string $stored, int $owner): array
    {
        $state = ScalarState::read($stored);
        if (!is_array($state) || !is_array($state['options'] ?? null) ||
            !is_array($state['enemies'] ?? null) || array_keys($state['enemies']) !== [0]) {
            throw new \DomainException('No active PvP combat.');
        }
        $options = $state['options'];
        if (($options['type'] ?? null) !== 'pvp' || ($options['owner'] ?? null) !== $owner ||
            !is_string($options['encounter'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/', $options['encounter']) ||
            !is_int($options['target'] ?? null) || $options['target'] < 1 || $options['target'] === $owner ||
            !is_string($options['reservation'] ?? null)) {
            throw new \DomainException('Invalid PvP authority.');
        }
        $enemy = $state['enemies'][0];
        if (!is_array($enemy)) { throw new \DomainException('Invalid PvP enemy.'); }
        foreach (['acctid','creaturelevel','creaturehealth','creatureattack','creaturedefense','creatureexp','creaturegold','playerstarthp','fightstartdate'] as $key) {
            $value = $enemy[$key] ?? null;
            if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) ||
                !is_finite((float)$value) || (float)$value < 0 || (float)$value > 2147483647) {
                throw new \DomainException('Invalid PvP numeric state.');
            }
        }
        if ((int)$enemy['acctid'] !== $options['target'] || $enemy['creaturelevel'] < 1 || $enemy['creaturehealth'] <= 0 || !empty($enemy['dead'])) {
            throw new \DomainException('Invalid or completed PvP combat.');
        }
        foreach (['creaturename','creatureweapon','location'] as $key) {
            if (!is_string($enemy[$key] ?? null) || strlen($enemy[$key]) > 255) {
                throw new \DomainException('Invalid PvP text state.');
            }
        }
        return $state;
    }
}
