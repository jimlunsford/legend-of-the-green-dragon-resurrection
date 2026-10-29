<?php

declare(strict_types=1);

namespace Resurrection\Tests;

use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\ConfigFile;
use Resurrection\Installation\InstallRefused;

final class ConfigFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/resurrection-configfile-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/served/sub', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (['/served/sub/link', '/served/sub/config.php', '/served/config.php', '/served/link', '/private.php', '/existing.php', '/dangling'] as $file) {
            if (is_link($this->directory . $file) || file_exists($this->directory . $file)) unlink($this->directory . $file);
        }
        rmdir($this->directory . '/served/sub');
        rmdir($this->directory . '/served');
        rmdir($this->directory);
    }

    private function refused(callable $call, string $message): void
    {
        try {
            $call();
            self::fail('Not refused: ' . $message);
        } catch (InstallRefused $refusal) {
            self::assertSame($message, $refusal->getMessage());
        }
    }

    public function testEnvironmentNeedsTheFourValuesAndAPlainPrefix(): void
    {
        $environment = ['DB_HOST' => '127.0.0.1:3306', 'DB_USER' => "user'", 'DB_PASS' => "FAKE-pass'\\", 'DB_NAME' => 'game', 'UNRELATED' => 'x'];
        self::assertSame(['DB_HOST' => '127.0.0.1:3306', 'DB_USER' => "user'", 'DB_PASS' => "FAKE-pass'\\", 'DB_NAME' => 'game',
            'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => ''], ConfigFile::fromEnvironment($environment));
        self::assertSame('lotgd_', ConfigFile::fromEnvironment($environment + ['DB_PREFIX' => 'lotgd_'])['DB_PREFIX']);
        self::assertSame('', ConfigFile::fromEnvironment(['DB_PASS' => ''] + $environment)['DB_PASS']);
        foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $name) {
            $missing = $environment;
            unset($missing[$name]);
            $this->refused(fn() => ConfigFile::fromEnvironment($missing), 'Set DB_HOST, DB_USER, DB_PASS and DB_NAME in the environment to write the configuration file.');
        }
        foreach (['DB_HOST', 'DB_USER', 'DB_NAME'] as $name) {
            $this->refused(fn() => ConfigFile::fromEnvironment([$name => ''] + $environment), 'DB_HOST, DB_USER and DB_NAME must not be empty.');
        }
        foreach (['a-b', 'a b', "a'b", 'é', "a\n"] as $prefix) {
            $this->refused(fn() => ConfigFile::fromEnvironment($environment + ['DB_PREFIX' => $prefix]), 'DB_PREFIX may contain only letters, digits and underscores.');
        }
    }

    public function testTargetMustBeANewAbsolutePathOutsideTheServedDirectory(): void
    {
        $served = $this->directory . '/served';
        self::assertSame($this->directory . '/private.php', ConfigFile::target($this->directory . '/private.php', $served, false));
        self::assertSame($this->directory . '/private.php', ConfigFile::target($served . '/../private.php', $served, false));
        $this->refused(fn() => ConfigFile::target('private.php', $served, false), 'Give the configuration file as an absolute path.');
        $this->refused(fn() => ConfigFile::target($this->directory . '/missing/private.php', $served, false), 'The configuration file\'s directory does not exist.');
        $this->refused(fn() => ConfigFile::target($this->directory . '/..', $served, false), 'The configuration file\'s directory does not exist.');
        file_put_contents($this->directory . '/existing.php', 'kept');
        $this->refused(fn() => ConfigFile::target($this->directory . '/existing.php', $served, false), 'The configuration file already exists. It is never overwritten.');
        symlink($this->directory . '/nowhere', $this->directory . '/dangling');
        $this->refused(fn() => ConfigFile::target($this->directory . '/dangling', $served, false), 'The configuration file already exists. It is never overwritten.');
        $inside = 'The configuration file would be inside the served directory. Choose a private path, or add --allow-served-path to place it there on purpose.';
        $this->refused(fn() => ConfigFile::target($served . '/config.php', $served, false), $inside);
        $this->refused(fn() => ConfigFile::target($served . '/sub/config.php', $served, false), $inside);
        $this->refused(fn() => ConfigFile::target($served . '/sub/../config.php', $served, false), $inside);
        // A link into the served directory is followed to where the file would land.
        symlink($served . '/sub', $this->directory . '/served/link');
        $this->refused(fn() => ConfigFile::target($served . '/link/config.php', $this->directory . '/served/sub', false), $inside);
        self::assertSame($served . '/config.php', ConfigFile::target($served . '/config.php', $served, true));
        // A sibling whose name starts with the served directory's name is outside it.
        self::assertFalse(ConfigFile::inside($this->directory . '/served-other/config.php', $served));
        self::assertTrue(ConfigFile::inside($served . '/config.php', $served));
    }

    public function testWriteIsOwnerOnlyExclusiveAndLoadsBack(): void
    {
        $values = ['DB_HOST' => 'localhost', 'DB_USER' => "o'user", 'DB_PASS' => "FAKE-'\\\n\$notCode", 'DB_NAME' => 'game',
            'DB_PREFIX' => 'p_', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => ''];
        $target = $this->directory . '/private.php';
        $mask = umask(0);
        try {
            ConfigFile::write($target, $values);
        } finally {
            umask($mask);
        }
        clearstatcache();
        self::assertSame(0600, fileperms($target) & 0777);
        $loaded = (static function (string $path): array {
            require $path;
            return compact('DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PREFIX', 'DB_USEDATACACHE', 'DB_DATACACHEPATH');
        })($target);
        self::assertSame($values, $loaded);
        // A file that appeared after the target check is refused, never overwritten.
        $bytes = file_get_contents($target);
        $this->refused(fn() => ConfigFile::write($target, ['DB_PASS' => 'other'] + $values), 'The configuration file could not be created. It is never overwritten.');
        self::assertSame($bytes, file_get_contents($target));
        symlink($this->directory . '/existing.php', $this->directory . '/dangling');
        $this->refused(fn() => ConfigFile::write($this->directory . '/dangling', $values), 'The configuration file could not be created. It is never overwritten.');
        self::assertFileDoesNotExist($this->directory . '/existing.php');
    }
}
