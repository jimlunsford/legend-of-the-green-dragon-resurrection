# Phase 6 task 1: release readiness review for 1.0.0-rc.1 (2026-11-06)

Jim's brief, "PHASE 6 TASK 1: RELEASE READINESS REVIEW (1.0.0-rc.1)". Expected start: `main` at
`4f66f61a919a550337b49e383db8dbd6af6d530c` (it matched). This task changes documents only: no code, test, setting,
version string, tag or release. Phase 5's acceptance is recorded first, in `PHASE5-CLOSEOUT-20261105.md`,
`PHASE5-SUMMARY-20261105.md` and `ROADMAP.md`.

Phase 6, "Release readiness", was chosen by Jim on 2026-11-05. Its goal: find and fix what stands between `main` and a
first release candidate, `1.0.0-rc.1`. The release itself (tag, GitHub release, hosting, deploy) stays excluded until
Jim says go. This review is task 1; the later tasks come from the gap list at the end.

## Verdict

`main` at `4f66f61` is **not ready to cut 1.0.0-rc.1**. Four BLOCKERs stand in the way, all small or medium:

1. Two runtime 500s the UI crawl found: the Warriors Online list (`list.php`, linked from the home page for everyone)
   whenever a dead player is online, and two Stats pages on MySQL 8.4.
2. No guide for hosting the game: the only install document is for a local, disposable install.
3. Front-door documents that say false things today (`README.md`, `VERSIONING.md`, `SECURITY-STATUS.md`).
4. The cut materials do not exist yet: release notes, the tag message, and a rule for `main`'s version after the tag.

Everything else checked passes, or is a SHOULD (13) or NICE (10) item. The install, upgrade path, licensing text,
credits, CSRF coverage, password hashing, disabled surfaces, gameplay tests and CI are in order.

## How this review was done

Every result below was checked on 2026-11-06; nothing is assumed from earlier records unless it says so.

- **Machine.** This session's container: PHP 8.5.12 and 8.4.27 (the setup-php builds), MySQL 8.4 and MariaDB 11.4 in
  Docker, Composer from the lock file. Local results are supporting evidence; CI is the acceptance.
- **Fresh install, as a new host would.** A clean `git clone` of `main`, then `python3 scripts/local-play.py up`
  exactly as `docs/MODERN-INSTALLATION.md` says; separately, the manual path (`php scripts/install.php
  --write-config ...`) into an empty MySQL 8.4 database, served by `php -S` and, once, by Apache 2.4 (the
  `php:8.4-apache` image with `php.ini-production`).
- **UI crawl.** A Playwright crawler (Chromium) with axe-core 4.10.3 on the manual install with the recommended
  modules. Four roles: signed out, a living player, a dead player, a superuser. From each role's start pages it
  followed every link breadth first, skipping links that would delete, buy, sell, fight a round or log out, and
  loaded each page at 1440x1000 and 390x844. Before each load it set the account's allowed navigation to the target
  (the recipe the Phase 5 screenshots used), so each page renders as if reached by its link. It measured sideways
  scroll, tap targets under 44px (at 390), inputs without a label, inline `style` attributes and `<font>`/`<center>`,
  the scene image, broken images, the page generation time from the footer, and axe's `color-contrast`, `label`,
  `select-name`, `button-name`, `link-name`, `image-alt` and `target-size` rules.
- **Pages crawled:** signed out 22, living player 87, dead player 29, superuser 386 with the installed
  administrator's flags plus 400 more with every editor flag (section 10, "Installed administrator", says why), about 920 page addresses, each at
  both widths.
- **No penetration testing and no scanning outside this repository.** The security section is a review of the code
  and of responses from the local install.

## The checklist

Verdicts: PASS, GAP (something missing), RISK (works, but can fail or mislead). Severity for GAP and RISK: BLOCKER
for rc.1, SHOULD before 1.0.0, NICE later. Task IDs (B, S, N) are in the gap list at the end.

| # | Item | Verdict | Severity | Evidence (detail in the sections below) | Task |
|---|---|---|---|---|---|
| 1.1 | Fresh install by the documented local path | PASS | | Clean clone, `local-play.py up`: installed and running in 29 s; 39 tables, 24 modules, 23 active, 1 administrator | |
| 1.2 | Manual CLI install | PASS | | `install.php --write-config`: same counts, config written 0600 | |
| 1.3 | A guide for hosting on a web server | GAP | BLOCKER | `MODERN-INSTALLATION.md` covers a local, disposable install only; nothing on docroot, internal-file rules, ownership, TLS, php.ini, cron entry, mail | B2 |
| 1.4 | Internal PHP files answer direct requests | RISK | SHOULD | 94 of 273 internal files return 500 and log a fatal when requested directly; `scripts/*.php` 404 as designed | B2, S2 |
| 1.5 | Config file readable by the web server | RISK | SHOULD | Apache as `www-data` and a 0600 `dbconnect.php` owned by the installing user: every page a blank 500 | B2, S4 |
| 1.6 | Shared hosting with no shell | GAP | SHOULD | Install, upgrade and maintenance are CLI only by design; options below | S3 |
| 1.7 | What to upload, extensions, settings | GAP | SHOULD | `vendor/` not needed at runtime; `ctype` and `filter` are used but not in `composer.json` | B2, S6 |
| 2.1 | Upgrade path from rc.1 | PASS | | `scripts/release-upgrade-check.py` (both modes in CI); semver order puts `1.0.0` after its rcs | |
| 2.2 | Backup and restore guidance | PASS | | `MODERN-INSTALLATION.md` "Upgrading"; `local-play.py backup`, `restore`, `up` and `cron.php` run end to end | |
| 3.1 | What the cut changes | GAP | BLOCKER | Listed below; no release notes, no tag message, no rule for `main`'s version after the tag | B4 |
| 4.1 | License and credit files | PASS | | `LICENSE.txt`, `README.txt` byte-identical to the tag (test); `NOTICE`, `assets/LICENSE`, three `OFL.txt`, `THIRD-PARTY-LICENSES.md` | |
| 4.2 | Credit line, attribution line, safe frame | PASS | | Every crawled page and popup carries both; checks and safe frame held by `test_safe_frame.py`, `test_ui_template.py` | |
| 4.3 | Source on request (1.1.2's license) | PASS | | "View PHP Source" in every footer, to `sourceurl` (default the public repository, which is public) | B2 |
| 4.4 | AGPL for bundled code | RISK | not a BLOCKER | No file is AGPL; nothing to decide for rc.1 | Jim and a lawyer |
| 4.5 | Attribution wording, legal review | RISK | SHOULD | Pending; reasoning below | Jim and a lawyer |
| 5.1 | Secrets and config permissions | PASS | | No secret in the tracked tree; config written 0600; `.gitignore` covers configs, keys, dumps | |
| 5.2 | Sessions, cookies, CSRF, passwords | PASS | | Strict cookie-only sessions, HttpOnly, SameSite=Lax, Secure on HTTPS; route inventory 0 OPEN; bcrypt with a 72-byte cap | |
| 5.3 | HTTP security headers | GAP | SHOULD | None sent: no frame protection, no `nosniff`, no Referrer-Policy | S1 |
| 5.4 | Content Security Policy | GAP | SHOULD | Not possible yet: about 20 inline scripts and 5 inline handlers remain | S1 |
| 5.5 | Disabled surfaces | PASS | | 404 and no links (`test_disabled_endpoints.py`); no `eval` left | |
| 5.6 | `composer audit` | PASS | | No advisories | |
| 6.1 | Sideways scroll | PASS | | 0 of about 920 pages at 1440 or 390 | |
| 6.2 | Missing scenes, broken images | PASS | | Every player place shows its scene; popups and staff pages none by design; 0 broken images | |
| 6.3 | Inline styles and `<font>` | PASS | | Only the bar widths (`style="width: N%"`, allowed by task 9); 0 `<font>` or `<center>` | |
| 6.4 | Contrast under 4.5:1 | PASS | | axe `color-contrast`: 0 violations on every page and width | |
| 6.5 | Unlabelled inputs | GAP | SHOULD | `list.php` name search; Cedrik's gem wish; Dag's bounty form and admin filters | S5 |
| 6.6 | Tap targets under 44px | GAP | SHOULD | Player pages: shop item buttons 22px tall, mail icons 16px, the module list links 16px, radio buttons 20px | S5 |
| 6.7 | Runtime errors met while crawling | GAP | BLOCKER | `list.php` 500 with a dead player online; `stats.php?op=referers` and `op=graph` 500 on MySQL 8.4 | B1 |
| 7.1 | Gameplay: a game day, level-up, death, resurrection, dragon, New Day | PASS | | Named tests below, all green in Modern core 42098285495 | |
| 8.1 | Known defects still open | RISK | mixed | 15 recorded 1.1.2 behaviors, 4 security residuals, rights items, 2 data oddities, the new findings | listed |
| 9.1 | Page generation time | PASS | | Median 0.061 s, 95th percentile 0.098 s, worst 0.172 s (local) | |
| 9.2 | Page weight | PASS | | Cold page about 212 to 229 KB uncompressed: fonts 125 to 149 KB, CSS 60 KB, HTML 10 to 26 KB | |
| 9.3 | Asset cache headers | GAP | NICE | Up to the web server; asset URLs carry no version, so far-future caching is unsafe across upgrades | N1 |
| 10.1 | Bundled modules | PASS | | 24 certified (`BUNDLED-MODULE-CERTIFICATION.md`); 23 active on a fresh install | |
| 10.2 | Guidance for third-party module authors | GAP | SHOULD | Hooks `scene` and `creatureportrait` only in the change ledger; `MODULE-POLICY.md` stale | S10 |
| 10.3 | Installed administrator's flags | GAP | SHOULD | 5 flags instead of 1.1.2's 20; editors hidden on a new game; not in the change ledger | S7 |
| 11.1 | Cron | PASS | | `cron.php` once per game day, lock, 404 over HTTP; ran twice locally | B2 |
| 11.2 | Mail | RISK | SHOULD | PHP `mail()`, best effort; no recovery or verification by email | B2, S11 |
| 11.3 | Logs | PASS | | PHP error log (no secrets by design), `gamelog`, `debuglog`, `faillog` | B2 |
| 11.4 | Translation tools | PASS | | `translatortool.php`, `untranslated.php` crawled without error | |
| 12.1 | Shard time trend | PASS | | 20:35 to 23:44 longest per run across Phase 5; close-out 23:03 | |
| 12.2 | Flaky or date-dependent tests | PASS | | None known; year-ahead run in task 13; clock pinned | |
| 12.3 | Runner and Node.js notices | RISK | SHOULD | Both annotations on every recent run; `runs-on: ubuntu-latest` unpinned | S8 |
| 13.1 | Front-door docs | GAP | BLOCKER | `README.md`, `VERSIONING.md`, `SECURITY-STATUS.md` state things that are no longer true | B3 |
| 13.2 | Docs for players, hosts, module authors | GAP | mixed | Players: in-game FAQ only (NICE); hosts: B2; module authors: S10 | B2, S10, N6 |

## 1. Fresh install

### 1.1 Local play from a clean clone (PASS)

A clean clone of `main` at `4f66f61`, then exactly the documented command:

```
$ python3 scripts/local-play.py up
{"tables":39,"modules_installed":24,"modules_active":23,"administrator":1,"state":"installed","modules":"recommended","config":"written"}
Legend of the Green Dragon - Resurrection is running.
LOCAL USE ONLY: this game is for playing on this computer, not for hosting.
Play:  http://127.0.0.1:8080/
real 0m29.465s
```

`home.php` answered 200 in about 30 ms. Then `backup` (file ends `-- Dump completed`, mode 0600), `upgrade --dry-run`
("The database is current: 0 of 0 upgrade steps applied ... Nothing was changed."), `restore FILE --yes`, `up` again
(game kept), and `php cron.php` twice in the cron container (`complete` once, then `already-complete`). The Docker
Hub images were pulled from Google's mirror and tagged with their Hub names (Docker Hub rate limits in this
container); nothing else differed from the document.

### 1.2 Manual CLI install (PASS)

Into an empty MySQL 8.4 database with a non-root account, with the credentials in the environment:
`php scripts/install.php --write-config --allow-served-path /path/dbconnect.php` printed the same counts and wrote the
file 0600. Served by `php -S` (PHP 8.5) and by Apache 2.4 with PHP 8.4 and `php.ini-production`: the home page
rendered (Apache: 0.084 s, 101 queries) once the web server could read the file (1.5).

### 1.3 Hosting guide (GAP, BLOCKER, task B2)

`docs/MODERN-INSTALLATION.md` opens "These instructions are for a local, disposable development installation" and
"Public hosting is not approved yet". There is no document a host can follow to put the game on a web server. A
rc.1 tester who wants to host needs at least:

- the docroot and the files that must not be served (1.4), with sample Apache and nginx rules;
- where `dbconnect.php` lives (the game reads it only from the repository root, which is inside the docroot), its
  owner and mode, and that the web server user must read it (1.5);
- the PHP version, the extensions (1.7) and the production `php.ini` settings the doc lists only in passing
  (`display_errors=Off`, `log_errors=On`, `zend.exception_ignore_args=On`, private session storage, `expose_php=Off`:
  Apache with `php.ini-production` sends `X-Powered-By: PHP/8.4.27`);
- HTTPS and the server value `HTTPS=on` that turns on the Secure cookie, and that a proxy must set it, not a
  client header;
- the cron entry, mail (11.2), logs, backups, upgrades, and the `sourceurl` duty for a modified copy (4.3);
- the shell requirement for rc.1 (1.6).

If Jim scopes rc.1 to local-play testers only, B2 drops to SHOULD.

### 1.4 Direct requests to internal files (RISK, SHOULD, tasks B2 and S2)

Requested directly on the local install, the 273 tracked files under `lib/`, `modules/`, `src/`, `scripts/*.php` and
`upgrades/` gave 173 answers of 200 (all but `upgrades/README.md` with an empty or one-byte body), 94 of 500, 2 of 409, 4 of 404. Each 500
logs a PHP fatal (a missing function or variable). With `display_errors=Off` nothing leaks, and no request changed
state, but a host that leaves `display_errors` on would show paths, and the error log fills with noise. Under Apache
the repository's other files are served as they are: `ROADMAP.md`, `CLAUDE.md`, `composer.json`, `composer.lock`,
`.gitignore`, `tests/`. The repository is public, so nothing secret is exposed; it is still untidy for a host.
`scripts/install.php` and `scripts/upgrade.php` answer 404 as designed, and `cron.php` 404 before bootstrap.
`SECURITY-STATUS.md` already lists "web-server rules must also deny direct internal/config access" as open.

### 1.5 The config file and the web server's user (RISK, SHOULD, tasks B2 and S4)

`install.php` writes `dbconnect.php` 0600, owned by whoever ran it. The docs say to run every command as the
application's user. Under Apache (`www-data`) with the file owned by another user, every page was a 500 with an empty
body and the log line `Uncaught ErrorException: Runtime error in common.php:135` and `Failed to open stream:
Permission denied`. A host gets no hint on the page. Making the file readable to the web server fixed it.

### 1.6 Shared hosting with no shell (GAP, SHOULD, task S3)

Today a host needs a shell, or at least a way to run CLI PHP:

| Operation | Today | Without a shell |
|---|---|---|
| Install | `php scripts/install.php` only; the 1.1.2 web installer answers 403 | Not possible. A workaround exists but is undocumented: install with local play, dump the database, import it through the host's panel, upload a hand-written `dbconnect.php` |
| Configure | The in-game Game Settings | Works |
| Upgrade | `php scripts/upgrade.php` only | Not possible |
| Maintenance | `php cron.php` only; browser-triggered maintenance was removed in Phase 3 | Many panels run a cron job with CLI PHP even without SSH; with none at all the game still plays (each player's New Day is separate) but the daily cleanups, expirations and module daily hooks never run |

Options for Jim:

- **A. Shell required (no code).** State it in the host guide for rc.1 and 1.0.0. Cheapest; excludes some hosts.
- **B. A web install and upgrade page behind a one-time token** the host creates as a file through the panel's file
  manager, refusing without it; same services as the CLI, same locks. Medium to large, new security surface, needs
  its own review. It is not the removed 1.1.2 installer, which stays 403.
- **C. A web-triggered maintenance request** behind a secret, for panels whose cron can only fetch a URL. Small to
  medium; brings back a browser path that Phase 3 deliberately removed, so it needs Jim's decision.

Recommendation: A for rc.1, decide B and C before 1.0.0.

### 1.7 What to upload, extensions, settings (GAP, SHOULD, tasks B2 and S6)

- **Upload:** the repository tree without `vendor/`. `composer.json` requires no runtime package, the local play image
  excludes `vendor/` and runs, and no game file loads `vendor/autoload.php`. Not needed on the server: `.git`,
  `.github`, `tests/`, `lab/`, `local/`, `docs/`, `scripts/art/`.
- **PHP:** 8.4 or 8.5 (`composer.json`: `~8.4.0 || ~8.5.0`).
- **Extensions:** `composer.json` requires `pdo`, `pdo_mysql`, `mbstring`, `json`, `session`. The code also calls
  `ctype_*` (6 files, for example the player ID check in `lib/web_security.php`) and `filter_var` (21 files), and the
  docs name `filter`; neither `ctype` nor `filter` is in `composer.json`. Both are on by default in most builds.

## 2. Upgrades (PASS)

- **The path from rc.1.** Once a tag `v1.0.0-rc.1` exists, `scripts/release-upgrade-check.py` (in Modern core, before
  the PHP tests) installs it from `git archive`, upgrades it to the commit under test and compares every table with a
  fresh install. Its `precedence()` sorts `1.0.0` after `1.0.0-rc.N` and rc numbers numerically. Until then it
  reports "no release yet", and the second CI run (`--release-ref HEAD`) exercises the install, upgrade and
  comparison on every commit. Locally on `4f66f61`: "no release yet ... Passing."
- **Steps.** `upgrades/` has none: no release exists, so no database needs one. `upgrades/README.md` says how to write
  one (check before change, change the fresh install in the same commit, never edit a step on `main`). After rc.1
  every schema, seed or setting change needs a step; the equivalence test and the release check hold that.
- **Backups.** `MODERN-INSTALLATION.md` "Upgrading": back up, dry run, upgrade with `--backup-confirmed`, verify, and
  restore. The local play commands run end to end (1.1). A host restore drill is a public hosting gate (`ROADMAP.md`).

## 3. Versioning: what the cut changes (GAP, BLOCKER, task B4)

Nothing was changed. To cut `1.0.0-rc.1`, in one commit before the tag:

- `src/Version.php`: `CURRENT = '1.0.0-rc.1'`.
- `project.json`: `"version": "1.0.0-rc.1"`; and `"phase"`, which still reads `"modern-core-engineering-in-progress"`.
  `public_game_hosting_approved` stays `false` unless Jim decides otherwise.
- `docs/VERSIONING.md`: "Current version: `1.0.0-rc.1`". `tests/tooling/test_version.py` and
  `scripts/validate-project.py` fail unless the three agree.
- Release notes, built from `docs/CHANGES-FROM-1.1.2.md` (148 lines: removed surfaces, accounts and security, gameplay
  and display, install/upgrade/local play, the Phase 5 UI), plus what an operator must know: fresh installs only, the
  shell requirement, the supported pairs, the known issues (section 8). None exists yet.
- `README.md`: today "No Resurrection version or release has been assigned" (B3).
- The annotated tag `v1.0.0-rc.1` with a message (none drafted), only on Jim's go. No GitHub release, image or host
  until he says so.

Missing rule: `VERSIONING.md` says `1.0.0-dev` "stays `1.0.0-dev` until the first test build" and that `-dev` sorts
before every rc, but not what `main` carries after the rc.1 tag. Going back to `1.0.0-dev` would make a newer
development build show an older version than rc.1. B4 should settle it (for example, keep `1.0.0-rc.1` until the rc.2
cut, or a documented pre-release form).

## 4. Licensing and credits

### 4.1 Files (PASS)

- `LICENSE.txt` and `README.txt` are byte-identical to `historical-source-1.1.2`
  (`test_license_text.test_license_and_credits_are_byte_identical_to_the_tag`, green in Baseline 42098285516).
- `NOTICE` states the 1.1.2 authors and license, Resurrection's credit with both links, the art license and its three
  folders, the removed skins and their designers, and the noncommercial status.
- `assets/LICENSE` is CC BY-SA 4.0 for `assets/scenes/`, `assets/portraits/`, `assets/icons/` only, with the full
  license text; fonts and `assets/ui/` are excluded by name.
- `assets/fonts/{alegreya,alegreya-sans,cinzel}/OFL.txt` exist; `test_ui_tokens.test_bundled_fonts_are_the_recorded_files_with_their_licenses`
  holds the font hashes against `docs/THIRD-PARTY-LICENSES.md`.
- The four remaining 1.1.2 images in `images/` (`new.gif`, `newscroll.GIF`, `somerights20.gif`, `trans.gif`) keep the
  open rights item in `LICENSING-AND-PROVENANCE.md`.

### 4.2 Credit line, attribution line and the safe frame (PASS)

On the local install, `home.php`, `about.php`, `news.php`, `create.php`, `list.php` and the popups `motd.php` and
`petition.php` each carry the 1.1.2 copyright line, `href="https://phoenix233.com"` and `href="https://jimlunsford.com"`
once. `prepare_template()`'s 1.1.2 checks, the XOR checks and the attribution check, and the safe frame, are held by
`test_safe_frame.py` (7 tests) and `test_ui_template.py`, green in Baseline 42098285516.

### 4.3 Source on request (PASS; host duty goes in B2)

LICENSE.txt asks that a server people outside family and friends can play give its complete source on request. Every
footer has "View PHP Source", linking `sourceurl` (default `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection`;
http or https only). The repository is public (`"private": false` from the GitHub API on 2026-11-06). `NOTICE`,
`README.md` and the setting's own description tell an operator who changes the game to point it at their modified
source; `LICENSING-AND-PROVENANCE.md` notes the default fits only an unmodified copy. The host guide (B2) should
repeat it.

### 4.4 and 4.5 The open legal items, for Jim's lawyer

Neither is a BLOCKER for rc.1, in my reading; this is an engineering view, not legal advice.

- **AGPL for bundled code: not a BLOCKER.** No file in the repository is labelled AGPL, and nothing AGPL is bundled,
  so an rc.1 built from `main` carries one license for code (CC BY-NC-SA 2.0 plus LICENSE.txt), one for art (CC BY-SA
  4.0) and the OFL for fonts. The question only becomes live when Jim wants to ship his own standalone code inside
  the repository. Until then the CLAUDE.md rule (label no file AGPL) keeps it closed.
- **Attribution wording, pending legal review: SHOULD before 1.0.0, and before rc.1 if Jim invites third parties to
  host rc.1 publicly.** The repository is already public under the same terms, so tagging rc.1 adds no new
  distribution. The wording does ask hosts to keep a credit "at least as prominently" as the original authors'
  (NOTICE, README.md); whether that matches section 4 of CC BY-NC-SA 2.0, and adds no term the license forbids, is the
  question for the lawyer. Also for the lawyer: whether the art's CC BY-SA 4.0 inside an NC game raises anything for a
  host, and the open rights items in `LICENSING-AND-PROVENANCE.md` (the four inherited images, module code by credited
  authors not reviewed file by file, the text removed with the disabled pages).

## 5. Security posture (review only)

- **Secrets and config (PASS).** `git grep` for private keys, AWS and GitHub token shapes and long quoted password
  literals: none outside tests. `.gitignore` covers `/dbconnect.php`, `/prefixes.php`, `.env*`, `*.key`, `*.pem`,
  dumps and `.local-play/`. `scripts/validate-project.py` refuses private-key signatures. The installer writes the
  config 0600 and never overwrites; its output carries no credential.
- **Sessions and cookies (PASS).** `lib/web_security.php`: `session.use_strict_mode=1`, `use_only_cookies=1`, cookie
  `HttpOnly`, `SameSite=Lax`, `Secure` when `HTTPS=on`; login rotates the ID and the CSRF token. Seen on the wire:
  `PHPSESSID ... HttpOnly; SameSite=Lax`; `lgi=...; HttpOnly; SameSite=Lax`; pages `Cache-Control: no-store`.
- **CSRF (PASS).** `ROUTE-INVENTORY-20261022.md` counts: CLOSED 88, DISABLED 3, READ-ONLY 69, OPEN 0;
  `test_route_inventory.py` fails on a new unlisted route.
- **Passwords (PASS).** `src/Security/Passwords.php`: `password_hash(PASSWORD_DEFAULT)` (bcrypt), 12 to 72 bytes,
  NULs refused; `password_needs_rehash` on login; legacy MD5 hashes cannot sign in.
- **HTTP headers (GAP, SHOULD, S1).** The game sends no `X-Frame-Options` or `frame-ancestors`, no
  `X-Content-Type-Options: nosniff`, no `Referrer-Policy`. The only CSP is `sandbox` on the user editor's last-hit
  view. Without frame protection, a signed-in player's buttons could be framed by another site (clickjacking); CSRF
  tokens do not stop that. A host can add the headers in the web server; the game should send a safe default.
- **CSP (GAP, SHOULD, S1).** Phase 5 moved the key map and tabs to static files, but a policy without
  `'unsafe-inline'` is not possible yet: inline `<script>` blocks remain in `bank.php`, `user.php`, `mounts.php`,
  `creatures.php`, `translatortool.php`, `modules/darkhorse.php`, `lib/forms.php`, `lib/motd.php`,
  `lib/clan/detail.php`, `lib/inn/inn_bartender.php`, `lib/graveyard/case_haunt*.php`,
  `lib/mail/case_write.php`, `lib/newday/dragonpointspend.php`, `lib/user/user_removeban.php` and the `{headscript}`
  slot in `lib/pageparts.php` (most are a one-line `focus()`), and inline handlers in `lib/pageparts.php` (popup
  links), `lib/user/user_.php`, `lib/content_editor.php`, `lib/clan/clan_membership.php` (confirm dialogs) and
  `lib/motd.php`. The bar widths use `style` attributes, which need `style-src-attr 'unsafe-inline'` or a change to
  classes. The JSON key map (`type="application/json"`) is not script and is fine under `script-src 'self'`.
- **Disabled surfaces (PASS).** LoGDnet, the SQL console, payments and the source viewer are removed and answer 404
  (`test_disabled_endpoints.py`); password recovery is an unknown op (400) and email validation 410; the 1.1.2 web
  installer 403; `cron.php`, `scripts/install.php`, `scripts/upgrade.php` 404 over HTTP. No `eval`, `create_function`
  or `assert($string)` remains in shipped PHP (`EXPRESSION-EVALUATION-AUDIT.md`: five found, five removed).
- **composer audit (PASS).** "No security vulnerability advisories found." `composer validate --strict`: valid.
- **Residuals already on record** (section 8): installed modules are trusted PHP; maintenance is not one
  transaction; the "Before public hosting" list in `SECURITY-STATUS.md`.

## 6. UI completeness (crawl)

### 6.7 Runtime errors (GAP, BLOCKER, task B1)

The crawl met three 500s; the server log has the cause of each:

- **`list.php`, "Warriors Currently Online"** (the default view, linked from the home page for every visitor and from
  the village): `Uncaught ErrorException ... list.php:137`. The online query (and the clan-members query) select no
  `hitpoints` column, and line 137 reads `$row['hitpoints']` for a dead player to choose "No" or "Unconscious". 1.1.2
  has the same code (`historical-source-1.1.2:list.php` lines 76 and 129); PHP 5 only noticed, PHP 8 warns and the
  application's error handler turns the warning into a 500. Reproduced on the local install: with a dead player
  online the page answers 500 for everyone, signed in or not; with the player signed out it answers 200. The
  full-list pages (`list.php?page=N`) select the column and work.
- **`stats.php?op=referers`** ("Top Referers") and **`stats.php?op=graph`** ("Last On" graph), on MySQL 8.4 only:
  `Database operation failed`. Both queries use `GROUP BY ... DESC` (`stats.php` lines 40 and 61), syntax MySQL 8.0
  removed; MariaDB 11.4 still accepts it (checked: the same query runs on MariaDB 11.4 and fails with error 1064 on
  MySQL 8.4).
- Not a defect: `graveyard.php?op=question` answered 409 ("Ramius resurrection is unavailable ...") for the crawl's
  dead player, whose state was set by hand; that guard refuses a state the game would not produce. The Ramius path is
  proven by the `test_ramius_*` tests (section 7).

### 6.1 to 6.4 (PASS)

- **Sideways scroll:** none, on every page at both widths.
- **Scenes:** every living or dead player's place page shows its scene (village, forest, inn, bank, weapons, armor,
  healer, gardens, rock, train, stables, mercenary camp, gypsy, lodge, clan, graveyard, shades, fields); other player
  pages the fallback; popups (mail, MoTD, petition), the Grotto, editors and Dag's admin pages none, as
  `PHASE5-TASK12-SCENES-20261105.md` specifies. No broken image anywhere.
- **Inline style and `<font>`:** the only `style` attributes are the HP, XP and favor bar widths (`width: N%`),
  which task 9's allow-list permits; no `<font>` or `<center>`.
- **Contrast:** axe-core's `color-contrast` rule found no violation on any page at either width. axe skips text it
  cannot judge (for example over an image), and those were not reviewed by hand.

### 6.5 Unlabelled inputs (GAP, SHOULD, task S5)

- `list.php`: the "Search by name" input (signed in only) has no label element; the text sits before it.
- Cedrik's potions (`runmodule.php?module=cedrikspotions&op=gems`): the gem count input and the wish radio buttons.
- Dag (`module=dag`): `contractname` and `amount` on the player's bounty form; `setter`, `getter`, `target`, `d` and
  the `s` select on the admin bounty lists.

The superuser pass with every editor flag (creatures, masters, mounts, companions, weapons, armor, taunts,
petitions, donators, translator, MoTD) found no unlabelled input.

### 6.6 Tap targets under 44px at 390 wide (GAP, SHOULD, task S5)

Inline text links (FAQ, "Return to Contents", the about page's credits) are left out: WCAG 2.5.8 exempts targets in a
sentence. What remains:

| Where | Target | Size (h x w) |
|---|---|---|
| `weapons.php`, `armor.php` | the affordable item buttons (`button.rz-shop-link`) | 22 x 32 to 105 |
| `list.php`, `bio.php` | the "write mail" icon link | 16 x 16 |
| `list.php` | player names | 38 tall |
| `about.php?op=listmodules` | each module's link | 16 tall |
| `create.php`, Cedrik's wish, `companions.php` checkboxes, the module manager checkboxes, Dag's admin radios | native radio and checkbox | 20 x 20 |
| Staff pages: `modules.php`, `user.php`, `referers.php` sort headers; `titleedit.php`, `creatures.php` "Edit" | links | 13 to 16 tall |
| Translator mode (`SU_IS_TRANSLATOR`): the "T" link after each text | link | 22 x 9 |

axe's own `target-size` rule (24px, WCAG AA) flagged only the translator "T" links.

## 7. Gameplay sanity (PASS)

All of these ran green in Modern core 42098285495 on both legs (every Python test is in exactly one shard,
`test_tooling_shards.py`). Locally, PHPUnit passed on PHP 8.4 (MariaDB 11.4) and 8.5: 341 tests, 146,687 assertions.

| Behavior | Tests |
|---|---|
| A new player's first day | `test_recommended_install_new_player_reaches_village_and_forest` (account creation, first New Day onboarding, village, forest, with the recommended modules) |
| A game day and its maintenance | `test_cli_maintenance_is_once_per_game_day`, `test_game_clock_pinned_away_from_rollover`, `test_ramius_fixture_refreshes_after_game_day_rollover` |
| Forest fights, victory and death | `test_ordinary_forest_progression_rewards_and_rollback`, `test_ordinary_forest_defeat_and_rollback`, `test_ordinary_forest_search_escape_and_event_handoff` |
| Level-up through the masters | `test_training_entry_authority_and_stale_round`, `test_training_autochallenge_maximum_and_multimaster`, `test_training_eligibility_shipped_masters_and_dragon_scale` |
| Death, the Graveyard and resurrection | `test_graveyard_victory_defeat_and_late_rollback`, `test_graveyard_restore_authority_http`, `test_ramius_get_core_favor_hooks_and_daily_separation`, `test_ramius_two_concurrent_sessions_settle_exactly_once`, `test_normal_newday_get_core_living_dead_and_hook_inventory` (a dead player's New Day) |
| The dragon kill | `test_dragon_transitions_rewards_and_rollback`, `test_dragon_kill_keeps_a_name_that_would_not_fit_http`, `test_dragon_points_single_bulk_replay_and_handoffs` |
| The New Day reset | `test_normal_newday_canonical_context_races_and_specialties`, `test_normal_newday_interest_spirits_mounts_buffs_companions`, `test_normal_newday_late_account_and_bundled_hook_rollback` |
| Every page in the frame | `TemplateRenderTest::testEveryPageRendersInTheResurrectionFrame` and the Phase 5 render tests |

The crawl also loaded the forest searches (`forest.php?op=search`, `type=slum`, `type=thrill`) and the training,
inn, stables, clan and Hall of Fame pages without an error.

## 8. Known defects still open

New in this review: the three 500s (B1), the administrator flags (S7), the unreadable-config blank page (S4).

Recorded as 1.1.2 behavior, not changed (`ROADMAP.md`, Phase 3 "Recorded, not changed", with each source there):

1. A ban reason is shown to the banned visitor as raw HTML (staff write it).
2. Weapon, armor and title text allows color and format codes.
3. Clearing the `lgi` cookie evades an ID ban.
4. The clan-delete hook is not called on disband.
5. A leader's deletion and a member's clan action started in the same instant can deadlock; InnoDB rolls one back (a
   500 that changed nothing).
6. The bad word list groups words by their first byte; a multibyte first letter shows under an empty heading.
7. In the user editor, when the player's page changes the field the admin changed, the player's value wins.
8. The unreachable `badnav.php` branch would still fail in `saveuser()`.
9. `motd.php`: a player's own protected POST to an admin op costs 10% experience with a news line; `newcount` paging
   never worked; the archive skips its first month row.
10. The healer sets hit points above the maximum back to it on view, for free; a shown commentary section stays
    usable for the session.
11. Training refuses a Dragon Point history entry other than `hp`, `at`, `de`, `ff` (no bundled module adds one).
12. `increment_module_setting()` on a missing setting warns (no bundled module calls it).
13. The Hall of Fame shows the translated "Unknown" as raw HTML (translators write it).
14. The Graveyard views reset drunkenness on view through the Drinks header hook.

Security residuals (`SECURITY-STATUS.md`; rows S02, S05 to S09 predate Phase 3 and are partly closed since, see B3):

15. Installed module code is trusted PHP; only the 24 bundled modules are certified (S04).
16. Account recovery and email verification are disabled, with no modern replacement (S05).
17. Maintenance is not one transaction; a failed run repeats its date-based cleanups (S13).
18. The "Before public hosting" gates: TLS, headers, internal-file denial, abuse controls, isolation, restore drills.

Rights (`LICENSING-AND-PROVENANCE.md`): the four inherited images; module code by credited authors not reviewed file
by file; the license text removed with the disabled pages; the archive modules (none imported).

1.1.2 data oddities (`PHASE5-SUMMARY-20261105.md`): creatures 134 and 135 are the same "Loneliness"; master 5's name
has U+FFFD where 1.1.2 lost a byte.

## 9. Performance

- **Generation time (PASS).** From the footer of every crawled page (PHP 8.5, MySQL 8.4, `php -S`, this container):
  471 page loads at 1440 (the first superuser pass and the three other roles), median 0.061 s, 95th percentile
  0.098 s, worst 0.17 s (`badword.php`); the editor pass's worst was 0.172 s (`creatures.php?level=5`). The home page:
  0.034 s, 98 queries.
- **Page weight (PASS).** One cold load at 1440 (bytes as served, uncompressed):

| Page | Requests | Total | Fonts | CSS | HTML | SVG |
|---|---|---|---|---|---|---|
| Signed-out home | 15 | 229 KB | 149 KB (7 woff2) | 60 KB (3 files) | 10 KB | 5 KB |
| Player, village | 26 | 225 KB | 125 KB (6) | 60 KB | 26 KB | 9 KB (scene, race, 11 icons) |
| Player, forest | 26 | 212 KB | 125 KB | 60 KB | 16 KB | 7 KB |

  All art: 459,702 bytes of SVG in the repository (318 portraits); a page loads only its own few.
- **Cache headers (GAP, NICE, N1).** Pages send `Cache-Control: no-store` (right for game pages). Assets get whatever
  the web server gives: PHP's built-in server sends no `Cache-Control`, `ETag` or `Last-Modified`; Apache sends
  `ETag` and `Last-Modified` but no `Cache-Control`. Asset URLs carry no version, so a far-future cache would serve
  old CSS after an upgrade. `resurrection.css` loads `fonts.css` and `tokens.css` by `@import`, one more round trip
  before text renders.

## 10. Modules

- **Bundled (PASS).** 24 modules, all certified for install, activation, lifecycle and gameplay
  (`BUNDLED-MODULE-CERTIFICATION.md`, `tests/unit/ModuleCertificationTest.php`); a fresh install activates the 23 on
  1.1.2's recommended list and leaves `game_fivesix` inactive.
- **Third-party modules (GAP, SHOULD, S10).** What a module author needs to know is spread over the change ledger and
  task docs: the new `scene` hook (name a scene for the module's pages, or none) and `creatureportrait` hook (a type
  for a foe with no portrait); the skin pieces that are gone (skin selection, the template cookie and preference,
  every 1.1.2 template; one template whose parts are fixed, with checks a template must pass); presentation classes
  in place of inline styles; POST, CSRF and one-use intents for state changes; that admission needs a review
  (`MODULE-POLICY.md`, whose "Modern engineering phase" paragraph still says hooks are not certified). No module
  author guide exists.
- **Installed administrator (GAP, SHOULD, S7).** `lib/installer/fresh_install.php:147` gives the first administrator
  `SU_MEGAUSER | SU_EDIT_USERS | SU_EDIT_CONFIG | SU_MANAGE_MODULES | SU_EDIT_COMMENTS`. 1.1.2's installer gave 20
  flags, "so they can do everything needed without having to first go into the user editor"
  (`historical-source-1.1.2:lib/installer/installer_stage_10.php`). On a new game the Grotto shows no creature,
  master, mount, companion, taunt, weapon, armor, petition or donation editor until the administrator grants the flags
  in the user editor. Some of 1.1.2's flags are gone on purpose (raw SQL, source viewer, payment log); the rest is a
  change from 1.1.2 that `CHANGES-FROM-1.1.2.md` does not record.

## 11. Operations

- **Cron (PASS).** `php cron.php`: lock, once per game day, refuses an out-of-date database, JSON result, 404 over
  HTTP. Local play runs it every 15 minutes. The host guide must give the crontab line (B2).
- **Backups (PASS).** Documented `mysqldump` and restore; local play's `backup` and `restore` ran (1.1).
- **Logs (PASS).** Errors go to PHP's error log with fixed source locations and no SQL or secrets; game events to
  `gamelog`, per-player detail to `debuglog`, failed logins to `faillog` with a fixed event vocabulary. Rotation and
  retention are the host's (B2).
- **Mail (RISK, SHOULD, S11).** One helper, `resurrection_send_email()`, calls PHP `mail()` and treats delivery as
  best effort (petition notices to admins, expiration warnings, new-mail notifications). With no mail transport the
  game still works and logs the failures. Password recovery is removed (an admin resets in the user editor) and
  email verification is unsupported (`requirevalidemail` must stay off); both are public hosting gates.
- **Local play (PASS).** Section 1.1.
- **Translation tools (PASS).** The translator tool and the untranslated list load for a translator; no error.

## 12. CI health

- **Shard time trend (PASS).** The longest shard job per accepted run in Phase 5, where the task docs record it (task 10's run is not broken down there): task 4 21:16,
  task 5 21:06, task 6 20:35, task 7 22:07, task 8 22:03, task 9 22:06, task 11 21:36, task 12 23:44, task 13 23:14, task 14
  23:22, task 15 22:05, close-out 23:03. The PHP 8.5 / MySQL 8.4 leg is the slower by 2 to 7 minutes (close-out:
  15:47 to 20:41 against 21:53 to 23:03). Steady, under 30 minutes; refresh `shard-durations.json` if a job passes
  25. The year-ahead local run in task 13 took 29:17 to 29:51 per shard on this kind of container, not in CI.
- **Flaky or date-dependent tests (PASS).** None open. Task 11 pinned the page renders' game clock; task 12 fixed the
  `PopupAdminRenderTest` time bomb; task 13 ran the whole suite a year ahead (and PHPUnit at 2099) and pinned the far
  dates to the run's clock. Remaining fixed dates in tests are past dates or pinned fixtures.
- **Runner and Node.js notices (RISK, SHOULD, S8).** The check-run annotations of the close-out's jobs, read through
  the GitHub API (`check-runs/127494016008/annotations` and `127494016122`), carry on every job:
  - warning: "Node.js 20 is deprecated. The following actions target Node.js 20 but are being forced to run on
    Node.js 24: actions/checkout@11d5960a326750d5838078e36cf38b85af677262, shivammathur/setup-php@bf6b4fbd49ca58e4608c9c89fba0b8d90bd2a39f."
  - notice: "The ubuntu-latest label will migrate to Ubuntu 26 beginning October 19, 2026."

  The jobs still ran on `ubuntu-24.04` image 20261101.360.1. All three workflows use `runs-on: ubuntu-latest`. When
  the label moves, Baseline integrity, which uses the runner's own PHP (8.3 today, so its PHP tests skip by design),
  may get PHP 8.4 or newer and start running those tests without a database. Fix: pin `runs-on: ubuntu-24.04` in
  `baseline-integrity.yml` and `modern-core-leg.yml` (three jobs) now, and move to Ubuntu 26 as its own task; update
  `actions/checkout` to a release whose `action.yml` declares `node24` (v5 or later) and `shivammathur/setup-php` to
  the first release that does, each pinned by full commit SHA as today, after reading the release's `action.yml`.

## 13. Docs

- **Front door (GAP, BLOCKER, B3).**
  - `README.md`: "No Resurrection version or release has been assigned"; "Modernization is in progress on
    `modernization/core-modernization`" (that branch is historical; work is on `main`); "the current phase gives the
    game a new interface" (Phase 5 is complete); the CI description predates the recommended-module install; the
    document list omits `CHANGES-FROM-1.1.2.md`, `UI-DESIGN-SPEC.md` and the Phase 5 summary.
  - `docs/VERSIONING.md`: "Gameplay keeps 1.1.2's behavior in every version (`MODERNIZATION-PRINCIPLES.md`); a version
    number never licenses a rebalance." Jim dropped that rule on 2026-10-30.
  - `docs/SECURITY-STATUS.md`: "PR #1 remains open and draft" (merged 2026-10-23); rows S08 and S09 list open work the
    route inventory and the expression audit closed; it should say what is open today.
  - `project.json`: `"phase": "modern-core-engineering-in-progress"`.
  - `docs/MODULE-POLICY.md`: "Hook registration ... not yet certified" (they are, for the 24).
- **About page (PASS).** The original text and copyright lines are kept (`test_license_text.py`); the footer shows
  "Version: Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition" and the attribution line.
- **Players (NICE, N6).** The in-game FAQ and New Player Primer (1.1.2's) are the only player docs; they do not
  mention anything new in Resurrection.
- **Hosts:** B2. **Module authors:** S10.

## Local verification run for this review

Supporting evidence only. Nothing in the repository was changed except the documents of this task.

- `composer validate --strict`: valid. `composer install` (lock file). `composer audit`: no advisories.
- `composer lint`: 408 files, 0 failures.
- `vendor/bin/phpstan analyse` and `-c phpstan-new.neon`: no errors.
- PHPUnit: 341 tests, 146,687 assertions, OK on PHP 8.4.27 (MariaDB 11.4) and on PHP 8.5.12 (fresh database).
- `scripts/verify-historical-baseline.py`: PASS, 417 files, 11 commits. `scripts/validate-project.py`: PASS.
- `scripts/release-upgrade-check.py`: "no release yet", passing.
- No HTTP shard was run locally: no test changed. CI runs the full suite on the commit of this task.
- The crawl, the install runs, the Apache check and the timings above.

## Ranked gap list

Size: S (under a day of work, one commit), M (one to three days), L (more, or needs a design).

### BLOCKER for 1.0.0-rc.1

| ID | Title | Scope | Tests | Size |
|---|---|---|---|---|
| B1 | Fix the 500s the crawl found | `list.php`: select `hitpoints` in the online and clan queries (or read it defensively), so the Warriors Online list renders with a dead player online. `stats.php`: drop `DESC` from the two `GROUP BY` clauses (lines 40, 61), keeping their `ORDER BY`. Record both as runtime fixes (old, new, why), with the 1.1.2 lines | A test that renders `list.php` (online view and `op=clan`) with a dead player online, failing first; the two Stats views on both CI legs, failing first on MySQL 8.4 | S |
| B2 | Write the host guide, with sample server rules (**status 2026-11-07: written in Phase 6 task 6**, `docs/HOST-GUIDE.md` and `docs/hosting/`; evidence `PHASE6-TASK6-HOST-GUIDE-20261107.md`) | A new host document: docroot and the paths to deny (`lib/`, `src/`, `modules/`, `tests/`, `scripts/`, `upgrades/`, `local/`, `lab/`, `docs/`, `vendor/`, dotfiles, `composer.*`, `*.md` except what a host wants), sample Apache and nginx snippets as files; `dbconnect.php` location, owner, mode and the web server user; PHP versions, extensions and production `php.ini`; HTTPS and `HTTPS=on`; cron line; mail; logs; backups and upgrades; `sourceurl` for a modified copy; what to upload (no `vendor/`); rc.1 needs a shell or panel cron with CLI PHP | A static test that the guide's deny list covers every top-level directory that is not served on purpose; the snippets checked by a test, not by hand | M |
| B3 | Refresh the front-door docs | `README.md` (status, branch, phase, document list), `VERSIONING.md` (the gameplay sentence), `SECURITY-STATUS.md` (PR #1, what is open today), `MODULE-POLICY.md` (certification paragraph), `project.json` `phase` (with B4 if it changes a field the tests read) | Existing `test_version.py`, `validate-project.py` | S |
| B4 | Prepare the rc.1 cut, stopping before the tag | Release notes for rc.1 from `CHANGES-FROM-1.1.2.md`, with operator notes and known issues; the tag message; the version rule for `main` after a tag, in `VERSIONING.md`; the cut checklist (the version commit: `src/Version.php`, `project.json`, `VERSIONING.md`). The version string changes only when Jim says go | `test_version.py` on the version commit; the release upgrade check picks the tag on the first commit after it | S |

### SHOULD before 1.0.0

| ID | Title | Scope | Tests | Size |
|---|---|---|---|---|
| S1 | Security headers, then a Content Security Policy | Send `X-Frame-Options: SAMEORIGIN` (or `frame-ancestors 'self'`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin` on every page and popup. Then move the inline scripts and handlers listed in section 5 to static files and data attributes, the bar widths to classes or a custom property set from a class, and send `Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'` | A header test on every page type; a static test that no shipped file writes an inline script or `on*=` handler outside an allow-list | S, then M |
| S2 | Refuse direct requests to internal PHP | A guard so that `lib/`, `src/`, `modules/` files requested directly answer 404 without bootstrapping, as `battle.php` does (defense in depth beside B2's rules) | A test that requests every internal PHP file directly and expects 404 and no log line | M |
| S3 | Decide and build the no-shell path | Jim's decision on options B and C in section 1.6; then the token-gated web install and upgrade page and, if chosen, the web-triggered maintenance request | HTTP tests for refusal without the token, the locks, the same results as the CLI | L |
| S4 | A clear page when the config cannot be read | When `dbconnect.php` exists but cannot be read, show 1.1.2's "Database Connection Error" style page (with the footer) instead of a blank 500, and log why | An operator page test with an unreadable file | S |
| S5 | Labels and tap targets on the pages found | Label the `list.php` search, Cedrik's wish form, Dag's player and admin forms; 44px for the shop item buttons, the mail icon links, the module list links and the radio and checkbox hit areas (through their labels) | Extend `test_ui_forms.py` label scans to these files; render tests for the sizes | S |
| S6 | Declare every PHP extension the game uses | Add `ext-ctype` and `ext-filter` to `composer.json`; one list in the host guide | `composer validate`; a static test that each extension function family used in shipped PHP is declared | S |
| S7 | The first administrator's flags | Record the change in `CHANGES-FROM-1.1.2.md`, then Jim decides: give the editor flags 1.1.2 gave (less the removed surfaces), or keep five and say so in the host guide | An install test of the flags | S |
| S8 | Pin the CI runner and update the Node 20 actions | `runs-on: ubuntu-24.04` in the three jobs; `actions/checkout` and `shivammathur/setup-php` at node24 releases, pinned by SHA; Ubuntu 26 later as its own task | The workflows' own runs; `test_tooling_shards.py`, `test_release_upgrade.py` read the workflow files | S |
| S9 | Legal review: attribution wording and the AGPL question | For Jim and a lawyer (section 4.4 and 4.5); any wording change it brings | `test_ui_template.py` and `test_safe_frame.py` hold the line and links | outside engineering |
| S10 | A guide for module authors | The hooks (`scene`, `creatureportrait`), what was removed with the skins, presentation classes, POST, CSRF and intents for state changes, the upgrade rule for module versions, admission review; update `MODULE-POLICY.md` | None (docs) | S |
| S11 | Mail, account recovery and email verification | Decide what 1.0.0 offers: at least document `mail()` and its failures for hosts; a modern recovery and verification flow is a public hosting gate | HTTP tests for any new flow | L |
| S12 | Let the game read its config from outside the docroot | An optional path (for example an environment variable or a fixed file one level up) so a host need not keep `dbconnect.php` in the served root | Install and page tests with the file outside | S |
| S13 | Bring `SECURITY-STATUS.md` up to date as a living record | After B3's quick fix: one current row per area with its evidence, and the public hosting gates as a checklist | None (docs) | S |

### NICE later

| ID | Title | Scope | Size |
|---|---|---|---|
| N1 | Asset caching | Versioned asset URLs (the version or a hash in the query) and a `Cache-Control` recommendation in the host guide | S |
| N2 | Flatten the CSS imports | Link `fonts.css` and `tokens.css` from the template, or build one file | S |
| N3 | Staff page tap targets | Sort headers and "Edit" links on staff tables at 44px on phones | S |
| N4 | The 1.1.2 data oddities | Decide whether to fix master 5's name and give one Loneliness its own drawing (both recorded changes) | S |
| N5 | The recorded 1.1.2 behaviors | Go through the 14 in section 8 and decide which to change now that the faithful rule is gone | M |
| N6 | Player docs | A short "what is new" for players, from the change ledger, linked from the home page or the FAQ | S |
| N7 | Compression | Recommend gzip or brotli for CSS, SVG and HTML in the host guide (fonts are already compressed) | S |
| N8 | Translator mode on phones | The "T" links after each text are 9px wide | S |
| N9 | Ubuntu 26 for CI | After S8: move both workflows to the new image deliberately, with a full run | S |
| N10 | A hosting image | A production container image (not local play) is excluded until Jim says go; listed so it is not forgotten | M |

Counts: 4 BLOCKER, 13 SHOULD (one of them, S9, outside engineering), 10 NICE.

## Acceptance (recorded 2026-11-06, Phase 6 task 2)

Confirmed through the GitHub API at the start of task 2:

| Workflow | Run | Result |
|---|---|---|
| Modern core | 42190029147 | **SUCCESS, all 12 jobs**: both legs' `checks`, the eight `http` shards and both `result` jobs |
| Baseline integrity | 42190029178 | **SUCCESS** |

Shard job durations (start to finish of each job), shards 1 to 4:

- PHP 8.4 / MariaDB 11.4: 17:16, 13:30, 16:35, 20:05
- PHP 8.5 / MySQL 8.4: 21:57, 22:02, 17:30, 21:46

The longest, 22:02, is under the 30-minute mark. Task 1 (`ea8bb3d`) is accepted. Jim's decisions on the gap list and
the task order are in `ROADMAP.md` and `PHASE6-TASK2-FIXES-20261106.md`.

## Decisions (recorded 2026-11-07, Phase 6 task 7)

Jim, 2026-11-07:

1. rc.1 ships only the sample server rules in `docs/hosting/`. No active `.htaccess` (`PHASE6-TASK6-HOST-GUIDE-20261107.md`,
   the question at its end, option A).
2. S11 (account recovery and email verification) is a known limitation for rc.1 and a blocker for 1.0.0. This confirms
   decision 4 of 2026-11-06 in `ROADMAP.md`, which the command chat had proposed. Until S11 ships, Game Settings keeps
   `requirevalidemail` off and locked (task 7).

## Status of B3, B4 and S9 (recorded 2026-11-07, Phase 6 task 7)

The gap list above stays as written. Task 7 (`PHASE6-TASK7-RC-MATERIALS-20261107.md`) closes or advances three rows:

| ID | Status after task 7 | Where |
|---|---|---|
| B3 | Done, pending task 7's CI acceptance. `README.md` (status, branch, phase, document list), `VERSIONING.md` (the gameplay sentence), `SECURITY-STATUS.md` (PR #1, what is open today; the full living record stays S13), `MODULE-POLICY.md` (the certification paragraph) and a hosting banner in `SECURITY.md` match the code. `project.json`'s `phase` is not changed: no test reads it, and the cut's version commit sets it | `README.md`, `docs/VERSIONING.md`, `docs/SECURITY-STATUS.md`, `docs/MODULE-POLICY.md`, `SECURITY.md` |
| B4 | Done up to the tag, pending task 7's CI acceptance. Draft release notes with operator notes and known issues; the cut checklist with the version commit, the checks before and after, the CI acceptance rule, the package step and the tag message; the rule for `main`'s version after a tag. The version string is unchanged (`1.0.0-dev`) | `docs/RELEASE-NOTES-1.0.0-rc.1.md`, `docs/RC-CUT-CHECKLIST.md`, `docs/VERSIONING.md`, `scripts/build-release-package.py` |
| S9 | Packet ready, awaiting counsel. Facts and numbered questions only, no conclusions and no wording changes | `docs/LEGAL-REVIEW-PACKET-20261107.md` |

What stands between `main` and the `v1.0.0-rc.1` tag is listed in `ROADMAP.md` (Phase 6, after task 7) and in
`docs/RC-CUT-CHECKLIST.md`.

## Task 7 accepted; S5 (recorded 2026-11-12, Phase 6 task 8)

Task 7 is accepted at `01cd708`: Modern core 42365272202 SUCCESS (12/12 jobs, all on `ubuntu-24.04`), Baseline
integrity 42365272387 SUCCESS. The rows above change as follows:

| ID | Status after task 8 | Where |
|---|---|---|
| B3 | Done. Accepted at `01cd708` | as above |
| B4 | Done up to the tag. Accepted at `01cd708` | as above |
| S9 | Packet ready, awaiting counsel | `docs/LEGAL-REVIEW-PACKET-20261107.md` |
| S5 | Done, pending task 8's CI acceptance. The inputs in 6.5 have labels; the player-page targets in 6.6 (shop item buttons, mail icon links, module list links, radio and checkbox hit areas through their labels) are at least 44px at 390 wide. The staff sort headers and the translator "T" links in 6.6 were not part of S5's row and are unchanged | `docs/PHASE6-TASK8-20261112.md` |
