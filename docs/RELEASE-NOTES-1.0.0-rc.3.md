# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.3

2026-11-16. Cut by `docs/RC-CUT-CHECKLIST.md` on the maintainer's go; the tag is `v1.0.0-rc.3`. The third test
build: what Jim's rc.2 server test found (R10). Each change is recorded in full in `docs/CHANGES-FROM-1.1.2.md`. Read
with the rc.2 notes, `docs/RELEASE-NOTES-1.0.0-rc.2.md`, and the rc.1 notes, `docs/RELEASE-NOTES-1.0.0-rc.1.md`:
everything there still holds unless this page says otherwise.

## What changed since 1.0.0-rc.2

Found in the rc.2 server test, 2026-11-16 (`docs/PHASE6-TASK22-20261116.md`).

- **R10: a refused action is no longer a dead end.** A player whose game day rolled over while they were in the
  forest pressed "Look for Something to Kill" and got a bare text page, "Expired or repeated action.", with no links;
  every other page brought the same form back, refused again. Only an administrator could leave, through the Grotto.
  - The cause: the search form never checked the new day. Its press used up the form's one-use action and then
    refused the search because the new day had not begun, and every later page showed that used form again. A search
    after the game day rolled over now begins the new day, as 1.1.2's search did, and a search pressed after the rollover is
    refused before anything is used up.
  - The dead end: a refused action on a game page (a used, stale or missing action, 409, or a form the session did not
    issue, 403) now answers in the game's frame, with the credits, the message "That action has expired. Nothing was
    changed." and a way out. After a 409 that is a link back to the village (or the Shades), which also becomes the
    page shown again on the next request and after the next sign-in; after a 403, a link back to the player's own
    page, and nothing is saved. The refused action still does nothing, and the same status codes are sent. Pages
    outside the game's navigation (mail, the MoTD, petitions, the translator, account links, the new day's questions)
    keep their plain refusals.
  - **The search link on its own, also seen on Jim's rc.2 server** (2026-11-16): after a game-day rollover, the link
    itself, `forest.php?op=search&c=...`, answered the plain "Forest combat unavailable. Stored state was preserved;
    reload for a fresh form, or request operator repair if it remains invalid.", and going to the village started the
    new day and cleared it. The link answers that only for a player the forest cannot take as they are: dead, with no
    hitpoints, or in a fight. The rollover check now comes before that check, as 1.1.2's search began with the new
    day, so for them too the link begins the new day, and after it the forest works (Phase 6 task 23,
    `docs/PHASE6-TASK23-RC3-VERSION-20261117.md`).

## Upgrading

Follow the host guide's upgrade steps (`HOST-GUIDE.md`, section 9, "Upgrade"): back up the database and the
configuration file, upload the new version into a new folder, then run `php scripts/upgrade.php --backup-confirmed` or
the upgrade on `setup.php`. A fresh install records every step as applied.

- **From rc.2:** no upgrade step runs. rc.3 changes no stored row; the upgrade finds the database current.
- **From rc.1:** two steps run, in order, as the rc.2 notes say ("Upgrading from rc.1"): `0002-signin-limits` (the
  three sign-in limit settings, where the database has none) and `0003-utf8-seed-text` (the UTF-8 seed text, such as
  master 5's "Unélith", where it is still exactly what rc.1 wrote).

## Known issues

Carried from rc.2:

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
- **A 1.1.2 data oddity:** two creatures are both "Loneliness".
- **The Dark Horse Tavern's games need many clicks on Continue** between rounds (dice, stones, five-six). Recorded in
  `ROADMAP.md` as an item for after `1.0.0`.
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
