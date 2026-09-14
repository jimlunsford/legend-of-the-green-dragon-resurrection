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
