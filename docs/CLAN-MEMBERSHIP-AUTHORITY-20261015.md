# Clan membership authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `3b6d7800e294f811229605bc0b06032ba2003b02`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. Predecessor Modern core
`38736206216` and Baseline integrity `38736206145`: SUCCESS at that head.

Part 1 of 2: who is in which clan, at what rank, and every path that changes that —
creation, application, approval, rank changes, removal, member and applicant
withdrawal, leader succession and empty-clan cleanup — in `clan.php`,
`lib/clan/applicant.php`, `applicant_apply.php`, `applicant_new.php`,
`clan_default.php`, `clan_membership.php`, `clan_start.php`, `clan_withdraw.php`
and `list.php`, with route-local helpers in `lib/clan_authority.php`. Part 2 (clan
text and moderation: `clan_motd.php`, `detail.php`) is untouched. Before this
change these files were 1.1.2 except the `a808579` NULL-description cast and the
`c33136a` MySQL 8 table-reference fix in `applicant_apply.php`.

Every mutation is POST + CSRF + a one-use intent bound to the actor and target
clan state, run inside `resurrection_player_mutation()` (locked actor and target
rows, stale check, rollback), which also owns the systemmail post-commit
notification queue (`resurrection_clan_mutation()`). GET only displays, except the
two historical housekeeping steps below.

## Treasury

Core LoGD 1.1.2 has **no clan treasury**. The `clans` table holds only id, name,
short name, MOTD, description, their authors and custom say; no historical source
stores or moves clan money. "Treasury" is removed from the roadmap; nothing was
invented.

## Cross-clan findings

- **Rank change across clans: proven.** Against the previous code, an officer of
  clan A requesting the 1.1.2 GET `clan.php?op=membership&setrank=31&whoacctid=<B
  member>` changed that clan-B member's rank from 10 to 20 (the officer's cap): the
  target was looked up by ID and updated by login with no clan condition. Now the
  target must be in the actor's clan under lock, and no form exists for anyone
  else; the test proves the GET and a forged POST change nothing.
- **Removal across clans: latent, not reproducible on the supported databases.**
  The 1.1.2 UPDATE (`WHERE acctid=... AND clanrank<=actor`) has no clan condition,
  so on a permissive server it would remove a member of another clan. On the
  supported strict servers every removal — same clan included — failed before any
  change, because it wrote `clanjoindate='0000-00-00 00:00:00'` (probe: "Database
  operation failed" on MySQL 8.4). Removal now requires the same clan under lock
  and resets the join date to NULL.

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Rank change | GET `setrank`+`whoacctid`; any value, capped at the actor's rank; no clan check | One POST form per member row offering exactly the 1.1.2 operations for that row (promote to the next rank, demote to the previous, founder steps down, remove); the server derives the rank from the `clanranks` rank set and keeps the cap | GET mutation, cross-clan change, arbitrary ranks |
| Who may do what | Encoded only in which links were drawn | The same rules enforced on the locked rows: operations need rank above member; promote if target < actor and < founder; demote if target ≤ actor, above applicant, not self and a lower rank above 0 exists; founder may step down; remove if target ≤ actor and not self | Server-side authority |
| `clan-setrank` hook | Before the write; `handled` skips the rank write | Unchanged, inside the transaction | Module contract |
| Removal | GET `remove`; unbound; zero join date (fails on strict servers); pasted notice-subject DELETE | POST, same-clan check, NULL join date, bound notice cleanup | GET mutation, broken path, apostrophes |
| Application | GET `op=apply&to=N` saved the application; any positive id; officer notices committed before the player's clan state | GET asks; POST applies only to a clan the list offers (exists, has a member above applicant, not hidden by `clan-applymember`) and only for a player in no clan. Clan state, the bound cleanup of old unread notices, officer notices and the description reminder are one transaction; mail notification follows commit | GET mutation, arbitrary clan, ordering |
| Applicant withdrawal | GET `op=withdraw`; zero join date; pasted DELETE | GET asks; POST clears the application (NULL join date) and its unread notices, bound | Same |
| New clan | POST without CSRF; `addslashes()` copy compared with magic-quoted input; fee and INSERT separate | Protected POST. Validation rules, messages, fees and founder rank (31) unchanged; names compared and bound raw, so apostrophes are accepted as the rule always said; uniqueness (locked), fee check, `process-createclan`, INSERT, debit and founder membership are one transaction | CSRF, PHP 8 behavior without magic quotes, atomicity |
| Insufficient fee | GET offers no form; POST path showed the fee message | Unchanged on GET; a form issued before the purse shrank is stale (409); the fee message remains for a fee check under lock | Bound intent |
| Member withdrawal | GET `op=withdraw` after a confirm page; succession, deletion, member reset and notices as separate statements; zero join dates | Confirm page posts a protected form; `clan-withdraw`, sole-leader succession (highest rank, then oldest join, then id) or deletion with `clan-delete` and member reset, bound notice cleanup and officer notices, then the actor's reset, all in one transaction | Atomicity, broken zero dates |
| Membership summary | `GROUP BY clanrank DESC` | `GROUP BY clanrank ORDER BY clanrank DESC` (same rows) | MySQL 8 removed `GROUP BY ... DESC`; the clan hall failed |
| Author names | `$row['name']` of a missing author | Empty name when no author | PHP 8 raised on new clans' clan hall |
| SQL | IDs, ranks and subjects pasted | Every clan id, account id, rank and subject bound in these files | Binding |

Historical fees, rank names, messages and succession order are unchanged.

## Housekeeping on view

Both remain on view, as in 1.1.2, because they take no request input, only repair
state any viewer would see, and are idempotent; both are now bound and run under
locks so concurrent viewers cannot double-apply them.

- **Leader auto-promotion (clan hall).** 1.1.2 promotes the highest-ranking, then
  oldest, member when a clan has no leader (for example after a leader's account
  expired). It now locks the clan's members, rechecks that no leader exists and
  promotes one; the viewer's own rank is updated through the session as before.
- **Empty-clan deletion (clan list).** 1.1.2 deletes a clan with no member above
  applicant when the list is shown. It now locks the clan, rechecks the count and
  deletes only if still empty. Applicants of a deleted clan are reset lazily by
  `common.php`, as before.
- The application list's empty-clan delete in 1.1.2 is unreachable (its `INNER
  JOIN` only yields clans with members), so it was dropped; the list view owns the
  cleanup.

## Tests

- `test_clan_rank_and_removal_authority_http` — anonymous; the 1.1.2 GET links
  (cross-clan and same clan) change nothing; no form for another clan's member and
  a forged POST is refused; invalid actions, arrays and extra fields 400; CSRF 403;
  an officer cannot promote an officer; approval of an applicant; promotion capped
  at the actor's rank; demotion; stale form; removal with apostrophe/UTF-8 name and
  its unread notice cleanup; members see no operations; leader promotes to leader;
  founder steps down.
- `test_clan_creation_application_and_withdrawal_http` — invalid, short, duplicate
  name and short name; CSRF; array input; unfunded (no form, stale form 409); a
  CHECK-constraint failure on the founder write after the clan INSERT rolls both
  back, the spent intent cannot retry, a fresh form creates `O'Brien Hall` with
  exact fees; last-member withdrawal deletes the clan and resets its applicant;
  applications only to listed clans; apply, withdraw application and reapply
  leave exactly one unread officer notice for an apostrophe/UTF-8 name; sole-leader
  withdrawal promotes the oldest member and notifies them.
- `test_clan_housekeeping_on_view_http` — leader auto-promotion once; empty-clan
  deletion of only the empty clan.
- `test_systemmail_clan_application_null_description_reminder` (from `a808579`)
  now applies through the rendered form; its assertions are unchanged.

All three new tests fail against the previous clan code and pass with the change.

## Gaps handed to Part 2 and elsewhere

- Part 2: `clan_motd.php` (MOTD, description, custom say) and `detail.php`
  (superuser rename, description block/unblock) are unchanged, as are the NULL
  description displays there.
- `common.php` still reads the actor's clan with `clanid` interpolated into a
  cached query (a database integer, not request input).
- `lib/charcleanup.php` (account deletion) has its own clan succession and still
  writes a malformed zero join date; it belongs to the account-deletion item.
- No bundled module hooks `clanranks`; module-added ranks are supported through the
  same rank set but not exercised by a bundled module.
