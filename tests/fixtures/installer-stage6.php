<?php
// Subprocess fixture: execute the actual installer stage with synthetic local data.
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';
use Resurrection\Configuration\LegacyConfig;
$mode = $argv[1];
$dir = sys_get_temp_dir() . '/resurrection-stage6-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
chdir($dir);
$values = [
    'DB_HOST' => 'localhost', 'DB_USER' => 'synthetic',
    'DB_PASS' => "FAKE-only-'\\password", 'DB_NAME' => 'synthetic_db',
    'DB_PREFIX' => 'fixture_', 'DB_USEDATACACHE' => 0,
    'DB_DATACACHEPATH' => "/tmp/fresh'cache\\path",
];
$session = ['dbinfo' => $values];
$messages = [];
function output(string $message): void { $GLOBALS['messages'][] = $message; }
function getsetting(string $name, string $default): string { return '1.0.6'; }
function db_prefix(string $name): string { return 'fixture_' . $name; }
function db_query(string $sql): array {
    return [['setting' => 'usedatacache', 'value' => '1'],
        ['setting' => 'datacachepath', 'value' => "/tmp/upgraded'cache\\path"]];
}
function db_fetch_assoc(array &$rows): array|false { return array_shift($rows) ?? false; }
try {
    if ($mode === 'upgrade') {
        file_put_contents('dbconnect.php', LegacyConfig::render($values));
        require 'dbconnect.php'; // common.php has already loaded these variables in real use.
        $values['DB_USEDATACACHE'] = 1;
        $values['DB_DATACACHEPATH'] = "/tmp/upgraded'cache\\path";
    } elseif ($mode === 'failure') {
        symlink($dir . '/nonexistent-parent/dbconnect.php', 'dbconnect.php');
    }
    require __DIR__ . '/../../lib/installer/installer_stage_6.php';
    $actual = null;
    if ($success) {
        $actual = (static function (): array {
            require 'dbconnect.php';
            return compact('DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_PREFIX',
                'DB_USEDATACACHE', 'DB_DATACACHEPATH');
        })();
    }
    echo json_encode(['success' => $success, 'matches' => $actual === $values,
        'leaked' => str_contains(implode("\n", $messages), $values['DB_PASS'])], JSON_THROW_ON_ERROR);
} finally {
    if (file_exists('dbconnect.php') || is_link('dbconnect.php')) { unlink('dbconnect.php'); }
    chdir(sys_get_temp_dir());
    rmdir($dir);
}
