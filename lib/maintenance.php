<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/** The scheduler's lock for this database: one maintenance run at a time (cron.php). */
function resurrection_maintenance_lock_name(): string {
    $rows = db_query('SELECT DATABASE() AS db', true, []);
    return 'resurrection-maintenance-' . substr(hash('sha256', (string)($rows[0]['db'] ?? '')), 0, 32);
}

/** Whether this connection holds the maintenance lock, so work that must never overlap can refuse to run without it. */
function resurrection_maintenance_lock_held(): bool {
    $rows = db_query('SELECT IS_USED_LOCK(?) = CONNECTION_ID() AS held', true, [resurrection_maintenance_lock_name()]);
    return (string)($rows[0]['held'] ?? '') === '1';
}

/** Where the last maintenance attempt is recorded: when, which path (cli or web), and its result. */
const RESURRECTION_MAINTENANCE_RESULTS = ['complete', 'already-complete', 'locked', 'failed'];

/** Record a maintenance attempt in maintenance_last_run (JSON: time in UTC, path, result). */
function resurrection_maintenance_record(string $path, string $result): void {
    savesetting('maintenance_last_run', json_encode(['time' => gmdate('Y-m-d H:i:s'), 'path' => $path, 'result' => $result], JSON_THROW_ON_ERROR));
}

/**
 * The last recorded maintenance attempt, or null when there is none or it cannot be read.
 * @return array{time: string, path: string, result: string}|null
 */
function resurrection_maintenance_last_run(): ?array {
    $stored = json_decode((string)getsetting('maintenance_last_run', ''), true);
    if (!is_array($stored) || !is_string($stored['time'] ?? null) || !in_array($stored['path'] ?? null, ['cli', 'web'], true)
        || !in_array($stored['result'] ?? null, RESURRECTION_MAINTENANCE_RESULTS, true)) { return null; }
    return ['time' => $stored['time'], 'path' => $stored['path'], 'result' => $stored['result']];
}

/**
 * The daily maintenance, as one service (Phase 6 task 5a; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3.5).
 * It was the body of cron.php, after the bootstrap: cron.php and webcron.php load common.php, then call it.
 *
 * It refuses unless the install is complete and the database current; takes the maintenance lock without waiting
 * ('locked' when another run holds it, or an upgrade); runs the day's maintenance once per game day (the day marker
 * maintenance_day; 'already-complete' when today's is written); and records the attempt in maintenance_last_run. A
 * failure is recorded as 'failed' and thrown on, with the lock released.
 *
 * @param string $path cli or web: who asked
 * @return array{maintenance: string, game_day: string}
 */
function resurrection_maintenance_run(string $path): array {
    if (!in_array($path, ['cli', 'web'], true)) { throw new InvalidArgumentException('Unknown maintenance path.'); }
    require_once __DIR__ . '/installer/fresh_install.php';
    if (resurrection_install_state() !== 'installed') { throw new RuntimeException('Installation incomplete.'); }
    // common.php already refuses a database that is not current; the service checks again for any other caller.
    if (resurrection_database_state() !== 'current') { throw new RuntimeException('The game cannot run on this database.'); }
    $lock = resurrection_maintenance_lock_name();
    $rows = db_query('SELECT GET_LOCK(?,0) AS acquired', true, [$lock]);
    if ((string)(db_fetch_assoc($rows)['acquired'] ?? '') !== '1') {
        resurrection_maintenance_record($path, 'locked');
        return ['maintenance' => 'locked', 'game_day' => ''];
    }
    try {
        clearsettings();
        $today = gmdate('Y-m-d', gametime());
        if (getsetting('maintenance_day', '') === $today) { $status = 'already-complete'; }
        else {
            if (!defined('RESURRECTION_MAINTENANCE_DAY')) { define('RESURRECTION_MAINTENANCE_DAY', $today); }
            // lib/newday/newday_runonce.php was written for the top level; the one global it reads is the data cache's path.
            global $datacachefilepath;
            require __DIR__ . '/newday/newday_runonce.php';
            savesetting('newdaySemaphore', gmdate('Y-m-d H:i:s'));
            savesetting('maintenance_day', $today);
            $status = 'complete';
        }
        resurrection_maintenance_record($path, $status);
    } catch (Throwable $error) {
        try { resurrection_maintenance_record($path, 'failed'); } catch (Throwable) {}
        throw $error;
    } finally { db_query('SELECT RELEASE_LOCK(?)', false, [$lock]); }
    return ['maintenance' => $status, 'game_day' => $today];
}
