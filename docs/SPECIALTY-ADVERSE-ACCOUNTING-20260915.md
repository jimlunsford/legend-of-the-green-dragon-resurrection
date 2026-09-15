# Specialty adverse accounting, 2026-09-15

Starting SHA: `37bc7d2af907d8ae3b512d51fc0d583e17d627a3`.
Branch `modernization/core-modernization`, PR #1 OPEN / DRAFT / NOT READY / NOT MERGED.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

**21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED.** All 24 lifecycle checks retained.
Phase 3 INCOMPLETE. Merge NO. Public hosting NO. No general-combat certification.

## Authority and observation

The existing loopback-only seed 12345 runs real authenticated specialty POSTs,
real module producers, battle.php and the shared player transaction. A temporary
fixture-only module observes battle-defeat/victory inside that transaction,
recording exact target HP, player HP, buffs and companions before Forest clears
combat. It neither supplies outcomes nor changes RNG. Replays compare persisted
gameplay/preferences and the terminal observation count. No production formula,
module text, cost, RNG or battle ordering changed.

The preserved historical battle.php, battle-buffs.php, battle-skills.php and
forestoutcomes.php are the authority. The existing modernization changes to
battle.php are entrypoint rejection, scalar decoding and missing-XP defaults,
not the following combat ordering.

## Exact adverse defeat evidence

Level-10 player, attack 100, defense 50, target HP 100000. Target attack/defense
are stated below. Each row persists player HP 0, alive 0, carried gold 1000→0,
experience 1000→900, unchanged gems/turns/base stats, and cleared combat.
Exact cost is 1/2/3/5 from 9 stored uses. Duplicate and alternate stale forms
cannot repeat settlement, effects, terminal hook or consumption.

| Specialty | Target attack/defense | Starting HP | Historical result | Terminal target HP | Buff rounds after defeat |
|---|---:|---:|---|---:|---|
| DA1 skeleton | 1000/1000 | 1 | Lethal player riposte 22 before skeleton acts | 100000 | No buff; skeleton retains 43/43 HP |
| DA2 Voodoo | 120/80 | 24 | Voodoo 263, lethal riposte 24, no enemy attack | 99737 | Removed |
| DA3 Curse | 1000/1000 | 11 | Curse halves riposte 22→11 | 100000 | 5, defense phase never reached |
| DA3 Curse | 1000/80 | 89 | Weapon 40, incoming 177×0.5 rounded to 89 | 99960 | 4 |
| MP1 regeneration | 1000/80 | 167 | Heal 10, weapon 40, incoming 177 | 99960 | 4 |
| MP2 Earth Fist | 120/80 | 24 | Fist 1, lethal riposte 24 | 99999 | 4 |
| MP3 Lifetap | 1000/1000 | 22 | Lethal riposte 22, zero healing | 100000 | 4 |
| MP5 shield | 1000/80 | 177 | Weapon 40, incoming 177, reflection 354 | 99606 | 4 |
| TS1 insult | 1000/80 | 70 | Weapon 40, incoming 70 | 99960 | 4 |
| TS2 poison | 1000/80 | 177 | Weapon 88, incoming 177 | 99912 | 4 |
| TS3 hidden attack | 1000/1000 | 22 | Zero enemy attack does not suppress defensive riposte 22 | 100000 | 5, defense phase never reached |
| TS5 backstab | 1000/80 | 104 | Weapon 135, incoming 104 | 99865 | 4 |

Skeleton persists at 43/43 after the player dies. Visiting news/shades retains
its exact state and adds suspended=true. This is historical suspension, not
another death settlement. Other skeleton damage, survival and lethal removal
proofs from the preceding checkpoint remain; full companion/target-transition
accounting is still incomplete.

Wither Soul zeroes both enemy attack and defense. An ordinary single target
cannot kill this one-HP fixture in its five active rounds. Round six, after
expiration, kills the player. Do not invent active-round retaliation to force
a defeat that the historical modifiers prevent.

## Shield terminal branches and failed Lifetap

Weapon damage is 40; enemy damage is 177; shield damage is 354 **after** player
damage. Reflection does not absorb damage. No damage reflects zero (retained test).

| Player HP / enemy HP before | Exact committed result |
|---|---|
| 178 / 395 | Both alive at 1 / 1, no rewards |
| 177 / 395 | Player defeat, enemy 1 HP, no rewards, gold lost and 10% XP loss |
| 178 / 394 | Enemy exactly zero, player 1 HP, victory |
| 177 / 394 | Both exactly zero in battle, historical Forest victory restores player to 1 HP |
| 1 / 393 | Enemy -1, player clamped to zero in battle, same historical mushroom recovery |

These seeded victories award exactly +8 gold / +14 XP / +1 gem. Shield retains
4 rounds; no repeated reward or terminal event follows replay. Simultaneous
lethal is **not** silently relabeled defeat: forestvictory has the preserved
mushroom recovery for this case.

Failed Lifetap with target attack/defense 1000/1000 leaves player 500→301
(22 riposte +177 attack), target unchanged at 100000, and four rounds remaining.
Negative damage neither heals nor compounds damage. Retained tests prove positive
40+5 healing, matching target damage and the max-HP cap.

## Duration semantics

All eleven persistent specialty buff variants are exercised from creation
through stored rounds 4,3,2,1,absent, followed by another round without revival.
DA1 uses the supported companions-disabled minion fallback. Skeleton itself
has no round TTL; DA2's one-round Voodoo expiration remains separately proved.
No extra uses are consumed by continuation rounds.

The engine computes modifiers at round start, but marks a buff used only at
its activation phase. expire_buffs decrements a used buff once per round and
removes it at zero. Consequently a defense-only effect can modify a riposte
without consuming a duration round if that riposte is lethal. Final-round tests
exercise victory and reachable defeat for all eleven variants. Defense-only
DA3/TS1/TS3 retain their last round when the encounter ends before defense;
activated round-start/offense effects expire. Wither Soul's active-round ordinary
defeat remains unreachable in this fixture. Buffs have no expireafterfight flag,
so historically unconsumed duration persists after terminal settlement.

Remaining duration gaps: full defeated-target/multi-target transitions, combined
area/companion outcomes and Dragon transitions. Existing New Day-after-use tests
remain required in final CI. These tests do not authorize ordinary Forest GET.

## Narrow specialty buff schema

SpecialtyBuffState inventories all fifteen historical identifiers, including
three exhausted-use message variants that the Forest dispatcher does not expose.
Each identifier binds to its exact source specialty schema, immutable LoGD
messages, exact modifiers and allowed numeric producer fields. Start messages
may be removed by activation. Positive integer rounds cannot exceed the producer
maximum (1 or 5). Runtime used is 0/1; suspended and calculation markers are bools.
Unknown critical fields, extra modifiers, zero/negative/excessive durations,
wrong source, changed text, objects, malformed serialization and non-array roots
fail closed. Numeric producer values are finite bounded integers; regeneration
also preserves the canonical numeric string supplied by PDO. Minion pairs,
Earth Fist multiples and Voodoo damage bounds must match creation formulas.
Creation values are not rebound to today's level after legitimate stat changes.

Authenticated authoritative hydration returns controlled HTTP 409 and preserves
the malformed blob for explicit repair. Producer application and calculation
also validate before using specialty effects. All eleven persisted variants have
HTTP corruption matrices against form GET, specialty POST and ordinary combat
GET, asserting no gameplay/preference mutation or consumption. Unit tests cover
objects, excessive nesting, INF/NAN, unknown identifiers and missing fields.
Trusted historical color/substitution text is preserved exactly. Configured and
player-controlled text is not admitted as specialty-owned formatting.

This is not a validator for all unrelated buffs. ScalarState, SkeletonCompanionState
and SpecialtyCombatState remain unchanged. Serialization inventory remains
89 sites / 44 files / 32 writers-checks / 56 ScalarState reads / one unserialize.

## Acceptance and remaining gates

Local PHP 8.4.25 / disposable MariaDB 10.11.18: retained suite has 83 PHPUnit tests
and 2398 assertions. Focused HTTP results and exact-head supported-target workflow
IDs are recorded in the appended checkpoint and PR #1 after they finish.
Final full CI must run both PHP 8.4/MariaDB and PHP 8.5/MySQL, all 46 Python/HTTP
tests, 317 tracked PHP lint files, both analysis gates, Composer and historical
integrity. A passing focused run is not full acceptance.

DA, MP and TS remain independently BLOCKED. Dragon specialty/prologue/terminal
POST+CSRF+intent authority and defeated-target progression are not changed.
No dead-A/live-B/final-target certification, no 24/24 milestone, no general
combat schema/recovery/ordinary-Forest authority promotion. Next close Dragon
caller-owned authority, then dead-target transitions and remaining combined
companion/area/duration accounting before reconsidering each specialty.

New Day dk/pdk, mail send/reply/systemmail, petitions, clans, bank/economy,
weapons/armor, stables/mounts, training/masters, broader PvP, administrator/content
editors, remaining serialized schemas, expiration and account-deletion failure
semantics remain BLOCKED. lib/expire_chars.php still advances last_char_expire
too early. Historical repositories/tag and VPS untouched. No deployment, public
runtime, release, package, new branch, PR #2, merge or Phase 4.
