<?php
return [
    'description' => 'Insert the probe row',
    'apply' => static function (): void {
        db_query('INSERT IGNORE INTO ' . db_identifier(db_prefix('upgrade_fixture_probe')) . ' (id) VALUES (1)');
    },
];
