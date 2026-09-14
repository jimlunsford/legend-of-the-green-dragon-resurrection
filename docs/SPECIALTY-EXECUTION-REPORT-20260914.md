# Phase 3 specialty execution report, 2026-09-14

**21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED. Phase 3 incomplete; merge NO; hosting NO.**

This is the requested 100-point evidence report. [Detailed deterministic accounting and scope](SPECIALTY-COMBAT-ACCOUNTING.md). The final response and PR #1 provide the exact publication SHA and final workflow IDs, which cannot be embedded in their own immutable commit in advance. Implementation CI: Modern core 34898989358 hit the 15-minute job deadline on both targets (cancelled, no assertion failure in completed tests); full HTTP acceptance was not reached. Both passed 80 PHPUnit tests / 2,350 assertions / 315 lint files, Composer and both PHPStan gates. Baseline integrity 34898989352 SUCCESS. Final publication raises the job limit to 25 minutes without dropping checks; new exact-head acceptance is required.

| # | Requested evidence | Result |
|---|---|---|
| 1 | Repository | https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection |
| 2 | Actual starting SHA | a89b25753671cb9c070a99d83f6deefccddcbab0 |
| 3 | Ending SHA | The commit containing this report; exact SHA supplied in the final execution response and PR #1. Implementation: 426a36deaaebfb6c4f1c8a59f02ae4d92ac7202d. |
| 4 | Commits added | 2 published commits: implementation and evidence/fixture-isolation/CI-deadline correction. No published history replaced. |
| 5 | Branch | modernization/core-modernization |
| 6 | main | 999cec6f9c655a840320d982d673bb863c68c2b2 |
| 7 | PR state | PR #1 OPEN, DRAFT |
| 8 | Ready status | NOT READY |
| 9 | Merge status | NOT MERGED |
| 10 | Merge SHA | Not applicable |
| 11 | PASS count | 21 |
| 12 | PASS WITH DOCUMENTED LIMITATION count | 0 |
| 13 | BLOCKED count | 3 |
| 14 | All 24 statuses | See the complete table below; all 24 lifecycle checks retain PASS. |
| 15 | Dark Arts final status | BLOCKED |
| 16 | Dark Arts level 1 actual effect | Weak target: skeleton hit 10 + riposte 1; total player/companion damage 76. Separate lethal scenario removes skeleton. |
| 17 | Dark Arts level 2 actual effect | Voodoo deals 263 at round start from historical attack-100 bounds 150..300; expires that round. |
| 18 | Dark Arts level 3 actual effect | Incoming 177 damage becomes round(177*0.5)=89. |
| 19 | Dark Arts level 5 actual effect | Zero enemy attack/defense: player loses no HP; hit 47 + riposte 2. |
| 20 | Skeleton creation | Retained real HTTP creation; level 10 HP43/attack26.5/defense14.5. |
| 21 | Skeleton contribution | New exact hit/riposte accounting above. |
| 22 | Skeleton persistence | Retained subsequent authenticated reads and committed state. |
| 23 | Skeleton survival | Retained victory and New Day survival; new weak-target and healing-aura survival. |
| 24 | Skeleton death/removal | Retained seeded lifecycle; new lethal incoming combat removes it. |
| 25 | Skeleton Dragon cleanup | Retained victory/continuation cleanup; not authority certification. |
| 26 | Skeleton malformed state | Retained exact SkeletonCompanionState unit/HTTP rejection, no weakening. |
| 27 | Mystical Powers final status | BLOCKED |
| 28 | Regeneration accounting | +10 HP at round start; wounded cap 995 to1000; no healing after five-round expiration. |
| 29 | Aura accounting | Real skeleton HP30 to33 to36 to39 to42 to43; full player still emits healing aura; caps at max. |
| 30 | Earth Fist accounting | Single target loses1 from fist; two living targets receive1 and22 before their combat; cost2/one round decrement. |
| 31 | Lifetap accounting | Hit40 and riposte5 each heal; target loses45/player gains45; near-full and full caps verified. |
| 32 | Damage shield accounting | 177 incoming reflects354; target loses40 weapon+354 shield. Zero reflection on no incoming damage. |
| 33 | Thieving Skills final status | BLOCKED |
| 34 | TS level1 accounting | Enemy attack*0.5 changes seeded incoming177 to70, not half final damage. |
| 35 | TS level2 accounting | Attack*2 gives hit88 + riposte5; base attack100 remains stored. |
| 36 | TS level3 accounting | Enemy attack0 gives no player damage; hit40 + riposte18. |
| 37 | TS level5 accounting | Attack/defense*3 gives hit135 + riposte42; strong target instead deals104 incoming; base stats unchanged. |
| 38 | Per-level invalid authority | 3 specialties x4 levels x20 cases; fresh insufficient authority cases and level1 no-form checks. |
| 39 | Insufficient uses | All twelve levels; stored insufficient/zero and changed-state rejection, exact successful costs. |
| 40 | Malformed uses | All twelve levels: malformed, negative and excessive; prior shared cases retained. |
| 41 | Wrong/inactive specialty | All twelve level paths independently exercised. |
| 42 | Missing module/file | All twelve: uninstalled/inactive/missing file/invalid handler/missing hook/conditional hook, including existing forms. |
| 43 | Malformed combat | All twelve missing/malformed/dead/terminal cases; prior broader malformed matrix retained. |
| 44 | Stale combat | All twelve changed combat cases; live selected-target switch also rejects. |
| 45 | Terminal combat | All twelve reject dead/zero/below-zero/terminal state; actual Voodoo victory rejects old forms. |
| 46 | POST/CSRF | Existing Forest transaction retained; missing/invalid CSRF per level. Train/graveyard reject injected parameters in GET and POST. |
| 47 | Replay | All twelve retained plus new exact effect duplicates, Voodoo terminal forms, area/target stale forms. |
| 48 | Rollback | Retained per-level late account CHECK failure and fresh-form retry; availability failures preserve snapshots. |
| 49 | New Day after actual use | Retained for all three specialties; not reimplemented. |
| 50 | Dragon specialty authority | BLOCKED; no Dragon mutation authorization closure performed. |
| 51 | Dragon prologue GET | OPEN RISK: prologue1 still mutates through GET. |
| 52 | Non-Forest callers | Training/graveyard specialty injection closed; existing PvP denial retained. Dragon remains open; see inventory. |
| 53 | Multi-target progression | BLOCKED: dead-A/live-B transition not certified; narrow validator still rejects dead entries. |
| 54 | Stale multi-target rejection | Two live targets and server selection A toB invalidate old specialty forms; dead-target transition remains open. |
| 55 | Terminal behavior | Voodoo exact-zero/below-zero victory clears combat once; general terminal families remain open. |
| 56 | Reward authority | Seeded Voodoo: +14XP/+44gold/+1flawless turn/no gem; old forms cannot repeat it. General and final multi-target rewards remain open. |
| 57 | Defeat authority | BLOCKED: no complete specialty/general defeat certification; existing companion death proof is separate. |
| 58 | Buff business validation | BLOCKED; no exact specialty buff schema added. Scalar object rejection does not certify modifiers. |
| 59 | 24/24 milestone | NOT REACHED |
| 60 | General combat schema | BLOCKED; SpecialtyCombatState remains narrow Forest validation. |
| 61 | Ordinary progression | BLOCKED; comparison/expiration rounds are effect evidence only. |
| 62 | General rewards | BLOCKED |
| 63 | General defeat | BLOCKED |
| 64 | General malformed-state rejection | BLOCKED; retained ScalarState/narrow validators are not a general schema. |
| 65 | General recovery | BLOCKED; existing narrow rejection preserves malformed state for explicit repair. |
| 66 | Dragon-point authority | BLOCKED; dk/pdk unchanged. |
| 67 | Remaining GET mutations | Ordinary Forest, Dragon specialty/prologue, New Day dk/pdk, petitions, clans, equipment, stables, training, legacy editors. |
| 68 | Remaining replay risks | Unclosed caller/outcome families above, including general rewards/defeat and Dragon reset. |
| 69 | Serialization inventory | 89 sites /44 files /32 writers-checks /56 ScalarState reads /one centralized unserialize; unchanged. |
| 70 | Business validators | SkeletonCompanionState and narrow SpecialtyCombatState retained unchanged; no new validator. |
| 71 | Mail | BLOCKED: send/reply/systemmail |
| 72 | Petitions | BLOCKED: administration |
| 73 | Clans | BLOCKED |
| 74 | Bank | BLOCKED: broader bank/economy |
| 75 | Weapons/armor | BLOCKED |
| 76 | Mounts/stables | BLOCKED |
| 77 | Training/masters | BLOCKED generally; specialty injection alone closed. |
| 78 | Broader PvP | BLOCKED; existing scoped PvP evidence retained. |
| 79 | Administrator/content editors | BLOCKED |
| 80 | Expiration cleanup | BLOCKED: last_char_expire still advances before cleanup completes. |
| 81 | Account deletion | BLOCKED: failure semantics remain open. |
| 82 | PHPUnit tests | 80 per supported target; see CI acceptance below. |
| 83 | Assertions | 2350 per supported target |
| 84 | Python/HTTP tests | 41 per supported target, including six added tests |
| 85 | PHP lint | 315 files |
| 86 | Failures/skips | Final successful supported-target runs required: zero failures/zero skips. Baseline-only job intentionally omits DB tests. |
| 87 | Legacy PHPStan | Level0, seven retained baseline findings, zero new errors |
| 88 | Infrastructure PHPStan | Level6, zero errors, no baseline |
| 89 | Composer | Strict validation, locked installation and audit required PASS |
| 90 | Modern core workflow | Implementation 34898989358; final exact-publication workflow supplied in final response/PR #1. |
| 91 | Baseline workflow | Implementation 34898989352; final exact-publication workflow supplied in final response/PR #1. |
| 92 | Historical baseline | Tag historical-source-1.1.2; object 51cab4fbe58a234651a3177a56289b18bc152b4d; source bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2; tree 4013a0ccc5227e87cd7a22de00b7c322d7aa237c; 417 files / 11 commits. |
| 93 | Historical repositories | Untouched |
| 94 | VPS | Untouched |
| 95 | Deployment | None |
| 96 | Public runtime | None |
| 97 | Release | None; no version/release tag, package or ZIP. |
| 98 | Phase3 completion | INCOMPLETE |
| 99 | Modern-core merge | NO; PR stays draft. |
| 100 | Exact next step | Finish adverse riposte/defeat accounting and remaining effect-duration cases; then Dragon specialty/prologue/outcome POST/CSRF/one-use authority, dead-target progression and specialty buff schemas before independent promotions. |

## All 24 modules

| Module | Lifecycle | Final certification |
|---|---|---|
| `cedrikspotions` | PASS | PASS |
| `crazyaudrey` | PASS | PASS |
| `dag` | PASS | PASS |
| `darkhorse` | PASS | PASS |
| `drinks` | PASS | PASS |
| `fairy` | PASS | PASS |
| `findgem` | PASS | PASS |
| `findgold` | PASS | PASS |
| `foilwench` | PASS | PASS |
| `game_dice` | PASS | PASS |
| `game_fivesix` | PASS | PASS |
| `game_stones` | PASS | PASS |
| `glowingstream` | PASS | PASS |
| `goldmine` | PASS | PASS |
| `lovers` | PASS | PASS |
| `outhouse` | PASS | PASS |
| `racedwarf` | PASS | PASS |
| `raceelf` | PASS | PASS |
| `racehuman` | PASS | PASS |
| `racetroll` | PASS | PASS |
| `sethsong` | PASS | PASS |
| `specialtydarkarts` | PASS | BLOCKED |
| `specialtymysticpower` | PASS | BLOCKED |
| `specialtythiefskills` | PASS | BLOCKED |
