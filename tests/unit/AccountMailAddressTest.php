<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * The address rules (design section 2.4; Phase 6 task 13a): resurrection_mail_address_valid() takes only the plain
 * dot-atom form, anchored at both ends, at most 128 bytes, with a domain that has a dot. lib/is_email.php (1.1.2's,
 * unanchored) is never the gate for mail.
 */
final class AccountMailAddressTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $installs = !in_array(realpath('lib/errorhandling.php'), get_included_files(), true);
        require_once 'lib/account_mail.php';
        if ($installs) restore_error_handler();
    }

    public function testDotAtomOnly(): void
    {
        foreach (['player@example.org', 'first.last@mail.example.co.uk', "o'brien+game@example.org", 'a_b-c@sub-domain.example.org',
                'x@a.io', 'UPPER@EXAMPLE.ORG', '!#$%&*+/=?^_`{|}~-@example.org'] as $address) {
            self::assertTrue(resurrection_mail_address_valid($address), $address);
        }
        foreach (['', 'player', '@example.org', 'player@', 'player@localhost', 'player@.example.org', 'player@example..org', 'player@-example.org',
                'player@example-.org', '.player@example.org', 'player.@example.org', 'pla..yer@example.org', 'player@example.org.',
                'player@@example.org', 'player@exa_mple.org', 'plåyer@example.org', 'player@exämple.org', null, 42, ['player@example.org']] as $address) {
            self::assertFalse(resurrection_mail_address_valid($address), var_export($address, true));
        }
    }

    public function testRefusesCrLfNulQuotesCommasAndIpLiterals(): void
    {
        foreach (["player@example.org\r\nBcc: other@example.org", "player@example.org\nBcc: other@example.org", "player@example.org\r",
                "player@example.org\0", "pla\0yer@example.org", "player@example.org\t", ' player@example.org', 'player@example.org ',
                'pla yer@example.org', '"pla yer"@example.org', '"player"@example.org', 'player@example.org, other@example.org',
                'player@example.org,other@example.org', 'Player <player@example.org>', '<player@example.org>', 'player@[127.0.0.1]',
                'player@[IPv6:::1]', 'player@127.0.0.1', 'player(comment)@example.org', 'player@example.org;other@example.org',
                "player\x7F@example.org", 'other@example.org player@example.org', "player@example.org\nother@example.org"] as $address) {
            self::assertFalse(resurrection_mail_address_valid($address), json_encode($address));
        }
        // 1.1.2's is_email() is unanchored: it takes a value with a second line, so it never gates mail.
        self::assertTrue((bool)is_email("player@example.org\nBcc: other@example.org"));
        self::assertStringNotContainsString('is_email(', (string)file_get_contents('lib/account_mail.php'));
    }

    public function testRefusesOver128Bytes(): void
    {
        $local = str_repeat('a', 64);
        $at128 = $local . '@' . str_repeat('b', 128 - 64 - 1 - 4) . '.org';
        self::assertSame(128, strlen($at128));
        self::assertTrue(resurrection_mail_address_valid($at128));
        $at129 = $local . '@' . str_repeat('b', 128 - 64 - 1 - 3) . '.org';
        self::assertSame(129, strlen($at129));
        self::assertFalse(resurrection_mail_address_valid($at129));
        // The column it is stored in is as wide as the rule.
        self::assertStringContainsString("'name'=>'emailaddress', 'type'=>'varchar(128)'", (string)file_get_contents('lib/all_tables.php'));
    }
}
