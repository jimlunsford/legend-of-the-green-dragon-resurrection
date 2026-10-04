# Mystical Powers supported-boundary candidate, 2026-09-27

Starting head: `816e709e91a51edfa0d3801200f90550757897b6` on
`modernization/core-modernization`, PR #1 OPEN and DRAFT. Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`.

## Decision and acceptance boundary

The candidate records **specialtymysticpower PASS**, giving **24 PASS / 0
limitations / 0 BLOCKED**, with all 24 lifecycle checks PASS. No remaining
supported bundled Mystical Powers behavior gap was identified after reviewing
retained onboarding, authority, costs, schemas, adverse effects, Dragon outcomes,
New Day, replay and rollback evidence with the six new HTTP tests below.
**Exact-candidate supported-matrix CI acceptance remains pending.** This is the
24/24 candidate milestone, not a claim that both candidate runtime jobs passed.
Until those jobs succeed, the last accepted certification remains 23/0/1.
The candidate SHA, workflow IDs and bounded inspection belong in PR #1.

Phase 3 remains INCOMPLETE. Merge NO. Public hosting NO. PR #1 remains OPEN,
DRAFT, NOT READY, NOT MERGED. Ordinary Forest authority, general combat and other
core blockers remain outside this certification. No deployment or VPS activity.

## Shipped ordering is the authority

Inspected `modules/specialtymysticpower.php`, `battle.php`,
`lib/battle-buffs.php`, `lib/extended-battle.php`, `lib/specialty_combat.php`,
`SkeletonCompanionState` and `SpecialtyBuffState`. The buff engine is byte-identical
to the preserved historical version. No production behavior or validator changed.

- MP1 creates five-round regeneration. It heals the current target's player at
  round start, capped at maximum HP, then heals eligible companions by
  `round(level / 3)`, individually capped. That is the MP healing aura.
- MP2 Earth Fist is a five-round persistent area minion buff, not an instant
  cast. It rolls 1 through level*3 at round start for each eligible live enemy.
  `maxattacks` still limits which enemies participate. Dead history is skipped.
- MP3 Lifetap heals positive weapon damage and positive player riposte damage,
  capped at player maximum HP; it cannot heal negative damage. A weapon kill
  still heals before the terminal flags are assessed and skips enemy retaliation.
- MP5 Lightning Aura is the five-round shield, reflecting twice the incoming
  damage, including weapon riposte damage. Reflection still occurs on a lethal
  enemy hit. It is not a separate companion modifier.
- With the shipped skeleton's fight ability, player attack and enemy exchange
  precede companion fighting. Regeneration aura healing precedes those actions.
  An exact-zero companion kill still allows retaliation; overkill skips it.
  Early player defeat skips companion fighting, but does not undo earlier aura healing.
- Used buffs decrement once after the enemy loop, before target selection and
  terminal hooks. A phase that never activates a buff does not spend its round.
  Server target selection occurs after the round; B is not an arbitrary request field.
- Final hooks see defeated history and the final target; the specialty POST
  handler owns settlement and clears Forest combat. Ordinary Forest GET terminal
  persistence is outside this task and was not changed or certified.

The retained `test_specialty_terminal_rewards_area_and_fallback` proves Earth
Fist's real area damage when multiple live enemies participate. New progression
cases use `maxattacks=1` to isolate the transition and then fresh B actions.
Dragon is intentionally single-target; retained Dragon authority, effect,
expiration and simultaneous-terminal tests supply its differing boundary.

## New exact HTTP evidence

| Test | Proven result |
| --- | --- |
| `test_mystic_effect_target_progression_and_fresh_recast` | Real level 2/3/5 casts leave A alive, exactly zero or negative; dead A is immutable, B is server-selected, arbitrary target fields reject, old forms and replay reject, fresh B recasts work with exact uses and no intermediate reward. At player level 15 Earth Fist rolls 16; at level 10 Lifetap heals a 40-point hit and shield returns 354 after 177 incoming damage. |
| `test_mystic_expiration_at_target_transition` | Real-produced regeneration, Earth Fist, Lifetap and shield cross A-to-B with three or one rounds remaining. Exact heal/damage and TTL assertions prove final-active-round expiration and absence of the effect on B afterward. |
| `test_mystic_progressed_terminal_healing_shield_and_rollback` | Final B victory/defeat after actual A defeat; regeneration/Lifetap healing before lethal damage; shield simultaneous final victory. Real regeneration + Lifetap + shield on A with B alive distinguishes player HP 127 (defeat) from 128 (one HP survives). A late account CHECK failure rolls back account HP, preferences/uses, combat/target, buffs and terminal observer writes; consumed intent rejects and fresh retry succeeds. |
| `test_mystic_aura_companion_progression_natural_expiration` | Real skeleton producer, then MP aura: five natural rounds heal companion 30 to 33/36/39/42/43; player 995 caps at 1000; sixth round has no extra heal. A-to-B transition on initial or final active round preserves exact damage and duration. Injury case proves 30+3-26=7 HP through aura, player hit, enemy riposte, skeleton strike, exact-zero retaliation; B's next round heals to 10 before companion action. |
| `test_mystic_all_effects_cross_target_and_expire_naturally` | Four actual casts (1,3,5,2) create the legitimate combined state. Player HP sequence: 5010,5065,5120,5044,4968,4882,4796,4710,4710. A dies exactly at combined damage 173; B then receives 173/173/173/1/45 damage as effects expire. Uses finish at 29 from 40; all exact TTLs, dead history, stale actions and unchanged rewards are asserted. |
| `test_mystic_aura_companion_and_earth_fist_terminal` | After actual A defeat, final-round aura and optional real-produced Earth Fist end in B victory or player defeat. Level-15 aura heals retained companion 30 to 35 before early victory/defeat skips companion fighting; player heals before Earth Fist or Lifetap. Both terminal hooks, HP, enemy HP, uses and expiration are exact. |

Companion coexistence uses the genuine bundled skeleton shape, not an invented
cross-specialty cast. The shipped Cedrik forgetfulness potion clears specialty
without clearing companions, and subsequent onboarding can select MP. Existing
healing-aura evidence uses the same retained-companion boundary. No DA buff is
injected into the MP combined matrix and Dark Arts certification is unchanged.

Every new specialty cast checks exact uses, stale intent rejection, replay and
subsequent read persistence. The shared rejection helper compares complete account
business snapshots and preferences, including reward fields. Terminal observer
cleanup is asserted. Existing 51 HTTP methods remain AST-identical.

## Local validation and CI discipline

Local PHP 8.4.26 / MariaDB 11.4.13: 88 PHPUnit tests, 2,462 assertions, including
24 module lifecycle checks. Focused HTTP: ten tests (authentication, six new tests,
retained simultaneous terminal, healing aura and area damage). Nine tooling tests.
321 PHP files lint without failure; legacy and infrastructure PHPStan pass.
Composer strict validation, locked installation and audit pass, no advisories.
The final focused run must pass before publishing this candidate.
Full suite now collects **66 Python/tooling/HTTP tests (57 HTTP + 9 tooling)**;
that count is not represented as a completed local full-suite or matrix run.
Exact-head Modern core and Baseline integrity remain the acceptance gate.

The starting documentation-head CI was inspected once: Modern core 36312821298
IN_PROGRESS; Baseline integrity 36312821299 SUCCESS. No waiting or redispatch.
After candidate publication, inspect its automatic exact-head runs once, with at
most one optional later inspection, then stop if pending. Freeze source changes.

Historical integrity PASS: tag `historical-source-1.1.2`, annotated object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.
Serialization inventory, business schemas, workflows and dependencies are unchanged.
Historical repositories remain untouched.

Next task: inspect the exact Mystical Powers candidate CI results and accept or
report its precise failure. General combat requires a separate execution.
