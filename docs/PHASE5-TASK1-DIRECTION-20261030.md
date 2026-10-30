# Phase 5 task 1: new direction, governance and UI plan (2026-10-30)

Evidence for Phase 5 task 1. It records Jim's change of direction, the governance changes that follow from it, the
close of Phase 4, and one test made independent of test order. No production code, template, CSS or skin changed.

Starting point: `main` at `8b33edfc8b9cf2f04661d5023011e704a21b485f`, as the task expected.

## Jim's decisions, 2026-10-30

Recorded exactly as decided. They are Jim's decisions, not open questions, except where marked.

1. **The behaviorally faithful rule is dropped, for everything.** Gameplay, balance, text and UI may change when a task
   prompt says so. Each change from 1.1.2 is recorded in docs: old behavior, new behavior, why.
2. **One new UI replaces all skins.** Dark fantasy, built from the mockups Jim approved in the command chat (tokens in
   `UI-DESIGN-SPEC.md`). When the new UI lands, every 1.1.2 skin in `templates/` is removed, along with skin
   selection. Not in this task.
3. **The game gets visuals:** location scenes, creature portraits, item and stat icons, character portraits by race.
   The art is original SVG drawn by Claude for Jim / Phoenix 2:33 LLC.
4. **The game stays PHP on this codebase**, so other people can host it. A clean-room rewrite under
   AGPL-3.0-or-later is a possible future, not started.
5. **Licensing:**
   - The core stays CC BY-NC-SA 2.0. The original credits stay, and are visible on every page. `LICENSE.txt` and
     `README.txt` stay byte-identical to the tag.
   - Jim's original art goes in `assets/` under CC BY-SA 4.0 with its own LICENSE file (created in the art task, not
     now).
   - Jim's standalone code shipped as a separate work: AGPL-3.0-or-later.
   - **Open question for Jim and a lawyer:** AGPL code bundled in the repository and run inside the NC core may leave
     hosts unable to satisfy both licenses. Until Jim decides, no file is labelled AGPL.
6. **The release, tags, deploy and hosting stay excluded until Jim says go.** `vps1.phoenix233.com` is never touched.

## Phase 4 closed

The output buffering fix (`8b33edf`) was confirmed through the GitHub Actions API on 2026-10-30: Modern core
40958421349 SUCCESS with all 12 jobs (shard job times as the command chat reported them: PHP 8.4 / MariaDB 11.4
15:43, 18:04, 16:36, 14:51; PHP 8.5 / MySQL 8.4 12:50, 20:26, 16:50, 18:00), Baseline integrity 40958422672
SUCCESS. Appended to `PHASE4-OUTPUT-BUFFERING-FIX-20261029.md`; ticked in `ROADMAP.md`. Phase 4's planned tasks
(1A, 1B, 2, 3) and the fix are all done, so Phase 4 is marked DONE.

## What changed in `CLAUDE.md`

- **Project:** "A behaviorally faithful modernization" and "Preserve historical behavior. Fix security and runtime
  defects without redesigning gameplay." are gone. New: a modern continuation, in PHP on this codebase so others can
  host it. Gameplay, text and UI change only when a task prompt says so, and every change from 1.1.2 is recorded
  (old, new, why), after checking the historical tag. Security and runtime defects are still fixed and recorded.
- **Phase:** Phase 4 complete, citing `8b33edf` and its runs. Current phase: Phase 5, "Resurrection UI", pointing to
  `ROADMAP.md`, the plan and the design spec. The direction's points 2 to 4 are listed.
- **Release exclusion:** "skin" removed from "hosting, tags, GitHub releases, image, skin and archive-module rights"
  (the skins are going). The release stays excluded until Jim says go.
- **Licensing:** a new section with decision 5, the open AGPL question included, and a line that fonts and other
  third-party files keep their own licenses.
- **Hard exclusions:** "Rebalance gameplay, or rewrite in a framework (React, Vue, Laravel, Symfony)" became "Adopt
  or rewrite in a framework (React, Vue, Laravel, Symfony) unless a task prompt says so". Everything else is kept,
  including `vps1.phoenix233.com`, no deploy/release/tag/hosting, no archive-only modules or Cities, no re-enabled
  disabled surface, and no 1.1.2 database migration.
- **Unchanged:** working branch, historical integrity, versioning (`1.0.0-dev` stays), tests, CI, roadmap rule,
  serialized state, documentation, local toolchain notes, report format.

## What changed in `ROADMAP.md`

- **Goal:** describes Phases 1 to 4 (modernize the code, preserve the game) in the past tense, and the 2026-10-30
  change of direction.
- **Phases table:** Phase 4 DONE at `8b33edf`; Phase 5 "Resurrection UI" CURRENT.
- **Phase 3 "Current":** points to Phase 5 instead of Phase 4.
- **Phase 4:** marked DONE; the fix ticked with its runs; "Phase 4 DONE".
- **Phase 5:** new section: goal, the release exclusion without skin rights, Jim's six decisions, and the checklist:
  this task (ticked only when CI accepts it) and tasks 2 to 15 as the plan proposes them.

## Other docs whose facts changed

- `README.md`, "What this is": no longer says "behaviorally faithful" or "Preserve the game. Modernize the
  implementation."; it says what Phases 1 to 4 did and that gameplay, text and interface may now change one recorded
  change at a time. `README.txt` (1.1.2's) is untouched.
- `docs/MODERNIZATION-PRINCIPLES.md`: a dated "Direction change, 2026-10-30" section appended; the original text is
  kept above it.

## New docs

- `UI-DESIGN-SPEC.md`: the design contract, with the approved palette, type, color code values, layout, components,
  art and accessibility rules. It also lists a value for every other color code `appoencode()` knows (24 more, from
  `` `~ `` to `` `Y ``), each clearing 4.5:1 on the panel and on the raised color, and notes two approved colors
  that are not for text (`--foe` 4.08:1, the borders) and how the spec handles them.
- `PHASE5-UI-PLAN-20261030.md`: the audit of the render path, skin selection, what needs PHP versus template and
  CSS, admin screens and popups, markup-dependent tests, the image pipeline, risks, and the proposed tasks.

## Checks that enforced the old wording

Searched `scripts/`, `tests/` and the docs checks for "behaviorally faithful", "faithful", "Preserve historical",
"Rebalance", the old exclusions, "skin" in the release list, and Phase 4 as current:

- `scripts/validate-project.py`: checks required files, `project.json`, the version and the approval flags; no wording.
- `tests/tooling/test_version.py`: the version string only (`1.0.0-dev`, unchanged).
- `tests/tooling/test_license_text.py`: `LICENSE.txt`, `README.txt` and the about page's original text; unchanged and
  still passing (`LICENSE.txt` and `README.txt` byte-identical to the tag).
- No other test or script reads `CLAUDE.md`, `ROADMAP.md`, `README.md` or the phase.

None enforced the old wording, so no check was changed. The historical-integrity checks and the byte checks are as
they were.

## Test change: `OutputBufferingTest` no longer depends on test order

`testAnInstalledGamePageRendersWithOutputBufferingOff` rendered `home.php` against the database `FreshInstallTest`
leaves installed. Run alone, or before `FreshInstallTest`, it found an empty database and failed.

Now it reads the install state first (in a separate PHP process, from the tree's own `dbconnect.php`):

- `installed`: used as it is (the normal full-suite order, so the suite's end state is unchanged).
- `empty`: installed by the test with `php scripts/install.php --modules=none <config>` (the same state
  `FreshInstallTest` leaves: all 24 modules installed and inactive), then emptied again afterwards (only that install,
  only in the disposable `resurrection_test` database), so a later `FreshInstallTest` still finds it empty. The
  cleanup's result (exit 0, state `empty`, no error output) is asserted after the test body.
- anything else: the test fails, saying the database must be empty or installed.

Every assertion of the old test is kept. The helper that loads the database libraries discards
`lib/all_tables.php`'s trailing newline, as every loader does (the first draft read the state as `"\nempty"`).

| Run (PHP 8.5.12, MySQL 8.4, `vendor/bin/phpunit --filter OutputBufferingTest`) | Result |
|---|---|
| Old test (`8b33edf`), fix in place, empty database | FAIL: "Failed asserting that 255 is identical to 0", `Uncaught RuntimeException: Database operation failed.` (the order dependency) |
| New test, fix in place, empty database | OK (2 tests, 21 assertions); the database is empty again afterwards (0 tables) |
| New test, `common.php`'s fix reverted locally (the `ob_start()` / `ob_end_clean()` around `lib/all_tables.php` removed), empty database | FAIL: "Failed asserting that 255 is identical to 0", `PHP Fatal error: Uncaught ErrorException: Runtime error in common.php:209`; the database was emptied again (0 tables). The revert was not committed |
| Full suite, fresh empty database | OK (283 tests, 141346 assertions) |

## Local verification (supporting evidence only)

PHP 8.5.12 (php-builder, as setup-php) and MySQL 8.4 in Docker:

- `vendor/bin/phpunit` on a fresh empty database: OK, 283 tests, 141346 assertions.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 392 files, 0 failures. `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: 417 files, 11 historical commits, tag object, commit and tree as recorded.
  `scripts/validate-project.py`: PASS. `git diff --check`: clean. `LICENSE.txt` and `README.txt` byte-identical to the
  tag.
- Tooling tests without a database (`test_license_text`, `test_tooling_shards`, `test_version`, `test_baseline`,
  `test_route_inventory`, `test_disabled_endpoints`, `test_local_play`, `test_recommended_modules`, `test_lab`,
  `test_php_runtime`, with `RESURRECTION_REQUIRE_PHP=1`): OK, 46 tests.
- No shared helper changed and no HTTP test was added, so no shard was run locally. CI runs the full suite.

## Not changed

- Production code, templates, CSS, skins, `LICENSE.txt`, `README.txt`, the version string, the CI workflows, the shard
  timeout and `tests/tooling/shard-durations.json`.
- No tag, release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 1
only after CI accepts it.
