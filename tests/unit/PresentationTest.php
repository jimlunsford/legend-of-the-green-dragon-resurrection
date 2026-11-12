<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Presentation 1.1.2 printed inline (Phase 5 task 9, docs/PHASE5-TASK9-FORMS-20261031.md): appoencode()'s `c, `> and `<
 * are classes the template's stylesheet draws, and so is every entry of resurrection_presentation().
 *
 * Until Phase 5 task 15 a 1.1.2 skin got 1.1.2's markup, byte for byte as 5b3ca63 printed it (the pinned LEGACY and
 * LEGACY_MAP); the skins and that markup are gone, and with them the pins (docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md;
 * docs/CHANGES-FROM-1.1.2.md lists each entry's 1.1.2 markup). The classes are printed with the template loaded and
 * before any template is (an operator page before one loads): there is one markup now.
 *
 * Each case runs in its own PHP process with the template's parts split from the template file as loadtemplate() splits
 * them. No database.
 */
final class PresentationTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string TEXT = "`cA centered line`c`>Right`>`<Left`< `2Green`0 `bBold`b";
    private const string RESURRECTION = "<div class='rz-center'>A centered line</div><div class='rz-float-right'>Right</div>"
        . "<div class='rz-float-left'>Left</div> <span class='colDkGreen'>Green</span> <b>Bold</b>";
    /** Every entry of resurrection_presentation(). */
    private const array KEYS = ['grid', 'grid-fit', 'inline-form', 'inline-form-dq', 'inline-form-flush', 'link-button', 'shop-button', 'larger',
        'larger-end', 'violet', 'grey', 'white', 'gold', 'warning', 'typed', 'pre-wrap', 'indent', 'clan-motd', 'clan-textarea',
        'plain-link', 'danger-cell'];

    public function testAppoencodeUsesClasses(): void
    {
        foreach ([true, false] as $loaded) {
            self::assertSame(self::RESURRECTION, $this->run_($loaded, 'echo appoencode(' . var_export(self::TEXT, true) . ');'));
        }
    }

    public function testEveryPresentationEntryIsAClass(): void
    {
        $code = 'foreach (' . var_export(self::KEYS, true) . ' as $k) echo $k, "\t", resurrection_presentation($k), "\n";';
        $new = $this->table($this->run_(true, $code));
        self::assertSame(self::KEYS, array_keys($new));
        foreach ($new as $key => $markup) {
            self::assertStringNotContainsString('style=', $markup, $key);
            self::assertStringNotContainsString('bgcolor', $markup, $key);
            self::assertStringNotContainsString('<font', $markup, $key);
        }
        self::assertSame("<span class='rz-larger'>", $new['larger']);
        self::assertSame(" class='rz-grid'", $new['grid']);
        self::assertSame(" class='rz-grid rz-fit'", $new['grid-fit']);
        self::assertSame('', $new['danger-cell']);
        // The same before a template is loaded; an unknown key is nothing.
        self::assertSame($new, $this->table($this->run_(false, $code)));
        self::assertSame('', $this->run_(true, 'echo resurrection_presentation("no-such-entry");'));
    }

    /** @return array<string, string> */
    private function table(string $out): array
    {
        $rows = [];
        foreach (explode("\n", rtrim($out, "\n")) as $line) {
            [$key, $markup] = explode("\t", $line, 2);
            $rows[$key] = $markup;
        }
        return $rows;
    }

    private function run_(bool $loaded, string $code): string
    {
        $preamble = 'function getsetting($name, $default) { return $default; } require "lib/output.php";'
            . ($loaded ? ' $template = []; foreach (explode("<!--!", (string)file_get_contents("templates/resurrection.htm")) as $piece) {'
            . ' $end = strpos($piece, "-->"); if ($end !== false && $end > 0) $template[substr($piece, 0, $end)] = substr($piece, $end + 3); }'
            . ' $GLOBALS["template"] = $template;' : '');
        $process = proc_open([PHP_BINARY, '-r', $preamble . ' ' . $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, (string)realpath(self::ROOT));
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        return $out;
    }
}
