# CLAUDE.md

Standing rules for Claude Code sessions in this repository. Read this first. A task prompt overrides this file only where it explicitly says so.

## Project

Legend of the Green Dragon - Resurrection. A behaviorally faithful modernization of LoGD 1.1.2 DragonPrime Edition for supported modern PHP and databases.

Phase 3 is complete, merged into `main` at `0d078dff49e790339e0ab0aefdf5b35478bf6419` (PR #1, normal merge commit, 2026-10-23). No phase is active. Do not start new work until Jim picks a direction.

Preserve historical behavior. Fix security and runtime defects without redesigning gameplay. When modern behavior must differ from 1.1.2, check the historical source first and record why.

## Working branch

- Work on `main`.
- PR #1 is merged and closed.
- Keep `modernization/core-modernization` as a historical branch. Do not delete it or push to it.
- Do not create branches or PRs unless a task prompt says to.
- Never reset, rebase, squash, force-push, or rewrite published history.
- One task produces at most one coherent commit, unless the task prompt says otherwise.
- Publish with a normal non-force `git push`.

Before publishing:

1. Verify the working tree is clean except for intended changes.
2. Verify the remote `main` head matches the task's expected starting SHA. If it moved, STOP and report. Do not overwrite.

## Historical integrity

These must never change. Verify them in every task that publishes.

- Tag: `historical-source-1.1.2`
- Annotated tag object: `51cab4fbe58a234651a3177a56289b18bc152b4d`
- Source commit: `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`
- Source tree: `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`
- 417 historical files, 11 preservation commits

Use the historical tag to check original behavior. Never modify it.

## Hard exclusions

Never, in any task:

- Touch `vps1.phoenix233.com`
- Deploy, release, tag, or publicly host
- Begin Phase 4
- Perform penetration testing, network scanning, credential discovery, or investigation of external systems
- Import archive-only modules or the Cities module
- Rebalance gameplay, or rewrite in a framework (React, Vue, Laravel, Symfony)
- Re-enable a disabled surface: LoGDnet, SQL/PHP console, historical payments, source viewer, legacy recovery

Do not start a subsystem the task prompt does not name.

## Tests

- Never remove, skip, or weaken an existing test to get a pass.
- Prefer fast tests: PHPUnit, static/inventory tests, narrow helper tests.
- Add HTTP tests only when needed. Each one adds to a shard's runtime.
- A new test should fail without the fix and pass with it. Show both.
- Do not claim a result you did not run. Local results are supporting evidence only. Supported acceptance comes from CI.
- Never weaken a correct production guard to make a test pass. When a test hits a real 409 or rejection, check the fixture first. Past failures came from fixtures in invalid states, not from bad guards.
- The LoGD game day rolls over every 24/`daysperday` hours: with the default 4 and the installer's midnight epoch, at 00:00, 06:00, 12:00 and 18:00 UTC. The web test harness pins the clock (`game_epoch`) when each shard starts, so a shard has a full game day of headroom. Tests that exercise New Day or rollover move the clock explicitly and restore it. Long-running tests must read the authoritative day from the application, not cache it.
- Keep failed CI runs on record. Never present a superseded run as acceptance.

Local verification, proportionate to the change:

- Focused PHPUnit and HTTP regressions for touched areas
- PHP lint
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`)
- Composer validate, install, audit
- Historical verifier and project validator
- Repository hygiene and whitespace

Local shard runs: run locally only the shards that contain new or changed tests, through `scripts/tooling-shards.py`. Run all four only when a shared helper changes (intent, CSRF, player mutation, systemmail, combat, the shard runner itself). CI runs the full suite on both legs, and CI is the acceptance.

## CI

Supported matrix:

- PHP 8.4 / MariaDB 11.4
- PHP 8.5 / MySQL 8.4

Workflows: Modern core, Baseline integrity.

Modern core runs per leg: one `checks` job, four `http` shard jobs, and one `result` job. `scripts/tooling-shards.py` assigns every Python test to exactly one shard, and `tests/tooling/test_tooling_shards.py` fails if a test is dropped or doubled.

- Do not change the 60-minute timeout unless the task prompt says to. Never raise it to fit more tests. If a shard nears 30 minutes, refresh `tests/tooling/shard-durations.json` or add a shard.
- New tests are assigned automatically. Do not hand-edit shard lists.
- Plain `unittest discover` runs tests in name order, so a web test that needs WebPlayer must not rely on running after `test_create_login_rotate_render_logout` unless the shard runner orders it. Run local serial checks through `scripts/tooling-shards.py`.
- Do not rerun old workflows. Do not dispatch duplicates.
- After publishing, inspect each workflow once. Optionally once more later.
- Pushing while the previous Modern core run is still going cancels that run (`cancel-in-progress`). When that happens, the previous candidate is accepted through the first accepted descendant, recorded as such.
- If Modern core is still running, STOP and report CI PENDING. Do not wait for the full suite.
- Acceptance requires both matrix legs and Baseline at SUCCESS, with actual totals. Never substitute predecessor totals.
- If a job fails, record the failing test, assertion, expected vs actual, and duration from the logs.
- If the log hosts are blocked, say so and ask Jim to paste the failing step's output. Do not guess.

## Roadmap

`ROADMAP.md` is the high-level plan. When a task closes or opens a checklist item, update `ROADMAP.md` in the same commit.

## Serialized state

Inventory (94 / 48 / 39 / 54 / 1 at `5bdba80`; 91 / 44 / 38 / 52 / 1 at `c00f2d1`; recounted at the merge gate, see `docs/SERIALIZED-STATE-AUDIT.md`): 94 sites, 45 files, 38 serialize writers/checks, 55 ScalarState reads, 1 centralized unserialize, at `54e22e6` and after.

Recompute only if serialized-state source changes. Never change counts by assumption.

## Documentation

- Evidence lives in `docs/`.
- Append new evidence. Do not rewrite historical checkpoint entries.
- Update docs only when facts change.

## Local toolchain notes

- The ondrej PHP PPA is blocked. Use the GitHub-hosted PHP builds that setup-php uses.
- Composer dist downloads may need GitHub auth. Fall back to source installs.
- Local MariaDB 10.11 works as a disposable test database. Supporting evidence only.
- PHPUnit expects an empty database, as in CI. Recreate the disposable DB before a full PHPUnit run.
- Move temporary fixture/config leftovers out of the repo before lint and commit.
- Remove each local MySQL container's data volume with the container (`docker rm -f -v`). Leftover volumes filled the session disk and stopped MySQL from starting.

## Report format

End every task with a compact report:

1. Status line in caps (for example: CANDIDATE PUBLISHED, CI PENDING)
2. Starting SHA and whether it matched
3. What was found
4. Files changed, or "none"
5. Tests added or changed
6. Local verification results
7. Published SHA, if any
8. Remote branch head, PR #1 head, main
9. CI run IDs and states, if any
10. Historical integrity
11. Phase / public hosting verdicts
12. Exact next task

Then STOP.
