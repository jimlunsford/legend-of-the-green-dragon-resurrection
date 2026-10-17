# Player mail authority, 2026-10-04

## Boundary and accepted predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and PR #1 head: `de9f12ec05934b21c3c249bb250d10ac70643125`.
Continue only `modernization/core-modernization`. Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`; PR #1 remains OPEN, DRAFT,
NOT READY and NOT MERGED. No deployment, hosting, tags, release or VPS work.

The predecessor's exact Modern core **37210214161** and Baseline integrity
**37210214168** were independently verified terminal SUCCESS. Modern jobs
111459691443 (PHP 8.4 / MariaDB 11.4) and 111459691511 (PHP 8.5 / MySQL 8.4)
both passed 108 PHPUnit tests / 2,785 assertions, 121 Python tests, 346 lint
files, both PHPStan gates, Composer validation/install/audit, historical
verification and project validation. Python durations were 3155.026s and
3295.784s. **Explicit Ramius early resurrection and broader New Day: accepted
PASS.** Older pending statements are historical; this record supersedes them.
Neither family is reopened by this mail change.

## Historical contract and deliberate modernization

Inspected the preserved tag's `mail.php`, `lib/mail/case_address.php`,
`case_write.php`, `case_send.php`, `lib/systemmail.php`, and `lib/all_tables.php`,
as well as current consumers. The historical tag is immutable.

| Concern | Historical behavior | Secured behavior |
| --- | --- | --- |
| Address | Exact unlocked login, then subsequence display-name LIKE search; zero/one/multiple results | Parameterized queries, literal `%`, `_`, `!`, Unicode character subsequences, at most 100 candidates; narrow larger searches. No match offers address form, no send authority. |
| Direct write | Exact login, inconsistently omitted locked check | Same exact lookup with `locked=0`; unavailable/locked recipient cannot yield send authority. |
| Reply | Owned source, original sender, `RE: ` once, original name/time/body quote; system cannot receive replies | Owned stored row only, source fields bound and rechecked under lock. System, display identities, missing/deleted sender or source reject. Request cannot override recipient or original metadata. |
| Ordinary sender | Actor ID | Authenticated persisted actor ID only. No `from`, `msgfrom`, `msgto`, count, reply ID or return ID accepted in send transport. |
| Game Master sender | `msgfrom` is VARCHAR(255), intentionally allowing a roleplay/display identity. Nonnumeric text was stored and displayed, without a reply account. Current pre-candidate helper incorrectly cast it to integer zero. | Preserve the display feature through `GameMasterMailSender`, resolved against persisted actor privilege and locked state, stored in the authenticated server draft, reconstructed after lock. It is not account impersonation by numeric ID and not System. Blank means self; numeric/ambiguous/reserved System/control/HTML/oversize labels reject. The send POST contains no sender selector. |
| Locked recipients | Search excluded them; direct/reply/send inconsistent | All four player paths consistently exclude locked recipients. Trusted internal system notifications retain their separate contract. |
| Subject | VARCHAR(255); strip LoGD newline and LF; no adequate bound | Plain UTF-8, 255 Unicode code points; controls and LoGD newline removed before measuring. Empty allowed, over-limit rejected. Reply prefix preserves behavior and prefilled subject is safely limited to 255. No database truncation. |
| Body | Normalize LoGD newline, CRLF and CR to LF; truncate by `mailsizelimit` bytes | Preserve normalization and byte truncation with `mb_strcut`, never split UTF-8; preserve literal apostrophes/backslashes. Empty allowed; other C0/DEL controls reject. Limit must be 1..65535. |
| Mailbox | Count unread when `onlyunreadmails=1`, otherwise all; full at count >= limit; system messages bypass player cap | Same semantics. Limit 1..10000; boolean setting exactly 0/1. Locked recipient serializes competing senders; capacity uses a current locking read. |
| Return context | Client-controlled `returnto` | No client return field. Only the bound owned reply source becomes read navigation after success; historical mark-seen retained. |
| Email | Immediate best effort | After game-mail commit. Failure cannot roll back or authorize retry. No reliable-delivery/outbox claim. |

A Game Master display label is explicitly content authorized by a current database
role, not an asserted account or system identity. The factory cannot resolve it
for an ordinary/locked actor. The helper independently reloads/revalidates the
actor before accepting the descriptor. The originator remains the actual actor.
Display mail is always plain text and unreplyable, including serialized-looking
content. Game Master status is checked under lock even when revoked after common
request authentication and CSRF checks have already succeeded.

## Settlement and transport

`lib/player_mail.php` owns a separate mail-only transaction, with no gameplay
account-stat writes. The server draft holds actor identity/creation date/login
generation/privilege, exact candidate identities, recipient preferences/email,
reply source, normalized material settings and the optional authorized display
label. Browser tracking IDs, access timestamps and sender presentation preferences
do not authorize mail and are excluded from actor authority.

The only send POST keys are `to`, `subject`, `body`, `csrf_token`, `action_token`.
`to` must exactly match a login in the server-produced candidate set. GET send
fails. Strict raw URL-encoded comparison rejects duplicate/array/normalized keys,
unknown authority fields and mixed reply/address contexts. CSRF plus the existing
session-bound `ActionToken` contract is required. Same-state forms share one intent;
a successful or late-failed consumed intent cannot settle twice. Malformed
transport/CSRF is rejected before consumption and grants no mutation authority.

After consuming the intent, lock actor and recipient accounts in ascending ID
order; compare persisted authority, take shared locks on relevant settings, lock
and compare an owned reply source if present, count capacity with a locking read,
normalize/bound content, then INSERT and commit. Concurrent sends use independent
actors and sessions, not a PHP session mutex. A failed DML transaction rolls back;
its intent stays consumed and a fresh form can retry. Original reply rows are
unchanged on failed send. Ordinary mailbox read/unread/delete retain their existing
ownership and CSRF behavior; this is not a read-acknowledgment redesign.

## Shared helper and translated content

`systemmail` accepts canonical positive recipient IDs. Malformed, zero, negative,
overflow or ambiguous IDs reject. A well-formed nonexistent recipient returns
false without an orphan INSERT, warning or notification. Positive senders must
exist; integer zero is reserved to trusted internal system callers. Raw display
strings are rejected by the helper; a GM descriptor is required.

Standalone helper calls acquire account locks and commit their own INSERT.
Transaction-owned calls require the explicit `mail_notifications` queue. Player
mail, PvP and Training own and discard that queue on failure and deliver after
commit. The prior PvP queue has only been renamed/shared, without an event bus or
changes to reward semantics. Unsupported callers must explicitly own deferral
before invoking the helper from a transaction.

System translation envelopes must be zero-indexed, nonempty lists of at most
33 items: a string format plus at most 32 scalar string/integer/finite-float/boolean
arguments. No objects, nulls, nested arrays or associative business roots. Validate
UTF-8, encoded/expanded size, conversion vocabulary and argument count; retain
LoGD color-percent escaping and literal `%%`. ScalarState's class/depth/node/size/
canonical checks remain unchanged underneath. Serialized subject must still fit
VARCHAR(255); body must fit TEXT. Shipped caller shapes were inspected, including
PvP's 13-argument body and Training's color-percent subject.

Only exact stored sender `'0'` permits translated-array interpretation in the
active inbox/read UI. Invalid stored envelopes stay literal; player and GM text
never enters that parser. Reply no longer has a system-array parsing branch.
External notifications translate valid envelopes, preserve preference and
`noemail` rules, require a complete valid email address, strip LoGD formatting,
and do not leak internal envelopes or authentication state. Missing language
preferences now use the existing default without an undefined-key warning.
Tests substitute PHP's `sendmail_path` only on disposable fixture processes;
there is no production test header, callback or bypass.

## Current shipped caller inventory

Fourteen invocations in ten shipped files (excluding the helper definition).
Inventory is not blanket certification.

| Caller | Calls | Content and transaction ownership | Status in this slice |
| --- | ---: | --- | --- |
| `lib/player_mail.php` | 1 | Authenticated player/GM plain content, owned transaction/deferred email | Candidate boundary |
| `lib/pvpsupport.php` | 2 | Trusted system translated victory/defeat, PvP transaction/deferred email | Retained regression gate |
| `lib/training_outcomes.php` | 1 | Trusted system translated referral, Training transaction/deferred email | Retained regression gate |
| `lib/graveyard/case_haunt3.php` | 1 | Trusted system translated haunt, legacy caller | Caller-specific haunt authority remains open; no Ramius/New Day caller exists |
| `lib/clan/applicant_apply.php` | 2 | System translated applicant/reminder, player/clan fields | Clan authority/atomicity not certified |
| `lib/clan/clan_withdraw.php` | 1 | System translated withdrawal, player identity argument | Clan authority/atomicity not certified |
| `bank.php` | 1 | System translated transfer, player name/amount arguments | Economy authority/atomicity not certified |
| `bios.php` | 2 | Administrator system translated notices | Administrative caller authority not certified |
| `donators.php` | 2 | Administrator system translated points/reason | Administrative caller authority not certified |
| `lib/mail.php` | 1 | Historical duplicate, no current include/reference found; not the active mail entrypoint | Retained legacy code, not certified as an independently supported route |

Remaining work includes each legacy caller's authorization, CSRF, replay,
transaction coupling and domain schema. These systems were not implemented or
promoted. The helper's explicit contract does not confer authority on a caller.

## Verification and acceptance

New HTTP matrices (eight consolidated methods):

- `test_player_mail_send_transport_search_and_bounds`
- `test_player_mail_reply_ownership_staleness_and_rendering`
- `test_player_mail_gamemaster_settings_locked_and_capacity`
- `test_player_mail_late_failure_consumption_and_fresh_retry`
- `test_player_mail_capacity_two_independent_senders`
- `test_player_mail_notifications_commit_failure_preferences_and_display`
- `test_player_mail_privilege_rechecked_after_waiting_for_account_lock`
- `test_player_mail_system_helper_notifications_and_missing_recipients`

They prove search cardinality and forged options; anonymous/direct transport;
ordinary/GM/System separation; subject/body boundaries; reply ownership and
escaping; stale recipient, settings, privilege and reply rows; deletion; cap fill
between issue and send; duplicate/two-form replay; independent SQL contention;
real CHECK-constraint INSERT failure for normal/reply/GM; fresh retry; no external
attempt on rollback; and failed external delivery observing the committed row
from a separate connection. Inbox/read/system translation/delete/unread regressions
are included. `MailContentTest` and `SystemMailTest` add six PHPUnit methods for
business schemas, invalid IDs, missing recipients, sender classes, notification
ownership and raw content persistence.

Local PHP 8.4.26 / MariaDB 10.11.14 verification:

- PHPUnit: **114 tests / 2,904 assertions PASS**, no skips.
- Final practical HTTP selection: **22 methods PASS in 84.092s**: eight player-mail,
  six PvP and eight Training methods, including real Training referral rollback
  and PvP autoround completion. All eight new mail matrices also passed in a
  separate consolidated run. Existing create/login/render/logout and mailbox
  delete/CSRF tests passed during broader setup; nine tooling tests passed.
- Complete Python collection: **129 tests**, including 120 HTTP methods. A full
  local-suite pass is NOT claimed. Broad runs stopped in unchanged
  `_specialty_terminal_capture` at repeated CREATE TABLE; a direct PDO probe
  identified error 1005 / errno 184, "Tablespace already exists" after DROP.
  This local fixture limitation recurred in a fresh local database. The five
  PvP/Training methods using that observer remain fully enabled for exact CI;
  no test/assertion was removed, weakened or skipped in the repository.
- Both PHPStan gates PASS; the new mail authority helper is included in the
  level-6 gate. Composer strict validation, locked install and audit PASS,
  no advisories. PHP lint and repository/historical checks PASS.
- Historical object/source/tree match the instructed pins, 417 files and
  11 preservation commits. No dependencies or workflows changed.

Exact candidate publication/CI IDs and final clean-tree lint total are recorded
in the current PR #1 checkpoint. Supported acceptance requires BOTH PHP 8.4 /
MariaDB 11.4 and PHP 8.5 / MySQL 8.4; local MariaDB 10.11 is supplemental.
The workflow timeout remains 60 minutes. No tests or supported target were removed.
The complete Python collection is 129 tests, including 120 HTTP methods.

Player mail address/write/reply/send: **BLOCKED pending exact published supported
matrix acceptance**. Shared player-facing/helper contract: **BLOCKED on the same
gate**. All systemmail caller trust: **PARTIAL / BLOCKED**. Phase 3 INCOMPLETE;
merge NO; public hosting NO; no Phase 4. If both exact-head workflows succeed,
accept only this player/helper scope, then prioritize remaining systemmail caller
trust from this inventory. If CI remains active after bounded observation, stop
with PLAYER MAIL CANDIDATE PUBLISHED, CI PENDING. If it times out, report the
runtime leg/last completed test and stop without raising the timeout.

## Systemmail caller-contract review (2026-10-14)

Accepted predecessor: head `7e57ed5cba5384d1b4cb707b920f7b2c647beb86`, Modern core
37226146119 SUCCESS, Baseline integrity 37226145947 SUCCESS (PHP 8.4 / MariaDB 11.4
and PHP 8.5 / MySQL 8.4). Player mail, the shared player-facing/systemmail helper,
Ramius and broader New Day: **PASS**. The helper was not changed by this review.

Recomputed current inventory (tracked shipped PHP, excluding the definition, tests,
lab, scripts and historical copies): **14 invocations in 10 files**, unchanged:
`lib/player_mail.php` 1, `lib/pvpsupport.php` 2, `lib/training_outcomes.php` 1,
`lib/graveyard/case_haunt3.php` 1, `lib/clan/applicant_apply.php` 2,
`lib/clan/clan_withdraw.php` 1, `bank.php` 1, `bios.php` 2, `donators.php` 2,
`lib/mail.php` 1. No shipped module calls the helper. `login.php` and
`lib/su_access.php` contain no call.

Caller contract versus parent route (the two are recorded separately):

| Call | Recipient | Sender / content | Transaction | Caller contract | Parent route |
| --- | --- | --- | --- | --- | --- |
| `player_mail.php:127` | Locked offered account | Actor or GM descriptor; plain | Owned + queue | PASS | PASS (accepted) |
| `pvpsupport.php:126,177` | PvP victim row | System; fixed formats, scalar args | PvP-owned + queue | PASS | PvP accepted |
| `training_outcomes.php:36` | Session referer ID | System; fixed format, scalar args | Training-owned + queue | PASS | Training accepted |
| `case_haunt3.php:22` | Account row by login | System; fixed subject/body, player name arg | None; standalone helper commit | PASS | BLOCKED |
| `applicant_apply.php:18` | Officer rows of target clan | System; fixed `$apply_short`, applicant name arg | None; standalone | PASS | BLOCKED |
| `applicant_apply.php:34` | Session actor | System; fixed text, clan name/short/description args | None; standalone | PASS after correction | BLOCKED |
| `clan_withdraw.php:47` | Officer rows | System; fixed subject/body, player name arg | None; standalone | PASS | BLOCKED |
| `bank.php:137` | Resolved account row | System; fixed text, player name + int amount | None; standalone | PASS | BLOCKED |
| `bios.php:18,25` | Moderator-selected `userid` (helper-validated) | System; fixed, no args | None; standalone | PASS | BLOCKED |
| `donators.php:101,103` | Administrator-selected `id` (helper-validated) | System; fixed formats, points/reason args | None; standalone | PASS | BLOCKED |
| `lib/mail.php:104` | Not reachable | Not reachable | n/a | Inert legacy duplicate | Not a route |

Every legacy System call uses the default sender 0 historically, never derives a
sender from request input (no request value reaches the fifth/fourth parameter),
and supplies application-controlled format text as element 0. Player, clan and
administrator values appear only as scalar arguments. Request-derived recipients
(`bios.php`, `donators.php`) pass through `MailContent::id`; malformed values
reject before the parent UPDATE in `bios.php`, nonexistent IDs return false.
Ignoring `false` is acceptable: every legacy notice is advisory and none of the
routes claims delivery. None of the reviewed callers runs inside a database
transaction (`resurrection_player_mutation`, module daily hooks and the mail/PvP/
Training owners are not on these paths), so the helper's standalone transaction
is the valid mode and no `mail_notifications` queue is required. No reviewed
caller sends a second PHP `mail()`; external email remains post-commit best effort.
Clan `DELETE ... subject='serialize(...)'` dedupe matches the helper's stored
`serialize(MailContent::translated(...))` form for valid list envelopes.

Storage (worst case, `MailContentTest::testLegacySystemCallerEnvelopesFitStorage`):
60 four-byte-character player names, 255-character clan names, 5-character short
names, the 4,096-byte `clan_motd.php` description cap with full newline expansion
(about 9.7 KB serialized), `PHP_INT_MAX` bank amounts and 63,000-byte donation
reasons all fit VARCHAR(255) subjects (largest 114 characters) and TEXT bodies.
No generic limit changed.

**Correction.** Clans founded by `applicant_new.php` store NULL `clandesc`.
`nltoappon(NULL)` raises a PHP 8 deprecation that the application error handler
converts to an exception, so applying to any clan without a description failed
after the officer notices had committed and before the application was saved.
The reminder argument is now `nltoappon((string)$row['clandesc'])`, computed once
and passed as the scalar argument; NULL/empty means no reminder, as historically
intended. `test_systemmail_clan_application_null_description_reminder` proves the
NULL path (application saved, one officer notice, no reminder) and the described
path (exact System subject and description argument); it fails without the fix.

**`lib/mail.php`.** No tracked file includes or requires it; every `mail.php`
reference resolves to the root entrypoint. On the disposable loopback fixture,
anonymous GET, authenticated GET, a benign authenticated POST and authenticated/
anonymous `op=send` POSTs all stopped at `require_once("common.php")` (web SAPIs
run with the script directory as working directory), with zero mail rows. With the
application root deliberately added to `include_path`, the same requests stopped
in common.php's not-installed branch (CWD-relative `dbconnect.php` check) before
any database, session-authority or mail code, again with zero rows. It is obsolete
duplicate code, not an independent route; no source change was made to it.

Parent-route blockers retained (not changed here): haunting is GET `op=haunt3`
with request-interpolated login SQL and a non-`_once` helper include; clan apply/
withdraw interpolate serialized player names into DELETE SQL (an apostrophe in a
name aborts the route), commit officer notices before session clan state is saved,
accept any positive target clan ID, and clan detail/MoTD views still pass NULL
descriptions to `nltoappon`; bank keeps `abs((int)httppost('amount'))`, login-
interpolated lookup and a non-atomic debit/credit/notice sequence; bios block/
unblock are GET mutations without CSRF that notify before the UPDATE; donation
adjustment is GET-driven with interpolated SQL and commits points before a
helper rejection of malformed (array/invalid UTF-8) reasons.

All shipped systemmail caller contracts: **PASS**. Parent routes: Haunting, Clan
application/withdrawal, Bank, Bio moderation and Donation administration remain
**BLOCKED**. Serialized-state inventory unchanged: 112 sites / 53 files / 42
serialize writers-checks / 69 ScalarState reads / one centralized unserialize.

## MySQL 8 leading-dot table reference (2026-10-15)

Modern core run 38633714168 (`a808579`) failed on PHP 8.5 / MySQL 8.4 in
`test_systemmail_clan_application_null_description_reminder`: the clan apply
request died at `lib/clan/applicant_apply.php:15` with "Database operation
failed". Line 14 built `DELETE FROM . mail WHERE ...`; MySQL 8.4 rejects the
legacy `.tbl_name` form (ERROR 1064) while MariaDB 11.4 accepts it. The line is
unchanged from `historical-source-1.1.2`, where `.mail` names the table in the
default database, so removing the stray dot preserves the historical statement.
That was the only hit in shipped PHP (root, `lib/`, `modules/`, `src/`); the fix
is syntax only and leaves the clan route's recorded blockers above in place.
`DatabaseTest::testShippedSqlHasNoLeadingDotTableReference` fails on the
pattern `. " . db_prefix(` returning (it named exactly this line before the fix).
