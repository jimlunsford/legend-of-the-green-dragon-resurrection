# Content editors part 2: creatures, masters, and the news formatting fatal, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `6820b80a7c2b1e806fbfe0a871d72a2a9177e898`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Scope, two ROADMAP items in one commit:

- A. `creatures.php` and `masters.php` (both `SU_EDIT_CREATURES`), every op, including the
  creature object preference editor (`subop=module`).
- B. The news formatting fatal: `sprintf_translate()` in `lib/translator.php`, and the
  same call in `output_notl()` in `lib/output.php`.

Mounts, companions, forest and training fight logic and the rest of the translator were
only read and exercised through their accepted routes.

Files: `creatures.php`, `masters.php`, `lib/content_editor.php`, `lib/typed_editor.php`,
`lib/translator.php`, `lib/output.php`, `src/Compatibility/LegacySprintf.php` (new).

At the start, `creatures.php` differed from `historical-source-1.1.2` only by the
`each()` replacement (`resurrection_array_next()`); `masters.php` was byte-identical.

## Access

Both pages run `check_su_access(SU_EDIT_CREATURES)` before any op, as in 1.1.2. The test
covers every op of both pages (list, add and edit forms, search, save, delete, the module
preference save) for an anonymous client (redirect or 403), a player and a superuser
holding every flag except `SU_EDIT_CREATURES` and megauser (403), and `SU_EDIT_CREATURES`
(200 for each page). No row changes in any of those requests.

## Pattern

The part 1 helpers in `lib/content_editor.php` are reused: `op` from the page's list,
positive integer IDs, non-negative levels (400 otherwise); every write is a POST with CSRF
and a one-use intent bound to the row as shown (GET 405; replay, a second copy of a form,
or a row changed since it was shown is 409); fields validated before the intent is spent;
an unknown ID is 404 before any write; each write in `resurrection_player_mutation()` with
the row locked and compared again; all SQL bound. The lists' "Del" links are POST forms
with 1.1.2's confirmation; "Edit" links stay GET.

New: `resurrection_creature_save()` and `resurrection_master_save()` in
`lib/content_editor.php`; `resurrection_object_types()` in `lib/typed_editor.php` (mounts
and creatures share the object preference editor).

## 1.1.2 comparison

"Old" is `6820b80`, reproduced on a fresh local database unless marked inferred.

| # | Editor | 1.1.2 | Old (`6820b80`) | Now |
|---|---|---|---|---|
| 1 | Creatures | `del` was a GET link; `creatureid` pasted into `WHERE creatureid = '$id'` | A GET deleted; `creatureid=321' OR creaturename='x` ran as SQL and deleted 321 (reproduced) | POST form bound to the row; GET 405; ID a positive integer (400) |
| 2 | Creatures | `save` built SQL from every POST key starting `creature` (column injection, below) | Same (reproduced) | Only the form's declared fields; anything else 400 |
| 3 | Creatures | Stats from `creature_stats($lev)` for levels 1-17 overwrote the posted ones; level 18 (the form's maximum, "non-forest") had no table entry, so its save wrote no stats | Level 18 and level 0 saves: fatal page (`reset(null)`, reproduced) | Level 1-18; 1-17 take that level's stats exactly; 18 keeps the stored stats, as in 1.1.2. Level 0 (outside the form's range) is 400: deliberate correction |
| 4 | Creatures | Save failures printed `db_error()` and the raw SQL | A quote in a name: fatal page with the UPDATE text on it (reproduced) | Bound; generic error page, no SQL or driver text |
| 5 | Creatures | `subop=module` wrote every posted key as an object preference of any module name, with no check that the module declares it | Wrote `anything`/`evil` for the creature, then a fatal page (reproduced) | Shared object preference editor: installed module, declared `prefs-creatures` only, typed values, intent bound to the creature and the module's values. No shipped module declares `prefs-creatures`, so every save is refused (400) |
| 6 | Creatures | Search pasted `q` into four `LIKE` clauses (`addslashes`); the list printed fields through `output_notl("%s", ...)` | Same | Bound; list output unchanged (escaped as before) |
| 7 | Creatures | `creaturewin`/`creaturelose` are `%s` arguments in forest and graveyard combat, never formats | Same | Unchanged; a `%` in them is harmless, so no format check on creature texts (a forest fight with `Beaten 100% flat` is tested) |
| 8 | Creatures | Edit of an unknown or seeded creature with NULL texts | Unknown ID fatal (`creatures.php:213`); seeded creature 1 fatal (`htmlentities(null)` in `showform.php`) (reproduced) | Unknown 404; NULL texts shown as empty |
| 9 | Creatures | A creature with an empty name was accepted | Same; modern forest combat refuses an empty `creaturename` (`src/Game/ForestCombatState.php:64`, inferred) | Name required (400) |
| 10 | Masters | `del` GET link, ID cast `(int)`; `save` texts through `addslashes` | A GET deleted (reproduced) | POST form; bound |
| 11 | Masters | ID 0 adds with health `level*11` (12 at level 1), attack and defense `level*2` | Same | Same formulas (tested at levels 1 and 7) |
| 12 | Masters | An edit with another level changes only the level; the stats stay | Same (reproduced: level 2 to 5 kept health 22, attack 4) | Same (tested) |
| 13 | Masters | An unknown ID ran a no-op UPDATE and said "Master ... updated." | Same (reproduced) | 404 |
| 14 | Masters | Level any integer (0 gives health 0) | Same (reproduced) | 1 to 195225786 (health `level*11` must fit `int(11)`): deliberate correction. Training only picks masters at the player's level (1 and up), so a level below 1 was never fought |
| 15 | Masters | `creaturewin`/`creaturelose` reach `output()` in training; with one of the listed codes they are formatted, so a stray `%` warned and printed an empty speech | A stray `%` with a code is a fatal training page (inferred from `output_notl()`; now covered by the formatter fix) | Refused at save when it does not format (as taunts in part 1); a speech without codes is not formatted and keeps a lone `%` (tested) |
| 16 | Masters | Form values in single-quoted attributes with `ENT_COMPAT`, so `O'Reilly` was cut at the apostrophe | Same | `ENT_QUOTES`: deliberate correction |

The stat tables, formulas, form fields, texts, defaults and messages ("Creature saved!",
"Creature not saved!", "Creature deleted", "Master %s added.", "Master %s updated.",
"Master deleted.") are unchanged.

## Column injection finding

1.1.2's creature save concatenated every POST key that starts with `creature` into the
SQL as a column name, with its value in single quotes:

- `creatureaiscript` is shown in the form only to `SU_MEGAUSER` or `SU_RAW_SQL`, but any
  `SU_EDIT_CREATURES` user could post it. In 1.1.2 that column was `eval()`ed as PHP in
  forest combat, so the creature editor flag was a code execution grant. Reproduced on
  `6820b80`: a superuser with only `SU_EDIT_CREATURES` stored `evil()`. The modern combat
  code never evaluates it (`Resurrection\Game\CreatureAi` accepts only the bundled
  behaviour), but an unsupported value makes every fight with that creature fail.
- Posted stats (`creaturehealth`, `creatureexp`, ...) were overwritten on an UPDATE because
  the level's stats came later in the SET list (reproduced: `creatureexp=999999` stayed
  34); on an INSERT a duplicate column fails the statement.
- A key that is not a column (any text after `creature`) is pasted as SQL; reproduced as a
  fatal page.
- The posted hidden `creatureid` chose the row to update.

Now the save accepts exactly the form's fields (`creatureid`, `creaturename`,
`creatureweapon`, `creaturewin`, `creaturelose`, `creaturelevel`, `forest`, `graveyard`,
and `creatureaiscript` only for `SU_MEGAUSER` or `SU_RAW_SQL`); any other key is 400 and
nothing is written. The intent is bound to the ID and row the form showed, so a forged
`creatureid` has no intent (409). `creatureaiscript` must be empty or the supported bundled
behaviour; when the field is absent the stored value is kept. `createdby` is the editing
superuser's login on add, as in 1.1.2.

## Formatter finding (news formatting fatal)

Defeat news stores the taunt as a sub-format; `news.php` and `bio.php` format it through
`sprintf_translate()`. A stored text with a `%` that is not a valid directive made both
pages fatal on PHP 8. Reproduced before the fix with three stored defeat rows:
`news.php` failed with `ValueError: Unknown format specifier "y"` and `bio.php` with
`ArgumentCountError` in `lib/translator.php`.

PHP 5's `sprintf()` (5.2 to 5.6 share the parser in `ext/standard/formatted_print.c`;
measured with `php:5.6-cli`):

| Case | Example | PHP 5 | PHP 8 |
|---|---|---|---|
| A directive without an argument (a stray `%` before text, a trailing `%`, too few arguments) | `100% sure %s` with one argument | `false`, warning "Too few arguments" | `ArgumentCountError` |
| Argument number 0 or empty | `%0$s`, `%$s` | `false`, "Argument number must be greater than zero" | `ValueError` |
| Width or precision at or above `INT_MAX` | `%2147483648d` | `false`, "Width/Precision must be greater than zero and less than 2147483647" | `ValueError` |
| Unknown conversion character | `a %y b` with `Bob` | `a  b`: the directive is dropped and its argument is used up | `ValueError` |
| `%` at the end with an argument; `'` padding at the end; `%l` | `end 100%` with `x` | `end 100`, empty | `ValueError` |

`false` printed as an empty string, so a defeat line kept its text and lost the taunt.
1.1.2 also showed the warning on the page with a backtrace (`logd_error_handler()` calls
`output()`, so `sprintf_translate()`'s output buffer captured nothing and `debug()` was not
called).

`Resurrection\Compatibility\LegacySprintf::format()` calls `vsprintf()` first, so every
format PHP 8 accepts gives PHP 8's result unchanged. Only when PHP 8 throws does it re-read
the format with PHP 5's parser: it returns `false` where PHP 5 did, or formats the
equivalent explicit format (every directive PHP 5 would format numbered, every directive it
dropped removed). The warning is returned to the caller, not printed (the modern stack
never prints PHP error text). `sprintf_translate()` and `output_notl()` use it; the output
buffer and `debug()` capture are unchanged.

Differential check (local, supporting evidence): 6152 generated formats and argument sets,
run on PHP 5.6 and on PHP 8.5 through `LegacySprintf`. 1941 throw on PHP 8; for all of
them the result matches PHP 5 exactly (954 are `false` on PHP 5). Formats PHP 8 accepts
were not changed; 92 of them give a different result on PHP 5 (PHP 8-only `%h`/`%H`,
`%.f` precision) and stay as PHP 8 formats them.

`output_notl()` had the same call and the same root cause; it formats master speeches in
training and the forest's pack line `` `2You encounter a group of `^%i`2 %s`2. `` (`%i` is
not a PHP conversion, so every pack encounter was a fatal page on PHP 8; PHP 5 printed the
line without the count). Fixed by the same change; the pack line is covered in PHPUnit,
not by an HTTP fight.

Other direct `sprintf`/`vsprintf` calls (not changed; listed per the brief):

| Call | Format source | Same failure? |
|---|---|---|
| `lib/output.php` nav text (`addnav` with an array), two calls | Code literal, translated when translation is on | Only with a bad translation row |
| `lib/pageparts.php:526` buff line, `:636` online list, `:694`/`:696` mail link | Translated code literal | Only with a bad translation row |
| `companions.php:172`, `mounts.php:211` delete confirmation | Translated code literal | Only with a bad translation row (part 3 editors) |
| `referers.php:100`, `lib/mail.php:375`/`:378`, `lib/clan/list.php:33`/`:35`, `lib/clan/applicant_apply.php:71`/`:73` | Translated code literal | Only with a bad translation row |
| `prefs.php:230` | Module pref descriptions | Module code, not stored text |
| `lib/showform.php:24` | Caller's key prefix | Code |
| `lib/datetime.php` | Code literals | No |
| `src/Security/MailContent.php:62` | Stored mail arguments | Already guarded (`try`) |
| `lib/content_editor.php` taunt sample | Editor input | Guarded (part 1) |

Bad translation rows are the rest of the translator, out of scope here.

### Taunt save check (B4)

Kept. PHP 5 rendered a taunt with a stray `%` as an empty or mangled string: the defeat
news line lost its taunt and 1.1.2 printed a PHP warning with a backtrace to every reader.
Nothing in 1.1.2 shows that output as intended, and refusing it at save costs nothing that
worked. Stored rows now render PHP 5's result instead of a fatal page; the editor still
refuses new ones and shows stored ones for repair. The same rule applies to master
speeches that contain a code.

## Tests

- `test_creature_and_master_editor_authority_http`, both editors: role matrix on every op,
  GET no-mutation (405), malformed ops, IDs and levels (400), unknown IDs (404), CSRF (403),
  missing and replayed intents and a second copy of a form (409), a row changed underneath
  (409), forged `creatureid`, `creaturehealth`, `creatureexp`, `creatureaiscript`,
  `createdby`, master `creaturehealth` and `creatureid` (refused, nothing written), level
  0, 18, 19, -1 and text, every text limit, invalid UTF-8, quotes, backslash, UTF-8 and HTML
  in every text, the stats of levels 3 and 17 from `creature_stats()`, level 18 keeping
  stored stats, 1.1.2's "not saved" note, the creature A.I. field by role, the module
  object preference namespace (undeclared, unknown and traversal module names, a forged
  `objtype`), search probes and list escaping, delete, the master formulas at levels 1 and
  7, a lone `%` accepted in a master speech without codes, stray `%` speeches with codes
  refused, a late write failure (fixture CHECK constraint) rolling the save back with the
  spent form staying spent and a fresh one saving, a forest fight against the saved
  creature (stats, texts, its death message escaped) and a training fight against the
  saved master (name, weapon, the formatted speech, the level-up).
- `test_news_and_bio_format_stored_stray_percent_http`: three stored defeat rows (stray
  `%`, unknown `%y`, clean) on `news.php` and `bio.php`.
- `tests/unit/LegacySprintfTest.php`: each PHP 5 failing case with PHP 5's result and
  warning, formats that must not change, and `sprintf_translate()` and `output_notl()`
  using it.

Before the fix: the editor test fails at its role matrix (the seeded creature's edit page
is a fatal page); the news test fails on `news.php` (`ValueError`) and `bio.php`
(`ArgumentCountError`); the unit test's formatter case fails with `ArgumentCountError`.
With the fix in place, each of these reverted alone makes a test fail:

| Reverted | Failing assertion |
|---|---|
| Creature declared-field check | `400 != 200` for a posted `creaturehealth` |
| Level stats from `creature_stats()` | the added creature's health, attack, defense, gold and experience were NULL |
| Level 18 keeps stored stats (made to take level 17's) | health `7 != 178` after a level 18 save |
| Creature level 1-18 (made 1-19) | `400 != 200` for level 19 |
| Creature edit intent bound to the row | `409 != 200` on the save over a row changed underneath |
| Unknown creature ID 404 | `404 != 409` for creature 4294967295 |
| Creature name required | `400 != 200` for an empty name |
| Supported A.I. behaviour only | `400 != 200` for `arbitrary php` |
| A.I. field only for `SU_MEGAUSER`/`SU_RAW_SQL` | `400 != 200` for a forged `creatureaiscript` |
| Master speech format check | `400 != 200` for `100% sure, %w` |
| Master level 1 and up | `400 != 200` for level 0 |
| Unknown master ID 404 | `404 != 409` for master 4294967295 |
| `ENT_QUOTES` in the master form | the form's name was `Sensei O` |
| `sprintf_translate()` change | `news.php` fatal page |
| `output_notl()` change | PHPUnit `ValueError: Unknown format specifier ","` |

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- All four shards through `scripts/tooling-shards.py`, each on a fresh database after
  PHPUnit (the translator and `output_notl()` are shared): shard 1 49 tests in 804 s,
  shard 2 37 in 847 s (holds the editor test, 52 s), shard 3 38 in 813 s (holds the news
  test, 1 s), shard 4 39 in 756 s; no failures, errors or skips.
- PHPUnit 158/158.
- Before the fix (old code stashed) and each revert above, on a fresh database.
- PHP lint: 368 files, no failures. Both PHPStan configs: no errors
  (`src/Compatibility/LegacySprintf.php` is under `src` in the level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier and project validator pass; `git diff --check` clean.
- Serialized state: no serialize or unserialize site changed; the inventory is not
  recounted.

## Remaining gaps

- `bio.php` is a fatal page for any character without a mount (PHP 8's false-to-array
  deprecation at `bio.php:97`); the news test gives its character a mount. New ROADMAP item.
- `bio.php` pastes `char` into `login = '$char'` when it is not numeric (unbound SQL). New
  ROADMAP item.
- Deleting a creature leaves its object preferences, as in 1.1.2.
- Creature search is a read-only POST without CSRF, as in 1.1.2.
- No shipped module declares `prefs-creatures`, so the creature object preference save
  path is refused end to end; the shared editor's accepted path is covered by the mount
  preference test.
- Two masters may share a level, or a level may have none, as in 1.1.2; training is
  unchanged.
- The format warnings PHP 5 printed are not logged.
- Bad translation rows can still break the formats listed above.

## Acceptance, 2026-10-17

`82a48fb83f32f39543c8c09b97a4942e88fc98f9` is accepted on its own run. Confirmed through the
GitHub Actions run and job API: Modern core **38971089072 SUCCESS**, all 12 jobs, with both
`result` jobs SUCCESS (PHP 8.4 / MariaDB 11.4 at 21:58:18Z, PHP 8.5 / MySQL 8.4 at
21:59:46Z, 2026-10-16). Baseline integrity **38971089075 SUCCESS** (21:42:00Z). Content
editors part 2 and the news formatting fatal are Done in `ROADMAP.md`. The two `bio.php`
gaps listed above are closed as candidates in
[content editors part 3](CONTENT-EDITORS-PART3-AUTHORITY-20261017.md).
