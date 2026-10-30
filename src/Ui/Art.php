<?php
declare(strict_types=1);
namespace Resurrection\Ui;

/**
 * The game's art in assets/ (CC BY-SA 4.0, assets/LICENSE): which scene banner a page shows and which portrait a race
 * gets (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md). Every lookup returns a file that exists, with its alt
 * text and size, or null: never a broken URL. The URLs are relative to the game's root, as the stylesheet's is.
 */
final class Art
{
    public const string ASSETS = __DIR__ . '/../../assets';

    /** A player page: it shows its scene. */
    public const string PLAYER = 'player';
    /** A popup (mail, MoTD, petition, the translator): no scene. */
    public const string POPUP = 'popup';
    /** A superuser or editor page, one that called check_su_access(): no scene. */
    public const string SUPERUSER = 'superuser';
    /** An operator page (not installed, database error, upgrade needed, game unavailable): no scene. */
    public const string OPERATOR = 'operator';

    /** Page script => scene. Every other player page shows the fallback scene. */
    public const array PLACES = ['home.php' => 'village', 'village.php' => 'village', 'forest.php' => 'forest'];
    public const string FALLBACK = 'fallback';

    /** The races with their own portrait: the bundled race modules' races, lowercased. Any other race is unknown. */
    public const array RACES = ['human', 'elf', 'dwarf', 'troll'];
    public const string UNKNOWN = 'unknown';

    /** The intrinsic sizes, from the files' viewBox: the scenes are 1200x320, the portraits 200x240. */
    public const array SCENE_SIZE = [1200, 320];
    public const array PORTRAIT_SIZE = [200, 240];

    /**
     * The scene a page shows, or null for none. $names gives each scene's alt text, the place's name, and the
     * fallback's (the page's own title). The `scene` module hook gets ['script' => ..., 'scene' => ..., 'alt' => ...]
     * and may name another scene in assets/scenes/ (with its own alt text, or the page's name for that scene), or ''
     * for none; a scene that does not exist is ignored. The hook is only asked on player pages.
     *
     * @param array<string, string> $names scene => alt text; 'fallback' is required
     * @param (callable(array{script: string, scene: string, alt: string}): mixed)|null $hook
     * @return array{src: string, alt: string, width: int, height: int}|null
     */
    public static function scene(string $script, string $kind, array $names, ?callable $hook = null): ?array
    {
        if ($kind !== self::PLAYER) { return null; }
        $script = basename($script);
        $scene = self::PLACES[$script] ?? self::FALLBACK;
        $alt = $names[$scene] ?? $names[self::FALLBACK] ?? '';
        if ($hook !== null) {
            $asked = $alt;
            $args = $hook(['script' => $script, 'scene' => $scene, 'alt' => $alt]);
            if (is_array($args) && array_key_exists('scene', $args)) {
                if ($args['scene'] === '' || $args['scene'] === null) { return null; }
                if (is_string($args['scene']) && $args['scene'] !== $scene && self::file('scenes', $args['scene']) !== null) {
                    $scene = $args['scene'];
                    $alt = $names[$scene] ?? $names[self::FALLBACK] ?? '';
                }
                // The module's own alt text, when it gives one; the one it was asked with names the old scene.
                if (isset($args['alt']) && is_string($args['alt']) && trim($args['alt']) !== '' && $args['alt'] !== $asked) { $alt = $args['alt']; }
            }
        }
        $src = self::file('scenes', $scene);
        if ($src === null || trim($alt) === '') { return null; }
        return ['src' => $src, 'alt' => $alt, 'width' => self::SCENE_SIZE[0], 'height' => self::SCENE_SIZE[1]];
    }

    /** The portrait's name for a race: the race lowercased when it has its own portrait, otherwise unknown. */
    public static function portraitName(string $race): string
    {
        $name = strtolower(trim($race));
        return in_array($name, self::RACES, true) ? $name : self::UNKNOWN;
    }

    /**
     * A race's portrait, its alt text the race as the character panel shows it, or null when the file is missing.
     * @return array{src: string, alt: string, width: int, height: int}|null
     */
    public static function portrait(string $race, string $alt): ?array
    {
        $src = self::file('portraits/race', self::portraitName($race));
        if ($src === null || trim($alt) === '') { return null; }
        return ['src' => $src, 'alt' => $alt, 'width' => self::PORTRAIT_SIZE[0], 'height' => self::PORTRAIT_SIZE[1]];
    }

    /** assets/<folder>/<name>.svg as a URL, or null when the name is not a plain file name or the file is missing. */
    private static function file(string $folder, string $name): ?string
    {
        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $name) !== 1) { return null; }
        $path = $folder . '/' . $name . '.svg';
        return is_file(self::ASSETS . '/' . $path) ? 'assets/' . $path : null;
    }
}
