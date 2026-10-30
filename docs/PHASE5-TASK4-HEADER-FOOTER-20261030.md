# Phase 5 task 4: header, footer, attribution and a quieter palette (2026-10-30)

Starting head: `976a61eaa9c2f8052205df4733e5c0c88315f3b0` (matched). Task 3's acceptance is recorded in
`PHASE5-TASK3-TEMPLATE-20261030.md` ("Accepted") and ticked in `ROADMAP.md`.

## Jim's decisions (2026-10-30, after reviewing the task 3 screenshots)

1. A quieter text palette: the same codes and meanings, lower saturation, pulled toward parchment. Jim approved 17
   values; the other 24 codes are retuned by the same rule, still distinct, each 4.5:1 or better on `--panel` and
   `--raised`.
2. Character panel values in `--text` (`#ECE4D4`), bold, not gold; the bonus and penalty colors stay distinct. CSS in
   the template, scoped to the character panel; no PHP for it.
3. In-text headings that repeat the page title are removed (the village printed "`` `c`b ``<Village> Square`` `b`c ``"
   under the page title "<Village> Square"). Text changes are allowed; each is recorded with its translation namespace.
4. The footer attribution on every page and popup: "A Phoenix 2:33 LLC Project by Jim Lunsford", with "Phoenix 2:33
   LLC" linking to https://phoenix233.com and "Jim Lunsford" to https://jimlunsford.com; plain links, on its own line,
   separate from the 1.1.2 credit line. The 1.1.2 copyright line, its values and its XOR checks are untouched.
5. The attribution notice: the copyright holder of Resurrection's modifications and additions is Phoenix 2:33 LLC
   (`NOTICE`, `README.md`, and a `CLAUDE.md` rule).
6. The footer Credits link only where about.php is an allowed nav for the page (always signed out); forced
   navigation is not widened.
7. The task order: the images come sooner (see "Task order").

## The palette

The rule, read from Jim's 17 values (task 2 value to approved value): cool hues (blue, cyan, green, magenta) lose about
half their saturation; warm hues (red, orange, yellow) keep most of it; lightness rises slightly; hues drift a few
degrees toward parchment. Applied to the other 24 codes: HLS saturation x 0.5 for cool hues and x 0.8 for warm ones
(hue 345 to 65 degrees), lightness +0.02, hue moved a tenth of the way (at most 4 degrees) toward 40 degrees. Five
results then sat too close to a neighbor (`` `J `` to `` `! ``, `` `M `` to `` `x ``, `` `X `` to `` `& ``, `` `t `` to
`` `^ ``, `` `~ `` to `` `) ``) and were nudged by the smallest change in hue, lightness or saturation (never above task 2's
saturation) that brought every pair to a CIE76 distance of 5 or more. The closest pairs now: `` `4 ``/`` `$ `` 4.66
(both Jim's), `` `& ``/`` `X `` 5.31, `` `) ``/`` `~ `` 5.46 (task 2 had `` `) ``/`` `~ `` at 2.10, `` `M ``/`` `x `` at
4.40). `` `~ `` (black) stays the darkest warm grey, darker than `` `7 `` and `` `) ``.

The final 41 codes (contrast ratios WCAG 2.x, on `--panel` `#1B1814` and `--raised` `#24201A`; source "Jim" for an
approved value, "rule" for a retuned one):

| Code | Class | Task 2 | Now | On panel | On raised | Source |
|---|---|---|---|---|---|---|
| `` `1 `` | `colDkBlue` | `#7E9BD9` | `#8FA3C4` | 6.91 | 6.33 | Jim |
| `` `! `` | `colLtBlue` | `#9CB4EA` | `#A9B8D4` | 8.84 | 8.09 | Jim |
| `` `2 `` | `colDkGreen` | `#6FA661` | `#86A877` | 6.64 | 6.08 | Jim |
| `` `@ `` | `colLtGreen` | `#9CCB8A` | `#9CCB8A` | 9.54 | 8.73 | Jim |
| `` `3 `` | `colDkCyan` | `#5FB0A8` | `#7FAAA3` | 6.89 | 6.31 | Jim |
| `` `# `` | `colLtCyan` | `#8FD3CC` | `#A3C4BE` | 9.43 | 8.64 | Jim |
| `` `4 `` | `colDkRed` | `#D9644A` | `#D9775E` | 5.69 | 5.21 | Jim |
| `` `$ `` | `colLtRed` | `#E8826A` | `#E8826A` | 6.60 | 6.04 | Jim |
| `` `5 `` | `colDkMagenta` | `#B98AD0` | `#B49AB8` | 6.95 | 6.37 | Jim |
| `` `% `` | `colLtMagenta` | `#D9A0C4` | `#CDB0C2` | 8.91 | 8.16 | Jim |
| `` `6 `` | `colDkYellow` | `#C2B05A` | `#C9B47A` | 8.67 | 7.94 | Jim |
| `` `^ `` | `colLtYellow` | `#E9D27C` | `#E3CF9A` | 11.50 | 10.53 | Jim |
| `` `7 `` | `colDkWhite` | `#BDB4A3` | `#C9C0AE` | 9.80 | 8.97 | Jim |
| `` `& `` | `colLtWhite` | `#F4EEE2` | `#F4EEE2` | 15.31 | 14.02 | Jim |
| `` `) `` | `colLtBlack` | `#8F877A` | `#9A917F` | 5.67 | 5.19 | Jim |
| `` `q `` | `colDkOrange` | `#C08650` | `#C4936A` | 6.51 | 5.96 | Jim |
| `` `Q `` | `colLtOrange` | `#E9A25A` | `#DDA878` | 8.38 | 7.67 | Jim |
| `` `~ `` | `colBlack` | `#958C7E` | `#938E85` | 5.43 | 4.97 | rule |
| `` `e `` | `colDkRust` | `#B98680` | `#B7938C` | 6.37 | 5.84 | rule |
| `` `E `` | `colLtRust` | `#D4A39C` | `#D2B0A8` | 8.86 | 8.11 | rule |
| `` `g `` | `colXLtGreen` | `#B5E3A6` | `#C4D8BC` | 11.70 | 10.72 | rule |
| `` `G `` | `colXLtGreen` | `#B5E3A6` | `#C4D8BC` | 11.70 | 10.72 | rule |
| `` `j `` | `colMdGrey` | `#D6D0C4` | `#D9D4CC` | 11.99 | 10.98 | rule |
| `` `J `` | `colMdBlue` | `#8FA6E0` | `#A4ADD0` | 7.97 | 7.30 | rule |
| `` `k `` | `colaquamarine` | `#8CE0C6` | `#A8CFC0` | 10.40 | 9.53 | rule |
| `` `K `` | `coldarkseagreen` | `#9DBF9A` | `#ABBAA9` | 8.70 | 7.97 | rule |
| `` `l `` | `colDkLinkBlue` | `#6FA3D6` | `#8FABC0` | 7.37 | 6.75 | rule |
| `` `L `` | `colLtLinkBlue` | `#7FBCF0` | `#A2C2D7` | 9.46 | 8.66 | rule |
| `` `m `` | `colwheat` | `#EAD8B5` | `#E8DBC2` | 12.92 | 11.83 | rule |
| `` `M `` | `coltan` | `#CDB08C` | `#C6B49D` | 8.77 | 8.03 | rule |
| `` `p `` | `collightsalmon` | `#EBA486` | `#E3B198` | 9.27 | 8.49 | rule |
| `` `P `` | `colsalmon` | `#E58C7C` | `#DD9E8E` | 7.88 | 7.22 | rule |
| `` `R `` | `colRose` | `#E6BDE0` | `#E0CDDC` | 11.74 | 10.75 | rule |
| `` `T `` | `colDkBrown` | `#A88B6C` | `#A69178` | 5.85 | 5.35 | rule |
| `` `t `` | `colLtBrown` | `#E9CF8C` | `#DDCAA2` | 10.99 | 10.06 | rule |
| `` `V `` | `colBlueViolet` | `#A98BE6` | `#B9A8D3` | 8.09 | 7.41 | rule |
| `` `v `` | `coliceviolet` | `#B3C1EA` | `#C7CCE0` | 11.07 | 10.14 | rule |
| `` `x `` | `colburlywood` | `#D9B88F` | `#D5BD9E` | 9.77 | 8.95 | rule |
| `` `X `` | `colbeige` | `#EFE8D0` | `#ECE8D3` | 14.36 | 13.15 | rule |
| `` `y `` | `colkhaki` | `#E6D99A` | `#E1D7A9` | 12.21 | 11.18 | rule |
| `` `Y `` | `coldarkkhaki` | `#BDB474` | `#B9B182` | 8.15 | 7.46 | rule |

`` `@ ``, `` `$ `` and `` `& `` keep their task 2 values (Jim's approved list repeats them). `` `g `` and `` `G `` share
`colXLtGreen`, as in 1.1.2. The spec's table and `assets/ui/tokens.css` hold the same values;
`tests/tooling/test_ui_tokens.py` checks that, the contrast, Jim's 17 values, the rule's direction (each retuned code
less saturated than in task 2) and the distances.

## Character panel values

`getcharstats()` writes every value as `` `^value`0 ``, so `appoencode()` wraps it in `<span class='colLtYellow'>`.
`assets/ui/resurrection.css` now has `.rz-statvalue .colLtYellow, .rz-statbuff .colLtYellow { color: var(--text); }`:
inside the character panel that wrapper reads as `--text`, bold (`.rz-statvalue` is already 700). `appoencode()` closes
one color span before opening the next, so the codes inside a value are sibling spans and keep their colors: the
bonus `` `@ `` (`#9CCB8A`) and penalty `` `$ `` (`#E8826A`) that `charstats()` and `check_temp_stat()` write, the
`` `& `` around a temporary stat, a colored name or title. Each differs from `--text` and from the other by a CIE76
distance over 20 (tested). Nothing else restyles `colLtYellow`.

## Repeated headings

Found by reading every bundled page's and bundled module's `page_header()` / `popup_header()` title and the first
output under it (a scan of every `output()` whose text, without color codes and tags, equals a page title, plus every
page whose title is a variable), then reading each hit. "Same" is equal after color codes, ignoring letter case (two
differ only in case). 22 sites, each removed; the text after each is unchanged.

| Page or module | File | Page title | Removed heading | Translation namespace |
|---|---|---|---|---|
| Village | `village.php` | "%s Square" | "`` `c`b ``%s Square`` `b`c ``" at the start of the village text | `village`: the text key changes (the heading was inside it; one `%s` fewer) |
| Bank | `bank.php` | "Ye Olde Bank" | "Ye Olde Bank" | `bank` |
| Create a character | `create.php` | "Create A Character" | "Create a Character" | `create.php` (default) |
| Gardens | `gardens.php` | "The Gardens" | "The Gardens" | `gardens` |
| Healer | `healer.php` | "Healer's Hut" | "Healer's Hut" | `healer` |
| Clan halls (applicant) | `lib/clan/applicant.php` | "Clan Halls" | "Clan Halls" | `clans` |
| Clan halls (waiting) | `lib/clan/waiting.php` | "Clan Halls" | "Clan Halls" | `clans` |
| Forest | `lib/forest.php` (`forest.php`) | "The Forest" | "The Forest" | `forest` |
| Graveyard | `lib/graveyard/case_default.php` (`graveyard.php`) | "The Graveyard" | "The Graveyard" | `graveyard` |
| New day | `lib/newday_daily_outcomes.php` (`newday.php`) | "It is a new day!" | "It is a New Day!" (and its `<font size='+1'>` wrapper) | `newday.php` (default) |
| Veteran's Club | `rock.php` | "The Veteran's Club" | "The Veteran's Club" | `rock` |
| Weapons | `weapons.php` | "MightyE's Weapons" (`$texts['title']`) | the same text | `weapon` (title schema) |
| Armor | `armor.php` | "Pegasus Armor" (`$texts['title']`) | the same text | `armor` (title schema) |
| Mercenary camp | `mercenarycamp.php` | "A Mercenary Camp" (`$texts['title']`) | the same text | `mercenarycamp` (title schema) |
| Inn | `inn.php` | the inn's name | the inn's name | `inn` |
| Cedrik's potions | `modules/cedrikspotions.php` | the inn's name | the inn's name | `inn` |
| Lovers | `modules/lovers.php` | the inn's name | the inn's name | `module-lovers` |
| Seth's song | `modules/sethsong.php` | the inn's name | the inn's name | `inn` |
| Drinks | `modules/drinks/run.php` | the drink page's title | the same title | `module-drinks` (title schema) |
| Dag Durnick | `modules/dag/run.php` | "Dag Durnick's Table" | "Dag Durnick's Table" | `module-dag` |
| Romar (human city) | `modules/racehuman.php` (villagetext) | "%s, City of Men" | "`` `c`b ``%s, City of Men`` `b`c ``" at the start of the city text | `module-racehuman`: the text key changes (one `%s` fewer). Shown only where a cities module makes Romar reachable |
| Dark Horse Tavern | `modules/darkhorse.php` | the tavern's name (`darkhorse_run()`) | the tavern's name, only on that page: `darkhorse_runevent()` takes `$showname` (default true); in the forest event the title is "The Forest", so the name stays there | none (`output_notl`) |

Except the village and Romar, each removed line was a second use of a key the page title still uses in the same
namespace, so no translation key is orphaned. Kept, because the heading is not the page title: Lodge ("The Hunter's
Lodge" under "Hunter's Lodge"), Title Editor ("-=Title Editor=-"), the Mausoleum headings under "The Graveyard", the
news date line under "LoGD News", the Hall of Fame and warrior list headings, the clan application form, "Something
Special!", "~ ~ ~ Fight ~ ~ ~", the resurrection's "It is a New Day!" under "Resurrected!", and the elf, dwarf and
troll cities' headings (each differs from its title). The about page is untouched (`test_license_text`). Lodge and Title
Editor are near-misses Jim may want removed too.

## Header controls, links and popups

**How.** `maillink()`, `motdlink()` and a new `petitionlink()` (`lib/pageparts.php`) write each control through
`resurrection_header_control()`: `<a href='mail.php' target='_blank' rel='noopener' class='rz-control rz-control-mail
motd|hotmotd' aria-label='…' title='…' onclick="…">`, an inline SVG line icon (`aria-hidden`, 24px grid, 1.8 stroke,
`currentColor`), the label in `<span class='rz-control-label'>`, and the badge. The mail count comes from
`maillink_counts()` (the same query as 1.1.2, returned as `['new' => …, 'old' => …]`), never parsed from the sentence;
the sentence "Ye Olde Mail: %s new, %s old" (`common`, unchanged key) is the link's `aria-label` and `title`. The badge
shows the unread count and is left out at zero. MoTD: an unread item gives a dot badge, the `hotmotd` class and the
label "MoTD (unread)". The `motd` / `hotmotd` classes stay, so the 1.1.2 skins (still selectable until task 15) keep
their link styles.

**Why a new tab.** The three pages are popup pages: `mail.php`, `motd.php` and `petition.php` define
`OVERRIDE_FORCED_NAV`, keep no restore page (`$nokeeprestore`), and print no navs. Opened in the same tab, the player
would leave the game page with no way back but the browser's Back button, and on a reload of that page the game would
see a nav it never allowed. A new tab leaves the game page, its allowed navs and its restore page where they were, on
any device, with or without JavaScript.

**The popup as an enhancement.** The `onclick` (`resurrection_popup_enhancement()`) runs only when
`matchMedia('(min-width: 900px) and (hover: hover) and (pointer: fine)')` matches, that is a desktop with a mouse: it
opens 1.1.2's 550x300 window under 1.1.2's window name (`mailphp`, `motdphp`, `petitionphp`) and cancels the link. If
the window is blocked, `window.open` returns null and the link opens as a plain link. On a phone or a tablet the
handler does nothing and the link opens normally.

**MoTD auto-open.** `page_footer()` no longer writes `window.open('motd.php', …)` into `{headscript}`;
`$session['needtoviewmotd']` is now set on every page (1.1.2's exception list never applied: it read `$nopopup`) and
only marks the link.

**Popup pages keep the game page's navs.** The HTTP test found that opening the MoTD added three navs to the game
page's allowed navs (the MoTD commentary's refresh links, `motd.php?comment=1` and `motd.php?refresh=1&c=…`):
`popup_footer()` saved whatever navs the popup added. All four popup pages (`mail.php`, `motd.php`, `petition.php`,
`translatortool.php`) run outside forced navigation and never need navs of their own, so `popup_footer()` now restores
the navs the request started with (`$baseaccount['allowednavs']`) before `saveuser()`. Recorded in the ledger.

**Phone.** Below 600px the labels are visually hidden (they remain the accessible names), each control is a 44px
square, the three sit on one row (`flex-wrap: nowrap`), and each badge sits on its control's corner.

## Footer

- `{credits}` (`creditslink()`): the Credits link only where following it is allowed. Signed out, `about.php`
  (it allows anonymous visits, and no forced navigation applies). Signed in, only if the page allowed `about.php` as a
  nav: either the bare `about.php` or `about.php?c=<counter>-<HHMMSS>` (`private_addnav()`'s form), and the link uses
  that exact key. Home and news allow it; the village and a fight do not, so there is no link there and nothing new
  to follow. The separator before Source comes with it. The template no longer contains any `about.php` link.
- `{newday}` (`resurrection_newday_text()`): "Next new day in %sh %sm" (`common`), the seconds from
  `secondstonextgameday()` minus the start of today, as text. Note, not changed: `secondstonextgameday()` passes the
  real seconds to `strtotime()`, which returns false for a fractional value; with `daysperday` limited to 1 to 6 by
  the settings editor every value divides 86400, so the seconds are whole.
- The attribution line (below).

## Attribution

**Wording pending legal review before release.**

- Template: `<p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33
  LLC</a> Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>`, in `footer`
  and `popupfoot`, after the element holding `{copyright}` and outside it; `--muted`, `--size-small`, the same as the
  credit line. Plain links: nothing is requested until one is clicked (the no-external-request test still passes).
- `NOTICE`, new "Resurrection" section: Resurrection is an adaptation of LoGD 1.1.2; its modifications and additions
  are Copyright (c) 2026 Phoenix 2:33 LLC, licensed under CC BY-NC-SA 2.0 as an adaptation; the original authors'
  notices, credits, `LICENSE.txt` and `README.txt` stay as they are. Credit: section 4 of the license requires anyone
  who distributes or publicly performs the work or an adaptation to keep its copyright notices intact and to credit its
  authors, in an adaptation where any other comparable authorship credit appears and at least as prominently; for
  Resurrection that credit is "A Phoenix 2:33 LLC Project by Jim Lunsford" with both links, in the footer of every page,
  and it is written as the credit the license already requires, not an added term.
- `README.md`: "Credit and attribution", the same in short, pointing to `NOTICE`.
- `CLAUDE.md` (Licensing): no task removes, hides, shrinks or weakens the line or its links; it stays in `footer` and
  `popupfoot`.
- Not changed: `LICENSE.txt`, `README.txt` (byte-identical to the tag), `$copyright`, `$license`, the `$y2`/`$z2` XOR
  tag and the checks in `common.php` and `prepare_template()`.
- Open: the 18 old skins (selectable in Preferences until task 15) do not carry the line; a player who picks one does
  not see it until task 15 removes them.

## Task order

Jim's decision, 2026-10-30: the images come sooner. `ROADMAP.md`'s checklist is now: 4 header and footer (this
task); 5 image pipeline plus the first art (village and forest scenes, race portraits; the command chat supplies the
SVGs); 6 navigation; 7 character panel; 8 phone layout; 9 forms, buttons and tables; 10 commentary and battle log;
11 popups, admin and superuser screens; 12 the remaining location scenes; 13 the remaining icons; 14 creature parts kit
and portraits; 15 remove the 1.1.2 skins. The plan's section 7 table keeps its original numbers (a historical record);
`ROADMAP.md` is the order. Comments in `assets/ui/resurrection.css` that named a task number now name the new one.

## Tests

Each new test fails on `976a61e` (the new test files copied onto it) or on a targeted revert of the final tree, and
passes on the final tree.

| Test | On `976a61e` or the revert | After |
|---|---|---|
| `test_ui_tokens.test_the_quieter_palette` (Jim's 17 values, the rule, distances) | FAIL: `'#8FA3C4' != '#7E9BD9'` | pass |
| `test_ui_tokens.test_character_panel_values_are_text_not_gold` | FAIL: "The character panel rule is missing" | pass |
| `test_ui_tokens.test_text_tokens_and_color_codes_are_readable` (existing, unchanged: all 41 codes 4.5:1 on both) | pass | pass |
| `test_ui_template.test_the_attribution_line` (footer and popupfoot each once, both exact links, after the credit line, not hidden) | FAIL: `1 != 0 : footer` | pass |
| `test_ui_template.test_frame_tags_are_ones_the_code_fills` (now `{credits}`, `{newday}`) | FAIL: `'{credits}' not found` in `lib/pageparts.php` | pass |
| `test_ui_template.test_required_tags_and_the_copyright_line` (now `{copyright}{credits}{source}`, no `about.php` in the template) | FAIL: `1 != 0 : newday` | pass |
| `TemplateRenderTest` (PHP CLI: attribution once on home, village and the mail popup, copyright once; mail badge with no mail and with 2 unread and 1 read, against the table; MoTD unread mark, no auto-open; `{newday}` against `secondstonextgameday()` on a pinned clock; Credits on home, absent in the village; the village title once) | FAIL at the attribution count (`0 is identical to 1`) | pass (135 assertions) |
| the same, village heading restored | FAIL: "The village title is repeated in the page", 2 not 1 | pass |
| the same, mail badge never written | FAIL: expected `<span class='rz-badge' aria-hidden='true'>2</span>`, got `''` | pass |
| the same, countdown an hour off | FAIL: `'Next new day in 6h 18m'` not among the expected values | pass |
| `test_header_and_footer_links_never_cause_a_bad_nav_http` (signed out: home and news have Credits to `about.php`; MoTD and Petition real links; signed in: news's Credits is the exact allowed nav and following it is allowed; village and a forest fight have no Credits link and no `about.php` link; following Mail, MoTD and Petition from the fight is a 200, no bad nav, and leaves allowed navs, restore page and the fight unchanged; the fight still loads; copyright and attribution once on every page) | FAIL on `976a61e`: attribution count `1 != 0` | pass |
| the same, `popup_footer()` nav restore removed | FAIL (motd.php, petition.php): allowed navs grew from 2 to 5 | pass |
| the same, Credits always shown | FAIL: `'about.php'` is not the page's allowed nav | pass |

Changed, not weakened: `test_ui_template`'s fixed `<a href="about.php">Credits</a>` assertion became the `{credits}`
tag check and "no `about.php` link in the template" (the link moved into PHP so it can be left out); the old literal
would be a bad nav on most pages. `TemplateRenderTest`'s `'Ye Olde Mail'` check became the exact mail link check.

## Local verification

All four shards, run in parallel through `scripts/tooling-shards.py` with `RESURRECTION_REQUIRE_PHP=1` (PHP 8.5.12,
one MySQL 8.4 container each, PHPUnit first on each empty database):

| Shard | Ran | Failures | Errors | Skipped | Python duration | Wall (with PHPUnit) |
|---|---|---|---|---|---|---|
| 1/4 | 67 (67 assigned) | 0 | 0 | 0 | 14.9 min | 914 s |
| 2/4 | 64 (63 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.1 min | 983 s |
| 3/4 | 71 (70 assigned + 1 prerequisite) | 0 | 0 | 0 | 13.7 min | 845 s |
| 4/4 | 66 (65 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.2 min | 995 s |

- `vendor/bin/phpunit` on an empty MySQL 8.4 database (PHP 8.5.12): OK, 284 tests.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 393 files, 0 failures. `composer validate --strict`: valid. `composer audit`: no advisories.
- Historical verifier: PASS (tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`, 417 files, 11 commits).
  Project validator: PASS (698 tracked files). Release upgrade check: passing (no release yet). `git diff --check`:
  clean. `LICENSE.txt` and `README.txt` byte-identical to `historical-source-1.1.2`. Static tooling tests
  (`test_ui_tokens`, `test_ui_template`, `test_tooling_shards`, `test_route_inventory`, `test_disabled_endpoints`,
  `test_license_text`): 40 tests OK.

Local results are supporting evidence; CI is the acceptance.

## Screenshots

1440x1000 and 390x844 of the signed-out home page, the village, a forest fight and the mail popup, taken with
Playwright from a throwaway local install (not committed; sent to Jim in the task report).

## Not changed

- The 1.1.2 copyright line, `$license`, the XOR checks, `LICENSE.txt`, `README.txt`, the about page.
- The version string, the CI workflows, the shard timeout, `tests/tooling/shard-durations.json`.
- The old skins and skin selection (task 15); no `assets/LICENSE` (task 5); no file labelled AGPL; no tag, release,
  deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 4 only
after CI accepts it.
