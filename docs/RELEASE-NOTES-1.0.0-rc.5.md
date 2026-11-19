# Release notes: Legend of the Green Dragon - Resurrection 1.0.0-rc.5

2026-11-19. Cut by `docs/RC-CUT-CHECKLIST.md` on the maintainer's go; the tag is `v1.0.0-rc.5`. The fifth test
build: what Jim's rc.4 server test found (R12 and R13), and no dead ends, parts 1 and 2. Each change is recorded in
full in `docs/CHANGES-FROM-1.1.2.md`. Read with the rc.4 notes, `docs/RELEASE-NOTES-1.0.0-rc.4.md`, the rc.3 notes,
`docs/RELEASE-NOTES-1.0.0-rc.3.md`, the rc.2 notes, `docs/RELEASE-NOTES-1.0.0-rc.2.md`, and the rc.1 notes,
`docs/RELEASE-NOTES-1.0.0-rc.1.md`: everything there still holds unless this page says otherwise.

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

No dead ends, part 1 (`docs/PHASE6-TASK27-20261118.md`):

- **A refused request no longer leaves a page with nothing to press.** Before, many pages answered an old link, a form
  pressed twice or a choice that was no longer there with a bare line of text and no links. Now every page of the game
  itself answers in the game's frame, with the same message and one link onward: back to the fight while one is going
  on, else back to the page the request came from (the bank, a shop, the inn, the clan list, the new day, your
  preferences, and so on), else the village, or the Shades for the dead. That link is also where the next sign-in
  brings you back. This covers the bank, the inn and its bartender, the clan halls, the talk on every page, the Green
  Dragon and Dragon Points, the weapon and armor shops, the forest's events, the Dark Horse Tavern's door, the
  graveyard and the Mausoleum, the gypsy, the healer, the stables, the new day and its race and specialty choices, the
  preferences, player fights, Ramius, the Hall of Fame, the warrior list and the bios.
- **Refusals say what happened.** A refusal now says what did not happen and that nothing was changed, for example
  "Training unavailable. Nothing was changed.", instead of asking you to reload, to ask for a fresh form or to have an
  administrator repair your character.
- **A refused fight round shows nothing of the round.** When the game could not save a round of a fight (the Green
  Dragon's, the graveyard's, a player fight's), the refusal showed the round above its message as if it had happened.
  Now it shows only the message and its link.
- **The forest shows your fight.** Opening the forest while a forest fight is waiting (from the village, for example)
  now shows that fight, ready to go on, instead of the forest's menu. Nothing is used up. With no fight waiting, the
  forest is as before.

No dead ends, part 2 (`docs/PHASE6-TASK28-20261119.md`):

- **The bundled modules refuse in the game's frame too.** The Dark Horse Tavern and its three games, Cedrik's
  potions, the drinks at the bar, the lovers, Seth's songs, Crazy Audrey, Dag's bounties and the outhouse answered an
  old link, a form pressed twice or a choice that was no longer there with a bare line of text and no links. Now each
  answers in the game's frame with one link onward: back to the game you were playing, Cedrik's potions, the bard,
  the bartender, Dag's table or the Main Room of the tavern, else the inn, the village or the forest where the module
  lives, or the Shades for the dead. Their messages say what did not happen and that nothing was changed; none asks
  you to request a fresh form or go back and try again.
- **Fewer clicks in the Dark Horse Tavern's games.** A game of stones took one press more than 1.1.2's: the bet drew
  no stones, so a press of Continue came before the first pair. Now the bet draws the first pair, as it did in 1.1.2,
  and every press after it draws one pair, until the last press settles the game. Dice (Bet, Pass, Keep) and five-six
  (one Roll a round) already took no more presses than 1.1.2's, and each game's last page shows the result together
  with the next round's Bet, Roll or choice of pair, where 1.1.2 needed a Play again first.
- **Names show as written.** The character panel, the Hall of Fame and the talk on every page showed a player's name
  as page code, so a name holding something like `<img>` showed a broken picture there. They show the name as text now,
  with its colors, like the rest of the game. No player could choose such a name: sign-up takes letters and digits.
- **A new day with no race or specialty goes on.** When a game has every race (or every specialty) turned off, the
  new day's question had nothing to press. Now it does what 1.1.2 did: it makes you Human (or a student of the
  mystical powers) and goes on with one Continue. The host guide tells the host how to give players the choice again.

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
- **The Dark Horse Tavern's door takes one press more than 1.1.2's, each way.** Riding in on a mount and leaving the
  tavern each show a page with one button to confirm, because the game changes your state only by a button, never by
  a link; 1.1.2 did both with a link.
- **Refusals on the staff tools and on pages outside the game itself (the sign-in, mail, the MoTD, petitions) stay
  plain on purpose:** a line of text, as before.
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
