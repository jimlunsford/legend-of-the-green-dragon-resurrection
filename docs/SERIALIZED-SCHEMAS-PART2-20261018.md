# Serialized business schemas, part 2: buffs and mount buffs

Date: 2026-10-18. Starting head: `fd548a3`. Scope: the three buff items that
[part 1](SERIALIZED-SCHEMAS-PART1-20261017.md) split out of "Remaining serialized business
schemas": combat buff compatibility (a defect), the buff-list schema, and the mount buff schema.
Companions, general combat state, news arguments and the other categories are not touched. No
buff's effect, formula, text or duration changed.

## The regression

At `fd548a3` the combat routes checked the buff list with `ForestBuffState::validate()`, which
required int `rounds`, PHP numbers for every statistic, a fixed key list without `activate`,
and plain-string messages. The 1.1.2 installer mounts, the mount editor, the drinks table, the
lovers and Crazy Audrey all write buffs outside that shape, and New Day had a second, different
rule (`NewDayState::buffs()`). Proved over HTTP before any fix, on MySQL 8.4 with the
installer data:

- Buy the installer Pony (mount 1) at the stables, then fight in the forest: **409 "Forest
  combat unavailable"** (`test_shipped_buff_shapes_fight_and_malformed_buff_list_http`, first
  failing assertion at `fd548a3`). An owner of an installer mount could not fight while the
  mount buff was active, and New Day re-applied it every day. Training, PvP and the graveyard
  ran the same check (from the code; the forest was proved over HTTP).
- A drinks buzz (`rounds` is text from the `drinks` table) was refused by both combat
  ("Invalid combat duration") and New Day, so the player could not fight and could not start
  a new day.
- The mount editor's own text fields were refused by combat; a text `minioncount` was refused
  by New Day too.

Shape probe at `fd548a3` (each shape as its writer stores it, through the two old checks):

| Shape | Old combat check | Old New Day check |
|---|---|---|
| Installer Pony, Gelding, Stallion | Invalid combat buff fields (`activate`) | accepted |
| Mount editor text fields | Invalid combat duration (text `rounds`) | accepted |
| Mount editor text minions | Invalid combat duration | Invalid minion count |
| Lover | Invalid combat message (wear-off tuple) | accepted |
| Crazy Audrey | Invalid combat buff fields (`activate`) | accepted |
| Drinks Ale, Hot Hands, Mulekick | Invalid combat duration | Invalid combat duration |
| Elf and troll racials, PvP bodyguard, god mode, specialty, transmutation, a carried buff | accepted | accepted |

With the fix every row is accepted and normalized (the probe prints int `rounds` for each).

## Shipped buff shapes

Every buff the shipped code and the 1.1.2 installer can write, with file and line at this
commit. "Text" means the value arrives as a PHP string (a database column or a form field).

| Writer | Buff | Shape |
|---|---|---|
| `lib/installer/installer_sqlstatements.php:765`–`767` | installer Pony, Gelding, Stallion (`mounts.mountbuff`) | `name`, `roundmsg`, text `rounds` ("20", "40", "60"), text `atkmod` ("1.2"), `activate` "offense"; no `schema` |
| `lib/mount_companion_editor.php:122`–`130` (`mounts.php` save and give) | any mount | every non-empty field of the form as text (`rounds`, every modifier, `minioncount`, the flags `invulnerable`, `allowinpvp`, `allowintrain`, ...), `schema` "mounts" |
| `stables.php:185` (purchase), `:257` (feed) | `mount` | the stored mount buff, schema "mounts" when it names none |
| `lib/newday_daily_outcomes.php:76`–`79`, `lib/ramius_outcomes.php:76`–`79` | `mount` | as the stables |
| `lib/newday_daily_outcomes.php:54`, `lib/ramius_outcomes.php:54` | any buff with `survivenewday` 1 | the hydrated buff, carried with its flags (`used`, `fields_calculated`) |
| `modules/drinks/security.php:44`–`48`, data `modules/drinks/install.php:38`–`40` | `buzz` | text `rounds` and modifiers from the `drinks` row ("10", "1.25", ".9"), the messages that are non-empty, schema "module-drinks" |
| `modules/lovers.php:178`–`189`, applied `modules/lovers/lovers_violet.php:29`, `:130`, `lovers_seth.php:32`, `:122` | `lover` | int `rounds` 60, `defmod` 1.2, `wearoff` the tuple `["`!You miss %s`!.`0", partner]` |
| `modules/crazyaudrey.php:255` | `crazyaudrey` | int `rounds` 5, `activate` "defense", `defmod` 1.05 |
| `modules/raceelf.php:117`, `modules/racetroll.php:129` | `racialbenefit` | an expression (`<defense>`, `<attack>`, `<level>`) in `defmod`/`atkmod`, `rounds` -1, `allowinpvp`, `allowintrain` |
| `lib/battle-skills.php:251`, `:269` | `bodyguard`, `godmode` | int and float modifiers, `invulnerable`, `expireafterfight`, `allowinpvp` |
| `lib/battle-skills.php:133`, `:157`, `:176`, `:204` | any | `suspended` 1 or 0 (int) |
| `lib/battle-buffs.php:77`, `:323` | any | `used` 1 or 0 (int) |
| `lib/buffs.php:27`, `:39`, `:41`, `:54` | any | `tempstats_calculated` and `fields_calculated` true; evaluated expression fields, restored to text |
| `modules/specialtydarkarts.php:185`–`235`, `specialtymysticpower.php:171`–`227`, `specialtythiefskills.php:168`–`215` | `da*`, `mp*`, `ts*` | producer-exact, `SpecialtyBuffState` |
| `modules/cedrikspotions.php:361` | `transmute` | producer-exact, `TransmutationState` |
| `lib/accounts.php:27` | new account | `[]` |

1.1.2 itself stored the same text numbers (`mounts.php:85`–`92` kept every posted field as
text; `modules/drinks/run.php:114` copied the row), and PDO returns them as text.

## The schema

`src/Game/BuffState.php` is now the one buff schema; `ForestBuffState` and
`NewDayState::buffs()` are gone.

- `read()`: the stored column. NULL or `''` is no buffs; anything else must read through
  `ScalarState::read()` as a valid list.
- `collection()`: an array of at most 100 buffs, each under a name of 1 to 128 bytes.
- `buff()`: one buff.
  - A bundled specialty buff (`da*`, `mp*`, `ts*` or a specialty schema) keeps its exact
    `SpecialtyBuffState` rule, and `transmute` its `TransmutationState` rule, unchanged.
  - Every other key must be in the vocabulary combat reads: 19 numbers (the modifiers,
    `lifetap`, `damageshield`, `regen`, `minioncount`, the damage ranges, the companion
    modifiers, the three `tempstat-` fields), 15 yes/no flags, 10 texts, `rounds`, `activate`.
    An unknown key is refused because `calculate_buff_fields()` would evaluate any text field
    holding an expression.
  - `rounds`: -1 or 1 to 100000; text digits become int.
  - Numbers: text in plain decimal form becomes int (whole) or float; an expression stays text
    once it evaluates for this player through `Expression`'s shipped vocabulary; the value must
    be finite and within ±2147483647; the multipliers and counts are never negative;
    `minioncount` is a whole number up to 1000.
  - Flags: true, false, 0, 1, "0" or "1"; text becomes int.
  - Texts: strings up to 4096 bytes; the lover's wear-off may be the exact translation tuple.
  - `activate`: a comma list of `roundstart`, `offense`, `defense` (1.1.2 metadata; the battle
    ignores it).
  - A missing `name` or `schema` becomes `''`, as 1.1.2 read it; charstats and the battle read
    both on every buff and a missing key is now a warning, which the error handler turns into
    a 500.
- `mount()`: the same schema with the mount default, `schema` "mounts" when the buff names
  none.
- `encode()`: the value a writer stores: `collection()` serialized, at most 65535 bytes.

Refused: what no shipped writer produces: an unknown key, a non-finite or out-of-range number,
an exponent or other non-decimal text number, an expression outside the shipped vocabulary, a
wrong type that would reach a formula, text over 4096 bytes, more than 100 buffs, a list too
large for the column, and any stored value other than the empty column that is not a list.

### Where it is used

| Site | Use | On failure |
|---|---|---|
| `common.php:257`–`269` | page load: `read()` after the transmute check | 409 "Invalid stored buff state. No game action was completed.", nothing written |
| `lib/forcednavigation.php:27`–`37` | session start: the same | 409, nothing written |
| `login.php:57`–`58` | login: `read()` before the login is recorded | 409 "Invalid stored buff state. You were not logged in." |
| `lib/forest_combat.php`, `lib/training_combat.php`, `lib/pvp_combat.php`, `lib/graveyard_combat.php` | each route's existing check: the stored list (`read()`) and the session list (`collection()`) | the route's existing 409, nothing written |
| `lib/ramius_resurrection.php:93` | `collection()` | the route's existing refusal |
| `lib/newday_daily.php` | the stored list (`read()`) and the owned mount (`mount()`), before any write | New Day's existing 409 |
| `lib/buffs.php:93` `apply_buff()` | every buff applied, after the `modify-buff` hook | DomainException, the caller's transaction rolls back |
| `lib/saveuser.php`, `lib/player_mutation.php`, `lib/specialty_combat.php` | every stored list goes through `encode()` | the save throws; nothing is written |
| `lib/stables_authority.php:35` | purchase and feed: `mount()` | 500 "Nothing was changed", before anything is charged |
| `lib/mount_companion_editor.php:121`–`138` | mount editor save: the built buff must pass `mount()` | 400, nothing saved |
| `mounts.php:235`–`240` | mount editor display: a refused stored buff is shown empty for repair | display only |

The New Day result is covered without a second rule: New Day re-applies each carried buff and
the mount through `apply_buff()`, and the save encodes the list.

## 1.1.2 comparison

- **Non-array buff lists.** 1.1.2 turned any stored value that was not an array into no buffs
  (`common.php:244`–`248`, `login.php:61`–`63`). Its only such value was the empty column:
  `create.php` never set `bufflist`, and `newday.php:147` blanked it before the save wrote the
  new list (`lib/saveuser.php:16` always serialized an array). The empty column (and NULL)
  still reads as no buffs. Any other non-array value now stops the request with 409 instead of
  silently dropping the player's buffs, matching the other closed categories.
- **Text numbers.** 1.1.2 stored and used text numbers (PHP 5 arithmetic coerced them). They
  pass and are normalized once, so combat sees the same values 1.1.2 computed with.
- **The stables purchase schema.** 1.1.2's purchase computed the "mounts" schema and then
  applied a fresh `unserialize()` of the row (`stables.php:173`–`175`), so a purchased mount
  buff took the page's translation namespace while feed and New Day used "mounts". The
  purchase now uses "mounts" as well, the mount buff schema's one default. Only the translation
  namespace of the buff's messages differs; the effect is the same.
- **Stored representation.** A list is stored normalized (int `rounds`, numeric modifiers), so
  a buff written as text by 1.1.2 is stored as numbers after the player's next save. 1.1.2's
  own reads coerced both forms the same way.

## Inventory

Serialized-state source changed, so the inventory was recounted with part 1's token method,
which reproduces 103 / 50 / 40 / 62 / 1 at `fd548a3`. After this commit (working tree, new
files included): **97 sites in 50 files: 38 serialize writers/checks, 58 ScalarState reads, 1
centralized unserialize**. The per-site status of the 12 buff and 7 mount sites, all now
CLOSED, is in `SERIALIZED-STATE-AUDIT.md`, section "Buffs and mount buffs, part 2 candidate,
2026-10-18".

## Tests

- `tests/unit/BuffStateTest.php` (new, 7 tests):
  - the three installer mounts, each string checked against the installer file, normalized
    and idempotent;
  - the mount editor's text fields;
  - the lover, Crazy Audrey, the elf and troll racials, the bodyguard, god mode and a carried
    buff;
  - the drinks Ale, Hot Hands and Mulekick as the drinks table stores them;
  - specialty and transmutation buffs keep their producer-exact schemas;
  - the empty column;
  - 43 malformed single fields, a missing duration, 6 malformed lover messages, bad names, a
    non-array buff, 101 buffs, 8 malformed stored values, non-list values, an oversize list.
- `tests/unit/ForestCombatStateTest.php`, `tests/unit/NewDayStateTest.php`: the cases that used
  `ForestBuffState` and `NewDayState::buffs()` now use `BuffState::collection()`, with the same
  inputs and expectations.
- `tests/tooling/test_web_application.py::test_shipped_buff_shapes_fight_and_malformed_buff_list_http`
  (new): buys the installer Pony at the stables and fights with it ("Your pony fights with
  you!"), the mount normalized; a mount whose buff carries an unknown key is refused at the
  stables (500) with nothing written; the lover, the three drinks, Crazy Audrey and the mount
  editor's text minions each fight, `rounds` counting down as an int; 12 malformed stored lists
  each give 409 on `village.php` and on `forest.php?op=fight` with the account and its output
  unchanged; the empty column renders the village; a malformed list stops a login (409) before
  it is recorded.
- `tests/tooling/test_web_application.py::test_normal_newday_keeps_installer_mount_buff_http`
  (new): a stored text Pony buff and a text buzz; New Day keeps only the mount, refreshed to
  20 rounds at 1.2.
- `test_mount_and_companion_editor_authority_http` (changed): the editor case that expected a
  text `minioncount` "1" to be refused now uses "1.5" and "-1" (the mount editor's own text "1"
  is a valid 1.1.2 shape); the stables purchase now stores the normalized buff
  (`('`&Probe Attack', 1.3, 15)` instead of text).
- `test_normal_newday_extension_hooks_and_stale_sources` (changed): New Day still refreshes the
  installer mount to 20 rounds; the stored value is now the int `20` instead of the installer's
  text `'20'`, because the list is stored normalized. Found by the local shard run.

### Before the fix

Each production change was reverted on its own, with the tests above unchanged, against a fresh
MySQL 8.4 database (PHPUnit, then the three web tests):

| Revert | Result |
|---|---|
| R0 every changed production file as at `fd548a3` | PHPUnit: 8 errors (the new and changed unit tests need `BuffState`). Web: all three fail. The shipped-shapes test: a mount buff with an unknown key is sold (200 instead of 500). The regression proof, run at `fd548a3` before that case was added: the bought installer Pony's first fight gives 409 "Forest combat unavailable". New Day with the text Pony and buzz: 409. The editor test: the stables store the text buff (`'1.3'`, `'15'`) |
| R1 forest combat with the `fd548a3` check (`ForestBuffState`) | 1 fails: the bought Pony's fight, 409 "Forest combat unavailable" |
| R2 page load hydrated as at `fd548a3` (no schema in `common.php` or `lib/forcednavigation.php`) | 1 test fails, 4 subtests and 1 error: the three drinks and the editor's text minions fight with a 409 "Expired or repeated action", and the mount assertion has no mount. The text values reach the page unnormalized, so the fight form's context and the action see different values (inferred from the code); the malformed-list cases are not reached |
| R3 `login.php` as at `fd548a3` | 1 fails: the malformed list logs in (303 instead of 409) |
| R4 the stables mount buff read as at `fd548a3` (`is_array` only) | 2 fail: the mount with an unknown key reaches `apply_buff()` (409 instead of the stables' 500, nothing written either way), and the mount editor, which now checks through the same function, refuses a valid mount (400) |
| R5 New Day's sources with the `fd548a3` check (`NewDayState::buffs()`) | 1 fails: New Day with the text Pony and buzz, 409 |

With the fix: PHPUnit 186/186 on MySQL 8.4; the three web tests pass in about 75 s together.

## Local verification

Supporting evidence only; CI is the acceptance.

- All four shards, because buffs are read on every page and every combat route and the
  shared save and player-mutation helpers changed, side by side on fresh MySQL 8.4 databases
  with PHP 8.5, 0 failures, errors or skips: shard 1 ran 42 tests in 818 s, shard 2 48 in
  860 s, shard 3 41 in 861 s, shard 4 43 in 936 s. The new web tests took 15.4 s (shipped
  shapes) and 14.7 s (New Day mount).
- The shard runs found two problems, both fixed before the final run:
  - `test_normal_newday_extension_hooks_and_stale_sources` expected the installer's text
    `'20'` for the stored mount rounds; the list is now stored normalized (see Tests).
  - `test_transmutation_persistence_and_failure_http` (unchanged) failed with a fatal
    "Class TransmutationState not found" at `login.php`: a login with a transmutation buff
    reached the schema before the producer schemas were loaded. `BuffState.php` now loads
    `ScalarState`, `Expression`, `SpecialtyBuffState` and `TransmutationState` itself.
- PHPUnit 186/186 on each shard database.
- PHP lint: 380 files, 0 failures.
- Both PHPStan gates: no errors (`src/` is in the level-6 gate).
- `composer validate --strict`: valid. `composer audit`: no advisories.
- Historical verifier: PASS (417 files, 11 commits). Project validator: PASS. Shard tooling
  test: OK.
- `git diff --check`: clean.

## Remaining gaps

- **Specialty `suspended` as an int.** `suspend_buffs()` and `unsuspend_buffs()`
  (`lib/battle-skills.php:133`, `:157`, `:176`, `:204`) write `suspended` 1 or 0, while the
  exact specialty schema (`SpecialtyBuffState`, unchanged as the brief requires, and pinned by
  `SpecialtyBuffStateTest`) accepts only bool. A specialty buff that is still active when a
  training or PvP fight suspends it could then be refused on the next page. Found by reading
  the code and probed at unit level only; it belongs with the specialty rules, not this part.
- **Expressions outside the vocabulary.** A stored expression that `Expression` does not know
  is refused. No shipped writer produces one (the two racials are the only expressions), but a
  third-party module's buff would now stop the page.
- **`calculate_buff_fields()`** keeps its specialty-only collection guard; the full schema runs
  before it at every entry and in `apply_buff()`.
- **Lockout bar.** As in part 1, a stored list that fails the schema stops every page for that
  player until it is repaired; the user editor shows `bufflist` view-only. All shipped writers
  now store only valid lists, so such a value can only come from the database itself.

## Acceptance, 2026-10-22

Inspected once on 2026-10-22 for `1dfb7c50999eb988369c26199e948dbf29695d2d`:

- Modern core 39099465964: SUCCESS, all 12 jobs (per leg one `checks`, four `http` shards
  and one `result`, on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4). The run started at
  05:07:31Z on 2026-10-18; the two `result` jobs finished at 05:25:58Z and 05:26:49Z.
- Baseline integrity 39099465985: SUCCESS.

Combat buff compatibility, the buff-list schema and the mount buff schema are Done at
`1dfb7c5`. The suspended specialty buffs and the companions are taken up by
[serialized schemas part 3](SERIALIZED-SCHEMAS-PART3-20261022.md).
