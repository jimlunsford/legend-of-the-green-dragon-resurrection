# Petition administration authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `3517a6c6b4b95b6772bf9361574128314e2aed66`
(CLAUDE.md and ROADMAP.md on top of `4478c06`). Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`. Predecessor Modern core
`38718023935` and Baseline integrity `38718024016`: SUCCESS at that head.

Scope is `viewpetition.php` only, with its route-local helpers in
`lib/petition_admin.php`. Petition intake (`petition.php`) is already PASS and
unchanged. The links the viewer offers to `user.php`, `donators.php` and
`bio.php` are unchanged and those routes are not reviewed here. Commentary
posting and moderation are unchanged.

Before this change the file was 1.1.2 apart from the `each()` cursor shim.
Every finding was confirmed in current source and in `historical-source-1.1.2`:
GET status change with unbound `id`/`setstat` and no CSRF, replay or status
check; list-view DELETE of closed petitions with an unbound date and no
commentary removal; unbound commentary stamp; unbound view and
previous/next queries; a stray `}` in the Hide Details URL; manual delete
commented out.

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Authorization | `check_su_access(SU_EDIT_PETITIONS)` first | Same call, before any input handling, commentary, cleanup or output | Already server-side; now proven for anonymous, ordinary, insufficient-superuser and petition-admin roles |
| Input | Raw `httpget` strings for `op`, `id`, `page`, `viewpageinfo` | `op` in `''`/`view`/`setstat`; `id` 1..4294967295 (required for view/status); `page` positive integer; `viewpageinfo` 0/1. Anything else is 400 before any effect | Typed input; `op=del` stays disabled |
| Status change | GET `viewpetition.php?setstat=N&id=M` on the list view | POST `viewpetition.php?op=setstat&id=M` with `setstat`, `csrf_token` and a one-use `action_token` bound to `M:currentStatus`. GET to the form target is 405; the old GET link only displays the list | Historical statuses, labels, which buttons the list offers (Close, U, P, E) and the return to the list are kept. The view page's "Mark" navs became one POST form with the same eight statuses |
| Status validation | Any string pasted into SQL | Only the historical set 0–7; others 400 without spending the intent. Missing petition 404 | Invalid input never mutates |
| Same status | `if ($row['status']!=$setstat)` skips the write | Kept: valid same-status submission writes nothing and does not invalidate the counter; the intent is spent | Historical no-op |
| Replay, duplicate, stale | Every GET reapplied | Second use of a form is 409; a form issued before another admin changed the status is 409 (context mismatch, and rechecked under `FOR UPDATE`) | One-use intent bound to petition and status |
| Status write | One unbound UPDATE, then cache invalidation | Bound UPDATE of status, `closeuserid`, `closedate` in a transaction after locking and rechecking the row; `petition_counts` invalidated only after commit | Status change and counter invalidation are one operation; failure rolls back and the spent intent cannot retry, a fresh form can |
| Commentary stamp | Any non-empty `insertcommentary` stamped `closeuserid`/`closedate` on the unbound GET `id` | After `addcommentary()` has required POST, CSRF and a session-issued section, a non-empty post whose section is exactly `pet-<id>` stamps that petition with a bound UPDATE; an UPDATE cannot create a row, so only an existing petition is stamped | Same protection the post already has; commentary itself unchanged |
| View, prev/next, list queries | `'$id'`, `LIMIT $limit`, `CASE status WHEN k THEN p` pasted | All bound, including LIMIT and the CASE ordering values | No string-pasted IDs or statuses |
| Missing petition on view | Read offsets of `false` | 404 before output | PHP 8 would otherwise raise |
| Hide Details | `viewpetition.php?op=view&id=$id}` | `viewpetition.php?op=view&id=$id` | Stray `}` |
| Body and page info | `htmlentities(stripslashes(...))` | `htmlentities(...)` without `stripslashes` | 1.1.2 stored magic-quoted text, so `stripslashes` restored the author's text. Intake now stores raw text, and `stripslashes` would delete real backslashes. Removing it restores the historical display. Escaping is unchanged |
| Names, labels | `output_notl` / `output` | Unchanged (escaped); button labels are fixed markup | Proven with an author name containing `<img src=x>` |
| Updated column | `if ($row['closedate'] != 0)` | Blank for NULL (fresh installs default `closedate` to NULL) and for the zero date (upgraded 1.1.2 data) | PHP 5 compared the zero date equal to 0; PHP 8 does not |

## Cleanup decision

**Kept on the list view; made bound, atomic, idempotent and honest.**

Evidence: 1.1.2 runs the delete on every list load (`op==""`), and the page
promises "Closed petitions will automatically delete themselves when they have
been closed for 7 days". Moving it behind a POST would make deletion depend on an
administrator pressing something, which changes that promise. The delete takes
no request input: its only parameter is the server clock, so a forged or
repeated GET can do nothing more than what a list load or the clock already does.
It runs only after `SU_EDIT_PETITIONS` authorization. For these reasons it is
recorded as a deliberate, time-driven retention exception for the GET-mutation
rescan, not an open GET mutation.

Implementation (`resurrection_petition_cleanup`): one transaction selects the
expired closed petitions `FOR UPDATE` with a bound cutoff, deletes each one's
commentary and then the petition, and commits. Concurrent list loads serialize on
the lock and the second finds nothing; repeating it removes nothing. Counter and
comment caches are invalidated only after a commit that removed rows. On failure
everything rolls back and the list still renders with "Expired closed petitions
could not be removed this time; nothing was deleted." On success with removals it
reports the count. It no longer relies on `db_affected_rows()`.

**Orphaned commentary is deleted with its petition.** 1.1.2's automatic delete
left `pet-<id>` commentary behind. Its disabled manual delete (`op=del`) removed
the petition and `pet-<id>` commentary together, which shows the intended
ownership. The rows have no other reader: the section is only displayed on that
petition's page. Leaving them would keep administrator notes about a removed
petition with nothing left to show them, and would attach them to any later
petition that ever received the same ID.

## Tests

Two HTTP tests in `tests/tooling/test_web_application.py`:

- `test_petition_admin_status_authority_http`:
  - Role matrix (anonymous redirect or 403; ordinary player and `SU_EDIT_COMMENTS|SU_EDIT_USERS|SU_EDIT_CONFIG` 403 on list, view and status POST, no change).
  - GET-only display and the 405 for GET on the form target.
  - Escaping of body, page info and author name, the backslash kept, and the Hide Details URL.
  - Invalid IDs, `op=del`, `page`, `viewpageinfo` and statuses rejected as 400, and missing petitions as 404, without spending the intent.
  - Missing or wrong CSRF rejected as 403.
  - A successful change, then replay 409, stale list form 409, the same-status no-op, and a single-use no-op form.
  - A CHECK-constraint failure on the status write returning 500 with nothing changed, the spent form returning 409, and a fresh form succeeding.
- `test_petition_admin_cleanup_and_commentary_http`:
  - Refused roles delete nothing.
  - A foreign-key hold makes the petition delete fail after its commentary was deleted. The transaction restores the commentary, and the list reports the failure.
  - After the hold is released, one load removes the expired closed petition and its commentary and reports it, while a recent closed petition and an old open petition stay. A second load removes nothing and reports nothing.
  - A protected `pet-<id>` comment stamps its petition. A post whose section does not match the URL stamps neither petition.

Local supporting evidence (PHP 8.5.11 setup-php build, MySQL 8.4.11, fresh
database, PHPUnit install, then `test_create_login_rotate_render_logout`):
both tests fail against the previous `viewpetition.php` (GET `?setstat=` links
present; list view fatal when the old delete fails) and pass with the change.
PHP lint, both PHPStan gates (`lib/petition_admin.php` added to the level 6
gate), Composer validate/audit, historical verification and project validation
pass. Acceptance comes only from CI on both supported legs.

## Remaining petition-related gaps

- Intake invalidates `petitioncounts`, but the counter reader and this viewer use
  `petition_counts` (also so in 1.1.2). With the data cache enabled, new petitions
  can show a stale count until another invalidation. Intake is out of scope here.
- The commentary stamp is a separate statement after the comment insert, not one
  transaction with it, because commentary was not redesigned.
- The viewer still links to `user.php`, `donators.php` and `bio.php`, which remain
  unreviewed or BLOCKED in their own families.
- Each petition form shown adds one intent entry to the session (bounded by the
  petitions an administrator has displayed).

## Acceptance, 2026-10-15

`ef97952d4444816d22bb6fc231d69e7d3ba0bc70` is accepted. Modern core
**38722109813 SUCCESS**: both `result` jobs and every `checks` and `http` shard job
succeeded on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4. The longest job was
the MySQL `http (3)` shard at 18m08s. Baseline integrity **38722109661 SUCCESS**.
Conclusions come from the GitHub Actions run and job API; job logs were not
readable, so totals are not restated. Petition administration is Done in
`ROADMAP.md`.

## Petition counter cache key, 2026-10-15

Readers and invalidators of the petition counter in shipped PHP:

| Site | Role | Key |
|---|---|---|
| `lib/pageparts.php` (superuser nav count) | Reader (`db_query_cached`) | `petition_counts` |
| `lib/petition_admin.php` status change | Invalidator | `petition_counts` |
| `lib/petition_admin.php` 7-day cleanup | Invalidator | `petition_counts` |
| `lib/petition/petition_default.php` intake | Invalidator | `petitioncounts` before, `petition_counts` now |

`viewpetition.php` keeps 1.1.2's commented-out manual-delete invalidation, which
is not executed. In 1.1.2 the same split exists: `lib/pageparts.php` reads
`petition_counts` and `viewpetition.php` invalidates `petition_counts`, but intake
invalidates `petitioncounts`. With the data cache enabled, the superuser petition
count therefore stayed stale after a new petition until another invalidation or
cache expiry. Intake now invalidates the key the reader uses; nothing else in
intake or caching changed. `DatabaseTest::testPetitionCounterInvalidationsUseTheReaderKey`
fails if any petition counter invalidation uses a key no `db_query_cached` reader
uses; before the fix it named `lib/petition/petition_default.php: petitioncounts`.
