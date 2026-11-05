# Resurrection UI design spec

The design contract every Phase 5 UI task builds to. One dark fantasy UI replaces all 1.1.2 skins
(`PHASE5-TASK1-DIRECTION-20261030.md`). The plan and the order of work are in `PHASE5-UI-PLAN-20261030.md`.

Values marked **approved** are the ones Jim approved in the mockups on 2026-10-30. Use them exactly. Phase 5 task 3
(2026-10-30) corrected the values that had drifted from the approved mockup: the radii, the page title, the story text,
the key badge, the bars, the column gutter, the UI text size and the frame's measurements. Each is marked "mockup" below,
with the value task 1 or 2 had set; the reasons are in `PHASE5-TASK3-TEMPLATE-20261030.md`. Values marked
**set here** were chosen in Phase 5 task 1 to complete the system; a later task may tune them, and records the change
here when it does. Changing an approved value needs Jim.

`assets/ui/tokens.css` holds every value below as a CSS custom property, and styles every color code class
(Phase 5 task 2). `tests/tooling/test_ui_tokens.py` keeps the file and this spec equal: it reads the tables below
(every row whose first cell is a `--token`, and the color code table), and checks contrast.

## Palette (approved)

| Token | Value | Use | Text |
|---|---|---|---|
| `--ground` | `#12100D` | Page background | no |
| `--header` | `#17140F` | Header bar, footer, phone tab bar | no |
| `--panel` | `#1B1814` | Nav, content and character panels. The reference color for text contrast | no |
| `--raised` | `#24201A` | Stat tiles, table heading rows (labels only; task 9: inputs `--panel`, buttons unfilled) | no |
| `--track` | `#2C2720` | Empty part of a bar (HP, XP) | no |
| `--border` | `#3A3329` | Panel and row borders (decorative) | no |
| `--input-border` | `#6E6452` | Input, button and key-badge outlines | no |
| `--text` | `#ECE4D4` | Body text | yes |
| `--muted` | `#A99F8D` | Labels, secondary text, the footer | yes |
| `--accent` | `#D9A84E` | Ember gold: primary action fill, links, focus ring, key badges | yes |
| `--accent-hover` | `#F0C46E` | Hover and pressed state of the accent | yes |
| `--health` | `#7FB069` | The player's HP bar and healing | yes |
| `--foe` | `#C9553D` | Enemies' HP bars and foe markers | no |
| `--damage` | `#E8826A` | Damage numbers and damage text | yes |
| `--magic` | `#8FB8D9` | Magic, buffs, specialty effects | yes |
| `--narrative` | `#9CCB8A` | Narrative green: story highlights | yes |

"Text" marks the tokens used for text. Each clears 4.5:1 on `--panel` and on `--raised` (WCAG 2.x ratio):

| Token | On panel | On raised |
|---|---|---|
| `text` | 13.99 | 12.81 |
| `muted` | 6.76 | 6.19 |
| `accent` | 8.14 | 7.45 |
| `accent-hover` | 10.80 | 9.89 |
| `health` | 7.00 | 6.41 |
| `damage` | 6.60 | 6.04 |
| `magic` | 8.44 | 7.73 |
| `narrative` | 9.54 | 8.73 |

Text on an accent fill (`--ground` on `--accent`): 8.74. No approved value had to change to pass on `--raised`.

Not for text:

- `--foe` is 4.08:1 on the panel, under 4.5. Jim confirmed on 2026-10-30: bars, fills and markers only (3.42:1
  against `--track`, which clears the 3:1 non-text rule). Text about damage uses `--damage`.
- `--input-border` was `#4A4235` (1.79:1). Jim's decision, 2026-10-30: `#6E6452`, so input, button and key-badge
  outlines clear the 3:1 non-text rule against the panel they sit on (3.04:1; 2.78:1 against their own `--raised`
  fill, which is the inside of the control, not its surroundings).
- `--border` stays `#3A3329` (1.42:1): panel and row borders are decorative, never the only sign of a control.
- **Inputs and outlined controls never sit on a `--raised` fill** (Jim, 2026-10-30, task 3). `--input-border` is
  3.04:1 against `--panel` but 2.78:1 against `--raised`, so a control is placed on `--panel`, `--header` or `--ground`
  (all darker, so the outline clears 3:1), never on a stat tile, a hovered row or another raised surface. Table rows
  therefore use no `--raised` fill: striped rows alternate `--panel` and `--header`. The one exception (task 9) is a
  table's heading row (`trhead`), which holds labels only, never a control.

## Type (approved)

All three families are SIL Open Font License. The woff2 files are self-hosted with their OFL texts (Phase 5 task 2).
The game makes no third-party font or CDN request.

| Family | Weights | Use |
|---|---|---|
| Cinzel | 500, 700 | Titles, character and creature names, section labels, uppercase tracked labels (nav group headings, stat labels) |
| Alegreya | 400, 500, italic | Narrative and story text: the page content `output()` produces, commentary, MoTD bodies |
| Alegreya Sans | 400, 500, 700 | Navigation, stats, forms, buttons, tables, the footer |

The files: `assets/fonts/<family>/`, woff2 only, latin and latin-ext subsets, each family's `OFL.txt` beside them;
`assets/ui/fonts.css` holds the `@font-face` rules (relative URLs, `font-display: swap`). Sources, versions and
checksums: `THIRD-PARTY-LICENSES.md`.

Fallback stacks (set here) and weights, as tokens:

| Token | Value |
|---|---|
| `--font-display` | `'Cinzel', Georgia, 'Times New Roman', serif` |
| `--font-story` | `'Alegreya', Georgia, serif` |
| `--font-ui` | `'Alegreya Sans', system-ui, -apple-system, 'Segoe UI', sans-serif` |
| `--weight-display` | `500` |
| `--weight-display-bold` | `700` |
| `--weight-story` | `400` |
| `--weight-story-medium` | `500` |
| `--weight-ui` | `400` |
| `--weight-ui-medium` | `500` |
| `--weight-ui-bold` | `700` |

Scale (set here). Text never goes below 13px:

| Token | Value | Use |
|---|---|---|
| `--size-body` | `19px` | Story text (Alegreya), desktop (mockup; was 16px) |
| `--leading-body` | `1.65` | Its line height (mockup; was 1.55) |
| `--size-body-phone` | `17px` | Story text on a phone (mockup) |
| `--leading-body-phone` | `1.6` | Its line height on a phone (mockup) |
| `--size-ui` | `16px` | UI text (Alegreya Sans), the page's base size (mockup; was 15px) |
| `--leading-ui` | `1.4` | Its line height |
| `--size-small` | `13px` | Footer, key badges |
| `--size-title` | `34px` | Page title, Cinzel 700, desktop (mockup; was 26px) |
| `--size-title-phone` | `24px` | Page title on a phone (mockup) |
| `--tracking-title` | `0.03em` | Page title letter spacing (mockup) |
| `--size-heading` | `18px` | Section heading, Cinzel 700 |
| `--size-label` | `13px` | Uppercase labels, Cinzel 500 (task 1 said 12px, which broke the 13px floor; task 2 set 13px) |
| `--tracking-label` | `0.12em` | Their letter spacing |
| `--tracking-nav-heading` | `0.16em` | Nav group headings: Cinzel 700, `--size-label`, uppercase, `--muted` (mockup) |
| `--size-wordmark` | `22px` | Header wordmark "Legend of the Green Dragon", Cinzel 700, `--text` (mockup) |
| `--tracking-wordmark` | `0.06em` | Its letter spacing (mockup) |
| `--size-wordmark-sub` | `13px` | "RESURRECTION" under it, Cinzel 500, uppercase, `--accent` (mockup) |
| `--tracking-wordmark-sub` | `0.2em` | Its letter spacing (mockup) |
| `--size-panel-name` | `20px` | The character's name in the character panel, Cinzel 700, `--text` (mockup, task 7) |
| `--size-panel-sub` | `14px` | The line under it, "Level 7 Elf · Mystical Powers", `--muted` (mockup, task 7) |
| `--size-tile-value` | `18px` | A stat tile's value, Alegreya Sans 700, `--text` (mockup, task 7) |
| `--size-phone-name` | `17px` | The character's name in the phone header, Cinzel 700, one line (mockup, task 8) |

## Color codes (approved values, plus the rest set here)

The LoGD color codes keep their characters and meanings. `appoencode()` (`lib/output.php`) still turns each into a
`<span class='…'>`; only the CSS values change. The new template is a standards-mode page (`<!DOCTYPE html>`), where
class selectors are case-sensitive: the CSS must use the exact class names `appoencode()` writes (for example
`colBlueViolet`, which several 1.1.2 skins spelled `colblueviolet` and only matched in quirks mode).

Every value clears 4.5:1 on `--panel` and on `--raised`.

**The quieter palette** (Jim, 2026-10-30, Phase 5 task 4): same codes, same meanings, lower saturation, pulled toward
parchment. Jim approved the 17 values marked "approved (Jim, task 4)". The other 24 codes were retuned by the same rule
(`PHASE5-TASK4-HEADER-FOOTER-20261030.md`, "The palette"): saturation scaled by 0.5 for cool hues and 0.8 for warm
ones (red to yellow), lightness up 0.02, hue nudged a few degrees toward parchment, then adjusted where needed so each
code stays distinct from every other (CIE76 distance 5 or more; only Jim's `` `4 `` and `` `$ `` pair sits closer, at
4.66). `tests/tooling/test_ui_tokens.py` pins Jim's values and checks the rule, the distances and the contrast.

| Code | Class | Value | On panel | On raised | Source |
|---|---|---|---|---|---|
| `` `1 `` | `colDkBlue` | `#8FA3C4` | 6.91 | 6.33 | approved (Jim, task 4) |
| `` `! `` | `colLtBlue` | `#A9B8D4` | 8.84 | 8.09 | approved (Jim, task 4) |
| `` `2 `` | `colDkGreen` | `#86A877` | 6.64 | 6.08 | approved (Jim, task 4) |
| `` `@ `` | `colLtGreen` | `#9CCB8A` | 9.54 | 8.73 | approved (Jim, task 4: unchanged) |
| `` `3 `` | `colDkCyan` | `#7FAAA3` | 6.89 | 6.31 | approved (Jim, task 4) |
| `` `# `` | `colLtCyan` | `#A3C4BE` | 9.43 | 8.64 | approved (Jim, task 4) |
| `` `4 `` | `colDkRed` | `#D9775E` | 5.69 | 5.21 | approved (Jim, task 4) |
| `` `$ `` | `colLtRed` | `#E8826A` | 6.60 | 6.04 | approved (Jim, task 4: unchanged) |
| `` `5 `` | `colDkMagenta` | `#B49AB8` | 6.95 | 6.37 | approved (Jim, task 4) |
| `` `% `` | `colLtMagenta` | `#CDB0C2` | 8.91 | 8.16 | approved (Jim, task 4) |
| `` `6 `` | `colDkYellow` | `#C9B47A` | 8.67 | 7.94 | approved (Jim, task 4) |
| `` `^ `` | `colLtYellow` | `#E3CF9A` | 11.50 | 10.53 | approved (Jim, task 4) |
| `` `7 `` | `colDkWhite` | `#C9C0AE` | 9.80 | 8.97 | approved (Jim, task 4) |
| `` `& `` | `colLtWhite` | `#F4EEE2` | 15.31 | 14.02 | approved (Jim, task 4: unchanged) |
| `` `) `` | `colLtBlack` | `#9A917F` | 5.67 | 5.19 | approved (Jim, task 4) |
| `` `q `` | `colDkOrange` | `#C4936A` | 6.51 | 5.96 | approved (Jim, task 4) |
| `` `Q `` | `colLtOrange` | `#DDA878` | 8.38 | 7.67 | approved (Jim, task 4) |
| `` `~ `` | `colBlack` | `#938E85` | 5.43 | 4.97 | set here (task 4: quieted by the rule). Black is unreadable on a dark panel, so a dark legible warm grey, darker than `` `7 `` and `` `) `` |
| `` `e `` | `colDkRust` | `#B7938C` | 6.37 | 5.84 | set here (task 4: quieted by the rule) |
| `` `E `` | `colLtRust` | `#D2B0A8` | 8.86 | 8.11 | set here (task 4: quieted by the rule) |
| `` `g `` | `colXLtGreen` | `#C4D8BC` | 11.70 | 10.72 | set here (task 4: quieted by the rule) |
| `` `G `` | `colXLtGreen` | `#C4D8BC` | 11.70 | 10.72 | set here (task 4: quieted by the rule; same class as `` `g ``, as in 1.1.2) |
| `` `j `` | `colMdGrey` | `#D9D4CC` | 11.99 | 10.98 | set here (task 4: quieted by the rule) |
| `` `J `` | `colMdBlue` | `#A4ADD0` | 7.97 | 7.30 | set here (task 4: quieted by the rule) |
| `` `k `` | `colaquamarine` | `#A8CFC0` | 10.40 | 9.53 | set here (task 4: quieted by the rule) |
| `` `K `` | `coldarkseagreen` | `#ABBAA9` | 8.70 | 7.97 | set here (task 4: quieted by the rule) |
| `` `l `` | `colDkLinkBlue` | `#8FABC0` | 7.37 | 6.75 | set here (task 4: quieted by the rule) |
| `` `L `` | `colLtLinkBlue` | `#A2C2D7` | 9.46 | 8.66 | set here (task 4: quieted by the rule) |
| `` `m `` | `colwheat` | `#E8DBC2` | 12.92 | 11.83 | set here (task 4: quieted by the rule) |
| `` `M `` | `coltan` | `#C6B49D` | 8.77 | 8.03 | set here (task 4: quieted by the rule) |
| `` `p `` | `collightsalmon` | `#E3B198` | 9.27 | 8.49 | set here (task 4: quieted by the rule) |
| `` `P `` | `colsalmon` | `#DD9E8E` | 7.88 | 7.22 | set here (task 4: quieted by the rule) |
| `` `R `` | `colRose` | `#E0CDDC` | 11.74 | 10.75 | set here (task 4: quieted by the rule) |
| `` `T `` | `colDkBrown` | `#A69178` | 5.85 | 5.35 | set here (task 4: quieted by the rule) |
| `` `t `` | `colLtBrown` | `#DDCAA2` | 10.99 | 10.06 | set here (task 4: quieted by the rule) |
| `` `V `` | `colBlueViolet` | `#B9A8D3` | 8.09 | 7.41 | set here (task 4: quieted by the rule) |
| `` `v `` | `coliceviolet` | `#C7CCE0` | 11.07 | 10.14 | set here (task 4: quieted by the rule) |
| `` `x `` | `colburlywood` | `#D5BD9E` | 9.77 | 8.95 | set here (task 4: quieted by the rule) |
| `` `X `` | `colbeige` | `#ECE8D3` | 14.36 | 13.15 | set here (task 4: quieted by the rule) |
| `` `y `` | `colkhaki` | `#E1D7A9` | 12.21 | 11.18 | set here (task 4: quieted by the rule) |
| `` `Y `` | `coldarkkhaki` | `#B9B182` | 8.15 | 7.46 | set here (task 4: quieted by the rule) |

That is every color code `appoencode()` knows (41 characters, 40 classes). Its other codes are not colors and keep
their meaning: `` `n `` line break, `` `0 `` end color, `` `b `` bold, `` `i `` italic, `` `h `` emphasis, `` `c ``
centered block, `` `> `` and `` `< `` floated blocks, `` `w `` the player's weapon name, a doubled backtick prints one backtick, and
`` `H `` the hotkey highlight, which the new UI draws as a key badge (below).

## Layout (approved)

**Header** (`--header`, 1px `--border` bottom, padding `--space-header-y` by `--space-side`): the wordmark at the left
("Legend of the Green Dragon" over "RESURRECTION"); at the right the mail, MoTD and petition controls (see Components,
"Header controls").

**Frame** (mockup, task 3): the page is `--ground` with `--text` in Alegreya Sans `--size-ui`. The columns sit in a
container at most `--width-container` wide, centered, with `--space-side` padding (`--space-side-phone` on a phone)
and `--space-gutter` between columns. The content column holds the page title (Cinzel 700, `--size-title`, tracking
`--tracking-title`) and below it the page in a `--panel` card (1px `--border`, `--radius-panel`, padding
`--space-card-y` by `--space-card-x`, `--space-card-phone` on a phone), set as story text (Alegreya `--size-body`,
`--leading-body`). Nav rows are 44px high with `--radius-control` and `--text` (see Components, "Navigation"). The character
panel's sections sit in `--panel` cards with `--space-panel` padding. Links are `--accent`, hover `--accent-hover`.

**Desktop**, three columns:

| Column | Width | Holds |
|---|---|---|
| Nav, left | 220 to 280px (`flex: 1 1 220px`) | Nav groups |
| Content, center | the rest (`flex: 999 1 560px`, `min-width: 0`) | The page title, then the content card with the scene banner across its top |
| Character, right | 260 to 320px (`flex: 1 1 260px`) | Portrait, HP and XP bars, stat tiles, equipment, buffs |

The columns wrap when the window is too narrow for all three, and below `--breakpoint-stack` each takes the full
width, with no sideways scroll.

**Phone** (set here: below 900px the columns stack; below 600px the phone chrome takes over; Phase 5 task 8): the
columns stack content first: the page title, scene, story and forms, then the page's navs, then the character panel,
then the More section, then the footer. The order is the stylesheet's (`order` below `--breakpoint-stack`); the page's
source keeps the desktop order, nav column first, with the skip link. Below `--breakpoint-phone` the signed-in header
is the compact phone header, the navs are tiles, and a bottom tab bar (`--header`) has up to four tabs: Go, Character,
Talk, More (Jim, 2026-10-31: in-page anchors, never links to other pages; see Components, "Tab bar"). The mockup's
"Village" tab is not used: a fixed link to the village would break forced navigation in a fight, a shop or the
graveyard. Between `--breakpoint-phone` and `--breakpoint-stack` (a tablet) the same stacking order with the desktop
header, nav rows and no tab bar. The page never scrolls sideways at 320px or wider; wide tables scroll inside their
own box.

**Footer** (`--header`, 1px `--border` top, `--muted` text at `--size-small`): at the left the original credit line (the copyright line and license block the template's
`{copyright}` tag carries, unaltered), the Credits link (`{credits}`, see Components), a Source link (the `sourceurl`
setting); at the right the version (`Resurrection\Version::display()`), the time to the next new day (`{newday}`,
task 4) and the page generation time; under them, on its own line, the attribution line (see Components).

Sizes, as tokens (column widths approved; the rest set here). CSS custom properties cannot be used inside a media
query, so the template writes the two breakpoints as literals equal to these tokens:

| Token | Value | Use |
|---|---|---|
| `--space-unit` | `4px` | Base spacing unit |
| `--width-container` | `1440px` | The frame's widest content, centered (mockup) |
| `--space-side` | `32px` | Side padding of the frame, the header and the footer (mockup) |
| `--space-side-phone` | `16px` | Side padding on a phone |
| `--space-gutter` | `24px` | Gutter between columns (mockup; was 16px) |
| `--space-header-y` | `14px` | Header bar padding, top and bottom (mockup) |
| `--space-card-y` | `32px` | Content card padding, top and bottom (mockup) |
| `--space-card-x` | `36px` | Content card padding, left and right (mockup) |
| `--space-card-phone` | `20px` | Content card padding on a phone (mockup) |
| `--space-panel` | `20px` | Character and nav panel padding (mockup; was 16px) |
| `--space-panel-phone` | `12px` | Panel padding on a phone |
| `--radius-panel` | `12px` | Panels and cards (mockup; was 6px) |
| `--radius-control` | `8px` | Buttons, inputs, nav rows, header controls (mockup; was 4px) |
| `--radius-badge` | `5px` | Key badges (mockup; was 3px) |
| `--radius-bar` | `999px` | Bars, fully rounded (mockup) |
| `--width-nav-min` | `220px` | Nav column, narrowest |
| `--width-nav-max` | `280px` | Nav column, widest |
| `--width-character-min` | `260px` | Character column, narrowest |
| `--width-character-max` | `320px` | Character column, widest |
| `--breakpoint-stack` | `900px` | Below it the columns stack |
| `--breakpoint-phone` | `600px` | Below it the phone chrome takes over |
| `--touch-target` | `44px` | Smallest touch target, both directions |
| `--bar-height` | `8px` | HP and XP bars in the character panel (mockup; was 10px) |
| `--bar-height-fight` | `10px` | HP bars in a fight (mockup) |
| `--bar-height-compact` | `6px` | Bars in the phone's compact header |
| `--tab-height` | `52px` | A tab of the phone's bottom tab bar, the touch target's height (mockup, task 8) |
| `--nav-tile-height` | `48px` | A nav tile on a phone, at least (mockup, task 8) |
| `--key-badge-size` | `24px` | Key badge, a square (mockup; was 22px) |
| `--icon-size` | `24px` | Icon grid |
| `--icon-size-label` | `16px` | An icon beside a label in the character panel and the phone header (task 13) |
| `--scene-height` | `240px` | Scene banner height (Jim, task 5) |
| `--scene-height-phone` | `150px` | Scene banner height on a phone (Jim, task 5) |
| `--portrait-width` | `64px` | Race portrait in the character panel (Jim, task 5) |
| `--portrait-width-phone` | `44px` | Race portrait in the phone header (Jim, task 5) |
| `--portrait-width-foe` | `80px` | A foe's portrait on its fighter card, every width (mockup, task 14) |
| `--icon-stroke` | `1.8` | Icon stroke width |
| `--focus-ring-width` | `2px` | Focus ring |
| `--focus-ring-offset` | `2px` | Its offset |

## Components (approved)

- **Header controls** (Phase 5 task 4). Mail, MoTD and petition are real links (`<a href>` to `mail.php`, `motd.php`,
  `petition.php`, `target="_blank"`), written by `maillink()`, `motdlink()` and `petitionlink()` in
  `lib/pageparts.php`. Each is a 44px outlined control on the header fill: a line icon (24px grid, 1.8 stroke,
  `currentColor`; an envelope, a scroll, a question mark in a circle), its label, and a badge. Mail's badge is the
  unread count from `maillink_counts()` (none at zero); its `aria-label` and `title` are the full sentence ("Ye Olde
  Mail: 2 new, 1 old"). An unread MoTD item gives the MoTD control a dot badge and the `aria-label` "MoTD (unread)";
  the MoTD is never opened automatically. Highlighted controls (`hotmotd`) take an `--accent` outline and label. The
  badge is `--accent` with `--ground` text, `--size-small` bold, fully rounded. On a desktop with a mouse a click opens
  1.1.2's popup window instead (550x300); anywhere else the link opens as a page in a new tab. Below
  `--breakpoint-phone` the labels are visually hidden (they stay as the accessible names) and the three controls are
  44px squares on one row, each badge on its corner.
- **Footer Credits link** (task 4). `{credits}`: a link to `about.php` only where following it is allowed: always for a
  visitor who is not signed in, and for a signed-in player only on a page that allows `about.php` as a nav (home,
  news), using that exact nav. Elsewhere (the village, a fight) it is left out, never a bad nav. The 1.1.2 credit line
  (`{copyright}`) is on every page regardless.
- **Next new day** (task 4). `{newday}`: "Next new day in 3h 13m", from `secondstonextgameday()`, plain text.
- **Attribution line** (Jim, 2026-10-30, task 4). "A Phoenix 2:33 LLC Project by Jim Lunsford", with "Phoenix 2:33 LLC"
  linking to https://phoenix233.com and "Jim Lunsford" to https://jimlunsford.com (plain links, `target="_blank"
  rel="noopener"`, nothing requested until clicked). Its own line (`<p class="rz-attribution">`) in the template's
  `footer` and `popupfoot`, after the 1.1.2 credit line and outside its element, so it never reads as authorship of the
  1.1.2 code; the same `--muted` color and `--size-small` size as that line. `CLAUDE.md`: no task removes, hides,
  shrinks or weakens it.
- **Character panel values** (Jim, 2026-10-30, task 4). Values are `--text`, bold, by default, not gold:
  `getcharstats()` wraps each value in `` `^ `` (`colLtYellow`), and the template's CSS, scoped to the character panel
  (`.rz-statvalue`, `.rz-statbuff`), reads that wrapper as `--text`. The bonus (`` `@ ``) and penalty (`` `$ ``) codes
  `charstats()` uses, and any other code in a value, keep their own colors. No PHP change.

- **Navigation** (Phase 5 task 6). The nav column is a `--panel` card (`navstart` and `navend` parts around `{navad}{nav}`)
  holding the nav groups. Each group has an uppercase Cinzel heading (`addnavheader()`, `--muted`, `--size-label`,
  `--tracking-nav-heading`); help text (`addnav("text", "")`) is `--muted` at `--size-small`.
  - **Rows.** Each nav is a real link, a row at least `--touch-target` (44px) tall and the full width of the card,
    `--radius-control` (8px), `--text`: the key badge, a 10px gap, then the label, which wraps. Hover and keyboard
    focus darken the row to `--ground`; focus also keeps the focus ring. Never `--raised`: the badge outline is
    3.26:1 against `--ground` and 3.04:1 against `--panel`, but would be 2.78:1 against `--raised`. The key on the
    darkened row is 8.74:1 and the label 15.03:1.
  - **Key badge.** A `--key-badge-size` (24px) square, `--radius-badge` (5px), 1px `--input-border` outline, no fill of
    its own (the nav's background shows through), the key in `--accent`, Alegreya Sans 700, `--size-small` (13px),
    shown uppercase. The badge is `aria-hidden`; the link carries `accesskey` and `aria-keyshortcuts` with the same key,
    so its accessible name is the label alone. A nav without a key keeps an empty badge space with no outline, so the
    labels line up. The label shows plain: no in-label highlight and no "(X) " prefix. `private_addnav()` decides
    the key exactly as 1.1.2 did and passes it to the `navitem` part as `{key}`; a skin whose `navitem` part has no
    `{key}` gets 1.1.2's in-label marking.
  - **Hotkeys.** The page carries its key map as JSON data (`<script type="application/json" id="rz-keys">`) and loads
    the static `assets/ui/keys.js`: a key goes where its nav goes (a popup nav opens its popup window), except while
    typing in an input or a textarea, or with Alt or Ctrl held. No inline script. Every nav works without JavaScript.
  - **No nav, no column.** A page with no nav item, heading or help text, and nothing in `{navad}` (a forest fight,
    whose actions are forms), has no nav column at all; the content takes its space. No empty card.
- **Phone header** (Phase 5 task 8). Below `--breakpoint-phone`, signed in, the header bar holds the `phonehead` part
  in place of the wordmark row (the stylesheet hides that row when the part is there): the race portrait
  (`phoneportrait` part, `--portrait-width-phone`, 44px), then the character's name (Cinzel 700, `--size-phone-name`
  17px, `--text`, one line, cut with an ellipsis) over the subtitle "Level 7 Elf · 14 turns · 1,247 gold" (`--muted`,
  `--size-small` 13px; the race as the panel names it), then the mail control with its badge (44px, the header's own
  `maillink()`). Under them, the HP and XP bars side by side (`phonebar` part): `--bar-height-compact` (6px) tracks,
  the label left (`--muted`) and the numbers right (`--text`, bold), `--size-small`. The numbers and fills are the
  panel's: hitpoints against the maximum, experience against `exp_for_next_level()`, at the level cap the experience
  alone over a full bar. Dead, the subtitle reads "Level 7 Elf · 6 torments · 40 favor" and the first bar is Soul,
  soulpoints against level × 5 + 50. Each bar's label has the panel's icon for it before it (task 13: hitpoints,
  soulpoints when dead, experience; 16px, decorative). Signed out (and in popups) the header keeps the wordmark, on one line, its size
  shrunk to fit the screen (at most `--size-wordmark`), with the MoTD and Petition controls below it at the right.
  New translated texts: "%s turns", "%s gold", "%s torments", "%s favor", "HP", "Soul", "XP".
- **Nav tiles** (task 8). Below `--breakpoint-phone` the nav groups lose their card and each nav is a tile in a
  two-column grid, 8px apart: at least `--nav-tile-height` (48px) tall, `--panel` fill, 1px `--border`,
  `--radius-control` (8px), the label wrapping, no key badge (no keyboard). Group headings and help text span both
  columns. The label keeps the nav row's size.
- **More section** (task 8). After the character panel, a `--panel` card (`<section id="rz-more">`, heading "More")
  holding the MoTD and Petition for Help controls, labelled, and the Credits link where the footer's rule allows it
  (task 4: never a bad nav), each a 44px control. Hidden above `--breakpoint-phone` (the header and footer hold them
  there). Not in popups.
- **Tab bar** (Jim, 2026-10-31, task 8). Below `--breakpoint-phone`, a bar fixed to the bottom of the window: `--header`
  fill, 1px `--border` top, the safe area (`env(safe-area-inset-bottom)`) added below it. Its tabs share the width
  equally, each `--tab-height` (52px) tall, the label `--size-small` (13px, the floor: the mockup's 12px would break it)
  Alegreya Sans 700, `--muted`; the tab whose section is in view is `--accent` (`rz-tab-current`, `aria-current`,
  marked by the static `assets/ui/tabs.js`; every tab works without it). The tabs are anchors on the page, so forced
  navigation is never broken:
  - **Go**: the page's navs (`#rz-nav`), only when the page shows the nav column (not in a forest fight).
  - **Character**: the character panel (`#rz-character`), only signed in.
  - **Talk**: the page's first commentary (`#rz-talk`, an empty anchor `viewcommentary()` prints before it), only when
    the page rendered commentary.
  - **More**: the More section (`#rz-more`), always.

  The page is padded at the bottom by the bar's height, its 1px border and the safe area, so the footer is never under
  the bar. No tab bar in popups, on operator pages (not installed, database error, upgrade needed) or in a 1.1.2 skin.
- **Buttons** (Phase 5 task 9). Two kinds, both `--touch-target` (44px) tall, 16px padding each side,
  `--radius-control` (8px), Alegreya Sans `--size-ui`, the focus ring:
  - **Primary**: `--accent` fill and border, `--ground` label, weight 700; hover `--accent-hover` (`rz-button-primary`,
    from `resurrection_button_class(true)` in `lib/output.php`). **At most one per form.**
  - **Secondary**, every other button: no fill, 1px `--input-border` outline, `--text` label, weight 500; hover
    `--accent` outline and `--accent-hover` label. A destructive action (preferences' Delete Character) is secondary
    with a `--damage` outline and label (`rz-button-danger`).

  Buttons side by side line up in a row 8px apart and wrap. No full-width buttons. A button drawn as a link (the
  editors' delete, `rz-link-button`) keeps the link's look. In a 1.1.2 skin every button's markup is 1.1.2's, byte for
  byte, until task 15 removes the skins.
- **Primary actions** (task 9). The form's primary button is: log in; create a character; the bank's deposit, borrow
  and withdraw; the inn's room; the healer's heal (each potion's form); commentary Add; preferences Save; mail Send;
  in a fight, Fight (task 10). Every other button is secondary. Task 9 also made each weapon and armor shop item a
  primary; task 10 (Jim's review of task 9) made them links (**Buy lists**, below).
- **Inputs** (task 9). Text, password, number and email inputs and selects are `--touch-target` (44px) tall, a
  textarea at least 88px; `--panel` fill (never `--raised`), 1px `--input-border` outline (3.04:1 on `--panel`, 3.16:1
  on `--header`), `--radius-control`, `--text`, the focus ring. Placeholder text is `--muted` (6.76:1 on `--panel`).
  Checkboxes and radios are 20px, in `--accent`, inside their label, and the label is the 44px tap target.
  - **Labels.** Every control a player fills in has a real `<label for>` or sits inside its `<label>`. `showform()`
    gives each control an id (`showform_control_id()`) and its row's label a `for`; a group of choices (checklist,
    radio, bitfield) labels each choice. A label never straddles a color span: the code closes the color before the
    label and re-opens it after.
- **Tabs** (task 9). `showform()`'s sections, in the Resurrection template, are a tab list drawn by the server: one
  `<button role="tab">` per section, 44px, secondary, the chosen one `--accent` filled with `--ground` text and
  `aria-selected="true"` (the others `false` and `tabindex="-1"`), each `aria-controls` its section's table
  (`role="tabpanel"`). The static `assets/ui/showform.js` shows the list, shows only the chosen section, and moves
  between tabs with a click or Left, Right, Home and End. Without the script the list stays hidden and every section
  stays shown. The tabs wrap; no inline event handler or inline script. A player who turned tabs off (`tabconfig`)
  gets every section shown, as in 1.1.2.
- **Tables** (task 9). A table scrolls sideways inside its own box (`overflow-x: auto`) and never makes the page
  scroll; a table with a heading row, or a grid table, has a 1px `--border` edge and `--radius-control`, so the box's
  end shows. The heading row (`trhead`) is `--raised` with `--muted` labels, `--size-label` (13px), uppercase,
  `--tracking-label`, weight 700. Body rows are a quiet zebra: `trlight` `--panel`, `trdark` `--header`; text on both
  clears 4.5:1 (`--text` 13.99:1 and 14.53:1). The highlighted row (`trhilight`, the player's own) is `--panel` with a
  3px `--accent` marker on its left. Cells keep the alignment the page prints (`align='right'` number columns stay
  right). On a phone a `showform()` form stacks: each label above its control, the control full width.
- **Presentation as classes** (task 9). What 1.1.2 printed inline is a class in the Resurrection template: `` `c `` is
  `rz-center`, `` `> `` and `` `< `` are `rz-float-right` and `rz-float-left`, grid tables are `rz-grid` (1px
  `--border` lines), inline forms `rz-inline`, larger text `rz-larger` (1.25em), typed text `rz-typed`, nested lists
  `rz-indent`, the narrators' colors the color code classes (`colBlueViolet`, `colLtBlack`, `colLtWhite`,
  `colLtYellow`). `resurrection_presentation()` in `lib/output.php` is the one map from each to 1.1.2's markup, which a
  1.1.2 skin keeps byte for byte.
- **Character panel** (Phase 5 task 7). `charstats()` fills the rows as 1.1.2 did; a template with the panel parts
  (`statheader`, `statportrait`, `statbar`, `statbarcap`, `stattiles`, `stattile`, `equiprows`, `equiprow`) renders them
  through `resurrection_charpanel()`, a 1.1.2 skin through `getcharstats()`'s rows, unchanged. From the top, one
  `--panel` card (`--space-panel` padding, 16px between its parts):
  1. **Header.** The race portrait (`--portrait-width`, 64px; `alt` the race as the subtitle names it), beside the
     character's name (Cinzel 700, `--size-panel-name` 20px, `--text`, its color codes kept) and the subtitle
     "Level 7 Elf · Mystical Powers": level, race, then " · " and the specialty's name from the `specialtynames` hook
     when the player has one (`--muted`, `--size-panel-sub` 14px). A race not chosen yet (`''`, `0` or `RACE_UNKNOWN`)
     reads "Not chosen yet", with the unknown portrait.
  2. **Bars**, below.
  3. **Stat tiles**, below: two columns, 8px apart. Alive: Turns, Spirits, Attack, Defense, Gold, Gems.
  4. **Equipment rows**, below: Weapon, Armor, and Mount when the player has one, then any row a module added to
     "Equipment Info".

  Then, each in its own card as the 1.1.2 sections were (`stathead`, `statrow`, `statbuff`): Buffs, Companions (a row
  each, as 1.1.2 wrote them), then every row a module added to another section, or a new section, as label/value rows
  under that section's title, translated as `getcharstats()` translates them. A standard row a module changed in the
  `charstats` hook shows the module's value in its component (the bar keeps its fill); the components otherwise read
  the account. Below `--breakpoint-stack` the panel stacks after the content and the navs; below `--breakpoint-phone`
  its portrait is hidden (the phone header shows it) and its padding is `--space-panel-phone`.
  - **The dead.** Soulpoints replace hitpoints in the first bar (maximum level × 5 + 50, as 1.1.2), and the tiles are
    Torments, Spirits ("DEAD"), Psyche, Spirit, Favor and Gems: 1.1.2's meanings in the same components.
  - **Icons** (Phase 5 task 13). A 16px icon (`--icon-size-label`) before each standard label, 6px from it and
    centered on its line (`rz-labelicon`), through the template's `labelicon` part and `resurrection_icon_html()`. A
    stat tile's label is one uppercase word, and TORMENTS with its icon needs 108px: in a tile whose content is
    narrower than 110px (the desktop's character column at its narrowest) every tile's icon stands on its own line
    above the label, 4px over it (a container query on the tile), and in a wider tile it sits before the label. Each
    is decorative: `<img alt="" aria-hidden="true">` with `width` and `height` 16, since the label beside it carries
    the meaning (and is translated as before). The icon is chosen by the label's English text, color codes dropped,
    lowercased (`Art::iconName()`), so a row a module adds has none unless its label is a standard stat's (a module
    row named "Gold" gets the gold icon; "Charm", "Clan" or "Ring" none). A missing file or a template without the part
    gives no image, never a broken one. The 1.1.2 skins show none.

    | Icon | Color | Where |
    |---|---|---|
    | `turns` | `#D9A84E` | Turns tile (alive) |
    | `torments` | `#E8826A` | Torments tile (dead) |
    | `spirits` | `#E3CF9A` | Spirits tile (alive and dead) |
    | `attack` | `#E8826A` | Attack tile (alive) |
    | `defense` | `#A99F8D` | Defense tile (alive) |
    | `psyche` | `#8FB8D9` | Psyche tile (dead) |
    | `spirit` | `#ECE4D4` | Spirit tile (dead) |
    | `gold` | `#D9A84E` | Gold tile (alive) |
    | `favor` | `#D9A84E` | Favor tile (dead) |
    | `gems` | `#8FB8D9` | Gems tile (alive and dead) |
    | `hitpoints` | `#7FB069` | Hitpoints bar (alive); the phone header's HP bar |
    | `soulpoints` | `#ECE4D4` | Soulpoints bar (dead); the phone header's Soul bar |
    | `experience` | `#D9A84E` | Experience bar; the phone header's XP bar |
    | `weapon` | `#C4936A` | Weapon row |
    | `armor` | `#A99F8D` | Armor row |
    | `mount` | `#C08650` | Mount row (with a mount) |
    | `buffs` | `#8FB8D9` | Buffs heading |
    | `companions` | `#7FB069` | Companions heading (with companions) |
- **HP and XP bars.** Fully rounded (`--radius-bar`), `--bar-height` in the character panel and `--bar-height-fight` in
  a fight. `--track` behind, the fill in `--health` for the player and `--foe` for enemies; the two fills
  also differ in lightness (relative luminance 0.366 and 0.193), so they read apart without color. XP uses `--accent`.
  Each bar shows its numbers as text (for example "34 / 50"), so a bar never carries meaning by color alone.
  - In the character panel (task 7) each bar has its label left (`--muted`) and "current / max" right (`--text`,
    Alegreya Sans 700, lining figures) above the track, 6px apart. Hitpoints (soulpoints when dead) against the
    maximum; experience against `exp_for_next_level()` for the player's level and dragon kills. The numbers are the
    stored ones (hitpoints rounded, as `charstats()` rounds them); the fill is their ratio, one decimal, never below 0%
    and never above 100%: hitpoints above the maximum (buffs, healing) and experience past the next level (before
    training) print as they are over a full bar. A temporary stat's note (`check_temp_stat()`) follows the numbers in
    its codes' colors.
  - **The level cap.** At level 15 no level follows, so the experience bar is full and shows the experience alone, with
    a caption under the track (`--muted`, `--size-small`): "Seek Out the Green Dragon" (the forest's nav) while the
    player is alive and has not sought the dragon today, otherwise "None in the land are mightier than you!" (what
    `train.php` says on reaching level 15). 1.1.2 showed only the experience number, at every level.
- **Stat tiles.** A `--raised` tile with an uppercase Cinzel label (`--muted`) over the value (Alegreya Sans 700).
  In the character panel (task 7): `--radius-control` (8px), 10px by 12px padding; the label Cinzel 500,
  `--size-label` (13px, the floor: the mockup's 12px would break it), `--tracking-label`, uppercase; the value
  `--size-tile-value` (18px), `--text`, lining figures. Attack and defense show the stored value with the buffs'
  difference beside it in the bonus (`` `@ ``) or penalty (`` `$ ``) color, and a temporary stat's note in its colors,
  as `charstats()` writes them. Spirits is a word ("Normal", "DEAD").
- **Equipment rows.** An icon, the item name, and its value (attack or defense), one row each: weapon, armor, mount.
  Task 7: the label left (`--muted`), the item's name right (`--text`, bold, its color codes kept), 8px padding, a 1px
  `--track` line between rows. Task 13: the icon before the label (Character panel, Icons).
- **Fighter cards** (Phase 5 task 10, `lib/battle_view.php`). At the top of every fight that runs `battle.php` (the
  forest, PvP, graveyard torments, training masters, the Green Dragon, module fights), a grid of cards, 16px apart,
  each at least 220px wide (one column on a phone): the player first, then each foe in the fight's order. A card is
  `--header` with a 1px `--border` edge, `--radius-control`, 16px padding. The name is Cinzel 700 `--size-heading`
  (`--text` for the player, `--damage` for a foe), plain text (color codes dropped); under it, `--muted`
  `--size-small`, "Level N" (`battle` namespace, "Level %s"), and for a foe " · " and the weapon in italics (`--text`).
  Then the hitpoints: the label left (`--muted`; "HP", or "Soul" for a dead player and their torment, `common`) and
  "current / max" right (`--text`, 700, lining figures), over a `--bar-height-fight` (10px) `--track` bar filled
  `--health` (the player) or `--foe` (a foe); the fill is current ÷ max, one decimal, held between 0% and 100%. A foe's
  card has a 1px `--foe` border.
  - **The foe's maximum.** Recorded when the fight starts as `creaturemaxhealth` (forest and Green Dragon, since task
    10), or the route's own record (`trainingmaxhp` for a master, `pvpmaxhp` for a PvP victim), or for a graveyard
    torment the value 1.1.2 always gave it (level × 5 + 50). When none is known (a fight stored before task 10, a foe
    a script added during the fight), the card shows the number with no bar (`rz-fighterbar-nobar`). A module's
    `hidehitpoints` shows "???" with no bar.
  - **The fallen and the target.** A dead foe's card reads "0 / max · DEAD" ("DEFEATED" in the graveyard) in
    `--muted`, its bar empty. With more than one foe, the target's card has a 3px `--accent` marker on its left and a
    "Target" label (`battle` namespace) after its name.
  - **Foe portraits** (Phase 5 task 14). A foe's card opens with its portrait (the `fighterportrait` part, an
    `<img>`), `--portrait-width-foe` (80px) wide at every width, its height from the file's 200x240, beside the name
    and the level line, 14px apart, centered on them; the hitpoints follow under both. The alt text is the foe's name as
    the card shows it (color codes dropped, no "Target" mark). The player's card has no portrait (the character panel
    shows the race portrait). Which portrait: Art, "Foe portraits" below.
- **Battle action bar** (task 10). Under the cards: Fight (the one primary) and Run (secondary) in a row, 8px apart;
  then each group of secondary buttons under a small uppercase Cinzel heading in `--muted`: "Automatic Fighting" (5
  rounds, 10 rounds, until the end, where the settings offer them), "Special Abilities" (the specialty's skills, with
  "N uses left" under the heading, `battle` namespace "%s uses left"), and a superuser's actions. The headings are
  `fightnav()`'s, in its `fightnav` namespace. Each button is the route's own form, with the same name, field, action
  token and target as before; only its place and class are new. A fight that has no Run (training) shows Fight alone.
  A module fight that uses `fightnav()` keeps its links in the nav column and gets the cards, log and result.
- **Battle log** (task 10). Under the actions, after a 1px `--border` line: the lines `battle.php` prints before its
  first round ("You have encountered … which lunges at you with …!" first, then a surprise attack or a skill's text),
  each its own row; then an ordered list, one item per round fought in this request, with no label (Jim, task 10
  fix: no "Round N"), each line of the round in its own row, in the colors its codes give. A quiet divider, a 1px
  `--border` line with 10px above and below, separates one round from the next; the first round has none. Fight is
  one round, "For 5 Rounds" up to five items, drawn the same way. A round that printed nothing shows no item.
  1.1.2's "~ ~ ~ Fight ~ ~ ~" divider, "Next round:" and "End of Round:" (with its hitpoint list) are left out: the
  page title, the cards and the dividers say them. The list's name for assistive technology is "Battle log".
- **Battle result** (task 10). What the fight printed after its last round, when it ended: a `--header` box with a 1px
  `--border` edge and `--radius-control`, headed "Victory" (`--accent`, with a 3px `--accent` marker) or "Defeat"
  (`--damage`, with a `--damage` marker), each line a row: the creature's last words, "You have slain …", the gold,
  experience and gems, the flawless bonus, or the death text and what it cost. Lines printed after the rounds of a
  fight that goes on sit in the same box without a heading. What a route prints after the fight (the graveyard's page,
  the Dragon's epilogue) follows the battle screen as ordinary content. What a page printed before the fight ("You
  failed to flee") stays above the cards.
- **Commentary** (task 10, `lib/commentary.php`). The comments are a list (`rz-comments`), one item per comment with a
  1px `--border` line between: the moderator's Del button first when the viewer may delete, then the time stamp
  (when the player shows them), the new-comment marker, the author's name in Cinzel 700 in its colors with the clan
  tag before it, then "says," and the quoted text, or the emote (`:`, `::`, `/me`), exactly as 1.1.2 wrote them, in
  their colors. The `viewcommentary` module hook still gets and may change each line. The moderation screen (`moderate.php`, all
  sections) keeps 1.1.2's lines until task 11. The talk form: its label (the page's message, for example "Speak" in
  the village) on its own line, then the 44px input and Add (primary) in a row that wraps. 1.1.2's draft-saving script
  is gone (`CHANGES-FROM-1.1.2.md`).
- **Commentary pager** (task 10). "<< First Unseen", "< Previous", "Refresh", "Next >", "Last Page >>" as a row of
  44px secondary buttons, 8px apart, wrapping; each an `<a class="button">` with 1.1.2's link, key and target. A page
  that does not exist is the same label as a `<span>` with `aria-disabled="true"`, `--muted` with a `--border`
  outline. The row is a `<nav>` named "Comment pages". None is primary (Add is the page's primary).
- **Buy lists** (task 10, Jim's review of task 9). In the weapon and armor shops an item the player can afford is its
  buy form's button drawn as a link: `--accent` text, weight 700, no fill or outline (`rz-shop-link`); its tap area is
  the whole table row, at least `--touch-target` (44px) tall, and the row darkens to `--ground` on hover. The form,
  its action token and target are unchanged. An item the player cannot afford is plain text, as before. No primary on
  the page. The stables' and mercenary camp's lists, and the healer's potions, are navs or one form each, not buy
  tables, and are unchanged.

### Popups and staff screens (Phase 5 task 11)

The popups (mail, petition, MoTD, the translator tool) use the same frame, cards and buttons as every page, in one column.
The 1.1.2 credit line (`{copyright}`) and the attribution line stay in `popupfoot`. A 1.1.2 skin is unchanged.

### Tabs in popups

Mail's Inbox and Write are tabs (`nav.rz-mailtabs`, a `role="tablist"` list): real links (`mail.php`,
`mail.php?op=address`), each a 44px secondary tab button (`rz-tab-button`), the current one `--accent` filled with
`aria-selected="true"`, the other `false`. They wrap. Reading a letter keeps Inbox current; the address and write forms
keep Write current. They need no script: each tab is a page.

### The inbox

- A heading ("Mail Box", plain), then a list (`ul.rz-inbox`), unread first and then newest first, as 1.1.2 ordered it.
- Each row: a labelled checkbox in a 44px square (its label, for screen readers only, is "Select: " and the subject);
  then the subject as the row's link (`--text`, weight 500, bold while unread); then the sender and the date on a line
  under it in `--muted`, `--size-small`, a middle dot between. An unread row has a small New badge before the subject:
  20px tall, transparent, a 1px `--accent` outline and `--accent` text. The New and Old scroll GIFs are gone.
- Check All (`data-rz-checkall="msg[]"`, `assets/ui/showform.js`) and Delete Checked are secondary buttons in a row;
  the inbox has no primary action. The form, its fields and its target are 1.1.2's.
- The note under the list (how many letters, the inbox limit, the days they keep) is a plain `--muted` paragraph,
  `--size-small`; an empty inbox says so in `--muted`. Nothing in the popup is italic.
- A letter: its subject as the heading (with New while unread), a `--muted` line with "From:" and the sender, "Sent:" and
  the date, the body between two 1px `--border` lines, then the actions: Reply (the one primary, only on a player's
  letter), Delete and Mark Unread (secondary forms), Report to Admin (secondary, not on the game's own letters), then
  Previous and Next as secondary buttons (an end with no letter is the same label marked unavailable).
- The write form keeps task 9's labels; Send is its one primary.

### MoTD items

- Each item is an `article.rz-motd`, a 1px `--border` line under each but the last. Its title in Cinzel
  (`--font-display`, `--size-heading`); for staff, Edit and Del as secondary buttons (Del asks first, `data-rz-confirm`);
  the author and the date on a `--muted` line, `--size-small`, a middle dot between; the body in Alegreya
  (`--font-story`), its line breaks kept.
- A poll is the same item with "Poll:" in `--accent` before its title. Its question is the legend of a fieldset; each
  choice is a radio with its label (the choice and its count, as 1.1.2 wrote them), at least 44px tall, and a bar
  under it: a 6px `progress`, `--accent` on `--raised`, fully rounded, its share of 400 as 1.1.2's image was (no
  `rule.gif`). Vote is the poll's one primary. At most 480px wide.
- Opening the MoTD clears its unread mark as 1.1.2 did (the account's `lastmotd` becomes the newest item's date).
- The petition popup is a labelled form with Submit as its one primary; its FAQ pages are plain readable text.
- The translator tool (staff with the translator's flag only) has visible labels for its namespace, text and
  translation; Save & Close is its one primary, Save No Close secondary.

### Admin tables

- Every admin and editor table scrolls sideways in its own box (task 9's `overflow-x: auto`); the box also holds
  anything positioned inside it (`position: relative`), so a screen-reader-only label never widens the page. No admin
  page scrolls sideways on a 390px phone.
- `showform()` forms (the creature, user, mount and other editors) use task 9's tabs. The settings editors drawn by
  `lib/typed_editor.php` (Game Settings, `configuration.php`) use the same tabs, one panel a section, each panel headed by
  its section's name, each setting's label above its control; their tab list carries `data-rz-tabs-noindex`, so the
  script adds no `showFormTabIndex` field (those forms take only the settings they declare). Save stays secondary.
- Every admin and editor control has a label: visible where the page prints label text, for screen readers only where
  it prints none (a row's checkbox, the second box of a pair). The helpers in `lib/output.php`
  (`resurrection_control_id()`, `resurrection_label_open()`, `resurrection_label_close()`, `resurrection_label_for()`)
  print nothing in a 1.1.2 skin.
- The moderation page (`moderate.php`) uses the commentary list (task 10) with each comment's checkbox labelled ("Select
  this comment") and its Ban link and section links as before.

### Petition badges

In the header's staff row (`{petitiondisplay}`), the User Editor and Petitions links, then one compact badge a petition
status, in 1.1.2's order: Top Level, Escalated, Unhandled, In-Progress, Informational, Awaiting Points, Bug, Closed. Each
badge shows the count; its name is in its `title` and, for screen readers, before the count. Unhandled (unread) is an
`--accent` badge with `--ground` text; the others are transparent with a 1px `--input-border` outline and `--text`
count. The row wraps and stays in the line on a phone; on a game page with the phone header (task 8) the staff row is
not shown there, as before.

## Art (approved)

- **Scene banners** across the top of the page's content card: woodcut style, layered dark silhouettes, light from
  windows, the moon, eyes and gems; art palette colors only (below).
- **Portraits** in an arched frame tinted by race (players) or creature type (creatures; Phase 5 task 14, "Foe
  portraits" below).
- **Icons**: line icons on a 24px grid, 1.8 stroke, colored by meaning (health green, damage, magic, gold accent).
  The character panel's 18 (`assets/icons/`, Phase 5 task 13) are drawn at 16px beside their labels; the list and
  where each is used are under Character panel, Icons.
- **Every image slot has a fallback**: a page with no scene of its own shows the fallback scene, a race with no
  portrait of its own the unknown portrait, a foe with no portrait of its own its type's fallback or the unknown one, a
  missing icon nothing (the text label stays). A lookup never gives a
  broken URL: a missing file means no image.
- All art is original SVG drawn by Claude for Jim / Phoenix 2:33 LLC, in `assets/` under CC BY-SA 4.0
  (`assets/LICENSE`, scope `assets/scenes/`, `assets/portraits/` and `assets/icons/`; Phase 5 task 5). Credit: "Art by
  Phoenix 2:33 LLC, CC BY-SA 4.0".

### Image rules (Phase 5 task 5)

- **Scene banner.** An `<img>` (the template's `scene` part) at the top of the content card, edge to edge inside its
  rounded top corners, `--scene-height` (240px) tall on desktop and `--scene-height-phone` (150px) below
  `--breakpoint-phone`, a 1px `--border` line under it. The picture is cropped to fill and centered
  (`object-fit: cover`), never letterboxed. The `width` and `height` attributes are the file's own size, 1200x320.
- **Which scene.** `src/Ui/Art.php`, by page script: `home.php` and `village.php` the village, `forest.php` the forest
  (its fights included), each place's own page its place and a bundled module's page the place it happens in (task 12,
  "Location scenes" below), every other player page the fallback. No scene on popups, on superuser and editor pages (a
  page that called `check_su_access()`), or on the operator pages (not installed, database error, upgrade needed,
  game unavailable). A module names its own scene for its pages through the `scene` hook: it gets `script`, `scene`
  and `alt`, and may set `scene` to another file in `assets/scenes/` (with `alt`) or to `''` for none; a name with no
  file is ignored.
- **Race portrait.** An `<img>` (the template's `portrait` part) at the top of the character panel, in its own
  `--panel` card, `--portrait-width` (64px) wide, beside the player's name (Cinzel 700, `--text`) and race (`--muted`,
  `--size-small`). Since task 7 the panel's portrait is in its header card (the `statportrait` part; "Character
  panel" above), and the `portrait` part is the header's. Below `--breakpoint-phone` (task 5) it moved into the header
  beside the controls, `--portrait-width-phone` (44px) wide, the image only; since task 8 the signed-in phone header
  shows it through its own `phoneportrait` part, the same size, and the header's slot stays hidden on a phone. `width` and `height` attributes: the file's 200x240. Signed out: none. The portrait is
  the player's race lowercased (`human`, `elf`, `dwarf`, `troll`); any other race, or none, gets `unknown`.
- **Alt text** comes from the same data: a scene's is the place's name (the village's and the inn's names from their
  settings, the other places' names translated, and for the fallback the page's own title; a module's hook may give its
  own), a portrait's the race as the character panel
  shows it. Plain text, escaped; an image without alt text is not shown.
- **Files.** Plain SVG drawings: no script, no event attribute, no `foreignObject`, no `href` or other external
  reference, every color from the art palette, each under 16 KB. `tests/tooling/test_art.py` checks them.

### Foe portraits (Phase 5 task 14)

Batch 4, approved by Jim on 2026-11-05: 313 portraits, 200x240 like the race portraits, each an arched frame tinted
by creature type, drawn from the parts kit `assets/portraits/creature/kit.json` by
`scripts/art/build_creature_portraits.py` (`assets/README.md` has the kit, the generator and how to rebuild). The
battle screen's foe cards show them ("Fighter cards" above). `Art::foe()` picks one by the fight's type (battle.php's
`$options['type']`, as the route stored it):

| Fight | Portrait | File |
|---|---|---|
| `pvp` | the victim's race portrait, by the victim's race (task 5's lookup: human, elf, dwarf, troll, else unknown) | `portraits/race/<race>.svg` |
| `dragon` | the Green Dragon's | `portraits/creature/green-dragon.svg` |
| `train` | the master's, by its `creatureid` (the masters table's id, the masterid: 1 to 14 seeded) | `portraits/master/<masterid>.svg` |
| any other (`forest`, `graveyard`, a module's) | the creature's, by its `creatureid` (288 seeded, 1 to 320 with gaps) | `portraits/creature/<creatureid>.svg` |

The rules:

- **By id, not by name.** A creature or master has its own portrait when its id is one the kit drew: a seeded one.
  A seeded creature renamed in the editor keeps its id's portrait, and so does a forest foe whose name the fight
  prefixes ("Elite", "Savage" and the others). The masters and the creatures share ids (both tables start at 1), so
  the fight's type decides which folder: a training fight's foe is a master.
- **Fallbacks.** A foe with no portrait of its own (a creature added in the creature editor, a module's creature with
  no id or an id the kit does not have, a master added in the masters editor, the forest's "evil doppleganger" when no
  creature fits the level) gets the fallback of its creature type, `portraits/creature/fallback/<type>.svg`: humanoid,
  beast, undead, spirit, monster, demon, construct, plant, dragon, or unknown (the hooded traveller). The type comes
  from the `creatureportrait` module hook: it gets `creature` (the foe as the fight stores it), `fight` (the fight's
  type) and `type` (`unknown`), and may set `type` to one of the nine; anything else, or no module, is unknown. The hook
  is asked only for a foe with no portrait of its own, once per card.
- **Never a broken URL.** Every lookup returns a file that exists, or nothing; an empty alt text means no image. A
  missing fallback falls back to unknown.
- **Alt text** is the foe's name as the card shows it, plain text, escaped.
- **Old skins.** Only the Resurrection template has the slot; a 1.1.2 skin's battle pages are byte for byte as before.

### Location scenes (Phase 5 task 12)

Batch 2, approved by Jim on 2026-11-01: 17 scenes, one per place, 1200x320 like batch 1, in the art palette's colors.
With batch 1's village, forest and fallback, these are all the scenes (`assets/scenes/<scene>.svg`):

| Scene | Page | Alt text (the place's name) |
|---|---|---|
| `village` | `home.php`, `village.php` | the `villagename` setting (default "Degolburg") |
| `forest` | `forest.php`, its fights and forest specials | "The Forest" |
| `inn` | `inn.php` | the `innname` setting (default "The Boar's Head Inn") |
| `bank` | `bank.php` | "Ye Olde Bank" |
| `weapons` | `weapons.php` | "MightyE's Weaponry" |
| `armor` | `armor.php` | "Pegasus Armor" |
| `healer` | `healer.php` | "Healer's Hut" |
| `gardens` | `gardens.php` | "The Gardens" |
| `rock` | `rock.php` (the Veteran's Club too) | "Curious Looking Rock" |
| `train` | `train.php` | "Bluspring's Warrior Training" |
| `stables` | `stables.php` | "Merick's Stables" |
| `mercenarycamp` | `mercenarycamp.php` | "Mercenary Camp" |
| `gypsy` | `gypsy.php` (the seance too) | "Ze Gypsy Tent" |
| `lodge` | `lodge.php` | "JCP's Hunter Lodge" |
| `clan` | `clan.php` | "Clan Halls" |
| `graveyard` | `graveyard.php` | "The Graveyard" |
| `shades` | `shades.php` | "Land of the Shades" |
| `dragon` | `dragon.php` (the fight in the lair; the approach is `forest.php`, so the forest) | "The Green Dragon's Lair" |
| `fields` | `pvp.php` | "The Fields" |
| `fallback` | every other player page | the page's own title |

The rules:

- **By page script** (`Art::PLACES`). The names are the village's own: the inn and the village by the settings an
  administrator sets (a renamed inn is named anew everywhere its scene shows, the module pages at the inn included);
  the others are the village's nav labels, translated with the `scenes` schema as "The Forest" is. Color codes are
  left out.
- **Module pages** (`runmodule.php`) take the scene of the place the module's page happens in, by a static map of the
  bundled modules (`Art::MODULES`): Cedrik's potions, Dag Durnick's bounties, the drinks, Violet and Seth (`lovers`)
  and Seth's song at the inn; Crazy Audrey's visit in the village square; the outhouses in the forest. The module is
  the one `runmodule.php` ran, recorded before its run function, so a hook that loads another module does not change
  it. The drink editor (`act=editor`) is an editor page and shows none, also for a player with the module's own edit
  preference, who never passes `check_su_access()`. Every other module page shows the fallback, named by its title.
- **Third-party modules** keep the `scene` hook: it is asked with the scene the static maps chose and may name another.
- **Forest specials** (the fairy, the gem and gold finds, the foil wench, the glowing stream, the gold mine, Crazy
  Audrey's forest visit, the Dark Horse's encounter) run inside `forest.php` and show the forest.
- **No scene** on popups, superuser, editor and operator pages, whatever the script.

### Art palette (Phase 5 task 5)

Every color the batch 1 files use. Future art stays within it, or extends it here in the task that adds the art.
Batch 2 (the location scenes, task 12), batch 3 (the character panel's icons, task 13) and batch 4 (the foe
portraits, task 14) use only these colors.
Colors that are also UI tokens are named; transparency comes from `opacity` and `stroke-opacity`, not from new colors.

| Color | Token | Use in batch 1 |
|---|---|---|
| `#050403` | | Unknown traveller's hood shadow |
| `#0C130F` | | Forest, nearest trees |
| `#0E0C0A` | | Portrait silhouettes |
| `#101814` | | Village and fallback, near tree line |
| `#142019` | | Forest, middle trees |
| `#16201B` | | Forest sky |
| `#16211B` | | Village and fallback, far tree line |
| `#17140F` | `--header` | Ground in the village and fallback |
| `#1B2621` | | Night sky, village and fallback; the moon's shadow |
| `#1F1A14` | | Human's hair |
| `#1F2A1C` | | Troll's brow |
| `#1F2E25` | | Forest, far trees |
| `#221C15` | | Village road |
| `#232A20` | | Troll's halo |
| `#24201A` | `--raised` | Portrait arch fill |
| `#24211C` | | Unknown traveller's halo |
| `#251E16` | | Village houses |
| `#252A2C` | | Elf's halo |
| `#2A2219` | | Forest path |
| `#2A2620` | | Human's halo |
| `#2B241D` | | Dwarf's halo |
| `#2C2720` | `--track` | Dwarf's helm ridge, human's collar |
| `#2E2620` | | Dwarf's beard |
| `#30271C` | | Village roofs |
| `#3A3329` | `--border` | Dwarf's helm, the village's stone |
| `#6B563F` | | Inn sign bracket |
| `#7FB069` | `--health` | Troll's frame |
| `#8FB8D9` | `--magic` | Elf's frame and jewel |
| `#9A917F` | `--code-colLtBlack` | Unknown traveller's eyes |
| `#9CCB8A` | `--narrative` | Forest mist, the village's rune |
| `#A99F8D` | `--muted` | Unknown traveller's frame |
| `#C08650` | | Inn sign |
| `#C4936A` | `--code-colDkOrange` | Dwarf's frame, helm band and beard rings |
| `#D9A84E` | `--accent` | Lit windows, human's frame and brooch |
| `#E3CF9A` | `--code-colLtYellow` | Dwarf's eyes |
| `#E8826A` | `--damage` | Eyes in the forest |
| `#E9D27C` | | The moon, elf's circlet, troll's eyes |
| `#ECE4D4` | `--text` | Stars, troll's tusks |

## Accessibility (approved)

- Text contrast 4.5:1 or better against the color behind it.
- Touch targets 44px or more in both directions (nav rows, tabs, buttons, inputs). On a phone (task 8) also the skip
  link, the footer's Credits and source links, the attribution's two links (taller, never smaller), a form's buttons,
  the commentary's paging links and the mail popup's Inbox and Write links.
- Real links (`<a href>`), real buttons and labelled inputs: no click handlers on plain elements.
- Alt text or `aria-label` on every image; decorative art gets `alt=""` and the information is in text.

Set here: a visible focus ring (2px `--accent` outline, 2px offset) on every focusable element; motion limited to
short fades, none under `prefers-reduced-motion: reduce`.

## Open items

None. Closed on 2026-10-30: the outline contrast item (Jim chose `--input-border` `#6E6452`, see Palette).
