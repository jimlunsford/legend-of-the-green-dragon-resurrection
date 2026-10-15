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

### Current

- [ ] None in progress. Choose the next bounded task from Remaining.

### Remaining

Infrastructure and records:

- [ ] Replace the PR #1 body with a short current summary that links here.

Route families:

- [ ] Bank: deposit, withdraw, transfer, loan (`bank.php`; candidate in `BANK-AUTHORITY-20261015.md`)
- [ ] Weapons and armor purchase and trade-in (`weapons.php`, `armor.php`)
- [ ] Stables and mount purchase, sale, replacement (`stables.php`)
- [ ] Haunting (`lib/graveyard/case_haunt*.php`)
- [ ] Bio moderation (`bios.php`)
- [ ] Donation administration (`donators.php`)

Administration:

- [ ] User editor (`user.php`)
- [ ] Content editors: creatures, weapons, armor, mounts, titles, taunts
- [ ] Module manager and remaining preference editors (`modules.php`)

State and maintenance:

- [ ] Remaining serialized business schemas: general combat, mounts, companions, buffs, translation arrays, preferences, editor old values, navigation (`SERIALIZED-STATE-AUDIT.md`)
- [ ] Expiration cleanup: `last_char_expire` must not advance before cleanup succeeds (`lib/expire_chars.php`)
- [ ] Account deletion failure and retry semantics (`lib/charcleanup.php`)
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
