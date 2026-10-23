# Clan text and moderation authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `2eff16799f16d6e5047cd89b871de2f28e87f209`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. Predecessor Modern core
`38767451569` and Baseline integrity `38767451036`: SUCCESS at that head (clan
membership, Part 1, accepted).

Part 2 of 2: clan MoTD, description and custom say line (`lib/clan/clan_motd.php`,
`clan.php?op=motd`), the moderator rename and description block/unblock
(`lib/clan/detail.php`, `clan.php?detail=N`), and the displays of that text. Helpers
were added to `lib/clan_authority.php`. One escaping line changed in
`lib/commentary.php` `talkform()`, where the custom say line is printed. Before this
change both route files were byte-identical to 1.1.2. Part 1 membership code was not
reopened. Bios, donations and commentary moderation are not touched.

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4.11 against `2eff167`:

| Finding | Observed |
|---|---|
| Officer opens `op=motd` for a new clan (NULL MoTD/description, author 0) | Fatal: `htmlentities()`/`nltoappon()` on NULL and `$row['name']` on a missing author |
| `op=motd` when the description is blocked (author 4294967295) | Fatal: the author lookup finds no row |
| POST `op=motd` without any CSRF token | MoTD written |
| MoTD containing an apostrophe | SQL error; nothing saved |
| Description `back \ slash` | Stored as `back  slash` (`stripslashes` removed the backslash) |
| Custom say of 20 characters | Strict mode refuses the write (`varchar(15)`); nothing saved |
| Custom say `x\'><q>` | Stored as `x'><q>` by the raw paste, then printed raw in the clan hall: `name='talkline' value='x'><q>` (HTML injection by a leader into every member's page) |
| `clan.php?detail=<id> OR 1=1` | 200; the membership query `WHERE clanid=<id> OR 1=1` listed a member of another clan |
| Moderator POST rename to another clan's names, no CSRF | Accepted: two clans named `Beta Hall` / `BETA` |
| Public `clan.php?detail=N` for a new clan | Fatal: `nltoappon(NULL)` (every clan until a description is saved) |

The rank checks used the account row the request loaded, which is fresh for each
request but not locked; a concurrent demotion could race the write. The route has no
clan parameter, so a member could only ever reach their own clan; that is unchanged.

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| MoTD/description/custom say save | POST, no CSRF, no intent; three autocommit statements | Protected POST with a one-use intent bound to the clan's stored names, text, authors and block state and to the actor's account, clan and rank. The clan row is locked and compared; the updates run in the historical order in one transaction | CSRF, replay, stale forms, partial writes |
| Who may edit | Officer and above: MoTD and description; leader and above: custom say; own clan only | Same rules, rechecked on the locked account row. An officer's POST carrying `customsay` is 400 (the form never offers it). A member's POST is 403 with the historical refusal text | Server-side authority |
| SQL and text | MoTD and custom say pasted raw; description `addslashes(stripslashes())` | Bound; stored exactly as entered. Invalid UTF-8 is 400 | Injection, apostrophes, lost backslashes |
| Length | MoTD and description `substr(…, 0, 4096)` in bytes (could split a UTF-8 character); custom say limited only by `maxlength=15` | The server cuts MoTD and description to 4096 characters and custom say to 15 characters, as 1.1.2's `substr` and a non-strict server did. The talk line must be one line: control characters are 400 | Strict mode refused oversized custom say; byte cuts broke UTF-8 |
| Unchanged text | Not rewritten; author kept | Same | Historical rule |
| Blocked description | Edit silently skipped; no textarea | No textarea, as before; a forged description is 400 and nothing is written. Only a moderator unblock lifts it | Blocks stay blocked |
| Displays on `op=motd` | Fatal on NULL text, missing or block-marker authors | NULL text shows empty; a missing author shows an empty name | PHP 8 |
| Form values | `htmlentities(…, ENT_COMPAT)` | `htmlspecialchars(…, ENT_QUOTES)` UTF-8 | Attribute safety |
| Custom say in the clan hall | Printed raw in the talk form's single-quoted hidden `talkline` field; the JavaScript preview and stored comments were already escaped | The hidden field is escaped. The preview (`appoencode` plus `addslashes`) and the comment display (`HTMLEntities`) are unchanged and proven escaped | Stored HTML injection |
| `detail` parameter | `$detail>0` string comparison, then pasted quoted and unquoted into four queries | Must be an integer of at least 1 (400 otherwise); every query binds it; a missing clan is 404 | Proven SQL injection |
| Moderator gate | SU_EDIT_COMMENTS; other POSTs ignored | SU_EDIT_COMMENTS, rechecked on the locked account; other POSTs are 403 | Server-side authority |
| Rename | `full_sanitize` (colour codes silently stripped), pasted, no uniqueness | A changed name must pass the clan-creation rules (letters, spaces, apostrophes and dashes; 5 to 50 characters; short name 2 to 5 letters; colour codes rejected) and be unique apart from this clan, checked under lock. The creation messages are shown. Unchanged names skip the rules, so older names stay usable. If either name is empty the rename is skipped, as before | Same rules as creation; duplicates |
| Block / unblock | `descauthor=4294967295` and the translated block text / `descauthor=0` and an empty description; button chosen by the current state | Same writes and texts, bound, in the same transaction as a rename. Blocking a blocked clan, or unblocking one that is not blocked, is 409. Both buttons at once is 400 | Stale forms |
| Detail display | Fatal on a NULL description; cached `SELECT` keyed `clandata-<id>` | NULL shows empty. The row is read directly by bound id | PHP 8; binding |
| Cache | `clandata-<id>` invalidated after each autocommit statement | Invalidated once after commit | Readers never see uncommitted text |
| Messages | "Updating MoTD", "Updating description", "Updating custom say line", "Updating clan names", "Blocking public description", "UNblocking public description", the block text and the refusal | Unchanged | Historical text |

## Tests

Two HTTP tests:

- `test_clan_motd_description_and_say_authority_http`:
  - Access: anonymous; a player in no clan; a member (GET refusal text, POST 403).
  - Officer basics: an officer's GET of a new clan renders; GET with query values writes nothing.
  - CSRF: a missing or wrong token is 403.
  - Bad input, each 400: an officer's custom say, array input, an extra field, invalid UTF-8.
  - Text handling: MoTD and description with apostrophes, backslashes, UTF-8, colour codes, HTML and newlines are stored exactly and authored.
  - Replay and stale forms are 409.
  - Escaping: hall and edit page escaped with the author name.
  - Length cuts: 5000 `é` and 4097 `雪` cut to 4096 characters.
  - Cross-clan: a clan-A form after the player moved to clan B is 409, and B's own form writes only B.
  - Custom say:
    - The leader's form escapes the value.
    - Multi-line and NUL custom say are 400.
    - The UTF-8/HTML custom say is stored exactly; 20 characters are cut to 15.
    - The clan hall talk form no longer prints it raw.
    - A posted comment stores and shows it escaped.
  - Rollback: a CHECK-constraint failure on the custom say write after the MoTD and description writes rolls all three back (500). The spent intent is 409, and a fresh form saves all three.
- `test_clan_detail_rename_and_block_authority_http`:
  - Public view: renders for a new clan (NULL description).
  - Bad `detail`: an injected value is 400 and a missing clan is 404.
  - Gates: a plain player and a superuser without SU_EDIT_COMMENTS see no form and their POSTs are 403. The moderator sees the form; GET writes nothing.
  - CSRF and input: CSRF is 403; array, extra-field and block-plus-unblock requests are 400.
  - Invalid renames, each refused with the creation message and nothing changed: digits, HTML, UTF-8, colour codes, both lengths, and both duplicate names.
  - Rename: a valid `O'Brien-Hall`/`OBH` rename leaves the other clan unchanged. Replay is 409, and so is a form made stale by an edit elsewhere.
  - Block: block writes the historical text and marker. The clan's officer then sees the blocked notice and no textarea. A forged description is 400; a MoTD-only save still works and leaves the block.
  - Unblock clears the description and the marker.
  - The public description shows escaped.

Both fail against `2eff167`. On the unfixed code, the MoTD test stops where a member's forged POST returned 200, not 403. The detail test stops on the fatal NULL-description public view. Both pass with the change. Local runs are supporting evidence only; acceptance comes from CI on both supported legs.

## Remaining clan gaps

- `common.php` still reads the player's clan with the session `clanid` interpolated
  into a cached query (an integer from the database, not request input; carried
  from Part 1).
- `lib/charcleanup.php` (account deletion) has its own clan succession and still
  writes a malformed zero join date; it belongs to the account-deletion item.
- No bundled module hooks `clanranks`. Module-added ranks are supported through the
  same rank set but not exercised.
- Clan moderation writes no audit or debug log, as in 1.1.2.

## Acceptance, 2026-10-15

`cbc0cf49875a59fd963b80cd7187bccc732141a0` is accepted. Modern core
**38779187332 SUCCESS**: both `result` jobs and every `checks` and `http` shard job
succeeded on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4. Baseline integrity
**38779187265 SUCCESS**. Conclusions come from the GitHub Actions run and job API.
Clan text and moderation is Done in `ROADMAP.md`.
