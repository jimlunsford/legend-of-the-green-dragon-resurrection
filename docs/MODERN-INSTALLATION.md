# Modern local installation and operation

Public hosting is not approved yet. These instructions are for a local, disposable development installation. Resurrection installs fresh only: a 1.1.2 database is not migrated or upgraded.

## Runtime and database

Use a standard PHP 8.4 or 8.5 build with PDO MySQL, mbstring, JSON, filter, and native sessions. Install the locked Composer dependencies. The CI pairs are PHP 8.4 with MariaDB 11.4 and PHP 8.5 with MySQL 8.4. A sessionless PHP build cannot run the application.

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

The command runs the supported installation service, creates the historical core schema and chronological fresh seed content, installs the fixed 24 shipped modules and their support data, activates the recommended modules, creates a modern administrator, and records completion only after all of that succeeds. Output contains counts and state, never credentials.

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

## Validation

CI installs into a truly empty resurrection_test database using a non-root database account, executes the actual CLI install command, and tests populated, 1.1.2 and repeated-install refusal, strict schema, seeds, administrator and player credentials, all 24 bundled module install callbacks and Dag/Drinks DDL. Its default install writes the configuration file from the environment and ends with 38 tables, 24 installed modules and exactly the 23 recommended modules active; it then clears that disposable database and installs again with `--modules=none` (24 installed, zero active), the state the later tests start from. An HTTP test plays the recommended module set as a brand-new player through account creation, first New Day onboarding, the village and the forest. Activation, lifecycle and gameplay of the 24 bundled modules are certified in `BUNDLED-MODULE-CERTIFICATION.md`. Python smoke tests use a standard PHP server bound only to 127.0.0.1, then run application HTTP requests and CLI maintenance. Tests refuse a differently named database.

Exact passing runs and remaining limitations are in the Phase 2 and Phase 3 records (`MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`, `PHASE3-MERGE-GATE-20261023.md`) and in `SECURITY-STATUS.md` and `COMPATIBILITY-STATUS.md`. A successful install does not approve public hosting.
