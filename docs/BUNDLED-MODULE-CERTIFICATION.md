# Bundled module certification

**Final certification: BLOCKED for all 24. Lifecycle/metadata certification: PASS for all 24.** Fully passing: 0; passing with documented limitations: 0; blocked: 24. This is a continuation checkpoint, not a claim of module/public-hosting safety.

The set and versions below were read from the actual 24 `modules/*.php` entrypoints and their `getmoduleinfo` functions. [Machine-readable matrix](BUNDLED-MODULE-CERTIFICATION.json) contains every setting/preference descriptor, dependency declaration, registered hook/callback/priority/condition, event, route status, schema and platform result. No module declares a hard `requires` dependency. Dark Horse games consume the `darkhorsegame` hook; optional Cities integration is outside this bundled set. Absence of a hard dependency is preserved, not bypassed.

## Shared lifecycle evidence

`ModuleCertificationTest` starts with installed/inactive modules, rejects injection while inactive, activates using the real API, verifies active database state, dependencies and callable registrations, round-trips every named setting/user preference (including apostrophe, backslash and UTF-8), primes hook preloads, deactivates, verifies disabled injection and preload invalidation, reactivates, reinstalls and compares hooks/events exactly, preserves a configured setting, then explicitly reactivates. All 24 run together; registrations have no duplicates and Dag/Drinks support data is preserved. Fixtures restore inactive state. Fresh installation still defaults to inactive.

Dependency fixtures cover valid, missing, inactive, insufficient-version, malformed and cyclic descriptors. Version checks use version_compare, and cache invalidation also invalidates dependents. Reinstallation is tested on the modern schema; historical-column conversion and destructive uninstall/data-loss recovery are not certified.

Both supported targets pass the tested subset: PHP 8.4.25 / MariaDB 11.4.13 and PHP 8.5.10 / MySQL 8.4.11. Passing tests report no PHP notices/warnings/deprecations. These results do not cover unexecuted random branches. CI counts and exact conclusions are in the [Phase 3 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE3.md).

| Module | Historical version | Lifecycle | Final certification |
|---|---|---|---|
| `cedrikspotions` | 2.6 | PASS | BLOCKED |
| `crazyaudrey` | 1.1 | PASS | BLOCKED |
| `dag` | 1.3 | PASS | BLOCKED |
| `darkhorse` | 1.1 | PASS | BLOCKED |
| `drinks` | 1.1 | PASS | BLOCKED |
| `fairy` | 1.1 | PASS | BLOCKED |
| `findgem` | 1.1 | PASS | BLOCKED |
| `findgold` | 1.1 | PASS | BLOCKED |
| `foilwench` | 1.1 | PASS | BLOCKED |
| `game_dice` | 1.1 | PASS | BLOCKED |
| `game_fivesix` | 1.7 | PASS | BLOCKED |
| `game_stones` | 1.1 | PASS | BLOCKED |
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

The final implementation fixture also exercises real authenticated Village, Inn and Forest HTTP rendering with all 24 active, and follows issued Dag/Lovers/Seth direct routes and their return-to-Inn links. All four valid skill levels for all three specialties construct their expected buffs/companion and spend the expected uses. Race choice text and actual Elf/Troll PvP/stat-adjustment hooks pass. The full mutation-security gates below remain open.

## Per-module evidence and remaining gates

### cedrikspotions (2.6)

Inn header navigation, health recalculation, real daily callback and retry receipts; purchase/effect routes remain untested.

- Hooks: `header-inn`, `newday-runonce`, `hprecalc`.
- Events: NOT APPLICABLE.
- Settings: `ischarm`, `ismax`, `istemp`, `isforget`, `istrans`, `charmcost`, `maxcost`, `tempcost`, `forgcost`, `transcost`, `random`, `minrand`, `maxrand`, `randcost`, `transmuteturns`, `defmod`, `atkmod`, `survive`, `charmgain`, `vitalgain`, `tempgain`, `carrydk`.
- User preferences: `extrahps`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### crazyaudrey (1.1)

Combined New Day reset, daily countdown/retry, event collection and forest entrance; random play/reward paths remain untested.

- Hooks: `village`, `village-desc`, `newday`, `newday-runonce`.
- Events: `forest`: `return 100;`.
- Settings: `cost`, `animal`, `animals`, `lanimal`, `lanimals`, `sound`, `buffname`, `gamedaysremaining`, `defaultanimal`, `defaultanimals`, `defaultsound`, `defaultbuffname`, `profit`, `villagepercent`.
- User preferences: `played`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### dag (1.3)

Strict bounty table install/reinstall with row preservation, Inn hooks, New Day count reset and PvP no-bounty path. Bounty creation/claims/admin mutations remain unreviewed. Issued direct route and return-to-Inn HTTP smoke passes on both targets.

- Hooks: `inn-desc`, `inn`, `superuser`, `newday`, `pvpwin`, `dragonkill`, `showsettings`, `delete_character`.
- Events: NOT APPLICABLE.
- Settings: `bountymin`, `bountymax`, `bountylevel`, `bountyfee`, `maxbounties`.
- User preferences: `bounties`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. PASS for real authenticated issued Inn-to-module-to-Inn HTTP navigation with all 24 active. Full action authorization, POST/CSRF, output and replay certification remains BLOCKED.
- Schema: bounty support table; strict fresh installation and row-preserving reinstall PASS.

### darkhorse (1.1)

Forest/travel chance, mount object preference, forest entrance and darkhorsegame callbacks from all three games. Tavern mutations and return navigation validation remain open.

- Hooks: `forest`, `mountfeatures`, `moderate`.
- Events: `forest`: `bundled:darkhorse-without-tavern-mount`; `travel`: `bundled:darkhorse-without-tavern-mount`.
- Settings: `tavernname`.
- User preferences: NOT APPLICABLE.
- Object preferences: mounts.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### drinks (1.1)

Strict drinks table and three default records preserved on reinstall, daily state, quota text/list and graveyard sobriety. Purchase and editor mutations remain unreviewed.

- Hooks: `ale`, `newday`, `superuser`, `header-graveyard`, `commentary`, `soberup`, `dragonkill`.
- Events: NOT APPLICABLE.
- Settings: `hardlimit`, `maxdrunk`.
- User preferences: `drunkeness`, `harddrinks`, `canedit`, `noslur`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: drinks support table; strict fresh installation and row-preserving reinstall PASS.

### fairy (1.1)

Forest event collection/entrance and health recalculation; random reward/loss branches remain untested.

- Hooks: `hprecalc`.
- Events: `forest`: `return 100;`.
- Settings: `carrydk`, `hptoaward`, `fftoaward`.
- User preferences: `extrahps`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### findgem (1.1)

Forest/travel registration and active collection; real forest event grants one gem. Shared event route replay/method/CSRF certification remains open.

- Hooks: NOT APPLICABLE.
- Events: `forest`: `return 100;`; `travel`: `return 20;`.
- Settings: NOT APPLICABLE.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### findgold (1.1)

Forest/travel registration and active collection; real forest event grants gold. Shared event route replay/method/CSRF certification remains open.

- Hooks: NOT APPLICABLE.
- Events: `forest`: `return 100;`; `travel`: `return 20;`.
- Settings: `mingold`, `maxgold`.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### foilwench (1.1)

Forest event collection/entrance and gift branch spends a gem and increments specialty. GET mutation remains a blocker.

- Hooks: NOT APPLICABLE.
- Events: `forest`: `return 100;`.
- Settings: NOT APPLICABLE.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### game_dice (1.1)

Module-defined darkhorsegame navigation with all game modules active; direct game execution, amount validation and replay remain open.

- Hooks: `darkhorsegame`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### game_fivesix (1.7)

Module-defined darkhorsegame navigation and combined New Day execution; direct play/jackpot persistence and mutation security remain open.

- Hooks: `darkhorsegame`, `newday`.
- Events: NOT APPLICABLE.
- Settings: `cost`, `dailyuses`, `jackpot`, `maxjackpot`, `lastwin5`, `lastpot5`, `lastwin4`, `lastpot4`, `lastwin3`, `lastpot3`.
- User preferences: `playstoday`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### game_stones (1.1)

Module-defined darkhorsegame navigation; direct game play, unvalidated unserialize of specialmisc, bets and replay remain open.

- Hooks: `darkhorsegame`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### glowingstream (1.1)

Forest/travel registration and active collection; forest entrance. Drink outcome branches remain untested.

- Hooks: NOT APPLICABLE.
- Events: `forest`: `return 100;`; `travel`: `return 100;`.
- Settings: NOT APPLICABLE.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### goldmine (1.0)

Forest event collection/entrance and decline/exit; mining random branches and economic mutation security remain open.

- Hooks: NOT APPLICABLE.
- Events: `forest`: `return 100;`.
- Settings: `alwaystether`, `percentgemloss`, `percentgoldloss`.
- User preferences: NOT APPLICABLE.
- Object preferences: mounts.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### lovers (1.0)

Inn navigation and daily preference reset; conversation/effect mutations and output audit remain open. Issued direct route and return-to-Inn HTTP smoke passes on both targets.

- Hooks: `newday`, `inn`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: `seenlover`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. PASS for real authenticated issued Inn-to-module-to-Inn HTTP navigation with all 24 active. Full action authorization, POST/CSRF, output and replay certification remains BLOCKED.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### outhouse (2.0)

Forest navigation and New Day preference reset; paid/free use and reward/loss actions remain untested.

- Hooks: `forest`, `newday`.
- Events: NOT APPLICABLE.
- Settings: `cost`, `goldinhand`, `giveback`, `takeback`, `goodmusthit`, `badmusthit`, `givegempercent`, `giveturnchance`.
- User preferences: `usedouthouse`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### racedwarf (1.1)

Race naming/setrace, location changes with raw quote/backslash/UTF-8 values and no-Cities location hooks. Creature gold multiplier. Full selection HTTP and combat/location route security remain open. Optional Cities is outside the bundled set. Chooserace hook text/navigation passes.

- Hooks: `chooserace`, `setrace`, `creatureencounter`, `villagetext`, `travel`, `village`, `validlocation`, `validforestloc`, `moderate`, `drinks-text`, `changesetting`, `drinks-check`, `raceminedeath`, `racenames`, `camplocs`, `mercenarycamptext`.
- Events: NOT APPLICABLE.
- Settings: `villagename`, `minedeathchance`.
- User preferences: NOT APPLICABLE.
- Object preferences: drinks.
- Direct runmodule route: BLOCKED. Full action authorization, POST/CSRF, output and replay certification not complete.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### raceelf (1.0)

Race naming/setrace, location changes with raw quote/backslash/UTF-8 values and no-Cities location hooks. New Day defense formula, PvP/training buff flags and zero-stat guard. Full selection HTTP and combat/location route security remain open. Optional Cities is outside the bundled set. Chooserace hook text/navigation passes. Actual pvpadjust and adjuststats hooks preserve the level-10 +3 modifier.

- Hooks: `chooserace`, `setrace`, `newday`, `villagetext`, `travel`, `validlocation`, `validforestloc`, `moderate`, `changesetting`, `raceminedeath`, `pvpadjust`, `adjuststats`, `racenames`, `weaponstext`.
- Events: NOT APPLICABLE.
- Settings: `villagename`, `minedeathchance`.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### racehuman (1.0)

Race naming/setrace, location changes with raw quote/backslash/UTF-8 values and no-Cities location hooks. New Day turn bonus and historical singular/plural text. Full selection HTTP and combat/location route security remain open. Optional Cities is outside the bundled set. Chooserace hook text/navigation passes.

- Hooks: `chooserace`, `setrace`, `newday`, `villagetext`, `stabletext`, `travel`, `validlocation`, `validforestloc`, `moderate`, `changesetting`, `raceminedeath`, `stablelocs`, `racenames`.
- Events: NOT APPLICABLE.
- Settings: `villagename`, `minedeathchance`, `bonus`.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### racetroll (1.0)

Race naming/setrace, location changes with raw quote/backslash/UTF-8 values and no-Cities location hooks. New Day attack formula, PvP/training buff flags and zero-stat guard. Full selection HTTP and combat/location route security remain open. Optional Cities is outside the bundled set. Chooserace hook text/navigation passes. Actual pvpadjust and adjuststats hooks preserve the level-10 +3 modifier.

- Hooks: `chooserace`, `setrace`, `newday`, `villagetext`, `travel`, `validlocation`, `validforestloc`, `moderate`, `changesetting`, `raceminedeath`, `pvpadjust`, `adjuststats`, `racenames`.
- Events: NOT APPLICABLE.
- Settings: `villagename`, `minedeathchance`.
- User preferences: NOT APPLICABLE.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### sethsong (1.1)

Inn navigation and daily preference reset; conversation/random-song outcomes and mutation security remain open. Issued direct route and return-to-Inn HTTP smoke passes on both targets.

- Hooks: `inn`, `newday`.
- Events: NOT APPLICABLE.
- Settings: `bhploss`, `shploss`, `hpgain`, `maxgems`, `mingems`, `mingold`, `maxgold`, `goldloss`, `visits`.
- User preferences: `been`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: BLOCKED. PASS for real authenticated issued Inn-to-module-to-Inn HTTP navigation with all 24 active. Full action authorization, POST/CSRF, output and replay certification remains BLOCKED.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### specialtydarkarts (1.1)

Specialty identity, skill increment, New Day uses, fight navigation, malformed/negative/unsupported level rejection and Dragon Kill reset. Full combat and selection route method/CSRF/replay certification remain open. Actual choice navigation and all four valid skill levels (1, 2, 3, 5) execute and spend the expected uses; Dark Arts companion construction also passes.

- Hooks: `choose-specialty`, `set-specialty`, `fightnav-specialties`, `apply-specialties`, `newday`, `incrementspecialty`, `specialtynames`, `specialtymodules`, `specialtycolor`, `dragonkill`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: `skill`, `uses`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### specialtymysticpower (1.0)

Specialty identity, skill increment, New Day uses, fight navigation, malformed/negative/unsupported level rejection and Dragon Kill reset. Full combat and selection route method/CSRF/replay certification remain open. Actual choice navigation and all four valid skill levels (1, 2, 3, 5) execute and spend the expected uses; Dark Arts companion construction also passes.

- Hooks: `choose-specialty`, `set-specialty`, `fightnav-specialties`, `apply-specialties`, `newday`, `incrementspecialty`, `specialtynames`, `specialtymodules`, `specialtycolor`, `dragonkill`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: `skill`, `uses`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

### specialtythiefskills (1.0)

Specialty identity, skill increment, New Day uses, fight navigation, malformed/negative/unsupported level rejection and Dragon Kill reset. Full combat and selection route method/CSRF/replay certification remain open. Actual choice navigation and all four valid skill levels (1, 2, 3, 5) execute and spend the expected uses; Dark Arts companion construction also passes.

- Hooks: `choose-specialty`, `set-specialty`, `fightnav-specialties`, `apply-specialties`, `newday`, `incrementspecialty`, `specialtynames`, `specialtymodules`, `specialtycolor`, `dragonkill`.
- Events: NOT APPLICABLE.
- Settings: NOT APPLICABLE.
- User preferences: `skill`, `uses`.
- Object preferences: NOT APPLICABLE.
- Direct runmodule route: NOT APPLICABLE. Empty historical run function; event/core hook route remains in scope.
- Schema: No new module table on fresh installation. Shared settings/preferences and player state only.

## Hook coverage boundaries

Combined fixtures exercise chooserace, choose-specialty, racenames, setrace, pvpadjust, adjuststats, newday, incrementspecialty, fightnav-specialties, apply-specialties rejection and all four valid levels, dragonkill reset, changesetting, validlocation, validforestloc, creatureencounter, hprecalc, header-inn, inn, inn-desc, forest, header-graveyard, ale, pvpwin (no bounty), darkhorsegame and both actual newday-runonce callbacks. Event collection covers eight forest and four travel registrations. Forest callbacks include actual Findgem/Findgold rewards, six other entrances, Foil Wench gift and Goldmine decline. These are concrete samples, not exhaustive path certification.

Full battle/battle-victory/battle-defeat and training/PvP routes are not certified by buff construction. Module registration does not imply every hook branch executed. Explicit remaining work includes all Dark Horse game action/state round trips, all purchase/value-changing paths, optional-integration decisions without archive imports, remaining conversations/rewards, object-preference input boundaries, administrator capability checks and full output-context review.

## Security and operational limits

Module PHP remains trusted repository code. The extension system is not a sandbox for arbitrary third-party PHP. All five prior expression eval sites are removed; see [expression audit](EXPRESSION-EVALUATION-AUDIT.md). Active daily-hook transactions and receipts are verified, but do not promise exactly-once external side effects or arbitrary DDL rollback. No statistical probability testing or gameplay rebalance was performed. Public hosting remains NO.
