# Descendant research

Reviewed 2026-09-13 as engineering references. No descendant application code or dependency was imported. Source facts below are separated from Resurrection decisions. Runtime claims are not security certification.

## NB-Core/lotgd

**Source facts:** the README advertises supported modern PHP/MySQL/MariaDB. Its Composer manifest declares Doctrine DBAL/migrations/ORM, Twig, Symfony cache, PHPMailer, PHPUnit and PHPStan, with PSR-4 `Lotgd` classes. `lib/dbwrapper.php` preserves legacy configuration compatibility and redirects to modern database functions. `lib/modules.php` retains procedural wrapper names but delegates to module/installer/hook classes, with stricter parameter types and explicit comments about compatibility changes.

Sources: [project](https://github.com/NB-Core/lotgd), [Composer](https://github.com/NB-Core/lotgd/blob/master/composer.json), [DB wrapper](https://github.com/NB-Core/lotgd/blob/master/lib/dbwrapper.php), [module wrappers](https://github.com/NB-Core/lotgd/blob/master/lib/modules.php).

**Decision:** study its migration/testing seams and legacy wrappers before repeating work. Do not import strict signatures or data/serialization changes without fixture evidence. Package declarations establish chosen tools, not that every old module works or every unsafe serialized value has been corrected.

## stephenKise/Legend-of-the-Green-Dragon

**Source facts:** the project advertises PHP 8.4+/MySQL 8.0.4+. Its DB selector uses procedural mysqli rather than the historical default mysql driver. It remains recognizably procedural and describes an opinionated modernization. The inspected `pull.php` invokes shell git pull without authentication in that file, demonstrating that PHP compatibility alone is not a hosting gate.

Sources: [project](https://github.com/stephenKise/Legend-of-the-Green-Dragon), [DB selector](https://github.com/stephenKise/Legend-of-the-Green-Dragon/blob/master/lib/dbwrapper.php), [inspected pull endpoint](https://github.com/stephenKise/Legend-of-the-Green-Dragon/blob/f273beebaebb0780615f4ef1f4ebbe13638ffdc8/pull.php).

**Decision:** compare focused helper/DB changes as references. Do not copy its management endpoints or assume tests, template compatibility or serialization safety without targeted review. No proof of complete 187-module compatibility was obtained.

## lotgd/lotgd

**Source facts:** the README describes a new implementation drawing on Daenerys. Composer requires PHP >=8.4 and includes Symfony 7-family components, Doctrine, Twig and modern serializer/JSON tooling. Its Composer license field says `proprietary`; do not assume an unrestricted code-import grant. It is a modern reimplementation, not evidence that the old procedural module API remains compatible.

Sources: [project](https://github.com/lotgd/lotgd), [Composer manifest](https://github.com/lotgd/lotgd/blob/main/composer.json). Its `main` default branch was verified through the connected GitHub repository metadata.

**Decision:** use it to understand reimplementation tradeoffs, not replace Resurrection's baseline or introduce its frontend/framework. Its serialization dependencies do not prove a safe migration path for historical serialized account fields.

## Comparison relevant to foundation

| Topic | Source evidence | Resurrection choice now |
|---|---|---|
| PHP 8 | Descendants advertise support; NB supplies typed wrappers | Keep historical reference unchanged; no PHP 8 port in foundation |
| Database | mysqli continuation and Doctrine-based evolution both exist | Characterize SQL, result types, defaults, locks and modes before choosing |
| Composer | Substantial modern dependency graphs in NB/new implementation | No dependencies needed for Python integrity tooling; no framework adoption |
| Testing | NB declares PHPUnit/PHPStan and QA scripts | Add integrity negative tests now; gameplay assertions only after observation |
| Templates | Twig coexists with historical-style code in NB; modern framework uses Twig | Preserve original template evidence, no redesign |
| Helpers/modules | NB delegates familiar functions to classes; Stephen changes default driver | Keep API expectations explicit; characterize old callback order and return values |
| Serialization | New serializer/JSON tools appear in modern implementation; no full migration audit performed | Byte-aware fixtures first; do not assume replacing unserialize is mechanical |
| Rights/security | Different licensing signals and privileged surfaces | Review every possible import separately; no code imported |
