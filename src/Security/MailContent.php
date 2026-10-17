<?php
declare(strict_types=1);
namespace Resurrection\Security;

/** Mail business schema; player text is never a translation envelope. */
final class MailContent
{
    public static function id(mixed $value, bool $system = false): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string)$value) ||
            strlen((string)$value) > 10 || (float)$value > 4294967295 || (!$system && (string)$value === '0')) {
            throw new \DomainException('Invalid mail account.');
        }
        return (int)$value;
    }

    public static function text(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > 262140) {
            throw new \DomainException('Invalid mail text.');
        }
        return $value;
    }

    public static function subject(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x1f\x7f]/', '', str_replace('`n', '', self::text($value)));
        if (mb_strlen($value, 'UTF-8') > 255) throw new \DomainException('Mail subject is too long.');
        return $value;
    }

    public static function body(mixed $value, int $limit): string
    {
        if ($limit < 1 || $limit > 65535) throw new \DomainException('Invalid mail size limit.');
        $value = str_replace(["\r\n", "\r"], "\n", str_replace('`n', "\n", self::text($value)));
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) throw new \DomainException('Invalid mail control character.');
        // Historical byte cap, without splitting a UTF-8 code point or stripping literal slashes.
        return mb_strcut($value, 0, $limit, 'UTF-8');
    }

    /** @return non-empty-list<string|int|float|bool> */
    public static function translated(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) < 1 || count($value) > 33 || !is_string($value[0])) {
            throw new \DomainException('Invalid translated mail.');
        }
        foreach ($value as $arg) {
            if (!is_string($arg) && !is_int($arg) && !is_bool($arg) && !(is_float($arg) && is_finite($arg))) {
                throw new \DomainException('Invalid translated mail argument.');
            }
            if (is_string($arg)) self::text($arg);
        }
        $encoded = serialize($value);
        if (strlen($encoded) > 65535 || ScalarState::read($encoded) !== $value) throw new \DomainException('Translated mail is too large.');
        // Bound and validate the shipped sprintf vocabulary, including literal percentages.
        $sprintf = str_replace('`%', '`%%', $value[0]);
        $format = preg_replace('/%%/', '', $sprintf);
        preg_match_all('/%(?:[1-9][0-9]*\$)?[-+0 ]*(?:[0-9]{1,3})?(?:\.[0-9]{1,3})?[bcdeEfFgGosuxX]/', $format, $matches);
        if (str_contains(preg_replace('/%(?:[1-9][0-9]*\$)?[-+0 ]*(?:[0-9]{1,3})?(?:\.[0-9]{1,3})?[bcdeEfFgGosuxX]/', '', $format), '%') || count($matches[0]) !== count($value)-1) {
            throw new \DomainException('Invalid translated mail format.');
        }
        try { if (strlen(vsprintf($sprintf, array_slice($value, 1))) > 65535) throw new \DomainException('Expanded mail is too large.'); }
        catch (\Throwable $error) { throw new \DomainException('Invalid translated mail format.', 0, $error); }
        return $value;
    }

    /** Stored system payload only. Invalid envelopes remain literal, never sprintf arguments.
     * @return non-empty-list<string|int|float|bool>|null
     */
    public static function stored(mixed $value): ?array
    {
        try { return self::translated(ScalarState::read($value)); }
        catch (\DomainException) { return null; }
    }

    /** @param array<string,mixed> $parsed
     * @param list<string> $allowed
     */
    public static function transport(string $raw, array $parsed, array $allowed): void
    {
        if (strlen($raw) > 800000) throw new \InvalidArgumentException('Mail request is too large.');
        $actual = [];
        foreach ($raw === '' ? [] : explode('&', $raw) as $pair) {
            $parts = explode('=', $pair, 2); $key = urldecode($parts[0]);
            if (count($parts) !== 2 || !in_array($key, $allowed, true) || array_key_exists($key, $actual)) {
                throw new \InvalidArgumentException('Invalid mail transport.');
            }
            $actual[$key] = urldecode($parts[1]);
        }
        ksort($actual); ksort($parsed);
        if ($actual !== $parsed) throw new \InvalidArgumentException('Ambiguous mail transport.');
    }
}
