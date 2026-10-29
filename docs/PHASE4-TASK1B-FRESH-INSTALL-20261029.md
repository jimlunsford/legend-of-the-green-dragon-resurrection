# Phase 4 task 1B: a fresh install ready to play, 2026-10-29

Starting head `b38bc21fadb8a5dd15c990aca74a1760c9397413` (task 1A). Scope: `scripts/install.php`, the fresh-install
service (`lib/installer/fresh_install.php`, `lib/installer/bundled_modules.php`), the config step
(`src/Configuration/ConfigFile.php`, `src/Installation/InstallRefused.php`) and one extraction in `lib/modules.php`.
`common.php`'s "Upgrade Needed" page is unchanged (task 2 wires it to the upgrade system). No 1.1.2 migration, no
version string change, no archive module.

## Task 1A acceptance

`b38bc21`: Modern core 40119918321, all 12 jobs SUCCESS (both legs' `checks`, eight `http` shards and both `result`
jobs); Baseline integrity 40119918310 SUCCESS. Read through the GitHub Actions API on 2026-10-29.

## 1. Modules activated at install

### The list

1.1.2's `installer.php` (lines 36 to 97 at `historical-source-1.1.2`) defines `$recommended_modules`: 59 names, with
`cities` commented out. Limited to the 24 bundled modules (`resurrection_bundled_modules()`), that is 23:

| On 1.1.2's list, activated by default (23) | Not on it, installed and inactive (1) |
|---|---|
| `cedrikspotions`, `crazyaudrey`, `dag`, `darkhorse`, `drinks`, `fairy`, `findgem`, `findgold`, `foilwench`, `game_dice`, `game_stones`, `glowingstream`, `goldmine`, `lovers`, `outhouse`, `racedwarf`, `raceelf`, `racehuman`, `racetroll`, `sethsong`, `specialtydarkarts`, `specialtymysticpower`, `specialtythiefskills` | `game_fivesix` |

The other 36 names on 1.1.2's list (`abigail`, `breakin`, `calendar`, ... `waterfall`) are archive modules that
Resurrection does not ship; nothing imports them. `resurrection_recommended_modules()` holds the 23, and
`tests/tooling/test_recommended_modules.py` derives them from the tag's `installer.php` on every run, so the list
cannot drift by hand.

### Default, with an explicit opt-out

Decision: activation is the default (`--modules=recommended`), and `--modules=none` keeps the earlier behavior (all 24
installed and inactive). Evidence from 1.1.2:

- `lib/installer/installer_stage_8.php` lines 202 to 217 define `chooseRecommendedModules()`, which ticks the
  "Activated" radio of every recommended module, and call it on page load unless the install is an upgrade
  (`if (!$session['dbinfo']['upgrade'])`, line 214). A fresh 1.1.2 install therefore activated the list unless the
  operator unticked modules.
- The same page, line 18: "If you are not familiar with Legend of the Green Dragon, and how the game is played, it is
  probably wisest to choose the default set of modules to be installed."
- `lib/installer/installer_stage_9.php` lines 110 to 117 then ran `activate_module()` for each ticked module.

So a fresh install that is playable at once matches 1.1.2. The opt-out matches the operator's 1.1.2 choice to untick
everything. `tests/tooling/test_recommended_modules.py` also checks the tag's stage 8 still calls the function on a
fresh install.

### How activation runs

- `activate_module()` (`lib/modules.php`) is split: its request checks (module manager privilege, POST, CSRF) stay in
  `activate_module()`, and the rest moves unchanged into `resurrection_activate_module()`: installed check, requirement
  check, `UPDATE ... SET active=1`, runtime and cache invalidation, false when nothing changed. The module manager's
  behavior is unchanged; the install, which has no request, calls the second function.
- `resurrection_activate_installed_modules()` (`lib/installer/bundled_modules.php`) refuses to run outside an open
  transaction and throws on the first module that does not activate.
- `resurrection_fresh_install()` now takes the activation list (only bundled names, each once, checked before any DDL).
  It installs the 24 inactive as before, then opens the final transaction: the activations, the administrator and the
  completion marker (`resurrection_install` = `complete`) commit together, inside the install's advisory lock. A
  failure rolls back every activation, so no module is active, no administrator exists and the install is not
  recorded complete. The failure output names the phase (`activate-module:<name>`).
- 1.1.2 instead printed "Failed!" for a module that did not activate and carried on, leaving whatever had activated
  (stage 9, lines 110 to 117). All or nothing is the stricter reading of the brief ("a failure leaves no half-active
  state"); the module manager can still change any module afterwards.
- The output reports `modules_active` from the database after commit (it was a constant 0) and the choice made.

## 2. The guided config step

`php scripts/install.php --write-config [--allow-served-path] /absolute/path` writes the configuration file from
DB_HOST, DB_USER, DB_PASS, DB_NAME and DB_PREFIX in the environment, through `LegacyConfig::render()`, with the data
cache off, then installs with that file. The existing mode, `php scripts/install.php [CONFIG]`, is unchanged.

| | 1.1.2 (`installer_stage_6.php`) | Now |
|---|---|---|
| Where | Always `dbconnect.php` in the site root, the served directory | An absolute path the operator gives; inside the served directory (the repository root and below, links resolved) only with `--allow-served-path` |
| Values | Pasted between single quotes (a quote in a password broke the file or injected PHP) | `LegacyConfig::render()` (`var_export`); DB_PREFIX letters, digits and `_` only; DB_HOST, DB_USER, DB_NAME required non-empty |
| Existing file | Skipped writing when `dbconnect.php` existed; otherwise `fopen(..., "w+")` | Never overwritten: an existing file or link is refused before anything happens, and the write itself is an exclusive create, so a file that appears meanwhile is refused too |
| Permissions | The web server's default | Created 0600 (owner only) from the start, under a 077 umask, then `chmod 0600` |
| On failure | The page printed the whole file, password included | Fixed messages only; a failed write removes the partial file |
| Before writing | Nothing checked | The path, the values, the administrator credentials, and the database (reachable and empty) are checked first; any refusal writes nothing |
| Proof it works | None | The install then connects with the written file |

No message, output or error names a credential; the CLI's refusals are fixed text (`InstallRefused`) and other
failures keep the generic phase line.

## 3. A 1.1.2 database: unsupported

`resurrection_install_state()` reported a recognizable 1.1.2 database (settings `installer_version` starting `1.` and
an accounts table with `acctid`, `login`, `password`) as `upgrade-required`, and the command printed only the generic
"Installation refused or failed (preflight)" line. The state is now `unsupported-1.1.2`, and the command prints:

> Installation refused. This database holds a Legend of the Green Dragon 1.1.2 game, which is unsupported: Resurrection
> does not upgrade or migrate a 1.1.2 database. The only path is a fresh install into an empty database. Nothing was
> changed.

The check still runs under the install's lock before any change, and with `--write-config` it also runs before the
file is written. The other refusals now say why too: a completed install ("Fresh installation is locked") and any
other non-empty database ("A fresh install needs an empty database"). 1.1.2's own installer offered an in-place
upgrade here; Resurrection installs fresh only (ROADMAP.md, Phase 4 decisions).

## Tests

| Test | What it proves | Before the change (`b38bc21`) |
|---|---|---|
| `tests/unit/FreshInstallTest.php` (changed) | Unrelated and 1.1.2 databases refused with their messages, every table and row unchanged, `--write-config` writing nothing for a 1.1.2 database; unknown options refused; a served path refused and nothing written; the default install with `--write-config` ends with 38 tables, 24 installed, exactly the 23 recommended active and `game_fivesix` inactive, the file 0600 and loading back to the environment's values, no credential in any output; a second write refused with the file unchanged; `--allow-served-path` passes the path check; a failed activation inside the transaction rolls back; then the disposable database is cleared and `--modules=none` installs the inactive state the later tests start from, followed by the existing account and module DDL checks | Fails at its first new assertion: the populated database's stderr was the generic line. Run by hand on `b38bc21`: the 1.1.2 database got the generic line, `--write-config` was read as a config path and failed with no file, and the default install reported `"modules_active":0` with 0 rows active |
| `tests/unit/ConfigFileTest.php` (new) | Environment values required and the prefix restricted; the target must be absolute, in an existing directory, new (a dangling link counts as existing), outside the served directory (also through `..` and a link) unless allowed; the write is 0600 under umask 0, loads back exactly, and refuses a file or link that appeared after the check | Errors: class `ConfigFile` not found |
| `tests/tooling/test_recommended_modules.py` (new) | `resurrection_recommended_modules()` is exactly the bundled modules on the tag's list, in bundled order, and only `game_fivesix` is left out; the tag's stage 8 selects the list on a fresh install | Fails: `resurrection_recommended_modules not found` |
| `test_web_application.py`: `test_recommended_install_new_player_reaches_village_and_forest` (new) | With exactly the recommended set active (as the install leaves it), a brand-new player creates an account, logs in, is offered all four races and all three specialties at the first New Day, picks Elf and Thieving Skills, reaches the village and the forest; module states are restored | Fails: `resurrection_recommended_modules()` is undefined |

The HTTP suite's starting state is unchanged (24 installed, none active): `FreshInstallTest` ends with the
`--modules=none` install, and `ModuleCertificationTest` and the web tests still begin from it.

## Local verification

Supporting evidence only; CI is the acceptance. PHP 8.5.12 with MySQL 8.4.13 in Docker.

- PHPUnit, full suite on a fresh database: OK, 270 tests, 141146 assertions (267 tests before this task).
- All four HTTP shards in parallel, each after PHPUnit on its own fresh database, with `RESURRECTION_REQUIRE_PHP=1`:
  shard 1 ran 57, shard 2 ran 54, shard 3 ran 61, shard 4 ran 56 (each count includes its account-setup
  prerequisite), all with 0 failures, 0 errors, 0 skipped; 17.1 to 19.7 minutes each. The new HTTP test ran in
  shard 4.
- Both PHPStan gates (`phpstan.neon`, `phpstan-new.neon`): no errors. PHP lint: 372 files, 0 failures.
- `composer validate --strict`: valid. `composer audit`: no advisories.
- `scripts/verify-historical-baseline.py`: 417 files, 11 historical commits. `scripts/validate-project.py`: PASS.
  `git diff --check`: clean.

## Not changed

- `common.php`'s "Upgrade Needed" page (task 2).
- `docs/VERSIONING.md` and every version string.
- Production hosting gates: `SECURITY-STATUS.md` S04 and S13 were updated from the Phase 3 evidence they cite, keeping
  what is still true; public hosting remains **NO**.

## Acceptance (appended 2026-10-29, task 2)

`504b5aa`: Modern core 40800637109, all 12 jobs SUCCESS (both legs' `checks`, eight `http` shards and both `result`
jobs, 01:28 to 01:50 UTC); Baseline integrity 40800637058 SUCCESS. Read through the GitHub Actions API on 2026-10-29.
`ROADMAP.md` marks 1B done in the task 2 commit.
