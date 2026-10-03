# Specialty combat accounting continuation, 2026-09-14

Starting branch SHA: `a89b25753671cb9c070a99d83f6deefccddcbab0`.
Branch: `modernization/core-modernization`. PR #1 remains open and draft.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

**21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED.**
Dark Arts, Mystical Powers and Thieving Skills each remain BLOCKED.
These are actual HTTP gameplay component proofs, not complete specialty certification.
The 24/24 milestone is not reached. Phase 3 incomplete; merge NO; hosting NO.

## Deterministic effect accounting

The loopback-only fixture uses `mt_srand(12345)` in its temporary external
auto-prepend file. No production randomness, formula, cost or module effect changed.
Unless specified otherwise the level-10 player starts at 5,000/10,000 HP,
attack 100, defense 50, no Dragon Kills, no buffs or companions, skill 15 and
9 uses. Target HP starts at 100,000, attack 120 and defense 80. Surprise is
already resolved. Each row is a real authenticated POST, committed battle round,
exact HP/cost check, subsequent read, and duplicate/replay rejection.

| Effect | Actual consumed result |
|---|---|
| DA1 skeleton, weak target attack/defense 1 | Player hit 47 + player riposte 18 + skeleton hit 10 + skeleton riposte 1 = 76 target HP lost. Skeleton remains 43/43 HP, attack 26.5, defense 14.5. Cost 1. |
| DA1 skeleton, normal target | Skeleton suffers 9 riposte + 85 incoming damage and is removed. Player's 40 hit + 5 riposte cause 45 target damage. No player HP lost. Cost 1. |
| DA1 companions-disabled fallback | Four historical minions execute; target ends at 99,985 HP, player at 491/1,000 from a 500-HP start, no companion is created; buff persists with 4 rounds. Cost 1. |
| DA2 Voodoo | Attack 100 gives historical bounds 150..300; seeded roll is 263. Target loses exactly 263 HP at round start. Player then suffers 24 riposte + 62 incoming damage. Voodoo expires in the same round. Cost 2. |
| DA3 Curse Spirit, target attack 1,000 | Unmodified incoming roll is 177; `round(177 * 0.5)` is 89. Player ends at 4,911 HP; target loses 40 HP. Cost 3. |
| DA5 Wither Soul, target attack 1,000 | Zero enemy attack and defense produce player hit 47 + riposte 2; target loses 49 HP, player loses none. Cost 5. |
| MP1 Regeneration | Player gains 10 HP before weapon combat; target loses 40 hit + 5 riposte. Cost 1. |
| MP1 aura and lifetime | Wounded player 995/1,000 heals 5; wounded real skeleton goes 30,33,36,39,42,43 HP. Aura heals even while player is full, capped at companion max HP. Buff rounds go 4,3,2,1,absent. Wounding the player after expiration does not cause more healing on the next round. No extra uses consumed. |
| MP2 Earth Fist | Seeded fist deals 1 HP at round start, then the player suffers 24 riposte + 62 incoming damage. Target ends 99,999. Cost 2. |
| MP2 area damage | Two living weak targets: fist deals 1 to A and 22 to B. Weapon hit 8 + riposte 5 against A; riposte 5 against B. Result HP A=99,986, B=99,973. One use cost of 2 and one round decrement. A remains the selected target. |
| MP3 Lifetap | Player hit 40 and successful riposte 5 each heal the player, total +45 HP; target loses the same 45. Separate 995/1,000 and full-HP scenarios cap healing at 1,000. Cost 3. |
| MP5 damage shield | With target attack 1,000, incoming damage 177 reflects 354. Player loses 177; target loses player hit 40 + shield 354 = 394. A separate no-damage/riposte case reflects zero. Cost 5. |
| TS1 Insult, target attack 1,000 | Enemy attack multiplier 0.5 changes incoming damage from 177 to 70; it does not halve final damage. Player hit remains 40. Cost 1. |
| TS2 Poison Attack | Attack multiplier 2 produces hit 88, plus riposte 5 = 93 target HP lost. Base attack remains 100 in storage. Cost 2. |
| TS3 Hidden Attack, target attack 1,000 | Zero enemy attack yields no player damage; player hit 40 + riposte 18 = 58 target HP lost. Cost 3. |
| TS5 Backstab | Attack/defense multipliers 3 produce hit 135 + riposte 42 = 177 target HP lost. Against attack 1,000 the player instead suffers 104 damage and deals 135. Base attack/defense remain 100/50. Cost 5. |

Every persistent five-round effect above has four remaining rounds after the
creation round. Accounting checks player and target HP, unchanged intermediate
rewards/base stats, exact `9 - level` uses and combat messages. Messages alone
are not the oracle. The normal Forest GET round used as an unmodified comparison
does not certify ordinary Forest HTTP authority.

Remaining accounting scope includes additional adverse interactions, complete
effect lifetimes outside the regeneration scenarios, and defeat interactions.
These component scenarios do not establish arbitrary buff combinations or all
supported combat families.

## Independent authority and module availability

`test_specialty_independent_level_authority` covers all 3 specialties x 4 levels
x 20 rejection cases: wrong/no specialty, inactive module, insufficient skill,
insufficient/zero/malformed/negative/excessive uses, missing/malformed combat,
zero/negative target HP, dead/terminal target, stale combat, missing/invalid
CSRF, unsupported level and malformed level. Each starts with a valid form,
changes the named input or authoritative state, submits, and compares the exact
gameplay and preference snapshot. Levels 2/3/5 additionally use a fresh context
with independently insufficient skill or uses. Level 1 with zero skill or uses
exposes no action intent; changed-state rejection is tested independently.
All twelve successful handlers retain separate exact-cost/replay/rollback tests.

`test_specialty_module_availability_changes` covers all twelve levels against
uninstalled module, inactive module, missing entrypoint file, invalid callback,
missing hook row and conditional hook metadata. Forms are issued before the
availability change. POST and subsequent GET fail closed without PHP fatal
pages or changed gameplay/preferences. File/registry/hook fixtures are restored.

The dispatcher now requires exactly one `apply-specialties` registration,
the selected module's callable `_dohook`, and an empty `whenactive` condition.
The same query locks the hook row inside the player transaction. This closes
the silent-skip path where a missing/conditional hook could advance combat
without applying the requested specialty. Existing onboarding is unchanged.

## Terminal and target evidence, with explicit limits

Voodoo's exact 263 damage is exercised against HP 263 (zero) and 262 (below zero).
No weapon round follows. Stored rewards yield exactly +14 experience, +44 gold,
no gem and the historical flawless +1 turn in the seeded fixture. Combat is
cleared in the transaction. Replaying the victory action or submitting a
pre-victory skeleton form changes neither rewards, uses, effects nor combat.

The two-live-target Earth Fist test proves historical area damage and a single
cost/round decrement. A form issued for target A rejects after server state
selects B. Earlier pre-round forms and duplicate actions also reject with no
snapshot change. This is **not dead-target progression certification**.
`SpecialtyCombatState` still rejects an envelope containing a dead enemy, so a
full A-dead/B-live transition and final-target reward progression remain open.

## Active shipped combat callers

| Caller | Representation and specialty result |
|---|---|
| `forest.php`, `lib/specialty_combat.php` | Enemies/options Forest envelope. The existing specialty POST transaction is retained. Ordinary fight/run/newtarget GET authority remains BLOCKED. |
| `dragon.php` | Dragon entry creates a legacy single-target array; battle produces enemies/options. `fightnav(true,false)` exposes legacy specialty links. Dragon skill/l GET authority, prologue1 reset, terminal proof and continuation replay remain BLOCKED. No Dragon route was modified here. |
| `train.php` | Master fights share battle but call `fightnav(false,false,...)`. No supported player-directed specialty UI. New early GET/POST skill/l rejection closes forged specialty entry before bootstrap or training mutation. General training eligibility, rewards, GET authority and schema remain BLOCKED. |
| `graveyard.php` | Torment shares battle with temporary soul HP/attack/defense substitutions and `fightnav(false,true,...)`. No supported specialty UI. New early GET/POST skill/l rejection closes forged specialty entry before buff clearing or other mutation. General graveyard combat remains uncertified. |
| `pvp.php` | Existing PvP transaction rejects skill/l/newtarget/type request injection and clears skill internally. No new specialty form. Existing limited funded-PvP evidence retained; broader PvP remains BLOCKED. |
| Bundled module/scripted callers | Source inventory found no additional bundled module directly including battle or invoking apply-specialties. Fairy/Foil Wench can increment skill; that is not player-directed combat use. `newday.php` and `news.php` include extended-battle helpers, not the battle dispatcher. |

The new train/graveyard test exercises all specialty/level combinations, scalar
and array parameters, GET/POST, authenticated/anonymous requests, and compares
gameplay/preferences. UI absence alone is not the rejection proof.

## Retained gates and next step

Retain onboarding, all twelve Forest persistence/rollback/New Day cases, direct
battle.php rejection and the full skeleton lifecycle/malformed-state tests.
Skeleton Dragon cleanup is lifecycle evidence only, not mutation authority.

No new serialized business validator was introduced. `ScalarState`,
`SkeletonCompanionState`, and the narrow `SpecialtyCombatState` are unchanged.
Inventory remains 89 sites / 44 files / 32 writers-checks / 56 ScalarState reads /
one centralized unserialize. Specialty buff business validation is still open.

Next: close Dragon specialty/prologue/outcome authority using caller-owned
POST/CSRF/one-use transaction semantics while preserving its single-target
representation and reset behavior; then dead-target progression, terminal/
defeat interactions and exact specialty buff schemas before any promotion.
The broader general combat schema, ordinary progression/rewards/defeat/recovery,
and New Day dk/pdk allocation have not been closed.

Remaining Phase 3 blockers include mail send/reply/systemmail, petition
administration, clans, bank/economy, weapons/armor, stables/mounts,
training/masters, broader PvP, administrator/content editors, remaining
serialized business schemas, expiration cleanup and account-deletion failure
semantics. `lib/expire_chars.php` still advances `last_char_expire` too early.
GET/replay concerns also remain in ordinary Forest, Dragon, New Day dk/pdk,
petitions, clans, equipment, stables, training and legacy editors.

Historical tag/object/source/tree and 417 files / 11 preservation commits are
unchanged. Historical repositories and the VPS were not modified. No deployment,
public runtime, release, package, version tag, Phase 4, new branch or PR #2.
Exact final SHA, workflow IDs and supported-target acceptance are recorded in
PR #1 and the appended Phase 3 checkpoint publication record.
