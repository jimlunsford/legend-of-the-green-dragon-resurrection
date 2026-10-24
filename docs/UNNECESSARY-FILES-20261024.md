# Unnecessary files removed, 2026-10-24

Maintenance task named by Jim. Not a phase. Starting head: `main` at `d0e17abbf9e1a7b4ace11360d56e5764b8019f02`.

Every removed file is a 1.1.2 file and stays byte for byte at `historical-source-1.1.2` (tag object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files). Removing
it from `main` does not touch the tag, the manifest or the historical verifier, which reads Git objects only. Five of
the removed PHP files carried modern edits (marked "modified" below); those edits only kept dead code linting on
PHP 8 or turned it into a refusal, and nothing depended on them.

## Search method

A file was removed only when all of these held, at `d0e17ab` and with the removals applied:

1. **No static reference.** No tracked file outside `docs/` names it: neither its path nor its basename, compared
   case-insensitively. This covers every `require`/`include`, `src=`/`href=`, CSS `url()`, test, script,
   PHPStan/PHPUnit config, workflow and `project.json`. For a template's own image, the path below `templates/` (for
   example `volcano/logo.gif`) was matched, since several skins share basenames.
2. **No dynamic reference.** Every `require`/`include` whose target is not a plain literal was listed and resolved:
   `about.php` (`lib/about/about_$op.php`), `petition.php` (`lib/petition/petition_$op.php`), module loading
   (`modules/$name.php`, `lib/modules.php` and `lib/installer/bundled_modules.php`), `installer_stage_6.php`'s config
   path and `scripts/install.php`. The Graveyard and New Day case dispatch are literal `require_once` lines. Asset
   names built from strings were searched separately (`"images/".(...)."scroll.GIF"` in `lib/mail/case_default.php`
   is the only one). Skins are chosen by `templates/*.htm` name only (`SettingDescriptor`, `home.php`, `prefs.php`,
   `lib/pageparts.php`, `lib/template.php`), so a skin's images are reached only through its `.htm` and `.css`.
3. **Not reachable through the include graph.** A graph of every literal include in tracked PHP, walked from the
   root pages, bundled modules, `scripts/`, `tests/` and `lab/` plus the two dynamic families above, does not reach
   it.
4. **Not a route.** It is not a page in `docs/ROUTE-INVENTORY-20261022.md` (every removed PHP file is under `lib/`,
   and none is named there), and `tests/tooling/test_route_inventory.py` passes unchanged.
5. **The tests pass without it.** See "Verification".

Also checked: what used the file at `historical-source-1.1.2`, to record whether modernization orphaned it or it was
already dead in 1.1.2.

## Removed

| Path | 1.1.2 file | Why it goes | How it was proved unused |
|---|---|---|---|
| `TODO.txt` | yes, unchanged | 1.1.2 developer to-do list | Decided by Jim. No tracked file names it |
| `AFTERUPGRADE.txt` | yes, unchanged | 1.1.2 manual upgrade steps (datacache settings into `dbconnect.php`); Resurrection refuses upgrades without a verified migration | Decided by Jim. Named only by `CHANGELOG.txt:134`, kept as written (see below) |
| `BUG FIXES.txt` | yes, unchanged | 1.1.2 list of maintenance fixes present at upload | Decided by Jim. Named only by `PROVENANCE.md`, which now says it is kept at the tag |
| `lib/register_global.php` | yes, modified | Exported request array keys as globals | No caller; 1.1.2's only caller, `lib/php_generic_environment.php`, no longer includes it |
| `lib/safeescape.php` | yes, unchanged | Quote escaping for string-built SQL | No caller; 1.1.2's callers (`lib/systemmail.php`, `lib/clan/clan_membership.php`) now bind their SQL |
| `lib/show_backtrace.php` | yes, modified | Backtrace HTML for error pages | No caller; 1.1.2's callers (the old `mysql`/`mysqli` wrappers and `lib/errorhandler.php`) no longer include it; diagnostics never emit backtraces (`REQUEST-SEMANTICS.md`) |
| `lib/spell.php` | yes, modified | Spell-check helper | No caller at this head or in 1.1.2 |
| `lib/e_dom.php` | yes, unchanged | Emits a `<script>` for `lib/e_dom.js` | No caller at this head or in 1.1.2 |
| `lib/e_dom.js` | yes, unchanged | DOM helper script | Named only by `lib/e_dom.php` (removed) |
| `lib/md5.js` | yes, unchanged | Client-side MD5 for 1.1.2's password forms | 1.1.2's `create.php`, `home.php` and `prefs.php` emitted it; the modern forms send the raw password for `password_verify()` and no page names it or calls `hex_md5` |
| `lib/source.php` | yes, unchanged | Directory walker for a source viewer | No caller at this head or in 1.1.2 (1.1.2's `source.php` did not include it); `source.php` stays as the disabled 410 endpoint |
| `lib/newday/dp_recalc.php` | yes, unchanged | Dragon Point recalculation fragment | No caller at this head or in 1.1.2; `lib/dragon_points.php` owns the allocation and the `pdkpointrecalc` hook |
| `lib/graveyard/case_battle_search.php` | yes, modified | 1.1.2's Graveyard search case; the modern file only threw "Use the protected Graveyard combat route." | `graveyard.php` no longer includes it (1.1.2 did, at `:28`); the search runs through `lib/graveyard_combat.php` |
| `lib/mail.php` | yes, modified | Inert duplicate of the pre-split mail page; `mail.php` and `lib/mail/` are the live code | No tracked PHP includes it here or in 1.1.2. Recorded as excluded in `SERIALIZED-SCHEMAS-PART5-20261022.md`; its serialized-state sites, SQL sweep rows and test path are updated below |
| `images/Medallion-Green.gif` | yes, unchanged | Unused image | No tracked file names it here or in 1.1.2 |
| `images/Medallion-Red.gif` | yes, unchanged | Unused image | As above |
| `images/Medallion-Yellow.gif` | yes, unchanged | Unused image | As above |
| `images/mightyesig.gif` | yes, unchanged | Unused image | As above |
| `images/paypal.gif` | yes, unchanged | Unused image | As above (1.1.2's donation buttons used `paypal1`-`3.gif`, not this one) |
| `templates/ailean/check.gif` | yes, unchanged | Skin image its skin never uses | Not named by `ailean.htm`, `aileans.htm`, `ailean.css` or any other file, here or in 1.1.2 |
| `templates/ailean/larrow.gif` | yes, unchanged | As above | As above |
| `templates/desert/logindragon.gif` | yes, unchanged | As above | `desert.htm` uses `images/logindragon.gif` (kept); nothing names `desert/logindragon.gif` |
| `templates/volcano/logo.gif` | yes, unchanged | As above | `volcano.htm` uses `volcano/logo.jpg` (kept); nothing names `volcano/logo.gif` |
| `templates/volcano/logo2.gif` | yes, unchanged | As above | Nothing names it, here or in 1.1.2 |

24 files: the three root text files and 21 shipped files. Tracked files on `main`: 664 before, 641 after (640 plus
this document).

## Kept, with the reason

Kept, unsure:

- `favicon.png`: no file names it (`lib/pageparts.php` links `favicon.ico`), but it is a web-root icon a client may
  request by convention without a link. Removable if Jim agrees.
- `images/paypal1.gif`, `images/paypal2.gif`, `images/paypal3.gif`: no file on `main` names them, but they are the
  donation buttons of the disabled historical payments surface (1.1.2 `lib/pageparts.php:256`, `:272`, `:292`) and
  of LoGDnet (1.1.2 `images/logdnet.php`). The task keeps disabled surfaces, so they stay. Removable if Jim agrees.

Kept, not candidates:

- `images/oldscroll.GIF`: no file names it whole, but `lib/mail/case_default.php:21` builds it from
  `"images/".($row['seen']?"old":"new")."scroll.GIF"`. Used.
- The historical web installer's stages (`lib/installer/installer_stage_0.php` to `_10.php`,
  `installer_stage_default.php`, `installer_functions.php`) and `lib/pullurl.php` (used only by stage 1):
  `installer.php` answers 403 before any include, so nothing reaches them, but they are a disabled surface;
  `test_unbound_sql_files_are_not_endpoints_http` checks four of them, `InstallerStage6Test` loads stage 6, and
  `lib/installer/fresh_install.php` loads `installer_sqlstatements.php` and `installer_default_settings.php`.
- `images/logdnet.php`, `logdnet.php`, `payment.php`, `source.php`, `rawsql.php` (410, `test_disabled_endpoints.py`)
  and `installer.php` (403, `test_unbound_sql_files_are_not_endpoints_http`): disabled endpoints.
- `lib/about/about_*.php` and `lib/petition/petition_*.php`: reached through `about.php` and `petition.php`'s `$op`
  include.
- Every `templates/*.htm` skin and its `.css`: chosen by name from `templates/*.htm`.
- `README.txt`, `CHANGELOG.txt`, `LICENSE.txt`: the original authors' credits and license. `CHANGELOG.txt:134` still
  says "see AFTERUPGRADE.txt"; it is the authors' historical changelog, kept as written, and the file it names is
  at the tag.
- `lab/`, `docs/`, the 24 bundled modules, `composer.lock`, the CI workflows, `scripts/` and the tests: excluded by
  the task.

## Records updated in the same commit

- `tests/tooling/test_web_application.py`, `test_unbound_sql_files_are_not_endpoints_http`: `lib/mail.php` was one
  of the five files it requested, expecting the file's own 404 guard (`Not an application endpoint.`). With the
  file gone the guard cannot answer, so for that path the test now asserts the file does not exist and that a
  request gets 404. The four installer files keep the exact assertion. Before the change, with the file removed, the
  test fails on `lib/mail.php` (404 with PHP's "Not Found" page instead of the guard's body); after it, it passes.
- `docs/RESCAN-TASK3-20261023.md`: the eight `lib/mail.php` rows leave the SQL sweep's reviewed list, as
  `SqlSweepTest` requires for values no longer interpolated ("Listed but no longer interpolated"). No other row and
  no test changed.
- Serialized-state inventory: the ten EXCLUDED `ScalarState::read` sites in `lib/mail.php` are gone. Token recount
  (the merge gate method; it reproduces 94 / 45 / 38 / 55 / 1 at `d0e17ab`): **84 sites / 44 files / 38 serialize
  writers-checks / 45 ScalarState reads / one centralized unserialize**. No other removed file held a site.
  Recorded in `SERIALIZED-STATE-AUDIT.md` and `CLAUDE.md`.
- `docs/ROUTE-INVENTORY-20261022.md`: no change; no removed file is in it.
- PHPStan baselines and configs: no change; no entry names a removed file.
- `PROVENANCE.md`, `docs/REQUEST-SEMANTICS.md` (an appended note), `ROADMAP.md`.

## Verification

Local, on the working tree with the removals (supporting evidence only; CI on the published commit is the
acceptance). PHP 8.5.12 with MySQL 8.4, one disposable database per run:

- PHPUnit: 265 tests OK, run in five separate copies. `SqlSweepTest` with the eight `lib/mail.php` rows still listed
  fails ("Listed but no longer interpolated", `lib/mail.php` `$id` first); with them removed it passes.
- `test_unbound_sql_files_are_not_endpoints_http`: the old version fails on `lib/mail.php` (404 with PHP's own
  "Not Found" page, not `Not an application endpoint.`); the new version passes.
- All four HTTP shards through `scripts/tooling-shards.py --shards 4`: shard 1 58 tests OK (1342 s), shard 2 49 OK
  (1369 s), shard 3 49 OK (1436 s), shard 4 50 OK (1467 s). `test_route_inventory.py` and `SqlSweepTest` are
  unchanged and pass.
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors. `scripts/lint-php.py`: 388 files, 0 failures.
- `composer validate --strict`, `composer install`, `composer audit` (no advisories).
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: PASS (641
  tracked files). `git diff --check`: clean.

## Accepted, 2026-10-24

The cleanup is accepted at its own commit, `acb31f61628b25016bb7a177df9e8c0ba0e7ef61`, read through the GitHub Actions
API by the next task:

- Modern core 40087707354: success. All 12 jobs success: for each leg (PHP 8.5 with MySQL 8.4, PHP 8.4 with MariaDB
  11.4) `checks`, `http (1)` to `http (4)` and `result`.
- Baseline integrity 40087707287: success.

## License and dead weight, 2026-10-24

Maintenance task named by Jim. Not a phase. Starting head: `main` at `acb31f61628b25016bb7a177df9e8c0ba0e7ef61`.
Engineering work, not legal advice; uncertain points are recorded as open in `LICENSING-AND-PROVENANCE.md`.

Jim's rule: keep what the license requires and what the game needs to run; remove dead weight (anything disabled,
unreachable or left over from removed features). Working 1.1.2 game features stay (skins, translator tools, donation
points, the Hunter's Lodge, modules), as do `lab/` and `docs/`. Never removed or altered: `LICENSE.txt`, every
copyright notice, the original about-page and license-page text, `README.txt`.

### The source link (A)

1.1.2's "View PHP Source" link (`lib/pageparts.php`, the skins' `{source}`) opened `source.php?url=<page>` in a
popup, its way of offering the source. `source.php` was a 410 stub, so the link was dead. It now links the new core
setting `sourceurl` (default `https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection`, also in the
installer's default settings), with the same text ("View PHP Source", translated as before) in the same place. The
setting's label in the settings editor says that an operator who changes the game must point it at their own
complete modified source, because the license requires giving that source on request to anyone they let play.

- `lib/source_link.php`: `resurrection_valid_source_url()` accepts only `http://` or `https://`, a valid host name or
  IP address, an optional port 1 to 65535, then an optional path, query or fragment without whitespace, control
  characters, quotes, angle brackets, backslashes or backticks, and no user info. The link escapes the URL
  (`htmlspecialchars`, `ENT_QUOTES`). An unset or invalid stored value (only reachable by writing the database
  directly) shows the default instead; the page view never stores anything (unlike `getsetting()`).
- The settings editor refuses an invalid value with 400 and changes nothing (`resurrection_settings_rules()`).
- Differences from 1.1.2, recorded: the link is a plain `target='_blank'` link with `rel='noopener noreferrer'`, not
  a `window.open()` popup (the popup was sized for `source.php`'s text listing and would put the URL inside a
  script string), and it points at one repository, not at the current page's file. The source viewer is not
  re-enabled.
- `source.php` itself is removed (E1).

### NOTICE (B) and third-party licenses (C)

`NOTICE` at the root names the work and its authors, the license (CC BY-NC-SA 2.0 plus `LICENSE.txt`), that
Resurrection's modifications are licensed the same way, that the project is noncommercial and not endorsed, and points
to the third-party list. `README.md` links it. `docs/THIRD-PARTY-LICENSES.md` lists every package in `composer.lock`:
no runtime package; 26 development packages (3 MIT, 23 BSD-3-Clause), installed into the untracked `vendor/`. No
license incompatible with distributing the project under CC BY-NC-SA 2.0 was found. Dependencies are unchanged.

### The required-text test (D)

`tests/tooling/test_license_text.py` (static, no database; reads the tag through `git show`):

- `LICENSE.txt` and `README.txt` are byte for byte the tag's copies.
- For `about.php` and every `lib/about/*.php` at the tag (the default, `setup`, `listmodules` and `license` ops),
  every original line that names a copyright (`copyright`, `©`, `(c)`) is still a line of the current file, and
  every sentence of every string the page shows (`output`, `output_notl`, `rawoutput`, `translate_inline`,
  `addnav`, `page_header` arguments and the setup page's label values) is still in the current file's shown strings.
  Added text passes. 150 sentences and 7 copyright lines are compared.
- `about.php` still dispatches all four ops, the tag is `bdc29df`, and a self-test proves the comparison catches a
  removed line, an altered author name and a removed `@copyright` docblock line.

Shown failing on a scratch copy of the tree: with the sentence "They worked with the letter but not the spirit of the
law." deleted from `lib/about/about_license.php`, the about-page test fails naming that sentence; with "Eric Stevens"
changed in `lib/about/about_default.php` and a line appended to `LICENSE.txt`, both the about-page test (the
`@copyright` line and the sentence) and the byte test (`LICENSE.txt differs from historical-source-1.1.2`) fail. On
the real tree all five tests pass.

### Removed (E)

Each removed file is a 1.1.2 file (except `src/Security/PaymentInfo.php` and its test, Phase 3 additions) and stays
byte for byte at `historical-source-1.1.2`; the tag, manifest and historical verifier are untouched.

| Path | Why it goes | How it was proved unused or disabled |
|---|---|---|
| `payment.php` | PayPal intake; a 410 stub before bootstrap | No include, no link (1.1.2's footer notify URL was removed earlier, `SECURITY-STATUS.md` S10); only `test_disabled_endpoints.py` and the route inventory named it |
| `source.php` | Source viewer; a 410 stub | Only the footer `{source}` link (now the public source, A), the test and the inventory |
| `rawsql.php` | SQL/PHP console; a 410 stub | Only `superuser.php`'s "Run Raw SQL" nav (removed), the test and the inventory |
| `logdnet.php` | LoGDnet server list; a 410 stub | Only `home.php`'s "LoGD Net" nav (removed), the test and the inventory |
| `images/logdnet.php` | LoGDnet registration image; a 410 stub | Only the test (1.1.2's footer script that loaded it was removed earlier, `SECURITY-STATUS.md` S03) |
| `paylog.php` | The PayPal payment log | Payments are disabled, so no row can be added: `payment.php` was the only writer of `paylog` (a fresh install has none). Reached only from `superuser.php` and `donators.php` ("Payment Log", removed) and `donators.php`'s transaction credit redirect (changed below) |
| `src/Security/PaymentInfo.php` | `paylog.info` reader | Required only by `paylog.php` and `tests/unit/PaymentInfoTest.php` |
| `tests/unit/PaymentInfoTest.php` | The removed class's test | Removed with the class (2 tests) |
| `images/paypal1.gif`, `paypal2.gif`, `paypal3.gif` | 1.1.2's donation buttons | No tracked file outside `docs/` names them; their 1.1.2 users were the footer's PayPal forms (removed earlier, `SECURITY-STATUS.md` S10) and `images/logdnet.php` (removed above) |
| `CHANGELOG.txt` | 1.1.2 history | No copyright or license notice: none of `copyright`, `licen`, `©`, `(c)`, `GPL`, `Creative`, `all rights` occurs in it (0 matches); identical to the tag's copy; no tracked file outside `docs/` names it (the historical manifest lists the tag's files) |

The five 410 endpoints become 404: no file answers. The features stay disabled; removing a stub serves nothing new.

Also removed: Eric Stevens's two `//NOTICE |` comment blocks in `lib/pageparts.php` asking operators to keep his
PayPal link. The license does not require them (they say so themselves: "under the license, you're not required to
keep this paypal link"), and the link was removed earlier (`SECURITY-STATUS.md` S10). They are not a copyright notice; the file's own
docblock is unchanged. The two `{paypal}` replacements stay, now with a short comment: 18 skins still contain
`{paypal}`, and the replacement empties it.

Nav links removed, each a deliberate correction (the target was a disabled 1.1.2 feature, so the link was dead):
`home.php` "LoGD Net" (`logdnet.php?op=list`); `superuser.php` "Run Raw SQL" (`rawsql.php`) and "Payment Log"
(`paylog.php`); `donators.php` "Payment Log" (`paylog.php`). No other shipped file linked a removed file
(`test_no_shipped_file_links_a_removed_file`).

**`donators.php`'s transaction credit (decided: keep the path, redirect to `donators.php`).** A credit with a
`txnid` settles exactly one unprocessed `paylog` row, runs the `donation_adjustments` hook and redirects; 1.1.2
redirected to `paylog.php`. Evidence for the choice:

- The path is the only caller of the `donation_adjustments` module hook, a module API, and it works on a 1.1.2
  database that still holds unprocessed PayPal rows (the `paylog` table stays so 1.1.2 databases still upgrade).
- Its protections are already tested (`test_donation_admin_authority_http`: an unknown or settled transaction is
  refused, a replay is refused, the hook adjusts the points). Removing it would mean deleting that coverage and a
  module hook's only call site, a larger change than the one-line redirect.
- With `paylog.php` gone, the redirect pointed at a missing file. It now returns to `donators.php`, the page that
  made the credit. The test now also asserts that target (it fails with the old line:
  `'a:1:{s:12:"donators.php";b:1;}' != 'a:1:{s:10:"paylog.php";b:1;}'`).
- Manual credit without a `txnid` is unchanged and still tested end to end (points, debug log, notice mail,
  negative amounts, rollback, replay).

**Kept:** the `paylog` table in `lib/all_tables.php` and the installer, so 1.1.2 databases still upgrade; it is no
longer read except by the transaction credit. Likewise the `logdnet` and `logdnetbans` tables and the
`SU_EDIT_PAYLOG`, `SU_RAW_SQL` and `SU_VIEW_SOURCE` bits (stored in accounts; `SU_RAW_SQL` still gates the creature
AI script field).

### Records updated in the same commit

- `docs/ROUTE-INVENTORY-20261022.md`: seven rows removed (`logdnet.php`, `logdnet.php?op=list`, `payment.php`,
  `rawsql.php`, `source.php`, `paylog.php`, `paylog.php?op=process`); counts CLOSED 88, DISABLED 4, READ-ONLY 69,
  OPEN 0, total 161; 62 top-level pages. `test_route_inventory.py` is unchanged and passes; with `rawsql.php`
  restored it fails (`top-level pages`, `'rawsql.php'`).
- `docs/RESCAN-TASK3-20261023.md`: the `paylog.php` row leaves the SQL sweep's reviewed list. `SqlSweepTest` is
  unchanged; with the row still listed it fails ("Listed but no longer interpolated", `paylog.php`).
- Serialized-state recount (the method of the entry above, comments excluded; it reproduces 84 / 44 / 38 / 45 / 1 at
  `acb31f6`): **83 sites / 43 files / 38 serialize writers-checks / 44 ScalarState reads / one centralized
  unserialize**. The one site removed is `src/Security/PaymentInfo.php:21`. Recorded in `SERIALIZED-STATE-AUDIT.md`
  and `CLAUDE.md`.
- `docs/CORE-ROUTE-SECURITY.md` and `docs/SECURITY-STATUS.md`: appended notes.
- `phpstan-new.neon`: `lib/source_link.php` joins the level 6 gate.
- `ROADMAP.md`, `README.md`, `docs/LICENSING-AND-PROVENANCE.md`.

### Tests added, changed and removed

- Added: `tests/tooling/test_license_text.py` (5 tests, D); `tests/unit/SourceLinkTest.php` (3 tests: accepted and
  refused URLs, the default); `test_source_link_setting_and_removed_surface_links_http` (the footer link renders the
  stored URL escaped, `&` as `&amp;`; 15 refused values, from `javascript:` to quotes and user info, show the default;
  the page view stores nothing; `superuser.php` and `donators.php` show no removed link, even for staff with the
  payment log and raw SQL bits; the settings editor refuses four invalid values with 400 and stores a valid one);
  `test_disabled_endpoints.py::test_no_shipped_file_links_a_removed_file` (static).
- Changed: `test_disabled_endpoints.py`'s 410 test is now `test_removed_disabled_features_are_not_found`: each file
  is gone and GET and POST get 404 from a plain file server (PHP's built-in server would otherwise answer a missing
  top-level script with `index.php`; the test's router serves existing files as usual and 404s the rest, and the
  cron test still passes through it). `test_donation_admin_authority_http` also asserts the credit's new redirect.
- Removed with their code: `test_paylog_process_dates_post_only_http` and `PaymentInfoTest` (2 tests). They tested
  only `paylog.php` and `PaymentInfo`, which Jim's list removes.

Shown failing before the change (scratch copies of the tree): the source link test with the old `lib/pageparts.php`
(no link found: `[default] != []`), and with the old `lib/typed_editor.php` (the editor saved `javascript:alert(1)`,
`ftp://`, a quote and empty: `400 != 200`); the donation test with the old redirect (above); the two
`test_disabled_endpoints.py` tests with the six files and the old links restored.

### Candidates for Jim (found, not removed)

- `home.php`'s "Forgotten Password" nav links `create.php?op=forgot`, which answers 410 (legacy recovery stays
  disabled). The link is dead in the same way as "LoGD Net" was.
- `installer.php` (403 only) and the historical web installer it guarded: `lib/installer/installer_stage_0.php` to
  `_10.php`, `installer_stage_default.php`, `installer_functions.php` and `lib/pullurl.php` (used only by stage 1).
  Tests load some of them (`InstallerStage6Test`, `test_unbound_sql_files_are_not_endpoints_http`), and
  `fresh_install.php` loads `installer_sqlstatements.php` and `installer_default_settings.php`, which stay.
- Inert settings of the removed features: the "Paypal" and "LoGDnet Setup" declarations in
  `lib/configuration_descriptors.php` (the editable ones were already unset; `paypalcurrency`, `serverdesc` and
  `curltimeout` remain declared, and a title and a note per section) and their defaults in
  `installer_default_settings.php`; `logdnet_sanitize()` in `lib/sanitize.php` (no caller).
- The user editor's "Manage Payment Log" (`SU_EDIT_PAYLOG`) and "View source code" (`SU_VIEW_SOURCE`) flags now grant
  nothing; the bits would stay defined for stored accounts.
- `donators.php`'s transaction credit itself, if Jim prefers it gone: reachable only by a hand-typed `txnid` for a
  1.1.2 payment row (kept above, with the reason).
- `favicon.png` (from the cleanup above, still kept unsure).
- Not candidates, recorded: the 1.1.2 game text that mentions PayPal (`lib/petition/petition_faq1.php`, `lodge.php`)
  is the original authors' text and stays; the `{paypal}` replacement stays (see above).

### Verification

Local, on the working tree (supporting evidence only; CI on the published commit is the acceptance). PHP 8.5.12 with
MySQL 8.4, one disposable database per run:

- PHPUnit: 266 tests OK (was 265: `PaymentInfoTest`'s 2 removed, `SourceLinkTest`'s 3 added), on a fresh database and
  again in each of the four shard copies.
- All four HTTP shards through `scripts/tooling-shards.py --shards 4`, run in parallel copies: shard 1 53 tests OK
  (1416 s), shard 2 50 OK (1349 s), shard 3 57 OK (1368 s), shard 4 52 OK (1455 s).
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors (the new gate first reported
  `resurrection_valid_source_url` not found until `lib/source_link.php` joined its paths). `scripts/lint-php.py`: 382
  files, 0 failures.
- `composer validate --strict`, `composer install`, `composer audit` (no advisories).
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: PASS (634
  tracked files). `git diff --check`: clean.

Tracked files on `main`: 641 before, 634 after (12 removed; `NOTICE`, `docs/THIRD-PARTY-LICENSES.md`,
`lib/source_link.php`, `tests/unit/SourceLinkTest.php` and `tests/tooling/test_license_text.py` added).
