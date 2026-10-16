# Weapons and armor purchase authority, 2026-10-15

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head: `52bfc4f2e29521dc909e6194221c9ae0efa785ad`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. The bank candidate's
Modern core run `38802851227` was still running when this task started. Its Baseline
integrity run `38802851288` succeeded. The bank therefore stays in Remaining.

Scope: `weapons.php` and `armor.php`, covering the shop listing, trade-in and
purchase. The two files are near mirrors and now share `lib/equipment_authority.php`.
Before this change both were byte-identical to 1.1.2. Stables, mounts and the content
editors are not touched.

## Findings verified on the previous code

These were probed over HTTP on PHP 8.5.11 / MySQL 8.4.11 against `52bfc4f`. The player
had 2 dragon kills, so the shop offered tier 2. A tier-2, a tier-3 and a tier-1 item
were added.

| Finding | Observed |
|---|---|
| Purchase | A GET of `weapons.php?op=buy&id=N` bought the item, with `id` pasted into SQL. There was no POST, CSRF or replay protection |
| Cross-tier by typed URL | **Disproven.** A direct GET for the tier-3 item redirected to `badnav.php` with no change. Forced navigation accepts only the exact URLs the previous page issued, and the listing issues buy URLs only for its own tier |
| Tier check in the purchase handler | **None.** Once the URL was an allowed navigation, the handler sold the tier-3 item (attack 10 → 106) and then the tier-1 item. Nothing but forced navigation stood between a player and any item ID |
| Replay | A second GET of the same buy URL bought the item again; each repeat cost the price minus the trade-in. After a purchase the player's `restorepage` is that buy URL, and New Day's "Continue" link (`resurrection_newday_continue()`) navigates to it, which replays the purchase. That path is a real allowed navigation |
| "Not enough gold" text | **Text only.** The theft text changed nothing (only the page-view `restorepage`) |
| Unlinked items | Items the `modify-weapon`/`modify-armor` hook marked `unavailable` had no link, but their buy URL was still added as an allowed navigation. The purchase path ignored `skip` and `unavailable` |
| Hooks | `weaponstext`/`armortext` (bundled user: `raceelf`'s `weaponstext`) and `modify-weapon`/`modify-armor`, on the listing and the purchase |

## Behavior against 1.1.2

| Behavior | 1.1.2 | Now | Reason |
|---|---|---|---|
| Purchase request | GET link `op=buy&id=N` | Each linked item is a POST form at the same URL, drawn where the link was, with the item name as its button. GET of `op=buy` shows the shop | GET mutation and replay |
| Protection | None | CSRF plus a one-use intent per item (`weapon-buy-<id>`, `armor-buy-<id>`). The intent is bound to the item, the offered tier, dragon kills, gold, current item name, stat, value and the attack or defense total | Replay, stale forms |
| Offered items | Highest level at or below dragon kills; links only for affordable items the hook did not withhold | Same listing and order (weapons by damage, armor by value); forms only where links were | Unchanged |
| Server item check | Existence only | Under lock: the item exists, is in the offered tier for the locked dragon kills, and the item hook neither skips nor withholds it. Otherwise the historical "one too many bonks" text, and nothing changes | Tier rule was navigation-only |
| Funds | `value ≤ gold + round(itemvalue × .75)` | Same formula, on the locked account | Unchanged |
| Price | Read at purchase | Read at purchase, so an admin price change after the form applies, as before. If the item is no longer affordable, the theft text shows and nothing changes | Unchanged |
| Effects | `gold −= value; item = name; gold += tradein; total −= itemstat; itemstat = stat; total += itemstat; itemvalue = value`, saved at page end | The same statements, in order, inside the locked player transaction, together with the debug log. A failure rolls back all of them | Atomic, no drift |
| SQL | Tier value cast; `id` pasted | Bound. `id` must be an integer of at least 1 (400 otherwise) | Binding |
| Escaping | Names through `output` | Unchanged (escaped) | — |
| Messages and hooks | — | Unchanged: titles, descriptions, trade-in text, the theft and purchase texts, "Try again?", `weaponstext`/`armortext`, `modify-weapon`/`modify-armor` | Historical |

Prices, the 75% trade-in and its rounding (`round(10 × .75) = 8`), the tier rule and
the stat formulas are unchanged.

## Tests

`test_weapon_and_armor_purchase_authority_http` runs the same scenario on both shops:

- Access and listing:
  - Anonymous requests are refused.
  - The listing offers exactly the affordable tier item (100 gold + 30 trade-in = 130).
  - It offers no form for the unaffordable, higher-tier or lower-tier items, and the HTML in the item name is escaped.
- GET of the buy URL changes nothing.
- Request refusals:
  - CSRF, missing or wrong, is 403.
  - Malformed, zero and missing IDs and an extra field are 400.
  - Unknown, higher-tier and lower-tier IDs are 409 with no change.
  - A stale form (gold changed) is 409.
- Rollback: a CHECK-constraint failure on the account write rolls back gold, item and stats (500). The spent intent is 409.
- Exact results: gold 100 − 130 + 30 = 0, item stat 7, value 130, total 10 − 3 + 7 = 14; replay is 409.
- Funds count the trade-in under lock, round(130 × .75) = 98: a price raised after the form shows the theft text and changes nothing; at the listed price, gold 33 − 131 + 98 = 0 and the total becomes 15.
- An item moved out of the tier after its form was issued shows the "one too many bonks" text and changes nothing.

It fails against `52bfc4f` because the listing has GET links and no purchase forms. The
probe above shows the GET purchase, its replay and the missing tier check. The test
passes with the change. Local runs are supporting evidence only; acceptance comes from
CI on both supported legs.

## Remaining gaps

- The buy forms are styled to read like the old links. Players without CSS still see
  ordinary buttons.
- The intent binds the player's state, not the item's price, because 1.1.2 charged the
  price current at purchase. A price change between listing and purchase is charged at
  the new price when affordable, as before.
- Equipment purchase does not touch buffs or other stats. Any module that adjusts
  attack or defense outside these columns is unaffected, as in 1.1.2.

## Acceptance, 2026-10-16

`639b8c50f52ae3f8874ab76a810d28b4c4e58257` is accepted. Modern core
**38810195085 SUCCESS**: both `result` jobs and every `checks` and `http` shard job
succeeded on PHP 8.4 / MariaDB 11.4 and PHP 8.5 / MySQL 8.4. Baseline integrity
**38810195222 SUCCESS**. Conclusions come from the GitHub Actions run and job API.
Weapons and armor are Done in `ROADMAP.md`.
