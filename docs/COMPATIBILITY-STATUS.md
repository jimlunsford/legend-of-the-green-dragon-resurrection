# Compatibility status

No supported-PHP compatibility claim is made. No application-source compatibility patch is included in foundation. Historical runtime candidates remain unproven until actually executed in isolation.

| Audit ID / class | Blocker | Scope / evidence |
|---|---|---|
| C01 | Removed get_magic_quotes_gpc | lib/errorhandling.php:25, early bootstrap |
| C02 | Removed mysql transport | Default dynamic mysql wrapper and direct installer calls |
| C03 | Curly-brace offsets | Installer helper, mail writer, mounts; archive meaninglesscode |
| C04 | Removed each | Widespread core and module loops |
| C05 | Reversed join arguments | pageparts, cleanup, petition and installer; archive topwebgames |
| C06 | Removed split and preg_replace /e | Selected archive modules and nicecomments support file |
| C07 | Bare array keys | Archive thieves.php:443 |
| C08–C10 | Globals, missing values and stricter typing | Path-sensitive behavioral work; not every legacy pattern is fatal |
| C11 | Generated-config syntax | installer_stage_6.php:78 writes an extra quote in an old upgrade branch |
| D01 | Legacy SQL TYPE= | Dag/Drinks support installers in core; Riddles in archive |
| Database | Zero dates, defaults and strict modes | Schema/seed behavior must be tested against explicit settings |
| Encoding/state | ISO-8859-1 and serialized strings | Preserve byte lengths before a deliberate conversion |
| Concurrency | Table locks and nontransactional multi-step state | Characterize daily work, economy and module lifecycle |

The existing mysqli wrappers are not a complete port. Historical fixes already replaced some core split calls and core descriptor TYPE syntax, while modules retain older forms. Do not alter module version strings to hide incompatibility.

Candidate selection must begin with the newest practical PHP 5.x line and a database that actually accepts the selected original installers. Do not blindly choose the oldest environment. See ../lab/historical-1.1.2/RUNTIME-INVESTIGATION.md for observed capability blockers. Supported modern PHP and databases remain the eventual production target.
