# Module manager, remaining preference editors and the stables mount lock

Date: 2026-10-17. Phase 3, route authority. Starting head
`19cbb4e3c5814476d0d11ce6fcbcb92cb5941b39`. Status: **candidate, CI pending**.

Content editors part 3, the two bio items and the mount owner lookup (`19cbb4e`) are
accepted (Modern core 38991243831, Baseline integrity 38991243814; see
[content editors part 3](CONTENT-EDITORS-PART3-AUTHORITY-20261017.md#acceptance-2026-10-17)).

Scope: the module manager (`modules.php`), the inventory of every request path that
writes module settings or preferences, and the stables purchase's mount read
(`stables.php`). The module hook system, the bundled modules' own code and the
configuration editor were not opened.

## Module manager (`modules.php`)

`check_su_access(SU_MANAGE_MODULES)` gates the page, as in 1.1.2. Every operation now:

- shows only a confirmation form on GET (the mass form has no GET; it is 405);
- runs only on POST with CSRF and a one-use intent bound to the operation, the module
  and its install state (installed, active, version, install date, file date) as shown;
  for the mass form, to the list and the state of every module in it;
- checks and acts while holding a named database lock
  (`GET_LOCK('resurrection-module-manager')`), so the state an intent names cannot change
  between the check and the operation through another manager request;
- accepts only a bundled module (`resurrection_bundled_modules()`, with its file present
  in `modules/`) for install, activate, reinstall and uninstall. An installed module that
  is not bundled can only be deactivated.

Statuses: 400 for a malformed, unknown or non-bundled name, an extra query parameter or
form field, a repeated or unoffered mass entry, or a wrong button; 403 for bad CSRF; 405
for GET on the mass form; 409 for a repeated, stale or wrong-state form (for example,
install of an installed module). A refused request changes nothing.

Role matrix (tested on every operation's URL, GET and POST): anonymous is redirected or
refused; a player and a superuser with every flag except `SU_MANAGE_MODULES` get 403;
`SU_MANAGE_MODULES` gets the pages.

### 1.1.2 comparison

| Point | 1.1.2 | Before this change (`19cbb4e`) | Now |
|---|---|---|---|
| Single operation | The list's GET link acted at once | GET showed a form posting back to `REQUEST_URI` with CSRF, no intent | GET shows a form posting to a URL built from `op`, `module` and `cat` only, with a one-use intent bound to the module's state |
| Repeated form | Repeated the operation | Ran again (CSRF is reusable) | 409 |
| Stale form (state changed since shown) | Ran | Ran | 409, checked under the manager lock |
| Extra parameters on the confirm URL | n/a | Carried in `REQUEST_URI` into the form's action | 400; the action is built, not copied |
| Module name | Any string; reinstall and uninstall pasted it into SQL | Pattern-checked; uninstall's deletes still interpolated | Pattern, then the bundled registry; all SQL bound |
| Files in `modules/` that are not bundled | Installable; the uninstalled list `require`d every file to read its info | Same | Listed as "Not installable (Not a bundled module)" without being loaded; install refused |
| Mass request | Last matching button won (install over the rest); names unchecked | Same, names pattern-checked | Exactly one of the list's own buttons; every entry a distinct module the list showed and the operation allows; one bad entry refuses the whole request before any change |
| Install text | "Module installed.  It is not yet active." | Missing (the lifecycle API prints nothing) | Restored, with 1.1.2's prerequisite line on that failure |
| Activate or deactivate a module not installed | Installed it first | Same (route allowed it) | 409; install it first |
| Reinstall | Reset the file date, re-ran install through the upgrade path, kept the active state | `install_module(force)`: settings kept, install re-run, left inactive (recorded S04 decision) | Unchanged from `19cbb4e` |
| Uninstall | Script, entry, hooks, settings, user prefs, object prefs, no transaction | Same order and texts; entry bound, the rest interpolated | See the uninstall finding below |
| Cache | Route dropped `inject-<module>` for activate, deactivate and reinstall; the API dropped `inject-` and `moduleprepare` on every operation and the hook entries on uninstall | API drops every `hook-`, `event-`, `moduleprepare` and `inject-` entry and the module's settings on every operation | Unchanged; the route's redundant `inject-` calls are removed |
| Escaping in the lists | Descriptions in `title` raw, authors printed as raw HTML, uninstalled names, categories and file names raw, links unencoded | Same | Escaped; an author's `<br>` stays a line break; URLs encoded |
| "Installed On" sort link | Link and allowed navigation disagreed, so the click was a bad navigation | Same | Fixed |
| Uninstalled list form | Never closed | Same | Closed |

Texts, categories, the uninstall warning and the lifecycle results are otherwise
1.1.2's. New texts: the confirmation line ("Confirm %s of %s"), "(Not a bundled module)",
the two uninstall failure reports below, and the refusal lines.

### Uninstall finding

1.1.2 (and `19cbb4e`) ran the module's uninstall script, then deleted the module entry,
its hooks (under `LOCK TABLES`), settings, user prefs and object prefs as separate
statements with no transaction. A failure partway (read from the code; the revert run
below ends on the fatal page) left a half-removed module: entry and hooks gone, so the
manager listed it as uninstalled, while later rows stayed behind and a later install kept
them.

Chosen: **all-or-nothing for what core deletes, and the truth on the page for what it
cannot roll back.** Full all-or-nothing is impossible within the lifecycle API: `dag` and
`drinks` drop their tables in their uninstall scripts (DDL commits implicitly), and the
race and specialty scripts update accounts. So `uninstall_module()` now:

1. deactivates the module first, so a failure never leaves a half-removed module running;
2. runs the module's uninstall script (unchanged; it can still read its settings);
3. deletes the entry, hooks, event hooks, settings, user prefs and object prefs in one
   transaction, bound, and prints 1.1.2's six lines only once all of them committed.

On a failure the response is 500 and the page says exactly what happened: either "The
module uninstall script failed. Its entry, hooks, settings, user prefs and object prefs
were not deleted. The module is still installed and is now inactive." or "The module
uninstall script ran, but deleting its entry, hooks, settings, user prefs and object
prefs failed, so none of them were deleted. The module is still installed and is now
inactive. Uninstall it again to finish." Every bundled uninstall script is safe to run
again (`DROP TABLE IF EXISTS`, idempotent updates). A mass uninstall stops at the first
module that does not complete; the ones before it are complete.

The hook deletes are now plain bound deletes inside the transaction instead of
`module_wipehooks()` (which used `LOCK TABLES` and interpolated SQL); the cache entries it
dropped are covered by the API's full invalidation. `module_wipehooks()` itself is
unchanged and has no remaining caller.

### Cache

No stale cache follows install or uninstall, in 1.1.2 or now: the lifecycle API drops the
data cache on every operation (1.1.2: `inject-` and `moduleprepare`; now every `hook-`,
`event-`, `moduleprepare` and `inject-` entry). The route's extra `inject-<module>` calls
were redundant in both and are removed. The test runs every operation on a server with
the data cache on: each cache file the earlier pages wrote is dropped or rewritten, and
the village shows Crazy Audrey exactly while the module is active.

### Confirmation form

The form's action is now `modules.php?op=<op>&module=<module>&cat=<category>`, built from
the validated values and encoded; any other query parameter is 400. It carries only the
CSRF token and the intent; any other posted field is 400.

## Remaining preference editors

Search run (outside `vendor/` and `tests/`):

```
rg -n --type php -l "module_settings|module_userprefs|module_objprefs|set_module_setting|set_module_pref|set_module_objpref|increment_module_(setting|pref|objpref)|clear_module_(pref|settings)|module_delete_(objprefs|userprefs)"
rg -n --type php -l "httpallpost|\$_POST\b"
```

Every hit that writes from a request:

| File | Route | Writes | Protection | Status |
|---|---|---|---|---|
| `configuration.php`, `lib/typed_editor.php` | `configuration.php` settings and `op=modulesettings` | `settings`, `module_settings` | `SU_EDIT_CONFIG`; typed descriptors; POST, CSRF, intent, locked recheck | Closed (shared typed settings editor) |
| `user.php`, `lib/user_editor.php` | `user.php` module prefs | `module_userprefs` | `SU_EDIT_USERS`; typed descriptors; POST, CSRF, intent | Closed (user editor part 1) |
| `prefs.php` | Player preferences, `<module>___user_*`/`check_*` | `module_userprefs` | POST, CSRF; namespace, declared key and active module checked; every module descriptor refused until typed (no bundled module declares one) | Closed fail-closed (player preference namespace) |
| `mounts.php`, `creatures.php`, `companions.php`, `lib/typed_editor.php` | `op=module` object prefs | `module_objprefs` | Each editor's flag; shared typed object editor; POST, CSRF, intent | Closed (mount object prefs; content editors parts 2 and 3) |
| `lib/mount_companion_editor.php` | Mount and companion delete | `module_objprefs` delete | Inside the delete's locked transaction, bound | Closed (content editors part 3) |
| `modules/drinks/misc_functions.php` | Drinks editor `subop=module`, delete | `module_objprefs` | Object pref save refused (400); delete bound, inside the editor's protected delete | Closed (drinks editor) |
| `modules.php`, `lib/modules.php` | Module manager install, reinstall, uninstall | `module_settings` defaults, all module rows on uninstall | This change | Candidate (this record) |

Other hits are gameplay state written by already-accepted module actions (Cedrik, Crazy
Audrey, Dag, Dark Horse, Drinks, Fairy, Five Sixes, Goldmine, Lovers, Outhouse, races,
Seth, specialties, New Day, Dragon points, combat), installer paths, account cleanup
(`lib/charcleanup.php`, its own ROADMAP item) and schema (`lib/all_tables.php`). None is a
request-driven preference editor. **No open preference editor remains.**

## Stables mount lock (`stables.php`)

The purchase read the mount without a lock inside its transaction, so a mount the editor
deactivated or deleted after the purchase form was shown, with that change still
uncommitted when the purchase read it, was sold from the older snapshot (reproduced
below). `resurrection_stables_mount()` takes a `$lock` flag, and only the purchase
(`confirmbuy`, inside the existing player transaction) reads with `FOR UPDATE`. The
purchase now waits for the editor's change and then sees it: an inactive or deleted
mount is refused with 1.1.2's "no such beastie" text and nothing changes. Prices,
trade-in, texts, offer rules, feeding and selling are unchanged.

The purchase locks the player's account and then the mount; the editor's mount delete
locks the mount and then the owners' accounts. If the two meet on the same rows, the
database rolls one back as a deadlock and that page says nothing was changed. Not
separately tested.

## Tests

- New `test_module_manager_authority_http` (shard 3): role matrix on every operation;
  escaping of every listed field; a stray file in `modules/` listed as not installable and
  never run; GET confirm only; 400 for unknown, stray, non-bundled, malformed and
  extra-parameter requests; 409 for wrong state and for another module's or operation's
  form; CSRF; repeated and stale single and mass forms; mass with two buttons, another
  list's button, a repeated entry, a malformed entry, a non-list, an extra field, and one
  unknown, stray or non-bundled entry among good ones; activate, deactivate, reactivate,
  reinstall, uninstall (first with a failure on the last delete, then complete) and
  install of Crazy Audrey with settings, user prefs, object prefs, hooks and event hooks
  checked before and after each, and the data cache checked after each.
- `test_stables_buy_feed_and_sell_authority_http` (shard 1) extended: a deactivation and a
  delete held uncommitted while the purchase runs are waited for, and the purchase is
  refused with nothing charged.

Before the fix (on `19cbb4e`'s `modules.php` and `lib/modules.php`): the module manager
test fails at the first list page, `'<script>' unexpectedly found` (the author printed as
raw HTML). With the stables read unlocked, the stables test fails on both the deactivated
and the deleted mount: the purchase completes ("leads out a fine ...") and the state
changes. Both pass with the fix.

Guard-by-guard reverts, each on the final tree with one guard removed (module manager
test):

| Revert | Result |
|---|---|
| Uninstalled list loads files that are not bundled | Fails: the stray file ran (`True is not false`) |
| No bundled check | Fails: 409 instead of 400 for an unknown module |
| 1.1.2-order uninstall (no transaction) | Fails: fatal 500 page on the injected failure |
| No mass entry check | Fails: a mass request with one unknown entry ran (200, not 400) |
| No query parameter allow-list | Fails: the confirm URL with `&sortby=active` is 200, not 400 |
| Mass intent not bound to the list | Fails: a stale mass form ran (200, not 409) |
| Single intent not bound to the state | Fails: a repeated activate ran (200, not 409) |

The manager lock (`GET_LOCK`) is defence in depth for two managers acting at once; it is
not separately tested.

## Verification

Local, PHP 8.5.11 / MySQL 8.4 (supporting evidence only; CI is the acceptance):

- Shards 1 (stables test) and 3 (module manager test) through `scripts/tooling-shards.py`,
  each on its own fresh database after PHPUnit, run side by side: shard 1 40 tests in
  885 s, shard 3 39 tests in 889 s; no failures, errors or skips. No shared helper
  changed, so shards 2 and 4 were not run locally.
- PHPUnit 158/158. `tests/tooling/test_tooling_shards.py` 4/4.
- Before the fix and each revert above, on the same local database.
- PHP lint: 370 files, no failures. Both PHPStan configs: no errors
  (`lib/module_manager.php` added to the level-6 config).
- `composer validate --strict` valid; `composer audit` no advisories.
- Historical verifier (417 files, 11 commits) and project validator pass; `git diff
  --check` clean.
- Serialized-state source unchanged; inventory stays 107/50/40/66/1.

## Remaining gaps

- `activate_module()` and `deactivate_module()` in the lifecycle API still install a
  module that is not installed, as in 1.1.2. The manager no longer calls them that way;
  the API is unchanged.
- `module_wipehooks()` keeps 1.1.2's `LOCK TABLES` and interpolated SQL; it has no caller
  left (the hook system was not opened).
- `prefs.php` reads the player's module prefs with the module names (from installed
  module info) and the player's ID interpolated into SQL. Read only, no request input; not
  changed here.
- A module that is not bundled but still installed (for example from an older database)
  can only be deactivated from the manager; removing it needs its code, which the
  manager no longer runs.
- The stables deadlock case above is not tested.
