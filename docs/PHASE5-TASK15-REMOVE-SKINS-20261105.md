# Phase 5 task 15: remove the 1.1.2 skins and skin selection (2026-11-05)

Task 15 of the Phase 5 plan (`ROADMAP.md`, `docs/PHASE5-UI-PLAN-20261030.md`). Started from `main` at
`e3d265c374ebb9a3d9f90d7e4bd952e39994fe05` (task 14), as the brief expected. It also records task 14's acceptance
(`docs/PHASE5-TASK14-PORTRAITS-20261105.md`, "Acceptance").

The one template, `templates/resurrection.htm`, replaces the 18 1.1.2 skins. The skins stay byte for byte at
`historical-source-1.1.2`; the tag is not touched. Each change a player, administrator or operator can notice is in
`docs/CHANGES-FROM-1.1.2.md` ("Phase 5 task 15: the 1.1.2 skins removed").

## Files removed

124 files under `templates/`, each blob identical to the same path at `historical-source-1.1.2` (checked: 124 of 124
`git rev-parse` matches). The tag has 129 files there; the other 5 (`ailean/check.gif`, `ailean/larrow.gif`,
`desert/logindragon.gif`, `volcano/logo.gif`, `volcano/logo2.gif`) were removed as unused in `acb31f6` (2026-10-24).

- 18 templates: `Aware.htm`, `Classic.htm`, `Cupertino.htm`, `Red.htm`, `ailean.htm`, `aileans.htm`, `amber.htm`,
  `blarg.htm`, `desert.htm`, `jade.htm`, `mer.htm`, `mourn.htm`, `redmond.htm`, `teksolo.htm`, `vcpp.htm`,
  `volcano.htm`, `yar2.htm`, `yarbrough.htm`.
- 17 stylesheets: `Classic.css`, `Red.css`, `ailean.css`, `amber.css`, `aware2.css`, `blarg.css`, `cupertino.css`,
  `desert.css`, `jade.css`, `mer.css`, `mourn.css`, `redmond.css`, `teksolo.css`, `vcpp.css`, `volcano.css`,
  `yar2.css`, `yarbrough.css` (`aileans.htm` used `ailean.css`, `Cupertino.htm` `cupertino.css`, `Aware.htm`
  `aware2.css`).
- 13 image folders, 89 files: `Red/` 2, `ailean/` 4, `amber/` 2, `blarg/` 3, `cupertino/` 15, `desert/` 10, `jade/` 10,
  `mer/` 10, `mourn/` 10, `redmond/` 6, `teksolo/` 3, `vcpp/` 4, `volcano/` 10.

`templates/` now holds `resurrection.htm` only. Two test fixtures that held old-skin pages are removed with their tests
(below): `tests/fixtures/battle-commentary-old-skin.json`, `tests/fixtures/popup-admin-old-skin.json`.

## Skin selection removed

| What | Where | Now |
|---|---|---|
| The `template` cookie | `lib/template.php` `prepare_template()` read it (a name matching `[A-Za-z0-9_-]+.htm`); `home.php` and `prefs.php` set it for 45 days | Nothing reads or sets it. An old cookie is ignored |
| The home page chooser | `home.php`: "Choose a different display skin:" (a `theme` select) and Choose, when `homeskinselect` was on; its POST set the cookie (an invalid name: 400 "Invalid skin.") | Removed; a posted `template` field is ignored |
| The Preferences Skin row | `prefs.php`: `"template"=>"Skin,theme"`, its default (the cookie, else `defaultskin`), its POST handling (set the cookie), and `template` in `$nonsettings` | Removed; `$post['template']` is dropped with `oldvalues` before the preferences are saved |
| `defaultskin` | `lib/configuration_descriptors.php` ("What skin should be the default?,theme"); `lib/installer/installer_default_settings.php` (`resurrection.htm`; 1.1.2 `jade.htm`) | Declaration and default removed |
| `homeskinselect` | Same two files ("Should the skin selection widget be shown?,bool"; default 1) | Declaration and default removed |
| The `theme` field type | `lib/showform.php` `case "theme"` (a select of `templates/*.htm`); `src/Http/SettingDescriptor.php` accepted `theme` as an enum of the same files | Removed. Nothing else used it: no bundled module, setting or preference declares one. `showform()` draws an unknown type through a module's `showform_field` extension or as a text input; the typed editor refuses it as an unsupported declaration |
| Template loading | `prepare_template()` and `loadtemplate()`: cookie, then `defaultskin`, then `resurrection.htm` | Both load `templates/resurrection.htm` only (`loadtemplate()` keeps its parameter, ignored, for modules) |

Stored `defaultskin` and `homeskinselect` rows on an existing install are ignored: no code reads either setting. No
release exists and every install is fresh (`CLAUDE.md`), so there is no upgrade step to delete them. A fresh install
stores neither (tested).

## Kept exactly as they were

- The required-tag checks in `prepare_template()`: `title`, `headscript`, `script` in the header; `nav`, `stats`,
  `petition`, `motd`, `mail`, `paypal`, `source`, `version`, `copyright` in the header or footer.
- The copyright tag enforcement and its XOR check: the tag the check pushes onto the list as `$y2^$z2` (`common.php`),
  and `$$z`, the copyright line, built from `$lc` and the tamper message as before.
- The session's cache (`templatename`, `templatemtime`): kept. The name is now always `resurrection.htm`, so the
  checks rerun whenever the file's modification time changes, and on a new session.
- The license block and the attribution line in `footer` and `popupfoot`; `templates/resurrection.htm` is unchanged.

**Copyright enforcement proof** (`tests/unit/TemplateRenderTest.php`): a copy of the tree whose
`templates/resurrection.htm` has its two `{copyright}` tags (footer and popup footer) removed renders `home.php` with
"You have one or more errors in your template page!" and "You do not have {copyright} defined in either your header or
footer" twice (once for `copyright` in the list, once for the XOR tag, as in 1.1.2), nothing else missing, and no
copyright line. The same copy with the tags back renders a clean page. Broken copies of the code fail it (below).

Finding, recorded and not changed: with one template, the fallback after a template error
(`$template=loadtemplate("resurrection.htm")`, task 3's replacement for 1.1.2's `jade.htm`) reloads the same file. A
template without `{copyright}` therefore prints the refusal and then renders without the credit line, where in 1.1.2 the
fallback was a different, valid skin. The brief keeps the enforcement exactly as it is; only an edited template can
reach this.

## PHP branches removed

Each branch served only a template without the Resurrection parts, which no longer exists. `resurrection_ui()` (the
switch: the Resurrection template loaded, or not) is removed with every call.

- `lib/output.php`: `resurrection_ui()`; `resurrection_form_script()`'s old-skin early return; `resurrection_control_id()`'s
  `$legacy` id (and the three `masters.php` callers' `'input'`); the old-skin early returns of
  `resurrection_label_open()`, `resurrection_label_close()`, `resurrection_label_for()`; `resurrection_button_class()`'s
  old-skin plain `button`; `resurrection_presentation()`'s second (1.1.2) column, each entry now its class only (kept:
  modules call it); `private_addnav()`'s in-label hotkey marking (`` `H `` highlight and "(X) " prefix) for a `navitem`
  part without `{key}`.
- `lib/pageparts.php`: `loadtemplate()`'s name and fallbacks; the petition counts' 1.1.2 text (`$pet`, `$ued`, the
  colored counts); `resurrection_charpanel_template()` and `charstats()`'s `getcharstats()` table branch (kept:
  `getcharstats()` itself); the `isset($template[...])` guards in `resurrection_scene_html()`,
  `resurrection_portrait_html()`, `resurrection_nav_column()`, `resurrection_icon_html()`,
  `resurrection_phonehead_html()` (and its phone portrait), `resurrection_tabbar_html()` and
  `resurrection_talk_anchor()`.
- `lib/template.php`: the cookie, `defaultskin` and file-exists fallbacks.
- `battle.php`: the `$rzbattle` switch, the "~ ~ ~ Fight ~ ~ ~" divider, "Next round:", "End of Round:" with
  `show_enemies()`, and the old fight output.
- `lib/extended-battle.php`: `show_enemies()`'s 1.1.2 hitpoint listing; it now prints the fighter cards (kept: modules
  call it).
- `lib/battle_view.php`: `resurrection_battle_ui()` and its guards in the action, note, portrait and assemble helpers.
- `lib/commentary.php`: the comment lines (now always the list), the moderation reason's plain text before its box
  (now always its label), the paging links (now always the pager), and the talk form without its class and its
  section chooser's label.
- `moderate.php`: the talk box's label for screen readers, printed only in the template.
- `mail.php`: 1.1.2's table of Inbox and Write links.
- `lib/mail/case_default.php`: the inbox table (scroll images, Check All script), the italic empty and note lines.
- `lib/mail/case_read.php`: the From, Subject and Sent lines, the scroll images, the actions table and its two
  previous and next queries (`docs/RESCAN-TASK3-20261023.md`: `$mail` sites 6 to 4).
- `lib/mail/case_write.php`: the Send button's old-skin class.
- `lib/motd.php`: the "[ Edit | Del ]" links with the inline `confirm()`, `rule.gif` poll bars, `<hr>`, and the poll
  editor's choice outside its label.
- `lib/showform.php`: `case "theme"`; the old-skin form (1.1.2's `prepare_form()` / `showFormTabClick` tab script and
  its spacer, the `formSections` script); the inline `increase()` script and `onClick` + and - buttons. The form is
  always the tabbed, labelled form.
- `lib/typed_editor.php`: the old-skin single page of sections.
- `prefs.php`: the Delete Character button's inline `onClick` and magenta cell.
- `untranslated.php`, `translatortool.php`: the plain 1.1.2 text before each field (now always its label).

Kept, since modules can still reach them: `resurrection_presentation()`, `getcharstats()`, `show_enemies()`,
`loadtemplate()` and `templatereplace()`.

## Images no longer referenced

Found by searching every shipped text file (not `docs/`, `tests/` or `vendor/`) for each file name under `images/`.
At `e3d265c` none was unreferenced (`oldscroll.GIF` was named by `lib/mail/case_default.php`'s
`($row['seen']?"old":"new")."scroll.GIF"`). Now 7 of the 11 are, and none is deleted in this task:

| File | Used until task 15 by |
|---|---|
| `images/headbkg.GIF` | the desert, jade, mer, mourn, volcano, yar2 and yarbrough stylesheets |
| `images/logindragon.gif` | 13 skins' login boxes |
| `images/lscroll.GIF` | the old-skin mail read view; yar2, yarbrough |
| `images/uscroll.GIF` | the same |
| `images/oldscroll.GIF` | the old-skin inbox |
| `images/rule.gif` | the old-skin MoTD poll bars; the blarg, vcpp, yar2 and yarbrough stylesheets |
| `images/title.gif` | Red, amber, redmond, yar2, yarbrough |

Still referenced: `new.gif` (`bios.php`, `lib/commentary.php`), `newscroll.GIF` (`bio.php`, `list.php`,
`viewpetition.php`), `somerights20.gif` (`common.php`), `trans.gif` (`stats.php`).

## Tests retired or changed

Jim's brief authorizes retiring exactly the tests that pin old-skin output. Each one guarded a skin that no longer
exists; none guards anything still in the game.

| Test | Pinned | Why it goes |
|---|---|---|
| `NavigationTest::testAnOldSkinRendersItsNavsAsBefore` and `PINNED_JADE_SHA256` / `PINNED_JADE_LENGTH` | task 6: jade.htm's navs byte for byte, the in-label marking | jade.htm is gone, and so is the marking |
| `NavigationTest`'s key test over jade.htm and Classic.htm; the no-nav column check in jade.htm | task 6 | The same keys are checked in the one template |
| `CharacterPanelTest::testEveryOldSkinRendersItsPanelAsBefore` (`PINNED_OLD_SKINS`) | task 7: every skin's panel table | The skins are gone |
| `CharacterPanelTest::testNoOldSkinShowsAnIcon` | task 13: no icon in a skin | The skins are gone |
| `PresentationTest`: `testAppoencodeUsesClassesInTheResurrectionTemplateOnly`, `testEveryPresentationEntryKeepsItsLegacyMarkupInAnOldSkin` (`LEGACY`, `LEGACY_MAP`) | task 9: the 1.1.2 markup in a skin and before a template loads | Replaced by `testAppoencodeUsesClasses` and `testEveryPresentationEntryIsAClass`: classes with the template loaded and before (an operator page), no `style=`, `bgcolor` or `<font>`, an unknown key nothing. The before-load case fails on `e3d265c`, which printed 1.1.2's markup there |
| `FormsRenderTest::testFormsHaveOnePrimaryLabelledInputsAndOldSkinsKeepTheirButtons` (`OLD_SKIN_BUTTONS`) | task 9: each form's button markup in a skin | Renamed `testFormsHaveOnePrimaryAndLabelledInputs`; the old-skin half goes; it now also checks Preferences has no `template` field, no `.htm` option and no 1.1.2 tab script |
| `BattleCommentaryRenderTest::testOldSkinBattleAndCommentaryAreUnchanged` and its fixture | task 10: battle and commentary pages in jade.htm | The skins are gone |
| `PopupAdminRenderTest::testOldSkinPopupsAndAdminScreensAreUnchanged` and its fixture | task 11: popups and admin pages in jade.htm | The skins are gone |
| `TemplateRenderTest`: the jade.htm render with no phone pieces; "a fresh install stores `defaultskin` `resurrection.htm`" | tasks 3 and 8 | Replaced by the checks below |
| `test_ui_template.py::test_the_old_skins_have_no_phone_pieces` | task 8: a `templates/*.htm` scan of the 18 skins | The skins are gone |
| `test_ui_template.py::test_it_is_the_default_skin` | task 3: `defaultskin` and the jade.htm fallback | Replaced by `test_it_is_the_one_template` |
| `test_ui_template.py` tab bar and phone header | task 8: asserted the `isset($template[...])` guards | Now assert the guards are gone (the parts are checked in the template) |
| `test_ui_forms.py`: three allow-list entries (the map's 1.1.2 column, the old tab script and its spacer); `templates` in `SKIPPED`; `test_the_old_skin_markup_lives_only_in_the_map` | task 9 | The entries are gone (the scan has no exception for them now, a stricter test); `templates/` is scanned; replaced by `test_appoencode_goes_through_the_map_and_the_old_tab_script_is_gone`. The presentation-class and form-script checks read the one-column map |
| `test_ui_battle.py::test_battle_php_drops_the_divider_only_in_the_new_screen` | task 10: "old skins keep the divider" | Renamed `test_battle_php_drops_the_divider`: the divider, `$rzbattle`, "Next round:" and "End of Round:" are gone. `test_shop_links` reads the one-column map |
| `test_ui_popups_admin.py::test_the_label_helpers_print_nothing_in_an_old_skin` | task 11 | Renamed `test_the_label_helpers_always_print_their_label` |

The docstrings of `test_ui_battle.py`, `test_ui_popups_admin.py`, `test_ui_forms.py` and the render tests no longer
claim old-skin coverage. `tests/fixtures/nav-render.php` and `charstats-render.php` load the one template.

## Tests added

- `tests/tooling/test_one_template.py` (new, static): only `templates/resurrection.htm` under `templates/` (tracked
  and on disk); no tracked PHP, JavaScript or template reads or sets the `template` cookie; no code names
  `defaultskin`, `homeskinselect` or a `theme` field; template loading names `resurrection.htm` and no other `.htm`;
  `home.php` and `prefs.php` have no skin control; and the scans find what they look for. Comments are skipped, since
  the comments that tell a removal's history name what was removed.
- `tests/tooling/test_route_inventory.py::test_the_link_scan_reads_the_one_template`: the dead-link scan reads
  `templates/resurrection.htm` and nothing else under `templates/`, and finds no dead link.
- `tests/tooling/test_disabled_endpoints.py::test_no_shipped_file_links_a_removed_file`: the template files it scans
  are `templates/resurrection.htm` alone.
- `tests/unit/TemplateRenderTest.php`: a fresh install stores neither skin setting; the home page has no chooser (no
  "display skin", no `template` field, no form to `home.php`); with `defaultskin` `jade.htm` and `homeskinselect` 1
  stored and a `jade.htm` cookie, the village and the home page are the Resurrection UI (clean, `rz-page`, no `jade`,
  the phone pieces, the village scene); with the stored rows and no cookie, the same; the copyright proof above. The
  stored rows are removed afterwards.

## Failing first, passing after

On `e3d265c` (a worktree at that commit, with this task's tests copied in), the tooling tests fail: 17 of 68 in
`test_one_template`, `test_route_inventory`, `test_disabled_endpoints`, `test_ui_template`, `test_ui_forms`,
`test_ui_battle` and `test_ui_popups_admin` (every new or changed test above except the scans' self-test). For example:

```
FAIL: test_only_the_resurrection_template_is_under_templates
AssertionError: Lists differ: ['templates/resurrection.htm'] != ['templates/Aware.htm', 'templates/Classic.css', ...
FAIL: test_no_code_reads_or_sets_the_template_cookie
AssertionError: Lists differ: [] != ["home.php: setcookie('template'", ...
FAIL: test_no_skin_setting_or_theme_type
AssertionError: Lists differ: [] != ['home.php: homeskinselect', 'home.php: ,theme"', ...
FAIL: test_the_link_scan_reads_the_one_template
AssertionError: Lists differ: [.../templates/resurrection.htm'] != [.../templates/Aware.htm'), ...
Ran 68 tests: FAILED (failures=17)
```

After: the same 68 pass.

`TemplateRenderTest` on `e3d265c` fails at each new check, run past the one before:

1. Line 92, a fresh install stores the skin settings: expected `'0'`, actual `'2'`.
2. With that line skipped, line 113: the home page contains the skin chooser ("display skin").
3. With the chooser checks skipped, line 208: the village with a `jade.htm` cookie is jade.htm's table page, not the
   Resurrection page.
4. With the cookie checks skipped, line 220: the home page with `defaultskin` `jade.htm` stored and no cookie is
   jade.htm (`templates/jade/tlt.gif` and the rest).

The copyright proof passes on `e3d265c` (the enforcement was already there) and fails on broken copies of this task's
`lib/template.php`:

- `copyright` removed from the list and the XOR push removed: line 241, no refusal on the page.
- The XOR push alone removed: line 244, the message once instead of twice (`Failed asserting that 1 is identical to
  2`).

With `lib/template.php` restored, `TemplateRenderTest` passes (1 test, 572 assertions).

## Local verification

Supporting evidence only; CI is the acceptance.

| Check | Result |
|---|---|
| PHPUnit, PHP 8.5.12 (`resurrection_test` on a disposable MySQL 8.4, emptied first) | OK (341 tests, 146442 assertions) |
| PHPUnit, PHP 8.4 (same database, emptied first) | OK (341 tests, 146442 assertions) |
| Shards 1 to 4 (`scripts/tooling-shards.py`, `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, a MySQL 8.4 each, all four at once, PHPUnit first in each) | OK: PHPUnit OK 341 in each (70 s); shard 1, 56 tests in 15.4 min; shard 2, 54 in 15.6; shard 3, 57 in 15.5; shard 4, 163 in 15.0; no failure, error or skip |
| PHPStan, `phpstan.neon` and `phpstan-new.neon` | No errors, both |
| `composer lint` (PHP 8.5) | PHP lint: 407 files, 0 failures |
| `composer validate --strict`, `composer install`, `composer audit` | Valid; install up to date; no security vulnerability advisories |
| Historical verifier, project validator | PASS: 417 historical files, 11 commits, tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`; project validator PASS, 969 tracked files |
| Repository hygiene, `git diff --cached --check` | Clean: only this task's changes; no whitespace errors |

The shards are balanced within 32 seconds of each other, so `tests/tooling/shard-durations.json` is not refreshed:
the new tests are static (a few seconds together) and so were the retired ones. (Task 14's local run, on a busier
machine, took 29 to 30 minutes a shard; CI is the measure.)

## Screenshots

Two sheets, not committed: the signed-out home page and Preferences, desktop (1440x1000) and phone (390x844). Neither
page has a skin control.

## Not changed

- `templates/resurrection.htm`, `assets/`, `LICENSE.txt`, `README.txt`, the copyright line and the attribution line.
- The `historical-source-1.1.2` tag and its objects.
- The images under `images/` (listed above, none deleted).
- The 60-minute job timeout, the shard runner and the duration table.
- Version strings.
