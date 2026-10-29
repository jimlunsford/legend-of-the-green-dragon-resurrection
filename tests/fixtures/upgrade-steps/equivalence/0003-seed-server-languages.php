<?php
// The fresh install's serverlanguages setting (resurrection_fresh_install()).
return [
    'description' => 'Seed the server languages setting',
    'apply' => static function (): void {
        db_query('INSERT IGNORE INTO ' . db_identifier(db_prefix('settings')) . ' (setting,value) VALUES (?,?)', true,
            ['serverlanguages', 'en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian']);
    },
];
