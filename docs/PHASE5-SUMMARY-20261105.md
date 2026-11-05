# Phase 5 summary: Resurrection UI (2026-10-30 to 2026-11-05)

Phase 5 replaced every 1.1.2 skin with one dark fantasy UI and original art. It ran from `836bb84` (task 1, 2026-10-30)
to the close-out commit (2026-11-05). This summary is written in the close-out. Phase 5 is complete when CI accepts
the close-out commit; the task after it records that acceptance. The next phase is Jim's to choose and is not opened.

The detail for each task is in its own doc. Every change a player, administrator or operator can notice is in
[`CHANGES-FROM-1.1.2.md`](CHANGES-FROM-1.1.2.md). The design contract is [`UI-DESIGN-SPEC.md`](UI-DESIGN-SPEC.md),
and the plan is [`PHASE5-UI-PLAN-20261030.md`](PHASE5-UI-PLAN-20261030.md).

## What Phase 5 changed, task by task

"Accepted" means both matrix legs of Modern core (PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4, all 12 jobs) and
Baseline integrity passed on that commit. Jim reordered tasks 5 to 15 on 2026-10-30 so the art came sooner; the
numbers below are the order the tasks ran in.

| Task | What it changed | Commit | Modern core | Baseline integrity | Doc |
|---|---|---|---|---|---|
| 1 | The new direction recorded; `CLAUDE.md` rewritten; Phase 4 closed; the UI plan and the design spec; `OutputBufferingTest` made independent of test order | `836bb84` | 41071411434 | 41071411378 | `PHASE5-TASK1-DIRECTION-20261030.md` |
| 2 | Design tokens (`assets/ui/tokens.css`), the three self-hosted font families with their OFL texts, all 41 color codes as classes; the changes ledger started | `4606b4a` | 41077391337 | 41077391247 | `PHASE5-TASK2-TOKENS-20261030.md` |
| 3 | `templates/resurrection.htm` and `assets/ui/resurrection.css`: header, three columns, footer with the credit line, popups; made the default skin | `976a61e` | 41094820755 | 41094820993 | `PHASE5-TASK3-TEMPLATE-20261030.md` |
| 4 | Header and footer: mail badge, MoTD and petition as links, time to the next new day, Credits where allowed, the Resurrection attribution line and `NOTICE`, the quieter color palette | `5752253` | 41111713295 | 41111713278 | `PHASE5-TASK4-HEADER-FOOTER-20261030.md` |
| 5 | The image pipeline (`src/Ui/Art.php`, `assets/LICENSE` CC BY-SA 4.0, the `scene` hook) and art batch 1: the village and forest scenes and the race portraits | `98fb6ec` | 41120765631 | 41120765632 | `PHASE5-TASK5-IMAGES-20261030.md` |
| 6 | Navigation: nav groups, key badges, the key script as a static file with a JSON key map, 44px rows | `d0f9415` | 41129318951 | 41129318835 | `PHASE5-TASK6-NAVIGATION-20261030.md` |
| 7 | Character panel: HP and XP bars, stat tiles, equipment rows, the portrait in the header card | `2a81203` | 41149508932 | 41149508958 | `PHASE5-TASK7-CHARACTER-PANEL-20261030.md` |
| 8 | Phone layout: the bottom tab bar (in-page anchors), the compact character header, nav tiles, the More section | `5b3ca63` | 41159564504 | 41159564474 | `PHASE5-TASK8-PHONE-20261031.md` |
| 9 | Forms, buttons and tables: labels on inputs, primary and secondary buttons, table styling, inline presentation replaced by classes | `cf13929` | 41196666950 | 41196666832 | `PHASE5-TASK9-FORMS-20261031.md` |
| 10 | Battle screen (fighter cards, actions, the log as rounds, the result block), the commentary list and pager, shop items as links; 1.1.2's broken draft script removed | `a9901d1`, accepted through `5dafec8` (the task 10 fix) | 41261243410 | 41261243423 | `PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md` |
| 11 | Popups (mail, MoTD with its poll, petition, translator), admin tables and settings tabs, every admin and editor input labelled, the moderation list | `28a3936` | 41311953273 | 41311953262 | `PHASE5-TASK11-POPUPS-ADMIN-20261101.md` |
| 12 | Art batch 2: the 17 place scenes, each place's page showing its own | `34a0617` | 41953000057 | 41952999724 | `PHASE5-TASK12-SCENES-20261105.md` |
| 13 | Art batch 3: the 18 line icons in the character panel; the tests' far-future dates pinned to the run's clock; shard durations refreshed | `6e2f55b` | 42029166571 | 42029165803 | `PHASE5-TASK13-ICONS-20261105.md` |
| 14 | Art batch 4: 313 creature, master and Green Dragon portraits from a parts kit, with its generator; the `creatureportrait` hook | `e3d265c` | 42055783450 | 42055783444 | `PHASE5-TASK14-PORTRAITS-20261105.md` |
| 15 | The 18 skins removed with skin selection (the cookie, the chooser, the Preferences row, the `defaultskin` and `homeskinselect` settings, the `theme` type) and every old-skin markup path | `5dfc363` | 42088078461 | 42088078447 | `PHASE5-TASK15-REMOVE-SKINS-20261105.md` |
| Close-out | The safe credit frame, the template notice for superusers only, the attribution line check, the 7 unused 1.1.2 images deleted, this summary | this commit | recorded by the next task | recorded by the next task | `PHASE5-CLOSEOUT-20261105.md` |

### The failed run on record

Task 10's first commit, `a9901d1`, failed Modern core run **41222167193** on the PHP 8.4 leg. Its test helpers used
`Dom\ParentNode::$children`, which exists only from PHP 8.5. The task 10 fix (`5dafec8`) replaced it with
`firstElementChild` / `nextElementSibling` and accepted `a9901d1` through itself (Modern core 41261243410, Baseline
integrity 41261243423). The failed run stays on record. Since then, `CLAUDE.md` requires PHPUnit to pass locally on
both 8.4 and 8.5 whenever a task adds or changes tests.

## Jim's decisions

| Date | Decision | Where |
|---|---|---|
| 2026-10-30 | The behaviorally faithful rule is dropped for everything. Gameplay, balance, text and UI may change when a task prompt says so, and each change is recorded (old, new, why) | `PHASE5-TASK1-DIRECTION-20261030.md` |
| 2026-10-30 | One new dark fantasy UI replaces all skins, built from the mockups Jim approved; the skins and skin selection are removed when it lands | same |
| 2026-10-30 | Original SVG art (scenes, creature portraits, item and stat icons, race portraits), drawn by Claude for Jim / Phoenix 2:33 LLC | same |
| 2026-10-30 | The game stays PHP on this codebase so others can host it. A clean-room AGPL-3.0-or-later rewrite is a possible future, not started | same |
| 2026-10-30 | The licensing split (below) | same |
| 2026-10-30 | The release, tags, deploy and hosting stay excluded until Jim says go | same |
| 2026-10-30 | Mockup corrections to the spec: radii, title and story sizes, key badge, bar heights, gutter; controls never on `--raised` | `PHASE5-TASK3-TEMPLATE-20261030.md`, `UI-DESIGN-SPEC.md` |
| 2026-10-30 | The quieter palette: 17 color-code values approved, the foe color `#C9553D` for bars only, damage text `#E8826A`, `--input-border` `#6E6452` | `PHASE5-TASK4-HEADER-FOOTER-20261030.md`, `UI-DESIGN-SPEC.md` |
| 2026-10-30 | Tasks 5 to 15 reordered so the images come sooner | `PHASE5-TASK4-HEADER-FOOTER-20261030.md` ("Task order") |
| 2026-10-30 | Art batch 1 (the village and forest scenes, the race portraits) attached to the task 5 brief | `PHASE5-TASK5-IMAGES-20261030.md` |
| 2026-10-31 | The phone tab bar's tabs are anchors on the same page (Go, Character, Talk, More), not a fixed Village link, which would break forced navigation | `PHASE5-TASK8-PHONE-20261031.md` |
| 2026-10-31 | 1.1.2's commentary draft script (a TypeError on every page with a comment box) is removed; affordable shop items are links, not filled buttons | `PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md` |
| 2026-11-01 | The battle log drops its "Round N" labels; a quiet divider separates rounds (the task 10 fix) | same |
| 2026-11-01 | Art batch 2, the 17 place scenes, approved | `PHASE5-TASK12-SCENES-20261105.md` |
| 2026-11-05 | Art batch 3, the 18 line icons, approved | `PHASE5-TASK13-ICONS-20261105.md` |
| 2026-11-05 | Art batch 4, the 313 portraits from the parts kit, approved | `PHASE5-TASK14-PORTRAITS-20261105.md` |
| 2026-11-05 | The skins removed (task 15). With one template left, a refused template falls back to a safe frame in code, and the attribution line is checked too; the template notice goes to superusers only (the close-out) | `PHASE5-TASK15-REMOVE-SKINS-20261105.md`, `PHASE5-CLOSEOUT-20261105.md` |

## Licensing

- **The core** stays CC BY-NC-SA 2.0, as an adaptation of LoGD 1.1.2. The original authors' credit line and the license
  block are on every page and popup. `LICENSE.txt` and `README.txt` stay byte-identical to `historical-source-1.1.2`.
  From the close-out, a template that drops them is refused and the safe frame carries them.
- **The art** in `assets/` (scenes, portraits, icons) is Jim's, drawn by Claude for Jim / Phoenix 2:33 LLC, under
  CC BY-SA 4.0, with its own `assets/LICENSE` (task 5) and checksums in `assets/README.md`.
- **The fonts** are self-hosted from @fontsource 5.3.0 under the SIL Open Font License 1.1, with their texts and
  SHA-256 hashes in `THIRD-PARTY-LICENSES.md`.
- **Resurrection's changes** and additions are Copyright (c) 2026 Phoenix 2:33 LLC, licensed under CC BY-NC-SA 2.0 as
  an adaptation of 1.1.2 (`README.md`, `NOTICE`). The attribution line "A Phoenix 2:33 LLC Project by Jim Lunsford"
  (links to https://phoenix233.com and https://jimlunsford.com) is on its own line under the 1.1.2 credit line, on
  every page and popup.
- **Jim's standalone code**, shipped as a separate work, is AGPL-3.0-or-later. No such work ships in this repository
  today, and no file is labelled AGPL.

## Open items

- **AGPL for bundled code is undecided.** AGPL code bundled in this repository and run inside the NC core may leave
  hosts unable to satisfy both licenses. Until Jim and a lawyer decide, no file is labelled AGPL.
- **The attribution wording is pending legal review** before release (`NOTICE`, `README.md`, the template line).
- **The release stays excluded**: hosting, tags, GitHub releases, an image, and the archive-module rights, until Jim says
  go. Public hosting has its own gates (`ROADMAP.md`, "Public hosting").

## 1.1.2 data oddities found

Both were found while drawing the portraits (task 14). Neither is changed; each is drawn as 1.1.2 seeds it.

- **The two Loneliness creatures.** 1.1.2 seeds the same creature twice: ids 134 and 135, both "Loneliness" with the
  weapon "Silence", at levels 17 and 18. They share one drawing, so two portrait files are the same picture.
- **Master 5's broken byte.** Master 5's seeded name has the replacement character U+FFFD where 1.1.2's file lost a byte
  (`55 6e ef bf bd 6c 69 74 68`, "Un?lith"; the historical tag has the same bytes). The game shows the seeded name;
  the portrait kit names it "Unélith" in the SVG's title only.
