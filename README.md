# Legend of the Green Dragon - Resurrection

**Modern core engineering in progress. No Resurrection version or release has been assigned.**

**The historical source contains known high-severity security defects and unsupported runtime assumptions. Do not deploy the historical baseline to the public Internet.**

## What this is

A behaviorally faithful modernization project based on Legend of the Green Dragon 1.1.2 Dragonprime Edition with bundled maintenance fixes.

Preserve the game. Modernize the implementation.

The goal is to preserve recognizable gameplay, atmosphere, progression and module-driven extensibility while improving runtime compatibility, security, maintainability, testing, database behavior and selected module support.

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

The integrity commands do not execute the application. Use the disposable modern CI environments for driver checks; PHP 5 lab work is deferred. A passing engineering check is not a successful game install.

## Project documents

- [Versioning](docs/VERSIONING.md)
- [Licensing and provenance](docs/LICENSING-AND-PROVENANCE.md)
- [Module admission](docs/MODULE-POLICY.md)
- [Security status](docs/SECURITY-STATUS.md)
- [Compatibility status](docs/COMPATIBILITY-STATUS.md)
- [Modernization principles](docs/MODERNIZATION-PRINCIPLES.md)
- [Characterization plan](docs/CHARACTERIZATION-PLAN.md)
- [Historical lab](lab/historical-1.1.2/README.md)
- [Descendant research](docs/DESCENDANT-RESEARCH.md)
- [Foundation checkpoint, historical record](docs/FOUNDATION-CHECKPOINT-20260913.md)
- [Modern core checkpoint](docs/MODERN-CORE-CHECKPOINT-20260913.md)

## Licensing

Resurrection is a **noncommercial project**. The inherited [LICENSE.txt](LICENSE.txt) remains unchanged. It identifies CC BY-NC-SA 2.0 and contains additional LoGD-specific source/public-performance language. This project does not apply an incompatible replacement license to inherited code. Individual module and asset rights require review.

## Credits

Original game design and code: **Eric Stevens and JT Traub**. Continued development: the **DragonPrime Development Team** and individually credited contributors/module authors.

**Jim Lunsford** preserved these files on GitHub in 2019 and is the Resurrection project's preservation/modernization maintainer. He is not an original LoGD author. The 2019 uploads are preservation history, not the original software's development dates.

## Modern core work

Modernization is in progress on `modernization/core-modernization`. The engineering/driver tests do not establish an installable or publicly hostable game. See [platform targets](docs/SUPPORTED-PLATFORMS.md) and [validation scope](docs/ENGINEERING-VALIDATION.md). PHP 5 characterization is deferred during this phase.
