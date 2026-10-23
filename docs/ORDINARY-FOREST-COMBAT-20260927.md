# Ordinary Forest combat candidate, 2026-09-27

Starting SHA: `0944188301c588ff7d0ff447983b63e6b7f58049`.
Branch: `modernization/core-modernization`; PR #1 OPEN, DRAFT, NOT READY, NOT MERGED.
Main: `999cec6f9c655a840320d982d673bb863c68c2b2`.

**Ordinary Forest authority and its shared consumer schemas: local evidence PASS,
exact-candidate supported-matrix acceptance PENDING. Broader general combat BLOCKED.
Phase 3 INCOMPLETE. Merge NO. Public hosting NO.**

The candidate SHA and exact workflow IDs belong in PR #1, avoiding a self-referential
commit. Publish once, inspect the exact-head workflows once, optionally once later,
and stop if pending. No deployment, release, tag, VPS access or other subsystem work.

## Route inventory and ownership

| Shipped route | Previous ordinary behavior | Candidate behavior |
|---|---|---|
| `forest.php` without pending event/combat | Forest presentation/navigation | Retained presentation |
| `forest.php` with stored combat | Could leave ordinary combat through landing navigation | Render validated combat without advancing |
| `forest.php?op=search[&type=...]` | GET spent a turn, selected creatures/events, and could process surprise damage | GET confirmation; scoped POST owns normal search, turn, encounter identity and surprise round in one player transaction |
| `forest.php?op=fight` | GET advanced combat and outcomes | GET presentation; POST one action, CSRF and state-bound one-use intent |
| `forest.php?op=fight&auto=five/ten/full` | GET multiple-round action | GET cannot fight; typed POST `rounds` requires current autofight settings |
| `forest.php?op=run` | GET escape roll, failed escape round or successful escape cleanup | GET presentation; scoped POST, same historical chance and ordering, atomic cleanup or round |
| `forest.php?op=newtarget` / `newtarget` query | GET changed target flags | GET only renders; POST target overrides reject; engine selects the current live target |
| `forest.php?op=dragon` | GET set `seendragon` | GET confirmation; scoped POST checks eligibility and records discovery; original cave prose retained |
| Pending Forest event routes | Existing event owners | Existing owners retained; search selects and persists the event, then links to its entry, avoiding nested transactions |
| `forest.php?op=specialty` | Previously secured specialty owner | Retained; understands the new optional encounter metadata |
| `battle.php`, including PATH_INFO | Caller-only include | Still not an HTTP endpoint: authenticated/anonymous GET and POST reject |
| `battle.php`, `forestvictory`, `forestdefeat` internally | Shared engine / caller settlement | Existing formulas and phase ordering retained; ordinary caller encloses round and settlement |

The old search producer is extracted into `lib/forest_encounter.php`. Difficulty,
creature selection, multi-enemy calculation and surprise behavior remain historical.
A new normal encounter receives a random 128-bit identity. The fallback doppleganger
now explicitly records its producer-known starting HP and damage flag. It remains
recognizable without a database creature ID. Existing valid envelopes without new
identity metadata remain readable; newly generated encounters always have it.

GET does not invoke the battle engine, consume turns, settle rewards, change targets,
roll escape, or mark cave discovery. Navigation/session tokens and access metrics
are not gameplay mutation. Forged request identity, indices, statistics, rewards,
flags, skills or outcomes cannot supply authority. A current New Day must be begun
before a normal search; this change does not implement New Day allocation.

## Schemas and contexts

`ForestCombatState` is separate from `SpecialtyCombatState`. It validates the ordered
1..100 enemy list, required identity/text/statistics, nonnegative rewards, finite
flags, live versus defeated history, eligible/current targets, Forest options,
optional encounter ID and reward ledgers. Defeated ledger entries must match the
retained enemy reward and the historical per-enemy divided amount. The sole shipped
Gypsy Bandit behavior uses the existing `CreatureAi::supported` allowlist and its
finite spell-point state. Nonordinary leader/flee/ineligible-target flags and
unsupported scripts reject rather than broadening this boundary to module callers.

`ForestBuffState` validates battle-consumer field types and bounds and delegates
specialty and Transmutation producer rules to their existing validators. Permanent
racial duration is allowed. `ForestCompanionState` validates ordinary companion
consumer records/abilities and delegates Skeleton Warrior's exact creation and
runtime state to `SkeletonCompanionState`. These consumer checks do not certify
all configured buff producers, companion recruitment/editing, or other combat families.

`ScalarState::read()` is unchanged: no PHP objects, malformed serialization,
oversized graph or unexpected root becomes combat authority. Validation never
repairs corrupt statistics with abs, coercion or defaults.

Ordinary action contexts bind account identity, HP/statistics, rewards/currencies,
turns, encounter data, buffs, companions and relevant settings. Record field order
is canonicalized, while enemy and effect collection ordering remains meaningful.
Temporary buff calculation flags are excluded. The transaction locks/rechecks the
account and rechecks the same context. Tokens are consumed even on failed attempts;
a fresh form is required to retry. A form emitted by the successful POST works
immediately after database hydration, including numeric account-field types.

## Exact HTTP evidence

Six new methods in `tests/tooling/test_web_application.py`:

- `test_ordinary_forest_http_authority_and_context`: anonymous rejection; GET
  fight/run/target/automatic/landing immutability; CSRF and token rejection; forged
  target/stat/reward/terminal fields; unselected-specialty ordinary attack; exact
  HP 500 -> 323 and target 100000 -> 99960; persistent current target; duplicate and
  stale-HP rejection; property-order equivalence; restarted encounter rejection.
- `test_ordinary_forest_progression_rewards_and_rollback`: weapon kill at exactly
  zero and -1 (40 damage), no retaliation on that kill, dead A retained, B selected,
  no B damage on A's action, fresh B advances, stale A rejects, target/reward
  overrides reject. Both `instantexp` settings are tested. Final-target failure
  rolls back account/reward/combat writes and both transactional terminal hooks;
  consumed retry rejects, fresh form settles once.
- `test_ordinary_forest_defeat_and_rollback`: 40 outgoing and 177 incoming damage
  against a player at 177 HP. Final state alive 0, HP 0, gold 0, experience
  1000 -> 900, gems unchanged, empty combat/buffs/companions. Late failure restores
  the account, combat, news table and terminal-hook table. Fresh retry adds one
  news record; stale and replayed forms cannot process defeat again.
- `test_ordinary_forest_malformed_preserved_and_repair`: empty, wrong/malformed
  root, malformed list, object payload, impossible HP/flags, identity/stat/reward
  types, options, duplicate/ineligible targets, excessive enemies, malformed buff
  and companion state. GET and POST preserve gameplay state and return controlled
  rejection. Explicit fixture/operator repair restores the same valid encounter.
- `test_ordinary_forest_search_escape_and_event_handoff`: GET search does not
  mutate; POST creates a unique stored encounter and spends one turn; replay and
  stale search reject; a returned escape form works immediately; successful escape
  clears combat without rewards; late search failure restores turn/HP/encounter;
  fresh retry succeeds. Cave discovery is GET-safe and POST/replay protected;
  configured five/ten/full actions use POST. Failed escape retains the enemy at
  100000 HP and changes player HP 5000 -> 3936, with no player attack and no replay.
  Existing mounted-event discovery separately proves the real handoff.
- `test_ordinary_forest_shield_simultaneous_terminal_and_companion`: real producer
  Lightning Aura plus ordinary attack gives 40 weapon + 354 shield damage while
  the player takes lethal 177 damage. Target HP 394/393 yields zero/-1 and victory;
  HP 395 leaves a live target and defeat. Terminal hooks see player HP zero; Forest
  victory retains its historical mushroom recovery to HP one. Shield duration
  decreases once. Real regeneration and skeleton give player HP 500 -> 510,
  companion HP 33 -> 36, remaining regeneration rounds 4 -> 3. Late account failure
  rolls all of those changes back. Companion changes invalidate prior forms.

Historical rewards: by default, intermediate deaths populate the experience ledger
without paying account experience. With `instantexp`, A's 100 experience in a
2-enemy encounter pays 50 immediately, once. Gold and gems wait for final victory.
The deterministic 100/200 experience, 40/60 gold-cap fixture settles total account
experience 1000 -> 1150, gold 1000 -> 1044 and gems 10 -> 11. Reward request fields
never override the stored participants or settings. Existing reward formulas,
including their rounding, are retained. Victory and defeat clear combat only after
historical effect cleanup and caller settlement, inside the same transaction.

The battle engine, buff engine and companion ordering code are unchanged.
Simultaneous terminal precedence remains engine-owned. We claim rollback for the
account and transactional database writes, not for external logging or other
external effects. The failed intent itself is deliberately not restored.

## Recovery policy

Reject and preserve invalid combat, buffs and companions. Return a controlled
400/409 response, with no PHP/SQL detail or gameplay write. Valid state changes need
a fresh form; actual corruption needs explicit operator repair. No automatic
clearing, reinterpretation or reward grant. Tests repair through the fixture's
operator database boundary and then demonstrate normal progression. Single/multiple
live enemies, injured enemies, consistent defeated history, active effects and
valid companions remain usable. Other combat families need their own acceptance.

## Verification and remaining gates

Local runtime: PHP 8.4.26 / MariaDB 11.4.13. Final unit floor:
**91 PHPUnit tests / 2,505 assertions**. Final ordinary/authentication pass:
**7 HTTP tests, zero failures/skips**. The broader focused integration pass is
**20 HTTP tests, zero failures/skips**, including all six Mystical Powers additions,
retained simultaneous-terminal coverage, Thieving combined duration, Transmutation,
ordinary tests and mounted-event discovery. A supplemental **3-test HTTP pass** verifies authentication, retained Forest
specialty/direct-battle authority and retained effect accounting. The successful
local runs cover **22 distinct HTTP methods**; overlapping runs are not added as
distinct tests. All final reported runs have zero failures/skips.

The full suite collects **72 Python tests: 63 HTTP + 9 tooling**. All nine tooling
checks pass locally. Full supported runtime totals are not claimed until exact-head
CI finishes. Seven retained HTTP methods now obtain/submit real ordinary POST forms
(or follow the event handoff); the Mystical fixture's ordinary-fight helper also
uses POST. No retained test method or gameplay assertion was removed or weakened.

**327 PHP lint files, zero failures.** Both PHPStan gates pass, including the new
ordinary boundary in the infrastructure gate. Composer strict validation, locked
installation and audit pass; no advisories. Dependencies and workflows are unchanged.
The serialization recount is **94 sites / 47 files / 34 serialize writers-checks /
59 ScalarState reads / one centralized unserialize**, adding only ForestCombatState's
business read. Extracting the producer does not add a raw serialization site.

Historical integrity PASS: annotated tag `historical-source-1.1.2`, tag object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.

Mystical Powers Modern core run `36313854531` was observed once during initial
verification and was IN_PROGRESS. No wait, redispatch, repeated polling or separate
promotion was performed. Last recorded accepted modules remain **23/0/1**;
module candidate evidence remains **24/0/0**. A later result is not inferred.

Remaining Phase 3 blockers: other combat families (training/masters, broader PvP,
module callers), broader producer schemas, New Day dk/pdk, mail/systemmail, petitions,
clans, economy/bank, equipment, stables, editors, remaining serialized consumers,
expiration cleanup and account deletion. `last_char_expire` still advances before
cleanup completes. This execution does not change those verdicts.

Exact next task: inspect this published candidate's exact Modern core and Baseline
integrity outcomes and accept or correct this ordinary slice narrowly. If it is
accepted, select the next combat-family boundary in a separate execution. Do not
merge, host publicly or declare Phase 3 complete on this evidence.
