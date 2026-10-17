# Inn economy authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `d3578d7b526c4483a082560f72bb7777e83bf96e`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. Predecessor Modern core
`38727313235` and Baseline integrity `38727313229`: SUCCESS at that head.

Scope: the four inn actions the records cleanup left uncovered — room rental,
bartender bribe, bartender specialty switch and Dragon flee — in `inn.php`,
`lib/inn/inn_room.php`, `lib/inn/inn_bartender.php`, `lib/inn/inn_default.php`,
with route-local helpers in `lib/inn_economy.php` and one line in
`lib/forest_combat.php` where the Dragon encounter is displayed. Already accepted
and not reopened: PvP inn attack and bodyguards, drinks (`ale`), Lovers, Seth,
Dag, commentary and New Day. Before this change the four files were 1.1.2 except
the `ScalarState` read of `donationconfig`.

Every mutation is now authenticated, POST, CSRF-checked, bound to a one-use intent
over the relevant account state, and run inside `resurrection_player_mutation()`
(locked account row, stale check, rollback). GET only displays. Existing helpers
are reused: `resurrection_action_fields()`, `resurrection_consume_action()`,
`resurrection_player_mutation()`, `resurrection_specialty_choices()`.

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Room payment | GET `op=room&pay=1\|2` debited, set `boughtroomtoday`, location and `loggedin=0`, then logged out | The historical links open a confirmation; the protected POST does the same writes in one transaction, then ends the session and redirects to `index.php` (303) | GET mutation, replay, no CSRF |
| Bank payment | `pay=2` skipped the funds check: a forced URL drove `goldinbank` negative | Each path checks its own balance under the lock; an empty bank shows the historical "Aah, so that's how it is" refusal and offers no form | Negative bank balances |
| Room prices | `expense = round(level*(10+ln level))`; bank adds the `innfee` setting (percent of expense, or flat) | Same formulas and display. The fee's leading number is parsed explicitly | PHP 8 raises on `"5%" * x`, so the room offer page was fatal (seen locally on PHP 8.5.11) |
| Already paid | Re-entry free, still logs out | Unchanged: free, logs out, from either path | Historical rule |
| Logout | `loggedin=0`, location = inn, `restorepage=inn.php?op=strolldown`, session array emptied | Same fields, plus `authversion+1` and the session destroyed, as `login.php` logs out | Ending a login the modern way; restore page and location unchanged |
| Bribe amount | `amt` taken from the URL: any value, negative values credited gems/gold, huge gold raised the chance past 100 | Only the six tiers offered for the current level (1/2/3 gems; level×10/50/100 gold). Anything else is 400 before any effect. The server derives the amount | Negative and off-tier bribes |
| Bribe type | Anything but `gem` was gold | Only `gem` or `gold`; others 400 | Unknown types |
| Bribe payment | GET paid immediately | GET shows the offer; protected POST pays inside the transaction | GET mutation, replay |
| Bribe chance and text | gems `amt*30`; gold `(amt/level-10)*50/90+25`; `e_rand(0,100) < chance`; success, wipe-counter and loss texts | Unchanged for every offered tier | Historical gameplay |
| Short purse | "You don't have N gems!/gold!", then "stands there staring at you blankly", nothing charged | **Unchanged.** The funds check now runs on the locked account and the text is identical | Decision: keep the 1.1.2 words; tiers are still offered regardless of funds, as before |
| Bribe success | Only drew nav links; `act=specialty` and `act=listupstairs` worked by direct URL | Success is server state bound to the account. The upstairs list and the specialty switch require it; the switch consumes it; any other inn page forfeits it (the answer only lasted that page in 1.1.2) | Unbribed direct access |
| `allowspecialswitch`, `pvp` | Checked only when drawing navs | Enforced on the services themselves (403) | Server-side settings |
| Specialty switch | GET `act=specialty&specialty=X` set `specialty` to any value | GET lists only installed, active bundled specialties (`resurrection_specialty_choices()`); protected POST rechecks them under lock and sets only `specialty` | Unchecked specialty bypassed the accepted specialty authority |
| Carry-over | Only `specialty` changed; each specialty's stored skill and uses stayed | Unchanged and proven: preferences are byte-identical after a switch. No onboarding hook runs, as before. Combat still validates the selection | Historical rule |
| Dragon flee | Any GET `op=fleedragon` set location to the village and cost a charm point, from anywhere, repeatedly | Only the encounter the Forest displays after its protected `op=dragon` (alive, level 15+, `seendragon=1`, no fight or event) may flee, once: the Forest records it, GET shows the choice, the protected POST moves to the village and costs the charm point, then the offer is gone | Repeatable GET mutation |
| Charm floor | `if (charm > 0) charm--` | Unchanged | Historical rule |
| Colors page | `<form action="$REQUEST_URI">` raw | Attribute-escaped; `testtext` output was already escaped | A request URI is attacker-influenced |

## Tests

Two HTTP tests:

- `test_inn_room_and_dragon_flee_authority_http` — anonymous access; GET-only
  room pages; forced `pay=2` with an empty bank (no form, then a stale bank form
  rejected, bank never negative); invalid `pay`; CSRF; unexpected field; stale
  gold; a CHECK-constraint failure on the account write after the debug log
  (500, nothing written, spent intent 409, fresh form succeeds with exact
  debits, inn location, logout and restore page); the old session after logout;
  free already-paid re-entry; bank payment with the 5% fee. Flee: refused without
  an encounter (GET and POST); the real Forest encounter; GET display only; CSRF;
  stale charm; one successful flee (village, charm 3 to 2); replay refused;
  charm floor 0.
- `test_inn_bartender_bribe_and_specialty_authority_http` — anonymous access;
  negative, off-tier and unknown bribes 400 on GET and POST; unknown `act`;
  CSRF; unbribed specialty/upstairs 403; short purse keeps the 1.1.2 text and
  costs nothing; seeded outcomes (fixture `inn-bribe`, `mt_srand(4)`): a 1-gem
  bribe fails and a 3-gem bribe answers, each paid once; replay 409; `pvp` and
  `allowspecialswitch` off 403; invalid, empty, array, extra-field and inactive
  specialties 400; stale specialty 409; a successful switch to Mystical Powers
  with stored preferences unchanged; the answer consumed; leaving the bartender
  forfeits an unused answer; the colors form escapes its request URI.

Both fail against the previous inn (room offer page fatal under PHP 8; negative
bribe accepted) and pass with the change. Local supporting evidence (PHP 8.5.11
setup-php build, MySQL 8.4.11) is recorded in the task report. Acceptance comes
only from CI on both supported legs.

## Remaining inn gaps

- `testtext` on the colors page is not typed; an array value raises a runtime
  error. It is display-only and changes no state.
- The colors page is still reachable without a bribe, as it is harmless display.
- A flat `innfee` with a fractional amount is truncated to whole gold; 1.1.2 would
  have written a fraction to an integer column.
- Inn events (`module_events("inn")`): no bundled module registers one, so none
  is certified here.

## Acceptance, 2026-10-15

`3b6d7800e294f811229605bc0b06032ba2003b02` is accepted. Modern core
**38736206216 SUCCESS**: both `result` jobs and every `checks` and `http` shard job
succeeded on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4. The longest job was
the MySQL `http (4)` shard at 17m08s. Baseline integrity **38736206145 SUCCESS**.
Conclusions come from the GitHub Actions run and job API; job logs were not
readable, so totals are not restated. The inn economy is Done in `ROADMAP.md`.
