<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Session-bound one-use form intent. Business authorization is checked separately. */
final class ActionToken
{
    /** @param array<string, mixed> $state */
    public static function issue(array &$state, string $scope, string $context): string
    {
        $entry = $state['actions'][$scope] ?? null;
        if (!is_array($entry) || ($entry['context'] ?? null) !== $context) {
            $entry = ['token'=>bin2hex(random_bytes(32)), 'context'=>$context];
            $state['actions'][$scope] = $entry;
        }
        return $entry['token'];
    }

    /** @param array<string, mixed> $state */
    public static function consume(array &$state, string $scope, string $context, mixed $token): void
    {
        $entry = $state['actions'][$scope] ?? null;
        if (!is_array($entry) || !is_string($token) || ($entry['context'] ?? null) !== $context ||
            !hash_equals($entry['token'], $token)) {
            throw new \DomainException('Expired or repeated action.');
        }
        unset($state['actions'][$scope]);
    }
}
