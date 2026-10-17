# Modern core engineering checkpoint, 2026-09-13

**PARTIAL ENGINEERING MILESTONE. Full modern-core acceptance is incomplete. NOT APPROVED FOR PUBLIC GAME HOSTING.**

## Repository and preservation

| Item | Evidence |
|---|---|
| Public repository | https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection |
| Starting foundation main | `999cec6f9c655a840320d982d673bb863c68c2b2` |
| Final main for this checkpoint | `999cec6f9c655a840320d982d673bb863c68c2b2`, unchanged |
| Branch | `modernization/core-modernization` |
| Pull request | https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/pull/1 |
| Merge status | Open draft, not merged; no merge commit |
| Application code checkpoint | `471056d6a8515fee60b51319173a5f1f87f8545c`; subsequent checkpoint/ledger changes are documentation only |
| Historical tag | `historical-source-1.1.2` |
| Annotated tag object | `51cab4fbe58a234651a3177a56289b18bc152b4d` |
| Historical target | `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2` |
| Historical root tree | `4013a0ccc5227e87cd7a22de00b7c322d7aa237c` |
| Integrity | All 417 historical files and 11 original commits verify; inherited LICENSE.txt unchanged |
| Historical core repository HEAD | `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, unchanged |
| Historical module archive HEAD | `ef92e2df4e27ff29eb596b2b27ce3c9ab2c15187`, unchanged |

Existing published main was cloned and verified before changes. Terminal Git transport worked for reads but had no push credential; connected Git blob/tree/commit/ref operations published the development commits with the existing parent history. No root commit or historical tag was recreated. No writes were made to either preservation repository.

## Runtime and database

Targets: PHP 8.5 and 8.4, as researched from authoritative current PHP documentation. Composer 2.10.3 was used locally; dependencies are locked (PHPUnit 12.5.35, PHPStan 2.2.14, 26 total development packages). Required runtime facilities include PDO/pdo_mysql, mbstring, JSON, sessions and standard PHP functions. Development requires XML/DOM/XMLWriter, tokenizer, Python 3 and Git. See SUPPORTED-PLATFORMS.md for support policy and production/development distinctions.

PDO is now the sole selected transport behind db_*. Native prepares, an optional bound-parameter argument, utf8mb4 connection charset, validated host/identifiers, nonpersistent connections, buffered row/cursor semantics and generic driver errors are implemented. Direct removed mysql calls in the installer were migrated; the three obsolete drivers were removed from the active branch and remain in the immutable tag.

CI uses MariaDB 11.4 and MySQL 8.4 with a non-root account and InnoDB/utf8mb4 test tables. It verifies STRICT_TRANS_TABLES without changing server or session SQL modes. This is driver integration, not game-schema or fresh-install compatibility. Historical schema zero dates, invalid defaults, table locks, engine assumptions and serialization/encoding need their own integration work.

## Compatibility findings

| Finding | Status | Evidence / residual work |
|---|---|---|
| C01 | DEFERRED, BLOCKER | Actual local bootstrap exits 255 at lib/errorhandling.php:26 because get_magic_quotes_gpc is absent. No shim or global error suppression added. Raw-input/SQL/output semantics must be migrated together |
| C02 | FIXED, transport scope | Sole PDO transport and migrated installer API calls; live driver tests. No claim that all legacy SQL callers are safe or the schema installs |
| C03 | FIXED | Three curly-offset locations corrected; all 264 shipped PHP files parse in CI |
| C04 | MITIGATED | 151 historical each call sites moved to explicit cursor semantics; partial/reset/nested/mutation/exhaustion and cached-row tests. PHP 8.5 exhaustion regression corrected. Full callers remain unverified |
| C05 | MITIGATED | Ten reversed join calls corrected after inspecting array producers; affected full routes still need execution |
| C06 | NOT APPLICABLE | Audit-specific removed regex APIs concern unimported archive scope; no active match identified in bundled code |
| C07 | NOT APPLICABLE | Audit-specific bare key concerns unimported thieves module; broader core undefined values remain |
| C08 | DEFERRED | Old request/global behavior blocks bootstrap |
| C09 | DEFERRED | Remaining value/coercion issues; four by-reference temporary query results corrected |
| C10 | MITIGATED | Two racehuman ternaries explicitly preserve historical association; stats interpolation corrected; route-level errors remain |
| C11 | FIXED, generation scope | Actual fresh and old-upgrade stage execution produces valid round-tripping config; failed-write path does not display secrets |
| D01 | DEFERRED | Bundled module DDL untested; Drinks still contains TYPE=MyISAM |

## Security findings

| Finding | Status | Evidence / residual risk |
|---|---|---|
| S02 | DEFERRED, BLOCKER | Commentary authorization, SQL, mutation method and CSRF still need repair and regression cases |
| S03 | DISABLED | LoGDnet and image proxy are unconditional 410 stubs; footer registration scripts removed; HTTP GET/POST tests |
| S04 | DEFERRED, BLOCKER | Request-controlled module force remains; activation/dependency/management authorization tests required |
| S05 | DEFERRED, BLOCKER | Modern password hashing, replay rejection and recovery not implemented |
| S06 | DEFERRED, BLOCKER | Stage 6 credential output and driver SQL/parameter diagnostics removed, but auth and other diagnostics remain unaudited |
| S07 | DEFERRED, BLOCKER | Session rotation, fixation resistance, cookies, inactivity policy and safe logout not implemented |
| S08 | DEFERRED, BLOCKER | No general CSRF or mutation-method implementation yet |
| S09 | DISABLED, console scope | Raw web SQL/PHP console replaced with 410; other eval in module expressions/buffs/battle remains |
| S10 | DISABLED | Payment callback and merchant form generation removed; no real payment service connected |
| S13 | MITIGATED | Repeated web cron requests rejected before bootstrap; CLI entry directory fixed. Actual daily maintenance, concurrency and idempotency unverified |

Source viewer is also disabled before configuration/file access. Archive S01/S11/S12 modules remain unimported. See SECURITY-STATUS.md for the full ledger. No full inherited security finding is claimed globally FIXED by driver or syntax work alone.

## Installer, authentication and sessions

Complete clean installation: **NOT PASSED**. Schema/seed creation, administrator creation, modern hashes, populated-database safety and post-install lockout remain incomplete. Stage 6 alone is tested through fresh, pre-1.1 upgrade and failed-write paths, including quotes/backslashes and no secret output. Its allow-listed serialization replaces raw interpolation and line eval. Configuration is still an ignored local PHP file; private placement, atomic writes and permission policy remain unresolved.

Authentication modernization: not implemented. Session modernization: not implemented. CSRF: not implemented. Install/create-account/login/logged-in-render/logout smoke: not passed. The application does not currently bootstrap on supported PHP because C01 remains.

## Bundled modules and behavior

24 bundled entrypoints remain with their support files. All shipped PHP is syntax-checked. Module install DDL, hook registration, dependencies, activation enforcement, settings and user/object preferences are not integration-certified. No archive-only module was imported. No intentional game-balance changes, visual redesign, framework rewrite, SPA or frontend build system was introduced. The racehuman display expression retains historical left associativity rather than silently changing wording behavior.

## Tests, static analysis and CI

The code checkpoint passed both matrix jobs. The locked suite contains 16 PHPUnit cases; local PHP 8.4.16 runs 54 assertions with one explicitly skipped live-database test. The local PocketMine CLI cannot start cli-server due to pmmpthread, so local HTTP tests fail to start and are not counted as passed. Standard CI PHP must run them.

PHPStan: level 0 across shipped root/lib/modules, initial baseline 18 findings, reduced to 8 by deleting only resolved mysql entries. No new errors allowed. New src infrastructure is checked at level 6 with zero baseline. Runtime warnings, deprecations and notices fail PHPUnit. This is an initial analysis floor, not comprehensive type correctness.

Historical baseline tests verify exact objects and rejection cases. Python suite contains 9 tests when a standard PHP binary is available, including real loopback-only HTTP GET/POST endpoint rejection tests. Composer validation/install/audit, full PHP lint, PHPUnit, both PHPStan scopes, project hygiene and historical integrity are CI gates. No paid larger runners or public game server are used.

### Verified code-checkpoint results

| Environment | Result |
|---|---|
| PHP 8.4.25 + MariaDB 11.4.13-MariaDB-ubu2404 | PASS, driver integration, 16 PHPUnit tests / 73 assertions / zero skips; 9 Python tests including HTTP rejection |
| PHP 8.5.10 + MySQL 8.4.11 | PASS, driver integration, 16 PHPUnit tests / 73 assertions / zero skips; 9 Python tests including HTTP rejection |
| Both PHP jobs | 264 PHP files linted, zero failures; Composer 2.10.3 validation/install/audit PASS; no known dependency advisories reported; PHPStan level 0 baseline 8 / new errors 0 and src level 6 errors 0 |
| [Modern core run 34769676640](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34769676640) | PASS at `471056d6a8515fee60b51319173a5f1f87f8545c`; jobs 103756853108 and 103756853255 |
| [Baseline integrity run 34769676642](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34769676642) | PASS at the same code checkpoint |

These results certify only the tested engineering/driver/endpoint scope. They do not claim a successful game install or login. A subsequent documentation-only commit records this evidence; its workflow run, if any, is reported in the PR/session final response rather than embedding a self-referential future run ID here.

### Earlier CI outcomes, retained honestly

| Run | Commit | Result |
|---|---|---|
| Baseline integrity 34768192922, attempt 2 | `999cec6f9c655a840320d982d673bb863c68c2b2` | PASS, published foundation |
| Baseline integrity 34769418699 | `c400d5553c95129e992afa1f9bf0e185bc9e2700` | PASS |
| New workflow validation 34769416890 | `c400d5553c95129e992afa1f9bf0e185bc9e2700` | FAIL, job-level service-port context; corrected in c3d7412 |
| Baseline integrity 34769484604 | `c3d741225a5cdd415476a6e42938bcf51b88d1e2` | PASS |
| Modern core 34769484605 | `c3d741225a5cdd415476a6e42938bcf51b88d1e2` | MariaDB/PHP 8.4 PASS; PHP 8.5 failed on destructuring false. Corrected primitive semantics and added regression, without suppressing warnings |

## Technical debt and exact continuation

### Merge blockers

1. Continue on the existing modernization branch and PR #1. Verify current remote main/tag before changes and preserve both historical repositories.
2. Define raw request semantics and remove C01 in coordination with SQL/input/output boundaries. Start from lib/errorhandling.php, lib/http.php, common.php, lib/installer and unsafe httppost/httpget callers. Do not replace magic quotes with a fake shim or simply drop escaping from string-built queries.
3. Modernize the real schema and fresh installer under the existing MariaDB/MySQL CI services: utf8mb4, InnoDB, strict defaults/zero dates, guarded populated databases, private configuration and post-install lockout. Run empty-database installation and seed/admin tests, not just driver DDL.
4. Implement password_hash/password_verify, eliminate hash replay and secret diagnostics, modernize or disable recovery, rotate sessions and implement logout/cookie/timeout policy.
5. Repair S02 and S04, integrate CSRF and proper mutation methods, audit contextual output and remaining eval expressions, and test the requested authorization/negative cases.
6. Exercise bundled module hooks/settings/preferences/install/dependency behavior, then the complete fresh-install/account/login/render/logout smoke. Resolve remaining warnings and update findings with actual evidence.
7. Keep the PR draft until the requested modern-core acceptance is satisfied. Merge only when acceptance and required CI checks pass; do not weaken gates.

### Before public hosting

Resolve all security blockers; finish installer isolation and secret handling, module/eval and SQL/output review, scheduler concurrency, session/authentication/abuse controls, supported deployment configuration, TLS/headers, recovery backups and independent security review. This task did not establish a production deployment configuration.

### Before first release

Deeper core gameplay regression, supported combinations/upgrade policy, repeatable release packaging, documentation and licensing/provenance review of shipped components. No version/tag/release artifact is created by this task.

### Later modernization

Archive module admission, old-live-database migration and serialized encoding conversion, performance/concurrency improvements, product/interface decisions, and any replacement networking/payment design.

## Public-hosting verdict

**NO.** The current branch still has a demonstrated bootstrap failure, incomplete fresh install and unresolved authentication/session/CSRF/authorization risks. Main remains the original foundation. Green engineering and driver CI cannot approve either for public game hosting.

VPS modified: **NO**. Original historical repositories modified: **NO**. Public game runtime exposed: **NO**. Release or deployment created: **NO**.
