<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The web setup page's screens (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, sections 3.6 to 3.8).
 *
 * Every screen, refusals included, is drawn in the operator frame (resurrection_operator_frame(), Phase 6 task 5a):
 * the template that prepare_template() checks, with the copyright line, the license block and the Resurrection
 * attribution line, and the safe frame when the template fails. No MoTD, petition, mail or online list. The page is
 * drawn after the database work is done and the connection is closed, as the not-installed page is drawn, so drawing
 * it runs no module and reads no game data. No inline script, handler or style: the behaviors are data-rz-* attributes
 * read by assets/ui/behaviors.js. A password, the database password and a token in use are never shown again; the
 * token the page suggests and the web maintenance address go through resurrection_secret_output().
 */

/** Escape for HTML. */
function resurrection_setup_h(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The frame's stand-in for the game's session: what page_header() and page_footer() read for a visitor who is not
 * signed in, as common.php fills it. It is never stored: the setup page's own session holds only the CSRF token and the
 * setup grant.
 *
 * @return array<string, mixed>
 */
function resurrection_setup_frame_session(): array {
    $session = ['loggedin' => false, 'lasthit' => 0, 'message' => '', 'debug' => '', 'allowednavs' => [], 'bufflist' => [],
        'templatename' => '', 'templatemtime' => 0, 'user' => []];
    foreach (get_all_tables()['accounts'] as $name => $column) {
        if (str_contains($column['type'], 'key')) continue;
        $session['user'][$name] = $column['default'] ?? '';
    }
    $session['user']['superuser'] = 0;
    $session['user']['loggedin'] = false;
    $session['user']['prefs'] = [];
    $session['user']['acctid'] = 0;
    return $session;
}

/**
 * Send a screen and stop. The database connection is closed first, and the frame is drawn as the not-installed page is
 * (DB_NODB): no module, no setting and no game data is read.
 *
 * @param list<string> $secrets the secrets $html shows once (resurrection_secret_output())
 */
function resurrection_setup_page(int $status, string $title, string $html, #[\SensitiveParameter] array $secrets = []): never {
    while (ob_get_level() > 0) ob_end_clean();
    resurrection_setup_disconnect();
    if (!defined('DB_NODB')) define('DB_NODB', true);
    $GLOBALS['settings'] = [];
    $GLOBALS['DB_USEDATACACHE'] = 0;
    $GLOBALS['session'] = resurrection_setup_frame_session();
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    resurrection_operator_frame();
    page_header($title);
    if ($secrets !== []) {
        resurrection_secret_output($html, ...$secrets);
    } else {
        rawoutput($html);
    }
    page_footer(false);
    exit;
}

/** The empty 404 of every state the page does not answer in (the S2 guard's): no body and no cookie. */
function resurrection_setup_not_found(): never {
    while (ob_get_level() > 0) ob_end_clean();
    // A session opened only to look for a grant (the maintenance choice) is removed when it holds nothing, so a 404
    // leaves no session file behind; one that holds something is left as it was.
    if (session_status() === PHP_SESSION_ACTIVE) {
        if ($_SESSION === []) session_destroy();
        else session_abort();
    }
    header_remove('Set-Cookie');
    http_response_code(404);
    exit;
}

/** A notice at the top of a screen: info, a warning (the warning style) or a refusal. */
function resurrection_setup_notice(string $text, string $kind = 'refusal'): string {
    $role = $kind === 'refusal' || $kind === 'warning' ? 'alert' : 'status';
    return '<div class="rz-setup-notice rz-setup-' . resurrection_setup_h($kind) . '" role="' . $role . '"><p>' . resurrection_setup_h($text) . '</p></div>';
}

/** The opening of a setup form: always a POST to plain setup.php (no query string), with the CSRF token and the step. */
function resurrection_setup_form(string $step, string $class = 'rz-setup-form', string $busy = ''): string {
    return '<form class="' . resurrection_setup_h($class) . '" action="setup.php" method="POST" autocomplete="off"'
        . ($busy !== '' ? ' data-rz-busy="rz-setup-busy"' : '') . '>' . resurrection_csrf_field()
        . '<input type="hidden" name="step" value="' . resurrection_setup_h($step) . '">';
}

/** The submit button, and the note behaviors.js shows while the request runs (hidden until then). */
function resurrection_setup_submit(string $label, string $busy = ''): string {
    return '<div class="rz-setup-actions"><button type="submit" class="button rz-button-primary">' . resurrection_setup_h($label) . '</button>'
        . ($busy !== '' ? '<p class="rz-setup-busy" id="rz-setup-busy" role="status" hidden>' . resurrection_setup_h($busy) . '</p>' : '')
        . '</div></form>';
}

/**
 * One labelled field. A password field is always empty; any other is filled with the value typed before, escaped.
 */
function resurrection_setup_field(string $name, string $label, string $type = 'text', string $value = '', string $help = '', bool $required = true): string {
    $id = 'rz-setup-' . str_replace('_', '-', $name);
    $attributes = ' autocomplete="off" spellcheck="false"' . ($required ? ' required' : '');
    $shown = $type === 'password' ? '' : $value;
    return '<div class="rz-setup-field"><label for="' . $id . '">' . resurrection_setup_h($label) . '</label>'
        . '<input type="' . $type . '" id="' . $id . '" name="' . resurrection_setup_h($name) . '" value="' . resurrection_setup_h($shown) . '"' . $attributes . '>'
        . ($help !== '' ? '<p class="rz-setup-help">' . resurrection_setup_h($help) . '</p>' : '') . '</div>';
}

/** A title and an introduction line for a screen. */
function resurrection_setup_intro(string $mode): string {
    return $mode === 'upgrade'
        ? '<p class="rz-setup-lead">This game\'s database is behind its code. This page applies the upgrade, as <code>php scripts/upgrade.php --backup-confirmed</code> does, for a host without a shell.</p>'
        : '<p class="rz-setup-lead">This game is not installed yet. This page installs it into an empty database, as <code>php scripts/install.php</code> does, for a host without a shell.</p>';
}

/**
 * The preflight checks (section 3.6): PHP's version, the required extensions, memory_limit, max_execution_time,
 * whether set_time_limit() may be called, whether the configuration's folder is writable (install without a
 * configuration file), and whether the request came over HTTPS.
 *
 * @return list<array{label: string, value: string, state: string}> state is ok, info, warning or fail (a hard failure)
 */
function resurrection_setup_preflight(string $mode, string $config, string $maxExecution, string $https): array {
    $checks = [];
    $checks[] = ['label' => 'PHP version', 'value' => PHP_VERSION . ' (8.4 or 8.5 needed)', 'state' => version_compare(PHP_VERSION, '8.4.0', '>=') ? 'ok' : 'fail'];
    $missing = array_values(array_filter(['ctype', 'filter', 'pdo', 'pdo_mysql', 'mbstring', 'json', 'session'], static fn (string $name): bool => !extension_loaded($name)));
    $checks[] = ['label' => 'PHP extensions', 'value' => $missing === [] ? 'ctype, filter, pdo, pdo_mysql, mbstring, json and session are loaded' : 'missing: ' . implode(', ', $missing), 'state' => $missing === [] ? 'ok' : 'fail'];
    $memory = resurrection_setup_bytes((string)ini_get('memory_limit'));
    $checks[] = ['label' => 'memory_limit', 'value' => (string)ini_get('memory_limit') . ' (32M or more needed)', 'state' => $memory < 0 || $memory >= 32 * 1024 * 1024 ? 'ok' : 'fail'];
    $seconds = (int)$maxExecution;
    $checks[] = ['label' => 'max_execution_time', 'value' => $seconds > 0 ? $seconds . ' seconds' : 'no limit', 'state' => $seconds > 0 && $seconds < 30 ? 'warning' : 'info'];
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    $timeLimit = function_exists('set_time_limit') && !in_array('set_time_limit', $disabled, true);
    $checks[] = ['label' => 'set_time_limit()', 'value' => $timeLimit ? 'allowed' : 'not allowed: the host\'s limit holds', 'state' => $timeLimit ? 'ok' : 'warning'];
    if ($mode === 'install') {
        $writable = is_dir(dirname($config)) && is_writable(dirname($config));
        $checks[] = ['label' => 'Configuration folder', 'value' => dirname($config) . ($writable ? ' (PHP can write the configuration file here)' : ' (PHP cannot write here: give PHP\'s user write access for the install, or create the configuration file by hand, then reload)'), 'state' => $writable ? 'ok' : 'warning'];
    } else {
        $checks[] = ['label' => 'Configuration file', 'value' => $config . ' (present)', 'state' => 'ok'];
    }
    $checks[] = ['label' => 'HTTPS', 'value' => $https, 'state' => 'ok'];
    return $checks;
}

/** An ini size ("64M", "1G", "-1") in bytes; -1 for no limit. */
function resurrection_setup_bytes(string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '-1') return -1;
    $number = (int)$value;
    return match (strtolower(substr($value, -1))) { 'g' => $number * 1024 ** 3, 'm' => $number * 1024 ** 2, 'k' => $number * 1024, default => $number };
}

/**
 * The checks as a list.
 *
 * @param list<array{label: string, value: string, state: string}> $checks
 */
function resurrection_setup_checks_html(array $checks): string {
    $words = ['ok' => 'OK', 'info' => 'Note', 'warning' => 'Check', 'fail' => 'Fails'];
    $html = '<dl class="rz-setup-checks">';
    foreach ($checks as $check) {
        $html .= '<dt>' . resurrection_setup_h($check['label']) . '</dt><dd class="rz-setup-check-' . $check['state'] . '"><span class="rz-setup-check-word">'
            . $words[$check['state']] . '</span> ' . resurrection_setup_h($check['value']) . '</dd>';
    }
    return $html . '</dl>';
}

/**
 * Screen 1, both modes: the preflight, then a button to the token step when nothing fails.
 *
 * @param list<array{label: string, value: string, state: string}> $checks
 */
function resurrection_setup_screen_preflight(string $mode, array $checks, string $notice = ''): never {
    $failed = array_filter($checks, static fn (array $check): bool => $check['state'] === 'fail');
    $html = $notice . resurrection_setup_intro($mode) . '<h2>Checks</h2>' . resurrection_setup_checks_html($checks);
    if ($failed) {
        $html .= resurrection_setup_notice('A requirement above fails. Fix it in your hosting panel, then reload this page.');
    } else {
        $html .= resurrection_setup_form('token') . resurrection_setup_submit('Continue');
    }
    resurrection_setup_page(200, $mode === 'upgrade' ? 'Upgrade the game' : 'Install the game', $html);
}

/**
 * The token file's instructions, with a fresh suggested value (shown once, never stored) and the complete file.
 *
 * @return array{0: string, 1: list<string>} the HTML, and the secrets it shows
 */
function resurrection_setup_token_help(string $directory, bool $upstream = false): array {
    $value = resurrection_setup_token_new();
    $file = RESURRECTION_SETUP_GUARD . "\n" . $value . ($upstream ? "\n" . RESURRECTION_SETUP_UPSTREAM : '');
    $html = '<h2>Prove you control the site\'s files</h2>'
        . '<ol class="rz-setup-steps"><li>In your hosting panel\'s file manager, open this folder: <code class="rz-setup-path">' . resurrection_setup_h($directory) . '</code></li>'
        . '<li>Create a file named <code>' . RESURRECTION_SETUP_TOKEN_FILE . '</code> there, holding exactly these lines' . ($upstream ? '' : ' (two lines)') . ':</li></ol>'
        . '<div class="rz-setup-copy"><textarea id="rz-setup-file" class="rz-setup-file" rows="' . ($upstream ? 3 : 2) . '" readonly autocomplete="off" spellcheck="false">' . resurrection_setup_h($file) . '</textarea>'
        . '<button type="button" class="button" data-rz-copy="rz-setup-file" data-rz-copied="Copied" hidden>Copy the file</button></div>'
        . '<p class="rz-setup-help">The first line makes the file show nothing if anyone requests it. The second line is a new random value; you may use another of the same form. Set the file to 0600 if the file manager allows. It works for 60 minutes from when you save it, once. Create it in the panel\'s file manager: some FTP clients keep your computer\'s file time, which can make it look too old.</p>'
        . '<p class="rz-setup-help">If TLS ends at a proxy in front of this site and PHP sees plain HTTP, add a third line, exactly <code>' . RESURRECTION_SETUP_UPSTREAM . '</code>.</p>';
    return [$html, [$value]];
}

/** Screen 2, install: the token step. */
function resurrection_setup_screen_token_install(string $directory, string $notice = ''): never {
    [$help, $secrets] = resurrection_setup_token_help($directory);
    $html = $notice . resurrection_setup_intro('install') . $help
        . resurrection_setup_form('verify')
        . resurrection_setup_field('token', 'Then paste the value from line 2 here', 'text', '', 'The value starts with rzsetup-.')
        . resurrection_setup_submit('Continue');
    resurrection_setup_page($notice === '' ? 200 : 403, 'Install the game', $html, $secrets);
}

/**
 * Screen 2, upgrade: the token, an administrator's login and password, and the backup box, after the pending list
 * (the dry run's text, word for word as the command prints it) and the backup notice (section 3.7).
 *
 * @param list<string> $pending the dry run's lines
 */
function resurrection_setup_screen_token_upgrade(string $directory, array $pending, string $login, string $notice = '', int $status = 200): never {
    [$help, $secrets] = resurrection_setup_token_help($directory);
    $html = $notice . resurrection_setup_intro('upgrade')
        . '<h2>What the upgrade changes</h2><pre class="rz-setup-report">' . resurrection_setup_h(implode("\n", $pending)) . '</pre>'
        . '<h2>Take a backup first</h2>'
        . '<p>MySQL and MariaDB commit a table change the moment it runs, so an upgrade that stops partway cannot be rolled back. A backup taken just before it is the way back.</p>'
        . '<p>Without a shell: use your hosting panel\'s backup tool, or phpMyAdmin\'s Export (every table, SQL format), and keep a copy of the configuration file too.</p>'
        . $help
        . resurrection_setup_form('upgrade', 'rz-setup-form', 'busy')
        . resurrection_setup_field('token', 'Then paste the value from line 2 here', 'text', '', 'The value starts with rzsetup-.')
        . resurrection_setup_field('admin_login', 'Administrator login', 'text', $login, 'An account with the top administrator flag (the first administrator has it).')
        . resurrection_setup_field('admin_password', 'Administrator password', 'password')
        . '<div class="rz-setup-field rz-setup-box"><input type="checkbox" id="rz-setup-backup" name="backup" value="1" required><label for="rz-setup-backup">I have taken a backup of the database just now.</label></div>'
        . resurrection_setup_submit('Upgrade', 'Upgrading. Do not close the page.');
    resurrection_setup_page($status, 'Upgrade the game', $html, $secrets);
}

/**
 * Screen 3, install: the form. The database values only when there is no configuration file yet.
 *
 * @param array<string, string> $typed the non-secret values typed before
 */
function resurrection_setup_screen_form(string $mode, string $config, #[\SensitiveParameter] array $typed, string $notice = '', int $status = 200): never {
    $value = static fn (string $name, string $default = ''): string => $typed[$name] ?? $default;
    $html = $notice . '<p class="rz-setup-lead">The token is accepted. Fill in the form and press Install. Nothing is changed before that.</p>';
    if ($mode === 'install') {
        $html .= '<h2>Database</h2><p>The database you created in your hosting panel. It must be empty. The page writes the configuration file to <code class="rz-setup-path">' . resurrection_setup_h($config) . '</code> (owner only, 0600).</p>';
    } else {
        $html .= '<h2>Database</h2><p>The configuration file <code class="rz-setup-path">' . resurrection_setup_h($config) . '</code> is there and its database is empty, so the install uses it.</p>';
    }
    $html .= resurrection_setup_form('install', 'rz-setup-form', 'busy');
    if ($mode === 'install') {
        $html .= resurrection_setup_field('db_host', 'Database server', 'text', $value('db_host', 'localhost'), 'Often localhost; your panel says which.')
            . resurrection_setup_field('db_name', 'Database name', 'text', $value('db_name'))
            . resurrection_setup_field('db_user', 'Database user', 'text', $value('db_user'))
            . resurrection_setup_field('db_pass', 'Database password', 'password')
            . resurrection_setup_field('db_prefix', 'Table prefix (optional)', 'text', $value('db_prefix'), 'Letters, digits and underscores; leave it empty unless the database is shared.', false);
    }
    $modules = $value('modules', 'recommended');
    $html .= '<h2>First administrator</h2>'
        . resurrection_setup_field('admin_login', 'Login', 'text', $value('admin_login'), '3 to 25 letters and digits, starting with a letter.')
        . resurrection_setup_field('admin_password', 'Password', 'password', '', '12 to 72 bytes.')
        . resurrection_setup_field('admin_password2', 'Password again', 'password')
        . resurrection_setup_field('admin_email', 'Email (optional)', 'email', $value('admin_email'), '', false)
        . '<h2>Modules</h2><fieldset class="rz-setup-choice"><legend>What the install turns on</legend>'
        . '<div><input type="radio" id="rz-setup-modules-recommended" name="modules" value="recommended"' . ($modules !== 'none' ? ' checked' : '') . '><label for="rz-setup-modules-recommended">The recommended modules (as the command\'s default)</label></div>'
        . '<div><input type="radio" id="rz-setup-modules-none" name="modules" value="none"' . ($modules === 'none' ? ' checked' : '') . '><label for="rz-setup-modules-none">None</label></div></fieldset>'
        . resurrection_setup_submit('Install', 'Installing. This takes up to a minute. Do not close the page.');
    resurrection_setup_page($status, 'Install the game', $html);
}

/**
 * Screen 4, install: what was done, the warnings, and the maintenance choice (decision 3: web maintenance stays off
 * unless the host picks it here).
 *
 * @param array{tables: int, modules_installed: int, modules_active: int, administrator: int, state: string} $result
 */
function resurrection_setup_screen_installed(array $result, string $login, bool $configWritten, string $config, bool $deleted, string $tokenPath): never {
    $html = '';
    if (!$deleted) {
        $html .= resurrection_setup_notice('PHP could not delete ' . $tokenPath . '. Delete it now: it cannot be used again, but it should not stay on the server.', 'warning');
    }
    $html .= '<p class="rz-setup-lead">The game is installed.</p><ul class="rz-setup-done">'
        . ($configWritten ? '<li>The configuration file was written to <code class="rz-setup-path">' . resurrection_setup_h($config) . '</code> (0600).</li>' : '<li>The existing configuration file was used.</li>')
        . '<li>' . (int)$result['tables'] . ' tables were created; ' . (int)$result['modules_installed'] . ' modules installed, ' . (int)$result['modules_active'] . ' active.</li>'
        . '<li>The first administrator is <strong>' . resurrection_setup_h($login) . '</strong>.</li>'
        . '<li>The token file was ' . ($deleted ? 'deleted' : 'not deleted (see above)') . ', and it cannot be used again.</li></ul>'
        . '<h2>Daily maintenance</h2>'
        . '<p>The game runs its daily maintenance (cleanups, expirations, the modules\' daily events) once per game day. Choose how:</p>'
        . resurrection_setup_form('maintenance', 'rz-setup-form rz-setup-maintenance')
        . '<fieldset class="rz-setup-choice"><legend>How maintenance runs</legend>'
        . '<div><input type="radio" id="rz-setup-maintenance-cron" name="maintenance" value="cron" checked><label for="rz-setup-maintenance-cron">My hosting panel\'s scheduler runs a PHP command (recommended): <code>php cron.php</code> in the game\'s folder, every 15 minutes. Web maintenance stays off.</label></div>'
        . '<div><input type="radio" id="rz-setup-maintenance-web" name="maintenance" value="web"><label for="rz-setup-maintenance-web">My scheduler can only fetch an address: turn on web maintenance and show its secret address once.</label></div></fieldset>'
        . resurrection_setup_submit('Save the choice')
        . '<p>Then <a href="index.php">sign in</a> with the administrator account. Game Settings, under Web maintenance, can change this later.</p>';
    resurrection_setup_page(200, 'The game is installed', $html);
}

/** Screen 5, install: the maintenance choice made; for web maintenance, the address shown once. */
function resurrection_setup_screen_maintenance(#[\SensitiveParameter] ?string $secret, #[\SensitiveParameter] ?string $address, string $base): never {
    if ($secret === null || $address === null) {
        $html = '<p class="rz-setup-lead">Web maintenance stays off.</p>'
            . '<p>In your hosting panel\'s scheduler, run <code>php cron.php</code> in the game\'s folder every 15 minutes, with the panel\'s PHP. Game Settings shows when maintenance last ran.</p>'
            . '<p><a href="index.php">Sign in</a> with the administrator account.</p>';
        resurrection_setup_page(200, 'Daily maintenance', $html);
    }
    $html = '<p class="rz-setup-lead">Web maintenance is on.</p>' . resurrection_webcron_once_html($address, $secret, $base)
        . '<p><a href="index.php">Sign in</a> with the administrator account. Game Settings, under Web maintenance, shows when the address was last used and can make a new one.</p>';
    resurrection_setup_page(200, 'Daily maintenance', $html, [$secret, $address]);
}

/**
 * The upgrade's progress: what this request applied, and Continue while something remains; or done.
 *
 * @param list<string> $lines the report's lines
 */
function resurrection_setup_screen_progress(array $lines, int $applied, int $total, bool $done, bool $deleted, string $tokenPath): never {
    if ($done) {
        $html = (!$deleted ? resurrection_setup_notice('PHP could not delete ' . $tokenPath . '. Delete it now: it cannot be used again, but it should not stay on the server.', 'warning') : '')
            . '<p class="rz-setup-lead">The database is current. Applied ' . $applied . ' of ' . $total . '.</p>'
            . '<pre class="rz-setup-report">' . resurrection_setup_h(implode("\n", $lines)) . '</pre>'
            . '<p>The token file was ' . ($deleted ? 'deleted' : 'not deleted (see above)') . ', and it cannot be used again. Check that maintenance still runs.</p>'
            . '<p><a href="index.php">Back to the game</a>.</p>';
        resurrection_setup_page(200, 'The upgrade is complete', $html);
    }
    $html = '<p class="rz-setup-lead">Applied ' . $applied . ' of ' . $total . '. Continue.</p>'
        . '<p>The upgrade runs in parts so that no request runs longer than the host allows. Each part stops only between two steps.</p>'
        . ($lines !== [] ? '<pre class="rz-setup-report">' . resurrection_setup_h(implode("\n", $lines)) . '</pre>' : '')
        . resurrection_setup_form('continue', 'rz-setup-form', 'busy')
        . resurrection_setup_submit('Continue', 'Upgrading. Do not close the page.');
    resurrection_setup_page(200, 'Upgrading', $html);
}

/** A refusal or failure on its own screen, with a way back to the start. */
function resurrection_setup_screen_message(int $status, string $title, string $text, string $more = ''): never {
    resurrection_setup_page($status, $title, resurrection_setup_notice($text) . $more . '<p><a href="setup.php">Start again</a>.</p>');
}

/**
 * Plain HTTP from anywhere but this machine (decision 5, T23). The screen says that the token file's third line
 * exists, and shows the three-line file, so a host whose TLS ends at a proxy is not stranded.
 */
function resurrection_setup_screen_plain_http(string $directory): never {
    [$help, $secrets] = resurrection_setup_token_help($directory, true);
    $html = resurrection_setup_notice('This page needs HTTPS: its forms carry the database password and an administrator\'s password. Open it as https:// instead.')
        . '<p>Turn on HTTPS for the site in your hosting panel (most panels offer a free certificate), then open this page again over https://.</p>'
        . '<p>If TLS already ends at a proxy in front of this site and PHP only sees plain HTTP, the token file can say so with a third line, exactly <code>' . RESURRECTION_SETUP_UPSTREAM . '</code>. The page then accepts the proxy\'s <code>X-Forwarded-Proto: https</code> for that token only, until it is used or 60 minutes old. The forwarded header is never trusted otherwise. Create the file as below, then reload this page.</p>'
        . $help;
    resurrection_setup_page(403, 'HTTPS needed', $html, $secrets);
}
