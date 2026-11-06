<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The web setup page, setup.php (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, section 3, and the
 * decisions): a thin front end over the install and upgrade services, resurrection_install_run() and
 * resurrection_upgrade_run(), for a host without a shell. There is no second install or upgrade path here.
 *
 * Flow. Install: preflight, the token step, the form, one request that installs, then the maintenance choice.
 * Upgrade: preflight, one step with the token, an SU_MEGAUSER login and password and the backup box, then the upgrade
 * in parts under a time budget ("Applied N of M. Continue"). The mode is decided again on every request
 * (resurrection_setup_mode()); in every other state the answer is the empty 404, with no cookie.
 *
 * After the token is accepted the session id is rotated and the session holds a setup grant: the mode, the token's
 * SHA-256 and an expiry 30 minutes ahead. Later requests need the grant, the CSRF token and the same token file. On
 * success the token file is deleted and its key recorded (setup_tokens_used: the first 96 bits of its SHA-256), and the
 * grant ends.
 */
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/page.php';

use Resurrection\Upgrade\UpgradeRefused;
use Resurrection\Upgrade\UpgradeStopped;

/** The setup page's own session cookie, apart from the game's. */
const RESURRECTION_SETUP_SESSION = 'rzsetup';

/** How long a setup grant lasts, in seconds. */
const RESURRECTION_SETUP_GRANT = 1800;

/** The upgrade's time budget per request, in seconds; half of max_execution_time when that is lower. */
const RESURRECTION_SETUP_BUDGET = 20.0;

/** Messages from the services that are fixed texts and may be shown as they are. */
const RESURRECTION_SETUP_SHOWN = [
    'Use 3 to 25 letters and digits, starting with a letter.', 'Invalid email address.',
    'Password must contain 12 to 72 bytes and no NUL characters.', 'Another installation is in progress.',
    'Strict SQL mode is required.', 'Database connection failed.',
];

/** What a token file's status tells the host (fixed text; never a value). */
function resurrection_setup_token_problem(string $status, string $directory): string {
    $file = RESURRECTION_SETUP_TOKEN_FILE;
    return match ($status) {
        'missing' => "There is no $file in $directory yet. Create it as described below, then try again.",
        'unreadable' => "PHP cannot read $file. Make it a plain file the site's PHP user can read (0600 for that user), then try again.",
        'oversize', 'guard', 'format' => "$file does not hold the expected lines: line 1 exactly as shown, line 2 the value, and nothing else except the optional third line " . RESURRECTION_SETUP_UPSTREAM . '. Create it again from the box below.',
        'weak' => 'The value in the file has too few different characters. Use the value the page suggests.',
        'old' => "$file is older than 60 minutes. Create a new one with a new value. Some FTP clients keep your computer's file time: create it in the panel's file manager instead.",
        'ahead' => "$file's time is more than 5 minutes in the future. Check the server's clock, or create the file again in the panel's file manager.",
        'used' => 'This token has been used already. Create a new token file with a new value.',
        'mismatch' => 'The value you typed is not the one in the file.',
        default => 'The token was not accepted.',
    };
}

/** Start the setup page's session: strict mode, cookies only, HttpOnly, SameSite=Strict, Secure over HTTPS. */
function resurrection_setup_session(bool $secure): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(RESURRECTION_SETUP_SESSION);
    // setup.php's own Cache-Control: no-store stays; PHP's session cache headers are not added.
    session_cache_limiter('');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

/**
 * The session's setup grant when it is live for this mode: unexpired, and (install and upgrade) bound to the token the
 * file still holds. Null otherwise; a grant for another mode or an expired one is dropped.
 *
 * @param array{status: string, key: ?string, token: ?string, upstream: bool} $token
 * @return array{mode: string, hash: string, expires: int, applied: int, total: int, upstream: bool}|null
 */
function resurrection_setup_grant(string $mode, #[\SensitiveParameter] array $token, int $now): ?array {
    $grant = $_SESSION['rz_setup_grant'] ?? null;
    if (!is_array($grant) || !is_string($grant['mode'] ?? null) || !is_string($grant['hash'] ?? null) || !is_int($grant['expires'] ?? null)) return null;
    if ($grant['expires'] <= $now || $grant['mode'] !== $mode) {
        unset($_SESSION['rz_setup_grant']);
        return null;
    }
    if ($mode !== 'installed' && ($token['status'] !== 'ok' || !hash_equals($grant['hash'], resurrection_setup_token_hash((string)$token['token'])))) return null;
    return ['mode' => $grant['mode'], 'hash' => $grant['hash'], 'expires' => $grant['expires'], 'applied' => (int)($grant['applied'] ?? 0), 'total' => (int)($grant['total'] ?? 0), 'upstream' => !empty($grant['upstream'])];
}

/**
 * Give the session a new grant after the token was accepted, with a new session id. upstream: the token file carries
 * the third line, so the proxy's X-Forwarded-Proto counts for this run's later requests too (T23).
 */
function resurrection_setup_grant_new(string $mode, #[\SensitiveParameter] string $token, int $now, int $total, bool $upstream): void {
    resurrection_rotate_session();
    $_SESSION['rz_setup_grant'] = ['mode' => $mode, 'hash' => resurrection_setup_token_hash($token), 'expires' => $now + RESURRECTION_SETUP_GRANT,
        'applied' => 0, 'total' => $total, 'upstream' => $upstream];
}

/** The upgrade's budget: 20 seconds, or half of max_execution_time (as the host set it) when that is lower. */
function resurrection_setup_budget(string $maxExecution): float {
    $seconds = (int)$maxExecution;
    return $seconds > 0 ? min(RESURRECTION_SETUP_BUDGET, $seconds / 2) : RESURRECTION_SETUP_BUDGET;
}

/** Let a long request finish: the host closing the browser or a proxy giving up does not stop PHP. */
function resurrection_setup_long_request(): void {
    ignore_user_abort(true);
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('set_time_limit') && !in_array('set_time_limit', $disabled, true)) {
        try { set_time_limit(300); } catch (Throwable) { /* the host's limit holds */ }
    }
}

/** A POST field as a string, or ''. */
function resurrection_setup_post(string $name): string {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

/**
 * The request. Everything the page does starts here; it always ends with a screen or the empty 404.
 *
 * @param string $gameDirectory the game's folder (the working directory, as for every page)
 * @param string $maxExecution max_execution_time as the host set it, read before the game's own setting
 */
function resurrection_setup_request(string $gameDirectory, string $maxExecution): never {
    $now = time();
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) resurrection_setup_not_found();
    $state = resurrection_setup_mode($gameDirectory);
    $mode = $state['mode'];
    if ($mode === 'none') {
        // The maintenance choice on the install's last screen is the one request after the install: the game is
        // installed and current now, so it needs the grant that install left in this browser's session.
        if ($method === 'POST' && resurrection_setup_post('step') === 'maintenance' && isset($_COOKIE[RESURRECTION_SETUP_SESSION])) {
            resurrection_setup_maintenance_choice($state, $now);
        }
        resurrection_setup_not_found();
    }
    $directory = resurrection_setup_token_directory($state['located'], $gameDirectory);
    $tokenPath = $directory . '/' . RESURRECTION_SETUP_TOKEN_FILE;
    $token = resurrection_setup_token_read($tokenPath, $now, resurrection_setup_used_tokens());
    $https = resurrection_setup_https($_SERVER, $token['status'] === 'ok' && $token['upstream']);
    if (!$https && !resurrection_setup_local($_SERVER)) resurrection_setup_screen_plain_http($directory);
    $httpsText = $https ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off' ? 'yes' : 'yes, through the proxy line in the token file')
        : 'no, but this request comes from this machine';
    resurrection_setup_session($https);
    $grantMode = $mode === 'upgrade' ? 'upgrade' : 'install';
    $grant = resurrection_setup_grant($grantMode, $token, $now);
    $checks = resurrection_setup_preflight($mode, $state['config'], $maxExecution, $httpsText);
    $failed = array_filter($checks, static fn (array $check): bool => $check['state'] === 'fail') !== [];
    if ($method !== 'POST') {
        if ($grant !== null && $mode === 'upgrade' && $grant['total'] > 0) resurrection_setup_screen_progress([], $grant['applied'], $grant['total'], false, true, $tokenPath);
        if ($grant !== null && $mode !== 'upgrade' && !$failed) resurrection_setup_screen_form($mode, $state['config'], []);
        resurrection_setup_screen_preflight($mode, $checks);
    }
    if (!\Resurrection\Security\Csrf::valid($_SESSION, $_POST['csrf_token'] ?? null)) {
        resurrection_setup_screen_message(403, 'Invalid form submission', 'Invalid form submission: the form was old, or came from another page. Nothing was changed.');
    }
    $step = resurrection_setup_post('step');
    if ($failed) resurrection_setup_screen_preflight($mode, $checks);
    $expected = $mode === 'upgrade' ? ['token', 'upgrade', 'continue'] : ['token', 'verify', 'install'];
    if (!in_array($step, $expected, true)) {
        unset($_SESSION['rz_setup_grant']);
        resurrection_setup_screen_preflight($mode, $checks, resurrection_setup_notice('The game\'s state changed since that page was shown, so the page starts again. Nothing was changed.', 'info'));
    }
    if ($mode === 'upgrade') resurrection_setup_upgrade($step, $state, $token, $grant, $directory, $tokenPath, $maxExecution, $now);
    resurrection_setup_install($step, $state, $token, $grant, $directory, $tokenPath, $gameDirectory, $now);
}

/**
 * Install mode's steps: the token step, its check, and the install.
 *
 * @param array{mode: string, config: string, located: ?string} $state
 * @param array{status: string, key: ?string, token: ?string, upstream: bool} $token
 * @param array{mode: string, hash: string, expires: int, applied: int, total: int, upstream: bool}|null $grant
 */
function resurrection_setup_install(string $step, array $state, #[\SensitiveParameter] array $token, ?array $grant, string $directory,
        string $tokenPath, string $gameDirectory, int $now): never {
    $mode = $state['mode'];
    if ($step === 'token') resurrection_setup_screen_token_install($directory);
    if ($step === 'verify') {
        if (resurrection_setup_blocked($mode, $token['key'], $directory, $now)) {
            resurrection_setup_screen_message(429, 'Too many attempts', 'Too many failed attempts against this token file. Wait 15 minutes, or create a new token file with a new value.');
        }
        $problem = $token['status'] !== 'ok' ? $token['status'] : (resurrection_setup_token_matches((string)$token['token'], $_POST['token'] ?? null) ? null : 'mismatch');
        if ($problem !== null) {
            resurrection_setup_failure($mode, 'token', $token['key'], $directory, $now);
            resurrection_setup_screen_token_install($directory, resurrection_setup_notice(resurrection_setup_token_problem($problem, $directory)));
        }
        resurrection_setup_grant_new('install', (string)$token['token'], $now, 0, $token['upstream']);
        resurrection_setup_screen_form($mode, $state['config'], []);
    }
    // install
    $typed = [];
    foreach (['db_host', 'db_name', 'db_user', 'db_prefix', 'admin_login', 'admin_email', 'modules'] as $name) $typed[$name] = resurrection_setup_post($name);
    if ($grant === null) {
        unset($_SESSION['rz_setup_grant']);
        resurrection_setup_screen_message(403, 'Start again', 'The setup session ended (30 minutes passed, or the token file changed or is gone). Nothing was changed.');
    }
    if (resurrection_setup_post('admin_password') !== resurrection_setup_post('admin_password2')) {
        resurrection_setup_screen_form($mode, $state['config'], $typed, resurrection_setup_notice('The two passwords differ. Nothing was changed.'), 400);
    }
    $writeConfig = $mode === 'install';
    // The configuration's place: the LOGD_DBCONNECT file, or exactly dbconnect.php in the game's folder, the one path the
    // served directory is allowed for (section 3.5).
    $allowServed = $writeConfig && $state['located'] === null && $state['config'] === rtrim($gameDirectory, '/') . '/dbconnect.php';
    $values = $writeConfig ? ['DB_HOST' => $typed['db_host'], 'DB_USER' => $typed['db_user'], 'DB_PASS' => resurrection_setup_post('db_pass'),
        'DB_NAME' => $typed['db_name'], 'DB_PREFIX' => $typed['db_prefix']] : [];
    $password = resurrection_setup_post('admin_password');
    unset($_POST['admin_password'], $_POST['admin_password2'], $_POST['db_pass'], $_POST['token']);
    resurrection_setup_long_request();
    unset($GLOBALS['fresh_install_phase']);
    $level = ob_get_level();
    ob_start();
    try {
        // The install itself is the service the command runs (lib/installer/install_run.php).
        $result = resurrection_install_run($typed['modules'] === 'none' ? 'none' : 'recommended', $state['config'], $writeConfig, $allowServed,
            $typed['admin_login'], $password, $typed['admin_email'], $values, $gameDirectory);
    } catch (\Resurrection\Installation\InstallRefused $refusal) {
        while (ob_get_level() > $level) ob_end_clean();
        resurrection_setup_screen_form($mode, $state['config'], $typed, resurrection_setup_notice('Installation refused. ' . $refusal->getMessage()), 409);
    } catch (Throwable $error) {
        while (ob_get_level() > $level) ob_end_clean();
        $phase = resurrection_setup_install_phase();
        if ($phase === 'preflight' && in_array($error->getMessage(), RESURRECTION_SETUP_SHOWN, true)) {
            resurrection_setup_screen_form($mode, $state['config'], $typed, resurrection_setup_notice('Installation refused. ' . $error->getMessage() . ' Nothing was changed.'), 400);
        }
        unset($_SESSION['rz_setup_grant']);
        error_log('Resurrection: web install failed (' . preg_replace('/[^A-Za-z0-9_.:-]/', '?', $phase) . ') at ' . basename($error->getFile()) . ':' . $error->getLine());
        resurrection_setup_screen_message(500, 'Installation failed', 'Installation refused or failed (' . $phase . '). Existing data is never automatically cleared.',
            $phase === 'preflight' ? '' : '<p>The database now holds part of a game, so this page answers no more. To try again: empty the database in your hosting panel (for example phpMyAdmin: select every table, then Drop), then open setup.php again. A configuration file this page wrote is kept, so the next attempt uses it.</p>');
    } finally {
        unset($password, $values);
    }
    while (ob_get_level() > $level) ob_end_clean();
    // Single use: the hash is recorded in the database just installed, then the file is deleted.
    resurrection_setup_record_used($grant['hash']);
    $deleted = resurrection_setup_token_delete($tokenPath);
    resurrection_setup_token_delete($directory . '/' . RESURRECTION_SETUP_FAILURE_FILE);
    $_SESSION['rz_setup_grant'] = ['mode' => 'installed', 'hash' => $grant['hash'], 'expires' => $now + RESURRECTION_SETUP_GRANT, 'applied' => 0, 'total' => 0, 'upstream' => $grant['upstream']];
    resurrection_setup_screen_installed($result, $typed['admin_login'], $writeConfig, $state['config'], $deleted, $tokenPath);
}

/** The install service's phase when it stopped (as scripts/install.php reports it): preflight until it changes anything. */
function resurrection_setup_install_phase(): string {
    $phase = $GLOBALS['fresh_install_phase'] ?? 'preflight';
    return is_string($phase) ? $phase : 'preflight';
}

/**
 * The maintenance choice on the install's last screen, the one request after the install. It needs the CSRF token and
 * the grant that install left (30 minutes, bound to the token whose hash the install recorded), and is used once.
 * Without them the answer is the empty 404, as for any request in this state.
 *
 * @param array{mode: string, config: string, located: ?string} $state
 */
function resurrection_setup_maintenance_choice(array $state, int $now): void {
    if ($state['config'] === '' || !is_file($state['config']) || !isset($_COOKIE[RESURRECTION_SETUP_SESSION])) return;
    $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
    resurrection_setup_session(resurrection_setup_https($_SERVER, false) || $forwarded);
    $grant = resurrection_setup_grant('installed', ['status' => 'missing', 'key' => null, 'token' => null, 'upstream' => false], $now);
    if ($grant === null || !\Resurrection\Security\Csrf::valid($_SESSION, $_POST['csrf_token'] ?? null)) return;
    // HTTPS as for the install: the proxy's header counts only when that install's token file carried the third line.
    if (!resurrection_setup_https($_SERVER, $grant['upstream']) && !resurrection_setup_local($_SERVER)) return;
    try {
        resurrection_database_open(resurrection_database_config($state['config']));
        if (resurrection_install_state() !== 'installed' || !in_array(resurrection_setup_used_key($grant['hash']), resurrection_setup_used_tokens(), true)) return;
        unset($_SESSION['rz_setup_grant']);
        if (resurrection_setup_post('maintenance') !== 'web') resurrection_setup_screen_maintenance(null, null, '');
        require_once __DIR__ . '/../webcron.php';
        require_once __DIR__ . '/../server_url.php';
        $secret = resurrection_webcron_enable();
        $base = resurrection_server_url() ?? resurrection_request_server_url() ?? '';
    } catch (Throwable) {
        return;
    }
    resurrection_setup_screen_maintenance($secret, $base . 'webcron.php?key=' . $secret, $base);
}

/**
 * Upgrade mode's steps: the token, login and backup step, and Continue.
 *
 * @param array{mode: string, config: string, located: ?string} $state
 * @param array{status: string, key: ?string, token: ?string, upstream: bool} $token
 * @param array{mode: string, hash: string, expires: int, applied: int, total: int, upstream: bool}|null $grant
 */
function resurrection_setup_upgrade(string $step, array $state, #[\SensitiveParameter] array $token, ?array $grant, string $directory,
        string $tokenPath, string $maxExecution, int $now): never {
    if ($step === 'continue') {
        if ($grant === null) {
            unset($_SESSION['rz_setup_grant']);
            resurrection_setup_screen_message(403, 'Start again', 'The setup session ended (30 minutes passed, or the token file changed or is gone). Nothing was changed. Open setup.php again; recorded steps are skipped.');
        }
        resurrection_setup_upgrade_part($state['config'], $grant, $tokenPath, $directory, $maxExecution);
    }
    $pending = [];
    try {
        resurrection_upgrade_run($state['config'], false, static function (string $line) use (&$pending): void { $pending[] = $line; });
    } catch (UpgradeRefused $refusal) {
        resurrection_setup_screen_message(409, 'Upgrade refused', 'Upgrade refused. ' . $refusal->getMessage());
    } catch (Throwable) {
        resurrection_setup_screen_message(500, 'Upgrade refused', 'Upgrade refused or failed (' . ($GLOBALS['resurrection_upgrade_phase'] ?? 'preflight') . '). Check the configuration and docs/MODERN-INSTALLATION.md, "Upgrading".');
    }
    $login = resurrection_setup_post('admin_login');
    if ($step === 'token') resurrection_setup_screen_token_upgrade($directory, $pending, '');
    // upgrade: the token first, then the backup box, then the administrator, once, before any step runs.
    resurrection_setup_reconnect($state['config']);
    if (resurrection_setup_blocked('upgrade', $token['key'], $directory, $now)) {
        resurrection_setup_screen_message(429, 'Too many attempts', 'Too many failed attempts against this token file. Wait 15 minutes, or create a new token file with a new value.');
    }
    $problem = $token['status'] !== 'ok' ? $token['status'] : (resurrection_setup_token_matches((string)$token['token'], $_POST['token'] ?? null) ? null : 'mismatch');
    if ($problem !== null) {
        resurrection_setup_failure('upgrade', 'token', $token['key'], $directory, $now);
        resurrection_setup_screen_token_upgrade($directory, $pending, $login, resurrection_setup_notice(resurrection_setup_token_problem($problem, $directory)), 403);
    }
    if (resurrection_setup_post('backup') !== '1') {
        resurrection_setup_screen_token_upgrade($directory, $pending, $login, resurrection_setup_notice('Take a fresh backup of the database first, then tick the box. Some upgrade changes cannot be rolled back, so the backup is the way back. Nothing was changed.'), 400);
    }
    $password = $_POST['admin_password'] ?? null;
    unset($_POST['admin_password'], $_POST['token']);
    if (!resurrection_setup_administrator($login, $password)) {
        unset($password);
        resurrection_setup_failure('upgrade', 'credentials', $token['key'], $directory, $now);
        resurrection_setup_screen_token_upgrade($directory, $pending, $login, resurrection_setup_notice('The administrator login or password is wrong, or the account does not hold the top administrator flag. Nothing was changed.'), 403);
    }
    unset($password);
    $total = count(array_filter($pending, static fn (string $line): bool => str_starts_with($line, '- step ') || str_starts_with($line, '- module ')));
    resurrection_setup_grant_new('upgrade', (string)$token['token'], $now, max(1, $total), $token['upstream']);
    $grant = resurrection_setup_grant('upgrade', $token, $now);
    if ($grant === null) resurrection_setup_screen_message(500, 'Start again', 'The setup session could not be kept. Nothing was changed.');
    resurrection_setup_upgrade_part($state['config'], $grant, $tokenPath, $directory, $maxExecution);
}

/** Connect to the configured database again (the dry run's service closed nothing, but a refusal may have). */
function resurrection_setup_reconnect(string $config): void {
    if (isset($GLOBALS['dbinfo']['connection'])) return;
    try {
        resurrection_database_open(resurrection_database_config($config));
    } catch (Throwable) {
        resurrection_setup_not_found();
    }
}

/**
 * The upgrade's login check (decision 1 and T22): an account's login and password, through resurrection_authenticate()
 * as login.php checks them, holding SU_MEGAUSER. It runs once, before any step, on the database as the upgrade finds it,
 * and reads only what 1.1.2's accounts table already had (login, password, locked, emailvalidation, superuser), so it
 * works on the oldest layout an upgrade starts from and never needs anything a pending step adds.
 */
function resurrection_setup_administrator(string $login, #[\SensitiveParameter] mixed $password): bool {
    if ($login === '' || !is_string($password) || $password === '') return false;
    require_once __DIR__ . '/../accounts.php';
    $account = resurrection_authenticate($login, $password);
    return is_array($account) && ((int)$account['superuser'] & SU_MEGAUSER) === SU_MEGAUSER;
}

/**
 * One part of the upgrade, under the budget, through the upgrade service. Done: the token file is deleted, its hash
 * recorded and the grant ends. Otherwise "Applied N of M. Continue".
 *
 * @param array{mode: string, hash: string, expires: int, applied: int, total: int, upstream: bool} $grant
 */
function resurrection_setup_upgrade_part(string $config, array $grant, string $tokenPath, string $directory, string $maxExecution): never {
    resurrection_setup_long_request();
    $lines = [];
    $level = ob_get_level();
    ob_start();
    try {
        $result = resurrection_upgrade_run($config, true, static function (string $line) use (&$lines): void { $lines[] = $line; }, resurrection_setup_budget($maxExecution));
    } catch (UpgradeRefused $refusal) {
        while (ob_get_level() > $level) ob_end_clean();
        resurrection_setup_screen_message(409, 'Upgrade refused', 'Upgrade refused. ' . $refusal->getMessage());
    } catch (UpgradeStopped $stopped) {
        while (ob_get_level() > $level) ob_end_clean();
        unset($_SESSION['rz_setup_grant']);
        resurrection_setup_screen_message(500, 'Upgrade stopped', $stopped->getMessage(), $lines !== [] ? '<pre class="rz-setup-report">' . resurrection_setup_h(implode("\n", $lines)) . '</pre>' : '');
    } catch (Throwable $error) {
        while (ob_get_level() > $level) ob_end_clean();
        unset($_SESSION['rz_setup_grant']);
        error_log('Resurrection: web upgrade failed (' . preg_replace('/[^A-Za-z0-9_.:-]/', '?', (string)($GLOBALS['resurrection_upgrade_phase'] ?? 'preflight')) . ') at ' . basename($error->getFile()) . ':' . $error->getLine());
        resurrection_setup_screen_message(500, 'Upgrade failed', 'Upgrade refused or failed (' . ($GLOBALS['resurrection_upgrade_phase'] ?? 'preflight') . ', at ' . basename($error->getFile()) . ':' . $error->getLine() . '). If the upgrade had started, the report says what was applied. Check the configuration and docs/MODERN-INSTALLATION.md, "Upgrading".');
    }
    while (ob_get_level() > $level) ob_end_clean();
    $applied = min($grant['total'], $grant['applied'] + count($result['steps']) + count($result['modules']));
    if ($result['state'] === 'current') {
        resurrection_setup_reconnect($config);
        resurrection_setup_record_used($grant['hash']);
        $deleted = resurrection_setup_token_delete($tokenPath);
        resurrection_setup_token_delete($directory . '/' . RESURRECTION_SETUP_FAILURE_FILE);
        unset($_SESSION['rz_setup_grant']);
        resurrection_setup_screen_progress($lines, $grant['total'], $grant['total'], true, $deleted, $tokenPath);
    }
    $_SESSION['rz_setup_grant']['applied'] = $applied;
    resurrection_setup_screen_progress($lines, $applied, $grant['total'], false, true, $tokenPath);
}
