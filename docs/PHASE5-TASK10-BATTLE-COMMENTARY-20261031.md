# Phase 5 task 10: battle screen, commentary and shop lists (2026-10-31)

Started from `main` at `cf13929` (task 9), as the brief expected. Plan: `PHASE5-UI-PLAN-20261030.md` (the commentary
and battle log task). Spec: `UI-DESIGN-SPEC.md` (Components: "Fighter cards", "Battle action bar", "Battle log",
"Battle result", "Commentary", "Commentary pager", "Buy lists"; "Primary actions" updated). Ledger:
`CHANGES-FROM-1.1.2.md` (the task 10 rows).

## Task 9 accepted

Recorded in `PHASE5-TASK9-FORMS-20261031.md` ("Acceptance") and ticked in `ROADMAP.md`: Modern core 41196666950,
SUCCESS, all 12 jobs; Baseline integrity 41196666832, SUCCESS; both on `cf13929`, confirmed through the GitHub Actions
API. Shard jobs, shards 1 to 4: PHP 8.4 / MariaDB 11.4 14:16, 19:18, 18:12, 14:38; PHP 8.5 / MySQL 8.4 15:11, 14:59,
20:43, 22:06.

## How the battle screen is built

`battle.php` and the combat routes still print what they printed. In the Resurrection template a new file,
`lib/battle_view.php`, sorts that output into the screen's parts as it is printed, and `page_footer()` puts them back in
the screen's order. In a 1.1.2 skin none of it runs (`resurrection_battle_ui()` is false), so the old skins' output is
byte for byte what `cf13929` printed (pinned, below).

| Step | Where | What |
|---|---|---|
| Begin | `battle.php`, before the skill or new target | what the page printed before the fight (for example "You failed to flee") is kept as the screen's prefix |
| Encounter lines | `battle.php`, the `enemycounter` block | the "~ ~ ~ Fight ~ ~ ~" divider is not printed; a skill's or a new target's text is held and follows "You have encountered … which lunges at you with …!", so that line opens the log |
| Each round | `battle.php`, top of the round loop | a new log item; "Next round:" is not printed |
| After the rounds | `battle.php` | what follows (victory, defeat) goes to the result block; "End of Round:" and its hitpoint list are not printed, the cards show the foes as the rounds left them |
| Cards | `show_enemies()` (`lib/extended-battle.php`) | registers the foes for the cards instead of printing the hitpoint list |
| Actions | each route's form helper | Fight, Run and the automatic fighting forms, the specialty skills, a superuser's actions join their group (`resurrection_battle_action()`) instead of printing in place |
| Close | the graveyard after a torment, the Dragon's prologue | what the route prints after the fight follows the screen as ordinary content |
| Assemble | `page_footer()` | prefix, then the `battle` part (cards, actions, log, result), then the rest; each piece's tags balanced so no color runs into the next |

The battle-victory and battle-defeat hooks still get `fightoutput` as 1.1.2 printed it, in print order
(`resurrection_battle_fightoutput()`).

Every fight that runs `battle.php` gets the screen: the forest (`lib/forest_combat.php`), PvP (`pvp.php`), graveyard
torments (`lib/graveyard_combat.php`), training masters (`lib/training_combat.php`), the Green Dragon
(`lib/dragon_combat.php`) and module fights. A module fight that offers its actions through `fightnav()` keeps them as
navs in the nav column (1.1.2's place); it gets the cards, the log and the result.

### Fighter cards

The player first (name, "Level N", "HP" bar in `--health` with "current / max"; a dead player's "Soul" against level ×
5 + 50, as the character panel shows it), then each foe (name in `--damage`, "Level N" and the weapon in italics, "HP"
bar in `--foe`, a 1px `--foe` border). Bars are `--bar-height-fight` (10px); the fill is current ÷ max to one decimal,
held between 0% and 100%. Names and weapons are plain text (color codes dropped), escaped. A dead foe reads "0 / max ·
DEAD" ("DEFEATED" in the graveyard, 1.1.2's words); with more than one foe the target is marked "Target". A module's
`hidehitpoints` shows "???" with no bar.

### The foe's maximum hitpoints

The brief's order: use a known starting value; else record one when the fight starts without changing a stored-state
format; else show the number without a bar and say why.

| Fight | Maximum | Source |
|---|---|---|
| Training master | `trainingmaxhp` | already recorded by `lib/training_combat.php` |
| PvP victim | `pvpmaxhp` | already recorded by `pvp.php` |
| Graveyard torment | level × 5 + 50 | the value the torment is always created with (1.1.2 and `lib/graveyard_combat.php`); used only while the player is dead |
| Forest (each foe) | `creaturemaxhealth` | **new**: recorded in `lib/forest_encounter.php` when the encounter is created |
| Green Dragon | `creaturemaxhealth` | **new**: recorded in `lib/dragon_outcomes.php` after the `buffdragon` hook |
| A fight stored before task 10, a foe a script adds during a fight | none | the card shows the number with no bar (`fighterhp` part) |

Recording `creaturemaxhealth` adds one key to each foe's array in the stored fight. That is a stored-state change, so
`ForestCombatState` and `DragonCombatState` accept it as an optional number from 1 (`ForestCombatStateTest`,
`DragonCombatStateTest`: accepted, absent, and rejected for 0, negative, text, null, an array, and above the range).
No serialize, `ScalarState::read` or unserialize call was added or removed; the inventory recount is unchanged, 83 / 43
/ 38 / 44 / 1 at `cf13929` and after (`SERIALIZED-STATE-AUDIT.md`, "Recount after the battle screen").

### Action bar

Fight is the one primary (`resurrection_button_class(true)`), Run secondary beside it, in a row. Then labelled groups of
secondary buttons: "Automatic Fighting" (5 rounds, 10 rounds, until the end, as the settings offer them), "Special
Abilities" with "N uses left" under the heading, and a superuser's actions (the Dragon's god mode and restart). The
group headings are `fightnav()`'s, in its `fightnav` namespace. Every action is the same form as at `cf13929`: the same
label, hidden fields, action token, CSRF token and target; only its place and class changed. Training has no Run (it
never had one). The Dragon's and PvP's forms follow the same groups.

### Battle log and result block

The log: the encounter lines, each its own row, then an ordered list with one item per round, each headed "Round N",
each line of the round its own row in its codes' colors. Lines are split at `<br>` and each line's tags balanced (a
color open across a break is closed at the line's end and opened again on the next); empty lines are dropped. Rounds
are counted per request: Fight is one round, "For 5 Rounds" up to five. A count across the whole fight would need a
counter in the stored fight state of five schemas; it is left for a later task if Jim wants it.

The result block: what the fight printed after its last round, headed "Victory" (`--accent` marker) or "Defeat"
(`--damage` marker): the creature's last words, "You have slain …", gold, experience, gems, the flawless bonus, or the
death text. Lines after the rounds of a fight that goes on (a skill's after-effects) sit in the same box without a
heading.

## Commentary

`viewcommentary()`: in the Resurrection template each comment is a list item (`ul.rz-comments`, `li.rz-comment`) with
its line as 1.1.2 wrote it: the moderator's Del form, the time stamp, the new marker, the clan tag and name, "says," or
the emote, the text. The `viewcommentary` module hook still gets and may change each line. A color the lines before the
list left open is closed before the list. The moderation screen (all sections) keeps 1.1.2's lines until task 11.

The pager: "<< First Unseen", "< Previous", "Refresh", "Next >", "Last Page >>" as a `<nav>` named "Comment pages", a
row of 44px secondary buttons with the same links, keys and targets; a page that does not exist is the same label as a
`<span aria-disabled="true">`. None is primary, since Add is the page's primary. Task 8's phone rule that sized the old
paging links is removed.

The talk form: its label (the page's message, task 9's `<label for="inputinsertcommentary">`) on its own line, then the
44px input and Add (primary) in a row that wraps (`form.rz-talkform`).

### The draft-saving script, removed

1.1.2's `previewfield()` (`lib/forms.php`, also used by mail's write form) printed, after the input:

```
<script language='JavaScript'>
    input$name.addEvent('keyup', function(){
        window.localStorage['value'] = input$name.value;
        window.localStorage['timestamp'] = (new Date()). getTime();
    }, false);
    input$name.value = window.localStorage['value'];
</script>
```

No browser provides `addEvent` (it was a MooTools-style method; 1.1.2 loads no such library), so the script threw a
TypeError on every page with a comment box, before it saved or restored anything. Jim's decision: remove it, in every
skin. No player loses a feature. The live preview and the characters-left text are unchanged. The old-skin commentary
pin allows exactly this one difference (below).

## Shop lists

Jim's review of task 9: an affordable item in the weapon and armor shops is a link, not a filled button. The
`shop-button` entry of `resurrection_presentation()` (`lib/output.php`) is now `class='rz-shop-link'` in the
Resurrection template: `--accent` text, weight 700, no fill or border; the row is the 44px tap area (the button's
`::after` covers its `tr`, which is `position: relative`; each cell at least `--touch-target` tall), and the row
darkens to `--ground` on hover. The form (`lib/equipment_authority.php`), its tokens and its target are unchanged. Items
the player cannot afford stay plain text. The weapon and armor pages now have no primary. In a 1.1.2 skin the button
keeps its link-style inline markup, byte for byte (`FormsRenderTest`).

Checked for the same pattern: the stables (`stables.php`) and the mercenary camp (`mercenarycamp.php`) list their
mounts and companions as navs, each leading to a page with one buy form; the healer's potions are one form each with one
primary (task 9); the bank and the inn have no item list. None is a body buy table, so none changed.

## Tests: failing first, passing after

Each new test was run on `cf13929` (a worktree with its own `vendor/`, the new tests and fixture copied in) and failed,
then on the task's tree and passed. Logs: run locally, not committed.

| Test | On `cf13929` | After |
|---|---|---|
| `BattleCommentaryRenderTest::testForestFightShowsCardsActionsAndTheRoundLog` (encounter and three Fight presses: both cards' numbers and bar widths against the saved state, Fight the one primary with Run beside it, the automatic group, cards then actions then log, one round item, no divider, "Next round:" or "End of Round:") | FAIL (no cards: 0 of 2) | PASS |
| `…::testAutomaticFightingLogsEachRoundAsAListItem` ("For 5 Rounds": an `<ol>` of five items, Round 1 to Round 5) | FAIL (no log list) | PASS |
| `…::testForestWinShowsTheVictoryResultBlock` | FAIL (no result block) | PASS |
| `…::testForestLossShowsTheDefeatResultBlock` | FAIL (no result block) | PASS |
| `…::testGraveyardTormentUsesTheSameComponents` (Soul, the torment's maximum) | FAIL (no cards) | PASS |
| `…::testTrainingMasterFightUsesTheSameComponents` (Fight alone, `trainingmaxhp`) | FAIL (no cards) | PASS |
| `…::testCommentaryIsAListWithAPagerAndALabelledForm` (25 items with an emote, a `/me`, a `::`, a clan tag, time stamps and a moderator's Del on each; the pager's five entries; one primary, Add; the label; no `addEvent`) | FAIL (no list) | PASS |
| `…::testOldSkinBattleAndCommentaryAreUnchanged` (17 pages in `jade.htm` against the pins) | FAIL (the commentary still printed the draft script) | PASS |
| `FormsRenderTest` (shops: nine affordable items, each its form's one `rz-shop-link` button; no primary on the page) | FAIL (9 primaries, expected 0) | PASS |
| `ForestCombatStateTest::testRecordedMaximumHitpoints`, `DragonCombatStateTest::testRecordedMaximumHitpoints` | ERROR (unexpected field) | PASS |
| `tests/tooling/test_ui_battle.py` (10 tests: styles, Fight the one primary in each route, the divider kept only for old skins, the draft script removed and recorded, the spec) | 10 FAIL | PASS |
| `test_ui_template.py::test_every_part_the_code_uses_exists` (the 16 battle parts with their keys) | FAIL | PASS |

The old-skin pins were also run against a broken copy of the task's tree (`show_enemies()` returning early in every
skin): `testOldSkinBattleAndCommentaryAreUnchanged` failed on "forest encounter".

### The old-skin pins

`tests/fixtures/battle-commentary-old-skin.json` holds what `cf13929` printed in `jade.htm` for 17 pages: the forest's
search form, encounter, three Fight presses and "For 5 Rounds"; a forest win and a loss; the graveyard's entry,
encounter and two Fight presses; training's entry, challenge and two Fight presses; and the village's commentary (30
comments, 25 shown, with time stamps, a clan tag and a moderator's Del). It was recorded by the test itself
(`RESURRECTION_RECORD_PINS`) in a `cf13929` worktree, twice, with identical results, and again with the worktree's own
`vendor/` (also identical). `mt_rand` is seeded per step, so each fight is the same every run; CSRF and action tokens,
the page counter in links, and account and comment ids are normalized. The pins need no active module (the test skips
them otherwise, which never happens in CI's fresh database). The commentary pin differs from the task's output only by
the removed draft script, which the test removes from the pin by exact text and checks was there once.

## Local verification

| Check | Result |
|---|---|
| PHPUnit, full, on an empty MySQL 8.4 | OK, 319 tests |
| Focused: `BattleCommentaryRenderTest`, `FormsRenderTest`, both state tests | OK, 17 tests, 1,447 assertions |
| Shards 1 to 4 (`scripts/tooling-shards.py`, `RESURRECTION_REQUIRE_PHP=1`, MySQL 8.4, a fresh database each) | OK: shard 1, 76 tests in 19:47; shard 2, 73 in 21:46; shard 3, 80 in 18:42; shard 4, 75 in 21:46 (each after a full PHPUnit on its fresh database) |
| PHPStan `phpstan.neon`; `phpstan-new.neon` (now analysing `lib/battle_view.php`, scanning `lib/template.php`) | no errors; no errors |
| `composer lint`; `composer validate --strict`; `composer audit` | 0 failures; valid; no advisories |
| Historical verifier; project validator | PASS (417 files, 11 commits); PASS |
| `scripts/release-upgrade-check.py` | passing (no release yet) |
| `git diff --check`; `LICENSE.txt`, `README.txt` | clean; unchanged |

`lib/forms.php` is CRLF; the change only removes lines, so its line endings are unchanged.

## Screenshots

Two sheets, not committed, attached to the task's reply: a desktop sheet (1440x1000 tiles) and a phone sheet (390x844
tiles), each tiling a forest fight after three Fight presses, a forest win, the village with seven comments, and the
weapon shop with three affordable items. From a throwaway install with the recommended modules (a Mystical Powers
player, so the Special Abilities group shows).

## Not changed

- Gameplay, balance and the game's text: every line the fight, the shops and the commentary print is the same; new
  strings are only the components' labels ("Level %s", "Round %s", "Battle log", "Victory", "Defeat", "Target", "%s
  uses left", "Comment pages").
- What every form posts: names, fields, tokens and targets.
- The 1.1.2 skins: battle and commentary output byte for byte, but for the removed draft script.
- The moderation screen's comment lines (task 11).
- The copyright line and license block on every page; the attribution line and its links.

## Follow-up for Jim

- The log numbers rounds per request. Numbering across the whole fight needs a round counter in the stored fight
  (forest, Dragon, graveyard, training and PvP schemas).
