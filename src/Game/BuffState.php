<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

// Every page, login and save reaches this schema, some without the producer schemas loaded.
require_once __DIR__ . '/../Security/ScalarState.php';
require_once __DIR__ . '/Expression.php';
require_once __DIR__ . '/SpecialtyBuffState.php';
require_once __DIR__ . '/TransmutationState.php';

/**
 * Business schema for a player's buff list (`accounts.bufflist`), each buff in it, and a mount's buff
 * (`mounts.mountbuff`). It accepts every buff shape the 1.1.2 installer and the shipped code write, including the
 * text numbers 1.1.2 stored (the installer mounts, the mount editor, the drinks table), the legacy `activate`
 * metadata (the installer mounts, Crazy Audrey) and the lovers' translated wear-off message, and returns each
 * value in the type combat uses: whole numbers as int, other numbers as float, yes/no flags as bool or int.
 * Expressions stay text; calculate_buff_fields() evaluates them through Expression's shipped vocabulary.
 * The bundled specialty buffs and the transmutation potion keep their exact producer schemas.
 *
 * Refused: a key outside the vocabulary (calculate_buff_fields() would evaluate any text field holding an
 * expression), a missing or out-of-range duration, a non-finite or out-of-range number, an expression outside the
 * shipped vocabulary, a value of the wrong type, text over 4096 bytes, more than 100 buffs, and a list whose
 * serialization the column cannot hold.
 */
final class BuffState
{
    /** The `bufflist` column is TEXT. */
    public const STORED_LENGTH = 65535;
    public const MAX_BUFFS = 100;
    public const TEXT_LENGTH = 4096;
    public const MAX_ROUNDS = 100000;

    /** Numbers combat multiplies, adds or compares (lib/battle-buffs.php, lib/buffs.php apply_temp_stat()). */
    private const NUMBERS = ['atkmod','defmod','dmgmod','badguyatkmod','badguydefmod','badguydmgmod','lifetap','damageshield',
        'regen','minioncount','minbadguydamage','maxbadguydamage','mingoodguydamage','maxgoodguydamage','compatkmod','compdefmod',
        'tempstat-attack','tempstat-defense','tempstat-maxhitpoints'];
    /** Multipliers and counts that are never negative. */
    private const NON_NEGATIVE = ['minioncount','lifetap','damageshield','atkmod','defmod','dmgmod','badguyatkmod','badguydefmod','badguydmgmod'];
    /** Yes/no fields: the shipped writers set true/1, the battle sets used and suspended to 0 or 1, 1.1.2 text "0"/"1". */
    private const FLAGS = ['areadamage','aura','invulnerable','suspended','used','fields_calculated','tempstats_calculated',
        'allowinpvp','allowintrain','allowintravel','expireafterfight','survive_newday','survivenewday','goodmusthit','badmusthit'];
    /** Messages and presentation. */
    private const TEXTS = ['name','startmsg','roundmsg','wearoff','effectmsg','effectnodmgmsg','effectfailmsg','auramsg','schema','newdaymessage'];
    /** 1.1.2's activation metadata; the battle engine decides activation from the effects and ignores it. */
    private const ACTIVATE = '/\A(?:roundstart|offense|defense)(?:,(?:roundstart|offense|defense))*\z/';
    /** modules/lovers.php lovers_getbuff(): the wear-off message is translated with the partner's name. */
    private const LOVER_WEAROFF = '`!You miss %s`!.`0';

    /**
     * The stored `bufflist` column, normalized. Empty (or NULL) is no buffs: 1.1.2's create.php left the column empty
     * and its New Day blanked it before the save wrote the new list. Anything else must be a valid list.
     * @param array<string,mixed> $player the stats an expression reads
     * @return array<string,array<string,mixed>>
     */
    public static function read(mixed $stored, array $player): array
    {
        if ($stored === null || $stored === '') return [];
        return self::collection(ScalarState::read($stored), $player);
    }

    /**
     * A whole buff list, each buff normalized.
     * @param array<string,mixed> $player
     * @return array<string,array<string,mixed>>
     */
    public static function collection(mixed $buffs, array $player): array
    {
        if (!is_array($buffs)) throw new \DomainException('Invalid buff list.');
        if (count($buffs) > self::MAX_BUFFS) throw new \DomainException('Too many buffs.');
        $result = [];
        foreach ($buffs as $id => $buff) {
            if (!is_string($id) || $id === '' || strlen($id) > 128) throw new \DomainException('Invalid buff name.');
            $result[$id] = self::buff($id, $buff, $player);
        }
        return $result;
    }

    /**
     * One buff under its name, as apply_buff() stores it, normalized.
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function buff(string $id, mixed $buff, array $player): array
    {
        if (!is_array($buff)) throw new \DomainException('Invalid buff.');
        // Producer-exact schemas: the bundled specialties and the transmutation potion.
        if (SpecialtyBuffState::covers($id, $buff)) { SpecialtyBuffState::validate($id, $buff); return $buff; }
        if ($id === 'transmute') return TransmutationState::read($buff);
        foreach (array_keys($buff) as $key) {
            if (!is_string($key) || !(in_array($key, self::NUMBERS, true) || in_array($key, self::FLAGS, true) ||
                in_array($key, self::TEXTS, true) || $key === 'rounds' || $key === 'activate')) throw new \DomainException('Invalid buff fields.');
        }
        $buff['rounds'] = self::rounds($buff['rounds'] ?? null);
        foreach (self::TEXTS as $key) {
            if (!array_key_exists($key, $buff)) continue;
            $value = $buff[$key];
            if ($id === 'lover' && $key === 'wearoff' && is_array($value)) {
                if (!array_is_list($value) || count($value) !== 2 || $value[0] !== self::LOVER_WEAROFF ||
                    !is_string($value[1]) || strlen($value[1]) > self::TEXT_LENGTH) throw new \DomainException('Invalid lover message.');
                continue;
            }
            if (!is_string($value) || strlen($value) > self::TEXT_LENGTH) throw new \DomainException('Invalid buff message.');
        }
        // The character stats and the battle read both on every buff; 1.1.2 read a missing one as empty.
        $buff += ['name' => '', 'schema' => ''];
        foreach (self::FLAGS as $key) {
            if (!array_key_exists($key, $buff)) continue;
            if (!in_array($buff[$key], [true, false, 0, 1, '0', '1'], true)) throw new \DomainException('Invalid buff flag.');
            if (is_string($buff[$key])) $buff[$key] = (int)$buff[$key];
        }
        if (array_key_exists('activate', $buff) && (!is_string($buff['activate']) || !preg_match(self::ACTIVATE, $buff['activate']))) {
            throw new \DomainException('Invalid buff activation.');
        }
        foreach (self::NUMBERS as $key) {
            if (array_key_exists($key, $buff)) $buff[$key] = self::number($key, $buff[$key], $player);
        }
        if (array_key_exists('minioncount', $buff)) {
            $count = $buff['minioncount'];
            if (is_float($count) && floor($count) === $count) $count = (int)$count;
            if (!is_int($count) || $count > 1000) throw new \DomainException('Invalid minion count.');
            $buff['minioncount'] = $count;
        }
        return $buff;
    }

    /**
     * A mount's stored buff as the stables, the mount editor and New Day apply it: the buff schema, with the
     * "mounts" translation schema when the buff names none (1.1.2's stables and New Day did the same).
     * @param array<string,mixed> $player
     * @return array<string,mixed>
     */
    public static function mount(mixed $buff, array $player): array
    {
        if (!is_array($buff)) throw new \DomainException('Invalid mount buff.');
        if (!isset($buff['schema']) || $buff['schema'] === '') $buff['schema'] = 'mounts';
        return self::buff('mount', $buff, $player);
    }

    /**
     * The value to store: a valid list, normalized and serialized, within the column.
     * @param array<string,mixed> $player
     */
    public static function encode(mixed $buffs, array $player): string
    {
        $encoded = serialize(self::collection($buffs, $player));
        if (strlen($encoded) > self::STORED_LENGTH) throw new \DomainException('Buffs exceed storage capacity.');
        return $encoded;
    }

    /** A duration: -1 (until the buff is removed) or 1 to 100000 rounds; 1.1.2 stored text digits. */
    private static function rounds(mixed $value): int
    {
        if (is_string($value) && preg_match('/\A-?(?:0|[1-9][0-9]{0,8})\z/', $value)) $value = (int)$value;
        if (!is_int($value) || $value < -1 || $value === 0 || $value > self::MAX_ROUNDS) throw new \DomainException('Invalid buff duration.');
        return $value;
    }

    /**
     * A number as combat uses it. Text digits become int or float; an expression stays text once it evaluates, for
     * this player, to a value in range (calculate_buff_fields() evaluates it again when the buff is used).
     * @param array<string,mixed> $player
     */
    private static function number(string $key, mixed $value, array $player): int|float|string
    {
        if (is_string($value) && str_contains($value, '<')) {
            $number = Expression::evaluate($value, $player);
        } elseif (is_string($value) && strlen($value) <= 32 && preg_match('/\A-?(?:(?:0|[1-9][0-9]*)(?:\.[0-9]+)?|\.[0-9]+)\z/', $value)) {
            $number = preg_match('/\A-?(?:0|[1-9][0-9]{0,9})\z/', $value) && abs((float)$value) <= 2147483647 ? (int)$value : (float)$value;
            $value = $number;
        } elseif (is_int($value) || is_float($value)) {
            $number = $value;
        } else {
            throw new \DomainException('Invalid buff statistic.');
        }
        if (!is_finite((float)$number) || abs((float)$number) > 2147483647 || (in_array($key, self::NON_NEGATIVE, true) && $number < 0)) {
            throw new \DomainException('Invalid buff statistic.');
        }
        return $value;
    }
}
