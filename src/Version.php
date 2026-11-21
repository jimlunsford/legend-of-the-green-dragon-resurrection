<?php
declare(strict_types=1);
namespace Resurrection;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * Resurrection's version: the one place it is set (docs/VERSIONING.md). project.json and docs/VERSIONING.md must say
 * the same (tests/tooling/test_version.py). It is only shown, and it names the Git ref the pages link the documents at
 * (docRef()); whether a database needs an upgrade is decided by its applied upgrade steps (lib/upgrade.php), never by
 * this string.
 */
final class Version
{
    /** MAJOR.MINOR.PATCH, with -dev while developing toward a release and -rc.N for a test build. */
    public const string CURRENT = '1.0.1-dev';

    /** The preserved game this one is built from, exactly as 1.1.2 named itself ($logd_version in common.php). */
    public const string BASE = '1.1.2 Dragonprime Edition';

    /**
     * The Git ref a version's documents are linked at (lib/source_link.php): its tag, `v` and the version, for a
     * release or a test build, and `main` for a -dev version, which is never tagged (Phase 6 task 34).
     */
    public static function docRef(string $version = self::CURRENT): string
    {
        return str_ends_with($version, '-dev') ? 'main' : 'v' . $version;
    }

    /** What the footer and the home page show. */
    public static function display(): string
    {
        return 'Resurrection ' . self::CURRENT . ', based on LoGD ' . self::BASE;
    }
}
