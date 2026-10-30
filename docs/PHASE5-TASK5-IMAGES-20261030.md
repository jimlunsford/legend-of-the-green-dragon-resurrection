# Phase 5 task 5: the image pipeline and the first art (2026-10-30)

Jim's brief, "PHASE 5 TASK 5: IMAGE PIPELINE AND THE FIRST ART": the plan's image-pipeline task (plan task 11), pulled
forward by Jim, with the first batch of original art. Expected start: `main` at `5752253` (it matched).

## Task 4 accepted

Recorded in `PHASE5-TASK4-HEADER-FOOTER-20261030.md` ("Accepted") and ticked in `ROADMAP.md`: Modern core
41111713295 SUCCESS, all 12 jobs; Baseline integrity 41111713278 SUCCESS. Both confirmed through the GitHub API.

## The art: checksums verified

Jim attached `resurrection-art-batch1.zip` (8 files, 15,707 bytes). It was unzipped into a scratch folder and every
file checked with `sha256sum -c` against the brief's list before use. All eight matched:

| File | SHA-256 | Bytes |
|---|---|---|
| `portraits/race/dwarf.svg` | `93373dcde21447afd1780e1bb999e6c88d729bf24535f205f354e63a759568d3` | 1,309 |
| `portraits/race/elf.svg` | `c19dcaaf65be923458b48bc12329bef80ba12f26d013237f22e584c4ddeb8927` | 1,145 |
| `portraits/race/human.svg` | `d619bfc4791001d00cf844e28ae493043a589b8912f1dd5f5c41bf67c6337740` | 961 |
| `portraits/race/troll.svg` | `873766832593ceab7c59f5c4fa62f798c3136f5241f57577a7ad91245000591e` | 1,143 |
| `portraits/race/unknown.svg` | `6814704b837cc59c26b20fd80a3e30e81077ee93202b68b15661142d6416a7b8` | 832 |
| `scenes/fallback.svg` | `9869c11fa1588d16db2e5c05774b824e3402409e1f820fa4a891d91a2a6541d5` | 3,188 |
| `scenes/forest.svg` | `26c9d2b6e12e37030d18de04cf0ff6fb2ecf16dda54df51353c6ca34739389e3` | 2,183 |
| `scenes/village.svg` | `5113b4c2d7256e9466f72742d1bd41630757f5904b3dd653eb9db1073eda2e06` | 4,946 |

They are committed byte for byte under `assets/` at those paths, checked again there, and not edited. Each is a plain
SVG: `<svg xmlns>`, a `<title>`, and `rect`, `circle`, `ellipse`, `path`, `polygon` and `g` elements with fill,
stroke and opacity attributes. Scenes are 1200x320, portraits 200x240 (their `viewBox`). They use 38 colors, all
recorded as the art palette in `UI-DESIGN-SPEC.md`.

## Licensing and its scope

- `assets/LICENSE`: "Copyright (c) 2026 Phoenix 2:33 LLC", CC BY-SA 4.0, then the license's full text. Its scope is
  named exactly: `assets/scenes/`, `assets/portraits/` and `assets/icons/`. It says it does not cover `assets/fonts/`
  (SIL OFL 1.1, each family's own `OFL.txt`) or `assets/ui/` (code, under the core license), nor any other file. The
  credit to use: "Art by Phoenix 2:33 LLC, CC BY-SA 4.0". The full text is the SPDX license-list copy of the CC BY-SA
  4.0 legal code (`creativecommons.org` was not reachable from the session; `raw.githubusercontent.com/spdx/license-
  list-data/main/text/CC-BY-SA-4.0.txt`, 18,375 bytes, SHA-256
  `cde7883b9050a1104f4ac19a1572aafd6e5d7323b68351aaf51fbf4beba54966`), unchanged.
- `assets/README.md`: what is here, the license and its scope, the credit, the layout, the batch 1 checksums, and how
  new art is added (from the command chat, with checksums, never edited here).
- `NOTICE`: an "Art" paragraph in the Resurrection section, with the scope and the credit.
- `LICENSING-AND-PROVENANCE.md`: a dated entry for the art.
- No file is labelled AGPL. `LICENSE.txt` and `README.txt` are unchanged.

## The lookup rules (`src/Ui/Art.php`)

Required where used (`src/` has no autoloader): `lib/pageparts.php` requires it.

- **Kind of page** (`resurrection_page_kind()` in `lib/pageparts.php`): an operator page when `DB_NODB` is defined or
  the database state is not `current` (not installed, database error, upgrade needed, game unavailable); a superuser
  or editor page when the page called `check_su_access()` (`$thispage_superuser_level`, set by `lib/su_access.php`);
  otherwise a player page. Popups render through `popup_header()` and `popup_footer()`, whose parts have no scene tag;
  `Art::scene()` also returns nothing for the popup kind.
- **Scene by place**: `home.php` and `village.php` the village, `forest.php` the forest (fights included: they are
  `forest.php?op=fight`), every other player page the fallback. Only player pages show a scene.
- **The `scene` hook** (new): on a player page `modulehook("scene", ['script', 'scene', 'alt'])`. A module may set
  `scene` to another file in `assets/scenes/` (with its own `alt`, or the page's name for that scene) or to `''` for
  none. A name that is not a plain file name (`[a-z0-9-]`) or has no file is ignored.
- **Race portrait**: the race lowercased (`human`, `elf`, `dwarf`, `troll`); any other race, or none, `unknown`.
- **Never a broken URL**: every lookup checks the file exists and returns `{src, alt, width, height}` or null. No alt
  text, no image.
- **Alt text** from the same data: the scene's is the place's name (the `villagename` setting, "The Forest" in the
  `scenes` translation namespace, the page's own title for the fallback), the portrait's the race as the character
  panel shows it. A race not chosen yet (`''`, the column's default `'0'`, or `RACE_UNKNOWN`, as
  `lib/race_onboarding.php` reads it) is named "Horrible Gelatinous Blob" (`RACE_UNKNOWN`, translated), as the panel
  names `RACE_UNKNOWN`; the panel itself still shows `0` there, unchanged. Everything is escaped.

## Showing them

- `templates/resurrection.htm`: two new parts, `scene` (`<div class="rz-scene"><img ...></div>`) and `portrait` (a
  card with the image, the name and the race), and three tags: `{scene}` first inside the content card, `{portrait}`
  in the header (`rz-header-portrait`) and at the top of the character column, before `{stats}`. `page_footer()` fills
  them through `resurrection_scene_html()` and `resurrection_portrait_html()` only when the template has the parts, so
  the 1.1.2 skins show nothing and print no warning.
- `assets/ui/resurrection.css`: the banner is edge to edge inside the card's rounded top corners (negative margins
  equal to the card padding, inner radius 11px), `--scene-height` 240px, `--scene-height-phone` 150px below 600px, the
  image `object-fit: cover` centered, never letterboxed; a 1px `--border` line under it. The portrait is
  `--portrait-width` 64px in its own card at the top of the character panel, beside the name (Cinzel 700) and race
  (`--muted`). Below 600px the panel's copy is hidden and the header's shows, `--portrait-width-phone` 44px, the image
  only, between the wordmark and the controls. `width`/`height` attributes are the files' own sizes.
- `assets/ui/tokens.css` and the spec: the four size tokens.

## Newest resident: cause and fix

Task 4's screenshot of the home page showed "The newest resident of the realm is: 0" on a fresh install. 1.1.2
(`historical-source-1.1.2:home.php`) read `getsetting("newestplayer", "")` as a string; with none stored (`""`, the
installer's default) PHP 5 compared `"" != 0` as false, took the `else` branch and set the name to `""`, so nothing
printed. Phase 2's `a6957a4` (2026-09-13) cast the setting to `(int)` for the SQL; the empty value became `0`, and the
same `else` branch then set the name to `0`, which `0 != ""` printed. The `else` branch is removed: with no newest
player stored, or one whose account is gone, the name stays empty and the line is left out, as in 1.1.2. The query
and the cast are unchanged.

## Spec and ledger

- `UI-DESIGN-SPEC.md`: the content column's row (the banner is in the card), the four size tokens, the Art section
  (fallbacks as built), "Image rules" (sizes, crop, which scene, the hook, the portrait, alt text, the file rules) and
  the "Art palette": all 38 colors with the token each matches, if any.
- `CHANGES-FROM-1.1.2.md`: scene banners, race portraits, the `scene` hook, the newest-resident fix.
- `ROADMAP.md`: task 4 ticked with its runs; task 5 in progress.

## Tests: failing first, passing after

New: `tests/tooling/test_art.py` (static), `tests/unit/ArtTest.php`. Changed: `tests/tooling/test_ui_template.py`
(the two parts and their keys, the two frame tags, a new slot test), `tests/tooling/test_ui_tokens.py` (a new image
size test), `tests/unit/TemplateRenderTest.php` (scenes, portraits, a forest fight, the popup, the newest resident).
Nothing was weakened; the render test's `render()` gained an optional query string.

On `5752253` with the new tests (the art, `Art.php`, the template and CSS changes absent):

| Test | Result on `5752253` | After |
|---|---|---|
| `test_art`: every file is the one supplied | FAIL: 8 files listed, none on disk | pass |
| `test_art`: every SVG is a plain drawing | FAIL: `0 not greater than or equal to 8` | pass |
| `test_art`: every color in the art palette | FAIL: the spec has no "Art palette" section | pass |
| `test_art`: the license names exactly its three folders | ERROR: no `assets/LICENSE` | pass |
| `test_ui_template`: every part the code uses exists | FAIL: `{'scene', 'portrait'}` | pass |
| `test_ui_template`: frame tags are ones the code fills | FAIL: `{portrait}` not in `lib/pageparts.php` | pass |
| `test_ui_template`: the scene and portrait slots | FAIL | pass |
| `test_ui_tokens`: the image sizes | FAIL | pass |
| `ArtTest` | ERROR: `src/Ui/Art.php` not found | pass (8 tests) |
| `TemplateRenderTest` | FAIL: the home page has no village scene | pass (211 assertions) |

`test_art`'s scanner self-test passes on both, as a check of the checker. On `5752253` the render test's home page
also printed "The newest resident of the realm is: 0".

Targeted reverts on the final tree, each restored after:

| Revert | Result |
|---|---|
| `home.php`'s `else` branch put back | `TemplateRenderTest` FAIL: the home page contains "newest resident" with none stored |
| `Art::scene()` ignores the hook | `ArtTest::testAModuleNamesItsOwnScene` FAIL |
| `Art::scene()` shows a scene on every kind of page | `ArtTest::testPopupsSuperuserAndOperatorPagesShowNoScene` FAIL |
| every race gets the unknown portrait | `ArtTest::testEachRaceGetsItsPortrait` FAIL; `TemplateRenderTest` FAIL at the dwarf's portrait |
| a broken SVG (`onload`, an external `<image href>`, `#123456`) added under `assets/` | `test_art` FAIL: the plain-drawing and palette checks |

## Local verification

All four shards, run in parallel through `scripts/tooling-shards.py` with `RESURRECTION_REQUIRE_PHP=1` (PHP 8.5.12,
one MySQL 8.4 container each, PHPUnit first on each empty database, exit 0 on all four):

| Shard | Ran | Failures | Errors | Skipped | Python duration | Wall (with PHPUnit) |
|---|---|---|---|---|---|---|
| 1/4 | 75 (75 assigned) | 0 | 0 | 0 | 15.7 min | 961 s |
| 2/4 | 65 (64 assigned + 1 prerequisite) | 0 | 0 | 0 | 14.9 min | 916 s |
| 3/4 | 68 (67 assigned + 1 prerequisite) | 0 | 0 | 0 | 15.6 min | 958 s |
| 4/4 | 67 (66 assigned + 1 prerequisite) | 0 | 0 | 0 | 13.3 min | 823 s |

The shard copies were taken before one last CSS change (the phone header's column gap, 24px to 12px, so the wordmark
wraps less beside the portrait); no shard test reads that rule, and the static tests were run again after it.

- `vendor/bin/phpunit` on an empty MySQL 8.4 database (PHP 8.5.12): OK, 292 tests.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon` (which covers `src/`, so `Art.php` at level 6): no
  errors.
- `composer lint`: 395 files, 0 failures.
- Historical verifier: PASS (tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`, 417 files, 11 commits).
  Project validator: PASS (712 tracked files, the new files included). Release upgrade check: passing (no release
  yet). `git diff --check`: clean. `LICENSE.txt` and `README.txt` byte-identical to `historical-source-1.1.2`.
  `composer validate --strict`: valid; `composer audit`: no advisories. Static tooling tests (`test_art`,
  `test_ui_tokens`, `test_ui_template`, `test_tooling_shards`, `test_route_inventory`, `test_disabled_endpoints`,
  `test_license_text`, `test_version`): OK.

Local results are supporting evidence; CI is the acceptance.

## Screenshots

1440x1000 and 390x844 of the signed-out home page, the village signed in (a dwarf, two unread letters, an unread
MoTD), a forest fight and the mail popup, taken with Playwright from a throwaway local install with all bundled
modules (not committed; sent to Jim in the task report).

## Not changed

- The art files; the 1.1.2 copyright line, the attribution line, `LICENSE.txt`, `README.txt`.
- The character panel's rows (Name and Race stay; task 7 builds the panel), the old skins (task 15).
- The version string, the CI workflows, the shard timeout, `tests/tooling/shard-durations.json`.
- No tag, release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 5 only
after CI accepts it.
