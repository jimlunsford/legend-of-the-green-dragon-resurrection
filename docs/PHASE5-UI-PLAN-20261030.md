# Phase 5 UI plan (2026-10-30)

Phase 5, Resurrection UI: one new dark fantasy UI with original art replaces all 1.1.2 skins. This document audits how
pages render today, at `8b33edf`, and plans the build. The design every task builds to is `UI-DESIGN-SPEC.md`; Jim's
decisions are in `PHASE5-TASK1-DIRECTION-20261030.md`.

Read from the code at `8b33edf`. Counts are from `grep` over tracked PHP outside `vendor/` and `tests/`, and are
approximate where a pattern can also match text.

## 1. How a page renders today

### The pieces

A page is three strings joined at the end of `page_footer()`: the template's `header` part, `$output` (everything the
page printed), and the template's `footer` part. A popup is `popuphead`, `$output`, `popupfoot`.

```
common.php ──► prepare_template() ──► loadtemplate(templates/<skin>.htm)
page script ─► page_header(title) ──► $header = template['header'] with {title}
             ► output(), rawoutput(), addnav() …     (into $output and the nav lists)
             ► page_footer() ──► hooks, buildnavs(), charstats(), MoTD/mail/petition links,
                                 key script, {copyright}, {source}, {version}, {pagegen}
                                 ──► echo $header . $output . $footer; exit
```

### `lib/template.php`

- `prepare_template()` picks the skin: the `template` cookie if it names an existing `templates/*.htm` (letters,
  digits, `_`, `-`), else the `defaultskin` setting (default `jade.htm`), else `jade.htm`. It loads it with
  `loadtemplate()`.
- **Required-tag checks.** When the skin's name or modification time differs from the one cached in the session
  (`$session['templatename']`, `templatemtime`), it checks the tags: `{title}`, `{headscript}` and `{script}` must be
  in the header; `{nav}`, `{stats}`, `{petition}`, `{motd}`, `{mail}`, `{paypal}`, `{source}`, `{version}` and one more
  must be in the header or the footer. A skin that fails prints "You have one or more errors in your template page!"
  and falls back to `jade.htm`.
- **Copyright enforcement.** The "one more" tag is pushed at run time as `$y2 ^ $z2`, two byte strings in `common.php`
  whose XOR is `copyright`. So every skin must carry `{copyright}`, and the check never names it in plain text. The
  value that fills it is the variable `$copyright` (also reached as `$$z`, `$z = $y2 ^ $z2`): `$lc . $copyright .
  "<br />"`, where `$lc` is the CC BY-NC-SA 2.0 license block and `$copyright` the authors' line. For non-superusers
  `common.php` also XOR-checks `$copyright` and `$license` against stored constants; a tampered value is replaced by a
  notice (`$x`) and the license by the original. The new template must carry `{copyright}` in its footer and in
  `popupfoot`; none of this code needs to change, and none may be weakened.
- `templatereplace($part, $vals)` fills `{key}` placeholders in a named part, and prints a warning into the page when
  a part or a key is missing (so a new template must define every part the code asks for).

### `lib/pageparts.php`

- `page_header(...)`: runs `everyheader`, `everyheader-loggedin` and `header-<script>` module hooks, translates and
  sanitizes the title, and starts `$header` from the template with `{title}` filled (escaped).
- `page_footer()`: runs `footer-<script>`, `footer-<module>` (on `runmodule.php`), `everyfooter` and
  `everyfooter-loggedin`. Each hook returns `[tag => [html, …]]`, which is inserted after `{tag}` in the header and
  footer (this is how 1.1.2's ad slots `{headerad}`, `{navad}`, `{verticalad}`, `{bodyad}` were filled). It then:
  - builds the navs (`buildnavs()`), the character panel (`charstats()`), and the MoTD check: when an unread MoTD exists
    and the page is not in `$nopopups`, `{headscript}` gets an inline `window.open('motd.php')` script;
  - writes `{headscript}` (the favicon link plus that script) and `{script}` (an inline `document.onkeypress` handler
    built from `$quickkeys`, one `if (c == 'X') { window.location='…' }` per hotkey);
  - empties `{paypal}`; fills `{nav}`, `{motd}` (`motdlink()`), `{mail}` (`maillink()`, signed in only),
    `{petition}` (a fixed link opening `petition.php` in a popup), `{petitiondisplay}` (staff petition counts, through
    the `petitioncount` part), `{stats}`, `{source}` (`resurrection_source_link()`), `{version}`
    (`Resurrection\Version::display()`), `{pagegen}` (page timing), and `{copyright}`;
  - removes every unfilled `{tag}`, saves the user, prints the page.
- `popup_header()` / `popup_footer()`: the same for popups, with `header-popup` and `footer-popup` hooks and the
  `{copyright}` line. Used by `mail.php`, `motd.php`, `translatortool.php` and the six `lib/petition/` pages.
- `popup($page, $size)`: the `window.open(...)` JavaScript string; 14 uses in 6 files.
- `charstats()`: for a signed-in player, fills sections with `addcharstat()`: "Vital Info" (name, level, hitpoints or
  soulpoints, turns or torments, attack, defense, spirits, race), "Companions", "Personal Info" (gold or favor, gems,
  experience), "Equipment Info" (weapon, armor, mount); then the `charstats` hook (modules add rows the same way), then
  `getcharstats()` renders them through the `statstart`, `stathead`, `statrow`, `statbuff` and `statend` parts, buffs
  last. Values carry color codes (`` `^ ``, `` `$ `` for a penalty, `` `@ `` for a bonus) and are run through
  `appoencode()`. Signed out, it is the online list (`onlinecharlist` hook or a query), cached as `charlisthomepage`.
- `maillink()` returns "Ye Olde Mail: N new, M old" with class `hotmotd` when there is unread mail; `motdlink()`
  returns "MoTD", `hotmotd` when unread. Both open popups.

### `lib/output.php`

- `output()`, `output_notl()` translate and pass text through `appoencode()`; `rawoutput()` appends HTML unchanged
  (1,207 calls in 111 files: most page markup the game prints is in PHP strings).
- `appoencode($data, $priv)`: the color and format codes. Each of the 41 color characters becomes
  `<span class='colXxx'>` (closing the previous color span); `` `0 `` closes it; `` `b `` / `` `i `` / `` `h `` toggle
  `<b>` / `<i>` / `<em>`; `` `c `` toggles `<div align='center'>`; `` `> `` / `` `< `` toggle floated divs with inline
  styles; `` `H `` toggles `<span class='navhi'>`; `` `n `` is `<br>`; `` `w `` inserts the weapon name. Open tags are
  closed at the end of the page (`$nestedtags`). The class names are part of the template contract: every skin's CSS
  styles them.
- Navigation: `addnav()`, `addnav_notl()`, `addnavheader()` collect links by section into `$navbysection`; every
  displayed internal link must also be added (`allowednavs`), or the request is a bad nav. `addnav("", $url)` (174
  calls) allows a link without showing it.
- `buildnavs()` renders each section that has visible items: the heading through `private_addnav()` and the `navhead`
  part, the `collapse-nav{` and `}collapse-nav` hooks around it (a module can wrap a section), then the items.
- `private_addnav()`: translates the label, then chooses the **hotkey**: a label written `"X?Label"` asks for `X`;
  otherwise the first letter or digit not already used on the page. The key is marked in the label with `` `H ``
  (so `appoencode()` wraps it in `<span class='navhi'>`); if the key is not in the label, `(X) ` is prepended. The
  item is rendered through the `navitem` part with `{link}` (the URL plus the `c=` counter), `{accesskey}`
  (` accesskey="X" `), `{popup}` and `{text}`; `$quickkeys[X]` gets the JavaScript the key script runs. The
  translator's button (`tlbutton_pop()`) is inserted before the `<a>` for translators.

### Where modules put HTML

- Through page output: `output()` and `rawoutput()` in their own pages and in hooked content (13 of the 39 PHP files of
  the 24 bundled modules call `rawoutput()`).
- Through hooks into the frame: `everyheader`, `header-<script>`, `everyfooter`, `footer-<script>`, `footer-popup`
  (returned HTML inserted after a template tag), `collapse-nav{` / `}collapse-nav` (wraps nav sections),
  `charstats` (`addcharstat()` rows), `onlinecharlist` (replaces the online list), and `template-<part>` (called by
  `loadtemplate()` for each part; its return value is ignored). Of the bundled modules, only `header-inn` and
  `header-graveyard` hook the frame; none adds frame HTML or character stats. Third-party modules may use any of them,
  so each hook keeps working in the new UI.

## 2. Skin selection, and what removing the skins touches

Today: 18 skins in `templates/` (`.htm` with its `.css` and an image folder; 124 tracked files).

| Where | What |
|---|---|
| `template` cookie | Set by `home.php` (signed-out skin form) and `prefs.php` (Skin preference), 45 days, `HttpOnly`, `SameSite=Lax`; validated by name and file. Read by `prepare_template()` |
| `prefs.php` | The "Skin" row (`theme` field type), its POST handling and the `$prefs['template']` default |
| `home.php` | "Choose a different display skin" form and its POST |
| `defaultskin` setting | Declared in `lib/configuration_descriptors.php` (`theme`), default `jade.htm` in `lib/installer/installer_default_settings.php` and in `prepare_template()` / `loadtemplate()`; stored in each install's `settings` table |
| `theme` field type | `lib/showform.php` (a select of `templates/*.htm`) and `src/Http/SettingDescriptor.php` (accepts any `templates/*.htm` name) |
| Session | `$session['templatename']`, `templatemtime` (the required-tag cache), defaulted in `common.php` |
| `loadtemplate()` | Falls back through `defaultskin` and `jade.htm` |
| Translator tools | `translatortool.php` renders through `popup_header()` / `popup_footer()` and the template; `tlbutton_pop()` buttons sit before nav links. No skin-specific code, but both must still render |
| Tests | `OutputBufferingTest` asserts jade's page head (`"\r\n<!DOCTYPE"`); `test_route_inventory.py` scans `templates/*.htm` for links; `test_disabled_endpoints.py` checks no skin links a removed endpoint; HTTP tests assert `<title>…` and count the copyright line, which any template satisfies |
| Docs | `LICENSING-AND-PROVENANCE.md` ("Images, skins and templates" rights item), `UNNECESSARY-FILES-20261024.md` (skin images), `MODERNIZATION-PRINCIPLES.md` ("retain original templates"), `THIRD-PARTY-LICENSES.md` |

Removing them (task 15) means: delete the 18 skins and their folders (kept byte for byte at
`historical-source-1.1.2`); drop the cookie, both forms, the `defaultskin` declaration and default, the `theme` type's
skin list, and the session cache keys; make `prepare_template()` load the one template; and leave stored
`defaultskin` rows and old `template` cookies harmless (ignored). Every removal is recorded as a change from 1.1.2. The
upgrade system (`upgrades/`) gets a step only if a stored value would otherwise break an existing install; the
`defaultskin` row does not need one once nothing reads it.

## 3. What must change in PHP, and what the template and CSS can do alone

### Template and CSS alone

- The page frame: header bar, three columns, footer, phone stacking, the column widths, colors and type. The frame
  is the template's `header` and `footer` parts, so a new `templates/resurrection.htm` with its CSS does it.
- The color codes: only CSS values (the class names stay).
- Nav heading and item styling, 44px rows: the `navhead`, `navhelp` and `navitem` parts.
- The stat table's look: the `stat*` parts (but not bars or tiles; see below).
- The footer's credit line, Credits and Source links and version: the tags exist (`{copyright}`, `{source}`,
  `{version}`); "Credits" is a plain link to `about.php` in the template.
- Popups' look: `popuphead` / `popupfoot`.
- The login box on the home page: the `login` and `loginfull` parts (`home.php` fills them).
- Generic styling of the markup PHP prints: `trhead` / `trlight` / `trdark` / `trhilight` rows (133 uses in 34
  files), `.button` (124), `<input>` (273), headings, links.

### Needs PHP

| Change | Where | Why the template cannot |
|---|---|---|
| Mail unread count as a badge | `maillink()` | The count is inside a translated sentence; a badge needs the number separately |
| Time to the next new day | `page_footer()` (new `{newday}` tag), `secondstonextgameday()` | No tag carries it |
| Key badges | `private_addnav()` (the `` `H `` marking), `appoencode()` (`` `H ``) or the `navitem` part with a new `{key}` | The key is marked inside the label text; a badge needs it as its own element |
| Key script | `page_footer()` `{script}` | An inline `document.onkeypress` script; moving it to a static file (and keeping every key) needs PHP to emit the key map as data |
| Popups on a phone | `popup()`, `maillink()`, `motdlink()`, `{petition}` | `window.open` popups are poor on phones; links should be real `href`s that open the page normally, with the popup only as an enhancement |
| HP and XP bars, stat tiles, equipment rows | `charstats()` / `getcharstats()`, new template parts (`statbar`, `stattile`, `equiprow`) | The panel is rendered as label/value rows; bars need numbers (current, max, next-level XP from `exp_for_next_level()`), not formatted strings. `addcharstat()` stays for modules |
| Portrait in the character panel | `charstats()` | Needs the race (and later the image lookup) |
| Phone tab bar and compact header | `page_footer()` | The Village tab must link only to a nav the page allowed (see Risks); the compact header needs the HP/XP numbers |
| Scene banners | `page_header()` or a new `{scene}` tag | The scene depends on the script and location |
| Commentary list and 44px input | `lib/commentary.php` (`viewcommentary()`, `talkform()`) | Comments are printed as text lines with `<br>`; the form is PHP |
| Battle log as an ordered list | the combat output in `battle.php` and `lib/*_combat.php` | Each line is an `output()` call; a list needs open/close markers around the round |
| One primary action per screen | the page scripts that print forms | Which button is primary is page knowledge |
| Labelled inputs | `lib/showform.php`, `lib/forms.php`, and forms printed with `rawoutput()` | Many inputs have a text label beside them but no `<label for>` |
| Inline presentation | 62 `<font>`, `bgcolor=` and `style=` uses; `` `c `` (`align='center'`) and `` `> `` / `` `< `` (inline floats) in `appoencode()` | Inline styles override the CSS |

### Admin and superuser screens

The Grotto (`superuser.php`) and the editors (`user.php`, `creatures.php`, `mounts.php`, `companions.php`,
`weaponeditor.php`, `armoreditor.php`, `titleedit.php`, `configuration.php`, `modules.php`, `viewpetition.php`,
`moderate.php`, `bios.php`, `donators.php`, `referers.php`, `gamelog.php`, `untranslated.php`, `translatortool.php`)
print wide tables and long forms through `rawoutput()` and `showform()`. They need: tables that scroll inside their
own box instead of the page; `showform()`'s tabbed sections (its own inline script) styled and usable at 44px; the
petition counts (`{petitiondisplay}`) as compact badges; and no change to what they do. They are styled after the
player screens (task 10), and only the shared pieces (tables, forms, buttons) are touched in PHP.

### Popups

| Popup | Page | Needs |
|---|---|---|
| Mail | `mail.php` (popup) | Inbox list, message view, write form with labelled inputs; the unread badge in the header; on a phone it opens as a page |
| Petition | `petition.php` and `lib/petition/` (popup) | A labelled form; the FAQ pages as plain text pages |
| MoTD | `motd.php` (popup; also opened automatically when unread) | Item list in Alegreya; the poll form; the auto-open replaced by the header's "unread" mark on a phone |
| Translator | `translatortool.php` (popup) | Staff only; styled, not redesigned |

All popups keep the `{copyright}` line in `popupfoot`.

## 4. Tests that depend on the current markup

- `tests/unit/OutputBufferingTest.php`: asserts the page starts with jade's page head (`"\r\n<!DOCTYPE"`) and has
  `<title>Legend of the Green Dragon`. The first assertion changes with the default template (task 3): the test
  then asserts the new template's head, which still proves nothing is printed before the page.
- HTTP tests (`tests/tooling/test_web_application.py`): they mostly read forms (`<form … action=…>`), links
  (`href=…`) and plain text after stripping tags, so they are tolerant of the frame. Markup-specific assertions to
  re-check in each task: `<title>…` (template), the copyright line counted once per page (template), the "View PHP
  Source" link markup (`lib/source_link.php`, line 8319), `<span class='colLtRed'>` / `colLtGreen` spans
  (`appoencode()` class names, unchanged), nav labels matched after stripping the hotkey `<span>` (a `<kbd>` badge
  must strip the same way), and form field `name="…"` / `value="…"` patterns.
- `tests/tooling/test_operator_pages.py`: `<title>` on the operator pages, and that the copyright values are set
  before `prepare_template()`.
- `tests/tooling/test_license_text.py`: `LICENSE.txt`, `README.txt` byte-identical, and the about page's original
  text and copyright lines; unaffected by a new template, and must stay green.
- `tests/tooling/test_route_inventory.py` and `test_disabled_endpoints.py`: scan `templates/*.htm` for links; a new
  template must link only existing pages.
- No test asserts a skin's CSS, the jade table layout, the nav `<br clear='all'>` or the stat table.

Each UI task runs the HTTP shards that cover the pages it changes; a change to `appoencode()`, `private_addnav()`,
`page_footer()` or `charstats()` is a shared helper, so it runs all four.

## 5. The image pipeline

### Layout

```
assets/
  LICENSE                CC BY-SA 4.0, Jim / Phoenix 2:33 LLC (created in the art task)
  README.md              what is here, the license, how to add art
  scenes/<place>.svg     village, forest, inn, graveyard, bank, … (one per place)
  portraits/race/<race>.svg            human, elf, dwarf, troll, unknown
  portraits/creature/parts/<slot>/<part>.svg   body, head, eyes, extra, … (the parts kit)
  portraits/creature/kit.json          creature → parts and tint
  portraits/creature/fallback/<type>.svg       one silhouette per creature type
  icons/<name>.svg       24px line icons: hp, xp, attack, defense, gold, gems, turns, weapon, armor, mount, …
  fonts/<family>/        woff2 and OFL.txt (task 2; OFL, not CC BY-SA: the LICENSE file names its scope)
  ui/                    tokens.css, the template's CSS and its small scripts (task 2 and 3)
```

Served as static files. SVGs are inlined or referenced by URL; either way they contain no script and no external
reference (a static test checks every `assets/**/*.svg`).

### Lookup

One small PHP library (proposed `src/Ui/Art.php`, required where used, as `src/` has no autoloader):

- **Scenes by place.** Key: the page script, refined by location where it varies (`village.php` uses the player's
  `location`, so a module city could add its own scene). A static map `script → scene`; unknown → the fallback banner.
  A module can supply its own scene through a new hook (`scene`), so third-party places are not stuck on the fallback.
- **Portraits by race.** Key: `$session['user']['race']` lowercased (`Human`, `Elf`, `Dwarf`, `Troll` from the
  bundled race modules); a race a third-party module adds, or `RACE_UNKNOWN`, gets the `unknown` portrait. Tinted by
  race in CSS (a custom property on the arched frame).
- **Creature portraits.** Key: the creature's name as the encounter sees it (`creaturename` in the fight's enemy list) and its
  `creatureid` when it comes from the `creatures` table. `kit.json` maps each seeded creature to a recipe: a creature
  type (beast, humanoid, undead, spirit, construct, dragon, …), and one part per slot plus a tint. The portrait is the
  parts layered in one SVG. Creatures added later in the editor, by modules, or the Green Dragon and masters get their
  type's fallback silhouette (the dragon and masters can get hand-drawn portraits).
- **Icons by meaning.** A fixed set named in the spec (stats, equipment, mail, petition, MoTD), referenced by name.
- **Fallbacks everywhere**: every lookup returns a valid image or none, never a broken URL; every image has alt text
  or `aria-label` from the same data (place name, race, creature name).

### 288 creatures from a parts kit

A fresh install seeds 288 creatures (`creatures`, levels 1 to 18; the creature table has no type column). Drawing 288
portraits by hand is not practical; a parts kit is:

1. Classify every seeded creature into a type, by hand, once, in `kit.json` (reviewable, diffable; the name and
   weapon say most: "Baby Unicorn" is a beast, "Rude Student" a humanoid).
2. Draw per type a base body, and per slot a small set of interchangeable parts (heads, eyes, horns, weapons,
   accessories), all on one grid and in palette colors.
3. Assign each creature a recipe in `kit.json`; a test checks every seeded creature has one, every part exists, and
   no two creatures at the same level share an identical recipe.
4. Render by layering the parts (server-side SVG composition, cached; or one precomposed SVG per creature generated
   by a script and committed, which keeps runtime simple). The plan picks precomposed files in task 14 if the
   repository size stays reasonable; the kit and the generator script stay the source.

## 6. Risks

- **The license block and copyright check.** The footer must keep `{copyright}` and its content unaltered; restyling
  it with CSS is fine, hiding it is not (it must stay visible on every page). The XOR checks in `common.php` and
  `prepare_template()` stay as they are.
- **Bad navs.** Every new link the UI adds (tab bar, header, footer, Credits) must be allowed for the page or be a
  page that is always allowed. The phone tab bar's "Village" must point at an allowed nav on that page (the page's own
  return-to-village nav), and is disabled when there is none; it must never let a player leave a fight or a forced
  page. Character, Talk and More are in-page anchors.
- **Hotkeys.** Moving the key script out of an inline block must keep every key, the popup keys, and the "not in an
  input" rule; translators' and modules' labels keep their `X?` prefixes.
- **Modules.** Third-party modules print their own HTML and hook the frame; the new template keeps every hook and tag
  they may use, and old markup they print (tables, `<font>`) must still be readable.
- **Translations.** Labels are translated by exact text (`translate_inline()`); changing wording or splitting a
  sentence (mail's "N new, M old") creates new translation keys, and existing translations stop matching. Each such
  change is recorded.
- **Test runtime.** New HTTP tests add to a shard's runtime; prefer static and PHP-CLI render tests.
- **Fonts and art size.** woff2 subsets and SVGs must stay small; the CI and repository should not grow by megabytes.
- **Third-party requests.** No CDN, font or image request leaves the game; a static test guards it.
- **AGPL labels.** No file is labelled AGPL until Jim decides the open licensing question.

## 7. Proposed tasks

Each task is one commit, with its tests and its acceptance checks. Every task records each change from 1.1.2 (old,
new, why) in its evidence doc, keeps the `{copyright}` line on every page, and is accepted only on CI (both legs, all
12 Modern core jobs, and Baseline integrity).

| # | Task | Scope | Tests | Acceptance checks |
|---|---|---|---|---|
| 1 | Direction, governance and UI plan | `CLAUDE.md`, `ROADMAP.md`, the spec, this plan, `OutputBufferingTest` independent of order | `OutputBufferingTest` alone on an empty database, and failing with the fix reverted | CI green; docs match Jim's decisions |
| 2 | Design tokens, fonts and color codes | `assets/ui/tokens.css` (palette, type, spacing, all 41 color codes), self-hosted woff2 for the three families with their OFL texts, `docs/THIRD-PARTY-LICENSES.md`; a `docs/CHANGES-FROM-1.1.2.md` ledger started | Static: tokens equal the spec's values; every color code and text token clears 4.5:1 on panel and raised; every color class `appoencode()` writes is styled; no external URL in `assets/ui` or `templates/` | CI green; nothing rendered changes yet |
| 3 | New template and base layout | `templates/resurrection.htm` and its CSS: header, three columns, footer with `{copyright}`, Credits, Source, version; popup parts; phone stacking; made the default skin (installer default and the fallbacks), old skins still selectable | Static: every template part the code uses exists, every required tag present, `{copyright}` in footer and popupfoot; PHP-CLI render of a signed-out and a signed-in page and a popup; `OutputBufferingTest`'s page-head assertion moves to the new head | CI green; copyright counted once per page in the HTTP tests |
| 4 | Header and footer | Mail badge with unread count, petition and MoTD as real links (popup as enhancement), `{newday}` time to the next new day | PHP-CLI renders with and without unread mail; badge count; countdown matches `secondstonextgameday()` | CI green; translation changes recorded |
| 5 | Navigation | Nav groups, key badges, the key script as a static file with a JSON key map, 44px rows | Unit: hotkey choice unchanged for a set of labels (including `X?`, missing key, translated labels); render: each badge matches its `accesskey`; HTTP nav-following tests unchanged | All four shards (shared helper); CI green |
| 6 | Character panel | HP and XP bars, stat tiles, equipment rows, race portrait slot; `addcharstat()` rows from modules still shown | PHP-CLI render alive and dead (soulpoints), with companions and buffs; bars carry numbers; a module `charstats` row appears | All four shards; CI green |
| 7 | Phone layout | Bottom tab bar, compact HP/XP header, stacking below the breakpoints | Static: tab bar links only allowed navs or anchors; one browser check (Playwright, 390px wide): no horizontal scroll, tab bar visible, targets 44px | CI green |
| 8 | Forms, buttons and tables | Labels on inputs, primary vs secondary buttons, table styling and scroll boxes, inline styles from `appoencode()` replaced by classes | Static: every `showform()` input labelled; render of a form page and an admin table | All four shards if `appoencode()` changes; CI green |
| 9 | Commentary and battle log | Commentary list and 44px input with Add; battle rounds as an ordered list | Render of a commentary section and a forest fight; HTTP combat tests unchanged | All four shards (combat, commentary); CI green |
| 10 | Popups, admin and superuser screens | Mail, petition, MoTD, translator popups; the Grotto and editors styled | Render each popup; HTTP admin tests unchanged | CI green |
| 11 | Image pipeline | `assets/` layout, `assets/LICENSE` (CC BY-SA 4.0) and README, `src/Ui/Art.php` lookups, fallbacks, the scene and portrait slots, alt text | Unit: lookups and fallbacks; static: every SVG has no script or external reference; every image has alt text | CI green |
| 12 | Art: location scenes | One woodcut scene per place | Static: one scene per mapped place, palette colors only, size budget | CI green; Jim reviews the art |
| 13 | Art: race portraits and icons | Four race portraits plus unknown, the icon set | Static: every icon the UI names exists, 24px grid, stroke 1.8 | CI green; Jim reviews |
| 14 | Art: creature parts kit and portraits | `kit.json` for all 288 seeded creatures, parts, type fallbacks, the generator | Static: every seeded creature has a recipe and every part exists; render in a forest fight | CI green; Jim reviews |
| 15 | Remove the 1.1.2 skins and skin selection | Section 2's list | Tests: no skin file, no skin form or cookie read; old cookie and stored `defaultskin` harmless; route and disabled-endpoint scans updated | All four shards; CI green; each removal recorded |

Order: 2 and 3 first (the frame everything sits in), then 4 to 10 (components, roughly by how often a player sees
them), 11 before any art, 12 to 14 the art, and 15 last, once nothing depends on a 1.1.2 skin.
