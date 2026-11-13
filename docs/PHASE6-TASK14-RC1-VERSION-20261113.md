# Phase 6 task 14: the `1.0.0-rc.1` version commit (no tag)

2026-11-13. Made from `0c034db311c83ca8f74ba5c25d86e5fec0f7bbfe`, which matched the remote `main`, by
`docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only: no tag, no GitHub release, and no
package published or attached anywhere. The tag and the package follow in a later task, after CI accepts this commit
(the checklist's sections 2 to 5).

## Recorded first

- **13c accepted** at `0c034db`: Modern core 43369500162 passed all 14 jobs. The PHP 8.4 `http` jobs took 16:13,
  15:14, 16:18, 12:37 and 10:20; the PHP 8.5 ones 17:07, 17:18, 18:25, 19:06 and 12:02. Baseline integrity 43369499961
  passed. Appended to `PHASE6-TASK13C-20261113.md` ("Acceptance"). 13c and S11 are ticked in `ROADMAP.md`, in
  `PHASE6-RC-READINESS-20261106.md` and in the checklist's section 0. S11 is complete.
- **Jim's go**, 2026-11-13: "cut 1.0.0-rc.1, tag first, then test it on a server." Recorded in `ROADMAP.md` (Jim's
  decisions of 2026-11-13) and in the checklist's section 0.
- **Section 0's other two boxes**, checked and ticked:
  - Every task before the cut is accepted: every Phase 6 task from 1 to 13c is ticked in `ROADMAP.md` with its accepted
    runs, the last being 13c at `0c034db`.
  - The release notes list every open item a host or player can hit, checked against `ROADMAP.md` at `0c034db` and
    the NICE items it points to (`PHASE6-RC-READINESS-20261106.md`, N1 to N10). Four were missing from the notes and
    are now known issues: N11 (Game Settings settings that do nothing), N1 (asset URLs carry no version), N3 and N8
    (small targets on phones: staff tables, translator links), and the public hosting gate for abuse controls (no
    sign-in throttling or flood limits). N5's 1.1.2 behaviors are already known issues; the other NICE items (N2, N4,
    N6, N7, N9, N10) are improvements or recorded changes, not faults a host or player hits. The other open entries in `ROADMAP.md` are the public hosting gates, which the notes already cover ("Not
    certified for public hosting"), and this task.

## The tag message (checklist section 3)

Checked line by line against the code and the docs. The first two paragraphs are still true and are unchanged. The
last one named a known issue that S11 closed.

Before:

```
Fresh installs only; no 1.1.2 database is upgraded. Known issues, among them no account recovery or email
verification: docs/RELEASE-NOTES-1.0.0-rc.1.md. Hosting: docs/HOST-GUIDE.md.
```

After:

```
Fresh installs only; no 1.1.2 database is upgraded. Known issues, among them mail only through PHP's mail(), best
effort, mail links in the web server's access log, no abuse controls yet, and 1.1.2 behaviors kept as recorded:
docs/RELEASE-NOTES-1.0.0-rc.1.md. Hosting: docs/HOST-GUIDE.md.
```

Each item is in the release notes' known issues today. "Fresh installs only" matches `docs/VERSIONING.md` and the
host guide; `docs/RELEASE-NOTES-1.0.0-rc.1.md` and `docs/HOST-GUIDE.md` exist at these paths. A note under the code
block in the checklist records the change.

## The version commit (checklist section 1)

- `src/Version.php`: `CURRENT` is `'1.0.0-rc.1'`.
- `project.json`: `version` `1.0.0-rc.1`, `phase` `release-candidate`.
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.1`"; the footer example shows the same; "No such tag exists yet."
  is now what holds after the tag: the tag `v1.0.0-rc.1` goes on this commit once CI accepts it, and `main` keeps
  `1.0.0-rc.1` until the next cut.
- `tests/tooling/test_version.py`: the pinned value is `1.0.0-rc.1`. One more literal in the same file had to change:
  the case "the doc differs" put `1.0.0-rc.1` into the doc to make it disagree with the code, and with the code now at
  `1.0.0-rc.1` it no longer disagreed, so the case found no problem and the test failed (`AssertionError: [] == []`).
  It now uses `1.0.1`, which still differs from the code and is still a valid version, so the case proves what it
  proved before. No test was removed, skipped or weakened.
- `docs/RELEASE-NOTES-1.0.0-rc.1.md`: the draft paragraph is gone; the notes are dated 2026-11-13 and name the tag.
  The known issues gained the four items above.
- `README.md` and `SECURITY.md`: `main` is `1.0.0-rc.1`, the first test build, for hosts to try, and not certified for
  public game hosting.
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md` record the version commit.
- Records brought up to date, nothing else of substance:
  - `docs/RC-CUT-CHECKLIST.md`: section 0 ticked, the intro, and the tag message above.
  - `docs/PHASE6-RC-READINESS-20261106.md`: S11 done and Jim's go.
  - `docs/SECURITY-STATUS.md`: its open list still named "Stale form tokens after signing in again during the new
    day", which task 9 fixed (accepted at `2a6e2a7`; `ROADMAP.md`, "Found during Phase 6", is ticked). The code wins,
    so the line is gone and the header says so.

No behavior changes. The footer shows the new version; with no configuration file, `home.php` on PHP 8.5's built-in
server answered 200 with:

```
<title>The game has not yet been installed
Version: Resurrection 1.0.0-rc.1, based on LoGD 1.1.2 Dragonprime Edition
```

## Section 1's checks

Each passed. Run on 2026-11-13 on the working tree that became this commit.

- **Remote `main`** was `0c034db311c83ca8f74ba5c25d86e5fec0f7bbfe`, the expected starting SHA (checked again just
  before the push).
- **The working tree** held only this commit's changes: the files listed above and this document.
- **`python3 scripts/verify-historical-baseline.py`**, exit 0:

  ```
  "status": "PASS", "tag": "historical-source-1.1.2",
  "tag_object": "51cab4fbe58a234651a3177a56289b18bc152b4d",
  "commit": "bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2",
  "tree": "4013a0ccc5227e87cd7a22de00b7c322d7aa237c",
  "files_verified": 417, "historical_commits": 11
  ```

- **`python3 scripts/validate-project.py`**, exit 0: `"status": "PASS"`, `"metadata_schema": 1`.
- **`python3 -m unittest tests/tooling/test_version.py -v`**, exit 0: `Ran 3 tests`, `OK`
  (`test_a_disagreement_or_an_invalid_version_fails`, `test_code_project_and_docs_agree_on_a_valid_version`,
  `test_the_base_version_stays_1_1_2`).
- **`composer check`** (PHP 8.5.12, a fresh MySQL 8.4 database), exit 0 in 108 seconds: `PHP lint: 466 files, 0
  failures`; PHPUnit `OK (498 tests, 150914 assertions)` in 1:37; PHPStan `[OK] No errors`; the historical verifier
  `PASS`.
- **Both PHPStan gates** (`phpstan.neon`, `phpstan-new.neon`): `[OK] No errors`.
- **PHPUnit on both legs**, each on a fresh database: PHP 8.4.27 with MariaDB 11.4, `OK (498 tests, 150914
  assertions)` in 1:17; PHP 8.5.12 with MySQL 8.4, the same in 1:39.
- **The release package**, built twice with `python3 scripts/build-release-package.py --out`, into `/var/tmp/rz-pkg1`
  and `/var/tmp/rz-pkg2` (outside the tree):

  | Build | Zip | Files | Bytes | SHA-256 |
  | --- | --- | --- | --- | --- |
  | 1 | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 753 | 3626284 | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` |
  | 2 | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 753 | 3626284 | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` |

  The two `.zip.sha256` files and the two zips are byte-identical (`cmp`), and the zip's name carries the new
  version. The package's `src/Version.php` says `'1.0.0-rc.1'`. It holds no `docs/`, so this document does not change
  it. Neither build was published or attached; both stay outside the repository. The package for the tag is built
  again from a clean checkout of the tag (checklist section 4).
- **`git diff --check`**: clean.

## Local results

All five tooling shards on both legs, each shard in its own copy of the final tree with its own database, installed by
`FreshInstallTest` first. No failures, errors or skips.

| Shard | Tests | PHP 8.4.27, MariaDB 11.4 | PHP 8.5.12, MySQL 8.4 |
| --- | --- | --- | --- |
| 1 | 203 | 13.4 min | 14.7 min |
| 2 | 57 | 13.0 min | 14.5 min |
| 3 | 59 | 13.7 min | 15.2 min |
| 4 | 60 | 13.8 min | 15.3 min |
| 5 | 60 | 13.1 min | 14.5 min |

The test counts include each shard's one prerequisite test, where it has one. No job is near 21 minutes, so the shard
durations were not refreshed. Local results are supporting evidence only; CI is the acceptance.

The only test change is in `tests/tooling/test_version.py`: the pinned value, and the one literal explained above.
