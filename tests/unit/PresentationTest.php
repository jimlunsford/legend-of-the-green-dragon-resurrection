<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Presentation 1.1.2 printed inline (Phase 5 task 9, docs/PHASE5-TASK9-FORMS-20261031.md): in the Resurrection template
 * appoencode()'s `c, `> and `< are classes its stylesheet draws; in a 1.1.2 skin they are 1.1.2's markup, byte for byte
 * as 5b3ca63 printed it, until task 15 removes the skins. The same holds for every entry of resurrection_presentation().
 *
 * Each case runs in its own PHP process with the template's parts split from the template file as loadtemplate() splits
 * them, so the real templates decide which markup is printed. No database.
 */
final class PresentationTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string TEXT = "`cA centered line`c`>Right`>`<Left`< `2Green`0 `bBold`b";
    /** appoencode(self::TEXT) at 5b3ca63, the markup a 1.1.2 skin keeps. */
    private const string LEGACY = "<div align='center'>A centered line</div><div style='float: right; clear: right;'>Right</div>"
        . "<div style='float: left; clear: left;'>Left</div> <span class='colDkGreen'>Green</span> <b>Bold</b>";
    private const string RESURRECTION = "<div class='rz-center'>A centered line</div><div class='rz-float-right'>Right</div>"
        . "<div class='rz-float-left'>Left</div> <span class='colDkGreen'>Green</span> <b>Bold</b>";
    /** Each entry's 1.1.2 markup, as the pages printed it at 5b3ca63. */
    private const array LEGACY_MAP = [
        'grid' => " bgcolor='#999999'",
        'inline-form' => " style='display:inline'",
        'inline-form-dq' => ' style="display:inline"',
        'inline-form-flush' => " style='display:inline;margin:0'",
        'link-button' => " style='background:none;border:0;padding:0;margin:0;font:inherit;color:inherit;cursor:pointer;text-decoration:underline'",
        'shop-button' => " style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'",
        'larger' => "<font size='+1'>",
        'larger-end' => '</font>',
        'violet' => "<span style='color: #9900FF'>",
        'grey' => "<span style='color: #787878'>",
        'white' => "<span style='color: white'>",
        'gold' => "<span style='color: gold'>",
        'warning' => "<span style='font-size: 20px'>",
        'typed' => "<span style='font-family: fixed-width'>",
        'pre-wrap' => '<pre style="white-space:pre-wrap">',
        'indent' => "<div style='padding-left: 20pt;'>",
        'clan-motd' => "<div style='margin-left: 15px; padding-left: 15px;'>",
        'clan-textarea' => "class='input' style='width: 66%'",
        'plain-link' => " style='text-decoration: none'",
        'danger-cell' => " style='background-color:#FF00FF'",
    ];

    public function testAppoencodeUsesClassesInTheResurrectionTemplateOnly(): void
    {
        self::assertSame(self::RESURRECTION, $this->run_('resurrection.htm', 'echo appoencode(' . var_export(self::TEXT, true) . ');'));
        self::assertSame(self::LEGACY, $this->run_('jade.htm', 'echo appoencode(' . var_export(self::TEXT, true) . ');'));
    }

    public function testEveryPresentationEntryKeepsItsLegacyMarkupInAnOldSkin(): void
    {
        $keys = var_export(array_keys(self::LEGACY_MAP), true);
        $code = 'foreach (' . $keys . ' as $k) echo $k, "\t", resurrection_presentation($k), "\n";';
        $old = $this->table($this->run_('jade.htm', $code));
        self::assertSame(self::LEGACY_MAP, $old);
        $new = $this->table($this->run_('resurrection.htm', $code));
        foreach ($new as $key => $markup) {
            self::assertStringNotContainsString('style=', $markup, $key);
            self::assertStringNotContainsString('bgcolor', $markup, $key);
            self::assertStringNotContainsString('<font', $markup, $key);
        }
        self::assertSame("<span class='rz-larger'>", $new['larger']);
        self::assertSame(" class='rz-grid'", $new['grid']);
        self::assertSame('', $new['danger-cell']);
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

    private function run_(string $skin, string $code): string
    {
        $preamble = 'function getsetting($name, $default) { return $default; } require "lib/output.php";'
            . ' $template = []; foreach (explode("<!--!", (string)file_get_contents("templates/' . $skin . '")) as $piece) {'
            . ' $end = strpos($piece, "-->"); if ($end !== false && $end > 0) $template[substr($piece, 0, $end)] = substr($piece, $end + 3); }'
            . ' $GLOBALS["template"] = $template;';
        $process = proc_open([PHP_BINARY, '-r', $preamble . ' ' . $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, (string)realpath(self::ROOT));
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        return $out;
    }
}
