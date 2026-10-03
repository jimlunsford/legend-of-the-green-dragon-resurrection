<?php

declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstallerStage6Test extends TestCase
{
    public static function modes(): array
    {
        return [['fresh', true], ['upgrade', true], ['failure', false]];
    }

    #[DataProvider('modes')]
    public function testActualStageGeneratesValidConfigWithoutDisplayingSecrets(string $mode, bool $success): void
    {
        $process = proc_open([PHP_BINARY, '-n', '-d', 'error_reporting=-1',
            __DIR__ . '/../fixtures/installer-stage6.php', $mode],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        self::assertSame(['success' => $success, 'matches' => $success, 'leaked' => false],
            json_decode($out, true, flags: JSON_THROW_ON_ERROR));
    }
}
