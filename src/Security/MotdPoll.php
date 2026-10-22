<?php
declare(strict_types=1);
namespace Resurrection\Security;

require_once __DIR__ . '/ScalarState.php';

/**
 * motd.motdbody of a poll (motdtype 1): {body: text, opt: choice number => label}. 1.1.2 stored the request
 * values with magic quotes' slashes and shows them through stripslashes(); the writer keeps that stored form.
 */
final class MotdPoll
{
    /** motd.motdbody is TEXT. */
    public const MAX_BYTES = 65535;
    public const MAX_OPTIONS = 100;

    /** @return array{body:string,opt:array<int,string>} */
    public static function read(mixed $encoded): array
    {
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES) throw new \DomainException('Invalid poll.');
        return self::poll(ScalarState::read($encoded));
    }

    /** @param array<array-key, mixed> $poll */
    public static function encode(array $poll): string
    {
        $encoded = serialize(self::poll($poll));
        if (strlen($encoded) > self::MAX_BYTES || ScalarState::read($encoded) !== $poll) throw new \DomainException('Poll is too large.');
        return $encoded;
    }

    /** @return array{body:string,opt:array<int,string>} */
    public static function poll(mixed $value): array
    {
        if (!is_array($value) || array_keys($value) !== ['body', 'opt'] || !is_string($value['body']) ||
            !is_array($value['opt']) || count($value['opt']) > self::MAX_OPTIONS) {
            throw new \DomainException('Invalid poll.');
        }
        // pollresults.choice is int unsigned.
        foreach ($value['opt'] as $key => $label) {
            if (!is_int($key) || $key < 0 || $key > 4294967295 || !is_string($label)) throw new \DomainException('Invalid poll option.');
        }
        return $value;
    }

    /**
     * The choices a player can vote for: the options pollitem() lists, those with a label.
     * @param array{body:string,opt:array<int,string>} $poll
     * @return array<int,string>
     */
    public static function choices(array $poll): array
    {
        return array_filter($poll['opt'], static fn (string $label): bool => trim($label) !== '');
    }
}
