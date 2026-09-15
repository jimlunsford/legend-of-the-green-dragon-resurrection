# Modern core phase 2 checkpoint, 2026-09-13

## Outcome and repository

Resurrection now has a verified modern base application: strict fresh installation, modern administrator and player credentials, real HTTP authentication/session rotation, first-login navigation to the historical village, commentary posting/moderation and logout. The broader modern-core merge and public-hosting gates are not complete. PR #1 remains **OPEN, DRAFT, NOT MERGED**.

- Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection
- Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.
- Branch: `modernization/core-modernization`; existing PR: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/pull/1
- Starting phase HEAD: `e4c9421e8d37c63c451f380d470321e1015e2350`, verified against the published branch before editing.
- Ending validated implementation HEAD: `540d6d7937cd311f24bb3542da8b3469906abbda`.
- A documentation-only checkpoint commit follows that implementation HEAD. Its exact final branch SHA and workflows are recorded in PR #1 and the final session report, avoiding self-referential future commit/run identifiers in this file.
- The previous eight modernization commits are preserved. Additional phase commits are listed below; none was squashed or reset. No parallel modernization branch or PR #2 was created.

## C01 and request semantics

Previously the database bootstrap loaded lib/errorhandling.php, called removed get_magic_quotes_gpc(), recursively added slashes to incoming state and excluded notice/deprecation reporting. Request helpers, register-global compatibility, SQL construction and output stripslashes relied on that convention.

The replacement contract is raw HTTP application input until explicit type validation, SQL binding or output-context escaping. There is no fake function, magic-quotes toggle, global SQL escaping or global HTML escaping. The server-global compatibility bridge uses a fixed metadata allow-list; arbitrary request keys are not exported.

Key files: lib/errorhandling.php, lib/errorhandler.php, lib/http.php, lib/php_generic_environment.php, common.php, src/Http/Input.php, lib/accounts.php, lib/saveuser.php, lib/checkban.php, lib/commentary.php, lib/commentary_security.php, login.php, create.php, home.php, prefs.php, configuration.php, lib/user/*, lib/modules.php and runmodule.php. The audit of safeescape, stripslashes_deep and register_global support callers is preserved in [REQUEST-SEMANTICS.md](REQUEST-SEMANTICS.md).

Input tests cover missing/null/default values, arrays where scalars are required, strict integer/boolean/enum handling and literal quotes/backslashes/HTML. Database and HTTP tests prove raw passwords and commentary survive correctly. C01 is **FIXED for the modern-core boundary**; C08 is **FIXED for bootstrap/install/authentication**; C09 is **MITIGATED**, with untested route-specific assumptions explicitly remaining. Remaining mail/petition/clan/gameplay SQL and escaping callers are not certified safe merely because bootstrap succeeds.

## Installation and database

| Target | Fresh installation | Authentication smoke |
|---|---|---|
| PHP 8.4.25 + MariaDB 11.4.13-MariaDB-ubu2404 | PASS, actual CLI install into empty database | PASS |
| PHP 8.5.10 + MySQL 8.4.11 | PASS, actual CLI install into empty database | PASS |

The supported entrypoint is `php scripts/install.php /private/path/dbconnect.php`. Administrator credentials are supplied through process environment, never command arguments or generated diagnostic output. See [MODERN-INSTALLATION.md](MODERN-INSTALLATION.md).

Each target creates **38 tables**: 36 historical core tables plus Dag bounty and Drinks support tables. Every table is InnoDB with utf8mb4_unicode_ci; the connection is utf8mb4. All **24 shipped modules** are installed with their real metadata, default settings, hooks and install callbacks, and **zero modules are active**. This deliberately leaves activation and gameplay certification to the next phase. No archive-only module is imported.

Verified seed counts: **288 creatures, 195 weapons, 195 armor entries, 14 masters, 32 titles, 3 mounts and 3 drinks**. The original chronological fresh seed path executes, including its old-creature-experience cleanup. The administrator is created after schema/module setup; completion is recorded with administrator creation in a transaction.

Strict modes observed:

- MariaDB: STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_AUTO_CREATE_USER, NO_ENGINE_SUBSTITUTION.
- MySQL: ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION.

Application connections retain server modes and add strict/zero-date requirements; no global SQL mode was weakened. Index and column identifiers are quoted, including MySQL's reserved `function` index. Missing numeric/string/text defaults are explicit. Old zero-date sentinels become NULL where they mean not-yet/permanent; news primary-key dates require actual dates. Account last-hit logic, permanent bans and Dag bounty dates were adjusted together with their schema. Dag/Drinks `TYPE=` syntax is replaced with `ENGINE=InnoDB`; their actual DDL and repeated install callbacks pass without deleting existing bounty data. D01 is **FIXED for the bundled scope**.

The installer takes a database advisory lock and inspects actual table state. Empty is allowed; unrelated populated data is refused and preserved; recognizable historical accounts/settings/version data is classified upgrade-required and refused without modification; a completed installation is locked and repeat attempts preserve accounts. A partial failed install is never automatically cleaned or truncated. The web installer returns **403 before bootstrap**, and the CLI installer rejects HTTP with **404**. Existing-database upgrades are intentionally unavailable pending a separately verified migration.

No unresolved strict-schema failure remains in the tested fresh path. Unexercised gameplay SQL, historical database migration, encoding conversion and full active-module DDL/hook behavior remain separate work.

## Authentication

| Boundary | Result and evidence |
|---|---|
| Initial administrator | PASS, created by actual CLI installation with elevated administrator flags and modern hash |
| Normal account creation | PASS via service and HTTP; unique case-insensitive login, validation, optional email, initial stats/location and defaults |
| Password API | password_hash(PASSWORD_DEFAULT) / password_verify; observed bcrypt cost 12; varchar(255); 12–72 byte policy prevents current bcrypt truncation |
| Credentials | Correct password accepted; wrong/missing/locked/malformed inputs rejected; stored hash and legacy MD5 replay rejected |
| Login | PASS via real HTTP with raw quote/backslash password; no client-side MD5 authority |
| Session rotation | PASS on login and privilege refresh; strict attacker-chosen ID rejection, HttpOnly and SameSite=Lax observed |
| Authenticated rendering | PASS following actual issued New Day/onboarding links into historical village under E_ALL |
| Revocation | Database generation advances on login, password change and logout; old generations fail subsequent authentication |
| Logout | POST/CSRF destroys native session and expires cookie; subsequent protected request and old-session replay rejected |
| Diagnostics | Actual faillog records contain fixed invalid_credentials only, with empty legacy ID; tests exclude password, hash, token and session ID |
| Recovery | DISABLED, HTTP 410; insecure password-derived reset/validation is unavailable. Modern random-token/email recovery deferred |

Secure cookies are selected when the trusted web-server HTTPS value is on. Loopback HTTP tests verify HttpOnly/Lax and native lifecycle; they do not claim a TLS-browser cookie test. Production proxy/session-storage configuration is documented but not deployed. Password changes require the current password and rotate/revoke authentication. Modified administrative account changes use database-authoritative old values, bound updates and POST/CSRF; full editor UI certification remains outstanding.

## Core security

| ID | Status | Evidence |
|---|---|---|
| S02 | FIXED for commentary | Anonymous/player denial; moderator permission; strict positive IDs; prepared transactional delete/audit/restore; POST and valid CSRF; missing/invalid IDs safe. Actual HTTP player posting, output sanitization and moderator deletion pass |
| S04 | FIXED for dispatch/lifecycle | Active allowed; inactive/uninstalled/invalid/dependency/version failure denied; forged force/admin parameters cannot execute. Manager lifecycle requires POST/CSRF. Failed reinstall preflight preserves metadata, successful reinstall remains inactive |
| S05 | FIXED for fresh credentials | Modern admin/player hashes; correct/wrong/hash-replay/malformed/locked cases; recovery safely disabled |
| S06 | FIXED for tested authentication/installer diagnostics | No raw credential/session serialization; generic errors and argument-free diagnostics; actual records inspected |
| S07 | FIXED for core session lifecycle | Native strict sessions, ID/CSRF rotation, privilege refresh, generation revocation and destructive logout verified |
| S08 | MITIGATED, operational foundation | Cryptographically random session-bound tokens and reusable field/validation/failure helpers; critical login/account/config/module/moderation POST adoption; other legacy mutations remain |
| S13 | MITIGATED, supported CLI path | HTTP rejected; CLI reaches existing maintenance, lock serializes execution, completion marker suppresses duplicate game-day run on both databases |
| S03/S09/S10 | DISABLED, retained | LoGDnet, raw PHP/SQL console and payment endpoints deny GET/POST before bootstrap; remote registration and payment forms remain removed |
| Source viewer | DISABLED, retained | Direct requests return 410; repository is the source distribution channel |

The new scheduler is not a promise of crash-safe rollback for arbitrary active module hooks. No active-module maintenance certification or production scheduling deployment is claimed.

## Tests and CI

Both final implementation jobs completed successfully:

| Check | Final implementation result |
|---|---|
| PHPUnit | 39 tests, 299 assertions, zero skips on each matrix target |
| Python tooling/security/HTTP | 12 tests pass on each matrix target, including all nine previous tests |
| PHP lint | 277 files, zero failures |
| Legacy PHPStan | Level 0 passes; baseline reduced from 8 to 7 by removing only resolved C01; zero new errors |
| New infrastructure PHPStan | Level 6, zero errors, no baseline; includes src and new procedural account/session/moderation/installer services |
| Composer | Locked installation, strict validation and dependency audit pass; no known advisories reported |
| Modern core | [34774766860](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34774766860): SUCCESS; jobs 103770749858 (PHP 8.5/MySQL), 103770750086 (PHP 8.4/MariaDB) |
| Baseline integrity | [34774766883](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34774766883): SUCCESS |

The full engineering jobs execute real empty-database installation and loopback HTTP smoke, not substitutes for application execution. The baseline-only job lacks a live installed database and skips that database-backed HTTP class; both engineering jobs execute it. The local PocketMine PHP 8.4.16 build lacks sessions/usable cli-server; local unit results with database skips were never counted as complete application evidence.

Earlier verified milestones are retained: phase-1 final Modern core **34769899866** and Baseline integrity **34769899886** passed at the starting HEAD (16 tests/73 assertions, nine Python tests, 264 PHP files). Phase-2 **34771323035** first passed real CLI core install on both databases. **34773513630** passed full base HTTP authentication/commentary/logout. **34773769740** passed installation of all 24 bundled modules. **34774193681** passed upgrade refusal and expanded level-6 services (39/294, 11 Python).

Intermediate failed runs exposed genuine missing CLI globals, MySQL reserved identifiers, empty query/coercion assumptions, PHP 8 callback argument behavior, early language setup and log/schema mismatches. Fixes are separate commits, with no error suppression or baseline inflation. New fixture failures in **34774350000** (two by-reference notices) and **34774465354** (leading whitespace assertion) were corrected without weakening warning checks or authorization cases. Run **34774546323** then exposed the fixture attempting to insert a default already persisted by getsetting; the fixture now updates that value. **34774664268** passed all 12 Python tests and 39/298 PHPUnit assertions. The final implementation run below additionally verifies immediate module lifecycle cache invalidation and malformed dependency denial, superseding those intermediate failures.

## Historical integrity and boundaries

Every final workflow verifies the exact annotated tag object, target commit, root tree, manifest bytes and complete reachable ancestry:

- Tag: historical-source-1.1.2
- Annotated object: `51cab4fbe58a234651a3177a56289b18bc152b4d`
- Historical source: `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`
- Root tree: `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`
- **417 historical files verified; all 11 preservation commits reachable.**

Original jimlunsford/lotgd and jimlunsford/lotgd-modules modifications: **NONE**. VPS vps1.phoenix233.com access/changes: **NONE**. Public game runtime/hostname: **NONE**. Deployment/release/tag/package creation: **NONE**. Tests run in disposable CI containers and bind their application server to loopback only. No gameplay rebalance, theme redesign, framework rewrite or archive import was performed.

## Remaining blockers and exact next phase

### Blocks broader modern-core merge

1. Certify activation and core hooks for the 24 installed bundled modules, beginning with races/specialties, Dag/Drinks and representative village/forest/day hooks. Fix PHP 8 warnings without gameplay rebalance. All are currently inactive.
2. Continue raw-input/bound-SQL and output review across remaining mail, petition, clan, gameplay and editor routes; complete their POST/CSRF/authorization mutations, including sensitive economy operations.
3. Remove or constrain remaining module/buff/combat eval expression execution and verify caller permissions and supported metadata semantics.
4. Extend critical administrative UI and active-hook scheduler failure/concurrency coverage. Keep PR #1 draft until documented broader acceptance gates pass.

### Blocks public hosting

All merge blockers plus modern account recovery/email verification, abuse/rate controls, trusted TLS/session/proxy configuration, internal/config/vendor/test-file denial, private credentials, operational isolation, backup/restore and independent security review. No hosting approval is implied by this checkpoint.

### Blocks first release

Supported active-module/core regression coverage, explicit upgrade/support policy, repeatable packaging, release/provenance/licensing review and release approval. No release artifact is produced here.

### Later cleanup

Historical production-data/serialized-encoding migration, archive-only module admission, performance/cache/concurrency refinements, and any future networking/payment replacement. PHP 5 characterization and manual combat/probability comparisons were not part of this phase.

## Public-hosting verdict

**NO.** The base modern application threshold is verified, but remaining route security, active-module/eval and operational gates prevent public hosting or merging the broader modernization PR.

## Phase commits

- `ed761708e006e1ef806574360b3e206df1364654` Add raw input and modern password and CSRF primitives
- `7b036ed03d0499d2bc406630c4aa4e54c3956b4b` Add guarded strict fresh installation and secure account service
- `dc79187807e7070d4a43a4b7ee6d8faf3ba65184` Quote schema indexes and support CLI seed defaults
- `899cf0b40c535b214deac6aa96ad7401c16e6ad5` Replace request escaping and integrate secure web account boundaries
- `8ae9ef34531a53dfa30700b8494d0d318ef7bfdf` Test commentary and module authorization and initialize anonymous preferences
- `2d8a37f6b6958348ac5fc91aa0427883ffd02c8c` Hydrate authenticated game state and complete module test context
- `a6957a468a892d11ae905ada03e2581b43ac31ca` Secure moderation batches, account preferences, and module management forms
- `12f86eb9ff6f4186a138af46d57195343a435f59` Bind administrative account updates and protect configuration mutations
- `f2dc86831cd8342c74b261fcbe1a5f1a64bfdd86` Enforce strict connections and add locked CLI maintenance coverage
- `1b2baffaa8b002f5164556cd1d8839483555e02a` Verify HTTP password rejection, session invalidation, and first-login navigation
- `26693518075e0daf9fb985a8385e9c7cea5cad67` Invalidate prior authentication generations and fix strict logging fields
- `d5f1d59de5c9d2ece7b6e7f9323fc5631107162f` Bind account searches and follow issued onboarding links in HTTP smoke tests
- `9d30539bbb79fad49bf99a52ef3a985e6655ae03` Complete event bootstrap and document modern local installation
- `e263b1ec4896fa861e8cd6574f53ed5d06bd3f8e` Harden module preference storage and verify moderator session rotation
- `ec92f3ccaef6627c0f233551b9556f0904a2a75f` Install metadata and support tables for the 24 bundled modules
- `55b4c14e6ff3088d53a46451a1b51fca0da8c281` Verify upgrade refusal and apply level 6 analysis to new core services
- `277140a003a9f6093cdadb67f04d6018daed737d` Preserve module metadata on failed preflight and test HTTP dispatcher enforcement
- `80f099a1cc40f1009ef64ae782bd9bf16fed23bc` Pin the annotated historical tag object in every integrity verification
- `ea3e5de00626f3407f9175e03ea1840bca552446` Read module fixture rows without temporary by-reference arguments
- `8d4f6099dbb4b2afb4005164835ea285733f4bdb` Allow historical framing whitespace in the dispatcher execution fixture
- `c20c54d03ed909a9e52c84dfc36e77cf8296d6dd` Update the dispatcher fixture setting after its persisted default
- `540d6d7937cd311f24bb3542da8b3469906abbda` Bind module state queries and invalidate lifecycle state immediately

A final documentation-only commit records this checkpoint, ledgers and installation/validation guidance.
