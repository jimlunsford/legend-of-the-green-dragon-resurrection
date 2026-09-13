# Historical characterization plan

## Status and evidence discipline

No runtime journey is claimed complete until a private execution records relevant before/after state. The current result ledger is `tests/characterization/results-20260913.json`. A source-derived expectation is a hypothesis, not an observed PASS. Environment failure is BLOCKED, not a passing skipped game test.

Only exact historical tag bytes and its 24 bundled modules may enter the first lab. No extra archive module is copied. The installer recommends 59 modules, of which 23 are supplied in core; the remaining bundled module is game_fivesix. Missing recommendation files must be documented, not fetched automatically. Observe the installer's actual selected/installed/active state instead of asserting all recommendations activate by default.

## Research controls

Run disposable containers with no published ports and no host network. The PHP service binds loopback inside its container. Database access uses a dedicated internal Docker network; Internet egress is disabled at that network. Disable PHP URL wrappers and route outbound mail to a local discard fixture. Never supply production credentials or mount other applications, SSH homes or the Docker socket inside the application.

Extract source from `historical-source-1.1.2`, verify its SHA-256 manifest, and make a disposable working copy. Generated `dbconnect.php` is allowed only in that copy. Do not mutate original files to bypass a problem. Dead license retrieval can be observed as an offline warning; any future stub must be separately labeled and must not fabricate successful upstream retrieval.

## State-transition journeys

| Journey | Before/after evidence | Expected assertion contract |
|---|---|---|
| Fresh install | table descriptors/actual schema, seed row counts, settings version, initial admin presence, module tables/flags/hooks | Empty dedicated DB becomes an initialized game; no data claimed historical; password fields never exported |
| Account creation | FixtureWarrior start level, HP, attack/defense, gold/bank, turns, location, race/specialty, relevant prefs | Record actual starting values and creation hooks; do not hardcode guessed values |
| Login/logout | logged-in flag, restore page and allowed navigation; session identity only compared internally | Successful auth and intended state transition; no session IDs/hashes in artifacts |
| Village | destination list, location and module-added navigation | Record available core and selected-module locations |
| Forest/flee | starting turns, selected opponent fields, encounter state and ending turns/state | Establish exactly when turn is spent and flee effects |
| Combat | player/opponent/buffs before, known seed if controlled, damage/rewards/HP after | Deterministic comparison only when randomness/input conditions are equal |
| Training | eligibility, master/opponent fields, level/stat changes | Advance under actual master rules, not a direct SQL level increment presented as gameplay |
| New Day | clocks/settings, turns/HP, gold/bank/interest, buffs/mount state, module prefs/hooks | Separate player daily work from global run-once work; record concurrency/clock limitations |
| Death | inventory/currency/experience/location/spirit fields before/after | Record loss/persistence and actual path into shade/graveyard |
| Graveyard/Ramius | favors, spirits/torments, resurrection eligibility and HP/location | Distinguish fixture setup from resurrection action |
| Dragon | eligible level/stats, battle, kill/reset/points/new-day transitions | Use declared synthetic fixtures to reach eligibility; capture both win/loss when practical |
| Economy | bank/gold balances, equipment IDs/stats and offered costs | Deposit/withdraw/purchase/trade/interest state changes; preserve rounding and limits |
| Modules | installed/active flags, metadata, hooks, own tables, settings/prefs | Representative race, specialty, event, inn, direct route, daily and combat hooks |

## Randomness and time

Source facts: `lib/e_rand.php` wraps `mt_rand`, rounds/swaps bounds, and defines a microtime seed helper. `common.php` contains a commented-out `mt_srand(make_seed())`. Therefore externally seeding a PHP request may control MT-based gameplay without changing source, but must be tested for every relevant random family and request ordering. A seed alone does not record every draw or guarantee equivalence across PHP generations.

The lab includes an explicitly labeled optional auto-prepend seed fixture, not an application patch. Its disabled default preserves ordinary randomness. Compare repeated fresh-process runs at a fixed seed only after confirming the application's other sources of entropy. Time uses wall clock, database time and configurable game-day conversion. Do not call real wall clock fixed merely because the timezone is UTC.

Initial New Day tests may set synthetic stored timestamps/settings to place a character across a boundary. This is fixture setup, not control of the actual wall clock. Record both. Never change the host/VPS clock. Future supported-runtime clock/random interfaces belong to a later modernization phase.

## Defect policy

Four distinct classifications: `behavioral-compatibility`, `historical-defect-not-preserved`, `obsolete-integration`, `intentional-resurrection-correction`. Known S01–S13 belong to defect/obsolete categories, never the golden gameplay contract. Initial result files contain no invented observed state.

## Safe artifacts

Export only explicit allow-listed fields, counts, selected fixed output fragments and normalized navigation paths. Do not publish full account rows, raw HTTP, HTML pages, logs, sessions, configuration, DB dumps, passwords/hashes, emails or headers. Generated runtime files are ignored. Keep synthetic setup separate from observed transitions. Every observation records source tag, image digests/versions, settings, module selection, random/time controls and result status.

Characterization CI is deferred until a container-capable environment produces repeatable installs and journeys. The baseline-integrity workflow is not a proxy for that result.
