# Haunting authority, 2026-10-16

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `8257aed3dc48f669a9361c202bac397488d3ad67`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Predecessor runs, both SUCCESS:

| Candidate | Commit | Modern core | Baseline integrity |
|---|---|---|---|
| Stables | `8257aed` | 38831947335 | 38831947372 |

Scope: the Graveyard haunt, `graveyard.php?op=haunt`, `op=haunt2` (the name search)
and `op=haunt3` (the haunt). Route-local helpers live in `lib/haunt_authority.php`.
Before this change all three case files were 1.1.2. Restoring the soul (`op=restore`),
the rest of the Mausoleum and the haunted New Day penalty are not opened; the penalty
was accepted with the New Day and Ramius records.

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4 against `8257aed`. The player
was dead, at level 0, with 30 favor. The fixture targets were one unlocked account
(level 0) and one locked account.

| Finding | Observed |
|---|---|
| GET haunts | GET `op=haunt3&name=…` charged 25 favor (30 → 5), rolled and wrote news at once |
| Replay | Repeating the same GET wrote another "unsuccessfully haunted" news row each time. The favor was not charged: 5 − 25 is negative, the unsigned `deathpower` write failed at the end of the page, but the news insert had already been committed. A GET haunt URL becomes the restore page, which New Day's "Continue" link follows |
| Favor | Never checked. The handler subtracts 25 from whatever is left |
| Locked account | Only the search applies `locked=0`. With 5 favor, the locked target's link haunted it: its `hauntedby` was set, the news and the notice were committed, and the favor was never charged (the same failed write) |
| Long names | `hauntedby` is `varchar(50)` and `name` is `varchar(60)`. A 58-character name was fatal under strict SQL on the target UPDATE. 1.1.2's MySQL truncated it |
| SQL | The target login from the URL is pasted into the SELECT and the UPDATE. An apostrophe was fatal (SQL error) |
| Search input | `name[]=x` was fatal (`strlen()` on an array). A UTF-8 search such as `雪` found nobody: the pattern is built byte by byte, so `%` lands inside the character |
| Helper include | `require("lib/systemmail.php")`, not `require_once`; the helper is already loaded on any page that loaded it earlier |

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Haunt | GET `op=haunt3` haunted at once | GET `op=haunt3` shows a "Haunt *name* (25 favor)" form; the haunt is its POST | GET mutation |
| Protection | None | CSRF plus a one-use intent (`graveyard-haunt`) bound to the target's ID and login and the player's ID, favor, level, life and name | Replay, stale forms |
| Target | Any account by login; the search listed only unlocked ones | The search's rule, `locked=0`, also on the GET form and under lock at the haunt; otherwise the historical "lost their concentration" text and no change | Rule was search-only |
| Favor | Subtracts 25, never checked | 25 required: the GET shows Ramius's "not yet impressed" line with no form, and the locked haunt refuses (409) | Unchecked cost |
| Already haunted | Message, no charge | Same, also checked under the target's lock | Concurrent haunts |
| Effects | Favor, roll, target UPDATE, news, notice; the favor saved at page end | The same order and rolls inside the locked player transaction, which also locks the target; the notice is written with it and its email runs after commit, so a failure rolls back favor, mark, news and notice | Atomic |
| `hauntedby` | The haunter's name, truncated to 50 by MySQL | The first 50 characters, explicitly | Strict SQL |
| SQL | Login pasted; the search escaped with `addslashes` | Bound. The search keeps 1.1.2's pattern (`%` between every typed character, wildcards included) | Binding |
| Search input | Array fatal; split into bytes | Array or invalid UTF-8 is 400; a UTF-8 name is split into characters (1.1.2's one byte was one character) | Input type, UTF-8 |
| Messages, rolls, cost, news, notice | — | Unchanged | Historical |

A player can still find and haunt themselves, as in 1.1.2.

## Tests

`test_graveyard_haunt_authority_http`:

- Anonymous requests are refused.
- The search binds an apostrophe, lists the unlocked target, omits the locked one and escapes the name. A one-character UTF-8 search (`雪`) finds the target. An array or invalid UTF-8 search is 400.
- GET of `op=haunt3` changes nothing (favor, mark, news, notices).
- CSRF, missing or wrong, is 403. An extra field, a missing or empty login and an array login are 400.
- The locked and an unknown target show "lost their concentration" with no form; a forged POST is 409.
- At 24 favor the GET shows "not yet impressed" with no form, and a form issued at 100 favor is 409.
- A failed haunt (level 0 always rolls 0): 100 → 75 favor, one news row, no notice, no mark. Replay is 409.
- Rollback: a CHECK-constraint failure on the player's write rolls back favor, mark, news and notice (500). The spent intent is 409.
- Success: 75 → 50 favor, the target marked with the first 50 characters of a 58-character name, one news row and one System notice naming the haunter. Replay is 409.
- Already haunted: no form; a form issued before someone else's haunt shows the historical message and charges nothing.

It fails against `8257aed` at the `雪` search (no match); the array search there is fatal. The probe above
records the GET haunts, replay, unchecked favor, locked target and fatals. The test
passes with the change. The success case uses level 100000 against level 0, which
fails one roll in 100001. Local runs are supporting evidence only; acceptance comes
from CI on both supported legs.

## Remaining gaps

- `op=haunt` and `op=haunt2` stay reachable by issued navigation alone, as in 1.1.2.
  They only display.
- `op=restore` (restoring the soul for favor) is still a GET mutation. It is not
  part of this family.
