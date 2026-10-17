# Account deletion and expiration cleanup authority, 2026-10-17

Phase 3 candidate. Starting head `43bcdc4e4536188ba8a324519d5298faeedddb36`.

Scope: `char_cleanup()` (`lib/charcleanup.php`) and its three callers, the user editor's
delete (`user.php?op=del`, `lib/user/user_del.php`), self-deletion (`prefs.php?op=suicide`)
and expiration (`lib/expire_chars.php`, run from `lib/newday/newday_runonce.php` by
`cron.php`), plus the expiration warning email. The module hook system, the clan pages and
`mail.php` were not opened. The rest of "truthful completion reporting" (`OPTIMIZE TABLE`,
the petition notice email) stays on the roadmap.

## What changed

- `lib/charcleanup.php`: `char_cleanup()` now runs only inside the caller's deletion
  transaction and binds every statement. `resurrection_char_delete()` locks the account,
  runs the cleanup and deletes the account row in that same transaction; a veto throws
  `ResurrectionCharacterDeletionVetoed` so the caller rolls everything back.
  `resurrection_char_cleanup_succession()` is 1.1.2's succession rule as a pure function.
- `lib/user/user_del.php`, `lib/user/user_.php`: the list's Del link is a POST form with
  CSRF and a one-use intent bound to the account (ID and login). The cleanup, the account
  row, the news line and the debug log commit together inside the accepted admin
  mutation (actor and target locked in ID order, systemmail queue owned).
- `prefs.php`: self-deletion takes a one-use intent; the cleanup, the account row and the
  news line commit together in the same locked mutation.
- `lib/char_expiration.php` (new), `lib/expire_chars.php`: expiration refuses to run
  without the maintenance lock, selects with bound SQL, deletes each account in its own
  transaction, reports every outcome, and records `last_char_expire` only when no account
  failed. The warning email marks `sentnotice` only when the transport accepted it.
- `lib/maintenance.php` (new), `cron.php`: the maintenance lock name moved into a helper
  that expiration also uses to check it holds the lock.
- `lib/systemmail.php`: `resurrection_send_email()` is the game's one external email
  transport. Mail notifications and the expiration warning both use it.

## 1.1.2 comparison

| Area | 1.1.2 (`historical-source-1.1.2`) | Before (`43bcdc4`) | Now |
|---|---|---|---|
| Transaction | None. The hook, the cleanup deletes and clan succession run as separate statements; each caller deletes the account row afterwards | Same | One transaction per account: hook writes, every cleanup delete, clan succession, the account row, and the caller's news line and debug log. Any failure rolls all of it back |
| Hook veto (`dodel=false`) | `char_cleanup()` returns false before deleting anything. Expiration skips the account; the user editor and self-deletion ignore the result and delete the account row, leaving its output, commentary, mail, news, module prefs and clan rank behind | Same | All three callers: nothing changes, and the hook's own writes (and any earlier hook's, such as dag's bounty closing) are rolled back. See "Veto decision" |
| SQL | IDs interpolated. Self-deletion took `userid` from the URL, unquoted | User editor ID typed; cleanup statements interpolated; self-deletion uses the session's account | Every statement bound. Self-deletion uses the session's account |
| Table prefixes | `mail`, `news`, `clans` and the clan `accounts` update ignore `db_prefix()` | Same | Prefixed |
| Clan disband | Deletes the clan, then `clanjoindate='0000-00-00 00:00;00'` for its members | Same; strict SQL refuses the value | Deletes the clan, then `clanid=0, clanrank=0, clanjoindate=NULL`. See "Zero date" |
| Succession | A deleted account with rank exactly `CLAN_LEADER` passes the clan to the first remaining member above applicant, `ORDER BY clanrank DESC, clanjoindate`; that member is set to leader unless already a leader; nobody left disbands | Same, unlocked | Same rule, account ID as the final tie-break (as the accepted clan code). Members locked `ORDER BY acctid FOR UPDATE`, then the clan row, as `clan_withdraw.php` does |
| User editor delete | GET link with a JavaScript confirm | Link still GET, so a 403 (`user.php` requires POST); no intent; no test | POST form, CSRF, one-use intent bound to the account's ID and login. A stale form is 409; an account already gone (or a repeated form) shows 1.1.2's "0 user deleted." and changes nothing; a failure is 500 with nothing changed |
| Deleting yourself, or a megauser | Allowed to any `SU_EDIT_USERS` editor; your own deletion shows the page and the next request ends the session | Same | Same. The page save is skipped after deleting your own account, so no `accounts_output` row is written for the deleted account |
| Self-deletion | Any account by `userid`; `selfdelete` gate; news line, logout, two cache invalidations | POST + CSRF, own account; the cache invalidations sat after `exit()` and never ran | One-use intent; `selfdelete` gate, news line and logout kept; the cache invalidations run again. A veto is 409 "Your character was not deleted."; a failure is 500 with nothing changed |
| Expiration guard | `last_char_expire` saved before any work; reachable from any New Day | Reachable only from `cron.php` under its database lock; timestamp still saved first | Refuses without the maintenance lock; timestamp saved only after a run with no failed account |
| Selection | Three rules, interpolated dates; `regdate < date_add(NOW(), ...) AND laston < regdate` | Same | Same three rules, bound. Trash rule `laston <= regdate`, cutoff from the PHP clock. See "Trash rule" |
| Per-account deletion | `char_cleanup()` per account, gamelog written, then one `DELETE ... IN (...)` for all | Same | Each account deleted in its own transaction after rechecking the rules under its lock; vetoed, failed and no-longer-eligible accounts are skipped, counted and named; the rest continue |
| Game log | "Deleted N accounts:" with the stats, written before the account rows were deleted | Same | Same text and stats, written after, for the accounts actually deleted; a line each for kept, failed or no-longer-eligible accounts when there are any |
| Warning email | `mail()` result ignored; `sentnotice=1` always | A refused delivery's warning became an exception and failed the maintenance run, after the timestamp was saved | Shared transport; `sentnotice=1` only when accepted; the game log reports sent and unsent; unsent ones are retried at the next run |

Unchanged: the `delete_character` hook arguments, `CHAR_DELETE_*` types, the three rule
settings and their defaults (45, 10, 1), the `NO_ACCOUNT_EXPIRATION` exemption, the 23-hour
interval, the news lines ("`#%s was unmade by the gods." hidden from the bio, "`#%s quietly
passed from this world."), the debug log text (`"deleted user" . name . "'0"`, 1.1.2's
typo kept), the "N user deleted." line, and the email subject, body, sender setting and
default.

## Veto decision

1.1.2 was inconsistent. Expiration honored a veto; the user editor and self-deletion
ignored it and deleted the account row after `char_cleanup()` had returned early, so a
veto there produced exactly the half state the brief rules out: an account row gone, with
its output, commentary, mail, news, module preferences and clan leadership left behind.

Decision: a veto means the account is not deleted, by every caller, and the deletion's
transaction is rolled back, including whatever any `delete_character` hook wrote first.
Without the rollback, dag would close a living character's bounties. Expiration counts
the account as "Kept by a module" and continues. The user editor reports "0 user
deleted." (the historical line, now true). Self-deletion answers 409 "Your character was
not deleted." 1.1.2 printed "Your character has been deleted!" either way.

## The dag hook

dag is the only bundled module that hooks `delete_character`
(`modules/dag/install.php`, certified in `BUNDLED-MODULE-CERTIFICATION.json`). It writes
one bound statement: `UPDATE bounty SET status=1, winner=0, windate=<now> WHERE target=?
AND status=0`, closing open bounties on the account. It now runs inside the deletion
transaction, so it commits with the deletion and rolls back with a veto or failure. The
tests check both. A module hook with effects outside the database (a file, an email)
cannot be rolled back; no bundled module has one.

## Zero date

`char_cleanup()` disbanded a clan with `clanjoindate='0000-00-00 00:00;00'`. The value
has a typo (`;` for `:`) and is a zero date. Strict SQL (`STRICT_TRANS_TABLES`, the
default on MySQL 8.4 and MariaDB 11.4) refuses it, so before this change a leader's
deletion that disbanded a clan failed at that statement, after the output and commentary
were already deleted. The accepted clan code uses `clanid=0, clanrank=CLAN_APPLICANT
(0), clanjoindate=NULL` for "no clan" (`lib/clan/clan_withdraw.php`), and NULL is the
installed column default. Disband now writes that. Revert R4 below shows the test failing
with the 1.1.2 value.

## Trash rule

1.1.2's `create.php` wrote `laston` one day before `regdate`, so "never logged on" read
`laston < regdate`. Modern account creation (`lib/accounts.php`) writes `laston` equal to
`regdate`, and the first login moves `laston` forward. With `<`, the rule never matched an
account created by this code. `laston <= regdate` keeps 1.1.2's selection for both shapes.
The trash cutoff is now computed from the PHP clock like the other two rules, the same
clock that writes `regdate`. Revert R10 shows the test failing with `<`.

## Email change

- Sent through `resurrection_send_email()`, the transport the systemmail after-commit
  sender already used. It refuses an address that is not a valid email and a subject or
  sender with a line break, and it returns false when PHP's mail transport refuses the
  message. The application's error handler turns that refusal's warning into an exception;
  before, an expiration warning that failed to send failed the whole maintenance run,
  after `last_char_expire` had been saved, so neither the deletions nor the warning were
  retried for 23 hours (revert R13).
- `sentnotice=1` is written only after the transport accepted the message
  (`... WHERE acctid=? AND sentnotice=0`, bound). An unsent warning stays at 0 and is
  retried at the next expiration run.
- Subject "LoGD Character Expiration", the body text, the `serverurl` fallback and the
  `gameadminemail` sender with its default `postmaster@localhost.com` are unchanged.
- Query precedence: 1.1.2 subtracted 5 from `expireoldacct` and wrote
  `1=0 OR (laston < cutoff) AND emailaddress!='' AND sentnotice=0 AND (exempt)`. SQL reads
  that as `1=0 OR (laston < cutoff AND ...)`: the accounts past the cutoff while the result
  is above 0, and nobody when it is 0 or less (the `OR` clause is then absent and `1=0 AND
  ...` matches nothing). The bound query keeps that result; PHPUnit checks the cutoff for
  every setting from -2 to 60.
- The game log gains one line per run that had warnings to send: "Sent N expiration
  warning emails.", plus "M could not be sent and will be retried." when some failed.

## Locks and retry

- Deletion: the acting account and the target are locked in ascending ID order (the
  accepted player and admin mutation), then, for a clan leader, every clan member
  `ORDER BY acctid` and the clan row, then the hook runs. A retry after any failure starts
  from an unchanged account.
- Expiration: `cron.php` takes `GET_LOCK('resurrection-maintenance-<db hash>', 0)` and
  refuses a second run. Expiration checks `IS_USED_LOCK(name) = CONNECTION_ID()` and
  refuses without it; it is not reachable outside `cron.php`, and the check keeps it that
  way. The timestamp is no longer the concurrency guard.
- Each account's rules are rechecked under its row lock, so a player who logged on after
  the selection is kept ("No longer eligible").
- A failed account leaves `last_char_expire` unchanged, so the next maintenance run (the
  next game day) expires again and retries it. The rest of maintenance continues: one
  account that always fails does not block mail, ban and log cleanup.

## Tests

PHPUnit, `tests/unit/CharacterDeletionTest.php` (5 tests):

- The selection condition equals a transcription of 1.1.2's SQL for every combination of
  `expireoldacct` in {-3, 0, 1, 5, 45}, `expirenewacct` in {-1, 0, 10} and
  `expiretrashacct` in {-1, 0, 1, 7}, with only the two recorded differences; no value is
  inlined.
- The warning cutoff for every `expireoldacct` from -2 to 60.
- The succession rule: not a clan member, every non-leader rank, promote, keep, the
  founder quirk, disband.
- The email transport refuses invalid addresses and header line breaks.
- `char_cleanup()` refuses to run outside a transaction.

HTTP and CLI (`tests/tooling/test_web_application.py`), on a shared `_deletion_fixture`
(a counting, vetoing `delete_character` hook, dag active with an open bounty, mail, news,
commentary, output and module prefs on every account, and an optional foreign key that
makes the account row's DELETE fail last):

- `test_account_deletion_authority_http`: anonymous and both non-editor roles refused;
  GET, missing CSRF, missing intent, an extra field and a non-integer ID refused with
  nothing changed; the list has no GET delete link; a delete removes every trace, closes
  the bounty, writes the news line (hidden from the bio) and the debug log; a repeat and
  an unknown ID show "0 user deleted."; a stale form is 409; a veto changes nothing and
  rolls back the hook's and dag's writes; a failure at the account row changes nothing,
  then a fresh form completes; a leader's deletion promotes the highest-ranked member with
  the earliest join date (two officers, one member, one applicant); a lone leader's
  deletion disbands the clan and leaves the applicant with `clanjoindate` NULL; a megauser
  is deletable; an editor deleting their own account gets the page, no output row, and is
  logged out on the next request.
- `test_self_deletion_authority_http`: `selfdelete` off shows no form and a POST deletes
  nothing; GET, missing intent and an extra field refused; a veto (409) and a failure (500)
  leave everything intact; success deletes every trace, writes the news line and ends
  the session; a replay is not a logged-in request.
- `test_character_expiration_authority_cli` (runs `php cron.php` with a capturing mail
  transport): each rule deletes its account and spares its near miss; an exempt account
  is kept; a vetoed and a failing account are kept whole and named in the game log;
  `last_char_expire` is unchanged; the warning email reaches the eligible account
  (`sentnotice=1`), the refused one stays 0 and the exempt one gets none; the retry deletes
  the two, records the run and resends only the unsent warning; a run within 23 hours does
  nothing; expiration run directly (without the maintenance lock) refuses and changes
  nothing; a second scheduler started while the first is inside the deletion hook is
  refused and the account is deleted once.

## Reverts

Each new test was run against the change taken back out, on the final tree, one guard at a
time (local, PHP 8.5.11 / MySQL 8.4). Every revert below made at least one new test fail at
the line named; restoring the guard made it pass.

| Revert | Test | Fails at |
|---|---|---|
| R0: `lib/charcleanup.php`, `lib/user/user_del.php`, `prefs.php` and `lib/expire_chars.php` as at `43bcdc4` | all three | account deletion: a POST with CSRF and no intent deleted (expected 409, got 200). Self-deletion: the form has no intent. Expiration: the failing account's DELETE aborted the whole maintenance run (exit 1) |
| R1: user editor delete without the intent | account deletion | CSRF-only POST accepted (expected 409, got 200) |
| R2: veto ignored, account row deleted anyway (1.1.2's user editor and self-deletion) | account deletion, self-deletion | "0 user deleted." missing; self-deletion veto expected 409, got 303 |
| R3: cleanup committed apart from the account row | all three | after the forced failure, the account row is intact but its output, mail, news, commentary, prefs and bounty are gone |
| R4: 1.1.2's `clanjoindate='0000-00-00 00:00;00'` on disband | account deletion | the lone leader's deletion is a 500 (strict SQL refuses the value) |
| R5: succession without the join date order | account deletion | the officer with the later join date became leader |
| R6: page save after deleting your own account | account deletion | an `accounts_output` row is written for the deleted account |
| R7: self-deletion without the intent | self-deletion | CSRF-only POST deleted (expected 409, got 303) |
| R8: expiration without the maintenance lock check | expiration | run directly, expiration ran instead of refusing |
| R9: `last_char_expire` saved before cleanup (1.1.2's order) | expiration | timestamp advanced although an account failed |
| R10: 1.1.2's trash rule `laston < regdate` | expiration, PHPUnit | the never-logged-on account was not deleted; the selection test fails |
| R11: `sentnotice` set whatever the send result | expiration | "Sent 2" logged and the refused warning marked sent |
| R12: one failed account aborts the run | expiration | maintenance exit 1 at the failing account |
| R13: the transport's refusal not caught | expiration | maintenance exit 1 at the refused warning email |
| R14: an expiration veto commits instead of rolling back | expiration | dag's bounty closing kept on the kept account |
| Succession `!== CLAN_LEADER` changed to `< CLAN_LEADER` | PHPUnit | the founder case of the recorded 1.1.2 rule |

Not covered by a revert: the clan member lock order (no race test) and the recheck of the
rules under the account lock.

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- All four shards through `scripts/tooling-shards.py`, each on its own fresh database after
  PHPUnit, run side by side (New Day maintenance, clan membership and systemmail are
  shared paths): shard 1 42 tests in 908 s, shard 2 39 (38 + 1 prerequisite) in 881 s,
  shard 3 47 (46 + 1) in 926 s, shard 4 41 (40 + 1) in 987 s; no failures, errors or
  skips. The three new tests are assigned automatically: expiration to shard 2, account
  deletion and self-deletion to shard 3 at this tree.
- PHPUnit 163/163. `tests/tooling/test_tooling_shards.py` passes.
- Each revert above, on the final tree.
- PHP lint: 373 files, no failures. Both PHPStan configs: no errors
  (`lib/charcleanup.php`, `lib/char_expiration.php` and `lib/maintenance.php` added to the
  level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier (417 files, 11 commits) and project validator pass; `git diff
  --check` clean.
- Serialized-state source unchanged (no `serialize()`, `ScalarState::read()` or
  `unserialize()` call added or removed); inventory stays 107/50/40/66/1.

## Remaining gaps

- 1.1.2's succession trigger is `clanrank == CLAN_LEADER`, so a deleted founder (rank 31,
  assigned only by an editor) hands nothing on, and `!= CLAN_LEADER` sets a remaining
  founder to leader. Both carried unchanged; the PHPUnit test records them.
- 1.1.2's cleanup does not call the `clan-delete` hook when it disbands a clan (withdrawal
  does). Carried unchanged; the hook system was not opened.
- The clan member lock order matches the accepted clan code, but no test races a clan
  deletion against a clan action.
- A `delete_character` hook from a non-bundled module that writes outside the database is
  not rolled back.
- Expiration's recheck of the rules under the account lock (a player who logs on during
  the run) is not separately tested.
- `OPTIMIZE TABLE` reporting and the petition notice email stay in the roadmap's
  "truthful completion reporting" item.
