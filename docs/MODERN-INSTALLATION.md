# Modern local installation and operation

Public hosting is not approved yet. These instructions are for a local, disposable development installation. Resurrection installs fresh only: a 1.1.2 database is not migrated or upgraded.

## Play locally

**Local use only, not for hosting.** This runs the game on your own computer for play and development. It uses PHP's built-in web server, no TLS and no mail, and publishes the game only on 127.0.0.1. Public hosting is not approved and has its own gates (`ROADMAP.md`, "Public hosting").

You need Docker with Compose v2.24 or newer, and Python 3. From a clone of the repository:

```sh
python3 scripts/local-play.py up      # start the game; the first time, install it
python3 scripts/local-play.py stop    # stop it; the game is kept
python3 scripts/local-play.py wipe    # delete the game, its data volumes and its credentials
```

Then open http://127.0.0.1:8080/ (set `RESURRECTION_LOCAL_PORT` to use another port; the address stays 127.0.0.1).

**What `up` does.** It runs `local/compose.yaml` with three services: `db` (MariaDB 11.4), `app` (PHP 8.4 with PHP's `php.ini-production`, as CI's setup-php uses, serving the game) and `cron` (`php cron.php` every 15 minutes). The first time, it generates a database password, a database root password and an administrator password into `.local-play/database.env` and `.local-play/admin.env`. The directory is owner-only, the files 0600, and Git ignores them. The database starts empty, and a one-off `setup` container runs `php scripts/install.php --write-config /config/dbconnect.php`: the guided config step above writes the configuration file (0600, in a Docker volume outside the served directory), then installs with the recommended modules active and creates the administrator. `up` prints the administrator login once, at that first install; afterwards it says where it is (`.local-play/admin.env`). The database password is never printed or passed on a command line, and the database publishes no port: it sits on an internal Docker network that only the game's containers join.

**Running `up` again** keeps the game. When the configuration file exists, the game is never installed again: `setup` runs `php scripts/upgrade.php --dry-run`, which changes nothing. When the database is current, the game starts. When the code is newer than the database, `up` says so, leaves the game stopped, upgrades nothing, and shows the commands:

```sh
python3 scripts/local-play.py backup                      # write .local-play/backups/resurrection-DATE.sql
python3 scripts/local-play.py upgrade --dry-run           # what the upgrade would change
python3 scripts/local-play.py upgrade --backup-confirmed  # upgrade (scripts/upgrade.php, see "Upgrading")
python3 scripts/local-play.py up                          # play again
```

`upgrade` stops the game while it runs `scripts/upgrade.php` and refuses anything but one of those two options. `backup` checks that the file ends with `-- Dump completed` and keeps it owner-only; it holds every account, so keep it private. A database newer than the code (a downgrade) is refused, as in "Upgrading"; the way back is the backup taken before the upgrade:

```sh
python3 scripts/local-play.py restore .local-play/backups/resurrection-DATE.sql
```

`restore` asks first, then replaces everything in the local game's database with the file. Run it with the version of the game the backup was taken with.

**`stop` and `wipe`.** `stop` removes the containers and keeps the data volumes, so the next `up` continues the same game. `wipe` asks first (or takes `--yes`), then removes the containers and the data volumes (`docker compose down --volumes`) and the generated credentials; backups in `.local-play/backups/` are kept. The next `up` installs a new game. Edits to the code take effect at the next `up`, which rebuilds the image.

## Runtime and database

Use a standard PHP 8.4 or 8.5 build with PDO MySQL, mbstring, JSON, filter, and native sessions, and PHP's standard `php.ini` (`php.ini-production`, as CI uses, or `php.ini-development`). The game needs that file's output buffering: without a `php.ini` (as in PHP's official Docker image), every web page of an installed game fails with a runtime error (`PHASE4-TASK3-LOCAL-SETUP-20261029.md`). Install the locked Composer dependencies. The CI pairs are PHP 8.4 with MariaDB 11.4 and PHP 8.5 with MySQL 8.4. A sessionless PHP build cannot run the application.

Create a dedicated **empty** database and a dedicated account authorized only for that database. Use utf8mb4 at database creation. Application connections explicitly use utf8mb4 and retain server SQL modes while adding STRICT_TRANS_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO, and NO_ENGINE_SUBSTITUTION. Fresh tables use InnoDB and utf8mb4_unicode_ci. Do not weaken global SQL modes.

The database configuration file is PHP that sets these variables:

- DB_HOST: hostname or IPv4 address, optionally followed by a TCP port.
- DB_USER, DB_PASS, DB_NAME: the dedicated development database credentials.
- DB_PREFIX: an optional ASCII table prefix (letters, digits and underscores); default empty.
- DB_USEDATACACHE: use 0 for initial validation.
- DB_DATACACHEPATH: empty when caching is disabled.

Supply RESURRECTION_ADMIN_LOGIN, RESURRECTION_ADMIN_PASSWORD and optionally RESURRECTION_ADMIN_EMAIL through the local process environment. Do not put passwords in command arguments, committed files or shell history. Passwords must contain 12 to 72 bytes; the maximum prevents silent bcrypt truncation under the current PASSWORD_DEFAULT implementation.

### Step 1: the configuration file

Either let the install command write it, or write it yourself.

**The command writes it (guided).** Put DB_HOST, DB_USER, DB_PASS, DB_NAME and optionally DB_PREFIX in the local process environment, the same way as the administrator credentials, and give `--write-config` and an absolute path:

```sh
php scripts/install.php --write-config /absolute/private/path/dbconnect.php
```

The file is written through the project's LegacyConfig generator with DB_USEDATACACHE 0 and an empty DB_DATACACHEPATH, created owner-only (0600) from the start. The command never overwrites a file: an existing file or link at that path is refused. A path inside the served directory (the repository root and everything under it) is refused unless you add `--allow-served-path`. Before writing, the command checks the path, the values, the administrator credentials, and that the database is reachable and empty; if any of these refuses, nothing is written. It then installs with the file it wrote, so a successful install proves the file works. Output and errors never contain a credential.

**You write it.** Create an owner-readable file outside the served directory with the variables above, and give its path:

```sh
php scripts/install.php /absolute/private/path/dbconnect.php
```

Use the LegacyConfig generator when writing configuration from code; never interpolate credentials into PHP source. With no path the command reads `dbconnect.php` in the repository root.

The game itself reads `dbconnect.php` in the repository root. For local play, either point to it with `--allow-served-path` (for example `php scripts/install.php --write-config --allow-served-path "$PWD/dbconnect.php"`) or copy the private file there with restrictive permissions; it is ignored by Git. The CI creates this file exclusively and removes it after loopback tests.

### Step 2: the install and its modules

The command runs the supported installation service, creates the historical core schema and chronological fresh seed content, installs the fixed 24 shipped modules and their support data, activates the recommended modules, creates a modern administrator, and records completion only after all of that succeeds. In the same final transaction it records every upgrade step in `upgrades/` as applied in the `upgrade_steps` table, because a fresh schema already has them (see "Upgrading"). Since task 2 the install no longer writes 1.1.2's `installer_version` setting: the shown version is not an install marker. Output contains counts and state, never credentials.

`--modules` chooses what the install activates:

- `--modules=recommended` (the default): 1.1.2's recommended modules that are bundled, 23 of the 24: `cedrikspotions`, `crazyaudrey`, `dag`, `darkhorse`, `drinks`, `fairy`, `findgem`, `findgold`, `foilwench`, `game_dice`, `game_stones`, `glowingstream`, `goldmine`, `lovers`, `outhouse`, `racedwarf`, `raceelf`, `racehuman`, `racetroll`, `sethsong`, `specialtydarkarts`, `specialtymysticpower`, `specialtythiefskills`. The one bundled module not on 1.1.2's list, `game_fivesix`, is installed and inactive. A new game is playable right away.
- `--modules=none`: all 24 installed and inactive, for an operator who chooses modules one by one in the module manager.

This follows 1.1.2, whose installer pre-selected its recommended modules for activation on every fresh install (`docs/PHASE4-TASK1B-FRESH-INSTALL-20261029.md` has the evidence). Activation uses the module lifecycle API inside the install's lock, in the same transaction as the administrator and the completion marker: if any activation fails, none is kept, no administrator exists and the install is not recorded complete. The module manager can change any module afterwards.

The installer uses an advisory database lock. Any existing table causes refusal, including an unrelated populated database, a partial failed install, and a previous historical game. A completed Resurrection database is reported as installed. A recognizable 1.1.2 database (its settings `installer_version` 1.x and an accounts table) is reported as unsupported: the command says that Resurrection does not upgrade or migrate a 1.1.2 database and that a fresh install into an empty database is the only path. Unrelated tables are reported as populated. Each is refused with its reason, without modification, and `--write-config` writes nothing for them. No automatic truncate, drop, retry cleanup or in-place upgrade is performed. Diagnose a failed disposable installation before explicitly recreating its disposable database. Never apply that procedure to valuable data.

The historical web installer returns 403 before bootstrap. Direct requests cannot install or upgrade, even if an installation marker is removed. scripts/install.php rejects HTTP with 404. No 1.1.2 database will be migrated; installs are fresh only.

## Authentication and sessions

Fresh accounts use password_hash(PASSWORD_DEFAULT) and password_verify. Legacy hashes and submitted stored hashes cannot authenticate. The former client-side MD5, password-derived recovery and email-validation login flows are unavailable. Password recovery is removed (since 2026-10-24 an unknown op, 400; resets stay an admin action in the user editor) and the old email validation link returns 410; email verification must be modernized before enabling requirevalidemail. Normal creation fails closed when that unsupported requirement is enabled.

Login, logout, account creation, password changes and the game, administrative, moderation and module actions that change state require POST and a session-bound CSRF token; `ROUTE-INVENTORY-20261022.md` lists every route, who reaches it and whether it changes state. Existing navigation state continues to govern game navigation and is not treated as CSRF protection. The logout link displays a confirmation form; POST destroys the native session and expires its cookie.

Native sessions use strict ID acceptance, cookie-only transport, HttpOnly and SameSite=Lax. Secure is set when the web server reports HTTPS=on. An eventual production proxy must establish that trusted server value; client-supplied forwarding headers must not decide it. Keep display_errors off in production, error_reporting at E_ALL, zend.exception_ignore_args enabled, session files private, and session storage isolated from other applications. Production TLS, headers, authorization review and abuse controls remain separate hosting gates.

A successful login rotates the session ID and CSRF token. A database authentication generation invalidates older sessions after another login, logout or password change. Privileges are reloaded from the database on each authenticated request and a change rotates the session. Authentication diagnostics use a fixed event vocabulary with no password, hash, token, session ID or cookie serialization.

## Scheduler

Fresh installs set newdaycron=1. Global maintenance is run locally with:

```sh
php cron.php
```

The command must run with the same private configuration and filesystem ownership as the application. Schedule it often enough to cover the configured game-day interval. The project does not install a scheduler or deploy a server.

A database advisory lock serializes maintenance. A completed game-day marker makes repeated invocations skip that day's maintenance. The marker is written after successful maintenance. Player New Day behavior and its turn/economy rules remain separate. Each bundled daily hook commits its writes with a per-day receipt, so a failed run rolls that hook back and a retry skips hooks already done; the core cleanup is not one transaction, and a non-bundled module's filesystem, network or DDL effects would not roll back (`SECURITY-STATUS.md`, S13). Running a scheduler in production is a public hosting gate. HTTP GET and POST to cron.php return 404 before bootstrap.

## Upgrading

A new version of Resurrection can change the database. Each change is a numbered upgrade step in `upgrades/`; a bundled module's change is a raised module version. A database records the steps it has applied in its `upgrade_steps` table. Once the new version's files are in place, the game compares that record and the bundled modules' versions with its own:

- **Behind** (steps or module upgrades pending): every page shows 1.1.2's "Upgrade Needed" message, with a line for the operator that names the commands below, and returns players to the game once the upgrade is done. `cron.php` refuses to run maintenance.
- **Ahead** (the database has a step this version does not have, or a newer bundled module): "Game Unavailable". This is a downgrade, and it is refused: run the newer version again, or restore the backup taken before it upgraded the database.
- A 1.1.2 database stays unsupported, and an incomplete install stays refused. Neither is upgraded.

Upgrades go one release at a time (`VERSIONING.md`). Run every command below as the same user, with the same private configuration file, as the install and `cron.php`. `scripts/upgrade.php` takes the same optional configuration path as `scripts/install.php` (default `dbconnect.php` in the repository root) and rejects HTTP with 404.

### 1. Back up

Put the new version's files in place first: the game then shows "Upgrade Needed" and no one can play while you work. Take a fresh backup of the whole database, for example:

```sh
mysqldump --single-transaction --default-character-set=utf8mb4 -h HOST -u USER -p DATABASE > resurrection-before-upgrade.sql
```

Keep the backup private: it holds every account. Check that the file is complete (it ends with a `-- Dump completed` line). A backup is required because MySQL and MariaDB commit a table change the moment it runs: if an upgrade stops partway, the changes it made cannot be rolled back.

To restore it, recreate the database empty and load the file into it, then put the previous version's files back:

```sh
mysql -h HOST -u USER -p -e 'DROP DATABASE DATABASE; CREATE DATABASE DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
mysql --default-character-set=utf8mb4 -h HOST -u USER -p DATABASE < resurrection-before-upgrade.sql
```

Restoring replaces everything in that database. Check the name twice before dropping it.

### 2. Dry run

```sh
php scripts/upgrade.php --dry-run
```

This lists what is pending, each upgrade step with its description and each bundled module upgrade with its versions, and changes nothing. On a current database it says so, also without changing anything.

### 3. Upgrade

```sh
php scripts/upgrade.php --backup-confirmed
```

Without `--backup-confirmed` the command refuses and says why. It takes the install's and the maintenance's database locks, so it never runs alongside an install or `cron.php`; if one is running, it refuses and changes nothing. It applies the pending steps in number order, recording each in `upgrade_steps` only after it has fully succeeded, then upgrades the bundled modules through the module lifecycle (each module's install function runs again, as 1.1.2 did when a module changed), and reports each one.

If a step fails, the command stops there with exit code 1. It names the failed step and the last good step; the failed step is not recorded and nothing after it runs. Fix the cause and run the command again: recorded steps are skipped, and every step checks for its own changes before it makes them. If the cause cannot be fixed, restore the backup.

### 4. Verify

```sh
php scripts/upgrade.php --dry-run
```

It should now say the database is current, with every step applied. Open the game: the "Upgrade Needed" page is gone, and the footer shows the new version. Run `php cron.php` once to confirm maintenance runs again.

## Validation

CI installs into a truly empty resurrection_test database using a non-root database account, executes the actual CLI install command, and tests populated, 1.1.2 and repeated-install refusal, strict schema, seeds, administrator and player credentials, all 24 bundled module install callbacks and Dag/Drinks DDL. Its default install writes the configuration file from the environment and ends with 39 tables (the 38 game tables and the `upgrade_steps` ledger), 24 installed modules and exactly the 23 recommended modules active; it then clears that disposable database and installs again with `--modules=none` (24 installed, zero active), the state the later tests start from. An HTTP test plays the recommended module set as a brand-new player through account creation, first New Day onboarding, the village and the forest. Activation, lifecycle and gameplay of the 24 bundled modules are certified in `BUNDLED-MODULE-CERTIFICATION.md`. The upgrade tests (`tests/unit/UpgradeTest.php`) run the upgrade command's steps, refusals and lock on that installed database and leave it exactly as they found it; an equivalence test applies temporary test-only steps and compares the result with the same changes made directly. Before the PHP tests, the release upgrade check (`scripts/release-upgrade-check.py`) installs the latest release tag, upgrades it to the commit under test and compares it with a fresh install; with no release tag yet it reports "no release yet" and passes, and a second run treats the commit under test as the release so the install, upgrade and comparison run on every commit. Python smoke tests use a standard PHP server bound only to 127.0.0.1, then run application HTTP requests and CLI maintenance. Tests refuse a differently named database.

Exact passing runs and remaining limitations are in the Phase 2 and Phase 3 records (`MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`, `PHASE3-MERGE-GATE-20261023.md`) and in `SECURITY-STATUS.md` and `COMPATIBILITY-STATUS.md`. A successful install does not approve public hosting.
