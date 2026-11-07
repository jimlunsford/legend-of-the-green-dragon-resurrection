# Phase 6 task 7: the email-validation lock, front-door docs (B3), rc.1 cut materials (B4), the release package, the legal review packet (S9)

Date: 2026-11-07. Starting commit: `5f2d95129fa8b1635707c791483458f6e511fea8` (task 6), matched on `origin/main`. The
version stays `1.0.0-dev`. No tag, no GitHub release, no package published.

## Recorded first

- Task 6 accepted at `5f2d951`: Modern core 42329102594 SUCCESS (all 12 jobs, `ubuntu-24.04`), Baseline integrity
  42329102592 SUCCESS. Recorded in `PHASE6-TASK6-HOST-GUIDE-20261107.md` ("Acceptance and decision") and ticked in
  `ROADMAP.md`.
- Jim's decisions of 2026-11-07, in `ROADMAP.md` and `PHASE6-RC-READINESS-20261106.md` ("Decisions"): rc.1 ships only
  the sample server rules in `docs/hosting/`, no active `.htaccess`; S11 is a known limitation for rc.1 and a blocker
  for 1.0.0.

## 1. The lock

### What it does

`requirevalidemail` ("Require users to validate their email address") is locked off until S11 ships.

- **Runtime.** `resurrection_locked_settings()` (`lib/settings.php`) returns `['requirevalidemail' => '0']`.
  `getsetting()` returns a locked value before it reads the cache or the database, so every caller sees `0` whatever
  the settings row holds: `create.php` makes accounts, and Preferences lets a player change their email (1.1.2
  refused that with the setting on).
- **Game Settings.** The layout declares it `locked` (`lib/configuration_descriptors.php`). `SettingDescriptor::declare()`
  skips a locked declaration, so it is never in the savable schema: a form that sends it is refused by `patch()` as an
  undeclared setting, and `configuration.php` answers 400 "Invalid or stale settings." with nothing changed. The editor
  (`lib/typed_editor.php`) draws the row as a disabled select showing "No", with no field name, tied by
  `aria-describedby` to one line: "Locked off. The game cannot verify email addresses yet, so with this on nobody could
  create an account. This is a known limitation of this version: see "Known limitations" in the host guide
  (docs/HOST-GUIDE.md)." `resurrection_editor_locked()` throws if the layout locks a setting `getsetting()` does not
  hold, so the two cannot drift.
- **Style.** `.rz-setting-locked select:disabled` and `.rz-setting-locked-note` in `assets/ui/resurrection.css`.
- **Docs.** `docs/HOST-GUIDE.md`, known limitations and section 6; the change ledger row.

### The settings sweep

Every Game Settings entry (`lib/configuration_descriptors.php`) and every bundled module's settings were read for one
question: can a value break sign-in, account creation or play? Only `requirevalidemail` does, so it is the only lock.
The rest, reported and not changed:

| Setting | What a bad value does | Why it is not locked |
|---|---|---|
| `LOGINTIMEOUT` | An integer with no bound. `0` or a few seconds signs every player out almost at once | It does what it says. A bound (for example 60 to 86400 seconds) is a choice for Jim |
| `maxonline`, `allowcreation`, the `expire*` settings | Turn off sign-in for new players, creation, or delete old accounts | Each works as designed and is the host's call |
| `newdaycron`, `show_notices`, `notify_on_warn`, `notify_on_error`, `notify_address`, `notify_every` | Nothing: no code reads them. `newdaycron`'s notes still cite 1.1.2's cron steps and the dead DragonPrime wiki | Dead settings; removing them is not this task's |
| `beta`, `betaperplayer` | Nothing visible: `pavilion.php` is not in the tree | Dead in practice |
| Translation settings | Change which text table is used | Harmless |
| `csp_mode` | `off` or `report-only` weaken the page policy | Security, not breakage; the host guide says to leave it at `enforce` |
| Module settings (24 bundled modules) | Gameplay values only | None touches sign-in, creation, mail or a removed surface |

### Tests

| Test | What it holds |
|---|---|
| `tests/unit/LockedSettingsTest.php` (new, 3 tests) | `getsetting('requirevalidemail')` is `'0'` with the row at `1`; a locked declaration is never in the schema and `patch()` refuses it; the layout's locked keys equal `resurrection_locked_settings()` |
| `test_web_application.test_requirevalidemail_is_locked_off_http` (new) | Game Settings shows the locked row with its reason and `aria-describedby`; the form has no `requirevalidemail` field; a POST with it at `1` or `0` answers 400 and leaves the settings table unchanged; the form as shown still saves; with the row forced to `1`, `create.php` makes an account; the page still shows it locked |

### Fail first, at `5f2d951`

PHPUnit, PHP 8.5, the new unit test against the starting code:

```
FEE                                                                 3 / 3 (100%)
1) Resurrection\Tests\LockedSettingsTest::testALockedDeclarationIsNeverASavableSetting
DomainException: Unsupported setting declaration.
2) Resurrection\Tests\LockedSettingsTest::testGameSettingsDeclaresEachHeldSettingLocked
Error: Call to undefined function Resurrection\Tests\resurrection_locked_settings()
1) Resurrection\Tests\LockedSettingsTest::testRequireValidEmailIsOffWhateverTheRowHolds
Failed asserting that two strings are identical.
-'0'
+'1'
Tests: 3, Assertions: 1, Errors: 2, Failures: 1.
```

The HTTP test against the starting code stops at its first assertion:
`AssertionError: unexpectedly None : requirevalidemail is not shown locked`. To show that each of the brief's three
checks fails on its own, a variant with each check in its own subTest (evidence only, not committed) ran on a
`git archive` of `5f2d951`:

```
FAIL: ... (check='1 the page shows it locked')
AssertionError: Regex didn't match: 'Require users to validate their email address <select disabled[^>]*><option selected>No</option>' not found
FAIL: ... (check='2 a POST that turns it on is refused and changes nothing')
AssertionError: Tuples differ: (400, [...]) != (200, [...])   (the POST was accepted and the settings changed)
FAIL: ... (check='3 create.php makes an account with the row forced to 1')
AssertionError: 'Account creation is unavailable' unexpectedly found in '<!DOCTYPE html>...<title>Create A Character</title>...
Ran 2 tests in 15.294s
FAILED (failures=3)
```

With the change, both new tests pass (results below).

## 2. Front-door docs (B3)

| File | Change |
|---|---|
| `README.md` | Status line (Phase 6, no release cut, `main` at `1.0.0-dev`, rc.1 waiting on Jim's go and legal review); "What this is" (Phases 1 to 6); "not released" with the release notes link; the local-play note; the CI paragraph; the document list (roadmap and ledger, host guide, release notes and cut checklist, Phase 5 summary and UI spec, readiness review, legal packet); "Modern core work" (work on `main`; PR #1 merged 2026-10-23) |
| `docs/VERSIONING.md` | The gameplay sentence now says gameplay changes one recorded change at a time since 2026-10-30, and takes its number by the rules like any change; new subsection "`main`'s version after a tag"; the version commit rule |
| `SECURITY.md` | A banner: not released, not certified for public game hosting, `public_game_hosting_approved` false, the historical tag never to be hosted |
| `docs/SECURITY-STATUS.md` | The header and the PR #1 line brought up to date; a section "2026-11-07: open today" listing what has closed since the old rows and what is open. The full living record stays S13 |
| `docs/MODULE-POLICY.md` | The certification paragraph: 24 certified, the module manager's authority, 23 active on a fresh install, module code trusted |
| `project.json` | Unchanged. No test reads `phase`; the cut's version commit sets it (`docs/RC-CUT-CHECKLIST.md`) |

Each statement was checked against the code at this commit. Nothing is called released.

## 3. rc.1 cut materials (B4)

- `docs/RELEASE-NOTES-1.0.0-rc.1.md` (new, marked draft and not cut): what Resurrection is, what changed from 1.1.2
  for players and for hosts (from `CHANGES-FROM-1.1.2.md`), operator notes pointing to the host guide, and known
  issues: S11 and the lock, mail, the stale form tokens after signing in again during the new-day questions, S5's small
  and unlabelled controls, the recorded 1.1.2 behaviors, the two data oddities, modules beyond the 24, maintenance not
  being one transaction, no 1.1.2 migration, public hosting not certified, and S9.
- `docs/RC-CUT-CHECKLIST.md` (new): before the cut; the version commit (`src/Version.php`, `project.json`,
  `docs/VERSIONING.md`, the pinned value in `tests/tooling/test_version.py`, the release notes made final, the
  `README.md` and `SECURITY.md` version lines); the checks before pushing it; the CI acceptance rule; the annotated
  tag and its exact message; the package step; the checks after; `main`'s version after the tag.
- `docs/VERSIONING.md`: the rule for `main`'s version after a tag (above).
- The version string is unchanged: `src/Version.php`, `project.json` and `docs/VERSIONING.md` say `1.0.0-dev`, and
  `test_version.py` and `validate-project.py` pass.

## 4. The release package

`scripts/build-release-package.py --out <absolute folder outside the tree>` builds
`legend-of-the-green-dragon-resurrection-<version>.zip` and its `.zip.sha256`, and prints one line of JSON.

- **What it holds.** The files Git lists (`git ls-files -co --exclude-standard`), taken by the host guide's "Upload
  these" entries, read the way `tests/tooling/test_host_guide.py` reads them: a folder takes its files, a file itself,
  a pattern the top folder's names. A file the "Do not upload" list names is refused, never dropped quietly; an upload
  entry that takes nothing is refused too.
- **Deterministic.** Sorted entries, each dated 1980-01-01 00:00, mode 0644, stored (no compression), no comment or
  extra fields, files at the zip's top.
- **Never publishes.** It writes to the `--out` folder only (absolute, outside the repository), and has no network,
  tag or upload code.
- **The guide.** The package is named in section 2; the script is in "Do not upload". The `*.md` entry in "Do not
  upload" now reads "in the top folder", with a note that the short notes in `assets/` and `upgrades/` come with their
  folders: the game needs `upgrades/` to exist (`StepCatalog::ids()` refuses a missing folder), and the server rules
  refuse Markdown anywhere. Without that, the two lists overlapped, and the script refused.

Tests, `tests/tooling/test_release_package.py` (new, 6 tests): the zip's list equals the guide's list from the same
reader as `test_host_guide.py`; nothing denied or generated is inside, and `upgrades/README.md` is; `LICENSE.txt`,
`README.txt`, `NOTICE`, `assets/LICENSE` and the `OFL.txt` files, and every other file, are byte-identical to the tree;
two builds are identical, with the date, stored flag and mode checked; the name carries the version, and an `--out`
inside the repository or relative is refused; the script has no upload, tag or publish code.

Fail first, at `5f2d951`: `FileNotFoundError: [Errno 2] No such file or directory: '.../scripts/build-release-package.py'`
(setUpClass, 0 tests ran).

### The build

At this commit (the package's files did not change after the last code edit):

| | |
|---|---|
| Files | 745 |
| Size | 3,478,219 bytes |
| SHA-256 | `848a08f855450960b5fa1c346837bf3cbeeea03c7473143a10d4504b75257bbe` |
| Two builds into two folders | byte-identical (`cmp`), the same `.sha256` |

### Unzip, install, play

The zip, unzipped into an empty docroot and served the way the host guide says: nginx 1.28.3 with the sample rules
(`docs/hosting/nginx.conf`), PHP-FPM 8.4, MariaDB 11.4, `LOGD_DBCONNECT` naming a private configuration file outside
the docroot, the docroot read-only to PHP.

- `setup.php` with a token file beside the configuration file: the install wrote the configuration file (mode 0600),
  made 39 tables, installed the 24 bundled modules with 23 active, and deleted the token file.
- The first administrator signed in, answered the new day and reached the village (Degolburg Square).
- Game Settings showed "Require users to validate their email address" locked, the select disabled, the reason line
  beside it, and no console errors.
- One forest fight: won, 13 experience and 78 gold.
- The host guide's path checks against this server: 44 refused, 14 served as expected. The one other result is the
  `.well-known/` decoy, which is not in the package, so the request found nothing (expected).

### Screenshots

Two sheets, not committed, attached to the reply and saved in the project files (`phase6-task7/`):

- `sheet1-settings-locked.png`: Game Settings with the locked row, desktop (1440 x 900) and phone (390 x 844).
- `sheet2-package-install.png`: the install from the package, `setup.php`'s last screen and then the village,
  desktop and phone.

## 5. The legal review packet (S9)

`docs/LEGAL-REVIEW-PACKET-20261107.md`: facts and numbered questions only, no conclusions and no wording changes.
Facts: every license with the files it covers (CC BY-NC-SA 2.0 and `LICENSE.txt`; CC BY-SA 4.0 for the three art
folders; OFL 1.1 for the fonts; MIT and BSD-3-Clause for the development packages; the AGPL-3.0-or-later intent, with
no file labelled AGPL); the credits on every page, their HTML, how `prepare_template()` and the safe frame enforce
them and what they cannot stop; what `NOTICE`, `README.md` and the host guide say; the host's duties; money in the
game today. Questions: the attribution line on top of CC BY-NC-SA 2.0; AGPL code beside the NC material and its
labels; whether the host guide states the duties correctly; NonCommercial and donations or advertising; what the art
and font licenses require in the package; the inherited images and modules; the text removed with the disabled pages.
S9 is "packet ready, awaiting counsel".

## 6. Records

- `ROADMAP.md`: task 6 ticked; Jim's decisions; task 7's line; B3 and B4 done, ticked when task 7 is accepted; S9
  "packet ready, awaiting counsel"; the list of what stands between `main` and the `v1.0.0-rc.1` tag.
- `docs/PHASE6-RC-READINESS-20261106.md`: decisions, and the status of B3, B4 and S9.
- `docs/CHANGES-FROM-1.1.2.md`: "Phase 6 task 7" (the lock, the package, `main`'s version after a tag, and what stands
  before the tag).

## What stands between `main` and the `v1.0.0-rc.1` tag

1. CI acceptance of this task's commit: both Modern core legs and Baseline integrity at SUCCESS.
2. Counsel's answers to the legal review packet (S9), and any wording change they bring, landed and accepted by CI.
3. Jim's go for the cut.
4. The version commit and its CI acceptance (`docs/RC-CUT-CHECKLIST.md`, sections 1 and 2).
5. The annotated tag `v1.0.0-rc.1` on the accepted commit, on Jim's go, then the package built from the tag twice
   with the same SHA-256, attached nowhere until Jim says where.

## Local verification

Supporting evidence only; CI is the acceptance. Local MySQL 8.4 containers (one per shard) and MySQL 8.4 for the PHP 8.4
PHPUnit run.

| Check | Result |
|---|---|
| PHPUnit, PHP 8.5.12 | OK, 420 tests, 148,809 assertions (in each shard copy) |
| PHPUnit, PHP 8.4.27 | OK, 420 tests, 148,809 assertions, 1:49 |
| Shard 1 of 4 | 72 tests (72 assigned), OK, 15.3 min of Python tests, 17:21 with PHPUnit |
| Shard 2 of 4 | 70 tests (69 assigned + 1 prerequisite), OK, 15.7 min, 17:45 |
| Shard 3 of 4 | 178 tests (177 assigned + 1 prerequisite), OK, 15.6 min, 17:39 |
| Shard 4 of 4 | 71 tests (70 assigned + 1 prerequisite), OK, 15.1 min, 17:12 |
| `test_tooling_shards.py`, `test_version.py`, `test_release_package.py` (6), `test_host_guide.py` (9) | OK |
| `composer lint`, PHP 8.5 and 8.4 | 444 files, 0 failures each |
| PHPStan, `phpstan.neon` and `phpstan-new.neon` | No errors |
| `composer validate --strict`, `install`, `audit` | valid; installed; no advisories |
| `scripts/verify-historical-baseline.py` | PASS: tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`, 417 files, 11 commits |
| `scripts/validate-project.py` | PASS, 1,018 tracked files checked |
| `git diff --check` | clean |
