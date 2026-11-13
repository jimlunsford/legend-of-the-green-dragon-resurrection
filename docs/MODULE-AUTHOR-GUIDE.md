# Module author guide

For anyone writing a module for Legend of the Green Dragon - Resurrection, or porting one from LoGD 1.1.2. A 1.1.2
module's shape is unchanged: one file `modules/<name>.php` with its `_getmoduleinfo()`, `_install()`, `_uninstall()`,
`_dohook()` and `_run()` functions, the same settings and preferences, and nearly all of the same hooks. What is new
are the rules every page now follows: a Content Security Policy, an entry guard on every internal file, POST and CSRF
for every change, one-use action tokens for game actions, and no secret in a kept page. This guide says what each rule
asks of a module, with the helper to use.

Words in `code font` are functions, hooks, classes, constants, settings or files exactly as the code names them
(`tests/tooling/test_module_author_guide.py` checks that each one exists). `mymodule` stands for your module's name.

**Only the 24 bundled modules install (rc.1).** The module manager installs a module only if it is in
`resurrection_bundled_modules()` (`resurrection_module_bundled()`); any other installed module may only be deactivated.
A module you write runs on a Resurrection server only after it is admitted into the release: section 9.

Module code is trusted PHP. It runs with the game's full rights over the database and the server, so these rules are
what keeps a module from undoing the game's protections. They are not a sandbox.

## 1. The entry guard

Every PHP file under `modules/` (the module and any support file in `modules/mymodule/`) must start with this line,
right after `<?php` (and after `declare` and `namespace`, if the file has them):

```php
<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
```

The game's pages (`runmodule.php`, `village.php` and the others) define `RESURRECTION_ENTRY` before anything else, so
the guard passes when the game loads the module. A browser that asks for the module's file directly gets an empty 404:
nothing runs and nothing is printed. The sample server rules (`docs/hosting/nginx.conf`, `docs/hosting/apache.conf`,
`docs/hosting/htaccess`) also refuse `modules/` entirely; the guard protects a host that did not install them.

`tests/unit/EntryGuardTest.php::testEveryInternalFileChecksTheConstantFirst` fails on any file under `modules/`,
`lib/` or `src/` whose first statement is not this check, and
`tests/tooling/test_operator_pages.py::test_internal_files_answer_a_direct_request_with_an_empty_404` asks for them
directly.

## 2. Hooks

A module still registers hooks with `module_addhook()` or `module_addhook_priority()` in its `_install()`, and gets
them in `mymodule_dohook($hookname, $args)`, which returns `$args`. `modulehook()` calls them as in 1.1.2.

### Hooks Resurrection added

- **The `scene` hook.** Each player page shows a scene picture above its text (Phase 5). The hook gets
  `['script' => ..., 'scene' => ..., 'alt' => ...]`: the page's script (such as `runmodule.php`), the scene it will
  show and that scene's alt text. Set `scene` to another scene in `assets/scenes/` (by its file name without the
  extension, such as `forest`), with your own `alt` if you like, or to `''` for no scene. A scene that does not exist is ignored. The
  hook is asked only on player pages. A module's own page shows the fallback scene unless its module is listed in
  `Art::MODULES` or this hook names one.
- **The `creatureportrait` hook.** A foe's card shows a portrait. A creature with no portrait of its own (any creature a
  module adds) gets the portrait of its type. The hook gets `['creature' => ..., 'fight' => ..., 'type' => 'unknown']`;
  set `type` to one of `Art::CREATURE_TYPES`: humanoid, beast, undead, spirit, monster, demon, construct, plant,
  dragon or unknown. Anything else is unknown.

```php
<?php
function mymodule_dohook($hookname, $args){
	switch ($hookname) {
	case "scene":
		if ($args['script'] === 'runmodule.php' && ($_GET['module'] ?? '') === 'mymodule') $args['scene'] = 'forest';
		break;
	case "creatureportrait":
		if (($args['creature']['creaturename'] ?? '') === 'Lantern Wisp') $args['type'] = 'spirit';
		break;
	}
	return $args;
}
```

The `header-graveyard` hook fires as it did in 1.1.2 (where `page_header()` called it as the graveyard's
`header-{script}`); the graveyard's code now names it itself. The hooks `camplocs` and `stablelocs` still fire, through
`resurrection_object_locations()`.

### What a module no longer gets

- **No install or upgrade on load.** 1.1.2's `injectmodule()` reinstalled a module whenever its file's date changed.
  Now loading a module never installs or upgrades it: that happens only through the module manager, the upgrade
  command or `setup.php` (section 8).
- **Module settings are checked by their declared types.** Settings and preferences are declared in
  `_getmoduleinfo()` as in 1.1.2, and `SettingDescriptor::declare()` reads them. A saved value must fit its type
  (`string`, `text`, `textarea`, `bool`, `int`, a bounded `int,LOW,HIGH`, `range`, `floatrange`, `amount`, `enum`,
  `enumpretrans`); `title`, `note`, `viewonly`, `invisible`, `hidden`, `bitfield` and `locked` are shown but never
  saved. Any other type is refused, so a module that declares one cannot be configured.
- **The email address is not saved from the session.** Since Phase 6 task 13c `saveuser()` never writes
  `emailaddress`, `emailverified` or `emailvalidation`: a module that sets `$session['user']['emailaddress']` changes
  nothing in the database. A player changes the address in Preferences (with the current password, and while account
  mail is on only through a link mailed to the new address); an administrator in the user editor.

### Every hook that fires

Every hook name the game, its pages and the bundled modules call at this commit. A name in braces is filled in when
it fires (`header-{script}` is `header-village` on `village.php`, `village-{location}` uses the player's location).
A hook not in this list never fires, and a module that registers it is never called.

`about`, `addpetition`, `adjuststats`, `ale`, `alter-companion`, `alter-gemchance`, `apply-specialties`, `armortext`,
`battle`, `battle-defeat`, `battle-victory`, `bio-mount`, `bioend`, `bioinfo`, `biostat`, `biotop`,
`blockcommentarea`, `boughtmount`, `buffbadguy`, `buffdragon`, `camplocs`, `censor`, `changesetting`, `charstats`,
`check-create`, `check-login`, `check_su_access`, `checkuserpref`, `choose-specialty`, `chooserace`,
`clan-applymember`, `clan-delete`, `clan-enter`, `clan-setrank`, `clan-withdraw`, `clanhall`, `clanranks`,
`collapse-nav{`, `collapse{`, `collect-events`, `commentary`, `companionsallowed`, `create-form`,
`creatureencounter`, `creatureportrait`, `dagnav`, `darkhorsegame`, `delete_character`, `dk-preserve`,
`dkpointlabels`, `donation`, `donation_adjustments`, `donator_point_messages`, `dragonkill`, `dragonkilltext`,
`drinks-check`, `drinks-text`, `everyfooter`, `everyfooter-loggedin`, `everyheader`, `everyheader-loggedin`,
`everyhit`, `everyhit-loggedin`, `faq-posttoc`, `faq-pretoc`, `faq-toc`, `fightnav`, `fightnav-specialties`,
`fightoptions`, `footer-popup`, `footer-{module}`, `footer-{script}`, `forest`, `forest-desc`, `forestsearch`,
`gardens`, `graveyard`, `graveyard-desc`, `grottonav`, `gypsy`, `header-graveyard`, `header-popup`,
`header-{script}`, `healmultiply`, `holiday`, `hprecalc`, `incrementspecialty`, `index`, `inn`, `inn-desc`,
`innchatter`, `innrooms`, `insertcomment`, `lodge`, `mailfunctions`, `master-autochallenge`, `mausoleum`,
`mercenarycamptext`, `moderate`, `modify-armor`, `modify-buff`, `modify-weapon`, `modifyuserview`, `mountfeatures`,
`mundanenav`, `newday`, `newday-intercept`, `newday-runonce`, `news-intercept`, `notifyuserprefchange`,
`onlinecharlist`, `pdkpointrecalc`, `petition-status`, `petitionform`, `petitions-descriptions`, `player-login`,
`player-logout`, `pointsdesc`, `potion`, `pre-newday`, `process-create`, `process-createclan`, `pvpadjust`,
`pvpcount`, `pvploss`, `pvpmodifytargets`, `pvpstart`, `pvpwarning`, `pvpwin`, `raceminedeath`, `ramiusfavors`,
`rock`, `runevent_{module}`, `scene`, `set-specialty`, `setrace`, `shades`, `showformextensions`, `showsettings`,
`soberup`, `soldmount`, `specialtycolor`, `specialtynames`, `stable-mount`, `stablelocs`, `stables-desc`,
`stables-nav`, `stabletext`, `superuser`, `template-{fieldname}`, `titleedit`, `training-defeat`,
`training-victory`, `validateprefs`, `validforestloc`, `validlocation`, `viewcommentary`, `village`, `village-desc`,
`village-desc-{location}`, `village-{location}`, `villagenav`, `villagetext`, `villagetext-{location}`,
`weaponstext`, `}collapse`, `}collapse-nav`

## 3. Presentation

Resurrection has one UI (`templates/resurrection.htm`, styled by `assets/ui/resurrection.css`). A module prints
classes the stylesheet styles, never inline style: no `style` attribute, no `<style>` element, no `<font>` or
`bgcolor` (the policy in section 4 blocks inline style).

- Text goes through `output()` and `output_notl()`, which escape HTML and turn 1.1.2's color and format codes into
  classes (`appoencode()`: `` `2 `` prints `.colDkGreen`, `` `^ `` prints `.colLtYellow`). Markup a module builds goes
  through `rawoutput()`, and the module escapes every value in it (`resurrection_attribute_value()` or
  `htmlspecialchars()`).
- Buttons: `class="button"`, or `resurrection_button_class()` (`resurrection_button_class(true)` gives the primary
  button, `.button` and `.rz-button-primary`).
- Table rows as in 1.1.2: `.trhead`, `.trlight`, `.trdark`, `.trhilight`.
- Labels: `resurrection_control_id()` on the control, and `resurrection_label_open()` / `resurrection_label_close()`
  around its label text.
- `resurrection_presentation()` returns the attribute or tag for each of 1.1.2's inline looks:

| Call | Prints |
| --- | --- |
| `resurrection_presentation('grid')` | `.rz-grid`: an admin or module table drawn as a grid; on a phone it scrolls inside its own box |
| `resurrection_presentation('grid-fit')` | `.rz-grid` and `.rz-fit`: a player-facing list that fits a phone's width |
| `resurrection_presentation('inline-form')` | `.rz-inline`: a form inside a line of text or a table cell |
| `resurrection_presentation('link-button')` | `.rz-link-button`: a button drawn as a link |
| `resurrection_presentation('larger')`, `resurrection_presentation('larger-end')` | `.rz-larger` around a heading-like line |
| `resurrection_presentation('violet')`, `resurrection_presentation('grey')`, `resurrection_presentation('white')`, `resurrection_presentation('gold')` | a narrator's colored line (`.colBlueViolet`, `.colLtBlack`, `.colLtWhite`, `.colLtYellow`) |
| `resurrection_presentation('typed')`, `resurrection_presentation('pre-wrap')` | `.rz-typed`: text shown as the player typed it |
| `resurrection_presentation('indent')` | `.rz-indent` |
| `resurrection_presentation('plain-link')` | `.rz-plain-link`: a link without its underline |

A label-and-field table that should stack on a phone (each label above its field) takes `.rz-stack`.
`tests/unit/PresentationTest.php` lists every key.

## 4. The Content Security Policy

Every page is sent with this policy (`SecurityHeaders::POLICY`):

```
default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

A browser runs only the game's own script files, loads only its own stylesheets, images and fonts, and sends forms
only to the game. So a module must not print:

- an inline script (`<script>` without `src`), an event handler attribute (`onclick`, `onchange`, `onsubmit` and the
  others) or a `javascript:` URL;
- inline style (section 3);
- anything from another site: images, fonts, scripts, frames or a form that posts elsewhere.

`tests/unit/InlineContentTest.php::testNoShippedSourcePrintsWhatThePolicyRefuses` scans every shipped PHP file,
modules included, for each of these.

**Behaviors.** 1.1.2's inline scripts became data attributes that `assets/ui/behaviors.js` reads on every page
(`resurrection_behaviors_script()` loads it). Use the helper where there is one:

| What | Attribute | Helper |
| --- | --- | --- |
| Ask before a link, button or form goes ahead | `data-rz-confirm` | `resurrection_confirm_attribute()` |
| Open a link as a popup window | `data-rz-popup` | `resurrection_popup_attributes()` |
| Give a field the focus when the page loads | `data-rz-focus` | `resurrection_focus_marker()` |
| Send a form when its select changes | `data-rz-autosubmit` | |
| Copy a field's value to another field | `data-rz-copy-to` | |
| Show a hidden part when a link is clicked | `data-rz-reveal`, `data-rz-reveal-area` | |

`assets/ui/behaviors.js` documents the others at its top. Every behavior is an enhancement: the page must still work
without it.

**A behavior of your own** goes in a static script file, loaded with `<script src="..." defer></script>` and driven by
data attributes on your markup. Put the file under `assets/` in its own folder (`assets/<module>/`), not under
`modules/`: the sample server rules refuse every request for `modules/`, so a browser would never get it. Give the
folder its own license file: `assets/LICENSE` covers only the game's art (`assets/scenes/`, `assets/portraits/`,
`assets/icons/`).

**`csp_mode` is the host's call.** The `csp_mode` setting (Game Settings, "Content Security Policy") is `enforce` by
default. `report-only` sends the same policy as `Content-Security-Policy-Report-Only`: the browser blocks nothing and
writes each violation to its own console (the policy names no report address, so nothing is sent anywhere). `off`
sends no policy. A host may use report-only for a while to see what a module breaks. A module must work under
`enforce`, must not ask hosts to change the setting, and must not send its own policy header.

## 5. State changes: POST, CSRF and action tokens

A request that changes anything (gold, a preference, a table row, a setting) must be a POST with the session's CSRF
token. A GET page shows a form and changes nothing. A game action also carries a one-use action token, so a repeated
or replayed form is refused.

| Helper | What it does |
| --- | --- |
| `resurrection_csrf_field()` | the hidden CSRF field for a form |
| `resurrection_require_post()` | stops the request with 403 "Invalid form submission." unless it is a POST with the session's token |
| `resurrection_action_fields()` | the CSRF field and a one-use action token for a scope and a context |
| `resurrection_content_form()` | the opening `<form method='POST'>` tag with both, the action allowed as a navigation target (`addnav('', $url)`) |
| `resurrection_consume_action()` | checks both and uses the token up; 409 "Expired or repeated action." if it is gone or does not match |
| `resurrection_player_mutation()` | runs your change in one transaction with the player's account locked and checked again; a `DomainException` rolls it back |

The **scope** names the action (your module's name, or `mymodule-buy`). The **context** binds the token to the state
it was shown for: the account and what the form does, such as the player's id and the step. Never put `authversion` or
anything else that changes on every sign-in in a context, or a page kept from before a new sign-in can never be sent
(Phase 6 task 9). The callback of `resurrection_player_mutation()` may change the player and the database rows. It
must not call `page_footer()` or `saveuser()`, exit, flush output, or change a table's structure.

```php
<?php
function mymodule_run(){
	global $session;
	require_once 'lib/content_editor.php';
	$url = 'runmodule.php?module=mymodule&op=buy';
	$context = (string)$session['user']['acctid'] . ':buy';
	page_header('The Lantern Seller');
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		output('A lantern costs %s gold.`n', get_module_setting('cost', 'mymodule'));
		rawoutput(resurrection_content_form($url, 'mymodule', $context)
			. '<button class="' . resurrection_button_class(true) . '">'
			. htmlspecialchars(translate_inline('Buy a lantern'), ENT_QUOTES, 'UTF-8') . '</button></form>');
		addnav('Return to the Forest', 'forest.php');
		page_footer();
	}
	resurrection_consume_action('mymodule', $context);
	try {
		resurrection_player_mutation(function () {
			global $session;
			$cost = (int)get_module_setting('cost', 'mymodule');
			if ($session['user']['gold'] < $cost) throw new DomainException('Not enough gold.');
			$session['user']['gold'] -= $cost;
			set_module_pref('lanterns', (int)get_module_pref('lanterns', 'mymodule') + 1, 'mymodule');
			output('You buy a lantern.`n');
		});
	} catch (DomainException $error) {
		http_response_code(409);
		exit('Lantern purchase rejected.');
	}
	addnav('Return to the Forest', 'forest.php');
	page_footer();
}
```

`page_footer()` ends the request, so the GET branch stops there. Read request values through `\Resurrection\Http\Input`
(`Input::choice()`, `Input::integer()`, `Input::string()`, `Input::boolean()`) and answer 400 to a value it refuses.
Settings and preferences saved from the module manager and the user editor are already POSTs with CSRF and an action
token; a module needs none of this for them.

## 6. Secrets on a page

1.1.2 keeps each signed-in player's last page in the database (`accounts_output`): `badnav.php` shows it again, and the
user editor shows it to administrators. Two rules keep secrets out of that copy:

- **Form tokens are handled for you.** `KeptPage::keep()` replaces the CSRF token and every action token in the kept
  copy with a marker, and `KeptPage::replay()` fills the markers from the session that shows the page again. Use the
  helpers in section 5 and print no token any other way.
- **A secret goes through `resurrection_secret_output()`.** A password, a key, a one-time code or anything else the
  player or an administrator must see once: print that part of the page with
  `resurrection_secret_output($html, $secret)`, naming every secret value in it. The page is sent with
  `Cache-Control: no-store`, and the kept copy (`resurrection_secret_kept_copy()`) has the part replaced by a note.

```php
<?php
function mymodule_show_code(string $code){
	resurrection_secret_output('<p>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>', $code);
}
```

Mark every parameter that carries a secret `#[\SensitiveParameter]`, so it never shows in a stack trace. Never put a
secret in a URL, a navigation link, `debuglog()`, mail or a module preference.

## 7. SQL

Every value goes into a query as a bound parameter, never into the SQL text:

```php
<?php
function mymodule_lanterns_of(int $acctid){
	$rows = db_query('SELECT value FROM ' . db_prefix('module_userprefs') . ' WHERE modulename=? AND setting=? AND userid=?',
		true, ['mymodule', 'lanterns', $acctid]);
	return (int)($rows[0]['value'] ?? 0);
}
```

- `db_query()` takes the SQL, whether a failure stops the page, and the values for its `?` placeholders.
- `db_prefix()` gives a table's name with the server's prefix; pass it a literal table name.
- A name that is not a literal (a column chosen from a list) goes through `db_identifier()`, which allows only letters,
  digits and underscores, and must be on the reviewed list in `docs/RESCAN-TASK3-20261023.md`.
- `tests/unit/SqlSweepTest.php::testEveryValueInterpolatedIntoSqlIsOnTheReviewedList` scans every shipped PHP file,
  modules included, and fails on any value written into SQL text that is not on that list.
- Create your tables in `_install()` with `synctable()` or after checking `db_table_exists()`, as the bundled `dag` and
  `drinks` modules do.

## 8. Versions and upgrades

A module's version is the `version` in its `_getmoduleinfo()`. Raise it with every release of the module that changes
anything it installs: hooks, tables, settings, preferences or the data it keeps. For a bundled module also raise it in
`resurrection_bundled_module_versions()`; `tests/unit/UpgradeTest.php::testBundledModuleVersionsMatchTheModuleFiles`
fails when the two disagree.

When an installed bundled module's stored version is older than the code's, `resurrection_upgrade_state()` reports the
database as behind and the game stops for an upgrade. `scripts/upgrade.php` or `setup.php` then calls
`resurrection_upgrade_module()`, which wipes the module's hooks with `module_wipehooks()`, runs its `_install()` again
and stores the new version. So:

- `_install()` must be safe to run again on a database that already has the module: register every hook, create a table
  only if it is missing, change a table with `synctable()`, and never reset the players' data.
- A version never goes down. A stored version newer than the code's makes the database "ahead": the game and the
  upgrade command refuse to run on it, and `setup.php` does not answer.
- Every release must upgrade cleanly from the one before (`docs/VERSIONING.md`).

## 9. Admission

`docs/MODULE-POLICY.md` decides which modules ship. Before a module ships in a release:

- **The review.** Its provenance, licensing, author attribution, dependencies, runtime compatibility, database
  behavior, security, gameplay behavior and fit with the release goals are reviewed, with every support file and asset.
  A module is admitted as a specific version, "APPROVED FOR RESURRECTION". Archive-only modules and the Cities module
  are not admitted.
- **Certification.** It is certified as the 24 were (`docs/BUNDLED-MODULE-CERTIFICATION.md`): install, activate,
  deactivate, reactivate, reinstall, its settings and preferences, its hooks, events and gameplay, and all modules
  active together, with tests in `tests/unit/ModuleCertificationTest.php`.
- **Registration.** It is added to `resurrection_bundled_modules()` and `resurrection_bundled_module_versions()`, and
  the rules in this guide hold: the entry guard, no inline script or style, POST and CSRF for every change, no secret
  in a kept page and no unreviewed SQL. The tests named in each section must pass.
- **The record.** Every behavior it adds or changes is recorded in `docs/CHANGES-FROM-1.1.2.md`.

## What 1.1.2 had that is gone

These went with the surfaces and skins Resurrection removed. A ported module that relies on one gets nothing.

- **Hooks that no longer fire:** `rawsql-execphp`, `rawsql-execsql`, `rawsql-modphp`, `rawsql-modsql` (with the SQL and
  PHP console), `validatesettings` and `mod-dyn-settings` (with 1.1.2's raw module settings save, replaced by the typed
  settings editor on 2026-09-13).
- **With the skins (Phase 5 task 15):** the `template` cookie and skin selection, the `defaultskin` and `homeskinselect`
  settings, the `theme` setting type, and every 1.1.2 skin in `templates/`. A module that printed markup for a skin
  prints the classes in section 3 instead.
