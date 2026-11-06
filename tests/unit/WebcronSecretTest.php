<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Web maintenance's secret and gate (Phase 6 task 5a; lib/webcron.php). The secret is 32 random bytes, base64url; only
 * its SHA-256 hash is stored; it travels as a header, a POST field or the query string, in that order; turning web
 * maintenance on again (a new address) stops the old secret at once, and turning it off stops every secret. The gate
 * answers refused, wait, done or run from one read of the settings.
 */
final class WebcronSecretTest extends TestCase
{
    private const array SETTINGS = ['webcron_enabled', 'webcron_secret_hash', 'webcron_secret_made', 'webcron_last_attempt', 'maintenance_day'];

    public static function setUpBeforeClass(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'datetime', 'modules', 'output', 'sanitize', 'translator', 'webcron'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
    }

    public function testANewSecretIs32RandomBytesInBase64url(): void
    {
        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $secret = resurrection_webcron_new_secret();
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $secret);
            $bytes = base64_decode(strtr($secret, '-_', '+/') . '=', true);
            self::assertIsString($bytes);
            self::assertSame(32, strlen($bytes));
            $seen[$secret] = true;
        }
        self::assertCount(50, $seen);
    }

    public function testEverySettingNameFitsTheSettingsTable(): void
    {
        // settings.setting is varchar(20) (lib/all_tables.php): a longer name is refused by a strict database.
        foreach ([...RESURRECTION_WEBCRON_SETTINGS, 'maintenance_last_run', 'maintenance_day'] as $name) {
            self::assertLessThanOrEqual(20, strlen($name), $name);
        }
        self::assertStringContainsString("'name'=>'setting', 'type'=>'varchar(20)'", (string)file_get_contents('lib/all_tables.php'));
    }

    public function testOnlyTheHashIsComparedWithHashEquals(): void
    {
        $secret = resurrection_webcron_new_secret();
        $hash = resurrection_webcron_hash($secret);
        self::assertSame(hash('sha256', $secret), $hash);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hash);
        self::assertTrue(resurrection_webcron_matches($secret, $hash));
        self::assertFalse(resurrection_webcron_matches($secret . 'x', $hash));
        self::assertFalse(resurrection_webcron_matches($hash, $hash), 'The stored hash is not itself a secret.');
        // An empty or damaged stored value never matches anything, the empty secret included.
        foreach (['', substr($hash, 1), $hash . '0', strtoupper($hash)] as $stored) {
            self::assertFalse(resurrection_webcron_matches($secret, $stored));
        }
        self::assertFalse(resurrection_webcron_matches('', ''));
        self::assertStringContainsString('hash_equals($storedHash, resurrection_webcron_hash($presented))', (string)file_get_contents('lib/webcron.php'));
    }

    public function testTheSecretComesFromTheHeaderThenPostThenQuery(): void
    {
        $header = ['HTTP_X_RESURRECTION_MAINTENANCE_KEY' => 'from-header'];
        self::assertSame('from-header', resurrection_webcron_presented($header, ['key' => 'from-post'], ['key' => 'from-query']));
        self::assertSame('from-post', resurrection_webcron_presented([], ['key' => 'from-post'], ['key' => 'from-query']));
        self::assertSame('from-query', resurrection_webcron_presented([], [], ['key' => 'from-query']));
        self::assertNull(resurrection_webcron_presented([], [], []));
        self::assertSame('X-Resurrection-Maintenance-Key', RESURRECTION_WEBCRON_HEADER);
        // A value that cannot be a secret ends the search: an empty one, an array, or one over 128 characters.
        self::assertNull(resurrection_webcron_presented(['HTTP_X_RESURRECTION_MAINTENANCE_KEY' => ''], ['key' => 'from-post'], []));
        self::assertNull(resurrection_webcron_presented([], ['key' => ['from-post']], ['key' => 'from-query']));
        self::assertNull(resurrection_webcron_presented([], [], ['key' => str_repeat('a', 129)]));
        self::assertSame(str_repeat('a', 128), resurrection_webcron_presented([], [], ['key' => str_repeat('a', 128)]));
        self::assertNull(resurrection_webcron_presented([], ['other' => 'x'], ['secret' => 'x']));
    }

    public function testTheGameDayIsWorkedOutAsTheGameWorksItOut(): void
    {
        $settings = ['game_epoch' => '2026-11-01 00:00:00 +0000', 'gameoffsetseconds' => '0', 'daysperday' => '4'];
        $now = strtotime('2026-11-06 13:00:00 UTC');
        // (now - epoch) * 4: 5 days 13 hours = 22 game days and 4 hours past the epoch.
        self::assertSame(gmdate('Y-m-d', (int)(($now - strtotime('2026-11-01 00:00:00 UTC')) * 4)), resurrection_webcron_game_day($settings, $now));
        self::assertSame('1970-01-23', resurrection_webcron_game_day(['game_epoch' => '1970-01-01 00:00:00 +0000', 'daysperday' => '1'], strtotime('1970-01-23 05:00:00 UTC')));
        self::assertSame('1970-01-01', resurrection_webcron_game_day(['game_epoch' => '1970-01-01 00:00:00 +0000', 'gameoffsetseconds' => '18000', 'daysperday' => '1'], strtotime('1970-01-01 05:00:00 UTC')));
        foreach ([[], ['game_epoch' => 'not a time', 'daysperday' => '4'], ['game_epoch' => '2026-11-01', 'daysperday' => 'four'], ['game_epoch' => '2026-11-01', 'daysperday' => '4', 'gameoffsetseconds' => 'x']] as $missing) {
            self::assertNull(resurrection_webcron_game_day($missing, $now));
        }
    }

    private function connect(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
    }

    /** @return array<string, string> */
    private static function stored(): array
    {
        return array_column(db_query('SELECT setting,value FROM settings WHERE setting IN (?,?,?,?,?)', true, self::SETTINGS), 'value', 'setting');
    }

    public function testRotationAndTurningOffStopTheOldSecretAndOnlyTheHashIsStored(): void
    {
        $this->connect();
        $saved = self::stored();
        try {
            db_query('DELETE FROM settings WHERE setting IN (?,?,?,?,?)', true, self::SETTINGS);
            $GLOBALS['settings'] = null;
            // Off by default: no setting at all refuses any secret.
            self::assertSame('refused', resurrection_webcron_gate(resurrection_webcron_new_secret(), 1_800_000_000));
            self::assertFalse(resurrection_webcron_status()['enabled']);

            $first = resurrection_webcron_enable();
            $stored = self::stored();
            self::assertSame('1', $stored['webcron_enabled']);
            self::assertSame(hash('sha256', $first), $stored['webcron_secret_hash']);
            self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $stored['webcron_secret_made']);
            // The secret itself is nowhere in the settings table.
            foreach (db_query('SELECT setting,value FROM settings') as $row) { self::assertStringNotContainsString($first, (string)$row['value'], (string)$row['setting']); }
            self::assertTrue(resurrection_webcron_status()['enabled']);
            self::assertSame('refused', resurrection_webcron_gate('wrong', 1_800_000_000));
            self::assertSame('run', resurrection_webcron_gate($first, 1_800_000_000));
            self::assertSame(1_800_000_000, resurrection_webcron_status()['last_attempt']);

            // A new address: the old secret stops at once, the new one works.
            $second = resurrection_webcron_enable();
            self::assertNotSame($first, $second);
            self::assertSame(hash('sha256', $second), self::stored()['webcron_secret_hash']);
            self::assertSame('refused', resurrection_webcron_gate($first, 1_800_001_000));
            self::assertSame('run', resurrection_webcron_gate($second, 1_800_001_000));

            // Off: the hash is cleared, so no secret works, and turning it on again makes a new one.
            resurrection_webcron_disable();
            $stored = self::stored();
            self::assertSame(['0', '', ''], [$stored['webcron_enabled'], $stored['webcron_secret_hash'], $stored['webcron_secret_made']]);
            self::assertSame('refused', resurrection_webcron_gate($second, 1_800_002_000));
            self::assertFalse(resurrection_webcron_status()['enabled']);
            // Enabled with no hash (a damaged row) still refuses everything, the empty secret included.
            db_query("UPDATE settings SET value='1' WHERE setting='webcron_enabled'");
            self::assertSame('refused', resurrection_webcron_gate($second, 1_800_002_000));
            self::assertSame('refused', resurrection_webcron_gate('', 1_800_002_000));
            self::assertFalse(resurrection_webcron_status()['enabled']);
        } finally { self::restore($saved); }
    }

    public function testTheGateWaitsAMinuteAndSkipsADayAlreadyDone(): void
    {
        $this->connect();
        $saved = self::stored();
        try {
            db_query('DELETE FROM settings WHERE setting IN (?,?,?,?,?)', true, self::SETTINGS);
            $GLOBALS['settings'] = null;
            $secret = resurrection_webcron_enable();
            $now = 1_800_000_000;
            self::assertSame('run', resurrection_webcron_gate($secret, $now));
            // Inside the gap: any number of requests wait, and none moves the last attempt.
            foreach ([0, 1, 30, 59] as $later) { self::assertSame('wait', resurrection_webcron_gate($secret, $now + $later)); }
            self::assertSame($now, resurrection_webcron_status()['last_attempt']);
            self::assertSame('run', resurrection_webcron_gate($secret, $now + 60));
            self::assertSame($now + 60, resurrection_webcron_status()['last_attempt']);
            // The day marker for the game day it is: past the gap, but nothing to load.
            $settings = array_column(db_query("SELECT setting,value FROM settings WHERE setting IN ('game_epoch','gameoffsetseconds','daysperday')"), 'value', 'setting');
            $today = resurrection_webcron_game_day($settings, $now + 120);
            self::assertNotNull($today, 'The installed game has its clock settings.');
            db_query('INSERT INTO settings (setting,value) VALUES (?,?)', true, ['maintenance_day', $today]);
            self::assertSame('done', resurrection_webcron_gate($secret, $now + 120));
            self::assertSame('wait', resurrection_webcron_gate($secret, $now + 121));
            // The gate's day is the game's own (convertgametime() in lib/datetime.php).
            $GLOBALS['settings'] = null;
            self::assertSame(gmdate('Y-m-d', convertgametime($now + 120)), $today);
            db_query("UPDATE settings SET value='1999-01-01' WHERE setting='maintenance_day'");
            self::assertSame('run', resurrection_webcron_gate($secret, $now + 180));
        } finally { self::restore($saved); }
    }

    /** @param array<string, string> $saved */
    private static function restore(array $saved): void
    {
        db_query('DELETE FROM settings WHERE setting IN (?,?,?,?,?)', true, self::SETTINGS);
        foreach ($saved as $setting => $value) { db_query('INSERT INTO settings (setting,value) VALUES (?,?)', true, [$setting, $value]); }
        $GLOBALS['settings'] = null;
        self::assertSame($saved, self::stored());
    }
}
