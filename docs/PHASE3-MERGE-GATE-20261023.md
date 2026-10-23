# Phase 3 merge gate verification, 2026-10-23

An independent check of the four merge gate conditions in `ROADMAP.md`, made by a session that had not seen the
Phase 3 work. Earlier reports were not trusted: each check below was made against the source, a running copy of the
application and the GitHub Actions API. This task changes no product code; it does not merge, mark PR #1 ready or
edit its body.

## Result: NOT PASSED

| Gate | Result | Why |
| --- | --- | --- |
| 1. Every item above is DONE | NOT PASSED | Every Done line checks out, and rescan task 3 is now accepted, which closes the Final rescan item. But this task opens four items (findings 1 to 4) besides the expected PR #1 body. |
| 2. A final full run passes on both supported CI targets | PENDING | The final full run is the CI run on the commit that publishes this document; its IDs and state are in the task report. No earlier run stands in for it. |
| 3. Historical integrity verifies unchanged | PASSED | Tag object, source commit, source tree, 417 files and 11 preservation commits as in `CLAUDE.md`; `main` is `999cec6`. |
| 4. A final GET-mutation and replay rescan finds no unclosed family | NOT PASSED | Finding 1: five Graveyard view pages still change player state on GET. |

Phase 3 is not complete.

## Starting state

- Expected and actual start: `54e22e6f83fb624eb019e38bb5ffe0e6ddb3668c` (matched). Remote branch and PR #1 head the
  same; PR #1 open, draft, not merged. `main` `999cec6f9c655a840320d982d673bb863c68c2b2`.
- Toolchain built from the `CLAUDE.md` notes: PHP 8.4 with the CI extensions, Composer, MariaDB 11.4 in disposable
  Docker containers.

## Findings

1. **Graveyard view pages sober the player on GET (gap, gate 4).** `graveyard.php?op=enter`, `haunt`, `haunt2`,
   `haunt3` and `restore` call `page_header()`, which runs the `header-graveyard` hook on view
   (`lib/pageparts.php:47` skips it only for combat and the Ramius pages). The bundled Drinks hook
   (`modules/drinks/dohook.php`, `header-graveyard`) then sets `drunkeness` to 0, outside any transaction. A probe
   set `drunkeness` to 50 and sent one GET each: 50 became 0 on all five, for a dead player and for a living one
   (the hook runs before the redirect to the village); `op=question` and the default page left it at 50. 1.1.2 did
   this on every Graveyard page. This branch moved the hook inside the transaction for torment combat
   (`GRAVEYARD-COMBAT-AUTHORITY-20260927.md`, which says a GET never "sobers the player") and for the Ramius
   question and ritual (`RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md`), but no record keeps it on the five view
   pages, and the route inventory lists `graveyard.php?op=enter|question` as changing nothing. The write is small and
   repeatable without gain beyond the first (it only ever sets 0; a player over 66 would otherwise lose one turn to
   the New Day hangover), and the protected actions on these pages (the favor spend, the haunt) did not run on GET.
   It is still a GET state change with no recorded decision, so the gate does not pass. Not fixed here. The choice is
   to record it as kept 1.1.2 housekeeping or to run the hook only inside a protected action.
2. **Route inventory row for `pvp.php` (records).** The inventory covers request keys other than `op` and `subop` by
   the row of the op that reads them. `pvp.php?act=attack` is read with an empty `op`, so it falls under the
   `pvp.php` row, which says "no" and READ-ONLY. It is the PvP entry: a GET that asks and a POST with CSRF and a
   one-use intent (`PVP-COMBAT-AUTHORITY-20260927.md`, `pvp.php:30-71`), so CLOSED, not read-only. The other
   non-`op` keys that reach a write are named by their rows (the inn's `act` and `pay`, the Drinks purchase,
   Cedrik, Lovers).
3. **Serialized-state records (records).** A fresh token recount of shipped PHP at `54e22e6` (`serialize(`,
   `ScalarState::read(`, `unserialize(`; tests, scripts and lab excluded) gives 94 sites, 45 files, 38 serialize,
   55 ScalarState reads, 1 unserialize. `CLAUDE.md` and `SERIALIZED-STATE-AUDIT.md` give 91 / 44 / 38 / 52 / 1, which
   is the same recount at `c00f2d1`. The differences: the three reads of the admin repair path
   (`lib/stored_state_repair.php:82` buff list, `:96` stored fight, `:137` Dragon Point history; rescan task 3), and
   the companion hire check moved from `mercenarycamp.php:216` to `lib/service_authority.php:146` (rescan task 1).
   Each reads the value only to pass it through its schema, so none is OPEN, but the audit has no row for them and
   the inventory line no longer matches. "No OPEN site" holds for the audit as written.

4. **Settings editor game log (records, found by the form pass).** Saving the game settings or a module's settings
   with the form unchanged writes one "Changed core setting ..." (or module) game log line for every field on the
   form, 126 for the core form, though only one stored value changed (`instantexp` from an empty string to `0`).
   `lib/typed_editor.php:93-102` logs every key of the submitted patch. 1.1.2's `configuration.php` logged and saved
   only the settings whose value differed, with the old and new value. Not a GET or replay issue, so not a gate 4
   finding, but a log that says more changed than did.

All four are on `ROADMAP.md` Remaining under "Merge gate findings".

## Gate 1: Phase 3 checklist status

Each Done line was read in `ROADMAP.md` at `54e22e6`. For each, the evidence document exists, and it, or the
checkpoint that accepted the family, names a Modern core run that the GitHub Actions API reports as success on the
commit or on an accepted descendant. Runs marked "own run" failed or were cancelled and stay on record; the family
was accepted through the descendant named.

| # | Line | Evidence | Accepted at | Modern core (API) |
| --- | --- | --- | --- | --- |
| 1 | Bundled modules, 24 PASS | `BUNDLED-MODULE-CERTIFICATION.md` | `cf6a349` (own run of `0944188` cancelled) | 36317455788 success |
| 2 | Race onboarding | `BUNDLED-MODULE-CERTIFICATION.md` (race rows) | `77c5879` | 34842503298 success |
| 3 | Specialty onboarding, Dark Arts, Thieving Skills | `BUNDLED-MODULE-CERTIFICATION.md`, `SPECIALTY-AUTHORITY.md`, `DARK-ARTS-COMPANION-20260927.md` | `2bfe8fc`, `be54511` | 36285456622, 36306254008 success |
| 4 | Ordinary Forest combat | `ORDINARY-FOREST-COMBAT-20260927.md` | `cf6a349` (own run of `bc94205`, 36315549242, cancelled) | 36317455788 success |
| 5 | Dragon and prologue | `DRAGON-AUTHORITY-20260926.md` | `72ab600`, `2bfe8fc` | 36273713605, 36285456622 success |
| 6 | Training and master combat | `TRAINING-MASTER-AUTHORITY-20260927.md` | `cf6a349` | 36317455788 success |
| 7 | Core PvP | `PVP-COMBAT-AUTHORITY-20260927.md` | `67f981e` | 36319758950 success |
| 8 | Graveyard torment combat | `GRAVEYARD-COMBAT-AUTHORITY-20260927.md` | `f7a29a8` (own run of `8410226`, 36322077603, cancelled; `f7a29a8` recorded in `RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md`) | 37203919450 success |
| 9 | New Day Dragon Point allocation | `DRAGON-POINT-AUTHORITY-20261003.md` | `f7a29a8` (own run of `7c3c127`, 37123254762, cancelled) | 37203919450 success |
| 10 | Normal New Day daily reset | `NEWDAY-DAILY-RESET-AUTHORITY-20261003.md` | `f7a29a8` | 37203919450 success |
| 11 | Ramius early resurrection | `RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md` | `de9f12e` | 37210214161 success |
| 12 | Player mail | `MAIL-AUTHORITY-20261004.md` | `7e57ed5` | 37226146119 success |
| 13 | Shared `systemmail()` helper | `MAIL-AUTHORITY-20261004.md` | `7e57ed5` | 37226146119 success |
| 14 | Typed settings editor, mount object preferences | `MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md` | `fd9b8a5` | 34796017543 success |
| 15 | Player preference namespace protection | `MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md` | `5da77fc` | 34781821375 success |
| 16 | Petition intake | `MODERN-CORE-CHECKPOINT-20260913-PHASE3.md` | `2e2076f` | 34777405824 success |
| 17 | Direct `battle.php` include blocked | `MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md` | `353ba60` | 34892412719 success |
| 18 | All 14 `systemmail()` callers | `MAIL-AUTHORITY-20261004.md` | `4478c06` | 38712609964 success |
| 19 | CI runtime split | `ENGINEERING-VALIDATION.md` | `4478c06` | 38712609964 success |
| 20 | Petition administration | `PETITION-ADMIN-AUTHORITY-20261015.md` | `ef97952` | 38722109813 success |
| 21 | Inn economy | `INN-ECONOMY-AUTHORITY-20261015.md` | `3b6d780` | 38736206216 success |
| 22 | Clan membership | `CLAN-MEMBERSHIP-AUTHORITY-20261015.md` | `2eff167` | 38767451569 success |
| 23 | Clan text and moderation | `CLAN-TEXT-MODERATION-AUTHORITY-20261015.md` | `cbc0cf4` | 38779187332 success |
| 24 | Bank | `BANK-AUTHORITY-20261015.md` | `52bfc4f` | 38802851227 success |
| 25 | Weapons and armor | `EQUIPMENT-PURCHASE-AUTHORITY-20261015.md` | `639b8c5` | 38810195085 success |
| 26 | Stables | `STABLES-AUTHORITY-20261016.md` | `8257aed` | 38831947335 success |
| 27 | Haunting | `HAUNT-AUTHORITY-20261016.md` | `595f0c3`, `2a48f35` | 38837315295, 38884592810 success |
| 28 | Bio moderation, donations | `BIO-DONATION-ADMIN-AUTHORITY-20261016.md` | `c166e2e` | 38892084451 success |
| 29 | Soul restoration, preferences fatal | `RESTORE-PREFS-AUTHORITY-20261016.md` | `fa30beb` (own run of `6a9a12b`, 38896842544, failed) | 38907797754 success |
| 30 | User editor part 1 | `USER-EDITOR-AUTHORITY-20261016.md` | `fa30beb` | 38907797754 success |
| 31 | CI game-day rollover | `CI-ROLLOVER-STABILITY-20261016.md` | `aeb19b9` | 38923797893 success |
| 32 | User editor part 2, bans | `BAN-ADMIN-AUTHORITY-20261016.md` | `eec24a6` | 38931835620 success |
| 33-34 | Ban message fatal; player ID cookie | `BAN-MESSAGE-AND-ID-COOKIE-20261016.md` | `55ec6c2` | 38942722221 success |
| 35 | Content editors part 1 | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md` | `6820b80` | 38958259938 success |
| 36-37 | Content editors part 2; news formatting fatal | `CONTENT-EDITORS-PART2-AUTHORITY-20261016.md` | `82a48fb` | 38971089072 success |
| 38-41 | Content editors part 3; bio fatal; bio lookup; mount owner lookup | `CONTENT-EDITORS-PART3-AUTHORITY-20261017.md` | `19cbb4e` | 38991243831 success |
| 42-43 | Module manager; stables lock | `MODULE-MANAGER-AUTHORITY-20261017.md` | `43bcdc4` | 39026254697 success |
| 44-45 | Expiration cleanup; account deletion | `DELETION-EXPIRATION-AUTHORITY-20261017.md` | `4182281` | 39029578747 success |
| 46 | Truthful completion reporting | `COMPLETION-REPORTING-20261017.md` | `05485ec` | 39044082958 success |
| 47 | Serialized schemas part 1 | `SERIALIZED-SCHEMAS-PART1-20261017.md` | `fd548a3` | 39054238252 success |
| 48-50 | Combat buff compatibility; schemas part 2; mount buffs | `SERIALIZED-SCHEMAS-PART2-20261018.md` | `1dfb7c5` | 39099465964 success |
| 51-53 | Suspended specialty buffs; schemas part 3; companion abilities | `SERIALIZED-SCHEMAS-PART3-20261022.md` | `49baab9` | 39704754364 success |
| 54-55 | Schemas part 4; Gypsy Bandit 500 | `SERIALIZED-SCHEMAS-PART4-20261022.md` | `5bdba80` | 39764211865 success |
| 56-59 | Schemas part 5; `motd.php`; `paylog.php`; `moderate.php` | `SERIALIZED-SCHEMAS-PART5-20261022.md` | `c00f2d1` | 39773347686 success |
| 60 | Final rescan task 1, route inventory | `ROUTE-INVENTORY-20261022.md` | `aea4608` (own run of `593578e`, 39822309470, failed) | 39841362118 success |
| 61 | Final rescan task 2 | `RESCAN-TASK2-20261022.md` | `107ce87` (own run of `623ca49`, 39868186035, failed) | 39929029453 success |
| 62 | Final rescan task 3 (recorded Done by this commit) | `RESCAN-TASK3-20261023.md` | `54e22e6` | 39950404013 success, 12 of 12 jobs |

Where `ROADMAP.md` names a Baseline integrity run, the API reports it as success at the same commit. Every accepted
commit above is an ancestor of `54e22e6`.

Open after this commit:

- The PR #1 body replacement (Jim's, by hand): the one expected open item.
- Findings 1 to 4 (not expected): the reason gate 1 does not pass.

Records notes, not open items: the Done lines 2, 3 and 13 to 17 name no evidence document in `ROADMAP.md`; their
evidence is in the checkpoints named above. Lines 8 to 10 were accepted through `f7a29a8`, which their own
documents do not name; `RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md` records that run as its verified
predecessor.

### Rescan task 3 acceptance

At `54e22e6`, through the GitHub Actions API: Modern core 39950404013 success, all 12 jobs success (`checks`,
`http (1)` to `http (4)` and `result` on each leg, PHP 8.5 with MySQL 8.4 and PHP 8.4 with MariaDB 11.4); Baseline
integrity 39950404085 success (`provenance`). Recorded in `RESCAN-TASK3-20261023.md` ("Acceptance, 2026-10-23").
Its three Final rescan lines are closed, and with them the Final rescan item, since no "to fix" line is left.

## Gate 2: final full run

The final full run is the CI run on the commit that publishes this document (the gate doc, the task 3 acceptance
and `ROADMAP.md`, no product change). Its Modern core and Baseline integrity run IDs and states are in the task
report, inspected once. If Modern core was still running at that check, the gate is pending until a later task
records its result; no earlier run is used in its place.

## Gate 3: historical integrity

`python3 scripts/verify-historical-baseline.py`: PASS. Tag `historical-source-1.1.2`, annotated tag object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source commit `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, source tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files verified, 11 historical commits; each also read with
`git cat-file`, `git rev-parse` and `git ls-tree`. `git ls-remote`: `main` is
`999cec6f9c655a840320d982d673bb863c68c2b2`.

## Gate 4: independent GET-mutation and replay rescan

### Method

The pass did not start from the route inventory's statuses.

1. **Static writers.** A scan of the 331 shipped PHP files (top-level pages, `lib/`, `modules/`, `src/`, comments
   removed) for every state writer: `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `TRUNCATE` and DDL in SQL strings; the
   setting and module preference writers (`savesetting`, `clearsetting`, `set_module_pref`, `increment_module_pref`,
   `clear_module_pref`, the module setting and object preference writers); account writes (`saveuser`,
   `resurrection_player_mutation`); file writes (`fopen` for writing, `fwrite`, `file_put_contents`, `unlink`,
   `mkdir`, `rename`, `touch`); news, logs and mail; cache invalidation. It also lists each file's request keys.
2. **Every GET, as four roles.** A copy of the application (this commit, PHP 8.4, MariaDB 11.4) with all 24 bundled
   modules active. As anonymous, a living player, a dead player and an admin with every superuser flag, it requested
   every URL of the route inventory's rows expanded (each op and subop) and every same-site link found in the
   responses, up to 1,500 per role. Before each request the URL was put in the player's allowed navigation, so the
   page ran as if its link had been issued. After each request the whole database was dumped and compared with the
   dump before it; any change was restored before the next request. Access bookkeeping was not counted as a change:
   `laston`, `lasthit`, `allowednavs`, `restorepage`, `gentime`, `gentimecount`, `gensize`, `lastip`, `uniqueid`,
   `loggedin`, `accounts_output` and the online count settings.
3. **Every form, three ways.** Each distinct form found was fetched fresh and sent once as rendered. When that
   changed state, the identical body was sent again (a replay of the same intent), and from a restored state its
   fields were sent as a GET.
4. **Probes** for what the crawl could not judge: the Graveyard pages with a non-default `drunkeness` (finding 1),
   and a commentary post with text, sent again, sent as a GET and sent with a stale counter.

Scratch tools only; nothing of it is committed.

### What changed state on GET

Each role reached the 1,500 request cap; the inventory's URLs were requested first, so every row was covered, and
the rest were harvested links (for the admin, mostly the translator tool's pages).

| Role | Requests | Pages | Statuses |
| --- | --- | --- | --- |
| anon | 1500 | 67 | 200 875, 302 592, 400 1, 403 10, 404 5, 410 17 |
| player | 1500 | 67 | 200 1282, 302 11, 400 17, 403 134, 404 5, 409 18, 410 33 |
| dead | 1500 | 67 | 200 1265, 302 9, 400 18, 403 134, 404 5, 409 36, 410 33 |
| admin | 1500 | 67 | 200 1322, 302 11, 400 52, 403 41, 404 8, 405 15, 409 18, 410 33 |

- **Graveyard view pages** (finding 1): `drunkeness` set to 0 on `op=enter`, `haunt`, `haunt2`, `haunt3`, `restore`.
- **A module preference's declared default stored on first read** (`lib/modules.php` `get_module_pref()`, 1.1.2's own
  code): the inn bartender and Dwarf ale (`drinks` `drunkeness` and `harddrinks` 0) and the outhouse
  (`usedouthouse` 0). The value read is the same with or without the row, so no state changes; recorded, not a
  finding.
- Nothing else. In particular no admin editor, no shop and no combat page changed state on GET.

The recorded 1.1.2 view housekeeping (village location, news expiry, the healer's clamp, the referer expiry, the
keepalive, the online count, clan housekeeping, New Day on navigation) did not show as unexpected changes: the
fixture state made most of them a no-op, and each has its own HTTP test in the suite.

### Replays and forms sent as GET

| Role | Forms sent | Changed state | Replay answers | Replay changed state | Sent as GET changed state |
| --- | --- | --- | --- | --- | --- |
| anon | 5 | 1 | 303 1 | 1 | 0 |
| player | 46 | 26 | 302 1, 409 25 | 0 | 0 |
| dead | 56 | 36 | 302 1, 409 35 | 0 | 2 |
| admin | 822 | 717 | 200 68, 302 1, 303 1, 404 585, 409 62 | 0 | 0 |

- No replay changed state. Player and dead forms answered 409 "Expired or repeated action."; the inn room's replay
  redirects to the login page, since the rental logs the player out. Admin replays answered 409, or 404 for a
  delete whose row the first send removed (the editors' "Not found"), or 200 with nothing left to do (comment
  moderation on the village page, the user editor's delete); none changed anything.
- Sent as a GET, no form's action ran. The two Graveyard forms sent as a GET (`op=restore`, `op=haunt3`) changed
  only `drunkeness` (finding 1).
- The one replay that changed state: a failed login writes one `faillog` row per attempt, a replay included. That is
  the security log, not a replayed action.
- Commentary: a post with text added one comment; the same body again, the same fields as a GET and a new text with
  the old counter each added none.

### Files written

Only the data cache (`lib/datacache.php`, off unless `usedatacache` is set), New Day's removal of old cache files
(`lib/newday/newday_runonce.php`, maintenance), the historical installer's licence fetch (`lib/pullurl.php` from
`installer_stage_1.php`, a stage that refuses direct requests since rescan task 3) and `cron.php`'s error output. No
request writes a file of its choosing.

### Rescan table

One row per top-level page and module route. Writers: state writers in the page's own files (the page, `lib/<page>/`
and `lib/<page>_*.php`, or the module's files), excluding the shared closure every page includes. GET: distinct URLs
requested over the roles and the changes seen. Forms: forms sent, how many changed state, and replays refused.
Inventory: the statuses of the page's rows in `ROUTE-INVENTORY-20261022.md`.

The writers column is a pointer, not the proof: a page whose writers live in a differently named helper (the
editors' `lib/*_editor.php`, the shared combat files) shows "none" there. The GET and form columns are the evidence.

| Route | Writers in own files | GET (4 roles) | Forms | Inventory |
| --- | --- | --- | --- | --- |
| `about.php` | none | 4 URLs; no change | - | READ-ONLY (2 rows) |
| `armor.php` | account 1 | 2 URLs; no change | 12 forms, 12 changed state, replay refused 12/12 | CLOSED, READ-ONLY (2 rows) |
| `armoreditor.php` | none | 35 URLs; no change | 197 forms, 196 changed state, replay refused 196/196 | CLOSED, READ-ONLY (2 rows) |
| `badnav.php` | account 1 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `badword.php` | sql_write 2 | 6 URLs; no change | 5 forms, 2 changed state, replay refused 2/2 | CLOSED, READ-ONLY (3 rows) |
| `bank.php` | account 1 | 9 URLs; no change | 9 forms, 9 changed state, replay refused 9/9 | CLOSED, READ-ONLY (2 rows) |
| `battle.php` | none | 4 URLs; no change | - | CLOSED (2 rows) |
| `bio.php` | none | 30 URLs; no change | - | READ-ONLY (1 rows) |
| `bios.php` | none | 3 URLs; no change | - | CLOSED, READ-ONLY (2 rows) |
| `clan.php` | account 1, sql_write 6 | 10 URLs; no change | 3 forms, 0 changed state, replay refused 0/0 | CLOSED (2 rows) |
| `common.php` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `companions.php` | none | 12 URLs; no change | 10 forms, 7 changed state, replay refused 7/7 | CLOSED, READ-ONLY (2 rows) |
| `configuration.php` | none | 18 URLs; no change | 16 forms, 16 changed state, replay refused 16/16 | CLOSED, READ-ONLY (2 rows) |
| `create.php` | settings 1 | 5 URLs; no change | 4 forms, 0 changed state, replay refused 0/0 | CLOSED, DISABLED, READ-ONLY (3 rows) |
| `creatures.php` | none | 45 URLs; no change | 290 forms, 288 changed state, replay refused 288/288 | CLOSED, READ-ONLY (2 rows) |
| `cron.php` | files 1, settings 2 | 1 URLs; no change | - | DISABLED (1 rows) |
| `donators.php` | none | 3 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `dragon.php` | account 2 | 2 URLs; no change | - | CLOSED (2 rows) |
| `forest.php` | account 1 | 33 URLs; no change | 4 forms, 2 changed state, replay refused 2/2 | CLOSED (10 rows) |
| `gamelog.php` | sql_write 1 | 2 URLs; no change | - | READ-ONLY (1 rows) |
| `gardens.php` | none | 2 URLs; no change | 3 forms, 0 changed state, replay refused 0/0 | CLOSED (1 rows) |
| `graveyard.php` | account 1 | 12 URLs; Drinks sober (finding 1) 46 | 4 forms, 3 changed state, replay refused 3/3, GET CHANGED 2 | CLOSED, READ-ONLY (6 rows) |
| `gypsy.php` | account 1 | 4 URLs; no change | 4 forms, 3 changed state, replay refused 3/3 | CLOSED, READ-ONLY (3 rows) |
| `healer.php` | account 2 | 13 URLs; no change | 10 forms, 10 changed state, replay refused 10/10 | CLOSED (2 rows) |
| `hof.php` | none | 29 URLs; no change | - | READ-ONLY (2 rows) |
| `home.php` | settings 2 | 2 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | READ-ONLY (2 rows) |
| `index.php` | none | 2 URLs; no change | - | READ-ONLY (2 rows) |
| `inn.php` | account 4 | 9 URLs; default preference stored 9 | 6 forms, 3 changed state, replay refused 3/3 | CLOSED (4 rows) |
| `installer.php` | none | 3 URLs; no change | - | DISABLED (2 rows) |
| `list.php` | none | 4 URLs; no change | 3 forms, 0 changed state, replay refused 0/0 | READ-ONLY (2 rows) |
| `lodge.php` | none | 3 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `logdnet.php` | none | 2 URLs; no change | - | DISABLED (2 rows) |
| `login.php` | sql_write 1 | 1 URLs; no change | 1 forms, 1 changed state, replay refused 0/1, REPLAY CHANGED 1 | CLOSED (2 rows) |
| `mail.php` | sql_write 4 | 10 URLs; no change | 6 forms, 3 changed state, replay refused 3/3 | CLOSED, READ-ONLY (2 rows) |
| `masters.php` | none | 19 URLs; no change | 29 forms, 15 changed state, replay refused 15/15 | CLOSED, READ-ONLY (2 rows) |
| `mercenarycamp.php` | account 2 | 5 URLs; no change | 6 forms, 6 changed state, replay refused 6/6 | CLOSED, READ-ONLY (2 rows) |
| `moderate.php` | none | 16 URLs; no change | 41 forms, 0 changed state, replay refused 0/0 | CLOSED (2 rows) |
| `modules.php` | settings 8, sql_write 25 | 12 URLs; no change | 7 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `motd.php` | account 1, sql_write 5 | 6 URLs; no change | 9 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `mounts.php` | none | 9 URLs; no change | 6 forms, 6 changed state, replay refused 6/6 | CLOSED, READ-ONLY (2 rows) |
| `newday.php` | account 1, files 1, sql_write 6 | 1 URLs; no change | 1 forms, 1 changed state, replay refused 1/1 | CLOSED (1 rows) |
| `news.php` | sql_write 1 | 6 URLs; no change | - | READ-ONLY (1 rows) |
| `paylog.php` | none | 2 URLs; no change | - | CLOSED, READ-ONLY (2 rows) |
| `payment.php` | none | 1 URLs; no change | - | DISABLED (1 rows) |
| `petition.php` | sql_write 3 | 7 URLs; no change | 4 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (3 rows) |
| `prefs.php` | settings 1 | 3 URLs; no change | 3 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (3 rows) |
| `pvp.php` | account 1 | 3 URLs; no change | - | CLOSED, READ-ONLY (2 rows) |
| `rawsql.php` | none | 1 URLs; no change | - | DISABLED (1 rows) |
| `referers.php` | sql_write 1 | 2 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `referral.php` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `rock.php` | none | 1 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED (1 rows) |
| `runmodule.php` | none | 1 URLs; no change | - | CLOSED (1 rows) |
| `runmodule.php?module=cedrikspotions` | account 1, settings 3 | 2 URLs; no change | 6 forms, 0 changed state, replay refused 0/0 | CLOSED (1 rows) |
| `runmodule.php?module=crazyaudrey` | account 1, settings 12 | 4 URLs; no change | 6 forms, 2 changed state, replay refused 2/2 | CLOSED (1 rows) |
| `runmodule.php?module=dag` | account 2, settings 2, sql_write 8 | 10 URLs; no change | 3 forms, 0 changed state, replay refused 0/0 | CLOSED (1 rows) |
| `runmodule.php?module=darkhorse` | account 2, sql_write 2 | 7 URLs; no change | - | CLOSED (1 rows) |
| `runmodule.php?module=drinks` | account 3, settings 7, sql_write 12 | 9 URLs; no change | 9 forms, 9 changed state, replay refused 9/9 | CLOSED (1 rows) |
| `runmodule.php?module=fairy` | settings 2 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=findgem` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=findgold` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=foilwench` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=game_dice` | account 1 | 1 URLs; no change | - | CLOSED (1 rows) |
| `runmodule.php?module=game_fivesix` | account 1, settings 5 | 1 URLs; no change | - | CLOSED (1 rows) |
| `runmodule.php?module=game_stones` | account 1 | 1 URLs; no change | - | CLOSED (1 rows) |
| `runmodule.php?module=glowingstream` | none | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=goldmine` | sql_write 12 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=lovers` | account 1, settings 3, sql_write 2 | 4 URLs; no change | 2 forms, 2 changed state, replay refused 2/2 | CLOSED (1 rows) |
| `runmodule.php?module=outhouse` | account 1, settings 6, sql_write 2 | 6 URLs; default preference stored 19 | 15 forms, 6 changed state, replay refused 6/6 | CLOSED (1 rows) |
| `runmodule.php?module=racedwarf` | settings 3, sql_write 1 | 1 URLs; default preference stored 3 | - | READ-ONLY (1 rows) |
| `runmodule.php?module=raceelf` | settings 3 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=racehuman` | settings 3 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=racetroll` | settings 3 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=sethsong` | account 1, settings 2, sql_write 2 | 3 URLs; no change | 3 forms, 3 changed state, replay refused 3/3 | CLOSED (1 rows) |
| `runmodule.php?module=specialtydarkarts` | settings 6, sql_write 4 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=specialtymysticpower` | settings 6, sql_write 4 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `runmodule.php?module=specialtythiefskills` | settings 6, sql_write 4 | 1 URLs; no change | - | READ-ONLY (1 rows) |
| `shades.php` | none | 2 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED (1 rows) |
| `source.php` | none | 41 URLs; no change | - | DISABLED (1 rows) |
| `stables.php` | account 3 | 10 URLs; no change | - | CLOSED, READ-ONLY (2 rows) |
| `stats.php` | none | 4 URLs; no change | - | READ-ONLY (2 rows) |
| `superuser.php` | sql_write 1 | 3 URLs; no change | 16 forms, 15 changed state, replay refused 15/15 | CLOSED, READ-ONLY (3 rows) |
| `taunt.php` | none | 4 URLs; no change | 27 forms, 27 changed state, replay refused 27/27 | CLOSED, READ-ONLY (2 rows) |
| `titleedit.php` | none | 6 URLs; no change | 34 forms, 33 changed state, replay refused 33/33 | CLOSED, READ-ONLY (2 rows) |
| `train.php` | none | 4 URLs; no change | 4 forms, 2 changed state, replay refused 2/2 | CLOSED, READ-ONLY (2 rows) |
| `translatortool.php` | sql_write 3 | 665 URLs; no change | 2 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (3 rows) |
| `untranslated.php` | sql_write 2 | 3 URLs; no change | 1 forms, 0 changed state, replay refused 0/0 | CLOSED, READ-ONLY (2 rows) |
| `user.php` | sql_write 3 | 22 URLs; no change | 5 forms, 3 changed state, replay refused 3/3 | CLOSED, READ-ONLY (5 rows) |
| `viewpetition.php` | none | 4 URLs; no change | 1 forms, 1 changed state, replay refused 1/1 | CLOSED (2 rows) |
| `village.php` | none | 2 URLs; no change | 68 forms, 66 changed state, replay refused 66/66 | CLOSED (1 rows) |
| `weaponeditor.php` | none | 5 URLs; no change | 16 forms, 16 changed state, replay refused 16/16 | CLOSED, READ-ONLY (2 rows) |
| `weapons.php` | account 1 | 2 URLs; no change | 12 forms, 12 changed state, replay refused 12/12 | CLOSED, READ-ONLY (2 rows) |

Coverage limits. The crawl sends the forms a page renders for the fixture's state, so a form that needs another
state was not sent here: a PvP target in range, a mount to trade or sell, clan applications and ranks, a Dragon
fight, most Forest events. Their replay and GET behavior was not re-proven by this pass; each has its own HTTP test
in the suite (named in the route inventory rows), which the four local shards ran and passed.

### Differences from the route inventory

- `graveyard.php?op=enter` (and `haunt`, `haunt2`, `haunt3`, `restore`): the inventory names no Drinks write; the
  pages change `drunkeness` on GET (finding 1).
- `pvp.php`: covers `act=attack`, a protected state change, though the row says READ-ONLY (finding 2).
- No page, op or module route was found that the inventory lacks, and no row whose status the crawl contradicted
  otherwise. Every DISABLED row answered as recorded (below).

### Static tests that hold the line

Each was broken deliberately in a scratch worktree, shown failing, and restored with `git checkout`; the restored
tree passed again and `git status` showed no change. None of the breakage was committed.

| Check | Break | Result |
| --- | --- | --- |
| Route inventory | a new op in `about.php` with no row | FAILED (1 failure) |
| Route inventory | the `healer.php?op=buy\|companion` row removed | FAILED (2 failures) |
| Route inventory | the counts table off by one (CLOSED 88 to 89) | FAILED (1 failure) |
| Route inventory | a new op in a module (`outhouse`) with no row | FAILED (1 failure) |
| SQL allow-list (`SqlSweepTest`) | a request value pasted into SQL in `list.php` | FAILED (1 failure) |
| SQL allow-list | a reviewed row removed from `RESCAN-TASK3-20261023.md` | FAILED (1 failure) |
| SQL allow-list | a second site of a listed value (`moderate.php` `$limit`) | FAILED (1 failure) |
| Shard test | the shard runner drops a test | FAILED (1 error) |
| Shard test | the workflow lists three shards | FAILED (1 failure) |
| All restored | | route inventory and shard tests OK (8 tests); `SqlSweepTest` OK (3 tests, 1,650 assertions) |

The first attempt at two SQL breaks appended the code after a closing `?>`, where it is HTML and the scanner rightly
ignores it; redone inside the PHP, both failed as shown.

## Also confirmed

- **Serialized state.** `SERIALIZED-STATE-AUDIT.md` has no OPEN site. The `CLAUDE.md` inventory line does not match
  a fresh recount (finding 3).
- **Disabled surfaces.** As anonymous, living, dead and admin: `payment.php`, `source.php` (with any `url`),
  `rawsql.php`, `logdnet.php` (and `op=list`), and legacy recovery (`create.php?op=forgot`, `op=val`) answer 410;
  `installer.php` 403; `battle.php` and `cron.php` 404 over HTTP.
- **Modules.** `modules/` holds the 24 bundled modules only: no archive-only module and no Cities module.
- **Local verification** (this commit's tree; the docs are the only change):
  - PHPUnit (`composer test`, MariaDB 11.4, fresh database): OK, 265 tests, 141,142 assertions. A first run against
    the database left by the crawl probes reported 5 failures in database tests that expect an empty database; on a fresh one it
    passed, as in each shard run.
  - Both PHPStan gates (`composer analyse`, `phpstan-new.neon`): no errors.
  - `composer validate --strict`: valid. `composer audit`: no advisories. `composer lint`: 397 files, 0 failures.
  - `scripts/verify-historical-baseline.py` and `scripts/validate-project.py`: PASS (663 tracked files).
    `git diff --check`: clean.
  - All four HTTP shards through `scripts/tooling-shards.py` on MariaDB 11.4 (PHP 8.4.27), one database each: shard 1
    51 tests, shard 2 48, shard 3 56, shard 4 50, each OK. Shard 2's first run failed while the scratch disk was full
    (a 500 on a PvP attack); after space was freed it passed whole.

## Final full run

The final full run is the Modern core and Baseline integrity runs on the commit that publishes this document. They
had not started or finished when this document was written; their IDs, states and totals are in the task report and
are recorded by the next task that reads them. Gate 2 passes only when all 12 Modern core jobs and Baseline
integrity are SUCCESS on that commit.
