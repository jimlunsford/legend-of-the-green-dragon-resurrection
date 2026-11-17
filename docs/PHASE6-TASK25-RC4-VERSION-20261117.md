# Phase 6 task 25: the `1.0.0-rc.4` version commit (no tag)

2026-11-17. Made from `91559fa6455e8ad1d0db031e8eb9c3cd7ceee666`, which matched the remote `main`, by
`docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only: no tag, no GitHub release, no
package published or attached anywhere, and the release cut workflow was not run in GitHub. The tag and the
pre-release follow when Jim runs the Release cut workflow, after CI accepts this commit (the checklist's sections 2 to
5). It changes no installed schema or rows, so it has no upgrade step. The showcase README moves to task 26.

## Recorded first

- **Task 24 accepted** at `91559fa`: Modern core 44126961634 passed all 14 jobs (run wall time 18:11).
  - PHP 8.4 / MariaDB 11.4: `checks` 2:52; `http` 1 to 5: 14:00, 9:32, 10:16, 14:06, 10:16; `result` 0:02.
  - PHP 8.5 / MySQL 8.4: `checks` 3:03; `http` 1 to 5: 10:20, 11:34, 12:16, 17:58, 11:46; `result` 0:06.
  - Slowest: 17:58, PHP 8.5 `http (4)`.
  - On both legs the release upgrade check installed `v1.0.0-rc.3` and upgraded it to `91559fa`, with the same schema
    and data as a fresh install (41 tables), and the same with HEAD installed.
  - Baseline integrity 44126961852 passed.
  - Checked in this task through the REST API: both runs' status, conclusion and head commit, each job's times from
    its start and end, and the two upgrade check lines in both `checks` job logs.
- Written into `PHASE6-TASK24-20261117.md` ("Acceptance"), `ROADMAP.md` (task 24's line: "Accepted at `91559fa`,
  Modern core 44126961634 (all 14 jobs; slowest 17:58, PHP 8.5 `http (4)`), Baseline integrity 44126961852", and the
  rc.3 progress list's item 4) and the checklist's section 5, whose rc.3 box is ticked: this run is the first after
  rc.3's cut, and its upgrade check installed `v1.0.0-rc.3` and upgraded it.

## Jim's decisions

Recorded in `ROADMAP.md` ("Jim's decisions (2026-11-17, recorded in task 25)") and in the checklist's section 0:

1. **Go: cut `1.0.0-rc.4`** ("go rc.4", 2026-11-17), the same way as rc.3. This task is the version commit; after CI
   accepts it, Jim runs the release cut workflow, which makes the tag `v1.0.0-rc.4` and the GitHub pre-release. The
   showcase README moves to task 26; every place that named it as task 25 now says task 26 (`ROADMAP.md`'s task list,
   its progress list, decision 1 of 2026-11-13 and decision 2 of 2026-11-16).
2. **`forest.php` with no `op` keeps the forest menu for `1.0.0`** while a fight is stored, as 1.1.2's did: its search
   link leads back into the fight, so it is no dead end (task 24, section 4). Two "After `1.0.0`" items are added to
   `ROADMAP.md`, each needing Jim's decision: `forest.php` showing the stored fight, and the other bare refusals on
   player pages (211, listed by file and line in `PHASE6-TASK24-20261117.md`, section 6). Both are known issues in the
   rc.4 notes, in players' words.

Section 0's other two boxes, checked and ticked for rc.4:

- Every task before the cut is accepted: task 24 at `91559fa`, above.
- The release notes list every open item a host or player can hit, checked against `ROADMAP.md` at this commit: its
  three open boxes are the Dark Horse Tavern's many Continue clicks and the two items above, all after `1.0.0` and all
  known issues in the notes; R11 is done and is the notes' change; the public hosting gates stay covered by "Not
  certified for public hosting".

## The checklist (`docs/RC-CUT-CHECKLIST.md`)

Brought up to date for rc.4 with rc.1's, rc.2's and rc.3's records kept as history: the paragraph on the third cut
now says its sections 2 to 4 are done, and a paragraph on the fourth follows it; section 0 has the rc.4 boxes, ticked;
sections 1, 2, 4 and 5 have an rc.4 line beside the earlier records (open boxes for CI, the tag, the package and the
next commit's upgrade check); section 3 is written for `v1.0.0-rc.4`, with rc.4's tag message as its first fenced
block and the open box for its tag, and rc.3's message and record box below it under "History", moved byte for byte.
rc.3's block there is the message of the tag `v1.0.0-rc.3` exactly (`git cat-file -p v1.0.0-rc.3`, from the line
after the header's blank line: 884 bytes, `cmp` equal). Section 5's rc.3 box is ticked with task 24's run.

## The tag message (checklist section 3)

The release cut workflow takes the first fenced block after `## 3. The tag` and adds one newline. rc.4's block, in
rc.3's shape:

```
Legend of the Green Dragon - Resurrection 1.0.0-rc.4

The fourth test build of Resurrection 1.0.0: a candidate for hosts and players to try. It is not the release, it has
had no independent security review, and it is not certified for public game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs, or an upgrade from 1.0.0-rc.3 or 1.0.0-rc.2 (no step) or 1.0.0-rc.1 (steps 0002 and 0003); no 1.1.2
database is upgraded. Known issues, among them mail only through PHP's mail(), best effort, mail links in the web
server's access log, abuse controls beyond sign-in throttling left to the operator, and 1.1.2 behaviors kept as
recorded: docs/RELEASE-NOTES-1.0.0-rc.4.md. Hosting: docs/HOST-GUIDE.md.
```

Taken out with the workflow's own Python (`section = text.index('## 3. The tag')`, the first `` ```\n `` after it to
the next `\n```, plus one newline), the file is 899 bytes with SHA-256
`c61d49fb0cc717509e58fd80317c993b0d1b43e33888aafa84ab249f5c1322b8`.

Each line, checked at this commit:

- "1.0.0-rc.4": `src/Version.php`. "The fourth test build": rc.1, rc.2 and rc.3 were the first three (`v1.0.0-rc.1`,
  `v1.0.0-rc.2`, `v1.0.0-rc.3`).
- "not the release ... no independent security review ... not certified for public game hosting": the release notes'
  last known issues, `SECURITY.md`, `README.md`, and `project.json` (`public_game_hosting_approved: false`).
- The credit paragraph: unchanged from rc.1 to rc.3; `LICENSE.txt` and `NOTICE` are at those names in the package.
- "an upgrade from 1.0.0-rc.3 or 1.0.0-rc.2 (no step)": `upgrades/` holds 0001 to 0003, all in rc.2 and rc.3; the two
  upgrade runs below ("3 of 3 upgrade steps applied ... Nothing was changed."). "or 1.0.0-rc.1 (steps 0002 and
  0003)": rc.1 has only 0001; the setup.php run below applied 0002 and 0003. All three are in the notes' "Upgrading".
- "no 1.1.2 database is upgraded": `docs/VERSIONING.md` and the host guide.
- Each known issue named is in the rc.4 notes' "Known issues": mail only through `mail()`, best effort; the token in
  the access log; "Abuse controls beyond sign-in throttling are the operator's"; the 1.1.2 behaviors kept.
- `docs/RELEASE-NOTES-1.0.0-rc.4.md` and `docs/HOST-GUIDE.md` exist at those paths.

## The version commit (checklist section 1)

- `src/Version.php`: `CURRENT` is `'1.0.0-rc.4'`.
- `project.json`: `version` `1.0.0-rc.4`, `phase` `release-candidate` (unchanged).
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.4`"; the footer example shows the same; the tag paragraph says the
  rc.3 tag is on its version commit and the rc.4 tag goes on this one once CI accepts it, after which `main` keeps
  `1.0.0-rc.4` until the next cut.
- `tests/tooling/test_version.py`: the pinned value is `1.0.0-rc.4`, the only test change.
- `docs/RELEASE-NOTES-1.0.0-rc.4.md`: the draft paragraph is gone and the notes are dated 2026-11-17; the R11 section
  is kept, checked against task 24's change and this task's rc.3 upgrade run below; "Upgrading" says rc.3 and rc.2
  need no step and rc.1 gets 0002 and 0003; the known issues are rc.3's, checked against `ROADMAP.md` (above), with
  the two new after-`1.0.0` items in players' words.
- `README.md` and `SECURITY.md`: `main` is `1.0.0-rc.4`, the fourth test build, not certified for public hosting;
  their links go to the rc.4 notes (README keeps links to rc.3's, rc.2's and rc.1's).
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md`: the version commit recorded (no behavior change, no upgrade step).
- `git grep 1.0.0-rc.3` at this commit finds only history: the checklist's records, the rc.3 notes, the evidence docs,
  `ROADMAP.md`'s records, `CHANGES-FROM-1.1.2.md`'s rows, `VERSIONING.md`'s tag paragraph, README's link to the rc.3
  notes, and the rc.4 notes' "since 1.0.0-rc.3" and upgrade lines. None states the current version, and no code or
  test names it.
- The doc links follow `Version::CURRENT`, so the pages' links to the host guide go to
  `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v1.0.0-rc.4/docs/HOST-GUIDE.md#…`. They
  resolve once the tag exists. Every anchor the code uses (`5-install`, `53-if-an-install-fails`, `9-upgrade`,
  `10-backups-and-restore`, `11-mail`) is a heading in `docs/HOST-GUIDE.md` at this commit; `DocLinkTest` and
  `CommandLinkTest` pass. Every Markdown link in the changed documents names a file that exists at this commit.
- `.github/workflows/release-cut.yml` is not changed.

## The dry run

The workflow handles an rc.4 cut as it stands: its inputs are typed by hand and nothing in its steps is specific to an
earlier cut. Its defaults are still rc.1's values; a run left at them stops before writing anything, as task 21
recorded.

As in task 23, its Preflight, message, Tag and package steps ran in the session against a throwaway bare copy of the
repository standing in for `origin` (with `historical-source-1.1.2`, `v1.0.0-rc.1`, `v1.0.0-rc.2` and `v1.0.0-rc.3`),
with `main` there set to a candidate commit, `7c1d348`, whose tree is this commit's except this evidence doc's text
(the package and the tag message are made from files this doc is not among; see "The package" below). The four steps'
scripts were taken from `.github/workflows/release-cut.yml` by parsing it with PyYAML, not copied by hand, and run in a
fresh clone of the stand-in checked out at the candidate, with the inputs `1.0.0-rc.4`, the commit, `c61d49fb…22b8`
and `cb56e85e…97df`:

- Preflight: passed (the historical verifier: PASS, 417 files; the validator: PASS, 1106 tracked files).
- The message: section 3's first block, the same 899 bytes as above; `sha256sum -c` OK.
- The tag: `v1.0.0-rc.4`, annotated (tag object `e2adb23` in the stand-in), on the candidate, its message equal to the
  file byte for byte, pushed as the one ref to the stand-in, whose tag and peeled commit then matched.
- The package: checked out at the tag on a clean tree, built twice, 772 files and 3,874,626 bytes each,
  `cb56e85e…97df` both times, the two equal under `cmp` and the hash equal to the input.
- A rerun of the four steps with the tag already on the stand-in found it, checked its type, commit and message, and
  left it as it was ("v1.0.0-rc.4 already exists on 7c1d348… with the exact message; left as it is."), then built the
  same package again.
- The refusals:
  - a wrong message SHA-256 stops the message step (`FAILED`);
  - version `1.0.0-rc.5` with this commit, or `1.0.0-rc.3` with this commit, stops Preflight ("src/Version.php is not
    …");
  - the commit input `0632351` (rc.3's) with the checkout at the candidate stops Preflight (HEAD is not the commit);
  - a checkout at `0632351` with the rc.4 inputs stops Preflight ("src/Version.php is not 1.0.0-rc.4");
  - a wrong package SHA-256 stops the package step.

The publishing steps use `gh` against GitHub and were not run. No tag was made in the repository's own clone (its tags
are still `historical-source-1.1.2`, `v1.0.0-rc.1`, `v1.0.0-rc.2` and `v1.0.0-rc.3`), and nothing was pushed anywhere
but the stand-in.

## The earlier packages

Downloaded from their GitHub pre-releases into their own empty folders outside the tree, through the REST API
(`gh api …/releases/tags/<tag>`, then each asset with `Accept: application/octet-stream`; `gh release download` uses
GraphQL, which these sessions cannot reach):

| | Zip | Size | SHA-256 | Its `.sha256` |
| --- | --- | --- | --- | --- |
| `v1.0.0-rc.1` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 3,626,284 bytes | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.2` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.2.zip` | 3,867,683 bytes | `133625b327d21878c173ac856e5b70b306b69735ca95151e8923cae3030ee439` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.3` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.3.zip` | 3,868,683 bytes | `6fe8b1323f140bdc2b2ef3ce45d7ee0a917f5a40689e7f462b210570d5603569` | 121 bytes, `sha256sum -c` OK |

All three are the values the brief gives.

## The package, built twice

`python3 scripts/build-release-package.py --out <folder>`, run twice from a fresh checkout of the candidate into two
empty folders outside the tree:

| | Folder 1 | Folder 2 |
| --- | --- | --- |
| File | `legend-of-the-green-dragon-resurrection-1.0.0-rc.4.zip` | the same |
| Entries | 772 files | 772 files |
| Size | 3,874,626 bytes | 3,874,626 bytes |
| SHA-256 | `cb56e85e120b9a0789762f55f509bfc78ae66b3462f6def383da8daed9c797df` | the same |

`cmp` finds the two identical, and the `.zip.sha256` files are equal. The zip has one top folder,
`legend-of-the-green-dragon-resurrection-1.0.0-rc.4/`, with `upload/`, `INSTALL.txt`, `HOST-GUIDE.md`,
`RELEASE-NOTES-1.0.0-rc.4.md`, `THIRD-PARTY-LICENSES.md`, `hosting/`, `LICENSE.txt`, `NOTICE` and `README.txt`. The
build reads the files Git lists with fixed entry times, and the only document it takes from `docs/` beyond the host
guide and the third-party licenses is the release notes, so this evidence doc and the checklist do not change it. It
was built again, twice, from the commit itself before the push (the task report).

## The fresh install

The zip unpacked into an empty folder, and only `upload/` copied into an empty document root, served by PHP 8.5.12's
built-in server on `127.0.0.1` as `nobody`, with `LOGD_DBCONNECT` pointing at an empty private folder outside it, and an
empty MySQL 8.4 database. Mail went nowhere (`sendmail_path=/bin/false`).

- `setup.php`: the 7 checks were ok or info (plain HTTP allowed only because the request came from the machine
  itself). The token file was written beside the configuration path with the lines the token screen gave, and the
  install form took the database, the first administrator and the recommended modules.
- Result: "The game is installed. The configuration file was written to …/private/dbconnect.php (0600). 41 tables
  were created; 24 modules installed, 23 active. The first administrator is Admin. The token file was deleted, and it
  cannot be used again." The last screen's maintenance choice: cron. The token file was gone; `setup.php` then answered
  404.
- The home page reads "Version: Resurrection 1.0.0-rc.4, based on LoGD 1.1.2 Dragonprime Edition".
- Played to the village: sign in (303 to the village, 302 to the new day), the new day (race Human, specialty Thieving
  Skills, then "It is a new day!"), then the village, "Degolburg Square".
- The database: 41 tables; 24 modules, 23 active; master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds
  0001, 0002 and 0003. The PHP error log stayed empty (0 bytes).

## The upgrade from rc.3 (Jim's R11 path)

Every page below was reached by the previous page's own links or forms, and every redirect was followed; the only
fixtures are the ones named (`forestchance` 0 for one search, the rollover, and `turns` 0 in step e). PHP 8.5.12,
MySQL 8.4, the server and configuration set up as for the fresh install.

**a. rc.3, a fight stored.** The rc.3 package's `upload/` installed through its own `setup.php` on an empty database
(41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.3). As Admin: sign in, the new day (Human, Thieving
Skills), the village, the forest, "Look for Something to Kill", and the search form pressed with `forestchance` 0 for
that one request: 200, The Forest, with the Fight and Run forms. The player: 10 turns, 9 of 10 hitpoints, alive, an
enemy stored (`badguy` MD5 `93b41c75…bd56d`). The database was dumped there, mid-session (155,423 bytes).

**b. rc.3, the presses.** In the same session: the fight's Fight form pressed (200, the fight); the same form pressed
again (409, framed, "That action has expired. Nothing was changed.", with a village link); that village link; the
village's forest link. A game-day rollover (the player's `lastnewday` moved back one game day). The forest's "Look for
Something to Kill" link: 302 to `newday.php`, "It is a new day!"; its "Begin New Day" form pressed: "It is a new
day!" with one link, Continue, to `forest.php?op=search`. The real Continue:

```
GET forest.php?op=search&c=18-171502 -> 409
title: (none)   links: none   forms: none
Forest combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains invalid.
```

This is R11 as Jim's server showed it. The player's turns, hitpoints, gold, experience and stored enemy were the same
before and after it. The rc.3 PHP error log was empty.

**c. The swap.** The server stopped; the step a dump restored into the emptied database (41 tables, the same stored
enemy, MD5 `93b41c75…bd56d`); rc.4's `upload/` copied into a new document root and served with the same
configuration file. The home page reads "Version: Resurrection 1.0.0-rc.4", with no "Upgrade Needed"; `setup.php`
answers 404. `php scripts/upgrade.php --dry-run`, then `--backup-confirmed`, from the new folder as `nobody`: both "The
database is current: 3 of 3 upgrade steps applied, and every installed bundled module is at its version. Nothing was
changed.", exit 0. `upgrade_steps` holds 0001, 0002 and 0003.

**d. rc.4, the same presses.** Signing in returns the player to their kept page, the search, which on rc.4 shows the
stored fight: 200, The Forest, the Fight and Run forms. Then the same presses as on rc.3:

| Request | Answer |
| --- | --- |
| `POST forest.php?op=fight` (the Fight form) | 200, The Forest, the fight |
| the same form again | 409, "That action has expired", framed, with a village link |
| `GET village.php` (that link) | 200, Degolburg Square |
| `GET forest.php` (the village's forest link) | 200, The Forest |
| the rollover (`lastnewday` back one game day) | |
| `GET forest.php?op=search` (the search link) | 302 to `newday.php`, then 200, "It is a new day!" |
| `POST newday.php?continue=1` (Begin New Day) | 200, "It is a new day!", one link: Continue, `forest.php?op=search` |
| `GET forest.php?op=search&c=9-172743` (the real Continue) | **200, The Forest, the Fight and Run forms**: "Farmboy Admin … HP 10 / 10 … Cunning Sapling Level 1 · Arrrr! HP 8 / 10 … Fight Run" |

Nothing was spent or reset by Continue: turns 12, hitpoints 10 of 10 (the new day's), gold 50, experience 0 and the
stored enemy (MD5 `b8003d36…f427e`, the one left by the round pressed before the rollover) were the same before and
after it. The fight was then played to its end through its own Fight form: six rounds, "Cunning Sapling … HP 0 / 10 ·
DEAD", with the forest and village links; the player gained 30 gold and 14 experience, and the stored enemy was
cleared. The page's village link: 200, Degolburg Square.

**e. rc.4, no forest fights left.** `turns` set to 0 (fixture); the village's forest link (200, The Forest); its "Look
for Something to Kill" link: 200, The Forest, "You are too tired to search the forest any longer today. Perhaps
tomorrow you will have more energy.", followed by the forest's description and its full menu (healer, both search
links, village, the outhouse and the forest's events). The game state was unchanged.

**f. The error log.** The PHP error log was empty throughout (0 bytes on rc.3 before the swap, and 0 bytes on rc.4
after steps c to e).

## The upgrade from rc.2

- The rc.2 package's `upload/` installed the same way on an empty MySQL 8.4 database through its own `setup.php`: 7
  checks ok or info; 41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.2; played to the village (the new
  day, then Degolburg Square). `upgrade_steps` holds 0001, 0002 and 0003. The error log was empty.
- The server stopped; rc.4's `upload/` copied into a new document root and served with the same configuration file.
  The home page reads 1.0.0-rc.4, with no "Upgrade Needed"; `setup.php` answers 404.
- `php scripts/upgrade.php --dry-run`, then `--backup-confirmed`, as `nobody`: both "The database is current: 3 of 3
  upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.", exit 0. No step
  runs.
- Signed in as Admin on rc.4: 303 to the village, 302 to the kept page, and the village, Degolburg Square. 41 tables.
  The PHP error log stayed empty.

## The upgrade from rc.1

- The rc.1 package (its zip holds the upload itself) installed the same way on an empty MySQL 8.4 database through its
  own `setup.php`: 7 checks ok or info; 41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.1; played to
  the village. The error log was empty.
- Before the upgrade: master 5 is `556EEFBFBD6C697468` (the U+FFFD rc.1 wrote); `upgrade_steps` holds only 0001; the
  nasty word list holds `kyrp` with U+FFFD (`6B797270EFBFBD2A`); no sign-in limit settings.
- The server stopped; rc.4's `upload/` served with the same configuration file. The home page shows "Upgrade Needed".
- `setup.php`'s upgrade: the dry run listed 0002 ("Add the sign-in limit settings with their defaults") and 0003
  ("Write the UTF-8 text 1.1.2 meant into three seed values") as pending, "Dry run: nothing was changed." With a new
  token file, the Admin login and password and the backup box: "applied and recorded step 0002-signin-limits",
  "applied and recorded step 0003-utf8-seed-text", "Upgrade complete: applied 2 upgrade step(s) and 0 module
  upgrade(s). The database is current. The token file was deleted, and it cannot be used again." The token file was
  gone.
- After: master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds 0001, 0002 and 0003; `serverlanguages`
  holds "Français" and "Español" (UTF-8, no U+FFFD); the nasty word list holds "kyrpä*" (`6B797270C3A42A`) and no
  U+FFFD; the sign-in limits are `signin_fail_pair` 5, `signin_fail_address` 20 and `signin_fail_account` 50; 41
  tables. The home page reads 1.0.0-rc.4. Signed in as Admin: the village; the Masters Editor (through the Superuser
  Grotto's link) lists "Unélith", with no U+FFFD on the page; then the village. The PHP error log stayed empty.

## Local results

Supporting evidence only; CI is the acceptance. Run on this commit's tree (the shards, PHPUnit and the upgrade check on
the local candidate `7c1d348`, whose tree is this commit's except this evidence doc's text).

- `test_version.py` (the new pinned value), `test_credit_wording.py`, `test_release_package.py`, `test_host_guide.py`,
  `test_utf8_text.py` and `test_module_author_guide.py`: 39 tests, OK on PHP 8.4.27 and PHP 8.5.12. `DocLinkTest` and
  `CommandLinkTest`: 4 tests, 75 assertions, OK on both.
- PHPUnit, 524 tests, 152,511 assertions, `failOnSkipped`: OK on PHP 8.4.27 with MariaDB 11.4 (2:21) and PHP 8.5.12
  with MySQL 8.4 (3:10).
- All five shards on both legs, with the local extras hidden (`PLAYWRIGHT_BROWSERS_PATH` an empty folder,
  `RESURRECTION_REQUIRE_PHP=1`), each on a fresh database installed by `FreshInstallTest`, each leg's five run side by
  side:

  | Shard | Tests run | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
  | --- | --- | --- | --- |
  | 1 | 62 | 939 s | 1,028 s |
  | 2 | 60 | 944 s | 1,030 s |
  | 3 | 233 | 939 s | 1,031 s |
  | 4 | 61 | 937 s | 1,018 s |
  | 5 | 61 | 939 s | 1,030 s |

  Every run: 0 failures, 0 errors, 0 skipped. Task 24's five R11 tests passed on both legs.

- `scripts/release-upgrade-check.py`, both modes, each on an empty database, on both legs (PHP 8.4.27 / MariaDB 11.4
  and PHP 8.5.12 / MySQL 8.4):
  - Default mode: "Release upgrade check: v1.0.0-rc.3 installed, upgraded to 7c1d348, and the same schema and data as a
    fresh install (41 tables)", with "The database is current: 3 of 3 upgrade steps applied … Nothing was changed."
    `v1.0.0-rc.3` is the highest release tag before this commit.
  - `--release-ref HEAD`: "HEAD installed, upgraded to 7c1d348, and the same schema and data as a fresh install (41
    tables)".

- PHP lint (479 files, 0 failures) on 8.4 and 8.5, both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`: no
  errors), `composer validate --strict`, install and audit (no advisories), the historical verifier (PASS, 417 files,
  11 commits), the project validator (PASS, 1106 tracked files), `scripts/asset-versions.py --check`, and `git diff
  --check`: clean. `LICENSE.txt` and `README.txt` are identical to `historical-source-1.1.2`.

## Next

CI acceptance of this commit. Then Jim runs the Release cut workflow from the Actions tab with the four inputs in the
task report (the checklist's sections 3 and 4), and tests rc.4 on his server, including an upgrade from rc.3. Then
`1.0.0`, and the showcase README (task 26).
