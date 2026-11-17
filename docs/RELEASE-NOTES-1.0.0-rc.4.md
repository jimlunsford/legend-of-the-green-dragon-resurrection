# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.4

2026-11-17. Cut by `docs/RC-CUT-CHECKLIST.md` on the maintainer's go; the tag is `v1.0.0-rc.4`. The fourth test
build: what Jim's rc.3 server test found (R11). Each change is recorded in full in `docs/CHANGES-FROM-1.1.2.md`. Read
with the rc.3 notes, `docs/RELEASE-NOTES-1.0.0-rc.3.md`, the rc.2 notes, `docs/RELEASE-NOTES-1.0.0-rc.2.md`, and the
rc.1 notes, `docs/RELEASE-NOTES-1.0.0-rc.1.md`: everything there still holds unless this page says otherwise.

## What changed since 1.0.0-rc.3

Found in the rc.3 server test, 2026-11-17 (`docs/PHASE6-TASK24-20261117.md`).

- **R11: the new day's Continue no longer leads to a dead end in the forest.** On Jim's server, a level 2 character
  with 10 forest fights pressed the new day's Continue and got a bare text page, "Forest combat unavailable. Stored
  state was preserved; reload for a fresh form, or request operator repair if it remains invalid.", with no links.
  - The cause: he was in a fight when the game day rolled over. The forest's search link began the new day, as rc.3
    made it, and stayed the page to come back to, so Continue went to it; and a search while a fight is stored was
    refused, as plain text.
  - Now a search link while a fight is stored shows that fight, with nothing used up or reset, and the fight goes on
    to its end. A search pressed while a fight is stored is refused in the game's frame, "That action has expired.
    Nothing was changed.", with one link back to the fight. 1.1.2 let a new search replace the fight, which made any
    search a way out of a fight; the fight stays the player's.
  - A search with no forest fights left shows 1.1.2's own words again, "You are too tired to search the forest any
    longer today. Perhaps tomorrow you will have more energy.", in the forest with its links, and uses nothing up.
    In rc.3 the link showed a search form whose press was refused as plain text.
  - The forest's other refusals, and the specialty abilities' in a forest fight, keep their status codes and messages
    but answer in the game's frame, with one way out: back to the fight while one is stored, else the forest, else the
    village (or the Shades for the dead). That way out is also the page shown after the next sign-in.

## Upgrading

Follow the host guide's upgrade steps (`HOST-GUIDE.md`, section 9, "Upgrade"): back up the database and the
configuration file, upload the new version into a new folder, then run `php scripts/upgrade.php --backup-confirmed` or
the upgrade on `setup.php`. A fresh install records every step as applied.

- **From rc.3 or rc.2:** no upgrade step runs. rc.4 changes no stored row; the upgrade finds the database current.
- **From rc.1:** two steps run, in order, as the rc.2 notes say ("Upgrading from rc.1"): `0002-signin-limits` and
  `0003-utf8-seed-text`.

## Known issues

Carried from rc.3:

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
- **The forest's own page shows the forest menu while a fight is waiting**, as 1.1.2's did, not the fight. Its search
  links lead straight back into that fight, so a player is never stuck. Recorded in `ROADMAP.md` as an item for after
  `1.0.0`.
- **Some pages still refuse with a bare line of text.** Outside the forest, a page that refuses a request (an old
  link, a form pressed twice, a missing choice) can still answer with a line of text and no links. The game's next
  page shows the player's last page again, which usually has a way on; R10 and R11 were cases where it did not, and
  both are fixed. Task 24 listed 211 such refusals by file and line
  (`docs/PHASE6-TASK24-20261117.md`, section 6). Recorded in `ROADMAP.md` as an item for after `1.0.0`.
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
