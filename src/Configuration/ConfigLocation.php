<?php
declare(strict_types=1);
namespace Resurrection\Configuration;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * Where the game's database configuration file is (Phase 6 task 3, S12; docs/PHASE6-TASK3-HARDENING-20261106.md).
 *
 * The environment variable LOGD_DBCONNECT, when it is set and not empty, holds the file's absolute path. It wins: the
 * game, scripts/install.php and scripts/upgrade.php use that file and ignore any dbconnect.php in the game's directory.
 * Unset, everything stays as before: dbconnect.php in the game's directory (the working directory of the page or the
 * command). A web server passes it as an environment variable (PHP-FPM's env[LOGD_DBCONNECT], Apache's SetEnv); both
 * reach PHP's getenv() or $_SERVER, and either is read.
 */
final class ConfigLocation
{
    public const string VARIABLE = 'LOGD_DBCONNECT';

    public const string DEFAULT_FILE = 'dbconnect.php';

    /**
     * LOGD_DBCONNECT's value, or null when it is unset or empty.
     *
     * @param array<array-key, mixed>|null $server $_SERVER, read when getenv() has no value
     */
    public static function fromEnvironment(?array $server = null): ?string
    {
        $value = getenv(self::VARIABLE);
        if (!is_string($value) || $value === '') {
            $value = ($server ?? $_SERVER)[self::VARIABLE] ?? null;
        }
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Whether a LOGD_DBCONNECT value can be used: an absolute path with no NUL byte. */
    public static function usable(string $path): bool
    {
        return str_starts_with($path, '/') && !str_contains($path, "\0") && basename($path) !== '';
    }

    /**
     * For a file PHP cannot see: the nearest directory above it that exists but cannot be entered, or null when every
     * directory PHP reaches can be entered (the file is missing). A closed directory hides everything under it, so a
     * file there reads as missing to PHP while it may well exist.
     */
    public static function closedDirectory(string $path): ?string
    {
        for ($directory = dirname($path); ; $directory = $parent) {
            if (is_dir($directory)) return is_executable($directory) ? null : $directory;
            $parent = dirname($directory);
            if ($parent === $directory) return null;
        }
    }
}
