# Phase 6 task 23: the `1.0.0-rc.3` version commit (no tag)

2026-11-17 (UTC; 2026-11-16 in Jim's time zone). Made from `088b43cba5e99421bef580c2aa8949e935d88e4c`, which matched
the remote `main`, by `docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only: no tag, no
GitHub release, no package published or attached anywhere, and the release cut workflow was not run. The tag and the
pre-release follow when Jim runs the Release cut workflow, after CI accepts this commit (the checklist's sections 2 to
5). The showcase README moves to task 24.

## Recorded first

- **Task 22 accepted** at `088b43c`: Modern core 43971175438 passed all 14 jobs. The PHP 8.4 `http` jobs took 9:29,
  11:51, 9:18, 15:15 and 14:50; the PHP 8.5 ones 17:44, 16:59, 17:51, 17:26 and 14:19. On both legs the release
  upgrade check installed `v1.0.0-rc.2` and upgraded it to `088b43c`, with the same schema and data as a fresh install
  (41 tables). Baseline integrity 43971175428 passed. Appended to `PHASE6-TASK22-20261116.md` ("Acceptance"); task 22
  and R10 carry it in `ROADMAP.md`; the checklist's section 5 box for rc.2 is ticked with it.
- **Jim's go**, 2026-11-16: "cut 1.0.0-rc.3, same as rc.2." Recorded in `ROADMAP.md` (Jim's decisions of 2026-11-16,
  decision 2) and in the checklist's section 0. The tag and the GitHub pre-release come from the Release cut workflow,
  which Jim runs after this commit is accepted, as for rc.2.
- **Section 0's other two boxes**, checked and ticked for rc.3:
  - Every task before the cut is accepted: task 22 at `088b43c`, above.
  - The release notes list every open item a host or player can hit, checked against `ROADMAP.md` at `088b43c`: its
    one open box is the Dark Horse Tavern's many Continue clicks (after `1.0.0`), a known issue in the notes; R10 is
    done and is the notes' change; the public hosting gates stay covered by "Not certified for public hosting".

## The search link after a rollover (Jim's note, and one change)

Jim's rc.2 server, 2026-11-16: `GET forest.php?op=search&c=4-005017`, after a game-day rollover, answered "Forest
combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains
invalid." Going to the village started the new day and cleared it. The brief asked to confirm that a test covers the
GET path as well as the POST.

- **What a test covered.** Task 22's `test_a_forest_search_after_the_day_rolls_over_is_refused_before_it_is_spent_http`
  covers the POST after the rollover (the framed 409) and every search link's GET after it (302 to `newday.php`), for
  an ordinary player.
- **What Jim's GET needs.** On rc.2 the search link's GET showed the form for an ordinary player after a rollover (task
  22's reproduction, (a)). A GET of `forest.php?op=search` answers that message only when the forest state check,
  `resurrection_forest_state()`, refuses the player: dead, no hitpoints, or an encounter already active (the GET branch
  returns before the action and the later new-day check). At `088b43c` task 22's rollover check came after that state
  check, so it never ran for those players.
- **Checked over HTTP** at `088b43c` (PHP 8.5, MySQL 8.4; the forest page's own search link, the player's
  `lastnewday` moved back one game day): ordinary player 302 to `newday.php`; dead, 0 HP and mid-fight players each 409
  with exactly Jim's text.
- **Jim's choice** (2026-11-16, the decision card): fix it in this commit.
- **The change** (`lib/forest_combat.php`): the rollover check moves before the state check, as 1.1.2's
  `forest.php` ran `checkday()` first for `op=search` (line 56 at `historical-source-1.1.2`). The check itself is
  unchanged. For a dead, 0 HP or mid-fight player after a rollover, the link now begins the new day (302 to
  `newday.php`), and a search posted after the rollover gets task 22's framed 409 instead of the plain one. Without a
  rollover, those states answer as before; unfinished onboarding keeps its refusal as before. No stored row changes,
  so no upgrade step.
- **The test**, `test_a_search_link_after_the_rollover_begins_the_new_day_for_a_dead_hurt_or_fighting_player_http`
  (`tests/tooling/test_web_application.py`, shard 2), on the plain player of task 22's fixture, `forestchance` 0 and
  put back: for each of the three states, the forest's own search link after the rollover answers 302 with
  `newday.php` the only allowed nav and the player's alive, hitpoints, turns, gold and encounter unchanged; then the
  new day is played through its own page and form; the player is alive with full hitpoints and today's
  `lastnewday`; and `forest.php` answers 200 without the refusal.
- **Fail first**, the new test on the tree with `lib/forest_combat.php` at `088b43c` (PHP 8.5, MySQL 8.4):

  ```
  FAIL: ... [dead]
  AssertionError: 302 != 409 : Forest combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains invalid.
  FAIL: ... [no hitpoints]
  AssertionError: 302 != 409 : Forest combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains invalid.
  FAIL: ... [in a fight]
  AssertionError: 302 != 409 : Forest combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains invalid.
  Ran 1 test ... FAILED (failures=3)
  ```

  With the change: OK. Task 22's three tests and the onboarding test pass unchanged (shards below).
- Recorded in `docs/CHANGES-FROM-1.1.2.md` (task 23's row), the rc.3 notes (R10's last point), `ROADMAP.md` (R10 and
  task 23) and `PHASE6-TASK22-20261116.md` ("Acceptance"). It changes no security control, hook or helper the module
  guide names, so `SECURITY-STATUS.md` and `MODULE-AUTHOR-GUIDE.md` are unchanged.

This is the one change of substance beside the version: section 1 of the checklist asks for nothing else of substance,
and this one is in the commit on Jim's choice.

## The checklist (`docs/RC-CUT-CHECKLIST.md`)

Brought up to date for rc.3 with rc.1's and rc.2's records kept as history: a paragraph at the top on the third cut; in
section 0 the rc.3 boxes, ticked; in sections 1, 2, 4 and 5 an rc.3 line beside the earlier records (open boxes for
CI, the tag, the package and the next commit's upgrade check); section 3 written for `v1.0.0-rc.3` with rc.3's tag
message as its first fenced block and the open box for its tag, and rc.2's message and record below it under
"History", unchanged. rc.2's block there is byte for byte the message of the tag `v1.0.0-rc.2` (`git cat-file -p
v1.0.0-rc.2`, 861 bytes, `cmp` equal). Section 5's rc.2 box is ticked with task 22's run.

## The tag message (checklist section 3)

The release cut workflow takes the first fenced block after `## 3. The tag` and adds one newline. rc.3's block, in
rc.2's shape:

```
Legend of the Green Dragon - Resurrection 1.0.0-rc.3

The third test build of Resurrection 1.0.0: a candidate for hosts and players to try. It is not the release, it has
had no independent security review, and it is not certified for public game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs, or an upgrade from 1.0.0-rc.2 (no step) or 1.0.0-rc.1 (steps 0002 and 0003); no 1.1.2 database is
upgraded. Known issues, among them mail only through PHP's mail(), best effort, mail links in the web server's access
log, abuse controls beyond sign-in throttling left to the operator, and 1.1.2 behaviors kept as recorded:
docs/RELEASE-NOTES-1.0.0-rc.3.md. Hosting: docs/HOST-GUIDE.md.
```

Taken out with the workflow's own Python, the file is 884 bytes with SHA-256
`8bd2354597b07bbf84e25997cb64aede8183cb84b3e5e2abad3140fee8e027de`.

Each line, checked at this commit:

- "1.0.0-rc.3": `src/Version.php`. "The third test build": rc.1 and rc.2 were the first two (`v1.0.0-rc.1`,
  `v1.0.0-rc.2`).
- "not the release ... no independent security review ... not certified for public game hosting": the release notes'
  last known issues, `SECURITY.md`, `README.md`, and `project.json` (`public_game_hosting_approved: false`).
- The credit paragraph: unchanged from rc.1 and rc.2; `LICENSE.txt` and `NOTICE` are at those names in the package.
- "an upgrade from 1.0.0-rc.2 (no step)": `upgrades/` holds 0001 to 0003, all in rc.2; the upgrade run below ("3 of 3
  upgrade steps applied ... Nothing was changed."). "or 1.0.0-rc.1 (steps 0002 and 0003)": rc.1 has only 0001; the
  setup.php run below applied 0002 and 0003. Both are in the notes' "Upgrading".
- "no 1.1.2 database is upgraded": `docs/VERSIONING.md` and the host guide.
- Each known issue named is in the rc.3 notes' "Known issues": mail only through `mail()`, best effort; the token in
  the access log; "Abuse controls beyond sign-in throttling are the operator's"; the 1.1.2 behaviors kept.
- `docs/RELEASE-NOTES-1.0.0-rc.3.md` and `docs/HOST-GUIDE.md` exist at those paths.

## The version commit (checklist section 1)

- `src/Version.php`: `CURRENT` is `'1.0.0-rc.3'`.
- `project.json`: `version` `1.0.0-rc.3`, `phase` `release-candidate` (unchanged).
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.3`"; the footer example shows the same; the tag paragraph says the
  rc.2 tag is on its version commit and the rc.3 tag goes on this one once CI accepts it, after which `main` keeps
  `1.0.0-rc.3` until the next cut.
- `tests/tooling/test_version.py`: the pinned value is `1.0.0-rc.3`. With the new GET test above, the only test changes.
- `docs/RELEASE-NOTES-1.0.0-rc.3.md`: the draft paragraph is gone and the notes are dated 2026-11-16; R10 lists the
  search link's plain refusal Jim saw and this task's change; a new "Upgrading" section points to the host guide's
  section 9, "Upgrade", with rc.2 (no step) and rc.1 (0002 and 0003); the known issues are rc.2's, checked against
  `ROADMAP.md` (section 0 above).
- `README.md` and `SECURITY.md`: `main` is `1.0.0-rc.3`, the third test build, not certified for public hosting; their
  links go to the rc.3 notes (README keeps links to rc.2's and rc.1's).
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md`: the version commit recorded.
- The doc links follow `Version::CURRENT`, so the pages' links to the host guide go to
  `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v1.0.0-rc.3/docs/HOST-GUIDE.md#…`. They
  resolve once the tag exists. Every anchor the code uses (`5-install`, `53-if-an-install-fails`, `9-upgrade`,
  `10-backups-and-restore`, `11-mail`) is a heading in `docs/HOST-GUIDE.md` at this commit; `DocLinkTest` and
  `CommandLinkTest` pass. Every Markdown link in the changed documents names a file that exists at this commit.

## The release cut workflow, dry run

Not changed: it handles an rc.3 cut as it stands. Its inputs are typed by hand and nothing in its steps is specific to
an earlier cut. Its defaults are still rc.1's values; a run left at them stops before writing anything, as task 21
recorded.

As in tasks 15 and 21, its Preflight, message, Tag and package steps ran in the session against a throwaway bare copy
of the repository standing in for `origin` (with `historical-source-1.1.2`, `v1.0.0-rc.1` and `v1.0.0-rc.2`), with
`main` there set to a candidate commit, `c75b0f4`, whose tree is this commit's except this evidence doc's final text
(the package and the tag message are made from files this doc is not among; see "The package" below). The four steps'
scripts were taken from `.github/workflows/release-cut.yml` by parsing it with PyYAML, not copied by hand, and run in a
fresh clone of the stand-in checked out at the candidate, with the inputs `1.0.0-rc.3`, the commit, `8bd23545…27de`
and `6fe8b132…3569`:

- Preflight: passed (the historical verifier: PASS, 417 files; the validator: PASS).
- The message: section 3's first block, the same 884 bytes as above; `sha256sum -c` OK.
- The tag: `v1.0.0-rc.3`, annotated, on the candidate, its message equal to the file byte for byte, pushed as the one
  ref to the stand-in, whose tag and peeled commit then matched.
- The package: checked out at the tag on a clean tree, built twice, 772 files and 3,868,683 bytes each,
  `6fe8b132…3569` both times, the two equal under `cmp` and the hash equal to the input.
- A rerun of the four steps with the tag already on the stand-in found it, checked its type, commit and message, and
  left it as it was ("v1.0.0-rc.3 already exists on c75b0f4… with the exact message; left as it is."), then built the
  same package again.
- The refusals: a wrong message SHA-256 stops the message step (`FAILED`); version `1.0.0-rc.4`, or `1.0.0-rc.2` with
  this commit, stops Preflight ("src/Version.php is not …"); a wrong package SHA-256 stops the package step.

The publishing steps use `gh` against GitHub and were not run. No tag was made in the repository's own clone (its tags
are still `historical-source-1.1.2`, `v1.0.0-rc.1` and `v1.0.0-rc.2`), and nothing was pushed anywhere but the
stand-in.

## The earlier packages

Downloaded from their GitHub pre-releases into their own empty folders outside the tree. `gh release download` was
refused (it uses GraphQL, which these sessions cannot reach: 403), so the same files came through the REST API instead
(`gh api …/releases/tags/<tag>`, then each asset with `Accept: application/octet-stream`):

| | Zip | Size | SHA-256 | Release's digest and `.sha256` |
| --- | --- | --- | --- | --- |
| `v1.0.0-rc.1` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 3,626,284 bytes | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` | the same |
| `v1.0.0-rc.2` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.2.zip` | 3,867,683 bytes | `133625b327d21878c173ac856e5b70b306b69735ca95151e8923cae3030ee439` | the same |

Both are the values the brief gives.

## The package, built twice

`python3 scripts/build-release-package.py --out <folder>`, run twice from the tree into two empty folders outside it:

| | Folder 1 | Folder 2 |
| --- | --- | --- |
| File | `legend-of-the-green-dragon-resurrection-1.0.0-rc.3.zip` | the same |
| Entries | 772 files | 772 files |
| Size | 3,868,683 bytes | 3,868,683 bytes |
| SHA-256 | `6fe8b1323f140bdc2b2ef3ce45d7ee0a917f5a40689e7f462b210570d5603569` | the same |

`cmp` finds the two identical, and the `.zip.sha256` files are equal. The zip has one top folder,
`legend-of-the-green-dragon-resurrection-1.0.0-rc.3/`, with `upload/`, `INSTALL.txt`, `HOST-GUIDE.md`,
`RELEASE-NOTES-1.0.0-rc.3.md`, `THIRD-PARTY-LICENSES.md`, `hosting/`, `LICENSE.txt`, `NOTICE` and `README.txt`. The
build reads the files Git lists with fixed entry times, and the only document it takes from `docs/` beyond the host
guide and the third-party licenses is the release notes, so this evidence doc and the checklist do not change it. It
was built again from the commit itself before the push, with the same SHA-256 (the task report).

## The fresh install

The zip unpacked into an empty folder, and only `upload/` copied into an empty document root, served by PHP 8.5.12's
built-in server on `127.0.0.1` as `nobody`, with `LOGD_DBCONNECT` pointing at an empty private folder outside it, and an
empty MySQL 8.4 database. Mail went nowhere (`sendmail_path=/bin/false`).

- `setup.php`: the 7 checks were ok or info (plain HTTP allowed only because the request came from the machine
  itself). The token screen gave the file's lines; the token file was written beside the configuration path with them,
  and the install form took the database, the first administrator and the recommended modules.
- Result: "The game is installed. The configuration file was written to …/private/dbconnect.php (0600). 41 tables
  were created; 24 modules installed, 23 active. The first administrator is Admin. The token file was deleted, and it
  cannot be used again." The last screen's maintenance choice: cron. The token file was gone; `setup.php` then answered
  404.
- The home page reads "Version: Resurrection 1.0.0-rc.3, based on LoGD 1.1.2 Dragonprime Edition".
- Played to the village: sign in, the new day (race Human, specialty Thieving Skills, then "It is a new day!"), then
  the village, "Degolburg Square".
- The database: 41 tables; master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds 0001, 0002 and 0003. The
  PHP error log stayed empty.

## The upgrade from rc.2

- The rc.2 package's `upload/` installed the same way on an empty MySQL 8.4 database through its own `setup.php`: 41
  tables, 24 modules, 23 active; the home page reads 1.0.0-rc.2; played to the village. `upgrade_steps` holds 0001,
  0002 and 0003; the sign-in limits are 5, 20 and 50.
- The server stopped; rc.3's `upload/` copied into a new document root and served with the same configuration file.
  The home page reads 1.0.0-rc.3, with no "Upgrade Needed"; `setup.php` answers 404, since the database is current.
- `php scripts/upgrade.php --dry-run`, then `--backup-confirmed`, from the new folder as `nobody`: both "The database
  is current: 3 of 3 upgrade steps applied, and every installed bundled module is at its version. Nothing was
  changed.", exit 0. No step runs.
- Jim's path, as Admin (an administrator, as Jim played), every page reached by its own links:
  - Sign-in, the village, the forest.
  - The Dark Horse Tavern, forced as the forest event as in task 22 (`specialinc` `module:darkhorse`): the forest's
    link shows "Something Special! Dark Horse Tavern … A cluster of trees nearby looks familiar…"; the tavern; "Leave"
    back to the forest (`specialinc` empty).
  - A game-day rollover as task 22's tests make one: the player's `lastnewday` moved back one game day.
  - "Look for Something to Kill" (`forest.php?op=search&c=…`): 302 to `newday.php`. The new day ("It is a new day!"),
    then its link back to the search page.
  - The search form ("Look for Something to Kill"), pressed (with `forestchance` 0 for that one request, so it is a
    fight and not an event): 200, The Forest, with the fight's Fight and Run forms and an enemy stored.
  - The PHP error log stayed empty.

## The upgrade from rc.1

- The rc.1 package (its zip holds the upload itself) installed the same way on an empty MySQL 8.4 database through its
  own `setup.php`: 41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.1; played to the village.
- Before the upgrade: master 5 is `556EEFBFBD6C697468` (the U+FFFD rc.1 wrote); `upgrade_steps` holds only 0001; the
  nasty word list holds `kyrp` with U+FFFD; no sign-in limit settings.
- The server stopped; rc.3's `upload/` served with the same configuration file. The home page shows "Upgrade Needed".
- `setup.php`'s upgrade: the dry run listed 0002 and 0003 as pending. With a new token file, the Admin login and
  password and the backup box: "applied and recorded step 0002-signin-limits", "applied and recorded step
  0003-utf8-seed-text", "Upgrade complete: applied 2 upgrade step(s) and 0 module upgrade(s). The database is current.
  The token file was deleted, and it cannot be used again."
- After: master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds 0001, 0002 and 0003; `serverlanguages`
  holds "Français" and "Español" (UTF-8, no U+FFFD); the nasty word list holds "kyrpä*" and no U+FFFD; the sign-in
  limits are 5, 20 and 50; 41 tables. The home page reads 1.0.0-rc.3. Signed in as Admin, the Masters Editor lists
  "Unélith" (no U+FFFD on the page), and the village opens. The PHP error log stayed empty.

## Local results

Supporting evidence only; CI is the acceptance. Run on this commit's tree.

- `test_version.py` (the new pinned value), `test_credit_wording.py`, `test_release_package.py`, `test_host_guide.py`
  and `test_utf8_text.py`: 30 tests, OK. `DocLinkTest` and `CommandLinkTest`: 4 tests, 75 assertions, OK.
- PHPUnit, 524 tests, 152,511 assertions, `failOnSkipped`: OK on PHP 8.4.27 with MariaDB 11.4 (1:10) and PHP 8.5.12
  with MySQL 8.4 (1:34).
- All five shards on both legs, with the local extras hidden (`PLAYWRIGHT_BROWSERS_PATH` an empty folder,
  `RESURRECTION_REQUIRE_PHP=1`), each on a fresh database installed by `FreshInstallTest`, each leg's five run side by
  side:

  | Shard | Tests run | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
  | --- | --- | --- | --- |
  | 1 | 61 | 758 s | 803 s |
  | 2 (the new test) | 59 | 768 s | 833 s |
  | 3 | 232 | 754 s | 808 s |
  | 4 | 60 | 653 s | 797 s |
  | 5 | 60 | 756 s | 815 s |

  Every run: 0 failures, 0 errors, 0 skipped. Task 22's three tests and
  `test_ordinary_forest_search_escape_and_event_handoff` passed on both legs. One earlier PHP 8.4 shard 4 attempt is
  not counted: a first launch of its runner could not bind its database port, kept waiting, and then replaced the tree
  under the second launch (23 tests ran, "Fixture database operation failed" and a missing `dbconnect.php`); shard 4
  was run again alone on a fresh database and tree, above.

- `scripts/release-upgrade-check.py`, both modes, each on an empty database, on both legs:

  - Default mode, on both legs (PHP 8.4.27 / MariaDB 11.4 and PHP 8.5.12 / MySQL 8.4): installed the published
    `v1.0.0-rc.2`, the highest release tag before this commit, and upgraded it to this commit: "the same schema and
    data as a fresh install (41 tables)", with "The database is current: 3 of 3 upgrade steps applied … Nothing was
    changed."
  - `--release-ref HEAD`, on both legs: installed this commit and upgraded it: the same result, 41 tables.
  - Run on the local candidate commit `bce378a`, whose tree is this commit's except this section's text.

- PHP lint (479 files, 0 failures) on 8.4 and 8.5, both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`: no
  errors), `composer validate --strict`, install and audit (no advisories), the historical verifier (PASS, 417 files,
  11 commits), the project validator (PASS), `scripts/asset-versions.py --check`, and `git diff --check`: clean.
  `LICENSE.txt` and `README.txt` are identical to `historical-source-1.1.2`.

## Next

CI acceptance of this commit. Then Jim runs the Release cut workflow from the Actions tab with the four inputs in the
task report (the checklist's sections 3 and 4), and tests rc.3 on his server, including the rc.2 upgrade. Then
`1.0.0`, and the showcase README (task 24).

## Acceptance and the rc.3 cut (recorded in Phase 6 task 24, 2026-11-17)

Given in Jim's task 24 brief and checked again in task 24 against the repository's API and the tag itself:

- **Task 23 accepted** at `0632351`: Modern core 43999468955, SUCCESS, all 14 jobs. PHP 8.4: `checks` 2:23; `http`
  13:28, 14:16, 9:46, 16:17, 16:14. PHP 8.5: `checks` 2:37; `http` 12:41, 12:03, 16:53, 10:58, 10:44 (slowest 16:53,
  PHP 8.5 `http (3)`). On both legs the release upgrade check installed `v1.0.0-rc.2` and upgraded it to `0632351`
  ("the same schema and data as a fresh install (41 tables)"), and `--release-ref HEAD` matched too. Baseline integrity
  43999469169, SUCCESS. Each job's times were taken again from the run's job list, and the two upgrade check lines
  from both `checks` job logs.
- **The cut:** release cut run 44055317395, SUCCESS (`workflow_dispatch` on `0632351`, 2026-11-17 09:24 UTC).
  - Tag `v1.0.0-rc.3`: annotated, tag object `dec02e9ff601fb445b577d870602aaae6ee91a4a`, by `github-actions[bot]`, on
    `0632351b9ca410e09971fe79b93992d66b8a658b`. Its message is 884 bytes, SHA-256
    `8bd2354597b07bbf84e25997cb64aede8183cb84b3e5e2abad3140fee8e027de`, byte for byte the block in
    `docs/RC-CUT-CHECKLIST.md`, section 3.
  - The pre-release, https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.3:
    a pre-release, not a draft and not latest (the repository has no latest release). Its
    `legend-of-the-green-dragon-resurrection-1.0.0-rc.3.zip` is 3,868,683 bytes, digest
    `sha256:6fe8b1323f140bdc2b2ef3ce45d7ee0a917f5a40689e7f462b210570d5603569`, the `package_sha256` this task's build
    gave; downloaded back, the zip has that SHA-256 and its `.sha256` file says the same.
  - `v1.0.0-rc.1` (`522be1c`), `v1.0.0-rc.2` (`147ad5a`) and `historical-source-1.1.2` (`51cab4f`) are unchanged.
- Jim's rc.3 server test then found R11 (`docs/PHASE6-TASK24-20261117.md`).
