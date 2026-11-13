<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Links to the project's documents (Phase 6 task 16, R2). The documents are not on the server: the release package
 * ships them beside upload/, never in the docroot. So no text the game shows names a docs/ path or a Markdown file;
 * a page that sends an operator to one links it online at this version's tag, through resurrection_doc_url() and
 * resurrection_doc_link() (lib/source_link.php), and every document and heading linked that way exists in the tree.
 */
final class DocLinkTest extends TestCase
{
    /**
     * Shipped files that may name a document, and why. Nothing here is text a page shows.
     * - lib/source_link.php: the helper's own check of a document path.
     * - src/Upgrade/StepCatalog.php: 'README.md' is the note that ships inside upgrades/, which the catalog skips.
     * - scripts/install.php, scripts/upgrade.php: the commands' refusal texts stay as they are (CLAUDE.md, "Install,
     *   upgrade and maintenance services"; tests/unit/UpgradeTest.php is their proof). Their doc names are R2's open
     *   part, recorded in docs/PHASE6-TASK16-20261113.md.
     */
    private const array EXCEPTIONS = [
        'lib/source_link.php' => ['#\Adocs/[A-Za-z0-9][A-Za-z0-9._-]*\.md\z#'],
        'src/Upgrade/StepCatalog.php' => ['README.md'],
        'scripts/install.php' => ['Unknown option. See docs/MODERN-INSTALLATION.md.'],
        'scripts/upgrade.php' => [
            'Unknown option. Use --dry-run or --backup-confirmed (docs/MODERN-INSTALLATION.md, "Upgrading"). Nothing was changed.',
            'Take a fresh backup of the database first, then run again with --backup-confirmed. Some upgrade changes cannot be rolled back: MySQL and MariaDB commit a table change the moment it runs, so if an upgrade stops partway, the backup is the way back. To see what would change without changing anything, use --dry-run. docs/MODERN-INSTALLATION.md, "Upgrading", says how to take and restore a backup. Nothing was changed.',
            '). If the upgrade had started, the report above says what was applied. Check the configuration and docs/MODERN-INSTALLATION.md, "Upgrading".',
        ],
    ];

    private const string NAMES_A_DOCUMENT = '#docs/|\b[\w.-]+\.md\b#';

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/source_link.php';
        require_once 'src/Version.php';
    }

    /** @return list<string> every shipped file the game runs or serves, as the release package's upload/ holds them */
    private static function shipped(): array
    {
        $files = [];
        foreach (['*.php', 'templates/*.htm', 'assets/ui/*.js', 'scripts/install.php', 'scripts/upgrade.php'] as $pattern) {
            $files = array_merge($files, glob($pattern) ?: []);
        }
        foreach (['lib', 'src', 'modules'] as $folder) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);
        return array_values(array_unique($files));
    }

    /** @return list<string> the text a file can show: PHP's string literals and inline HTML; a template or script whole, comments out */
    private static function texts(string $file): array
    {
        $source = (string)file_get_contents($file);
        if (!str_ends_with($file, '.php')) {
            if (str_ends_with($file, '.js')) $source = (string)preg_replace('#/\*.*?\*/|(?<![:"\'])//[^\n]*#s', '', $source);
            return [$source];
        }
        $texts = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
                $text = $token[1];
                if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $text = $text[0] === "'" ? strtr(substr($text, 1, -1), ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes(substr($text, 1, -1));
                }
                $texts[] = $text;
            }
        }
        return $texts;
    }

    public function testNoShippedTextNamesADocumentExceptThroughTheHelper(): void
    {
        $found = [];
        foreach (self::shipped() as $file) {
            foreach (self::texts($file) as $text) {
                if (preg_match(self::NAMES_A_DOCUMENT, $text) !== 1) continue;
                // A bare document path, alone in its literal, is an argument for the helper (checked below).
                if (preg_match('#\Adocs/[A-Za-z0-9][A-Za-z0-9._-]*\.md\z#', $text) === 1) continue;
                if (in_array($text, self::EXCEPTIONS[$file] ?? [], true)) continue;
                $found[] = $file . ': ' . substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 160);
            }
        }
        self::assertSame([], $found);
    }

    public function testEveryLinkedDocumentAndHeadingExists(): void
    {
        $links = [];
        foreach (self::shipped() as $file) {
            $source = (string)file_get_contents($file);
            preg_match_all("#resurrection_doc_url\\(\\s*'(docs/[^']+)'\\s*(?:,\\s*'([a-z0-9-]*)'\\s*)?\\)#", $source, $urls, PREG_SET_ORDER);
            preg_match_all("#resurrection_doc_link\\(\\s*'(docs/[^']+)'\\s*,\\s*'(?:[^'\\\\]|\\\\.)*'\\s*(?:,\\s*'([a-z0-9-]*)'\\s*)?\\)#", $source, $html, PREG_SET_ORDER);
            preg_match_all("#\\[\\s*'(docs/[^']+)'\\s*,\\s*'(?:[^'\\\\]|\\\\.)*'\\s*,\\s*'([a-z0-9-]*)'\\s*\\]#", $source, $arrays, PREG_SET_ORDER);
            foreach ([...$urls, ...$html, ...$arrays] as $match) $links[] = [$file, $match[1], $match[2] ?? ''];
            preg_match_all("#'(docs/[A-Za-z0-9][A-Za-z0-9._-]*\\.md)'#", $source, $bare);
            foreach ($bare[1] as $path) self::assertFileExists($path, $file);
        }
        self::assertNotEmpty($links);
        $missing = [];
        foreach ($links as [$file, $path, $section]) {
            if (!is_file($path)) { $missing[] = "$file: $path"; continue; }
            if ($section === '') continue;
            preg_match_all('/^#{1,6}\s+(.+?)\s*$/m', (string)file_get_contents($path), $headings);
            $slugs = array_map(static fn (string $h): string => str_replace(' ', '-', (string)preg_replace('/[^a-z0-9 _-]/', '', strtolower(trim(str_replace('`', '', $h))))), $headings[1]);
            if (!in_array($section, $slugs, true)) $missing[] = "$file: $path#$section";
        }
        self::assertSame([], $missing);
        // The pages that send an operator to install, upgrade or restore link the host guide, not the developer guide.
        foreach ($links as [$file, $path]) self::assertSame('docs/HOST-GUIDE.md', $path, $file);
    }

    public function testTheLinkIsTheRepositoryAtThisVersionsTag(): void
    {
        $url = RESURRECTION_SOURCE_URL . '/blob/v' . \Resurrection\Version::CURRENT . '/docs/HOST-GUIDE.md';
        self::assertSame($url, resurrection_doc_url('docs/HOST-GUIDE.md'));
        self::assertSame($url . '#9-upgrade', resurrection_doc_url('docs/HOST-GUIDE.md', '9-upgrade'));
        self::assertSame('<a href="' . $url . '#11-mail">&quot;Mail&quot; in the host guide &amp; more</a>',
            resurrection_doc_link('docs/HOST-GUIDE.md', '"Mail" in the host guide & more', '11-mail'));
        // It opens in place: no target, no script.
        self::assertStringNotContainsString('target=', resurrection_doc_link('docs/HOST-GUIDE.md', 'x'));
        foreach ([['README.md', ''], ['docs/../NOTICE', ''], ['docs/HOST-GUIDE.md"><x', ''], ['docs/sub/x.md', ''],
            ['docs/HOST-GUIDE.md', 'a b'], ['docs/HOST-GUIDE.md', '"x'], ['https://example.org/docs/x.md', '']] as [$path, $section]) {
            try {
                resurrection_doc_url($path, $section);
                self::fail("Accepted $path#$section");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
