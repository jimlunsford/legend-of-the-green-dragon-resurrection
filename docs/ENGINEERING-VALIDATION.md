# Modern engineering validation

Run Composer 2 install, then composer check, vendor/bin/phpstan analyse -c phpstan-new.neon, and python3 -m unittest discover -s tests/tooling -v. Composer lock pins all 26 development dependencies. No Composer plugins are allowed. PHP lint runs without host php.ini so parse checks do not depend on host extensions; PHPUnit uses E_ALL and fails on warnings, notices and deprecations.

The legacy PHPStan level is 0 across shipped root PHP, lib and modules, with vendor/tests/lab excluded. The initial measured baseline contained 18 findings. Removing the obsolete mysql installer calls closed 10, leaving 8 at phase 1. Phase 2 removes only the resolved C01 entry, leaving 7. The baseline was reduced by deleting only resolved entries; new findings must fail CI. This low starting level does not establish type or security correctness. New src infrastructure and the new procedural account, web-security, moderation, fresh-install, bundled-installer and CLI-install services are checked at level 6 without a baseline. Tests are executed by PHPUnit rather than included in this legacy static-analysis scope.

## Cursor migration

The foundation had 151 each calls in 61 files. Categories included full traversal after reset, partially consumed form key/value descriptors, skip-one translation arguments, nested/mutating buff and navigation arrays, cached database readers and one temporary query result. All were moved to resurrection_array_next, an explicit by-reference cursor operation implemented with key/current/next. It never resets implicitly. Exhaustion returns null so PHP 8.5 destructuring does not warn and clears the loop variables as before. db_fetch_assoc maps this back to its public false-at-exhaustion contract. This difference was found by the first PHP 8.5 CI run and is covered by a dedicated regression. See [PHP 8.5 destructuring changes](https://www.php.net/manual/en/migration85.incompatible.php). Existing reset and mutation points are retained. Invalid non-array inputs throw rather than silently becoming empty collections. Error-suppressing @each calls were removed.

The one temporary result was materialized first. Retired database drivers were subsequently removed; their exact original bytes remain in historical-source-1.1.2. Phase 2 exercises core configuration/SQL and install/authentication paths; untested routes can still contain type failures.

Tests exercise false/null/zero values, exhaustion, caller pointer advancement, reset, partial traversal, pair consumption, mutations, nested loops and invalid input. The PDO result reader is tested with the same cached array contract. These establish primitive semantics; they do not certify all module hooks or all gameplay.

## Additional parse and compatibility work

Three curly-offset sites were converted. The racehuman nested ternary was parenthesized to preserve historical left associativity, including its possibly unintended display wording; no balance change was made. Deprecated stats interpolation was corrected. Ten reversed join calls in module metadata, footer replacements, license diagnostics, account cleanup and petition IP handling were reordered after checking their array producers. Full route execution, malformed hook results and remaining coercion failures still need work.

## Driver boundary

lib/dbwrapper.php selects PDO only. Direct removed mysql installer calls use the procedural wrappers. Prepared parameters are the optional third db_query argument. The installation/authentication/commentary/configuration boundaries now use raw input and bound SQL. Other legacy string-built queries remain a security-review blocker; see REQUEST-SEMANTICS.md. Buffered result arrays retain count and cursor behavior; LINK remains a boolean compatibility marker. Persistent connections are intentionally not reused. Current host configuration accepts a hostname or IPv4 address with optional port; Unix socket and IPv6 configuration need a deliberate extension if required. Prefixes and table identifiers accept ASCII letters, digits and underscores only.

Database integration uses a non-root disposable account. It tests connection charset and SQL strictness, InnoDB DDL, quoted/backslash/emoji values, insert IDs, affected rows, count/cursor exhaustion, missing tables, statement batching rejection, NOT NULL enforcement, transaction rollback, and generic error messages. FreshInstallTest additionally runs the actual CLI installer against an empty database: all 38 tables, 24 bundled install callbacks, core seeds, modern admin/player hashes, strict defaults, unrelated/upgrade/repeat refusal and account boundaries.

## Endpoint validation

Python tests start a PHP HTTP server bound only to 127.0.0.1. They make GET and POST requests to five disabled endpoints and repeat denied web-cron requests, asserting exact statuses and bodies. A standard CLI build with cli-server support is required. The disposable local PocketMine PHP build contains pmmpthread, which prevents cli-server startup; this local limitation must be reported, not treated as a pass. CI uses standard PHP builds and must run these tests.

## Phase-2 application coverage

The previous regression floor was 16 PHPUnit tests/73 assertions, nine Python tests and 264 linted PHP files. The expanded suite retains those cases and adds raw input, modern passwords, CSRF, real strict installation, account state and authorization tests. See the phase-2 checkpoint for final exact counts and workflow IDs.

Python HTTP smoke uses native sessions and a real freshly installed database. It rejects an attacker-chosen session ID, creates a player, rejects bad/malformed/replayed-hash credentials without secret logging, authenticates with rotation, follows issued onboarding links to the historical village, posts quoted/backslash/HTML commentary, rejects player deletion, rotates after privilege refresh, accepts moderator deletion, destroys logout state, rejects old-session reuse, and denies recovery/installer access. A separate temporary synthetic module tests actual dispatcher execution and inactive/uninstalled/dependency refusal despite forged force flags. Fixture files and records are removed. CLI maintenance runs twice to verify completion and repeat suppression.

MariaDB/MySQL jobs execute all tests without skips. The standalone baseline job has no installed database and reports the explicitly unavailable database-backed HTTP class as skipped; it still runs integrity/rejection and disabled-endpoint tests. This is not substituted for either full engineering job.

The local PocketMine PHP 8.4.16 build lacks native sessions and cannot host HTTP due to pmmpthread. Local lint/static/unit checks are useful, but local skipped database/HTTP cases are never claimed as passes. Supported application evidence comes from standard PHP 8.4.25 and 8.5.10 in disposable GitHub Actions services.
