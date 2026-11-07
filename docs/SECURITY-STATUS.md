# Security status

**NOT CERTIFIED FOR PUBLIC GAME HOSTING** (no release cut yet; what is open today: the section "2026-11-07: open today" at the end)

Updated 2026-09-13 for draft PR #1 (merged on 2026-10-23; the rows below are kept as recorded then). This ledger describes the modernization branch, not unchanged foundation main. The inherited audit remains the reference. A disabled endpoint is not a security certification for the rest of the application.

| ID | Status | Remediation/evidence | Residual risk or next work |
|---|---|---|---|
| S01 | NOT APPLICABLE to shipped core | Archive advertising modules were not imported | Admission review still required |
| S02 | FIXED for commentary boundary | Authorized moderator/admin only; strict positive IDs, prepared queries, transactional audit/delete/restore, POST and CSRF. Anonymous/player/GET/bad-token/SQL-expression/missing-ID tests; actual player posting and moderator deletion via HTTP | Broader moderation UI and ban workflows are not fully browser-certified |
| S03 | DISABLED | logdnet.php and images/logdnet.php are unconditional 410 stubs; footer registration/remote script generation removed. HTTP regression tests cover GET/POST | No replacement network service; legacy settings are inert; other outbound fetch helpers require separate review |
| S04 | FIXED for dispatcher/lifecycle boundary; all 24 bundled modules certified | Request force ignored; installed, active and dependency/version checks enforced; ordinary force rejected. Manager lifecycle requires POST/CSRF. Reinstall preflight preserves metadata on failure and successful reinstall stays inactive. Phase 3 certified all 24 bundled modules: install, activate, deactivate, reactivate, reinstall, settings/preferences round trip, hooks, events and their gameplay mutations, all 24 active together (`BUNDLED-MODULE-CERTIFICATION.md`, 24 PASS); every module manager operation is POST + CSRF + a one-use intent and only bundled modules install (`MODULE-MANAGER-AUTHORITY-20261017.md`); the five eval sites are removed (`EXPRESSION-EVALUATION-AUDIT.md`). A fresh install activates the 23 recommended bundled modules through the lifecycle API, all or nothing (`PHASE4-TASK1B-FRESH-INSTALL-20261029.md`) | Installed code remains trusted PHP. Certification covers the 24 bundled modules only; any other module needs its own admission review. Not a public hosting approval |
| S05 | FIXED for fresh credentials; recovery DISABLED | password_hash(PASSWORD_DEFAULT), password_verify, varchar(255), no MD5 authority or hash replay. Admin/player creation, wrong/missing/locked/malformed/hash-as-password cases tested. Password changes verify current password and revoke prior generations | No historical credential migration. Password-derived recovery and legacy validation return 410; modern token/email workflow deferred |
| S06 | FIXED for authentication/installer diagnostics | Failed logins store a fixed event only; no passwords/hashes/tokens/cookies/session IDs. HTTP tests inspect actual faillog records. Generic exceptions and argument-free source diagnostics replace request/backtrace serialization. Previous stage-6 nondisclosure tests retained | Other gameplay/mail/admin logging and production log access still require review |
| S07 | FIXED for core lifecycle | Native strict cookie-only sessions, HttpOnly/Lax, Secure on trusted HTTPS. Login and privilege changes rotate ID and CSRF; database generation revokes older sessions on login/password/logout. Logout destroys server session and expires cookie. HTTP tests verify fixation rejection, rotation, authenticated render and old-session rejection | Secure HTTPS cookie branch documented, not TLS-browser tested; deployment/session storage isolation remains unapproved |
| S08 | MITIGATED; foundation operational | Random session-bound CSRF helpers; POST/CSRF for login/logout/create, preferences/password, modified user/configuration, module management and moderation mutations. Navigation is not treated as CSRF. Unit/integration/HTTP negative and positive tests | Remaining gameplay economy, mail, petition and other editor mutations still require route-by-route POST/CSRF/authorization coverage |
| S09 | DISABLED for raw web console | rawsql.php is an unconditional 410 stub with no application bootstrap; HTTP tests cover GET/POST | Other eval expressions remain in modules, buffs and extended battle; constrained evaluation is still required |
| S10 | DISABLED | payment.php is an unconditional 410 stub; footer merchant forms/notify URLs removed; HTTP tests cover GET/POST | No modern payment implementation or external payment account connected |
| S11 | NOT APPLICABLE to shipped core | Archive blog module not imported | Future admission review |
| S12 | NOT APPLICABLE to shipped core | Archive character restore module not imported | Future restore boundary review |
| S13 | MITIGATED; supported CLI path verified; bundled daily hooks and maintenance completion accepted | HTTP GET/POST return 404. Installed CLI maintenance acquires database lock, runs existing maintenance and records completed game day; repeated invocation returns already-complete. Both matrix HTTP/CLI suites pass. With all 24 bundled modules active, each daily hook (Cedrik's Potions, Crazy Audrey) commits its writes with a per-day receipt: a failing hook rolls back its own writes, leaves the game day unrecorded, and a retry skips hooks already done; failure, retry, duplicate and concurrency are tested (`MODERN-CORE-CHECKPOINT-20260913-PHASE3.md`, "Active-module maintenance"). Expiration deletes each account all or nothing and records `last_char_expire` only when none failed (`DELETION-EXPIRATION-AUTHORITY-20261017.md`); `OPTIMIZE TABLE` records `lastdboptimize` only when no table failed; both refuse to run without the maintenance lock (`COMPLETION-REPORTING-20261017.md`) | Not a universal exactly-once scheduler: the core cleanup is not one transaction (a failure leaves the game day unrecorded and the next run repeats its date-based cleanups), `OPTIMIZE TABLE` commits implicitly, and a non-bundled module's filesystem, network or DDL effects would not roll back. The project installs no scheduler; running one in production (supervision, monitoring) is a public hosting gate |
| Source viewer | DISABLED | source.php returns 410 before config or file access; repository remains the source distribution channel | Future web-server rules must also deny direct internal/config access |

## Fidelity and public hosting

Known security defects are not desired gameplay. Historical originals remain in the immutable tag. No public runtime, VPS deployment, DNS, backup, database-server or web-server changes were made.

Before public hosting: finish remaining SQL/output/authorization/CSRF routes, replace or constrain module/buff/combat eval paths, certify active bundled hooks and scheduler recovery, implement modern recovery/email delivery, and verify private configuration/internal-file denial, TLS/headers, abuse controls, deployment isolation and restore procedures. These are remaining gates even though the base installation/authentication milestone now passes. Independent security review remains required for public readiness.

PR #1 remained open and draft at that time (it merged on 2026-10-23). Public-hosting verdict: **NO**. See [phase-2 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE2.md) and [validation](ENGINEERING-VALIDATION.md); the [previous checkpoint](MODERN-CORE-CHECKPOINT-20260913.md) is retained unchanged.

## 2026-10-24: disabled endpoints removed

S03, S09, S10 and the source viewer: the 410 stubs `logdnet.php`, `images/logdnet.php`, `rawsql.php`, `payment.php`
and `source.php` were removed from `main`, with the payment log `paylog.php` (`UNNECESSARY-FILES-20261024.md`,
"License and dead weight"). A request now finds no file (404). The features stay disabled, and nothing that served
them is left: `tests/tooling/test_disabled_endpoints.py` checks GET and POST get 404 and that no shipped file links
them. The footer's "View PHP Source" link now points at the configured public source (`sourceurl`, http or https
only), not at a viewer. The rows above are kept as recorded on 2026-09-13.

## 2026-10-29: S04 and S13 updated (Phase 4 task 1B)

Rows S04 and S13 above were rewritten from the Phase 3 evidence they cite; the other rows are kept as recorded on
2026-09-13. Before this update S04's residual risk read "Installed code remains trusted PHP; bundled hook/eval and full
module activation certification still block broad acceptance", and S13's read "Full active-module hooks, crash recovery
and side-effect rollback need certification before production scheduling". What stays true is kept in each row: code
installed as a module is trusted PHP, only the 24 bundled modules are certified, maintenance is not globally atomic,
and a production scheduler is a hosting gate. The "Before public hosting" list above is as recorded; its "certify active
bundled hooks and scheduler recovery" item is now covered for the bundled modules by these rows, and scheduler
deployment remains with the hosting gates. Public-hosting verdict: still **NO**.

## 2026-11-07: open today (Phase 6 task 7, B3)

A short list of what is open at this commit, from the code and the Phase 6 evidence. The full living record, one current
row per area with its evidence and the hosting gates as a checklist, is S13 (`ROADMAP.md`); the rows above stay as
recorded.

Closed since the rows above were written (each with its evidence doc):

- S08: every state change is a POST with CSRF, most with a one-use intent (`CORE-ROUTE-SECURITY.md`, Phase 3).
- S09: the eval sites are removed (`EXPRESSION-EVALUATION-AUDIT.md`); the raw console is gone (2026-10-24 above).
- Internal files: `lib/`, `src/` and `modules/` answer a direct request with an empty 404; security headers and an
  enforced Content Security Policy on every response; a clear page for an unreadable configuration; the configuration
  file outside the docroot through `LOGD_DBCONNECT` (`PHASE6-TASK3-HARDENING-20261106.md`).
- A host without a shell: the token-gated `setup.php` and the secret web maintenance request
  (`PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`, `PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md`,
  `PHASE6-TASK5B-SETUP-PAGE-20261106.md`); the host guide and sample server rules (`HOST-GUIDE.md`).

Open:

- **Account recovery and email verification (S05; Phase 6 S11).** None exists. Resets are an administrator's action
  in the user editor. `requirevalidemail` is held off and locked in Game Settings (`PHASE6-TASK7-RC-MATERIALS-20261107.md`).
  A known limitation for rc.1 and a blocker for 1.0.0 (Jim, 2026-11-07).
- **Mail is best effort (S11).** PHP `mail()`; a failure is logged and the game goes on (`HOST-GUIDE.md`, "Mail").
- **Installed module code is trusted PHP (S04).** Only the 24 bundled modules are certified.
- **Maintenance is not one transaction (S13).** A failed run repeats its date-based cleanups.
- **Stale form tokens after signing in again during the new day.** The replayed page's buttons answer "Invalid form
  submission." (403); reloading the page works (`ROADMAP.md`, "Found during Phase 6").
- **The recorded 1.1.2 behaviors** (`PHASE6-RC-READINESS-20261106.md`, section 8), among them a ban reason shown as raw
  HTML (staff write it) and an ID ban that clearing the `lgi` cookie evades.
- **The public hosting gates** (`ROADMAP.md`, "Public hosting"): abuse controls, production TLS, proxy and session
  configuration on a real host, isolation and least privilege, a restore drill, monitoring, and an independent security
  review. Public-hosting verdict: still **NO** (`project.json`: `public_game_hosting_approved` is `false`).
