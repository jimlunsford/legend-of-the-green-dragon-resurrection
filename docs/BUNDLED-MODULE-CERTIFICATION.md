# Bundled module certification

**Final counts: 21 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 3 BLOCKED. Lifecycle/metadata: 24 PASS. Modern-core merge: NOT READY. Public hosting: NO.**

This 2026-09-14 continuation updates the original all-BLOCKED checkpoint using new mutation evidence. The earlier Phase 3 history remains in [the original checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE3.md). Only the 24 shipped entrypoints are in scope. No archive module was imported. No module is promoted merely because a route renders.

PASS covers the named module in supported bundled use, not arbitrary core/editor routes or public hosting. Find Gold reads server settings, and the shared configuration boundary is now secured; broader administrator editors remain core merge blockers. Optional Cities integration, legacy-column migration and destructive uninstall recovery are outside this fresh-install bundled milestone. A nonsecurity limitation is not invented to disguise a security blocker.

| Module | Version | Lifecycle | Final certification |
|---|---|---|---|
| `cedrikspotions` | 2.6 | PASS | PASS |
| `crazyaudrey` | 1.1 | PASS | PASS |
| `dag` | 1.3 | PASS | PASS |
| `darkhorse` | 1.1 | PASS | PASS |
| `drinks` | 1.1 | PASS | PASS |
| `fairy` | 1.1 | PASS | PASS |
| `findgem` | 1.1 | PASS | PASS |
| `findgold` | 1.1 | PASS | PASS |
| `foilwench` | 1.1 | PASS | PASS |
| `game_dice` | 1.1 | PASS | PASS |
| `game_fivesix` | 1.7 | PASS | PASS |
| `game_stones` | 1.1 | PASS | PASS |
| `glowingstream` | 1.1 | PASS | PASS |
| `goldmine` | 1.0 | PASS | PASS |
| `lovers` | 1.0 | PASS | PASS |
| `outhouse` | 2.0 | PASS | PASS |
| `racedwarf` | 1.1 | PASS | BLOCKED |
| `raceelf` | 1.0 | PASS | PASS |
| `racehuman` | 1.0 | PASS | PASS |
| `racetroll` | 1.0 | PASS | PASS |
| `sethsong` | 1.1 | PASS | PASS |
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

### crazyaudrey (1.1): PASS

Village pet/play uses authenticated active-module state, a locked player transaction, typed locked server settings, nonnegative affordable cost/profit, a paidvisit preference, daily played state, POST/CSRF and day/action-bound one-use intents. Historical repeat petting is retained; free or repeated daily basket play is rejected. Configured UTF-8/apostrophe/backslash/markup and escaped buff names pass. Actual HTTP tests cover insufficient funds, forged values, GET/no mutation, CSRF, replay, inactive/anonymous access, final-account-write rollback of account/preferences/profit/debug log, fresh retry and New Day reset. Fixed seeds exercise all basket rewards and both loss/lower-bound paths through Forest and Village callbacks; retained Forest HTTP proves current-event completion/replay. Evidence: test_module_audrey_village_authority and testAudreyDeterministicBasketsAndDailyReset.

- Previous certification: BLOCKED. Current supported bundled scope: PASS.

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

### fairy (1.1): PASS

All seven historical outcomes are deterministic: configured turns, net gem reward, max/current HP and specialty skill/use increments. Award boundaries 1 and 5 and both carry flags pass. No-gem give and decline paths complete without reward. Declared settings and bounded accumulated HP fail closed before rewards or recalculation. Real shared-editor invalid/boundary/replay cases and actual Dragon processing prove extra-HP carry/removal. Temporary means maximum HP that does not survive Dragon Kill; Fairy has no separate temporary-HP-only reward. Retained Forest HTTP covers current event, POST/CSRF, completion and replay. Evidence: testFairyConfiguredOutcomesCarryAndInvalidState, test_fairy_settings_and_dragon_carry_http, test_module_purchases_post_csrf_replay_and_effects.

- Previous certification: BLOCKED. Current supported bundled scope: PASS.

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

### glowingstream (1.1): PASS

All ten historical rolls are forced with fixed seeds: death retaining gold/experience, near-death HP/turn loss, full healing plus a turn, gem, turn-only and healing-only results. HP/turn lower bounds and decline are asserted. Retained real Forest HTTP proves current-event authority, POST/CSRF, completion and replay. This module declares no configurable effect values, gold gain/loss or other tunable settings; those generic checklist branches are not applicable. Optional travel remains outside bundled scope. Evidence: testGlowingStreamEveryShippedOutcome and test_module_purchases_post_csrf_replay_and_effects.

- Previous certification: BLOCKED. Current supported bundled scope: PASS.

### goldmine (1.0): PASS

PASS in supported bundled scope: actual Forest HTTP forces all 20 historical mining rolls, no reward, gold, gems, combined rewards, cave-in death, exact configured gold/gem loss (0/25/50/100 percent across HTTP and retained unit evidence), no mount, tether, auto-tether, mount survival/death/player save, and rescue via all four shipped race hooks. Validated declared settings and mount preferences, current mount record and race rescue settings bind the one-use event intent and are rechecked under transaction locks. GET preserves gameplay state; anonymous/inactive, malformed action, missing/stale mount, malformed preferences/settings, stale form and replay checks pass. Final-account-write failure rolls back account/news/debug writes; failed intent remains consumed and fresh retry succeeds. Story output retains color formatting and escapes HTML. No random distribution test or gameplay rebalance.

- `test_goldmine_deterministic_http_authority`, retained `testGoldmineDeathWithoutMountHasDefinedOutcome`, shared mount/settings editor HTTP tests, and all 24 lifecycle checks.
- Both supported targets: Modern core [34837310102](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34837310102), baseline [34837310095](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34837310095).

### lovers (1.0): PASS

PASS: actual Inn-to-form HTTP preserves seven alternative flirt choices on both NPC paths; married +/- charm/buff, unmarried success/no-effect/loss, bounded choices and forged effect/path rejection, living/current daily eligibility, active/authenticated module, read-only GET, POST/CSRF/state intent, stale/replay rejection, database persistence and subsequent login, actual New Day reset plus marriage attrition/divorce, final-account news/debug/preference rollback and fresh retry, escaped configured text with LoGD formatting. Evidence: test_lovers_alternative_choices_daily_authority_http.

- Both supported targets: Modern core 34841113158, baseline 34841112989 at `72a43cc0cfd4e6e653a943a5377d7adbfc4139db`.
- Previous certification: BLOCKED. No sequential dialogue stages were introduced.

### outhouse (2.0): PASS

Real HTTP deterministically covers paid/free entry, configured cost, paid gold/gem/turn rewards, no reward, free gold reward/no reward, no-wash penalty and currency floor, exhausted daily visits, fresh-form stage bypass, GET/no mutation, CSRF, duplicate/replay, invalid settings/preferences and actual New Day reset/fresh entry. Locked declared settings and strict used/stage values reject malformed state; intents include account/game-day context. The historical nowash comparison (roll >= badmusthit) is retained. Evidence: test_outhouse_configured_outcomes_http and retained paid/free/wash HTTP.

- Previous certification: BLOCKED. Current supported bundled scope: PASS.

### racedwarf (1.1): BLOCKED

Existing choice text, newday/stat and combined-hook behavior PASS. Actual HTTP onboarding choice/invalid choice/POST/CSRF/persistence/reselection matrix remains unimplemented; Cities excluded.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### raceelf (1.0): PASS

PASS: test_race_onboarding_http_authority follows real rendered POST forms and independently proves active choice display, authenticated/CSRF authority, exact race/main-village persistence, forged race/module/filename/location/stat rejection, stale/inactive/anonymous denial, no selection GET, no automatic inactive fallback, duplicate/reselection/replay rejection, final-account rollback and fresh retry. Cities remains absent. Both supported targets pass at 77c58792f87afad71c0db5fc77042cd39ab418b8, Modern core 34842503298 and Baseline integrity 34842503326. Elf defense, PvP/adjuststats and New Day buff formulas remain unchanged.

- Previous certification: BLOCKED. Existing runtime, lifecycle and combined-hook evidence retained.

### racehuman (1.0): PASS

PASS: test_race_onboarding_http_authority follows real rendered POST forms and independently proves active choice display, authenticated/CSRF authority, exact race/main-village persistence, forged race/module/filename/location/stat rejection, stale/inactive/anonymous denial, no selection GET, no automatic inactive fallback, duplicate/reselection/replay rejection, final-account rollback and fresh retry. Cities remains absent. Both supported targets pass at 77c58792f87afad71c0db5fc77042cd39ab418b8, Modern core 34842503298 and Baseline integrity 34842503326. Human daily Forest-fight bonus remains +2 at its historical default.

- Previous certification: BLOCKED. Existing runtime, lifecycle and combined-hook evidence retained.

### racetroll (1.0): PASS

PASS: test_race_onboarding_http_authority follows real rendered POST forms and independently proves active choice display, authenticated/CSRF authority, exact race/main-village persistence, forged race/module/filename/location/stat rejection, stale/inactive/anonymous denial, no selection GET, no automatic inactive fallback, duplicate/reselection/replay rejection, final-account rollback and fresh retry. Cities remains absent. Both supported targets pass at 77c58792f87afad71c0db5fc77042cd39ab418b8, Modern core 34842503298 and Baseline integrity 34842503326. Troll attack, PvP/adjuststats and New Day buff formulas remain unchanged.

- Previous certification: BLOCKED. Existing runtime, lifecycle and combined-hook evidence retained.

### sethsong (1.1): PASS

Actual HTTP forces every one of the 19 song results, plus female charm, HP floor, insufficient gold, zero gems and overfull HP. Configured fixed rewards/losses and visit limits, no-effect result, GET/no mutation, CSRF, replay, exhaustion, a second fresh valid visit and New Day reset pass. Declared ranges, paired reward bounds, nonnegative effect/counter values and locked current settings are checked before mutation. One-use intent includes account/game-day context. Historical song content, odds and effect formulas are retained. Evidence: test_sethsong_all_configured_effects_http and retained Seth HTTP/newday hook fixtures.

- Previous certification: BLOCKED. Current supported bundled scope: PASS.

### specialtydarkarts (1.1): BLOCKED

Shared onboarding PASS through actual HTTP on both supported targets (see latest continuation below). Existing skill-level buff/use and Dragon-reset hook fixtures remain PASS. Actual combat authority, invalid/negative levels, unavailable uses, current combat, POST/CSRF, replay, persistence and rollback remain BLOCKED.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### specialtymysticpower (1.0): BLOCKED

Shared onboarding PASS through actual HTTP on both supported targets (see latest continuation below). Existing skill-level buff/use and Dragon-reset hook fixtures remain PASS. Actual combat authority, invalid/negative levels, unavailable uses, current combat, POST/CSRF, replay, persistence and rollback remain BLOCKED.

- Component route result: BLOCKED.
- Exact metadata, descriptors, hooks, dependencies, database tables and prior lifecycle evidence remain in [the JSON record](BUNDLED-MODULE-CERTIFICATION.json).

### specialtythiefskills (1.0): BLOCKED

Shared onboarding PASS through actual HTTP on both supported targets (see latest continuation below). Existing skill-level buff/use and Dragon-reset hook fixtures remain PASS. Actual combat authority, invalid/negative levels, unavailable uses, current combat, POST/CSRF, replay, persistence and rollback remain BLOCKED.

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


## Remaining effects continuation (2026-09-14)

Five modules promoted individually: Crazy Audrey, Fairy, Glowing Stream, Outhouse and Seth. Goldmine and Lovers remain BLOCKED; all four race and all three specialty onboarding/combat gates remain BLOCKED. The original ten certifications remain intact. Test-only random fixtures are installed solely in disposable test databases and removed afterwards; production random APIs, outcomes and probabilities are unchanged. Current implementation evidence is in the appended closure checkpoint.

Latest supported implementation `77c58792f87afad71c0db5fc77042cd39ab418b8`: 74 PHPUnit tests / 2,231 assertions / 32 Python and HTTP tests / 309 PHP files linted; zero failures or skips on either supported target. Modern core 34842503298 and Baseline integrity 34842503326 SUCCESS. Three specialty certifications remain BLOCKED; this is not Phase 3 completion.

## Specialty onboarding continuation

The shared onboarding implementation and actual HTTP evidence are described in [SPECIALTY-AUTHORITY.md](SPECIALTY-AUTHORITY.md). All three specialties remain BLOCKED until their actual combat boundary is proven. Counts remain **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. Both supported targets PASS at 82bb591d5355ccd0e5a071f1d4fd3efd7033c7ba: 74 PHPUnit tests / 2231 assertions / 33 Python and HTTP tests / 310 PHP files linted; zero failures/skips. Modern core 34886260212 and Baseline integrity 34886260167 SUCCESS. Hook/formula tests do not substitute for HTTP combat certification.
