# CLAUDE.md

Standing rules for Claude Code sessions in this repository. Read this first. A task prompt overrides this file only where it explicitly says so.

## Project

Legend of the Green Dragon - Resurrection. A modern continuation of LoGD 1.1.2 DragonPrime Edition on supported PHP and databases, in PHP on this codebase so that other people can host it.

Phase 3 is complete, merged into `main` at `0d078dff49e790339e0ab0aefdf5b35478bf6419` (PR #1, normal merge commit, 2026-10-23). Phase 4, Release foundation, is complete: its last item, the output buffering fix, was accepted at `8b33edfc8b9cf2f04661d5023011e704a21b485f` (Modern core 40958421349, all 12 jobs; Baseline integrity 40958422672).

Current phase: Phase 6, Release readiness (opened 2026-11-06; Jim chose it on 2026-11-05). Its goal: find and fix what stands between `main` and the first release candidate, `1.0.0-rc.1`. Task 1, the readiness review, is in `docs/PHASE6-RC-READINESS-20261106.md`; the later tasks come from its gap list, and Jim picks which run. The release itself (tag, GitHub release, hosting, deploy) stays excluded until Jim says go. Work only on what a task prompt names.

Phase 5, Resurrection UI (2026-10-30 to 2026-11-05; complete, accepted at `4f66f61`), replaced all 1.1.2 skins with one dark fantasy UI and original art: what it changed, task by task, in `docs/PHASE5-SUMMARY-20261105.md`; tasks and status in `ROADMAP.md`; the design contract any UI change builds to in `docs/UI-DESIGN-SPEC.md`.

Direction (Jim, 2026-10-30; `docs/PHASE5-TASK1-DIRECTION-20261030.md`):

- Gameplay, balance, text and UI change only when a task prompt says so. Record every change from 1.1.2 in docs: old behavior, new behavior, and why. Check the historical tag for the old behavior first.
- Fix security and runtime defects whenever found, recorded the same way.
- One new UI replaces all skins. When it lands, every 1.1.2 skin in `templates/` and skin selection are removed, in the task that says so.
- The game gets original SVG art (scenes, creature portraits, item and stat icons, race portraits), drawn by Claude for Jim / Phoenix 2:33 LLC.
- A clean-room rewrite under AGPL-3.0-or-later is a possible future, not started. Do not start it.

The release itself (hosting, tags, GitHub releases, an image, archive-module rights) stays excluded until Jim says go.

## Licensing

- The core stays CC BY-NC-SA 2.0. The original credits stay, visible on every page (the copyright line and license block the templates must carry). `LICENSE.txt` and `README.txt` stay byte-identical to `historical-source-1.1.2`.
- Jim's original art goes in `assets/` under CC BY-SA 4.0, with its own LICENSE file. That file is created in the art task, not before.
- Jim's standalone code, shipped as a separate work, is AGPL-3.0-or-later.
- **Open question for Jim** (S9 was closed without counsel on 2026-11-12; this question is not decided): AGPL code bundled in this repository and run inside the NC core may leave hosts unable to satisfy both licenses. Until Jim decides, label no file AGPL.
- Fonts and other third-party files keep their own licenses (for example SIL OFL for the UI fonts), recorded in `docs/THIRD-PARTY-LICENSES.md`.
- The Resurrection attribution line, "A Phoenix 2:33 LLC Project by Jim Lunsford" (links to https://phoenix233.com and https://jimlunsford.com), stays in the template's `footer` and `popupfoot`, on its own line under the 1.1.2 credit line. No task removes, hides, shrinks or weakens it or its links (the project's own rule for its tasks). `NOTICE` ("Resurrection") states the credit as section 4(d) of CC BY-NC-SA 2.0 describes it (S9 closed by Jim's decision, 2026-11-12: no legal counsel).
- `prepare_template()` checks the template on every change of the file: 1.1.2's required tags, its XOR copyright tag enforcement and `common.php`'s `$copyright` / `$license` XOR checks (kept exactly; never weaken or rename them), and the attribution line with both exact links in both `footer` and `popupfoot`. A template that is missing, unreadable or fails any check is replaced by the safe frame in `src/Ui/SafeFrame.php`, which carries the credit line, the license block and the attribution line; the notice goes to superusers and the error log (`docs/PHASE5-CLOSEOUT-20261105.md`). Keep the safe frame in code, not under `templates/`, and keep it passing those checks.

## Working branch

- Work on `main`.
- PR #1 is merged and closed.
- Keep `modernization/core-modernization` as a historical branch. Do not delete it or push to it.
- Do not create branches or PRs unless a task prompt says to.
- Never reset, rebase, squash, force-push, or rewrite published history.
- One task produces at most one coherent commit, unless the task prompt says otherwise.
- Publish with a normal non-force `git push`.

Before publishing:

1. Verify the working tree is clean except for intended changes.
2. Verify the remote `main` head matches the task's expected starting SHA. If it moved, STOP and report. Do not overwrite.

## Historical integrity

These must never change. Verify them in every task that publishes.

- Tag: `historical-source-1.1.2`
- Annotated tag object: `51cab4fbe58a234651a3177a56289b18bc152b4d`
- Source commit: `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`
- Source tree: `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`
- 417 historical files, 11 preservation commits

Use the historical tag to check original behavior. Never modify it.

## Hard exclusions

Never, in any task:

- Touch `vps1.phoenix233.com`
- Deploy, release, tag, or publicly host
- Perform penetration testing, network scanning, credential discovery, or investigation of external systems
- Import archive-only modules or the Cities module
- Adopt or rewrite in a framework (React, Vue, Laravel, Symfony) unless a task prompt says so
- Re-enable a disabled surface: LoGDnet, SQL/PHP console, historical payments, source viewer, legacy recovery
- Do not build a 1.1.2 database migration; installs are fresh only.

Do not start a subsystem the task prompt does not name.

## Versioning

- Resurrection has its own numbers, `MAJOR.MINOR.PATCH`. They do not continue 1.1.2's.
- The first release is 1.0.0. Test builds are `1.0.0-rc.N`.
- Every release must upgrade cleanly from the one before.
- No 1.1.2 server will be upgraded. All installs are fresh.
- Do not change a version string unless a task prompt says to.

## Configuration file

Which database configuration file the game, `scripts/install.php` and `scripts/upgrade.php` use (Phase 6 task 3, S12; `src/Configuration/ConfigLocation.php`):

1. `LOGD_DBCONNECT` set (not empty): that absolute path, and only it. A `dbconnect.php` in the game's directory is ignored. Missing: the game shows the not-installed page and the installer writes the file there. Unreadable, damaged, or not an absolute path: the S4 operator page (503). A command given another path is refused.
2. `LOGD_DBCONNECT` unset or empty: `dbconnect.php` in the game's directory, as before. A command's path argument works as before.

Keep this order. Never let a docroot file win over `LOGD_DBCONNECT`.

## Install, upgrade and maintenance services

Since Phase 6 task 5a each operation is one service, and the services are the one code path:

- `resurrection_install_run()` (`lib/installer/install_run.php`)
- `resurrection_upgrade_run()` and `resurrection_upgrade()` (`lib/upgrade.php`)
- `resurrection_maintenance_run()` (`lib/maintenance.php`)

The commands (`scripts/install.php`, `scripts/upgrade.php`, `cron.php`) and the web pages (`webcron.php`, `setup.php`) are thin front ends: they parse arguments or the request, read the environment, print and set exit codes or the response, and call the service. Change the behavior in the service, never in one front end. The commands' locks, lock names, JSON and exit codes stay as they are; their existing tests are the proof. The rule that their refusal texts stay word for word was for task 5a's refactor, whose tests proved it changed no behavior. A message may now change on purpose, in the same commit as the tests that pin it (Jim, 2026-11-13; Phase 6 task 17 made the four that named `docs/MODERN-INSTALLATION.md` link the host guide).

## Web setup page

`setup.php` (Phase 6 task 5b; `docs/PHASE6-TASK5B-SETUP-PAGE-20261106.md`) is the browser front end over the install and upgrade services, for hosts without a shell. Keep these rules:

- It answers only while the game is not installed (no configuration file, or one whose database is empty) or its database is behind. Every other state is the empty 404 with no cookie. The mode is decided again on every request. The one exception is the maintenance choice on the install's last screen, which needs that install's grant.
- It never loads `common.php`. It always sends the enforced CSP, `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
- Every change needs the token file `resurrection-setup.php` and the value it holds typed on the page:
  - The file sits beside the `LOGD_DBCONNECT` file when that is set, else in the game folder, never both.
  - Line 1 is the guard line, line 2 is `rzsetup-` plus 32 base32 characters, and an optional line 3 is exactly `https-terminated-upstream`.
  - It holds at most 128 bytes, is read with a bounded read and is never included.
  - It is refused when it has fewer than 12 distinct characters, is more than 60 minutes old or is more than 5 minutes ahead.
  - The value is compared with `hash_equals()`. The file is used once: it is deleted on success, and its key is recorded in `setup_tokens_used`.
  - Upgrade also needs an `SU_MEGAUSER` login and password and the backup box.
- Plain HTTP is refused unless `REMOTE_ADDR` is the machine itself. `X-Forwarded-Proto` counts only with the file's third line.
- Failures are capped at 10 in 15 minutes per token file: in `faillog` for an upgrade, in the guarded counter file for an install.
- The token, the passwords and the maintenance secret never go in a URL, log, error page, session or saved page. Mark parameters that carry them `#[\SensitiveParameter]`. A page that shows a secret once uses `resurrection_secret_output()` (`lib/pageparts.php`).
- Screens use the operator frame with the full credits. The credits are copied into `lib/setup/credits.php`, and `SetupCreditsTest` keeps them byte-identical to `common.php`'s. Every change is POST plus CSRF, with no inline script or style.

## Account mail

Account mail (S11; Phase 6 task 13a, `docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md`, `lib/account_mail.php`,
`account.php`). Keep these rules:

- Every email goes through `resurrection_send_email()` (`lib/systemmail.php`). Nothing else calls `mail()`;
  `MailMessageTest` fails otherwise. Its headers stay fixed, and no player or request value goes into one.
- A link in a mail comes only from `resurrection_server_url()` (the `serverurl` setting), never from `Host`,
  `SERVER_NAME` or any other request value. Account mail needs an `https` server URL.
- A token is 32 random bytes, base64url. Only its SHA-256 hash is stored. Its shape is checked before any lookup; it is
  bound to its purpose, account, address and `authversion`, expires, opens at most 5 times and is used once.
- Every parameter that carries a token, or a mail body that holds one, is `#[\SensitiveParameter]`.
- `account.php` takes the token out of the request before `common.php` runs and exchanges it for a session grant and
  a 303 to a clean address. A token never goes in a page, a log, a session, a saved page or a referer record. Every
  link that cannot be used gets the one fixed 400 page.
- Limits are keyed on `REMOTE_ADDR` only, never a forwarded header, and count every request whether or not an account
  matched.
- The switch turns on only when every condition holds; turning it off is always allowed. `requirevalidemail` reads as
  on only while account mail is effectively on (`resurrection_locked_settings()`, task 13c); never make it hold
  without account mail, or nobody could sign up and confirm.
- Password recovery (task 13b, `lib/account_recovery.php`): every forgot-password request that passes CSRF gets the
  same page, status, headers and cookies, whatever it matched. Its work (limits, lookup, token, mail) runs after the
  page is complete (`resurrection_account_after_response()`), with the 1.5-second floor where the server cannot finish
  a response early. A recovery mail goes only to an address verified for the account. `account.php` pages show no
  time-dependent footer (`RESURRECTION_QUIET_FOOTER`); keep them free of anything that differs per request.
- Verification and the address change (task 13c, `lib/account_verify.php`, `lib/prefs_email.php`): an address is
  verified only while `emailverified` equals `emailaddress`, so any change leaves it unverified. `saveuser()` never
  writes `emailaddress`, `emailverified` or `emailvalidation`; an address changes only through Preferences' section
  (the current password, the anchored check, `blockdupeemail`; while account mail is on only by a confirmed link to
  the new address) or the user editor. Every confirm page acts only on a POST. While account mail is on, notification
  mail goes only to verified addresses (`resurrection_account_mail_notify_allowed()`).
- Tests and CI never send mail. Every test web server runs with a mail capture (`tests/tooling/mail_capture.py`;
  `start_php_server()` refuses one without); PHPUnit runs with `sendmail_path=/bin/false` (CI's `ini-values`;
  `MailCaptureGuardTest` and `test_mail_capture.py` fail otherwise). A test that needs a message uses a capture.

## Tests

- Never remove, skip, or weaken an existing test to get a pass.
- Prefer fast tests: PHPUnit, static/inventory tests, narrow helper tests.
- Add HTTP tests only when needed. Each one adds to a shard's runtime.
- A new test should fail without the fix and pass with it. Show both.
- Do not claim a result you did not run. Local results are supporting evidence only. Supported acceptance comes from CI.
- Never weaken a correct production guard to make a test pass. When a test hits a real 409 or rejection, check the fixture first. Past failures came from fixtures in invalid states, not from bad guards.
- PHPUnit runs the files in name order on an empty database, and `FreshInstallTest` installs it. A PHPUnit test that
  needs the installed database must sort after `FreshInstallTest` (the account mail tests are `Mail*Test` for that).
- The LoGD game day rolls over every 24/`daysperday` hours: with the default 4 and the installer's midnight epoch, at 00:00, 06:00, 12:00 and 18:00 UTC. The web test harness pins the clock (`game_epoch`) when each shard starts, so a shard has a full game day of headroom. Tests that exercise New Day or rollover move the clock explicitly and restore it. Long-running tests must read the authoritative day from the application, not cache it.
- Keep failed CI runs on record. Never present a superseded run as acceptance.

Local verification, proportionate to the change:

- Focused PHPUnit and HTTP regressions for touched areas
- PHP lint
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`)
- Composer validate, install, audit
- Historical verifier and project validator
- Repository hygiene and whitespace

Local shard runs: run locally only the shards that contain new or changed tests, through `scripts/tooling-shards.py`. Run all five only when a shared helper changes (intent, CSRF, player mutation, systemmail, combat, the shard runner itself). CI runs the full suite on both legs, and CI is the acceptance.

## CI

Supported matrix:

- PHP 8.4 / MariaDB 11.4
- PHP 8.5 / MySQL 8.4

Workflows: Modern core, Baseline integrity.

Modern core runs per leg: one `checks` job, five `http` shard jobs, and one `result` job, 14 jobs in all (Phase 6 task 9; 12 before). Acceptance is all 14 jobs. `checks` runs the full PHPUnit suite; an `http` job runs only `FreshInstallTest`, the install its shard needs, then its shard. `scripts/tooling-shards.py` assigns every Python test to exactly one shard, and `tests/tooling/test_tooling_shards.py` fails if a test is dropped or doubled.

- Do not change the 60-minute timeout unless the task prompt says to. Never raise it to fit more tests. Keep every job under 21 minutes. When the slowest nears it, refresh `tests/tooling/shard-durations.json` with `scripts/tooling-shards.py --refresh-durations` from every `http` job log of at least two runs, both legs, or add a shard.
- New tests are assigned automatically. Do not hand-edit shard lists.
- Plain `unittest discover` runs tests in name order, so a web test that needs WebPlayer must not rely on running after `test_create_login_rotate_render_logout` unless the shard runner orders it. Run local serial checks through `scripts/tooling-shards.py`.
- Do not rerun old workflows. Do not dispatch duplicates.
- After publishing, inspect each workflow once. Optionally once more later.
- Pushing while the previous Modern core run is still going cancels that run (`cancel-in-progress`). When that happens, the previous candidate is accepted through the first accepted descendant, recorded as such.
- If Modern core is still running, STOP and report CI PENDING. Do not wait for the full suite.
- Acceptance requires both matrix legs and Baseline at SUCCESS, with actual totals. Never substitute predecessor totals.
- If a job fails, record the failing test, assertion, expected vs actual, and duration from the logs.
- If the log hosts are blocked, say so and ask Jim to paste the failing step's output. Do not guess.

## Roadmap

`ROADMAP.md` is the high-level plan. When a task closes or opens a checklist item, update `ROADMAP.md` in the same commit.

## Serialized state

Inventory (94 / 48 / 39 / 54 / 1 at `5bdba80`; 91 / 44 / 38 / 52 / 1 at `c00f2d1`; 94 / 45 / 38 / 55 / 1 from `54e22e6`, recounted at the merge gate; see `docs/SERIALIZED-STATE-AUDIT.md`): 83 sites, 43 files, 38 serialize writers/checks, 44 ScalarState reads, 1 centralized unserialize, after `src/Security/PaymentInfo.php` was removed with `paylog.php` (2026-10-24; 84 / 44 / 38 / 45 / 1 after `lib/mail.php` was removed).

Recompute only if serialized-state source changes. Never change counts by assumption.

## Documentation

- Evidence lives in `docs/`.
- Append new evidence. Do not rewrite historical checkpoint entries.
- Update docs only when facts change.
- `docs/SECURITY-STATUS.md` is a living record, one current row per security area, with the public hosting gates as a checklist (Phase 6 task 10, S13). A task that changes a security control updates that control's row, and the checklist if a gate moves, in the same commit. Where the record and the code disagree, the code wins: fix the record and list the fix in the task's evidence doc. Its "History (as recorded)" sections stay as written.
- `docs/MODULE-AUTHOR-GUIDE.md` tells module authors the rules (S10). A task that adds or removes a hook, or changes a helper, class, constant or setting it names, updates it in the same commit; `tests/tooling/test_module_author_guide.py` fails otherwise.
- Any statement of the credit or of what a host must do stays within the licenses (Phase 6 task 11, Jim's decision of 2026-11-12): the credit in the words of section 4(d) of CC BY-NC-SA 2.0 (4(c) is the noncommercial clause), the host's duties quoted from `LICENSE.txt` and the legal code, the template check and the safe frame described as how the software behaves, and no added term, condition or penalty. `tests/tooling/test_credit_wording.py` checks the shipped documents.

## Local toolchain notes

- The ondrej PHP PPA is blocked. Use the GitHub-hosted PHP builds that setup-php uses.
- Composer dist downloads may need GitHub auth. Fall back to source installs.
- Local MariaDB 10.11 works as a disposable test database. Supporting evidence only.
- PHPUnit expects an empty database, as in CI. Recreate the disposable DB before a full PHPUnit run.
- Whenever a task adds or changes tests, PHPUnit must pass locally on both supported PHP versions, 8.4 and 8.5, before publishing (install the 8.4 build beside 8.5 and run `php8.4 vendor/bin/phpunit`). Task 10's tests passed on 8.5 and failed on 8.4 in CI: they used `Dom\ParentNode::$children`, which exists only from PHP 8.5.
- Every new or changed tooling (shard) test also runs locally on both CI legs before a push, not only PHPUnit: PHP 8.4
  with MariaDB 11.4, and PHP 8.5 with MySQL 8.4 (Phase 6 task 13a's fix: a test that passed on 8.5 failed on 8.4 in
  CI, where PHP 8.4's `mail()` prints no warning). For each leg, put a folder first on `PATH` whose `php` is that
  version (the tests start `php` from `PATH`), start a fresh database of that leg's server, install it with
  `vendor/bin/phpunit tests/unit/FreshInstallTest.php`, then run the shard that holds the test:
  `PATH=<folder whose php is 8.4>:$PATH RESURRECTION_TEST_DB_HOST=<MariaDB 11.4 host:port> RESURRECTION_REQUIRE_PHP=1 python3 scripts/tooling-shards.py --shards 5 --shard N`,
  and the same with PHP 8.5 and MySQL 8.4. Run all five shards on both legs when the change touches a shared helper.
- PHPUnit needs `sendmail_path=/bin/false` locally too, and it can only be set at startup: put it in an ini file in a
  folder named by `PHP_INI_SCAN_DIR` (for example `PHP_INI_SCAN_DIR=":/path/to/ini"`). `-d` does not reach the child
  processes the tests start.
- Run the Python tooling tests from `tests/tooling` or through `scripts/tooling-shards.py`. As root, the test servers
  run as `nobody`, so a copy of the tree, and the folder put first on `PATH` for a PHP version, must sit where
  `nobody` can read them (not under a mode-700 folder).
- Move temporary fixture/config leftovers out of the repo before lint and commit.
- Remove each local MySQL container's data volume with the container (`docker rm -f -v`). Leftover volumes filled the session disk and stopped MySQL from starting.

## Report format

End every task with a compact report:

1. Status line in caps (for example: CANDIDATE PUBLISHED, CI PENDING)
2. Starting SHA and whether it matched
3. What was found
4. Files changed, or "none"
5. Tests added or changed
6. Local verification results
7. Published SHA, if any
8. Remote main head
9. CI run IDs and states, if any
10. Historical integrity
11. Phase / public hosting verdicts
12. Exact next task

Then STOP.
