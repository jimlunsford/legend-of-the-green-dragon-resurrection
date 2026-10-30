# Third-party licenses

Every third-party package in `composer.lock` at the commit that adds this file (2026-10-24), with the license the lock
file records for it. This is an engineering record, not legal advice. Dependencies are not changed by it.

## Summary

- **Runtime packages: none.** `composer.json` requires only PHP 8.4 or 8.5 and its bundled extensions (`pdo`,
  `pdo_mysql`, `mbstring`, `json`, `session`). The game runs without any Composer package.
- **Bundled fonts: 3 families** (added 2026-10-30, Phase 5 task 2): Cinzel, Alegreya and Alegreya Sans, SIL Open
  Font License 1.1, self-hosted in `assets/fonts/` for the new UI. See "Bundled fonts" below.
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

## Bundled fonts

Added 2026-10-30 (Phase 5 task 2) for the new UI (`docs/UI-DESIGN-SPEC.md`). Served by the game itself from
`assets/fonts/`; the game requests no font from a third party. Only the faces the spec uses are bundled, as woff2,
latin and latin-ext subsets. Each family's license text is beside its files as `OFL.txt`, copied unchanged from the
package's `LICENSE`.

| Family | Faces | Source | Version | License | Upstream |
|---|---|---|---|---|---|
| Cinzel | 500, 700 | npm `@fontsource/cinzel` | 5.3.0 | SIL OFL 1.1 (`OFL-1.1` in its `package.json`; `OFL.txt`) | Copyright 2020 The Cinzel Project Authors, https://github.com/NDISCOVER/Cinzel |
| Alegreya | 400, 500, 400 italic | npm `@fontsource/alegreya` | 5.3.0 | SIL OFL 1.1 (`OFL-1.1`; `OFL.txt`) | Copyright 2011 The Alegreya Project Authors, https://github.com/huertatipografica/Alegreya |
| Alegreya Sans | 400, 500, 700 | npm `@fontsource/alegreya-sans` | 5.3.0 | SIL OFL 1.1 (`OFL-1.1`; `OFL.txt`) | Copyright 2013 The Alegreya Sans Project Authors, https://github.com/huertatipografica/Alegreya-Sans |

The OFL allows using, studying, copying, modifying, embedding and redistributing the fonts, alone or bundled with
software, including noncommercially alongside work under other terms, as long as the license and copyright notice go
with them, the fonts are not sold by themselves, and a modified font does not use the Reserved Font Name. The files
here are unmodified. The OFL does not extend to the rest of the project, and the project's licenses do not apply to the
fonts.

Every bundled font file and its SHA-256 (`tests/tooling/test_ui_tokens.py` checks them):

| File | SHA-256 |
|---|---|
| `assets/fonts/alegreya/alegreya-latin-400-italic.woff2` | `9be8976a11463895bb4d590973a684c1c96616b7e40a03333151efa8c7210241` |
| `assets/fonts/alegreya/alegreya-latin-400-normal.woff2` | `7cbcf38a5ef682a8b9e2ef4a330b31a08b33bf1ae08e338267c0d1292479c68a` |
| `assets/fonts/alegreya/alegreya-latin-500-normal.woff2` | `53addbca37745d63946bfb8fefc33570c075a91d5463c4194139b6a2efad3930` |
| `assets/fonts/alegreya/alegreya-latin-ext-400-italic.woff2` | `27550192eeb67db7db1ff877e24b8b5e6acefe351d3b4f1941bc19c31c7029f2` |
| `assets/fonts/alegreya/alegreya-latin-ext-400-normal.woff2` | `2f7d416d7c40459b8f24ee9f4d2e14b963b29c67912de517615a2f2cfe292aa4` |
| `assets/fonts/alegreya/alegreya-latin-ext-500-normal.woff2` | `3c67babba896cc63751b95a7a51ac6f005dc019a4f62ec982b57b0089248ef23` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-400-normal.woff2` | `b2a5a35a2563a2f9bf9fb91939fc6ea6c115e9811cda5c4a37d02f623c7c4dbe` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-500-normal.woff2` | `55b61d22cc7afd0bca94d17a84f0e92963b8f5216dbfbde56b2f6cf530c9b2dd` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-700-normal.woff2` | `5acd19a588614d23e52b741a21dd8555178ab4932c3195c6d8fb4b296f84acae` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-ext-400-normal.woff2` | `c76008bc2a70ed1bdfd00cf5639e6a7163b7f1a095e50aac94d8c8dcd5eab337` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-ext-500-normal.woff2` | `578e5203186650ae0a335692f1ecc71b0591f569f3bbeae7bfcf55874e199514` |
| `assets/fonts/alegreya-sans/alegreya-sans-latin-ext-700-normal.woff2` | `8f289f338a955d3a9d51cd59a0a99e96627fe54d3c54859f4eeeacb9f24b80bc` |
| `assets/fonts/cinzel/cinzel-latin-500-normal.woff2` | `2864b2c9db377e10db43d3ca1ab268c0e10cf3ee94b1a325d104b8e4a74dc7da` |
| `assets/fonts/cinzel/cinzel-latin-700-normal.woff2` | `8efa224fe70fef188a39c095e218b81fd31061809f2752537e33a9ec7b9c2263` |
| `assets/fonts/cinzel/cinzel-latin-ext-500-normal.woff2` | `08eac55c117837a40ede8e3ac455e4752aa5ac7353e3f29a96a67a12043d6647` |
| `assets/fonts/cinzel/cinzel-latin-ext-700-normal.woff2` | `55b110958b9b614a410cf607c12b1ff2990fa3e5bd0bfacf17e5fd7dce681562` |
