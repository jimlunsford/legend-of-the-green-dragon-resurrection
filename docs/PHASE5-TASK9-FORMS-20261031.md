# Phase 5 task 9: forms, buttons and tables (2026-10-31)

Started from `main` at `5b3ca63` (task 8), as the brief expected. Plan: `PHASE5-UI-PLAN-20261030.md`. Spec:
`UI-DESIGN-SPEC.md` (Palette notes; Components, "Buttons", "Primary actions", "Inputs", "Tabs", "Tables",
"Presentation as classes"). Ledger: `CHANGES-FROM-1.1.2.md` (the task 9 rows).

## Task 8 accepted

Recorded in `PHASE5-TASK8-PHONE-20261031.md` ("Accepted") and ticked in `ROADMAP.md`: Modern core 41159564504,
SUCCESS, all 12 jobs; Baseline integrity 41159564474, SUCCESS; both on `5b3ca63`. No shard passed 25 minutes; the
longest was PHP 8.5 / MySQL 8.4 shard 2 at 22:03.

## Buttons

Both kinds are 44px tall, 16px each side, an 8px radius, Alegreya Sans 16px, the focus ring. Buttons side by side keep
an 8px gap (`margin: 4px 8px 4px 0`) and wrap. No full-width buttons; the phone keeps them at their width too (the
brief allowed a lone primary to stretch; none needed it).

| Kind | Look | Where |
|---|---|---|
| Primary (`rz-button-primary`) | `--accent` fill and border, `--ground` label (8.74:1), weight 700; hover `--accent-hover` | at most one per form; the list below |
| Secondary (`button`, the default) | no fill, 1px `--input-border` outline (3.04:1 on `--panel`), `--text` label, weight 500; hover `--accent` outline | every other button |
| Danger (`rz-button-danger`) | secondary with a `--damage` outline and label (6.6:1) | preferences' Delete Character |
| Link-styled (`rz-link-button`) | the link's look, the button's focus ring | the content editors' delete buttons |

`resurrection_button_class($primary)` in `lib/output.php` returns `"button rz-button-primary"` only in the Resurrection
template and `"button"` otherwise, so a 1.1.2 skin's button markup is unchanged, byte for byte (pinned by
`FormsRenderTest` against the markup 5b3ca63 printed).

### The primary list

| Form | Code |
|---|---|
| Log in | the login part (unchanged since task 3) |
| Create a character | `create.php` |
| Bank deposit, borrow, withdraw | `bank.php` (op `deposit`, `borrow`, `withdraw`) |
| Weapon and armor shop buy | `lib/equipment_authority.php` (each affordable item's form; the map's `shop-button`) |
| The inn's room | `lib/inn/inn_room.php` through `resurrection_inn_button(..., true)` |
| The healer's heal | `healer.php` (`op=buy`, each potion's form) through `resurrection_service_form(..., true)`; the companion heal stays secondary |
| Commentary Add | `lib/commentary.php` (`talkform()`) |
| Preferences Save | `prefs.php` through `showform(..., $primary = true)` |
| Mail Send | `lib/mail/case_write.php` (`class` added only in the Resurrection template: 5b3ca63's `<button type="submit">` had none) |

Every other button is secondary: the bank's Preview Transfer and Complete Transfer, mail Search, petition Submit, every
admin and editor button, and so on. Fight and Run are task 10's and unchanged. A shop page has one primary per item's
form, which is the brief's rule (one per form); the page shows several.

## Inputs

Text, password, number and email inputs and selects are 44px tall, a textarea at least 88px; `--panel` fill (never
`--raised`), 1px `--input-border` outline (3.04:1 on `--panel`, 3.16:1 on `--header`), 8px radius, `--text`, the focus
ring. Placeholder text is `--muted`, 6.76:1 on `--panel`. Checkboxes and radios are 20px in `--accent`; when inside
their label, the label is the 44px tap target (`label:has(> input[type=radio])`).

### Labels

Every control a player fills in has a `<label for>` or sits inside its `<label>`, in every skin (labels are new
elements around the same text; only button markup is pinned byte for byte).

- `showform()` (`lib/showform.php`): each control gets an id from `showform_control_id($form, $key)`
  (`showform<n>-<key>`, letters, digits, `-` and `_`; a resizeable textarea keeps 1.1.2's `textarea<key>`), and its
  row's label cell is a `<label for>`. A checklist, radio group or bitfield labels each choice instead
  (`<label><input type='radio'> Choice</label>`). Hidden and view-only rows and module extensions get no `for`.
- `lib/forms.php` `previewfield()` prints `input<name>`; its two callers label it: commentary (`viewcommentary()`
  wraps the "Interject your own commentary?" line in `<label for='inputinsertcommentary'>`) and mail write
  (`Body:`, `for="inputbody"`).
- Hand-fixed pages: `create.php` (name, password, confirm, email; the sex radios were already inside labels),
  `bank.php` (deposit, borrow, withdraw amounts; transfer amount and recipient, both forms; the confirm select),
  `lib/mail/case_address.php` (To, and the Game Master's From), `lib/mail/case_write.php` (To when there is a choice,
  Subject, Body), `lib/petition/petition_default.php` (character name and email signed out, the description),
  `prefs.php` (through `showform()`). The inn's room, the healer and the shops have buttons only.

A label never straddles an open color span: appoencode() keeps one color span open across calls, and a `</span>`
inside a `<label>` would close the label in the browser. So each label is printed as: close the color (`` `0 ``),
open the label, the text in its own colors, close the color, close the label, re-open the color the text was in.

### Unlabelled admin and editor inputs, left for task 11

Found by the same static scan as `test_ui_forms.py` (a control with no `<label for>` and no wrapping label in its
file). Not fixed, as the brief says.

| File | Count | Controls (name) |
|---|---|---|
| `badword.php` | 2 | word, word |
| `companions.php` | 24 | companion[name], companion[dyingtext], companion[description], companion[jointext], companion[category], companion[companionlocation], companion[maxhitpoints], companion[maxhitpointsperlevel], and 16 more `companion[...]` fields |
| `creatures.php` | 1 | q (search) |
| `donators.php` | 3 | name, amt, reason |
| `lib/clan/detail.php` | 2 | clanname, clanshort (the superuser's clan rename) |
| `lib/commentary.php` | 5 | comment[...] checkboxes (2 sites), reason0, reason, section (moderation, and moderate.php's all-sections chooser) |
| `lib/motd.php` | 8 | choice, subject, body, changeauthor, changedate, subject, body, opt[] (the MoTD and poll editors) |
| `lib/typed_editor.php` | 2 | the typed editor's select and input |
| `lib/user/user_setupban.php` | 6 | type, ip, type, id, duration, reason |
| `masters.php` | 5 | level, name, weapon, lose, win |
| `moderate.php` | 1 | mod[...] |
| `modules.php` | 2 | module[] (two lists) |
| `mounts.php` | 33 | mount[mountname], mount[mountdesc], and 31 more `mount[...]` fields |
| `taunt.php` | 1 | taunt |
| `translatortool.php` | 4 | uri, text, trans, u |
| `untranslated.php` | 5 | ns, intext, outtext, intext, outtext |
| `user.php` | 1 | q (search) |

105 controls in 17 files. The same scan also finds player forms the brief did not name for task 9; they are listed here
for a later task (task 11 restyles mail), not fixed:

| File | Count | Controls (name) |
|---|---|---|
| `clan.php` | 2 | clanname, clanshort (founding a clan) |
| `lib/clan/clan_motd.php` | 3 | clanmotd, clandesc, customsay |
| `lib/graveyard/case_haunt.php`, `case_haunt2.php` | 2 | name |
| `lib/inn/inn_bartender.php` | 1 | testtext |
| `lib/mail/case_default.php` | 1 | msg[] (the inbox's checkboxes) |
| `lib/newday/dragonpointspend.php` | 1 | the dragon point inputs |
| `list.php` | 1 | name |
| `motd.php` | 1 | month |
| `modules/cedrikspotions.php` | 6 | gemcount, wish (5) |
| `modules/dag/misc_functions.php`, `modules/dag/run.php` | 14 | setter, getter, target, s, d, d, contractname, amount (bounties) |
| `modules/darkhorse.php` | 2 | testtext, name |
| `modules/game_dice.php`, `modules/game_stones.php` | 2 | bet |

36 controls. Total 141.

## Tabs (showform)

In the Resurrection template `showform()` no longer prints 1.1.2's inline script. It records where the tab list goes,
and once the sections are known splices in `showform_tabs()`:

```html
<div class='rz-tabs' role='tablist' id='showFormSection1' data-rz-tabs='1' hidden>
  <button type='button' class='rz-tab-button' role='tab' id='showFormButton1' aria-controls='showFormTable1'
    aria-selected='true' data-rz-tab='1'>Account Preferences</button>
  <button ... id='showFormButton2' aria-controls='showFormTable2' aria-selected='false' tabindex='-1' ...>...</button>
</div>
```

Each section's table is `role='tabpanel' aria-labelledby='showFormButton<n>'`. `assets/ui/showform.js` (static, ASCII,
loaded once per page with `defer`) shows the list, hides every section but the chosen one, and on a click or Left,
Right, Home and End moves the selection, `aria-selected` and the roving `tabindex`. Like 1.1.2's script it adds the
hidden `showFormTabIndex` field to the form and keeps it on the chosen section, so a save returns to the same section;
the server prints no new field, so every `showform()` form posts the fields it did before. Without the script the list stays hidden and every section shows (as
1.1.2 did without JavaScript). A player with tabs turned off (`tabconfig` 0) gets no tab list. The same script runs
the resizeable textarea's + and - (`data-rz-resize`, rows 4 to 49 as 1.1.2's `increase()`) and the Delete Character
confirmation (`data-rz-confirm`). A 1.1.2 skin keeps 1.1.2's script and markup.

On a phone the form stacks (`rz-showform`, `rz-form-section`, classes printed only in the Resurrection template):
each label above its control, the control full width.

## Tables

Heading rows (`trhead`) are `--raised` with `--muted` labels at 13px, uppercase, `--tracking-label` (0.12em), weight
700; they hold labels only, so the rule that controls never sit on `--raised` holds (spec, Palette notes). Body rows
alternate `trlight` (`--panel`) and `trdark` (`--header`), `--text` 13.99:1 and 14.53:1 on them. `trhilight` (the
player's own row) is `--panel` with a 3px `--accent` marker on its left. A table scrolls sideways in its own box
(`display: block; overflow-x: auto; max-width: 100%`); a table with a heading row, and a grid table, has a 1px
`--border` edge and the control radius, so the box's end shows. `align='right'` cells stay right-aligned (the weapon
and armor shops' damage and cost columns, the bank, the lists). Grid tables (`rz-grid`, 1.1.2's `bgcolor='#999999'`
behind 1px cell spacing) draw 1px `--border` lines.

## Inline presentation

At 5b3ca63, `git grep -i "<font|bgcolor=|style="` over the PHP outside `vendor/`, `tests/` and `templates/` found 63
uses on 61 lines in 45 files. Each became a key of `resurrection_presentation()` in `lib/output.php`: the class in the
Resurrection template, 1.1.2's exact markup in a 1.1.2 skin (`PresentationTest` pins every key's old markup; each one is
found verbatim in 5b3ca63's source). `appoencode()`'s `` `c ``, `` `> `` and `` `< `` go through the same map.

| Was | Became (Resurrection) | Key | Sites |
|---|---|---|---|
| `` `c `` `<div align='center'>` | `rz-center` (text centered; a table inside centered) | center | `appoencode()` |
| `` `> `` / `` `< `` `<div style='float: right; clear: right;'>` (left) | `rz-float-right` / `rz-float-left` | float-right, float-left | `appoencode()` (2) |
| `bgcolor='#999999'` on a grid table | `rz-grid` (1px `--border` lines) | grid | armoreditor, companions, creatures, donators, list, masters, modules (2), mounts, stats, taunt, weaponeditor, `lib/modules.php`, `lib/user/user_removeban.php`, `modules/dag/misc_functions.php` (2), `modules/dag/run.php`, `modules/drinks/misc_functions.php` (18) |
| `<font size='+1'>` ... `</font>` | `<span class='rz-larger'>` (1.25em) | larger, larger-end | badword (2 opening, 2 closing), `lib/ramius_outcomes.php` |
| `<span style='color: #9900FF'>` | `colBlueViolet` (`` `V ``) | violet | inn, `modules/cedrikspotions.php`, `modules/dag/run.php`, `modules/drinks/run.php`, `modules/lovers.php`, `modules/sethsong.php` (6) |
| `<span style='color: #787878'>` | `colLtBlack` (`` `) ``) | grey | `modules/darkhorse.php` |
| `<span style='color: white'>`, `<span style='color: gold'>` | `colLtWhite` (`` `& ``), `colLtYellow` (`` `^ ``) | white, gold | `lib/extended-battle.php` (the pickpocket line) |
| `style='display:inline'` on a form (and `display:inline;margin:0`, double-quoted variant) | `rz-inline` | inline-form, inline-form-flush, inline-form-dq | `lib/admin_authority.php` (2), `lib/clan/clan_membership.php`, `lib/commentary.php`, `lib/content_editor.php`, `lib/equipment_authority.php`, `lib/motd.php`, `lib/mount_companion_editor.php`, `lib/user/user_.php`, news, viewpetition (2) |
| A button styled as a link (`background:none;border:0;...;text-decoration:underline`) | `rz-link-button` | link-button | `lib/content_editor.php`, `lib/mount_companion_editor.php` |
| The shops' link-styled buy button | `button rz-button-primary` | shop-button | `lib/equipment_authority.php` |
| `<span style='font-family: fixed-width'>` | `rz-typed` (monospace, wraps) | typed | viewpetition (2) |
| `<pre style="white-space:pre-wrap">` | `<pre class='rz-typed'>` | pre-wrap | `lib/stored_state_repair.php` |
| `<div style='padding-left: 20pt;'>` | `rz-indent` | indent | `lib/dump_item.php` |
| `<div style='margin-left: 15px; padding-left: 15px;'>` | `rz-indent` | clan-motd | `lib/clan/clan_default.php` |
| `class='input' style='width: 66%'` | `class='input rz-wide'` | clan-textarea | `lib/clan/clan_motd.php` (2) |
| `<span style='font-size: 20px'>` | `rz-larger` | warning | `lib/user/user_edit.php` |
| `<a ... style='text-decoration: none'>` (commentary speaker links) | `rz-plain-link` | plain-link | `lib/commentary.php` (2) |
| `<td style='background-color:#FF00FF'>` (Delete Character's cell) | no background | danger-cell | prefs |

59 uses converted. Kept, each with its reason (the allow-list in `tests/tooling/test_ui_forms.py`):

| Kept | Why |
|---|---|
| `lib/output.php`, inside `resurrection_presentation()` | the one place 1.1.2's markup lives, for 1.1.2 skins only, until task 15 removes them |
| `lib/showform.php`, 1.1.2's tab script (2 uses: the tab `<div>`s and the spacer) | printed only in a 1.1.2 skin (the branch after `if ($rz)`) |
| `lib/about/about_listmodules.php`, the module list's `bgcolor='#999999'` | the about page's 1.1.2 strings stay verbatim: the license asks that "original text from the about page must be preserved", and `test_license_text.py` holds every shown string of the five about files to the tag. The stylesheet draws `table[bgcolor="#999999"]` exactly as `rz-grid` |
| `lib/installer/installer_sqlstatements.php`, the Gypsy Bandit's creature script | data: the stored script `CreatureAi::supported()` recognizes by its SHA-256, so its text cannot change; the line it describes is printed by `lib/extended-battle.php`, which now uses classes |

No case was a color a player chose (those are color codes, already classes).

## Defect found, not fixed

`previewfield()` (`lib/forms.php`, unchanged since 1.1.2: the historical tag has the same lines) prints an inline
script that calls `input<name>.addEvent('keyup', ...)`, a MooTools method the game never loads. In every browser it
throws `TypeError: inputinsertcommentary.addEvent is not a function` (and `inputbody` on mail write), so the comment
draft it meant to save in `localStorage` was never saved, in 1.1.2 or now. Fixing it either removes a feature that
never worked or turns on a new one (restoring a draft into every comment box), which is a behavior choice; the
commentary is task 10's, so it is recorded here for task 10 and left as it is. The pages work; the error is in the
browser console only.

## Browser check (local evidence)

Chromium (Playwright), local game on PHP 8.5.12 and MySQL 8.4, player Aric (level 7, 512 gold), signed in through the
login form. Each page full height; every link, button, input, select and textarea measured (a checkbox or radio by its
label).

| Page | Viewport | Page width / window | Targets | Under 44px | Unlabelled | Primaries per form |
|---|---|---|---|---|---|---|
| Preferences | 390x844 | 390 / 390 | 22 | 0 | 0 | 1 |
| Bank (deposit) | 390x844 | 390 / 390 | 18 | 0 | 0 | 1 |
| Weapon shop | 390x844 | 390 / 390 | 15 | 0 | 0 | 1, 1 |
| Mail write | 390x844 | 390 / 390 | 10 | 0 | 0 | 1 |
| Create a character (signed out) | 390x844 | 390 / 390 | 22 | 0 | 0 | 1 |
| Preferences | 320x640 | 320 / 320 | 22 | 0 | 0 | 1 |
| Bank (deposit) | 320x640 | 320 / 320 | 18 | 0 | 0 | 1 |
| Weapon shop | 320x640 | 320 / 320 | 15 | 0 | 0 | 1, 1 |
| Mail write | 320x640 | 320 / 320 | 10 | 0 | 0 | 1 |
| Create a character (signed out) | 320x640 | 320 / 320 | 22 | 0 | 0 | 1 |

No page scrolls sideways. Two tables are wider than their box and scroll inside it: the create form's table at 320
(312 in a 246 box) and the mail popup's Inbox/Write table at 320 (128 in 123). Before the phone stacking rule the
preferences table was 576 wide in a 316 box at 390; it now fits (296).

Tabs, at 390, 320 and 1440: three tabs, 44px tall, the first `aria-selected`. A click on the second: `false:hidden
true:shown false:hidden`. End: focus on "Game Behavior Preferences", `showFormTabIndex` 3. Home: "Account Preferences",
1. A save from the second tab: "Settings Saved", and the page comes back on the second tab (`false,true,false`,
`showFormTabIndex` 2). The only console errors were the `addEvent` defect above.

## Tests: failing first, passing after

New tests:

- `tests/tooling/test_ui_forms.py` (static, 12 tests): no `<font>`, `bgcolor=` or `style=` outside the allow-list (and
  no stale entry); appoencode's codes through the map and the legacy tab script only in its 1.1.2 branch; every input
  in the hand-fixed pages has a bound label (operator controls named); previewfield's inputs labelled by their
  callers; showform labels every control; showform's tabs (roles, `aria-selected`, no `on...=`), the script tag once;
  `showform.js` (ASCII, tabs, keys, resize range, confirm; no `eval`, `innerHTML`, `document.write`, URLs); the
  stylesheet's buttons, inputs (`--panel`, never `--raised`; placeholder `--muted`), tabs, tables (raised heading,
  zebra, marker, scroll box, right alignment); every class the map prints is styled.
- `tests/unit/PresentationTest.php` (PHPUnit, no database): `appoencode()` of `` `c ``, `` `> ``, `` `< `` with the
  real templates' parts: classes in `resurrection.htm`, 5b3ca63's exact bytes in `jade.htm`; every key of the map, old
  markup pinned in jade.htm, no `style=`, `bgcolor` or `<font` in the Resurrection template.
- `tests/unit/FormsRenderTest.php` (PHPUnit with the disposable database, PHP CLI renders as TemplateRenderTest): a
  level 7 player with 5,000 gold, 2,000 in the bank and 30 of 70 hitpoints, self-delete on (restored afterwards). Pages:
  create.php and petition.php signed out; village (commentary), bank deposit, withdraw and borrow, weapons, armor, the
  inn's room, the healer's full heal, preferences, mail address, mail write. In the Resurrection template: at most one
  primary per form, exactly one in each named form; every control in the content labelled, and ids unique;
  preferences' tab list (hidden until the script, one `aria-selected`, each tab's panel and `aria-labelledby`, the
  no `showFormTabIndex` field from the server: the script adds it), no `on...` attribute and no inline script in the content, `showform.js` once, Delete
  Character's `data-rz-confirm`. In jade.htm (the skin cookie): no `rz-page`, no `rz-button-primary`, preferences keeps
  1.1.2's tab script, and every page's button markup equals the list captured from 5b3ca63 for the same page and
  state (the capture: the same test run in a 5b3ca63 worktree, writing what it found).

Changed for the intended markup: `tests/tooling/test_web_application.py`, `test_create_login_rotate_render_logout`.
Its browser-like reader of the preferences form matched `<select name='x'>` exactly, so with each select's new id it
found no select and would have posted none; it now reads `<select name='x' ...>`, and the four checks that the new
account's booleans render "No" selected name the id (`<select name='emailonmail' id='showform1-emailonmail'>`). What it
guards is unchanged: the form a browser would post saves "0" for each. The first shard run failed there (all four
shards: shards 2 and 4 run it as their prerequisite), and in shard 3 on `test_license_text`'s about page check (below,
"Kept"), before both fixes.

On `5b3ca63` with the new tests copied in (git worktree):

- `test_ui_forms`: 12 of 12 fail (8 failures, 4 errors): the inline scan lists 57 lines (the 61, less the four
  allowed); create.php's four inputs
  without labels; no map, no `showform.js`, no tab markup, the old stylesheet values.
- `PresentationTest`: 2 failures (`appoencode()` prints `<div align='center'>` in the Resurrection template; no
  `resurrection_presentation()`).
- `FormsRenderTest`: fails at its first page: "create.php: create.php?op=create, Failed asserting that 0 is identical
  to 1" (no primary).

On a broken copy of the new tree (one change at a time, each restored), `FormsRenderTest`:

| Change | Result |
|---|---|
| The primary class printed in old skins too | fails: jade.htm's create.php button markup differs |
| Bank deposit not primary | fails: 0 primaries in `bank.php?op=depositfinish` |
| A second primary in the preferences form | fails: 2 primaries in `prefs.php?op=save` |
| A tab with `onclick` | fails: "Failed asserting that 'onclick' starts not with "on"" |
| create.php's name label removed | fails: "create.php: INPUT name" unlabelled |
| The shops' old-skin button style changed by one property | fails: weapons.php's jade.htm button markup differs |

On the new tree all pass: `test_ui_forms` 12 tests OK; `PresentationTest` 2 tests, 78 assertions; `FormsRenderTest`
1 test, 653 assertions; `test_ui_template` and `test_ui_tokens` 30 tests OK.

## Local verification

Local results are supporting evidence; CI is the acceptance. PHP 8.5.12, MySQL 8.4 in Docker, `RESURRECTION_REQUIRE_PHP=1`.

| Check | Result |
|---|---|
| PHPUnit, full (each shard's run) | OK, 309 tests, 144,184 assertions |
| Shard 1 | OK, 72 tests, 17.4 min |
| Shard 2 | OK, 79 tests (78 assigned and 1 prerequisite), 20.6 min |
| Shard 3 | OK, 71 tests (70 and 1 prerequisite), 19.5 min |
| Shard 4 | OK, 72 tests (71 and 1 prerequisite), 20.7 min |
| PHPStan `phpstan.neon` and `phpstan-new.neon` | no errors |
| `composer lint` | 401 files, 0 failures |
| `composer validate --strict`, `composer audit` | valid; no advisories |
| Historical verifier, project validator | PASS, PASS (417 files, 11 commits) |
| `git diff --check`; `LICENSE.txt`, `README.txt` against the tag | clean; byte-identical |
| `scripts/release-upgrade-check.py` | passing (no release yet) |

The shards ran three times; the runner stops at the first failure. Run 1: shards 1, 2 and 4 failed in
`test_create_login_rotate_render_logout` (test_web_application.py:10630, the `<select name='emailonmail'>` regex; see
"Changed for the intended markup") and shard 3 in `test_about_page_keeps_every_original_copyright_line_and_sentence`
(the about page's `bgcolor`, now kept). Run 2: shard 4 failed in `test_creature_and_master_editor_authority_http`
(test_web_application.py:7673): the creature editor's form fields, expected exactly, gained `showFormTabIndex`, which
the server then printed. The fix was in the code, not the test: like 1.1.2's script, `showform.js` now adds that field,
so every `showform()` form's fields are as before. Run 3, above, passed.

## Screenshots

Two sheets, not committed, attached to the task's reply: a desktop sheet (1440x1000) and a phone sheet (390x844), each
tiling preferences (with its tabs), the bank's deposit, the weapon shop and create a character (signed out).

## Not changed

- Gameplay, balance and text. No translated string changed (the mail address label wraps the existing "Address: To:"
  string). What every form posts is unchanged.
- Fight and Run (task 10). The admin and editor forms' labels (task 11; listed above).
- The 1.1.2 skins: every button's markup and every converted inline style's markup byte for byte (tests above); they
  gain only the new `<label>` elements.
- The copyright line and license block on every page; the attribution line and its links.
