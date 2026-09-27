# Graveyard torment combat authority, 2026-09-27

## Boundary and starting state

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting public branch and PR #1 head: `67f981ecdd7960acc77233814536a7c76d182004`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. PR #1 remains open,
draft, not ready and not merged. One Graveyard implementation/test/evidence
candidate is authorized. Publication identity and its bounded CI observation
belong in PR #1 and the execution report, rather than a self-referencing commit.

Scope: shipped torment search, rounds, flee, soulpoints, favor, participants,
terminal settlement and invalid-state recovery. No resurrection, restoration,
haunting, New Day, wider Graveyard event certification, other combat family,
Phase 4, release, tag, deployment or VPS work.

## Historical comparison and route inventory

Compared active `graveyard.php`, `lib/graveyard/case_battle_search.php`, the shared
battle engine, companion suspension and Dark Arts producer with the preserved
`historical-source-1.1.2` behavior. Legacy combat URLs changed gameplay on GET;
the route also stripped buffs on every page. The bundled drinks
`header-graveyard` hook additionally reset drunkenness outside a transaction.

| Route | Classification after this candidate | Authority |
| --- | --- | --- |
| `graveyard.php` with no event | Read-only combat/idle GET presentation | Shows the existing Graveyard description/navigation and protected forms |
| `graveyard.php?op=search` GET | Confirmation GET | Never rolls an event/enemy, spends a fight, strips buffs or sobers the player |
| `graveyard.php?op=search` POST | Protected mutation | Locked eligible dead player, CSRF, one-use state-bound intent, server event/enemy selection |
| `graveyard.php?op=fight` GET | Read-only presentation | Displays current enemy and forms; cannot settle combat |
| `graveyard.php?op=fight` POST | Protected mutation | Bound encounter, soul state, favor, participants, prefs and relevant settings |
| `graveyard.php?op=run` GET | Read-only presentation | No flee roll or penalty |
| `graveyard.php?op=run` POST | Protected mutation | Server RNG, capped penalty or historical failed-run round |
| Default with pending `specialinc` or explicit event handler | Existing event owner | Search persists the event and links back to Graveyard, without executing a nested event transaction |
| `battle.php` | Internal caller only | Direct HTTP still returns 404 |
| Old search include | Retired internal mutation | Rejects use outside the protected wrapper |

Other Mausoleum and event routes retain their owners and are not certified here.
No normal torment gameplay GET mutation remains. Rendering may still persist
navigation/output/access bookkeeping. The narrowly scoped save guard preserves
the original buff blob during GET, including calculation metadata. Combat strips
buffs and runs the historical Graveyard header hook only inside the transaction.
The shared deferred event link now uses its caller's base route instead of a
hardcoded Forest link; Forest's existing route remains the same.

## Preserved gameplay and schema

* Dead eligibility checks both the persisted row and the hydrated account:
  alive=false and ordinary HP=0. Inconsistent alive/HP combinations fail closed.
  Search requires positive gravefights, no current combat/event and no pending
  New Day. An existing valid encounter can finish with zero remaining fights.
* A selected event consumes no gravefight and creates no normal enemy. Otherwise
  search consumes exactly one gravefight and chooses one `graveyard=1` creature
  by the historical server RNG/SQL ordering. Search still enters the shared
  battle engine immediately, including historical surprise behavior.
* For level L, shift is -1 below level 5, otherwise 0. Enemy attack is
  `9 + shift + int((L-1)*1.5)`, defense is the same base times 0.7, initial HP is
  `L*5+50`, and favor is randomly chosen from `10+round(L/3)` through
  `20+round(L/3)`. Player attack and defense temporarily become
  `10+round((L-1)*1.5)`; effective HP is soulpoints. Ordinary HP/attack/defense
  are restored in a finally block. Resulting effective HP becomes soulpoints.
* `GraveyardCombatState` owns exactly one live target, allowed options, a
  32-hex encounter identity, strict numeric/flag/text domains, scaled statistics,
  starting soul state and favor. Runtime validation checks the selected creature
  against the current Graveyard catalog. Forest gold/gem/XP reward fields are
  rejected. ScalarState is unchanged. All 55 shipped Graveyard seed creatures
  have empty/null AI; nonempty custom AI fails closed instead of being ignored.
* Soulpoints, favor and gravefights use the historical unsigned-int database
  domain, 0 through 4,294,967,295. Player level uses its unsigned-tinyint domain.
  No absolute-value conversion, loose coercion or invented default repairs
  malformed authority. Zero soulpoints at fresh search produces the historical
  defeat; persisted terminal combat cannot masquerade as a live continuation.
* Victory adds server-owned creatureexp to deathpower, never ordinary experience.
  Overflow rejects the whole mutation. Defeat preserves ordinary HP and dead
  status, clamps effective HP through the shared engine, sets gravefights=0 and
  writes the historical defeat news once. Terminal combat blobs are cleared.
* Flee succeeds exactly when `e_rand(0,2)==1`. Penalty is
  `min(5+e_rand(0,L), current favor)`. Failed flee retains the historical `run`
  round ordering, with no favor penalty or second fight consumption.
* Successful flee historically returned navigation but left an orphaned live
  combat blob. That residue was a consistency defect, not resumable gameplay.
  This candidate clears it without awarding rewards or changing soulpoints,
  gravefights or participants. Replayed or stale forms cannot resume it.
* Search suspends companions without `allowinshades`. Active state rejects an
  excluded companion that has lost its suspension. Allowed companions use the
  shared ordered attack/response engine. Terminal expiry follows that engine;
  excluded companions remain suspended while the player is dead. They are not
  unconditionally restored, which would contradict the historical alive guard.
* Dark Arts' real skeleton producer contains no `allowinshades` flag. Its exact
  existing validator is reused; the skeleton is excluded without injury. This
  does not reopen Dark Arts certification or certify new companion producers.
* Skill/l injection is still rejected before bootstrap. Other client enemy,
  reward, outcome, target and automatic-round query authority is rejected.
  Configured automatic rounds are supplied only by protected forms.

## Deterministic HTTP evidence

The fixture RNG lives in an external auto-prepend file on the loopback test
server. Production accepts no seed, outcome or failure-injection parameter.
Late failures use fixture-only database constraints outside request transactions.

| Case | Exact observed/proven behavior |
| --- | --- |
| Search, level 10 | 5 to 4 fights, enemy attack 22/defense 15.4, initial HP 100, favor 13; surprise/riposte leaves enemy HP 96 and soulpoints 100 |
| Normal round | Player deals 2, enemy deals 5: enemy HP 96 to 94, soulpoints 100 to 95, ordinary HP 0, ordinary attack/defense 100/50 preserved |
| Victory at zero/negative HP | Enemy HP 2/1 becomes 0/-1; favor 100 to 113, four fights remain; terminal hook exactly once |
| Defeat | Soulpoints 1 to 0, ordinary HP 0, dead status retained, gravefights 0, favor unchanged, one news and terminal-hook record |
| Successful flee | Favor 100 to 87; starting favor 5 or 0 ends at 0; soulpoints unchanged, four fights remain, encounter cleared |
| Failed flee | Soulpoints 100 to 92, enemy remains HP 96, favor 100, four fights remain; stale/replayed intent rejected |
| Allowed companion | After player ripostes for 4, helper attacks for 11, enemy hits helper for 6; enemy HP 85, helper HP 100 to 94; helper starting at HP 1 dies and is removed |
| Excluded companion and skeleton | Suspended unchanged and never attack; remain suspended after terminal combat while player is dead |
| GET/buffs/header | Buff blob and temporary base attack preserved; drunkenness 75 remains 75 on GET; accepted search strips buffs and changes it to 0 |
| Search/event/round/flee/terminal rollback | Account, encounter, soul/favor/fights, buffs, companions, news and transactional hook writes restored where applicable; failed intent stays consumed, fresh intent retries once |
| Event handoff | `specialinc` belongs to selected event, five fights remain, no enemy/soul/favor change; deferred event isn't executed inside search |
| Bounds/recovery | Favor maximum accepted without overflow; overflowing reward rejected; corrupt combat and participant state preserved, explicit fixture/operator repair permits continuation |

Replay checks cover search rerolls, stale rounds, terminal rewards/news, both
flee results and replacement encounter identity. Eligibility covers living,
inconsistent alive/HP, active event, incompatible/corrupt combat and no fights.
Malformed cases include serialized objects, invalid serialization/roots/trailing
data, empty/multiple enemies, identity, HP, attack/defense, level, favor, flags,
options/encounter identity, companion and buff business state. Rejections are
controlled responses without trace/SQL disclosure. Recovery never declares a
win/loss, clears corruption, grants favor or consumes another fight implicitly.

## Validation and acceptance

Local runtime: PHP 8.4.26 / MariaDB 11.4.13. PHPUnit: **95 tests / 2,616
assertions PASS**. Selected HTTP regressions: **49 PASS** (including all nine
new Graveyard matrices, training, ordinary Forest, PvP, Mystical Powers, Dragon,
Dark Arts, Thieving Skills, Transmutation and specialty injection rejection).
The final Graveyard route guard additionally receives a focused rerun. Tooling:
**9 PASS**. PHP lint: **335 files / zero failures**. Both PHPStan gates, Composer
strict validation/audit, project hygiene and historical integrity: **PASS**.
Zero failures/skips in final local gates. The full CI test collection contains
**100 Python tests (91 HTTP plus 9 tooling)**; this is a collection total, not a
claim that all 91 HTTP tests ran locally. Existing tests are retained. Earlier
development failures were corrected before these final gates.

At initial verification only:

* PvP Modern core `36319758950`: IN_PROGRESS at exact head
  `67f981ecdd7960acc77233814536a7c76d182004`; Baseline `36319758964`: SUCCESS.
* Training Modern core `36317455788` and Baseline `36317455823`: SUCCESS at
  `cf6a349a0e3b8f7260f5dce1f5f0bb04255af567`. Accepted training matrix supplied
  and verified terminal: PHP 8.4.26/MariaDB 11.4.13 and PHP 8.5.11/MySQL 8.4.11,
  93 PHPUnit tests/2,531 assertions, 82 Python tests, 331 lint files, both PHPStan
  gates and Composer PASS, zero supported-matrix failures/skips. These descendant
  runs include inherited Mystical Powers and ordinary Forest regressions.

No repeated prior polling or retrospective certification rewrite. The certification
registry still records 23 accepted modules with 24-module candidate evidence;
the successful training descendant now provides inherited regression evidence.
This slice does not change module certification files.

Graveyard torment: **BLOCKED pending this candidate's exact supported-matrix CI**.
A local pass does not establish PHP 8.5/MySQL acceptance. Broader combat remains
BLOCKED for remaining module/other shipped callers and serialized schemas.
Other Phase 3 blockers include New Day dk/pdk, mail, petitions, clans, economy,
equipment, stables, editors, expiration cleanup and account deletion.
Phase 3 INCOMPLETE; merge NO; public hosting NO. Whole Graveyard is not certified.

Historical integrity remains PASS: annotated object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 preservation commits.

Next task after publication: accept this exact Graveyard candidate only after
both supported Modern core jobs and Baseline succeed, or inspect and correct its
specific failure. Do not begin another subsystem in this execution.
