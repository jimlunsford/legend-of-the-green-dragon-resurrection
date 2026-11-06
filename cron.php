<?php
define('RESURRECTION_ENTRY', true);
// Global maintenance is a CLI operation. Player New Day routes are unchanged.
//
// The maintenance itself is one service, resurrection_maintenance_run() in lib/maintenance.php (Phase 6 task 5a),
// shared with the web maintenance request (webcron.php). This command loads the game and calls it.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit("Not found.\n");
}
chdir(__DIR__);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
// A page the game renders and ends with exit (the not-installed page, the configuration page, a database error page)
// is never this command's answer (Phase 6 task 5a, F1): until then it printed that HTML and exited 0, so a scheduler
// recorded success. The guard drops the output, writes one fixed line and exits 1.
$resurrection_cron_done = false;
register_shutdown_function(static function (): void {
    if ($GLOBALS['resurrection_cron_done']) return;
    while (ob_get_level()) { ob_end_clean(); }
    fwrite(STDERR, "Maintenance refused: the game could not start (it is not installed, or its configuration file or database cannot be used). Nothing was run.\n");
    exit(1);
});
ob_start();
try {
    // No configuration file: the game is not installed. Refuse before common.php would render its page.
    // (A LOGD_DBCONNECT that is not an absolute path is common.php's to refuse; the guard above answers it.)
    require_once 'src/Configuration/ConfigLocation.php';
    $resurrection_cron_located = \Resurrection\Configuration\ConfigLocation::fromEnvironment();
    $resurrection_cron_config = $resurrection_cron_located ?? \Resurrection\Configuration\ConfigLocation::DEFAULT_FILE;
    if (($resurrection_cron_located === null || \Resurrection\Configuration\ConfigLocation::usable($resurrection_cron_located))
            && !file_exists($resurrection_cron_config) && !is_link($resurrection_cron_config)
            && ($resurrection_cron_located === null || \Resurrection\Configuration\ConfigLocation::closedDirectory($resurrection_cron_located) === null)) {
        $resurrection_cron_done = true;
        while (ob_get_level()) { ob_end_clean(); }
        fwrite(STDERR, "Maintenance refused: there is no configuration file, so the game is not installed. Nothing was run.\n");
        exit(1);
    }
    // CLI has no web request metadata. These are local bootstrap defaults only.
    $_SERVER += ['SCRIPT_NAME' => 'cron.php', 'REQUEST_URI' => 'cron.php', 'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80', 'REMOTE_ADDR' => '', 'REQUEST_METHOD' => ''];
    define('ALLOW_ANONYMOUS', true);
    require 'common.php';
    require_once 'lib/maintenance.php';
    $result = resurrection_maintenance_run('cli');
    if ($result['maintenance'] === 'locked') { throw new RuntimeException('Maintenance already running.'); }
    $resurrection_cron_done = true;
    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    $resurrection_cron_done = true;
    while (ob_get_level()) { ob_end_clean(); }
    // Fixed source location identifies code failures without disclosing SQL or secrets.
    fwrite(STDERR, 'Maintenance refused or failed at ' . basename($error->getFile()) . ':' . $error->getLine() . ' ' . ($GLOBALS['dbinfo']['error_context'] ?? '') . ".\n");
    exit(1);
}
