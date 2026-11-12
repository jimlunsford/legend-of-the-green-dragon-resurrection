<?php
declare(strict_types=1);
namespace Resurrection\Tests;
use PHPUnit\Framework\TestCase;

/**
 * Account mail's messages (design sections 2.3 and 2.4; Phase 6 task 13a). Every account mail goes through
 * resurrection_send_email(), the game's one call of mail(); its links come only from an https:// serverurl, never the
 * request; its headers are fixed, and no player value reaches one. The messages are sent by a child PHP whose mail goes
 * to a capture (tests/support/mail_capture.php).
 *
 * It runs on the installed test database FreshInstallTest leaves, so its name sorts after FreshInstallTest's (the
 * design named it Account..., which would run before the install).
 */
final class MailMessageTest extends TestCase
{
    use AccountMailFixture;

    private const array FORGED = ['HTTP_HOST' => 'evil.example.net', 'SERVER_NAME' => 'evil.example.net', 'SERVER_PORT' => '8443',
        'HTTP_X_FORWARDED_HOST' => 'evil.example.net', 'HTTP_X_FORWARDED_PROTO' => 'https', 'REQUEST_URI' => '/evil.example.net/', 'REMOTE_ADDR' => '203.0.113.9'];

    public static function setUpBeforeClass(): void
    {
        self::loadAccountMail();
    }

    public function testLinksComeFromServerUrlOnly(): void
    {
        $this->connectAccountMail();
        self::ready();
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);', self::FORGED);
        self::assertSame('sent', $out);
        self::assertCount(1, $messages);
        [, $body] = self::parse($messages[0]);
        // Two addresses: the game's, and the link. Both are serverurl's.
        self::assertSame(2, preg_match_all('#[a-z]+://[^\s,]+#i', $body, $links));
        self::assertSame('https://game.example.org/', $links[0][0]);
        self::assertMatchesRegularExpression('#\Ahttps://game\.example\.org/account\.php\?op=open&t=[A-Za-z0-9_-]{43}\z#', $links[0][1]);
        foreach (['evil', '8443', '203.0.113.9'] as $forged) self::assertStringNotContainsString($forged, $messages[0]);
        // The link function itself, in this process, with the request forged the same way.
        $saved = $_SERVER;
        $_SERVER = array_replace($_SERVER, self::FORGED);
        try {
            $GLOBALS['settings'] = null;
            self::assertSame('https://game.example.org/account.php?op=open&t=' . str_repeat('A', 43), resurrection_account_mail_link(str_repeat('A', 43)));
            self::settings(['serverurl' => null]);
            self::assertNull(resurrection_account_mail_link(str_repeat('A', 43)));
            self::assertNull(resurrection_account_mail_base());
        } finally { $_SERVER = $saved; }
        // No account mail code reads the request's host.
        foreach (['lib/account_mail.php', 'lib/account_mail_admin.php', 'account.php', 'lib/systemmail.php', 'lib/char_expiration.php'] as $file) {
            self::assertDoesNotMatchRegularExpression('/\$_SERVER\s*\[\s*[\'"](?:HTTP_HOST|SERVER_NAME|SERVER_PORT|HTTP_X_FORWARDED_\w+)/', (string)file_get_contents($file), $file);
        }
    }

    public function testHttpServerUrlTurnsAccountMailOff(): void
    {
        $this->connectAccountMail();
        self::ready();
        self::settings(['serverurl' => 'http://game.example.org/']);
        self::assertNull(resurrection_account_mail_base());
        self::assertContains('serverurl', resurrection_account_mail_blockers());
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('not-ready', $out);
        self::assertSame([], $messages);
        self::assertSame([], db_query('SELECT tokenid FROM account_tokens'));
        foreach (['', 'game.example.org', 'ftp://game.example.org/', 'https://', 'https://game example.org/', 'HTTP://game.example.org/'] as $url) {
            self::settings(['serverurl' => $url]);
            self::assertNull(resurrection_account_mail_base(), $url);
        }
        self::settings(['serverurl' => 'HTTPS://game.example.org/']);
        self::assertSame('HTTPS://game.example.org/', resurrection_account_mail_base());
    }

    public function testFixedSubjectsAndHeaders(): void
    {
        $this->connectAccountMail();
        self::ready();
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('sent', $out);
        [$headers, $body] = self::parse($messages[0]);
        self::assertSame(['To' => 'admin@example.org', 'Subject' => 'Test mail from the game', 'From' => 'game@example.org', 'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => '8bit', 'Auto-Submitted' => 'auto-generated'], $headers);
        self::assertStringStartsWith('This is a test mail from Legend of the Green Dragon at https://game.example.org/, sent from Game Settings by the administrator FixtureAdmin.', $body);
        // Through the transport directly: a subject outside ASCII is one encoded word, the body is passed as it is.
        [$out, $messages] = $this->child('var_export(resurrection_send_email("player@example.org", "Prüfung", "Zeile ü\n", "game@example.org"));');
        self::assertSame('true', $out);
        [$headers, $body] = self::parse($messages[1]);
        self::assertSame('=?UTF-8?B?' . base64_encode('Prüfung') . '?=', $headers['Subject']);
        self::assertStringStartsWith("Zeile ü\n", $body);
        // A refusal by the transport is false, and the log and game log say so.
        [$out] = $this->child('echo resurrection_account_mail_test_send(1);', [], 1);
        self::assertSame('refused', $out);
        self::assertSame(['sent', 'refused'], array_column(db_query("SELECT result FROM account_mail_log WHERE purpose='mailtest' ORDER BY eventid"), 'result'));
        self::assertSame(['Account mail (mailtest) could not be handed to the mail transport.', 'Test mail refused by the mail transport'],
            array_column(db_query("SELECT message FROM gamelog WHERE category='account mail' AND (message LIKE '%refused%' OR message LIKE 'Account mail (mailtest)%') ORDER BY logid"), 'message'));
    }

    public function testNoPlayerValueInHeaders(): void
    {
        $this->connectAccountMail();
        // CR or LF in an address, the sender or the subject: nothing is handed to the transport.
        $cases = ['"player@example.org\r\nBcc: other@example.org", "Subject", "game@example.org"', '"player@example.org", "Subject\r\nBcc: other@example.org", "game@example.org"',
            '"player@example.org", "Subject\nBcc: other@example.org", "game@example.org"', '"player@example.org", "Subject\0", "game@example.org"',
            '"player@example.org", "Subject", "game@example.org\nBcc: other@example.org"', '"player@example.org, other@example.org", "Subject", "game@example.org"',
            '"Player <player@example.org>", "Subject", "game@example.org"', '"player@example.org", "Subject", "Game <game@example.org>"'];
        foreach ($cases as $arguments) {
            [$out, $messages] = $this->child('var_export(resurrection_send_email(' . $arguments . ', "Body"));');
            self::assertSame('false', $out, $arguments);
            self::assertSame([], $messages, $arguments);
        }
        // An account's address with a line break never reaches mail: the test mail finds no address.
        self::ready();
        db_query("UPDATE accounts SET emailaddress=? WHERE acctid=1", true, ["admin@example.org\nBcc: other@example.org"]);
        [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
        self::assertSame('no-address', $out);
        self::assertSame([], $messages);
        // A sender (Admin Email) with a line break, or the shipped default, is not a sender at all.
        db_query("UPDATE accounts SET emailaddress='admin@example.org' WHERE acctid=1");
        foreach (["game@example.org\nBcc: other@example.org", 'postmaster@localhost.com', 'POSTMASTER@localhost.com'] as $sender) {
            self::settings(['gameadminemail' => $sender]);
            self::assertNull(resurrection_account_mail_sender(), $sender);
            [$out, $messages] = $this->child('echo resurrection_account_mail_test_send(1);');
            self::assertSame('not-ready', $out);
            self::assertSame([], $messages);
        }
        // mail() is called in one place only, with the fixed headers: every call of the function mail in shipped PHP.
        $calls = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator('.', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = substr((string)$file, 2);
            if (!str_ends_with($path, '.php') || preg_match('#\A(vendor|tests|\.git)/#', $path)) continue;
            $tokens = array_values(array_filter(token_get_all((string)file_get_contents((string)$file)), static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
            foreach ($tokens as $i => $token) {
                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true) && ltrim(strtolower($token[1]), '\\') === 'mail' && ($tokens[$i + 1] ?? null) === '('
                    && !(is_array($tokens[$i - 1] ?? null) && in_array($tokens[$i - 1][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true))) {
                    $calls[$path] = ($calls[$path] ?? 0) + 1;
                }
            }
        }
        self::assertSame(['lib/systemmail.php' => 1], $calls);
        self::assertStringContainsString('@mail($address, $subject, $body, implode("\r\n", $headers))', (string)file_get_contents('lib/systemmail.php'));
    }
}
