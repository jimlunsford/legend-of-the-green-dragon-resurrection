<?php
/**
 * Save the whole test database and put it back exactly (Phase 6 task 5a): for the PHPUnit tests that must change it
 * in ways a narrow undo cannot reverse (a fresh install into the emptied database, a day's maintenance run), and must
 * still leave it as FreshInstallTest left it for the tests after them. The caller has connected to the disposable test
 * database; a whole-database snapshot (tests/support/database_snapshot.php) before and after proves the restore.
 */

/** @return array<string, array{create: string, rows: list<array<string, mixed>>}> every table's CREATE TABLE and rows */
function resurrection_database_save(): array {
    $saved = [];
    foreach (db_query('SHOW TABLES') as $row) {
        $table = (string)array_values($row)[0];
        $saved[$table] = ['create' => (string)db_query('SHOW CREATE TABLE ' . db_identifier($table))[0]['Create Table'],
            'rows' => db_query('SELECT * FROM ' . db_identifier($table))];
    }
    return $saved;
}

/** Drop every table, then recreate each as saved (its AUTO_INCREMENT included) with its rows. */
function resurrection_database_restore(array $saved): void {
    foreach (db_query('SHOW TABLES') as $row) { db_query('DROP TABLE ' . db_identifier((string)array_values($row)[0])); }
    $mode = (string)db_query('SELECT @@SESSION.sql_mode AS m')[0]['m'];
    db_query("SET SESSION sql_mode = ''");
    try {
        foreach ($saved as $table => $copy) {
            db_query($copy['create']);
            foreach ($copy['rows'] as $row) {
                db_query('INSERT INTO ' . db_identifier($table) . ' (' . implode(',', array_map('db_identifier', array_keys($row))) . ') VALUES ('
                    . implode(',', array_fill(0, count($row), '?')) . ')', true, array_values($row));
            }
        }
    } finally {
        db_query('SET SESSION sql_mode = ?', true, [$mode]);
        $GLOBALS['settings'] = null;
    }
}
