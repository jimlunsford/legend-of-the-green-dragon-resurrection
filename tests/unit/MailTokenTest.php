<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Account mail's tokens (design section 3.2; Phase 6 task 13a): 32 random bytes in base64url, only the SHA-256 stored,
 * a shape check before any query, an expiry per purpose, at most 5 openings, single use, bound to the account's
 * authversion, its purpose and its address. Opening exchanges the token for a grant that holds its hash, never it.
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it Account..., which would run before the install).
 */
final class MailTokenTest extends TestCase
{
    use AccountMailFixture;

    private const int NOW = 1800000000;

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
    }

    private static function claimAndSpend(array $opened, int $now): void
    {
        $connection = $GLOBALS['dbinfo']['connection'];
        $connection->beginTransaction();
        try {
            resurrection_account_token_claim($opened['tokenid'], $opened['tokenhash'], $opened['purpose'], $now);
            resurrection_account_token_spend($opened['tokenid'], $now);
            $connection->commit();
        } catch (\Throwable $error) { $connection->rollBack(); throw $error; }
    }

    public function testTokenIs43Base64UrlCharactersFrom32Bytes(): void
    {
        $this->connectAccountMail();
        $seen = [];
        for ($i = 0; $i < 25; $i++) {
            $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $token);
            self::assertTrue(resurrection_account_token_shape($token));
            $bytes = base64_decode(strtr($token, '-_', '+/') . '=', true);
            self::assertIsString($bytes);
            self::assertSame(32, strlen($bytes));
            $seen[$token] = true;
        }
        self::assertCount(25, $seen);
        // The shape is checked before any query: with no database connection at all, every malformed value is refused.
        $connection = $GLOBALS['dbinfo']['connection'];
        $GLOBALS['dbinfo']['connection'] = null;
        try {
            foreach ([null, 42, [], ['x'], '', str_repeat('A', 42), str_repeat('A', 44), str_repeat('A', 42) . '+', str_repeat('A', 42) . '/',
                    str_repeat('A', 42) . '=', str_repeat('A', 42) . "\n", "' OR 1=1 -- " . str_repeat('A', 31), str_repeat('Ä', 21) . 'A'] as $bad) {
                self::assertFalse(resurrection_account_token_shape($bad));
                self::assertNull(resurrection_account_token_open($bad, ['mailtest'], self::NOW), json_encode($bad));
            }
        } finally { $GLOBALS['dbinfo']['connection'] = $connection; }
    }

    public function testOnlyTheHashIsStored(): void
    {
        $this->connectAccountMail();
        $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        $row = db_query('SELECT * FROM account_tokens')[0];
        self::assertSame(hash('sha256', $token), $row['tokenhash']);
        self::assertSame(['tokenid', 'acctid', 'purpose', 'tokenhash', 'address', 'authversion', 'created', 'expires', 'opened', 'used'], array_keys($row));
        // The token is nowhere in the database: not in any table, any row, any column.
        require_once __DIR__ . '/../support/database_snapshot.php';
        $snapshot = json_encode(resurrection_database_snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertStringNotContainsString($token, $snapshot);
        self::assertStringContainsString($row['tokenhash'], $snapshot);
        // Opening it returns its row with the hash, never the token.
        $opened = resurrection_account_token_open($token, ['mailtest'], self::NOW);
        self::assertSame(['tokenid' => (int)$row['tokenid'], 'acctid' => 1, 'purpose' => 'mailtest', 'address' => 'admin@example.org', 'tokenhash' => $row['tokenhash']], $opened);
        self::assertStringNotContainsString($token, (string)json_encode($opened));
        // The hash is not itself a token.
        self::assertNull(resurrection_account_token_open($row['tokenhash'], ['mailtest'], self::NOW));
    }

    public function testExpiryPerPurpose(): void
    {
        $this->connectAccountMail();
        self::assertSame(['recover' => 1800, 'verify' => 86400, 'email' => 86400, 'mailtest' => 86400], RESURRECTION_ACCOUNT_TOKEN_LIFETIMES);
        foreach (RESURRECTION_ACCOUNT_TOKEN_LIFETIMES as $purpose => $lifetime) {
            $token = resurrection_account_token_issue(1, $purpose, 'admin@example.org', self::NOW);
            self::assertNotNull(resurrection_account_token_open($token, [$purpose], self::NOW + $lifetime - 1), $purpose);
            self::assertNull(resurrection_account_token_open($token, [$purpose], self::NOW + $lifetime), $purpose);
            $row = db_query('SELECT created,expires FROM account_tokens WHERE tokenhash=?', true, [hash('sha256', $token)])[0];
            self::assertSame($lifetime, strtotime($row['expires']) - strtotime($row['created']));
        }
        $this->expectException(\InvalidArgumentException::class);
        resurrection_account_token_issue(1, 'other', 'admin@example.org', self::NOW);
    }

    public function testSingleUse(): void
    {
        $this->connectAccountMail();
        $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        $opened = resurrection_account_token_open($token, ['mailtest'], self::NOW);
        self::assertNotNull($opened);
        self::claimAndSpend($opened, self::NOW + 10);
        self::assertNull(resurrection_account_token_open($token, ['mailtest'], self::NOW + 20));
        try { self::claimAndSpend($opened, self::NOW + 20); self::fail('A used token was claimed again.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        self::assertSame(date('Y-m-d H:i:s', self::NOW + 10), db_query('SELECT used FROM account_tokens')[0]['used']);
        // A claim needs the caller's transaction.
        try { resurrection_account_token_claim($opened['tokenid'], $opened['tokenhash'], 'mailtest', self::NOW); self::fail('Claimed outside a transaction.'); }
        catch (\LogicException $error) { self::assertStringContainsString('transaction', $error->getMessage()); }
    }

    public function testAuthversionChangeKillsIt(): void
    {
        $this->connectAccountMail();
        $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        $opened = resurrection_account_token_open($token, ['mailtest'], self::NOW);
        self::assertNotNull($opened);
        // A sign-in, a password change or a sign-out everywhere moves authversion: the link and the grant are dead.
        db_query('UPDATE accounts SET authversion=authversion+1 WHERE acctid=1');
        self::assertNull(resurrection_account_token_open($token, ['mailtest'], self::NOW));
        try { self::claimAndSpend($opened, self::NOW); self::fail('A token was claimed after its account moved on.'); }
        catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        self::assertNull(db_query('SELECT used FROM account_tokens')[0]['used']);
    }

    public function testPurposeAndAddressAreBound(): void
    {
        $this->connectAccountMail();
        $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        self::assertNull(resurrection_account_token_open($token, ['recover', 'verify', 'email'], self::NOW));
        self::assertSame('0', db_query('SELECT opened FROM account_tokens')[0]['opened'], 'A refused purpose does not count as an opening.');
        $opened = resurrection_account_token_open($token, ['mailtest'], self::NOW);
        self::assertSame('admin@example.org', $opened['address']);
        $connection = $GLOBALS['dbinfo']['connection'];
        $connection->beginTransaction();
        try {
            resurrection_account_token_claim($opened['tokenid'], $opened['tokenhash'], 'verify', self::NOW);
            self::fail('A token was claimed for another purpose.');
        } catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        finally { $connection->rollBack(); }
        $connection->beginTransaction();
        try {
            resurrection_account_token_claim($opened['tokenid'], str_repeat('0', 64), 'mailtest', self::NOW);
            self::fail('A token was claimed with another hash.');
        } catch (\DomainException $error) { self::assertSame('Expired link.', $error->getMessage()); }
        finally { $connection->rollBack(); }
        // An address that is not one is never bound to a token.
        foreach (["admin@example.org\nBcc: other@example.org", 'not an address', ''] as $address) {
            try { resurrection_account_token_issue(1, 'mailtest', $address, self::NOW); self::fail('Issued for ' . json_encode($address)); }
            catch (\InvalidArgumentException $error) { self::assertSame('Invalid address.', $error->getMessage()); }
        }
        try { resurrection_account_token_issue(999999, 'mailtest', 'admin@example.org', self::NOW); self::fail('Issued for a missing account.'); }
        catch (\DomainException $error) { self::assertSame('Account removed.', $error->getMessage()); }
    }

    public function testOpenLimit(): void
    {
        $this->connectAccountMail();
        self::assertSame(5, RESURRECTION_ACCOUNT_TOKEN_OPENS);
        $token = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        for ($i = 1; $i <= 5; $i++) self::assertNotNull(resurrection_account_token_open($token, ['mailtest'], self::NOW + $i), 'opening ' . $i);
        self::assertNull(resurrection_account_token_open($token, ['mailtest'], self::NOW + 6));
        self::assertSame('5', db_query('SELECT opened FROM account_tokens')[0]['opened']);
    }

    public function testNewRecoveryTokenReplacesTheOld(): void
    {
        $this->connectAccountMail();
        $first = resurrection_account_token_issue(1, 'recover', 'admin@example.org', self::NOW);
        $other = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        $second = resurrection_account_token_issue(1, 'recover', 'admin@example.org', self::NOW + 60);
        self::assertNull(resurrection_account_token_open($first, ['recover'], self::NOW + 61));
        self::assertNotNull(resurrection_account_token_open($second, ['recover'], self::NOW + 61));
        // Only recovery tokens are replaced.
        self::assertNotNull(resurrection_account_token_open($other, ['mailtest'], self::NOW + 61));
    }

    public function testTheGrantHoldsTheHashAndExpires(): void
    {
        $_SESSION = [];
        self::assertNull(resurrection_account_grant('mailtest', self::NOW));
        $hash = hash('sha256', 'x');
        $_SESSION['account_grant'] = ['purpose' => 'mailtest', 'tokenid' => 7, 'hash' => $hash, 'expires' => self::NOW + RESURRECTION_ACCOUNT_GRANT_LIFETIME];
        self::assertSame(900, RESURRECTION_ACCOUNT_GRANT_LIFETIME);
        self::assertSame($_SESSION['account_grant'], resurrection_account_grant('mailtest', self::NOW));
        self::assertNull(resurrection_account_grant('recover', self::NOW));
        self::assertNull(resurrection_account_grant('mailtest', self::NOW + 900));
        foreach ([['hash' => 'not-a-hash'], ['tokenid' => '7'], ['expires' => null], ['purpose' => null]] as $damage) {
            $_SESSION['account_grant'] = array_replace(['purpose' => 'mailtest', 'tokenid' => 7, 'hash' => $hash, 'expires' => self::NOW + 900], $damage);
            self::assertNull(resurrection_account_grant('mailtest', self::NOW));
        }
        resurrection_account_grant_clear();
        self::assertArrayNotHasKey('account_grant', $_SESSION);
        // The grant is stored from the opened row: its hash, never a token.
        self::assertStringContainsString("'hash' => \$opened['tokenhash']", (string)file_get_contents('lib/account_mail.php'));
    }

    public function testCleanupRemovesOldTokensAndLogRows(): void
    {
        $this->connectAccountMail();
        $day = 86400;
        $live = resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW);
        resurrection_account_token_issue(1, 'recover', 'admin@example.org', self::NOW - 8 * $day);
        resurrection_account_token_issue(1, 'mailtest', 'admin@example.org', self::NOW - 6 * $day);
        $used = resurrection_account_token_issue(1, 'verify', 'admin@example.org', self::NOW - $day);
        db_query('UPDATE account_tokens SET used=? WHERE tokenhash=?', true, [date('Y-m-d H:i:s', self::NOW - 8 * $day), hash('sha256', $used)]);
        foreach ([31, 29, 0] as $age) resurrection_account_mail_log('mailtest', 'sent', 1, 'admin@example.org', self::NOW - $age * $day);
        // Removed: the recovery token expired 8 days ago, the token used 8 days ago, the log row 31 days old.
        self::assertSame(['tokens' => 2, 'log' => 1], resurrection_account_mail_cleanup(self::NOW));
        self::assertSame(2, (int)db_query('SELECT COUNT(*) AS n FROM account_tokens')[0]['n']);
        self::assertNotNull(resurrection_account_token_open($live, ['mailtest'], self::NOW));
        self::assertSame(2, (int)db_query('SELECT COUNT(*) AS n FROM account_mail_log')[0]['n']);
        // Maintenance runs it once a game day, and deleting a character removes its tokens.
        self::assertStringContainsString('resurrection_account_mail_cleanup();', (string)file_get_contents('lib/newday/newday_runonce.php'));
        self::assertStringContainsString("db_query('DELETE FROM ' . db_prefix('account_tokens') . ' WHERE acctid=?', true, [\$id]);", (string)file_get_contents('lib/charcleanup.php'));
    }
}
