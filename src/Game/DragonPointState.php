<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

/** Permanent allocation vocabulary and limits, independent of New Day resets. */
final class DragonPointState
{
    public const UINT_MAX = 4294967295;

    public static function integer(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) ||
            !preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string)$value) ||
            strlen((string)$value) > 10 || (float)$value > self::UINT_MAX) {
            throw new \DomainException('Invalid Dragon Point integer.');
        }
        return (int)$value;
    }

    /** @return list<string> */
    public static function read(mixed $encoded, mixed $kills): array
    {
        $limit = self::integer($kills);
        $points = ScalarState::read($encoded);
        if (!is_string($encoded) || strlen($encoded) > 65535 || !is_array($points) ||
            !array_is_list($points) || count($points) > $limit) {
            throw new \DomainException('Invalid Dragon Point history.');
        }
        // Removed module identifiers remain readable, never implicitly buyable.
        foreach ($points as $point) {
            if (!is_string($point) || !preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $point)) {
                throw new \DomainException('Invalid historical allocation.');
            }
        }
        return $points;
    }

    /** @return array{desc:array<string,string>,buy:array<string,bool>} */
    public static function schema(mixed $schema): array
    {
        if (!is_array($schema) || !is_array($schema['desc'] ?? null) || !is_array($schema['buy'] ?? null) ||
            array_diff(array_keys($schema['buy']), array_keys($schema['desc'])) !== []) {
            throw new \DomainException('Invalid allocation schema.');
        }
        $desc = []; $buy = [];
        foreach ($schema['desc'] as $key => $label) {
            if (!is_string($key) || !preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $key) ||
                in_array($key, ['csrf_token','action_token','allocation','type'], true) ||
                !is_string($label) || $label === '' || strlen($label) > 1024 ||
                !in_array($schema['buy'][$key] ?? null, [0,1,false,true], true)) {
                throw new \DomainException('Invalid allocation declaration.');
            }
            $desc[$key] = $label; $buy[$key] = (bool)$schema['buy'][$key];
        }
        if (($buy['unknown'] ?? false) || !isset($desc['unknown'])) throw new \DomainException('Unknown spends are display-only.');
        ksort($desc); ksort($buy);
        return ['desc'=>$desc, 'buy'=>$buy];
    }

    /** Reject duplicate keys and PHP's ambiguous key normalization before using parsed input.
     * @param array<string,mixed> $post
     */
    public static function transport(string $body, array $post): void
    {
        if ($body === '' || strlen($body) > 16384) throw new \InvalidArgumentException('Invalid form body.');
        $raw = [];
        foreach (explode('&', $body) as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if (count($parts) !== 2 || !preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $key) || array_key_exists($key, $raw)) {
                throw new \InvalidArgumentException('Ambiguous form key.');
            }
            $raw[$key] = urldecode($parts[1]);
        }
        ksort($raw); ksort($post);
        if ($raw !== $post) throw new \InvalidArgumentException('Ambiguous form data.');
    }

    /** @param array<string,mixed> $counts
     * @param array<string,bool> $buy
     * @return array<string,int>
     */
    public static function counts(array $counts, array $buy, int $unspent): array
    {
        $allowed = array_keys(array_filter($buy));
        if ($unspent < 1 || array_diff(array_keys($counts), $allowed) !== [] ||
            array_diff($allowed, array_keys($counts)) !== []) throw new \InvalidArgumentException('Invalid allocation types.');
        $result = []; $total = 0;
        foreach ($counts as $type => $value) {
            try { $number = self::integer($value); }
            catch (\DomainException $error) { throw new \InvalidArgumentException('Invalid allocation count.', 0, $error); }
            if ($number > $unspent - $total) throw new \InvalidArgumentException('Allocation exceeds available points.');
            $result[$type] = $number; $total += $number;
        }
        if ($total !== $unspent) throw new \InvalidArgumentException('Spend the exact available total.');
        ksort($result);
        return $result;
    }

    /** @param array<string,mixed> $player
     * @param array<string,int> $counts
     * @return array{dragonpoints:list<string>,maxhitpoints:int,attack:int,defense:int}
     */
    public static function allocate(array $player, array $counts): array
    {
        $points = self::read(serialize($player['dragonpoints']), $player['dragonkills']);
        $unspent = self::integer($player['dragonkills']) - count($points);
        // ScalarState's 10,000-node ceiling applies to the resulting collection too.
        if ($unspent < 1 || array_sum($counts) !== $unspent || count($points) + $unspent > 9999) {
            throw new \DomainException('Allocation cannot be persisted.');
        }
        $result = [];
        foreach (['maxhitpoints'=>['hp',5], 'attack'=>['at',1], 'defense'=>['de',1]] as $field => [$type,$multiplier]) {
            $current = self::integer($player[$field]);
            $increment = ($counts[$type] ?? 0) * $multiplier;
            if ($increment > self::UINT_MAX - $current) throw new \DomainException('Permanent stat overflow.');
            $result[$field] = $current + $increment;
        }
        foreach ($counts as $type => $count) {
            if ($count < 0) throw new \DomainException('Invalid count.');
            for ($i=0; $i<$count; $i++) $points[] = $type;
        }
        self::read(serialize($points), $player['dragonkills']);
        return ['dragonpoints'=>$points, 'maxhitpoints'=>$result['maxhitpoints'], 'attack'=>$result['attack'], 'defense'=>$result['defense']];
    }
}
