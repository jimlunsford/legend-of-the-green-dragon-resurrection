# Normal New Day: Graveyard fixture correction, 2026-10-03

## Boundary and original failure

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Branch: `modernization/core-modernization`; existing draft PR #1 only.
Starting public commit: `28c4b803b1d1ede7d1479361f58194b08bdd7cc0`.
Starting tree: `580abce31b0881511e3e2d4bd20a5dc8f16d7f78`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Modern core run `37127144959` failed on both supported jobs:

| Job | Runtime | Actual failure |
| --- | --- | --- |
| `111214636692` | PHP 8.4.26 / MariaDB 11.4.13 | Companion rollback expected 500, received 409; 41 Python methods reached |
| `111214636552` | PHP 8.5.11 / MySQL 8.4.11 | Same assertion and response; 41 Python methods reached |

Baseline integrity `37127144947` succeeded. Both Modern jobs were terminal at the initial inspection. No old workflow was redispatched or repeatedly polled.

The exact failing method was `test_graveyard_companion_suspension_and_skeleton_exclusion` in `tests/tooling/test_web_application.py`. Its response was the generic stored-state-preserved Graveyard 409. The failure reproduced unchanged on local PHP 8.4.26 with MariaDB 10.11.14 and supported MariaDB 11.4.13.

## Observed root cause

This is a shared fixture eligibility defect, not a database-vendor defect, companion-validation defect, or CHECK-constraint side effect.

The real HTTP account-creation/onboarding setup commits a nonempty `lastnewday`. The shared `_graveyard_fixture.prepare()` then called `_specialty_accounting_fixture.prepare('')`, clearing the specialty while retaining that marker. Normal New Day deliberately makes `is_new_day()` true for an incomplete race/specialty/Dragon Point onboarding state when a durable marker exists. An empty specialty therefore correctly requires onboarding before Graveyard search.

Temporary diagnostic instrumentation, removed before the candidate, observed:

1. Before form generation: dead player, HP zero, soulpoints 100, gravefights 5, empty combat blob, Human race, empty specialty, nonempty `lastnewday`, and valid helper/excluded/skeleton companions.
2. GET context construction and `resurrection_graveyard_state()` passed, including persisted and hydrated companion validation. GET did not mutate the account or companions.
3. Before/after `ALTER TABLE ... ADD CONSTRAINT fixture_grave_companion`: identical account and serialized companion state. The constraint was not changing eligibility.
4. POST consumed the action intent, acquired the account lock, passed persisted-account revalidation and the second context/state checks, then entered the search eligibility guard.
5. `$state === []` and `gravefights === 5`; `is_new_day()` was true because the specialty was empty. The exact exception was `DomainException('Search unavailable.')` at `lib/graveyard_combat.php`'s search guard (starting-source line 68).
6. No header hook, companion suspension, combat transition, or account UPDATE had occurred. The intended late CHECK failure was never reached.

The Graveyard implementation and shared fixture had not changed since the preceding Graveyard candidate `84102268ac4c8ac0178c9a816e1f1bcf617d7398`. Its earlier `is_new_day()` implementation used only `lasthit`/game time; the Normal New Day commit added the durable-marker/onboarding guard. Clearing the marker or relaxing that guard would evade the correct application contract.

## Narrow correction and retained proof

Only the shared Graveyard fixture now selects `DA`, a valid bundled specialty. It retains the real completed-day marker and all production eligibility, intent, state, and transaction checks. No production PHP, database schema, dependency, workflow, timeout, or gameplay formula changes.

The same temporary CHECK constraints and expected HTTP 500 remain in every failure-injection case. All nine Graveyard HTTP methods use the corrected helper, including search, victory/defeat, companions, flee, event handoff, and header-hook rollback. No vendor branch, retry masking, skip, assertion reduction, or 409-as-success was added.

The companion case additionally checks the nonempty durable marker, unchanged account/companion/buff state through GET and constraint creation, and complete player module preferences through rollback and replay rejection. It activates the shipped Drinks header hook with drunkenness 75 and supplies a temporary buff, so related database DML and effect cleanup occur before the final account write. A failed consumed intent must reject after removing the constraint; a fresh form must commit successfully. Search eligibility also retains an explicit empty-specialty rejection.

Corrected temporary pre-write tracing confirmed gravefights 5 -> 4, a newly created live combat blob, helper HP 100 -> 94 and `used=true`, and suspension of the excluded helper and skeleton. The final account UPDATE then threw `Database operation failed.` with the constraint installed. Rollback preserved the starting account, combat, companions, buffs, news, and preferences; Drinks remained 75. Fresh retry committed gravefights 4, enemy HP 85, helper HP 94, empty buffs, Drinks 0, and the same `lastnewday`. Existing terminal companion suspension, death, ordering, soul/favor accounting, and malformed-state evidence remains intact.

## Verification

| Verification | Result |
| --- | --- |
| PHP 8.4.26 / MariaDB 11.4.13 focused Graveyard | 9/9 HTTP methods PASS |
| PHP 8.4.26 / MariaDB 10.11.14 supplemental Graveyard | 9/9 HTTP methods PASS |
| PHP 8.4.26 / MySQL 8.4.11 supplemental focused HTTP | 17/17 PASS: setup + 9 Graveyard + 7 Normal New Day |
| PHPUnit, MariaDB 11.4.13 | 105 tests / 2,721 assertions PASS, no skips |
| PHPUnit, MySQL 8.4.11 with PHP 8.4.26 | 105 tests / 2,721 assertions PASS, no skips |
| Final PHP lint | 342 files, zero failures |
| PHPStan baseline and infrastructure gates | Both PASS, zero errors |
| Composer locked install, strict validation, audit | PASS; no vulnerability advisories |
| Complete local Python/HTTP attempt, PHP 8.4.26 / MariaDB 11.4.13 | 71 methods in one uninterrupted run, 718.377 seconds: 70 PASS (61 HTTP + 9 tooling), 1 retained Forest failure; fail-fast stopped the remaining 41 |
| Whitespace and project hygiene | PASS |

The local MySQL check is supplemental because PHP 8.5 is not installed locally. Supported PHP 8.5/MySQL acceptance remains CI-owned. PHPStan initially could not open its local worker socket under sandbox restrictions; both unchanged gates passed with loopback execution enabled. The installed Composer CLI emits its own PHP 8.4 deprecation notices, separate from application tests.

One attempted repeat PHPUnit setup detected a leftover synthetic `resurrectionspecialtyobserver.php` from the earlier HTTP run and failed the module-inventory assertion. It did not start the complete Python suite. The final full-run attempt uses a clean isolated checkout and freshly recreated disposable database. Initial expected reproductions and that setup failure are not presented as passing runs or combined into an uninterrupted result.

The full attempt passed all nine Graveyard methods and all seven Normal New Day methods, then stopped at `test_ordinary_forest_search_escape_and_event_handoff`: the first intended successful search returned 409 instead of 200 (candidate test-source line 2016). An unchanged, clean control checkout of the starting commit `28c4b803b1d1ede7d1479361f58194b08bdd7cc0`, using the same disposable database after fixture restoration, reproduced the same assertion at original line 2000 in a separate single-method run. Thus this failure predates the Graveyard correction; full local success is NOT claimed. That retained Forest fixture explicitly supplies `specialty=''`, and its search path also checks `is_new_day()`. No Forest source/test correction or further subsystem investigation was made in this execution. The retained failure is an additional acceptance blocker, separate from the unchanged CI timeout issue.

The test collection remains 112 methods (103 HTTP and nine tooling), not a claim that all 112 executed successfully. Final test-source bytes were compared equal across the reviewed candidate, complete-run checkout, and MySQL focused checkout.

Historical integrity PASS: annotated tag `historical-source-1.1.2`, object `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 preservation commits. No historical object changed.

Exact published-head CI identity and state belong to PR #1, avoiding a self-referential commit SHA in this file. Focused runs overlap the full suite and must not be added together as distinct test counts.

## Acceptance boundary and next task

This correction establishes no supported-matrix acceptance until both exact published-head CI legs succeed. Normal New Day acceptance remains blocked pending that evidence. Explicit early resurrection remains outside scope. Phase 3 is incomplete; merge NO; public hosting NO. PR #1 remains OPEN, DRAFT, NOT READY, NOT MERGED. No deployment, tag, release, other branch/PR, historical repository change, or VPS access.

The independent 45-minute workflow ceiling remains a known infrastructure blocker; it did not cause the original 409 failure and is unchanged here.

Next task: **Change only `.github/workflows/modern-core.yml` `timeout-minutes` from 45 to 60, preserve all tests and matrix legs, publish one workflow-only commit, and verify one full exact-head CI cycle.**
