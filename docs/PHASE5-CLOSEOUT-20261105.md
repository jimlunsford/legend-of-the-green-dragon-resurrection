# Phase 5 close-out: the safe credit frame, the unused images, the phase summary (2026-11-05)

Jim's brief, "PHASE 5 CLOSE-OUT: SAFE CREDIT FRAME, UNUSED IMAGES, PHASE SUMMARY". Expected start: `main` at
`5dfc36335c3e713c27e7ef05f218a3b57baeb054` (it matched). Task 15's acceptance is recorded first, in
`PHASE5-TASK15-REMOVE-SKINS-20261105.md` and `ROADMAP.md`. What the whole phase changed is in
[`PHASE5-SUMMARY-20261105.md`](PHASE5-SUMMARY-20261105.md).

## Why

1.1.2's `prepare_template()` refused a skin that failed its required-tag check (for example, with `{copyright}`
removed) and rendered a different, valid skin, `jade.htm`. Task 15 left one template, so the fallback was the same
file: a template without `{copyright}` was refused, then rendered anyway, with no credit line. The original authors'
credits and the license block must be on every page, and so must Jim's attribution line (`CLAUDE.md`). A host must not
be able to drop them by editing one file.

## The safe frame

`src/Ui/SafeFrame.php` (`Resurrection\Ui\SafeFrame`) holds a minimal template in PHP, not as a file under
`templates/`, so editing or deleting the template file cannot remove it.

- **Parts.** It has all 51 parts of `templates/resurrection.htm`, with the same keys in the same order, so no page prints
  a missing-part warning. The 47 component parts (the character panel, nav, battle, login, art slots and so on) are
  the template's own. The page frame (`header`, `footer`, `popuphead`) is the template's, less the phone's pieces:
  `{phonehead}`, `{tabbar}` and the More section, which only the stylesheet keeps hidden on a desktop. `popupfoot` is
  the template's.
- **Tags.** The header has `{title}`, `{headscript}` and `{script}`. The header or footer has `{nav}`, `{stats}`,
  `{petition}`, `{motd}`, `{mail}`, `{paypal}`, `{source}`, `{version}` and `{copyright}`. So the frame passes every
  check it stands in for.
- **Credits.** `{copyright}` is in the footer and in the popup footer. It becomes the 1.1.2 credit line and the license
  block. The attribution line "A Phoenix 2:33 LLC Project by Jim Lunsford", with both links, is under it in both.
- **Stylesheet.** The frame links `assets/ui/resurrection.css`, and it renders correctly even if that file is
  missing. It is plain markup that still reads, with the credit line, the license block and the attribution line as
  text and links. One `<style>` rule, `img { max-width: 100%; height: auto }`, comes before the stylesheet (which has the
  same rule), so the art never grows past the page. The rule has spaces inside its braces: `page_header()` and
  `page_footer()` strip any `{...}` without one as an unused tag. This was found when the first version's
  `img{max-width:100%;height:auto}` vanished from the page. The body gets the class `rz-safe-frame` (popups: `rz-page
  rz-popup rz-safe-frame`).

## prepare_template()

`lib/template.php`:

1. **Fallback.** When `templates/resurrection.htm` is missing or unreadable (`is_file` and `is_readable`), or fails
   its checks, `$template` is the safe frame. `loadtemplate()` (`lib/pageparts.php`) also returns the safe frame for a
   missing or unreadable file instead of raising a warning. A part the checks read (`header`, `footer`, `popupfoot`)
   that the file lacks counts as empty and is named in the reasons, so the checks run as written.
2. **Checks kept exactly.** These are unchanged:
   - the header's required tags;
   - the empty footer list;
   - the "may appear anywhere but must appear" list, with its XOR push
     `if (!$key) array_push($templatetags,$y2^$z2);`, which names `copyright` a second time;
   - the copyright line (`$z = $y2^$z2;` ... `$$z = $lc . $$z . "<br />";`);
   - `common.php`'s `$copyright` / `$license` XOR checks (`strcmp($cp^$y,$z)`, `strcmp($l^$a,$b)`).

   `git diff 5dfc363` shows no changed line in any of them; `common.php` is not in the diff at all.
   `tests/tooling/test_safe_frame.py` pins each block's SHA-256 as it was at `5dfc363`.
3. **One check added.** `footer` and `popupfoot` must each carry the attribution line with both exact links
   (`SafeFrame::ATTRIBUTION`, the template's own markup). If either is missing, or a link changed, the template is
   refused and the safe frame is used.
4. **The check still runs whenever the template file changes.** The session caches the name and mtime only when the
   check passed, as in 1.1.2. A refused template is checked again on every page, as in 1.1.2, until it is fixed.
5. **The copyright line now always runs.** 1.1.2 skipped it for a refused skin, so its fallback showed the credit line
   without the license block. Now it runs for the safe frame too, so the frame carries both. The inner lines are
   byte-identical; only the `else` around them is gone.

## The notice

1.1.2 printed "You have one or more errors in your template page!" and the reasons to everyone, before the page's own
markup (before the doctype).

Now `resurrection_template_refused()` does two things:

- It writes one line to the error log for each request: `Resurrection: templates/resurrection.htm was refused; the
  safe frame is used: <reasons>`.
- It keeps the notice: the same heading and reasons in `<div class="rz-template-notice" role="alert">`, escaped, with
  the braces as `&#123;` / `&#125;`. Without the entities, `page_header()` would strip `{copyright}` from the reason as
  an unused tag.

`resurrection_template_notice()` returns the notice only to a signed-in superuser (an account with the Grotto,
`superuser & ~SU_DOESNT_GIVE_GROTTO`, as `village.php` decides it). `page_header()` and `popup_header()` append it to
the header, so it sits inside the safe frame's page at the top of the content card. Players and signed-out visitors
see a normal page in the safe frame. The change is recorded in `CHANGES-FROM-1.1.2.md`.

**Limits.** The checks verify that the credit tag and the attribution line are present, not that they are visible: a
rule in `assets/ui/resurrection.css`, or markup around the line, could still hide them. That is outside this task.
Refusing an unreadable file could not be shown locally, because the tests run as root, and root reads any file. The
missing-file path covers the same branch (`$readable` is false for both).

## The images removed

The 7 images task 15 found unreferenced were checked again before deletion. A search of every shipped text file (not
`docs/`, `tests/`, `vendor/` or `images/`) for each file name, case-insensitive, found none: `headbkg.GIF`,
`logindragon.gif`, `lscroll.GIF`, `oldscroll.GIF`, `rule.gif`, `title.gif` and `uscroll.GIF`. Two tests name two of
them only to assert their absence from a page (`PopupAdminRenderTest`: `oldscroll.GIF`, `rule.gif`).

All 7 were deleted from `images/`. They remain byte for byte at `historical-source-1.1.2`. `images/` keeps `new.gif`,
`newscroll.GIF` (bio, list and petition pages), `somerights20.gif` (the license block) and `trans.gif`. The historical
verifier still passes (417 files).

## Tests

**`tests/unit/TemplateRenderTest.php`.** Task 15's copyright section asserted 1.1.2's behavior: the refusal printed on
the page and no credit line. The brief changes that behavior, so the section is replaced. A copied tree renders with
four broken templates:

- the template without `{copyright}`;
- the template without the attribution line;
- the template with one attribution link changed (`phoenix233.example`);
- no template file at all.

For each one:

- **home.php, signed out.** The page renders in the safe frame (doctype, body class, stylesheet and style rule). It has
  no missing-part, piece or runtime warning, and the 1.1.2 credit line exactly once. It has the license block
  ("Creative Commons License") exactly once and the attribution line exactly once, after the credit line. It ends with
  `</html>`, and it has no notice.
- **The error log.** It has exactly one refusal line with the expected reason. "You do not have {copyright} ..." is
  logged twice, once for the list and once for the XOR tag, as 1.1.2 counts it. Nothing else is missing.
- **motd.php, a popup.** The popup safe frame, with the credit, license and attribution lines once each, and no notice.
- **village.php as a superuser.** The safe frame, with the notice once. The escaped reason appears as many times as it
  was logged, and the notice is inside the content card.

The restored template renders with no safe frame for a signed-out visitor, and with no safe frame and no notice for a
superuser; nothing is logged.

**`tests/tooling/test_safe_frame.py`** (new, static):

- the frame's parts and keys equal the template's, with the frame-only exceptions above;
- the required tags, `{copyright}` and the attribution line are in the footer and popup footer;
- the frame stands without its stylesheet: no `{...}` the tag cleanup would strip, no external resource, ASCII only;
- it is wired into `prepare_template()`, `loadtemplate()` and both headers;
- the 1.1.2 checks are pinned by SHA-256;
- the 7 images are gone and nothing names them.

**`tests/tooling/test_ui_template.py`.** It pinned the line `$template=loadtemplate("resurrection.htm");`, the
refused-template fallback the brief replaces. It now pins the safe-frame lines, and that no other template name is
loaded.

**`tests/tooling/test_ui_forms.py`.** The inline-presentation scan reads PHP files only, so the frame's copies of the
template's bar parts (`style="width: {percent}%"`, as in the template) are listed with their reason.

### Failing first

**On `5dfc363`** (the new tests copied into a worktree), each variant run alone:

| Variant | Result on `5dfc363` |
|---|---|
| no `{copyright}` | FAIL: the page is 1.1.2's refusal text ("You have one or more errors ...") with no frame |
| no attribution line | FAIL: the page renders the template, not the safe frame |
| a changed attribution link | FAIL: the same |
| no template file | FAIL: home.php exits 255 (a runtime error), not 0 |

`test_safe_frame.py` on `5dfc363`: 2 failures and 3 errors (no `SafeFrame.php`, nothing wired in, the images still
there). The pinned-checks test passes there, as it should: it pins `5dfc363`.

**On broken copies of the new code** (each change alone, `TemplateRenderTest`):

| Broken copy | Result |
|---|---|
| A: the attribution check removed | FAIL at "home.php, no attribution line": not the safe frame |
| B: refused but rendered anyway (the safe-frame assignment removed) | FAIL at "home.php, no {copyright}": not the safe frame |
| C: the notice shown to everyone | FAIL at "no {copyright}": the signed-out page shows the notice |
| D: the refusal not logged | FAIL at "no {copyright}": 0 refusal lines, expected 1 |
| The XOR push changed (`if (!$key)` to `if (false)`) | `test_safe_frame.py` FAIL: the template.php block's hash differs |
| `common.php`'s license check changed (`strcmp($l^$a,$b)` to `false`) | `test_safe_frame.py` FAIL: the common.php block's hash differs |

**After:** all pass (below).

## Local verification (supporting evidence only)

- **PHPUnit:** 341 tests, 146,687 assertions, OK on PHP 8.5.12 and on PHP 8.4. The first full 8.5 run found one error
  in the new test: `@unlink()` of a missing file threw, because an earlier test's error handler throws on suppressed
  warnings. It was fixed with `is_file()` guards, and both versions then passed.
- **Four shards**, with `RESURRECTION_REQUIRE_PHP=1`, PHP 8.5 and MySQL 8.4, one container each: all pass. Shard 1: 58 tests in 16.7 min. Shard 2: 57 (56 + 1 prerequisite) in 16.8 min. Shard 3: 56 (55 + 1) in 16.7 min. Shard 4: 166 (165 + 1) in 16.3 min. Each shard copy also ran PHPUnit first (about 85 s). Those copies were taken before the `is_file()` fix above, so they show the same single error. The fixed tree passed the full PHPUnit run on both versions.
- **Both PHPStan gates:** no errors. **PHP lint:** 408 files, 0 failures.
- **Composer:** `composer validate --strict` valid; `composer install` and `composer audit` show no advisories.
- **Historical verifier:** PASS, 417 files, 11 commits. **Project validator:** PASS.
- **`git diff --cached --check`:** clean.

## Screenshot

One desktop sheet, 1440x1000 and not committed, was attached to the report. It shows the village in a tree whose
`templates/resurrection.htm` lacks `{copyright}`, rendered in the safe frame twice:

- as a player: no notice;
- as a superuser: the notice at the top of the content, with both `{copyright}` reasons.

Both pages show the credit line, the license block and the attribution line once each, with no sideways scroll. The
same tree with the stylesheet blocked was also checked: the home page reads as plain markup with the credits, the
license and the attribution line, and the scene no wider than the page.

## CI

Recorded by the next task: the close-out's Modern core and Baseline integrity runs. Phase 5 is complete on their
acceptance.
