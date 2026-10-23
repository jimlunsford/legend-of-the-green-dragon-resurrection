# Content editors part 1: weapons, armor, taunts, titles, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `55ec6c2ebf3fdd29bbc14863d506a51698fd3cb8`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Scope: `weaponeditor.php` and `armoreditor.php` (`SU_EDIT_EQUIPMENT`), `taunt.php`
(`SU_EDIT_CREATURES`) and `titleedit.php` (`SU_EDIT_USERS`), every op of each. The shops
were only read and exercised through their accepted purchase route. The creature, master,
mount and companion editors are parts 2 and 3.

Files: `lib/content_editor.php` (new), `weaponeditor.php`, `armoreditor.php`, `taunt.php`,
`titleedit.php`; `phpstan-new.neon`.

All four files were byte-identical to `historical-source-1.1.2` at the start.

## Access

Each page runs its `check_su_access()` gate before any op, as in 1.1.2. The tests cover
every op for an anonymous client (redirect or 403), a player and a superuser holding every
flag except the gate and megauser (403), and the gate flag (200 for each page). No row
changes in any of those requests.

## Shared pattern

`lib/content_editor.php` holds what the four editors share:

- `op` is one of the page's ops, IDs are positive integers that fit `int(11) unsigned`
  and `level` is a non-negative integer that fits `int(11)`; anything else is 400.
  An unknown ID is 404 before any write (1.1.2 drew a fatal page or an empty form, or
  ran a no-op write).
- Every write is a POST with CSRF and a one-use intent. The intent is bound to the
  operation and the row as it was shown (an add form: the page level, or nothing). GET
  on a write op is 405. A replay, a second copy of the same form, or a row changed since
  the form was shown is 409 and writes nothing.
- Validation runs before the intent is spent: a POST carries only the protected fields,
  the form's fields and `showform()`'s tab index; text is a string of valid UTF-8 without
  NUL within its limit; numbers are whole and within the field's range.
- Each write runs in `resurrection_player_mutation()`: the row is locked and compared
  with the form's row again before it is written. All SQL is bound.
- The lists' "Del" links are POST forms with 1.1.2's confirmation text; "Edit" links stay
  GET (display only).

Each editor's field list, price table, texts and defaults are unchanged.

## 1.1.2 comparison

| # | Editor | 1.1.2 | `55ec6c2` | Now |
|---|---|---|---|---|
| 1 | Weapons, armor | `del` was a GET link with the ID pasted into SQL | Same: a GET deleted (reproduced) | POST form bound to the row; GET 405 |
| 2 | Weapons, armor | `save` pasted the name and damage/defense into double-quoted SQL | Same: a name with `"` was a fatal page (reproduced) | Bound |
| 3 | Weapons, armor | Price `$values[$damage]` from the 1-15 table; an out-of-range value left `value=` empty, a SQL error and no write | Undefined key: fatal page (reproduced at damage 16) | 1-15 only, else 400; the price table is unchanged |
| 4 | Weapons, armor | Name up to the item column (128); the shop copied it into `accounts.weapon`/`armor` (50), which non-strict MySQL truncated | A name over 50 characters saves, then its purchase fails under strict SQL | Names are capped at 50 characters, the account column the shop writes (deliberate correction) |
| 5 | Weapons, armor | `edit` of an unknown ID drew a broken form | Fatal page (`array_key_exists()` on false, reproduced) | 404 |
| 6 | Weapons, armor | `level` cast with `(int)` | Same | Non-negative integer, else 400; an add uses the page level, as in 1.1.2 |
| 7 | Weapons, armor | The hidden ID in the posted form chose the row to update | Same | The intent is bound to that ID and row; another ID has no intent (409) |
| 8 | Taunts | `del` GET link; `tauntid` and `taunt` pasted into double-quoted SQL | Same: a taunt with `"` was a fatal page (reproduced) | POST form; bound SQL |
| 9 | Taunts | A stray `%` (not one of the listed codes) never threw in PHP 5's `sprintf`: at most a warning, and the taunt could come out mangled or empty | Saved; the editor preview of it is a fatal page (`ValueError` at `translator.php:109`, reproduced). Defeat news (forest, training, Dragon, graveyard, PvP) stores the taunt and `news.php` and `bio.php` format it through the same `sprintf_translate()` | Refused at save (400) unless it formats as combat formats it; a stored one is shown for repair with a note instead of the preview |
| 10 | Taunts | The `` `% `` colour code is escaped by `sprintf_translate()` | Same | Unchanged; the save check applies the same escape |
| 11 | Taunts | `edit` of an unknown ID drew a broken form | Fatal page (reproduced) | 404 |
| 12 | Taunts | `editor` set to the editing superuser's login | Same, through `addslashes` | Same login, bound |
| 13 | Titles | `save` pasted `id`, `dk`, `male`, `female` into SQL; `ref` saved as `''` | A quote in a title or a non-numeric `dk` was a fatal page (reproduced) | Bound; `dk` within `int(11)`, titles at most 25 characters (the column and `accounts.title`); `ref` stays `''` |
| 14 | Titles | Reported "Title modified." / "New title added." from the affected rows, so an unchanged save said "Unable to modify title.", followed by `db_error()` | Same | The same notes from the affected rows; the driver error text is no longer printed |
| 15 | Titles | `delete` GET link | Same | POST form; GET 405 |
| 16 | Titles | The rebuild ("Reset Users Titles") ran on a GET link, every account in one unguarded pass, `addslashes` and the pasted `acctid` | Same | GET shows a form; the rebuild is its POST, bound to the title table shown, in transactions of 50 accounts (below) |

## Title rebuild findings

- Trigger: in 1.1.2 the rebuild runs only from the "Reset Users Titles" link
  (`titleedit.php?op=reset`), a GET. Saving or deleting a title never rebuilds; that is
  kept. The link now shows a confirmation form; its POST runs the rebuild. A replay is
  409, and a form shown before the title table changed is 409.
- Rule, unchanged: an account whose title is one of the titles of its dragon kill rank
  (the highest rank at or below its dragon kills) is skipped. Any other account gets a
  random title of that rank (`get_dk_title()`), and its name is rebuilt around it with
  `change_player_title()`. 1.1.2's two outputs ("Changing ... to ..." and "Changing only
  the title (not the name) of ...") are kept.
- Custom titles are preserved as in 1.1.2: an account with a `ctitle` keeps it in its
  name and in `ctitle`; only `title` changes.
- Transactions: accounts are locked and rewritten in ascending ID order, 50 per
  transaction. A failure rolls back only its batch: every account is either fully
  rewritten (name and title together) or untouched. The page reports the truth: how many
  accounts were checked and changed before the failure, that the rest were not changed,
  and a fresh form to run it again. Accounts already rebuilt are skipped on the retry.
- A new name longer than `accounts.name` (60) is not written; the account is listed as
  "Not changing ...: the new name would be too long." Under 1.1.2's non-strict MySQL it
  was silently truncated; under strict SQL the write would fail and stop the batch.
- The acting superuser's own account is written through the session, as 1.1.2 did, but
  inside the batch's transaction, so the page's own save does not write the old name back.
- Online players: each request reloads the account, so an online player sees the new
  name on their next page, and the end-of-request save writes only fields that request
  changed, so it does not restore the old name (tested). A player's page loaded before
  the rebuild that then posts a gameplay action gets the accepted player-mutation answer:
  the locked account no longer matches the page, 409, reload. This is inferred from
  `resurrection_player_mutation()`'s account comparison and was not tested here.

## Tests

- `test_weapon_and_armor_editor_authority_http`, both editors: role matrix, GET
  no-mutation, malformed and unknown IDs and levels, CSRF, missing and replayed intents,
  a second copy of a form, a row changed underneath, every field's bounds (damage or
  defense 0, 16, -1, text, decimal, empty; a 51-character name; invalid UTF-8; extra
  fields; a posted ID that is malformed or unknown), quotes, backslash, UTF-8 and HTML in
  names, the price from the table, escaping in the list, the accepted shop offering and
  selling the saved item at its price (gold, item, stat, value and total), and delete.
- `test_taunt_and_title_editor_authority_http`: the same for taunts and titles, plus
  stray `%` taunts (`100% sure %w`, `50%!`, `end 100%`, `%d of %w`) refused, a doubled
  `%%` and the `` `% `` colour code accepted, a stored stray `%` taunt shown for repair,
  the editor login, 1.1.2's unchanged-save note, and the rebuild: GET changes nothing; a
  form older than the title table is 409; the acting superuser, an online player, a
  custom title, a name that would not fit and 60 bulk accounts; a late write failure in
  the second batch (a fixture CHECK constraint) leaves the first batch rebuilt and the
  second untouched, with the counts on the page; the spent form stays spent and a fresh
  one finishes; the online player's next page shows the new name and keeps it.

Before the fix, both tests fail at their first write check: the GET `del` link deleted
the row (weapon 16, taunt 1). With the fix in place, each of these reverted alone makes a
test fail:

| Reverted | Failing assertion |
|---|---|
| GET delete | weapons: `405 != 200` on `weaponeditor.php?op=del` |
| Damage/defense range and price key check | weapons: `400 != 500` for damage 16 |
| 50-character name cap (128) | weapons: `400 != 200` for 51 characters |
| Delete intent bound to the row | weapons: `409 != 200` on the stale delete |
| Edit intent bound to the row | weapons: `409 != 200` on the save over a changed row |
| Taunt format check at save | taunts: `400 != 200` for `100% sure %w` |
| Taunt preview guard | taunts: fatal page on the stored stray `%` taunt |
| Rebuild only on POST with an intent | titles: the GET reset changed accounts |
| Rebuild in batches (one transaction for all) | titles: the first batch was rolled back |
| Rebuild in transactions (none) | titles: accounts in the failed batch were partly rewritten |
| Name length check in the rebuild | titles: the first batch failed on the long name |
| 25-character title cap (100) | titles: `400 != 500` for 26 characters |

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- Shard 1 (it holds both new tests) through `scripts/tooling-shards.py` on a fresh
  database after PHPUnit: 40 tests OK in 818 s. The two new tests take about 56 s
  together when run alone. No shared helper changed, so the other shards were not run.
- PHPUnit 126/126.
- Before the fix, and each revert above, on a fresh database.
- PHP lint: no failures. Both PHPStan configs: no errors (`lib/content_editor.php` is in
  the level-6 config; `lib/titles.php` is added to its scan files).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier and project validator pass; `git diff --check` clean.
- Serialized state: no serialize or unserialize site changed; the inventory is not
  recounted.

## Remaining gaps

- A taunt with a stray `%` that is already stored (a database from before this change)
  still breaks every page that formats it on PHP 8: `news.php` and `bio.php` format
  defeat news through `sprintf_translate()` in `lib/translator.php`. News rows store
  the taunt text, so repairing the taunt does not repair news already written. Inferred
  from the shared code path; only the editor preview was reproduced. A fresh install's
  taunts all format (none has a stray `%`).
- Weapon, armor and title text is not sanitized for `` `c ``, `` `H ``, `` `w `` or HTML,
  as in 1.1.2. Output escapes HTML; colour and format codes apply wherever the name or
  title is shown.
- `lib/dragon_outcomes.php` rebuilds the name after a dragon kill without a length
  check, so a long base name with a long title would fail that player's dragon kill write
  under strict SQL. Not changed here.
- Two open edit forms for the same row share one intent; the second save is 409 and must
  be reloaded. Each listed row has its own delete intent, kept in the session until used.
- Deleting a weapon, armor or title does not change accounts that hold it, as in 1.1.2.

## Acceptance, 2026-10-17

`6820b80a7c2b1e806fbfe0a871d72a2a9177e898` is accepted on its own run. Confirmed through the
GitHub Actions run and job API: Modern core **38958259938 SUCCESS**, all 12 jobs, with both
`result` jobs SUCCESS (PHP 8.4 / MariaDB 11.4 at 20:09:37Z, PHP 8.5 / MySQL 8.4 at
20:10:39Z, 2026-10-16). Baseline integrity **38958259926 SUCCESS** (19:53:56Z). Content
editors part 1 is Done in `ROADMAP.md`. Recorded with content editors part 3
([content editors part 3](CONTENT-EDITORS-PART3-AUTHORITY-20261017.md)).
