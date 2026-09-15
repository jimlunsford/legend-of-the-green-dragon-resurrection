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

function resurrection_end_session(): void {
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $cookie['path'], 'domain' => $cookie['domain'],
        'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_destroy();
}
