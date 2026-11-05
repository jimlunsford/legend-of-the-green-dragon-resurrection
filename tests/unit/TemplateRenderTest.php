<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The Resurrection UI's template (templates/resurrection.htm), the default since Phase 5 task 3, renders real pages:
 * a signed-out home page, a signed-in village page and the mail popup (docs/PHASE5-TASK3-TEMPLATE-20261030.md). Each
 * is rendered by the PHP CLI with output_buffering=0, as OutputBufferingTest does, and must finish cleanly, print no
 * template warning, start with the template's own doctype, and carry the copyright line exactly once.
 *
 * Phase 5 task 4 (docs/PHASE5-TASK4-HEADER-FOOTER-20261030.md) adds: the Resurrection attribution line exactly once on
 * each page and popup; the mail link's unread badge and its full "N new, M old" label, from the mail table; the MoTD
 * link's unread mark in place of the automatic popup; the footer's time to the next new day against
 * secondstonextgameday() on a pinned clock; the Credits link only where about.php is allowed; and the village's page
 * title shown once, not repeated as a heading in the page.
 *
 * Phase 5 task 5 (docs/PHASE5-TASK5-IMAGES-20261030.md) adds the art: the village scene on the signed-out home page and
 * the village, the forest scene in a forest fight, each with its alt text, at the top of the content card; the
 * player's race portrait (the unknown one for the default race, the dwarf's for a dwarf) in the character panel and the
 * phone header; no scene and no portrait in the mail popup, no portrait signed out. And the home page's newest-resident
 * line: absent with no newest player stored (1.1.2's behavior), the player's name with one.
 *
 * Phase 5 task 6 (docs/PHASE5-TASK6-NAVIGATION-20261030.md) adds the navigation: on the home page and the village each
 * nav's key badge equals its accesskey and aria-keyshortcuts, the page's key map has one entry per key going where the
 * link goes, and no inline key script is left; a forest fight, which has no nav to show, renders without the nav column.
 *
 * Phase 5 task 7 (docs/PHASE5-TASK7-CHARACTER-PANEL-20261030.md) moves the panel's portrait into its header card, beside
 * the name; the phone header keeps its own. A player with no race chosen yet is named "Not chosen yet".
 *
 * Phase 5 task 8 (docs/PHASE5-TASK8-PHONE-20261031.md) adds the phone's pieces, shown by the stylesheet below 600px:
 * the compact character header (portrait, name, "Level 7 Dwarf, 14 turns, 1,247 gold", the mail link, the HP and XP
 * bars; torments, favor and soulpoints when dead), only signed in; the More section (MoTD, Petition for Help, Credits
 * where allowed); and the bottom tab bar, whose tabs are anchors on the page, each target there once: Go only with the
 * nav column, Character only signed in, Talk only where commentary rendered, More always. The content comes before the
 * character panel, More and the footer. The mail popup has none of them.
 *
 * Phase 5 task 15 (docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md) removes the 1.1.2 skins and skin selection: a fresh
 * install stores no defaultskin or homeskinselect setting; a request carrying an old template cookie (jade.htm), with a
 * stored defaultskin of jade.htm and homeskinselect of 1 as an older install has them, renders the Resurrection UI,
 * signed out and signed in; the home page has no skin chooser. (Until task 15 the village was rendered in jade.htm too,
 * by its cookie, and had none of the phone's pieces.)
 *
 * The Phase 5 close-out (docs/PHASE5-CLOSEOUT-20261105.md) adds the safe frame: in a tree whose
 * templates/resurrection.htm lacks {copyright}, lacks the attribution line, has a changed attribution link, or is
 * missing, the home page, the MoTD popup and the village render in the built-in safe frame with the 1.1.2 credit line,
 * the license block and the attribution line each exactly once; the refusal is written to the error log once; a
 * superuser sees the notice with its reasons, a player and a signed-out visitor do not. The valid template renders with
 * no safe frame, no notice and nothing logged.
 *
 * The test does not depend on the order it runs in: an installed database is used as it is; an empty one is installed
 * here with scripts/install.php --modules=none and emptied again afterwards. The player it creates, its mail, the MoTD
 * item and the clock settings it changes are removed or restored.
 */
final class TemplateRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team';
    private const string ATTRIBUTION = '<p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a>'
        . ' Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>';
    private const string CARD = '<div class="rz-card rz-content">';
    /** lib/constants.php's SU_EDIT_CONFIG, which this process does not load. */
    private const int SU_EDIT_CONFIG = 128;

    public function testTheDefaultTemplateRendersAPageASignedInPageAndAPopup(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $root = (string)realpath(self::ROOT);
        $work = sys_get_temp_dir() . '/resurrection-template-render-' . bin2hex(random_bytes(6));
        $tree = $work . '/tree';
        mkdir($tree . '/.sessions', 0700, true);
        $installed = false;
        $player = 0;
        $motd = 0;
        $clock = null;
        $newest = null;
        $skinrows = false;
        try {
            foreach (scandir($root) ?: [] as $entry) {
                if (in_array($entry, ['.', '..', '.git', 'dbconnect.php'], true)) { continue; }
                symlink($root . '/' . $entry, $tree . '/' . $entry);
            }
            file_put_contents($tree . '/dbconnect.php', LegacyConfig::render(['DB_HOST' => $host,
                'DB_USER' => (string)getenv('RESURRECTION_TEST_DB_USER'), 'DB_PASS' => (string)getenv('RESURRECTION_TEST_DB_PASSWORD'),
                'DB_NAME' => 'resurrection_test', 'DB_PREFIX' => '', 'DB_USEDATACACHE' => 0, 'DB_DATACACHEPATH' => '']));
            chmod($tree . '/dbconnect.php', 0600);
            $state = $this->php($tree, 'echo resurrection_install_state();');
            self::assertContains($state, ['empty', 'installed'], 'The disposable test database must be empty or installed.');
            if ($state === 'empty') {
                [$code, $out, $err] = $this->process([PHP_BINARY, 'scripts/install.php', '--modules=none', $tree . '/dbconnect.php'], $root,
                    ['RESURRECTION_ADMIN_LOGIN' => 'TemplateRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic template render password'] + getenv());
                self::assertSame(0, $code, $err . $out);
                $installed = true;
            }
            // No skin settings: a fresh install stores neither (1.1.2 stored defaultskin "jade.htm" and homeskinselect 1;
            // tasks 3 to 14 stored defaultskin "resurrection.htm"). No getsetting() here: it would store its default.
            self::assertSame('0', $this->php($tree, 'echo count(db_query("SELECT setting FROM settings WHERE setting IN (?, ?)", true, ["defaultskin", "homeskinselect"]));'));

            // The clock the footer's countdown is read from, pinned for the test and restored afterwards.
            $clock = json_decode($this->php($tree, 'echo json_encode(db_query("SELECT setting, value FROM settings WHERE setting IN (?, ?, ?)", true, ["game_epoch", "gameoffsetseconds", "daysperday"]));'), true);
            // Until Phase 5 task 11 the pinned epoch was a UTC midnight (2026-01-01), which kept the real 00:00, 06:00, 12:00
            // and 18:00 UTC boundaries: a run that crossed one sent village.php to a new day. GameClock starts the game
            // day a minute ago, as the web shards do, and leaves a whole game day of headroom.
            foreach (GameClock::settings() as $name => $value) {
                $this->php($tree, 'savesetting(' . var_export($name, true) . ', ' . var_export($value, true) . ');');
            }

            // No newest player stored, as on a fresh install: the setting is restored afterwards.
            $newest = json_decode($this->php($tree, 'echo json_encode(db_query("SELECT value FROM settings WHERE setting=?", true, ["newestplayer"]));'), true);
            $this->php($tree, 'savesetting("newestplayer", "");');
            $villagename = $this->php($tree, 'echo getsetting("villagename", LOCATION_FIELDS);');

            $before = $this->newdayText($tree);
            $home = $this->render($tree, $work, 'home.php', null);
            $after = $this->newdayText($tree);
            $this->assertCleanPage($home, 'home.php');
            // No skin chooser: 1.1.2's "Choose a different display skin" form posted a template field back to home.php.
            self::assertStringNotContainsString('display skin', $home);
            self::assertStringNotContainsString("name='template'", $home);
            self::assertStringNotContainsString("action='home.php'", $home);
            self::assertStringContainsString('<form action=\'login.php\' method=\'POST\'>', $home);
            self::assertStringContainsString('<input name="name" id="name"', $home);
            self::assertStringContainsString('class="rz-navitem nav"', $home);
            // Signed out, the Credits link is always there: about.php allows anonymous visits. Since Phase 5 task 8 it is
            // in the footer and again in the More section (the phone's), so twice in the page (once before).
            self::assertSame(2, substr_count($home, '<a href="about.php" class="rz-credits">Credits</a>'));
            self::assertSame(1, substr_count($this->more($home), '<a href="about.php" class="rz-credits">Credits</a>'));
            // The time to the next new day, as text, equal to secondstonextgameday() on the pinned clock.
            self::assertMatchesRegularExpression('/^Next new day in [0-5]h [0-5]?[0-9]m$/', $before);
            self::assertSame(1, preg_match('#<span class="rz-newday">([^<]*)</span>#', $home, $newday));
            self::assertContains($newday[1], array_unique([$before, $after]));
            // Signed out: no mail link; MoTD and Petition are real links that open in a new tab, the popup only an
            // enhancement, and no MoTD window is opened by the page.
            self::assertStringNotContainsString("href='mail.php'", $home);
            self::assertStringContainsString("<a href='motd.php' target='_blank' rel='noopener' class='rz-control rz-control-motd motd' aria-label='MoTD' title='MoTD'", $home);
            self::assertStringContainsString("<a href='petition.php' target='_blank' rel='noopener' class='rz-control rz-control-petition motd' aria-label='Petition for Help'", $home);
            self::assertStringNotContainsString("window.open('motd.php'", $home);
            // The village scene, the place's name its alt text; signed out, no portrait. No newest player: no line.
            $this->assertScene($home, 'village', $villagename);
            self::assertStringNotContainsString('rz-portrait', $home);
            self::assertStringNotContainsString('newest resident', $home);
            $this->assertNavs($home, 'home.php');
            // The phone: signed out, the wordmark and no character header; tabs Go and More.
            self::assertStringContainsString('<span class="rz-wordmark-title">Legend of the Green Dragon</span>', $home);
            $this->assertPhone($home, ['rz-nav' => 'Go', 'rz-more' => 'More'], null);
            foreach (["href='motd.php'", "href='petition.php'"] as $link) {
                self::assertStringContainsString($link, $this->more($home), $link);
            }

            // A signed-in player in the village: the account the game creates, signed in through the session the
            // forced-navigation check reads (do_forced_nav()).
            $player = (int)$this->php($tree, 'require_once "lib/accounts.php"; $id = resurrection_create_account("TemplateRender", "Synthetic template render player password");'
                . ' db_query("UPDATE accounts SET loggedin=1, laston=?, lasthit=? WHERE acctid=?", true, [date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), $id]); echo $id;');
            self::assertGreaterThan(0, $player);
            $version = (int)$this->php($tree, 'echo db_fetch_assoc(db_query("SELECT authversion FROM accounts WHERE acctid=?", true, [' . $player . ']))["authversion"];');
            $session = ['acctid' => $player, 'authversion' => $version];

            $village = $this->render($tree, $work, 'village.php', $session);
            $this->assertCleanPage($village, 'village.php');
            self::assertStringContainsString('<section class="rz-card rz-statsection">', $village);
            self::assertStringContainsString('class="rz-navitem nav"', $village);
            // No mail: the envelope link without a badge, its label the full sentence.
            $this->assertMailLink($village, 0, 0);
            // The village allows no about.php nav, so there is no Credits link; the 1.1.2 credit line stays.
            self::assertStringNotContainsString('rz-credits', $village);
            self::assertStringNotContainsString('href="about.php', $village);
            // The page title "<Village> Square" is shown once, as the page title: not again as a heading in the page.
            $square = $this->php($tree, 'echo getsetting("villagename", LOCATION_FIELDS) . " Square";');
            self::assertSame(1, substr_count($village, '<title>' . $square . '</title>'));
            self::assertSame(1, substr_count($village, '<h1 class="rz-title">' . $square . '</h1>'));
            $body = substr($village, (int)strpos($village, '</head>'));
            self::assertSame(1, substr_count($body, $square), 'The village title is repeated in the page');
            // The village scene; no race chosen yet: the unknown portrait, its alt the race as the character panel shows
            // it, "Not chosen yet" since Phase 5 task 7 (RACE_UNKNOWN, "Horrible Gelatinous Blob", before).
            $this->assertScene($village, 'village', $villagename);
            $name = $this->php($tree, 'echo preg_replace("/`./", "", db_query("SELECT name FROM accounts WHERE acctid=?", true, [' . $player . '])[0]["name"]);');
            $this->assertPortrait($village, 'unknown', 'Not chosen yet', $name);
            $this->assertNavs($village, 'village.php');
            // The phone: the village has its navs, the panel and commentary, so all four tabs; no Credits in More.
            $this->assertPhone($village, ['rz-nav' => 'Go', 'rz-character' => 'Character', 'rz-talk' => 'Talk', 'rz-more' => 'More'], 'unknown');
            self::assertStringNotContainsString('rz-credits', $this->more($village));

            // Unread and read mail: the badge shows the unread count, the label the full counts, as the table holds them.
            $this->php($tree, 'foreach ([0, 0, 1] as $seen) db_query("INSERT INTO mail (msgfrom, msgto, subject, body, sent, seen) VALUES (0, ?, ?, ?, ?, ?)", true, [' . $player . ', "Render test", "Render test body", date("Y-m-d H:i:s"), $seen]);');
            $counts = $this->php($tree, '$r = db_query("SELECT SUM(seen=0) AS unread, SUM(seen=1) AS seen FROM mail WHERE msgto=?", true, [' . $player . ']); echo $r[0]["unread"], ",", $r[0]["seen"];');
            self::assertSame('2,1', $counts);
            // An MoTD item the player has not read: the MoTD link is marked; no window opens.
            $motd = (int)$this->php($tree, 'db_query("INSERT INTO motd (motdtitle, motdbody, motddate, motdtype, motdauthor) VALUES (?, ?, ?, 0, 0)", true, ["Render test", "Render test MoTD", date("Y-m-d H:i:s")]); echo db_insert_id();');
            self::assertGreaterThan(0, $motd);
            // Signed in again on the village, as before the first visit (that visit replaced the allowed navs), with an
            // MoTD last read in 2000.
            // A dwarf now: the dwarf's portrait. Level 7, 14 turns, 1,247 gold, 63 of 70 hitpoints, 5,025 experience of
            // the 6,641 level 7 needs with no dragon kills (lib/experience.php): the phone header's numbers.
            $this->php($tree, 'db_query("UPDATE accounts SET allowednavs=?, laston=?, lasthit=?, lastmotd=?, race=?, level=7, turns=14, gold=1247, hitpoints=63, maxhitpoints=70, experience=5025, dragonkills=0 WHERE acctid=?", true, [serialize(["village.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), "2000-01-01 00:00:00", "Dwarf", ' . $player . ']);');
            $village = $this->render($tree, $work, 'village.php', $session);
            $this->assertCleanPage($village, 'village.php');
            $this->assertMailLink($village, 2, 1);
            self::assertStringContainsString("<a href='motd.php' target='_blank' rel='noopener' class='rz-control rz-control-motd hotmotd' aria-label='MoTD (unread)'", $village);
            self::assertStringContainsString("<span class='rz-badge rz-badge-dot' aria-hidden='true'></span>", $village);
            self::assertStringNotContainsString("window.open('motd.php'", $village);
            $this->assertPortrait($village, 'dwarf', 'Dwarf', $name);
            $this->assertPhone($village, ['rz-nav' => 'Go', 'rz-character' => 'Character', 'rz-talk' => 'Talk', 'rz-more' => 'More'], 'dwarf',
                [$name, 'Level 7 Dwarf &middot; 14 turns &middot; 1,247 gold', [['health', 'hitpoints', 'HP', '63 / 70', '90'], ['experience', 'experience', 'XP', '5025 / 6641', '75.7']]]);

            // An old skin's cookie (jade.htm, as 1.1.2's skin chooser set it), and the skin settings an older install
            // stored (defaultskin jade.htm, homeskinselect 1): nothing reads them, so the page is the Resurrection UI, the
            // same page as without them. The two rows are removed afterwards.
            $skinrows = true;
            $this->php($tree, 'savesetting("defaultskin", "jade.htm"); savesetting("homeskinselect", "1");');
            self::assertSame('jade.htm,1', $this->php($tree, '$r = db_query("SELECT setting, value FROM settings WHERE setting IN (?, ?) ORDER BY setting", true, ["defaultskin", "homeskinselect"]); echo $r[0]["value"], ",", $r[1]["value"];'));
            $this->php($tree, 'db_query("UPDATE accounts SET allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true, [serialize(["village.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
            $old = $this->render($tree, $work, 'village.php', $session, '', 'jade.htm');
            $this->assertCleanPage($old, 'village.php with a jade.htm cookie');
            self::assertStringContainsString('<body class="rz-page">', $old);
            self::assertStringNotContainsString('jade', $old);
            $this->assertPhone($old, ['rz-nav' => 'Go', 'rz-character' => 'Character', 'rz-talk' => 'Talk', 'rz-more' => 'More'], 'dwarf',
                [$name, 'Level 7 Dwarf &middot; 14 turns &middot; 1,247 gold', [['health', 'hitpoints', 'HP', '63 / 70', '90'], ['experience', 'experience', 'XP', '5025 / 6641', '75.7']]]);
            $oldhome = $this->render($tree, $work, 'home.php', null, '', 'jade.htm');
            $this->assertCleanPage($oldhome, 'home.php with a jade.htm cookie');
            self::assertStringNotContainsString('jade', $oldhome);
            self::assertStringNotContainsString('display skin', $oldhome);
            $this->assertScene($oldhome, 'village', $villagename);
            // The stored defaultskin alone, no cookie: the same.
            $stored = $this->render($tree, $work, 'home.php', null);
            $this->assertCleanPage($stored, 'home.php with defaultskin jade.htm stored');
            self::assertStringNotContainsString('jade', $stored);
            $this->php($tree, 'db_query("DELETE FROM settings WHERE setting IN (?, ?)", true, ["defaultskin", "homeskinselect"]);');
            $skinrows = false;

            // The safe frame (Phase 5 close-out, docs/PHASE5-CLOSEOUT-20261105.md). The copyright enforcement
            // (prepare_template(): the required tags, {copyright} named by the XOR of $y2 and $z2) still refuses a template
            // without {copyright}, and so does the new check a template without the attribution line, both links exact,
            // in its footer and popup footer; a missing template is refused too. Each refused page is the safe frame
            // (\Resurrection\Ui\SafeFrame) with the 1.1.2 credit line, the license block and the attribution line
            // once each; the reasons go to the error log, and into the page for a superuser only. A copy of the tree with
            // its own templates/ and every other file the same.
            $broken = $work . '/broken';
            mkdir($broken . '/templates', 0700, true);
            mkdir($broken . '/.sessions', 0700);
            foreach (scandir($root) ?: [] as $entry) {
                if (in_array($entry, ['.', '..', '.git', 'dbconnect.php', 'templates'], true)) { continue; }
                symlink($root . '/' . $entry, $broken . '/' . $entry);
            }
            copy($tree . '/dbconnect.php', $broken . '/dbconnect.php');
            $source = (string)file_get_contents($root . '/templates/resurrection.htm');
            // In the footer and the popup footer.
            self::assertSame(2, substr_count($source, '{copyright}'));
            self::assertSame(2, substr_count($source, self::ATTRIBUTION));
            $log = $work . '/php-error.log';
            $variants = [
                'no {copyright}' => str_replace('{copyright}', '', $source),
                'no attribution line' => str_replace(self::ATTRIBUTION, '', $source),
                'a changed attribution link' => preg_replace('#https://phoenix233\.com#', 'https://phoenix233.example', $source, 1),
                'no template file' => null,
            ];
            $reasons = [
                'no {copyright}' => ['You do not have {copyright} defined in either your header or footer', 2],
                'no attribution line' => ['You do not have the Resurrection attribution line, with both its links, in both your footer and popupfoot', 1],
                'a changed attribution link' => ['You do not have the Resurrection attribution line, with both its links, in both your footer and popupfoot', 1],
                'no template file' => ['The template file templates/resurrection.htm is missing or cannot be read', 1],
            ];
            foreach ($variants as $label => $text) {
                // No @: an earlier test's error handler may still throw on a suppressed warning.
                if (is_file($broken . '/templates/resurrection.htm')) { unlink($broken . '/templates/resurrection.htm'); }
                if ($text !== null) { file_put_contents($broken . '/templates/resurrection.htm', $text); }
                // A player's (signed-out) page, a popup, and a superuser's page.
                if (is_file($log)) { unlink($log); }
                $page = $this->render($broken, $work, 'home.php', null, '', null, $log);
                $this->assertSafeFrame($page, 'home.php, ' . $label, false);
                self::assertStringNotContainsString('errors in your template page', $page, $label);
                [$reason, $times] = $reasons[$label];
                $logged = is_file($log) ? (string)file_get_contents($log) : '';
                self::assertSame(1, substr_count($logged, 'Resurrection: templates/resurrection.htm was refused; the safe frame is used: '), $label);
                // Twice for {copyright}, as in 1.1.2: once for "copyright" in the list of tags that must appear, once
                // for the tag the check adds to that list as the XOR of $y2 and $z2. Nothing else is missing.
                self::assertSame($times, substr_count($logged, $reason), $label);
                self::assertSame($times, substr_count($logged, 'You do not have ') + substr_count($logged, 'The template file '), $label . ': only that is missing');
                $popup = $this->render($broken, $work, 'motd.php', null, '', null, $log);
                $this->assertSafeFrame($popup, 'motd.php, ' . $label, true);
                self::assertStringNotContainsString('errors in your template page', $popup, $label);
                $this->php($tree, 'db_query("UPDATE accounts SET superuser=?, allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true, [' . self::SU_EDIT_CONFIG . ', serialize(["village.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
                $staff = $this->render($broken, $work, 'village.php', $session, '', null, $log, self::SU_EDIT_CONFIG);
                $this->php($tree, 'db_query("UPDATE accounts SET superuser=0 WHERE acctid=?", true, [' . $player . ']);');
                $this->assertSafeFrame($staff, 'village.php as a superuser, ' . $label, false);
                self::assertSame(1, substr_count($staff, '<div class="rz-template-notice" role="alert"><b>You have one or more errors in your template page!</b><br>'), $label);
                // The braces as entities: page_header() would otherwise strip "{copyright}" as an unused tag.
                self::assertSame($times, substr_count($staff, str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'))), $label);
                // Inside the page: after the frame's content card opens.
                self::assertGreaterThan(strpos($staff, self::CARD), strpos($staff, 'rz-template-notice'), $label);
            }
            // The same tree with the template restored renders the template itself: no safe frame, no notice, nothing
            // logged, for a player or a superuser.
            file_put_contents($broken . '/templates/resurrection.htm', $source);
            if (is_file($log)) { unlink($log); }
            $restored = $this->render($broken, $work, 'home.php', null, '', null, $log);
            $this->assertCleanPage($restored, 'home.php in the copied tree');
            self::assertStringNotContainsString('rz-safe-frame', $restored);
            $this->php($tree, 'db_query("UPDATE accounts SET superuser=?, allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true, [' . self::SU_EDIT_CONFIG . ', serialize(["village.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
            $staff = $this->render($broken, $work, 'village.php', $session, '', null, $log, self::SU_EDIT_CONFIG);
            $this->php($tree, 'db_query("UPDATE accounts SET superuser=0 WHERE acctid=?", true, [' . $player . ']);');
            $this->assertCleanPage($staff, 'village.php as a superuser in the copied tree');
            self::assertStringNotContainsString('rz-safe-frame', $staff);
            self::assertStringNotContainsString('rz-template-notice', $staff);
            self::assertFalse(is_file($log) && filesize($log) > 0, 'nothing logged: ' . (is_file($log) ? (string)file_get_contents($log) : ''));

            // A forest fight: the forest scene, the fight below it.
            $this->php($tree, '$e = array_merge(db_query("SELECT * FROM creatures ORDER BY creatureid LIMIT 1")[0], ["creaturename" => "Scene Test Target",'
                . ' "creaturehealth" => 100000, "creatureattack" => 1, "creaturedefense" => 1, "creaturelevel" => 1, "playerstarthp" => 500, "diddamage" => 0]);'
                . ' db_query("UPDATE accounts SET alive=1, hitpoints=500, maxhitpoints=1000, turns=10, specialinc=?, badguy=?, allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true,'
                . ' ["", serialize(["enemies" => [$e], "options" => ["type" => "forest"]]), serialize(["forest.php?op=fight" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
            $fight = $this->render($tree, $work, 'forest.php', $session, 'op=fight');
            $this->assertCleanPage($fight, 'forest.php?op=fight');
            self::assertStringContainsString('Scene Test Target', $fight);
            $this->assertScene($fight, 'forest', 'The Forest');
            // No nav to show: no nav column, no empty card, no key map; the content takes the space. (The page's one
            // <nav> is the phone's tab bar since Phase 5 task 8, without a Go tab; checked below.)
            self::assertSame(1, substr_count($fight, '<nav'));
            foreach (['<nav class="rz-nav"', 'id="rz-nav"', 'rz-nav-panel', 'rz-navitem', 'rz-keys', 'assets/ui/keys.js'] as $absent) {
                self::assertStringNotContainsString($absent, $fight, $absent);
            }
            self::assertStringContainsString('<div class="rz-columns">' . "\n  \n" . '  <main class="rz-main" id="rz-content">', $fight);
            $this->assertPortrait($fight, 'dwarf', 'Dwarf', $name);
            // The phone: no nav column and no commentary, so no Go and no Talk.
            $this->assertPhone($fight, ['rz-character' => 'Character', 'rz-more' => 'More'], 'dwarf',
                [$name, 'Level 7 Dwarf &middot; 10 turns &middot; 1,247 gold', [['health', 'hitpoints', 'HP', '500 / 1000', '50'], ['experience', 'experience', 'XP', '5025 / 6641', '75.7']]]);

            // Dead, in the graveyard: torments and favor in place of turns and gold, soulpoints (of level * 5 + 50) in
            // place of hitpoints.
            $this->php($tree, 'db_query("UPDATE accounts SET alive=0, hitpoints=0, soulpoints=62, gravefights=6, deathpower=40, badguy=?, allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true,'
                . ' ["", serialize(["graveyard.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
            $graveyard = $this->render($tree, $work, 'graveyard.php', $session);
            $this->assertCleanPage($graveyard, 'graveyard.php');
            $this->assertPhone($graveyard, ['rz-nav' => 'Go', 'rz-character' => 'Character', 'rz-more' => 'More'], 'dwarf',
                [$name, 'Level 7 Dwarf &middot; 6 torments &middot; 40 favor', [['soul', 'soulpoints', 'Soul', '62 / 85', '72.9'], ['experience', 'experience', 'XP', '5025 / 6641', '75.7']]]);
            $this->php($tree, 'db_query("UPDATE accounts SET alive=1, hitpoints=63 WHERE acctid=?", true, [' . $player . ']);');

            $mail = $this->render($tree, $work, 'mail.php', $session);
            $this->assertCleanPage($mail, 'mail.php');
            self::assertStringContainsString('<body class="rz-page rz-popup">', $mail);
            self::assertStringNotContainsString('class="rz-columns"', $mail);
            // A popup: no scene and no portrait.
            self::assertStringNotContainsString('rz-scene', $mail);
            self::assertStringNotContainsString('rz-portrait', $mail);
            self::assertStringNotContainsString('assets/scenes/', $mail);
            self::assertStringNotContainsString('assets/portraits/', $mail);
            // A popup: no phone header, no More section, no tab bar.
            foreach (['rz-tabbar', 'rz-phonehead', 'rz-more', 'assets/ui/tabs.js'] as $absent) {
                self::assertStringNotContainsString($absent, $mail, 'mail.php: ' . $absent);
            }

            // The newest player stored: the home page names them.
            $this->php($tree, 'savesetting("newestplayer", "' . $player . '");');
            $home = $this->render($tree, $work, 'home.php', null);
            $this->assertCleanPage($home, 'home.php');
            self::assertStringContainsString('The newest resident of the realm is: ', $home);
            self::assertSame(1, preg_match('#The newest resident of the realm is: </span><span class=\'colLtWhite\'>([^<]*)</span>#', $home, $resident), $home);
            self::assertSame($name, $resident[1]);
        } finally {
            $cleanup = [];
            if ($skinrows) {
                $cleanup[] = $this->php($tree, 'db_query("DELETE FROM settings WHERE setting IN (?, ?)", true, ["defaultskin", "homeskinselect"]); echo "skin rows removed";', false);
            }
            if ($motd > 0) {
                $cleanup[] = $this->php($tree, 'db_query("DELETE FROM motd WHERE motditem=?", true, [' . $motd . ']); echo "motd removed";', false);
            }
            if (is_array($newest) && !$installed) {
                $restore = 'db_query("DELETE FROM settings WHERE setting=?", true, ["newestplayer"]);';
                foreach ($newest as $row) {
                    $restore .= ' db_query("INSERT INTO settings (setting, value) VALUES (?, ?)", true, ["newestplayer", ' . var_export($row['value'], true) . ']);';
                }
                $cleanup[] = $this->php($tree, $restore . ' echo "newest restored";', false);
            }
            if ($player > 0) {
                $cleanup[] = $this->php($tree, 'db_query("DELETE FROM mail WHERE msgto=?", true, [' . $player . ']); foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $player . ']); echo "removed";', false);
            }
            if (is_array($clock) && !$installed) {
                $restore = 'db_query("DELETE FROM settings WHERE setting IN (?, ?, ?)", true, ["game_epoch", "gameoffsetseconds", "daysperday"]);';
                foreach ($clock as $row) {
                    $restore .= ' db_query("INSERT INTO settings (setting, value) VALUES (?, ?)", true, [' . var_export($row['setting'], true) . ', ' . var_export($row['value'], true) . ']);';
                }
                $cleanup[] = $this->php($tree, $restore . ' echo "clock restored";', false);
            }
            // Only an install this test made in the disposable database: empty it again for the tests that follow.
            if ($installed) {
                $cleanup[] = $this->php($tree, 'foreach (array_column(db_query("SHOW TABLES"), "Tables_in_resurrection_test") as $t) db_query("DROP TABLE " . db_identifier($t)); echo resurrection_install_state();', false);
            }
            $this->remove($work);
        }
        self::assertSame(array_merge($skinrows ? ['skin rows removed'] : [], $motd > 0 ? ['motd removed'] : [], is_array($newest) && !$installed ? ['newest restored'] : [], $player > 0 ? ['removed'] : [],
            is_array($clock) && !$installed ? ['clock restored'] : [], $installed ? ['empty'] : []), $cleanup);
    }

    /** A finished page of the new template: no error, no template warning, the copyright line once. */
    private function assertCleanPage(string $page, string $script): void
    {
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $page, $script);
        self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', $page, $script);
        self::assertStringNotContainsString('Runtime error', $page, $script);
        self::assertStringNotContainsString('template part was not found', $page, $script);
        self::assertStringNotContainsString('piece was not found', $page, $script);
        self::assertStringNotContainsString('errors in your template page', $page, $script);
        self::assertSame(1, substr_count($page, self::COPYRIGHT), $script);
        self::assertSame(1, substr_count($page, 'Creative Commons License</a>'), $script);
        // The Resurrection attribution: its own line, once, after the 1.1.2 credit line.
        self::assertSame(1, substr_count($page, self::ATTRIBUTION), $script);
        self::assertSame(1, substr_count($page, 'Phoenix 2:33 LLC'), $script);
        self::assertGreaterThan(strpos($page, self::COPYRIGHT), strpos($page, self::ATTRIBUTION), $script);
        self::assertStringEndsWith("</html>\n", $page, $script);
    }

    /**
     * A page in the safe frame (Phase 5 close-out): the frame's body class; the 1.1.2 credit line, the license block and
     * the attribution line once each, the attribution after the credit line; no missing-part warning; the stylesheet
     * linked, and the frame's own small style block.
     */
    private function assertSafeFrame(string $page, string $label, bool $popup): void
    {
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">", $page, $label);
        self::assertStringContainsString($popup ? '<body class="rz-page rz-popup rz-safe-frame">' : '<body class="rz-page rz-safe-frame">', $page, $label);
        self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', $page, $label);
        self::assertStringContainsString("<style>img { max-width: 100%; height: auto }</style>\n<link rel=\"stylesheet\" href=\"assets/ui/resurrection.css\">", $page, $label);
        self::assertStringNotContainsString('template part was not found', $page, $label);
        self::assertStringNotContainsString('piece was not found', $page, $label);
        self::assertStringNotContainsString('Runtime error', $page, $label);
        self::assertSame(1, substr_count($page, self::COPYRIGHT), $label);
        self::assertSame(1, substr_count($page, 'Creative Commons License</a>'), $label);
        self::assertSame(1, substr_count($page, self::ATTRIBUTION), $label);
        self::assertGreaterThan(strpos($page, self::COPYRIGHT), strpos($page, self::ATTRIBUTION), $label);
        self::assertStringEndsWith("</html>\n", $page, $label);
    }

    /**
     * The nav column and its hotkeys: the column once; each nav's badge, accesskey and aria-keyshortcuts the same key
     * (none of them for a nav without a key); the key map, one entry per key, going where the link goes; and no inline
     * key script.
     */
    private function assertNavs(string $page, string $script): void
    {
        // The column's id is the phone tab bar's Go target (Phase 5 task 8).
        self::assertSame(1, substr_count($page, '<nav class="rz-nav" id="rz-nav" aria-label="Navigation"><div class="rz-card rz-nav-panel">'), $script);
        self::assertGreaterThan(0, preg_match_all('#<a href="([^"]*)"([^>]*) class="rz-navitem nav" [^>]*><span class="rz-key" aria-hidden="true">([^<]*)</span><span class="rz-navlabel">#', $page, $links, PREG_SET_ORDER), $script);
        self::assertSame(substr_count($page, 'class="rz-navitem nav"'), count($links), $script);
        $keyed = [];
        foreach ($links as [, $href, $attributes, $badge]) {
            preg_match('/ accesskey="([^"]*)"/', $attributes, $key);
            preg_match('/ aria-keyshortcuts="([^"]*)"/', $attributes, $shortcut);
            self::assertSame($key[1] ?? '', $badge, $href);
            self::assertSame($key[1] ?? '', $shortcut[1] ?? '', $href);
            if ($badge !== '') { $keyed[strtoupper(html_entity_decode($badge, ENT_QUOTES))] = html_entity_decode($href, ENT_QUOTES); }
        }
        self::assertNotSame([], $keyed, $script);
        self::assertSame(1, preg_match('#<script type="application/json" id="rz-keys">([^<]*)</script>\n<script src="assets/ui/keys.js" defer></script>#', $page, $json), $script);
        $map = json_decode($json[1], true);
        self::assertIsArray($map);
        self::assertSame(count($keyed), count($map), $script);
        foreach ($map as $entry) {
            self::assertArrayHasKey($entry[0], $keyed, $script);
            self::assertSame($keyed[$entry[0]], $entry[2], $entry[0]);
            self::assertContains($entry[1], ['go', 'open', 'popup'], $entry[0]);
        }
        foreach (['onkeypress', 'keyevent', "class='navhi'"] as $absent) {
            self::assertStringNotContainsString($absent, $page, $script . ': ' . $absent);
        }
    }

    /** The scene banner: first in the content card, the scene's file with the place's name as its alt text, once. */
    private function assertScene(string $page, string $scene, string $alt): void
    {
        $banner = '<div class="rz-scene"><img class="rz-scene-img" src="assets/scenes/' . $scene . '.svg" alt="'
            . htmlspecialchars($alt, ENT_QUOTES) . '" width="1200" height="320"></div>' . "\n";
        self::assertNotSame('', trim($alt));
        self::assertStringContainsString(self::CARD . $banner, $page, $scene);
        self::assertSame(1, substr_count($page, 'class="rz-scene"'), $scene);
        self::assertSame(1, substr_count($page, '<img class="rz-scene-img"'), $scene);
    }

    /** The race portrait: the race's file, the race as alt text, beside the name, in the panel's header card and the phone header. */
    private function assertPortrait(string $page, string $file, string $race, string $name): void
    {
        $portrait = '<div class="rz-card rz-portrait"><img class="rz-portrait-img" src="assets/portraits/race/' . $file . '.svg" alt="'
            . htmlspecialchars($race, ENT_QUOTES) . '" width="200" height="240"><div class="rz-portrait-text"><span class="rz-portrait-name">'
            . htmlspecialchars($name, ENT_QUOTES) . '</span><span class="rz-portrait-race">' . htmlspecialchars($race, ENT_QUOTES) . '</span></div></div>' . "\n";
        self::assertNotSame('', trim($name));
        self::assertStringContainsString('<div class="rz-header-portrait">' . $portrait . '</div>', $page, $file);
        self::assertSame(1, substr_count($page, '<img class="rz-portrait-img"'), $file);
        // The character panel's header card (Phase 5 task 7): the same file and alt text, beside the name.
        self::assertSame(1, preg_match('#<div class="rz-character-inner"><div class="rz-stats">.*?<div class="rz-statid"><img class="rz-statportrait" src="assets/portraits/race/'
            . $file . '\.svg" alt="' . preg_quote(htmlspecialchars($race, ENT_QUOTES), '#') . '" width="200" height="240">\s*<div class="rz-statid-text"><h2 class="rz-statname">(.*?)</h2>#s', $page, $card), $file);
        self::assertSame($name, trim(strip_tags($card[1])), $file);
        self::assertSame(1, substr_count($page, '<img class="rz-statportrait"'), $file);
    }

    /**
     * The phone's pieces on a page of the template (Phase 5 task 8). The tab bar once, after the footer, then its script:
     * exactly these tabs, in this order, each an anchor whose target is on the page once. The source order the stylesheet
     * keeps: the content, the character panel, More, the footer, the tab bar. The phone header: none signed out
     * ($portrait null); signed in, in the page header, the race portrait, then the name, the subtitle, the mail link and
     * the bars, each [kind, icon, label, value, fill percent], checked when $head is given. The icon (Phase 5 task 13) is
     * the panel's for the bar, a decorative 16px image before the label.
     *
     * @param array<string, string> $tabs target id => label
     * @param array{0: string, 1: string, 2: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>}|null $head
     */
    private function assertPhone(string $page, array $tabs, ?string $portrait, ?array $head = null): void
    {
        self::assertSame(1, substr_count($page, '<nav class="rz-tabbar"'));
        self::assertSame(1, preg_match('#</footer>\n<nav class="rz-tabbar" aria-label="Sections">(.*?)</nav>\n<script src="assets/ui/tabs.js" defer></script>\n</body>#s', $page, $bar), 'tab bar');
        self::assertSame(count($tabs), preg_match_all('#<a class="rz-tab" href="\#([a-z-]+)">([^<]*)</a>\n#', $bar[1], $found, PREG_SET_ORDER));
        self::assertSame($tabs, array_combine(array_column($found, 1), array_column($found, 2)));
        foreach (array_keys($tabs) as $target) {
            self::assertSame(1, substr_count($page, ' id="' . $target . '"'), $target);
        }
        if (!isset($tabs['rz-talk'])) { self::assertStringNotContainsString('id="rz-talk"', $page); }
        // Source order: content, panel, More, footer, tab bar (the nav column, before the content in the source, is
        // moved after it by the stylesheet's order below 900px).
        $at = array_map(fn (string $needle): int|false => strpos($page, $needle), ['<main class="rz-main"', '<aside class="rz-character"', '<section class="rz-more"', '<footer', '<nav class="rz-tabbar"']);
        self::assertNotContains(false, $at);
        $sorted = $at;
        sort($sorted);
        self::assertSame($sorted, $at);
        if ($portrait === null) {
            self::assertStringNotContainsString('rz-phonehead', $page);
            return;
        }
        self::assertSame(1, substr_count($page, '<div class="rz-phonehead">'));
        self::assertSame(1, preg_match('#<header class="rz-header">.*?<div class="rz-phonehead"><div class="rz-phonehead-top"><img class="rz-phonehead-img" src="assets/portraits/race/'
            . $portrait . '\.svg" alt="[^"]+" width="200" height="240">\s*<div class="rz-phonehead-id"><p class="rz-phonehead-name">([^<]*)</p><p class="rz-phonehead-sub">([^<]*)</p></div>'
            . '<div class="rz-phonehead-mail"><a href=\'mail\.php\'[^>]*class=\'rz-control rz-control-mail [^>]*>.*?</a></div></div><div class="rz-phonehead-bars">(.*?)</div></div>\s*</header>#s', $page, $phone), 'phone header');
        if ($head === null) { return; }
        self::assertSame($head[0], html_entity_decode($phone[1], ENT_QUOTES));
        self::assertSame($head[1], $phone[2]);
        preg_match_all('#<div class="rz-phonebar rz-phonebar-([a-z]+)"><div class="rz-phonebar-text"><span class="rz-phonebar-label">(?:<img class="rz-labelicon" src="assets/icons/([a-z]+)\.svg" alt="" aria-hidden="true" width="16" height="16">)?([^<]*)</span><span class="rz-phonebar-value">([^<]*)</span></div>'
            . '<div class="rz-phonebar-track" aria-hidden="true"><div class="rz-phonebar-fill" style="width: ([0-9.]+)%"></div></div></div>#', $phone[3], $bars, PREG_SET_ORDER);
        self::assertSame($head[2], array_map(fn (array $bar): array => array_slice($bar, 1), $bars));
    }

    /** The More section's links. */
    private function more(string $page): string
    {
        self::assertSame(1, preg_match('#<section class="rz-more" id="rz-more" aria-label="More">\s*<h2 class="rz-more-head">More</h2>\s*<div class="rz-more-links">(.*?)</div>\s*</section>#s', $page, $more), 'More');
        return $more[1];
    }

    /** The header's mail link: an envelope, the unread count as a badge (none at zero), the full sentence as its label. */
    private function assertMailLink(string $page, int $unread, int $read): void
    {
        $label = "Ye Olde Mail: $unread new, $read old";
        self::assertSame(1, preg_match("#<a href='mail\\.php' target='_blank' rel='noopener' class='rz-control rz-control-mail (hotmotd|motd)' aria-label='([^']*)' title='([^']*)' onclick=\"[^\"]*\"><svg class='rz-icon'[^>]*>.*?</svg><span class='rz-control-label'>Mail</span>(.*?)</a>#s", $page, $link), 'mail link');
        self::assertSame($unread > 0 ? 'hotmotd' : 'motd', $link[1]);
        self::assertSame($label, $link[2]);
        self::assertSame($label, $link[3]);
        self::assertSame($unread > 0 ? "<span class='rz-badge' aria-hidden='true'>$unread</span>" : '', $link[4], $link[0]);
    }

    /** The footer's countdown text for the clock now: secondstonextgameday() from the start of today, in hours and minutes. */
    private function newdayText(string $tree): string
    {
        return $this->php($tree, 'require_once "lib/datetime.php"; $d = gametimedetails(); $s = secondstonextgameday($d) - strtotime($d["now"]);'
            . ' printf("Next new day in %dh %dm", intdiv($s, 3600), intdiv($s % 3600, 60));');
    }

    /**
     * One GET of a page, rendered by the PHP CLI with output_buffering=0. With a session, the request is signed in as
     * that account: the session the game started at login, which do_forced_nav() checks against the account row.
     *
     * @param array{acctid: int, authversion: int}|null $session
     */
    private function render(string $tree, string $work, string $script, ?array $session, string $query = '', ?string $skin = null,
        ?string $errorLog = null, int $privileges = 0): string
    {
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        $signin = $session === null ? '' :
            "session_save_path(" . var_export($tree . '/.sessions', true) . "); session_start();\n" .
            "\$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$session['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$session['authversion']}, 'auth_privileges' => $privileges];\n";
        file_put_contents($work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            ($skin === null ? '' : "\$_COOKIE = ['template' => " . var_export($skin, true) . "];\n") .
            $signin .
            "require '$script';\n");
        [$code, $out, $err] = $this->process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            ...($errorLog === null ? [] : ['-d', 'error_log=' . $errorLog]),
            '-d', 'session.save_path=' . $tree . '/.sessions', $work . '/page.php'], $tree);
        self::assertSame(0, $code, $script . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $script);
        return $out;
    }

    /**
     * Runs PHP code with the tree's dbconnect.php and the database libraries loaded, in a separate process. The
     * libraries load lib/all_tables.php, whose trailing newline is discarded as every loader does.
     */
    private function php(string $tree, string $code, bool $assert = true): string
    {
        $preamble = 'require "dbconnect.php"; ob_start(); foreach (["dbwrapper_pdo", "constants", "datacache", "settings", "installer/fresh_install"] as $l) require "lib/" . $l . ".php";'
            . ' ob_end_clean(); $DB_USEDATACACHE = 0; db_connect($DB_HOST, $DB_USER, $DB_PASS) && db_select_db($DB_NAME) or exit(2);';
        [$exit, $out, $err] = $this->process([PHP_BINARY, '-r', $preamble . ' ' . $code], $tree);
        if ($assert) { self::assertSame(0, $exit, $err . $out); }
        return $exit === 0 && $err === '' ? $out : "exit $exit: $err$out";
    }

    /**
     * @param list<string> $command
     * @param array<string, string>|null $environment
     * @return array{0: int, 1: string, 2: string}
     */
    private function process(array $command, string $directory, ?array $environment = null): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $this->remove($path . '/' . $entry); }
        }
        rmdir($path);
    }
}
