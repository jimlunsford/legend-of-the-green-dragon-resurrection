# Phase 6 task 29: the `1.0.0-rc.5` version commit (no tag)

2026-11-19. Made from `4aaa47ac286982f55190ef34b45bccfb284ac2be`, which matched the remote `main`, by
`docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only: no tag, no GitHub release, no
package published or attached anywhere, and the release cut workflow was not run in GitHub. The tag and the
pre-release follow when Jim runs the Release cut workflow, after CI accepts this commit (the checklist's sections 2 to
5). It changes no installed schema or rows, so it has no upgrade step. Then Jim's server test of rc.5.

## Recorded first

- **Task 28 accepted** at `4aaa47a`: Modern core 44453802484 passed all 14 jobs (run wall time 15:05).
  - PHP 8.4 / MariaDB 11.4: `checks` 2:18; `http` 1 to 5: 10:01, 11:47, 13:35, 9:01, 13:59; `result` 0:06.
  - PHP 8.5 / MySQL 8.4: `checks` 2:35; `http` 1 to 5: 10:35, 14:54, 12:57, 10:23, 11:19; `result` 0:06.
  - Slowest: 14:54, PHP 8.5 `http (2)`.
  - On both legs: "Release upgrade check: v1.0.0-rc.4 installed, upgraded to 4aaa47a, and the same schema and data as a
    fresh install (41 tables)", and the same with HEAD installed.
  - Baseline integrity 44453802448 passed.
  - Checked in this task through the REST API: both runs' status, conclusion and head commit (`completed`, `success`,
    `4aaa47a…`), each job's times from its start and end, and the two upgrade check lines in both `checks` job logs.
- Written into `PHASE6-TASK28-20261119.md` ("Acceptance (recorded in Phase 6 task 29, 2026-11-19)"), `ROADMAP.md`
  (task 28's line, ticked: "Accepted at `4aaa47a`, Modern core 44453802484 (all 14 jobs; slowest 14:54, PHP 8.5
  `http (2)`; …), Baseline integrity 44453802448 (recorded in task 29)", and the progress list's item 8).

## Jim's decisions

rc.5 is part of the plan Jim set on 2026-11-18, recorded in `ROADMAP.md`: "Jim's decisions (2026-11-18, recorded in
task 26)", decision 1 ("R12 and R13 are fixed before `1.0.0` (task 26), and `1.0.0-rc.5` is cut and server-tested,
the same way as rc.4, before `1.0.0`"), and "Jim's decision (2026-11-18, recorded in task 27)", which put no dead
ends (tasks 27 and 28) before it and made rc.5's version commit task 29, then his server test. No new decision was
needed. In the checklist's section 0 that is the go for `1.0.0-rc.5`.

Section 0's other two boxes, checked and ticked for rc.5:

- Every task before the cut is accepted: task 26 at `8ab8888` (Modern core 44337851502; Baseline integrity
  44337851494), task 27 at `c45337e` (Modern core 44400078528; Baseline integrity 44400078510) and task 28 at `4aaa47a`
  (above), each with all 14 jobs.
- The release notes list every open item a host or player can hit, checked against `ROADMAP.md` at this commit: the
  after-`1.0.0` list says "Nothing is listed here"; the only open boxes left are task 29 itself, 30 (`1.0.0`), 31 (the
  README) and the checklist's post-release steps, none of which a host or player can hit; R12 and R13 and the four
  items that were after `1.0.0` are done and are the notes' changes; the tavern door's one extra press each way (task
  28, recorded for Jim) and the refusals kept plain on purpose are known issues; the public hosting gates stay covered
  by "Not certified for public hosting".

## The checklist (`docs/RC-CUT-CHECKLIST.md`)

Brought up to date for rc.5 with rc.1's to rc.4's records kept as history: the paragraph on the fourth cut now says
its sections 2 to 4 are done (recorded in task 26), and a paragraph on the fifth follows it; section 0 has the rc.5
boxes, ticked; sections 1, 2, 4 and 5 have an rc.5 line beside the earlier records (open boxes for CI, the tag, the
package and the next commit's upgrade check; section 5's `main` line says `main` keeps `1.0.0-rc.5` until the next cut
sets `1.0.0`); section 3 is written for `v1.0.0-rc.5`, with rc.5's tag message as its first fenced block and the open
box for its tag, and rc.4's message and record box below it under "History", moved byte for byte. rc.4's block there
is the message of the tag `v1.0.0-rc.4` exactly (`git cat-file -p v1.0.0-rc.4`, from the line after the header's
blank line: 899 bytes, SHA-256 `c61d49fb…22b8`, `cmp` equal).

## The tag message (checklist section 3)

The release cut workflow takes the first fenced block after `## 3. The tag` and adds one newline. rc.5's block, in
rc.4's shape:

```
Legend of the Green Dragon - Resurrection 1.0.0-rc.5

The fifth test build of Resurrection 1.0.0: a candidate for hosts and players to try. It is not the release, it has
had no independent security review, and it is not certified for public game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs, or an upgrade from 1.0.0-rc.4, 1.0.0-rc.3 or 1.0.0-rc.2 (no step) or 1.0.0-rc.1 (steps 0002 and
0003); no 1.1.2 database is upgraded. Known issues, among them mail only through PHP's mail(), best effort, mail links
in the web server's access log, abuse controls beyond sign-in throttling left to the operator, and 1.1.2 behaviors
kept as recorded: docs/RELEASE-NOTES-1.0.0-rc.5.md. Hosting: docs/HOST-GUIDE.md.
```

Taken out with the workflow's own Python (`section = text.index('## 3. The tag')`, `start = text.index('```\n',
section) + 4`, `end = text.index('\n```', start)`, `text[start:end] + '\n'`), the file is 910 bytes with SHA-256
`df9dbfacddcfeeebbc01cdf2237cac5036c6ab80f11c74586d41c20063317f0b`.

Each line, checked at this commit:

- "1.0.0-rc.5": `src/Version.php`. "The fifth test build": rc.1 to rc.4 were the first four (`v1.0.0-rc.1` to
  `v1.0.0-rc.4`, all present).
- "not the release ... no independent security review ... not certified for public game hosting": the release notes'
  last known issues, `SECURITY.md`, `README.md`, and `project.json` (`public_game_hosting_approved: false`).
- The credit paragraph: unchanged from rc.1 to rc.4; `LICENSE.txt` and `NOTICE` are at those names in the package.
- "an upgrade from 1.0.0-rc.4, 1.0.0-rc.3 or 1.0.0-rc.2 (no step)": `upgrades/` holds 0001 to 0003, all in rc.2, rc.3
  and rc.4; the three upgrade runs below ("The database is current: 3 of 3 upgrade steps applied … Nothing was
  changed."). "or 1.0.0-rc.1 (steps 0002 and 0003)": rc.1 has only 0001; the setup.php run below applied 0002 and
  0003. All four are in the notes' "Upgrading".
- "no 1.1.2 database is upgraded": `docs/VERSIONING.md` and the host guide.
- Each known issue named is in the rc.5 notes' "Known issues": mail only through `mail()`, best effort; the token in
  the access log; "Abuse controls beyond sign-in throttling are the operator's"; the 1.1.2 behaviors kept.
- `docs/RELEASE-NOTES-1.0.0-rc.5.md` and `docs/HOST-GUIDE.md` exist at those paths.

## The version commit (checklist section 1)

- `src/Version.php`: `CURRENT` is `'1.0.0-rc.5'`.
- `project.json`: `version` `1.0.0-rc.5`, `phase` `release-candidate` (unchanged).
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.5`"; the footer example shows the same; the tag paragraph says the
  rc.4 tag is on its version commit and the rc.5 tag goes on this one once CI accepts it, after which `main` keeps
  `1.0.0-rc.5` until the next cut.
- `tests/tooling/test_version.py`: the pinned value is `1.0.0-rc.5`, the only test change.
- `docs/RELEASE-NOTES-1.0.0-rc.5.md`: the draft paragraph is gone and the notes are dated 2026-11-19 ("Cut by
  `docs/RC-CUT-CHECKLIST.md` on the maintainer's go; the tag is `v1.0.0-rc.5`. The fifth test build …").
  - "What changed since 1.0.0-rc.4" keeps tasks 26 to 28's player words, each checked against the code at this commit
    and, where a path reaches it, against the rc.4 upgrade run below: R12 (every training GET begins a due new day,
    `resurrection_checkday_on_rollover()` in `lib/training_combat.php`; `badnav.php` begins it before replaying a page
    with `checkday()`'s mark; a stale Challenge is the framed 409 with the way to the new day; the graveyard's and the
    inn's "There is no dragon to run from."), R13 (the ability round is the forest fight with Fight and Run), no dead
    ends part 1 (the framed refusals, "Nothing was changed.", no battle screen for a rolled-back round, `forest.php`
    with no `op` showing a stored fight), part 2 (the modules' framed refusals, the stones bet drawing the first pair in
    `modules/game_stones.php`, names through `resurrection_appoencode_closed()` in the character panel, `hof.php` and
    `lib/commentary.php`, and "No Races Installed" / "No Specialties Installed" in `lib/race_onboarding.php` and
    `lib/specialty_onboarding.php`).
  - "Upgrading": from rc.4, rc.3 or rc.2 no step; from rc.1, `0002-signin-limits` and `0003-utf8-seed-text`.
  - "Known issues": rc.4's, checked against `ROADMAP.md` at this commit. The forest's own page showing the menu, the
    bare refusals and the Dark Horse games' many clicks are gone (tasks 27 and 28); the tavern door's one extra press
    each way is there (task 28 added it to the draft); and one new line: "Refusals on the staff tools and on pages
    outside the game itself (the sign-in, mail, the MoTD, petitions) stay plain on purpose: a line of text, as before."
    (`tests/tooling/test_no_bare_refusals.py`, groups two and three).
- `README.md` and `SECURITY.md`: `main` is `1.0.0-rc.5`, the fifth test build, not certified for public hosting; their
  links go to the rc.5 notes (README keeps links to rc.4's, rc.3's, rc.2's and rc.1's).
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md`: the version commit recorded (no behavior change, no upgrade step).
- `git grep 1.0.0-rc.4` at this commit finds only history: the checklist's records, the rc.4 notes, the evidence docs,
  `ROADMAP.md`'s records, `CHANGES-FROM-1.1.2.md`'s rows, `VERSIONING.md`'s tag paragraph, README's link to the rc.4
  notes, and the rc.5 notes' "since 1.0.0-rc.4" and upgrade lines. None states the current version, and no code or
  test names it (three test docstrings name "Jim's rc.4 server" as where R12 and R13 were found).
- The doc links follow `Version::CURRENT`, so the pages' links to the host guide go to
  `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v1.0.0-rc.5/docs/HOST-GUIDE.md#…`. They
  resolve once the tag exists. Every anchor the code uses (`5-install`, `53-if-an-install-fails`, `9-upgrade`,
  `10-backups-and-restore`, `11-mail`) is a heading in `docs/HOST-GUIDE.md` at this commit (sections 5, 5.3, 9, 10 and
  11; task 28's addition is in section 13, "Troubleshooting", which no code links by anchor); `DocLinkTest` and
  `CommandLinkTest` pass. Every Markdown link in the changed documents names a file that exists at this commit.
- `.github/workflows/release-cut.yml` is not changed.

## The dry run

The workflow handles an rc.5 cut as it stands: its inputs are typed by hand and nothing in its steps is specific to an
earlier cut. Its defaults are still rc.1's values; a run left at them stops before writing anything.

As in task 25, its Preflight, message, Tag and package steps ran in the session against a throwaway bare copy of the
repository standing in for `origin` (with `historical-source-1.1.2` and `v1.0.0-rc.1` to `v1.0.0-rc.4`), with `main`
there set to a candidate commit, `1c8be00`, whose tree is this commit's except this evidence doc's text (the package
and the tag message are made from files this doc is not among; see "The package" below). The four steps' scripts were
taken from `.github/workflows/release-cut.yml` by parsing it with PyYAML, not copied by hand, and each run was a fresh
clone of the stand-in checked out at the given commit, with `GITHUB_ENV` and `RUNNER_TEMP` as the runner gives them and
the inputs `1.0.0-rc.5`, the commit, `df9dbfac…7f0b` and `2d8e135e…a800`:

- Preflight: passed (the historical verifier: PASS, 417 files, 11 commits; the validator: PASS, 1112 tracked files).
- The message: section 3's first block, the same 910 bytes as above; `sha256sum -c` OK.
- The tag: `v1.0.0-rc.5`, annotated (tag object `9b77a47` in the stand-in), on the candidate, its message equal to the
  file byte for byte, pushed as the one new ref to the stand-in, whose tag and peeled commit then matched.
- The package: checked out at the tag on a clean tree, built twice, 772 files and 3,919,931 bytes each,
  `2d8e135e…a800` both times, the two equal under `cmp` and the hash equal to the input.
- A rerun of the four steps with the tag already on the stand-in found it, checked its type, commit and message, and
  left it as it was ("v1.0.0-rc.5 already exists on 1c8be00… with the exact message; left as it is.", tag object still
  `9b77a47`), then built the same package again.
- The refusals:
  - a wrong message SHA-256 stops the message step (`FAILED`);
  - version `1.0.0-rc.6` with this commit, or `1.0.0-rc.4` with this commit, stops Preflight ("src/Version.php is not
    1.0.0-rc.6", "… is not 1.0.0-rc.4");
  - the commit input `8aa67b8` (rc.4's) with the checkout at the candidate stops Preflight (HEAD is not the commit);
  - a checkout at `8aa67b8` with the rc.5 inputs stops Preflight ("src/Version.php is not 1.0.0-rc.5");
  - a wrong package SHA-256 stops the package step (the `.sha256` files differ).

The publishing steps use `gh` against GitHub and were not run. No tag was made in the repository's own clone (its tags
are still `historical-source-1.1.2` and `v1.0.0-rc.1` to `v1.0.0-rc.4`), and nothing was pushed anywhere but the
stand-in. Logs: `/mnt/project-files/phase6-task29/first.log` and `refusals.log`.

## The earlier packages

Downloaded from their GitHub pre-releases, each into its own empty folder outside the tree, through the REST API
(`gh api …/releases/tags/<tag>`, then each asset with `Accept: application/octet-stream`; `gh release download` uses
GraphQL, which these sessions cannot reach):

| | Zip | Size | SHA-256 | Its `.sha256` |
| --- | --- | --- | --- | --- |
| `v1.0.0-rc.1` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 3,626,284 bytes | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.2` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.2.zip` | 3,867,683 bytes | `133625b327d21878c173ac856e5b70b306b69735ca95151e8923cae3030ee439` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.3` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.3.zip` | 3,868,683 bytes | `6fe8b1323f140bdc2b2ef3ce45d7ee0a917f5a40689e7f462b210570d5603569` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.4` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.4.zip` | 3,874,626 bytes | `cb56e85e120b9a0789762f55f509bfc78ae66b3462f6def383da8daed9c797df` | 121 bytes, `sha256sum -c` OK |

All four are the values the brief gives.

## The package, built twice

`python3 scripts/build-release-package.py --out <folder>`, run twice from a fresh checkout of the candidate into two
empty folders outside the tree:

| | Folder 1 | Folder 2 |
| --- | --- | --- |
| File | `legend-of-the-green-dragon-resurrection-1.0.0-rc.5.zip` | the same |
| Entries | 772 files | 772 files |
| Size | 3,919,931 bytes | 3,919,931 bytes |
| SHA-256 | `2d8e135e1c4bff0e14b88e32f9fd8dc4cdcf4fc83e69e022e5aca07c4b55a800` | the same |

`cmp` finds the two identical, and the `.zip.sha256` files are equal. The zip has one top folder,
`legend-of-the-green-dragon-resurrection-1.0.0-rc.5/`, with `upload/`, `INSTALL.txt`, `HOST-GUIDE.md`,
`RELEASE-NOTES-1.0.0-rc.5.md`, `THIRD-PARTY-LICENSES.md`, `hosting/`, `LICENSE.txt`, `NOTICE` and `README.txt`. The
build reads the files Git lists with fixed entry times, and the only document it takes from `docs/` beyond the host
guide and the third-party licenses is the release notes, so this evidence doc and the checklist do not change it. It
was built again, twice, from the commit itself before the push (the task report).

## The fresh install

The rc.5 zip unpacked into an empty folder, and only `upload/` copied into an empty document root, served by PHP
8.5.12's built-in server on `127.0.0.1` as `nobody`, with `LOGD_DBCONNECT` pointing at an empty private folder outside
it, and an empty MySQL 8.4 database. Mail went nowhere (`sendmail_path=/bin/false`); errors went to a log file
(`log_errors=1`, `display_errors=0`, `error_reporting=-1`). The same setup serves every run below.

- `setup.php`: the 7 checks were OK or Note (PHP version, extensions, `memory_limit`, `max_execution_time` 30 seconds
  as a note, `set_time_limit()`, the configuration folder, and HTTPS: "no, but this request comes from this machine").
  The token file was written beside the configuration path, and the install form took the database, the first
  administrator and the recommended modules.
- Result: "The game is installed. The configuration file was written to …/private/dbconnect.php (0600). 41 tables
  were created; 24 modules installed, 23 active. The first administrator is Admin. The token file was deleted, and it
  cannot be used again." The last screen's maintenance choice: cron ("Web maintenance stays off."). The token file was
  gone; `setup.php` then answered 404.
- The home page reads "Version: Resurrection 1.0.0-rc.5, based on LoGD 1.1.2 Dragonprime Edition".
- Played to the village: the sign-in form (303 to `village.php`, 302 to `newday.php`), the new day (race Human,
  specialty Thieving Skills, then "It is a new day!" and its Begin New Day), then the village, "Degolburg Square",
  whose footer reads 1.0.0-rc.5.
- The database: 41 tables; 24 modules, 23 active; master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds
  `0001-account-mail`, `0002-signin-limits` and `0003-utf8-seed-text`. The PHP error log stayed empty (0 bytes).

## The upgrade from rc.4 (Jim's paths, steps a to f)

Every page below was reached by the previous page's own links or forms, every redirect was followed by its Location
header, and every sign-in went through the home page's form. The fixtures set state only, each named where it is
used. Each request's status, title, navigation links and forms are in `/mnt/project-files/phase6-task29/`
(`rc4ab.final.out` for steps a and b, `rc5cde.final.out` for c to e).

**a. rc.4, the two players.** The rc.4 package's `upload/` installed through its own `setup.php` on an empty database
(41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.4). Two characters made through the sign-up form
(`create.php`, "Your account was created."), each signed in and taken through the new day:

- **Trainee** (Human, Thieving Skills). Fixture: experience 100 (what level 1 needs to train), attack 15, defense 15,
  hitpoints 40 of 40, so that the master fight can be won. The village's training link: 200, "Bluspring's Warrior
  Training", "You are ready to challenge your master", the Challenge form. The kept page (`restorepage`) is
  `train.php`.
- **Shadow** (Human, Dark Arts). Fixture: Dark Arts skill 1 and 2 uses left today (a new character has skill 0, and
  Skeleton Crew needs skill 1). The village's forest link, "Look for Something to Kill", and the search form pressed
  with `forestchance` 0 for that one request (put back to 15 after it): 200, The Forest, the fight with Fight, Run and
  "Skeleton Crew (1)" under "Special Abilities, 2 uses left".

The database was dumped there (171,590 bytes; Shadow's stored enemy MD5 `c68e4236…72a9`).

**b. rc.4, the old behavior.**

- R12. The rollover (Trainee's `lastnewday` back one game day). The yard has no sign-out link, so the player came back
  as Jim did, in a new browser: the home page's form, 303 to `village.php`, 302 to `badnav.php`, 200, the kept yard
  with its Challenge form. Challenge pressed:

  ```
  POST train.php?op=challenge -> 409
  title: (none)   links: none   forms: none
  Training unavailable. Stored state was preserved. Reload for a fresh form, or request operator repair if state remains invalid.
  ```

  Nothing was spent (`seenmaster`, turns, gold, experience, level and the stored enemy as before).
- R13. In Shadow's session, Skeleton Crew pressed on the battle screen: 200, "Special Abilities", one link, Continue
  (`forest.php?op=specialty`), and the abilities form; 1 use left. The round page's Continue: 200, "Special
  Abilities", the Skeleton Crew form only. Pressed: 200, one link, Continue; 0 uses left. The round page's Continue:

  ```
  GET forest.php?op=specialty&c=15-103957 -> 200
  title: Special Abilities   navigation links: none   forms: none
  ```

  The only links on the page are the panel's mail, MoTD and petition popups. The fight was still stored. This is R13
  as Jim's server showed it.
- The rc.4 PHP error log was empty (0 bytes).

**c. The swap.** The server stopped; the step a dump restored into the emptied database (41 tables, Shadow's stored
enemy MD5 `c68e4236…72a9`, 2 Dark Arts uses; Trainee at level 1 with 100 experience and the yard kept); rc.5's
`upload/` copied into a new document root and served with the same configuration file. The home page reads "Version:
Resurrection 1.0.0-rc.5", with no "Upgrade Needed"; `setup.php` answers 404. `php scripts/upgrade.php --dry-run`, then
`--backup-confirmed`, from the new folder as `nobody`: both "The database is current: 3 of 3 upgrade steps applied,
and every installed bundled module is at its version. Nothing was changed.", exit 0. No step ran; `upgrade_steps`
holds 0001 to 0003; 41 tables.

**d. rc.5, the same presses.**

- R12. The rollover, then the sign-in in a new browser, as on rc.4:

  | Request | Answer |
  | --- | --- |
  | `POST login.php` (the home page's form) | 303 to `village.php`, 302 to `badnav.php` |
  | `GET badnav.php` | 200, the yard rc.4 kept, with its Challenge form (see the note below) |
  | `POST train.php?op=challenge` (that form) | **409, framed**: "Bluspring's Warrior Training", "Training unavailable. Nothing was changed.", the credits, one link, `train.php`, which is also the return page; nothing spent |
  | `GET train.php` (that link) | 302 to `newday.php`, 200, "It is a new day!" with Begin New Day |
  | `POST newday.php?continue=1` (Begin New Day) | 200, "It is a new day!", one link: Continue, `train.php` |
  | `GET train.php` (Continue) | 200, the yard, "You are ready to challenge your master", the Challenge form |
  | `POST train.php?op=challenge` | 200, the master fight (`seenmaster` 1, a fight stored) |
  | three presses of its Fight form | "You have defeated Mireraband!", level 2 |
  | the page's village link | 200, Degolburg Square |

  The note: the sign-in's replay goes to the new day only when the kept page carries `checkday()`'s mark (task 26),
  and a yard page that rc.4 kept does not, since rc.4's yard never called `checkday()`. So on an upgraded game the
  first sign-in after the rollover shows that old yard again, and its Challenge is the framed refusal above, whose one
  link leads to the new day: no dead end, one press more than on a page rc.5 kept. With a yard page rc.5 kept, the
  same path goes straight to the new day, shown right after: the village's training link (the kept page has the mark),
  the rollover, a new browser's sign-in: 303, 302, **200, "It is a new day!"**; Begin New Day; its Continue to the yard
  with the Challenge form; the village by its link. Recorded under "Found" below.
- R13. Shadow signs in (a new browser): 303, 302 to `badnav.php`, 200, The Forest, the stored fight with Fight, Run
  and "Skeleton Crew (1)".

  | Press | Answer |
  | --- | --- |
  | Skeleton Crew | 200, The Forest: the fight with Fight and Run, "Skeleton Crew (1)" still offered; 1 use left; the fight stored |
  | Skeleton Crew (the last use) | 200, The Forest: the fight with Fight and Run, no ability left; 0 uses; the fight stored |
  | Fight, five times | each page the fight with Fight and Run; then "Cunning Sapling … HP 0 / 10 · DEAD", with the forest and village links |
  | the village link | 200, Degolburg Square |

  Every page while the fight was stored had Fight and Run.

**e. Also on rc.5.**

- R11. From the village: the forest link (200, The Forest, its menu), "Look for Something to Kill" (200, the search
  form), the search pressed with `forestchance` 0 for that one request: the fight, stored (MD5 `2c22b397…a255`), with
  Fight and Run. The rollover (Shadow's `lastnewday` back one game day). On rc.5 no page shows the search link while a
  fight is stored: since task 27 the forest shows the stored fight, and the fight's own pages offer only the fight.
  So the search link is reached the one way left, from the forest page loaded before the search (as a second tab or
  the back button gives it): `GET forest.php?op=search&c=…` is not among the page's allowed links, so the game
  answers 302 to `badnav.php`, which shows the kept fight, 200, Fight and Run, nothing spent. The fight played to its
  end by its Fight form (five presses), then the forest link (200, The Forest), its search link: 302 to `newday.php`,
  200, "It is a new day!"; Begin New Day: one link, Continue, `forest.php?op=search`; Continue: 200, The Forest, the
  search form. On rc.3 this order was R11's dead end. The path the brief names (the search link while the fight is
  stored, the new day, its Continue into the kept fight) needs a page with that link, which rc.5 no longer draws; the
  test task 24 wrote for it (`test_the_new_days_continue_after_a_search_link_shows_the_kept_fight_http`, the link
  allowed as rc.4's menu allowed it) passes in the shards below.
- A framed module refusal. Fixture: the Dark Horse Tavern as the forest's event (`specialinc` `module:darkhorse`, as a
  search that finds it sets) and 200 gold. The search form: the tavern's event, "A cluster of trees nearby looks
  familiar…"; Enter the tavern; the old man; "A Game of Stones"; Like Pair; then a bet of 700 typed (more than the
  gold): **400, framed**, "A Game of Stones", "Invalid Stones action. Nothing was changed.", one link, the game
  (`runmodule.php?module=game_stones`), which is also the return page; gold, the wager and the event unchanged. The
  link: 200, the game's bet form.
- One game of stones, counted from the choice: Like Pair (press 1, before the refusal above), the Bet of 10 (press 2,
  its page shows the first pair drawn), then one Continue for each further pair and the last that settles: 8 presses,
  6 pairs drawn, so `d + 2` (`d` = 6). The last page shows the result ("Having defeated you at his game, the old man
  claims your …") and the next game's Like Pair and Unlike Pair.

**f. The error log.** The PHP error log was empty throughout: 0 bytes on rc.4 after steps a and b, and 0 bytes on
rc.5 after steps c to e.

## The upgrades from rc.3 and rc.2

For each: the package's `upload/` installed the same way on an empty MySQL 8.4 database through its own `setup.php`
(41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.3, or 1.0.0-rc.2; `upgrade_steps` 0001 to 0003);
Admin signed in and played through the new day to the village; the error log empty. Then the server stopped, rc.5's
`upload/` was copied into a new document root and served with the same configuration file:

- The home page reads 1.0.0-rc.5, with no "Upgrade Needed"; `setup.php` answers 404.
- `php scripts/upgrade.php --dry-run`, then `--backup-confirmed`, as `nobody`: both "The database is current: 3 of 3
  upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.", exit 0. No step
  runs; 41 tables.
- Admin signed in: 303 to the village, 302 to `badnav.php`, the kept village page; its forest link and the forest's
  village link: Degolburg Square, whose footer reads 1.0.0-rc.5. (The kept page itself is the saved page from before
  the swap, so its footer still showed the old version.) The PHP error log stayed empty.

## The upgrade from rc.1

- The rc.1 package (its zip holds the upload itself) installed the same way on an empty MySQL 8.4 database through
  its own `setup.php`: 41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.1; played to the village. The
  error log was empty.
- Before the upgrade: master 5 is `556EEFBFBD6C697468` (the U+FFFD rc.1 wrote); `upgrade_steps` holds only 0001; the
  nasty word list holds `kyrp` with U+FFFD; no sign-in limit settings.
- The server stopped; rc.5's `upload/` served with the same configuration file. The home page shows "Upgrade Needed".
- `setup.php`'s upgrade: the token screen listed, as its dry run, "step 0002-signin-limits: Add the sign-in limit
  settings with their defaults …" and "step 0003-utf8-seed-text: Write the UTF-8 text 1.1.2 meant into three seed
  values …", "Dry run: nothing was changed." With the token file, the Admin login and password and the backup box:
  "applied and recorded step 0002-signin-limits", "applied and recorded step 0003-utf8-seed-text", "Upgrade complete:
  applied 2 upgrade step(s) and 0 module upgrade(s). The database is current. The token file was deleted, and it cannot
  be used again." The token file was gone.
- After: master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds 0001, 0002 and 0003; `serverlanguages`
  holds "Français" and "Español"; the nasty word list holds "kyrpä*" and no U+FFFD; the sign-in limits are
  `signin_fail_pair` 5, `signin_fail_address` 20 and `signin_fail_account` 50; 41 tables. Signed in as Admin: the
  village (its footer 1.0.0-rc.5 after a link, as above); the Masters Editor, through the Superuser Grotto's link,
  lists "5 Unélith", with no U+FFFD on the page; then the village. The PHP error log stayed empty.

## Found, not changed

- **A yard page kept by rc.4 does not begin the new day at the first sign-in on rc.5** (step d). Task 26's sign-in
  fix keys on the new-day mark in the kept page, which a page saved by rc.4 cannot carry. On a game upgraded from rc.4,
  a player last on the yard who signs in after a rollover sees that yard again; Challenge answers the framed 409,
  "Training unavailable. Nothing was changed.", whose one link begins the new day and returns to the yard. Nothing is
  spent and it happens once, at most once per player: every page rc.5 keeps carries the mark. No dead end, so this
  version commit changes nothing; for Jim to know before his rc.5 server test, since his server upgrades from rc.4.
- **The first page after a sign-in on an upgraded game is the page saved before the swap**, footer included, so it
  can still read the old version until the next link (seen in every upgrade above). This is how the kept page has
  always worked (`badnav.php`); the home page and every page after it read 1.0.0-rc.5.

## Local results

Supporting evidence only; CI is the acceptance. Run on the candidate `1c8be00`, whose tree is this commit's except this
evidence doc's text.

- `test_version.py` (the new pinned value), `test_no_bare_refusals.py`, `test_credit_wording.py`,
  `test_release_package.py`, `test_host_guide.py`, `test_module_author_guide.py` and `test_utf8_text.py`: 44 tests, OK
  on PHP 8.4.27 and PHP 8.5.12. `DocLinkTest` (3 tests, 44 assertions) and `CommandLinkTest` (1 test, 31 assertions):
  OK on both.
- PHPUnit, 524 tests, 152,511 assertions, `failOnSkipped`: OK on PHP 8.5.12 with MySQL 8.4 (2:43) and PHP 8.4.27 with
  MariaDB 11.4 (2:12).
- All five shards on both legs, with the local extras hidden (`PLAYWRIGHT_BROWSERS_PATH` an empty folder,
  `RESURRECTION_REQUIRE_PHP=1`), each on a fresh database installed by `FreshInstallTest`, each leg's five run side by
  side:

  | Shard | Tests run | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
  | --- | --- | --- | --- |
  | 1 | 65 | OK, 1126 s | OK, 1519 s |
  | 2 | 63 | OK, 1222 s | OK, 1608 s |
  | 3 | 63 | OK, 1174 s | OK, 1561 s |
  | 4 | 244 | OK, 1145 s | OK, 1506 s |
  | 5 | 66 | OK, 1170 s | OK, 1566 s |

  501 tests on each leg; 0 failures, 0 errors, 0 skips.

- `scripts/release-upgrade-check.py`, both modes, each on an empty database, on both legs (PHP 8.4.27 / MariaDB 11.4
  and PHP 8.5.12 / MySQL 8.4):
  - Default mode: "Release upgrade check: v1.0.0-rc.4 installed, upgraded to 1c8be00, and the same schema and data as a
    fresh install (41 tables)", with "The database is current: 3 of 3 upgrade steps applied … Nothing was changed."
    `v1.0.0-rc.4` is the highest release tag before this commit.
  - `--release-ref HEAD`: "HEAD installed, upgraded to 1c8be00, and the same schema and data as a fresh install (41
    tables)".
- PHP lint (479 files, 0 failures) on 8.4 and 8.5, both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`: no
  errors), `composer validate --strict`, install and audit (no advisories), the historical verifier (PASS, 417 files,
  11 commits), the project validator (PASS, 1112 tracked files), `scripts/asset-versions.py --check` (exit 0), and `git
  diff --check`: clean. `LICENSE.txt` and `README.txt` are identical to `historical-source-1.1.2`.

## Next

CI acceptance of this commit. Then Jim runs the Release cut workflow from the Actions tab with the four inputs in the
task report (the checklist's sections 3 and 4), and tests rc.5 on his server, upgraded from rc.4. Then `1.0.0` (task
30) and the showcase README (task 31).

## Acceptance and the rc.5 cut (recorded in Phase 6 task 30, 2026-11-20)

- **Accepted** at `0dfdb1d`: Modern core 44513763444 passed all 14 jobs; run wall time 20:33.
  - PHP 8.4 / MariaDB 11.4: `checks` 2:21; `http` 1 to 5: 9:00, 11:23, 10:28, 13:25, 16:35; `result` 0:04.
  - PHP 8.5 / MySQL 8.4: `checks` 2:49; `http` 1 to 5: 11:39, 20:25, 12:05, 11:18, 11:09; `result` 0:02.
  - Slowest: 20:25, PHP 8.5 `http (2)`, under the 21-minute target but over task 28's largest projection (16:26). The
    same 63 tests as task 28's run took about 40% longer on that runner (the shard's Python time 845.9 s at `4aaa47a`,
    1174.8 s at `0dfdb1d`); this commit changed only `test_version.py` among the tests.
    `test_dragon_specialty_authority_matrix` alone took 329.4 s (238.8 s at `4aaa47a`). Task 30 splits it.
  - On both legs: "Release upgrade check: v1.0.0-rc.4 installed, upgraded to 0dfdb1d, and the same schema and data as a
    fresh install (41 tables)", and the same with HEAD installed.
- Baseline integrity 44513763427 passed.
- **The cut**, 2026-11-19, on Jim's "start it":
  - Release cut run 44597371544 (`workflow_dispatch`, on `main` at `0dfdb1d`): success.
  - Tag `v1.0.0-rc.5`: tag object `5498b1c4efff4f3dc1f565d90197181f4cf8f598`, annotated, by `github-actions[bot]`, on
    `0dfdb1d76c3ab4a0e00db695b32f66108b70c332`. Its message is 910 bytes, SHA-256
    `df9dbfacddcfeeebbc01cdf2237cac5036c6ab80f11c74586d41c20063317f0b`, byte for byte the block in
    `docs/RC-CUT-CHECKLIST.md`, section 3 (`cmp` equal).
  - The pre-release `v1.0.0-rc.5`: not a draft, not latest (the repository has no latest release), with
    `legend-of-the-green-dragon-resurrection-1.0.0-rc.5.zip`, 3,919,931 bytes, SHA-256
    `2d8e135e1c4bff0e14b88e32f9fd8dc4cdcf4fc83e69e022e5aca07c4b55a800` (772 files), equal to this task's two builds,
    and its `.sha256`, which names the same hash.
  - `historical-source-1.1.2` (tag object `51cab4f…4d`) and `v1.0.0-rc.1` to `v1.0.0-rc.4` (tag objects `522be1c…`,
    `147ad5a…`, `dec02e9…`, `f3de198…`) are unchanged, and so are their pre-releases' zip digests.
- Checked in task 30 through the REST API: the three runs' status, conclusion, event and head commit, each Modern core
  job's times from its start and end, the two upgrade check lines in both `checks` job logs, the tag refs and the tag
  object, and the releases and their assets; the zip and its `.sha256` downloaded through the REST API (the asset
  endpoint), not `gh release download`, and hashed locally.
- **Jim's rc.5 server test** began on 2026-11-19, upgraded from rc.4. It is in progress and solid so far.
- **Renumbered** by Jim's decision of 2026-11-20 (recorded in task 30): task 30 splits the Dragon specialty matrix test,
  the `1.0.0` version commit is task 31, and the showcase README task 32.
