# Security status

**NOT APPROVED FOR PUBLIC GAME HOSTING**

Historical audit date: 2026-09-13. No finding below is fixed in this foundation. Runtime isolation is a research boundary, not remediation. Archive-only defects remain relevant to admission policy but those modules are not copied into Resurrection.

| ID | Historical issue | Scope | Status |
|---|---|---|---|
| S01 | Unsafe advertising upload | Archive-only, not imported | OPEN / not corrected |
| S02 | Anonymous commentary deletion / SQL-injection path | Inherited core | OPEN / not corrected |
| S03 | Anonymous remote fetch and unsafe deserialization | Inherited core | OPEN / not corrected |
| S04 | Module activation / force-loading bypass | Inherited core | OPEN / not corrected |
| S05 | Weak and replayable password hashes | Inherited core | OPEN / not corrected |
| S06 | Authentication material enters diagnostics | Inherited core | OPEN / not corrected |
| S07 | Incomplete session lifecycle controls | Inherited core | OPEN / not corrected |
| S08 | No general CSRF protection | Inherited core and module contract | OPEN / not corrected |
| S09 | Privileged web SQL/PHP execution | Inherited core | OPEN / not corrected |
| S10 | Unsafe donation validation | Inherited core | OPEN / not corrected |
| S11 | Trusted blog HTML | Archive-only, not imported | OPEN / not corrected |
| S12 | Unsafe character restore boundary | Archive-only, not imported | OPEN / not corrected |
| S13 | Anonymous maintenance / cron trigger | Inherited core | OPEN / not corrected |

## Desired behavior versus defects

All S01–S13 are historical defects or unsafe trust boundaries, intentionally not preserved as desired product behavior. Golden gameplay fixtures must not authorize anonymous moderation, force-loading bypass, password-hash replay or web PHP execution. Future fixes need security regression tests plus gameplay compatibility tests.

## Before public hosting

Require supported runtime/database; modern credentials/recovery/sessions; parameterized or rigorously typed queries; authorization; contextual output safety; CSRF; safe files/modules; secured scheduler/admin surfaces; disabled or repaired obsolete integrations; tested installer/migrations/encoding; TLS and headers; site/database isolation; backups/restore; non-secret logs; abuse limits; working email; dependency/component rights; and independent verification of repaired high-severity findings.

The public repository must never be described as proof that the game is safe to host. See the full historical audit for path-level evidence and exploitability conditions. Do not add operational exploit payloads to the README.
