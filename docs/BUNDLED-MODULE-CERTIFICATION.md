# Bundled module certification

**Final counts: 10 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 14 BLOCKED. Lifecycle/metadata: 24 PASS. Modern-core merge: NOT READY. Public hosting: NO.**

This 2026-09-14 continuation updates the original all-BLOCKED checkpoint using new mutation evidence. The earlier Phase 3 history remains in [the original checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE3.md). Only the 24 shipped entrypoints are in scope. No archive module was imported. No module is promoted merely because a route renders.

PASS covers the named module in supported bundled use, not arbitrary core/editor routes or public hosting. Find Gold reads server settings, and the shared configuration boundary is now secured; broader administrator editors remain core merge blockers. Optional Cities integration, legacy-column migration and destructive uninstall recovery are outside this fresh-install bundled milestone. A nonsecurity limitation is not invented to disguise a security blocker.

| Module | Version | Lifecycle | Final certification |
|---|---|---|---|
| `cedrikspotions` | 2.6 | PASS | PASS |
| `crazyaudrey` | 1.1 | PASS | BLOCKED |
| `dag` | 1.3 | PASS | PASS |
| `darkhorse` | 1.1 | PASS | PASS |
| `drinks` | 1.1 | PASS | PASS |
| `fairy` | 1.1 | PASS | BLOCKED |
| `findgem` | 1.1 | PASS | PASS |
| `findgold` | 1.1 | PASS | PASS |
| `foilwench` | 1.1 | PASS | PASS |
| `game_dice` | 1.1 | PASS | PASS |
| `game_fivesix` | 1.7 | PASS | PASS |
| `game_stones` | 1.1 | PASS | PASS |
| `glowingstream` | 1.1 | PASS | BLOCKED |
| `goldmine` | 1.0 | PASS | BLOCKED |
| `lovers` | 1.0 | PASS | BLOCKED |
| `outhouse` | 2.0 | PASS | BLOCKED |
| `racedwarf` | 1.1 | PASS | BLOCKED |
| `raceelf` | 1.0 | PASS | BLOCKED |
| `racehuman` | 1.0 | PASS | BLOCKED |
| `racetroll` | 1.0 | PASS | BLOCKED |
| `sethsong` | 1.1 | PASS | BLOCKED |
| `specialtydarkarts` | 1.1 | PASS | BLOCKED |
| `specialtymysticpower` | 1.0 | PASS | BLOCKED |
| `specialtythiefskills` | 1.0 | PASS | BLOCKED |

## Shared evidence

All 24 install, activate, deactivate, reactivate, reinstall and round-trip named setting/preference values using the real API; registrations, dependency enforcement and cache invalidation are tested. All 24 run together. Race/specialty hooks and active daily-hook failure/retry/concurrency tests remain passing. These facts do not certify their untested HTTP mutation boundaries.

The shared player transaction locks/rechecks the actor, binds changed fields, includes related DML, rolls back invalid currency and restores in-memory state on failure. The current-event contract adds POST/CSRF, a session event generation, consumed intent and persisted completion to seven Forest modules. Outhouse additionally persists the paid/free visit stage. Stories, reward ranges, price formulas and probabilities remain historical except documented invalid-state corrections in the continuation checkpoint.

Real HTTP evidence covers Dag placement, Drinks purchase/editor save, all five Cedrik effects, full Stones choose/bet/draw/settle, seven Forest event routes, Outhouse paid/free/wash, Seth song consumption and forged player-preference namespace rejection. See [route security](CORE-ROUTE-SECURITY.md), [serialized state](SERIALIZED-STATE-AUDIT.md) and [continuation results](MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md).

## Per-module evidence and remaining gates

### cedrikspotions (2.6): PASS

PASS in supported bundled scope: real HTTP purchases, exact configured charges, typed effect values and Transmutation business schema; account reload/fresh login; cumulative rounds retain original modifiers/carry flag; actual New Day carry/reset; actual Forest combat consumes one round and expires; actual Dragon victory/reset strips sickness and retains/removes Vitality HP according to carrydk. Malformed/missing/stale state and duplicate purchase coverage; final-account-write failure rolls back the preceding debug log and exact player snapshot, then a fresh intent retries. Shared settings role/CSRF/replay/bounds/ranges and stored configuration behavior are covered. Core onboarding/combat/Dragon reset authorization remains a separate BLOCKED family.

- Previous certification: BLOCKED. Current route component: PASS in the named scope.
- Prior checkpoint evidence remains in the appended Phase 3 record.

### crazyaudrey (1.1): BLOCKED

Shared Forest event POST/CSRF/current-state/replay path passes. Direct Village paid pet/play route is still a GET mutation without the shared transaction/action contract.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### dag (1.3): PASS

PASS: real funded PvP win derives actor, target and payout from locked server state; mature open bounties pay once, own/delayed/closed rows do not pay. Four actual CHECK-constraint failures at bounty close, bounty news, in-game mail and final winner credit roll back account/victim/bounty/news/debug/mail state. Fresh intent retries preserved combat safely. Anonymous/ordinary/insufficient/authorized administrator place/close/cleanup/list HTTP matrix, malformed/deleted IDs, invalid targets/amounts, GET/CSRF/replay and literal UTF-8/apostrophe/backslash/LIKE-symbol searches pass. General core settings, account deletion and remaining PvP defeat/inn/combat gates remain separate core blockers.

- Component route result: PASS.

- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### darkhorse (1.1): PASS

PASS in supported bundled scope: mounted entry derives the account mount and declared findtavern preference from server state and uses POST/CSRF/one-use intents; GET does not enter. No mount, invalid/disabled preference, stale/removed mount and replay are tested. Actual Forest discovery and persisted event dispatch are exercised without a mount; forged developer event-handler input cannot select an event for a normal user. Exit confirms on GET and requires POST; replay cannot re-enter or pay. Existing wager/abandonment/information tests remain passing. Shared typed tavern settings and declared mount preferences, including cross-object/module attempts, bounds, stale/deleted objects, output and transaction rollback, are covered.

- Previous certification: BLOCKED. Current route component: PASS in the named scope.
- Prior checkpoint evidence remains in the appended Phase 3 record.

### drinks (1.1): PASS

PASS: protected active purchase, server price, configured hardlimit/maxdrunk boundaries, bounded drunkenness and actual New Day reset pass. Real editor create/save/activate/deactivate/delete passes for SU_EDIT_USERS and legitimately stored canedit; anonymous/ordinary/insufficient/forged/revoked editors are denied. Typed extreme values round-trip; out-of-range, arrays, unknown fields, invalid IDs, GET, missing CSRF and duplicates are rejected. UTF-8/apostrophe/backslash and markup storage/rendering pass. General configuration and administrator preference assignment remain separately tracked core gates.

- Component route result: PASS.

- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### fairy (1.1): BLOCKED

Current Forest event POST/CSRF and one-time give action pass; scalar-only state and transactional player/pref writes. Remaining: all HP/pref/specialty/no-gem result branches with configured values and shared settings-editor closure.

- Component route result: PARTIAL.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### findgem (1.1): PASS

Simple supported Forest reward passes actual GET/no-effect, POST/CSRF, exactly one gem, persisted completion and replay rejection. Active-module/current-event authorization, bound transactional writes and currency bounds apply. No direct mutation, user setting, object preference or serialized state. Optional Cities travel integration is outside this bundled scope.

- Component route result: PASS.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### findgold (1.1): PASS

Supported Forest reward passes actual GET/no-effect, POST/CSRF, level-scaled historical reward range, persisted completion and no repeat reward. Server settings supply amounts; bound transactional writes reject invalid resulting currency. No player amount authority or serialized state. Optional Cities integration is outside scope; general administrator settings editor remains a separate core blocker.

- Component route result: PASS.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### foilwench (1.1): PASS

Current Forest event requires POST/CSRF and consumed intent. Real HTTP proves exactly one gem spent and one Dark Arts skill increment, no replay increment, no reward without a gem and malformed operation rejection. Existing specialty hook fixtures cover the bundled skill handlers. Bound player/pref transaction and safe LoGD output; no serialized state.

- Component route result: PASS.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### game_dice (1.1): PASS

PASS: server-owned wager, roll, attempts and result; positive affordable committed stake; finite bet/pass/keep with at most three player rolls; historical opponent stopping rules and payouts; POST/CSRF, one-use state intent, abandonment, wrong-game and forged-field rejection; real HTTP progression/settlement/rollback/replay and deterministic opponent branches on both targets.

- Component route result: PASS.
- Exact metadata and lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### game_fivesix (1.7): PASS

PASS: server-configured bounded cost/daily quota/jackpot, committed stake, server dice/result, unique wager generation, one-use POST/CSRF, locked shared jackpot and atomic player/pref/settings writes; exact payout/cap/reset unit branches, actual HTTP rollback and replay, two actors on two loopback servers with serial-equivalent jackpot/account results, plus actual New Day hook counter reset on both targets. General administrator settings editor remains a separate core merge blocker.

- Component route result: PASS.
- Exact metadata and lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### game_stones (1.1): PASS

PASS: account-owned, unique-generation JSON wager envelope; stake debited atomically on bet; exact Stones conservation schema; finite choose/bet/draw/settle; loss/tie/win return 0/1/2 stakes; explicit no-refund abandonment and conflicting-game rejection; full HTTP game, replay, malformed state, all-in unit branch and database settlement-failure rollback on both targets.

- Component route result: PASS.
- Exact metadata and lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### glowingstream (1.1): BLOCKED

Shared current-event POST/CSRF and consumed drink action pass. Historical effects unchanged; transactional bounded currency. Remaining: all value-changing branches, configured effects and settings-editor validation evidence.

- Component route result: PARTIAL.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### goldmine (1.0): BLOCKED

Shared current-event POST/CSRF, mine action and consumed reward pass. A seeded no-mount death regression fixes an uninitialized outcome flag and verifies configured currency losses/experience. Remaining: alternate reward/loss and optional mount-specific branches, configured amounts and settings-editor validation.

- Component route result: PARTIAL.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### lovers (1.0): BLOCKED

Existing hook semantics and issued Inn route render PASS. Value-changing conversation/flirt/chat routes still require daily-stage authorization, POST/CSRF and replay closure.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### outhouse (2.0): BLOCKED

Paid/free use and wash pass real GET/no-effect, POST/CSRF, charge, replay and fresh-form stage rejection; used/stage prefs persist and reset at New Day. Currency cannot go negative. Remaining: forced reward/loss/gem/turn outcomes, nowash branch and settings-editor validation.

- Component route result: PARTIAL.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### racedwarf (1.1): BLOCKED

Existing choice text, newday/stat and combined-hook behavior PASS. Actual HTTP onboarding choice/invalid choice/POST/CSRF/persistence/reselection matrix remains unimplemented; Cities excluded.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### raceelf (1.0): BLOCKED

Existing choice text, newday/stat and combined-hook behavior PASS. Actual HTTP onboarding choice/invalid choice/POST/CSRF/persistence/reselection matrix remains unimplemented; Cities excluded.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### racehuman (1.0): BLOCKED

Existing choice text, newday/stat and combined-hook behavior PASS. Actual HTTP onboarding choice/invalid choice/POST/CSRF/persistence/reselection matrix remains unimplemented; Cities excluded.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### racetroll (1.0): BLOCKED

Existing choice text, newday/stat and combined-hook behavior PASS. Actual HTTP onboarding choice/invalid choice/POST/CSRF/persistence/reselection matrix remains unimplemented; Cities excluded.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### sethsong (1.1): BLOCKED

GET is read-only; real POST/CSRF increments daily been once and replay cannot repeat effect. Counter is rechecked under lock; original random effects retained. Remaining: complete HP/gold/gem branches, visit exhaustion with a fresh intent and settings-editor validation.

- Component route result: PARTIAL.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### specialtydarkarts (1.1): BLOCKED

Existing valid skill-level buff/use and dragon-kill/reset hook fixtures PASS. Actual HTTP onboarding and combat action authority, invalid/negative levels, unavailable uses, POST/CSRF and duplicate use remain unclosed.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### specialtymysticpower (1.0): BLOCKED

Existing valid skill-level buff/use and dragon-kill/reset hook fixtures PASS. Actual HTTP onboarding and combat action authority, invalid/negative levels, unavailable uses, POST/CSRF and duplicate use remain unclosed.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### specialtythiefskills (1.0): BLOCKED

Existing valid skill-level buff/use and dragon-kill/reset hook fixtures PASS. Actual HTTP onboarding and combat action authority, invalid/negative levels, unavailable uses, POST/CSRF and duplicate use remain unclosed.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

## Second Phase 3 continuation evidence

Stones, Dice and Five/Six are promoted after both supported jobs pass the full expanded HTTP, deterministic payout, replay and rollback tests. Modern core 34783935347 and Baseline integrity 34783935406 pass at implementation `1a6cd7340978e68fa43e7b2d015f4ca513d130ec`. Totals: 65 PHPUnit tests / 1612 assertions / 16 Python tests / 300 linted PHP files, zero skips. General settings, object-preference, combat and other core route gates remain independent merge blockers. No other module was promoted or silently declared complete.

## Dag/Drinks continuation evidence (2026-09-14)

Dag and Drinks are individually promoted after their named remaining gates passed on both targets at `d575acfcf7631201b0f195f0fe10be11fe0d63ec`. Counts are **8 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 16 BLOCKED; 24 lifecycle PASS**. The six previously certified modules remain PASS. The JSON includes a concrete next required gate for each BLOCKED module.

Modern core [34792299180](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34792299180) and Baseline integrity [34792299186](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34792299186) pass. Each supported target runs 67 PHPUnit tests, 1,692 assertions and 20 Python tests with zero skips; 302 PHP files lint with zero failures; both PHPStan policies and Composer gates pass. The final documentation commit is rerun and its exact-head result is recorded in PR #1.

Neither promotion certifies the shared administrator configuration/preference editor, all systemmail callers, full PvP core or character deletion. Cedrik's configured prices are closed; its Transmutation persistence and settings-editor gates are still BLOCKED. No other module is promoted by inference from this test count.

## Cedrik / shared editor / Dark Horse continuation

All eight previously certified modules remain PASS. Cedrik and Dark Horse are added only after both supported targets pass. Lifecycle remains 24 PASS. Goldmine gains tested shared preference infrastructure but stays BLOCKED for its configured gameplay outcomes. Other consumers are not automatically certified by a secure shared primitive. See the latest appended checkpoint for all 24 previous/final decisions and remaining gates.
