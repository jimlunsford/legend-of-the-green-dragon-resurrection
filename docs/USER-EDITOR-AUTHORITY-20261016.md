# User editor authority, part 1: account edit, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `6a9a12b56424ba7a6dd0dfbee2004b91ea4b074b`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Predecessor runs, inspected once at the start of this task:

- `c166e2e` (bio moderation and donation administration): Modern core 38892084451
  SUCCESS, Baseline integrity 38892084290 SUCCESS. Accepted; see
  [bio and donation admin authority](BIO-DONATION-ADMIN-AUTHORITY-20261016.md).
- `6a9a12b` (soul restoration and the `prefs.php` fatal): Baseline integrity 38896842463
  SUCCESS, Modern core 38896842544 FAILURE. Not accepted; both items stay in Remaining.
  See [restore and preferences](RESTORE-PREFS-AUTHORITY-20261016.md).

Scope: `user.php` (`SU_EDIT_USERS`; a megauser's session carries it too) for these ops:

- the account list and search (`op=` and `op=search`)
- the edit page (`op=edit`) and the module preference page (`op=edit&subop=module`)
- save (`op=save`), the special actions (`op=special`: Grant New Day, Fix Broken Navs,
  Mark Email As Valid) and module preference save (`op=savemodule`)
- the last page hit (`op=lasthit`) and the debug log (`op=debuglog`)
- the shared `returnpetition` parameter

Not opened: the bans (`setupban`, `saveban`, `delban`, `removeban`) are part 2. Delete
(`op=del`, `lib/user/user_del.php`) belongs to account deletion (`lib/charcleanup.php`).
The historical `$userinfo` field list in `user.php` is unchanged.

Route-local helpers are in `lib/user_editor.php`. They reuse the existing admin
transaction (`resurrection_admin_mutation`, which locks the editor and the target), the
one-use intents (`resurrection_action_fields` / `resurrection_consume_action`), the
admin failure mapping, and the typed preference descriptors (`SettingDescriptor`,
`lib/typed_editor.php`). No new framework.

## Findings verified on the previous code

Both new HTTP tests were run against the `6a9a12b` production code (the tests unchanged,
PHP 8.5.11 / MySQL 8.4):

| Test | Fails at | Observed |
|---|---|---|
| `test_user_editor_account_save_authority_http` | The first edit page GET | Fatal: `HTMLEntities(null)` in `lib/showform.php:366` for an account whose clan join date is NULL. The installed `clanjoindate` default is NULL, so this covers every account created outside a clan |
| Same, with a dated `clanjoindate` (temporary, not committed) | Escaping | `lastip` (`viewonly`) rendered raw: `<svg onload=1>` reached the page |
| Same, also skipping the escaping checks (temporary) | The unchanged save | Saving the form untouched wrote 2 fields: `specialty` was blanked (`specialty has changed to .`) and the titled name was rewritten |
| `test_user_editor_special_module_and_log_authority_http` | The first special check | A POST with no action button returned 200 |

The PHPUnit test fails on `6a9a12b` because `lib/user_editor.php` does not exist there.

## 1.1.2 comparison

| Behavior | 1.1.2 | `6a9a12b` | Now | Reason |
|---|---|---|---|---|
| Access | `check_su_access(SU_EDIT_USERS)` | Same | Same; covered for anonymous, player, every flag except Edit Users and megauser, Edit Users, megauser on every op | Brief: role matrix |
| Comparison base for save | `oldvalues`: the form's own snapshot, posted back serialized and `unserialize`d from the request | The current database row | A server-side snapshot of the form, kept in the editor's session and bound to the one-use intent | Stale-form decision below |
| Unchanged form | Rewrote the name (the title was recombined into `oldvalues['name']`); an enum with no matching option (a module specialty) was written as the first option | Same, compared with the live row | Writes nothing: "No fields were changed in the user's record." | Item 1 |
| Player changes after the form was shown | Reverted for every field the form carried | Reverted | Kept: only fields the admin changed are written | Item 1 |
| A changed field that changed underneath | Overwritten | Overwritten | Whole save refused, 409, nothing written | Item 1 |
| Posted `oldvalues` | The comparison base | Ignored | 400 | Not an account field |
| Field types | None; values interpolated into SQL | Strings only, bound | Each posted field checked against its `$userinfo` descriptor (int, bool, enum, password, bitfield, text) and its column (integer range, signedness, date format, NULL for a cleared nullable date, length) before any write; 400 otherwise | Item 3 |
| `viewonly` fields (`acctid`, `lastip`, `prefs`…) | Writable by a forged POST | Writable | 400 | Item 3 |
| Superuser flags | Strip what the editor cannot set, restore what they cannot remove; megauser sets all | Same formula, against the live row | Same formula, unchanged (3000 random cases against a transcription of 1.1.2). It is evaluated against the snapshot to see whether the admin changed the flags, then written against the locked row | Item 4 |
| Superuser checkboxes | Sum of every truthy key | Same | Keys must be 0 or one bit, each `1`; otherwise 400 | Item 3 |
| Password | `md5(md5())`; the debug log recorded the new password in clear | Hashed; `authversion+1`; logged "Administrator changed account password" | Same; admin-set passwords must be 12–72 bytes (the hash limit) | Item 6; deliberate correction |
| Special | Unbound SQL; no button did nothing | Unbound | Bound; exactly one action, POST + CSRF + one-use intent bound to the account's login, last hit and email validation, in the locked transaction | Item 7 |
| Grant New Day | `lasthit='0000-00-00 00:00:00'` | `NULL` | `NULL` (the zero date fails under strict SQL) | Unchanged from `6a9a12b` |
| Fix Broken Navs | Clears `allowednavs`, `restorepage`, `specialinc`; deletes the `accounts_output` row | Same | Same, nothing more (test compares every other column) | Item 7 |
| Module preferences | Any posted key written to any module name, unbound | Bound, any key | Installed module and declared, typed preferences only (`SettingDescriptor`); `viewonly` and undeclared keys 400; `validateprefs` still sees the values and can refuse | Item 8 |
| Module preference text | "Setting %s to %s" | "Preference %s updated." | "Setting %s to %s" | 1.1.2 text restored |
| Module preference page | Any module name | Same | Installed module only; "The %s module doesn't appear to define any user preferences." takes the name as a format argument | Deliberate correction |
| `returnpetition` | Any text, into links | Same | Positive integer up to 4294967295; anything else dropped | Item 9 |
| Last page hit | Raw stored page in the editor's origin | Same | Same page, served with `Content-Security-Policy: sandbox` | Item 10; deliberate correction |
| Debug log | `start` cast with `(int)`; previous page could go negative | Same, unbound | Bound; `start` must be an integer ≥ 0 (400 otherwise); previous page clamped at 0 | Item 10 |
| Search sort links | Search text in `&q=` raw | Same | URL-encoded | Item 10 |
| `viewonly` display | `dump_item` raw | Same | Escaped (keys and values) | Item 10; deliberate correction |
| Self-edit | Session fields set directly | Session rotated after the write | Same, after commit; the session's `auth_version` refreshed | Unchanged |

Texts, debug log texts and the name and title branches are the 1.1.2 ones except where
the table says otherwise.

## Stale-form decision

1.1.2's `oldvalues` was what made a stale form safe in principle: the save compared each
posted value with what the form had shown, not with the live row. But it trusted the
client to send that snapshot back, unserialized it from the request, and recombined the
title into the name, so every save rewrote the name. `6a9a12b` dropped `oldvalues` and
compared with the live row, which made every field the player had changed since the form
was shown look like an admin edit, and wrote the form's old value over it.

Now:

1. The edit page stores the snapshot it was built from in the editor's session, and the
   form's one-use intent is bound to a hash of it.
2. On save, every posted value is compared with what the form showed for it. Fields the
   admin did not change are not written, whatever happened to them since.
3. For each field the admin did change, the locked row must still hold the snapshot
   value. If any of them changed underneath, the whole save is refused (409) and
   nothing is written. The admin reloads and sees the current values.

Refusing the whole save rather than writing the other fields keeps one save one decision:
a partial write would apply an edit whose context had changed.

The `oldvalues` hidden field and its `serialize()` writer are gone; the serialized-state
inventory drops to 107 / 49 / 39 / 67 / 1 (`SERIALIZED-STATE-AUDIT.md`).

## Online target

The question was whether the player's page-end save overwrites an admin edit made while
that page is running. `saveuser()` writes only the fields whose session value differs
from `$baseaccount`, the row as the page loaded it, plus `laston`. It never writes the
password. That is the 1.1.2 model.

The test proves it with a second PHP server. The target loads `list.php`, a fixture hook
holds the page in its footer, the admin saves `gold=500`, and the page then adds a turn
and finishes. Both survive: gold 500 and turns 7778. A password change sets
`authversion+1`; the player's next request ends the session (303 to the timeout page),
and nothing else in the row changes.

Gap: an unlocked page that changes the same field the admin changed still writes last.
Pages that already use the locked player mutation recheck the row and refuse.

## Tests

PHPUnit `tests/unit/UserEditorTest.php`:

- the superuser formula against a transcription of 1.1.2 on 3000 random cases, plus
  explicit grant and removal cases
- the checkbox bit rules
- the field accept and refuse tables
- the displayed values and petition IDs

HTTP `test_user_editor_account_save_authority_http`:

- the role matrix on every op
- escaping and search
- an unchanged form; the stale form; a field changed underneath
- types; CSRF; a missing intent; an unknown account
- the date, boolean, name and gold saves; superuser rules
- the password; a late failure (a CHECK constraint) rolled back with the spent intent
  refused and a fresh form saving
- the online target; a self-edit

HTTP `test_user_editor_special_module_and_log_authority_http`:

- special: one action, replay, stale, the Fix Broken Navs column set
- savemodule: types, undeclared and `viewonly` keys, unknown and malformed modules,
  `validateprefs`, replay, stale
- `returnpetition`; debug log escaping and `start`; the last hit sandbox header

Local runs are supporting evidence only; acceptance comes from CI on both supported legs.

## Remaining gaps

- An unlocked player page that changes the same field the admin changed writes last
  (above).
- Module preference saves trust the module's declarations; a module whose preferences
  have no typed descriptor shows its page but cannot be saved (fail closed).
- The bans are part 2. Delete is tracked under account deletion.
- The 1.1.2 `modifyuserview` hook can still add display fields. A field it adds that is
  not `viewonly` and not in `$userinfo` now makes the save 400; 1.1.2 ignored it. No
  bundled module uses the hook.

## Candidate CI, 2026-10-16

`fa30beba8bf6225fc638c83d6f7c0bbb4930e295` was inspected once at the start of the CI
rollover task (13:38Z). Baseline integrity 38907797223 SUCCESS. Modern core 38907797754
was still running: both `checks` jobs SUCCESS, five `http` shards SUCCESS, three still
in progress, and no `result` job yet. User editor part 1 stays in Remaining. Soul
restoration and the `prefs.php` fix cannot be accepted through `fa30beb` until that run
is accepted. Their tests (`test_graveyard_restore_authority_http` and
`test_create_login_rotate_render_logout`) are byte-identical between `6a9a12b` and
`fa30beb`. Of the code they reach, only `lib/showform.php` changed, in its `viewonly`
branch (escaping); the boolean field the `prefs.php` fix covers is a separate branch.
