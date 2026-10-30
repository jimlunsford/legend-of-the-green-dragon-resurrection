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
| `--raised` | `#24201A` | Stat tiles, the fill of inputs and buttons, hovered rows | no |
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
  therefore use no `--raised` fill: striped rows alternate `--panel` and `--header`.

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
`--leading-body`). Nav rows are 44px high with `--radius-control`, `--text`, and `--raised` on hover. The character
panel's sections sit in `--panel` cards with `--space-panel` padding. Links are `--accent`, hover `--accent-hover`.

**Desktop**, three columns:

| Column | Width | Holds |
|---|---|---|
| Nav, left | 220 to 280px (`flex: 1 1 220px`) | Nav groups |
| Content, center | the rest (`flex: 999 1 560px`, `min-width: 0`) | The scene banner, the page title, the page content |
| Character, right | 260 to 320px (`flex: 1 1 260px`) | Portrait, HP and XP bars, stat tiles, equipment, buffs |

The columns wrap when the window is too narrow for all three, and below `--breakpoint-stack` each takes the full
width, with no sideways scroll.

**Phone** (set here: below 900px the columns stack; below 600px the phone chrome takes over): the columns stack
content first. A compact header shows the HP and XP bars. A bottom tab bar (`--header`) has four tabs: Village,
Character, Talk, More. The page never scrolls sideways; wide admin tables scroll inside their own box.

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
| `--key-badge-size` | `24px` | Key badge, a square (mockup; was 22px) |
| `--icon-size` | `24px` | Icon grid |
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

- **Nav groups.** Each group has an uppercase Cinzel heading (`addnavheader()`). Items are full-width rows.
- **Key badges.** Every nav link that has a hotkey shows it as a key badge: a `--key-badge-size` square, `--raised`,
  `--radius-badge`, with an `--input-border` outline and the key in `--accent`, Alegreya Sans 700, 13px. The keyboard shortcut keeps working.
- **One primary action per screen**, gold fill (`--accent`, text `--ground`, hover `--accent-hover`). Every other
  button is secondary: `--raised` fill, `--input-border` outline, `--text` label.
- **HP and XP bars.** Fully rounded (`--radius-bar`), `--bar-height` in the character panel and `--bar-height-fight` in
  a fight. `--track` behind, the fill in `--health` for the player and `--foe` for enemies; the two fills
  also differ in lightness (relative luminance 0.366 and 0.193), so they read apart without color. XP uses `--accent`.
  Each bar shows its numbers as text (for example "34 / 50"), so a bar never carries meaning by color alone.
- **Stat tiles.** A `--raised` tile with an uppercase Cinzel label (`--muted`) over the value (Alegreya Sans 700).
- **Equipment rows.** An icon, the item name, and its value (attack or defense), one row each: weapon, armor, mount.
- **Commentary.** A list of comments (speaker in Cinzel, text in Alegreya), and below it the input, at least 44px
  high, with an Add button beside it.
- **Battle log.** An ordered list, one entry per combat line, newest last. Damage numbers in `--damage`, healing in
  `--health`, magic in `--magic`.

## Art (approved)

- **Scene banners** above the page content: woodcut style, layered dark silhouettes, light from windows, the moon,
  eyes and gems; palette colors only.
- **Portraits** in an arched frame tinted by race (players) or creature type (creatures).
- **Icons**: line icons on a 24px grid, 1.8 stroke, colored by meaning (health green, damage, magic, gold accent).
- **Every image slot has a fallback**: a missing scene shows a plain banner in the palette, a missing portrait a
  generic silhouette for the race or creature type, a missing icon nothing (the text label stays).
- All art is original SVG drawn by Claude for Jim / Phoenix 2:33 LLC, in `assets/` under CC BY-SA 4.0 with its own
  LICENSE file (created in the art task).

## Accessibility (approved)

- Text contrast 4.5:1 or better against the color behind it.
- Touch targets 44px or more in both directions (nav rows, tabs, buttons, inputs).
- Real links (`<a href>`), real buttons and labelled inputs: no click handlers on plain elements.
- Alt text or `aria-label` on every image; decorative art gets `alt=""` and the information is in text.

Set here: a visible focus ring (2px `--accent` outline, 2px offset) on every focusable element; motion limited to
short fades, none under `prefers-reduced-motion: reduce`.

## Open items

None. Closed on 2026-10-30: the outline contrast item (Jim chose `--input-border` `#6E6452`, see Palette).
