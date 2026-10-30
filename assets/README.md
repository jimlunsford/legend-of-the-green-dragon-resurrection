# assets

The files the Resurrection UI serves as they are: its original art, its stylesheets and its fonts.

## The art and its license

The art is original SVG drawn by Claude for Phoenix 2:33 LLC and approved by Jim Lunsford. It is Copyright (c) 2026
Phoenix 2:33 LLC and licensed under the Creative Commons Attribution-ShareAlike 4.0 International license
(CC BY-SA 4.0); the full text is in [`LICENSE`](LICENSE).

The license covers exactly three folders:

- `scenes/`
- `portraits/`
- `icons/` (none yet)

It does not cover `fonts/` (each font family keeps the SIL Open Font License 1.1 in its own `OFL.txt`, see
`docs/THIRD-PARTY-LICENSES.md`) or `ui/` (the stylesheets are code, under the core license: CC BY-NC-SA 2.0 and
`LICENSE.txt`, see `NOTICE`), or any other file in the repository.

Credit to use wherever the art is shown or shared outside the game:

> Art by Phoenix 2:33 LLC, CC BY-SA 4.0

## Layout

```
assets/
  LICENSE                     CC BY-SA 4.0 for scenes/, portraits/ and icons/
  README.md                   this file
  scenes/<place>.svg          scene banners, 1200x320: village, forest, fallback
  portraits/race/<race>.svg   race portraits, 200x240: human, elf, dwarf, troll, unknown
  icons/<name>.svg            line icons on a 24px grid (later tasks)
  fonts/<family>/             woff2 faces and OFL.txt (SIL OFL 1.1)
  ui/                         tokens.css, fonts.css, resurrection.css
```

`src/Ui/Art.php` picks the files: the scene by page (`home.php` and `village.php` show the village, `forest.php` the
forest, every other player page the fallback; popups, superuser, editor and operator pages none; a module can name its
own scene with the `scene` hook), and the portrait by race (the race lowercased; any other race, or none, gets
`unknown`). `docs/UI-DESIGN-SPEC.md` has the image rules and the art palette.

## Batch 1 (Phase 5 task 5)

| File | SHA-256 |
|---|---|
| `portraits/race/dwarf.svg` | `93373dcde21447afd1780e1bb999e6c88d729bf24535f205f354e63a759568d3` |
| `portraits/race/elf.svg` | `c19dcaaf65be923458b48bc12329bef80ba12f26d013237f22e584c4ddeb8927` |
| `portraits/race/human.svg` | `d619bfc4791001d00cf844e28ae493043a589b8912f1dd5f5c41bf67c6337740` |
| `portraits/race/troll.svg` | `873766832593ceab7c59f5c4fa62f798c3136f5241f57577a7ad91245000591e` |
| `portraits/race/unknown.svg` | `6814704b837cc59c26b20fd80a3e30e81077ee93202b68b15661142d6416a7b8` |
| `scenes/fallback.svg` | `9869c11fa1588d16db2e5c05774b824e3402409e1f820fa4a891d91a2a6541d5` |
| `scenes/forest.svg` | `26c9d2b6e12e37030d18de04cf0ff6fb2ecf16dda54df51353c6ca34739389e3` |
| `scenes/village.svg` | `5113b4c2d7256e9466f72742d1bd41630757f5904b3dd653eb9db1073eda2e06` |

`tests/tooling/test_art.py` checks every file under `scenes/` and `portraits/` against this table.

## Adding or changing art

Art comes only from the command chat, approved by Jim, as new files with their SHA-256 checksums. A task:

1. verifies every checksum before using a file, and stops if any differs;
2. commits the files byte for byte at their paths (a change to a piece of art is a new file from the command chat,
   never an edit here);
3. adds each file and checksum to the table above and to `tests/tooling/test_art.py`;
4. keeps every color within the art palette in `docs/UI-DESIGN-SPEC.md`, or extends the palette there in the same
   task.

Every SVG here stays a plain drawing: no script, no event attribute, no `foreignObject`, no `href` or other external
reference, and under 16 KB. The static test enforces it.
