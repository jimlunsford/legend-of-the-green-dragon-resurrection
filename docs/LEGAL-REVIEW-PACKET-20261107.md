# Legal review packet: licensing and attribution (S9)

For counsel. Prepared 2026-11-07 in Phase 6 task 7, from the repository at the commit that adds this file (its parent
is `5f2d951`). It holds facts and questions only. It draws no legal conclusion and changes no wording: every quoted
text below is as it stands in the repository. The answers go to Jim Lunsford (Phoenix 2:33 LLC), who decides any
change; a change lands in a later task.

## 1. The project in brief

- **What it is.** Legend of the Green Dragon - Resurrection is a browser role-playing game in PHP. It is a modified
  version of Legend of the Green Dragon 1.1.2 DragonPrime Edition (by Eric Stevens and JT Traub, continued by the
  DragonPrime Development Team and credited module authors). People run it on their own web servers and let others
  play it in a browser.
- **Where it is.** A public GitHub repository, `jimlunsford/legend-of-the-green-dragon-resurrection` (public as
  recorded on 2026-11-06, `docs/PHASE6-RC-READINESS-20261106.md`, section 4.3). The exact 1.1.2 source is preserved
  unchanged under the tag `historical-source-1.1.2`.
- **What is planned.** A first test build, `1.0.0-rc.1`, offered to hosts and players to try, then a release, 1.0.0.
  Both would be published as a source tag and a zip of the files a host uploads (`scripts/build-release-package.py`).
  Neither has been published. The project calls itself noncommercial (`NOTICE`, `README.md`).
- **Who holds what.** The 1.1.2 code: its authors, as stated in the source files and `NOTICE`. Resurrection's
  modifications and additions and its original art: Phoenix 2:33 LLC, as stated in `NOTICE`, `README.md` ("Credit and
  attribution") and `assets/LICENSE`.

## 2. Licenses and the files each covers

| License | Files it covers | Where it is stated |
|---|---|---|
| Creative Commons Attribution-NonCommercial-ShareAlike 2.0 (CC BY-NC-SA 2.0), plus the authors' own terms in `LICENSE.txt` | The 1.1.2 code and text, and Resurrection's modifications and additions to it: every file not listed in another row, including the PHP code, `templates/resurrection.htm`, `assets/ui/` (stylesheets and scripts), `modules/` and the translations | `LICENSE.txt` (the 1.1.2 authors' plain-English terms; it points to the CC BY-NC-SA 2.0 legal code online), `README.txt`, `NOTICE`, the comment headers of `common.php`, the license block on every page (section 3.1) |
| Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0) | The original art in `assets/scenes/`, `assets/portraits/` and `assets/icons/`, and nothing else | `assets/LICENSE` (with the full license text), `assets/README.md`, `NOTICE` ("Art") |
| SIL Open Font License 1.1 (OFL) | The fonts in `assets/fonts/cinzel/`, `assets/fonts/alegreya/` and `assets/fonts/alegreya-sans/` (woff2 files, unmodified, from the npm `@fontsource` packages 5.3.0) | Each folder's `OFL.txt`; `docs/THIRD-PARTY-LICENSES.md` ("Bundled fonts", with each file's SHA-256) |
| MIT and BSD-3-Clause | 26 development packages (PHPUnit, PHPStan and their dependencies), installed by `composer install` into `vendor/`. They are not in the repository and not in the release package; the game does not use them | `docs/THIRD-PARTY-LICENSES.md` |
| AGPL-3.0-or-later (intended, not used) | No file. The stated intent is that Jim Lunsford's standalone code, shipped as a separate work, would be AGPL-3.0-or-later. A clean-room rewrite under that license is described as a possible future, not started. Until counsel answers, the rule is to label no file AGPL (`CLAUDE.md`, "Licensing"). A search of the repository at this commit finds "AGPL" only in documents and in one test that checks a file is not labelled AGPL | `CLAUDE.md`, `docs/PHASE5-SUMMARY-20261105.md` |

Other facts on the files:

- `LICENSE.txt` and `README.txt` are byte-identical to the 1.1.2 tag; a test fails if they change
  (`tests/tooling/test_license_text.py`).
- `LICENSE.txt` says, among other things, that a host who lets people "outside of a normal circle of family and its
  social acquaintances" play a modified copy must make all changes available on request, arrange a special license
  with the authors, or remove the code. It makes an exception for some standalone modules. It names Eric Stevens and
  JT Traub and grants the DragonPrime developers a revocable authority to produce new distributions.
- Four inherited 1.1.2 images remain in `images/` (`new.gif`, `newscroll.GIF`, `somerights20.gif`, `trans.gif`), with no
  recorded author or terms beyond the 1.1.2 distribution; `somerights20.gif` is the Creative Commons badge in the
  license block. `docs/LICENSING-AND-PROVENANCE.md` keeps them as an open item.
- The 24 bundled modules in `modules/` came with 1.1.2 and name their authors in their own code. They have not been
  reviewed file by file for rights (`docs/LICENSING-AND-PROVENANCE.md`). None of the 187 modules in the separate 1.1.2
  module archive is included.
- The release package holds `LICENSE.txt`, `README.txt`, `NOTICE`, `assets/LICENSE`, `assets/README.md` and the three
  `OFL.txt` files, byte-identical to the repository (`tests/tooling/test_release_package.py`). The host guide's sample
  server rules refuse every Markdown file over the web, so `assets/README.md` is in the package but not served to
  players; the guide does not say whether `LICENSE.txt`, `NOTICE` or `assets/LICENSE` are reachable over the web.

## 3. The credits on every page

### 3.1 What a player sees

Every page's footer carries, in this order (`templates/resurrection.htm`, `footer`):

1. The 1.1.2 license block, from `common.php`: the Creative Commons badge and "This work is licensed under a Creative
   Commons License", linking `http://creativecommons.org/licenses/by-nc-sa/2.0/`.
2. The 1.1.2 copyright line, from `common.php`: "Game Design and Code: Copyright © 2002-2005, Eric Stevens & JT
   Traub, © 2006-2007, Dragonprime Development Team". The game joins items 1 and 2 into the template's `{copyright}`
   tag (`lib/template.php`).
3. A "View PHP Source" link to the `sourceurl` setting (section 4).
4. The version line: "Version: Resurrection 1.0.0-dev, based on LoGD 1.1.2 Dragonprime Edition".
5. On its own line, the Resurrection attribution line:

   > A Phoenix 2:33 LLC Project by Jim Lunsford

   with "Phoenix 2:33 LLC" linking to https://phoenix233.com and "Jim Lunsford" to https://jimlunsford.com. The exact
   HTML, in the template's `footer` and `popupfoot` (lines 46 and 80):

   ```html
   <p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>
   ```

A popup's footer (`popupfoot`) carries the license block, the copyright line and the attribution line, without the
source link or version.

The game's about page keeps 1.1.2's license text in full. The art credit ("Art by Phoenix 2:33 LLC, CC BY-SA 4.0") appears in
`NOTICE`, `assets/LICENSE` and `assets/README.md`, not on the game's pages. The fonts' notices are in their `OFL.txt`
files, not on the game's pages.

### 3.2 How the game enforces it

- `prepare_template()` (`lib/template.php`) checks the page template whenever its file changes. It keeps 1.1.2's
  checks unchanged: the required template tags, including `{copyright}`, and 1.1.2's encoded checks that the copyright
  line and license block are unaltered (`common.php`'s `$copyright` and `$license`). It adds one check: the
  attribution line, with both exact links, in both `footer` and `popupfoot` (`SafeFrame::hasAttribution()`,
  `src/Ui/SafeFrame.php`).
- A template that is missing, unreadable or fails any check is not used. The game draws a minimal built-in frame
  instead (`src/Ui/SafeFrame.php`, in code, not a file a host edits in `templates/`), which carries the copyright line,
  the license block and the attribution line. The refusal is shown to administrators and written to the error log.
- Tests hold these: `tests/tooling/test_safe_frame.py`, `tests/tooling/test_ui_template.py`,
  `tests/tooling/test_one_template.py`.
- A host who edits the PHP code can remove any of this. The checks stop an edited template, not an edited program.

### 3.3 What the project's documents say about it

- `NOTICE` ("Resurrection", "Credit."):

  > Credit. Section 4 of the CC BY-NC-SA 2.0 license requires anyone who distributes or publicly performs this work,
  > or an adaptation of it, to keep its copyright notices intact and to credit its authors, in an adaptation where any
  > other comparable authorship credit appears and at least as prominently. For Resurrection that credit is:
  >
  >     A Phoenix 2:33 LLC Project by Jim Lunsford
  >
  > with "Phoenix 2:33 LLC" linking to https://phoenix233.com and "Jim Lunsford" linking to https://jimlunsford.com.
  > The game shows it in the footer of every page and popup, on its own line under the original authors' credit line.
  > Anyone who hosts, distributes or adapts Resurrection keeps this notice intact and keeps that credit, with both
  > links, where the original authors' credit appears (the footer of every page) and at least as prominently. This is
  > the credit the license already requires, not an added term.

- `README.md` ("Credit and attribution") says the same in shorter form: "Anyone who hosts, distributes or adapts
  Resurrection keeps that credit and its links where the original authors' credit appears, at least as prominently,
  as the license's attribution section requires."
- `docs/HOST-GUIDE.md`, section 6, "The credits stay":

  > Every page's footer carries the 1.1.2 copyright and license block and the line "A Phoenix 2:33 LLC Project by Jim
  > Lunsford" with its two links. The license terms require them: do not remove, hide or shrink them. If the page
  > template is changed so that it lacks them, the game refuses it and draws its built-in frame with the credits
  > instead. (The attribution line's wording is under legal review; this is the rule as it stands today.)

- `docs/HOST-GUIDE.md`, section 2, lists `LICENSE.txt`, `README.txt` and `NOTICE` as "the license and the credits, which
  must travel with the game".

## 4. What the host guide asks of a host

- **Source.** Section 6, "Public source code URL" (`sourceurl`): "the 'View PHP Source' link in every footer. The license
  (`LICENSE.txt`) requires you to give the complete source to anyone you let play. The default points at the project's
  public repository, which is right only for an unchanged copy. If you change the code, publish your changed source and
  point this setting at it." The setting accepts only http and https addresses
  (`lib/configuration_descriptors.php`, `lib/source_link.php`).
- **Credits.** Section 6, "The credits stay", quoted in 3.3.
- **Files.** Section 2 lists the license and credit files among the files to upload.
- The guide says nothing about commercial use, donations or advertising.

## 5. Money in the game today

Facts for question 4:

- 1.1.2's PayPal payments are removed: the payment notification page, the payment log and the footer's PayPal buttons
  (`docs/CHANGES-FROM-1.1.2.md`, "Payments"). No payment account is connected.
- 1.1.2's donation points remain: a staff member can record "donation points" for a player by hand
  (`donators.php`), and a player spends them at the Hunter's Lodge (`lodge.php`) on in-game perks. Nothing in the game
  takes money.
- No bundled module shows advertising. The module policy forbids importing the archive's advertising modules
  (`docs/MODULE-POLICY.md`).
- `project.json` records `"commercial_use_approved": false` and `"public_game_hosting_approved": false`.

## 6. Questions

1. **The attribution line.** On top of CC BY-NC-SA 2.0 and `LICENSE.txt`, the project asks every host to keep "A
   Phoenix 2:33 LLC Project by Jim Lunsford", with its two links, where the original authors' credit appears and at
   least as prominently, and the game refuses a template without it.
   1. Is this a credit that section 4 of CC BY-NC-SA 2.0 lets the author of an adaptation require, or is it an added
      term or restriction of the kind the license does not allow on the work it covers?
   2. Does the answer change because the line includes links, or because the game enforces it in code?
   3. Is the wording in `NOTICE` ("This is the credit the license already requires, not an added term") accurate? If
      not, what wording would be?
   4. Do the 1.1.2 authors' terms in `LICENSE.txt`, or their grant to the DragonPrime developers to produce
      distributions, bear on whether a third party may add a credit of its own to an adaptation?
2. **AGPL-3.0-or-later code beside the CC BY-NC-SA material.** Jim intends his standalone code, shipped as a separate
   work, to be AGPL-3.0-or-later.
   1. Can AGPL-3.0-or-later code be shipped in the same repository or release package as the CC BY-NC-SA 2.0 game, and
      run inside it (loaded by the game's PHP), so that a host can meet both licenses at once?
   2. If yes, how should each file be labelled, and what must the package and `NOTICE` say?
   3. If not, what separation would be needed (a separate repository, a separate package, a separate program reached
      over a network, or something else)?
   4. Does the AGPL's network clause interact with `LICENSE.txt`'s source-on-request term for a host who runs both?
3. **The host guide.** Does section 6 of `docs/HOST-GUIDE.md` (quoted in sections 3.3 and 4) state a host's duties
   correctly and completely? In particular:
   1. Is pointing the "View PHP Source" link at a published copy of the modified source enough for `LICENSE.txt`'s
      "available on request ... in toto"?
   2. Should the guide name other duties, for example keeping `LICENSE.txt`, `NOTICE` and the art and font license
      files reachable by players, or offering the source to people who ask by other means?
4. **NonCommercial.** The license forbids use "primarily intended for or directed toward commercial advantage or
   private monetary compensation".
   1. May a host take donations to pay for the server? Does the answer change if donors get in-game perks (the
      donation points in section 5)?
   2. May a host show advertising on the game's pages, or on pages around it?
   3. Does Phoenix 2:33 LLC being a company bear on whether the project itself stays noncommercial, for example if it
      hosts a server or links the game from its own site?
5. **The art and the fonts in the package.**
   1. The art is CC BY-SA 4.0 inside a game under CC BY-NC-SA 2.0. Is anything more needed in the package, the game or
      `NOTICE` for a host to meet CC BY-SA 4.0, for example showing the art credit to players?
   2. Do the art's ShareAlike terms reach the rest of the game, or the game's terms reach the art?
   3. The fonts are OFL 1.1, unmodified, each with its `OFL.txt` in the package. Is anything more needed?
6. **Inherited material.** Do the four inherited images in `images/`, or the 24 bundled 1.1.2 modules whose authors are
   named only in their code, need anything before they ship in a release package?
7. **Text removed with disabled pages.** 1.1.2's source viewer and LoGDnet pages carried text about the license and
   copyright ("Removing valid copyright information ...", "Altering our copyright statement ..."). Those pages are
   removed (the text stays at the historical tag); the about page keeps 1.1.2's license text in full. Must that text
   appear in the running game?

## 7. Where the facts come from

`LICENSE.txt`, `README.txt`, `NOTICE`, `README.md`, `assets/LICENSE`, `assets/README.md`, `assets/fonts/*/OFL.txt`,
`docs/THIRD-PARTY-LICENSES.md`, `docs/LICENSING-AND-PROVENANCE.md`, `docs/HOST-GUIDE.md`, `docs/MODULE-POLICY.md`,
`docs/CHANGES-FROM-1.1.2.md`, `docs/PHASE6-RC-READINESS-20261106.md` (sections 4.1 to 4.5), `CLAUDE.md`, `common.php`,
`templates/resurrection.htm`, `lib/template.php`, `src/Ui/SafeFrame.php`, `lib/source_link.php`,
`lib/configuration_descriptors.php`, `donators.php`, `lodge.php`, `project.json`, and the tests named above.
