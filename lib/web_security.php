<?php
require_once __DIR__ . '/../src/Http/Input.php';
require_once __DIR__ . '/../src/Security/Csrf.php';

function resurrection_require_post(): void {
    try {
        \Resurrection\Security\Csrf::requirePost($_SESSION, $_SERVER['REQUEST_METHOD'] ?? '', $_POST['csrf_token'] ?? null);
    } catch (DomainException $error) {
        http_response_code(403);
        header('Cache-Control: no-store');
        exit('Invalid form submission.');
    }
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
