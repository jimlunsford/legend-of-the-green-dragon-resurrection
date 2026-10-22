# Serialized business schemas, part 3: suspended buffs and companions

Date: 2026-10-22. Starting head: `1dfb7c5`. Scope: the suspended specialty buff gap found in
[part 2](SERIALIZED-SCHEMAS-PART2-20261018.md), one companion schema, and one companion
abilities schema. General combat state, news arguments, mail and the other categories are not
touched. No companion's or buff's effect, formula, text or duration changed.

## The suspended specialty buff

`suspend_buffs()` and `unsuspend_buffs()` (`lib/battle-skills.php:133`, `:204`) write
`suspended` as the int `1` or `0`, exactly as 1.1.2 did (`lib/battle-skills.php:130`, `:201`
at `historical-source-1.1.2`). Training calls them with `allowintrain`
(`lib/training_combat.php:143`, `:150`) and PvP with `allowinpvp` (`battle.php:113`, `:496`).
No bundled specialty buff carries either flag, so every active specialty buff is suspended on
those two routes. At `1dfb7c5` the specialty buffs were checked by their producer-exact schema
(`SpecialtyBuffState`), which allows `suspended` only as a bool.

Proved over HTTP before the fix (`test_suspended_specialty_buff_training_and_pvp_http`): a real
Thieving Skills Insult cast in the forest, still active with 4 rounds, then a master challenge:
**409 "Training unavailable. Stored state was preserved."**, nothing written. The entry page
itself suspends the buff and the route's own check then refuses the value it just wrote, so a
player with an active specialty buff could not train and could not fight in PvP until the buff
ran out in the forest. Graveyard and the forest do not suspend buffs; graveyard suspends only
companions.

The fix is in the buff schema, not in `suspend_buffs()`: for a specialty buff,
`BuffState::buff()` turns `suspended` `1` or `0` into `true` or `false` once, before the exact
check (`src/Game/BuffState.php:89`). `true` and `false` pass unchanged. Any other value (`2`,
`'1'`, `null`) is still refused by `SpecialtyBuffState`. Everything else about the specialty
schema is unchanged; `SpecialtyBuffStateTest` still pins bool-only for the producer schema
itself. With the fix, over HTTP: training entry 200 with the buff stored `suspended => true`,
a training round where the buff does not act, the victory restores it as `false`, and a later
forest round shows the Insult acting with its rounds going from 4 to 3. PvP entry, a round
and the victory behave the same. A stored 1.1.2 int `1` or `0` reads and is stored as a bool.

Companions: `suspend_companions()` and `unsuspend_companions()`
(`lib/extended-battle.php:95`, `:132`) write `true` and `false`, so there is no int case. The
companion schema accepts `true`, `false`, `0`, `1`, `'0'` and `'1'` for every flag anyway,
because the mercenary camp stores the database's text flags.

`calculate_buff_fields()` (`lib/buffs.php:17`) now runs `BuffState::collection()` over the
whole list as a check before any field is evaluated. It replaces
`SpecialtyBuffState::collection()`, which checked the specialty buffs with the producer schema
alone and so refused the `1` that `suspend_buffs()` had just written in the same request: the
schema fix alone still left training at 409 (revert R8 below). It is a check only: the list is not rewritten there, because the function itself adds
the `tempstats_calculated` and `fields_calculated` markers and the normalized list is already
what hydration and `apply_buff()` produced.

## The healer companion

Found while testing the mercenary camp, not in scope as written but inside "hiring a
mercenary and fighting with it": at `1dfb7c5`, hiring the installer healer Florenz and then
fighting while hurt gave a **500**. `report_companion_move()` reads `healmsg`,
`healcompanionmsg` and `magicfailmsg` without `isset()` (`lib/extended-battle.php:233`, `:252`,
`:281`, `:343`, unchanged from 1.1.2) and falls back to its default text when the value is
empty. The `companions` table has no such columns, so a hired mercenary never has them. In
PHP 5 the missing key was a notice and 1.1.2 printed the default; the modern error handler
turns the warning into an exception. The companion schema now adds the three messages as `''`
when missing (`src/Game/CompanionState.php:103`), so the default text is shown as in 1.1.2:
"Florenz heals your wounds. You regenerate 2 hitpoints." No battle code changed.

## Shipped companion shapes

Every companion the shipped code and the 1.1.2 installer write, with file and line at this
commit:

| Producer | Where | Shape |
|---|---|---|
| Installer Mortimer | `lib/installer/installer_sqlstatements.php:871` | `companions` row; numbers and flags as database text; abilities `fight "1"`, `heal "0"`, `magic "0"`, `defend false` |
| Installer Florenz | `lib/installer/installer_sqlstatements.php:872` | As Mortimer; abilities `heal "2"`, `magic "0"`, `fight false`, `defend false` |
| Dwarf racial Grizzly Bear | `modules/racedwarf.php:46` | As Mortimer, location Qexelcrag; abilities `fight "0"`, `heal "0"`, `magic "0"`, `defend "1"` |
| Mercenary camp hire | `mercenarycamp.php:207`-`221` | `SELECT *` row as text, attack, defense and max hit points scaled by level, `hitpoints` set to max, the `alter-companion` hook, abilities read from the row |
| Companion editor abilities | `lib/mount_companion_editor.php:146`-`157` | `fight`, `defend` as `'1'` or `false`, `heal`, `magic` `'0'` to `'30'`, stored as 1.1.2 stored them |
| Companion editor Take | `lib/mount_companion_editor.php:375`-`392` | The camp's formulas on the editor's row |
| Dark Arts skeleton warrior | `modules/specialtydarkarts.php:170`-`181` | Float hit points from `round()`, attack such as `26.5`, abilities `fight => true`, `ignorelimit => true`; `apply_companion()` with its third argument `true` |
| `apply_companion()` | `lib/buffs.php:127`-`130` | Adds `ignorelimit => true` when called with the third argument |
| Battle | `lib/extended-battle.php:69` | `used => false` |
| Battle | `lib/extended-battle.php:95`, `:132` | `suspended => true`, `suspended => false` |
| Battle | `lib/extended-battle.php:223`-`362` | `used => true`, `hitpoints` changes (float possible; `0` only with `cannotdie`) |
| Healing aura | `lib/battle-buffs.php:150`-`178` | `hitpoints` raised, at most to max |
| Training level-up | `lib/training_outcomes.php:46`-`49` | `attack`, `defense`, `maxhitpoints` raised by the per-level values |
| Healer, camp heal | `healer.php:90`, `mercenarycamp.php:190` | `hitpoints` set to max |
| Dragon kill | `lib/dragon_outcomes.php:217`-`218` | List reset to `[]` |
| New account | `lib/accounts.php:28` | `[]` |

## The schema

`src/Game/CompanionState.php` replaces `ForestCompanionState` (removed) and the
skeleton-only list check `SkeletonCompanionState::companions()` (removed;
`SkeletonCompanionState::validate()` stays as the skeleton's producer schema).

- **List** (`collection()`): an array of at most 100 companions. The key is the companion's
  name as `apply_companion()` stores it: int or string, not empty, at most 255 bytes (the
  `companions.name` column). PHP stores a name such as `"12"` as an int key, so both pass.
- **The skeleton warrior**: under the key `skeleton_warrior` the exact producer schema applies
  unchanged (creation formula, damage, runtime flags as bools), and the value is returned as
  stored.
- **Any other companion** (`companion()`):
  - keys only from the vocabulary combat, the character stats and the camps read: the
    numbers `hitpoints`, `maxhitpoints`, `attack`, `defense`, `companionid`, the three
    per-level values and the three costs; the texts `name`, `category`, `description`,
    `companionlocation`, `jointext`, `dyingtext`, `schema` and the five battle messages; the
    flags `used`, `suspended`, `ignorelimit`, `cannotdie`, `cannotbehealed`,
    `companionactive`, `allowinshades`, `allowinpvp`, `allowintrain`, `expireafterfight`; and
    `abilities`;
  - required: `name`, `hitpoints`, `maxhitpoints`, `attack`, `defense`, `abilities`;
  - numbers: int or float, finite, from 0 to 2147483647; plain decimal text becomes int or
    float;
  - texts: strings of at most 8192 bytes (the editor's longest field); the name 1 to 255
    bytes;
  - flags: `true`, `false`, `0`, `1`, `'0'`, `'1'`; text becomes int;
  - health: `maxhitpoints` above 0, `hitpoints` at most `maxhitpoints`, `hitpoints` 0 only
    with `cannotdie` (the battle removes any other companion at 0);
  - `healmsg`, `healcompanionmsg` and `magicfailmsg` default to `''`.
- **Abilities** (`abilities()`): an array whose keys are only `fight`, `defend` (flags, as
  above) and `heal`, `magic` (whole numbers 0 to 30; text such as `"2"` becomes int). Any
  subset passes: the skeleton has only `fight`. The editor offers 0 to 30, as 1.1.2's did.
- **Stored value** (`read()`): NULL or `''` is no companions. Anything else must unserialize
  through `ScalarState` (no objects) to a valid list.
- **Writer** (`encode()`): serializes only a valid, normalized list of at most 65535 bytes
  (the TEXT column).

Normalization is idempotent: a normalized list passes unchanged. A 1.1.2 or camp-stored list is
rewritten once, on the player's next save, with numbers and flags as numbers and the three
messages present.

### Where it is used

- Page load (`common.php:349`): `CompanionState::read()`; a failure gives 409 "Invalid stored
  companion state. No game action was completed." before anything is written.
- Session start (`lib/forcednavigation.php:41`): the same read, the same 409.
- `apply_companion()` (`lib/buffs.php:104`): checks the new companion, builds the resulting
  list, and stores only `CompanionState::encode()`; a refused companion throws
  `DomainException` before anything changes.
- Writers: `lib/saveuser.php:28`, `lib/player_mutation.php:72` and the battle round
  (`battle.php:523`, which used `createstring()`) store only `CompanionState::encode()`.
- Combat routes: forest (`lib/forest_combat.php:16`), training (`lib/training_combat.php:45`),
  PvP (`lib/pvp_combat.php:48`), graveyard (`lib/graveyard_combat.php:15`, `:16`, `:124`),
  Ramius (`lib/ramius_resurrection.php:48`, `:49`), Dragon (`lib/dragon_combat.php:21`), the
  specialty routes (`lib/specialty_combat.php:15`) and New Day (`lib/newday_daily.php:89`)
  check the stored row with `read()` where they already checked it, and the hydrated list with
  `collection()`, each inside its existing 409 or rollback.
- Abilities: the mercenary camp hire (`mercenarycamp.php:220`; refused gives 500 "This
  companion cannot be hired. Nothing was changed."), the companion editor's Take
  (`lib/mount_companion_editor.php:388`, 500 and rollback), the editor's save
  (`lib/mount_companion_editor.php:155`, 400, nothing saved; the stored shape stays 1.1.2's)
  and the editor display (`companions.php:161`; a refused value is shown empty for repair).

## 1.1.2 comparison

- **Non-array stored values.** 1.1.2 used `@unserialize()` on the column and turned anything
  that was not an array into no companions, dropping non-array entries
  (`common.php:335`-`343`). Its only non-array value was the empty column: `create.php` never
  set `companions`, and every 1.1.2 writer serialized an array (`lib/saveuser.php:17`,
  `lib/buffs.php:178`, `battle.php:513`, `dragon.php:227`-`228`). Decision: the empty column and
  NULL still read as no companions; any other value that is not a valid list stops the request
  with 409, as the closed buff, navigation and preference categories do, instead of silently
  dropping the player's companions.
- **Text numbers and flags.** 1.1.2 stored the camp's database text and PHP 5 arithmetic
  coerced it. Text passes and is normalized once, so combat computes with the same values.
- **Missing battle messages.** 1.1.2 read a missing `healmsg`, `healcompanionmsg` or
  `magicfailmsg` as empty and printed the default; the schema supplies `''`, with the same
  result.
- **Abilities.** 1.1.2's camp applied `@unserialize()` abilities unchecked
  (`mercenarycamp.php:215`); a broken value hired a companion with `abilities => false`. Now
  the hire stops with nothing charged or stored.
- **Suspended buffs.** 1.1.2 stored `suspended` as `1`/`0` and had no schema; the schema now
  accepts those values and stores bools.

## Inventory

Serialized-state source changed, so the inventory was recounted with part 1's token method,
which reproduces 97 / 50 / 38 / 58 / 1 at `1dfb7c5`. After this commit (working tree, new
files included): **93 sites in 49 files: 37 serialize writers/checks, 55 ScalarState reads, 1
centralized unserialize**. The per-site status of the 11 companion sites, all now CLOSED, is in
`SERIALIZED-STATE-AUDIT.md`, section "Companions and companion abilities, part 3 candidate,
2026-10-22".

## Tests

- `tests/unit/CompanionStateTest.php` (new, 5 tests):
  - the shipped mercenaries as the camp stores them at levels 1 and 10: the installer's
    Mortimer and Florenz and the dwarf's Grizzly Bear, each abilities string checked against
    its source file, normalized and idempotent;
  - the editor's abilities, the Take shape, the runtime flags and float hit points;
  - the empty column;
  - abilities: every editor level, subsets, text and int forms;
  - malformed: unknown keys, missing statistics, negative, non-finite and out-of-range
    numbers, impossible health, wrong types, oversized text and names, bad abilities, more
    than 100 companions, malformed stored values and objects.
- `tests/unit/BuffStateTest.php`: new `testSuspendedSpecialtyBuffsPassAndNormalize`.
- `tests/unit/ForestCombatStateTest.php`, `tests/unit/SkeletonCompanionStateTest.php`: the cases
  that used `ForestCompanionState` and `SkeletonCompanionState::companions()` now use
  `CompanionState::collection()`, with the same inputs and expectations.
- `test_suspended_specialty_buff_training_and_pvp_http` (new): the case above, over training
  and PvP, plus the 1.1.2 int flags.
- `test_mercenary_companions_fight_and_malformed_companions_http` (new): hire Florenz at level
  10 (charged, stored normalized) and fight hurt ("Florenz heals your wounds. You regenerate 2
  hitpoints."); hire Mortimer and fight; a stored 1.1.2 Florenz with text columns and text
  abilities reads and is stored normalized on the next page; 8 malformed stored abilities each
  stop the hire with 500 and nothing charged or stored; a Dark Arts skeleton raised in the
  forest fights an ordinary round and keeps its exact form; 18 malformed stored lists each give
  409 on `village.php`, `forest.php?op=fight` and `mercenarycamp.php` with the account row and
  its output unchanged; the empty column reads as none.
- `test_mount_and_companion_editor_authority_http` (changed): Take now stores the normalized
  abilities (`{'fight': 1, 'heal': 3, 'magic': 0, 'defend': False}` instead of text); 4
  malformed stored abilities each stop Take with 500 and the player's list unchanged, and the
  edit page still renders with Fighter unchecked.
- `test_graveyard_companion_suspension_and_skeleton_exclusion` (changed fixture, same
  assertions): its hand-written helper companion is now stored with the three battle messages
  as `''`, the shape the schema stores. Without them the first page view normalized the list
  and the test's "nothing changed" snapshot saw the added keys. Found by the local shard run.

### Before the fix

Each production change was reverted on its own in a copy of the tree, with the tests above
unchanged, against a fresh MySQL 8.4 database (PHPUnit, then the three web tests that cover
this work):

| Revert | Result |
|---|---|
| R0 every changed production file as at `1dfb7c5` (`CompanionState` removed, the old schemas back) | PHPUnit: 9 errors (the new and changed unit tests need `CompanionState`; the suspended buff test). Web: all three fail. Training entry with the active Insult: 409 "Training unavailable". The hired Florenz is stored as the raw row, without the normalized fields. Take stores text abilities (`'1'`, `'3'`, `'0'`) |
| R1 `src/Game/BuffState.php` as at `1dfb7c5` | PHPUnit: 1 error, `testSuspendedSpecialtyBuffsPassAndNormalize` ("Invalid specialty flag"). Web: 1 fails, training entry 409 |
| R2 the three battle message defaults removed from `CompanionState` | PHPUnit: 1 failure (`CompanionStateTest`). Web: 1 fails, the hired Florenz has no `healmsg`. The fight it guards was a 500 at `1dfb7c5` (above) |
| R3 page load and session start as at `1dfb7c5` (`common.php`, `lib/forcednavigation.php`) | Web: 11 of the 18 malformed lists reach the page. Ten render and then fail in the save, which now refuses them (an uncaught `DomainException` from `CompanionState::encode()`, nothing stored); one (a name that is an array) is a `TypeError` in the character stats. The other 7 are refused by the old skeleton-only check |
| R4 `mercenarycamp.php` as at `1dfb7c5` | Web: the 8 malformed abilities each fail. `apply_companion()` still refuses them, but as an uncaught `DomainException` page instead of the camp's 500 "Nothing was changed" |
| R5 `lib/mount_companion_editor.php` as at `1dfb7c5` | No failure. `apply_companion()` refuses the same abilities inside Take's existing try (500, rollback). R9 shows Take without either |
| R6 the buff check in `calculate_buff_fields()` removed | No failure. Every entry already checks the list; the check is defense in depth |
| R7 the writers as at `1dfb7c5` (`battle.php`, `lib/saveuser.php`, `lib/player_mutation.php`) | No failure. Every list reaching them was checked and normalized at page load or in `apply_companion()`; `CompanionStateTest` covers `encode()` |
| R8 `lib/buffs.php` as at `1dfb7c5` (`apply_companion()` and the specialty-only guard in `calculate_buff_fields()`) | Web: 1 fails, training entry 409: the old guard refuses the int flag in the same request. The mercenary cases pass, normalized by page load and the save |
| R9 `lib/buffs.php` and `lib/mount_companion_editor.php` as at `1dfb7c5` | Web: 2 fail, training entry as R8, and a Take with malformed stored abilities is accepted (the next case gets 409 "This changed" instead of 500) |

With the fix: PHPUnit 192/192 on MySQL 8.4; the three web tests pass in about 90 s together.

## Local verification

Supporting evidence only; CI is the acceptance.

- All four shards, because buffs and companions are read on every page and every combat route
  and the shared save and player-mutation helpers changed, side by side on fresh MySQL 8.4
  databases with PHP 8.5, PHPUnit first (192/192 on each), 0 failures, errors or skips in the
  final runs: shard 1 ran 51 tests in 852 s, shard 2 42 in 882 s, shard 3 41 in 902 s, shard 4
  42 in 906 s. The new web tests took 16.0 s (suspended buff) and 30.3 s (mercenaries); the
  changed editor test 40.2 s.
- The first shard 1 run found one problem, fixed before the final run: the graveyard
  companion test's hand-written fixture was stored without the three battle messages, so the
  first page view normalized it and the test's "nothing changed" snapshot differed (see Tests).
  Shards 2 to 4 passed on their first run; the fix touches only that shard 1 test.
- `composer validate --strict`, `composer audit` (no advisories), `composer lint` (381 files,
  0 failures), `composer analyse` and `phpstan-new.neon` (no errors),
  `scripts/verify-historical-baseline.py` (PASS: 417 files, 11 commits),
  `scripts/validate-project.py`, `tests/tooling/test_tooling_shards.py`, `git diff --check`.

## Remaining gaps

- **Lockout bar.** As in parts 1 and 2, a stored list that fails the schema stops every page
  for that player until it is repaired; the user editor shows `companions` view-only. All
  shipped writers now store only valid lists. The admin repair path is a Final rescan item in
  `ROADMAP.md`.
- **Login** does not check companions (it checks navigation, preferences, buffs and dragon
  points); the first page after login does, with the same 409.
- **Mercenary camp route authority**, found here and not changed: hire and heal are GET
  mutations guarded only by allowed navigation; hire pastes `id` into SQL, the list query
  pastes the session location and dragon kills, and heal takes `cost` from the URL. Added to
  the Final rescan in `ROADMAP.md`.
- A companion that a module names `skeleton_warrior` gets the skeleton's exact rules. No shipped
  module does.
- Healer and magician levels are capped at 30, the editor's range. A module that writes a
  higher level is refused.

## Acceptance, 2026-10-22

Inspected once on 2026-10-22 for `49baab9cc6493117b3eb10da0a4a94062bfece97`:

- Modern core 39704754364: SUCCESS, all 12 jobs (per leg one `checks`, four `http` shards
  and one `result`, on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4). The run started at
  01:16:47Z on 2026-10-22; the two `result` jobs finished at 01:33:33Z and 01:35:35Z.
- Baseline integrity 39704754291: SUCCESS.

The suspended specialty buffs, the companion schema and the companion abilities schema are Done
at `49baab9`. General combat state is taken up by
[serialized schemas part 4](SERIALIZED-SCHEMAS-PART4-20261022.md).
