# Resurrection versioning

Current version: `1.0.0-dev`

Resurrection has its own version numbers, `MAJOR.MINOR.PATCH`. They do not continue Legend of the Green Dragon 1.1.2's: the game it is built from stays "1.1.2 Dragonprime Edition", which the about page and the footer still credit.

## The number

`src/Version.php` sets the version, once. `project.json` and the line above must say the same; `tests/tooling/test_version.py` and `scripts/validate-project.py` fail if they disagree or if the version is not a valid semantic version in one of the forms below. Change it only when a task or release decision says to.

The footer shows "Version: Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition", and the home page shows the same after "Game server running version:". The version is only shown. Whether a database needs an upgrade is decided by the upgrade steps it has applied, never by this string (`docs/MODERN-INSTALLATION.md`, "Upgrading").

## What each part means

- **MAJOR**: a change that breaks operators or module authors: a removed or changed setting, command or configuration value an operator relies on; a changed module API, hook or table that a module written for the previous version would break on.
- **MINOR**: a feature or improvement that upgrades cleanly from the previous version, with the upgrade command and no other operator action.
- **PATCH**: fixes only. No new feature and no changed behavior beyond the fix.

Gameplay keeps 1.1.2's behavior in every version (`docs/MODERNIZATION-PRINCIPLES.md`); a version number never licenses a rebalance.

## Development, test builds and releases

- **`1.0.0-dev`** is the version while development leads toward the first release. `-dev` sorts before every `-rc` of the same number, so a development build never looks newer than a test build of its own release. It stays `1.0.0-dev` until the first test build.
- **`1.0.0-rc.N`** is a test build of 1.0.0 (`rc.1`, `rc.2`, ...). Each one is a candidate for the release.
- **`1.0.0`** is the first release. After it, development moves to the next number with `-dev` (for example `1.1.0-dev`).

A test build or release gets a tag `vMAJOR.MINOR.PATCH` or `vMAJOR.MINOR.PATCH-rc.N` (for example `v1.0.0-rc.1`), only on an explicit release decision. No such tag exists yet. `-dev` versions are never tagged.

## Upgrades

- Every release upgrades cleanly from the one before it: `php scripts/upgrade.php` brings a database installed by the previous release to the new release's state, the same schema and seed data as a fresh install of the new release.
- Upgrades go one release at a time. Supported is an upgrade from the release directly before; skipping releases is not tested and not supported. An operator further behind upgrades through each release in turn.
- A database newer than the code (a downgrade) is refused, by the game and by the upgrade command. The way back is the backup taken before the upgrade.
- No 1.1.2 database is ever upgraded or migrated. Every Resurrection install starts fresh.

The upgrade steps, how they are written and how the upgrade path is tested are in `upgrades/README.md` and `docs/PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`. The release upgrade check (`scripts/release-upgrade-check.py`, in Modern core) installs the latest release tag and upgrades it to the commit under test; until the first tag it reports that there is no release yet.

## The historical tag

`historical-source-1.1.2` is an annotated preservation marker for the exact imported 1.1.2 source. It is not a release and not a Resurrection version: it must not move, must not receive a GitHub Release, and does not match the release tag pattern above, so the release upgrade check never picks it.
