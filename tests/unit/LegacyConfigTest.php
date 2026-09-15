<?php

declare(strict_types=1);

namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

final class LegacyConfigTest extends TestCase
{
    public function testRoundTripEscapesQuotesBackslashesAndPhpText(): void
    {
        $values = [
            'DB_HOST' => 'localhost', 'DB_USER' => "test'user",
            'DB_PASS' => "FAKE-only-'\\\n\$notCode", 'DB_NAME' => 'test_db',
            'DB_PREFIX' => 'test_', 'DB_USEDATACACHE' => 1,
            'DB_DATACACHEPATH' => "/tmp/a'quoted\\cache/\n<?php print('not code');",
        ];
        $file = tempnam(sys_get_temp_dir(), 'resurrection-config-');
        try {
            file_put_contents($file, LegacyConfig::render($values));
            $loaded = (static function (string $path): array {
                require $path;
                return compact('DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PREFIX',
                    'DB_USEDATACACHE', 'DB_DATACACHEPATH');
            })($file);
            self::assertSame($values, $loaded);
            self::assertStringNotContainsString('?>', LegacyConfig::render($values));
        } finally {
            unlink($file);
        }
    }

    public function testMissingFieldFailsWithoutIncludingSecretValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing configuration field: DB_HOST');
        LegacyConfig::render(['DB_PASS' => 'FAKE-do-not-display']);
    }
}
