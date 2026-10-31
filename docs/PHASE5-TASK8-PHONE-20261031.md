# Phase 5 task 8: phone layout (2026-10-31)

Started from `main` at `2a81203` (task 7), as the brief expected. Plan: `PHASE5-UI-PLAN-20261030.md`. Spec:
`UI-DESIGN-SPEC.md` (Layout, "Phone"; Components, "Phone header", "Nav tiles", "More section", "Tab bar";
Accessibility). Ledger: `CHANGES-FROM-1.1.2.md` (the task 8 rows).

## Task 7 accepted

Recorded in `PHASE5-TASK7-CHARACTER-PANEL-20261030.md` ("Accepted") and ticked in `ROADMAP.md`: Modern core
41149508932, SUCCESS, all 12 jobs; Baseline integrity 41149508958, SUCCESS; both on `2a81203`. The note there: PHP 8.5
shard 2 took 22:07, the longest shard so far, under the 30-minute mark.

## The tab bar decision (Jim, 2026-10-31)

The approved mockup's tab bar had a "Village" tab. A fixed link to the village would break forced navigation: a player
may follow only the navs the current page allowed (in a fight, a shop, the graveyard, the village is not one of them).
So the tabs are anchors on the same page, safe everywhere:

| Tab | Target | Shown when |
|---|---|---|
| Go | `#rz-nav`, the nav column (`navstart` part) | the page shows its nav column (not in a forest fight) |
| Character | `#rz-character`, the character panel | signed in |
| Talk | `#rz-talk`, an empty anchor `viewcommentary()` prints before the page's first commentary | the page rendered commentary |
| More | `#rz-more`, the More section: MoTD, Petition for Help, Credits where the footer's rule allows it | always |

`resurrection_tabbar_html()` (`lib/pageparts.php`) builds it from the template's `tabbar` and `tab` parts; without the
parts (the 1.1.2 skins) or on an operator page it returns nothing, and the popup parts have no `{tabbar}`. The static
`assets/ui/tabs.js` marks the tab whose section is in view (`rz-tab-current`, `aria-current="location"`); without it
every tab still jumps. Labels are 13px (`--size-small`), not the brief's 12px: 13px is the spec's text floor.

## The order

Below 900px (`--breakpoint-stack`) the stylesheet orders the stacked columns: the content (title, scene, story,
forms) `order: 1`, the nav column `2`, the character panel `3`; the More section and the footer follow in the source.
The source order is unchanged (nav column, then content, then panel), so the desktop page, its skip link and keyboard
order, and the 1.1.2 skins stay as they were. Between 600 and 900px (a tablet) the same order with the desktop header
and nav rows. Measured positions (top of each block, page coordinates, 390x844):

| Page | Content | Navs | Panel | More | Footer |
|---|---|---|---|---|---|
| Home, signed out | 118 | 1350 | 1632 | 1781 | 1956 |
| Village | 128 | 874 | 1739 | 2386 | 2508 |
| Forest fight | 128 | (none) | 531 | 1178 | 1301 |
| Graveyard, dead | 128 | 868 | 1021 | 1668 | 1790 |
| Weapon shop | 128 | 1920 | 1992 | 2640 | 2762 |

## The header

Below 600px, signed in, the header is the `phonehead` part (`resurrection_phonehead_html()`): the race portrait (44px),
the name (Cinzel 700, 17px, one line, ellipsis), the subtitle, the mail control with its badge (44px), and under them
compact (6px) HP and XP bars with the label left and numbers right. The stylesheet hides the wordmark row when the part
is present (`.rz-header:has(.rz-phonehead) .rz-header-inner`). Measured text of the header:

- Alive (village, fight, weapon shop): "Farmboy Aric / Level 7 Elf · 12 turns · 512 gold / Mail / HP 63 / 70 / XP 5025 / 6641".
- Dead (graveyard): "Farmboy Aric / Level 7 Elf · 6 torments · 40 favor / Mail / Soul 62 / 85 / XP 5025 / 6641".

Signed out (and in popups) the wordmark stays on one line: its size is `min(--size-wordmark, (100vw - 32px) / 18)`
(the title is about 17.6 times its font size wide), measured 19.9px and 358px wide at 390, 16px and 288px wide at 320,
with the MoTD and Petition controls on the row below at the right.

## Browser check (local evidence)

Pre-installed Playwright (Chromium), against a disposable local install (`php -S`, MySQL 8.4), player "Aric", level 7
elf. Script: every visible link, button, input and select measured with `getBoundingClientRect()`; horizontal scroll
is `scrollWidth > clientWidth`; the footer check scrolls to the bottom and compares the footer's bottom with the tab
bar's top.

| Page | Viewport | Page width | Side scroll | Targets | Under 44px | Tabs (each 52px tall) | Footer bottom / bar top |
|---|---|---|---|---|---|---|---|
| Home, signed out | 390x844 | 390 | no | 24 | 0 | Go, More | 791 / 791 |
| Village | 390x844 | 390 | no | 36 | 0 | Go, Character, Talk, More | 791 / 791 |
| Forest fight | 390x844 | 390 | no | 13 | 0 | Character, More | 791 / 791 |
| Graveyard, dead | 390x844 | 390 | no | 16 | 0 | Go, Character, More | 791 / 791 |
| Weapon shop | 390x844 | 390 | no | 15 | 0 | Go, Character, More | 791 / 791 |
| Mail popup | 390x844 | 390 | no | 6 | 0 | (no bar) | 742 / none |
| Home, signed out | 320x640 | 320 | no | 24 | 0 | Go, More | 587 / 587 |
| Village | 320x640 | 320 | no | 36 | 0 | Go, Character, Talk, More | 587 / 587 |
| Forest fight | 320x640 | 320 | no | 13 | 0 | Character, More | 587 / 587 |
| Graveyard, dead | 320x640 | 320 | no | 16 | 0 | Go, Character, More | 587 / 587 |
| Weapon shop | 320x640 | 320 | no | 15 | 0 | Go, Character, More | 587 / 587 |
| Mail popup | 320x640 | 320 | no | 6 | 0 | (no bar) | 640 / none |

The bar is 53px (52px tabs and its 1px border top); tab widths 98px (four tabs at 390), 80px (four at 320), 130 or
107px (three), 195 or 160px (two). At 768x1024 (tablet) no page scrolls sideways, the bar is not shown (display none,
0px tall) and the order is the phone's. Every tab's target exists on its page.

The first run found, and this task fixed:

- The footer's bottom 1px under the bar (792 against 791): the page padding now adds the bar's 1px border.
- The skip link 42px tall: now 44px at every width.
- The footer's Credits (36x13) and "View PHP Source" (86x13) links, and the attribution's two links (82x13, 64x13):
  now 44px tall on a phone (the attribution links taller only; nothing about them is smaller, hidden or moved).
- The weapon shop's item buttons ("Rake" 30x44, "Trowel" 42x44): a form's buttons are now at least 44px wide.
- The commentary's "Refresh" link (53x23): the commentary's paging links (all carry `refresh=1`) are now 44px.
- The mail popup's "Inbox" and "Write" links (35x16): now 44px. Task 11 restyles the mail pages.

Not part of the phone check: at 768px (tablet) the footer's text links and the shop's narrow item buttons are under
44px, as on the desktop.

The home page's skin chooser fits at 390 and 320 (its select 122x44, inside the content card, no side scroll); its
label wraps to five short lines at 320, and at 768 its "Choose" button sits above the select. Left as it is: task 15
removes the chooser.

## Tests: failing first, passing after

New and changed tests:

- `tests/unit/TemplateRenderTest.php` (render, PHP CLI): signed-out home shows the wordmark, no phone header, tabs Go
  and More, and the MoTD, Petition and Credits links in More (Credits now twice in the page: footer and More); the
  village (no race, then a level 7 dwarf with 14 turns, 1,247 gold, 63/70 hitpoints, 5,025 of 6,641 experience) has
  all four tabs, each target once, the phone header with "Level 7 Dwarf · 14 turns · 1,247 gold", HP "63 / 70" (90%)
  and XP "5025 / 6641" (75.7%), and no Credits in More; the same village in jade.htm (the skin cookie) has none of the
  phone's pieces; a forest fight has tabs Character and More only (its one `<nav>` is the tab bar); a dead player in
  the graveyard reads "Level 7 Dwarf · 6 torments · 40 favor" with Soul "62 / 85" (72.9%); the mail popup has no tab
  bar, phone header or More. On every page: source order content, panel, More, footer, tab bar.
- `tests/tooling/test_ui_template.py` (static): the new parts and their keys; `test_the_phone_tab_bar` (in the
  footer only, after `</footer>`, never in a popup; every tab href `#{target}`; the four targets and their conditions;
  the operator and template guards; each target id once; `resurrection_talk_anchor()` called only by
  `viewcommentary()`, after its blocked-commentary check; More's links; `tabs.js` ASCII with no unsafe calls);
  `test_the_phone_header`; `test_the_phone_styles` (hidden above the phone breakpoint; order 1, 2, 3 below 900px; the
  bar fixed with the safe area; 52px tabs; the page padding with the 1px border; the wordmark row hidden with the phone
  header; the tiles; wide tables in their own box); `test_the_old_skins_have_no_phone_pieces`. Changed for the
  intended markup: the nav column's `id="rz-nav"`, `{credits}` twice in the footer part, `phonehead` and `tabbar` frame
  tags.
- `tests/tooling/test_operator_pages.py`: no tab bar and no `tabs.js` on the operator pages.
- `tests/tooling/test_ui_tokens.py`, `tests/unit/NavigationTest.php`: the phone portrait selector and the nav
  column's id, as intended.

On `2a81203` with the new tests copied in (git worktree):

- `test_ui_template`: 7 failures (the new parts missing; `{tabbar}` not filled; `{credits}` once; the nav without its
  id; `test_the_phone_header`, `test_the_phone_styles`, `test_the_phone_tab_bar`).
- `TemplateRenderTest`: fails at its first new assertion ("Failed asserting that 1 is identical to 2", the Credits link
  not yet in More).

On a broken copy of the new tree (one change at a time, each restored):

| Change | Test | Result |
|---|---|---|
| Talk tab on every page | TemplateRenderTest | fails: 3 tabs, not 2, on the fight |
| Dead player shown turns and gold | TemplateRenderTest | fails: subtitle not "6 torments · 40 favor" |
| Talk anchor printed in old skins | TemplateRenderTest | fails: "jade.htm: rz-talk" |
| Phone header signed out | TemplateRenderTest | fails: rz-phonehead on the home page |
| Tab bar on operator pages | test_operator_pages | 2 failures: rz-tabbar on the not-installed and database-error pages |
| Page padding without the bar's border | test_ui_template | fails: the padding rule |
| Navs before content on a phone | test_ui_template | fails: order {main 2, nav 1} |
| A phone piece in an old skin (jade.htm) | test_ui_template | fails: "tabbar" in jade.htm |
| A tab linking to another page | test_ui_template | fails: href "{target}" not "#{target}" |
| Talk anchor removed from viewcommentary() | test_ui_template | fails: no caller |
| `{tabbar}` in the popup footer | test_ui_template | fails: two `{tabbar}` |

On the new tree all of them pass: TemplateRenderTest OK (483 assertions); test_ui_template and test_ui_tokens 30
tests OK; test_operator_pages 3 tests OK.

## Local verification

Local results are supporting evidence; CI is the acceptance. PHP 8.5.12, MySQL 8.4 in Docker, `RESURRECTION_REQUIRE_PHP=1`.

| Check | Result |
|---|---|
| PHPUnit, full (each shard's run) | OK, 306 tests, 143,457 assertions |
| Shard 1 | OK, 69 tests, 15.3 min |
| Shard 2 | OK, 76 tests (75 assigned and 1 prerequisite), 16.8 min |
| Shard 3 | OK, 68 tests, 17.2 min |
| Shard 4 | OK, 69 tests, 18.3 min |
| PHPStan `phpstan.neon` and `phpstan-new.neon` | no errors |
| `composer lint` | 399 files, 0 failures |
| `composer validate --strict`, `composer audit` | valid; no advisories |
| Historical verifier, project validator | PASS, PASS (417 files, 11 commits) |
| `git diff --check`; `LICENSE.txt`, `README.txt` against the tag | clean; byte-identical |
| `scripts/release-upgrade-check.py` | passing (no release yet) |

Shard 2's first run failed, as the shards stop at the first failure:
`test_header_and_footer_links_never_cause_a_bad_nav_http` (test_web_application.py:7057) expected the header's
controls once, `['motd.php', 'petition.php']`, and got them twice, `['motd.php', 'petition.php', 'motd.php',
'petition.php']`: the More section now repeats MoTD and Petition (and the phone header the mail link). Its next check,
the Credits link once, would have failed the same way. Both expectations now name each place: the header's controls,
the phone header's mail link when signed in, More's MoTD and Petition; the Credits link twice (footer and More), the
same nav. Nothing it guards was relaxed: no link may be a bad nav, and the village and the fight still have no Credits
link. The rerun above passed.

## Screenshots

Two sheets, not committed, attached to the task's reply: a phone sheet (390x844) and a tablet sheet (768x1024), each
the village, a forest fight, the graveyard (dead) and the signed-out home page.

## Not changed

- Gameplay, balance and text: no change. The navs, their hotkeys and what each allows are as before.
- The 1.1.2 skins: no new part, tag or markup reaches them (render and static tests above).
- The copyright line and license block, on every page; the attribution line and its links (taller tap targets on a
  phone only).
- Desktop above 900px: unchanged, except the skip link's height (44px) when it is focused.
