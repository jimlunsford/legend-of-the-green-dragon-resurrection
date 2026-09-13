# Modern local installation and operation

This branch is not approved for public hosting. These instructions are for a disposable development installation. No existing player database migration is implemented or certified.

## Runtime and database

Use a standard PHP 8.4 or 8.5 build with PDO MySQL, mbstring, JSON, filter, and native sessions. Install the locked Composer dependencies. The CI pairs are PHP 8.4 with MariaDB 11.4 and PHP 8.5 with MySQL 8.4. A sessionless PHP build cannot run the application.

Create a dedicated **empty** database and a dedicated account authorized only for that database. Use utf8mb4 at database creation. Application connections explicitly use utf8mb4 and retain server SQL modes while adding STRICT_TRANS_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO, and NO_ENGINE_SUBSTITUTION. Fresh tables use InnoDB and utf8mb4_unicode_ci. Do not weaken global SQL modes.

Create an owner-readable configuration file outside the served directory initially, using the PHP variable names below. Use the project's LegacyConfig generator when programmatically writing configuration; never interpolate credentials into PHP source.

- DB_HOST: hostname or IPv4 address, optionally followed by a TCP port.
- DB_USER, DB_PASS, DB_NAME: the dedicated development database credentials.
- DB_PREFIX: an optional ASCII table prefix; default empty.
- DB_USEDATACACHE: use 0 for initial validation.
- DB_DATACACHEPATH: empty when caching is disabled.

Supply RESURRECTION_ADMIN_LOGIN, RESURRECTION_ADMIN_PASSWORD and optionally RESURRECTION_ADMIN_EMAIL through the local process environment. Do not put passwords in command arguments, committed files or shell history. Passwords must contain 12 to 72 bytes; the maximum prevents silent bcrypt truncation under the current PASSWORD_DEFAULT implementation.

Run from the repository root:

```sh
php scripts/install.php /absolute/private/path/dbconnect.php
```

The command runs the supported installation service, creates the historical core schema and chronological fresh seed content, creates a modern administrator, and records completion only after successful administrator creation. Output contains counts and state, never credentials. For local application testing, place the approved configuration at dbconnect.php with restrictive permissions; it is ignored by Git. The CI creates this file exclusively and removes it after loopback tests.

The installer uses an advisory database lock. Any existing table causes refusal, including an unrelated populated database, a partial failed install, and a previous historical game. A completed Resurrection database is reported as installed. No automatic truncate, drop, retry cleanup or in-place upgrade is performed. Diagnose a failed disposable installation before explicitly recreating its disposable database. Never apply that procedure to valuable data.

The historical web installer returns 403 before bootstrap. Direct requests cannot install or upgrade, even if an installation marker is removed. scripts/install.php rejects HTTP with 404. A future upgrade must be a separately reviewed migration with backup and recovery evidence.

## Authentication and sessions

Fresh accounts use password_hash(PASSWORD_DEFAULT) and password_verify. Legacy hashes and submitted stored hashes cannot authenticate. The former client-side MD5, password-derived recovery and email-validation login flows are unavailable. Recovery returns 410; email verification must be modernized before enabling requirevalidemail. Normal creation fails closed when that unsupported requirement is enabled.

Login, logout, account creation, password changes and the modified administrative/moderation/module mutations require POST and a session-bound CSRF token. Existing navigation state continues to govern game navigation and is not treated as CSRF protection. The logout link displays a confirmation form; POST destroys the native session and expires its cookie.

Native sessions use strict ID acceptance, cookie-only transport, HttpOnly and SameSite=Lax. Secure is set when the web server reports HTTPS=on. An eventual production proxy must establish that trusted server value; client-supplied forwarding headers must not decide it. Keep display_errors off in production, error_reporting at E_ALL, zend.exception_ignore_args enabled, session files private, and session storage isolated from other applications. Production TLS, headers, authorization review and abuse controls remain separate hosting gates.

A successful login rotates the session ID and CSRF token. A database authentication generation invalidates older sessions after another login, logout or password change. Privileges are reloaded from the database on each authenticated request and a change rotates the session. Authentication diagnostics use a fixed event vocabulary with no password, hash, token, session ID or cookie serialization.

## Scheduler

Fresh installs set newdaycron=1. Global maintenance is run locally with:

```sh
php cron.php
```

The command must run with the same private configuration and filesystem ownership as the application. Schedule it often enough to cover the configured game-day interval. No scheduler or server deployment is performed by this phase.

A database advisory lock serializes maintenance. A completed game-day marker makes repeated invocations skip that day's maintenance. The marker is written after successful maintenance. Player New Day behavior and its turn/economy rules remain separate. Individual third-party hook side effects are not transactionally rolled back after a failure; full hook and failure-recovery certification remains required before production scheduling. HTTP GET and POST to cron.php return 404 before bootstrap.

## Validation

CI installs into a truly empty resurrection_test database using a non-root database account, executes the actual CLI install command, and tests populated/repeated-install refusal, strict schema, seeds, administrator and player credentials, and bundled Dag/Drinks DDL. Python smoke tests use a standard PHP server bound only to 127.0.0.1, then run application HTTP requests and CLI maintenance. Tests refuse a differently named database.

See the phase-2 checkpoint and security/compatibility ledgers for exact passing runs and remaining limitations. Installation success alone does not certify bundled gameplay modules or public hosting.
