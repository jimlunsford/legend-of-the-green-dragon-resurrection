<?php
/**
 * Save the whole disposable test database to a file and put it back exactly (Phase 6 task 5b): for the web setup page's
 * HTTP tests (tests/tooling/test_web_ops_http.py), which need an empty database and leave the installed one as they
 * found it for the tests after them. Uses resurrection_database_save() and resurrection_database_restore().
 *
 *   php tests/support/database_dump.php save|restore|empty FILE CONFIG
 *
 * CONFIG is a database configuration file for the test database; the file is a PHP array export, read back with
 * require (never unserialize). "empty" drops every table.
 */
define('RESURRECTION_ENTRY', true);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__, 2));
[$_, $action, $file, $config] = $argv + [null, '', '', ''];
require 'lib/dbwrapper_pdo.php';
require __DIR__ . '/database_save.php';
$values = (static function (string $path): array { require $path; return get_defined_vars(); })($config);
if (($values['DB_NAME'] ?? '') !== 'resurrection_test') { fwrite(STDERR, "Refusing a database that is not the test database.\n"); exit(1); }
db_connect($values['DB_HOST'], $values['DB_USER'], $values['DB_PASS']);
db_select_db($values['DB_NAME']);
if ($action === 'save') {
    file_put_contents($file, '<?php return ' . var_export(resurrection_database_save(), true) . ";\n");
} elseif ($action === 'restore') {
    resurrection_database_restore(require $file);
} elseif ($action === 'empty') {
    foreach (db_query('SHOW TABLES') as $row) { db_query('DROP TABLE ' . db_identifier((string)array_values($row)[0])); }
} else {
    fwrite(STDERR, "Unknown action.\n");
    exit(1);
}
