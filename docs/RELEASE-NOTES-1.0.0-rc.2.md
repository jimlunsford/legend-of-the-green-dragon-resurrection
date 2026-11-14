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
- **R4: the shell commands link the host guide too.** Four messages named `docs/MODERN-INSTALLATION.md`: an unknown
  option to `scripts/install.php` or `scripts/upgrade.php`, the upgrade without `--backup-confirmed`, and a failed
  upgrade. Each now names the host guide's matching section ("Install", "Upgrade", "Backups and restore") online at the
  running version's tag.
- **R5: a new icon.** The game's own icon, a dragon eye, replaces 1.1.2's favicon: `favicon.ico` (16, 32 and 48 px),
  `favicon.png`, `favicon.svg` and `apple-touch-icon.png` in the game's folder, linked from every page. They are new
  files to upload (host guide, section 2); its art is CC BY-SA 4.0 like the rest (`assets/LICENSE`).

### Known issues fixed (Phase 6 task 18)

The four known-issue fixes of the finish line (Jim's decision of 2026-11-13; `docs/PHASE6-TASK18-20261114.md`).

- **Sign-in throttling.** Failed sign-ins are counted, and too many are refused for a while with "Too many attempts.
  Try again in N minutes.", whatever the case, without checking the password. Three limits in Game Settings, "Sign-in
  Limits", each kept inside its range: one name from one address, 5 in 15 minutes; one address, 20 in 15 minutes; one
  name from every address, 50 in an hour (high on purpose, so nobody can keep a player out). The address is the one
  PHP sees, never a forwarded header: behind a proxy, set the visitor's address in the server (host guide, section 6).
  The waits end by themselves; a successful sign-in clears nothing. A name no account has now answers exactly as a
  wrong password does, in time too, so the sign-in form no longer shows which logins exist. Daily maintenance deletes
  `faillog` rows older than 30 days.
- **Game Settings lists only settings that do something.** `newdaycron`, `show_notices` and the four `notify_*`
  settings are gone from it (nothing read them; a stored value is left alone). `beta` and `betaperplayer` are still
  read by the code, so they stay, and each label says it does nothing in this game.
- **Versioned asset URLs.** Every stylesheet, script and font URL carries `?v=` and part of the file's hash, so after
  an upgrade browsers fetch each changed file. Hosts may now cache `assets/ui/` and `assets/fonts/` for a long time;
  images keep plain URLs, so let browsers revalidate them (host guide, section 9).
- **Phone tap targets.** On a phone, the sort headers and the Edit, Ban and Log links on the staff tables (Module
  Manager, User Editor, Title Editor, Creature Editor), and translator mode's T links, are at least 44 x 44.

### Provenance

- **Two things have no record beyond the 1.1.2 distribution:** the authorship of the four images inherited from 1.1.2
  (`images/new.gif`, `images/newscroll.GIF`, `images/trans.gif`, `images/somerights20.gif`), and the bundled
  modules' code by authors other than 1.1.2's own (named in each module and in `THIRD-PARTY-LICENSES.md`). Both ship
  as part of the Legend of the Green Dragon 1.1.2 distribution, under its license (CC BY-NC-SA 2.0 and `LICENSE.txt`),
  as 1.1.2 shipped them. Nothing was removed.

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
- **Abuse controls beyond sign-in throttling are the operator's:** no limit on posting floods; account creation is
  limited only while sign-ups must confirm their address (5 an hour from one network address).
- **Images carry no version** in their URLs: a long browser cache of the pictures can outlast an upgrade, so do not
  set far-future caching for `assets/` images (the art does not change after `1.0.0`).
- **Not certified for public hosting.** The project's own gates (`ROADMAP.md`, "Public hosting") are not all met, and no
  independent security review has been done. `project.json` keeps `public_game_hosting_approved` at `false`. Since
  Jim's decision of 2026-11-13, the gates the code does not meet belong to whoever runs a public server (host guide,
  at its top): Resurrection is finished as a showcase project at `1.0.0`, with bug fixes only after it.
- **Licensing (S9): closed by decision.** Jim decided on 2026-11-12 not to seek legal counsel: the project meets the
  licenses as written, and every statement of the credit stays within what they allow (section 4(d) of CC BY-NC-SA
  2.0, quoted in `NOTICE`). The legal review packet was not sent and is kept as a record
  (`docs/LEGAL-REVIEW-PACKET-20261107.md`). No file is labeled AGPL.
