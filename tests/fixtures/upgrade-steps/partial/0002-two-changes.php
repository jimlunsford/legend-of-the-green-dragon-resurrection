<?php
// Two table changes, each committed the moment it runs; it fails between them while the setting asks it to.
return [
    'description' => 'Add two probe columns',
    'apply' => static function (): void {
        $table = db_identifier(db_prefix('upgrade_fixture_probe'));
        $columns = array_column(db_query('SHOW COLUMNS FROM ' . $table), 'Field');
        if (!in_array('first', $columns, true)) { db_query('ALTER TABLE ' . $table . " ADD COLUMN `first` int NOT NULL DEFAULT '1'"); }
        if (db_query('SELECT value FROM ' . db_identifier(db_prefix('settings')) . " WHERE setting='upgrade_fixture_fail'")) {
            throw new RuntimeException('Synthetic failure between the two changes.');
        }
        if (!in_array('second', $columns, true)) { db_query('ALTER TABLE ' . $table . " ADD COLUMN `second` int NOT NULL DEFAULT '2'"); }
    },
];
