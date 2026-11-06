# Phase 6 task 2: crash fixes, CI pin, first administrator's flags (2026-11-06)

Jim's brief, "PHASE 6 TASK 2: CRASH FIXES, CI PIN, FIRST ADMINISTRATOR'S FLAGS". Expected start: `main` at
`ea8bb3da19be9b5b323f39995d6d56f79117f4c8` (it matched). The work comes from the readiness review's gap list
(`PHASE6-RC-READINESS-20261106.md`): B1 (two runtime 500s), S8 (the CI runner and the Node 20 actions) and S7 (the first
administrator's flags). No version string, tag or release changes.

## Task 1 accepted

Confirmed through the GitHub API before any change:

| Workflow | Run | Result |
|---|---|---|
| Modern core | 42190029147 | **SUCCESS, all 12 jobs**: both legs' `checks`, the eight `http` shards and both `result` jobs |
| Baseline integrity | 42190029178 | **SUCCESS** |

Shard job durations (start to finish of each job, from the jobs API), shards 1 to 4:

- PHP 8.4 / MariaDB 11.4: 17:16, 13:30, 16:35, 20:05
- PHP 8.5 / MySQL 8.4: 21:57, 22:02, 17:30, 21:46

They match Jim's figures. The result is appended to `PHASE6-RC-READINESS-20261106.md` and task 1 is ticked in
`ROADMAP.md`.

## Jim's decisions (2026-11-06)

Recorded in `ROADMAP.md` with the task order:

1. **rc.1 is for public hosts too.** B2 (host guide) stays a BLOCKER, and these become rc.1 blockers as well: S1
   (security headers; a Content Security Policy as far as task 3 gets it), S2 (internal-file guard), S3 (no-shell path),
   S4 (unreadable-config page), S6 (declared extensions), S7 (admin flags), S8 (CI pin), S9 (legal review, Jim and a
   lawyer), S12 (config outside the docroot).
2. **First administrator's flags:** 1.1.2's set, minus the flags for surfaces that were removed (this task).
3. **No-shell hosts:** a token-gated web install and upgrade page and a secret-protected web maintenance (cron)
   request. Design and security review first, Jim approves, then build. The 1.1.2 web installer stays 403; this is a new
   surface, not that one.
4. Proposed by the command chat, open for Jim to overturn: S11 (password recovery, email verification) is a documented
   known limitation for rc.1 and a 1.0.0 blocker.

Task order: 2 crash fixes, CI pin, admin flags (this task); 3 hardening (S1, S2, S4, S6, S12); 4 web install, upgrade and
maintenance: design and threat model; 5 the same: build; 6 host guide (B2), covering both the shell and the web path;
7 front-door docs, rc.1 cut materials, legal packet (B3, B4, S9). The other SHOULD and NICE items stay listed,
unscheduled.

## B1: list.php, the online and clan lists

| | Line |
|---|---|
| 1.1.2, online view (`historical-source-1.1.2:list.php` line 75) | `SELECT acctid,name,login,alive,location,race,sex,level,laston,loggedin,lastip,uniqueid FROM ... WHERE locked=0 AND loggedin=1 AND laston>'...' ORDER BY level DESC, dragonkills DESC, login ASC` |
| 1.1.2, clan view (line 79) | the same columns, `... AND clanid='{$session['user']['clanid']}' ORDER BY ...` |
| 1.1.2, the read (line 129) | `} else if ($row['hitpoints'] > 0) {` (after `if ($row['alive'] == true)`), for the Alive column |
| 1.1.2, full list (line 88) | `SELECT acctid,name,login,alive,hitpoints,location,...`: the one query that selected it |
| New, online and clan views (`list.php` lines 85 and 89) | `SELECT acctid,name,login,alive,hitpoints,location,race,sex,level,laston,loggedin,lastip,uniqueid FROM ...` (the rest unchanged) |

Why: a dead player online reaches the `hitpoints` read, and the column was not in the row. PHP 5 gave a notice and a
null, so a dead and an unconscious player both showed "No"; PHP 8 gives a warning, which the game's error handler turns
into a 500 for the whole page. The online list is linked from the home page for everyone, so one dead player online
broke it for every visitor. With the column selected, the Alive column shows what 1.1.2's full list always showed:
"Yes" (alive), "Unconscious" (dead with hitpoints left) or "No" (dead). That is the only visible change: an unconscious
player online now reads "Unconscious" instead of 1.1.2's accidental "No".

### The other pages checked

Every query in `list.php`, `hof.php`, `bio.php`, `clan.php` and `lib/clan/*.php`, against every column each one reads:

| File | Queries | Result |
|---|---|---|
| `list.php` | the count, the online, clan and full lists | the online and clan lists fixed above; the others select what they read |
| `hof.php` | the count, the eight listings and their "me" counts | each listing's `dataN` columns match its headers (money 1, gems 0, charm 2, tough 2, resurrects 1, days 1, the default 6); `name` in all |
| `bio.php` | the character (18 columns), the mount (`SELECT *`), the news (`SELECT *`) | every key read is selected |
| `clan.php` and `lib/clan/` | `$claninfo` (`SELECT *` in `common.php`), `resurrection_clan_account()` and `resurrection_clan_text_row()` (`lib/clan_authority.php`), the membership, detail, list, applicant, motd, withdraw and default pages | every key read is selected |

No other column is read without being selected.

## B1: stats.php and the GROUP BY sweep

| | Line |
|---|---|
| 1.1.2, Top Referers (`historical-source-1.1.2:stats.php` line 40, `op=referers`) | `... WHERE accounts.referer>0 GROUP BY accounts.referer DESC ORDER BY c DESC` |
| 1.1.2, Logon Graph (line 61, `op=graph`) | `SELECT count(acctid) AS c, substring(laston,1,10) AS d FROM accounts GROUP BY d DESC ORDER BY d DESC` |
| New (`stats.php` lines 41 and 62) | `... GROUP BY accounts.referer ORDER BY c DESC` and `... GROUP BY d ORDER BY d DESC` |

Why: MySQL 8.0 removed `ASC` and `DESC` on `GROUP BY` (MySQL 8.4.13 here: `ERROR 1064 (42000): You have an error in your
SQL syntax; ... near 'DESC ORDER BY d DESC'`). MariaDB 11.4 still accepts it, so the pages worked on one supported
database and were a 500 on the other. Each query already has an `ORDER BY`, which sets the order the page shows: the
graph's dates are unique, so its order is fully the same; the referers are by count, and referers with the same count
had no defined order in 1.1.2 either.

The sweep: every `GROUP BY` in shipped PHP (`git ls-files` PHP outside `tests/`, `scripts/` and `lab/`, 332 files with
the modules; no SQL files ship), read up to the end of its clause. 14 sites at `ea8bb3d`; only stats.php's two ordered the group:

| File:line | Clause | Result |
|---|---|---|
| `creatures.php:86` | `group by creaturelevel order by creaturelevel` | fine |
| `lib/clan/applicant_apply.php:52` | `GROUP BY clans.clanid ORDER BY c DESC` | fine |
| `lib/clan/clan_default.php:33` | `GROUP BY clanrank ORDER BY clanrank DESC` | fine |
| `lib/clan/list.php:5` | `GROUP BY clans.clanid ORDER BY c DESC` | fine |
| `lib/clan_authority.php:119` | `GROUP BY c.clanid,c.clanname` | fine |
| `lib/motd.php:133` | `GROUP BY choice ORDER BY choice` | fine |
| `lib/pageparts.php:216` | `GROUP BY status` | fine |
| `motd.php:86` | `GROUP BY d ORDER BY d DESC` | fine |
| `mounts.php:110` | `GROUP BY hashorse` | fine |
| `referers.php:69` | `GROUP BY site ORDER BY $order LIMIT 100` | fine |
| `stats.php:40` (now 41) | `GROUP BY accounts.referer DESC ORDER BY c DESC` | **fixed**: `DESC` dropped from the group |
| `stats.php:61` (now 62) | `GROUP BY d DESC ORDER BY d DESC` | **fixed**: `DESC` dropped from the group |
| `translatortool.php:126` | `GROUP BY uri ORDER BY uri ASC` | fine |
| `untranslated.php:81` | `GROUP BY namespace ORDER BY namespace ASC` | fine |

`tests/unit/GroupByOrderTest.php` keeps it so.

## S8: the CI runner and the actions

Every job of every workflow now names `runs-on: ubuntu-24.04`: `baseline-integrity.yml` (`provenance`) and
`modern-core-leg.yml` (`checks`, `http`, `result`). `modern-core.yml`'s one job calls `modern-core-leg.yml` and has no
runner of its own.

The actions, each moved to the newest release and pinned by full commit SHA with the version in a comment:

| Action | Before | After | `action.yml` at the new commit |
|---|---|---|---|
| `actions/checkout` | `11d5960a326750d5838078e36cf38b85af677262` (v4; `using: node20`, line 116) | `3d3c42e5aac5ba805825da76410c181273ba90b1` (v7.0.1, the newest tag; v7.0.0's change is a refusal to check out fork PRs for `pull_request_target` and `workflow_run`, which these workflows do not use) | line 116: `  using: node24` |
| `shivammathur/setup-php` | `bf6b4fbd49ca58e4608c9c89fba0b8d90bd2a39f` (2.35.5; `using: 'node20'`, line 37) | `eb7c497e18156a6bbabfef1d3a82760b9eda3962` (2.40.0, the newest tag; the annotated tag object is `4424c9aab975b57bd706ee5a39c6b4334d6e4f60`, the pin is its commit) | line 37: `  using: 'node24'` |

How they were read: the session's GitHub API access covers this repository only (a request for another repository's API
is refused by the session proxy), so each tag's commit and `action.yml` were read from github.com with git
(`git ls-remote --tags`, then `git fetch --depth 1` of the tag and `git show FETCH_HEAD:action.yml`). The SHAs are the
commits GitHub resolves those tags to.

The annotations before this task, on Modern core 42190029147's job `engineering (8.5, mysql:8.4) / checks`
(check run 127799471915, read with the check-run annotations API):

- warning: "Node.js 20 is deprecated. The following actions target Node.js 20 but are being forced to run on Node.js
  24: actions/checkout@11d5960a326750d5838078e36cf38b85af677262,
  shivammathur/setup-php@bf6b4fbd49ca58e4608c9c89fba0b8d90bd2a39f. ..."
- notice: "The ubuntu-latest label will migrate to Ubuntu 26 beginning October 19, 2026. ..."

The same job's annotations after this commit's push are read once and reported with the run, and recorded with this
task's acceptance in the next task.

## S7: the first administrator's flags

Before this task `scripts/install.php` gave the first administrator 5 flags: `SU_MEGAUSER | SU_EDIT_USERS |
SU_EDIT_CONFIG | SU_MANAGE_MODULES | SU_EDIT_COMMENTS` (65745). 1.1.2's installer gave 20
(`historical-source-1.1.2:lib/installer/installer_stage_10.php` lines 19 to 25, 3923967). Jim's decision: 1.1.2's set,
minus the flags for removed surfaces. The new set is 17 flags, 2350079:

| 1.1.2 flag | Bit | Now | Why |
|---|---|---|---|
| `SU_MEGAUSER` | 1 | given | |
| `SU_EDIT_MOUNTS` | 2 | given | mounts and companions editors |
| `SU_EDIT_CREATURES` | 4 | given | creatures, masters, taunts |
| `SU_EDIT_PETITIONS` | 8 | given | petitions |
| `SU_EDIT_COMMENTS` | 16 | given | moderation, bios, bad words |
| `SU_EDIT_DONATIONS` | 32 | given | the donator page (`donators.php`) and donation points; not the removed payment log |
| `SU_EDIT_USERS` | 64 | given | user editor |
| `SU_EDIT_CONFIG` | 128 | given | configuration, stats, referers, game log |
| `SU_INFINITE_DAYS` | 256 | given | |
| `SU_EDIT_EQUIPMENT` | 512 | given | weapons and armor editors |
| `SU_EDIT_PAYLOG` | 1024 | **gone with its surface** | the payment log (`paylog.php`) was removed 2026-10-24; the user editor hides the flag |
| `SU_DEVELOPER` | 2048 | given | developer navigation and settings |
| `SU_POST_MOTD` | 4096 | given | MoTD |
| `SU_MODERATE_CLANS` | 16384 | given | clan moderation |
| `SU_EDIT_RIDDLES` | 32768 | given | as in 1.1.2 it gates no core page (an add-on riddle module used it); the flag and its editor row exist |
| `SU_MANAGE_MODULES` | 65536 | given | module manager |
| `SU_AUDIT_MODERATION` | 131072 | given | moderation audit |
| `SU_RAW_SQL` | 524288 | **gone with its surface** | the SQL/PHP console (`rawsql.php`) was removed; its one remaining use, the creature AI field in `creatures.php`, also opens to `SU_MEGAUSER`, which the administrator has |
| `SU_VIEW_SOURCE` | 1048576 | **gone with its surface** | the source viewer (`source.php`) was removed; the user editor hides the flag |
| `SU_NEVER_EXPIRE` | 2097152 | given | the account never expires |

No flag of 1.1.2's set no longer exists: all 20 constants are still defined in `lib/constants.php`, unchanged. The
flags 1.1.2's installer did not give (`SU_DEBUG_OUTPUT`, `SU_IS_TRANSLATOR`, `SU_EDIT_ITEMS`, `SU_GIVE_GROTTO`,
`SU_OVERRIDE_YOM_WARNING`, `SU_SHOW_PHPNOTICE`, `SU_IS_GAMEMASTER`) are still not given. No release exists, so there is
no upgrade step: an existing local install keeps the administrator it has.

## Tests

Each new test was run first on `ea8bb3d` with only the tests copied in (a git worktree at `ea8bb3d`), then on this
commit. PHPUnit 12.5.35 on PHP 8.5.12, against MySQL 8.4.13 and MariaDB 11.4.13 (Docker).

| Test | What | At `ea8bb3d` | Now |
|---|---|---|---|
| `WarriorListStatsRenderTest::testOnlineListsShowEachPlayersState` | `list.php` and `list.php?op=clan` with a dead player (0 hitpoints) and an unconscious one (6) online, as a superuser and as the dead player: each page completes, the online players listed as Unconscious, No, Yes, the offline one left out; the full list agrees | **fails** on both databases: `list.php: PHP Fatal error: Uncaught ErrorException: Runtime error in list.php:137` (the same at `list.php?op=clan`, line 137) | passes on both |
| `WarriorListStatsRenderTest::testStatsReferersRender` | `stats.php?op=referers`: the two referers, most referrals first, each with its players | **fails on MySQL 8.4**: `Uncaught RuntimeException: Database operation failed.` at `stats.php(41): db_query()`; passes on MariaDB | passes on both |
| `WarriorListStatsRenderTest::testStatsGraphRenders` | `stats.php?op=graph`: renders, the offline player's day listed, newest day first | **fails on MySQL 8.4**: `Database operation failed.` at `stats.php(62): db_query()`; passes on MariaDB | passes on both |
| `GroupByOrderTest` (2 tests) | the pattern finds 1.1.2's two lines and passes the 12 good clauses; no shipped PHP has an ordered `GROUP BY` | **fails**: `stats.php:40: GROUP BY " . db_prefix("accounts") . ".referer DESC`, `stats.php:61: GROUP BY d DESC` | passes |
| `FreshInstallTest::testRealInstallationAndAccountBoundary` (extended) | the installed administrator has exactly 1.1.2's set less the three flags, 2350079 | **fails**: `Failed asserting that 65745 is identical to 2350079.` | passes |
| `test_tooling_shards.ToolingShardTests.test_every_workflow_job_pins_its_runner_and_actions` (new, beside the test that already reads the workflow) | every job runs on `ubuntu-24.04` or calls a local workflow; no `ubuntu-latest`; every action pinned by a 40-hex SHA with a version comment; checkout and setup-php at the new pins | **fails**: `baseline-integrity.yml provenance: ['ubuntu-latest']` | passes |

The render test is `PopupAdminRenderTest`'s method (the PHP CLI over a symlinked tree, a PHP session per account) on its
own install: two clan members online (one dead, one unconscious), the superuser in the clan, one player offline since
2026-10-20, and referrals (two by the superuser, one by the dead player).

## Local verification

Supporting evidence only; CI is the acceptance.

- PHPUnit, full suite, each on a recreated empty database: PHP 8.5 / MySQL 8.4, PHP 8.4 / MariaDB 11.4, PHP 8.4 /
  MySQL 8.4 and PHP 8.5 / MariaDB 11.4: **OK, 346 tests, 147069 assertions** each.
- The four Modern core `http` shards, each in its own worktree with its own MySQL 8.4 database, PHPUnit first as in CI,
  `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, run side by side: **all four OK**, no failure, error or skip.

  | Shard | PHPUnit | Tests run | Python duration | Whole job (PHPUnit and shard) |
  |---|---|---|---|---|
  | 1 | OK, 346 | 58 | 16.5 min | 17:54 |
  | 2 | OK, 346 | 56 (55 + the account setup) | 16.7 min | 18:07 |
  | 3 | OK, 346 | 59 (58 + the account setup) | 16.6 min | 18:04 |
  | 4 | OK, 346 | 165 (164 + the account setup) | 16.2 min | 17:40 |
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`): no errors.
- `composer validate --strict`: valid. `composer install`: done. `composer audit`: no advisories. `composer lint`:
  410 files, 0 failures.
- Historical verifier: PASS (417 files, 11 commits). Project validator: PASS. `git diff --check`: clean.

## Acceptance (recorded in Phase 6 task 3)

Task 2 is **accepted at `fcce356`** (Jim, 2026-11-06).

- Modern core 42206564929: **SUCCESS**, 12/12 jobs. PHP 8.4 / MariaDB 11.4 `http` shards 19:25, 19:24, 13:44, 17:50;
  PHP 8.5 / MySQL 8.4 `http` shards 22:09, 15:08, 16:02, 18:30.
- Baseline integrity 42206564872: **SUCCESS**.
- Every job ran on `ubuntu-24.04`. Annotations: 0. The Node 20 deprecation warning and the Ubuntu 26 notice are gone.

B1, S7 and S8 are ticked in `ROADMAP.md`.
