<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The web setup page's gates (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, sections 3.2, 3.8, 3.9
 * and the decisions): when the page answers, whether the request counts as HTTPS, and the failed-attempt limit.
 */
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/../../src/Configuration/ConfigLocation.php';

use Resurrection\Configuration\ConfigLocation;

/** Failed attempts allowed against one token file within the window. */
const RESURRECTION_SETUP_FAILURE_LIMIT = 10;

/** The failure window, in seconds. */
const RESURRECTION_SETUP_FAILURE_WINDOW = 900;

/** The faillog words of a failed attempt (fixed vocabulary, never a value). */
const RESURRECTION_SETUP_FAILURES = ['token' => 'setup_invalid_token', 'credentials' => 'setup_invalid_credentials'];

/**
 * When the page answers (section 3.2), decided again on every request. The caller has loaded the database wrapper and
 * the installer's state function. On an answering state that has a configuration file, the database stays connected.
 *
 * - install: no configuration file at the path the game reads; the page writes it (database form).
 * - install-existing: the configuration file loads, the database is reachable and empty (state 2).
 * - upgrade: a completed install whose database is behind the code.
 * - none: every other state (installed and current, ahead, out of order, populated or partly installed, a 1.1.2
 *   database, an unreachable database, or a configuration file that cannot be used). The caller answers the empty 404.
 *
 * @return array{mode: string, config: string, located: ?string}
 */
function resurrection_setup_mode(string $gameDirectory): array {
    $located = ConfigLocation::fromEnvironment();
    $none = ['mode' => 'none', 'config' => '', 'located' => $located];
    if ($located !== null && !ConfigLocation::usable($located)) return $none;
    $config = $located ?? rtrim($gameDirectory, '/') . '/' . ConfigLocation::DEFAULT_FILE;
    clearstatcache();
    if (!file_exists($config) && !is_link($config)) {
        // A file under a directory PHP cannot enter is unreadable, not missing (the game shows its S4 page).
        if ($located !== null && ConfigLocation::closedDirectory($config) !== null) return $none;
        return ['mode' => 'install', 'config' => $config, 'located' => $located];
    }
    if (!is_file($config) || !is_readable($config)) return $none;
    $level = ob_get_level();
    ob_start();
    try {
        $database = resurrection_database_config($config);
        resurrection_database_open($database);
        unset($database);
        $state = resurrection_install_state();
        $mode = 'none';
        if ($state === 'empty') {
            $mode = 'install-existing';
        } elseif ($state === 'installed') {
            $catalog = \Resurrection\Upgrade\StepCatalog::ids(resurrection_upgrade_steps_directory());
            if (resurrection_upgrade_state($catalog)['state'] === 'behind') $mode = 'upgrade';
        }
    } catch (Throwable) {
        $mode = 'none';
    } finally {
        while (ob_get_level() > $level) ob_end_clean();
    }
    if ($mode === 'none') resurrection_setup_disconnect();
    return ['mode' => $mode, 'config' => $config, 'located' => $located];
}

/** Drop the database connection (an advisory lock goes with it). */
function resurrection_setup_disconnect(): void {
    unset($GLOBALS['dbinfo']['connection']);
}

/**
 * Whether the request comes from this machine: REMOTE_ADDR only, never the Host header.
 *
 * @param array<string, mixed> $server
 */
function resurrection_setup_local(array $server): bool {
    return in_array($server['REMOTE_ADDR'] ?? null, ['127.0.0.1', '::1'], true);
}

/**
 * Whether the request counts as HTTPS: $_SERVER['HTTPS'] set and not "off". X-Forwarded-Proto: https counts only when
 * the live token file carries the third line (T23), for that token's run; otherwise the header is ignored.
 *
 * @param array<string, mixed> $server
 */
function resurrection_setup_https(array $server, bool $upstreamLine): bool {
    $https = $server['HTTPS'] ?? '';
    if (is_string($https) && $https !== '' && strtolower($https) !== 'off') return true;
    $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
    return $upstreamLine && is_string($forwarded) && strtolower(trim($forwarded)) === 'https';
}

/**
 * Whether attempts against this token file are refused for now (section 3.9): 10 failures in 15 minutes. Upgrade mode
 * counts faillog rows; install mode (no database yet) reads the counter file beside the token file. A file that cannot
 * be read has no key, and nothing can succeed against it anyway.
 */
function resurrection_setup_blocked(string $mode, ?string $key, string $directory, int $now): bool {
    if ($key === null) return false;
    if ($mode === 'upgrade') {
        $rows = db_query('SELECT COUNT(*) AS n FROM ' . db_identifier(db_prefix('faillog')) . ' WHERE post IN (?,?) AND id=? AND date>?', true,
            [RESURRECTION_SETUP_FAILURES['token'], RESURRECTION_SETUP_FAILURES['credentials'], $key, date('Y-m-d H:i:s', $now - RESURRECTION_SETUP_FAILURE_WINDOW)]);
        return (int)($rows[0]['n'] ?? 0) >= RESURRECTION_SETUP_FAILURE_LIMIT;
    }
    $counter = resurrection_setup_counter_read($directory . '/' . RESURRECTION_SETUP_FAILURE_FILE);
    return $counter !== null && $counter['key'] === $key && $now - $counter['start'] < RESURRECTION_SETUP_FAILURE_WINDOW
        && $counter['count'] >= RESURRECTION_SETUP_FAILURE_LIMIT;
}

/**
 * Record a failed attempt: a faillog row with fixed words, the IP and the token file's key (upgrade mode), or one more
 * in the counter file (install mode, when the folder is writable; otherwise nothing is stored).
 *
 * @param string $reason token or credentials
 */
function resurrection_setup_failure(string $mode, string $reason, ?string $key, string $directory, int $now): void {
    if ($mode === 'upgrade') {
        db_query('INSERT INTO ' . db_identifier(db_prefix('faillog')) . ' (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', true,
            [date('Y-m-d H:i:s', $now), RESURRECTION_SETUP_FAILURES[$reason], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 40), 0, $key ?? '']);
        return;
    }
    if ($key === null) return;
    resurrection_setup_counter_add($directory . '/' . RESURRECTION_SETUP_FAILURE_FILE, $key, $now);
}

/**
 * The counter file: line 1 the guard line, line 2 "<key> <window start> <count>". Null when it is missing or damaged.
 *
 * @return array{key: string, start: int, count: int}|null
 */
function resurrection_setup_counter_read(string $path): ?array {
    try {
        clearstatcache(true, $path);
        if (is_link($path) || !is_file($path) || filesize($path) > RESURRECTION_SETUP_TOKEN_CAP) return null;
        $text = file_get_contents($path, false, null, 0, RESURRECTION_SETUP_TOKEN_CAP);
    } catch (Throwable) {
        return null;
    }
    return is_string($text) ? resurrection_setup_counter_parse($text) : null;
}

/** @return array{key: string, start: int, count: int}|null */
function resurrection_setup_counter_parse(string $text): ?array {
    $lines = explode("\n", $text);
    if ($lines[0] !== RESURRECTION_SETUP_GUARD || preg_match('/\A([0-9a-f]{16}) ([0-9]{1,12}) ([0-9]{1,6})\z/', $lines[1] ?? '', $match) !== 1) return null;
    return ['key' => $match[1], 'start' => (int)$match[2], 'count' => (int)$match[3]];
}

/** One more failure in the counter file, under an exclusive lock; a new token file or an expired window starts again. */
function resurrection_setup_counter_add(string $path, string $key, int $now): void {
    try {
        if (is_link($path) || !is_writable(dirname($path)) || (file_exists($path) && !is_writable($path))) return;
        $mask = umask(0077);
        try { $handle = fopen($path, 'c+'); } finally { umask($mask); }
        if ($handle === false) return;
        try {
            if (!flock($handle, LOCK_EX)) return;
            $counter = resurrection_setup_counter_parse((string)stream_get_contents($handle, RESURRECTION_SETUP_TOKEN_CAP));
            if ($counter === null || $counter['key'] !== $key || $now - $counter['start'] >= RESURRECTION_SETUP_FAILURE_WINDOW) {
                $counter = ['key' => $key, 'start' => $now, 'count' => 0];
            }
            $counter['count'] = min($counter['count'] + 1, 999999);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, RESURRECTION_SETUP_GUARD . "\n" . $counter['key'] . ' ' . $counter['start'] . ' ' . $counter['count'] . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    } catch (Throwable) {
        // Not stored: the token's 160 bits carry the protection alone.
    }
}
