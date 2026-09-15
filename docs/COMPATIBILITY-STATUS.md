# Compatibility status

Updated 2026-09-13 for draft PR #1. Phase 2 proves bootstrap, strict fresh installation and authenticated core navigation on both supported CI targets. It does not certify every historical route or bundled gameplay hook. PHP 5 characterization is deferred during this phase. Main remains the published foundation until the requested acceptance gates pass.

| ID | Original problem / affected scope | Status | Remediation and evidence | Remaining limitation |
|---|---|---|---|---|
| C01 | Removed magic quotes and implicit request escaping | FIXED for modern-core boundary | No call, toggle or fake shim; raw GET/POST/cookies; typed Input helpers; SQL/output destinations migrated together. InputTest, FreshInstallTest and actual HTTP create/login/village/commentary tests | Remaining mail, petition, clan and gameplay SQL/stripslashes callers require route-specific security review; see REQUEST-SEMANTICS.md |
| C02 | Removed mysql transport and direct installer calls | FIXED at transport boundary | Sole PDO db_* driver; old drivers removed from shipped branch; direct installer calls migrated; native parameter support and driver integration tests | Remaining legacy SQL callers are not all parameterized; phase 2 verifies core schema/install/authentication |
| C03 | Curly offsets in mounts, mail writer and installer helpers | FIXED | All three corrected; full shipped-PHP lint | Parsing is not route execution |
| C04 | 151 each calls in original branch | MITIGATED | Explicit cursor primitive, preserved reset/partial/mutating traversal, temporary result materialized, @each removed; cursor and cached-reader tests | Full form, buff, hook and gameplay caller integration remains; invalid non-arrays now fail explicitly |
| C05 | Reversed joins in modules, pageparts, expiry, petition and license diagnostics | MITIGATED | Ten calls corrected after inspecting array producers; lint/static analysis | Full affected routes and malformed/false hook-result behavior not yet exercised |
| C06 | Removed split and preg_replace /e in archive modules | NOT APPLICABLE to bundled scope | No active matching removed APIs identified in the shipped core; archive not imported | Reassess during module admission |
| C07 | Bare key in archive thieves module | NOT APPLICABLE to bundled scope | Archive-only module not imported | Broader core missing-key assumptions fall under C08-C10 |
| C08 | Globals/input semantics | FIXED for bootstrap/install/authentication | Fixed server-metadata allow-list replaces register-global bridge; missing/null/default and scalar/array rejection tests; raw quotes, backslashes, Unicode and HTML preserved until destination | Historical scalar callers outside tested routes still need adoption |
| C09 | Missing values and coercion | MITIGATED, core paths pass | E_ALL HTTP tests exposed and fixed anonymous prefs, empty settings/query exhaustion, newest-player coercion, censor lists, module event server values and early translator setup. Dates use NULL semantics; strict INSERT defaults explicit | Untested gameplay/editor/mail paths, resource-limit parsing and malformed hook-return types remain |
| C10 | Strict PHP behavior / route-specific failures | MITIGATED | racehuman ternary explicitly preserves old associativity; stats interpolation corrected; full lint | Warnings/TypeErrors across application routes remain unverified |
| C11 | Extra generated quote in pre-1.1 upgrade configuration | FIXED | Shared allow-listed var_export generator; actual fresh, old upgrade and failed-write stage fixtures; no line eval or credential display | Stage-6 evidence retained; web installer now denied and guarded CLI fresh path verified. Existing-database upgrades and production configuration isolation remain separate work |
| D01 | Old bundled module engine syntax | FIXED for all bundled DDL | Dag and Drinks use ENGINE=InnoDB and utf8mb4; their actual install functions, seeds and repeated execution run under strict MariaDB/MySQL. All 24 shipped module install callbacks run in fresh install | Hook/gameplay certification and third-party module admission remain separate |

## Database, encoding and modules

The driver uses native PDO prepares and explicit utf8mb4. Fresh installation produces 36 core tables plus Dag bounty and Drinks tables: 38 InnoDB tables, all utf8mb4_unicode_ci. Connection SQL modes preserve server defaults and add strict/zero-date protections. News primary-key dates require real publication dates; absent account and ban dates use NULL. Missing numeric/string/text defaults are explicit instead of relying on permissive INSERT behavior. No historical production database is migrated.

The fixed 24 bundled entrypoints are installed with metadata, settings and hooks, and left inactive. Dag/Drinks support DDL and seeds are executed, including safe repeated callback checks. S04 state/dependency/force boundaries are exercised with actual injection and HTTP dispatch. Full activation, event and gameplay certification is still required. No archive-only module was imported and no balance, theme or framework changes were made.

## Additional runtime findings discovered in phase 2

| Finding | Resolution | Evidence |
|---|---|---|
| Reserved schema index name `function` rejected by MySQL | Validate and quote index/column identifiers | Actual empty MySQL installation |
| PHP 8 treats string metadata keys as named callback arguments | Remove navigation translation metadata and pass positional values | Historical home/village rendering |
| Optional SERVER_NAME/PHP_SELF and anonymous preference state absent | Explicit CLI/server defaults and initialized application state | CLI installer, scheduler and E_ALL HTTP smoke |
| Empty query results used as rows | Guard exhaustion in MOTD, newest-player and censor paths | Fresh installation followed by empty-content home/village |
| Old failure-log column assumptions and strict field lengths | Use actual faillog.post with fixed events and 20-character maintenance key limit | Negative-login diagnostics and repeated CLI maintenance |
| Language setup occurred after an early anonymous redirect | Initialize translation before forced-navigation rejection | Post-logout and old-session replay HTTP requests |

The earlier C02/C03/C04/C05/C10/C11 evidence remains valid. See [phase-1 checkpoint](MODERN-CORE-CHECKPOINT-20260913.md), [phase-2 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE2.md), [request contract](REQUEST-SEMANTICS.md), [installation](MODERN-INSTALLATION.md) and [validation](ENGINEERING-VALIDATION.md).
