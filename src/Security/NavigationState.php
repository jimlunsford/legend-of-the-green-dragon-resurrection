<?php
declare(strict_types=1);
namespace Resurrection\Security;

/**
 * Business schema for an account's forced navigation: `allowednavs`, the serialized map of links the player may
 * request next, and `restorepage`, the plain page a new day, the upgrade notice and the installer return to.
 */
final class NavigationState
{
    public const RESTORE_PAGE_LENGTH = 128;

    /**
     * The stored `allowednavs` column. An empty value means no allowed links: the user editor's Fix Navs writes it,
     * as 1.1.2's did, and badnav.php then shows the corrupted-navs page. 1.1.2 wrapped a stored value that was not
     * an array as `array(0 => <stored text>)`, and an override page could save that entry back beside new links;
     * badnav.php drops it, so that legacy entry is accepted here and dropped. Anything else that is not a map of
     * link to boolean throws.
     * @return array<string, bool>
     */
    public static function read(mixed $stored): array
    {
        if ($stored === null || $stored === '') { return []; }
        $navs = ScalarState::read($stored);
        if (!is_array($navs)) { throw new \DomainException('Invalid stored navigation.'); }
        if (array_key_exists(0, $navs) && is_string($navs[0])) { unset($navs[0]); }
        return self::map($navs);
    }

    /**
     * The in-memory map addnav() builds and saveuser() stores: link => true. Links are strings (an empty link is
     * possible and badnav.php drops it) without control characters; values are booleans.
     * @return array<string, bool>
     */
    public static function map(mixed $navs): array
    {
        if (!is_array($navs)) { throw new \DomainException('Invalid navigation.'); }
        foreach ($navs as $link => $allowed) {
            if (!is_string($link) || preg_match('/[\x00-\x1F\x7F]/', $link) || !is_bool($allowed)) {
                throw new \DomainException('Invalid navigation.');
            }
        }
        /** @var array<string, bool> $navs */
        return $navs;
    }

    /** The stored `restorepage` column: empty (or NULL, the column default) when unset, else a valid game page. */
    public static function restorePage(mixed $stored): string
    {
        if ($stored === null || $stored === '') { return ''; }
        if (!is_string($stored) || !self::validRestorePage($stored)) {
            throw new \DomainException('Invalid stored return page.');
        }
        return $stored;
    }

    /**
     * A game page as common.php records it from the request: a script name ending in .php, then an optional
     * query of printable ASCII without quotes, angle brackets, backslash or backtick, within the 128-character
     * column. The value is written raw into a meta refresh and a navigation link.
     */
    public static function validRestorePage(string $page): bool
    {
        return strlen($page) <= self::RESTORE_PAGE_LENGTH &&
            preg_match('/\A[A-Za-z0-9_.-]+\.php(?:\?[\x21\x23-\x26\x28-\x3B\x3D\x3F-\x5B\x5D-\x5F\x61-\x7E]*)?\z/', $page) === 1;
    }
}
