# Phase 4 task 2: the version number and the upgrade system (2026-10-29)

Starting `main`: `504b5aafd2758880e72fec9ca744191e6e59b221` (task 1B). Task 1B's acceptance is recorded in
`PHASE4-TASK1B-FRESH-INSTALL-20261029.md` (Modern core 40800637109, all 12 jobs; Baseline integrity 40800637058).

Nothing is released in this task, and no tag is created. Every tag used below was created in a throwaway repository
in a temporary directory, never in the project.

## Design in brief

1. **One version number.** `src/Version.php` holds `CURRENT = '1.0.0-dev'`. The footer and the home page show
   "Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition". `project.json` and `docs/VERSIONING.md` must say
   the same, and a test fails if they do not. The version is only shown. It is no longer the install marker.
2. **Schema state is a ledger.** The `upgrade_steps` table holds one row per applied step, with its UTC time. Steps
   are numbered files in `upgrades/`, run in number order. Each one checks for its change before making it, and is
   recorded only after it fully succeeds. A fresh install records every step it ships with.
3. **Bundled modules upgrade through the module lifecycle.** An upgrade is detected when a bundled module's stored
   version is older than its file's version. The upgrade then runs the module's install function again, the way 1.1.2
   did when a module file changed.
4. **The game reads the state.** When the database is behind, every page shows 1.1.2's "Upgrade Needed" message with
   the commands to run, and `cron.php` refuses to run. A newer database (a downgrade), a broken step record, a 1.1.2
   game or an incomplete install gets "Game Unavailable" with its reason.
5. **`php scripts/upgrade.php`** is CLI only. It uses the install's configuration and takes the install and
   maintenance locks. `--dry-run` lists what is pending. `--backup-confirmed` is required to apply. It applies and
   records each step, then the module upgrades, and stops at the first failure. Modern core proves the upgrade path:
   it installs the latest release tag (none yet), upgrades it, and compares it with a fresh install.

## A. One version number

### What 1.1.2 showed, and where

At `historical-source-1.1.2`, `common.php:39` set `$logd_version = "1.1.2 Dragonprime Edition";`. That one string
was used in six places:

| Use in 1.1.2 | Where | Now |
|---|---|---|
| Footer `{version}`: "Version: 1.1.2 Dragonprime Edition" | `lib/pageparts.php:360` | "Version: Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition" (`Version::display()`) |
| Home page: "Game server running version: 1.1.2 Dragonprime Edition" | `home.php:128` | "Game server running version: Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition" |
| About page: "LoGD version 1.1.2 Dragonprime Edition", inside the section that "may not be modified" | `lib/about/about_default.php:39-40` | Unchanged. `$logd_version` is still the base string (`Version::BASE`), so the page shows the same text |
| The install marker: the `installer_version` setting, written by the installer and compared with `$logd_version` | `common.php:171,226`, `lib/installer/installer_stage_10.php:33,37,53` | Retired; see below |
| Translation rows record the version they were written for | `translatortool.php`, `untranslated.php` | Unchanged: still `$logd_version`, the base. A translation belongs to 1.1.2's text, which Resurrection keeps |
| LoGDnet registration (`v=`) | `lib/pageparts.php:234-235` | LoGDnet stays disabled (`CLAUDE.md`); unchanged |

The about page's text and the copyright line are byte-for-byte as before. `tests/tooling/test_license_text.py`
(the required-text test) passes unchanged.

### The source of truth, and why `1.0.0-dev`

- `src/Version.php` holds `CURRENT` (Resurrection's version) and `BASE` (the preserved game's own name for itself).
  `common.php` sets `$logd_version = Version::BASE`. The footer and home page call `Version::display()`.
- `project.json` `"version"` (until now `null`) and the line "Current version: `1.0.0-dev`" in `docs/VERSIONING.md`
  must match `CURRENT`. Both `tests/tooling/test_version.py` and `scripts/validate-project.py` check this.
- Why `1.0.0-dev`: the first release is 1.0.0 and its test builds are `1.0.0-rc.N` (`CLAUDE.md`). Under SemVer,
  pre-release identifiers compare in ASCII order, and `dev` sorts before `rc`. So a development build never looks
  newer than a test build of the same release. The old `VERSIONING.md` said "0.x during development". No 0.x version
  was ever set (`project.json` held `null`), so nothing was ever numbered 0.x.
- The allowed forms are `MAJOR.MINOR.PATCH`, `-dev` and `-rc.N` with N ≥ 1. The test checks the full SemVer 2.0.0
  grammar first, then the project's forms. So `1.0.0-beta`, `1.0.0-rc.0`, `1.0.0+build.1`, `v1.0.0` and the base
  string all fail.

### The shown version is no longer the install marker

1.1.2's game ran only when `getsetting("installer_version")` equalled `$logd_version`. Otherwise it showed "Upgrade
Needed" and sent admins to the web installer. That made the display string the schema marker: changing the shown
version would have locked every game.

Now:

- The game decides whether it can run from two things: the completion marker `resurrection_install = complete`
  (1B) and the ledger (`lib/upgrade.php`, `resurrection_database_state()`).
- A fresh install no longer writes `installer_version`.
- A 1.1.2 database is still recognized by 1.1.2's own `installer_version` 1.x setting, so it stays unsupported.
- `test_version.py` fails if `common.php` again compares `$logd_version` or reads `installer_version`.

The new code reads the settings array directly, not through `getsetting()`. `getsetting()` writes its default when a
setting is missing, and a page that only checks state must not write.

## B. Schema state

### The ledger and the steps

- **The ledger.** The `upgrade_steps` table has `step varchar(64)` as its primary key and `applied datetime` (UTC).
  It is part of `resurrection_fresh_schema()`, so a fresh install has 39 tables.
- **Recording at install.** The install reads the step list before its first table change. It records every listed
  step in its final transaction, together with the administrator and the completion marker. A fresh schema already
  contains what those steps would make.
- **Step files.** A step is `upgrades/NNNN-words.php`: four digits, then lower-case words joined by hyphens, at most
  64 characters. `src/Upgrade/StepCatalog.php` lists steps in number order. It refuses an unknown file, a bad name or
  two steps with the same number. A step file must return exactly a non-empty `description` and an `apply` closure.
  Including the file runs nothing.
- **No transactions.** The runner does not wrap steps in a transaction. MySQL and MariaDB commit every table change
  as soon as it runs, so a transaction would only pretend. Each step instead checks for each change before making it
  ("check before change"). It is recorded only after `apply` returns. A step that stops partway runs again from the
  start on the next upgrade and skips the changes it already made. The partial-failure test shows exactly this.
- **Reading and checking first.** Before anything changes, every pending step is read and checked. A broken step file
  therefore stops the upgrade before its first step runs.
- **States** (`resurrection_upgrade_state()`):

  | State | Condition | Game | Command |
  |---|---|---|---|
  | `ahead` | The ledger has a step this code does not have, or a bundled module's stored version is newer than its file's | "Game Unavailable": a newer version upgraded the database | Refused as a downgrade, nothing changed |
  | `out-of-order` | A pending step comes before an applied one | "Game Unavailable" | Refused, not repaired: restore the backup |
  | `behind` | No ledger, a pending step, or an older bundled module | "Upgrade Needed" | Applies |
  | `current` | None of these | Runs | "The database is current … Nothing was changed." |

  The game checks first that the completion marker is set and that the database is not a 1.1.2 game. Those two keep
  their 1B refusals.
- **Missing ledger.** A development database installed before this task has no ledger, so it counts as behind. The
  upgrade creates the ledger. No real steps exist yet, so nothing else is pending.

### Bundled module upgrades, and how a change is detected

1.1.2's `injectmodule()` compared a module file's modification time with the stored `filemoddate`. When the time had
changed, it wiped the module's hooks and ran its `_install()` again. Resurrection cannot use the file time: a git
checkout or archive sets every file's time to the checkout time. Every deploy would then look like a change to all
24 modules, and a real change made in the same second would look like none.

Resurrection detects a module change by **version** instead:

- `resurrection_bundled_module_versions()` (`lib/installer/bundled_modules.php`) lists each bundled module's
  `getmoduleinfo()` version.
- A stored version older than the listed one is a pending module upgrade. A newer one is a downgrade.
- Versions are compared with 1.1.2's own `module_compare_versions()`.
- `UpgradeTest::testBundledModuleVersionsMatchTheModuleFiles` reads every module file and fails if the list and the
  files disagree. A module author therefore cannot raise a version without the upgrade seeing it.

`resurrection_upgrade_module()` (`lib/modules.php`) runs the upgrade through the module lifecycle, in 1.1.2's order:

1. It requires the module's row and file, and checks the module's requirements.
2. It sets `$mostrecentmodule`, calls `module_wipehooks()` and `_install()`, and clears the module caches.
3. It updates the stored metadata last, so a failed install leaves the old version stored and the upgrade pending.

The metadata is written as a fresh install writes it, and the active or inactive state is kept. A module the operator
uninstalled has no row, so it is not upgraded. Non-bundled modules are not touched.

### The game and the scheduler

- **Upgrade Needed.** `common.php` keeps the "Upgrade Needed" page for a database that is behind: 1.1.2's first and
  last sentences, the 30-second refresh and `NO_SAVE_USER`. It now names the commands: "take a fresh backup, see what
  is pending with `php scripts/upgrade.php --dry-run`, then apply it with `php scripts/upgrade.php --backup-confirmed`".
  1.1.2's link to the web installer stays gone; that installer returns 403.
- **Game Unavailable.** The other states get "Game Unavailable" with their reason and no refresh, because waiting does
  not fix them.
- **The scheduler.** A command run (`cron.php`) throws instead of printing a page, so maintenance refuses with exit
  code 1 and never runs on a database it cannot use.

## C. `php scripts/upgrade.php`

- Over HTTP it returns 404 "Not found." before loading anything. This is checked in `test_disabled_endpoints.py`.
- It takes one optional configuration path, defaulting to `dbconnect.php`, the same file the install and the game use.
- Without `--dry-run` or `--backup-confirmed`, it refuses, says why a backup is needed (table changes cannot be rolled
  back), and points to the docs. Both flags together, or an unknown option, are also refused. Every refusal ends
  "Nothing was changed." and exits 1.
- **Locks.** It takes the install lock (`resurrection_install_lock_name()`, now shared with the installer) and the
  maintenance lock (`resurrection_maintenance_lock_name()`), both with no wait. If either is held, it refuses. While
  the upgrade holds them, an install and `cron.php` cannot start.
- **Order.** It applies the pending steps in order, then the module upgrades, and prints each. The last line says the
  database is current.
- **Failures.** It stops at the first failure with "Upgrade stopped: step X failed (file:line) and was not recorded.
  The database is at the last good step: Y. Nothing after the failure ran. …". The message tells the operator to fix
  the cause and run the upgrade again, or to restore the backup. No exception text, SQL, values or configuration is
  printed.

The operator steps (backup, dry run, upgrade, verify, restore) are in `MODERN-INSTALLATION.md`, "Upgrading".

## D. Tests

### The equivalence test

`UpgradeTest::testAnUpgradeFromAnOlderStepMatchesTheFreshInstall` uses three test-only steps in
`tests/fixtures/upgrade-steps/equivalence/`. Each one makes a change that the fresh install at this commit already
has: a unique `accounts.login` index (a table change), the `accounts.lastnewday` column (a table change) and the
`serverlanguages` setting (a row). Treated as this commit's steps, they would have been recorded by the install. The
test works like this:

1. It snapshots the whole installed database (`tests/support/database_snapshot.php`: every table definition and
   every row) and records the three steps. That is the fresh-install target.
2. It undoes step 0003 only. The upgrade applies one step, and the database equals the target.
3. It undoes all three. The accounts definition now differs, and the upgrade applies all three in order. The database
   equals the target.
4. A further upgrade reports "current" and changes nothing.
5. It removes the ledger rows. The database equals the original snapshot.

The fixture checks first that undoing loses nothing (every `lastnewday` is still empty).

### The release upgrade check

`scripts/release-upgrade-check.py` runs in Modern core's `checks` job, on the empty test database, before the PHP
tests. It picks the highest tag `vMAJOR.MINOR.PATCH` or `vMAJOR.MINOR.PATCH-rc.N`, by SemVer order, that is an
ancestor of the commit under test and not that commit itself. It then:

1. installs that release with its own `scripts/install.php`, from `git archive`;
2. upgrades it with this commit's `scripts/upgrade.php --backup-confirmed`;
3. snapshots the database and installs this commit fresh;
4. fails unless the two snapshots are the same.

Values two installs can never share are blanked: times, the salted administrator hash, module file times and the
`lastdboptimize` text time. Whether each one is set is kept.

With no release tag it prints "Release upgrade check: no release yet. … Passing." and exits 0. That is the case today:
the only tag is `historical-source-1.1.2`, which does not match. A second step runs it with `--release-ref HEAD`, so
the install, upgrade and comparison run on every commit even before a release exists.
`tests/tooling/test_release_upgrade.py` proves the check switches on once a tag exists. It builds throwaway
repositories and checks:

- non-release tags give "no release yet";
- `v1.0.0-rc.1` switches the check on;
- `rc.10` sorts after `rc.2`, and a release after its test builds;
- a tag on HEAD, or off its history, is not used;
- the project's own result matches its tags;
- Modern core runs both steps before `composer test`.

Proof that the check catches a missing step, run locally (PHP 8.5.12, MySQL 8.4) in a throwaway copy of this
commit's tree with its own git history:

| Case | Result |
|---|---|
| `--release-ref HEAD` | PASS: "HEAD installed, upgraded to d46cc4d, and the same schema and data as a fresh install (39 tables)", report "The database is current: 0 of 0 upgrade steps applied …" |
| A commit adds `accounts.example` to the fresh schema without a step; release `HEAD~1` | FAILED, exit 1: "HEAD~1 upgraded to d46cc4d differs from a fresh install of d46cc4d: table accounts: definition differs" |
| The next commit adds `upgrades/0001-add-accounts-example.php`; release `HEAD~2` | PASS: "Pending: step 0001-add-accounts-example … applied and recorded step 0001-add-accounts-example. Upgrade complete: applied 1 upgrade step(s) … The database is current." |
| Tag `v1.0.0-rc.1` on that release commit (throwaway repository only), no `--release-ref` | PASS, upgrading from `v1.0.0-rc.1`, with the same report |

### Tests added or changed, and each failing first

"Before" means the new tests were run against task 1B's code (`504b5aa`, extracted with `git archive`) with only the
test files copied in.

| Test | What it proves | Before the change (`504b5aa`) |
|---|---|---|
| `tests/tooling/test_version.py` (new) | Code, `project.json` and `VERSIONING.md` agree on a valid version; any disagreement or invalid form fails; `BASE` stays "1.1.2 Dragonprime Edition"; `common.php` no longer uses the shown version as the marker | 3 errors: `src/Version.php` does not exist. The check run on 1B's files reports `VERSIONING.md` does not state a version and `project.json` is `None`, not a valid semantic version |
| `tests/tooling/test_release_upgrade.py` (new) | Picks the release or says "no release yet"; switches on with a tag; SemVer order; HEAD and off-history tags excluded; workflow order | Errors: `scripts/release-upgrade-check.py` does not exist |
| `tests/unit/UpgradeTest.php` (new, 11 tests) | The catalog; module versions match the files; a current database untouched and the backup flag; a missing ledger; equivalence; order; re-run after a partial failure; dry run; the lock; refusal of a newer, out-of-order database; bundled module upgrades through the lifecycle ending as the fresh install. Each leaves the database identical to its snapshot | Error at `UpgradeTest.php:27`: `lib/upgrade.php` does not exist |
| `tests/unit/FreshInstallTest.php` (changed) | A step in `upgrades/` during the install is recorded and never run; no `installer_version` row; `--modules=none` leaves an empty ledger and a current database; Dag and Drinks hooks stay under their own module | Error at `FreshInstallTest.php:117`: table `upgrade_steps` does not exist |
| `test_web_application.py`: `test_upgrade_needed_page_and_copyright_footer_http` (rewritten) | No `installer_version` row; an older bundled module shows "Upgrade Needed" with the operator line for an anonymous visitor and a signed-in player, with the footer and copyright; `cron.php` refuses and the maintenance day is unchanged; the dry run lists it; `--backup-confirmed` upgrades it; the game returns | FAIL: `[] != [{'value': '1.1.2 Dragonprime Edition'}]` (1B still wrote `installer_version`) |
| `test_web_application.py`: `test_game_unavailable_pages_for_a_database_it_refuses_http` (new) | A newer module, a newer step, a 1.1.2 game and an incomplete install each show "Game Unavailable" with their reason, and `upgrade.php` refuses each | FAIL in every subtest: the newer module rendered the normal page; the newer step's fixture failed (no ledger); the 1.1.2 game showed "Upgrade Needed"; the incomplete install failed its assertion |
| `test_disabled_endpoints.py`: `test_web_upgrade_command_rejected` (new) | GET and POST `/scripts/upgrade.php` return 404 "Not found." | FAIL: `b'No such file.\n' != b'Not found.\n'` |
| `test_operator_pages.py` (changed) | The footer shows "Version: Resurrection {CURRENT}, based on LoGD 1.1.2 Dragonprime Edition" | Error: `src/Version.php` does not exist |

Each guard was also reverted one at a time in a copy of the finished tree, with the test that should catch it run on a
freshly installed database (PHP 8.5.12, MySQL 8.4). Every one failed with the guard reverted and passed with it back:

| Guard reverted | Test | Fails at |
|---|---|---|
| Steps sorted in reverse (`StepCatalog`) | `testStepsRunInNumberOrderAndOnlyPendingOnesRun` | `UpgradeTest.php:264` |
| A step recorded before it runs | `testAReRunAfterAPartialFailureFinishesTheUpgrade` | `UpgradeTest.php:297` |
| The dry run applies | `testADryRunListsWhatIsPendingAndChangesNothing` | `UpgradeTest.php:321` |
| The lock always granted | `testTheLockKeepsUpgradesInstallsAndMaintenanceApart` | `UpgradeTest.php:339` |
| The downgrade refusal removed | `testANewerDatabaseIsRefusedAndNotChanged` | `UpgradeTest.php:390` |
| The backup flag not required | `testACurrentDatabaseIsLeftUntouchedAndApplyingNeedsABackup` | `UpgradeTest.php:174` |
| A current database not recognized | `testACurrentDatabaseIsLeftUntouchedAndApplyingNeedsABackup` | `UpgradeTest.php:176` |
| A missing ledger counted as current | `testAMissingLedgerIsBehindAndTheUpgradeCreatesIt` | `UpgradeTest.php:192` |
| A module upgrade without its install function | `testABundledModuleUpgradeRunsTheModuleLifecycleAndMatchesTheFreshInstall` | `UpgradeTest.php:443` |
| An equivalence step whose column differs from the fresh schema (`varchar(12)`) | `testAnUpgradeFromAnOlderStepMatchesTheFreshInstall` | `UpgradeTest.php:245` |
| The install not recording the steps | `FreshInstallTest` | `FreshInstallTest.php:117` |

## Decisions and their reasons

- **The fresh install stops writing `installer_version`.** It was 1.1.2's marker that the shown version was installed.
  Keeping it would tie the display to the schema again. A 1.1.2 database is still recognized by its own
  `installer_version` 1.x.
- **Module changes are detected by version, not file time.** File times do not survive git (see above). A static list
  checked against the files keeps detection exact: `UpgradeTest` fails when a module's version is raised without the list.
- **Module metadata after an upgrade is written as a fresh install writes it,** so an upgraded database equals a fresh
  one. Rows the module manager wrote have two more infokeys (`description`, `requires`). Only the flags in infokeys are
  ever read, and those are the same.
- **The home page shows the Resurrection version too,** with the base credited, as the footer does. 1.1.2 showed the
  same string in both places.
- **Translations keep the base version** (`translations.version`), because their text is 1.1.2's.
- **`cron.php` refuses a database that is not current,** so maintenance never runs on an old schema or on a database
  this version refuses.
- **A ledger missing on a pre-task-2 development database counts as behind,** and the upgrade creates the ledger.
- **The release check also runs with `--release-ref HEAD`,** so the install, upgrade and snapshot comparison are
  exercised on every commit before the first tag exists.
- **Upgrades go one release at a time.** Skipping releases is neither tested nor supported (`VERSIONING.md`).

## Local verification

Supporting evidence only; CI is the acceptance. PHP 8.5.12 with MySQL 8.4 in Docker.

- PHPUnit, full suite on a fresh database: OK, 281 tests, 141308 assertions (270 tests before this task).
- All four HTTP shards in parallel, each after PHPUnit on its own fresh database, with `RESURRECTION_REQUIRE_PHP=1`:
  shard 1 ran 65, shard 2 ran 57, shard 3 ran 57, shard 4 ran 58 (each count includes its account-setup
  prerequisite where it has one), all with 0 failures, 0 errors, 0 skipped; 22.9 to 24.6 minutes each.
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`): no errors. PHP lint: 391 files, 0 failures.
- `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: 417 files, 11 historical commits. `scripts/validate-project.py`: PASS.
  `git diff --check`: clean.
- `python3 scripts/release-upgrade-check.py --plan` in the project: "no release yet" (the only tag is
  `historical-source-1.1.2`).
- The installed database has 39 tables; `upgrade_steps` is InnoDB, utf8mb4_unicode_ci.

## Not changed

- The about page's text, the copyright line, `LICENSE.txt` and the credits.
- The historical tag `historical-source-1.1.2`. No tag was created.
- No 1.1.2 database is upgraded or migrated. The 60-minute CI timeout is unchanged. The serialized-state inventory's
  source is unchanged, so it was not recounted.
- Public hosting remains **NO**.
