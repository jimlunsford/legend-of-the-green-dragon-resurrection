# Foundation checkpoint, 2026-09-13

Historical record of the earlier foundation task. Its publication blockers were subsequently resolved. Current publication and modernization evidence is recorded in [the modern checkpoint](MODERN-CORE-CHECKPOINT-20260913.md). The original observations below remain intact.

**PARTIAL FOUNDATION. Prepared and verified locally; GitHub publication and historical execution are BLOCKED. This is not a release.**

## Repository

Intended public URL: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection

The repository has **not been created by this task**. Connected GitHub lookup returned 404 at the final check. Public visibility, GitHub default branch, Issues/Actions settings, remote tag and successful CI cannot be claimed. Local branch is `main` and its origin is set to that intended destination only. No GitHub Release or Pages site was created.

The installed GitHub connection exposes repository reads and selected content/commit operations but no repository-create, repository-settings or annotated-tag-create operation. The workspace has no `gh` or authenticated Git push setup. An installed connection does not make those missing operations available. No write was attempted against either preservation repository, and no authentication restriction was bypassed.

### Local commits created

| Commit | Purpose |
|---|---|
| `5c7397ee10ea61cd96c071c78b928b6238dc3662` | Establish Resurrection identity, provenance and development policies |
| `ff222c94e2e91839288e6cf35b0779f19a398e85` | Historical manifest, verifier and baseline integrity CI |
| `d290ad6993db92ba3646f6fb45b7ee193a654032` | Isolated lab scaffold, descendant research and explicit characterization blocker ledger |
| Commit containing this checkpoint | Record final verified local state and external capability blockers |

**Final public `main` HEAD: unavailable, no repository was published.** The final local HEAD is the commit containing this checkpoint, resolvable with `git log -1 --format=%H -- docs/FOUNDATION-CHECKPOINT-20260913.md`. The delivery receipt outside Git records its literal SHA; a commit cannot embed its own final hash. Do not substitute the pre-checkpoint HEAD for that final commit.

Local annotated tag `historical-source-1.1.2` has tag-object SHA `51cab4fbe58a234651a3177a56289b18bc152b4d` and target `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`. It has not been pushed. Its message identifies the exact historical source and explicitly says it is not a Resurrection release. No `v1.1.2`, `v0.1.0`, other release tag or Resurrection version was assigned. Immutability is a project policy plus a verifier; no remote tag-protection setting has been applied.

## Historical baseline

| Evidence | Result |
|---|---|
| Core commit | `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2` |
| Core tree | `4013a0ccc5227e87cd7a22de00b7c322d7aa237c` |
| Historical files | 417, all manifest hashes PASS |
| Historical commits | All 11 commit objects present and byte-identical to preserved clone |
| Historical files on local main | All 417 still byte-identical; no normalization or application edit |
| LICENSE.txt SHA-256 | `4ddd8d05e2cd699466cbd7ddbf55677d9ad145e3a94dc7a5da171b93c973f40b` |
| Archive commit | `ef92e2df4e27ff29eb596b2b27ce3c9ab2c15187` |
| Archive tree | `63662057e5b23b3488d45083af94cc39d89c8f69` |
| Original repositories | No mutations made; connected final master HEAD/tree reads still match both audited pairs |

The new manifest describes the historical tag, not evolving main. The standalone verifier checks annotation, commit, tree, 417 hashes and complete 11-commit history. Future main may change deliberately without changing the historical reference.

## Licensing and provenance

Historical LICENSE.txt and TXT documentation remain unchanged. PROVENANCE.md and docs/LICENSING-AND-PROVENANCE.md preserve Eric Stevens/JT Traub/DragonPrime credits and Jim's preservation/modernization role. CC BY-NC-SA 2.0, NonCommercial, ShareAlike and the additional LoGD source expectations are documented. No replacement blanket license was applied.

The project is noncommercial. Individual module and asset rights remain unresolved where evidence is insufficient. Admission statuses do not imply rights clearance. **Zero modules or assets were imported beyond the exact historical core.** The icecaravan/icetown mismatch is documented, not altered.

All requested project documents exist: README.md, PROVENANCE.md, SECURITY.md, CONTRIBUTING.md and the versioning, licensing, module policy, security, compatibility, modernization principles, characterization plan and descendant research documents. Descendants were studied as references; none of their code was imported.

## CI and local verification

| Check/workflow | Evidence | Result |
|---|---|---|
| `baseline-integrity.yml` / Baseline integrity | Full-history/tag checkout, Python verifier, metadata/hygiene validator, negative tooling tests | Created locally; GitHub run ID: **none**; remote result: **NOT RUN** |
| Runner policy | `ubuntu-latest`, contents read-only, no stored checkout credentials | Static workflow inspection PASS; no runner consumed |
| Historical verifier | Exact tag/tree/417 files/11 commits | Local PASS |
| Project metadata and tracked-file hygiene | JSON validation, required documents, generated-config/key/token checks | Local PASS; not a full security certification |
| Tooling tests | Seven tests including tampered/missing/duplicate manifest, unselected/floating runtime rejection, byte-exact source export and overwrite refusal | Seven PASS |
| Workflow and Compose YAML | Parsed with installed YAML parser | Syntax parse PASS; Docker Compose semantics/build not executed |
| Historical characterization workflow | Reliability gate unmet | Intentionally not created |

A GitHub Actions passing run remains an acceptance criterion, not a claimed result. No PHP compatibility CI has been mislabeled as passing.

## Historical lab

| Item | Observed status |
|---|---|
| PHP combination executed | None |
| Database combination executed | None |
| Candidate PHP | 5.6.40 with mysql/mysqli, source-derived hypothesis only |
| Candidate DB | Start with a supported MariaDB candidate, record failures before older/configured comparisons; no selected version |
| Clean installer completed | No, not executed |
| Game rendered | No, not executed |
| Runtime image lock | Null digest placeholders, rejected by prepare-with-lock until reviewed inputs exist |
| Isolation design | No published ports; app loopback HTTP; internal network; IPv6 disabled; non-root/drop-all-capabilities; read-only root; tmpfs game/database; URL wrappers disabled; mail discard fixture |
| Execution blocker | No Docker/Podman/engine/socket/PHP; namespace isolation probes failed |
| Source preparation | Tested export/rehash of 417 Git blobs in temporary directories; no PHP execution |
| Credentials/data | No real credentials or user data; no synthetic account was created; no credential-bearing runtime file was committed |
| VPS/public service | No VPS access or changes; no public or private legacy game service launched |

See lab/historical-1.1.2/RUNTIME-INVESTIGATION.md for exact probe failures and open image/extension/DB UID/mode/isolation checks. The scaffold is not yet a demonstrated reproducible lab. No application source was patched to make it appear functional.

## Characterization

The attempt in this environment reached prerequisite investigation and fixture planning only. **Zero HTTP/SQL gameplay journeys were executed. Zero observed state transitions are claimed.** The JSON ledger retains null before/after values and zero passed gameplay assertions.

| Behavior | Status | Evidence |
|---|---|---|
| Fresh install, config/schema/seeds/admin/modules | BLOCKED | No isolated runtime; stage/seed/state capture contract written |
| Synthetic account creation | BLOCKED | Starting-stat/turn/location/race/specialty capture planned; zero accounts created |
| Login/session/navigation | BLOCKED | Safe state capture planned; no session captured |
| Village | BLOCKED | Core/module navigation assertions planned |
| Forest/fight selection/flee | BLOCKED | Turn and creature-state contract planned |
| Basic combat | BLOCKED | Before/after stats, buffs, rewards and observed randomness contract planned |
| Training/level advancement | BLOCKED | Eligibility/level/stat transition plan |
| New Day | BLOCKED | Time, turns, health, interest and daily-hook plan |
| Death | BLOCKED | Loss/persistence/alive/location plan |
| Graveyard/Ramius | BLOCKED | Favor/spirit/resurrection plan |
| Economy | BLOCKED | Deposit/withdrawal/interest/equipment/trade-in plan |
| Dragon progression | NOT ATTEMPTED | Controlled level-15 fixture plan; no seeded character or dragon fight |
| Bundled module hooks | PARTIAL static inventory; runtime BLOCKED | All 24 entrypoints/13 supports inventoried; eight proposed representative modules; none runtime characterized |

The installer actively recommends 59 module names; 23 are present in the core, 36 absent. The 24th bundled entrypoint, game_fivesix, is not in that active recommendation list. Filesystem presence is not activation evidence. No absent module was fetched or installed.

Randomness uses mt_rand through e_rand; the bootstrap's explicit seeding line is commented. No RNG or wall-clock override was executed. Future fixture seeding must be labeled and account for request boundaries and PHP-version differences. S01-S13 are historical defects, intentionally not desired behavior.

## Security

**Is Resurrection currently approved for public game hosting? No.**

All inherited findings remain open. Foundation tooling does not repair authentication, SQL injection, CSRF, sessions, authorization, unsafe administrative execution or remote trust boundaries. A public source repository, once created, will not authorize public game deployment. No release or production-ready claim is made.

## Exact next phase

**Finish this foundation's publication and private execution baseline before starting modernization.** Resume in a disposable workspace with authenticated GitHub repository creation/Git push (including annotated tags and workflow files) and a functioning Docker engine. No VPS is needed.

1. Restore the supplied Git bundle as `main`; run the historical verifier before publication. Retain the original objects/tag. If the destination now exists, inspect it before any push and do not overwrite unrelated work.
2. Create only jimlunsford/legend-of-the-green-dragon-resurrection as PUBLIC, without an initial README. Publish main and the annotated historical tag together, set main default, enable ordinary Issues/Actions, and verify no Release/Pages site. Use standard GitHub-hosted runners only.
3. Obtain a passing Baseline integrity run and record its actual run ID/final remote SHA. Verify the two preservation refs remain unchanged.
4. Resolve reviewed image digests, validate network isolation, execute the clean installer with bundled modules only and record exact blockers without source patches.
5. Capture the first real account → login → village → bank deposit/withdrawal before/after state journey. This is the smallest useful behavior baseline before nondeterministic combat.

The smallest *subsequent modernization slice* should be chosen from that observed installer/account/economy boundary, likely database transport/configuration compatibility with characterization tests. It is provisional until runtime evidence exists. Neither that slice nor broad PHP modernization was begun.
