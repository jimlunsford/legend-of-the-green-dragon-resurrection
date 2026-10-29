<?php
// Supported local installation command. Never expose this entrypoint over HTTP.
//
//   php scripts/install.php [--modules=recommended|none] [CONFIG]
//   php scripts/install.php --write-config [--allow-served-path] [--modules=recommended|none] /absolute/CONFIG
//
// CONFIG is an existing configuration file (default dbconnect.php). With --write-config the command first writes it
// from DB_HOST, DB_USER, DB_PASS, DB_NAME and DB_PREFIX in the environment (owner-only, never over an existing file,
// outside the served directory unless --allow-served-path) and installs with the file it wrote. --modules chooses
// what the install activates: 1.1.2's recommended modules (the default) or none.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found.\n"); }
chdir(dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    require 'src/Configuration/ConfigFile.php';
    $modules = 'recommended';
    $writeConfig = false;
    $allowServed = false;
    $paths = [];
    foreach (array_slice($argv ?? [], 1) as $argument) {
        if ($argument === '--write-config') { $writeConfig = true; }
        elseif ($argument === '--allow-served-path') { $allowServed = true; }
        elseif (str_starts_with($argument, '--modules=')) { $modules = substr($argument, strlen('--modules=')); }
        elseif (str_starts_with($argument, '--')) { throw new \Resurrection\Installation\InstallRefused('Unknown option. See docs/MODERN-INSTALLATION.md.'); }
        else { $paths[] = $argument; }
    }
    if (count($paths) > 1) { throw new \Resurrection\Installation\InstallRefused('Give one configuration file.'); }
    if ($allowServed && !$writeConfig) { throw new \Resurrection\Installation\InstallRefused('--allow-served-path applies only to --write-config.'); }
    if ($writeConfig && !$paths) { throw new \Resurrection\Installation\InstallRefused('--write-config needs the configuration file\'s absolute path.'); }
    $config = $paths[0] ?? 'dbconnect.php';
    require 'lib/dbwrapper_pdo.php';
    require 'lib/constants.php';
    require 'lib/datacache.php';
    require 'lib/settings.php';
    require 'lib/modules.php';
    require 'lib/output.php';
    require 'lib/sanitize.php';
    require 'lib/translator.php';
    // lib/all_tables.php (1.1.2), which the installer loads, prints a newline after its closing tag; the output starts clean.
    ob_start();
    require 'lib/installer/fresh_install.php';
    ob_end_clean();
    // The module API's translation namespace falls back to the page address (common.php sets it); a command has none.
    $REQUEST_URI = '';
    $activate = resurrection_install_module_plan($modules);
    $login = getenv('RESURRECTION_ADMIN_LOGIN');
    $password = getenv('RESURRECTION_ADMIN_PASSWORD');
    $email = getenv('RESURRECTION_ADMIN_EMAIL') ?: '';
    if ($login === false || $password === false) { throw new RuntimeException('Administrator credentials must be provided through the environment.'); }
    if ($writeConfig) {
        // Everything that can refuse runs before the file is written: the target, the values, the administrator, and
        // the database itself (reachable and empty; the install checks it again under its lock).
        $target = \Resurrection\Configuration\ConfigFile::target($config, dirname(__DIR__), $allowServed);
        $values = \Resurrection\Configuration\ConfigFile::fromEnvironment(getenv());
        resurrection_validate_account($login, $email);
        \Resurrection\Security\Passwords::hash($password);
        $DB_PREFIX = $values['DB_PREFIX'];
        if (!db_connect($values['DB_HOST'], $values['DB_USER'], $values['DB_PASS']) || !db_select_db($values['DB_NAME'])) {
            throw new RuntimeException('Database connection failed.');
        }
        resurrection_refuse_install_state(resurrection_install_state());
        \Resurrection\Configuration\ConfigFile::write($target, $values);
        unset($values);
        $config = $target;
    }
    // The install reads its database settings from the file, whether it existed or was just written.
    require $config;
    if (!isset($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) ||
        !is_string($DB_HOST) || !is_string($DB_USER) || !is_string($DB_PASS) || !is_string($DB_NAME)) {
        throw new RuntimeException('Database configuration is incomplete.');
    }
    if (!db_connect($DB_HOST, $DB_USER, $DB_PASS) || !db_select_db($DB_NAME)) {
        throw new RuntimeException('Database connection failed.');
    }
    unset($DB_HOST, $DB_USER, $DB_PASS);
    $result = resurrection_fresh_install($login, $password, $email, $activate);
    unset($password);
    echo json_encode($result + ['modules' => $modules, 'config' => $writeConfig ? 'written' : 'existing'], JSON_THROW_ON_ERROR) . "\n";
} catch (\Resurrection\Installation\InstallRefused $refusal) {
    // Fixed text, never a submitted or configured value.
    fwrite(STDERR, 'Installation refused. ' . $refusal->getMessage() . "\n");
    exit(1);
} catch (Throwable $error) {
    // No exception trace, SQL, submitted values, or configuration in output/logs.
    fwrite(STDERR, "Installation refused or failed (" . ($GLOBALS['fresh_install_phase'] ?? 'preflight') . "). Existing data is never automatically cleared. Check the target and installation documentation.\n");
    exit(1);
}
