<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The character panel (Phase 5 task 7, docs/PHASE5-TASK7-CHARACTER-PANEL-20261030.md): charstats() rendered by the
 * game's own code through tests/fixtures/charstats-render.php, in a PHP process of its own, with no database.
 *
 * The 1.1.2 skins' panel is pinned from d0f9415, the code before the new panel: every old skin, for each player below,
 * must render byte for byte as it did. The new template renders a header card (portrait, name, level, race and
 * specialty), the HP (or soulpoints) and XP bars from numbers, six stat tiles, equipment rows, then buffs, companions
 * and the rows modules added.
 */
final class CharacterPanelTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    /** A level 7 elf with the Mystical Powers specialty, a mount, a companion and two buffs. */
    private const array ALIVE = [
        'name' => '`%Farmboy `&Aric', 'level' => 7, 'hitpoints' => 63, 'maxhitpoints' => 70, 'turns' => 12,
        'attack' => 14, 'defense' => 13, 'spirits' => 1, 'alive' => 1, 'race' => 'Elf', 'gold' => 512, 'gems' => 3,
        'experience' => 5025, 'dragonkills' => 0, 'weapon' => 'Short Sword', 'armor' => 'Leather Tunic',
        'hashorse' => 2, 'soulpoints' => 85, 'gravefights' => 5, 'deathpower' => 30, 'superuser' => 0, 'specialty' => 'MP',
    ];
    private const array BUFFS = [
        'mount' => ['name' => '`&Pony Ride', 'rounds' => 12, 'atkmod' => 1.2, 'schema' => 'mounts'],
        'aura' => ['name' => '`#Lucky Aura', 'rounds' => -1, 'defmod' => 0.9, 'schema' => ''],
        'resting' => ['name' => 'Resting', 'rounds' => 3, 'suspended' => true, 'atkmod' => 0.5, 'schema' => ''],
        'curse' => ['name' => ['`$Curse of %s', 'Ramius'], 'rounds' => 2, 'schema' => ''],
    ];
    private const array COMPANIONS = [
        'fido' => ['name' => '`7Fido', 'hitpoints' => 8, 'maxhitpoints' => 10],
        'golem' => ['name' => '`)Stone Golem', 'hitpoints' => 30, 'maxhitpoints' => 30, 'suspended' => true],
        'ghost' => ['name' => 'Ghost', 'hitpoints' => -3, 'maxhitpoints' => 12, 'cannotdie' => true],
        'fallen' => ['name' => 'Fallen Squire', 'hitpoints' => 0, 'maxhitpoints' => 15],
    ];
    private const array MOUNT = ['mountname' => '`&Pony'];
    /** RACE_UNKNOWN (lib/constants.php): the race the game stores for a player who has none. */
    private const string BLOB = 'Horrible Gelatinous Blob';
    private const array SPECIALTIES = ['DA' => 'Dark Arts', 'MP' => 'Mystical Powers', 'TS' => 'Thieving Skills'];
    /** What a module adds in the charstats hook: a row to a standard section, a new section, a set row, a buff-style row. */
    private const array MODULE = [
        ['addcharstat', 'Vital Info'], ['addcharstat', 'Charm', '`%12'],
        ['addcharstat', 'Clan Info'], ['addcharstat', 'Clan', '`#The Watch'], ['addcharstat', 'Clan Info', 'The clan hall is open'],
        ['setcharstat', 'Equipment Info', 'Ring', '`^Ring of Fire'],
        ['setcharstat', 'Personal Info', 'Gems', '3 `@(+1 hidden)'],
    ];

    /**
     * Each player, and the SHA-256 of every old skin's panel for that player as d0f9415 rendered it (a JSON object of
     * skin => HTML, the skins in name order).
     */
    private const array PINNED_OLD_SKINS = [
        'alive' => '75a530f80bd6fd524923399d6b2e3fec32ed6e7386cbd4ff6b630338df65c151',
        'dead' => 'd3a7dc3f3d712161a28829d3afb5d3b9a0cbc86604f50f5805eeb2e6230f7e7f',
        'unchosen' => 'd5d3f8ba946df545eddb2f8858c6d764469698f15cce3216cce9a001885307f9',
        'blob' => '70f20d6cd221b2336c72bbe7774446312ad31b14838c427de52f13576862ba31',
        'capped' => '297b7e663856f265ce69a2a02f924c08220d7a9a0d643e903f26e87f11f49aaf',
        'overmax' => 'f27d118e061697840cd2c2dbf111eb58b99a153ced1caeabab2438c1b4d7323c',
        'module' => 'dfed752acdd4b77bb7d6e90c87c89f00f8b2bf3dc7731a26e220ace465fa34bb',
        'bare' => '198af48c87721691da80018fe8ea0301d8adc2f41e438d810d0c9f78070b1f58',
    ];

    /** @return array<string, array<string, mixed>> the players the old-skin pin covers */
    private static function players(): array
    {
        $alive = ['user' => self::ALIVE, 'bufflist' => self::BUFFS, 'companions' => self::COMPANIONS, 'mount' => self::MOUNT,
            'tempstats' => ['gold' => 5, 'attack' => -1], 'specialties' => self::SPECIALTIES];
        $dead = $alive;
        $dead['user'] = array_merge(self::ALIVE, ['alive' => 0, 'hitpoints' => 0, 'spirits' => -1, 'hashorse' => 0]);
        $dead['bufflist'] = [];
        $dead['companions'] = [];
        $dead['tempstats'] = ['deathpower' => 10];
        $unchosen = $alive;
        $unchosen['user'] = array_merge(self::ALIVE, ['race' => '0', 'specialty' => '', 'level' => 1, 'experience' => 0, 'hashorse' => 0]);
        $blob = $unchosen;
        $blob['user']['race'] = self::BLOB;
        $capped = $alive;
        $capped['user'] = array_merge(self::ALIVE, ['level' => 15, 'experience' => 52000, 'dragonkills' => 2]);
        $overmax = $alive;
        $overmax['user'] = array_merge(self::ALIVE, ['hitpoints' => 91.6, 'maxhitpoints' => 70, 'experience' => 7000]);
        $module = $alive;
        $module['module'] = self::MODULE;
        $bare = ['user' => array_merge(self::ALIVE, ['hashorse' => 0, 'specialty' => 'XX']), 'specialties' => self::SPECIALTIES];
        return ['alive' => $alive, 'dead' => $dead, 'unchosen' => $unchosen, 'blob' => $blob, 'capped' => $capped,
            'overmax' => $overmax, 'module' => $module, 'bare' => $bare];
    }

    public function testEveryOldSkinRendersItsPanelAsBefore(): void
    {
        $skins = self::oldSkins();
        self::assertCount(18, $skins);
        $hashes = [];
        foreach (self::players() as $player => $input) {
            $pages = [];
            foreach ($skins as $skin) {
                $page = $this->render($skin, $input);
                // The old skins call no hook the new panel added.
                self::assertSame(['charstats'], $page['hooks'], "$player $skin");
                $pages[$skin] = $page['html'];
            }
            $hashes[$player] = hash('sha256', (string)json_encode($pages, JSON_UNESCAPED_SLASHES));
        }
        self::assertSame(self::PINNED_OLD_SKINS, $hashes);
    }

    public function testTheNewPanelShowsTheHeaderBarsTilesEquipmentAndBuffs(): void
    {
        $page = $this->render('resurrection.htm', self::players()['alive']);
        $html = $page['html'];
        // The header card: the elf's portrait, the name, and level, race and specialty; no Name, Level or Race row.
        self::assertSame(['src' => 'assets/portraits/race/elf.svg', 'alt' => 'Elf'], $this->portrait($html));
        self::assertSame('Farmboy Aric', $this->text($html, 'rz-statname'));
        self::assertSame('Level 7 Elf · Mystical Powers', $this->text($html, 'rz-statsub'));
        self::assertSame(['charstats', 'specialtynames'], $page['hooks']);
        // The bars: the stored numbers, the fill their ratio.
        self::assertSame([
            ['kind' => 'health', 'label' => 'Hitpoints', 'value' => '63 / 70', 'percent' => '90', 'caption' => null],
            ['kind' => 'experience', 'label' => 'Experience', 'value' => '5025 / 6641', 'percent' => '75.7', 'caption' => null],
        ], $this->bars($html));
        // The six tiles, in order; attack and defense with the buffs' difference in the bonus and penalty colors, and a
        // temporary stat's note as charstats() writes it.
        self::assertSame(['Turns' => '12', 'Spirits' => 'High', 'Attack' => '13+2.6 (12--1)', 'Defense' => '13-1.3',
            'Gold' => '517 (512+5)', 'Gems' => '3'], $this->tiles($html));
        self::assertStringContainsString('<span class="rz-stattile-value">13<span class=\'colLtGreen\'>+2.6 </span>', $html);
        self::assertStringContainsString('<span class="rz-stattile-value">13<span class=\'colLtRed\'>-1.3</span>', $html);
        // Equipment: weapon, armor and the mount.
        self::assertSame(['Weapon' => 'Short Sword', 'Armor' => 'Leather Tunic', 'Mount' => 'Pony'], $this->equipment($html));
        // Then buffs (the suspended one left out), then companions; no other section.
        self::assertSame(['Buffs', 'Companions'], $this->sections($html));
        self::assertSame('Pony Ride (12 rounds left) Lucky Aura Curse of Ramius (2 rounds left)', $this->section($html, 'Buffs'));
        self::assertStringNotContainsString('Resting', $html);
        foreach (['Name', 'Level', 'Race', 'Hitpoints', 'Experience', 'Gold', 'Weapon', 'Creature'] as $row) {
            self::assertStringNotContainsString(">$row</span></span><span class=\"rz-statvalue\">", $html, $row);
        }
        self::assertSame(1, substr_count($html, 'class="rz-card rz-statcard"'));
        // The card comes first, the buffs and companions after it.
        self::assertLessThan(strpos($html, 'rz-statsection'), strpos($html, 'rz-equiprows'));
    }

    public function testCompanionsRenderAsRowsInTheirSection(): void
    {
        $html = $this->render('resurrection.htm', self::players()['alive'])['html'];
        // After the card and the buffs.
        self::assertSame(['Buffs', 'Companions'], $this->sections($html));
        self::assertGreaterThan((int)strpos($html, 'class="rz-card rz-statcard"'), (int)strpos($html, '>Companions</h2>'));
        // Living companions, and one that cannot die at 0; a fallen one is left out, as charstats() leaves it.
        self::assertSame(['Fido' => '8/10', 'Stone Golem' => '30/30 *', 'Ghost' => '0/12'], $this->rows($html, 'Companions'));
        self::assertStringContainsString("<span class='colLtRed'>8</span><span class='colDkWhite'>/</span><span class='colLtWhite'>10</span>", $html);
        self::assertStringNotContainsString('Fallen Squire', $html);
        // No companions: no section.
        self::assertNotContains('Companions', $this->sections($this->render('resurrection.htm', self::players()['bare'])['html']));
    }

    public function testHitpointsAboveTheMaximumFillTheBarAndShowTheStoredNumbers(): void
    {
        $bars = $this->bars($this->render('resurrection.htm', self::players()['overmax'])['html']);
        // 91.6 hitpoints, rounded by charstats() as 1.1.2 rounds them, against 70; experience past what level 8 needs.
        self::assertSame(['kind' => 'health', 'label' => 'Hitpoints', 'value' => '92 / 70', 'percent' => '100', 'caption' => null], $bars[0]);
        self::assertSame(['kind' => 'experience', 'label' => 'Experience', 'value' => '7000 / 6641', 'percent' => '100', 'caption' => null], $bars[1]);
        // Nothing below empty.
        $hurt = self::players()['bare'];
        $hurt['user']['hitpoints'] = -4;
        self::assertSame(['kind' => 'health', 'label' => 'Hitpoints', 'value' => '-4 / 70', 'percent' => '0', 'caption' => null],
            $this->bars($this->render('resurrection.htm', $hurt)['html'])[0]);
    }

    public function testTheLevelCapFillsTheExperienceBarWithTheGamesWording(): void
    {
        // Level 15: no level follows. Before the day's dragon fight the bar says what the forest offers.
        $capped = self::players()['capped'];
        $capped['user']['seendragon'] = 0;
        $html = $this->render('resurrection.htm', $capped)['html'];
        self::assertSame(['kind' => 'experience', 'label' => 'Experience', 'value' => '52000', 'percent' => '100',
            'caption' => 'Seek Out the Green Dragon'], $this->bars($html)[1]);
        self::assertSame('Level 15 Elf · Mystical Powers', $this->text($html, 'rz-statsub'));
        // Once the dragon has been sought today, and when dead, the line train.php says on reaching level 15.
        $capped['user']['seendragon'] = 1;
        self::assertSame(['kind' => 'experience', 'label' => 'Experience', 'value' => '52000', 'percent' => '100',
            'caption' => 'None in the land are mightier than you!'], $this->bars($this->render('resurrection.htm', $capped)['html'])[1]);
        $capped['user']['seendragon'] = 0;
        $capped['user']['alive'] = 0;
        self::assertSame('None in the land are mightier than you!', $this->bars($this->render('resurrection.htm', $capped)['html'])[1]['caption']);
        // Translated as the game translates it.
        $capped['user']['alive'] = 1;
        $capped['translations'] = ['Seek Out the Green Dragon' => 'Suche den Grünen Drachen'];
        self::assertSame('Suche den Grünen Drachen', $this->bars($this->render('resurrection.htm', $capped)['html'])[1]['caption']);
        // Level 14 with the experience for 15: still "current / max", full.
        $ready = self::players()['bare'];
        $ready['user'] = array_merge($ready['user'], ['level' => 14, 'experience' => 40000]);
        self::assertSame(['kind' => 'experience', 'label' => 'Experience', 'value' => '40000 / 36071', 'percent' => '100', 'caption' => null],
            $this->bars($this->render('resurrection.htm', $ready)['html'])[1]);
    }

    public function testADeadPlayerShowsSoulpointsTormentsAndFavorInTheSameComponents(): void
    {
        $dead = self::players()['dead'];
        $dead['user']['soulpoints'] = 40;
        $html = $this->render('resurrection.htm', $dead)['html'];
        // Soulpoints against level x 5 + 50, as charstats() works it out; experience as when alive.
        self::assertSame([
            ['kind' => 'soul', 'label' => 'Soulpoints', 'value' => '40 / 85', 'percent' => '47.1', 'caption' => null],
            ['kind' => 'experience', 'label' => 'Experience', 'value' => '5025 / 6641', 'percent' => '75.7', 'caption' => null],
        ], $this->bars($html));
        self::assertSame(['Torments' => '5', 'Spirits' => 'DEAD', 'Psyche' => '19', 'Spirit' => '19', 'Favor' => '40 (30+10)',
            'Gems' => '3'], $this->tiles($html));
        self::assertSame(['Weapon' => 'Short Sword', 'Armor' => 'Leather Tunic'], $this->equipment($html));
        foreach (['Hitpoints', 'Turns', 'Attack', 'Defense', 'Gold'] as $absent) {
            self::assertStringNotContainsString(">$absent<", $html, $absent);
        }
        self::assertSame('None', $this->section($html, 'Buffs'));
    }

    public function testARaceNotChosenYetIsNamedSoWithTheUnknownPortrait(): void
    {
        foreach (['unchosen', 'blob'] as $player) {
            $page = $this->render('resurrection.htm', self::players()[$player]);
            self::assertSame('Level 1 Not chosen yet', $this->text($page['html'], 'rz-statsub'), $player);
            self::assertSame(['src' => 'assets/portraits/race/unknown.svg', 'alt' => 'Not chosen yet'], $this->portrait($page['html']), $player);
            self::assertStringNotContainsString('Gelatinous', $page['html'], $player);
            // No specialty: no separator and no specialty hook.
            self::assertSame(['charstats'], $page['hooks'], $player);
        }
        // Translated.
        $translated = self::players()['unchosen'];
        $translated['translations'] = ['Not chosen yet' => 'Noch nicht gewählt', 'Level %s %s' => 'Stufe %s, %s'];
        self::assertSame('Stufe 1, Noch nicht gewählt', $this->text($this->render('resurrection.htm', $translated)['html'], 'rz-statsub'));
        // A specialty code no module names is left out.
        self::assertSame('Level 7 Elf', $this->text($this->render('resurrection.htm', self::players()['bare'])['html'], 'rz-statsub'));
    }

    public function testRowsAModuleAddsAppearInTheirSections(): void
    {
        $module = self::players()['module'];
        $module['translations'] = ['`&Charm`0' => '`&Charme`0', 'Clan Info' => 'Clan'];
        $html = $this->render('resurrection.htm', $module)['html'];
        // A row added to a standard section, under that section's title; a new section with a row and a buff-style row;
        // a row added to Equipment Info among the equipment.
        // The buff-style row's title is translated as getcharstats() translates it, with its `0 in front.
        self::assertSame(['Buffs', 'Companions', 'Vital Info', 'Clan', 'Clan Info'], $this->sections($html));
        self::assertSame(['Charme' => '12'], $this->rows($html, 'Vital Info'));
        self::assertSame(['Clan' => 'The Watch'], $this->rows($html, 'Clan'));
        self::assertStringContainsString('<h2 class="rz-stathead">Clan Info</h2><div class="rz-statbuff"><span class=\'colLtYellow\'>The clan hall is open</span></div>', $html);
        self::assertSame(['Weapon' => 'Short Sword', 'Armor' => 'Leather Tunic', 'Mount' => 'Pony', 'Ring' => 'Ring of Fire'], $this->equipment($html));
        // A standard row a module changed shows the module's value in its component.
        self::assertSame('3 (+1 hidden)', $this->tiles($html)['Gems']);
        // The module's colors are kept.
        self::assertStringContainsString("<span class='colLtMagenta'>12</span>", $html);
    }

    /**
     * The panel's portrait.
     *
     * @return array{src: string, alt: string}|null
     */
    private function portrait(string $html): ?array
    {
        self::assertLessThanOrEqual(1, preg_match_all('#<img class="rz-statportrait" src="([^"]*)" alt="([^"]*)" width="200" height="240">#', $html, $m));
        return isset($m[1][0]) ? ['src' => $m[1][0], 'alt' => html_entity_decode($m[2][0], ENT_QUOTES)] : null;
    }

    /** The text in the element with this class, tags stripped. */
    private function text(string $html, string $class): string
    {
        self::assertSame(1, preg_match('#<(\w+) class="' . $class . '">(.*?)</\1>#s', $html, $m), $class);
        return trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES));
    }

    /**
     * The bars: their kind, label, value text, fill and caption (null: a bar with a maximum, which has none).
     *
     * @return list<array{kind: string, label: string, value: string, percent: string, caption: string|null}>
     */
    private function bars(string $html): array
    {
        preg_match_all('#<div class="rz-statbar rz-statbar-([a-z]+)"><div class="rz-statbar-text"><span class="rz-statbar-label">([^<]*)</span>'
            . '<span class="rz-statbar-value">(.*?)</span></div><div class="rz-statbar-track" aria-hidden="true"><div class="rz-statbar-fill" style="width: ([0-9.]+)%"></div></div>'
            . '(?:<div class="rz-statbar-caption">(.*?)</div>)?</div>#s', $html, $m, PREG_SET_ORDER);
        self::assertSame(substr_count($html, 'class="rz-statbar '), count($m));
        return array_map(fn (array $bar) => ['kind' => $bar[1], 'label' => $bar[2], 'value' => trim(strip_tags($bar[3])),
            'percent' => $bar[4], 'caption' => isset($bar[5]) ? strip_tags($bar[5]) : null], $m);
    }

    /** @return array<string, string> tile label => value text */
    private function tiles(string $html): array
    {
        preg_match_all('#<div class="rz-stattile"><span class="rz-stattile-label">([^<]*)</span><span class="rz-stattile-value">(.*?)</span></div>#s', $html, $m);
        self::assertSame(substr_count($html, 'class="rz-stattile"'), count($m[1]));
        return array_combine($m[1], array_map(fn (string $v) => trim(strip_tags($v)), $m[2]));
    }

    /** @return array<string, string> equipment label => value text */
    private function equipment(string $html): array
    {
        preg_match_all('#<div class="rz-equiprow"><span class="rz-equiprow-label">(.*?)</span><span class="rz-equiprow-value">(.*?)</span></div>#s', $html, $m);
        self::assertSame(substr_count($html, 'class="rz-equiprow"'), count($m[1]));
        return array_combine(array_map(fn (string $v) => trim(strip_tags($v)), $m[1]), array_map(fn (string $v) => trim(strip_tags($v)), $m[2]));
    }

    /** @return list<string> the titles of the sections after the card, in order */
    private function sections(string $html): array
    {
        preg_match_all('#<section class="rz-card rz-statsection"><h2 class="rz-stathead">(.*?)</h2>#s', $html, $m);
        return array_map(fn (string $v) => trim(strip_tags($v)), $m[1]);
    }

    /** The text of the first section with this title. */
    private function section(string $html, string $title): string
    {
        self::assertSame(1, preg_match('#<h2 class="rz-stathead">' . preg_quote($title, '#') . '</h2>(.*?)</section>#s', $html, $m), $title);
        return trim((string)preg_replace('/\s+/', ' ', strip_tags($m[1])));
    }

    /** @return array<string, string> the label => value rows of the first section with this title */
    private function rows(string $html, string $title): array
    {
        self::assertSame(1, preg_match('#<h2 class="rz-stathead">' . preg_quote($title, '#') . '</h2>(.*?)</section>#s', $html, $m), $title);
        preg_match_all('#<div class="rz-statrow"><span class="rz-statlabel">(.*?)</span><span class="rz-statvalue">(.*?)</span></div>#s', $m[1], $rows);
        return array_combine(array_map(fn (string $v) => trim(strip_tags($v)), $rows[1]), array_map(fn (string $v) => trim(strip_tags($v)), $rows[2]));
    }

    /** @return list<string> every template except the new one */
    private static function oldSkins(): array
    {
        $skins = array_map('basename', (array)glob(self::ROOT . '/templates/*.htm'));
        sort($skins);
        return array_values(array_filter($skins, fn (string $skin) => $skin !== 'resurrection.htm'));
    }

    /**
     * Renders the panel through tests/fixtures/charstats-render.php from the tree's root.
     *
     * @param array<string, mixed> $input
     * @return array{html: string, output: string, hooks: list<string>}
     */
    private function render(string $template, array $input): array
    {
        $input['template'] = $template;
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', 'tests/fixtures/charstats-render.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, (string)realpath(self::ROOT));
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        $page = json_decode($out, true);
        self::assertIsArray($page, $out);
        // Nothing the panel printed into the page (a template warning would be).
        self::assertSame('', $page['output']);
        return $page;
    }
}
