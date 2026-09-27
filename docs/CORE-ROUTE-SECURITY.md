# Core route security

Date: 2026-09-14. This is the source of truth for the Phase 3 continuation merge decision. **Modern-core verdict: NOT READY. PR #1 must remain OPEN and DRAFT. Public hosting: NO.**

PASS applies only to the named operation and evidence. PARTIAL means protections and tests exist but the family has unclosed acceptance gates. OPEN means unproven or unresolved, not an assertion that every path is exploitable. Existing login/issued navigation is not accepted as business authorization. All HTTP tests run against disposable databases and loopback PHP servers in both CI matrix targets.

| Route | Mutation | Authentication | Authorization | Input typing | SQL binding | POST | CSRF | Replay handling | Output context | Status | Test evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| runmodule.php / module injection | Dispatch | Existing login + forced-nav gate | Active install + dependency checks | Module identifier allowlist | Bound registry reads | Per action | Per action | Per action | Module-specific | PASS dispatch; not blanket route certification | Existing module HTTP inactive/force/dependency fixtures |
| Forest: findgem, findgold, foilwench | Reward / gem-for-skill | Logged-in player | Current server-selected event + active module | Finite operation + server amounts | Player/pref/news/debug writes bound | Yes; GET confirmation only | Yes | Generation + consumed intent + persisted completion | LoGD text; no request markup | PASS tested Forest scope | test_module_purchases_post_csrf_replay_and_effects; lifecycle/hooks; PlayerMutationTest |
| Forest: goldmine | Reward / loss / skill | Logged-in player | Same current-event contract | Finite operation; historical random effects | Shared transaction writes bound | Yes | Yes | Same completion contract | Historical LoGD story retained | PARTIAL; random/state branch and integration review incomplete | Same HTTP test; prior hook fixtures |
| runmodule.php?module=dag | Place bounty | Logged-in player | Active Dag; eligible existing nonself target; daily quota | Positive bounded amount; typed UTF-8 name; literal LIKE search | Bound; account/target locks | Yes | Yes | One-use intent; transaction | LoGD names; escaped attributes | PASS placement | PlayerMutationTest; existing player HTTP; administrator literal-name search |
| Dag pvpwin hook | Claim bounty | Internal callback from owned PvP result | Server target; mature open bounties; own excluded | Typed target and stored payout; PvP schema | Bound; enclosing combat transaction | Yes at caller | Yes | Consumed state intent; conditional closed status; terminal combat cleared | LoGD output; news/debug/mail SQL bound | PASS named operations | Funded HTTP, four rollback points and retry; PvpStateTest |
| Dag manage=true | Place / close / cleanup / list | Logged-in player | SU_EDIT_USERS in handler | Bounded IDs/amounts; allowlisted sort/filter; UTF-8 literal search | Bound values; typed database IDs | Yes for mutations | Yes | Consumed place/close/cleanup intents; conditional close | Escaped attributes, LoGD names | PASS named operations | Anonymous/ordinary/insufficient/authorized; GET/CSRF/invalid/replay/valid HTTP |
| runmodule.php?module=drinks | Purchase / consume | Logged-in player | Active drink; server funds/quota | Typed ID; authoritative cost; configured hardlimit/maxdrunk bounds | Bound; drink/account locks | Yes; GET confirms | Yes | One-use purchase | LoGD remarks; escaped editor values | PASS named operations | Purchase HTTP; PlayerMutationTest configured boundaries and actual New Day reset |
| Drinks editor | Create / save / delete / activate / deactivate | Logged-in player | Stored canedit or SU_EDIT_USERS; forged/revoked delegation denied | DrinkInput allowlist; finite IDs; extreme and invalid bounds | Bound; related DML transactional | Yes | Yes | One-use intent on every mutation | Raw UTF-8/backslash/apostrophe; escaped forms | PASS named operations | Actual role/CRUD/revocation/malformed/CSRF/replay matrix; DrinkInputTest |
| runmodule.php?module=cedrikspotions | All five potion effects | Logged-in player | Active module; server availability/funds | Finite wish; positive quantity; server cost; persisted state bounds | Shared account/pref transaction | Yes | Yes | One-use intent | Historical LoGD story | PASS named potion/persistence operations | Shared settings and full Transmutation/Dragon lifecycle HTTP; exact rollback and retry |
| runmodule.php?module=game_stones | Choose / commit bet / draw / settle | Logged-in account | Active game and Dark Horse event; owner and game match | Exact JSON envelope and Stones schema; finite action and fields | Bound locked player transaction | Yes | Yes | Unique game generation; state intent; terminal state; no-refund abandonment | LoGD text; typed dice/stones; application return | PASS supported game | StonesGameTest; DarkHorseGameTest; full HTTP play/replay/abandonment and final-write rollback |
| Dark Horse entry / exit / bartender / event | Mount entry / encounter exit / paid information / wager abandonment | Logged-in event actor | Matching active wager; trusted target lookup; direct mount requirement | Bounded names/search; strict wager schema; server 100-gold cost | Bound SELECTs and locked actor debit | Yes for mounted entry, exit, payment and abandonment; GET confirms | Yes | Consumed scoped intent; wager generation and terminal state | LoGD stat/story text; escaped form URL | PASS supported Dark Horse operations | Mounted/Forest entry, exit, shared settings/mount preferences plus retained wager/information HTTP |
| game_dice / game_fivesix | Commit / roll / settle / jackpot | Logged-in account | Active Dark Horse event; matching owner/game; quota/funds | Finite server state; positive wager; settings ranges; no client result/attempt/jackpot | Bound player transaction; Five/Six locks module and setting rows | Yes | Yes | Unique wager ID; consumed state intent; one settlement | Typed dice and LoGD text; application-owned return | PASS supported games; other admin editor surfaces remain core blockers | DarkHorseGameTest; actual HTTP progression/replay/rollback; two-server jackpot concurrency; New Day reset hook |
| Crazy Audrey Village and Forest | Pet/play and basket reward/loss | Authenticated player | Active module; paid visit; daily played; current Forest event | Declared settings; bounded funds/profit; strict daily state | Bound, locked account/settings/preferences, atomic profit/debug/buff | Yes, GET confirms | Yes | Day/action intent; paid/played state; event completion | Escaped configured buff name; LoGD story text | PASS supported bundled scope | test_module_audrey_village_authority; testAudreyDeterministicBasketsAndDailyReset; retained Forest HTTP |
| Fairy and Glowing Stream Forest | Historical event effects | Authenticated player | Active current event | Finite operations; Fairy declared awards and bounded extra HP | Shared bound player/preference transaction | Yes | Yes | Consumed event intent and persisted completion | Existing LoGD text | PASS supported bundled scope | Deterministic ModuleCertificationTest outcomes; Fairy editor/Dragon HTTP; retained event HTTP |
| Lovers | Daily interaction and alternative flirt choices | Authenticated player | Active module; living player; no pending event; unconsumed daily flag; stored sex/marriage path | Bounded alternative 1..7 or married visit; no supplied effects | Bound locked account/pref/news/debug transaction | Yes; GET read-only | Yes | State/day-bound one-use; persisted daily flag; stale/replay rejection | Escaped configured/player text; historical LoGD formatting | PASS supported bundled scope | test_lovers_alternative_choices_daily_authority_http; both targets 34841113158 |
| runmodule.php?module=outhouse | Paid/free use; wash/leave outcome | Logged-in player | Active module; used/stage checked under lock | Finite op; server cost; matching paid/free stage | Account/pref/debug/news transaction | Yes; GET confirmation | Yes | Day/account one-use + strict persisted used/stage; cannot re-wash via new form | LoGD story and item display | PASS supported bundled scope | test_outhouse_configured_outcomes_http; retained paid/free/wash HTTP |
| runmodule.php?module=sethsong | Daily song effect | Logged-in player | Active module; daily visits rechecked under lock | Declared typed settings, paired reward bounds, nonnegative effects and visits | Account/pref/news transaction | Yes; GET visit read-only | Yes | Day/account one-use + daily counter | LoGD story | PASS supported bundled scope | test_sethsong_all_configured_effects_http; retained HTTP and New Day hook |
| newday.php races | Human / Elf / Dwarf / Troll choice | Authenticated player | Unknown race; no pending event; dragon points spent; installed active bundled module | Server-resolved identity/location; bounded allowlisted fields | Bound account/registry/settings reads and account update | Yes; GET only displays | Yes | State/day/options intent; stored race transition; stale/reselection denial | Escaped city descriptions; historical LoGD text | PASS supported bundled races | test_race_onboarding_http_authority; retained runtime/PvP/New Day hooks |
| newday.php + battle.php specialties | Choice / combat use / reset | Existing login gate | Combat/session ownership and remaining uses gate incomplete | Unsupported/negative levels need full route rejection | OPEN | Combat GET mutations remain | OPEN | OPEN | Finite expressions already replace eval | BLOCKED | Existing all valid skill buff/uses and reset hook fixtures; route matrix absent |
| mail.php delete/unread | Delete / unread state | Logged-in mailbox owner | Ownership enforced | Typed message IDs | Bound | Yes | Yes | Delete/status idempotent | Escaped subject and LoGD body | PASS existing scoped operations | Existing mailbox HTTP and ownership service tests |
| mail.php send / address / reply | Send mail | Existing login gate | Reply mailbox ownership and GM sender boundary not fully certified | Recipient/subject/body bounds OPEN | Recipient search still needs closure; systemmail helper writes bound | Send form uses POST; enforced boundary OPEN | OPEN | OPEN | Subject/body context closure OPEN | BLOCKED | Source review case_send.php/case_write.php; no new send/reply HTTP tests |
| lib/systemmail.php | Internal mail + optional email notification | No standalone HTTP handler | Internal helper; caller may be player-facing mail send; not proven trusted-only | Recipient/from/subject/body bounds OPEN | Existing helper database reads/writes bound; caller trust remains OPEN | Caller responsibility | Caller responsibility | No durable deduplication | Scalar restoration constrained; translation schema OPEN | BLOCKED | Source/call-boundary review; no complete helper test suite |
| petition.php intake | Submit petition | Explicit anonymous intake or logged-in actor | Server actor identity; no arbitrary POST persistence | Prior bounded intake | Bound | Yes | Yes | Existing intake semantics; abuse gate separate | No serialized session diagnostics | PASS existing intake scope | Prior real petition HTTP |
| viewpetition.php | Status / notes / cleanup | Logged-in admin | SU_EDIT_PETITIONS exists; full matrix missing | id/setstat not fully typed | Unbound id/status queries | Status and cleanup still GET | OPEN for status/cleanup | OPEN | Petition text review incomplete | BLOCKED | Source review; intake tests are not admin evidence |
| clan.php / lib/clan/* | Join/leave/rank/removal/MOTD/text | Existing route gate | Cross-clan target + officer/rank checks incomplete | setrank/remove and other inputs OPEN | Unbound rank/target/text SQL | GET mutations remain | OPEN | OPEN | Persisted MOTD/text context OPEN | BLOCKED | Source clan_membership.php; required role/rank HTTP matrix absent |
| bank.php | Deposit/withdraw/transfer/loan | Existing login gate | Recipient + balances transaction OPEN | abs(cast amount) still accepts negative intent | Unbound recipient SQL; transfer not certified atomic | Historical forms; enforcement OPEN | OPEN | OPEN | Recipient name context OPEN | BLOCKED | Source review; no new economy rollback/HTTP suite |
| weapons.php / armor.php | Purchase/trade-in | Existing login gate | Server item/eligibility checks not fully certified | Request item ID not strictly bounded | Unbound item ID SELECT | GET buy mutation remains | OPEN | OPEN | Item-name context OPEN | BLOCKED | Source review; no purchase HTTP suite |
| stables.php / mounts.php | Purchase/sale/replacement/admin edit | Existing login gate | Ownership/location/admin matrix OPEN | Request id + buff schema OPEN | Unbound request id query | GET mutations remain | OPEN | OPEN | Mount text context OPEN | BLOCKED | Object restoration constrained only; no mount transaction/HTTP suite |
| train.php / masters.php | Fight / level advancement / master edit | Existing login gate | Server experience/master authority incomplete | master/victory/combat parameters OPEN | Unbound master input query | GET combat/advancement paths remain | OPEN | OPEN | Master/character text OPEN | BLOCKED | Source review + prior stat fixtures; no duplicate advancement test |
| pvp.php / battle.php | Entry / round / result | Logged-in live actor | Existing eligible nonself target; location/immunity/daily fights; owned target/reservation | Finite action; bounded target; minimum PvP business schema | Bound target/victim/account/bounty/news/mail writes | Yes; GET confirms | Yes | State-bound one-use intents; terminal state cleared; transaction rollback | LoGD output; target markup HTTP assertion | PARTIAL core; funded Dag suite PASS | Actual entry/win/invalid eligibility/rollback/retry; defeat, inn/bodyguard, broader combat matrices still OPEN |
| user.php | Account and core/module settings edit | Existing login gate | Existing capability gates; representative role matrix incomplete | User field namespace and role matrix remain OPEN; configuration.php now uses declared settings | Some bound settings writes; whole family incomplete | Some POST guards exist | Some CSRF exists | Repeated absolute writes partly idempotent; full effects OPEN | Form/output contexts incomplete | BLOCKED | Source review; no complete anonymous/player/insufficient/admin matrix |
| creatures.php / armoreditor.php / weaponeditor.php / mounts.php / titleedit.php / taunt.php | Administrative content CRUD | Existing login gate | Existing capability checks not a full HTTP proof | IDs and editable field allowlists need closure | Unbound/interpolated legacy writes remain to classify | Not fully certified | Not fully certified | Not certified | Persistent creature/item/mount/title/taunt HTML context OPEN | BLOCKED | No new full representative editor HTTP matrix |
| Remaining user preferences / modules.php lifecycle editor | Preference/lifecycle edits | Existing login gate | Representative administrative role matrix incomplete | Shared configuration and mount object editor separated below; remaining callers OPEN | Whole family incomplete | Not fully certified | Not fully certified | Not fully certified | Remaining caller-specific contexts OPEN | BLOCKED | Lifecycle APIs pass; no blanket HTTP editor certification |
| configuration.php core and declared module settings | Typed patch | Logged-in player | SU_EDIT_CONFIG; declared installed namespace | Existing descriptors; bounded numeric/text/boolean/enum/amount values; declared keys and paired ranges | Bound transactional settings, associated DML and audit | Yes | Yes | One-use context includes namespace, schema and displayed state; locked recheck | Escaped form labels/values/options | PASS exercised shared boundary | Actual authorized/ordinary/anonymous/insufficient role; forged namespace/key; malformed/bounds/enum; CSRF; replay; stale; rollback/fresh retry; configured behavior |
| mounts.php shared module preferences | Declared object preference patch | Logged-in player | SU_EDIT_MOUNTS; installed declaring module and existing mount | Fixed mounts type; bounded ID; declared key/type/range/text | Bound; actor/module/mount/preference locks; associated audit | Yes | Yes | Object and preference snapshot context; cross-object/module and stale/deleted rejection | Escaped values and labels | PASS enabled mounts boundary | Dark Horse/Goldmine real HTTP role/CSRF/replay/types/bounds/cross-object/module/deletion/rollback; Drinks object editing stays disabled |

The `prefs.php` player namespace path now rejects ambiguous triple-underscore suffixes, internal preference names, undeclared keys and inactive/missing modules before any write. None of the 24 bundled modules declares an editable `user_`/`check_` preference; future descriptors remain fail-closed until a typed parser is implemented. HTTP attempts to forge Drinks `canedit` and specialty `skill` values leave all module preference rows unchanged. This closes that specific privilege/value-injection path, not the full core preferences or administrator editor family.

## Shared mutation limits

`resurrection_player_mutation()` locks the actor account, rejects stale hydrated state and negative/fractional/overflowing currency, and commits changed player fields with related InnoDB DML. Bounty targets and drink rows are locked by their services. On failure it rolls back DML and restores player state, companions, page output, base account and module caches. `PlayerMutationTest` proves rollback/retry and stale-state rejection. It does not prove arbitrary external hooks, DDL, network effects or crash-safe exactly-once execution.

Action intents are session-bound, scoped and consumed once. Event intents are also tied to a server event generation; completed events persist `specialinc` removal. Outhouse persists used/stage separately. PHP session locking plus account revalidation handles ordinary duplicate/concurrent requests; there is no universal durable receipt system for these actions. A server crash between database commit and session persistence remains a documented recovery consideration.

## Remaining meaningful GET mutations and replay risks

Confirmed remaining families include Lovers effects, race/specialty onboarding and combat, petition status/cleanup, clan membership/rank/removal, equipment, stables, training, PvP and legacy editors. Bank/mail forms also need enforced CSRF/replay and transaction boundaries. No exhaustive claim is made for unreviewed core routes.

Stones, Dice and Five/Six now commit stakes before continued play, retain account-owned versioned JSON state with unique game IDs, reject conflicting game changes and settle only once. Oldman GET no longer clears state; a protected matching POST abandons without refund. Both-target HTTP tests include final-write failure/rollback and explicit bypasses of allowed navigation to test business authority.

## Maintenance classification

- `lib/expire_chars.php` advances `last_char_expire` before selection, deletion hooks, account deletion and notifications. A failure can falsely suppress retry. This is a **merge blocker**, not merely a hosting concern. No fix/failure-retry test is claimed in this continuation.
- `lib/charcleanup.php` executes deletion hooks before related-row cleanup; a later veto can follow earlier hook side effects, and exceptions/DML failures have no encompassing account-deletion transaction here. Expiration logs deletion before its final account DELETE. Full hook failure/rollback/manual-recovery behavior remains a **merge blocker**.
- Expiration notices call external `mail()` and mark sentnotice without checking delivery acceptance; this cannot be rolled back with SQL. Delivery/recovery design is a separate hosting concern, but false cleanup completion still must be fixed for merge.
- `lib/newday/dbcleanup.php` advances its optimization marker before `OPTIMIZE TABLE`. MySQL/MariaDB optimization and DDL can implicitly commit. Do not claim a transaction covers these operations. Optimization retry/observability can be a documented maintenance limitation after correct cleanup completion semantics exist.
- Previously certified bundled daily-hook failure/retry/concurrency receipts remain green; they do not certify later core cleanup.

## Output and administrative scope

Keep LoGD formatting separate from plain text, trusted application HTML, HTML attributes and URLs. The changed module forms escape attributes; persisted player/item text still uses LoGD output. News/debug values are now bound. This does not close arbitrary administrator-authored HTML or unchecked preference namespaces. Systemmail is an internal PHP function, but `case_send.php` calls it with player-controlled message text and a GM-specific sender path. Its internal location alone is not evidence of a trusted-only input boundary.

Disabled LoGDnet, raw SQL/PHP console, payments, source viewer and insecure recovery remain disabled. Recovery HTTP 410 is acceptable at the merge milestone. No deployment, public game runtime, release or VPS operation is part of this work.

See [serialized state](SERIALIZED-STATE-AUDIT.md), [module certification](BUNDLED-MODULE-CERTIFICATION.md), and [continuation evidence](MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md).

## Dark Horse continuation, verified on both targets

The preceding checkpoint rows remain historical evidence. This continuation replaces the implementation of the three game routes and oldman, plus the bartender's paid information query. The expanded supported matrix passed at `1a6cd7340978e68fa43e7b2d015f4ca513d130ec`: Modern core 34783935347; Baseline integrity 34783935406.

- All games persist a versioned account-owned JSON wager envelope with a random 128-bit game ID in `specialmisc`: game, stake, finite stage, active flag, result, settlement flag, exact game data. The account transaction commits the debit before another action can observe a roll. Stones and Dice return 0/1/2 stakes on loss/tie/win, preserving net economics. Five/Six retains its 5/10/100 percent payout and 100-gold jackpot reset.
- `oldman` GET preserves state and offers resume. POST/CSRF plus a state-bound consumed intent permits abandonment only for the matching active game, with no refund. Terminal and malformed state cannot authorize abandonment; terminal state may start a new game. Leaving via other navigation never refunds a committed stake. Historical in-progress states without the envelope fail closed; no deployed migration is in scope.
- Dice actions are bet/pass/keep. The server stores the die and 1..3 roll count. Posted bet on subsequent actions, try, what and undeclared fields are rejected. All game value operations require POST/CSRF and a consumed state-bound intent. Return links come from the application-owned hook, restricted to forest/travel.
- Five/Six locks the installed module row and reads settings under row locks inside the player transaction. This serializes participating player jackpot writes, including absent-setting defaults. Jackpot, daily uses, stake and account updates roll back together. Two independently scheduled loopback servers prove serial-equivalent shared jackpot settlement for two actors, one daily debit each and no replay payment. General admin settings validation remains a separate core blocker. No universal exactly-once crash receipt is claimed.
- Bartender search/login SELECTs use bound values. Search/name lengths are bounded. Paid GET renders confirmation; POST costs the historical 100 gold, checks funds and target inside the account transaction, and consumes a one-use intent. Existing LoGD stat/story output remains formatted; action attributes are escaped. The broader settings/object-preference editor remains a separate blocker.
- New HTTP tests explicitly issue otherwise unavailable navigation to test business authorization, rather than relying on allowednavs. They exercise duplicate requests, wrong game, abandonment, finite Dice progression, forged parameters, daily quota, paid information, malformed persisted state, and actual temporary CHECK constraints that fail final settlement writes (fixture DDL occurs outside the application transaction).

Remaining meaningful GET families: Crazy Audrey Village, Lovers, race/specialty onboarding and combat, petition administration, clans, equipment, stables, training, PvP and unreviewed editors. Bank/mail enforced method, CSRF, replay and transaction closure remains outstanding. The prior Dark Horse/Dice/Five-Six GET findings have been replaced in code; their new tests pass on both supported targets.

The token-based shipped-source rescan still finds 55 ScalarState reads, 31 serialization writers/checks and one actual unserialize across 41 files; active shipped eval remains zero. Remaining GET examples were reconfirmed in weapons, armor, clan membership/rank, petition status, stables, training/PvP, Audrey Village and Lovers. This is a meaningful-family rescan, not a claim that every legacy route has been certified. Session/DB crash boundaries for non-game paid information and other scoped action intents are still documented recovery limitations; no universal exactly-once subsystem was added.

Final-run correction: Goldmine's no-mount death branch now initializes its mount-death outcome flag. A seeded actual-callback regression covers death, HP, event completion and configured currency/experience effects. This fixes the intermittent error observed in the existing HTTP Forest test; the whole module stays BLOCKED for its other configured/mount/editor gates.

## Dag/Drinks/Cedrik continuation (2026-09-14, verified)

The published continuation begins at `150f7a09a1927ff1b32f6802a41282785a25369b`. The implementation passes both supported targets at `d575acfcf7631201b0f195f0fe10be11fe0d63ec`: Modern core 34792299180 and Baseline integrity 34792299186. Dag and Drinks are individually PASS; the core verdict remains NOT READY.

| Route | Authentication/authorization | Input/SQL | Method/CSRF/replay | Output/failure semantics | Evidence |
|---|---|---|---|---|---|
| pvp.php entry/round/result and Dag pvpwin | Authenticated live actor; bounded nonself existing eligible target; server location, immunity, availability and daily fights; account-owned encounter/target/reservation | Bound target lock/read/write; minimum single-target PvP business schema | GET confirms; POST/CSRF plus consumed entry/round intent; round tied to stored state; terminal combat cleared | Battle, actor/victim, bounty close/credit, news, debug and in-game mail in one transaction; external email deferred until commit | New funded real HTTP, role/target rejection and four failure points; PASS on both targets |
| Dag administrator place/close/cleanup/list | Handler SU_EDIT_USERS, independently of navigation | Typed bounded IDs/amount; finite sort/filter; bound values | POST/CSRF; one-use placement, close and cleanup intents; conditional close | Funded overview missing location/grouping defaults repaired; unknown/deleted record handled | New anonymous/ordinary/insufficient/authorized HTTP matrix; PASS on both targets |
| Drinks create/edit/activate/deactivate/delete | Explicit SU_EDIT_USERS or stored delegated canedit; forged player-pref path remains denied | Existing DrinkInput allowlist; create defaults; absent edit record 404; bound DML | Existing POST/CSRF and one-use forms exercised for each action, including revocation and replay | Raw UTF-8/apostrophe/backslash storage and escaped form/list values | New role/CRUD/extreme-value HTTP matrix; configured limits and New Day service/callback assertions; PASS on both targets |
| Cedrik configured/random prices | Active module and existing server effect availability | Known configured prices 1..10; typed random minimum/maximum/current cost; invalid ranges rejected | Existing one-use POST/CSRF retained; offered gems and server price determine whole doses | Historical reset potions charge one dose; remainder handling retained | New fixed/random/bounds/funds/max-offer/replay HTTP cases; PASS on both targets |

`systemmail()` now binds its database reads/write and queues the PvP notification portion until after commit. This is **partial helper closure**, not certification of player mail send/reply, all callers, sender privileges, length limits or translated-array business schemas. External notification acceptance/failure remains separate from committed game state.

Transmutation sickness is measured in **combat rounds**, with optional `survivenewday`; it has no historical day countdown. Actual New Day/onboarding/combat carry/expiration coverage and the typed settings editor remain Cedrik gates. No day-based effect or pricing rebalance is introduced.

### Current remaining GET and replay inventory (2026-09-14)

The preceding historical GET lists are superseded here for `pvp.php`: entry, rounds and results now require POST/CSRF and consumed intents. GET offers confirmation and does not fund or settle combat. This closes that method boundary, not the full core PvP family.

A fresh source rescan confirms meaningful unresolved GET mutations in `modules/crazyaudrey.php` pet/play, `modules/lovers/*` conversation effects, race/specialty selection and specialty combat, `viewpetition.php` status/cleanup, clan membership/rank/removal, `weapons.php` and `armor.php` buy, stables purchase/sale, `train.php` challenge/victory and legacy editors. The training `victory` parameter needs server/capability authority independently of its developer navigation link. Bank uses `abs()` on submitted amounts and still needs strict positive amounts, locked atomic movement and rollback. Mail send/reply still needs enforced ownership/sender/recipient, CSRF and replay closure. No harmful remaining GET action is accepted as justified navigation.

Dag's new close/cleanup and PvP mutations have duplicate tests. Drinks CRUD and Cedrik configured purchases retain one-use intents and duplicate assertions. Untouched families above remain replay risks. Short-lived session intents do not claim universal durable exactly-once delivery across a crash between database and session persistence.

Full PvP certification still requires defeat/result/inn-bodyguard and broader actor/combat-state HTTP cases. The minimum PvP schema does not close Forest/training combat or all optional nested combat fields. Mail helper binding does not close all `systemmail()` caller trust models. Core settings/object preferences, representative editors, other serialized schemas, expiration completion ordering, account deletion and optimization failure reporting remain merge blockers as recorded above.

### Verified closure decision

Implementation `d575acfcf7631201b0f195f0fe10be11fe0d63ec`: Modern core 34792299180 SUCCESS (PHP 8.4/MariaDB job 103818653164; PHP 8.5/MySQL job 103818653066); Baseline integrity 34792299186 SUCCESS. Each target: 67 PHPUnit / 1,692 assertions / 20 Python tests / 302 PHP lint / zero skips or lint failures. Legacy PHPStan level 0 retains seven findings with no new errors; infrastructure level 6 has zero errors and no baseline. Composer strict validation, locked install and audit pass.

`test_dag_funded_pvp_and_failure_rollback` proves a mature 250-gold bounty pays from stored state once, with own/delayed/closed exclusions, one daily fight consumed, forged target/amount ignored, invalid actor/target denied and four real database failure points. Bounty close, bounty news, in-game mail and final winner-credit constraint failures each restore the full relevant snapshot; removing the final constraint and issuing a fresh intent retries the preserved state without fixture resets. Repeated failed or settled intents cannot pay again. Fixture DDL is outside the tested DML transaction.

`test_dag_administrator_http_matrix` covers actual authorized place/close/cleanup/list/search and denied roles/GET/CSRF/replay/malformed/nonexistent records. Literal Unicode, apostrophe, backslash, percent, underscore and escape-symbol target search works without SQL interpolation or raw markup. `test_drinks_editor_delegation_create_activation_delete` covers real CRUD/delegation/revocation/extremes, supplemented by actual configured purchase and New Day callback tests. These close Dag and Drinks in their supported bundled scope. `test_potions_configured_prices_and_random_bounds` closes Cedrik pricing only.

Module counts: 8 PASS, zero documented-limitations, 16 BLOCKED. All 24 lifecycle PASS. PR #1 remains OPEN/DRAFT/unmerged. Modern-core NOT READY; public hosting NO. No disabled surface is restored, and no deployment or Phase 4 work is authorized by these component results.

## Cedrik / shared editor / Dark Horse continuation (2026-09-14)

Current shared settings and enabled mount object preferences are PASS for the exact exercised contract. The broader user/content/mount CRUD/module-manager editor matrix remains BLOCKED; these callers do not inherit certification. Goldmine gameplay and all other untested configured outcomes remain open.

Cedrik's purchase commits exact account/buff/pref/debug changes together. Transmutation state is business-validated at hydration; sickness counts used combat rounds, carries only when its stored flag says so, and is stripped by the shipped Dragon reset. Vitality carrydk is tested through actual Dragon processing. Persistence tests exercise existing New Day, Forest combat and Dragon endpoints; they do not certify those endpoints' authorization, GET/replay or complete transaction contracts. Those core families remain BLOCKED/PARTIAL.

Dark Horse mounted entry and event exits require POST/CSRF intents. Forest discovery reserves the server-selected encounter identity, as in the established event dispatcher; GET does not charge, reward, abandon, enter by mount, or exit. Existing wager and information settlement guarantees remain unchanged. An ended encounter cannot be recreated by replaying its exit request.

Settings use strict submitted values, known namespaces and declared keys; absent declared values use descriptor fallbacks. Historical core false defaults stored as empty strings are interpreted as false only when validating stored state, never accepted as boolean POST values. Integer-or-percent fields retain that existing vocabulary. Disabled payments/LoGDnet controls are excluded. Live clock labels no longer define editor intent identity. A module must still declare a supported type to be writable.

Multi-write failure tests reject the second settings/preference write after preceding DML, or Cedrik's final account write after its debug log. Transactional snapshots roll back, failed intents do not replay, and fresh intents retry. Shipped settings callbacks are DML-only in this tested scope; arbitrary callbacks, DDL and external effects are not certified transactional. Session intents still do not provide universal crash-safe exactly-once delivery.

Remaining meaningful GET families include Audrey Village, Lovers, race/specialty onboarding, general Forest/Dragon/training combat and Dragon reset, petition administration, clans, equipment/stables, training and legacy editors. Full PvP, bank/mail enforced action boundaries, remaining serialized schemas and cleanup/deletion failures remain open. No such harmful GET is relabeled as navigation.


## Remaining effects closure (2026-09-14)

The current table supersedes earlier Audrey/Fairy/Glowing Stream/Outhouse/Seth blockers. All five supported module scopes now pass their named effect and action gates; this does not close broader core combat, editors or cleanup. Audrey adds a paidvisit preference and atomic paid petting plus daily basket authority, with real final-write rollback/retry. Outhouse and Seth validate configured values and daily state and bind intents to account/game day. Fairy validates its accumulated extra HP and declared awards before rewards and recalculation. The random test helper is temporary trusted test source registered only in disposable databases, with no production random override.

Current unresolved meaningful GET families after rescan: Lovers conversation/effects; race/specialty selection and specialty combat; petition status/cleanup; clan membership/rank/removal; equipment buy; stable purchase/sale; training challenge/advancement; legacy editor mutations. Core PvP entry/round/result POST protection remains retained, not an unresolved GET claim. Bank and player mail still need enforced amount/ownership/CSRF/replay/transaction contracts. Session action intents do not provide universal crash-safe exactly-once delivery. Goldmine still needs its complete deterministic configured/mount/tether/racial matrix.

The existing systemmail database binding and deferred PvP notification work remains PARTIAL. Sender/caller trust, recipient/content bounds, send/reply ownership and translation business schemas remain unclosed. General combat, mounts/companions/buffs/preferences/editor/navigation schemas remain unclosed. Expiration still moves its marker before cleanup and logs deletion before final account deletion; deletion hooks and related DML still lack complete tested failure/retry semantics. No cleanup, optimization or external-notification improvement is claimed here.

Modern-core merge: NO. Public hosting: NO. PR #1 remains OPEN/DRAFT/unmerged. Next: continue Phase 3 with Goldmine and Lovers, then four race and three specialty authority gates, followed by the listed core families.


## Goldmine closure, 2026-09-14

| Boundary | Authentication/authorization | Input and SQL | Method, CSRF and replay | Output / status / proof |
|---|---|---|---|---|
| Forest Goldmine event | Authenticated actor; installed active module; server current event; locked account recheck | Typed declared settings/preferences; bound mount/configuration/race reads; settings, mount and rescue state bound to intent and locked before mutation | GET read-only; POST/CSRF; one-use event intent; stale context and duplicate rejection; completed event cleared transactionally | Historical text and color formatting retained; HTML output escape tested; PASS in supported bundled scope via `test_goldmine_deterministic_http_authority` |
| Secured event dispatch failure | Existing shared authorization retained; inactive event rejected before state clearing | Shared account transaction rolls back account, news and debug writes | Failed intent stays consumed; new form permits retry after rollback | Controlled 500 instead of uncaught PHP error; no falsely reported success; inactive request preserves pending event |

Goldmine deterministic HTTP covers all 20 mining rolls, configured currency-loss endpoints, four bundled race rescues, no-mount and real mount tether/auto-tether/survive/die/save paths, malformed and stale configuration/preferences/mount state, CSRF, anonymous/inactive access, replay, failure injection and fresh retry. Both supported targets PASS in Modern core 34837310102 at `58b9badd48aabb64eb572097439a7242ccb75b66`.

The post-change source rescan still finds meaningful GET mutations in Lovers (`modules/lovers/lovers_seth.php`, `lovers_violet.php`), race and specialty onboarding (`lib/newday/setrace.php`, `setspecialty.php`), specialty combat (`apply-specialty` hooks with `skill`/`l`), petition administration, clan application/membership, weapons/armor purchase, stables, training and legacy editors. These remain blockers, not accepted navigation exceptions. General combat and remaining core replay/business-schema gates are unchanged. No universal crash-safe exactly-once claim is made.


## Lovers alternative-choice boundary (PASS on both supported targets)

The pre-change state is a daily `seenlover` boolean, not a dialogue-stage counter. Sex selects Violet or Seth. `marriedto=INT_MAX` selects a single randomized NPC-married interaction; otherwise `flirt=1..7` are seven alternative choices. Choices 1..6 retain charm-dependent outcomes/caps; choice 6 retains two-turn exhaustion with zero floor and news; choice 7 retains charm >=22 marriage/buff/news or failed-proposal turn loss/debug. Chat only describes appearance or conversation and changes no player state.

GET entry is now read-only, including the formerly mutating married entry and absent default preference row. The authenticated, active module requires a living player outside another pending event and an unconsumed valid daily flag. POST uses CSRF and a one-use intent bound to account, game-day, sex, married state, charm, turns, HP, pending event, location and stored buffs. Typed action/choice and unknown/repeated form-field rejection prevent supplied paths/effects. The existing player transaction locks the account and rechecks module/daily state before applying historical story code. Bound preference/account/news/debug writes commit together. On failure the shared layer restores player/output/buff state and clears preference caches; the consumed intent requires a fresh form. This is not universal crash-safe exactly-once delivery.

`test_lovers_alternative_choices_daily_authority_http` follows the actual Inn link and rendered form for valid actions. Fixture navigation authorization is used only to reach rejection boundaries independently of forced navigation. It covers all seven choices on both NPC paths, positive/no-effect/negative outcomes, charm caps, exhaustion floors, married +/- charm/buff, malformed/missing/duplicate choices, forged path and effect fields, inactive/anonymous/ineligible/exhausted access, CSRF, stale/replayed forms, database persistence and subsequent login, actual New Day, NPC marriage attrition/divorce, three final-account rollback scenarios, fresh retry, and configured apostrophe/backslash/UTF-8/HTML-like text with LoGD formatting retained.

No meaningful Lovers effect GET remains. Race/specialty onboarding and specialty combat, petition administration, clans, equipment, stables, training and legacy editors remain unresolved. Other core blockers and serialized-state inventory are unchanged. Both supported targets pass at `72a43cc0cfd4e6e653a943a5377d7adbfc4139db`: Modern core 34841113158, Baseline integrity 34841112989. Lovers is promoted independently to PASS.


## Shared race onboarding boundary (PASS on both supported targets)

All four bundled race modules use one `newday.php` POST boundary. The server resolves Human/Elf/Dwarf/Troll from the active installed bundled modules, rejects request module/path/location/stat fields, and stores race plus the configured main village in one account UPDATE. Cities is absent; no race-specific Cities location, home-city preference or archive dependency is invented. Existing descriptions and formulas remain. A missing active race no longer silently assigns Human through GET; it displays an unavailable message until a race is activated.

The intent binds account, unselected race, specialty, game day, age, dragon-kill/point state, pending event, location, alive state, available module choices and configured village. The transaction locks/rechecks the account, module registry and village setting. Already-selected players and stale forms cannot use this endpoint to reach normal New Day mutations. Explicit `setrace` GET requests are rejected before New Day logic. Legitimate selection after transmutation is retained and its existing persistence test now submits the real form.

Actual HTTP tests prove each race independently: displayed form, valid POST, exact race/location persistence, no supplied stat changes, anonymous denial, CSRF, inactive choice removal, forged race/module/filename/location/stat rejection, reselection/replay, stale account/form state, no-active-race read-only display, GET rejection, and account CHECK failure with unchanged race/location and fresh retry. A valid form POST traverses the normal forced-navigation filter. The race mutation is one atomic account write; no multi-table Cities transaction is claimed. Retained unit coverage verifies Human's daily fight bonus, Elf/Troll stat/PvP modifiers, Dwarf's gold modifier and no-Cities location semantics.

Remaining meaningful GET families after this change: specialty onboarding/combat, petition administration, clans, weapons/armor, stables, training and legacy editors. General combat state, mail/systemmail, bank, broader PvP, administrator/business schemas and cleanup/deletion remain blockers. The `ScalarState` reader and inventory are unchanged. The specialty review confirms the next work must address `lib/newday/setspecialty.php`, `battle.php`, `lib/battle-skills.php`, `lib/fightnav.php` and the three specialty hooks together, including stored uses and server combat state; no specialty certification is claimed here.

The focused New Day rescan also confirms a separate unresolved core gate: `newday.php?dk=...` can allocate a dragon point through GET before onboarding dispatch. This is not a race selection; it is not covered or certified by the race POST boundary. The existing point-allocation and general New Day authority require their own closure. Requests containing `setrace` in GET are rejected before that logic.

Race acceptance: all four independently PASS on PHP 8.4/MariaDB 11.4 and PHP 8.5/MySQL 8.4 at `77c58792f87afad71c0db5fc77042cd39ab418b8`; Modern core 34842503298, Baseline integrity 34842503326. The final three specialty certifications remain BLOCKED, including onboarding, combat levels 1/2/3/5, insufficient uses, wrong specialty, absent/invalid/terminal combat, replay and exact persisted consumption at the real HTTP boundary. Retained hook-level regression is not route certification. General combat schema is unchanged and BLOCKED. Modern-core merge remains NO; public hosting remains NO.

## Shared specialty onboarding continuation

| Boundary | Evidence / status |
|---|---|
| Authentication / authorization | Authenticated account; race chosen, specialty empty, no pending event or unspent dragon points; exact active installed present bundled choice |
| Input / SQL | Typed DA/MP/TS; reject extra request fields and malformed stored skill/uses; bound account, registry and preference queries |
| Method / CSRF / replay | POST, existing CSRF and one-use state-bound intent; explicit choice GET rejected before New Day; stale/reselected forms rejected |
| Persistence / rollback | Existing player transaction with account/registry/preference locks; late account-write CHECK proves preference/account rollback; fresh-form retry |
| Output | Historical translated story; escaped choice label/form attributes |
| Tests | `test_specialty_onboarding_http_authority`; adapted real first-login onboarding; retained race authority and hook/formula regressions |
| Status | Onboarding PASS on both supported targets at `82bb591d5355ccd0e5a071f1d4fd3efd7033c7ba`. Whole specialties remain BLOCKED for combat |

Current meaningful GET blockers: specialty combat, New Day dragon-point allocation (both single `dk` and bulk `pdk` paths require review), petition administration, clans, weapons/armor, stables, training and legacy editors. Specialty onboarding GET no longer assigns a specialty or a fallback. General combat and remaining core mail/economy/editor/schema/cleanup gates remain BLOCKED. Full source flow and unchanged formulas: [SPECIALTY-AUTHORITY.md](SPECIALTY-AUTHORITY.md).

Both supported targets PASS at 82bb591d5355ccd0e5a071f1d4fd3efd7033c7ba: 74 PHPUnit tests / 2231 assertions / 33 Python and HTTP tests / 310 PHP files linted; zero failures/skips. Modern core 34886260212 and Baseline integrity 34886260167 SUCCESS. Jobs: 104117559745 (PHP 8.4.25 / MariaDB 11.4.13) and 104117559301 (PHP 8.5.10 / MySQL 8.4.11). Both logs explicitly checked out the implementation SHA.


## Dark Arts companion business-state implementation, 2026-09-14 continuation

Starting SHA: `e70429e22d7c0c47570db998e609677c5dcfa2a2`. `SkeletonCompanionState` validates the named skeleton above unchanged ScalarState: exact required fields/text/abilities/ignorelimit, only optional boolean used/suspended, finite positive bounded statistics, current HP at most max HP, and attack/defense/max HP consistent with the historical creation formula. Creation level is recovered from max HP, preserving companions across later player training. No lifetime counter, death immunity, extra modifier, arbitrary ability or nested extension is accepted. Half-point combat statistics remain unchanged. Other named companion arrays are NOT thereby business-certified.

Common hydration rejects malformed encoded maps and skeleton state with a controlled 409 before route gameplay. It preserves the stored blob for explicit repair, with no silent deletion or normalization. The apply_companion producer/fallback also validates skeleton state. Runtime death/removal, combat victory retention, New Day retention and Dragon clearing retain their historical implementation; complete HTTP lifecycle/rollback/replay authority is still pending.

Added SkeletonCompanionStateTest and test_darkarts_companion_business_state_http. The HTTP fixture creates a real skeleton through the existing historical Forest action and checks exact cost 1 (5 to 4), formula statistics, used flag, subsequent login/read, injured/suspended states, malformed stored matrices and unchanged game state on rejected GET/POST. This does NOT certify the historical GET action, stale-form protection, general combat state, or transactional specialty use. CI acceptance pending at this implementation checkpoint. All three specialties remain BLOCKED; **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. Phase 3 incomplete, merge NO, hosting NO.


## Forest specialty transaction implementation

The next implementation adds `forest.php?op=specialty`, shared by DA/MP/TS. GET renders forms, while legacy Forest `skill`/`l` URLs reject before a round. POST requires CSRF and the existing one-use intent bound to player/combat/buff/companion/specialty preference state. The transaction rechecks installed active bundled module, selected identity, strict stored skill/uses, supported level 1/2/3/5 and live Forest state; the request supplies only the level. Existing effect hooks receive the server-resolved identity. Forest round and victory/defeat processing share the existing player transaction. Defeat gains an optional non-flushing mode so the callback can commit before page_footer. Terminal state is cleared inside this specialty transaction. Failed consumed intents require a new form.

`SpecialtyCombatState` is a narrow Forest specialty validator, NOT the general combat schema. It checks exact root/options/enemy key vocabularies, creature identity/name/weapon, bounded HP/attack/defense/level/rewards/player-start HP, finite flags, supported target and reward-map structure. It currently rejects encounters containing dead enemies, rather than claiming multi-target progression certification. Other core combat routes still need their real consumer schemas. No new field/formula/reward value is introduced.

HTTP tests added for all twelve Forest specialty actions: exact use cost and persisted combat/account read, duplicate/replay, late account-write failure after uses and fresh retry; plus stored preference, selected/inactive specialty, level, injected effects, CSRF, combat/target/stale state, anonymous and GET matrices. Exact effect/companion lifecycle/New Day after use and Dragon/other eligible route closure remain incomplete. No specialty promotion. CI pending for this implementation; retained earlier evidence is not represented as acceptance of this new tree. Phase 3 INCOMPLETE, module counts 21/0/3, merge NO, hosting NO.

### Continuation GET rescan and scope limits

Re-read active shipped code after the Forest change. Forest specialty URL GET is a read-only form and legacy Forest skill/l GET rejects; ordinary Forest fight/search/run/target changes are still legacy and are NOT closed. The specialty GET family cannot be removed globally: Dragon retains battle skill dispatch and `op=prologue1` reset, including GET flawless input. PvP forbids specialties by its existing rules, and its existing transaction is unchanged.

New Day still reads `dk` at line 42 and `pdk` at line 82. viewpetition still has GET-driven delete-old and status update paths. Clan operations delegated from clan.php, equipment buy routes in weapons.php/armor.php, stables actions, train.php victory/master paths, and legacy content editors retain unclosed mutation/replay authority. Bank still uses absolute-value coercion for submitted amounts; mail send/reply/systemmail and petition/clan/account-deletion semantics remain unclosed. Expiration still saves last_char_expire at line 11 of lib/expire_chars.php before cleanup. None is certified by the Forest specialty change.

Direct battle.php HTTP requests are rejected with 404 before bootstrap, preference writes or battle execution. It is a caller-owned include and no shipped navigation points to it. Added authenticated/anonymous GET/POST tests check unchanged player/specialty/combat state. Forest/Dragon/PvP include behavior is retained. This blocks the direct include bypass; it does not certify the remaining caller routes.


## 2026-09-14: specialty availability and non-exposing callers

`lib/specialty_combat.php` requires exactly one unconditional callable selected apply-specialties hook, with its row locked in the mutation transaction. Missing/conditional hooks cannot silently advance a specialty round. All twelve levels have missing-module/file/inactive/invalid-handler/missing-hook/conditional-hook and stale-availability HTTP evidence.

`train.php` and `graveyard.php` reject GET or POST skill/l presence, including arrays, before bootstrap. Both disable specialty navigation. Authenticated/anonymous HTTP matrices cover all three identities and levels 1/2/3/5, including independently supplied skill or level. This closes specialty injection only; general training/graveyard combat remains uncertified. Existing PvP specialty rejection is unchanged.

Dragon still exposes legacy specialty links and prologue1 resets via GET. Ordinary Forest fight/run/target, New Day dk/pdk, petitions, clans, equipment, stables, training and legacy editors remain meaningful GET/replay risks. [Accounting and complete caller inventory](SPECIALTY-COMBAT-ACCOUNTING.md).


## 2026-09-15: adverse specialty accounting and buff business schema

Continuation from `37bc7d2af907d8ae3b512d51fc0d583e17d627a3`.
`SpecialtyBuffState` validates the finite DA/MP/TS identifiers, exact producer
messages/modifiers/schema, numeric creation fields, positive remaining duration,
and actual engine flags. Authoritative hydration rejects malformed collections
with HTTP 409 before route gameplay, preserving the stored blob for explicit
repair. Buff application and field calculation validate specialty shapes too.
Other named array buffs remain uncertified; this is not the general buff schema.
`ScalarState::read()`, `SkeletonCompanionState` and `SpecialtyCombatState` are unchanged.
No new serialization reader or writer: 89 sites / 44 files / 32 writers-checks /
56 ScalarState reads / one centralized unserialize remains the inventory.

New deterministic HTTP tests cover lethal riposte/retaliation, exact Forest
defeat settlement, shield simultaneous-lethal victory and mushroom recovery,
failed Lifetap, all persistent five-round specialty effects, terminal activation
phase boundaries, and malformed-buff rejection before specialty/ordinary rounds.
Historical costs, formulas, messages and death/victory behavior are unchanged.
See [adverse accounting and limits](SPECIALTY-ADVERSE-ACCOUNTING-20260915.md).

All three specialties remain BLOCKED: **21 PASS / 0 limitation / 3 BLOCKED**.
Dragon/prologue GET authority, dead-target progression, full multi-target terminal
semantics and remaining companion/area interactions are not closed. Ordinary
Forest mutation authority, general combat/rewards/defeat/recovery and the other
Phase 3 core gates remain BLOCKED. No 24/24 milestone, merge, hosting or Phase 4.
Exact-head CI acceptance is recorded in PR #1 after publication.

### Retained Transmutation error contract

Full run 34961216418 completed all 46 tests on both targets but failed the
existing Transmutation malformed-state assertion (expected 400, got 409).
All five new specialty HTTP tests passed. Hydration now calls the existing
TransmutationState validator before the shared collection check, preserving
its controlled 400 response for null/malformed potion entries. Specialty
corruption remains 409. Neither validator nor the retained test is weakened.
Local recheck passes 83 PHPUnit tests/2398 assertions and authentication,
Transmutation persistence/failure, and the specialty corruption matrix.
New final-head acceptance is required and recorded in PR #1.


## 2026-09-26: Dragon caller authority supersedes the earlier Dragon GET finding

`dragon.php` now delegates to `lib/dragon_combat.php`. Introduction, combat and prologue GET requests only offer forms. Begin, fight, specialty and continuation mutations require authentication, allowed server-owned state, POST, CSRF and scoped state-bound one-use intents. Developer God Mode/restart retain role checks. Old skill/level/flawless/auto/target query injection rejects. A unique encounter identity binds live combat and the stored victory marker; the current Dragon Kill count also binds continuation. Battle, preference/account writes, companions/buffs, outcome news and the later reset/reward hooks are covered by caller-owned transactions. The reset preserves `authversion`. See [the full route/hook inventory and test boundaries](DRAGON-AUTHORITY-20260926.md).

This closes the implementation of the named Dragon GET mutations, subject to the final exact-head HTTP acceptance recorded in PR #1. It does not certify all companion/buff/combined-effect gameplay. The focused dead-A/live-B validator and TS2 test do not authorize ordinary Forest GET fight/run/target routes. Other meaningful GET/replay findings remain: ordinary Forest, New Day dk/pdk, petitions, clans, equipment, stables, training and legacy content editors. Broader rewards/defeat, PvP, mail and account-deletion/expiration remain open.


## 2026-09-26: Recovery acceptance and additional Thieving Skills obligations

The recovered Dragon authority implementation at `4bd0569e43567f29908434826c9d5dc23ad47460` is now accepted by exact-head Modern core 36271747607 and Baseline integrity 36271747672. The retained immediate Transmutation victory-to-prologue submission passes on both targets. New tests extend TS1/TS3/TS5 progression, stale target-A/B forms, terminal replay and exact settlement; a second matrix exercises combined Thieving Skills via real casts in both callers. Their separate final acceptance belongs to the ending SHA recorded in PR #1. No application route, authority check or ordinary Forest GET behavior changes in this continuation. Existing general-core GET/replay and merge blockers remain.


## 2026-09-27: recovery verified from published history

See [the recovery record](PHASE3-RECOVERY-20260927.md). Exact SHA `72ab600fdc8c959ec3240bdc5abaa72f39bd178e` is ACCEPTED by Modern core 36273713605 and Baseline integrity 36273713736. Both supported targets passed 88 PHPUnit tests / 2,462 assertions, 52 Python/tooling and HTTP tests, 321 PHP lint files, both PHPStan and all Composer gates, with no runtime-matrix failures or skips. The baseline-only job separately skips the database-dependent HTTP class. The Transmutation and authversion regressions remain accepted; Dwarf documentation agrees with JSON. All six recovered commits are retained. New specialty test candidate acceptance is recorded separately; no parent result is substituted for it.


### Accepted specialty continuation and independent decision

Exact candidate `2bfe8fc3c7cee4a8a8ac0c3647571a318d243634` passes Modern core **36285456622** and Baseline integrity **36285456697**. Both supported targets: 88 PHPUnit tests / 2,462 assertions, **55** Python/tooling and HTTP tests, 321 lint files, zero runtime-matrix failures/skips, all Composer and both PHPStan gates. All three new regression matrices pass. **Thieving Skills is PASS; totals are 22 PASS / 0 limitation / 2 BLOCKED, with 24 lifecycle PASS.** Dark Arts retains companion combat/fallback transition gaps; Mystical Powers retains area-effect progression and aura/companion gaps. Full independent reasoning and exact boundaries are in [the recovery record](PHASE3-RECOVERY-20260927.md). Application source, workflows and serialized readers/writers are unchanged. The 24/24 gate remains open; general combat development has not begun. Phase 3 incomplete, merge NO, hosting NO, PR #1 draft. The final documentation commit requires its own terminal exact-head acceptance recorded in PR #1.


## Dark Arts companion-only checkpoint, 2026-09-27

Starting accepted head `ea1f3563c14188f9e1f536785c71c5b699f332f0`. Five added
HTTP methods cover exact companion injury/death ordering in Forest and Dragon,
healthy/injured companion carry on player defeat, zero/negative target progression,
final settlement, Dragon pending victory and clearing, fallback minions, live
replacement, combined-effect natural expiration, rollback and replay. Historical
zero-HP retaliation and producer-setting semantics are preserved. No production
source or business-validator changes. The terminal test observer additionally
verifies cleanup. See [the complete bounded record](DARK-ARTS-COMPANION-20260927.md).

Local PHP 8.4.26 / MariaDB 11.4.13: 88 PHPUnit tests / 2,462 assertions and
authentication plus seven Dark Arts HTTP methods pass. Full suite collects
60 Python/tooling/HTTP tests; its two-runtime result is not inferred from the
focused run. Exact candidate SHA and workflow IDs/state are recorded in PR #1.
Until both exact-head workflows pass, Dark Arts remains BLOCKED for acceptance,
with no additional behavior gap identified. Accepted counts remain 22 PASS /
0 limitations / 2 BLOCKED; 24 lifecycle PASS. Mystical Powers is unchanged.
Next task is exact-candidate acceptance and documentation-only promotion to
23/0/1 if justified. Phase 3 INCOMPLETE; merge NO; public hosting NO; PR #1
OPEN/DRAFT/NOT READY/NOT MERGED. No general combat, Phase 4, VPS, deployment,
release or tag work. Stop at the bounded CI checkpoint.


## Accepted Dark Arts certification, 2026-09-27

Engineering candidate `be545112cd8dd7471a3d70e66bc884f211f549b6` is accepted.
Modern core **36306254008** and Baseline integrity **36306254020** are terminal
**SUCCESS**, inspected once without redispatch. Actual matrix job logs establish:

| Job | PHP | Database | PHPUnit | Assertions | Python/tooling/HTTP | PHP lint |
|---|---|---|---|---|---|---|
| 108583392551 | 8.4.26 | MariaDB 11.4.13 | 88 | 2,462 | 60 | 321 |
| 108583392420 | 8.5.11 | MySQL 8.4.11 | 88 | 2,462 | 60 | 321 |

Each target has zero failures and zero skips. Both legacy and infrastructure
PHPStan pass. Composer strict validation, locked install and security audit pass
with no vulnerability advisories. All 24 bundled lifecycle checks remain PASS.
The 60 Python tests comprise 51 HTTP and nine tooling tests.

**specialtydarkarts: PASS. Certification: 23 PASS / 0 PASS WITH DOCUMENTED
LIMITATION / 1 BLOCKED. specialtymysticpower is the sole blocked bundled module.**
The retained onboarding, all four levels/costs, Forest/Dragon authority, schemas,
adverse/defeat accounting, duration, New Day, malformed/stale state, unavailable
module/file/handler, encounter identity, stored victory and forged flawless
rejection evidence remains accepted. The five companion methods and retained
injured-carry/business-state cases pass on both targets, closing injury/death,
healthy/injured companion player-defeat carry, target progression/stale target,
final settlement, exact-zero Dragon companion death versus overkill, pending
victory/continuation/cleanup, fallback producer/coexistence, real combined effects,
natural expiration, terminal ordering, replay, rollback and fresh retry.
No supported Dark Arts behavior gap remains identified. See
[DARK-ARTS-COMPANION-20260927.md](DARK-ARTS-COMPANION-20260927.md) for exact tests and
historical ordering. This acceptance supersedes its pending-CI decision and the
earlier Dark Arts blocked checkpoint entries; prior checkpoint history is retained.

This closeout changes documentation only. Serialization inventory and trust-boundary
implementation are unchanged, so SERIALIZED-STATE-AUDIT.md requires no update.
Historical integrity PASS: annotated tag `historical-source-1.1.2` object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.
Historical repositories are unchanged. Main remains
`999cec6f9c655a840320d982d673bb863c68c2b2`.

Phase 3 **INCOMPLETE**; modern-core merge **NO**; public hosting **NO**.
PR #1 stays OPEN, DRAFT, NOT READY, NOT MERGED. No Mystical Powers development,
general combat, other subsystem, Phase 4, VPS, deployment, release or tag work.
Documentation-head SHA and its one-time workflow inspection are recorded in PR #1;
engineering acceptance stays tied to the candidate above. Stop after publication.
Next engineering task, in a separate execution: close Mystical Powers' remaining
Earth Fist/Lifetap/shield target progression and expiration, and aura/companion
ordering, then independently reassess its bundled certification.

## Mystical Powers supported-boundary candidate, 2026-09-27

Starting head `816e709e91a51edfa0d3801200f90550757897b6`. This candidate adds
six HTTP tests and evidence only, closing Earth Fist/Lifetap/shield target
progression, regeneration/aura transitions, exact expiration, companion ordering,
real combined effects, terminal outcomes, stale/replay rejection and late-write
rollback/fresh retry. Production code, schemas, dependencies and workflows do not
change. All 51 retained HTTP methods are AST-identical.

Candidate certification: **24 PASS / 0 limitations / 0 BLOCKED; all 24 lifecycle
PASS**. Exact-head supported-matrix acceptance remains **PENDING**, with last
accepted counts 23/0/1. See [the full evidence](MYSTICAL-POWERS-20260927.md).
Local floor: 88 PHPUnit / 2,462 assertions; focused HTTP and nine tooling tests;
321 lint files; both PHPStan gates and Composer pass. Full suite collects 66
Python/tooling/HTTP tests, not yet claimed as a completed supported-matrix run.

PR #1 will record the exact candidate SHA and bounded workflow inspection.
Phase 3 INCOMPLETE; merge NO; public hosting NO. PR remains OPEN, DRAFT, NOT READY,
NOT MERGED. Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.
Historical integrity: expected tag object, source/tree, 417 files and 11 commits
PASS. No VPS, release, deployment, general combat or another subsystem work.
Next task: exact-candidate CI acceptance; general combat requires a new execution.

## Ordinary Forest combat candidate, 2026-09-27

The ordinary Forest route now separates GET presentation from state-bound POST
search, attack, escape and cave discovery. CSRF, one-use intents and the existing
locked player transaction cover normal encounter creation/surprise, live rounds,
target progression and final victory/defeat settlement. Client target switching and
stat/reward/outcome overrides reject. GET automatic-fight links cannot advance a
round; configured multiple-round choices are explicit POST fields. The engine and
historical outcome formulas are unchanged. Pending events keep their established
owners through an explicit search-to-entry handoff, avoiding nested transactions.

**Ordinary authority: local evidence PASS; exact-candidate CI PENDING. Broader
general combat remains BLOCKED.** Six new HTTP matrices prove live/terminal state,
replay, rewards, rollback, malformed-state preservation and explicit repair. Retained
round tests submit the new forms without removing gameplay assertions. Direct
battle.php protection is retained. See [the route inventory and exact evidence](ORDINARY-FOREST-COMBAT-20260927.md).
PR #1 records the exact SHA, CI IDs/states and bounded stop. Module evidence remains
candidate 24/0/0, last recorded accepted 23/0/1. Phase 3 INCOMPLETE, merge NO, public
hosting NO. No other subsystem, deployment, VPS, release or Phase 4 work.
