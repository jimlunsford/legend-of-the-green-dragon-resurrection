<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Explicit implementation of the sole AI behavior in the historical seeds. */
final class CreatureAi
{
    public const LEGACY_SHA256 = 'b10d2a3635b2c5004234438f3b53ac9c8f048f9a221652b2c55a1ea37e56dc6c';

    public static function supported(string $script): bool
    {
        return $script === 'bundled:gypsy-bandit' || hash_equals(self::LEGACY_SHA256, hash('sha256', trim(str_replace("\r\n", "\n", $script))));
    }

    /**
     * @param array<string, mixed> $player
     * @param array<string, mixed> $creature
     */
    public static function apply(string $script, array &$player, array &$creature, int $roll): float
    {
        if (!self::supported($script)) throw new \DomainException('Unsupported creature behavior.');
        if ($roll < 0 || $roll > 7) throw new \InvalidArgumentException('Invalid creature chance.');
        $creature['spellpoints'] ??= 1;
        $gold = round((float)$player['gold'] * 0.2);
        if ($roll === 0 && $gold > 200 && $creature['spellpoints'] == 1) {
            $player['gold'] -= $gold;
            $creature['creaturegold'] += $gold;
            $creature['spellpoints']--;
            return $gold;
        }
        return 0.0;
    }
}
