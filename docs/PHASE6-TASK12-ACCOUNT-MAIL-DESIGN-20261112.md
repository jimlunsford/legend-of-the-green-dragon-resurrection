# Phase 6 task 12: mail, password recovery and email verification, design and threat model (S11)

Date: 2026-11-12. Starting head: `2999947`. Status: **design for Jim's approval. No code, no tests.** The build tasks
in section 12 build what Jim approves.

Jim made S11 an rc.1 blocker on 2026-11-12 (`ROADMAP.md`, Phase 6, decisions of 2026-11-12, item 2). Today the game has
no "forgot my password" flow and no email verification. 1.1.2's recovery flow stays disabled, an administrator resets
passwords by hand in the user editor, and `requirevalidemail` is locked off because nothing verifies an address. This
document designs the replacement before any of it is written.

Section 13 holds the open questions for Jim. The game ships with no runtime Composer packages, and nothing here
changes that.

## Starting point: what exists today

Read from the code at `2999947`, and from 1.1.2 at the tag `historical-source-1.1.2`.

| Part | Where | What it does now |
|---|---|---|
| Sign-up | `create.php` | POST plus CSRF. Login 3 to 25 letters and digits (`resurrection_validate_account()`), email optional unless `requireemail`, validated with `FILTER_VALIDATE_EMAIL` and at most 128 bytes; `blockdupeemail` checked; password 12 to 72 bytes. With `requirevalidemail` on, no account is made at all ("Account creation is unavailable."). `op=val` (1.1.2's validation link) answers 410; `op=forgot` is not an op, so it is a 400. |
| Sign-in | `login.php`, `resurrection_authenticate()` (`lib/accounts.php`) | POST plus CSRF. Refuses a locked account, an account whose `emailvalidation` is not empty, and a wrong password, all with one message. A failure writes a `faillog` row with the fixed word `invalid_credentials` and the IP. Success: `resurrection_begin_login()` raises `authversion`, the session id rotates, the session's `auth_version` is set. |
| Passwords | `src/Security/Passwords.php` | `password_hash()` with `PASSWORD_DEFAULT` (bcrypt) for 12 to 72 bytes with no NUL; `verify()` refuses a stored hash typed as a password. |
| Session generation | `accounts.authversion` (`lib/installer/fresh_install.php`), `lib/forcednavigation.php:64` | Every request compares the session's `auth_version` with the row's `authversion`; a mismatch ends the session. Sign-in, sign-out, a self-service password change and an admin's password change all raise it. A Dragon kill keeps it (`lib/dragon_outcomes.php`, `$nochange`). |
| Self-service password change | `prefs.php`, `resurrection_change_password()` | Needs the current password; raises `authversion` in a transaction bound to the old hash; the session rotates. |
| Email change | `prefs.php:164` | Writes `$session['user']['emailaddress']` (saved by `saveuser()`) with no current password and no verification. Checked with `is_email()` (`lib/is_email.php`), whose pattern has no anchors. With `requirevalidemail` on, a change is refused. `blockdupeemail` is not checked. |
| Admin reset | `lib/user_editor.php:236` | `SU_EDIT_USERS`: a new password is hashed by `Passwords::hash()`, `authversion` raised, under the account lock, with a `debuglog` line. The "special" action clears `emailvalidation`. |
| Mail transport | `resurrection_send_email()` (`lib/systemmail.php:72`) | PHP `mail()`; refuses an address that fails `FILTER_VALIDATE_EMAIL` and a CR or LF in the subject or sender; the From header is `gameadminemail` (not validated as an address). True only when `mail()` accepted the message; a warning is caught and returns false. |
| Mail callers | `lib/systemmail.php` (notifications for in-game mail, opt-in `emailonmail`), `lib/char_expiration.php` (the warning before an inactive character expires), `lib/petition/petition_default.php` (petition notices to `gameadminemail`, opt-in `emailpetitions`) | Each sends after its own commit; a refusal is logged (error log or game log) and the game goes on. The notification and petition mails take their link from `resurrection_server_url()` (`lib/server_url.php`), never from the request. |
| `serverurl` | `lib/server_url.php`, `lib/installer/installer_default_settings.php:100` | `resurrection_server_url()` returns the stored value when `resurrection_valid_server_url()` accepts it (`http` or `https`, a host, optional port and path), else null. The install stores the install request's scheme, host and path; the host guide asks the host to check it and make it `https://`. Game Settings does not validate it when saved. |
| `requirevalidemail` lock | `resurrection_locked_settings()` (`lib/settings.php:61`), `lib/configuration_descriptors.php:55` (`locked`), `lib/typed_editor.php:198` | `getsetting()` returns `0` whatever is stored; Game Settings shows it locked and refuses a change. |
| CSRF and one-use intents | `src/Security/Csrf.php`, `src/Security/ActionToken.php`, `resurrection_require_post()` | 32-byte session token compared with `hash_equals()`; one-use action tokens bound to a scope and context. |
| Kept pages | `src/Security/KeptPage.php`, `resurrection_secret_output()` (`lib/pageparts.php`) | A signed-in player's last page is kept in `accounts_output` with its form tokens replaced by markers. A page that shows a secret once is sent `no-store`, and the kept copy holds a fixed note instead of the secret. |
| Security headers | `src/Http/SecurityHeaders.php` | `X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy: same-origin`, CSP `default-src 'self'` with no inline script or style, enforced by default. |
| Failure records | `faillog` (`eventid`, `date`, `post` tinytext, `ip` varchar(40), `acctid`, `id` varchar(32)) | Written by `login.php` (failed sign-ins) and `lib/setup/guard.php` (setup failures, counted for the 10-in-15-minutes limit). Nothing deletes old rows. |
| Maintenance and expiration | `lib/maintenance.php`, `lib/char_expiration.php` | Under the maintenance lock, once per game day: 1.1.2's three expiration rules (`expireoldacct` 45, `expirenewacct` 10, `expiretrashacct` 1 day for an account registered and never signed in, `laston <= regdate`), then the warning mail five days before `expireoldacct`. |
| Upgrade steps | `lib/upgrade.php`, `upgrades/README.md`, `src/Upgrade/StepCatalog.php` | No steps exist yet. A step is `upgrades/NNNN-name.php` returning a description and an `apply` closure; it must check for its own changes before making them, because DDL commits at once; a fresh install records every step as applied; `tests/unit/UpgradeTest.php` holds a fresh install and an upgraded database to the same schema. |
| Mail in tests | `tests/tooling/test_web_application.py:72` | The fixture server runs with `sendmail_path` pointing at a capture program that records each message and exits 1; nothing reaches a real mail server. |

### 1.1.2's recovery and validation (at the tag), and why it stays disabled

- **Forgot password** (`create.php?op=forgot`): took a character name, looked it up with the name pasted into SQL, and
  answered "Could not locate a character with that name" or "that account does not have an email address", so it told
  anyone which accounts existed and which had an address. It stored `"x" . md5(date . password hash)` in
  `emailvalidation`, a value built from the time and the stored hash, kept until used, with no expiry. The mail named
  the requester's IP and linked to `http://` plus `SERVER_NAME`, `SERVER_PORT` and `SCRIPT_NAME`, so the request's
  Host header chose where the link pointed.
- **The link** (`create.php?op=val&id=...`): looked the value up with it pasted into SQL, then printed a sign-in form
  holding the account's stored password hash as `!md52!<hash>` with `force=1`; `login.php` accepted that as a password.
  Anyone who opened the link, or later read the page from a cache or history, held a credential that worked until the
  password changed.
- **Sign-up validation** (`requirevalidemail`): stored `md5(date . email)`, built from the time and the address the
  form had just shown, mailed the same Host-built link, and blocked sign-in until it was used.
- No limit on requests, so any visitor could send any number of mails to any account's address.

None of it is brought back. Resurrection removed the MD5 password paths, the `!md52!` form and the forgot op; `op=val`
answers 410 and keeps answering 410.

### Findings while reading

Recorded here and in `ROADMAP.md` ("Found during Phase 6"). This task changes no code (the brief); each is scheduled
into a build task in section 12.

- **F1 (security, small; for 13a).** The expiration warning mail (`lib/char_expiration.php:142`) takes its link from
  `getsetting("serverurl", <a URL built from SERVER_NAME, SERVER_PORT and REQUEST_URI>)`, not from
  `resurrection_server_url()`. A stored value is put in the mail without validation, and when no `serverurl` row
  exists, `getsetting()` saves the request-built default as the setting. Run from `webcron.php`, that default comes
  from the scheduler's request. Every install writes `serverurl`, so this needs the row to be missing. The fix: use
  `resurrection_server_url()` as the notification and petition mails do.
- **F2 (security; for 13c).** `prefs.php` changes the email address with no current password and no verification, and
  checks it with `is_email()`, whose pattern is unanchored (`x a@bb.cc y` passes). A value that fails is still saved
  when `requireemail` is off. `blockdupeemail` is not checked on a change. Today this only affects where notification
  mail goes. Once an address can recover an account, it is a takeover path for anyone who holds a signed-in session
  (section 4.2).
- **F3 (abuse; for 13c, question 7).** Notification mail (`emailonmail`) goes to any address a player typed. A player
  can point a stranger's inbox at the game, and every in-game mail sent to that character then mails the stranger.
- **F4 (context, unscheduled).** `faillog` is never cleaned up, and `resurrection_authenticate()` runs
  `password_verify()` only when the login exists, so sign-in timing shows whether a login exists. Login names are
  public in the game (List Warriors shows every character, and the name is the login), so this reveals little; both
  belong to the open "Abuse controls" hosting gate, not to S11.
- **F5 (context).** `gameadminemail` defaults to `postmaster@localhost.com`, a real outside domain. Mail sent from it
  fails the receiving servers' sender checks. Section 2.5 makes account mail refuse to turn on with it.

## 1. Scope and non-goals

S11 adds four things:

1. **Account mail**, a switch in Game Settings (off by default) that turns on everything below once the host has
   proved mail works (section 2.5).
2. **Password recovery**: a player who forgot their password asks for a link by login or email, opens it, and sets a
   new password.
3. **Email verification**: proof that a player receives mail at their address, at sign-up when `requirevalidemail` is
   on, and from Preferences for any account.
4. **Email change** with the new address verified before it takes effect and a notice to the old one.

It never:

- brings back any of 1.1.2's recovery or validation code, the `!md52!` sign-in, or a link built from the request;
- sends account mail to an address the account does not hold, or to an address typed into the recovery form (the
  form only looks an account up; the mail goes to the address on the account);
- answers a recovery request in a way that shows whether an account exists, has an address, or what state it is in;
- shows or mails a password, a password hash, or the IP of the person who asked;
- signs the player in from a link (a reset ends with the sign-in page);
- adds a runtime Composer package, a framework, or an outside service the game calls;
- changes the administrator's manual reset in the user editor, which stays as it is;
- builds a 1.1.2 database migration (installs stay fresh only).

Not part of S11: sign-in throttling and other flood controls (the "Abuse controls" hosting gate, F4), and SMTP unless
Jim chooses it (question 1).

## 2. Mail transport

### 2.1 Today

`resurrection_send_email()` is the one function that hands mail to PHP `mail()`. It is best effort: `mail()` returning
true means the local transport (sendmail, exim, postfix or the host's wrapper) accepted the message, not that it was
delivered. Account mail goes through the same function, extended (2.3, 2.4), so there is still one path.

### 2.2 `mail()` alone, or `mail()` plus a minimal SMTP client

| | (a) `mail()` only | (b) `mail()`, or an in-house SMTP client when configured |
|---|---|---|
| Hosts it serves | Shared hosts (cPanel, Plesk, DirectAdmin) run a local MTA that signs mail for the site's domain. A VPS host has a shell and can point `sendmail_path` at a relay client such as msmtp, which the host guide can show. | Also hosts with no MTA and no shell (some containers and platform hosts), and hosts that want mail sent through their own mail provider's SMTP. |
| Code | No new transport code. | A client of about 300 to 400 lines: connect with a timeout, read multi-line replies, `EHLO`, `STARTTLS` with certificate and host name checks (or implicit TLS on 465), `AUTH PLAIN` or `LOGIN`, `MAIL FROM`, `RCPT TO`, `DATA` with dot-stuffing and line length limits, `QUIT`; a total time limit per message; every reply code checked. |
| Secrets | None. | An SMTP password (2.2.1). |
| Tests | The existing capture program. | A fake SMTP server in the Python tests that speaks STARTTLS with a certificate made for the test (Python's `ssl`, a key made with `openssl` on the runner), plus refusal cases: a server that does not offer STARTTLS (refused, nothing sent), a wrong certificate (refused), a reply that never ends (time limit), `AUTH` refused, a `RCPT` refused. About one build task of its own. |
| New surface | None. | An outbound connection to a host the administrator types, TLS checks, parsing of server replies, a stored credential. |

**Recommendation: (a), `mail()` only for rc.1** (question 1). The hosts rc.1 is written for (panels with no shell,
`docs/HOST-GUIDE.md`) have a working `mail()` that signs for the site's domain, and a host with a shell can relay
through `sendmail_path` without any game code. An SMTP client is protocol and TLS code written and kept by this
project, plus a stored credential, added to a release whose blocker is recovery, not transport. `resurrection_send_email()`
stays the one seam, so SMTP can be added later as its own task without touching recovery.

#### 2.2.1 If Jim chooses SMTP

- **Where the credentials live: the configuration file**, beside the database password: `$SMTP_HOST`, `$SMTP_PORT`,
  `$SMTP_SECURITY` (`starttls` or `tls`; there is no plain option), `$SMTP_USER`, `$SMTP_PASS`, `$SMTP_FROM`. Not the
  settings table: settings are shown in Game Settings, copied into every database backup and readable by any
  administrator with `SU_EDIT_CONFIG`, and Game Settings has no secret field. The file is already kept outside the
  docroot when `LOGD_DBCONNECT` is set, written 0600 by the installer, and never shown.
- The values are read inside the transport function from the already loaded configuration, passed as
  `#[\SensitiveParameter]`, and never put in an exception message, a log line, the debug log or a page. Game Settings
  shows only "SMTP: configured (host, port, security)" or "SMTP: not configured", never the user or password.
- A failure is logged as one fixed line with the stage and the server's reply code (for example `SMTP refused at AUTH
  (535)`), never the server's reply text, which can echo the user name.
- With no `$SMTP_HOST`, `mail()` is used as now.

### 2.3 Links come from `serverurl` only

Every link in account mail is built from `resurrection_server_url()` and nothing else: never `HTTP_HOST`,
`SERVER_NAME`, `REQUEST_URI` or `X-Forwarded-*`. For account mail the value must also start with `https://`: a link
that carries a token never travels over plain HTTP. When `serverurl` is missing, invalid or `http://`, account mail is
off (2.5, section 6) and no account mail is sent. The other mails keep their current behavior, with F1 fixed.

### 2.4 Header safety and address validation

- **Addresses.** One validator for every address the game sends to or from: at most 128 bytes, no CR, LF or NUL,
  `FILTER_VALIDATE_EMAIL`, and in addition the plain dot-atom form only (letters, digits and
  ``.!#$%&'*+/=?^_`{|}~-`` before the `@`; a domain of letters, digits and hyphens with at least one dot). Quoted
  local parts, comments, IP literals and anything with a comma, space or angle bracket are refused, so a value can
  never be read by the transport as two recipients or as a header. Checked when an address is saved (sign-up, email
  change) and again when mail is sent.
- **Subjects.** Fixed texts written by the game (translated), never a player's name or any value a player typed. A
  translated subject outside ASCII is encoded with `mb_encode_mimeheader()`. CR and LF are refused.
- **Sender.** `gameadminemail`, through the same validator. Account mail refuses to turn on while it is the default
  `postmaster@localhost.com` (F5) or fails the validator.
- **Headers sent.** `From`, `MIME-Version: 1.0`, `Content-Type: text/plain; charset=UTF-8`,
  `Content-Transfer-Encoding: 8bit`, and `Auto-Submitted: auto-generated` (so vacation replies are not sent back).
  Nothing else; no `Reply-To`, no player value in any header.
- **Body.** Plain text. It may name the account's login (letters and digits only), never the character's display name
  (it holds colour codes and player-chosen text).

### 2.5 The test mail, and the account mail switch

Game Settings gets an "Account mail" section, for `SU_EDIT_CONFIG`:

- **Status**: the transport (`mail()`, or SMTP if chosen), `serverurl` and whether it is usable for account mail, the
  sender address and whether it passes, whether account mail is on, the last test mail and its result, and counts of
  account mails accepted and refused by the transport in the last 24 hours (section 5's table).
- **Send a test mail** (POST, CSRF, a one-use action token; at most 5 an hour per administrator). It goes to the
  signed-in administrator's own account address, never to an address typed into the form. The page shows the result
  ("handed to the mail transport" or "refused by the mail transport") and writes a game log line with the same fixed
  words and the administrator's account id. The mail holds a confirmation link (a `mailtest` token, section 3.2).
- **Confirming the test mail.** Opening the link and pressing Confirm stores `account_mail_confirmed` (JSON: the time,
  the `serverurl` and the sender address it was sent with, the administrator's account id) and marks that
  administrator's address verified.
- **Turn account mail on** (POST, CSRF, action token) is refused unless all of these hold: `serverurl` is valid and
  `https://`; the sender passes 2.4; and `account_mail_confirmed` exists with the same `serverurl` and sender as now.
  **Turn it off** is always allowed.
- **Effective state.** Account mail counts as on only while the switch is on and every condition above still holds.
  Changing `serverurl` or `gameadminemail` afterwards turns it off in effect until a new test mail is confirmed, and
  the status says why.

What a confirmed test mail **proves**: the transport accepted a message from this sender; it reached one real inbox;
the link in it, built from `serverurl`, reaches this game over HTTPS; and the confirmation round trip works.

What it **does not prove**: that mail reaches other providers (spam filtering differs by provider and by recipient);
that the sender passes SPF, DKIM and DMARC at every provider; that the transport keeps working later; or that a
player's address is typed correctly. The status counts and section 6 cover the later failures.

## 3. Password recovery

### 3.1 The request

A new entry point, `account.php` (section 8), op `forgot`. The sign-in page and the "login incorrect" message link to
it while account mail is on.

- **The form** takes one field, "Login or email address", plus CSRF. A value with `@` is treated as an address, else as
  a login. Nothing else.
- **The response is the same** in every case: the same status (200), the same page text, "If an account matches and can
  receive mail here, a message with a link is on its way. It works for 30 minutes. Check your spam folder too.", the
  same headers, and the same cookies. That covers: no match, a match with no address, an unverified address, a locked
  or banned account, a staff account that is excluded (question 3), a pending sign-up, a limit reached, account mail
  turned off between the page load and the POST, and a mail that the transport refused. Malformed input (not a string,
  over 128 bytes) gets the same page too; only a missing or wrong CSRF token gets the usual 403.
- **What is looked up.** By login: the one account with that login. By address: every account whose `emailaddress`
  equals it, case-insensitively, and is verified for it (question 2), at most 5 (the oldest first). When several
  accounts share an address, one mail goes to it, listing each eligible account's login with its own link.
- **Eligible** (otherwise nothing is sent): account mail effectively on; the address verified; `locked` is 0; no ban
  matches the account's last IP or ID (the same query `checkban()` runs, without its session side effects);
  `emailvalidation` empty (not a pending sign-up); a staff account only as question 3 decides; within every limit in
  section 5.
- **Equal timing, as far as is practical.** The request does the same database work whatever the outcome: the limit
  counts, one account lookup, one `account_mail_log` row (section 5). Creating a token and the mail are the only
  extra work, and the mail is sent **after the response is complete**: the page is sent with `Content-Length`, then
  `fastcgi_finish_request()` (PHP-FPM) or `litespeed_finish_request()` (LiteSpeed) closes the response before the mail
  is handed to the transport. Where the server has neither (mod_php, CGI), every response to this POST, mail or not,
  is held to a floor of 1.5 seconds, which covers a local `mail()` hand-off. What is left: a token row's insert (well
  under a millisecond), and on a server without either function, a transport slower than the floor. The per-requester
  limit (10 an hour) caps how many samples one requester can take.
- **No new token is made while one is live and fewer than 5 minutes old** for the same account (the earlier mail
  stands). After that, a new request replaces any live recovery token for the account (the earlier one is marked used),
  so at most one recovery link per account works at a time.

### 3.2 Tokens

One table holds every token (section 7): `recover`, `verify` (sign-up and Preferences), `email` (email change) and
`mailtest`.

| Property | Rule |
|---|---|
| Entropy | 32 bytes from `random_bytes()`, written base64url without padding: 43 characters. |
| Stored | Only its SHA-256 (hex, 64 characters), unique. The token itself exists only in the mail and in the request that opens it. A slow hash is not needed for 256 random bits (as `webcron.php`'s secret). |
| Looked up | By its hash (an indexed equality), after a format check (43 characters of the base64url alphabet); anything else is refused without a query. |
| Bound to | The account (`acctid`), the purpose, the address the mail went to, and the account's `authversion` when the token was made. |
| Expiry | `recover` 30 minutes; `verify`, `email` and `mailtest` 24 hours. |
| Single use | Used up only by the final POST that acts on it (3.4), in the same transaction as the change. Opening the link does not use it up (3.3). |
| Opens | At most 5 openings per token; after that it is refused. |
| Killed by | Use; expiry; a newer recovery token for the account (recover only); any change of `authversion`: a password change by any path, a sign-in, a sign-out, an admin reset. A sign-in kills a pending recovery link on purpose: whoever signed in had the password. |
| Cleaned up | Maintenance deletes tokens that are used or expired and older than 7 days. |

Binding to `authversion` is what the brief asks for and it covers every password change, because every path that sets
a password raises it. The cost: a player who asks for a verification or change link, then signs out before opening it,
has to ask again. The pages say so.

### 3.3 The token in the URL, and how it is contained

The link is `<serverurl>account.php?op=open&t=<token>`. A link has to carry the token, so the design keeps the token
out of every other place and makes the URL useless soon:

1. **Exchanged on first open.** `account.php?op=open` checks the token (format, hash lookup, unused, unexpired,
   `authversion` still matching, fewer than 5 opens), counts one open, rotates the session id, stores a short grant in
   the session (purpose, token id, the token's hash, expiry 15 minutes), and answers **303 to a clean URL**
   (`account.php?op=reset`, or `op=confirm` for the other purposes) with no body. The token is never written into a
   page, a form or the session; the grant holds only its hash and id. A refused token gets one fixed page ("This link
   has expired or was already used. Ask for a new one.") with a 400, the same for every reason.
2. **Link scanners.** Mail providers often open links before the player does. Opening does not use the token up and
   the change needs a POST from the page, so a scanner's visit makes a grant in the scanner's own session and nothing
   else. The 5-open cap stops a token from being opened without end.
3. **Referer.** Both the 303 and every `account.php` page send `Referrer-Policy: no-referrer`. The clean page has no
   outside resource (the enforced CSP allows only `'self'`), so nothing on it could receive a Referer anyway.
4. **The game's own records of URLs.** `common.php` stores the request URI in two places: the referer tracking table
   (`referers.dest`, for visits from an outside `http://` page) and, for a signed-in player, `restorepage`. The open
   request defines a constant before `common.php` that skips both (as `RESURRECTION_WEBCRON` skips referer tracking).
   No `account.php` page is kept in `accounts_output` (`NO_SAVE_USER` or the secret-page rule), and none prints the
   token, so `KeptPage` has nothing to keep.
5. **Caches and history.** Every `account.php` response sends `Cache-Control: no-store`. The browser's history holds
   the token URL; after use, expiry or a sign-in it is dead (3.2).
6. **Logs.** The game never logs the token or its hash: game log and debug log lines name the purpose and the account
   id only, and the PHP error log gets fixed lines. Parameters that carry it are `#[\SensitiveParameter]`. The web
   server's access log records the URL with the token; the game cannot stop that. The host guide says so (section
   11), and the 30-minute life and single use limit what a log reader can do with it.
7. **A signed-in visitor.** If someone signed in as another account opens a recovery link, the reset page says to sign
   out first and does nothing. Verification and change links work whoever is signed in, because they act only on the
   account the token names.

Question 6 offers an alternative: the token after `#` in the link, read by a small script, so it never reaches the
server's access log.

### 3.4 The reset

`account.php?op=reset`, GET: with a live `recover` grant, a form with two password fields (12 to 72 bytes, as
everywhere) and CSRF; without one, the expired-link page.

POST, in one transaction:

1. CSRF valid; grant present, unexpired and of purpose `recover`; passwords match and pass `Passwords::hash()`.
2. Lock the token row (`FOR UPDATE`): unused, unexpired, and its hash equal to the grant's.
3. Lock the account row: `authversion` still equals the token's, `locked` still 0.
4. `UPDATE accounts SET password=?, authversion=authversion+1, loggedin=0 WHERE acctid=? AND authversion=?`; exactly
   one row, else roll back.
5. Mark every live recovery token of the account used.
6. Commit.

Then: rotate the session id and clear the grant; write a game log line (`account mail`, "password reset by email",
the account id); show "Your password was changed. Sign in with it now." with a link to the sign-in page; and send the
**notice mail** after the response: "The password for <login> at <serverurl> was changed by a recovery link at
<time UTC>. If you did not do this, ask for a new link or contact the game's administrator." No IP, no link with a
token. Every other session of the account ends on its next request (`authversion`), and the "online" marker is cleared.

The player is not signed in by the reset: the sign-in page is the one place that starts a session.

**Two resets at once** (two tabs, or the player and someone with a leaked link): both lock the token row in step 2; the
first commits and marks it used; the second finds it used and gets the expired-link page. Step 4's `authversion`
condition refuses anything that slipped past.

### 3.5 Accounts in particular states

None of these is visible to the requester: every one gets the response of 3.1.

| Account | What happens |
|---|---|
| No email address | No mail. Its owner uses Petition for Help; an administrator resets in the user editor. The sign-up page says that without an address a forgotten password can only be reset by an administrator. |
| Address not verified | No mail (question 2). Preferences offers "Verify my address" to a signed-in player. |
| Pending sign-up verification | No mail. The sign-in page offers "send the confirmation link again" after a correct password (4.1). |
| Locked (`locked=1`) or banned | No mail, nothing recorded beyond the log row. |
| Staff account (any Grotto flag) | As question 3 decides. Recommended: allowed, with a game mail to every other `SU_MEGAUSER` account after a reset and a switch to exclude staff accounts. |
| In the middle of a Dragon kill, a fight or the new-day questions | Allowed. The reset changes only `password`, `authversion` and `loggedin`, under the account row lock (`FOR UPDATE`) the game's own account transactions take, so it waits for a transaction in progress and never interleaves with one. A request already running finishes; `saveuser()` never writes `password`, and it writes `authversion` only when the session changed it, which no page does. The next request is signed out. After signing in, the player resumes as after any sign-in (the kept page through `badnav.php`, the new day if one is due). |
| `NO_ACCOUNT_EXPIRATION` or old accounts | No difference. |

### 3.6 The administrator's manual reset

Unchanged: `SU_EDIT_USERS` sets a password in the user editor, `authversion` is raised, and a `debuglog` line is
written. It stays the fallback for every account recovery cannot serve (section 6). It also kills any live token of the
account, because it raises `authversion`.

## 4. Email verification

An account is **verified for an address** when a new column, `accounts.emailverified`, holds exactly the current
`emailaddress` (not empty). Storing the verified address, rather than a flag, means any path that changes
`emailaddress` (the user editor, a module) leaves the account unverified without needing to clear anything. The
column is added to the Dragon kill's `$nochange` list (`lib/dragon_outcomes.php`), as `emailaddress` and
`emailvalidation` are.

### 4.1 At sign-up, when `requirevalidemail` is on

- The address is required and validated (2.4), and `blockdupeemail` is checked as now.
- The account is made with `emailvalidation` set to a fixed marker, `rz-pending` (never a token; the column and its
  meaning, "sign-in blocked until verified", are 1.1.2's). A `verify` token (24 hours) is made and the mail is sent
  after the response. The page says: "Check your inbox for a link to confirm your address. You can sign in once it is
  confirmed. Unconfirmed accounts are removed after N days." (N from `expiretrashacct`.)
- **What an unverified account can and cannot do (question 4).** Recommended, as 1.1.2: it cannot sign in. It holds its
  login name and nothing else: no session, no mail, no commentary, no character. `resurrection_authenticate()` already
  refuses a non-empty `emailvalidation`.
- **After a correct password**, the sign-in refusal for a pending account is "Confirm your email address first", with a
  button "Send the link again" (POST, CSRF, and a 10-minute session grant made by that correct sign-in, so only someone
  with the password can ask). A wrong password still gets the usual message, so the state shows only to someone who
  has the password.
- **Resending**: a new `verify` token replaces the earlier one; limits per section 5 (3 an hour, 6 a day per account).
- **Confirming**: the link's grant leads to a page "Confirm <address> for <login>" with a Confirm button (POST, so a
  link scanner never confirms). In one transaction: the token unused and live, the account's `emailaddress` still the
  token's address; set `emailvalidation=''` and `emailverified=emailaddress`; mark the token used. The page then says
  the player can sign in.
- **Cleanup**: under the existing rules. An unverified account has never signed in (`laston <= regdate`), so
  `expiretrashacct` (default 1 day) deletes it at the next maintenance after that many days, with the usual module
  hooks and game log line. With `expiretrashacct` at 0, unverified accounts stay until an administrator deletes them
  (left over: names can be held that way; the host guide says so).
- **The administrator's override**: the user editor's existing action that clears `emailvalidation` lets the account sign
  in; its address stays unverified until the player verifies it from Preferences.

With `requirevalidemail` off, sign-up is as now; the address (if any) starts unverified, and the player can verify it
from Preferences.

### 4.2 Verifying or changing the address of an existing account

In Preferences, while account mail is on:

- **Verify my address** (when unverified and not empty): POST, CSRF, an action token; sends a `verify` token to the
  current address. Limits as above.
- **Change my address**: needs the **current password** in the same form (as a password change does), a valid new
  address (2.4), and `blockdupeemail` checked against other accounts. Nothing changes yet. An `email` token (24 hours)
  goes to the **new** address, and a notice goes to the **old** address (if it was verified): "A change of the email
  address for <login> to another address was requested at <time UTC>. If this was not you, sign in and change your
  password; that cancels the change." The notice does not name the new address and carries no link. Confirming the
  link (the confirm page, POST) sets `emailaddress` and `emailverified` to the new address in one transaction, only if
  the account's `authversion` and address are still those the token was made with. A second notice then goes to the
  old address saying the change is done.
- The current behavior of writing `$session['user']['emailaddress']` from the form ends; `saveuser()` never carries an
  address change.
- While account mail is off: an address change takes effect at once, as today, but needs the current password (F2),
  is checked with the 2.4 validator and `blockdupeemail`, and leaves the account unverified.

### 4.3 Lifting the `requirevalidemail` lock

Recommended condition (question 5): **`requirevalidemail` is read as on only while account mail is effectively on**
(2.5), which needs a confirmed test mail for the current `serverurl` and sender. `resurrection_locked_settings()` gives
way to a conditional rule: Game Settings shows the setting locked with the reason ("Turn on account mail first") while
account mail is off, and allows it once account mail is on.

If mail stops working later, nothing locks players out (section 6):

- the administrator turns account mail off (or changing `serverurl` or the sender turns it off in effect);
- `requirevalidemail` then reads as off, so new sign-ups make ordinary accounts;
- a pending sign-up (`emailvalidation='rz-pending'`) can sign in while account mail is off: `resurrection_authenticate()`
  refuses the marker only while account mail is effectively on. Its address stays unverified.

The game does not turn account mail off by itself when the transport refuses mail: a refusal is counted and shown in
Game Settings and written to the game log, and the administrator decides. A transport that accepts mail and then loses
it cannot be seen from PHP at all.

## 5. Abuse limits

All counts come from one new table, `account_mail_log` (section 7), one row per request or mail: date, purpose,
account id, an address key (SHA-256 of the lower-cased address, so the log keeps no second copy of addresses), a
requester key (the IPv4 address, or the IPv6 /64 prefix) and a result (`sent`, `refused`, `limited`, `skipped`).
Maintenance deletes rows older than 30 days.

`faillog` is not used: it records failed sign-ins and setup failures, the setup page counts its rows for its own limit,
its `id` column is 32 characters, and the coming sign-in throttling will count it. Mixing successful mail into it would
make all of those wrong.

| Limit | Default | Counted on | When reached |
|---|---|---|---|
| Recovery requests per requester | 10 an hour | every recovery POST, matched or not | Same response, nothing sent |
| Link opens per requester | 30 an hour | every `op=open` request, valid or not | The expired-link page, whatever the token |
| Recovery mails per account | 3 an hour, 6 a day | recovery mails made | Same response, nothing sent |
| Verification mails per account (sign-up resend, Preferences) | 3 an hour, 6 a day | verify mails made | "Try again later" (the requester is the signed-in owner, or has the password) |
| Email change requests per account | 3 a day | change requests (each sends two mails) | "Try again later" |
| Mails per target address, every purpose and account | 5 a day | every account mail and notice | Recovery: same response; others: "Try again later" |
| Sign-ups that send mail, per requester | 5 an hour | sign-ups with `requirevalidemail` on | The account is not made; "Try again later" |
| Test mails per administrator | 5 an hour | test mails | "Try again later" |
| All account mail | 100 an hour (Game Settings, 10 to 1000, a bounded setting as `LOGINTIMEOUT` is) | every account mail | Nothing more is sent this hour; requests answer as for their own limit; one game log line per hour |

Notification mail (in-game mail, expiration warnings, petitions) keeps its own rules and is not counted, except that
question 7 limits it to verified addresses.

The per-address limit is what keeps the game from flooding an inbox: however many accounts point at an address and
however many requesters ask, it gets at most 5 account mails a day. The global limit protects the host's mail quota
(many shared hosts cap mail at 100 to 500 an hour) and its sending reputation.

Left over: requesters behind one address (a school, a carrier's NAT, a proxy that hides client addresses from PHP)
share the per-requester limit. `REMOTE_ADDR` is the only address used; `X-Forwarded-For` is not trusted.

## 6. Hosts where mail does not work

- Account mail is off by default and turns on only after a confirmed test mail (2.5). A host whose mail never works
  never turns it on, and the game is as today: no recovery link, no verification, `requirevalidemail` off, admin
  resets.
- While account mail is off, the sign-in page shows no "Forgot your password?" link, and `account.php?op=forgot` shows
  one line: "Password recovery by email is not available on this game. Use Petition for Help to ask an administrator to
  reset your password." Preferences shows the address without "Verify" and says the same. Links already mailed (for
  example while it was on) get the expired-link page; their tokens stay bound and expire.
- If mail stops working after it was on, the administrator turns account mail off (4.3); nothing locks a player out,
  pending sign-ups can sign in, and the admin reset in the user editor is the fallback for every account.
- Game Settings' status (2.5) shows refusals in the last 24 hours, and each refusal writes a fixed game log line, so a
  host sees the failure without reading the PHP error log.

## 7. Schema and upgrade

### 7.1 Changes

| Object | Change | Why |
|---|---|---|
| `accounts.emailverified` | New, `varchar(128) NOT NULL DEFAULT ''` | The address that was verified (section 4) |
| `accounts.emailvalidation` | Reused as is (`varchar(32)`, keyed): empty, or the fixed marker `rz-pending` | 1.1.2's "sign-in blocked until verified"; already refused by `resurrection_authenticate()` and cleared by the user editor |
| `account_tokens` | New table: `tokenid` int unsigned auto increment (primary key); `acctid` int unsigned; `purpose` varchar(16); `tokenhash` char(64) (unique); `address` varchar(128); `authversion` int unsigned; `created` datetime; `expires` datetime; `opened` tinyint unsigned default 0; `used` datetime NULL. Keys: `tokenhash` unique, (`acctid`, `purpose`), `expires` | Every token, hashed (3.2) |
| `account_mail_log` | New table: `eventid` int unsigned auto increment (primary key); `date` datetime; `purpose` varchar(16); `acctid` int unsigned default 0; `addresskey` char(64) default ''; `requesterkey` varchar(40) default ''; `result` varchar(16). Keys: `date`, (`purpose`, `acctid`, `date`), (`addresskey`, `date`), (`requesterkey`, `date`) | Limits and status (section 5) |
| Settings | `account_mail` (0), `account_mail_confirmed` (empty), `account_mail_hourly` (100, bounded 10 to 1000), `account_mail_staff` (question 3) | Section 2.5 |
| `faillog`, `bans`, `accounts_output` | Unchanged | — |

Settings are rows, not schema: `getsetting()` returns the default for a missing row, and the fresh install writes
the defaults (`installer_default_settings.php`), so a fresh install and an upgraded database read the same values.

Character deletion (`resurrection_char_delete()`) also deletes the account's `account_tokens` rows; `account_mail_log`
rows stay until their 30 days pass (they hold an account id and keys, no address).

### 7.2 The upgrade step

This would be the first real step: `upgrades/0001-account-mail.php`, written to the step rules (`upgrades/README.md`):

- It holds its own literal table and column definitions. It does not call a shared descriptor function, because a step
  on `main` never changes and a shared function might.
- Each change checks first: `SHOW TABLES LIKE` for each table before `CREATE TABLE`; `SHOW COLUMNS` before
  `ALTER TABLE ... ADD COLUMN emailverified`; `SHOW INDEX` before each key. A run stopped after any statement and run
  again finishes the rest and changes nothing twice.
- It changes no rows.
- The fresh install gets the same objects in `resurrection_fresh_schema()` (as `authversion` was added), in the same
  commit, and records the step as applied.

Question 8 asks whether to ship it as a step at all, since no release exists yet and no released database needs it.
Recommended: yes. It costs little, exercises the step machinery on a real step before the first release, and lets any
database installed from `main` (test servers) upgrade instead of being reinstalled.

## 8. Requests, pages and credits

- **Entry points.** One new page, `account.php` (`RESURRECTION_ENTRY` first; `EntryGuardTest` counts 65). Ops:
  `forgot` (GET form, POST request), `open` (GET with the token, 303), `reset` (GET form, POST), `confirm` (GET page,
  POST) for `verify`, `email` and `mailtest` grants, `resend` (POST, from the sign-in refusal). Each is a row in
  `docs/ROUTE-INVENTORY-20261022.md`'s tables, which `tests/tooling/test_route_inventory.py` requires. `create.php`'s
  `op=val` keeps its 410.
- **Changed pages.** `create.php` (verification at sign-up), `login.php` (the pending-account refusal and resend
  grant), `index.php` (the "Forgot your password?" link while account mail is on), `prefs.php` (verify, change,
  current password), `configuration.php` and its descriptors (the Account mail section and its POST ops).
- **POST and CSRF.** Every change is a POST with the session's CSRF token; the Game Settings and Preferences actions
  also take a one-use action token. GET never changes anything except the open count and the session grant in
  `op=open`.
- **Headers.** The enforced CSP with no inline script or style (every page the game sends already passes it; the
  inline content scan in the HTTP tests checks the new pages too); `Cache-Control: no-store` and `Referrer-Policy:
  no-referrer` on every `account.php` response; `X-Robots-Tag: noindex` there too.
- **Credits.** `account.php` loads `common.php` and renders through `page_header()` and `page_footer()`, so every page
  carries the template's copyright line, license block and the attribution line, or the safe frame with the same when
  the template fails its check. The 303 and the empty responses carry no page, as now.
- **Secrets out of pages, logs and errors.** No page prints a token, a password or a password hash; the open request
  defines the constant that skips referer tracking and `restorepage` (3.3); `account.php` pages are never kept in
  `accounts_output`; errors use fixed texts; the game log and debug log name a purpose and an account id only.
- **Module hooks.** No new hook. `process-create` and `check-create` still fire at sign-up as now; if the build adds a
  hook after all, `docs/MODULE-AUTHOR-GUIDE.md` changes in the same commit (its test fails otherwise).

## 9. Threat model

### Assets

| Asset | Where it lives |
|---|---|
| A1 Player accounts | `accounts` (password hash, `authversion`, address) |
| A2 Staff and administrator accounts | `accounts` with Grotto flags |
| A3 Tokens | The mail; the link's URL in the browser, history and the server's access log; the hash in `account_tokens` |
| A4 Email addresses and whether an account exists or has one | `accounts`; recovery responses |
| A5 Inboxes of players and of strangers | Outside the game |
| A6 The host's mail quota and sending reputation | The host's MTA |
| A7 Availability of sign-in for every player | — |

### Actors

| Actor | Can |
|---|---|
| R1 Anonymous visitor or bot | Request any URL, any number of times, with any headers |
| R2 A player | Sign in to their own account, change their own preferences |
| R3 Someone at a shared or unattended computer | Use a browser's open session and history |
| R4 Someone who reads a player's mailbox (a mail scanner, a compromised mailbox) | Read and open links |
| R5 Someone who reads the server's access logs or statistics | Read URLs |
| R6 The host, by mistake | Leave `serverurl` as `http://` or wrong, leave the sender default, have a broken MTA |

### Entry points

E1 `account.php?op=forgot` POST; E2 `account.php?op=open` GET; E3 `account.php?op=reset` and `op=confirm` POST; E4
`account.php?op=resend` POST; E5 Preferences (verify, change); E6 `create.php` (sign-up with verification); E7 Game
Settings (test mail, switch); E8 the mail itself.

### Misuse cases

| # | Misuse | Actor, entry | Control | Left over |
|---|---|---|---|---|
| T1 | Account enumeration: learn whether an account or address exists, has an address, or is locked, banned, staff or pending | R1, E1 | One response for every case (3.1): status, text, headers, cookies; equal database work; mail sent after the response or a 1.5-second floor; the per-requester limit counts every request | Login names are public in the game anyway (F4). On a server with neither `fastcgi_finish_request()` nor `litespeed_finish_request()`, a transport slower than the floor shows in timing; a token insert's sub-millisecond cost remains |
| T2 | Token guessing | R1, E2 | 256 random bits; hash lookup; refused tokens get one page; the per-requester limit applies to opens as well as requests | None known |
| T3 | Token leaking through the mail (scanners, forwarding) | R4, E8 | Opening does not use it, the change needs a POST from the page; 30-minute life for recovery; single use; dead after any sign-in or password change; the notice mail after a reset | Someone who reads the mailbox within 30 minutes can reset the password. Mailbox access is the recovery factor by definition; the notice tells the owner |
| T4 | Token leaking through logs | R5 | The game logs neither token nor hash (3.3); 30-minute life; single use; the host guide says access logs hold the URL | A log reader with the URL inside its 30 minutes, before the player uses it, can reset. Question 6 (b) removes this |
| T5 | Token leaking through Referer | R1 | `Referrer-Policy: no-referrer` on the 303 and the pages; the token URL answers with a 303 and no body, so no page with outside links is ever shown at that URL; CSP allows no outside resource | None known |
| T6 | Token leaking through history or a shared computer | R3 | Clean URL after the exchange; `no-store`; token dead after use, expiry or a sign-in; the grant lives 15 minutes in that browser's session only | An unused, unexpired link in history at a shared computer, within its 30 minutes. A player who opened it there and left without finishing leaves a 15-minute grant in that browser |
| T7 | Replay of a used token or a captured POST | R1, R4 | Used tokens refused; the token row is locked and marked in the change's transaction; CSRF per session; the grant names the token's hash | None known |
| T8 | A Host header used to poison a link | R1, E1, E6 | Links from `serverurl` only, never the request; `https://` required for account mail; F1 fixed so no mail builds a URL from the request | A host who saves a wrong `serverurl` sends wrong links; the test mail's confirmation would fail first, and account mail turns off in effect when `serverurl` changes |
| T9 | Mail header injection | R1, R2, E1, E5, E6 | Dot-atom address validator (2.4) when saved and when sent; fixed subjects; no player value in a header; CR and LF refused | None known |
| T10 | Inbox flooding through the game | R1, R2, E1, E4, E5, E6 | Per-address 5 a day across every account and purpose; per-account and per-requester limits; global 100 an hour; no mail to unverified addresses for recovery; no new token within 5 minutes | Up to 5 unwanted mails a day per address, each naming the game and saying "if this was not you, ignore it" |
| T11 | Account takeover through an email change | R3 (open session), R2 | The change needs the current password (F2 fixed); the new address must confirm; the old address is told when it is asked and when it is done; any sign-in or password change in between kills the link | Someone who already knows the password can change the address; they could change the password anyway |
| T12 | A race between two resets, or a reset and a sign-in | R1, R4, E3 | Token row and account row locked in one transaction; `authversion` condition on the update; every recovery token used up together | The loser gets the expired-link page |
| T13 | A token used after a password change | R4, R5 | Bound to `authversion`, raised by every password change, sign-in, sign-out and admin reset | None known |
| T14 | Administrator account takeover | R4, E1 | As question 3 decides; recommended: allowed with every control above, a game mail to every other megauser after a staff reset, a game log line, and a switch to exclude staff accounts | A staff member's mailbox is as strong as their game password; a host can exclude staff accounts |
| T15 | A host whose mail is broken | R6 | Account mail off by default and only on after a confirmed test mail; status and log of refusals; turning it off restores today's behavior and lets pending sign-ups in | A transport that accepts and then drops mail looks like success; players see no mail and use Petition for Help |
| T16 | Squatting names with sign-ups never verified | R1, E6 | Per-requester sign-up limit; the existing `expiretrashacct` rule deletes them | With `expiretrashacct` at 0 they stay until an admin deletes them |
| T17 | Session fixation through the link | R1, E2 | The session id rotates when the grant is stored, and again after the reset | None known |
| T18 | CSRF or clickjacking on the new pages | R1 | POST plus CSRF; SameSite=Lax; `form-action 'self'`; `X-Frame-Options` and `frame-ancestors 'self'` | None known |
| T19 | A verification link confirming an address the account no longer has | R4 | The confirm transaction checks the account's address and `authversion` still match the token | None known |
| T20 | Mail that discloses who asked | R1 | No IP or requester detail in any mail (unlike 1.1.2) | The owner cannot see who asked; the game log has the account id and time, the mail log the requester key |
| T21 | The mail log as a store of personal data | R5 | Addresses kept only as SHA-256 keys; rows deleted after 30 days | A key can be matched against a guessed address by someone who reads the database |

## 10. Test plan for the build tasks

Each build task's tests fail before its change and pass after it (shown in its evidence doc). Mail never leaves CI:

- **Mail capture.** The HTTP fixture server already runs with `sendmail_path` set to a capture program
  (`tests/tooling/test_web_application.py:72`). The new HTTP tests use the same mechanism with a capture program that
  writes each message to a file and **exits 0** (accepted), and a second one that exits 1 (refused). PHPUnit tests
  that need a real `mail()` call run PHP in a subprocess with `-d sendmail_path=<capture>`.
- **A guard.** `MailCaptureGuardTest` (PHPUnit) fails under `RESURRECTION_REQUIRE_PHP=1` (Modern core) unless the
  PHP running the suite has `sendmail_path` pointing at a capture or `/bin/false`; the workflow sets it with
  `setup-php`'s `ini-values`. So the in-process suite cannot send real mail even by mistake.
- **SMTP (only if chosen):** a Python fake SMTP server in the test, on 127.0.0.1, with a certificate made for the run.

### PHPUnit

| Test | Proves |
|---|---|
| `AccountMailAddressTest::testDotAtomOnly`, `::testRefusesCrLfNulQuotesCommasAndIpLiterals`, `::testRefusesOver128Bytes` | 2.4 address rules |
| `AccountMailMessageTest::testLinksComeFromServerUrlOnly` (a forged `HTTP_HOST`, `SERVER_NAME` and `X-Forwarded-Host` never appear), `::testHttpServerUrlTurnsAccountMailOff`, `::testFixedSubjectsAndHeaders`, `::testNoPlayerValueInHeaders` | 2.3, 2.4 |
| `AccountTokenTest::testTokenIs43Base64UrlCharactersFrom32Bytes`, `::testOnlyTheHashIsStored`, `::testExpiryPerPurpose`, `::testSingleUse`, `::testAuthversionChangeKillsIt`, `::testPurposeAndAddressAreBound`, `::testOpenLimit`, `::testNewRecoveryTokenReplacesTheOld` | 3.2 |
| `AccountRecoveryTest::testEligibilityMatrix` (no address, unverified, pending, locked, banned, staff in and out, over a limit, mail off: no token, no mail), `::testSeveralAccountsOnOneAddressGetOneMail`, `::testResetUpdatesPasswordAuthversionAndLoggedin`, `::testSecondResetFindsTheTokenUsed` | 3.1, 3.4, 3.5 |
| `AccountMailLimitTest` (one test per row of section 5's table, plus `::testRequestsAreCountedWhetherOrNotAnAccountMatched`) | Section 5 |
| `EmailVerificationTest::testPendingSignupCannotSignInWhileAccountMailIsOn`, `::testPendingSignupCanSignInWhileAccountMailIsOff`, `::testConfirmSetsEmailverified`, `::testAnyAddressChangeLeavesTheAccountUnverified`, `::testDragonKillKeepsEmailverified` | Section 4 |
| `EmailChangeTest::testNeedsCurrentPassword`, `::testNewAddressConfirmsBeforeItTakesEffect`, `::testOldAddressIsToldWithoutTheNewOne`, `::testSigningInBetweenKillsTheLink`, `::testBlockdupeemailOnChange` | 4.2, F2 |
| `AccountMailSwitchTest::testRefusedWithoutHttpsServerUrl`, `::testRefusedWithTheDefaultSender`, `::testRefusedWithoutAConfirmedTestMail`, `::testServerUrlChangeTurnsItOffInEffect` | 2.5 |
| `LockedSettingsTest` (changed as the design changes the rule, never weakened): `requirevalidemail` still reads `0` whatever the row holds while account mail is off (the existing assertions, kept), and reads the stored value only while account mail is effectively on (new) | 4.3 |
| `CharacterDeletionTest::testExpirationWarningLinkComesFromServerUrl` | F1 |
| `UpgradeTest::testAccountMailStepMatchesTheFreshInstall`, `::testAccountMailStepStoppedMidwayFinishesOnRerun` | 7.2 |
| `EntryGuardTest` (65 entry points), `SqlSweepTest`, `SensitiveParameterTest` (the new parameters), `SecurityHeadersTest` (the `no-referrer` and `no-store` for `account.php`) | Existing guards, extended |
| `MailCaptureGuardTest` | No real mail in CI |

### HTTP (a new file, `tests/tooling/test_account_mail_http.py`, assigned to a shard by the runner)

Kept to about ten tests, because each adds shard time:

| Test | Proves |
|---|---|
| `test_recovery_request_answers_the_same_for_every_account_state` | Byte-identical bodies (less the CSRF field), same status, headers and cookies, for: no match, no address, unverified, locked, banned, pending, staff excluded, limited, a real match; only the real match leaves a captured mail |
| `test_recovery_link_is_exchanged_for_a_clean_url` | 303 to `op=reset` with no body; `no-referrer`, `no-store`; the token absent from `referers`, `restorepage`, `accounts_output`, the game log, the debug log and the PHP error log |
| `test_reset_sets_the_password_ends_sessions_and_mails_a_notice` | A second signed-in session is signed out; the old password fails, the new one works; the notice is captured without a token or IP |
| `test_reset_token_is_single_use_expires_and_dies_on_sign_in` | Second use, a token past 30 minutes (clock moved and restored), a token after a sign-in: the expired-link page |
| `test_two_resets_at_once_one_wins` | Two servers, one token: one success, one expired-link page |
| `test_forged_host_never_reaches_a_mail` | A request with a forged `Host` and `X-Forwarded-Host`: every captured link starts with `serverurl` |
| `test_signup_verification_and_resend` | With `requirevalidemail` and account mail on: no sign-in before confirming, resend after a correct password only, confirm by POST, then sign-in works |
| `test_email_change_needs_password_and_confirmation` | As 4.2, with both notices captured |
| `test_limits_hold_and_do_not_reveal_accounts` | The per-address and per-requester limits, with responses unchanged |
| `test_account_mail_off_leaves_today_behavior` | No forgot link; the one-line page; sign-up without verification; pending accounts sign in |
| `test_test_mail_round_trip_turns_account_mail_on` | Test mail captured; confirm by POST; the switch refused before and allowed after; refused again after `serverurl` changes |

Every page these tests load goes through the existing inline content scan (no inline script or style) and the credit
checks the template tests run.

## 11. Host-facing steps (for `docs/HOST-GUIDE.md`)

Section 11 of the host guide ("Mail") gains:

1. **Before turning on account mail.** In Game Settings set Server URL to the game's `https://` address and Admin Email
   to a real address on the site's own domain (mail from another domain is often refused or put in spam). Add an email
   address to your own administrator account in Preferences.
2. **Send a test mail** from Game Settings, Account mail. The page says whether the server's mail transport accepted
   it. Open the mail and press Confirm on the page it links to. If it does not arrive within a few minutes, check the
   spam folder, then the panel's mail log; on a VPS, point PHP's `sendmail_path` at a working relay client.
3. **Turn account mail on.** Players then see "Forgot your password?", can verify their address in Preferences, and
   address changes are confirmed by mail. Then, if wanted, turn on "Require users to validate their email address".
4. **What players see when it is off**: no recovery link, and a line telling them to use Petition for Help. Reset
   passwords in the user editor as before.
5. **If mail stops working**: Game Settings shows refusals in the last 24 hours, and the game log has a line for each.
   Turn account mail off; new players can sign up without verification, and players waiting for a confirmation can
   sign in.
6. **Access logs** hold the link a player opens, with its token, for 30 minutes of use at most. Keep logs and
   statistics behind the panel login (as the guide already says for the setup token).
7. **Unconfirmed sign-ups** are removed by maintenance after `expiretrashacct` days (default 1). With it at 0 they
   stay until you delete them.
8. **If SMTP is chosen (question 1)**: the six `$SMTP_*` lines in the configuration file, what each means, that the
   password is never shown back, and that only `starttls` or `tls` is accepted.

The "Known limitations" note at the top and the release notes' known-issue entry for S11 are rewritten when the build
lands (13c).

## 12. Build split

Each task is shippable alone, green on both legs and Baseline, and leaves the game working for hosts that never turn
account mail on.

- **13a: Account mail foundation.** The schema (section 7) with upgrade step `0001-account-mail.php` and the fresh
  install's copy; the address validator and message builder (2.3, 2.4) behind `resurrection_send_email()`; the token
  and limit libraries (3.2, 5) with maintenance cleanup; Game Settings' Account mail section: status, test mail,
  confirmation through `account.php?op=open` and `op=confirm` (purpose `mailtest` only), the switch; the mail capture
  guard; F1 fixed. Players see nothing new; `requirevalidemail` stays locked. `SECURITY-STATUS.md` row "Mail and
  recovery" updated (IN PROGRESS), route inventory rows, `CHANGES-FROM-1.1.2.md`.
- **13b: Password recovery.** `account.php` `forgot`, `open` and `reset` for `recover`; the sign-in page link; the
  notices; staff handling (question 3); the HTTP tests for recovery. Visible only while account mail is on.
- **13c: Verification, email change, and the lock lifted.** Preferences verify and change (F2 fixed), sign-up
  verification with the pending marker, sign-in refusal and resend, notification mail to verified addresses
  (question 7, F3), the conditional `requirevalidemail` rule (4.3) replacing the hard lock, `HOST-GUIDE.md`, the
  release notes, `SECURITY-STATUS.md` (row and the hosting gate ticked), `ROADMAP.md` (S11 ticked).
- **If SMTP is chosen**: a task of its own between 13a and 13b (the client, the configuration lines, the fake server
  tests), so recovery is built on a finished transport.

13a is the largest. Splitting recovery from verification keeps each HTTP test set small and lets 13b ship recovery for
accounts that already have a verified address (the administrator's, from the test mail) before 13c lands.

## 13. Open questions for Jim

1. **Mail transport.**
   Options: (a) `mail()` only; (b) `mail()`, plus an in-house SMTP client (STARTTLS or implicit TLS, `AUTH`, time
   limits) configured in the configuration file.
   Recommendation: **(a)** for rc.1. The panel hosts rc.1 is written for have a working `mail()` that signs for the
   site's domain, a host with a shell can relay through `sendmail_path`, and an SMTP client adds protocol, TLS and a
   stored credential to a release whose blocker is recovery. The one transport function keeps (b) open for later.
2. **Which address recovery mails go to.**
   Options: (a) only an address verified for the account; (b) any valid address on the account.
   Recommendation: **(a)**. An unverified address may be mistyped or someone else's, and then a recovery link would
   hand the account to that inbox. Any player can verify from Preferences in one step once account mail is on.
3. **Staff and administrator accounts.**
   Options: (a) recovery allowed, with a game mail to every other `SU_MEGAUSER` after a staff reset, a game log line,
   and a Game Settings switch to exclude staff accounts (default: included); (b) staff accounts excluded by default,
   the switch to include them; (c) always excluded.
   Recommendation: **(a)**. A host without a shell has no other way back into a sole administrator account (there is
   no reset command), so excluding staff by default risks a locked game; the notices and the switch let a host with
   several administrators choose otherwise.
4. **What an unverified sign-up can do.**
   Options: (a) nothing: it cannot sign in until confirmed (1.1.2's rule); (b) sign in and play, with mail, commentary
   and other player-to-player features held back until confirmed.
   Recommendation: **(a)**. It reuses the refusal `resurrection_authenticate()` already has, adds no checks across
   the game's pages, and the existing `expiretrashacct` rule cleans up. (b) touches every page that lets a player reach
   another.
5. **When `requirevalidemail` can be turned on.**
   Options: (a) only while account mail is effectively on, which needs a test mail confirmed by its link for the
   current `serverurl` and sender; (b) after a test mail the transport accepted (no link); (c) an administrator's
   checkbox.
   Recommendation: **(a)**. Only the round trip proves that mail arrives and that its links reach the game over HTTPS;
   (b) proves only the hand-off, and (c) proves nothing.
6. **How the token travels in the link.**
   Options: (a) in the query string, exchanged on first open for a session grant and a 303 to a clean URL (3.3);
   (b) after `#`, read by a small script under `assets/ui/` and posted to the server, so it never reaches access logs
   or proxies, with a form to paste the code when scripts are off.
   Recommendation: **(a)**. It works in every browser and mail client without a script; the access-log exposure (T4)
   is limited to the 30-minute, single-use window, and the host guide keeps logs behind the panel login. (b) is the
   fallback if Jim wants the logs out of the picture.
7. **Notification mail (in-game mail, expiration warnings) and unverified addresses.**
   Options: (a) while account mail is on, notifications go only to verified addresses; while it is off, as today;
   (b) always as today.
   Recommendation: **(a)**. It closes F3 (a player pointing a stranger's inbox at the game) wherever the game can
   verify addresses, and changes nothing on hosts that never turn account mail on. Players with notifications on are
   asked to verify once. This is a change from 1.1.2's behavior and goes into `CHANGES-FROM-1.1.2.md`.
8. **Shipping the schema as an upgrade step.**
   Options: (a) `upgrades/0001-account-mail.php` plus the fresh install; (b) the fresh install only, since no release
   exists and no released database needs a step.
   Recommendation: **(a)**. It exercises the step rules on a real step before the first release, under
   `UpgradeTest`, and lets databases installed from `main` upgrade.
9. **The build split.**
   Options: (a) three tasks, 13a foundation, 13b recovery, 13c verification and the lock (section 12); (b) two tasks,
   foundation, then recovery and verification together.
   Recommendation: **(a)**. Each stays reviewable with a small HTTP test set, and each is green alone.

Defaults that are not questions unless Jim wants them to be: recovery links live 30 minutes and other links 24 hours;
5 opens per link; the limits in section 5; the 1.5-second floor; no automatic sign-in after a reset; no IP in any
mail.

## Acceptance (recorded in Phase 6 task 13a, 2026-11-12)

Task 12 is **accepted at `ae4bd3b`**:

- Modern core 43245774215: **SUCCESS**, 14/14 jobs, every job on `ubuntu-24.04`. PHP 8.4 / MariaDB 11.4 `http` shards
  11:52, 14:59, 16:02, 13:00, 13:31; PHP 8.5 / MySQL 8.4 `http` shards 16:49, 13:00, 12:00, 17:11, 15:38.
- Baseline integrity 43245774307: **SUCCESS**.

## Decisions (approved 2026-11-12)

Jim approved the design on 2026-11-12 with every recommendation, plus one addition.

1. `mail()` only for rc.1. No SMTP client.
2. Recovery mail goes only to an address verified for the account.
3. Staff accounts can recover, with a game mail to every other `SU_MEGAUSER` after a staff reset, a game log line, and
   a switch to exclude staff (default: included).
4. An unverified sign-up cannot sign in until confirmed.
5. `requirevalidemail` reads as on only while account mail is effectively on (a confirmed test mail for the current
   `serverurl` and sender).
6. The token travels in the query string. It is exchanged on first open for a session grant and a 303 to a clean URL.
7. While account mail is on, notification mail goes only to verified addresses. While it is off, as today.
8. The schema ships as upgrade step `0001` plus the fresh install.
9. The build splits into 13a, 13b and 13c, as in section 12.

**Addition (13c): the verify prompt.** While account mail is on, a signed-in player whose address is unverified sees a
one-line prompt to verify it, with a link to Preferences. The player can dismiss it. It is not shown while account mail
is off. It belongs to section 4.2, the test plan and the 13c scope, as follows:

- **Section 4.2, addition.** While account mail is effectively on, a signed-in player whose `emailaddress` is not
  empty and not verified (or empty, when `requireemail` is on) sees one line at the top of the page, "Your email
  address is not verified. Verify it in Preferences.", with a link to `prefs.php` and a Dismiss button (POST, CSRF).
  Dismissing hides it for that address: the dismissal is stored with the address it was made for, so a later change of
  address shows it again. It is never shown while account mail is off, and never on `account.php` or the operator
  pages.
- **Section 10, addition (13c).** PHPUnit `VerifyPromptTest::testShownOnlyWhileAccountMailIsOnAndTheAddressIsUnverified`,
  `::testDismissHidesItForThatAddressOnly`; HTTP: `test_signup_verification_and_resend` also checks the prompt after a
  Preferences address change, its link and its Dismiss POST, and `test_account_mail_off_leaves_today_behavior` checks it
  is absent.
- **Section 12, addition.** 13c's scope includes the prompt.
