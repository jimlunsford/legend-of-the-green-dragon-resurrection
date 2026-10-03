# Phase 3 recovery and specialty evidence, 2026-09-27

## Recovered state

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`. Continue only
`modernization/core-modernization` and draft PR #1. Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`.

No Resurrection checkout survived in this execution workspace. The initial
read-only inspection therefore had no local branch, HEAD, index or working tree
to report. GitHub supplied the authoritative published history. A fresh clone
then matched remote branch and PR head at
`72ab600fdc8c959ec3240bdc5abaa72f39bd178e`, with no staged, unstaged or untracked
files. No later published commit existed at recovery. No older work was reset,
cleaned, stashed or discarded.

Every published commit after `ebcd70988478553a67d645dcffa6f05573e0338e`, in order:

| Full SHA | Subject |
|---|---|
| `e095e8c02ab8f25bd8912347ca6cb5b9f0402938` | Secure Dragon combat and kill continuation with state-bound transactions |
| `ccc7debf59d647d7ebfb1ce46109c7ccfa484b97` | Validate defeated-target progression and bind Dragon encounter identities |
| `ad899bd3e66b50016439f9b99d1d282a100538a8` | Preserve login generations through Dragon resets and record remaining certification gates |
| `ab3e1d662f0ce023e38fe43786164fd591ddaec5` | Preserve Dragon prologue after simultaneous lethal shield victory |
| `4bd0569e43567f29908434826c9d5dc23ad47460` | Canonicalize Transmutation before binding combat action contexts |
| `72ab600fdc8c959ec3240bdc5abaa72f39bd178e` | Recover accepted Phase 3 state and extend Thieving Skills combat evidence |

The first five commits are the recovered history leading to `4bd0569`.
`ad899bd` preserves `authversion`; `4bd0569` is the Transmutation correction.
`72ab600` is its immediate child, not an alternative history.

## Acceptance recovered from GitHub

| Commit | Modern core | Baseline integrity | Per supported runtime |
|---|---|---|---|
| `4bd0569e43567f29908434826c9d5dc23ad47460` | 36271747607 SUCCESS | 36271747672 SUCCESS | 88 PHPUnit tests, 2,462 assertions; 50 Python/tooling and HTTP tests; 321 PHP lint files |
| `72ab600fdc8c959ec3240bdc5abaa72f39bd178e` | 36273713605 SUCCESS | 36273713736 SUCCESS | 88 PHPUnit tests, 2,462 assertions; 52 Python/tooling and HTTP tests; 321 PHP lint files |

**72ab600 is ACCEPTED.** The old run was inspected once for this recovery, not
redispatched or repeatedly polled. It started at `2026-09-26T21:40:01Z`. PHP 8.4
with MariaDB completed successfully at `22:08:20Z`; PHP 8.5 with MySQL completed
successfully at `22:12:19Z`. The workflow's terminal update was `22:12:20Z`.
Both jobs finished inside the configured 45-minute timeout. No failed job or
test existed in that run.

Complete job logs confirm exact commit checkout, Composer strict validation,
locked installation and audit, lint, PHPUnit, legacy and infrastructure PHPStan,
historical integrity and the full Python suite. Both supported runtime jobs had
zero failures and zero skips. The separate baseline-only job intentionally
skipped the database-dependent HTTP test class and ran nine tooling tests. The
old unqualified "zero skips" wording applies to the supported runtime matrix,
not to that baseline-only job.

The 72ab600 diff contains six documentation files and 99 added test lines, with
no application or workflow changes. Its two additional tests establish:

- TS1/TS3/TS5 exact-zero and negative target-A progression, later-target victory
  and player defeat, costs, reward/loss settlement, and stale/replayed forms.
- Actual same-specialty casts of all four effects through natural expiration
  in Forest and Dragon, with exact HP, target damage, uses and duration values.
- Insult and Hidden Attack retain their round when a weapon kill precedes
  defense activation; Backstab consumes its offense round. Hidden Attack
  suppresses normal enemy attacks but does not prevent a lethal weapon riposte.

Dwarf's Markdown table row and subsection now agree with JSON: PASS. This was
a stale-document correction, not a new promotion. Counts at recovery were
21 PASS / 0 limitation / 3 BLOCKED; all 24 lifecycle checks passed.

## Permanent retained regressions

`resurrection_combat_context()` still canonicalizes validated Transmutation
state before signing. Its unit test proves persistence/hydration ordering does
not invalidate equivalent state; changed duration, attack/defense modifiers or
survival policy still invalidate the context. The original immediate potion
Dragon victory-to-prologue HTTP test passes on both recovered candidates.
No form refresh, relaxed validator or changed gameplay formula masks it.

The Dragon reset's preserve list still includes `authversion`. The retained
HTTP test compares its exact stored value before and after real prologue
completion and verifies subsequent replay rejection.

## Accepted new specialty evidence

Candidate `2bfe8fc3c7cee4a8a8ac0c3647571a318d243634` adds three HTTP tests only:

| Test | Additional obligation |
|---|---|
| `test_darkarts_injured_companion_target_transition_and_terminal` | Real-produced 30-HP skeleton carried across Voodoo's zero/negative A kill; malformed companion rejection after progression; final B victory or early player defeat; exact retained HP, costs, settlement and replay. |
| `test_mystic_combined_final_round_simultaneous_terminal` | Regeneration plus Lifetap on their final active round, then Lightning Aura; target HP 393/394/395 distinguishes negative/zero victory from defeat in Forest and Dragon, with exact healing/damage, expiration and route-specific terminal semantics. |
| `test_thieving_combined_target_terminal_rollback` | Insult and Poison carry across A; Poison's last round expires on B; early victory versus defense-phase defeat; forced final-write failure rolls back preference, account and both terminal hook rows, then a fresh intent settles exactly once. |

These twelve scenarios supplement the accepted matrices; they do not change
source, workflows, formulas or existing tests. Exact candidate
`2bfe8fc3c7cee4a8a8ac0c3647571a318d243634` is ACCEPTED by Modern core
**36285456622** and Baseline integrity **36285456697**, both terminal SUCCESS.
The complete logs for jobs **108525255420** (PHP 8.5/MySQL) and **108525255505**
(PHP 8.4/MariaDB) explicitly show all three new tests passing. Both targets pass
88 PHPUnit tests / 2,462 assertions, 55 Python/tooling and HTTP tests, 321 PHP
lint files, Composer validation/locked install/audit and both PHPStan gates,
with zero runtime-matrix failures or skips. The original Transmutation HTTP
regression and all 24 lifecycle checks remain passing.

The shell had read-only GitHub access but no push credential. The connected
GitHub tool published the identical test tree with parent 72ab600. The local
unpublished duplicate `b5fd74bf37c7870955e760637ba3dde2401e5f7c` remains in its
original checkout; it is not part of published history. A fresh active checkout
matches the published candidate. No reset, force push or history rewrite was
used to reconcile the two commit identities.

## Scope and continuation

The shipped Dragon is a single-target encounter. `DragonCombatState` explicitly
rejects an enemy map other than `[0]`. Forest multi-target progression and Dragon
victory/prologue continuation are distinct supported boundaries; no artificial
multi-target Dragon feature is required for module certification.

### Independent certification decisions

**22 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 2 BLOCKED. Lifecycle: 24 PASS.**

| Module | Decision | Evidence and remaining scope |
|---|---|---|
| Thieving Skills | PASS | All four shipped actions have independent authority/cost/replay/rollback, exact modifier and adverse accounting, individual zero/negative target progression, duration and terminal tests. The accepted 72ab600 matrix adds all four effects together through natural expiration in both callers. The new combined Insult/Poison transition test closes the documented final-active-round, terminal settlement and rollback/retry gap. |
| Dark Arts | BLOCKED | New evidence closes injured-skeleton HP carry across a Voodoo target kill, malformed state after progression, final-target victory and early player defeat with the skeleton retained. Companion combat injury/death ordering across targets and Dragon, fallback-minion transitions and remaining combined effects still need proof. |
| Mystical Powers | BLOCKED | New evidence closes final-round regeneration/Lifetap/shield combinations at exact-zero, negative and surviving target HP in both callers. Earth Fist/Lifetap/shield multi-target progression, expiration across that progression and aura/companion ordering remain unproven. |

Thieving Skills promotion is based on the complete evidence boundary, not on a
new test in isolation. Representative combined transitions plus the per-action
terminal/transition matrices and all-four-effect lifetime matrix cover the
actual shared phases; no arbitrary Cartesian product of every buff permutation
is required. Its named module scope does not certify ordinary Forest GET
mutations, general core combat, other modules or public hosting. New Day use
restoration, Dragon reset, module/file/handler availability, malformed business
state and nonexposing-caller injection regressions remain part of acceptance.

The 24/24 milestone is not claimed. General combat development is not started
under that conditional gate. Ordinary Forest authority, shared/general
settlement/recovery, New Day `dk`/`pdk`, mail/systemmail, petition administration,
clans, bank/economy, equipment, stables, training, broader PvP, editors, remaining
serialized schemas, expiration and account deletion remain Phase 3 blockers.
`last_char_expire` still advances before cleanup completes.

Phase 3 INCOMPLETE; merge NO; public hosting NO. PR #1 remains OPEN, DRAFT,
NOT READY and NOT MERGED. Historical repositories and VPS untouched. No
deployment, public runtime, release, tag, GitHub Release or Phase 4 work.

Historical integrity remains tag object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 preservation commits.

The final documentation commit requires its own exact-head terminal workflows.
PR #1 records that ending SHA and workflow IDs to avoid a self-referential
documentation/commit-SHA update cycle.
