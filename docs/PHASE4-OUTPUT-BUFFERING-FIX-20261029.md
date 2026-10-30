# Phase 4 fix: the game no longer depends on PHP output buffering (2026-10-29)

Starting `main`: `0c390204d3b406167628b7d310f510e473d6bb6a` (task 3). Task 3's acceptance is recorded in
`PHASE4-TASK3-LOCAL-SETUP-20261029.md`, "Acceptance" (Modern core 40950097216, all 12 jobs; Baseline integrity
40950097228).

## The defect

Task 3 found it (`PHASE4-TASK3-LOCAL-SETUP-20261029.md`, "Finding"). With `output_buffering=0`, which is PHP's default
without a `php.ini` and the setting in PHP's official Docker image, every page of an installed game failed with
"Runtime error in common.php:206".

- `common.php` required `lib/all_tables.php` at line 102. That file is 1.1.2's, unchanged. It ends with `?>` and two
  newlines, and PHP prints the second one.
- `common.php`'s own output buffer starts at line 128 (`ob_start()`, before `dbconnect.php`). With buffering off the
  newline was sent at once, and the `header("Content-Type: ...")` call at line 206 failed with "headers already sent".
  The application's error handler turns that warning into the runtime error.
- CI never hit it: setup-php runs PHP with `php.ini-production`, which sets `output_buffering=4096`, so the newline
  waited in PHP's own buffer and the header still went out.

## How 1.1.2 ordered these

At `historical-source-1.1.2`, `common.php` loaded the same library list (`lib/local_config.php` through
`lib/php_generic_environment.php`, lines 45 to 89), started its buffer at line 99 (`ob_start()`, then `dbconnect.php`),
and sent the header at line 175. It never loaded `lib/all_tables.php`: in 1.1.2 only the installer did
(`lib/installer/installer_functions.php:36`). So none of 1.1.2's pages printed anything before the buffer.

Resurrection added the require in `899cf0b` (2026-09-13, Phase 2) so `common.php` can fill a new session's account
defaults from `get_all_tables()['accounts']` (lines 103 to 106). That put 1.1.2's trailing newline in front of every
page.

## The fix, and why this one

`common.php` now discards whatever `lib/all_tables.php` prints, the way `scripts/upgrade.php` already does:

```php
ob_start();
require_once 'lib/all_tables.php';
ob_end_clean();
```

`lib/all_tables.php` is not edited: it stays byte-identical to the historical tag.

Moving the require after line 128 would be the larger and less safe change. The session defaults at lines 103 to 106
need `get_all_tables()` before the buffer starts. And the buffer at line 128 is not thrown away: when the database
cannot be reached, `common.php` echoes it (`echo $out;`) in front of the "Database Connection Error" page, so the
newline would be printed there instead. Discarding it right where it is printed changes nothing else.

## Every other include before the buffer

**All 49 files `common.php` loads before line 128 on a page request** were checked at run time. The part of
`common.php` before its buffer ran on its own with `-d output_buffering=0`, with the fix, from a tree of links to the
repository: exit 0, and 0 bytes on standard output. `get_included_files()` listed:

`src/Security/ScalarState.php`, `src/Version.php`, `lib/local_config.php`, `lib/dbwrapper.php`,
`lib/errorhandling.php`, `src/Compatibility/array_cursor.php`, `lib/datacache.php`, `lib/dbwrapper_pdo.php`,
`lib/holiday_texts.php`, `lib/modules.php`, `lib/arraytourl.php`, `lib/sanitize.php`, `lib/constants.php`,
`lib/http.php`, `lib/e_rand.php`, `lib/buffs.php`, `src/Game/Expression.php`, `src/Game/SpecialtyBuffState.php`,
`src/Game/BuffState.php`, `src/Game/TransmutationState.php`, `src/Game/CompanionState.php`,
`src/Game/SkeletonCompanionState.php`, `lib/pageparts.php`, `lib/output.php`, `src/Compatibility/LegacySprintf.php`,
`lib/tempstat.php`, `lib/su_access.php`, `lib/datetime.php`, `lib/translator.php`, `src/Security/PreferenceState.php`,
`lib/template.php`, `lib/settings.php`, `lib/redirect.php`, `lib/censor.php`, `lib/saveuser.php`,
`src/Security/NavigationState.php`, `src/Game/DragonPointState.php`, `lib/arrayutil.php`, `lib/addnews.php`,
`src/Security/NewsArguments.php`, `lib/sql.php`, `lib/mounts.php`, `lib/debuglog.php`, `lib/forcednavigation.php`,
`lib/php_generic_environment.php`, `lib/web_security.php`, `src/Http/Input.php`, `src/Security/Csrf.php`,
`lib/all_tables.php`.

The code that runs between them (`php_generic_environment()`, `resurrection_start_session()`) printed nothing either.

**Every tracked PHP file** was then read with PHP's tokenizer for text outside PHP tags (`T_INLINE_HTML`), anywhere in
the file. Outside `tests/` there is exactly one: `lib/all_tables.php:1492`, the newline. No other file needed the fix.
`OutputBufferingTest::testNoOtherPhpFilePrintsOutsideItsTags` keeps it that way.

## `scripts/install.php`, `cron.php` and the other scripts

| Entry point | Loads `lib/all_tables.php` | Before this fix | Now |
|---|---|---|---|
| `common.php` (every page) | line 102 | The newline was sent before the buffer; with buffering off, every page failed | Discarded |
| `scripts/install.php` | through `lib/installer/fresh_install.php` | The same newline came first in the command's output, ahead of its JSON line (no header, so no failure; `FreshInstallTest` trimmed it) | Discarded the same way; `FreshInstallTest` now expects an empty output exactly |
| `scripts/upgrade.php` | through `lib/installer/fresh_install.php` | Already discarded (task 2) | Unchanged |
| `cron.php` | through `common.php`, then `lib/installer/fresh_install.php` | `ob_start()` is its first statement, so nothing was sent | Unchanged |
| `installer.php` | no | Answers 403 before any bootstrap | Unchanged |

The other files in `scripts/` are Python.

## Not done to hide it

`php.ini`, the local image's PHP settings and CI's PHP settings are unchanged. The local image still installs
`php.ini-production`, so local play runs on CI's settings; only the comment that said the game needs it was corrected
(`local/Dockerfile`, and the matching comment in `tests/tooling/test_local_play.py`). `docs/MODERN-INSTALLATION.md`
no longer asks for a `php.ini`: it says the game also runs with `output_buffering` off.

## Tests

`tests/unit/OutputBufferingTest.php` (PHPUnit, no HTTP server, no new shard test):

- `testAnInstalledGamePageRendersWithOutputBufferingOff`: on the database `FreshInstallTest` leaves installed, the PHP
  CLI renders `home.php` as a GET with `-d output_buffering=0`, in a tree of links to the repository with its own
  `dbconnect.php`. The CLI refuses a header after output just as a web server does, so the defect fails the same way.
  It asserts exit 0, nothing on standard error, no "Runtime error", and that the output starts with the jade template's
  own page head (`"\r\n<!DOCTYPE"`), so nothing was printed before the page.
- `testNoOtherPhpFilePrintsOutsideItsTags`: the tokenizer check above.

`tests/unit/FreshInstallTest.php`: the refused install's standard output must now be exactly empty
(`assertSame('', $out)` instead of `assertSame('', trim($out))`). The test is stricter, not weaker.

**Failing first, passing after** (PHP 8.5.12, MySQL 8.4):

| Tree | Result |
|---|---|
| `common.php` as at `0c39020` | FAIL `testAnInstalledGamePageRendersWithOutputBufferingOff`: "Failed asserting that 255 is identical to 0", standard error `PHP Fatal error: Uncaught ErrorException: Runtime error in common.php:206` |
| The fix | OK (2 tests, 11 assertions) |
| `scripts/install.php` as at `0c39020` | FAIL `FreshInstallTest::testRealInstallationAndAccountBoundary` at `FreshInstallTest.php:80` ("Failed asserting that two strings are identical": the newline) |
| The fix | OK |

**Supporting check over HTTP.** PHP's built-in server with `-d output_buffering=0` on the same tree, with the
recommended modules active, and the task 3 player script: the home page, account creation, login, first New Day
(race and specialty), the village, the forest and a forest search all rendered, with no runtime error in the server
log. With `common.php` reverted, `home.php` sent the single newline and the server logged the uncaught
"Runtime error in common.php:206".

## Local verification (supporting evidence only)

PHP 8.5.12 (php-builder, as setup-php) and MySQL 8.4 in Docker:

- `vendor/bin/phpunit` on a fresh database: OK, 283 tests, 141343 assertions.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 392 files, 0 failures. `composer validate --strict`: valid. `composer audit`: no advisories.
- All four shards, because `common.php` runs on every page (`scripts/tooling-shards.py --shards 4`, run in parallel in
  four copies of the tree, each after `composer test` on its own fresh MySQL 8.4 database, with
  `RESURRECTION_REQUIRE_PHP=1`): shard 1 ran 60, OK, 17.9 min; shard 2 ran 67 (66 and 1 prerequisite), OK, 18.4 min;
  shard 3 ran 59 (58 and 1), OK, 19.3 min; shard 4 ran 60 (59 and 1), OK, 18.4 min. No failures, errors or skips.
- `scripts/verify-historical-baseline.py`: 417 files, 11 historical commits, tag object, commit and tree as recorded;
  `lib/all_tables.php` and `README.txt` are byte-identical to the tag. `scripts/validate-project.py`: PASS.
  `git diff --check`: clean.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` marks the fix
done only after CI accepts it.

## Acceptance (appended 2026-10-30, Phase 5 task 1)

The fix is **accepted at `8b33edf`**. Read through the GitHub Actions API on 2026-10-30:

- Modern core 40958421349: **SUCCESS, all 12 jobs** (both legs' `checks`, eight `http` shards and both `result` jobs,
  21:30 to 21:51 UTC on 2026-10-29). Shard jobs: PHP 8.4 / MariaDB 11.4 15:43, 18:04, 16:36, 14:51; PHP 8.5 / MySQL 8.4
  12:50, 20:26, 16:50, 18:00 (shards 1 to 4). Their shard steps: 15:01, 17:18, 15:52, 14:10; 11:54, 19:34, 15:59, 17:03,
  all under 30 minutes, so `tests/tooling/shard-durations.json` needs no refresh.
- Baseline integrity 40958422672: **SUCCESS**.

`ROADMAP.md` marks the fix done, and with it Phase 4's last planned item.

Follow-up in the same commit: `testAnInstalledGamePageRendersWithOutputBufferingOff` relied on the database
`FreshInstallTest` leaves installed, so it failed when run alone or before that test (on an empty database: exit 255,
"Database operation failed."). It now installs an empty database itself and empties it again afterwards; an installed
database is used as it is (`PHASE5-TASK1-DIRECTION-20261030.md`).
