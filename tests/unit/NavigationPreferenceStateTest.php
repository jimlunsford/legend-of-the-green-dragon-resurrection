<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\NavigationState;
use Resurrection\Security\PreferenceState;

final class NavigationPreferenceStateTest extends TestCase
{
    /** Stored `prefs` as 1.1.2's prefs.php, commentary.php and holiday_texts.php wrote them. */
    private const LEGACY_PREFS = 'a:13:{s:8:"language";s:2:"en";s:9:"tabconfig";s:1:"1";s:11:"emailonmail";s:1:"1";' .
        's:10:"systemmail";s:1:"0";s:10:"dirtyemail";s:1:"0";s:9:"timestamp";s:1:"2";s:10:"timeformat";s:10:"[m/d h:ia]";' .
        's:10:"timeoffset";d:-5.5;s:12:"ihavenocheer";i:0;s:6:"nojump";s:1:"0";s:16:"showFormTabIndex";s:1:"1";' .
        's:7:"oldform";s:8:"O\'Reilly";s:5:"fancy";s:3:"雪";}';

    public function testLegacyPreferencesPass(): void
    {
        $prefs = PreferenceState::read(self::LEGACY_PREFS);
        self::assertSame(['language' => 'en', 'tabconfig' => '1'], array_slice($prefs, 0, 2));
        self::assertSame(-5.5, $prefs['timeoffset']);
        self::assertSame(0, $prefs['ihavenocheer']);
        self::assertSame(self::LEGACY_PREFS, PreferenceState::encode($prefs));
        // An account that never saved preferences, and the shapes the game's own writers produce.
        self::assertSame([], PreferenceState::read(''));
        self::assertSame([], PreferenceState::read(null));
        self::assertSame([], PreferenceState::read('a:0:{}'));
        $written = ['timestamp' => 0, 'timeoffset' => 0.0, 'timeformat' => '', 'language' => '', 'tabconfig' => true,
            'nojump' => false, 'emailonmail' => 1, 'timeoffset2' => 'x'];
        self::assertSame($written, PreferenceState::map($written));
        foreach (['0', '-12', '5.5', ' 3', 14, -3.75, '1e2'] as $offset) {
            self::assertSame($offset, PreferenceState::map(['timeoffset' => $offset])['timeoffset']);
        }
    }

    public function testBlankTimeOffsetReadsAsZero(): void
    {
        // 1.1.2 stored a cleared int field as ""; PHP 8 throws a TypeError on "" * 3600.
        self::assertSame(0, PreferenceState::read(serialize(['timeoffset' => '']))['timeoffset']);
        self::assertSame('a:1:{s:10:"timeoffset";i:0;}', PreferenceState::encode(['timeoffset' => '']));
    }

    public function testMalformedPreferencesFail(): void
    {
        $bad = ['garbage', 'O:8:"stdClass":0:{}', 's:3:"abc";', 'i:5;', 'b:0;', 'N;', 'a:1:{s:1:"a";O:8:"stdClass":0:{}}',
            'a:1:{s:9:"tabconfig";d:INF;}'];
        foreach ([[5 => 'x'], ['' => 'x'], ['a b' => 'x'], ["a\nb" => 'x'], [str_repeat('k', 256) => 'x'], ['a' => null],
            ['a' => []], ['a' => ['b' => 1]], ['tabconfig' => 2], ['tabconfig' => 'yes'], ['nojump' => ''], ['dirtyemail' => 1.0],
            ['timestamp' => 3], ['timestamp' => '1 '], ['timestamp' => true], ['timeoffset' => 'abc'], ['timeoffset' => '5abc'],
            ['timeoffset' => 1000001], ['timeoffset' => '-1e9'], ['timeoffset' => true], ['timeformat' => 5], ['timeformat' => false],
            ['language' => "en' OR 1=1 -- "], ['language' => 5], ['language' => str_repeat('a', 33)], ['language' => 'pt br']] as $prefs) {
            $bad[] = serialize($prefs);
        }
        foreach ($bad as $stored) {
            try { PreferenceState::read($stored); self::fail('Accepted ' . $stored); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([false, 'a:0:{}', 5, null] as $value) {
            try { PreferenceState::map($value); self::fail('Accepted a non-map.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        $this->expectException(\DomainException::class);
        PreferenceState::encode(['bio' => str_repeat('x', 65536)]);
    }

    public function testLegacyNavigationPasses(): void
    {
        $navs = 'a:4:{s:11:"village.php";b:1;s:20:"forest.php?op=search";b:1;s:23:"bio.php?char=1&ret=%2Fx";b:1;s:0:"";b:1;}';
        self::assertSame(['village.php' => true, 'forest.php?op=search' => true, 'bio.php?char=1&ret=%2Fx' => true, '' => true],
            NavigationState::read($navs));
        // The user editor's Fix Navs, as in 1.1.2, and 1.1.2's wrapped non-array value saved back from an override page.
        self::assertSame([], NavigationState::read(''));
        self::assertSame([], NavigationState::read(null));
        self::assertSame([], NavigationState::read('a:0:{}'));
        self::assertSame(['mail.php' => true], NavigationState::read('a:2:{i:0;s:0:"";s:8:"mail.php";b:1;}'));
        self::assertSame(['a.php' => false], NavigationState::map(['a.php' => false]));
        foreach (['village.php', 'forest.php?op=search', 'inn.php?op=strolldown', 'village.php?c=1',
            'runmodule.php?module=x&op=travel&city=Romar%20Town', 'mail.php?op=send&to[]=1', 'list.php?page=2#top',
            'newday.php?resurrection=true', str_repeat('a', 124) . '.php'] as $page) {
            self::assertSame($page, NavigationState::restorePage($page), $page);
        }
        self::assertSame('', NavigationState::restorePage(''));
        self::assertSame('', NavigationState::restorePage(null));
    }

    public function testMalformedNavigationFails(): void
    {
        $bad = ['garbage', 'O:8:"stdClass":0:{}', 's:11:"village.php";', 'b:1;', 'i:0;', 'N;',
            'a:1:{s:11:"village.php";O:8:"stdClass":0:{}}', serialize([1 => 'village.php']), serialize([0 => true]),
            serialize([0 => 'x', 1 => 'y']), serialize(['village.php' => 1]), serialize(['village.php' => 'yes']),
            serialize(['village.php' => null]), serialize(['village.php' => ['a' => true]]), serialize(["village.php\n" => true])];
        foreach ($bad as $stored) {
            try { NavigationState::read($stored); self::fail('Accepted ' . $stored); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([[0 => 'legacy'], 'village.php', null, [5 => true]] as $map) {
            try { NavigationState::map($map); self::fail('Accepted a non-map.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach (["village.php?x='><script>", 'village.php?a="b"', 'village.php?<b>', 'village.php?a\\b', 'village.php?a`b',
            'village.php?a b', "village.php\n", "village.php?a=\x7F", 'village.php?é', 'javascript:alert(1)', 'http://evil.example/',
            '/village.php', '../village.php', 'village', 'village.phps', '.php', str_repeat('a', 125) . '.php', 5, false, []] as $page) {
            try { NavigationState::restorePage($page); self::fail('Accepted ' . json_encode($page)); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
