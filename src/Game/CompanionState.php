<?php
declare(strict_types=1);
namespace Resurrection\Game;

use Resurrection\Security\ScalarState;

// Every page and save reaches this schema, some without the producer schemas loaded.
require_once __DIR__ . '/../Security/ScalarState.php';
require_once __DIR__ . '/SkeletonCompanionState.php';

/**
 * Business schema for a player's companion list (`accounts.companions`), each companion in it, and a companion's
 * abilities (`companions.abilities`). It accepts every companion shape the 1.1.2 installer and the shipped code write:
 * a mercenary camp or companion editor row as the database returns it (text numbers and flags), scaled to the player's
 * level, the bundled Dark Arts skeleton warrior, and the runtime flags combat adds (`used`, `suspended`). Each value is
 * returned in the type combat uses: whole numbers as int, other numbers as float, text flags as int.
 * The skeleton warrior keeps its exact producer schema (SkeletonCompanionState).
 *
 * Refused: a key outside the vocabulary combat, the character stats and the camps read, a missing statistic, a
 * negative, non-finite or out-of-range number, impossible health, a wrong type, oversized text, abilities outside the
 * camp and editor vocabulary, more than 100 companions, and a list whose serialization the column cannot hold.
 */
final class CompanionState
{
    /** The `companions` column is TEXT. */
    public const STORED_LENGTH = 65535;
    public const MAX_COMPANIONS = 100;
    /** The list key and the name: `companions.name` is varchar(255). */
    public const NAME_LENGTH = 255;
    /** The companion editor's longest field (description, join text). */
    public const TEXT_LENGTH = 8192;
    public const MAX_NUMBER = 2147483647;
    /** The editor offers healer and magician levels 0 to 30, as 1.1.2's did. */
    public const MAX_ABILITY_LEVEL = 30;

    /** The statistics combat rolls and heals with, and the row's level and cost columns. */
    private const NUMBERS = ['hitpoints','maxhitpoints','attack','defense','companionid','attackperlevel','defenseperlevel',
        'maxhitpointsperlevel','companioncostdks','companioncostgems','companioncostgold'];
    private const TEXTS = ['name','category','description','companionlocation','jointext','dyingtext','schema',
        'healcompanionmsg','healmsg','magicmsg','magicfailmsg'];
    /** Yes/no fields: the database returns "0"/"1", the battle writes true/false, apply_companion() writes true. */
    private const FLAGS = ['used','suspended','ignorelimit','cannotdie','cannotbehealed','companionactive',
        'allowinshades','allowinpvp','allowintrain','expireafterfight'];
    private const REQUIRED = ['name','hitpoints','maxhitpoints','attack','defense','abilities'];
    /** report_companion_move() reads these without isset(); 1.1.2 read a missing one as empty and used its default. */
    private const MESSAGES = ['healmsg','healcompanionmsg','magicfailmsg'];
    private const ABILITY_FLAGS = ['fight','defend'];
    private const ABILITY_LEVELS = ['heal','magic'];

    /**
     * The stored `companions` column, normalized. Empty (or NULL) is no companions: 1.1.2's create.php left the column
     * unset and every 1.1.2 writer stored a serialized array. Anything else must be a valid list.
     * @return array<int|string,array<string,mixed>>
     */
    public static function read(mixed $stored): array
    {
        if ($stored === null || $stored === '') return [];
        return self::collection(ScalarState::read($stored));
    }

    /**
     * A whole companion list, each companion normalized, in its order.
     * @return array<int|string,array<string,mixed>>
     */
    public static function collection(mixed $companions): array
    {
        if (!is_array($companions)) throw new \DomainException('Invalid companion state.');
        if (count($companions) > self::MAX_COMPANIONS) throw new \DomainException('Invalid companion count.');
        $result = [];
        foreach ($companions as $id => $companion) {
            // The mercenary camp keys a companion by its name; PHP stores a name such as "12" as an int key.
            if ($id === '' || strlen((string)$id) > self::NAME_LENGTH) throw new \DomainException('Invalid companion name.');
            $result[$id] = self::companion((string)$id, $companion);
        }
        return $result;
    }

    /**
     * One companion under its list key, as apply_companion() stores it, normalized.
     * @return array<string,mixed>
     */
    public static function companion(string $id, mixed $companion): array
    {
        if (!is_array($companion)) throw new \DomainException('Invalid companion state.');
        // Producer-exact schema: the bundled Dark Arts skeleton warrior.
        if ($id === 'skeleton_warrior') { SkeletonCompanionState::validate($companion); return $companion; }
        foreach (array_keys($companion) as $key) {
            if (!is_string($key) || !(in_array($key, self::NUMBERS, true) || in_array($key, self::TEXTS, true) ||
                in_array($key, self::FLAGS, true) || $key === 'abilities')) throw new \DomainException('Invalid companion fields.');
        }
        foreach (self::REQUIRED as $key) if (!array_key_exists($key, $companion)) throw new \DomainException('Missing companion statistic.');
        foreach (self::NUMBERS as $key) if (array_key_exists($key, $companion)) $companion[$key] = self::number($companion[$key]);
        foreach (self::TEXTS as $key) {
            if (array_key_exists($key, $companion) && (!is_string($companion[$key]) || strlen($companion[$key]) > self::TEXT_LENGTH)) {
                throw new \DomainException('Invalid companion text.');
            }
        }
        if ($companion['name'] === '' || strlen($companion['name']) > self::NAME_LENGTH) throw new \DomainException('Invalid companion name.');
        foreach (self::FLAGS as $key) if (array_key_exists($key, $companion)) $companion[$key] = self::flag($companion[$key]);
        $companion['abilities'] = self::abilities($companion['abilities']);
        if ($companion['maxhitpoints'] <= 0 || $companion['hitpoints'] > $companion['maxhitpoints'] ||
            ($companion['hitpoints'] == 0 && empty($companion['cannotdie']))) throw new \DomainException('Invalid companion health.');
        $companion += array_fill_keys(self::MESSAGES, '');
        return $companion;
    }

    /**
     * A companion's abilities, as the camps, the editor and the battle read them: Fighter and Defender as yes/no,
     * the healer and magician levels as whole numbers from 0 to 30. Text values become int.
     * The installer's Mortimer and Florenz, the dwarf's Grizzly Bear and every editor save have all four; the
     * skeleton warrior has only `fight`.
     * @return array<string,bool|int>
     */
    public static function abilities(mixed $abilities): array
    {
        if (!is_array($abilities)) throw new \DomainException('Invalid companion abilities.');
        foreach ($abilities as $key => $value) {
            if (in_array($key, self::ABILITY_FLAGS, true)) {
                $abilities[$key] = self::flag($value);
            } elseif (in_array($key, self::ABILITY_LEVELS, true)) {
                if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]?)\z/', $value)) $value = (int)$value;
                if (!is_int($value) || $value < 0 || $value > self::MAX_ABILITY_LEVEL) throw new \DomainException('Invalid companion ability level.');
                $abilities[$key] = $value;
            } else {
                throw new \DomainException('Invalid companion abilities.');
            }
        }
        return $abilities;
    }

    /** The value to store: a valid list, normalized and serialized, within the column. */
    public static function encode(mixed $companions): string
    {
        $encoded = serialize(self::collection($companions));
        if (strlen($encoded) > self::STORED_LENGTH) throw new \DomainException('Companions exceed storage capacity.');
        return $encoded;
    }

    /** A statistic: never negative, finite, within the int columns; plain decimal text becomes int or float. */
    private static function number(mixed $value): int|float
    {
        if (is_string($value) && strlen($value) <= 32 && preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/', $value)) {
            $value = preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value) && (float)$value <= self::MAX_NUMBER ? (int)$value : (float)$value;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0 || $value > self::MAX_NUMBER) {
            throw new \DomainException('Invalid companion number.');
        }
        return $value;
    }

    /** A yes/no value: true, false, 0, 1, "0" or "1"; text becomes int. */
    private static function flag(mixed $value): bool|int
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) throw new \DomainException('Invalid companion flag.');
        return is_string($value) ? (int)$value : $value;
    }
}
