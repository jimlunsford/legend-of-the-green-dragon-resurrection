<?php
return [
    'description' => 'Create the probe table',
    'apply' => static function (): void {
        db_query('CREATE TABLE IF NOT EXISTS ' . db_identifier(db_prefix('upgrade_fixture_probe')) . ' (id int NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    },
];
