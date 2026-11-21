# Phase 6 task 33: the `1.0.0` version commit, and the release cut workflow for a full release (no tag)

2026-11-21. Made from `3500e4403cd7d3c42f003811f08d3646f4766260`, which matched the remote `main`, by
`docs/RC-CUT-CHECKLIST.md` sections 0 and 1. This task makes the version commit only. It makes no tag and no GitHub
release, it publishes or attaches no package anywhere, and it does not run the release cut workflow in GitHub or change
any repository setting. It changes the release cut workflow so that a release (`MAJOR.MINOR.PATCH`) is published as a
full GitHub release marked Latest, while a test build (`-rc.N`) stays a pre-release that is never Latest. The tag and
the release follow when Jim runs that workflow, after CI accepts this commit (the checklist's sections 2 to 5). This
commit changes no installed schema or rows, so it has no upgrade step.

## Recorded first

- **Task 32 accepted** at `3500e44`: Modern core 44851280261 passed all 14 jobs (run wall time 16:50).
  - PHP 8.4 / MariaDB 11.4: `checks` 2:17; `http` 1 to 5: 10:40, 15:08, 14:25, 15:59, 9:45; `result` 0:03.
  - PHP 8.5 / MySQL 8.4: `checks` 3:05; `http` 1 to 5: 16:43, 11:55, 11:21, 12:00, 11:28; `result` 0:02.
  - Slowest: 16:43, PHP 8.5 `http (1)`.
  - On both legs: "Release upgrade check: v1.0.0-rc.5 installed, upgraded to 3500e44, and the same schema and data as a
    fresh install (41 tables)", and the same with HEAD installed.
  - Baseline integrity 44851280175 passed.
  - Checked in this task through the REST API: both runs' status, conclusion, event and head commit (`completed`,
    `success`, `push`, `3500e44…`); each job's time, from its start and end (the run went from 01:37:40Z to 01:54:30Z);
    and the two upgrade check lines in both `checks` job logs. All of them match the brief's figures.
- **GitHub's About box**, set by Jim on 2026-11-20, checked through the REST API: the description, the website
  https://legendofthegreendragon.com, the 10 topics, private vulnerability reporting (`{"enabled": true}`) and the
  social preview (set: the repository page's `og:image` is a custom upload).
  - The REST API does not report the social preview's bytes, and the image host is closed to these sessions, so they
    could not be compared with `docs/images/social-preview.png`.
  - The full record is in `PHASE6-TASK32-20261121.md`'s "Acceptance (recorded in Phase 6 task 33, 2026-11-21)".
- These results are also written into `ROADMAP.md`: task 32's line is ticked ("Accepted at `3500e44`, Modern core
  44851280261 (all 14 jobs; slowest 16:43, PHP 8.5 `http (1)`; …), Baseline integrity 44851280175 (recorded in task
  33)"), task 31's line carries the About box, and the progress list's item 13 is updated.
- No release in the repository is marked Latest today: `GET /repos/…/releases/latest` answers 404, because all five
  releases are pre-releases.

## Jim's decisions

`1.0.0` comes from the plan Jim set on 2026-11-20 (recorded in `ROADMAP.md` and in task 31's and task 32's briefs):

- rc.5 passed his server test;
- `1.0.0` is a full GitHub release marked Latest, and the test builds stay pre-releases;
- everything is polished first: task 31 for the documents, task 32 for F1, F3 and F4, with F2 recorded and not changed;
- then task 33 is the `1.0.0` version commit, with the workflow change, and task 34 sets `main` to `1.0.1-dev` and
  records the cut.

After `1.0.0`, bug fixes only (Jim, 2026-11-13). No new decision was needed. In the checklist's section 0 this is the
go for `1.0.0`.

The other two boxes in section 0 were checked and ticked for `1.0.0`:

- **Every task before the cut is accepted, each with all 14 jobs:**
  - task 30 at `deee4e6` (Modern core 44723370052);
  - task 31 at `a2b3208` (Modern core 44805790920; Baseline integrity 44805790924);
  - task 32 at `3500e44` (above).
- **The release notes list every open item a host or player can hit**, checked against `ROADMAP.md` at `3500e44`:
  - The after-`1.0.0` list says "Nothing is listed here".
  - The only open boxes left are task 33 itself, task 34 and the checklist's post-release steps. A host or player can
    hit none of them.
  - F2 (special abilities need skill as well as uses) is in the notes as recorded, not changed.
  - The rest of rc.5's known issues are carried over, except two: licensing moved to its own section, and "Not
    certified" moved to "Status".

## The checklist (`docs/RC-CUT-CHECKLIST.md`)

The checklist is brought up to date for `1.0.0`, and rc.1's to rc.5's records are kept as history.

- **The title:** it is now "Cutting a test build or a release: the checklist".
- **The opening paragraphs:** a paragraph on `1.0.0` names this task and the workflow change.
- **Section 0:** the `1.0.0` boxes, ticked (above).
- **Section 1:**
  - The version line names `'1.0.0'` in task 33.
  - The phase line says what each kind of version takes: `release-candidate` for a test build, `released` for a
    release.
  - The README line says what a release states.
- **Sections 2, 4 and 5:** an open `1.0.0` box in each, for CI, the release and the next commit's upgrade check.
  - Section 4's box says the release is a full release marked Latest, checked as such by the workflow.
  - Section 5 says `main` keeps `1.0.0` until task 34 sets `1.0.1-dev`.
- **Section 3:** written for `v1.0.0`.
  - `1.0.0`'s message is its first fenced block, with a note and the open box for its tag.
  - rc.5's message and its box come after, under "History: the tag message for `1.0.0-rc.5`, exactly as tagged", moved
    byte for byte.
  - rc.5's block there is the message of the tag `v1.0.0-rc.5` exactly: `git cat-file -p v1.0.0-rc.5` from the line
    after the header's blank line, 910 bytes, SHA-256 `df9dbfac…7f0b`, `cmp` equal.
- **Where the checklist said "pre-release":** each such place now says what a release gets instead. A test build is a
  pre-release with `--latest=false`, and the check finds that the latest release is not its tag. A release is a full
  release with `--latest`, and the check finds that it is not a pre-release, not a draft, and is what
  `/releases/latest` returns.

## The tag message (checklist section 3)

The release cut workflow takes the first fenced block after `## 3. The tag` and adds one newline. `1.0.0`'s block, in
rc.5's shape:

```
Legend of the Green Dragon - Resurrection 1.0.0

The first release of Resurrection. It has had no independent security review, and it is not certified for public
game hosting.

Based on Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT Traub and the DragonPrime
Development Team. Licensed under CC BY-NC-SA 2.0 (LICENSE.txt). Resurrection's changes: Phoenix 2:33 LLC (NOTICE).

Fresh installs, or an upgrade from 1.0.0-rc.5, 1.0.0-rc.4, 1.0.0-rc.3 or 1.0.0-rc.2 (no step) or 1.0.0-rc.1 (steps
0002 and 0003); no 1.1.2 database is upgraded. Known issues, among them mail only through PHP's mail(), best effort,
mail links in the web server's access log, abuse controls beyond sign-in throttling left to the operator, and 1.1.2
behaviors kept as recorded: docs/RELEASE-NOTES-1.0.0.md. Hosting: docs/HOST-GUIDE.md.
```

It was taken out with the workflow's own Python:

```python
section = text.index('## 3. The tag')
start = text.index('```\n', section) + 4
end = text.index('\n```', start)
message = text[start:end] + '\n'
```

The result is 838 bytes with SHA-256 `75be9c441ce17b34788156f629bfd102b008604959a9dbac4f561763d1d91b0a`.

Each line, checked at this commit:

- **"1.0.0":** `src/Version.php`.
- **"The first release of Resurrection":**
  - rc.1 to rc.5 are test builds (`v1.0.0-rc.1` to `v1.0.0-rc.5`, all of them pre-releases, checked through the REST
    API below).
  - No tag without `-rc` exists apart from `historical-source-1.1.2`.
- **"no independent security review … not certified for public game hosting":** the release notes' "Status",
  `SECURITY.md`, `README.md`, and `project.json` (`public_game_hosting_approved: false`).
- **The credit paragraph:** unchanged from rc.1 to rc.5. `LICENSE.txt` and `NOTICE` are at those names in the package.
- **"an upgrade from 1.0.0-rc.5, 1.0.0-rc.4, 1.0.0-rc.3 or 1.0.0-rc.2 (no step)":**
  - `upgrades/` holds steps 0001 to 0003, and all four of those builds have all three.
  - The upgrade runs below each answer "The database is current: 3 of 3 upgrade steps applied … Nothing was changed.".
- **"or 1.0.0-rc.1 (steps 0002 and 0003)":** rc.1 has only step 0001, and the setup.php run below applied 0002 and 0003.
  Both upgrade cases are in the notes' "Upgrading".
- **"no 1.1.2 database is upgraded":** `docs/VERSIONING.md` and the host guide.
- **The known issues named:** each one is in the `1.0.0` notes' "Known issues":
  - mail only through `mail()`, best effort;
  - a link's token in the access log;
  - "Abuse controls beyond sign-in throttling are the operator's";
  - the 1.1.2 behaviors kept.
- **The two documents:** `docs/RELEASE-NOTES-1.0.0.md` and `docs/HOST-GUIDE.md` exist at those paths and are in the
  package.

## The version commit (checklist section 1)

- **`src/Version.php`:** `CURRENT` is `'1.0.0'`.
- **`project.json`:** `version` is `1.0.0` and `phase` is `released`.
  - The validator had no value for a release. It checked only that the phase was a string, and it knew only
    `release-candidate`.
  - So `scripts/validate-project.py` now defines the phases (`PHASES = ('release-candidate', 'released')`) and checks
    that the phase fits the version, in `phase_problem()`:
    - a test build (`-rc.N`) has `release-candidate`;
    - a release (`MAJOR.MINOR.PATCH`) has `released`;
    - a development version such as `1.0.1-dev` may have either.
  - `docs/VERSIONING.md` has a new subsection on this, "`project.json`'s phase".
- **`tests/tooling/test_version.py`:** the pinned value is `1.0.0`. A new test,
  `test_project_json_names_the_phase_its_version_is_in`, does three things:
  - it loads the validator and checks that `project.json`'s phase is `released` and that `phase_problem()` accepts it;
  - it checks the pairs that fit: `1.0.0`, `2.3.4` and `1.0.1-dev` with `released`; `rc.1`, `rc.12` and `1.1.0-dev`
    with `release-candidate`;
  - it checks the pairs that do not: `1.0.0` with `release-candidate`; `rc.6` with `released`; `1.0.0` with `release`,
    with 1.1.2's old phase string, with an empty phase and with none.
- **Fail-first at `3500e44`:** the changed test file was run against `3500e44`'s tree and failed with 2 of 4 tests
  failing: `'1.0.0' != '1.0.0-rc.5'` and `'released' != 'release-candidate'`. The log is in
  `/mnt/project-files/phase6-task33/local/failfirst-test_version-at-3500e44.txt`. At this commit all 4 pass.
- **`docs/VERSIONING.md`:**
  - "Current version: `1.0.0`", and the footer example shows the same.
  - The tag paragraph says the rc.5 tag is on its version commit and that the tag `v1.0.0` goes on this one once CI
    accepts it; after it, the first commit on `main` sets `1.0.1-dev`.
  - The paragraph on the workflow says what it publishes for each kind of version.
- **`docs/RELEASE-NOTES-1.0.0.md`:** written as the release page's text, with the draft paragraph gone, dated
  2026-11-21 ("Cut by `docs/RC-CUT-CHECKLIST.md` on the maintainer's go; the tag is `v1.0.0`."). It is 10,638 bytes.
  Its sections:
  - **The village screenshot**, as an absolute `raw.githubusercontent.com` link pinned to `v1.0.0`, with alt text. A
    release page shows the notes outside the repository's tree, so a relative image path would break there.
  - **"What Resurrection is"** and **"What it is built on, and what changed from 1.1.2"**:
    - runtime, security, interface and art, gameplay and hosting, each in a sentence or two;
    - links to `CHANGES-FROM-1.1.2.md` and `SECURITY-STATUS.md`, pinned to `blob/v1.0.0`.
  - **"Install"**, with the host guide pinned to `v1.0.0`.
  - **"Upgrading":**
    - the host guide's section 9 in four steps;
    - from rc.5, rc.4, rc.3 or rc.2 no step runs and the game shows no "Upgrade Needed";
    - from rc.1, `0002-signin-limits` and `0003-utf8-seed-text` run.
  - **"What changed since 1.0.0-rc.5":**
    - task 31's documents;
    - task 32's F1, F3 and F4, each checked against the code at this commit, and F1 and F3 also checked in the rc.5
      upgrade run below;
    - F2 as recorded, not changed;
    - the version line.
  - **"Known issues":** rc.5's list, checked against `ROADMAP.md`.
  - **"Licenses and credits":** the credit in the words of section 4(d) of CC BY-NC-SA 2.0. `test_credit_wording.py`
    passes.
  - **"Status":** not certified for public game hosting; bug fixes only after `1.0.0`.
- **`README.md`:** "`main` is `1.0.0`, the first release, after five test builds; …", with a link to the `1.0.0` notes.
  `SECURITY.md` has had no version line since task 31 and is unchanged.
- **`docs/README.md`** (the index): "Start here" names the `1.0.0` notes. The records list gains the rc.5 notes and this
  document.
- **`CLAUDE.md`:** the current-phase paragraph says that task 33 is done, what the workflow does now, and that task 34
  is next.
- **`ROADMAP.md`, `docs/CHANGES-FROM-1.1.2.md` and `docs/SECURITY-STATUS.md`:**
  - `ROADMAP.md` records the version commit, with task 33's line ticked and "CI acceptance pending".
  - `CHANGES-FROM-1.1.2.md` gains "Phase 6 task 33: the `1.0.0` version commit": no behavior change, no upgrade step, the
    phase check and the workflow change.
  - `SECURITY-STATUS.md`: the CI integrity row says the workflow publishes a pre-release for a test build and the full
    release marked Latest for a release.
- **`git grep 1.0.0-rc.5` at this commit finds only history**, and none of it states the current version:
  - the checklist's records;
  - the rc.5 notes;
  - the evidence documents;
  - `ROADMAP.md`'s and `CHANGES-FROM-1.1.2.md`'s records;
  - `CLAUDE.md`'s list of the five test builds;
  - `VERSIONING.md`'s tag paragraph;
  - the index's entries for rc.5;
  - the `1.0.0` notes' "since 1.0.0-rc.5" and upgrade lines;
  - the two places that name the highest release tag today: `VERSIONING.md`'s and `MODERN-INSTALLATION.md`'s "(today
    `v1.0.0-rc.5`)" on the release upgrade check. They stay true until `v1.0.0` exists, and task 34 updates them.

  No code or test names rc.5.
- **The doc links follow `Version::CURRENT`**, so the pages' links to the host guide now go to
  `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/blob/v1.0.0/docs/HOST-GUIDE.md#…`. They
  resolve once the tag exists.
  - Every anchor the code uses is a heading in `docs/HOST-GUIDE.md` at this commit: `5-install`,
    `53-if-an-install-fails`, `9-upgrade`, `10-backups-and-restore` and `11-mail` (sections 5, 5.3, 9, 10 and 11).
  - `DocLinkTest` and `CommandLinkTest` pass.
  - Every Markdown link in the changed documents names a file that exists at this commit.

## The workflow change (`.github/workflows/release-cut.yml`)

Before this task, the workflow published every cut as a pre-release with `--latest=false` and checked that it was
one. Now the version decides what is published:

- **Preflight** sorts the version before anything is written:
  - `MAJOR.MINOR.PATCH-rc.N` (with N from 1) is a test build;
  - `MAJOR.MINOR.PATCH` is a release;
  - anything else stops the run with "Not a release version (MAJOR.MINOR.PATCH or MAJOR.MINOR.PATCH-rc.N): …".

  The kind goes into `GITHUB_ENV` as `KIND`, beside `TAG`. Preflight's other checks are unchanged: `src/Version.php`
  holds the version, the checkout is the commit, the commit is on `main`, and the historical verifier and the
  validator pass.
- **The step "Publish the pre-release"** is renamed "Publish the release (a pre-release for a test build, the full
  release marked Latest for a release)". It chooses its flags from `KIND` and the version together, so that the two
  must agree:
  - a test build gets `--prerelease --latest=false`, as before;
  - a release gets `--latest` and no `--prerelease`;
  - a release whose version has a `-` in it, or any other combination, stops the step.

  The rest is as before: it refuses if a release for the tag exists ("not touched"), and it creates with
  `--verify-tag`, the title, the notes file taken from the tag, the zip and its `.sha256`.
- **The step "Check the release and download the zip back"** checks:
  - `isPrerelease` is `true` for a test build and `false` for a release;
  - `isDraft` is `false`;
  - `/repos/…/releases/latest`: for a test build it is not the tag (or there is none), as before; for a release it must
    be the tag;
  - the tag, the two assets, their sizes and the downloaded zip's SHA-256, as before.
- **Unchanged:** the inputs and their defaults (still rc.1's values, so a run left at them stops in Preflight before
  writing anything), the message, Tag and package steps, the permissions, and the order of the steps.

### The stand-in runs

Every step, the two publishing steps included, was run in the session, and nothing was pushed or published anywhere
but local stand-ins:

- **The stand-in origin:** a throwaway bare copy of the repository, with `historical-source-1.1.2` and `v1.0.0-rc.1` to
  `v1.0.0-rc.5`, and its `main` set to the candidate `ef3b2b2`. That commit's tree is this commit's except for this
  evidence document's text.
- **The steps:** each step's script was taken from `.github/workflows/release-cut.yml` by parsing it with PyYAML, not
  copied by hand. It ran with GitHub's default shell for run steps, `bash --noprofile --norc -eo pipefail`.
- **Each run** was a fresh clone of the stand-in, fetched with its tags and checked out at the given commit, with
  `GITHUB_ENV` and `RUNNER_TEMP` as the runner gives them. Values a step writes to `GITHUB_ENV` were carried to the
  next step.
- **The publishing steps** ran against a stand-in `gh`: a small script, first on `PATH`, that records every call and
  answers the calls the workflow makes the way GitHub's REST API would, from a local state file. Its first state had
  rc.1 to rc.5 as pre-releases, none of them Latest. It answers:
  - `release view` (plain, and `--json … --jq …` through `jq`);
  - `release create` (`--verify-tag` checked against the stand-in origin; a pre-release with `--latest` refused, as
    GitHub's 422 does; with no `--latest` flag, GitHub's automatic choice);
  - `api …/releases/latest` (404 when no release is Latest);
  - `release download`.

  On request it gives one wrong answer after the create: `isPrerelease` flipped, `isDraft` true, or a wrong
  `/releases/latest`. That shows each check failing.

The harness is in `/mnt/project-files/phase6-task33/workflow/`: `run.py`, `gh` and the logs.

**`1.0.0`, the inputs `1.0.0`, `ef3b2b2…`, `75be9c44…` and `bf51607d…`:** all six steps passed.

- **Preflight:** "1.0.0 is a release"; the historical verifier passed (PASS, 417 files, 11 commits) and so did the
  validator (PASS, 1126 tracked files).
- **The message:** section 3's first block, the same 838 bytes as above; `sha256sum -c` OK.
- **The tag:** `v1.0.0`, annotated (tag object `c0decd4` in the stand-in), on `ef3b2b2`, its message equal to the file
  byte for byte. It was pushed as the one new ref, and the stand-in's tag and peeled commit then matched.
- **The package:** checked out at the tag on a clean tree and built twice: 772 files and 3,913,079 bytes each,
  `bf51607d…c6f` both times, equal to the input.
- **The create call:**

  ```
  release create v1.0.0 --verify-tag --latest --title "Legend of the Green Dragon - Resurrection 1.0.0"
    --notes-file …/notes.md …/legend-of-the-green-dragon-resurrection-1.0.0.zip …/legend-of-the-green-dragon-resurrection-1.0.0.zip.sha256
  ```

  It had no `--prerelease`. The stand-in's state after it: prerelease false, draft false, Latest true, notes 10,638
  bytes (the notes at the tag).
- **The check:** "prerelease: false", "draft: false", "Latest release: v1.0.0", the two assets (3,913,079 and 116
  bytes), and the downloaded zip's `sha256sum -c` OK.

**A rerun with the tag present:** Preflight, the message and the Tag step passed ("v1.0.0 already exists on ef3b2b2…
with the exact message; left as it is."). The tag object was still `c0decd4`, and the package was the same again. With
the release also present, the publish step refused: "A release for v1.0.0 already exists; not touched.".

**The wrong answers, each on a fresh state.** The create succeeds, then the stand-in answers one call wrongly, and the
check step stops at that call:

| Wrong answer | The check step's last call | Result |
| --- | --- | --- |
| `isPrerelease` true | `release view v1.0.0 --json isPrerelease` | exit 1 |
| `isDraft` true | `release view v1.0.0 --json isDraft` | exit 1 |
| `/releases/latest` answers `v1.0.0-rc.5` | `api repos/…/releases/latest --jq .tag_name` | exit 1 |

**The refusals:**

- a wrong message SHA-256 (rc.5's) stops the message step (`FAILED`);
- version `1.0.0-rc.5` with this commit stops Preflight ("src/Version.php is not 1.0.0-rc.5"), and so does version
  `1.0.1` ("… is not 1.0.1");
- the commit input `0dfdb1d` (rc.5's) with the checkout at the candidate stops Preflight, because HEAD is not the
  commit;
- a checkout at `0dfdb1d` with the `1.0.0` inputs stops Preflight ("src/Version.php is not 1.0.0");
- the malformed versions `1.0`, `1.0.0-beta` and `v1.0.0` each stop Preflight ("Not a release version
  (MAJOR.MINOR.PATCH or MAJOR.MINOR.PATCH-rc.N): …") with `GITHUB_ENV` still empty, so nothing is written. The
  stand-in's tags after all of these were the same six as before.
- A wrong package SHA-256 (rc.5's) stops the package step, because the `.sha256` files differ. As in every cut since
  task 25, the Tag step comes before the package, so the tag is already pushed by then. A rerun with the right SHA
  finds the tag, leaves it as it is and goes on (the rerun above). This order is unchanged.

**A test build, `1.0.0-rc.6`,** shows the rc path is as before.

- **The setup:** a scratch commit on the stand-in's `main` only (`1e24b12`: the candidate with `Version::CURRENT` and
  `project.json` set to `1.0.0-rc.6`, the phase `release-candidate`, a `docs/RELEASE-NOTES-1.0.0-rc.6.md` and a section
  3 block). Its message SHA-256 is `6979ddf2…` and its package `8ff0477e…` (3,910,483 bytes).
- **The run:** all six steps passed.
  - Preflight: "1.0.0-rc.6 is a test-build".
  - The create call: `--verify-tag --prerelease --latest=false`.
  - The state: prerelease true, Latest false.
  - The check: "prerelease: true", "Latest release: none", and the download checked.
- **The wrong answers:** `isPrerelease` false stops the check at `--json isPrerelease`, and `/releases/latest` answering
  `v1.0.0-rc.6` stops it at the latest check.

No tag was made in the repository's own clone: its tags are still `historical-source-1.1.2` and `v1.0.0-rc.1` to
`v1.0.0-rc.5`. Nothing went to GitHub but the REST reads in this document.

## The earlier packages

Each was downloaded from its GitHub pre-release into its own empty folder outside the tree, through the REST API
(`gh api …/releases/tags/<tag>`, then each asset with `Accept: application/octet-stream`). Each release is a
pre-release and not a draft:

| | Zip | Size | SHA-256 | Its `.sha256` |
| --- | --- | --- | --- | --- |
| `v1.0.0-rc.1` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip` | 3,626,284 bytes | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.2` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.2.zip` | 3,867,683 bytes | `133625b327d21878c173ac856e5b70b306b69735ca95151e8923cae3030ee439` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.3` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.3.zip` | 3,868,683 bytes | `6fe8b1323f140bdc2b2ef3ce45d7ee0a917f5a40689e7f462b210570d5603569` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.4` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.4.zip` | 3,874,626 bytes | `cb56e85e120b9a0789762f55f509bfc78ae66b3462f6def383da8daed9c797df` | 121 bytes, `sha256sum -c` OK |
| `v1.0.0-rc.5` | `legend-of-the-green-dragon-resurrection-1.0.0-rc.5.zip` | 3,919,931 bytes | `2d8e135e1c4bff0e14b88e32f9fd8dc4cdcf4fc83e69e022e5aca07c4b55a800` | 121 bytes, `sha256sum -c` OK |

All five are the values the brief gives.

## The package, built twice

`python3 scripts/build-release-package.py --out <folder>` was run twice, from a fresh checkout of the candidate, into
two empty folders outside the tree:

| | Folder 1 | Folder 2 |
| --- | --- | --- |
| File | `legend-of-the-green-dragon-resurrection-1.0.0.zip` | the same |
| Entries | 772 files | 772 files |
| Size | 3,913,079 bytes | 3,913,079 bytes |
| SHA-256 | `bf51607d89f77b3591d8436a1946440f3621b9ad90b1aa809d6818b5e97c3c6f` | the same |

- **The two builds:** `cmp` finds them identical, and the `.zip.sha256` files are equal.
- **The layout:** the zip has one top folder, `legend-of-the-green-dragon-resurrection-1.0.0/`, with `upload/`,
  `INSTALL.txt`, `HOST-GUIDE.md`, `RELEASE-NOTES-1.0.0.md`, `THIRD-PARTY-LICENSES.md`, `hosting/`, `LICENSE.txt`,
  `NOTICE` and `README.txt`.
- **Why this document does not change it:** the build reads the files Git lists, with fixed entry times. Beyond the
  host guide and the third-party licenses, the only document it takes from `docs/` is the release notes, so neither
  this evidence document nor the checklist is in it.
- **Before the push**, it was built again, twice, from the commit itself. The result is in the task report.

## The fresh install

The `1.0.0` zip was unpacked into an empty folder. Only its `upload/` was copied, into an empty document root.

- **The setup**, the same for every run below:
  - PHP 8.5.13's built-in server on `127.0.0.1`, as `nobody`;
  - `LOGD_DBCONNECT` pointing at an empty private folder outside the document root;
  - an empty MySQL 8.4 database;
  - mail going nowhere (`sendmail_path=/bin/false`) and errors going to a log file (`log_errors=1`,
    `display_errors=0`, `error_reporting=-1`).
- **The browser stand-in:** every page was reached by the previous page's own links or forms, every redirect was
  followed by its Location header, and every request's status, title, navigation links and forms were logged. The
  fixtures set state only, each named where it is used. The logs are in `/mnt/project-files/phase6-task33/play/`.
- **`setup.php`:**
  - Its checks were OK or Note: "PHP version OK 8.5.13 (8.4 or 8.5 needed)", the extensions, `memory_limit`,
    `max_execution_time` 30 seconds as a note, `set_time_limit()`, the configuration folder, and HTTPS (this request
    comes from this machine).
  - The token file was written beside the configuration path. The install form took the database, the first
    administrator and the recommended modules.
- **The result:** "The game is installed. The configuration file was written to …/private/dbconnect.php (0600). 41
  tables were created; 24 modules installed, 23 active. The first administrator is Admin. The token file was deleted,
  and it cannot be used again."
  - The last screen's maintenance choice: cron ("Web maintenance stays off.").
  - The token file was gone, and `setup.php` then answered 404.
- **The game:**
  - The home page reads "Version: Resurrection 1.0.0, based on LoGD 1.1.2 Dragonprime Edition".
  - Admin played to the village: the sign-in form (303 to `village.php`, 302 to `newday.php`), then the new day (race,
    specialty, "It is a new day!"), then "Degolburg Square", whose footer reads 1.0.0.
- **The database:** 41 tables; 24 modules, 23 active; master 5 is `556EC3A96C697468` ("Unélith"); `upgrade_steps` holds
  `0001-account-mail`, `0002-signin-limits` and `0003-utf8-seed-text`.
- **The PHP error log** stayed empty (0 bytes).

## The upgrade from rc.5, by the player's path

This run follows a player whose fight is kept over the upgrade. It hits F1 (the failed flee's text) and F3 ("1 use
left"), and plays the fight to its end. Logs: `rc5ab.log` and `rc5cd.log`.

### On rc.5: the player, and the kept fight

1. **The install:** the rc.5 package's `upload/`, installed through its own `setup.php` on an empty database: 41
   tables, 24 modules, 23 active, steps 0001 to 0003. The home page reads 1.0.0-rc.5.
2. **The character:** Shadow, made through the sign-up form (`create.php`, "Your account was created."), signed in
   through the home page's form and taken through the new day: race Human, specialty Dark Arts, "It is a new day!", and
   then the village, whose footer reads 1.0.0-rc.5.
3. **The fixture:** Dark Arts skill 1 and uses 2, so that Skeleton Crew (level 1) can be used once with one use left
   after it. Attack 3, defense 15 and hitpoints 150 of 150, a weak blow so that the fight lasts several rounds.
4. **The fight:** the forest, by the village's link; then the forest page's search link
   (`forest.php?op=search&c=13-…`), which shows the search form; then that form.
   - A second fixture kept the search from finding an event: `forestchance` was set to 0 for that one request and put
     back to 15.
   - The search found a Batling, with the fight's forms (Fight, Run, specialty).
   - The stored enemy has MD5 `7def20b4…4578` and the restore page `forest.php?op=search`.
5. **The backup:** a dump of the database (155,526 bytes). rc.5's error log was empty.

### On 1.0.0: the swap, and the fight to its end

1. **The swap:** the server stopped. The dump was restored, and the restored database had the same counts, the same
   stored fight and Dark Arts uses 2. Then `1.0.0`'s `upload/` was copied into a new document root and served with the
   same configuration file.
2. **The game's state:**
   - The home page reads "Version: Resurrection 1.0.0, based on LoGD 1.1.2 Dragonprime Edition", with no "Upgrade
     Needed".
   - `setup.php` answers 404.
   - `php scripts/upgrade.php --dry-run` and then `--backup-confirmed`, run as `nobody` in the new folder, each give
     "The database is current: 3 of 3 upgrade steps applied, and every installed bundled module is at its version.
     Nothing was changed.", exit 0. The counts are unchanged.
3. **Sign-in:** with a new browser, through the home page's form: 303 to the village, 302 to `badnav.php`, 200 with the
   kept fight. Its forms are Fight, Run and specialty, and the abilities note reads "2 uses left".
4. **The forest's search link,** as the forest page loaded before the search gave it (a second tab or the back
   button): 302 to `badnav.php`, which shows the kept fight again. The stored fight's MD5 is unchanged, so no new fight
   was drawn.
5. **Run, press 1:** a failed flee. The message has 1.1.2's markup (centered, bold, the `colLtRed` class), with no
   "`` `\ ``" in the page's text and no "`\$You failed`" in its source. The fight's forms follow.
6. **Skeleton Crew (1),** the one ability offered: the note now reads "1 use left". "1 uses left" appears nowhere, and
   the stored uses are 1.
7. **Fight, three presses:** "You have slain Batling!", with the forest's and the village's links.
8. **The village,** by that link: "Degolburg Square", "Version: Resurrection 1.0.0, based on LoGD 1.1.2 Dragonprime
   Edition".
9. **The PHP error log:** empty (0 bytes), on the first attempt.

## The upgrades from rc.4, rc.3 and rc.2

Each package's `upload/` was installed the same way, on an empty MySQL 8.4 database, through its own `setup.php`. Each
gave 41 tables, 24 modules (23 active), `upgrade_steps` 0001 to 0003, and a home page reading 1.0.0-rc.4, 1.0.0-rc.3 or
1.0.0-rc.2. Admin signed in and played through the new day to the village, and the error log stayed empty.

Then the server stopped, and `1.0.0`'s `upload/` was copied into a new document root and served with the same
configuration file:

- The home page reads 1.0.0, with no "Upgrade Needed", and `setup.php` answers 404.
- `php scripts/upgrade.php --dry-run` and then `--backup-confirmed`, run as `nobody`, each give "The database is
  current: 3 of 3 upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.",
  exit 0. No step runs, and there are still 41 tables.
- Admin signed in: 303 to the village, 302 to `badnav.php`, then the kept village page, whose footer still reads the
  old version because it is the page saved before the swap. Through its forest link and the forest's village link,
  Degolburg Square reads 1.0.0.
- The PHP error log stayed empty.

The log is `older-1.0.0-rc.4-1.0.0-rc.3-1.0.0-rc.2.log`.

## The upgrade from rc.1

The log is `older-1.0.0-rc.1.log`.

- **The install:** the rc.1 package, whose zip holds the upload itself, was installed the same way on an empty MySQL
  8.4 database through its own `setup.php`. That gave 41 tables and 24 modules (23 active), and the home page reads
  1.0.0-rc.1. Admin played to the village, and the error log was empty.
- **Before the upgrade:**
  - master 5 is `556EEFBFBD6C697468`, with the U+FFFD that rc.1 wrote;
  - `upgrade_steps` holds only 0001;
  - the nasty word list holds `kyrp` with U+FFFD;
  - there are no sign-in limit settings.
- **The swap:** the server stopped, and `1.0.0`'s `upload/` was served with the same configuration file. The home page
  shows "Upgrade Needed".
- **`setup.php`'s upgrade:**
  - Its first screen showed the preflight checks.
  - The next screen listed, as its dry run, "step 0002-signin-limits: Add the sign-in limit settings with their
    defaults …" and "step 0003-utf8-seed-text: Write the UTF-8 text 1.1.2 meant into three seed values …".
  - With a new token file, the Admin login and password and the backup box: "The upgrade is complete. The database is
    current. Applied 2 of 2.", recording each step ("applied and recorded step 0002-signin-limits", "… 0003-utf8-seed-text")
    and "Upgrade complete: applied 2 upgrade step(s) and 0 module upgrade(s)". The token file was deleted.
- **After the upgrade:**
  - master 5 is `556EC3A96C697468` ("Unélith");
  - `upgrade_steps` holds 0001, 0002 and 0003;
  - the nasty word list holds "kyrpä*" and no U+FFFD;
  - the sign-in limits are `signin_fail_pair` 5, `signin_fail_address` 20 and `signin_fail_account` 50;
  - there are 41 tables, and `setup.php` answers 404.
- **The game after it:**
  - Admin signed in: the kept village, then, after the forest and back, the village with the 1.0.0 footer.
  - The Masters Editor, through the Superuser Grotto's link, lists "5 Unélith", with no U+FFFD on the page.
  - Then back to the village.
  - The PHP error log stayed empty.

## Found, not changed

- **The home page shows 1.1.2's default login banner**, "\*BETA\* This is a BETA of this website…". It is the
  `loginbanner` setting's default, 1.1.2's text, and an operator sets it in Game Settings. It is not a version string,
  and gameplay and text change only when a task says so, so it stays. For Jim to know before the cut.
- **The package's `INSTALL.txt` for a release** opens "A release of Legend of the Green Dragon - Resurrection." and
  has no line on review or public hosting. For a test build, the same place says it "has had no independent security
  review, and it is not certified for public game hosting" (`install_text()` in `scripts/build-release-package.py`,
  written that way since task 16). For a release, the same statement is in places that ship with it or that it links
  to: the release notes' "Status" (in the package), the tag message, `README.md`, and the host guide's opening
  paragraph, "Public hosting is yours to decide" (in the package). The build script is unchanged, so the package
  tested here is the one Jim's cut builds. Adding the line is a one-line change for Jim to decide, in task 34 or later.
- **A wrong package SHA-256 stops the cut after the tag is pushed** (the refusals above). The order has been unchanged
  since task 25: the package is built from the tag, so the tag must exist first. A rerun with the right value goes on.
- **The first page after a sign-in on an upgraded game is the page saved before the swap,** footer included, until the
  next link. This is how the kept page has always worked (`badnav.php`), and it was seen in every upgrade above.
- **On PHP's built-in server only, `index.php` with no query string logs a warning** for 1.1.2's `QUERY_STRING` line.
  A real web server always sets that variable. The runs above check that a server is up by connecting to its port, so
  their error logs stayed empty.

## Local results

These are supporting evidence only; CI is the acceptance. They were run on the candidate `ef3b2b2`, whose tree is this
commit's except for this evidence document's text.

- **The tooling tests:** `test_version.py` (the new pinned value and the phase test), `test_no_bare_refusals.py`,
  `test_doc_links.py`, `test_credit_wording.py`, `test_release_package.py`, `test_host_guide.py`,
  `test_module_author_guide.py` and `test_utf8_text.py`: 52 tests, OK on PHP 8.4.28 and on PHP 8.5.13.
- **`DocLinkTest` and `CommandLinkTest`:** 4 tests and 75 assertions, OK on both.
- **PHPUnit,** 533 tests and 152,523 assertions, with `failOnSkipped`: OK on PHP 8.5.13 with MySQL 8.4, and on PHP
  8.4.28 with MariaDB 11.4.
- **All five shards on both legs,** with the local extras hidden (`PLAYWRIGHT_BROWSERS_PATH` an empty folder,
  `RESURRECTION_REQUIRE_PHP=1`). Each ran on a fresh database installed by `FreshInstallTest`, and each leg's five ran
  side by side:

  | Shard | Tests run | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
  | --- | --- | --- | --- |
  | 1 | 251 | OK, 1038 s | OK, 1201 s |
  | 2 | 67 (66 + the prerequisite) | OK, 1070 s | OK, 1235 s |
  | 3 | 68 (67 + the prerequisite) | OK, 1044 s | OK, 1197 s |
  | 4 | 68 (67 + the prerequisite) | OK, 1040 s | OK, 1187 s |
  | 5 | 69 (68 + the prerequisite) | OK, 1037 s | OK, 1189 s |

  519 assigned tests on each leg; 0 failures, 0 errors, 0 skips.

- **`scripts/release-upgrade-check.py`,** in both modes, each on an empty database, on both legs (PHP 8.4.28 / MariaDB
  11.4 and PHP 8.5.13 / MySQL 8.4):
  - Default mode: "Release upgrade check: v1.0.0-rc.5 installed, upgraded to ef3b2b2, and the same schema and data as a
    fresh install (41 tables)". `v1.0.0-rc.5` is the highest release tag before this commit.
  - `--release-ref HEAD`: "HEAD installed, upgraded to ef3b2b2, and the same schema and data as a fresh install (41
    tables)".
- **The other checks:**

  - PHP lint: 480 files, 0 failures, on 8.4 and on 8.5.
  - Both PHPStan gates (`phpstan.neon` and `phpstan-new.neon`): no errors, on 8.4 and on 8.5.
  - `composer validate --strict`: valid. `composer install`: done. `composer audit`: no advisories.
  - The historical verifier: PASS, 417 files, 11 commits. The project validator: PASS, 1126 tracked files.
  - `scripts/asset-versions.py --check`: exit 0. `git diff --check 3500e44..`: clean.
  - Baseline integrity's three steps, run as it runs them in a clean clone with no `vendor/`, on PHP 8.3.6 with no
    database: the verifier and the validator passed, and `python3 -m unittest discover -s tests/tooling` ran 223 tests,
    OK, with 29 skipped. Those 29 are the tests that need PHP 8.4 or the database, and each of them runs, unskipped, in
    its Modern core shard.
  - `LICENSE.txt` and `README.txt` are identical to `historical-source-1.1.2`.
  - The logs are in `/mnt/project-files/phase6-task33/local/`.

## Screenshots

There are two sheets, in `/mnt/project-files/phase6-task33/`.

**`sheet1-release-notes.png`:** `docs/RELEASE-NOTES-1.0.0.md` rendered as GitHub-flavored Markdown, as the release page
will show it.

- GitHub's own Markdown API refuses these sessions. So it was rendered with `markdown-it-py`'s GFM preset and GitHub's
  Markdown stylesheet (`github-markdown-css` from the npm registry), at desktop width, in three columns.
- The village image's link is pinned to `v1.0.0`, which does not exist yet. For the picture only, the link was routed
  to the same file at this commit, `docs/images/village.png`, and the caption says so.

**`sheet2-footer-and-home-after-rc5-upgrade.png`:** after the rc.5 upgrade above, the home page at desktop width (1280
px) and at 390 px, each with a crop of the footer.

- The footer reads "Version: Resurrection 1.0.0, based on LoGD 1.1.2 Dragonprime Edition", with the 1.1.2 credit line
  and, on its own line under it, "A Phoenix 2:33 LLC Project by Jim Lunsford".
- At 390 px the full-page capture shows the phone's fixed navigation bar part way down the page. That is where the
  viewport ended when the page was captured, not a fault in the layout.

## Historical integrity

- `historical-source-1.1.2` is tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, on commit
  `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, with tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`.
- The verifier passes: 417 historical files, 11 preservation commits.
- `LICENSE.txt` and `README.txt` are byte-identical to the tag.
- No file is labeled AGPL.

## Next

The next step is CI acceptance of this commit. Then Jim runs the Release cut workflow from the Actions tab, with the
four inputs in the task report (the checklist's sections 3 and 4). It makes the tag `v1.0.0` and the full release
marked Latest, and checks both. Then task 34 sets `main` to `1.0.1-dev` and records the cut. That commit's Modern core
installs `v1.0.0` and upgrades it (the checklist's section 5).
