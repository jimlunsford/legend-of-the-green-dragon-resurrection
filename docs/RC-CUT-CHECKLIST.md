# Cutting a test build: the checklist

How `1.0.0-rc.1` (and any later `1.0.0-rc.N` or `1.0.0`) is cut. Written in Phase 6 task 7 (2026-11-07). For `1.0.0-rc.1`, section 0 is done and section 1 is
made in Phase 6 task 14 (2026-11-13, `docs/PHASE6-TASK14-RC1-VERSION-20261113.md`), which also brought section 3's tag
message up to date. No tag exists and no package has been published; `main` is `1.0.0-rc.1` from the version commit on.

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

## 1. The version commit

One commit on `main`, made from the expected starting SHA, that changes the version and nothing else of substance:

- `src/Version.php`: `CURRENT` to the new version (`'1.0.0-rc.1'`).
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

## 2. CI acceptance of the version commit

The version commit is accepted only when, on that exact commit:

- [ ] Modern core is SUCCESS on both legs (PHP 8.4 with MariaDB 11.4, PHP 8.5 with MySQL 8.4): every `checks`, `http`
      shard and `result` job, with the actual totals recorded.
- [ ] Baseline integrity is SUCCESS.

A run that a later push cancelled is not acceptance, and no earlier commit's run stands in for it. A failed run stays on
record. If anything fails, fix it in a new commit and start this section again on that commit; the tag goes on the
commit CI accepted.

## 3. The tag (only on Jim's go)

An annotated tag on the accepted version commit, named `v1.0.0-rc.1`. It matches the release tag pattern the release
upgrade check reads (`scripts/release-upgrade-check.py`, `vMAJOR.MINOR.PATCH` or `vMAJOR.MINOR.PATCH-rc.N`), so from the
next commit on every Modern core run installs it and upgrades it to the commit under test.

The tag message, exactly:

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

Never move or delete the tag once pushed. `historical-source-1.1.2` is never touched.

## 4. The release package (only on Jim's go)

- [ ] From a clean checkout of the tag: `python3 scripts/build-release-package.py --out <absolute folder outside the
      tree>`. It writes `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` and its `.zip.sha256`, and prints the
      file count, size and SHA-256. Record all three.
- [ ] Build it a second time into another folder; the SHA-256 must match.
- [ ] Unzip it into an empty docroot, install through `setup.php`, and play to the village and one forest fight (the
      run recorded in `docs/PHASE6-TASK7-RC-MATERIALS-20261107.md` is the pattern).
- [ ] Attach the zip and its `.sha256` nowhere until Jim says where. The script itself never uploads, tags or publishes,
      and it is not in the package (`docs/HOST-GUIDE.md`, "Do not upload").

## 5. After the tag

- [ ] The next commit's Modern core shows the release upgrade check installing `v1.0.0-rc.1` and upgrading it, instead
      of "no release yet".
- [ ] `main` keeps `1.0.0-rc.1` (`docs/VERSIONING.md`, "`main`'s version after a tag"). It never goes back to
      `1.0.0-dev`. The next cut's version commit sets `1.0.0-rc.2`, or `1.0.0` for the release.
- [ ] After `v1.0.0`, the first commit on `main` sets the next number with `-dev` (`1.1.0-dev`, or `1.0.1-dev` when only
      fixes are planned).
- [ ] Record the tag, the accepted runs and the package's SHA-256 in `ROADMAP.md` and the cut's evidence doc.
