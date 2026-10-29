# Phase 4 task 3: one-command local setup (2026-10-29)

Starting `main`: `86f8e7426b283df291174111cd85cfef09fa044a` (the task 2 fix). Task 2's acceptance through `86f8e74`
is recorded in `PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`, "Acceptance" (Modern core 40858859597, all 12 jobs;
Baseline integrity 40858859591; `ca4e660`'s failed Modern core 40855211633 kept on record).

**Local use only, not for hosting.** This task lets Jim play the game on his own computer with one command. It is not
a deploy, not hosting and not a release. Nothing here touches any server, no tag is created, and no image is published.

## The commands

```sh
python3 scripts/local-play.py up      # start the game; the first time, install it
python3 scripts/local-play.py stop    # stop it; the game is kept
python3 scripts/local-play.py wipe    # delete the game, its data volumes and its credentials
```

Then open http://127.0.0.1:8080/. Also: `backup`, `upgrade --dry-run`, `upgrade --backup-confirmed` and
`restore FILE` (`docs/MODERN-INSTALLATION.md`, "Play locally").

## The approach, and why

**Docker Compose, started by a small Python command.** `local/compose.yaml` runs the supported stack, and
`scripts/local-play.py` is the one command that drives it.

- **Docker**, because it brings a supported pair (PHP 8.4 and MariaDB 11.4, one of CI's two legs) without installing
  PHP or a database server on the computer, the same way on Linux, macOS and Windows. A script that used the computer's
  own PHP and database would first need both installed at supported versions.
- **A command in front of Compose**, because plain `docker compose up` cannot do four things the task asks for:
  generate the credentials into an ignored file owned by the user (a container would write it as root on Linux),
  print the administrator login exactly once, decide before the game starts whether this is a new game, a current
  game or one that needs an upgrade (and then not start it), and ask before a wipe. Python 3 because the project's
  tooling is Python, it runs on every platform Docker does, and its functions can be tested without Docker.
- **PHP's built-in web server**, the server CI's HTTP tests use, with `PHP_CLI_SERVER_WORKERS=4`. It is fine for one
  player on one computer and is one more reason the setup must never be used for hosting.
- **The configuration file in a Docker volume**, `/config/dbconnect.php`, outside the served directory: `scripts/install.php
  --write-config` writes it there without `--allow-served-path`, and a link at the game's `dbconnect.php` points to it.
  It is never in the clone.
- **The code is copied into the image** (not mounted), so the served tree holds no `.git`, tests, vendor, local state or
  credentials (`local/Dockerfile.dockerignore`, and `local/` itself is removed from it). `up` rebuilds the image, so an
  edit or a `git pull` takes effect at the next `up`, which is also when the upgrade check runs.

## What `up` does

1. Checks Docker and Compose. If a local game exists in Docker but `.local-play/` has lost its credentials, it stops and
   says so instead of generating new ones that would not match.
2. The first time, generates `.local-play/database.env` (database name and user, a 32-character password and a
   32-character root password) and `.local-play/admin.env` (login `admin`, a 20-character password): letters and
   digits from Python's `secrets`, the directory 0700, each file created exclusively with mode 0600. Git ignores
   `/.local-play/`.
3. Builds the image (`php:8.4-cli`, `pdo_mysql`, PHP's `php.ini-production` as CI's setup-php uses, `local/php.ini`:
   errors logged not shown, `zend.exception_ignore_args`, mail to `/bin/true`, sessions in a private tmpfs), runs as
   an unprivileged user (uid 10001).
4. Starts `db` (`mariadb:11.4`, utf8mb4) and waits until it is healthy. The image creates the empty database and its
   dedicated user from `database.env`.
5. Runs the one-off `setup` container (`local/setup.sh`):
   - **No configuration file: a new game.** `php scripts/install.php --write-config /config/dbconnect.php`, with
     DB_HOST, DB_USER, DB_PASS and DB_NAME and the administrator in its environment (never in arguments). That is the
     task 1B install path unchanged: it checks everything, writes the file (0600), installs into the empty database
     with the 23 recommended modules active, and creates the administrator.
   - **A configuration file: an existing game, never installed again.** `php scripts/upgrade.php --dry-run`, which
     changes nothing. "The database is current" starts the game; pending steps or module upgrades stop here.
6. Starts `app` (published on `127.0.0.1:8080` only) and `cron` (`php cron.php` every 900 seconds; cron.php itself
   skips a game day it already completed), waits until the app answers, and prints the address and, after a new
   install only, the administrator login.

When the code is newer than the database, `up` prints the dry run's list, stops `app` and `cron`, and shows the
backup, dry run, upgrade and play commands. It never runs the upgrade. A refusal (a downgrade, a database that is
not a completed install) also leaves the game stopped, with `upgrade.php`'s reason and the restore and wipe commands.

## Safe by default

| Rule | How | Held by |
|---|---|---|
| 127.0.0.1 only | `ports: - "127.0.0.1:${RESURRECTION_LOCAL_PORT:-8080}:8080"` on `app`, the only published port; the variable changes only the port | `test_game_is_published_only_on_loopback`; `docker ps` in the flow |
| No database port | `db` has no `ports`; it is on the `database` network, `internal: true` (no route out); only `app` also joins `web` | `test_database_publishes_no_port_and_is_internal`; `docker ps` shows `3306/tcp` unpublished |
| Credentials generated, ignored, never committed | `.local-play/` in `.gitignore`; files 0600 in a 0700 directory | `test_credentials_are_generated_owner_only_and_ignored`; `validate-project.py` |
| Database password never printed | Given to Compose only as an `env_file`; to the install as environment; the backup and restore clients read it as `MYSQL_PWD` inside the database container; `setup.sh` drops the root password from its environment before anything runs | The test above; the flow found it 0 times in the transcript and the containers' logs |
| Administrator login printed once | Printed after a new install; later runs say where it is | The flow, steps 1 and 4 |
| Labelled | "LOCAL USE ONLY ... not for hosting" in the compose file, every `up` and the docs | `test_labelled_local_use_only` |
| Install only through `scripts/install.php`, upgrades only through `scripts/upgrade.php` | `local/setup.sh`; no other install code in `local/` or the command | `test_install_goes_through_the_install_command`, `test_upgrade_is_never_automatic` |
| Wipe removes the data | `docker compose down --volumes --remove-orphans` | `test_wipe_removes_the_data_volumes`; `docker volume ls` empty after the wipe |

Every container sets `no-new-privileges`; the game's three containers (`setup`, `app`, `cron`) also drop all
capabilities and run read-only with a private `/tmp` (the database image needs its own start-up privileges). A request for `/.local-play/database.env` or
`/local/compose.yaml` gets the game's redirect, not the file: neither is in the image.

## Finding: the game needs a `php.ini` (output buffering)

The first local run installed the game, but every web page then failed with "Runtime error in common.php:206". PHP's
official Docker image ships no `php.ini`, so `output_buffering` is 0. `common.php` requires `lib/all_tables.php`
(1.1.2's file, unchanged: it ends with `?>` and two newlines, so it prints one newline) at line 102, before its own
output buffer starts at line 128; with buffering off that newline is sent, and the `header()` call at line 206 fails.
`scripts/upgrade.php` already discards the same newline for its own report.

- **Not in 1.1.2.** 1.1.2's `common.php` did not include `lib/all_tables.php`; the include came with `899cf0b`
  (2026-09-13, Phase 2). 1.1.2's own pages therefore never depended on output buffering.
- **Why CI never saw it.** setup-php runs PHP with `php.ini-production` (`output_buffering = 4096`), and so does the
  PHP 8.5 build used for local runs. With 4096 the newline is buffered and the page works. Checked in the container:
  `-d output_buffering=4096` serves `home.php` normally.
- **Here:** the image installs `php.ini-production`, so the local game runs PHP exactly as CI does (held by
  `test_runtime_is_a_supported_pair_configured_as_ci`). `docs/MODERN-INSTALLATION.md` now says the game needs PHP's
  standard `php.ini`.
- **Not fixed here.** Removing the dependency (discarding that newline in `common.php`, as `scripts/upgrade.php`
  does) changes the bootstrap of every page and needs its own test that fails without it, which needs a database and
  a server without output buffering. That is outside this task; it is proposed as a next task.

## Tests

`tests/tooling/test_local_play.py`, nine static tests. They read `local/compose.yaml`, `local/setup.sh`,
`local/Dockerfile` and the command, and call the command's functions; they run no Docker and no PHP, so they run in
Baseline integrity too. No HTTP test was added.

| Test | Checks |
|---|---|
| `test_compose_is_plain_block_style` | The four services; no flow-style ports or services (which the indentation reader below could miss); no bare port |
| `test_game_is_published_only_on_loopback` | `app` publishes exactly `127.0.0.1:${RESURRECTION_LOCAL_PORT:-8080}:8080`; every published port starts with `127.0.0.1:`; no host networking |
| `test_database_publishes_no_port_and_is_internal` | `db`, `setup`, `cron` publish nothing; `db` has no `ports` or `expose`; the `database` network is internal; only `app` joins `web` |
| `test_runtime_is_a_supported_pair_configured_as_ci` | `php:8.4-cli` with `php.ini-production`, `mariadb:11.4`; the image leaves out `.git`, `.local-play`, tests, vendor, `dbconnect.php`, `.env` |
| `test_install_goes_through_the_install_command` | One install, `php scripts/install.php --write-config "$CONFIG"`, recommended modules, no served path; an existing config file means the dry run; no other install code; the "current" words match `lib/upgrade.php` |
| `test_upgrade_is_never_automatic` | `upgrade` accepts exactly `--dry-run` or `--backup-confirmed` and runs `scripts/upgrade.php`; `up` never applies |
| `test_credentials_are_generated_owner_only_and_ignored` | `.local-play/` files and backups ignored and untracked; generated 0700/0600, valid login, 12 to 72 byte password, plain characters, kept on a second run; the password only in `MYSQL_PWD` and never as `-p` |
| `test_wipe_removes_the_data_volumes` | `wipe` is `down --volumes --remove-orphans`; `stop` keeps volumes; the database lives in the `db` volume |
| `test_labelled_local_use_only` | The label in the compose file and the command |

**Failing first, passing after** (`python3 -B -m unittest tests/tooling/test_local_play.py`; `-B` because a reverted
file of the same size and second could otherwise load from Python's bytecode cache):

| Tree | Result |
|---|---|
| `86f8e74` with only the test added | ERROR: `local/compose.yaml` does not exist (0 tests ran) |
| The task's tree | OK, 9 tests |
| `app` published as `"${RESURRECTION_LOCAL_PORT:-8080}:8080"` (every address) | FAIL `test_game_is_published_only_on_loopback` |
| `db` given `ports: - "127.0.0.1:3306:3306"` | FAIL `test_database_publishes_no_port_and_is_internal` |
| The `database` network `internal: false` | FAIL `test_database_publishes_no_port_and_is_internal` |
| `/.local-play/` removed from `.gitignore` | FAIL `test_credentials_are_generated_owner_only_and_ignored` |
| Credentials created 0644 | FAIL `test_credentials_are_generated_owner_only_and_ignored` |
| `setup.sh` installs without `--write-config` | FAIL `test_install_goes_through_the_install_command` |
| `setup.sh` installs even when the config file exists | FAIL `test_install_goes_through_the_install_command` |
| `setup.sh` runs `upgrade.php --backup-confirmed` | FAIL `test_install_goes_through_the_install_command` |
| `wipe` without `--volumes` | FAIL `test_wipe_removes_the_data_volumes` |
| The image without `php.ini-production` | FAIL `test_runtime_is_a_supported_pair_configured_as_ci` |

The shard runner assigns the nine tests itself (`scripts/tooling-shards.py`); they cost the median weight, as every
test without a measured time does.

## The full flow, run once from a clean clone (supporting evidence)

Run on 2026-10-29, 20:25:50 to 20:27:06 UTC, in this cloud session's Docker (Docker Compose v5.6.1; images
`php:8.4-cli` = PHP 8.4.27 and `mariadb:11.4` = MariaDB 11.4.13), from `git clone` of a local, unpublished commit whose
`.gitignore`, `local/`, `scripts/local-play.py` and test are byte-identical to this task's commit (only the docs
differ). The image was already in the build cache from earlier runs; a first run that built it took 41 seconds from
the command to the running game. The player is driven over HTTP as a browser would (a script in the session's
scratch space, not in the repository). Step 5's upgrade step file was written into the clone only, to make the code
newer than the database, and removed in step 6; it is not part of the task. Docker's progress lines and timestamps
are left out, and the generated administrator password is redacted here.

```text
== 1. up from the clean clone ==
$ git status --short --ignored                      (nothing)
$ docker volume ls                                  (no volumes)
$ python3 scripts/local-play.py up
LOCAL USE ONLY: this game is for playing on this computer, not for hosting.
{"tables":39,"modules_installed":24,"modules_active":23,"administrator":1,"state":"installed","modules":"recommended","config":"written"}
==============================================================================
Legend of the Green Dragon - Resurrection is running.
LOCAL USE ONLY: this game is for playing on this computer, not for hosting.
Play:  http://127.0.0.1:8080/
A new game was installed. Administrator login (shown once; also kept in .local-play/admin.env):
  Login:     admin
  Password:  (redacted: 20 generated letters and digits)
Stop (keeps the game):  python3 scripts/local-play.py stop
Wipe (deletes it):      python3 scripts/local-play.py wipe
==============================================================================
exit 0
$ ls -la .local-play
drwx------  .   -rw-------  admin.env   -rw-------  database.env
$ docker ps
resurrection-local-app-1 | 127.0.0.1:8080->8080/tcp | Up 5 seconds (healthy)
resurrection-local-cron-1 |  | Up 5 seconds
resurrection-local-db-1 | 3306/tcp | Up 19 seconds (healthy)
$ (in app) ls -la /config /srv/game/dbconnect.php
/srv/game/dbconnect.php -> /config/dbconnect.php
drwx------ game game  /config      -rw------- game game  256  dbconnect.php

== 2. play ==
home.php -> Legend of the Green Dragon
register LocalHero -> Your account was created
login -> 303
first New Day -> A little history about yourself
race Elf, specialty Thieving Skills chosen
village.php -> Degolburg Square
forest.php -> The Forest
forest search -> The Forest | event
admin login admin -> superuser.php | Superuser Grotto
$ curl http://127.0.0.1:8080/.local-play/database.env    302 (the game's redirect; the file is not served)
$ curl http://127.0.0.1:8080/local/compose.yaml          302
$ docker logs resurrection-local-cron-1
{"maintenance":"complete","game_day":"1970-05-04"}

== 3. stop ==
$ python3 scripts/local-play.py stop
The local game is stopped. Its data is kept; `python3 scripts/local-play.py up` starts it again.
$ docker ps -a                                      (no containers)
$ docker volume ls                                  resurrection-local_config, resurrection-local_db

== 4. up again: the game is kept ==
$ python3 scripts/local-play.py up
The database is current: 0 of 0 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.
Legend of the Green Dragon - Resurrection is running.
Your existing game was kept. The administrator login is in .local-play/admin.env.
exit 0
login LocalHero (existing character) -> 303
after login, the character is where it was left -> badnav.php | The Forest
admin login admin -> superuser.php | Superuser Grotto

== 5. code newer than the database ==
$ python3 scripts/local-play.py up
Pending:
- step 0001-local-play-evidence: Local play evidence step (test only)
Dry run: nothing was changed.
The code is newer than this game's database: it needs an upgrade before it can be played.
Nothing was upgraded, and the game is not started. To upgrade (docs/MODERN-INSTALLATION.md, "Play locally"):
  1. Back up:    python3 scripts/local-play.py backup
  2. Check:      python3 scripts/local-play.py upgrade --dry-run
  3. Upgrade:    python3 scripts/local-play.py upgrade --backup-confirmed
  4. Play:       python3 scripts/local-play.py up
exit 1
$ docker ps                                         resurrection-local-db-1 only
$ python3 scripts/local-play.py upgrade
Use `upgrade --dry-run` to see what would change, or `upgrade --backup-confirmed` after `backup`.
exit 1
$ python3 scripts/local-play.py backup
Backup written: .local-play/backups/resurrection-20261029-202635.sql (keep it private: it holds every account).
-rw------- 175177 bytes; last line: -- Dump completed on 2026-10-29 20:26:35
$ python3 scripts/local-play.py upgrade --dry-run
Pending:
- step 0001-local-play-evidence: Local play evidence step (test only)
Dry run: nothing was changed.
$ python3 scripts/local-play.py upgrade --backup-confirmed
Applying:
- applied and recorded step 0001-local-play-evidence
Upgrade complete: applied 1 upgrade step(s) and 0 module upgrade(s). The database is current.
Start the game again with: python3 scripts/local-play.py up
$ python3 scripts/local-play.py up
The database is current: 1 of 1 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.
Legend of the Green Dragon - Resurrection is running.
exit 0

== 6. the code goes back (the step file removed): a downgrade is refused ==
$ python3 scripts/local-play.py up
Upgrade refused. This database is newer than this version of Resurrection: it has upgrade steps this version does not have (0001-local-play-evidence). A downgrade is refused: run the newer version again, or restore the backup taken before it upgraded this database. Nothing was changed.
The game was not started: the setup refused or failed (the reason is above). Nothing was changed.
To put a backup back:   python3 scripts/local-play.py restore .local-play/backups/FILE.sql
To start a new game:    python3 scripts/local-play.py wipe, then up
exit 1
$ python3 scripts/local-play.py restore .local-play/backups/resurrection-20261029-202635.sql --yes
The backup is restored. Use the version of the game it was taken with, then: python3 scripts/local-play.py up
$ python3 scripts/local-play.py up
The database is current: 0 of 0 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.
exit 0
login LocalHero (existing character) -> 303
after login, the character is where it was left -> badnav.php | The Forest
admin login admin -> superuser.php | Superuser Grotto
database password in the transcript: 0, in the containers' logs: 0; dbconnect.php mode: 600
$ git status --short --ignored                      !! .local-play/

== 7. wipe ==
$ echo no | python3 scripts/local-play.py wipe
... Type "wipe" to continue: Nothing was deleted.
exit 1
$ python3 scripts/local-play.py wipe --yes
The local game, its data volumes and its credentials are deleted. `python3 scripts/local-play.py up` installs a new game.
$ docker ps -a; docker volume ls; docker network ls --filter name=resurrection-local     (all empty)
$ git status --short --ignored                      !! .local-play/   (the backups, kept by design)
```

A first, earlier run of the same flow is what found the output buffering defect above; it is not counted as evidence.

## Local verification (supporting evidence only)

PHP 8.5.12 (php-builder, as setup-php) and MySQL 8.4 in Docker, on the task's tree:

- `vendor/bin/phpunit` on a fresh database: OK, 281 tests, 141332 assertions.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 391 files, 0 failures. No PHP file was added or changed.
- `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: 417 files, 11 historical commits, tag object, commit and tree as recorded.
- `scripts/validate-project.py`: PASS. `git diff --check`: clean. `README.txt` is byte-identical to the tag.
- Tooling tests without a database (`test_local_play`, `test_tooling_shards`, `test_license_text`, `test_version`,
  `test_baseline`, `test_recommended_modules`, `test_route_inventory`, `test_lab`, `test_php_runtime`,
  `test_disabled_endpoints`, with `RESURRECTION_REQUIRE_PHP=1`): OK, 46 tests.
- No shared helper changed and no HTTP test was added, so no shard was run locally. CI runs the full suite.

## Not changed

- `scripts/install.php`, `scripts/upgrade.php`, `cron.php` and every PHP file: the setup only calls them.
- `README.txt` (byte-identical to `historical-source-1.1.2`). `README.md` gained a short pointer to "Play locally".
- The version string, the shard timeout and `tests/tooling/shard-durations.json`.
- No tag, release, image push or hosting. The release stays excluded until Jim says so.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` marks task 3 done
only after CI accepts it.

## Acceptance (appended 2026-10-29, output buffering fix)

Task 3 is **accepted at `0c39020`**. Read through the GitHub Actions API on 2026-10-29:

- Modern core 40950097216: **SUCCESS, all 12 jobs** (both legs' `checks`, eight `http` shards and both `result` jobs,
  20:33 to 20:55 UTC). Shard steps: PHP 8.4 / MariaDB 11.4 13:29, 17:47, 18:05, 16:59; PHP 8.5 / MySQL 8.4 18:28,
  15:02, 21:02, 13:13 (shards 1 to 4), all under 30 minutes.
- Baseline integrity 40950097228: **SUCCESS**.

`ROADMAP.md` marks task 3 done in the commit that fixes the defect found above ("Finding: the game needs a `php.ini`").
That fix removes the dependency: the game now runs with `output_buffering` off (`PHASE4-OUTPUT-BUFFERING-FIX-20261029.md`).
The local image still installs `php.ini-production`, so local play keeps CI's settings.
