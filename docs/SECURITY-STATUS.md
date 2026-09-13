# Security status

**NOT APPROVED FOR PUBLIC GAME HOSTING**

Updated 2026-09-13 for draft PR #1. This ledger describes the modernization branch, not unchanged foundation main. The inherited audit remains the reference. A disabled endpoint is not a security certification for the rest of the application.

| ID | Status | Remediation/evidence | Residual risk or next work |
|---|---|---|---|
| S01 | NOT APPLICABLE to shipped core | Archive advertising modules were not imported | Admission review still required |
| S02 | DEFERRED, blocking | Commentary deletion still has the original authorization/SQL/GET boundary; PDO alone does not fix string-built SQL | Require authorization, typed IDs, POST, CSRF, transactional moderation and the requested regression cases |
| S03 | DISABLED | logdnet.php and images/logdnet.php are unconditional 410 stubs; footer registration/remote script generation removed. HTTP regression tests cover GET/POST | No replacement network service; legacy settings are inert; other outbound fetch helpers require separate review |
| S04 | DEFERRED, blocking | runmodule.php still accepts caller-controlled administrative force | Remove request force and test installed/active/dependency enforcement |
| S05 | DEFERRED, blocking | Historical double-MD5 and hash replay remain | password_hash/password_verify, replay rejection, modern reset or disable recovery |
| S06 | DEFERRED, blocking | Stage 6 no longer displays configuration secrets; PDO errors omit SQL/parameters. Actual generation/failure fixtures verify non-disclosure | Authentication, installer stages outside stage 6, logs and errorhandler still require full secret review |
| S07 | DEFERRED, blocking | No session lifecycle modernization claimed | Rotation, fixation resistance, cookie flags, logout and timeout tests |
| S08 | DEFERRED, blocking | No general CSRF mechanism yet | Integrate with account/economy/moderation/admin/mail/module mutations and test failures |
| S09 | DISABLED for raw web console | rawsql.php is an unconditional 410 stub with no application bootstrap; HTTP tests cover GET/POST | Other eval expressions remain in modules, buffs and extended battle; constrained evaluation is still required |
| S10 | DISABLED | payment.php is an unconditional 410 stub; footer merchant forms/notify URLs removed; HTTP tests cover GET/POST | No modern payment implementation or external payment account connected |
| S11 | NOT APPLICABLE to shipped core | Archive blog module not imported | Future admission review |
| S12 | NOT APPLICABLE to shipped core | Archive character restore module not imported | Future restore boundary review |
| S13 | MITIGATED, incomplete scheduler | cron.php rejects web requests before bootstrap. Repeated GET/POST tests require 404. CLI uses its own directory | CLI maintenance still blocked by core bootstrap. Locking, concurrency, repeat/idempotency and full daily-hook integration remain unverified |
| Source viewer | DISABLED | source.php returns 410 before config or file access; repository remains the source distribution channel | Future web-server rules must also deny direct internal/config access |

## Fidelity and public hosting

Known security defects are not desired gameplay. Historical originals remain in the immutable tag. No public runtime, VPS deployment, DNS, backup, database-server or web-server changes were made.

Before public hosting: complete supported fresh install, modern credentials/recovery/sessions, typed or parameterized SQL boundaries, authorization, context-specific output escaping, CSRF and mutation methods, safe modules/eval, scheduler concurrency, private configuration, post-install lockout, TLS/headers, abuse controls, mail, deployment isolation and recovery evidence. Independent security review is still warranted after those engineering gates are met.

See [modern checkpoint](MODERN-CORE-CHECKPOINT-20260913.md) and [validation scope](ENGINEERING-VALIDATION.md) for actual workflow results. Do not promote main or merge this partial branch solely because engineering CI passes.
