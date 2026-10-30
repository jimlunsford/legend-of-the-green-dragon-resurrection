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
    /** A pinned game clock: the epoch the installer writes (a UTC midnight), no offset, four game days a day. */
    private const array CLOCK = ['game_epoch' => '2026-01-01 00:00:00 +0000', 'gameoffsetseconds' => '0', 'daysperday' => '4'];

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
            // The game's own default, as a fresh install stores it.
            self::assertSame('resurrection.htm', $this->php($tree, 'echo getsetting("defaultskin", "unset");'));

            // The clock the footer's countdown is read from, pinned for the test and restored afterwards.
            $clock = json_decode($this->php($tree, 'echo json_encode(db_query("SELECT setting, value FROM settings WHERE setting IN (?, ?, ?)", true, ["game_epoch", "gameoffsetseconds", "daysperday"]));'), true);
            foreach (self::CLOCK as $name => $value) {
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
            self::assertStringContainsString('<form action=\'login.php\' method=\'POST\'>', $home);
            self::assertStringContainsString('<input name="name" id="name"', $home);
            self::assertStringContainsString('class="rz-navitem nav"', $home);
            // Signed out, the Credits link is always there: about.php allows anonymous visits.
            self::assertSame(1, substr_count($home, '<a href="about.php" class="rz-credits">Credits</a>'));
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
            // The village scene; the default race has no portrait of its own: the unknown one, its alt the race as the
            // character panel shows it.
            $this->assertScene($village, 'village', $villagename);
            $name = $this->php($tree, 'echo preg_replace("/`./", "", db_query("SELECT name FROM accounts WHERE acctid=?", true, [' . $player . '])[0]["name"]);');
            $this->assertPortrait($village, 'unknown', 'Horrible Gelatinous Blob', $name);

            // Unread and read mail: the badge shows the unread count, the label the full counts, as the table holds them.
            $this->php($tree, 'foreach ([0, 0, 1] as $seen) db_query("INSERT INTO mail (msgfrom, msgto, subject, body, sent, seen) VALUES (0, ?, ?, ?, ?, ?)", true, [' . $player . ', "Render test", "Render test body", date("Y-m-d H:i:s"), $seen]);');
            $counts = $this->php($tree, '$r = db_query("SELECT SUM(seen=0) AS unread, SUM(seen=1) AS seen FROM mail WHERE msgto=?", true, [' . $player . ']); echo $r[0]["unread"], ",", $r[0]["seen"];');
            self::assertSame('2,1', $counts);
            // An MoTD item the player has not read: the MoTD link is marked; no window opens.
            $motd = (int)$this->php($tree, 'db_query("INSERT INTO motd (motdtitle, motdbody, motddate, motdtype, motdauthor) VALUES (?, ?, ?, 0, 0)", true, ["Render test", "Render test MoTD", date("Y-m-d H:i:s")]); echo db_insert_id();');
            self::assertGreaterThan(0, $motd);
            // Signed in again on the village, as before the first visit (that visit replaced the allowed navs), with an
            // MoTD last read in 2000.
            // A dwarf now: the dwarf's portrait.
            $this->php($tree, 'db_query("UPDATE accounts SET allowednavs=?, laston=?, lasthit=?, lastmotd=?, race=? WHERE acctid=?", true, [serialize(["village.php" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), "2000-01-01 00:00:00", "Dwarf", ' . $player . ']);');
            $village = $this->render($tree, $work, 'village.php', $session);
            $this->assertCleanPage($village, 'village.php');
            $this->assertMailLink($village, 2, 1);
            self::assertStringContainsString("<a href='motd.php' target='_blank' rel='noopener' class='rz-control rz-control-motd hotmotd' aria-label='MoTD (unread)'", $village);
            self::assertStringContainsString("<span class='rz-badge rz-badge-dot' aria-hidden='true'></span>", $village);
            self::assertStringNotContainsString("window.open('motd.php'", $village);
            $this->assertPortrait($village, 'dwarf', 'Dwarf', $name);

            // A forest fight: the forest scene, the fight below it.
            $this->php($tree, '$e = array_merge(db_query("SELECT * FROM creatures ORDER BY creatureid LIMIT 1")[0], ["creaturename" => "Scene Test Target",'
                . ' "creaturehealth" => 100000, "creatureattack" => 1, "creaturedefense" => 1, "creaturelevel" => 1, "playerstarthp" => 500, "diddamage" => 0]);'
                . ' db_query("UPDATE accounts SET alive=1, hitpoints=500, maxhitpoints=1000, turns=10, specialinc=?, badguy=?, allowednavs=?, laston=?, lasthit=? WHERE acctid=?", true,'
                . ' ["", serialize(["enemies" => [$e], "options" => ["type" => "forest"]]), serialize(["forest.php?op=fight" => true]), date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), ' . $player . ']);');
            $fight = $this->render($tree, $work, 'forest.php', $session, 'op=fight');
            $this->assertCleanPage($fight, 'forest.php?op=fight');
            self::assertStringContainsString('Scene Test Target', $fight);
            $this->assertScene($fight, 'forest', 'The Forest');
            $this->assertPortrait($fight, 'dwarf', 'Dwarf', $name);

            $mail = $this->render($tree, $work, 'mail.php', $session);
            $this->assertCleanPage($mail, 'mail.php');
            self::assertStringContainsString('<body class="rz-page rz-popup">', $mail);
            self::assertStringNotContainsString('class="rz-columns"', $mail);
            // A popup: no scene and no portrait.
            self::assertStringNotContainsString('rz-scene', $mail);
            self::assertStringNotContainsString('rz-portrait', $mail);
            self::assertStringNotContainsString('assets/scenes/', $mail);
            self::assertStringNotContainsString('assets/portraits/', $mail);

            // The newest player stored: the home page names them.
            $this->php($tree, 'savesetting("newestplayer", "' . $player . '");');
            $home = $this->render($tree, $work, 'home.php', null);
            $this->assertCleanPage($home, 'home.php');
            self::assertStringContainsString('The newest resident of the realm is: ', $home);
            self::assertSame(1, preg_match('#The newest resident of the realm is: </span><span class=\'colLtWhite\'>([^<]*)</span>#', $home, $resident), $home);
            self::assertSame($name, $resident[1]);
        } finally {
            $cleanup = [];
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
        self::assertSame(array_merge($motd > 0 ? ['motd removed'] : [], is_array($newest) && !$installed ? ['newest restored'] : [], $player > 0 ? ['removed'] : [],
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

    /** The race portrait: the race's file, the race as alt text, beside the name and race, in the panel and the phone header. */
    private function assertPortrait(string $page, string $file, string $race, string $name): void
    {
        $portrait = '<div class="rz-card rz-portrait"><img class="rz-portrait-img" src="assets/portraits/race/' . $file . '.svg" alt="'
            . htmlspecialchars($race, ENT_QUOTES) . '" width="200" height="240"><div class="rz-portrait-text"><span class="rz-portrait-name">'
            . htmlspecialchars($name, ENT_QUOTES) . '</span><span class="rz-portrait-race">' . htmlspecialchars($race, ENT_QUOTES) . '</span></div></div>' . "\n";
        self::assertNotSame('', trim($name));
        self::assertStringContainsString('<div class="rz-header-portrait">' . $portrait . '</div>', $page, $file);
        self::assertStringContainsString('<div class="rz-character-inner">' . $portrait, $page, $file);
        self::assertSame(2, substr_count($page, '<img class="rz-portrait-img"'), $file);
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
    private function render(string $tree, string $work, string $script, ?array $session, string $query = ''): string
    {
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        $signin = $session === null ? '' :
            "session_save_path(" . var_export($tree . '/.sessions', true) . "); session_start();\n" .
            "\$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$session['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$session['authversion']}, 'auth_privileges' => 0];\n";
        file_put_contents($work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            $signin .
            "require '$script';\n");
        [$code, $out, $err] = $this->process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
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
