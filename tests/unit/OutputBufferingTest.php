<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The game must not depend on PHP's output buffering (docs/PHASE4-OUTPUT-BUFFERING-FIX-20261029.md). With
 * output_buffering=0, PHP's default without a php.ini and the setting in PHP's official Docker image, anything printed
 * before common.php starts its own buffer is sent at once, and the header() calls after it fail. CI's php.ini sets
 * output_buffering=4096, which hid exactly that: lib/all_tables.php's trailing newline made every page of an installed
 * game "Runtime error in common.php:206".
 */
final class OutputBufferingTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    /**
     * A page of the installed game (FreshInstallTest leaves it installed), rendered by the PHP CLI as a GET of home.php
     * with output_buffering=0. The CLI enforces "headers already sent" like a web server does, so an early byte fails
     * the same way. The run uses a tree of links to the repository with its own dbconnect.php, as the game reads the
     * file from its working directory.
     */
    public function testAnInstalledGamePageRendersWithOutputBufferingOff(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $root = (string)realpath(self::ROOT);
        $work = sys_get_temp_dir() . '/resurrection-output-buffering-' . bin2hex(random_bytes(6));
        $tree = $work . '/tree';
        mkdir($tree . '/.sessions', 0700, true);
        try {
            foreach (scandir($root) ?: [] as $entry) {
                if (in_array($entry, ['.', '..', '.git', 'dbconnect.php'], true)) { continue; }
                symlink($root . '/' . $entry, $tree . '/' . $entry);
            }
            file_put_contents($tree . '/dbconnect.php', LegacyConfig::render(['DB_HOST' => $host,
                'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'),
                'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
            chmod($tree . '/dbconnect.php', 0600);
            file_put_contents($work . '/page.php', "<?php\n" .
                "\$_SERVER = ['SCRIPT_NAME' => '/home.php', 'PHP_SELF' => '/home.php', 'REQUEST_URI' => '/home.php', 'QUERY_STRING' => '',\n" .
                "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
                "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
                "require 'home.php';\n");
            [$code, $out, $err] = $this->process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
                '-d', 'session.save_path=' . $tree . '/.sessions', $work . '/page.php'], $tree);
            self::assertSame(0, $code, $err . substr($out, 0, 2000));
            self::assertSame('', $err);
            self::assertStringNotContainsString('Runtime error', $out);
            // Nothing before the page: it starts with the jade template's own page head ("\r\n" after the
            // <!--!pagehead--> marker, then the doctype). The defect put lib/all_tables.php's "\n" first.
            self::assertStringStartsWith("\r\n<!DOCTYPE", $out);
            self::assertStringContainsString('<title>Legend of the Green Dragon', $out);
            self::assertStringNotContainsString((string)getenv('RESURRECTION_TEST_DB_PASSWORD'), $out);
        } finally {
            $this->remove($work);
        }
    }

    /**
     * Only lib/all_tables.php (1.1.2, kept unchanged) prints anything outside PHP tags, and every loader discards it.
     * A new file that did the same could break pages again under output_buffering=0.
     */
    public function testNoOtherPhpFilePrintsOutsideItsTags(): void
    {
        [$code, $listed] = $this->process(['git', 'ls-files', '-z', '--', '*.php'], (string)realpath(self::ROOT));
        self::assertSame(0, $code);
        $printing = [];
        foreach (array_filter(explode("\0", $listed)) as $file) {
            if (str_starts_with($file, 'tests/')) { continue; }
            foreach (token_get_all((string)file_get_contents(self::ROOT . '/' . $file)) as $token) {
                if (is_array($token) && $token[0] === T_INLINE_HTML) { $printing[] = $file . ':' . $token[2] . ' ' . json_encode($token[1]); }
            }
        }
        self::assertSame(['lib/all_tables.php:1492 "\n"'], $printing);
    }

    /** @param list<string> $command @return array{0: int, 1: string, 2: string} */
    private function process(array $command, string $directory): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $this->remove($path . '/' . $entry); }
        }
        rmdir($path);
    }
}
