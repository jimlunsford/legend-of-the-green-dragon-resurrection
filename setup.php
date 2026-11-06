<?php
define('RESURRECTION_ENTRY', true);
// The web setup page (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3): installs a new game,
// or upgrades one whose database is behind the code, for a host without a shell. It is a front end over the same
// services as scripts/install.php and scripts/upgrade.php (lib/setup/run.php). It answers only while the game is not
// installed (no configuration file, or one whose database is empty) or its database is behind; in every other state it
// is the empty 404. Every change needs the token file resurrection-setup.php, created by the host through the panel's
// file manager, and the value it holds typed on the page; an upgrade also needs an SU_MEGAUSER login and password.
//
// It does not load common.php, which shows the not-installed and "Upgrade Needed" pages exactly where this page must
// answer. It always sends the enforced Content Security Policy, Cache-Control: no-store and X-Robots-Tag: noindex.
require_once __DIR__ . '/src/Http/SecurityHeaders.php';
\Resurrection\Http\SecurityHeaders::send();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
// The host's own limit, read before the game sets its own (lib/local_config.php): the upgrade's budget is half of it.
$resurrection_setup_max_execution = (string)ini_get('max_execution_time');
// The game's libraries, at the top level as common.php loads them (some keep globals of their own). Nothing they print
// reaches the page (lib/all_tables.php prints a newline).
ob_start();
require_once 'lib/local_config.php';
require_once 'lib/dbwrapper.php';
require_once 'lib/holiday_texts.php';
require_once 'lib/sanitize.php';
require_once 'lib/constants.php';
require_once 'lib/datacache.php';
require_once 'lib/modules.php';
require_once 'lib/http.php';
require_once 'lib/e_rand.php';
require_once 'lib/buffs.php';
require_once 'lib/pageparts.php';
require_once 'lib/output.php';
require_once 'lib/tempstat.php';
require_once 'lib/su_access.php';
require_once 'lib/datetime.php';
require_once 'lib/translator.php';
require_once 'lib/template.php';
require_once 'lib/settings.php';
require_once 'lib/redirect.php';
require_once 'lib/censor.php';
require_once 'lib/saveuser.php';
require_once 'lib/arrayutil.php';
require_once 'lib/addnews.php';
require_once 'lib/sql.php';
require_once 'lib/mounts.php';
require_once 'lib/debuglog.php';
require_once 'lib/forcednavigation.php';
require_once 'lib/php_generic_environment.php';
require_once 'lib/web_security.php';
require_once 'lib/all_tables.php';
require_once 'lib/installer/install_run.php';
require_once 'lib/upgrade.php';
require_once 'lib/setup/run.php';
// The footer's credits, as common.php sets them (lib/setup/credits.php).
require 'lib/setup/credits.php';
ob_end_clean();
$pagestarttime = getmicrotime();
php_generic_environment();
resurrection_setup_request((string)getcwd(), $resurrection_setup_max_execution);
