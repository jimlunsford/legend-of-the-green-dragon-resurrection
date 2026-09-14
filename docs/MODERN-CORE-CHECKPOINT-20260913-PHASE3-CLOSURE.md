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
