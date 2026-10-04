# Expression execution audit

Phase 3 scope: shipped core and the 24 bundled modules. The immutable historical tag is not modified. Archive-only modules are excluded.

## Inventory and disposition

| Phase 2 execution site | Classification | Source and risk | Phase 3 replacement |
|---|---|---|---|
| `lib/modules.php`, `module_collect_events` | A, historical B intent | Persisted `module_event_hooks.event_chance` was arbitrary PHP, including includes | Numeric constants through `Game\Expression`; Dark Horse uses one explicit named condition. Exact historical Dark Horse metadata is recognized without executing its text. Module activation/dependencies are checked before collecting its event. |
| `lib/modules.php`, `module_condition` | A, historical B intent | Persisted `module_hooks.whenactive` was arbitrary PHP | Fixed bundled expression vocabulary; unsupported conditions fail with a generic exception. No bundled module declares a nonempty whenactive condition. |
| `lib/buffs.php`, debug dynamic-field branch | A, historical B intent | Serialized/configured buff values became PHP after placeholder replacement | Removed. Debug prefix selects the same fixed evaluator, without execution or exposing source/state in diagnostics. |
| `lib/buffs.php`, normal dynamic-field branch | A, historical B intent | Same execution boundary without debug output | Removed. Only actual bundled numeric formulas are recognized. Non-expression scalar metadata retains its type. |
| `lib/extended-battle.php`, `execute_ai_script` | A | Creature/editor/database AI source executed in combat | Explicit source-controlled `Game\CreatureAi` implementation of the sole seeded behavior. Arbitrary code and unknown behavior identifiers are rejected. Numeric AI table reads use bound SQL. |

**Five eval calls found, five removed.** None is merely hidden behind an error-suppression operator. No PHP evaluator, general-purpose script engine, runtime compilation, generated PHP include, object construction, filesystem/shell/database capability, or arbitrary callback is exposed by the replacement vocabulary.

## Bundled semantics retained

- Event metadata: `return 100;` and `return 20;` remain supported numeric values. Collection still clamps and normalizes using the historical algorithm.
- Dark Horse forest/travel chance: 0 when the current mount has `findtavern`, otherwise 100. New registrations store `bundled:darkhorse-without-tavern-mount`. An exact whitespace-normalized match for the old registration is a migration compatibility identifier only. It cannot execute the embedded include/function text. Other PHP strings are refused.
- Elf defense: `(<defense>?(1+((1+floor(<level>/5))/<defense>)):0)`.
- Troll attack: `(<attack>?(1+((1+floor(<level>/5))/<attack>)):0)`.
- Both racial formulas preserve the zero-stat short circuit, numeric-string database values, floor operation, and existing multiplier. No general variable access or function invocation is supported. `debug:` retains the same result.
- Static numeric/boolean module conditions are accepted; arbitrary historical condition scripts are not.
- The sole nonempty seeded creature AI is creature 320, Gypsy Bandit. It initializes one spell point, rolls 0..7, steals rounded 20% only on roll zero when that amount exceeds 200, transfers that gold to the creature, and consumes the spell point. The code is implemented explicitly. The exact historical script's normalized SHA-256 and `bundled:gypsy-bandit` are accepted identifiers; modifying the script invalidates recognition. The original script remains inert seed data, with a characterization copy in `tests/fixtures/gypsy-bandit-ai.txt`.

This finite vocabulary deliberately does not implement arbitrary arithmetic, arbitrary PHP variables, or the old `<module|preference>` replacement feature: none is required by shipped expression metadata. New forms require source review, explicit implementation and tests. Unsupported metadata fails closed instead of changing gameplay silently.

## Other execution mechanisms reviewed

Search covered literal eval, assert, create_function, regular-expression replacement calls, callback dispatch, event/buff metadata, dynamic configuration generation, AI scripts, and include/require construction. No evaluated `/e` regular expression or string assertion execution remains in shipped source. Dag/Drinks use static call_user_func_array wrapper targets for their support files. Module PHP entrypoints remain trusted installed source, loaded through the module-name/activation/dependency boundary, not a scripting sandbox. Database-backed hook function selection now permits only the conventional module-name `_dohook` entrypoint. Arbitrary database-selected PHP function names are rejected.

Phase 1/2 disabled SQL/PHP console, source viewer, LoGDnet, payments, and historical recovery remain disabled. No disabled endpoint was reactivated for compatibility.

## Evidence and limits

`ExpressionTest` reads the real racial formula strings and exercises constants, boolean results, both numeric and numeric-string states, zero division guards, malformed/oversized input, unsupported functions/variables, includes, object syntax and code-injection attempts. Creature tests use the preserved seed script, both line endings, the named identifier, the no-theft roll/threshold, the exact one-time transfer and modified-script rejection. `ModuleCertificationTest` checks the actual installed creature metadata and active event collection, including Dark Horse object preference changes.

Final supported-matrix run IDs and conclusions are recorded in the Phase 3 checkpoint. These tests do not certify all combat routes, serialized-state validation, module route authorization or economy mutation safety. Those remain independent gates.
