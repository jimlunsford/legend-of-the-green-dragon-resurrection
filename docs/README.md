# Documents

The documents for Legend of the Green Dragon - Resurrection. There are two kinds:

- **Living documents** are guides and references that describe the game and the project as they are now. A change to
  what they describe updates them in the same commit.
- **Records** are evidence of a task, a phase or a checkpoint, written when the work was done. They are kept as
  written: later facts are added to the end, never rewritten. They link to each other by path, so no file here is moved
  or renamed.

The project's overview is the [README](../README.md); the plan and its progress are in [ROADMAP.md](../ROADMAP.md).
`tests/tooling/test_doc_links.py` checks that every file in this folder is listed here exactly once and that the links
in the living documents resolve.

## Start here

- [HOST-GUIDE.md](HOST-GUIDE.md): how to run the game on a web server, with a shell or with only a hosting panel.
- [RELEASE-NOTES-1.0.0-rc.5.md](RELEASE-NOTES-1.0.0-rc.5.md): the current test build, `1.0.0-rc.5` (2026-11-19): what
  is in it, how to upgrade, and the known issues.
- [CHANGES-FROM-1.1.2.md](CHANGES-FROM-1.1.2.md): every deliberate change from LoGD 1.1.2 that a player, administrator
  or host can notice: the old behavior, the new behavior and why.

## Reference

Living documents.

- [MODERN-INSTALLATION.md](MODERN-INSTALLATION.md): play on your own computer with Docker, and how the install and
  upgrade commands behave, for development.
- [SUPPORTED-PLATFORMS.md](SUPPORTED-PLATFORMS.md): the PHP and database versions the project supports and tests.
- [VERSIONING.md](VERSIONING.md): the version number, test builds, releases and upgrades between them.
- [RC-CUT-CHECKLIST.md](RC-CUT-CHECKLIST.md): how a test build or release is cut, with the record of each cut.
- [SECURITY-STATUS.md](SECURITY-STATUS.md): each security control's current state, and the public hosting gates.
- [MODULE-AUTHOR-GUIDE.md](MODULE-AUTHOR-GUIDE.md): the rules a module follows, for anyone writing or porting one.
- [MODULE-POLICY.md](MODULE-POLICY.md): which modules ship, and what admitting another one takes.
- [UI-DESIGN-SPEC.md](UI-DESIGN-SPEC.md): the interface's design contract: palette, type, layout, components, art and
  accessibility.
- [THIRD-PARTY-LICENSES.md](THIRD-PARTY-LICENSES.md): the fonts and development packages that are not the project's
  own, with their licenses.
- [LICENSING-AND-PROVENANCE.md](LICENSING-AND-PROVENANCE.md): the project's licensing position, with its history.
- [hosting/nginx.conf](hosting/nginx.conf), [hosting/apache.conf](hosting/apache.conf) and
  [hosting/htaccess](hosting/htaccess): sample web server rules for the host guide, section 3. Samples, not active.
- [package/INSTALL.txt](package/INSTALL.txt): the template for `INSTALL.txt` in the release package.
- [provenance/baseline.json](provenance/baseline.json) and
  [provenance/historical-core-manifest.sha256](provenance/historical-core-manifest.sha256): the historical 1.1.2
  source's identity and the hash of each of its 417 files, which `scripts/verify-historical-baseline.py` checks.

### Images

Screenshots of the game for the README, taken from a local install of this tree, and the social preview image for
the repository's page. They show the game's text and interface (`LICENSE.txt`) and Resurrection's art (CC BY-SA 4.0,
`assets/LICENSE`); the social preview is made from that art.

- [images/village.png](images/village.png): the village square, at desktop width.
- [images/forest-fight.png](images/forest-fight.png): a forest encounter.
- [images/battle-screen.png](images/battle-screen.png): the battle screen after a round, with special abilities.
- [images/green-dragon.png](images/green-dragon.png): the fight with the Green Dragon.
- [images/phone.png](images/phone.png): the village and a fight on a phone.
- [images/social-preview.png](images/social-preview.png): the 1280 x 640 social preview.

## Records

Kept as written. Dates are when the work was done.

Some records have no date in their name, because they were written to be kept up to date while their work went on.
That work is over, so they are records now, among them `COMPATIBILITY-STATUS.md`, `CHARACTERIZATION-PLAN.md`, `DESCENDANT-RESEARCH.md` and
`ENGINEERING-VALIDATION.md`, and the Phase 3 audit and authority documents. One record still grows:
`SERIALIZED-STATE-AUDIT.md` gets new inventory rows when the code it counts changes (`CLAUDE.md`).

### Foundation (2026-09-13)

- [FOUNDATION-CHECKPOINT-20260913.md](FOUNDATION-CHECKPOINT-20260913.md): the foundation checkpoint: the 1.1.2 source
  preserved with its provenance, historical tag and manifests (2026-09-13).
- [CHARACTERIZATION-PLAN.md](CHARACTERIZATION-PLAN.md): the plan for characterizing 1.1.2's behavior in a private lab
  (2026-09-13). The lab was never needed; the plan is a record.
- [DESCENDANT-RESEARCH.md](DESCENDANT-RESEARCH.md): research into later LoGD versions and forks (2026-09-13).
- [MODERNIZATION-PRINCIPLES.md](MODERNIZATION-PRINCIPLES.md): the principles of Phases 1 to 4, with the direction
  changes of 2026-10-30 and 2026-11-05 added at the end (2026-09-13 to 2026-11-05).
- [COMPATIBILITY-STATUS.md](COMPATIBILITY-STATUS.md): the compatibility ledger for modern PHP and databases
  (2026-09-13 to 2026-10-29). Superseded by the phase records and the security status record under Reference.

### Phases 1 and 2: a modern base (2026-09-13)

- [MODERN-CORE-CHECKPOINT-20260913.md](MODERN-CORE-CHECKPOINT-20260913.md): Phase 1, the first modern engineering
  base: PHP and database compatibility, PDO, CI and static analysis (2026-09-13).
- [MODERN-CORE-CHECKPOINT-20260913-PHASE2.md](MODERN-CORE-CHECKPOINT-20260913-PHASE2.md): Phase 2, strict fresh
  install, modern credentials, sessions and CSRF, all 24 bundled modules installable (2026-09-13).
- [ENGINEERING-VALIDATION.md](ENGINEERING-VALIDATION.md): the validation tooling and its limits, as of Phases 1 to 3
  (2026-09-13 to 2026-10-15).
- [REQUEST-SEMANTICS.md](REQUEST-SEMANTICS.md): the raw HTTP request contract that replaced 1.1.2's magic quotes and
  global escaping (2026-09-13 to 2026-10-24).

### Phase 3: every trust boundary closed (2026-09-13 to 2026-10-23)

- [MODERN-CORE-CHECKPOINT-20260913-PHASE3.md](MODERN-CORE-CHECKPOINT-20260913-PHASE3.md): the Phase 3 checkpoint
  (2026-09-13).
- [MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md](MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md): the Phase 3
  security closure continuation (2026-09-13 onward).
- [CORE-ROUTE-SECURITY.md](CORE-ROUTE-SECURITY.md): core route security, route by route (2026-09-14 to 2026-10-24).
- [SPECIALTY-EXECUTION-REPORT-20260914.md](SPECIALTY-EXECUTION-REPORT-20260914.md): the specialty execution report
  (2026-09-14).
- [SPECIALTY-COMBAT-ACCOUNTING.md](SPECIALTY-COMBAT-ACCOUNTING.md): specialty combat accounting (2026-09-14).
- [SPECIALTY-AUTHORITY.md](SPECIALTY-AUTHORITY.md): the shipped specialties' authority (2026-09-14).
- [SPECIALTY-ADVERSE-ACCOUNTING-20260915.md](SPECIALTY-ADVERSE-ACCOUNTING-20260915.md): specialty adverse accounting
  (2026-09-15).
- [DRAGON-AUTHORITY-20260926.md](DRAGON-AUTHORITY-20260926.md): the Green Dragon fight and the progression after it
  (2026-09-26).
- [DARK-ARTS-COMPANION-20260927.md](DARK-ARTS-COMPANION-20260927.md): the Dark Arts companion boundary (2026-09-27).
- [GRAVEYARD-COMBAT-AUTHORITY-20260927.md](GRAVEYARD-COMBAT-AUTHORITY-20260927.md): graveyard torment combat
  (2026-09-27).
- [MYSTICAL-POWERS-20260927.md](MYSTICAL-POWERS-20260927.md): Mystical Powers (2026-09-27).
- [ORDINARY-FOREST-COMBAT-20260927.md](ORDINARY-FOREST-COMBAT-20260927.md): ordinary forest combat (2026-09-27).
- [PHASE3-RECOVERY-20260927.md](PHASE3-RECOVERY-20260927.md): recovery and specialty evidence (2026-09-27).
- [PVP-COMBAT-AUTHORITY-20260927.md](PVP-COMBAT-AUTHORITY-20260927.md): player-versus-player combat (2026-09-27).
- [TRAINING-MASTER-AUTHORITY-20260927.md](TRAINING-MASTER-AUTHORITY-20260927.md): training and master combat
  (2026-09-27).
- [DRAGON-POINT-AUTHORITY-20261003.md](DRAGON-POINT-AUTHORITY-20261003.md): New Day Dragon Point allocation
  (2026-10-03).
- [NEWDAY-DAILY-RESET-AUTHORITY-20261003.md](NEWDAY-DAILY-RESET-AUTHORITY-20261003.md): the New Day daily reset
  (2026-10-03).
- [NEWDAY-GRAVEYARD-CI-CORRECTION-20261003.md](NEWDAY-GRAVEYARD-CI-CORRECTION-20261003.md): a New Day graveyard fixture
  correction (2026-10-03).
- [MAIL-AUTHORITY-20261004.md](MAIL-AUTHORITY-20261004.md): player mail (2026-10-04).
- [RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md](RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md): Ramius's early
  resurrection (2026-10-04).
- [BANK-AUTHORITY-20261015.md](BANK-AUTHORITY-20261015.md): the bank (2026-10-15).
- [CLAN-MEMBERSHIP-AUTHORITY-20261015.md](CLAN-MEMBERSHIP-AUTHORITY-20261015.md): clan membership (2026-10-15).
- [CLAN-TEXT-MODERATION-AUTHORITY-20261015.md](CLAN-TEXT-MODERATION-AUTHORITY-20261015.md): clan text and moderation
  (2026-10-15).
- [EQUIPMENT-PURCHASE-AUTHORITY-20261015.md](EQUIPMENT-PURCHASE-AUTHORITY-20261015.md): weapon and armor purchases
  (2026-10-15).
- [INN-ECONOMY-AUTHORITY-20261015.md](INN-ECONOMY-AUTHORITY-20261015.md): the inn's economy (2026-10-15).
- [PETITION-ADMIN-AUTHORITY-20261015.md](PETITION-ADMIN-AUTHORITY-20261015.md): petition administration
  (2026-10-15).
- [BUNDLED-MODULE-CERTIFICATION.md](BUNDLED-MODULE-CERTIFICATION.md) and
  [BUNDLED-MODULE-CERTIFICATION.json](BUNDLED-MODULE-CERTIFICATION.json): the certification of the 24 bundled
  modules, 24 PASS, and its data (2026-09-13 to 2026-10-15).
- [BAN-ADMIN-AUTHORITY-20261016.md](BAN-ADMIN-AUTHORITY-20261016.md): ban administration (2026-10-16).
- [BAN-MESSAGE-AND-ID-COOKIE-20261016.md](BAN-MESSAGE-AND-ID-COOKIE-20261016.md): the ban message and the player ID
  cookie (2026-10-16).
- [BIO-DONATION-ADMIN-AUTHORITY-20261016.md](BIO-DONATION-ADMIN-AUTHORITY-20261016.md): bio moderation and donation
  administration (2026-10-16).
- [CI-ROLLOVER-STABILITY-20261016.md](CI-ROLLOVER-STABILITY-20261016.md): CI stability across the game-day rollover
  (2026-10-16).
- [CONTENT-EDITORS-PART1-AUTHORITY-20261016.md](CONTENT-EDITORS-PART1-AUTHORITY-20261016.md): content editors, part 1:
  weapons, armor, taunts and titles (2026-10-16).
- [CONTENT-EDITORS-PART2-AUTHORITY-20261016.md](CONTENT-EDITORS-PART2-AUTHORITY-20261016.md): content editors, part 2:
  creatures and masters (2026-10-16).
- [HAUNT-AUTHORITY-20261016.md](HAUNT-AUTHORITY-20261016.md): haunting (2026-10-16).
- [RESTORE-PREFS-AUTHORITY-20261016.md](RESTORE-PREFS-AUTHORITY-20261016.md): soul restoration and new-account
  preferences (2026-10-16).
- [STABLES-AUTHORITY-20261016.md](STABLES-AUTHORITY-20261016.md): the stables (2026-10-16).
- [USER-EDITOR-AUTHORITY-20261016.md](USER-EDITOR-AUTHORITY-20261016.md): the user editor, part 1 (2026-10-16).
- [COMPLETION-REPORTING-20261017.md](COMPLETION-REPORTING-20261017.md): truthful completion reporting (2026-10-17).
- [CONTENT-EDITORS-PART3-AUTHORITY-20261017.md](CONTENT-EDITORS-PART3-AUTHORITY-20261017.md): content editors, part 3:
  mounts, companions and the bio page (2026-10-17).
- [DELETION-EXPIRATION-AUTHORITY-20261017.md](DELETION-EXPIRATION-AUTHORITY-20261017.md): account deletion and
  expiration cleanup (2026-10-17).
- [MODULE-MANAGER-AUTHORITY-20261017.md](MODULE-MANAGER-AUTHORITY-20261017.md): the module manager and the remaining
  preference editors (2026-10-17).
- [SERIALIZED-SCHEMAS-PART1-20261017.md](SERIALIZED-SCHEMAS-PART1-20261017.md): serialized business schemas, part 1
  (2026-10-17).
- [SERIALIZED-SCHEMAS-PART2-20261018.md](SERIALIZED-SCHEMAS-PART2-20261018.md): part 2, buffs and mount buffs
  (2026-10-18).
- [SERIALIZED-SCHEMAS-PART3-20261022.md](SERIALIZED-SCHEMAS-PART3-20261022.md): part 3, suspended buffs and
  companions (2026-10-22).
- [SERIALIZED-SCHEMAS-PART4-20261022.md](SERIALIZED-SCHEMAS-PART4-20261022.md): part 4, general combat state
  (2026-10-22).
- [SERIALIZED-SCHEMAS-PART5-20261022.md](SERIALIZED-SCHEMAS-PART5-20261022.md): part 5, news arguments, other state,
  MoTD, payment log and comment moderation (2026-10-22).
- [SERIALIZED-STATE-AUDIT.md](SERIALIZED-STATE-AUDIT.md): the serialized-state audit; its inventory rows are updated
  when that code changes (`CLAUDE.md`) (2026-09-14 onward).
- [EXPRESSION-EVALUATION-AUDIT.md](EXPRESSION-EVALUATION-AUDIT.md): the audit that replaced every `eval()`
  (2026-09-13).
- [RESCAN-TASK2-20261022.md](RESCAN-TASK2-20261022.md): the final rescan, task 2: admin tools and small leftovers
  (2026-10-22).
- [ROUTE-INVENTORY-20261022.md](ROUTE-INVENTORY-20261022.md): every route, who reaches it and whether it changes
  state (2026-10-22).
- [RESCAN-TASK3-20261023.md](RESCAN-TASK3-20261023.md): the final rescan, task 3: the repair path, the SQL sweep and
  referer tracking (2026-10-23).
- [PHASE3-MERGE-GATE-20261023.md](PHASE3-MERGE-GATE-20261023.md): the merge gate for PR #1 (2026-10-23).

### Phase 4: release foundation (2026-10-24 to 2026-10-30)

- [UNNECESSARY-FILES-20261024.md](UNNECESSARY-FILES-20261024.md): the unnecessary files removed, with the license and
  dead-weight work (2026-10-24).
- [PHASE4-TASK1B-FRESH-INSTALL-20261029.md](PHASE4-TASK1B-FRESH-INSTALL-20261029.md): a fresh install ready to play
  (2026-10-29).
- [PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md](PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md): the version number and the
  upgrade system (2026-10-29).
- [PHASE4-TASK3-LOCAL-SETUP-20261029.md](PHASE4-TASK3-LOCAL-SETUP-20261029.md): one-command local play (2026-10-29).
- [PHASE4-OUTPUT-BUFFERING-FIX-20261029.md](PHASE4-OUTPUT-BUFFERING-FIX-20261029.md): the game no longer depends on
  PHP's output buffering (2026-10-29).

### Phase 5: Resurrection UI (2026-10-30 to 2026-11-05)

- [PHASE5-SUMMARY-20261105.md](PHASE5-SUMMARY-20261105.md): what Phase 5 changed, task by task (2026-11-05). Start
  here for Phase 5.
- [PHASE5-TASK1-DIRECTION-20261030.md](PHASE5-TASK1-DIRECTION-20261030.md): the new direction, governance and UI plan
  (2026-10-30).
- [PHASE5-UI-PLAN-20261030.md](PHASE5-UI-PLAN-20261030.md): the UI plan (2026-10-30).
- [PHASE5-TASK2-TOKENS-20261030.md](PHASE5-TASK2-TOKENS-20261030.md): design tokens, fonts and color codes
  (2026-10-30).
- [PHASE5-TASK3-TEMPLATE-20261030.md](PHASE5-TASK3-TEMPLATE-20261030.md): the new template and base layout
  (2026-10-30).
- [PHASE5-TASK4-HEADER-FOOTER-20261030.md](PHASE5-TASK4-HEADER-FOOTER-20261030.md): header, footer and the
  attribution line (2026-10-30).
- [PHASE5-TASK5-IMAGES-20261030.md](PHASE5-TASK5-IMAGES-20261030.md): the image pipeline and the first art
  (2026-10-30).
- [PHASE5-TASK6-NAVIGATION-20261030.md](PHASE5-TASK6-NAVIGATION-20261030.md): navigation (2026-10-30).
- [PHASE5-TASK7-CHARACTER-PANEL-20261030.md](PHASE5-TASK7-CHARACTER-PANEL-20261030.md): the character panel
  (2026-10-30).
- [PHASE5-TASK8-PHONE-20261031.md](PHASE5-TASK8-PHONE-20261031.md): the phone layout (2026-10-31).
- [PHASE5-TASK9-FORMS-20261031.md](PHASE5-TASK9-FORMS-20261031.md): forms, buttons and tables (2026-10-31).
- [PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md](PHASE5-TASK10-BATTLE-COMMENTARY-20261031.md): the battle screen,
  commentary and shop lists (2026-10-31).
- [PHASE5-TASK11-POPUPS-ADMIN-20261101.md](PHASE5-TASK11-POPUPS-ADMIN-20261101.md): popups and the admin screens
  (2026-11-01).
- [PHASE5-TASK12-SCENES-20261105.md](PHASE5-TASK12-SCENES-20261105.md): location scenes (2026-11-05).
- [PHASE5-TASK13-ICONS-20261105.md](PHASE5-TASK13-ICONS-20261105.md): icons, date-proof tests and shard balance
  (2026-11-05).
- [PHASE5-TASK14-PORTRAITS-20261105.md](PHASE5-TASK14-PORTRAITS-20261105.md): creature and master portraits
  (2026-11-05).
- [PHASE5-TASK15-REMOVE-SKINS-20261105.md](PHASE5-TASK15-REMOVE-SKINS-20261105.md): the 1.1.2 skins and skin selection
  removed (2026-11-05).
- [PHASE5-CLOSEOUT-20261105.md](PHASE5-CLOSEOUT-20261105.md): the close-out: the safe credit frame and the unused
  images (2026-11-05).

### Phase 6: release readiness (2026-11-06 onward)

- [PHASE6-RC-READINESS-20261106.md](PHASE6-RC-READINESS-20261106.md): task 1, the readiness review and its ranked gap
  list (2026-11-06).
- [PHASE6-TASK2-FIXES-20261106.md](PHASE6-TASK2-FIXES-20261106.md): task 2, crash fixes, the CI pin and the first
  administrator's flags (2026-11-06).
- [PHASE6-TASK3-HARDENING-20261106.md](PHASE6-TASK3-HARDENING-20261106.md): task 3, security headers, the Content
  Security Policy, internal files refused and the configuration file's place (2026-11-06).
- [PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md](PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md): task 4, the design and threat
  model for web install, upgrade and maintenance (2026-11-06).
- [PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md](PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md): task 5a, the shared
  services, the operator frame and web maintenance (2026-11-06).
- [PHASE6-TASK5B-SETUP-PAGE-20261106.md](PHASE6-TASK5B-SETUP-PAGE-20261106.md): task 5b, the web setup page,
  `setup.php` (2026-11-06).
- [PHASE6-TASK6-HOST-GUIDE-20261107.md](PHASE6-TASK6-HOST-GUIDE-20261107.md): task 6, the host guide (2026-11-07).
- [PHASE6-TASK7-RC-MATERIALS-20261107.md](PHASE6-TASK7-RC-MATERIALS-20261107.md): task 7, the front-door documents,
  the cut materials and the release package script (2026-11-07).
- [LEGAL-REVIEW-PACKET-20261107.md](LEGAL-REVIEW-PACKET-20261107.md): the legal review packet, not sent by Jim's
  decision of 2026-11-12 (2026-11-07).
- [PHASE6-TASK8-20261112.md](PHASE6-TASK8-20261112.md): task 8, shard balance, the sign-out timeout's bounds, labels
  and tap targets (2026-11-12).
- [PHASE6-TASK9-20261112.md](PHASE6-TASK9-20261112.md): task 9, CI shard time, stale form tokens after signing in
  again, the phone layout (2026-11-12).
- [PHASE6-TASK10-20261112.md](PHASE6-TASK10-20261112.md): task 10, the security status record and the module author
  guide (2026-11-12).
- [PHASE6-TASK11-20261112.md](PHASE6-TASK11-20261112.md): task 11, the credit stated within the license (2026-11-12).
- [PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md](PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md): task 12, the design
  and threat model for mail, password recovery and email verification (2026-11-12).
- [PHASE6-TASK13A-20261112.md](PHASE6-TASK13A-20261112.md): task 13a, account mail's foundation (2026-11-12).
- [PHASE6-TASK13B-20261112.md](PHASE6-TASK13B-20261112.md): task 13b, password recovery (2026-11-12).
- [PHASE6-TASK13C-20261113.md](PHASE6-TASK13C-20261113.md): task 13c, email verification and the address change
  (2026-11-13).
- [PHASE6-TASK14-RC1-VERSION-20261113.md](PHASE6-TASK14-RC1-VERSION-20261113.md): task 14, the `1.0.0-rc.1` version
  commit (2026-11-13).
- [PHASE6-TASK15-RC1-CUT-20261113.md](PHASE6-TASK15-RC1-CUT-20261113.md): task 15, the `v1.0.0-rc.1` tag, the package
  and the pre-release (2026-11-13).
- [RELEASE-NOTES-1.0.0-rc.1.md](RELEASE-NOTES-1.0.0-rc.1.md): the notes for `1.0.0-rc.1` (2026-11-13).
- [PHASE6-TASK16-20261113.md](PHASE6-TASK16-20261113.md): task 16, rc.1 server-test fixes, part 1 (2026-11-13).
- [PHASE6-TASK17-20261113.md](PHASE6-TASK17-20261113.md): task 17, rc.1 server-test fixes, part 2, and the dragon-eye
  icon (2026-11-13).
- [PHASE6-TASK18-20261114.md](PHASE6-TASK18-20261114.md): task 18, sign-in throttling, settings that did nothing,
  versioned asset URLs and phone tap targets (2026-11-14).
- [PHASE6-TASK19-20261115.md](PHASE6-TASK19-20261115.md): task 19, every tap target at phone width, site-wide
  (2026-11-15).
- [PHASE6-TASK20-20261116.md](PHASE6-TASK20-20261116.md): task 20, rc.1 server-test fixes, part 3 (2026-11-16).
- [PHASE6-TASK21-RC2-VERSION-20261116.md](PHASE6-TASK21-RC2-VERSION-20261116.md): task 21, the `1.0.0-rc.2` version
  commit (2026-11-16).
- [RELEASE-NOTES-1.0.0-rc.2.md](RELEASE-NOTES-1.0.0-rc.2.md): the notes for `1.0.0-rc.2` (2026-11-16).
- [PHASE6-TASK22-20261116.md](PHASE6-TASK22-20261116.md): task 22, the rc.2 cut recorded and the refused-action dead
  end (2026-11-16).
- [PHASE6-TASK23-RC3-VERSION-20261117.md](PHASE6-TASK23-RC3-VERSION-20261117.md): task 23, the `1.0.0-rc.3` version
  commit (2026-11-17).
- [RELEASE-NOTES-1.0.0-rc.3.md](RELEASE-NOTES-1.0.0-rc.3.md): the notes for `1.0.0-rc.3` (2026-11-17).
- [PHASE6-TASK24-20261117.md](PHASE6-TASK24-20261117.md): task 24, the forest after the new day's Continue
  (2026-11-17).
- [PHASE6-TASK25-RC4-VERSION-20261117.md](PHASE6-TASK25-RC4-VERSION-20261117.md): task 25, the `1.0.0-rc.4` version
  commit (2026-11-17).
- [RELEASE-NOTES-1.0.0-rc.4.md](RELEASE-NOTES-1.0.0-rc.4.md): the notes for `1.0.0-rc.4` (2026-11-17).
- [PHASE6-TASK26-20261118.md](PHASE6-TASK26-20261118.md): task 26, dead ends on the training page and after a special
  ability (2026-11-18).
- [PHASE6-TASK27-20261118.md](PHASE6-TASK27-20261118.md): task 27, no dead ends, part 1 (2026-11-18).
- [PHASE6-TASK28-20261119.md](PHASE6-TASK28-20261119.md): task 28, no dead ends, part 2 (2026-11-19).
- [PHASE6-TASK29-RC5-VERSION-20261119.md](PHASE6-TASK29-RC5-VERSION-20261119.md): task 29, the `1.0.0-rc.5` version
  commit (2026-11-19).
- [PHASE6-TASK30-20261120.md](PHASE6-TASK30-20261120.md): task 30, the Dragon specialty test split so the CI shards
  can balance (2026-11-20).
- [PHASE6-TASK31-20261120.md](PHASE6-TASK31-20261120.md): task 31, release polish: this index, the README and the
  living documents brought up to date (2026-11-20).
