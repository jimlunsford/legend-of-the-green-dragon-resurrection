<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\SecurityHeaders;

/**
 * Phase 6 task 3 (S1): the security headers' one place (src/Http/SecurityHeaders.php) and the csp_mode setting. The
 * pages that send them, in each mode, are checked over HTTP in tests/tooling/test_hardening_http.py.
 */
final class SecurityHeadersTest extends TestCase
{
    private const string POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
    private const array ALWAYS = ['X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'same-origin'];

    public function testEachModeSendsThePolicyItSaysAndTheOtherThreeAlways(): void
    {
        self::assertSame(self::POLICY, SecurityHeaders::POLICY);
        self::assertSame(self::ALWAYS + ['Content-Security-Policy' => self::POLICY], SecurityHeaders::headers('enforce'));
        self::assertSame(self::ALWAYS + ['Content-Security-Policy-Report-Only' => self::POLICY], SecurityHeaders::headers('report-only'));
        self::assertSame(self::ALWAYS, SecurityHeaders::headers('off'));
        // The user editor's last hit is sandboxed whatever the mode.
        self::assertSame(self::ALWAYS + ['Content-Security-Policy' => self::POLICY . '; sandbox'], SecurityHeaders::headers('enforce', true));
        self::assertSame(self::ALWAYS + ['Content-Security-Policy' => 'sandbox', 'Content-Security-Policy-Report-Only' => self::POLICY], SecurityHeaders::headers('report-only', true));
        self::assertSame(self::ALWAYS + ['Content-Security-Policy' => 'sandbox'], SecurityHeaders::headers('off', true));
    }

    public function testAnUnknownModeIsEnforce(): void
    {
        foreach (['enforce', 'report-only', 'off'] as $mode) self::assertSame($mode, SecurityHeaders::mode($mode));
        foreach ([null, false, '', 'OFF', 'report_only', ' off', 'disabled', 1, ['off']] as $value) {
            self::assertSame('enforce', SecurityHeaders::mode($value), var_export($value, true));
            self::assertSame(self::ALWAYS + ['Content-Security-Policy' => self::POLICY], SecurityHeaders::headers(is_string($value) ? $value : 'x'));
        }
    }

    public function testTheSettingIsDeclaredWithItsHelpAndInstalledAsEnforce(): void
    {
        $root = dirname(__DIR__, 2);
        $descriptors = (string)file_get_contents($root . '/lib/configuration_descriptors.php');
        self::assertStringContainsString('"csp_mode"=>"Content Security Policy,enum,enforce,Enforce (the default),report-only,Report only,off,Off",', $descriptors);
        self::assertSame(1, preg_match('/"(Every page sends a Content Security Policy[^"]*),note",/', $descriptors, $note));
        self::assertStringNotContainsString(',', $note[1], 'A note is split at commas');
        foreach (['third-party module', 'Content-Security-Policy-Report-Only', 'Off sends no policy', 'whatever this says'] as $text) {
            self::assertStringContainsString($text, $note[1]);
        }
        self::assertStringContainsString('"csp_mode"=>"enforce",', (string)file_get_contents($root . '/lib/installer/installer_default_settings.php'));
        $schema = \Resurrection\Http\SettingDescriptor::declare(['csp_mode' => 'Content Security Policy,enum,enforce,Enforce (the default),report-only,Report only,off,Off']);
        self::assertSame(['enforce' => 'Enforce (the default)', 'report-only' => 'Report only', 'off' => 'Off'], $schema['csp_mode']['options']);
        self::assertSame('enforce', $schema['csp_mode']['default']);
    }
}
