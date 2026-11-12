<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Tests and CI never send real mail (Phase 6 task 13a, S11). PHPUnit and every php process a test starts run with
 * sendmail_path set to /bin/false, which refuses every message, or to a test's capture program
 * (tests/tooling/mail_capture.py). CI sets it in php.ini through setup-php's ini-values, in both jobs. Locally, give it
 * through an ini file in PHP_INI_SCAN_DIR, which the child processes read too (CLAUDE.md, "Local toolchain notes").
 */
final class MailCaptureGuardTest extends TestCase
{
    private static function harmless(string $path): bool
    {
        return $path === '/bin/false' || preg_match('#/resurrection-mail-capture-[^/]+/sendmail\z#', $path) === 1;
    }

    public function testPhpUnitAndItsChildProcessesCannotSendMail(): void
    {
        $here = (string)ini_get('sendmail_path');
        self::assertTrue(self::harmless($here), 'sendmail_path is "' . $here . '": run PHPUnit with sendmail_path=/bin/false in php.ini or PHP_INI_SCAN_DIR, never the host\'s mail program.');
        $process = proc_open([PHP_BINARY, '-r', 'echo ini_get("sendmail_path");'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $child = (string)stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertTrue(self::harmless($child), 'A php process a test starts has sendmail_path "' . $child . '": set it in php.ini or PHP_INI_SCAN_DIR, not only with -d.');
    }

    public function testBothCiJobsSetIt(): void
    {
        $workflow = (string)file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/modern-core-leg.yml');
        self::assertSame(2, substr_count($workflow, 'ini-values: error_reporting=-1, display_errors=1, sendmail_path=/bin/false'));
        self::assertSame(2, substr_count($workflow, 'ini-values:'));
    }
}
