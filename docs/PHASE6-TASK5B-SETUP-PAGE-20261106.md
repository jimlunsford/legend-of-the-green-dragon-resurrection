# Phase 6 task 5b: the web setup page, `setup.php` (S3 part 2) (2026-11-06)

Jim's brief, "Phase 6 task 5b: the web setup page, setup.php (S3 part 2)". Expected start: `main` at
`281fcf0f1d7a55579ed60a2f3c6c86db5bad3e84` (it matched). The design is `PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`,
sections 3, 5, 6 and 7, with "Decisions (approved 2026-11-06)" and its amendments T22 and T23; where they differ from
the brief, the brief and the decisions win. The services are task 5a's (`PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md`).
Task 5a's acceptance is recorded at the end of its document and ticked in `ROADMAP.md`. No version string, tag or
release changes. Every change an operator, administrator or player can notice is in `CHANGES-FROM-1.1.2.md`
("Phase 6 task 5b").

## Step 0: secrets in saved page HTML

### Where the game keeps rendered pages or raw request data

| Store | What it keeps | Written by | Read back by | A secret page's secret there at `281fcf0`? |
|---|---|---|---|---|
| `accounts_output.output` | Each signed-in player's last rendered page, whole | `page_footer()` puts the page in `$session['output']`; `saveuser()` writes it (`lib/saveuser.php`) | `badnav.php` (echoes it), the user editor's last page hit (`lib/user/user_lasthit.php`) | **Yes.** Game Settings' web maintenance page (`configuration.php?op=webcron_on`, `op=webcron_new`) left the address and its secret here |
| `$session['output']` from `redirect()` | A fixed "redirect" note with the target URL | `lib/redirect.php` | `saveuser()` as above | No: the secret page is never a redirect target |
| The PHP session store | The game's session (`$session`); the setup page's own session (`rzsetup`: the grant, the CSRF token) | PHP | PHP | No. `$session['output']` is unset after `saveuser()`; the setup grant holds the token's SHA-256, never the token |
| `accounts.restorepage` | The request URI of the last page | `saveuser()` | the game after login | No: the secret pages are POSTs to URIs without the secret |
| `petitions.body` | The petition form's fields | `petition.php` | the petition viewer | No secret page links there |
| `debuglog`, `gamelog` | Fixed texts with ids and amounts; Game Settings' web maintenance lines are fixed words | the game | the log viewers | No |
| `faillog` | Fixed words (`setup_invalid_token`, `setup_invalid_credentials` from this task), the IP, a key | login, the setup page | the fail log viewer | No: the key is the first 16 hex characters of the token file's SHA-256, never the token |
| `untranslated`, `translations` | Text through `translate()` and its namespace (the request URI) | the translator (collect texts) | the translator tool | No: the secret is printed raw, never translated, and never in a URI |
| `referers` | An external referer URI and the destination URI | `lib/referer_tracking.php` | the referers page | No |
| `settings` | `webcron_secret_hash` (SHA-256 of the secret), `setup_tokens_used` (keys of used tokens) | Game Settings, `setup.php` | the gates | No: hashes only |
| The data cache files | Query results, when `DB_USEDATACACHE` is on | `lib/datacache.php` | the game | No: the settings cache holds the hash |
| The PHP error log | Fixed lines with a phase or a file and line | the services, `setup.php`, `webcron.php` | the host | No: no secret or value is logged |

### Result

The leak existed at `281fcf0`: after Game Settings showed a new web maintenance address, `accounts_output` held the
whole page with the address and its secret, and `badnav.php` would show it again. Fixed in one place, used by every
page that shows a secret:

- `resurrection_secret_output($html, ...$secrets)` in `lib/pageparts.php` prints the secret part between two markers,
  sends `Cache-Control: no-store` and remembers the secrets for this request only.
- `page_footer()` hands `saveuser()` the kept copy from `resurrection_secret_kept_copy()`. The marked part is replaced
  by the note "A secret was shown here once. It is not kept, so it cannot be shown again.", or only the note is kept
  if a secret appears anywhere else on the page.
- Game Settings' address page (`resurrection_webcron_render()` through the new `resurrection_webcron_once_html()`),
  the setup page's token suggestion and its maintenance address use it.

Tests: `test_secret_page_is_kept_nowhere_http` (`test_web_application.py`): after `webcron_on` and `webcron_new`, every
column of every table, every session file and the server's error output are searched for each secret: zero hits; the
kept copy holds the note; `badnav.php` shows the note and not the address. At `281fcf0` it fails with
`['accounts_output.output']`. `SecretPageTest` (PHPUnit, 4 tests) covers the rule itself.

## The page

`setup.php` defines `RESURRECTION_ENTRY` first, sends the enforced policy (`SecurityHeaders::send()`),
`Cache-Control: no-store` and `X-Robots-Tag: noindex`, and never loads `common.php`. It loads common.php's libraries at
its top level (some keep globals), the services, and `lib/setup/credits.php`, then calls `resurrection_setup_request()`.

| File | What it holds |
|---|---|
| `setup.php` | The entry point (61 lines) |
| `lib/setup/token.php` | The token file: name, place, format, the bounded read, the checks, the used-token record |
| `lib/setup/guard.php` | The mode (section 3.2), HTTPS, the failure limit |
| `lib/setup/page.php` | The screens, drawn in the operator frame |
| `lib/setup/run.php` | The controller: the session and its grant, the steps, the calls to the services |
| `lib/setup/credits.php` | The credits, byte for byte from `common.php` (`SetupCreditsTest`) |

### Item 2: when the page answers (3.2)

`resurrection_setup_mode()` decides on every request, GET or POST:

| State | Mode | Answer |
|---|---|---|
| No configuration file at the path the game reads | install | The page |
| A configuration file that loads, its database reachable and empty | install-existing | The page (no database fields; the file is used as it is) |
| A completed install whose database is behind | upgrade | The page |
| Installed and current, ahead, out of order, populated or partly installed, a 1.1.2 database | none | The empty 404, no cookie |
| A damaged, incomplete or unreadable configuration file, an unreachable database, an unusable `LOGD_DBCONNECT` | none | The empty 404, no cookie |

A method other than GET, HEAD or POST is the empty 404. The one exception to the 404 is the maintenance choice on the
install's last screen (item 5): the game is installed and current by then, so that one POST needs the grant the
install left in this browser's session (bound to the token whose key the install recorded) and the CSRF token. Without
both it is the empty 404 like any other request, and it works once. Tests: `SetupStateTest::testModeForEveryState`
(14 states, each in its own process), `test_setup_is_an_empty_404_in_every_non_answering_state` (8 states and out of
order, GET, POST and a forged maintenance POST, compared with an internal file's 404; no session file is left).

### Item 3: the token file (3.3, T23)

- **Place.** Beside the `LOGD_DBCONNECT` file when that is set, else the game's folder; the other place is never read.
- **Format.** Line 1 is `<?php http_response_code(404); exit; ?>`; line 2 is `rzsetup-` and 32 characters of
  `a-z2-7`; an optional line 3 is exactly `https-terminated-upstream`. One trailing newline at most; LF or CRLF line
  ends. At most 128 bytes, read with `fread()` of 129 bytes at most, never included. A link is refused.
- **Refused.** Fewer than 12 distinct characters; a modification time more than 60 minutes old or more than 5 minutes
  ahead; a used token.
- **Proof.** The file and the typed value (trimmed, both checked for form), compared with `hash_equals()`.
- **Single use.** On success the file is deleted and its key recorded in `setup_tokens_used` (the last 10). A recorded
  token is refused. See "Notes" for the key's length.
- **Grant.** When the token is accepted the session id is rotated (`session_regenerate_id(true)`) and the session holds
  a 30-minute grant: the mode, the token's SHA-256, the expiry. Every later step needs the grant, the CSRF token and the
  same token file (its hash must still match).
- **Never shown again.** The token goes in no URL, log line, error page, session or kept page; each function that
  takes it marks the parameter `#[\SensitiveParameter]` (`SensitiveParameterTest`). The page suggests a fresh value on
  the token step (through `resurrection_secret_output()`); it stores nothing.
- `.gitignore` lists `/resurrection-setup.php` and `/resurrection-setup-failures.php`; `EntryGuardTest` skips both as it
  skips `dbconnect.php`.

Tests: `SetupTokenTest` (14 tests, the design's names plus `testRecordsTheLastTenUsedTokens` and
`testRefusesALinkAndIsNeverIncluded`), `test_setup_install_refusals`, `test_setup_token_file_requested_directly_is_an_empty_404`.

### Item 4: HTTPS

`$_SERVER['HTTPS']` set and not `off` is HTTPS. Plain HTTP is refused (403, the "This page needs HTTPS" screen) unless
`REMOTE_ADDR` is `127.0.0.1` or `::1`; the Host header never counts. `X-Forwarded-Proto: https` counts only while the
live token file carries the third line, and only for that token's run. The refusal screen says the line exists and
shows the three-line file. The session cookie is `Secure` whenever the request counts as HTTPS. Tests:
`test_setup_refuses_plain_http`, `test_setup_forwarded_proto_trusted_only_with_the_third_line`.

### Item 5: install

1. Preflight: PHP version, extensions, `memory_limit`, `max_execution_time`, whether `set_time_limit()` may be called,
   whether the configuration's folder is writable (or the file is there), HTTPS. A failing requirement stops here.
2. The token step, then the form: the database (only when there is no configuration file), the first administrator,
   the modules (recommended or none).
3. One request runs `resurrection_install_run()`, with `ignore_user_abort(true)` and `set_time_limit(300)` where
   allowed. The served path is allowed only for exactly `dbconnect.php` in the game's folder when `LOGD_DBCONNECT` is
   unset. A refusal from the service (`InstallRefused`) shows the form again (409) and changes nothing; a failure after
   the service changed the database says so and never empties anything (decision 6).
4. The last screen: what was done, and the maintenance choice. The scheduler (`php cron.php`, the default) keeps web
   maintenance off; the URL turns it on (`resurrection_webcron_enable()`) and shows the address once under step 0's rule.

Tests: `test_setup_install_end_to_end`, `test_setup_install_with_existing_config_and_empty_database` (state 2),
`test_setup_concurrent_installs_one_wins` (two servers), `test_setup_install_refusals`.

### Item 6: upgrade

The upgrade step needs the token, an `SU_MEGAUSER` login and password typed on the page, and the backup box. The page
shows the dry run's lines (the command's own text) and the backup advice first. The login check runs once, before any
step: `resurrection_setup_administrator()` is `resurrection_authenticate()` and the `SU_MEGAUSER` bit, which read only
what 1.1.2's accounts table already had. Failures go to `faillog`. Then `resurrection_upgrade_run()` runs in parts under
a budget of 20 seconds, or half of `max_execution_time` when that is lower (the host's value, read before the game sets
its own). "Applied N of M. Continue" needs the grant, the CSRF token and the same token file. A step cut off by the
host's limit is not recorded, and the next Continue runs it again. When the database is current, the key is recorded,
the file deleted and the grant ended.

T22: `UpgradeLoginLayoutTest::testAuthenticatesAgainstTheOldestUpgradableLayoutBeforeAnyStep` drops the upgrade ledger
(the layout before the upgrade system) and adds a pending step that changes the accounts table. The check accepts the
right login, refuses the others, and changes nothing (a whole-database snapshot is identical); the upgrade then applies
the step and the check still works. `test_setup_upgrade_login_check_runs_before_any_step` proves the same over HTTP.
Other tests: `test_setup_upgrade_needs_token_megauser_and_backup_box`, `test_setup_upgrade_in_parts_and_after_a_killed_step`.

### Item 7: failures (3.9)

10 failures in 15 minutes against one token file, then every attempt against it is refused (429) until the window
ends. The key is the first 16 hex characters of the SHA-256 of the file's bytes, so a new file starts a new count.
Upgrade mode writes `faillog` rows; install mode writes `resurrection-setup-failures.php` beside the token file (the
guard line, then `<key> <window start> <count>`, 0600, under `flock()`). Tests: `SetupFailureLimitTest` (3),
`test_setup_failure_limit`.

### Item 8: every screen

Each screen is drawn after the database work, with the database disconnected, in the operator frame of task 5a:
`prepare_template()`'s checks, the full credits, the attribution line, the safe frame as the fallback. No module runs.
Every change is a POST to plain `setup.php` with the CSRF token (`rzsetup` session: strict mode, cookies only,
HttpOnly, SameSite=Strict). No inline script, handler or style: the copy button (`data-rz-copy`) and the busy form
(`data-rz-busy`, new in `assets/ui/behaviors.js`: the button disabled, the note shown, a second submit stopped) are data
attributes; the styles are `.rz-setup-*` in `assets/ui/resurrection.css`. A password, the database password or the
token is never shown again. Tests: `test_setup_headers_policy_and_credits_on_every_screen` and `assertScreen()` on every
screen of every HTTP test; `test_setup_never_echoes_or_logs_secrets`; `SetupCreditsTest`.

### Item 9: wording

The not-installed page now says: "The game must be installed before it can be played: the operator runs
`php scripts/install.php` on the server's command line, or, on a host without a shell, opens `setup.php` in the
browser and follows its steps, as docs/MODERN-INSTALLATION.md describes." ("There is no web installer." is gone.) The
"not a completed installation" page ends "... and install again with `php scripts/install.php` or `setup.php`".
`installer.php` still answers 403. Tests changed with the text: `test_not_installed_page_shows_the_cli_instruction_and_footer`,
`test_operator_frame_has_no_game_panels_and_keeps_the_credits` (the not-installed page names `setup.php`; the others do
not), `test_game_unavailable_pages_for_a_database_it_refuses_http`.

### Item 10: records

`EntryGuardTest` counts 64 entry points and lists `setup.php`; `test_internal_files_answer_a_direct_request_with_an_empty_404`
counts 62 top-level pages and expects `setup.php` to answer with its install checks. `ROUTE-INVENTORY-20261022.md`
has a `setup.php` row (CLOSED 91, total 164). `RESCAN-TASK3-20261023.md` reviews the two new fixed table names.
`phpstan-new.neon` covers `lib/setup/`. `CHANGES-FROM-1.1.2.md`, `CLAUDE.md` ("Web setup page"),
`MODERN-INSTALLATION.md` ("Without a shell: setup.php") and `ROADMAP.md` are updated.

## Threat model rows and their tests

| Row | Control | Test |
|---|---|---|
| T1 Guess the token | 160 bits, format and diversity, `hash_equals()`, the limit | `SetupTokenTest` (format, diversity, `testComparisonUsesHashEquals`, `testGeneratedValueCarries160Bits`), `test_setup_failure_limit` |
| T2 Token leaks through logs or backups | Never logged or kept; 60 minutes; deleted; key recorded; upgrade needs a password | `test_setup_never_echoes_or_logs_secrets`, `SetupTokenTest::testRefusesOldAndFutureModificationTimes`, `testRefusesARecordedToken` |
| T3 Replay a used token or a captured POST | Single use, CSRF per session, the grant | `test_setup_upgrade_needs_token_megauser_and_backup_box` (used token refused), `test_setup_get_changes_nothing_and_post_needs_csrf` |
| T4 Two installers race | Mode per request, the service's lock, exclusive config create | `test_setup_concurrent_installs_one_wins` |
| T5 Install over an existing database | Non-empty states are 404; the service refuses | `test_setup_is_an_empty_404_in_every_non_answering_state`, `test_setup_install_refusals` (non-empty database) |
| T6 Timeout mid-install | One request, `ignore_user_abort`, `set_time_limit(300)`; never empties | `test_setup_install_end_to_end` (one request); decision 6 in the failure text |
| T7 Timeout mid-upgrade | Parts under a budget; a step recorded after it succeeds | `test_setup_upgrade_in_parts_and_after_a_killed_step`, `UpgradeBudgetTest` (5a) |
| T9 The server serves the token file | The guard line; a file without it is refused | `test_setup_token_file_requested_directly_is_an_empty_404`, `SetupTokenTest::testRefusesAFileWithoutTheGuardLine` |
| T11 Claim a fresh upload first | No install without the token file | `test_setup_get_changes_nothing_and_post_needs_csrf` (install without a grant refused), `test_setup_install_refusals` |
| T12 Probe other servers through the database field | The form only after the token | `test_setup_install_refusals` (no form before the token) |
| T13 Brute-force the upgrade password | Token first; `faillog`; the limit | `test_setup_upgrade_needs_token_megauser_and_backup_box`, `test_setup_failure_limit`, `SetupFailureLimitTest::testFaillogCountInUpgradeMode` |
| T14 CSRF | POST plus the session's token; SameSite=Strict | `test_setup_get_changes_nothing_and_post_needs_csrf` |
| T15 Clickjacking or injection | Frame options, the enforced policy, no inline script, escaping | `test_setup_headers_policy_and_credits_on_every_screen`, `assertScreen()`, the browser check |
| T16 Session fixation | Strict mode; the id rotated at the token | `test_setup_install_end_to_end` (cookie changes) |
| T17 Error pages leak values | Fixed texts; `#[\SensitiveParameter]` | `test_setup_never_echoes_or_logs_secrets`, `SensitiveParameterTest` |
| T18 Another account reads the files | Config 0600; counter 0600 | `test_setup_install_end_to_end` (0600), `SetupFailureLimitTest` (0600) |
| T19 Old code over a newer or 1.1.2 database | 404 and the services' refusals | `test_setup_is_an_empty_404_in_every_non_answering_state` (ahead, out of order, 1.1.2) |
| T22 The login check on an older layout | Checked once before any step, original columns only | `UpgradeLoginLayoutTest` (2), `test_setup_upgrade_login_check_runs_before_any_step` |
| T23 A forged `X-Forwarded-Proto` | Counted only with the file's third line | `SetupTokenTest` (third line), `test_setup_forwarded_proto_trusted_only_with_the_third_line` |

T8, T10, T20 and T21 are web maintenance's and the release's; their tests are task 5a's and unchanged.

## Fail-first, on `281fcf0`

The new and changed tests were copied into a worktree at `281fcf0` (with `tests/support/database_dump.php`, a test
helper) and run there:

- PHPUnit: `SetupTokenTest`, `SetupFailureLimitTest`, `UpgradeLoginLayoutTest` and `SensitiveParameterTest` error in
  `setUpBeforeClass` (`lib/setup/*.php` does not exist); `SetupStateTest` 14 of 14 fail; `SetupCreditsTest` 2 of 2 fail;
  `SecretPageTest` 4 of 4 fail (`Call to undefined function resurrection_secret_kept_copy()`); `EntryGuardTest` fails
  `testEveryEntryPointDefinesTheConstantFirst` (63 entry points, no `setup.php`).
- `test_web_ops_http.py`: 15 of 15 tests fail (41 failures with subtests): `setup.php` is a plain 404 there.
- `test_secret_page_is_kept_nowhere_http`: fails, `Lists differ: [] != ['accounts_output.output']` (the step 0 leak).
- `test_game_unavailable_pages_for_a_database_it_refuses_http` (incomplete install), `test_not_installed_page_shows_the_cli_instruction_and_footer`,
  `test_operator_frame_has_no_game_panels_and_keeps_the_credits` (not installed) and
  `test_internal_files_answer_a_direct_request_with_an_empty_404` (`62 != 61`): fail on the old wording and count.

All pass with the change (below).

## Browser check

Playwright (Chromium) against a local server, the policy enforced, at 1440 and 390 wide: the install (checks, token
step, form, installed with the maintenance choice) and the upgrade (token, administrator and backup; "Applied 2 of 4.
Continue"; done; plain HTTP refused from another address). Every response carried the enforced policy; zero policy
violations and no console errors other than the refusal's own 403. The copy button is shown by `behaviors.js` and
copies the exact file to the clipboard ("Copied"). On Install, Upgrade and Continue the button is disabled, the busy
note is shown, and a second submit sends nothing (one POST each). The PHP error log stayed empty. The two sheets
(`setup-sheet-1-install.png`, `setup-sheet-2-upgrade.png`) are with the task's report, not in the repository.

## Local results (supporting evidence; CI is the acceptance)

- PHPUnit: PHP 8.5, OK (414 tests, 107 s); PHP 8.4, OK (414 tests, 100 s). Each shard's own run: OK (414 tests,
  148785 assertions), about 123 s.
- The four HTTP shards, in parallel, each on its own MariaDB 10.11 database, through `scripts/tooling-shards.py`:
  shard 1 ran 67 (17.6 min), shard 2 ran 65 (18.2 min), shard 3 ran 173 (17.9 min), shard 4 ran 66 (17.4 min); no
  failures, errors or skips.
- Shard projection: the 15 setup tests take about 50 s together and the secret test about 2 s; the runner spread them
  over all four shards (4, 19, 19 and 8 s). On top of 281fcf0's CI times (PHP 8.4: 13:50, 21:16, 18:34, 14:30; PHP 8.5:
  22:06, 21:42, 21:58, 21:47) the slowest shard projects to about 22:30, under 25 minutes, so `shard-durations.json`
  is unchanged. The 60-minute timeout is unchanged.
- PHPStan, both configurations (`phpstan.neon`, `phpstan-new.neon`, which now lists the four `lib/setup/` files): no
  errors.
- PHP lint: 442 files, no errors. `composer validate`, `install` and `audit`: OK. Project validator: exit 0.
  Historical verifier: PASS. Both release upgrade checks: pass. `git diff --check`: clean.

## Notes

- **The maintenance choice and the state table.** Section 3.2 makes "installed and current" a 404, but decision 3
  puts the maintenance choice on the install's last screen, after which the game is installed. The page answers that
  one POST only with the install's grant in the same session, the CSRF token and the token's key among the used ones,
  once. Everything else in that state stays the empty 404.
- **The used-token record keeps 96 bits.** A settings value holds at most 255 characters, and ten SHA-256 hashes need
  649. `setup_tokens_used` keeps the first 24 hex characters of each (96 bits), space separated, the last 10 (249
  characters). A replayed token always matches its own key; a new token that matched an old one by chance (2^-96) would
  only be refused. The first draft stored full hashes as JSON and failed on the fourth token (`SetupTokenTest::testRecordsTheLastTenUsedTokens`).
- **CRLF.** The token file may use CRLF line ends: a file saved by a Windows editor in a file manager works.
- **`X-Robots-Tag` on the 404.** `setup.php` sends its fixed headers before it decides, so its empty 404 carries them
  too; an internal file's 404 (the S2 guard) does not. Both are empty with no cookie.
- **Credits copied.** `lib/setup/credits.php` repeats `common.php`'s credit block byte for byte; `SetupCreditsTest`
  fails if they differ. `prepare_template()`, the XOR checks, the attribution line and the safe frame are unchanged.
- **Upgrade Needed text.** The "Upgrade Needed" page still says the web installer is no longer available (true of
  `installer.php`) and names the commands; the brief named only the not-installed and incomplete-install pages, so it is
  unchanged. A later task may add `setup.php` there.
- **Test fixtures.** The HTTP tests put their fixture upgrade steps in `upgrades/` (named `*-setup-fixture-*`, removed
  after each test), because the served page reads the code's own steps. The class saves the whole test database first
  and restores it at the end (`tests/support/database_dump.php`). Out of order is covered over HTTP only: PHPUnit has no
  recorded steps to put out of order without writing to `upgrades/`, which the PHPUnit tests never do.
- **The scan.** `SqlSweepTest` flagged a user-facing sentence that began "Delete" followed by a path; it was reworded.

## CI failure and fix (2026-11-07)

**CI at `af0337c`.**
- Modern core 42296406057: FAILURE, 11 of 12 jobs SUCCESS. The failed job was `engineering (8.4, mariadb:11.4) / http (2)`, at 1:42.
  - The failing test was `test_web_ops_http.SetupPageTests.test_setup_concurrent_installs_one_wins`.
  - It raised `urllib.error.URLError: [Errno 111] Connection refused` from `self.start(c)` at line 519, after 10.3 s.
  - The shard runner stops at the first failure (`failfast`), so shard 2 ran 12 of its 64 assigned tests.
  - The other shards took 17:12, 16:10 and 19:42 on PHP 8.4, and 19:16, 17:27, 22:12 and 22:05 on PHP 8.5.
- Baseline integrity 42296405848: SUCCESS.
- The failed run stays on record and was not rerun. 5b is accepted through this fix's run.

**Cause (confirmed).** The race test started its second PHP server on `served.port + 1`, a port nobody checked.
- On the runner that port was in use, so the second server exited at once.
  - A runner's loopback ports are shared with outgoing connections and with the database service's published port, which comes from the same ephemeral range.
  - `php -S` exits with "Failed to listen on ... (reason: Address already in use)" when a socket is bound there, even one that is not listening.
- The wait loop that followed never called `poll()`. After 200 × 0.05 s it fell through silently, which matches the 10.3 s.
  - The first request then met nothing listening: connection refused at line 519.
- The second server also lacked the `auto_prepend_file` (the fixture's REMOTE_ADDR/HTTPS headers) and the ini that `tree()` gives the first one.
- Reproduced locally with the old test file at `af0337c` (below).

**The change.**
- New `tests/tooling/loopback_server.py` holds two functions:
  - `free_port()`: binds to port 0, so the OS picks the port.
  - `wait_until_ready()`: checks `poll()` on every pass. It returns only when the process is running and answers. If the process exits, or nothing answers in time, it raises `ServerStartError` with the port and the server's own output.
- `start_php_server()` launches `php -S` on a free port with its output in a file, and also waits for PHP's own "Development Server (http://127.0.0.1:PORT) started" line.
  - Another program that listens on the port is therefore never taken for the server.
  - If PHP reports the port taken in the moment between choosing and binding it, it tries a new port, up to 3 times. Otherwise it fails with its message.
- In `test_web_ops_http.py`, the server launch moved out of `tree()` into `server(served, env, workers, ini)`. It gives every server the same error log, sessions, prepend file, ini and environment. `tree()` and the race test's second server both use it.
  - The race test's assertions are unchanged.
  - The server's output file is a dot file in the tree, so the test that lists the tree's non-dot files is unaffected.
- New `tests/tooling/test_loopback_server.py` (4 fast tests): a server that starts answers on an OS port; one that exits fails with its output; another program listening on the port is not taken for the server; one that never answers fails after the time allowed.

**Other derived ports and readiness loops in `tests/`.**
- Derived or fixed ports: only the race test's `served.port + 1`.
  - Every other server takes its port from the OS: `test_disabled_endpoints.py`, `test_web_application.py` (the class server and 8 second servers), `test_operator_pages.py`, and `tree()`.
  - `127.0.0.1:1` in fixtures is a deliberately closed database address, not a server.
- Readiness loops that could fall through silently, all now `wait_until_ready(server, port, ...)`:

| File | Line (at `af0337c`) | Before |
|---|---|---|
| `test_web_ops_http.py` | 217 (`tree()`) | Checked `poll()` but fell through after 200 tries |
| `test_web_ops_http.py` | 512 (race test) | No `poll()`; fell through |
| `test_web_application.py` | 5297, 5509, 7318, 8324, 9328, 9771 | No `poll()`; fell through after 100 × 0.02 s |
| `test_web_application.py` | 9924 | Checked `poll()` but fell through |
| `test_operator_pages.py` | 86 | Checked `poll()` but fell through |

- Already safe, left as they were:
  - `test_disabled_endpoints.py:34` and `test_web_application.py:89` return on success, raise if the server exits and raise after the loop.
  - `test_web_application.py:10594` has `else: self.fail(...)` after the loop.

**Upgrade Needed.** One operator line added after "An administrator applies the upgrade from the server's command line; the web installer is no longer available.":
- "The operator applies the upgrade with `php scripts/upgrade.php --backup-confirmed` on the server's command line, or, on a host without a shell, opens `setup.php` in the browser and follows its steps, as docs/MODERN-INSTALLATION.md describes."
- The page's other text is unchanged. `test_upgrade_needed_page_and_copyright_footer_http` asserts the line for the anonymous visitor and the signed-in player. The change is recorded in `CHANGES-FROM-1.1.2.md`.

**Fail-first (PHP 8.4.27, MariaDB 11.4).**
- Old harness: the test file from `af0337c`, run with `served.port + 1` held by a runner script.
  - With the port bound (not listening), the result is exactly the CI error: `ERROR: test_setup_concurrent_installs_one_wins`, `File ".../test_web_ops_http.py", line 519, in test_setup_concurrent_installs_one_wins`, `urllib.error.URLError: <urlopen error [Errno 111] Connection refused>`, after 11.1 s.
  - With the port held by a listening socket, the old loop takes that socket for the server: the request hangs and fails with `TimeoutError: timed out` after 121 s.
- New harness, the same runner, both ways: OK (2.5 s and 2.7 s). The second server's port comes from the OS.
- New guard, a throwaway call with a `-t` path that does not exist:
  - The old pattern ends after 10.1 s with `URLError: <urlopen error [Errno 111] Connection refused>`.
  - `start_php_server()` fails after 0.1 s with `ServerStartError: Loopback PHP test server on 127.0.0.1:40017 exited with code 1 before it answered.` followed by `Server output: Directory /tmp/exit-at-once-.../no-such-tree does not exist.`
- Upgrade Needed with `af0337c`'s `common.php`: `FAIL` (anonymous and player), `AssertionError: "The operator applies the upgrade with php scripts/upgrade.php --backup-confirmed ..." not found`. With the change: OK.

**20 runs.** `test_setup_concurrent_installs_one_wins` ran 20 times in a row, each in its own process, on PHP 8.4.27 with MariaDB 11.4: 20 of 20 OK, 2.4 to 3.2 s each.

**Local results (supporting evidence; CI is the acceptance).**
- PHPUnit on MariaDB 11.4: PHP 8.4.27 OK (414 tests, 71 s); PHP 8.5.12 OK (414 tests, 87 s).
- The four shards ran in parallel on PHP 8.4.27, each with its own MariaDB 11.4 container. Each shard's PHPUnit was OK (414 tests, 91 to 96 s).

| Shard | Tests run | Time |
|---|---|---|
| 1 | 68 | 14.1 min |
| 2 | 66 | 14.4 min |
| 3 | 174 | 14.3 min |
| 4 | 67 | 13.8 min |

  No failures, errors or skips.
- The fix adds under 1 s of tests (the four loopback tests) and makes the race test's second server start the same way as the first. The slowest CI shard at `af0337c` was 22:12, so `shard-durations.json` and the 60-minute timeout are unchanged.
- PHPStan, both configurations: no errors. PHP lint: 442 files, 0 failures. `composer validate`, `install` and `audit`: OK. Project validator: exit 0. Historical verifier: PASS (417 files, 11 commits). `git diff --check`: clean.
- Correction to "Local results" above: that shard run used MySQL 8.4 containers (`mysql:8.4`), not MariaDB 10.11. This run used MariaDB 11.4, the failing leg's database.

**Acceptance (recorded in Phase 6 task 6, 2026-11-07).** Task 5b is **accepted at `73cc1da`**, through the fix:
- Modern core 42321788194: **SUCCESS**, 12/12 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http` shards
  19:35, 20:48, 14:13, 18:34; PHP 8.5 / MySQL 8.4 `http` shards 19:14, 17:01, 19:52, 17:50.
- Baseline integrity 42321788112: **SUCCESS**.
- The failed run, Modern core 42296406057 at `af0337c`, stays on record.
