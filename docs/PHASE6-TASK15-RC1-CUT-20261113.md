# Phase 6 task 15: the `v1.0.0-rc.1` tag, the package and the GitHub pre-release (2026-11-13)

Jim's go of 2026-11-13: cut `1.0.0-rc.1`, tag first; the package goes out as a GitHub pre-release (his answer "A"). This
lifted the no-tag and no-release rule for exactly the annotated tag `v1.0.0-rc.1` on `2bbc69d` and one GitHub
pre-release with that tag, the zip and its `.sha256`. Nothing was deployed and no server was touched.
`historical-source-1.1.2` is unchanged.

| | |
| --- | --- |
| Tag | `v1.0.0-rc.1`, annotated, tag object `522be1c1dfbbb3fe020ebd6586c7b756372d233b` |
| Tagged commit | `2bbc69dd50cb62ba3126bc05390b1f84ce34e507` (the version commit, task 14) |
| Accepted runs on that commit | Modern core 43449051336 (SUCCESS, all 14 jobs), Baseline integrity 43449051323 (SUCCESS) |
| Release | https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.1 (pre-release, not latest) |
| Package | `legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip`: 753 files, 3,626,284 bytes |
| Package SHA-256 | `f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857` |
| Made by | the Release cut workflow, run 43458915150 (job 132000316774), from `main` at `c6d4677`, started by Jim |

## Why the tag and the release were made by a workflow

The brief has the session tag and push from its own clone and publish with `gh`. The session could not:

- `git push origin v1.0.0-rc.1` (from the session, tag object `a92584e`, made locally with the exact message) was refused by
  the session's network proxy: `error: RPC failed; HTTP 403`. Not retried.
- On Jim's choice (decision card, "Tag and release via the REST API"), `POST /repos/.../git/tags` was refused by the same
  proxy: "Write access to this GitHub API path is not permitted through this proxy." Not retried. Nothing was created.
- GraphQL is refused there as well, which `gh release create --verify-tag` and `gh release list` use.

Jim has no clone or terminal of his own. On his second choice (decision card, "One-click workflow (annotated tag)") the
session pushed `.github/workflows/release-cut.yml` to `main` in its own commit, `c6d4677`, with the workflow inventory in
`tests/tooling/test_tooling_shards.py` updated to list it (accepted on its own runs: Modern core 43458779026, all 14
jobs on `ubuntu-24.04`, PHP 8.4 shards 13:28, 14:35, 14:00, 17:25, 10:58, PHP 8.5 shards 15:09, 13:00, 15:51, 17:30,
17:51; Baseline integrity 43458778969). Jim started it once from the Actions tab. The local tag
`a92584e` was never pushed and was deleted from the session's clone before the workflow ran. The tag on GitHub is the
workflow's, `522be1c`, with the same object, type and message. Only its tagger differs: `github-actions[bot]` instead of
the session.

The workflow (`workflow_dispatch` only, `main` only, `contents: write` for its one job) does the brief's steps 1 to 3
and 5 in the brief's order, and stops at the first failure:

1. Preflight: the inputs' shapes; the checkout is the commit; the commit is on `main`; `src/Version.php` is the
   version; the release notes exist; `historical-source-1.1.2` is tag object `51cab4f…` on commit `bdc29df…`; the
   historical verifier and the project validator pass; the tree is clean.
2. The tag message: section 3 of `docs/RC-CUT-CHECKLIST.md` at the commit, written to a file outside the tree; its first
   line names the version; its SHA-256 must be the input's (`41bf462a…090bd`, the session's copy of the same text).
3. The tag: `git tag -a v1.0.0-rc.1 2bbc69d -F <file>`, the message compared byte for byte, `git push origin
   refs/tags/v1.0.0-rc.1` (that one ref, no `--tags`, no force), then the remote's tag object and peeled commit checked.
   A tag already on the remote is accepted only if it is exactly that tag, and is never changed.
4. The package: `git checkout --detach v1.0.0-rc.1` on a clean tree, built twice into two folders outside it, the two
   zips compared byte for byte and the hash compared with the input (`f8b0a1d4…7857`, task 14's build).
5. The release, only if none exists for the tag: `gh release create v1.0.0-rc.1 --verify-tag --prerelease --latest=false
   --title "Legend of the Green Dragon - Resurrection 1.0.0-rc.1" --notes-file <docs/RELEASE-NOTES-1.0.0-rc.1.md from the
   tag>` with the zip and its `.sha256`; then `gh release view`, the asset count, names and sizes, the latest release
   not this one, and the zip downloaded back and checked.

Before it was pushed, steps 1 to 4 ran in the session against a throwaway bare copy of the repository standing in for
`origin`: all passed, the copy got a tag on `2bbc69d` with the exact message, both builds came out
`f8b0a1d4…7857`, and a rerun found the tag and left it as it was. The publishing steps could not run there.

## 1. Preflight (the session, before any tag)

```
$ git ls-remote origin refs/heads/main
2bbc69dd50cb62ba3126bc05390b1f84ce34e507	refs/heads/main
$ git tag -l v1.0.0-rc.1                       # nothing
$ git ls-remote origin 'refs/tags/*'
51cab4fbe58a234651a3177a56289b18bc152b4d	refs/tags/historical-source-1.1.2
bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2	refs/tags/historical-source-1.1.2^{}
$ git rev-parse historical-source-1.1.2 historical-source-1.1.2^{commit} historical-source-1.1.2^{tree}
51cab4fbe58a234651a3177a56289b18bc152b4d
bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2
4013a0ccc5227e87cd7a22de00b7c322d7aa237c
$ python3 scripts/verify-historical-baseline.py    # "status": "PASS", 417 files, 11 historical commits
$ python3 scripts/validate-project.py              # "status": "PASS", 1064 tracked files
$ python3 -m unittest tests/tooling/test_version.py -v   # Ran 3 tests ... OK
$ curl -sS https://api.github.com/repos/jimlunsford/legend-of-the-green-dragon-resurrection   # .permissions
{'admin': True, 'maintain': True, 'push': True, 'triage': True, 'pull': True}
```

`gh auth status` reports the session's `GH_TOKEN` as invalid. The token Jim added is put on requests by the network
proxy, not held in the variable, so `gh auth status` cannot see it. The API's own answer above is the check of push
rights.

## 2. The tag

The workflow's `Tag` step (run 43458915150):

```
To https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection
 * [new tag]         v1.0.0-rc.1 -> v1.0.0-rc.1
Tag object: 522be1c1dfbbb3fe020ebd6586c7b756372d233b
object 2bbc69dd50cb62ba3126bc05390b1f84ce34e507
type commit
tag v1.0.0-rc.1
tagger github-actions[bot] <41898099+github-actions[bot]@users.noreply.github.com> 1794585854 +0000

Legend of the Green Dragon - Resurrection 1.0.0-rc.1
(the rest of section 3's message, byte for byte)
Remote:
522be1c1dfbbb3fe020ebd6586c7b756372d233b	refs/tags/v1.0.0-rc.1
2bbc69dd50cb62ba3126bc05390b1f84ce34e507	refs/tags/v1.0.0-rc.1^{}
```

Checked again from the session afterwards:

```
$ git ls-remote --tags origin
51cab4fbe58a234651a3177a56289b18bc152b4d	refs/tags/historical-source-1.1.2
bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2	refs/tags/historical-source-1.1.2^{}
522be1c1dfbbb3fe020ebd6586c7b756372d233b	refs/tags/v1.0.0-rc.1
2bbc69dd50cb62ba3126bc05390b1f84ce34e507	refs/tags/v1.0.0-rc.1^{}
$ git fetch origin refs/tags/v1.0.0-rc.1:refs/tags/v1.0.0-rc.1
$ git cat-file -t v1.0.0-rc.1                 # tag
$ git rev-parse v1.0.0-rc.1^{commit}          # 2bbc69dd50cb62ba3126bc05390b1f84ce34e507
$ git cat-file -p v1.0.0-rc.1 | sed '1,/^$/d' | cmp - <section 3's message, extracted at 2bbc69d>   # identical
```

The message file: 772 bytes, SHA-256 `41bf462a2044affbe85eb9b40d25f76c27e2ed5caf92d42ee8876f5311a090bd`.

## 3. The package

Built twice by the session from a clean worktree of the tag (`git worktree add --detach <folder> v1.0.0-rc.1`, HEAD
`2bbc69d`, no changes), into two folders outside the tree:

```
$ python3 scripts/build-release-package.py --out <scratch>/build1
{"zip": ".../build1/legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip", "files": 753, "bytes": 3626284, "sha256": "f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857"}
$ python3 scripts/build-release-package.py --out <scratch>/build2
{"zip": ".../build2/legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip", "files": 753, "bytes": 3626284, "sha256": "f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857"}
$ cmp build1/*.zip build2/*.zip                # identical
```

The workflow's two builds on the runner, from the pushed tag:

```
{"zip": "/home/runner/work/_temp/build1/legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip", "files": 753, "bytes": 3626284, "sha256": "f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857"}
{"zip": "/home/runner/work/_temp/build2/legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip", "files": 753, "bytes": 3626284, "sha256": "f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857"}
```

All four match each other and task 14's two builds (`docs/PHASE6-TASK14-RC1-VERSION-20261113.md`: 753 files,
3626284 bytes, `f8b0a1d4…7857`). No mismatch.

## 4. The install check

The session's first build, unzipped into an empty docroot and served as a host without a shell would use it:

- PHP 8.4.27 (`php -S 127.0.0.1:8099 -t <docroot>`, running as `nobody`; the docroot owned by root, so PHP could not
  write it), MariaDB 11.4.13 (a disposable container), `LOGD_DBCONNECT` naming `dbconnect.php` in a private folder
  outside the docroot, and PHP's `sendmail_path` pointed at a capture program that records and sends nothing.
- `setup.php`: the checks all OK (plain HTTP accepted because the request came from the machine itself), the token
  step, `resurrection-setup.php` created beside the configuration file (the guard line and the suggested
  `rzsetup-` value, mode 0600), the token accepted, the install form with the recommended modules. Answer: "The game is
  installed. The configuration file was written to /srv/rc1/private/dbconnect.php (0600). 41 tables were created; 24
  modules installed, 23 active. The first administrator is Admin. The token file was deleted, and it cannot be used
  again." Maintenance choice: the scheduler (`php cron.php`), web maintenance off. The token file was gone afterwards.
- Played in Chromium (Playwright): signed in as the administrator, the first New Day's questions (race, specialty,
  Begin New Day), "It is day number 1", the village (Degolburg Square), the forest, "Look for Something to Kill", and
  one fight to its end: a level 1 administrator met a level 2 Slithering Asp and lost after 10 rounds ("Defeat. All
  gold on hand has been lost! 10 % of experience has been lost! You may begin fighting again tomorrow."). The defeat is
  the game's ordinary result for that fight. No console errors, no PHP errors in the error log, no request with an
  error status in the clean run.
- The footer on every page: "Version: Resurrection 1.0.0-rc.1, based on LoGD 1.1.2 Dragonprime Edition", with the
  1.1.2 credits, the license block and the attribution line.
- Mail: the capture recorded no message (account mail is off after an install, and nothing in the run sends mail).

The first try of the play script did not know the New Day questions are form buttons. It left the account part way
through them. Two later tries replayed `forest.php?op=search` with stale forms (two 409s in the server log), and
between them the account's row was edited by hand in the database. Those tries were thrown away: the database was
dropped, the configuration file removed, and the install and the play above were run again from the start on the same
unzipped docroot.

## 5. The GitHub pre-release

The workflow's checks (run 43458915150):

```
$ gh release view v1.0.0-rc.1
title:	Legend of the Green Dragon - Resurrection 1.0.0-rc.1
tag:	v1.0.0-rc.1
draft:	false
prerelease:	true
immutable:	false
author:	github-actions[bot]
created:	2026-11-13T16:04:14Z
published:	2026-11-13T16:04:19Z
url:	https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.1
asset:	legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip
asset:	legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip.sha256
(the release notes, docs/RELEASE-NOTES-1.0.0-rc.1.md from the tag)
$ gh release view v1.0.0-rc.1 --json tagName,name,isPrerelease,isDraft,url,assets
{"assets":[{"name":"legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip","size":3626284},{"name":"legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip.sha256","size":121}],"isDraft":false,"isPrerelease":true,"name":"Legend of the Green Dragon - Resurrection 1.0.0-rc.1","tagName":"v1.0.0-rc.1","url":"https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/releases/tag/v1.0.0-rc.1"}
$ gh api repos/.../releases/latest           # 404 Not Found: there is no latest release, so not this one
$ gh release download v1.0.0-rc.1 --dir <new folder>; sha256sum -c
f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857  legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip
legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip: OK
```

Checked again from the session, through the REST API and a download into a new, empty folder:

```
GET /repos/.../releases/tags/v1.0.0-rc.1
  tag_name v1.0.0-rc.1, prerelease true, draft false,
  name "Legend of the Green Dragon - Resurrection 1.0.0-rc.1", published 2026-11-13T16:04:19Z
  legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip         3626284  sha256:f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857
  legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip.sha256  121      sha256:3a1d00c4805b93fa87befb2bb32c4209e1d32e9d00f30b7a53d75ba19989bf0a
GET /repos/.../releases/latest               404
$ curl -L -o <new folder>/... https://github.com/.../releases/download/v1.0.0-rc.1/<name>   # 200 for both
$ sha256sum legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip
f8b0a1d4f0bb153f649ff849972bb4a8649efd216ed0fec22bb88001cb4a7857  legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip
$ sha256sum -c legend-of-the-green-dragon-resurrection-1.0.0-rc.1.zip.sha256   # OK
$ cmp <download>/*.zip <build1>/*.zip        # identical
```

The release's `target_commitish` reads `main`, the field's default. The tag already existed on `2bbc69d` when the
release was made, so the release is on the tag, and the tag is on `2bbc69d`.

## 6. Records

- `docs/RC-CUT-CHECKLIST.md`: sections 2, 3 and 4 done for `1.0.0-rc.1`, with the runs, the tag and the package.
- `ROADMAP.md`: task 14 accepted; task 15 with the tag, its object and commit, the accepted runs, the release, and the
  package's file count, size and SHA-256; the cut sequence done; next, Jim's test of rc.1 on a real server.
- `docs/VERSIONING.md`: unchanged. Its line on `main` after a tag ("After a test build's tag (`v1.0.0-rc.N`), `main`
  keeps `1.0.0-rc.N`") is now true for rc.1: `main` stays `1.0.0-rc.1` (`src/Version.php`, `project.json`), and its
  sentence on the rc.1 tag says what happened.
- The records commit changes docs only. Section 5's first box (the next commit's Modern core installs `v1.0.0-rc.1` and
  upgrades it) is checked on that commit's run and recorded when it is accepted.

## Next

Jim tests rc.1 on a real server, from the release's zip, following `docs/HOST-GUIDE.md`. A fix found there goes into
`main` and a later cut (`1.0.0-rc.2`); the `v1.0.0-rc.1` tag never moves.
