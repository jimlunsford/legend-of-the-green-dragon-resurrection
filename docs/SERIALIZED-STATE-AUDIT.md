# Serialized-state audit

Date: 2026-09-14. Scope: tracked shipped PHP, excluding tests and tooling. Token-based inventory ignores comments. No archive modules were imported.

**Object-restoration boundary: constrained. Full business-schema closure: BLOCKED.**

The inventory contains **87 sites in 42 files: 31 `serialize()` writers/checks, 55 `ScalarState::read()` calls, and one actual `unserialize()` implementation**. `ScalarState::read()` disables classes, limits input to 1 MiB, depth to 32 and visited nodes to 10,000, accepts only null/boolean/integer/string/finite-float/array values, rejects incomplete objects and cycles, and requires canonical complete serialization. Errors return `false`; callers still need their own exact schema and invalid-state response. PHP session encoding itself is server-owned and is not a call site in this inventory.

`game_stones` no longer restores PHP serialization. `StonesState` accepts bounded JSON only (512 bytes, depth 4), checks keys/types/ranges, side/bet values, even piles and conservation of all 16 stones. Malicious serialized objects and malformed JSON receive rejection. An in-progress pre-conversion Stones blob is rejected instead of migrated; no production runtime was deployed. The second continuation closes the shared Dark Horse contract using an account-owned wager envelope with a unique game ID and committed stake; see the added business schemas below.

## Exact call-site inventory

Each remaining site is listed below. A writer never instantiates an object by itself. Every listed read is now routed through the same constrained implementation; this prevents object construction but does not prove that the contained values are safe business state.

| Site | Operation | Trust source | Expected schema | Remediation / residual risk | Test evidence |
|---|---|---|---|---|---|
| `battle.php:22` | `ScalarState::read` | Account badguy blob populated by combat/core routes | Creature/attack stack arrays with finite combat vocabulary | Object construction removed; complete creature schema and action authority not certified | ScalarStateTest + ExpressionTest; combat/training HTTP BLOCKED |
| `bio.php:123` | `ScalarState::read` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `common.php:256` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `common.php:346` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `companions.php:56` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `companions.php:94` | `serialize` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `companions.php:217` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `healer.php:13` | `ScalarState::read` | Persisted legacy donation/payment configuration or administrative log | Donation preference map / payment metadata array | Constrained read; payments stay disabled, no payment capability restored | ScalarStateTest; legacy schema and readers not fully certified |
| `lib/accounts.php:27` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/accounts.php:27` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/accounts.php:27` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/accounts.php:28` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/accounts.php:28` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/addnews.php:75` | `serialize` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `lib/arrayutil.php:32` | `serialize` | Trusted helper arguments or diagnostic display of persisted bytes | Scalar/array debug or conversion value | Constrained restore; writer alone does not instantiate objects | ScalarStateTest; helper is not an authorization boundary |
| `lib/buffs.php:96` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `lib/clan/applicant.php:26` | `serialize` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `lib/clan/applicant_apply.php:14` | `serialize` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `lib/clan/clan_membership.php:35` | `serialize` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `lib/clan/clan_withdraw.php:44` | `serialize` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `lib/commentary_security.php:28` | `serialize` | Persisted moderated comments containing user text and actor metadata | Comment metadata array / scalar text | Object construction removed; commentary service has existing ownership/CSRF handling | ScalarStateTest + existing commentary HTTP; general moderator editor matrix not certified |
| `lib/commentary_security.php:71` | `ScalarState::read` | Persisted moderated comments containing user text and actor metadata | Comment metadata array / scalar text | Object construction removed; commentary service has existing ownership/CSRF handling | ScalarStateTest + existing commentary HTTP; general moderator editor matrix not certified |
| `lib/datacache.php:35` | `ScalarState::read` | Application-generated local cache bytes | Arbitrary bounded scalar/array cache payload | Object construction removed; cache directory must remain application-controlled | ScalarStateTest; filesystem isolation is a separate hosting gate |
| `lib/datacache.php:56` | `serialize` | Application-generated local cache bytes | Arbitrary bounded scalar/array cache payload | Object construction removed; cache directory must remain application-controlled | ScalarStateTest; filesystem isolation is a separate hosting gate |
| `lib/dump_item.php:10` | `ScalarState::read` | Trusted helper arguments or diagnostic display of persisted bytes | Scalar/array debug or conversion value | Constrained restore; writer alone does not instantiate objects | ScalarStateTest; helper is not an authorization boundary |
| `lib/dump_item.php:26` | `ScalarState::read` | Trusted helper arguments or diagnostic display of persisted bytes | Scalar/array debug or conversion value | Constrained restore; writer alone does not instantiate objects | ScalarStateTest; helper is not an authorization boundary |
| `lib/forcednavigation.php:22` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/forcednavigation.php:24` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/forcednavigation.php:25` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/forcednavigation.php:27` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/forcednavigation.php:28` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/http.php:44` | `serialize` | Request-derived form values or server-rendered oldvalues hidden field | Form map or nested field array | Serialization alone does not instantiate objects; hidden values are untrusted on return | ScalarStateTest for downstream restores; preference/editor field allowlists remain BLOCKED |
| `lib/inn/inn_room.php:3` | `ScalarState::read` | Persisted legacy donation/payment configuration or administrative log | Donation preference map / payment metadata array | Constrained read; payments stay disabled, no payment capability restored | ScalarStateTest; legacy schema and readers not fully certified |
| `lib/mail.php:137` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:138` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:179` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:180` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:185` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:186` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:289` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:290` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:296` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail.php:297` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail/case_default.php:30` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail/case_read.php:16` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail/case_read.php:21` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail/case_write.php:37` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/mail/case_write.php:42` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/motd.php:48` | `ScalarState::read` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/motd.php:222` | `serialize` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/output.php:429` | `serialize` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/output.php:620` | `ScalarState::read` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/output.php:709` | `serialize` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/player_mutation.php:59` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/player_mutation.php:60` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/player_mutation.php:64` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/player_mutation.php:77` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/saveuser.php:16` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/saveuser.php:17` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/saveuser.php:18` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/saveuser.php:23` | `serialize` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `lib/systemmail.php:16` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/systemmail.php:20` | `serialize` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/systemmail.php:24` | `serialize` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/systemmail.php:67` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/systemmail.php:71` | `ScalarState::read` | Mail database content, internal translated messages, recipient preferences; player text reaches mailbox readers | Plain string or translation argument array; preference map | Constrained scalar restoration; send/reply identity, bounds and SQL remain open | ScalarStateTest + existing mailbox ownership/rendering HTTP; full mail family BLOCKED |
| `lib/translator.php:134` | `ScalarState::read` | Translation keys, language preferences or admin-authored MOTD persisted in database | Translation argument arrays / language preference map / MOTD poll array | Object construction removed; translation and MOTD field schemas remain incomplete | ScalarStateTest; output remains context-dependent, editor routes BLOCKED |
| `lib/user/user_edit.php:55` | `serialize` | Request-derived form values or server-rendered oldvalues hidden field | Form map or nested field array | Serialization alone does not instantiate objects; hidden values are untrusted on return | ScalarStateTest for downstream restores; preference/editor field allowlists remain BLOCKED |
| `login.php:54` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `login.php:57` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `login.php:58` | `ScalarState::read` | Database account state and server session; hydrated preferences, buffs, companions, navigation | Named account maps/lists; allowednavs URL-to-boolean map | Readers reject objects; writers remain PHP encoding for compatibility; exact schemas incomplete | ScalarStateTest, auth HTTP and PlayerMutationTest; account/editor/combat closure remains open |
| `mercenarycamp.php:216` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `moderate.php:137` | `ScalarState::read` | Persisted moderated comments containing user text and actor metadata | Comment metadata array / scalar text | Object construction removed; commentary service has existing ownership/CSRF handling | ScalarStateTest + existing commentary HTTP; general moderator editor matrix not certified |
| `mounts.php:92` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `mounts.php:273` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `newday.php:182` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `news.php:73` | `ScalarState::read` | Persisted news/mail translation arguments, including player names | Translation format plus scalar/nested translation arguments | Readers cannot instantiate objects; addnews INSERT now bound; clan writes remain open | ScalarStateTest; news/debug exercised by module routes; clan and translation schemas incomplete |
| `paylog.php:37` | `ScalarState::read` | Persisted legacy donation/payment configuration or administrative log | Donation preference map / payment metadata array | Constrained read; payments stay disabled, no payment capability restored | ScalarStateTest; legacy schema and readers not fully certified |
| `paylog.php:65` | `ScalarState::read` | Persisted legacy donation/payment configuration or administrative log | Donation preference map / payment metadata array | Constrained read; payments stay disabled, no payment capability restored | ScalarStateTest; legacy schema and readers not fully certified |
| `prefs.php:298` | `serialize` | Request-derived form values or server-rendered oldvalues hidden field | Form map or nested field array | Serialization alone does not instantiate objects; hidden values are untrusted on return | ScalarStateTest for downstream restores; preference/editor field allowlists remain BLOCKED |
| `src/Security/PvpState.php:11` | `ScalarState::read` | Actor account badguy, produced by locked PvP entry | Owned encounter, one target, bounded finite required fields, live opponent | Minimum PvP schema added; general combat and optional fields remain open | PvpStateTest; funded PvP HTTP/rollback matrix |
| `src/Security/ScalarState.php:17` | `unserialize` | Compatibility bytes from callers below | Bounded scalar/array tree; canonical PHP encoding | Classes disabled; recursively rejects objects; malformed input returns false | ScalarStateTest; global parser only, not business-schema validation |
| `src/Security/ScalarState.php:19` | `serialize` | Compatibility bytes from callers below | Bounded scalar/array tree; canonical PHP encoding | Classes disabled; recursively rejects objects; malformed input returns false | ScalarStateTest; global parser only, not business-schema validation |
| `stables.php:174` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `stables.php:176` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `stables.php:196` | `ScalarState::read` | Persisted mount, buff or companion definitions and account state | Buff maps / companion lists / scalar ability maps | Object construction removed; per-field schema and editor authority incomplete | ScalarStateTest + existing buff/race/specialty fixtures; mount/editor routes BLOCKED |
| `train.php:116` | `ScalarState::read` | Account badguy blob populated by combat/core routes | Creature/attack stack arrays with finite combat vocabulary | Object construction removed; complete creature schema and action authority not certified | ScalarStateTest + ExpressionTest; combat/training HTTP BLOCKED |

## Test and residual-risk interpretation

- `ScalarStateTest`: scalar/nested-array compatibility; malformed, trailing, oversized, deep, cyclic and malicious object payloads; wakeup probes do not execute.
- `StonesGameTest`: deterministic completed win, loss/tie, finite draw progression, invalid actions/bets, settlement replay and scope/context/CSRF intent validation.
- `test_create_login_rotate_render_logout`: real Stones choose/bet/draw/settle forms, duplicate submissions, malformed serialized state and normal return navigation.
- `PlayerMutationTest`: account and related DML rollback together, successful retry and rejection of stale account hydration. This is not a guarantee for DDL or external effects.
- Compatibility reads returning `false` do not confer exact field-level validation. Buff/companion, combat, mount, translated mail/MOTD, preferences and administrative schemas remain merge work. User/editor-controlled values still require typed inputs, bound SQL and context-specific output.
- `lib/http.php::postparse()` still serializes array fields while building escaped SQL. It must not be treated as the approved boundary for an unreviewed editor.
- Disabled payments remain disabled. This audit does not authorize payment restoration, external recovery, public serving of internals, or deployment.

Workflow results and the merge decision are recorded in [the continuation checkpoint](MODERN-CORE-CHECKPOINT-20260913-PHASE3-CLOSURE.md).

## Dark Horse JSON envelope continuation

The PHP serialization inventory remains 87 sites / 41 files, 31 writers/checks, 55 ScalarState readers and one centralized actual unserialize. No compatibility parser was loosened.

`DarkHorseState` adds a bounded 2 KiB/depth-6 JSON envelope to `specialmisc`, with exact keys, a random 128-bit game ID, and owner/game/stage/wager/active/result/settled consistency. Stones retains its bounded conservation schema inside the envelope. Dice data is exactly roll (1..6), tries (1..3), opponent (0..6). Five/Six data is exactly five integers (1..6). Unknown envelope keys, invalid types, foreign ownership, conflicting active games, serialized objects and malformed JSON fail closed. No external request can supply the envelope or result data. Mutation writers execute within the locked account transaction. Combat, mount, buff, companion, translated mail/MOTD and editor schemas remain unclosed.

Recounted with PHP token_get_all on tracked shipped PHP (excluding tests/tooling/comments): exactly 87 sites / 41 files, 31 serialize, 55 ScalarState::read, one actual unserialize, zero active eval. The new wager ID makes identical Five/Six rolls distinct action contexts. The full existing ScalarState parser limits remain unchanged. Both-target tests now include actual final-write rollback for each game and two-server shared jackpot execution.


## 2026-09-14 PvP schema continuation

Token recount: **88 sites / 42 files; 31 serialize; 56 ScalarState::read; one actual unserialize**. The added read is `src/Security/PvpState.php`. The existing centralized reader and all class/depth/node/canonical/cycle limits remain unchanged. The full inventory above includes this extra read and refreshed line locations; systemmail notification extraction moves its two existing reads without adding restores.

`PvpState` adds a minimum schema for the shipped single-target PvP route: options type, owner, random encounter ID, target and reservation; one opponent; bounded finite health/attack/defense/level/experience/gold/start fields; bounded name/weapon/location; target consistency; no dead/completed opponent. HTTP entry creates this state from the locked account. Continued actions read the actor's stored state, consume its intent and recheck the target reservation. Terminal results clear the state in the combat transaction.

This does **not** certify the general Forest/training combat schema, all optional combat fields, mount/companion/buff schemas, translated mail arrays, preferences, editor oldvalues or navigation schemas. PvP inn/bodyguard and other unexecuted result branches remain separately classified in the route record.

## Cedrik persistence continuation (2026-09-14)

Exact token recount: **87 sites / 42 files / 31 serialize / 55 ScalarState reads / one centralized unserialize**. The only removed call is the second buff restoration in newday.php. New Day now carries the already validated, restored buff list with an explicit cursor reset. No new deserializer or relaxed ScalarState limit was introduced.

TransmutationState defines exact names/text/schema, positive bounded rounds, finite attack/defense modifiers from 0.1 through 2, the 0/1 New Day carry flag and the small existing runtime flag vocabulary. Malformed, stale-zero/negative/oversized, nested or unexpected state fails closed before gameplay. The normal buff container and centralized scalar reader remain in use. Vitality extra-HP bookkeeping is a bounded integer. Other combat, buff, mount, companion, mail and editor business schemas remain open.


## Remaining effects continuation (2026-09-14)

Exact token recount remains **87 sites / 42 files / 31 serialize writers / 55 ScalarState reads / one centralized unserialize**. No serialization is removed or added by these module changes, and all ScalarState class/size/depth/node/cycle/incomplete-object/canonical-completeness limits remain intact.

Fairy accumulated extra HP now uses a bounded nonnegative integer (0..4294967295, missing/empty means zero) before awards and hprecalc; increment overflow fails closed. Declared Fairy carry/award settings are validated before effects. Audrey paidvisit/played and Outhouse used/stage are finite preference values; Seth visit count is a bounded nonnegative integer. These are narrow business-state validators, not certification of the entire preferences serialization envelope. Audrey configured buff names are escaped before entering the legacy trusted buff-name display. General combat, mounts, companions, remaining buffs, translated mail, core preferences, editor oldvalues and navigation business schemas remain BLOCKED.

## Specialty onboarding continuation

No serialization sites changed: **87 sites / 42 files / 31 writers/checks / 55 ScalarState reads / one centralized unserialize**. Onboarding preferences are scalar database values, validated by a business check without changing ScalarState. No combat or companion schema has been added. Both remain BLOCKED; [SPECIALTY-AUTHORITY.md](SPECIALTY-AUTHORITY.md) records the actual consumers and persistence boundaries to close next.


## Dark Arts companion business-state implementation, 2026-09-14 continuation

Starting SHA: `e70429e22d7c0c47570db998e609677c5dcfa2a2`. `SkeletonCompanionState` validates the named skeleton above unchanged ScalarState: exact required fields/text/abilities/ignorelimit, only optional boolean used/suspended, finite positive bounded statistics, current HP at most max HP, and attack/defense/max HP consistent with the historical creation formula. Creation level is recovered from max HP, preserving companions across later player training. No lifetime counter, death immunity, extra modifier, arbitrary ability or nested extension is accepted. Half-point combat statistics remain unchanged. Other named companion arrays are NOT thereby business-certified.

Common hydration rejects malformed encoded maps and skeleton state with a controlled 409 before route gameplay. It preserves the stored blob for explicit repair, with no silent deletion or normalization. The apply_companion producer/fallback also validates skeleton state. Runtime death/removal, combat victory retention, New Day retention and Dragon clearing retain their historical implementation; complete HTTP lifecycle/rollback/replay authority is still pending.

Added SkeletonCompanionStateTest and test_darkarts_companion_business_state_http. The HTTP fixture creates a real skeleton through the existing historical Forest action and checks exact cost 1 (5 to 4), formula statistics, used flag, subsequent login/read, injured/suspended states, malformed stored matrices and unchanged game state on rejected GET/POST. This does NOT certify the historical GET action, stale-form protection, general combat state, or transactional specialty use. CI acceptance pending at this implementation checkpoint. All three specialties remain BLOCKED; **21 PASS / 0 PASS WITH DOCUMENTED LIMITATION / 3 BLOCKED**. Phase 3 incomplete, merge NO, hosting NO.


## Forest specialty transaction implementation

The next implementation adds `forest.php?op=specialty`, shared by DA/MP/TS. GET renders forms, while legacy Forest `skill`/`l` URLs reject before a round. POST requires CSRF and the existing one-use intent bound to player/combat/buff/companion/specialty preference state. The transaction rechecks installed active bundled module, selected identity, strict stored skill/uses, supported level 1/2/3/5 and live Forest state; the request supplies only the level. Existing effect hooks receive the server-resolved identity. Forest round and victory/defeat processing share the existing player transaction. Defeat gains an optional non-flushing mode so the callback can commit before page_footer. Terminal state is cleared inside this specialty transaction. Failed consumed intents require a new form.

`SpecialtyCombatState` is a narrow Forest specialty validator, NOT the general combat schema. It checks exact root/options/enemy key vocabularies, creature identity/name/weapon, bounded HP/attack/defense/level/rewards/player-start HP, finite flags, supported target and reward-map structure. It currently rejects encounters containing dead enemies, rather than claiming multi-target progression certification. Other core combat routes still need their real consumer schemas. No new field/formula/reward value is introduced.

HTTP tests added for all twelve Forest specialty actions: exact use cost and persisted combat/account read, duplicate/replay, late account-write failure after uses and fresh retry; plus stored preference, selected/inactive specialty, level, injected effects, CSRF, combat/target/stale state, anonymous and GET matrices. Exact effect/companion lifecycle/New Day after use and Dragon/other eligible route closure remain incomplete. No specialty promotion. CI pending for this implementation; retained earlier evidence is not represented as acceptance of this new tree. Phase 3 INCOMPLETE, module counts 21/0/3, merge NO, hosting NO.

Inventory delta for this Forest implementation: one new ScalarState read in SpecialtyCombatState and one new buff serializer in specialty_combat. Current source totals: **89 sites / 44 files / 32 writers/checks / 56 ScalarState reads / one centralized unserialize**. Prior dated inventory tables remain historical evidence.


Accepted Forest component at 353ba60ee0fad71fdc46669009c8a349dc4f0ecc: both supported targets, 80 PHPUnit tests / 2,350 assertions / 35 Python-HTTP tests / 315 lint files, zero failures/skips. Modern core 34892412719 and Baseline integrity 34892412674 SUCCESS. The detailed appended Phase 3 checkpoint separates verified Forest authority/cost/effect-parameter/replay/rollback/New Day evidence from remaining module and general-core gates. Final additional tests cover direct battle include rejection, seeded companion death/removal and actual Dragon win/reset clearing; exact-head acceptance is recorded in PR #1. Module counts remain 21/0/3, with 24 lifecycle PASS; Phase 3 incomplete, merge NO, hosting NO.


## 2026-09-14: consumed-effect evidence, schema boundaries unchanged

No serialization writer, reader or validator changed. Inventory remains **89 sites / 44 files / 32 writers-checks / 56 ScalarState::read calls / one centralized unserialize**. ScalarState, SkeletonCompanionState and SpecialtyCombatState are unchanged.

Actual HTTP consumption covers specialty gameplay, regeneration/aura expiration, two-live-target Earth Fist and stale selected-target rejection. This is not a buff business schema. Malformed specialty modifier structures, broader combat families, dead-target progression and general recovery remain BLOCKED. The narrow validator still rejects dead enemies anywhere in the envelope and preserves malformed state for explicit repair. [Evidence and limits](SPECIALTY-COMBAT-ACCOUNTING.md).


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


## 2026-09-26: Dragon state and outcome schemas

Token-based recount of tracked shipped PHP (excluding tests, scripts and lab; comments ignored): **93 sites / 46 files / 34 serialize writers/checks / 58 ScalarState::read calls / one centralized unserialize implementation**. Relative to 89/44/32/56/1, Dragon adds two writer sites in `lib/dragon_combat.php` and two compatibility reads in `src/Game/DragonCombatState.php`.

`DragonCombatState::read` checks the actual flat legacy Dragon or one-enemy envelope, type/identity/flags and finite statistics; new persisted envelopes receive an opaque server-owned encounter identity. `DragonCombatState::victory` checks a three-field pending outcome (typed flawless boolean, exact kill count and encounter identity). Neither schema trusts a request outcome or restores objects. `SpecialtyCombatState` now permits consistent nonpositive-HP, dead, untargeted enemy history only with an eligible live target remaining. Contradictions and terminal encounters reject. `SkeletonCompanionState`, `SpecialtyBuffState` and `ScalarState::read()` retain their existing protections. Malformed state is rejected and preserved for explicit repair. Broader arbitrary enemy/buff/editor schemas are still uncertified. Exact test and acceptance limits are in DRAGON-AUTHORITY-20260926.md and PR #1.


## 2026-09-26: Recovery: stable Transmutation contexts, unchanged business schemas

At recovered SHA `4bd0569e43567f29908434826c9d5dc23ad47460`, canonical Transmutation hydration and meaningful-change context rejection pass with the retained Dragon-reset HTTP regression on both supported targets (Modern core 36271747607). New progression/combined-buff tests consume existing schemas and add no serialized writer, reader or validator. Inventory remains 93 sites / 46 files / 34 serialize writers-checks / 58 ScalarState reads / one centralized unserialize. Final acceptance of the added tests is separate and recorded in PR #1; malformed-state recovery and broader business schemas remain open.


## 2026-09-27: recovery verified from published history

See [the recovery record](PHASE3-RECOVERY-20260927.md). Exact SHA `72ab600fdc8c959ec3240bdc5abaa72f39bd178e` is ACCEPTED by Modern core 36273713605 and Baseline integrity 36273713736. Both supported targets passed 88 PHPUnit tests / 2,462 assertions, 52 Python/tooling and HTTP tests, 321 PHP lint files, both PHPStan and all Composer gates, with no runtime-matrix failures or skips. The baseline-only job separately skips the database-dependent HTTP class. The Transmutation and authversion regressions remain accepted; Dwarf documentation agrees with JSON. All six recovered commits are retained. New specialty test candidate acceptance is recorded separately; no parent result is substituted for it.


### Accepted specialty continuation and independent decision

Exact candidate `2bfe8fc3c7cee4a8a8ac0c3647571a318d243634` passes Modern core **36285456622** and Baseline integrity **36285456697**. Both supported targets: 88 PHPUnit tests / 2,462 assertions, **55** Python/tooling and HTTP tests, 321 lint files, zero runtime-matrix failures/skips, all Composer and both PHPStan gates. All three new regression matrices pass. **Thieving Skills is PASS; totals are 22 PASS / 0 limitation / 2 BLOCKED, with 24 lifecycle PASS.** Dark Arts retains companion combat/fallback transition gaps; Mystical Powers retains area-effect progression and aura/companion gaps. Full independent reasoning and exact boundaries are in [the recovery record](PHASE3-RECOVERY-20260927.md). Application source, workflows and serialized readers/writers are unchanged. The 24/24 gate remains open; general combat development has not begun. Phase 3 incomplete, merge NO, hosting NO, PR #1 draft. The final documentation commit requires its own terminal exact-head acceptance recorded in PR #1.

## 2026-09-27: Ordinary Forest consumer schemas

Token-based recount: **94 sites / 47 files / 34 serialize writers-checks /
59 ScalarState reads / one centralized unserialize**. The sole new read is
`ForestCombatState::read`; `ScalarState::read` is unchanged. The Forest encounter
producer moved to its caller-owned library without adding serialization authority.

`ForestCombatState` checks the ordinary ordered enemy envelope, live/defeated target
consistency, options, finite statistics, reward-ledger correspondence and new
server-generated encounter identity. `ForestBuffState` and `ForestCompanionState`
check battle-consumer types and delegate exact specialty, Transmutation and Skeleton
Warrior producer invariants to the existing validators. These do not certify every
configured producer or alternate combat family. Specialty state gains only optional
validated encounter metadata, not permission for unrelated ordinary shapes.

Malformed data rejects and remains stored for explicit repair. No normalization,
implicit combat clearing or object restoration. Canonical action contexts ignore
record-field ordering but retain meaningful enemy/buff/companion collection order.
Ordinary GET never calls the mutation engine. Local evidence PASS; exact-head CI
acceptance PENDING, recorded in PR #1. Broader general combat and serialized-state
closure remain BLOCKED. See [the exact tests and limits](ORDINARY-FOREST-COMBAT-20260927.md).

## Training/master state candidate, 2026-09-27

`src/Game/TrainingCombatState.php` adds a family-owned ScalarState business read:
one server-selected live master, nearest-level identity, historical Dragon-scaled
statistics/HP bounds, single live target, training level and encounter identity.
Forest rewards/options and terminal continuation are rejected. `train.php` no
longer reads a request-created victory encounter; `lib/training_combat.php`
validates the raw persisted buff root before normalized data can conceal it,
selects the stored master only from authoritative nearest-level rows, and writes
the validated server-created encounter. `ScalarState::read()` and the ordinary
Forest schema are unchanged. No raw unserialize or dynamic execution is added.

Malformed combat/buff/companion state is preserved for operator repair, without
advancement, automatic defeat or clearing. Context canonicalizes record fields
while preserving effect/companion order. Local HTTP matrices cover corrupt roots,
objects, serialization, identities, levels, HP/stats, targets, effects, repair,
stale state, settlement and rollback. See [training evidence](TRAINING-MASTER-AUTHORITY-20260927.md).
Training acceptance remains BLOCKED pending exact candidate CI; broader serialized
state closure is not claimed.


## PvP consumer schema candidate, 2026-09-27

`PvpState` now allowlists the single-opponent root/options/enemy vocabulary, binds actor/victim/reservation/generation and immutable snapshots, bounds live HP to the recorded maximum, rejects terminal/wrong-family/duplicate/current-target corruption, and checks room-tier metadata. PvP also consumes the existing strict buff/companion validators and validates the historical bodyguard buff. `ScalarState::read()` is unchanged. Invalid/old state is preserved and rejected, requiring explicit repair rather than automatic reset. See [exact scope and HTTP evidence](PVP-COMBAT-AUTHORITY-20260927.md). Supported-matrix acceptance remains pending; unrelated serialized producers are not promoted.

## Graveyard torment consumer candidate, 2026-09-27

`GraveyardCombatState` validates a single catalog-backed Graveyard target,
server encounter identity, historically scaled HP/attack/defense/level, favor
reward, starting soul state, live flags and allowed options. It rejects Forest
reward semantics and terminal continuation. Raw companion/buff roots and existing
business validators are checked before transition. Soulpoints/favor/gravefights
retain unsigned database bounds. Corruption is preserved for explicit repair,
never silently cleared or rewarded. `ScalarState::read()` is unchanged.
[Full boundary and deterministic proof](GRAVEYARD-COMBAT-AUTHORITY-20260927.md).
Acceptance awaits exact supported-matrix CI; broader serialized consumers remain
BLOCKED.

## Dragon Point history consumer candidate, 2026-10-03

`DragonPointState` validates raw persisted history before allocation or onboarding.
It requires a scalar-safe, zero-indexed identifier list bounded by persisted kills,
TEXT capacity and the unchanged ScalarState ceiling. Removed-module identifiers
remain displayable/countable without implicit buyability. Malformed roots,
entries and over-allocation preserve state for explicit administrator repair.
The existing `ff` vocabulary and downstream count are retained. See
[the narrow trust contract and proofs](DRAGON-POINT-AUTHORITY-20261003.md).
Exact supported CI remains the acceptance gate; broader state closure is not claimed.

## Normal daily-reset consumer candidate, 2026-10-03

The daily reset reads raw persisted Dragon Point history through `DragonPointState`,
buffs through existing specialty/Transmutation/Forest business validators, and
companions through existing skeleton/Forest schemas. Owned mount rows and raw
mount effects are refreshed and validated before use. Malformed state fails closed
without repair/normalization; GET retains stored buff bytes. Equivalent record
field order is canonicalized for the one-use context. The low-level `ScalarState`
reader remains unchanged. [Scope and evidence](NEWDAY-DAILY-RESET-AUTHORITY-20261003.md).
Broader serialized consumers and explicit early resurrection remain BLOCKED.

## Mail translation business schema candidate, 2026-10-04

`MailContent` validates the translated system-mail business envelope in addition
to unchanged ScalarState parser protections: nonempty list, string format, at
most 32 scalar arguments, no nested/associative/object/null payloads, bounded
format conversions/counts and encoded/expanded sizes, valid UTF-8 and actual
VARCHAR(255)/TEXT capacity. LoGD color-percent behavior is retained. Only exact
system sender `'0'` permits this interpretation in the active inbox/read UI;
player and GM display mail remain literal, including serialization-looking text.
Replies are owned plain-player sources and cannot originate from system mail.
Invalid stored envelopes remain literal, never `sprintf` argument arrays.

Recomputed token inventory over tracked shipped PHP (excluding tests, lab and
scripts): predecessor **114 sites / 55 files / 41 serialize writers-checks /
72 ScalarState reads / one centralized unserialize**; candidate **112 sites /
53 files / 42 writers-checks / 69 ScalarState reads / one centralized unserialize**.
Five direct active mail display/reply reads were replaced/removed; two reads and
one serialization check live in MailContent. This is a current recount, not a
rewrite of earlier dated totals. No parser limits or historical sources changed.

[Mail contract, caller inventory and tests](MAIL-AUTHORITY-20261004.md).
The helper/player scope awaits exact CI; caller-specific clan, bank, admin,
haunt and legacy trust work remains open. Accepted predecessor Ramius and broader
New Day are PASS at Modern 37210214161 / Baseline 37210214168 SUCCESS.

## Inventory recount and stables candidate, 2026-10-16

Recounted with the same token method (PHP tokenizer over tracked shipped PHP,
excluding `tests/`, `scripts/` and `lab/`; comments ignored; `serialize(`,
`ScalarState::read(` and `unserialize(` calls). The method reproduces the recorded
**112 / 53 / 42 / 69 / 1** exactly at `4478c06`. Two later accepted commits changed it
without a recorded recount:

- `9c2f458` (clan membership): the notice-subject `serialize()` calls in
  `lib/clan/applicant.php`, `applicant_apply.php`, `clan_membership.php` and
  `clan_withdraw.php` moved into one bound helper in `lib/clan_authority.php`:
  **109 / 50 / 39 / 69 / 1**.
- `cbc0cf4` (clan text): `resurrection_clan_text_context()` serializes its state
  array as hash input for the one-use intent (never stored or read back):
  **110 / 50 / 40 / 69 / 1**.

Accepted at `639b8c5`: **110 sites / 50 files / 40 serialize writers-checks / 69
ScalarState reads / one centralized unserialize**. This is a recount, not a rewrite
of earlier dated totals.

Stables candidate: `stables.php` read `mounts.mountbuff` three times through
ScalarState and used the result unchecked (a malformed buff was fatal after the
purchase was logged). It now reads it once in `resurrection_stables_buff()`
(`lib/stables_authority.php`), which requires an array and fails before any change.
Candidate inventory: **108 / 50 / 40 / 67 / 1**. ScalarState limits are unchanged;
the mount buff business schema beyond "is an array" stays on the roadmap. See
[stables authority](STABLES-AUTHORITY-20261016.md).

## Stables acceptance and haunting candidate, 2026-10-16

Stables `8257aed` is accepted (Modern core 38831947335, Baseline integrity
38831947372), so the accepted inventory is **108 / 50 / 40 / 67 / 1** at `8257aed`.
The haunting candidate adds no `serialize()`, `ScalarState::read()` or `unserialize()`
call: recounted with the same token method (which again reproduces 112 / 53 / 42 /
69 / 1 at `4478c06`), it stays **108 / 50 / 40 / 67 / 1**. See
[haunting authority](HAUNT-AUTHORITY-20261016.md).

## User editor candidate, 2026-10-16

Bio moderation and donation administration (`c166e2e`) and soul restoration (`6a9a12b`)
added no call: recounted with the same token method (which again reproduces 112 / 53 /
42 / 69 / 1 at `4478c06` and 108 / 50 / 40 / 67 / 1 at `8257aed`), `6a9a12b` is
**108 / 50 / 40 / 67 / 1**.

The user editor candidate removes one writer: `lib/user/user_edit.php` no longer
serializes the form into the `oldvalues` hidden field (the inventory row above,
`lib/user/user_edit.php:55`). The save compares with a server-side snapshot in the
editor's session instead and refuses a posted `oldvalues`. Its intent context is a
SHA-256 of a JSON encoding, not a PHP serialization. Candidate inventory: **107 sites /
49 files / 39 serialize writers-checks / 67 ScalarState reads / one centralized
unserialize**. ScalarState limits are unchanged. See
[user editor authority](USER-EDITOR-AUTHORITY-20261016.md).

## Content editors part 3 candidate, 2026-10-17

Content editors part 1 (`6820b80`) and part 2 (`82a48fb`) added no call: recounted with the
same token method (which again reproduces 112 / 53 / 42 / 69 / 1 at `4478c06` and 108 /
50 / 40 / 67 / 1 at `8257aed`), `82a48fb` is **107 / 49 / 39 / 67 / 1**.

The mount and companion editors (`mounts.php`, `companions.php`, new
`lib/mount_companion_editor.php`) change it:

- `mounts.php` give no longer reads `mounts.mountbuff` itself (ScalarState read removed);
  it uses the stables' checked reader `resurrection_stables_buff()`. The edit form's read
  stays.
- `companions.php` save no longer serializes every posted array (writer removed), and
  take no longer reads `companions.abilities` there (read removed). The edit form's read
  stays.
- `lib/mount_companion_editor.php` adds two writers, the mount buff
  (`resurrection_mount_buff()`, which 1.1.2 serialized through the shared `postparse()`
  in `lib/http.php`) and the companion abilities, both built from the declared fields
  only and checked against their consumers before they are stored, and one read, take's
  abilities, which must be an array and pass combat's companion check.

Candidate inventory: **107 sites / 50 files / 40 serialize writers-checks / 66
ScalarState reads / one centralized unserialize**. ScalarState limits are unchanged. See
[content editors part 3](CONTENT-EDITORS-PART3-AUTHORITY-20261017.md).

## Business-schema inventory, 2026-10-17

Recounted at `05485ec` with the same token method, which again reproduces 112 / 53 / 42 /
69 / 1 at `4478c06` and 108 / 50 / 40 / 67 / 1 at `8257aed`: **107 sites / 50 files / 40
serialize writers-checks / 66 ScalarState reads / one centralized unserialize**, unchanged
since content editors part 3 (the account deletion, expiration and completion reporting
tasks added no call).

Each site below has a category from the `ROADMAP.md` list (or "other") and its status after
the serialized business schemas part 1 candidate, against the definition of done in
[serialized schemas part 1](SERIALIZED-SCHEMAS-PART1-20261017.md). "This commit" marks a
closure in that candidate. Line numbers are those at `05485ec`. Statuses other than the
navigation, preference and editor old value closures come from reading the code at
`05485ec`; that document marks which findings were verified by a probe.

| Site | Kind | Category | Status after this commit | Evidence or gap |
|---|---|---|---|---|
| `battle.php:28` | read | general combat | OPEN | Each caller runs its route schema first, but the Forest-specialty caller's `SpecialtyCombatState` is looser than `ForestCombatState`; the post-round value is not re-read before commit on the Dragon and Forest-specialty routes |
| `bio.php:128` | read | translation arrays (news) | OPEN | No shape check before `sprintf_translate`; a non-array gives a TypeError page; no malformed test |
| `common.php:256` | read | buffs | OPEN | Second read after hydration; a non-array silently becomes `[]`; only transmute is checked |
| `common.php:342` | read | companions | OPEN | `SkeletonCompanionState::companions`, 409; exact only for the skeleton warrior, other companions only need to be arrays |
| `companions.php:159` | read | companions | OPEN | Editor display; abilities with wrong types are rendered unchecked; no malformed test |
| `healer.php:13` | read | other: donation config | n/a | Value never used (dead read, as in 1.1.2) |
| `lib/accounts.php:27` | ser | buffs | CLOSED | Constant `[]`, which every buff schema accepts |
| `lib/accounts.php:27` | ser | other: dragon points | CLOSED | Constant `[]`, accepted by `DragonPointState::read` |
| `lib/accounts.php:27` | ser | preferences | CLOSED | Constant `[]`, accepted by `PreferenceState` (`NavigationPreferenceStateTest`) |
| `lib/accounts.php:28` | ser | companions | CLOSED | Constant `[]` |
| `lib/accounts.php:28` | ser | navigation | CLOSED | Constant `[village.php => true]`, accepted by `NavigationState` |
| `lib/addnews.php:75` | ser | translation arrays (news) | OPEN | Writer serializes any arguments with no schema |
| `lib/arrayutil.php:32` | ser | other: shared encoder | n/a | `createstring()`; its callers write badguy and companions, covered under those categories |
| `lib/buffs.php:101` | read | companions | OPEN | Skeleton check only, then `apply_companion` writes without the full schema |
| `lib/clan_authority.php:73` | ser | translation arrays (mail) | CLOSED | Not stored: a bound `WHERE subject=?` comparison |
| `lib/clan_authority.php:157` | ser | other: clan context hash | CLOSED | Not stored: hash input |
| `lib/commentary_security.php:28` | ser | other: commentary snapshot | OPEN | Writer stores `SELECT *` plus author fields with no schema |
| `lib/commentary_security.php:71` | read | other: commentary snapshot | OPEN | `is_array` then 409; keys and types unchecked before the restore INSERT; no malformed test |
| `lib/datacache.php:35` | read | other: data cache | n/a | Not business state; an invalid file reads as a miss and is recomputed |
| `lib/datacache.php:56` | ser | other: data cache | n/a | Not business state |
| `lib/dragon_combat.php:69` | ser | general combat | CLOSED | Serializes what `DragonCombatState::read` returned; `DragonCombatStateTest`, `test_dragon_specialty_authority_matrix` |
| `lib/dragon_combat.php:87` | ser | general combat | CLOSED | `DragonCombatState::victory` exact shape; `test_dragon_transitions_rewards_and_rollback` |
| `lib/dump_item.php:12` | read | other: admin dump display | n/a | Escaped display of a stored value; a failed read shows the literal |
| `lib/dump_item.php:28` | read | other: admin dump display | n/a | `dump_item_ascode()` has no callers |
| `lib/forcednavigation.php:23` | read | buffs | OPEN | Transmute 400 and `SpecialtyBuffState::collection` 409, but only specialty and transmute entries are exact; other buffs only need to be arrays |
| `lib/forcednavigation.php:36` | read | other: dragon points | OPEN | A non-array becomes `[]` without `DragonPointState::read`; `saveuser()` can then overwrite a corrupt history (from code reading) |
| `lib/forcednavigation.php:37` | read | preferences | CLOSED | **This commit**: `PreferenceState::read`, 409 before any write; `NavigationPreferenceStateTest`, `test_navigation_and_preference_state_http` |
| `lib/forcednavigation.php:39` | read | navigation | CLOSED | **This commit**: `NavigationState::read` (with `restorePage`), 409 before any write; same tests |
| `lib/forcednavigation.php:40` | read | navigation | CLOSED | **This commit**: same read as :39 |
| `lib/graveyard_combat.php:13` | read | buffs | OPEN | `ForestBuffState::validate`, 409, nothing written; criterion 4 fails: it rejects buffs 1.1.2 and shipped modules write (the installer Pony mount buff, verified) |
| `lib/graveyard_combat.php:15` | read | companions | CLOSED | `ForestCompanionState`, 409; `test_graveyard_malformed_state_and_explicit_repair` |
| `lib/graveyard_combat.php:91` | ser | general combat | OPEN | Re-read after the round (`GraveyardCombatStateTest`); criterion 4: a 1.1.2 in-flight torment is rejected and no exception is recorded (from code reading) |
| `lib/http.php:44` | ser | other: form helper | n/a | `postparse()` has no callers |
| `lib/inn/inn_room.php:4` | read | other: donation config | n/a | Value never used (dead read, as in 1.1.2) |
| `lib/mail.php:137` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:138` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:179` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:180` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:185` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:186` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:289` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:290` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:296` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/mail.php:297` | read | translation arrays (mail) | OPEN | Inert: no tracked file includes `lib/mail.php`; the live readers use `MailContent::stored`. Switch or record as excluded |
| `lib/motd.php:48` | read | other: motd poll | OPEN | `body`/`opt` used unchecked |
| `lib/motd.php:222` | ser | other: motd poll | OPEN | Stores posted values unchecked; motd.php is still 1.1.2 SQL |
| `lib/mount_companion_editor.php:134` | ser | mounts | OPEN | Checks `is_array` plus `NewDayState::buffs`, not the combat consumers' check; refuses text `minioncount` |
| `lib/mount_companion_editor.php:158` | ser | companions | CLOSED | Built from typed fields only; `test_mount_and_companion_editor_authority_http` |
| `lib/mount_companion_editor.php:385` | read | companions | OPEN | `ForestCompanionState`, 500 and rollback, but no malformed stored-abilities test (criterion 5) |
| `lib/newday_daily.php:60` | read | mounts | CLOSED | `NewDayState::buffs`, 409; `test_normal_newday_canonical_context_races_and_specialties` |
| `lib/newday_daily.php:64` | read | mounts | CLOSED | Same bytes as :60; hash input |
| `lib/newday_daily.php:89` | read | buffs | CLOSED | `NewDayState::buffs`, 409; `test_normal_newday_stale_replay_transport_and_corruption`, `NewDayStateTest` |
| `lib/newday_daily.php:98` | read | buffs | CLOSED | Hash input; the values are validated at :89 and :90 |
| `lib/newday_daily_outcomes.php:76` | read | mounts | CLOSED | Checked at newday_daily.php:61 in the same transaction (the New Day result buff list is not re-checked: buffs writer gap) |
| `lib/output.php:432` | ser | translation arrays (labels) | CLOSED | Not stored: in-request nav key |
| `lib/output.php:623` | read | translation arrays (labels) | CLOSED | Not stored: decodes the in-request key made at :432 |
| `lib/output.php:712` | ser | translation arrays (labels) | CLOSED | Not stored: lookup key |
| `lib/player_mutation.php:66` | ser | buffs | OPEN | Serializes the session buff list with no schema |
| `lib/player_mutation.php:67` | ser | companions | OPEN | Serializes companions with no schema |
| `lib/player_mutation.php:71` | ser | other: generic save | OPEN | Serializes any array field; prefs are now encoded by `PreferenceState::encode` first (**this commit**), dragon points still fall through (closes with dragon points) |
| `lib/player_mutation.php:84` | ser | other: generic save | CLOSED | Not stored: in-memory `$baseaccount` bookkeeping |
| `lib/pvp_combat.php:26` | ser | general combat | CLOSED | Not stored: hash input |
| `lib/pvp_combat.php:39` | ser | general combat | CLOSED | Not stored: hash input; the badguy it uses is read through `PvpState` |
| `lib/pvp_combat.php:46` | read | buffs | OPEN | `ForestBuffState::validate`, 409; criterion 4 fails as at graveyard_combat.php:13 |
| `lib/ramius_outcomes.php:76` | read | mounts | CLOSED | Checked under lock; result checked by `NewDayState::buffs`; `test_ramius_hooks_canonical_state_and_stale_mount` |
| `lib/ramius_resurrection.php:48` | read | companions | CLOSED | `ForestCompanionState`; `test_ramius_stale_state_settings_and_malformed_authority` |
| `lib/saveuser.php:16` | ser | navigation | CLOSED | **This commit**: stores only `NavigationState::map()` output; `restorepage` and `prefs` are checked in the same place |
| `lib/saveuser.php:19` | ser | buffs | OPEN | Serializes the session buff list with no schema |
| `lib/saveuser.php:20` | ser | companions | OPEN | Serializes companions with no schema |
| `lib/saveuser.php:26` | ser | other: generic save | OPEN | As player_mutation.php:71 |
| `lib/specialty_combat.php:39` | ser | buffs | OPEN | Serializes the buff list after only the transmute step; the specialty route runs no full buff schema |
| `lib/stables_authority.php:34` | read | mounts | OPEN | `is_array` only; a wrongly shaped array is stored through `apply_buff` |
| `lib/systemmail.php:37` | read | preferences | CLOSED | **This commit**: `PreferenceState::read`; a malformed recipient rejects the mail, nothing inserted (`SystemMailTest::testRecipientWithMalformedPreferencesNeverInserts`) |
| `lib/systemmail.php:41` | ser | translation arrays (mail) | CLOSED | `MailContent::translated`; `SystemMailTest` |
| `lib/systemmail.php:42` | ser | translation arrays (mail) | CLOSED | `MailContent::translated`; `SystemMailTest` |
| `lib/systemmail.php:98` | read | translation arrays (mail) | CLOSED | Re-reads its own value through `translated()`; post-commit, best effort |
| `lib/systemmail.php:102` | read | translation arrays (mail) | CLOSED | As :98 |
| `lib/training_combat.php:37` | read | buffs | OPEN | `ForestBuffState::validate`, 409; criterion 4 fails as at graveyard_combat.php:13 |
| `lib/training_combat.php:52` | read | general combat | OPEN | Pre-read for the master id, then `TrainingCombatState::read`; criterion 4: the 1.1.2 `{type:train}` state is rejected and no exception is recorded (from code reading) |
| `lib/training_combat.php:139` | ser | general combat | OPEN | Re-read after the round (`TrainingCombatStateTest`); criterion 4 as :52 |
| `lib/translator.php:137` | read | preferences | CLOSED | **This commit**: `PreferenceState::read` (post-commit mail delivery; a failure skips the email) |
| `login.php:54` | read | other: dragon points | OPEN | Reads prefs and dragon points; prefs now go through `PreferenceState` before the login is recorded (**this commit**); dragon points still fall back to `[]` |
| `login.php:57` | read | buffs | OPEN | No check; the value is unused and never saved by login.php |
| `login.php:58` | read | navigation | CLOSED | **This commit**: `NavigationState::read` and `restorePage` before the login is recorded, 409; `test_navigation_and_preference_state_http` |
| `mercenarycamp.php:216` | read | companions | OPEN | No check; a bad value reaches `apply_companion` and is saved |
| `moderate.php:137` | read | other: commentary snapshot | OPEN | No check; a `false` value is indexed |
| `mounts.php:234` | read | mounts | OPEN | Editor display; a non-array becomes `[]`; non-string fields unchecked |
| `news.php:73` | read | translation arrays (news) | OPEN | As bio.php:128; `test_news_and_bio_format_stored_stray_percent_http` covers valid rows only |
| `paylog.php:37` | read | other: payment log | OPEN | GET loop writes `processdate` from an unchecked value |
| `paylog.php:65` | read | other: payment log | OPEN | Display fields unchecked |
| `prefs.php:310` | ser | editor old values | CLOSED | **This commit**: the hidden `oldvalues` field is no longer written; nothing reads a posted or stored `oldvalues` (posted copies are discarded unread) |
| `src/Game/DragonCombatState.php:13` | read | general combat | CLOSED | `DragonCombatStateTest` (legacy flat format, malformed), `test_dragon_specialty_authority_matrix` |
| `src/Game/DragonCombatState.php:52` | read | general combat | CLOSED | `victory()`; `test_dragon_transitions_rewards_and_rollback` |
| `src/Game/DragonPointState.php:26` | read | other: dragon points | CLOSED | `DragonPointStateTest`, `test_dragon_points_invalid_requests_stale_and_corruption` |
| `src/Game/DragonPointState.php:108` | ser | other: dragon points | CLOSED | Not stored: validation round trip |
| `src/Game/DragonPointState.php:125` | ser | other: dragon points | CLOSED | Not stored: validation round trip |
| `src/Game/ForestCombatState.php:13` | read | general combat | CLOSED | `ForestCombatStateTest`, `test_ordinary_forest_malformed_preserved_and_repair` (1.1.2 shape accepted) |
| `src/Game/GraveyardCombatState.php:24` | read | general combat | OPEN | As graveyard_combat.php:91 |
| `src/Game/SpecialtyCombatState.php:13` | read | general combat | OPEN | Looser than `ForestCombatState` for the same value (no reward-ledger, AI or flag checks) and stricter in two 1.1.2 cases (no `creatureid`, `spellpoints`) |
| `src/Game/TrainingCombatState.php:46` | read | general combat | OPEN | As training_combat.php:52 |
| `src/Security/MailContent.php:53` | ser | translation arrays (mail) | CLOSED | Not stored: size and canonical check inside `translated()` |
| `src/Security/MailContent.php:54` | read | translation arrays (mail) | CLOSED | As :53; `MailContentTest` |
| `src/Security/MailContent.php:72` | read | translation arrays (mail) | CLOSED | `stored()`; `MailContentTest`, `test_player_mail_notifications_commit_failure_preferences_and_display` |
| `src/Security/PvpState.php:11` | read | general combat | CLOSED | `PvpStateTest`, `test_pvp_malformed_state_preserved_and_repaired`; old encounters fail closed by design (`PVP-COMBAT-AUTHORITY-20260927.md`) |
| `src/Security/PvpState.php:69` | ser | general combat | CLOSED | Not stored: hash input |
| `src/Security/ScalarState.php:17` | unser | other: central reader | CLOSED | The primitive; `ScalarStateTest` |
| `src/Security/ScalarState.php:19` | ser | other: central reader | CLOSED | Canonical check; `ScalarStateTest` |

Summary: 49 CLOSED, 50 OPEN, 8 n/a. By category: navigation 5/5 closed, preferences 4/4,
editor old values 1/1, general combat 9 of 16, buffs 3 of 12, companions 4 of 11, mounts 4 of
7, news arguments 0 of 3, mail 8 of 18 (the 10 open sites are the inert `lib/mail.php`),
navigation labels 3/3, other 8 closed, 11 open, 8 n/a of 27.

After the candidate (working-tree recount, same method, new files included): **103 sites / 50
files / 40 serialize writers-checks / 62 ScalarState reads / one centralized unserialize**.
`lib/forcednavigation.php` drops from five reads to two (bufflist, dragonpoints), `login.php`
from three to two (dragonpoints, bufflist), `lib/systemmail.php:37` and `lib/translator.php:137`
are gone, and `prefs.php` no longer serializes `oldvalues`; `src/Security/NavigationState.php`
adds one read and `src/Security/PreferenceState.php` one read and one serialize. ScalarState
limits are unchanged.
