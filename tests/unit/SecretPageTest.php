<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The one rule for a page that shows a secret once (Phase 6 task 5b, step 0; lib/pageparts.php): the page is sent with
 * Cache-Control: no-store, and the copy of it the game keeps (accounts_output, shown again by badnav.php and the user
 * editor) has the secret part replaced by a fixed note. Each case runs in its own process, since the secrets a page
 * shows are remembered for its request.
 */
final class SecretPageTest extends TestCase
{
    /** Runs PHP code after loading lib/pageparts.php's helpers; returns its standard output. */
    private static function page(string $code): string
    {
        $script = "define('RESURRECTION_ENTRY', true); chdir(" . var_export(dirname(__DIR__, 2), true) . ");"
            . " require 'lib/names.php'; ob_start(); foreach (['constants','datacache','settings','modules','output','sanitize','translator','pageparts'] as \$l) require_once 'lib/'.\$l.'.php'; ob_end_clean();"
            . " \$GLOBALS['output'] = ''; " . $code;
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        return $out;
    }

    public function testAPageWithoutASecretIsKeptAsItIs(): void
    {
        self::assertSame('<p>page</p><!--rz-secret-->x<!--/rz-secret-->', self::page("echo resurrection_secret_kept_copy('<p>page</p><!--rz-secret-->x<!--/rz-secret-->');"));
    }

    public function testTheSecretPartIsReplacedByTheNote(): void
    {
        $out = self::page("resurrection_secret_output('<input value=\"S3cret-value-0123456789\">', 'S3cret-value-0123456789');"
            . " \$shown = \$GLOBALS['output']; echo json_encode(['shown' => \$shown, 'kept' => resurrection_secret_kept_copy('<h1>Settings</h1>' . \$shown . '<p>after</p>'), 'page' => resurrection_secret_page()]);");
        $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('S3cret-value-0123456789', $result['shown']);
        self::assertSame("<h1>Settings</h1><p class=\"rz-secret-note\">A secret was shown here once. It is not kept, so it cannot be shown again.</p>\n<p>after</p>", $result['kept']);
        self::assertTrue($result['page']);
    }

    public function testASecretOutsideTheMarkedPartKeepsOnlyTheNote(): void
    {
        $out = self::page("resurrection_secret_output('<b>S3cret-value-0123456789</b>', 'S3cret-value-0123456789');"
            . " echo resurrection_secret_kept_copy('<p>also S3cret-value-0123456789 here</p><!--rz-secret--><b>S3cret-value-0123456789</b><!--/rz-secret-->');");
        self::assertSame('<p class="rz-secret-note">A secret was shown here once. It is not kept, so it cannot be shown again.</p>', $out);
    }

    public function testPageFooterKeepsTheMaskedCopy(): void
    {
        // The rule sits where the kept copy is made: page_footer() hands saveuser() the copy, never the page.
        $source = (string)file_get_contents('lib/pageparts.php');
        self::assertSame(1, substr_count($source, "\$session['output']=resurrection_secret_kept_copy(\$output);"));
        self::assertSame(0, preg_match("/\\\$session\\['output'\\]\\s*=\\s*\\\$output\\s*;/", $source));
        // Both pages that show a secret use it: Game Settings' web maintenance and the setup page.
        self::assertStringContainsString('resurrection_secret_output(resurrection_webcron_once_html(', (string)file_get_contents('lib/webcron.php'));
        self::assertStringContainsString('resurrection_secret_output(', (string)file_get_contents('lib/setup/page.php'));
    }
}
