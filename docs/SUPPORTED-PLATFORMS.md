# Supported platforms

The PHP and database versions Resurrection supports, and what the project tests on them. To run the game on a web
server, follow the host guide, [`HOST-GUIDE.md`](HOST-GUIDE.md); to play on your own computer, see
[`MODERN-INSTALLATION.md`](MODERN-INSTALLATION.md).

## PHP

PHP 8.4 or 8.5, nothing older or newer: `composer.json` allows only these two (`~8.4.0 || ~8.5.0`). The web setup
page's check (`setup.php`) fails a PHP older than 8.4 and warns on one newer than 8.5 ("untested; Resurrection supports
PHP 8.4 and 8.5"); after the warning, setup may continue.

| Version | Tested with | PHP's active support | PHP's security support |
|---|---|---|---|
| 8.5 | MySQL 8.4 | Through 2027-12-31 | Through 2029-12-31 |
| 8.4 | MariaDB 11.4 | Through 2026-12-31 | Through 2028-12-31 |

The dates are from PHP's [supported versions](https://www.php.net/supported-versions.php) page (checked 2026-11-20).
PHP 8.6, released 2026-11-19, is not supported: `composer.json` does not allow it, and the project does not test it.

The game needs the extensions `composer.json` lists: `ctype`, `filter`, `json`, `mbstring`, `pdo`, `pdo_mysql` and
`session`. A PHP built without sessions cannot run it. It loads no Composer package when it runs, so a hosted copy has
no `vendor/` folder, and it does not depend on PHP's output buffering.

Development also needs Composer 2, the `dom`, `xml`, `xmlwriter` and `tokenizer` extensions, Python 3 and Git. The
development tools are locked in `composer.lock`: PHPUnit 12.5.35 and PHPStan 2.2.14.

## Databases

MariaDB 11.4 or MySQL 8.4. Both are long-term support series: MariaDB 11.4 is maintained through May 2029 under the
[MariaDB maintenance policy](https://mariadb.org/about/#maintenance-policy), and MySQL 8.4 is an LTS series under the
[MySQL release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html).

What the game expects of the database:

- An empty database, created with the `utf8mb4` character set, and a user allowed only on that database (never root).
  A fresh install creates 41 tables, InnoDB, `utf8mb4_unicode_ci`.
- Its connection uses PDO with `utf8mb4`, native prepared statements, every value bound, and multi-statement execution
  off.
- It adds `STRICT_TRANS_TABLES`, `NO_ZERO_DATE`, `NO_ZERO_IN_DATE`, `ERROR_FOR_DIVISION_BY_ZERO` and
  `NO_ENGINE_SUBSTITUTION` to the server's SQL mode for its own connection, and keeps the server's other modes. Do not
  weaken the server's SQL mode for it.

## What CI tests

The Modern core workflow runs on every push to `main` and every pull request, on two pairs:

- PHP 8.4 with MariaDB 11.4
- PHP 8.5 with MySQL 8.4

On each pair: Composer's checks (`validate --strict`, `audit`), PHP lint, both PHPStan gates, the release upgrade check
(the latest release installed, upgraded to the commit under test and compared with a fresh install), the PHPUnit suite,
and the HTTP tests in five shards, which play the game through PHP's built-in web server. The Baseline
integrity workflow checks the historical 1.1.2 source and runs the tooling tests that need no database.

CI takes the newest patch release of each version, so the exact versions move. At `deee4e6` (Phase 6 task 30, Modern
core run 44723370052) they were PHP 8.4.28 with MariaDB 11.4.13, and PHP 8.5.13 with MySQL 8.4.13.

These are the two tested pairs. Other pairings of the same versions may work; they are not tested.

## Not supported

- Any other PHP version or database server.
- A 1.1.2 database. Installs are fresh only: Resurrection never migrates or upgrades a 1.1.2 database.
- Weakened SQL modes, a shared database or a database user with rights beyond the game's database.
