# Phase 5 task 11: popups, admin and superuser screens (2026-11-01)

Starting head: `5dafec87cb0b6e2677822a41f9b1878b4639c9a5` (the task 10 fix), matched. Task 10's acceptance is recorded in
`PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md` ("Acceptance") and ticked in `ROADMAP.md`.

Everything new is drawn only in the Resurrection template (`resurrection_ui()`). In a 1.1.2 skin every popup and admin
page in this task is byte for byte what `5dafec8` printed (`tests/fixtures/popup-admin-old-skin.json`, below). Nothing
changes what any page does: the same queries, links, forms, fields and targets. Each visible change is in
`CHANGES-FROM-1.1.2.md`; the components are in `UI-DESIGN-SPEC.md` ("Popups and staff screens").

## Popups

### Mail

- `mail.php`: Inbox and Write (and any tab a module adds through `mailfunctions`) are tabs, `nav.rz-mailtabs`, real
  links, 44px, the current one `aria-selected="true"` and `--accent` filled. Reading a letter keeps Inbox current; the
  address and write forms keep Write current.
- The inbox (`lib/mail/case_default.php`, `resurrection_mail_inbox()` in the new `lib/mail_view.php`): the heading
  "Mail Box"; a list, unread first; each row a labelled checkbox ("Select: subject", for screen readers), a New badge on
  unread rows (an `--accent` outline) instead of `newscroll.GIF` / `oldscroll.GIF`, the subject as the link, the sender
  and date under it in `--muted` (plain text). Check All (`data-rz-checkall`, handled in `assets/ui/showform.js`, no
  inline `onClick`) and Delete Checked are secondary. Same form (`mail.php?op=process`, CSRF token, `msg[]`).
- A letter (`lib/mail/case_read.php`, `resurrection_mail_read()`): the subject as a heading (New while unread), "From:"
  and "Sent:" on a `--muted` line, the body (its color codes kept and closed inside it), then Reply (the one primary,
  only on a player's letter), Delete and Mark Unread (the same POST forms), Report to Admin (not on the game's own
  letters), and Previous and Next. The letter is marked read and the mail cache cleared as before. `uscroll.GIF` and
  `lscroll.GIF` are not drawn.
- The write form keeps task 9's labels; Send stays its one primary.

### Italics: the cause

The whole mail popup rendered in italics. The cause is in 1.1.2's `lib/mail/case_default.php` (unchanged since the
historical tag): the inbox note is printed with

```
output("`n`n`iYou currently have %s messages in your inbox.`nYou will no longer be able ... after %s days.", ...);
```

It opens `` `i `` and never closes it. `appoencode()` keeps the open tag in `$nestedtags`, so everything printed after
it, down to the page end, is inside `<i>`; only `popup_footer()`'s tag closer ends it before the footer. With an empty
inbox the page is italic from the start ("`` `i ``Aww, you have no mail, how sad.`` `i ``" is closed, but the note
after it is not), so 100% of the text is italic; with three letters, 239 of the inbox's 383 characters of text were
italic (measured with a DOM walk over the rendered page at `5dafec8`).

The fix is at the source, in the Resurrection template: the heading is an `h2` and the note a plain `--muted`
paragraph (`p.rz-mail-note`), printed from the same translated format string with its codes left out
(`resurrection_mail_plain()`), so no italic tag is ever opened; a letter's body is encoded on its own and every tag it
opens is closed inside it (`resurrection_appoencode_closed()`, `lib/output.php`). After the fix: 0 italic characters, and
the render test finds no `i` or `em` element in the content of the inbox, the read view or the write forms. The
translation keys are unchanged. A 1.1.2 skin prints 1.1.2's text byte for byte (its pin includes the open `<i>`).

### Petition

`petition.php`: the form keeps task 9's labels; Submit is now its one primary (`FormsRenderTest`'s list of primaries
updated to say so, as this brief asks). The FAQ pages and the primer are plain readable text in the card.

### MoTD

`lib/motd.php` (`motditem()`, `pollitem()`, `motd_admin()`): each item is an `article.rz-motd` with its title in
Cinzel, Edit and Del as secondary buttons for staff (Del asks first through `data-rz-confirm`, no inline `confirm()`),
the author and date in `--muted`, the body in Alegreya with its line breaks. A poll is a fieldset: the question is its
legend, each choice a radio with its label (the choice and its count), and an `--accent` bar, a `progress` element whose
value is the same share of 400 that 1.1.2 used for `rule.gif`'s width (no inline style). Vote is the primary. Opening the
MoTD still sets the account's `lastmotd` to the newest item's date, so its unread mark clears as before (the render test
checks the stored value). The MoTD and poll editors are labelled.

### Translator tool

`translatortool.php` (staff with the translator's flag only, unchanged): visible labels for the namespace, the text and
the translation; Save & Close is the one primary, Save No Close secondary; the list view's checkboxes labelled.

## Admin and superuser screens

- Tables: every admin table already scrolled in its own box (task 9). A label read by screen readers only is absolutely
  positioned; inside a table it escaped the box and widened the companion editor's page on a phone to 474px. Tables
  now hold what is positioned inside them (`.rz-content table { position: relative; }`). Checked at 390px wide with
  Playwright on the Grotto and every editor page reached from it: `scrollWidth` 390 on each, the wide tables scrolling
  inside their boxes.
- Tabs: `showform()` editors (creatures, users, mounts, companions, masters and the others) already had task 9's tabs.
  The settings editors drawn by `lib/typed_editor.php` (Game Settings, `configuration.php`), which Phase 3 made one
  long page, now get the same tabs: one panel a section, each headed by its name, each label above its control. Their
  tab list carries `data-rz-tabs-noindex` and `showform.js` adds no `showFormTabIndex` field to them, because
  `SettingDescriptor::patch()` rejects any field the editor does not declare. Save stays secondary (no single main
  action on a settings page).
- Petition badges (`{petitiondisplay}`, `resurrection_petition_badges()` in `lib/pageparts.php`): the User Editor and
  Petitions links, then one compact badge a status in 1.1.2's order, its name in `title` and for screen readers. Unhandled
  is `--accent`; the others outlined. The counts are the same `GROUP BY status` query's.
- Moderation (`moderate.php`, `lib/commentary.php`): the comments use task 10's commentary list; each comment's
  checkbox is labelled "Select this comment"; the Ban links, section links and the moderation form are unchanged. The
  talk box on this page had no label text; it gets one for screen readers ("Comment").
- Configuration, creature and user search, and the other editors: nothing they save or show changes.

### Labels: the 105

Task 9 (`PHASE5-TASK9-FORMS-20261031.md`) listed 105 unlabelled controls in 17 admin and editor files. Each is now
labelled: by the label text the page already prints (wrapped or bound with `for`), or by a label for screen readers
only where the page prints none (a row's checkbox, the second box of a pair, a module's checkbox "Select module: name").
The helpers in `lib/output.php` (`resurrection_control_id()`, `resurrection_label_open()`, `resurrection_label_close()`,
`resurrection_label_for()`) print nothing in a 1.1.2 skin; where a control already had an id in 1.1.2 (`masters.php`'s
`id='input'`), that id is kept there.

`tests/tooling/test_ui_popups_admin.py` scans the 17 files. Against `5dafec8` it finds 103 unlabelled controls; task 9's
other 2 are the typed editor's select and input (`lib/typed_editor.php`), which sit inside a `<label>` opened in an
earlier `rawoutput()` call: task 9's scan did not follow it, this one does, and the rendered Game Settings page has them
labelled on both commits. After this task: 0. The render test checks every control on every admin page and popup is
labelled and each id used once.

| File | Unlabelled at `5dafec8` (this scan) | Now |
|---|---|---|
| `mounts.php` | 33 | 0 |
| `companions.php` | 24 | 0 |
| `lib/motd.php` | 8 | 0 |
| `lib/user/user_setupban.php` | 6 | 0 |
| `masters.php`, `untranslated.php`, `lib/commentary.php` | 5 each | 0 |
| `translatortool.php` | 4 | 0 |
| `donators.php` | 3 | 0 |
| `badword.php`, `lib/clan/detail.php`, `modules.php` | 2 each | 0 |
| `creatures.php`, `moderate.php`, `taunt.php`, `user.php` | 1 each | 0 |
| `lib/typed_editor.php` | 0 (task 9 counted 2; labelled already) | 0 |
| Total | 103 (+2) | 0 |

### SQL sweep list

`SqlSweepTest` found three new interpolations; each is reviewed onto `RESCAN-TASK3-20261023.md`: `lib/output.php`
`$legacy` and the escaped `$id` (HTML `id` attributes, "not SQL"), and `lib/mail/case_read.php` `$where` (one of two
fixed clauses from a literal array; the values are bound), with one more `$mail` site (`db_prefix('mail')`).

## Midnight UTC (the game-day rollover)

Task 10 recorded a local run that crossed midnight UTC and failed 10 PHPUnit page renders. The cause: the render tests
(`TemplateRenderTest`, `FormsRenderTest`, `BattleCommentaryRenderTest`) sign a player in with a last hit a minute ahead
and render pages, without pinning the game clock. When a game-day boundary (00:00, 06:00, 12:00, 18:00 UTC with the
installer's midnight epoch and 4 days a day) falls in between, `is_new_day()` is true and the page redirects to
`newday.php`: an empty page, or "Forest combat unavailable".

Reproduced with libfaketime, each PHP process started 30 seconds before 06:00 UTC (a relative offset, so every
subprocess shares the shifted clock; MySQL in Docker is not shifted):

| Test, at `5dafec8`'s code | Result |
|---|---|
| `TemplateRenderTest` | FAIL: "Failed asserting that '' starts with `<!DOCTYPE html>`" |
| `FormsRenderTest` | FAIL: `village.php` empty |
| `BattleCommentaryRenderTest` | FAIL: all 8 |

The fix: `tests/unit/GameClock.php` gives the settings the web shards already use (`_game_clock` in
`tests/tooling/test_web_application.py`): the epoch a minute before now, minus 30 days, 4 days a day, no offset. Each
render test stores them before it renders and restores the old values after (`TemplateRenderTest` replaces its fixed
2026-01-01 epoch with it; `PopupAdminRenderTest` uses it from the start). After the fix, started at 05:59:30 (shifted):
`FormsRenderTest` OK (696 assertions), `TemplateRenderTest` OK (483), `BattleCommentaryRenderTest` OK (8 tests, 681),
`PopupAdminRenderTest` OK; and one combined run started at 05:59:50 and ended at 06:00:52, crossing the boundary: OK
(12 tests, 2,602 assertions). No production code changed for this.

The web test that failed in the same task 10 run, `test_lovers_alternative_choices_daily_authority_http`, already runs
under the shard's pinned clock. Run alone with its clock shifted to 15 seconds before 00:00 UTC, it crossed midnight and
passed (151.7s, ended 00:02:17 shifted). The first attempt of that run, at the next midnight, did not start (a faketime
offset written without its sign) and is not counted. Its task 10 failure was not reproduced and its cause is
not known; no code change is made for it.

## Tests: failing first, passing after

| Test | On `5dafec8` | Now |
|---|---|---|
| `PopupAdminRenderTest::testOldSkinPopupsAndAdminScreensAreUnchanged` (16 popups, 29 admin pages and the petition counts in `jade.htm`, pinned at `5dafec8`) | PASS (the pins are `5dafec8`'s pages) | PASS |
| `PopupAdminRenderTest::testEveryPageRendersInTheResurrectionFrame` | PASS | PASS |
| `PopupAdminRenderTest::testMailPopup` | FAIL (no mail tabs) | PASS |
| `PopupAdminRenderTest::testPetitionMotdAndTranslator` | FAIL (Submit not primary) | PASS |
| `PopupAdminRenderTest::testAdminScreens` | FAIL ("creatures: INPUT q has no label") | PASS |
| `test_ui_popups_admin.py` (10 tests: labels, the scan's self-test, the script, the stylesheet, the docs) | FAIL: 8 failures, 1 error | PASS |
| `TemplateRenderTest`, `FormsRenderTest`, `BattleCommentaryRenderTest` across 06:00 (shifted clock) | FAIL (10 tests) | PASS |
| `SqlSweepTest` with this task's code, before the list was updated | FAIL (3 unlisted values) | PASS |

`PopupAdminRenderTest` passes on PHP 8.4.27 and 8.5.12 (5 tests, 1,740 assertions). It uses only DOM features present
in 8.4 (`querySelector`, `querySelectorAll`, `closest`, `getAttribute`, `textContent`).

Changed tests, none weakened: `FormsRenderTest` (petition Submit is now a primary, as the brief asks; the game clock
pinned beside `selfdelete`, each setting restored), `TemplateRenderTest` and `BattleCommentaryRenderTest` (the game clock
pinned), `test_ui_forms.py` (talkform()'s section chooser is now labelled, so its "left for task 11" list is empty; its
label check accepts the new helpers).

### The old-skin pins in the full suite

The pins hold what the pages print on this class's own install (the player, petition, comment and module lists show the
whole database). Run alone on an empty database the class installs one; in the full suite an earlier test has already
installed the game, and the first version of the pin test skipped there (the full PHPUnit run on PHP 8.4 reported "OK,
but some tests were skipped", 1 skip). It would never have run in CI. The class now sets that install aside: it saves
each table's definition and rows to a file in its work directory, drops the tables (the installer refuses a database
that is not empty, and that guard is unchanged), installs its own, and at the end drops its own and recreates the saved
tables. Checked on a database installed with the recommended modules: `CHECKSUM TABLE` and the auto-increment value of
all 39 tables, and every `SHOW CREATE TABLE`, identical before and after the class ran (on PHP 8.5 and 8.4). The full
suite now runs the pins with no skips.

While another local web shard ran against the same working tree, the pin test failed twice on `modules.php`: a web test
writes a temporary module file into `modules/`, and the page listed it. That comes only from running the two at once
on one tree; CI runs each job on its own checkout, PHPUnit before the shard. Of six runs beside a running shard, two
failed this way and four passed; run without a shard beside it, it has not failed.

## The shard split

`tests/tooling/shard-durations.json` is refreshed from this task's local shard logs (PHP 8.5, MySQL 8.4; every test's
printed duration, 311 tests, where the old table had 133 and gave the rest the median). The 60-minute job timeout is
unchanged.

| | Shard 1 | Shard 2 | Shard 3 | Shard 4 |
|---|---|---|---|---|
| Old table: tests | 77 | 83 | 75 | 76 |
| Old table: this task's measured run | 18.2 min | 21.4 min | 19.7 min | 22.4 min |
| Refreshed table: tests | 51 | 49 | 50 | 161 |
| Refreshed table: predicted from the same times | 20.3 min | 20.5 min | 20.5 min | 20.5 min |

Shard 4 holds the many short static tests. CI's shard jobs ran 13:38 to 23:24 for task 10 on the old split.

## Local verification

| Check | Result |
|---|---|
| PHPUnit, full, PHP 8.4.27, empty MySQL 8.4 (final tree) | OK, 324 tests, 146,691 assertions, no skips |
| PHPUnit, full, PHP 8.5.12, empty MySQL 8.4 (final tree) | OK, 324 tests, 146,691 assertions, no skips |
| Shards 1 to 4 (`scripts/tooling-shards.py`, `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, MySQL 8.4, a fresh database each, after a full PHPUnit), old split | Shard 1 first FAILED (kept on record below), then OK: 77 tests in 18:11; shard 2 OK, 83 in 21:22; shard 3 OK, 75 in 19:43; shard 4 OK, 76 in 22:24 |
| Shard 1 on the refreshed split and the final tree (full PHPUnit first, then the shard) | OK: PHPUnit 324 tests, no skips; shard 1, 51 tests in 20:30 (predicted 20.3 min) |
| `PopupAdminRenderTest` alone, PHP 8.4 and 8.5 | OK, 5 tests, 1,740 assertions, each |
| `test_ui_popups_admin.py`, `test_ui_forms.py`, `test_tooling_shards.py` | OK |
| PHPStan `phpstan.neon`; `phpstan-new.neon` (now also `lib/mail_view.php`, with `lib/showform.php` scanned) | no errors; no errors |
| `composer lint`; `composer validate --strict`; `composer audit` | 0 failures (406 files); valid; no advisories |
| Historical verifier; project validator | PASS (417 files, 11 commits); PASS |
| `scripts/release-upgrade-check.py` | passing (no release yet) |
| `git diff --check`; new files' whitespace; `LICENSE.txt`, `README.txt` | clean; clean; identical to the tag |

Shards 2 to 4 ran before two last changes: the MoTD poll's text and radio markup (below) and the pin test's set-aside
(PHPUnit only). Shard 1, which holds the MoTD web test, and the full PHPUnit runs were repeated after them.

### Shard 1's first run: failed, kept on record

The first shard 1 run stopped on `test_motd_route_authority_http`: `"name='choice' value='4' checked" not found`. The
poll's radio printed its new id between `value` and `checked`. The id now follows `checked`, so the markup the test
reads is 1.1.2's. Its rerun stopped on the same test, a line later: `"O'Reilly (0 - 0%)" not found`. The choice and its
count were in two spans, the text escaped with `ENT_QUOTES`. Each choice now prints 1.1.2's text, "choice (count -
percent%)" with its color codes, through `appoencode()` (each tag it opens closed) in one span; the count is no longer
drawn in `--muted`. The web test is unchanged. The third run passed (77 tests, 18:11).

## Screenshots

Two sheets, not committed, attached to the reply and saved in the project files (`phase5-task11/`): `desktop-sheet.png`
(six 1440x1000 views) and `phone-sheet.png` (six 390x844 views), each tiling the mail inbox, a letter, the MoTD with
its poll, the Grotto with the petition badges (on the phone the badges are in the header, which the phone header
replaces on game pages), Game Settings with its tabs, and the creature list's table in its scroll box. Every view's page
width equals the viewport (no sideways scroll).

## Not changed

- What any page does: queries, links, forms, fields, targets, permissions, tokens.
- Gameplay, balance and text. The strings the Resurrection template adds are labels and names only, each translatable:
  "New", "Select", "Mail" and "Messages" (the tab and pager names), "From:" and "Sent:", "Select this comment", "Select
  module", "Comment", "Section:", "Reason:", "Ban by IP", "Ban by ID", "Bonus per level", "Poll:" and the petition status
  names (1.1.2 printed the counts without them).
- The 1.1.2 skins: every popup and admin page byte for byte (pinned).
- The copyright line and license block on every page and popup; the attribution line and its links in `footer` and
  `popupfoot`.
- The 60-minute job timeout.
