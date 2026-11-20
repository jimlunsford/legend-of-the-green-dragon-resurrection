# Provenance

## Core source

- Repository: https://github.com/jimlunsford/lotgd
- Preserved HEAD: `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`
- Root tree: `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`
- Files: 417; PHP files: 259; bundled module entrypoints: 24.
- Identity: **Legend of the Green Dragon 1.1.2 Dragonprime Edition with bundled maintenance fixes**.
- New-project baseline tag: `historical-source-1.1.2`.

The complete 11-commit core ancestry is preserved without rewriting original commit objects. Those 2019 GitHub uploads are Jim Lunsford's preservation activity, not original LoGD development history. `BUG FIXES.txt` (kept at the historical tag; removed from `main` on 2026-10-24, see `docs/UNNECESSARY-FILES-20261024.md`) records maintenance changes already present when uploaded; their precise dates and authorship are not established by the upload history.

Original authors include Eric Stevens and JT Traub. Preserve DragonPrime Development Team and individual source/module credits. Jim is the preservation owner and Resurrection maintainer, not an original LoGD author. No official continuation or endorsement is claimed.

The source is not authenticated against a canonical upstream release archive. Its internal version/schema/upgrade markers establish the 1.1.2 line; the maintenance record qualifies any claim of a pristine release.

## Module archive

- Repository: https://github.com/jimlunsford/lotgd-modules
- Preserved HEAD: `ef92e2df4e27ff29eb596b2b27ce3c9ab2c15187`
- Root tree: `63662057e5b23b3488d45083af94cc39d89c8f69`
- 243 files; 205 PHP files; 187 module entrypoints; 18 supporting PHP files; 37 avatar GIFs.

All 37 module-tree files in core match the archive byte-for-byte. The archive adds 205 module-tree files and contains all 59 active installer recommendations. It is historical reference material: no archive module or asset is imported, and Resurrection admits no module beyond the 24 bundled ones ([docs/MODULE-POLICY.md](docs/MODULE-POLICY.md)).

## Reconstruction limits

Neither repository supplies an original server database, `dbconnect.php`, historical player data, module activation state or exact historical configuration. Resurrection continues the software; it does not claim to recover Jim's exact old server, its players or its data.

## Preservation contract

Neither source repository is modified by Resurrection work. Do not push branches/tags, issues, settings, releases or changes to them. Do not move/delete the new-project historical tag. Resurrection's work happens in this repository, on `main`. Keep the baseline manifest tied to the historical tag, not evolving main.

See [historical-core-manifest.sha256](docs/provenance/historical-core-manifest.sha256) and [baseline.json](docs/provenance/baseline.json). The verifier (`scripts/verify-historical-baseline.py`, run by both CI workflows) checks commit, tree, file count, object type and each historical blob. `main` evolves; the tag does not.
