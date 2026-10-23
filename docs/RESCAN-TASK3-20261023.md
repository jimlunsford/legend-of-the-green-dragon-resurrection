# Final rescan task 3: repair path, SQL sweep, referer tracking, 2026-10-23

Candidate pending CI. Starts at `107ce87`. Closes, once CI accepts, the last three lines of the `ROADMAP.md` Final
rescan list: referer tracking (`common.php`), request values reaching SQL unbound (the full sweep), and the admin repair
path for players locked out by stored state. Each line was checked against the source at `107ce87` before the change.

The same commit records rescan task 2 accepted through `107ce87` (`RESCAN-TASK2-20261022.md`, "Acceptance").

## A. Referer tracking (`common.php`)

1.1.2 counted every visit that arrived from an off-site `http://` page: one row per referring URL in `referers`, with
the URL (`uri`, text), the referring host (`site`, varchar(50)), this host and the request path (`dest`,
varchar(255)) and the visitor's address (`ip`, varchar(40)). The visitor chooses the `Referer` header and the path. At
`107ce87` the three statements were already bound, but nothing fitted the values to their columns: under strict SQL a
host over 50 characters or a path over 255 failed the statement, and with it the page the visitor asked for (500).

**Decision: keep the tracking, bind it, and cut each value to its column in characters, as 1.1.2's non-strict MySQL
stored it.** Reasons:

- 1.1.2 ran on non-strict MySQL, which stored a longer value cut to the column (with a warning). Cutting reproduces what
  1.1.2 kept, so the referer list (`referers.php`) shows the same sites it showed then. Skipping the row instead would
  lose visits 1.1.2 counted.
- The columns count characters (utf8mb4), so the cut is in characters (`mb_substr`); the URL is cut to the text
  column's 65,535 bytes on a character boundary (`mb_strcut`). The cut URL is also the one looked up, so a long URL still
  counts on one row.
- A value that is not valid UTF-8 cannot be stored faithfully in a utf8mb4 column (strict SQL refuses it), so that visit
  is **not tracked**: there is no 1.1.2 value to reproduce.
- Any failure while tracking (the database refusing a value, a lost connection) is swallowed: tracking is bookkeeping,
  and the page goes on. Each statement stands alone, as in 1.1.2, so nothing is half written.
- 1.1.2's own rules are kept as written: its own-site test (including its `dirname()` of the request URI), only
  `http://` referers, and the host comparison with `:80` removed. A missing `Referer` or `Host` header reads as empty.

The code is `lib/referer_tracking.php` (`resurrection_referer_row()` builds the fitted row, `resurrection_track_referer()`
writes it); `common.php` calls it where the old block was.

**Also restored: 1.1.2's monthly cleanup.** On one new row in a hundred (`e_rand(1,100)==2`), 1.1.2 deleted up to 300
referers last seen over a month ago and wrote a game log line ("Deleted N records from referers older than ..."). That
cleanup was dropped without a record in `899cf0b`; it is back, bound, before the insert, as in 1.1.2. The expiry on view
in `referers.php` (`expirecontent`) stays as task 2 recorded it. `RESCAN-TASK2-20261022.md` said `referers.php` was "the
only place" expired referers are removed; that was wrong at the time (the 1.1.2 cleanup should have been there), and a
correction is appended to that document.

Tests: `test_referer_tracking_fits_its_columns_http` (a 78-character host, a 60-character multibyte host, a host and path
with quotes and a backslash, a 312-character path: each page 200, the stored row cut as above, a second visit counts on
the same row; a `Referer` that is not UTF-8 is not tracked; the database refusing the row leaves the page 200 and nothing
written) and, in `tests/unit/RescanTask3Test.php`, the row builder for each 1.1.2 rule, the character-boundary cut of a
long URL, the bound write against the database, and the cleanup on a seeded draw of 2.

## B. Request values reaching SQL unbound: the full sweep

### Method

`tests/unit/SqlInterpolationScanner.php` reads every tracked PHP file (`git ls-files '*.php'`) except `tests/`, `scripts/`
and `lab/` (tooling), with PHP's tokenizer. The historical tag is not in the working tree. A string piece is SQL when it
starts with a statement keyword (any case), contains an upper-case clause keyword (`WHERE`, `SET`, `LIMIT`, `VALUES`,
...), or ends in a column comparison (`acctid=`, `name='`) right before a value; text that is HTML or a link (`<tag`,
`href=`, `.php`, `?`, `&`) is not SQL. For each SQL piece the scanner takes the whole expression around it (the
concatenation, or the double-quoted string with its interpolations) and reports every part that is not a literal. A
`db_query()` whose first argument is built only from values reports them too. Bound parameters never appear: they are not
part of the SQL text.

Three kinds of value need no review (the rule in `SqlInterpolationScanner::BY_RULE`): `db_prefix()` of a literal table
name, a named constant, and a number. Every other value is listed below with its class:

- **server**: a value no request chooses: an integer column of the database or the session's own account row, a typed
  integer game setting, the clock, `e_rand()`, a fixed fragment chosen by code, a placeholder list, `db_prefix()` or
  `db_identifier()` of a name from a fixed list, a module or hook name from bundled module code, an installer-time value.
  Each entry says where its value comes from; none was assumed.
- **unreachable**: code no request reaches. The historical web installer's stages (`installer.php` answers 403 before
  any include) and 1.1.2's old `lib/mail.php` (nothing includes it). A direct request to any of these files gets 404:
  `lib/mail.php` had the guard; this commit adds the same guard to `installer_stage_0.php`, `installer_stage_9.php`,
  `installer_stage_10.php` and `installer_functions.php` (`test_unbound_sql_files_are_not_endpoints_http`). The CLI
  installer (`scripts/install.php`, `cron.php`) uses `lib/installer/fresh_install.php`, not these files.
- **not SQL**: a match that is not a query (the `Content-Type` header's `charset=`).

There is no "request" class: every request value found is bound in this commit.

### Counts

| | Values (file, value) | Sites |
|---|---|---|
| At `107ce87` | 249 | 743 (and 1,566 by rule; 2,309 in all) |
| Bound in this commit | 49 | 84 |
| Added (new code and placeholder lists) | 5 | 8 |
| One `$limit` site in `lib/commentary.php` gone (two identical branches merged) | 0 | -1 |
| Now: server | 188 | 630 |
| Now: unreachable | 16 | 35 |
| Now: not SQL | 1 | 1 |
| Now: request | 0 | 0 |
| Now, in all | 205 | 666 (and 1,565 by rule; 2,231 in all) |

`tests/unit/SqlSweepTest.php` scans the tree and compares every value it finds, with its number of sites, against the
table at the end of this document: a new interpolated value, another site of a listed one, or a listed value that is gone
fails the test, so the list cannot drift. Each listed value must carry a class and a reason. A third test runs the
scanner over a small page and checks it finds an interpolated request value and a session value and ignores a bound one.
Run against the code at `107ce87` (with this document), the comparison fails on the 49 values bound here.

### What was bound, by route

Request values (each a 500 before, from a player request):

| Value | Route | 1.1.2 | Now | Test |
|---|---|---|---|---|
| `comscroll` (`lib/commentary.php`) | `village.php` and every page that shows commentary | the page number times the page size pasted into `LIMIT`; past PHP's integer range it became a float in the SQL and the page failed | `LIMIT ?,?`; the offset stops at PHP's largest integer; past the end is an empty page | `test_rescan_task3_bound_request_values_http` |
| `newcount` (`motd.php`) | `motd.php` | pasted into `LIMIT`; a negative value, or one whose sum with the item count passed PHP's integer range, failed the page | `LIMIT ?,?`, never below 0, the sum capped; the 1.1.2 offset and row count kept | same |

Stored text that people wrote (bound; before, a quote failed the query or was escaped by hand):

| Value | Route | 1.1.2 | Now | Test |
|---|---|---|---|---|
| the translated "Unknown" (`hof.php`) | `hof.php` (dragon kills, the default) and `hof.php?op=days` | translator text pasted between quotes: a translation with a quote failed both pages | bound, cast to text so the server keeps it a string beside the integer column | same |
| the location (`lib/pvplist.php`) | `pvp.php` and the inn's bartender (`lib/inn/inn_bartender.php`), the PvP target list | `addslashes()` of the player's stored location (a village name an admin chose) | bound, with the list's integers | the PvP list in `test_pvp_eligibility_and_entry_rollback` |
| the game log message and category (`lib/gamelog.php`) | every route that logs (logins, maintenance, the admin tools) | `addslashes()`; messages can carry a typed login | bound | `RescanTask3Test::testGamelogStoresItsTextAsGiven` |
| the dwarf village name (`modules/racedwarf.php`) | the module's install (its Grizzly Bear companion), from the module manager | an admin's setting pasted between quotes: a quote failed the insert | bound | - |

Server values bound anyway (taken off the list rather than reviewed): the module API in `lib/modules.php` (module, hook,
setting and object names from bundled module code, `(float)` values, account IDs; 1.1.2 pasted some names without
escaping, so a quote in one failed the query: `RescanTask3Test::testModulePreferenceApiBindsNamesAndValues`), the title
lookups in `lib/titles.php` (the kill count and a module's `ref`: `testTitlesBindTheReference`), the newest-player
lookups in the four race modules (`newestplayer`, an account ID `create.php` writes), and `battle_spawn()` in
`lib/extended-battle.php` (no caller).

The admin pages bound in earlier tasks (`bio.php`, `mounts.php?op=xml`, the task 2 tools) were checked again by the
scanner: none of their request values remains in SQL text.

## C. Admin repair path (`user.php?op=repair`)

The new schemas refuse stored state 1.1.2 accepted, and a refused value can stop a player on every page (409, nothing
written) until an operator repairs it. Fix Navs repairs navigation only. The user editor now has **Repair stored state**
(Operations, on the edit page), for SU_EDIT_USERS, as every user editor op (`check_su_access()` at the top of `user.php`).

### What it shows

For the selected account it judges six stored values, each by the check the game already applies, and shows only those
that fail, with the reason the check gave and the stored text (escaped). A value that passes is not listed. Nothing else
of the account is shown or editable: this is not a raw editor. The code is `lib/stored_state_repair.php`.

| Value | The check (unchanged, reused) | Where the game applies it |
|---|---|---|
| `prefs` | `PreferenceState::read()` | every page (`lib/forcednavigation.php`), login |
| `bufflist` | the potion entry by `TransmutationState::read()`, then `BuffState::read()` | every page, login |
| `companions` | `CompanionState::read()` | every page |
| `restorepage` | `NavigationState::restorePage()` (a 1.1.2 page with a quote fails) | every page, login |
| `dragonpoints` | `DragonPointState::history()` | every page, login |
| `badguy` | the schema of the route that continues the fight (below) | that route |

A stored fight is judged by the route that continues it, chosen from the fight itself: `options.type` names it
(`forest`: `ForestCombatState::read()`; `dragon`: `DragonCombatState::read()`; `pvp`: `PvpState::read()` for this account;
`graveyard`: the Graveyard route's check, `GraveyardCombatState::read()` with the player's state and the creature it names;
`train`: the Training route's check, the master for the player's level, the challenge made and the experience to train,
then `TrainingCombatState::read()`, with its 1.1.2 upgrade). A creature stored without the envelope is 1.1.2's Dragon
shape, which only the Dragon reads; a pending Dragon victory is checked by `DragonCombatState::victory()`. A fight no
route reads fails. The Graveyard and Training checks were already in `lib/graveyard_combat.php` and
`lib/training_combat.php`; they are moved into named functions (`resurrection_graveyard_torment()`,
`resurrection_training_fight()`, and `resurrection_training_masters()` now takes the account), called by the routes as
before and by the repair page. No rule is added.

While the Dragon Point history fails, Training (which sizes its master from the spent points) is judged against the
history the Dragon Point repair would write, which is what Training sees once that repair is made.

### The repairs

Each failing value has one fixed repair, offered as its own form:

| Value | Repair |
|---|---|
| `prefs` | the defaults: an empty preference map, as a new account has |
| `bufflist` | empty |
| `companions` | empty |
| `restorepage` | `village.php` |
| `badguy` | cleared (`''`, no fight) |
| `dragonpoints` | rebuilt so every spent point stays spent (decision below) |

Each repair is a POST with CSRF (403) and a one-use intent bound to the account, the field and the failing value as
shown (409 when the value changed, when the form is repeated, or for another field). Fields other than the tokens are
400; an unknown field is 400; a missing account is 404. It runs in one transaction that locks the administrator's and
the account's rows, reads the account again under the lock, refuses a value that changed (409) or that now passes its
check (409: a passing value is never "repaired", for example a Training fight whose player has since challenged the
master), writes the one column with the bound repair value, and adds a debug log line on the account naming the
administrator and the field ("Administrator <login> repaired the stored <field>: <repair>"). Any later failure rolls the
write and the log line back (500, "Nothing was changed"). No other column of the account changes. An administrator
repairing their own stored fight gets the same value in the session, so the page's own save agrees.

### Dragon Point decision

The history is the ledger of spent points: `unspent = dragonkills - count(history)`, and the New Day makes a player spend
every unspent point before anything else (`lib/dragon_points.php`, `resurrection_dragon_point_boundary()`, line 117). An
empty history, 1.1.2's reset of a history it could not read, would hand every point back, and the player would buy them
all again on top of the attack, defense and hit points the earlier purchases already gave. So the repair keeps the
count of spent points:

- A stored value that still reads as a list (for example one with an entry in the wrong case) keeps its length and every
  valid entry; each other entry becomes `hp`.
- Anything else becomes one spend per dragon kill, all `hp`. Because the New Day forces every point to be spent, a player
  past their first New Day after a kill has spent exactly one point per kill.

So **no point is handed back**: after the repair the player has the same number of points to spend as before the
history broke (none, for a player past a New Day).

Why `hp` for a spend whose kind is lost: it is the only kind whose whole effect already lives in an account column. At
a dragon kill the game sets maximum hit points to `10 + dkpoints + extra`, where `extra = maxhitpoints - dkpoints -
level*10` (`lib/dragon_outcomes.php`, lines 91 to 98 and 191), so the `hp` entries cancel out and the hit points above the
level's base carry over whole. An `hp` entry adds no forest fight (`ff` entries do, each New Day: `lib/newday_daily_outcomes.php` line 71,
`lib/ramius_outcomes.php` line 71), adds nothing to attack or defense at a kill (`at` and `de` entries do,
`lib/dragon_outcomes.php` line 211), and adds nothing to the Dragon's or a master's strength (both count `at` and `de`
entries). So `hp` grants nothing the account does not already hold. The cost falls on the player and is recorded: a lost
spend that was attack or defense does not come back at the next dragon kill, and one that was a forest fight stops adding
its daily forest fight. The administrator can see the history before the repair on the repair page and in the user
editor, and can restore attack and defense in the user editor.

Not chosen: the display-only `unknown` type the Dragon Point page shows for spends it does not recognize. Training refuses
any history entry other than `hp`, `at`, `de` and `ff` (`TrainingCombatState::budget()`, line 17), so an `unknown` entry
would lock the player out of Training, which is what the repair is for.

A history whose repair would not fit its column (over 4,164 spends) is shown without a repair form.

### Tests

`test_user_editor_stored_state_repair_http`:

- Role matrix: anonymous is sent to login, a player and a superuser with every flag except Edit Users and megauser get
  403 on the page and on a repair; Edit Users and megauser see the page. A missing account is 404. The edit page links
  the repair page.
- With every value valid the page offers nothing.
- For each failing value (preferences with a bad flag, a buff list entry that is not a buff, a companion list entry that
  is not a companion, a 1.1.2 `restorepage` with a quote, a Forest fight without enemies, a fight of a type no route
  continues, an unreadable fight, a truncated Dragon Point history at two kills, and a readable history with an invalid
  entry at three): the logged-in player is refused (409) on the page that checks it; the repair page offers that value
  only; a bad CSRF token (403), a missing intent (409), an extra field (400), the form posted for another field (409) and
  an unknown field (400) change nothing; the repair writes the fixed value, changes no other column and adds one log
  line naming the administrator and the field; repeating it is 409; the value is no longer offered; the player loads the
  village (200).
- Fights by route: a 1.1.2 Training fight the upgrade accepts is not offered; one with a boost the budget does not allow
  is; a Graveyard fight for a living player is. A form for a Training fight that passes by the time it is posted (the
  player challenged meanwhile) is refused (409) and changes nothing.
- A stale form (the value changed after the page) is 409. A late failure (a constraint on the write) is 500 with nothing
  written and no log line; the spent intent is then 409 and a fresh form repairs.
- The administrator's own stored fight: repaired, and the session and the database agree on the next page.

Before the fix the repair page does not exist: `user.php?op=repair` shows the account search, every offered-value check
fails, and the locked-out player stays locked out.

## New gaps and records

Found here; recorded on the `ROADMAP.md` Final rescan list in this commit:

- Training refuses a Dragon Point history with any entry other than `hp`, `at`, `de` and `ff`
  (`TrainingCombatState::budget()`), while the history itself accepts any lower-case identifier and the Dragon Point page
  lets a module add kinds through `dkpointlabels`. 1.1.2 counted only `at` and `de` and ignored the rest. No bundled
  module adds a kind, so no shipped game reaches it. Recorded, not changed.
- `increment_module_setting()` on a setting that does not exist yet inserts it, then adds to a cache entry that is not
  set (a PHP warning, which the game's error handler turns into an error). 1.1.2 code; no bundled module calls the
  function. Recorded, not changed.
- The Hall of Fame shows the translated "Unknown" as raw HTML; translators write it, as they write every translated text.
  Recorded, not changed.
- Correction to `RESCAN-TASK2-20261022.md`: 1.1.2's monthly referer cleanup in `common.php` had been dropped in
  `899cf0b`; it is restored here (section A).

Found and fixed in this commit by its own test: binding the Hall of Fame's "Unknown" as a plain parameter let MySQL 8.4
type it from the integer column beside it in `IF()`, so the page showed `0` instead of the text. It is now cast to text.

## Tests

New:

- `tests/tooling/test_web_application.py`: `test_referer_tracking_fits_its_columns_http`,
  `test_rescan_task3_bound_request_values_http`, `test_unbound_sql_files_are_not_endpoints_http`,
  `test_user_editor_stored_state_repair_http`.
- `tests/unit/SqlSweepTest.php` (the reviewed list), `tests/unit/RescanTask3Test.php` (the referer row, tracking against
  the database, the cleanup, the game log, the module API, the title lookups), with the scanner in
  `tests/unit/SqlInterpolationScanner.php`.

No test was removed, skipped or weakened.

## Before the fix

Each new test was run against `107ce87` (a detached worktree with only the new tests, the scanner and this document
copied in) on a fresh MySQL 8.4 database, then against this change.

At `107ce87`:

- `SqlSweepTest::testEveryValueInterpolatedIntoSqlIsOnTheReviewedList` fails: 49 values interpolated into SQL are not on
  the reviewed list (`hof.php` `$unk`, `lib/commentary.php` `$com*$limit`, `motd.php` `$newcount`, `lib/modules.php`
  `$name`, `lib/titles.php` `$ref`, `common.php`'s referer values, among them). The other two sweep tests pass.
- `RescanTask3Test` cannot load (`lib/referer_tracking.php` does not exist): 16 errors. With that one library left out of
  the test's requires, `testModulePreferenceApiBindsNamesAndValues` and `testTitlesBindTheReference` error with
  "Database operation failed" (a quote in a pasted name or reference). `testGamelogStoresItsTextAsGiven` passes at
  `107ce87` too: its `addslashes()` was safe for that text. `gamelog()` is bound anyway, so the text is never part of the
  query.
- `test_referer_tracking_fits_its_columns_http`: the 78-character host and the 60-character multibyte host each fail the
  page (500, "Database operation failed" from `common.php`), and so does a `Referer` that is not UTF-8. The quoted host
  and path pass at `107ce87` (already bound).
- `test_rescan_task3_bound_request_values_http`, each case on its own: `village.php?comscroll=9223372036854775807` and
  `comscroll=1000000000000000000` are 500 (`lib/commentary.php`); `motd.php?newcount=-1` and
  `newcount=9223372036854775807` are 500 (`motd.php`); `hof.php` and `hof.php?op=days` with the translated "Unknown" are
  500 (`hof.php`). `comscroll=0` and `newcount=5` pass.
- `test_unbound_sql_files_are_not_endpoints_http`: `installer.php` is 403 and `lib/mail.php` already refuses a direct
  request, but `installer_stage_0.php` and `installer_stage_10.php` are 500 (undefined `output()`),
  `installer_stage_9.php` is 500 (a missing include) and `installer_functions.php` is 200 with an empty page.
- `test_user_editor_stored_state_repair_http` fails at its first check: `user.php?op=repair` shows the account search,
  not the repair page.

After this change every one of these passes (see "Local verification").

## Local verification

Supporting evidence only (PHP 8.5.12, MySQL 8.4.13 in Docker); CI is the acceptance.

- PHPUnit on a fresh database: OK (265 tests, 141,142 assertions), including `SqlSweepTest` (3) and `RescanTask3Test`
  (17).
- All four shards, because `common.php` runs on every page: each in its own copy of the tree with its own fresh MySQL
  8.4 database, after PHPUnit OK on that database, through `scripts/tooling-shards.py --shards 4 --shard N`. Shard 1 OK
  (51 tests, 1,074 s), shard 2 OK (48 tests, 1,062 s), shard 3 OK (56 tests, 1,117 s), shard 4 OK (50 tests, 1,114 s):
  205 tests, the four new HTTP tests among them. MariaDB was not run locally.
- `test_route_inventory` and `test_tooling_shards` OK. PHP lint (397 files, 0 failures). Both PHPStan gates: no errors.
  `composer validate --strict` valid; `composer audit` no advisories. Historical verifier PASS (417 files, 11
  commits); project validator PASS; `git diff --check` clean.

## The reviewed list

Every value still interpolated into SQL text in shipped PHP, with its number of sites, class and reason. Kept exact by
`tests/unit/SqlSweepTest.php`; values by rule (a literal table name through `db_prefix()`, a named constant, a number)
are not listed.

<!-- sql-sweep-table -->
| File | Value | Sites | Class | Why |
|---|---|---|---|---|
| `badnav.php` | `$session['user']['acctid']` | 1 | server | Integer `acctid` of the signed-in account row, loaded from the database by common.php. |
| `badword.php` | `$lock ? " FOR UPDATE" : ""` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `bio.php` | `$where` | 1 | server | One of two fixed predicates (`acctid = ?` or `login = ?`); the `char` value is bound. |
| `common.php` | `$session['user']['clanid']` | 1 | server | Integer `clanid` of the signed-in account row. |
| `common.php` | `getsetting('charset','ISO-8859-1')` | 1 | not SQL | The `Content-Type` header text (`charset=`), not a query. |
| `gamelog.php` | `$sqlcat` | 2 | server | Empty or the fixed `WHERE ... category = ?`; the category is bound. |
| `hof.php` | `$extra` | 5 | server | Empty or one of two fixed `dragonkills`/`bestdragonage` predicates chosen by the checked `op`. |
| `hof.php` | `$limit` | 7 | server | The fixed `?,?`; the offset and page size are bound. |
| `hof.php` | `$meop` | 7 | server | `<=` or `>=`, chosen by the checked `op` and `subop`. |
| `hof.php` | `$order` | 28 | server | `ASC` or `DESC`, chosen by the checked `op` and `subop`. |
| `hof.php` | `$racesel` | 2 | server | Fixed `IF(race ...)` expression with the constant `RACE_UNKNOWN`. |
| `hof.php` | `$session['user']['bestdragonage']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$session['user']['charm']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$session['user']['dragonkills']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$session['user']['gems']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$session['user']['goldinbank'] + $session['user']['gold']` | 1 | server | Sum of two integer columns of the signed-in account row. |
| `hof.php` | `$session['user']['maxhitpoints']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$session['user']['resurrections']` | 1 | server | Integer column of the signed-in account row. |
| `hof.php` | `$sexsel` | 1 | server | Fixed `IF(sex, ...)` expression. |
| `hof.php` | `$sql` | 1 | server | The fixed count query above it (only `$standardwhere`). |
| `hof.php` | `$standardwhere` | 15 | server | Fixed predicate built from the constant `SU_HIDE_FROM_LEADERBOARD`. |
| `hof.php` | `$superusermask` | 1 | server | The constant `SU_HIDE_FROM_LEADERBOARD`. |
| `home.php` | `$newplayer` | 1 | server | `(int)` of the `newestplayer` setting. |
| `home.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("LOGINTIMEOUT",900)." seconds"))` | 1 | server | `date()` output: digits, dashes, colons and a space only. |
| `lib/accounts.php` | `implode(',', array_fill(0, count($values), '?'))` | 1 | server | Placeholders; the values are bound. |
| `lib/accounts.php` | `implode(',', array_map('db_identifier', array_keys($values)))` | 1 | server | `db_identifier()` of the fixed column names of the new account array. |
| `lib/admin_authority.php` | `$columns` | 1 | server | Fixed column lists from every caller (`donators.php`, `lib/admin_authority.php`, `lib/user/user_del.php`, `lib/user/user_setupban.php`, `lib/user_editor.php`). |
| `lib/admin_authority.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/ban_admin.php` | `$ban['type'] === 'ip' ? 'ipfilter' : 'uniqueid'` | 1 | server | One of two fixed column names. |
| `lib/char_expiration.php` | `$rules` | 3 | server | Fixed predicates from `resurrection_expire_rules()`; the dates are bound. |
| `lib/clan_authority.php` | `$lock ? ' FOR UPDATE' : ''` | 2 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/commentary.php` | `$cid` | 2 | server | `(int)` of the session's last comment ID. |
| `lib/commentary.php` | `$limit` | 1 | server | `max(1, min(100, (int)$limit))` of the caller's fixed page size. |
| `lib/commentary.php` | `$sectionWhere` | 4 | server | `1=1` (moderation view) or the fixed `section=?`; the section is bound. |
| `lib/commentary.php` | `max(1, min(100, (int)$limit))` | 1 | server | Integer 1 to 100 from the caller's fixed page size. |
| `lib/content_editor.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/content_editor.php` | `$shop['id']` | 1 | server | Fixed column (`weaponid` or `armorid`) from `resurrection_equipment_shop()`. |
| `lib/content_editor.php` | `$shop['name']` | 2 | server | Fixed column (`weaponname` or `armorname`) from `resurrection_equipment_shop()`. |
| `lib/content_editor.php` | `$shop['stat']` | 2 | server | Fixed column (`damage` or `defense`) from `resurrection_equipment_shop()`. |
| `lib/content_editor.php` | `$table` | 6 | server | `db_prefix()` of the fixed `creatures` or `masters` table, or of the equipment table from `resurrection_equipment_shop()`. |
| `lib/content_editor.php` | `db_identifier($column)` | 2 | server | Fixed key column names from the same callers. |
| `lib/content_editor.php` | `db_prefix($table)` | 2 | server | Fixed table names from every caller (`weaponeditor.php`, `armoreditor.php`, `masters.php`, `titleedit.php`, `taunt.php`, `lib/mount_companion_editor.php`). |
| `lib/content_editor.php` | `implode(',', array_fill(0, count($values), '?'))` | 1 | server | Placeholders; the values are bound. |
| `lib/content_editor.php` | `implode(',', array_keys($values))` | 1 | server | The creature form's declared columns (`RESURRECTION_CREATURE_TEXT` and fixed names), never request keys. |
| `lib/content_editor.php` | `implode(',', array_map(static fn (string $key) => "$key=?", array_keys($values)))` | 1 | server | The creature form's declared columns with placeholders; the values are bound. |
| `lib/darkhorse_entry.php` | `$lock?' FOR UPDATE':''` | 2 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/db_optimize.php` | `db_identifier($table)` | 1 | server | `db_identifier()` of a table name from `SHOW TABLES`; a name it refuses is reported, not run. |
| `lib/dbwrapper_pdo.php` | `db_identifier($tablename)` | 1 | server | `db_identifier()` of a caller's table name (letters, digits and `_` only, or an exception). |
| `lib/dragon_points.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/equipment_authority.php` | `$shop['id']` | 1 | server | Fixed column (`weaponid` or `armorid`) from `resurrection_equipment_shop()`. |
| `lib/equipment_authority.php` | `db_prefix($shop['table'])` | 2 | server | Fixed table (`weapons` or `armor`) from `resurrection_equipment_shop()`. |
| `lib/forest_encounter.php` | `$mintargetlevel` | 3 | server | Integer arithmetic on the account's level and typed integer settings, clamped to 1-17. |
| `lib/forest_encounter.php` | `$multi` | 1 | server | Integer from `e_rand()` and typed integer settings, at least 1. |
| `lib/forest_encounter.php` | `$targetlevel` | 3 | server | Integer arithmetic on the account's level, clamped to 1-17. |
| `lib/forest_encounter.php` | `e_rand()` | 3 | server | Integer from the game's random number generator. |
| `lib/graveyard_combat.php` | `e_rand()` | 1 | server | Integer from the game's random number generator. |
| `lib/installer/fresh_install.php` | `db_identifier(db_prefix('accounts'))` | 1 | server | `db_identifier()` of a fixed table name. |
| `lib/installer/fresh_install.php` | `db_identifier(db_prefix('settings'))` | 2 | server | `db_identifier()` of a fixed table name. |
| `lib/installer/installer_functions.php` | `db_identifier($dbname)` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_sqlstatements.php` | `$creaturefields111` | 1 | server | Fixed column list defined at the top of the same file. |
| `lib/installer/installer_sqlstatements.php` | `$creaturefields` | 287 | server | Fixed column list defined at the top of the same file. |
| `lib/installer/installer_sqlstatements.php` | `getsetting("villagename", LOCATION_FIELDS)` | 2 | server | Read only by the CLI installer on an empty database, where it is the installer's default village name. |
| `lib/installer/installer_stage_0.php` | `db_escape(httppost("password"))` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_0.php` | `db_escape(httppost("username"))` | 2 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_0.php` | `md5(md5(stripslashes(httppost("password"))))` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_10.php` | `$name` | 3 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_10.php` | `$pass` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_10.php` | `$su` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/installer/installer_stage_9.php` | `$tablename` | 1 | unreachable | Historical web installer only: `installer.php` answers 403 before any include, and a direct request to this file gets 404 (guard added in this commit; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/lookup_user.php` | `$match[2] ?? 'ASC'` | 1 | server | `ASC` or `DESC`, matched by the ordering pattern. |
| `lib/lookup_user.php` | `$predicate` | 2 | server | Fixed predicate with placeholders; the search text is bound. |
| `lib/lookup_user.php` | `$sort` | 2 | server | `ORDER BY` of an allow-listed column and `ASC`/`DESC`. |
| `lib/lookup_user.php` | `$sql` | 2 | server | Fixed SELECT of the allow-listed columns. |
| `lib/lookup_user.php` | `db_identifier($match[1])` | 1 | server | `db_identifier()` of a column checked against the fixed column list. |
| `lib/lookup_user.php` | `implode(',', array_map('db_identifier', $columns))` | 1 | server | The fixed column list. |
| `lib/mail.php` | `$id` | 6 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `$replyto` | 1 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `$row1['acctid']` | 1 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `$session['user']['acctid']` | 9 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `$to` | 4 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `addslashes($string)` | 1 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("oldmail",14)."days"))` | 1 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail.php` | `join("','",$msg)` | 1 | unreachable | 1.1.2's old mail page: nothing includes it, and a direct request gets 404 (`SERIALIZED-SCHEMAS-PART5-20261022.md`; `test_unbound_sql_files_are_not_endpoints_http`). |
| `lib/mail/case_default.php` | `$accounts` | 3 | server | `db_prefix('accounts')`. |
| `lib/mail/case_default.php` | `$mail` | 2 | server | `db_prefix('mail')`. |
| `lib/mail/case_default.php` | `$session['user']['acctid']` | 1 | server | Integer `acctid` of the signed-in account row. |
| `lib/mail/case_read.php` | `$accounts` | 3 | server | `db_prefix('accounts')`. |
| `lib/mail/case_read.php` | `$mail` | 5 | server | `db_prefix('mail')`. |
| `lib/mail_security.php` | `$operation === 'unread' ? 'UPDATE ' . db_prefix('mail') . ' SET seen=0' : 'DELETE FROM ' . db_prefix('mail')` | 1 | server | One of two fixed statements chosen by the checked operation. |
| `lib/mail_security.php` | `implode(',', array_fill(0, count($ids), '?'))` | 1 | server | Placeholders; the checked message IDs are bound. |
| `lib/modules.php` | `$Pmodule_hooks` | 9 | server | `db_prefix('module_hooks')`. |
| `lib/modules.php` | `$Pmodule_settings` | 1 | server | `db_prefix('module_settings')`. |
| `lib/modules.php` | `$Pmodule_userprefs` | 1 | server | `db_prefix('module_userprefs')`. |
| `lib/modules.php` | `$Pmodules` | 2 | server | `db_prefix('modules')`. |
| `lib/modules.php` | `$active` | 2 | server | Empty or the fixed `active = 1 AND` condition. |
| `lib/modules.php` | `$modulelist` | 2 | server | Placeholders for the module names, which are bound. |
| `lib/modules.php` | `db_identifier($column)` | 2 | server | `db_identifier()` of the fixed column list of `install_module()`. |
| `lib/modules.php` | `db_prefix($table)` | 1 | server | Fixed table (`module_userprefs` or `module_settings`) from `newday_runonce.php`. |
| `lib/modules.php` | `e_rand()` | 1 | server | Integer from the game's random number generator. |
| `lib/modules.php` | `implode(',', $updates)` | 1 | server | `db_identifier()` of the fixed column list with `VALUES()`. |
| `lib/modules.php` | `implode(',', array_fill(0, count($columns), '?'))` | 1 | server | Placeholders; the values are bound. |
| `lib/modules.php` | `implode(',', array_fill(0, count($keys), '?'))` | 1 | server | Placeholders; the setting names are bound. |
| `lib/modules.php` | `implode(',', array_fill(0, max(1, count($hooknames)), '?'))` | 1 | server | Placeholders; the hook names are bound. |
| `lib/modules.php` | `implode(',', array_map('db_identifier', $columns))` | 1 | server | The fixed column list of `install_module()`. |
| `lib/motd.php` | `$lock ? " FOR UPDATE" : ""` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/motd.php` | `implode(',', $sets)` | 1 | server | Fixed `column=?` assignments; the values are bound. |
| `lib/mount_companion_editor.php` | `$column` | 2 | server | Fixed key column (`mountid` or `companionid`) from every caller. |
| `lib/mount_companion_editor.php` | `$flag` | 1 | server | Fixed column (`mountactive` or `companionactive`) from `mounts.php` and `companions.php`. |
| `lib/mount_companion_editor.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/mount_companion_editor.php` | `$name` | 2 | server | `db_prefix()` of the fixed `mounts` or `companions` table. |
| `lib/mount_companion_editor.php` | `db_prefix($table)` | 1 | server | Fixed table (`mounts` or `companions`) from every caller. |
| `lib/mount_companion_editor.php` | `implode(',', array_fill(0, count($values), '?'))` | 1 | server | Placeholders; the values are bound. |
| `lib/mount_companion_editor.php` | `implode(',', array_keys($values))` | 1 | server | The form's declared columns (`RESURRECTION_MOUNT_*`, `RESURRECTION_COMPANION_*` and fixed names), never request keys. |
| `lib/mount_companion_editor.php` | `implode(',', array_map(static fn (string $key) => "$key=?", array_keys($values)))` | 1 | server | The form's declared columns with placeholders; the values are bound. |
| `lib/newday/newday_runonce.php` | `$timestamp` | 2 | server | `date()` output. |
| `lib/newday/newday_runonce.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("expirecontent",180)." days"))` | 2 | server | `date()` output. |
| `lib/newday/newday_runonce.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("oldmail",14)."days"))` | 1 | server | `date()` output. |
| `lib/newday/newday_runonce.php` | `date("Y-m-d")` | 1 | server | `date()` output. |
| `lib/newday_daily.php` | `$suffix` | 6 | server | Empty or the fixed ` FOR UPDATE`. |
| `lib/pageparts.php` | `$session['user']['acctid']` | 1 | server | Integer `acctid` of the signed-in account row. |
| `lib/pageparts.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("LOGINTIMEOUT",900)." seconds"))` | 1 | server | `date()` output. |
| `lib/partner.php` | `$session['user']['marriedto']` | 1 | server | Integer `marriedto` column of the signed-in account row. |
| `lib/player_mail.php` | `$draft['settings']['onlyunreadmails'] ? ' AND seen=0' : ''` | 1 | server | Empty or a fixed condition chosen by a setting. |
| `lib/player_mail.php` | `$lock ? ' FOR UPDATE' : ''` | 2 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/player_mail.php` | `$lock ? ' LOCK IN SHARE MODE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/player_mutation.php` | `implode(',', $sets)` | 1 | server | `db_identifier()` of account columns present in the base row, with placeholders; the values are bound. |
| `lib/pvpsupport.php` | `$badguy['acctid']` | 1 | server | Cast with `(int)` at the top of `pvpdefeat()`. |
| `lib/pvpsupport.php` | `$winamount` | 1 | server | `round()` of the account's level and gold. |
| `lib/pvpsupport.php` | `$wonexp` | 1 | server | `round()` of the account's experience and a typed integer setting. |
| `lib/pvpsupport.php` | `(int)$badguy['acctid']` | 1 | server | Integer cast. |
| `lib/race_onboarding.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/ramius_resurrection.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/referer_tracking.php` | `$table` | 4 | server | `db_prefix('referers')`. |
| `lib/saveuser.php` | `implode(',', $assignments)` | 1 | server | `db_identifier()` of account columns present in the base row, with placeholders; the values are bound. |
| `lib/service_authority.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/specialty_combat.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/specialty_onboarding.php` | `$lock ? ' FOR UPDATE' : ''` | 2 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/stables_authority.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/stored_state_repair.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/stored_state_repair.php` | `db_identifier($field)` | 1 | server | `$field` is one of the six repair field names (`RESURRECTION_REPAIR_FIELDS` keys), checked before any query. |
| `lib/tabledescriptor.php` | `$existing[$key]['name']` | 1 | server | A column or key name read from the table's own `SHOW COLUMNS`/`SHOW KEYS` during installation. |
| `lib/tabledescriptor.php` | `$tablename` | 3 | server | A table name from the fixed descriptors (`lib/all_tables.php`) during installation. |
| `lib/tabledescriptor.php` | `$val['name']` | 2 | server | A column or key name read from the table's own `SHOW COLUMNS`/`SHOW KEYS` during installation. |
| `lib/tabledescriptor.php` | `db_identifier($tablename)` | 1 | server | `db_identifier()` of a table name from the fixed descriptors. |
| `lib/tabledescriptor.php` | `join(",\n",$changes)` | 1 | server | Column definitions built from the fixed descriptors during installation. |
| `lib/taunt.php` | `e_rand()` | 2 | server | Integer from the game's random number generator. |
| `lib/titles.php` | `$useref` | 1 | server | Empty or the fixed `AND ref=?`; the ref is bound. |
| `lib/titles.php` | `e_rand()` | 1 | server | Integer from the game's random number generator. |
| `lib/training_combat.php` | `$table` | 2 | server | `db_prefix('masters')`. |
| `lib/training_outcomes.php` | `$session['user']['referer']` | 1 | server | Integer `referer` column of the signed-in account row. |
| `lib/training_outcomes.php` | `getsetting("refereraward",25)` | 1 | server | A typed integer setting (`refereraward`, int in the settings descriptors; the typed editor stores only an integer). |
| `lib/translator.php` | `$where` | 1 | server | One of two fixed predicates with placeholders. |
| `lib/typed_editor.php` | `$lock?' FOR UPDATE':''` | 4 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/typed_editor.php` | `$object['id']` | 1 | server | Fixed key column from `resurrection_object_types()`. |
| `lib/typed_editor.php` | `db_prefix($type)` | 1 | server | A table name that is a key of `resurrection_object_types()` (refused otherwise). |
| `lib/user/user_debuglog.php` | `$accounts` | 4 | server | `db_prefix('accounts')`. |
| `lib/user/user_debuglog.php` | `$debuglog` | 12 | server | `db_prefix('debuglog')`. |
| `lib/user/user_removeban.php` | `$since` | 1 | server | Fixed predicates with placeholders; the dates are bound. |
| `lib/user_editor.php` | `$lock ? ' FOR UPDATE' : ''` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `lib/user_editor.php` | `substr($sql, 0, -1)` | 1 | server | `db_identifier()` of account columns present in the stored row, with placeholders; the values are bound. |
| `list.php` | `$limit` | 1 | server | Empty or the fixed ` LIMIT ?,? `; the page is bound. |
| `list.php` | `$search` | 1 | server | Empty or the fixed ` AND name LIKE ? `; the pattern is bound. |
| `list.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("LOGINTIMEOUT",900)." seconds"))` | 2 | server | `date()` output. |
| `moderate.php` | `$limit` | 1 | server | The fixed `75`. |
| `moderate.php` | `$session['user']['clanid']` | 1 | server | Integer `clanid` of the signed-in account row. |
| `moderate.php` | `$where` | 1 | server | `1=1` and the fixed `AND moderator=?`; the moderator is bound. |
| `modules.php` | `$order?"ASC":"DESC"` | 1 | server | `ASC` or `DESC`. |
| `modules.php` | `db_identifier($sortby)` | 1 | server | A column from the fixed four-value choice (`Input::choice()`). |
| `modules/dag/install.php` | `$row1['acctid']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/dag/install.php` | `$row1['bounties']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/dag/install.php` | `date("Y-m-d H:i:s")` | 1 | server | `date()` output. |
| `modules/dag/misc_functions.php` | `$d` | 1 | server | The fixed ` ASC` or ` DESC`, chosen by a switch. |
| `modules/dag/misc_functions.php` | `$exact ? ' WHERE name=?' : " WHERE name LIKE ? ESCAPE '!'"` | 1 | server | One of two fixed predicates with a placeholder. |
| `modules/dag/misc_functions.php` | `$s` | 1 | server | A fixed ` ORDER BY` column, chosen by a switch. |
| `modules/dag/misc_functions.php` | `$t` | 2 | server | Fixed conditions and lists of `(int)` account IDs read from the database. |
| `modules/dag/misc_functions.php` | `join(",",$ids)` | 3 | server | `(int)` account IDs read from the database (0 when none). |
| `modules/drinks/dohook.php` | `$hard` | 1 | server | Empty or the fixed `AND harddrink=0`. |
| `modules/drinks/install.php` | `$row['Field']` | 4 | server | A column name from `SHOW COLUMNS`, used only after matching one of the fixed names. |
| `modules/drinks/misc_functions.php` | `implode(',',$sets)` | 1 | server | `db_identifier()` of the drink form's declared columns with placeholders; the values are bound. |
| `modules/drinks/misc_functions.php` | `implode(',',array_fill(0,count($values),'?'))` | 1 | server | Placeholders; the values are bound. |
| `modules/drinks/misc_functions.php` | `implode(',',array_map('db_identifier',array_keys($values)))` | 1 | server | `db_identifier()` of the drink form's declared columns (`DrinkInput::parse()`). |
| `modules/goldmine.php` | `$suffix` | 4 | server | Empty or the fixed ` FOR UPDATE`. |
| `modules/lovers.php` | `$row1['acctid']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/lovers.php` | `$row1['seenlover']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/lovers.php` | `$suffix` | 2 | server | Empty or the fixed ` FOR UPDATE`. |
| `modules/outhouse.php` | `$row1['acctid']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/outhouse.php` | `$row1['usedouthouse']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/sethsong.php` | `$row1['acctid']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/sethsong.php` | `$row1['seenbard']` | 1 | server | Integer column read from the accounts table during the module's install migration. |
| `modules/sethsong.php` | `e_rand()` | 1 | server | Integer from the game's random number generator. |
| `modules/specialtydarkarts.php` | `$specialty` | 1 | server | The module's fixed specialty code, set in the same function. |
| `modules/specialtymysticpower.php` | `$specialty` | 1 | server | The module's fixed specialty code, set in the same function. |
| `modules/specialtythiefskills.php` | `$specialty` | 1 | server | The module's fixed specialty code, set in the same function. |
| `news.php` | `date("Y-m-d H:i:s",strtotime("-".getsetting("expirecontent",180)." days"))` | 1 | server | `date()` output. |
| `paylog.php` | `$lock ? " FOR UPDATE" : ""` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `prefs.php` | `$session['user']['acctid']` | 1 | server | Integer `acctid` of the signed-in account row. |
| `prefs.php` | `implode("','",$foundmodules)` | 1 | server | Names of installed active modules read from the modules table (bundled file names; the module manager installs only bundled modules). |
| `referers.php` | `$order` | 1 | server | From the fixed seven-entry sort table, chosen by the checked `sort`. |
| `referers.php` | `$siteorder` | 1 | server | From the fixed seven-entry sort table, chosen by the checked `sort`. |
| `referral.php` | `$session['user']['acctid']` | 1 | server | Integer `acctid` of the signed-in account row. |
| `stats.php` | `$row['acctid']` | 1 | server | Integer `acctid` read from the accounts table. |
| `translatortool.php` | `$lock ? " FOR UPDATE" : ""` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `untranslated.php` | `$lock ? " FOR UPDATE" : ""` | 1 | server | Fixed lock clause chosen by a boolean argument. |
| `viewpetition.php` | `$sort` | 1 | server | Repeated fixed ` WHEN ? THEN ?`; the status values are bound. |
| `village.php` | `$newestplayer` | 1 | server | The `newestplayer` setting, written only by `create.php` as the new account's integer ID (not in the settings editor); the page also requires a non-zero integer. |
<!-- /sql-sweep-table -->
