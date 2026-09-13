# Module admission policy

File presence in the historical archive is not Resurrection support. The archive preserves historical material; Resurrection curates an implementation.

Review every proposed module for provenance, licensing, author attribution, dependencies, runtime compatibility, database behavior, security, gameplay behavior and fit with release goals. Include support files and assets. Never clear a module based on its entrypoint alone.

## Status vocabulary

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

## Foundation scope

Only the 24 entrypoints and 13 supporting module files already in the exact core are present. The initial representative candidates are listed in `tests/characterization/module-set.json`. Most remain historical-only; lab observations must identify which were actually installed/activated/exercised.

Do not import advertising, mightyblogs, charrestore, TopWebGames, payment modules, remote integrations or developer utilities from the separate archive just to increase module coverage.

## Confirmed mismatch

Archived `icecaravan` requires `icetown >= 1.3`, but archived `icetown` reports `1.0`. Do not change its version string to satisfy the dependency checker. Investigate original revision, required API and behavior. Neither module is admitted in this foundation.
