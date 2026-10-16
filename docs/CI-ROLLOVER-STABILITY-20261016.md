# CI game-day rollover stability, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `fa30beba8bf6225fc638c83d6f7c0bbb4930e295`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Predecessor runs for `fa30beb` (user editor part 1), inspected once at 13:38Z: Baseline
integrity 38907797223 SUCCESS; Modern core 38907797754 still running. Part 1 stays in
Remaining, and soul restoration and the `prefs.php` fix (`6a9a12b`) stay there too
(see [user editor authority](USER-EDITOR-AUTHORITY-20261016.md)).

Scope: the web test harness (`tests/tooling/test_web_application.py`) and the CLAUDE.md
rollover rule. No production file changes.

## The clock

`convertgametime()` (`lib/datetime.php`) is `(now - gameoffsetseconds - game_epoch) *
daysperday`, and the game day is `gmdate('Y-m-d', gametime())`. The installer stores
`game_epoch` as UTC midnight 30 days before the install and `gameoffsetseconds` as 0,
and `daysperday` defaults to 4. A game day therefore lasts six real hours and rolls over
at 00:00, 06:00, 12:00 and 18:00 UTC. CLAUDE.md said only 18:00.

## Run history

Times are UTC, from the jobs API. "Tests" is the test step for the unsharded runs and
the `http` jobs for the sharded run.

| Run | Commit | Start | Tests ended | Boundary crossed | Result | Rollover? |
|---|---|---|---|---|---|---|
| 37140769825 | `4ae45f5` | 2026-10-03 17:30:41 | 8.5: 17:59:24; 8.4: 18:00:47 | 8.4 only (18:00) | FAILURE, both legs | **No.** Both legs failed the same assertion, `test_ordinary_forest_search_escape_and_event_handoff` line 2016, `200 != 409` "Forest combat unavailable". The 8.5 leg failed at 17:59:24, before the boundary. Fixed by `175112b` ("Correct Forest search fixture onboarding state") |
| 37142838688 | `56e08e1` | 2026-10-03 18:04:27 | 8.4: 18:32:47; 8.5: 18:35:06 | None | FAILURE, both legs | **No.** Same forest assertion (8.5 log read; 8.4 log not read). The commit only raised the timeout on top of `4ae45f5` |
| 37220060804 | `6cb4835` | 2026-10-04 17:19:24 | 8.4: 18:00:16; 8.5: 18:01:34 | 18:00 | FAILURE, both legs | **Yes.** 8.5 failed at 18:01:34 on `test_ramius_rejects_live_torment_without_abandoning_it`, `200 != 409` "Ramius resurrection is unavailable. Complete any due New Day…". The 8.4 leg stopped at 18:00:16 (log not read). `7e57ed5` then refreshed that one fixture's day |
| 38758814063 | `9c2f458` | 2026-10-15 15:16 | — | — | FAILURE | Known shard-order bug; left as recorded |
| 38896842544 | `6a9a12b` | 2026-10-16 11:54:05 | 12:00:04 – 12:09:18 | 12:00 | FAILURE, all 8 `http` shards | **Yes.** The first two shards failed at 12:00:04 and 12:00:05. Shard 1: `test_lovers_alternative_choices_daily_authority_http` (`inn.php` 302, line 677); shard 2: graveyard 409 (line 1463); shard 3: "Training unavailable". The same tests fail in the local reproduction below |

The two 2026-10-03 failures sit near 18:00 but are a deterministic fixture failure, not
a rollover. 37220060804 and 38896842544 are rollovers.

## Root cause

The account-setup test gives WebPlayer a New Day, which stores the game day in
`lastnewday`. Most fixtures written before mid-October reuse that state and never
refresh it. Newer fixtures set `lastnewday` from the app (`_current_game_day()`) before
each request. When a shard crossed a boundary, every later test on an older fixture saw
a player who was owed a New Day:

- page requests redirected to New Day (302 where 200 was expected): Lovers via
  `inn.php`, the specialty accounting test via `village.php`
- combat entry refused with 409, which is correct production behavior for a due New Day:
  training ("Training unavailable") and the graveyard
- a test straddling the boundary had its form refused before the planted late failure
  (graveyard event handoff: 409 where its 500 was expected)

All three categories in the brief were checked:

- **A day cached at setup.** This is the root cause: WebPlayer's `lastnewday` from
  account setup.
- **Intents or forms bound to the day.** They appear only in the test straddling the
  boundary. The production refusal is correct.
- **New Day firing mid-test.** The 302 intercepts send the player to New Day. The tests
  do not follow the redirect, so no turns, favor, buffs or flags changed; the failures
  are the status assertions.

## Local reproduction

PHP 8.5.11 / MySQL 8.4, shard 1 of 4, run through the shard runner's own plan and order.
Failfast was off so every failure is listed. Before the shard, `game_epoch` was set so
that a boundary falls 180 seconds after start (13:46:00Z): the epoch was set to that
time minus 30 days, with `gameoffsetseconds` 0.

Before the fix (`fa30beb`): 39 tests, **35 failures in 6 tests**, all on or after the
boundary.

| Test | Original line | Assertion | Expected | Actual |
|---|---|---|---|---|
| `test_graveyard_event_handoff_is_atomic_and_does_not_consume_fight` (straddled the boundary) | 1545 → 1460 | late failure status | 500 | 409 "Graveyard combat unavailable" |
| `test_lovers_alternative_choices_daily_authority_http` (14 subtests + the test) | 677 | `inn.php` | 200 | 302 (New Day) |
| `test_specialty_gameplay_accounting` (15 subtests) | 3468 | `village.php` | 200 | 302 (New Day) |
| `test_training_buffs_companions_and_context_canonicalization` | 1650 | challenge | 200 | 409 "Training unavailable" |
| `test_training_corrupt_state_preserved_and_repaired` | 1650 | challenge | 200 | 409 |
| `test_training_eligibility_shipped_masters_and_dragon_scale` | 1698 | challenge | 200 | 409 |
| `test_training_settlement_rollback_and_defeat` | 1748 → 1650 | challenge | 200 | 409 |

After the fix, the same 39 tests in the same runner order, with a boundary forced 180
seconds after start (15:13:44Z): 39 tests in 818 s, **0 failures, 0 errors**. The pin in
`setUpClass` moved the epoch before the first web test, so the forced boundary never
reached a fixture. The guard test changes the shard assignment, so shard 1 on the new
tree holds a different set; it was also run with a forced boundary (13:59:12Z): 38 tests
in 873 s, 0 failures, 0 errors.

## Fix

The harness pins the game clock. `WebApplicationTests.setUpClass` runs once per shard
process, before any web test. It calls `_pin_game_clock()`, which runs the
application's own settings and clock code in PHP:

- sets `game_epoch` to one minute before now, minus 30 days, so it keeps the installer's
  shape and a boundary falls one minute before the shard starts
- sets `gameoffsetseconds` to 0
- reads `gametime()` back and refuses to start (RuntimeError) unless the remaining
  headroom is at least the CI job timeout (3600 s) and within five minutes of a full
  game day

Every shard now has six hours of game day ahead of it, whatever the wall clock says. A
shard's job timeout is 60 minutes. Tests read the day through `_current_game_day()`, the
app's clock, as before. Tests that exercise New Day or rollover still move
`gameoffsetseconds` explicitly and restore it; they already restored it to 0 or to the
value they saved, which matches the pin.

Why the epoch and not `gameoffsetseconds`: several tests set `gameoffsetseconds` to
86400 to force a New Day and then write back the literal 0. Pinning through the epoch
leaves the offset at 0, so those tests keep working unchanged.

Why pin once per shard and not before every test: re-basing the epoch moves the boundary
to "now". Accounts that rely on `lasthit` rather than `lastnewday` would then be owed a
New Day by every later re-pin. One pin per process keeps one game day across the whole
shard.

Why not refresh `lastnewday` in every older fixture: about thirty fixtures would change,
and each would still be exposed to a boundary between its own refresh and its request.
The pin removes the boundary from the run instead.

Guard test `test_game_clock_pinned_away_from_rollover` (shard 1):

- the pinned day is the app's current day, with at least the job timeout left
- moving the epoch so a boundary is 30 seconds away is visible (headroom ≤ 30 s)
- re-pinning restores a full day
- the exact pinned settings are restored afterwards

Without the pin it fails. Locally, with `setUpClass` reading the clock instead of pinning
it and a boundary forced 900 seconds after start, account setup passed and the guard
test failed on its headroom check: `AssertionError: 892 not greater than or equal to
3600`. With the pin restored and the same forced boundary, both tests pass.

Production behavior is unchanged: no production file is touched. No production defect at
the boundary was found. Every refusal in the reproduction is a correct guard.

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- All four shards through `scripts/tooling-shards.py`, each on a fresh database after
  PHPUnit: shard 1 38 OK (840 s), shard 2 37 OK (857 s), shard 3 37 OK (813 s), shard 4
  46 OK (894 s). 158 tests, including the shard partition test.
- PHPUnit 124/124 before each shard.
- Forced rollover before and after the fix, and the guard test with and without the pin,
  as above.
- PHP lint 365 files, 0 failures. Both PHPStan configs: no errors.
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier and project validator pass; `git diff --check` clean.

## Remaining gaps

- A run that takes longer than one game day (six hours) would still cross a boundary. The
  job timeout (60 minutes) prevents that in CI.
- Running `unittest discover` in a single process (not the shard runner) also pins once,
  in `setUpClass`, so the same bound applies.

## Candidate CI, 2026-10-16

`aeb19b93fce01a0829cdb4697bfde565524f5448` was inspected once at the start of the bans
task (15:35Z). Baseline integrity 38923797782 SUCCESS. Modern core 38923797893 was still
running: both `checks` jobs SUCCESS, all eight `http` shards in progress, no `result` job
yet. The ROADMAP item stays open.

## Acceptance, 2026-10-16

Recorded at the start of the ban message task (16:30Z). Modern core 38923797893 on
`aeb19b9` (started 15:25:20Z): all 12 jobs SUCCESS, both `checks` jobs, all eight `http`
shards, and both `result` jobs (PHP 8.4 / MariaDB 11.4 at 15:40:33Z, PHP 8.5 / MySQL 8.4
at 15:41:56Z). Baseline integrity 38923797782 SUCCESS. The ROADMAP item is closed.
