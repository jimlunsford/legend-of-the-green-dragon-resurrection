# Ramius early resurrection authority, 2026-10-04

## Scope and predecessor

Repository: `jimlunsford/legend-of-the-green-dragon-resurrection`.
Starting branch and draft PR #1 head:
`f7a29a8ff162cb4d3dc93db7492158a7da568965`.
Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.

Verified predecessor Modern core `37203919450` and Baseline integrity
`37203919454`: terminal SUCCESS at that exact head. Both PHP 8.4 / MariaDB
11.4 and PHP 8.5 / MySQL 8.4 succeeded. Actual logs report 105 PHPUnit tests,
2,721 assertions, 342 linted files, and 112 Python tests, OK (2448.807s and
3216.296s respectively). Composer validation/install/audit, both PHPStan gates,
historical integrity and project validation also succeeded. No prior run was
redispatched. The workflow timeout remains 60 minutes.

Preserved historical `graveyard.php`, `newday.php`, favor-question and ritual
sources were inspected alongside current daily, mutation, intent, onboarding,
buff and companion consumers. This slice covers only explicit same-day Ramius
resurrection. Graveyard healing/haunting, other economy routes and all other
Phase 3 families are unchanged.

## Entry and authority

`graveyard.php?op=question` and `op=resurrection` dispatch before event handling,
checkday and gameplay header hooks. The question retains the historical favor
threshold and navigation. The complete ritual narrative is retained. Its
Continue button now posts to `newday.php?resurrection=true` with exactly
`csrf_token`, `action_token`, and `resurrection=ramius`. The legacy URL is only
a routing selector. Its GET renders the same ritual/form and never settles it.

All relevant raw queries and POST bodies reject duplicate keys, arrays, nested
values, PHP-normalized ambiguous names and undeclared authority fields. The only
accepted explicit query value is literal `true`; `1`, `True`, `false` and duplicate
variants fail closed. Cache/navigation `c` and the existing `continue` selector
supply no gameplay authority. Only the New Day endpoint accepts the settlement
POST, with URL-encoded transport, authenticated account, CSRF and the existing
one-use `ramius-resurrection` intent.

The account transaction acquires the existing account lock and compares the
persisted record against the freshly hydrated request. It locks and rereads
settings, module registrations/hooks/settings/preferences and the owned mount.
Eligibility and context are checked again under those locks. The context binds
auth version, life/HP, favor, day and committed marker, progression/identity,
bank, counters, raw buffs/companions, mount, event/combat state and material
settings and hook registrations. Equivalent associative order is canonicalized;
ordered lists remain ordered. Display metrics, navigation and access timestamps
are not gameplay authority.

Required death state is persisted `alive=0`, HP exactly zero, valid level and
unsigned soulpoints/gravefights/favor. Zero soulpoints and zero gravefights do not
exclude resurrection. Favor must be at least 100 and at most UINT32_MAX.
`badguy` and `specialinc` must be empty. A live torment or deferred event remains
owned by its existing boundary and cannot be abandoned through this route.
Corrupt bytes are preserved for repair. Raw life state is checked as well as the
hydrated state, so common.php's display normalization cannot authorize revival.

The committed `lastnewday` must equal `gmdate('Y-m-d', gametime())` under current
server time settings. An empty, older or different marker cannot use this route.
If ordinary New Day is due, its existing protected daily transition owns revival
without a Ramius cost. Day rollover during settlement aborts the transaction.
Early resurrection preserves the committed marker exactly; normal New Day stays
ineligible that day and becomes eligible on the next real game day.

Historical New Day could encounter allocation/race/specialty selection before
resurrection. The already-protected onboarding handoffs are retained, including
resurrection routing context, but cannot revive the player or deduct favor.
Final Ramius eligibility requires all three prerequisites to be complete.
Direct Graveyard entry fails closed on incomplete onboarding.

## Historical effects retained

| Effect | Special resurrection rule |
| --- | --- |
| Favor/life/count | Deduct exactly 100; alive true; HP restored to maximum; resurrections +1. |
| Age/master | Age +1, seenmaster zero. Increment inputs must leave room in UINT32 storage. |
| Spirits | Forced -6, displayed as Resurrected. The historical two RNG draws remain consumed before the forced result. |
| Turn pool | Configured base turns plus count of validated stored Dragon Point `ff` allocations. |
| Fixed adjustment | Signed whole-number configuration, default -6; clamp below negative pool to negative pool; positive and zero settings remain supported. |
| Percent adjustment | Signed whole percent; clamp below -100%; exact quotient/remainder arithmetic, half away from zero. Positive and zero percent are supported. No PHP string coercion. |
| Turn bounds | Full unsigned persisted turn domain; reject unpersistable results, including mount/haunt/hook totals. Large percentages are not incorrectly limited to the absolute-fight domain. |
| Mount | Fresh authoritative mount buff, New Day message and signed Forest Fight contribution, including zero and negative. No acquisition/editing changes. |
| Haunting | Subtract one fight and clear hauntedby exactly once. No acquisition changes. |
| Daily-only counters | Preserve playerfights, soulpoints and gravefights exactly. Never apply pvpday or ordinary daily Shades replenishment. |
| Other counters | Zero transferredtoday, amountouttoday, seendragon, seenmaster, fedmount and boughtroomtoday. |
| Timestamps | Server local laston; UTC lasthit; recentcomments receives previous lasthit; committed lastnewday unchanged. |
| Interest | Historical server-sampled multiplier, remaining-fights exclusion, configured bank cap, positive balance and debt rules. Existing explicit signed-INT rounding and overflow checks are reused. Debug DML remains transactional. |
| Buffs | Restore temporary fields; remove ordinary effects; retain validated survivable raw effects and rounds/messages; reapply authoritative mount/racial effects. GET cannot strip or persist display changes. |
| Companions | Existing validator and historical unsuspend operation, including ordinary allowed/excluded companions and a valid Dark Arts skeleton. No acquisition changes. |
| News | One Ramius resurrection news entry on success. Bundled Lovers may separately add its historical divorce news. No failed/replayed Ramius news. |
| Continuation | Server-owned `village.php?c=1`; refresh cannot repeat settlement. |

The favor-question/ritual historically ran the Graveyard header, whose bundled
Drinks hook clears drunkenness. That write has moved inside settlement before
New Day hooks. It runs once even when the ritual is reached through the old
New Day URL. Consequently the complete historical Ramius ritual does not charge
a hangover fight for the pre-Graveyard drunkenness. Ordinary New Day's separate
hangover behavior is unchanged. No bundled `ramiusfavors` hook exists; arbitrary
extension DML is not invoked from that read-only question surface.

## Hook inventory and transaction limits

The 13 active bundled `newday` registrations remain Dag, Drinks, Lovers,
Outhouse, Five/Six, Human, Elf, Troll, Seth Song, Crazy Audrey, Dark Arts, Mystical
Powers and Thieving Skills. Dwarf has no such registration. There are no bundled
`newday-intercept` or `pre-newday` registrations. The latter receives historical
`resurrection='true'`, as does `newday`; intercept retains its empty argument
contract. Disposable observers verify those arguments and invocation counts.

Effects remain the documented daily hook effects: reset module counters,
restore specialty uses/bonus, racial effects, and Lovers marriage/charm effects.
None of these bundled hooks branches on the resurrection argument. All hook DML,
news and debug writes share the player transaction. Failed output is discarded;
request caches are cleared. Cache invalidation itself is not transactional.

Extensions remain trusted PHP. DDL, explicit commits, exit, output flushing or
external network effects are not made rollback-safe by this handler. Arbitrary
third-party extensions are not certified. Global maintenance stays in cron.

The intent is consumed before gameplay starts. A late failure rolls back favor,
life, age/count, interest, turns, timestamps/counters, buffs, companions, news,
debug DML and bundled hook preferences. The failed intent cannot retry; a fresh
form can. SQL commit and session persistence are not claimed to be crash-atomic.
Durable living state prevents a second resurrection if a committed response or
session update is lost.

## Verification and classification

Final local results and candidate publication are recorded below and in PR #1.
Exact supported-matrix acceptance remains pending until the new candidate's
Modern core and Baseline integrity succeed. Prior success is not candidate
acceptance. No unprotected mutation fallback remains in newday.php: it dispatches
protected allocation, race, specialty, ordinary daily reset or Ramius settlement.

Phase 3 remains INCOMPLETE; merge NO; public hosting NO. No VPS, deployment,
release, tag, second PR, new branch or Phase 4 work is included. The next task is
bounded exact-candidate CI acceptance (or inspection of its exact failure).

Local verification uses PHP 8.4.26 and isolated MariaDB 10.11.14 over TCP. The
runtime's Unix-socket restriction was handled with the database's supported
empty socket setting, without escalation or any VPS access. Initial environment
and disposable-fixture failures were corrected before the final checks.

- Nine new Ramius HTTP methods: PASS, final combined run 88.035s. Includes exact
  favor/turn/interest/counter outcomes, all GETs, transport, replay, stale sources,
  real active torment, canonical maps, unsigned/signed numeric extremes, hooks,
  two late rollback sites, fresh retry and two POSTs waiting at the account lock.
- Twenty-six retained HTTP regressions: PASS, 146.117s. All seven normal daily
  methods, five Dragon Point methods, both identity onboarding methods, all nine
  Graveyard methods and one each Forest/Training/PvP authority regression.
- The existing create/login/session/render/logout method also passes.
- Nine non-gameplay Python/tooling tests: PASS. Full collection is 121 methods
  (112 HTTP plus nine tooling). This is not a claim of a local full-suite run;
  the unchanged exact-head GitHub workflows run the entire collection.

The concurrent test uses two independent loopback listeners and a copied
synthetic authenticated fixture session so the session-file mutex cannot conceal
missing SQL revalidation. Both real requests are observed at the account lock
before release; results are one HTTP 200 and one HTTP 409, exactly one cost,
count, age increment, interest settlement, turn calculation and Ramius news.
Ordinary multiple forms from one real session independently prove replay rejection.

Fixture-only session copies, observer modules, constraints and database processes
are cleaned up. No test-only fault injector or alternate authentication path is
included in production source.

Final clean-install PHPUnit: **108 tests / 2,785 assertions, PASS**, no skips.
PHP lint: **346 files / zero failures**. Legacy and infrastructure PHPStan: PASS.
Composer strict validation, locked installation and audit: PASS, no advisories.
Historical tag/object/source/tree: PASS, **417 files / 11 preservation commits**.
Project validation and tracked-file hygiene: PASS. No workflow, dependency or
historical source changes. The new handler is included in the level-6 gate.

Explicit Ramius resurrection: **local proof PASS, supported CI PENDING**.
Broader New Day family: **supported acceptance PENDING**. No broader PASS is
claimed before the candidate's complete two-database CI summaries succeed.
