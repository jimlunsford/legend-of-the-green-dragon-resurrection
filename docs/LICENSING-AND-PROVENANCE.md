# Licensing and provenance working position

This is an engineering rights ledger and working interpretation, not a legal ruling.

## Inherited core

The historical `LICENSE.txt` is preserved byte-for-byte. It identifies **Creative Commons Attribution-NonCommercial-ShareAlike 2.0**, links to that legal text, and supplies additional LoGD-specific source/public-performance expectations and exceptions.

The project is **noncommercial**. Do not claim commercial use is permitted. Retain attribution, copyright notices and ShareAlike obligations. Modifications are contemplated by the inherited terms, but that does not permit blanket relicensing to MIT, GPL or AGPL. Later LoGD code cannot be imported into the old GPL line as a relicensing shortcut.

The CC legal code and LoGD's explanatory/additional text are distinct evidence. Do not describe generic CC BY-NC-SA as an AGPL-style source license. Conservatively retain LoGD's stated expectation that applicable modified source be available on request for public game hosting beyond the stated family/social circle. A live executable source viewer is not mandatory under the LoGD text; safe source delivery can satisfy that stated approach.

Sources: [preserved LICENSE](../LICENSE.txt), [CC deed](https://creativecommons.org/licenses/by-nc-sa/2.0/), [official legal-code source](https://github.com/creativecommons/cc-legal-tools-data/blob/main/legacy/legalcode/by-nc-sa_2.0.html).

## Authorship

Eric Stevens and JT Traub are original game authors; DragonPrime developers and individual contributors retain their credits. Jim Lunsford is preservation owner and Resurrection modernization maintainer. GitHub visibility, repository ownership and the act of uploading files do not transfer authorship or supply unrestricted relicensing rights. No official endorsement is claimed.

## Modules and assets

The archive has no clean blanket license grant established for all 187 modules. `core_module` metadata and author credits are provenance clues, not sufficient independent rights clearance. Exact copies already in the core retain that distribution context but are not permission to erase individual attribution.

Admission requires a component record: path/hash, source commit, credited author, explicit/contextual license evidence, dependencies, asset/content rights, modifications and unresolved questions. Status must not imply rights cleared merely because code executes.

Known unresolved archive questions include avatar artwork use scope, riddle content provenance and contradictory funddrive distribution wording. No such extra module/content is imported here. Unresolved components may be omitted from Resurrection releases while remaining preserved in the original archive.

The inherited core asset collection also needs an asset-level ledger before a release. A public foundation repository is not a public-hosting approval or proof that all future release rights have been resolved. Keep source notices intact and seek rights-holder or qualified advice where uncertainty affects distribution/commercial plans.

## 2026-10-24: honoring the 1.1.2 license

Maintenance named by Jim (`UNNECESSARY-FILES-20261024.md`, "License and dead weight"). An engineering record, not a
legal ruling. What it did:

- **Source on request.** The game's "View PHP Source" link, 1.1.2's way of offering the source, pointed at the
  disabled `source.php`. It now opens the `sourceurl` setting, by default the public repository
  (https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection), with 1.1.2's text and position. The
  setting's description tells an operator who changes the game to point it at their own complete modified source.
  This supports, and does not replace, `LICENSE.txt`'s requirement that the complete modified source be given on
  request (`LICENSE.txt` itself says it need not come through the online link). Only http and https URLs are
  accepted; the URL is escaped. The source viewer is not re-enabled.
- **NOTICE.** A root `NOTICE` names Legend of the Green Dragon 1.1.2 DragonPrime Edition by Eric Stevens and JT
  Traub, with the DragonPrime Development Team and the credited module authors; CC BY-NC-SA 2.0 plus `LICENSE.txt`;
  Resurrection's modifications under the same terms; noncommercial and not endorsed; third-party libraries under
  their own licenses. `README.md` links it.
- **Third-party licenses.** `docs/THIRD-PARTY-LICENSES.md`: no runtime Composer package; 26 development packages
  (MIT or BSD-3-Clause), not tracked in the repository. No incompatible license found.
- **Required text protected.** `tests/tooling/test_license_text.py` fails if `LICENSE.txt` or `README.txt` differs
  from the tag by a byte, or if the about page's files lose or alter an original copyright line or a sentence of
  the original about and license text. Added text is allowed, as the about page itself allows.
- **Kept unchanged:** `LICENSE.txt`, `README.txt`, every copyright notice (including `common.php`'s footer
  copyright and each file's `@copyright` docblock) and the about-page text. Removed as dead weight, with Jim's
  approval: Eric Stevens's PayPal-link request comments in `lib/pageparts.php` (a request the comments themselves say
  the license does not require) and `CHANGELOG.txt` (history only, no copyright or license notice). Both stay at
  `historical-source-1.1.2`.

What stays open, before any release or hosting:

- **Archive modules.** The 187-entry module archive has no blanket license grant; each module needs the component
  record above before admission. None is imported.
- **Images, skins and templates.** The inherited core images and the 18 skins (`templates/`) have no asset-level
  ledger. Their authorship and terms are not established beyond the core's distribution context.
- **Third-party material inside the 1.1.2 tree** that is not a Composer package (module code by credited authors,
  any embedded script or artwork) has not been reviewed file by file.
- **Text removed with the disabled surfaces.** 1.1.2's `source.php` and `logdnet.php` pages carried text about the
  license and copyright ("Removing valid copyright information ...", "Altering our copyright statement ..."). Those
  pages were replaced by stubs before this task and are now removed; the text stays at the tag. Whether it should
  appear somewhere in the running game is open; the about page's license text is kept in full.
- **Whether the source link and repository satisfy the "on request" terms** for a given operator is the operator's
  responsibility; the default URL fits only an unmodified copy of this repository.
- Rights-holder or qualified advice where any of this affects distribution or hosting plans.
