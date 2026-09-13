<?php
// Supported local installation command. Never expose this entrypoint over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found.\n"); }
chdir(dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $config = $argv[1] ?? 'dbconnect.php';
    require $config;
    require 'lib/dbwrapper_pdo.php';
    require 'lib/constants.php';
    require 'lib/datacache.php';
    require 'lib/settings.php';
    require 'lib/modules.php';
    require 'lib/output.php';
    require 'lib/sanitize.php';
    require 'lib/installer/fresh_install.php';
    if (!db_connect($DB_HOST, $DB_USER, $DB_PASS) || !db_select_db($DB_NAME)) {
        throw new RuntimeException('Database connection failed.');
    }
    unset($DB_HOST, $DB_USER, $DB_PASS);
    $login = getenv('RESURRECTION_ADMIN_LOGIN');
    $password = getenv('RESURRECTION_ADMIN_PASSWORD');
    $email = getenv('RESURRECTION_ADMIN_EMAIL') ?: '';
    if ($login === false || $password === false) { throw new RuntimeException('Administrator credentials must be provided through the environment.'); }
    $result = resurrection_fresh_install($login, $password, $email);
    unset($password);
    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    // No exception trace, SQL, submitted values, or configuration in output/logs.
    fwrite(STDERR, "Installation refused or failed (" . ($GLOBALS['fresh_install_phase'] ?? 'preflight') . "). Existing data is never automatically cleared. Check the target and installation documentation.\n");
    exit(1);
}
