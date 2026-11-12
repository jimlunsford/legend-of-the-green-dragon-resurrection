<?php
declare(strict_types=1);

namespace Resurrection\Tests;

/**
 * The account mail tests' shared fixture (Phase 6 task 13a). They run on the installed test database: the
 * administrator account (acctid 1) gets an address, the settings account mail reads are set per test, and tearDown()
 * puts back every one of them and empties account mail's two tables, which a fresh install leaves empty. A message is
 * "sent" by a child PHP whose sendmail_path is tests/support/mail_capture.php: what PHP's mail() hands the transport is
 * written to a file, and nothing leaves the machine.
 */
trait AccountMailFixture
{
    /** @var array<string, string|null> setting => stored value (null: no row) */
    private array $savedSettings = [];
    /** @var array<string, mixed> */
    private array $savedAccount = [];
    private ?string $capture = null;

    private const array FIXTURE_SETTINGS = ['serverurl', 'gameadminemail', 'account_mail', 'account_mail_proof', 'account_mail_pending', 'account_mail_hourly'];

    private static function loadAccountMail(): void
    {
        require_once 'lib/names.php';
        ob_start();
        foreach (['constants', 'datacache', 'settings', 'datetime', 'modules', 'output', 'sanitize', 'translator', 'account_mail'] as $library) {
            require_once 'lib/' . $library . '.php';
        }
        ob_end_clean();
    }

    private function connectAccountMail(): void
    {
        $host = getenv('RESURRECTION_TEST_DB_HOST');
        if ($host === false) { self::markTestSkipped('Requires disposable CI database.'); }
        self::assertSame('resurrection_test', getenv('RESURRECTION_TEST_DB_NAME'));
        $GLOBALS['DB_PREFIX'] = '';
        $GLOBALS['DB_USEDATACACHE'] = 0;
        $GLOBALS['DB_DATACACHEPATH'] = '';
        $GLOBALS['settings'] = null;
        $GLOBALS['session'] = ['user' => ['acctid' => 0]];
        self::assertTrue(db_connect($host, (string)getenv('RESURRECTION_TEST_DB_USER'), (string)getenv('RESURRECTION_TEST_DB_PASSWORD')));
        self::assertTrue(db_select_db('resurrection_test'));
        self::assertSame([], db_query('SELECT tokenid FROM account_tokens'), 'Fixture invalid: account_tokens is not empty.');
        self::assertSame([], db_query('SELECT eventid FROM account_mail_log'), 'Fixture invalid: account_mail_log is not empty.');
        foreach (self::FIXTURE_SETTINGS as $name) {
            $rows = db_query('SELECT value FROM settings WHERE setting=?', true, [$name]);
            $this->savedSettings[$name] = $rows === [] ? null : (string)$rows[0]['value'];
        }
        $this->savedAccount = db_query('SELECT emailaddress,emailverified,authversion FROM accounts WHERE acctid=1')[0];
    }

    /** Set (or with null, remove) settings, and drop the settings cache. */
    private static function settings(array $values): void
    {
        foreach ($values as $name => $value) {
            if ($value === null) db_query('DELETE FROM settings WHERE setting=?', true, [$name]);
            else db_query('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true, [$name, (string)$value, (string)$value]);
        }
        $GLOBALS['settings'] = null;
    }

    /** The settings a test mail can go out with. */
    private static function ready(): void
    {
        self::settings(['serverurl' => 'https://game.example.org/', 'gameadminemail' => 'game@example.org', 'account_mail_hourly' => 100]);
        db_query("UPDATE accounts SET emailaddress='admin@example.org' WHERE acctid=1");
    }

    protected function tearDown(): void
    {
        $this->restoreAccountMailFixture();
        parent::tearDown();
    }

    private function restoreAccountMailFixture(): void
    {
        if ($this->savedSettings !== []) {
            self::settings($this->savedSettings);
            db_query('UPDATE accounts SET emailaddress=?,emailverified=?,authversion=? WHERE acctid=1', true,
                [$this->savedAccount['emailaddress'], $this->savedAccount['emailverified'], $this->savedAccount['authversion']]);
            db_query('DELETE FROM account_tokens');
            db_query('DELETE FROM account_mail_log');
            db_query("DELETE FROM gamelog WHERE category='account mail'");
            $this->savedSettings = [];
        }
        if ($this->capture !== null && file_exists($this->capture)) unlink($this->capture);
        $this->capture = null;
        $_SESSION = [];
    }

    /**
     * Run PHP code in a child process with the game's libraries loaded, connected to the test database, the given
     * $_SERVER values, and mail going to a capture that answers $exit. Returns [stdout, the captured messages].
     * @param array<string, string> $server
     * @return array{0: string, 1: list<string>}
     */
    private function child(string $code, array $server = [], int $exit = 0): array
    {
        $this->capture ??= (string)tempnam(sys_get_temp_dir(), 'resurrection-mail-capture-');
        $transport = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(dirname(__DIR__) . '/support/mail_capture.php') . ' ' . escapeshellarg($this->capture) . ' ' . $exit;
        $boot = 'chdir(' . var_export(dirname(__DIR__, 2), true) . '); $DB_PREFIX = ""; $DB_USEDATACACHE = 0; $DB_DATACACHEPATH = ""; $REQUEST_URI = "";'
            . ' $session = ["user" => ["acctid" => 0]];'
            . ' require "lib/names.php"; ob_start();'
            . ' foreach (["constants","datacache","settings","datetime","modules","output","sanitize","translator","account_mail"] as $l) require_once "lib/" . $l . ".php";'
            . ' ob_end_clean();'
            . ' db_connect(getenv("RESURRECTION_TEST_DB_HOST"), getenv("RESURRECTION_TEST_DB_USER"), getenv("RESURRECTION_TEST_DB_PASSWORD")); db_select_db("resurrection_test");'
            . ' $_SERVER = array_replace($_SERVER, json_decode($argv[1], true));';
        $process = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . $transport, '-r', $boot . $code, json_encode($server, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = (string)stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $out . $err);
        self::assertSame('', $err);
        $GLOBALS['settings'] = null;
        return [$out, self::captured($this->capture)];
    }

    /** @return list<string> each message as PHP's mail() handed it to the transport */
    private static function captured(string $file): array
    {
        if (!file_exists($file)) return [];
        $messages = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $messages[] = (string)json_decode($line, true, 512, JSON_THROW_ON_ERROR)['message'];
        }
        return $messages;
    }

    /** A captured message's headers (name => value) and body. @return array{0: array<string, string>, 1: string} */
    private static function parse(string $message): array
    {
        $message = str_replace("\r\n", "\n", $message);
        [$head, $body] = explode("\n\n", $message, 2) + [1 => ''];
        $headers = [];
        foreach (explode("\n", $head) as $line) {
            [$name, $value] = explode(':', $line, 2) + [1 => ''];
            self::assertArrayNotHasKey($name, $headers, 'A header appears twice: ' . $name);
            $headers[$name] = ltrim($value);
        }
        return [$headers, $body];
    }
}
