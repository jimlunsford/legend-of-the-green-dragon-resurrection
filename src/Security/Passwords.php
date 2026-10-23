<?php
declare(strict_types=1);
namespace Resurrection\Security;

final class Passwords
{
    public static function hash(#[\SensitiveParameter] string $password): string
    {
        // PASSWORD_DEFAULT currently uses bcrypt. Reject truncation and NULs.
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Password must contain 12 to 72 bytes and no NUL characters.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(#[\SensitiveParameter] mixed $password, #[\SensitiveParameter] mixed $hash): bool
    {
        if (!is_string($password) || !is_string($hash) || $password === '' || strlen($password) > 72 ||
            str_contains($password, "\0") || password_get_info($hash)['algo'] === null) {
            return false;
        }
        // No legacy MD5 branch and no request-controlled stored-hash bypass.
        return !hash_equals($hash, $password) && password_verify($password, $hash);
    }
}
