# Roadmap

The single high-level plan for Legend of the Green Dragon - Resurrection.

Checkpoint documents hold the evidence. This file holds the plan. When they disagree, the checkpoint evidence wins and this file gets corrected.

## Goal

Modernize the code. Preserve the game.

Bring LoGD 1.1.2 DragonPrime Edition onto supported PHP and databases with every important state transition examined, constrained and tested. Keep the gameplay, atmosphere, odd historical behavior and module system intact.

The finish line for this roadmap was the merge of PR #1 into `main`, reached on 2026-10-23 at `0d078df`. What happens after that is a separate decision.

## Phases

| Phase | Purpose | Status | Record |
|---|---|---|---|
| Foundation | Preserve the exact 1.1.2 source, provenance, historical tag and manifests. Inventory compatibility and security. No modernization claims. | DONE | `docs/FOUNDATION-CHECKPOINT-20260913.md` |
| Phase 1 | First modern engineering base: PHP/database compatibility, PDO direction, CI, static analysis, early installer and runtime work. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913.md` |
| Phase 2 | A real modern base application: strict fresh install, modern credentials, authentication, session rotation, CSRF infrastructure, account creation, village, commentary, logout, all 24 bundled modules installable. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE2.md` |
| Phase 3 | Close every game and application trust boundary until the modern core is mergeable. | DONE, merged into `main` at `0d078df` on 2026-10-23 | [`docs/PHASE3-MERGE-GATE-20261023.md`](docs/PHASE3-MERGE-GATE-20261023.md), `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE3*.md`, `docs/CORE-ROUTE-SECURITY.md` |
| After Phase 3 | Choose a direction. | NOT STARTED | This file, when decided |

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
- [x] Installer pages fixed (2026-10-24, candidate; `754bf14`'s own Baseline integrity 40109324481 failed on the runner's PHP 8.3 and stays on record, Modern core 40109324440 succeeded; accepted only through the next line, its first descendant): "not yet installed", "Database Connection Error" and "Upgrade Needed" render their text and the normal footer with the copyright line (the footer values are set before them; the tamper check is unchanged); signed-in pages show the copyright line again instead of `0` (PHP 8 comparison); the signed-in upgrade page no longer stops in the stats panel (`UNNECESSARY-FILES-20261024.md`, "Installer pages").
- [x] PHP tooling tests guarded (2026-10-24, candidate): tooling tests that run the application's PHP without a database skip, with the reason, on a PHP older than `composer.json` allows (Baseline integrity's system PHP 8.3), and fail instead in Modern core (`RESURRECTION_REQUIRE_PHP=1`) (`UNNECESSARY-FILES-20261024.md`, "Baseline on the runner's PHP").

### Current

- None. Phase 3 is complete and merged. No phase is active until Jim picks a direction.

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

## After Phase 3

No direction is chosen yet. That is deliberate. Direction gets decided after the code is done, not before.

Options on the table at that point:

- Tag a Resurrection release (`v0.1.0` per `docs/VERSIONING.md`)
- Run a server
- Keep it as a preserved, runnable reference edition
- Something else

Public hosting has its own gates regardless of direction: modern account recovery and email verification, abuse controls, production TLS/proxy/session configuration, internal-file protection, isolation and least privilege, backup and restore testing, monitoring, and independent review. Merge does not equal hosting.

"Do not start Phase 4" means: do not make direction decisions or begin post-merge work until Jim picks a direction and names it.

## Supported targets

- PHP 8.4 / MariaDB 11.4
- PHP 8.5 / MySQL 8.4

## Updating this file

Update this file in the same commit that closes or opens a checklist item. Move items between Done, Current and Remaining. Do not copy evidence here; link to it.
