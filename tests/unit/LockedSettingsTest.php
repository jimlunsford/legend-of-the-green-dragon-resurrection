<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\SettingDescriptor;

/**
 * Settings the game holds at one value (Phase 6 task 7, S11 interim): requirevalidemail is off whatever the settings
 * table stores, and the settings editor never declares a locked setting, so a form that sends one is refused.
 * docs/PHASE6-TASK7-RC-MATERIALS-20261107.md.
 */
final class LockedSettingsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../lib/settings.php';
        require_once __DIR__ . '/../../src/Http/SettingDescriptor.php';
    }

    public function testRequireValidEmailIsOffWhateverTheRowHolds(): void
    {
        global $settings;
        $saved = $settings ?? null;
        try {
            foreach (['1', 1, '0', 'yes'] as $stored) {
                $settings = ['requirevalidemail' => $stored, 'allowcreation' => '1'];
                self::assertSame('0', getsetting('requirevalidemail', 1));
                self::assertFalse((bool)getsetting('requirevalidemail', 0));
            }
            self::assertSame('1', getsetting('allowcreation', 0), 'other settings read as before');
        } finally {
            $settings = $saved;
        }
    }

    public function testALockedDeclarationIsNeverASavableSetting(): void
    {
        $schema = SettingDescriptor::declare(['Account Creation,title', 'requireemail' => 'Require an email address,bool',
            'requirevalidemail' => 'Require users to validate their email address,locked']);
        self::assertSame(['requireemail'], array_keys($schema));
        foreach (['1', '0'] as $value) {
            try {
                SettingDescriptor::patch($schema, ['requirevalidemail' => $value]);
                self::fail('A locked setting was accepted: ' . $value);
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame(['requireemail' => '1'], SettingDescriptor::patch($schema, ['requireemail' => '1']));
    }

    public function testGameSettingsDeclaresEachHeldSettingLocked(): void
    {
        // Every setting the game holds is declared locked in Game Settings' layout, and nothing else is.
        $source = (string)file_get_contents(__DIR__ . '/../../lib/configuration_descriptors.php');
        preg_match_all('/"([A-Za-z_]+)"=>"[^"]*,locked"/', $source, $declared);
        self::assertSame(array_keys(resurrection_locked_settings()), $declared[1]);
        self::assertSame(['requirevalidemail' => '0'], resurrection_locked_settings());
    }
}
