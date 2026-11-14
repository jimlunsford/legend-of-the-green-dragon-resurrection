<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Ui\AssetUrl;

/**
 * Versioned asset URLs (Phase 6 task 18, N1; src/Ui/AssetUrl.php): each stylesheet and script URL the game prints
 * carries ?v= and the first 12 hex characters of its file's SHA-256, computed once per request, so a browser fetches a
 * changed file after an upgrade. The page renders (PopupAdminRenderTest, TemplateRenderTest) check every URL on a page;
 * tests/tooling/test_asset_versions.py checks the URLs inside the stylesheets.
 */
final class AssetUrlTest extends TestCase
{
    private string $work = '';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../src/Ui/AssetUrl.php';
    }

    protected function tearDown(): void
    {
        AssetUrl::reset();
        if ($this->work !== '') {
            foreach (glob($this->work . '/assets/ui/*') ?: [] as $file) unlink($file);
            @rmdir($this->work . '/assets/ui');
            @rmdir($this->work . '/assets');
            @rmdir($this->work);
        }
    }

    public function testEveryStylesheetAndScriptGetsItsFilesVersion(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root . '/assets/ui/*.css') ?: [], glob($root . '/assets/ui/*.js') ?: []);
        self::assertCount(8, $files, 'four stylesheets and four scripts');
        foreach ($files as $file) {
            $path = substr($file, strlen($root) + 1);
            self::assertSame($path . '?v=' . substr(hash_file('sha256', $file), 0, 12), AssetUrl::versioned($path), $path);
            self::assertMatchesRegularExpression('#\Aassets/ui/[a-z]+\.(css|js)\?v=[0-9a-f]{12}\z#', AssetUrl::versioned($path));
        }
    }

    public function testTheVersionChangesWhenTheFileChanges(): void
    {
        $this->work = (string)tempnam(sys_get_temp_dir(), 'resurrection-asset-url-');
        unlink($this->work);
        mkdir($this->work . '/assets/ui', 0700, true);
        file_put_contents($this->work . '/assets/ui/game.css', "body { color: red; }\n");
        $first = AssetUrl::versioned('assets/ui/game.css', $this->work);
        self::assertSame('assets/ui/game.css?v=' . substr(hash('sha256', "body { color: red; }\n"), 0, 12), $first);
        // An upgrade changes the file: the next request links the new version. Within one request it is computed once.
        file_put_contents($this->work . '/assets/ui/game.css', "body { color: blue; }\n");
        self::assertSame($first, AssetUrl::versioned('assets/ui/game.css', $this->work), 'kept for the rest of the request');
        AssetUrl::reset();
        $second = AssetUrl::versioned('assets/ui/game.css', $this->work);
        self::assertSame('assets/ui/game.css?v=' . substr(hash('sha256', "body { color: blue; }\n"), 0, 12), $second);
        self::assertNotSame($first, $second);
        // A file that cannot be read keeps its plain URL, so the page still links it.
        self::assertSame('assets/ui/missing.js', AssetUrl::versioned('assets/ui/missing.js', $this->work));
        // rewrite() versions every stylesheet and script link in markup, and leaves every other URL alone.
        $html = '<link rel="stylesheet" href="assets/ui/game.css"><script src="assets/ui/missing.js" defer></script>'
            . '<link rel="icon" href="favicon.svg"><img src="assets/scenes/village.svg" alt=""><a href="assets/ui/game.css.txt">x</a>';
        self::assertSame('<link rel="stylesheet" href="' . $second . '"><script src="assets/ui/missing.js" defer></script>'
            . '<link rel="icon" href="favicon.svg"><img src="assets/scenes/village.svg" alt=""><a href="assets/ui/game.css.txt">x</a>',
            AssetUrl::rewrite($html, $this->work));
    }

    public function testTheTemplateAndTheSafeFrameAreVersionedAfterTheirChecks(): void
    {
        $root = dirname(__DIR__, 2);
        // The template file and the safe frame keep their plain URLs as written; prepare_template() versions them.
        self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', (string)file_get_contents($root . '/templates/resurrection.htm'));
        $source = (string)file_get_contents($root . '/lib/template.php');
        self::assertStringContainsString("if (is_string(\$text)) \$template[\$part] = \\Resurrection\\Ui\\AssetUrl::rewrite(\$text);", $source);
        self::assertGreaterThan(strpos($source, '$template=\Resurrection\Ui\SafeFrame::parts();'), strpos($source, 'AssetUrl::rewrite($text)'),
            'after the safe frame is chosen');
        foreach (['lib/output.php' => ['showform.js', 'behaviors.js'], 'lib/pageparts.php' => ['keys.js', 'tabs.js']] as $file => $scripts) {
            $text = (string)file_get_contents($root . '/' . $file);
            foreach ($scripts as $script) {
                self::assertStringContainsString("AssetUrl::versioned('assets/ui/" . $script . "')", $text, $file);
                self::assertStringNotContainsString('src=\"assets/ui/' . $script . '\"', $text, $file);
            }
        }
    }
}
