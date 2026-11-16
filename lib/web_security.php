<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/../src/Http/Input.php';
require_once __DIR__ . '/../src/Security/Csrf.php';

function resurrection_require_post(): void {
    try {
        \Resurrection\Security\Csrf::requirePost($_SESSION, $_SERVER['REQUEST_METHOD'] ?? '', $_POST['csrf_token'] ?? null);
    } catch (DomainException $error) {
        resurrection_refuse_action(403, 'Invalid form submission.');
    }
}

/**
 * Whether this request is a game page under forced navigation (Phase 6 task 22, R10): a signed-in player's page whose
 * address do_forced_nav() checked against the allowed navs (lib/forcednavigation.php), rendered in the game frame.
 * Not a popup or page outside forced navigation (mail, MoTD, petitions, the translator, account.php, badnav.php,
 * a module that overrides it), not a page before common.php (setup.php, account.php's token exchange), not a visitor
 * signed out, and not newday.php: the new-day questions are replayed with this session's tokens on the next request,
 * so a refusal there is no dead end, and their plain refusals stay as they were.
 */
function resurrection_refusal_framed(): bool {
    global $session, $SCRIPT_NAME;
    return PHP_SAPI !== 'cli' && !empty($GLOBALS['resurrection_forced_nav_page']) && !headers_sent() &&
        function_exists('page_header') && function_exists('page_footer') &&
        !empty($session['loggedin']) && !empty($session['user']['acctid']) && ($SCRIPT_NAME ?? '') !== 'newday.php';
}

/**
 * Refuse a form: the CSRF refusal (403) or a spent, stale or missing action token (409) (Phase 6 task 22, R10). The
 * status and the refusal stay as they were; nothing the form asked for is done. Outside a game page (see
 * resurrection_refusal_framed()) the answer is the plain text it always was.
 *
 * On a game page the answer is the game's frame, with the credits, one fixed message and a way out the player may
 * follow, so a refused action is never a dead end:
 * - 409: the request carried this session's CSRF token, so it came from this session's own page. The page shown is
 *   kept in place of the refused form, its nav (back to the village, or the Shades for the dead) joins the navs the
 *   last page allowed (a repeated press of a game's button leaves that game's page open), and the return page becomes
 *   that nav, so neither badnav.php nor a later sign-in sends the player back to the refused form. Nothing else about
 *   the player is written (saveuser() under RESURRECTION_REFUSAL_PRESENTATION).
 * - 403: the request may not come from this session at all, so it changes nothing, not even the navigation: the
 *   page is shown, never kept or saved, and its link goes to badnav.php, which shows the player's own last page
 *   again with this session's form tokens.
 */
function resurrection_refuse_action(int $status, string $plain): never {
    global $session, $output, $navbysection, $navsection, $baseaccount;
    http_response_code($status);
    header('Cache-Control: no-store');
    if (!resurrection_refusal_framed()) exit($plain);
    require_once __DIR__ . '/villagenav.php';
    if (!defined('RESURRECTION_REFUSAL_PRESENTATION')) define('RESURRECTION_REFUSAL_PRESENTATION', true);
    // Whatever the refused page began to print or offer is dropped: only the refusal and its way out remain.
    $output = '';
    $navbysection = [];
    $navsection = '';
    $session['allowednavs'] = [];
    tlschema('refusal');
    page_header('That action has expired');
    output('`$That action has expired. Nothing was changed.`0');
    tlschema();
    if ($status === 403) {
        // Nothing of this session changes either: the sign-in's one fresh replay of the kept page stays available.
        if (!empty($_SESSION['kept_page_reissue'])) register_shutdown_function(static function (): void { $_SESSION['kept_page_reissue'] = true; });
        tlschema('refusal');
        addnav('Return to the game', 'badnav.php');
        tlschema();
        page_footer(false);
        exit; // page_footer() always exits; this states it for the never return type.
    }
    // The navs the last page allowed stay allowed (forced navigation emptied the session's copy for this request).
    require_once __DIR__ . '/../src/Security/NavigationState.php';
    try { $session['allowednavs'] = \Resurrection\Security\NavigationState::read($baseaccount['allowednavs'] ?? ''); }
    catch (DomainException $error) { $session['allowednavs'] = []; }
    villagenav();
    $session['user']['restorepage'] = !empty($session['user']['alive']) ? 'village.php' : 'shades.php';
    page_footer();
    exit;
}

function resurrection_csrf_field(): string { return \Resurrection\Security\Csrf::field($_SESSION); }

function resurrection_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function resurrection_rotate_session(): void {
    if (!session_regenerate_id(true)) { throw new RuntimeException('Unable to renew session.'); }
    unset($_SESSION['csrf']);
    \Resurrection\Security\Csrf::token($_SESSION);
}

/**
 * The player ID (the lgi cookie, matched by ID bans) for this request: the cookie when it has the generated format,
 * 32 hex characters (1.1.2's md5(microtime()), this branch's random bytes); otherwise the stored ID when it has, else
 * a new one. Either of those is sent back as the cookie.
 * @return array{0: string, 1: bool} the ID, and whether to send the cookie
 */
function resurrection_player_id(mixed $cookie, mixed $stored): array {
    $valid = static fn(mixed $id): bool => is_string($id) && strlen($id) === 32 && ctype_xdigit($id);
    if ($valid($cookie)) { return [$cookie, false]; }
    return [$valid($stored) ? $stored : bin2hex(random_bytes(16)), true];
}

function resurrection_end_session(): void {
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $cookie['path'], 'domain' => $cookie['domain'],
        'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_destroy();
}
