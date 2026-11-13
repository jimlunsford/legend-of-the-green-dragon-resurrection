# Roadmap

The single high-level plan for Legend of the Green Dragon - Resurrection.

Checkpoint documents hold the evidence. This file holds the plan. When they disagree, the checkpoint evidence wins and this file gets corrected.

## Goal

Phases 1 to 4: modernize the code, preserve the game. LoGD 1.1.2 DragonPrime Edition was brought onto supported PHP and databases with every important state transition examined, constrained and tested, and the gameplay, atmosphere, odd historical behavior and module system kept intact.

The finish line for the modernization was the merge of PR #1 into `main`, reached on 2026-10-23 at `0d078df`. On 2026-10-24 Jim chose the next direction: Phase 4, Release foundation, complete at `8b33edf`.

On 2026-10-30 Jim changed the direction (`docs/PHASE5-TASK1-DIRECTION-20261030.md`): the behaviorally faithful rule is dropped. Gameplay, balance, text and UI may change when a task prompt says so, and every change from 1.1.2 is recorded with the old behavior, the new behavior and why. Phase 5 gives the game one new UI and original art. It stays PHP on this codebase, so other people can host it.

Phase 5 is complete. On 2026-11-05 Jim chose Phase 6, Release readiness: find and fix what stands between `main` and the first release candidate, `1.0.0-rc.1`. The release itself stays excluded until he says go.

## Phases

| Phase | Purpose | Status | Record |
|---|---|---|---|
| Foundation | Preserve the exact 1.1.2 source, provenance, historical tag and manifests. Inventory compatibility and security. No modernization claims. | DONE | `docs/FOUNDATION-CHECKPOINT-20260913.md` |
| Phase 1 | First modern engineering base: PHP/database compatibility, PDO direction, CI, static analysis, early installer and runtime work. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913.md` |
| Phase 2 | A real modern base application: strict fresh install, modern credentials, authentication, session rotation, CSRF infrastructure, account creation, village, commentary, logout, all 24 bundled modules installable. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE2.md` |
| Phase 3 | Close every game and application trust boundary until the modern core is mergeable. | DONE, merged into `main` at `0d078df` on 2026-10-23 | [`docs/PHASE3-MERGE-GATE-20261023.md`](docs/PHASE3-MERGE-GATE-20261023.md), `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE3*.md`, `docs/CORE-ROUTE-SECURITY.md` |
| Phase 4 | Release foundation: make the game ready for its first release. | DONE at `8b33edf` on 2026-10-30 | [Phase 4 below](#phase-4-release-foundation) |
| Phase 5 | Resurrection UI: one new dark fantasy UI with original art replaces all 1.1.2 skins. | COMPLETE, accepted at `4f66f61` (2026-11-05; recorded 2026-11-06) | [Phase 5 below](#phase-5-resurrection-ui), [`docs/PHASE5-SUMMARY-20261105.md`](docs/PHASE5-SUMMARY-20261105.md) |
| Phase 6 | Release readiness: find and fix what stands between `main` and the first release candidate, `1.0.0-rc.1`. | CURRENT (opened 2026-11-06; Jim chose it on 2026-11-05) | [Phase 6 below](#phase-6-release-readiness), [`docs/PHASE6-RC-READINESS-20261106.md`](docs/PHASE6-RC-READINESS-20261106.md) |

## Phase 3 checklist

Each family is DONE only when its acceptance evidence passes on both supported CI targets. Details and exact run IDs live in the linked records.

### Done

- [x] Bundled modules: 24 PASS, all 24 lifecycle PASS (`BUNDLED-MODULE-CERTIFICATION.md`). Mystical Powers accepted at descendant `cf6a349`, Modern core 36317455788; its own candidate `0944188` run was cancelled.
- [x] Race onboarding: Human, Elf, Dwarf, Troll
- [x] Specialty onboarding, plus Dark Arts and Thieving Skills combat
- [x] Ordinary Forest combat authority (`ORDINARY-FOREST-COMBAT-20260927.md`)
- [x] Dragon and prologue authority (`DRAGON-AUTHORITY-20260926.md`)
- [x] Training and master combat (`TRAINING-MASTER-AUTHORITY-20260927.md`)
- [x] Core PvP (`PVP-COMBAT-AUTHORITY-20260927.md`)
- [x] Graveyard torment combat (`GRAVEYARD-COMBAT-AUTHORITY-20260927.md`)
- [x] New Day Dragon Point allocation (`DRAGON-POINT-AUTHORITY-20261003.md`)
- [x] Normal New Day daily reset (`NEWDAY-DAILY-RESET-AUTHORITY-20261003.md`)
- [x] Ramius early resurrection (`RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md`)
- [x] Player mail: address, write, reply, send, inbox (`MAIL-AUTHORITY-20261004.md`)
- [x] Shared `systemmail()` helper contract
- [x] Shared typed settings editor and mount object preferences
- [x] Player preference namespace protection
- [x] Petition intake
- [x] Direct `battle.php` include bypass blocked
- [x] All 14 `systemmail()` caller contracts, plus the clan NULL-description fix and the MySQL 8 table-reference fix (`MAIL-AUTHORITY-20261004.md`). Accepted at `4478c06`, Modern core 38712609964.
- [x] CI runtime: Modern core split into a checks job and four HTTP shards per leg (`ENGINEERING-VALIDATION.md`). Longest job about 16 minutes. Accepted at `4478c06`.
- [x] Petition administration (`viewpetition.php`) (`PETITION-ADMIN-AUTHORITY-20261015.md`). Accepted at `ef97952`, Modern core 38722109813.
- [x] Inn economy: room rental, bartender bribe and specialty switch, Dragon flee (`INN-ECONOMY-AUTHORITY-20261015.md`). Accepted at `3b6d780`, Modern core 38736206216.
- [x] Clan membership: creation, application, approval, ranks, removal, withdrawal, leader succession, empty-clan cleanup (`CLAN-MEMBERSHIP-AUTHORITY-20261015.md`). Accepted at `2eff167` (candidate `9c2f458` plus the shard-order fix), Modern core 38767451569. Run 38758814063 failed on shard order and stays on record.
- [x] Clan text and moderation: MOTD, description, custom say, superuser rename and description block/unblock, NULL-description display (`CLAN-TEXT-MODERATION-AUTHORITY-20261015.md`). Accepted at `cbc0cf4`, Modern core 38779187332.
- [x] Bank: deposit, withdraw, borrow, transfer (`BANK-AUTHORITY-20261015.md`). Accepted at `52bfc4f`, Modern core 38802851227.
- [x] Weapons and armor purchase and trade-in (`EQUIPMENT-PURCHASE-AUTHORITY-20261015.md`). Accepted at `639b8c5`, Modern core 38810195085.
- [x] Stables: buy, trade, feed, sell (`STABLES-AUTHORITY-20261016.md`). Accepted at `8257aed`, Modern core 38831947335.
- [x] Haunting: search and haunt (`HAUNT-AUTHORITY-20261016.md`). Accepted at `595f0c3` (Modern core 38837315295) and follow-up `2a48f35` (Modern core 38884592810, Baseline integrity 38884592513).
- [x] Bio moderation (`bios.php`) and donation administration (`donators.php`) (`BIO-DONATION-ADMIN-AUTHORITY-20261016.md`). Accepted at `c166e2e`, Modern core 38892084451, Baseline integrity 38892084290.
- [x] Soul restoration, a GET favor spend found during haunting (`lib/graveyard/case_restore.php`), and the preferences fatal for new accounts (`prefs.php`, `lib/showform.php`) (`RESTORE-PREFS-AUTHORITY-20261016.md`). Candidate `6a9a12b`; its own run, Modern core 38896842544, failed at the 12:00Z game day rollover and stays on record. Accepted through the first accepted descendant `fa30beb`, Modern core 38907797754, Baseline integrity 38907797223; the tests are unchanged between the two.
- [x] User editor part 1: account edit, special actions, module preferences, last hit, debug log (`user.php`) (`USER-EDITOR-AUTHORITY-20261016.md`). Accepted at `fa30beb`, Modern core 38907797754, Baseline integrity 38907797223.
- [x] CI game-day rollover stability: the web test harness pins the game clock so a shard never crosses a game day boundary (`CI-ROLLOVER-STABILITY-20261016.md`). Accepted at `aeb19b9`, Modern core 38923797893, Baseline integrity 38923797782.
- [x] User editor part 2: bans (`user.php` `setupban`, `saveban`, `delban`, `removeban`) (`BAN-ADMIN-AUTHORITY-20261016.md`). Accepted at `eec24a6`, Modern core 38931835620, Baseline integrity 38931835612.
- [x] Ban message page fatal: a matching ban at login or account creation was a 500 instead of 1.1.2's message, found during bans (`lib/checkban.php`, `lib/translator.php`) (`BAN-MESSAGE-AND-ID-COOKIE-20261016.md`). Accepted at `55ec6c2`, Modern core 38942722221, Baseline integrity 38942722236.
- [x] Player ID cookie: only the generated 32-hex `lgi` format becomes the stored `uniqueid`, found during bans (`common.php`, `lib/web_security.php`) (`BAN-MESSAGE-AND-ID-COOKIE-20261016.md`). Accepted at `55ec6c2`, Modern core 38942722221, Baseline integrity 38942722236.
- [x] Content editors part 1: weapons, armor, taunts, titles, including the title rebuild (`weaponeditor.php`, `armoreditor.php`, `taunt.php`, `titleedit.php`) (`CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`). Accepted at `6820b80`, Modern core 38958259938, Baseline integrity 38958259926.
- [x] Content editors part 2: creatures, masters (`creatures.php`, `masters.php`) (`CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`). Accepted at `82a48fb`, Modern core 38971089072, Baseline integrity 38971089075.
- [x] News formatting fatal: a stored taunt with a stray `%` made `news.php` and `bio.php` fail on PHP 8 in `sprintf_translate()` (`lib/translator.php`, `lib/output.php`), found during content editors part 1 (`CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`). Accepted at `82a48fb`, Modern core 38971089072, Baseline integrity 38971089075.
- [x] Content editors part 3: mounts and companions: object add, edit, save, delete, give, take, activate, deactivate, and the owner refunds on mount delete (`mounts.php`, `companions.php`) (`CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`). Accepted at `19cbb4e`, Modern core 38991243831, Baseline integrity 38991243814.
- [x] Bio page fatal for a character without a mount: PHP 8's false-to-array deprecation at `bio.php:97`, found during content editors part 2 (`CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`). Accepted at `19cbb4e`, Modern core 38991243831, Baseline integrity 38991243814.
- [x] Bio page lookup by login pasted `char` into SQL (`bio.php`), found during content editors part 2 (`CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`). Accepted at `19cbb4e`, Modern core 38991243831, Baseline integrity 38991243814.
- [x] Mount owner lookup (`mounts.php?op=xml`) answered before the editor's `SU_EDIT_MOUNTS` check, with its ID pasted into SQL, found during content editors part 3 (`CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`). Accepted at `19cbb4e`, Modern core 38991243831, Baseline integrity 38991243814.
- [x] Module manager and remaining preference editors: every lifecycle op is POST + CSRF + a one-use intent bound to the module and its state; only bundled modules install; uninstall reports what it removed; the preference-editor inventory found no open request writer (`modules.php`) (`MODULE-MANAGER-AUTHORITY-20261017.md`). Accepted at `43bcdc4`, Modern core 39026254697, Baseline integrity 39026254784.
- [x] Stables purchase reads the mount under a lock, so a mount deactivated and deleted meanwhile cannot be bought (`stables.php`), found during content editors part 3 (`MODULE-MANAGER-AUTHORITY-20261017.md`). Accepted at `43bcdc4`, Modern core 39026254697, Baseline integrity 39026254784.
- [x] Expiration cleanup: lock-guarded, each account deleted all or nothing, `last_char_expire` recorded only when no account failed, the warning email marks `sentnotice` only when sent (`lib/expire_chars.php`) (`DELETION-EXPIRATION-AUTHORITY-20261017.md`). Accepted at `4182281`, Modern core 39029578747, Baseline integrity 39029578850.
- [x] Account deletion failure and retry semantics: one locked transaction per account for the hook, cleanup, clan succession and account row, with the veto honored by all three callers; the user editor's delete and self-deletion take a one-use intent (`lib/charcleanup.php`, `user.php?op=del`, `prefs.php?op=suicide`) (`DELETION-EXPIRATION-AUTHORITY-20261017.md`). Accepted at `4182281`, Modern core 39029578747, Baseline integrity 39029578850.
- [x] Truthful completion reporting: `OPTIMIZE TABLE` reads each table's result rows, logs counts and failures, and records `lastdboptimize` only when no table failed, under the maintenance lock (`lib/newday/dbcleanup.php`); the petition notice goes through the shared mail helper and a refused notice is logged; `serverurl` is never saved from a request (`lib/petition/petition_default.php`, `referral.php`); every live external email send uses one helper (`COMPLETION-REPORTING-20261017.md`). Accepted at `05485ec`, Modern core 39044082958, Baseline integrity 39044082762.
- [x] Serialized business schemas part 1: per-site inventory with category and status, the definition of done, navigation (`allowednavs`, `restorepage`), preferences and editor old values closed at every read and writer (`lib/forcednavigation.php`, `login.php`, `prefs.php`, `lib/saveuser.php`, `lib/player_mutation.php`, `lib/systemmail.php`), and the badnav.php fatal found while testing (`SERIALIZED-SCHEMAS-PART1-20261017.md`). Accepted at `fd548a3`, Modern core 39054238252, Baseline integrity 39054238222.
- [x] Combat buff compatibility: the installer Pony, the drinks buzz, the lover, Crazy Audrey and the mount editor's text fields no longer stop combat or New Day (`SERIALIZED-SCHEMAS-PART2-20261018.md`). Accepted at `1dfb7c5`, Modern core 39099465964, Baseline integrity 39099465985.
- [x] Serialized business schemas part 2: one buff-list schema at hydration, login, combat and every buff writer (`src/Game/BuffState.php`) (`SERIALIZED-SCHEMAS-PART2-20261018.md`). Accepted at `1dfb7c5`, Modern core 39099465964, Baseline integrity 39099465985.
- [x] Mount buff schema for the stables, the mount editor and New Day (`SERIALIZED-SCHEMAS-PART2-20261018.md`). Accepted at `1dfb7c5`, Modern core 39099465964, Baseline integrity 39099465985.
- [x] Suspended specialty buffs: training and PvP no longer stop while a specialty buff is active (`suspend_buffs()` writes 1/0; the buff schema normalizes it), and a hired healer companion no longer makes the fight a 500 (`src/Game/BuffState.php`, `lib/buffs.php`) (`SERIALIZED-SCHEMAS-PART3-20261022.md`). Accepted at `49baab9`, Modern core 39704754364, Baseline integrity 39704754291.
- [x] Serialized business schemas part 3: one companion schema at hydration, `apply_companion()`, every companion writer and every combat route (`src/Game/CompanionState.php`) (`SERIALIZED-SCHEMAS-PART3-20261022.md`). Accepted at `49baab9`, Modern core 39704754364, Baseline integrity 39704754291.
- [x] Companion abilities: one schema for the mercenary camp, the companion editor and take (`SERIALIZED-SCHEMAS-PART3-20261022.md`). Accepted at `49baab9`, Modern core 39704754364, Baseline integrity 39704754291.
- [x] Serialized business schemas part 4: general combat state. The Forest contract on the Forest-specialty route (1.1.2's doppleganger and the Gypsy Bandit's ability state now pass), the post-round re-check on Dragon and Forest specialty, and the 1.1.2 in-flight Training and Graveyard fights upgraded on first read (`SERIALIZED-SCHEMAS-PART4-20261022.md`). Accepted at `5bdba80`, Modern core 39764211865, Baseline integrity 39764211576.
- [x] Forest search that picked the Gypsy Bandit gave a 500 and rolled back (`ForestCombatState` loads its dependencies) (`SERIALIZED-SCHEMAS-PART4-20261022.md`). Accepted at `5bdba80`, Modern core 39764211865, Baseline integrity 39764211576.
- [x] Serialized business schemas part 5: news arguments and other business state. One news arguments schema for `addnews()`, `news.php` and `bio.php` (structure and size only; a bad row is skipped, a refused writer rolls back); `lib/mail.php` recorded as excluded; Dragon Point history checked at session load and stored only through its schema; the moderated comment snapshot, the payment log and the MOTD poll read and written through schemas. No serialized-state site of the audit is left OPEN. Also fixes the moderation audit page's 500 for a clan founder's comment (`SERIALIZED-SCHEMAS-PART5-20261022.md`). Accepted at `c00f2d1`, Modern core 39773347686, Baseline integrity 39773347676.
- [x] `motd.php` route family: role matrix, every mutation a POST with CSRF and a one-use intent, a vote bound to an existing poll and one of its options, all SQL bound, the MOTD body escaped with colour codes kept; also fixes the 500 with no MOTD and the 500 in the archive for a 30-day month on MySQL 8.4 (`SERIALIZED-SCHEMAS-PART5-20261022.md`). Accepted at `c00f2d1`, Modern core 39773347686, Baseline integrity 39773347676.
- [x] `paylog.php` processdate: a POST with CSRF and a one-use intent, bound; GET writes nothing; also fixes the 500 on MySQL 8.4 (Final rescan line, `SERIALIZED-SCHEMAS-PART5-20261022.md`). Accepted at `c00f2d1`, Modern core 39773347686, Baseline integrity 39773347676.
- [x] `moderate.php` longer existing ban: 1.1.2's rule restored, bound, inside the moderation transaction (Final rescan line, `SERIALIZED-SCHEMAS-PART5-20261022.md`). Accepted at `c00f2d1`, Modern core 39773347686, Baseline integrity 39773347676.
- [x] Final rescan task 1: the route inventory (`ROUTE-INVENTORY-20261022.md`): every top-level page, op, subop and bundled module route with who reaches it, whether it changes state and its status, kept complete by `tests/tooling/test_route_inventory.py`. Closes the player-facing state changes it found open: the mercenary camp hire and heal (the Final rescan line), the healer's potions and companion healing, and the gypsy's paid seance, each a GET that asks and a POST with CSRF and a one-use intent at the server's price under lock; binds the request values of `list.php`, `hof.php`, `news.php` and the mail read page (`ROUTE-INVENTORY-20261022.md`). Accepted through the first accepted descendant `aea4608` (the forest specialty fixture fix), Modern core 39841362118, Baseline integrity 39841362038; `593578e`'s own run, Modern core 39822309470, failed on a fixture gap and stays on record.
- [x] Final rescan task 2: admin tools and small leftovers. News delete for comment moderators only, the word filter editor, `referers.php` rebuild, the translator tool's save and `untranslated.php`'s saves each a POST with CSRF and a one-use intent, checked under lock in one transaction, bound; `gamelog.php`, the referer list (sort from an allow-list, the 1.1.2 expiry on view kept), the keepalive (kept a GET), the translator form and list and the untranslated view bound and escaped; the mail notification links the configured `serverurl`; `getmount()` bound; a name that would not fit after a dragon kill keeps the old name and title; `bio.php`'s return link only to known on-site pages; a test races a clan leader's deletion against a member's withdrawal in both orders. Also fixes the 500 on `referers.php` (and `stats.php`) from PHP 8's float modulo in `dhms()`, the 500 on the referer list's URL sort on MySQL 8.4, the 500 on a dragon kill whose new name is too long, and the referer list showing only its first site (1.1.2 reused the site loop's counter; found by Modern core 39868186035, which failed on MariaDB and stays on record) (`RESCAN-TASK2-20261022.md`). Closes twelve Final rescan lines: news delete, the keepalive, `badword.php`, `gamelog.php`, `referers.php`, `translatortool.php`, `untranslated.php`, the mail notification link, `getmount()`, the name length after a dragon kill, `bio.php`'s return link and the deletion race test. Accepted through the first accepted descendant `107ce87` (the referer list fix), Modern core 39929029453, Baseline integrity 39929029457; `623ca49`'s own run, Modern core 39868186035, failed on MariaDB and stays on record.
- [x] Final rescan task 3: repair path, SQL sweep, referer tracking. Referer tracking on every page bound and cut to its columns in characters as 1.1.2's non-strict MySQL stored it, a `Referer` that is not UTF-8 not tracked, a tracking failure never failing the page, and 1.1.2's monthly referer cleanup restored; a sweep of every value interpolated into SQL in shipped PHP, each request value bound (the commentary page and MoTD paging) and stored text bound with it (the Hall of Fame's translated "Unknown", the PvP list's location, the game log, a module's village name), every other value on a reviewed list kept exact by `tests/unit/SqlSweepTest.php`, and the historical installer's stages refusing direct requests; `user.php?op=repair` (Edit Users) shows only the stored values that fail their schema and repairs each one with a POST, CSRF and a one-use intent bound to the account and the failing value, under lock, with a log line. Also fixes the 500s on the commentary page past PHP's integer range, on MoTD paging with a negative or huge offset and on the Hall of Fame with a translated "Unknown" (`RESCAN-TASK3-20261023.md`). Closes the last three Final rescan lines: referer tracking, the request-value sweep and the admin repair path. Accepted at `54e22e6`, Modern core 39950404013, Baseline integrity 39950404085.
- [x] Merge gate verification (`PHASE3-MERGE-GATE-20261023.md`): an independent check of the four gate conditions at `54e22e6`; not passed, with four findings, each closed by the next line. Published at `d3082f2` (Baseline integrity 39998306290 success; Modern core 39998306405 success, finished before the next push).
- [x] Merge gate findings: the Graveyard views' drunkenness reset kept as 1.1.2 behavior (one bound write of a fixed value; forced navigation keeps a living player out, as in 1.1.2); the route inventory's `pvp.php?act=attack` row, held by `test_route_inventory.py`; the serialized-state recount (94 / 45 / 38 / 55 / 1 from `54e22e6`, four sites recorded, none OPEN); the settings editor logging, and telling the `changesetting` hook, only the fields whose stored value changed, with 1.1.2's message and both values (`PHASE3-MERGE-GATE-20261023.md`, "Merge gate findings closed"). Accepted at `19bf7a0`, Modern core 40004015634 (all 12 jobs), Baseline integrity 40004015236: the final full run.

### Maintenance after Phase 3

- [x] Unnecessary files removed (2026-10-24): the 1.1.2 developer notes `TODO.txt`, `AFTERUPGRADE.txt` and `BUG FIXES.txt`, and 21 shipped files proven unused (dead PHP including `lib/mail.php`, orphaned scripts and unused images), each kept byte for byte at `historical-source-1.1.2`. Serialized-state inventory 84 / 44 / 38 / 45 / 1 (`UNNECESSARY-FILES-20261024.md`). Accepted at `acb31f6`, Modern core 40087707354 (all 12 jobs), Baseline integrity 40087707287.
- [x] License honored and dead weight removed (2026-10-24): the "View PHP Source" link opens the `sourceurl` setting (default the public repository; http or https only, escaped); a root `NOTICE`; `docs/THIRD-PARTY-LICENSES.md` (no incompatible license); a test that keeps `LICENSE.txt`, `README.txt` and the original about-page text and copyright lines; the disabled 410 endpoints (`payment.php`, `source.php`, `rawsql.php`, `logdnet.php`, `images/logdnet.php`), `paylog.php` with `PaymentInfo`, `paypal1`-`3.gif`, the PayPal request comments and `CHANGELOG.txt` removed, each kept at `historical-source-1.1.2`, with their dead nav links. Serialized-state inventory 83 / 43 / 38 / 44 / 1. Open rights items in `LICENSING-AND-PROVENANCE.md` (`UNNECESSARY-FILES-20261024.md`, "License and dead weight"). Accepted at `9fb11a6`, Modern core 40093585247 (all 12 jobs), Baseline integrity 40093585196.
- [x] Dead weight, round 2 (2026-10-24): the "Forgotten Password" link and `create.php?op=forgot` (now an unknown op, 400; `op=val` still 410); the PayPal and LoGDnet setting declarations and defaults (stored rows kept) and `logdnet_sanitize()`; the user editor's "Manage Payment Log" and "View source code" flags hidden, their bits kept as stored; `common.php`'s two installer links replaced by the command-line instruction; the unused web installer stages, `installer_functions.php` and `lib/pullurl.php` removed (`installer_stage_6.php` and `installer.php`'s 403 stay). A static test fails on any link to a missing or DISABLED page. Serialized-state inventory unchanged, 83 / 43 / 38 / 44 / 1 (`UNNECESSARY-FILES-20261024.md`, "Dead weight, round 2"). Accepted at `2e0c948`, Modern core 40103233755 (all 12 jobs), Baseline integrity 40103233707.
- [x] Installer pages fixed (2026-10-24; `754bf14`'s own Baseline integrity 40109324481 failed on the runner's PHP 8.3 and stays on record, Modern core 40109324440 succeeded; accepted through the next line, its first accepted descendant `9620489`): "not yet installed", "Database Connection Error" and "Upgrade Needed" render their text and the normal footer with the copyright line (the footer values are set before them; the tamper check is unchanged); signed-in pages show the copyright line again instead of `0` (PHP 8 comparison); the signed-in upgrade page no longer stops in the stats panel (`UNNECESSARY-FILES-20261024.md`, "Installer pages"). Accepted through `9620489`, Modern core 40113437111 (all 12 jobs), Baseline integrity 40113437055.
- [x] PHP tooling tests guarded (2026-10-24): tooling tests that run the application's PHP without a database skip, with the reason, on a PHP older than `composer.json` allows (Baseline integrity's system PHP 8.3), and fail instead in Modern core (`RESURRECTION_REQUIRE_PHP=1`) (`UNNECESSARY-FILES-20261024.md`, "Baseline on the runner's PHP"). Accepted at `9620489`, Modern core 40113437111 (all 12 jobs), Baseline integrity 40113437055.

### Current

- None. Phase 3 is complete and merged. The current phase is Phase 6, below.

### Remaining

Infrastructure and records:

- [x] Replace the PR #1 body with a short current summary that links here. Done by Jim by hand before the merge.

State and maintenance (remaining serialized business schemas, split in `SERIALIZED-SCHEMAS-PART1-20261017.md`; per-site status in `SERIALIZED-STATE-AUDIT.md`):

- None left: part 5 (translation arrays, other business state) is Done.

Final rescan (each line checked against the source at `1dfb7c5`, the route inventory's lines at `c00f2d1`; evidence doc in brackets). The route inventory (`ROUTE-INVENTORY-20261022.md`) lists every entry point; each of its OPEN entries is a line here:

To fix: none left. The last three (referer tracking, the request-value sweep, the admin repair path) closed with Final rescan task 3, Done above.

Recorded, not changed (kept as 1.1.2 behavior):

- A ban reason is shown to the banned visitor as raw HTML; staff write it (`BAN-MESSAGE-AND-ID-COOKIE-20261016.md`)
- Weapon, armor and title text allows color and format codes (`CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`)
- Clearing the `lgi` cookie evades an ID ban (`BAN-MESSAGE-AND-ID-COOKIE-20261016.md`)
- The clan-delete hook is not called on disband (`DELETION-EXPIRATION-AUTHORITY-20261017.md`)
- A leader's deletion locks the leader's account, and a member's clan action the member's own, before either locks the clan's members; started in the same instant they can deadlock, and InnoDB then rolls one of them back whole (error 1213, a 500 that changed nothing). Read from the code; the race test covers the two orders where one starts first (`RESCAN-TASK2-20261022.md`)
- The bad word list display groups words by their first byte, so a word that starts with a multibyte letter shows under an empty heading (`RESCAN-TASK2-20261022.md`)
- In the user editor, when the player's page changes the same field the admin changed, the player's value wins (`USER-EDITOR-AUTHORITY-20261016.md`)
- The unreachable `badnav.php` branch would still fail in `saveuser()` (`SERIALIZED-SCHEMAS-PART1-20261017.md`)
- `motd.php`: a signed-in player's own protected POST to an admin op still costs 10% experience with a news line; `newcount` paging never worked and still does not; the archive list skips its first month row (`SERIALIZED-SCHEMAS-PART5-20261022.md`)
- The healer sets hit points above the maximum back to it on view, for free; a shown commentary section (the gypsy's seance) stays usable for the session through the shared commentary helper (`ROUTE-INVENTORY-20261022.md`)
- Training refuses a Dragon Point history with an entry other than `hp`, `at`, `de` and `ff`, which a module could add through `dkpointlabels`; no bundled module does (`RESCAN-TASK3-20261023.md`)
- `increment_module_setting()` on a setting that does not exist yet adds to an unset cache entry (a PHP warning); no bundled module calls it (`RESCAN-TASK3-20261023.md`)
- The Hall of Fame shows the translated "Unknown" as raw HTML; translators write it, as every translated text (`RESCAN-TASK3-20261023.md`)
- The Graveyard views (`graveyard.php?op=enter`, `haunt`, `haunt2`, `haunt3`, `restore`) reset drunkenness through the Drinks header hook on view, so a dead player sobers there; forced navigation keeps a living player out, as in 1.1.2 (`PHASE3-MERGE-GATE-20261023.md`)

The unused `lib/mail.php` was recorded as excluded: nothing includes it, and a direct request got 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`). It was removed from `main` on 2026-10-24 (`UNNECESSARY-FILES-20261024.md`).

- [x] Final rescan: Done only when every "to fix" line above is Done or proven not reachable. Closed 2026-10-23 with Final rescan task 3 (accepted at `54e22e6`).

Merge gate findings (`PHASE3-MERGE-GATE-20261023.md`): none left; the four are Done above.

### Merge gate

PR #1 can be marked ready only when:

1. Every item above is DONE.
2. A final full run passes on both supported CI targets.
3. Historical integrity verifies unchanged.
4. A final GET-mutation and replay rescan finds no unclosed family.

Then merge with a normal merge commit, not a squash, so the full history stays intact. Phase 3 ends.

Verification, 2026-10-23 at `54e22e6` (`PHASE3-MERGE-GATE-20261023.md`): **NOT PASSED.** 1 not passed: the four merge gate findings above are open besides the PR #1 body. 2 pending: the final full run is the CI run on the commit that published the verification. 3 passed. 4 not passed: the Graveyard view pages still sober the player on GET. Phase 3 is not complete.

Re-check, 2026-10-23, with the merge gate findings closed (`PHASE3-MERGE-GATE-20261023.md`, "Merge gate findings closed"): 1 passed, every item Done except the PR #1 body, which is Jim's. 2 pending: the final full run is the CI run on the commit that closes the findings, and passes only with all 12 Modern core jobs and Baseline integrity SUCCESS. 3 passed. 4 passed: the Graveyard reset is kept as 1.1.2 behavior and the route inventory has no OPEN entry.

Final full run, 2026-10-23 at `19bf7a0` (`PHASE3-MERGE-GATE-20261023.md`, "Final full run recorded"): Modern core 40004015634 success, all 12 jobs; Baseline integrity 40004015236 success. 2 passed. All four gates PASSED.

The commit that recorded the final run, `93c468f`, passed its own CI before the merge: Modern core 40009875511 success, Baseline integrity 40009875538 success.

Merged, 2026-10-23: Jim merged PR #1 into `main` with a normal merge commit, `0d078df` (parents `999cec6` and `93c468f`; same tree as `93c468f`). Its push run on `main`: Modern core 40016472780 success, all 12 jobs; Baseline integrity 40016473210 success. Gate record: [`docs/PHASE3-MERGE-GATE-20261023.md`](docs/PHASE3-MERGE-GATE-20261023.md).

**Phase 3 DONE. Merged into `main` at `0d078df` on 2026-10-23.**

## Phase 4: Release foundation

**DONE** (started 2026-10-24, complete 2026-10-30 when CI accepted its last item at `8b33edf`). Goal: make the game ready for its first release.

The release itself stays excluded until Jim says so: hosting, tags, GitHub releases, an image, and the skin and archive-module rights (`docs/LICENSING-AND-PROVENANCE.md`).

### Decisions (Jim, 2026-10-24)

- No 1.1.2 server will be upgraded. All installs are fresh. No 1.1.2 database migration is built.
- Versioning: `MAJOR.MINOR.PATCH`, Resurrection's own numbers. The first release is 1.0.0; test builds are `1.0.0-rc.N`. Every release must upgrade cleanly from the one before. No version string changes in task 1A.
- The upgrade system (task 2) is built before anything ships.

### Checklist

- [x] 1A: start Phase 4 (docs only). Records the acceptance of `754bf14` (through `9620489`) and `9620489`; `CLAUDE.md` gets the current phase, the fresh-install-only exclusion and the versioning rule; `docs/MODERN-INSTALLATION.md` describes the install as it works today. Accepted at `b38bc21`, Modern core 40119918321 (all 12 jobs), Baseline integrity 40119918310.
- [x] 1B: a fresh install ready to play: activate the recommended modules during install, a guided config file step, and an "unsupported" message for a 1.1.2 database (`docs/PHASE4-TASK1B-FRESH-INSTALL-20261029.md`). Accepted at `504b5aa`, Modern core 40800637109 (all 12 jobs), Baseline integrity 40800637058.
- [x] 2: the version number and the upgrade system, built before anything ships (`docs/PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`). `ca4e660`'s own Modern core 40855211633 failed (`SqlSweepTest`, three unreviewed values in `lib/upgrade.php`) and stays on record. Accepted through `86f8e74`, its first accepted descendant: Modern core 40858859597 (all 12 jobs), Baseline integrity 40858859591.
  - [x] One version number, `1.0.0-dev`, in `src/Version.php`, read by the code, `project.json` and the docs check; the footer shows it and credits 1.1.2; the shown version is no longer the install marker.
  - [x] Schema state: the `upgrade_steps` ledger, numbered steps in `upgrades/`, bundled module upgrades through the lifecycle API, "Upgrade Needed" when behind, a refusal for a downgrade, a 1.1.2 database still unsupported.
  - [x] `php scripts/upgrade.php`: CLI only, the install's configuration, the install and maintenance locks, `--dry-run`, `--backup-confirmed`, stop at the first failure.
  - [x] Tests: the runner, an equivalence test with temporary test-only steps, and the release upgrade check in Modern core ("no release yet" until a release tag exists).
  - [x] Docs: `docs/VERSIONING.md` rewritten, "Upgrading" in `docs/MODERN-INSTALLATION.md`.
- [x] 3: one-command local setup: `python3 scripts/local-play.py up` brings up a playable local game from a clean clone, for local play and development only, never hosting (`docs/PHASE4-TASK3-LOCAL-SETUP-20261029.md`). Accepted at `0c39020`, Modern core 40950097216 (all 12 jobs), Baseline integrity 40950097228.
  - [x] Docker Compose (`local/compose.yaml`): PHP 8.4 with CI's php.ini and MariaDB 11.4; an empty database, the config written by `scripts/install.php --write-config` (0600), the recommended modules, the administrator, the game served, `php cron.php` on a schedule.
  - [x] Safe by default: the game on 127.0.0.1 only, no database port, generated credentials in the ignored `.local-play/`, the database password never printed, the administrator login printed once, labelled local use only.
  - [x] A second `up` keeps the game; when the code is newer than the database it says so, shows the backup and upgrade commands, and does not upgrade; `stop`, and `wipe` with the data volumes.
  - [x] Tests: the static test `tests/tooling/test_local_play.py`; the full flow run once from a clean clone (supporting evidence).
  - [x] Docs: "Play locally" in `docs/MODERN-INSTALLATION.md`.
- [x] Fix: the game no longer depends on PHP's output buffering. Found in task 3: with `output_buffering` off (PHP's default without a `php.ini`, and the official Docker image), every page of an installed game failed at `common.php:206`, because `lib/all_tables.php` (1.1.2) prints a newline before `common.php`'s buffer starts (`docs/PHASE4-OUTPUT-BUFFERING-FIX-20261029.md`). Accepted at `8b33edf`, Modern core 40958421349 (all 12 jobs), Baseline integrity 40958422672.

All of Phase 4's planned tasks are done. **Phase 4 DONE.**

## Phase 5: Resurrection UI

**COMPLETE** (2026-10-30 to 2026-11-05), accepted at the close-out commit `4f66f61`; Phase 6 task 1 recorded the acceptance on 2026-11-06. What the phase changed, task by task, with its decisions, licensing and open items: [`docs/PHASE5-SUMMARY-20261105.md`](docs/PHASE5-SUMMARY-20261105.md). Goal: one new dark fantasy UI, with original art, replaces all 1.1.2 skins. The plan and audit: [`docs/PHASE5-UI-PLAN-20261030.md`](docs/PHASE5-UI-PLAN-20261030.md). The design contract: [`docs/UI-DESIGN-SPEC.md`](docs/UI-DESIGN-SPEC.md).

The release stays excluded until Jim says go: hosting, tags, GitHub releases, an image, and the archive-module rights. The skins are removed (task 15), so their rights are no longer a release item.

### Decisions (Jim, 2026-10-30)

Recorded in `docs/PHASE5-TASK1-DIRECTION-20261030.md`:

1. The behaviorally faithful rule is dropped, for everything. Gameplay, balance, text and UI may change when a task prompt says so; each change from 1.1.2 is recorded (old vs new, why).
2. One new UI replaces all skins, built from the mockups Jim approved. When it lands, every 1.1.2 skin in `templates/` and skin selection are removed.
3. The game gets visuals: location scenes, creature portraits, item and stat icons, character portraits by race. Original SVG drawn by Claude for Jim / Phoenix 2:33 LLC.
4. The game stays PHP on this codebase, so other people can host it. A clean-room rewrite under AGPL-3.0-or-later is a possible future, not started.
5. Licensing: the core stays CC BY-NC-SA 2.0 with the original credits visible on every page, and `LICENSE.txt` and `README.txt` byte-identical to the tag; Jim's art in `assets/` under CC BY-SA 4.0 with its own LICENSE file; Jim's standalone code, shipped as a separate work, AGPL-3.0-or-later. Open question for Jim and a lawyer: AGPL code bundled here and run inside the NC core may leave hosts unable to satisfy both licenses; until Jim decides, no file is labelled AGPL.
6. The release, tags, deploy and hosting stay excluded until Jim says go.

### Checklist

The plan's 15 tasks (section 7 of the plan has each task's tests and acceptance checks). Each task is one commit. Done only when CI accepts it. Jim reordered tasks 5 to 15 on 2026-10-30 so the images come sooner (`docs/PHASE5-TASK4-HEADER-FOOTER-20261030.md`, "Task order"); the plan's own table keeps the original numbers, and this list is the order.

- [x] 1: Direction, governance and UI plan. `CLAUDE.md` rewritten, Phase 4 closed, `docs/UI-DESIGN-SPEC.md`, the plan, and `OutputBufferingTest` independent of test order (`docs/PHASE5-TASK1-DIRECTION-20261030.md`). Accepted at `836bb84`, Modern core 41071411434 (all 12 jobs), Baseline integrity 41071411378.
- [x] 2: Design tokens, fonts and color codes. `assets/ui/tokens.css` (palette, type, spacing, all 41 color codes), self-hosted woff2 for the three families with their OFL texts, `docs/THIRD-PARTY-LICENSES.md`, the `docs/CHANGES-FROM-1.1.2.md` ledger started, and their static tests (`docs/PHASE5-TASK2-TOKENS-20261030.md`). Accepted at `4606b4a`, Modern core 41077391337 (all 12 jobs), Baseline integrity 41077391247.
- [x] 3: New template and base layout. `templates/resurrection.htm` and `assets/ui/resurrection.css`: header, three columns, footer with `{copyright}`, Credits, Source and version; popup parts; stacking without sideways scroll; made the default skin (installer default and the fallbacks), old skins still selectable. The spec corrected to the approved mockup. Static tests for every template part and required tag, PHP-CLI renders of a signed-out page, a signed-in page and a popup (`docs/PHASE5-TASK3-TEMPLATE-20261030.md`). Accepted at `976a61e`, Modern core 41094820755 (all 12 jobs), Baseline integrity 41094820993.
- [x] 4: Header, footer, attribution and a quieter palette. Mail badge with the unread count; mail, MoTD and petition as real links (popup as an enhancement), the MoTD auto-open replaced by an unread mark; `{newday}` time to the next new day; the Credits link only where about.php is allowed; the Resurrection attribution line on every page and popup, with `NOTICE`; the quieter color code palette; character panel values in `--text`; in-text headings that repeat the page title removed (`docs/PHASE5-TASK4-HEADER-FOOTER-20261030.md`). Accepted at `5752253`, Modern core 41111713295 (all 12 jobs), Baseline integrity 41111713278.
- [x] 5: Image pipeline and the first art. `assets/` layout, `assets/LICENSE` (CC BY-SA 4.0) and README, `src/Ui/Art.php` lookups, fallbacks, the scene and portrait slots, alt text; the village and forest scenes and the race portraits (the command chat supplies the SVGs; `docs/PHASE5-TASK5-IMAGES-20261030.md`). Accepted at `98fb6ec`, Modern core 41120765631 (all 12 jobs), Baseline integrity 41120765632.
- [x] 6: Navigation. Nav groups, key badges, the key script as a static file with a JSON key map, 44px rows; hotkey choice unchanged; no nav column on a page with no nav (`docs/PHASE5-TASK6-NAVIGATION-20261030.md`). Accepted at `d0f9415`, Modern core 41129318951 (all 12 jobs), Baseline integrity 41129318835.
- [x] 7: Character panel. HP and XP bars, stat tiles, equipment rows, race portrait slot; `addcharstat()` rows from modules still shown (`docs/PHASE5-TASK7-CHARACTER-PANEL-20261030.md`). Accepted at `2a81203`, Modern core 41149508932 (all 12 jobs), Baseline integrity 41149508958.
- [x] 8: Phone layout. Bottom tab bar (in-page anchors: Go, Character, Talk, More), compact HP/XP header, stacking below the breakpoints (`docs/PHASE5-TASK8-PHONE-20261031.md`). Accepted at `5b3ca63`, Modern core 41159564504 (all 12 jobs), Baseline integrity 41159564474.
- [x] 9: Forms, buttons and tables. Labels on inputs, primary and secondary buttons, table styling and scroll boxes, `appoencode()` inline styles replaced by classes (`docs/PHASE5-TASK9-FORMS-20261031.md`). Accepted at `cf13929`, Modern core 41196666950 (all 12 jobs), Baseline integrity 41196666832.
- [x] 10: Battle screen, commentary and shop lists. Fighter cards with HP bars, Fight as the one primary with Run beside it, the battle log as an ordered list of rounds (no round label, a quiet divider between rounds; task 10 fix) and a result block; the commentary as a list with a button pager and a labelled form, 1.1.2's broken draft script removed; affordable shop items as links (`docs/PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md`). First run on `a9901d1` failed on PHP 8.4 (test helpers used a PHP 8.5-only DOM property); fixed in the task 10 fix. Accepted through `5dafec8`, Modern core 41261243410 (all 12 jobs), Baseline integrity 41261243423; `a9901d1`'s Modern core 41222167193 failed and stays on record.
- [x] 11: Popups, admin and superuser screens. Mail tabs and an inbox list with New badges (the popup's italics fixed at their source), the read view, petition, MoTD with its poll and the translator tool; admin tables in scroll boxes, the settings editors' tabs, every admin and editor input labelled, the staff petition counts as badges, the moderation page as the commentary list; the page-rendering tests pin the game clock; shard durations refreshed (`docs/PHASE5-TASK11-POPUPS-ADMIN-20261101.md`). Accepted at `28a3936`, Modern core 41311953273 (all 12 jobs), Baseline integrity 41311953262.
- [x] 12: Art: the remaining location scenes. The 17 place scenes (batch 2, approved by Jim on 2026-11-01) committed byte for byte with their checksums; each place's page and the bundled modules whose pages happen in a place show its scene, the alt text the place's name (the inn's and the village's from their settings); the scene list and mapping rules in the spec (`docs/PHASE5-TASK12-SCENES-20261105.md`) Accepted at `34a0617`, Modern core 41953000057 (all 12 jobs), Baseline integrity 41952999724.
- [x] 13: Art: the remaining icons. The 18 line icons (batch 3, approved by Jim on 2026-11-05) committed byte for byte with their checksums; an icon lookup; icons before the character panel's stat, bar, equipment and section labels (Resurrection template only); the tests' far-future dates pinned to the run's clock after a year-ahead run of the whole suite; shard durations refreshed (`docs/PHASE5-TASK13-ICONS-20261105.md`). Accepted at `6e2f55b`, Modern core 42029166571 (all 12 jobs), Baseline integrity 42029165803.
- [x] 14: Art: creature and master portraits. The 313 portraits (batch 4, approved by Jim on 2026-11-05) drawn from the parts kit, committed byte for byte with `SHA256SUMS`, the kit (`kit.json`, every seeded creature and master) and its generator (`scripts/art/build_creature_portraits.py`); creature, master and Green Dragon lookups with type fallbacks and the `creatureportrait` hook; each foe's portrait on its fighter card, a PvP victim's race portrait (Resurrection template only) (`docs/PHASE5-TASK14-PORTRAITS-20261105.md`). Accepted at `e3d265c`, Modern core 42055783450 (all 12 jobs), Baseline integrity 42055783444.
- [x] 15: Remove the 1.1.2 skins and skin selection, each removal recorded (`docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md`). Accepted at `5dfc363`, Modern core 42088078461 (all 12 jobs), Baseline integrity 42088078447.
- [x] Close-out: the safe credit frame (a built-in template in `src/Ui/SafeFrame.php` that `prepare_template()` renders when `templates/resurrection.htm` is missing, unreadable or fails its checks, with the attribution line now checked too, and the template notice for superusers only), the 7 unused 1.1.2 images deleted, and the Phase 5 summary (`docs/PHASE5-CLOSEOUT-20261105.md`, `docs/PHASE5-SUMMARY-20261105.md`). Accepted at `4f66f61`, Modern core 42098285495 (all 12 jobs), Baseline integrity 42098285516.

**Phase 5 COMPLETE**, accepted at `4f66f61`. Jim chose the next phase, Phase 6, on 2026-11-05.

Every change from 1.1.2 that a player, administrator or operator can notice is recorded in [`docs/CHANGES-FROM-1.1.2.md`](docs/CHANGES-FROM-1.1.2.md).

## Phase 6: Release readiness

**CURRENT** (opened 2026-11-06; Jim chose it on 2026-11-05). Goal: find and fix what stands between `main` and the first release candidate, `1.0.0-rc.1`. The release itself (tag, GitHub release, hosting, deploy) stays excluded until Jim says go. Task 1 is the readiness review; the later tasks come from its gap list, and Jim picks which run.

### Checklist

- [x] 1: Release readiness review for `1.0.0-rc.1` (docs only): Phase 5's acceptance recorded, Phase 6 opened, `main` audited against a 13-area checklist (fresh install, shared hosting, upgrades, versioning, licensing, security by review, a UI crawl of about 920 pages at 1440 and 390, gameplay tests, known defects, performance, modules, operations, CI health, docs), with a ranked gap list (`docs/PHASE6-RC-READINESS-20261106.md`). Verdict: not ready to cut; 4 BLOCKER, 13 SHOULD, 10 NICE. Accepted at `ea8bb3d`, Modern core 42190029147 (all 12 jobs), Baseline integrity 42190029178.
- [x] 2: Crash fixes, CI pin, first administrator's flags (B1, S8, S7): `list.php`'s online and clan lists select `hitpoints`; `stats.php`'s two `GROUP BY ... DESC` fixed, with a sweep and a static test; every CI job on `ubuntu-24.04`, `actions/checkout` v7.0.1 and `shivammathur/setup-php` 2.40.0 (node24) pinned by SHA; the first administrator gets 1.1.2's flags less the removed surfaces' (`docs/PHASE6-TASK2-FIXES-20261106.md`). Accepted at `fcce356`, Modern core 42206564929 (all 12 jobs), Baseline integrity 42206564872.
- [x] 3: Hardening: S1, S2, S4, S6, S12. Every response sends `X-Frame-Options`, `nosniff`, `Referrer-Policy` and an enforced Content Security Policy (`csp_mode`: enforce, report-only, off) from `src/Http/SecurityHeaders.php`; every inline script, handler and style moved to `assets/ui/`; internal PHP answers a direct request with an empty 404; an unreadable or damaged config gets a 503 operator page; `ext-ctype` and `ext-filter` declared; `LOGD_DBCONNECT` names a config file outside the docroot (`docs/PHASE6-TASK3-HARDENING-20261106.md`). Accepted at `b1de59d`, Modern core 42239340118 (all 12 jobs), Baseline integrity 42239340082.
- [x] 4: Web install, upgrade and maintenance: design and threat model (S3) (`docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`). Jim approved it on 2026-11-06 with every recommendation and two amendments (the section "Decisions (approved 2026-11-06)"). Accepted at `668cf4e`, Modern core 42247060344 (all 12 jobs), Baseline integrity 42247060378.
- [x] 5a: Shared services, operator frame, `cron.php` fix, web maintenance (S3 part 1): the install, upgrade and maintenance services the commands and web pages share, the upgrade's time budget, `cron.php`'s exit 1 without a config (F1), the operator frame, `webcron.php` and Game Settings' web maintenance (`docs/PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md`). Accepted at `281fcf0`, Modern core 42281955893 (all 12 jobs), Baseline integrity 42281955860.
- [x] 5b: The web setup page, `setup.php` (S3 part 2): token-gated install and upgrade for hosts without a shell, the upgrade in parts, the post-install maintenance choice, and one rule for pages that show a secret once (step 0) (`docs/PHASE6-TASK5B-SETUP-PAGE-20261106.md`). CI at `af0337c` failed on the race test's second server (Modern core 42296406057, kept on record); the fix (a port the OS chose, a readiness wait that fails clearly) and `setup.php` on the Upgrade Needed page followed. Accepted through the fix at `73cc1da`, Modern core 42321788194 (all 12 jobs), Baseline integrity 42321788112.
- [x] 6: Host guide (B2), covering both the shell and the web path: `docs/HOST-GUIDE.md`, sample server rules in `docs/hosting/` (nginx, Apache, `.htaccess`, not active) checked by `tests/tooling/test_host_guide.py` and, locally, behind real nginx with PHP-FPM and Apache with mod_php; the server URL a fresh install over HTTPS stores is fixed (`docs/PHASE6-TASK6-HOST-GUIDE-20261107.md`). Accepted at `5f2d951`, Modern core 42329102594 (all 12 jobs), Baseline integrity 42329102592.
- [x] 7: Front-door docs, rc.1 cut materials, legal packet (B3, B4, S9), and the `requirevalidemail` lock: Game Settings shows it locked off and refuses a change, and the game treats it as off; `README.md`, `VERSIONING.md`, `SECURITY.md`, `SECURITY-STATUS.md` and `MODULE-POLICY.md` match the code; the draft release notes (`docs/RELEASE-NOTES-1.0.0-rc.1.md`), the cut checklist with the tag message (`docs/RC-CUT-CHECKLIST.md`) and the version rule for `main` after a tag; the release package script (`scripts/build-release-package.py`); the legal review packet (`docs/LEGAL-REVIEW-PACKET-20261107.md`) (`docs/PHASE6-TASK7-RC-MATERIALS-20261107.md`). Accepted at `01cd708`, Modern core 42365272202 (all 12 jobs), Baseline integrity 42365272387.
- [x] 8: Records, shard rebalance, `LOGINTIMEOUT` bounds, labels and tap targets (S5): task 7's acceptance recorded; `tests/tooling/shard-durations.json` refreshed from run 42365272202's per-test times on both legs (the slowest PHP 8.5 shard projected at 19.0 minutes, from 20.5); Game Settings takes `LOGINTIMEOUT` only from 300 to 86400 seconds and says why it refuses anything else, and a stored value outside that range is read as its nearest end; the S5 inputs labelled and the S5 targets at least 44px; the release notes updated (`docs/PHASE6-TASK8-20261112.md`). Accepted at `3dba4d1`, Modern core 43147065548 (all 12 jobs; two PHP 8.5 shards over 21 minutes, 22:38 and 23:26), Baseline integrity 43147065448.
- [x] 9: CI shard time, stale form tokens after signing in again, phone layout: the `http` jobs install the game with `FreshInstallTest` only (the full PHPUnit suite stays in `checks`), five shards per leg (14 jobs), and `tests/tooling/shard-durations.json` holds each test's largest time over two runs, both legs (`scripts/tooling-shards.py --refresh-durations`); the kept copy of a page holds no form token, and `badnav.php` fills its forms from the session that shows it, so a player who signs in again mid-page carries on while the old tokens stay refused; on a phone `create.php` and the companion and mount editors put each label above its field, the player-facing lists fit the screen, and admin tables scroll in their own box (`docs/PHASE6-TASK9-20261112.md`). Accepted at `2a6e2a7`, Modern core 43181214497 (all 14 jobs; slowest 17:55), Baseline integrity 43181214600.
- [x] 10: Security status record (S13) and module author guide (S10): task 9's acceptance recorded; `docs/SECURITY-STATUS.md` as a living record, one current row per area (status, control, evidence by file and test, what is left over) and the public hosting gates as a checklist, with the rule that a task changing a control updates its row in the same commit (also in `CLAUDE.md`); `docs/MODULE-AUTHOR-GUIDE.md` for writing or porting a module (hooks, presentation, the CSP, the entry guard, POST and CSRF, secrets, SQL, versions, admission), pointed to by `docs/MODULE-POLICY.md`, with `tests/tooling/test_module_author_guide.py` checking every name and example against the code (`docs/PHASE6-TASK10-20261112.md`). Accepted at `f1c92b2`, Modern core 43228986117 (all 14 jobs; slowest 17:36), Baseline integrity 43228986075.
- [x] 11: S9 closed by Jim's decision and S11 moved into rc.1 (both recorded), task 10's acceptance recorded; every statement of the credit and of what hosts must do under the licenses says only what the licenses say (the credit as section 4(d) of CC BY-NC-SA 2.0 describes it, `LICENSE.txt` quoted, no added term), kept so by `tests/tooling/test_credit_wording.py`; `installer.php`'s 403 names the two real ways to install, `php scripts/install.php` and `setup.php` (`docs/PHASE6-TASK11-20261112.md`). Accepted at `2999947`, Modern core 43239302566 (all 14 jobs; slowest 15:24), Baseline integrity 43239302397.
- [x] 12: Mail, password recovery and email verification: design and threat model (S11), for Jim's approval; task 11's acceptance recorded. Account mail behind a Game Settings switch that turns on only after a test mail confirmed by its link; recovery by login or address with one answer for every case; 256-bit tokens stored hashed, single use, bound to `authversion`, exchanged on first open for a session grant and a clean URL; verification at sign-up and from Preferences; address changes confirmed by the new address with a notice to the old; limits per account, address, requester and in all; `requirevalidemail` allowed only while account mail is on; the schema as upgrade step `0001`; a threat model, the build's tests, and a build split into 13a, 13b and 13c, with nine open questions (`docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md`). Design only. Jim approved it on 2026-11-12 with every recommendation and one addition, the verify prompt for 13c (the section "Decisions (approved 2026-11-12)"). Accepted at `ae4bd3b`, Modern core 43245774215 (all 14 jobs; slowest 17:11), Baseline integrity 43245774307.
- [x] 13a: Account mail foundation (S11 part 1): the schema as upgrade step `0001` and in the fresh install; one mail path, `resurrection_send_email()`, with links from an `https://` `serverurl` only and an anchored address validator; F1 fixed; the token and limit libraries with maintenance cleanup; `account.php` (`open` and `confirm` for the test mail only); Game Settings' Account mail section (status, test mail, confirmation, switch); mail capture for every test server with a guard. Nothing new for players; `requirevalidemail` stays locked (`docs/PHASE6-TASK13A-20261112.md`). Published at `a2fd481`: Modern core 43294075991 FAILED (13 of 14 jobs; the PHP 8.4 `http (2)` job, on a mail capture test that expected PHP 8.5's `mail()` warning), Baseline integrity 43294075746 SUCCESS. Fixed in the follow-up commit (the test checks what both versions guarantee; a rule in `CLAUDE.md` runs new tooling tests on both legs before a push); Accepted at `127c058`, through the fix: Modern core 43312025080 (all 14 jobs; slowest 16:58), Baseline integrity 43312024999.
- [x] 13b: Password recovery (S11 part 2): `account.php` `forgot`, `open` and `reset`; the sign-in link; the notices; staff handling and the staff switch. Built in `docs/PHASE6-TASK13B-20261112.md` (one answer for every request, the work after the response, the reset in one transaction, the notice mail, staff notices to every other megauser, the staff switch in Game Settings' Account mail; recovery only while account mail is on and only to a verified address). Accepted at `a4c2ea7`, Modern core 43333363270 (all 14 jobs; slowest 17:26), Baseline integrity 43333363385.
- [ ] 13c: Verification, email change and the lock lifted (S11 part 3): Preferences verify and change (F2), sign-up verification, sign-in refusal and resend, notifications to verified addresses only while account mail is on (F3), the verify prompt, `requirevalidemail` read as on only while account mail is on, the host guide and release notes. Built in `docs/PHASE6-TASK13C-20261113.md` (pending CI acceptance).

What stands between `main` and the `v1.0.0-rc.1` tag, in order (`docs/RC-CUT-CHECKLIST.md`; Jim's decisions of 2026-11-12):

1. Task 11's CI acceptance. Done: accepted at `2999947`.
2. The S11 design and threat model (mail, account recovery, email verification), approved by Jim. Written in task 12 (`docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md`); approved by Jim on 2026-11-12. Done.
3. The S11 build (13a, 13b, 13c), accepted by CI on both legs and Baseline, with the `requirevalidemail` lock lifted as the design says, and the release notes and host guide updated. Built: 13a accepted at `127c058`, 13b at `a4c2ea7`; 13c built in task 13c (lock lifted, release notes and host guide updated), waiting for its CI acceptance.
4. Jim's go for the cut.
5. The version commit (`src/Version.php`, `project.json`, `docs/VERSIONING.md`, the pinned value in `tests/tooling/test_version.py`, the release notes made final) and its CI acceptance on both legs and Baseline.
6. The annotated tag `v1.0.0-rc.1` on that accepted commit, on Jim's go; then the release package built from the tag, twice with the same SHA-256, and attached nowhere until Jim says where.

S9 is closed by Jim's decision (no legal counsel). Nothing else is a blocker for the tag. The recorded 1.1.2 behaviors ship as known issues in the release notes; S11's known-issue entry is gone since task 13c: the release notes list it among the changes, and keep as known issues that mail is PHP `mail()` only, best effort, and that a link's token reaches the web server's access log. The stale form tokens after signing in again are fixed in task 9.

Found during Phase 6 (not yet scheduled to a task unless it says so):

- [x] Signing in again in the middle of the new-day questions replays a page (`badnav.php`) that carries the earlier session's form tokens, so its buttons answer "Invalid form submission." (403). Already present at `fcce356`. Found in task 3 (`docs/PHASE6-TASK3-HARDENING-20261106.md`). Fixed in task 9 (`docs/PHASE6-TASK9-20261112.md`). Accepted at `2a6e2a7`.
- [x] `cron.php` run with no configuration file prints the HTML not-installed page and exits 0, so a scheduler records success. Found in task 4 (`docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md`, F1); fixed in task 5a (exit 1, one fixed line on stderr). Accepted at `281fcf0`.
- [x] The configuration page from task 3 (S4) still shows the MoTD and Petition for Help links and the Online Characters panel. None of them can work without a configuration. Task 5a draws it, the not-installed page and the "not a completed installation" page in the operator frame without them. Accepted at `281fcf0`.
- [x] The expiration warning mail (`lib/char_expiration.php`) takes its link from `getsetting("serverurl", <a URL built from the request>)`, not `resurrection_server_url()`: a stored value goes into the mail unvalidated, and with no `serverurl` row the request-built default is saved as the setting. Found in task 12 (F1); fixed in task 13a (`resurrection_expire_warning_body()`, the configured server URL only, nothing saved; `CharacterDeletionTest::testExpirationWarningLinkComesFromServerUrl`).
- [x] `prefs.php` changes the email address with no current password and no verification, checks it with the unanchored `is_email()`, saves a failing value when `requireemail` is off, and skips `blockdupeemail`. Found in task 12 (F2); fixed in task 13c on every host: Preferences' own address section needs the current password, the anchored check and `blockdupeemail`, and while account mail is on a confirmation by the new address; `saveuser()` never writes the address (`MailEmailChangeTest::testNeedsCurrentPasswordWithAccountMailOnAndOff`, `test_account_verification_http.py::test_email_change_needs_password_and_confirmation`).
- [x] Notification mail (`emailonmail`) goes to any address a player typed, so a player can point a stranger's inbox at the game. Found in task 12 (F3); fixed in task 13c: while account mail is on, new-mail and expiration notices go only to verified addresses; while it is off, as 1.1.2 (decision 7; `MailVerificationTest::testNotificationMailGoesOnlyToVerifiedAddressesWhileAccountMailIsOn`).

Jim's decisions (2026-11-06):

1. rc.1 is for public hosts too. B2 (host guide) stays a BLOCKER, and these become rc.1 blockers as well: S1 (security headers; CSP as far as task 3 gets it), S2 (internal-file guard), S3 (no-shell path), S4 (unreadable-config page), S6 (declared extensions), S7 (admin flags), S8 (CI pin), S9 (legal review, Jim and a lawyer), S12 (config outside the docroot).
2. First administrator's flags: 1.1.2's set, minus the flags for surfaces that were removed.
3. No-shell hosts: build a token-gated web install and upgrade page and a secret-protected web maintenance (cron) request. Design and security review first, Jim approves, then build. The 1.1.2 web installer stays 403; this is a new surface, not that one.
4. Proposed by the command chat, open for Jim to overturn: S11 (password recovery, email verification) is a documented known limitation for rc.1 and a 1.0.0 blocker.

Jim's decisions (2026-11-07):

1. rc.1 ships only the sample server rules in `docs/hosting/`. No active `.htaccess`.
2. S11 (account recovery and email verification) is a known limitation for rc.1 and a blocker for 1.0.0 (decision 4 above, confirmed). Until it ships, `requirevalidemail` stays off and locked (task 7).

Jim's decisions (2026-11-12):

1. S9: no legal counsel. The project meets the licenses as written, and every statement of the credit rule stays within what the licenses allow. The legal review packet (`docs/LEGAL-REVIEW-PACKET-20261107.md`) was not sent and is kept as a record. Task 11 brings every statement of the credit and of hosts' duties within the licenses' own words.
2. S11 (mail, account recovery and email verification) is an rc.1 blocker. This replaces decision 2 of 2026-11-07, which made it a known limitation for rc.1. The order: a design and threat model first, approved by Jim; then the build; then the `requirevalidemail` lock lifted as the design says.

The gap list (IDs, scope, tests and sizes in the review). Each item is ticked when the task that closes it is accepted:

BLOCKER for `1.0.0-rc.1`:

- [x] B1: Fix the 500s the crawl found: the Warriors Online list (`list.php`) whenever a dead player is online, and `stats.php?op=referers` and `op=graph` on MySQL 8.4 (S). Task 2.
- [x] B2: Write the host guide, with sample web server rules: docroot and denied paths, the config file's place, owner and mode, PHP version, extensions and production settings, HTTPS, cron, mail, logs, backups, upgrades, `sourceurl`, what to upload; both the shell path and the web path (M). Task 6 (`docs/HOST-GUIDE.md`).
- [x] B3: Refresh the front-door docs: `README.md`, `docs/VERSIONING.md`, `docs/SECURITY-STATUS.md`, `docs/MODULE-POLICY.md`, `project.json`'s `phase` (S). Task 7, with `SECURITY.md`'s hosting banner; `project.json`'s `phase` is left for the version commit (no test reads it). Accepted at `01cd708`.
- [x] B4: Prepare the rc.1 cut, stopping before the tag: release notes from `docs/CHANGES-FROM-1.1.2.md`, the tag message, the version rule for `main` after a tag, the cut checklist (S). Task 7 (`docs/RELEASE-NOTES-1.0.0-rc.1.md`, draft; `docs/RC-CUT-CHECKLIST.md`; `docs/VERSIONING.md`), with the release package script. Accepted at `01cd708`.
- [x] S1: Security headers (frame protection, `nosniff`, Referrer-Policy), then a Content Security Policy as far as task 3 gets it (S, then M). Task 3.
- [x] S2: Refuse direct requests to internal PHP files (`lib/`, `src/`, `modules/`) with a 404 (M). Task 3.
- [x] S3: The no-shell path: a token-gated web install and upgrade page and a secret-protected web maintenance request (L). Tasks 4, 5a (web maintenance) and 5b (the setup page).
- [x] S4: A clear page, not a blank 500, when `dbconnect.php` exists but cannot be read (S). Task 3.
- [x] S6: Declare `ext-ctype` and `ext-filter` in `composer.json` (S). Task 3.
- [x] S7: The first administrator's flags: 1.1.2's set less the removed surfaces', recorded in the change ledger (S). Task 2.
- [x] S8: Pin the CI runner to `ubuntu-24.04` and move `actions/checkout` and `shivammathur/setup-php` to Node 24 releases (S). Task 2.
- [x] S9: Legal review of the attribution wording and the AGPL question (Jim and a lawyer). Closed by Jim's decision on 2026-11-12: no legal counsel; the project meets the licenses as written, and every statement of the credit rule stays within what the licenses allow (task 11). The packet was not sent and is kept as a record (`docs/LEGAL-REVIEW-PACKET-20261107.md`). No file is labeled AGPL.
- [x] S12: Let the game read its config from outside the docroot (S). Task 3.
- [x] S11: Mail, account recovery and email verification (L; a public hosting gate). An rc.1 blocker since Jim's decision of 2026-11-12 (it was a known limitation for rc.1 and a 1.0.0 blocker, 2026-11-07). Design and threat model first, approved by Jim; then the build; then the `requirevalidemail` lock lifted as the design says. Design and threat model written in task 12 (`docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md`), approved by Jim on 2026-11-12; the build is 13a, 13b and 13c. 13a, the foundation, is accepted at `127c058`; 13b, password recovery, at `a4c2ea7`; 13c, verification, the address change and the lock lifted, is built (`docs/PHASE6-TASK13C-20261113.md`; pending CI acceptance).

SHOULD before 1.0.0, not scheduled:

- [x] S5: Labels and 44px tap targets on the pages the crawl found (S). Task 8 (`docs/PHASE6-TASK8-20261112.md`). Accepted at `3dba4d1`.
- [x] S10: A guide for module authors (S). Task 10 (`docs/MODULE-AUTHOR-GUIDE.md`, kept true by `tests/tooling/test_module_author_guide.py`). Accepted at `f1c92b2`.
- [x] S13: Bring `docs/SECURITY-STATUS.md` up to date as a living record (S). Task 10 (one current row per area, the public hosting gates as a checklist, the update rule in `CLAUDE.md`). Accepted at `f1c92b2`.

The 10 NICE items (N1 to N10) are listed in the review, not here, and are not scheduled. One NICE item found since, not scheduled:

- [ ] N11: Settings that do nothing (task 7's settings sweep, `docs/PHASE6-TASK7-RC-MATERIALS-20261107.md`): no code reads `newdaycron`, `show_notices`, `notify_on_warn`, `notify_on_error`, `notify_address` or `notify_every` (`newdaycron`'s notes still cite 1.1.2's cron steps and the DragonPrime wiki), and `beta` and `betaperplayer` do nothing visible because `pavilion.php` is not in the tree. Decide whether to remove them from Game Settings or say in each that it does nothing. Recorded in task 8; unchanged (S).

### Public hosting

Public hosting is not approved. It has its own gates: modern account recovery and email verification (met in the code by S11, Phase 6 tasks 13a to 13c; `docs/SECURITY-STATUS.md`), abuse controls, production TLS/proxy/session configuration, internal-file protection, isolation and least privilege, backup and restore testing, monitoring, and independent review. Release readiness does not equal hosting.

## Supported targets

- PHP 8.4 / MariaDB 11.4
- PHP 8.5 / MySQL 8.4

## Updating this file

Update this file in the same commit that closes or opens a checklist item. Move items between Done, Current and Remaining. Do not copy evidence here; link to it.
