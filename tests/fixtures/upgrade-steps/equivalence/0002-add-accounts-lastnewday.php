<?php
// The fresh install's accounts.lastnewday column (resurrection_fresh_schema()), which 1.1.2 did not have.
return [
    'description' => 'Add the accounts lastnewday column',
    'apply' => static function (): void {
        $columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');
        if (!in_array('lastnewday', $columns, true)) {
            db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . " ADD COLUMN `lastnewday` varchar(10) NOT NULL DEFAULT ''");
        }
    },
];
