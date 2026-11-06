<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 3 (S2): the files a browser may load define RESURRECTION_ENTRY first; every file under lib/, modules/
 * and src/ (and common.php and battle.php, which only pages include) checks it before anything else and answers a
 * direct request with an empty 404. A command run from the shell (PHP_SAPI cli) is not a web request and is let
 * through: the CLI entry points define the constant too, and the test helpers load single files. The HTTP check (every
 * internal file requested directly gives an empty 404 and no log line) is in tests/tooling/test_hardening_http.py.
 */
final class EntryGuardTest extends TestCase
{
    public const string GUARD = "if (!\\defined('RESURRECTION_ENTRY') && \\PHP_SAPI !== 'cli') { \\http_response_code(404); exit; }";
    public const string DEFINE = "define('RESURRECTION_ENTRY', true);";

    public function testEveryInternalFileChecksTheConstantFirst(): void
    {
        $root = dirname(__DIR__, 2);
        $wrong = [];
        $count = 0;
        foreach ($this->files($root) as $file) {
            if (!preg_match('#\A(lib|modules|src)/#', $file) && !in_array($file, ['common.php', 'battle.php'], true)) continue;
            $count++;
            if ($this->firstStatement($root . '/' . $file) !== self::GUARD) $wrong[] = $file;
        }
        self::assertSame([], $wrong, 'The first statement (after declare and namespace) must be the guard');
        self::assertGreaterThan(270, $count);
    }

    public function testEveryEntryPointDefinesTheConstantFirst(): void
    {
        $root = dirname(__DIR__, 2);
        $wrong = [];
        $entries = [];
        foreach ($this->files($root) as $file) {
            if (!(preg_match('#\A[^/]+\.php\z#', $file) || preg_match('#\Ascripts/[^/]+\.php\z#', $file))
                || in_array($file, ['common.php', 'battle.php', 'dbconnect.php'], true)) continue;
            $entries[] = $file;
            if ($this->firstStatement($root . '/' . $file) !== self::DEFINE) $wrong[] = $file;
        }
        self::assertSame([], $wrong);
        self::assertContains('village.php', $entries);
        self::assertContains('installer.php', $entries);
        self::assertContains('cron.php', $entries);
        self::assertContains('scripts/install.php', $entries);
        self::assertContains('scripts/upgrade.php', $entries);
        self::assertCount(62, $entries);
        // PHPUnit's own entry point.
        self::assertSame(self::DEFINE, $this->firstStatement($root . '/tests/bootstrap.php'));
        self::assertStringContainsString('bootstrap="tests/bootstrap.php"', (string)file_get_contents($root . '/phpunit.xml'));
        self::assertTrue(defined('RESURRECTION_ENTRY'));
    }

    /** @return list<string> */
    private function files(string $root): array
    {
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard -- "*.php"', $output, $status);
        self::assertSame(0, $status);
        return array_values(array_filter(explode("\0", implode("\n", $output)), static fn (string $file): bool =>
            $file !== '' && !str_starts_with($file, 'tests/') && !str_starts_with($file, 'vendor/') && is_file($root . '/' . $file)));
    }

    /** The file's first statement after <?php, line comments, declare(...) and namespace ...;, as written on its line. */
    private function firstStatement(string $path): string
    {
        $lines = explode("\n", (string)file_get_contents($path));
        foreach ($lines as $n => $line) {
            $line = trim($line);
            if ($n === 0 && $line === '<?php') continue;
            if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, 'declare(strict_types=1);') || preg_match('/\Anamespace [A-Za-z\\\\]+;\z/', $line)) continue;
            return $line;
        }
        return '';
    }
}
