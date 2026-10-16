# Ban message fatal and player ID cookie, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `eec24a6bf531ac1e70b1c51f7e62a39cddbca721`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Scope: the two ROADMAP items found during bans
([ban administration](BAN-ADMIN-AUTHORITY-20261016.md)): the ban message page
(`lib/checkban.php`, `lib/translator.php`) and the `lgi` player ID cookie (`common.php`).
Nothing else in `common.php` or the translator changed.

Files: `lib/translator.php`, `lib/web_security.php` (new helper
`resurrection_player_id()`), `common.php` (the `lgi` block only).
`lib/checkban.php` is unchanged.

## A. Ban message page

### Reproduction

At `eec24a6`, with a matching ban, both callers stop with the same uncaught
`ErrorException` (the application's error handler turns the warning into one):

| Caller | Stack | Error |
|---|---|---|
| `login.php:45` `checkban($account['login'])` | `checkban.php:30` `translate_inline` → `translator.php:120` `translate` → `translator.php:73` `tlbutton_push` | `Undefined array key "user"` at `lib/translator.php:189` |
| `create.php:11` `checkban()` (GET and POST `op=create`, IP ban and ID ban) | same | same, `lib/translator.php:189` |

The visitor sees an error page instead of the message, and the login is not completed.
No account is created at `create.php`, because the fatal comes before it. Locally the
PHP built-in server, with Xdebug's error display, answered 200 with the error page.

### Root cause

`checkban()` empties the session (`$session=array()`, as 1.1.2 did) and then translates
the message. `translate()` and `translate_inline()` call the translator-button helpers,
which read `$session['user']['superuser']` unguarded (`tlbutton_push`, `tlbutton_pop`,
`tlbutton_clear`; `translator.php:189`, `215`, `224` before the change). Under PHP 5 the
missing key was a silent notice, so the value was null and the user was not a translator.
Under PHP 8 it is a warning, which the error handler makes fatal. The 1.1.2 source has the
same three reads (`translator.php:186`, `212`, `221`) and the same `checkban()` sequence.

The root cause is in the translator, not in `checkban()`. Each of the three reads is now
`($session['user']['superuser'] ?? 0) & SU_IS_TRANSLATOR`: a missing user is not a
translator, as under PHP 5. A translator with a loaded user is unchanged.

### Other callers that empty or replace the session

| Site | Translates after the reset? | Result |
|---|---|---|
| `lib/checkban.php:28` (login and create) | Yes: the ban message | Fixed by the translator change |
| `badnav.php:47`, the not-logged-in branch: `$session=array(); translator_setup(); redirect("index.php");` | Yes: `redirect()` translates "Click here." and the petition line | Same root cause, covered by the same change. The branch is unreachable: `badnav.php` is not anonymous, so `do_forced_nav()` sends a visitor without a session login to `index.php?op=timeout` first, and a session login whose account is not logged in ends the session there. If it were reached, `saveuser()` would next read `$session['loggedin']` unguarded, a different cause, not changed here. |
| `resurrection_end_session()` callers: logout (`login.php:28`), session timeout (`common.php:194`), forced navigation (`lib/forcednavigation.php:45`, `50`), character delete (`prefs.php:62`), inn room (`lib/inn/inn_room.php:41`) | No: each sends `Location` and exits | Not affected |
| `do_forced_nav()` anonymous branch (`translate_inline` before `redirect`) and `home.php`'s timeout and cookie messages | Yes, but the session is not emptied: `common.php` fills `$session['user']` with the account defaults (`superuser` 0) | Not affected |

### Exemptions

Unchanged from 1.1.2: with a login, an account with `banoverride`, or any superuser flag
outside `SU_DOESNT_GIVE_GROTTO`, sets `$session['banoverride']` and skips both checks.
The test covers Edit Users (64) and `banoverride` logging in under a matching IP prefix
and permanent ID ban, and `SU_DEBUG_OUTPUT` (8192, inside `SU_DOESNT_GIVE_GROTTO`) being
refused.

## B. Player ID cookie

### Generation

| | Value |
|---|---|
| 1.1.2 | `md5(microtime())`: 32 lowercase hex characters |
| This branch | `bin2hex(random_bytes(16))`: 32 lowercase hex characters |
| Columns | `accounts.uniqueid` and `bans.uniqueid`, both `varchar(32)` |

### Finding

1.1.2 and `eec24a6` both kept any `lgi` cookie of 32 or more bytes as the player's
`uniqueid`. The value is client-chosen: markup, quotes or wildcards reached the account,
`moderate.php`'s comment ban and the ban pages (escaped since bans), and a value over 32
bytes does not fit the column, so the account's save fails under strict SQL.

### Change

`resurrection_player_id($cookie, $stored)` (`lib/web_security.php`) accepts a cookie only
when it is exactly 32 hex characters. Anything else, including an array, is no cookie:
the stored `uniqueid` is used when it is itself 32 hex characters, otherwise a new ID is
issued. In either of those cases the cookie is sent. `common.php` stores the result and
sets `$_COOKIE['lgi']` to it, so the rest of the request (`checkban()` without a login,
`saveban`'s self-ban check) sees only that value. 1.1.2's three paths are kept: a valid
cookie is the ID; a valid stored ID is sent back; otherwise a new one.

### Stored values outside the format

Decision: replace them on the player's next request without a valid cookie. No migration.

Evidence: neither generator produces anything else, so such a value can only come from a
client-chosen cookie. Keeping it would mean sending it back as a cookie, which echoes
the client's markup, and over 32 bytes it cannot be saved. A value that a ban was placed
on keeps matching that account at login, because `checkban($login)` reads the stored row
before the login loads the account; the value is replaced only after a successful login.
It no longer matches as a cookie, which is the same as clearing the cookie, an evasion
that 1.1.2 ID bans always allowed.

ID bans on valid IDs, including bans placed before this change, match exactly as before.

### Cookie attributes

1.1.2 set the cookie with an expiry only. No script reads it: the only readers are
`common.php`, `lib/checkban.php`, `lib/ban_admin.php` and `home.php` (searched in PHP,
JavaScript and template files). It now has the session cookie's attributes: HttpOnly,
SameSite=Lax, and Secure on HTTPS. Its expiry (365 days) and default path are 1.1.2's.

## 1.1.2 comparison

| Behavior | 1.1.2 | `eec24a6` | Now |
|---|---|---|---|
| Ban message at login or account creation | Every matching reason, permanent or expiry text, petition line | Fatal at `translator.php:189` | 1.1.2's message |
| Login under a matching ban | Refused | Refused (fatal) | Refused, sent to the message |
| Account creation under a matching ban | Nothing created | Nothing created (fatal) | Nothing created, sent to the message |
| `lasthit` on a matching ban | Only bans with an empty `uniqueid` (`$row['uniqueidid']` typo) | Fatal before the update | Every matching ban (bound update, kept from earlier work) |
| Permanent ban | `banexpire='0000-00-00'` | `NULL` | `NULL` (unchanged) |
| `banoverride`, superuser exemption | Skips both checks | Same | Same |
| Translator buttons for a translator | Shown | Shown | Shown |
| `lgi` cookie accepted | Any 32 or more bytes | Any 32 or more bytes | Exactly 32 hex characters |
| Short or invalid cookie, valid stored ID | Stored ID sent back | Same | Same |
| Short cookie, stored ID 32+ bytes | Stored value sent back | Same | Replaced by a new ID |
| Cookie attributes | Expiry only | Expiry only | HttpOnly, SameSite=Lax, Secure on HTTPS |
| Ban reason on the message | Raw HTML | (fatal) | Raw HTML (unchanged, gap) |

## Tests

- `tests/unit/TranslatorTest.php` (new): the three button helpers with an emptied
  session, an empty user and a non-translator return false or empty; a translator still
  gets the button. Fails at `eec24a6` with `Undefined array key` warnings at
  `translator.php:189`, `215`, `224`.
- `tests/unit/WebSecurityTest.php`: `testPlayerIdCookieKeepsOnlyTheGeneratedFormat`. A
  valid cookie is kept and not resent; empty, 31, 33 and 64 characters, non-hex, markup,
  leading space, trailing newline, an array and a number fall back to a valid stored ID,
  else a new 32-hex ID. Fails at `eec24a6`: the helper does not exist.
- `test_user_editor_ban_authority_http` (extended, shard 4):
  - Cookie: a markup, 33-character, non-hex, 31-character and oversized markup cookie
    leave the stored ID unchanged and the response sends it back HttpOnly and
    SameSite=Lax; a stored invalid ID is replaced by a new one; a valid cookie replaces
    the stored ID. The planted ID is now written to the database directly.
  - Login: an IP prefix ban (14 days) and a permanent ban on the planted ID together
    show both reasons, the expiry date and the permanent text, the opening line and the
    petition line; non-matching decoys are not listed. Every matching ban's `lasthit`
    is set and the decoys' are not. The visitor is not logged in afterwards.
  - Login: a comment ban (3 days) on a valid stored ID shows its reason and date.
  - Exemptions as above; an expired ban does not refuse the login.
  - Account creation: an IP prefix ban, and an ID ban on a 1.1.2-format (md5) ID sent as
    the cookie, refuse GET and POST and create nothing; without a ban the same POST
    creates the account (removed afterwards with the fixture).

Before and after, local (PHP 8.5.11 / MySQL 8.4):

| Variant | PHPUnit | HTTP test |
|---|---|---|
| `eec24a6` with the new tests | Both new tests fail | Fails at the first invalid cookie: the markup value is stored |
| Cookie fix only | Translator test fails | Fails at the first ban message: login answers the error page (200 locally), not 302 to `index.php` |
| Translator change reverted alone | Translator test fails | As above |
| Cookie validation reverted alone | Pass | Fails: the markup cookie is stored |
| Cookie attributes reverted alone | Pass | Fails: no HttpOnly |
| Invalid stored IDs kept (`strlen >= 32`) | Player ID test fails | Fails: the planted stored ID is kept |
| All changes | Pass | Pass |

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- All four shards through `scripts/tooling-shards.py`, each on a fresh database after
  PHPUnit (the translator is shared): shard 1 48 OK (816 s), shard 2 36 OK (825 s),
  shard 3 37 OK (821 s), shard 4 38 OK (843 s; the ban test 24 s), with the shard
  partition tests.
- PHPUnit 126/126 before each shard (two new tests).
- Each reverted part, as above.
- PHP lint 366 files, 0 failures. Both PHPStan configs: no errors (`lib/translator.php`
  and `lib/web_security.php` are in the level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier and project validator pass; `git diff --check` clean.
- Serialized state: no serialize or unserialize site changed; the inventory is not
  recounted.

## Remaining gaps

- `home.php` prints the session message with `output_notl(..., true)`, so a ban reason
  reaches the banned visitor as raw HTML. Reasons come from Edit Users admins and
  `moderate.php`'s moderators. 1.1.2 behavior, unchanged.
- `badnav.php`'s not-logged-in branch would fail in `saveuser()` if it were reached
  (above). It is unreachable; not changed.
- ID bans remain evadable by clearing the cookie, as in 1.1.2; an invalid cookie now has
  the same effect.
- `moderate.php` inserts a comment ban without 1.1.2's check for a longer existing ban
  (recorded during bans).
