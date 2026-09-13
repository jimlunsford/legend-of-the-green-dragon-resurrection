# Modern-core checkpoint: 2026-09-13, Phase 3 continuation

## Decision

**Modern-core merge verdict: NOT READY. Public hosting: NO. Outcome C.** PR #1 remains OPEN, DRAFT and NOT MERGED. It was not marked ready. Significant route/mutation security work remains. Passing lifecycle and representative hook tests do not certify every module route or make the modern core mergeable.

This checkpoint preserves useful Phase 3 implementation and records exact remaining work. It does not overwrite the authoritative [Phase 2 checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE2.md) or the Phase 1 checkpoint. Continue the same branch and PR.

## Repository state

- Repository: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection
- Starting `main`: `999cec6f9c655a840320d982d673bb863c68c2b2`.
- Starting Phase 3 branch: `19111e51c407960a0cdc848b4509a5e586313392`.
- Branch: `modernization/core-modernization`.
- Implementation ending SHA: e5e9193aba19e7d98ffa5a2c558eda2b9c96ca52.
- Ending branch SHA: the commit containing this final checkpoint, resolved with `git log -1 --format=%H -- docs/MODERN-CORE-CHECKPOINT-20260913-PHASE3.md`. The exact final SHA and its subsequent CI run IDs are also recorded in PR #1 and the execution report; embedding this file's own commit hash would be self-referential.
- Commits added: 11 (10 implementation/test commits plus this documentation checkpoint), preserving all prior modernization history.
- PR: https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/pull/1
- PR state: OPEN / DRAFT / NOT MERGED. No ready transition, no merge commit. GitHub's synthetic test-merge ref is not an actual merge.
- Ending `main`: `999cec6f9c655a840320d982d673bb863c68c2b2`, unchanged.

Live GitHub state was checked before edits. The branch matched the requested Phase 2 SHA, so no later valid work was reset. GitHub API writes fast-forwarded only the existing branch. No new modernization branch or PR was created.

## Bundled module certification

The actual `modules/*.php` set is exactly the 24 requested entrypoints. See [readable certification matrix](BUNDLED-MODULE-CERTIFICATION.md) and [machine-readable matrix](BUNDLED-MODULE-CERTIFICATION.json). They record historical versions, lifecycle, dependencies, registrations, settings, preferences, object preferences, routes, schema, daily/combat/location participation, warnings and per-platform evidence.

**24 lifecycle PASS; 0 fully certified PASS; 0 PASS WITH DOCUMENTED LIMITATION; 24 overall BLOCKED.** Overall BLOCKED means required security/execution evidence is incomplete, not that activation failed. Every module installs, activates, deactivates, reinstalls, reactivates and participates in the tested combined set. There is no technical activation conflict requiring an invalid combination or a dependency bypass.

| Module | Version | Lifecycle / metadata | Full certification |
|---|---|---|---|
| cedrikspotions | 2.6 | PASS | BLOCKED |
| crazyaudrey | 1.1 | PASS | BLOCKED |
| dag | 1.3 | PASS | BLOCKED |
| darkhorse | 1.1 | PASS | BLOCKED |
| drinks | 1.1 | PASS | BLOCKED |
| fairy | 1.1 | PASS | BLOCKED |
| findgem | 1.1 | PASS | BLOCKED |
| findgold | 1.1 | PASS | BLOCKED |
| foilwench | 1.1 | PASS | BLOCKED |
| game_dice | 1.1 | PASS | BLOCKED |
| game_fivesix | 1.7 | PASS | BLOCKED |
| game_stones | 1.1 | PASS | BLOCKED |
| glowingstream | 1.1 | PASS | BLOCKED |
| goldmine | 1.0 | PASS | BLOCKED |
| lovers | 1.0 | PASS | BLOCKED |
| outhouse | 2.0 | PASS | BLOCKED |
| racedwarf | 1.1 | PASS | BLOCKED |
| raceelf | 1.0 | PASS | BLOCKED |
| racehuman | 1.0 | PASS | BLOCKED |
| racetroll | 1.0 | PASS | BLOCKED |
| sethsong | 1.1 | PASS | BLOCKED |
| specialtydarkarts | 1.1 | PASS | BLOCKED |
| specialtymysticpower | 1.0 | PASS | BLOCKED |
| specialtythiefskills | 1.0 | PASS | BLOCKED |

### Implemented and exercised

- Actual lifecycle state, disabled injection, registered callbacks, event registrations, missing/inactive/insufficient/malformed/cyclic dependency handling, dependent/preload cache invalidation, reinstall registration equality, preserved configured values and no duplicate hooks.
- All declared named settings and user preferences: defaults and raw apostrophe/backslash/UTF-8 round trips. Dark Horse mount object preference controls event chance immediately. These API tests do not certify all administrator preference forms or object-preference input boundaries.
- All 24 active together, combined hook ordering by the runtime, race names and specialty identities, race stat benefits, setting/location changes, New Day resets, health recalculation, Inn/Forest navigation, Dag no-bounty PvP behavior, Drinks quota/graveyard state and the module-defined Dark Horse game hook.
- Eight forest and four travel events collected from real metadata. Findgem and Findgold rewards, other forest entrances, Foil Wench gift/skill increment and Goldmine decline exercised.
- Reinstall preserves the strict InnoDB/utf8mb4 Dag bounty and Drinks tables and existing fixture rows/default drink records. Fresh install remains 38 tables and all modules inactive.
- Runtime fixes include Dag's uninitialized no-bounty totals, Drinks' misspelled quota-text variable and missing partner initialization, bound race-location changes, the Dwarf companion-location field and Human singular/plural wording. No intended gameplay value was changed.

- Real authenticated HTTP navigation with all 24 active: Village, Inn and Forest; issued Dag/Lovers/Seth direct routes and return-to-Inn links; account hydration and historical output without PHP warnings. Full action security remains unproven.
- All three specialties: choice navigation and valid levels 1/2/3/5 construct expected buffs or the Dark Arts companion and consume the expected uses. All four races: choice text/navigation; Elf/Troll actual PvP/stat adjustment +3 at level 10, preserving behavior.

### Remaining module blockers

- All direct value-changing routes need method/CSRF/replay/authorization and output tests. Known GET mutation paths remain, including specialty combat actions, Foil Wench gifting and multiple purchase/play paths. Navigation enforcement is not a replacement for POST/CSRF or business authorization.
- Dag bounty placement/claims/admin paths and actual funded PvP claims; Drinks purchase/use/editor paths; Cedrik potion purchases; Lovers/Seth conversations and random effects; Outhouse and remaining forest outcomes need full certification.
- Dice/Five-Six/Stones direct random-game execution and state round trips are incomplete. Stones still calls unvalidated `unserialize` on `specialmisc`; validate a bounded scalar schema and disallow object instantiation before certifying it.
- Race selection/location text and specialty initialization need complete HTTP onboarding coverage. Full battle, battle-victory/defeat, training and PvP integration are not certified by constructing buffs. Optional Cities behavior must remain outside archive scope.
- Historical-column upgrades and destructive uninstall/recovery were not exercised. Reinstall on the modern fresh schema passes. No module was labeled fully PASS merely for installing.

## Expression execution audit

See [complete inventory and semantics](EXPRESSION-EVALUATION-AUDIT.md).

| Original site | Classification | Remediation |
|---|---|---|
| Module event chance eval | A, with B historical intent | Finite numeric vocabulary and named Dark Horse condition |
| Module whenactive eval | A, with B historical intent | Finite numeric/boolean conditions; unsupported input refused |
| Buff debug eval | A, with B historical intent | Same finite racial formula evaluator, no execution |
| Buff normal eval | A, with B historical intent | Same finite evaluator; retain scalar/text metadata types |
| Creature AI eval | A | Explicit Gypsy Bandit implementation; known legacy-script fingerprint accepted only as an inert identifier |

**5 eval sites found; 5 removed; 0 active eval calls remain in shipped PHP.** Search also covered evaluated regular expressions, assertions, generated configuration, callbacks, includes, event conditions and buff/combat metadata. No `/e` evaluation or string assertion execution remains. Module hook dispatch permits only each installed module's conventional `_dohook`, not a database-selected arbitrary PHP function.

`src/Game/Expression.php` supports the exact bundled constants and the two actual Elf/Troll formulas, numeric database strings, boolean constants and the old debug prefix. Zero attack/defense is explicitly guarded. No general PHP grammar, arbitrary functions/variables, includes, object construction, filesystem, shell or database capability is exposed. Dark Horse uses an explicit mount-preference condition. `CreatureAi` preserves the sole seeded Gypsy Bandit roll, threshold, rounded amount, transfer and one-time use.

Tests use real bundled formulas and the actual installed seed AI, malformed/oversized input, unsupported function/variable/object/include syntax, code injection, zero division guards, altered AI text and actual retained effects. Unknown metadata fails closed. Remaining risks are separate: trusted module PHP is not sandboxed; serialized game state and full combat routes still need review. No claim is made that eval removal alone makes these safe.

## Shipped route security status

PASS below is explicitly limited to the named boundary. Every family whose broader gate is incomplete remains BLOCKED overall.

| Family | SQL | Authorization | Method / CSRF | Output | Overall |
|---|---|---|---|---|---|
| Mail | Bound deletion/unread with strict positive ID list; send/recipient/reply/systemmail remain | Actor login and owner-scoped deletion/unread tested, including cross-account attempt | GET and missing-CSRF rejection for deletion/unread; send remains | Mail-list subject stored HTML escaped while color codes retained; broader body/system message review remains | BLOCKED, partial repair |
| Petitions | Bound intake/rate-count SQL and bounded allowlisted fields; admin SQL remains | Authenticated identity comes from session; anonymous contact explicitly unverified; admin actions unverified | Intake POST/CSRF tested; viewer status/cleanup still mutate on GET | Problem/abuse form values escaped; session diagnostics and arbitrary POST fields no longer persisted; viewer output still needs review | BLOCKED, partial repair |
| Clans | Raw rank/remove/MOTD/customsay SQL remains | Membership target must be constrained to actor clan and permitted ranks; existing UI hiding is insufficient | GET rank/remove and unprotected text changes remain | Clan text/name/description contexts need review | BLOCKED |
| Bank/economy | Transfer recipient/raw-input paths and atomic balance updates remain | Eligibility/server balances and duplicate actions need tests | Transfers/deposit/withdrawal and purchases lack complete POST/CSRF | Recipient/economy text needs review | BLOCKED |
| Weapons/armor | Purchase/item and editor boundaries not certified | Server price, item eligibility, editor permission need tests | Purchase/sale/editor mutations not certified | Item descriptions/names not certified | BLOCKED |
| Mounts/stables | Ownership, preferences and serialized state not certified | Purchase/sale/editor checks need tests | Mutations not certified | Mount names/descriptions not certified | BLOCKED |
| Training/masters | Advancement paths not certified | Server eligibility and duplicate advancement tests missing | Advancement method/CSRF not certified | Master/name text not certified | BLOCKED |
| PvP | Target/claim paths not certified; Dag no-bounty read bound | Eligibility/target/repeated-action tests incomplete | PvP action boundary not certified | Target/combat output incomplete | BLOCKED |
| Administrator editors | Phase 2 protected services retained; wider user/configuration/content/module setting routes need review | Full admin/player/anonymous capability matrix not completed | Representative full editor POST/CSRF/GET/malformed-ID matrix not completed | Content/name/biography output contexts incomplete | BLOCKED |
| Commentary moderation | Existing bound transactional audit/delete/restore retained | Real player denial and moderator success retained | Existing POST/CSRF retained and HTTP tested | User commentary HTML injection rejection retained | PASS for Phase 2 scope |

Concrete continuation sources: `lib/mail/case_send.php`, reply/address cases and `lib/systemmail.php`; `viewpetition.php` status changes and GET cleanup; `lib/clan/clan_membership.php` cross-clan target/rank/remove and `clan_motd.php` raw text; `bank.php` transfers; `weapons.php`, `armor.php`, `stables.php`, `mounts.php`, `train.php`, `masters.php`, `pvp.php`; `user.php`, `configuration.php`, `creatures.php`, equipment editors, `titleedit.php`, `taunt.php` and module preference/settings editors. Audit all helper includes, not just entrypoint files.

No recovery work was added. HTTP 410 recovery remains explicit and tested. LoGDnet, raw SQL/PHP console, payments and source viewer remain disabled.

## Active-module maintenance

The two bundled `newday-runonce` handlers are Cedrik's Potions and Crazy Audrey. All 24 are active in the disposable scheduler database. The CLI advisory lock encloses maintenance. Each daily callback now transacts its DML together with a per-day receipt in the existing settings table. Receipt keys fit the real 20-character schema. Already completed callbacks are skipped on retry.

A synthetic temporary installed module runs after the bundled daily hooks and independently throws, returns malformed state or causes a database error. Each fails observably with exit 1, rolls back its own write, omits secrets and leaves global completion unset/empty. Earlier Crazy Audrey work runs once across the failed attempts. A successful retry commits once; a repeat reports already-complete. Near-concurrent invocation while the active callback runs is rejected by the advisory lock. Temporary fixture PHP/module rows and receipts are removed afterwards.

The dormant browser-triggered global maintenance fallback was removed from `newday.php`; changing `newdaycron` cannot bypass the CLI boundary. Player New Day remains separate.

**Result: PASS for active bundled daily-hook failure/retry/duplicate/concurrency scope.** This is not a universal exactly-once scheduler. Bundled daily callbacks use transactional database writes; arbitrary third-party filesystem/network effects or DDL would not roll back. Core cleanup is not globally atomic: `expire_chars.php` still advances its own expiration marker before completing cleanup, and optimization DDL can commit implicitly. Failure during that later core cleanup and account-deletion hooks needs additional review; do not infer crash-safe cleanup certification from the active daily-hook tests.

## Tests and CI

Validated implementation: `e5e9193aba19e7d98ffa5a2c558eda2b9c96ca52`.

| Gate | Exact result |
|---|---|
| Modern core [34777799831](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34777799831) | SUCCESS, both jobs |
| PHP 8.4.25 / MariaDB 11.4.13 | SUCCESS, job 103778999760 |
| PHP 8.5.10 / MySQL 8.4.11 | SUCCESS, job 103778999702 |
| PHPUnit per job | **47 tests, 1393 assertions, zero skips** (Phase 2: 39/299) |
| Python per job | **13 tests, all pass** (Phase 2: 12); existing HTTP test expanded substantially |
| PHP lint per job | **282 files, zero failures** (Phase 2: 277) |
| PHPStan level 0 | Zero new errors; unchanged 7 retained baseline findings |
| PHPStan level 6 | Zero errors, no baseline; new src and scoped procedural services |
| Composer | Strict validation, locked install and dependency audit PASS; no new dependencies |
| Baseline integrity [34777799828](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/actions/runs/34777799828) | SUCCESS |

This final checkpoint adds documentation only. Its exact-head CI must also be verified before ending the execution; PR #1 records the resulting workflow IDs and conclusions without creating a self-referential commit-hash/CI loop in this file.

All Phase 2 test methods remain. Database-backed tests execute in both supported CI jobs without skips. Local PHP 8.4 lint/static checks supplement CI; local skipped database runs are not used as acceptance evidence. The existing two-job workflow automatically discovers the added PHPUnit and Python coverage; no extra matrix was added.

| Intermediate Modern core run | Conclusion | Meaning / correction |
|---|---|---|
| 34776205111 | FAILURE | Combined fixture lacked module-block globals; all-24 lifecycle assertions passed before the fixture error |
| 34776421736 | SUCCESS | Lifecycle/dependency/race-input checkpoint |
| 34776640917 | SUCCESS | Expression removal and real metadata characterization |
| 34776789475 | FAILURE | Scheduler fixture/receipt keys exceeded strict settings column width; shortened without changing schema |
| 34776948659 | FAILURE | Test expected no maintenance marker row; core getsetting persists an empty default, which correctly does not represent completion |
| 34777154788 | SUCCESS | Active failure/retry/concurrency and mail boundaries, 47 tests/1358 assertions, 13 Python, 282 lint |
| 34777405824 | SUCCESS | Petition intake and sensitive-diagnostic rejection, same PHPUnit/lint floor |
| 34777526404 | FAILURE | New HTTP walk attempted unissued Village URL; changed fixture to follow current issued navigation |
| 34777720072 | FAILURE | New chooserace/specialty fixture lacked bootstrap navigation-block arrays; initialized actual runtime state |

Corresponding Baseline integrity runs succeeded: 34776205117, 34776421724, 34776640900, 34776789520, 34776948529, 34777154828, 34777405832, 34777526390 and 34777720073. Intermediate failures are retained in history, not hidden by baseline regeneration or removing tests.

## Historical integrity and scope boundaries

- Tag: `historical-source-1.1.2`.
- Annotated tag object: `51cab4fbe58a234651a3177a56289b18bc152b4d`.
- Historical source commit: `bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`.
- Historical root tree: `4013a0ccc5227e87cd7a22de00b7c322d7aa237c`.
- **417 historical files verify; all 11 original preservation commits reachable.** Baseline verifier passes locally and in CI.
- No historical repository was modified. No archive-only module was imported. No gameplay rebalance, theme redesign or framework replacement.
- `vps1.phoenix233.com` untouched. No deployment, public hostname/runtime, release tag, GitHub Release, ZIP or deployment package. Tests run only in repository/CI environments.

## Exact next recommended phase

Continue **Phase 3 closure on `modernization/core-modernization`, PR #1**. Do not start Phase 4, a replacement branch, a new PR, deployment or release.

1. Finish bundled direct routes and shared event/combat action boundaries: real issued navigation, typed input, active/dependency checks, server-authoritative state, POST/CSRF, replay rejection, safe serialized state and output. Prioritize Dag/Drinks and Dark Horse game state, then complete forest/potion/conversation paths. Retain the all-24 combined fixture.
2. Complete mail send/reply/systemmail, petition administration, clan membership/ranks/MOTD, then transactional economy/equipment/mount/training/PvP paths. Use the existing CSRF and input infrastructure. Avoid static-SQL churn or changes to game values.
3. Finish admin HTTP capability matrices and content/preferences output/SQL review; review later cleanup failure markers and account-deletion hooks.
4. Re-run both supported matrix jobs, Composer and unchanged baseline verification. Promote each module's overall certification only on actual evidence. Update this checkpoint and PR #1.
5. Mark ready and normal-merge PR #1 only if every modern-core acceptance gate passes, then verify main and its CI. No merge is justified by this checkpoint.

Public hosting remains **NO** independently: modern recovery/email verification, abuse controls, trusted production TLS/proxy/session configuration, internal/config/vendor/test-file protection, isolation, least-privilege credentials, backup/restore testing, monitoring and independent security review remain. No public-hosting claim follows from a future core merge.


## Security closure continuation (2026-09-13)

The original checkpoint above is preserved as historical evidence. The [security closure continuation](MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md) records subsequent commits and results: 60 PHPUnit tests / 1,551 assertions, 14 Python tests, 295 PHP files linted, both static-analysis policies and Composer gates passing at implementation head `5da77fc0aa2db4c845ef68813c4fb77d3372a834`. Modern core run `34781821375` and Baseline integrity run `34781821371` succeeded. The final documentation-head workflow evidence is in PR #1.

The updated module decisions are **3 PASS, 0 PASS WITH DOCUMENTED LIMITATION, 21 BLOCKED**, with all 24 lifecycle PASS. See [route security](CORE-ROUTE-SECURITY.md), [serialized state](SERIALIZED-STATE-AUDIT.md), and [module certification](BUNDLED-MODULE-CERTIFICATION.md). **Modern-core merge remains NOT READY; PR #1 remains OPEN and DRAFT; public hosting remains NO.** No Phase 4, new branch/PR, deployment or release is authorized by this checkpoint.
