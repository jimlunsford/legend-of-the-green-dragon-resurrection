# Resurrection UI design spec

The design contract every Phase 5 UI task builds to. One dark fantasy UI replaces all 1.1.2 skins
(`PHASE5-TASK1-DIRECTION-20261030.md`). The plan and the order of work are in `PHASE5-UI-PLAN-20261030.md`.

Values marked **approved** are the ones Jim approved in the mockups on 2026-10-30. Use them exactly. Values marked
**set here** were chosen in Phase 5 task 1 to complete the system; a later task may tune them, and records the change
here when it does. Changing an approved value needs Jim.

## Palette (approved)

| Token | Value | Use |
|---|---|---|
| `--ground` | `#12100D` | Page background |
| `--header` | `#17140F` | Header bar, footer, phone tab bar |
| `--panel` | `#1B1814` | Nav, content and character panels. The reference color for text contrast |
| `--raised` | `#24201A` | Cards, stat tiles, inputs, hovered rows |
| `--track` | `#2C2720` | Empty part of a bar (HP, XP) |
| `--border` | `#3A3329` | Panel and row borders |
| `--input-border` | `#4A4235` | Input and button outlines |
| `--text` | `#ECE4D4` | Body text |
| `--muted` | `#A99F8D` | Labels, secondary text, the footer |
| `--accent` | `#D9A84E` | Ember gold: primary action fill, links, focus ring, key badges |
| `--accent-hover` | `#F0C46E` | Hover and pressed state of the accent |
| `--health` | `#7FB069` | The player's HP bar and healing |
| `--foe` | `#C9553D` | Enemies' HP bars and foe markers |
| `--damage` | `#E8826A` | Damage numbers and damage text |
| `--magic` | `#8FB8D9` | Magic, buffs, specialty effects |
| `--narrative` | `#9CCB8A` | Narrative green: story highlights |

Contrast on `--panel` (WCAG 2.x ratio; computed in task 1): text 13.99, muted 6.76, accent 8.14, accent-hover 10.80,
health 7.00, damage 6.60, magic 8.44, narrative 9.54. Text on an accent fill (`--ground` on `--accent`): 8.74.

Two approved colors are not for text:

- `--foe` is 4.08:1 on the panel, under 4.5. Use it for bars, fills and markers only (3.42:1 against `--track`, which
  clears the 3:1 non-text rule). Text about damage uses `--damage`.
- `--border` (1.42:1) and `--input-border` (1.79:1) are under the 3:1 non-text rule on their own. An input is
  therefore never identified by its border alone: it also has the `--raised` fill, a visible label, and an accent focus
  ring. See "Open items".

## Type (approved)

All three families are SIL Open Font License. The woff2 files are self-hosted with their OFL texts (Phase 5 task 2).
The game makes no third-party font or CDN request.

| Family | Weights | Use |
|---|---|---|
| Cinzel | 500, 700 | Titles, character and creature names, section labels, uppercase tracked labels (nav group headings, stat labels) |
| Alegreya | 400, 500, italic | Narrative and story text: the page content `output()` produces, commentary, MoTD bodies |
| Alegreya Sans | 400, 500, 700 | Navigation, stats, forms, buttons, tables, the footer |

Fallback stacks (set here): Cinzel → `Georgia, 'Times New Roman', serif`; Alegreya → `Georgia, serif`;
Alegreya Sans → `system-ui, -apple-system, 'Segoe UI', sans-serif`. `font-display: swap`.

Scale (set here): body 16px / 1.55 (Alegreya); UI text 15px / 1.4 (Alegreya Sans); small 13px (footer, key badges);
page title 26px Cinzel 700; section heading 18px Cinzel 700; uppercase labels 12px Cinzel 500, letter-spacing 0.12em.
Text never goes below 13px.

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

**Header** (`--header`): the wordmark at the left; at the right the mail link with its unread count as a badge, the
petition link, and the MoTD link.

**Desktop**, three columns:

| Column | Width | Holds |
|---|---|---|
| Nav, left | 220 to 280px | Nav groups |
| Content, center | the rest | The scene banner, the page title, the page content |
| Character, right | 260 to 320px | Portrait, HP and XP bars, stat tiles, equipment, buffs |

**Phone** (set here: below 900px the columns stack; below 600px the phone chrome takes over): the columns stack
content first. A compact header shows the HP and XP bars. A bottom tab bar (`--header`) has four tabs: Village,
Character, Talk, More. The page never scrolls sideways; wide admin tables scroll inside their own box.

**Footer** (`--header`, `--muted` text): the original credit line (the copyright line and license block the template's
`{copyright}` tag carries, unaltered), a Credits link (`about.php`), a Source link (the `sourceurl` setting), the
version (`Resurrection\Version::display()`), and the time to the next new day.

Spacing (set here): a 4px base unit; panel padding 16px (12px on a phone); 16px gutter between columns; 16px side
gutter on a phone; panel radius 6px.

## Components (approved)

- **Nav groups.** Each group has an uppercase Cinzel heading (`addnavheader()`). Items are full-width rows.
- **Key badges.** Every nav link that has a hotkey shows it as a key badge: a small `--raised` box with an
  `--input-border` outline and the key in `--accent`, Alegreya Sans 700, 13px. The keyboard shortcut keeps working.
- **One primary action per screen**, gold fill (`--accent`, text `--ground`, hover `--accent-hover`). Every other
  button is secondary: `--raised` fill, `--input-border` outline, `--text` label.
- **HP and XP bars.** `--track` behind, the fill in `--health` for the player and `--foe` for enemies; the two fills
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

- The approved `--border` and `--input-border` values are under WCAG's 3:1 contrast for component boundaries. The
  spec compensates (raised fill, labels, focus ring). If Jim wants the outlines themselves to clear 3:1, the input
  border would need to be about `#6E6452` or lighter (3.04:1 on the panel); that is his call.
