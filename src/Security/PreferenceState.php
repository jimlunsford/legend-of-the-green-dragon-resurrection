<?php
declare(strict_types=1);
namespace Resurrection\Security;

/**
 * Business schema for an account's `prefs` map. prefs.php saves each posted preference field by name, as 1.1.2
 * did, so the map holds the form's fields, the client-side `showFormTabIndex`, and any field an older form posted.
 * Every key is a field name and every value a scalar. The fields the game reads have the types their readers need.
 */
final class PreferenceState
{
    /** The `prefs` column is TEXT. */
    public const STORED_LENGTH = 65535;

    /** Yes/no fields: showform's bool select posts "0" or "1"; holiday_texts.php stores the int 0. */
    private const FLAGS = ['tabconfig', 'emailonmail', 'systemmail', 'dirtyemail', 'ihavenocheer', 'nojump'];

    /**
     * The stored `prefs` column. Empty (or NULL) is an account that never saved preferences, as 1.1.2 created it.
     * @return array<string, scalar>
     */
    public static function read(mixed $stored): array
    {
        if ($stored === null || $stored === '') { return []; }
        $prefs = ScalarState::read($stored);
        if (!is_array($prefs)) { throw new \DomainException('Invalid stored preferences.'); }
        return self::map($prefs);
    }

    /**
     * Validates a preference map and returns it with a blank time offset read as 0, as 1.1.2's arithmetic did.
     * PHP 8 throws a TypeError on `"" * 3600`, which prefs.php computes.
     * @return array<string, scalar>
     */
    public static function map(mixed $prefs): array
    {
        if (!is_array($prefs)) { throw new \DomainException('Invalid preferences.'); }
        foreach ($prefs as $key => $value) {
            // PHP posts never carry spaces, dots or control characters in a field name.
            if (!is_string($key) || !preg_match('/\A[^\x00-\x20\x7F]{1,255}\z/', $key) || !self::scalar($value)) {
                throw new \DomainException('Invalid preferences.');
            }
        }
        foreach (self::FLAGS as $key) {
            if (array_key_exists($key, $prefs) && !in_array($prefs[$key], [true, false, 0, 1, '0', '1'], true)) {
                throw new \DomainException("Invalid preference $key.");
            }
        }
        if (array_key_exists('timestamp', $prefs) && !in_array($prefs['timestamp'], [0, 1, 2, '0', '1', '2'], true)) {
            throw new \DomainException('Invalid preference timestamp.');
        }
        if (array_key_exists('timeoffset', $prefs)) {
            if ($prefs['timeoffset'] === '') { $prefs['timeoffset'] = 0; }
            $offset = $prefs['timeoffset'];
            // Hours added to a Unix time; the bound keeps the sum a valid timestamp.
            if (is_bool($offset) || !is_numeric($offset) || !is_finite((float)$offset) || abs((float)$offset) > 1000000) {
                throw new \DomainException('Invalid preference timeoffset.');
            }
        }
        if (array_key_exists('timeformat', $prefs) && !is_string($prefs['timeformat'])) {
            throw new \DomainException('Invalid preference timeformat.');
        }
        // A language code: translator.php falls back to the default for any other code, and untranslated.php
        // puts the value in its SQL.
        if (array_key_exists('language', $prefs) &&
            (!is_string($prefs['language']) || !preg_match('/\A[A-Za-z0-9_-]{0,32}\z/', $prefs['language']))) {
            throw new \DomainException('Invalid preference language.');
        }
        /** @var array<string, scalar> $prefs */
        return $prefs;
    }

    /**
     * The value to store: a valid map, serialized, within the column. A value still in its stored (string) form, as
     * an account row loaded without hydration carries it, is checked and kept as it is.
     */
    public static function encode(mixed $prefs): string
    {
        if (is_string($prefs)) { self::read($prefs); return $prefs; }
        $encoded = serialize(self::map($prefs));
        if (strlen($encoded) > self::STORED_LENGTH) { throw new \DomainException('Preferences exceed storage capacity.'); }
        return $encoded;
    }

    private static function scalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_bool($value) || (is_float($value) && is_finite($value));
    }
}
