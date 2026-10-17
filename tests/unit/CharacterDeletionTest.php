<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

final class CharacterDeletionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once 'lib/constants.php';
        // The mail helpers load the database wrapper, which installs the application's error handler once.
        $installs = !in_array(realpath('lib/errorhandling.php'), get_included_files(), true);
        require_once 'lib/char_expiration.php';
        if ($installs) restore_error_handler();
    }

    /** lib/expire_chars.php's 1.1.2 selection condition, transcribed. */
    private static function historical(int $old, int $new, int $trash, int $now): string
    {
        return "(superuser&".NO_ACCOUNT_EXPIRATION.")=0 AND (1=0\n".
            ($old>0?"OR (laston < \"".date("Y-m-d H:i:s",strtotime("-$old days",$now))."\")\n":"").
            ($new>0?"OR (laston < \"".date("Y-m-d H:i:s",strtotime("-$new days",$now))."\" AND level=1 AND dragonkills=0)\n":"").
            ($trash>0?"OR (regdate < date_add(NOW(),interval -".$trash." day) AND laston < regdate)\n":"").")";
    }

    /** @param list<int|string> $parameters */
    private static function interpolate(string $sql, array $parameters): string
    {
        self::assertSame(substr_count($sql, '?'), count($parameters));
        foreach ($parameters as $value) {
            $sql = preg_replace('/\?/', is_int($value) ? (string)$value : '"' . $value . '"', $sql, 1);
        }
        return $sql;
    }

    public function testSelectionRulesMatchHistoricalSql(): void
    {
        $now = 1792000000;
        foreach ([-3, 0, 1, 5, 45] as $old) foreach ([-1, 0, 10] as $new) foreach ([-1, 0, 1, 7] as $trash) {
            [$where, $parameters] = resurrection_expire_rules($old, $new, $trash, $now);
            $expected = self::historical($old, $new, $trash, $now);
            // The two recorded differences: the trash cutoff is computed like the other two, from the same clock
            // that writes regdate, and "never logged on" matches modern creation's laston = regdate.
            $expected = preg_replace('/date_add\(NOW\(\),interval -(\d+) day\)/', '"' . date('Y-m-d H:i:s', strtotime("-$trash days", $now)) . '"', $expected);
            $expected = str_replace('laston < regdate', 'laston <= regdate', (string)$expected);
            $strip = static fn(string $sql): string => (string)preg_replace('/\s+/', '', $sql);
            self::assertSame($strip($expected), $strip(self::interpolate($where, $parameters)), "old=$old new=$new trash=$trash");
            self::assertStringNotContainsString('"', $where);
        }
    }

    public function testWarningCutoffKeepsHistoricalPrecedence(): void
    {
        $now = 1792000000;
        foreach (range(-2, 60) as $old) {
            $cutoff = resurrection_expire_warning_cutoff($old, $now);
            // 1.1.2: $old -= 5; "1=0 OR (laston < cutoff) AND ..." selects only while the result is above 0.
            $days = $old - 5;
            self::assertSame($days > 0 ? date('Y-m-d H:i:s', strtotime("-$days days", $now)) : null, $cutoff, "old=$old");
        }
    }

    public function testClanSuccessionMatchesHistoricalRule(): void
    {
        $member = static fn(int $rank): array => ['acctid' => 7, 'clanrank' => $rank];
        // Not in a clan, or not exactly a leader (1.1.2 compared with ==): the clan is untouched.
        self::assertNull(resurrection_char_cleanup_succession(0, CLAN_LEADER, $member(CLAN_MEMBER)));
        foreach ([CLAN_APPLICANT, CLAN_MEMBER, CLAN_OFFICER, CLAN_FOUNDER] as $rank) {
            self::assertNull(resurrection_char_cleanup_succession(3, $rank, null));
        }
        // A leader: the first remaining member is made leader unless already one; nobody left disbands the clan.
        self::assertSame(['promote', 7], resurrection_char_cleanup_succession(3, CLAN_LEADER, $member(CLAN_MEMBER)));
        self::assertSame(['promote', 7], resurrection_char_cleanup_succession(3, CLAN_LEADER, $member(CLAN_OFFICER)));
        self::assertSame(['keep'], resurrection_char_cleanup_succession(3, CLAN_LEADER, $member(CLAN_LEADER)));
        // 1.1.2's != CLAN_LEADER also rewrote a remaining founder to leader; carried unchanged and recorded.
        self::assertSame(['promote', 7], resurrection_char_cleanup_succession(3, CLAN_LEADER, $member(CLAN_FOUNDER)));
        self::assertSame(['disband'], resurrection_char_cleanup_succession(3, CLAN_LEADER, null));
    }

    public function testEmailTransportRefusesInvalidAddressesAndHeaderBreaks(): void
    {
        self::assertFalse(resurrection_send_email('', 'Subject', 'Body', 'postmaster@localhost.com'));
        self::assertFalse(resurrection_send_email('not an address', 'Subject', 'Body', 'postmaster@localhost.com'));
        self::assertFalse(resurrection_send_email("player@example.invalid\nBcc: x@example.invalid", 'Subject', 'Body', 'postmaster@localhost.com'));
        self::assertFalse(resurrection_send_email('player@example.invalid', "Subject\r\nBcc: x@example.invalid", 'Body', 'postmaster@localhost.com'));
        self::assertFalse(resurrection_send_email('player@example.invalid', 'Subject', 'Body', "postmaster@localhost.com\nBcc: x@example.invalid"));
    }

    public function testCleanupRefusesToRunOutsideTheDeletionTransaction(): void
    {
        $connection = new class { public function inTransaction(): bool { return false; } };
        $saved = $GLOBALS['dbinfo'] ?? null;
        $GLOBALS['dbinfo'] = ['connection' => $connection];
        try {
            char_cleanup(5, CHAR_DELETE_MANUAL);
            self::fail('Cleanup ran outside a transaction');
        } catch (\LogicException $error) {
            self::assertStringContainsString('transaction', $error->getMessage());
        } finally {
            $GLOBALS['dbinfo'] = $saved;
        }
    }
}
