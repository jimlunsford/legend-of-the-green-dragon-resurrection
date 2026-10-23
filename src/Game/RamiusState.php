<?php
declare(strict_types=1);
namespace Resurrection\Game;

/** Server-owned same-day resurrection rules; no request values enter settlement. */
final class RamiusState
{
    /** Historical amount setting accepts signed whole fights or signed whole percent.
     * @return array{value:int,percent:bool}
     */
    public static function setting(mixed $value): array
    {
        if ((!is_int($value) && !is_string($value)) ||
            !preg_match('/\A([+-]?)(0|[1-9][0-9]*)(%?)\z/', (string)$value, $match)) {
            throw new \DomainException('Invalid resurrection turn setting.');
        }
        // Percentages above UINT_MAX may still produce a persistable adjustment.
        // Bound the setting by the largest useful percentage for a nonzero pool,
        // not by the storage domain of an absolute fight count.
        // Negative magnitudes have no lower limit: the historical rule clamps them.
        $negative = $match[1] === '-';
        $magnitude = $match[2];
        $limit = $match[3] === '%' ? 429496729500 : 4294967295;
        if (strlen($magnitude) > strlen((string)$limit) || (float)$magnitude > $limit) {
            if (!$negative) throw new \DomainException('Resurrection adjustment overflow.');
            $magnitude = '4294967295';
        }
        return ['value'=>($negative ? -1 : 1) * (int)$magnitude, 'percent'=>$match[3] === '%'];
    }

    public static function adjustment(mixed $setting, int $pool): int
    {
        NewDayState::integer($pool);
        $rule = self::setting($setting);
        if (!$rule['percent']) {
            $result = max(-$pool, $rule['value']);
            if ($result > 4294967295 - $pool) throw new \DomainException('Resurrection turn overflow.');
            return $result;
        }
        $percent = max(-100, $rule['value']);
        // Quotient/remainder arithmetic avoids a potentially overflowing pool*percent.
        $whole = intdiv($pool, 100) * $percent;
        $remainder = ($pool % 100) * $percent;
        $rounded = intdiv(abs($remainder), 100) + (abs($remainder) % 100 >= 50 ? 1 : 0);
        $result = $whole + ($remainder < 0 ? -$rounded : $rounded);
        if ($result > 4294967295 - $pool) throw new \DomainException('Resurrection turn overflow.');
        return $result;
    }

    /** @param array<string,mixed> $player */
    public static function eligible(array $player, string $day, bool $requireFavor = true): void
    {
        GraveyardCombatState::player($player);
        if (($player['lastnewday'] ?? null) !== $day || ($player['badguy'] ?? null) !== '' ||
            ($player['specialinc'] ?? null) !== '') throw new \DomainException('Not a clean same-day death.');
        if ($requireFavor && NewDayState::integer($player['deathpower']) < 100) throw new \DomainException('Insufficient favor.');
        NewDayState::integer($player['maxhitpoints'] ?? null, 1, 2147483647);
        NewDayState::integer($player['age'] ?? null, 0, 4294967294);
        NewDayState::integer($player['resurrections'] ?? null, 0, 4294967294);
    }
}
