# Phase 5 task 12: location scenes (2026-11-05)

Task 12 of the Phase 5 plan (`ROADMAP.md`, `docs/PHASE5-UI-PLAN-20261030.md`): the remaining location scenes. Started
from `main` at `28a3936fa426d2fa35e28343731694560f1219aa` (task 11), as the brief expected.

It also records task 11's acceptance (`docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md`, "Acceptance") and fixes a test
that failed on any commit from 2026-11-04 08:15 UTC ("A dated pin test", below).

## The art: batch 2, checksums verified

17 scenes, original SVG drawn by Claude for Phoenix 2:33 LLC, approved by Jim on 2026-11-01, attached to the brief as
`resurrection-art-batch2.zip`. Unzipped outside the repository and checked with `sha256sum -c` against the brief's
list before any use: all 17 OK. Committed byte for byte under `assets/scenes/` (no edit), each listed with its
checksum in `assets/README.md` ("Batch 2") and `tests/tooling/test_art.py`.

| File | SHA-256 | Size |
|---|---|---|
| `scenes/armor.svg` | `115ead5d1ababe762134c2cb6c653b54a00c7e287f628d8b6209897ef7d96f38` | 4,552 |
| `scenes/bank.svg` | `256923ca0f8cc396c610b6eb825d9a1e2d4be8caaff8bfc7d925afef2573e9d6` | 13,667 |
| `scenes/clan.svg` | `b2368ffeb7e15003d7d649094e30ed67bbfc0593419313ac399c8252c890b0ef` | 12,200 |
| `scenes/dragon.svg` | `5d71224de2d40dcd9bf74a262832906e4aaab992391ce11f503ccb7dce5a2770` | 1,812 |
| `scenes/fields.svg` | `fe0528561d41f815efb3caa61db3aae4acd2e6ef896ccf1cfee2e45f3313987e` | 4,246 |
| `scenes/gardens.svg` | `0c854fbac31e7b3890e205f635b8acd5fe32d4c75f4dc6a3fbf1d127e014a5f1` | 4,553 |
| `scenes/graveyard.svg` | `2fc1c01c44c5c28b8b20d69bdd6babc9e5f0fceec0db357484b7195d08f08914` | 4,015 |
| `scenes/gypsy.svg` | `9316f3b8e37847acb090fddde8cf7cd15b8f06fbd5a5c90722be0500d84dec25` | 4,363 |
| `scenes/healer.svg` | `53b7fa5bd9b6ec97103880443c553bf682f0f12052d69c9c49b3f19624aafc26` | 8,169 |
| `scenes/inn.svg` | `326d401485cad52a2962f9f3c53baa39cf7f66577242a2e3d766535eea3e665d` | 7,404 |
| `scenes/lodge.svg` | `8ad19edc6e51efd9e9516e2d2cb8a9f9d2aaf1b27d001e1492a6baa44ab8e29b` | 4,905 |
| `scenes/mercenarycamp.svg` | `2ec7e4d4b7b95b67afb02cf4717815f434c5f3e9e3da59faa614547cc0980a3b` | 4,210 |
| `scenes/rock.svg` | `57e8a5c154725409741de15373a9f4a0972b3a4319d97182aee4bfd85729e280` | 3,914 |
| `scenes/shades.svg` | `aeec36eeeb740d4f6cb2c22fd3df46806710926024f0e1670f548926c54ebcdc` | 3,649 |
| `scenes/stables.svg` | `e316875b62d063d0ea7b708a7a38e82aa8bc29a6bb3c43b9d684bf6be8bbe48e` | 3,897 |
| `scenes/train.svg` | `78c2371f80af364d3e1ed01d6edfa0d9c51841fa6e8c28734e98885524b82133` | 5,964 |
| `scenes/weapons.svg` | `97417d9d10db40dea471030074a08e6d60b719e44c96b32d00129d040fb1d1cf` | 5,780 |

Every file is 1200x320 (`viewBox="0 0 1200 320"`), has a `<title>`, is under 16 KB, is a plain drawing (the static
test's safety checks find nothing), and uses only colors already in the art palette (`docs/UI-DESIGN-SPEC.md`, "Art
palette", unchanged).

## The page mapping

`src/Ui/Art.php`, `PLACES`. Each script was checked to be a player page: none calls `check_su_access()` or
`popup_header()` (the clan detail page checks a moderator flag for its comment tools but is a player page), so all 17
are mapped; none was left out.

| Script | Scene | Alt text |
|---|---|---|
| `inn.php` | `inn` | the `innname` setting (default "The Boar's Head Inn") |
| `bank.php` | `bank` | "Ye Olde Bank" |
| `weapons.php` | `weapons` | "MightyE's Weaponry" |
| `armor.php` | `armor` | "Pegasus Armor" |
| `healer.php` | `healer` | "Healer's Hut" |
| `gardens.php` | `gardens` | "The Gardens" |
| `rock.php` | `rock` | "Curious Looking Rock" (the Veteran's Club, the same script, too) |
| `train.php` | `train` | "Bluspring's Warrior Training" |
| `stables.php` | `stables` | "Merick's Stables" |
| `mercenarycamp.php` | `mercenarycamp` | "Mercenary Camp" |
| `gypsy.php` | `gypsy` | "Ze Gypsy Tent" (the seance too) |
| `lodge.php` | `lodge` | "JCP's Hunter Lodge" |
| `clan.php` | `clan` | "Clan Halls" |
| `graveyard.php` | `graveyard` | "The Graveyard" |
| `shades.php` | `shades` | "Land of the Shades" |
| `dragon.php` | `dragon` | "The Green Dragon's Lair" |
| `pvp.php` | `fields` | "The Fields" |

Task 5's three are unchanged: `home.php` and `village.php` the village (the `villagename` setting), `forest.php` the
forest ("The Forest"). The Green Dragon's approach ("You approach the blackened entrance of a cave") is
`forest.php?op=dragon`, so it shows the forest; "Enter the cave" goes to `dragon.php`, the lair.

## The module mapping

`runmodule.php` now records the module it runs (`$resurrection_running_module`) before calling the module's run
function; `lib/pageparts.php` passes it to `Art::scene()`. `$mostrecentmodule` cannot be used: hooks run during the
page (the footer's included) move it to other modules. A static map, `Art::MODULES`, gives the bundled modules' pages
their place; `Art::MODULE_EDITORS` names the one module page that is an editor. The `scene` hook stays for third-party
modules and is asked with the scene the maps chose.

Each of the 24 bundled modules:

| Module | Scene | Why |
|---|---|---|
| `cedrikspotions` | `inn` | Cedrik's potions are sold at the inn (`header-inn` hook; the page is titled with the inn's name, "Return to the Inn") |
| `dag` | `inn` | Dag Durnick's table is in the inn ("Return to the Inn"). His bounty lists for staff call `check_su_access()`: none |
| `drinks` | `inn` | The drinks are bought at the bar ("Return to the Inn"). The drink editor (`act=editor`): none, also for a player with the module's `canedit` preference, who never passes `check_su_access()` |
| `lovers` | `inn` | Violet and Seth, at the inn (the page is titled with the inn's name) |
| `sethsong` | `inn` | Seth's songs, at the inn (the page is titled with the inn's name) |
| `crazyaudrey` | `village` | Her pages are her visit in the village square (`village` hook; its intent is `crazyaudrey-village`). Her forest event runs in `forest.php`: the forest |
| `outhouse` | `forest` | "The nearby village has two outhouses, which it keeps way out here in the forest" (`forest` hook) |
| `darkhorse` | fallback | The Dark Horse Tavern is its own place, reached from the forest on a mount; no scene is drawn for it. The fallback's alt text is its page title, the module's own `tavernname` setting. Its forest encounter runs in `forest.php`: the forest |
| `game_dice`, `game_fivesix`, `game_stones` | fallback | Played in the Dark Horse Tavern (`darkhorsegame` hook) |
| `racedwarf` | fallback | Its one page, the Great Kegs of Ale, is in the dwarven village, reached only with the `cities` module (not bundled, excluded) |
| `fairy`, `findgem`, `findgold`, `foilwench`, `glowingstream`, `goldmine` | (forest) | Forest specials with an empty run function: they run inside `forest.php` and show the forest. `findgem`, `findgold` and `glowingstream` are also `travel` events, which only the `cities` module runs |
| `raceelf`, `racehuman`, `racetroll`, `specialtydarkarts`, `specialtymysticpower`, `specialtythiefskills` | fallback | No page of their own (an empty run function); their text shows on core pages |

Forest specials confirmed: they are event hooks of `forest` (`module_addeventhook("forest", ...)`), run by
`forest.php`, so `Art::place()` sees `forest.php` and gives the forest (`ArtTest`, `PAGES`).

## Alt text

- A scene's alt text is the place's name. `Art::name()` reads it: the village's and the inn's from their settings
  (`villagename`, `innname`; `Art::NAME_SETTINGS`), so an administrator's rename is used everywhere the scene shows,
  the module pages at the inn included; the others from `Art::NAMES`, the village's own nav labels, translated with
  `translate_inline(..., "scenes")` as task 5's "The Forest" is; the fallback (and a module's scene with no known
  name) the page's own title. Color codes are left out (`full_sanitize()`), and the text is escaped.
- Configurable place names found: `villagename` and `innname` (game settings); the Dark Horse's `tavernname` and the
  race modules' `villagename` (module settings). The tavern's name reaches the alt text through its page title (the
  fallback). The race villages exist only with the `cities` module, so the village scene keeps the game's
  `villagename`. The shops' titles (`weaponstext`, `armortext`, `stabletext`, `mercenarycamptext`) are text a module
  hook may change, not settings; their scenes are named by the village's nav labels.
- New translation keys, namespace `scenes`: the 16 names in the page table above other than the inn's.

## Tests: failing first, passing after

New and changed tests:

- `tests/tooling/test_art.py`: the 17 checksums (batch 2) in `CHECKSUMS`; the existing checks (plain drawing, palette,
  16 KB, the README's tables) cover the new files.
- `tests/unit/ArtTest.php`: five new tests. Every mapped page returns its scene and alt text (and a translated name
  goes through the translator); a renamed inn or village gives the new name (on the inn, the village, the home page and
  module pages); each mapped module returns its place's scene (the drinks bought at the inn show it, the drink editor
  none, and the hook is asked with the module's scene); unmapped pages and modules (every other bundled module, a
  third-party name, `cities`, none) the fallback named by the page; popups, superuser and operator pages none on every
  mapped page and module; the 24 bundled modules are each listed once. Two existing tests used `bank.php` as the
  example of a page without a scene; it now has one, so they use `news.php` (same assertions).
- `tests/unit/SceneRenderTest.php` (new): renders, in the PHP CLI, the inn, the bank, the inn renamed ("`#The Prancing
  Pony`0"), Seth's song (`runmodule.php?module=sethsong`) at the renamed inn and the graveyard of a dead player; each
  shows its scene first in the content card with the place's name as alt text, once. It pins the game clock and puts
  back the inn's name, the song module's state, the player and an install it made.

On `28a3936` with only the new tests (a worktree, its own vendor):

| Test | Result on 28a3936 |
|---|---|
| `test_art.py` | FAILED (2): the 17 files are not on disk (`test_every_file_is_the_one_supplied`); 8 SVGs, not 25 (`test_every_svg_is_a_plain_drawing`) |
| `ArtTest` | 6 errors: `Art::MODULES` and `Art::place()` undefined; `Art::scene()` takes no name function |
| `SceneRenderTest` | FAILED at the inn: `src="assets/scenes/fallback.svg" alt="The Boar's Head Inn"`, not `inn.svg` |

On copies of the final tree, each with one thing broken:

| Broken | Fails |
|---|---|
| `bank.php` unmapped | `ArtTest` (2: the page list, the "module only on runmodule.php" check) and `SceneRenderTest` at the bank |
| The inn named by a fixed English name, not its setting | `ArtTest::testARenamedInnOrVillageGivesTheNewName` and `SceneRenderTest` at the renamed inn |
| `sethsong` unmapped | `ArtTest::testEachMappedModuleShowsItsPlacesScene` and `SceneRenderTest` at the song page |
| `runmodule.php` does not record the module | `SceneRenderTest` at the song page (the unit tests pass the module directly) |
| The drink editor not excluded | `ArtTest::testEachMappedModuleShowsItsPlacesScene` (the editor shows the inn) |
| One byte of `inn.svg` changed | `test_art.py`: the checksum differs |

The final tree: all of them pass (`ArtTest` 13 tests, `SceneRenderTest` 1, `test_art.py` 5).

## A dated pin test

The full PHPUnit run failed one test that this task does not touch, and it fails on `28a3936` too:
`PopupAdminRenderTest::testOldSkinPopupsAndAdminScreensAreUnchanged`, at the mail reply:

```
----Original Message from Farmboy RzPen (NOW)---
+---Original Message from Farmboy RzPen (2026-10-30 08:15:00)---
```

The test's normalizer replaced every time within five days of the clock with `NOW`. The fixture's mail, MoTD and
comment times are fixed (2026-10-29 to 2026-10-31), and were within five days when task 11 recorded the pins; from
2026-11-04 08:15 UTC the oldest mail's time was not, so the test fails on every commit from then on (task 11's own CI
ran on 2026-11-01 and passed). The fix keeps the window for the times a run makes (the last hit, the petitions) and
matches the fixed times against the recording's time, `PINNED_AT` (2026-11-01 12:00 UTC), in place of the clock: the
same times become `NOW` as when the pins were recorded, on any date. The pins and every assertion are unchanged.
Failing before: the test on `28a3936` and on this tree before the fix; passing after: the test alone and in the full
suite on PHP 8.4 and 8.5.

## Local verification

| Check | Result |
|---|---|
| PHPUnit, full, PHP 8.5.12, empty MySQL 8.4 | OK, 330 tests, 147,020 assertions |
| PHPUnit, full, PHP 8.4.27, empty MySQL 8.4 | OK, 330 tests, 147,020 assertions |
| Shards 1 to 4 (`scripts/tooling-shards.py`, `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, MySQL 8.4, a fresh database each, after a full PHPUnit), run in parallel | All OK: shard 1, 51 tests in 23:57; shard 2, 50 in 24:26; shard 3, 51 in 24:29; shard 4, 162 in 24:05 (each count with its prerequisite web test) |
| PHPStan `phpstan.neon`; `phpstan-new.neon` (covers `src/`) | no errors; no errors |
| `composer lint`; `composer validate --strict`; `composer audit` | 0 failures (407 files); valid; no advisories |
| Historical verifier; project validator | PASS (417 files, 11 commits); PASS |
| `git diff --check`; `LICENSE.txt`, `README.txt` | clean; identical to the tag |

The four shards ran at once on a 4-CPU machine, so each took longer than it would alone; no Python test was added or
changed except `test_art.py` (static), so `tests/tooling/shard-durations.json` is not refreshed.

## Screenshots

Two sheets, not committed, attached to the reply and saved in the project files (`phase5-task12/`):
`desktop-sheet.png` (eight 1440x1000 views: the inn, the bank, the weapon shop, the healer, the graveyard and the
shades of a dead player, the training yard, the stables) and `phone-sheet.png` (four 390x844 views at 2x: the inn, the
gardens, the gypsy tent and the dragon's lair itself, a level 15 player through "Seek Out the Green Dragon" and "Enter
the cave"). Every view's scene image loaded with its alt text, and every page's width equals the viewport.

## Not changed

- What any page does: queries, links, forms, permissions, gameplay, balance and text. The only new strings are the 16
  place names (alt text).
- The `scene` hook's arguments and rules.
- The 1.1.2 skins (no scene slot until task 15).
- The art palette, `assets/LICENSE`, the copyright line and license block, the attribution line and its links.
- The 60-minute job timeout and the shard split.
