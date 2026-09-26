# Dragon authority and defeated-target progression, 2026-09-26

Phase 3 continuation from `ebcd70988478553a67d645dcffa6f05573e0338e` on `modernization/core-modernization`, PR #1. Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. This document records implementation scope and test obligations. The final exact ending SHA, supported-target results, workflow IDs and counts are recorded in the PR #1 execution checkpoint after CI completes. An intermediate or cancelled workflow is not acceptance.

## Shipped Dragon route inventory

| Path | Previously | Current boundary |
|---|---|---|
| `dragon.php`, `?nointro=1` | Created/replaced Dragon state and could run a surprise round on GET | Read-only introduction or combat forms. `POST ?op=begin` creates a server-scaled Dragon and processes its historical initial round inside one player transaction. Existing combat cannot be silently replaced. |
| `?op=fight` | GET combat, request skill/level/auto/target | GET forms; POST ordinary combat, typed allowed auto values, CSRF, state-bound one-use intent. Request skill, target, stats and reward fields reject. |
| `?op=run` | GET tail-blocked combat | GET forms only; the server-side run transition retains the historical tail-blocked fight. No ordinary run control is offered, matching the previous `fightnav(true,false)`. |
| `?op=specialty` | Specialty GET links through fight | All twelve DA/MP/TS level 1/2/3/5 actions use the shared selected-module/handler/skill/use checks, Dragon schema, CSRF and scoped one-use POST intents. Exact cost and battle are inside the player transaction. |
| Developer combat/restart | God mode and abort/restart GET links | Explicit developer-only POST forms, with role checks repeated against locked account state. Restart creates a new encounter identity. |
| Victory | Battle flags followed by news and request-carried flawless value | Battle-derived result, victory news and a typed pending outcome persist atomically. The pending outcome contains server-derived flawless status, current kill count and the unique encounter identity. It is never accepted as live combat. |
| `?op=prologue1` | GET story, full account reset and reward; request `flawless` trusted | GET continuation form only. POST requires pending stored victory and an independent one-use intent. Story, historical reset/rewards, hooks and clearing occur in a second player transaction. Request flawless/outcome/destination values reject. |
| Defeat | Nontransactional loss and dead state | Actual battle defeat, news/debug writes, gold loss, HP/alive state and combat clearing are committed once with the round. Historical Dragon defeat does not deduct Forest XP. |
| After prologue | News / subsequent New Day | Historical news destination retained. Buffs, companions and combat clear; specialties reset through the shipped `dragonkill` hooks. Modern `authversion` survives the reset. |

The shared engine is `battle.php`, including `apply-specialties`, battle-buff and companion processing, `battle-victory` and `battle-defeat`. The Dragon caller owns the round/outcome transaction. Forest retains its separate caller and Forest reward/defeat calculations. Dragon does not call Forest settlement. There is no public Dragon include endpoint; the legacy direct `battle.php` denial remains.

The retained Dragon hooks are `buffdragon`, `fightoptions`, battle hooks, `hprecalc`, `dk-preserve`, `dragonkilltext`, and `dragonkill`. Bundled health carry consumers include Fairy and Cedrik's Potions. Specialty reset consumers are all three specialty modules; Drinks and Dag also have Dragon Kill hooks. No hook implementation was changed or archive module imported.

## State and failure semantics

`DragonCombatState` accepts the actual flat legacy Dragon or its single-enemy battle envelope, checks exact fields, finite bounded statistics, the Dragon type/level, flags and live HP. Newly written combat has a cryptographically random server-owned encounter identity, retained through victory. Pending outcomes are bound to the current kill count. Foreign combat, defeated/terminal envelopes, corrupted statistics and stale identities reject. Malformed state is preserved for explicit repair, never silently reinterpreted or discarded.

`SkeletonCompanionState`, `SpecialtyBuffState` and their hydration rejection remain shared. `ScalarState::read()` is unchanged. The Dragon prologue uses the historical scaling, rewards, health carry, title, charm and specialty-reset calculations extracted into `lib/dragon_outcomes.php`. Its previous terminating health error now throws so the player transaction can roll back.

The historical reset-by-column loop also reset the newly introduced `authversion` column. This would revoke the current authenticated generation and roll back the generation counter. The reset now explicitly preserves it. Tests assert both the stored generation and subsequent replay rejection after actual completion.

A failed mutation rolls back preference writes, player/combat state, buff/companion changes and related transactional news/debug/observer rows. An attempted intent remains consumed; a fresh form can retry. Authentication/bootstrap metrics and navigation bookkeeping are not gameplay outcome state.

## Defeated targets

`SpecialtyCombatState` now accepts dead-enemy history only when HP is nonpositive, `dead` is true, and `istarget` is false. Contradictory flags, a killed-player marker, multiple live targets or an encounter without an eligible live target reject. Negative HP does not introduce a different authority path. Rewards remain in the historical engine/caller; validation adds no reward formula.

The focused HTTP progression fixture uses Thieving Skills level 2. Target A starts at 88 or 87 HP, takes exactly 88 damage, and remains at zero or -1. The server selects B. An old form cannot affect B, and injected target/stat fields reject. A fresh action against B produces the historically calculated attack and retaliation. Separate final victory and later-target defeat cases verify exact rewards/losses, unchanged A, terminal clearing, and no repeated costs or settlement. This is not certification of every specialty/companion/buff transition.

## Added test boundaries

- `DragonCombatStateTest`: legacy/envelope compatibility, malformed/foreign/terminal rejection, typed pending outcomes and kill-count binding.
- `SpecialtyCombatStateTest`: consistent dead-A/live-B state, exact zero and negative HP, contradictory flags and all-dead rejection.
- `test_dragon_specialty_authority_matrix`: twelve actions, costs, persisted effects, authenticated reads, replay/duplicates, wrong/no/inactive specialty, invalid handler, skill/use authority, malformed/zero/negative/excessive uses, invalid levels, missing/malformed/terminal/changed combat, CSRF and anonymous access.
- `test_dragon_transitions_rewards_and_rollback`: read-only GETs; entry/restart/role authority; stale combat families and kill generations; specialty and ordinary forms after victory; server-derived flawless reward; pending outcome; companion clearing; reset; authversion preservation; actual defeat; rollback after preference, companion/buff, news and final reset writes; fresh retry.
- `test_dragon_effect_accounting_duration_and_corruption`: explicit eleven effect accounting cases, persistent effect duration through expiration via ordinary Dragon POSTs, unchanged uses during ordinary rounds, and malformed specialty buffs/companions rejected before gameplay.
- `test_defeated_target_progression_exact_zero_negative_and_terminal`: TS2 zero/negative target A, server-selected B, adverse response, final victory and player defeat, exact settlement and stale/replay rejection.

The existing Dark Arts Dragon lifecycle, Fairy health-carry and Potions health-carry tests now follow generated POST forms. Their gameplay assertions remain. No existing test was removed. The workflow timeout is 45 minutes for the expanded matrices; HTTP execution stops at the first failure, while a successful run still executes the entire suite.

## Independent certification decisions

| Specialty | Decision | Remaining evidence before PASS |
|---|---|---|
| Dark Arts | BLOCKED | Full Dragon skeleton injury/death/player-defeat ordering and fallback minions; defeated-target companion HP carry, death and final settlement; all relevant buff transition and combined-effect cases. |
| Mystical Powers | BLOCKED | Complete aura/companion Dragon and multi-target ordering; Earth Fist/Lifetap/shield defeated-target progression, durations and simultaneous outcomes with other supported effects. |
| Thieving Skills | BLOCKED | Beyond TS2, complete TS1/TS3/TS5 defeated-target transition and exact combined player/enemy modifier accounting, including final-active-round and terminal combinations. |

Counts remain **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. All 24 lifecycle checks remain in the regression suite. The 24/24 milestone is not claimed; broader general combat work is not started under that conditional instruction.

Ordinary Forest GET combat/search/run/target authority, general reward/defeat/recovery, New Day `dk`/`pdk`, mail send/reply/systemmail, petition administration, clans, economy, equipment, stables, training, broader PvP, administrator/content editors, remaining serialized business schemas, expiration and account-deletion failure semantics remain blockers. `last_char_expire` still advances before cleanup. Phase 3 is INCOMPLETE; merge NO; public hosting NO. No deployment, release, public runtime, VPS operation, historical-repository change, new modernization branch, PR #2 or Phase 4 work.
