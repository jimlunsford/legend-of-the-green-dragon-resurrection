# Core PvP combat authority, 2026-09-27

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Branch: `modernization/core-modernization`. Actual starting head:
`cf6a349a0e3b8f7260f5dce1f5f0bb04255af567`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.
PR #1 remains OPEN, DRAFT, NOT READY, NOT MERGED.

**Core PvP combat boundary: BLOCKED pending the published candidate's exact
supported-matrix CI.** This is a bounded PvP candidate, not acceptance of all
combat or Phase 3. Local evidence and the exact publication are recorded below
and in PR #1. No graveyard or other family was developed.

## Historical authority and route inventory

Inspected the active and preserved `pvp.php`, `lib/pvpsupport.php`,
`lib/pvplist.php`, `lib/pvpwarning.php`, `battle.php`, `lib/battle-skills.php`,
`lib/extended-battle.php`, inn listing/room source and bundled race/Dag callbacks.
The preserved annotated tag is `historical-source-1.1.2`.

| Surface | Classification | Authority / behavior |
|---|---|---|
| `pvp.php` | Read-only GET | Discovery or current encounter form; no combat settlement |
| `inn.php?op=bartender&act=listupstairs` | Read-only GET | Inn victim listing and keys; uses `pvplist()` |
| `pvp.php?act=attack&name=ID[&inn=1]` | Confirmation GET, protected POST mutation | Bounded numeric selection, locked eligibility, reservation, counter and first engine round |
| `pvp.php?op=fight[&inn=1][&auto=five/ten/full]` | Confirmation GET, protected POST mutation | Stored encounter/victim, validated participants and server engine |
| `pvp.php?op=run` | Confirmation GET, protected POST mutation | Historical refusal to flee, then a normal fight round |
| PvP result | Internal settlement on winning/losing POST | No independent result mutation endpoint; forged result GET rejected |
| `battle.php` | Internal include | Direct HTTP denied; PvP entry/round transaction owns effects |
| `pvpadjust`, `pvpwarning`, `pvpwin`, `pvploss`, battle hooks | Internal callbacks | Bundled Elf/Troll opponent adjustment; immunity forfeiture; Dag only on actual victory |
| `systemmail`, news and debug helpers | Internal callbacks | PvP transaction owns in-game writes; external notification runs after commit |

No remaining GET combat mutation is introduced or retained within this PvP
boundary. Inn room rental and bartender bribes are separate, unclosed economy
operations. This record does not certify the entire inn, New Day navigation,
general mail, or the graveyard reached later by normal dead-player navigation.

Historical discovery uses levels `[attacker-1, attacker+2]`; the shipped entry
check permits an absolute difference of two. This difference is preserved.
There is no Dragon Kill *difference* rule. Dragon kills instead remove initial
immunity. Eligibility uses stored location, locked/slaydragon/alive, the
age/DK/pk/experience immunity alternatives, recent logged-in availability,
reservation timeout and the attacker's remaining daily fights. An offline flag
or an expired login timeout makes a living player available; there is no
separate sleeping boolean. The inn selector must match the stored inn location.
Stored event/combat ownership also prevents entering a second encounter.

The daily fight is consumed exactly once at entry, including entry's first
round, rather than at victory, defeat or each bodyguard round. An immune attacker
forfeits immunity by setting `pk=1`. `pvpflag` is the historical victim timestamp
reservation/protection, retained after both outcomes. It is not reset early by
this candidate. No cancellation or escape is added. The `run` action fights.

## Authority, locking and schema

Entry and rounds require authenticated live stored actor state, POST, CSRF and
one-use session intents. Context includes both accounts' gameplay state,
ordered semantic buffs/companions, the target/inn selection, relevant settings
and enabled modules. Navigation/timing bookkeeping and buff calculation flags
are excluded from action authority. A consumed token cannot be retried, even
following rollback; a fresh form is required.

The mutation helper optionally locks PvP account IDs in ascending numeric order
before its existing full actor recheck. Non-PvP callers retain the same callback
contract. PvP recomputes its action context under those locks. The opponent is
chosen once by `setup_target()`, including historical race adjustment. The
encounter retains owner, target, generation, reservation, immutable-opponent
hash and victim gameplay snapshot hash. Rounds recheck the victim snapshot,
not merely `alive` and `pvpflag`. A stale changed victim cannot be settled even
with a freshly obtained form. Explicit operator repair is required if the
original encounter can no longer be continued; no silent release or retarget
creates an advantage.

`PvpState` now rejects unknown root/options/enemy fields, multiple targets,
wrong owner/family/identity, forged immutable stats, malformed reservation or
encounter metadata, impossible numeric values, nonfinite/exponent values,
invalid flags, noncurrent targets and terminal state presented as live. HP must
be positive and at most the server-recorded starting maximum. Bodyguard level
must be an integer from 1 through 5 and agree with the recorded room tier.
The unchanged `ScalarState::read()` continues rejecting malformed/object roots.
Old encounters missing the new snapshot fields fail closed; no automatic
migration, reset or production deployment is performed.

Existing combat-consumer buff and companion validators are reused without
certifying their unrelated producers. Persisted and hydrated buff collections
are checked. The bodyguard's exact tier modifiers, PvP permission, lifetime and
allowed fields are checked against the encounter. Malformed state is preserved
and rejected; valid explicit fixture repair permits continuation.

All rewards remain server-derived. Both actors' resulting gold/experience are
checked for nonnegative signed-32-bit bounds before commit. The damage roller
has a PvP-only 1,024-attempt rejection limit for an impossible no-damage roll;
otherwise all original random draws and damage formulas remain unchanged.
Automatic PvP has a 1,000-round transaction ceiling. Exceeding either limit
rejects and rolls back, allowing the original state to be inspected or retried
with bounded manual rounds. Other combat families are unchanged.

## Actual bodyguard behavior

A bodyguard is **a buff on the attacker**, selected by the victim's stored
`boughtroomtoday`. It is not another enemy and has no separate HP, identity,
death, payout or transition to the victim. The display-name list in the inn is
flavor text; it does not select an engine opponent.

| Room tier | Victim attack modifier | Attacker defense modifier | Duration |
|---|---:|---:|---|
| 1 | 1.05 | 0.95 | -1, expires after fight |
| 2 | 1.10 | 0.90 | -1, expires after fight |
| 3 | 1.20 | 0.80 | -1, expires after fight |
| 4 | 1.30 | 0.70 | -1, expires after fight |
| 5 | 1.40 | 0.60 | -1, expires after fight |

The round's inn state is derived from the recorded opponent, not the request.
Bodyguard HP/skip/selection requests have no authority. Inn victory and attacker
defeat are normal PvP settlements with inn-specific output/news. The buff is
removed at terminal cleanup. Separate bodyguard kill/transition/transition
rollback are NOT APPLICABLE. Actual inn rounds, terminal rollback, replay and
buff cleanup are covered instead. No invented two-opponent combat was added.

## Exact settlement and HTTP evidence

Nine new HTTP methods exercise real entry/forms/engine/transactions, with
fixture-only deterministic RNG. No application test switches or substitute
settlement functions are introduced. Existing funded Dag tests are retained.

- `test_pvp_round_stale_reservation_and_victim_authority`: deterministic surviving
  round, fresh-request persistence, stale/used forms, forged payout/result,
  changed victim location/protection/HP/life/reservation/gold/experience/room,
  explicit repair and invalid result/skill/target routes.
- `test_pvp_eligibility_and_entry_rollback`: actor and target rejection matrix,
  historical level and immunity alternatives, timed-out login, daily counter,
  immunity forfeiture, reservation rollback and retry, pending event/combat.
- `test_pvp_cross_player_reservation_and_tokens`: another session cannot use the
  attacker's CSRF/intent, steal a reservation or continue copied owned combat.
- `test_pvp_victory_zero_negative_rewards_replay_and_rollback`: victim HP ends at
  exactly zero and minus one, observer sees actual engine victory, final-write
  failure rolls back all PvP DML and observer events, fresh retry settles once.
- `test_pvp_defeat_inn_bodyguard_settlement_and_rollback`: actual engine defeat
  in fields and all five inn tiers, full late rollback/retry, once-only mail/news,
  retained reservation, terminal clearing and replay rejection.
- `test_pvp_reward_rounding_level15_and_inn_victory`: level-difference rounding,
  level-15 attacker suppression, actual level-15 defender behavior, inn victory.
- `test_pvp_buffs_companions_run_and_bodyguard_authority`: bodyguard rounds,
  unsupported request overrides, corrupt bodyguard, permitted buff duration,
  suspended buff duration, skeleton exclusion and refusal-to-flee semantics.
- `test_pvp_malformed_state_preserved_and_repaired`: malformed serialization,
  objects/root types, identities, flags, stats, duplicate targets, bodyguard,
  buffs, companions and live terminal state; no implicit recovery or mutation.
- `test_pvp_autorounds_overflow_and_permitted_companion`: five/ten/full automatic
  rounds, impossible damage and long-fight bounds, currency/experience overflow
  rollback and permitted companion participation/order.

A level-5 equal-level victim with 100 gold and 1,000 experience gives the
attacker `round(50*log(100)) = 230` gold and 100 experience. Attacker
1,000/5,000 becomes 1,230/5,100. Victim becomes dead with zero gold and 950
experience; its stored 250 HP is unchanged, because historical PvP sets `alive`
and does not rewrite that HP. Attacker HP remains 500 in the fixed-minion
victory fixture, daily fights remain 9, combat clears, locations stay unchanged,
and the victim reservation timestamp remains. One in-game mail, one news row
and two debug rows are written in the no-bounty case.

With configured `pvpattlose=15` and `pvpdefgain=10`, attacker defeat changes
1,000 gold/5,000 experience to 0/4,250, HP 0 and alive false. Defender gold
100 becomes 445 (`round(50*log(1000)) = 345`), experience 1,000 becomes 1,500,
and stored HP/life/location remain unchanged. Combat clears, fight count remains
9 and the reservation remains. Inn defeat uses these same formulas, not Forest
loss semantics. No Dag `pvpwin` is called on defeat.

For victim experience 1,005, PHP rounding yields base reward 101, a level-3
victim reward of 81 or level-7 reward of 121; victim loss is 50. A level-15
attacker receives zero gold/experience. A level-15 defender receives zero
experience but still receives the historical gold reward: shipped code assigns
zero to unused `$wonamount`, leaving `$winamount` intact. This candidate does
not silently rebalance that quirk.

Mail participants/originator and news participants/outcomes are asserted from
stored records. Replay cannot repeat settlement, victim changes, rewards,
counters, mail or news. Existing Dag evidence covers mature funded payout,
own/future/closed exclusions and four late failure points. Transactional
in-game mail rolls back; external notification remains post-commit and is not
claimed to roll back or to have been delivered by these fixtures. General mail
send/reply remains BLOCKED.

## Verification, publication and remaining gates

Local runtime: PHP 8.4.26 / MariaDB 11.4.13. PHPUnit passes **93 tests /
2,555 assertions**. The final focused regression run covers **40 HTTP methods**,
including all nine new PvP methods, funded Dag, authentication and inherited
Mystical Powers, ordinary Forest, training, Dragon, Dark Arts, Thieving and
Transmutation cases. A supplemental two-method run checks the final explicit
victim-retaliation defeat with Dag enabled; its methods overlap the focused
suite and are not added to the distinct count. The suite collects **91 Python
tests: 82 HTTP + 9 tooling**. All nine tooling tests pass. **332 PHP files** lint
without failure; both PHPStan gates pass, including the new PvP helper in the
strict gate. Composer strict validation, locked install and audit pass with no
advisories. No dependencies, workflows, prior assertions or tests were removed.
The first tooling invocation lacked the runtime PATH and skipped PHP cases; the
corrected complete nine-test run passed with zero skips. Intermediate fixture
and implementation failures are not presented as final successful evidence.
The final local gates have zero failures/skips. Local PHP 8.4 evidence is not
acceptance of the PHP 8.4/MariaDB 11.4 plus PHP 8.5/MySQL 8.4 supported matrix.

The deterministic surviving normal round changes attacker HP **5000 to 4823**
and victim combat HP **10000 to 9960**, retains one target and reservation, and
keeps nine remaining fights. Tier-1 bodyguard round leaves attacker HP 500 and
victim combat HP 9981; tier 5 leaves 497 and 9983. The permitted companion fixture
acts after player attack and victim response, takes HP **1000 to 923** and ends
victim HP at **9946**. The bundled skeleton is suspended without injury, while
permitted buffs decrement and suspended buffs retain their duration.

Training Modern core `36317455788` was observed IN_PROGRESS once during initial
verification; Training Baseline `36317455823` was SUCCESS at starting head.
Neither was redispatched or repeatedly polled. Earlier cancellations are not
reported as test failures. No separate training or module closeout was attempted.
Accepted module totals remain **23 PASS / 0 limitations / 1 BLOCKED**; inherited
candidate module evidence remains **24 PASS / 0 limitations / 0 BLOCKED** until
exact supported-target regression evidence is accepted.

Historical integrity PASS: tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`,
source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.

Broader combat remains BLOCKED for graveyard, remaining module/other shipped
combat consumers and remaining serialized producer schemas. Remaining Phase 3
blockers also include New Day dk/pdk, general mail, petitions, clans,
bank/economy, equipment, stables, editors, expiration cleanup and account
deletion. Phase 3 INCOMPLETE; merge NO; public hosting NO. No main/history reset,
workflow/dependency change, release, deployment or VPS access occurred.

Next task: inspect the two exact published PvP-candidate workflow runs once.
Accept PvP only if both supported matrix jobs and Baseline succeed; otherwise
address the exact failure. Resolve inherited candidate acceptance separately.
Do not begin graveyard or another family within this execution.
