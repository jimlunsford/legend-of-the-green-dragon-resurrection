# Phase 5 task 3: new template and base layout (2026-10-30)

Evidence for Phase 5 task 3: the Resurrection UI's page frame, `templates/resurrection.htm` with
`assets/ui/resurrection.css`, made the default skin. It is the first task whose result shows in the game. What pages
do is unchanged: no page, nav, form or game rule changed, and no new tag was added.

Starting point: `main` at `4606b4ab2f69f31d4c7949bffe49d9ca59a89706`, as the task expected.

## Task 2 accepted

`4606b4a` was confirmed through the GitHub Actions API: Modern core 41077391337 SUCCESS with all 12 jobs, Baseline
integrity 41077391247 SUCCESS (shard times in `PHASE5-TASK2-TOKENS-20261030.md`, where the acceptance is appended).
Task 2 is ticked in `ROADMAP.md`.

## Spec corrections (the approved mockup)

The spec had drifted from the mockup Jim approved. Each value below is set in `docs/UI-DESIGN-SPEC.md` and
`assets/ui/tokens.css` (marked "mockup" in the spec), and `tests/tooling/test_ui_tokens.py` ties them together: the
spec and the file must hold the same values (as before), and a new test, `test_the_mockup_values`, pins these exact
values in both.

| Token | Was | Now | What it is |
|---|---|---|---|
| `--radius-panel` | 6px | 12px | Panels and cards |
| `--radius-control` | 4px | 8px | Buttons, inputs, nav rows, header controls |
| `--radius-badge` | 3px | 5px | Key badges |
| `--radius-bar` | (none) | 999px | Bars, fully rounded |
| `--size-title` | 26px | 34px | Page title, Cinzel 700, desktop |
| `--size-title-phone` | (none) | 24px | Page title on a phone |
| `--tracking-title` | (none) | 0.03em | Page title letter spacing |
| `--size-body` | 16px | 19px | Story text, Alegreya, desktop |
| `--leading-body` | 1.55 | 1.65 | Its line height |
| `--size-body-phone` | (none) | 17px | Story text on a phone |
| `--leading-body-phone` | (none) | 1.6 | Its line height |
| `--key-badge-size` | 22px | 24px | Key badge, a square |
| `--bar-height` | 10px | 8px | Bars in the character panel |
| `--bar-height-fight` | (none) | 10px | Bars in a fight |
| `--size-ui` | 15px | 16px | The page's base size, Alegreya Sans |
| `--space-gutter` | 16px | 24px | Between columns |
| `--space-panel` | 16px | 20px | Character and nav panel padding |
| `--width-container` | (none) | 1440px | The frame's widest content, centered |
| `--space-side` | (none) | 32px | Side padding of the frame, header and footer (16px on a phone, `--space-side-phone`, unchanged) |
| `--space-header-y` | (none) | 14px | Header bar padding, top and bottom |
| `--space-card-y`, `--space-card-x` | (none) | 32px, 36px | Content card padding |
| `--space-card-phone` | (none) | 20px | Content card padding on a phone |
| `--size-wordmark`, `--tracking-wordmark` | (none) | 22px, 0.06em | "Legend of the Green Dragon", Cinzel 700 |
| `--size-wordmark-sub`, `--tracking-wordmark-sub` | (none) | 13px, 0.2em | "RESURRECTION", Cinzel 500, `--accent` |
| `--tracking-nav-heading` | (none) | 0.16em | Nav group headings, Cinzel 700, 13px, uppercase, `--muted` |

Why: each is the value in the approved mockup; the earlier spec had set its own (task 1) where the mockup was not
transcribed. The type sizes stay at or above the 13px floor. No color changed, so no contrast figure changed.

**New rule** (spec, Palette): inputs and outlined controls never sit on a `--raised` fill. `--input-border` is 3.04:1
on `--panel` but 2.78:1 on `--raised`; on `--header` and `--ground`, both darker than the panel, it clears 3:1. The
test checks the three grounds clear 3:1 and that `--raised` does not, and the stylesheet follows the rule: no table row
uses `--raised` (striped rows alternate `--panel` and `--header`), and the header controls sit on `--header`.

The spec's Layout section now also records the frame (container, columns with their flex values, the content card, nav
rows, character cards, footer), so the template and the spec describe the same page.

## The template's parts, and where the code uses them

`loadtemplate()` splits a template at each `<!--!name-->` marker. `templatereplace()` prints a warning into the page
when a part, or a key of one, is missing, so the template must have every part the code asks for. The list was read
from the code (every `templatereplace()` call, every `$template[...]` read, and the `template-<part>` hooks `common.php`
loads), not guessed; `tests/tooling/test_ui_template.py` reads it the same way on every run.

| Part | Used by | Filled with | In the new template |
|---|---|---|---|
| `header` | `page_header()`, `page_footer()` (`lib/pageparts.php`), `prepare_template()`'s tag check | `{title}`, `{headscript}`, `{script}`, `{nav}`, `{mail}`, `{motd}`, `{petition}`, `{petitiondisplay}`, and the ad slots `{headerad}`, `{navad}`, `{verticalad}`, `{bodyad}` | The HTML5 page head, the header bar, the nav column, and the content column up to the page itself |
| `footer` | `page_footer()`, the tag check | `{stats}`, `{copyright}`, `{source}`, `{version}`, `{pagegen}`, `{paypal}` (emptied) | The end of the content card, the character column, the footer |
| `popuphead` | `popup_header()` | `{title}` | Page head, header bar with the wordmark, the title, the content card |
| `popupfoot` | `popup_footer()` | `{copyright}` | The end of the card, a footer with the credit line |
| `statstart`, `stathead`, `statrow`, `statbuff`, `statend` | `getcharstats()` (`lib/pageparts.php`) | `{title}`, `{value}` | Each stat section is its own card: `statstart` opens a hidden lead section, and every `stathead` or `statbuff` closes the open section and opens a card, so a module's `addcharstat()` sections are cards too |
| `navhead` | `private_addnav()` (`lib/output.php`) | `{title}` | An `<h2>` nav group heading |
| `navhelp` | `private_addnav()` | `{text}` | A muted line |
| `navitem` | `private_addnav()` | `{link}`, `{accesskey}`, `{popup}`, `{text}` | A 44px row link; `href` keeps its double quotes, and the hotkey letter stays marked by `` `H `` (`.navhi`) until task 5 |
| `petitioncount` | `page_footer()` (staff) | `{petitioncount}` | A muted line under the header controls |
| `login` | `home.php` | `{username}`, `{password}`, `{button}` | The same inputs (`name`, `id`, `accesskey`), now with `<label for>`, and the button as the page's primary action |
| `loginfull` | `home.php` | (none) | "Server Full!", as jade had it |
| `adwrapper` | `common.php`'s `template-adwrapper` hook (no core call; kept for modules) | `{content}` | A card |

Required tags (`prepare_template()`, `lib/template.php`): `{title}`, `{headscript}` and `{script}` in the header;
`{nav}`, `{stats}`, `{petition}`, `{motd}`, `{mail}`, `{paypal}`, `{source}`, `{version}` and `{copyright}` (also
pushed as `$y2 ^ $z2`) in the header or footer. All present. `{copyright}`, `{version}` and `{pagegen}` are filled only
in the footer part, so they are there. `{copyright}` is exactly once in `footer` and once in `popupfoot`, nowhere else.
None of the copyright code changed, and no check was weakened.

The template is ASCII (pages are sent in the game's `charset` setting, `iso-8859-1` by default), and holds no `{...}`
text other than those tags, because the footer clean-up removes any unfilled `{tag}`.

## What was styled

`assets/ui/resurrection.css` loads `fonts.css` and `tokens.css` (relative URLs) and uses only token values; the two
breakpoints are literals equal to their tokens. The page:

- **Page:** `--ground`, `--text`, Alegreya Sans 16px. Container at most 1440px, centered, 32px side padding (16px on a
  phone), 24px between columns.
- **Header bar:** `--header`, 1px `--border` bottom, 14px by 32px padding. The wordmark at the left; `{mail}`,
  `{motd}` and `{petition}` at the right as 44px outlined controls (`--input-border`, 8px radius), an unread MoTD or
  mail (`hotmotd`) in `--accent`.
- **Columns:** nav `flex: 1 1 220px`, max 280px; content `flex: 999 1 560px`, `min-width: 0`; character
  `flex: 1 1 260px`, max 320px. They wrap when the window is too narrow, and below 900px each takes the full width.
  No sideways scroll at 390px (checked in the screenshots below: page width equals window width on every page).
- **Content:** the page title (`{title}`, Cinzel 700, 34px, 24px on a phone, tracking 0.03em) as the page's `<h1>`,
  then the page in a `--panel` card (1px `--border`, 12px radius, 32px by 36px padding, 20px on a phone) as story text
  (Alegreya 19px / 1.65, 17px / 1.6 on a phone). Forms, tables, labels and buttons inside it use Alegreya Sans.
- **Nav:** in a `--panel` card; headings Cinzel 700, 13px, uppercase, tracking 0.16em, `--muted`; rows 44px tall,
  8px radius, `--text`, `--raised` on hover. The panel hides when a page has no navs.
- **Character:** each stat section a `--panel` card with 20px padding (12px on a phone); label `--muted`, value bold.
  The signed-out online list sits in one card.
- **Footer:** `--header`, 1px `--border` top, 13px `--muted`. Left: `{copyright}` (the license block and the authors'
  line, unchanged), a "Credits" link to `about.php`, `{source}`. Right: `{version}`, then `{pagegen}`.
- **Focus:** a 2px `--accent` ring with 2px offset on every focusable element (`:focus-visible`). Links `--accent`,
  hover `--accent-hover`. A "Skip to content" link appears on focus.
- **Markup PHP prints:** `trhead` (`--header`, `--muted`, bold), `trlight` (`--panel`), `trdark` (`--header`),
  `trhilight` (an `--accent` bar at the row's start); `.button` and every button input (secondary: `--raised` fill,
  `--input-border`, 44px); text inputs, selects and textareas (`--raised` fill, `--input-border`, 8px radius, 44px,
  16px text); checkboxes in `--accent`; headings in Cinzel; tables scroll inside their own box
  (`display: block; overflow-x: auto`); images and `pre` never wider than the card; the color code classes from
  `tokens.css`.
- **Popups:** the same head and header bar, one column, the title, the card, and the footer with the credit line.

## Default skin

| Where | 1.1.2 | Now |
|---|---|---|
| `lib/installer/installer_default_settings.php` | `"defaultskin"=>"jade.htm"` | `"resurrection.htm"` |
| `prepare_template()` (`lib/template.php`) | falls back to `getsetting("defaultskin", "jade.htm")`, then `jade.htm`; a template with errors falls back to `jade.htm` | `resurrection.htm` in all three places |
| `loadtemplate()` (`lib/pageparts.php`) | `getsetting("defaultskin", "jade.htm")`, then `jade.htm` | `resurrection.htm` in both |
| `home.php`, `prefs.php` (the skin pickers' preselected value with no cookie) | `getsetting("defaultskin", "jade.htm")` | `getsetting("defaultskin", "resurrection.htm")` |

The 18 old skins stay selectable, on the home page and in Preferences, until task 15. No release exists yet, so no
upgrade step: an install made before this commit keeps its stored `defaultskin` (`jade.htm`) until an administrator
changes it in the game settings, and every fresh install starts on the new UI. Recorded in
`docs/CHANGES-FROM-1.1.2.md`, with the color code remap now marked as landed.

## Found: the Credits link for a signed-in player

`about.php` is open to anonymous visitors, but for a signed-in player it is under the forced-navigation check
(`do_forced_nav()`): a page may only be reached through a link the previous page allowed. The home page and the news
page allow it; other pages do not. So, checked in the browser on the local install, a signed-in player who follows
the footer's Credits link from the village or a fight is sent to `badnav.php`, which shows their current page again.
Nothing breaks and nothing is lost, but the link does nothing there. Allowing `about.php` from every page would let a
player leave a fight through About, then the news, then the village, so it is a game-flow change, not a template
change, and this task does not make it. Task 4 (header and footer) is the place to decide: for example, open Credits
from a signed-in page as a popup the way MoTD and mail are opened, or show it only where it is allowed.

## Tests

- **`tests/tooling/test_ui_template.py`** (new, static, 10 tests): every part the code uses exists, with exactly the
  keys the code fills; every required tag (read from `lib/template.php`, with the XOR tag decoded from `common.php`)
  is present; `{copyright}` once in `footer` and `popupfoot` and nowhere else; `{version}` and `{pagegen}` in the
  footer; every frame tag is one the code fills; the page and popup start with the doctype and load the stylesheet;
  ASCII only; the stylesheet loads `fonts.css` and `tokens.css` and its breakpoints equal the tokens; the template is
  the default in the installer and every fallback, with jade still present; and three tests of the readers.
- **`tests/unit/TemplateRenderTest.php`** (new, PHPUnit): with `output_buffering=0`, as `OutputBufferingTest` does, the
  PHP CLI renders a signed-out `home.php`, a signed-in `village.php` (a player the game creates, signed in through the
  session `do_forced_nav()` checks) and the `mail.php` popup. Each exits 0 with nothing on stderr, starts with the
  template's doctype, loads the stylesheet, has no "Runtime error", no template warning, the copyright line and the
  license link exactly once, and ends with `</html>`. It also checks the fresh install stores `resurrection.htm`, the
  login form's fields, the stat cards, the nav rows and the popup's one-column body. It installs an empty database
  itself (`--modules=none`) and empties it again, and removes its player.
- **`tests/unit/OutputBufferingTest.php`**: the page-head assertion moved to the new template. It asserted jade's
  `"\r\n<!DOCTYPE"`; it now asserts the page starts with `<!DOCTYPE html>\n<html lang="en">\n<head>\n` (the doctype is
  written right after the `<!--!header-->` marker, so the page's first byte is the doctype's) and loads the
  stylesheet. It still proves nothing is printed before the page.
- **`tests/tooling/test_ui_tokens.py`**: `test_the_mockup_values` (new) pins the corrected values and the raised rule;
  the request scan now asserts it covers the new template and stylesheet.

### Failing first, then passing

| Run | Result |
|---|---|
| New static tests on `4606b4a`'s tree | `test_ui_template`: 7 errors (`FileNotFoundError: templates/resurrection.htm`); the 3 reader tests pass. `test_ui_tokens`: `test_the_mockup_values` FAIL (`'12px' != '6px'`), the request scan FAIL (`'templates/resurrection.htm' not found` in the scanned files) |
| New PHPUnit tests on `4606b4a`'s code, empty database | `TemplateRenderTest` FAIL (the stored `defaultskin` is `jade.htm`, not `resurrection.htm`); `OutputBufferingTest` FAIL (`'\r\n<!DOCTYPE HTML PUBLIC ...' starts with "<!DOCTYPE html>"`). (Its other test, `testNoOtherPhpFilePrintsOutsideItsTags`, also failed there only because that copy was not a Git checkout.) The database was empty again afterwards (0 tables) |
| Broken copy A: `{copyright}` removed from `popupfoot` | static FAIL (`1 != 0 : popupfoot`); render FAIL (`0 is identical to 1`: no copyright line in the mail popup) |
| Broken copy B: the `statbuff` part removed | static FAIL (`'statbuff' not found`); render FAIL (the village page stops, exit 255) |
| Broken copy C: a newline before the doctype in `header` | static FAIL (`test_page_and_popup_heads`); `OutputBufferingTest` and `TemplateRenderTest` FAIL (`'\n<!DOCTYPE html>...' starts with "<!DOCTYPE html>"`) |
| Broken copy D: the installer default back to `jade.htm` | static FAIL (`test_it_is_the_default_skin`); render FAIL (the stored default is not `resurrection.htm`) |
| Broken copy E: `common.php`'s output buffering fix reverted | `OutputBufferingTest` FAIL (`255 is identical to 0`, the page stops before printing) |
| Broken copy F: `--radius-panel` back to 6px in the spec and `tokens.css` | `test_the_mockup_values` FAIL (`'12px' != '6px'`) |
| Broken copy G: `@import url('https://fonts.googleapis.com/...')` in `resurrection.css` | the request scan FAIL, and `test_the_stylesheet_and_what_it_loads` FAIL |
| The real files | `test_ui_template` 10 OK, `test_ui_tokens` 8 OK; `TemplateRenderTest` OK (62 assertions) and `OutputBufferingTest` OK, on an empty database and in the full suite |

Each broken copy was made in a scratch copy of the tree and undone; none is committed.

## Screenshots for Jim

Taken with the pre-installed Playwright (Chromium) against a local install with the recommended modules
(`php scripts/install.php`, its own disposable database, `php -S` on 127.0.0.1), at 1440x1000 and 390x844. Not
committed. Random forest events were turned off in that throwaway game only (`forestchance` 0) so the forest search
reached a fight. Paths are in the task report.

## Local verification (supporting evidence only)

PHP 8.5.12 (php-builder, as setup-php) and MySQL 8.4 in Docker:

- `vendor/bin/phpunit` on a fresh empty database: OK, 284 tests, 141406 assertions (283 before, plus
  `TemplateRenderTest`).
- **All four shards**, because the template frames every page: each in its own copy of the tree with its own MySQL 8.4,
  `vendor/bin/phpunit` then `python3 scripts/tooling-shards.py --shards 4 --shard N` with `RESURRECTION_REQUIRE_PHP=1`,
  as CI runs them. All OK, no failure, error or skip:

  | Shard | Tests | PHPUnit | Shard (Python) |
  |---|---|---|---|
  | 1 | 66 (66 assigned) | 20s, OK | 984.3s (16.4 min) |
  | 2 | 63 (62 assigned + 1 prerequisite) | 21s, OK | 947.9s (15.8 min) |
  | 3 | 70 (69 assigned + 1 prerequisite) | 21s, OK | 935.7s (15.6 min) |
  | 4 | 65 (64 assigned + 1 prerequisite) | 21s, OK | 960.9s (16.0 min) |

  The new static test is assigned automatically; `test_tooling_shards` passes.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 393 files, 0 failures. `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: PASS, 417 files, 11 historical commits, tag object, commit and tree as
  recorded. `scripts/validate-project.py`: PASS. `git diff --cached --check`: clean. `LICENSE.txt` and `README.txt`
  byte-identical to the tag.
- `scripts/release-upgrade-check.py`: "no release yet", passing. With `--release-ref HEAD` run on the uncommitted
  tree, it installed `4606b4a`, upgraded it to this tree and found exactly one difference from a fresh install: the
  stored `defaultskin` (`jade.htm` upgraded, `resurrection.htm` fresh). That is the expected result of changing an
  installer default without an upgrade step, and the reason none is needed now: no release exists. Once this commit
  is `HEAD`, the check compares it with itself, as CI runs it. When a release exists, a change to an installer default
  needs an upgrade step or this check fails, as it should.

## Not changed

- What any page does: no page script, nav, form, game rule or translation changed. No new template tag.
- `LICENSE.txt`, `README.txt`, the copyright and license code, the version string, the CI workflows, the shard
  timeout and `tests/tooling/shard-durations.json`.
- The 18 old skins and skin selection (task 15). No `assets/LICENSE` (the art task). No file labelled AGPL. No tag,
  release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 3
only after CI accepts it.

### Accepted (recorded 2026-10-30, Phase 5 task 4)

`976a61e` is accepted. Confirmed through the GitHub API before task 4 started:

- Modern core [41094820755](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/41094820755):
  SUCCESS, all 12 jobs (both `checks` jobs, all eight `http` shard jobs, both `result` jobs). Shard jobs, from the
  jobs' start and completion times:

  | Leg | Shard 1 | Shard 2 | Shard 3 | Shard 4 |
  |---|---|---|---|---|
  | PHP 8.4 / MariaDB 11.4 | 18:29 | 18:19 | 17:03 | 14:43 |
  | PHP 8.5 / MySQL 8.4 | 19:11 | 19:27 | 17:27 | 19:29 |

- Baseline integrity [41094820993](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/41094820993):
  SUCCESS.
