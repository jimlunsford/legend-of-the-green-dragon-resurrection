# Phase 5 task 13: icons, date-proof tests and shard balance (2026-11-05)

Task 13 of the Phase 5 plan (`ROADMAP.md`, `docs/PHASE5-UI-PLAN-20261030.md`): the character panel's icons. Started
from `main` at `34a06174efb1fe2485a285de3561e0cb97a11def` (task 12), as the brief expected. It also records task 12's
acceptance (`docs/PHASE5-TASK12-SCENES-20261105.md`, "Acceptance"), sweeps the tests for fixed dates compared with
the clock, and refreshes the shard durations.

## The art: batch 3, checksums verified

18 line icons, original SVG drawn by Claude for Phoenix 2:33 LLC, approved by Jim on 2026-11-05, attached to the brief
as `resurrection-art-batch3.zip`. Unzipped outside the repository and checked with `sha256sum -c` against the brief's
list before any use: all 18 OK. Committed byte for byte under `assets/icons/` (no edit), each listed with its checksum
in `assets/README.md` ("Batch 3") and `tests/tooling/test_art.py`. Each is a 24px grid (`viewBox="0 0 24 24"`), one
stroke color, 1.8 stroke, round caps and joins, with a `<title>`, about 300 bytes. They pass the existing safety
checks (no script, event attribute, `foreignObject`, `href` or external reference; under 16 KB) and use only colors
already in the art palette, so the palette is unchanged.

| File | SHA-256 |
|---|---|
| `icons/armor.svg` | `c185a72bb8fb4268a65e700dcc74affdf85c0fbfa562c6805bc1300a5bb4c808` |
| `icons/attack.svg` | `143c5b73a3d993ef7741f152ac1a67eb410a85e85fee554bfff78f9ceb058c4d` |
| `icons/buffs.svg` | `20bbc7c6ae06ae8ae6520ac1008880dbcc45ac43fed4059816beb6d8e0d1c1b1` |
| `icons/companions.svg` | `415177aa071d92f674801a3f035b48981691f0fea74c24b2ae404048d7522c04` |
| `icons/defense.svg` | `4b63a78b89728b2918dc97f00cc41d28c441472380229395558537b1e7a8f381` |
| `icons/experience.svg` | `3630c79f29eba85261575ca4dad73f5174ab9638b106cae9d9e746cfe18a60a6` |
| `icons/favor.svg` | `80f70941091987e73f3f03db092103ea0b6947a616c8dd3be2587038a0dd250a` |
| `icons/gems.svg` | `7176f2f7a69577bb7c4e63e173950490c3e3301456d33c66f684b735b230944d` |
| `icons/gold.svg` | `70969655290bf4b09913e31b54de49787531be7587ff2ed7ee482fc6e505298b` |
| `icons/hitpoints.svg` | `da8e73153f8905e2be37c9529f2544a920eb72b8c51e0c7b0d84440ab67eec87` |
| `icons/mount.svg` | `80c954cc51cc27baa3ccef0125852b4c9d677f4908fdac7d097ec7fa6922dd4a` |
| `icons/psyche.svg` | `1bbe230727083ea5fba32fd9544d1959c950ec1f1282c8fe4ce55bbf4b53a01e` |
| `icons/soulpoints.svg` | `05311d11d00dac8f7bbed7d2ae21e4018738f33c0f7208b67c2467158ca64d40` |
| `icons/spirit.svg` | `49a33aa3757f9093e8471bbc2171c4ac2bdbc2c258987ebe384b2d7dd1b55cf3` |
| `icons/spirits.svg` | `f149f22778c999abf8c8d21b5c17123599de42cb2cfd7a47d1929f651c49140e` |
| `icons/torments.svg` | `5c987dd7745f9d34077adb2baaa01bc3f2d36daa72f859f8df27a7084b7e1653` |
| `icons/turns.svg` | `261ba7b4b15f476a47bb7f28b1f89900ae42328b456b40daa9cf7e4e0c83fc13` |
| `icons/weapon.svg` | `c5535706a49bf59fd0be6f29f4d634c9d91ee918fb791adca490676f034eeadc` |

## The lookup

`src/Ui/Art.php`:

- `Art::ICONS`: the 18 names. Each is the English label it stands beside, lowercased.
- `Art::icon($name)`: `['src' => 'assets/icons/<name>.svg', 'width' => 16, 'height' => 16]` for a name in the list
  whose file exists, otherwise null. An unknown name (`''`, `charm`, `Gold`, `gold.svg`, `../scenes/forest`, a scene
  or race name) gives nothing, never a broken URL.
- `Art::iconName($label)`: the label trimmed and lowercased when it is one of the 18, otherwise null.

`lib/pageparts.php`, `resurrection_icon_html($label)`: the icon for an English label, its color codes dropped
(`full_sanitize()`), through the template's new `labelicon` part:
`<img class="rz-labelicon" src="{src}" alt="" aria-hidden="true" width="{width}" height="{height}">`. Nothing when the
label is not a standard stat's, the file is missing, or the template has no `labelicon` part (every 1.1.2 skin). The
label itself is translated as before; the icon is chosen by the English text, so a translation keeps its icon.

## Where each icon is used

Resurrection template only. 16px (`--icon-size-label`, a new token in the spec and `assets/ui/tokens.css`), 6px before
the label, centered on its line.

| Icon | Where |
|---|---|
| `turns`, `spirits`, `attack`, `defense`, `gold`, `gems` | The stat tiles, alive |
| `torments`, `spirits`, `psyche`, `spirit`, `favor`, `gems` | The stat tiles, dead |
| `hitpoints` (`soulpoints` when dead), `experience` | The panel's two bars, and the phone header's two compact bars |
| `weapon`, `armor`, `mount` | The equipment rows (mount only with a mount) |
| `buffs`, `companions` | The Buffs and Companions section headings |

- The template parts `stattile`, `statbar`, `statbarcap`, `equiprow` and `phonebar` have a new `{icon}` before
  `{label}`; only the Resurrection code fills them. The section parts (`stathead`, `statrow`, `statbuff`) are
  `getcharstats()`'s too, for every skin, so they are unchanged: the panel puts the icon at the start of the title it
  passes.
- A row a module adds gets an icon only when its label, color codes dropped, is a standard stat's (a module row
  "`^Gold" gets the gold icon; "Charm", "Clan", "Ring" and module sections get none).
- A stat tile's label is one uppercase word, and TORMENTS with its icon needs 108px. At 1440px the desktop's character
  column is at its narrowest and a tile's content is 81px wide, so the icon first wrapped onto its own line in some
  tiles and not others. The tile is now a size container: under 110px of content every tile's icon stands on its own
  line above the label (4px over it), and in a wider tile (1000px wide, a phone) it sits before the label.
- The 1.1.2 skins are unchanged: task 7's old-skin pins (`CharacterPanelTest`) pass, and no old skin shows an icon.

## Tests: failing first, passing after

New or extended:

- `tests/tooling/test_art.py`: the 18 checksums (batch 3), `icons/` in the listed folders and the README table, and
  `test_every_icon_the_code_names_exists` (`Art::ICONS` is exactly the 18 files; every label the panel and the phone
  header pass is one of them; the template draws icons only through `labelicon`, decorative).
- `tests/tooling/test_ui_template.py`: the keys each panel part takes, `{icon}` included, and `labelicon`'s.
- `tests/tooling/test_ui_tokens.py`, `test_the_label_icons`: the 16px token, the icon's style and the tile rule.
- `tests/unit/ArtTest.php`: every name gives its file at 16px and every file is a name; unknown names give nothing;
  labels give their stat's name and module labels none.
- `tests/unit/CharacterPanelTest.php`: the alive and dead panels carry the right icon as the first child of each
  label, each `alt=""`, `aria-hidden="true"`, 16 by 16, a file that exists, and no icon anywhere else; a module's rows
  get none unless named as a standard stat; no old skin (all 18, three players) shows one. The existing helpers accept
  the icon before a label (and the new test checks it).
- `tests/unit/TemplateRenderTest.php`: the phone header's bars carry their icons (hitpoints, experience; soulpoints
  dead).

On `34a0617`'s production files (`src/Ui/Art.php`, `lib/pageparts.php`, the template, the stylesheets, the spec and
`assets/README.md` from that commit; no `assets/icons/`) with the new tests:

```
test_art: FAIL test_every_file_is_the_one_supplied, test_every_icon_the_code_names_exists, test_every_svg_is_a_plain_drawing
test_ui_template: FAIL test_every_part_the_code_uses_exists
ArtTest: 3 errors (Art::ICONS, Art::icon(), Art::iconName() undefined)
CharacterPanelTest: 3 failures (alive, dead and module icons)
TemplateRenderTest: 1 failure (the phone bars' icons)
```

On broken copies of the final tree:

| Break | Fails |
|---|---|
| `getcharstats()`'s Buffs heading gets an icon and the part guard is removed | `testEveryOldSkinRendersItsPanelAsBefore`, `testNoOldSkinShowsAnIcon`, `test_every_icon_the_code_names_exists` |
| A module's own row gets a default icon | `testALabelGivesItsStandardStatsIconName`, three `CharacterPanelTest` tests |
| The icon is not decorative (`alt="icon"`, no `aria-hidden`) | The three icon render tests, `test_every_icon_the_code_names_exists` |
| `assets/icons/favor.svg` is missing | `test_every_file_is_the_one_supplied`, `test_every_icon_the_code_names_exists`, `testEachIconNameGivesItsFileAt16Pixels`, the dead panel's test |

On the final tree all pass (below).

## Date-proof tests

Every test and fixture under `tests/` was searched for fixed dates and times (`YYYY-MM-DD` literals, `mktime`,
`strtotime` of a literal, "days ago" wording) and each one read for what it is compared with. Then the whole suite ran
a year ahead: PHP and the tests under libfaketime (`faketime -f +365d`), and MySQL 8.4 in Docker with the same
library and offset (`--user mysql`, `LD_PRELOAD` the multithreaded library, `FAKETIME=+365d`), so both clocks read
2027-11-05. On the tree before this task's pins:

| Run, clocks at 2027-11-05 | Result |
|---|---|
| PHPUnit, PHP 8.5, four separate databases | OK, 337 tests, 147,859 assertions, each |
| Shard 1 | OK, 52 tests (failfast off), 29:17 |
| Shard 2 | OK, 52 tests (51 + the account setup), 29:31 |
| Shard 3 | OK, 49 tests (48 + the account setup), 29:51 |
| Shard 4 | OK, 162 tests (161 + the account setup), 29:27 |

A first attempt with only PHP's clock moved was stopped: tests whose fixtures write MySQL's `NOW()` (for example a
ban lasting `DATE_ADD(NOW(), INTERVAL 10 DAY)`) failed only because the two clocks disagreed by a year, which never
happens on a server.

### Found and fixed

| Test | Fixed time | Compared with | Fix |
|---|---|---|---|
| `tests/unit/PopupAdminRenderTest.php` | The mail, MoTD and commentary fixtures, 2026-10-29 to 2026-10-31 | The pin normalizer's five-day window around `time()` | Task 12: matched against the pins' own recording time, `PINNED_AT` (2026-11-01 12:00 UTC) |
| `tests/unit/PlayerMutationTest.php` (`testDagPlacementEligibilityFundsQuotaAndClaimReplay`) | A bounty's `setdate` 2099-01-01, "not yet claimable" | Dag's claim, against the clock | Ten years after the run: `date('Y-m-d H:i:s', strtotime('+10 years'))` |
| `test_web_application.py`, `test_pvp_round_stale_reservation_and_victim_authority` | The victim's `pvpflag` 2099-01-01 (twice) and `laston` 2099-01-01 | PvP's immunity and online checks, against the clock | `years_from_now()`, ten years after the run |
| `test_web_application.py`, `test_dag_funded_pvp_and_failure_rollback` | A bounty's `setdate` 2099-01-01 and the victim's `pvpflag` 2099-01-01 | Dag's claim and PvP's immunity, against the clock | `years_from_now()` |

The 2099 values would have failed only from 2099; they are pinned to the run's own clock so no test carries a
deadline. `years_from_now()` is a new helper at the top of `test_web_application.py`.

Failing first, passing after:

- A year ahead, both clocks moved: `PopupAdminRenderTest` as it stood at `28a3936` (before task 12's pin)
  fails, `testOldSkinPopupsAndAdminScreensAreUnchanged` ("Failed asserting that two strings are identical"); as it
  stands now it passes (5 tests, 1,740 assertions). Clocks at 2027-11-05 11:08 and 11:10 UTC.
- In 2099 (PHP's clock at 2099-06-01; MySQL cannot start past 2038, so it kept the real time): the full PHPUnit suite
  with `34a0617`'s tests fails one test, `PlayerMutationTest::testDagPlacementEligibilityFundsQuotaAndClaimReplay`
  at line 117 ("own bounty still delayed": the 2099 bounty had become claimable); with this task's tests it passes,
  337 tests, 147,859 assertions. The two web tests cannot be shown the same way: with PHP 73 years ahead of MySQL the
  web harness's own account setup fails (`test_create_login_rotate_render_logout`, line 10573) before them, before and
  after the change alike.

### Checked and left

- Times far in the past that the game reads as "long ago" (`2000-01-01` for `lasthit`, `lastnewday`, `laston`,
  `pvpflag`; `2001-01-01` for a ban's last hit; `2020-01-01` and `2020-01-02` for bounties and closed petitions; the
  `2000-01-01` maintenance markers): they stay in the past whenever the tests run.
- Fixed times stored and read back, or compared only with each other, never with the clock: `CommentSnapshotTest`
  (`postdate`), `PvpStateTest` (`pvpflag`, `reservation` in a state the validator checks), `RamiusStateTest`
  (`lastnewday` against the day it is given, `2026-10-04`), `UserEditorTest` (date inputs the editor validates),
  `BattleCommentaryRenderTest` (comment times and `recentcomments`, shown as clock times), and in the web tests a
  bio's `biotime`, clan join dates, a founder comment's `postdate`, a user editor `lasthit` of 2026-10-16 (a past hit,
  as intended) and a forged `current_day=2026-01-01` request value that must be refused.
- Times taken from the clock during the run: the popup render's petitions (whole days before now), the ban, mail and
  day helpers in the web tests (`time.time()`), and the game clock pins (`tests/unit/GameClock.php`, the shards'
  `game_epoch`).
- `tests/characterization/results-20260913.json` (a record, not a test input) and dates in comments.

## The shard split

`tests/tooling/shard-durations.json` is refreshed from this task's final local shard run (PHP 8.5, MySQL 8.4, a fresh
database each, the full PHPUnit suite first, `RESURRECTION_REQUIRE_PHP=1`): every test's printed duration, 313 of 313
discovered tests (the account setup timed in the shard that owns it). The shards ran two at a time, not four, to load
the machine less. The 60-minute job timeout and the runner are unchanged; the lists are the runner's own.

| | Shard 1 | Shard 2 | Shard 3 | Shard 4 |
|---|---|---|---|---|
| Old table: tests | 164 | 47 | 49 | 53 |
| Old table: this run, measured | 25:35 | 25:58 | 26:56 | 26:39 |
| Refreshed table: tests | 52 | 161 | 49 | 51 |
| Refreshed table: predicted from the same times | 26.1 min | 26.3 min | 26.3 min | 26.3 min |

The predicted times include the account setup each shard but the first replays. Locally the old split was already
within 1.4 minutes; the refresh brings the prediction within 0.2.

CI does not run the tests at the local machine's relative speeds. Task 12's Modern core run (41953000057, the old
table) took these shard steps, shards 1 to 4: PHP 8.4 12:07, 12:21, 14:41, 17:49; PHP 8.5 15:38, 16:18, 18:23, 21:17.
Against task 12's local run of the same split (23.9, 24.4, 24.5, 24.1 min), CI ran shard 4 at about 0.88 of its local
time and shard 1 at about 0.65: shard 4 (then the static tests and the longest specialty and editor tests) is slower on
a CI runner than the others. The refresh balances the local times; this task's CI run shows how far that evens the
jobs, and if the gap stays, CI's own per-test durations (in each shard job's log) are the better source for the
next refresh.

## Local verification

| Check | Result |
|---|---|
| PHPUnit, full, PHP 8.5.12, empty MySQL 8.4 (final tree) | OK, 337 tests, 147,859 assertions, no skips |
| PHPUnit, full, PHP 8.4.27, empty MySQL 8.4 (final tree) | OK, 337 tests, 147,859 assertions, no skips |
| Shards 1 to 4 (`scripts/tooling-shards.py`, old table, final tree, PHPUnit first in each) | OK: shard 1, 164 tests in 25:35; shard 2, 48 in 25:58; shard 3, 50 in 26:56; shard 4, 54 in 26:39 |
| The same a year ahead (PHP and MySQL at 2027-11-05; before the 2099 pins) | OK: PHPUnit 337 each; shards 29:17, 29:31, 29:51, 29:27 |
| `test_art.py`, `test_ui_template.py`, `test_ui_tokens.py`, `test_tooling_shards.py` with the refreshed table | OK |
| PHPStan `phpstan.neon`; `phpstan-new.neon` | no errors; no errors |
| `composer lint`; `composer validate --strict`; `composer audit` | 0 failures (407 files); valid; no advisories |
| Historical verifier; project validator | PASS; PASS |
| `git diff --check` | clean |

## Screenshots

Not committed; attached to the task's report (`/mnt/project-files/phase5-task13/`). A copy of the final tree on its own
MySQL 8.4 database, the recommended modules, one player (a level 9 elf with Mystical Powers, a pony, two buffs and a
mercenary), `php -S`, Playwright at a device scale of 2:

- `desktop-panel-alive-dead.png`: the character panel at 1440x1000 in the village (alive) and the graveyard (dead),
  side by side, cropped to the panel.
- `phone-village-graveyard.png`: the village and the graveyard at 390x844, the phone header's bars with their icons.

Every icon image loaded (`naturalWidth` above 0) with `alt=""` and `aria-hidden="true"`, 16px wide; no page scrolled
sideways.

## Not changed

- The 1.1.2 skins, their panel and `getcharstats()`.
- The art palette, `assets/LICENSE`, the copyright line and license block, the attribution line and its links.
- Translation keys (the icons are chosen by the English label; no text is added).
- The 60-minute job timeout and the shard runner; the shard lists are not edited by hand.

## Acceptance (recorded in task 14, 2026-11-05)

Task 13 is accepted at `6e2f55b44421bf8de56504578dee82359f773b14`. Checked by the command chat and confirmed through the
GitHub Actions API in task 14:

- Modern core 42029166571: SUCCESS, all 12 jobs (both legs' checks, four shards and result). Shard jobs, shards 1 to 4:
  PHP 8.4 / MariaDB 11.4 19:48, 17:22, 20:36, 20:13; PHP 8.5 / MySQL 8.4 16:59, 23:14, 16:25, 20:50.
- Baseline integrity 42029165803: SUCCESS.

The shard steps inside those jobs (the `tooling-shards.py` step alone), shards 1 to 4: PHP 8.4 17:49, 15:20, 18:42,
18:14; PHP 8.5 15:08, 20:57, 14:37, 18:40. With the refreshed table the PHP 8.4 jobs are within 3:14 of each other
(task 12: 6:03); on PHP 8.5 shard 2, now the 161 short tests, is the longest at 23:14, under 30 minutes.
