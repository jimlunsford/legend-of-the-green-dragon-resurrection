# Soul restoration and the new-account preferences fatal, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `c166e2eb91f421b706e051ffd45d8c86c7e61014`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Recorded with this candidate:

- Haunting accepted. `2a48f35`: Modern core 38884592810 SUCCESS (all 12 jobs), Baseline
  38884592513 SUCCESS. See [haunting](HAUNT-AUTHORITY-20261016.md).
- Bio moderation and donation administration (`c166e2e`): Modern core 38892084451 was
  still running when inspected, so both stay in Remaining.

This candidate covers two items:

- Soul restoration, `graveyard.php?op=restore`. Route-local helpers are in
  `lib/restore_authority.php`, and the page is `lib/graveyard/case_restore.php`. That page
  was byte-identical to 1.1.2 before this change.
- The new-account preferences fatal. `prefs.php` is unchanged; the fix is one boolean
  case in `lib/showform.php`.

Not opened: the rest of `prefs.php` (email, password, suicide, skin and module
preferences) and `paylog.php`.

## A. Soul restoration

### Findings, checked against current source and the historical tag

| Finding | Result |
|---|---|
| GET mutation | Confirmed. `case_restore.php` charged favor and filled `soulpoints` on any GET, with no CSRF and no replay protection. Against `c166e2e`, the new test's first GET was the restoration itself: no form, the favor taken |
| Cost | `round(10 * (max - soulpoints) / max)` with `max = level * 5 + 50`, identical in 1.1.2 and in `case_enter.php`'s "Restore Your Soul (%s favor)" link. Unchanged |
| Zero cost | Yes. The cost rounds to 0 when the deficit is under a twentieth of `max` (`10d/max < 0.5`). At level 1 (`max` 55) a deficit of 1 or 2 costs 0 and 3 costs 1. At level 15 (`max` 125) up to 6 costs 0. 1.1.2 granted these for free, since any favor is `>= 0`. This is historical and kept; the test restores level 1 at 53/55 for 0 favor |
| Dead player in the graveyard | `graveyard.php` sends the living to the village before dispatching `op=restore`, except while a special event holds the page, as accepted for haunting. A request the page did not issue goes to badnav (allowed navigation) |
| Texts and navs | Granted, cursed out and already full, and the three navs: unchanged |

### Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Restore | GET `op=restore` charged and restored at once, replayably | GET shows a "Restore Your Soul (*n* favor)" form, the 1.1.2 Mausoleum link's own text. The restoration is its POST | GET mutation |
| Protection | None | CSRF plus a one-use intent (`graveyard-restore`) bound to the player's ID, favor, soulpoints, level and life | Replay, stale forms |
| Write | Favor and soul set in the session and saved at page end | Favor and soul change together in the locked player transaction. Life, fullness and favor are rechecked there; a living actor is refused (409) | Atomic |
| Already full | The sigh, no charge | Same, with no form | Historical |
| Too little favor | The curse, no charge | Same, with no form. A form issued with more favor is stale (409) | Historical |
| Cost, including 0 | `round(10 * deficit / max)` | Unchanged | Historical |

## B. The new-account preferences fatal

### Reproduction

A brand-new account (`lib/accounts.php` stores `prefs` as `serialize([])`) opens
`prefs.php` after its first village page. Against `c166e2e`, the extended account test
failed there:

```
Fatal error: Uncaught ErrorException: Runtime error in showform.php:304
```

The POST to `prefs.php?op=save` renders the same form after saving, so it fails the same
way.

### Root cause

- `showform()`'s `bool` case compares `$row[$key]==0` and `$row[$key]==1` without
  checking that the key exists.
- `prefs.php` defaults `tabconfig` and `timeformat`, but not the booleans
  `emailonmail`, `systemmail`, `dirtyemail`, `ihavenocheer` and `nojump`. The first
  missing one, `emailonmail`, fails.
- In 1.1.2 the undefined index was an `E_NOTICE`, which 1.1.2's `errorhandling.php`
  excluded (`error_reporting(E_ALL ^ E_NOTICE)`). The value read as `null`, `null == 0`
  selected "No", and saving posted 0.
- PHP 8 raises `E_WARNING` ("Undefined array key"), and the modern error handler turns
  every reported error into an `ErrorException`.

### Fix

The `bool` case reads `$row[$key] ?? null`, which is the value 1.1.2 used. A missing
boolean shows "No" and saves as whatever the browser posts, 0 by default. Accounts with
stored values render exactly as before. Preference names, the form and saved values are
unchanged.

### Other readers of the missing keys

| Reader | Missing key | Status |
|---|---|---|
| `lib/holiday_texts.php` | `ihavenocheer` | Guarded (defaults to 0) |
| `lib/commentary.php` | `timestamp`, `timeoffset`, `timeformat` | Guarded |
| `lib/showform.php` tabs | `tabconfig` | Guarded |
| `lib/translator.php` | `language` | Guarded |
| `lib/systemmail.php` | `emailonmail`, `systemmail` | Guarded |
| `prefs.php` | `timeformat` | Defaulted before use |
| `untranslated.php` (translators only) | `language` | **Unguarded**: a translator with no stored language fails the same way. Different fix (a language default), so not changed; listed as a gap |

Other `showform()` field types also read `$row[$key]` unguarded: `theme`, `location`,
`checklist`, `radio`, `dayrange`, `range`, `floatrange`, `datelength` and `float`.
`prefs.php` never reaches them with a missing key (`template` is always set). They are
listed as gaps for other editors, not changed.

## Tests

`test_graveyard_restore_authority_http`, at level 10 (`max` 100) and 40 soulpoints
(cost 6):

- Anonymous requests are refused.
- GET shows the form and changes nothing.
- Missing or wrong CSRF is 403, and an extra field 400.
- A request this page did not issue goes to badnav with no change.
- A form issued at other favor is 409.
- A living actor is redirected on GET and on POST, with no change.
- Rollback: a CHECK-constraint failure on the player's write is 500 with favor and soul
  unchanged, and the spent intent is 409.
- A fresh form restores: 10 → 4 favor and 40 → 100 soul with the historical text.
  Replay is 409.
- Already full shows the sigh with no form.
- Too little favor shows the curse with no form, and an older form is 409.
- Level 1 at 53/55 restores for 0 favor.

`test_create_login_rotate_render_logout`, extended: the new account has no stored
`emailonmail`. `prefs.php` renders, with "No" selected for `emailonmail`, `systemmail`,
`dirtyemail` and `nojump`. Saving the form as a browser posts it stores 0 for each, and
the village renders again.

Both fail against `c166e2e`: the restore test at its first GET (no form; the GET
restored), and the account test at `prefs.php` (the fatal above). Both pass with the
change. Local runs are supporting evidence only; acceptance comes from CI on both
supported legs.

## Remaining gaps

- `untranslated.php` reads `prefs['language']` unguarded (translators only).
- The other `showform()` field types listed above read missing keys unguarded.
- Restoring stays reachable while a special event holds the graveyard page, as accepted
  for haunting. The locked recheck still refuses a living actor.

## Candidate CI, 2026-10-16

`6a9a12b56424ba7a6dd0dfbee2004b91ea4b074b` was inspected once at the start of the user
editor task. Baseline integrity 38896842463 SUCCESS. Modern core 38896842544 FAILURE:

- Both `checks` jobs SUCCESS.
- All eight `http` shards FAILURE, and both `result` jobs FAILURE (12:07:46Z and
  12:09:23Z).
- The shards failed on different tests. Shard 1:
  `test_lovers_alternative_choices_daily_authority_http`, `inn.php` 302 instead of 200
  (`tests/tooling/test_web_application.py:677`). Shard 2:
  `test_graveyard_flee_success_failure_caps_and_rollback`, "Graveyard combat
  unavailable" 409 (`:1463`). Shard 3: a training test, "Training unavailable".
- The run started at 11:54Z. Both shard 1 jobs ended at 12:00:04Z and 12:00:05Z, and
  every failure read happened at or after 12:00:00Z.

Inferred, not proven: with the default `daysperday` of 4 the game day rolls over every
six hours, including 12:00Z, and a fixture's game day went stale mid-run. Locally, shard 1
passed 47/47 at `6a9a12b` away from a rollover. The run stays on record and is not
acceptance. Soul restoration and the `prefs.php` fix stay in Remaining.
