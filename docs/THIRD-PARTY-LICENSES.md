# Third-party licenses

Every third-party package in `composer.lock` at the commit that adds this file (2026-10-24), with the license the lock
file records for it. This is an engineering record, not legal advice. Dependencies are not changed by it.

## Summary

- **Runtime packages: none.** `composer.json` requires only PHP 8.4 or 8.5 and its bundled extensions (`pdo`,
  `pdo_mysql`, `mbstring`, `json`, `session`). The game runs without any Composer package.
- **Development packages: 26**, pulled in by `phpunit/phpunit` and `phpstan/phpstan` for tests and static analysis.
  They are installed into `vendor/`, which is not tracked (`.gitignore`), so the repository does not contain or
  distribute them; anyone who runs `composer install` gets them from their own publishers under their own licenses.
- Licenses found: MIT (3 packages) and BSD-3-Clause (23 packages).
- **Incompatible license: none found.** MIT and BSD-3-Clause are permissive. They allow use, copying, modification
  and redistribution, including noncommercial redistribution alongside work under other terms, on condition that the
  copyright notice and license text are kept (BSD-3-Clause also forbids using the authors' names to endorse a
  derived product). Neither requires the rest of the project to use their license, and neither forbids distributing
  them next to work under CC BY-NC-SA 2.0 and the LoGD terms in `LICENSE.txt`. The packages keep their own licenses;
  CC BY-NC-SA 2.0 is not applied to them.

## Runtime (`packages`)

None.

## Development only (`packages-dev`)

| Package | Version | License |
|---|---|---|
| `myclabs/deep-copy` | 1.14.0 | MIT |
| `nikic/php-parser` | v5.8.0 | BSD-3-Clause |
| `phar-io/manifest` | 2.0.4 | BSD-3-Clause |
| `phar-io/version` | 3.2.1 | BSD-3-Clause |
| `phpstan/phpstan` | 2.2.14 | MIT |
| `phpunit/php-code-coverage` | 12.5.7 | BSD-3-Clause |
| `phpunit/php-file-iterator` | 6.0.2 | BSD-3-Clause |
| `phpunit/php-invoker` | 6.0.0 | BSD-3-Clause |
| `phpunit/php-text-template` | 5.0.0 | BSD-3-Clause |
| `phpunit/php-timer` | 8.0.0 | BSD-3-Clause |
| `phpunit/phpunit` | 12.5.35 | BSD-3-Clause |
| `sebastian/cli-parser` | 4.2.1 | BSD-3-Clause |
| `sebastian/comparator` | 7.1.8 | BSD-3-Clause |
| `sebastian/complexity` | 5.0.0 | BSD-3-Clause |
| `sebastian/diff` | 7.0.1 | BSD-3-Clause |
| `sebastian/environment` | 8.1.2 | BSD-3-Clause |
| `sebastian/exporter` | 7.0.3 | BSD-3-Clause |
| `sebastian/global-state` | 8.0.3 | BSD-3-Clause |
| `sebastian/lines-of-code` | 4.0.1 | BSD-3-Clause |
| `sebastian/object-enumerator` | 7.0.0 | BSD-3-Clause |
| `sebastian/object-reflector` | 5.0.0 | BSD-3-Clause |
| `sebastian/recursion-context` | 7.0.1 | BSD-3-Clause |
| `sebastian/type` | 6.0.4 | BSD-3-Clause |
| `sebastian/version` | 6.0.0 | BSD-3-Clause |
| `staabm/side-effects-detector` | 1.0.5 | MIT |
| `theseer/tokenizer` | 2.0.1 | BSD-3-Clause |

## Method

The table is read from `composer.lock` (`packages` and `packages-dev`, each package's `license` field). The installed
copy of each package was also checked locally: see "Installed license files" below.

## Not covered here

- PHP itself and its extensions (PHP License and others) are the operator's platform, not bundled.
- The MySQL or MariaDB server, the web server and CI actions are not bundled either.
- Third-party material inside the inherited 1.1.2 tree (module code by credited authors, images, skins, any embedded
  script) is not a Composer package. Its rights are an open item in `LICENSING-AND-PROVENANCE.md`.

## Installed license files

Checked on 2026-10-24 after `composer install` from this lock file: each of the 26 packages ships a `LICENSE` file,
and each file's text matches the lock file's license (the MIT permission grant for the three MIT packages; the
three-clause BSD conditions, including the no-endorsement clause, for the 23 BSD-3-Clause packages). Two notes:

- `phpstan/phpstan` is distributed as a PHAR that bundles PHPStan's own dependencies under their licenses. It is a
  development tool run by CI; nothing from it is shipped with the game.
- `vendor/composer/` (the class loader Composer generates) is Composer's MIT-licensed code. It is generated locally
  and not tracked.
