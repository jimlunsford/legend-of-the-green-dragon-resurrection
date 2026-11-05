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
  scenes/<place>.svg          scene banners, 1200x320: village, forest, fallback, and the 17 places of batch 2
  portraits/race/<race>.svg   race portraits, 200x240: human, elf, dwarf, troll, unknown
  icons/<name>.svg            line icons on a 24px grid (later tasks)
  fonts/<family>/             woff2 faces and OFL.txt (SIL OFL 1.1)
  ui/                         tokens.css, fonts.css, resurrection.css
```

`src/Ui/Art.php` picks the files: the scene by page (`home.php` and `village.php` show the village, `forest.php` the
forest, and since Phase 5 task 12 each place's own page its place: `inn.php` the inn, `bank.php` the bank, `pvp.php` the
fields, and so on; a bundled module's page the scene of the place it happens in, Seth's song and Violet at the inn for
example; every other player page the fallback; popups, superuser, editor and operator pages none; a module can name its
own scene with the `scene` hook), and the portrait by race (the race lowercased; any other race, or none, gets
`unknown`). `docs/UI-DESIGN-SPEC.md` has the image rules, the scene list with its mapping rules, and the art palette.

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

## Batch 2 (Phase 5 task 12)

The location scenes, approved by Jim on 2026-11-01. They use only colors already in the art palette.

| File | SHA-256 |
|---|---|
| `scenes/armor.svg` | `115ead5d1ababe762134c2cb6c653b54a00c7e287f628d8b6209897ef7d96f38` |
| `scenes/bank.svg` | `256923ca0f8cc396c610b6eb825d9a1e2d4be8caaff8bfc7d925afef2573e9d6` |
| `scenes/clan.svg` | `b2368ffeb7e15003d7d649094e30ed67bbfc0593419313ac399c8252c890b0ef` |
| `scenes/dragon.svg` | `5d71224de2d40dcd9bf74a262832906e4aaab992391ce11f503ccb7dce5a2770` |
| `scenes/fields.svg` | `fe0528561d41f815efb3caa61db3aae4acd2e6ef896ccf1cfee2e45f3313987e` |
| `scenes/gardens.svg` | `0c854fbac31e7b3890e205f635b8acd5fe32d4c75f4dc6a3fbf1d127e014a5f1` |
| `scenes/graveyard.svg` | `2fc1c01c44c5c28b8b20d69bdd6babc9e5f0fceec0db357484b7195d08f08914` |
| `scenes/gypsy.svg` | `9316f3b8e37847acb090fddde8cf7cd15b8f06fbd5a5c90722be0500d84dec25` |
| `scenes/healer.svg` | `53b7fa5bd9b6ec97103880443c553bf682f0f12052d69c9c49b3f19624aafc26` |
| `scenes/inn.svg` | `326d401485cad52a2962f9f3c53baa39cf7f66577242a2e3d766535eea3e665d` |
| `scenes/lodge.svg` | `8ad19edc6e51efd9e9516e2d2cb8a9f9d2aaf1b27d001e1492a6baa44ab8e29b` |
| `scenes/mercenarycamp.svg` | `2ec7e4d4b7b95b67afb02cf4717815f434c5f3e9e3da59faa614547cc0980a3b` |
| `scenes/rock.svg` | `57e8a5c154725409741de15373a9f4a0972b3a4319d97182aee4bfd85729e280` |
| `scenes/shades.svg` | `aeec36eeeb740d4f6cb2c22fd3df46806710926024f0e1670f548926c54ebcdc` |
| `scenes/stables.svg` | `e316875b62d063d0ea7b708a7a38e82aa8bc29a6bb3c43b9d684bf6be8bbe48e` |
| `scenes/train.svg` | `78c2371f80af364d3e1ed01d6edfa0d9c51841fa6e8c28734e98885524b82133` |
| `scenes/weapons.svg` | `97417d9d10db40dea471030074a08e6d60b719e44c96b32d00129d040fb1d1cf` |

`tests/tooling/test_art.py` checks every file under `scenes/` and `portraits/` against these tables.

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
