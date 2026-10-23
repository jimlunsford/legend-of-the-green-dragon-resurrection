<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

final class BankAuthorityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once 'lib/bank_authority.php';
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    /** The 1.1.2 withdrawfinish arithmetic, transcribed with the undefined $leftoborrow marked. */
    private static function historical(int $gold, int $bank, int $amount, bool $borrow, int $maxborrow): array
    {
        if ($amount == 0) $amount = abs($bank);
        if ($amount > $bank && !$borrow) return ['short', $gold, $bank];
        if ($amount > $bank) {
            $lefttoborrow = $amount;
            if ($lefttoborrow <= $bank + $maxborrow) {
                if ($bank > 0) { $lefttoborrow -= $bank; $gold += $bank; $bank = 0; }
                if ($lefttoborrow - $bank > $maxborrow) return ['leftoborrow', $gold, $bank];
                return ['borrow', $gold + $lefttoborrow, $bank - $lefttoborrow];
            }
            return ['limit', $gold, $bank];
        }
        return ['withdraw', $gold + $amount, $bank - $amount];
    }

    public function testWithdrawalMatchesHistoricalArithmeticAndNeverReachesTheUndefinedVariable(): void
    {
        foreach ([0, 20, 60, 100] as $maxborrow) {
            for ($bank = -120; $bank <= 120; $bank += 5) {
                for ($amount = 0; $amount <= 260; $amount += 5) {
                    foreach ([false, true] as $borrow) {
                        [$outcome, $gold, $after] = self::historical(7, $bank, $amount, $borrow, $maxborrow);
                        $now = resurrection_bank_withdrawal(7, $bank, $amount, $borrow, $maxborrow);
                        $case = "$maxborrow/$bank/$amount/" . (int)$borrow;
                        self::assertNotSame('leftoborrow', $outcome, $case);
                        self::assertNotSame('refused', $now['outcome'], $case);
                        self::assertSame($outcome, $now['outcome'], $case);
                        self::assertSame([$gold, $after], [$now['gold'], $now['bank']], $case);
                        // Gold never goes negative; debt never passes the borrow limit unless it already had.
                        self::assertGreaterThanOrEqual(0, $now['gold'], $case);
                        if ($now['bank'] !== $bank) self::assertGreaterThanOrEqual(-$maxborrow, $now['bank'], $case);
                    }
                }
            }
        }
        // Examples from the HTTP test: the balance is withdrawn first, then the rest borrowed.
        self::assertSame(['outcome'=>'borrow', 'amount'=>130, 'withdrew'=>30, 'borrowed'=>100, 'gold'=>130, 'bank'=>-100],
            resurrection_bank_withdrawal(0, 30, 130, true, 100));
        self::assertSame('limit', resurrection_bank_withdrawal(130, -100, 1, true, 100)['outcome']);
    }

    public function testAmountsAreEmptyOrCanonicalNonNegativeIntegers(): void
    {
        foreach (['' => 0, ' ' => 0, '0' => 0, '50' => 50, ' 50 ' => 50, '999999999' => 999999999] as $value => $expected) {
            $_POST = ['amount' => (string)$value];
            self::assertSame($expected, resurrection_bank_amount(), var_export($value, true));
        }
        $_POST = [];
        self::assertSame(0, resurrection_bank_amount());
        foreach (['-50', '-0', '1.5', 'abc', '12abc', '0x10', '1e3', '007', '+5', '99999999999999999999999', ['1']] as $value) {
            $_POST = ['amount' => $value];
            try { resurrection_bank_amount(); self::fail('Accepted ' . var_export($value, true)); }
            catch (\InvalidArgumentException $error) { self::assertNotEmpty($error->getMessage()); }
        }
    }

    public function testSearchPatternMatchesUtf8CharactersAndLiteralWildcards(): void
    {
        self::assertSame('%', resurrection_bank_search_pattern(''));
        self::assertSame("%O%'%R%", resurrection_bank_search_pattern("O'R"));
        self::assertSame('%雪%\\%', resurrection_bank_search_pattern('雪\\'));
        self::assertSame('%|%%|_%||%', resurrection_bank_search_pattern('%_|'));
        $this->expectException(\InvalidArgumentException::class);
        resurrection_bank_search_pattern("\xff");
    }
}
