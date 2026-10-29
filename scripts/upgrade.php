<?php
// Supported upgrade command. Never expose this entrypoint over HTTP.
//
//   php scripts/upgrade.php --dry-run [CONFIG]
//   php scripts/upgrade.php --backup-confirmed [CONFIG]
//
// CONFIG is the install's configuration file (default dbconnect.php). --dry-run lists what is pending and changes
// nothing. Applying needs --backup-confirmed: MySQL and MariaDB commit a table change the moment it runs, so an upgrade
// that stops partway cannot be rolled back, and a backup taken just before it is the way back
// (docs/MODERN-INSTALLATION.md, "Upgrading"). The command holds the install's and maintenance's database locks while
// it runs, applies the pending steps in upgrades/ in order, records each once it succeeded, runs any pending bundled
// module upgrade, and stops at the first failure (lib/upgrade.php).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found.\n"); }
chdir(dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require 'src/Upgrade/UpgradeRefused.php';
require 'src/Upgrade/UpgradeStopped.php';
use Resurrection\Upgrade\UpgradeRefused;
use Resurrection\Upgrade\UpgradeStopped;

$phase = 'preflight';
$locked = false;
$code = 1;
try {
    $dryRun = false;
    $backup = false;
    $paths = [];
    foreach (array_slice($argv ?? [], 1) as $argument) {
        if ($argument === '--dry-run') { $dryRun = true; }
        elseif ($argument === '--backup-confirmed') { $backup = true; }
        elseif (str_starts_with($argument, '--')) { throw new UpgradeRefused('Unknown option. Use --dry-run or --backup-confirmed (docs/MODERN-INSTALLATION.md, "Upgrading"). Nothing was changed.'); }
        else { $paths[] = $argument; }
    }
    if (count($paths) > 1) { throw new UpgradeRefused('Give one configuration file. Nothing was changed.'); }
    if ($dryRun && $backup) { throw new UpgradeRefused('Give --dry-run or --backup-confirmed, not both. Nothing was changed.'); }
    if (!$dryRun && !$backup) {
        throw new UpgradeRefused('Take a fresh backup of the database first, then run again with --backup-confirmed. Some upgrade changes cannot be rolled back: MySQL and MariaDB commit a table change the moment it runs, so if an upgrade stops partway, the backup is the way back. To see what would change without changing anything, use --dry-run. docs/MODERN-INSTALLATION.md, "Upgrading", says how to take and restore a backup. Nothing was changed.');
    }
    $config = $paths[0] ?? 'dbconnect.php';
    // lib/all_tables.php (1.1.2) prints a newline after its closing tag; the report starts clean.
    ob_start();
    foreach (['dbwrapper_pdo', 'constants', 'datacache', 'settings', 'modules', 'output', 'sanitize', 'translator', 'installer/fresh_install', 'upgrade'] as $library) {
        require_once 'lib/' . $library . '.php';
    }
    ob_end_clean();
    // The module API's translation namespace falls back to the page address (common.php sets it); a command has none.
    $REQUEST_URI = '';
    // The same database settings the install used, from the same file.
    require $config;
    if (!isset($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) ||
        !is_string($DB_HOST) || !is_string($DB_USER) || !is_string($DB_PASS) || !is_string($DB_NAME)) {
        throw new RuntimeException('Database configuration is incomplete.');
    }
    $phase = 'connect';
    if (!db_connect($DB_HOST, $DB_USER, $DB_PASS) || !db_select_db($DB_NAME)) {
        throw new RuntimeException('Database connection failed.');
    }
    unset($DB_HOST, $DB_USER, $DB_PASS);
    $phase = 'lock';
    if (!resurrection_upgrade_lock()) {
        throw new UpgradeRefused('Another upgrade, an install or maintenance (cron.php) is running on this database. Run the upgrade again once it has finished. Nothing was changed.');
    }
    $locked = true;
    $phase = $dryRun ? 'dry-run' : 'upgrade';
    resurrection_upgrade(resurrection_upgrade_steps_directory(), !$dryRun, static function (string $line): void { echo $line, "\n"; });
    $code = 0;
} catch (UpgradeRefused $refusal) {
    // Fixed text, never a submitted or configured value.
    fwrite(STDERR, 'Upgrade refused. ' . $refusal->getMessage() . "\n");
} catch (UpgradeStopped $stopped) {
    fwrite(STDERR, $stopped->getMessage() . "\n");
} catch (Throwable $error) {
    // No exception message, trace, SQL, submitted values or configuration in the output.
    fwrite(STDERR, 'Upgrade refused or failed (' . $phase . ', at ' . basename($error->getFile()) . ':' . $error->getLine() . '). If the upgrade had started, the report above says what was applied. Check the configuration and docs/MODERN-INSTALLATION.md, "Upgrading".' . "\n");
} finally {
    if ($locked) { resurrection_upgrade_unlock(); }
}
exit($code);
