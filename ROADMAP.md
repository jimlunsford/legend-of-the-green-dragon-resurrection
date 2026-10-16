# Roadmap

The single high-level plan for Legend of the Green Dragon - Resurrection.

Checkpoint documents hold the evidence. This file holds the plan. When they disagree, the checkpoint evidence wins and this file gets corrected.

## Goal

Modernize the code. Preserve the game.

Bring LoGD 1.1.2 DragonPrime Edition onto supported PHP and databases with every important state transition examined, constrained and tested. Keep the gameplay, atmosphere, odd historical behavior and module system intact.

The finish line for this roadmap is the merge of PR #1 into `main`. What happens after that is a separate decision.

## Phases

| Phase | Purpose | Status | Record |
|---|---|---|---|
| Foundation | Preserve the exact 1.1.2 source, provenance, historical tag and manifests. Inventory compatibility and security. No modernization claims. | DONE | `docs/FOUNDATION-CHECKPOINT-20260913.md` |
| Phase 1 | First modern engineering base: PHP/database compatibility, PDO direction, CI, static analysis, early installer and runtime work. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913.md` |
| Phase 2 | A real modern base application: strict fresh install, modern credentials, authentication, session rotation, CSRF infrastructure, account creation, village, commentary, logout, all 24 bundled modules installable. | DONE | `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE2.md` |
| Phase 3 | Close every game and application trust boundary until the modern core is mergeable. | **CURRENT** | `docs/MODERN-CORE-CHECKPOINT-20260913-PHASE3*.md`, `docs/CORE-ROUTE-SECURITY.md` |
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

### Current

- [ ] None in progress. Choose the next bounded task from Remaining.

### Remaining

Infrastructure and records:

- [ ] Replace the PR #1 body with a short current summary that links here.

Route families:

- [ ] News formatting fatal: a stored taunt with a stray `%` (from a database older than the taunt editor's check) makes `news.php` and `bio.php` fail on PHP 8 in `sprintf_translate()` (`lib/translator.php`), found during content editors part 1 (`CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`)

Administration:

- [ ] Content editors part 1: weapons, armor, taunts, titles, including the title rebuild (`weaponeditor.php`, `armoreditor.php`, `taunt.php`, `titleedit.php`; candidate in `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`)
- [ ] Content editors part 2: creatures, masters (`creatures.php`, `masters.php`)
- [ ] Content editors part 3: mounts and companions: object add, edit, save, delete, give, take, activate, deactivate, and the owner refunds on mount delete (`mounts.php`, `companions.php`). The `mounts.php` module preference editor is already closed (shared typed settings editor and mount object preferences, above).
- [ ] Module manager and remaining preference editors (`modules.php`)

State and maintenance:

- [ ] Remaining serialized business schemas: general combat, mounts, companions, buffs, translation arrays, preferences, editor old values, navigation (`SERIALIZED-STATE-AUDIT.md`)
- [ ] Expiration cleanup: `last_char_expire` must not advance before cleanup succeeds (`lib/expire_chars.php`)
- [ ] Account deletion failure and retry semantics (`lib/charcleanup.php`), including the user editor's delete (`user.php?op=del`, `lib/user/user_del.php`)
- [ ] Truthful completion reporting for external email and `OPTIMIZE TABLE`

### Merge gate

PR #1 can be marked ready only when:

1. Every item above is DONE.
2. A final full run passes on both supported CI targets.
3. Historical integrity verifies unchanged.
4. A final GET-mutation and replay rescan finds no unclosed family.

Then merge with a normal merge commit, not a squash, so the full history stays intact. Phase 3 ends.

## After Phase 3

No direction is chosen yet. That is deliberate. Direction gets decided after the code is done, not before.

Options on the table at that point:

- Tag a Resurrection release (`v0.1.0` per `docs/VERSIONING.md`)
- Run a server
- Keep it as a preserved, runnable reference edition
- Something else

Public hosting has its own gates regardless of direction: modern account recovery and email verification, abuse controls, production TLS/proxy/session configuration, internal-file protection, isolation and least privilege, backup and restore testing, monitoring, and independent review. Merge does not equal hosting.

"Do not start Phase 4" means: do not make direction decisions or begin post-merge work until the merge gate passes.

## Supported targets

- PHP 8.4 / MariaDB 11.4
- PHP 8.5 / MySQL 8.4

## Updating this file

Update this file in the same commit that closes or opens a checklist item. Move items between Done, Current and Remaining. Do not copy evidence here; link to it.
