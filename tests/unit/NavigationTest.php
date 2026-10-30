<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The navigation (Phase 5 task 6, docs/PHASE5-TASK6-NAVIGATION-20261030.md): navs rendered by the game's own nav code
 * (addnav(), buildnavs(), private_addnav()) through tests/fixtures/nav-render.php, in a PHP process of its own, with no
 * database.
 *
 * The hotkeys are pinned from 98fb6ec, the code before the key badges, whose private_addnav() is 1.1.2's: a label with
 * its key up front ("X?Label", with the key in the label, not in it, already taken, lowercase), a label whose first
 * letter is taken, labels with no free letter or digit, a translated label, labels with color codes, an entity and
 * HTML, popups, and a page that runs out of keys. The new code must give every item the same key, in the new template
 * and in an old skin, and the old skin's navs must render byte for byte as at 98fb6ec.
 *
 * In the new template each key shows as a badge equal to the link's accesskey and aria-keyshortcuts, the badge hidden
 * from assistive technology so the link's name is the label alone; the page's key map (read by assets/ui/keys.js)
 * has one entry per key, going where the link goes; and a page with no nav to show has no nav column.
 */
final class NavigationTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    /** The navs: each entry is one addnav() call. */
    private const array NAVS = [
        ['Gates'],
        ['D?Talk to Dag Durnick', 'inn.php?op=bartender'],
        ['Q?Return to the Village', 'village.php'],
        ['q?Quiet corner', 'inn.php?op=corner'],
        ['v?Return home', 'home.php'],
        ['!?`&&#149; __GOD MODE', 'forest.php?op=fight&skill=godmode', true],
        ['T?Train', 'train.php'],
        ['Streets'],
        ['Forest', 'forest.php'],
        ['Fight the Master', 'train.php?op=challenge'],
        ['Visit the Tavern', 'inn.php'],
        ['Bank', 'bank.php'],
        ['`@Green `^Dragon', 'dragon.php'],
        ['&#149; Mercenary Camp', 'mercenarycamp.php', true],
        ['<b>Hall of Fame</b>', 'hof.php', true],
        ['Mail', 'mail.php', false, true, '550x300'],
        ['Open a window', 'list.php', false, true, ''],
        ['Help'],
        ['Read the help', ''],
        ['', 'hidden.php?op=secret'],
        ['Exhaust'],
        ['Armor Shop', 'armor.php'],
        ['Weapons Shop', 'weapons.php'],
        ['Healer', 'healer.php'],
        ['Jewelry', 'jewelry.php'],
        ['Kitchen', 'kitchen.php'],
        ['Lodge', 'lodge.php'],
        ['News', 'news.php'],
        ['Oracle', 'oracle.php'],
        ['Prison', 'prison.php'],
        ['Riddles', 'riddles.php'],
        ['Stables', 'stables.php'],
        ['Undertaker', 'undertaker.php'],
        ['Vault', 'vault.php'],
        ['Xylophone', 'xylo.php'],
        ['Yard', 'yard.php'],
        ['Zoo', 'zoo.php'],
        ['Clock', 'clock.php'],
        ['Ice', 'ice.php'],
        ['Pier 9', 'pier.php'],
        ['0 to 8 in 1 2 3', 'count.php'],
        ['4 5 6 7', 'more.php'],
        ['Go', 'go.php'],
        ['!!!', 'bang.php'],
        ['Digits 12345678', 'digits.php?n=1'],
        ['Digits 12345678', 'digits.php?n=2'],
        ['Digits 12345678', 'digits.php?n=3'],
        ['Digits 12345678', 'digits.php?n=4'],
        ['Digits 12345678', 'digits.php?n=5'],
        ['Digits 12345678', 'digits.php?n=6'],
        ['Digits 12345678', 'digits.php?n=7'],
        ['Last one', 'last.php'],
        ['Zebra', 'zebra.php'],
    ];
    /** The translated label (translate() as the translation tables would answer). */
    private const array TRANSLATIONS = ['Visit the Tavern' => 'Besuche die Taverne', 'Bank' => '`^Ye Olde `2Bank'];

    /**
     * Each nav item's key, in order (null: no key), as 98fb6ec chose them. Notes: "Q?Return to the Village" has no Q
     * in its label, so 1.1.2 prefixes "(Q) " and the key is Q; "q?Quiet corner" asks for q, already taken by Q, so the
     * first free letter is used; "v?Return home" asks for a lowercase v that is not in the label, and 1.1.2's search
     * then lands on the "(" of the "(V) " prefix it added, so the key is "("; "Stables" and the others with no key have
     * no free letter or digit; "Digits 12345678" takes the free digits one by one until the page runs out of keys.
     */
    private const array PINNED_KEYS = ['D', 'Q', 'u', '(', '!', 'T', 'F', 'i', 'B', 'Y', 'G', 'M', 'H', 'a', 'O', 'r', 'W', 'e',
        'J', 'K', 'L', 'N', 'c', 'P', 's', null, null, 'V', 'X', null, 'Z', null, null, '9', '0', '4', null, null,
        '1', '2', '3', '5', '6', '7', '8', null, null];
    /** The access keys the page used (the $accesskeys global), in the order taken, as 98fb6ec took them. */
    private const array PINNED_ACCESSKEYS = ['d', 'q', 'u', '(', '!', 't', 'f', 'i', 'b', 'y', 'g', 'm', 'h', 'a', 'o', 'r', 'w', 'e',
        'j', 'k', 'l', 'n', 'c', 'p', 's', 'v', 'x', 'z', '9', '0', '4', '1', '2', '3', '5', '6', '7', '8'];
    /** The old skin jade.htm's navs as 98fb6ec rendered them, the time in each link written as HHMMSS: SHA-256 and length. */
    private const string PINNED_JADE_SHA256 = '6dd157864acc856d886e604bd3889895c8c3221411e138e9e85ac34c7e332116';
    private const int PINNED_JADE_LENGTH = 5991;

    public function testEveryNavKeepsTheKey98fb6ecGaveIt(): void
    {
        foreach (['resurrection.htm', 'jade.htm', 'Classic.htm'] as $template) {
            $page = $this->render($template);
            self::assertSame(self::PINNED_KEYS, array_column($this->items($page['html']), 'accesskey'), $template);
            self::assertSame(self::PINNED_ACCESSKEYS, $page['accesskeys'], $template);
        }
    }

    public function testAnOldSkinRendersItsNavsAsBefore(): void
    {
        $html = (string)preg_replace('/c=7-\d{6}/', 'c=7-HHMMSS', $this->render('jade.htm')['html']);
        self::assertSame(self::PINNED_JADE_LENGTH, strlen($html));
        self::assertSame(self::PINNED_JADE_SHA256, hash('sha256', $html));
        // 1.1.2's in-label marking: the key highlighted in the label, or "(X) " before it.
        self::assertStringContainsString(">Talk to <span class='navhi'>D</span>ag Durnick</a>", $html);
        self::assertStringContainsString(">(<span class='navhi'>Q</span>) Return to the Village</a>", $html);
        self::assertStringContainsString("&#149; (<span class='navhi'>!</span>) GOD MODE</a>", $html);
        self::assertStringNotContainsString('rz-key', $html);
        self::assertStringNotContainsString('aria-keyshortcuts', $html);
    }

    public function testEachKeyShowsAsABadgeBeforeAPlainLabel(): void
    {
        $page = $this->render('resurrection.htm');
        $items = $this->items($page['html']);
        self::assertCount(47, $items);
        foreach ($items as $item) {
            // The badge, the accesskey and aria-keyshortcuts carry the same key; no key, none of them, and an empty badge.
            self::assertSame($item['accesskey'], $item['shortcut'], $item['link']);
            self::assertSame($item['accesskey'] ?? '', html_entity_decode($item['badge'], ENT_QUOTES), $item['link']);
            // The link holds the hidden badge and the label, nothing else: its accessible name is the label alone.
            self::assertSame('<span class="rz-key" aria-hidden="true">' . $item['badge'] . '</span><span class="rz-navlabel">' . $item['label'] . '</span>', $item['content']);
            // The label shows plain: no in-label marking, no "(X) " prefix, and every tag it opens closed in it.
            self::assertStringNotContainsString('navhi', $item['label'], $item['link']);
            self::assertDoesNotMatchRegularExpression('/\(.\) /', $item['label'], $item['link']);
            self::assertSame(substr_count($item['label'], '<span'), substr_count($item['label'], '</span>'), $item['link']);
            self::assertStringStartsNotWith('</', $item['label'], $item['link']);
        }
        $labels = array_column($items, 'label', 'link');
        self::assertSame('Talk to Dag Durnick', $labels['inn.php?op=bartender&c=7']);
        self::assertSame('Return to the Village', $labels['village.php?c=7']);
        self::assertSame('Return home', $labels['home.php?c=7']);
        self::assertSame("<span class='colLtWhite'>&#149; GOD MODE</span>", $labels['forest.php?op=fight&skill=godmode&c=7']);
        self::assertSame('Besuche die Taverne', $labels['inn.php?c=7']);
        self::assertSame("<span class='colLtYellow'>Ye Olde </span><span class='colDkGreen'>Bank</span>", $labels['bank.php?c=7']);
        self::assertSame('<b>Hall of Fame</b>', $labels['hof.php?c=7']);
        self::assertSame('Stables', $labels['stables.php?c=7']);
        // The popups keep their target and click handler.
        self::assertStringContainsString("class=\"rz-navitem nav\" target='_blank' onClick=\"window.open('mail.php','mailphp','scrollbars=yes,resizable=yes,width=550,height=300').focus(); return false;\">", $page['html']);
        self::assertStringContainsString("class=\"rz-navitem nav\" target='_blank'><span class=\"rz-key\" aria-hidden=\"true\">O</span>", $page['html']);
        // Headings and help text, the hidden nav allowed but not shown.
        self::assertStringContainsString('<h2 class="rz-navhead">Gates</h2>', $page['html']);
        self::assertStringContainsString('<div class="rz-navhelp">Read the help</div>', $page['html']);
        self::assertStringNotContainsString('hidden.php', $page['html']);
        self::assertContains('hidden.php?op=secret', $page['allowednavs']);
    }

    public function testTheKeyMapHasOneEntryPerKeyGoingWhereTheLinkGoes(): void
    {
        $page = $this->render('resurrection.htm');
        $items = array_values(array_filter($this->items($page['html']), fn (array $item) => $item['accesskey'] !== null));
        self::assertIsArray($page['keys']);
        self::assertCount(count($items), $page['keys']);
        self::assertSame(array_map(fn (array $item) => strtoupper((string)$item['accesskey']), $items), array_column($page['keys'], 0));
        foreach ($items as $n => $item) {
            $entry = $page['keys'][$n];
            self::assertSame($item['href'], $entry[2], $item['link']);
            if ($item['href'] === 'mail.php') {
                self::assertSame(['A', 'popup', 'mail.php', 'mailphp', 'scrollbars=yes,resizable=yes,width=550,height=300'], $entry);
            } elseif ($item['href'] === 'list.php') {
                self::assertSame(['O', 'open', 'list.php'], $entry);
            } else {
                self::assertSame('go', $entry[1], $item['link']);
            }
        }
        // The map rides in {script} as data, with the static script; no inline key handler.
        $script = (string)$page['script'];
        self::assertSame(1, preg_match('#\A<script type="application/json" id="rz-keys">(.*)</script>\n<script src="assets/ui/keys.js" defer></script>\z#s', $script, $json), $script);
        self::assertSame($page['keys'], json_decode($json[1], true));
        foreach (['onkeypress', 'keyevent', 'window.location', 'window.open', '<!--', '&', '<b>'] as $absent) {
            self::assertStringNotContainsString($absent, $json[1], $absent);
        }
        // page_footer() removes every {tag} left in the page; the map has none for it to remove.
        self::assertSame($script, preg_replace("/{[^} \t\n\r]*}/i", '', $script));
        $braces = $this->render('resurrection.htm', [['Brace', 'brace.php?x={y}']]);
        self::assertSame($braces['script'], preg_replace("/{[^} \t\n\r]*}/i", '', (string)$braces['script']));
        self::assertStringContainsString('brace.php?x=\\u007by\\u007d', (string)$braces['script']);
        self::assertSame([['B', 'go', 'brace.php?x={y}&c=7-' . substr($braces['keys'][0][2], -6)]], $braces['keys']);
        // No key, no map and no script.
        $none = $this->render('resurrection.htm', [['!!!', 'bang.php'], ['', 'hidden.php']]);
        self::assertSame([], $none['keys']);
        self::assertSame('', $none['script']);
    }

    public function testTranslatorButtonsStayBeforeTheLink(): void
    {
        $page = $this->render('resurrection.htm', [['Forest', 'forest.php'], ['Bank', 'bank.php']], true);
        self::assertSame(2, substr_count($page['html'], "<a href='translatortool.php' class='t-button'>T</a><a href=\""));
        // The translated Bank label ("`^Ye Olde `2Bank") takes its key from the translation.
        self::assertSame(['F', 'Y'], array_column($this->items($page['html']), 'accesskey'));
    }

    public function testAPageWithNoNavHasNoNavColumn(): void
    {
        $navs = $this->render('resurrection.htm', [['Forest', 'forest.php']]);
        self::assertSame(['<nav class="rz-nav" aria-label="Navigation"><div class="rz-card rz-nav-panel">' . "\n", "</div></nav>\n"], $navs['column']);
        // Only hidden navs (a forest fight's are its forms and allowed links): no column, no empty card.
        self::assertSame(['', ''], $this->render('resurrection.htm', [['', 'forest.php?op=fight']])['column']);
        self::assertSame(['', ''], $this->render('resurrection.htm', [])['column']);
        // Something in the {navad} slot shows the column.
        self::assertSame($navs['column'], $this->render('resurrection.htm', [], false, '<div>ad</div>')['column']);
        // An old skin has no column parts and keeps its own markup.
        self::assertSame(['', ''], $this->render('jade.htm', [['Forest', 'forest.php']])['column']);
    }

    /**
     * Renders navs through tests/fixtures/nav-render.php from the tree's root.
     *
     * @param list<list<mixed>>|null $navs
     * @return array{html: string, accesskeys: list<string>, keys: list<list<string>>|null, script: string|null, column: list<string>|null, allowednavs: list<string>, output: string}
     */
    private function render(string $template, ?array $navs = null, bool $translator = false, string $navad = ''): array
    {
        $input = json_encode(['template' => $template, 'translator' => $translator, 'translations' => self::TRANSLATIONS,
            'navs' => $navs ?? self::NAVS, 'navad' => $navad], JSON_THROW_ON_ERROR);
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', 'tests/fixtures/nav-render.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, (string)realpath(self::ROOT));
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $err . $out);
        self::assertSame('', $err);
        $page = json_decode($out, true);
        self::assertIsArray($page, $out);
        // Nothing the nav code printed into the page (a template warning would be).
        self::assertSame('', $page['output']);
        return $page;
    }

    /**
     * The nav links: their URL (link: the time in it dropped; href: as followed), accesskey, aria-keyshortcuts, badge,
     * label and content.
     *
     * @return list<array{link: string, href: string, accesskey: string|null, shortcut: string|null, badge: string, label: string, content: string}>
     */
    private function items(string $html): array
    {
        preg_match_all('#<a href="([^"]*)"([^>]*)>(.*?)</a>#s', $html, $links, PREG_SET_ORDER);
        $items = [];
        foreach ($links as [, $href, $attributes, $content]) {
            if (str_starts_with($href, 'translatortool.php')) { continue; }
            $href = html_entity_decode($href, ENT_QUOTES);
            preg_match('/accesskey="([^"]*)"/', $attributes, $key);
            preg_match('/aria-keyshortcuts="([^"]*)"/', $attributes, $shortcut);
            preg_match('#\A<span class="rz-key" aria-hidden="true">(.*?)</span><span class="rz-navlabel">(.*)</span>\z#s', $content, $parts);
            $items[] = ['link' => (string)preg_replace('/-\d{6}\z/', '', $href), 'href' => $href,
                'accesskey' => isset($key[1]) ? html_entity_decode($key[1], ENT_QUOTES) : null,
                'shortcut' => isset($shortcut[1]) ? html_entity_decode($shortcut[1], ENT_QUOTES) : null,
                'badge' => $parts[1] ?? '', 'label' => $parts[2] ?? '', 'content' => $content];
        }
        return $items;
    }
}
