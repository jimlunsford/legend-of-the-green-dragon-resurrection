<?php
/**
 * A whole-database snapshot for the upgrade tests: every table's definition (columns in order, indexes, engine,
 * collation) and every row, in a stable order. tests/unit/UpgradeTest.php compares a database with itself before and
 * after an upgrade; scripts/release-upgrade-check.py compares an upgraded database with a fresh install, and runs this
 * file as a command for that:
 *
 *   php tests/support/database_snapshot.php [--normalized] CONFIG    (prints the snapshot as JSON)
 *
 * --normalized blanks the values two installs can never share: each date and time value (an install records its own
 * time, also as text in the lastdboptimize setting), the administrator's password hash (salted) and the bundled
 * modules' file times. Whether such a value is set is kept. The caller has loaded lib/dbwrapper_pdo.php and connected.
 */

/** @return array{tables: array<string, array<string, mixed>>, rows: array<string, list<string>>} */
function resurrection_database_snapshot(bool $normalized = false): array {
    $tables = [];
    foreach (db_query('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME') as $table) {
        $tables[(string)$table['name']] = ['engine' => $table['engine'], 'collation' => $table['collation'], 'columns' => [], 'indexes' => []];
    }
    foreach (db_query('SELECT TABLE_NAME AS t, COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS dflt, EXTRA AS extra, COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION') as $column) {
        $table = (string)$column['t'];
        unset($column['t']);
        $tables[$table]['columns'][] = $column;
    }
    foreach (db_query('SELECT TABLE_NAME AS t, INDEX_NAME AS name, NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS seq, COLUMN_NAME AS col, SUB_PART AS sub_part, INDEX_TYPE AS type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX') as $index) {
        $table = (string)$index['t'];
        unset($index['t']);
        $tables[$table]['indexes'][] = $index;
    }
    $rows = [];
    foreach ($tables as $name => $table) {
        $times = [];
        foreach ($table['columns'] as $column) {
            if (preg_match('/^(?:date|datetime|timestamp|time)\b/', (string)$column['type'])) { $times[] = (string)$column['name']; }
        }
        $blank = $normalized ? array_merge($times, ['accounts' => ['password'], 'modules' => ['filemoddate']][$name] ?? []) : [];
        $list = [];
        foreach (db_query('SELECT * FROM ' . db_identifier($name)) as $row) {
            foreach ($blank as $column) { $row[$column] = $row[$column] === null ? null : 'set'; }
            if ($normalized && $name === 'settings' && in_array($row['setting'], ['lastdboptimize'], true)) { $row['value'] = 'set'; }
            $list[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        sort($list, SORT_STRING);
        $rows[$name] = $list;
    }
    return ['tables' => $tables, 'rows' => $rows];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $arguments = array_slice($argv, 1);
    $normalized = ($arguments[0] ?? '') === '--normalized';
    if ($normalized) { array_shift($arguments); }
    if (count($arguments) !== 1) { fwrite(STDERR, "Usage: php tests/support/database_snapshot.php [--normalized] CONFIG\n"); exit(2); }
    chdir(dirname(__DIR__, 2));
    require 'lib/dbwrapper_pdo.php';
    require $arguments[0];
    if (!db_connect($DB_HOST, $DB_USER, $DB_PASS) || !db_select_db($DB_NAME)) { fwrite(STDERR, "Database connection failed.\n"); exit(1); }
    echo json_encode(resurrection_database_snapshot($normalized), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
}
