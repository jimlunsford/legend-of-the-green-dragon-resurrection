<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Phase 6 task 3 (S1): the game's pages pass a Content Security Policy that runs only the game's own script and style
 * files (src/Http/SecurityHeaders.php), so no shipped source may print an inline script, an inline event handler, a
 * javascript: URL, a style attribute or a <style> element. assets/ui/behaviors.js does what those did, from data
 * attributes. The scan reads the strings and inline HTML of every shipped PHP file (comments are skipped) and the
 * template; tests/tooling/test_web_application.py checks every page the HTTP suite renders for the same things.
 */
final class InlineContentTest extends TestCase
{
    /** What the policy refuses, as it can appear in PHP strings or markup. */
    private const array PATTERNS = [
        'an inline script' => '/<script\b(?![^>]*\bsrc\s*=)/i',
        'an inline event handler' => '/<[a-z][^<>]*?\son[a-z]+\s*=/is',
        'an event handler attribute' => '/(?:^|[\s\'"])on(?:click|change|submit|keyup|keydown|keypress|load|input|reset|focus|blur|mouse[a-z]+|dblclick|error|unload|beforeunload)\s*=\s*[\\\\\'"]/i',
        'a javascript: URL' => '/javascript:/i',
        'a style attribute' => '/(?:^|[\s\'"])style\s*=/i',
        'a style element' => '/<style\b/i',
    ];

    /**
     * Places a pattern may stay, each with why: (file, text on the line).
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const array ALLOWED = [
        ['lib/installer/installer_sqlstatements.php', "'Gypsy Bandit'", "data: the Gypsy Bandit's stored creature script, recognized by its SHA-256 (CreatureAi) and never run or printed; lib/extended-battle.php prints the pickpocket's line with classes"],
        ['lib/sanitize.php', '<script[^>]*>', "the sanitizer's pattern that removes script elements from text"],
        ['lib/sanitize.php', '<style[^>]*>', "the sanitizer's pattern that removes style elements from text"],
    ];

    public function testNoShippedSourcePrintsWhatThePolicyRefuses(): void
    {
        $root = dirname(__DIR__, 2);
        $found = [];
        $used = [];
        foreach ($this->sources($root) as $file => $chunks) {
            foreach ($chunks as [$line, $text]) {
                foreach (self::PATTERNS as $what => $pattern) {
                    if (!preg_match($pattern, $text)) continue;
                    $allowed = null;
                    foreach (self::ALLOWED as $n => [$path, $needle]) {
                        if ($path === $file && str_contains($text, $needle)) $allowed = $n;
                    }
                    if ($allowed === null) $found[] = "$file:$line: $what: " . substr(trim($text), 0, 140);
                    else $used[$allowed] = true;
                }
            }
        }
        self::assertSame([], $found);
        ksort($used);
        self::assertSame(array_keys(self::ALLOWED), array_keys($used), 'An allowed place is gone: drop its entry');
    }

    public function testThePatternsFindWhatThePolicyRefuses(): void
    {
        $bad = ["<script language='JavaScript'>x()</script>", '<script>const a=1;</script>', "<a href='#' onClick=\"x()\">",
            "<input name='a' onKeyUp='p()'>", "<form onsubmit='return confirm(1);'>", ' onClick=\\"x()\\"', '<a href="javascript:x()">',
            '<div style="width: 5%">', "<span style='color: red'>", '<style>img { max-width: 100% }</style>',
            "<script type=\"application/json\" id=\"rz-keys\">[]</script>"];
        $good = ['<script src="assets/ui/behaviors.js" defer></script>', "<script src=\"assets/ui/keys.js\" defer data-rz-keys='[]'></script>",
            '<a href="mail.php" data-rz-popup=\'550x300\'>', '<div class="rz-statbar-fill rz-fill-40"></div>', "<button data-rz-confirm=\"Sure?\">",
            'the onClick handler is gone', 'Online', "<input name='online' value='1'>", '$style = "default";', 'one decimal'];
        foreach ($bad as $text) {
            self::assertTrue($this->matchesAny($text), $text);
        }
        foreach ($good as $text) {
            self::assertFalse($this->matchesAny($text), $text);
        }
    }

    private function matchesAny(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) return true;
        }
        return false;
    }

    /**
     * Every shipped PHP file's strings and inline HTML (not its comments), and the template, as [line, text] chunks.
     * @return array<string, list<array{0: int, 1: string}>>
     */
    private function sources(string $root): array
    {
        exec('git -C ' . escapeshellarg($root) . ' ls-files -z --cached --others --exclude-standard -- "*.php" "*.htm" "*.html"', $output, $status);
        self::assertSame(0, $status);
        $result = [];
        foreach (array_filter(explode("\0", implode("\n", $output))) as $file) {
            if (preg_match('#\A(tests|lab|vendor)/#', $file) || $file === 'dbconnect.php' || !is_file("$root/$file")) continue;
            $text = (string)file_get_contents("$root/$file");
            if (!str_ends_with($file, '.php')) {
                foreach (explode("\n", $text) as $n => $line) $result[$file][] = [$n + 1, $line];
                continue;
            }
            foreach (\PhpToken::tokenize($text) as $token) {
                if (!$token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML, T_START_HEREDOC])) continue;
                foreach (explode("\n", $token->text) as $n => $line) $result[$file][] = [$token->line + $n, $line];
            }
            // Heredoc and nowdoc bodies (the safe frame's template) are T_ENCAPSED_AND_WHITESPACE: read above.
        }
        self::assertArrayHasKey('templates/resurrection.htm', $result);
        self::assertArrayHasKey('src/Ui/SafeFrame.php', $result);
        return $result;
    }
}
