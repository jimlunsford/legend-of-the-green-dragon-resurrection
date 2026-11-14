<?php
// The sign-in limits (Phase 6 task 18, F4; lib/signin_throttle.php). The fresh install stores the three settings with
// their defaults; a database installed before them has no rows, which the game reads as the same defaults. This step
// adds each missing row with its default, so an upgraded database holds exactly what a fresh install does. Each row is
// checked first: an existing row, including one an administrator already changed, is left alone, and a rerun changes
// nothing. Its values are written out here, never read from shared code. No other row is changed.
return [
    'description' => 'Add the sign-in limit settings with their defaults: signin_fail_pair, signin_fail_address and signin_fail_account',
    'apply' => static function (): void {
        $defaults = ['signin_fail_pair' => '5', 'signin_fail_address' => '20', 'signin_fail_account' => '50'];
        foreach ($defaults as $setting => $value) {
            if (db_query('SELECT setting FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', true, [$setting]) === []) {
                db_query('INSERT INTO ' . db_identifier(db_prefix('settings')) . ' (setting,value) VALUES (?,?)', true, [$setting, $value]);
            }
        }
    },
];
