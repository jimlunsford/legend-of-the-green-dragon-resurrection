# Supported platforms and current verification scope

Policy checked 2026-09-13. Resurrection remains a development branch, not an installable supported game or a hosting-approved release.

## PHP target

| Branch | Role | Upstream active support | Upstream security support |
|---|---|---|---|
| 8.5 | Primary modern target | Through 2027-12-31 | Through 2029-12-31 |
| 8.4 | Previous supported target | Through 2026-12-31 | Through 2028-12-31 |

Source: [PHP supported versions](https://www.php.net/supported-versions.php). PHP 8.6 is still a prerelease. Composer deliberately accepts only 8.4.x and 8.5.x; matrix results describe engineering checks, not every gameplay route.

Runtime requirements: PDO, pdo_mysql, mbstring, JSON, sessions and normal standard PHP facilities. Development additionally needs Composer 2, DOM/XML/XMLWriter, tokenizer, Python 3, and Git. Session support is required for the future application smoke gate even though the isolated driver/config tests do not exercise sessions. PHPUnit is locked to 12.5.35, PHPStan to 2.2.14. New infrastructure uses PSR-4; historical procedural code remains procedural.

## Database targets

Primary target: MariaDB 11.4 LTS. Secondary candidate: MySQL 8.4 LTS. MariaDB 11.4 has Community maintenance through May 2029 according to the [MariaDB maintenance policy](https://mariadb.org/about/#maintenance-policy). MySQL 8.4 is an LTS series under the [MySQL release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html).

The CI matrix pairs PHP 8.4 with MariaDB 11.4 and PHP 8.5 with MySQL 8.4 to test the new PDO driver. These are two tested pairings once their checks pass, not a claim about every PHP/database combination. Exact resolved versions and workflow outcomes belong in the checkpoint.

Driver requirements: explicit utf8mb4 connection, native prepared statements, multi-statement execution disabled, predictable string-valued result columns, validated identifiers, and a non-root application account scoped to one database. Test tables use InnoDB and utf8mb4. Integration checks require STRICT_TRANS_TABLES; no global or connection SQL-mode weakening is performed. The game schema, zero dates, defaults, legacy table locks, and bundled module DDL still require a separate fresh-install pass. No full schema compatibility claim is made yet.

## Development and production boundaries

Use disposable local or CI databases and synthetic data. Do not run the historical PHP 5 lab for this phase. CI uses standard GitHub runners with no public game runtime. Runtime credentials, Composer vendor contents, local sessions, caches and logs are not committed.

Future production will require HTTPS, a supported PHP SAPI with sessions, private configuration outside the document root where practical, and web-server rules denying configuration, vendor, tests, internal libraries and development tooling. Those deployment rules are not implemented or certified here. No production web-server or VPS changes are part of this work.

## Configuration transition

The current installer still uses ignored local dbconnect.php. There is not yet environment-variable precedence or a completed safe fresh-install flow. Stage 6 now serializes an explicit field allow-list with var_export for both fresh and upgrade generation; it no longer prints generated credentials on failure. Existing trusted configuration is reloaded in the upgrade branch because common.php unsets connection credentials. Atomic writes, location outside the public tree, populated-database guards and post-install lockout remain required.
