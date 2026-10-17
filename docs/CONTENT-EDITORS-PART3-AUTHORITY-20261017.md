# Content editors part 3: mounts, companions, and the bio page, 2026-10-17

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `82a48fb83f32f39543c8c09b97a4942e88fc98f9`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Scope, in one commit:

- A. `bio.php` (the two ROADMAP items found during part 2: the login lookup pasted into
  SQL, and the fatal page for a character without a mount) and the mount editor's owner
  lookup (`mounts.php?op=xml`), which ran before the editor's access check.
- B. Content editors part 3: `mounts.php` (add, edit, save, delete with owner refunds,
  give, activate, deactivate) and `companions.php` (add, edit, save, delete, take,
  activate, deactivate, and the companion object preference editor).

Also recorded here: content editors part 1 (`6820b80`) and part 2 with the news formatting
fatal (`82a48fb`) are accepted (see their documents' acceptance sections).

The mount object preference editor (`mounts.php?subop=module`) was already closed and is
kept unchanged. `stables.php`, companion combat and the general buff and companion
schemas were not opened; the editors only call their accepted checks.

Files: `bio.php`, `mounts.php`, `companions.php`, `lib/mount_companion_editor.php` (new),
`lib/typed_editor.php` (companions join the shared object preference editor),
`lib/dbwrapper_pdo.php` (`db_query_cached()` takes bound parameters, passed to
`db_query()`; existing callers are unchanged).

## Access

Both editors run `check_su_access(SU_EDIT_MOUNTS)` before any op, as in 1.1.2, and the
owner lookup now does too. The test runs every op of both pages (list, add and edit forms,
the owner lookup, save, delete, give, take, activate, deactivate, the companion
preference save) for an anonymous client (redirect or 403), a player and a superuser
holding every flag except `SU_EDIT_MOUNTS` and megauser (403), and `SU_EDIT_MOUNTS` (200
for each page). No row changes in any of those requests.

`bio.php` keeps 1.1.2's access: anonymous visitors are sent to log in; any logged-in
player can view any biography.

## Owner lookup finding

`mounts.php?op=xml&id=N` lists the names of the characters who own mount N (the list's
owner count links to it). In 1.1.2 it ran before `check_su_access(SU_EDIT_MOUNTS)`, with
the ID pasted unquoted into `WHERE hashorse=$id`. The only thing in front of it was forced
navigation, so the page answered whoever's allowed navigation held the URL, whatever
their flags, and the ID was SQL. Reproduced on `82a48fb`: a player (no flags) with the
URL allowed got 200 and the owner list.

1.1.2's rule for the lookup is the editor's: it is the editor's own page and data, linked
only from the editor's list. It now requires `SU_EDIT_MOUNTS` (403 otherwise), takes a
positive integer ID (400 otherwise, including `1 OR 1=1`, `0` and a missing ID), and binds
the query. The response format is unchanged.

## Bio findings

- Login lookup. 1.1.2 used `acctid = $char` for a numeric `char` and `login = '$char'`
  otherwise: any logged-in player could put SQL into the second (inferred from the code;
  a login with a quote also failed). Both are now bound. A numeric value still matches
  `acctid`, any other text matches `login` exactly, as plain text: `Bio O'Brien` finds its
  character, and `x' OR '1'='1`, `x' OR acctid='N` and `BioRider%` find nobody (1.1.2's
  "This character is already deleted."). A non-text `char` (an array) finds nobody.
- Mountless fatal. For a character without a mount the mount query returns no row;
  PHP 5 turned the `false` into an array on the next line (`$mount['acctid'] = ...`), PHP 8
  refuses (`bio.php:97`, reproduced on `82a48fb`). The smallest change: no row is an
  empty array. The `bio-mount` hook gets the same argument shape (`acctid`, plus the mount
  row when there is one) and the page shows 1.1.2's "Creature: None". The mount and news
  queries are bound; the `ret` link is unchanged.

## Pattern

The part 1 and 2 helpers in `lib/content_editor.php` are reused: `op` from the page's
list, positive integer IDs (400 otherwise); every write is a POST with CSRF and a one-use
intent bound to the row as shown (GET 405; a replay, a second copy of a form, or a row
changed since it was shown is 409); fields validated before the intent is spent; an
unknown ID is 404 before any write; each write in `resurrection_player_mutation()` with
the row locked and compared again; all SQL bound; no SQL or driver text printed; output
escaped. 1.1.2's action links (Give, Take, Activate, Deactivate, Del) are inline POST
forms with the same labels; "Edit" and the owner count stay links.

New in `lib/mount_companion_editor.php`: the declared field lists, the mount buff and
companion abilities builders, `resurrection_object_save()`, `resurrection_object_action()`
(row actions), the mount delete with refunds, give and take. Each change drops the row's
cache entry (`mountdata-<id>`, `companiondata-<id>`) after the commit.

## 1.1.2 comparison

"Old" is `82a48fb`. "Reproduced" means shown on a fresh local database; otherwise the old
behaviour is read from the code.

| # | Editor | 1.1.2 | Old (`82a48fb`) | Now |
|---|---|---|---|---|
| 1 | Mounts | Owner lookup (`op=xml`) before the flag check, ID pasted into SQL | Same (reproduced: a player got 200) | `SU_EDIT_MOUNTS`, positive integer ID, bound |
| 2 | Mounts | Activate, deactivate, give, delete: GET links, ID pasted into `WHERE mountid='$id'` | Same | POST forms bound to the mount as listed; GET 405; ID a positive integer |
| 3 | Mounts | Delete: refund read from the cached row, then three separate statements outside a transaction | Same; an unknown or already deleted ID fails on the `false` row before any write (read from the code) | Refund, owner reset, mount delete and preference delete in one locked transaction; unknown 404 (below) |
| 4 | Mounts | Refund: every owner gets the mount's `mountcostgems` in gems and `mountcostgold` in bank gold, and loses the mount | Same | Same amounts (tested exactly, online, offline and the acting superuser) |
| 5 | Mounts | Del offered in the list only for an inactive mount; a hand-made GET deleted an active one | Same | An active mount's delete is 409: the list's rule, enforced |
| 6 | Mounts | Delete confirmation named the owner count | Same | Same text; the form is bound to that count, so an owner buying or selling meanwhile makes it stale (409) |
| 7 | Mounts | Give read the buff from the cache, unchecked | Same, through ScalarState, unchecked | The stables' check (`resurrection_stables_buff()`); a malformed stored buff stops it (500) before anything changes. No cost and no hooks, as in 1.1.2 |
| 8 | Mounts | Save built SQL from every posted `mount[...]` key (any column) and every `mount[mountbuff][...]` key | Same | Only the form's 13 fields and 21 buff fields; anything else 400 |
| 9 | Mounts | Buff: every non-empty buff field as text, plus `schema` `mounts`; `stripslashes()` on each | Same | Same shape and order; checked as the stables and New Day check a stored mount buff (below). `stripslashes()` dropped: a magic-quotes leftover that ate backslashes. Deliberate correction |
| 10 | Mounts | Costs, forest fights, active flag unchecked | Same | Costs 0 to 2147483647 (the range the stables charge and the refund pays into), forest fights a signed integer, active 0 or 1; texts to their column (name and category 50 characters) |
| 11 | Mounts | A save that changed nothing printed "Mount not saved:" and the SQL | Same | "Mount not saved!" without the SQL. Deliberate correction |
| 12 | Mounts | Edit of a mount with a malformed stored buff | PHP 8 fails building the form from `false` (read from the code) | The form opens with an empty buff for repair |
| 13 | Companions | Activate, deactivate, take, delete: GET links, ID pasted into SQL | Same | POST forms bound to the companion as listed; GET 405 |
| 14 | Companions | Deactivate dropped the cache entry `companionsdata-$id`, a typo for the edit page's `companiondata-$id`, so the edit page could show the old active flag and a save put it back | Same | `companiondata-<id>`, tested with the data cache on. Deliberate correction |
| 15 | Companions | The list counted into `$companions` (copied from the mount list's owner count), which is the acting superuser's own companion list, and formatted an undefined `$conf` for each inactive companion | Fatal list page (reproduced: `pageparts.php:583`, the character stats reading the changed list); an inactive companion also fails on `$conf` (read from the code) | No writes into `$companions`; the list works and the acting superuser's companions are unchanged |
| 16 | Companions | Take: level formulas (`attack + attackperlevel * level`, the same for defense and maximum hitpoints, hitpoints full), the `alter-companion` hook, `apply_companion()`, "Succesfully taken ... as companion." | Same, abilities through ScalarState | Same formulas, hook, call and message (tested at level 5). The form is bound to the acting superuser's level and companions; the result must pass combat's companion check (`ForestCompanionState`), or nothing is taken (500) |
| 17 | Companions | Save built SQL from every posted `companion[...]` key; arrays serialized | Same | Only the form's 21 fields and its four abilities; anything else 400 |
| 18 | Companions | Abilities: posted keys serialized as given, unchecked boxes appended as `false` | Same | Keys `fight`, `defend` (checkbox `1`), `heal`, `magic` (0-30) only; stored in 1.1.2's shape and order |
| 19 | Companions | Unchecked `cannotdie` and `cannotbehealed` saved as `false` (an empty string); the add form posted an empty `companionactive` | Same; strict SQL refuses an empty string in those integer columns (read from the code) | Unchecked is 0; a new companion is inactive (0). Deliberate correction |
| 20 | Companions | Numbers and texts unchecked | Same | Numbers 0 to 2147483647 (combat's companion range), DK cost 0-127 (its column), name 1-255 bytes (combat stores companions by name), texts to combat's limits |
| 21 | Companions | `subop=module` wrote every posted key as an object preference of any module named in the URL | Same | The shared typed object preference editor (installed module, declared `prefs-companions`, typed values). No shipped module declares `prefs-companions`, so every save is refused (400) |
| 22 | Companions | Delete offered only for an inactive companion; a hand-made GET deleted an active one | Same | An active companion's delete is 409; its object preferences are deleted in the same transaction |
| 23 | Both | Save failures printed the SQL | Same | Generic error page, no SQL or driver text |

The form fields, field order, defaults (including the edit form's hidden active flag),
location lists (`stablelocs`, `camplocs` hooks), texts and messages ("Mount saved!",
"Companion saved!", "There are %s user(s) who own this mount, are you sure you wish to
delete it?", "If you wish to delete a companion, you have to deactivate it first.") are
unchanged. The location `<select>` is now escaped and closed.

## Refund findings

- Amounts: 1.1.2's rule, at the time of deletion: each owner gets the mount's gem cost
  in gems and its gold cost in bank gold, and `hashorse` becomes 0. Tested exactly for an
  offline owner, an online owner and the acting superuser (gems + 3, bank gold + 260).
  Feed costs and the stables' trade-in rate play no part, as in 1.1.2.
- Atomicity: the refunds, the mount delete and the object preference delete are one
  transaction with the mount row and the owners locked. A failure on the last write
  (forced with a fixture constraint) leaves every owner, the mount and its preferences as
  they were; the spent form stays spent (409) and a fresh form deletes.
- Repeats: the form is one-use; a replay, a second copy, or a request after the delete is
  refused (409 or 404) and refunds nobody twice. An unknown ID is 404 before any write.
- In 1.1.2 a non-integer ID such as `1abc` matched mount 1 when read (MySQL's string to
  number cast) and refunded its owners; the delete statement compares the same text and,
  under strict SQL, fails, leaving the mount. Read from the code, not reproduced. IDs are
  now positive integers (400).
- Online owners: the refund is written to the accounts directly, as in 1.1.2. An online
  owner's next page reloads the account and its end-of-page save writes only the fields
  that page changed (the accepted online-player model), so the refund is kept (tested).
  The acting superuser, if an owner, is refunded through the session in the same
  transaction, so the page's own save keeps it.
- Owners keep the mount's buff until it wears off or their next New Day, as in 1.1.2.
- The delete form is bound to the owner count it shows; a purchase or sale meanwhile
  makes it stale (409).
- 1.1.2 refunded from the cached mount row. The row is now read locked inside the
  transaction, so a price changed since the list was shown makes the form stale.

## Mount buff and abilities checks

A saved mount buff must pass the checks the game applies to a stored one: the stables'
(`resurrection_stables_buff()`: it reads back as an array) and New Day's accepted mount
buff consumer (`NewDayState::buffs()`, through `ForestBuffState`), which every owner's New
Day runs. A buff it refuses would stop every owner's New Day, so it is 400 at save:
`rounds` empty or 0, non-numeric or expression modifiers (`abc`, `<charm>`),
`invulnerable` other than 0 or 1, a text over 4096 bytes. A saved mount is then bought at
the stables in the test (price and buff as saved).

A companion's abilities must have only the form's keys and types. Take checks the whole
companion list as combat does (`ForestCompanionState::validate()`).

## Serialized state

Recounted with the same token method (PHP tokenizer over tracked shipped PHP, excluding
`tests/`, `scripts/` and `lab/`; comments ignored), which reproduces 112 / 53 / 42 / 69 /
1 at `4478c06`, 108 / 50 / 40 / 67 / 1 at `8257aed` and 107 / 49 / 39 / 67 / 1 at
`82a48fb`. Candidate: **107 sites / 50 files / 40 serialize writers-checks / 66
ScalarState reads / one centralized unserialize**. Details in `SERIALIZED-STATE-AUDIT.md`.

## Tests

- `test_mount_and_companion_editor_authority_http`, both editors: role matrix on every op;
  GET no-mutation (405) on every write; malformed ops and IDs (400); unknown IDs (404) for
  delete, give, take, activate, deactivate and save; the companion object preference
  editor (undeclared and unknown modules, a forged `objtype`: 400); CSRF (403); missing
  and replayed intents, second copies of forms and rows changed underneath (409); the
  exact add form field sets; undeclared fields (`mount[mountid]`, `mount[hashorse]`,
  `hashorse`, `mount[mountbuff][evil]`, `mount[mountbuff][tempstat-attack]`,
  `companion[companionid]`, `companion[hitpoints]`, `companion[abilities][evil]`,
  `abilities`); every range and text limit named above, invalid UTF-8, quotes, backslash,
  UTF-8 and HTML in texts; malformed buffs and abilities; the exact stored rows,
  including the serialized buff and abilities; no SQL in a "not saved" page; the edit
  page of a malformed stored buff; activate with a stale and a replayed form; a stables
  purchase of the saved mount (gold, gems, buff); give (mount and buff), give of a
  malformed buff (500, nothing changed), give with a stale form; the owner lookup with
  three owners; the data cache on a second fixture server with the cache on (bio caches
  `mountdata-<id>`, a save drops it and bio shows the new name, deactivate drops it); the
  delete confirmation text with the owner count; delete with a stale owner count; the late
  write failure and fresh-form retry; exact refunds for the acting superuser, an online
  and an offline owner; replay after delete (404, no second refund); the online owner's
  next page keeping the refund; the companion list with an inactive companion leaving the
  acting superuser's companions unchanged; take at level 5 with exact stats and abilities
  and a replay (409); deactivate dropping `companiondata-<id>` with the edit page then
  showing inactive; companion delete (active 409, then deleted with its preferences, then
  404).
- `test_mount_owner_lookup_and_bio_page_http`: bio for anonymous (redirect), a mountless
  character ("Creature: None"), lookup by login, a mounted character (its mount's name), a
  login with a quote, three SQL probes and an array `char` (nobody found); the owner
  lookup refused for a player and a superuser without `SU_EDIT_MOUNTS` (403), working for
  `SU_EDIT_MOUNTS`, and malformed IDs (400).
- `test_news_and_bio_format_stored_stray_percent_http`: comment updated (the bio fatal is
  closed); its character keeps the mount.

Before the fix (production files of `82a48fb` restored, tests as committed, fresh
database): the editor test fails at its role matrix (`403 != 200` for a player on
`mounts.php?op=xml`); the bio test fails on the mountless character (`bio.php:97`,
`ErrorException`). With the owner lookup taken out of the matrix, the editor test then
fails on the companion list (fatal page at `lib/pageparts.php:583`). After the fix both
pass.

With the fix in place, each guard below was reverted alone on a fresh database:

| Reverted | Result |
|---|---|
| Only the declared posted keys | editor test: `400 != 200` for `mount[mountid]` |
| New Day's mount buff check | editor test: `400 != 200` for an empty `rounds` |
| Unknown ID 404 before the intent | editor test: `404 != 409` for mount 4294967295 |
| Mount delete in one transaction (a `COMMIT` before the mount delete) | editor test: after the late failure the online and offline owners were refunded (`('11','8','360','0')`) while the mount stayed |
| Companion deactivate cache key (1.1.2's `companionsdata-` restored) | editor test: the edit page's cache entry still existed |
| Companion abilities keys | editor test: `400 != 200` for `companion[abilities][evil]` |
| Owner lookup needs `SU_EDIT_MOUNTS` | bio test: `403 != 200` for a player |
| Bio mountless row as an empty array | bio test: fatal page at `bio.php:101` |
| Bio login bound (1.1.2's `login = '$char'`) | bio test: fatal page from the lookup |
| The save's locked recheck of the row | still passes: the same changed row is refused earlier, when the intent's context is compared with the row read before the lock. The locked recheck only matters for a change between that read and the lock, which the test does not race |
| The delete's inactive-only check (companions) | still passes: a delete form exists only for an inactive row and is bound to it, so an activated row is already stale (409). The check is defence in depth |

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- All four shards through `scripts/tooling-shards.py` (`lib/dbwrapper_pdo.php` and
  `lib/typed_editor.php` are shared), each on its own fresh database after PHPUnit, run
  side by side: shard 1 41 tests in 946 s, shard 2 38 in 1074 s (holds the bio test,
  11 s), shard 3 46 in 1087 s (holds the editor test, 50 s), shard 4 40 in 1052 s; no
  failures, errors or skips. Only PHPDoc in `lib/mount_companion_editor.php` changed
  after these copies were taken; the two new tests and the news and bio test were rerun on
  the final tree.
- PHPUnit 158/158. `tests/tooling/test_tooling_shards.py` 4/4.
- Before the fix and each revert above, on a fresh database.
- PHP lint: 369 files, no failures. Both PHPStan configs: no errors
  (`lib/mount_companion_editor.php` added to the level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier (417 files, 11 commits) and project validator pass; `git diff
  --check` clean.

## Remaining gaps

- The stables read the mount without a lock (`stables.php`, not opened here). A mount
  deactivated and deleted between a player's purchase page and its commit could leave
  that player owning a deleted mount (read from the code, not reproduced). New ROADMAP
  item.
- A mount buff with a minion count cannot be saved from the editor: the form posts text
  and New Day's accepted consumer requires a number. 1.1.2 stored it as text. Part of the
  remaining mount buff schema item.
- `getmount()` in `lib/mounts.php` still builds its query from `hashorse` unbound (an
  integer from the account row). Not changed here.
- Companions players already hold are theirs: deleting or editing a companion does not
  change them, as in 1.1.2.
- `apply_companion()` past the companion limit still prints "Succesfully taken", as in
  1.1.2.
- No shipped module declares `prefs-companions`, so the companion object preference save
  is refused end to end; the shared editor's accepted path is covered by the mount
  preference test.
- Two open edit forms for the same row share one intent; the second save is 409 and must
  be reloaded.
