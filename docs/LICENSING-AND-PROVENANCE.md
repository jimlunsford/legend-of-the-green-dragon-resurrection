# Licensing and provenance working position

This is an engineering rights ledger and working interpretation, not a legal ruling.

## Inherited core

The historical `LICENSE.txt` is preserved byte-for-byte. It identifies **Creative Commons Attribution-NonCommercial-ShareAlike 2.0**, links to that legal text, and supplies additional LoGD-specific source/public-performance expectations and exceptions.

The project is **noncommercial**. Section 4(c) of the license says: "You may not exercise any of the rights granted to
You in Section 3 above in any manner that is primarily intended for or directed toward commercial advantage or private
monetary compensation." `NOTICE` quotes the whole clause. Do not claim commercial use is permitted. Retain attribution, copyright notices and ShareAlike obligations. Modifications are contemplated by the inherited terms, but that does not permit blanket relicensing to MIT, GPL or AGPL. Later LoGD code cannot be imported into the old GPL line as a relicensing shortcut.

The CC legal code and LoGD's explanatory/additional text are distinct evidence. Do not describe generic CC BY-NC-SA as an AGPL-style source license. `LICENSE.txt` says, in its own words, that "if you put this game on a web server and allow people 'outside of a
normal circle of family and its social acquaintances' to play there, then you are publically performing this work and
MUST either a) make any and ALL changes which you make to the game available on request (note this doesn't have to be
available via the online source display link, but they must be able to ask you for the code AND receive it in toto),
b) make arrangements privately with the authors wherein they grant you a special license, or c) remove the code
entirely from the machine." This page does not interpret it; read `LICENSE.txt` and the legal code in full. The game
offers its source through the `sourceurl` link (2026-10-24, below); the source viewer stays disabled.

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
  ledger. Their authorship and terms are not established beyond the core's distribution context. (2026-11-05: the
  skins part is closed: Phase 5 task 15 removed the skins, see below. The inherited core images in `images/` stay
  open.)
- **Third-party material inside the 1.1.2 tree** that is not a Composer package (module code by credited authors,
  any embedded script or artwork) has not been reviewed file by file.
- **Text removed with the disabled surfaces.** 1.1.2's `source.php` and `logdnet.php` pages carried text about the
  license and copyright ("Removing valid copyright information ...", "Altering our copyright statement ..."). Those
  pages were replaced by stubs before this task and are now removed; the text stays at the tag. Whether it should
  appear somewhere in the running game is open; the about page's license text is kept in full.
- **Whether the source link and repository satisfy the "on request" terms** for a given operator is the operator's
  responsibility; the default URL fits only an unmodified copy of this repository.
- Rights-holder or qualified advice where any of this affects distribution or hosting plans.

## 2026-10-30: Resurrection's original art

Phase 5 task 5 (`PHASE5-TASK5-IMAGES-20261030.md`). An engineering record, not a legal ruling.

- **Original art.** Scene banners and race portraits in `assets/scenes/` and `assets/portraits/`, original SVG drawn by
  Claude for Phoenix 2:33 LLC and approved by Jim, committed byte for byte from the command chat with their SHA-256
  checksums. Copyright (c) 2026 Phoenix 2:33 LLC, licensed under CC BY-SA 4.0 in `assets/LICENSE`, which names its
  scope exactly: `assets/scenes/`, `assets/portraits/` and `assets/icons/`. It does not cover `assets/fonts/` (SIL OFL
  1.1, `THIRD-PARTY-LICENSES.md`) or `assets/ui/` (code, under the core license). `NOTICE` and `assets/README.md`
  state the same, with the credit "Art by Phoenix 2:33 LLC, CC BY-SA 4.0".
- The art is a separate work shown by the game, not an adaptation of the 1.1.2 images: none of the inherited core
  images or skins is used or traced. The inherited images and skins keep the open item above until task 15 removes
  the skins.

## 2026-11-05: the 1.1.2 skins removed

Phase 5 task 15 (`PHASE5-TASK15-REMOVE-SKINS-20261105.md`). An engineering record, not a legal ruling.

- **The skins rights item is closed.** The 18 skins (each `.htm`, its stylesheet and its image folder: 124 files) are
  removed from `templates/`, and skin selection with them. Resurrection ships one template, `templates/resurrection.htm`,
  its own work under the core license. No 1.1.2 skin, stylesheet or skin image is shipped, so their unestablished
  authorship and terms no longer bear on what the repository distributes.
- The skins remain byte for byte, with their designers' credits, at `historical-source-1.1.2`; the tag is unchanged.
  `NOTICE` names each skin's credited designer and says where they remain.
- Still open: the inherited core images in `images/` (the task lists the ones nothing references any more; none is
  deleted), and the other items above.

## 2026-11-12: the credit within the license (Phase 6 task 11)

`PHASE6-TASK11-20261112.md`. An engineering record, not a legal ruling.

- **S9 closed by decision.** Jim decided on 2026-11-12 not to seek legal counsel: the project meets the licenses as
  written, and every statement of the credit stays within what the licenses allow. `LEGAL-REVIEW-PACKET-20261107.md`
  was not sent and is kept as a record.
- **The credit is section 4(d)'s.** Section 4(d) of CC BY-NC-SA 2.0 is the credit clause (4(c) is the noncommercial
  clause). For a Derivative Work it asks for "a credit identifying the use of the Work in the Derivative Work", the URI
  the Licensor specifies "to the extent reasonably practicable", and that "at a minimum such credit will appear where
  any other comparable authorship credit appears and in a manner at least as prominent as such other comparable
  authorship credit." Sections 4(a) and 4(b) say that you "may not offer or impose any terms" that "alter or restrict the
  terms of this License". `NOTICE`, `README.md`, `HOST-GUIDE.md` and the release notes now state the Resurrection credit
  in 4(d)'s terms and add no term, condition or penalty of their own. The template check and the built-in frame are
  described as how the software behaves, not as a license term.
- **Host duties are quoted.** Where a shipped document states what a host must do, it quotes `LICENSE.txt` or the legal
  code; this page's "Inherited core" section quotes `LICENSE.txt` in place of the earlier reading of it.
- **Kept unchanged:** `LICENSE.txt`, `README.txt`, the rendered credit and attribution lines, their links and where they
  appear, the template check, the XOR checks and the safe frame. `tests/tooling/test_credit_wording.py` keeps the
  shipped documents free of wording that adds a term.
