# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.2

Draft. This test build has not been cut. Started in Phase 6 task 16 (2026-11-13) from what the rc.1 server test found;
each change is recorded in full in `docs/CHANGES-FROM-1.1.2.md`. Read with the rc.1 notes,
`docs/RELEASE-NOTES-1.0.0-rc.1.md`: everything there still holds unless this page says otherwise.

## What changed since 1.0.0-rc.1

Found while installing rc.1 on a real server (`ROADMAP.md`, "Found in the rc.1 server test").

### For hosts

- **R1: the package says how to install it.** The zip holds one folder, `legend-of-the-green-dragon-resurrection-<version>`.
  Its `upload/` is exactly what goes into the site's folder: upload the contents of `upload/`. Beside it are
  `INSTALL.txt` (check the SHA-256, upload, read the host guide), the host guide, the release notes, the third-party
  licenses, the sample server rules in `hosting/`, and copies of `LICENSE.txt`, `README.txt` and `NOTICE`, so the
  terms can be read before anything is uploaded. In rc.1 the zip held only the upload, and its only readme was 1.1.2's
  `README.txt`, which describes 1.1.2's install.
- **R2: the game's pages link the host guide online.** The not-installed page, the "Upgrade Needed" and "Game
  Unavailable" pages, `setup.php`'s upgrade failures, `installer.php`'s 403 and the locked "Require users to validate
  their email address" note in Game Settings named files under `docs/`, which are not on the server. They now link the
  host guide's section at this version's tag on GitHub, and they open in place.
- **R3: `NOTICE` names only files the package holds.** It pointed at `docs/THIRD-PARTY-LICENSES.md`, which the rc.1
  zip did not hold. It now names `THIRD-PARTY-LICENSES.md`, in the repository's docs folder and at the package's top
  level.

## Known issues

Carried from rc.1:

- **Mail is best effort, through PHP's `mail()` only.** There is no SMTP setting. A message that cannot be handed over
  is logged and the game goes on (host guide, "Mail"). Until the host turns account mail on, there is no recovery by
  email: an administrator resets a password in the user editor.
- **A link's token reaches the web server's access log.** The game never logs it, but the server records the address
  a player opens for the link's life (30 minutes for recovery, 24 hours otherwise); the host guide says to keep logs
  behind the panel login.
- **1.1.2 behaviors kept as they were** (`ROADMAP.md`, Phase 3, "Recorded, not changed"), among them: a ban reason is
  shown to the banned visitor as raw HTML (staff write it); clearing the `lgi` cookie evades an ID ban; weapon, armor and
  title text allows color codes; the clan-delete hook is not called on disband; the healer resets hitpoints above the
  maximum on view; the Graveyard views sober a dead player; the MoTD archive skips its first month.
- **1.1.2 data oddities:** two creatures are both "Loneliness", and master 5's name has a replacement character where
  1.1.2 lost a byte.
- **Modules beyond the bundled 24** are not supported: installed module code is trusted PHP.
- **Maintenance is not one transaction.** A failed run repeats its date-based cleanups on the next run.
- **No 1.1.2 migration**, by design: install fresh.
- **No abuse controls yet:** no sign-in throttling and no limit on posting floods; account creation is limited only
  while sign-ups must confirm their address (5 an hour from one network address).
- **Game Settings lists some settings that do nothing** (`newdaycron`, `show_notices`, the `notify_*` settings, and
  `beta` and `betaperplayer`, whose page is not in the game).
- **Asset URLs carry no version**, so a long browser cache of the styles and pictures can outlast an upgrade; do not
  set far-future caching for `assets/`.
- **Small targets on phones:** the sort headers and "Edit" links on some staff tables, and translator mode's links.
- **Not certified for public hosting.** The project's own gates (`ROADMAP.md`, "Public hosting") are not all met, and no
  independent security review has been done. `project.json` keeps `public_game_hosting_approved` at `false`.
- **Licensing (S9): closed by decision.** Jim decided on 2026-11-12 not to seek legal counsel: the project meets the
  licenses as written, and every statement of the credit stays within what they allow (section 4(d) of CC BY-NC-SA
  2.0, quoted in `NOTICE`). The legal review packet was not sent and is kept as a record
  (`docs/LEGAL-REVIEW-PACKET-20261107.md`). No file is labeled AGPL.

New in this draft:

- **The shell commands still name `docs/MODERN-INSTALLATION.md`** in four messages: an unknown option to either
  command, the upgrade without `--backup-confirmed`, and a failed upgrade. Their texts are kept as they are for now; the host guide, section
  9, covers the same ground.
