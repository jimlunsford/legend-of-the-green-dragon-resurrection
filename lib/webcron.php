<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * Web maintenance (Phase 6 task 5a; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 4 and the decisions): a
 * scheduler that can only fetch an address requests webcron.php with a secret, and the game runs the same daily
 * maintenance as cron.php (resurrection_maintenance_run()).
 *
 * - Off by default (webcron_enabled 0). Off, a wrong secret, a missing one, or any problem before the check: the S2
 *   guard's empty 404. A correct secret: 200 and "OK", whatever happened.
 * - The secret is 32 random bytes, base64url (43 characters). Only its SHA-256 hash is stored (webcron_secret_hash),
 *   compared with hash_equals(). It travels as the X-Resurrection-Maintenance-Key header, the POST field key, or the
 *   query string key, in that order of preference.
 * - At most one attempt a minute gets past the secret check (webcron_last_attempt, claimed atomically). It loads the
 *   game only when today's maintenance is not written yet (maintenance_day); the maintenance lock is taken without
 *   waiting, and the maintenance runs once per game day.
 * - Game Settings turns it on (a new address, shown once), off (the hash is cleared), or makes a new address.
 */

/** The request header a scheduler can send the secret in. */
const RESURRECTION_WEBCRON_HEADER = 'X-Resurrection-Maintenance-Key';

/** Seconds between two attempts that reach the game. */
const RESURRECTION_WEBCRON_GAP = 60;

/** The settings web maintenance keeps (none is in the Game Settings form, so none can be set through it). */
const RESURRECTION_WEBCRON_SETTINGS = ['webcron_enabled', 'webcron_secret_hash', 'webcron_secret_made', 'webcron_last_attempt'];

/**
 * The secret a request presents: the header first, then the POST field, then the query string. Null when there is
 * none, or the value cannot be a secret (not a string, empty, or longer than 128 characters).
 *
 * @param array<array-key, mixed> $server $_SERVER
 * @param array<array-key, mixed> $post $_POST
 * @param array<array-key, mixed> $get $_GET
 */
function resurrection_webcron_presented(#[\SensitiveParameter] array $server, #[\SensitiveParameter] array $post, #[\SensitiveParameter] array $get): ?string {
    foreach ([$server['HTTP_X_RESURRECTION_MAINTENANCE_KEY'] ?? null, $post['key'] ?? null, $get['key'] ?? null] as $value) {
        if ($value === null) continue;
        return is_string($value) && $value !== '' && strlen($value) <= 128 ? $value : null;
    }
    return null;
}

/** A new secret: 32 random bytes, base64url without padding (43 characters). */
function resurrection_webcron_new_secret(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** The stored form of a secret: its SHA-256 hash, hex. A slow hash is not needed for 256 random bits. */
function resurrection_webcron_hash(#[\SensitiveParameter] string $secret): string {
    return hash('sha256', $secret);
}

/** Whether a presented secret matches the stored hash; never true for an empty hash. */
function resurrection_webcron_matches(#[\SensitiveParameter] string $presented, string $storedHash): bool {
    return strlen($storedHash) === 64 && hash_equals($storedHash, resurrection_webcron_hash($presented));
}

/**
 * Turn web maintenance on with a new secret, replacing any earlier one, and return the secret. The caller shows it
 * once; only its hash is stored.
 */
function resurrection_webcron_enable(): string {
    $secret = resurrection_webcron_new_secret();
    savesetting('webcron_secret_hash', resurrection_webcron_hash($secret));
    savesetting('webcron_secret_made', gmdate('Y-m-d H:i:s'));
    savesetting('webcron_enabled', '1');
    return $secret;
}

/** Turn web maintenance off. The hash is cleared, so the old address never works again. */
function resurrection_webcron_disable(): void {
    savesetting('webcron_enabled', '0');
    savesetting('webcron_secret_hash', '');
    savesetting('webcron_secret_made', '');
}

/**
 * Web maintenance's settings, read from the table (not the settings cache, which a request of webcron.php does not
 * update).
 *
 * @return array{enabled: bool, made: string, last_attempt: int}
 */
function resurrection_webcron_status(): array {
    $rows = db_query('SELECT setting,value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting IN (?,?,?)', true,
        ['webcron_enabled', 'webcron_secret_hash', 'webcron_secret_made']);
    $settings = array_column($rows, 'value', 'setting');
    $attempt = db_query('SELECT value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', true, ['webcron_last_attempt']);
    return ['enabled' => ($settings['webcron_enabled'] ?? '') === '1' && strlen((string)($settings['webcron_secret_hash'] ?? '')) === 64,
        'made' => (string)($settings['webcron_secret_made'] ?? ''), 'last_attempt' => (int)($attempt[0]['value'] ?? 0)];
}

/**
 * The gate, before anything heavy: one indexed read of the settings, the secret, the gap, then the day marker.
 *
 * - 'refused': web maintenance is off, or the secret does not match. The caller answers the empty 404.
 * - 'wait': the secret matches, but an attempt reached the game less than a minute ago. The caller answers OK.
 * - 'done': the secret matches and this request claimed the attempt, but today's maintenance is already written
 *   (maintenance_day). The caller answers OK without loading the game.
 * - 'run': the secret matches, this request claimed the attempt (an atomic update, so of many requests at once only
 *   one gets it), and today's maintenance is not written yet, or the day cannot be worked out here. The caller loads
 *   the game and runs the maintenance, which checks the day again under its lock, then answers OK.
 *
 * The caller has connected to the database.
 */
function resurrection_webcron_gate(#[\SensitiveParameter] string $presented, ?int $now = null): string {
    $now ??= time();
    $table = db_identifier(db_prefix('settings'));
    $rows = db_query('SELECT setting,value FROM ' . $table . ' WHERE setting IN (?,?,?,?,?,?)', true,
        ['webcron_enabled', 'webcron_secret_hash', 'maintenance_day', 'game_epoch', 'gameoffsetseconds', 'daysperday']);
    $settings = array_column($rows, 'value', 'setting');
    if (($settings['webcron_enabled'] ?? '') !== '1' || !resurrection_webcron_matches($presented, (string)($settings['webcron_secret_hash'] ?? ''))) {
        return 'refused';
    }
    // The time is a 10-digit Unix time, so the stored text compares in time order (an empty value is the oldest).
    db_query('UPDATE ' . $table . ' SET value=? WHERE setting=? AND value <= ?', true,
        [sprintf('%010d', $now), 'webcron_last_attempt', sprintf('%010d', $now - RESURRECTION_WEBCRON_GAP)]);
    $claimed = db_affected_rows() === 1;
    if (!$claimed) {
        db_query('INSERT IGNORE INTO ' . $table . ' (setting,value) VALUES (?,?)', true, ['webcron_last_attempt', sprintf('%010d', $now)]);
        $claimed = db_affected_rows() === 1;
    }
    if (!$claimed) return 'wait';
    $today = resurrection_webcron_game_day($settings, $now);
    return $today !== null && ($settings['maintenance_day'] ?? '') === $today ? 'done' : 'run';
}

/**
 * Today's game day (Y-m-d), worked out as convertgametime() in lib/datetime.php does, from the stored settings only.
 * Null when a setting it needs is missing or not a number: the caller then lets the maintenance decide.
 *
 * @param array<array-key, mixed> $settings game_epoch, gameoffsetseconds (optional, 0) and daysperday
 */
function resurrection_webcron_game_day(array $settings, int $now): ?string {
    $epoch = is_string($settings['game_epoch'] ?? null) ? strtotime($settings['game_epoch']) : false;
    $offset = $settings['gameoffsetseconds'] ?? '0';
    $perDay = $settings['daysperday'] ?? null;
    if ($epoch === false || !is_numeric($offset) || !is_numeric($perDay)) return null;
    $at = $now - (int)$offset;
    return gmdate('Y-m-d', (int)(($at - $epoch) * $perDay));
}

/**
 * Record a failed web run in maintenance_last_run, also when it failed before the maintenance could record it (the
 * game refused to start: an upgrade is pending, or the install is incomplete). Written straight to the table, as
 * resurrection_maintenance_record() writes it; never throws.
 */
function resurrection_webcron_record_failure(): void {
    try {
        // A module's daily hook runs in a transaction (resurrection_invoke_module_hook()); a run that ended inside one
        // leaves it open, and this record must not be rolled back with it.
        $connection = $GLOBALS['dbinfo']['connection'] ?? null;
        if ($connection instanceof \PDO && $connection->inTransaction()) $connection->rollBack();
        $value = json_encode(['time' => gmdate('Y-m-d H:i:s'), 'path' => 'web', 'result' => 'failed'], JSON_THROW_ON_ERROR);
        db_query('INSERT INTO ' . db_identifier(db_prefix('settings')) . ' (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true,
            ['maintenance_last_run', $value, $value]);
        if (function_exists('invalidatedatacache')) invalidatedatacache('game-settings');
    } catch (Throwable) {
    }
}

/**
 * webcron.php's answer, sent once at the end of every request, whatever happened before (a shutdown function, so a
 * page that ends with exit is never the answer): everything printed is dropped, every header set before is replaced,
 * and the request gets either the empty 404 or 200 with "OK".
 */
function resurrection_webcron_answer(): void {
    // A run that did not come back (an exception, or the game ended the request with a page of its own) is a failure.
    if (!empty($GLOBALS['resurrection_webcron_running'])) resurrection_webcron_record_failure();
    while (ob_get_level() > 0) ob_end_clean();
    if (headers_sent()) return;
    header_remove();
    $ok = ($GLOBALS['resurrection_webcron_answer'] ?? '') === 'ok';
    http_response_code($ok ? 200 : 404);
    \Resurrection\Http\SecurityHeaders::send();
    header('Cache-Control: no-store');
    if ($ok) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "OK\n";
    }
}

/** A stored time, shown in UTC; "never" for none. */
function resurrection_webcron_when(string $time): string {
    return $time === '' ? 'never' : $time . ' UTC';
}

/**
 * Game Settings' web maintenance section (configuration.php?op=webcron). $secret is set only on the page that made it
 * (turn on, or a new address): the address is shown there once, with a copy button. Afterwards the section shows only
 * when the address was made, when it was last used, and the last maintenance result.
 */
function resurrection_webcron_render(#[\SensitiveParameter] ?string $secret): void {
    require_once __DIR__ . '/server_url.php';
    require_once __DIR__ . '/maintenance.php';
    $h = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $status = resurrection_webcron_status();
    $last = resurrection_maintenance_last_run();
    rawoutput('<section class="rz-webcron" aria-labelledby="rz-webcron-title"><h2 id="rz-webcron-title">Web maintenance</h2>');
    rawoutput('<p>The game runs its daily maintenance once per game day. The best way is a PHP command in your hosting panel\'s scheduler: <code>php cron.php</code> in the game\'s directory. If your scheduler can only fetch an address, turn on web maintenance. The game makes a secret address, and a request to it runs the same maintenance, at most once per game day, whoever asks and however often.</p>');
    if ($secret !== null && $status['enabled']) {
        $base = resurrection_server_url() ?? resurrection_request_server_url();
        $address = ($base ?? '') . 'webcron.php?key=' . $secret;
        rawoutput('<div class="rz-webcron-once" role="status"><p><strong>Copy this address now. It is shown only once.</strong> The game keeps only a fingerprint of it, so it cannot show it again. If you lose it, make a new address.</p>');
        rawoutput('<label for="rz-webcron-address">Maintenance address</label><div class="rz-webcron-copy"><input type="text" id="rz-webcron-address" class="rz-webcron-value" value="' . $h($address) . '" readonly autocomplete="off" spellcheck="false"><button type="button" class="button" data-rz-copy="rz-webcron-address" data-rz-copied="Copied" hidden>Copy</button></div>');
        rawoutput('<p>A scheduler that can send a request header (cron-job.org, EasyCron) should send the key in the <code>' . $h(RESURRECTION_WEBCRON_HEADER) . '</code> header instead, to <code>' . $h(($base ?? '') . 'webcron.php') . '</code>, so the key stays out of address logs.</p>');
        rawoutput('<label for="rz-webcron-key">Key alone</label><div class="rz-webcron-copy"><input type="text" id="rz-webcron-key" class="rz-webcron-value" value="' . $h($secret) . '" readonly autocomplete="off" spellcheck="false"><button type="button" class="button" data-rz-copy="rz-webcron-key" data-rz-copied="Copied" hidden>Copy</button></div>');
        rawoutput('<p>Ask for it every 5 to 15 minutes. It answers <code>OK</code> whatever happened; the result shows on this page.</p></div>');
    }
    rawoutput('<dl class="rz-webcron-status">');
    rawoutput('<dt>Web maintenance</dt><dd>' . ($status['enabled'] ? 'On' : 'Off') . '</dd>');
    if ($status['enabled']) {
        rawoutput('<dt>Address made</dt><dd>' . $h(resurrection_webcron_when($status['made'])) . '</dd>');
        rawoutput('<dt>Address last used</dt><dd>' . $h($status['last_attempt'] > 0 ? resurrection_webcron_when(gmdate('Y-m-d H:i:s', $status['last_attempt'])) : 'never') . '</dd>');
    }
    $results = ['complete' => 'complete', 'already-complete' => 'already done for this game day', 'locked' => 'skipped, another run or an upgrade held the lock', 'failed' => 'failed (see the PHP error log)'];
    $paths = ['cli' => 'the command (cron.php)', 'web' => 'the web address'];
    rawoutput('<dt>Last maintenance</dt><dd>' . ($last === null ? 'none recorded' : $h(resurrection_webcron_when($last['time']) . ', by ' . $paths[$last['path']] . ': ' . $results[$last['result']])) . '</dd>');
    rawoutput('</dl>');
    $form = static function (string $op, string $label, string $confirm = '') use ($h): void {
        $url = 'configuration.php?op=' . $op;
        addnav('', $url);
        rawoutput('<form class="rz-inline" action="' . $h($url) . '" method="POST"' . ($confirm === '' ? '' : ' data-rz-confirm="' . $h($confirm) . '"') . '>' . resurrection_csrf_field() . '<button type="submit" class="button">' . $h($label) . '</button></form> ');
    };
    rawoutput('<div class="rz-webcron-actions">');
    if ($status['enabled']) {
        $form('webcron_new', 'Make a new address', 'Make a new maintenance address? The old one stops working at once.');
        $form('webcron_off', 'Turn off web maintenance', 'Turn off web maintenance? The address stops working at once.');
    } else {
        $form('webcron_on', 'Turn on web maintenance');
    }
    rawoutput('</div></section>');
}
