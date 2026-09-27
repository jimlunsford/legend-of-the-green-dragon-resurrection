# Dark Arts companion boundary, 2026-09-27

Starting accepted head: `ea1f3563c14188f9e1f536785c71c5b699f332f0`.
Branch: `modernization/core-modernization`; PR #1 remains draft. Main stays
`999cec6f9c655a840320d982d673bb863c68c2b2`.

## Decision and acceptance

The remaining Dark Arts behavior now has deterministic, real HTTP regression
coverage. The local PHP 8.4.26 / MariaDB 11.4.13 run passes the new cases and
retained Dark Arts cases. **Final certification remains BLOCKED pending the
exact candidate's PHP 8.4/MariaDB and PHP 8.5/MySQL CI acceptance.** Accepted
counts remain **22 PASS / 0 limitations / 2 BLOCKED**, with 24 lifecycle PASS.
No Mystical Powers status or tests are changed. Once both exact-head workflows
pass, a documentation-only acceptance closeout may promote Dark Arts to PASS
and record 23/0/1. No additional Dark Arts behavior gap is identified in this
bounded review. PR #1 records the candidate SHA, workflow IDs and current CI
state, avoiding a self-referential documentation-commit cycle.

## Historical authority

`battle.php`, `lib/extended-battle.php`, `lib/battle-buffs.php` and the bundled
Dark Arts producer were traced against `historical-source-1.1.2`. The combat
ordering and companion damage formulas were not changed.

1. Apply the selected specialty; calculate round-start effects, including minions
   and Voodoo. A terminal round-start result skips the weapon/companion exchange.
2. Calculate the player/enemy roll. Player weapon attack or lethal riposte can
   stop combat before enemy defense activation or companion fighting.
3. Enemy attacks the player, unless an actual defending companion intercepts.
   The skeleton only has `fight`, so it does not intercept player damage.
4. If player and target remain alive, the skeleton attacks, then the enemy
   retaliates against it when target HP is **greater than or equal to zero**.
   Thus exact-zero companion kills still permit retaliation; overkills do not.
   Companion riposte damage and enemy retaliation precede the single removal.
5. Expire used buffs, resolve dead-target history and server-selected next
   target, then settle the caller's terminal result. Live skeletons have no
   `expireafterfight` flag. They retain injured HP on Forest victory/defeat and
   Dragon defeat or pending victory. Dragon prologue completion clears them.

A player already defeated in the weapon/enemy exchange cannot also lose its
skeleton in that later companion phase. This combination is not manufactured.
A skeleton and Dragon can both die at exact zero, with Curse Spirit expiring
in the same round. Existing simultaneous player/Dragon lethal rules are retained.

## New HTTP evidence

| Test | Proven boundary |
|---|---|
| `test_darkarts_companion_injury_death_defeat_and_rollback` | Healthy creation followed by real nonlethal injury; next-request HP; Curse Spirit rounding; lethal companion riposte/retaliation and one removal; subsequent absence; explicit resummon and live replacement; healthy/injured companion retained on player defeat in both callers; late account-write failure rolls back uses, HP, companions, buffs and combat, then fresh intent retries. |
| `test_darkarts_companion_combined_natural_expiration` | Actual casts 1, 3, 5, then repeated Voodoo: both persistent modifiers through natural expiration, exact player/companion/target HP every round, ordered effects and wearoff, exact costs and replay in Forest and Dragon. |
| `test_darkarts_companion_target_progression_and_terminal_order` | Companion kills A at zero/negative HP; zero permits lethal retaliation, negative preserves the injured skeleton; B selected server-side without intermediate reward; fresh B Wither Soul action; final Voodoo settlement, injured carry, exact rewards, stale-A and replay rejection. |
| `test_darkarts_dragon_companion_simultaneous_victory_and_expiration` | Real injury down to one companion HP, exact-zero versus negative Dragon kill, companion death versus injured carry, final Curse expiration, stored victory, immediate continuation, final clearing and replay. |
| `test_darkarts_fallback_minions_combined_and_terminal` | Server-disabled DA1 minion stats and costs, context invalidation on setting changes, request-side override rejection, all Dark Arts buffs through natural expiration, Forest progression at final minion round, independent Dragon fallback victory/defeat/continuation/replay, and historical coexistence after changing the producer setting while a warrior exists. |

Every new specialty mutation is replayed and its business snapshot compared.
Fresh subsequent requests check committed state. Ordinary Dragon terminal actions
and continuations have their own replay checks. Failed one-use intents can remain
consumed; this is not a universal exactly-once delivery claim.

Representative exact totals (seed 12345, existing loopback-only fixture):

| Case | Forest | Dragon |
|---|---|---|
| Healthy skeleton, first nonlethal retaliation | 43 -> 17 HP; target 99929; player 500 | 60 -> 37 HP; target 99923; player 500 |
| Following Curse Spirit round | 17 -> 4 HP; target 99858; player 500 | 37 -> 25 HP; target 99846; player 500 |
| Final combined sequence after both modifiers expire | skeleton 4; target 98258; player 487 | skeleton 25; target 98153; player 487 |
| Fallback DA1 statistics | 4 minions; maximum damage 6 | 6 minions; maximum damage 9 |

## Fallback is a producer choice

There is **no automatic death-to-minion fallback**. Skeleton death removes the
warrior. A later DA1 cast explicitly creates a replacement, or creates `da1`
minions when `enablecompanions` is false. Fresh disabled-mode casts produce no
skeleton and cannot be overridden by a request.

The historical setting does **not** suspend or delete an existing skeleton.
Changing it and casting DA1 can produce minions alongside the old warrior. The
new coexistence case proves the warrior's subsequent damage/removal in Forest
and surviving contribution in Dragon. Suppressing that contribution would change
shipped behavior, so no such cleanup policy was introduced.

## Retained certification scope and validation

Accepted onboarding, all 1/2/3/5 costs/effects, Forest/Dragon authority, malformed
state and missing module/file/handler rejection, adverse accounting, duration,
stale forms, New Day restoration, Dragon identity/prologue/reset, replay and
rollback remain required. The new tests close the previously listed companion
and same-specialty combination obligations, rather than reopening Thieving Skills
or general combat. Cross-specialty aura work remains Mystical Powers' separate gate.

No production source, business validator, serialization reader/writer, workflow,
version, dependency or historical object changes. `SkeletonCompanionState` and
`SpecialtyBuffState` remain strict. Serialization inventory therefore does not
change. The terminal test observer now explicitly verifies table/file cleanup.

Local evidence: 88 PHPUnit tests / 2,462 assertions; authentication plus seven
Dark Arts HTTP methods pass, including five added methods. The full Python suite
now collects **60 tests (51 HTTP + 9 tooling)**. Full supported-matrix execution
is delegated to exact-head CI and is not claimed complete from the focused run.
Composer strict validation, locked install and security audit, both PHPStan gates,
PHP lint, nine tooling tests and historical integrity are required alongside CI.

Historical integrity: tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`,
source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.

Phase 3 INCOMPLETE; merge NO; public hosting NO. No VPS access, deployment,
release, version tag, Phase 4, Mystical Powers development or general combat
closure. After publication, inspect CI once and at most once later, then stop
if still nonterminal. Next task: exact-candidate acceptance and Dark Arts
certification closeout only.
