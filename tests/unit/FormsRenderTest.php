<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * Forms and buttons on real pages (Phase 5 task 9, docs/PHASE5-TASK9-FORMS-20261031.md). In the Resurrection template:
 * each form has at most one primary button, and the forms task 9 names (create a character, bank deposit, borrow and
 * withdraw, the weapon and armor shops' buy, the inn's room, the healer's heal, commentary Add, preferences Save, mail
 * Send) exactly one; every control a player fills in has a bound label; preferences' sections are server-drawn tabs, one
 * aria-selected, each controlling its section, with no inline event handler or inline script in the page's content.
 * In a 1.1.2 skin (jade.htm, its cookie as the skin chooser sets it), every button's markup is byte for byte what
 * 5b3ca63 printed for the same page and state, and no Resurrection class appears.
 *
 * Pages are rendered by the PHP CLI as TemplateRenderTest renders them. The test does not depend on the order it runs
 * in: an installed database is used as it is; an empty one is installed here with scripts/install.php --modules=none
 * and emptied again afterwards. The player it creates and its mail are removed.
 */
final class FormsRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string LOGIN = 'FormsRender';
    private const string PASSWORD = 'Synthetic forms render player password';

    /**
     * Each page's button markup in jade.htm at 5b3ca63, in page order, for the state set here (level 7, 5,000 gold,
     * 2,000 in the bank, 30 of 70 hitpoints, no weapon or armor value).
     */
    private const array OLD_SKIN_BUTTONS = [
        'create.php' => [
            "<input type=\"submit\" class=\"button\" value=\"Create your character\">",
        ],
        'petition.php' => [
            "<input type='submit' class='button' value='Submit'>",
        ],
        'village.php' => [
            "<input type='submit' class='button' value='Add'>",
        ],
        'bank.php?op=deposit' => [
            "<input type='submit' class='button' value='Deposit'>",
        ],
        'bank.php?op=withdraw' => [
            "<input type='submit' class='button' value='Withdraw'>",
        ],
        'bank.php?op=borrow' => [
            "<input type='submit' class='button' value='Borrow'>",
        ],
        'weapons.php' => [
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n</span><span class='colLtWhite'>Rake</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Trowel</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Spade</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Adze</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Gardening Hoe</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Torch</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Pitchfork</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Shovel</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Hedge Trimmers</span>\n</button>",
        ],
        'armor.php' => [
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Fuzzy Slippers</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Flannel Pajamas</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Homespun Longjohns</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Homespun Undershirt</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Knitted Socks</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Knitted Gloves</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Old Leather Boots</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Homespun Pants</span>\n</button>",
            "<button type='submit' style='background:none;border:0;padding:0;margin:0;font:inherit;cursor:pointer;text-decoration:underline'>\n<span class='colLtWhite'>Homespun Tunic</span>\n</button>",
        ],
        'inn.php?op=room&pay=1' => [
            "<button class=\"button\">Give him 84 gold</button>",
        ],
        'healer.php?op=buy&pct=100' => [
            "<button type='submit' class='button'>\n<span class='colLtYellow'>Complete Healing</span>\n</button>",
        ],
        'prefs.php' => [
            "<input type='submit' class='button' value='Save'>",
            "<input type='submit' class='button' value='Delete Character' onClick='return confirm(\"Are you sure you wish to delete your character?\");'>",
        ],
        'mail.php?op=address' => [
            "<button type=\"submit\">Search</button>",
        ],
        'mail.php?op=write&to=' . self::LOGIN => [
            "<button type=\"submit\">Send</button>",
        ],
    ];

    /** The form, by its action, whose one primary button task 9 names on each page. */
    private const array PRIMARY = [
        'create.php' => ['create.php?op=create'],
        'village.php' => ['village.php?comment=1'],
        'bank.php?op=deposit' => ['bank.php?op=depositfinish'],
        'bank.php?op=withdraw' => ['bank.php?op=withdrawfinish'],
        'bank.php?op=borrow' => ['bank.php?op=withdrawfinish'],
        'weapons.php' => ['weapons.php?op=buy'],
        'armor.php' => ['armor.php?op=buy'],
        'inn.php?op=room&pay=1' => ['inn.php?op=room&pay=1'],
        'healer.php?op=buy&pct=100' => ['healer.php?op=buy&pct=100'],
        'prefs.php' => ['prefs.php?op=save'],
        'mail.php?op=write&to=' . self::LOGIN => ['mail.php?op=send'],
    ];

    public function testFormsHaveOnePrimaryLabelledInputsAndOldSkinsKeepTheirButtons(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $root = (string)realpath(self::ROOT);
        $work = sys_get_temp_dir() . '/resurrection-forms-render-' . bin2hex(random_bytes(6));
        $tree = $work . '/tree';
        mkdir($tree . '/.sessions', 0700, true);
        $installed = false;
        $player = 0;
        $selfdelete = null;
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
                    ['RESURRECTION_ADMIN_LOGIN' => 'FormsRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic forms render admin password'] + getenv());
                self::assertSame(0, $code, $err . $out);
                $installed = true;
            }

            // Players may delete their character (selfdelete), so preferences shows its delete button; restored afterwards.
            $selfdelete = json_decode($this->php($tree, 'echo json_encode(db_query("SELECT value FROM settings WHERE setting=?", true, ["selfdelete"]));'), true);
            $this->php($tree, 'savesetting("selfdelete", "1");');

            $player = (int)$this->php($tree, 'require_once "lib/accounts.php"; $id = resurrection_create_account(' . var_export(self::LOGIN, true) . ', ' . var_export(self::PASSWORD, true) . ');'
                . ' db_query("UPDATE accounts SET loggedin=1, level=7, gold=5000, goldinbank=2000, hitpoints=30, maxhitpoints=70, turns=10, weaponvalue=0, armorvalue=0, boughtroomtoday=0 WHERE acctid=?", true, [$id]); echo $id;');
            self::assertGreaterThan(0, $player);
            $version = (int)$this->php($tree, 'echo db_fetch_assoc(db_query("SELECT authversion FROM accounts WHERE acctid=?", true, [' . $player . ']))["authversion"];');
            $session = ['acctid' => $player, 'authversion' => $version];

            foreach (array_keys(self::OLD_SKIN_BUTTONS) as $url) {
                $signedIn = !in_array($url, ['create.php', 'petition.php'], true);
                // The Resurrection template, the default.
                $page = $this->visit($tree, $work, $url, $signedIn ? $session : null, $player, null);
                self::assertStringContainsString('<link rel="stylesheet" href="assets/ui/resurrection.css">', $page, $url);
                $document = \Dom\HTMLDocument::createFromString($page, LIBXML_NOERROR);
                $this->assertPrimaries($document, $url);
                $this->assertLabelled($document, $url);
                if ($url === 'prefs.php') { $this->assertTabs($document, $page); }

                // The same page and state in a 1.1.2 skin.
                $old = $this->visit($tree, $work, $url, $signedIn ? $session : null, $player, 'jade.htm');
                self::assertStringNotContainsString('rz-page', $old, $url);
                self::assertStringNotContainsString('rz-button-primary', $old, $url);
                self::assertSame(self::OLD_SKIN_BUTTONS[$url], $this->buttons($old), $url);
                if ($url === 'prefs.php') {
                    self::assertStringContainsString('function prepare_form(id){', $old);
                    self::assertStringNotContainsString('rz-tabs', $old);
                    self::assertStringNotContainsString('assets/ui/showform.js', $old);
                }
            }
        } finally {
            $cleanup = [];
            if ($player > 0) {
                $cleanup[] = $this->php($tree, 'db_query("DELETE FROM mail WHERE msgto=? OR msgfrom=?", true, [' . $player . ', ' . $player . ']); foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $player . ']); echo "removed";', false);
            }
            if (is_array($selfdelete) && !$installed) {
                $restore = 'db_query("DELETE FROM settings WHERE setting=?", true, ["selfdelete"]);';
                foreach ($selfdelete as $row) {
                    $restore .= ' db_query("INSERT INTO settings (setting, value) VALUES (?, ?)", true, ["selfdelete", ' . var_export($row['value'], true) . ']);';
                }
                $cleanup[] = $this->php($tree, $restore . ' echo "selfdelete restored";', false);
            }
            if ($installed) {
                $cleanup[] = $this->php($tree, 'foreach (array_column(db_query("SHOW TABLES"), "Tables_in_resurrection_test") as $t) db_query("DROP TABLE " . db_identifier($t)); echo resurrection_install_state();', false);
            }
            $this->remove($work);
        }
        self::assertSame(array_merge($player > 0 ? ['removed'] : [], is_array($selfdelete) && !$installed ? ['selfdelete restored'] : [],
            $installed ? ['empty'] : []), $cleanup);
    }

    /** At most one primary in any form; exactly one in each form task 9 names, and at least one such form. */
    private function assertPrimaries(\Dom\HTMLDocument $document, string $url): void
    {
        $named = 0;
        foreach ($document->querySelectorAll('form') as $form) {
            $count = $form->querySelectorAll('.rz-button-primary')->length;
            self::assertLessThanOrEqual(1, $count, $url . ': ' . $form->getAttribute('action'));
            foreach (self::PRIMARY[$url] ?? [] as $action) {
                if (str_starts_with((string)$form->getAttribute('action'), $action)) {
                    self::assertSame(1, $count, $url . ': ' . $form->getAttribute('action'));
                    $named++;
                }
            }
        }
        if (isset(self::PRIMARY[$url])) {
            self::assertGreaterThan(0, $named, $url . ': no form ' . implode(', ', self::PRIMARY[$url]));
        } else {
            self::assertSame(0, $document->querySelectorAll('.rz-button-primary')->length, $url);
        }
    }

    /** Every control a player fills in, in the page's content, has a <label for> or sits inside its <label>. */
    private function assertLabelled(\Dom\HTMLDocument $document, string $url): void
    {
        $controls = 0;
        foreach ($document->querySelectorAll('.rz-content input, .rz-content select, .rz-content textarea') as $control) {
            $type = strtolower((string)$control->getAttribute('type'));
            if ($control->tagName === 'INPUT' && in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) { continue; }
            $controls++;
            $id = (string)$control->getAttribute('id');
            $bound = $control->closest('label') !== null
                || ($id !== '' && $document->querySelector('label[for="' . addcslashes($id, '"\\') . '"]') !== null);
            self::assertTrue($bound, $url . ': ' . $control->tagName . ' ' . $control->getAttribute('name'));
            if ($id !== '') {
                self::assertSame(1, $document->querySelectorAll('[id="' . addcslashes($id, '"\\') . '"]')->length, $url . ': id ' . $id);
            }
        }
        if (!in_array($url, ['inn.php?op=room&pay=1', 'healer.php?op=buy&pct=100', 'weapons.php', 'armor.php'], true)) {
            self::assertGreaterThan(0, $controls, $url);
        }
    }

    /** Preferences: server-drawn tabs, one selected, each controlling its section; no inline handler or script. */
    private function assertTabs(\Dom\HTMLDocument $document, string $page): void
    {
        $list = $document->querySelector('[role="tablist"]');
        self::assertNotNull($list);
        self::assertTrue($list->hasAttribute('hidden'), 'The tab list waits for the script');
        $tabs = $list->querySelectorAll('[role="tab"]');
        self::assertGreaterThan(1, $tabs->length);
        self::assertSame($tabs->length, $document->querySelectorAll('[role="tabpanel"]')->length);
        $selected = 0;
        foreach ($tabs as $tab) {
            self::assertSame('BUTTON', $tab->tagName);
            self::assertSame('button', $tab->getAttribute('type'));
            $panel = $document->getElementById((string)$tab->getAttribute('aria-controls'));
            self::assertNotNull($panel);
            self::assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
            if ($tab->getAttribute('aria-selected') === 'true') {
                $selected++;
                self::assertFalse($tab->hasAttribute('tabindex'));
            } else {
                self::assertSame('false', $tab->getAttribute('aria-selected'));
                self::assertSame('-1', $tab->getAttribute('tabindex'));
            }
        }
        self::assertSame(1, $selected);
        // showFormTabIndex is the script's to add, as 1.1.2's script added it: the form's fields are unchanged.
        self::assertNull($document->querySelector('[name="showFormTabIndex"]'));
        // No inline event handler and no inline script in the content; the static script once.
        $content = $document->querySelector('.rz-content');
        self::assertNotNull($content);
        foreach ($content->querySelectorAll('*') as $element) {
            foreach ($element->attributes as $attribute) {
                self::assertStringStartsNotWith('on', strtolower($attribute->name), $element->tagName);
            }
            if ($element->tagName === 'SCRIPT') {
                self::assertTrue($element->hasAttribute('src'), 'inline script in the content');
            }
        }
        self::assertSame(1, substr_count($page, '<script src="assets/ui/showform.js" defer></script>'));
        self::assertNotNull($document->querySelector('.rz-content input.rz-button-danger[data-rz-confirm]'));
    }

    /**
     * Every button's markup in a page, in order: each <button> element whole, each submit, button or reset <input>.
     *
     * @return list<string>
     */
    private function buttons(string $page): array
    {
        preg_match_all('#<button\b[^>]*>.*?</button>|<input\b[^>]*\btype=(["\']?)(?:submit|button|reset)\1[^>]*>#is', $page, $found);
        return $found[0];
    }

    /** One GET as the player (or signed out), the account's allowed navs and clock set so the page is the one asked for. */
    private function visit(string $tree, string $work, string $url, ?array $session, int $player, ?string $skin): string
    {
        if ($session !== null) {
            $this->php($tree, 'db_query("UPDATE accounts SET allowednavs=?, laston=?, lasthit=?, restorepage=? WHERE acctid=?", true, [serialize([' . var_export($url, true) . ' => true]),'
                . ' date("Y-m-d H:i:s"), date("Y-m-d H:i:s", time() + 60), "", ' . $player . ']);');
        }
        [$script, $query] = array_pad(explode('?', $url, 2), 2, '');
        return $this->render($tree, $work, $script, $session, $query, $skin);
    }

    /**
     * One GET of a page, rendered by the PHP CLI with output_buffering=0, as TemplateRenderTest::render().
     *
     * @param array{acctid: int, authversion: int}|null $session
     */
    private function render(string $tree, string $work, string $script, ?array $session, string $query = '', ?string $skin = null): string
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
            ($skin === null ? '' : "\$_COOKIE = ['template' => " . var_export($skin, true) . "];\n") .
            $signin .
            "require '$script';\n");
        [$code, $out, $err] = $this->process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'session.save_path=' . $tree . '/.sessions', $work . '/page.php'], $tree);
        self::assertSame(0, $code, $script . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $script);
        return $out;
    }

    /** Runs PHP code with the tree's dbconnect.php and the database libraries loaded, in a separate process. */
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
