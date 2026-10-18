<?php
declare(strict_types=1);
namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Game\BuffState;
use Resurrection\Game\TransmutationState;
use Resurrection\Security\ScalarState;

/** The buff schema against every buff shape the 1.1.2 installer and the shipped code write (docs/SERIALIZED-SCHEMAS-PART2-20261018.md). */
final class BuffStateTest extends TestCase
{
    private const PLAYER = ['attack' => '14', 'defense' => '12', 'level' => '6'];

    /** The installer mounts' buffs (lib/installer/installer_sqlstatements.php), as 1.1.2 stored them. */
    private const INSTALLER = [
        'Pony' => ['a:5:{s:4:"name";s:13:"`&Pony Attack";s:8:"roundmsg";s:26:"Your pony fights with you!";s:6:"rounds";s:2:"20";s:6:"atkmod";s:3:"1.2";s:8:"activate";s:7:"offense";}', 20],
        'Gelding' => ['a:5:{s:4:"name";s:16:"`&Gelding Attack";s:8:"roundmsg";s:29:"Your gelding fights with you!";s:6:"rounds";s:2:"40";s:6:"atkmod";s:3:"1.2";s:8:"activate";s:7:"offense";}', 40],
        'Stallion' => ['a:5:{s:4:"name";s:17:"`&Stallion Attack";s:8:"roundmsg";s:30:"Your stallion fights with you!";s:6:"rounds";s:2:"60";s:6:"atkmod";s:3:"1.2";s:8:"activate";s:7:"offense";}', 60],
    ];

    public function testInstallerMountsPassAsTheStablesApplyThem(): void
    {
        $installer = (string)file_get_contents(__DIR__ . '/../../lib/installer/installer_sqlstatements.php');
        foreach (self::INSTALLER as $mount => [$stored, $rounds]) {
            self::assertStringContainsString(addcslashes($stored, '"'), $installer, $mount);
            $buff = BuffState::mount(ScalarState::read($stored), self::PLAYER);
            self::assertSame(['name' => "`&$mount Attack", 'roundmsg' => 'Your ' . strtolower($mount) . ' fights with you!', 'rounds' => $rounds,
                'atkmod' => 1.2, 'activate' => 'offense', 'schema' => 'mounts'], $buff, $mount);
            // Normalized once: a second pass changes nothing, and the stored list reads back the same.
            self::assertSame($buff, BuffState::mount($buff, self::PLAYER));
            self::assertSame(['mount' => $buff], BuffState::read(BuffState::encode(['mount' => $buff], self::PLAYER), self::PLAYER));
        }
        // A mount buff that names its schema keeps it.
        self::assertSame('custom', BuffState::mount(['name' => 'x', 'rounds' => 1, 'schema' => 'custom'], [])['schema']);
    }

    public function testMountEditorTextFieldsPass(): void
    {
        // lib/mount_companion_editor.php resurrection_mount_buff(): every non-empty field of 1.1.2's form, as text.
        $saved = ['name' => '`&Probe Attack', 'roundmsg' => 'Your probe fights!', 'wearoff' => 'The probe tires.', 'effectmsg' => 'Hit for {damage}.',
            'effectnodmgmsg' => 'Missed.', 'effectfailmsg' => 'Failed.', 'rounds' => '15', 'atkmod' => '1.3', 'defmod' => '0.9', 'invulnerable' => '1',
            'regen' => '2', 'minioncount' => '3', 'minbadguydamage' => '1', 'maxbadguydamage' => '4', 'mingoodguydamage' => '0', 'maxgoodguydamage' => '0',
            'lifetap' => '.5', 'damageshield' => '0.25', 'badguydmgmod' => '0.8', 'badguyatkmod' => '0.75', 'badguydefmod' => '1', 'schema' => 'mounts'];
        $buff = BuffState::mount($saved, self::PLAYER);
        self::assertSame([15, 1.3, 0.9, 1, 2, 3, 1, 4, 0, 0, 0.5, 0.25, 0.8, 0.75, 1],
            array_map(static fn ($key) => $buff[$key], ['rounds', 'atkmod', 'defmod', 'invulnerable', 'regen', 'minioncount', 'minbadguydamage',
                'maxbadguydamage', 'mingoodguydamage', 'maxgoodguydamage', 'lifetap', 'damageshield', 'badguydmgmod', 'badguyatkmod', 'badguydefmod']));
        self::assertSame(array_keys($saved), array_keys($buff));
        // A blank name is left out by the form; the character stats read every buff's name.
        self::assertSame('', BuffState::mount(['rounds' => '5', 'atkmod' => '1.1'], [])['name']);
    }

    public function testModuleAndCoreBuffsPass(): void
    {
        $shapes = [
            // modules/lovers.php lovers_getbuff(), applied by lovers_violet.php and lovers_seth.php.
            'lover' => ["name" => "`!Lover's Protection", 'rounds' => 60, 'wearoff' => ['`!You miss %s`!.`0', '`%Violet`0'], 'defmod' => 1.2,
                'roundmsg' => 'Your lover inspires you to keep safe!', 'schema' => 'module-lovers'],
            // modules/crazyaudrey.php.
            'crazyaudrey' => ['name' => 'Warm Fuzzies', 'rounds' => 5, 'activate' => 'defense', 'defmod' => 1.05, 'schema' => 'module-crazyaudrey'],
            // modules/raceelf.php and modules/racetroll.php on New Day: the shipped expressions.
            'racialbenefit' => ['name' => '`@Elvish Awareness`0', 'defmod' => '(<defense>?(1+((1+floor(<level>/5))/<defense>)):0)',
                'allowinpvp' => 1, 'allowintrain' => 1, 'rounds' => -1, 'schema' => 'module-raceelf'],
            // lib/battle-skills.php: the PvP bodyguard and the superuser god mode.
            'bodyguard' => ['startmsg' => "`\${badguy}'s bodyguard protects them!", 'name' => '`&Bodyguard', 'wearoff' => 'The bodyguard seems to have fallen asleep.',
                'badguyatkmod' => 1.4, 'defmod' => 0.6, 'rounds' => -1, 'allowinpvp' => 1, 'expireafterfight' => 1, 'schema' => 'pvp'],
            'godmode' => ['name' => '`&GOD MODE', 'rounds' => 1, 'wearoff' => 'You feel mortal again.', 'atkmod' => 25, 'defmod' => 25, 'invulnerable' => 1,
                'startmsg' => '`&`bYou feel godlike.`b', 'schema' => 'skill'],
            // New Day carries a buff marked to survive it; the battle marks use, suspension and calculation.
            'carried' => ['name' => 'carried', 'rounds' => 2, 'defmod' => 1.3, 'survivenewday' => 1, 'newdaymessage' => 'Carried once', 'schema' => 'test',
                'used' => 0, 'suspended' => 1, 'fields_calculated' => true, 'tempstats_calculated' => true],
        ];
        self::assertSame($shapes, BuffState::collection($shapes, self::PLAYER));
        $troll = ['name' => '`@Trollish Strength`0', 'atkmod' => '(<attack>?(1+((1+floor(<level>/5))/<attack>)):0)', 'allowinpvp' => 1,
            'allowintrain' => 1, 'rounds' => -1, 'schema' => 'module-racetroll'];
        self::assertSame($troll, BuffState::buff('racialbenefit', $troll, ['attack' => 0, 'level' => 1]));
        // The battle's calculated value of an expression is a number.
        self::assertSame(1.125, BuffState::buff('racialbenefit', ['defmod' => 1.125] + $shapes['racialbenefit'], self::PLAYER)['defmod']);
    }

    public function testDrinksAsStoredByTheDrinksTablePass(): void
    {
        // modules/drinks/security.php builds the buzz from the drinks row (modules/drinks/install.php); PDO returns text.
        $ale = ['name' => '`#Buzz', 'rounds' => '10', 'roundmsg' => "You've got a nice buzz going.", 'wearoff' => 'Your buzz fades.', 'atkmod' => '1.25', 'schema' => 'module-drinks'];
        $hot = ['name' => '`$Hot Hands', 'rounds' => '12', 'roundmsg' => 'You feel like your hands are about to burn off.',
            'wearoff' => 'Finally, your hands are no longer burning.', 'atkmod' => '1.1', 'defmod' => '.9', 'dmgmod' => '1.5', 'schema' => 'module-drinks'];
        $mule = ['name' => '`#Mulekick', 'rounds' => '15', 'roundmsg' => 'You hear a donkey braying in the distance', 'wearoff' => 'That donkey finally shuts up.',
            'dmgmod' => '1.3', 'damageshield' => '1.3', 'effectfailmsg' => 'Your head rings as the donkey kicks you instead.',
            'effectnodmgmsg' => 'That mule would have kicked {badguy} to the moon, but it missed!',
            'effectmsg' => '{badguy} sees`$ {damage}`) stars as the mule kicks him over the moon.', 'schema' => 'module-drinks'];
        self::assertSame(array_replace($ale, ['rounds' => 10, 'atkmod' => 1.25]), BuffState::buff('buzz', $ale, []));
        self::assertSame(array_replace($hot, ['rounds' => 12, 'atkmod' => 1.1, 'defmod' => 0.9, 'dmgmod' => 1.5]), BuffState::buff('buzz', $hot, []));
        self::assertSame(array_replace($mule, ['rounds' => 15, 'dmgmod' => 1.3, 'damageshield' => 1.3]), BuffState::buff('buzz', $mule, []));
    }

    public function testProducerExactBuffsKeepTheirSchemas(): void
    {
        $siphon = ['name' => '`%Siphon Life', 'rounds' => 4, 'wearoff' => "Your weapon's aura fades.", 'lifetap' => 1,
            'effectmsg' => 'You are healed for {damage} health.', 'effectnodmgmsg' => 'You feel a tingle as your weapon tries to heal your already healthy body.',
            'effectfailmsg' => 'Your weapon wails as you deal no damage to your opponent.', 'schema' => 'module-specialtymysticpower', 'used' => 1];
        $potion = TransmutationState::create(3, .75, .75, 1);
        $stored = array_replace($potion, ['rounds' => '3', 'atkmod' => '0.75']);
        self::assertSame(['mp3' => $siphon, 'transmute' => $potion], BuffState::collection(['mp3' => $siphon, 'transmute' => $stored], []));
        foreach ([['mp3' => ['lifetap' => 2] + $siphon], ['mp3' => ['rounds' => '4'] + $siphon], ['transmute' => ['name' => 'x'] + $potion]] as $bad) {
            $this->reject($bad);
        }
    }

    public function testEmptyStoredListIsNoBuffs(): void
    {
        self::assertSame([], BuffState::read('', []));
        self::assertSame([], BuffState::read(null, []));
        self::assertSame([], BuffState::read('a:0:{}', []));
        self::assertSame('a:0:{}', BuffState::encode([], []));
    }

    public function testMalformedBuffsFail(): void
    {
        $base = ['name' => 'x', 'rounds' => 3, 'schema' => 'test'];
        $cases = [];
        foreach ([['evil' => '1'], ['activate' => 'always'], ['activate' => ['offense']], ['rounds' => 0], ['rounds' => -2], ['rounds' => 100001], ['rounds' => '1.5'],
            ['rounds' => 'abc'], ['rounds' => ' 5'], ['rounds' => 1.0], ['rounds' => true], ['rounds' => null], ['atkmod' => '1e2'], ['atkmod' => 'abc'], ['atkmod' => ''],
            ['atkmod' => 'INF'], ['atkmod' => INF], ['atkmod' => NAN], ['atkmod' => -0.5], ['atkmod' => '-1'], ['atkmod' => true], ['atkmod' => []], ['atkmod' => null],
            ['atkmod' => '<charm>'], ['atkmod' => '(<charm>?1:0)'], ['badguydmgmod' => 2147483648], ['regen' => '-2147483648'], ['minioncount' => '1.5'],
            ['minioncount' => 1001], ['minioncount' => -1], ['minioncount' => 1.5], ['minioncount' => '1e1'], ['aura' => 'yes'], ['aura' => 2], ['aura' => []],
            ['used' => null], ['name' => str_repeat('x', 4097)], ['name' => 5], ['name' => ['x']], ['schema' => null], ['roundmsg' => ['`!You miss %s`!.`0', 'x']],
            ['wearoff' => ['`!You miss %s`!.`0', 'x']], ['tempstat-attack' => 'x']] as $change) {
            $cases[] = ['fixture' => array_replace($base, $change)];
        }
        $cases[] = ['fixture' => ['name' => 'x', 'schema' => 'test']];
        $lover = ['name' => "`!Lover's Protection", 'rounds' => 60, 'defmod' => 1.2, 'schema' => 'module-lovers'];
        foreach ([[], ['wrong', 'Violet'], ['`!You miss %s`!.`0'], ['`!You miss %s`!.`0', []], ['`!You miss %s`!.`0', 'Violet', 'extra'], [1 => '`!You miss %s`!.`0', 2 => 'x']] as $message) {
            $cases[] = ['lover' => $lover + ['wearoff' => $message]];
        }
        $cases[] = [5 => $base]; $cases[] = ['' => $base]; $cases[] = [str_repeat('k', 129) => $base]; $cases[] = ['x' => 'buff']; $cases[] = ['x' => null];
        $cases[] = array_fill_keys(array_map(static fn ($n) => "b$n", range(1, 101)), $base);
        foreach ($cases as $buffs) $this->reject($buffs);
        foreach (['garbage', 'b:0;', 'i:5;', 's:3:"abc";', 'N;', 'O:8:"stdClass":0:{}', 'a:1:{s:1:"x";O:8:"stdClass":0:{}}', serialize(['x' => 2])] as $stored) {
            try { BuffState::read($stored, []); self::fail('Accepted ' . $stored); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        foreach ([false, 'x', 5] as $value) {
            try { BuffState::collection($value, []); self::fail('Accepted a non-list.'); }
            catch (\DomainException) { self::assertTrue(true); }
            try { BuffState::mount($value, []); self::fail('Accepted a non-buff.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
        // The column holds 65535 bytes.
        $this->expectException(\DomainException::class);
        BuffState::encode(array_fill_keys(array_map(static fn ($n) => "b$n", range(1, 20)), ['name' => str_repeat('x', 4000), 'rounds' => 1]), []);
    }

    /** @param array<mixed> $buffs */
    private function reject(array $buffs): void
    {
        try { BuffState::collection($buffs, self::PLAYER); self::fail('Accepted ' . json_encode($buffs, JSON_PARTIAL_OUTPUT_ON_ERROR)); }
        catch (\DomainException) { self::assertTrue(true); }
    }
}
