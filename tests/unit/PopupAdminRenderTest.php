<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The popups (mail, petition, MoTD, the translator tool) and the admin and superuser screens on real pages (Phase 5
 * task 11, docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md).
 *
 * A staff account with every editor's flag reads its mail (unread, read and system mail), the MoTD with a poll, the
 * petition pages, and the Grotto and its editors. In the Resurrection template each page is checked for its new
 * components. Until Phase 5 task 15 every page was also rendered in a 1.1.2 skin (jade.htm, by its cookie) and compared
 * byte for byte with what 5dafec8 printed (tests/fixtures/popup-admin-old-skin.json); the skins are gone, and so are
 * that test and its fixture (docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md).
 *
 * Rendering is the other render tests': the PHP CLI over a symlinked tree, one PHP session kept across the pages. The
 * pages list what the whole database holds (players, petitions, comments, modules), so the class always works on its own
 * install: an empty database is installed here with scripts/install.php --modules=none; an install another test left
 * (the full suite's order) is set aside first (each table's definition and rows saved to a file, then dropped, since the
 * installer refuses a database that is not empty) and put back, unchanged, after.
 * The game clock is pinned (tests/unit/GameClock.php).
 */
final class PopupAdminRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string PASSWORD = 'Synthetic popup render player password';
    /** Every editor's flag, without the debug output, the source viewer, the SQL console, the payment log or the
     * translator's (which puts a translate link before every string); the translator tool's page adds that one. */
    private const int STAFF = 80206847 & ~262144;
    private const int TRANSLATOR = 262144;
    private const string COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team';
    private const string ATTRIBUTION = '<p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a>'
        . ' Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>';

    /** The popups, by label: their URL ({unread}, {read}, {system} and {pen} are the fixture's ids). */
    private const array POPUPS = [
        'mail inbox' => 'mail.php',
        'mail read' => 'mail.php?op=read&id={unread}',
        'mail read system' => 'mail.php?op=read&id={system}',
        'mail address' => 'mail.php?op=address',
        'mail write' => 'mail.php?op=write&to=RzPen',
        'mail reply' => 'mail.php?op=write&replyto={read}',
        'petition' => 'petition.php',
        'petition faq' => 'petition.php?op=faq',
        'petition faq1' => 'petition.php?op=faq1',
        'petition faq2' => 'petition.php?op=faq2',
        'petition faq3' => 'petition.php?op=faq3',
        'petition primer' => 'petition.php?op=primer',
        'motd' => 'motd.php',
        'motd add' => 'motd.php?op=add',
        'motd add poll' => 'motd.php?op=addpoll',
        'translator' => 'translatortool.php?u=village&t=The+village+square+hustles+and+bustles.',
    ];

    /** The Grotto and the editors. */
    private const array ADMIN = [
        'grotto' => 'superuser.php',
        'configuration' => 'configuration.php',
        'creatures' => 'creatures.php',
        'creature edit' => 'creatures.php?op=edit&creatureid={creature}',
        'creature add' => 'creatures.php?op=add',
        'users' => 'user.php',
        'user edit' => 'user.php?op=edit&userid={pen}',
        'user ban' => 'user.php?op=setupban&userid={pen}',
        'mounts' => 'mounts.php',
        'mount add' => 'mounts.php?op=add',
        'companions' => 'companions.php',
        'companion add' => 'companions.php?op=add',
        'weapons' => 'weaponeditor.php?level=0',
        'armor' => 'armoreditor.php?level=0',
        'titles' => 'titleedit.php',
        'modules' => 'modules.php',
        'petitions' => 'viewpetition.php',
        'petition view' => 'viewpetition.php?op=view&id={petition}',
        'moderate' => 'moderate.php',
        'bios' => 'bios.php',
        'donators' => 'donators.php',
        'referers' => 'referers.php',
        'gamelog' => 'gamelog.php',
        'untranslated' => 'untranslated.php',
        'badwords' => 'badword.php',
        'masters' => 'masters.php',
        'master edit' => 'masters.php?op=edit&id=1',
        'taunts' => 'taunt.php',
        'taunt edit' => 'taunt.php?op=edit&tauntid=1',
    ];

    private static string $tree = '';
    private static string $work = '';
    /** The file holding another test's install while this class runs ('' when none is set aside). */
    private static string $aside = '';
    /** Whether the database holds this class's install (or its start), to drop afterwards. */
    private static bool $owned = false;
    /** @var array<string, string> the fixture's ids */
    private static array $ids = [];
    /** @var array<string, mixed> the staff session */
    private static array $staff = [];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { return; }
        $root = (string)realpath(self::ROOT);
        self::$work = sys_get_temp_dir() . '/resurrection-popup-render-' . bin2hex(random_bytes(6));
        self::$tree = self::$work . '/tree';
        mkdir(self::$tree . '/.sessions', 0700, true);
        foreach (scandir($root) ?: [] as $entry) {
            if (in_array($entry, ['.', '..', '.git', 'dbconnect.php'], true)) { continue; }
            symlink($root . '/' . $entry, self::$tree . '/' . $entry);
        }
        file_put_contents(self::$tree . '/dbconnect.php', LegacyConfig::render(['DB_HOST' => $host,
            'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'),
            'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
        chmod(self::$tree . '/dbconnect.php', 0600);
        $state = self::php('echo resurrection_install_state();');
        if ($state === 'installed') {
            self::setAside(self::$work . '/aside.json');
            $state = self::php('echo resurrection_install_state();');
        }
        try {
            if ($state !== 'empty') {
                throw new \RuntimeException('The disposable test database must be empty or installed, not ' . $state);
            }
            self::$owned = true;
            [$code, $out, $err] = self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', self::$tree . '/dbconnect.php'], $root,
                ['RESURRECTION_ADMIN_LOGIN' => 'PopupRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic popup render admin password'] + getenv());
            if ($code !== 0) { throw new \RuntimeException('Install failed: ' . $err . $out); }
            foreach (GameClock::settings() as $name => $value) { self::php('savesetting(' . var_export($name, true) . ', ' . var_export($value, true) . ');'); }
            self::fixture();
        } catch (\Throwable $e) {
            self::restore();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tree === '') { return; }
        try {
            self::restore();
        } finally {
            self::remove(self::$work);
            self::$tree = '';
        }
    }

    protected function setUp(): void
    {
        if (getenv('RESURRECTION_TEST_DB_HOST') === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
    }

    /**
     * Every popup and admin page in the Resurrection template: the frame's head, the copyright line and the attribution
     * line once each, after it; no page-wide italics or bold left open (each tag the content opens, it closes).
     */
    public function testEveryPageRendersInTheResurrectionFrame(): void
    {
        foreach (self::POPUPS + self::ADMIN as $label => $url) {
            $page = $this->page($url);
            self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $page, $label);
            self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', $page, $label);
            self::assertSame(1, substr_count($page, self::COPYRIGHT), $label);
            self::assertSame(1, substr_count($page, self::ATTRIBUTION), $label);
            self::assertGreaterThan(strpos($page, self::COPYRIGHT), strpos($page, self::ATTRIBUTION), $label);
            $document = $this->document($page);
            self::assertNotNull($document->querySelector('.rz-footer-credits'), $label);
            self::assertNull($document->querySelector('i .rz-footer-credits, b .rz-footer-credits, em .rz-footer-credits'), $label . ': the footer is not inside an open tag');
        }
    }

    /**
     * The mail popup: the Inbox and Write tabs; the inbox as a list, unread first, each unread letter with a New badge,
     * its subject the link, the sender and date after; Check All and Delete Checked secondary; the note plain; no italics
     * anywhere in the page (1.1.2's note left `i open to the end of the page). The read view: subject, sender, date, body,
     * then Reply (the one primary action, on a player's letter), Delete, Mark Unread, and Previous and Next.
     */
    public function testMailPopup(): void
    {
        $ids = self::$ids;
        $inbox = $this->document($this->page('mail.php'));
        $this->assertTabs($inbox, ['Inbox' => 'mail.php', 'Write' => 'mail.php?op=address'], 'Inbox');
        $letters = $inbox->querySelectorAll('.rz-content ul.rz-inbox > li.rz-mail');
        self::assertSame(3, $letters->length);
        $order = [];
        foreach ($letters as $letter) {
            $box = $letter->querySelector('input[type="checkbox"][name="msg[]"]');
            self::assertNotNull($box);
            $id = (string)$box->getAttribute('value');
            $order[] = $id;
            self::assertNotNull($box->closest('label'), "letter $id: the checkbox is labelled");
            self::assertStringContainsString('Select: ', (string)$box->closest('label')->textContent);
            $unread = $id !== $ids['read'];
            self::assertSame($unread, str_contains((string)$letter->getAttribute('class'), 'rz-mail-unread'), "letter $id");
            $badge = $letter->querySelector('.rz-badge.rz-badge-new');
            self::assertSame($unread, $badge !== null, "letter $id: a New badge only while unread");
            if ($badge !== null) { self::assertSame('New', trim((string)$badge->textContent)); }
            $subject = $letter->querySelector('a.rz-mail-subject');
            self::assertNotNull($subject);
            self::assertSame("mail.php?op=read&id=$id", $subject->getAttribute('href'));
            self::assertNotNull($letter->querySelector('.rz-mail-meta .rz-mail-from'));
            self::assertMatchesRegularExpression('/\A[A-Z][a-z]{2} \d\d, \d\d:\d\d [ap]m\z/', trim((string)$letter->querySelector('.rz-mail-meta .rz-mail-date')?->textContent));
        }
        self::assertSame([$ids['unread'], $ids['system'], $ids['read']], $order, 'unread first, then newest first');
        self::assertSame('Meet me at the inn', trim((string)$letters->item(0)?->querySelector('.rz-mail-subject')?->textContent));
        self::assertSame('System', trim((string)$letters->item(1)?->querySelector('.rz-mail-from')?->textContent));
        self::assertStringContainsString('RzPen', (string)$letters->item(2)?->querySelector('.rz-mail-from')?->textContent);
        $checkall = $inbox->querySelector('.rz-mail-actions button[data-rz-checkall="msg[]"]');
        self::assertNotNull($checkall);
        self::assertSame('Check All', trim((string)$checkall->textContent));
        self::assertSame('Delete Checked', $inbox->querySelector('.rz-mail-actions input[type="submit"]')?->getAttribute('value'));
        self::assertNull($inbox->querySelector('.rz-content .rz-button-primary'), 'the inbox has no primary action');
        self::assertSame('mail.php?op=process', $inbox->querySelector('form.rz-inbox-form')?->getAttribute('action'));
        self::assertNotNull($inbox->querySelector('form.rz-inbox-form input[name="csrf_token"]'));
        self::assertNotNull($inbox->querySelector('script[src="assets/ui/showform.js"]'));
        self::assertStringStartsWith('You currently have 3 messages in your inbox.', trim((string)$inbox->querySelector('.rz-content p.rz-mail-note')?->textContent));
        self::assertSame('Mail Box', trim((string)$inbox->querySelector('.rz-content h2.rz-mail-heading')?->textContent));
        $this->assertNoItalics($inbox, 'mail inbox');
        $html = $inbox->saveHtml();
        foreach (['newscroll.GIF', 'oldscroll.GIF', 'onClick', 'onclick'] as $gone) { self::assertStringNotContainsString($gone, $html, 'mail inbox'); }

        // A player's unread letter.
        $read = $this->document($this->page('mail.php?op=read&id={unread}'));
        $this->assertTabs($read, ['Inbox' => 'mail.php', 'Write' => 'mail.php?op=address'], 'Inbox');
        $message = $read->querySelector('.rz-content article.rz-mail-message');
        self::assertNotNull($message);
        self::assertSame('New Meet me at the inn', preg_replace('/\s+/', ' ', trim((string)$message->querySelector('h2.rz-mail-heading')?->textContent)));
        self::assertNotNull($message->querySelector('h2 .rz-badge.rz-badge-new'));
        self::assertStringContainsString('RzPen', (string)$message->querySelector('.rz-mail-meta .rz-mail-from')?->textContent);
        self::assertSame('2026-10-31 18:40:00', trim((string)$message->querySelector('.rz-mail-meta .rz-mail-date')?->textContent));
        $body = $message->querySelector('.rz-mail-body');
        self::assertNotNull($body);
        self::assertStringContainsString('The bard plays tonight.', (string)$body->textContent);
        self::assertStringContainsString('Save me a seat by the fire.', (string)$body->textContent);
        self::assertNotNull($body->querySelector('br'));
        $primary = $read->querySelectorAll('.rz-content .rz-button-primary');
        self::assertSame(1, $primary->length, 'Reply is the one primary action');
        self::assertSame('Reply', trim((string)$primary->item(0)?->textContent));
        self::assertSame('mail.php?op=write&replyto=' . $ids['unread'], $primary->item(0)?->getAttribute('href'));
        foreach (['del' => 'Delete', 'unread' => 'Mark Unread'] as $op => $label) {
            $form = $message->querySelector('form[action="mail.php?op=' . $op . '"]');
            self::assertNotNull($form, $label);
            self::assertSame($ids['unread'], $form->querySelector('input[name="id"]')?->getAttribute('value'));
            self::assertNotNull($form->querySelector('input[name="csrf_token"]'));
            self::assertSame($label, trim((string)$form->querySelector('button[type="submit"]')?->textContent));
        }
        $report = $message->querySelector('a[href^="petition.php?problem="]');
        self::assertNotNull($report);
        self::assertSame('Report to Admin', trim((string)$report->textContent));
        self::assertSame('mail.php?op=read&id=' . $ids['read'], $message->querySelector('nav.rz-pager a[href*="id=' . $ids['read'] . '"]')?->getAttribute('href'));
        self::assertNotNull($message->querySelector('nav.rz-pager a[href="mail.php?op=read&id=' . $ids['system'] . '"]'));
        $this->assertNoItalics($read, 'mail read');
        self::assertStringNotContainsString('scroll.GIF', $read->saveHtml());
        self::assertSame('1', self::php('echo db_query("SELECT seen FROM mail WHERE messageid=?", true, [' . (int)$ids['unread'] . '])[0]["seen"];'), 'reading marks the letter read');

        // The game's own letter: no Reply and no Report to Admin; the last letter's Next is off.
        $system = $this->document($this->page('mail.php?op=read&id={system}'));
        self::assertNull($system->querySelector('.rz-content .rz-button-primary'));
        self::assertNull($system->querySelector('.rz-content a[href^="mail.php?op=write"]'));
        self::assertNull($system->querySelector('.rz-content a[href^="petition.php"]'));
        self::assertSame('System', trim((string)$system->querySelector('.rz-mail-meta .rz-mail-from')?->textContent));
        self::assertSame('The clan has accepted you.', trim((string)$system->querySelector('.rz-mail-body')?->textContent));
        self::assertSame('true', $system->querySelector('nav.rz-pager span.rz-pager-off')?->getAttribute('aria-disabled'));
        $this->assertNoItalics($system, 'mail read system');

        // Writing: the Write tab, every field labelled, Send the one primary action.
        foreach (['mail address' => 'mail.php?op=address', 'mail write' => 'mail.php?op=write&to=RzPen', 'mail reply' => 'mail.php?op=write&replyto={read}'] as $label => $url) {
            $page = $this->document($this->page($url));
            $this->assertTabs($page, ['Inbox' => 'mail.php', 'Write' => 'mail.php?op=address'], 'Write');
            $this->assertLabelled($page, $label);
            $this->assertNoItalics($page, $label);
        }
        $write = $this->document($this->page('mail.php?op=write&to=RzPen'));
        $primary = $write->querySelectorAll('.rz-content .rz-button-primary');
        self::assertSame(1, $primary->length);
        self::assertSame('Send', trim((string)$primary->item(0)?->textContent));
        self::assertNotNull($write->querySelector('label[for="subject"]'));
        self::assertNotNull($write->querySelector('label[for="inputbody"]'));
    }

    /**
     * The petition (a labelled form, Submit the primary action), its FAQ pages, the MoTD (title, author and date, body;
     * the poll as a labelled form with Vote primary and bars, not rule.gif; reading it clears the unread mark as 1.1.2
     * did) and the translator tool (staff only, labelled, Save & Close primary).
     */
    public function testPetitionMotdAndTranslator(): void
    {
        $petition = $this->document($this->page('petition.php'));
        $this->assertLabelled($petition, 'petition');
        $primary = $petition->querySelectorAll('.rz-content .rz-button-primary');
        self::assertSame(1, $primary->length);
        self::assertSame('Submit', $primary->item(0)?->getAttribute('value'));
        self::assertSame('petition.php?op=submit', $primary->item(0)?->closest('form')?->getAttribute('action'));
        foreach (['petition faq' => 'petition.php?op=faq', 'petition faq1' => 'petition.php?op=faq1', 'petition faq2' => 'petition.php?op=faq2',
                  'petition faq3' => 'petition.php?op=faq3', 'petition primer' => 'petition.php?op=primer'] as $label => $url) {
            $page = $this->document($this->page($url));
            self::assertGreaterThan(200, strlen(trim((string)$page->querySelector('.rz-content')?->textContent)), $label);
            self::assertNull($page->querySelector('.rz-content .rz-button-primary'), $label);
        }

        $motd = $this->document($this->page('motd.php'));
        $items = [];
        foreach ($motd->querySelectorAll('.rz-content article.rz-motd') as $article) {
            $items[preg_replace('/\s+/', ' ', trim((string)$article->querySelector('h2.rz-motd-title')?->textContent))] = $article;
        }
        foreach (['Popup render fixture: the bridge is open' => ['2026-10-29 10:00:00', 'Travellers may pass at no charge.'],
                  'Popup render fixture: harvest fair' => ['2026-10-30 12:00:00', 'The harvest fair opens in the village square at noon.']] as $title => [$date, $text]) {
            self::assertArrayHasKey($title, $items);
            $article = $items[$title];
            self::assertSame($date, trim((string)$article->querySelector('.rz-motd-meta .rz-motd-date')?->textContent), $title);
            self::assertStringContainsString('RzStaff', (string)$article->querySelector('.rz-motd-meta .rz-motd-author')?->textContent, $title);
            self::assertStringContainsString($text, (string)$article->querySelector('.rz-motd-body')?->textContent, $title);
            self::assertNotNull($article->querySelector('.rz-motd-admin a.button[href^="motd.php?op=add&id="]'), $title);
            self::assertNotNull($article->querySelector('.rz-motd-admin button[data-rz-confirm]'), $title);
        }
        self::assertNotNull($items['Popup render fixture: the bridge is open']->querySelector('.rz-motd-body br'), 'the body keeps its line breaks');
        self::assertArrayHasKey('Poll: Popup render fixture: the caravan', $items);
        $poll = $items['Poll: Popup render fixture: the caravan'];
        self::assertSame('Poll:', trim((string)$poll->querySelector('.rz-motd-kind')?->textContent));
        self::assertSame('2026-10-31 08:00:00', trim((string)$poll->querySelector('.rz-motd-date')?->textContent));
        self::assertSame('Which road should the caravan take?', trim((string)$poll->querySelector('fieldset.rz-poll > legend.rz-poll-question')?->textContent));
        $options = $poll->querySelectorAll('fieldset.rz-poll .rz-poll-option');
        self::assertSame(2, $options->length);
        foreach ([['The river road (0 - 0%)', '1'], ['The mountain pass (1 - 100%)', '400']] as $n => [$text, $bar]) {
            $option = $options->item($n);
            self::assertNotNull($option);
            $radio = $option->querySelector('input[type="radio"][name="choice"]');
            self::assertSame((string)$n, $radio?->getAttribute('value'));
            self::assertNotNull($motd->querySelector('label[for="' . $radio?->getAttribute('id') . '"]'), $text);
            self::assertSame($text, trim((string)$option->querySelector('.rz-poll-text')?->textContent));
            self::assertSame($bar, $option->querySelector('progress.rz-poll-bar')?->getAttribute('value'));
        }
        $vote = $poll->querySelectorAll('.rz-button-primary');
        self::assertSame(1, $vote->length);
        self::assertSame('Vote', $vote->item(0)?->getAttribute('value'));
        self::assertSame('motd.php?op=vote', $vote->item(0)?->closest('form')?->getAttribute('action'));
        self::assertStringNotContainsString('rule.gif', $motd->saveHtml());
        $this->assertLabelled($motd, 'motd');
        $this->assertNoItalics($motd, 'motd');
        self::assertSame(self::php('echo db_query("SELECT MAX(motddate) AS d FROM motd")[0]["d"];'),
            self::php('echo db_query("SELECT lastmotd FROM accounts WHERE acctid=?", true, [' . (int)self::$ids['staff'] . '])[0]["lastmotd"];'),
            'reading the MoTD clears its unread mark');
        foreach (['motd add' => 'motd.php?op=add', 'motd add poll' => 'motd.php?op=addpoll'] as $label => $url) {
            $this->assertLabelled($this->document($this->page($url)), $label);
        }

        $translator = $this->document($this->page(self::POPUPS['translator']));
        $this->assertLabelled($translator, 'translator');
        $primary = $translator->querySelectorAll('.rz-content .rz-button-primary');
        self::assertSame(1, $primary->length);
        self::assertSame('Save & Close', $primary->item(0)?->getAttribute('value'));
        foreach (['translator-uri', 'translator-text', 'translator-trans'] as $id) {
            self::assertNotNull($translator->querySelector('label[for="' . $id . '"]:not(.rz-sr)'), $id);
        }
    }

    /**
     * The Grotto and the editors: every control labelled; the staff's petition counts as badges, each status's count as
     * the database holds it, Unhandled the one --accent badge; the configuration page's sections as tabs without an index
     * field (its form takes only declared settings); the creature list a table in the content's scroll box; the
     * moderation page the commentary list with each comment's checkbox labelled and its Ban link kept.
     */
    public function testAdminScreens(): void
    {
        foreach (self::ADMIN as $label => $url) {
            $this->assertLabelled($this->document($this->page($url)), $label);
        }

        $grotto = $this->document($this->page('superuser.php'));
        $counts = json_decode(self::php('$c = array_fill(0, 8, 0); foreach (db_query("SELECT status, COUNT(*) AS c FROM petitions GROUP BY status") as $r) $c[(int)$r["status"]] = (int)$r["c"]; echo json_encode($c);'), true);
        $names = [5 => 'Top Level', 4 => 'Escalated', 0 => 'Unhandled', 1 => 'In-Progress', 3 => 'Informational', 7 => 'Awaiting Points', 6 => 'Bug', 2 => 'Closed'];
        $badges = $grotto->querySelectorAll('.rz-petitioncount .rz-petition-badges .rz-badge.rz-petition-badge');
        self::assertSame(8, $badges->length);
        $n = 0;
        foreach ($names as $status => $name) {
            $badge = $badges->item($n++);
            self::assertNotNull($badge);
            self::assertSame($name . ': ' . $counts[$status], $badge->getAttribute('title'));
            self::assertSame($name . ': ' . $counts[$status], trim((string)$badge->textContent));
            self::assertSame($name . ': ', $badge->querySelector('.rz-sr')?->textContent);
            self::assertSame($status === 0, str_contains((string)$badge->getAttribute('class'), 'rz-petition-unhandled'), $name);
        }
        // The fixture's petitions, by status: on this class's own install, they are all there are.
        self::assertSame([0 => 3, 1 => 1, 2 => 2, 3 => 1, 4 => 1, 5 => 1, 6 => 0, 7 => 1], $counts);
        self::assertSame('user.php', $grotto->querySelector('.rz-petitioncount a.rz-staff-link')?->getAttribute('href'));
        self::assertNotNull($grotto->querySelector('.rz-petitioncount a.rz-staff-link[href="viewpetition.php"]'));

        $configuration = $this->document($this->page('configuration.php'));
        $list = $configuration->querySelector('.rz-content [data-rz-tabs][role="tablist"]');
        self::assertNotNull($list);
        self::assertTrue($list->hasAttribute('data-rz-tabs-noindex'));
        $tabs = $list->querySelectorAll('[role="tab"]');
        self::assertGreaterThan(3, $tabs->length);
        foreach ($tabs as $n => $tab) {
            self::assertSame($n === 0 ? 'true' : 'false', $tab->getAttribute('aria-selected'));
            $panel = $configuration->getElementById((string)$tab->getAttribute('aria-controls'));
            self::assertNotNull($panel, (string)$tab->textContent);
            self::assertSame('tabpanel', $panel->getAttribute('role'));
            self::assertSame(trim((string)$tab->textContent), trim((string)$panel->querySelector('.rz-form-section-title')?->textContent));
        }
        self::assertSame('Game Setup', trim((string)$tabs->item(0)?->textContent));
        self::assertNull($configuration->querySelector('input[name="showFormTabIndex"]'));
        self::assertNull($configuration->querySelector('.rz-content .rz-button-primary'), 'Save stays secondary');
        self::assertNotNull($configuration->querySelector('script[src="assets/ui/showform.js"]'));

        $creatures = $this->document($this->page('creatures.php'));
        $table = $creatures->querySelector('.rz-content table.rz-grid');
        self::assertNotNull($table);
        self::assertNotNull($table->querySelector('a[href^="creatures.php?op=edit&creatureid="]'));
        $edit = $this->document($this->page('creatures.php?op=edit&creatureid={creature}'));
        self::assertNotNull($edit->querySelector('.rz-content form[action="creatures.php?op=save"] table.rz-showform'));

        $moderate = $this->document($this->page('moderate.php'));
        $comments = [];
        foreach ($moderate->querySelectorAll('.rz-content ul.rz-comments > li.rz-comment') as $item) {
            if (str_contains((string)$item->textContent, '(rzrender)')) { $comments[] = $item; }
        }
        self::assertCount(3, $comments);
        foreach ($comments as $item) {
            $box = $item->querySelector('input[type="checkbox"][name^="comment["]');
            self::assertNotNull($box);
            self::assertSame('Select this comment', trim((string)$moderate->querySelector('label[for="' . $box->getAttribute('id') . '"]')?->textContent));
            self::assertNotNull($item->querySelector('a[href^="user.php?op=setupban&userid="]'));
        }
        self::assertNotNull($moderate->querySelector('.rz-content a[href="moderate.php?area=rzrender"]'));
        self::assertNotNull($moderate->querySelector('label[for="reason0"]'));
    }

    // The fixture.

    /** The staff account and a pen pal, three letters, two MoTD items and a poll, petitions in each status, comments. */
    private static function fixture(): void
    {
        // Fixed ids, so the pages print the same ids every run.
        $make = function (string $login, int $superuser, int $acctid): int {
            $now = date('Y-m-d H:i:s');
            return (int)self::php('require_once "lib/accounts.php"; $id = resurrection_create_account(' . var_export($login, true) . ', ' . var_export(self::PASSWORD, true) . ');'
                . ' db_query("UPDATE accounts SET acctid=? WHERE acctid=?", true, [' . $acctid . ', $id]); db_query("UPDATE accounts_output SET acctid=? WHERE acctid=?", true, [' . $acctid . ', $id]);'
                . ' db_query("UPDATE accounts SET loggedin=1, laston=?, lasthit=?, superuser=?, lastmotd=?, regdate=?, lastip=?, uniqueid=? WHERE acctid=?", true, [' . var_export($now, true) . ', ' . var_export(date('Y-m-d H:i:s', time() + 60), true) . ', ' . $superuser . ', "2026-10-31 09:00:00", "2026-10-01 12:00:00", "127.0.0.1", "rzrender' . $acctid . '", ' . $acctid . ']); echo ' . $acctid . ';');
        };
        $staff = $make('RzStaff', self::STAFF, 910001);
        $pen = $make('RzPen', 0, 910002);
        $ids = ['staff' => (string)$staff, 'pen' => (string)$pen];
        $mail = function (int $id, int|string $from, string $subject, string $body, string $sent, int $seen) use ($staff): string {
            return self::php('db_query("INSERT INTO mail (messageid, msgfrom, msgto, subject, body, sent, seen) VALUES (?, ?, ?, ?, ?, ?, ?)", true, [' . $id . ', ' . var_export((string)$from, true) . ', ' . $staff . ', '
                . var_export($subject, true) . ', ' . var_export($body, true) . ', ' . var_export($sent, true) . ', ' . $seen . ']); echo ' . $id . ';');
        };
        $ids['read'] = $mail(920001, $pen, 'Supplies for the road', "Bring rope and two torches.\nWe leave at dawn.", '2026-10-30 08:15:00', 1);
        $ids['unread'] = $mail(920002, $pen, 'Meet me at the inn', "The bard plays tonight.\nSave me a seat by the fire.", '2026-10-31 18:40:00', 0);
        $ids['system'] = $mail(920003, 0, 'Your Clan Application', 'The clan has accepted you.', '2026-10-31 07:05:00', 0);
        $poll = self::php('require_once "src/Security/MotdPoll.php"; echo \Resurrection\Security\MotdPoll::encode(["body" => "Which road should the caravan take?", "opt" => ["The river road", "The mountain pass", "", ""]]);');
        foreach ([['Popup render fixture: the bridge is open', "The bridge over the river is open again.\nTravellers may pass at no charge.", '2026-10-29 10:00:00', 0, ''],
                  ['Popup render fixture: harvest fair', 'The harvest fair opens in the village square at noon.', '2026-10-30 12:00:00', 0, ''],
                  ['Popup render fixture: the caravan', $poll, '2026-10-31 08:00:00', 1, '']] as $n => [$title, $body, $date, $type]) {
            $ids['motd' . $n] = self::php('db_query("INSERT INTO motd (motditem, motdtitle, motdbody, motddate, motdtype, motdauthor) VALUES (?, ?, ?, ?, ?, ?)", true, [' . (930001 + $n) . ', ' . var_export($title, true) . ', ' . var_export($body, true) . ', ' . var_export($date, true) . ', ' . $type . ', ' . $staff . ']); echo ' . (930001 + $n) . ';');
        }
        self::php('db_query("INSERT INTO pollresults (choice, account, motditem) VALUES (?, ?, ?)", true, [1, ' . $pen . ', ' . $ids['motd2'] . ']);');
        // Petitions in each status: 0 unhandled (3), 1 in progress, 2 closed (2), 3 informational, 4 escalated, 5 top level, 7 awaiting points.
        foreach ([0, 0, 0, 1, 2, 2, 3, 4, 5, 7] as $n => $status) {
            // Dates a whole number of days ago, so their age reads the same ("2d") all day.
            $ids['petition'] = self::php('db_query("INSERT INTO petitions (petitionid, author, date, status, body, pageinfo, closedate, closeuserid, ip, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", true, [' . (940001 + $n) . ', ' . $pen . ', ' . var_export(date('Y-m-d H:i:s', time() - (2 + $n % 3) * 86400), true) . ', ' . $status . ', '
                . var_export("Popup render fixture petition $n.\n[charname] RzPen\n[email] \n[description] The forest path is blocked.", true) . ', "", ' . var_export(date('Y-m-d H:i:s', time() - 86400), true) . ', 0, "127.0.0.1", "rzrender"]); echo ' . (940001 + $n) . ';');
        }
        foreach ([[$pen, 'Has anyone seen the healer today?'], [$staff, ':waves to the crowd.'], [$pen, 'The forest was quiet this morning.']] as $n => [$author, $text]) {
            self::php('db_query("INSERT INTO commentary (section, author, comment, postdate) VALUES (?, ?, ?, ?)", true, ["rzrender", ' . $author . ', ' . var_export($text, true) . ', ' . var_export(sprintf('2026-10-31 0%d:00:00', 8 + $n), true) . ']);');
        }
        $ids['creature'] = self::php('$r = db_query("SELECT MIN(creatureid) AS c FROM creatures"); echo $r[0]["c"];');
        self::$ids = $ids;
        $row = json_decode(self::php('echo json_encode(db_query("SELECT authversion, superuser FROM accounts WHERE acctid=?", true, [' . $staff . '])[0]);'), true);
        self::$staff = ['id' => 'rzpopup' . bin2hex(random_bytes(8)), 'acctid' => $staff, 'authversion' => (int)$row['authversion'], 'superuser' => (int)$row['superuser']];
    }

    /** The letters' read marks as the fixture made them, before each page (reading a letter marks it read). */
    private function resetMail(): void
    {
        self::php('db_query("UPDATE mail SET seen=1 WHERE messageid=?", true, [' . (int)self::$ids['read'] . ']); db_query("UPDATE mail SET seen=0 WHERE messageid IN (?, ?)", true, [' . (int)self::$ids['unread'] . ', ' . (int)self::$ids['system'] . ']);'
            . ' db_query("UPDATE accounts SET lastmotd=? WHERE acctid=?", true, ["2026-10-31 09:00:00", ' . (int)self::$ids['staff'] . ']);');
    }

    // Pages.

    /**
     * One GET in the staff session, with the URL as the account's one allowed navigation. The translator's pages (the
     * tool and the untranslated texts) run in a second session of the same account with the translator's flag added.
     */
    private function page(string $url): string
    {
        $url = strtr($url, array_combine(array_map(fn ($k) => '{' . $k . '}', array_keys(self::$ids)), array_values(self::$ids)));
        $s = self::$staff;
        if (str_starts_with($url, 'translatortool.php') || str_starts_with($url, 'untranslated.php')) { $s['id'] .= 't'; $s['superuser'] |= self::TRANSLATOR; }
        $this->resetMail();
        self::php('db_query("UPDATE accounts SET allowednavs=?, restorepage=?, laston=?, lasthit=?, superuser=? WHERE acctid=?", true, [serialize([' . var_export($url, true) . ' => true]), "", '
            . var_export(date('Y-m-d H:i:s'), true) . ', ' . var_export(date('Y-m-d H:i:s', time() + 60), true) . ', ' . $s['superuser'] . ', ' . $s['acctid'] . ']);');
        [$script, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        file_put_contents(self::$work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            "\$_POST = [];\n" .
            "session_save_path(" . var_export(self::$tree . '/.sessions', true) . "); session_id(" . var_export($s['id'], true) . "); session_start();\n" .
            "if (!isset(\$_SESSION['session'])) \$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$s['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$s['authversion']}, 'auth_privileges' => {$s['superuser']}];\n" .
            "mt_srand(20261101);\n" .
            "require '$script';\n");
        [$code, $out, $err] = self::process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'session.save_path=' . self::$tree . '/.sessions', self::$work . '/page.php'], self::$tree);
        self::assertSame(0, $code, $url . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $url);
        self::assertStringContainsString('</html>', $out, $url . ': ' . substr($out, 0, 2000));
        if (getenv('RESURRECTION_POPUP_RENDER_DUMP')) {
            file_put_contents(getenv('RESURRECTION_POPUP_RENDER_DUMP') . '/' . preg_replace('/[^a-z0-9]+/i', '-', $url) . '.html', $out);
        }
        return $out;
    }

    private function document(string $page): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString($page, LIBXML_NOERROR);
    }

    // Checks.

    /**
     * The mail tabs: real links, the current one aria-selected.
     *
     * @param array<string, string> $tabs label => link
     */
    private function assertTabs(\Dom\HTMLDocument $document, array $tabs, string $current): void
    {
        $found = [];
        foreach ($document->querySelectorAll('.rz-content nav.rz-mailtabs [role="tablist"] a[role="tab"]') as $tab) {
            $label = trim((string)$tab->textContent);
            $found[$label] = $tab->getAttribute('href');
            self::assertSame($label === $current ? 'true' : 'false', $tab->getAttribute('aria-selected'), $label);
            self::assertStringContainsString('rz-tab-button', (string)$tab->getAttribute('class'));
        }
        self::assertSame($tabs, $found);
    }

    /** No italics anywhere in the content (1.1.2's mail note left `i open to the page's end). */
    private function assertNoItalics(\Dom\HTMLDocument $document, string $label): void
    {
        self::assertNull($document->querySelector('.rz-content i, .rz-content em'), $label . ': no italics');
    }

    /** Every visible control in the content has a label (around it or naming its id), and each id is used once. */
    private function assertLabelled(\Dom\HTMLDocument $document, string $label): void
    {
        foreach ($document->querySelectorAll('.rz-content input, .rz-content select, .rz-content textarea') as $control) {
            $type = strtolower((string)$control->getAttribute('type'));
            if ($control->tagName === 'INPUT' && in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) { continue; }
            $id = (string)$control->getAttribute('id');
            $bound = $control->closest('label') !== null
                || ($id !== '' && $document->querySelector('label[for="' . addcslashes($id, '"\\') . '"]') !== null);
            self::assertTrue($bound, $label . ': ' . $control->tagName . ' ' . $control->getAttribute('name') . ' has no label');
            if ($id !== '') {
                self::assertSame(1, $document->querySelectorAll('[id="' . addcslashes($id, '"\\') . '"]')->length, $label . ': id ' . $id . ' is used once');
            }
        }
    }

    // Processes.

    /** Runs PHP code with the tree's dbconnect.php and the database libraries loaded, in a separate process. */
    private static function php(string $code): string
    {
        $preamble = 'require "dbconnect.php"; ob_start(); foreach (["dbwrapper_pdo", "constants", "datacache", "settings", "installer/fresh_install"] as $l) require "lib/" . $l . ".php";'
            . ' ob_end_clean(); $DB_USEDATACACHE = 0; db_connect($DB_HOST, $DB_USER, $DB_PASS) && db_select_db($DB_NAME) or exit(2);';
        [$exit, $out, $err] = self::process([PHP_BINARY, '-r', $preamble . ' ' . $code], self::$tree);
        if ($exit !== 0 || $err !== '') { throw new \RuntimeException("exit $exit: $err$out"); }
        return $out;
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $environment
     * @return array{0: int, 1: string, 2: string}
     */
    private static function process(array $command, string $directory, ?array $environment = null): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
        if (!is_resource($process)) { throw new \RuntimeException('proc_open failed'); }
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    /** Drops this class's install and puts back the install set aside, as it was. */
    private static function restore(): void
    {
        if (self::$owned) self::php('foreach (array_map(fn ($r) => (string)reset($r), db_query("SHOW TABLES")) as $t) db_query("DROP TABLE " . db_identifier($t));');
        self::$owned = false;
        if (self::$aside === '') { return; }
        // Each table as it was: its definition (with its next auto-increment value), then its rows, values bound.
        self::php('foreach (json_decode(file_get_contents(' . var_export(self::$aside, true) . '), true) as $table) {'
            . ' db_query($table["create"]);'
            . ' foreach (array_chunk($table["rows"], 100) as $rows) {'
            . '  $columns = array_keys($rows[0]); $one = "(" . implode(",", array_fill(0, count($columns), "?")) . ")";'
            . '  $values = []; foreach ($rows as $row) foreach ($row as $v) $values[] = $v === null ? null : base64_decode($v);'
            . '  db_query("INSERT INTO " . db_identifier($table["name"]) . " (" . implode(",", array_map("db_identifier", $columns)) . ") VALUES " . implode(",", array_fill(0, count($rows), $one)), true, $values); } }');
        self::$aside = '';
    }

    /** Saves every table of the install another test left (definition and rows) to $file, then drops them. */
    private static function setAside(string $file): void
    {
        self::php('$out = []; foreach (array_map(fn ($r) => (string)reset($r), db_query("SHOW TABLES")) as $t) {'
            . ' $create = db_query("SHOW CREATE TABLE " . db_identifier($t))[0]["Create Table"];'
            . ' $rows = array_map(fn ($row) => array_map(fn ($v) => $v === null ? null : base64_encode((string)$v), $row), db_query("SELECT * FROM " . db_identifier($t)));'
            . ' $out[] = ["name" => $t, "create" => $create, "rows" => $rows]; }'
            . ' file_put_contents(' . var_export($file, true) . ', json_encode($out, JSON_THROW_ON_ERROR)); chmod(' . var_export($file, true) . ', 0600);'
            . ' foreach ($out as $table) db_query("DROP TABLE " . db_identifier($table["name"]));');
        self::$aside = $file;
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { self::remove($path . '/' . $entry); }
        }
        rmdir($path);
    }
}
