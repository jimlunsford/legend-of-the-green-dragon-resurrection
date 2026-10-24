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

## Accepted, 2026-10-24 (license and dead weight)

"License and dead weight" is accepted at its own commit, `9fb11a65925bb5cf87267cc56f9c3ef808e23def`, read through the
GitHub Actions API by the next task:

- Modern core 40093585247: success. All 12 jobs success: for each leg (PHP 8.5 with MySQL 8.4, PHP 8.4 with MariaDB
  11.4) `checks`, `http (1)` to `http (4)` and `result`.
- Baseline integrity 40093585196: success.

## Dead weight, round 2, 2026-10-24

Maintenance named by Jim, not a phase. Starting head: `main` at `9fb11a65925bb5cf87267cc56f9c3ef808e23def`. The rule:
remove what points at, configures or grants a feature that no longer exists; never a working game feature. Untouched:
`LICENSE.txt`, `README.txt`, `NOTICE`, every copyright notice, the about-page text and `test_license_text.py`. The
transaction credit and `favicon.png` stay. Every removed file stays byte for byte at `historical-source-1.1.2`.

### 1. "Forgotten Password"

- `home.php`'s "Forgotten Password" nav linked `create.php?op=forgot`, which only answered 410 (legacy recovery stays
  disabled). The link is removed, and `op=forgot` leaves `create.php`'s op list, so it is refused as an unknown op
  (400) like any other. Password resets stay an admin action: the user editor's "New Password" field.
- `op=val` (1.1.2's email validation link) is kept: it still answers 410 with the same text. Nothing in the shipped
  code links it, but emails sent by a 1.1.2 server may (listed below for Jim).
- Proof: no other shipped file names `op=forgot` (`git grep`); the route inventory scan agrees.

### 2. PayPal and LoGDnet settings, `logdnet_sanitize()`

- Removed from `lib/configuration_descriptors.php`: the "Paypal" section (`paypalemail`, `paypalcurrency`,
  `paypalcountry-code`, `paypaltext`, its note) and the "LoGDnet Setup" section (`logdnet`, `serverdesc`,
  `logdnetserver`, `curltimeout`, its note); the unset list keeps only `defaultsuperuser`. `serverurl`, which 1.1.2
  listed under LoGDnet, is a working setting (system mail and expiration notices link it) and moves to "Game Setup".
- Removed from `lib/installer/installer_default_settings.php`: the same eight defaults, so a fresh install no longer
  stores them. `serverurl` and `sourceurl` stay.
- Stored rows in an existing database are not deleted. Nothing reads them, and the settings editor neither shows nor
  writes an undeclared setting (the new HTTP test saves the editor with six such rows present and they stay as
  stored).
- `logdnet_sanitize()` is removed from `lib/sanitize.php`: no caller in any tracked file.
- Proof: `git grep` finds no read of any of the eight settings outside the declarations and defaults; `curltimeout`
  was read only by `lib/pullurl.php` (removed under 4).

### 3. Admin flags that grant nothing

Hidden from the user editor (`user.php`'s superuser bitfield) and from the settings editor's `defaultsuperuser`
declaration (which the editor already never shows):

- "Manage Payment Log", `SU_EDIT_PAYLOG` (1024): the payment log is removed; nothing checks the bit.
- "View source code", `SU_VIEW_SOURCE` (1048576): the source viewer is removed; nothing checks the bit except
  `SU_DOESNT_GIVE_GROTTO`, which keeps using it.

Kept visible: "Execute Raw SQL*", `SU_RAW_SQL` (524288). The SQL console is removed, but the bit still grants the
creature editor's A.I. script field (`creatures.php`, `lib/content_editor.php`) and still hides its holder from the
Hall of Fame (`SU_HIDE_FROM_LEADERBOARD`), so it grants something.

Every constant keeps its name and value in `lib/constants.php`. No account changes: a save keeps each hidden bit
exactly as the account already holds it (`resurrection_user_editor_keep_hidden()` in `lib/user_editor.php`, applied
before the unchanged 1.1.2 superuser arithmetic), so an admin who holds one keeps it, nobody can clear it, and a
forged checkbox cannot grant it. Without that step a megauser saving an unchanged form would silently drop the hidden
bits, because the browser no longer posts their boxes.

### 4. The old web installer

- `common.php`'s "Game Installer" link and nav (no `dbconnect.php`) and "Installer (Admins only!)" link and nav
  ("Upgrade Needed") pointed at `installer.php`, which answers 403. They are removed, and each page names the
  command line in plain text: the not-installed page says the operator runs `php scripts/install.php` as
  `docs/MODERN-INSTALLATION.md` describes, and to restore a lost `dbconnect.php` from backup; the upgrade page says
  an administrator applies upgrades from the command line and points at the same guide (an older database needs a
  separately verified migration). Neither page shows a path, setting or account beyond the public repository's own
  file names.
- Which stages are still used: the CLI install (`scripts/install.php`, `cron.php`) loads only
  `lib/installer/fresh_install.php`, which loads `installer_sqlstatements.php`, `installer_default_settings.php` and
  `bundled_modules.php` (all kept). `installer.php` includes nothing. The tests load only `installer_stage_6.php`
  (`tests/fixtures/installer-stage6.php`, `InstallerStage6Test`), which needs only `LegacyConfig`. Kept:
  `installer.php` (the 403 answer) and `installer_stage_6.php`.
- Removed, each proven unused (no reference outside `docs/` except the other removed stages and the test path list
  updated below): `installer_stage_0.php` to `_5.php`, `_7.php` to `_10.php`, `installer_stage_default.php`,
  `installer_functions.php` (required only by stages 3, 4, 5, 8 and 9) and `lib/pullurl.php` (required only by stage
  1; `pullurl(` has no other caller). Its PHPStan baseline entry goes with it.

### 5. The rest of the last report's list

Everything else on the previous "Candidates for Jim" list is covered by 1 to 4, except the transaction credit and
`favicon.png`, which stay as Jim decided.

### Records updated in the same commit

- `docs/ROUTE-INVENTORY-20261022.md`: `create.php?op=forgot|val` becomes `create.php?op=val`;
  `installer.php?op=trycreate|confirm_overwrite` (its ops were the removed stages' links) is removed; counts CLOSED
  88, DISABLED 3, READ-ONLY 69, OPEN 0, total 160; 62 top-level pages, unchanged. A new section records why.
- `docs/RESCAN-TASK3-20261023.md`: the removed stages' eight rows (all unreachable) leave the SQL sweep's reviewed
  list; `SqlSweepTest` is unchanged and fails with them still listed.
- Serialized-state recount (same method, comments excluded): unchanged, 83 sites / 43 files / 38 serialize
  writers-checks / 44 ScalarState reads / one centralized unserialize. None of the removed code held a site.
  `SERIALIZED-STATE-AUDIT.md` and `CLAUDE.md` need no change.
- `docs/CORE-ROUTE-SECURITY.md` (appended), `docs/MODERN-INSTALLATION.md` (recovery wording), `ROADMAP.md`.

### Tests added and changed

- Added `test_route_inventory.py::test_no_page_links_a_missing_or_disabled_page` (static): no nav entry, href, form
  action, redirect or Location header in a shipped PHP file or skin points at a top-level page that does not exist,
  a missing module, or an entry the route inventory lists as DISABLED (each DISABLED row must say 403, 404 or 410).
  Before the change it fails with 19 dead links: `common.php` 4 (`installer.php`), `home.php` 1
  (`create.php?op=forgot`) and the installer stages 14 (`installer.php`, `?op=trycreate`, `?op=confirm_overwrite`).
  Three 1.1.2 links to pages 1.1.2 never shipped are listed in the test (`UNSHIPPED`), which also fails if one of
  them ships or its link goes, and are listed for Jim below. `test_link_scan_finds_known_links` checks the scan.
- Added `test_dead_admin_flags_and_settings_are_hidden_http`: the user editor (as megauser) shows neither hidden
  flag's box nor label and still shows "Execute Raw SQL"; an unchanged save writes nothing; adding or clearing every
  shown flag leaves the hidden bits as stored; forged hidden boxes grant nothing; the settings editor shows no PayPal
  or LoGDnet field and still shows `serverurl`, and saving it leaves six stored PayPal and LoGDnet rows untouched.
  Against the old tree it fails (`'superuser[1024]' unexpectedly found`); with the labels hidden but without the
  keep-hidden step it fails too (`'No fields were changed' not found`: the save dropped the bits).
- Added `UserEditorTest::testHiddenFlagsKeepTheirStoredBits` (the two bit values, the hidden mask, and the kept and
  refused bits for three kinds of editor). Against the old code the functions do not exist.
- Changed `test_create_login_rotate_render_logout`: `create.php?op=forgot` is now 400 (was 410), and a new assertion
  keeps `create.php?op=val` at 410. Against the old tree it fails (`400 != 410`).
- Changed `test_unbound_sql_files_are_not_endpoints_http`: the four installer files it requested no longer exist, so
  it asserts each is absent and gets 404, as it already did for `lib/mail.php`; `installer.php` is still 403.

### Found, not changed: the two installer pages fail to render

Both pages that name the installer (no `dbconnect.php`, and "Upgrade Needed") stop with a runtime error before any
text is shown, on `9fb11a6` as on this commit: `page_header()` runs `prepare_template()`, which reads the template
values `$y2`, `$z2` and `$lc` (the copyright and license footer) that `common.php` sets only later (lines 283 to 310,
after both pages), and the modern error handler turns the resulting undefined-variable warning into an exception
(`Runtime error in template.php:90`). Seen locally with PHP's built-in server for the first page and with the HTTP
fixture (installer version set to `1.1.1 Dragonprime Edition`) for the second. The text change above is correct but
cannot be seen until this is fixed. It is not fixed here: the fix moves the copyright footer's setup, which this
task does not name and which sits next to the copyright notices. Listed for Jim below.

### Candidates for Jim (found, not removed)

- The two installer pages' render failure (above). Suggested fix: compute the footer values before either early page,
  with a test that renders both pages.
- `create.php?op=val`: 410 only, linked from nothing shipped; old 1.1.2 emails may link it.
- `superuser.php`'s "Loot Editor" and `village.php`'s "Eye-catching Pavilion" navs appear only if `looteditor.php` or
  `pavilion.php` exists (neither is in 1.1.2); `village.php`'s hidden nav to `retitle.php` points at no file.
- "Execute Raw SQL*" now grants only the creature A.I. script field; its label could say so (a label change only).
- `installer_stage_6.php` is used only by its own test; the code path it tests (rewriting a pre-1.1.0
  `dbconnect.php` during a web upgrade) has no caller.
- `common.php`'s two `lib/smsnotify.php` hooks ("only really for Eric's server"): the file does not exist.
- The `{paypal}` skin placeholder in 20 skins and `lib/template.php`'s required-tag list, emptied by
  `lib/pageparts.php` (kept last round; still kept).
- Not candidates, recorded: the `paylog`, `logdnet` and `logdnetbans` tables and the installer's `UPDATE logdnet`
  upgrade statement stay so a 1.1.2 database still upgrades; the stored PayPal and LoGDnet setting rows stay.

### Verification

Local, on the working tree (supporting evidence only; CI on the published commit is the acceptance). PHP 8.5.12 with
MySQL 8.4, one disposable database per run:

- PHPUnit: 267 tests OK (was 266: `testHiddenFlagsKeepTheirStoredBits` added), in each of the four shard copies. It
  includes `FreshInstallTest` (the real CLI install on an empty database, and refusal of a populated, installed or
  upgrade-required one) and `InstallerStage6Test`.
- All four HTTP shards through `scripts/tooling-shards.py --shards 4`, run in parallel copies, each after its own
  fresh CLI install: shard 1 60 tests OK (1406 s), shard 2 50 OK (1318 s), shard 3 53 OK (1390 s), shard 4 52 OK
  (1282 s). A first attempt failed because it overlapped with stale runs left over from a Docker restart (same
  copies, same databases); it was discarded and all four shards were rerun clean.
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors. `scripts/lint-php.py`: 369 files, 0 failures.
- `composer validate --strict`, `composer install`, `composer audit` (no advisories).
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: PASS (621
  tracked files). `git diff --check`: clean.

Tracked files on `main`: 634 before, 621 after (13 removed, none added).

## Accepted, 2026-10-24 (dead weight, round 2)

"Dead weight, round 2" is accepted at its own commit, `2e0c948c8b03bba23b5a270a462c75a61dad431b`, read through the
GitHub Actions API by the next task:

- Modern core 40103233755: success. All 12 jobs success: for each leg (PHP 8.5 with MySQL 8.4, PHP 8.4 with MariaDB
  11.4) `checks`, `http (1)` to `http (4)` and `result`.
- Baseline integrity 40103233707: success.

## Installer pages, 2026-10-24

Fix named by Jim, not a phase. Starting head: `main` at `2e0c948c8b03bba23b5a270a462c75a61dad431b`. Round 2 found
that the two operator pages in `common.php` never showed their text.

### The error

- "The game has not yet been installed" (no `dbconnect.php`), reproduced with PHP's built-in server on a copy of the
  tree without `dbconnect.php`: `Uncaught ErrorException: Runtime error in template.php:90`, from
  `prepare_template()` called by `page_header()` at `common.php:120`. No page text is sent.
- "Database Connection Error" (a `dbconnect.php` whose server is unreachable): the same error. Not named in the
  brief; it has the same cause and the same fix.
- "Upgrade Needed" (the stored `installer_version` is not the code's), reproduced with the HTTP fixture (installer
  version set to `1.1.1 Dragonprime Edition`): the same error from `page_header()` at `common.php:240`. For a
  signed-in player, once that is fixed, a second one: `Uncaught TypeError: count(): Argument #1 ($value) must be of
  type Countable|array, null given` in `charstats()` (`lib/pageparts.php:573`), from `page_footer()`.
- Found while checking the footer, decided by Jim to fix here: every page a signed-in player sees showed `0` where the
  copyright line ("Game Design and Code: Copyright © 2002-2005, Eric Stevens & JT Traub, © 2006-2007, Dragonprime
  Development Team") belongs. Anonymous pages were right.

### The cause

- `prepare_template()` and `page_footer()` read `$y2` and `$z2` (whose XOR names the `copyright` variable), `$lc` (the
  license block) and `$x` (the 1.1.2 tamper check's message, or 0). `common.php` set `$cp`, `$l` at line 200 and the
  rest at lines 283 to 308, after all three early pages. With them unset, `$y2 ^ $z2` is 0 and `$$z` reads the
  undefined variable `${0}`; the modern error handler turns that warning into an exception.
- The signed-in upgrade page's stats panel counts `$companions` and walks `$session['bufflist']`, which `common.php`
  loads only after the page.
- `lib/template.php` replaces the copyright line with `$x` when `$x > ''`. Untampered, `$x` is the integer 0. PHP 5
  and 7 compared `0 > ''` as numbers (false); PHP 8 compares them as strings (`'0' > ''`, true), so the line became
  `0`.

### 1.1.2

`historical-source-1.1.2:common.php` has the same order: the pages at lines 105 ("not yet been installed"), 143 and 162
("Database Connection Error") and 227 ("Upgrade Needed"); `$cp` and `$l` at 189 to 190; `$y2`, `$z2` and the tamper
check at 304 to 328; `prepare_template()` at 331. On the PHP of its day the pages rendered with notices: the text
showed, but `$y2 ^ $z2` was 0, so `{copyright}` was never filled and was then cleared as a spare tag. The pages had
no copyright or license line. The signed-in upgrade page showed the session's buff list and no companions
(`count(null)` was 0). Every other page, signed in or not, showed the copyright line.

### The fix

- `common.php`, before the first early page: `$cp = $copyright;`, `$l = $license;`, `$y2` and `$z2` (moved there
  unchanged, same values), then `$x = 0;` and `$lc = $l;`. The tamper check (`if ($session['user']['superuser']==0)`
  ... `else` ...) stays where it was, unchanged, and still sets `$x` and `$lc` for every page that gets past the early
  pages. The copyright and license text are not changed.
- The "Upgrade Needed" page sets the buff list to empty only if the session holds none, and the companions to none,
  before its `page_footer()`: what 1.1.2 showed there.
- `lib/template.php`: the copyright line is replaced only when `$x` is a string (the tamper message), as on PHP 5
  and 7.

Why this changes nothing for any other page: `$copyright`, `$license`, `$y2` and `$z2` are constants assigned once
(the moved lines are byte for byte the old ones); no code between the new and old places reads or writes `$cp`,
`$l`, `$y2`, `$z2`, `$x`, `$lc` (checked in `common.php`, in the files it includes at top level in between, and for
`global`/`$GLOBALS` use anywhere: only `prepare_template()` and `popup_footer()` declare them); and both branches of
the unchanged tamper check overwrite `$x` and `$lc` before `prepare_template()`. So every page past the early ones
gets the same values as before. The two other changes act only on the upgrade page and on signed-in pages' copyright
line (the decided fix). The early pages show what any anonymous page shows in its header and footer (the MoTD and
petition links, the online list, the license, the copyright line, the skin credit, the source link, the version, the
page timing); their own text is unchanged from round 2.

The refresh link: with no account loaded, `common.php` fills every account column with its default, so
`restorepage` is a string ('' or the page asked for, which `NavigationState::validRestorePage()` allows only without
quotes, angle brackets, backslash or backtick), and the page escapes it with `htmlspecialchars(..., ENT_QUOTES)`. The
new HTTP test checks `home.php?a=1&b=2` (shown as `&amp;`) and a query with encoded markup (shown still encoded).

### Tests

- Added `tests/tooling/test_operator_pages.py` (no database): the not-installed page and the database error page,
  served from a symlinked copy of the tree, answer 200 with no error text, their own text (the CLI instruction; "Unable
  to connect to the database server."), the copyright line and the license once each, and the version. A static test
  keeps the footer values before the first `page_header()` call and the tamper check after the upgrade page. On the
  old tree all three fail (`Runtime error in template.php:90`; the values after the first page).
- Added `test_upgrade_needed_page_and_copyright_footer_http`: a signed-in page and an anonymous page carry the
  copyright line and no `0`; with a mismatched installer version, the anonymous and the signed-in upgrade page render
  with the text, the copyright line, the license, no `installer.php`, and the escaped refresh link. On the old tree it
  fails at the signed-in copyright line (`1 != 0`); with only the template fix, at the upgrade page (`Runtime error
  in template.php:90`); without the buff and companion defaults, at the signed-in upgrade page (`count(): Argument
  #1 ($value) must be of type Countable|array, null given`); without the template fix, at the signed-in copyright
  line again.
- `tests/tooling/test_license_text.py` is unchanged and passes.

### Verification

Local, on the working tree (supporting evidence only; CI on the published commit is the acceptance). PHP 8.5.12 with
MySQL 8.4, one disposable database per run:

- All four HTTP shards through `scripts/tooling-shards.py --shards 4` (`common.php` runs on every page), in parallel
  copies, each after PHPUnit's fresh CLI install: shard 1 61 tests OK (1200 s), shard 2 51 OK (1344 s), shard 3 54
  OK (1194 s), shard 4 53 OK (1278 s).
- PHPUnit: 267 tests OK in each copy. `test_license_text.py`: 5 tests OK.
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors. `scripts/lint-php.py`: 369 files, 0 failures.
- `composer validate --strict`, `composer install`, `composer audit` (no advisories).
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: PASS (622
  tracked files). `git diff --check`: clean.

## Baseline on the runner's PHP, 2026-10-24

Fix named by Jim, not a phase. Starting head: `main` at `754bf14ed01970858be6afd5c2f4b62e9147c22f` ("Installer
pages").

### The failed run

`754bf14`'s own runs, read through the GitHub Actions API, stay on record:

- Modern core 40109324440: success, all 12 jobs.
- Baseline integrity 40109324481: failure. Job `provenance` (120917544779), step 5 "Test integrity rejection cases"
  (`python3 -m unittest discover -s tests/tooling -v`, 15:57:35 to 15:57:38Z): 28 tests, 1 failure, 1 skipped.
  `test_operator_pages.OperatorPageTests.test_database_connection_error_page_shows_its_text_and_footer` failed in
  `assertRendered` at `assertNotRegex(body, ERROR)`: expected no error text, the page held `Fatal error: Uncaught
  Error: Class "Pdo\Mysql" not found in lib/dbwrapper_pdo.php on line 26` (from `db_connect()`, `common.php:151`).
  The job log was readable this time; reproduced locally with PHP 8.3.6 and no database: the same failure, and the
  other 27 tests as in the job.

### The cause

The Baseline job installs no PHP, so the test ran on the runner's system PHP, 8.3. `Pdo\Mysql` exists from PHP 8.4,
and the project supports only PHP 8.4 and 8.5 (`composer.json`: `"php": "~8.4.0 || ~8.5.0"`). The product is not at
fault; the test ran PHP on a version the project does not support. The tooling tests that ran PHP skipped only when
there was no PHP at all. `test_disabled_endpoints.py` shares that gap (it passed on 8.3, by chance of what it calls).
`test_web_application.py` runs PHP only with the installed CI database and skips without it, so Baseline never runs
its PHP.

### The fix

- `tests/tooling/php_runtime.py`: `supported_php()` returns the PHP binary when its version is at least the lowest
  that `composer.json`'s constraint allows (read from the file, 8.4 now). Otherwise it skips, with the reason
  (`PHP 8.3 at /usr/bin/php is older than the supported minimum, PHP 8.4 (composer.json); these tests run in Modern
  core`). With `RESURRECTION_REQUIRE_PHP=1` the same case, or no PHP, is an error instead. A PHP whose version cannot
  be read is an error either way, never a skip.
- `test_operator_pages.py` and `test_disabled_endpoints.py` get their PHP from it. The two operator page tests
  check per test, so the static order test still runs everywhere; `test_disabled_endpoints.py`'s static link scan
  moved to its own class, `ShippedLinkTests`, for the same reason (the test itself is unchanged). On a supported PHP
  every test runs exactly as before.
- Modern core's `http` jobs (the ones that run the tooling shards on PHP 8.4 and 8.5) set
  `RESURRECTION_REQUIRE_PHP: '1'`, so a skip of these tests fails the job with its reason. `scripts/tooling-shards.py`
  already fails a shard with any skipped test; the variable makes the failure name the cause and holds outside the
  shard runner too.
- Baseline integrity is unchanged: still provenance, no PHP installed, the same command.
- `tests/tooling/test_php_runtime.py` (no PHP needed: fake binaries print a version): the minimum comes from
  `composer.json`; an older PHP skips with the reason, and with the variable set to `1` (only `1`) fails; 8.4, 8.5
  and newer run with or without it; no PHP skips, or fails when required; an unreadable version fails; Modern core's
  `http` job sets the variable once and installs the matrix PHP (8.4 and 8.5), Baseline neither installs PHP nor sets
  it; and no other tooling test finds PHP except through the guard (`test_web_application.py` excepted, as above).

Before the fix: on `754bf14`'s tree the new test file errors on import (no `php_runtime`); with the guard but
without the workflow variable, `test_modern_core_requires_php_and_baseline_does_not` fails (`1 != 0`); with
`test_operator_pages.py` alone reverted, PHP 8.3 fails the database error page again and
`test_tooling_tests_find_php_only_through_the_guard` fails (`['test_operator_pages.py']`).

### Verification

Local, on the working tree (supporting evidence only; CI is the acceptance):

- The Baseline step's command, `python3 -m unittest discover -s tests/tooling -v`, no database:
  - PHP 8.3.6: OK, 32 tests, 4 skipped (the `DisabledEndpointTests` class and the two operator pages, each with the
    reason above; the web tests for their database).
  - PHP 8.5.12: OK, 34 tests, 1 skipped (the web tests for their database).
  - PHP 8.3.6 with `RESURRECTION_REQUIRE_PHP=1`: exit 1, 3 errors (the same three, `RuntimeError: ...;
    RESURRECTION_REQUIRE_PHP=1 requires them to run`) instead of skips.
- The changed and new tests fall in all four shards, so all four ran, PHP 8.5.12 with MySQL 8.4 and
  `RESURRECTION_REQUIRE_PHP=1` as in Modern core, each after PHPUnit's fresh install (267 tests OK each): shard 1
  62 tests OK (1220 s), shard 2 54 OK (1307 s), shard 3 54 OK (1232 s), shard 4 55 OK (1390 s), 0 skipped.
  PHP 8.4 is not available locally; Modern core's 8.4 leg covers it.
- PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors. `scripts/lint-php.py`: 369 files, 0 failures.
- `scripts/verify-historical-baseline.py`: PASS (417 files, 11 commits). `scripts/validate-project.py`: PASS.
  `git diff --check`: clean.

### Acceptance of `754bf14`

`754bf14` is not accepted on its own runs: its Baseline integrity run failed. It is accepted only through this fix,
its first descendant, if both Modern core legs and Baseline integrity succeed on this commit; the next task records
those runs.
