<?php
declare(strict_types=1);
namespace Resurrection\Security;

require_once __DIR__ . '/ScalarState.php';

/**
 * paylog.info: the PayPal notification fields 1.1.2's payment.php stored (serialize($_POST)), a flat map of
 * field names to strings. Read only: payment intake stays disabled, so nothing writes it.
 */
final class PaymentInfo
{
    /** paylog.info is TEXT. */
    public const MAX_BYTES = 65535;
    public const MAX_FIELDS = 512;

    /** @return array<array-key, string> */
    public static function read(mixed $encoded): array
    {
        if (!is_string($encoded) || $encoded === '' || strlen($encoded) > self::MAX_BYTES) throw new \DomainException('Invalid payment record.');
        $info = ScalarState::read($encoded);
        if (!is_array($info) || count($info) > self::MAX_FIELDS) throw new \DomainException('Invalid payment record.');
        foreach ($info as $key => $value) {
            if (!is_string($value) || (is_string($key) && strlen($key) > 255)) throw new \DomainException('Invalid payment field.');
        }
        return $info;
    }

    /** @param array<array-key, string> $info */
    public static function field(array $info, string $key): string
    {
        return $info[$key] ?? '';
    }

    /**
     * The payment's own date, as 1.1.2 read it (strtotime), or null when it has none.
     * @param array<array-key, string> $info
     */
    public static function date(array $info): ?string
    {
        $time = strtotime(self::field($info, 'payment_date'));
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }
}
