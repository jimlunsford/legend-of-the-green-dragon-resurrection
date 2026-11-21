<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The web setup page's PHP version check (Phase 6 task 32, F4; lib/setup/page.php, resurrection_setup_preflight()). It
 * said "8.4 or 8.5 needed" and passed every PHP from 8.4 up, 8.6 too. Now 8.4 and 8.5 are ok, as the page showed them
 * before; an older PHP fails, as before; a newer one is a warning ("untested"), and setup may continue, since only a
 * failed check stops it. The version is the preflight's last parameter, PHP_VERSION unless a test passes another.
 */
final class SetupPreflightTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/setup/page.php';
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function versions(): array
    {
        return [
            '8.3 fails' => ['8.3.30', 'fail', '8.3.30 (8.4 or 8.5 needed)'],
            '8.4 is ok' => ['8.4.28', 'ok', '8.4.28 (8.4 or 8.5 needed)'],
            '8.4.0 is ok' => ['8.4.0', 'ok', '8.4.0 (8.4 or 8.5 needed)'],
            '8.5 is ok' => ['8.5.13', 'ok', '8.5.13 (8.4 or 8.5 needed)'],
            '8.6 is a warning' => ['8.6.0', 'warning', '8.6.0 (untested; Resurrection supports PHP 8.4 and 8.5)'],
            '9.0 is a warning' => ['9.0.1', 'warning', '9.0.1 (untested; Resurrection supports PHP 8.4 and 8.5)'],
        ];
    }

    #[DataProvider('versions')]
    public function testThePhpVersionCheck(string $version, string $state, string $value): void
    {
        $check = resurrection_setup_preflight('install', '/tmp/rz-preflight/dbconnect.php', '30', 'yes', $version)[0];
        self::assertSame(['label' => 'PHP version', 'value' => $value, 'state' => $state], $check);
    }

    public function testOnlyAnOlderPhpStopsSetup(): void
    {
        // The preflight screen offers Continue unless a check fails (resurrection_setup_screen_preflight()).
        foreach (['8.3.30' => true, '8.4.28' => false, '8.5.13' => false, '8.6.0' => false] as $version => $stops) {
            $checks = resurrection_setup_preflight('upgrade', '/tmp/rz-preflight/dbconnect.php', '0', 'yes', $version);
            $failed = array_filter($checks, static fn (array $check): bool => $check['state'] === 'fail' && $check['label'] === 'PHP version');
            self::assertSame($stops, $failed !== [], $version);
        }
    }

    public function testTheWarningAsThePageShowsIt(): void
    {
        $html = resurrection_setup_checks_html([resurrection_setup_preflight('install', '/tmp/rz-preflight/dbconnect.php', '30', 'yes', '8.6.0')[0]]);
        self::assertSame('<dl class="rz-setup-checks"><dt>PHP version</dt><dd class="rz-setup-check-warning"><span class="rz-setup-check-word">Check</span>'
            . ' 8.6.0 (untested; Resurrection supports PHP 8.4 and 8.5)</dd></dl>', $html);
    }

    public function testThisPhpShowsAsBefore(): void
    {
        // CI runs PHP 8.4 and 8.5: the page shows them exactly as it did before this check changed.
        $check = resurrection_setup_preflight('install', '/tmp/rz-preflight/dbconnect.php', '30', 'yes')[0];
        self::assertSame(['label' => 'PHP version', 'value' => PHP_VERSION . ' (8.4 or 8.5 needed)', 'state' => 'ok'], $check);
    }
}
