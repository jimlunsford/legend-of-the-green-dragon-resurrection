# Phase 6 task 21: the `1.0.0-rc.2` version commit (no tag)

2026-11-16. Made from `27c3f94c5e1c64cea3e50f2cf67aac66a15c6bdc`, which matched the remote `main`, by
`docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only: no tag, no GitHub release, no
package published or attached anywhere, and the release cut workflow was not run. The tag and the pre-release follow
when Jim runs the Release cut workflow, after CI accepts this commit (the checklist's sections 2 to 5).

## Recorded first

- **Task 20 accepted** at `27c3f94`: Modern core 43789198080 passed all 14 jobs. The PHP 8.4 `http` jobs took 15:36,
  14:11, 10:06, 9:51 and 9:43; the PHP 8.5 ones 11:24, 16:17, 16:52, 17:04 and 13:20. Baseline integrity 43789198083
  passed. Appended to `PHASE6-TASK20-20261116.md` ("Acceptance"). Task 20 and R6 to R9 are ticked in `ROADMAP.md`.
- **Jim's go**, 2026-11-16: "cut 1.0.0-rc.2, same as rc.1." Recorded in `ROADMAP.md` (Jim's decisions of 2026-11-16)
  and in the checklist's section 0. The tag and the GitHub pre-release come from the Release cut workflow, which Jim
  runs after this commit is accepted, as for rc.1.
- **Section 0's other two boxes**, checked and ticked for rc.2:
  - Every task before the cut is accepted: Phase 6 tasks 16 to 20 are ticked in `ROADMAP.md` with their accepted runs,
    the last being task 20 at `27c3f94`.
  - The release notes list every open item a host or player can hit, checked against `ROADMAP.md` at `27c3f94`: R1 to
    R9 are done and listed as changes; the four known-issue fixes (sign-in throttling, N11, N1, N3/N8) are done and
    listed; the one item from the rc.1 server test that stays open, the Dark Horse Tavern's many Continue clicks
    (a post-`1.0.0` item in `ROADMAP.md`), is now a known issue; the public hosting gates stay covered by "Not
    certified for public hosting".

## The checklist (`docs/RC-CUT-CHECKLIST.md`)

Brought up to date for rc.2 with rc.1's record kept as history: a short paragraph at the top on the second cut; in
section 0 the rc.2 boxes, ticked; in sections 1, 2, 4 and 5 an rc.2 line beside rc.1's record; section 3 written for
`v<version>` with rc.2's tag message as its first fenced block, the workflow's open box for the rc.2 tag, and rc.1's
message and record below it under "History", unchanged.

## The tag message (checklist section 3)

The release cut workflow takes the first fenced block after `## 3. The tag` and adds one newline. rc.2's block, in
rc.1's shape:

```
Legend of the Green Dragon - Resurrection 1.0.0-rc.2

The second test build of Resurrection 1.0.0: a candidate for hosts and players to try. It is not the release, it has
had no independent security review, and it is not certified for public game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs, or an upgrade from 1.0.0-rc.1 (steps 0002 and 0003); no 1.1.2 database is upgraded. Known issues,
among them mail only through PHP's mail(), best effort, mail links in the web server's access log, abuse controls
beyond sign-in throttling left to the operator, and 1.1.2 behaviors kept as recorded:
docs/RELEASE-NOTES-1.0.0-rc.2.md. Hosting: docs/HOST-GUIDE.md.
```

Taken out with the workflow's own Python, the file is 861 bytes with SHA-256
`4b5b7465ffa300bc328d1e2ce05287b35a34ea8412572a555b4b66fc9a3ab2ed`. rc.1's block under "History" is byte for byte the
message of the tag `v1.0.0-rc.1` (`git cat-file -p v1.0.0-rc.1`, SHA-256 `41bf462a…090bd`).

Each line, checked at this commit:

- "1.0.0-rc.2": `src/Version.php`. "The second test build": rc.1 was the first (`v1.0.0-rc.1`, task 15).
- "not the release ... no independent security review ... not certified for public game hosting": the release notes'
  last known issues, `SECURITY.md`, `README.md`, and `project.json` (`public_game_hosting_approved: false`).
- The credit paragraph: unchanged from rc.1; `LICENSE.txt` and `NOTICE` exist at those names in the package.
- "an upgrade from 1.0.0-rc.1 (steps 0002 and 0003)": `upgrades/0002-signin-limits.php` and
  `upgrades/0003-utf8-seed-text.php`, the release notes' "Upgrading from rc.1", and the upgrade run below.
- "no 1.1.2 database is upgraded": `docs/VERSIONING.md` and the host guide.
- Each known issue named is in the release notes' "Known issues" at this commit: mail only through `mail()`, best
  effort; the token in the access log; "Abuse controls beyond sign-in throttling are the operator's" (rc.1's "no abuse
  controls yet" is no longer true: task 18 added sign-in throttling); the 1.1.2 behaviors kept.
- `docs/RELEASE-NOTES-1.0.0-rc.2.md` and `docs/HOST-GUIDE.md` exist at those paths.

## The version commit (checklist section 1)

- `src/Version.php`: `CURRENT` is `'1.0.0-rc.2'`.
- `project.json`: `version` `1.0.0-rc.2`, `phase` `release-candidate` (unchanged).
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.2`"; the footer example shows the same; the tag paragraph says the
  rc.1 tag is on its version commit and the rc.2 tag goes on this one once CI accepts it, after which `main` keeps
  `1.0.0-rc.2` until the next cut.
- `tests/tooling/test_version.py`: the pinned value is `1.0.0-rc.2`. That is the only test change.
- `docs/RELEASE-NOTES-1.0.0-rc.2.md`: the draft paragraph is gone and the notes are dated 2026-11-16; the changes
  (R1 to R9, the four known-issue fixes, steps 0002 and 0003) and the known issues checked against `ROADMAP.md`; a new
  "Upgrading from rc.1" section points to the host guide's section 9, "Upgrade", and names the two steps.
- `README.md` and `SECURITY.md`: `main` is `1.0.0-rc.2`, the second test build, not certified for public hosting;
  their links go to the rc.2 notes (README keeps a link to rc.1's).
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md`: the version commit recorded.
- The doc links follow `Version::CURRENT`, so the pages' links to the host guide now go to
  `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v1.0.0-rc.2/docs/HOST-GUIDE.md#…`. They
  resolve once the tag exists. Every anchor they use (`5-install`, `53-if-an-install-fails`, `9-upgrade`,
  `10-backups-and-restore`, `11-mail`) is a heading in `docs/HOST-GUIDE.md` at this commit; `DocLinkTest` and
  `CommandLinkTest` pass.

## The release cut workflow, dry run

Not changed: it handles an rc.2 cut as it stands. Its inputs are typed by hand and nothing in its steps is specific to
rc.1. Its defaults are rc.1's values: a run left at all of them would find rc.1's tag unchanged and stop at the
publish step ("A release for v1.0.0-rc.1 already exists; not touched"), and rc.1's version typed with this commit stops
in Preflight (`src/Version.php` is not `1.0.0-rc.1`). Either way nothing is written. GitHub's "latest release" stays
none while every release is a pre-release, so its last check holds.

As in task 15, its Preflight, message, Tag and package steps ran in the session against a throwaway bare copy of the
repository standing in for `origin`, with `main` there set to this commit and the historical tags present. The four
steps' scripts were taken from `.github/workflows/release-cut.yml` at this commit by parsing the file, not copied by
hand, and run with the inputs `1.0.0-rc.2`, the commit, `4b5b7465…b2ed` and `133625b3…e439`:

- Preflight: passed (the historical verifier: 417 files, 11 commits, tag object `51cab4f…`; the validator: PASS).
- The message: section 3's first block, the same 861 bytes as above; `sha256sum -c` OK.
- The tag: `v1.0.0-rc.2`, annotated, on this commit, its message equal to the file byte for byte, pushed as the one
  ref to the stand-in `origin`, whose tag and peeled commit then matched.
- The package: checked out at the tag on a clean tree, built twice, 772 files and 3,867,683 bytes each,
  `133625b3…e439` both times, the two equal under `cmp` and the hash equal to the input.
- A rerun of the four steps with the tag already on the stand-in found it, checked its type, commit and message, and
  left it as it was ("already exists ... left as it is"), then built the same package again.
- The refusals: a wrong message SHA-256 stops the message step; version `1.0.0-rc.3`, or `1.0.0-rc.1` with this
  commit, stops Preflight.

The publishing steps use `gh` against GitHub and were not run. No tag was made in the repository's own clone, and
nothing was pushed anywhere but the stand-in.

## The package, built twice

`python3 scripts/build-release-package.py --out <folder>`, run twice from the tree into two empty folders outside it:

| | Folder 1 | Folder 2 |
| --- | --- | --- |
| File | `legend-of-the-green-dragon-resurrection-1.0.0-rc.2.zip` | the same |
| Entries | 772 files | 772 files |
| Size | 3,867,683 bytes | 3,867,683 bytes |
| SHA-256 | `133625b327d21878c173ac856e5b70b306b69735ca95151e8923cae3030ee439` | the same |

`cmp` finds the two identical. The zip has one top folder, `legend-of-the-green-dragon-resurrection-1.0.0-rc.2/`, with
`upload/` (762 files), `INSTALL.txt`, `HOST-GUIDE.md`, `RELEASE-NOTES-1.0.0-rc.2.md`, `THIRD-PARTY-LICENSES.md`,
`hosting/`, `LICENSE.txt`, `NOTICE` and `README.txt`.

## The fresh install

The zip unpacked into an empty folder, and only `upload/` copied into an empty document root, served by PHP 8.5.12's
built-in server on `127.0.0.1` with `LOGD_DBCONNECT` pointing at an empty private folder outside it, and an empty
MySQL 8.4 database. Mail went nowhere (`sendmail_path=/bin/false`).

- `setup.php`: every check passed (plain HTTP allowed only because the request came from the machine itself). The
  token screen gave a value; the token file was written beside the configuration path with it, and the install form
  took the database, the first administrator and the recommended modules.
- Result: "The game is installed. The configuration file was written to …/dbconnect.php (0600). 41 tables were
  created; 24 modules installed, 23 active. The first administrator is Admin. The token file was deleted." The last
  screen's maintenance choice: cron.
- The home page reads "Version: Resurrection 1.0.0-rc.2, based on LoGD 1.1.2 Dragonprime Edition".
- Played to the village: sign in, the new day (race Human, specialty Thieving Skills), "Begin New Day", then the
  village, "Degolburg Square".
- The database: master 5 is `556EC3A96C697468` ("Unélith"), `upgrade_steps` holds 0001, 0002 and 0003; the PHP error
  log stayed empty.

## The upgrade from rc.1

- The rc.1 package, downloaded from its GitHub pre-release: 3,626,284 bytes, SHA-256 `f8b0a1d4…7857`, the recorded
  value. Its `upload/` copied into an empty document root and installed through its own `setup.php` on an empty MySQL
  8.4 database, the same way: 41 tables, 24 modules, 23 active; the home page reads 1.0.0-rc.1; played to the village.
- Before the upgrade: master 5 is `556EEFBFBD6C697468` (the U+FFFD rc.1 wrote); `upgrade_steps` holds only 0001;
  `serverlanguages` holds U+FFFD; no sign-in limit settings.
- The server stopped; rc.2's `upload/` copied into a new document root and served with the same configuration file.
  The home page shows "Upgrade Needed".
- `setup.php`'s upgrade: the dry run listed 0002 and 0003 as pending. With a new token file, the Admin login and
  password and the backup box: "applied and recorded step 0002-signin-limits", "applied and recorded step
  0003-utf8-seed-text", "Upgrade complete: applied 2 upgrade step(s) and 0 module upgrade(s). The database is current.
  The token file was deleted."
- After: master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds 0001, 0002 and 0003; `serverlanguages`
  holds "Français" and "Español"; the nasty word list holds "kyrpä*" and no U+FFFD; the sign-in limits are 5, 20 and
  50. The home page reads 1.0.0-rc.2. Signed in as Admin, the Master Editor lists "Unélith" (no U+FFFD on the page),
  and the village opens. The PHP error log stayed empty.

## Local results

Supporting evidence only; CI is the acceptance. Run on this commit's tree.

- `test_version.py` (the new pinned value), `test_credit_wording.py`, `test_release_package.py`, `test_host_guide.py`
  and `test_utf8_text.py`: 30 tests, OK. `DocLinkTest` (3 tests, 44 assertions) and `CommandLinkTest`: OK; the printed
  link is `…/blob/v1.0.0-rc.2/docs/HOST-GUIDE.md#9-upgrade`. Every relative link in the changed documents (54) names
  a file that exists.
- PHPUnit, 524 tests, 152,511 assertions: OK on PHP 8.4 with MariaDB 11.4 (2:04) and PHP 8.5 with MySQL 8.4 (2:24).
- All five shards on both legs, with Playwright's browsers hidden, each on a fresh database installed by
  `FreshInstallTest`: OK, 0 skipped.

  | Shard | Tests | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
  | --- | --- | --- | --- |
  | 1 | 60 | 841 s | 878 s |
  | 2 | 58 | 827 s | 866 s |
  | 3 | 231 | 758 s | 805 s |
  | 4 | 59 | 764 s | 790 s |
  | 5 | 60 | 745 s | 800 s |

- `release-upgrade-check.py`, each mode on an empty database: on both legs the default mode installed `v1.0.0-rc.1`,
  applied 0002 and 0003, and matched a fresh install (41 tables); `--release-ref HEAD` found 3 of 3 steps applied
  and changed nothing.
- PHP lint (479 files, 0 failures, on 8.4 and 8.5), both PHPStan gates (no errors), `composer validate --strict`,
  install and audit (no advisories), the historical verifier (PASS, 417 files, 11 commits), the project validator
  (PASS), `asset-versions.py --check`, and `git diff --check`: clean. `LICENSE.txt` and `README.txt` are identical to
  `historical-source-1.1.2`.

## Next

CI acceptance of this commit. Then Jim runs the Release cut workflow from the Actions tab with the four inputs in the
task report (the checklist's sections 3 and 4), and tests rc.2 on his server, including the rc.1 upgrade.

## Acceptance and the cut (recorded in Phase 6 task 22, 2026-11-16)

- **Accepted** at `100103d`: Modern core 43852203138 passed all 14 jobs. The PHP 8.4 `http` jobs took 9:51, 9:18,
  10:43, 15:46 and 14:36; the PHP 8.5 ones 14:09, 11:15, 17:46, 14:05 and 11:23. Baseline integrity 43852203104
  passed.
- **The cut**, checked by the command chat on 2026-11-16: the Release cut workflow, run 43944843726, passed, started
  with the four inputs of this task's report on Jim's request.
  - The tag `v1.0.0-rc.2`: annotated, tag object `147ad5a2800ccff279b8e35f5b56ae1fe877c2d2`, on `100103d`; its
    message is 861 bytes, SHA-256 `4b5b7465…b2ed`, the checklist's section 3.
  - The pre-release,
    https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.2: a pre-release,
    not latest. Its zip is 3,867,683 bytes with the digest `sha256:133625b3…e439`, equal to this task's build.
  - `v1.0.0-rc.1` and `historical-source-1.1.2` are unchanged.
- Jim's server test of rc.2 (upgraded from rc.1) found R10, fixed in task 22 (`PHASE6-TASK22-20261116.md`).
