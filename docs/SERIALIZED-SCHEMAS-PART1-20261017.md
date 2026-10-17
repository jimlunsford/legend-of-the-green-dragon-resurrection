# Serialized business schemas, part 1: inventory, definition of done, first closures

Date: 2026-10-17. Starting head: `05485ec`. Scope: the `ROADMAP.md` item "Remaining serialized
business schemas" (`SERIALIZED-STATE-AUDIT.md`). This part turns it into a checkable list,
closes navigation, preferences and editor old values, and plans the rest.

## Definition of done

A serialized category is CLOSED when:

1. Every read is followed, before use or write, by an exact shape check: keys, types, ranges
   and required fields, as the consumers need.
2. A failing value stops the request with a clear response and writes nothing.
3. Every writer stores only values that pass the same check.
4. Valid values written by 1.1.2 still pass.
5. A test covers a valid value, each malformed shape, and the no-write outcome.

A site whose value is never stored (a hash input, an in-request key, a bound comparison) is
CLOSED as "not stored". A site that is not business state (a cache file, an admin dump display,
a helper with no callers, a value that is read and never used) is marked n/a.

Object restoration was already closed: every read goes through `ScalarState::read()`, which
restores no objects. This part is about the business shape of the values.

## Inventory

The token method (PHP tokenizer over tracked shipped PHP, excluding `tests/`, `scripts/` and
`lab/`; counting `serialize(`, `ScalarState::read(` and `unserialize(` calls) reproduces
**112/53/42/69/1 at `4478c06`** and **108/50/40/67/1 at `8257aed`**. At **`05485ec`** it gives
**107 sites in 50 files: 40 serialize writers/checks, 66 ScalarState reads, 1 centralized
unserialize**. The per-site table is in `SERIALIZED-STATE-AUDIT.md`, section "Business-schema
inventory, 2026-10-17".

After this commit (working tree recount, same method, new files included): **103 sites in 50
files: 40 / 62 / 1**. Removed: four reads in `lib/forcednavigation.php` and `login.php` that
moved into the schemas, the reads in `lib/systemmail.php:37` and `lib/translator.php:137`, and
the `oldvalues` serialize in `prefs.php`. Added: one read in `src/Security/NavigationState.php`,
one read and one serialize in `src/Security/PreferenceState.php`.

## Category table

Status of the 107 sites at `05485ec`, after this commit.

| Category | Sites | CLOSED | OPEN | n/a |
|---|---|---|---|---|
| Navigation (`allowednavs`, plus `restorepage`) | 5 | 5 | 0 | 0 |
| Preferences (`prefs`) | 4 | 4 | 0 | 0 |
| Editor old values | 1 | 1 | 0 | 0 |
| General combat (`badguy`) | 16 | 9 | 7 | 0 |
| Buffs (`bufflist`) | 12 | 3 | 9 | 0 |
| Companions (`companions`, `companions.abilities`) | 11 | 4 | 7 | 0 |
| Mounts (`mounts.mountbuff`) | 7 | 4 | 3 | 0 |
| Translation arrays: news arguments | 3 | 0 | 3 | 0 |
| Translation arrays: mail | 18 | 8 | 10 | 0 |
| Translation arrays: navigation labels | 3 | 3 | 0 | 0 |
| Other | 27 | 8 | 11 | 8 |
| **Total** | **107** | **49** | **50** | **8** |

The ten OPEN mail sites are all in `lib/mail.php`, which no tracked file includes; the live
mail readers use `MailContent::stored()`. `restorepage` is a plain string column, not a
serialized site, so it adds no row; it is closed with navigation. `login.php:54` read both
`prefs` and `dragonpoints`; it is counted under Other (dragon points), and its `prefs` half is
closed here.

The classification of the combat, buff, companion, mount, translation and other sites comes
from reading the code at `05485ec`; the claims that matter for the next tasks are marked below
as verified or as read from the code.

## Closures in this commit

### Navigation

`src/Security/NavigationState.php`:

- `read()`: the stored `allowednavs` column. Empty or NULL means no allowed links: the user
  editor's Fix Navs writes `''`, as 1.1.2 did, and the next page then shows badnav.php's
  corrupted-navs page, as in 1.1.2. 1.1.2 wrapped a stored value that was not an array as
  `array(0 => <text>)` and an override page (mail, MOTD, petition) could save that entry back
  beside new links; badnav.php drops it, so a legacy entry `0 => string` is accepted and
  dropped. Anything else that is not a map of string link to boolean throws.
- `map()`: the in-memory map `addnav()` builds: string links without control characters (an
  empty link is possible and badnav.php drops it), boolean values.
- `restorePage()` / `validRestorePage()`: `restorepage` is empty, or a script name ending in
  `.php` with an optional query of printable ASCII without `"`, `'`, `<`, `>`, `\` or backtick,
  within the 128-character column. It is written raw into the upgrade notice's meta refresh
  (`common.php`) and the installer's Continue link.

Reads: `lib/forcednavigation.php` (every page) and `login.php` (before the login is recorded)
check both columns and answer 409 "Invalid stored navigation state." with nothing written.
`login.php` no longer reads `allowednavs` as an unchecked value.

Writers: `saveuser()` stores only `NavigationState::map()` output and checks `restorepage`;
`resurrection_player_mutation()` checks `restorepage`. `common.php` and `checkday()` record the
request as `restorepage` only when it is a valid page; otherwise the previous page is kept.
Before, a request longer than 128 characters made `saveuser()` fail under strict SQL mode (500).
The constant writers (`lib/accounts.php` new account, `lib/ramius_outcomes.php`,
`lib/inn/inn_room.php`, the user editor's Fix Navs) already write valid values.

The upgrade notice also HTML-escapes `restorepage` in its meta refresh.

Found while testing: `badnav.php` read `$session['output']`, which `page_footer()` always
clears, so every visit to badnav.php was a 500 ("Runtime error in badnav.php:13") on PHP 8.
1.1.2 read the missing value as `""`. It now reads `?? ''`, and the corrupted-navs page and the
stored-output page work again.

### Preferences

`src/Security/PreferenceState.php`:

- `read()`: empty or NULL is an account that never saved preferences; otherwise the value must
  decode to a map.
- `map()`: every key is a field name of 1 to 255 bytes without spaces or control characters
  (PHP turns spaces and dots in a posted name into underscores); every value is a string, int,
  finite float or bool. Arrays, NULL and integer keys are refused. The fields the game reads
  have the types their readers need:
  - `tabconfig`, `emailonmail`, `systemmail`, `dirtyemail`, `ihavenocheer`, `nojump`:
    `0`, `1`, `"0"`, `"1"`, `true` or `false` (showform's bool select posts `"0"`/`"1"`;
    `lib/holiday_texts.php` stores the int 0);
  - `timestamp`: 0, 1 or 2, as int or string;
  - `timeoffset`: numeric (int, finite float or numeric string) within ±1,000,000 hours;
  - `timeformat`: a string;
  - `language`: up to 32 of `A-Z a-z 0-9 _ -` or empty (`untranslated.php` puts it in SQL).
- A blank `timeoffset` (1.1.2 stored a cleared int field as `""`) reads as 0. PHP 8 throws a
  TypeError on `"" * 3600`, which prefs.php computes, so such an account got a 500 on the
  preferences page before.
- `encode()`: a valid map, serialized, within the 65,535-byte TEXT column. A value still in its
  stored string form is checked and kept as it is.

Reads: `lib/forcednavigation.php` and `login.php` answer 409 "Invalid stored preference state."
before any write; `systemmail()` refuses a recipient whose stored preferences are not valid,
inside its transaction, so no mail row is inserted; `translate_mail()` reads the recipient's
language through the schema (a failure there skips the post-commit email, as other delivery
failures do). `common.php`'s session default for an anonymous visitor is unchanged.

Writers: `prefs.php` builds the map the post would store and checks it before the password
change, the module preferences or anything else: an invalid value answers 400 "Your
preferences were not saved: a value is not valid. Nothing was changed." `saveuser()` and
`resurrection_player_mutation()` store `prefs` only through `PreferenceState::encode()`.
`lib/accounts.php` writes `serialize([])`.

### Editor old values

The user editor no longer uses posted `oldvalues` (accepted earlier); its `$oldvalues` is the
locked account row. `prefs.php` still wrote a hidden `oldvalues` field holding the serialized
form values, although 1.1.2's reader of it (`unserialize(httppost('oldvalues'))`) was already
gone and the posted copy was discarded. The field is no longer written; a page rendered before
this release that still posts it is discarded unread, as before. `modules.php`'s
`module_delete_oldvalues()` is unrelated (module settings). Nothing reads or writes a posted or
stored `oldvalues` now.

## Tests

- `tests/unit/NavigationPreferenceStateTest.php` (new): valid 1.1.2-shaped preferences
  (including a 13-field map with `showFormTabIndex`, a legacy key and a UTF-8 value, which
  round-trips byte for byte), the blank time offset, 34 malformed preference values (every
  field type and range, integer and spaced keys, arrays, NULL, objects, INF, an oversize map),
  valid navigation (links, the empty link, Fix Navs, the legacy entry 0) and 15 malformed
  navigation values, 9 valid and 20 malformed return pages.
- `tests/unit/SystemMailTest.php::testRecipientWithMalformedPreferencesNeverInserts` (new): four
  malformed recipient preference values are refused, no mail row and no notification.
- `tests/tooling/test_web_application.py::test_navigation_and_preference_state_http` (new):
  17 malformed stored values (7 preferences, 5 navigation maps, 5 return pages) each give 409
  on `village.php` and on login, with the account row, its output row and its module
  preferences unchanged; the blank 1.1.2 time offset renders prefs.php and is saved as 0;
  six invalid preference posts (bad flag, bad timestamp, bad offset, an SQL-shaped language,
  an integer field name, an array) give 400 and change nothing, although each also carries a
  valid password change; a valid post saves; the form carries no `oldvalues`; a 134-character
  request keeps the previous `restorepage`; Fix Navs' empty value redirects to badnav.php and
  shows the corrupted-navs page; the legacy entry 0 is dropped.

### Before the fix

Each production change was reverted on its own, with the tests above unchanged, against a fresh
MySQL 8.4 database (PHPUnit, then the web test):

| Revert | Result |
|---|---|
| R0 every changed production file as at `05485ec` | PHPUnit: the new SystemMail test fails. Web: 38 subtests fail: all 17 page and 17 login cases (500s or 303 instead of 409), the blank offset (TypeError in prefs.php), the writer (`oldvalues` still in the form), the long request (500), Fix Navs (500 at badnav.php:13) |
| R1 page load reads `prefs` without the schema | 8 fail: the 7 malformed page cases (a fatal on `false` in `holiday_texts.php`, or the save-side check throwing a 500) and the blank offset |
| R2 page load reads `allowednavs` as 1.1.2 | 6 fail: the 5 malformed maps (200 instead of 409) and Fix Navs (the wrapped value is refused by the save-side check) |
| R3 page load does not check `restorepage` | 5 fail: the 5 malformed return pages (200) |
| R4 `login.php` as at `05485ec` | 17 fail: every login case (303, logged in) |
| R5 `prefs.php` as at `05485ec` | 1 fails: the writer case (`oldvalues` in the form, then 200 for invalid posts) |
| R6 `restorepage` recorded from any request | 1 fails: the long request (500 from the save-side check) |
| R7 `badnav.php` as at `05485ec` | 1 fails: Fix Navs (500 at badnav.php:13) |
| R8 blank time offset kept as `""` | Unit: 1 error. Web: 2 fail: the account with a blank offset cannot log in (409), and the blank-offset post is refused (400) |
| R9 `systemmail.php` as at `05485ec` | Unit: the new SystemMail test fails (the mail is inserted) |

With the fix: PHPUnit 179/179 on MySQL 8.4; the web test passes in about 30 s.

## Local verification

Supporting evidence only; CI is the acceptance.

- All four shards, because navigation and preferences are read on every page and the shared
  save and player-mutation helpers changed, side by side on fresh MySQL 8.4 databases with
  PHP 8.5, 0 failures, errors or skips: shard 1 ran 50 tests in 883 s, shard 2 41 in 926 s
  (the new web test, 27.7 s), shard 3 40 in 896 s, shard 4 41 in 965 s.
- PHPUnit 179/179 on each shard database.
- PHP lint: 379 files, 0 failures.
- Both PHPStan gates: no errors (`src/` is in the level-6 gate, so the two new classes are).
- `composer validate --strict`: valid. `composer audit`: no advisories.
- Historical verifier: PASS. Project validator: PASS. Shard tooling test: OK.
- `git diff --check`: clean.

## Remaining work, proposed split

Each part is about one schema plus its tests, the size of this task.

1. **Combat buff compatibility (a defect; do first).** `ForestBuffState::validate`, used on
   forest combat, training, PvP and the graveyard, rejects buff shapes that 1.1.2 and the
   shipped modules write. Verified with a unit probe: the 1.1.2 installer Pony mount buff
   (`rounds:"20"`, `atkmod:"1.2"`, `activate:"offense"`) is refused ("Invalid combat buff
   fields."). Read from the code, not run: the mount editor's text fields, the lovers `wearoff`
   tuple, Crazy Audrey's `activate`, and a drinks buzz with text `rounds` are refused too, while
   `NewDayState::buffs` accepts most of them. A player carrying such a buff would get 409 in
   those routes. Move the New Day normalization into the combat check; unit cases for each
   shipped shape; one HTTP case entering the forest with the installer mount buff.
2. **Full buff-list schema at hydration and in writers.** `SpecialtyBuffState::collection` is
   exact only for specialty and transmute entries; other buffs only need to be arrays at
   `lib/forcednavigation.php:23`, `common.php:256` (which also turns a non-array into `[]`),
   the Dragon and Forest-specialty routes. Writers with no schema: `saveuser.php:19`,
   `player_mutation.php:66`, `specialty_combat.php:39`, `apply_buff()` (`lib/buffs.php:91`),
   the New Day result (`newday_daily.php:144` re-checks `$baseaccount`, not the new list).
   `login.php:57` reads the list and never uses it.
3. **Mount buff schema** (`MountBuffState`): exact mount-buff keys, legacy `activate`, text or
   number values, positive `rounds`, the 1.1.2 installer rows; at `stables_authority.php:34`
   (today `is_array` only), the mount editor (`mount_companion_editor.php:134`, which refuses
   1.1.2's text `minioncount`), `mounts.php:234` and New Day. Depends on part 1.
4. **Companions at hydration and in writers.** `SkeletonCompanionState` is exact only for the
   skeleton warrior; use `ForestCompanionState` (which accepts the 1.1.2 mercenary rows) at
   `common.php:342`, `lib/buffs.php:101`, `apply_companion()`, `saveuser.php:20`,
   `player_mutation.php:67` and `battle.php:522`.
5. **Companion abilities schema** for `mercenarycamp.php:216` (no check; a bad value is
   applied and saved), `companions.php:159`, `mount_companion_editor.php:385` (the code checks,
   but no malformed test) and `:158`.
6. **Forest-specialty combat schema.** `SpecialtyCombatState` is looser than `ForestCombatState`
   for the same Forest value (no reward-ledger, AI or flag checks) and stricter in two 1.1.2
   cases (no `creatureid` for the doppleganger; `spellpoints` added by the creature AI). Read
   from the code. Use the Forest contract on `forest.php?op=specialty`.
7. **Post-round re-check on Dragon and Forest specialty.** Forest, Training, Graveyard and PvP
   re-read the non-terminal state after `battle.php` writes it, inside the transaction; Dragon
   and Forest specialty commit it unchecked. Read from the code.
8. **1.1.2 in-flight combat formats for Training and Graveyard** (criterion 4). The 1.1.2
   `{type:'train'}` and torment states are refused (read from the code); either upgrade them
   or record a fail-closed exception as PvP already does (`PVP-COMBAT-AUTHORITY-20260927.md`).
9. **News arguments schema.** `news.php:73`, `bio.php:128` read arguments with no shape check
   (a non-array is a TypeError page); `lib/addnews.php:75` stores any arguments. A list of
   scalars or one-level taunt envelopes, with structure and size limits only; the format count
   must not be equality-checked, since 1.1.2 taunts with a stray `%` must still render
   (`test_news_and_bio_format_stored_stray_percent_http`). Display pages fall back safely;
   the writer refuses and the caller's transaction rolls back.
10. **Other business state:** dragon points at session load (`forcednavigation.php:36`,
    `login.php:54` fall back to `[]` without `DragonPointState::read`; read from the code, a
    later save can then overwrite a corrupt history); the moderated comment snapshot
    (`commentary_security.php:28`, `:71`, `moderate.php:137`); the payment log display and its
    GET `processdate` write (`paylog.php:37`, `:65`); the MOTD poll (`lib/motd.php:48`, `:222`,
    best done with a motd.php route task). `lib/mail.php`'s ten reads are inert: switch them to
    `MailContent::stored()` or record the file as excluded.

Not business state, no task: the data cache, the admin dump display, `postparse()` and
`dump_item_ascode()` (no callers), the donation config reads (value never used), the
navigation label keys, hash inputs, `ScalarState` itself.

## Remaining gaps of this part

- A stored preference or navigation value that fails the schema locks that player out of
  every page until it is repaired, the same bar as the buff and companion checks. Fix Navs in
  the user editor repairs `allowednavs` and `restorepage`; there is no in-game repair for
  `prefs` (the user editor shows it view-only). All shipped writers now store only valid
  values, so such a value can only come from the database itself.
- A 1.1.2 `restorepage` holding a quote or another refused character (possible if an old
  browser sent one raw) also locks the player out until Fix Navs. 1.1.2 links are generated
  with `urlencode()`, so this is unlikely.
- The login refusal still lets `resurrection_authenticate()` rehash an outdated password hash
  first; that is credential maintenance, not game state.
- `ScalarState::read()` requires canonical serialization, so a 1.1.2 float serialized by PHP 5
  with 17 digits (for example a `timeoffset` of 0.1) does not read; that bar is older than this
  part and unchanged.
