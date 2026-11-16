<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Http\FailureLog;

/**
 * Phase 6 task 20, the logging check. The production crash of R6 reached the host's nginx error log because it was
 * uncaught: PHP wrote its own line, with the message, the file and the line. That holds for every page: the game's
 * error handler turns a warning into an ErrorException that keeps its file and line, and nothing in the game turns
 * log_errors off. What logged nothing was a failure the game catches and answers with a 5xx ("Nothing was changed"):
 * 32 catch blocks and 11 failure helpers. Each now writes FailureLog's one line first; this test keeps it that way.
 */
final class FailureLogTest extends TestCase
{
    public function testTheLineNamesThePageTheClassTheFileAndTheLineAndNothingElse(): void
    {
        $saved = $_SERVER['SCRIPT_NAME'] ?? null;
        $_SERVER['SCRIPT_NAME'] = '/forest.php';
        try {
            $error = new \RuntimeException("secret 'value' SELECT * FROM accounts\nsecond line"); $line = __LINE__;
            self::assertSame('Resurrection: forest.php failed: RuntimeException at tests/unit/FailureLogTest.php:' . $line, FailureLog::line($error));
            $error = new \PDOException('SQLSTATE[23000]: Duplicate entry \'player@example.test\''); $line = __LINE__;
            self::assertStringNotContainsString('example', FailureLog::line($error));
            self::assertStringEndsWith('PDOException at tests/unit/FailureLogTest.php:' . $line, FailureLog::line($error));
        } finally {
            if ($saved === null) unset($_SERVER['SCRIPT_NAME']); else $_SERVER['SCRIPT_NAME'] = $saved;
        }
    }

    public function testRecordWritesExactlyOneLineToTheErrorLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'rz-failure-');
        $saved = ini_set('error_log', $log);
        try {
            FailureLog::record(new \LogicException('not in the log'));
        } finally {
            ini_set('error_log', (string)$saved);
        }
        $lines = file($log, FILE_IGNORE_NEW_LINES);
        unlink($log);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertMatchesRegularExpression('#Resurrection: \S+ failed: LogicException at tests/unit/FailureLogTest\.php:\d+\z#', $lines[0]);
        self::assertStringNotContainsString('not in the log', $lines[0]);
    }

    /** An uncaught error, as a page meets it after common.php's error handler: PHP's own line, with file and line. */
    public function testAnUncaughtErrorOnAPageWritesALineWithItsFileAndLine(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['missing class' => 'new \Resurrection\Game\NoSuchState();', 'warning' => '$x = []; echo $x["missing"];', 'thrown' => 'throw new DomainException("Active wager.");'] as $what => $code) {
            $probe = tempnam(sys_get_temp_dir(), 'rz-uncaught-') . '.php';
            $log = tempnam(sys_get_temp_dir(), 'rz-uncaught-log-');
            file_put_contents($probe, "<?php\ndefine('RESURRECTION_ENTRY', true);\nrequire " . var_export($root . '/src/autoload.php', true) . ";\nrequire " . var_export($root . '/lib/errorhandling.php', true) . ";\n" . $code . "\n");
            exec(escapeshellarg(PHP_BINARY) . ' -n -d display_errors=0 -d log_errors=1 -d error_log=' . escapeshellarg($log) . ' ' . escapeshellarg($probe) . ' 2>&1', $output, $status);
            $lines = (array)file($log, FILE_IGNORE_NEW_LINES);
            unlink($probe); unlink($log);
            self::assertSame(255, $status, $what);
            self::assertSame([], $output, $what . ': nothing on the page');
            self::assertNotSame([], $lines, $what);
            // The first line of PHP's entry carries the error and where it happened: the probe's line 5.
            self::assertMatchesRegularExpression('#PHP Fatal error:  Uncaught \S+: .* in ' . preg_quote($probe, '#') . ':5\z#', (string)$lines[0], $what);
        }
    }

    public function testEveryCaughtFailureAnsweredWithA5xxIsLogged(): void
    {
        $root = dirname(__DIR__, 2);
        $silent = [];
        $catches = 0;
        $helpers = 0;
        foreach ($this->shippedPhp($root) as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            // catch (Throwable ...) { ... http_response_code(5xx) ... }
            preg_match_all('/catch\s*\(\s*\\\\?Throwable\b[^)]*\)\s*\{/', $source, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [$text, $offset]) {
                $block = $this->block($source, $offset + strlen($text));
                if (!preg_match('/http_response_code\(\s*5\d\d/', $block)) continue;
                $catches++;
                if (!str_contains($block, 'FailureLog::record(') && !str_contains($block, 'error_log(')) $silent[] = $file . ':' . (substr_count($source, "\n", 0, $offset) + 1);
            }
            // A failure helper: a function that takes the Throwable and answers 5xx.
            preg_match_all('/function\s+\w+\s*\([^)]*\\\\?Throwable\s+\$\w+[^)]*\)[^{]*\{/', $source, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [$text, $offset]) {
                $block = $this->block($source, $offset + strlen($text));
                if (!preg_match('/http_response_code\(\s*5\d\d/', $block)) continue;
                $helpers++;
                if (!str_contains($block, 'FailureLog::record(') && !str_contains($block, 'error_log(')) $silent[] = $file . ':' . (substr_count($source, "\n", 0, $offset) + 1);
            }
        }
        self::assertSame([], $silent, 'A caught failure answered with a 5xx writes its log line first');
        self::assertGreaterThan(30, $catches);
        self::assertGreaterThan(10, $helpers);
    }

    private function block(string $source, int $start): string
    {
        $depth = 1;
        for ($i = $start, $n = strlen($source); $i < $n && $depth > 0; $i++) {
            if ($source[$i] === '{') $depth++;
            elseif ($source[$i] === '}') $depth--;
        }
        return substr($source, $start, $i - $start - 1);
    }

    /** @return list<string> */
    private function shippedPhp(string $root): array
    {
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard -- "*.php"', $output, $status);
        self::assertSame(0, $status);
        return array_values(array_filter(explode("\0", implode("\n", $output)), static fn (string $file): bool =>
            $file !== '' && !str_starts_with($file, 'tests/') && !str_starts_with($file, 'vendor/') && is_file($root . '/' . $file)));
    }
}
