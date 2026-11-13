# Security

**NOT RELEASED. NOT CERTIFIED FOR PUBLIC GAME HOSTING.**

No Resurrection release has been cut. `main` is `1.0.0-rc.1`, the first test build: it is meant for hosts to try, with the limitations its [release notes](docs/RELEASE-NOTES-1.0.0-rc.1.md) list, and with the [host guide](docs/HOST-GUIDE.md)'s rules. It has had no independent security review, and the project's own public hosting gates ([ROADMAP.md](ROADMAP.md), "Public hosting") are not all met: `project.json` keeps `public_game_hosting_approved` at `false`.

This repository also keeps the vulnerable historical 1.1.2 source, unchanged, under the tag `historical-source-1.1.2`. Never run it on an Internet-facing server, including a temporary demo hostname.

Read [security status](docs/SECURITY-STATUS.md) and [lab safety](lab/historical-1.1.2/README.md). No historical audit finding is considered fixed by repository setup, containers or a successful HTTP response.

## Reporting

Do not post credentials, real player data or exploit payloads in public issues. Contact the maintainer through an established private channel for sensitive details. GitHub private vulnerability reporting is not asserted to be enabled in this foundation; verify its availability before relying on it. Public issues can track already-documented high-level remediation work without operational exploit details.

Contributions must distinguish desired game behavior from historical security defects. Security corrections are not required to reproduce vulnerable behavior.
