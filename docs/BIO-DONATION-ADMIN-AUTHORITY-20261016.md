# Bio moderation and donation admin authority, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `2a48f35450e2d7cfea1e02d06da55011b3e567be`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Predecessor runs for `2a48f35` (the haunting follow-up), inspected once at the start of
this task: Baseline integrity 38884592513 SUCCESS; Modern core 38884592810 still in
progress. Haunting therefore stays in Remaining.

Scope: two superuser surfaces.

- `bios.php` (`SU_EDIT_COMMENTS`): block and unblock.
- `donators.php` (`SU_EDIT_DONATIONS`): the search (`op=add1`), adding points (`op=add2`)
  and its transaction credit path, and "Return whence you came".

Both pages were byte-identical to 1.1.2 before this change. Route-local helpers live in
`lib/admin_authority.php`. Not opened: the user editor, `paylog.php`'s own actions,
`payment.php` (still disabled, 410), and the bio editor in `prefs.php`, which was only
confirmed to honor a blocked bio (below).

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4 against `2a48f35`, as a superuser
with `SU_EDIT_COMMENTS | SU_EDIT_DONATIONS`. The target account had a bio and 100 points.

| Finding | Observed |
|---|---|
| GET block | `bios.php?op=block&userid=N` blocked the bio and mailed the player at once |
| Block replay | The same GET mailed again (two notices for one block) |
| Unblock | Fatal: `biotime='0000-00-00 00:00:00'` is refused under strict SQL (below). The notice had already been committed, so the player was told the bio was unblocked while it stayed blocked |
| Unknown account (bio) | The UPDATE ran against nothing and `systemmail` found no recipient; the page reported nothing |
| GET credit | `donators.php?op=add2&id=N&amt=50&reason=x` added 50 points, a debug log row and a notice |
| Credit replay | The same GET added 50 more points, another log row and another notice |
| Negative amount | `amt=-500` with a balance of 200 was fatal (unsigned column out of range) |
| Empty amount | Fatal |
| Fractional amount | `amt=1.5` added 2 points (MySQL rounded) and mailed |
| Unknown account (credit) | Fatal at the missing name, before any write |
| Transaction credit | Added the points, marked the payment log row processed and redirected to the payment log. No notice |
| Transaction replay | The same link credited the points again; the row was already processed |
| Unknown transaction | The link credited the points anyway; there was no payment log row to mark |
| SQL | `userid`, `id`, `amt` and `txnid` were pasted into SQL. The search escaped only apostrophes and split names byte by byte |

Zero date, run directly under the application's SQL modes (strict, `NO_ZERO_DATE`,
`NO_ZERO_IN_DATE`) on a `datetime NULL` column like the fresh schema's `biotime`:

| Statement | MySQL 8.4.11 | MariaDB 11.4.13 |
|---|---|---|
| `SET biotime='0000-00-00 00:00:00'` | Error 1292, incorrect datetime value | Error 1292, incorrect datetime value |
| `SET biotime=NULL` | OK | OK |
| `SET donation=donation+'-500'` (balance 100) | Error 1264, out of range | Error 1264, out of range |
| `SET donation=donation+'99999999999'` | Error 1264 | Error 1264 |
| `SET donation=donation+'1.5'` (balance 100) | OK, 102 | OK, 102 |
| `SET donation=donation+''` | OK, unchanged | Error 1292, truncated DOUBLE |

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Block, unblock | GET links `op=block|unblock&userid=N` | `[Block]` / `[Unblock]` POST forms; GET of an action URL is 405 and changes nothing | GET mutation |
| Bio protection | None | CSRF plus a one-use intent per account (`bio-moderation-N`) bound to the action, the bio and the `biotime` it was shown with | Replay, stale forms |
| Bio order | Notice first, then an unchecked UPDATE, outside any transaction | The account and the moderator are locked; the bio is rechecked under lock; the notice and the UPDATE commit together; the email runs after commit | Atomic |
| Unknown account (bio) | UPDATE of nothing, no notice | 404 before any write | Unknown target |
| Repeat block or unblock | Rewrote and mailed again | Block applies only to an unblocked bio and unblock only to a blocked one (the lists already offered only those); a repeat is refused (409), no write and no notice | The repeat was only reachable by replay |
| Unblock `biotime` | `'0000-00-00 00:00:00'`, fatal under strict SQL | `NULL`, the fresh schema's "never set" value | Strict SQL; see below |
| Block marker, sentinel, notices | `` `iBlocked for inappropriate usage`i ``, `9999-12-31 23:59:59`, two notice texts | Unchanged | Historical |
| Add points | GET link `op=add2&id=N&amt=…&reason=…&txnid=…` | POST form for each search match, carrying the points, reason and transaction; GET of `op=add2` is 405 | GET mutation |
| Credit protection | None | CSRF plus a one-use intent per account (`donation-add-N`) bound to the account's balance, the points, the reason, the transaction and that transaction's payment log row | Replay, stale forms, tampering |
| Amount | Any text, pasted into `donation+'…'` | A whole number other than 0, from -9,999,999 to 9,999,999, leaving a balance from 0 to 4,294,967,295; otherwise 400 before any write, and the search shows no form | Columns; see below |
| Unknown account (credit) | Fatal | 404 before any write | Unknown target |
| Self-credit | Session got the typed amount, the database the adjusted points; the session save won | Both get the points, written in the locked player transaction | Player mutation model |
| Transaction credit | Any `txnid`, pasted into SQL; credited even if unknown or processed | Exactly one payment log row with that ID, unprocessed, rechecked under lock; unknown is 404, processed is 409; the row is marked once | Double credit |
| Credit order | UPDATE, hooks, payment log, debug log and notice as separate statements | All of them, with the hooks' own writes, in the locked transaction; the email runs after commit | Atomic |
| Hook names and arguments | `donation_adjustments` (`points`, `amount`=points/100, `acctid`, `messages`), `donation` (`id`, `amt`, `manual`) | Unchanged; the values are now integers instead of request strings | Historical |
| Adjusted points | Stored by MySQL's rounding | Rounded as `payment.php` did (`round()`), giving the same stored value | Same result, explicit |
| Debug log texts, values | Manual: the amount; transaction: the adjusted points | Unchanged | Historical |
| Notices | Manual entries only (the transaction path redirected first); "Donation Point Added" for exactly 1 | Unchanged | Historical |
| Search | `%` between bytes, apostrophe escaping only; `%` and `_` were wildcards | The bank's recipient pattern (`resurrection_bank_search_pattern`, `ESCAPE '|'`): UTF-8 characters, `%` and `_` match only themselves; bound; invalid UTF-8 or an array is 400 | Reuse |
| Confirm links | `amt` and `txnid` unescaped in the URL | Hidden POST fields, attribute-escaped; names escaped in the button | Escaping |
| Transaction on a return page | A `ret` dropped the transaction from the links | Unchanged | Historical |
| Return whence you came | `ret` from the URL after `cmd_sanitize()` and the last `/`; anything, including `javascript:` and quotes that reached the hotkey script | Only the two pages that link here, in their display forms (`user.php?op=edit…`, `viewpetition.php?op=view…`); anything else is the historical empty return (a plain label) | Off-site and script links |

New texts, shown only where 1.1.2 showed broken links: "Points must be a whole number
other than 0, from -9,999,999 to 9,999,999.", "That transaction is unknown or has
already been processed." and "(not enough points to remove)".

## Decisions

**Negative amounts are kept.** 1.1.2 offered them: `payment.php` credited reversals with
the negative `mc_gross`, and `paylog.php` builds the manual credit link as
`amt=round(mc_gross*100)`, which is negative for an unmatched reversal. A negative amount
therefore removes points. It may not leave a negative balance: 1.1.2's permissive MySQL
would have clamped it to 0 while the log and the notice claimed the full removal, and
strict SQL makes it fatal. Such a request is refused before any write.

**Zero is refused.** It changed nothing but still logged, ran the hooks and mailed
"You have received 0 donation points".

**The amount limit is the debug log's.** `debuglog.value` is `float(9,2)`, so a logged
amount beyond 9,999,999.99 cannot be stored under strict SQL. The account column
(`int unsigned`) bounds the balance. Fractions, signs such as `+5` and leading zeros
are refused instead of rounded.

**Unblock writes `NULL`.** The fresh schema already turns every zero-date default into
`NULL` ("not yet"); `biotime` is nullable with no default. `prefs.php` compares
`biotime > "9000-01-01"`, which is false for `NULL`, so the bio can be written again and
gets a real date. The bio list needs `bio > ''`, which an unblocked bio (`''`) never has
until it is rewritten. No historical database is migrated, so no zero dates exist.

**A transaction credit still sends no notice.** In 1.1.2 the redirect to the payment log
came before the notice, so only manual entries mailed.

## Tests

`test_bio_moderation_authority_http`:

- Anonymous requests and every role without `SU_EDIT_COMMENTS` are refused (403).
- The list escapes names and bios and has no action links. GET of either action is 405
  with no change and no notice. Bad `op` or `userid` values are 400.
- Missing or wrong CSRF is 403, an extra field 400, an unknown account 404.
- Block writes the marker and the sentinel with exactly one notice. Its replay is 409, no
  second notice. A blocked bio is offered only Unblock.
- An unblock form issued before another change is 409, as is an unblock form posted to
  block.
- Unblock writes `''` and `NULL` with one notice; its replay is 409. This runs on both CI
  databases.
- The moderator's own bio: blocked, `prefs.php` answers "You cannot modify your bio" and
  keeps it; after unblock, `prefs.php` writes the new bio with a real date.

`test_donation_admin_authority_http`:

- Anonymous requests and every role without `SU_EDIT_DONATIONS` are refused (403).
- The 1.1.2 GET credit link is 405 with no change. Bad parameters are 400.
- The search binds an apostrophe and matches `雪`. `%_` matches only the literal
  characters, and `_x_` matches nothing. Names and the reason are escaped. An array or
  invalid UTF-8 is 400.
- Return: the user editor and petition viewer links work. `javascript:`, quotes and
  action URLs are dropped.
- These amounts offer no form and are 400 when forged: `0`, `-0`, `1.5`, `abc`, empty,
  `+5`, `007`, ±10,000,000 and `99999999999`. An extra field is 400, an unknown account
  404, and bad CSRF 403.
- Credit 50: the balance, one log row with the historical text and value, and one notice
  commit together; the replay is 409. A tampered amount is 409. One point uses the
  singular notice.
- -51 removes points. A removal past the balance offers no form and is 400 when forged.
  A form issued at another balance is 409. The balance limit is 4,294,967,295.
- Rollback: a CHECK constraint that fails the notice insert returns 500 with points, log
  and notice unchanged. The spent intent is 409, and a fresh form succeeds.
- Self-credit: the database holds the credit after a later page.
- Transaction credit, with a fixture `donation_adjustments` hook that doubles the points
  and adds a message:
  - An unknown transaction offers no form and is 404 when forged.
  - A form for another transaction is 409.
  - The credit stores 80 for an amount of 40, in the database and the session alike. It
    writes the hook's message and the "Credited manually" log, marks the payment log row
    with the account, sends no notice and redirects to the payment log.
  - Replay is 409. The settled transaction offers no form.

Against `2a48f35` both tests fail at their first GET check. The bio list carries GET
action links (`href='bios.php?op=…`), and the GET credit link returns 200 and credits.
Both pass with the change on MySQL 8.4 and MariaDB 11.4 locally. Local runs are
supporting evidence only; acceptance comes from CI on both supported legs.

## Remaining gaps

- `prefs.php` is fatal on GET (and after a POST) for an account whose stored preferences
  lack a boolean the form shows. New accounts start with no preferences. This is outside
  this scope and is not fixed; the test gives its fixture player every boolean it shows.
- `bio.php`'s own `ret` return link is not opened.
- `paylog.php` still runs its unbound `processdate` UPDATE on GET. That page's actions
  are outside this scope.

## Candidate CI, 2026-10-16

`c166e2eb91f421b706e051ffd45d8c86c7e61014` was inspected once at the start of the
restoration task. Modern core 38892084451 was still running: both `checks` jobs were
SUCCESS and all eight `http` shards were in progress. The task brief reports Baseline
integrity 38892084290 SUCCESS; it was not re-inspected here. Bio moderation and
donation administration stay in Remaining until Modern core is accepted.

## Acceptance, 2026-10-16

Bio moderation and donation administration are accepted at `c166e2e`. Both workflows
passed and were inspected once at the start of the user editor task:

| Workflow | Run | Result |
|---|---|---|
| Modern core | 38892084451 (11:11–11:28 UTC) | All 12 jobs SUCCESS. `engineering (8.4, mariadb:11.4) / result` SUCCESS at 11:26:41Z; `engineering (8.5, mysql:8.4) / result` SUCCESS at 11:28:27Z |
| Baseline integrity | 38892084290 | SUCCESS |
