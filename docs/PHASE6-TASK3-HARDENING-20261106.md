# Phase 6 task 3: hardening (S1, S2, S4, S6, S12) (2026-11-06)

Jim's brief, "Phase 6 task 3: hardening (S1, S2, S4, S6, S12)". Expected start: `main` at
`fcce356f7f01d649dfcad3fe70000515d2a40185` (it matched). The items come from the readiness review's gap list
(`PHASE6-RC-READINESS-20261106.md`). Task 2's acceptance is recorded first, in `PHASE6-TASK2-FIXES-20261106.md` and
`ROADMAP.md`. No version string, tag or release changes. Every change a player, administrator or operator can notice is
in `CHANGES-FROM-1.1.2.md` ("Phase 6 task 3").

## S1: security headers and a Content Security Policy

### The headers, from one place

`src/Http/SecurityHeaders.php` holds the headers and is the only code that sends them:

| Header | Value | When |
|---|---|---|
| `X-Frame-Options` | `SAMEORIGIN` | always |
| `X-Content-Type-Options` | `nosniff` | always |
| `Referrer-Policy` | `same-origin` | always |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'` | `csp_mode` = enforce (the default) |
| `Content-Security-Policy-Report-Only` | the same policy | `csp_mode` = report-only |

`SecurityHeaders::send()` is called from three places, each before any output:

- `common.php`, first thing after the entry guard, with the default mode (enforce). Every page that includes
  `common.php` has the headers from then on, including the pages that stop before the game's settings can be read: the
  not-installed page, the database error page, the S4 configuration page and the safe frame.
- `common.php` again, once the settings are loaded, with `csp_mode`'s value. It replaces both policy headers, so a game
  set to report-only or off sends that and nothing else.
- `installer.php` (its 403) and `index.php` (its redirect), which do not include `common.php`.

The user editor's "last hit" page (`lib/user/user_lasthit.php`), which shows a player's stored page as it was, keeps its
`sandbox` directive: `SecurityHeaders::send($mode, true)` sends the policy plus `; sandbox` under enforce, and a separate
`Content-Security-Policy: sandbox` under report-only and off. It replaces the page's own `header()` call.

### csp_mode

A new game setting under a new "Security Headers" heading in Game Settings (`lib/configuration_descriptors.php`):
`csp_mode`, "Content Security Policy", an enum of enforce ("Enforce (the default)"), report-only ("Report only") and off
("Off"). The installer writes enforce (`lib/installer/installer_default_settings.php`); any value that is not one of the
three is read as enforce (`SecurityHeaders::mode()`). Its help text, shown above it:

> Every page sends a Content Security Policy that lets the browser run only the game's own script and style files.
> Every page the game ships passes it. Choose Report only or Off on purpose: only for a third-party module that still
> prints inline script or inline style (its pages break under Enforce). Report only sends the policy as
> Content-Security-Policy-Report-Only: the browser blocks nothing and reports what it would block in its console. Off
> sends no policy. The frame and content-type protection and the Referrer-Policy are sent whatever this says.

### Inline scripts and handlers moved

One new static file, `assets/ui/behaviors.js`, loaded by `{headscript}` on every page and popup (`lib/pageparts.php`),
does what the inline code did, from data attributes. It uses delegated listeners on the document, so content added later
is covered. Each behavior is the same as before: the same confirm texts, the same counters, previews and toggles.

| Was (at `fcce356`) | Where | Now |
|---|---|---|
| `document.getElementById(..).focus()` scripts | `bank.php` (5), `creatures.php`, `user.php`, `lib/graveyard/case_haunt.php`, `lib/graveyard/case_haunt2.php`, `lib/inn/inn_bartender.php`, `modules/darkhorse.php` (2) | `resurrection_focus_marker($id)` (`lib/output.php`): `<span hidden data-rz-focus='id'>`; `behaviors.js` focuses the field on load |
| `onClick="window.open(..)"` popup links | nav popups (`lib/output.php`), `bio.php`, `list.php`, `viewpetition.php`, the translator's "T" link (`lib/translator.php`) | `resurrection_popup_attributes()`: `data-rz-popup="WxH"`; same window name (the page with non-alphanumerics removed) and features as `popup()` |
| The header controls' desktop-only popup `onclick` | `lib/pageparts.php` (`resurrection_header_control`, `resurrection_popup_enhancement()` removed) | `data-rz-popup="550x300" data-rz-popup-media="desktop"`: a popup only where `(min-width: 900px) and (hover: hover) and (pointer: fine)` matches, else the link opens in a tab, as before |
| `onclick="return confirm(..)"`, `onsubmit="return confirm(..)"` | `lib/clan/clan_membership.php` (remove member), `lib/commentary.php` (delete and ban, twice), `lib/content_editor.php` (delete), `lib/user/user_.php` (delete user), `modules.php` (uninstall) | `resurrection_confirm_attribute($text)`: `data-rz-confirm="text"` on the button, link or form; the same text |
| The ban form's "permanent ban?" confirm when duration is 0 | `lib/user/user_setupban.php` | `data-rz-confirm` plus `data-rz-confirm-when-zero='duration'`: asks only while the field holds 0 |
| `assets/ui/showform.js`'s own `data-rz-confirm` handler | `assets/ui/showform.js` | removed; `behaviors.js` handles every confirm, so a form is never asked twice |
| The ban form's "Use this filter" `onClick` (fill the IP, tick its radio) | `lib/user/user_setupban.php`, `resurrection_ban_js()` removed from `lib/ban_admin.php` | `data-rz-fill='ip' data-rz-fill-value=.. data-rz-fill-check='ipradio'` |
| `getUserInfo()` script and `onClick` (load the list of affected players in place) | `lib/user/user_removeban.php`, `mounts.php` | `data-rz-userinfo='id'`: an `XMLHttpRequest` to the link's address, the names written into the element. The mount editor's copy used `xmldom.load()`, which modern browsers do not have, so it never worked there; it works now |
| `onChange="this.form.submit();"` (the MoTD month list) | `motd.php` | `data-rz-autosubmit` |
| `onChange` copying the moderation reason | `lib/commentary.php` | `data-rz-copy-to='reason'` |
| `onClick` that disables and unticks the other box (fighter, defender) | `companions.php` | `data-rz-uncheck-other='defender'` and `='fighter'` |
| The script that hides the clan's "Edit Clan Info" area and the `onClick` that shows it | `lib/clan/detail.php` | `data-rz-reveal-area` on the area, `data-rz-reveal='hidearea'` on the link |
| "Add Poll Item": `onClick="javascript:..innerHTML += .."` and a script that emptied the area | `lib/motd.php` | `data-rz-append='hidepolls' data-rz-append-html=..`; `behaviors.js` appends with `insertAdjacentHTML`, so the choices already typed stay (1.1.2's `innerHTML +=` cleared them). The emptying script did nothing visible and is gone |
| `window.close()` script after a translation is saved | `translatortool.php` | `<span hidden data-rz-close-window>` |
| The mail warning script (`const mailWarnings=..`) | `lib/mail/case_write.php` | `data-rz-mail-to="to" data-rz-mail-warnings="[..]"` on the warning |
| The dragon point counter script | `lib/newday/dragonpointspend.php` | `data-rz-points-left`, `data-rz-points-target="amtLeft"`, `data-rz-points-text` on the form; updates on input and reset |
| `previewtext<name>()`: a script generated per field, and `onKeyUp` on the field | `lib/forms.php` (`previewfield()`), used by commentary, mail, petitions and others | one port of the same function in `behaviors.js` (the same colour map, talk line, `/me`, `::`, `/game` for game masters, characters-left text); the per-field values are `data-rz-preview-*` attributes on the input or textarea |
| The key map as a `<script type="application/json" id="rz-keys">` element | `lib/pageparts.php` (`resurrection_nav_script()`) | `<script src="assets/ui/keys.js" defer data-rz-keys='[..]'>`; `keys.js` reads the attribute |
| `{headscript}`'s inline branch (`$headscript`, unused) | `lib/pageparts.php` | removed; `{headscript}` is the favicon and `behaviors.js` |

`popup()` and `popup_window()` stay in `lib/pageparts.php` for third-party modules; `popup()` still returns the
`window.open(..)` text 1.1.2 modules put in an `onClick`. Such a module's links need `csp_mode` report-only or off.

The data attributes are double-quoted. The first local run of the four shards had the popup attributes single-quoted;
the header controls then ended in `'><svg`, which `test_user_editor_ban_authority_http` rightly reads as an attribute
broken open by a planted ID, and it failed. With double quotes it passes, unchanged in that respect.

### Inline styles

The bar fills (the character panel's stat bars and phone bars, the fighter bars in a battle) were
`style="width: N%"` in `templates/resurrection.htm` and the safe frame. They are now `class="rz-..-fill rz-fill-N"`,
and `assets/ui/resurrection.css` has the 101 rules `.rz-fill-0` to `.rz-fill-100`. **Chosen: width classes in whole
percent steps.** They need no script, so the bars are right with JavaScript off and before any script runs; a custom
property set from a data attribute would need script (CSS cannot read an attribute into a width yet), and a `style`
attribute or `<style>` is what the policy refuses. The cost is precision: the fill is the nearest whole percent
(`resurrection_fill_percent()` in `lib/pageparts.php`), within half a percent of the true value. A value between 0 and 1
shows as 1, and between 99 and 100 as 99, so a bar is never shown empty or full when it is not.

The safe frame's `<style>img { max-width: 100%; height: auto }</style>` (twice) became `<link rel="stylesheet"
href="assets/ui/safeframe.css">`, linked before `resurrection.css`. The safe frame stays in code and still passes the
template checks.

### Resources from another origin

None. Every script, stylesheet, font and image the pages load is under the game's own directory: `assets/`,
`images/`, `templates/` and `favicon.ico`. `assets/ui/fonts.css` and `tokens.css` are `@import`ed locally; the fonts
are files under `assets/`. The Creative Commons badge in the credits is the local `images/somerights20.gif`. No page
uses a `data:` URI. The off-site addresses in the pages are links the player follows (Creative Commons, DragonPrime,
the source repository, phoenix233.com, jimlunsford.com, the GNU and Wikipedia links on the about pages), which the
policy does not restrict. The Gypsy Bandit's stored creature script in `lib/installer/installer_sqlstatements.php`
contains a `style=` in data that is never printed. Nothing needed the policy widened.

### Checks

- `tests/unit/SecurityHeadersTest.php`: each mode's headers, the sandbox variants, an unknown mode is enforce, the
  setting's declaration, note and installed default.
- `tests/unit/InlineContentTest.php`: reads every shipped PHP file's strings and inline HTML (not its comments) and the
  template for an inline script, an inline handler, a `javascript:` URL, a `style` attribute or a `<style>` element.
  Three places are allowed, each with its reason: the Gypsy Bandit data and the sanitizer's two patterns that remove
  script and style elements from text. At `fcce356` it lists 63 findings
  (the inventory above).
- `test_web_application.py`: every HTML page the suite's test server sends is parsed
  (`tests/fixtures/inline-content-scan.php`, loaded through `auto_prepend_file`); the class fails if any page had an
  inline script, `<style>`, `on*` attribute, `style` attribute or `javascript:` URL. At `fcce356`: 60 of 86 pages in
  the three-test run. `test_security_headers_on_every_page_type_in_each_mode_http` checks home, village, a popup
  (`mail.php`), an operator page (`configuration.php`), the installer's 403 and `index.php`'s redirect in each mode.
- `test_operator_pages.py`: `test_operator_pages_send_the_security_headers` checks the not-installed page, the
  database error page, the installer and the safe frame.

### The browser check

The suite has no browser layer, so this ran locally: Playwright Chromium (the preinstalled build) against a fresh
install (recommended modules) served by `php -S` with `LOGD_DBCONNECT`, the policy enforced by the game. A listener for
`securitypolicyviolation` is installed before any page script, in every page and popup; console messages naming the
Content Security Policy are collected too. Dialogs are recorded and dismissed. Result: **35 of 35 checks pass, 74
documents, every one sent the enforced policy, 0 violations.** A control at the end injects an inline handler and the
listener reports it (then it is discounted), so a silent listener would have failed the check.

| Behavior | Checked |
|---|---|
| Commentary preview and characters-left counter (village) | colour code rendered, "You have 179 characters left." |
| Nav popup (F.A.Q.) | opens a 500 x 300 window; the page stays |
| Header control popup (MoTD, desktop) | opens a popup; the page stays |
| Poll: Add Poll Item | 5 option fields become 6; a typed choice survives the next append |
| Mail write popup | the staff warning shows; the body preview renders |
| Key map | B opens the bank, F the forest |
| Focus | bank deposit field, creature editor search, user editor search |
| Dragon point counter | 3 left, 1 left after typing 2, 3 after Reset; the points are spent |
| Clan detail | the area starts hidden; the link shows it |
| Confirms, dismissed | title editor delete, module uninstall, user delete, permanent ban: each asked with its text, the page stays; a timed ban does not ask |
| Companion editor | ticking fighter disables and unticks defender, and back |
| Mount editor, Remove Ban | the affected players load in place |
| Ban filter link | fills the IP and ticks its radio |
| A fight round | rendered |
| Grotto pages | Petitions, Recent Commentary, Bios, Donators, the six content editors, Nasty Words, Referers, Stats, Gamelog, Game Settings |

The same check against `fcce356` (with the policy added to each page by the browser, since `fcce356` sends none)
fails: the commentary preview and counter do not work, and 30 violations are recorded before it stops at the first moved
behavior. Pages visited in the passing run: armoreditor, badword, bank (and deposit), bios, clan (list, detail),
companions (edit), configuration, creatures, donators, forest (search, fight), gamelog, home, mail (address, write),
masters, moderate, modules (a category), motd (add poll), mounts, newday, petition (F.A.Q.), referers, stats,
superuser, taunt, titleedit, user (remove ban, set up ban), viewpetition, village, weaponeditor.

## S2: internal PHP refuses a direct request

One constant, `RESURRECTION_ENTRY`. The 62 entry points define it as their first statement: the 60 top-level pages a
browser loads (every top-level `.php` but `common.php`, `battle.php` and `dbconnect.php`), and `scripts/install.php` and
`scripts/upgrade.php`. PHPUnit's entry point is the new `tests/bootstrap.php` (`phpunit.xml` points to it). Every other
PHP file the game ships checks it first, before any other statement: the 187 under `lib/`, the 39 under `modules/`, the
47 under `src/` (class files too), and `common.php` and `battle.php`, which only pages include; 275 files.

```php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
```

A direct request gets a 404 with an empty body and nothing else: no bootstrap, no database, no session, no log line. A
command run from the shell (`cli`) is let through: the CLI entry points define the constant anyway, and the test
helpers and tools load single files. A web server's PHP is never `cli` (`php -S` is `cli-server`). `vendor/` is not
edited.

Tests: `tests/unit/EntryGuardTest.php` (the first statement of every internal file is the guard, more than 270 files;
the 62 entry points define the constant first; the bootstrap) and
`test_operator_pages.test_internal_files_answer_a_direct_request_with_an_empty_404` (every file under `lib/`, `src/`
and `modules/`, and `common.php` and `battle.php`, requested directly: 404, empty body, no log line; every entry point
still answers as before: the installer's 403, `index.php`'s 302, `cron.php`'s 404 for the web, `runmodule.php`'s 400
without a module, the rest the not-installed page). At `fcce356`: `battle.php` gave 404 with 28 bytes, `common.php` and
the others 200.

### vendor/ and other non-game files in the docroot

The game does not use `vendor/` at run time: `composer.json` requires only PHP and extensions, and `vendor/` holds the
development tools (PHPUnit, PHPStan). `vendor/` is not in the repository (`.gitignore`); it exists only where someone
ran `composer install`. Measured with `php -S` serving the game's directory after `composer install`:

| Path | Answer |
|---|---|
| `vendor/autoload.php`, `vendor/composer/installed.php` | 200, empty body (the files run) |
| `vendor/composer/installed.json` | 200, 69 KB of JSON: every package and version |
| `vendor/bin/phpunit`, `vendor/phpunit/phpunit/phpunit` | 200, served as text |
| `composer.json`, `composer.lock` | 200 |
| `.git/HEAD` (a git checkout) | 200 |
| `lib/output.php`, `src/Http/SecurityHeaders.php`, `modules/darkhorse.php`, `common.php`, `battle.php` | 404, empty |

So `vendor/` is reachable from the docroot as shipped whenever it is installed there, and it discloses versions and runs
third-party PHP on request. For the host guide (task 6): do not run `composer install` in the served directory (the
game needs no `vendor/`), and deny `vendor/`, `composer.*`, `.git/`, `docs/`, `tests/`, `scripts/` and `lab/` in the
web server's rules. No server configuration file is added in this task.

## S4: a page when the configuration cannot be used

`common.php` loads the configuration file (`dbconnect.php`, or `LOGD_DBCONNECT`'s file) under checks; on a failure it
calls `resurrection_config_failure()` (`lib/config_failure.php`):

| Case | Reason shown |
|---|---|
| The file exists but PHP cannot read it (permissions, owner), or it is not a regular file | "The file exists, but PHP cannot read it." |
| `LOGD_DBCONNECT`'s file is under a directory PHP cannot enter (the file may exist but PHP cannot see it) | the same |
| Loading it stops with an error: a parse error, an exception, or it leaves `$DB_HOST`, `$DB_USER`, `$DB_PASS` or `$DB_NAME` unset (an empty file, an incomplete one) | "The file could not be loaded: PHP stopped with an error inside it. It is damaged, or it was changed by hand." with the line PHP stopped at, when the error is in that file |
| `LOGD_DBCONNECT` is not an absolute path | "LOGD_DBCONNECT is set, but it is not an absolute path." |

The page: status 503, `Retry-After: 300`, `Cache-Control: no-store`, the security headers, the title "The game cannot
read its configuration", what failed, which file, and what to check (owner, permissions, directories, the file itself,
`LOGD_DBCONNECT`), drawn in the game's template with the full footer: the 1.1.2 credit line, the license block and the
attribution line (the safe frame's, if the template fails its checks). Anything the file printed before it failed is
dropped. The reason and the path go to the PHP error log once, with control characters replaced. Nothing from the file
is shown or logged: not its contents, not a credential, not PHP's own message (a parse error can quote the file). A
missing file still gives the not-installed page.

Tests (`test_operator_pages.py`, a server running as an unprivileged user when the suite runs as root):
`test_an_unreadable_config_file_gets_the_operator_page`, `test_a_damaged_config_file_gets_the_operator_page` (a parse
error, a file that throws, an empty file, an incomplete one) and `test_logd_dbconnect_uses_the_same_page` (an unreadable
file, a damaged one, a relative path, a file under a closed directory directly and two levels down). Each asserts the
503, the page, the three credit parts, the reason and path, one log line, and that the secret in the file appears
nowhere. At `fcce356`: 200 for each case, and in the "throws" case the secret the file printed before it threw reached the page.

Sheet 1 (below) shows the page.

## S6: every PHP extension declared

The functions and classes the shipped PHP uses, by extension (PHP 8.5's reflection, everything but `tests/` and
`vendor/`):

| Extension | Used for | Declared |
|---|---|---|
| ctype | `ctype_alpha`, `ctype_digit`, `ctype_xdigit` | **added**: `ext-ctype` |
| filter | `filter_var` | **added**: `ext-filter` |
| json | `json_encode`, `json_decode`, `JsonException` | `ext-json` |
| mbstring | `mb_strlen`, `mb_substr`, `mb_strcut`, `mb_str_split`, `mb_check_encoding` | `ext-mbstring` |
| PDO, pdo_mysql | `PDO`, `PDOException`, `Pdo\Mysql` | `ext-pdo`, `ext-pdo_mysql` |
| session | `session_start` and 7 more | `ext-session` |
| Core, date, hash, pcre, random, Reflection, SPL, standard | the rest | always part of PHP 8.4 and 8.5 |

`composer.json` and `composer.lock` (its content hash and platform list) carry the two additions.
`tests/unit/ExtensionDeclarationTest.php` tokenizes the shipped PHP, maps each called function and named class to its
extension through reflection and fails if one is not declared and not always present; a second test proves the scan
finds a call. At `fcce356` it reports `ext-ctype` and `ext-filter` with the files that use them.

## S12: the configuration file outside the docroot

`LOGD_DBCONNECT`, when set and not empty, is the configuration file's absolute path
(`src/Configuration/ConfigLocation.php`; read from `getenv()`, then `$_SERVER`, which is where `SetEnv` and PHP-FPM's
`env[]` put it). The precedence is recorded in `CLAUDE.md`:

1. Set: that path, and only it. A `dbconnect.php` in the game's directory is ignored. Missing: the not-installed page,
   and `scripts/install.php` writes there (as `--write-config` would, mode 0600, outside the served directory). Unreadable,
   damaged or not absolute: the S4 page. `scripts/install.php` and `scripts/upgrade.php` refuse another path given on
   the command line ("LOGD_DBCONNECT names the configuration file the game reads. Give that path, or none.") and a
   relative value.
2. Unset or empty: `dbconnect.php` in the game's directory, as before; the commands' path argument as before.

Tests: `FreshInstallTest::testRealInstallationAndAccountBoundary` (extended): the two refusals, an install with only
`LOGD_DBCONNECT` set to a path outside the served directory (written, 0600, the values loaded), `upgrade.php --dry-run`
with it, and the upgrade refusing another path. `test_web_application.test_logd_dbconnect_reads_the_config_from_outside_the_docroot_http`:
a second server on the same tree without `dbconnect.php`, the file elsewhere: home, village, mail and the warriors list
play; with a docroot `dbconnect.php` pointing at a closed port, the environment's file still wins; without the
variable the docroot file is used (the database error); a missing file gives the not-installed page.
`test_operator_pages.test_logd_dbconnect_uses_the_same_page` covers the S4 cases through the variable. At `fcce356`
the install wrote beside the game, the play test found the game not installed, and the S4 cases were 200.

## Also fixed

- **The mount editor's player list** never loaded in a modern browser (`xmldom.load()`); it loads now (above).
- **Add Poll Item** no longer clears the choices already typed (above).
- **Line endings**: `lib/forms.php` and `modules/specialtythiefskills.php` keep their CRLF endings; the lines added to
  them are CRLF too.

Not fixed, found while testing: after signing in again in the middle of the new-day questions, the replayed page
(`badnav.php`) carries the earlier session's form tokens, so its buttons answer "Invalid form submission." (403). The
same at `fcce356`; the player goes on from the village link. A later task can decide whether to fix it.

## Screenshots

Two sheets, delivered with this task's report (not committed):

- Sheet 1, `sheet1-s4-operator-page.png`: the S4 page at 1280 x 900 and on a phone (iPhone 13, full page):
  `LOGD_DBCONNECT=/etc/resurrection/dbconnect.php`, mode 0600, owned by root, PHP running as nobody.
- Sheet 2, `sheet2-csp-enforced.png`: the village, a fight round, the bank (deposit, field focused) and the mail-write
  popup with its warning and body preview, each rendered with the policy enforced, from the browser check's run.

## Tests

Each new or extended test was run first against `fcce356` (a worktree at `fcce356` with only the tests copied in), then
on this commit.

| Test | At `fcce356` | Now |
|---|---|---|
| `SecurityHeadersTest` (3) | **fails**: class `Resurrection\Http\SecurityHeaders` not found; the descriptor missing | passes |
| `InlineContentTest` (2) | **fails**: 63 findings | passes |
| `EntryGuardTest` (2) | **fails**: `battle.php`, `common.php`, `lib/...` (every internal file) | passes |
| `ExtensionDeclarationTest` (2) | **fails**: `ext-ctype (lib/extended-battle.php, lib/lookup_user.php, lib/mail/case_read.php)`, `ext-filter (lib/accounts.php, lib/configuration_descriptors.php, lib/darkhorse_entry.php)` | passes |
| `CharacterPanelTest::testTheFillIsAWholePercentClassThatTheStylesheetDraws` | **fails**: `resurrection_fill_percent()` undefined | passes |
| `FreshInstallTest::testRealInstallationAndAccountBoundary` (extended for S12) | **fails** at the first `LOGD_DBCONNECT` refusal | passes |
| `test_operator_pages` (5 new tests) | **15 failures**: the S4 cases 200 (the "throws" case printed the secret), the guard (`battle.php: 404 28 bytes`, the rest 200), the five `LOGD_DBCONNECT` cases 200, `X-Frame-Options` missing on every operator page | pass |
| `test_web_application`: the headers test, the `LOGD_DBCONNECT` test, and the page scan | **2 failures and an error**: `'SAMEORIGIN' != None : enforce home.php`; `'has not yet been installed' unexpectedly found`; "Pages printed what the Content Security Policy refuses (60 of 86 pages)" | pass |
| The browser check (local) | **fails**: preview and counter dead, 30 violations, stops at the first moved behavior | 35 of 35, 0 violations |

Existing tests changed to follow the new markup, none weakened: `BattleCommentaryRenderTest` and `CharacterPanelTest`
(the fill is the `rz-fill-N` class, no `style`; the expected percents rounded), `NavigationTest` (the popup's data
attributes and no `onClick`; the key map's attribute), `TemplateRenderTest` (the same, the header mail control, the safe
frame's stylesheet and no `<style>`), `OutputBufferingTest` (a line number moved by the guard), `test_ui_forms.py`
(three safe frame allowances no longer needed are dropped; `behaviors.js` does the confirm), `test_ui_template.py` (the
key map attribute, the phone bar class, no `style=` in the template), `test_web_application.py` (the last hit's policy
plus `sandbox`; the translator's close marker; the user editor's ban test now finds the stored IP's quote escaped in
the filter link's `data-rz-fill-value`, where it was a `\u0027` in the inline script's string, and no `onClick`).

## Local verification

Supporting evidence only; CI is the acceptance.

- PHPUnit 12.5, full suite, each on a recreated empty database (MySQL 8.4.13 in Docker): PHP 8.5.12 **OK, 356 tests,
  147329 assertions** (in each shard's run below); PHP 8.4.27 **OK, 356 tests, 147329 assertions**.
- The four Modern core `http` shards, each in its own worktree with its own MySQL 8.4 database, PHPUnit first as in CI,
  `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, run side by side. A first run on an earlier state of this change: shards 1, 2
  and 4 OK; shard 3 failed `test_user_editor_ban_authority_http` (the quoting described under S1, and the test's
  needle for the ban filter's old inline script). Both were fixed, and the second run, on this commit's tree:
  **all four OK**, no failure, error or skip.

  | Shard | PHPUnit | Tests run | Python duration | Whole job (PHPUnit and shard) |
  |---|---|---|---|---|
  | 1 | OK, 356 | 60 | 16.4 min | 17:49 |
  | 2 | OK, 356 | 59 (58 + the account setup) | 16.3 min | 17:31 |
  | 3 | OK, 356 | 58 (57 + the account setup) | 16.2 min | 17:24 |
  | 4 | OK, 356 | 168 (167 + the account setup) | 15.9 min | 17:04 |
- The browser check, on this commit's tree: 35 of 35, 0 violations (above).
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`): no errors.
- `composer validate --strict`: valid. `composer install`: nothing to install. `composer audit`: no advisories.
  `composer lint`: 419 files, 0 failures.
- Historical verifier: PASS (417 files, 11 commits). Project validator: PASS (970 tracked files). `git diff --check`
  with `core.whitespace=cr-at-eol` (the two CRLF files' added lines end in CR, as their existing lines do): clean.

## Acceptance (recorded in Phase 6 task 4)

Task 3 is **accepted at `b1de59d`** (Jim, 2026-11-06).

- Modern core 42239340118: **SUCCESS**, 12/12 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http`
  shards 19:10, 20:19, 20:03, 20:26; PHP 8.5 / MySQL 8.4 `http` shards 21:38, 21:44, 16:45, 21:22.
- Baseline integrity 42239340082: **SUCCESS**.

Task 3, S1, S2, S4, S6 and S12 are ticked in `ROADMAP.md`. Two items found in this task are added to `ROADMAP.md`
under Phase 6: the stale form tokens after signing in again during the new-day questions (above), and the header and
panel links on this task's configuration page (MoTD, Petition for Help, Online Characters), which cannot work without
a configuration; task 5 removes them.
