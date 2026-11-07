# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.1

**Draft. This test build has not been cut.** There is no `v1.0.0-rc.1` tag and no package yet: the cut happens only on
the maintainer's go, by `docs/RC-CUT-CHECKLIST.md`. Until then `main` is `1.0.0-dev`. Written in Phase 6 task 7
(2026-11-07) from `docs/CHANGES-FROM-1.1.2.md`, which holds every change in full with its evidence.

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
- **No password recovery by email** in this build (see "Known issues").

### For hosts

- **Fresh installs only.** A Resurrection install starts from an empty database. A 1.1.2 database is refused, never
  upgraded or migrated.
- **Install with or without a shell.** `php scripts/install.php` on the command line, or `setup.php` in the browser for
  a host with only a hosting panel: it asks for a one-use token file that proves you control the site's files. The
  1.1.2 web installer is gone (`installer.php` answers 403).
- **Upgrades** are numbered steps applied by `php scripts/upgrade.php` (or `setup.php`), one release at a time. While the
  database is behind, the game shows "Upgrade Needed".
- **Daily maintenance** runs from `cron.php` in a scheduler, or, for a scheduler that can only fetch an address, from
  `webcron.php` with a secret key. Game Settings shows when it last ran.
- **The configuration file can live outside the docroot**: set `LOGD_DBCONNECT` to its absolute path. A file the game
  cannot read gets a clear page (503), not a blank one.
- **Hardened responses.** Security headers and an enforced Content Security Policy on every page (`csp_mode`), and the
  game's internal files answer a direct request with an empty 404.
- **Removed surfaces.** LoGDnet, the raw SQL and PHP console, PayPal payments and the payment log, the source viewer
  (the footer's "View PHP Source" link now opens the public source, `sourceurl`), and password recovery.
- **Modules.** The 24 bundled modules are certified; a fresh install turns on the 23 recommended ones. Only bundled
  modules install. None of 1.1.2's archive modules is included.
- **A credit line** under 1.1.2's on every page: "A Phoenix 2:33 LLC Project by Jim Lunsford", with its two links. The
  host guide says what a host must keep.

## Operator notes

Follow the host guide, `docs/HOST-GUIDE.md`: what to upload (the package holds exactly that list), the docroot and the
paths to refuse (sample nginx, Apache and `.htaccess` rules in `docs/hosting/`; none is active), the configuration
file, install, maintenance, upgrades, backups, settings, mail, logs and troubleshooting.

Requirements in short: PHP 8.4 or 8.5 with `ctype`, `filter`, `json`, `mbstring`, `pdo`, `pdo_mysql` and `session`;
MariaDB 11.4 or MySQL 8.4 with an empty `utf8mb4` database; HTTPS; and a scheduler for daily maintenance.

After installing, check the server URL (`serverurl`), the admin email (`gameadminemail`) and, if you change the code,
the public source address (`sourceurl`), and run the checks in the guide's section 8.

## Known issues

- **No account recovery or email verification (S11).** A player who forgets a password asks an administrator, who
  resets it in the user editor. Game Settings shows "Require users to validate their email address"
  (`requirevalidemail`) locked off, and the game treats it as off whatever the database holds, because with it on
  nobody could create an account. Planned for 1.0.0, which it blocks.
- **Mail is best effort.** The game sends mail with PHP's `mail()`. A message that cannot be handed over is logged and
  the game goes on (host guide, "Mail").
- **Signing in again during the new day's questions** can replay a page whose buttons answer "Invalid form
  submission." (403). Reloading the page, or following its link again, gives working buttons.
- **Some controls are small or unlabelled on a phone (S5):** the shop item buttons, the mail icon links, the module
  list links and radio buttons are under 44px; the Warriors list name search, Cedrik's gem wish and Dag's bounty forms
  have inputs without labels.
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
- **Licensing questions are with counsel (S9):** the attribution line's wording, and how code under AGPL-3.0-or-later
  could ship beside the CC BY-NC-SA material (`docs/LEGAL-REVIEW-PACKET-20261107.md`). No file is labeled AGPL.

## Credits

Original game design and code: Eric Stevens and JT Traub. Continued development: the DragonPrime Development Team and
the credited contributors and module authors (`README.txt`). Resurrection: Phoenix 2:33 LLC, by Jim Lunsford. The original
art in `assets/scenes/`, `assets/portraits/` and `assets/icons/` is by Phoenix 2:33 LLC, under CC BY-SA 4.0
(`assets/LICENSE`); the interface fonts are under the SIL Open Font License 1.1 (`docs/THIRD-PARTY-LICENSES.md`).
