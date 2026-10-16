# Stables authority, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `639b8c50f52ae3f8874ab76a810d28b4c4e58257`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Predecessor runs, all SUCCESS:

| Candidate | Commit | Modern core | Baseline integrity |
|---|---|---|---|
| Weapons and armor | `639b8c5` | 38810195085 | 38810195222 |
| Bank | `52bfc4f` | 38802851227 | 38802851288 |

The bank run finished before `639b8c5` was pushed, so it was not cancelled.

Scope: `stables.php`, which covers examine, buy or trade, feed and sell. Route-local
helpers live in `lib/stables_authority.php`. Before this change `stables.php` was 1.1.2
except for the ScalarState reads of `mounts.mountbuff`. The mount editor and the general
mount and buff schemas are not opened.

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4.11 against `639b8c5`. The player
had 2 dragon kills and was in the main village. The fixture mounts were: one offered;
one inactive; one stabled `Elsewhere`; one with `mountdkcost` 5; one with a malformed
buff. Each cost 100 gold and 2 gems.

| Finding | Observed |
|---|---|
| Listing | Examine links only for the active, here-or-everywhere, affordable-by-dragon-kills mounts (the rule lives only in the listing query) |
| Inactive or out-of-location mount by typed URL | **Disproven as a direct URL.** `confirmbuy` for the inactive mount redirected to `badnav.php`; forced navigation only accepts issued links |
| Purchase handler checks | **None.** Once the URL was an allowed navigation, the player bought the inactive mount (GET `buymount` with no mount bought at once), then the out-of-location mount, then the one above the dragon-kill cost |
| Replay | Repeating a `confirmbuy` for the same mount bought it again, losing a third of its price each time (33 gold and 1 gem here). A GET buy URL becomes the restore page, which New Day's "Continue" link follows |
| Sell | **Always fatal on PHP 8.** The success text reads `$playermount['newname']`, a key no bundled module sets. The 1.1.2 notice is now an error after the sale was logged, so the sale was never saved |
| Sell without a mount | Fatal (`$playermount['mountname']` on an empty mount), after a debug-log row |
| Feed | The success text reads `$playermount['basename']`, fatal the same way after the feeding was logged. Feeding with no mount is fatal. The handler never checked `fedmount`: once a day was enforced only by hiding the link |
| Malformed stored buff | Fatal after the purchase was logged (`$buff['schema']` on `false`); the charge was not saved, the debug log was |
| Hooks | `stabletext` (Human race module's Bertold texts), `stables-nav`, `stables-desc`, `stable-mount`, `boughtmount`, `soldmount`; `stablelocs` belongs to the mount editor |

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Buy with no mount | GET `buymount` bought at once | GET `buymount` shows the creature and cost lines from examine and a "Buy this creature" form; the purchase is its POST | GET mutation |
| Trade with a mount | GET `buymount` asked; "Yes" was a GET link to `confirmbuy` | Same question; "Yes" is the protected POST | Same |
| Protection | None | CSRF plus a one-use intent per action (`stables-confirmbuy`, `stables-feed`, `stables-confirmsell`). The intent is bound to the mount ID, the current mount, gold, gems, location, dragon kills, level, `fedmount` and the mount buff's rounds | Replay, stale forms |
| Offer | Listing only: active, `mountlocation` `all` or here, `mountdkcost ≤ dragonkills` | The same rule, also for examine, the buy question and under lock at purchase; otherwise the historical "no such beastie" text and no change | Rule was navigation-only |
| Funds and trade-in | `gold + round(cost_old × 2/3) ≥ cost`, same for gems | Same formulas on the locked account; refusal text unchanged | Unchanged |
| Purchase effects | Session changes and debug log, then buff, `stable-mount`, `boughtmount` | Same order inside the locked player transaction, so a failure rolls back purse, mount, buff, log and hook writes | Atomic |
| Feed | GET; `allowfeed`; full price `round(level × feedcost)`; half when the buff has more than half its rounds; "not hungry" (no charge) when full; sets `fedmount` | POST; same settings, prices, half-price rule and texts. The player must own a mount, and `fedmount` (once a day, as the "'til the morrow" text says) is enforced by the server, not just the hidden link | Nav-only limits |
| Sell | GET `confirmsell`; no mount check | POST; the player must own a mount; 2/3 back; buff stripped; `soldmount` | Selling nothing |
| Mount buff | `ScalarState::read` used unchecked | Read once; anything but an array stops the action before any change (500, nothing written) | Fails safely |
| Missing `newname` / `basename` | Notices on PHP 4/5 (falsy) | Treated as empty, so the mount's name is used as 1.1.2 effectively did | PHP 8 runtime defect |
| SQL | Mount ID pasted into cached queries; the listing pasted the location | Bound | Binding |
| Messages, prices, settings, hooks | — | Unchanged | Historical |

No mount rows are written, so there is no `mountdata-<id>` cache to invalidate. Account
state is saved by the player transaction.

## Tests

`test_stables_buy_feed_and_sell_authority_http`:

- Access and display:
  - Anonymous requests are refused.
  - The listing offers exactly the three offered fixture mounts.
  - Examining the inactive, out-of-location or too-costly mount shows "no such beastie" with no buy link.
  - The offered mount's name is escaped.
  - GET of `buymount`, `confirmbuy`, `feed` and `confirmsell` changes nothing.
- Request refusals:
  - CSRF, missing or wrong, is 403.
  - Malformed, zero and missing IDs and an extra field are 400.
  - Unknown, inactive, elsewhere and too-costly IDs offer no form; a forged POST is 409.
- Rechecks under lock:
  - A mount withdrawn after its form was issued is not sold.
  - Too little gold shows the historical refusal.
  - A stale purse is 409.
  - A malformed buff is 500 with nothing charged or logged.
- Rollback: a CHECK-constraint failure on the account write rolls back purse, mount, buff and log (500). The spent intent is 409. A fresh form buys: gold 1000 → 900, gems 10 → 8, buff rounds 20, one log row. Replay is 409.
- Trade-in counts toward gems: a 2-gem trade with 0 gems and a 1-gem trade-in is refused; with 8 gems, gold 900 + 67 − 300 = 667 and gems 8 + 1 − 2 = 7.
- Feeding:
  - Disabled feeding shows the refusal and offers no form.
  - Not hungry: no charge.
  - Half price round(25 / 2) = 13 at 15 of 20 rounds, buff restored to 20 and `fedmount` set; replay 409.
  - No form while fed.
  - Full price 25.
  - Too little gold: no change.
- Selling: round(300 × 2/3) = 200 gold and 1 gem back, buff removed. Replay is 409. With no mount there is no sell or feed form, and forged POSTs are 409.

It fails against `639b8c5` at its first examine check: an inactive mount shows its
details and a buy link. The probe above records the GET purchases, replay, out-of-offer
purchases and the fatal sell and feed. The test passes with the change. Local runs are
supporting evidence only; acceptance comes from CI on both supported legs.

## Remaining gaps

- `getmount()` (`lib/mounts.php`, shared) still pastes the stored `hashorse` integer
  into a cached query. It is database state, not request input, and stays with the
  general mount work.
- The mount buff is checked only for being an array. Its business schema (fields,
  types, rounds) stays with the general mount and buff schema item.
- The 1.1.2 purchase applies the stored buff without the "mounts" schema default it
  computes (`apply_buff` then uses the current translation namespace). This is kept
  as historical behavior.
