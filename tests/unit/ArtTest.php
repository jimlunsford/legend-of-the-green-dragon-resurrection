<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Ui\Art;

/**
 * src/Ui/Art.php, the art lookups (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md): the scene each page shows,
 * the portrait each race gets, and the `scene` module hook. Every lookup returns a file that exists, with alt text from
 * the same data, or null.
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
        self::assertSame(['src' => 'assets/scenes/fallback.svg', 'alt' => 'Ye Olde Bank', 'width' => 1200, 'height' => 320],
            Art::scene('bank.php', Art::PLAYER, self::NAMES));
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
        self::assertNull(Art::scene('bank.php', Art::PLAYER, ['fallback' => '  ']));
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
        self::assertSame('Degolburg', Art::scene('bank.php', Art::PLAYER, self::NAMES, fn (array $a) => ['scene' => 'village'] + $a)['alt'] ?? null);
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
        foreach (array_unique(array_merge(array_values(Art::PLACES), [Art::FALLBACK])) as $scene) {
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
}
