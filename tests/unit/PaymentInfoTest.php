<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;
use Resurrection\Security\PaymentInfo;

final class PaymentInfoTest extends TestCase
{
    public function testHistoricalNotificationFields(): void
    {
        // 1.1.2 stored $_POST under magic quotes: string values, slashes included.
        $info = ['payment_date'=>'10:00:00 Oct 22, 2026 PDT', 'txn_type'=>'web_accept', 'mc_gross'=>'5.00', 'mc_fee'=>'0.45',
            'mc_currency'=>'USD', 'memo'=>"O\\'Reilly 雪", 'item_number'=>'Joe:legend', 0=>'numeric key'];
        self::assertSame($info, PaymentInfo::read(serialize($info)));
        self::assertSame('web_accept', PaymentInfo::field($info, 'txn_type'));
        self::assertSame('', PaymentInfo::field($info, 'missing'));
        self::assertSame(date('Y-m-d H:i:s', strtotime('10:00:00 Oct 22, 2026 PDT')), PaymentInfo::date($info));
        self::assertNull(PaymentInfo::date(['payment_date'=>'not a date']));
        self::assertNull(PaymentInfo::date([]));
        self::assertSame([], PaymentInfo::read('a:0:{}'));
    }

    public function testMalformedRecordsAreRefused(): void
    {
        foreach (['', 'broken', 'b:0;', 's:1:"x";', serialize(['a'=>['nested']]), serialize(['a'=>5]), serialize(['a'=>null]),
            serialize([str_repeat('k', 256)=>'x']), serialize(array_fill(0, PaymentInfo::MAX_FIELDS + 1, 'x')),
            serialize(['a'=>str_repeat('x', PaymentInfo::MAX_BYTES)]), 'O:8:"stdClass":0:{}', null, 5] as $encoded) {
            try { PaymentInfo::read($encoded); self::fail('Malformed payment record accepted.'); }
            catch (\DomainException) { self::assertTrue(true); }
        }
    }
}
