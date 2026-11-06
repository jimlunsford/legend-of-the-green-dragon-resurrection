# Phase 6 task 4: web install, upgrade and maintenance, design and threat model (S3)

Date: 2026-11-06. Starting head: `b1de59d`. Status: **design for Jim's approval. No code, no tests.** Task 5 builds
what Jim approves.

Jim decided that `1.0.0-rc.1` serves hosts with no shell (`ROADMAP.md`, Phase 6, decision 3), and chose options B and
C of the readiness review (`docs/PHASE6-RC-READINESS-20261106.md`, section 1.6):

- **B.** A web install and upgrade page behind a one-time token the host creates through the panel's file manager.
- **C.** A web maintenance request behind a secret, for panels whose scheduler can only fetch a URL.

The 1.1.2 web installer (`installer.php`) stays 403. Everything below is a new page.

Section 9 holds the open questions for Jim.

## 1. Scope and non-goals

The web path does three things, and only these:

1. **Install** a new game into an empty database, writing the configuration file first when there is none.
2. **Upgrade** a completed install whose database is behind the code (pending upgrade steps or bundled module
   upgrades).
3. **Run the daily maintenance** that `cron.php` runs, when a scheduler requests a secret address.

It never:

- runs SQL typed by anyone, or offers a database console;
- shows, edits or uploads files (no file editor, no file manager, no module or theme upload);
- runs shell commands;
- imports, upgrades or migrates a 1.1.2 database (installs stay fresh only);
- clears, drops or repairs an existing database, including a failed partial install;
- edits the configuration file after it is written, or shows its contents;
- downgrades (a database newer than the code is refused, as today);
- re-enables any disabled surface (LoGDnet, SQL/PHP console, payments, source viewer, legacy recovery).

The page is a thin front end over the same services as the commands (section 3.5). There is no second install,
upgrade or maintenance implementation.

## 2. What exists today

Read from the code at `b1de59d`.

| Part | Where | What it does now |
|---|---|---|
| Install command | `scripts/install.php` | CLI only (404 over HTTP). Parses arguments, resolves the config path (`LOGD_DBCONNECT` wins), optionally writes the config from `DB_*` environment values after every check that can refuse (target, values, administrator, database reachable and empty), then calls `resurrection_fresh_install()`. Prints JSON; refusals are fixed texts on STDERR. |
| Install service | `lib/installer/fresh_install.php` | `resurrection_fresh_install()`: validates the account and module plan, takes `GET_LOCK('resurrection-install-<sha256(db)>', 0)`, refuses unless the database is `empty` (checked again under the lock), requires strict SQL mode, creates 39 tables, runs the 1.1.2 seed statements, writes default settings, installs the 24 bundled modules, then commits module activation, the administrator (17 flags, task 2) and the `resurrection_install=complete` marker in one transaction. |
| Install states | same | `resurrection_install_state()`: `empty`, `installed`, `unsupported-1.1.2`, `populated` (anything else, including a partial install). |
| Config writer | `src/Configuration/ConfigFile.php`, `LegacyConfig.php` | Absolute path, existing directory, never over an existing file or link, created exclusively with umask 0077 and `chmod 0600`; refuses a path inside the served directory unless `--allow-served-path`. No message names a value. |
| Config location | `src/Configuration/ConfigLocation.php` (S12) | `LOGD_DBCONNECT` (env or `$_SERVER`), when set and not empty, is the only config path; otherwise `dbconnect.php` in the game directory. |
| Upgrade command | `scripts/upgrade.php` | CLI only. `--dry-run` or `--backup-confirmed` (refuses without one). Takes the install lock **and** the maintenance lock together (`resurrection_upgrade_lock()`), then `resurrection_upgrade()`. |
| Upgrade service | `lib/upgrade.php` | Reads and checks every pending step before changing anything; applies steps in order, recording each in `upgrade_steps` only after it succeeded; then bundled module upgrades; stops at the first failure with `UpgradeStopped` (the database stays at the last good step; a rerun continues). States: `current`, `behind`, `ahead`, `out-of-order`. |
| Game gate | `common.php:245`, `:295` | When the database is not `current`, **every page**, including `login.php`, shows "Upgrade Needed" (behind) or "Game Unavailable" and stops. In the CLI it throws, so `cron.php` refuses. |
| Maintenance | `cron.php`, `lib/maintenance.php` | CLI only (404 over HTTP before bootstrap). Loads `common.php`, requires `installed`, takes `GET_LOCK('resurrection-maintenance-<sha256(db)>', 0)`, skips when `maintenance_day` is today's game day, else runs `lib/newday/newday_runonce.php` and writes the day marker. Output is buffered and dropped; prints one JSON line. |
| Player New Day | `newday.php` | Fresh installs set `newdaycron=1`, and the browser-triggered daily run was removed in Phase 3: **only `cron.php` runs maintenance.** A host with no scheduler gets no daily cleanups, expirations or module daily hooks. |
| Security headers | `src/Http/SecurityHeaders.php` (S1) | `X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy: same-origin`, CSP `default-src 'self'` (no inline script or style), mode from `csp_mode`. |
| Internal-file guard | every file under `lib/`, `src/`, `modules/` (S2) | Answers a direct request with an empty 404 unless an entry point defined `RESURRECTION_ENTRY`. `tests/unit/EntryGuardTest.php` counts 62 entry points. **It covers PHP files only**: a `.txt` or `.json` file in the docroot is served as is. |
| Operator pages | `lib/config_failure.php` (S4), `common.php:175` (not installed) | 503 page for an unreadable or damaged config; a not-installed page that points to the command. Both render through `page_header()`/`page_footer()` with `DB_NODB`, so the credits and attribution show, and so do MoTD, Petition for Help and Online Characters, which cannot work there (ROADMAP item, task 5). |
| CSRF and session | `src/Security/Csrf.php`, `lib/web_security.php` | Session token, `hash_equals`; session with strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS; `resurrection_rotate_session()`. Works with no database (PHP's own session storage). |
| Login failures | `login.php:44` | A `faillog` row with fixed vocabulary (`invalid_credentials`) and the IP. |
| PHP limits | `lib/local_config.php` | `common.php` sets `memory_limit` 64M and `max_execution_time` 90; a host can cap both. |

### Measurements

Local, supporting evidence only: PHP 8.5.12 CLI, MySQL 8.4.13 in Docker on the same machine, `b1de59d`.

| Operation | Wall time | Peak PHP memory |
|---|---|---|
| `scripts/install.php --write-config` (recommended modules, 23 active) | 3.35 s | 4 MB |
| `scripts/install.php --write-config --modules=none` | 3.37 s | 4 MB |
| `scripts/upgrade.php --dry-run` (no steps exist yet) | 0.07 s | 2 MB |
| `cron.php`, first run of the game day | 0.18 s | 4 MB |
| `cron.php`, same game day | 0.11 s | 4 MB |

The install runs 825 seed statements and 39 `CREATE TABLE`s and installs 24 modules. A shared host's database server
can be several times slower than a local one, mostly on DDL; an install that takes 15 to 35 seconds there is
plausible. That is close to a 30-second `max_execution_time` and to the 30 to 60 seconds many proxies allow a request.

### Findings while reading

- **F1 (runtime defect, for task 5).** `cron.php` run with no configuration file prints the HTML not-installed page to
  standard output and exits 0, so a scheduler records success. Seen locally: `LOGD_DBCONNECT` pointing at a missing
  file, `php cron.php`, exit status 0, HTML on stdout. The cause is `common.php:175`, which renders the page for every
  caller; the database-state check that makes `cron.php` throw (`common.php:297`) comes later. Task 5 moves the body
  of `cron.php` into a service anyway (section 3.5) and can make the command exit 1 with a fixed message. Recorded in
  `ROADMAP.md`.
- **F2 (context).** Since only `cron.php` runs maintenance (above), option C is the only way a host whose panel has no
  PHP-command scheduler gets daily maintenance at all.

## 3. Web install and upgrade (B)

### 3.1 Entry point

A new entry point, proposed name `setup.php`, in the game directory. It defines `RESURRECTION_ENTRY` first (the S2
rule), so `EntryGuardTest` counts 63 entry points (64 with section 4). `installer.php` keeps its 403 and its test.

`setup.php` does **not** load `common.php`: `common.php` renders the not-installed page when the config is missing and
the "Upgrade Needed" page when the database is behind, which are exactly the states `setup.php` serves. It loads a
small bootstrap of its own, in this order:

1. `SecurityHeaders::send()` with the enforce mode (there is no `csp_mode` to read before an install, and the setup
   page always enforces), `Cache-Control: no-store`, `X-Robots-Tag: noindex`.
2. The config location (`ConfigLocation::fromEnvironment()`).
3. The state decision (section 3.2), which may connect to the database.
4. Only for an answering state: the session, the template frame, the form.

### 3.2 When the page answers

| Config file (at the path `ConfigLocation` resolves) | Database | Mode |
|---|---|---|
| Missing | none yet | **Install**, with the database form |
| Present and loads | reachable, install state `empty` | **Install**, using the existing config (no database form) |
| Present and loads | install state `installed`, upgrade state `behind` | **Upgrade** |
| Present and loads | `installed` and `current`, `ahead` or `out-of-order` | 404 |
| Present and loads | `populated` (includes a partial install) or `unsupported-1.1.2` | 404 |
| Present and loads | unreachable, or the database cannot be selected | 404 |
| Unreadable, damaged, or `LOGD_DBCONNECT` not absolute | — | 404 (the game's own pages show the S4 page) |

The 404 is the S2 guard's: status 404, empty body, the security headers and `Cache-Control: no-store`, nothing else
(no session cookie). In the states the game is running in, `setup.php` looks like an internal file.

The two answering modes reveal nothing new: anyone can already see that a game is not installed (the not-installed
page) or behind ("Upgrade Needed"). What is left: the response time differs between a 404 that needed a database
query and one that did not, which tells an outsider roughly which kind of 404 it was. The state is public anyway.

Every POST decides the mode again before acting: a page loaded in install mode never installs into a database that
became populated in between.

### 3.3 The token

**File name and place.** `resurrection-setup.php`, in exactly one directory:

- When `LOGD_DBCONNECT` is set and usable: the directory of that file, and only there. A token file in the game
  directory is ignored. This mirrors S12's rule that a docroot file never wins over `LOGD_DBCONNECT`.
- Otherwise: the game directory, beside `dbconnect.php`.

The name goes in `.gitignore`, and `EntryGuardTest` skips it as it skips `dbconnect.php`.

**Why a `.php` file.** The S2 guard covers PHP files only, and every web server serves an unknown `.txt` file in the
docroot as plain text. A `.php` file whose first line ends execution with an empty 404 shows nothing when requested,
on any server that runs PHP for `.php` files, which is the condition the whole game already depends on: `dbconnect.php`
holds the database password in the same place. A server that serves PHP source leaks every PHP file, the config
included (threat T9).

**Contents.** Exactly two lines:

```text
<?php http_response_code(404); exit; ?>
rzsetup-<32 characters from a-z and 2-7>
```

- Line 1 is fixed. The page refuses a file whose first line differs, so a file that would be served is never
  accepted.
- Line 2 is `rzsetup-` and 32 base32 characters: 160 bits when generated by the page.
- Nothing else, except one trailing newline. Larger than 128 bytes is refused before reading further.
- The page reads it with a bounded `file_get_contents()`. It never includes or requires it.

**Where the value comes from.** In install or upgrade mode, the page shows a fresh value (`random_bytes(20)`, base32)
and the complete two-line file, with a copy button and the exact folder to create it in. The value is a suggestion:
the page stores nothing (no state is needed before the host proves file access). A value of the right format typed by
hand also works; the page also refuses values with fewer than 12 distinct characters, a cheap guard against
`rzsetup-aaaa…`.

**Proof: the file plus the typed value.** The host creates the file, then pastes the same value into the form. The page
checks both formats, then compares with `hash_equals()`.

- File alone is not enough: between the moment the file exists and the install finishing, any visitor could run the
  install, the race 1.1.2's installer had.
- The typed value could instead be held in the session that showed it (the page would compare the file against the
  session's value). The typed value is preferred because it survives a session expiry and a change of browser; the
  strength is the same.

**Entropy.** 160 bits. Online guessing is not feasible even with no limit at all; the limit in section 3.9 is defense
in depth and keeps logs readable.

**Expiry.** The file's modification time: refused when older than 60 minutes, or more than 5 minutes in the future. The
message says to create a new file, and that some FTP clients keep the local file's time (create it in the panel's file
manager instead).

**Single use.**

1. On success the page deletes the file.
2. It also records `sha256(token)` in a setting, `setup_tokens_used` (the last 10), in the database it just installed
   or upgraded. A token whose hash is recorded is refused, even if the file is put back.
3. If the delete fails (permissions), the final screen says so first, in the warning style, and names the file to
   delete. Item 2 already makes it useless, and in the `current` state the page answers 404 anyway.

**After the token is accepted.** The session id is rotated (`resurrection_rotate_session()`), and the session holds a
setup grant: the mode, the token's hash and an expiry 30 minutes ahead. A "Continue" request in an upgrade done in
parts (section 3.6) needs the grant and the CSRF token, and the token file must still hold the same value (its hash is
compared). The grant ends at success, at expiry, or when the mode changes.

**The token never travels in the open.**

- Forms are POST, with an action of plain `setup.php` (no query string). The value never appears in a URL, so never in
  an access log line, a Referer header or browser history.
- The token field is `autocomplete="off"`, `spellcheck="false"`, and empty whenever a form is shown again.
- No message, log line or exception names it: every function that takes it marks the parameter
  `#[\SensitiveParameter]` (as `Passwords::hash()` does), `zend.exception_ignore_args` is on, and failures are logged
  with fixed words only (`setup_invalid_token`).
- `Referrer-Policy: same-origin` already stops a Referer leaving the site; the setup screens have no external links
  besides the credits and attribution, which keep `rel="noopener"`.

### 3.4 Who may upgrade

**Recommendation: the token and an administrator's login and password, typed on the setup page.** The account must
hold `SU_MEGAUSER`, the top flag: the first administrator holds it, and it lets an account grant every flag
(`user.php`). The page checks them with
`resurrection_authenticate()` before any step runs, and a wrong pair adds a `faillog` row with fixed vocabulary
(`setup_invalid_credentials`), as `login.php` does.

Why typed credentials and not a signed-in session: while the database is behind, `common.php` shows "Upgrade Needed"
on every page, `login.php` included, so nobody can sign in; an earlier session may have expired.

Why both: the token proves control of the site's files; the credentials prove control of the game. A token that leaked
(an old copy of the site's files, threat T2) is not enough on its own, and neither is a phished administrator
password. The check runs once, at the start; later "Continue" requests use the setup grant, so they never read the
accounts table while an upgrade step may be changing it.

The install needs the token only: there is no administrator yet. The install form creates the first one.

Other options are in question 1.

### 3.5 One code path

Task 5 moves the bodies of the three commands into services. The commands keep only argument parsing, environment
reading, output and exit codes; the web pages call the same services.

| Service (proposed) | From | Used by |
|---|---|---|
| `resurrection_install_run()` | `scripts/install.php`, from the module plan to `resurrection_fresh_install()`: validate the account and the password, the config target and values, connect, refuse a non-empty database, write the config, install | `scripts/install.php`, `setup.php` |
| `resurrection_upgrade_run()` | `scripts/upgrade.php`: take the upgrade lock, `resurrection_upgrade()`, release | `scripts/upgrade.php`, `setup.php` |
| `resurrection_maintenance_run()` | `cron.php`: require `installed` and `current`, take the maintenance lock, check the day marker, run `newday_runonce.php`, write the marker, return `complete`, `already-complete` or `locked` | `cron.php`, the web maintenance entry (section 4) |

The locks, the state checks, the refusal texts and the results are the services' own, so they are the same for both.
The refusal texts are fixed and never contain a value, so the web page can show them as they are.

Two small additions to the services, both neutral for the commands:

- **A time budget for the upgrade.** `resurrection_upgrade()` takes an optional budget in seconds. Before starting the
  next step or module upgrade it checks the time spent; past the budget it stops cleanly, between steps, and reports
  what remains. The command passes none, so it behaves exactly as now.
- **The config path for the web.** `ConfigFile::target()` refuses a path inside the served directory unless asked. When
  `LOGD_DBCONNECT` is unset, the web install writes `dbconnect.php` in the game directory, which is the game's default
  location today; `setup.php` passes the allowance for that one exact path and no other. The host guide recommends
  `LOGD_DBCONNECT` where the panel can set it.

When PHP cannot create the file (the folder is not writable for PHP's user), the page refuses with fixed text and
shows **two ways on**: give PHP's user write access to the folder for the install, or create the config file by hand
from the documented template (`docs/MODERN-INSTALLATION.md`) and reload; the page then sees state 2 of section 3.2
(config present, database empty) and installs with it. The page never shows a filled-in config file.

### 3.6 Shared-host limits and timeouts

**Before the token.** The first screen (a preflight, shown in both modes) lists PHP's version, the required
extensions, `max_execution_time`, `memory_limit`, whether `set_time_limit()` is allowed, whether the config folder is
writable, and whether the request came over HTTPS. It refuses to go on when a hard requirement fails (PHP below 8.4, a
required extension missing, `memory_limit` below 32M) and says which.

**Install: one request.** Measured at 3.4 seconds and 4 MB locally (section 2). The install runs in one request, with
`ignore_user_abort(true)` (a closed browser or a proxy that gives up does not stop PHP) and `set_time_limit(300)` where
the host allows it. It is not split into parts: its last part (module activation, administrator, completion marker)
commits in one transaction, and a split install would be a second install path with its own intermediate states.

What the host sees:

| Point | What happens | What the host sees |
|---|---|---|
| Before | Preflight, then the form | The checks; the file to create; the form |
| During | One POST; the button disables itself (`behaviors.js`) | "Installing. This takes up to a minute. Do not close the page." |
| Success | Config written (0600) when it was missing, game installed, token deleted, hash recorded | What was done, the administrator's login, the next steps (maintenance, sign in) |
| Refusal before any change (bad token, database not empty, strict mode off, values invalid) | Nothing changed | The fixed refusal text, the form again without passwords or token |
| Failure midway (database error) | The install's lock is released with the connection; the database holds part of a schema and no completion marker; a config written in this request stays | The fixed failure text with the phase (as the command prints), and the recovery steps below |
| Hard timeout (PHP killed at the host's cap) | As above. `GET_LOCK` is per connection, so a killed request never leaves a lock behind | The server's own error page or a blank page |

Recovery after a failure or timeout: `setup.php` answers 404 (state `populated`), and the game's pages show "not a
completed installation". Task 5 rewrites that page's operator line for both paths: empty the database in the panel
(for example phpMyAdmin: select every table, drop), then open `setup.php` again. The config written before is kept, so
the second attempt runs in state 2 without retyping the database values. The page never empties the database itself
(question 6).

**Upgrade: in parts when needed.** The budget is 20 seconds, or half of `max_execution_time` when that is set and
lower. Each request applies steps until the budget is spent, never stopping inside a step, then shows "Applied N of M.
Continue" (a POST button with CSRF and the setup grant). Module upgrades follow the same rule. A step that a timeout
cuts off is not recorded and runs again on the next request; the step rules already require that every step "checks
for its own changes before it makes them" (`lib/upgrade.php`), and task 5's tests prove it for a step killed midway.

| Point | What the host sees |
|---|---|
| Before | The pending list (the dry-run text, word for word as the command's), the backup notice, the confirmation box |
| Each part | What was applied and recorded in this request, what remains, Continue |
| Success | "The database is current", the token deleted, a link to the game |
| Step failure | The command's `UpgradeStopped` text: the step, the source location, the last good step, "fix the cause and run again" |
| Timeout mid-step | The server's error page; opening `setup.php` again shows the upgrade mode with what remains |
| Refusal (`ahead`, `out-of-order`, lock taken) | `ahead` and `out-of-order` are 404 (section 3.2) and the game pages explain them; a taken lock gives the command's text: another upgrade, install or maintenance is running |

**Partly uploaded code.** An upgrade on a no-shell host means uploading new files over FTP or the file manager. While
that runs, old and new files are mixed. The host guide says to upload the new version into a new folder, copy the
config if it lives in the game folder, and swap the folder names in the panel; the swap is near instant.

### 3.7 Backups

Before an upgrade the page shows:

- the pending list;
- that MySQL and MariaDB commit a table change the moment it runs, so an upgrade that stops partway cannot be rolled
  back, and a backup taken just before it is the way back (the command's wording);
- how to take one without a shell: the panel's backup tool, or phpMyAdmin's Export, and to keep a copy of the
  configuration file;
- a required box: "I have taken a backup of the database just now."

**Recommendation: refuse until the box is ticked**, the web form of `--backup-confirmed`. The refusal is fixed text
and changes nothing. The page cannot check that a backup exists; the box records the host's statement, as the flag
does.

### 3.8 Requests, CSRF, CSP and credits

- **GET only shows.** Every change is a POST checked with `Csrf::requirePost()` against the session's token. The
  session works with no database (PHP's own session storage).
- **Headers.** As in 3.1, always the enforced policy. No inline script, handler or style; behaviors (copy button,
  disabling the submit button, showing the "do not close" note) come from `assets/ui/behaviors.js` and `data-rz-*`
  attributes, as task 3 did for the game.
- **Credits on every screen.** Every screen, refusals included, renders through the template frame in an operator
  form: no MoTD, Petition for Help or Online Characters (the same change task 5 makes to the S4 page), with the
  copyright line, the license block and the attribution line, checked by `prepare_template()` as every page is, and
  the safe frame when the template fails. The 404 has no body, like the S2 guard's.
- **Values typed into forms.** Shown again only for non-secret fields (database host, name, user, prefix,
  administrator login and email), escaped. The database password, the administrator password and the token are never
  shown again or logged.
- **HTTPS.** The install form carries the database password and the first administrator's password. Recommendation:
  refuse over plain HTTP, except from `localhost`; HTTPS is read from `$_SERVER['HTTPS']` only, never from a
  forwarded header a client can set. Question 5.

### 3.9 Failed attempts

What counts as a failure: a wrong typed token, a token file that is missing, malformed, expired or already used, and a
wrong administrator login or password.

| Mode | Storage |
|---|---|
| Upgrade (database present) | `faillog` rows with fixed vocabulary and the IP, as `login.php`; counted for the last 15 minutes |
| Install (no database yet) | A small counter file, `resurrection-setup-failures.php`, beside the token file, when that folder is writable. It starts with the token file's guard line, so a request for it gives an empty 404, and it holds only a count, a window start and a short prefix of the token file's hash. When it is not, nothing is stored: the 160-bit value carries the protection alone, and the page does not say which applies |

**Limit.** Above 10 failures in 15 minutes against one token file, the page refuses every attempt against that file
for 15 minutes, with fixed text. A new token file starts a new count, so the host is not locked out by someone else's
failures. A per-IP limit is not used: shared hosts often sit behind proxies, and changing IP is trivial.

What is left: a steady flood can keep delaying the host, never get in. Such a flood is visible in the access log, and
the host guide shows how to limit `setup.php` to the host's own IP address in the panel for the minutes of an install
or upgrade.

## 4. Web maintenance (C)

### 4.1 Entry point and default

A new entry point, proposed name `webcron.php`, defining `RESURRECTION_ENTRY`. `cron.php` stays CLI only and keeps its
404 over HTTP and its test.

**Recommendation: off by default.** A setting, `webcron_enabled`, starts at 0. While it is off, `webcron.php` answers
the S2 guard's empty 404. The web install's last screen asks how the host will run maintenance (a PHP command in the
panel's scheduler, a scheduler that fetches a URL, or neither yet) and turns it on only when the host picks the URL.
Off by default keeps a public trigger closed on hosts that run `cron.php`, and the install screen makes the choice
visible to hosts that need it. Question 3.

### 4.2 The secret

- 32 random bytes, base64url (43 characters).
- **Stored as its SHA-256 hash in the settings table** (`webcron_secret_hash`), never in plain text. A slow password
  hash is not needed for a 256-bit random value.
- Generated when web maintenance is turned on (the install's last screen or Game Settings), and again on rotation.
  The full address is shown **once**, on the page that generated it, with a copy button. Afterwards Game Settings
  shows only when it was made, when it was last used, and the last result.
- **Rotation**: a "Make a new maintenance address" action in Game Settings (POST, CSRF, `SU_EDIT_CONFIG`). The old
  address stops working at once.

The brief proposed storing the secret in the config file. The config file is written once, owner-only, and the game
never writes it again; rotating from Game Settings would mean a web request rewriting the file that holds the database
password. The settings table avoids that. Question 2.

### 4.3 How the secret travels

What the schedulers support (from their documentation, read 2026-11-06):

| Scheduler | What it can do | Fits |
|---|---|---|
| cPanel, Cron Jobs | Runs a command line on the host, for example `/usr/local/bin/php /home/USER/public_html/cron.php` ([Nexcess, cPanel cron](https://docs.nexcess.com/hosting/server-administration/linux/scheduling-php-scripts-with-cron-jobs.md)) | `cron.php` itself; no web path needed |
| Plesk, Scheduled Tasks | Three types: "Run a command", "Fetch a URL" ("Specify the URL only"), "Run a PHP script" ([Plesk Obsidian guide](https://docs.plesk.com/en-US/obsidian/administrator-guide/server-administration/scheduling-tasks.64993)). The guide documents no method or header choice for "Fetch a URL" | "Run a PHP script" with `cron.php`; or "Fetch a URL" with the secret in the URL |
| cron-job.org (external) | Request method, custom headers and body; waits for the response and gives up after 30 seconds ([FAQ](https://cron-job.org/en/faq/)) | Header |
| EasyCron (external) | Method (GET, POST and others), custom headers, body ([FAQ](https://www.easycron.com/faq/how-to-customize-http-method-for-my-cron-job-requests)) | Header |

Other panels (DirectAdmin and host-made panels) were not checked in this task; the host guide lists what is known when
it is written.

**Recommendation: accept the secret in any of three places**, compared with `hash_equals()` on its hash:

1. a request header, `X-Resurrection-Maintenance-Key` (documented first);
2. a POST field, `key`;
3. the query string, `webcron.php?key=…`, for schedulers that only fetch a URL.

Why the query string is acceptable here: the secret allows exactly one thing, running the day's maintenance, which
runs at most once per game day whoever asks (section 4.4). Someone holding a leaked secret can make it run at the
start of the game day instead of when the scheduler would have, and can send requests that are cheap to answer. It
reaches no account, setting or data, and no page. Where the query string leaks: the host's access log, the panel's
scheduler log, statistics tools that show query strings (AWStats, Webalizer), and any proxy log. The host guide says
to keep statistics behind the panel login and to rotate the address if it leaks. Question 4.

### 4.4 Behavior

- **What it runs.** `resurrection_maintenance_run()`, the same service as `cron.php`, under the same maintenance lock,
  with the same day marker. It refuses unless the install is complete and the database is `current`, as `cron.php`
  does; the upgrade holds the maintenance lock too, so maintenance never runs during an upgrade.
- **At most once per interval, whatever the request rate.**
  1. The day marker: the maintenance itself runs once per game day (24/`daysperday` hours; 6 hours by default).
  2. A gap: a setting `webcron_last_attempt`. A request less than 60 seconds after the previous attempt is answered
     right after the secret check, without the lock or the game.
  3. The lock, taken without waiting: a concurrent request does not queue.
- **Cheap gate first.** Before anything heavy: the security headers, the config location and file, a database
  connection, and one indexed read of the four settings it needs (`webcron_enabled`, `webcron_secret_hash`,
  `webcron_last_attempt`, `maintenance_day`). No session, no cookie, no `common.php`. Only a request that is enabled,
  correct, past the gap and on a new game day goes on to the run, which loads `common.php` as `cron.php` does, with
  the session and forced navigation turned off for this entry (task 5 detail; tested).
- **Constant answer.** With a correct secret: status 200, body `OK` and a newline, `text/plain`, `no-store`, whether it
  ran, was already done, found the lock taken, was inside the gap, or failed. Off, wrong or missing secret, or any
  config or database problem before the check: the empty 404. Jobs print nothing: the output is buffered and dropped,
  as in `cron.php`.
- **Where the result goes instead.** A setting `maintenance_last_run` (time, path `cli` or `web`, result `complete`,
  `already-complete`, `locked` or `failed`), shown in Game Settings, written by both `cron.php` and `webcron.php`. A
  failure also goes to the PHP error log with `cron.php`'s fixed source-location line.
- **Timeouts.** Measured at 0.18 seconds locally; it runs with `ignore_user_abort(true)`, so a scheduler that gives up
  (cron-job.org after 30 seconds) does not cut it off.

### 4.5 When both are set up

`cron.php` and `webcron.php` share the lock and the day marker. Whichever runs first on a game day does the work; the
other finds `already-complete` (or `locked` while the first runs). Nothing runs twice. Game Settings shows which path
ran last. The host guide recommends one scheduler, the PHP command where the panel offers it.

## 5. Threat model

### Assets

| Asset | Where it lives |
|---|---|
| A1 Database credentials | The config file; the install form while it is submitted |
| A2 Administrator account | `accounts`; the install form; the upgrade form |
| A3 Game data | The database |
| A4 The setup token | The token file; the host's clipboard; one POST |
| A5 The maintenance secret | Its hash in `settings`; the scheduler's settings; access logs if sent in the URL |
| A6 Availability of the game and of the setup path | — |

### Actors

| Actor | Can |
|---|---|
| R1 Anonymous visitor or bot | Request any URL, any number of times |
| R2 Someone with read access to logs or old backups | Read access logs, statistics pages, panel scheduler logs, copies of the site's files |
| R3 A player | Sign in to the game; nothing more |
| R4 Another account on the same shared server | Depends on the host's isolation; on a poorly isolated server, read another account's world-readable files |
| R5 The host, by mistake | Leave a token file behind, skip a backup, serve PHP source by misconfiguration, upload half a release |

### Entry points

E1 `setup.php` GET; E2 `setup.php` POST; E3 the token file `resurrection-setup.php` requested directly; E4
`webcron.php`; E5 the config file requested directly; E6 the logs and backups the host keeps.

### Misuse cases

| # | Misuse | Actor, entry | Control | Left over |
|---|---|---|---|---|
| T1 | Guess the token | R1, E2 | 160 bits generated by the page; format and diversity checks; `hash_equals()`; the failure limit (3.9); the page answers only while not installed or behind | A host who hand-types a weak value that passes the checks weakens it; the limit still caps attempts |
| T2 | Token leaks through logs or backups | R2, E6 | Never in a URL, log line, Referer or error page (3.3); 60-minute expiry; deleted on success; its hash recorded so a restored file is refused; upgrade also needs an administrator's password | A token copied from a backup within its 60 minutes, during an install (no administrator exists yet), can be used once by someone who can also reach the site in that window |
| T3 | Replay a used token or a captured POST | R1, R2, E2 | Single use (hash recorded); the CSRF token is per session; the setup grant expires in 30 minutes and is bound to the token's hash; HTTPS required (question 5) | None known beyond T2 |
| T4 | Two installers race | R1 or R5, E2 | Mode decided again on every POST; the install's `GET_LOCK` and the empty check under it; the config written with an exclusive create that never overwrites | The loser sees a refusal; nothing is changed twice |
| T5 | Install over an existing database | R1, R5, E2 | `populated`, `installed` and `unsupported-1.1.2` are 404; the install service refuses anything but `empty` under its lock; nothing is ever dropped | None |
| T6 | Timeout mid-install | R5 (host's limits) | Measured at 3.4 s; `ignore_user_abort`, `set_time_limit` where allowed; the last part commits in one transaction; the lock dies with the connection | A partial schema needs the host to empty the database in the panel (3.6); the page never does it (question 6) |
| T7 | Timeout mid-upgrade | R5 | Upgrade in parts under a time budget; each step recorded only after it succeeds; steps must be safe to rerun; backup confirmed first | A step that is not safe to rerun would be a step-authoring defect; task 5's test kills a step midway to prove the rule for the steps that exist then |
| T8 | Flood the maintenance URL | R1, E4 | Off by default; wrong secret is an empty 404 after one indexed read; no session; a 60-second gap; the lock without waiting; the run once per game day | Each request still costs a PHP start, the config load, a database connection and one query; the host's own rate limits are the backstop |
| T9 | A misconfigured server serves the token file (or PHP source) | R1, E3, E5 | The token file must start with a line that ends PHP with an empty 404, so a server that runs PHP never shows it; a file without that line is refused; expiry and single use limit the window | A server that serves PHP source shows the token and the config alike; nothing in PHP can stop that. The host guide's check (request `dbconnect.php` and `resurrection-setup.php`, expect an empty page) catches it |
| T10 | A leaked maintenance secret | R2, E4 | It allows only the once-per-day run (4.3); rotation in Game Settings; hash stored, not the secret | The holder can make maintenance run early in the game day, and send cheap requests, until the host rotates |
| T11 | Claim a freshly uploaded game before the host | R1, E2 | No install without the token file, which only someone with file access can create | None |
| T12 | Use the install form's database host field to probe other servers | R1, E2 | The database form is processed only after the token is accepted | A token holder can make PHP connect to a host of their choice, which the host could do anyway |
| T13 | Brute-force the administrator password on the upgrade form | R1, E2 | The token is checked first; failures in `faillog`; the limit (3.9) | As T1 |
| T14 | CSRF against the host's browser | R1, E2 | POST plus the session's CSRF token on every change; SameSite=Lax cookie; `form-action 'self'` | None known |
| T15 | Clickjacking or script injection on the setup page | R1, E1 | `X-Frame-Options`, `frame-ancestors 'self'`, enforced CSP without inline script; echoed values escaped | None known |
| T16 | Session fixation | R1, E1 | `session.use_strict_mode`; the id rotated when the token is accepted | None known |
| T17 | Error pages leak values | R1, E1, E2 | Fixed texts from the services; exceptions print only a phase or a source location, never a message, SQL or value (as the commands); `#[\SensitiveParameter]` | None known |
| T18 | Another account on the server reads the token or the config | R4 | Config written 0600; the host guide asks for 0600 on the token file where the file manager allows it | On a server without account isolation, as for every file of the site |
| T19 | Old code over a newer database, or a 1.1.2 database | R5 | `ahead`, `out-of-order`, `unsupported-1.1.2` are 404 here and refused by the services | None |
| T20 | Half-uploaded release | R5 | Host guide: upload to a new folder, swap; the upgrade reads and checks every pending step file before changing anything | A host who uploads in place may see errors until the upload ends |
| T21 | Maintenance runs during an upgrade | R1, E4 | The upgrade holds the maintenance lock; the run refuses unless the database is `current` | None |
| T22 | The upgrade's login check misreads an older database layout (added with decision 1's amendment) | R5, E2 | The check runs once, before any step, against the database as the upgrade finds it; task 5b proves it works against the oldest layout an upgrade starts from | A future step that changes how accounts are stored must keep the check working on the layout before it; the 5b test fails if it does not |
| T23 | A client sends `X-Forwarded-Proto: https` over plain HTTP to get past the HTTPS refusal (added with decision 5's amendment) | R1, E2 | The header is ignored unless the token file carries the exact third line `https-terminated-upstream`, which only someone with file access can write; it applies to that token's run only; the 128-byte cap holds | While such a token is live, a plain-HTTP request that forges the header is accepted; the line is the host's statement that TLS ends upstream, and it expires and is used up with the token |

## 6. Test plan for task 5

Every control above has a test that fails without it. New tests are PHPUnit where they can be, and HTTP tests only
where a request is the point; HTTP tests are grouped to keep shard time down.

### PHPUnit

| Test | Proves |
|---|---|
| `SetupTokenTest::testAcceptsTheExactTwoLineFile` | The format |
| `SetupTokenTest::testRefusesAFileWithoutTheGuardLine` | T9 |
| `SetupTokenTest::testRefusesShortLongOrForeignCharacters`, `testRefusesLowDiversityValues`, `testRefusesOversizeFiles` | T1, format |
| `SetupTokenTest::testRefusesOldAndFutureModificationTimes` | Expiry |
| `SetupTokenTest::testRefusesARecordedToken` | Single use, T3 |
| `SetupTokenTest::testGeneratedValueCarries160Bits` | Entropy |
| `SetupTokenTest::testLocationFollowsLogdDbconnectAndIgnoresTheGameDirectory` | S12 precedence |
| `SetupTokenTest::testComparisonUsesHashEquals` (static) | Constant time |
| `SetupTokenTest::testAcceptsTheOptionalThirdLineExactly`, `testRefusesAnyOtherThirdLine`, `testCapHoldsWithTheThirdLine` (decision 5's amendment) | T23 |
| `UpgradeLoginLayoutTest::testAuthenticatesAgainstTheOldestUpgradableLayoutBeforeAnyStep` (decision 1's amendment) | T22 |
| `SetupStateTest::testModeForEveryState` (data provider over the table in 3.2) | When the page answers |
| `SetupFailureLimitTest::testCounterFileIsGuardedAndKeyedByTokenFile`, `testFaillogCountInUpgradeMode`, `testNewTokenFileStartsANewCount` | 3.9 |
| `InstallRunParityTest::testCommandAndServiceProduceTheSameDatabase` | One code path |
| `UpgradeBudgetTest::testStopsBetweenStepsNeverInside`, `testResumesWhereItStopped`, `testNoBudgetIsUnchanged` | 3.6, T7 |
| `MaintenanceRunTest::testResultForEachCase`, `testRefusesWhenTheDatabaseIsBehind` | 4.4, T21 |
| `WebcronSecretTest::testOnlyTheHashIsStored`, `testRotationReplacesTheHash`, `testAcceptsHeaderPostAndQuery` | 4.2, 4.3 |
| `SensitiveParameterTest::testTokenSecretAndPasswordsAreMarked` (static) | T17 |
| `EntryGuardTest` (changed, not weakened): 64 entry points, `setup.php` and `webcron.php` listed, `resurrection-setup.php` skipped like `dbconnect.php` | S2 rule |

### HTTP (`tests/tooling/test_web_ops_http.py`)

| Test | Proves |
|---|---|
| `test_setup_is_an_empty_404_in_every_non_answering_state` (installed and current, ahead, out-of-order, populated, unsupported 1.1.2, damaged config, database unreachable; compared with an internal file's 404) | 3.2 |
| `test_setup_token_file_requested_directly_is_an_empty_404` | T9 |
| `test_setup_install_refusals` (no file, wrong typed value, expired, guard line missing, used token, non-empty database) | T1, T3, T5 |
| `test_setup_get_changes_nothing_and_post_needs_csrf` | 3.8, T14 |
| `test_setup_install_end_to_end` (config written 0600, game plays, token file gone, hash recorded, `setup.php` now 404) | B, single use |
| `test_setup_install_with_existing_config_and_empty_database` | State 2, the hand-made config path |
| `test_setup_concurrent_installs_one_wins` (two servers) | T4 |
| `test_setup_upgrade_needs_token_megauser_and_backup_box` | 3.4, 3.7, T13 |
| `test_setup_upgrade_in_parts_and_after_a_killed_step` (fixture steps in a temporary steps directory) | 3.6, T7 |
| `test_setup_never_echoes_or_logs_secrets` (responses, PHP error log and session file searched for the token, the database password and the administrator password) | 3.3, T17 |
| `test_setup_headers_policy_and_credits_on_every_screen` | 3.8, T15 |
| `test_setup_refuses_plain_http` (if question 5 is answered so) | 3.8 |
| `test_setup_forwarded_proto_trusted_only_with_the_third_line` (decision 5's amendment) | T23 |
| `test_setup_upgrade_login_check_runs_before_any_step` (decision 1's amendment) | T22 |
| `test_setup_failure_limit` | 3.9 |
| `test_webcron_off_wrong_or_missing_secret_is_the_same_404` | 4.1, 4.4 |
| `test_webcron_runs_once_per_game_day_with_a_constant_answer` (moves the clock and restores it) | 4.4 |
| `test_webcron_flood_runs_once` (parallel requests) | T8 |
| `test_webcron_and_cron_share_the_lock_and_the_day` | 4.5 |
| `test_webcron_sets_no_cookie_and_prints_nothing` | 4.4 |
| `test_webcron_refuses_while_an_upgrade_is_pending` | T21 |
| `test_webcron_rotation_stops_the_old_address` | 4.2, T10 |
| `test_cron_without_a_config_exits_nonzero` | F1 |

The existing tests that `installer.php` answers 403 and `cron.php` 404 over HTTP stay unchanged. The route inventory
gets rows for `setup.php` and `webcron.php`. Task 5's browser check covers the setup screens at 1440 and 390 wide,
with no policy violation.

## 7. Host-facing steps (for the host guide, task 6)

### Install without a shell

1. In the panel, create a MySQL or MariaDB database and a user with all privileges on it. Note the host, database
   name, user and password.
2. Turn on HTTPS for the site (most panels offer a free certificate).
3. Upload the game's files (task 6 lists which) into the site's folder.
4. Optional, where the panel can set environment variables: create a folder outside the site's public folder and set
   `LOGD_DBCONNECT` to a file path in it (the config then never sits in the public folder).
5. Open `https://your-site/setup.php`. Read the checks; fix any that fail.
6. The page shows a two-line file and the folder to put it in. In the panel's file manager, create
   `resurrection-setup.php` there and paste the two lines. Set it to 0600 if the file manager allows.
7. Back on the page, paste the value, fill in the database values (unless the config already exists), the first
   administrator's login, password and email, choose the modules, and press Install. Wait for the result.
8. The result page says what was done. If it says the token file could not be deleted, delete it now.
9. Choose how maintenance runs (below).
10. Sign in with the administrator account.

### Upgrade without a shell

1. Read the release notes.
2. Back up the database (panel backup or phpMyAdmin, Export) and the configuration file.
3. Upload the new version into a new folder. If the config is `dbconnect.php` in the game folder, copy it into the new
   folder. Swap the folders' names in the file manager.
4. Open the site: it shows "Upgrade Needed". Open `https://your-site/setup.php`.
5. Create a new `resurrection-setup.php` as in the install, paste its value, enter an administrator's login and
   password, tick the backup box, and press Upgrade. Press Continue whenever the page asks.
6. When it says the database is current, check that the token file is gone and that maintenance still runs.

### Maintenance

1. If the panel's scheduler runs PHP commands (cPanel Cron Jobs, Plesk "Run a PHP script"): run `cron.php` with the
   panel's PHP every 15 minutes. Leave web maintenance off.
2. Otherwise, if a scheduler can fetch a URL (Plesk "Fetch a URL", cron-job.org, EasyCron): in Game Settings turn web
   maintenance on and copy the address it shows once. Prefer sending the key as the `X-Resurrection-Maintenance-Key`
   header where the scheduler allows it; otherwise use the whole address. Every 15 minutes.
3. Game Settings shows when maintenance last ran and how it went. If the address leaks, make a new one there.
4. With neither, the game plays, but daily cleanups, expirations and module daily events do not run.

### Checks the guide asks the host to make

- Requesting `https://your-site/dbconnect.php` and `https://your-site/resurrection-setup.php` (while it exists) gives
  an empty page. Anything else means the server shows PHP files as text: stop and fix the server first.
- Statistics tools and logs are behind the panel login.

## 8. What task 5 changes, in short

New: `setup.php`, `webcron.php`, the three services, the setup token reader, the failure limit, the Game Settings
controls for web maintenance, the operator frame without MoTD, Petition for Help and Online Characters (also used by
the S4 page and the not-installed page), `.gitignore` entries. Changed: the three commands call the services; the
upgrade service takes an optional budget; the not-installed and "not a completed installation" pages name both paths;
`cron.php` exits 1 without a config (F1). Unchanged: `installer.php` (403), `cron.php` over HTTP (404), every lock
name, every refusal text, the config writer's rules, the S12 precedence.

## 9. Open questions for Jim

1. **Who may upgrade.**
   Options: (a) the token alone; (b) the token and an administrator's login and password typed on the setup page,
   account with `SU_MEGAUSER`; (c) the token and an administrator already signed in.
   Recommendation: **(b)**. Signing in is impossible while the database is behind, which rules out (c) whenever the
   session has lapsed; (b) needs both file access and game ownership, so one leaked secret is not enough (T2, T13).
2. **Where the maintenance secret lives.**
   Options: (a) its hash in the settings table, made when web maintenance is turned on, rotatable in Game Settings;
   (b) the config file, made at install, rotated by editing the file by hand.
   Recommendation: **(a)**. Rotation from Game Settings, which the brief asks for, would otherwise mean a web request
   rewriting the file that holds the database password.
3. **Web maintenance on or off by default.**
   Options: (a) off, with the web install's last screen offering to turn it on; (b) on from the install.
   Recommendation: **(a)**. Hosts with a PHP scheduler never get an extra public trigger, and hosts without one make
   the choice on the install screen.
4. **How the maintenance secret travels.**
   Options: (a) header, POST field or query string; (b) header or POST only; (c) query string only.
   Recommendation: **(a)**, header documented first. Plesk's "Fetch a URL" documents only a URL, so (b) shuts out
   those hosts; the secret allows only the once-per-day run, so a leak through a URL log costs little (T10).
5. **Plain HTTP on the setup page.**
   Options: (a) refuse, except from `localhost`; (b) warn and require a box; (c) allow.
   Recommendation: **(a)**. The install form carries the database password and the first administrator's password,
   and free certificates are common on panels. A host behind a proxy that hides HTTPS from PHP would need to fix
   that first (the forwarded header is not trusted).
6. **A failed partial install.**
   Options: (a) the page never clears a database; the host empties it in the panel and tries again; (b) a
   token-protected button that drops the tables with the game's prefix.
   Recommendation: **(a)**. It keeps the rule that existing data is never cleared automatically, which the command
   follows, and a web button that drops tables is a new destructive surface; the measured install time makes a
   partial install rare.

Proposed names (`setup.php`, `webcron.php`, `resurrection-setup.php`, the setting names) are not questions; say if
any should change.

## Decisions (approved 2026-11-06)

Jim approved the design on 2026-11-06 with every recommendation in section 9, plus two amendments. Where this section
and the sections above differ, this section wins.

1. **Upgrade:** the token plus an `SU_MEGAUSER` login and password typed on the setup page. **Amendment:** task 5b must
   prove that the login check works against the oldest database layout an upgrade starts from, before any step runs
   (threat T22; tests `UpgradeLoginLayoutTest`, `test_setup_upgrade_login_check_runs_before_any_step`).
2. **Maintenance secret:** its SHA-256 hash in the settings table, rotatable in Game Settings.
3. **Web maintenance:** off by default. The install's last screen offers it (task 5b).
4. **Secret travel:** the header (documented first), the POST field or the query string.
5. **Plain HTTP:** refused on the setup page except from `localhost`. **Amendment:** the token file may carry an
   optional third line, exactly `https-terminated-upstream`. When it is present, the page accepts
   `X-Forwarded-Proto: https` as HTTPS for that run only. The forwarded header is never trusted otherwise. The 128-byte
   cap still holds (threat T23; tests in `SetupTokenTest` and `test_setup_forwarded_proto_trusted_only_with_the_third_line`).
6. **Failed partial install:** the page never empties a database. The host empties it in the panel.

Task 5 is split in two: **5a** builds the shared services, the operator frame, the `cron.php` fix (F1) and web
maintenance; **5b** builds `setup.php`.

Task 4 is **accepted at `668cf4e`** (recorded in Phase 6 task 5a):

- Modern core 42247060344: **SUCCESS**, 12/12 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http`
  shards 15:25, 16:30, 19:44, 17:43; PHP 8.5 / MySQL 8.4 `http` shards 16:03, 17:42, 20:14, 21:11.
- Baseline integrity 42247060378: **SUCCESS**.
