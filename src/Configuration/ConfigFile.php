<?php

declare(strict_types=1);

namespace Resurrection\Configuration;

use Resurrection\Installation\InstallRefused;

require_once __DIR__ . '/LegacyConfig.php';
require_once __DIR__ . '/../Installation/InstallRefused.php';

/**
 * The install command's config step: the database configuration file written from the environment through
 * LegacyConfig, created owner-only (0600), never over an existing file, and not inside the served directory unless the
 * operator asks. No message names a value.
 */
final class ConfigFile
{
    /**
     * DB_HOST, DB_USER, DB_PASS and DB_NAME are required; DB_PREFIX is optional (letters, digits and _). The data cache
     * starts off, as the installation guide asks for a first install.
     * @param array<string, string> $environment
     * @return array{DB_HOST: string, DB_USER: string, DB_PASS: string, DB_NAME: string, DB_PREFIX: string, DB_USEDATACACHE: int, DB_DATACACHEPATH: string}
     */
    public static function fromEnvironment(#[\SensitiveParameter] array $environment): array
    {
        $values = [];
        foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $name) {
            if (!isset($environment[$name])) {
                throw new InstallRefused('Set DB_HOST, DB_USER, DB_PASS and DB_NAME in the environment to write the configuration file.');
            }
            $values[$name] = $environment[$name];
        }
        if ($values['DB_HOST'] === '' || $values['DB_USER'] === '' || $values['DB_NAME'] === '') {
            throw new InstallRefused('DB_HOST, DB_USER and DB_NAME must not be empty.');
        }
        $prefix = $environment['DB_PREFIX'] ?? '';
        if (!preg_match('/\A[A-Za-z0-9_]*\z/', $prefix)) {
            throw new InstallRefused('DB_PREFIX may contain only letters, digits and underscores.');
        }
        return ['DB_HOST' => $values['DB_HOST'], 'DB_USER' => $values['DB_USER'], 'DB_PASS' => $values['DB_PASS'],
            'DB_NAME' => $values['DB_NAME'], 'DB_PREFIX' => $prefix, 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => ''];
    }

    /**
     * Where the file would go: an absolute path whose directory exists, to a name that does not exist yet, outside
     * $servedRoot unless $allowServed. Checked before anything is written or changed.
     */
    public static function target(string $path, string $servedRoot, bool $allowServed): string
    {
        if (!str_starts_with($path, '/')) {
            throw new InstallRefused('Give the configuration file as an absolute path.');
        }
        $name = basename($path);
        $directory = realpath(dirname($path));
        if ($name === '' || $name === '.' || $name === '..' || $directory === false || !is_dir($directory)) {
            throw new InstallRefused('The configuration file\'s directory does not exist.');
        }
        $target = rtrim($directory, '/') . '/' . $name;
        if (file_exists($target) || is_link($target)) {
            throw new InstallRefused('The configuration file already exists. It is never overwritten.');
        }
        if (!$allowServed && self::inside($target, $servedRoot)) {
            throw new InstallRefused('The configuration file would be inside the served directory. Choose a private path, or add --allow-served-path to place it there on purpose.');
        }
        return $target;
    }

    /** Whether $path (already resolved) is inside the resolved $root. */
    public static function inside(string $path, string $root): bool
    {
        $root = realpath($root);
        if ($root === false) {
            throw new \RuntimeException('The served directory cannot be resolved.');
        }
        return str_starts_with($path, rtrim($root, '/') . '/');
    }

    /**
     * Create the file exclusively, owner-only from the start, and write the rendered configuration. A file that
     * appeared meanwhile is refused, not overwritten; a failed write removes what it created.
     * @param array<string, mixed> $values
     */
    public static function write(string $target, #[\SensitiveParameter] array $values): void
    {
        $contents = LegacyConfig::render($values);
        // PHP resolves links before its exclusive open, so a link is refused here, not followed.
        if (is_link($target)) {
            throw new InstallRefused('The configuration file could not be created. It is never overwritten.');
        }
        $mask = umask(0077);
        try {
            // A warning is an exception under the install command's error handler; either way, false.
            $file = @fopen($target, 'x');
        } catch (\ErrorException) {
            $file = false;
        } finally {
            umask($mask);
        }
        if ($file === false) {
            throw new InstallRefused('The configuration file could not be created. It is never overwritten.');
        }
        $written = false;
        try {
            $written = chmod($target, 0600) && fwrite($file, $contents) === strlen($contents) && fflush($file);
        } finally {
            fclose($file);
            if (!$written) {
                unlink($target);
            }
        }
        if (!$written) {
            throw new \RuntimeException('The configuration file could not be written.');
        }
    }
}
