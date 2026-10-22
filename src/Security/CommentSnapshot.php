<?php
declare(strict_types=1);
namespace Resurrection\Security;

require_once __DIR__ . '/ScalarState.php';

/**
 * moderatedcomments.comment: the commentary row a moderator removed, with its author's display fields.
 * 1.1.2 wrote two shapes: moderate.php added name, login, clanrank and clanshort; the commentary Del link
 * added name, acctid, clanrank and clanshort. Both read; restore inserts the commentary columns back.
 */
final class CommentSnapshot
{
    /** moderatedcomments.comment is TEXT. */
    public const MAX_BYTES = 65535;
    private const COMMENTARY = ['commentid', 'section', 'author', 'comment', 'postdate'];
    private const AUTHOR = ['name', 'login', 'acctid', 'clanrank', 'clanshort'];

    /** @param array<string, mixed> $comment */
    public static function encode(array $comment): string
    {
        $encoded = serialize(self::snapshot($comment));
        if (strlen($encoded) > self::MAX_BYTES || ScalarState::read($encoded) !== $comment) {
            throw new \DomainException('Comment snapshot is too large.');
        }
        return $encoded;
    }

    /** @return array<string, mixed> */
    public static function read(mixed $encoded): array
    {
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES) throw new \DomainException('Invalid comment snapshot.');
        return self::snapshot(ScalarState::read($encoded));
    }

    /** @return array<string, mixed> */
    public static function snapshot(mixed $value): array
    {
        if (!is_array($value)) throw new \DomainException('Invalid comment snapshot.');
        $keys = array_keys($value);
        if (array_diff(self::COMMENTARY, $keys) !== [] || array_diff($keys, [...self::COMMENTARY, ...self::AUTHOR]) !== []) {
            throw new \DomainException('Invalid comment snapshot fields.');
        }
        self::id($value['commentid'], 1);
        self::id($value['author'], 0);
        // commentary.section is varchar(20) NULL, comment varchar(200): restore must fit them.
        if (($value['section'] !== null && !self::text($value['section'], 20)) || !self::text($value['comment'], 200) ||
            ($value['postdate'] !== null && (!is_string($value['postdate']) ||
                !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $value['postdate'])))) {
            throw new \DomainException('Invalid comment snapshot value.');
        }
        foreach (['name' => 255, 'login' => 255, 'clanshort' => 255] as $key => $limit) {
            if (isset($value[$key]) && (!is_string($value[$key]) || strlen($value[$key]) > $limit)) {
                throw new \DomainException('Invalid comment author.');
            }
        }
        if (isset($value['acctid'])) self::id($value['acctid'], 0);
        if (isset($value['clanrank']) && self::id($value['clanrank'], 0) > 255) throw new \DomainException('Invalid comment author.');
        return $value;
    }

    private static function id(mixed $value, int $minimum): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', (string)$value) ||
            (int)$value < $minimum || (int)$value > 4294967295) {
            throw new \DomainException('Invalid comment snapshot ID.');
        }
        return (int)$value;
    }

    private static function text(mixed $value, int $characters): bool
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') <= $characters;
    }
}
