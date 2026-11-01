<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Configuration\LegacyConfig;

/**
 * The battle screen and the commentary on real pages (Phase 5 task 10, docs/PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md).
 *
 * Fights are played through their routes as a player plays them: the entry form's GET, its POST, then each Fight, every
 * POST carrying the tokens of the form the previous page printed, in one PHP session kept across the pages. mt_rand is
 * seeded before each page, so a fight (the creature chosen, every roll) is the same each run.
 *
 * In the Resurrection template: the fighter cards (the player, then the foe) with the numbers and bar widths of the
 * state the page saved; Fight as the one primary with Run beside it; the battle log as an ordered list, one item per
 * round, each labelled; the victory or defeat result block. In a 1.1.2 skin (jade.htm, by its cookie), the content of
 * every battle and commentary page is byte for byte what cf13929 printed (tests/fixtures/battle-commentary-old-skin.json),
 * except the commentary's draft script, which task 10 removed.
 *
 * Rendering is TemplateRenderTest's and FormsRenderTest's: the PHP CLI over a symlinked tree. An installed database is
 * used as it is; an empty one is installed here with scripts/install.php --modules=none and emptied again afterwards.
 * The settings changed for the fights, the players, their mail, news and comments, and the clan are restored or removed.
 */
final class BattleCommentaryRenderTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';
    private const string PINS = __DIR__ . '/../fixtures/battle-commentary-old-skin.json';
    private const string PASSWORD = 'Synthetic battle render player password';
    /** Settings for the fights: automatic fighting offered, no forest or graveyard event in place of a fight. */
    private const array SETTINGS = ['autofight' => '1', 'autofightfull' => '1', 'forestchance' => '0', 'gravechance' => '0'];
    /** 1.1.2's draft-saving script, which previewfield() printed after the comment input until task 10 (lib/forms.php is CRLF),
     * with the line break rawoutput() adds. */
    private const string DRAFT_SCRIPT = "\r\n\t\t<script language='JavaScript'>\r\n\t\t\tinputinsertcommentary.addEvent('keyup', function(){\r\n"
        . "\t\t\t\twindow.localStorage['value'] = inputinsertcommentary.value;\r\n\t\t\t\twindow.localStorage['timestamp'] = (new Date()). getTime();\r\n"
        . "\t\t\t}, false);\r\n\t\t\tinputinsertcommentary.value = window.localStorage['value'];\r\n\t\t</script>\r\n\t\n";

    private static string $tree = '';
    private static string $work = '';
    private static bool $installed = false;
    /** @var array<string, list<array{value: string}>> */
    private static array $saved = [];
    private static int $sequence = 0;
    /** @var array<string, int> */
    private static array $steps = [];
    /** @var array<string, array<string, mixed>> the account's state after each page of the last fight */
    private array $states = [];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { return; }
        $root = (string)realpath(self::ROOT);
        self::$work = sys_get_temp_dir() . '/resurrection-battle-render-' . bin2hex(random_bytes(6));
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
        if ($state === 'empty') {
            [$code, $out, $err] = self::process([PHP_BINARY, 'scripts/install.php', '--modules=none', self::$tree . '/dbconnect.php'], $root,
                ['RESURRECTION_ADMIN_LOGIN' => 'BattleRenderAdmin', 'RESURRECTION_ADMIN_PASSWORD' => 'Synthetic battle render admin password'] + getenv());
            if ($code !== 0) { throw new \RuntimeException('Install failed: ' . $err . $out); }
            self::$installed = true;
        } elseif ($state !== 'installed') {
            throw new \RuntimeException('The disposable test database must be empty or installed, not ' . $state);
        }
        // The game clock is pinned too (GameClock, Phase 5 task 11): a run that crosses a game-day boundary (00:00, 06:00,
        // 12:00 or 18:00 UTC on the installer's clock) no longer sends the fights to a new day.
        $settings = self::SETTINGS + GameClock::settings();
        $names = var_export(array_keys($settings), true);
        self::$saved = json_decode(self::php('$s = []; foreach (' . $names . ' as $n) $s[$n] = db_query("SELECT value FROM settings WHERE setting=?", true, [$n]); echo json_encode($s);'), true);
        foreach ($settings as $name => $value) { self::php('savesetting(' . var_export($name, true) . ', ' . var_export($value, true) . ');'); }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tree === '') { return; }
        try {
            if (self::$installed) {
                self::php('foreach (array_column(db_query("SHOW TABLES"), "Tables_in_resurrection_test") as $t) db_query("DROP TABLE " . db_identifier($t));');
            } else {
                $restore = '';
                foreach (self::$saved as $name => $rows) {
                    $restore .= 'db_query("DELETE FROM settings WHERE setting=?", true, [' . var_export($name, true) . ']);';
                    foreach ($rows as $row) {
                        $restore .= ' db_query("INSERT INTO settings (setting, value) VALUES (?, ?)", true, [' . var_export($name, true) . ', ' . var_export($row['value'], true) . ']);';
                    }
                }
                if ($restore !== '') { self::php($restore); }
            }
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

    /** A forest fight, three Fight presses after the encounter: the cards, the action bar and the log of each round. */
    public function testForestFightShowsCardsActionsAndTheRoundLog(): void
    {
        $this->withPlayer('RzForest', ['level' => 5, 'hitpoints' => 500, 'maxhitpoints' => 500, 'attack' => 1, 'defense' => 12], function (array $s): void {
            $pages = $this->forestFight($s, null, 3);
            foreach ($pages as $label => $page) {
                if ($label === 'forest entry') { continue; }
                $document = $this->document($page);
                $state = $this->states[$label];
                self::assertNotSame('', $state['badguy'], $label . ': the fight goes on');
                $this->assertCards($document, $state, $label);
                $this->assertActionBar($document, true, $label);
            }
            $document = $this->document($pages['fight 3']);
            $rounds = $this->rounds($document);
            self::assertCount(1, $rounds, 'one Fight, one round');
            self::assertNotSame([], $rounds[0]);
            // The encounter page's log opens with the encounter line, and no Fight divider is left.
            $entry = $this->document($pages['encounter']);
            $intro = $entry->querySelector('.rz-battle-log .rz-battle-intro .rz-battle-line');
            self::assertNotNull($intro);
            self::assertStringStartsWith('You have encountered ', trim($intro->textContent));
            self::assertStringContainsString('which lunges at you with ', $intro->textContent);
            foreach ($pages as $label => $page) {
                self::assertStringNotContainsString('~ ~ ~ Fight ~ ~ ~', $page, $label);
                self::assertStringNotContainsString('Next round:', $page, $label);
                self::assertStringNotContainsString('End of Round:', $page, $label);
            }
        });
    }

    /** "For 5 Rounds": the log is an ordered list, one item per round fought, with no round label. */
    public function testAutomaticFightingLogsEachRoundAsAListItem(): void
    {
        $this->withPlayer('RzAuto', ['level' => 5, 'hitpoints' => 500, 'maxhitpoints' => 500, 'attack' => 1, 'defense' => 12], function (array $s): void {
            $this->forestFight($s, null, 0);
            $page = $this->submit($s, 'forest.php?op=fight', null, 'five');
            $document = $this->document($page);
            $list = $document->querySelector('.rz-battle-log ol.rz-battle-rounds');
            self::assertNotNull($list);
            self::assertCount(5, $this->rounds($document));
            $items = $this->elements($list);
            self::assertCount(5, $items);
            foreach ($items as $item) {
                self::assertSame('LI', $item->tagName);
                self::assertGreaterThan(0, $item->querySelectorAll('.rz-battle-line')->length);
            }
            $this->assertCards($document, $this->state($s), 'five rounds');
        });
    }

    /** A win: the result block, headed Victory, with the gold and experience lines, after the last round. */
    public function testForestWinShowsTheVictoryResultBlock(): void
    {
        $this->withPlayer('RzWin', ['level' => 1, 'hitpoints' => 500, 'maxhitpoints' => 500, 'attack' => 400, 'defense' => 50], function (array $s): void {
            $pages = $this->forestFight($s, 'victory', 6);
            $document = $this->document(end($pages));
            $result = $document->querySelector('.rz-battle > .rz-battle-result.rz-battle-result-victory');
            self::assertNotNull($result, 'victory result block');
            self::assertSame('Victory', trim((string)$result->querySelector('.rz-battle-resulthead')?->textContent));
            self::assertStringContainsString('You have slain ', $result->textContent);
            self::assertStringContainsString('gold', $result->textContent);
            self::assertStringContainsString('experience', $result->textContent);
            // The result follows the log; the foe's card says it is dead; no fight action is left.
            self::assertSame('rz-battle-log', $result->previousElementSibling?->getAttribute('class'));
            $foe = $document->querySelector('.rz-fighter-foe');
            self::assertNotNull($foe);
            self::assertStringContainsString('rz-fighter-down', (string)$foe->getAttribute('class'));
            self::assertStringContainsString('DEAD', $foe->textContent);
            self::assertNull($document->querySelector('.rz-battle-actions'));
            self::assertSame('', $this->state($s)['badguy']);
        });
    }

    /** A loss: the result block, headed Defeat, with the death text. */
    public function testForestLossShowsTheDefeatResultBlock(): void
    {
        $this->withPlayer('RzLoss', ['level' => 5, 'hitpoints' => 1, 'maxhitpoints' => 50, 'attack' => 1, 'defense' => 0], function (array $s): void {
            $pages = $this->forestFight($s, 'defeat', 6);
            $document = $this->document(end($pages));
            $result = $document->querySelector('.rz-battle > .rz-battle-result.rz-battle-result-defeat');
            self::assertNotNull($result, 'defeat result block');
            self::assertSame('Defeat', trim((string)$result->querySelector('.rz-battle-resulthead')?->textContent));
            self::assertStringContainsString('All gold on hand has been lost!', $result->textContent);
            self::assertStringContainsString('You may begin fighting again tomorrow.', $result->textContent);
            $player = $document->querySelector('.rz-fighter-player');
            self::assertNotNull($player);
            self::assertNull($document->querySelector('.rz-battle-actions'));
            self::assertSame(0, (int)$this->state($s)['alive']);
        });
    }

    /** A graveyard torment: the same components, with the soul's wording. */
    public function testGraveyardTormentUsesTheSameComponents(): void
    {
        $fields = ['level' => 3, 'alive' => 0, 'hitpoints' => 0, 'soulpoints' => 65, 'gravefights' => 10, 'deathpower' => 0];
        $this->withPlayer('RzGrave', $fields, function (array $s): void {
            $pages = $this->graveyardFight($s, 2);
            $document = $this->document($pages['fight 2']);
            $state = $this->state($s);
            self::assertNotSame('', $state['badguy'], 'the torment goes on');
            $this->assertCards($document, $state, 'torment');
            $this->assertActionBar($document, true, 'torment');
            self::assertCount(1, $this->rounds($document));
            $foe = $state['enemies'][0];
            self::assertSame($foe['creatureweapon'], trim((string)$document->querySelector('.rz-fighter-foe .rz-fighter-sub i')?->textContent));
            $entry = $this->document($pages['encounter']);
            self::assertStringStartsWith('You have encountered ', trim((string)$entry->querySelector('.rz-battle-intro .rz-battle-line')?->textContent));
        });
    }

    /** A training master fight: the same components, with Fight and no Run (training never offered one). */
    public function testTrainingMasterFightUsesTheSameComponents(): void
    {
        $fields = ['level' => 1, 'experience' => 200, 'seenmaster' => 0, 'hitpoints' => 300, 'maxhitpoints' => 300, 'attack' => 1, 'defense' => 30];
        $this->withPlayer('RzTrain', $fields, function (array $s): void {
            $pages = $this->trainingFight($s, 2);
            $document = $this->document($pages['fight 2']);
            $state = $this->state($s);
            self::assertNotSame('', $state['badguy'], 'the master fight goes on');
            $this->assertCards($document, $state, 'training');
            $this->assertActionBar($document, false, 'training');
            self::assertCount(1, $this->rounds($document));
            $foe = $document->querySelector('.rz-fighter-foe');
            self::assertNotNull($foe);
            self::assertSame($state['enemies'][0]['trainingmaxhp'], $this->hp($foe)[1]);
        });
    }

    /**
     * The village's comments as a list, with an emote, a /me, a clan tag, time stamps and a moderator's Del form; the
     * pager as a row of buttons; the talk form's label; no script calls addEvent.
     */
    public function testCommentaryIsAListWithAPagerAndALabelledForm(): void
    {
        $this->withPlayer('RzTalk', ['superuser' => 16], function (array $s): void {
            $this->withComments($s, function () use ($s): void {
                $page = $this->page($s, 'village.php');
                $document = $this->document($page);
                $list = $document->querySelector('.rz-content ul.rz-comments');
                self::assertNotNull($list);
                $items = [];
                foreach ($this->elements($list) as $child) {
                    self::assertSame('rz-comment', $child->getAttribute('class'));
                    $items[] = $child;
                }
                self::assertCount(25, $items, 'the village shows 25 comments');
                $texts = [];
                foreach ($items as $item) { $texts[] = preg_replace('/\s+/', ' ', trim($item->textContent)); }
                $joined = implode("\n", $texts);
                self::assertStringContainsString('says, "Good morning, village."', $joined);
                self::assertMatchesRegularExpression('/RzTalkFriend waves to the crowd\./', $joined);
                self::assertMatchesRegularExpression('/RzTalkFriend dances a little jig\./', $joined);
                self::assertStringContainsString('<RZB> ', $joined, 'clan tag');
                self::assertMatchesRegularExpression('#^Del ?\[10/30 \d\d:\d\d[ap]m\] #', $texts[0], 'a moderator\'s Del, then the time stamp');
                // Every item: a moderator's Del form, then the comment.
                foreach ($items as $item) {
                    $form = $item->querySelector('form');
                    self::assertNotNull($form);
                    self::assertSame('POST', strtoupper((string)$form->getAttribute('method')));
                    self::assertNotNull($form->querySelector('input[name="removecomment"]'));
                    self::assertSame('Del', trim((string)$form->querySelector('button')?->textContent));
                }
                // The author's name in its color, the bio link 1.1.2 printed.
                self::assertNotNull($list->querySelector('a[href^="bio.php?char="] .colLtWhite'));

                $pager = $document->querySelector('nav.rz-pager');
                self::assertNotNull($pager);
                self::assertSame('Comment pages', $pager->getAttribute('aria-label'));
                $labels = [];
                foreach ($this->elements($pager) as $child) { $labels[] = [$child->tagName, trim($child->textContent)]; }
                self::assertSame([['SPAN', '<< First Unseen'], ['A', '< Previous'], ['A', 'Refresh'], ['SPAN', 'Next >'], ['SPAN', 'Last Page >>']], $labels);
                foreach ($pager->querySelectorAll('a') as $link) {
                    self::assertSame('button', $link->getAttribute('class'));
                    self::assertStringStartsWith('village.php?', (string)$link->getAttribute('href'));
                }
                foreach ($pager->querySelectorAll('span') as $off) {
                    self::assertSame('true', $off->getAttribute('aria-disabled'));
                }
                self::assertSame(1, $document->querySelectorAll('.rz-button-primary')->length, 'one primary: Add');
                $form = $document->querySelector('form.rz-talkform');
                self::assertNotNull($form);
                self::assertSame('Add', $form->querySelector('.rz-button-primary')?->getAttribute('value'));
                $label = $document->querySelector('label[for="inputinsertcommentary"]');
                self::assertNotNull($label);
                self::assertSame('Speak', trim($label->textContent), 'the village\'s own label');
                self::assertNotNull($form->querySelector('#inputinsertcommentary'));
                self::assertStringNotContainsString('addEvent', $page);
                self::assertStringNotContainsString('previewtext_draft', $page);
            });
        });
    }

    /**
     * In a 1.1.2 skin the battle and commentary pages are byte for byte what cf13929 printed, tokens and ids normalized.
     * With RESURRECTION_RECORD_PINS set to a path, the pages are written there as JSON instead: the fixture was recorded so
     * at cf13929, with this test copied into a cf13929 worktree.
     */
    public function testOldSkinBattleAndCommentaryAreUnchanged(): void
    {
        $active = (int)self::php('$r = db_query("SELECT COUNT(*) AS c FROM modules WHERE active=1"); echo $r[0]["c"];');
        if ($active !== 0) { self::markTestSkipped('The pins are recorded with no active module.'); }
        $pages = [];
        $this->withPlayer('RzOldForest', ['level' => 5, 'hitpoints' => 500, 'maxhitpoints' => 500, 'attack' => 1, 'defense' => 12], function (array $s) use (&$pages): void {
            foreach ($this->forestFight($s, null, 3, 'jade.htm') as $label => $page) { $pages['forest ' . $label] = $page; }
            $pages['forest five rounds'] = $this->submit($s, 'forest.php?op=fight', 'jade.htm', 'five');
        });
        $this->withPlayer('RzOldWin', ['level' => 1, 'hitpoints' => 500, 'maxhitpoints' => 500, 'attack' => 400, 'defense' => 50], function (array $s) use (&$pages): void {
            $won = $this->forestFight($s, 'victory', 6, 'jade.htm');
            $pages['forest win'] = end($won);
        });
        $this->withPlayer('RzOldLoss', ['level' => 5, 'hitpoints' => 1, 'maxhitpoints' => 50, 'attack' => 1, 'defense' => 0], function (array $s) use (&$pages): void {
            $lost = $this->forestFight($s, 'defeat', 6, 'jade.htm');
            $pages['forest loss'] = end($lost);
        });
        $this->withPlayer('RzOldGrave', ['level' => 3, 'alive' => 0, 'hitpoints' => 0, 'soulpoints' => 65, 'gravefights' => 10, 'deathpower' => 0], function (array $s) use (&$pages): void {
            foreach ($this->graveyardFight($s, 2, 'jade.htm') as $label => $page) { $pages['graveyard ' . $label] = $page; }
        });
        $this->withPlayer('RzOldTrain', ['level' => 1, 'experience' => 200, 'seenmaster' => 0, 'hitpoints' => 300, 'maxhitpoints' => 300, 'attack' => 1, 'defense' => 30], function (array $s) use (&$pages): void {
            foreach ($this->trainingFight($s, 2, 'jade.htm') as $label => $page) { $pages['training ' . $label] = $page; }
        });
        $this->withPlayer('RzOldTalk', ['superuser' => 16], function (array $s) use (&$pages): void {
            $this->withComments($s, function () use ($s, &$pages): void {
                $pages['village commentary'] = $this->commentary($this->page($s, 'village.php', null, 'jade.htm'));
            });
        });
        $pinned = [];
        foreach ($pages as $label => $page) {
            $pinned[$label] = $label === 'village commentary' ? $page : $this->normalize($this->content($page));
        }
        $record = getenv('RESURRECTION_RECORD_PINS');
        if ($record !== false && $record !== '') {
            file_put_contents($record, json_encode($pinned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            self::markTestIncomplete('Pins recorded to ' . $record);
        }
        /** @var array<string, string> $expected */
        $expected = json_decode((string)file_get_contents(self::PINS), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(array_keys($expected), array_keys($pinned));
        foreach ($expected as $label => $html) {
            if ($label === 'village commentary') {
                // The one change task 10 makes in a 1.1.2 skin: the draft script is gone (CHANGES-FROM-1.1.2.md).
                self::assertSame(1, substr_count($html, self::DRAFT_SCRIPT), 'the pin holds the draft script once');
                $html = str_replace(self::DRAFT_SCRIPT, '', $html);
            }
            self::assertSame($html, $pinned[$label], $label);
            self::assertStringNotContainsString('rz-', $pinned[$label], $label);
        }
        self::assertStringNotContainsString('addEvent', $pinned['village commentary']);
    }

    // The fights.

    /**
     * The forest: the search form, the encounter, then up to $presses Fight presses, stopping at the outcome asked
     * for (or failing if it never comes).
     *
     * @param array<string, mixed> $s
     * @return array<string, string>
     */
    private function forestFight(array $s, ?string $until, int $presses, ?string $skin = null): array
    {
        $this->states = [];
        $pages = ['forest entry' => $this->page($s, 'forest.php?op=search', null, $skin, true)];
        $pages['encounter'] = $this->submit($s, 'forest.php?op=search', $skin);
        $this->states['encounter'] = $this->state($s);
        for ($i = 1; $i <= $presses; $i++) {
            if ($until !== null && $this->ended($s, $until)) { break; }
            $pages['fight ' . $i] = $this->submit($s, 'forest.php?op=fight', $skin);
            $this->states['fight ' . $i] = $this->state($s);
        }
        if ($until !== null) { self::assertTrue($this->ended($s, $until), 'the fight ended in ' . $until); }
        return $pages;
    }

    /** @param array<string, mixed> $s @return array<string, string> */
    private function graveyardFight(array $s, int $presses, ?string $skin = null): array
    {
        $this->states = [];
        $pages = ['entry' => $this->page($s, 'graveyard.php', null, $skin, true)];
        $pages['encounter'] = $this->submit($s, 'graveyard.php?op=search', $skin);
        for ($i = 1; $i <= $presses; $i++) { $pages['fight ' . $i] = $this->submit($s, 'graveyard.php?op=fight', $skin); $this->states['fight ' . $i] = $this->state($s); }
        return $pages;
    }

    /** @param array<string, mixed> $s @return array<string, string> */
    private function trainingFight(array $s, int $presses, ?string $skin = null): array
    {
        $this->states = [];
        $pages = ['entry' => $this->page($s, 'train.php', null, $skin, true)];
        $pages['encounter'] = $this->submit($s, 'train.php?op=challenge', $skin);
        for ($i = 1; $i <= $presses; $i++) { $pages['fight ' . $i] = $this->submit($s, 'train.php?op=fight', $skin); $this->states['fight ' . $i] = $this->state($s); }
        return $pages;
    }

    /** @param array<string, mixed> $s */
    private function ended(array $s, string $until): bool
    {
        $state = $this->state($s);
        if ($state['badguy'] !== '') { return false; }
        return $until === 'victory' ? (int)$state['alive'] === 1 : (int)$state['alive'] === 0;
    }

    // Assertions.

    /**
     * The player's card and each foe's: name, level, weapon, and hitpoints "current / max" with the bar's width, as the
     * state the page saved has them.
     *
     * @param array<string, mixed> $state
     */
    private function assertCards(\Dom\HTMLDocument $document, array $state, string $label): void
    {
        $cards = $document->querySelectorAll('.rz-battle > .rz-fighters > section.rz-fighter');
        self::assertSame(1 + count($state['enemies']), $cards->length, $label);
        $player = $cards->item(0);
        self::assertStringContainsString('rz-fighter-player', (string)$player->getAttribute('class'), $label);
        self::assertSame($state['plainname'], trim((string)$player->querySelector('.rz-fighter-name')?->textContent), $label);
        self::assertSame('Level ' . $state['level'], trim((string)$player->querySelector('.rz-fighter-sub')?->textContent), $label);
        $alive = (int)$state['alive'] === 1;
        $mine = $alive ? [(int)$state['hitpoints'], (int)$state['maxhitpoints']] : [(int)$state['soulpoints'], (int)$state['level'] * 5 + 50];
        self::assertSame($alive ? 'HP' : 'Soul', trim((string)$player->querySelector('.rz-fighterbar-label')?->textContent), $label);
        self::assertSame($mine, $this->hp($player), $label);
        self::assertSame($this->width($mine[0], $mine[1]), $this->bar($player), $label);
        foreach ($state['enemies'] as $index => $enemy) {
            $card = $cards->item($index + 1);
            self::assertStringContainsString('rz-fighter-foe', (string)$card->getAttribute('class'), $label);
            self::assertSame($enemy['plainname'], trim((string)$card->querySelector('.rz-fighter-name')?->textContent), $label);
            $sub = $card->querySelector('.rz-fighter-sub');
            self::assertNotNull($sub);
            self::assertStringStartsWith('Level ' . $enemy['creaturelevel'], trim($sub->textContent), $label);
            self::assertSame($enemy['plainweapon'], trim((string)$sub->querySelector('i')?->textContent), $label);
            $max = $enemy['creaturemaxhealth'] ?? $enemy['trainingmaxhp'] ?? ($alive ? null : (int)$state['level'] * 5 + 50);
            self::assertNotNull($max, $label . ': the foe\'s maximum is known');
            self::assertSame([(int)round($enemy['creaturehealth']), (int)round($max)], $this->hp($card), $label);
            self::assertSame($this->width($enemy['creaturehealth'], $max), $this->bar($card), $label);
        }
    }

    /** Fight, the one primary, with Run beside it (when the fight has Run), then the automatic fighting group. */
    private function assertActionBar(\Dom\HTMLDocument $document, bool $run, string $label): void
    {
        $primaries = $document->querySelectorAll('.rz-button-primary');
        self::assertSame(1, $primaries->length, $label . ': one primary');
        $main = $document->querySelector('.rz-battle-actions > .rz-battle-main');
        self::assertNotNull($main, $label);
        $buttons = [];
        foreach ($main->querySelectorAll('form button') as $button) {
            $buttons[] = [trim($button->textContent), str_contains((string)$button->getAttribute('class'), 'rz-button-primary')];
        }
        self::assertSame($run ? [['Fight', true], ['Run', false]] : [['Fight', true]], $buttons, $label);
        $auto = $document->querySelector('.rz-battle-actions > .rz-battle-group[aria-labelledby="rz-battle-auto"]');
        self::assertNotNull($auto, $label);
        self::assertSame('Automatic Fighting', trim((string)$auto->querySelector('h3')?->textContent), $label);
        self::assertSame(3, $auto->querySelectorAll('form button.button')->length, $label);
        self::assertSame(0, $auto->querySelectorAll('.rz-button-primary')->length, $label);
        // The cards, the actions, the log: in that order.
        $order = [];
        foreach ($this->elements($document->querySelector('.rz-battle')) as $child) { $order[] = explode(' ', (string)$child->getAttribute('class'))[0]; }
        self::assertSame(['rz-fighters', 'rz-battle-actions', 'rz-battle-log'], $order, $label);
    }

    /** @return list<list<string>> each round's lines' text; no round carries a label */
    private function rounds(\Dom\HTMLDocument $document): array
    {
        $rounds = [];
        foreach ($document->querySelectorAll('.rz-battle-log > ol.rz-battle-rounds > li.rz-battle-round') as $item) {
            self::assertNull($item->querySelector('.rz-battle-roundlabel, h1, h2, h3, h4, h5, h6'), 'no round label');
            self::assertDoesNotMatchRegularExpression('/\bRound \d/', $item->textContent, 'no "Round N"');
            $lines = [];
            foreach ($item->querySelectorAll('.rz-battle-line') as $line) { $lines[] = trim($line->textContent); }
            $rounds[] = $lines;
        }
        return $rounds;
    }

    /**
     * The element's child elements, in order. Dom\ParentNode::$children exists only from PHP 8.5; this walks
     * firstElementChild and nextElementSibling, which PHP 8.4 has too.
     *
     * @return list<\Dom\Element>
     */
    private function elements(?\Dom\Element $parent): array
    {
        $children = [];
        for ($child = $parent?->firstElementChild; $child !== null; $child = $child->nextElementSibling) { $children[] = $child; }
        return $children;
    }

    /** @return list<int> the card's "current / max" */
    private function hp(\Dom\Element $card): array
    {
        $value = trim((string)$card->querySelector('.rz-fighterbar-value')?->textContent);
        self::assertMatchesRegularExpression('#^\d+ / \d+$#', $value);
        return array_map('intval', explode(' / ', $value));
    }

    private function bar(\Dom\Element $card): string
    {
        $fill = $card->querySelector('.rz-fighterbar-track > .rz-fighterbar-fill');
        self::assertNotNull($fill);
        self::assertSame(1, preg_match('/^width: ([0-9.]+)%$/', (string)$fill->getAttribute('style'), $m));
        return $m[1];
    }

    private function width(int|float $current, int|float $max): string
    {
        return (string)round(max(0, min(100, $current / $max * 100)), 1);
    }

    // Players, comments and pages.

    /**
     * Creates a player with these account fields, runs $test with a session for them, and removes them, their mail,
     * news and comments afterwards.
     *
     * @param array<string, int|string> $fields
     */
    private function withPlayer(string $login, array $fields, callable $test): void
    {
        $fields += ['loggedin' => 1, 'turns' => 10, 'gold' => 100, 'laston' => date('Y-m-d H:i:s'), 'lasthit' => date('Y-m-d H:i:s', time() + 60)];
        $set = implode(', ', array_map(fn ($k) => $k . '=?', array_keys($fields)));
        $id = (int)self::php('require_once "lib/accounts.php"; $id = resurrection_create_account(' . var_export($login, true) . ', ' . var_export(self::PASSWORD, true) . ');'
            . ' db_query("UPDATE accounts SET ' . $set . ' WHERE acctid=?", true, array_merge(' . var_export(array_values($fields), true) . ', [$id])); echo $id;');
        self::assertGreaterThan(0, $id);
        $row = json_decode(self::php('echo json_encode(db_query("SELECT authversion, superuser FROM accounts WHERE acctid=?", true, [' . $id . '])[0]);'), true);
        $s = ['id' => 'rzbattle' . bin2hex(random_bytes(8)), 'acctid' => $id, 'authversion' => (int)$row['authversion'], 'superuser' => (int)$row['superuser']];
        try {
            $test($s);
        } finally {
            self::php('foreach (["mail" => "msgto=? OR msgfrom=?", "news" => "accountid=? OR accountid=?", "commentary" => "author=? OR author=?"] as $t => $w) db_query("DELETE FROM " . db_identifier($t) . " WHERE " . $w, true, [' . $id . ', ' . $id . ']);'
                . ' foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $id . ']);');
            foreach (glob(self::$tree . '/.sessions/sess_' . $s['id']) ?: [] as $file) { unlink($file); }
        }
    }

    /**
     * Thirty comments in the village (25 shown), from the viewer and from a second player in a clan: a greeting, an
     * emote, a /me, a :: emote and colored text among them, at fixed times; the viewer shows time stamps.
     *
     * @param array<string, mixed> $s
     */
    private function withComments(array $s, callable $test): void
    {
        $friend = (int)self::php('require_once "lib/accounts.php"; db_query("INSERT INTO clans (clanname, clanshort, clanmotd, clandesc, motdauthor, descauthor, customsay) VALUES (?, ?, ?, ?, 0, 0, ?)", true, ["Render Brigade", "RZB", "", "", ""]);'
            . ' $rows = db_query("SELECT clanid FROM clans WHERE clanshort=?", true, ["RZB"]); $clan = (int)$rows[0]["clanid"];'
            . ' $id = resurrection_create_account("RzTalkFriend", ' . var_export(self::PASSWORD, true) . '); db_query("UPDATE accounts SET clanid=?, clanrank=? WHERE acctid=?", true, [$clan, 10, $id]); echo $id;');
        $comments = [];
        for ($i = 1; $i <= 25; $i++) { $comments[] = [$i % 2 ? $friend : $s['acctid'], 'Small talk number ' . $i . '.']; }
        $comments = array_merge($comments, [[$s['acctid'], 'Good morning, village.'], [$friend, ':waves to the crowd.'], [$friend, '/me dances a little jig.'],
            [$s['acctid'], '::is thinking about dragons.'], [$friend, '`@The green smoke rises.']]);
        $insert = '';
        foreach ($comments as $n => [$author, $text]) {
            $insert .= 'db_query("INSERT INTO commentary (section, author, comment, postdate) VALUES (?, ?, ?, ?)", true, ["village", ' . $author . ', ' . var_export($text, true) . ', "' . sprintf('2026-10-30 %02d:%02d:00', 8 + intdiv($n, 6), ($n % 6) * 10) . '"]);';
        }
        self::php($insert . ' $r = db_query("SELECT prefs FROM accounts WHERE acctid=?", true, [' . $s['acctid'] . ']); $a = $r[0];'
            . ' $p = $a["prefs"] === "" ? [] : unserialize($a["prefs"], ["allowed_classes" => false]); $p["timestamp"] = 1;'
            . ' db_query("UPDATE accounts SET prefs=?, recentcomments=? WHERE acctid=?", true, [serialize($p), "2026-10-30 10:00:00", ' . $s['acctid'] . ']);');
        try {
            $test();
        } finally {
            self::php('db_query("DELETE FROM commentary WHERE author=?", true, [' . $friend . ']); db_query("DELETE FROM clans WHERE clanshort=?", true, ["RZB"]);'
                . ' foreach (["accounts_output", "accounts"] as $t) db_query("DELETE FROM " . db_identifier($t) . " WHERE acctid=?", true, [' . $friend . ']);');
        }
    }

    /**
     * POSTs the form on the session's last page whose action starts with $action (with $rounds, the automatic
     * fighting form for that many rounds), its hidden fields as the browser sends them.
     *
     * @param array<string, mixed> $s
     */
    private function submit(array $s, string $action, ?string $skin = null, ?string $rounds = null): string
    {
        $last = (string)file_get_contents(self::$work . '/last-' . $s['id'] . '.html');
        $document = $this->document($last);
        foreach ($document->querySelectorAll('form') as $form) {
            if (!str_starts_with((string)$form->getAttribute('action'), $action)) { continue; }
            $fields = [];
            foreach ($form->querySelectorAll('input[type="hidden"]') as $input) { $fields[(string)$input->getAttribute('name')] = (string)$input->getAttribute('value'); }
            if (($fields['rounds'] ?? null) !== $rounds) { continue; }
            return $this->page($s, (string)$form->getAttribute('action'), $fields, $skin);
        }
        self::fail('No form ' . $action . ($rounds === null ? '' : ' (' . $rounds . ')') . " on the last page:\n" . substr($this->content($last), 0, 3000));
    }

    /**
     * One request in the player's session: a GET, or a POST of $post. The first request may set the account's allowed
     * navigation to its own URL, as the link that led there would have.
     *
     * @param array<string, mixed> $s
     * @param array<string, string>|null $post
     */
    private function page(array $s, string $url, ?array $post = null, ?string $skin = null, bool $allow = false): string
    {
        if ($allow || !is_file(self::$work . '/last-' . $s['id'] . '.html')) {
            self::php('db_query("UPDATE accounts SET allowednavs=?, restorepage=? WHERE acctid=?", true, [serialize([' . var_export($url, true) . ' => true]), "", ' . $s['acctid'] . ']);');
        }
        [$script, $query] = array_pad(explode('?', html_entity_decode($url), 2), 2, '');
        parse_str($query, $get);
        $uri = '/' . $script . ($query === '' ? '' : '?' . $query);
        // The same seed for the same step of the same scenario, every run.
        self::$steps[$s['id']] = (self::$steps[$s['id']] ?? 0) + 1;
        $seed = 20261031 + self::$steps[$s['id']] * 7919;
        self::$sequence++;
        file_put_contents(self::$work . '/page.php', "<?php\n" .
            "\$_SERVER = ['SCRIPT_NAME' => '/$script', 'PHP_SELF' => '/$script', 'REQUEST_URI' => " . var_export($uri, true) . ", 'QUERY_STRING' => " . var_export($query, true) . ",\n" .
            "    'REQUEST_METHOD' => " . var_export($post === null ? 'GET' : 'POST', true) . ", 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80', 'REMOTE_ADDR' => '127.0.0.1',\n" .
            "    'HTTP_HOST' => 'localhost'] + \$_SERVER;\n" .
            "\$_GET = " . var_export($get, true) . ";\n" .
            "\$_POST = " . var_export($post ?? [], true) . ";\n" .
            ($skin === null ? '' : "\$_COOKIE = ['template' => " . var_export($skin, true) . "];\n") .
            "session_save_path(" . var_export(self::$tree . '/.sessions', true) . "); session_id(" . var_export($s['id'], true) . "); session_start();\n" .
            "if (!isset(\$_SESSION['session'])) \$_SESSION = ['session' => ['loggedin' => true, 'lasthit' => time(), 'allowednavs' => [], 'user' => ['acctid' => {$s['acctid']}, 'loggedin' => true]],\n" .
            "    'auth_version' => {$s['authversion']}, 'auth_privileges' => {$s['superuser']}];\n" .
            "mt_srand($seed);\n" .
            "require '$script';\n");
        [$code, $out, $err] = self::process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'session.save_path=' . self::$tree . '/.sessions', self::$work . '/page.php'], self::$tree);
        self::assertSame(0, $code, $url . ': ' . $err . substr($out, 0, 2000));
        self::assertSame('', $err, $url);
        self::assertStringContainsString('</html>', $out, $url . ': ' . substr($out, 0, 2000));
        file_put_contents(self::$work . '/last-' . $s['id'] . '.html', $out);
        if (getenv('RESURRECTION_BATTLE_RENDER_DUMP')) {
            file_put_contents(getenv('RESURRECTION_BATTLE_RENDER_DUMP') . '/' . sprintf('%03d', self::$sequence) . '-' . preg_replace('/[^a-z0-9]+/i', '-', $url) . ($skin ? '-old' : '') . '.html', $out);
        }
        return $out;
    }

    /**
     * The account's state as the page saved it, with each foe's name and weapon as plain text.
     *
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function state(array $s): array
    {
        $state = json_decode(self::php('require_once "lib/sanitize.php"; $a = db_query("SELECT name, level, alive, hitpoints, maxhitpoints, soulpoints, badguy FROM accounts WHERE acctid=?", true, [' . $s['acctid'] . '])[0];'
            . ' $b = $a["badguy"] === "" ? [] : unserialize($a["badguy"], ["allowed_classes" => false]); $e = $b["enemies"] ?? [];'
            . ' foreach ($e as $i => $x) { $e[$i]["plainname"] = trim(full_sanitize($x["creaturename"])); $e[$i]["plainweapon"] = trim(full_sanitize($x["creatureweapon"])); }'
            . ' $a["plainname"] = trim(full_sanitize($a["name"])); $a["enemies"] = array_values($e); echo json_encode($a);'), true);
        self::assertIsArray($state);
        return $state;
    }

    private function document(string $page): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString($page, LIBXML_NOERROR);
    }

    /**
     * The page's content: what page_footer() put between jade.htm's header part and its footer part, from the forced
     * navigation comment every page prints first to the cell the footer part closes.
     */
    private function content(string $page): string
    {
        $start = strpos($page, "<!--\nAllowAnonymous");
        if ($start === false) { return $page; }
        self::assertSame(1, preg_match_all("#\\s*</td>\\s*<td valign=top width='120'>#", $page, $found, PREG_OFFSET_CAPTURE));
        return substr($page, $start, $found[0][0][1] - $start);
    }

    /** The commentary's part of a page: from its anchor, through the comments and the talk form, to the pager's end. */
    private function commentary(string $page): string
    {
        $content = $this->content($page);
        $start = strpos($content, "<a name='village'></a>");
        $end = strrpos($content, 'Last Page &gt;&gt;');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $end += strlen('Last Page &gt;&gt;');
        if (substr($content, $end, 4) === '</a>') { $end += 4; }
        return $this->normalize(substr($content, $start, $end - $start));
    }

    /** Tokens, the session's page counter, and account and comment ids, which differ from run to run. */
    private function normalize(string $html): string
    {
        return (string)preg_replace(['/name=(["\'])(csrf_token|action_token)\1 value=(["\'])[^"\']*\3/', '/value=(["\'])[^"\']*\1( name=(["\'])(csrf_token|action_token)\3)/',
            '/([?&;]c=)\d+(-\d+)?/', '/(char=)\d+/', '/(name="removecomment" value=")\d+/', "/(name='counter' value=')\\d+/"],
            ['name=$1$2$1 value=$3TOKEN$3', 'value=$1TOKEN$1$2', '${1}N', '${1}N', '${1}N', '${1}N'], $html);
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
