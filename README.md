# Legend of the Green Dragon - Resurrection

**Modern core engineering in progress. No Resurrection version or release has been assigned.**

**The historical source contains known high-severity security defects and unsupported runtime assumptions. Do not deploy the historical baseline to the public Internet.**

## What this is

A modern continuation of Legend of the Green Dragon 1.1.2 Dragonprime Edition with bundled maintenance fixes, in PHP, so that other people can host it.

Phases 1 to 4 modernized the implementation and kept the game as it was: runtime compatibility, security, maintainability, testing, database behavior and selected module support. Since 2026-10-30 gameplay, text and the interface may change too, one deliberate and recorded change at a time; the current phase gives the game a new interface and original art. See [ROADMAP.md](ROADMAP.md).

## What this is not

- Not the original LoGD project or an official DragonPrime continuation.
- Not endorsed by the original authors.
- Not currently production-ready or approved for public game hosting.
- Not a reconstruction of an exact old server, player community or historical database.

## Historical source

- [Preserved core](https://github.com/jimlunsford/lotgd), commit `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`.
- [Module archive](https://github.com/jimlunsford/lotgd-modules), commit `ef92e2df4e27ff29eb596b2b27ce3c9ab2c15187`.
- Annotated baseline marker: `historical-source-1.1.2`, not a Resurrection release.

The original 11 preservation commits and exact 417-file core tree remain reachable under the immutable historical tag. Historical TXT documentation remains intact. The separate 187-entrypoint module archive is reference material, not an automatically supported module set. See [PROVENANCE.md](PROVENANCE.md).

## Play it on your own computer

Local use only, not for hosting. With Docker (Compose v2.24 or newer) and Python 3:

```sh
python3 scripts/local-play.py up      # start the game; the first time, install it
python3 scripts/local-play.py stop    # stop it; the game is kept
python3 scripts/local-play.py wipe    # delete the game and its data
```

Then open http://127.0.0.1:8080/. Details, upgrades and backups: [Play locally](docs/MODERN-INSTALLATION.md#play-locally).

## Development model

Modernize and test the core boundaries first. Compare specific gameplay behavior against the preserved baseline when needed.

Characterize desired gameplay, not security bugs. Known defects remain open until corrected and tested. No public historical runtime belongs on a developer's production server. The disposable lab must not expose a host port and must constrain outbound networking.

Start with:

```sh
python3 scripts/verify-historical-baseline.py
python3 scripts/validate-project.py
python3 -m unittest discover -s tests/tooling -v
composer install
composer check
vendor/bin/phpstan analyse -c phpstan-new.neon
```

The integrity commands do not execute the application. The modern CI matrix also performs a real strict fresh installation, installs all 24 bundled modules inactive, and verifies account creation, authentication, session rotation, village rendering, commentary moderation and logout. See the phase-2 checkpoint for exact evidence and remaining security/module gates. PHP 5 lab work is deferred; public hosting remains unapproved.

## Project documents

- [Versioning](docs/VERSIONING.md)
- [Licensing and provenance](docs/LICENSING-AND-PROVENANCE.md)
- [Notice](NOTICE) and [third-party licenses](docs/THIRD-PARTY-LICENSES.md)
- [Module admission](docs/MODULE-POLICY.md)
- [Security status](docs/SECURITY-STATUS.md)
- [Compatibility status](docs/COMPATIBILITY-STATUS.md)
- [Modernization principles](docs/MODERNIZATION-PRINCIPLES.md)
- [Characterization plan](docs/CHARACTERIZATION-PLAN.md)
- [Historical lab](lab/historical-1.1.2/README.md)
- [Descendant research](docs/DESCENDANT-RESEARCH.md)
- [Foundation checkpoint, historical record](docs/FOUNDATION-CHECKPOINT-20260913.md)
- [Modern core phase-1 checkpoint](docs/MODERN-CORE-CHECKPOINT-20260913.md)
- [Modern core phase-2 checkpoint](docs/MODERN-CORE-CHECKPOINT-20260913-PHASE2.md)
- [Modern local installation](docs/MODERN-INSTALLATION.md)
- [Raw request contract](docs/REQUEST-SEMANTICS.md)

## Licensing

Resurrection is a **noncommercial project**. The inherited [LICENSE.txt](LICENSE.txt) remains unchanged. It identifies CC BY-NC-SA 2.0 and contains additional LoGD-specific source/public-performance language. This project does not apply an incompatible replacement license to inherited code. Individual module and asset rights require review.

See [NOTICE](NOTICE) for the attribution, the license and what it asks of anyone who runs a modified copy, and [third-party licenses](docs/THIRD-PARTY-LICENSES.md) for the development libraries in `composer.lock` (the game itself needs none). The game's "View PHP Source" link points at the public source; an operator who changes the game sets it (`sourceurl`) to their own modified source.

## Credit and attribution

Resurrection's modifications and additions are Copyright (c) 2026 Phoenix 2:33 LLC, licensed under CC BY-NC-SA 2.0 as an adaptation of LoGD 1.1.2; the original authors' copyright and credits stay as they are. The game credits Resurrection on every page and popup, on its own line under the original authors' credit: "A Phoenix 2:33 LLC Project by Jim Lunsford", with "Phoenix 2:33 LLC" linking to https://phoenix233.com and "Jim Lunsford" to https://jimlunsford.com. Anyone who hosts, distributes or adapts Resurrection keeps that credit and its links where the original authors' credit appears, at least as prominently, as the license's attribution section requires. See [NOTICE](NOTICE).

## Credits

Original game design and code: **Eric Stevens and JT Traub**. Continued development: the **DragonPrime Development Team** and individually credited contributors/module authors.

**Jim Lunsford** preserved these files on GitHub in 2019 and is the Resurrection project's preservation/modernization maintainer. He is not an original LoGD author. The 2019 uploads are preservation history, not the original software's development dates.

## Modern core work

Modernization is in progress on `modernization/core-modernization`. The engineering/driver tests do not establish an installable or publicly hostable game. See [platform targets](docs/SUPPORTED-PLATFORMS.md) and [validation scope](docs/ENGINEERING-VALIDATION.md). PHP 5 characterization is deferred during this phase.
