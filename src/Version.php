<?php
declare(strict_types=1);
namespace Resurrection;

/**
 * Resurrection's version: the one place it is set (docs/VERSIONING.md). project.json and docs/VERSIONING.md must say
 * the same (tests/tooling/test_version.py). It is only shown; whether a database needs an upgrade is decided by its
 * applied upgrade steps (lib/upgrade.php), never by this string.
 */
final class Version
{
    /** MAJOR.MINOR.PATCH, with -dev while developing toward a release and -rc.N for a test build. */
    public const string CURRENT = '1.0.0-dev';

    /** The preserved game this one is built from, exactly as 1.1.2 named itself ($logd_version in common.php). */
    public const string BASE = '1.1.2 Dragonprime Edition';

    /** What the footer and the home page show. */
    public static function display(): string
    {
        return 'Resurrection ' . self::CURRENT . ', based on LoGD ' . self::BASE;
    }
}
