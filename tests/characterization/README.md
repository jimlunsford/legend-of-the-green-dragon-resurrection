# Historical characterization evidence

`module-set.json` is a complete static inventory of the 24 bundled entrypoints and 13 support files, including literal hook declarations and installer recommendations. It is not proof of runtime activation. Eight representative modules have a proposed characterization scope, not release approval. The other modules remain historical-only. No separate archive modules were imported.

`results-20260913.json` records capability blockers and proposed evidence fields for each journey. It contains **zero observed gameplay state transitions**. BLOCKED means prerequisite investigation prevented execution; it does not imply that a PHP request was attempted and failed. Dragon progression is NOT ATTEMPTED, with a fixture plan in the characterization document. No synthetic accounts were actually created.

Future results must record exact runtime digests, database modes/encoding/timezone, source tag, selected modules, fixture setup, observations and assertions. Do not replace null observations with predicted values. Use FixtureWarrior/FixtureMage or similarly obvious synthetic identities.

Only allowlisted state belongs in Git: level, hitpoints, gold, goldinbank, turns, attack, defense, experience, alive, location, dragonkill count, safe buff summaries, selected module settings/preferences and route destinations stripped of session/authentication data. Verify field names against the historical schema before writing a collector. Do not export entire accounts rows, serialized session payloads, password hashes, cookie jars, generated configuration or raw logs. Do not blindly persist complete HTML responses from diagnostic paths.

Behavioral compatibility covers gameplay. S01-S13 are historical defects, intentionally not preserved. Obsolete integrations and intentional future security corrections need separate assertions and review.
