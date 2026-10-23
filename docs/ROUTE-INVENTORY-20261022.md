# Route inventory, 2026-10-22

The backbone of the final rescan: every request entry point the shipped code serves, who can reach it, whether it
changes state, and its status. Built at `c00f2d1` and updated by the candidate that adds this file (final rescan task 1);
final rescan task 2 updated the admin rows and `bio.php` (`RESCAN-TASK2-20261022.md`).

Several route families (the mercenary camp, the MOTD, the news delete link) turned up only by accident late in Phase 3.
A sweep for unbound SQL alone would not have found all of them, so this list starts from the routes, not from the SQL.

## How the list is built and kept

- Every top-level `*.php` file (67), every `op` and `subop` a page compares, chooses, switches on or links to (in any
  shipped file), the `lib/about` and `lib/petition` files a page includes by op, and every `op` and `subop` of the 24
  bundled modules (their run function, their events, and every `runmodule.php?module=` link).
- `tests/tooling/test_route_inventory.py` scans the source the same way and fails unless the tables below list exactly
  those pages, ops, subops and modules: a new page, op or module cannot appear without an entry, and a removed one cannot
  stay listed. It also checks every row's status and the counts.
- Other request parameters (for example `act`, `what`, `type`, `id`) are covered by the row of the op that reads them.

## Statuses

- **CLOSED**: the state change is authenticated and authorized by the server, a POST with CSRF and, where it can be
  repeated, a one-use intent bound to the state it was shown for; checked under lock in one transaction; SQL bound;
  output escaped. The evidence names the authority document and the HTTP test.
- **DISABLED**: refused before the game starts (410 for disabled features; 404 or 403 where noted). Never re-enabled.
- **READ-ONLY**: changes no game state; its queries that take request input are bound and its output escaped.
- **OPEN**: something is missing, as stated. Every OPEN entry is a line on the `ROADMAP.md` Final rescan list.

"Player" means a signed-in, living or dead character as the page allows; "admin" names the superuser flag the page checks
with `check_su_access()`. Short names in the evidence column: `CRS` is `CORE-ROUTE-SECURITY.md`, `BMC` is
`BUNDLED-MODULE-CERTIFICATION.md`, tests are in `tests/tooling/test_web_application.py` unless named otherwise.

## Counts

| Status | Entries |
|---|---|
| CLOSED | 88 |
| DISABLED | 9 |
| READ-ONLY | 69 |
| OPEN | 0 |
| Total | 166 |

## Top-level pages

| Entry | Who | Changes state | Status | Evidence or gap |
|---|---|---|---|---|
| `about.php` | anyone | no | READ-ONLY | No query takes request input. |
| `about.php?op=license\|listmodules\|setup` | anyone | no | READ-ONLY | One file per op (`lib/about/about_<op>.php`); the module list query takes no request input. |
| `armor.php` | player | no | READ-ONLY | Listing bound (`EQUIPMENT-PURCHASE-AUTHORITY-20261015.md`). |
| `armor.php?op=buy` | player | gold, armor | CLOSED | `EQUIPMENT-PURCHASE-AUTHORITY-20261015.md`; `test_weapon_and_armor_purchase_authority_http`. |
| `armoreditor.php` | admin SU_EDIT_EQUIPMENT | no | READ-ONLY | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`. |
| `armoreditor.php?op=add\|edit\|save\|del` | admin SU_EDIT_EQUIPMENT | armor rows | CLOSED | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`; `test_weapon_and_armor_editor_authority_http`. |
| `badnav.php` | player | navigation only | READ-ONLY | Rebuilds the allowed navigation from the stored page; the query takes no request input. |
| `badword.php` | admin SU_EDIT_COMMENTS | no | READ-ONLY | The word lists; no request input in SQL. |
| `badword.php?op=test` | admin SU_EDIT_COMMENTS | no | READ-ONLY | Shows the censor's result for a posted word; writes nothing; a POST with CSRF since rescan task 2 (`RESCAN-TASK2-20261022.md`). |
| `badword.php?op=add\|remove\|addgood\|removegood` | admin SU_EDIT_COMMENTS | nastywords | CLOSED | `RESCAN-TASK2-20261022.md`: POST with CSRF and a one-use intent bound to the list as shown; locked, checked and rewritten in one transaction, bound; 1.1.2's stored word format; `test_badword_editor_authority_http`. |
| `bank.php` | player | no | READ-ONLY | `BANK-AUTHORITY-20261015.md`. |
| `bank.php?op=deposit\|depositfinish\|withdraw\|withdrawfinish\|borrow\|transfer\|transfer2\|transfer3` | player | gold, bank, transfers | CLOSED | `BANK-AUTHORITY-20261015.md`; `test_bank_deposit_withdraw_and_borrow_authority_http`, `test_bank_transfer_authority_http`. |
| `battle.php` | none | no | CLOSED | A direct request is refused (404); combat is a caller-owned include (`CRS`, "Direct battle.php include bypass blocked"); `test_forest_specialty_post_authority_and_rollback`. |
| `battle.php?op=fight\|run\|newtarget` | none (through Forest, PvP, training, graveyard, Dragon) | combat | CLOSED | Each caller's own route: `ORDINARY-FOREST-COMBAT-20260927.md`, `PVP-COMBAT-AUTHORITY-20260927.md`, `TRAINING-MASTER-AUTHORITY-20260927.md`, `GRAVEYARD-COMBAT-AUTHORITY-20260927.md`, `DRAGON-AUTHORITY-20260926.md`. |
| `bio.php` | anyone | no | READ-ONLY | Queries bound (`CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`); `ret` returns only to a known on-site page, else the warrior list (`RESCAN-TASK2-20261022.md`); `RescanTask2Test` (PHPUnit), `test_bio_return_link_stays_on_known_pages_http`. |
| `bios.php` | admin SU_EDIT_COMMENTS | no | READ-ONLY | `BIO-DONATION-ADMIN-AUTHORITY-20261016.md`. |
| `bios.php?op=block\|unblock` | admin SU_EDIT_COMMENTS | bio | CLOSED | `BIO-DONATION-ADMIN-AUTHORITY-20261016.md`; `test_bio_moderation_authority_http`. |
| `clan.php` | player | clan housekeeping | CLOSED | `CLAN-MEMBERSHIP-AUTHORITY-20261015.md`, `CLAN-TEXT-MODERATION-AUTHORITY-20261015.md`; `test_clan_housekeeping_on_view_http`, `test_clan_detail_rename_and_block_authority_http`. |
| `clan.php?op=list\|waiting\|apply\|new\|withdraw\|withdrawconfirm\|membership\|motd` | player | clan membership and text | CLOSED | Same documents; `test_clan_creation_application_and_withdrawal_http`, `test_clan_rank_and_removal_authority_http`, `test_clan_motd_description_and_say_authority_http`. |
| `common.php` | none | no | READ-ONLY | The bootstrap include; a direct request only meets its sign-in and navigation checks. |
| `companions.php` | admin SU_EDIT_MOUNTS | no | READ-ONLY | `CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`. |
| `companions.php?op=add\|edit\|save\|del\|take\|activate\|deactivate&subop=module` | admin SU_EDIT_MOUNTS | companion rows, players' companions, module preferences | CLOSED | `CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`; `test_mount_and_companion_editor_authority_http`. |
| `configuration.php` | admin SU_EDIT_CONFIG | no | READ-ONLY | Typed editor (`CRS`, configuration row). |
| `configuration.php?op=save\|modulesettings` | admin SU_EDIT_CONFIG | settings | CLOSED | `CRS`; `test_shared_typed_settings_http`. |
| `create.php` | anonymous | no | READ-ONLY | The account form. |
| `create.php?op=create` | anonymous | account | CLOSED | POST + CSRF, bound (`MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`); `test_create_login_rotate_render_logout`. |
| `create.php?op=forgot\|val` | anonymous | no | DISABLED | 410: legacy recovery and email validation stay disabled. |
| `creatures.php` | admin SU_EDIT_CREATURES | no | READ-ONLY | `CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`. |
| `creatures.php?op=search\|add\|edit\|save\|del&subop=module` | admin SU_EDIT_CREATURES | creature rows, module preferences | CLOSED | `CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`; `test_creature_and_master_editor_authority_http`. |
| `cron.php` | CLI only | maintenance | DISABLED | A web request gets 404 (`COMPLETION-REPORTING-20261017.md`); `test_disabled_endpoints.py::test_web_cron_rejected_on_repeated_requests`. |
| `donators.php` | admin SU_EDIT_DONATIONS | no | READ-ONLY | `BIO-DONATION-ADMIN-AUTHORITY-20261016.md`. |
| `donators.php?op=add1\|add2` | admin SU_EDIT_DONATIONS | donation points | CLOSED | `BIO-DONATION-ADMIN-AUTHORITY-20261016.md`; `test_donation_admin_authority_http`. |
| `dragon.php` | player | combat, rewards | CLOSED | `DRAGON-AUTHORITY-20260926.md`; `test_dragon_transitions_rewards_and_rollback`. |
| `dragon.php?op=prologue1` | player | Dragon kill outcome | CLOSED | `DRAGON-AUTHORITY-20260926.md`; `test_dragon_transitions_rewards_and_rollback`. |
| `forest.php` | player | events | CLOSED | `ORDINARY-FOREST-COMBAT-20260927.md`; `test_ordinary_forest_search_escape_and_event_handoff`. |
| `forest.php?op=search\|fight\|run\|newtarget\|dragon\|specialty` | player | combat | CLOSED | `ORDINARY-FOREST-COMBAT-20260927.md`, `SERIALIZED-SCHEMAS-PART4-20261022.md`; `test_ordinary_forest_http_authority_and_context`, `test_forest_specialty_post_authority_and_rollback`. |
| `gamelog.php` | admin SU_EDIT_CONFIG | no | READ-ONLY | `RESCAN-TASK2-20261022.md`: `cat` and `start` checked and bound; the category links encoded and escaped; `test_gamelog_and_referers_authority_http`. |
| `gardens.php` | player | commentary | CLOSED | Commentary through the shared helper (`MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`); `SecurityIntegrationTest::testCommentaryAuthorizationAndRawStorage`. No bundled gardens event. |
| `graveyard.php` | player (dead) | no | READ-ONLY | `GRAVEYARD-COMBAT-AUTHORITY-20260927.md`. |
| `graveyard.php?op=search\|fight\|run` | player (dead) | torment combat | CLOSED | `GRAVEYARD-COMBAT-AUTHORITY-20260927.md`; `test_graveyard_search_round_authority_and_replay`, `test_graveyard_victory_defeat_and_late_rollback`. |
| `graveyard.php?op=enter\|question` | player (dead) | no | READ-ONLY | The mausoleum and Ramius's question; no query takes request input. |
| `graveyard.php?op=haunt\|haunt2\|haunt3` | player (dead) | favor, haunt | CLOSED | `HAUNT-AUTHORITY-20261016.md`; `test_graveyard_haunt_authority_http`. |
| `graveyard.php?op=restore` | player (dead) | favor, soul | CLOSED | `RESTORE-PREFS-AUTHORITY-20261016.md`; `test_graveyard_restore_authority_http`. |
| `graveyard.php?op=resurrection` | player (dead) | resurrection | CLOSED | `RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md`; `test_ramius_strict_transport_replay_and_multiple_forms`. |
| `gypsy.php` | player | no | READ-ONLY | The price; closes a paid seance (this task). |
| `gypsy.php?op=pay` | player | gold | CLOSED | This task: GET asks; POST + CSRF + intent bound to the price and purse; server price; locked transaction (`CRS`, 2026-10-22 paid services); `test_gypsy_seance_authority_http`. |
| `gypsy.php?op=talk` | player who paid, or SU_EDIT_COMMENTS | commentary | CLOSED | This task: open only after a paid seance in this session, until the player snaps out of it, or to comment moderators; `test_gypsy_seance_authority_http`. |
| `healer.php` | player | hit points clamp | CLOSED | This task. As in 1.1.2, hit points above the maximum are set back to it for free (no request input). The return target is `''` or `village.php`; `test_healer_potion_and_companion_authority_http`. |
| `healer.php?op=buy\|companion` | player | gold, hit points, companion | CLOSED | This task: GET asks; POST + CSRF + intent; potion size from a fixed list; server price (the URL's `compcost` is ignored); locked transaction; `test_healer_potion_and_companion_authority_http`. |
| `hof.php` | player | no | READ-ONLY | This task: `op`, `subop` and `page` checked; the page LIMIT bound; `test_list_hall_of_fame_and_news_bound_requests_http`. |
| `hof.php?op=kills\|money\|gems\|charm\|tough\|resurrects\|days&subop=most\|least` | player | no | READ-ONLY | Same; the order and comparison come from the fixed `subop`. |
| `home.php` | anonymous | skin cookie | READ-ONLY | The skin choice is POST + CSRF and sets only the visitor's own cookie; the online count is cached from server values. |
| `home.php?op=timeout` | anonymous | no | READ-ONLY | The timeout notice. |
| `index.php` | anyone | no | READ-ONLY | Redirects to `home.php` with the same query. |
| `index.php?op=timeout` | anyone | no | READ-ONLY | Same. |
| `inn.php` | player | commentary, events | CLOSED | `INN-ECONOMY-AUTHORITY-20261015.md`; commentary through the shared helper; `test_inn_room_and_dragon_flee_authority_http`. |
| `inn.php?op=bartender` | player | gold, gems, specialty | CLOSED | `INN-ECONOMY-AUTHORITY-20261015.md`; `test_inn_bartender_bribe_and_specialty_authority_http`. |
| `inn.php?op=room\|fleedragon` | player | gold, logout, Dragon flee | CLOSED | `INN-ECONOMY-AUTHORITY-20261015.md`; `test_inn_room_and_dragon_flee_authority_http`. |
| `inn.php?op=converse\|strolldown` | player | commentary | CLOSED | Commentary through the shared helper; Seth's song hooks here (`runmodule.php?module=sethsong` row). |
| `installer.php` | none | no | DISABLED | 403: installation is a CLI action (`MODERN-INSTALLATION.md`); the web installer test checks it. |
| `installer.php?op=trycreate\|confirm_overwrite` | none | no | DISABLED | Same 403; the ops are the historical installer stages' links. |
| `list.php` | anyone | no | READ-ONLY | This task: `op` and `page` checked, paging bound; `test_list_hall_of_fame_and_news_bound_requests_http`. |
| `list.php?op=search\|clan` | anyone / player | no | READ-ONLY | This task: the search is bound (1.1.2 pasted it with `addslashes()`), split by character so the pattern stays UTF-8, at most 255 bytes; the clan list bound. |
| `lodge.php` | player | commentary | CLOSED | Core spends no points: the `lodge` hook has no bundled consumer. Commentary through the shared helper, for members only. |
| `lodge.php?op=points` | player | no | READ-ONLY | The points text; no query. |
| `logdnet.php` | anyone | no | DISABLED | 410 (`test_disabled_endpoints.py::test_disabled_features_reject_get_and_post_before_bootstrap`). |
| `logdnet.php?op=list` | anyone | no | DISABLED | Same 410. |
| `login.php` | anonymous | session | CLOSED | POST + CSRF, rotation (`MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`); `test_create_login_rotate_render_logout`. |
| `login.php?op=logout` | player | session | CLOSED | Same. |
| `mail.php` | player | no | READ-ONLY | The inbox (`MAIL-AUTHORITY-20261004.md`). |
| `mail.php?op=address\|write\|send\|read\|del\|unread\|process` | player | mail | CLOSED | `MAIL-AUTHORITY-20261004.md`; `test_player_mail_send_transport_search_and_bounds`, `test_player_mail_reply_ownership_staleness_and_rendering`. This task binds the read page's previous/next lookups. |
| `masters.php` | admin SU_EDIT_CREATURES | no | READ-ONLY | `CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`. |
| `masters.php?op=edit\|save\|del` | admin SU_EDIT_CREATURES | master rows | CLOSED | `CONTENT-EDITORS-PART2-AUTHORITY-20261016.md`; `test_creature_and_master_editor_authority_http`. |
| `mercenarycamp.php` | player | no | READ-ONLY | This task: the listing query is bound (1.1.2 pasted the location and dragon kills); `test_mercenary_camp_hire_and_heal_authority_http`. |
| `mercenarycamp.php?op=buy\|heal` | player | gold, gems, companions | CLOSED | This task: GET asks; POST + CSRF + intent bound to the offer, the price and the player; the offer and price re-checked under lock; healing priced by the server (the URL's `cost` is never read); `test_mercenary_camp_hire_and_heal_authority_http`. |
| `moderate.php` | admin SU_EDIT_COMMENTS | comment removal | CLOSED | Removal through the shared helper (POST + CSRF); `SecurityIntegrationTest::testCommentaryAuthorizationAndRawStorage`. |
| `moderate.php?op=commentdelete\|audit&subop=undelete` | admin SU_EDIT_COMMENTS / SU_AUDIT_MODERATION | comments, bans | CLOSED | `SERIALIZED-SCHEMAS-PART5-20261022.md` (E); `test_comment_ban_respects_longer_existing_ban_http`. |
| `modules.php` | admin SU_MANAGE_MODULES | no | READ-ONLY | `MODULE-MANAGER-AUTHORITY-20261017.md`. |
| `modules.php?op=mass` | admin SU_MANAGE_MODULES | module lifecycle | CLOSED | `MODULE-MANAGER-AUTHORITY-20261017.md`; `test_module_manager_authority_http`. |
| `motd.php` | anyone | no | READ-ONLY | `SERIALIZED-SCHEMAS-PART5-20261022.md` (C). |
| `motd.php?op=vote\|add\|addpoll\|del` | player / admin SU_POST_MOTD | votes, MOTD | CLOSED | `SERIALIZED-SCHEMAS-PART5-20261022.md` (C); `test_motd_route_authority_http`. |
| `mounts.php` | admin SU_EDIT_MOUNTS | no | READ-ONLY | `CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`. |
| `mounts.php?op=add\|edit\|save\|del\|give\|activate\|deactivate\|xml&subop=module` | admin SU_EDIT_MOUNTS | mount rows, owners, module preferences | CLOSED | `CONTENT-EDITORS-PART3-AUTHORITY-20261017.md`; `test_mount_and_companion_editor_authority_http`, `test_mount_owner_lookup_and_bio_page_http`, `test_shared_mount_preferences_http`. |
| `newday.php` | player | New Day, race, specialty, Dragon Points, resurrection | CLOSED | `NEWDAY-DAILY-RESET-AUTHORITY-20261003.md`, `DRAGON-POINT-AUTHORITY-20261003.md`, `RAMIUS-EARLY-RESURRECTION-AUTHORITY-20261004.md`; `test_normal_newday_stale_replay_transport_and_corruption`, `test_race_onboarding_http_authority`, `test_specialty_onboarding_http_authority`, `test_dragon_points_single_bulk_replay_and_handoffs`. |
| `news.php` | anyone | expired news | READ-ONLY | This task binds the day and paging and treats a page below 1 as page 1; the 1.1.2 expiry of old news on view uses server values only; `test_list_hall_of_fame_and_news_bound_requests_http`. |
| `paylog.php` | admin SU_EDIT_PAYLOG | no | READ-ONLY | `SERIALIZED-SCHEMAS-PART5-20261022.md` (D). |
| `paylog.php?op=process` | admin SU_EDIT_PAYLOG | processdate | CLOSED | `SERIALIZED-SCHEMAS-PART5-20261022.md` (D); `test_paylog_process_dates_post_only_http`. |
| `payment.php` | anyone | no | DISABLED | 410 (`test_disabled_endpoints.py`). |
| `petition.php` | anyone | petition | CLOSED | Intake (`CRS`, petition intake; `COMPLETION-REPORTING-20261017.md`); `test_petition_notice_email_and_server_url_http`. |
| `petition.php?op=submit` | anyone | petition | CLOSED | The intake form's target, handled by the default file; same evidence. |
| `petition.php?op=faq\|faq1\|faq2\|faq3\|primer` | anyone | no | READ-ONLY | Help text, one file per op. |
| `prefs.php` | player | no | READ-ONLY | `RESTORE-PREFS-AUTHORITY-20261016.md`. |
| `prefs.php?op=save` | player | preferences, password, email | CLOSED | `CRS` (player preference namespace), `RESTORE-PREFS-AUTHORITY-20261016.md`; `test_navigation_and_preference_state_http`. |
| `prefs.php?op=suicide` | player | account deletion | CLOSED | `DELETION-EXPIRATION-AUTHORITY-20261017.md`; `test_self_deletion_authority_http`. |
| `pvp.php` | player | no | READ-ONLY | The target list (`PVP-COMBAT-AUTHORITY-20260927.md`). |
| `pvp.php?op=fight\|run` | player | PvP combat | CLOSED | `PVP-COMBAT-AUTHORITY-20260927.md`; `test_pvp_eligibility_and_entry_rollback`, `test_pvp_round_stale_reservation_and_victim_authority`. |
| `rawsql.php` | anyone | no | DISABLED | 410 (`test_disabled_endpoints.py`). |
| `referers.php` | admin SU_EDIT_CONFIG | expired referers | READ-ONLY | `RESCAN-TASK2-20261022.md`: the 1.1.2 expiry on view kept, from server values only, bound; `sort` from an allow-list (400 otherwise); output escaped, only http(s) links; `test_gamelog_and_referers_authority_http`. |
| `referers.php?op=rebuild` | admin SU_EDIT_CONFIG | referers | CLOSED | `RESCAN-TASK2-20261022.md`: GET asks; POST with CSRF and a one-use intent; every row locked and rewritten in one transaction, bound; `test_gamelog_and_referers_authority_http`. |
| `referral.php` | anyone | no | READ-ONLY | The referral link and list; the query takes no request input; `serverurl` never saved (`COMPLETION-REPORTING-20261017.md`). |
| `rock.php` | player (veteran) | commentary | CLOSED | Commentary through the shared helper. |
| `runmodule.php` | player | dispatch | CLOSED | Active install and dependency checks (`CRS`, runmodule row); `test_dispatcher_enforces_module_state`. Module routes are below. |
| `shades.php` | player (dead) | commentary | CLOSED | Commentary through the shared helper. |
| `source.php` | anyone | no | DISABLED | 410 (`test_disabled_endpoints.py`). |
| `stables.php` | player | no | READ-ONLY | Listing bound (`STABLES-AUTHORITY-20261016.md`). |
| `stables.php?op=examine\|buymount\|confirmbuy\|feed\|sellmount\|confirmsell` | player | gold, gems, mount | CLOSED | `STABLES-AUTHORITY-20261016.md`; `test_stables_buy_feed_and_sell_authority_http`. |
| `stats.php` | admin SU_EDIT_CONFIG | no | READ-ONLY | No query takes request input. |
| `stats.php?op=stats\|referers\|graph` | admin SU_EDIT_CONFIG | no | READ-ONLY | Same. |
| `superuser.php` | admin (any grotto flag) | no | READ-ONLY | The grotto menu. |
| `superuser.php?op=keepalive` | admin (any grotto flag) | own `laston` | READ-ONLY | `RESCAN-TASK2-20261022.md`: stays a GET (it writes only the admin's own `laston`, as every page view does); bound; the raw request URI no longer echoed; `test_news_delete_and_keepalive_authority_http`. |
| `superuser.php?op=newsdelete` | admin SU_EDIT_COMMENTS | news | CLOSED | `RESCAN-TASK2-20261022.md`: comment moderators only; POST with CSRF and a one-use intent bound to the news line; locked and rechecked in one transaction, bound; returns only to the news page; `test_news_delete_and_keepalive_authority_http`. |
| `taunt.php` | admin SU_EDIT_CREATURES | no | READ-ONLY | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`. |
| `taunt.php?op=edit\|save\|del` | admin SU_EDIT_CREATURES | taunts | CLOSED | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`; `test_taunt_and_title_editor_authority_http`. |
| `titleedit.php` | admin SU_EDIT_USERS | no | READ-ONLY | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`. |
| `titleedit.php?op=add\|edit\|save\|delete\|reset` | admin SU_EDIT_USERS | titles, players' titles | CLOSED | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`; `test_taunt_and_title_editor_authority_http`. |
| `train.php` | player | no | READ-ONLY | `TRAINING-MASTER-AUTHORITY-20260927.md`. |
| `train.php?op=challenge\|autochallenge\|question` | player | training combat, levels | CLOSED | `TRAINING-MASTER-AUTHORITY-20260927.md`; `test_training_entry_authority_and_stale_round`, `test_training_victory_zero_negative_replay_and_next_level`. |
| `translatortool.php` | admin SU_IS_TRANSLATOR | no | READ-ONLY | `RESCAN-TASK2-20261022.md`: `u` and `t` checked text, read once, escaped; the namespace lookup bound; `test_translator_tools_authority_http`. |
| `translatortool.php?op=save` | admin SU_IS_TRANSLATOR | translations | CLOSED | `RESCAN-TASK2-20261022.md`: POST with CSRF and a one-use intent bound to the namespace, text and rows shown; one transaction, bound; Save No Close goes only to this tool's list; `test_translator_tools_authority_http`. |
| `translatortool.php?op=list` | admin SU_IS_TRANSLATOR | no | READ-ONLY | `RESCAN-TASK2-20261022.md`: `u` bound; output escaped; `test_translator_tools_authority_http`. |
| `untranslated.php` | admin SU_IS_TRANSLATOR | no | READ-ONLY | `RESCAN-TASK2-20261022.md`: the language preference and the random pick bound; the text escaped and read-only; `test_translator_tools_authority_http`. |
| `untranslated.php?op=list\|step2` | admin SU_IS_TRANSLATOR | translations | CLOSED | `RESCAN-TASK2-20261022.md`: POST with CSRF and a one-use intent bound to the collected text; the insert and delete in one transaction, only while the text is untranslated; bound; `test_translator_tools_authority_http`. |
| `user.php` | admin SU_EDIT_USERS | no | READ-ONLY | `USER-EDITOR-AUTHORITY-20261016.md`. |
| `user.php?op=search\|edit\|save\|special\|savemodule\|lasthit\|debuglog&subop=module\|xml` | admin SU_EDIT_USERS | accounts, module preferences | CLOSED | `USER-EDITOR-AUTHORITY-20261016.md`; `test_user_editor_account_save_authority_http`, `test_user_editor_special_module_and_log_authority_http`. |
| `user.php?op=setupban\|saveban\|delban\|removeban` | admin SU_EDIT_USERS | bans | CLOSED | `BAN-ADMIN-AUTHORITY-20261016.md`; `test_user_editor_ban_authority_http`. |
| `user.php?op=del` | admin SU_EDIT_USERS | account deletion | CLOSED | `DELETION-EXPIRATION-AUTHORITY-20261017.md`; `test_account_deletion_authority_http`. |
| `user.php?op=repair` | admin SU_EDIT_USERS | the failing stored value of one account | CLOSED | `RESCAN-TASK3-20261023.md`: shows only the stored values that fail their schema; one fixed repair each, a POST with CSRF and a one-use intent bound to the account and the failing value, locked and re-checked in one transaction (a value that passes is refused), with a debug log line; `test_user_editor_stored_state_repair_http`. |
| `viewpetition.php` | admin SU_EDIT_PETITIONS | cleanup | CLOSED | `PETITION-ADMIN-AUTHORITY-20261015.md`; `test_petition_admin_cleanup_and_commentary_http`. |
| `viewpetition.php?op=view\|setstat\|del` | admin SU_EDIT_PETITIONS | petition status | CLOSED | `PETITION-ADMIN-AUTHORITY-20261015.md`; `test_petition_admin_status_authority_http` (`del` is the 1.1.2 commented-out branch; its link is not served). |
| `village.php` | player | location, commentary | CLOSED | `MODERN-CORE-CHECKPOINT-20260913-PHASE2.md`: the 1.1.2 location write on view uses server values; commentary through the shared helper. No bundled village event. |
| `weaponeditor.php` | admin SU_EDIT_EQUIPMENT | no | READ-ONLY | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`. |
| `weaponeditor.php?op=add\|edit\|save\|del` | admin SU_EDIT_EQUIPMENT | weapon rows | CLOSED | `CONTENT-EDITORS-PART1-AUTHORITY-20261016.md`; `test_weapon_and_armor_editor_authority_http`. |
| `weapons.php` | player | no | READ-ONLY | Listing bound (`EQUIPMENT-PURCHASE-AUTHORITY-20261015.md`). |
| `weapons.php?op=buy` | player | gold, weapon | CLOSED | `EQUIPMENT-PURCHASE-AUTHORITY-20261015.md`; `test_weapon_and_armor_purchase_authority_http`. |

## Bundled module routes

A module's run function answers `runmodule.php?module=<name>`; a Forest event answers on `forest.php` while the event is
the player's current one (`specialinc`). All 24 bundled modules are certified in `BMC` (24 PASS, lifecycle 24 PASS).

| Entry | Who | Changes state | Status | Evidence or gap |
|---|---|---|---|---|
| `runmodule.php?module=cedrikspotions&op=bartender\|gems` | player | gems, potion effects | CLOSED | `CRS` (Cedrik rows), `BMC`; `test_potions_configured_prices_and_random_bounds`, `test_potions_dragon_reset_persistence_http`. |
| `runmodule.php?module=crazyaudrey&op=pet\|play\|baskets` | player | gold, Audrey's daily state | CLOSED | `CRS` (Crazy Audrey row), `BMC`; `test_module_audrey_village_authority`. |
| `forest.php?module=crazyaudrey&op=search\|baskets\|play\|run` | player (Forest event) | basket outcomes | CLOSED | `CRS` (Crazy Audrey row), `BMC`; `test_module_audrey_village_authority`. |
| `runmodule.php?module=dag&op=list\|addbounty\|finalize\|viewbounties\|bounties\|closebounty\|cleanup` | player / admin SU_EDIT_USERS | bounties | CLOSED | `CRS` (Dag rows), `BMC`; `test_dag_administrator_http_matrix`, `test_dag_funded_pvp_and_failure_rollback`. |
| `runmodule.php?module=darkhorse&op=enter\|tavern\|bartender\|oldman\|tables\|leave\|leaveleave&subop=search` | player (mounted, or the Forest event) | gold, wagers, mount entry | CLOSED | `CRS` (Dark Horse rows), `BMC`; `test_darkhorse_mounted_entry_and_exit_http`, `test_darkhorse_wagers_abandonment_and_information`. |
| `forest.php?module=darkhorse&op=search` | player (Forest event) | event | CLOSED | Same; the travel event needs the Cities module, which is not imported. |
| `runmodule.php?module=drinks&op=add\|edit\|save\|del\|activate\|deactivate&subop=module` | player (editor delegation) / admin SU_EDIT_USERS | drinks, purchases | CLOSED | `CRS` (Drinks rows), `BMC`; `test_drinks_editor_delegation_create_activation_delete`, `test_module_purchases_post_csrf_replay_and_effects`. |
| `forest.php?module=fairy&op=search\|give` | player (Forest event) | gems, hit points, turns | CLOSED | `CRS` (Fairy row), `BMC`; `test_fairy_settings_and_dragon_carry_http`. |
| `forest.php?module=findgem` | player (Forest event) | gems | CLOSED | `CRS` (Forest findgem row), `BMC`; `test_module_purchases_post_csrf_replay_and_effects`. |
| `forest.php?module=findgold` | player (Forest event) | gold | CLOSED | Same. |
| `forest.php?module=foilwench&op=give\|dont` | player (Forest event) | gems, skill | CLOSED | Same. |
| `runmodule.php?module=game_dice` | player (Dark Horse) | wagers | CLOSED | `CRS` (Dark Horse continuation), `BMC`; `test_darkhorse_wagers_abandonment_and_information`. |
| `runmodule.php?module=game_fivesix` | player (Dark Horse) | wagers, jackpot | CLOSED | Same; `test_darkhorse_shared_jackpot_concurrency`. |
| `runmodule.php?module=game_stones` | player (Dark Horse) | wagers | CLOSED | Same. |
| `forest.php?module=glowingstream&op=search\|drink` | player (Forest event) | hit points, turns, death | CLOSED | `CRS` (Fairy and Glowing Stream row), `BMC`; `ModuleCertificationTest` (PHPUnit). |
| `forest.php?module=goldmine&op=search\|mine\|no` | player (Forest event) | gold, gems, death, mount | CLOSED | `CRS` (Goldmine closure), `BMC`; `test_goldmine_deterministic_http_authority`. |
| `runmodule.php?module=lovers&op=flirt\|chat` | player | charm, daily flag | CLOSED | `CRS` (Lovers rows), `BMC`; `test_lovers_alternative_choices_daily_authority_http`. |
| `runmodule.php?module=outhouse&op=pay\|free\|washpay\|washfree\|nowash` | player | gold, outcomes | CLOSED | `CRS` (outhouse row), `BMC`; `test_outhouse_configured_outcomes_http`. |
| `runmodule.php?module=racedwarf&op=ale` | player | no | READ-ONLY | The dwarven ale room: navigation and the `ale` hook (drink forms are the drinks module's). |
| `runmodule.php?module=raceelf` | player | no | READ-ONLY | Empty run function; race choice is `newday.php` (`test_race_onboarding_http_authority`). |
| `runmodule.php?module=racehuman` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=racetroll` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=sethsong&op=strolldown\|fleedragon` | player (inn) | song effects | CLOSED | `CRS` (Seth's song row), `BMC`; `test_sethsong_all_configured_effects_http`. |
| `runmodule.php?module=specialtydarkarts` | player | no | READ-ONLY | Empty run function; choice and combat use are `newday.php` and the combat routes (`SPECIALTY-AUTHORITY.md`). |
| `runmodule.php?module=specialtymysticpower` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=specialtythiefskills` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=fairy` | player | no | READ-ONLY | Empty run function; the module is a Forest event. |
| `runmodule.php?module=findgem` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=findgold` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=foilwench` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=glowingstream` | player | no | READ-ONLY | Same. |
| `runmodule.php?module=goldmine` | player | no | READ-ONLY | Same. |

## Closed in this task

- `mercenarycamp.php?op=buy|heal` (hire and heal), with the listing query bound.
- `healer.php?op=buy|companion` (potions and companion healing), with the return target checked.
- `gypsy.php?op=pay|talk` (the paid seance).
- Read-only player pages bound: `list.php` (search, paging, clan list), `hof.php` (paging; `op`, `subop`, `page`
  checked), `news.php` (day and paging), and `mail.php?op=read` (previous and next message lookups).
- `lodge.php`: confirmed it spends nothing in core.

## Handed to final rescan task 2 (OPEN, admin)

`badword.php` writes, `gamelog.php`, `referers.php` (view and rebuild), `superuser.php?op=keepalive`,
`superuser.php?op=newsdelete`, `translatortool.php` (form, save, list) and `untranslated.php` (view, list, step2). Each
is a line on the `ROADMAP.md` Final rescan list.

## Player-facing OPEN entries left

- `bio.php`: the unchecked `ret` link (already on the Final rescan list). It changes no state; it is left for the
  return-link fix with the rest of that line.

## Recorded, not changed (1.1.2 behavior)

- The healer sets hit points above the maximum back to it on view, for free.
- `village.php` sets the player's location to the village on view; `news.php` expires old news on view; `home.php`
  caches the online count in a setting. All use server values only.
- The gypsy's seance commentary section, once shown, stays usable for the rest of the session through the shared
  commentary helper, as any shown section does.

## Tests

- `tests/tooling/test_route_inventory.py` (static): the source scan must match this inventory exactly, every row has
  a status and evidence, and the counts table adds up. Shown failing for a new page (`zz_newpage.php`), a new op
  (`gypsy.php?op=newop`) and a dropped row (`stats.php?op=referers`), each restored after.
- `test_mercenary_camp_hire_and_heal_authority_http`: GET confirm changes nothing; CSRF 403; repeat and stale forms
  409; short gold and gems; a URL `cost` ignored (the form shows and charges the server price); repricing between
  the form and the POST 409; quotes, a backslash and UTF-8 in the location and the companion name; a late write
  failure rolls back, and a fresh form then succeeds.
- `test_healer_potion_and_companion_authority_http`: the same for potions (sizes 100% to 10% only; others 400) and
  the companion heal (a URL `compcost` ignored), plus the return target.
- `test_gypsy_seance_authority_http`: the same for the paid seance; `op=talk` without payment is 403; a superuser
  with commentary moderation enters without paying, as in 1.1.2.
- `test_list_hall_of_fame_and_news_bound_requests_http`: quotes, a backslash, SQL text and UTF-8 in the list search
  find exactly the right warrior; an over-long or invalid UTF-8 name, an unknown `op` or `subop` and a malformed `page`
  answer 400; paging on the list, the HoF and the news still answers 200; nothing changes.
- `test_mercenary_companions_fight_and_malformed_companions_http` (existing): its hire step now confirms with the
  GET and posts the form. No assertion was removed or weakened.

## Before the fix

The new HTTP tests against the `c00f2d1` pages (with only the tests changed):

| Test | Failure |
| --- | --- |
| camp | `Fatal error` (database operation failed) for a quote in the location, in the unbound listing |
| healer | `healer.php?op=buy&pct=55` answered 200, not 400 |
| gypsy | `op=talk` answered 200 without payment, not 403 |
| list, HoF, news | the UTF-8 and quoted searches did not find the warrior; malformed requests answered 200, not 400 |

Guard reverts on the fixed code, one at a time:

| Revert | Result |
| --- | --- |
| R1: 1.1.2 camp (GET hire), only the listing bound | FAIL: no hire form, the GET acted |
| R2: camp heal price taken from the URL | FAIL: the form showed `(1 Gold)`, not `(159 Gold)` |
| R3: healer companion price taken from the URL | FAIL: the form showed `(1 Gold)`, not `(159 Gold)` |
| R4: healer potion size unchecked | FAIL: `pct=55` answered 200, not 400 |
| R5: gypsy payment not checked on `op=talk` | FAIL: 200 without payment, not 403 |
| R6: camp price recheck under lock removed | OK: the form context already binds the price; the lock recheck is defense in depth |
| R7: camp price bound neither in the context nor under lock | FAIL: a repriced companion was hired at the new price |

## Local verification

Supporting evidence only; CI is the acceptance. PHP 8.4 (the setup-php build) with a disposable MySQL 8.4 per shard:

- All four shards through `scripts/tooling-shards.py --shards 4`, each after a clean `composer test`: shard 1 OK (48
  tests, 1084 s), shard 2 OK (44, 1141 s), shard 3 OK (54, 1096 s), shard 4 OK (46, 1208 s); 189 of 189 discovered
  tests assigned. PHPUnit OK (205 tests, 139387 assertions) on each.
- PHP lint of every changed file; PHPStan `phpstan.neon` and `phpstan-new.neon`: no errors.
- `composer validate --strict`, `composer audit`: valid, no advisories.
- Historical verifier PASS (417 files, 11 commits); project validator PASS; `git diff --check` clean.

## CI failure on `593578e`, 2026-10-22 (kept on record)

Modern core 39822309470 on `593578e`: every job passed except engineering (8.5, mysql:8.4) / http (4), which ran 15
tests in 318 s and failed `test_forest_specialty_post_authority_and_rollback` (spec `TS`, level `5`) at
`self.assertEqual(200,request('village.php')[0])`: `AssertionError: 200 != 302`. Baseline integrity 39822309107
passed. The run (17:24 to 17:30Z) was not near a game-day boundary. This run is not acceptance for rescan task 1.

Reproduction on PHP 8.5 / MySQL 8.4 (supporting evidence only):

- Shard 4 through `scripts/tooling-shards.py` twice, and the shard 4 order up to the failing test three times: all
  passed. The player entered the test at level 1, experience 0, `seenmaster` 0, alive, with the day current.
- A probe that repeats the test's own `prepare()`, specialty POST and `village.php` visit hit the failure: 3 of 800
  Backstab (TS 5) rounds, none of 300 rounds at DA 1, MP 5 and TS 3. The 302's `Location` was `shades.php`. At that
  moment WebPlayer had `alive` 0, `hitpoints` 0, level 10, experience 0, `seenmaster` 0, `badguy` empty, and the round's
  page read: "Thieving Kender hits you for 15425 points of damage! ... You may begin fighting again tomorrow."

Root cause: a fixture gap, not a product defect and not MySQL-specific. `rolldamage()` takes the player's defense roll
from `bell_rand(0, defense x defmod)`, whose 1.1.2 curve runs from -0.716599 to 1.712549 of the range, and the
creature's hit is its attack roll minus that defense roll. A negative defense roll therefore becomes a hit of up to
0.716599 x defense x defmod. The fixture gave the player defense 10000 and 10000 hit points; Backstab sets defmod 3,
so a round can deal up to about 21500 (roughly 3 rounds in 1000 deal 10000 or more). The player dies, the round is
an ordinary forest defeat, and `village.php` correctly redirects a dead player to `shades.php`. Only TS 5 raises
defmod in this test, which is why its earlier subtests passed. The failure is random: it is not tied to the shard
order, the experience, `seenmaster` or the database; the commit's new tests only changed which run drew it.
`lib/bell_rand.php` and `rolldamage()` match the historical tag except for the PHP 8 iterator and the PvP loop cap.

Fix: the test's `prepare()` now pins defense 1000, which keeps the largest possible inverted hit at about 2150, far
under the 10000 hit points, with the reason in a comment. The assertion is unchanged: `village.php` must answer 200
and the state must not change. No product code changed; the 1.1.2 combat math is kept.

Other fixtures that visit `village.php` after combat were checked against the same bound. The skeleton companion
case (`test_darkarts_companion_business_state_http`: defense 10000, 10000 hit points, Dark Arts level 1, defmod 1)
can lose at most about 7170 in its one round and stays alive. The other high-defense fixtures (the dragon, Fairy
and PvP bounty cases) do not visit `village.php` after the fight, use a seeded roll, or expect a defeat. None
shares the gap.

Local result with the fix (supporting evidence only): the probe ran 1000 Backstab rounds with no death; shard 4
passed on MySQL 8.4 (46 tests, 878 s) and on MariaDB 11.4 (46 tests, 864 s), each after PHPUnit OK (205 tests).
Static: `test_route_inventory`, `test_tooling_shards`, the historical verifier, the project validator and
`git diff --check` pass.

## Rescan task 1 accepted through `aea4608`, 2026-10-22

Rescan task 1 (`593578e`) is accepted through `aea4608`, its first accepted descendant, which changes only the forest
specialty fixture described above. `593578e`'s own run, Modern core 39822309470, failed (engineering (8.5, mysql:8.4) /
http (4)) and stays on record as a failure; it is not acceptance.

On `aea4608` (`aea46084430feb92d432cc99b3ccc8bbf0b1ac03`), checked through the GitHub API job by job:

| Run | Job | Conclusion |
| --- | --- | --- |
| Modern core 39841362118 | engineering (8.4, mariadb:11.4) / checks | success |
| | engineering (8.4, mariadb:11.4) / http (1) | success |
| | engineering (8.4, mariadb:11.4) / http (2) | success |
| | engineering (8.4, mariadb:11.4) / http (3) | success |
| | engineering (8.4, mariadb:11.4) / http (4) | success |
| | engineering (8.4, mariadb:11.4) / result | success |
| | engineering (8.5, mysql:8.4) / checks | success |
| | engineering (8.5, mysql:8.4) / http (1) | success |
| | engineering (8.5, mysql:8.4) / http (2) | success |
| | engineering (8.5, mysql:8.4) / http (3) | success |
| | engineering (8.5, mysql:8.4) / http (4) | success |
| | engineering (8.5, mysql:8.4) / result | success |
| Baseline integrity 39841362038 | provenance | success |

This closes what task 1 closed: the mercenary camp hire and heal (its Final rescan line), the healer's potions and
companion healing, the gypsy's paid seance, and the bound read-only pages (`list.php`, `hof.php`, `news.php`, the mail
read page).

## Final rescan task 2, 2026-10-22 (candidate)

The twelve OPEN entries handed over above are closed in `RESCAN-TASK2-20261022.md`: five state-changing routes are
CLOSED (`badword.php` writes, `referers.php?op=rebuild`, `superuser.php?op=newsdelete`, `translatortool.php?op=save`,
`untranslated.php?op=list|step2`) and seven are READ-ONLY with their request values bound and output escaped (`bio.php`,
`gamelog.php`, `referers.php`, `superuser.php?op=keepalive`, `translatortool.php` and its list, `untranslated.php`). The
keepalive stays a GET and the referer expiry stays on view; both write server values only (decisions in the evidence
document). Counts: CLOSED 87, DISABLED 9, READ-ONLY 69, OPEN 0, total 165.

`test_rescan_task2_entries_have_their_evidence` (static) checks that each of the twelve has its status, cites the
evidence document and names an HTTP test that exists. Against the task 1 inventory it fails: every entry is OPEN.

New gap found (on the Final rescan list): `common.php`'s referer tracking writes the visitor's `Referer` host and the
request path into `referers.site` (50 characters) and `referers.dest` (255) unchecked; under strict SQL a longer value
fails the page. Not a route of its own; it runs on every page.

## Final rescan task 2 accepted, 2026-10-23

Task 2 is accepted through `107ce87`, the first accepted descendant of its candidate `623ca49`: Modern core
39929029453 and Baseline integrity 39929029457 succeeded. `623ca49`'s own run, Modern core 39868186035, failed on
MariaDB (the referer list's 1.1.2 loop counter) and stays on record. The twelve entries above stand as recorded.

## Final rescan task 3, 2026-10-23 (candidate)

One new entry: `user.php?op=repair`, the user editor's repair path for an account locked out by stored state
(`RESCAN-TASK3-20261023.md`), CLOSED. Counts: CLOSED 88, DISABLED 9, READ-ONLY 69, OPEN 0, total 166.

The SQL sweep in the same document ties each value it bound to its routes: `village.php` and every other page that shows
commentary (`comscroll`), `motd.php` (`newcount`), `hof.php` (the translated "Unknown"), and the shared libraries behind
many routes (the game log, the module API, the PvP list, the title lookup). Each of those entries keeps its status; their
request values are now bound. `common.php`'s referer tracking (not a route of its own) fits its values to the columns.
