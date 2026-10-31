# Phase 5 task 7: character panel (2026-10-30)

Started from `main` at `d0f9415` (task 6), as the brief expected. Plan: `PHASE5-UI-PLAN-20261030.md` (section 1,
`lib/pageparts.php` `charstats()`, and section 3). Spec: `UI-DESIGN-SPEC.md` ("Character panel", "HP and XP bars",
"Stat tiles", "Equipment rows").

## Task 6 accepted

Recorded in `PHASE5-TASK6-NAVIGATION-20261030.md` ("Accepted") and ticked in `ROADMAP.md`: Modern core 41129318951,
SUCCESS, all 12 jobs; Baseline integrity 41129318835, SUCCESS; both on `d0f9415`, confirmed through the GitHub API.

## What 1.1.2 shows

`charstats()` fills sections with `addcharstat()` and `getcharstats()` renders them as label/value rows through the
`statstart`, `stathead`, `statrow`, `statbuff` and `statend` parts:

- "Vital Info": Name, Level, then Hitpoints ("63/70"), Turns, Attack, Defense when alive, or Soulpoints ("40/85", the
  maximum level × 5 + 50), Torments, Psyche and Spirit (both 10 + round((level − 1) × 1.5)) when dead; Spirits ("DEAD"
  when dead); Race.
- "Companions": one row per living companion (or one that cannot die), "hp/max" red below the maximum, green at it,
  " *" when suspended.
- "Personal Info": Gold (Favor when dead), Gems, Experience: the bare number, at every level. Nothing in the panel says
  what the next level needs; only the master's "Question Master" answer does. At level 15, the cap, it is the same bare
  number; `train.php` has no master left ("There is nothing left for you here but memories") and said "None in the land
  are mightier than you!" when the player reached 15, and the forest offers "Seek Out the Green Dragon" until the
  dragon has been sought that day (`seendragon`).
- "Equipment Info": Weapon, Armor, and "Creature" (the mount's name) when the player has one.
- Then the `charstats` hook (modules add rows the same way, to any section), then "Buffs".

Attack and defense show the stored value with the buffs' difference in the bonus (`` `@+ ``) or penalty (`` `$ ``)
color; `check_temp_stat()` appends a temporary stat's note (" (512+5)"). Those meanings are kept.

## The parts added

`templates/resurrection.htm` gains eight parts; `resurrection_charpanel_template()` uses the new panel only when a
template has all of them (`statheader`, `statbar`, `statbarcap`, `stattile`, `stattiles`, `equiprow`, `equiprows`; and
`statportrait` for the image). The 1.1.2 skins have none, so they keep `getcharstats()`, unchanged.

| Part | Fields | What |
|---|---|---|
| `statheader` | `{portrait}`, `{name}`, `{subtitle}` | Opens the card: portrait, name (h2), "Level 7 Elf · Mystical Powers" |
| `statportrait` | `{src}`, `{alt}`, `{width}`, `{height}` | The race portrait `<img>` (`Art::portrait()`) |
| `statbar` | `{kind}`, `{label}`, `{current}`, `{max}`, `{percent}`, `{note}` | A bar with "current / max" |
| `statbarcap` | `{kind}`, `{label}`, `{current}`, `{percent}`, `{note}`, `{caption}` | A bar with the value alone and a caption (the level cap) |
| `stattiles`, `stattile` | `{tiles}`; `{label}`, `{value}`, `{note}` | The two-column grid; one tile |
| `equiprows`, `equiprow` | `{rows}`; `{label}`, `{value}` | The equipment rows |

`charstats()` is unchanged up to the hook, so every row a module reads with `getcharstat()` is still there with 1.1.2's
value. It keeps a copy of the rows as it set them before calling the `charstats` hook, then `resurrection_charpanel()`
renders the card from the account's numbers (bars and tiles get numbers: current, maximum, `exp_for_next_level()`;
attack and defense with the buffs as `charstats()` worked them out), then Buffs (the same list), Companions (the same
rows), and the rows modules added. The panel's `{portrait}` slot in the character column is gone: the portrait is in
the card. The phone header keeps its `portrait` part.

- **Specialty.** The `specialtynames` hook's name for the player's `specialty`; omitted when the player has none or no
  module names the code. Only the new panel calls the hook.
- **Race not chosen yet.** `''`, `0` (the column's default) or `RACE_UNKNOWN` reads "Not chosen yet" (new key), with the
  unknown portrait; the phone header's portrait uses the same name for its alt text (`resurrection_charpanel_race()`).
- **Mount.** The row is labelled "Mount" (new key); its row in `$charstat_info` is still "Creature".
- **Over the maximum.** Hitpoints above the maximum (buffs, healing) print as stored (rounded, as `charstats()` rounds
  them in 1.1.2) over a full bar: the fill is the ratio, one decimal, clamped to 0..100%. Experience past what the next
  level needs (before training) likewise: "40000 / 36071", full.
- **Level cap (decision).** At level 15 no level follows, so "current / max" has no max. The experience bar is full, shows
  the experience alone, and says what the game says there: "Seek Out the Green Dragon" (the forest's nav; namespace
  `forest`) while the player is alive and has not sought the dragon today, otherwise "None in the land are mightier
  than you!" (`train.php` on reaching 15; namespace `train`). Level 14 with the experience for 15 is not the cap: its
  bar reads "current / max" over a full fill until the player trains.
- **The dead.** Soulpoints against level × 5 + 50 in the first bar (kind `soul`, the same `--health` fill); the tiles
  are Torments, Spirits ("DEAD"), Psyche, Spirit, Favor and Gems. Experience as when alive.
- **Colors.** Bonus, penalty and temporary-stat notes keep `charstats()`'s codes and colors beside the tile's number. A
  value's own color codes (a name, a weapon) are kept.
- **Numbers.** Alegreya Sans draws old-style figures by default, so "12" looked smaller than "High"; bar, tile and
  equipment values use lining figures (`font-variant-numeric: lining-nums`).
- **Tile labels.** The mockup's 12px would break the spec's 13px text floor (the same call task 2 made for
  `--size-label`), so tile labels are `--size-label`, 13px. New tokens: `--size-panel-name` 20px, `--size-panel-sub`
  14px, `--size-tile-value` 18px (spec and `tokens.css` together).

## Module rows

`addcharstat()`, `setcharstat()`, `getcharstat()` and the `charstats` hook are unchanged. In the new panel:

- A row a module adds to "Equipment Info" is an equipment row after the mount.
- A row added to any other standard section ("Vital Info", "Personal Info", "Companions"), or to a new section (or to
  "Other Info", where `addcharstat()` puts a row with no section), renders as a label/value row in a card titled with
  its section, after Buffs and Companions, in the order the rows were added; a row named like its section uses the
  `statbuff` part, as `getcharstats()` does. Labels, titles and values are translated and wrapped exactly as
  `getcharstats()` does (`` `&label`0 ``, `` `^value`0 ``).
- A standard row a module changed in the hook shows the module's value in its component (a tile's value, a bar's
  numbers over the account's fill, an equipment row, the name or the subtitle's level or race), so nothing a module
  sets is lost.

No bundled module uses `addcharstat()`, `setcharstat()` or the `charstats` hook (searched in `modules/`), so no bundled
module has a row to lose; the test uses a fixture module.

## The old-skin pin

`tests/unit/CharacterPanelTest.php` renders the panel with the game's own code through
`tests/fixtures/charstats-render.php` (no database: the settings, module and translation functions are stand-ins),
for eight players: alive with buffs (one suspended, one whose name is a sprintf array), companions (one suspended, one
that cannot die at 0, one fallen), a mount and temporary stats; dead; race `0`; race `RACE_UNKNOWN`; level 15; hitpoints
above the maximum; a module's rows; and a bare player. For each it hashes all 18 old skins' panels (a JSON object of
skin => HTML). The eight SHA-256 values were taken on `d0f9415`'s code before any change and are unchanged after. The
old skins also call no hook beyond `charstats`.

## Tests: failing first, passing after

`CharacterPanelTest` (8 tests) and the updated `TemplateRenderTest`, run against `d0f9415` with the new tests copied in
(a `git worktree` of `d0f9415`, PHP 8.5.12, MySQL 8.4):

| Test | On `d0f9415` | After |
|---|---|---|
| `testEveryOldSkinRendersItsPanelAsBefore` | passes (it is the pin); fails on a broken copy (`getcharstats()`'s row value without its closing `` `0 ``: `alive` hash `8045467b…` instead of `75a530f8…`) | passes |
| `testTheNewPanelShowsTheHeaderBarsTilesEquipmentAndBuffs` | fails: no portrait in a header card (null) | passes |
| `testCompanionsRenderAsRowsInTheirSection` | fails: Companions came before Buffs | passes |
| `testHitpointsAboveTheMaximumFillTheBarAndShowTheStoredNumbers` | fails: no bars | passes |
| `testTheLevelCapFillsTheExperienceBarWithTheGamesWording` | fails: no bars | passes |
| `testADeadPlayerShowsSoulpointsTormentsAndFavorInTheSameComponents` | fails: no bars | passes |
| `testARaceNotChosenYetIsNamedSoWithTheUnknownPortrait` | fails: no subtitle | passes |
| `testRowsAModuleAddsAppearInTheirSections` | fails: sections differ | passes |
| `TemplateRenderTest` (village, dwarf, fight) | fails: no `rz-statportrait` | passes |

Existing tests changed for the new behavior, not weakened: `TemplateRenderTest::assertPortrait()` now expects the
panel's portrait in its header card beside the name (the phone header's portrait is checked as before, now the only
`rz-portrait-img`), and the default race's alt text "Not chosen yet" instead of "Horrible Gelatinous Blob";
`tests/tooling/test_ui_template.py` expects `{stats}` alone in the character column and checks the `statportrait` part's
`<img>` like the scene and portrait parts.

## Local verification

PHP 8.5.12, MySQL 8.4 in Docker, Composer from source. Supporting evidence only; CI is the acceptance.

All four shards, run in parallel from copies of the final tree, each with its own empty MySQL 8.4 database,
`RESURRECTION_REQUIRE_PHP=1`, `error_reporting=-1` and `display_errors=1`, as CI's shard jobs (`charstats()` is a shared
helper):

| Shard | Ran | Failures | Errors | Skipped | Python duration | Seconds |
|---|---|---|---|---|---|---|
| 1/4 | 68 (68 assigned) | 0 | 0 | 0 | 13.7 min | 825 s |
| 2/4 | 75 (74 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.5 min | 992 s |
| 3/4 | 67 (66 assigned + 1 prerequisite) | 0 | 0 | 0 | 15.6 min | 935 s |
| 4/4 | 68 (67 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.6 min | 997 s |

The shard copies were taken before this evidence doc was finished; only this doc changed after them.

- `vendor/bin/phpunit` on an empty MySQL 8.4 database (PHP 8.5.12): OK, 306 tests, on each of the four shards.
- `phpstan analyse -c phpstan.neon` and `-c phpstan-new.neon`: no errors.
- `composer lint`: 399 files, 0 failures.
- Historical verifier: PASS (tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`, 417 files, 11 commits).
  Project validator: PASS (the new files included). Release upgrade check: passing (no release yet).
  `git diff --check`: clean. `LICENSE.txt` and `README.txt` byte-identical to `historical-source-1.1.2`.
  `composer validate --strict`: valid; `composer audit`: no advisories. Static tooling tests (`test_ui_template`,
  `test_ui_tokens`, `test_art`, `test_tooling_shards`, `test_route_inventory`, `test_disabled_endpoints`,
  `test_license_text`, `test_version`): OK, 53 tests.

## Screenshots

1440x1000 and 390x844 of the village signed in, a forest fight, a dead player in the Land of the Shades and the weapon
shop (the phone sheet adds each page scrolled to the panel), from a throwaway local install with the recommended
modules (not committed; sent to Jim in the task report).

## Found, not fixed

- A negative temporary stat prints a doubled minus in 1.1.2's note: `check_temp_stat()` writes "`` `$- ``" before a
  value that is already negative ("(12--1)"). Kept: the old skins must stay byte for byte, and the new panel shows the
  same note. Recorded for a later task.

## Not changed

- What `charstats()` stores and what modules read: the rows, their labels and values, `addcharstat()`,
  `setcharstat()`, `getcharstat()`, `getcharstat_value()`, the `charstats` hook. The signed-out online list.
- The old skins' panel (pinned), the compact phone header with HP and XP (task 8), the phone stacking.
- The 1.1.2 copyright line, the attribution line, `LICENSE.txt`, `README.txt`, the version string, the CI workflows,
  the shard timeout, `tests/tooling/shard-durations.json`.
- No tag, release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 7 only
after CI accepts it.

### Accepted (recorded 2026-10-31, Phase 5 task 8)

`2a81203` is accepted. Confirmed through the GitHub API before task 8 started:

- Modern core [41149508932](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/41149508932):
  SUCCESS, all 12 jobs (both `checks` jobs, all eight `http` shard jobs, both `result` jobs), head `2a81203`. Shard
  jobs, from the jobs' start and completion times:

  | Leg | Shard 1 | Shard 2 | Shard 3 | Shard 4 |
  |---|---|---|---|---|
  | PHP 8.4 / MariaDB 11.4 | 11:21 | 19:21 | 15:23 | 14:56 |
  | PHP 8.5 / MySQL 8.4 | 13:12 | 22:07 | 15:48 | 21:05 |

- Baseline integrity [41149508958](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/41149508958):
  SUCCESS, head `2a81203`.

The PHP 8.5 / MySQL 8.4 shard 2 job reached 22:07, the longest so far in Phase 5 (task 6's longest was 20:35, task 5's
21:06). It is under the 30-minute mark, so `tests/tooling/shard-durations.json` is not refreshed; the next tasks
report any shard job past 25 minutes.
