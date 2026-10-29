<?php
// The fresh install's unique login index (resurrection_fresh_schema()); 1.1.2's was not unique.
return [
    'description' => 'Make the accounts login index unique',
    'apply' => static function (): void {
        $index = db_query('SHOW INDEX FROM ' . db_identifier(db_prefix('accounts')) . " WHERE Key_name='login'");
        if (!$index || (string)$index[0]['Non_unique'] !== '0') {
            db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . ($index ? ' DROP INDEX `login`,' : '') . ' ADD UNIQUE KEY `login` (`login`)');
        }
    },
];
