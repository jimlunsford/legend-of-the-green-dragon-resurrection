# Serialized business schemas, part 4: general combat state

Date: 2026-10-22. Starting head: `49baab9`. Scope: items 6, 7 and 8 of the split in
[part 1](SERIALIZED-SCHEMAS-PART1-20261017.md), the two "General combat" lines of
`ROADMAP.md`. News arguments, the other business state, the mercenary camp routes and every
combat formula, outcome, text and reward are not touched.

Each item was checked against the source at `49baab9` before the change; all three held as
written in part 1, and each was then proved over HTTP (below).

## Item 6: the Forest contract on the Forest-specialty route

`forest.php?op=specialty` reads the same stored value as the ordinary Forest route: a Forest
fight in `accounts.badguy`. At `49baab9` it had its own schema, `SpecialtyCombatState`, while
the ordinary route used `ForestCombatState`. Compared key by key:

| Check | `SpecialtyCombatState` (removed) | `ForestCombatState` |
|---|---|---|
| Enemies are a list (`array_is_list`) | no | yes |
| Creature AI script must be a supported one (`CreatureAi::supported`) | no | yes |
| `cannotbetarget`, `essentialleader`, `fleesifalone`, `alwaysattacks` refused | no | yes |
| Reward ledgers (`experience`, `experiencegained`) consistent with a defeated enemy | no | yes |
| Missing `creatureid` allowed for 1.1.2's doppleganger ("An evil doppleganger of ...") | no, refused | yes |
| `spellpoints` (0, 1, '0', '1'), added by the Gypsy Bandit's AI | no, refused as an unexpected field | yes |

So the specialty route was looser than the Forest in four ways and stricter in the two 1.1.2
cases. `lib/specialty_combat.php` now reads the Forest value with `ForestCombatState::read`
(Dragon keeps `DragonCombatState`), and `src/Game/SpecialtyCombatState.php` is removed. No
check was relaxed for the ordinary Forest. `tests/unit/SpecialtyCombatStateTest.php` keeps its
cases against the Forest contract; its one case that relied on the looser schema (a reward
ledger for a live enemy) is now asserted as refused, with a comment.

Proved over HTTP before the fix (`test_forest_specialty_reads_forest_contract_and_rechecks_round`):

- **Doppleganger**, produced by the real Forest search with no creature in range
  (`lib/forest_encounter.php:126`, as 1.1.2's `forest.php:175`): the specialty page gave
  **409 "Specialty combat is unavailable"** on GET, so a player fighting a doppleganger could not
  use a specialty at all. 1.1.2 allowed it: specialties were `forest.php?op=fight&skill=..`
  on the same stored fight.
- **Gypsy Bandit's `spellpoints`**: the same 409 on the specialty page once the bandit's AI
  had run a round.

Both now give 200, the specialty round runs, and the value keeps its 1.1.2 fields (no
`creatureid`; `spellpoints` 1).

### Also fixed: a Forest search that picks the Gypsy Bandit gave a 500

Found while building the bandit test from the real search. `ForestCombatState::read` calls
`CreatureAi::supported()` for a creature with an AI script, but nothing on the Forest route
loaded `src/Game/CreatureAi.php` before `battle.php` does (`lib/extended-battle.php:536`);
there is no autoloader in the application. The search reads the new encounter before its
first round (`lib/forest_combat.php:131`), so at `49baab9` a search that picked the installer's
Gypsy Bandit (creature 320, the only seeded creature with an AI) failed with **500 "Forest
action was not completed"**, rolled back; a stored bandit fight would give the same 500 on
every Forest page. `ForestCombatState.php` now requires its two dependencies itself. Proved by
the revert of that file alone (R8 below). No AI behavior changed.

## Item 7: the post-round re-check on Dragon and Forest specialty

Forest, Training, Graveyard and PvP read the state `battle.php` wrote, inside the transaction,
before it is committed. At `49baab9` Dragon and Forest specialty did not:

- Forest specialty called `resurrection_combat_forms()` after the commit, which catches the
  refusal silently: **200, the refused value committed**.
- Dragon called `resurrection_dragon_authority('fight')` from `resurrection_dragon_forms()`
  after the commit: **409, but the refused value already written** (the round's damage, the
  consumed specialty use and the stored fight all committed).

Now, for a fight that did not end, Forest specialty calls the ordinary Forest round's helper
`resurrection_forest_state()` (`lib/specialty_combat.php:109`) and Dragon calls
`resurrection_dragon_authority('fight')` (`lib/dragon_combat.php:107`), both inside the
transaction. A refusal throws, everything rolls back, and the response is the route's own
409 (the same text as any other refusal on that route). Victory and defeat clear the fight as
before. No helper is new.

The malformed value in the tests is real: a module's `fightoptions` hook (`prepare_fight()`,
`lib/extended-battle.php:52`) can add any option, and `battle.php` stores the options with the
round. A fixture module adding one unknown option makes the post-round state fail the route's
schema. With it, Dragon fight, Dragon specialty, Forest specialty and the ordinary Forest round
(already checked) all give 409 with the account and preferences unchanged; without it, a fresh
form continues.

## Item 8: 1.1.2 in-flight Training and Graveyard states

### What 1.1.2 stored

- Training (`train.php:93-110` at `historical-source-1.1.2`): the master row
  (`SELECT * FROM masters`, so PHP 5 text values) with the boosts added
  (attack and defense each at most `round(budget*.25)`, health `+(budget-atk-def)*5`), and
  `options => [type => 'train']`. The round runs in the same request, so a stored fight also
  has `prepare_fight()`'s `maxattacks`, `didsurprise`, and `autosettarget()`'s `dead` and
  `istarget` (`diddamage` once the player is hurt). No `traininglevel`, `encounter`,
  `trainingmaxhp` or `playerstarthp`.
- Graveyard (`lib/graveyard/case_battle_search.php` at the tag): the whole creatures row
  (`SELECT *`: `creaturegold`, `oldcreatureexp`, `creatureaiscript`, `createdby`, `forest`,
  `graveyard` included), the torment statistics (attack `9+shift+(int)((level-1)*1.5)`, defense
  that value `*.7`, health `level*5+50`, experience `e_rand(10+round(level/3),20+round(level/3))`),
  `creaturelevel` copied from the session (text), and `options => [type => 'graveyard']`, plus
  the same first-round fields. No `encounter` or `playerstarthp`.

At `49baab9` both were refused (409, nothing written) on every read, before and after the
round. Because a player in a fight may only follow the fight's links, a refused fight locked
the player out of every page: any other page sends them back to the fight page, which gave the
409.

### Decision: upgrade on first read, both routes

PvP records a fail-closed exception (`PVP-COMBAT-AUTHORITY-20260927.md:81`), because a PvP
fight snapshots another player's account and that snapshot cannot be rebuilt. Training and
Graveyard need no such exception: every field the current shape adds can be derived from the
stored value and the player, and the derivation is deterministic, so every read of the same
stored value gives the same state and the same form context (a GET still writes nothing).

`TrainingCombatState::legacy()` and `GraveyardCombatState::legacy()` recognize only the exact
1.1.2 shape (the 1.1.2 option keys, one enemy, no current-only fields) and return it in the
current shape:

| Field | Training | Graveyard |
|---|---|---|
| `encounter` | `md5` of the stored bytes | `md5` of the stored bytes |
| level | `traininglevel` = the player's level | `creaturelevel` text `'10'` becomes `10` |
| maximum | `trainingmaxhp` = master health + (budget - stored boosts) * 5, 1.1.2's formula | none (the bound is `level*5+50`) |
| start | `playerstarthp` = the player's hit points | `playerstarthp` = the player's soul points, as an integer |
| dropped | none | `creaturegold`, `oldcreatureexp`, `createdby`, `forest`, `graveyard`, an empty `creatureaiscript` |
| first-round flags | `dead` false, `istarget` true, `diddamage` 0 when absent | the same |

Neither 1.1.2 producer recorded a starting value, and nothing in either route reads it; it
exists for the current shape's bound. The upgraded state must then pass the full `read()`, so
nothing is relaxed: boosts above the budget, health above the maximum, a changed master, an
extra key, a non-empty AI script, a wrong level or statistic are refused as before.

The round writes the upgraded state into the session before `battle.php` runs
(`lib/training_combat.php:142`, `lib/graveyard_combat.php:101`; for a current state this
re-serializes the same bytes), so the fight is upgraded once, inside the round's transaction,
and the post-round check then reads the current shape.

### What happens to the player

- **A 1.1.2 Training fight:** the training page and the fight page show the fight as for any
  fight (nothing written); the next round upgrades and continues it; the fight ends as any
  training fight (a win advances the level, a loss is the usual defeat). Proved: continues
  over two rounds with the same encounter, and completes with the level advanced.
- **A 1.1.2 torment:** the same on `graveyard.php`; a win gives the usual favor, a loss the
  usual defeat. Proved likewise.
- **A 1.1.2 value that fails the checks once upgraded** (for example boosts larger than the
  player's budget allows, or a torment creature with an AI script, which 1.1.2's graveyard
  search never refused): refused like any malformed stored fight, 409 with nothing written.
  The player stays on the fight page until an operator repairs the stored fight. That repair
  path is the existing "Admin repair path" item of the `ROADMAP.md` Final rescan, extended in
  this commit to name the stored fight.

## 1.1.2 comparison

| Case | 1.1.2 | `49baab9` | Now |
|---|---|---|---|
| Specialty against a doppleganger | allowed | 409 | allowed |
| Specialty against the Gypsy Bandit after its AI ran | allowed | 409 | allowed |
| Forest search that picks the Gypsy Bandit | the fight | 500, rolled back | the fight |
| Specialty round whose stored result fails the Forest contract | no check | 200, committed | 409, nothing written |
| Dragon round whose stored result fails the Dragon contract | no check | 409, committed | 409, nothing written |
| In-flight 1.1.2 Training fight | continues | 409, locked out | upgraded, continues |
| In-flight 1.1.2 torment | continues | 409, locked out | upgraded, continues |

## Inventory

Recount (working tree, same token method; it reproduces 93 / 49 / 37 / 55 / 1 at `49baab9`):
**94 sites / 48 files / 39 serialize writers-checks / 54 ScalarState reads / one centralized
unserialize**. Removed: the read in `src/Game/SpecialtyCombatState.php` (file removed). Added:
the upgrade write-backs `lib/training_combat.php:142` and `lib/graveyard_combat.php:101`. Per-site
status is in `SERIALIZED-STATE-AUDIT.md`, "General combat state, part 4 candidate".

## Tests

PHPUnit (fast):

- `TrainingCombatStateTest::testLegacyInFlightFightIsUpgradedOnRead`: the 1.1.2 shape upgraded
  (maximum 17 from budget 1, start HP, level, `md5` encounter), the same on a second read,
  idempotent on the upgraded value, `diddamage` defaulted; 8 malformed enemy changes and 3
  malformed option sets refused.
- `GraveyardCombatStateTest::testLegacyInFlightTormentIsUpgradedOnRead`: the full 1.1.2 row
  upgraded (dropped columns, integer level, start soul points, also from text soul points),
  deterministic and idempotent; 8 malformed enemy changes and bad options refused.
- `SpecialtyCombatStateTest`: now against `ForestCombatState`; the live-enemy ledger case refused.

HTTP (`tests/tooling/test_web_application.py`):

- `test_forest_specialty_reads_forest_contract_and_rechecks_round`: a Forest-specialty fight
  that completes (victory, experience, one use spent, replay refused); the doppleganger from
  the real search; the Gypsy Bandit from the real search with `spellpoints` 1, then the
  specialty and an ordinary round; a malformed post-round state on Forest specialty and on the
  ordinary Forest round refused with nothing written, then a fresh form continues.
- `test_dragon_rounds_recheck_stored_state`: the same malformed post-round state on Dragon
  fight and Dragon specialty, 409 with nothing written; then each continues.
- `test_training_112_inflight_fight_is_upgraded_and_continues`: GET writes nothing; two rounds
  continue with the derived encounter; a low-health 1.1.2 fight completes (level 11); 4
  malformed 1.1.2 values refused with nothing written.
- `test_graveyard_112_inflight_torment_is_upgraded_and_continues`: the same for a torment
  (favor earned on the win); 5 malformed 1.1.2 values refused with nothing written, also with a
  form taken on a live torment.

### Before the fix

Each revert is applied to a copy of the final tree, tests unchanged, fresh MySQL 8.4 database.

| Revert | What is reverted | Result |
|---|---|---|
| R0 | every production file at `49baab9` (`lib/`, `src/`) | PHPUnit: the two upgrade tests error. HTTP, 9 failures: doppleganger specialty page 409; bandit search 500; Forest-specialty malformed round 200 and committed; Dragon fight and Dragon specialty malformed round 409 but written (the specialty also spent a use, `uses` 9 to 8); Training "continues" and "completes" 409; Graveyard "continues" and "completes" 409 |
| R1 | `lib/specialty_combat.php` and `SpecialtyCombatState.php` at `49baab9` | doppleganger 409, bandit specialty page 409, malformed round 200 and committed |
| R2 | only the new post-round re-check in `lib/specialty_combat.php` | Forest-specialty malformed round 200 and committed; the rest pass |
| R3 | `lib/dragon_combat.php` at `49baab9` | Dragon fight and Dragon specialty: 409 with the state written |
| R4 | `src/Game/TrainingCombatState.php` at `49baab9` | PHPUnit: the Training upgrade test errors. HTTP: Training "continues" and "completes" 409 |
| R5 | only the Training write-back (`lib/training_combat.php:142`) | the round answers 200 but stores the 1.1.2 shape again (`traininglevel` missing), so the fight is never upgraded and its encounter changes every round |
| R6 | `src/Game/GraveyardCombatState.php` at `49baab9` | PHPUnit: the Graveyard upgrade test errors. HTTP: Graveyard "continues" and "completes" 409 |
| R7 | only the Graveyard write-back (`lib/graveyard_combat.php:101`) | as R5 (`encounter` missing after the round) |
| R8 | `src/Game/ForestCombatState.php` at `49baab9` (no dependency loading) | the Forest search that picks the Gypsy Bandit: 500 |
| none | final tree | all four tests pass; PHPUnit 194 tests pass |

The ordinary Forest round's malformed-round case passes in every revert: it already had the
re-check, and is the control. The 1.1.2 values the upgrade refuses give 409 in every revert,
as before.

## Local verification

Supporting evidence only (PHP 8.5, MySQL 8.4 in Docker, disposable databases); CI is the
acceptance.

- PHPUnit: 194 tests, 139184 assertions, OK.
- The four new HTTP tests: OK on the final tree, each failing under the reverts above.
- All four shards through `scripts/tooling-shards.py` (combat is a shared path), each copy with
  its own fresh database: shard 1 52 tests, shard 2 43, shard 3 42, shard 4 43, all OK
  (about 20 minutes each, in parallel).
- `composer validate --strict`, `composer audit` (no advisories), `composer lint` (380 files, 0
  failures).
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors.
- Historical verifier PASS (417 files, 11 commits, tag object, commit and tree unchanged);
  project validator PASS; `tests/tooling/test_tooling_shards.py` OK; `git diff --check` clean.

## Remaining gaps

- The admin repair path (Final rescan) now also covers a stored fight that fails its route's
  schema, including a 1.1.2 fight the upgrade cannot accept. Until it exists, such a player
  needs an operator to clear `accounts.badguy`.
- The general combat category is closed by this candidate except where the audit says
  otherwise (see `SERIALIZED-STATE-AUDIT.md`). News arguments, mail and the other business
  state remain, as in `ROADMAP.md`.
