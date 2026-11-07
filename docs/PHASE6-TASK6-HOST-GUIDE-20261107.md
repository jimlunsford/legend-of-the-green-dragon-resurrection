# Phase 6 task 6: the host guide (B2), 2026-11-07

Starting head: `main` at `73cc1da0e0b0a98b04b77e8287819d76e85d42cc`, as the brief expected. The task writes the host
guide (`docs/HOST-GUIDE.md`) and sample server rules (`docs/hosting/`), makes the guide the one place for hosting
steps, and checks the guide against the code with a new test and, locally, behind real nginx and Apache servers. Along
the way it found and fixed one runtime defect: the server URL a fresh install over HTTPS stores.

Before the work, it recorded task 5b's acceptance (`PHASE6-TASK5B-SETUP-PAGE-20261106.md`, "CI failure and fix") and
ticked 5b and S3 in `ROADMAP.md`.

## Sources read

Written from the code at `73cc1da`. Where a document and the code disagreed, the code won and the document was fixed
(next section).

- `docs/PHASE6-RC-READINESS-20261106.md`: the B2 row, sections 1.6 (hosting) and 1.7 (operations).
- `docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`: section 7 and "Decisions (approved 2026-11-06)".
- `docs/PHASE6-TASK3-HARDENING-20261106.md`, `docs/PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md`,
  `docs/PHASE6-TASK5B-SETUP-PAGE-20261106.md`.
- `docs/MODERN-INSTALLATION.md`, `docs/SUPPORTED-PLATFORMS.md`, `docs/MODULE-POLICY.md`, `SECURITY.md`.
- The code each fact in the guide rests on: `composer.json` (PHP and extensions), `src/Configuration/ConfigLocation.php`
  and `ConfigFile.php` (`LOGD_DBCONNECT`, the S4 reasons), `lib/dbwrapper_pdo.php` (SQL modes, utf8mb4),
  `scripts/install.php` and `scripts/upgrade.php` (options, environment), `lib/installer/install_run.php` and
  `installer_default_settings.php`, `setup.php` and `lib/setup/` (token file, HTTPS rule, failure cap, preflight,
  upgrade parts), `cron.php`, `webcron.php`, `lib/webcron.php` and `lib/maintenance.php` (maintenance paths, the
  header, the status line), `src/Http/SecurityHeaders.php` and `lib/configuration_descriptors.php` (`csp_mode` and the
  setting labels), the S2 entry guard (`RESURRECTION_ENTRY` in every served page, the empty 404 in internal files),
  `lib/web_security.php` and `common.php` (the Secure cookie), `lib/systemmail.php` and `lib/char_expiration.php`
  (mail), `common.php` and `src/Ui/SafeFrame.php` (the credits rule), the fixed `error_log` lines, `.gitignore`.

## Documents fixed where they disagreed with the code

- `MODERN-INSTALLATION.md`:
  - "Use a standard PHP 8.4 or 8.5 build with PDO MySQL, mbstring, JSON, filter, and native sessions" left out
    `ctype`, which `composer.json` requires since task 3 (S6). Now the full `ext-*` list.
  - "Install the locked Composer dependencies" read as a runtime step. The game loads no Composer package; now "for
    development", and a hosted copy has no `vendor/`.
  - "The game itself reads `dbconnect.php` in the repository root" was true only without `LOGD_DBCONNECT` (task 3, S12).
    Now it gives the order.
  - The setup.php paragraph ("The full host guide is a later task") and the backup and restore commands under
    "Upgrading" are replaced by pointers to the guide, so the steps live in one place. The "Upgrading" heading and its
    steps 2 to 4 stay, because the in-game "Upgrade Needed" text points at this document.
- `SUPPORTED-PLATFORMS.md`:
  - "not an installable supported game" and "Those deployment rules are not implemented or certified here": the
    install exists (Phase 4 task 1B, Phase 6 task 5b) and the rules now exist as samples. Reworded to what is true.
  - The runtime requirements now name the `composer.json` extension list. The sentence about sessions being needed
    "for the future application smoke gate" is gone: the game needs sessions now.
  - "Configuration transition" said there was no environment-variable precedence; `LOGD_DBCONNECT` has existed since
    task 3. The section is now "Configuration" and describes the current order, writer, `setup.php` and S4.
- Noticed, not changed here, because B3 (task 7) refreshes the front-door documents: `SECURITY.md`'s "not approved for
  public game hosting" banner, `MODULE-POLICY.md`'s certification paragraph and `README.md`'s status section. The
  in-game pages still name `docs/MODERN-INSTALLATION.md`; that text is code and stays.

## The guide and the sample rules

`docs/HOST-GUIDE.md` covers, in the order a host needs them: requirements (PHP, extensions, database, SQL modes,
HTTPS, maintenance, `php.ini`), the upload and do-not-upload lists, the docroot with the served and refused paths, the
configuration file (`LOGD_DBCONNECT`, how to set it on Apache, PHP-FPM, nginx and panels, owner and mode, the S4 page),
the install with a shell and with `setup.php`, a failed install, the settings to check (`serverurl`, `gameadminemail`,
`sourceurl`, the credits rule with its pending legal review, `csp_mode`, `requirevalidemail`), daily maintenance
(cPanel, Plesk and crontab lines, web maintenance with the header first, every 15 minutes, the status line, what
happens with neither), the checks after install, upgrade, backups and restore, mail and its limits (S11), logs and
troubleshooting.

Three sample rule files, in `docs/` (never uploaded) and marked "A SAMPLE, NOT ACTIVE":

- `docs/hosting/nginx.conf`: port 80 serves `/.well-known/acme-challenge/` and redirects the rest to HTTPS; port 443
  refuses the deny list with 404 (`location` blocks before `location /`), passes `.php` to PHP-FPM with
  `try_files $uri =404` and `fastcgi_param HTTPS on`, and shows `LOGD_DBCONNECT` as a commented `fastcgi_param`.
  The IPv6 `listen` lines are comments: a server without IPv6 refuses to start with them.
- `docs/hosting/apache.conf`: a `<Directory>` for the game's folder (no indexes, `AllowOverride None`, the files deny
  list in `<FilesMatch>`), `<DirectoryMatch>` for dot folders and the internal and development folders, PHP-FPM wrapped
  in `<IfModule mod_proxy_fcgi.c>`, the HTTPS host wrapped in `<IfModule mod_ssl.c>`, `SetEnv LOGD_DBCONNECT` commented.
  Apache allows no comment after a directive, so every CHANGE note is on its own line.
- `docs/hosting/htaccess`: the same deny list for hosts that allow only `.htaccess`: `RewriteRule ... - [R=404]` in
  `<IfModule mod_rewrite.c>`, then the files again with `Require all denied` in `<IfModule mod_authz_core.c>`. Its
  comments say what it needs (`AllowOverride FileInfo` and `AuthConfig`) and what a 500 means.

No `.htaccess` is added to the game's folder (the question for Jim is below).

`README.md` gains one line linking the guide. `MODERN-INSTALLATION.md` keeps local play, development and how the
commands behave, and links the guide for hosting.

## The server URL fix (runtime defect)

Found in the nginx check: after a `setup.php` install over HTTPS, the install's last screen offered web maintenance
at `http://game.example.org:443/webcron.php?key=...`. That address cannot work (plain HTTP to the TLS port), and the
game's mail (`lib/systemmail.php`, `resurrection_server_url()`) carries the same base address.

- **1.1.2** (`installer_default_settings.php` at `historical-source-1.1.2`):
  `"http://".$_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] == 80?"":":".$_SERVER['SERVER_PORT']).dirname($_SERVER['REQUEST_URI'])`.
  It always wrote `http://` and kept any port but 80. 1.1.2 had only the web installer, usually over HTTP, so this
  rarely showed; Resurrection's web install requires HTTPS, so it always did.
- **Now**: the install request's own scheme (`https://` when `HTTPS` is set and not `off`), the server's name, the
  port only when it is not the scheme's default (443 or 80), and the folder as before. Plain HTTP and the command line
  store what they stored before (`http://localhost/` from `scripts/install.php`).
- **Why**: runtime defect, recorded in `CHANGES-FROM-1.1.2.md`. The guide still tells the host to check the setting.
- **Test**: `tests/unit/InstallServerUrlTest.php` (3 tests). Against the `73cc1da` defaults file it fails:
  `testAnHttpsInstallStoresHttpsWithoutTheDefaultPort`, expected `'https://game.example.org/'`, actual
  `'http://game.example.org:443/'`. With the fix all 3 pass. After the fix, the nginx run's install stored
  `https://game.example.org/` and showed `https://game.example.org/webcron.php?key=...`; the Apache run on port 9443
  stored and showed `https://127.0.0.1:9443/`.

## The test: `tests/tooling/test_host_guide.py`

Nine tests, plain Python, no server or database, so they run in every shard and in Baseline integrity:

- `test_the_lists_are_there`: the guide has the upload, do-not-upload, served and refused lists.
- `test_every_top_level_path_has_a_decision`: every top-level file or folder in the repository (tracked and
  untracked-not-ignored) is on the served list or the refused list, and each served top-level `.php` file marks itself
  as an entry point (`RESURRECTION_ENTRY`).
- `test_every_refused_path_is_refused_by_every_rule_file`: each refused item, as a sample request path (also in other
  letter cases, such as `LIB/output.php` and `Composer.json`, and `assets/README.md`), is refused by each of the three
  rule files, parsed from their deny blocks.
- `test_nothing_served_is_refused`: no rule file refuses a served path or `.well-known/`.
- `test_the_upload_list_and_the_deny_list_agree`: nothing to upload is on the do-not-upload list, every refused folder
  and development file is on the do-not-upload list (or is a file the game makes), and every file in `scripts/` is on
  exactly one list.
- `test_generated_names_are_ignored_by_git`: the files the game or tools make (`dbconnect.php`, the token file and
  counter, `vendor/`, `.local-play/` and others) are ignored by Git, so the top-level check does not depend on them.
- `test_every_name_the_guide_uses_exists`: each file, page, command and option, environment variable, header, setting,
  log line, bold setting label and troubleshooting title the guide names is in the shipped code.
- `test_the_extensions_are_composer_json_s`: the guide's extension list is exactly `composer.json`'s.
- `test_the_samples_are_not_active`: each sample says "A SAMPLE, NOT ACTIVE", no `.htaccess` is in the repository's
  top folder, and no Apache directive has a trailing comment.

Fail first at `73cc1da`: the module errors at once, `FileNotFoundError: ... docs/HOST-GUIDE.md`. Passes on the final
tree (9 tests, OK).

Mutations, each run on the final tree and reverted (all fail the test named):

| Mutation | Fails |
|---|---|
| nginx folder rule without `var` | `test_every_refused_path_is_refused_by_every_rule_file` |
| `apache.conf` files rule without `project.json` | `test_every_refused_path_is_refused_by_every_rule_file` |
| `htaccess` rewrite without `dbconnect` | `test_every_refused_path_is_refused_by_every_rule_file` |
| nginx refusing `assets/` | `test_nothing_served_is_refused` |
| a comment after an Apache directive | `test_the_samples_are_not_active` |
| the guide names a setting that does not exist | `test_every_name_the_guide_uses_exists` |
| the guide labels a setting wrongly | `test_every_name_the_guide_uses_exists` |
| the guide names a header the code never sends | `test_every_name_the_guide_uses_exists` (passed at first: the header check matched inside a longer name; fixed with a word-boundary match, then it failed) |
| the guide names an option the command lacks | `test_every_name_the_guide_uses_exists` |
| the guide uploads `vendor/` | `test_the_upload_list_and_the_deny_list_agree` |
| the guide drops `docs/` from the deny list | `test_every_top_level_path_has_a_decision`, `test_the_upload_list_and_the_deny_list_agree` |
| a new top-level folder `newfolder/` | `test_every_top_level_path_has_a_decision` |
| a new top-level PHP file that is not an entry point | `test_every_top_level_path_has_a_decision` |

## Local checks behind real servers (supporting evidence, not in CI)

Docker on the session machine, images from `mirror.gcr.io`: nginx 1.28.3; PHP 8.4.27 FPM and PHP 8.4.27 with Apache
2.4.69 (Debian), each with `pdo_mysql` and `php.ini-production`; MariaDB 11.4.13. A self-signed certificate for
`game.example.org`. A Python client (scratch, not committed) requests every path, installs through `setup.php`
exactly as a host would (preflight, token file created as the PHP user with umask 077, the form, the last screen,
the web maintenance choice), calls `webcron.php`, signs in, takes the new day, visits the village and fights in the
forest.

The path check requests 44 refused paths (every item on the guide's deny list, using a real file under each folder,
plus other letter cases) and 14 served paths. Decoy files holding a marker string stand in for what a host might
upload by mistake or the game makes (`.git/HEAD`, `.env`, `.local-play/admin.env`, `var/`, `vendor/autoload.php`,
`prefixes.php`, a token file and counter). A refused path passes on 403 or 404 without the marker; a served path
passes on 200 or 302 without `<?php` in the body.

### nginx with PHP-FPM, final `nginx.conf`

The sample with two lines changed for the containers (`fastcgi_pass fpm:9000`, the `LOGD_DBCONNECT` line switched on
to `/srv/resurrection/private/dbconnect.php`). The docroot holds everything (the whole working tree plus `vendor/` and
the decoys, including a decoy `dbconnect.php`) and is mounted read-only into PHP.

- `nginx -t`: "syntax is ok", "test is successful".
- Paths before install: 44 refused, 14 served, 0 failures. After install: the same.
- Port 80: `/index.php` gives 301 to HTTPS; the ACME token gives 200.
- Install through `setup.php`: preflight all OK, the token file in the private folder, "The game is installed", the
  configuration file written to `/srv/resurrection/private/dbconnect.php` (`0600`, `www-data`); the decoy
  `dbconnect.php` in the docroot is ignored.
- The maintenance choice showed `https://game.example.org/webcron.php?key=...` (key 43 characters).
  `webcron.php` with the key in `X-Resurrection-Maintenance-Key`: 200 `OK\n`, `text/plain; charset=UTF-8`; with the
  key in the query: 200 `OK\n`; a wrong key: 404 with an empty body. The database then held
  `maintenance_last_run` `{"time":"2026-11-07 02:02:06","path":"web","result":"complete"}` and `serverurl`
  `https://game.example.org/`.
- Play: sign in, new day, Degolburg Square, the forest, a fight (Experience 15 / 100, Gold 64).
- `setup.php` after the install: empty 404.
- `cron.php` as `www-data` with `LOGD_DBCONNECT`: `{"maintenance":"already-complete",...}`, exit 0. Without it, the
  decoy `dbconnect.php` in the docroot is read and refused: the S4 line
  `Resurrection: the database configuration file cannot be used (broken): dbconnect.php`, then
  "Maintenance refused: the game could not start ... Nothing was run.", exit 1.

### Apache with mod_php, final `apache.conf`

The docroot holds exactly the guide's upload list plus the decoys and copies of the development files, docs, tests,
lab, local, `.github` and a script (so each refused path has a real file); the configuration file in the game's
folder (no `LOGD_DBCONNECT`).

- `apachectl -t`: Syntax OK.
- Paths before install: 44 / 14 / 0 failures. After install, with the real `dbconnect.php` and a planted
  `resurrection-setup.php` on disk: 44 / 14 / 0 (both refused with 403).
- Port 80 (earlier run, same port-80 block): `/home.php` 301 to `https://game.example.org/home.php`, the ACME token
  200, `/.git/HEAD` 301.
- Install through `setup.php`, the configuration file written to the game's folder (`0600`), the maintenance choice
  showing `https://127.0.0.1:9443/webcron.php?key=...`, `webcron.php` header 200 `OK\n`, query 200, wrong key 404
  empty, play with a fight (Experience 14 / 100).

### Apache with the `.htaccess` form

A shared host's virtual host with no rules of its own; the sample copied to the game's folder as `.htaccess`.

- `AllowOverride All`, mod_rewrite on: Syntax OK; paths 44 / 14 / 0 before and after install; install, webcron (200,
  200, 404) and play with a fight (Experience 13 / 100).
- mod_rewrite off (`a2dismod rewrite`): 13 failures, all folders: `/lib/`, `/vendor/` and `/docs/hosting/nginx.conf`
  listed or served, `/vendor/autoload.php` and `/tests/bootstrap.php` 500, `scripts/local-play.py`, `local/`, `var/`,
  `.git/HEAD`, `.git/config`, `.github/`, `.local-play/` served. The files layer still refuses `dbconnect.php`, the
  token file, `composer.json`, the `.md` files and the dotfiles at the top. This is why the guide says never to upload
  the "Do not upload" list, rules or not.
- `AllowOverride FileInfo` only: every request answers 500 (`/index.php` included); the error log says
  `.htaccess: Require not allowed in <FilesMatch> context`. This is the risk the question below is about.
- `AllowOverride None`: the file is ignored; 31 failures, the same as no rules at all.

## Local verification

On the final tree, before the commit (supporting evidence; CI is the acceptance):

- PHPUnit on PHP 8.5.12 and on PHP 8.4.27, each against an empty MySQL 8.4 database: OK (417 tests, 148794
  assertions) on both, `InstallServerUrlTest` included.
- The four tooling shards through `scripts/tooling-shards.py` (the new test's nine methods land in all four), each in
  its own copy of the tree with its own MySQL 8.4 database, after PHPUnit as in CI, PHP 8.5: shard 1 ran 70, shard 2
  175, shard 3 70, shard 4 69 (each with the account-setup prerequisite where needed); failures 0, errors 0, skipped 0
  in all four (12.3, 12.5, 12.6 and 11.7 minutes of Python time). A first attempt is not counted: the copies sat in a
  folder the `nobody` user cannot enter, so `test_operator_pages` (which runs its server as `nobody` under root) failed,
  and the run's own `result.txt` in a copy's top folder failed `test_every_top_level_path_has_a_decision`, as that test
  should. Both were fixed in the runner, not in the tests.
- `tests.tooling.test_host_guide` and `tests.tooling.test_tooling_shards`: OK.
- PHP lint on 8.5 and 8.4: 443 files, 0 failures. PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors.
- `composer validate --strict`: valid. `composer install`: nothing to change. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: pass.
  `git diff --check`: clean; no trailing whitespace or CR in the new files.

## Notes

- `php.ini-production` leaves `expose_php` on, so both images sent `X-Powered-By: PHP/8.4.27`. The guide says to turn
  it off; the game does not override it.
- Known, already on the roadmap: after signing in again, the replayed page can carry stale form tokens; the client
  reloads the form's page and continues.
- The cPanel and Plesk lines follow those panels' scheduled task screens as generally documented; they were not run
  on a real panel here. In particular, whether Plesk's "Run a command" accepts a `VAR=value` prefix is not verified
  here; a host whose panel refuses it can wrap the line in `sh -c '...'` or keep `dbconnect.php` in the game's folder.

## For Jim: an active `.htaccess` in rc.1, or only the samples?

**Question.** Should rc.1 ship an active `.htaccess` in the game folder (the Apache rules switched on by default), or
only the samples in `docs/hosting/`?

- **A. Only the samples (recommended).** Nothing changes for any host until the host copies a rule file.
  - Risk: a host who skips the step serves whatever they upload. With the guide's upload list that is only the game's
    own files, and the internal PHP already answers 404 (S2), the configuration file and token file print nothing,
    and `setup.php` is closed once installed. What leaks is what the host should not have uploaded (`vendor/`, `.git`,
    `docs/`) and folder listings where the server lists folders.
- **B. Ship an active `.htaccess`.** Apache hosts that allow it get the rules with no step.
  - Risk: on a host whose `AllowOverride` leaves out `AuthConfig` or `FileInfo` (common on locked-down shared hosting),
    every page answers 500 with no hint in the browser, as the local check showed. A first-time host sees a broken game
    and may not find the cause. It is ignored on nginx, LiteSpeed configurations that do not read it and
    `AllowOverride None`, so it gives a false sense of cover there. Wrapping more blocks in `<IfModule>` does not help:
    a `Require` the host forbids is a 500 whatever module is loaded. Upgrades also have to carry it, and a host's own
    edits to it get overwritten.
- **C. Ship a smaller active `.htaccess`** (only `Options -Indexes` and the files `<FilesMatch>`). Fewer directives,
  but `Options` and `Require` still each need an override the host may not give, so the 500 risk stays.

Recommendation: A for rc.1, with the guide's check after install ("open `dbconnect.php` and `lib/`") so each host sees
in a minute whether their server refuses them. Revisit after rc.1 feedback from real hosts.

## Acceptance and decision (recorded in Phase 6 task 7, 2026-11-07)

Task 6 is **accepted at `5f2d951`**:

- Modern core 42329102594: **SUCCESS**, 12/12 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http` shards
  19:53, 20:52, 18:53, 19:27; PHP 8.5 / MySQL 8.4 `http` shards 15:12, 21:03, 18:25, 17:47.
- Baseline integrity 42329102592: **SUCCESS**.

Jim's answer to the question above (2026-11-07): **A, only the samples.** rc.1 ships the sample server rules in
`docs/hosting/` and no active `.htaccess`.
