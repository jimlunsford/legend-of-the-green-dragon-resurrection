<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/** The "View PHP Source" link's URL (lib/source_link.php): http or https only, never markup. */
final class SourceLinkTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once 'lib/source_link.php';
    }

    public function testAcceptsHttpAndHttpsUrlsUnchanged(): void
    {
        foreach ([RESURRECTION_SOURCE_URL, 'http://localhost/', 'https://Git.Example.org/logd', 'http://127.0.0.1:8080/src/',
            'https://[::1]:8443/x', 'https://fork.example.org/my-logd/tree/main?ref=a&tab=b#readme', 'https://example.org?x=1',
            'https://example.org#src', 'https://example.org/a%20b/~c+d(e)!$*,;=:@'] as $url) {
            self::assertSame($url, resurrection_valid_source_url($url), $url);
        }
    }

    public function testRefusesOtherSchemesAndUnsafeText(): void
    {
        foreach (['', 'javascript:alert(1)', 'JAVASCRIPT:alert(1)//https://x.example', 'ftp://example.org/', 'data:text/html,x',
            '//example.org/src', 'example.org/src', 'https:/example.org/', 'https://', 'https://user:pass@example.org/',
            "https://example.org/x'y", 'https://example.org/x"y', 'https://example.org/<script>', 'https://example.org/a b',
            "https://example.org/\nx", "https://example.org/\x00", 'https://example.org/a\\b', 'https://example.org/`x',
            'https://-bad.example/', 'https://exa_mple.org/', 'https://example.org:0/', 'https://example.org:65536/',
            'https://[nothex]/', 'https://example.org/' . str_repeat('a', 300)] as $url) {
            self::assertNull(resurrection_valid_source_url($url), json_encode($url, JSON_THROW_ON_ERROR));
        }
    }

    public function testTheDefaultIsThePublicRepository(): void
    {
        self::assertSame('https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection', RESURRECTION_SOURCE_URL);
        $defaults = require 'lib/installer/installer_default_settings.php';
        self::assertSame(RESURRECTION_SOURCE_URL, $defaults['sourceurl']);
    }
}
