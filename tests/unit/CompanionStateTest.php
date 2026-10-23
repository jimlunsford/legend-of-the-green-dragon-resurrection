<?php
declare(strict_types=1);
namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Game\CompanionState;
use Resurrection\Security\ScalarState;

/** The companion schema against every companion shape the 1.1.2 installer and the shipped code write (docs/SERIALIZED-SCHEMAS-PART3-20261022.md). */
final class CompanionStateTest extends TestCase
{
    /** The installer's companions (lib/installer/installer_sqlstatements.php) and the dwarf's (modules/racedwarf.php), as stored. */
    private const SHIPPED = [
        'Mortimer teh javelin man' => ['installer', '1', 'Knight', 5, 2, 1, 2, 20, 20, 'a:4:{s:5:"fight";s:1:"1";s:4:"heal";s:1:"0";s:5:"magic";s:1:"0";s:6:"defend";b:0;}',
            ['fight' => 1, 'heal' => 0, 'magic' => 0, 'defend' => false], 573, 4],
        'Florenz' => ['installer', '2', 'Healer', 1, 1, 5, 5, 15, 10, 'a:4:{s:4:"heal";s:1:"2";s:5:"magic";s:1:"0";s:5:"fight";b:0;s:6:"defend";b:0;}',
            ['heal' => 2, 'magic' => 0, 'fight' => false, 'defend' => false], 1000, 3],
        'Grizzly Bear' => ['dwarf', '3', 'Wild Beasts', 1, 2, 5, 2, 25, 25, 'a:4:{s:5:"fight";s:1:"0";s:4:"heal";s:1:"0";s:5:"magic";s:1:"0";s:6:"defend";s:1:"1";}',
            ['fight' => 0, 'heal' => 0, 'magic' => 0, 'defend' => 1], 600, 4],
    ];
    private const MESSAGES = ['healmsg' => '', 'healcompanionmsg' => '', 'magicfailmsg' => ''];

    /** A companions row as PDO returns it (text), scaled as mercenarycamp.php and the editor's Take scale it. */
    private function hired(string $name, int $level = 10): array
    {
        [, $id, $category, $attack, $attackper, $defense, $defenseper, $hp, $hpper, $abilities, , $gold, $gems] = self::SHIPPED[$name];
        $row = ['companionid' => $id, 'name' => $name, 'category' => $category, 'description' => 'Fixture description.',
            'attack' => (string)$attack, 'attackperlevel' => (string)$attackper, 'defense' => (string)$defense, 'defenseperlevel' => (string)$defenseper,
            'maxhitpoints' => (string)$hp, 'maxhitpointsperlevel' => (string)$hpper, 'abilities' => $abilities, 'cannotdie' => '0',
            'cannotbehealed' => '0', 'companionlocation' => 'Degolburg', 'companionactive' => '1', 'companioncostdks' => '0',
            'companioncostgems' => (string)$gems, 'companioncostgold' => (string)$gold, 'jointext' => 'Hello.', 'dyingtext' => 'Gone.',
            'allowinshades' => '1', 'allowinpvp' => '0', 'allowintrain' => '0'];
        // mercenarycamp.php op=buy and resurrection_companion_take(): 1.1.2's formulas on the text columns.
        $row['attack'] = $row['attack'] + $row['attackperlevel'] * $level;
        $row['defense'] = $row['defense'] + $row['defenseperlevel'] * $level;
        $row['maxhitpoints'] = $row['maxhitpoints'] + $row['maxhitpointsperlevel'] * $level;
        $row['hitpoints'] = $row['maxhitpoints'];
        $row['abilities'] = ScalarState::read($row['abilities']);
        return $row;
    }

    private function skeleton(): array
    {
        return ['name' => '`4Skeleton Warrior', 'hitpoints' => 43.0, 'maxhitpoints' => 43.0, 'attack' => 26.5, 'defense' => 14.5,
            'dyingtext' => '`$Your skeleton warrior crumbles to dust.`n', 'abilities' => ['fight' => true], 'ignorelimit' => true];
    }

    public function testShippedMercenariesPassAndNormalize(): void
    {
        $sources = ['installer' => (string)file_get_contents(__DIR__ . '/../../lib/installer/installer_sqlstatements.php'),
            'dwarf' => (string)file_get_contents(__DIR__ . '/../../modules/racedwarf.php')];
        foreach (self::SHIPPED as $name => [$source, $id, , $attack, $attackper, $defense, $defenseper, $hp, $hpper, $abilities, $normalized, $gold, $gems]) {
            self::assertStringContainsString("'" . addcslashes($abilities, '"') . "'", $sources[$source], $name);
            $row = $this->hired($name);
            $companion = CompanionState::companion($name, $row);
            $expected = array_replace($row, ['companionid' => (int)$id, 'attack' => $attack + $attackper * 10, 'attackperlevel' => $attackper,
                'defense' => $defense + $defenseper * 10, 'defenseperlevel' => $defenseper, 'maxhitpoints' => $hp + $hpper * 10,
                'maxhitpointsperlevel' => $hpper, 'hitpoints' => $hp + $hpper * 10, 'abilities' => $normalized, 'cannotdie' => 0, 'cannotbehealed' => 0,
                'companionactive' => 1, 'companioncostdks' => 0, 'companioncostgems' => $gems, 'companioncostgold' => $gold, 'allowinshades' => 1,
                'allowinpvp' => 0, 'allowintrain' => 0]) + self::MESSAGES;
            self::assertSame($expected, $companion, $name);
            // Normalized once: a second pass changes nothing, and the stored list reads back the same.
            self::assertSame($companion, CompanionState::companion($name, $companion));
            self::assertSame([$name => $companion], CompanionState::read(CompanionState::encode([$name => $row])));
        }
        // A level 1 hire and a name PHP keys as an int, both as the camp stores them.
        self::assertSame(25, CompanionState::companion('Florenz', $this->hired('Florenz', 1))['maxhitpoints']);
        self::assertArrayHasKey(12, CompanionState::collection([12 => ['name' => '12'] + $this->hired('Florenz')]));
    }

    public function testEditorTakeAndBattleRuntimeShapesPass(): void
    {
        // lib/mount_companion_editor.php resurrection_companion_abilities(): a checked box is "1" and comes first, levels are text.
        $editor = ['fight' => '1', 'heal' => '3', 'magic' => '0', 'defend' => false];
        $taken = ['abilities' => $editor] + $this->hired('Mortimer teh javelin man', 5);
        $companion = CompanionState::companion('Pal', $taken);
        self::assertSame(['fight' => 1, 'heal' => 3, 'magic' => 0, 'defend' => false], $companion['abilities']);
        self::assertSame([15, 11, 120], [$companion['attack'], $companion['defense'], $companion['maxhitpoints']]);
        // apply_companion(..., true) adds ignorelimit; the battle marks use and suspension and leaves float health after a hit.
        $runtime = array_replace($companion, ['ignorelimit' => true, 'used' => true, 'suspended' => false, 'hitpoints' => 7.0]);
        self::assertSame($runtime, CompanionState::companion('Pal', $runtime));
        // A companion that cannot die stays at 0 hitpoints (report_companion_move()).
        $unconscious = array_replace($companion, ['cannotdie' => 1, 'hitpoints' => 0]);
        self::assertSame($unconscious, CompanionState::companion('Pal', $unconscious));
        // Module-supplied messages and fields combat reads are kept as given.
        $messages = array_replace($companion, ['healmsg' => 'Patched up.', 'magicmsg' => 'Zap {damage}.', 'schema' => 'module-x', 'expireafterfight' => 1]);
        self::assertSame($messages, CompanionState::companion('Pal', $messages));
        // The Dark Arts skeleton keeps its exact producer schema, unchanged, beside a mercenary.
        $list = ['skeleton_warrior' => $this->skeleton(), 'Pal' => $companion];
        self::assertSame($list, CompanionState::collection($list));
        self::assertSame($list, CompanionState::read(CompanionState::encode($list)));
        $flags = array_replace($this->skeleton(), ['hitpoints' => 1, 'used' => true, 'suspended' => true]);
        self::assertSame(['skeleton_warrior' => $flags], CompanionState::collection(['skeleton_warrior' => $flags]));
    }

    public function testEmptyStoredListIsNoCompanions(): void
    {
        self::assertSame([], CompanionState::read(''));
        self::assertSame([], CompanionState::read(null));
        self::assertSame([], CompanionState::read('a:0:{}'));
        self::assertSame('a:0:{}', CompanionState::encode([]));
    }

    public function testShippedAbilitiesPassAndNormalize(): void
    {
        foreach (self::SHIPPED as $name => $shipped) {
            self::assertSame($shipped[10], CompanionState::abilities(ScalarState::read($shipped[9])), $name);
            self::assertSame($shipped[10], CompanionState::abilities($shipped[10]), $name);
        }
        foreach ([[], ['fight' => true], ['defend' => '1', 'heal' => '30', 'magic' => '30', 'fight' => false], ['heal' => 0, 'magic' => 0]] as $abilities) {
            CompanionState::abilities($abilities);
        }
        self::assertSame(['fight' => true], CompanionState::abilities(['fight' => true]));
        self::assertSame(['heal' => 30, 'magic' => 7], CompanionState::abilities(['heal' => '30', 'magic' => 7]));
        foreach ([null, false, '', 'fight', 1, ['evil' => 1], [0 => 'fight'], ['fight' => 2], ['fight' => 'yes'], ['fight' => []], ['fight' => null],
            ['defend' => '2'], ['heal' => 31], ['heal' => '31'], ['heal' => -1], ['heal' => '-1'], ['heal' => '1.5'], ['heal' => 1.5], ['heal' => true],
            ['heal' => '01'], ['heal' => ' 1'], ['magic' => 'x'], ['magic' => null], ['magic' => 100]] as $bad) {
            try { CompanionState::abilities($bad); self::fail('Accepted ' . json_encode($bad)); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }

    public function testMalformedCompanionsFail(): void
    {
        $good = $this->hired('Mortimer teh javelin man');
        $bad = [];
        foreach ([['hitpoints', -1], ['hitpoints', 221], ['hitpoints', 0], ['hitpoints', '0'], ['maxhitpoints', 0], ['attack', '1e3'], ['attack', '-1'],
            ['attack', ' 1'], ['attack', '0x1A'], ['attack', INF], ['attack', NAN], ['attack', 2147483648], ['attack', []], ['attack', true], ['attack', null],
            ['defense', 'abc'], ['companionid', -1], ['name', ''], ['name', []], ['name', str_repeat('x', 256)], ['description', str_repeat('x', 8193)],
            ['healmsg', []], ['dyingtext', 5], ['used', 2], ['used', 'yes'], ['cannotdie', 'true'], ['allowinpvp', []], ['suspended', null],
            ['unknown', 1], ['rounds', 5], ['atkmod', 2], ['abilities', 'fight'], ['abilities', null], ['abilities', ['fight' => 2]],
            ['abilities', ['heal' => 31]], ['abilities', ['evil' => true]]] as [$key, $value]) {
            $bad[] = [$key => $value] + $good;
        }
        foreach (['name', 'hitpoints', 'maxhitpoints', 'attack', 'defense', 'abilities'] as $key) {
            $missing = $good; unset($missing[$key]); $bad[] = $missing;
        }
        $bad[] = null; $bad[] = 'companion'; $bad[] = [];
        foreach ($bad as $companion) $this->reject(['Mortimer' => $companion]);
        $this->reject(['' => $good]);
        $this->reject([str_repeat('x', 256) => $good]);
        $this->reject(array_fill_keys(array_map(static fn (int $i) => "c$i", range(1, 101)), $good));
        // The skeleton's exact rules still apply under its key.
        $this->reject(['skeleton_warrior' => $good]);
        $this->reject(['skeleton_warrior' => array_replace($this->skeleton(), ['used' => 1])]);
        foreach (['broken', 'a:0:{}junk', 'O:8:"stdClass":0:{}', serialize('x'), serialize(5), serialize(['Mortimer' => 'x']), false, 0, []] as $stored) {
            try { CompanionState::read($stored); self::fail('Accepted stored ' . json_encode($stored)); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        // A list the column cannot hold is never encoded.
        $large = array_fill_keys(array_map(static fn (int $i) => "c$i", range(1, 10)), ['description' => str_repeat('x', 8192)] + $good);
        try { CompanionState::encode($large); self::fail('Encoded an oversize list'); }
        catch (\DomainException) { self::assertTrue(true); }
    }

    private function reject(array $companions): void
    {
        try { CompanionState::collection($companions); self::fail('Accepted ' . json_encode($companions, JSON_PARTIAL_OUTPUT_ON_ERROR)); }
        catch (\DomainException) { self::assertTrue(true); }
        try { CompanionState::encode($companions); self::fail('Encoded ' . json_encode($companions, JSON_PARTIAL_OUTPUT_ON_ERROR)); }
        catch (\DomainException) { self::assertTrue(true); }
    }
}
