<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The shell commands' refusal messages link the host guide (Phase 6 task 17, R4). The documents are not on the server,
 * so each message that sends the operator to read on names the host guide's matching section online at the running
 * version's tag (lib/source_link.php), and none names a docs/ path or a Markdown file. Each command runs as a host runs
 * it; none of them reaches a database (the last one is given a closed port).
 */
final class CommandLinkTest extends TestCase
{
    private static function guide(): string
    {
        require_once 'src/Version.php';
        return 'https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v' . \Resurrection\Version::CURRENT . '/docs/HOST-GUIDE.md';
    }

    /** @return array{0:int,1:string,2:string} exit code, stdout, stderr */
    private static function command(string ...$arguments): array
    {
        $environment = array_diff_key(getenv(), ['LOGD_DBCONNECT' => true]);
        $process = proc_open([PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testEachRefusalLinksTheHostGuideAtTheTagAndNamesNoDocument(): void
    {
        $closed = tempnam(sys_get_temp_dir(), 'rz-closed-');
        self::assertIsString($closed);
        file_put_contents($closed, "<?php \$DB_HOST='127.0.0.1:1'; \$DB_USER='nobody'; \$DB_PASS='closed-port-only'; \$DB_NAME='none'; \$DB_PREFIX=''; \$DB_USEDATACACHE=0; \$DB_DATACACHEPATH='';\n");
        try {
            $cases = [
                'install, unknown option' => [['scripts/install.php', '--no-such-option'], '#5-install',
                    'Installation refused. Unknown option. See the host guide, "Install": ' . self::guide() . "#5-install\n"],
                'upgrade, unknown option' => [['scripts/upgrade.php', '--no-such-option'], '#9-upgrade',
                    'Upgrade refused. Unknown option. Use --dry-run or --backup-confirmed (the host guide, "Upgrade": ' . self::guide() . "#9-upgrade). Nothing was changed.\n"],
                'upgrade, no backup confirmed' => [['scripts/upgrade.php'], '#10-backups-and-restore', null],
                'upgrade, failed' => [['scripts/upgrade.php', '--dry-run', $closed], '#9-upgrade', null],
            ];
            foreach ($cases as $label => [$arguments, $anchor, $exact]) {
                [$code, $out, $err] = self::command(...$arguments);
                self::assertSame(1, $code, $label);
                self::assertSame('', $out, $label);
                if ($exact !== null) self::assertSame($exact, $err, $label);
                self::assertStringContainsString(self::guide() . $anchor, $err, $label);
                $rest = str_replace(self::guide() . $anchor, '', $err);
                self::assertStringNotContainsString('docs/', $rest, $label);
                self::assertDoesNotMatchRegularExpression('#\b[\w.-]+\.md\b#', $rest, $label);
                self::assertSame(1, substr_count($err, "\n"), $label . ': one line');
            }
        } finally {
            unlink($closed);
        }
    }
}
