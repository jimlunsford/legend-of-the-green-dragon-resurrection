# Security status

**NOT APPROVED FOR PUBLIC GAME HOSTING**

Updated 2026-09-13 for draft PR #1. This ledger describes the modernization branch, not unchanged foundation main. The inherited audit remains the reference. A disabled endpoint is not a security certification for the rest of the application.

| ID | Status | Remediation/evidence | Residual risk or next work |
|---|---|---|---|
| S01 | NOT APPLICABLE to shipped core | Archive advertising modules were not imported | Admission review still required |
| S02 | FIXED for commentary boundary | Authorized moderator/admin only; strict positive IDs, prepared queries, transactional audit/delete/restore, POST and CSRF. Anonymous/player/GET/bad-token/SQL-expression/missing-ID tests; actual player posting and moderator deletion via HTTP | Broader moderation UI and ban workflows are not fully browser-certified |
| S03 | DISABLED | logdnet.php and images/logdnet.php are unconditional 410 stubs; footer registration/remote script generation removed. HTTP regression tests cover GET/POST | No replacement network service; legacy settings are inert; other outbound fetch helpers require separate review |
| S04 | FIXED for dispatcher/lifecycle boundary | Request force ignored; installed, active and dependency/version checks enforced; ordinary force rejected. Manager lifecycle requires POST/CSRF. Reinstall preflight preserves metadata on failure and successful reinstall stays inactive | Installed code remains trusted PHP; bundled hook/eval and full module activation certification still block broad acceptance |
| S05 | FIXED for fresh credentials; recovery DISABLED | password_hash(PASSWORD_DEFAULT), password_verify, varchar(255), no MD5 authority or hash replay. Admin/player creation, wrong/missing/locked/malformed/hash-as-password cases tested. Password changes verify current password and revoke prior generations | No historical credential migration. Password-derived recovery and legacy validation return 410; modern token/email workflow deferred |
| S06 | FIXED for authentication/installer diagnostics | Failed logins store a fixed event only; no passwords/hashes/tokens/cookies/session IDs. HTTP tests inspect actual faillog records. Generic exceptions and argument-free source diagnostics replace request/backtrace serialization. Previous stage-6 nondisclosure tests retained | Other gameplay/mail/admin logging and production log access still require review |
| S07 | FIXED for core lifecycle | Native strict cookie-only sessions, HttpOnly/Lax, Secure on trusted HTTPS. Login and privilege changes rotate ID and CSRF; database generation revokes older sessions on login/password/logout. Logout destroys server session and expires cookie. HTTP tests verify fixation rejection, rotation, authenticated render and old-session rejection | Secure HTTPS cookie branch documented, not TLS-browser tested; deployment/session storage isolation remains unapproved |
| S08 | MITIGATED; foundation operational | Random session-bound CSRF helpers; POST/CSRF for login/logout/create, preferences/password, modified user/configuration, module management and moderation mutations. Navigation is not treated as CSRF. Unit/integration/HTTP negative and positive tests | Remaining gameplay economy, mail, petition and other editor mutations still require route-by-route POST/CSRF/authorization coverage |
| S09 | DISABLED for raw web console | rawsql.php is an unconditional 410 stub with no application bootstrap; HTTP tests cover GET/POST | Other eval expressions remain in modules, buffs and extended battle; constrained evaluation is still required |
| S10 | DISABLED | payment.php is an unconditional 410 stub; footer merchant forms/notify URLs removed; HTTP tests cover GET/POST | No modern payment implementation or external payment account connected |
| S11 | NOT APPLICABLE to shipped core | Archive blog module not imported | Future admission review |
| S12 | NOT APPLICABLE to shipped core | Archive character restore module not imported | Future restore boundary review |
| S13 | MITIGATED; supported CLI path verified | HTTP GET/POST return 404. Installed CLI maintenance acquires database lock, runs existing maintenance and records completed game day; repeated invocation returns already-complete. Both matrix HTTP/CLI suites pass | Full active-module hooks, crash recovery and side-effect rollback need certification before production scheduling |
| Source viewer | DISABLED | source.php returns 410 before config or file access; repository remains the source distribution channel | Future web-server rules must also deny direct internal/config access |

## Fidelity and public hosting

Known security defects are not desired gameplay. Historical originals remain in the immutable tag. No public runtime, VPS deployment, DNS, backup, database-server or web-server changes were made.

Before public hosting: finish remaining SQL/output/authorization/CSRF routes, replace or constrain module/buff/combat eval paths, certify active bundled hooks and scheduler recovery, implement modern recovery/email delivery, and verify private configuration/internal-file denial, TLS/headers, abuse controls, deployment isolation and restore procedures. These are remaining gates even though the base installation/authentication milestone now passes. Independent security review remains required for public readiness.

PR #1 remains open and draft. Public-hosting verdict: **NO**. See [phase-2 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE2.md) and [validation](ENGINEERING-VALIDATION.md); the [previous checkpoint](MODERN-CORE-CHECKPOINT-20260913.md) is retained unchanged.
