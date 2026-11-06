# Phase 6 task 5a: shared services, operator frame, cron fix, web maintenance (S3 part 1) (2026-11-06)

Jim's brief, "Phase 6 task 5a: shared services, operator frame, cron fix, web maintenance (S3 part 1)". Expected start:
`main` at `668cf4e09e053a3af048a3da220db795c1f24ac6` (it matched). The design is
`PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`, approved by Jim on 2026-11-06 with every recommendation and two amendments,
now recorded there under "Decisions (approved 2026-11-06)" with task 4's acceptance (Modern core 42247060344, 12/12;
Baseline integrity 42247060378). `ROADMAP.md` ticks task 4 and splits task 5 into 5a (this task) and 5b (`setup.php`).
No version string, tag or release changes. Every change an operator or administrator can notice is in
`CHANGES-FROM-1.1.2.md` ("Phase 6 task 5a").

## Item 1: one service per operation

The bodies of the three commands moved into functions; the commands kept their argument parsing, environment reading,
output and exit codes.

| Service | File | Called by |
|---|---|---|
| `resurrection_install_run($modules, $config, $writeConfig, $allowServed, $login, $password, ...)` | `lib/installer/install_run.php` (new) | `scripts/install.php`; `setup.php` in task 5b |
| `resurrection_upgrade_run($config, $apply, $report, $budget = null)` and `resurrection_upgrade(...)` | `lib/upgrade.php` | `scripts/upgrade.php`; `setup.php` in task 5b |
| `resurrection_maintenance_run($path)`, `$path` `cli` or `web` | `lib/maintenance.php` | `cron.php`, `webcron.php` |

- **Configuration reading.** `lib/database_config.php` (new) reads the database configuration file inside a function,
  so the credentials never become globals (`resurrection_database_config()`), and connects with the commands' fixed
  messages (`resurrection_database_open()`, its argument `#[\SensitiveParameter]`).
- **Locks.** The same as before: the install's and upgrade's lock and the maintenance lock
  (`resurrection-maintenance-` and 32 hex characters of the database name's SHA-256), taken without waiting.
- **Refusals.** The same texts and the same JSON, for example "Another upgrade, an install or maintenance (cron.php) is
  running on this database. ..." and `{"maintenance":"complete","game_day":"..."}`.
- **Exit codes.** The same as before.
- **The maintenance service:**
  - It refuses an unknown path (`InvalidArgumentException`, "Unknown maintenance path."), an install that never
    completed ("Installation incomplete.") and a database that is behind the code ("The game cannot run on this
    database.").
  - It then takes the lock. If another holder has it, the result is `locked`.
  - It answers `already-complete` for a game day already done. Otherwise it runs `lib/newday/newday_runonce.php`,
    marks the day and answers `complete`. A job that throws is recorded as `failed` and thrown on, with the lock
    released.
  - Every result is written to `maintenance_last_run` as JSON with three fields: `time` (UTC), `path` and `result`.
    A refusal records nothing.
  - `cron.php` keeps its `Maintenance already running.` refusal for `locked`.

One difference a command user can see: `scripts/upgrade.php` with a missing configuration file names
`database_config.php:16` as the failure location instead of `upgrade.php:65`. The text around it is unchanged.

Tests:

- `InstallRunParityTest` (new). The service and the command, each in its own process, give the same refusal on an
  installed database and the same missing-credentials refusal. A full install by each gives identical normalized
  database snapshots. The service's result keys are fixed.
- `MaintenanceRunTest` (new). It covers each result, returned and recorded with its path:
  - complete, from the web path;
  - already-complete, from the CLI path;
  - locked, while the test holds the lock;
  - failed: a job hits a renamed table, the result is "Database operation failed.", the day is not marked and the
    lock is released;
  - complete again after that.
  It also covers the refusals (behind, incomplete, an unknown path), which record nothing.
- The existing command tests pass unchanged: `FreshInstallTest`, the upgrade tests, `test_cli_maintenance_is_once_per_game_day`,
  `test_active_maintenance_failures_retries_and_concurrency` and the installer command web tests. That is the proof of
  no behavior change.

## Item 2: the upgrade's time budget

`resurrection_upgrade()` and `resurrection_upgrade_run()` take an optional budget in seconds.

- **A negative budget** is refused before anything else happens.
- **Once the budget is spent,** the upgrade stops only between two steps or two module upgrades, and only after at
  least one ran in this call. It reports "Time budget reached: applied N upgrade step(s) and M module upgrade(s) in
  this run; ... remain. Run the upgrade again to continue: recorded steps are skipped." and returns the state
  `behind`. The next call continues from there.
- **With no budget,** nothing changes. The command passes none.

Test `UpgradeBudgetTest` (new) covers:

- No budget changes nothing, and a large budget behaves the same. This is the regression guard, so it passes on
  `668cf4e` too.
- A budget of 0 stops after the first step, and the next run resumes.
- A negative budget is refused before anything is applied.
- The run service stops between module upgrades (fairy, then findgold).

## Item 3: F1, `cron.php` with no configuration or a game that cannot start

**Before**, on `668cf4e`: with no configuration file, `cron.php` printed the not-installed HTML page on stdout and
exited 0, so a scheduler recorded a success. A configuration file the game could not use (S4), a database it could
not reach, or an incomplete file did the same with that page.

**Now** `cron.php`:

- Exits 1 and prints nothing on stdout.
- Prints one fixed line on stderr:
  - With no file: "Maintenance refused: there is no configuration file, so the game is not installed. Nothing was
    run."
  - When `common.php` ends the request with a page: "Maintenance refused: the game could not start (it is not
    installed, or its configuration file or database cannot be used). Nothing was run."

A configuration file that cannot be used also sends the S4 page's reason to the PHP error log, which the command's PHP
prints on stderr before that line.

The other command-line entry points were checked:

- `scripts/install.php` and `scripts/upgrade.php` never load `common.php`, so neither prints a game page, and each
  exits 1 with its own fixed line on stderr when it cannot go on. With no configuration, `scripts/upgrade.php --dry-run`
  answers "Upgrade refused or failed (preflight, at database_config.php:16). ..." with exit 1. `scripts/install.php`
  writes a configuration when there is none, which is its job; without the database settings it answers "Installation
  refused or failed (preflight). ..." with exit 1. Neither had the fault.
- No other shipped PHP file runs from the command line.

Test `test_cron_without_a_config_exits_nonzero` (new) covers four cases: no configuration, `LOGD_DBCONNECT` naming a
relative path, a database that cannot be reached, and an incomplete configuration file. Each must exit non-zero with
an empty stdout and the fixed line on stderr.

## Item 4: the operator frame

`resurrection_operator_frame()` (`lib/pageparts.php`) marks the request's page as an operator page.

**What it keeps.** The page keeps the template, so `prepare_template()` still checks it on every change: 1.1.2's
required tags, the XOR copyright checks, and the attribution line with both links in `footer` and `popupfoot`. The
safe frame is still the fallback. Neither check was changed.

**What it drops** is everything that cannot work before the game can read a completed database:

- the MoTD and Petition for Help links;
- the mail link;
- the staff's petition counts;
- the character panel, which for a signed-out visitor is the Online Characters list.

An empty character column is not drawn (`.rz-character:has(> .rz-character-inner:empty)` in `resurrection.css`).

**Where it is used.** Only three pages use the frame, and they name only the command path (`php scripts/install.php`;
task 5b adds the web path):

- the not-installed page (`common.php`);
- the S4 configuration page (`lib/config_failure.php`);
- the "not a completed installation" page (`common.php`).

Upgrade Needed, the 1.1.2-database page and the other Game Unavailable pages keep the game frame.

Tests:

- `test_operator_frame_has_no_game_panels_and_keeps_the_credits` (new, `test_operator_pages.py`). For the not-installed
  page and the S4 page it checks: no MoTD, Petition for Help or Online Characters; the copyright line, the license
  block and the attribution line with both links present.
- `test_operator_frame_on_an_incomplete_install_http` (new). It checks the same for the "not a completed installation"
  page.

Screenshots are in sheet 1.

## Item 5: web maintenance, `webcron.php`

This follows design section 4 and Jim's decisions 2 to 4.

### Settings

No upgrade step was needed: an absent setting reads as its default.

| Setting | Default | Holds |
|---|---|---|
| `webcron_enabled` | 0 (absent) | on or off |
| `webcron_secret_hash` | empty | SHA-256 of the secret, 64 lower-case hex characters |
| `webcron_secret_made` | empty | when the address was made, UTC |
| `webcron_last_attempt` | absent | the last request that got past the secret check, as a zero-padded Unix time |
| `maintenance_last_run` | absent | JSON with `time`, `path` (`cli` or `web`) and `result`; written by both paths |

Every name fits `settings.setting`, a varchar(20). A test guards this, because a longer name is refused by a strict
database.

### The gate (`lib/webcron.php`)

Checks, in order:

1. **Request method.** Anything but GET, POST or HEAD gets the 404.
2. **The secret.** It is taken from the `X-Resurrection-Maintenance-Key` header, then the POST field `key`, then the
   query string `key`. The first value present decides. An empty value, an array, or a value over 128 characters ends
   the search, and the request gets the 404.
3. **The configuration file.** It is found as `common.php` finds it (`LOGD_DBCONNECT` first, then `dbconnect.php`)
   and must be usable and readable. The request then connects to the database.
4. **One indexed settings read.** One `SELECT` of six settings (`webcron_enabled`, `webcron_secret_hash`,
   `maintenance_day`, `game_epoch`, `gameoffsetseconds`, `daysperday`), with nothing heavy before it. Off, or a secret
   whose hash does not match by `hash_equals()`, is `refused`: the 404.
5. **The 60-second gap.** The attempt is claimed atomically: `UPDATE ... WHERE value <= now-60`, or
   `INSERT IGNORE` when the row is absent. A request inside the gap is `wait`: it gets OK and nothing runs.
6. **The game day.** It is worked out as `convertgametime()` works it out. When `maintenance_day` already equals it,
   the answer is `done`: OK, and the game is never loaded.
7. **Run.** Otherwise the game is loaded as `cron.php` loads it, with no session, no `lgi` cookie, no referer
   tracking and no forced navigation. `resurrection_maintenance_run('web')` runs under the same lock (taken without
   waiting) and the same day marker as `cron.php`.

**The answer** comes from one shutdown function, so nothing the game or a module prints can reach the client:

- every output buffer is dropped and every header removed;
- a request with a correct secret gets 200 and `OK` with a newline, `text/plain`, `Cache-Control: no-store`, whatever
  happened;
- every other request gets the S2 guard's empty 404;
- the security headers are sent either way.

**A run that fails,** including a database that is behind, is recorded as `failed` in `maintenance_last_run`. Any open
transaction is rolled back first, so the record is kept. One fixed line goes to the PHP error log
(`Resurrection: web maintenance failed at <file>:<line> <context>`), with no SQL, values or secret.

The other entry points are unchanged: `cron.php` over HTTP is still the 404, and `installer.php` is still the 403.

### The secret

- **Format.** 32 bytes from `random_bytes()`, base64url without padding: 43 characters.
- **Storage.** Only its SHA-256 hash is stored. Comparison is `hash_equals($storedHash, resurrection_webcron_hash($presented))`.
  An empty or damaged stored hash never matches, and neither does the empty secret.
- **Redaction.** Every function that takes the secret, or the arrays it travels in, marks the parameter
  `#[\SensitiveParameter]`: `resurrection_webcron_presented()`, `resurrection_webcron_hash()`,
  `resurrection_webcron_matches()`, `resurrection_webcron_gate()`, `resurrection_webcron_render()` and
  `resurrection_database_open()`.
- **Rotation.** Making a new address replaces the hash, so the old secret stops at once. Turning web maintenance off
  clears the hash, so no secret works.

### Game Settings

The screens are at `configuration.php?op=webcron`, under `SU_EDIT_CONFIG`, linked from the Game Settings navigation as
"Web maintenance".

- **Changes.** Turn on (`op=webcron_on`), make a new address (`op=webcron_new`) and turn off (`op=webcron_off`). Each
  is a POST with the CSRF token (`require_post`) and writes a game log line in the `settings` category. Making a new
  address and turning off ask for confirmation (`data-rz-confirm`).
- **The address, shown once.** It is shown only on the page that made it, which is sent `no-store`:
  - the full address, `<server URL>webcron.php?key=<secret>`;
  - the key alone, with the header's name for schedulers that can send one;
  - a Copy button beside each, using `data-rz-copy` in `assets/ui/behaviors.js`. The buttons are hidden without
    script and show "Copied" for two seconds.
- **Afterwards,** the page shows only: on or off, when the address was made, when it was last used, and the last
  maintenance result ("<time> UTC, by the web address: complete", or "by the command (cron.php)").
- **No inline script or style.** The styles are in `resurrection.css` (`.rz-webcron*`) and the behavior is in
  `behaviors.js`.

### Tests

PHPUnit:

- `WebcronSecretTest` (new) covers:
  - the secret's format and randomness;
  - every setting name fits varchar(20);
  - comparison of the hash only, with `hash_equals`;
  - the order of the header, the POST field and the query string, and the values that end the search;
  - the game-day formula;
  - on the database: rotation and turning off stop the old secret, and only the hash is stored;
  - the gate's wait, done and run, checked against `convertgametime()`.
- `SensitiveParameterTest` (new) covers:
  - every function that takes the secret, the arrays it travels in, or the database credentials carries
    `#[\SensitiveParameter]`;
  - a guard that fails when a web maintenance function takes a parameter named like the secret (or the request
    arrays) without the attribute;
  - a real exception thrown inside the gate (an invalid table prefix) shows `SensitiveParameterValue` in its trace in
    place of the secret.

HTTP (`test_web_application.py`, grouped in one class with shared fixtures):

- `test_webcron_off_wrong_or_missing_secret_is_the_same_404`: off, a wrong secret, no secret, an empty, oversized or
  array secret, another method, and a damaged, incomplete or missing configuration or an unreachable database all give
  the same empty 404, byte for byte and header for header, and record nothing.
- `test_webcron_runs_once_per_game_day_with_a_constant_answer`: the first request runs, and later ones answer the same
  OK without running. A new game day runs again.
- `test_webcron_flood_runs_once`: concurrent requests (`PHP_CLI_SERVER_WORKERS=6`) produce one run, and every answer
  is the same OK. A second flood past the gap on the same game day loads nothing.
- `test_webcron_and_cron_share_the_lock_and_the_day`: while one holds the lock the other gets `locked`, and a day done
  by one is done for the other.
- `test_webcron_sets_no_cookie_and_prints_nothing`: no `Set-Cookie` header. A module hook that prints, sets headers and
  cookies, or ends the request changes nothing in the answer. A hook that ends the request is recorded as failed, and
  the lock is released with the request.
- `test_webcron_refuses_while_an_upgrade_is_pending`: OK, nothing run, `failed` recorded, one fixed log line without
  the secret.
- `test_webcron_rotation_stops_the_old_address`: a new address stops the old one at once, and turning off stops every
  address.

Browser check (Playwright Chromium, CSP enforced; `browser-check/settings.js`): 26/26 checks passed at 1440 wide and at
390 wide (iPhone 13) with zero CSP violations. They cover:

- the off state;
- the address shown once, with the key;
- the page is no-store;
- the Copy button is shown by script, copies the address and says "Copied";
- only the hash is stored;
- the address answers `OK`;
- the status after a run;
- the address is not shown again.

Screenshots are in sheet 2.

## Item 6: the entry guard and the route inventory

- `EntryGuardTest` now expects `webcron.php` among the entry points, and the count is 63. Nothing was relaxed: the
  file is in the list, and it defines `RESURRECTION_ENTRY` first.
- `test_operator_pages.py` checks it too: 61 web entry points, and `webcron.php` answers a request without a secret
  with the empty 404.
- `ROUTE-INVENTORY-20261022.md` adds three rows: `configuration.php?op=webcron` (READ-ONLY), its three changes
  (CLOSED) and `webcron.php` (CLOSED). The totals are now 90 CLOSED, 70 READ-ONLY and 163 in all, under a new section
  "Web maintenance, 2026-11-06".
- `RESCAN-TASK3-20261023.md` reviews two new SQL interpolation entries onto its list, both class `server`:
  `lib/webcron.php` `$table` (3 sites) and `db_identifier(db_prefix('settings'))` (3 sites). Each is the fixed
  settings table name; every value is bound.

## Item 7: records

- `CHANGES-FROM-1.1.2.md`: seven rows under "Phase 6 task 5a".
- `CLAUDE.md`: a new section, "Install, upgrade and maintenance services". The services are the one code path. The
  commands and the web pages are thin front ends. A change goes in the service, never in one front end.
- `ROADMAP.md`: task 4 is ticked and task 5 is split into 5a and 5b. 5a reads "Published for review; ticked when CI
  accepts it". The F1 and S4-panel gaps now point to 5a.
- `PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`: "Decisions (approved 2026-11-06)" is appended, with the two amendments
  (T22 and T23, their tests named for 5b) and task 4's acceptance.

## Files

New:

- `webcron.php`
- `lib/webcron.php`
- `lib/database_config.php`
- `lib/installer/install_run.php`
- `tests/unit/InstallRunParityTest.php`
- `tests/unit/UpgradeBudgetTest.php`
- `tests/unit/MaintenanceRunTest.php`
- `tests/unit/WebcronSecretTest.php`
- `tests/unit/SensitiveParameterTest.php`
- `tests/support/database_save.php`: saves every table and puts it back, so tests on the installed database leave it
  exactly as found
- this document

Changed:

- Entry points: `cron.php`, `scripts/install.php`, `scripts/upgrade.php`, `common.php`, `configuration.php`
- Libraries: `lib/maintenance.php`, `lib/upgrade.php`, `lib/pageparts.php`, `lib/config_failure.php`
- `lib/dbwrapper_pdo.php`: `db_affected_rows()` is tagged `@phpstan-impure`, a doc comment only
- UI: `assets/ui/behaviors.js`, `assets/ui/resurrection.css`
- Static analysis: `phpstan-new.neon` (three new files in its paths)
- Tests: `tests/unit/EntryGuardTest.php`, `tests/tooling/test_operator_pages.py`, `tests/tooling/test_web_application.py`
- Records: `CLAUDE.md`, `ROADMAP.md`, `docs/CHANGES-FROM-1.1.2.md`, `docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`,
  `docs/ROUTE-INVENTORY-20261022.md`, `docs/RESCAN-TASK3-20261023.md`

## Fail-first, on `668cf4e`

The new tests were run against the `668cf4e` tree in a separate worktree.

**PHPUnit**: `Tests: 12, Assertions: 278, Errors: 3, Failures: 6`.

| Test | Result on `668cf4e` |
|---|---|
| `InstallRunParityTest` | Fails: `lib/installer/install_run.php` does not exist |
| `MaintenanceRunTest` (both tests) | Fails: `resurrection_maintenance_run()` is undefined |
| `UpgradeBudgetTest::testStopsBetweenStepsAndTheNextRunResumes` | Fails: all three steps applied, no stop |
| `UpgradeBudgetTest::testANegativeBudgetIsRefusedBeforeAnything` | Fails: "A negative budget was accepted." |
| `UpgradeBudgetTest::testTheRunServiceStopsBetweenModuleUpgrades` | Fails: `resurrection_upgrade_run()` is undefined |
| `SensitiveParameterTest` | Errors: its files are missing |
| `WebcronSecretTest` | Errors: its files are missing |
| `EntryGuardTest::testEveryEntryPointDefinesTheConstantFirst` | Fails: no `webcron.php` |
| `UpgradeBudgetTest::testNoBudgetChangesNothing` | Passes, as it must on both trees |

**HTTP, web application**: `Ran 12 tests`, `FAILED (failures=15)`.

- Every `test_webcron_*` test fails.
- `test_cron_without_a_config_exits_nonzero` fails in all four of its cases. For example, with no configuration it
  exits 0 with the not-installed HTML on stdout.
- `test_operator_frame_on_an_incomplete_install_http` fails.
- The two existing maintenance tests run with the new ones and pass on both trees.

**HTTP, operator pages**: `Ran 2 tests`, `FAILED (failures=3)`.

- `test_operator_frame_has_no_game_panels_and_keeps_the_credits` fails: the MoTD, Petition for Help and Online
  Characters are present.
- `test_internal_files_answer_a_direct_request_with_an_empty_404` fails: it counts 61 entry points, there were 60, and
  `webcron.php` is missing.

The full outputs are attached to the report (`fail-first/`).

## Local results (supporting evidence; CI is the acceptance)

Disposable MySQL 8.4.13 in Docker; PHP 8.5.12 and 8.4.27 (the setup-php builds).

| Check | Result |
|---|---|
| PHPUnit, PHP 8.5, fresh database | OK, 373 tests, 147955 assertions, 1:49 |
| PHPUnit, PHP 8.4, fresh database | OK, 373 tests, 147955 assertions, 1:49 |
| HTTP shard 1/4 (PHP 8.5) | OK: ran 63 (63 assigned), 0 failures, errors or skips; 1185.1 s (19.8 min) |
| HTTP shard 2/4 (PHP 8.5) | OK: ran 61 (60 assigned + 1 prerequisite), 0 failures, errors or skips; 1192.1 s (19.9 min) |
| HTTP shard 3/4 (PHP 8.5) | OK: ran 169 (168 assigned + 1 prerequisite), 0 failures, errors or skips; 1175.7 s (19.6 min) |
| HTTP shard 4/4 (PHP 8.5) | OK: ran 62 (61 assigned + 1 prerequisite), 0 failures, errors or skips; 1148.6 s (19.1 min) |
| `composer lint` | 429 files, 0 failures |
| PHPStan, `phpstan.neon` and `phpstan-new.neon` | No errors (both) |
| `composer validate`, `composer audit` | Valid; no advisories |
| Historical verifier | 417 files, 11 commits, tag and objects unchanged |
| Project validator | Passes |
| `git diff --check` | Clean |
| Browser check, Game Settings | 26/26, 0 CSP violations, 1440 and 390 wide |
| Browser check, operator pages | not-installed 200 and S4 503 at 1440 and on iPhone 13; no game panels, attribution present, CSP enforced, 0 violations |

The four shards ran at the same time on one 4-core machine, each in its own worktree with its own database, after a
full PHPUnit run as in CI. Their times are longer than a CI shard alone would take.

The new HTTP tests add about 16 seconds in all, measured from the shards' per-test durations. The slowest is
`test_webcron_rotation_stops_the_old_address` at 3.1 s, and each other new test takes under 3.1 s. Task 4's CI shards
ran 15:25 to 21:11, so no shard projects past 25 minutes, and `tests/tooling/shard-durations.json` is unchanged. The
60-minute timeout is unchanged.

The SQL sweep (`SqlSweepTest`) first failed on the two new `lib/webcron.php` entries. Both are the fixed settings
table name. They were reviewed onto the list (Item 6) and the sweep passes.

## Notes

- **The gate skips a day already done.** When today's maintenance is done, the gate answers OK without loading the
  game. Such a request is not written to `maintenance_last_run`; only requests that load the game are recorded.
- **The address shown uses `serverurl`** (`resurrection_server_url()`), or the request's own address when that is
  not set. A local install shows `http://localhost/`.
- **Sheet 2's address** is a disposable local key that was replaced right after the check.
- **Phone screenshots** are taken with the viewport grown to the page height, so the phone's fixed bottom navigation
  is drawn at the end of the page instead of over its middle. On sheet 2 they are cropped to the top of the page,
  through the web maintenance section.

## Acceptance (recorded in Phase 6 task 5b, 2026-11-06)

Task 5a is **accepted at `281fcf0`**:

- Modern core 42281955893: **SUCCESS**, 12/12 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http` shards
  13:50, 21:16, 18:34, 14:30; PHP 8.5 / MySQL 8.4 `http` shards 22:06, 21:42, 21:58, 21:47.
- Baseline integrity 42281955860: **SUCCESS**.
