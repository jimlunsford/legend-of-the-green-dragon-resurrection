# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.1

**Draft. This test build has not been cut.** There is no `v1.0.0-rc.1` tag and no package yet: the cut happens only on
the maintainer's go, by `docs/RC-CUT-CHECKLIST.md`. Until then `main` is `1.0.0-dev`. Written in Phase 6 task 7
(2026-11-07) from `docs/CHANGES-FROM-1.1.2.md`, which holds every change in full with its evidence; brought up to date
in Phase 6 task 8 (2026-11-12), and for account mail (S11) in Phase 6 task 13c (2026-11-13).

## What Resurrection is

A modern continuation of Legend of the Green Dragon 1.1.2 DragonPrime Edition, the browser role-playing game by Eric
Stevens and JT Traub and the DragonPrime Development Team. It stays PHP on the same codebase so that other people can
host it, and runs on today's PHP and databases: PHP 8.4 or 8.5, with MariaDB 11.4 or MySQL 8.4. It is a noncommercial
project, licensed as 1.1.2 is, under CC BY-NC-SA 2.0 (`LICENSE.txt`), with the original credits kept on every page.
Resurrection's changes are by Phoenix 2:33 LLC (`NOTICE`).

`1.0.0-rc.1` is the first test build: a candidate for 1.0.0, for hosts and players to try. It is not the release, and it
has not had an independent security review.

## What changed from 1.1.2

The level a host or player notices. Each line is a group of rows in `docs/CHANGES-FROM-1.1.2.md`.

### For players

- **A new look.** One dark fantasy interface replaces all of 1.1.2's skins: a scene picture for each place, portraits
  for the races and the foes, icons on the character panel, hitpoints and experience as bars, a battle screen with
  fighter cards, and a layout made for phones (a character header, nav tiles and a tab bar). Skin choice is gone.
- **The same game.** Gameplay, balance and text are 1.1.2's except where a change is recorded. Fixed along the way:
  crashes on modern PHP and MySQL (the online list with a dead player online, two statistics pages, a dragon kill with
  a long name), the referer list showing only one site, and others in the ledger.
- **Safer accounts.** Passwords are hashed with PHP's password API and must be 12 to 72 bytes. Signing in, changing the
  password and signing out end older sessions. Every action that changes something is a form with a security token, so
  a link elsewhere cannot act for you.
- **Labels and tap targets.** Every control on the Warriors list's search, Cedrik's gem wish and Dag's bounty forms has
  a label, and the shop item buttons, the write-mail icons, the module list's links and the radio buttons and
  checkboxes are at least 44px to tap on a phone.
- **Signing in again picks up where you left off.** After a sign-out or a timeout in the middle of the new day's
  questions, a fight or a forest event, the page you left comes back with working buttons; a form from the earlier
  session is still refused.
- **Phone layout.** On a phone, account creation and the companion and mount editors put each label above its field,
  the Warriors list, the Hall of Fame, Dag's bounty list and the module list fit the screen, and an admin table that is
  wider than the screen scrolls inside its own box.
- **Password recovery by email**, once the host has turned account mail on: "Forgot your password?" on the sign-in
  page mails a link that works for 30 minutes and once, only to an address you have verified. Every request gets the
  same answer, so it does not tell anyone which names or addresses exist.
- **Your email address, verified.** Preferences shows whether your address is verified and can mail you a link to
  verify it; a one-line reminder says so until you do or dismiss it. Changing the address needs your current password,
  and while account mail is on it changes only when you confirm a link mailed to the new address; a verified old
  address is told, without the new address.
- **New accounts may need to confirm their address**, when the host turns on "Require users to validate their email
  address": the sign-in waits until the link mailed at sign-up is confirmed, and can send it again.

### For hosts

- **Fresh installs only.** A Resurrection install starts from an empty database. A 1.1.2 database is refused, never
  upgraded or migrated.
- **Install with or without a shell.** `php scripts/install.php` on the command line, or `setup.php` in the browser for
  a host with only a hosting panel: it asks for a one-use token file that proves you control the site's files. The
  1.1.2 web installer is gone (`installer.php` answers 403).
- **Upgrades** are numbered steps applied by `php scripts/upgrade.php` (or `setup.php`), one release at a time. While the
  database is behind, the game shows "Upgrade Needed".
- **The sign-out timeout has bounds.** "Seconds of inactivity before auto-logoff" (`LOGINTIMEOUT`) takes 300 to 86400
  seconds in Game Settings, which refuses anything else and says why. A database row outside that range is read as
  the nearest end of it, so a stored 0 no longer signs every player out.
- **Daily maintenance** runs from `cron.php` in a scheduler, or, for a scheduler that can only fetch an address, from
  `webcron.php` with a secret key. Game Settings shows when it last ran.
- **The configuration file can live outside the docroot**: set `LOGD_DBCONNECT` to its absolute path. A file the game
  cannot read gets a clear page (503), not a blank one.
- **Hardened responses.** Security headers and an enforced Content Security Policy on every page (`csp_mode`), and the
  game's internal files answer a direct request with an empty 404.
- **Removed surfaces.** LoGDnet, the raw SQL and PHP console, PayPal payments and the payment log, the source viewer
  (the footer's "View PHP Source" link now opens the public source, `sourceurl`), and 1.1.2's password recovery and
  validation links.
- **Account mail.** Recovery, verification and address change mail stay off until the host proves the game's mail
  arrives: an `https://` Server URL, a real sender and a test mail confirmed from Game Settings, Account mail. Then one
  switch turns it on (and off, always). Links come only from the Server URL; tokens are random, stored only as a hash,
  bound to their account and purpose, short-lived and used once; every kind of mail has limits. While account mail is
  on, new-mail and expiration notices go only to verified addresses, and `requirevalidemail` can be turned on; while it
  is off, it reads as off. Staff accounts can be excluded from recovery. Host guide, "Mail".
- **Modules.** The 24 bundled modules are certified; a fresh install turns on the 23 recommended ones. Only bundled
  modules install. None of 1.1.2's archive modules is included.
- **A credit line** under 1.1.2's on every page: "A Phoenix 2:33 LLC Project by Jim Lunsford", with its two links: the
  credit section 4(d) of CC BY-NC-SA 2.0 describes, which `NOTICE` quotes in full, with no added term.

## Operator notes

Follow the host guide, `docs/HOST-GUIDE.md`: what to upload (the package holds exactly that list), the docroot and the
paths to refuse (sample nginx, Apache and `.htaccess` rules in `docs/hosting/`; none is active), the configuration
file, install, maintenance, upgrades, backups, settings, mail, logs and troubleshooting.

Requirements in short: PHP 8.4 or 8.5 with `ctype`, `filter`, `json`, `mbstring`, `pdo`, `pdo_mysql` and `session`;
MariaDB 11.4 or MySQL 8.4 with an empty `utf8mb4` database; HTTPS; and a scheduler for daily maintenance.

After installing, check the server URL (`serverurl`), the admin email (`gameadminemail`) and, if you change the code,
the public source address (`sourceurl`), and run the checks in the guide's section 8.

Where the game stands on security, area by area, with the evidence and what is left over, and which public hosting
gates are still open: `docs/SECURITY-STATUS.md`. Before adding a module that is not bundled, read
`docs/MODULE-AUTHOR-GUIDE.md` (the rules a module must follow) and `docs/MODULE-POLICY.md`: rc.1 installs only its 24
bundled modules.

## Known issues

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
- **Not certified for public hosting.** The project's own gates (`ROADMAP.md`, "Public hosting") are not all met, and no
  independent security review has been done. `project.json` keeps `public_game_hosting_approved` at `false`.
- **Licensing (S9): closed by decision.** Jim decided on 2026-11-12 not to seek legal counsel: the project meets the
  licenses as written, and every statement of the credit stays within what they allow (section 4(d) of CC BY-NC-SA
  2.0, quoted in `NOTICE`). The legal review packet was not sent and is kept as a record
  (`docs/LEGAL-REVIEW-PACKET-20261107.md`). No file is labeled AGPL.

## Credits

Original game design and code: Eric Stevens and JT Traub. Continued development: the DragonPrime Development Team and
the credited contributors and module authors (`README.txt`). Resurrection: Phoenix 2:33 LLC, by Jim Lunsford. The original
art in `assets/scenes/`, `assets/portraits/` and `assets/icons/` is by Phoenix 2:33 LLC, under CC BY-SA 4.0
(`assets/LICENSE`); the interface fonts are under the SIL Open Font License 1.1 (`docs/THIRD-PARTY-LICENSES.md`).
