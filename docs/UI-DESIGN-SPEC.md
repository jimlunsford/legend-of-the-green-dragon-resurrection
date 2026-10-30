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

| Code | Class | Value | On panel | On raised | Source |
|---|---|---|---|---|---|
| `` `1 `` | `colDkBlue` | `#7E9BD9` | 6.38 | 5.84 | approved |
| `` `! `` | `colLtBlue` | `#9CB4EA` | 8.53 | 7.81 | approved |
| `` `2 `` | `colDkGreen` | `#6FA661` | 6.15 | 5.63 | approved |
| `` `@ `` | `colLtGreen` | `#9CCB8A` | 9.54 | 8.73 | approved |
| `` `3 `` | `colDkCyan` | `#5FB0A8` | 6.96 | 6.37 | approved |
| `` `# `` | `colLtCyan` | `#8FD3CC` | 10.41 | 9.53 | approved |
| `` `4 `` | `colDkRed` | `#D9644A` | 4.95 | 4.53 | approved |
| `` `$ `` | `colLtRed` | `#E8826A` | 6.60 | 6.04 | approved |
| `` `5 `` | `colDkMagenta` | `#B98AD0` | 6.41 | 5.87 | approved |
| `` `% `` | `colLtMagenta` | `#D9A0C4` | 8.23 | 7.54 | approved |
| `` `6 `` | `colDkYellow` | `#C2B05A` | 8.13 | 7.44 | approved |
| `` `^ `` | `colLtYellow` | `#E9D27C` | 11.77 | 10.78 | approved |
| `` `7 `` | `colDkWhite` | `#BDB4A3` | 8.61 | 7.88 | approved |
| `` `& `` | `colLtWhite` | `#F4EEE2` | 15.31 | 14.02 | approved |
| `` `) `` | `colLtBlack` | `#8F877A` | 4.98 | 4.56 | approved |
| `` `q `` | `colDkOrange` | `#C08650` | 5.70 | 5.22 | approved |
| `` `Q `` | `colLtOrange` | `#E9A25A` | 8.24 | 7.54 | approved |
| `` `~ `` | `colBlack` | `#958C7E` | 5.33 | 4.88 | set here: black is unreadable on a dark panel, so the darkest legible warm grey, darker than `` `7 `` |
| `` `e `` | `colDkRust` | `#B98680` | 5.71 | 5.23 | set here |
| `` `E `` | `colLtRust` | `#D4A39C` | 8.02 | 7.34 | set here |
| `` `g `` | `colXLtGreen` | `#B5E3A6` | 12.21 | 11.19 | set here |
| `` `G `` | `colXLtGreen` | `#B5E3A6` | 12.21 | 11.19 | set here (same class as `` `g ``, as in 1.1.2) |
| `` `j `` | `colMdGrey` | `#D6D0C4` | 11.52 | 10.55 | set here |
| `` `J `` | `colMdBlue` | `#8FA6E0` | 7.33 | 6.71 | set here |
| `` `k `` | `colaquamarine` | `#8CE0C6` | 11.45 | 10.48 | set here |
| `` `K `` | `coldarkseagreen` | `#9DBF9A` | 8.72 | 7.98 | set here |
| `` `l `` | `colDkLinkBlue` | `#6FA3D6` | 6.64 | 6.08 | set here |
| `` `L `` | `colLtLinkBlue` | `#7FBCF0` | 8.72 | 7.99 | set here |
| `` `m `` | `colwheat` | `#EAD8B5` | 12.62 | 11.56 | set here |
| `` `M `` | `coltan` | `#CDB08C` | 8.58 | 7.86 | set here |
| `` `p `` | `collightsalmon` | `#EBA486` | 8.58 | 7.86 | set here |
| `` `P `` | `colsalmon` | `#E58C7C` | 7.05 | 6.46 | set here |
| `` `R `` | `colRose` | `#E6BDE0` | 10.71 | 9.81 | set here |
| `` `T `` | `colDkBrown` | `#A88B6C` | 5.54 | 5.07 | set here |
| `` `t `` | `colLtBrown` | `#E9CF8C` | 11.60 | 10.62 | set here |
| `` `V `` | `colBlueViolet` | `#A98BE6` | 6.34 | 5.80 | set here |
| `` `v `` | `coliceviolet` | `#B3C1EA` | 9.88 | 9.05 | set here |
| `` `x `` | `colburlywood` | `#D9B88F` | 9.44 | 8.64 | set here |
| `` `X `` | `colbeige` | `#EFE8D0` | 14.42 | 13.21 | set here |
| `` `y `` | `colkhaki` | `#E6D99A` | 12.43 | 11.38 | set here |
| `` `Y `` | `coldarkkhaki` | `#BDB474` | 8.38 | 7.67 | set here |

That is every color code `appoencode()` knows (41 characters, 40 classes). Its other codes are not colors and keep
their meaning: `` `n `` line break, `` `0 `` end color, `` `b `` bold, `` `i `` italic, `` `h `` emphasis, `` `c ``
centered block, `` `> `` and `` `< `` floated blocks, `` `w `` the player's weapon name, a doubled backtick prints one backtick, and
`` `H `` the hotkey highlight, which the new UI draws as a key badge (below).

## Layout (approved)

**Header** (`--header`, 1px `--border` bottom, padding `--space-header-y` by `--space-side`): the wordmark at the left
("Legend of the Green Dragon" over "RESURRECTION"); at the right the mail link with its unread count as a badge (task 4),
the MoTD link and the petition link, each a 44px outlined control (`--input-border`, `--radius-control`).

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
`{copyright}` tag carries, unaltered), a Credits link (`about.php`), a Source link (the `sourceurl` setting); at the
right the version (`Resurrection\Version::display()`), the page generation time, and (task 4) the time to the next new
day.

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
