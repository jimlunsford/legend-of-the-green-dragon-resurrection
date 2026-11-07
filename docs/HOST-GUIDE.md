# Host guide

How to run Legend of the Green Dragon - Resurrection on a web server, from an empty hosting account to a game that
runs, upgrades and gets backed up. It is written for a host who has never seen the code. It covers both kinds of
host: one with a shell (SSH) and one with only a hosting panel (cPanel, Plesk or similar).

The steps are short. Where a step depends on your kind of host, it says so. Words in `code font` are file names,
commands, settings or values to type exactly.

**Known limitations of a hosted game (rc.1).** There is no password recovery by email: an administrator resets a
password in the user editor. There is no email verification: the setting `requirevalidemail` must stay off (when it
is on, nobody can create an account). Mail from the game is best effort ("Mail", below). The project's own public
hosting gates are listed in `ROADMAP.md`, "Public hosting".

## 1. What you need

- **PHP 8.4 or 8.5.** Nothing older or newer: `composer.json` allows only these two.
- **These PHP extensions:** `ctype`, `filter`, `json`, `mbstring`, `pdo`, `pdo_mysql` and `session` (the list in
  `composer.json`). Most hosts have them all on. `setup.php` checks them for you.
- **A database: MariaDB 11.4 or MySQL 8.4.** These are the two the project tests on every change: PHP 8.4 with
  MariaDB 11.4, and PHP 8.5 with MySQL 8.4 (`docs/SUPPORTED-PLATFORMS.md`). Other versions may work; they are not
  tested.
- **Strict SQL mode.** The game turns on `STRICT_TRANS_TABLES`, `NO_ZERO_DATE`, `NO_ZERO_IN_DATE`,
  `ERROR_FOR_DIVISION_BY_ZERO` and `NO_ENGINE_SUBSTITUTION` for its own connection and keeps the server's other modes.
  Do not weaken the server's SQL mode for it.
- **An empty database** with its own user, allowed only on that database. Create it with the `utf8mb4` character set
  (most panels do by default). Note the database server, database name, user and password.
- **HTTPS** for the site, with a real certificate (most panels give a free one). `setup.php` refuses plain HTTP, and
  the game marks its session cookie Secure only when PHP sees `HTTPS` set to `on`. Apache's mod_ssl and the sample
  nginx rules set it. Behind a proxy that ends TLS, set it in the site's server configuration; never set it from a
  header a visitor can send.
- **A way to run maintenance**: a scheduler that runs a PHP command (best), or one that can fetch an address
  ("Daily maintenance", below).
- **PHP settings** (in `php.ini`, or your panel's PHP settings page):
  - `display_errors` off and `log_errors` on, so errors go to the log and never to a player.
  - `memory_limit` 32M or more (the game asks for 64M itself where the host allows it).
  - `expose_php` off, so PHP does not announce its version.
  - `zend.exception_ignore_args` on (the game also sets it).
  - `session.save_path` a folder only this site uses, so other sites on the server cannot read its sessions.
  - `output_buffering` may be on or off; the game works either way.

A shell is not required. Every step below has a way through the panel.

## 2. What to upload

Take the files from the release (or from a clone of the repository). Upload only what the game needs.

### Upload these

Into the site's folder (the docroot, the folder the site's address opens, for example public_html):

- `assets/`
- `images/`
- `templates/`
- `lib/`
- `src/`
- `modules/`
- `upgrades/`
- `scripts/install.php` and `scripts/upgrade.php`, inside a scripts folder: the shell path uses them
- `*.php`: every PHP file in the top folder, including `common.php` and `battle.php`, which the pages include
- `favicon.ico` and `favicon.png`
- `LICENSE.txt`, `README.txt` and `NOTICE`: the license and the credits, which must travel with the game

### Do not upload

The game does not use these. If one is uploaded by mistake, the server rules in section 3 still refuse it.

- `vendor/`: development tools only. The game never loads it, and it shows every package and version. Never run
  `composer install` in the site's folder.
- `.git/`, `.github/`, `.gitignore`: the project's history and its own automation.
- `tests/`, `lab/`, `local/`, `docs/`: tests, the historical lab, local play and the project's documents.
- `scripts/art/`, `scripts/lint-php.py`, `scripts/local-play.py`, `scripts/release-upgrade-check.py`,
  `scripts/tooling-shards.py`, `scripts/validate-project.py`, `scripts/verify-historical-baseline.py`: the
  project's own tools (everything in the scripts folder but the two commands above).
- `composer.json`, `composer.lock`, `project.json`, `phpunit.xml`, `phpstan.neon`, `phpstan-new.neon`,
  `phpstan-baseline.neon`: development files.
- `*.md`: the project's Markdown documents (`README.md`, `ROADMAP.md`, `CLAUDE.md` and the others).

Upload into a new, empty folder. Leaving the files of an older copy beside the new ones mixes two versions.

## 3. The docroot and the paths a server must refuse

The game's folder is the docroot: the folder holding `index.php`. The game can also live in a sub-folder of a larger
site; then that sub-folder is the game's folder.

### Served on purpose

- `*.php`: the pages in the game's folder (each one starts by marking itself as a page)
- `assets/`: the style sheets, scripts, fonts and art
- `images/`: the few 1.1.2 images still used
- `templates/`: the page template (harmless to serve)
- `favicon.ico`, `favicon.png`
- `LICENSE.txt`, `README.txt`, `NOTICE`: the license and credits
- `.well-known/`: certificate renewals (your panel may create it)

### Refused

Each of these answers "not found" (404) or "forbidden" (403). Why, in one line each:

- `lib/`, `src/`, `modules/`: the game's internal code. Each file there already answers a direct request with an empty
  404; the rule makes sure nothing in the folder is ever served.
- `upgrades/`: the upgrade steps, run only by the upgrade, never by a visitor.
- `scripts/`: the install and upgrade commands, for the shell only. They answer 404 over the web anyway.
- `vendor/`, `tests/`, `docs/`, `lab/`, `local/`, `var/`: development material that does not belong on a server.
- Dotfiles and dot folders (`.git/`, `.github/`, `.gitignore`, `.htaccess`, `.env`, `.local-play/`): history,
  automation, server settings and secrets. Except `.well-known/`.
- `dbconnect.php`: the configuration file, with the database password. PHP reads it from the disk; nobody needs to
  request it.
- `prefixes.php`: an optional table prefix map, read the same way.
- `resurrection-setup.php` and `resurrection-setup-failures.php`: the setup token file and its failure counter
  (section 5). `setup.php` reads them from the disk; a request for them is refused.
- `common.php`, `battle.php`: included by the pages, never opened directly. They answer 404 anyway.
- `composer.json`, `composer.lock`, `project.json`, `phpunit.xml`, `phpstan.neon`, `phpstan-new.neon`,
  `phpstan-baseline.neon`: development files.
- `*.md`: Markdown documents anywhere, including `assets/README.md`.

### Sample server rules

Three sample files hold these rules. They are samples, not active: the game never reads them, and they sit in
`docs/`, which you do not upload. Copy the one you need, change the lines marked CHANGE, and check it.

- `docs/hosting/nginx.conf`: nginx with PHP-FPM. Check with `nginx -t`.
- `docs/hosting/apache.conf`: an Apache 2.4 virtual host, with mod_php or PHP-FPM. Check with `apachectl -t`.
- `docs/hosting/htaccess`: the same rules for a host where you cannot edit the server's configuration. Upload it into
  the game's folder and rename it `.htaccess`. If every page then answers 500, the host does not allow one of its
  directives: delete it again and ask the host (the file's comments say which directives it needs).

A panel host without either can still run the game: the internal code refuses direct requests itself, the
configuration file and the token file show an empty page, and the files in "Do not upload" are not there. Keep the
configuration file outside the folder when you can (section 4).

## 4. The configuration file

The game keeps its database settings in one PHP file. Where it looks:

1. If the environment variable `LOGD_DBCONNECT` is set, that absolute path, and only it. A `dbconnect.php` in the
   game's folder is then ignored.
2. Otherwise, `dbconnect.php` in the game's folder.

**Use `LOGD_DBCONNECT` when you can**, with the file in a folder outside the docroot, for example a folder named private
beside public_html. The database password then never sits in a folder the web server serves.

How to set it, depending on the server:

- **Apache:** `SetEnv LOGD_DBCONNECT /home/you/private/dbconnect.php` in the virtual host, or in the game's
  `.htaccess` if the host allows it.
- **PHP-FPM:** `env[LOGD_DBCONNECT] = /home/you/private/dbconnect.php` in the site's pool file, or
  `fastcgi_param LOGD_DBCONNECT /home/you/private/dbconnect.php;` in nginx.
- **A hosting panel:** some panels have an environment variables setting for the site or its PHP. If yours has, add
  `LOGD_DBCONNECT` there.
- **The scheduler and the shell** need it too: the commands in sections 5 and 7 show how.

The path must be absolute (start with `/`). If it is not, the game shows the configuration page (below).

**Owner and mode.** The file holds the database password, so only the site's own user, and the web server where it
must, may read it:

- When PHP runs as your own user (most panels): owner you, mode `0600`.
- When PHP runs as the web server's user (for example www-data) and the file is yours: owner you, group the web
  server's group, mode `0640`.
- Never readable by everyone.

The install writes the file `0600`, owned by whoever ran it: you, when you install from the shell, or PHP's user,
when you install through `setup.php`. If PHP runs as another user than the one who wrote it, change its group and
mode as above, or PHP cannot read it.

**The configuration page.** When the file exists but the game cannot use it, every page shows "The game cannot read
its configuration" (status 503) and says which file and why:

- "The file exists, but PHP cannot read it.": owner, mode, or a folder above it that PHP's user cannot enter.
- "The file could not be loaded": the file is damaged, or was changed by hand. The page gives the line PHP stopped at.
- "LOGD_DBCONNECT is set, but it is not an absolute path.": fix the variable.

The same reason goes to the PHP error log once. Nothing from the file is ever shown or logged. A file that does not
exist is not this page: the game shows "The game has not yet been installed".

## 5. Install

Choose one: with a shell (5.1) or without one (5.2). Both run the same install.

### 5.1 With a shell

1. Upload the files (section 2) and point the site at the game's folder.
2. Sign in to the shell and go to the game's folder.
3. Put the values in the environment, not on the command line, so they stay out of the shell's history (`read -s`
   asks without showing what you type):

   ```bash
   export LOGD_DBCONNECT=/home/you/private/dbconnect.php
   export DB_HOST=localhost DB_NAME=yourdb DB_USER=youruser
   read -s DB_PASS && export DB_PASS
   export RESURRECTION_ADMIN_LOGIN=admin RESURRECTION_ADMIN_EMAIL=you@example.org
   read -s RESURRECTION_ADMIN_PASSWORD && export RESURRECTION_ADMIN_PASSWORD
   php scripts/install.php
   ```

   With `LOGD_DBCONNECT` set and no file there yet, the command writes the configuration file at that path (`0600`)
   and installs. `DB_PREFIX` is optional (letters, digits and underscores). The administrator's password must be 12
   to 72 bytes. The command prints a one-line summary (tables, modules, the administrator) and never a password.

   Without `LOGD_DBCONNECT` the game reads `dbconnect.php` in its own folder, so the command must write it there:
   `php scripts/install.php --write-config --allow-served-path /home/you/public_html/dbconnect.php` (the full path
   of `dbconnect.php` in the game's folder). The server rules in section 3 then refuse requests for it.

4. `--modules=none` installs every bundled module turned off; the default turns on the recommended ones.
5. Check the file's owner and mode (section 4), set the server URL (section 6), and set up maintenance (section 7).
6. Sign in with the administrator account.

### 5.2 Without a shell: `setup.php`

1. Turn on HTTPS for the site.
2. Create the database and its user in the panel (section 1).
3. Upload the files (section 2).
4. Optional, if you can set `LOGD_DBCONNECT` (section 4): create the private folder now and set the variable. The
   configuration file and the token file then live there.
5. Open `https://your-site/setup.php`. It checks PHP's version and extensions, `memory_limit`, `max_execution_time`,
   whether the configuration's folder can be written, and HTTPS. Fix anything that fails, then reload.
6. The page shows a file of two lines and the folder to put it in: the game's folder, or the `LOGD_DBCONNECT` file's
   folder when that is set. In the panel's file manager, create `resurrection-setup.php` there and paste the two lines.
   - Line 1 makes the file show nothing if anyone requests it.
   - Line 2 is the token: `rzsetup-` and 32 letters and digits. Use the one the page suggests.
   - Set the file to `0600` if the file manager allows.
   - It works once, for 60 minutes from when you save it. Create it in the panel's file manager: some FTP programs
     keep your computer's file time, which can make it look too old.
   - **The optional third line.** If TLS ends at a proxy in front of the site and PHP sees plain HTTP, add a third
     line, exactly `https-terminated-upstream`. The page then trusts the proxy's `X-Forwarded-Proto` header for this
     one install. Without that line the header is never trusted.
7. Back on the page, type the token. Then fill in the database values (they are skipped when the configuration file
   already exists and its database is empty), the first administrator's login, password and email, and the modules
   (recommended or none). Press Install and wait: it takes up to a minute.
8. The last screen says what was done: where the configuration file was written, the tables and modules, and that the
   token file was deleted. If it says PHP could not delete the token file, delete it now.
9. On the same screen, choose how maintenance runs (section 7).
10. Set the server URL (section 6), then sign in with the administrator account.

**Rules `setup.php` keeps.** It answers only while the game is not installed, or while its database is behind (an
upgrade). Once the game is installed and current it is an empty 404. Plain HTTP is refused, except from the server
itself. After 10 wrong attempts in 15 minutes against one token file it refuses that file for the rest of the 15
minutes ("Too many attempts"); a new token file starts a new count. Install failures are counted in
`resurrection-setup-failures.php` beside the token file, which the install deletes when it succeeds.

### 5.3 If an install fails

Nothing is ever cleared automatically.

- **Refused before it started** ("Installation refused", for example a database that is not empty, a wrong password
  or a weak administrator password): nothing was changed. Fix what it says and try again.
- **Failed partway** ("Installation failed"): the database now holds part of a game, so `setup.php` and
  `scripts/install.php` refuse it, and the game shows "Game Unavailable: its database is not a completed
  installation". Empty the database in the panel (for example in phpMyAdmin: select every table, then Drop), then
  run the install again. A configuration file the install already wrote is kept and used.
- **The server stopped answering during the install** (a timeout): look at the database. Empty, try again. Holding
  tables, treat it as failed partway.

The PHP error log has one line for a web install that failed: `Resurrection: web install failed (<phase>) at
<file>:<line>`.

## 6. Settings to check after the install

Open Game Settings (`configuration.php`, in the Grotto) as the administrator:

- **Server URL** (`serverurl`): the game's own address with `https://` and a trailing slash, for example
  `https://game.example.org/`. The install stores the address of the request it ran in (`setup.php` over HTTPS:
  the right one, as PHP saw it; `scripts/install.php`: `http://localhost/`), so check it. The game puts it in its
  mail and in the web maintenance address, so it must be `https://`.
- **Admin Email** (`gameadminemail`): a real address you read. It is the sender of the game's mail and receives
  petition notices when `emailpetitions` is on.
- **Public source code URL** (`sourceurl`): the "View PHP Source" link in every footer. The license
  (`LICENSE.txt`) requires you to give the complete source to anyone you let play. The default points at the
  project's public repository, which is right only for an unchanged copy. If you change the code, publish your
  changed source and point this setting at it.
- **The credits stay.** Every page's footer carries the 1.1.2 copyright and license block and the line "A Phoenix 2:33
  LLC Project by Jim Lunsford" with its two links. The license terms require them: do not remove, hide or shrink
  them. If the page template is changed so that it lacks them, the game refuses it and draws its built-in frame with
  the credits instead. (The attribution line's wording is under legal review; this is the rule as it stands today.)
- **Content Security Policy** (`csp_mode`, under Security Headers): leave it at `enforce`. Every page the game ships
  works under it. Choose `report-only` only for a third-party module that still prints inline script or inline style:
  its pages break under `enforce`. `report-only` blocks nothing and makes the browser report what it would block in
  its console; `off` sends no policy. The other protections (frame, content type, referrer) are sent whatever it
  says.
- **Require users to validate their email address** (`requirevalidemail`): leave it off (see the known limitations
  at the top).

## 7. Daily maintenance

Once per game day (every 6 hours with the default 4 game days a day) the game runs its daily maintenance: cleanups,
character expiration and the modules' daily events. Something must start it. Ask every 15 minutes; it runs at most
once per game day, and the other requests do nothing.

### With a scheduler that runs PHP (best)

Run `cron.php` with the panel's PHP 8.4 or 8.5, every 15 minutes. Leave web maintenance off.

- **cPanel**, Cron Jobs, "Once Per Fifteen Minutes", command:

  ```sh
  LOGD_DBCONNECT=/home/you/private/dbconnect.php /usr/local/bin/php /home/you/public_html/cron.php > /dev/null
  ```

- **Plesk**, Scheduled Tasks, "Run a command", every 15 minutes, with the site's PHP:

  ```sh
  LOGD_DBCONNECT=/var/www/vhosts/example.org/private/dbconnect.php /opt/plesk/php/8.4/bin/php /var/www/vhosts/example.org/httpdocs/cron.php > /dev/null
  ```

- **A shell's crontab**, as the site's user:

  ```sh
  */15 * * * * LOGD_DBCONNECT=/home/you/private/dbconnect.php php /home/you/public_html/cron.php > /dev/null
  ```

Change the paths to yours; the PHP path differs between hosts (the panel shows it). Leave out the
`LOGD_DBCONNECT=...` part if the configuration file is `dbconnect.php` in the game's folder. Run it as the site's own
user, so it reads the same configuration file as the pages.

On success `cron.php` prints one line of JSON (`complete` or `already-complete`), which `> /dev/null` discards. Its
errors go to the scheduler's error output, which most panels mail to you: for example "Maintenance refused: there is
no configuration file, so the game is not installed. Nothing was run." It then exits with code 1. Over the web,
`cron.php` answers 404.

### With a scheduler that can only fetch an address

For schedulers such as Plesk's "Fetch a URL", cron-job.org or EasyCron:

1. In Game Settings, open Web maintenance and press "Turn on web maintenance" (or choose it on `setup.php`'s last
   screen). The page shows the secret address and the key once. Copy them then: the game keeps only a fingerprint.
2. **Prefer the header.** If the scheduler can send a request header, ask for `https://your-site/webcron.php` and send
   the key in the `X-Resurrection-Maintenance-Key` header. The key then stays out of address logs.
3. Otherwise use the whole address, `https://your-site/webcron.php?key=...`. It must start with `https://`. If the
   address shown starts with `http://` or names the wrong site, set the server URL (section 6), then press "Make a
   new address".
4. Every 15 minutes.

A request with the right key answers `OK` whatever happened. Any other request is an empty 404. If the address leaks,
press "Make a new address": the old one stops at once. "Turn off web maintenance" stops every address.

### The status line

Game Settings, Web maintenance, shows "Last maintenance": when it last ran, by which path ("the command (cron.php)"
or "the web address") and how it went (complete, already done for this game day, skipped because another run held
the lock, or failed: see the PHP error log). Check it the day after you set maintenance up.

### With neither

The game still plays: each player's own new day is separate. But the daily cleanups, character expiration and the
modules' daily events never run, and old data piles up.

## 8. Checks after the install

Make these checks once the game is installed, and after every upgrade:

1. Open `https://your-site/dbconnect.php` (if the file is in the game's folder) and `https://your-site/resurrection-setup.php`
   (while one exists). Each must give an empty page, or the server's refusal if you added the rules. If you see text
   that starts with `<?php`, the server is showing PHP files as text: stop, and fix the server before anything else.
2. Open a refused path, for example `https://your-site/lib/` or `https://your-site/composer.json`: 404 or 403.
3. Look at the headers of the home page (your browser's developer tools, Network): `Content-Security-Policy`,
   `X-Frame-Options`, `X-Content-Type-Options` and `Referrer-Policy` are there. With `csp_mode` at `report-only` the
   first is `Content-Security-Policy-Report-Only`.
4. The day after: Game Settings' status line shows a maintenance run (section 7).
5. Sign in as the administrator, and create a player account in a private window, to see the game play.

## 9. Upgrade

New versions can change the database. Upgrades go one release at a time. Read the release notes first.

1. **Back up** the database and the configuration file (section 10). An upgrade that stops partway cannot be rolled
   back: the backup is the way back.
2. **Upload the new version into a new folder** beside the old one. If the configuration file is `dbconnect.php` in
   the game's folder, copy it into the new folder. Copy any `.htaccess` too. Then swap the folders' names in the
   file manager (the old folder becomes, for example, public_html_old). If the new version changes the database, the
   game now shows "Upgrade Needed" and no one can play until the upgrade is done.
3. **Apply the upgrade**, one of:
   - **With a shell**, in the new folder, with `LOGD_DBCONNECT` set as for the install:
     `php scripts/upgrade.php --dry-run` lists what is pending and changes nothing;
     `php scripts/upgrade.php --backup-confirmed` applies it. If a step fails, the command stops, names the step and
     changes nothing after it: fix the cause and run it again (applied steps are skipped), or restore the backup.
   - **Without a shell**, open `https://your-site/setup.php`. It shows what the upgrade changes. Create a new
     `resurrection-setup.php` as for the install (section 5.2), type its value, an administrator's login and password
     (an account with the top administrator flag, as the first administrator has), tick the backup box and press
     Upgrade. The upgrade runs in parts, so no request runs longer than the host allows: press Continue each time the
     page shows "Applied N of M". A part cut off by the host's time limit is not recorded and runs again on the next
     Continue. When the page says the database is current, the token file is deleted.
4. **Check**: the "Upgrade Needed" page is gone, the footer shows the new version, and the checks in section 8 pass.
5. When all is well, delete the old folder.

## 10. Backups and restore

Back up the database and the configuration file. The database holds every account, so keep backups private.

**With a shell:**

```sh
mysqldump --single-transaction --default-character-set=utf8mb4 -h HOST -u USER -p DATABASE > resurrection-backup.sql
```

Check that the file ends with a `-- Dump completed` line. To restore: empty the database (drop and create it again),
then `mysql --default-character-set=utf8mb4 -h HOST -u USER -p DATABASE < resurrection-backup.sql`.

**Without a shell:**

- The panel's own backup tool, if it backs up databases (cPanel Backup, Plesk Backup Manager), or
- phpMyAdmin: select the database, Export, method Custom, every table, format SQL, and save the file.

To restore with phpMyAdmin: select the database, select every table and Drop them, then Import the saved file.

**Either way**, keep a copy of the configuration file (`dbconnect.php` or the `LOGD_DBCONNECT` file): without it the
game cannot reach its database. Restore a backup with the version of the game it was taken with: a database from a
newer version shows "Game Unavailable" in an older one.

## 11. Mail

The game sends mail through PHP's `mail()`: new in-game mail for players who asked for it, petition notices to the
admin address (when `emailpetitions` is on) and warnings before an inactive character expires. `mail()` needs a mail
transport on the server (most hosts have one; `sendmail_path` in PHP's settings points to it) and a real
`gameadminemail` as the sender. Mail from a server address that does not match the site's domain often lands in spam.

Mail is best effort. When a message cannot be handed to the transport, the game goes on:

- An in-game mail is still delivered in the game; the PHP error log gets `Mail notification failed after game mail commit.`
- A petition is still filed; the game log (Grotto, Game Log, petition) says its email notice could not be sent.
- An expiration warning that could not be sent is tried again at the next maintenance; the game log (char expiration)
  counts them.

Password recovery and email verification do not exist in this version (the known limitations at the top).

## 12. Logs

- **The PHP error log** (the host's error log, or `error_log` in PHP's settings): errors, with the file and line
  only, never SQL, passwords or keys. Lines the game writes itself:

  | Line | Means |
  |---|---|
  | `Resurrection: the database configuration file cannot be used (...)` | The configuration page was shown; the reason and the file follow (section 4) |
  | `Resurrection: templates/resurrection.htm was refused; the safe frame is used: ...` | The page template was changed and fails its checks; the game draws its built-in frame. Restore `templates/` from the release |
  | `Resurrection: web install failed (...) at ...` | A `setup.php` install failed partway (section 5.3) |
  | `Resurrection: web upgrade failed (...) at ...` | A `setup.php` upgrade part failed; the page said what was applied |
  | `Resurrection: web maintenance failed at ...` | A web maintenance run failed; Game Settings' status line says failed |
  | `Mail notification failed after game mail commit.` | `mail()` refused a message (section 11) |
  | `Character expiration failed for one account; it was left unchanged.` | Maintenance could not delete one expired character; it tries again next time |
  | `Runtime error in <file>:<line>` (inside a PHP "Uncaught" line) | A fault in the game or a module: report it with the file and line |

- **`cron.php`'s error output**: the scheduler's mail or log (section 7).
- **In the game**, for administrators: the Game Log (`gamelog.php`) for game events such as maintenance, settings
  changes and petitions; each player's debug log in the user editor; failed sign-ins (also the setup page's failed
  upgrade attempts) in the database's `faillog` table.
- The game does not rotate or trim the server's logs: that is the host's (most panels do it).

## 13. Troubleshooting

**"The game cannot read its configuration" (503).** The configuration file exists but cannot be used. The page says
why (section 4): owner and mode, a folder PHP cannot enter, a damaged file, or a `LOGD_DBCONNECT` that is not an
absolute path. Fix that and reload.

**"The game has not yet been installed".** There is no configuration file where the game looks. A new game: install
it (section 5). A game that ran before: the configuration file is gone, or `LOGD_DBCONNECT` points somewhere else
than before. Put the file back from your backup; never install again over a game you want to keep.

**"Database Connection Error".** The configuration file is read, but the database server or the database cannot be
reached with its values. Check the database server's name, the database, the user and its password in the panel.

**"Game Unavailable: its database is not a completed installation".** An install stopped partway. Empty the
database and install again (section 5.3).

**"Upgrade Needed".** The files are newer than the database. Apply the upgrade (section 9, step 3). Players are
sent back to the game when it is done.

**"Game Unavailable" for another reason.** The page says which:

- A newer version has upgraded this database (a downgrade): put the newer version's files back, or restore the backup
  taken before that upgrade.
- The database's record of upgrade steps is out of order: `php scripts/upgrade.php --dry-run` names the missing step;
  restore the backup taken before the last upgrade.
- The database holds a 1.1.2 game: Resurrection does not upgrade or migrate a 1.1.2 database. Install fresh into an
  empty database.

**"This page needs HTTPS" (`setup.php`, 403).** Open it with `https://`. If HTTPS already ends at a proxy in front of
the site and PHP sees plain HTTP, add the third line `https-terminated-upstream` to the token file (section 5.2). For
the game itself, make the server tell PHP `HTTPS=on` (the sample rules do), so the session cookie is marked Secure;
never decide it from a header the visitor can send.

**`setup.php` answers an empty page (404).** That is on purpose whenever the game is installed and current, or the
configuration file or database cannot be used. Open the home page to see which.

**The token is refused.** The page says why: no file yet, a file PHP cannot read, lines that are not exactly as shown,
older than 60 minutes, more than 5 minutes ahead of the server's clock, or already used. Create a new file with a new
value.

**Every page answers 500 right after adding `.htaccess`.** The host does not allow a directive in it. Delete it
(section 3).

**Pages work but look wrong, or buttons do nothing.** A third-party module may print inline script or style; see
`csp_mode` (section 6). The browser's console names what the policy refused.

## Where these facts come from

This guide follows the code at the commit that added it. The evidence for each part: `docs/PHASE6-RC-READINESS-20261106.md`
(sections 1.3 to 1.7, 4.3 and 11), `docs/PHASE6-TASK3-HARDENING-20261106.md` (headers, `csp_mode`, the internal-file
guard, the configuration page, `LOGD_DBCONNECT`, `vendor/`), `docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md` (section 7
and its decisions), `docs/PHASE6-TASK5A-SERVICES-WEBCRON-20261106.md` (maintenance, web maintenance),
`docs/PHASE6-TASK5B-SETUP-PAGE-20261106.md` (`setup.php`) and `docs/PHASE6-TASK6-HOST-GUIDE-20261107.md` (this guide,
the sample rules and their checks). Local play and development are in `docs/MODERN-INSTALLATION.md`.
