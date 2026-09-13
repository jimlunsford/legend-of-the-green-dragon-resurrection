# Modern engineering validation

Run Composer 2 install, then composer check, vendor/bin/phpstan analyse -c phpstan-new.neon, and python3 -m unittest discover -s tests/tooling -v. Composer lock pins all 26 development dependencies. No Composer plugins are allowed. PHP lint runs without host php.ini so parse checks do not depend on host extensions; PHPUnit uses E_ALL and fails on warnings, notices and deprecations.

The legacy PHPStan level is 0 across shipped root PHP, lib and modules, with vendor/tests/lab excluded. The initial measured baseline contained 18 findings. Removing the obsolete mysql installer calls closed 10, leaving 8. The baseline was reduced by deleting only resolved entries; new findings must fail CI. This low starting level does not establish type or security correctness. New src infrastructure is also checked at level 6 without a baseline. Tests are executed by PHPUnit rather than included in this legacy static-analysis scope.

## Cursor migration

The foundation had 151 each calls in 61 files. Categories included full traversal after reset, partially consumed form key/value descriptors, skip-one translation arguments, nested/mutating buff and navigation arrays, cached database readers and one temporary query result. All were moved to resurrection_array_next, an explicit by-reference cursor operation implemented with key/current/next. It never resets implicitly. Existing reset and mutation points are retained. Invalid non-array inputs throw rather than silently becoming empty collections. Error-suppressing @each calls were removed.

The one temporary result was materialized first. Retired database drivers were subsequently removed; their exact original bytes remain in historical-source-1.1.2. Configuration/SQL and other route-specific type failures remain possible until integration reaches those paths.

Tests exercise false/null/zero values, exhaustion, caller pointer advancement, reset, partial traversal, pair consumption, mutations, nested loops and invalid input. The PDO result reader is tested with the same cached array contract. These establish primitive semantics; they do not certify all module hooks or all gameplay.

## Additional parse and compatibility work

Three curly-offset sites were converted. The racehuman nested ternary was parenthesized to preserve historical left associativity, including its possibly unintended display wording; no balance change was made. Deprecated stats interpolation was corrected. Ten reversed join calls in module metadata, footer replacements, license diagnostics, account cleanup and petition IP handling were reordered after checking their array producers. Full route execution, malformed hook results and remaining coercion failures still need work.

## Driver boundary

lib/dbwrapper.php selects PDO only. Direct removed mysql installer calls use the procedural wrappers. Prepared parameters are the optional third db_query argument. Existing string-built queries have not all been parameterized; C01 and SQL trust-boundary work remain blockers. Buffered result arrays retain count and cursor behavior; LINK remains a boolean compatibility marker. Persistent connections are intentionally not reused. Current host configuration accepts a hostname or IPv4 address with optional port; Unix socket and IPv6 configuration need a deliberate extension if required. Prefixes and table identifiers accept ASCII letters, digits and underscores only.

Database integration uses a non-root disposable account. It tests connection charset and SQL strictness, InnoDB DDL, quoted/backslash/emoji values, insert IDs, affected rows, count/cursor exhaustion, missing tables, statement batching rejection, NOT NULL enforcement, transaction rollback, and generic error messages. It does not install the game schema.

## Endpoint validation

Python tests start a PHP HTTP server bound only to 127.0.0.1. They make GET and POST requests to five disabled endpoints and repeat denied web-cron requests, asserting exact statuses and bodies. A standard CLI build with cli-server support is required. The disposable local PocketMine PHP build contains pmmpthread, which prevents cli-server startup; this local limitation must be reported, not treated as a pass. CI uses standard PHP builds and must run these tests.
