<?php
declare(strict_types=1);
namespace Resurrection\Security;
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }

require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/ActionToken.php';

/**
 * The copy of a player's last page that 1.1.2 keeps (accounts_output) and badnav.php shows again (Phase 6 task 9).
 *
 * A page's forms carry the session's CSRF token and one-use action tokens. The copy keeps neither: each is replaced by
 * a marker, the CSRF token by one fixed marker and each action token by a marker that names its scope and context
 * (never the token). When the page is shown again, the markers are filled from the session that shows it:
 *
 * - the CSRF token is that session's;
 * - an action token is the one that session holds for the same scope and context, if it holds one;
 * - only when $reissue is true (the first replay after signing in, when the session is new and holds no action), an
 *   action the session does not hold is issued afresh for that scope and context, so the page works as it did;
 * - otherwise the token is left empty, and the form is refused as an expired action, as it was before.
 *
 * A token from an earlier session is never in the copy and never in the new session, so its forms stay refused.
 */
final class KeptPage
{
    public const CSRF_MARKER = 'rz-kept-csrf';
    public const ACTION_MARKER = 'rz-kept-action-';

    /**
     * The copy of $page to keep: every token of $state replaced by its marker.
     * @param array<string, mixed> $state the session ($_SESSION)
     */
    public static function keep(string $page, array $state): string
    {
        $replace = [];
        if (isset($state['csrf']) && is_string($state['csrf']) && preg_match('/\A[a-f0-9]{64}\z/', $state['csrf'])) {
            $replace[$state['csrf']] = self::CSRF_MARKER;
        }
        foreach (is_array($state['actions'] ?? null) ? $state['actions'] : [] as $scope => $entry) {
            if (!is_array($entry) || !is_string($entry['token'] ?? null) || !is_string($entry['context'] ?? null) ||
                !preg_match('/\A[a-f0-9]{64}\z/', $entry['token'])) {
                continue;
            }
            $replace[$entry['token']] = self::ACTION_MARKER . bin2hex(json_encode([(string)$scope, $entry['context']], JSON_THROW_ON_ERROR));
        }
        return $replace === [] ? $page : strtr($page, $replace);
    }

    /**
     * The kept copy, its markers filled from $state (see the class comment). A copy kept before this rule, with its
     * tokens in it, is returned as it is.
     * @param array<string, mixed> $state the session ($_SESSION), which a reissued action is added to
     */
    public static function replay(string $kept, array &$state, bool $reissue): string
    {
        $page = str_replace(self::CSRF_MARKER, Csrf::token($state), $kept);
        return (string)preg_replace_callback('/' . preg_quote(self::ACTION_MARKER, '/') . '([a-f0-9]+)/', static function (array $match) use (&$state, $reissue): string {
            $named = self::named($match[1]);
            if ($named === null) return '';
            [$scope, $context] = $named;
            $entry = $state['actions'][$scope] ?? null;
            if (is_array($entry) && ($entry['context'] ?? null) === $context && is_string($entry['token'] ?? null)) {
                return $entry['token'];
            }
            return $reissue ? ActionToken::issue($state, $scope, $context) : '';
        }, $page);
    }

    /** The kept copy for the user editor's last-page view: the markers left empty, no token filled in. */
    public static function view(string $kept): string
    {
        return (string)preg_replace('/' . preg_quote(self::CSRF_MARKER, '/') . '|' . preg_quote(self::ACTION_MARKER, '/') . '[a-f0-9]+/', '', $kept);
    }

    /** @return array{0: string, 1: string}|null the scope and context a marker names */
    private static function named(string $hex): ?array
    {
        if (strlen($hex) % 2 !== 0) return null;
        $named = json_decode((string)hex2bin($hex), true);
        if (!is_array($named) || !array_is_list($named) || count($named) !== 2 || !is_string($named[0]) || !is_string($named[1])) {
            return null;
        }
        return [$named[0], $named[1]];
    }
}
