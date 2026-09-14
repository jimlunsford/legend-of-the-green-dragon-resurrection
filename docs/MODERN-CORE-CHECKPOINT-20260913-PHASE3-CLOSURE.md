# Phase 3 security closure continuation, 2026-09-13

**Modern-core merge verdict: NOT READY. PR #1: OPEN, DRAFT, NOT MERGED. Public hosting: NO.**

This is verified progress toward the requested closure, not a declaration that closure is complete. The [original Phase 3 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE3.md) remains intact. No Phase 4, new modernization branch, PR #2, deployment or release was started.

## Published starting state and scope

- Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection
- Existing branch: `modernization/core-modernization`.
- Verified starting branch HEAD: `2568337ae710b7011eaa5b3f1908ed206b6b4de6`.
- Verified starting and unchanged `main`: `999cec6f9c655a840320d982d673bb863c68c2b2`.
- Existing PR: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/pull/1, open/draft/not merged at the start. It remains draft because the acceptance gates below are still open.
- Last implementation commit covered by this record: `5da77fc0aa2db4c845ef68813c4fb77d3372a834`. This evidence-document commit follows it without changing application code. The final published document-commit HEAD and its exact-head workflow IDs are recorded in PR #1 and the execution report.
- Historical annotated tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`; source commit `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`; root tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`; 417 files; 11 preservation commits. Verification passes unchanged.

## What this continuation proves

1. Every shipped compatibility deserialization call now uses `ScalarState::read()`. It prevents class restoration and rejects object-containing, cyclic, malformed, trailing, oversized and excessive-node data. The audit enumerates all 87 sites. It does not replace exact combat/mount/preference schemas.
2. Stones uses bounded JSON with an exact schema and conservation checks. Real HTTP tests play choose/bet/draw/settle, reject malicious serialized state and duplicate submissions, and verify result persistence. The surrounding Dark Horse wager-abandonment contract remains open, so Stones is not fully certified.
3. The shared player mutation transaction locks/rechecks the hydrated account, binds changed fields, includes related InnoDB writes and restores in-memory state/output on rollback. Tests prove successful retry, stale-balance rejection and rollback of negative, fractional or overflowing currency plus a related write. No rollback guarantee is claimed for DDL, external mail or arbitrary hooks.
4. Dag places eligible bounded bounties atomically with charge/count updates and claims mature open bounties once. Tests cover invalid/self/missing targets, bounds, quota, own/delayed bounty handling, claim replay and denied administrative privilege. Actual player POST/CSRF/GET/replay is covered. The actual funded PvP claim and complete administrator HTTP matrix remain open.
5. Drinks purchase uses active server item/price, funds, hard-drink quota, bounded drunkenness and one-use POST/CSRF. Its editor uses typed allowlisted fields, capability checks, bound SQL and protected mutations. Actual administrator save and raw apostrophe/backslash/UTF-8 round trips pass; duplicate save is rejected. Create/delete/activation and delegated editor matrices still need completion.
6. Cedrik's Charm, Vitality, Health, Forgetfulness and Transmutation effects are tested through real protected forms. Availability, quantity, fixed server cost, charge/effect persistence, malformed input and replay are covered. Existing daily/HP hooks remain green; configured/random-cost and full New Day route persistence are not promoted without evidence.
7. One shared current-event contract protects Crazy Audrey, Fairy, Find Gem, Find Gold, Foil Wench, Glowing Stream and Goldmine in the Forest. GET renders confirmation; POST requires CSRF and an event-generation intent. Reward and completion persist together. Foil Wench spends one gem and adds one skill exactly once; an unpaid action and malformed operation are tested.
8. Outhouse protects paid/free use and wash with one-use forms plus persistent visit stage. A newly obtained form cannot re-wash a completed visit. Seth's song becomes read-only on GET and rechecks its daily counter in the transaction on POST. Real route tests pass. Unexecuted configured/random outcome branches remain explicitly unclosed.
9. Player preferences no longer allow an appended `user_`/`check_` suffix to write an internal module preference. All namespace fields are validated before any write. The 24 bundled modules expose no player-editable pref descriptors; unsupported future descriptors fail closed. HTTP tests attempt to forge Drinks `canedit`, specialty `skill`, an undeclared key and a missing module and verify unchanged preference rows.
10. News/debug helper writes are bound, and translated administrator navigation headings no longer trigger PHP array-to-string errors. Existing disabled surfaces remain disabled.

## Explicit behavior corrections and compatibility limits

- Hard-drink daily quotas are enforced even where the previous branch could bypass them. Drunkenness is capped at the historical editor's 100-point upper bound.
- Outhouse's penalty cannot drive available gold below zero. Shared protected actions reject invalid gold/gem results rather than commit corruption. Prices, random ranges, race/specialty values and probabilities were not rebalanced.
- Shipped `.9` drink modifiers remain accepted as finite numeric text. Effect message bounds match their 255-character database columns.
- GET mutation links for changed routes lead to confirmation or are rejected; harmless navigation remains navigation. Developer forced-event GET triggers now reject mutation and need a protected form; their historical debug navigation is not certified functional.
- Old in-progress PHP-serialized Stones state is rejected; no production state migration or deployment occurred.
- Generic PHP serialization remains for historical scalar/array compatibility. Rejection of objects is not proof of valid business state at every caller.

## Regression evidence at the last implementation commit

| Gate | PHP 8.4 + MariaDB | PHP 8.5 + MySQL |
|---|---|---|
| PHPUnit | 60 tests, 1,551 assertions, zero skips | 60 tests, 1,551 assertions, zero skips |
| Python / actual HTTP | 14 tests, all pass, zero skips | 14 tests, all pass, zero skips |
| PHP lint | 295 files, zero failures | 295 files, zero failures |
| Legacy PHPStan | Level 0, seven retained findings, zero new errors | Same |
| New infrastructure PHPStan | Level 6, zero errors, no baseline | Same |
| Composer | Strict validate, locked install, dependency audit PASS | Same |
| Fresh install / auth / 24 combined modules / scheduler | PASS | PASS |
| Historical baseline | PASS: 417 files / 11 preservation commits | Same |

- Modern core: [34781821375](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34781821375), SUCCESS, implementation head `5da77fc0aa2db4c845ef68813c4fb77d3372a834`.
- Matrix job IDs: PHP 8.4/MariaDB `103790139313`; PHP 8.5/MySQL `103790139143`. Both completed successfully.
- Baseline integrity: [34781821371](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34781821371), SUCCESS at the same implementation head.
- Prior regression floor was 47 tests / 1,393 assertions / 13 Python tests / 282 linted files. No baseline regeneration or test disabling was used.
- Intermediate failed runs exposed a test-link HTML decoding mismatch, an absent-parameter regression in the Drinks editor, a Python assertion syntax error, translated-heading array conversion and rejection of the shipped `.9` modifier. These were corrected in history and the final implementation run passes. Earlier failures are not presented as successful evidence.
- The final evidence-only commit must also pass both workflows at its own exact head before this execution is considered checkpointed. Its run links belong in PR #1 to avoid a self-referential commit-SHA/document update cycle.

## Individual module decisions

**3 PASS; 0 PASS WITH DOCUMENTED LIMITATION; 21 BLOCKED. All 24 lifecycle PASS.**

Find Gem, Find Gold and Foil Wench are promoted for their proven supported Forest paths. The other 21 retain specific open security or execution gates. See the complete [module matrix](BUNDLED-MODULE-CERTIFICATION.md) and [JSON metadata/evidence](BUNDLED-MODULE-CERTIFICATION.json). A module's PASS does not close the independent core administrator, combat or public-hosting gates.

## Why PR #1 must not merge

The structured [route-security record](CORE-ROUTE-SECURITY.md) contains each family's authentication, authorization, typing, SQL, method, CSRF, replay, output and evidence result. Significant blockers remain:

1. Finish Dark Horse's paid-information SQL and active wager authority; Dice/Five-Six POST/CSRF/state/replay/jackpot transactions; actual funded Dag PvP claim and administrator matrix; Crazy Audrey Village and Lovers effects. Complete the remaining configured/outcome paths for Drinks, Cedrik, Fairy, Glowing Stream, Goldmine, Outhouse and Seth before promoting them.
2. Actual race onboarding and specialty/combat HTTP boundaries, including invalid/negative levels, available uses, duplicate actions and authoritative combat state.
3. Mail send/reply/recipient ownership and bounded message SQL; `systemmail()` trusted-call boundary. The helper is internal, but player-facing mail send calls it, so it is not certified trusted-only. Existing deletion/unread and intake protections are retained.
4. Petition administration still performs GET cleanup/status changes and uses unbound request IDs/status. Intake security is not administrator evidence.
5. Clan rank/removal lacks complete cross-clan and actor/rank authorization; request-derived rank and target SQL, MOTD/text and POST/CSRF remain open. The required anonymous/nonmember/member/officer/leader matrix is absent.
6. Bank transfer and deposit/withdraw/loan, weapons/armor purchase, mounts/stables, training/masters and PvP still need typed authoritative state, atomic movement, POST/CSRF and replay tests. These families were inspected and not falsely marked closed.
7. Full representative administrator/editor matrix remains unimplemented. General configuration/module editors still accept insufficiently constrained fields. Core preferences, output contexts, companion/mount and combat schemas are incomplete despite removal of object restoration.
8. `expire_chars.php` advances the completion marker before cleanup. Deletion hooks and later related-row/account writes have no encompassing failure transaction here, and expiration logs success before final account deletion. These are merge blockers. `OPTIMIZE TABLE` may implicitly commit; optimization cannot be represented as rollback-safe. Full failure/retry/manual recovery tests remain necessary.

## Exact continuation order

Continue Phase 3 on this same branch and PR. First close the shared Dark Horse wager/return state contract and Dice/Five-Six, then complete Dag's funded PvP/admin and the remaining bundled effect branches. Follow with race/specialty HTTP boundaries, mail/systemmail, petition administration, clans, economy/equipment/mounts/training/PvP, the full editor/output/preference matrix and cleanup/deletion failure semantics. Re-run both CI targets, update individual module evidence and only then reconsider readiness/normal-merge authorization. **Do not start Phase 4.**

## Boundaries preserved

No historical repository was written to. The historical tag, source/tree, file manifest and preservation history remain unchanged. The archive-only module repository was not imported. `vps1.phoenix233.com` was untouched. No deployment, VPS database operation, Nginx/PHP-FPM/systemd/firewall/DNS/TLS/backup change, public hostname or game runtime, release tag, GitHub Release, ZIP or deployment artifact was created. Tests used repository/CI environments and loopback HTTP only.

Public hosting remains NO independently of merge: modern recovery/email verification, abuse controls, trusted TLS/proxy/session configuration, internal-file protection, isolation, least privilege, restore testing, monitoring and independent review remain later gates. Old recovery remains unavailable with HTTP 410; no insecure fallback was restored.

## Implementation commits added before this evidence commit

| SHA | Change |
|---|---|
| `eb43ebccca5ac306f7a62f486153f29de474fe86` | Constrain historical scalar restoration and secure Stones state and one-use transactional actions |
| `062fc47bc8e1b57da72443180e070e2c3d35bd9b` | Protect Dag bounty transactions, Drinks purchases and editor, and Cedrik potion submissions |
| `8ceeb3c49047c5de64af005248b11fadd16ffa10` | Require current-event POST intents and atomic rewards for seven bundled Forest events |
| `356c45411cf6d4d85f1d186b660398806ef1785f` | Secure Outhouse visit stages and Seth song consumption with HTTP editor coverage |
| `5dc598340c155584c6dd47bb2cfa77af73604112` | Accept absent optional Drinks namespace and assert one-time specialty reward |
| `9df56c7ea7fcb15ae0c1451721c3ff5561dd1ae3` | Correct specialty reward assertion syntax |
| `2b59a5f979e4e52dada275633f3d9e6ac75a5610` | Render translated administrator navigation headings without array conversion |
| `3bc09518723f3a5a5ed64df93ad88ed000e74a8e` | Reject forged player preference namespaces before writes and test unpaid event actions |
| `7735f1a1f59de47314d0613cab33f93a99f11cfd` | Preserve shipped drink decimal modifiers and effect message lengths in typed editor |
| `5da77fc0aa2db4c845ef68813c4fb77d3372a834` | Roll back invalid currency results and related writes at the shared mutation boundary |

## Continuation 2: committed Dark Horse wagers and three game certifications

**Modern-core merge: NOT READY. PR #1 remains OPEN, DRAFT, NOT MERGED. Public hosting: NO. Continue Phase 3.**

This execution closes the first shared game-state cluster. It does not certify the remaining module, core/editor or cleanup families by implication. Earlier continuation history above is retained.

### Published history and preservation

- Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection
- Branch: `modernization/core-modernization`; existing PR #1 only.
- Starting branch SHA: `459236e70779817571254e554346cce5899cb855`, verified against the published branch and PR before editing.
- Ending implementation SHA: `1a6cd7340978e68fa43e7b2d015f4ca513d130ec`. This documentation-only checkpoint follows it. The final documentation-commit SHA and exact-head workflow IDs are recorded in PR #1 and the execution report because a commit cannot contain its own hash.
- Unchanged main: `999cec6f9c655a840320d982d673bb863c68c2b2`.
- Five implementation/test commits, followed by this documentation commit:

| Commit | Change |
|---|---|
| `522f1735d1fc90b7551ce0f50912169b0dd81e39` | Shared committed wagers; Stones/Dice/Five-Six state/action changes; oldman and paid bartender boundary; tests |
| `4b0e921218d882a98678fb94f02dd2b252e81b41` | Explicit class includes for procedural HTTP bootstrap; generic response after database rollback; rotated-CSRF fixture correction |
| `4028dbbf3ad1b1b9611aedc6775b4f66d81200eb` | Two-server jackpot test; persisted Dice-result validation; mounted tavern-entry check; rendered-nav assertion correction |
| `10e80dcb064ee7737b4f452b1b64d27cb4b888f6` | All-in Stones and final-write failure tests; internal event POST marker handling; fixture routing/day corrections |
| `1a6cd7340978e68fa43e7b2d015f4ca513d130ec` | Unique game generations; Five/Six New Day counter assertion; bounded color text; portable constraint-based failure injection |

All published commits extend the actual prior branch. No valid remote history was reset, squashed or rebased. GitHub's authenticated connector published matching local file trees because this workspace's Git transport had no push credentials; no user terminal relay was required.

Historical tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 preservation commits verify unchanged. No writes were made to `jimlunsford/lotgd` or `jimlunsford/lotgd-modules`. No archive import, VPS access, deployment, public runtime, new branch/PR, version tag, release or package occurred.

### Wager and route results

- A small shared JSON envelope records schema version, random 128-bit game ID, owning account, game, wager, finite stage, active flag, result, settlement flag and exact game data. It is bounded to 2 KiB and validates owner/key/type/state consistency. Every newly started game has a different intent context, even if all Five/Six dice and payouts repeat.
- Stones and Dice debit the positive affordable stake atomically on commitment. Settlement returns zero stakes for a loss, one for a tie, two for a win. Their net historical economics are unchanged. Draw/play does not require the committed money to remain in the wallet; the all-in Stones regression proves this.
- Oldman GET preserves active and terminal state. A live game offers resume or explicit POST/CSRF abandonment. Abandonment checks a matching active game and a consumed state intent, retains the forfeited wager record and refunds nothing. Empty, wrong-game, completed, repeated, foreign-owner and malformed state are rejected. Other navigation cannot refund committed risk. Old pre-envelope in-progress blobs fail closed; no live migration was attempted.
- Dice stores its die, 1..3 roll count and opponent result on the server. Finite bet/pass/keep actions reject request-carried try/what/result and wager changes. The historical opponent stopping policy is preserved, including the different tie rules on its first and second rolls. Settlement and its debug/account writes occur in the player transaction.
- Five/Six rechecks daily uses and balance and reads authoritative settings while holding the installed module row and setting-row locks. Its contribution, cap, 5/10/100-percent awards and 100-gold full-jackpot reset remain unchanged. Invalid negative/overflow configuration fails closed. The valid supported maximum must accommodate the historical 100-gold reset. Account, daily-use, jackpot and winner/news writes share the transaction.
- Two actual PHP loopback servers concurrently execute Five/Six for different authenticated actors. Both database targets prove results equivalent to one valid serial ordering, correct per-player gold and daily use, no lost shared pot update and no replay payout. Deterministic unit branches prove each payout formula and cap. The real bundled New Day hook resets playstoday.
- Bartender search/login queries are bound; names/search/text are typed and bounded. Paid GET displays a confirmation. POST/CSRF with a consumed intent charges the original 100 gold only for an existing unlocked target and sufficient funds, under the actor transaction. HTTP tests prove missing-CSRF, replay and forged cost rejection, insufficient funds, missing target and quote/backslash/UTF-8 search handling. LoGD stat/story formatting and escaped action attributes remain distinct.
- Mounted direct entry now checks the actual mount's tavern ability, and the tavern event remains set while inside. Its complete positive/negative HTTP matrix and settings/object-preference editor remain unproven, so **Dark Horse itself remains BLOCKED**, despite its closed wager and paid-information components.

Actual settlement failure injection covers **Stones, Dice and Five/Six** at the account UPDATE after gameplay/related DML. A temporary CHECK constraint is installed as fixture DDL before the HTTP request, rejects terminal game state, and is removed afterward. The tests assert the account/game state remains unchanged, Five/Six jackpot and daily-use writes roll back, and a freshly issued retry succeeds. DDL is never claimed to participate in the application rollback.

### Final module decisions

**6 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 18 BLOCKED. All 24 lifecycle PASS.**

| Module | Decision | Exact remaining module gate, if blocked |
|---|---|---|
| findgem | PASS | None in supported bundled scope |
| findgold | PASS | None in supported bundled gameplay; general settings editor remains a core gate |
| foilwench | PASS | None in supported bundled scope |
| game_stones | PASS, promoted | Shared wager/abandonment blocker closed; full game and failure/replay evidence pass |
| game_dice | PASS, promoted | Server state, method/CSRF, finite progression, payout and rollback/replay evidence pass |
| game_fivesix | PASS, promoted | Shared jackpot concurrency, formulas, daily state, method/CSRF and rollback/replay evidence pass; general settings editor remains core scope |
| darkhorse | BLOCKED | Mounted entry HTTP matrix; shared settings/object-preference editor closure |
| dag | BLOCKED | Funded actual PvP claim; full authorized/denied admin place/close/cleanup HTTP matrix; bounty-specific write failure injection |
| drinks | BLOCKED | Editor create/delete/activation; delegated canedit matrix; configured hard-limit/drunkenness/reset boundaries |
| cedrikspotions | BLOCKED | Configured/random costs and bounds; actual transmutation New Day persistence; settings editor |
| crazyaudrey | BLOCKED | Village paid play/pet remains a GET mutation; daily/replay/effect closure |
| lovers | BLOCKED | Conversation/effect POST/CSRF, daily stage/visits, replay and reset |
| fairy | BLOCKED | Material HP/max-HP/turn/specialty/no-reward/configured/carry branches |
| glowingstream | BLOCKED | All value-changing outcomes and configured values; settings editor |
| goldmine | BLOCKED | Alternate gold/gem/death and mount/tether outcomes; object prefs and configured bounds |
| outhouse | BLOCKED | Wash/no-reward/no-wash/gem/turn outcomes and configured probability/cost/editor boundaries |
| sethsong | BLOCKED | All HP/gold/gem effects, fresh-intent visit exhaustion, configured values/reset/editor evidence |
| racehuman | BLOCKED | Actual valid/invalid/duplicate HTTP onboarding and persistence |
| raceelf | BLOCKED | Actual valid/invalid/duplicate HTTP onboarding and persistence |
| racedwarf | BLOCKED | Actual valid/invalid/duplicate HTTP onboarding and persistence |
| racetroll | BLOCKED | Actual valid/invalid/duplicate HTTP onboarding and persistence |
| specialtydarkarts | BLOCKED | HTTP onboarding and actual combat/session/uses/POST/CSRF/replay authority |
| specialtymysticpower | BLOCKED | Same specialty onboarding/combat gates |
| specialtythiefskills | BLOCKED | Same specialty onboarding/combat gates |

No other module's prior unexecuted gates were relabeled as complete. The Markdown and JSON matrices agree.

### Remaining core and cleanup decisions

| Requested family | Result in this continuation |
|---|---|
| Dag funded PvP / admin | No new closure beyond existing service/player placement evidence; gates above remain |
| Race and specialty onboarding | Not changed; actual HTTP authorization/selection/persistence matrices remain |
| Specialty combat and combat-state schema | Not changed; current combat authority, remaining uses, replay and exact badguy schema remain |
| Mail send / reply / systemmail | Not changed; recipient/reply ownership, sender semantics, lengths, bound helper/caller audit and protected send remain |
| Petition administration | Not changed; SU_EDIT_PETITIONS matrix, typed IDs/status, protected cleanup/status/note/delete and output remain |
| Clans | Not changed; complete role/rank/cross-clan/self-target authorization, bound membership/text writes, POST/CSRF/replay/atomicity remain |
| Bank/economy | Not changed; positive bounded amounts, locked balances, transfer ownership/debit/credit and failure rollback remain |
| Weapons / armor | Not changed; typed item, authoritative price/eligibility/trade-in, protected atomic purchase and replay remain |
| Mounts/stables | Not changed; ownership/location/price/transaction, exact mount/buff/companion schemas and editor matrix remain |
| Training/masters | Not changed; server eligibility, combat entry, one-level advancement and replay remain |
| PvP | Not changed; actor/target/location/alive/daily/combat authority and funded Dag interaction remain |
| Admin/editor role matrix | Not changed; representative anonymous/player/insufficient/admin, methods/CSRF/IDs/unknown-fields matrices remain |
| Module settings and object preferences | Not changed; declared descriptor/type/range/length/namespace allowlists and authorized bound editor writes remain |
| Player preference protection | Existing forged canedit/skill namespace rejection retained and regression tests pass |
| Serialized state | Exactly 87 tokenized sites / 41 files, 31 serialize, 55 ScalarState reads, one actual unserialize; new wager business schemas closed; combat/mount/companion/buff/mail-translation/editor/nav schemas remain |
| Meaningful GET mutations | Dark Horse payment and game mutations removed; Audrey Village, Lovers, onboarding/combat, petitions, clans, equipment, stables, training/PvP and legacy editor families remain; bank/mail enforced boundaries also open |
| Replay risks | New games pass duplicate/fresh-form invalid-stage and shared jackpot replay tests. Untouched route families remain open. Non-game session-intent crash windows are not a universal durable exactly-once guarantee |
| expire_chars.php | Not changed; premature last_char_expire marker remains a merge blocker, with multi-point failure/retry tests outstanding |
| charcleanup/account deletion | Not changed; preflight/hook/related cleanup/account DELETE/log failure semantics and external-effect recovery remain blockers |
| External expiration mail / optimization | Still require separate observability, completion/retry semantics; external mail and OPTIMIZE implicit commits are not rollback-safe |

### Both-target evidence

At implementation `1a6cd7340978e68fa43e7b2d015f4ca513d130ec`:

| Gate | PHP 8.4 / MariaDB | PHP 8.5 / MySQL |
|---|---|---|
| PHPUnit | 65 tests, 1,612 assertions, zero skips | Same |
| Python and actual HTTP | 16 tests, zero skips | Same |
| PHP lint | 300 files, zero failures | Same |
| Legacy PHPStan | Level 0; seven retained findings; zero new errors | Same |
| New infrastructure PHPStan | Level 6; zero errors; no baseline | Same |
| Composer | Strict validation, locked install, dependency audit PASS | Same |
| Fresh install/auth/modules/scheduler | PASS existing full regression | Same |
| Modern core | SUCCESS, job 103795858461 | SUCCESS, job 103795858653 |

- Modern core: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34783935347, SUCCESS.
- Baseline integrity: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34783935406, SUCCESS.
- Final documentation-head CI is run again and recorded in PR #1 and the final report. No test was skipped or removed, no static baseline regenerated, no dependency added.
- Prior failed Modern core runs remain visible: 34783218603 (missing procedural class includes), 34783386237 (rendered navigation test assertion), 34783540283 (fixture New Day/search routing and trigger portability), 34783775745 (constraint fixture auto-increment restriction). These were corrected, not concealed. Two-server jackpot concurrency already passed on both jobs of 34783775745.

### Merge decision and precise next work

PR #1 cannot be marked ready or merged while the known major core and bundled-module gates above remain. No merge commit exists for this execution; main stays at the verified original SHA. Public hosting remains NO independently of the merge decision.

Continue **Phase 3 on this branch and PR**. The shared wager cluster is now complete. Next prioritize **Dag's actual funded PvP interaction and full administrator place/close/cleanup matrix with bounty-specific rollback**, then Drinks' create/delete/delegated-editor and configured-boundary completion, Cedrik, Audrey/Lovers, remaining Forest/Outhouse/Seth branches, onboarding/combat, and the recorded core/editor/cleanup families. The general typed settings/object-preference editor should be completed as those dependent module gates are addressed. Do not start Phase 4, deploy or release.

### Strict-analysis scope follow-up

The six-commit checkpoint above was published at `6725c39ef2612f86b863ecae4da58fd8257ee018`. Final source review then added `lib/darkhorse_game.php` explicitly to `phpstan-new.neon`, ensuring the new procedural helper is checked at level 6 alongside the new classes and the existing player mutation service. The expanded strict scope passes locally with zero errors and no baseline. This seventh continuation commit changes only analysis scope and this evidence note; application behavior, test counts and module decisions are unchanged. The final branch SHA and both exact-head workflow outcomes for this follow-up are recorded in PR #1 and the execution report. The entire published history remains intact.

### Final-run Goldmine regression correction

Final workflow 34784430521 exposed a pre-existing random Goldmine cave-in failure on MariaDB: `horsedead` was read without initialization when the player died and the mount-death roll did not set it. The MySQL job passed, as did all new Dark Horse tests on both jobs. This was not dismissed by retrying. The eighth continuation commit initializes the outcome flag to zero and adds `testGoldmineDeathWithoutMountHasDefinedOutcome`, which deterministically exercises the actual callback with seed zero, checks death/HP/no-mount/event completion, and proves configured gold/gem losses and experience retain their formulas. No odds or effects change. Goldmine remains BLOCKED for its other outcomes, mount/object preferences and editor gates. Final PHPUnit count becomes 66 tests; the exact assertion count and both workflow outcomes are recorded in PR #1 after the expanded matrix completes.

## Dag funded PvP / Drinks / configured Cedrik continuation (2026-09-14)

**Modern-core merge: NOT READY. Public hosting: NO. PR #1 remains OPEN, DRAFT, NOT MERGED.** This append supersedes older conclusions for the routes it names, while preserving all prior continuation history. Phase 4 is not started.

### Published starting state and commits

Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection. Existing branch `modernization/core-modernization`, existing PR #1. Published starting SHA verified as `150f7a09a1927ff1b32f6802a41282785a25369b`; main verified unchanged at `999cec6f9c655a840320d982d673bb863c68c2b2`. PR was OPEN/DRAFT/unmerged. No legitimate later published work was reset or replaced.

Verified ending implementation SHA: **`d575acfcf7631201b0f195f0fe10be11fe0d63ec`**. Five implementation commits, followed by this evidence-only commit (six commits total for this execution). The final documentation commit's full ending SHA and its exact-head CI are recorded in PR #1 and the final execution report, after they exist.

| Commit | Purpose |
|---|---|
| `fb1d7dcc9a9646db2b8e2a58b1ddbf090f0a9cd9` | Owned PvP actions, minimum state schema, enclosing funded Dag settlement transaction and deferred email notification |
| `f1832ef53fc0305dfa5891a8e656c7df5b1b6ade` | Drinks delegated editor/CRUD HTTP coverage and actual pvpwin callback argument fixture |
| `f01e66912856410d2fe0806c493dbc277ddcffa3` | Exercised PvP/form corrections, new drink defaults, configured Cedrik pricing validation and HTTP cases |
| `95fe60bb7bceb8a6af6fe600aab8a905f8c394aa` | Inn/bodyguard context derived from stored target; hydrated actual Drinks New Day callback fixture |
| `d575acfcf7631201b0f195f0fe10be11fe0d63ec` | Literal Unicode Dag searches, consistent dead-player fixture, complete failure retry without account resets, serialized inventory |
| This documentation commit | Module promotion, route/security/schema records, retained failure history and merge decision |

### Gates closed and evidence limits

- **Dag funded PvP PASS.** Real HTTP entry/round/win uses a valid locked actor and eligible target. An eligible mature 250-gold bounty pays once; own, delayed and closed rows do not pay. Posted amount/target/winner cannot select payment or victim. The account owns a unique encounter, target and reservation; action methods, CSRF and consumed entry/round intents are enforced; terminal combat is cleared. Repeated result and invalid actor/target requests preserve relevant state. Historical bounty economics are unchanged.
- **Dag administration PASS.** Actual place/close/cleanup/list/search covers anonymous, ordinary player, insufficient capability and SU_EDIT_USERS. Positive typed IDs, nonexistent/deleted records, invalid amount/target, allowlisted filters/sort, GET, missing CSRF, replay and valid POST are exercised independently of hidden navigation. Literal apostrophe/backslash/UTF-8/percent/underscore/escape-symbol names work through bound SQL; markup stays out of raw HTML. Placement charges administrators the historical zero cost and records the system setter.
- **Dag rollback PASS.** Test CHECK constraints fail bounty close, bounty-specific news, in-game mail and final winner-credit writes. Actor/victim balances, HP/experience/daily fights/combat/reservation, bounty rows, news/debug/mail counts roll back to their snapshots. Failed intents cannot be replayed. After removing the final constraint, a fresh issued intent retries the preserved encounter to exactly one payout, without a fixture account reset. DDL is fixture setup outside the DML transaction. External email is queued until commit and failure cannot roll back the completed game result.
- **Drinks PASS.** Existing purchase and editor save are retained. Actual create/activate/deactivate/delete, stored delegation, forged/revoked delegation, both authorized editor roles and denied role/method/CSRF/ID matrices pass. Extreme valid values persist; invalid ranges, arrays and undeclared fields do not. Configured hard-drink and max-drunk thresholds, hard maximum, invalid configuration and actual New Day reset/hangover-once behavior pass in the transactional callback fixture. This does not certify arbitrary administrator preference/configuration editing.
- **Cedrik pricing PASS; module BLOCKED.** Each configured fixed price, random cost 1/10 endpoints, invalid random bounds/current cost, exact whole-dose charge, multiple quantity, insufficient funds, maximum offered integer, malformed quantity and replay pass. Historical offered-gems/remainder and single-dose Forgetfulness/Transmutation behavior remains. Transmutation sickness is counted in combat rounds, with optional New Day survival, not a day countdown. Actual New Day/combat carry/expiration/Dragon Kill route evidence and the shared typed settings editor remain open.
- **PvP core PARTIAL.** The new protected entry/round/win path and funded Dag boundary are proven. Full defeat/result/inn-bodyguard, broader actor and combat-state matrices are not claimed complete. Stored inn location now controls bodyguard behavior, but deriving it in code does not substitute for that remaining HTTP evidence.
- **Serialized state PARTIAL.** `PvpState` validates owner/encounter/target/reservation, single enemy, finite bounded core combat numbers and bounded text before this PvP route executes combat. The token scan is **88 sites / 42 files: 31 serialize, 56 ScalarState reads, one actual unserialize**. ScalarState's class/input/depth/node/cycle/canonical fail-closed restrictions are unchanged. General combat, optional nested fields, mounts, companions, buffs, mail translation, preferences, editor oldvalues and navigation schemas remain open. No arbitrary shipped eval is restored.
- **systemmail PARTIAL.** SQL reads and insertion are bound, raw message text is preserved rather than pre-escaped, and PvP email notifications are deferred until commit. Mail send/reply, recipient/sender trust classification of every caller, length limits and translated-array business schemas remain unclosed.

### Module decisions

**8 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 16 BLOCKED; 24 lifecycle PASS.** Dag and Drinks are promoted individually on the evidence above. All six prior PASS decisions remain intact. The readable and JSON certification records contain the complete 24-module matrix and an explicit next required gate for each blocked module.

| Module(s) | Final status | Exact remaining supported gate |
|---|---|---|
| dag; drinks; findgem; findgold; foilwench; game_stones; game_dice; game_fivesix | PASS | No module-specific gate remains in the certified bundled scope; separate core gates below remain |
| cedrikspotions | BLOCKED | Actual Transmutation New Day/combat carry, expiration, survival and Dragon Kill interaction; typed settings editing |
| darkhorse | BLOCKED | Mounted/no-mount/findtavern/event-entry HTTP matrix, tavern name setting and mount object preference editor |
| fairy | BLOCKED | Deterministic configured HP/permanent-HP/turn/specialty/no-reward/requirement and carry branches |
| glowingstream | BLOCKED | Deterministic configured currency/HP/turn/reward/loss branch coverage |
| goldmine | BLOCKED | Remaining reward/loss/death/mount-save/mount-death/tether branches and configured mount preferences |
| outhouse | BLOCKED | Reward/no-reward/penalty/gem/turn/nowash branches and configured cost/chance editor evidence |
| sethsong | BLOCKED | HP/gold/gem/no-effect branches, configured visits/exhaustion/fresh intent and reset |
| crazyaudrey | BLOCKED | Village paid pet/play authority, daily state, POST/CSRF/replay and effects |
| lovers | BLOCKED | Protected daily conversation/effect stage progression and reset |
| racehuman; raceelf; racedwarf; racetroll | BLOCKED | Actual HTTP onboarding/invalid/forged/CSRF/persistence/location/reselection and later New Day effects |
| specialtydarkarts; specialtymysticpower; specialtythiefskills | BLOCKED | Actual HTTP selection and combat level/use/state/CSRF/duplicate-use matrix |

### Other major core gates remain open

| Family | Result / remaining work |
|---|---|
| Settings editor | BLOCKED: inspect shipped descriptors; typed key/module/type/range/length allowlists and full HTTP roles/method/CSRF matrix |
| Object preferences | BLOCKED: declared module/object/key, compatible ID, typed value and authorized POST/CSRF; no arbitrary namespaces |
| Race onboarding | BLOCKED for all four; existing hook/stat fixtures retained, actual HTTP not added |
| Specialty onboarding/combat | BLOCKED for all three; valid hook/buff fixtures retained, actual HTTP action authority not added |
| General combat schema | BLOCKED: minimum PvP schema added, full shipped combat and genuinely used nested fields still need validation |
| Mail send | BLOCKED: sender/recipient authority, bounds, SQL, enforced POST/CSRF/replay and quota |
| Mail reply | BLOCKED: original ownership and stored sender-derived recipient, invalid/deleted/spoofed target and rendering |
| systemmail | PARTIAL as above; every-caller trust model and player-facing semantics not certified |
| Petition administration | BLOCKED: SU_EDIT_PETITIONS, strict IDs/status, bound writes, protected cleanup/status/notes/delete and full HTTP role matrix |
| Clans | BLOCKED: membership/rank/cross-clan officer/leader actions, bounded persistent text, bound atomic POST/CSRF and replay |
| Bank | BLOCKED: reject negative rather than abs; positive bounds, locked atomic debit/credit and failure rollback |
| Weapons | BLOCKED: authoritative typed item/price/trade-in and protected atomic purchase/replay/failure injection |
| Armor | BLOCKED: same purchase/trade-in boundary |
| Mounts/stables | BLOCKED: ownership/location/price, buy/sell/replace/buffs, exact state schema and editor/object preferences |
| Training/masters | BLOCKED: server master/experience/level and victory authority, protected entry/advancement, no double-level replay |
| PvP | PARTIAL as above; full core certification not inferred from funded Dag win |
| Admin/editor matrix | BLOCKED: user/configuration/creature/equipment/mount/title/taunt/module manager role, field, SQL and output matrices |
| Remaining serialized schemas | BLOCKED: general combat, mounts, companions, buffs, translation arrays, preferences, oldvalues and navigation |
| Expiration cleanup | BLOCKED: last_char_expire still advances before successful cleanup; candidate/hook/related/final-delete failure injection absent |
| Account deletion | BLOCKED: preflight/veto/hook/related-row/final-delete/success-log phases, transactions and observable retry semantics |
| External email and OPTIMIZE | Delivery is not transactional; optimization may implicitly commit. Completion/notification/optimization reporting and retry semantics must stay separate |

The fresh meaningful-GET scan reconfirms Audrey Village, Lovers, onboarding/specialty combat, `viewpetition.php`, clans, weapons/armor, stables, training and legacy editors. `pvp.php` entry/round/result GETs are now confirmation-only. No meaningful remaining GET mutation is accepted as harmless navigation. Changed mutation routes have duplicate tests; untouched families retain replay risks. One-use session intents are not a universal crash-safe exactly-once platform.

### Supported validation

At `d575acfcf7631201b0f195f0fe10be11fe0d63ec`:

| Gate | PHP 8.4.25 / MariaDB 11.4.13 | PHP 8.5.10 / MySQL 8.4.11 |
|---|---|---|
| PHPUnit | 67 tests, 1,692 assertions, zero skips | Same |
| Python / loopback HTTP | 20 tests, zero skips, PASS | Same |
| PHP lint | 302 files, zero failures | Same |
| Legacy PHPStan | Level 0, seven retained findings, zero new errors | Same |
| Infrastructure PHPStan | Level 6, zero errors, no baseline | Same |
| Composer | Strict validation, locked install, audit PASS | Same |
| Fresh install/auth/24 modules/games/scheduler | Retained full regression PASS | Same |
| Modern core job | 103818653164 SUCCESS | 103818653066 SUCCESS |

Modern core [34792299180](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34792299180) SUCCESS. Baseline integrity [34792299186](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34792299186) SUCCESS. The evidence-only ending commit runs the complete matrix again; its exact SHA and run IDs are reported in PR #1 and the final response. No tests or static-analysis gates were removed or weakened. A supplemental local PHP 8.4 / disposable MariaDB 10.11 fixture run passed; it does not replace either supported target.

Intermediate failed Modern core runs are retained: 34791067220 (pvpwin fixture lacked the actual callback arguments), 34791203164 (missing-op false value and new drink form defaults; stale HTTP fixture day/target state), 34791465123 (New Day fixture lacked hydrated preferences), and 34791589194 (an inconsistent alive=0/positive-HP actor fixture). Corrections are in the published history. Common bootstrap derives alive from HP, so the final dead-player case sets HP=0 and still asserts rejection. Added literal-name coverage exposed a real SQL LIKE backslash/UTF-8 bug, corrected in d575acf. These failures were investigated rather than hidden by weakening assertions.

### Preservation, decision and exact next phase

Historical tag `historical-source-1.1.2` remains annotated object **51cab4fbe58a234651a3177a56289b18bc152b4d**, target **bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2**, root tree **4013a0ccc5227e87cd7a22de00b7c322d7aa237c**: **417 files and 11 preservation commits PASS**. No writes to `jimlunsford/lotgd` or `jimlunsford/lotgd-modules`; no archive imports. No VPS access, public game runtime, deployment, release/tag/ZIP or Phase 4. Disabled LoGDnet, console, payments, source viewer and recovery remain disabled.

Known major gates remain, so PR #1 stays OPEN/DRAFT and is not merged. No merge SHA exists for this execution. Main remains unchanged. **Modern-core NOT READY; public hosting NO.**

**Next phase: continue Phase 3 on this same branch/PR**, beginning with Cedrik Transmutation route persistence and the shared typed settings/object-preference editor, then Dark Horse entry/configuration and remaining configured bundled branches; continue onboarding/combat, mail/petitions/clans/economy/equipment/mounts/training/full PvP, other editors/schemas, and cleanup/deletion failure semantics. Do not start Phase 4 until the actual modern-core gates are closed.

## Cedrik, shared editors and Dark Horse checkpoint (2026-09-14)

**Phase 3 continues. Modern-core NOT READY. PR merge NO. Public hosting NO.** The eight existing module certifications remain intact. Cedrik and Dark Horse close their named remaining gates: **10 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 14 BLOCKED; 24 lifecycle PASS.** No other module is promoted by inference from the shared editor tests.

### Repository and publication state

Starting verification matched the requested state exactly: main `999cec6f9c655a840320d982d673bb863c68c2b2`, branch `7b537eebc213bd470d3dd3e1b819b705fb9c5cf8`, PR #1 OPEN/DRAFT/unmerged, no other modernization branch or PR #2. Main remains unchanged. Work continues on `modernization/core-modernization` and PR #1. No published commit was rewritten or squashed; all five implementation/test commits below are descendants of the supplied starting SHA. The sixth focused commit records this checkpoint and the two workflow checkout changes required to test the exact PR head. A seventh evidence commit adds explicit shared-editor audit and account-snapshot rollback assertions after the final review; no production behavior changes.

Implementation and regression ending SHA: `fd9b8a576d02b671a6e8a35490ed82845174381e`.

- `5c16f5369e05d5e1b69521f1fceedac273e7f631`: Secure shared typed settings and mount preference editors
- `8230dfef1989682f892dff8b6f40cdd9c64416cb`: Validate Cedrik potion state across combat and lifecycle resets
- `90eba516e6e11e1a812b8d05d517a7f7b763413a`: Protect Dark Horse mount entry and encounter exits with POST intents
- `a8d3497a0c4258948758da2839a9659681f9d562`: Preserve legacy setting forms and validate stable editor snapshots
- `fd9b8a576d02b671a6e8a35490ed82845174381e`: Exercise editor-to-game configuration and actual tavern discovery
- `82b08b5daf40f1af346e013d8d5b926ac3a3256d`: Record Cedrik and Dark Horse certification and verify the exact PR head

The final repository SHA is the seventh, evidence-only commit updating this appended section. A commit cannot contain its own object ID or future CI run IDs: the post-commit final SHA, exact checkout evidence and completed workflow IDs are recorded in the current checkpoint at [PR #1](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/pull/1). Final certification publication requires those runs to pass; the implementation evidence below is not relabeled as a final-head run. The PR remains OPEN/DRAFT and has no merge commit.

### Work completed and intended persistence

Cedrik uses the existing buff container and centralized scalar decoder with an exact Transmutation business schema. The schema fixes text/identity fields, positive bounded combat rounds, finite attack/defense modifiers from 0.1 through 2, the stored New Day carry flag, and the existing small runtime flag vocabulary. Missing sickness means no effect; malformed/stale/oversized/unexpected state fails before gameplay. Vitality extra-HP bookkeeping is bounded. Costs, availability, gains, rounds and modifiers come from locked server settings, never posted outcome fields.

Successful purchases assert exact balances, HP/stat/preference/buff changes. Repeated Transmutation adds configured rounds while retaining the original sickness modifiers and carry flag. Fresh login and account reload preserve the result. Actual combat activation changes attack/defense and consumes one round per used round; exhaustion removes the buff. Actual Forest HTTP advances and expires sickness. New Day carries the validated remaining sickness only when its saved flag allows it; the false branch removes it. New Day no longer reloads an unchecked second buff copy, and resets the buff-list cursor before carrying entries. Actual Dragon victory/reset always strips sickness; Vitality retains 15 extra HP (25 max HP) for carrydk=1 and resets to zero extra HP (10 max HP) for carrydk=0. Repeated lifecycle execution does not duplicate those effects. These tests prove Cedrik persistence through core routes, not blanket security certification of general Forest/Dragon/New Day mutations.

Shared settings reuse existing core/module declarations and stores. `SettingDescriptor` covers the shipped string/text, boolean, bounded integer/float, enum/theme and integer-or-percent vocabulary. Unknown types and undeclared keys fail closed; disabled payments/LoGDnet controls remain excluded. Namespace and SU_EDIT_CONFIG are server enforced. Saves are POST/CSRF with one-use intents tied to schema and displayed values, and locked actor/module/settings rechecks. Values, related location DML, audit and the exercised shipped DML callbacks commit together. Paired ranges and Cedrik active random prices are validated. Core historical empty false defaults are accepted only when interpreting stored defaults, never as boolean POST values. Clock-dependent labels no longer destabilize editor intents. Forms escape stored text, labels and option values. A valid HTTP editor change is followed by an ordinary player's purchase proving the saved five-gem price and five-round sickness.

The shared object editor permits the enabled `mounts` object type only, requires SU_EDIT_MOUNTS, an installed declaring module, an existing bounded mount ID and declared preference keys/types/bounds. It locks the actor, module, mount and relevant preference rows; intent context includes object identity and the displayed object/preferences. Cross-object/module, deleted/stale object and revoked authorization attempts fail. Dark Horse and Goldmine exercise the common contract. Read-only preference defaults no longer create rows on GET. The separate Drinks object editor stays disabled. This does not certify mount CRUD, purchases, sales or replacements.

Dark Horse mounted entry derives the mount from the account, verifies the real mount and exact findtavern preference, and uses protected POST to persist the encounter. Revoked preferences, invalid/deleted mounts, other event state and replay fail safely. Actual server-selected Forest discovery at configured 100% works without a mount; an ordinary player's forged eventhandler does not select the event. Event exit is a protected POST, and an active wager must settle or be explicitly abandoned first. Replayed exit cannot recreate an encounter or mutate a balance. Shared settings and object preferences are tested alongside retained bartender, wager, jackpot/concurrency, information/payment and lifecycle tests. The configured tavern title is escaped in the page title.

Two narrow runtime fixes were necessary for real lifecycle evidence: legitimate Dragon combat has no creatureexp, so experience option setup uses zero when absent; fight navigation now derives the script from SCRIPT_NAME instead of the removed PHP_SELF variable. Neither adds a random-result override or claims full combat-route closure. The Dag rollback test now allows the real combat engine's surprise/miss rounds before the existing injected settlement failure; all original rollback and retry assertions remain.

Goldmine gains authorized and adversarial shared mount-preference HTTP evidence, including transactional multi-write rollback. It remains BLOCKED because reward/no-reward/death, tether, racial rescue, mount rescue/death and configured loss combinations require a separate deterministic gameplay matrix. No archive-only module or unrelated subsystem was added.

### Full bundled-module certification

| Module | Previous | Final | Remaining gate |
|---|---|---|---|
| `cedrikspotions` | BLOCKED | PASS | None in the supported bundled scope |
| `crazyaudrey` | BLOCKED | BLOCKED | Protect and test the Village paid pet/play mutation with daily state, POST/CSRF and one-use replay handling. |
| `dag` | PASS | PASS | None in the supported bundled scope |
| `darkhorse` | BLOCKED | PASS | None in the supported bundled scope |
| `drinks` | PASS | PASS | None in the supported bundled scope |
| `fairy` | BLOCKED | BLOCKED | Force all supported HP/permanent-HP/turn/specialty/no-reward branches under configured values and carry behavior. |
| `findgem` | PASS | PASS | None in the supported bundled scope |
| `findgold` | PASS | PASS | None in the supported bundled scope |
| `foilwench` | PASS | PASS | None in the supported bundled scope |
| `game_dice` | PASS | PASS | None in the supported bundled scope |
| `game_fivesix` | PASS | PASS | None in the supported bundled scope |
| `game_stones` | PASS | PASS | None in the supported bundled scope |
| `glowingstream` | BLOCKED | BLOCKED | Force every supported reward/loss/HP/turn/currency branch under configured values. |
| `goldmine` | BLOCKED | BLOCKED | Complete deterministic reward/loss/death/tether/mount-save/mount-death outcomes using the now-secured settings and mount preference editors. |
| `lovers` | BLOCKED | BLOCKED | Secure conversation/flirt/chat daily stages, POST/CSRF and one-use replay before certifying effects. |
| `outhouse` | BLOCKED | BLOCKED | Force reward/no-reward/penalty/gem/turn/nowash branches with configured costs and chances. |
| `racedwarf` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `raceelf` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `racehuman` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `racetroll` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `sethsong` | BLOCKED | BLOCKED | Force HP/gold/gem/no-effect outcomes and configured visit exhaustion/fresh-intent/reset boundaries. |
| `specialtydarkarts` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |
| `specialtymysticpower` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |
| `specialtythiefskills` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |

### Full current core route-security record

Statuses apply to the named scope. Detailed authentication, authorization, typing, SQL, method, CSRF, replay and output columns remain in [CORE-ROUTE-SECURITY.md](CORE-ROUTE-SECURITY.md). The full current family list follows, including cleanup and schema blockers.

| Core family / route | Current status | Evidence / remaining gate |
|---|---|---|
| runmodule.php / module injection | PASS dispatch; not blanket route certification | Existing module HTTP inactive/force/dependency fixtures |
| Forest: findgem, findgold, foilwench | PASS tested Forest scope | test_module_purchases_post_csrf_replay_and_effects; lifecycle/hooks; PlayerMutationTest |
| Forest: fairy, glowingstream, goldmine, crazyaudrey | PARTIAL; random/state branch and integration review incomplete | Same HTTP test; prior hook fixtures |
| runmodule.php?module=dag | PASS placement | PlayerMutationTest; existing player HTTP; administrator literal-name search |
| Dag pvpwin hook | PASS named operations | Funded HTTP, four rollback points and retry; PvpStateTest |
| Dag manage=true | PASS named operations | Anonymous/ordinary/insufficient/authorized; GET/CSRF/invalid/replay/valid HTTP |
| runmodule.php?module=drinks | PASS named operations | Purchase HTTP; PlayerMutationTest configured boundaries and actual New Day reset |
| Drinks editor | PASS named operations | Actual role/CRUD/revocation/malformed/CSRF/replay matrix; DrinkInputTest |
| runmodule.php?module=cedrikspotions | PASS named potion/persistence operations | Shared settings and full Transmutation/Dragon lifecycle HTTP; exact rollback and retry |
| runmodule.php?module=game_stones | PASS supported game | StonesGameTest; DarkHorseGameTest; full HTTP play/replay/abandonment and final-write rollback |
| Dark Horse entry / exit / bartender / event | PASS supported Dark Horse operations | Mounted/Forest entry, exit, shared settings/mount preferences plus retained wager/information HTTP |
| game_dice / game_fivesix | PASS supported games; other admin editor surfaces remain core blockers | DarkHorseGameTest; actual HTTP progression/replay/rollback; two-server jackpot concurrency; New Day reset hook |
| Crazy Audrey Village; Lovers | BLOCKED | Prior hooks/route rendering + Audrey Forest test only |
| runmodule.php?module=outhouse | PARTIAL; full outcome/settings validation outstanding | Real paid/free use, wash, replay and fresh-form stage rejection HTTP |
| runmodule.php?module=sethsong | PARTIAL; full effect/settings validation outstanding | Real GET/no-effect, POST/CSRF, visit counter and replay HTTP; existing New Day hook |
| newday.php races | BLOCKED | Existing hook/stat fixtures only; actual race choice HTTP matrix absent |
| newday.php + battle.php specialties | BLOCKED | Existing all valid skill buff/uses and reset hook fixtures; route matrix absent |
| mail.php delete/unread | PASS existing scoped operations | Existing mailbox HTTP and ownership service tests |
| mail.php send / address / reply | BLOCKED | Source review case_send.php/case_write.php; no new send/reply HTTP tests |
| lib/systemmail.php | BLOCKED | Source/call-boundary review; no complete helper test suite |
| petition.php intake | PASS existing intake scope | Prior real petition HTTP |
| viewpetition.php | BLOCKED | Source review; intake tests are not admin evidence |
| clan.php / lib/clan/* | BLOCKED | Source clan_membership.php; required role/rank HTTP matrix absent |
| bank.php | BLOCKED | Source review; no new economy rollback/HTTP suite |
| weapons.php / armor.php | BLOCKED | Source review; no purchase HTTP suite |
| stables.php / mounts.php | BLOCKED | Object restoration constrained only; no mount transaction/HTTP suite |
| train.php / masters.php | BLOCKED | Source review + prior stat fixtures; no duplicate advancement test |
| pvp.php / battle.php | PARTIAL core; funded Dag suite PASS | Actual entry/win/invalid eligibility/rollback/retry; defeat, inn/bodyguard, broader combat matrices still OPEN |
| user.php | BLOCKED | Source review; no complete anonymous/player/insufficient/admin matrix |
| creatures.php / armoreditor.php / weaponeditor.php / mounts.php / titleedit.php / taunt.php | BLOCKED | No new full representative editor HTTP matrix |
| Remaining user preferences / modules.php lifecycle editor | BLOCKED | Lifecycle APIs pass; no blanket HTTP editor certification |
| configuration.php core and declared module settings | PASS exercised shared boundary | Actual authorized/ordinary/anonymous/insufficient role; forged namespace/key; malformed/bounds/enum; CSRF; replay; stale; rollback/fresh retry; configured behavior |
| mounts.php shared module preferences | PASS enabled mounts boundary | Dark Horse/Goldmine real HTTP role/CSRF/replay/types/bounds/cross-object/module/deletion/rollback; Drinks object editing stays disabled |
| Remaining serialized business schemas | BLOCKED | General combat, mounts, companions, other buffs, translation arrays, preferences and editor values still need exact schemas. Scalar parser protections are retained. |
| Expiration cleanup | BLOCKED | last_char_expire advances before all candidate/hook/deletion phases succeed; failure/retry matrix absent. |
| Account deletion | BLOCKED | Hook veto/side effects, related DML, final DELETE and success reporting lack the complete tested transaction/retry contract. |
| External email / database optimization | BLOCKED completion reporting | Email is external and OPTIMIZE can implicitly commit. Correct completion, retry and observable reporting remain required; no SQL atomicity claim. |
| Full administrator/editor matrix | BLOCKED | Shared settings and mount object preferences PASS does not certify user/content/mount CRUD/module-manager callers. |

### Mutation, replay and failure review

- Settings and enabled mount-object editors: actual anonymous/ordinary/insufficient/authorized role paths; forged namespace/module/type/key/ID; wrong/nested/extreme values; invalid enum; missing/invalid CSRF; valid first save, replay and stale snapshot; exact persisted text and safe rendering. Object coverage includes cross-object/module and deletion. Privilege and object identity are server derived or validated.
- Cedrik: retained exact configured pricing and all potion tests; new Transmutation/Vitality state, New Day/combat/Dragon boundaries, malformed/missing/stale state and actual editor-to-game behavior. Settings test minimum/maximum, invalid/reversed ranges and retained configuration. Client result fields cannot set resulting buffs or balances.
- Dark Horse: mounted GET only confirms; entry, exit, charge and wager mutations require protected POST. Forest discovery reserves a server-selected encounter identity, as in the existing dispatcher; it does not charge/reward/abandon a wager or bypass mounted entry. Existing event staging is not claimed to be a universal GET-purity guarantee for the whole Forest route.
- Real CHECK-constraint failure injection rejects a later settings write after earlier settings/audit DML, a later object preference write after earlier preference DML, and Cedrik's final account write after debug DML. Exact transactional snapshots roll back, balances and counters do not drift, the consumed failed intent cannot mutate again, and a fresh intent completes the preserved state. Test-fixture DDL is outside the transaction under test.
- One-use session intents provide the tested ordinary duplicate/replay boundary. They are not universal crash-safe exactly-once delivery. A crash between database commit and session persistence remains possible. External mail, arbitrary hooks, DDL and optimization are not made transactional by these changes.
- Remaining meaningful GET mutation families include Audrey Village, Lovers, race/specialty onboarding and combat, general Forest/Dragon/training combat and Dragon reset, petition administration, clans, equipment/stables and legacy editors. Full PvP defeat/inn/bodyguard, mail/bank enforced action boundaries and remaining caller-specific replay/transaction gates are open. Existing PvP entry/round/result protections remain intact.

### Serialized state and historical preservation

Exact tracked shipped-PHP token inventory: **87 sites, 42 files, 31 serialize, 55 ScalarState reads, one centralized unserialize**. The prior 88/42/31/56/1 count decreases by exactly one: the redundant New Day buff read is removed in favor of the already validated hydrated list. No raw unserialize is added. ScalarState's 1 MiB/depth32/node10000, no-classes, scalar-only, canonical-complete protections are unchanged. All exact sites and line numbers are refreshed in [SERIALIZED-STATE-AUDIT.md](SERIALIZED-STATE-AUDIT.md); other business schemas remain blocked.

Historical verification PASS: **417 files, 11 commits**. Unchanged `historical-source-1.1.2` annotated tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, target `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`. Imported history/tags and historical repositories were not modified.

### Supported target evidence

At implementation checkpoint `a8d3497a0c4258948758da2839a9659681f9d562`, Modern core [34795324750](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34795324750) and Baseline integrity [34795324921](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34795324921) completed SUCCESS. Jobs 103827169639 and 103827169348 confirm the following counts. The subsequent `fd9b8a576d02b671a6e8a35490ed82845174381e` commit adds actual editor-to-purchase and Forest discovery assertions within existing tests. Modern core [34796017543](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34796017543) and Baseline integrity [34796017575](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34796017575) both completed SUCCESS for that checkpoint; a clean local full run has the same totals. Final commit CI runs the full matrix again with explicit head-SHA checkout, and the final run IDs and exact SHA are recorded in PR #1 after completion.

| Gate | PHP 8.4.25 / MariaDB 11.4.13 | PHP 8.5.10 / MySQL 8.4.11 |
|---|---|---|
| PHPUnit | 71 tests, 1,744 assertions, zero skips | Same |
| Python / real HTTP | 25 tests, zero skips, PASS | Same |
| PHP lint | 308 files, zero failures | Same |
| Legacy PHPStan | Level 0, seven retained findings, zero new errors | Same |
| Infrastructure PHPStan | Level 6, zero errors, no baseline | Same |
| Composer | Strict validation, locked installation, audit PASS | Same |
| Fresh install / auth / modules / games / scheduler | Full retained regression PASS | Same |
| Historical integrity / metadata | PASS | Same |

The prior baseline was 67 PHPUnit / 1,692 assertions / 20 Python / 302 lint files. The increase is four PHPUnit tests, 52 assertions, five HTTP methods and six PHP files. No prior security test is skipped, deleted or weakened. Local PHP 8.4/MariaDB 10.11 is supplemental only. Modern core and Baseline integrity workflows now explicitly check out `github.event.pull_request.head.sha` for PRs and `github.sha` otherwise; no gate was removed or relaxed.

### Final decision and next Phase 3 work

**Is modern-core ready to merge into main? NO. Should PR #1 be merged? NO. Should public hosting begin? NO.** Modern-core remains NOT READY, PR #1 remains OPEN/DRAFT/unmerged and main is unchanged. No Phase 4 branch, PR #2, release, public runtime, hosting/deployment or VPS action was created or performed.

The next directly related Phase 3 priority is Goldmine's configured outcome/mount interaction matrix using the secured shared editors, followed by Fairy/Glowing Stream/Outhouse/Seth Song configured branches where that contract directly applies. Do not infer these passes from editor closure. Remaining major merge families are race/specialty onboarding and combat, full combat/PvP state and defeat/inn behavior, mail send/reply/systemmail, petition administration, clans/bank/equipment/stables/training, remaining administrator editor and serialized business schemas, expiration/account deletion and truthful external-effect/optimization completion reporting. Continue on this branch and PR until those gates have evidence.


## Remaining bundled effects continuation (2026-09-14)

**15 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 9 BLOCKED; all 24 lifecycle PASS. Modern-core merge: NO. Public hosting: NO. Phase 3 is incomplete.**

### Repository and publication

Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection. Starting published branch matched `dc5926394b667a3c544ddfd7b0d61278270aa332` on `modernization/core-modernization`. Main matched and remains `999cec6f9c655a840320d982d673bb863c68c2b2`. PR #1 was verified OPEN, DRAFT and NOT MERGED. This execution retains that state; no merge SHA exists. Earlier local work was left intact and a clean checkout was used.

Three implementation/test commits were appended to the existing branch:

1. `1a0783840ac16750f2717fb93a6b272cbee72517`: Secure Audrey Village visits and exercise every Glowing Stream outcome.
2. `4b982ddc27a799ce1efb2d7296fcfc1ba57f13ab`: Validate Fairy awards and prove configured effects and Dragon carry.
3. `ae236d69ae856231256625fe33e6f9b5b1dcefd9`: Validate Outhouse and Seth state and certify deterministic HTTP outcomes.

**Implementation/test ending SHA: `ae236d69ae856231256625fe33e6f9b5b1dcefd9`.** The following documentation commit contains this checkpoint and synchronized audits/certification. Its own final publication SHA and exact-head workflow IDs are recorded in PR #1 and the final execution report after publication. Shell Git had no write credentials, so publication used the connected GitHub app with explicit parent commits, exact local/published tree comparison and non-force branch updates. No published history was reset, squashed or rebased.

### Every bundled module

Five new promotions are independent of unrelated core blockers. All previous ten PASS certifications remain intact. The JSON aggregate previously still said 8/16 and referenced older runs despite its ten PASS entries; current counts/run metadata are reconciled with the individual records.

| Module | Previous | Final | Remaining gate |
|---|---|---|---|
| `cedrikspotions` | PASS | PASS |  |
| `crazyaudrey` | BLOCKED | PASS | None in the supported bundled scope. |
| `dag` | PASS | PASS | None in supported bundled scope. |
| `darkhorse` | PASS | PASS |  |
| `drinks` | PASS | PASS | None in supported bundled scope. |
| `fairy` | BLOCKED | PASS | None in the supported bundled scope. |
| `findgem` | PASS | PASS | None in supported bundled scope. |
| `findgold` | PASS | PASS | None in supported bundled scope. |
| `foilwench` | PASS | PASS | None in supported bundled scope. |
| `game_dice` | PASS | PASS | None in supported bundled scope. |
| `game_fivesix` | PASS | PASS | None in supported bundled scope. |
| `game_stones` | PASS | PASS | None in supported bundled scope. |
| `glowingstream` | BLOCKED | PASS | None in the supported bundled scope. |
| `goldmine` | BLOCKED | BLOCKED | Complete deterministic reward/loss/death/tether/mount-save/mount-death outcomes using the now-secured settings and mount preference editors. |
| `lovers` | BLOCKED | BLOCKED | Secure conversation/flirt/chat daily stages, POST/CSRF and one-use replay before certifying effects. |
| `outhouse` | BLOCKED | PASS | None in the supported bundled scope. |
| `racedwarf` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `raceelf` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `racehuman` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `racetroll` | BLOCKED | BLOCKED | Complete actual HTTP choice/invalid/forged/POST/CSRF/persistence/location/reselection and later New Day effect matrix. |
| `sethsong` | BLOCKED | PASS | None in the supported bundled scope. |
| `specialtydarkarts` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |
| `specialtymysticpower` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |
| `specialtythiefskills` | BLOCKED | BLOCKED | Complete actual HTTP onboarding and specialty combat level/use/state/POST/CSRF/duplicate authority matrix. |

### Work and proof

**crazyaudrey: PASS.** Village pet/play uses authenticated active-module state, a locked player transaction, typed locked server settings, nonnegative affordable cost/profit, a paidvisit preference, daily played state, POST/CSRF and day/action-bound one-use intents. Historical repeat petting is retained; free or repeated daily basket play is rejected. Configured UTF-8/apostrophe/backslash/markup and escaped buff names pass. Actual HTTP tests cover insufficient funds, forged values, GET/no mutation, CSRF, replay, inactive/anonymous access, final-account-write rollback of account/preferences/profit/debug log, fresh retry and New Day reset. Fixed seeds exercise all basket rewards and both loss/lower-bound paths through Forest and Village callbacks; retained Forest HTTP proves current-event completion/replay. Evidence: test_module_audrey_village_authority and testAudreyDeterministicBasketsAndDailyReset.

**fairy: PASS.** All seven historical outcomes are deterministic: configured turns, net gem reward, max/current HP and specialty skill/use increments. Award boundaries 1 and 5 and both carry flags pass. No-gem give and decline paths complete without reward. Declared settings and bounded accumulated HP fail closed before rewards or recalculation. Real shared-editor invalid/boundary/replay cases and actual Dragon processing prove extra-HP carry/removal. Temporary means maximum HP that does not survive Dragon Kill; Fairy has no separate temporary-HP-only reward. Retained Forest HTTP covers current event, POST/CSRF, completion and replay. Evidence: testFairyConfiguredOutcomesCarryAndInvalidState, test_fairy_settings_and_dragon_carry_http, test_module_purchases_post_csrf_replay_and_effects.

**glowingstream: PASS.** All ten historical rolls are forced with fixed seeds: death retaining gold/experience, near-death HP/turn loss, full healing plus a turn, gem, turn-only and healing-only results. HP/turn lower bounds and decline are asserted. Retained real Forest HTTP proves current-event authority, POST/CSRF, completion and replay. This module declares no configurable effect values, gold gain/loss or other tunable settings; those generic checklist branches are not applicable. Optional travel remains outside bundled scope. Evidence: testGlowingStreamEveryShippedOutcome and test_module_purchases_post_csrf_replay_and_effects.

**outhouse: PASS.** Real HTTP deterministically covers paid/free entry, configured cost, paid gold/gem/turn rewards, no reward, free gold reward/no reward, no-wash penalty and currency floor, exhausted daily visits, fresh-form stage bypass, GET/no mutation, CSRF, duplicate/replay, invalid settings/preferences and actual New Day reset/fresh entry. Locked declared settings and strict used/stage values reject malformed state; intents include account/game-day context. The historical nowash comparison (roll >= badmusthit) is retained. Evidence: test_outhouse_configured_outcomes_http and retained paid/free/wash HTTP.

**sethsong: PASS.** Actual HTTP forces every one of the 19 song results, plus female charm, HP floor, insufficient gold, zero gems and overfull HP. Configured fixed rewards/losses and visit limits, no-effect result, GET/no mutation, CSRF, replay, exhaustion, a second fresh valid visit and New Day reset pass. Declared ranges, paired reward bounds, nonnegative effect/counter values and locked current settings are checked before mutation. One-use intent includes account/game-day context. Historical song content, odds and effect formulas are retained. Evidence: test_sethsong_all_configured_effects_http and retained Seth HTTP/newday hook fixtures.

The random helper is temporary trusted test source, installed only in disposable databases using a conventional module hook and removed after each test. There is no production random override, request parameter, new shipped entrypoint, probability sampling or changed outcome formula. The preserved story text and valid historical effects remain in place. Changes reject invalid state and secure mutation/output boundaries.

### Current core route matrix

Detailed authentication, authorization, typing, SQL, method, CSRF, replay and output columns remain in CORE-ROUTE-SECURITY.md. The current family status is copied here so no unrelated blocker is hidden by module promotions.

| Route/family | Status | Evidence / remaining scope |
|---|---|---|
| runmodule.php / module injection | PASS dispatch; not blanket route certification | Existing module HTTP inactive/force/dependency fixtures |
| Forest: findgem, findgold, foilwench | PASS tested Forest scope | test_module_purchases_post_csrf_replay_and_effects; lifecycle/hooks; PlayerMutationTest |
| Forest: goldmine | PARTIAL; random/state branch and integration review incomplete | Same HTTP test; prior hook fixtures |
| runmodule.php?module=dag | PASS placement | PlayerMutationTest; existing player HTTP; administrator literal-name search |
| Dag pvpwin hook | PASS named operations | Funded HTTP, four rollback points and retry; PvpStateTest |
| Dag manage=true | PASS named operations | Anonymous/ordinary/insufficient/authorized; GET/CSRF/invalid/replay/valid HTTP |
| runmodule.php?module=drinks | PASS named operations | Purchase HTTP; PlayerMutationTest configured boundaries and actual New Day reset |
| Drinks editor | PASS named operations | Actual role/CRUD/revocation/malformed/CSRF/replay matrix; DrinkInputTest |
| runmodule.php?module=cedrikspotions | PASS named potion/persistence operations | Shared settings and full Transmutation/Dragon lifecycle HTTP; exact rollback and retry |
| runmodule.php?module=game_stones | PASS supported game | StonesGameTest; DarkHorseGameTest; full HTTP play/replay/abandonment and final-write rollback |
| Dark Horse entry / exit / bartender / event | PASS supported Dark Horse operations | Mounted/Forest entry, exit, shared settings/mount preferences plus retained wager/information HTTP |
| game_dice / game_fivesix | PASS supported games; other admin editor surfaces remain core blockers | DarkHorseGameTest; actual HTTP progression/replay/rollback; two-server jackpot concurrency; New Day reset hook |
| Crazy Audrey Village and Forest | PASS supported bundled scope | test_module_audrey_village_authority; testAudreyDeterministicBasketsAndDailyReset; retained Forest HTTP |
| Fairy and Glowing Stream Forest | PASS supported bundled scope | Deterministic ModuleCertificationTest outcomes; Fairy editor/Dragon HTTP; retained event HTTP |
| Lovers | BLOCKED | No new closure |
| runmodule.php?module=outhouse | PASS supported bundled scope | test_outhouse_configured_outcomes_http; retained paid/free/wash HTTP |
| runmodule.php?module=sethsong | PASS supported bundled scope | test_sethsong_all_configured_effects_http; retained HTTP and New Day hook |
| newday.php races | BLOCKED | Existing hook/stat fixtures only; actual race choice HTTP matrix absent |
| newday.php + battle.php specialties | BLOCKED | Existing all valid skill buff/uses and reset hook fixtures; route matrix absent |
| mail.php delete/unread | PASS existing scoped operations | Existing mailbox HTTP and ownership service tests |
| mail.php send / address / reply | BLOCKED | Source review case_send.php/case_write.php; no new send/reply HTTP tests |
| lib/systemmail.php | BLOCKED | Source/call-boundary review; no complete helper test suite |
| petition.php intake | PASS existing intake scope | Prior real petition HTTP |
| viewpetition.php | BLOCKED | Source review; intake tests are not admin evidence |
| clan.php / lib/clan/* | BLOCKED | Source clan_membership.php; required role/rank HTTP matrix absent |
| bank.php | BLOCKED | Source review; no new economy rollback/HTTP suite |
| weapons.php / armor.php | BLOCKED | Source review; no purchase HTTP suite |
| stables.php / mounts.php | BLOCKED | Object restoration constrained only; no mount transaction/HTTP suite |
| train.php / masters.php | BLOCKED | Source review + prior stat fixtures; no duplicate advancement test |
| pvp.php / battle.php | PARTIAL core; funded Dag suite PASS | Actual entry/win/invalid eligibility/rollback/retry; defeat, inn/bodyguard, broader combat matrices still OPEN |
| user.php | BLOCKED | Source review; no complete anonymous/player/insufficient/admin matrix |
| creatures.php / armoreditor.php / weaponeditor.php / mounts.php / titleedit.php / taunt.php | BLOCKED | No new full representative editor HTTP matrix |
| Remaining user preferences / modules.php lifecycle editor | BLOCKED | Lifecycle APIs pass; no blanket HTTP editor certification |
| configuration.php core and declared module settings | PASS exercised shared boundary | Actual authorized/ordinary/anonymous/insufficient role; forged namespace/key; malformed/bounds/enum; CSRF; replay; stale; rollback/fresh retry; configured behavior |
| mounts.php shared module preferences | PASS enabled mounts boundary | Dark Horse/Goldmine real HTTP role/CSRF/replay/types/bounds/cross-object/module/deletion/rollback; Drinks object editing stays disabled |

The player-preference injection guard remains PASS for rejecting internal/undeclared/suffixed keys, including Drinks canedit and specialty skill. No bundled player-editable descriptor was enabled. Broader preferences and administrator editor certification remains BLOCKED.

Serialized inventory is unchanged: **87 sites / 42 files / 31 serialize writers / 55 ScalarState reads / one centralized unserialize**. Fairy extra HP now has a bounded nonnegative integer contract (0..4294967295; missing/empty means zero), validated before award/recalculation, with increment overflow rejection. Audrey paid/played, Outhouse used/stage and Seth count are validated narrow daily-state values. General combat, mount, companion, buff, translated mail, preference, editor oldvalue and navigation schemas remain BLOCKED. ScalarState class/size/depth/node/cycle/incomplete-object/canonical-completeness limits remain intact.

Remaining meaningful GET mutations after source rescan: Lovers effects; race/specialty selection and specialty combat; petition status/cleanup; clan membership/rank/removal; weapons/armor buy; stables purchase/sale; training challenge/advancement; legacy editors. Audrey is removed from the current list. Core PvP entry/round/result already uses POST; its remaining full-family blockers do not imply that its protected entry is still a GET mutation. Mail and bank still need their complete enforced input, ownership, CSRF, replay and transaction contracts.

Remaining replay risks: the untouched core families above, Goldmine's full configured outcome matrix, and the documented database/session crash gap. Closed routes have actual duplicate assertions, but no universal crash-safe exactly-once guarantee is claimed. A player transaction cannot make arbitrary hooks, external mail, DDL or optimization rollback-safe.

**Expiration cleanup: BLOCKED, unchanged.** last_char_expire still moves before successful cleanup, and deletion success is logged before final account deletion. **Account deletion: BLOCKED, unchanged.** Veto hooks, related DML and final deletion lack complete failure/retry proof. **Optimization: OPEN.** OPTIMIZE can implicitly commit, and marker/retry/failure observability remains. **External notification: separate, unchanged.** No transactional delivery claim. No cleanup gate is falsely promoted from module daily-hook evidence.

### Exact implementation validation

Both workflows checked out `ae236d69ae856231256625fe33e6f9b5b1dcefd9` explicitly:

| Workflow | Run | Result |
|---|---|---|
| Modern core | [34834000623](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34834000623) | SUCCESS, both supported targets |
| Baseline integrity | [34834000651](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34834000651) | SUCCESS |

| Gate | PHP 8.4.25 / MariaDB 11.4.13 | PHP 8.5.10 / MySQL 8.4.11 |
|---|---|---|
| Job | 103943545770 SUCCESS | 103943545643 SUCCESS |
| PHPUnit | 74 tests / 2,227 assertions / zero skips | Same |
| Python / real HTTP | 29 tests / zero skips / zero failures | Same |
| PHP lint | 308 files / zero failures | Same |
| Legacy PHPStan | Level 0 / seven retained findings / zero new errors | Same |
| Infrastructure PHPStan | Level 6 / zero errors / no baseline | Same |
| Composer | Strict validation, locked install, dependency audit PASS | Same |
| Fresh install / auth / modules / games / scheduler | Retained complete regression PASS | Same |

Baseline-only tooling still has its existing database-class skip because that workflow provides no installed database; neither supported Modern core target skips any test. The complete supplementary local PHP 8.4/MariaDB 10.11 UTC run also passed, 74/2,227 and 29 HTTP/tooling tests. It does not replace supported-target evidence.

Intermediate Modern core run 34833182035 failed because Audrey's new test sorted before the existing account-creation fixture on both targets, and an existing Dragon persistence fixture could inherit a dead actor on MariaDB. The third source/test commit places Audrey after account creation and explicitly initializes both Dragon fixtures alive, retaining all prior assertions. Targeted testing caught and fixed Audrey buff-name HTML output and stack leakage during injected final-write failure before final acceptance. Local exploratory online-state failures were resolved by aligning the disposable database to UTC and validating from a clean checkout. No intermediate failed/superseded run is reported as a final pass.

### Preservation, boundary and next step

Historical integrity is PASS: tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`; source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`; root tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`; **417 files / 11 preservation commits**. Historical repositories jimlunsford/lotgd and jimlunsford/lotgd-modules were not modified.

VPS untouched. No public runtime, deployment, release, release tag, ZIP or deployment package. No archive-only module import, new modernization branch, PR #2, framework/UI rewrite or gameplay rebalance. Disabled LoGDnet, console, payments, source viewer and recovery remain disabled.

**Modern-core merge verdict: NO. Public-hosting verdict: NO. PR #1 remains OPEN/DRAFT/unmerged.**

**Exact next recommended phase: continue Phase 3 on this same branch and PR, first Goldmine's remaining reward/loss/mount/tether/racial matrix and Lovers' authoritative conversation stage machine, then the four race and three specialty onboarding/combat gates, followed by combat schemas and the recorded core/cleanup families. Do not start Phase 4.**


## Goldmine deterministic HTTP closure, 2026-09-14

Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection

- Actual starting SHA: `e04a61795e791ad73354cea793055acca16e00b8`, verified against live GitHub before edits.
- Implementation SHA: `58b9badd48aabb64eb572097439a7242ccb75b66`; one appended implementation commit, followed by this evidence commit. The ending SHA is the commit containing this section; its exact SHA and final validation run IDs are recorded in PR #1 and the execution report after publication (a commit cannot contain its own SHA).
- Existing branch: `modernization/core-modernization`. No new branch or PR.
- Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`.
- PR #1 remains OPEN, DRAFT, NOT READY and NOT MERGED. No actual merge SHA. GitHub's temporary test-merge object is not a completed merge.
- Publication used the authenticated GitHub Git-object API after ordinary Git HTTPS push had no credentials. Published tree was checked against the tested local tree before the non-forced branch update. All published history was preserved.

### Scope completed

Goldmine promoted independently: **16 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 8 BLOCKED; all 24 lifecycle PASS**. Both certification records updated.

PASS in supported bundled scope: actual Forest HTTP forces all 20 historical mining rolls, no reward, gold, gems, combined rewards, cave-in death, exact configured gold/gem loss (0/25/50/100 percent across HTTP and retained unit evidence), no mount, tether, auto-tether, mount survival/death/player save, and rescue via all four shipped race hooks. Validated declared settings and mount preferences, current mount record and race rescue settings bind the one-use event intent and are rechecked under transaction locks. GET preserves gameplay state; anonymous/inactive, malformed action, missing/stale mount, malformed preferences/settings, stale form and replay checks pass. Final-account-write failure rolls back account/news/debug writes; failed intent remains consumed and fresh retry succeeds. Story output retains color formatting and escapes HTML. No random distribution test or gameplay rebalance.

The new evidence also found and fixed two shared-dispatch defects: inactive secured events were silently cleared, and database failure escaped as a PHP error page. Inactive events now reject without consuming pending state; failed transactional execution returns a controlled failure response. Account/news/debug rollback and a fresh retry are proven through actual HTTP.

### Supported-target evidence

Implementation `58b9badd48aabb64eb572097439a7242ccb75b66`:

| Gate | PHP 8.4 / MariaDB 11.4 | PHP 8.5 / MySQL 8.4 |
|---|---|---|
| PHPUnit | 74 tests / 2,227 assertions | 74 tests / 2,227 assertions |
| Python / HTTP | 30 tests / PASS | 30 tests / PASS |
| Supported matrix skips / failures | 0 / 0 | 0 / 0 |
| PHP lint | 308 / zero failures | 308 / zero failures |
| Legacy PHPStan | Level 0, seven retained findings, zero new errors | Same |
| Infrastructure PHPStan | Level 6, zero errors, no baseline | Same |
| Composer | Strict validation, locked install and audit PASS | Same |

- Modern core: [34837310102](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34837310102), SUCCESS, both targets explicitly checked out the implementation SHA.
- Baseline integrity: [34837310095](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34837310095), SUCCESS.
- Full supplementary local UTC regression also PASS: 74 tests / 2,227 assertions and 30 Python/HTTP tests. Local MariaDB 10.11 is supplementary, not a replacement for supported-target CI.
- Intermediate failures are not acceptance evidence. Corrected fixture mount ID to the actual tinyint column range; isolated stale temporary fixture/configuration files in the local runner; corrected local timezone alignment. The new inactive-event and exception-output failures drove production fixes. No coverage was removed.

### Remaining requested modules and core gates

Eight requested module certifications remain unfinished: Lovers, Human, Elf, Dwarf, Troll, Dark Arts, Mystical Powers and Thieving Skills. Their statuses remain BLOCKED. No new Lovers progression/effect/persistence/reset certification, race onboarding/forgery/reselection/replay HTTP certification, or specialty onboarding/levels 1/2/3/5/insufficient-use/wrong-state/replay certification is claimed. Existing runtime/stat/daily-hook evidence remains intact.

General combat schema is still BLOCKED and unchanged. Remaining meaningful GET families: Lovers, race/specialty onboarding, specialty combat, petition administration, clans, equipment, stables, training and legacy editors. Replay authority in these unclosed routes remains unproven. The new session intents do not claim universal crash-safe exactly-once execution.

Serialized inventory unchanged: **87 sites / 42 files / 31 serialize writers/checks / 55 ScalarState reads / one centralized unserialize**. No serialized trust boundary or count changed; the serialized-state audit was not rewritten. Goldmine's declared configuration/preferences validation does not certify general combat, mount buff, companion, mail, editor or other serialized business schemas.

Core blockers remain general combat, mail send/reply and systemmail caller/content/translation trust, petition administration, clans, bank, weapons/armor, mounts/stables, training/masters, broader PvP, administrator/content editors, serialized business schemas, expiration cleanup and account deletion. `last_char_expire` still advances before cleanup completes. No cleanup/deletion or external-hook rollback claim is made.

Historical baseline unchanged: annotated tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 preservation commits. Both historical repositories untouched. No archive imports, VPS access, deployment, public runtime, release, version tag, package, gameplay rebalance, framework rewrite or restored disabled feature.

**Phase 3 remains NOT COMPLETE. Modern-core merge: NOT READY. Public hosting: NO.**

Exact next Phase 3 step: close Lovers' real entry/choice/daily-consumption contract through POST/CSRF and server-owned progression, both Seth and Violet paths, married and unmarried effects, persisted visit exhaustion, New Day reset, replay and transactional failure/retry. The historical `flirt=1..7` values are alternative choices, not sequential stages; preserve those choices rather than imposing a new seven-stage story. Then continue Human, Elf, Dwarf, Troll and the three specialty boundaries in the requested order.

### Final-validation regression correction

The evidence-only head `80c493cfc9e6cd4b0d15e6acb11cf81df2ab9fc1` passed PHP 8.4 and baseline integrity 34838004154, but Modern core 34838004195 failed its PHP 8.5 target in the existing `test_potions_dragon_reset_persistence_http`: the unseeded fight produced the death/news path rather than the required victory/prologue link. Goldmine's deterministic test passed on that target. This failed run is not final acceptance evidence.

Both related Fairy and potion Dragon carry/reset fixtures now seed the real Dragon HTTP battle through the already-existing test-only random hook. All existing victory, persistence, carry/reset and buff-removal assertions remain. No production combat formula or randomness changed. Targeted actual HTTP for both fixtures and all 74 PHPUnit tests / 2,227 assertions pass locally.

This is the third appended commit in this execution. The ending SHA is now the commit containing this addendum; PR #1 and the final execution report record its literal SHA and exact-head supported-matrix run IDs after publication. Certification remains 16 PASS / 0 limited / 8 BLOCKED. Phase 3 remains incomplete, merge NOT READY and public hosting NO.


### Certification summary reconciliation

Reconciled the JSON aggregate counts and top-level workflow pointers with the already-updated per-module decisions: 16 PASS / 0 limited / 8 BLOCKED, all 24 lifecycle PASS. The validated implementation pointer is `58b9badd48aabb64eb572097439a7242ccb75b66`, Modern core 34837310102, baseline 34837310095 and supported-target jobs 103953960563 / 103953960872. Earlier aggregate pointers were stale; no certification decision changed in this reconciliation. This fourth appended commit is the final execution head; its literal SHA and final supported-matrix evidence are recorded in PR #1 and the execution report.


## 2026-09-14: Lovers certified independently before race completion

Actual starting SHA: `f282bc5c2b1cca7dba1755f74150a95bb1621c06`. Published Lovers implementation ending SHA: `72a43cc0cfd4e6e653a943a5377d7adbfc4139db`, one appended implementation commit. This evidence update follows that commit; subsequent race work is recorded separately. Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`; PR #1 OPEN/DRAFT/NOT READY/NOT MERGED, no actual merge SHA.

Lovers is PASS: 17 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 7 BLOCKED; all 24 lifecycle checks PASS. The four races and three specialties remain BLOCKED at this checkpoint. `flirt=1..7` remain alternatives. Actual HTTP covers both NPC paths, married/unmarried positive/no-effect/negative effects, read-only entry, bounded input, daily consumption, persistent account/buff/news/debug state, subsequent authentication, New Day reset, married attrition/divorce, replay/stale form, three final-account rollback cases and fresh retry. Failed intents remain consumed; no universal crash-safe exactly-once claim.

Both supported targets explicitly checked out `72a43cc0cfd4e6e653a943a5377d7adbfc4139db` and passed: 74 PHPUnit tests / 2,227 assertions; 31 Python/HTTP tests; 308 linted PHP files; zero failures/skips. Legacy PHPStan level 0 retains seven findings with no new errors; infrastructure level 6 has no errors/baseline. Composer strict validation, locked installation and audit PASS. Modern core 34841113158 SUCCESS (PHP 8.4/MariaDB job 103966069936; PHP 8.5/MySQL job 103966069657); Baseline integrity 34841112989 SUCCESS. Local results are supplementary. Initial navigation-counter/story-assertion fixture failures and a temporary leftover fixture-file failure are not acceptance evidence; no coverage was removed.

Historical tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files and 11 commits remain unchanged. Historical repositories untouched. Git HTTPS push had no credentials; authenticated Git objects matched the local tree and appended history with a non-forced branch update.

Lovers effect GETs are closed. Race/specialty onboarding, specialty combat, petition administration, clans, equipment, stables, training and legacy editor GET families remain. General combat schema and the prior mail/systemmail, bank, PvP, administrator, serialized-business-state, expiration and deletion gates remain unresolved. Serialized inventory is unchanged: 87 sites / 42 files / 31 writers/checks / 55 ScalarState reads / one centralized unserialize.

Phase 3 remains incomplete; modern-core merge NO; public hosting NO. No VPS, deployment, public runtime, archive import, release, tag, package, rebalance, framework rewrite or Phase 4 work. Next: finish shared HTTP race onboarding and independently promote Human, Elf, Dwarf and Troll, then specialty onboarding/combat authority.


## 2026-09-14: Lovers and all four races certified; specialties remain blocked

### Published history and scope

Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection. Existing branch `modernization/core-modernization`; existing PR #1 only.

Actual starting SHA: `f282bc5c2b1cca7dba1755f74150a95bb1621c06`.

Validated implementation ending SHA: `77c58792f87afad71c0db5fc77042cd39ab418b8`.

Three commits precede this final evidence append: `72a43cc0cfd4e6e653a943a5377d7adbfc4139db` (Lovers implementation), `aad653aed8eb7f97f35f90411b4f67aa9df99567` (independent Lovers certification), and `77c58792f87afad71c0db5fc77042cd39ab418b8` (shared race implementation). This evidence append is the fourth commit. Its enclosing commit supplies the final repository ending SHA; that SHA and the final exact-head workflow IDs are recorded in PR #1 and the execution report, avoiding a self-referential commit hash inside this file.

Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. PR #1 remains OPEN, DRAFT, NOT READY and NOT MERGED; no actual merge SHA. Published history is preserved, without force-push, squash, another branch or PR #2. Git HTTPS had no credentials; each authenticated Git-object tree matched the staged local tree before a non-forced update.

### Certification decisions

**21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED. All 24 lifecycle checks PASS.** All individual final statuses are maintained in BUNDLED-MODULE-CERTIFICATION.md and .json.

Lovers, Human, Elf, Dwarf and Troll are PASS. The sixteen previous PASS decisions are retained. Dark Arts (`specialtydarkarts`), Mystical Powers (`specialtymysticpower`) and Thieving Skills (`specialtythiefskills`) remain BLOCKED. Five of the eight requested modules closed; the eight-module objective is not complete.

Lovers preserves seven alternative choices, not sequential stages. Actual Inn-link/form HTTP proves living authenticated/active/current-daily entry, read-only GET including absent default preference and married entry, every choice on both Seth/Violet paths, married positive/negative effects, unmarried positive/no-effect/negative branches, charm caps, turn exhaustion floors, marriage success/failure, derived buffs/news/debug, daily use once, malformed/forged/stale/replayed submissions, database persistence and subsequent login, actual New Day reset, married attrition/divorce, final-account rollback and fresh retry. Configured apostrophe, backslash, UTF-8 and HTML-like text retain LoGD formatting and escape markup. All three meaningful rollback fixtures preserve preference/player/news/debug state. The one-use intent is consumed on failure; retry requires a fresh form. No universal crash-safe exactly-once guarantee.

Each race independently passes real HTTP display, POST, CSRF, active module, server choice identity, exact stored race/location, invalid/forged race/module/filename/location/stat fields, anonymous/inactive/stale form denial, duplicate/reselection/replay rejection and GET rejection. No inactive-module Human fallback writes through GET. Race and location persist in an atomic account UPDATE; a CHECK failure leaves both unchanged, and a fresh form retries successfully. Valid forms traverse forced navigation without fixture authorization of the submitted mutation. The transmutation test retains all effect/persistence assertions and now uses a real race POST form.

The destination remains the configured main village because Cities is not bundled. No Cities import or invented home-city preference. Human's daily Forest-fight bonus, Elf's defense/PvP/New Day effects, Dwarf's gold/location behavior and Troll's attack/PvP/New Day effects remain covered by existing runtime tests, with formulas unchanged. The new shared boundary is also analyzed at infrastructure PHPStan level 6.

### Remaining specialty and combat gates

No new specialty onboarding or combat HTTP certification is claimed. All three still need active/typed selection, identity/initial skill/uses persistence, replay/reselection, and valid combat levels 1/2/3/5 plus unsupported/negative/excessive/non-numeric levels, zero/insufficient uses, wrong/no specialty, wrong player state, missing/invalid/terminal combat, POST/CSRF, duplicate/replay and persisted historical use cost/effect authority. Existing hook/formula and internal-preference injection regressions still pass; they do not substitute for these HTTP boundaries.

The route review identifies shared `lib/newday/setspecialty.php` onboarding and combat across `battle.php`, `lib/battle-skills.php`, `lib/fightnav.php` and the three specialty modules. Dark Arts also has the shipped companion-enabled path, which must not be omitted. General combat business validation above ScalarState is not added; it remains BLOCKED. No later core family is promoted.

### Acceptance evidence

Both supported targets explicitly checked out `77c58792f87afad71c0db5fc77042cd39ab418b8`:

| Gate | PHP 8.4.25 / MariaDB 11.4.13 | PHP 8.5.10 / MySQL 8.4.11 |
|---|---|---|
| PHPUnit | 74 tests / 2,231 assertions | Same |
| Python / actual HTTP | 32 tests / PASS | Same |
| Failures / skips | 0 / 0 | 0 / 0 |
| PHP lint | 309 files / PASS | Same |
| Legacy PHPStan | Level 0; seven retained findings; zero new errors | Same |
| Infrastructure PHPStan | Level 6; zero errors; no baseline | Same |
| Composer | Strict validation, locked install, audit PASS | Same |

Modern core **34842503298 SUCCESS**, jobs **103970582048** (PHP 8.4/MariaDB) and **103970582001** (PHP 8.5/MySQL). Baseline integrity **34842503326 SUCCESS**. Fresh install, authentication, all 24 module lifecycles, certification, Lovers, races, scheduler/New Day and all retained specialty/formula/security regressions pass. Earlier independent Lovers proof: Modern core 34841113158 and Baseline integrity 34841112989 at `72a43cc0cfd4e6e653a943a5377d7adbfc4139db`.

The full supplementary local run passes 74 tests / 2,231 assertions and 32 Python/HTTP tests, plus 309-file lint and both PHPStan scopes. Its MariaDB 10.11 is supplementary only; it is not substituted for the supported matrix. An intermediate full run exposed the retained transmutation test's old GET race selection, now corrected without deleting assertions. Initial navigation/story fixture mismatches, temporary stale fixture files, missing static symbol discovery and a transient local execution-service outage are not acceptance evidence.

### Remaining mutations, preservation and verdicts

Meaningful GET families remain specialty onboarding/combat, petition administration, clans, equipment, stables, training and legacy editors. The focused rescan also found the separate New Day dragon-point allocation `dk` GET path; it is documented as an unresolved core gate, not part of race selection PASS. Existing protected PvP entry/round/result boundaries are retained, while broader PvP remains blocked. Mail send/reply/systemmail trust, bank, administrator/content editors, remaining serialized schemas, expiration cleanup and account deletion semantics remain unresolved. `last_char_expire` still advances before cleanup completes. Unclosed core replay/rollback claims remain unproven.

Serialized inventory unchanged: **87 sites / 42 files / 31 writers/checks / 55 ScalarState::read calls / one centralized unserialize**. No generic ScalarState weakening, new combat schema or cosmetic serialized-audit rewrite.

Historical baseline unchanged: annotated tag `historical-source-1.1.2`, tag object `51cab4fbe58a234651a3177a56289b18bc152b4d`, source `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, **417 files / 11 preservation commits**. Both historical repositories untouched by this execution.

VPS untouched. No deployment, public runtime, release, version tag, ZIP/package, archive import, gameplay rebalance, UI/framework rewrite or Phase 4. Dangerous-feature disablement remains intact.

**Phase 3: INCOMPLETE. Modern-core merge: NO. Public hosting: NO.** Exact next Phase 3 step: finish the shared specialty onboarding contract, then prove Dark Arts, Mystical Powers and Thieving Skills combat authority through actual HTTP (including Dark Arts companion behavior), the minimum required combat schema, and transaction/replay/failure persistence. Only after those three certifications close should general combat schema work continue in the requested order.

## 2026-09-14 specialty onboarding continuation

Actual published starting SHA: `33a6cf447bcbda8292283d85f0c6dcc103c51d5d`. Main verified unchanged at `999cec6f9c655a840320d982d673bb863c68c2b2`. PR #1 verified OPEN/DRAFT/unmerged. Historical annotated tag verified at `51cab4fbe58a234651a3177a56289b18bc152b4d`.

Shared onboarding now uses the existing CSRF, intent and player transaction. All three choices have actual HTTP tests for server-owned initial and earned preferences, exact specialty persistence, forged/inactive/missing choices, duplicate/reselection/stale forms, late-write rollback, fresh retry and actual New Day restoration. Details and actual combat architecture are in SPECIALTY-AUTHORITY.md.

Local retained PHPUnit: 74 tests / 2,231 assertions PASS. Initial full local Python run found a routing rejection-status regression in the race adversarial test; corrected dispatch precedence retains the original assertion. The expanded specialty and retained race HTTP tests then PASS. Both PHPStan configurations pass locally. Supported exact-head CI remains required, not replaced by local MariaDB 10.11.

All three specialties remain BLOCKED for actual combat levels 1/2/3/5, exact cost/effect persistence, insufficient/zero uses, wrong specialty, malformed/terminal/stale combat, replay and rollback. Dark Arts companion authority/schema remains BLOCKED. Counts remain 21 PASS / 0 limitation / 3 BLOCKED; 24/24 milestone NOT reached. General combat schema remains BLOCKED, so dragon-point/mail and later priorities are not advanced.

Remaining meaningful GET mutations: specialty combat; New Day dragon points; petition administration; clans; weapons/armor; stables; training; legacy editors. Remaining major core gates also include mail/systemmail, bank, broader PvP, administrator/content editors, remaining business schemas, expiration cleanup (`last_char_expire` advances early) and account-deletion failure semantics. Existing generic ScalarState and 87/42/31/55/1 inventory unchanged. No universal exactly-once claim.

Ending SHA, commit total and supported CI IDs will be recorded after publication and acceptance. Phase 3 INCOMPLETE; modern-core merge NO; public hosting NO. Historical repositories/VPS untouched. No deploy, public runtime, release, archive import, formula rebalance, Phase 4, new modernization branch or PR #2.

### Specialty onboarding accepted, final publication receipt

Implementation ending SHA: `82bb591d5355ccd0e5a071f1d4fd3efd7033c7ba`. Both supported targets have passed this exact implementation. Modern core **34886260212 SUCCESS**; Baseline integrity **34886260167 SUCCESS**. Jobs **104117559745** (PHP 8.4.25 / MariaDB 11.4.13) and **104117559301** (PHP 8.5.10 / MySQL 8.4.11) explicitly checked out that SHA. Each reports **74 PHPUnit tests / 2,231 assertions / 33 Python and HTTP tests / 310 PHP files linted / zero failures / zero skips**. Legacy PHPStan level 0 retains seven baseline findings with zero new errors; infrastructure level 6 passes without a baseline. Composer strict validation, locked install and audit all PASS. All 24 lifecycle checks retain PASS.

This final evidence commit follows the implementation commit, for **two appended commits** from the starting SHA, preserving history. It also makes the forged non-specialty-choice fixture explicitly name the installed Drinks module. The final ending SHA and its exact-head CI IDs/results are recorded in PR #1 and the execution report after publication; this source checkpoint does not attempt to embed its own self-referential commit hash.

Shared specialty onboarding is **PASS**, independently covering DA, MP and TS. Initial skill/uses are server-owned zero defaults; existing earned preferences survive legitimate specialty clearing. No client skill, use, preference key, module name or filename authorizes state. Reselection, replay, stale forms, missing/inactive modules, missing source files and malformed stored preferences fail closed. Late account-write failure rolls back the preceding preference writes and specialty; the failed intent remains consumed and fresh-form retry works. Actual New Day restores `floor(skill/3)+specialtybonus` for the selected specialty, and preserves its identity. Existing deterministic Dragon-reset hook coverage remains unchanged.

**The requested specialty combat objective is not finished.** Dark Arts, Mystical Powers and Thieving Skills each remain **BLOCKED**, including levels 1/2/3/5 at the HTTP boundary, insufficient/zero/negative/malformed uses, wrong/no specialty, absent/malformed/terminal/dead/impossible/stale combat, exact persisted cost/effect, replay and combat rollback. Dark Arts companion creation, persistence, combat effect, death/removal, malformed state and rollback are not certified; its business validator is not implemented. The trace records its real fields and formulas but does not certify them.

No 24/24 milestone: **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. All 24 final statuses remain in BUNDLED-MODULE-CERTIFICATION.md and JSON. General combat schema, fields validation, malformed-state rejection, normal persistence/termination remain **BLOCKED**. ScalarState is unchanged; inventory is **87 sites / 42 files / 31 writers/checks / 55 reads / one unserialize**. Neither a combat nor companion business schema was added.

New Day dragon-point allocation remains **BLOCKED**, including the GET `dk` path and bulk `pdk` authority. Meaningful remaining GET families: specialty combat, dragon-point allocation, petition administration, clans, weapons/armor, stables, training and legacy editors. Unclosed core replay/failure semantics remain unproven. Mail send/reply/systemmail, petitions, clans, bank, weapons/armor, mounts/stables, training/masters, broader PvP and administrator/content editors all remain **BLOCKED**. Existing protected PvP entry/round/result evidence is retained. Expiration cleanup is **BLOCKED** because `last_char_expire` still advances before completion; account-deletion failure semantics remain **BLOCKED**.

PR #1 remains OPEN, DRAFT, NOT READY and NOT MERGED, with no actual merge SHA. Main remains `999cec6f9c655a840320d982d673bb863c68c2b2`. Historical tag/source/tree remain `51cab4fbe58a234651a3177a56289b18bc152b4d` / `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2` / `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, with 417 files and 11 preservation commits. Historical repositories and VPS were not touched. No public runtime, deployment, release, version tag, package, archive import or Phase 4 work.

**Phase 3 INCOMPLETE. Modern-core merge NO. Public hosting NO.** Exact next Phase 3 step: secure shared specialty combat authority across the actual battle callers, starting with Dark Arts level 1 companion behavior, validating business state before consumption and enclosing use/effect/combat/outcome persistence in the existing transaction without nesting PvP transactions. Prove the HTTP matrix before promoting any specialty.


## Dark Arts companion business-state implementation, 2026-09-14 continuation

Starting SHA: `e70429e22d7c0c47570db998e609677c5dcfa2a2`. `SkeletonCompanionState` validates the named skeleton above unchanged ScalarState: exact required fields/text/abilities/ignorelimit, only optional boolean used/suspended, finite positive bounded statistics, current HP at most max HP, and attack/defense/max HP consistent with the historical creation formula. Creation level is recovered from max HP, preserving companions across later player training. No lifetime counter, death immunity, extra modifier, arbitrary ability or nested extension is accepted. Half-point combat statistics remain unchanged. Other named companion arrays are NOT thereby business-certified.

Common hydration rejects malformed encoded maps and skeleton state with a controlled 409 before route gameplay. It preserves the stored blob for explicit repair, with no silent deletion or normalization. The apply_companion producer/fallback also validates skeleton state. Runtime death/removal, combat victory retention, New Day retention and Dragon clearing retain their historical implementation; complete HTTP lifecycle/rollback/replay authority is still pending.

Added SkeletonCompanionStateTest and test_darkarts_companion_business_state_http. The HTTP fixture creates a real skeleton through the existing historical Forest action and checks exact cost 1 (5 to 4), formula statistics, used flag, subsequent login/read, injured/suspended states, malformed stored matrices and unchanged game state on rejected GET/POST. This does NOT certify the historical GET action, stale-form protection, general combat state, or transactional specialty use. CI acceptance pending at this implementation checkpoint. All three specialties remain BLOCKED; **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. Phase 3 incomplete, merge NO, hosting NO.

Published ending SHA and exact-head workflow results will be recorded after CI. Main verified unchanged at `999cec6f9c655a840320d982d673bb863c68c2b2`; PR #1 OPEN/DRAFT/unmerged. Historical repositories/tag and VPS untouched. No deployment/release/Phase 4.


## Forest specialty transaction implementation

The next implementation adds `forest.php?op=specialty`, shared by DA/MP/TS. GET renders forms, while legacy Forest `skill`/`l` URLs reject before a round. POST requires CSRF and the existing one-use intent bound to player/combat/buff/companion/specialty preference state. The transaction rechecks installed active bundled module, selected identity, strict stored skill/uses, supported level 1/2/3/5 and live Forest state; the request supplies only the level. Existing effect hooks receive the server-resolved identity. Forest round and victory/defeat processing share the existing player transaction. Defeat gains an optional non-flushing mode so the callback can commit before page_footer. Terminal state is cleared inside this specialty transaction. Failed consumed intents require a new form.

`SpecialtyCombatState` is a narrow Forest specialty validator, NOT the general combat schema. It checks exact root/options/enemy key vocabularies, creature identity/name/weapon, bounded HP/attack/defense/level/rewards/player-start HP, finite flags, supported target and reward-map structure. It currently rejects encounters containing dead enemies, rather than claiming multi-target progression certification. Other core combat routes still need their real consumer schemas. No new field/formula/reward value is introduced.

HTTP tests added for all twelve Forest specialty actions: exact use cost and persisted combat/account read, duplicate/replay, late account-write failure after uses and fresh retry; plus stored preference, selected/inactive specialty, level, injected effects, CSRF, combat/target/stale state, anonymous and GET matrices. Exact effect/companion lifecycle/New Day after use and Dragon/other eligible route closure remain incomplete. No specialty promotion. CI pending for this implementation; retained earlier evidence is not represented as acceptance of this new tree. Phase 3 INCOMPLETE, module counts 21/0/3, merge NO, hosting NO.

Companion-only implementation `78696d40470c89df8fd8371ed8387a7af6395c4a` passed Modern core 34889623000 and Baseline integrity 34889623022: both supported targets, 78 tests / 2,306 assertions / 34 Python-HTTP tests / 312 lint files, zero failures/skips. Forest implementation `f882c352a76370c94d0e963969b830a5d8b2ceb4` passed 80 PHPUnit tests / 2,340 assertions and lint, but Modern core 34890634735 failed infrastructure PHPStan before HTTP: missing scanned outcome symbols, include-assigned boolean types, and a redundant positive enemy-count check. These are corrected without ignoring findings or weakening the gate; Baseline integrity 34890634759 passed. Published failure remains in history.

The corrected implementation `33b5408493353c3dc6a45cdbccfee802cd894ef2` passed strict analysis but Modern core 34890886760 exposed two HTTP fixture expectations: MP regeneration retains the PDO-loaded level string "10", and anonymous forced navigation uses the retained 302 redirect. No effect formula or redirect implementation was changed to satisfy the tests. Dark Arts companion HTTP passed; remaining completed Forest matrices passed before the final redirect assertion. Baseline integrity 34890886751 passed. Corrections retain exact persisted value/type expectations. Additional pending checks cover deterministic Voodoo terminal clearing, skeleton victory/New Day retention, the companions-disabled fallback, a fresh-intent insufficient budget distinct from stale preferences, and required diddamage/integral target identity.
