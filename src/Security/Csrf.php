<?php
declare(strict_types=1);
namespace Resurrection\Security;

final class Csrf
{
    /** @param array<string, mixed> $state */
    public static function token(array &$state): string
    {
        if (!isset($state['csrf']) || !is_string($state['csrf']) || !preg_match('/\A[a-f0-9]{64}\z/', $state['csrf'])) {
            $state['csrf'] = bin2hex(random_bytes(32));
        }
        return $state['csrf'];
    }

    /** @param array<string, mixed> $state */
    public static function valid(array $state, mixed $submitted): bool
    {
        return is_string($submitted) && isset($state['csrf']) && is_string($state['csrf']) &&
            strlen($submitted) === 64 && hash_equals($state['csrf'], $submitted);
    }

    /** @param array<string, mixed> $state */
    public static function field(array &$state): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::token($state) . '">';
    }

    /** @param array<string, mixed> $state */
    public static function requirePost(array $state, string $method, mixed $submitted): void
    {
        if ($method !== 'POST' || !self::valid($state, $submitted)) {
            throw new \DomainException('Invalid form submission.');
        }
    }
}
