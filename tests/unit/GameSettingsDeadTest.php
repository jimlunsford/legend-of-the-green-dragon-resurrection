<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The Game Settings entries that did nothing (Phase 6 task 18, N11; docs/PHASE6-TASK18-20261114.md): task 7's settings
 * sweep found eight. Each one nothing reads is no longer declared in Game Settings (lib/configuration_descriptors.php),
 * and a stored row is left alone (no upgrade step). The two that code still reads are kept, with their readers named
 * here, and a note in Game Settings says they do nothing in this game. PopupAdminRenderTest checks the rendered page.
 */
final class GameSettingsDeadTest extends TestCase
{
    /** Removed: no core file or bundled module reads them. */
    private const array REMOVED = ['newdaycron', 'show_notices', 'notify_on_warn', 'notify_on_error', 'notify_address', 'notify_every'];

    /** Kept: each reader, file => the read it holds. */
    private const array KEPT = [
        'beta' => ['common.php' => 'getsetting("beta", 0)'],
        'betaperplayer' => [
            'common.php' => 'getsetting("betaperplayer", 1)',
            'village.php' => 'getsetting("betaperplayer", 1) == 1 && @file_exists("pavilion.php")',
            'moderate.php' => 'getsetting("betaperplayer", 1) == 1 && @file_exists("pavilion.php")',
            'lib/commentary.php' => 'getsetting("betaperplayer", 1) == 1 && @file_exists("pavilion.php")',
        ],
    ];

    /** Files that only store a default, never read one: the fresh install's defaults and 1.1.2's installer rows. */
    private const array WRITERS = ['lib/installer/installer_default_settings.php', 'lib/installer/installer_sqlstatements.php', 'lib/installer/fresh_install.php'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> every shipped PHP file, core and bundled modules, relative to the root */
    private static function shipped(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = substr((string)$file, strlen(self::root()) + 1);
            if (!str_ends_with($path, '.php') || preg_match('#\A(vendor|tests|\.git|docs|scripts)/#', $path)) continue;
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    /** @return list<string> the setting keys lib/configuration_descriptors.php declares */
    private static function declared(): array
    {
        preg_match_all('/^\s*"([A-Za-z0-9_]+)"=>/m', (string)file_get_contents(self::root() . '/lib/configuration_descriptors.php'), $found);
        return $found[1];
    }

    public function testEachRemovedSettingIsGoneAndReadNowhere(): void
    {
        $declared = self::declared();
        self::assertContains('LOGINTIMEOUT', $declared, 'the scan reads the declarations');
        $readers = [];
        foreach (self::shipped() as $file) {
            if (in_array($file, self::WRITERS, true)) continue;
            $source = (string)file_get_contents(self::root() . '/' . $file);
            foreach (self::REMOVED as $name) {
                if (preg_match('/[\'"]' . $name . '[\'"]/', $source)) $readers[$name][] = $file;
            }
        }
        foreach (self::REMOVED as $name) {
            self::assertNotContains($name, $declared, $name . ' is still in Game Settings');
            self::assertArrayNotHasKey($name, $readers, $name . ' is read in ' . implode(', ', $readers[$name] ?? []));
        }
        $source = (string)file_get_contents(self::root() . '/lib/configuration_descriptors.php');
        self::assertStringNotContainsString('wiki.dragonprime.net', $source, "newdaycron's notes are gone with it");
        self::assertStringNotContainsString('"Error Notification,title"', $source, 'the section is gone with its five settings');
        // The fresh install's defaults are unchanged: a row a database stores is left alone.
        $defaults = (string)file_get_contents(self::root() . '/lib/installer/installer_default_settings.php');
        foreach (self::REMOVED as $name) self::assertMatchesRegularExpression('/^\t"' . $name . '"=>/m', $defaults, $name);
    }

    public function testEachKeptSettingHasItsReaderAndItsNote(): void
    {
        $declared = self::declared();
        foreach (self::KEPT as $name => $readers) {
            self::assertContains($name, $declared, $name);
            foreach ($readers as $file => $read) {
                self::assertStringContainsString($read, (string)file_get_contents(self::root() . '/' . $file), $name . ' in ' . $file);
            }
        }
        // What they gate is not in the game, and each label says so (Game Settings shows no notes).
        self::assertFileDoesNotExist(self::root() . '/pavilion.php');
        $source = (string)file_get_contents(self::root() . '/lib/configuration_descriptors.php');
        self::assertStringContainsString('"beta"=>"Enable beta features for all players? (Does nothing in this game: its beta page pavilion.php is not part of it.),bool",', $source);
        self::assertStringContainsString('"betaperplayer"=>"Enable beta features per player? (Does nothing in this game: its beta page pavilion.php is not part of it.),bool",', $source);
    }
}
