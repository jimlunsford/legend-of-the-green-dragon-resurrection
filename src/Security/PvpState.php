<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Strict business schema for the single-target shipped PvP route only. */
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
        if (array_diff(array_keys($state),['enemies','options'])!==[]) throw new \DomainException('Invalid PvP root.');
        $options = $state['options'];
        if (array_diff(array_keys($options),['type','owner','target','encounter','reservation','victimhash','enemyhash','maxattacks','didsurprise'])!==[]) throw new \DomainException('Invalid PvP options.');
        foreach (['victimhash','enemyhash'] as $key) if (!is_string($options[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$options[$key])) throw new \DomainException('Invalid PvP snapshot.');
        if (isset($options['maxattacks'])) {
            self::number($options['maxattacks'],1,100);
            if (floor((float)$options['maxattacks'])!=(float)$options['maxattacks']) throw new \DomainException('Invalid PvP attack limit.');
        }
        if (isset($options['didsurprise'])) self::flag($options['didsurprise']);
        if (($options['type'] ?? null) !== 'pvp' || ($options['owner'] ?? null) !== $owner ||
            !is_string($options['encounter'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/', $options['encounter']) ||
            !is_int($options['target'] ?? null) || $options['target'] < 1 || $options['target'] > 2147483647 || $options['target'] === $owner ||
            !is_string($options['reservation'] ?? null) || !preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$options['reservation'])) {
            throw new \DomainException('Invalid PvP authority.');
        }
        $enemy = $state['enemies'][0];
        if (!is_array($enemy)) { throw new \DomainException('Invalid PvP enemy.'); }
        if (($enemy['pvpflag'] ?? null)!==$options['reservation']) throw new \DomainException('Inconsistent PvP reservation.');
        $numbers=['acctid','creaturelevel','creaturehealth','creatureattack','creaturedefense','creatureexp','creaturegold','playerstarthp','fightstartdate','pvpmaxhp'];
        foreach ($numbers as $key) self::number($enemy[$key] ?? null);
        foreach (['acctid','creaturelevel','fightstartdate','pvpmaxhp'] as $key) if (floor((float)$enemy[$key])!=(float)$enemy[$key]) throw new \DomainException('Invalid PvP integer.');
        self::number($enemy['playerstarthp'],1); self::number($enemy['fightstartdate'],1);
        $metadata=['loggedin','location','laston','alive','pvpflag','boughtroomtoday','race','locked','age','dragonkills','pk','slaydragon'];
        if (array_diff(array_keys($enemy),[...$numbers,...$metadata,'creaturename','creatureweapon','dead','istarget','diddamage','bodyguardlevel'])!==[]) throw new \DomainException('Unexpected PvP enemy field.');
        foreach (['dead','istarget','diddamage'] as $key) if (array_key_exists($key,$enemy)) self::flag($enemy[$key]);
        if (isset($enemy['istarget']) && !$enemy['istarget']) throw new \DomainException('Invalid PvP target.');
        if (isset($enemy['bodyguardlevel'])) {
            self::number($enemy['bodyguardlevel'],1,5);
            if ((float)$enemy['bodyguardlevel']!==floor((float)$enemy['bodyguardlevel']) || $enemy['bodyguardlevel']!=($enemy['boughtroomtoday'] ?? null)) throw new \DomainException('Invalid bodyguard level.');
        }
        if (!hash_equals($options['enemyhash'],self::enemyHash($enemy))) throw new \DomainException('PvP opponent changed.');
        if ((int)$enemy['acctid'] !== $options['target'] || $enemy['creaturelevel'] < 1 || $enemy['creaturehealth'] <= 0 || $enemy['creaturehealth']>$enemy['pvpmaxhp'] || !empty($enemy['dead'])) {
            throw new \DomainException('Invalid or completed PvP combat.');
        }
        foreach (['creaturename','creatureweapon','location'] as $key) {
            if (!is_string($enemy[$key] ?? null) || strlen($enemy[$key]) > 255) {
                throw new \DomainException('Invalid PvP text state.');
            }
        }
        return $state;
    }

    /** Hash immutable, server-selected opponent fields, excluding round-local HP/flags.
     * @param array<string,mixed> $enemy
     */
    public static function enemyHash(array $enemy): string
    {
        foreach (['creaturehealth','dead','istarget','diddamage'] as $key) unset($enemy[$key]);
        foreach ($enemy as $key=>$value) {
            if (!is_scalar($value)) throw new \DomainException('Invalid opponent metadata.');
            $enemy[$key]=(string)$value;
        }
        ksort($enemy);
        return hash('sha256',serialize($enemy));
    }
    private static function number(mixed $value,int $min=0,int $max=2147483647): void
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) ||
            (is_string($value) && !preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/D',$value)) ||
            !is_numeric($value) || !is_finite((float)$value) || $value<$min || $value>$max) throw new \DomainException('Invalid PvP statistic.');
    }
    private static function flag(mixed $value): void
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new \DomainException('Invalid PvP flag.');
    }
}
