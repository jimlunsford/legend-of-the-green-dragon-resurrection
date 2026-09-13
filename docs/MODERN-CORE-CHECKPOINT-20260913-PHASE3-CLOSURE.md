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
