<?php
define('RESURRECTION_ENTRY', true);
// Web maintenance (Phase 6 task 5a; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 4): for a host whose
// scheduler can only fetch an address. It runs what cron.php runs, resurrection_maintenance_run(), under the same lock
// and day marker. Off by default; Game Settings turns it on and shows the address once.
//
// Off, a wrong or missing secret, or any problem before the check: the empty 404 (lib/webcron.php). A correct secret:
// 200 and "OK", whatever happened. No session, no cookie, and nothing the game or a module prints.
require_once __DIR__ . '/src/Http/SecurityHeaders.php';
\Resurrection\Http\SecurityHeaders::send();
// The game's directory is the working directory, as for every page (cron.php, a command, changes to it itself).
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
// A scheduler that gives up waiting (cron-job.org after 30 seconds) does not cut a run short.
ignore_user_abort(true);
require_once __DIR__ . '/src/Configuration/ConfigLocation.php';
require_once __DIR__ . '/lib/webcron.php';
$resurrection_webcron_answer = 'not-found';
$resurrection_webcron_running = false;
register_shutdown_function('resurrection_webcron_answer');
ob_start();
try {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST', 'HEAD'], true)) exit;
    $resurrection_webcron_key = resurrection_webcron_presented($_SERVER, $_POST, $_GET);
    if ($resurrection_webcron_key === null) exit;
    // The configuration file, as common.php finds it (LOGD_DBCONNECT first), loaded here at the top level as common.php
    // loads it, so that common.php below finds the same values. Any problem with it is the 404.
    $resurrection_webcron_located = \Resurrection\Configuration\ConfigLocation::fromEnvironment();
    if ($resurrection_webcron_located !== null && !\Resurrection\Configuration\ConfigLocation::usable($resurrection_webcron_located)) exit;
    $resurrection_webcron_config = $resurrection_webcron_located ?? \Resurrection\Configuration\ConfigLocation::DEFAULT_FILE;
    if (!is_file($resurrection_webcron_config) || !is_readable($resurrection_webcron_config)) exit;
    require_once 'lib/dbwrapper.php';
    require $resurrection_webcron_config;
    if (!isset($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) || !is_string($DB_HOST) || !is_string($DB_USER) || !is_string($DB_PASS) || !is_string($DB_NAME)) exit;
    if (!db_connect($DB_HOST, $DB_USER, $DB_PASS) || !db_select_db($DB_NAME)) exit;
    // One indexed read of the settings, the secret, the gap, then the day marker: nothing heavy before this.
    $resurrection_webcron_gate = resurrection_webcron_gate($resurrection_webcron_key);
    unset($resurrection_webcron_key);
    if ($resurrection_webcron_gate === 'refused') exit;
    $resurrection_webcron_answer = 'ok';
    if ($resurrection_webcron_gate !== 'run') exit;
    $resurrection_webcron_running = true;
    // The game, as cron.php loads it, without a session, a cookie or forced navigation.
    $_SERVER['SCRIPT_NAME'] = 'webcron.php';
    $_SERVER['REQUEST_URI'] = 'webcron.php';
    define('RESURRECTION_WEBCRON', true);
    define('ALLOW_ANONYMOUS', true);
    define('OVERRIDE_FORCED_NAV', true);
    require 'common.php';
    require_once 'lib/maintenance.php';
    resurrection_maintenance_run('web');
    $resurrection_webcron_running = false;
} catch (Throwable $error) {
    // Recorded in maintenance_last_run as failed by the answer below; the answer stays the same. Logged only for a
    // request that got past the secret and the gap (a flood logs nothing), with a fixed location only.
    if ($resurrection_webcron_running) error_log('Resurrection: web maintenance failed at ' . basename($error->getFile()) . ':' . $error->getLine() . ' ' . ($GLOBALS['dbinfo']['error_context'] ?? ''));
}
exit;
