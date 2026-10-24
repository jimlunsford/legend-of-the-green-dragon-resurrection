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
