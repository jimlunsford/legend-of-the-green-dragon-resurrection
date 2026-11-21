# Release notes: Legend of the Green Dragon - Resurrection 1.0.0

**Draft** (started in Phase 6 task 32, 2026-11-21). Task 33, the `1.0.0` version commit, finishes it: the date, what
that task changes, and the release itself. `1.0.0` is a full release, marked Latest; the five test builds before it
stay pre-releases. Each change is recorded in full in `docs/CHANGES-FROM-1.1.2.md`. Read with the rc.5 notes,
`docs/RELEASE-NOTES-1.0.0-rc.5.md`, and the notes of the builds before it: everything there still holds unless this
page says otherwise.

## What changed since 1.0.0-rc.5

Release polish (`docs/PHASE6-TASK31-20261120.md`):

- **A new front page for the project.** The README now says what Resurrection is and what Legend of the Green Dragon
  was, with screenshots of the village, a forest fight, the battle screen, the Green Dragon and the phone layout; what
  changed from 1.1.2 and where each change is recorded; how it was built; how to play it on your own computer and how
  to host it; and the licenses and credits.
- **Every guide brought up to date.** The host guide is for running the game on a web server and the local install
  guide for playing on your own computer, and each points to the other. The supported platforms page gives PHP's and
  the databases' support dates. The documents have an index, `docs/README.md`, which says which are guides you act on
  and which are the project's records.
- No game code changed in the polish.

Four small fixes found in the polish (`docs/PHASE6-TASK32-20261121.md`):

- **A failed escape reads as it should.** When you tried to run from a forest fight and failed, the message showed a
  stray "`` `\$ ``" in front of "You failed to flee your opponent!", in the ordinary text color. It now shows as it
  did in 1.1.2: centered, bold and red.
- **"1 use left".** The battle screen's Special Abilities note said "1 uses left" when one use was left. It now says
  "1 use left", and "2 uses left" and so on for more.
- **The setup page is honest about newer PHP.** The web setup page (`setup.php`) said "8.4 or 8.5 needed" but marked
  any PHP from 8.4 up as fine, PHP 8.6 too. PHP 8.4 and 8.5 still show as fine, and an older PHP still fails. A newer
  PHP now gets a warning, "untested; Resurrection supports PHP 8.4 and 8.5", and setup may go on: the game is tested
  only on 8.4 and 8.5.
- **Recorded, not changed: special abilities need skill as well as uses.** In 1.1.2 an ability could be used whenever
  enough uses were left that day, so a brand-new character (skill 0) could spend its one daily bonus use on its first
  ability. Since Resurrection's Phase 3, an ability of level 1, 2, 3 or 5 also needs at least that much skill, so a new
  character waits for its first skill point (from training, the Fairy or the Foil Wench). This was never written down
  as a change from 1.1.2; it now is, and it stays as it is for `1.0.0`, so the game's balance does not move at the last
  step.

## Upgrading

Follow the host guide's upgrade steps (`HOST-GUIDE.md`, section 9, "Upgrade"): back up the database and the
configuration file, upload the new version into a new folder, then run `php scripts/upgrade.php --backup-confirmed` or
the upgrade on `setup.php`. A fresh install records every step as applied.

- **From rc.5, rc.4, rc.3 or rc.2:** no upgrade step runs so far. Tasks 31 and 32 change no stored row; the upgrade
  finds the database current. (Task 33 confirms this for `1.0.0`.)
- **From rc.1:** two steps run, in order, as the rc.2 notes say ("Upgrading from rc.1"): `0002-signin-limits` and
  `0003-utf8-seed-text`.

## Known issues

Carried from rc.5, all still true:

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
