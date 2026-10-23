# Final rescan task 2: admin tools and small leftovers, 2026-10-22

Candidate pending CI. Starts at `aea4608`. Closes the twelve OPEN entries of the route inventory
(`ROUTE-INVENTORY-20261022.md`) and five small Final rescan lines, and adds the race test the Final rescan list asked
for. Out of scope (rescan task 3): the admin repair path for locked-out players and the final unbound-SQL sweep.

Each line was checked against the source at `aea4608` before the change.

## Shared rules

Every admin mutation here follows one pattern (`lib/admin_tools.php`, with `lib/player_mutation.php`):

- The page's own flag through `check_su_access()` first, so anonymous visitors, players and staff without the flag get
  403 (or the login redirect) before anything else.
- Only a POST mutates (405 otherwise). Fields other than the form's own and the CSRF and intent tokens are 400.
- CSRF (403), then a one-use intent bound to the stored state the form was shown for (409 when it is stale, repeated
  or for another target).
- The checks and writes run in one transaction that locks the rows first and re-checks them; a change between the
  form and the lock is 409, any later failure rolls everything back and answers 500 ("Nothing was changed").
- Request text is checked (arrays, invalid UTF-8 and text over the column size are 400) and bound. ORDER BY comes from
  an allow-list, LIMIT from checked integers.
- Stored text is escaped on output.

## A. Admin tools

| Tool | 1.1.2 | Now |
| --- | --- | --- |
| `superuser.php?op=newsdelete` | Any staff flag that enters the grotto; a GET link; `newsid` pasted into the DELETE; returned to whatever followed the last `/` of `return`. | `SU_EDIT_COMMENTS`, the flag `news.php` already showed the Del link for. The Del link is a small POST form; the intent is bound to the whole news row; the row is locked and re-checked before the bound DELETE. `newsid` must be 1 to 4294967295 (400), a missing row is 404. The return is `news.php` with only its `offset` and `page` numbers (a nav link's comment counter is dropped), otherwise `news.php` (`resurrection_news_return()`). |
| `superuser.php?op=keepalive` | GET write of the admin's own `laston`, pasted; the refresh tag echoed the raw request URI. | Stays a GET (decision below). Bound; the refresh goes to the fixed `superuser.php?op=keepalive`. |
| `badword.php` add, remove, addgood, removegood | Any request to the op rewrote the list (no CSRF); `addslashes()`; a DELETE and an INSERT with nothing around them, so a failed INSERT lost the list. | POST, CSRF, an intent bound to the list as shown; the list row locked, compared, then a bound DELETE and INSERT in one transaction (a failure keeps the old list); the joined list must fit the 64 KiB column. The stored format is 1.1.2's: unescaped single quotes in the nasty list get a backslash, CR and LF are removed, remove takes the word as stored. `op=test` writes nothing but now needs a POST with CSRF. |
| `gamelog.php` | `cat` pasted into WHERE; `start` cast and pasted into LIMIT (a negative start was a database error); category links unencoded. | `cat` is text up to 255 bytes, `start` up to nine digits (400 otherwise); both bound; the previous page never goes below 0; category links `rawurlencode()`d. Messages were already escaped by the output layer. |
| `referers.php` | Every view deleted expired referers (with an undefined query when `expirecontent` was 0); `sort` pasted into both ORDER BY clauses; links built with `ENT_COMPAT`, any scheme. | The expiry stays (decision below), from `(int)expirecontent` and the clock only, bound, and skipped at 0. `sort` is one of the seven values the sort links produce (400 otherwise). The "URL" sort orders the per-site summary by site: 1.1.2's `ORDER BY uri` on a `GROUP BY site` query is refused by MySQL 8.4's ONLY_FULL_GROUP_BY (error 1055), so that sort was a 500. Links escaped with `ENT_QUOTES`; only http and https addresses are links. |
| `referers.php?op=rebuild` | A GET link rewrote every site with `addslashes()`. | GET shows a Rebuild form; the POST (CSRF, intent) locks every row and writes each site bound in one transaction. A site longer than the 50-character column is cut to 50 characters, as 1.1.2's non-strict MySQL stored it. |
| `translatortool.php` | `u` and `t` decoded a second time and stripped of slashes; shown with `htmlentities()` without quotes in one place. | `u` up to 255 bytes and `t` up to 64 KiB, valid UTF-8, used as received (the translator button encodes them once); escaped with `ENT_QUOTES`; the save form carries the intent for this namespace and text. |
| `translatortool.php?op=save` | Any POST, no CSRF; `uri`, `text`, `trans` pasted; Save No Close sent `Location: translatortool.php?op=list&u=` plus the raw page. | POST, CSRF, an intent bound to the language, namespace, text and the matching translation rows as shown; locked and re-checked in one transaction; bound. The 1.1.2 rules are kept: an empty translation deletes, one exact row is updated, a case-only match adds a row, an insert stores the version with 1.1.2's trailing space. Save No Close sends `translatortool.php?op=list&u=` plus the `rawurlencode()`d page, so the Location is always this tool on this site. |
| `translatortool.php?op=list` | `u` pasted. | Bound; namespaces, texts, versions and authors escaped; each edit link encodes the namespace and text once. |
| `untranslated.php` (view) | The language preference pasted; the text shown as HTML; a hidden `id` from a column the table does not have. | Bound (`ORDER BY rand(?)` with the game's random number); the text escaped and read-only; the hidden language and namespace escaped; no `id`. |
| `untranslated.php?op=list` (save mode), `op=step2` | INSERT and DELETE from posted values pasted, no CSRF; saved from any request. | POST, CSRF, an intent bound to the language, namespace and text the form showed; the INSERT and DELETE in one transaction, only while the text is still in the untranslated list (409 otherwise). The edit form is offered only for a text in the list (404 otherwise). `mode` and `ns` checked (400). |

Role matrix, from the tests: anonymous visitors are redirected or get 403; a player gets 403; staff without the page's
flag get 403 (for news delete, user and config editors and MOTD posters; for the translator tools, every flag except
`SU_IS_TRANSLATOR` and megauser); staff with the flag reach the page. The keepalive keeps 1.1.2's rule, any flag that
enters the grotto (`SU_DEBUG_OUTPUT` alone does not).

### Decisions

**Keepalive stays a GET.** It writes only the signed-in admin's own `laston`, and `saveuser()` writes the same column
on every page view (in 1.1.2 too), so a forged request can do nothing a page view does not. A POST with CSRF would add
no protection. The query is bound, and the reflected request URI in the refresh tag (a stored-nothing XSS in 1.1.2)
is gone.

**The referer expiry stays on view.** In 1.1.2 `referers.php` is the only place expired referers are removed: there is
no newday or maintenance cleanup for them. Moving it would change when old rows disappear. It only reads server values
(`expirecontent` and the clock), is bound, and no request value reaches it.

**An unset or invalid `serverurl` (mail notice).** The game mail is already delivered when the email is sent, so the
email is still sent, with "(Server URL not set or not valid)" in place of the link, as the petition notice does
(`resurrection_server_url()`, `lib/server_url.php`). Nothing is ever saved from the request.

**Deliberate corrections** (each tested):

- `translatortool.php?op=save` removes the matching untranslated text by its namespace; 1.1.2 compared it with an
  undefined variable and never removed it. With several matching rows, every exact match is updated (1.1.2 meant to,
  but kept only the last one, and with none it ran `IN ()`, a database error).
- The double decode in the translator form and the HTML-encoding inside its edit links are removed; 1.1.2's encoded
  links and option values looked up other text.
- The gamelog previous page never goes below 0.
- The referer list's URL sort orders the summary by site (see above).
- `badword.php?op=test` needs CSRF.
- The mail notice text changed from "at http://%s" to "at %s" in both places, because the link now carries its own
  scheme. A stored translation of the old English text no longer matches; the notice falls back to English.
- `dhms()` (`lib/dhms.php`) writes out PHP 5's integer truncation for `%`. PHP 8 refuses a float with a fraction there,
  so every `referers.php` view with a referer and the `stats.php` page-time lines were a 500. The output is 1.1.2's.

## B. Leftovers

| Line | 1.1.2 | Now |
| --- | --- | --- |
| Mail notification link (`lib/systemmail.php`) | `http://` plus the request's `Host` header and script folder: a sender's forged Host put any address into the recipient's email. | The configured, validated `serverurl` (decision above). |
| `getmount()` (`lib/mounts.php`) | The stored mount ID pasted into the cached query. | Only a whole number 0 to 4294967295 is looked up (otherwise no mount, as an unknown ID always was); bound; the cache key is still `mountdata-<id>`. |
| Name after a dragon kill (`lib/dragon_outcomes.php`, `lib/names.php`) | `accounts.name` is `varchar(60)`; the title from `titles` is at most 25. 1.1.2's non-strict MySQL cut a name that grew past 60 characters; under strict SQL the save failed, and the whole dragon kill answered 500 and rolled back. | The title editor's and user editor's rule: when the new name or title would not fit (measured in characters, the limits read from the schema), the old name and title are kept. The kill itself completes. |
| `bio.php` `ret` | `cmd_sanitize()` then whatever followed the last `/`, allowed as a navigation: `ret=/motd.php?x=/forest.php?op=fight` let a player into the forest fight. | `resurrection_bio_return()` (`lib/return_links.php`): the page must be one of the pages that link to a bio (village, inn, gardens, rock, shades, gypsy, lodge, superuser, moderate, motd, viewpetition, clan, prefs, user, pvp, list), with an optional folder prefix; its query is dropped, except a user editor or petition record (`op=edit&userid=N`, `op=view&id=N`). Anything else returns to `list.php`. |
| Race: clan leader deleted while a member acts on the clan | No test. | `test_clan_leader_deletion_races_member_withdrawal_http` (below). |

### The race

A user editor deletes the clan's leader (rank 30) while a member (rank 10) withdraws; an officer (rank 20) is the
heir. Account IDs are in the order leader, member, officer. The test runs each order: the first request runs on a
second PHP server and holds its locks while a fixture hook sleeps inside its transaction (the `delete_character` hook
for the deletion, the `clan-withdraw` hook for the withdrawal), and the second is sent while it sleeps.

- Deletion first: the deletion holds the leader, then every member in account order, then the clan row. The withdrawal
  waits at its own account lock (the test sees it waiting at a `FOR UPDATE`), and after the commit it withdraws a
  member from a clan the officer now leads.
- Withdrawal first: the withdrawal holds the member, every member, then the clan row. The deletion waits at the
  leader's lock, and after the commit it hands the clan to the officer, the only member left.

In both orders: both answer 200, the leader's account and its traces are gone, the member is out of the clan with no
join date, the officer is leader with their join date, the clan still exists, and each hook ran once. The lock order
holds; nothing is half done. The test passes on `aea4608` as well: it guards the lock order accepted in account
deletion (`DELETION-EXPIRATION-AUTHORITY-20261017.md`) rather than fixing a defect.

Recorded, not changed: each side locks one account before the member scan (the deletion its target, the withdrawal the
member), so two requests started in the same instant can deadlock. InnoDB then rolls one back whole (error 1213; the
request answers 500 with nothing changed). This is read from the code, not tested.

## New gap (on the Final rescan list)

`common.php`'s referer tracking runs on every page with an off-site `Referer`. It writes the referring host into
`referers.site` (`varchar(50)`) and the host and request path into `referers.dest` (`varchar(255)`) unchecked. Under
strict SQL a longer value is a database error, so the page fails. Not changed here (not one of this task's lines).

## Tests

PHPUnit `tests/unit/RescanTask2Test.php`: the bio and news return targets (folders, queries, off-site, protocol-relative,
`javascript:`, backslashes, quotes, upper case, longer names, the bio page itself); `getmount()` gives the same row for
an integer and a string ID and nothing for injection text, negatives, fractions, leading zeros, out-of-range values,
arrays and unknown IDs; `resurrection_name_fits()` counts characters, not bytes.

HTTP (`tests/tooling/test_web_application.py`):

- `test_news_delete_and_keepalive_authority_http`: role matrix; no Del form for staff without comment moderation; 405,
  403, 400 for an extra field and five malformed IDs, 404, 409 for another line's intent and for a changed row; the
  delete returns to `news.php?offset=1&page=2`; six off-site, script, other-page and bad-query returns go to
  `news.php`; a repeat is 404. Keepalive: 403 without a grotto flag and for `SU_DEBUG_OUTPUT`; an encoded script in the
  URL is not echoed; the refresh is fixed; `laston` is updated.
- `test_badword_editor_authority_http`: role matrix; 405; 403; 400 for an array, invalid UTF-8 and an extra field; 409
  for another op's intent, a repeat and a list changed after the form; the stored format with a quote, a backslash and
  UTF-8; add and remove on both lists; the test op needs CSRF and writes nothing; a late failure (a CHECK constraint)
  rolls back and keeps the old list.
- `test_gamelog_and_referers_authority_http`: role matrix on three routes; a quoted category matches only itself and
  its link is encoded; six malformed `start` and `cat` values are 400; the expired referer is removed on view and the
  current ones escaped; all seven sorts work, four bad ones are 400; the rebuild GET writes nothing; 403; 400; a late
  failure rolls back every row; a 60-character host is stored as 50 characters; a repeat is 409.
- `test_translator_tools_authority_http`: role matrix on seven routes; quotes, a backslash, HTML and UTF-8 in the
  namespace and text; 400 for arrays, length, invalid UTF-8 and an unknown op; save 405, 403, 400, 409 for another text,
  a repeat and a changed translation; Save No Close and three off-site, protocol-relative and CR/LF namespaces all
  redirect to this tool's list; several rows; an empty translation deletes; the list binds and escapes. The untranslated
  edit form only for a collected text (404 otherwise); its save and step2 405, 403, 400, 409 for a changed language,
  namespace or text and for a repeat; the random text escaped and read-only, no hidden `id`.
- `test_mail_notification_links_the_configured_server_url`: with a forged Host `evil.example`, the email links the
  configured `serverurl` (with and without a trailing slash, and with https and a port), or says it is not set for an
  unset, `javascript:`, quoted or user-info value; it is always sent; nothing is saved from the request.
- `test_dragon_kill_keeps_a_name_that_would_not_fit_http`: a short name takes the new title; a 58-character name (with
  a multibyte letter) keeps its name and title and the kill completes; a name that comes to exactly 60 characters takes
  the title; a custom title stays in the name.
- `test_bio_return_link_stays_on_known_pages_http`: twelve `ret` values on a living and a deleted character: the
  allowed navigation holds only the checked return, never the forest, an off-site host, `javascript:` or a module.
- `test_clan_leader_deletion_races_member_withdrawal_http`: above.

Static: `test_rescan_task2_entries_have_their_evidence` (`tests/tooling/test_route_inventory.py`).

No existing test was removed, skipped or weakened.

## Before the fix

The nine new HTTP tests against the `aea4608` pages, with only the test file changed:

| Test | First failure |
| --- | --- |
| news delete and keepalive | staff without comment moderation (bits 4288) deleted on a GET: 302, not 403 |
| badword | the add form had no CSRF or intent field |
| gamelog and referers | the quoted category's link was not encoded (`gamelog.php?cat=fixture%27%20OR%20%271` missing) |
| translator tools | `Fatal error` (database operation failed) for a quote in `u`, in the unbound namespace lookup |
| mail link | all seven cases: the email read "at http://evil.example/attack" |
| dragon name | the long-name kill answered 500, not 200 |
| bio return | ten of twelve cases: the navigation held `forest.php?op=fight`, `avascript:alert(1)`, `village.php` (for the off-site host), the module, or `village.php'><script>` |
| clan race | passes (it guards the existing lock order) |

The static test fails against the task 1 inventory (every entry OPEN). PHPUnit `RescanTask2Test` fails on `aea4608`
(the functions do not exist). Checked directly on MySQL 8.4: 1.1.2's referer summary with `ORDER BY uri` is error
1055.

## Local verification

Supporting evidence only; CI is the acceptance. PHP 8.5 (the setup-php build) with a disposable MySQL 8.4 per shard.
All four shards ran, because shared helpers changed (`lib/systemmail.php`, `lib/translator.php`, `lib/dhms.php`):

- `scripts/tooling-shards.py --shards 4`, each after a clean `composer test` (PHPUnit OK, 245 tests, 139449
  assertions): shard 1 OK (50 tests, 850 s), shard 2 OK (47, 869 s), shard 3 OK (55, 938 s), shard 4 OK (49, 898 s);
  198 of 198 discovered tests assigned.
- `tests/tooling/test_route_inventory.py` OK (4 tests).
- PHP lint (392 files, 0 failures); PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors (no baseline change).
- `composer validate --strict`: valid; `composer audit`: no advisories.
- Historical verifier PASS; project validator PASS; `git diff --check` clean.

## Remaining gaps

- The referer tracking in `common.php` (new line above).
- Rescan task 3: the admin repair path for locked-out players, and the final unbound-SQL sweep.

## CI failure on `623ca49`, 2026-10-23 (kept on record)

Modern core 39868186035 on `623ca49`: every job passed except engineering (8.4, mariadb:11.4) / http (1), which failed
`test_gamelog_and_referers_authority_http` at `self.assertIn("href='http://ref.fixture.example/path&#039;&lt;x&gt;'",
body)`: the referer list showed one site group, `x`, with the long fixture URL, and no group for
`ref.fixture.example` or `stale`; status 200. The job ran 22:54 to 22:58 UTC. Every MySQL job passed; Baseline
integrity 39868185442 passed. This run is not acceptance for rescan task 2.

Reproduction (supporting evidence only): shard 1 through `scripts/tooling-shards.py` at `623ca49`, with the
`referers` table dumped just before and just after the view, on PHP 8.4 with MariaDB 11.4.13 and with MySQL 8.4.13.
MariaDB failed exactly as in CI; MySQL passed.

| | Rows (id, site, `last`) | `NOW()` and PHP `date()` | Summary order (`GROUP BY site ORDER BY count DESC`) |
| --- | --- | --- | --- |
| MariaDB, before the view | 1 `ref.fixture.example`, 2 `stale`, 3 `x` (all 08:29:14), 4 `old.fixture.example` (2000-01-01) | both 08:29:19 UTC (`time_zone` SYSTEM, UTC) | `x`, `stale`, `ref.fixture.example`, `old.fixture.example` |
| MariaDB, after the view | 1, 2, 3 unchanged; 4 deleted | | `x`, `stale`, `ref.fixture.example` |
| MySQL, before the view | the same four rows (08:29:19 and 2000-01-01) | both 08:29:24 UTC | `old.fixture.example`, `ref.fixture.example`, `stale`, `x` |
| MySQL, after the view | 1, 2, 3 unchanged; 4 deleted | | `ref.fixture.example`, `stale`, `x` |

So every current row was stored and kept, the expiry removed only the 2000-dated row, the clocks agree, and the
summary query returned all three current sites on both databases. Ruled out: the expiry and the clocks, the summary
query itself, and `common.php`'s referer tracking (the rows are unchanged by the request, and the test client sends no
`Referer`).

Root cause: a product defect, kept from 1.1.2. `referers.php` counts the summary rows into `$number` for the site
loop, then reuses `$number` for each site's URL rows (`historical-source-1.1.2:referers.php` lines 54 and 72). After
the first site, the loop runs on only while its index is below that site's URL count, so with one URL per site only
the first site is ever shown. The three fixture sites have equal counts, and MariaDB and MySQL return the tie in a
different order: MySQL's first site was `ref.fixture.example`, the one the assertion looks for; MariaDB's was `x`. The
fixture was valid; the assertion was right; the test only passed on MySQL by the luck of the tie.

Fix: the per-site loop has its own counter (`$urls`), so every site of the summary (up to its 1.1.2 limit of 100) is
listed. This is a deliberate correction of 1.1.2 behavior. The test now also requires the other two current sites'
URLs, so it fails without the fix on both databases, whatever order the tie takes. No assertion was loosened.

Against `623ca49`'s `referers.php`, with only the strengthened test: MariaDB 11.4 failed at the
`ref.fixture.example` link (its first site was `x`); MySQL 8.4 failed at `http://other.fixture.example/a` (its first
site was `ref.fixture.example`).

Local result with the fix (PHP 8.4.27, supporting evidence only): shard 1 OK on MariaDB 11.4 (50 tests, 957 s) and on
MySQL 8.4 (50 tests, 971 s), each after PHPUnit OK (245 tests, 139449 assertions). No shared helper changed, so only
shard 1 (the changed test's) ran. Static: `test_route_inventory` and `test_tooling_shards` OK; PHP lint (392 files,
0 failures); both PHPStan gates no errors; `composer validate --strict` valid; `composer audit` no advisories;
historical verifier PASS; project validator PASS; `git diff --check` clean.
