<?php
declare(strict_types=1);

namespace Resurrection\Ui;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * Versioned asset URLs (Phase 6 task 18, N1; docs/PHASE6-TASK18-20261114.md). Every stylesheet and script the game
 * prints carries ?v= and the first 12 hex characters of the SHA-256 of the file's bytes, computed once per request and
 * kept in memory, so that after an upgrade a browser fetches each changed file instead of a cached copy. Until then the
 * URLs carried no version, and a far-future cache would have served the old file.
 *
 * The stylesheet's own @import lines and the fonts' URLs inside assets/ui/fonts.css carry the same query, written into
 * the files by scripts/asset-versions.py (tests/tooling/test_asset_versions.py fails when one is stale), so a changed
 * font changes fonts.css, which changes resurrection.css, whose new version every page then links. Images keep plain
 * URLs: the art is fixed at 1.0.0 (no new art after it, Jim's decision of 2026-11-13), a stale image breaks nothing, and
 * the host guide says to revalidate them.
 *
 * The URLs stay relative and on the game's own origin, so the Content Security Policy ('self') is unchanged.
 */
final class AssetUrl
{
    /** @var array<string, string> path => versioned URL, for this request */
    private static array $cache = [];

    /** The asset URLs a template or a page may print: a stylesheet or script under assets/ui/. */
    public const string PATTERN = '#\b(href|src)="(assets/ui/[A-Za-z0-9_-]+\.(?:css|js))"#';

    /**
     * The URL for a file under the game's folder, with ?v= and its version; the path alone when the file cannot be read
     * (the page still works, as before this task).
     */
    public static function versioned(string $path, ?string $root = null): string
    {
        $root ??= \dirname(__DIR__, 2);
        $key = $root . "\0" . $path;
        if (isset(self::$cache[$key])) { return self::$cache[$key]; }
        $file = $root . '/' . $path;
        $hash = \is_file($file) && \is_readable($file) ? \hash_file('sha256', $file) : false;
        return self::$cache[$key] = $hash === false ? $path : $path . '?v=' . \substr($hash, 0, 12);
    }

    /** Every stylesheet and script URL in this markup (PATTERN), versioned. */
    public static function rewrite(string $html, ?string $root = null): string
    {
        return (string)\preg_replace_callback(self::PATTERN,
            static fn (array $m): string => $m[1] . '="' . \htmlspecialchars(self::versioned($m[2], $root), ENT_QUOTES, 'UTF-8') . '"', $html);
    }

    /** Forget the versions computed so far (a new request; the tests). */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
