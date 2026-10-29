<?php
return [
    'description' => 'Log step 0001',
    'apply' => static function (): void {
        db_query('INSERT INTO ' . db_identifier(db_prefix('settings')) . " (setting,value) VALUES ('upgrade_fixture_log','0001') ON DUPLICATE KEY UPDATE value=CONCAT(value,',0001')");
    },
];
