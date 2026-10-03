# Normal New Day daily-reset authority, 2026-10-03

## Scope and starting evidence

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Actual starting public branch and PR #1 head:
`7c3c12765057ff1a4b9eac76629aa18a07460c9e`.
Main: `999cec6f9c655a840320d982d673bb863c68c2b2`.
PR #1 is open, draft, not ready and unmerged.

Prior Dragon Point runs were inspected once: Modern core `37123254762`
IN_PROGRESS; Baseline integrity `37123254758` SUCCESS. No waiting, redispatch,
or subsequent poll of that cycle. Pending prior CI did not block this development.

Historical `newday.php`, `lib/datetime.php`, companion handling and all bundled
New Day hooks were inspected before implementation. This scope excludes explicit
`resurrection=true`, global maintenance and all other Phase 3 subsystems.

## Method, day identity and durable authority

Normal GET renders a confirmation form without invoking `newday-intercept`,
`pre-newday` or `newday`. Presentation can calculate temporary display fields but
cannot persist gameplay fields, serialized buff bytes or companion changes.
Only normal navigation, display metrics and access time may be saved by that page.

Normal POST requires authentication, CSRF, a one-use state-bound intent and exact
URL-encoded fields. Duplicate/ambiguous transport is rejected by the existing
Dragon Point transport validator. No client day, balance, rate, age, timestamp,
turn count or RNG result is accepted. Dragon Point, race and specialty stages
retain their existing protected dispatch before the daily reset.

Day identity is `gmdate('Y-m-d', gametime())`, using the existing
`convertgametime`, configured epoch, offset and days-per-day calculation. The
fresh-install account schema adds `lastnewday`, a dedicated ten-character game
calendar date. It commits with the daily effects and survives Dragon progression
reset. `is_new_day()` honors this marker before the older `lasthit` fallback,
so changing access/presentation fields cannot authorize another reset or trap a
completed player in a redirect loop. An empty marker uses historical eligibility
for initial onboarding. Existing databases are not altered on a web request;
this branch still requires a separately verified migration before upgrading a
populated installation. No deployment or migration execution is claimed.

The existing player transaction locks the account, compares persisted state,
then refreshes and locks module registrations, hook rows, material game settings,
module settings, actor preferences and any owned mount. Both eligibility and the
intent context are checked again under lock. The form binds the account's
material fields, prior day, age, life/HP, level, identity, completed points,
bank/counters, raw effects/companions, mount and hook sources. Equivalent record
key order is canonicalized; ordered lists remain ordered. A day transition before
commit rejects and rolls back. Invalid state is preserved for explicit repair.

Durable SQL state is the daily guard. Session intent adds replay protection; it
is not the sole guard. A crash before SQL commit rolls back transactional DML;
a crash after SQL commit can lose the result page/session response while the
marker prevents replay. No universal crash-safe exactly-once external-effect or
session/SQL atomicity claim is made.

## Preserved daily effects

| Boundary | Historical rule retained |
| --- | --- |
| Life | Dead player becomes alive and gains exactly one resurrection; living player gains none. HP restores to max HP. No normal Ramius news, deathpower cost or special turn penalty. |
| Age/master | Age +1; `seenmaster=0`. |
| Turns | Server `turns` + two independent `e_rand(-1,1)` draws + stored validated `ff` count + signed mount Forest Fights - haunted fight, then bundled hooks. |
| Spirits | Sum of the two server draws, -2 through +2. Test RNG exists only in disposable fixture modules. |
| Bank | Historical integer-percent sampled multiplier, reversed endpoints supported by existing `e_rand`. Zero interest when remaining turns exceed threshold and balance is nonnegative; also when enabled maximum-bank threshold is reached. Debt still accrues interest despite unused fights. |
| Bank bounds | Signed 32-bit persisted balance and result; finite bounded settings/product. Historical database rounding to INT is explicit, halves away from zero. No silent clamp. |
| Buffs | Restore temporary calculated fields; strip ordinary effects; reapply only `survivenewday=1` raw validated effects, retaining rounds, schema and message. |
| Mount | Read existing owned row without cache, validate buff and signed Forest Fight contribution. Missing/corrupt ownership/effect rejects. Preserve name and New Day output; empty combat substitutions are supplied in this noncombat path. Shipped numeric-string mount rounds and the exact legacy mount/Audrey `activate` metadata and Lovers translated wearoff tuple are validated in a copy, with raw state retained. Purchase/editing is outside scope. |
| Haunted | Subtract one fight and clear `hauntedby`, once. |
| PvP/transfers | `playerfights=pvpday`; `transferredtoday=0`; `amountouttoday=0`. |
| Flags | `seendragon`, `seenmaster`, `fedmount`, `boughtroomtoday` reset to zero. |
| Shades | `soulpoints=50+5*level`; `gravefights=gravefightsperday`. |
| Time/comments | `recentcomments` receives previous `lasthit`; `lasthit` receives server UTC time; `laston` retains historical server local-time access semantics. |
| Companions | Historical `unsuspend_companions("allowinshades")` actually clears suspension on all retained companions. Its argument does not filter eligibility. Both allowed and excluded companions return. Skeleton remains excluded from Shades combat, but returns from suspension on normal New Day. |
| Continuation | Preserve sanitized saved route; empty, badnav, newday and Graveyard continuations go to village. Result refresh/replayed POST cannot repeat the reset. |

## Active bundled hook inventory and side effects

The installed fixture enables all 24 shipped modules, then reads actual active
registrations. Thirteen register `newday`; none register `pre-newday` or
`newday-intercept`. Source inventory matches those registrations.

| Module | Normal hook effects | Transaction classification |
| --- | --- | --- |
| Dag | Clear daily bounties | Preference DML/cache invalidation |
| Drinks | Clear hard drinks/drunkenness; hangover over 66 removes one fight, historically floored at zero | Account + preference DML + output/cache |
| Lovers | Clear daily visit; historical NPC-marriage charm loss/divorce and news | Account + preference/news DML + output/cache |
| Outhouse | Clear used/stage | Preference DML/cache |
| Five/Six | Clear plays today | Preference DML/cache |
| Human | Configured extra fights for Human | Account + output |
| Elf | Elvish Awareness for Elf | Raw buff + output |
| Troll | Trollish Strength for Troll | Raw buff + output |
| Seth Song | Clear been | Preference DML/cache |
| Crazy Audrey | Clear played/paid visit | Preference DML/cache |
| Dark Arts | Restore `int(skill/3)` plus selected-specialty bonus | Preference DML + output/cache |
| Mystical Powers | Same historical use calculation | Preference DML + output/cache |
| Thieving Skills | Same historical use calculation | Preference DML + output/cache |

Dwarf has no `newday` registration. Other shipped hooks are not invented.
Bundled preference, news and debug-log DML use the same InnoDB connection and
transaction as the account. Page output is buffered/restored on failure.
Cache invalidation is nontransactional but contains no committed gameplay reward;
request preference/settings caches are cleared on rollback. No bundled New Day
hook sends email or performs another irreversible external action.

Extension intercept/pre/day hooks execute only inside normal POST's locked
mutation. Disposable observers prove zero GET invocations and one invocation on
success. Extension PHP remains trusted code: DDL, network effects, `exit`,
explicit commits or output flushing are not made transactional by this handler.
Arbitrary third-party extensions are not certified. Global run-once cleanup stays
with cron/advisory locking and is never invoked here.

## Evidence and acceptance

Seven new real HTTP methods cover living/dead resets, exact deterministic
interest/spirits/turn totals, positive/zero/negative mount fights, raw GET
immutability, carried/removed buffs, companions, all bundled hook counters,
racial effects, three specialty restorations, stale account/settings/prefs/mount/
hook/day state, equivalent record order, corrupt state, replay, duplicate forms,
locked revalidation after a competing commit, extension invocation and rollback.

Representative living/dead fixture: level 6, three spent points including two
`ff`, base 10, spirits +1, haunted -1, Human +1, Drinks hangover -1 => 12 turns.
Bank 1000 => 1100 at fixed 10%; HP 100; soulpoints 80; gravefights 12; PvP fights 4.
Age 1 => 2; resurrection count 2 => 2 living or 3 dead. Bank 101 => 111 and debt
-101 => -111; fights/cap exclusions and disabled cap are exercised. Seeds also
prove spirits 0 and -2, with mount contributions 0, -2 and +3.

Late account CHECK failure and actual bundled Dark Arts preference-write failure
occur after meaningful core/hook effects. Account fields, bank, buffs,
companions, preferences, Lovers news and debug DML roll back together. Consumed
intent rejects, and a fresh form retries successfully. A separate connection
holds the account lock while real HTTP POST waits; a competing committed day
marker/age update makes that POST reject without applying bank/turn changes.
Two actual issued forms also prove one normal reset and second-form rejection.

Final local verification used PHP 8.4.26 and disposable MariaDB 10.11.14:

- PHPUnit: 105 tests, 2,721 assertions, zero failures/skips on the clean final run.
- 24 distinct focused HTTP methods have passing results across the focused and
  corrective reruns, including all seven new methods and retained onboarding,
  Dragon Points, Lovers, buffs and Graveyard companion coverage. Initial failures
  exposed fixture setup and the shipped Lovers message tuple; both were corrected
  and their affected methods rerun successfully. No retained test was removed.
- Nine tooling tests pass. Full discovery is 112 Python tests: 103 HTTP plus nine
  tooling. This is a collection total, not a claim that all 103 HTTP tests ran locally.
- PHP lint: 342 files, zero failures. Both PHPStan gates pass. Composer strict
  validation, locked installation and audit pass with no vulnerability advisories.
- Historical tag/object/source/tree integrity passes: 417 files, 11 preservation
  commits. Tracked-file hygiene and whitespace checks pass before publication.

Exact publication/CI references are recorded in the publication checkpoint on
PR #1. Local success is not supported-matrix acceptance.
Normal daily-reset acceptance remains BLOCKED until exact-candidate Modern core
and Baseline integrity succeed. Broader New Day remains BLOCKED because explicit
Ramius early resurrection is unchanged and unproven.

Phase 3 remains incomplete. Remaining families include explicit resurrection,
mail/systemmail, petitions, clans, bank/economy outside daily interest, equipment,
stables/editors, remaining serialized consumers, expiration and account deletion.
Merge: NO. Public hosting: NO. No Phase 4, VPS, deployment, release, tag, branch,
or second PR is authorized by this slice. Next task is bounded exact-candidate
CI acceptance or a narrow candidate-caused correction; explicit early resurrection
requires a subsequent scoped execution.
