# Module admission policy

Which modules ship with Resurrection, and what admitting another one would take. How to write or port a module to the rules every page follows now (hooks, presentation, the Content Security Policy, the entry guard, POST and CSRF, secrets, SQL, versions and upgrades) is in [`MODULE-AUTHOR-GUIDE.md`](MODULE-AUTHOR-GUIDE.md).

## What ships

The 24 modules bundled with LoGD 1.1.2, and no others.

- All 24 are certified: install, activate, deactivate, reactivate, reinstall, settings and preferences round trips, their hooks, events and gameplay changes, and all 24 active together ([`BUNDLED-MODULE-CERTIFICATION.md`](BUNDLED-MODULE-CERTIFICATION.md), 24 PASS, Phase 3).
- A fresh install activates the 23 that 1.1.2's installer recommended. `game_fivesix`, the one bundled module not on that list, is installed and inactive; `--modules=none` installs all 24 inactive ([`PHASE4-TASK1B-FRESH-INSTALL-20261029.md`](PHASE4-TASK1B-FRESH-INSTALL-20261029.md)).
- Only bundled modules install: the module manager installs a module only if it is in `resurrection_bundled_modules()`, and any other installed module may only be deactivated. Every module manager operation is a POST with CSRF and a one-use intent, and dependencies and activation are enforced ([`MODULE-MANAGER-AUTHORITY-20261017.md`](MODULE-MANAGER-AUTHORITY-20261017.md)).
- Module code is trusted PHP: it runs with the game's full rights over the database and the server. The certification covers these 24 only.

## No other module is admitted

Resurrection is finished at 1.0.0, and after it takes bug fixes only (Jim's decision of 2026-11-13). So the 24 are final: no module is added to a release. Archive-only modules and the Cities module are never imported, and nothing comes from the separate module archive (`PROVENANCE.md`, "Module archive") to raise the module count: no advertising, mightyblogs, charrestore, TopWebGames or payment modules, remote integrations or developer utilities.

A host may change their own copy under the license (`LICENSE.txt`). A module added to a copy that way has had none of the review below; whoever adds it takes that on.

## What admitting a module would take

For the record, and for anyone reviewing a module for their own copy. File presence in the historical archive is not Resurrection support: the archive preserves historical material, and Resurrection curates an implementation. Review the module for provenance, licensing, author attribution, dependencies, runtime compatibility, database behavior, security, gameplay behavior and fit with the release, with every support file and asset, never the entrypoint alone. Then certify it as the 24 were, register it and meet the rules of the author guide ([`MODULE-AUTHOR-GUIDE.md`](MODULE-AUTHOR-GUIDE.md), section 9), and record every behavior it adds or changes in [`CHANGES-FROM-1.1.2.md`](CHANGES-FROM-1.1.2.md).

## History (as recorded)

The policy as it stood before Phase 3 certified the 24 and before the finish line, kept as written below. Its lab (APPROVED FOR CHARACTERIZATION) was never run: the project read 1.1.2's behavior from the source at the historical tag ([`CHARACTERIZATION-PLAN.md`](CHARACTERIZATION-PLAN.md) is the plan, kept as a record).

How to write or port a module to the rules every page follows now (hooks, presentation, the Content Security Policy, the entry guard, POST and CSRF, secrets, SQL, versions and upgrades) is in [`MODULE-AUTHOR-GUIDE.md`](MODULE-AUTHOR-GUIDE.md). This policy decides which modules ship.

File presence in the historical archive is not Resurrection support. The archive preserves historical material; Resurrection curates an implementation.

Review every proposed module for provenance, licensing, author attribution, dependencies, runtime compatibility, database behavior, security, gameplay behavior and fit with release goals. Include support files and assets. Never clear a module based on its entrypoint alone.

### Status vocabulary

Use a set of explicit review states because a module may need more than one kind of work:

- HISTORICAL: preserved reference, no support claim.
- UNDER REVIEW: evaluation in progress.
- APPROVED FOR CHARACTERIZATION: permitted only in the isolated synthetic lab.
- COMPATIBILITY WORK REQUIRED: runtime/API/schema changes needed.
- SECURITY WORK REQUIRED: unsafe boundaries unresolved.
- RIGHTS REVIEW REQUIRED: license/provenance questions unresolved.
- DEFERRED: outside the current milestone.
- APPROVED FOR RESURRECTION: a specific version has completed rights, behavior, compatibility and security gates.
- RETIRED / OBSOLETE INTEGRATION: retained historically but not part of current product scope.

Lab approval does not imply public-hosting or release approval. Record tested core/module versions, required assets/tables, result evidence and remaining work. Activation requires separate deliberate selection.

### Foundation scope

Only the 24 entrypoints and 13 supporting module files already in the exact core are present. The initial representative candidates are listed in `tests/characterization/module-set.json`. Most remain historical-only; lab observations must identify which were actually installed/activated/exercised.

Do not import advertising, mightyblogs, charrestore, TopWebGames, payment modules, remote integrations or developer utilities from the separate archive just to increase module coverage.

### Confirmed mismatch

Archived `icecaravan` requires `icetown >= 1.3`, but archived `icetown` reports `1.0`. Do not change its version string to satisfy the dependency checker. Investigate original revision, required API and behavior. Neither module is admitted in this foundation.

### Modern engineering phase

The same 24 bundled entrypoints remain. Phase 3 certified all 24: install, activate, deactivate, reactivate, reinstall, settings and preferences round trips, their hooks, events and gameplay changes, and all 24 active together (`BUNDLED-MODULE-CERTIFICATION.md`, 24 PASS). Every module manager operation is a POST with CSRF and a one-use intent, only bundled modules install, and dependencies and activation are enforced (`MODULE-MANAGER-AUTHORITY-20261017.md`). A fresh install activates the 23 recommended modules by default (`PHASE4-TASK1B-FRESH-INSTALL-20261029.md`). Module code is still trusted PHP: the certification covers these 24 only, and any other module needs its own admission review under this policy. The archived module set remains outside the project; none is imported.
