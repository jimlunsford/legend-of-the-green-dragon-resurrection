# Shipped specialty authority, Phase 3

Inspected starting source: `33a6cf447bcbda8292283d85f0c6dcc103c51d5d`.

## Shared onboarding boundary

`newday.php` orders dragon-point spending, race choice, then specialty choice. `lib/newday/setspecialty.php` delegates to `lib/specialty_onboarding.php`. Explicit specialty POST submissions are dispatched before New Day hooks or stat changes, including submissions after selection. Explicit `setspecialty` GET is rejected.

The three `choose-specialty` hooks retain their childhood descriptions and render POST forms. The server resolves DA, MP and TS only from the corresponding installed, active, present bundled module. The form intent binds the account, race, specialty, day, age, dragon state, pending event, location, alive state, available registry choices and current skill/uses. Display reads absent preference defaults without writing them.

Selection consumes the existing session action intent and requires CSRF. The existing player transaction locks/rechecks the account, registry and preference rows. It writes the exact specialty and explicitly persists default-zero or already-earned server preferences, then invokes only the selected module's story hook. Selection historically does not increment skill or restore daily uses. This matters when a legitimate game event clears the specialty without clearing earned preferences. Invalid preference state fails closed instead of being repaired from a request.

Fresh characters start at skill 0 and uses 0 on selection. The following New Day establishes the daily uses. `incrementspecialty` increases skill by one and grants a use when the new skill is divisible by three. Shipped callers include training, Fairy and Foil Wench. All three New Day hooks use `floor(skill / 3)` plus `specialtybonus` only for the selected specialty. Each of the three inspected Dragon Kill hooks resets both skill and uses to zero. These formulas are unchanged.

A late database CHECK failure after preference writes rolls back the preference and account writes. The shared transaction restores player, companion, output and base-account memory and clears preference caches on exceptions. Consumed intents stay consumed; a fresh form is required. This is not universal crash-safe exactly-once delivery.

## Combat boundary traced, still BLOCKED

`lib/fightnav.php` calls all active `fightnav-specialties` hooks. Their historical links encode `op=fight`, `skill` and `l` in GET. `battle.php` loads `accounts.badguy` through ScalarState, prepares enemies/options/companions, reads the action and calls `apply_skill()` in `lib/battle-skills.php`. That function dispatches `apply-specialties`; the module hooks read GET again, allow levels 1, 2, 3 and 5, apply an effect, then write `uses - l`.

Existing hooks check uses but do not provide the requested stored-specialty/stored-skill/current-combat/POST/CSRF/intent authority. The navigation also derives availability from uses. Adding stored-skill checks needs explicit tests for the zero-skill daily-bonus case; no implicit rebalance has been made here. Hook tests remain formula regressions only.

| Specialty | Level/cost 1 | Level/cost 2 | Level/cost 3 | Level/cost 5 |
|---|---|---|---|---|
| Dark Arts | Skeleton Warrior when companions enabled; otherwise five-round Skeleton Crew minions | One-round Voodoo, damage from player attack | Five-round Curse Spirit, damage multiplier 0.5 | Five-round Wither Soul, enemy attack/defense multipliers 0 |
| Mystical Powers | Five-round regeneration from player level, companion aura | Five-round Earth Fist, 1 through level*3 area damage | Five-round Siphon Life, lifetap 1 | Five-round Lightning Aura, damage shield 2 |
| Thieving Skills | Five-round Insult, enemy attack multiplier 0.5 | Five-round Poison Attack, attack multiplier 2 | Five-round Hidden Attack, enemy attack multiplier 0 | Five-round Backstab, attack/defense multipliers 3 |

All costs are exactly the requested supported action level. The unsuccessful-use joke buffs are historical behavior but do not meet the requested fail-closed boundary. Combat code has not been changed in this checkpoint.

## Companion representation and remaining schema work

Dark Arts level 1 calls `apply_companion('skeleton_warrior', ..., true)` in `lib/buffs.php`. The server formula supplies name, hitpoints/maxhitpoints, attack, defense, dyingtext, `abilities.fight=true`, and `ignorelimit=true`. The same named companion is replaced, not appended under arbitrary request keys. HP is `round(level*3.33)+10`; attack is `round(level/4+2)*round(level/3+2)+1.5`; defense is `floor(level/3)*ceil(level/6+2)+2.5`.

`common.php` currently loads serialized companions and retains array entries without business validation. `prepare_companions`, suspension helpers and `report_companion_move` in `lib/extended-battle.php` consume/mutate companion state. `battle.php` removes companions when their move returns false; `lib/saveuser.php` serializes the global companion array. The skeleton has no explicitly supplied lifetime counter. Do not invent a round-expiration field. A narrow validator must account for actual runtime-added fields and death/removal behavior before certification.

## Combat persistence boundary to close next

`battle.php` accepts a legacy single-creature representation and an enemies/options attack stack, then writes the resulting stack to the player. Forest constructs multi-enemy stacks; Dragon still constructs a single creature; training constructs a stack; PvP owns a separately validated encounter/reservation state and already wraps combat and outcome handling in the shared player transaction.

Forest and Dragon rewards/terminal processing occur outside the shared battle include. Therefore wrapping only `apply_skill()` cannot prove atomic combat/effect/reward/use persistence. Avoid nested transactions in PvP. Before implementing the shared combat authority, inventory fields in Forest, PvP, Dragon, training, buffs and extended battle; reject invalid stored state before prepare/target/effect logic, and bind the action to its actual route and current stored state. General combat and companion business schemas remain BLOCKED above unchanged ScalarState.

Next implementation: shared specialty combat forms and server action authorization, Dark Arts companion validation and transactional effect/use/combat persistence, with real route HTTP tests and late-write rollback. Then independently certify Mystical Powers and Thieving Skills. No module promotion or general combat safety claim follows from onboarding alone.


## Dark Arts companion business-state implementation, 2026-09-14 continuation

Starting SHA: `e70429e22d7c0c47570db998e609677c5dcfa2a2`. `SkeletonCompanionState` validates the named skeleton above unchanged ScalarState: exact required fields/text/abilities/ignorelimit, only optional boolean used/suspended, finite positive bounded statistics, current HP at most max HP, and attack/defense/max HP consistent with the historical creation formula. Creation level is recovered from max HP, preserving companions across later player training. No lifetime counter, death immunity, extra modifier, arbitrary ability or nested extension is accepted. Half-point combat statistics remain unchanged. Other named companion arrays are NOT thereby business-certified.

Common hydration rejects malformed encoded maps and skeleton state with a controlled 409 before route gameplay. It preserves the stored blob for explicit repair, with no silent deletion or normalization. The apply_companion producer/fallback also validates skeleton state. Runtime death/removal, combat victory retention, New Day retention and Dragon clearing retain their historical implementation; complete HTTP lifecycle/rollback/replay authority is still pending.

Added SkeletonCompanionStateTest and test_darkarts_companion_business_state_http. The HTTP fixture creates a real skeleton through the existing historical Forest action and checks exact cost 1 (5 to 4), formula statistics, used flag, subsequent login/read, injured/suspended states, malformed stored matrices and unchanged game state on rejected GET/POST. This does NOT certify the historical GET action, stale-form protection, general combat state, or transactional specialty use. CI acceptance pending at this implementation checkpoint. All three specialties remain BLOCKED; **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. Phase 3 incomplete, merge NO, hosting NO.


## Forest specialty transaction implementation

The next implementation adds `forest.php?op=specialty`, shared by DA/MP/TS. GET renders forms, while legacy Forest `skill`/`l` URLs reject before a round. POST requires CSRF and the existing one-use intent bound to player/combat/buff/companion/specialty preference state. The transaction rechecks installed active bundled module, selected identity, strict stored skill/uses, supported level 1/2/3/5 and live Forest state; the request supplies only the level. Existing effect hooks receive the server-resolved identity. Forest round and victory/defeat processing share the existing player transaction. Defeat gains an optional non-flushing mode so the callback can commit before page_footer. Terminal state is cleared inside this specialty transaction. Failed consumed intents require a new form.

`SpecialtyCombatState` is a narrow Forest specialty validator, NOT the general combat schema. It checks exact root/options/enemy key vocabularies, creature identity/name/weapon, bounded HP/attack/defense/level/rewards/player-start HP, finite flags, supported target and reward-map structure. It currently rejects encounters containing dead enemies, rather than claiming multi-target progression certification. Other core combat routes still need their real consumer schemas. No new field/formula/reward value is introduced.

HTTP tests added for all twelve Forest specialty actions: exact use cost and persisted combat/account read, duplicate/replay, late account-write failure after uses and fresh retry; plus stored preference, selected/inactive specialty, level, injected effects, CSRF, combat/target/stale state, anonymous and GET matrices. Exact effect/companion lifecycle/New Day after use and Dragon/other eligible route closure remain incomplete. No specialty promotion. CI pending for this implementation; retained earlier evidence is not represented as acceptance of this new tree. Phase 3 INCOMPLETE, module counts 21/0/3, merge NO, hosting NO.

The Forest validator deliberately requires diddamage because terminal Forest rewards consume it. Missing that flag must fail before any effect, rather than reaching an undefined-field warning during outcome processing. New HTTP assertions also cover companion retention across deterministic Voodoo victory, terminal replay rejection and actual New Day retention. Companion death/removal and Dragon clear still need independent real HTTP proof, as do the companions-disabled Skeleton Crew and complete wounded-player/aura effects.


Accepted Forest component at 353ba60ee0fad71fdc46669009c8a349dc4f0ecc: both supported targets, 80 PHPUnit tests / 2,350 assertions / 35 Python-HTTP tests / 315 lint files, zero failures/skips. Modern core 34892412719 and Baseline integrity 34892412674 SUCCESS. The detailed appended Phase 3 checkpoint separates verified Forest authority/cost/effect-parameter/replay/rollback/New Day evidence from remaining module and general-core gates. Final additional tests cover direct battle include rejection, seeded companion death/removal and actual Dragon win/reset clearing; exact-head acceptance is recorded in PR #1. Module counts remain 21/0/3, with 24 lifecycle PASS; Phase 3 incomplete, merge NO, hosting NO.


## 2026-09-14: consumed effects and independent authority continuation

Implementation `426a36deaaebfb6c4f1c8a59f02ae4d92ac7202d` adds six actual HTTP tests and retains onboarding, twelve Forest actions, rollback/New Day, direct battle entry and companion lifecycle regression. [Specialty combat accounting](SPECIALTY-COMBAT-ACCOUNTING.md) records exact gameplay results, authority matrices, active callers and limits.

All twelve paths independently reject wrong/no/inactive specialty, skill/use failures, malformed/stale/terminal combat and invalid CSRF/levels. All twelve reject uninstalled/inactive modules, missing files and invalid/missing/conditional handlers, including forms issued before availability changed. The dispatcher requires and locks the exact unconditional selected handler. Training and graveyard never expose specialties and now reject forged GET/POST skill/l before bootstrap.

New evidence includes actual modifiers, Lifetap hit/riposte healing, shield reflection, Voodoo zero/below-zero victory, skeleton contribution/death, fallback minions, regeneration/aura/caps/expiration, two-live-target Earth Fist, stale target selection and exact terminal rewards/replay. Remaining adverse-effect/lifetime combinations, Dragon authority, dead-target progression, defeat and specialty buff schemas remain open.

**21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED; 24 lifecycle PASS.** No promotion. General combat BLOCKED; Phase 3 incomplete; merge NO; hosting NO.

Implementation CI: Modern core 34898989358 hit the 15-minute job deadline on both targets (cancelled, no assertion failure in completed tests); full HTTP acceptance was not reached. Both passed 80 PHPUnit tests / 2,350 assertions / 315 lint files, Composer and both PHPStan gates. Baseline integrity 34898989352 SUCCESS. Final publication raises the job limit to 25 minutes without dropping checks; new exact-head acceptance is required. Final publication acceptance uses new exact-head runs recorded in PR #1.
