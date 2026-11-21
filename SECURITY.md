# Security

**NOT CERTIFIED FOR PUBLIC GAME HOSTING.** Resurrection has had no independent security review, and the project's
public hosting gates are not all met (below).

## Report a vulnerability

Report it privately. Do not put its details in a public issue, a pull request or a comment.

1. Use GitHub's private vulnerability reporting: on this repository's **Security** tab, **Report a vulnerability**.
   Only the maintainer sees the report.
2. If the tab has no such button, open an issue titled "Security contact" that says only that you have a report and
   asks for a private way to send it. Put no details of the problem in it.

Say which version you tested (every page's footer shows it), what you did, what happened and what you expected. Test
only on a game you run yourself: [local play](docs/MODERN-INSTALLATION.md#play-locally) sets one up on your own
computer. Never include a real password, real player data or anyone else's information.

`1.0.0` was released on 2026-11-21, and the project now takes bug fixes only. Fixes come as bug-fix releases
(`1.0.1` and on).

## Scope

In scope: the code on `main` and in the latest release (today `1.0.0`). That is the game's pages, its libraries and the
24 bundled modules; the install and upgrade commands and `setup.php`; web maintenance (`webcron.php`); account mail;
and the release package.

Out of scope:

- **The historical 1.1.2 source.** The tag `historical-source-1.1.2` keeps it unchanged on purpose, with known
  high-severity defects and unsupported runtime assumptions. Never run it on a server the Internet can reach, even for a
  short demo.
- **A host's own server**: its TLS, proxy, file permissions, database user, backups and monitoring. The
  [host guide](docs/HOST-GUIDE.md) says what to do; the gates below that the code does not meet belong to whoever
  runs a public server.
- **Modules that are not bundled.** Only the 24 bundled modules install
  ([docs/MODULE-POLICY.md](docs/MODULE-POLICY.md)).
- **What is already recorded as open**: the unticked gates below, and the "Left over" column of the security status
  record.

## Public hosting status

Each security control, its evidence in the tests and what is left over are in
[docs/SECURITY-STATUS.md](docs/SECURITY-STATUS.md), with the public hosting gates as a checklist. Done: every state
change is a POST with CSRF and authorized on the server, every request value is bound in SQL, no `eval()`, an
enforced Content Security Policy, the internal files refused, account recovery and email verification by mail, and
sign-in throttling. Open: abuse controls beyond sign-in, production TLS and session settings checked on a real host,
isolation and least privilege on that host, a tested restore, monitoring, a supervised scheduler and an independent
security review. `project.json` keeps `public_game_hosting_approved` at `false`.
