# New Day Dragon Point allocation authority, 2026-10-03

## Scope and provenance

This candidate closes only permanent Dragon Point allocation in `newday.php`.
Actual starting public branch and draft PR #1 head:
`84102268ac4c8ac0178c9a816e1f1bcf617d7398`. Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`.

The preserved `historical-source-1.1.2:newday.php` and its allocation presentation
were inspected. A kill funds one point; `hp` adds 5 permanent max HP, `at` adds 1
attack, `de` adds 1 defense, and `ff` records one permanent Forest Fight point.
The ordered history remains serialized in the existing `dragonpoints` column.
No rebalance, migration, dependency, workflow or historical-source change.

Prior runs were inspected once at entry:

| Slice | Modern core | Baseline integrity | Exact published head |
| --- | --- | --- | --- |
| Graveyard | 36322077603: CANCELLED | 36322077539: SUCCESS | `84102268ac4c8ac0178c9a816e1f1bcf617d7398` |
| PvP | 36319758950: SUCCESS | 36319758964: SUCCESS | `67f981ecdd7960acc77233814536a7c76d182004` |

Inherited accepted PvP matrix supplied with this execution: PHP 8.4.26 / MariaDB
11.4.13 and PHP 8.5.11 / MySQL 8.4.11, each 93 PHPUnit tests / 2,555 assertions,
91 Python tests, 332 lint files, both PHPStan gates and Composer PASS, zero
supported-matrix failures/skips. The run conclusions and heads above were verified;
this candidate does not retroactively reconcile other certification documents.
Graveyard Modern core cancellation is not a success or an allocation failure.

## Authority and transaction

`DragonPointState` validates the raw persisted serialization before allocation,
race selection, specialty selection or normal New Day handoff. It does not rely on
the older hydration fallback that turns an invalid scalar root into an empty list.
`ScalarState` is unchanged. The authoritative budget is persisted Dragon Kills
minus the number of valid stored entries. No submitted total or kill count funds
an allocation.

The history must be a zero-indexed list of nonempty identifier strings matching
`[a-z][a-z0-9_]{0,63}`. Built-ins are `hp`, `ff`, `at`, `de`. Well-formed removed
module identifiers (including historical `unknown`) remain readable, count as
spent, and display under Unknown Spends when no current label exists. Their
presence does not confer buyability. Invalid roots, nested/non-string entries,
invalid identifiers and over-allocation reject without truncation, refund,
normalization or onboarding advancement. Repair is an explicit administrator
operation based on provenance, followed by a fresh form; no repair endpoint is
added here.

Dragon Kills and permanent stat arithmetic retain the account columns' unsigned
32-bit domain (0 through 4,294,967,295). Allocation results must also fit the
existing 65,535-byte TEXT column and unchanged ScalarState node bound. A valid
large kill count remains readable; a history that cannot be persisted fails
closed before iteration or writing. No database truncation or wraparound is used.

Both single and bulk forms require authentication, POST, CSRF, a one-use intent,
server buyability and the existing player transaction. Single mode requires
exactly one available point. Bulk mode requires more than one and an exact total.
All buyable bulk fields are mandatory; zero is valid. Canonical unsigned decimal
integers are accepted. Negative, signed, floating, exponent, junk, blank,
overflow-sized, array/nested, missing and undeclared fields reject. Raw URL-encoded
transport is checked against parsed fields so duplicate keys, encoded duplicate
keys, PHP key normalization and truncation cannot create ambiguous authority.
JavaScript only displays points left; HTTP tests never execute it.

The intent binds identity, Dragon Kills, allocation history, unspent total, raw
permanent statistics, race/specialty, alive state, special event, day/age,
location, auth version, resurrection routing and the current server schema.
Schema mappings are sorted; equivalent associative order is not a new state.
Historical list order remains meaningful and is retained.

The player mutation locks and compares the persisted account before the callback.
Schema module/hook rows are locked and labels/buyability resolved again from
uncached registrations. When extension hooks exist, game/module settings and the
actor's preferences are locked and their request caches refreshed. The
context and budget are rechecked under that lock. Stat changes and the allocation
history commit in the same account write. The mutation helper updates its base
snapshot so the normal page save cannot restore the pre-commit account. Failure
rolls back both account changes and directly related hook DML. A failed intent
can remain consumed; retry requires a newly rendered form.

Legacy `dk` and `pdk` selectors in GET or POST are rejected without spending or
advancing onboarding. Allocation-shaped POSTs with no points cannot fall through
to daily handling. Normal
allocation-page GET only renders. A successful POST displays confirmation and a
Continue link. That subsequent request chooses the existing race, specialty or
normal New Day stage. The POST itself does not perform daily resets. The
`resurrection=true` context is preserved through forms and continuation; it never
changes the allocation budget or resurrects the player during spending.

## Extension contract

No bundled module currently implements `dkpointlabels` or `pdkpointrecalc`.
The former still supplies authoritative descriptions and explicit buyability.
Its declarations require safe identifier keys, textual labels and boolean/0/1
buyability; `unknown` is always display-only. Labels are escaped at HTML output.
Server-declared extension types can be purchased and stored without inventing
built-in stat effects.

Historically, bulk `pdkpointrecalc` receives no arguments and its return is unused;
implementations can access global `$pdks`. This candidate retains that call and
global count map inside the transaction. Only already validated buyable counts
reach it. The resulting map is checked again for strict integers, current types
and exact total. A hook cannot alter bound allocation authority or protected
permanent statistics. Synthetic tests demonstrate a legitimate count
redistribution, an extra declared type, disabled buyability, order equivalence,
invalid recalculations and rollback of hook DML on exception. Hooks remain trusted
server PHP, subject to the existing DML-only mutation callback contract; arbitrary
third-party code is not sandboxed or certified by this slice.

## Proof inventory

| Boundary | Evidence |
| --- | --- |
| Historical effects | Each single type persists once with exact stat deltas and unchanged unrelated resources; five-point bulk adds HP +10, attack +1, defense +1, one `ff` |
| GET and server authority | Forged `dk`/`pdk` at zero, one and five points reject; raw HTTP submits without JavaScript |
| Authentication and intent | Anonymous, missing/invalid CSRF or intent, replay and alternative issued choices reject |
| Strict inputs | Under/overspend, extreme, negative, malformed, nested, missing, duplicate and undeclared keys reject atomically |
| Stale contexts | Kill/history/stat/race/specialty/day/alive changes reject; resurrection context mismatch rejects |
| Multiple tabs | Independently issued forms from one initial state yield exactly one allocation |
| Lock acquisition race | Separate database writer holds the account; HTTP reaches its locking read, writer changes kills or label-hook configuration and commits; locked revalidation rejects without allocation |
| Rollback | Late database CHECK rejects prepared HP/attack/defense/history; replay fails; fresh form succeeds exactly once; hook DML also rolls back |
| Stored corruption | Over-allocation, invalid serialization/root/entry/list shape preserve stored bytes and do not show onboarding |
| Handoff | Final spend leads to existing protected race or specialty forms, or existing New Day presentation |
| Forest Fight consumer | Real existing New Day output counts newly stored `ff`; full daily-reset semantics are not certified |
| Bounds | Unit and HTTP stat overflow; unit exact upper boundaries, uint kill domain, serialization capacity |
| Historical integrity | Annotated tag `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits: PASS |

The focused tests are `DragonPointStateTest` and the five `test_dragon_points_*`
HTTP matrices. Existing tests are retained without weakened assertions.

## Candidate verification and verdict

Local environment: PHP 8.4.26 / MariaDB 10.11.14 (supplemental local proof;
GitHub's PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4 matrix remains required).

| Gate | Result |
| --- | --- |
| PHPUnit | 101 tests, 2,687 assertions, zero failures/skips |
| Python / HTTP | All 105 distinct Python tests have passing results, including 96 HTTP application tests |
| Final allocator verification | Five allocation HTTP matrices rerun on final source: PASS, zero failures/skips |
| Lint | 338 PHP files, zero failures |
| Static analysis | Both PHPStan gates PASS |
| Composer | Locked install, strict validation and audit PASS; no vulnerability advisories |
| Integrity | Historical verifier, project hygiene and diff whitespace checks PASS |

The first broad Python run passed 53 cases, then stopped during an inherited
fixture's CREATE TABLE with a local MariaDB table/engine inconsistency, before
that case's gameplay assertions. No test assertion was weakened. An unchanged
copy and fresh disposable database under `/tmp` passed the remaining cases,
with 58 passing recovery-run tests including repeated bootstrap/allocation cases.
The union was checked against discovery: 105/105 distinct cases. The final
cache-refresh and no-points POST guards were subsequently verified through all
five focused HTTP matrices, full PHPUnit, lint and both static-analysis gates.
There are zero unresolved local test failures and no skipped tests. The local
runtime setup failure is retained here rather than characterized as a product
failure or silently omitted.

Exact published SHA, complete-tree equality and workflow run IDs belong to the
PR publication evidence because this document is part of the candidate tree.

New Day Dragon Point allocation: **BLOCKED pending exact-candidate supported-matrix
CI acceptance**. Local boundary proof does not promote full New Day.
Broader New Day, remaining Phase 3 route/serialized-state families, resurrection,
mail, petitions, clans, economy/equipment, mounts/stables, editors, expiration
cleanup and account deletion remain outside this execution. Phase 3 INCOMPLETE;
merge NO; public hosting NO. PR #1 must remain OPEN/DRAFT/NOT READY/NOT MERGED.
No VPS, deployment, release, tag or Phase 4 work.

Next task: inspect the published Dragon Point candidate's bounded CI result and
accept this exact slice only if both supported matrix jobs and baseline pass;
otherwise address only its precise failure. Do not start another subsystem here.
