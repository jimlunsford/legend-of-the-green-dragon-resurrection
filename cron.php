<?php
// Global maintenance is a CLI operation. Player New Day routes are unchanged.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit("Not found.\n");
}
chdir(__DIR__);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ob_start();
try {
    // CLI has no web request metadata. These are local bootstrap defaults only.
    $_SERVER += ['SCRIPT_NAME' => 'cron.php', 'REQUEST_URI' => 'cron.php', 'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '80', 'REMOTE_ADDR' => '', 'REQUEST_METHOD' => ''];
    define('ALLOW_ANONYMOUS', true);
    require 'common.php';
    require_once 'lib/installer/fresh_install.php';
    if (resurrection_install_state() !== 'installed') { throw new RuntimeException('Installation incomplete.'); }
    $rows = db_query('SELECT DATABASE() AS db');
    $database = db_fetch_assoc($rows)['db'];
    $lock = 'resurrection-maintenance-' . substr(hash('sha256', $database), 0, 32);
    $rows = db_query('SELECT GET_LOCK(?,0) AS acquired', true, [$lock]);
    if (db_fetch_assoc($rows)['acquired'] !== '1') { throw new RuntimeException('Maintenance already running.'); }
    try {
        clearsettings();
        $today = gmdate('Y-m-d', gametime());
        if (getsetting('maintenance_day', '') === $today) { $status = 'already-complete'; }
        else {
            require 'lib/newday/newday_runonce.php';
            savesetting('newdaySemaphore', gmdate('Y-m-d H:i:s'));
            savesetting('maintenance_day', $today);
            $status = 'complete';
        }
    } finally { db_query('SELECT RELEASE_LOCK(?)', false, [$lock]); }
    while (ob_get_level()) { ob_end_clean(); }
    echo json_encode(['maintenance' => $status, 'game_day' => $today], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    while (ob_get_level()) { ob_end_clean(); }
    // Fixed source location identifies code failures without disclosing SQL or secrets.
    fwrite(STDERR, 'Maintenance refused or failed at ' . basename($error->getFile()) . ':' . $error->getLine() . ' ' . ($GLOBALS['dbinfo']['error_context'] ?? '') . ".\n");
    exit(1);
}
