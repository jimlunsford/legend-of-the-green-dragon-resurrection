<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\SettingDescriptor;

/**
 * Settings the game reads only inside a range (Phase 6 task 8): LOGINTIMEOUT is held to 300..86400 seconds whatever
 * the settings table stores, and Game Settings declares the same bounds, so a value outside them is refused with one
 * line saying why. docs/PHASE6-TASK8-20261112.md.
 */
final class BoundedSettingsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../lib/settings.php';
        require_once __DIR__ . '/../../src/Http/SettingDescriptor.php';
    }

    public function testLoginTimeoutIsReadInsideItsBoundsWhateverTheRowHolds(): void
    {
        global $settings;
        $saved = $settings ?? null;
        try {
            $cases = ['0' => 300, '1' => 300, '-900' => 300, '299' => 300, '300' => 300, '900' => 900, '86400' => 86400,
                '86401' => 86400, '999999999' => 86400, '' => 300, 'never' => 300];
            foreach ($cases as $stored => $read) {
                $settings = ['LOGINTIMEOUT' => (string)$stored, 'maxonline' => '0'];
                self::assertSame($read, getsetting('LOGINTIMEOUT', 900), 'stored ' . var_export((string)$stored, true));
            }
            self::assertSame('0', getsetting('maxonline', 10), 'other settings read as before');
        } finally {
            $settings = $saved;
        }
        // Phase 6 task 13a adds account_mail_hourly (10 to 1000 mails an hour); LOGINTIMEOUT's range is unchanged.
        self::assertSame(['LOGINTIMEOUT' => [300, 86400], 'account_mail_hourly' => [10, 1000]], resurrection_bounded_settings());
        self::assertSame('abc', resurrection_bounded_setting('maxonline', 'abc'));
    }

    public function testABoundedDeclarationRefusesAValueOutsideItWithOneLine(): void
    {
        $schema = SettingDescriptor::declare(['LOGINTIMEOUT' => 'Seconds of inactivity before auto-logoff,int,300,86400',
            'motditems' => 'How many items should be shown on the motdlist,int']);
        self::assertSame([300, 86400, true], [$schema['LOGINTIMEOUT']['min'], $schema['LOGINTIMEOUT']['max'], $schema['LOGINTIMEOUT']['bounded']]);
        self::assertArrayNotHasKey('bounded', $schema['motditems'], 'an int without bounds is as before');
        foreach (['0', '299', '86401', '-1'] as $value) {
            try {
                SettingDescriptor::patch($schema, ['LOGINTIMEOUT' => $value]);
                self::fail('Accepted ' . $value);
            } catch (\InvalidArgumentException $error) {
                self::assertSame(SettingDescriptor::OUT_OF_BOUNDS, $error->getCode());
                self::assertSame('Not saved: "Seconds of inactivity before auto-logoff" must be a whole number from 300 to 86400.', $error->getMessage());
            }
        }
        foreach (['abc', '9.5', ''] as $value) {
            try {
                SettingDescriptor::patch($schema, ['LOGINTIMEOUT' => $value]);
                self::fail('Accepted ' . $value);
            } catch (\InvalidArgumentException $error) {
                self::assertNotSame(SettingDescriptor::OUT_OF_BOUNDS, $error->getCode(), 'not a number is refused as before');
            }
        }
        self::assertSame(['LOGINTIMEOUT' => '300', 'motditems' => '0'], SettingDescriptor::patch($schema, ['LOGINTIMEOUT' => '300', 'motditems' => '0']));
        self::assertSame(['LOGINTIMEOUT' => '86400'], SettingDescriptor::patch($schema, ['LOGINTIMEOUT' => '86400']));
        foreach (['x,int,9,1', 'x,int,a,5'] as $declaration) {
            try {
                SettingDescriptor::declare(['x' => $declaration]);
                self::fail('Accepted bounds ' . $declaration);
            } catch (\DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testGameSettingsDeclaresLoginTimeoutWithTheGamesBounds(): void
    {
        $source = (string)file_get_contents(__DIR__ . '/../../lib/configuration_descriptors.php');
        self::assertStringContainsString('"LOGINTIMEOUT"=>"Seconds of inactivity before auto-logoff,int,".implode(\',\', resurrection_bounded_settings()[\'LOGINTIMEOUT\']),', $source);
        self::assertSame(1, substr_count($source, '"LOGINTIMEOUT"=>'));
    }
}
