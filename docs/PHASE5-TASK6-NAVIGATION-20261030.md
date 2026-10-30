# Phase 5 task 6: navigation (2026-10-30)

Jim's brief, "PHASE 5 TASK 6: NAVIGATION": key badges, a static key script, 44px rows, and no nav column on a page
with no nav. Expected start: `main` at `98fb6ec` (it matched).

## Task 5 accepted

Recorded in `PHASE5-TASK5-IMAGES-20261030.md` ("Accepted") and ticked in `ROADMAP.md`: Modern core 41120765631
SUCCESS, all 12 jobs; Baseline integrity 41120765632 SUCCESS.

## How 1.1.2 chooses a hotkey

`private_addnav()` in `lib/output.php` at `98fb6ec` is byte for byte the one at `historical-source-1.1.2` (checked
with `diff`). For each nav with text and a link it decides one key:

1. `"X?Label"`: the key X is asked for. If X (lowercased) is already taken, or is a space, the label is searched as in
   step 2. Otherwise, if X is not in the label (a case-sensitive search), 1.1.2 puts "(X) " before the label (or in
   place of a `__` in it) and the key is the first X in the new text, found with `strpos($text, $key)` on the key as
   asked. A lowercase key asked for and absent from the label is therefore never found; `strpos()` returns `false`,
   which reads as position 0, and the key becomes the "(" of the prefix.
2. Otherwise the first letter or digit of the label that is not taken, skipping color codes (the character after a
   backtick), entities (`&...;`) and HTML tags (`<...>`).
3. No free letter or digit: no key.

The key is recorded in `$accesskeys` (lowercase) and in `$quickkeys` with what the key does. Task 6 does not change
this logic: the new code runs the same lines on the same text, including the "(X) " prefix, and only changes how the
result is shown.

## Hotkeys pinned from 98fb6ec

`tests/fixtures/nav-render.php` renders navs with the game's own nav code (`addnav()`, `buildnavs()`,
`private_addnav()`, `templatereplace()`, `loadtemplate()`) and stand-ins for the functions that read the database or
the translation tables. `tests/unit/NavigationTest.php` feeds it 47 navs in four groups. Run on `98fb6ec` first, the
keys were:

| Nav (addnav text) | Case | Key at 98fb6ec |
|---|---|---|
| `D?Talk to Dag Durnick` | "X?Label", key in the label | D |
| `Q?Return to the Village` | "X?Label", key not in the label ("(Q) " added) | Q |
| `q?Quiet corner` | "X?Label", key already taken | u |
| `v?Return home` | "X?Label", lowercase key not in the label | ( |
| `` !?`&&#149; __GOD MODE `` | "X?Label" with color code, entity and `__` | ! |
| `T?Train` | "X?Label" | T |
| `Forest` | plain | F |
| `Fight the Master` | first letter taken | i |
| `Visit the Tavern`, translated to `Besuche die Taverne` | translated label | B |
| `Bank`, translated to `` `^Ye Olde `2Bank `` | translated, color codes, B taken | Y |
| `` `@Green `^Dragon `` | color codes | G |
| `&#149; Mercenary Camp` (priv) | entity | M |
| `<b>Hall of Fame</b>` (priv) | HTML | H |
| `Mail` (popup, 550x300) | popup | a |
| `Open a window` (popup, no size) | new window | O |
| `Armor Shop` ... `Riddles` (9 navs) | letters running out | r, W, e, J, K, L, N, c, P, s |
| `Stables`, `Undertaker` | no free letter | none |
| `Vault`, `Xylophone` | | V, X |
| `Yard` | no free letter | none |
| `Zoo` | | Z |
| `Clock`, `Ice` | no free letter | none |
| `Pier 9`, `0 to 8 in 1 2 3`, `4 5 6 7` | digits | 9, 0, 4 |
| `Go`, `!!!` | no free letter or digit | none |
| `Digits 12345678` (7 times) | the page runs out of keys | 1, 2, 3, 5, 6, 7, 8 |
| `Last one`, `Zebra` | all 36 letters and digits taken | none |

Also a heading per group, a help line (`addnav("Read the help", "")`) and a hidden nav (`addnav("", "hidden.php")`).
`$accesskeys` at the end: `d q u ( ! t f i b y g m h a o r w e j k l n c p s v x z 9 0 4 1 2 3 5 6 7 8`.

With the new code the keys are identical in the new template and in two old skins (`jade.htm`, `Classic.htm`), and
`jade.htm`'s navs render byte for byte as at `98fb6ec` (the time in each link written as `HHMMSS`; 5,991 bytes,
SHA-256 `6dd157864acc856d886e604bd3889895c8c3221411e138e9e85ac34c7e332116`).

## The markup

At `98fb6ec` the Resurrection template's `navitem` part was
`<a href="{link}" {accesskey} class="rz-navitem nav" {popup}>{text}</a>`, and `{text}` held 1.1.2's marking:
`Talk to <span class='navhi'>D</span>ag Durnick`, `(<span class='navhi'>Q</span>) Return to the Village`.

Now the part is

```
<a href="{link}" {accesskey} class="rz-navitem nav" {popup}><span class="rz-key" aria-hidden="true">{key}</span><span class="rz-navlabel">{text}</span></a>
```

and a nav renders as

```
<a href="village.php?c=7-HHMMSS"  accesskey="Q" aria-keyshortcuts="Q"  class="rz-navitem nav" ><span class="rz-key" aria-hidden="true">Q</span><span class="rz-navlabel">Return to the Village</span></a>
```

- `private_addnav()` checks whether the template's `navitem` part has `{key}`. If it does, it passes the key (HTML
  escaped) as `{key}`, writes `accesskey` and `aria-keyshortcuts` with the same key into `{accesskey}`, and shows the
  label without the `` `H `` marking and without the "(X) " prefix (or its `__` place). If it does not (every 1.1.2
  skin), nothing changes: the same marking, the same parts, the same keys.
- A nav without a key gets an empty badge (`<span class="rz-key" aria-hidden="true"></span>`), drawn without an
  outline, and no `accesskey` or `aria-keyshortcuts`.
- The badge is `aria-hidden`, so the link's accessible name is the label alone. Checked in Chromium's accessibility
  tree for the village: `link "Forest"`, `link "MightyE's Weaponry"`, `link "F.A.Q. (newbies start here)"`.
- The label is encoded by the new `resurrection_nav_label()`: `appoencode()` on the label alone, every tag it opens
  closed at its end, and the page's open-tag state left as it was. In 1.1.2 a color a label left open ran into the
  next label, which then began with a stray `</span>`; inside the new label element that would close the element.
- The translator's buttons (`tlbutton_pop()`) are still put before each `<a `.
- Popup navs keep their `target='_blank'` and click handler.

## The key script

1.1.2's `page_footer()` wrote an inline script into `{script}` on every page:

```
document.onkeypress=keyevent;
function keyevent(e){ ... c = the key, uppercased ...
	if (target.nodeName.toUpperCase()=='INPUT' || target.nodeName.toUpperCase()=='TEXTAREA' || altKey || ctrlKey){
	}else{
		if (c == 'F') { window.location='forest.php?c=5-205333'; return false; }
		...
```

with one line per key: `window.location='<link>'`, `window.open('<link>')` for a popup without a size, or `popup()`'s
`window.open('<link>','<name>','scrollbars=yes,resizable=yes,width=W,height=H').focus()`.

**Typing in a form field (1.1.2):** a key does nothing while the focus is in an `INPUT` or a `TEXTAREA`, or while Alt
or Ctrl is held. It is not checked for a `SELECT`, a button, Meta (Cmd) or anything else. When a key goes somewhere,
`return false` stops it being typed. The new script keeps exactly this rule.

Now:

- `$quickkeys` holds data, not script: `["go", url]`, `["open", url]` or `["popup", url, name, features]`, the name
  and features from the new `popup_window()`, which `popup()` now uses too (its output is unchanged).
- `resurrection_nav_keys()` lists them as `[KEY, action, url, ...]`, the key uppercase as 1.1.2 compared it, in the
  order the keys were taken; `resurrection_nav_script()` writes
  `<script type="application/json" id="rz-keys">[...]</script>` and `<script src="assets/ui/keys.js" defer></script>`
  into `{script}`. A page with no hotkey gets neither.
- The JSON escapes `<`, `>`, `&`, `'` and `"`, so nothing in a URL can end the element. It is a list of lists, and a
  brace in a URL is written `\u007b` or `\u007d`, because `page_footer()` removes every `{tag}` left in the page at
  the end (`/{[^} \t\n\r]*}/`): an object such as `{"url":"forest.php"}` would have been removed.
- `assets/ui/keys.js` (static, no build step) reads the map and listens for `keypress` as 1.1.2 did: the same
  uppercased key, the same targets, the same popup windows; nothing in an input or a textarea or with Alt or Ctrl;
  `preventDefault()` when a key goes somewhere, as `return false` did. A key the page used twice goes where the later
  one goes, as in 1.1.2's map.
- All skins use it: `{script}` is filled the same way for every template.
- Every nav is a plain link, so the game works without JavaScript.

Checked in Chromium on the village (local install, see Screenshots): pressing `f` went to `forest.php`; typing `f` in
the commentary input typed it and stayed on the village; Ctrl+F stayed on the village; with JavaScript off, a nav
click followed the link.

Not changed, noted: the village's commentary box logs a JavaScript error, `inputinsertcommentary.addEvent is not a
function`, from 1.1.2's `lib/forms.php` (it calls an `addEvent` no library provides, so 1.1.2's draft-saving code never
runs). It is unrelated to the navigation; fixing it would make that code run for the first time, which is a behavior
change, so it is left for the commentary task (task 10).

## No nav, no column

The header part now has `{navstart}{navad}{nav}{navend}` where it had the `<nav>` element, and two new parts hold the
element:

```
<!--!navstart--><nav class="rz-nav" aria-label="Navigation"><div class="rz-card rz-nav-panel">
<!--!navend--></div></nav>
```

`page_footer()` fills both from `resurrection_nav_column()`: the parts when the page shows a nav (any item, heading or
help text in the built navs, or anything a module put in `{navad}`), two empty strings when it shows none. A forest
fight's actions are forms (Phase 3) and its navs are hidden (`addnav("", $url)`), so it has no nav column; the content
column, `flex: 999 1 560px`, takes the space (measured: 1,091px of the 1,440px window). A
template without the parts (every 1.1.2 skin) gets two empty strings and keeps its own markup. `{nav}` stays in the
header, so `prepare_template()`'s required tags are unchanged.

## Rows and contrast

- `.rz-navitem`: a flex row, `min-height: var(--touch-target)` (44px), `width: 100%` of the nav card, 6px by 10px
  padding, `--radius-control` (8px), `--text`, the badge then a 10px gap then the label (`.rz-navlabel`, wraps).
  Measured in the village at 1440px: one-line rows 44px, two-line rows 54px, each 194px wide in the 220px card.
- `.rz-key`: 24px square (`--key-badge-size`), `--radius-badge` (5px), 1px `--input-border`, `background:
  transparent`, the key in `--accent`, Alegreya Sans 700, `--size-small` (13px), uppercase. Measured: 24x24.
- Hover and focus (`:hover`, `:focus-visible`): `background: var(--ground)`, and focus keeps the 2px `--accent`
  focus ring. Chromium's computed background on the focused row: `rgb(18, 16, 13)`, `--ground`.

WCAG 2.x contrast ratios (computed from the tokens; `test_ui_template.py` computes the outline ratios too):

| Pair | Ratio | Rule |
|---|---|---|
| Badge outline `--input-border` on `--panel` (the row at rest) | 3.04 | 3:1 non-text |
| Badge outline on `--ground` (hovered or focused row) | 3.26 | 3:1 non-text |
| Badge outline on `--raised` (not used) | 2.78 | fails, hence not `--raised` |
| Key `--accent` on `--panel` / `--ground` | 8.14 / 8.74 | 4.5:1 text |
| Label `--text` on `--panel` / `--ground` | 13.99 / 15.03 | 4.5:1 text |

A lighter fill fails: every accent tint of the panel from 3% to 8% puts the outline between 2.90 and 2.64.

## Spec and ledger

`UI-DESIGN-SPEC.md`: the "Navigation" component (rows, key badge, hotkeys, no nav no column) replaces the "Nav groups"
and "Key badges" lines, which had the badge on `--raised` and the rows `--raised` on hover; `--raised`'s use no longer
lists hovered rows, and the layout's nav-row sentence points to the component. `CHANGES-FROM-1.1.2.md`: four rows
(the badge, the key script, the empty nav column, the rows).

## Tests: failing first, passing after

New and changed tests:

- `tests/unit/NavigationTest.php` (new, no database), with `tests/fixtures/nav-render.php`:
  - `testEveryNavKeepsTheKey98fb6ecGaveIt`: the pinned keys and `$accesskeys`, in `resurrection.htm`, `jade.htm` and
    `Classic.htm`.
  - `testAnOldSkinRendersItsNavsAsBefore`: `jade.htm`'s navs byte for byte (length and SHA-256 from `98fb6ec`).
  - `testEachKeyShowsAsABadgeBeforeAPlainLabel`: badge = `accesskey` = `aria-keyshortcuts` on every nav (none on a
    nav without a key); the link holds only the hidden badge and the label; no marking, no "(X) ", balanced tags.
  - `testTheKeyMapHasOneEntryPerKeyGoingWhereTheLinkGoes`: one entry per key, the link's URL, the popup and new
    window entries; the `{script}` exactly the JSON data and the static script; the `{tag}` clean-up leaves it whole,
    with a brace in a URL too; no key, no script.
  - `testTranslatorButtonsStayBeforeTheLink`.
  - `testAPageWithNoNavHasNoNavColumn`.
- `tests/unit/TemplateRenderTest.php`: on the signed-out home page and the village, every nav's badge, `accesskey` and
  `aria-keyshortcuts` agree, the key map has one entry per key with the link's URL, and there is no `onkeypress`,
  `keyevent` or `navhi`; the forest fight has no `<nav`, no nav card, no nav item and no key map.
- `tests/tooling/test_ui_template.py`: `PART_KEYS` gains `{key}` for `navitem` and the `navstart` and `navend` parts;
  `FRAME_TAGS` gains `navstart` and `navend`; new `test_the_nav_and_its_key_script` (the parts, no inline script in
  the template or the code, `keys.js`'s rule) and `test_the_nav_row_and_badge_styles` (44px, the badge's tokens, no
  `--raised`, the outline at 3:1 or more on every fill it sits on); `test_contrast` checks the ratio helper.

**On `98fb6ec`** (the new tests copied into a `git archive` of `98fb6ec`):

- `NavigationTest`: the two pin tests and the translator test pass (the pins are `98fb6ec`'s own); the badge, key map
  and nav column tests fail (`Failed asserting that null is identical to 'D'`; `null is of type array`).
- `TemplateRenderTest`: fails on the signed-out home page (no nav column parts: `0 is identical to 1`).
- `test_ui_template.py`: 4 failures (`navitem` lacks `{key}`, no `navstart` part, the inline script, the `--raised`
  hover).

**On a broken copy of the new code** (`strpos($text, $key, 3)` in the prefix branch; `resurrection_nav_column()`
always returning the parts): the pin test fails (`two arrays are identical`), the old-skin test fails (`6037 is
identical to 5991`), the key map test fails, the nav column test fails, and `TemplateRenderTest` fails at the forest
fight's `<nav` check.

**After:** `NavigationTest` and `TemplateRenderTest` OK (7 tests, 890 assertions); `test_ui_template.py` OK (15 tests).

The HTTP tests were not changed: none reads a nav's label text through the markup the badge changed.

## Local verification

All four shards, run in parallel through `scripts/tooling-shards.py` with `RESURRECTION_REQUIRE_PHP=1` (PHP 8.5.12,
one MySQL 8.4 container each, PHPUnit first on each empty database, exit 0 on all four). The nav code renders every
page, so all four were run:

| Shard | Ran | Failures | Errors | Skipped | Python duration | Wall (with PHPUnit) |
|---|---|---|---|---|---|---|
| 1/4 | 68 (68 assigned) | 0 | 0 | 0 | 13.7 min | 844 s |
| 2/4 | 75 (74 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.4 min | 1005 s |
| 3/4 | 67 (66 assigned + 1 prerequisite) | 0 | 0 | 0 | 15.4 min | 947 s |
| 4/4 | 68 (67 assigned + 1 prerequisite) | 0 | 0 | 0 | 16.4 min | 1009 s |

The shard copies were taken before this evidence doc was written; nothing else changed after them.

- `vendor/bin/phpunit` on an empty MySQL 8.4 database (PHP 8.5.12): OK, 298 tests, on each of the four shards.
- `phpstan analyse -c phpstan.neon` and `-c phpstan-new.neon`: no errors.
- `composer lint`: 397 files, 0 failures.
- Historical verifier: PASS (tag object `51cab4f`, commit `bdc29df`, tree `4013a0c`, 417 files, 11 commits).
  Project validator: PASS (716 tracked files, the new files included). Release upgrade check: passing (no release
  yet). `git diff --check`: clean. `LICENSE.txt` and `README.txt` byte-identical to `historical-source-1.1.2`.
  `composer validate --strict`: valid; `composer audit`: no advisories. Static tooling tests (`test_ui_template`,
  `test_ui_tokens`, `test_art`, `test_tooling_shards`, `test_route_inventory`, `test_disabled_endpoints`,
  `test_license_text`, `test_version`): OK, 53 tests.

Local results are supporting evidence; CI is the acceptance.

## Screenshots

1440x1000 and 390x844 of the village signed in, a forest fight, the signed-out home page and the weapon shop, plus the
nav card with a focused and a hovered row, taken with Playwright from a throwaway local install with all bundled
modules (not committed; sent to Jim in the task report).

## Not changed

- Which key any nav gets; where a player may go: `allowednavs`, bad-nav handling, hidden navs (`addnav("", $url)`),
  popup navs, `navhelp`, and the `collapse-nav{` / `}collapse-nav` hooks (`buildnavs()` is unchanged).
- The old skins' navs (task 15 removes them), the phone layout (task 8: the nav still stacks above the content on a
  phone).
- The 1.1.2 copyright line, the attribution line, `LICENSE.txt`, `README.txt`, the version string, the CI workflows,
  the shard timeout, `tests/tooling/shard-durations.json`.
- No tag, release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 6 only
after CI accepts it.
