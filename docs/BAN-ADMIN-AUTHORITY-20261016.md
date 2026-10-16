# User editor part 2: ban administration, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `aeb19b93fce01a0829cdb4697bfde565524f5448`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Scope: the `user.php` ban ops `setupban` (form and similar-user lists), `saveban`,
`removeban` (list and `subop=xml`) and `delban`, plus a read-only check of
`lib/checkban.php` against what `saveban` stores. `moderate.php`'s comment ban and the New
Day ban purge were only confirmed. User delete stays under account deletion.

Files: `lib/ban_admin.php` (new), `lib/user/user_setupban.php`, `user_saveban.php`,
`user_removeban.php`, `user_delban.php`; `phpstan-new.neon`.

## Access

`user.php` runs `check_su_access(SU_EDIT_USERS)` before any op, and `saveban` and
`delban` require POST and CSRF before they are dispatched. The test covers every ban op
for an anonymous client, a player, a superuser holding every flag except Edit Users and
megauser (403), and Edit Users and megauser (200 for each GET; 403 for a POST without
CSRF). No ban row changes in any of those requests.

## Findings and changes

| # | Op | Found at `aeb19b9` | 1.1.2 | Now |
|---|---|---|---|---|
| 0 | setupban | "Add a ban" (no account) was a fatal page: `$row['name']` on a missing row throws | Read the missing row as empty (a silenced notice) | Empty form, as 1.1.2 showed |
| 1 | setupban | Account lookup interpolated `$userid`; same-ID list used `addslashes` | Same, unvalidated | Bound |
| 2 | setupban | Similar-IP `LIKE '$thisip%'` interpolated the stored IP: `%` or `_` was a wildcard, and a quote made the query fail (fatal page). The IP is server-set; the test plants one | Same | `LIKE ? ESCAPE '|'`, literal prefix; the same prefix walk (stops after two dots) and the same `NOT LIKE` previous-prefix exclusion |
| 3 | setupban | `reason` from GET printed raw into `value="..."` (reflected XSS) | Same | `htmlspecialchars(ENT_QUOTES)` |
| 4 | setupban | "Use this filter" put the prefix raw into `value='...'` in an inline handler | Same | JSON string literal (hex-escaped quotes, tags, ampersands) inside an escaped attribute |
| 5 | saveban | POST + CSRF + bound, but no one-use intent: a replay inserted a second row | No CSRF, raw SQL | The setupban form carries a one-use intent; validation precedes it; a replay is 409 and writes nothing |
| 6 | saveban | A negative or non-integer duration threw an uncaught exception (500); over 36500 days was 400 | `(int)`: negative stored an already-expired ban; non-numeric was 0, permanent; huge overflowed `strtotime` | Negative, non-integer and over 36500 are 400 with nothing written. An empty field is 0 (permanent), as 1.1.2's cast and its permanent-ban confirm treated it |
| 7 | saveban | Self-ban checks matched 1.1.2 (request IP as a prefix, `lgi` cookie), but the texts were new | "You don't really want to ban yourself now do you??" + "That's your own IP address!" / "That's your own ID!" | 1.1.2's checks and texts |
| 8 | saveban | Output "Ban entered."; no debug log | "%s ban rows entered." and a debug log line; no session effect | 1.1.2's text and debug log line (permanent logs 1.1.2's `0000-00-00`), written in one transaction with the row |
| 8a | saveban | IP and ID allowed up to 40 bytes; the columns are 15 and 32, so 16-40 bytes failed in strict SQL (500) | Truncated silently by non-strict MySQL | Over 15 (IP) or 32 (ID) is 400. An empty ID is 400 (1.1.2 stored a row that can never match); an empty IP gets 1.1.2's self-ban text, because every address starts with it |
| 9 | removeban | Stored XSS: each row's `ipfilter` and `uniqueid` went raw into the `href` and the inline `onClick` call | Same | `rawurlencode` in the URL, the URL escaped for the attribute; the handler passes the link's own `href` |
| 10 | removeban | `banner` and `banreason` through `output_notl` | Same | Confirmed: `appoencode` escapes HTML; colour codes still apply, as in 1.1.2 |
| 11 | xml | `ip` and `id` through `addslashes`; an array was a 500 | Raw SQL | Bound; a non-string is 400; the NONE text is XML-escaped; names stay `urlencode(appoencode(...))` |
| 12 | removeban | `duration` from GET went into `strtotime` and the heading | Same | Only the listed durations and `forever`; anything else shows the default two-week list |
| 13 | removeban | A permanent ban (NULL) showed about 2.9 million days | Compared a zero datetime with `"0000-00-00"`, never matched, and showed a large negative count | "Never" (display only). The default and duration lists still leave permanent bans out and `forever` shows them, as 1.1.2 did |
| 13a | removeban | Expired, not yet purged bans were listed | Deleted expired bans on every list view (a GET write) | Left out of the list; the New Day purge still deletes them. The list shows what 1.1.2 showed |
| 14 | removeban | "Click to show users" used `xmldom.async=false; xmldom.load()`. Chromium 141: `TypeError: xmldom.load is not a function`; nothing shown | Worked in 2006 browsers | One `XMLHttpRequest` of the link's registered URL, same parsing and output |
| 15 | delban | POST, CSRF, role, bound; a non-string field was a 500 | GET, raw SQL | Confirmed; a non-string is 400. It deletes every row with the shown `(ipfilter, uniqueid)` pair, as 1.1.2's DELETE did |

## Stored XSS (item 9)

`common.php` takes any `lgi` cookie of 32 or more bytes as the player's `uniqueid` and
saves it (PHP URL-decodes the cookie first). `moderate.php`'s comment ban, and an admin's
ID ban, store that value in `bans.uniqueid`. The ban list then printed it raw:

```
<a href='user.php?op=removeban&subop=xml&ip=&id=AAAAAAAAA'><svg/onload=alert(1)>' ...
   onClick="getUserInfo('','AAAAAAAAA'><svg/onload=alert(1)>',0); return false;">
```

The test proves the chain on a real request: a logged-in player sends
`lgi=AAAAAAAAA'><svg/onload=alert(1)>` (32 bytes, URL-encoded) and their stored
`uniqueid` becomes that string. A ban on it, in `moderate.php`'s row shape and through
`saveban`, then appeared in the list as markup. Now the value appears only URL-encoded in
the link, HTML-escaped in the hidden `delban` fields and text cells, and the handler
takes no row data.

The cookie itself is outside this task (see Remaining gaps).

## Durations (item 6)

| Posted `duration` | 1.1.2 | `aeb19b9` | Now |
|---|---|---|---|
| `14` | Expires in 14 days | Same | Same |
| `0` or empty | Permanent (`0000-00-00`) | Permanent (NULL); empty was a 500 | Permanent (NULL) |
| `-1` | Row that expired yesterday, never enforced | 500, nothing written | 400, nothing written |
| `abc` | `(int)` 0: permanent | 500 | 400 |
| `36501`, `99999999999` | `strtotime` overflow: a meaningless date | 400 | 400 |

The negative case is a deliberate correction. 1.1.2 stored a row that never blocked
anyone and was deleted by the next New Day purge.

## Confirmed without change

- `moderate.php`'s comment ban writes `(uniqueid, banexpire, banreason, banner)` with
  `ipfilter` left at its `''` default: the same row shape as an ID ban from `saveban`
  (its `banexpire` is a datetime three days out; `saveban` stores midnight).
- `lib/newday/newday_runonce.php:36` deletes bans with `banexpire < today`, keeping
  permanent (NULL) bans.
- `lib/checkban.php` (read only): at login, the account's stored `lastip` (prefix) and
  `uniqueid`, then the request's IP and `lgi` cookie, are matched against active bans
  (`banexpire IS NULL OR banexpire >= today`). Through real logins in the test, an
  IP-prefix ban and a permanent ID ban stored by `saveban`, and a timed ID ban in
  `moderate.php`'s shape, each refuse the login; an expired ban does not. A stored `%` or
  `_` is literal here too (`substring` comparison, not `LIKE`). Superusers and
  `banoverride` accounts are exempt, as in 1.1.2.

## Finding not fixed: the ban message page fails (`lib/checkban.php`)

When a ban matches, `checkban()` resets `$session` to an empty array and then calls
`translate_inline()`. `tlbutton_push()` (`lib/translator.php:189`) reads
`$session['user']['superuser']`, which no longer exists. PHP 5 silenced that notice; here
the error handler turns it into a fatal error. The login is still refused and nothing is
written, but the banned player gets a 500 instead of 1.1.2's ban message, and the ban's
`lasthit` is not updated. `create.php` calls `checkban()` the same way. The brief made
`checkban` a read-only check, so this is recorded for its own task. The test asserts only
the refusal (no redirect to the village, not logged in afterwards).

## 1.1.2 comparison

| Behavior | 1.1.2 | `aeb19b9` | Now |
|---|---|---|---|
| Access | `SU_EDIT_USERS` | Same | Same |
| Add a ban without an account | Empty form | Fatal | Empty form |
| SQL | Raw | Mostly bound; setupban and xml raw or `addslashes` | All bound |
| Similar IPs | Prefix walk, wildcards live | Same | Same walk, literal prefix |
| Reflected `reason`, filter link | Raw | Raw | Escaped for attribute and JS string |
| saveban method | GET or POST, no CSRF | POST + CSRF | POST + CSRF + one-use intent |
| saveban replay | Duplicate row | Duplicate row | 409, no write |
| Duration | `(int)`, any value | Integer ≥ 0, ≤ 36500; else 500 or 400 | Integer 0..36500 (empty = 0); else 400 |
| Self-ban rule and text | IP prefix / cookie; 1.1.2 text | Same rule; new text | Same rule; 1.1.2 text |
| Saved text, debug log | "n ban rows entered.", logged | "Ban entered.", not logged | 1.1.2 text, logged, one transaction |
| Default reason | "Don't mess with me." | Same | Same |
| List cells and links | Raw | Hidden fields escaped; link and handler raw | All escaped for context |
| List durations | Any `strtotime` text | Same | Listed durations and `forever` |
| Permanent display | Large negative day count | ~2.9 million days | Never |
| Expired bans | Purged on view | Listed until the New Day purge | Not listed; New Day purge |
| Show users | Synchronous XML load | Broken in current browsers | One XMLHttpRequest, same output |
| delban | GET, raw | POST + CSRF + bound | Same; non-string 400 |
| Enforcement | IP prefix / ID, permanent or unexpired | Same | Unchanged (message page finding above) |

## Tests

`test_user_editor_ban_authority_http` (one HTTP test):

- the role matrix on all six ban requests, with no ban row written
- the planted `lgi` cookie becoming the stored ID
- setupban: the reflected `reason` round-trips as text; a stored `%_` IP lists the
  literal match and not the wildcard decoy; the quote stays inside the script string;
  "Add a ban" without an account
- saveban: CSRF, a missing intent, every refused input with nothing written, the saved
  row, 1.1.2's text and debug log, replay, both self-ban texts, a permanent ban
- removeban: the planted ID inert in every context, Never, expired bans left out, the
  default, listed and refused durations, each "show users" link passing the navigation
  check (twice for the first) and returning XML
- xml: same-ID and literal IP-prefix matching, an injection attempt, an array input
- delban: CSRF, an array input, the pair deleted
- enforcement through real logins for the IP, comment, permanent and expired bans

Failing before, passing after: each fix was reverted on its own to the `aeb19b9` code for it, and the test was run
locally (PHP 8.5.11 / MySQL 8.4) with the rest of the change in place. Every revert fails:

| Reverted | First failing assertion |
|---|---|
| "Add a ban" row lookup | Role matrix, Edit Users GET `user.php?op=setupban`: fatal (`Trying to access array offset on false`, `user_setupban.php:5`) |
| `reason` escaping | The parsed form's `reason` is not `"><script>reason</script>` (the markup broke out of the attribute) |
| Literal LIKE prefix | setupban for the planted IP: fatal SQL error from the stored quote |
| Filter JS string | `value='10.%_'` found in the page |
| One-use intent | Missing `action_token`: 200 (a row written) instead of 409 |
| Duration handling | `duration=-1`: fatal (uncaught `InvalidArgumentException`) instead of 400 |
| Column widths | 16-byte IP: 500 instead of 400 |
| Self-ban texts | "You don't really want to ban yourself now do you??" not found |
| Debug log line | The `entered a ban:` line is missing |
| List link and handler | `<svg/onload=alert(1)>` found in the ban list |
| Permanent display | `2912154 days` found |
| Listed durations | `-1000 years` did not fall back to the two-week list |
| Expired filter | `Expired reason` found in the list |
| XML input (whole `aeb19b9` block) | Matching passes; `ip[]=1`: fatal instead of 400 |
| delban input | `uniqueid[]`: fatal instead of 400 |

With every fix in place the test passes. On `aeb19b9` as a whole it fails at its first
check, the "Add a ban" fatal. The "Click to show users" script was checked in Chromium
141 (Playwright, a static page with each version and the same XML): the `aeb19b9`
script throws `TypeError: xmldom.load is not a function` and shows nothing; the new one
shows the names.

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- Shard 4, which holds the new test, through `scripts/tooling-shards.py` on a fresh
  database after PHPUnit: 38 tests OK (890 s; the ban test 19 s). PHPUnit 124/124.
- Each reverted fix, as above, and the Chromium check of the list script.
- PHP lint 364 files, 0 failures. Both PHPStan configs: no errors (`lib/ban_admin.php`
  added to the level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier and project validator pass; `git diff --check` clean.
- Serialized state: no serialize or unserialize site changed; the inventory is not
  recounted.

## Remaining gaps

- The ban message page fails (finding above).
- `common.php` accepts any `lgi` cookie of 32 or more bytes as the player's `uniqueid`.
  Over 32 bytes, the account's save fails in strict SQL. The ban pages now treat the
  value as data; the cookie format itself is not validated.
- `home.php` prints the session message with `output_notl(..., true)`, so a ban reason
  is shown to the banned visitor as raw HTML. Reasons come from Edit Users admins and
  `moderate.php`'s moderators. This is 1.1.2 behavior and was not changed here.
- `moderate.php` inserts a comment ban without 1.1.2's check for a longer existing ban.
  Only confirmed here.
- The setupban form's intent is per editor session: with two ban forms open, the second
  save gets 409 and the form must be reloaded.
