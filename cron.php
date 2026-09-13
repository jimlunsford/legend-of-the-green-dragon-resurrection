<?php
// Global maintenance is a CLI operation. Player New Day routes are unchanged.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit("Not found.\n");
}
chdir(__DIR__);
define('ALLOW_ANONYMOUS', true);
require_once 'common.php';
savesetting('newdaySemaphore', gmdate('Y-m-d H:i:s'));
require 'lib/newday/newday_runonce.php';
