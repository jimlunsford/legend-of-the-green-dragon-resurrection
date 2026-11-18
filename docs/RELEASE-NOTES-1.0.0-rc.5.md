# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.5

Draft. This test build has not been cut. Started in Phase 6 task 26 (2026-11-18) from what Jim's rc.4 server test
found; each change is recorded in full in `docs/CHANGES-FROM-1.1.2.md`. Read with the rc.4 notes,
`docs/RELEASE-NOTES-1.0.0-rc.4.md`, the rc.3 notes, `docs/RELEASE-NOTES-1.0.0-rc.3.md`, the rc.2 notes,
`docs/RELEASE-NOTES-1.0.0-rc.2.md`, and the rc.1 notes, `docs/RELEASE-NOTES-1.0.0-rc.1.md`: everything there still
holds unless this page says otherwise.

## What changed since 1.0.0-rc.4

Found in the rc.4 server test, 2026-11-18 (`docs/PHASE6-TASK26-20261118.md`).

- **R12: the training yard no longer leads to a dead end after the day rolls over.** On Jim's server, the game day
  rolled over while he was at the training yard. After he signed in again, the yard showed its Challenge button, and
  pressing it gave a bare text page, "Training unavailable. Stored state was preserved. Reload for a fresh form, or
  request operator repair if state remains invalid.", with no links.
  - The cause: the training yard never started the new day, as 1.1.2's did. Signing in brings back the last page a
    player saw, so the yard came back from the old day, and Challenge refused the new day.
  - Now every page of the training yard starts a due new day first, and so does signing in when the page it brings
    back is one that would: the new day comes up, and its Continue goes back to the yard, where Challenge starts the
    fight. Nothing is used up on the way.
  - A Challenge pressed on a yard page loaded before the day rolled over is refused in the game's frame, with one link
    that leads to the new day. Nothing is used up, and the day's challenge is still there.
  - A fight with the master that was going on when the day rolled over is kept: after the new day it is shown as it
    was and plays to its end. It counts as the new day's challenge, so after a loss there is no second challenge that
    day; after a win, the next master is open as before. (1.1.2 kept the fight too, but its new day also opened a
    second challenge.)
  - The yard's other refusals keep their status codes and messages but answer in the game's frame, with one way out:
    back to the fight while one is going on, else the yard, else the village (or the Shades for the dead). That way out
    is also the page shown after the next sign-in.
  - The same check found two other pages that refused before starting the new day, and they start it first now: the
    graveyard (a dead player's search for something to torment) and the flight from the Dragon to the inn. After the
    new day there is no Dragon to flee, so that page now says "There is no dragon to run from." in the game's frame,
    with the way into the inn.
- **R13: a special ability in a forest fight no longer leads to a page with nothing to press.** On Jim's server, Dark
  Arts' Skeleton Crew pressed on the battle screen led to a page with only Continue, and Continue to a page with only
  the abilities that had uses left; after the day's last use there was nothing to press at all.
  - Now the page after an ability is the fight, as after an ordinary round: the enemies, Fight and Run, the abilities
    with uses left, and the automatic fighting buttons where the game allows them. When the round ends the fight, the
    page is the fight's ending with its links. This holds for Dark Arts, Mystical Powers and Thieving Skills alike.
  - An old link to the abilities page shows the fight while one is going on, or the forest, and uses nothing up.
  - The Dragon's fight already showed Fight and the abilities after an ability (and, as in 1.1.2, no Run from the
    Dragon). Player fights, the training yard and the graveyard offer no abilities.

## Upgrading

Follow the host guide's upgrade steps (`HOST-GUIDE.md`, section 9, "Upgrade"): back up the database and the
configuration file, upload the new version into a new folder, then run `php scripts/upgrade.php --backup-confirmed` or
the upgrade on `setup.php`. A fresh install records every step as applied.

- **From rc.4, rc.3 or rc.2:** no upgrade step runs. rc.5 changes no stored row; the upgrade finds the database current.
- **From rc.1:** two steps run, in order, as the rc.2 notes say ("Upgrading from rc.1"): `0002-signin-limits` and
  `0003-utf8-seed-text`.

## Known issues

Carried from rc.4:

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
- **Some pages still refuse with a bare line of text.** Outside the forest, the training yard and the inn's flight
  from the Dragon, a page that refuses a request (an old link, a form pressed twice, a missing choice) can still answer
  with a line of text and no links. The game's next page shows the player's last page again, which usually has a way
  on; R10, R11 and R12 were cases where it did not, and all three are fixed. Task 24 listed 211 such refusals by file
  and line (`docs/PHASE6-TASK24-20261117.md`, section 6); task 26 framed training's and the inn's. Recorded in
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
