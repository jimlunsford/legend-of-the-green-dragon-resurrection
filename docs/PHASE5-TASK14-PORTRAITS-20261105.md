# Phase 5 task 14: creature and master portraits (2026-11-05)

Task 14 of the Phase 5 plan (`ROADMAP.md`, `docs/PHASE5-UI-PLAN-20261030.md`): the foe portraits. Started from `main`
at `6e2f55b44421bf8de56504578dee82359f773b14` (task 13), as the brief expected. It also records task 13's acceptance
(`docs/PHASE5-TASK13-ICONS-20261105.md`, "Acceptance").

## The art: batch 4, checksums verified

313 portraits from a parts kit, original SVG drawn by Claude for Phoenix 2:33 LLC, approved by Jim on 2026-11-05,
attached to the brief as `resurrection-art-batch4.zip`. Unzipped outside the repository and checked before any use:

| Check | Result |
|---|---|
| `SHA256SUMS` against the brief (`1662a4cf…714687`) | OK |
| `portraits/creature/kit.json` against the brief (`a3234d759c889721ff96b1dda8d7877781993ef33ee9e96133ce1ddc52f32fc1`) | OK |
| `tools/build_creature_portraits.py` against the brief (`7e621fd9b41781831bf173720ef1cab13a0f7dec4186d332d1d4adf810d40c81`) | OK |
| `sha256sum -c SHA256SUMS` | all 313 OK; the list names exactly the zip's 313 SVGs |
| The generator on `kit.json` into an empty folder (`python3 -I … kit.json OUT`) | 313 files, byte for byte the zip's (`sha256sum -c` in the output: all OK; `diff -r`: no difference) |

The zip held 288 creature portraits, `green-dragon.svg`, 10 fallbacks and 14 master portraits. Committed unedited:

- the SVGs and `kit.json` under `assets/` at their zip paths (`assets/portraits/creature/<creatureid>.svg`,
  `assets/portraits/creature/green-dragon.svg`, `assets/portraits/creature/fallback/<type>.svg`,
  `assets/portraits/master/<masterid>.svg`, `assets/portraits/creature/kit.json`);
- `SHA256SUMS` as `assets/portraits/SHA256SUMS` (its paths, `./portraits/…`, are relative to `assets/`, so
  `sha256sum -c portraits/SHA256SUMS` runs there);
- the generator as `scripts/art/build_creature_portraits.py`. It is code, under the core license like the other
  scripts; no file is labelled AGPL (the open question in `CLAUDE.md` stands).

Each SVG is 200x240, has a `<title>` (the kit's name), is under 2 KB, and uses only colors already in the art palette,
so the palette is unchanged. The existing safety, palette and size checks (`tests/tooling/test_art.py`) cover them:
they read every SVG under `assets/`.

### What the kit holds

Checked against the installer's statements (`lib/installer/installer_sqlstatements.php`), not a hard-coded list:

- **288 creatures**, the ids, names, levels and weapons a fresh install seeds: ids 1 to 131, 134 to 189 and 219 to
  320 (the gaps are 1.1.2's). Recipes by type: humanoid 106, beast 55, undead 35, spirit 29, monster 21, construct 19,
  demon 10, plant 8, dragon 5.
- **14 masters**, ids 1 to 14 with their levels and weapons. Master 5's seeded name holds the replacement character
  U+FFFD where 1.1.2's file lost a byte (the historical tag has the same bytes, `55 6e ef bf bd 6c 69 74 68`); the kit
  names it "Unélith". The SVG's title is the kit's; the card's alt text is the name the fight shows, the seeded one.
- **The Green Dragon** (`special`), and **10 fallbacks**: humanoid, beast, undead, spirit, monster, demon, construct,
  plant, dragon and unknown.
- No two recipes at the same level are the same. Two files are the same drawing: creatures 134 and 135, both
  "Loneliness" with "Silence", seeded by 1.1.2 at levels 17 and 18. One creature seeded twice keeps one portrait.

Besides the seeds' own rows, the installer touches the two tables only to set `forest` and `graveyard`, drop
`oldcreatureexp`, and fix Sensei Noetha's win line; the test fails if any other statement appears, so a rename can
never slip past it.

## The lookup

`src/Ui/Art.php` (task 5's class), every lookup returning a file that exists, with its alt text and size, or null:

- `Art::creature($id, $alt, $type)`: `portraits/creature/<id>.svg` for an id the kit drew (a whole number above 0, as
  an int or its digits; anything else, `01`, `1.0`, a path, has none); otherwise the fallback of the type `$type()`
  names (asked only then), or unknown.
- `Art::master($id, $alt, $type)`: `portraits/master/<id>.svg` the same way.
- `Art::dragon($alt)`: `portraits/creature/green-dragon.svg`.
- `Art::creatureType($name)`: one of `Art::CREATURE_TYPES` (the 10 fallbacks), else `unknown`.
- `Art::foe($enemy, $fight, $alt, $hook)`: the battle card's choice, by the fight's type (`battle.php`'s
  `$options['type']`, as the route stored it): `pvp` the victim's race portrait (task 5's `Art::portrait()`, by
  `race`), `dragon` the Green Dragon's, `train` the master's by `creatureid`, any other the creature's by
  `creatureid`. For a foe with no portrait of its own `$hook` is the new `creatureportrait` module hook: it gets
  `['creature' => the foe, 'fight' => the type, 'type' => 'unknown']` and may set `type`.

The masters table's key is also named `creatureid` and both tables start at 1, so a master and a creature share ids:
the fight's type, not the id, says which folder. A seeded creature renamed in the editor keeps its id's portrait, as
does a forest foe the fight prefixes ("Elite", "Savage" and the others). Foes with no portrait of their own: a
creature added in the creature editor (its id is past 320, as auto-increment gives), a master added in the masters
editor (id 15 and up), a module's foe with no id, and the forest's "evil doppleganger" (no creature fits the level).
Each gets the type the hook names, or the unknown traveller. No bundled module builds a foe of its own, so none uses
the hook yet.

## The battle card

`lib/battle_view.php` keeps the fight's type when `battle.php` hands it the foes (`resurrection_battle_enemies()`), and
`resurrection_battle_portrait()` fills the template's new `fighterportrait` part for each foe's card: an `<img>`, its
`width` and `height` the file's 200x240, its alt text the foe's name with color codes dropped. The `fighter` part now
wraps the name and level line in `rz-fighter-id` with the portrait before them; the player's card gets none (the
character panel shows the race portrait). `assets/ui/resurrection.css` draws the portrait 80px wide
(`--portrait-width-foe`, new in `tokens.css` and the spec), at every width, beside the name and level, 14px apart.
With several foes each card has its own. Only the Resurrection template has the part; a 1.1.2 skin's battle pages are
byte for byte as before (the task 10 pins pass).

Recorded in `docs/UI-DESIGN-SPEC.md` ("Fighter cards", "Foe portraits", the art rules and palette note),
`docs/CHANGES-FROM-1.1.2.md` ("Foe portraits (task 14)", "`creatureportrait` module hook (task 14)") and
`assets/README.md` (batch 4, the kit, the generator, how to add a recipe and rebuild).

## Tests: failing first, passing after

New and changed:

- `tests/tooling/test_portraits.py` (new, static): every portrait matches `SHA256SUMS`, and the portrait folders hold
  nothing else but the kit; the kit holds exactly the seeded creatures (ids, names, levels, weapons) and masters, read
  from the installer, each with its file; the seed reader's own cases; no two recipes at the same level the same (and
  the one shared drawing is the creature seeded twice); the fallbacks are `Art::CREATURE_TYPES` and every recipe's
  type has one; the generator, run with `-I` on the kit into a temporary folder, gives the committed files byte for
  byte and nothing else; the generator carries no AGPL label.
- `tests/tooling/test_art.py`: `SHA256SUMS` and `kit.json` pinned (`PORTRAIT_KIT`), every file under
  `assets/portraits/` listed through them, the README's pins; the SVG checks assert they read the 313 new files.
- `tests/tooling/test_ui_battle.py`: the portrait's CSS, token and template parts; the spec's "Foe portraits".
- `tests/tooling/test_ui_template.py`: the `fighter` part's `{portrait}` and the `fighterportrait` part's keys.
- `tests/unit/ArtTest.php`: six lookups: a seeded creature (by int or string id, renamed, the hook not asked); unknown
  creatures (an editor's id, the seeds' gaps, no id, malformed ids) and an editor's master; a module-named type for
  each of the 10, anything else unknown, and the hook's arguments; each master, and a training foe's id read as a
  master's; the Green Dragon; a PvP victim's race.
- `tests/unit/BattleCommentaryRenderTest.php`: every foe card the existing fights render (forest, five rounds, the
  graveyard torment, the training master) must show its portrait with the right `src` (worked out in the test from the
  stored foe), alt text and size, and the player's card none; three new fights: two forest foes (two cards, two
  different portraits; the target's "Target" mark), the Green Dragon (its encounter and a round), and PvP (an elf
  victim's race portrait). The old-skin pins are unchanged and pass.

On `6e2f55b` with the new tests copied in (no art, no code):

| Run | Result |
|---|---|
| The static tests above (36) | FAILED: 4 failures, 2 errors (`test_portraits` cannot load the kit; `test_every_file_is_the_one_supplied`, `test_every_svg_is_a_plain_drawing`, `test_every_part_the_code_uses_exists`, `test_foe_portrait`, the spec test) |
| PHPUnit `ArtTest`, `BattleCommentaryRenderTest` (33) | 6 errors (`Art::creature()`, `master()`, `dragon()`, `foe()`, `CREATURE_TYPES` undefined) and 7 failures (no portrait file: forest, five rounds, graveyard, training, two foes, dragon; PvP: no image) |
| The same with the art and generator added, the code still 6e2f55b's | the 7 render tests fail on the missing image (`0 is identical to 1`); `test_the_fallbacks_are_the_creature_types_the_code_knows` fails |

Broken copies of the final tree, one change each (`test_portraits.py`, `test_art.py`):

| Change | Fails |
|---|---|
| None | OK |
| One portrait one byte longer | the SHA256SUMS check, the rebuild, `test_every_file_is_the_one_supplied` |
| Creature 5 dropped from the kit | the kit check, the rebuild, the kit pin |
| Creature 2 given creature 1's recipe (both level 1) | the same-level check, the rebuild, the kit pin |
| Master 3 misnamed in the kit | the kit check, the rebuild, the kit pin |
| The installer renames creature 320 | the kit check |
| The installer gains an `UPDATE` of a creature's name | the kit check |
| The generator draws the gold one shade off | the rebuild |
| An extra portrait file | the SHA256SUMS check, `test_every_file_is_the_one_supplied` |
| `Art::CREATURE_TYPES` without dragon | the fallbacks check |
| A master portrait with a `<script>` | `test_every_svg_is_a_plain_drawing` (and the checksum) |
| A fallback with a color outside the palette | `test_every_color_is_in_the_art_palette` (and the checksum) |
| The Green Dragon's file over 16 KB | `test_every_svg_is_a_plain_drawing` (and the checksum) |

A fixture note: the PvP attack form is bound to both accounts as they are, and a new account's first page fills its
preferences, which changed the attacker the form was issued for ("Expired or repeated action"). The test opens the
village first, as a player would have; the guard is right and unchanged.

## Local verification

| Check | Result |
|---|---|
| PHPUnit, full, PHP 8.5.12, empty MySQL 8.4 | OK, 346 tests, 148,253 assertions |
| PHPUnit, full, PHP 8.4.27, empty MySQL 8.4 | OK, 346 tests, 148,253 assertions |
| Shards 1 to 4 (`scripts/tooling-shards.py`, `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5, a MySQL 8.4 each, all four at once, PHPUnit first in each: OK 346) | OK: shard 1, 54 tests in 29.5 min; shard 2, 52 in 30.0; shard 3, 165 in 29.8; shard 4, 53 in 29.2; no failure, error or skip |
| PHPStan `phpstan.neon`; `phpstan-new.neon` | no errors; no errors |
| `composer lint`; `composer validate --strict`; `composer audit` | 0 failures (407 files); valid; no advisories |
| Historical verifier; project validator | PASS; PASS (1,092 tracked files with this task's staged) |
| `git diff --cached --check` | clean |

The local shards ran four at once on four cores (task 13 ran two at a time: 25 to 27 min), so their times are not a
basis for the duration table, which is unchanged; the new static test is assigned by the runner. CI's own times are
the record.

## Screenshots

Not committed; attached to the task's report (`/mnt/project-files/phase5-task14/`). A copy of the final tree on its
own MySQL 8.4 database, the recommended modules, six players, `php -S`, Playwright at a device scale of 2, each fight
started through its own form:

- `desktop-sheet.png` (1440x1000): three forest fights (levels 1, 9 and 13), a training master (Adwares, master 6),
  a graveyard torment and the Green Dragon, each cropped to the scene's foot, the cards and the Fight row.
- `phone-sheet.png`: a forest fight and the Green Dragon at 390x844, side by side.

Every portrait loaded (`naturalWidth` above 0), had its alt text and was 80px wide; no page scrolled sideways.

## Not changed

- The 1.1.2 skins and their battle pages; `battle.php`'s rounds; the creatures, masters and their stored fights.
- The art palette, `assets/LICENSE`, the copyright line and license block, the attribution line and its links.
- Translation keys (the alt text is the fight's own name).
- The 60-minute job timeout, the shard runner and the duration table.
