# Compatibility status

Updated 2026-09-13 for draft PR #1. The modern branch has engineering and driver coverage, not a completed modern game runtime. PHP 5 characterization is deferred during this phase. Main remains the published foundation until the requested acceptance gates pass.

| ID | Original problem / affected scope | Status | Remediation and evidence | Remaining limitation |
|---|---|---|---|---|
| C01 | Removed get_magic_quotes_gpc and implicit request escaping, lib/errorhandling.php | DEFERRED, bootstrap blocker | Explicitly retained as one PHPStan baseline finding, not shimmed | Migrate SQL/input/output semantics together; removing escaping alone would expose legacy string-built queries |
| C02 | Removed mysql transport and direct installer calls | FIXED at transport boundary | Sole PDO db_* driver; old drivers removed from shipped branch; direct installer calls migrated; native parameter support and driver integration tests | Existing SQL callers are not all parameterized; schema/installer/application still unverified |
| C03 | Curly offsets in mounts, mail writer and installer helpers | FIXED | All three corrected; full shipped-PHP lint | Parsing is not route execution |
| C04 | 151 each calls in original branch | MITIGATED | Explicit cursor primitive, preserved reset/partial/mutating traversal, temporary result materialized, @each removed; cursor and cached-reader tests | Full form, buff, hook and gameplay caller integration remains; invalid non-arrays now fail explicitly |
| C05 | Reversed joins in modules, pageparts, expiry, petition and license diagnostics | MITIGATED | Ten calls corrected after inspecting array producers; lint/static analysis | Full affected routes and malformed/false hook-result behavior not yet exercised |
| C06 | Removed split and preg_replace /e in archive modules | NOT APPLICABLE to bundled scope | No active matching removed APIs identified in the shipped core; archive not imported | Reassess during module admission |
| C07 | Bare key in archive thieves module | NOT APPLICABLE to bundled scope | Archive-only module not imported | Broader core missing-key assumptions fall under C08-C10 |
| C08 | Globals/input semantics | DEFERRED | No comprehensive bootstrap compatibility claim | C01 and old request/global assumptions remain |
| C09 | Missing values and coercion | DEFERRED | Cached result API tested; four temporary by-reference query results materialized | Runtime paths and resource-limit parsing need tests/fixes |
| C10 | Strict PHP behavior / route-specific failures | MITIGATED | racehuman ternary explicitly preserves old associativity; stats interpolation corrected; full lint | Warnings/TypeErrors across application routes remain unverified |
| C11 | Extra generated quote in pre-1.1 upgrade configuration | FIXED | Shared allow-listed var_export generator; actual fresh, old upgrade and failed-write stage fixtures; no line eval or credential display | Complete installer safety and configuration storage/atomicity remain separate blockers |
| D01 | Old bundled module engine syntax | DEFERRED | Drinks still contains TYPE=MyISAM; bundled DDL not executed | Modern schema/engine changes must be integration-tested |

## Database, encoding and modules

The driver uses utf8mb4 and native prepares. Driver integration uses InnoDB under STRICT_TRANS_TABLES and does not weaken SQL modes. This does not modernize the historical schema, zero dates, defaults, mail/HTML encoding, serialized state, game transactions or module installers. No historical player database is available or imported.

All 24 bundled module entrypoints remain. Syntax coverage includes support files; no hook/dependency/activation/settings/preference or module-DDL compatibility claim is made. S04 remains a security blocker. No archive-only module was imported. No gameplay rebalance, template redesign or framework migration was performed.

See [platform policy](SUPPORTED-PLATFORMS.md), [validation details](ENGINEERING-VALIDATION.md), and [checkpoint](MODERN-CORE-CHECKPOINT-20260913.md).
