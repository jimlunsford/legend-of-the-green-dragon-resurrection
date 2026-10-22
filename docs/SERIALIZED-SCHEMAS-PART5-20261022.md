# Serialized business schemas, part 5: news arguments, other state, MOTD, payment log, comment moderation

Date: 2026-10-22. Starting head: `5bdba80`. Scope: items 9 and 10 of the split in
[part 1](SERIALIZED-SCHEMAS-PART1-20261017.md) (the "Translation arrays" and "Other business
state" lines of `ROADMAP.md`), `motd.php` as a route family, and two Final rescan lines:
`paylog.php`'s GET `processdate` write and `moderate.php`'s missing 1.1.2 longer-ban check. The
player mail routes, the clan MOTD and the news and bio display beyond the argument check are not
opened.

Items 9 and 10 were checked against the source at `5bdba80` before the change. Both held as
written in part 1, with three additions found while testing (below): `motd.php` failed on PHP 8
when no MOTD exists, its archive failed under strict SQL for a 30-day month, and `paylog.php`
failed on MySQL 8.4 before it showed anything.

## The schemas

Each is a small class in `src/Security/` (or `src/Game/`) that loads its own dependencies, read
through `ScalarState` (no objects, depth and node limits, canonical bytes) and then checked for
structure and size. A read that fails throws `DomainException`; a writer stores only what its
reader accepts.

| Schema | Stored value | Accepts | Refuses |
|---|---|---|---|
| `NewsArguments` | `news.arguments` | `''`/NULL (no arguments); a list of at most 64 items, each a scalar or NULL, or a one-level translation envelope: a non-empty list of scalars whose format is not NULL or a boolean, and which, when it starts with a boolean, starts with `true`, a schema name and a format (1.1.2's taunt `[true,'taunts',format,args...]`); at most 65,535 bytes (TEXT) | maps, deeper nesting, empty envelopes, `false` or a missing schema in an envelope, objects, NaN/INF |
| `DragonPointState::history()` | `accounts.dragonpoints` at load | `''`/NULL (1.1.2's `create.php` never set it), a list of identifiers (`[a-z][a-z0-9_]{0,63}`, removed module names stay readable), at most 65,535 bytes | anything else |
| `DragonPointState::encode()` | the page save and the player transaction | a history `history()` accepts | anything else |
| `CommentSnapshot` | `moderatedcomments.comment` | the commentary columns (`commentid` ≥ 1, `author` ≥ 0, `section` up to 20 characters or NULL, `comment` up to 200 UTF-8 characters, `postdate` a datetime or NULL) plus any of 1.1.2's two author key sets (`moderate.php`: `name`, `login`, `clanrank`, `clanshort`; the commentary Del link: `name`, `acctid`, `clanrank`, `clanshort`), each typed and bounded | missing commentary keys, unknown keys, values that would not fit the restore INSERT |
| `PaymentInfo` | `paylog.info` | 1.1.2's `serialize($_POST)`: a flat map of at most 512 fields to strings, at most 65,535 bytes | `''`, nested values, non-strings |
| `MotdPoll` | `motd.motdbody` of a poll | exactly `{body: string, opt: {choice number: label}}`, at most 100 options, choice numbers 0 to 4294967295 (`pollresults.choice`), at most 65,535 bytes | other keys or order, `opt` not an array (1.1.2 could store `false`), non-string labels |

The news schema checks structure and size only. It does not compare the number of format
directives with the arguments, so 1.1.2 taunts with a stray `%` still render
(`test_news_and_bio_format_stored_stray_percent_http` is unchanged and passes).

## A. Translation arrays

**News arguments.**

- Writer: `addnews_for_user()` stores `NewsArguments::encode()`. A refused argument list
  throws, so the caller's transaction rolls back. PHPUnit proves it against the database: a
  first news line in the same transaction is rolled back with it.
- `news.php` skips a stored line whose arguments fail. A comment moderator still gets that
  line's separator and Del link, so it can be removed. `bio.php` skips it.
- Before the fix, every malformed row was a 500 for both pages: a TypeError in
  `resurrection_array_next()`, or an `ErrorException` in `sprintf_translate()`.

**`lib/mail.php`: recorded as excluded, not switched.**

- It is an inert duplicate of the pre-split mail page. The live code is `mail.php` with
  `lib/mail/case_*.php`, whose readers use `MailContent::stored()`.
- No tracked file includes it, at this head or at `historical-source-1.1.2`. Evidence:
  `git grep "lib/mail\.php"` over the PHP sources of both trees finds no include; every
  `mail.php` reference resolves to the root entrypoint.
- Requested directly, it stops at `require_once("common.php")`, as recorded in
  `MAIL-AUTHORITY-20261004.md`, before any database or mail code runs.
- The file is kept, because it is historical and nothing needs it removed. It now starts with
  the guard `lib/training_outcomes.php` uses: a direct request gets 404 "Not an application
  endpoint." Its ten `ScalarState` reads are the 10 excluded sites of the audit.

## B. Other business state

**Dragon points at session load.**

- `lib/forcednavigation.php` and `login.php` read the stored history with
  `DragonPointState::history()`. A corrupt history now gives 409 with nothing written:
  - at login, "Invalid stored Dragon Point state. You were not logged in.", checked before the
    login is recorded;
  - on any page, "Invalid stored Dragon Point state. No game action was completed."
- 1.1.2, and this branch until now, replaced an unreadable history with `[]`. The next save then
  wrote it back empty, which handed the spent points back to be spent again.
- The kill-count limit is not applied at load. The user editor can lower `dragonkills` below the
  points already spent, and 1.1.2 kept such an account playable. `DragonPointState::read()` still
  applies the limit when points are spent.
- The two save writers (`saveuser()` and `resurrection_player_mutation()`) store the history
  through `DragonPointState::encode()`. They now refuse any other array field instead of
  serializing it unchecked. Every stored collection already had its encoder: navigation,
  preferences, buffs, companions, and now dragon points.

**The moderated comment snapshot.**

- `resurrection_archive_comment()` stores `CommentSnapshot::encode()`. It is the one snapshot
  writer, used by the commentary Del button and by `moderate.php`.
- `resurrection_restore_comment()` reads `CommentSnapshot::read()`. A refused snapshot gives 400
  and nothing is restored.
- The audit page (`moderate.php?op=audit`) lists a refused snapshot with its moderator and date
  only, with no checkbox.
- Found while testing: the audit page gave a 500 for a clan founder's comment. Rank 31 maps to
  `$clanrankcolors[4]`, and `moderate.php`'s list has four colors. 1.1.2 (PHP 5) showed nothing
  there, and so does the page now (`?? ''`). The comment page's own list has five colors and is
  unchanged.

**The payment log.**

- Display: `PaymentInfo::read()`. A record that fails is listed with its own columns
  (transaction ID, processed, who) and blank payment fields. A record without a parseable
  `payment_date` shows an empty date.
- For the `processdate` write, see D.

**The MOTD poll body.** `pollitem()` reads `MotdPoll::read()`; the poll form stores
`MotdPoll::encode()` (C).

## C. motd.php as a route family

`motd.php` and `lib/motd.php` were byte-identical to 1.1.2. The command chat's findings, each
verified against the source at `5bdba80`:

| Finding | At `5bdba80` (and 1.1.2) | Now |
|---|---|---|
| Vote SQL | `motditem` and `choice` from POST pasted into a DELETE and an INSERT | Bound; both must be whole numbers (400 otherwise, including a quote in either value) |
| Vote method and replay | POST, but no CSRF and no intent; replayable | POST with CSRF and a one-use intent (`motd-vote-N`) bound to the poll as shown; a replay is 409 |
| Choice outside the poll | Accepted: any `choice` was stored, for any `motditem`, even a non-poll or a missing one | The poll must exist and be a poll (404 otherwise); the choice must be one the poll lists, an option with a label, as `pollitem()` shows (400 otherwise); a stored poll that fails its schema refuses every vote (409) |
| Anonymous vote | Ran the SQL with an empty account | 403 "Sign in to vote." |
| One vote per player | DELETE then INSERT, no transaction | Kept, with a transaction: one vote per player per poll, and voting again replaces it, as in 1.1.2. The poll row is locked first, so concurrent votes by one player cannot leave two rows |
| Admin add, edit, poll, delete SQL | Pasted `id`, `subject`, `body`; poll body stored as `addslashes(serialize(...))` inside double quotes | Bound; subject at most 200 characters (`motdtitle` is varchar(200)), body at most 65,535 bytes, both UTF-8 (400 otherwise); the poll body is `MotdPoll::encode()` |
| Capability check | `SU_POST_MOTD` gates add, addpoll and del | Unchanged and covering every admin op; checked on every request against the account row loaded at session start |
| Admin GET mutations | `op=del&id=N` deleted on GET (a link with a JavaScript confirm) | Del is a POST form (the same confirm) with CSRF and a one-use intent (`motd-del-N`) bound to the item; GET is 405 and changes nothing |
| Admin replay and staleness | none | Add, edit and poll creation each take a one-use intent; edit and delete are bound to the item as shown (an item edited or deleted meanwhile is 409); preview writes nothing and offers a fresh form |
| Editing a poll | `op=add&id=<poll>` showed the serialized body for editing and saved text over it | 400: polls cannot be edited after they are begun (1.1.2's own note on the poll form) |
| Non-admin admin op | A signed-in player who requested `op=add`, `addpoll` or `del` by any method lost 10% experience, with a news line, so a link elsewhere could cost a player | GET (and anonymous, any method) is 403 and changes nothing. The player's own POST with a valid CSRF token gets 1.1.2's penalty and both texts, in one player transaction |
| Archive month | `month` pasted into SQL | `YYYY-MM` only (400 otherwise), bound |
| Output escaping | The MOTD body and the poll author were output as raw HTML (`output_notl(..., true)`) | Escaped, with LoGD colour and format codes kept, as everywhere else (`appoencode` without the private flag); subject, poll body and options were already escaped |

The penalty keeps 1.1.2's behavior where it was meant to apply: a player who tampers with a
request. It needs the player's CSRF token, which only their own session holds. It takes no
one-use intent. It is not an action the player asks for, and each attempt is its own attempt.

Also fixed, found while testing:

- **`motd.php` with no MOTD at all** read `$row['motddate']` from `false` at the end of the
  page. On PHP 8 that is a 500, for every signed-in visitor of a fresh install. PHP 5 read
  NULL, and so does the page now.
- **The archive** compared `motddate <= '{$m}-31'`. For a 30-day month MySQL 8.4 refuses that
  with error 1525 (incorrect DATETIME value), so the archive was a 500. It also left out
  anything posted after midnight on the 31st, which the archive list still counted. The query
  now covers the whole month, from the first of the month up to the first of the next.

Unchanged from 1.1.2:

- every text;
- the poll's stored form: request values with the slashes magic quotes added, shown through
  `stripslashes()`. The writer adds them, so old and new polls display alike;
- the cache keys;
- `newcount` paging, which never worked in 1.1.2 (`httppost('proceed')` on a GET form) and
  still does not;
- the archive list skipping its first month row;
- `motd.php` allowing anonymous visitors and overriding forced navigation.

The one change to request handling: 1.1.2's `stripslashes()` on the request subject and body in
the edit preview undid magic quotes. Magic quotes no longer apply, so those values are used as
typed. Stored values are still shown through `stripslashes()`, as before.

## D. paylog.php processdate

- 1.1.2 stamped every payment without a process date with its own `payment_date`, on every
  GET of the payment log, with an unbound UPDATE (`txnid` only `addslashes`).
- That write is now `paylog.php?op=process`: POST with CSRF and a one-use intent
  (`paylog-process`). The intent is bound to the pending payments and the dates they would get,
  so a payment added meanwhile makes the form stale (409). It keeps the existing
  `check_su_access(SU_EDIT_PAYLOG)`.
- The UPDATE is bound, runs per payment (`payid`, still pending) inside a transaction, and
  re-checks the pending set under lock.
- GET writes nothing. It shows "N payments have no process date yet." and the button when there
  are any.
- "Pending" is the fresh schema's NULL `processdate`. The fresh install turns every zero-date
  default into NULL ("not yet"), and no historical database is migrated
  (`BIO-DONATION-ADMIN-AUTHORITY-20261016.md`).
- A record whose info fails `PaymentInfo`, or has no parseable `payment_date`, is left pending.
  1.1.2 stamped it 1970-01-01.
- The display's `month` must be `YYYY-MM` and is bound.
- Payment intake stays disabled: `payment.php` still answers 410 and is not touched.

Found while testing, both on MySQL 8.4 only: the page failed before showing anything.

- `WHERE processdate='0000-00-00'` is refused under the application's strict SQL modes
  (error 1525).
- `GROUP BY month DESC` is a syntax error, because MySQL 8.0 removed `GROUP BY ... DESC`.

The pending query no longer uses the zero date. The month list is `GROUP BY month ORDER BY month
DESC`, the same order, and leaves out payments not yet dated.

## E. moderate.php's longer-ban check

1.1.2 (`moderate.php`, "Delete Checked & Ban (3 days)"):

- selected each distinct author's ID;
- for each, read a ban for that ID; when one existed whose expiry was not earlier than the new
  one, it added no ban and left the author logged in.

At `5bdba80` the ban was always inserted, once per selected comment.

Now `resurrection_moderate_comments()` restores the rule in one transaction that covers the
bans, the logouts, the audit snapshots and the deletes:

- For each selected comment, the author's existing bans for that ID are locked (`FOR UPDATE`).
- When one has a NULL expiry or one at least as long as the new one, no ban is added and the
  author is not logged out.
- All SQL is bound. A failure rolls back all four kinds of write.

Differences from 1.1.2:

- A NULL expiry is permanent in this branch (the user editor's permanent ban), so it counts as
  longer. 1.1.2's permanent ban was `0000-00-00`, which compared as shorter, so 1.1.2 added a
  3-day ban next to a permanent one.
- 1.1.2 looked only at the first ban row it read; any longer ban now counts.
- Two comments by one author give one ban: the second sees the first. 1.1.2 got the same result
  from `DISTINCT`.
- An account without an ID (NULL as well as `''`) gets no ban.

The moderation form itself (CSRF, no intent) is unchanged.

## 1.1.2 comparison

| Case | 1.1.2 | `5bdba80` | Now |
|---|---|---|---|
| News line with malformed stored arguments | PHP 5 warnings, partial line | 500 on news.php and bio.php | skipped (moderator keeps the Del link) |
| News line with a stray-% taunt | renders | renders | renders |
| Corrupt stored Dragon Point history | reset to empty, points spent again | reset to empty | 409, nothing written |
| Vote for a choice the poll does not list, or for a non-poll | stored | stored | 400 / 404 |
| Vote replay | stored again | stored again | 409 |
| MOTD delete by GET link | deleted | deleted | 405; delete is a protected POST |
| Player requests an admin MOTD op by GET | 10% experience lost | 10% experience lost | 403, nothing changed |
| Player's own protected POST to an admin MOTD op | (no CSRF existed) | 10% experience lost | 10% experience lost, both texts |
| MOTD body HTML | raw | raw | escaped, colour codes kept |
| motd.php with no MOTD | renders | 500 | renders |
| MOTD archive, 30-day month | renders (permissive SQL) | 500 on MySQL 8.4 | renders |
| Payment log GET | stamps pending dates | 500 on MySQL 8.4 | displays only |
| Comment ban with a longer ban existing | no new ban | new ban, logged out | no new ban |
| Audit page with a founder's comment | renders (notice) | 500 | renders |

## Closing status of the audit categories

Per-site status is in `SERIALIZED-STATE-AUDIT.md`, "News arguments, mail and other business
state, part 5 candidate". When this part is accepted:

- **News arguments:** 3 of 3 CLOSED.
- **Mail:** 8 CLOSED; the 10 `lib/mail.php` reads are excluded with the evidence above.
- **Other:** every site CLOSED or n/a. The 11 OPEN at the 2026-10-17 inventory are CLOSED here,
  including the two generic save sites, which closed with dragon points as the inventory said.
- **Every other category** was already closed in parts 1 to 4.

No site is left OPEN. The remaining gaps are Final rescan lines (below).

## Inventory

Recount (working tree, same token method; it reproduces 94 / 48 / 39 / 54 / 1 at `5bdba80`):
**91 sites / 44 files / 38 serialize writers-checks / 52 ScalarState reads / one centralized
unserialize**. The per-site moves are in `SERIALIZED-STATE-AUDIT.md`, "News arguments, mail and
other business state, part 5 candidate". ScalarState limits are unchanged.

## Tests

PHPUnit, one per schema plus the writer's transaction:

- `NewsArgumentsTest`: 1.1.2 argument lists and taunt envelopes (including a stray `%`) pass;
  malformed stored values (maps, nesting, empty or `false`-led envelopes, a NULL format,
  objects, oversize) are refused; the writer refuses what news cannot render.
- `SecurityIntegrationTest::testNewsWriterRefusesUnrenderableArgumentsAndTheTransactionRollsBack`:
  against the database, a refused `addnews_for_user()` rolls back a news line written earlier
  in the same transaction.
- `DragonPointStateTest::testSessionLoadHistory`: NULL and `''` are empty, a removed module's
  identifier and a history beyond the kill count load; corrupt values are refused; `encode()`
  stores only what `history()` reads back.
- `CommentSnapshotTest`: both 1.1.2 author key sets and the modern writer pass; missing or
  unknown keys and values that would not fit the restore are refused.
- `PaymentInfoTest`: 1.1.2 PayPal fields pass, with the date read as 1.1.2 did; `''`, nested
  values and non-strings are refused.
- `MotdPollTest`: a 1.1.2 poll (slashes, blank options) passes and lists only labelled choices;
  `opt` as `false`, extra keys, bad choice numbers and non-string labels are refused.

HTTP (`tests/tooling/test_web_application.py`):

- `test_news_and_bio_skip_malformed_arguments_http`: malformed rows on news.php and bio.php are
  skipped and the rows around them render; a comment moderator keeps the Del link; the stray-%
  taunt still renders (and `test_news_and_bio_format_stored_stray_percent_http` is unchanged).
- `test_dragon_point_history_checked_at_load_http`: six corrupt histories, each refused at login
  and on a page with 409 and nothing written; NULL and a history beyond the kill count load.
- `test_motd_route_authority_http`: the role matrix (anonymous, player, admin without and with
  `SU_POST_MOTD`); escaping with colour codes kept; the archive month; GET admin ops refused
  without a write; the player's own protected POST keeps 1.1.2's penalty; votes valid, repeated
  (409), changed with a fresh form, a wrong option, an unknown poll, a non-poll, a quote in
  either value, anonymous; a poll that fails its schema; admin add (preview, CSRF, replay), edit
  (stale), poll (stored form, replay) and delete (GET 405, CSRF, replay).
- `test_paylog_process_dates_post_only_http`: GET writes nothing; POST without CSRF, with extra
  fields, stale, or replayed is refused; the protected POST dates the valid records only; a
  malformed record is listed with blank fields.
- `test_comment_ban_respects_longer_existing_ban_http`: a ban with and without a longer
  existing ban (and a permanent one), two comments by one author, delete without ban, the audit
  page with a founder's comment and a malformed snapshot, and a restore.

## Before the fix

Each new test was run against copies of the tree with production files put back to `5bdba80`
(tests kept), on a fresh MySQL 8.4, then on the final tree.

| Revert | Production put back | Result before the fix |
|---|---|---|
| R0 | every production file of this commit | PHPUnit: the new classes are missing (10 errors, 1 failure). HTTP: news 500 (`translator.php:87`); Dragon Point test 500 (the `motd.php` no-MOTD fatal); MOTD TypeError in `resurrection_array_next()` (the broken poll); paylog 500 "Database operation failed"; a 3-day ban added next to the longer ban |
| R1 | `news.php`, `bio.php` | news test fails: 500 at `translator.php:87` |
| R2 | `lib/addnews.php` | the PHPUnit writer test fails: the refused line is stored |
| R3 | `lib/forcednavigation.php` | 6 subtests: 200 instead of 409 |
| R4 | `login.php` | 6 subtests: 303 (logged in) instead of 409 |
| R5 | `moderate.php`, `lib/commentary_security.php` | ban list differs: a 3-day ban added next to the longer ban |
| R6 | `paylog.php` | 500 |
| R7 | `motd.php`, `lib/motd.php` | MOTD TypeError; the Dragon Point test also fails (the no-MOTD fatal) |
| R8 | only the longer-ban check removed | `['2026-11-01 11:21:30'] != ['2026-10-25 11:21:32', '2026-11-01 11:21:30']` |
| R9 | only the vote's choice check removed | `400 != 302 : {'choice': '1'}` |
| R10 | only GET's `processdate` stamp restored | the GET stamped `TXN-PAYLOG-1` (`None` expected) |
| R11 | only the GET penalty restored | `403 != 200 : motd.php?op=add` |

After the fix every test above passes (local verification below).

## Local verification

Supporting evidence only (PHP 8.5, disposable MySQL 8.4); CI is the acceptance.

- PHPUnit: 205 tests OK on a fresh database (run once per shard copy, four times).
- All four shards through `scripts/tooling-shards.py` (news, Dragon Point history and the MOTD
  are read on common paths): shard 1, 52 tests OK (1,249 s); shard 2, 43 OK (1,240 s); shard 3,
  45 OK (1,353 s); shard 4, 45 OK (1,279 s).
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`): no errors.
- PHP lint of every changed file, `composer validate --strict`, install, `composer audit` (no
  advisories), historical verifier PASS (417 files, 11 commits), project validator,
  `tests/tooling/test_tooling_shards.py`, `git diff --check`: all pass.

## Remaining gaps

On the `ROADMAP.md` Final rescan list, added or extended in this commit:

- News delete: the news page's Del link is a GET to `superuser.php?op=newsdelete`, which
  deletes with `newsid` pasted into SQL, with no CSRF or intent, open to any staff member who
  can enter the grotto. Not opened here: the brief keeps the news display beyond the argument
  check out of scope.
- The admin repair path line now also names a corrupt Dragon Point history: the player gets a
  409 on every page, and the user editor shows the history read-only.
- D (`paylog.php`) and E (`moderate.php`) stay open until CI accepts this candidate.

Recorded, not changed (1.1.2 behavior): the MOTD penalty on the player's own protected POST,
`newcount` paging, and the archive list skipping its first month row. The comment moderation
form keeps CSRF without a one-use intent: a replay finds the comments already gone, and the
ban step only bans the author of a comment that still exists, so a replay changes nothing.
