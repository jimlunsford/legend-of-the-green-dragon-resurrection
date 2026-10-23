<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserEditorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once 'lib/constants.php';
        // The name helpers load the database wrapper, which installs the application's error handler once.
        $installs = !in_array(realpath('lib/errorhandling.php'), get_included_files(), true);
        require_once 'lib/user_editor.php';
        if ($installs) restore_error_handler();
    }

    /** The 1.1.2 user_save.php superuser arithmetic, transcribed. */
    private static function historical(array $posted, int $old, int $editor): int
    {
        $value = 0;
        foreach ($posted as $k => $v) if ($v) $value += (int)$k;
        $stripfield = ((int)$old | $editor | SU_ANYONE_CAN_SET | ($editor & SU_MEGAUSER ? 0xFFFFFFFF : 0));
        $value = $value & $stripfield;
        $unremovable = ~ ((int)$editor | SU_ANYONE_CAN_SET | ($editor & SU_MEGAUSER ? 0xFFFFFFFF : 0));
        $filteredunremovable = (int)$old & $unremovable;
        return $value | $filteredunremovable;
    }

    public function testSuperuserRulesMatchHistoricalArithmetic(): void
    {
        mt_srand(20261016);
        $bits = array_map(static fn($i) => 1 << $i, range(0, 31));
        for ($case = 0; $case < 3000; $case++) {
            $old = mt_rand(0, 0x7FFFFFFF) & mt_rand(0, 0x7FFFFFFF);
            $editor = (mt_rand(0, 0x7FFFFFFF) & mt_rand(0, 0x7FFFFFFF) & ~SU_MEGAUSER) | ($case % 5 === 0 ? SU_MEGAUSER : 0);
            $posted = ['0' => '1'];
            foreach ($bits as $bit) if (mt_rand(0, 2) === 0) $posted[(string)$bit] = '1';
            $requested = resurrection_user_editor_bits(array_combine(array_map('intval', array_keys($posted)), $posted));
            self::assertSame(self::historical($posted, $old, $editor), resurrection_user_editor_superuser($requested, $old, $editor));
        }
        $edit = SU_EDIT_USERS;
        // An editor cannot grant a flag they lack, but can grant their own and the flags anyone can set.
        self::assertSame(0, resurrection_user_editor_superuser(SU_EDIT_CONFIG, 0, $edit));
        self::assertSame(SU_EDIT_USERS | SU_DEBUG_OUTPUT, resurrection_user_editor_superuser(SU_EDIT_USERS | SU_DEBUG_OUTPUT | SU_RAW_SQL, 0, $edit));
        // An editor cannot remove a flag they cannot set; they can remove their own.
        self::assertSame(SU_EDIT_CONFIG, resurrection_user_editor_superuser(0, SU_EDIT_CONFIG | SU_EDIT_USERS, $edit));
        // A flag the account already holds stays when the editor keeps it checked (it is disabled, so restored).
        self::assertSame(SU_EDIT_CONFIG | SU_EDIT_USERS, resurrection_user_editor_superuser(SU_EDIT_USERS, SU_EDIT_CONFIG | SU_EDIT_USERS, $edit));
        // Only a megauser sets and removes everything.
        self::assertSame(0xFFFFFFFF, resurrection_user_editor_superuser(0xFFFFFFFF, 0, SU_MEGAUSER));
        self::assertSame(0, resurrection_user_editor_superuser(0, 0xFFFFFFFF, SU_MEGAUSER));
        self::assertSame(SU_RAW_SQL, resurrection_user_editor_superuser(0, SU_RAW_SQL, $edit | SU_EDIT_CONFIG));
    }

    public function testPrivilegeCheckboxesAreSingleBitsCheckedOnce(): void
    {
        self::assertSame(SU_EDIT_USERS | SU_MEGAUSER, resurrection_user_editor_bits([0 => '1', 64 => '1', 1 => '1']));
        self::assertSame(0x80000000, resurrection_user_editor_bits([0x80000000 => '1']));
        foreach (['64', [3 => '1'], [-1 => '1'], [64 => '2'], [64 => ['1']], ['x' => '1'], [0x100000000 => '1']] as $value) {
            try { resurrection_user_editor_bits($value); self::fail('Accepted ' . json_encode($value)); }
            catch (InvalidArgumentException $error) { self::assertNotSame('', $error->getMessage()); }
        }
    }

    public function testFieldsValidateAgainstDescriptorAndColumn(): void
    {
        $schema = resurrection_user_editor_schema([
            'Account info,title', 'acctid' => 'User id,viewonly', 'login' => 'Login', 'newpassword' => 'New Password,password',
            'locked' => 'Account Locked,bool', 'sex' => 'Sex,enum,0,Male,1,Female', 'gold' => 'Gold in hand,int',
            'deathpower' => ['Favor with %s`0,int', 'Ramius'], 'title' => 'Dragonkill Title,hidden', 'superuser' => 'Flags,bitfield,96,64,Edit Users',
        ]);
        self::assertSame(['login', 'newpassword', 'locked', 'sex', 'gold', 'deathpower', 'title', 'superuser'], array_keys($schema));
        self::assertSame(['0', '1'], $schema['sex']['options']);
        self::assertSame(96, $schema['superuser']['mask']);
        $int = ['type' => 'int', 'unsigned' => true, 'length' => null, 'nullable' => false];
        $signed = ['type' => 'int', 'unsigned' => false, 'length' => null, 'nullable' => false];
        $tiny = ['type' => 'tinyint', 'unsigned' => true, 'length' => null, 'nullable' => false];
        $text = ['type' => 'varchar', 'unsigned' => false, 'length' => 5, 'nullable' => false];
        $date = ['type' => 'datetime', 'unsigned' => false, 'length' => null, 'nullable' => true];
        $accepted = [
            [$schema['gold'], $int, '4294967295', 4294967295], [$schema['gold'], $int, '0', 0], [$schema['gold'], $signed, '-2147483648', -2147483648],
            [$schema['locked'], $tiny, '1', 1], [$schema['sex'], $tiny, '0', 0], [$schema['login'], $text, '雪雪雪雪雪', '雪雪雪雪雪'],
            [$schema['login'], $date, '', null], [$schema['login'], $date, '2026-10-16 12:00:00', '2026-10-16 12:00:00'],
            [$schema['newpassword'], null, '', ''], [$schema['newpassword'], null, 'twelve bytes', 'twelve bytes'],
        ];
        foreach ($accepted as [$field, $column, $value, $stored]) self::assertSame($stored, resurrection_user_editor_value($field, $column, $value));
        $refused = [
            [$schema['gold'], $int, 'ten'], [$schema['gold'], $int, '4294967296'], [$schema['gold'], $int, '-1'], [$schema['gold'], $int, '1.5'],
            [$schema['gold'], $int, ' 5'], [$schema['gold'], $int, '007'], [$schema['gold'], $signed, '2147483648'], [$schema['gold'], $int, ''],
            [$schema['locked'], $tiny, '2'], [$schema['locked'], $tiny, 'yes'], [$schema['sex'], $tiny, '2'], [$schema['sex'], $tiny, '1 '],
            [$schema['login'], $text, '雪雪雪雪雪雪'], [$schema['login'], $text, "a\0"], [$schema['login'], $text, "\xe9"], [$schema['login'], $text, ['x']],
            [$schema['login'], $date, '2026-02-30 00:00:00'], [$schema['login'], $date, 'tomorrow'], [$schema['login'], null, 'x'],
            [$schema['newpassword'], null, 'short'], [$schema['newpassword'], null, str_repeat('x', 73)],
        ];
        foreach ($refused as [$field, $column, $value]) {
            try { resurrection_user_editor_value($field, $column, $value); self::fail('Accepted ' . json_encode($value)); }
            catch (InvalidArgumentException $error) { self::assertNotSame('', $error->getMessage()); }
        }
    }

    public function testDisplayedValuesFollowTheFormAndPetitionIdsArePositiveIntegers(): void
    {
        $schema = resurrection_user_editor_schema(['name' => 'Name', 'locked' => 'Locked,bool', 'banoverride' => 'Override,bool',
            'sex' => 'Sex,enum,0,Male,1,Female', 'clanrank' => 'Rank,enum,0,Applicant,10,Member', 'newpassword' => 'Pw,password', 'superuser' => 'Flags,bitfield,0']);
        $row = ['name' => '`2Farmboy `&Jim`0', 'title' => '`2Farmboy', 'ctitle' => '', 'locked' => '2', 'banoverride' => null, 'sex' => '1',
            'clanrank' => '7', 'superuser' => '65'];
        self::assertSame(['name' => '`&Jim', 'locked' => '0', 'banoverride' => '0', 'sex' => '1', 'clanrank' => '0', 'newpassword' => '', 'superuser' => 65],
            resurrection_user_editor_displayed($schema, $row));
        foreach (['1' => '1', '4294967295' => '4294967295', '0' => '', '-1' => '', '01' => '', '4294967296' => '', "1'><b>" => '', '1&op=del' => ''] as $value => $kept) {
            self::assertSame($kept, resurrection_user_editor_petition((string)$value));
        }
        self::assertSame('', resurrection_user_editor_petition(['1']));
        self::assertSame('', resurrection_user_editor_petition(false));
    }
}
