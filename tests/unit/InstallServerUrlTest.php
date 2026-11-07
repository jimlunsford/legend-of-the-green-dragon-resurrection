<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 6: the server URL a fresh install stores (lib/installer/installer_default_settings.php). It follows the
 * install request's scheme and leaves out the scheme's default port. 1.1.2 always wrote http:// and kept any port but
 * 80, so a setup.php install over HTTPS stored http://host:443/ (found in the host guide's nginx check).
 */
final class InstallServerUrlTest extends TestCase
{
    /** @param array<string, string> $server */
    private static function stored(array $server): string
    {
        $saved = $_SERVER;
        try {
            foreach (['HTTPS', 'SERVER_NAME', 'SERVER_PORT', 'REQUEST_URI'] as $name) unset($_SERVER[$name]);
            $_SERVER = $server + $_SERVER;
            $defaults = require 'lib/installer/installer_default_settings.php';
            return $defaults['serverurl'];
        } finally {
            $_SERVER = $saved;
        }
    }

    public function testAnHttpsInstallStoresHttpsWithoutTheDefaultPort(): void
    {
        self::assertSame('https://game.example.org/', self::stored(['HTTPS' => 'on', 'SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '443', 'REQUEST_URI' => '/setup.php']));
        self::assertSame('https://game.example.org/', self::stored(['HTTPS' => '1', 'SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '443', 'REQUEST_URI' => '/setup.php']));
        self::assertSame('https://game.example.org:8443/game', self::stored(['HTTPS' => 'on', 'SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '8443', 'REQUEST_URI' => '/game/setup.php']));
    }

    public function testPlainHttpAndTheCommandLineAreAsBefore(): void
    {
        self::assertSame('http://game.example.org/', self::stored(['SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/setup.php']));
        self::assertSame('http://game.example.org/', self::stored(['HTTPS' => 'off', 'SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/setup.php']));
        self::assertSame('http://127.0.0.1:8080/game', self::stored(['SERVER_NAME' => '127.0.0.1', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/game/setup.php?step=1']));
        // scripts/install.php: no request at all.
        self::assertSame('http://localhost/', self::stored([]));
    }

    public function testTheStoredValueIsAValidServerUrl(): void
    {
        require_once 'lib/server_url.php';
        foreach ([['HTTPS' => 'on', 'SERVER_NAME' => 'game.example.org', 'SERVER_PORT' => '443', 'REQUEST_URI' => '/setup.php'], []] as $server) {
            $url = self::stored($server);
            self::assertSame(str_ends_with($url, '/') ? $url : $url . '/', resurrection_valid_server_url($url));
        }
    }
}
