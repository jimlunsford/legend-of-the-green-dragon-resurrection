<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Ui\Art;

/**
 * src/Ui/Art.php, the art lookups (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md): the scene each page shows,
 * the portrait each race gets, and the `scene` module hook. Every lookup returns a file that exists, with alt text from
 * the same data, or null.
 *
 * Phase 5 task 12 (docs/PHASE5-TASK12-SCENES-20261105.md) adds the location scenes: each place's page and each bundled
 * module whose pages happen in a place show its scene, the alt text the place's name (the village's and the inn's from
 * their settings, the others translated); unmapped pages and modules the fallback; popups, superuser pages and the
 * drink editor none.
 *
 * Phase 5 task 14 (docs/PHASE5-TASK14-PORTRAITS-20261105.md) adds the foe portraits: a seeded creature's by creatureid,
 * a seeded master's by masterid, the Green Dragon's, a PvP victim's race portrait, and for any other creature the
 * fallback of the type a module names through the `creatureportrait` hook, or the unknown one.
 */
final class ArtTest extends TestCase
{
    private const array NAMES = ['village' => 'Degolburg', 'forest' => 'The Forest', 'fallback' => 'Ye Olde Bank'];

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../src/Ui/Art.php';
    }

    public function testEachMappedPageShowsItsScene(): void
    {
        self::assertSame(['src' => 'assets/scenes/village.svg', 'alt' => 'Degolburg', 'width' => 1200, 'height' => 320],
            Art::scene('home.php', Art::PLAYER, self::NAMES));
        self::assertSame(['src' => 'assets/scenes/village.svg', 'alt' => 'Degolburg', 'width' => 1200, 'height' => 320],
            Art::scene('village.php', Art::PLAYER, self::NAMES));
        // The forest's fights are forest.php too; the script is the key, with or without a path.
        self::assertSame(['src' => 'assets/scenes/forest.svg', 'alt' => 'The Forest', 'width' => 1200, 'height' => 320],
            Art::scene('/game/forest.php', Art::PLAYER, self::NAMES));
    }

    public function testAnUnmappedPlayerPageShowsTheFallback(): void
    {
        // bank.php was the example until Phase 5 task 12 gave the bank its scene; news.php has none.
        self::assertSame(['src' => 'assets/scenes/fallback.svg', 'alt' => 'Ye Olde Bank', 'width' => 1200, 'height' => 320],
            Art::scene('news.php', Art::PLAYER, self::NAMES));
        self::assertSame('assets/scenes/fallback.svg', Art::scene('runmodule.php', Art::PLAYER, self::NAMES)['src'] ?? null);
    }

    public function testPopupsSuperuserAndOperatorPagesShowNoScene(): void
    {
        foreach ([Art::POPUP, Art::SUPERUSER, Art::OPERATOR] as $kind) {
            self::assertNull(Art::scene('mail.php', $kind, self::NAMES), $kind);
            self::assertNull(Art::scene('village.php', $kind, self::NAMES), $kind);
        }
        // The hook is not even asked there.
        $asked = false;
        self::assertNull(Art::scene('user.php', Art::SUPERUSER, self::NAMES, function (array $args) use (&$asked) {
            $asked = true;
            return ['scene' => 'forest'] + $args;
        }));
        self::assertFalse($asked);
    }

    public function testNoAltTextNoImage(): void
    {
        self::assertNull(Art::scene('news.php', Art::PLAYER, ['fallback' => '  ']));
        self::assertNull(Art::portrait('Elf', ''));
    }

    public function testAModuleNamesItsOwnScene(): void
    {
        $seen = null;
        $hook = function (array $args) use (&$seen) {
            $seen = $args;
            return ['scene' => 'forest', 'alt' => 'The Haunted Glade'] + $args;
        };
        self::assertSame(['src' => 'assets/scenes/forest.svg', 'alt' => 'The Haunted Glade', 'width' => 1200, 'height' => 320],
            Art::scene('runmodule.php', Art::PLAYER, self::NAMES, $hook));
        self::assertSame(['script' => 'runmodule.php', 'scene' => 'fallback', 'alt' => 'Ye Olde Bank'], $seen);
        // Without its own alt text the scene's name from the page's data is used.
        self::assertSame('Degolburg', Art::scene('news.php', Art::PLAYER, self::NAMES, fn (array $a) => ['scene' => 'village'] + $a)['alt'] ?? null);
        // '' means no scene for the page.
        self::assertNull(Art::scene('village.php', Art::PLAYER, self::NAMES, fn (array $a) => ['scene' => ''] + $a));
        // A scene with no file, or a name that is not a plain file name, is ignored: never a broken URL.
        foreach (['castle', '../ui/tokens', 'Forest', 'forest.svg', 'scenes/forest', ' forest'] as $name) {
            self::assertSame('assets/scenes/village.svg',
                Art::scene('village.php', Art::PLAYER, self::NAMES, fn (array $a) => ['scene' => $name] + $a)['src'] ?? null, $name);
        }
        // A hook that returns nothing useful changes nothing.
        self::assertSame('assets/scenes/forest.svg', Art::scene('forest.php', Art::PLAYER, self::NAMES, fn (array $a) => null)['src'] ?? null);
        self::assertSame('assets/scenes/forest.svg', Art::scene('forest.php', Art::PLAYER, self::NAMES, fn (array $a) => [])['src'] ?? null);
    }

    public function testEachRaceGetsItsPortrait(): void
    {
        foreach (['Human' => 'human', 'Elf' => 'elf', 'Dwarf' => 'dwarf', 'Troll' => 'troll'] as $race => $file) {
            self::assertSame($file, Art::portraitName($race));
            self::assertSame(['src' => "assets/portraits/race/$file.svg", 'alt' => $race, 'width' => 200, 'height' => 240],
                Art::portrait($race, $race));
        }
        self::assertSame('elf', Art::portraitName(' ELF '));
    }

    public function testAnyOtherRaceIsUnknown(): void
    {
        foreach (['', 'Horrible Gelatinous Blob', 'Gnome', '../human', 'human.svg'] as $race) {
            self::assertSame('unknown', Art::portraitName($race), $race);
        }
        self::assertSame(['src' => 'assets/portraits/race/unknown.svg', 'alt' => 'Gnome', 'width' => 200, 'height' => 240],
            Art::portrait('Gnome', 'Gnome'));
    }

    public function testEveryFileALookupCanReturnExists(): void
    {
        foreach (array_unique(array_merge(array_values(Art::PLACES), array_values(Art::MODULES), [Art::FALLBACK])) as $scene) {
            self::assertFileExists(Art::ASSETS . "/scenes/$scene.svg");
        }
        foreach (array_merge(Art::RACES, [Art::UNKNOWN]) as $race) {
            self::assertFileExists(Art::ASSETS . "/portraits/race/$race.svg");
            $svg = (string)file_get_contents(Art::ASSETS . "/portraits/race/$race.svg");
            self::assertStringContainsString('viewBox="0 0 ' . Art::PORTRAIT_SIZE[0] . ' ' . Art::PORTRAIT_SIZE[1] . '"', $svg, $race);
        }
        foreach (glob(Art::ASSETS . '/scenes/*.svg') ?: [] as $file) {
            self::assertStringContainsString('viewBox="0 0 ' . Art::SCENE_SIZE[0] . ' ' . Art::SCENE_SIZE[1] . '"', (string)file_get_contents($file), $file);
        }
    }

    /** Phase 5 task 12: each place's page, its scene and the place's name as alt text. */
    private const array PAGES = [
        'inn.php' => ['inn', "The Boar's Head Inn"], 'bank.php' => ['bank', 'Ye Olde Bank'],
        'weapons.php' => ['weapons', "MightyE's Weaponry"], 'armor.php' => ['armor', 'Pegasus Armor'],
        'healer.php' => ['healer', "Healer's Hut"], 'gardens.php' => ['gardens', 'The Gardens'],
        'rock.php' => ['rock', 'Curious Looking Rock'], 'train.php' => ['train', "Bluspring's Warrior Training"],
        'stables.php' => ['stables', "Merick's Stables"], 'mercenarycamp.php' => ['mercenarycamp', 'Mercenary Camp'],
        'gypsy.php' => ['gypsy', 'Ze Gypsy Tent'], 'lodge.php' => ['lodge', "JCP's Hunter Lodge"],
        'clan.php' => ['clan', 'Clan Halls'], 'graveyard.php' => ['graveyard', 'The Graveyard'],
        'shades.php' => ['shades', 'Land of the Shades'], 'dragon.php' => ['dragon', "The Green Dragon's Lair"],
        'pvp.php' => ['fields', 'The Fields'],
        // Task 5's three, unchanged.
        'home.php' => ['village', 'Degolburg'], 'village.php' => ['village', 'Degolburg'], 'forest.php' => ['forest', 'The Forest'],
    ];

    /** Phase 5 task 12: the bundled modules whose pages happen in a place, and that place's scene. */
    private const array MODULE_SCENES = [
        'cedrikspotions' => 'inn', 'dag' => 'inn', 'drinks' => 'inn', 'lovers' => 'inn', 'sethsong' => 'inn',
        'crazyaudrey' => 'village', 'outhouse' => 'forest',
    ];

    /** The other bundled modules: their pages, where they have any, show the fallback. */
    private const array FALLBACK_MODULES = [
        'darkhorse', 'game_dice', 'game_fivesix', 'game_stones', 'fairy', 'findgem', 'findgold', 'foilwench', 'glowingstream',
        'goldmine', 'racedwarf', 'raceelf', 'racehuman', 'racetroll', 'specialtydarkarts', 'specialtymysticpower',
        'specialtythiefskills',
    ];

    /** The game's own settings, as a fresh install stores them, or as $changed overrides them. */
    private static function names(array $changed = [], string $title = 'A Page'): \Closure
    {
        $settings = $changed + ['villagename' => 'Degolburg', 'innname' => "The Boar's Head Inn"];
        return static fn (string $scene): string => Art::name($scene,
            static fn (string $setting): string => $settings[$setting] ?? throw new \LogicException("No setting $setting"),
            static fn (string $text): string => $text, $title);
    }

    public function testEveryPlacesPageShowsItsSceneNamedForThePlace(): void
    {
        foreach (self::PAGES as $script => [$scene, $alt]) {
            self::assertSame(['src' => "assets/scenes/$scene.svg", 'alt' => $alt, 'width' => 1200, 'height' => 320],
                Art::scene($script, Art::PLAYER, self::names()), $script);
            self::assertFileExists(Art::ASSETS . "/scenes/$scene.svg");
        }
        // The names other than the settings' are translated, with the "scenes" schema the caller passes.
        $translated = Art::scene('bank.php', Art::PLAYER, static fn (string $scene): string
            => Art::name($scene, static fn (string $s): string => 'unused', static fn (string $text): string => "[$text]", 'Title'));
        self::assertSame('[Ye Olde Bank]', $translated['alt'] ?? null);
        self::assertSame('[The Forest]', Art::scene('forest.php', Art::PLAYER, static fn (string $scene): string
            => Art::name($scene, static fn (string $s): string => 'unused', static fn (string $text): string => "[$text]", 'Title'))['alt'] ?? null);
    }

    public function testARenamedInnOrVillageGivesTheNewName(): void
    {
        $renamed = self::names(['innname' => 'The Prancing Pony', 'villagename' => 'Bree']);
        self::assertSame('The Prancing Pony', Art::scene('inn.php', Art::PLAYER, $renamed)['alt'] ?? null);
        self::assertSame('Bree', Art::scene('village.php', Art::PLAYER, $renamed)['alt'] ?? null);
        self::assertSame('Bree', Art::scene('home.php', Art::PLAYER, $renamed)['alt'] ?? null);
        // A module page at the inn takes the inn's name too.
        self::assertSame('The Prancing Pony', Art::scene('runmodule.php', Art::PLAYER, $renamed, null, 'lovers')['alt'] ?? null);
        self::assertSame('Bree', Art::scene('runmodule.php', Art::PLAYER, $renamed, null, 'crazyaudrey')['alt'] ?? null);
    }

    public function testEachMappedModuleShowsItsPlacesScene(): void
    {
        $alts = ['inn' => "The Boar's Head Inn", 'village' => 'Degolburg', 'forest' => 'The Forest'];
        foreach (self::MODULE_SCENES as $module => $scene) {
            self::assertSame($scene, Art::place('runmodule.php', $module), $module);
            self::assertSame(['src' => "assets/scenes/$scene.svg", 'alt' => $alts[$scene], 'width' => 1200, 'height' => 320],
                Art::scene('runmodule.php', Art::PLAYER, self::names(), null, $module), $module);
        }
        // The drinks bought at the inn show it; the drink editor, an editor page, shows none.
        self::assertSame('assets/scenes/inn.svg', Art::scene('runmodule.php', Art::PLAYER, self::names(), null, 'drinks', ['act' => 'buy', 'id' => '1'])['src'] ?? null);
        self::assertNull(Art::scene('runmodule.php', Art::PLAYER, self::names(), null, 'drinks', ['act' => 'editor', 'admin' => 'true']));
        // The module only counts on runmodule.php.
        self::assertSame('assets/scenes/bank.svg', Art::scene('bank.php', Art::PLAYER, self::names(), null, 'lovers')['src'] ?? null);
        // The `scene` hook is still asked, with the module's scene, and can change it.
        $seen = null;
        self::assertSame('assets/scenes/gardens.svg', Art::scene('runmodule.php', Art::PLAYER, self::names(), function (array $args) use (&$seen) {
            $seen = $args;
            return ['scene' => 'gardens'] + $args;
        }, 'lovers')['src'] ?? null);
        self::assertSame(['script' => 'runmodule.php', 'scene' => 'inn', 'alt' => "The Boar's Head Inn"], $seen);
    }

    public function testAnUnmappedPageOrModuleShowsTheFallbackNamedForThePage(): void
    {
        $fallback = ['src' => 'assets/scenes/fallback.svg', 'alt' => 'Daily News', 'width' => 1200, 'height' => 320];
        foreach (['news.php', 'list.php', 'hof.php', 'prefs.php', 'bio.php', 'newday.php'] as $script) {
            self::assertSame($fallback, Art::scene($script, Art::PLAYER, self::names([], 'Daily News')), $script);
        }
        foreach (array_merge(self::FALLBACK_MODULES, ['', 'somethirdpartymodule', 'Lovers', 'cities']) as $module) {
            self::assertSame(Art::FALLBACK, Art::place('runmodule.php', $module), $module);
            self::assertSame($fallback, Art::scene('runmodule.php', Art::PLAYER, self::names([], 'Daily News'), null, $module), $module);
        }
        // Every bundled module is listed once here or in the mapped list: 24.
        self::assertCount(24, array_unique(array_merge(array_keys(self::MODULE_SCENES), self::FALLBACK_MODULES)));
        $bundled = array_map(static fn (string $f): string => basename($f, '.php'), glob(Art::ASSETS . '/../modules/*.php') ?: []);
        sort($bundled);
        $listed = array_merge(array_keys(self::MODULE_SCENES), self::FALLBACK_MODULES);
        sort($listed);
        self::assertSame($bundled, $listed);
    }

    public function testPopupsAndSuperuserPagesShowNoSceneAnywhere(): void
    {
        foreach ([Art::POPUP, Art::SUPERUSER, Art::OPERATOR] as $kind) {
            foreach (array_keys(self::PAGES) as $script) {
                self::assertNull(Art::scene($script, $kind, self::names()), "$kind $script");
            }
            foreach (array_keys(self::MODULE_SCENES) as $module) {
                self::assertNull(Art::scene('runmodule.php', $kind, self::names(), null, $module), "$kind $module");
            }
        }
    }

    /** The character panel's icons (Phase 5 task 13): one file per name, the English label it stands beside, lowercased. */
    private const array ICONS = [
        'turns', 'torments', 'spirits', 'attack', 'defense', 'psyche', 'spirit', 'gold', 'favor', 'gems',
        'hitpoints', 'soulpoints', 'experience', 'weapon', 'armor', 'mount', 'buffs', 'companions',
    ];

    public function testEachIconNameGivesItsFileAt16Pixels(): void
    {
        self::assertSame(self::ICONS, Art::ICONS);
        foreach (self::ICONS as $name) {
            self::assertSame(['src' => "assets/icons/$name.svg", 'width' => 16, 'height' => 16], Art::icon($name), $name);
            self::assertFileExists(Art::ASSETS . "/icons/$name.svg");
        }
        // Every file in assets/icons/ is one of them.
        $files = array_map(static fn (string $f): string => basename($f, '.svg'), glob(Art::ASSETS . '/icons/*') ?: []);
        sort($files);
        $names = self::ICONS;
        sort($names);
        self::assertSame($names, $files);
    }

    public function testAnUnknownIconNameGivesNothing(): void
    {
        foreach (['', 'charm', 'Gold', ' gold', 'gold.svg', '../scenes/forest', 'scenes/forest', 'forest', 'elf', 'hp',
            'ICONS', 'gold/', 'unknown', 'fallback'] as $name) {
            self::assertNull(Art::icon($name), $name);
        }
    }

    public function testALabelGivesItsStandardStatsIconName(): void
    {
        foreach (self::ICONS as $name) {
            self::assertSame($name, Art::iconName(ucfirst($name)), $name);
            self::assertSame($name, Art::iconName(" $name "), $name);
            self::assertSame($name, Art::iconName(strtoupper($name)), $name);
        }
        // A module's own label, or one only like a stat's, gives none.
        foreach (['Charm', 'Clan', 'Clan Info', 'Vital Info', 'Ring', 'Hit points', 'Gold pieces', 'HP', 'XP', '', 'Gems!'] as $label) {
            self::assertNull(Art::iconName($label), $label);
        }
    }

    /** The foe portraits (Phase 5 task 14). */
    private const array PORTRAIT = ['width' => 200, 'height' => 240];

    public function testASeededCreatureGetsItsOwnPortrait(): void
    {
        self::assertSame(['src' => 'assets/portraits/creature/1.svg', 'alt' => 'Thieving Kender'] + self::PORTRAIT, Art::creature(1, 'Thieving Kender'));
        // By id, as the creatures table gives it (a string) or as an int; the alt text is the name the fight shows.
        self::assertSame(['src' => 'assets/portraits/creature/320.svg', 'alt' => 'Elite Gypsy Bandit'] + self::PORTRAIT, Art::creature('320', 'Elite Gypsy Bandit'));
        // A renamed seeded creature keeps its id's portrait; the type a module names is not even asked.
        $asked = false;
        self::assertSame('assets/portraits/creature/3.svg', Art::creature(3, 'Renamed Unicorn', function () use (&$asked) { $asked = true; return 'demon'; })['src'] ?? null);
        self::assertFalse($asked);
        foreach ([1, 135, 219, 320] as $id) { self::assertFileExists(Art::ASSETS . "/portraits/creature/$id.svg"); }
        // No alt text, no image.
        self::assertNull(Art::creature(1, ' '));
    }

    public function testAnUnknownCreatureGetsTheUnknownFallback(): void
    {
        $unknown = ['src' => 'assets/portraits/creature/fallback/unknown.svg', 'alt' => 'Mystery'] + self::PORTRAIT;
        // An editor's creature (an id past the seeds, or one of the seeds' gaps), a module's (no id), the doppelganger.
        foreach ([321, 132, '133', 0, -1, null, '', '01', '1.0', '1 ', 'green-dragon', '../master/1', 1.0, [1], true, '99999999999'] as $id) {
            self::assertSame($unknown, Art::creature($id, 'Mystery'), var_export($id, true));
        }
        self::assertSame($unknown, Art::master(15, 'Mystery'));
        self::assertSame($unknown, Art::master(null, 'Mystery'));
    }

    public function testAModuleNamedTypeGetsItsFallback(): void
    {
        self::assertSame(10, count(Art::CREATURE_TYPES));
        foreach (Art::CREATURE_TYPES as $type) {
            self::assertSame("assets/portraits/creature/fallback/$type.svg", Art::creature(999, 'A thing', fn () => $type)['src'] ?? null, $type);
            self::assertFileExists(Art::ASSETS . "/portraits/creature/fallback/$type.svg");
            self::assertSame($type, Art::creatureType($type));
        }
        // Anything else is unknown: a type that does not exist, another case, a path, not a string.
        foreach (['', 'Beast', 'dog', 'race', 'human', '../race/elf', 'fallback/beast', 'green-dragon', null, 3, ['beast'], false] as $type) {
            self::assertSame('assets/portraits/creature/fallback/unknown.svg', Art::creature(999, 'A thing', fn () => $type)['src'] ?? null, var_export($type, true));
            self::assertSame('unknown', Art::creatureType($type));
        }
        // Through the hook, as the battle screen asks it: the foe, the fight's type, and unknown to change.
        $seen = null;
        $hook = function (array $args) use (&$seen) { $seen = $args; return ['type' => 'undead'] + $args; };
        $foe = ['creaturename' => 'Bone Collector', 'creaturelevel' => 4];
        self::assertSame(['src' => 'assets/portraits/creature/fallback/undead.svg', 'alt' => 'Bone Collector'] + self::PORTRAIT,
            Art::foe($foe, 'forest', 'Bone Collector', $hook));
        self::assertSame(['creature' => $foe, 'fight' => 'forest', 'type' => 'unknown'], $seen);
        // A hook that gives nothing back, or no type, leaves unknown; it is not asked for a seeded creature.
        self::assertSame('assets/portraits/creature/fallback/unknown.svg', Art::foe($foe, 'forest', 'x', fn (array $a) => null)['src'] ?? null);
        self::assertSame('assets/portraits/creature/fallback/unknown.svg', Art::foe($foe, 'forest', 'x', fn (array $a) => $a)['src'] ?? null);
        $seen = null;
        self::assertSame('assets/portraits/creature/9.svg', Art::foe(['creatureid' => '9'] + $foe, 'graveyard', 'x', $hook)['src'] ?? null);
        self::assertNull($seen);
    }

    public function testAMasterGetsItsOwnPortrait(): void
    {
        for ($id = 1; $id <= 14; $id++) {
            self::assertSame(['src' => "assets/portraits/master/$id.svg", 'alt' => 'Master'] + self::PORTRAIT, Art::master($id, 'Master'), (string)$id);
        }
        // A training fight's foe is the master with that id, not the creature: the two tables share their ids.
        self::assertSame('assets/portraits/master/1.svg', Art::foe(['creatureid' => '1', 'creaturename' => 'Mireraband'], 'train', 'Mireraband')['src'] ?? null);
        self::assertSame('assets/portraits/creature/1.svg', Art::foe(['creatureid' => '1', 'creaturename' => 'Thieving Kender'], 'forest', 'Thieving Kender')['src'] ?? null);
    }

    public function testTheGreenDragonGetsItsPortrait(): void
    {
        $dragon = ['src' => 'assets/portraits/creature/green-dragon.svg', 'alt' => 'The Green Dragon'] + self::PORTRAIT;
        self::assertSame($dragon, Art::dragon('The Green Dragon'));
        self::assertSame($dragon, Art::foe(['creaturename' => '`@The Green Dragon`0', 'type' => 'dragon'], 'dragon', 'The Green Dragon'));
        self::assertNull(Art::dragon(''));
    }

    public function testAPvpVictimGetsTheirRacePortrait(): void
    {
        self::assertSame(['src' => 'assets/portraits/race/elf.svg', 'alt' => 'Violet'] + self::PORTRAIT,
            Art::foe(['creatureid' => 1, 'race' => 'Elf', 'creaturename' => 'Violet'], 'pvp', 'Violet'));
        self::assertSame('assets/portraits/race/unknown.svg', Art::foe(['race' => 'Gnome'], 'pvp', 'Gnome Player')['src'] ?? null);
        self::assertSame('assets/portraits/race/unknown.svg', Art::foe([], 'pvp', 'No Race')['src'] ?? null);
    }
}
