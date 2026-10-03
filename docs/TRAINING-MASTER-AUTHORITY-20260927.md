# Training/master combat candidate, 2026-09-27

Starting commit: `bc942057570559beb0bec82084d7108da756ac3c` on
`modernization/core-modernization`, PR #1 OPEN/DRAFT, main
`999cec6f9c655a840320d982d673bb863c68c2b2`. This is one bounded Phase 3
training/master slice. No other combat family is promoted or developed here.

Status: **Training/master combat BLOCKED pending exact candidate supported-matrix CI.**
Local implementation/evidence is complete as enumerated below. Broader combat is
BLOCKED; Phase 3 is INCOMPLETE; merge NO; public hosting NO. The commit carrying
this document is the candidate; its exact SHA and workflow IDs belong in PR #1
and the publication report, not a self-referential source commit.

## Historical authority and route inventory

Authority is preserved `historical-source-1.1.2:train.php`, `battle.php`,
`lib/experience.php`, `lib/battle-skills.php`, `lib/extended-battle.php`,
`lib/installer/installer_sqlstatements.php`, and `village.php`. The historical
master table has 14 rows, Mireraband through Yoresh. Selection uses the greatest
master level no higher than the player's level, randomly choosing among rows at
that level. The request no longer supplies identity or victory. The unrestricted
legacy `victory` override, including its developer navigation, is removed.

| Route or caller | Historical classification | Candidate classification |
| --- | --- | --- |
| `train.php`, `op=question` | Navigation GET with checkday | Read-only training presentation GET |
| `op=challenge` | GET sets seenmaster, creates fight, may resolve an opening round | GET confirmation; authenticated POST mutation |
| `op=autochallenge` | GET heals, invokes hook, writes news | GET confirmation; eligible POST combines automatic-challenge preparation and combat entry |
| `op=fight`, optional `auto` | GET combat mutation and terminal settlement | GET live presentation; POST fight with bounded `rounds` field |
| `op=run` | GET refuses escape and fights | GET presentation; old unprotected mutation rejected, no flee control offered |
| `master`, `victory`, `skill`, `l`, target/stat/reward overrides | Request authority or unsupported specialty input | Rejected before bootstrap in GET and POST |
| `village.php` truant check | Internal redirect to autochallenge GET | Same redirect; destination now needs protected POST |
| `battle.php` | Internal shared engine | Unchanged internal engine; direct HTTP remains 404 |
| `lib/training_outcomes.php` | Extracted training settlement | Internal include in locked transaction; direct HTTP 404 |

No training gameplay action remains authorized by GET. Normal rendering may
persist the established navigation/access metadata and buff calculation markers;
these are excluded from gameplay authority through canonical context handling.
No master HP, player HP, challenge flag, advancement, rewards or turns are changed
by training GET rendering. New Day remains a separate existing boundary; challenge
entry requires the current day rather than running New Day through this route.

Eligibility is a living authenticated player with positive HP, level 1 through 14,
no active event or different combat, and an available server-selected master.
Challenge attempts require `seenmaster=0`. Insufficient experience historically
spends the daily challenge without combat or advancement, and this remains true.
Experience is `exp_for_next_level(level, dragonkills)`: the original lookup plus
`round((dragonkills/4)*level*100)`. Automatic challenges additionally require
`automaster` and experience strictly above the next level's requirement. Their
healing/hook/news happens once inside entry, never merely on visiting the URL.

Master scaling is unchanged: count stored attack/defense Dragon spends, add
integer `(maxhitpoints-level*10)/5`, multiply by .33 and round. Attack and defense
allocations use the historical random draws capped at rounded 25% of that budget;
remaining points add five HP each. Normal post-Dragon reset state uses its current
level, HP and points. No Dragon allocation, reset or endgame redesign is included.
The Elf-specific Gadriel dialogue remains. Level 14 advances to 15; level 15 GET
shows the historical memories exit, and all further training mutations reject.

## Authority and state

`TrainingCombatState` owns one live master and its training encounter. It does not
weaken `ForestCombatState` or `ScalarState::read()`. Validation checks exact selected
master identity, nearest-level relationship, immutable row values, legal scaled
attack/defense/max HP, positive current HP no greater than starting master HP,
player start HP, single current target, live flags, training level and encounter
identity. Forest rewards, extra enemies, terminal-as-live representations and
unexpected options/fields reject. Actual null master gold/experience values are
retained; master combat does not award these resources.

Entry and fight require POST, CSRF, a scoped one-use state-bound intent and
`resurrection_player_mutation`. The account is locked and compared with the
hydrated account before execution. Context is rechecked within the transaction.
It binds player identity, level/experience, HP/stats, seenmaster, location,
Dragon state, master rows, encounter/HP/status, buffs, companions, material settings
and specialty preferences. Map field order is canonical; participant collection
order remains meaningful. Pre-entry, pre-round and winning forms cannot repeat a
round, recreate combat or settle twice. Terminal combat is cleared only after
server-resolved victory/defeat inside the transaction. No separate advancement
submission exists.

Corrupt state is rejected and preserved for explicit operator repair. It is never
silently reinterpreted, cleared, or treated as a victory/defeat. HTTP tests include
malformed serialization, PHP objects, wrong roots, absent/unsupported master,
identity/level/stat/HP mismatches, wrong family, duplicate/invalid target,
malformed buffs/companions and terminal-as-live state. Explicit fixture repair is
followed by successful normal continuation. Bootstrap errors on this route return
a generic response without PHP/SQL traces.

## Historical settlement and participant behavior

| Result | Exact historical effect |
| --- | --- |
| Victory | Level +1; max HP +10; soulpoints +5; attack +1; defense +1 |
| Healing | Victory raises current HP to new max only if below it; overfull HP remains overfull |
| Experience/resources | Experience, gold, gems and turns unchanged; no Forest rewards |
| Master flag | Entry sets seenmaster=1; victory resets to 0 only when multimaster=1 |
| Specialty | Selected bundled specialty skill +1; every third skill level grants one extra use |
| Companion growth | When enabled, surviving companions gain per-level stats and heal to new max HP |
| Referral | Eligible unawarded referral grants configured donation, marks awarded, inserts system mail |
| Victory hooks/news | Historical news and training-victory hook, navigation to question/challenge/village |
| Defeat | Player stays alive, heals to current max HP, retains level/stats/experience/resources and seenmaster=1 |
| Defeat hooks/news | Historical taunt/news and training-defeat hook; question/challenge/village navigation |
| Terminal effects | Expire fight-only effects, restore suspended participants, clear settled combat |

The shared engine retains its historical zero/negative HP ordering. Controlled
HTTP victories reach master HP exactly 0 and -1 and advance once. The next request
sees the new level and its new requirement. Insufficient experience cannot create
the next combat. Fresh successive training is possible when multimaster is enabled
and stored experience already meets the next requirement, as historically intended.

Training uses existing validated buff/companion consumer schemas, with additional
training presentation/effect-field checks. `allowintrain` governs participation.
The Dark Arts skeleton lacks this flag, so it is suspended without attacking or
being injured in the master fight. Permitted companions participate through the
unchanged engine ordering: the tested fighting companion acts after the player
attack and master response. Surviving companions grow on victory. Missing optional
per-level growth values default to zero, preserving old PHP's arithmetic meaning
without modern undefined-key errors. Bundled Elf/Troll formulas remain formulas
on persistence, and all three specialty growth/third-level use boundaries are tested.
No new buff/companion producer is certified here.

Victory and defeat fault injection fails the final account write after real
transactional hook/news work. Player/combat/participant state and those related
writes roll back. A second victory test covers referral donation, inserted mail,
awarded flag and specialty preferences. Entry has its own late-write rollback.
The failed intent remains consumed; a fresh form retries successfully once.
Referral email notifications are queued using the existing notification mechanism
and delivered only after commit. External mail delivery and cache invalidation are
not claimed as transactional rollback guarantees.

## Verification and publication boundary

Local runtime: PHP 8.4.26 / MariaDB 11.4.13. **93 PHPUnit tests / 2,531 assertions**
pass. The focused integration run passes **30 HTTP tests**, including all ten new
training matrices, ordinary Forest, all six Mystical Powers additions, Dragon,
Dark Arts, Thieving, Transmutation, authentication and direct/nonexposing combat
rejection. A final supplemental participant/rollback run checks the strengthened
companion-order and defeat-participant assertions. These overlap existing methods,
not additional distinct tests. Final local runs have zero failures/skips.

The suite collects **82 Python tests: 73 HTTP + 9 tooling**. All nine tooling tests
pass. **331 PHP files lint with zero failures**. Both PHPStan gates pass, including
the new training code in the infrastructure gate. Composer strict validation,
locked installation and audit pass with no advisories; dependencies and workflows
are unchanged. Existing test methods/assertions were retained. The deterministic
normal training round changes player HP 5000 to 4823 and master HP 100000 to 99960,
retains the live target and persists to a fresh request. Exact supported-matrix
acceptance is reserved for the published candidate's Modern core jobs
(PHP 8.4/MariaDB 11.4 and PHP 8.5/MySQL 8.4).

Prior ordinary Forest candidate run `36315549242` was observed IN_PROGRESS during
initial verification; `36315549203` Baseline integrity was SUCCESS. No repeated
polling, redispatch, separate ordinary-combat closeout or Mystical Powers promotion
was performed. The last accepted module totals remain **23 PASS / 0 limitations /
1 BLOCKED**; candidate module evidence remains **24 PASS / 0 limitations / 0 BLOCKED**.
The cancelled standalone Mystical Powers run is not called a test failure.

Historical integrity PASS: annotated tag `historical-source-1.1.2`, tag object
`51cab4fbe58a234651a3177a56289b18bc152b4d`, source
`bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2`, tree
`4013a0ccc5227e87cd7a22de00b7c322d7aa237c`, 417 files, 11 preservation commits.

Remaining Phase 3 blockers: broader PvP, remaining module combat callers,
graveyard/other shipped combat families, broader serialized producer/consumer
schemas, New Day dk/pdk, mail/systemmail, petitions, clans, bank/economy,
equipment, stables, editors, expiration cleanup and account deletion. Their
acceptance is not implied by training evidence. No VPS, deployment, release,
main, historical source or workflow changes are made by this candidate.

Next task: inspect this candidate's exact Modern core and Baseline integrity
results once and accept training only if the complete supported matrix passes;
otherwise correct the exact failure narrowly. Resolve the pending earlier-slice
acceptance separately. Do not begin another family as part of this execution.
