<?php
declare(strict_types=1);
namespace Resurrection\Http;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

/**
 * The security headers every response carries (Phase 6 task 3, S1; docs/PHASE6-TASK3-HARDENING-20261106.md). This is
 * the one place they are set: common.php sends them before anything else and again once the game's settings can be
 * read, and the few entry points that do not load common.php (index.php's redirect, installer.php's 403) call it
 * themselves.
 *
 * X-Frame-Options, X-Content-Type-Options and Referrer-Policy are always sent. The Content Security Policy follows the
 * csp_mode game setting: enforce (the default) sends it as Content-Security-Policy, report-only as
 * Content-Security-Policy-Report-Only (the browser reports what it would block and blocks nothing; for a third-party
 * module that still prints inline script), and off sends none. Every page the game ships passes the policy.
 */
final class SecurityHeaders
{
    public const string POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public const string DEFAULT_MODE = 'enforce';

    /** @var list<string> */
    public const array MODES = ['enforce', 'report-only', 'off'];

    /** @var array<string, string> The headers sent whatever the mode. */
    public const array ALWAYS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
    ];

    /** A stored csp_mode value as one of MODES: anything else (unset, empty, unknown) is the default, enforce. */
    public static function mode(mixed $setting): string
    {
        return is_string($setting) && in_array($setting, self::MODES, true) ? $setting : self::DEFAULT_MODE;
    }

    /**
     * The headers for a mode, in the order they are sent. $sandbox adds the sandbox directive to an enforced policy
     * whatever the mode: a page that shows stored markup as it was sent (the user editor's last hit) is always
     * sandboxed, as before this task.
     *
     * @return array<string, string>
     */
    public static function headers(string $mode, bool $sandbox = false): array
    {
        $mode = self::mode($mode);
        $headers = self::ALWAYS;
        if ($mode === 'enforce') {
            $headers['Content-Security-Policy'] = self::POLICY . ($sandbox ? '; sandbox' : '');
        } else {
            if ($sandbox) $headers['Content-Security-Policy'] = 'sandbox';
            if ($mode === 'report-only') $headers['Content-Security-Policy-Report-Only'] = self::POLICY;
        }
        return $headers;
    }

    /** Sends the headers for a mode, replacing any this request set before (a second call wins). */
    public static function send(string $mode = self::DEFAULT_MODE, bool $sandbox = false): void
    {
        if (headers_sent()) return;
        header_remove('Content-Security-Policy');
        header_remove('Content-Security-Policy-Report-Only');
        foreach (self::headers($mode, $sandbox) as $name => $value) header($name . ': ' . $value);
    }
}
