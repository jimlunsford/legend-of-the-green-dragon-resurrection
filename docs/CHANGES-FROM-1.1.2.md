# Changes from 1.1.2

The ledger of deliberate changes from Legend of the Green Dragon 1.1.2 DragonPrime Edition that a player, an
administrator or an operator can notice. `CLAUDE.md` requires one entry for every such change: the area, 1.1.2's
behavior, the new behavior, why, and the source of the evidence. The 1.1.2 behavior is read from the
`historical-source-1.1.2` tag.

Started on 2026-10-30 (Phase 5 task 2). The entries before Phase 5 are seeded from the evidence docs where each change
was recorded at the time; they cite those docs and do not re-derive them. The detail, tests and CI runs are in the
cited doc. Changes that only make old behavior work on modern PHP or databases, with the same result for the player
(the many PHP 8 and MySQL 8.4 fatals fixed in Phase 3), are recorded in their docs and not repeated here.

## Removed or disabled surfaces

| Area | 1.1.2 | Now | Why | Source |
|---|---|---|---|---|
| LoGDnet | `logdnet.php` and `images/logdnet.php` registered the server with the LoGDnet network; the footer generated a remote registration script | Removed; a request finds no page (404). The legacy settings are inert | Remote network service with no safe replacement | `SECURITY-STATUS.md` (S03; "2026-10-24: disabled endpoints removed"), `UNNECESSARY-FILES-20261024.md` ("License and dead weight"), `9fb11a6` |
| Raw SQL / PHP console | `rawsql.php` ran SQL and PHP typed by an administrator | Removed (404) | A web console that runs arbitrary code | `SECURITY-STATUS.md` (S09), `UNNECESSARY-FILES-20261024.md`, `9fb11a6` |
| Payments | `payment.php` (PayPal notifications), footer PayPal buttons, `paylog.php` | Removed (404); the PayPal and LoGDnet setting declarations and defaults removed (stored rows kept); the user editor's "Manage Payment Log" flag hidden | Historical payments are disabled; no payment account is connected | `SECURITY-STATUS.md` (S10), `UNNECESSARY-FILES-20261024.md` ("License and dead weight", "Dead weight, round 2"), `9fb11a6`, `2e0c948` |
| Source viewer and the "View PHP Source" link | `source.php` showed the server's PHP files in a popup | `source.php` removed (404). The footer link opens the public source repository named by the `sourceurl` setting (http or https only, escaped) | Showing server files is unsafe; the license is honored by linking the published source | `SECURITY-STATUS.md` ("Source viewer"), `UNNECESSARY-FILES-20261024.md` ("License and dead weight"), `9fb11a6` |
| Password recovery | "Forgotten Password" link and `create.php?op=forgot` / `op=val` reset by a password-derived value | Link removed; `op=forgot` is an unknown op (400), `op=val` answers 410. No recovery until a modern token and email flow exists | The 1.1.2 reset was password-derived and insecure | `MODERN-CORE-CHECKPOINT-20260913-PHASE2.md` ("Recovery"), `SECURITY-STATUS.md` (S05), `UNNECESSARY-FILES-20261024.md` ("Dead weight, round 2"), `2e0c948` |
| Web installer | `installer.php` installed and upgraded the game in the browser, in stages | `installer.php` answers 403; the web stages are removed. Install from the command line (`php scripts/install.php`); the not-installed and "Upgrade Needed" pages name the command | A web installer on a live server is an attack surface | `UNNECESSARY-FILES-20261024.md` ("Dead weight, round 2", "Installer pages"), `MODERN-INSTALLATION.md`, `2e0c948`, `754bf14` |
| Developer notes and unused files | `TODO.txt`, `AFTERUPGRADE.txt`, `BUG FIXES.txt`, `CHANGELOG.txt` and 21 unused files shipped | Removed from the shipped tree; each kept byte for byte at the tag | Dead weight | `UNNECESSARY-FILES-20261024.md`, `acb31f6`, `9fb11a6` |

## Accounts and security

| Area | 1.1.2 | Now | Why | Source |
|---|---|---|---|---|
| Passwords | MD5 hashes, hashed in the browser; the stored hash could be replayed | `password_hash()` / `password_verify()`; a password of 12 to 72 bytes; the stored hash is not accepted as a password; login, password change and logout revoke older sessions | Credential security | `MODERN-CORE-CHECKPOINT-20260913-PHASE2.md` ("Password API" to "Revocation"), `SECURITY-STATUS.md` (S05) |
| Forms and state-changing links | Many actions ran on a GET link and could be replayed | Every state change is a POST with a CSRF token, and most with a one-use intent bound to what was shown; a GET only asks or shows. Examples: the module manager, the MoTD editor and votes, the healer, the mercenary camp, the gypsy's seance, the admin tools | Request forgery and replay | `CORE-ROUTE-SECURITY.md`, `ROUTE-INVENTORY-20261022.md`, `MODULE-MANAGER-AUTHORITY-20261017.md`, `SERIALIZED-SCHEMAS-PART5-20261022.md`, `RESCAN-TASK2-20261022.md` |
| Modules | Any module file could be installed, including by a forced request | Only the 24 bundled modules install; every lifecycle operation is a POST with CSRF and a one-use intent; uninstall reports what it removed | Installed modules are trusted code | `MODULE-MANAGER-AUTHORITY-20261017.md`, `SECURITY-STATUS.md` (S04), `43bcdc4` |
| Player ID cookie | Any `lgi` cookie value became the stored `uniqueid` | Only the generated 32-hex format is stored | Stored value from the client | `BAN-MESSAGE-AND-ID-COOKIE-20261016.md`, `55ec6c2` |
| Bio return link | `bio.php` returned to any page the request named | Only to known on-site pages | Open redirect | `RESCAN-TASK2-20261022.md`, `107ce87` |
| Mail notification link | `http://` plus the request's `Host` header: a forged Host put any address into the recipient's email | The configured, validated `serverurl`; `serverurl` is never saved from a request | Link spoofing | `RESCAN-TASK2-20261022.md`, `COMPLETION-REPORTING-20261017.md` |
| Admin repair | No repair path for a damaged stored value | `user.php?op=repair` shows the stored values that fail their schema and repairs each with a POST, CSRF and a one-use intent, logged | Damaged serialized state stops a page instead of being silently reset | `RESCAN-TASK3-20261023.md`, `54e22e6` |

## Gameplay and display

| Area | 1.1.2 | Now | Why | Source |
|---|---|---|---|---|
| Name after a dragon kill | A name that grew past the 60-character column with its new title was cut short by non-strict MySQL | The old name and title are kept and the kill completes (under strict SQL the cut became a 500 that rolled back the kill) | Strict SQL; the title and user editors' rule | `RESCAN-TASK2-20261022.md`, `107ce87` |
| Referer list | Showed only its first site (the site loop's counter was reused) | Shows every site | 1.1.2 defect | `RESCAN-TASK2-20261022.md`, `107ce87` |
| Copyright line on the not-installed, database error and "Upgrade Needed" pages | Those pages ran before the footer values were set and showed no copyright line | They show the normal footer with the copyright line | The license requires the credit on every page | `UNNECESSARY-FILES-20261024.md` ("Installer pages"), `754bf14` |
| Footer version | "Version: 1.1.2 Dragonprime Edition" | Resurrection's own version (`1.0.0-dev` today), crediting the 1.1.2 base; the shown version is no longer the install marker | Resurrection has its own version numbers | `PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`, `VERSIONING.md`, `ca4e660` (accepted through `86f8e74`) |

## Install, upgrade and local play

| Area | 1.1.2 | Now | Why | Source |
|---|---|---|---|---|
| Fresh install | The web installer; modules installed and activated by hand afterwards | `php scripts/install.php` into an empty database only; it activates the 23 recommended bundled modules by default (`--modules=none` opts out), can write the config file (`--write-config`, 0600, outside the served directory unless asked), and creates the administrator | A fresh install ready to play | `PHASE4-TASK1B-FRESH-INSTALL-20261029.md`, `MODERN-INSTALLATION.md`, `504b5aa` |
| A 1.1.2 database | The installer upgraded it in place | Refused: "unsupported", nothing changed. There is no 1.1.2 migration; installs are fresh only | Jim's decision, 2026-10-24 | `PHASE4-TASK1B-FRESH-INSTALL-20261029.md`, `ROADMAP.md` (Phase 4 decisions), `504b5aa` |
| Upgrades | The installer compared `installer_version` and ran its upgrade statements | Numbered steps in `upgrades/` recorded in an `upgrade_steps` ledger, run by `php scripts/upgrade.php` (`--dry-run`, `--backup-confirmed`); "Upgrade Needed" when behind; a downgrade refused; `cron.php` refuses a database that is not current; `installer_version` no longer written | Every release must upgrade cleanly from the one before | `PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`, `ca4e660`, `86f8e74` |
| Local play | None | `python3 scripts/local-play.py up` runs a local game in Docker (127.0.0.1 only), for play and development, never hosting | One-command local setup | `PHASE4-TASK3-LOCAL-SETUP-20261029.md`, `0c39020` |

## Phase 5: Resurrection UI

| Area | 1.1.2 | Now | Why | Source |
|---|---|---|---|---|
| Color codes (lands in task 3) | Each skin's CSS gave the codes its own colors, mostly saturated web colors for light text on dark or light grounds (jade: `` `1 `` `#0000FF`, `` `~ `` `#000000`, `` `@ `` `#00FF00`) | Same codes, same classes, same meanings; new values from `UI-DESIGN-SPEC.md` in `assets/ui/tokens.css`, each 4.5:1 or better on the panel and raised colors. `` `~ `` (black) becomes a dark warm grey, readable on the dark UI | Jim's new UI (2026-10-30); readable contrast on every code | `UI-DESIGN-SPEC.md` ("Color codes"), `PHASE5-TASK2-TOKENS-20261030.md` |
