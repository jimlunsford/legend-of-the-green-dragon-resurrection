<?php
declare(strict_types=1);
namespace Resurrection\Ui;

/**
 * The game's art in assets/ (CC BY-SA 4.0, assets/LICENSE): which scene banner a page shows and which portrait a race
 * gets (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md; the location scenes, Phase 5 task 12,
 * docs/PHASE5-TASK12-SCENES-20261105.md). Every lookup returns a file that exists, with its alt text and size, or null:
 * never a broken URL. The URLs are relative to the game's root, as the stylesheet's is.
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
    public const array PLACES = [
        'home.php' => 'village', 'village.php' => 'village', 'forest.php' => 'forest',
        'inn.php' => 'inn', 'bank.php' => 'bank', 'weapons.php' => 'weapons', 'armor.php' => 'armor',
        'healer.php' => 'healer', 'gardens.php' => 'gardens', 'rock.php' => 'rock', 'train.php' => 'train',
        'stables.php' => 'stables', 'mercenarycamp.php' => 'mercenarycamp', 'gypsy.php' => 'gypsy', 'lodge.php' => 'lodge',
        'clan.php' => 'clan', 'graveyard.php' => 'graveyard', 'shades.php' => 'shades', 'dragon.php' => 'dragon',
        'pvp.php' => 'fields',
    ];
    public const string FALLBACK = 'fallback';

    /** The script every module page runs under; its scene comes from MODULES by the module that ran. */
    public const string RUNMODULE = 'runmodule.php';

    /**
     * Bundled module => scene, for the module's own pages (runmodule.php): the modules whose pages happen in a place
     * with a scene. Every other module's page shows the fallback; a third-party module names its own scene through the
     * `scene` hook. The forest specials need no entry: they run inside forest.php.
     */
    public const array MODULES = [
        'cedrikspotions' => 'inn', 'dag' => 'inn', 'drinks' => 'inn', 'lovers' => 'inn', 'sethsong' => 'inn',
        'crazyaudrey' => 'village', 'outhouse' => 'forest',
    ];

    /**
     * Module pages that are editors, with the request value that marks them: no scene. The drink editor calls
     * check_su_access() only for a player without the module's own `canedit` preference, so it is not always a
     * superuser page by that test.
     */
    public const array MODULE_EDITORS = ['drinks' => ['act', 'editor']];

    /**
     * The scenes whose place has an English name, translated (schema "scenes") when shown: the alt text. The village
     * and the inn are named by settings (NAME_SETTINGS); the fallback by the page's own title.
     */
    public const array NAMES = [
        'forest' => 'The Forest', 'bank' => 'Ye Olde Bank', 'weapons' => "MightyE's Weaponry", 'armor' => 'Pegasus Armor',
        'healer' => "Healer's Hut", 'gardens' => 'The Gardens', 'rock' => 'Curious Looking Rock',
        'train' => "Bluspring's Warrior Training", 'stables' => "Merick's Stables", 'mercenarycamp' => 'Mercenary Camp',
        'gypsy' => 'Ze Gypsy Tent', 'lodge' => "JCP's Hunter Lodge", 'clan' => 'Clan Halls', 'graveyard' => 'The Graveyard',
        'shades' => 'Land of the Shades', 'dragon' => "The Green Dragon's Lair", 'fields' => 'The Fields',
    ];

    /** The scenes a game setting names: scene => setting. */
    public const array NAME_SETTINGS = ['village' => 'villagename', 'inn' => 'innname'];

    /** The races with their own portrait: the bundled race modules' races, lowercased. Any other race is unknown. */
    public const array RACES = ['human', 'elf', 'dwarf', 'troll'];
    public const string UNKNOWN = 'unknown';

    /** The intrinsic sizes, from the files' viewBox: the scenes are 1200x320, the portraits 200x240. */
    public const array SCENE_SIZE = [1200, 320];
    public const array PORTRAIT_SIZE = [200, 240];

    /**
     * The scene a page shows, or null for none. $names gives each scene's alt text, the place's name, and the
     * fallback's (the page's own title): an array (scene => text, 'fallback' required), or a function of the scene,
     * as name() is. On runmodule.php the scene is the running module's (MODULES), and none on a module's editor page
     * (MODULE_EDITORS, by $request). The `scene` module hook gets ['script' => ..., 'scene' => ..., 'alt' => ...]
     * and may name another scene in assets/scenes/ (with its own alt text, or the page's name for that scene), or ''
     * for none; a scene that does not exist is ignored. The hook is only asked on player pages.
     *
     * @param array<string, string>|callable(string): string $names
     * @param (callable(array{script: string, scene: string, alt: string}): mixed)|null $hook
     * @param array<array-key, mixed> $request the request's values (the query string), for MODULE_EDITORS
     * @return array{src: string, alt: string, width: int, height: int}|null
     */
    public static function scene(string $script, string $kind, array|callable $names, ?callable $hook = null, string $module = '', array $request = []): ?array
    {
        if ($kind !== self::PLAYER) { return null; }
        $script = basename($script);
        if ($script === self::RUNMODULE && isset(self::MODULE_EDITORS[$module])) {
            [$key, $value] = self::MODULE_EDITORS[$module];
            if (($request[$key] ?? null) === $value) { return null; }
        }
        $scene = self::place($script, $module);
        $name = is_array($names)
            ? static fn (string $scene): string => (string)($names[$scene] ?? $names[self::FALLBACK] ?? '')
            : static fn (string $scene): string => (string)$names($scene);
        $alt = $name($scene);
        if ($hook !== null) {
            $asked = $alt;
            $args = $hook(['script' => $script, 'scene' => $scene, 'alt' => $alt]);
            if (is_array($args) && array_key_exists('scene', $args)) {
                if ($args['scene'] === '' || $args['scene'] === null) { return null; }
                if (is_string($args['scene']) && $args['scene'] !== $scene && self::file('scenes', $args['scene']) !== null) {
                    $scene = $args['scene'];
                    $alt = $name($scene);
                }
                // The module's own alt text, when it gives one; the one it was asked with names the old scene.
                if (isset($args['alt']) && is_string($args['alt']) && trim($args['alt']) !== '' && $args['alt'] !== $asked) { $alt = $args['alt']; }
            }
        }
        $src = self::file('scenes', $scene);
        if ($src === null || trim($alt) === '') { return null; }
        return ['src' => $src, 'alt' => $alt, 'width' => self::SCENE_SIZE[0], 'height' => self::SCENE_SIZE[1]];
    }

    /**
     * The scene a player page's place has before the `scene` hook: the script's (PLACES), on runmodule.php the
     * module's (MODULES), otherwise the fallback.
     */
    public static function place(string $script, string $module = ''): string
    {
        $script = basename($script);
        if ($script === self::RUNMODULE) { return self::MODULES[$module] ?? self::FALLBACK; }
        return self::PLACES[$script] ?? self::FALLBACK;
    }

    /**
     * A scene's alt text, the place's name: the setting that names it (read through $setting, as getsetting()), the
     * English name through $translate (as translate_inline() with the "scenes" schema), or for the fallback and any
     * other scene, the page's own title.
     *
     * @param callable(string): mixed $setting
     * @param callable(string): mixed $translate
     */
    public static function name(string $scene, callable $setting, callable $translate, string $title): string
    {
        if (isset(self::NAME_SETTINGS[$scene])) { return (string)$setting(self::NAME_SETTINGS[$scene]); }
        if (isset(self::NAMES[$scene])) { return (string)$translate(self::NAMES[$scene]); }
        return $title;
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
