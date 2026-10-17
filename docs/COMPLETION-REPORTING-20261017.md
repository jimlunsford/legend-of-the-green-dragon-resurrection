# Truthful completion reporting: OPTIMIZE TABLE and external email, 2026-10-17

Phase 3 roadmap item "Truthful completion reporting": `lib/newday/dbcleanup.php`, the petition
notice email in `lib/petition/petition_default.php`, and an inventory of every external email
send. Starting head `4182281afcb9cf25d1b07c13154fa69014555cb6`.

Not opened: the petition admin pages (accepted), New Day logic beyond `dbcleanup.php`, and the
expiration code (accepted in the previous task).

## 1.1.2 comparison

| Area | 1.1.2 | Before (`4182281`) | Now |
|---|---|---|---|
| Schedule marker | `lastdboptimize` saved before any table is touched | Same | The run's start time is saved only when no table failed |
| Result sets | Ignored | Ignored | Each table's rows are read and classified: done, done with a note, or failed |
| Log line | `Optimized tables: <every table> in N seconds.` whatever happened | Same | Counts, the tables grouped by the server's note, each failed table with the server's messages, and a line saying the run was not recorded |
| Table names | Pasted into SQL | `db_identifier()`; a name it refuses threw and failed the whole maintenance run | That table is reported failed; the rest continue |
| Concurrency | Ran inside `newday.php`'s runonce, under the `newdaySemaphore` race | Reached only through `cron.php`, which holds `GET_LOCK`; `dbcleanup.php` itself unguarded | Refuses to run without the maintenance lock, as expiration does |
| Schedule, interval, table list | `lastdboptimize`, once a day, `SHOW TABLES` | Same | Same |
| Petition notice transport | `mail()`, result ignored, no From header | Same | `resurrection_send_email()` after the petition row is written, From the admin address |
| Refused petition notice | Player told "Your petition has been sent to the server admin." | The error handler turns the refused `mail()` warning into an exception: a fatal page after the row was saved (revert R6b) | The same player text, the row kept, and a game log line for the admin |
| `serverurl` when unset | Built from `SERVER_NAME`, `SERVER_PORT` and `REQUEST_URI` and saved, by `getsetting()`'s default and by the trailing-slash fix | Same | Never saved from a request. The petition notice says "(Server URL not set or not valid)" |
| `serverurl` when set | A trailing slash added and saved | Same | Same, when the value is a valid base URL; an invalid value is not used and not rewritten |
| Referral page | Same request-built save as the petition | Same | Shows the request-built URL to that player, validated, and never saves it |

## A. OPTIMIZE TABLE

### Rows each server returns

Captured with the application's PDO settings (`ATTR_EMULATE_PREPARES` off, `FETCH_ASSOC`,
`STRINGIFY_FETCHES`) on MySQL 8.4.11 and MariaDB 11.4.13. Every row also carries
`Table` (`resurrection_test.<name>`) and `Op` (`optimize`).

| Table | MySQL 8.4.11 | MariaDB 11.4.13 |
|---|---|---|
| InnoDB | `note` "Table does not support optimize, doing recreate + analyze instead"; `status` "OK" | Same |
| MyISAM, with a deleted row | `status` "OK" | Same |
| MyISAM, unchanged | `status` "Table is already up to date" | Same |
| MEMORY, CSV | `note` "The storage engine for the table doesn't support optimize" (no status row) | Same |
| View | `Error` "'resurrection_test.v' is not BASE TABLE"; `status` "Operation failed" | `Error` "'resurrection_test.v' is not of type 'BASE TABLE'"; `status` "Operation failed" |
| Table dropped meanwhile | `Error` "Table 'resurrection_test.m' doesn't exist"; `status` "Operation failed" | Same |

None of these raised an exception. An error is only visible in the rows.

### Classification (`resurrection_optimize_classify()`, `lib/db_optimize.php`)

- **Failed**: any `error` row; a `status` other than "OK" or "Table is already up to date";
  no rows; no status and no note; an unrecognized `Msg_type`; a statement that raised a
  database error; a table name `db_identifier()` refuses.
- **Done with a note**: `note`, `info` or `warning` rows and no failure. InnoDB is always here.
- **Done**: a successful status and nothing else.

`Msg_type` is compared case-insensitively (`Error` on both servers here; MySQL documents
lower case).

### The log line

From a local MariaDB 11.4 run of the new integration test (the shipped tables plus fixtures):

```
Optimized tables: 39 of 41 completed in 0.27 seconds.
Completed with the note "Table does not support optimize, doing recreate + analyze instead": aaa_fixture_optimize_hold, accounts, accounts_output, armor, bans, ...
Failed: 2
fixture-optimize-name (The table name is not supported.)
fixture_optimize_view ('resurrection_test.fixture_optimize_view' is not of type 'BASE TABLE'; Operation failed)
The optimization was not recorded as run, so the next maintenance run tries again.
```

### Schedule decision

`lastdboptimize` advances only when no table failed. No exception was needed:

- A fresh install has 38 tables, all InnoDB base tables, and no views. On both servers every
  one returns the InnoDB note and status OK, so the shipped schema has no table that always
  fails.
- The only always-failing cases found are objects an operator adds to the game database: a
  view, or a table whose name has characters outside `[A-Za-z0-9_]`. With one present, the
  optimization stays due and runs again at each maintenance run (once per game day), and
  each run's log names the object. That is the truthful outcome; the operator removes it or
  moves it out of the game database.
- A transient failure (a table dropped meanwhile, a lock or disk error) is retried at the
  next maintenance run instead of waiting another day, as 1.1.2's early save made it do.

`lastdboptimize` records the start time, as 1.1.2 did. The interval check in
`lib/newday/newday_runonce.php` and the installer default are unchanged.

### Lock

`resurrection_optimize_tables()` refuses to run unless this connection holds the maintenance
lock (`resurrection_maintenance_lock_held()`, the check expiration uses). Its only caller is
`newday_runonce.php`, which only `cron.php` reaches, under `GET_LOCK`. A second scheduler
started while the first is inside `OPTIMIZE TABLE` exits 1 with "Maintenance already running."
`OPTIMIZE TABLE` commits implicitly; no transaction is claimed around it.

## B. Petition notice email

- **Transport.** `resurrection_send_email()` (`lib/systemmail.php`), called after the petition
  `INSERT` has run in autocommit, so the row is committed first. The helper refuses an
  invalid address and CR or LF in the subject or sender, and catches the refused-delivery
  warning. The admin address is both To and From, as the expiration warning does; 1.1.2 sent
  no From header.
- **Player text.** Kept: "Your petition has been sent to the server admin." The petition row
  is what the admin works from (`viewpetition.php`), and 1.1.2 showed the same text whatever
  the email did, so the text describes the petition, not the notice. It is shown whether or
  not the notice was accepted.
- **Admin.** A refused notice writes a game log line, category `petition`: "Petition N was
  filed, but its email notice to the admin address could not be sent." The petition itself
  is in the petition list either way.
- **Before.** With a refused send, the error handler threw on `mail()`'s warning after the
  row was saved, so the player got a fatal page for a petition that had in fact been filed
  (revert R6b).

### serverurl decision

1.1.2 (`lib/petition/petition_default.php` and `referral.php` at the historical tag) builds a
URL from `SERVER_NAME`, `SERVER_PORT` and `REQUEST_URI` when `serverurl` is unset. Two writes
save it: `getsetting()` saves its default for any unset setting, and the trailing-slash branch
calls `savesetting()`. With Apache's default `UseCanonicalName Off`, `SERVER_NAME` is the
client's `Host` header, and `REQUEST_URI` is always the client's. So any visitor filing a
petition could set the global URL the game then puts in its emails and referral links,
including a path such as `/petition.php?op=submit&x=/a`.

Decision: the self-configuration does not stay. No validation can tell a forged `Host` from
the real one, so a validated value would still be the attacker's.

- `serverurl` is written by installation (`installer_default_settings.php`, an operator
  action) and the game settings editor only. A fresh install stores `http://localhost/` from
  the CLI installer.
- `resurrection_server_url()` (`lib/server_url.php`) reads the stored value without
  `getsetting()`'s default save, and returns it only if it is a valid base URL: `http` or
  `https`, a host name or IP address, an optional port 1 to 65535, a plain path, and no user
  info, query, fragment, quotes, whitespace or control characters. A valid value without the
  trailing slash is saved with one, as 1.1.2 did. An invalid value is not used and not
  rewritten.
- The petition notice uses "(Server URL not set or not valid)" in place of the URL when there
  is none.
- `referral.php` shows the requesting player 1.1.2's request-built URL when `serverurl` is
  unset, after the same validation, and never saves it. Only that player sees it.

### Header and body safety

- Subject: `New LoGD Petition at %s` with the validated URL or the fixed placeholder. A
  validated URL has no control characters, and the helper refuses CR and LF in the subject.
- To and From: the configured admin address; the helper rejects an invalid address and CR or
  LF in the sender.
- Body: the server line, the author's sanitized name, the date and `output_array()` of the
  posted fields. It is passed as the body argument of `mail()`, which PHP writes after the
  headers, so its content cannot add headers. The default `sendmail -t -i` does not end the
  message at a line with a single dot.

## C. External email inventory

Searched for `mail(`, `mb_send_mail`, `imap_mail`, SMTP, sendmail, PHPMailer, `popen` and
`proc_open` in application code and every bundled module. `lib/pullurl.php`'s `fsockopen` is
HTTP for LoGDnet, not mail.

| Send | File | Trigger | Transport | Success checked and reported |
|---|---|---|---|---|
| Game mail notification | `lib/systemmail.php` `resurrection_systemmail_deliver()` | Game mail to a player with "email on new mail" on: player mail, PvP, training, clan, bank, haunt, admin, expiration hooks | `resurrection_send_email()`, after the game mail commits | Yes: returned, logged with `error_log()` when refused. Nothing tells the sender an email was delivered; the game mail is the record |
| Expiration warning | `lib/char_expiration.php` | Maintenance, accounts within 5 days of expiry | `resurrection_send_email()` | Yes: `sentnotice` only when accepted; the game log counts sent and unsent (previous task) |
| Petition notice | `lib/petition/petition_default.php` | A petition filed with `emailpetitions` on | `resurrection_send_email()` | Yes: a refused notice is logged; the player text describes the filed petition (this task) |
| Forgotten password link | `create.php?op=forgot` (1.1.2 line 80) | Disabled: legacy recovery returns 410 | None | Not applicable. Stays disabled |
| Account verification | `create.php` (1.1.2 line 216) | Disabled: `requirevalidemail` makes account creation unavailable | None | Not applicable |
| Error notification | `lib/errorhandler.php` (1.1.2 line 117) | Removed: the modern error boundary sends no email | None | Not applicable |
| Payment problems (2) | `payment.php` (1.1.2 lines 186, 201) | Disabled: historical payments return 410 | None | Not applicable |

Every live external send goes through `resurrection_send_email()`.

## Tests

PHPUnit `tests/unit/CompletionReportingTest.php` (10 tests):

- every captured result set above, classified;
- unexpected sets: an error row before an OK status, no rows, a row with no type, a failed
  status, an unknown type, and a warning with OK;
- the log line, with and without a failure;
- `serverurl` validation: six valid URLs and twenty invalid ones, including CR/LF, a query
  path, user info, quotes, a bad port and an over-long value.

`test_table_optimization_reporting_cli` (shard 4):

- a run with a view and an unsupported table name: exit 0, one log line with the counts,
  the InnoDB note, both failures and their messages, and `lastdboptimize` unchanged;
- the retry without them: every table completed and `lastdboptimize` recorded;
- no second run within the day;
- a direct run without the lock: refused, nothing logged;
- a second scheduler while the first is held inside `OPTIMIZE TABLE` by an open transaction:
  refused, and one log line.

`test_petition_notice_email_and_server_url_http` (shard 3), on a second fixture server whose
transport accepts mail except to `fail-admin@example.invalid` and that takes `SERVER_NAME` from
`Host` as Apache does:

- accepted: the player text, the row, one notice To and From the admin address naming the
  configured URL, and the configured URL saved with its trailing slash;
- refused: the same player text, the row, and the game log line naming the petition;
- `serverurl` unset with a forged `Host`: nothing saved, and the notice names no host;
- the referral page with a forged `Host`: the link is shown and nothing is saved.

The shared web client gained an optional `host` argument; the default is unchanged.

### Each test failing before the fix

Each revert takes one guard out of the final tree. Run on PHP 8.5 / MySQL 8.4:

| Revert | Failing test and assertion |
|---|---|
| R0: the three changed files as at `4182281` | Both web tests. Maintenance exits 1 at `dbwrapper_pdo.php:47` (`db_identifier()` on the unsupported name); the notice has no From header |
| R1: `lastdboptimize` saved before any table | CLI: `'2000-01-01 00:00:00' != '2026-10-17 12:10:00'` |
| R2: every table reported done | CLI: the line reads "40 of 41 completed" |
| R3: no lock check | CLI: `'refused' != 'ran'` |
| R4: an unsupported name aborts the run | CLI: maintenance exits 1 |
| R5: error rows ignored when a status follows | CLI: the view's message is missing; PHPUnit 4 failures |
| R6: the notice through raw `mail()` | HTTP: no From header |
| R6b: raw `mail()` with a From header | HTTP: the refused notice is a fatal error page |
| R7: a refused notice not logged | HTTP: no game log line |
| R8: the petition's 1.1.2 `serverurl` code | HTTP: `serverurl` saved as `http://evil.example:<port>/` |
| R9: the referral's 1.1.2 `serverurl` code | HTTP: `serverurl` saved as `http://evil.example:<port>/` |
| R10: any configured URL accepted | PHPUnit: the validation test fails |
| R11: a configured URL not normalized | HTTP: `serverurl` stays without its trailing slash |

Restoring each guard made the test pass again.

## Local verification

All local, PHP 8.5.11. Supporting evidence only; CI is the acceptance.

- The two new web tests, after account setup, on fresh databases: MySQL 8.4.11 and MariaDB
  11.4.13, both OK.
- Shards 3 and 4 (the shards holding the new tests), side by side on fresh MySQL 8.4
  databases, 0 failures, errors or skips: shard 3 ran 40 tests in 1011 s, shard 4 ran 41 in
  1038 s. The petition test took 2.5 s and the optimization test 5.1 s.
- PHPUnit 173/173.
- PHP lint: 376 files, 0 failures.
- Both PHPStan gates: no errors. `lib/db_optimize.php` and `lib/server_url.php` added to the
  level-6 gate.
- `composer validate --strict`: valid. `composer audit`: no advisories.
- Historical verifier: PASS. Project validator: PASS. Shard tooling test: OK.
- `git diff --check`: clean.
- Serialized-state source unchanged (no `serialize()`, `ScalarState::read()` or
  `unserialize()` call added or removed); the inventory stays 107/50/40/66/1.

## Remaining gaps

- **Mail notification link.** The game mail notification body says "You have received new mail
  on LoGD at http://%s" with the sender's `HTTP_HOST`, as 1.1.2 did. A player can forge it in
  another player's email. It is the body, not a header, and no setting is written, but the
  link is the sender's. It is in the shared `systemmail` helper and was not changed here; the
  fix is to use `resurrection_server_url()`.
- **Expiration warning URL.** `lib/char_expiration.php` still reads `serverurl` through
  `getsetting()` with a default built from `cron.php`'s CLI values, so when warnings are due and
  `serverurl` is unset, it is saved as `http://localhost.`. No request header is involved, and the value is then refused
  by the new validation. The accepted expiration code was not reopened.
- **Settings editor.** The game settings editor saves `serverurl` as typed. Validation happens
  where it is used.
- **Operator objects.** A view or an odd-named table in the game database keeps the
  optimization due at every maintenance run, by design (above).
- **Log size.** The log line still lists every table, as 1.1.2 did.
