# Phase 5 task 2: design tokens, fonts and color codes (2026-10-30)

Evidence for Phase 5 task 2. It adds the design tokens, the self-hosted fonts and the color code values as files, with
static tests, and starts the ledger of changes from 1.1.2. Nothing rendered changes: no template, page, PHP render code
or skin was edited, and no page loads the new files yet (task 3 does).

Starting point: `main` at `836bb84a86625c0274d6ae5862c7cd2a39c04083`, as the task expected.

## Task 1 accepted

`836bb84` was confirmed through the GitHub Actions API: Modern core 41071411434 SUCCESS with all 12 jobs (shard job
times PHP 8.4 / MariaDB 11.4 18:00, 16:09, 16:59, 17:45; PHP 8.5 / MySQL 8.4 18:43, 20:32, 16:46, 19:54), Baseline
integrity 41071411378 SUCCESS. Appended to `PHASE5-TASK1-DIRECTION-20261030.md`; task 1 ticked in `ROADMAP.md`.

## Jim's decisions, 2026-10-30

- **Foe red `#C9553D`** is for bars, fills and markers only. Damage text uses `--damage` `#E8826A`. No value changed.
- **`--input-border`** changes from `#4A4235` (1.79:1 on panel, 1.64:1 on raised) to `#6E6452` (3.04:1 on panel,
  2.78:1 on its own raised fill), so input, button and key-badge outlines clear 3:1 against the panel they sit on.
  Panel and row borders stay `--border` `#3A3329` (decorative). The spec's open item is closed and the decision is
  recorded in its Palette section.

## What was added

| File | What |
|---|---|
| `assets/ui/tokens.css` | One plain CSS file, no build step. `:root` custom properties for every value in the spec: 16 palette colors, 3 font families, 7 weights, 9 type sizes, spacing, radii, layout widths and breakpoints, control, bar, icon and focus sizes (57 spec tokens), plus a `--code-<class>` property for each of the 40 color classes. Then one rule per class, `.colDkBlue { color: var(--code-colDkBlue); }`, with the code(s) in a comment. Class names are those `appoencode()` writes, unchanged (41 codes; `` `g `` and `` `G `` share `colXLtGreen`) |
| `assets/ui/fonts.css` | 16 `@font-face` rules: relative `../fonts/` URLs, `font-display: swap`, the latin and latin-ext `unicode-range` values as fontsource publishes them |
| `assets/fonts/<family>/` | 16 woff2 files and an `OFL.txt` per family (below) |
| `docs/THIRD-PARTY-LICENSES.md` | "Bundled fonts": each family, its source, version, license and upstream, and the SHA-256 of every file |
| `docs/CHANGES-FROM-1.1.2.md` | The new ledger (below) |
| `docs/UI-DESIGN-SPEC.md` | Token tables with the exact names in `tokens.css`, a Text column on the palette, the text contrast table, Jim's two decisions, `--size-label` 13px, the open item closed |
| `tests/tooling/test_ui_tokens.py` | The static tests (below) |

## Fonts

All three from the fontsource project (`npm pack`, the published npm tarballs), unmodified:

| Family | Package | Version | License | Files bundled |
|---|---|---|---|---|
| Cinzel | `@fontsource/cinzel` | 5.3.0 | `OFL-1.1` | 500 and 700 normal, latin and latin-ext (4) |
| Alegreya | `@fontsource/alegreya` | 5.3.0 | `OFL-1.1` | 400 normal, 500 normal, 400 italic, latin and latin-ext (6) |
| Alegreya Sans | `@fontsource/alegreya-sans` | 5.3.0 | `OFL-1.1` | 400, 500 and 700 normal, latin and latin-ext (6) |

Each package's `package.json` declares `OFL-1.1` and points to `fontsource/font-files`; each package's `LICENSE` is the
SIL Open Font License, Version 1.1, with the family's copyright line (Cinzel 2020, Alegreya 2011, Alegreya Sans 2013).
That text is copied unchanged to `assets/fonts/<family>/OFL.txt`. The `@font-face` ranges in `fonts.css` were compared
with the package's own `latin.css` and `latin-ext.css` and match. The SHA-256 of every file is in
`docs/THIRD-PARTY-LICENSES.md`, and the test checks the files against it.

## Contrast

Computed with the WCAG 2.x relative luminance formula, the same code the test uses. Text must reach 4.5:1 on
`--panel` `#1B1814` and on `--raised` `#24201A`.

Every text token and every color code passes on both grounds. **No approved value had to be lightened**, and no value
the spec chose itself was adjusted. The lowest code is `` `4 `` `colDkRed` `#D9644A` at 4.53:1 on raised.

### Palette

"Text: no" tokens are grounds, borders and bar fills, not text colors; their ratios are listed for completeness. The
non-text checks: `--input-border` 3.04:1 on panel (3:1 minimum, Jim's decision); `--foe` against its empty `--track`
3.42:1 and `--health` against `--track` 5.86:1 (3:1 minimum for graphics).

| Token | Value | Text | On panel | On raised |
|---|---|---|---|---|
| `--ground` | `#12100D` | no | 1.07 | 1.17 |
| `--header` | `#17140F` | no | 1.04 | 1.13 |
| `--panel` | `#1B1814` | no | 1.00 | 1.09 |
| `--raised` | `#24201A` | no | 1.09 | 1.00 |
| `--track` | `#2C2720` | no | 1.19 | 1.09 |
| `--border` | `#3A3329` | no | 1.42 | 1.30 |
| `--input-border` | `#6E6452` | no | 3.04 | 2.78 |
| `--text` | `#ECE4D4` | yes | 13.99 | 12.81 |
| `--muted` | `#A99F8D` | yes | 6.76 | 6.19 |
| `--accent` | `#D9A84E` | yes | 8.14 | 7.45 |
| `--accent-hover` | `#F0C46E` | yes | 10.80 | 9.89 |
| `--health` | `#7FB069` | yes | 7.00 | 6.41 |
| `--foe` | `#C9553D` | no | 4.08 | 3.74 |
| `--damage` | `#E8826A` | yes | 6.60 | 6.04 |
| `--magic` | `#8FB8D9` | yes | 8.44 | 7.73 |
| `--narrative` | `#9CCB8A` | yes | 9.54 | 8.73 |

### Color codes

All 41 codes `appoencode()` knows, each as the spec and `tokens.css` give it.

| Code | Class | Value | On panel | On raised |
|---|---|---|---|---|
| `` `1 `` | `colDkBlue` | `#7E9BD9` | 6.38 | 5.84 |
| `` `! `` | `colLtBlue` | `#9CB4EA` | 8.53 | 7.81 |
| `` `2 `` | `colDkGreen` | `#6FA661` | 6.15 | 5.63 |
| `` `@ `` | `colLtGreen` | `#9CCB8A` | 9.54 | 8.73 |
| `` `3 `` | `colDkCyan` | `#5FB0A8` | 6.96 | 6.37 |
| `` `# `` | `colLtCyan` | `#8FD3CC` | 10.41 | 9.53 |
| `` `4 `` | `colDkRed` | `#D9644A` | 4.95 | 4.53 |
| `` `$ `` | `colLtRed` | `#E8826A` | 6.60 | 6.04 |
| `` `5 `` | `colDkMagenta` | `#B98AD0` | 6.41 | 5.87 |
| `` `% `` | `colLtMagenta` | `#D9A0C4` | 8.23 | 7.54 |
| `` `6 `` | `colDkYellow` | `#C2B05A` | 8.13 | 7.44 |
| `` `^ `` | `colLtYellow` | `#E9D27C` | 11.77 | 10.78 |
| `` `7 `` | `colDkWhite` | `#BDB4A3` | 8.61 | 7.88 |
| `` `& `` | `colLtWhite` | `#F4EEE2` | 15.31 | 14.02 |
| `` `) `` | `colLtBlack` | `#8F877A` | 4.98 | 4.56 |
| `` `q `` | `colDkOrange` | `#C08650` | 5.70 | 5.22 |
| `` `Q `` | `colLtOrange` | `#E9A25A` | 8.24 | 7.54 |
| `` `~ `` | `colBlack` | `#958C7E` | 5.33 | 4.88 |
| `` `e `` | `colDkRust` | `#B98680` | 5.71 | 5.23 |
| `` `E `` | `colLtRust` | `#D4A39C` | 8.02 | 7.34 |
| `` `g `` | `colXLtGreen` | `#B5E3A6` | 12.21 | 11.19 |
| `` `G `` | `colXLtGreen` | `#B5E3A6` | 12.21 | 11.19 |
| `` `j `` | `colMdGrey` | `#D6D0C4` | 11.52 | 10.55 |
| `` `J `` | `colMdBlue` | `#8FA6E0` | 7.33 | 6.71 |
| `` `k `` | `colaquamarine` | `#8CE0C6` | 11.45 | 10.48 |
| `` `K `` | `coldarkseagreen` | `#9DBF9A` | 8.72 | 7.98 |
| `` `l `` | `colDkLinkBlue` | `#6FA3D6` | 6.64 | 6.08 |
| `` `L `` | `colLtLinkBlue` | `#7FBCF0` | 8.72 | 7.99 |
| `` `m `` | `colwheat` | `#EAD8B5` | 12.62 | 11.56 |
| `` `M `` | `coltan` | `#CDB08C` | 8.58 | 7.86 |
| `` `p `` | `collightsalmon` | `#EBA486` | 8.58 | 7.86 |
| `` `P `` | `colsalmon` | `#E58C7C` | 7.05 | 6.46 |
| `` `R `` | `colRose` | `#E6BDE0` | 10.71 | 9.81 |
| `` `T `` | `colDkBrown` | `#A88B6C` | 5.54 | 5.07 |
| `` `t `` | `colLtBrown` | `#E9CF8C` | 11.60 | 10.62 |
| `` `V `` | `colBlueViolet` | `#A98BE6` | 6.34 | 5.80 |
| `` `v `` | `coliceviolet` | `#B3C1EA` | 9.88 | 9.05 |
| `` `x `` | `colburlywood` | `#D9B88F` | 9.44 | 8.64 |
| `` `X `` | `colbeige` | `#EFE8D0` | 14.42 | 13.21 |
| `` `y `` | `colkhaki` | `#E6D99A` | 12.43 | 11.38 |
| `` `Y `` | `coldarkkhaki` | `#BDB474` | 8.38 | 7.67 |

## Other spec changes

- `--size-label`: task 1's spec said uppercase labels are 12px, which broke its own 13px minimum. Now 13px.
- Every token now has its name in the spec, so the spec and `tokens.css` can be compared by a test.

## The ledger: `docs/CHANGES-FROM-1.1.2.md`

Columns: area, 1.1.2, now, why, source. Seeded from the evidence docs by citing them, in four groups (removed or
disabled surfaces; accounts and security; gameplay and display; install, upgrade and local play), then the first
Phase 5 entry: the color code remap, marked "lands in task 3". Two candidates were left out because they are not
changes from 1.1.2: the settings editor's log (it restores 1.1.2 behavior) and the output buffering fix (a defect of
the modernization, not of 1.1.2).

## Tests

`tests/tooling/test_ui_tokens.py`, pure Python, no PHP or database (so it also runs in Baseline integrity), assigned
to a shard automatically:

- `test_tokens_equal_the_spec`: every spec token is in `:root` with the same value, no extra properties, and every
  color code's `--code-` property equals the spec.
- `test_text_tokens_and_color_codes_are_readable`: computes the contrast of every text token (the spec's Text column)
  and every `--code-` value on panel and raised (4.5:1), and the three non-text pairs above (3:1).
- `test_every_color_class_appoencode_writes_is_styled`: reads the `$colors` table from `lib/output.php`, checks every
  class has a rule in `tokens.css`, and that the spec's code-to-class table matches `appoencode()`.
- `test_bundled_fonts_are_the_recorded_files_with_their_licenses`: the files on disk are exactly the recorded ones,
  each with its SHA-256; every family folder has an `OFL.txt` with the OFL 1.1 title; every `@font-face` points to a
  recorded file by a relative URL with `font-display: swap`.
- `test_no_resource_request_leaves_the_game`: scans `.css`, `.htm`, `.html`, `.svg`, `.js` and `.xml` under `assets/`
  and `templates/` for an external URL in `src=` (and the other fetching attributes), `<link href>`, `url()` and
  `@import`. `data:` and `blob:` are allowed; a plain `<a href>` is allowed (the old skins' credit links).
- `RequestScannerTests` (2): the scanner finds each kind of external request, and allows local paths and plain links.

### Failing first, then passing

| Run | Result |
|---|---|
| The test against the tree at `836bb84` (no `assets/`) | 5 errors: `FileNotFoundError: .../assets/ui/tokens.css`; the 2 scanner tests pass (fixtures only) |
| Broken copy: `--panel` `#1B1815` in `tokens.css` | `test_tokens_equal_the_spec` FAIL |
| Broken copy: `colBlack` `#6E6452` in the spec and `tokens.css` | contrast FAIL (2.78 on raised) |
| Broken copy: `--muted` `#7A7264` in both | contrast FAIL (3.72 on panel) |
| Broken copy: the `.colRose` rule removed | FAIL: "`` `R `` writes class colRose, which tokens.css does not style" (and the tokens test) |
| Broken copy: one byte appended to `alegreya-latin-400-normal.woff2` | FAIL: SHA-256 mismatch (expected `7cbcf38a...`, got `ce104040...`) |
| Broken copy: `assets/fonts/cinzel/OFL.txt` removed | FAIL: "cinzel has no OFL.txt" |
| Broken copy: `assets/ui/broken.css` with `@import url('https://fonts.googleapis.com/...')` | request test FAIL |
| Broken copy: `templates/jade.htm` `<link href>` to `https://cdn.example/jade.css` | request test FAIL |
| The real files | OK, 7 tests |

Each broken copy was undone after its run; none is committed.

## Local verification (supporting evidence only)

PHP 8.5.12 (php-builder, as setup-php) and MySQL 8.4 in Docker:

- `vendor/bin/phpunit` on a fresh empty database: OK, 283 tests, 141346 assertions.
- `composer analyse` and `phpstan analyse -c phpstan-new.neon`: no errors.
- `composer lint`: 392 files, 0 failures. `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: PASS, 417 files, 11 historical commits, tag object, commit and tree as
  recorded. `scripts/validate-project.py`: PASS. `git diff --cached --check`: clean. `LICENSE.txt` and `README.txt`
  byte-identical to the tag.
- Tooling tests without a database (`test_ui_tokens`, `test_tooling_shards`, `test_license_text`, `test_version`,
  `test_baseline`, `test_route_inventory`, `test_disabled_endpoints`, `test_local_play`, `test_recommended_modules`,
  `test_lab`, `test_php_runtime`, with `RESURRECTION_REQUIRE_PHP=1`): OK, 53 tests.
- No shard was run locally, as the task said. CI runs the full suite.

## Not changed

- Templates, pages, PHP render code, skins, `LICENSE.txt`, `README.txt`, the version string, the CI workflows, the
  shard timeout and `tests/tooling/shard-durations.json`.
- No `assets/LICENSE` (the art task creates it). No file labelled AGPL. No tag, release, deploy or hosting.

## Acceptance

Pending: this commit's Modern core (both legs, all 12 jobs) and Baseline integrity runs. `ROADMAP.md` ticks task 2
only after CI accepts it.
