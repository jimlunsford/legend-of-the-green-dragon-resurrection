# Supported platforms and current verification scope

Policy checked 2026-09-13; the runtime requirements and the configuration section updated 2026-11-07 (Phase 6 task 6) to match the code. No release has been cut yet. How to host the game: [`HOST-GUIDE.md`](HOST-GUIDE.md).

## PHP target

| Branch | Role | Upstream active support | Upstream security support |
|---|---|---|---|
| 8.5 | Primary modern target | Through 2027-12-31 | Through 2029-12-31 |
| 8.4 | Previous supported target | Through 2026-12-31 | Through 2028-12-31 |

Source: [PHP supported versions](https://www.php.net/supported-versions.php). PHP 8.6 is still a prerelease. Composer deliberately accepts only 8.4.x and 8.5.x; matrix results describe engineering checks, not every gameplay route.

Runtime requirements: the extensions `composer.json` declares (ctype, filter, JSON, mbstring, PDO, pdo_mysql, session) and normal standard PHP facilities; no Composer package is needed at run time. Development additionally needs Composer 2, DOM/XML/XMLWriter, tokenizer, Python 3, and Git. PHPUnit is locked to 12.5.35, PHPStan to 2.2.14. New infrastructure uses PSR-4; historical procedural code remains procedural.

## Database targets

Primary target: MariaDB 11.4 LTS. Secondary candidate: MySQL 8.4 LTS. MariaDB 11.4 has Community maintenance through May 2029 according to the [MariaDB maintenance policy](https://mariadb.org/about/#maintenance-policy). MySQL 8.4 is an LTS series under the [MySQL release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html).

The CI matrix pairs PHP 8.4 with MariaDB 11.4 and PHP 8.5 with MySQL 8.4 to test the new PDO driver. Both passed at code checkpoint 471056d6a8515fee60b51319173a5f1f87f8545c (PHP 8.4.25 / MariaDB 11.4.13 and PHP 8.5.10 / MySQL 8.4.11). These are two tested pairings, not a claim about every PHP/database combination. Exact resolved versions and workflow outcomes belong in the checkpoint.

Driver requirements: explicit utf8mb4 connection, native prepared statements, multi-statement execution disabled, predictable string-valued result columns, validated identifiers, and a non-root application account scoped to one database. Test tables use InnoDB and utf8mb4. Integration checks require STRICT_TRANS_TABLES; no global or connection SQL-mode weakening is performed. The game schema, zero dates, defaults, legacy table locks, and bundled module DDL still require a separate fresh-install pass. No full schema compatibility claim is made yet.

## Development and production boundaries

Use disposable local or CI databases and synthetic data. Do not run the historical PHP 5 lab for this phase. CI uses standard GitHub runners with no public game runtime. Runtime credentials, Composer vendor contents, local sessions, caches and logs are not committed.

A hosted game needs HTTPS, a supported PHP SAPI with sessions, the configuration file outside the document root where practical (`LOGD_DBCONNECT`), and web-server rules denying configuration, vendor, tests, internal libraries and development tooling. The host guide lists them; sample rules for nginx and Apache are in `docs/hosting/` (samples, not active), checked by `tests/tooling/test_host_guide.py` and locally behind real servers (`PHASE6-TASK6-HOST-GUIDE-20261107.md`). No production web-server or VPS changes are part of this work.

## Configuration

The game, `scripts/install.php` and `scripts/upgrade.php` read the configuration file `LOGD_DBCONNECT` names when that environment variable is set (an absolute path), else `dbconnect.php` in the game's directory (`src/Configuration/ConfigLocation.php`; Phase 6 task 3, S12). The install writes it through the LegacyConfig generator, owner-only (0600), never over an existing file, outside the served directory unless allowed; a host without a shell installs and upgrades with `setup.php` (Phase 6 task 5b). A file that exists but cannot be used gets the 503 configuration page (S4). Populated databases are refused, and a completed install records its completion.
