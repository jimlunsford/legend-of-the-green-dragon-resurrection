# Cutting a test build: the checklist

How `1.0.0-rc.1` (and any later `1.0.0-rc.N` or `1.0.0`) is cut. Written in Phase 6 task 7 (2026-11-07). For `1.0.0-rc.1`, section 0 is done and section 1 is
made in Phase 6 task 14 (2026-11-13, `docs/PHASE6-TASK14-RC1-VERSION-20261113.md`), which also brought section 3's tag
message up to date. Sections 2 to 4 are done in Phase 6 task 15 (2026-11-13,
`docs/PHASE6-TASK15-RC1-CUT-20261113.md`): the tag `v1.0.0-rc.1` is on the accepted version commit and the package is
published as a GitHub pre-release. `main` is `1.0.0-rc.1` from the version commit on.

For `1.0.0-rc.2` (Jim's go of 2026-11-16, "the same as rc.1"): section 0 is done, section 1 is made in Phase 6 task 21
(2026-11-16, `docs/PHASE6-TASK21-RC2-VERSION-20261116.md`), which also writes rc.2's tag message in section 3 and runs
the release cut workflow against a stand-in `origin`. Sections 2 to 4 are done (recorded in Phase 6 task 22,
`docs/PHASE6-TASK22-20261116.md`): CI accepted the version commit `100103d`, and the release cut workflow, run on Jim's
request with task 21's inputs, made the tag `v1.0.0-rc.2`, built the package and published the pre-release. rc.1's
record below stays as history.

For `1.0.0-rc.3` (Jim's go of 2026-11-16, "same as rc.2"): section 0 is done, section 1 is made in Phase 6 task 23
(`docs/PHASE6-TASK23-RC3-VERSION-20261117.md`), which also writes rc.3's tag message in section 3 and runs the release
cut workflow against a stand-in `origin`. Sections 2 to 4 are done (recorded in Phase 6 task 24,
`docs/PHASE6-TASK24-20261117.md`): CI accepted the version commit `0632351`, and the release cut workflow, run on Jim's
request with task 23's inputs, made the tag `v1.0.0-rc.3`, built the package and published the pre-release. rc.1's and
rc.2's records stay as history.

For `1.0.0-rc.4` (Jim's go of 2026-11-17, "go rc.4"): section 0 is done, section 1 is made in Phase 6 task 25
(`docs/PHASE6-TASK25-RC4-VERSION-20261117.md`), which also writes rc.4's tag message in section 3 and runs the release
cut workflow against a stand-in `origin`. Sections 2 to 4 follow: CI's acceptance of the version commit, then the
release cut workflow, which Jim runs. rc.1's, rc.2's and rc.3's records stay as history.

A cut happens only on the maintainer's explicit go (Jim). Every step that tags, publishes or attaches anything waits for
that go, and a session never does them on its own. The steps are in order; a step that fails stops the cut.

## 0. Before the cut starts

- [x] Jim has said go for this cut, naming the version (`1.0.0-rc.1`): on 2026-11-13, "cut 1.0.0-rc.1, tag first,
      then test it on a server" (recorded in `ROADMAP.md`, Jim's decisions of 2026-11-13).
- [x] S11 (mail, account recovery and email verification) has landed and been accepted by CI on both legs and
      Baseline, before the version commit: its design and threat model approved by Jim first, then the build, with the
      `requirevalidemail` lock lifted as the design says and the release notes and host guide updated (Jim,
      2026-11-12: an rc.1 blocker). Built: the design approved (task 12, accepted at `ae4bd3b`), 13a and 13b accepted,
      13c (verification, address changes, the lock lifted, the host guide and release notes) committed in
      `PHASE6-TASK13C-20261113.md` and accepted at `0c034db` (Modern core 43369500162, all 14 jobs; Baseline integrity
      43369499961), recorded in Phase 6 task 14.
- [x] The release notes no longer list S11 as a known issue (Phase 6 task 13c: S11 moved into the changes; the
      known issues keep that mail is `mail()` only and best effort, and that a link's token reaches the access log).
- [x] Every task before the cut is accepted: both Modern core legs and Baseline integrity at SUCCESS on its commit.
      Checked in Phase 6 task 14: every Phase 6 task from 1 to 13c is ticked in `ROADMAP.md` with its accepted runs,
      the last being 13c at `0c034db`.
- [x] The release notes (`docs/RELEASE-NOTES-<version>.md`) list every open item a host or player can hit, checked
      against `ROADMAP.md` at the cut's starting commit. Checked in Phase 6 task 14 against `ROADMAP.md` at
      `0c034db`: the open items N1, N3, N8 and N11 and the missing abuse controls were added to the known issues.

For `1.0.0-rc.2`:

- [x] Jim has said go, naming the version: on 2026-11-16, "cut 1.0.0-rc.2, same as rc.1": the version commit first,
      then, after CI accepts it, the tag and the GitHub pre-release from the release cut workflow, which Jim runs
      (recorded in `ROADMAP.md`, Jim's decisions of 2026-11-16).
- [x] Every task before the cut is accepted: the rc.1 server-test fixes (tasks 16, 17 and 20, R1 to R9) and the four
      known-issue fixes (tasks 18 and 19), the last being task 20 at `27c3f94` (Modern core 43789198080, all 14 jobs;
      Baseline integrity 43789198083), recorded in Phase 6 task 21.
- [x] The release notes (`docs/RELEASE-NOTES-1.0.0-rc.2.md`) list every open item a host or player can hit, checked
      in Phase 6 task 21 against `ROADMAP.md` at `27c3f94`: master 5's broken name left the known issues (R9 fixed it),
      and the Dark Horse games' many Continue clicks (the one open item, after `1.0.0`) joined them. They name the two
      upgrade steps an rc.1 database runs, 0002 and 0003.

For `1.0.0-rc.3`:

- [x] Jim has said go, naming the version: on 2026-11-16, "cut 1.0.0-rc.3, same as rc.2": the version commit first,
      then, after CI accepts it, the tag and the GitHub pre-release from the release cut workflow, which Jim runs
      (recorded in `ROADMAP.md`, Jim's decisions of 2026-11-16).
- [x] Every task before the cut is accepted: the rc.2 server-test fix (task 22, R10) at `088b43c` (Modern core
      43971175438, all 14 jobs; Baseline integrity 43971175428), recorded in Phase 6 task 23.
- [x] The release notes (`docs/RELEASE-NOTES-1.0.0-rc.3.md`) list every open item a host or player can hit, checked
      in Phase 6 task 23 against `ROADMAP.md` at `088b43c`: R10 is done and listed as the change, with the search
      link's plain refusal Jim also saw; the one open item, the Dark Horse games' many Continue clicks (after
      `1.0.0`), stays a known issue. They say an rc.2 database runs no upgrade step and an rc.1 database runs 0002 and
      0003.

For `1.0.0-rc.4`:

- [x] Jim has said go, naming the version: on 2026-11-17, "go rc.4": the version commit first, then, after CI accepts
      it, the tag and the GitHub pre-release from the release cut workflow, which Jim runs (recorded in `ROADMAP.md`,
      Jim's decisions of 2026-11-17).
- [x] Every task before the cut is accepted: the rc.3 server-test fix (task 24, R11) at `91559fa` (Modern core
      44126961634, all 14 jobs; Baseline integrity 44126961852), recorded in Phase 6 task 25.
- [x] The release notes (`docs/RELEASE-NOTES-1.0.0-rc.4.md`) list every open item a host or player can hit, checked
      in Phase 6 task 25 against `ROADMAP.md` at `91559fa` with Jim's decision of 2026-11-17: R11 is done and listed as
      the change; the three open items, all after `1.0.0` (the Dark Horse games' many Continue clicks, the forest menu
      while a fight is stored, the other bare refusals on player pages), are known issues. They say an rc.3 or rc.2
      database runs no upgrade step and an rc.1 database runs 0002 and 0003.

## 1. The version commit

One commit on `main`, made from the expected starting SHA, that changes the version and nothing else of substance:

- `src/Version.php`: `CURRENT` to the new version (`'1.0.0-rc.1'`; `'1.0.0-rc.2'` in task 21; `'1.0.0-rc.3'` in
  task 23; `'1.0.0-rc.4'` in task 25).
- `project.json`: `version` to the same, and `phase` to what the cut says (for example `release-candidate`).
- `docs/VERSIONING.md`: the "Current version:" line to the same, and "No such tag exists yet" to what will be true
  after the tag.
- `tests/tooling/test_version.py`: the pinned value in `test_code_project_and_docs_agree_on_a_valid_version` to the
  new version. That assertion exists so the version changes only on purpose; updating it here is that purpose.
- `docs/RELEASE-NOTES-<version>.md`: drop the "Draft. This test build has not been cut." paragraph and give the date.
- `README.md` and `SECURITY.md`: their "`main` is `1.0.0-dev`" lines to the new version, still saying it is a test
  build and not certified for public hosting.
- `ROADMAP.md` and `docs/CHANGES-FROM-1.1.2.md`: record the version commit.

Checks before pushing it (each must pass; record the output in the cut's evidence doc):

- [ ] Remote `main` is at the expected starting SHA. If it moved, stop.
- [ ] The working tree holds only the version commit's changes.
- [ ] `python3 scripts/verify-historical-baseline.py` passes and `historical-source-1.1.2` is unchanged (tag object
      `51cab4fbe58a234651a3177a56289b18bc152b4d`, commit `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`).
- [ ] `python3 scripts/validate-project.py` passes.
- [ ] `python3 -m unittest tests/tooling/test_version.py -v` passes with the new version.
- [ ] `composer check`, both PHPStan gates, PHPUnit on PHP 8.4 and 8.5.
- [ ] `python3 scripts/build-release-package.py --out <absolute folder outside the tree>` twice, into two folders:
      the two `.zip.sha256` files must be the same, and the zip's name must carry the new version.
- [ ] `git diff --check` is clean.

Push with a normal, non-force `git push`. No tag yet.

For `1.0.0-rc.2` these checks ran in Phase 6 task 21, for `1.0.0-rc.3` in Phase 6 task 23, and for `1.0.0-rc.4` in
Phase 6 task 25; their output is in each task's evidence doc.

## 2. CI acceptance of the version commit

The version commit is accepted only when, on that exact commit:

- [x] Modern core is SUCCESS on both legs (PHP 8.4 with MariaDB 11.4, PHP 8.5 with MySQL 8.4): every `checks`, `http`
      shard and `result` job, with the actual totals recorded. For `1.0.0-rc.1`: run 43449051336 on `2bbc69d`, all 14
      jobs, on `ubuntu-24.04` (PHP 8.4 shards 14:44, 10:29, 15:24, 14:50, 13:37; PHP 8.5 shards 17:33, 16:58, 17:46,
      17:59, 10:32).
- [x] Baseline integrity is SUCCESS. For `1.0.0-rc.1`: run 43449051323 on `2bbc69d`.
- [x] For `1.0.0-rc.2`: both of the above on task 21's version commit, `100103d`: Modern core 43852203138, all 14
      jobs (PHP 8.4 shards 9:51, 9:18, 10:43, 15:46, 14:36; PHP 8.5 shards 14:09, 11:15, 17:46, 14:05, 11:23);
      Baseline integrity 43852203104.
- [x] For `1.0.0-rc.3`: both of the above on task 23's version commit, `0632351`: Modern core 43999468955, all 14
      jobs (PHP 8.4 checks 2:23, shards 13:28, 14:16, 9:46, 16:17, 16:14; PHP 8.5 checks 2:37, shards 12:41, 12:03,
      16:53, 10:58, 10:44; on both legs the release upgrade check installed `v1.0.0-rc.2` and upgraded it to `0632351`,
      41 tables, and `--release-ref HEAD` matched too); Baseline integrity 43999469169 (recorded in task 24).
- [ ] For `1.0.0-rc.4`: both of the above on task 25's version commit.

A run that a later push cancelled is not acceptance, and no earlier commit's run stands in for it. A failed run stays on
record. If anything fails, fix it in a new commit and start this section again on that commit; the tag goes on the
commit CI accepted.

## 3. The tag (only on Jim's go)

An annotated tag on the accepted version commit, named `v<version>` (`v1.0.0-rc.4` for this cut). It matches the release
tag pattern the release upgrade check reads (`scripts/release-upgrade-check.py`, `vMAJOR.MINOR.PATCH` or
`vMAJOR.MINOR.PATCH-rc.N`), so from the next commit on every Modern core run installs it and upgrades it to the commit
under test.

The tag message for `1.0.0-rc.4`, exactly. The release cut workflow takes the first fenced block after this section's
heading, so this block stays first:

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

(Written in Phase 6 task 25, 2026-11-17, in rc.3's shape. Each line checked against the code and the documents at the
version commit in its evidence doc.)

- [ ] For `1.0.0-rc.4`: the tag, made by the release cut workflow after CI accepts the version commit, on Jim's
      request, with the message above byte for byte.

History: the tag message for `1.0.0-rc.3`, exactly as tagged:

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

(Written in Phase 6 task 23, 2026-11-16, in rc.2's shape. Each line checked against the code and the documents at the
version commit in its evidence doc.)

- [x] For `1.0.0-rc.3`: the tag, made by the release cut workflow (run 44055317395, started with task 23's four inputs
      on Jim's request, 2026-11-17): tag object `dec02e9ff601fb445b577d870602aaae6ee91a4a`, annotated, by
      `github-actions[bot]`, on `0632351b9ca410e09971fe79b93992d66b8a658b`, with the message above byte for byte (884
      bytes, SHA-256 `8bd23545…e027de`). `v1.0.0-rc.1`, `v1.0.0-rc.2` and `historical-source-1.1.2` are unchanged
      (recorded in task 24).

History: the tag message for `1.0.0-rc.2`, exactly as tagged:

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

(Written in Phase 6 task 21, 2026-11-16, in rc.1's shape. Each line checked against the code and the documents at the
version commit in its evidence doc.)

- [x] For `1.0.0-rc.2`: the tag, made by the release cut workflow (run 43944843726, started with task 21's four inputs
      on Jim's request, 2026-11-16): tag object `147ad5a2800ccff279b8e35f5b56ae1fe877c2d2`, annotated, on
      `100103d76f3e1903e006e0503920054b285040f1`, with the message above byte for byte (861 bytes, SHA-256
      `4b5b7465…b2ed`). `v1.0.0-rc.1` and `historical-source-1.1.2` are unchanged.

History: the tag message for `1.0.0-rc.1`, exactly as tagged:

```
Legend of the Green Dragon - Resurrection 1.0.0-rc.1

The first test build of Resurrection 1.0.0: a candidate for hosts and players to try. It is not the release, it has
had no independent security review, and it is not certified for public game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs only; no 1.1.2 database is upgraded. Known issues, among them mail only through PHP's mail(), best
effort, mail links in the web server's access log, no abuse controls yet, and 1.1.2 behaviors kept as recorded:
docs/RELEASE-NOTES-1.0.0-rc.1.md. Hosting: docs/HOST-GUIDE.md.
```

(Brought up to date in Phase 6 task 14, 2026-11-13: until then the message listed "no account recovery or email
verification" among the known issues, which S11 closed.)

- [x] Done for `1.0.0-rc.1` in Phase 6 task 15 (2026-11-13): tag object `522be1c1dfbbb3fe020ebd6586c7b756372d233b`,
      type commit, on `2bbc69dd50cb62ba3126bc05390b1f84ce34e507`, with the message above byte for byte. The session's
      network refuses tag pushes, so `.github/workflows/release-cut.yml` made and pushed it (run 43458915150, started
      by Jim from the Actions tab); the remote tag resolves to `2bbc69d`.

Never move or delete the tag once pushed. `historical-source-1.1.2` is never touched.

## 4. The release package (only on Jim's go)

What the package holds (since Phase 6 task 16, R1; the `1.0.0-rc.1` zip held only the upload): one folder,
`legend-of-the-green-dragon-resurrection-<version>/`, with `upload/` (exactly the host guide's "Upload these" list, what
goes into the site's folder), `INSTALL.txt` (made from `docs/package/INSTALL.txt`: check the SHA-256, upload the
contents of `upload/`, read the host guide, online at the tag), and beside them `HOST-GUIDE.md`,
`RELEASE-NOTES-<version>.md`, `THIRD-PARTY-LICENSES.md`, `hosting/` with the three sample rule files, and copies of
`LICENSE.txt`, `README.txt` and `NOTICE`. Check `INSTALL.txt` in the built zip before the release goes out. The
release cut workflow (`.github/workflows/release-cut.yml`) builds the same package with the same script.

- [x] From a clean checkout of the tag: `python3 scripts/build-release-package.py --out <absolute folder outside the
      tree>`. It writes `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` and its `.zip.sha256`, and prints the
      file count, size and SHA-256. Record all three. For `1.0.0-rc.1`: 753 files, 3,626,284 bytes,
      `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857`, the same as task 14's builds.
- [x] Build it a second time into another folder; the SHA-256 must match. It matched, in the session and on the runner.
- [x] Unzip it, copy the contents of `upload/` into an empty docroot (before task 16, the whole zip), install through
      `setup.php`, and play to the village and one forest fight (the run recorded in
      `docs/PHASE6-TASK7-RC-MATERIALS-20261107.md` is the pattern). Done in task 15: 41 tables, 24
      modules with 23 active, the village and one forest fight, the footer showing `1.0.0-rc.1`.
- [x] For `1.0.0-rc.2`: the same, by the release cut workflow from the tag. Task 21 built the version commit's package
      twice (its SHA-256 is the workflow's `package_sha256` input), installed it fresh through `setup.php`, and
      upgraded the rc.1 package's install to it. The workflow's build, 3,867,683 bytes, is `sha256:133625b3…e439`, the
      same.
- [x] Attach the zip and its `.sha256` nowhere until Jim says where. The script itself never uploads, tags or publishes,
      and it is not in the package (`docs/HOST-GUIDE.md`, "Do not upload"). For `1.0.0-rc.1` Jim said a GitHub
      pre-release: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.1
      (not latest; the zip downloaded back has the same SHA-256). For `1.0.0-rc.2`, the same way:
      https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.2 (a pre-release,
      not latest; the zip's digest equals the build). For `1.0.0-rc.3`, the same way:
      https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.3 (a pre-release,
      not latest; the zip's digest equals the build).
- [x] For `1.0.0-rc.3`: the same, by the release cut workflow from the tag. Task 23 built the version commit's package
      twice (its SHA-256 is the workflow's `package_sha256` input), installed it fresh through `setup.php`, and
      upgraded the rc.2 and rc.1 packages' installs to it. The workflow's build, 3,868,683 bytes, is
      `sha256:6fe8b132…3569`, the same; the zip downloaded back has that SHA-256, as its `.sha256` says (recorded in
      task 24).
- [ ] For `1.0.0-rc.4`: the same, by the release cut workflow from the tag. Task 25 built the version commit's package
      twice (its SHA-256 is the workflow's `package_sha256` input), installed it fresh through `setup.php`, and
      upgraded the rc.3, rc.2 and rc.1 packages' installs to it.

## 5. After the tag

- [x] The next commit's Modern core shows the release upgrade check installing `v1.0.0-rc.1` and upgrading it, instead
      of "no release yet". For `1.0.0-rc.1`: Modern core 43461940045 on `95ae3f3`, both legs ("v1.0.0-rc.1 installed,
      upgraded to 95ae3f3, and the same schema and data as a fresh install (41 tables)").
- [x] `main` kept `1.0.0-rc.1` (`docs/VERSIONING.md`, "`main`'s version after a tag") until the next cut's version
      commit set `1.0.0-rc.2` (task 21). It never goes back to `1.0.0-dev`. After `v1.0.0-rc.2`, `main` keeps
      `1.0.0-rc.2` until the next cut's version commit set `1.0.0-rc.3` (task 23). After `v1.0.0-rc.3`, `main` keeps
      `1.0.0-rc.3` until the next cut's version commit set `1.0.0-rc.4` (task 25). After `v1.0.0-rc.4`, `main` keeps
      `1.0.0-rc.4` until the next cut sets `1.0.0-rc.5` or `1.0.0`.
- [x] For `1.0.0-rc.2`: the next commit's Modern core installs `v1.0.0-rc.2` and upgrades it. Modern core
      43971175438 on Phase 6 task 22's commit, `088b43c`, both legs ("v1.0.0-rc.2 installed, upgraded to 088b43c, and
      the same schema and data as a fresh install (41 tables)"; recorded in task 23).
- [x] For `1.0.0-rc.3`: the next commit's Modern core installs `v1.0.0-rc.3` and upgrades it. Modern core
      44126961634 on Phase 6 task 24's commit, `91559fa`, both legs ("v1.0.0-rc.3 installed, upgraded to 91559fa, and
      the same schema and data as a fresh install (41 tables)"; recorded in task 25).
- [ ] For `1.0.0-rc.4`: the next commit's Modern core installs `v1.0.0-rc.4` and upgrades it.
- [ ] After `v1.0.0`, the first commit on `main` sets the next number with `-dev` (`1.1.0-dev`, or `1.0.1-dev` when only
      fixes are planned).
- [ ] Record the tag, the accepted runs and the package's SHA-256 in `ROADMAP.md` and the cut's evidence doc.
