"""Sign-in throttling over HTTP (Phase 6 task 18, F4; lib/signin_throttle.php, docs/PHASE6-TASK18-20261114.md).

The three limits (one name from one address, one address, one name from every address) through login.php: the same
"Too many attempts. Try again in N minutes." for each, no password checked while throttled, a signin_throttled row of
its own, X-Forwarded-For never read, and a successful sign-in clearing nothing. An unknown name and a wrong password
answer alike in status, headers, body and time (medians within a tolerance).

The server runs on a symlinked copy of the tree with its own configuration file, on the installed test database the
shard's install left, with mail going to a capture (mail_capture.py). The class moves aside the sign-in rows faillog
holds from earlier tests and puts them back, removes the accounts it makes and restores the settings it changes.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import statistics
import subprocess
import sys
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_runtime import supported_php  # noqa: E402
from loopback_server import start_php_server  # noqa: E402
import mail_capture  # noqa: E402
from test_account_mail_http import Client, text  # noqa: E402

PASSWORD = 'Synthetic throttle password'
SETTINGS = ('signin_fail_pair', 'signin_fail_address', 'signin_fail_account')
CSRF = re.compile(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})')
INCORRECT = 'Error, your login was incorrect'
THROTTLED = re.compile(r'Too many attempts\. Try again in (\d+) minutes?\.')
WORDS = ('invalid_credentials', 'signin_throttled')


def name_key(login):
    """faillog.id for a sign-in name: as lib/signin_throttle.php keys it."""
    return hashlib.sha256(b'signin:' + login.rstrip(' ').lower().encode()).hexdigest()[:32]


class SignInThrottleHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.php = supported_php()
        cls.work = tempfile.TemporaryDirectory(prefix='resurrection-signin-throttle-')
        work = Path(cls.work.name)
        cls.config = work / 'dbconnect.php'
        cls.config.write_text("<?php\n$DB_HOST = " + json.dumps(host) + ";\n$DB_USER = " + json.dumps(os.environ['RESURRECTION_TEST_DB_USER'])
                              + ";\n$DB_PASS = " + json.dumps(os.environ['RESURRECTION_TEST_DB_PASSWORD'])
                              + ";\n$DB_NAME = 'resurrection_test';\n$DB_PREFIX = '';\n$DB_USEDATACACHE = 0;\n$DB_DATACACHEPATH = '';\n")
        cls.tree = work / 'tree'
        cls.tree.mkdir()
        for entry in ROOT.iterdir():
            if entry.name not in {'dbconnect.php', '.git', 'resurrection-setup.php', 'resurrection-setup-failures.php'}:
                os.symlink(entry, cls.tree / entry.name)
        os.symlink(cls.config, cls.tree / 'dbconnect.php')
        cls.sessions = work / 'sessions'
        cls.sessions.mkdir()
        cls.error_log = work / 'php-errors.log'
        cls.error_log.touch()
        cls.mail = mail_capture.MailCapture(exit_code=0)
        arguments = [cls.php, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' + str(cls.error_log), '-d', 'error_reporting=-1',
                     '-d', 'session.save_path=' + str(cls.sessions), *cls.mail.args()]
        environment = {k: v for k, v in os.environ.items() if k != 'LOGD_DBCONNECT'}
        cls.server, cls.port = start_php_server(arguments, cls.tree, cls.tree, environment, work / 'server.out')
        cls.saved_settings = {row['setting']: row['value'] for row in cls.query(
            'SELECT setting,value FROM settings WHERE setting IN (' + ','.join('?' * len(SETTINGS)) + ')', list(SETTINGS))}
        cls.saved_rows = cls.query('SELECT date,post,ip,acctid,id FROM faillog WHERE post IN (?,?) ORDER BY eventid', list(WORDS))
        cls.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
        cls.fast = cls.php_eval("echo password_hash(" + json.dumps(PASSWORD) + ", PASSWORD_BCRYPT, ['cost' => 4]);")
        cls.real = cls.php_eval("echo password_hash(" + json.dumps(PASSWORD) + ", PASSWORD_DEFAULT);")
        cls.made = []

    @classmethod
    def tearDownClass(cls):
        try:
            for name in SETTINGS:
                cls.query('DELETE FROM settings WHERE setting=?', [name])
                if name in cls.saved_settings:
                    cls.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, cls.saved_settings[name]])
            cls.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
            for row in cls.saved_rows:
                cls.query('INSERT INTO faillog (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', [row['date'], row['post'], row['ip'], row['acctid'], row['id']])
        finally:
            cls.server.terminate()
            cls.server.wait(timeout=10)
            cls.mail.cleanup()
            cls.work.cleanup()

    def setUp(self):
        self.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
        for name, value in (('signin_fail_pair', '5'), ('signin_fail_address', '20'), ('signin_fail_account', '50')):
            self.setting(name, value)

    def tearDown(self):
        self.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
        if self.made:
            joined = ','.join(str(i) for i in self.made)
            self.query(f'DELETE FROM accounts_output WHERE acctid IN ({joined})')
            self.query(f'DELETE FROM accounts WHERE acctid IN ({joined})')
        self.made.clear()
        self.assertEqual('', self.error_log.read_text(), 'PHP logged an error')

    # Fixtures -------------------------------------------------------------------------------------------------------

    @classmethod
    def php_eval(cls, code):
        result = subprocess.run([cls.php, '-r', code], capture_output=True, text=True, timeout=60)
        if result.returncode != 0:
            raise AssertionError(result.stderr)
        return result.stdout

    @classmethod
    def query(cls, sql, parameters=()):
        code = ("chdir(getenv('ROOT')); define('RESURRECTION_ENTRY', true); require 'lib/dbwrapper_pdo.php'; require getenv('CONFIG');"
                " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME);"
                " $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);"
                " $rows=db_query($input[0],true,$input[1]); echo json_encode(is_array($rows)?$rows:[],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);")
        result = subprocess.run([cls.php, '-r', code], input=json.dumps([sql, list(parameters)]), capture_output=True, text=True,
                                timeout=60, env=dict(os.environ, ROOT=str(ROOT), CONFIG=str(cls.config)))
        if result.returncode != 0:
            raise AssertionError('Fixture query failed: ' + result.stderr[-1000:] + result.stdout[-1000:])
        return json.loads(result.stdout)

    def setting(self, name, value):
        self.query('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', [name, value, value])

    def account(self, login, password_hash):
        values = {'login': login, 'name': 'Farmboy ' + login, 'password': password_hash, 'emailaddress': '', 'emailverified': '',
                  'superuser': 0, 'locked': 0, 'emailvalidation': '', 'lastip': '192.0.2.10', 'uniqueid': hashlib.md5(login.encode()).hexdigest(),
                  'laston': '2026-11-12 00:00:00', 'regdate': '2026-11-12 00:00:00', 'restorepage': 'village.php', 'badguy': '',
                  'companions': 'a:0:{}', 'allowednavs': 'a:0:{}', 'specialmisc': '', 'bufflist': 'a:0:{}', 'dragonpoints': 'a:0:{}',
                  'prefs': 'a:0:{}', 'donationconfig': ''}
        self.query('INSERT INTO accounts (' + ','.join('`' + k + '`' for k in values) + ') VALUES (' + ','.join('?' * len(values)) + ')', list(values.values()))
        acctid = int(self.query('SELECT acctid FROM accounts WHERE login=?', [login])[0]['acctid'])
        self.query("INSERT INTO accounts_output (acctid,output) VALUES (?,'')", [acctid])
        self.made.append(acctid)
        return acctid

    def attempt(self, login, password, headers=None):
        """One sign-in from a new browser: (status, Location, the 303's body, the home page's message text, seconds)."""
        client = Client(self.port)
        _, _, home = client.request('home.php')
        started = time.monotonic()
        status, response, body = client.request('login.php', {'csrf_token': CSRF.search(home).group(1), 'name': login, 'password': password}, headers)
        elapsed = time.monotonic() - started
        message = ''
        if response.get('Location') == 'index.php':
            _, _, after = client.request('home.php')
            found = re.search(r'(Error, your login was incorrect|Too many attempts\. Try again in \d+ minutes?\.)', text(after))
            message = found.group(1) if found else text(after)[:600]
        return status, response.get('Location'), body, message, elapsed

    def rows(self, word=None):
        if word is None:
            return self.query('SELECT post,ip,acctid,id FROM faillog WHERE post IN (?,?) ORDER BY eventid', list(WORDS))
        return self.query('SELECT post,ip,acctid,id FROM faillog WHERE post=? ORDER BY eventid', [word])

    # Tests ----------------------------------------------------------------------------------------------------------

    def test_each_limit_gives_one_message_and_checks_no_password(self):
        player = self.account('ThrottlePlayer', self.fast)
        # One name from one address: five failures, then the right password is refused without being checked.
        for _ in range(5):
            self.assertEqual((303, 'index.php', '', INCORRECT), self.attempt('ThrottlePlayer', 'Not the password at all')[:4])
        status, location, body, message, _ = self.attempt('ThrottlePlayer', PASSWORD)
        self.assertEqual((303, 'index.php', ''), (status, location, body))
        self.assertRegex(message, THROTTLED)
        self.assertEqual('15', THROTTLED.search(message).group(1))
        # The password was not checked: a cost-4 hash would have been rehashed at PASSWORD_DEFAULT's cost on success.
        stored = self.query('SELECT password,loggedin FROM accounts WHERE acctid=?', [player])[0]
        self.assertEqual((self.fast, '0'), (stored['password'], str(stored['loggedin'])))
        self.assertEqual([{'post': 'signin_throttled', 'ip': '127.0.0.1', 'acctid': '0', 'id': name_key('ThrottlePlayer')}], self.rows('signin_throttled'))
        self.assertEqual(5, len(self.rows('invalid_credentials')), 'a throttled attempt is not a failure')
        # A wrong password, the name in other case, and a forwarded header naming another address: the same message.
        for login, headers in (('ThrottlePlayer', None), ('throttleplayer', None), ('ThrottlePlayer', {'X-Forwarded-For': '198.51.100.7'})):
            self.assertRegex(self.attempt(login, 'Not the password at all', headers)[3], THROTTLED)
        # Another name from this address is not held by the pair; an unknown name is held just the same.
        self.assertEqual(INCORRECT, self.attempt('NoSuchThrottleName', 'Not the password at all')[3])
        for _ in range(4):
            self.attempt('NoSuchThrottleName', 'Not the password at all')
        self.assertRegex(self.attempt('NoSuchThrottleName', 'Not the password at all')[3], THROTTLED)

        # One address whatever the name: at its lowest bound, 5.
        self.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
        self.setting('signin_fail_address', '5')
        for i in range(5):
            self.assertEqual(INCORRECT, self.attempt('Spread' + str(i), 'Not the password at all')[3])
        self.assertRegex(self.attempt('ThrottlePlayer', PASSWORD)[3], THROTTLED)
        self.assertRegex(self.attempt('Spread9', 'Not the password at all')[3], THROTTLED)

        # One name from every address: 50 failures from other addresses hold it here, on its first attempt.
        self.query('DELETE FROM faillog WHERE post IN (?,?)', list(WORDS))
        self.setting('signin_fail_address', '20')
        stamp = self.php_eval("echo date('Y-m-d H:i:s');")  # the clock and zone the game writes faillog dates with
        for i in range(50):
            self.query('INSERT INTO faillog (date,post,ip,acctid,id) VALUES (?,?,?,?,?)', [stamp, 'invalid_credentials', '198.51.100.' + str(i + 1), 0, name_key('ThrottlePlayer')])
        status, location, body, message, _ = self.attempt('ThrottlePlayer', PASSWORD)
        self.assertEqual((303, 'index.php', ''), (status, location, body))
        self.assertRegex(message, THROTTLED)
        self.assertIn(THROTTLED.search(message).group(1), ('59', '60'))
        self.assertEqual(self.fast, self.query('SELECT password FROM accounts WHERE acctid=?', [player])[0]['password'])
        self.assertEqual(INCORRECT, self.attempt('AnotherName', 'Not the password at all')[3])

    def test_a_successful_sign_in_clears_nothing(self):
        self.account('FreePlayer', self.fast)
        for i in range(4):
            self.attempt('Wander' + str(i), 'Not the password at all')
        self.attempt('FreePlayer', 'Not the password at all')
        before = self.rows()
        status, location = self.attempt('FreePlayer', PASSWORD)[:2]
        self.assertEqual((303, 'village.php'), (status, location))
        self.assertEqual(before, self.rows(), 'a sign-in leaves the failures where they were')
        self.assertEqual(5, len(before))

    def test_an_unknown_name_and_a_wrong_password_look_the_same(self):
        self.account('TimingPlayer', self.real)
        answers = {'wrong': [], 'unknown': []}
        for _ in range(4):
            for case, login in (('wrong', 'TimingPlayer'), ('unknown', 'NoSuchTimingPlayer')):
                answers[case].append(self.attempt(login, 'Not the password at all'))
        for case, results in answers.items():
            for status, location, body, message, _ in results:
                self.assertEqual((303, 'index.php', '', INCORRECT), (status, location, body, message), case)
        # The same rows: the fixed word, the address, no account id, the name's key.
        self.assertEqual({('invalid_credentials', '127.0.0.1', '0')}, {(r['post'], r['ip'], str(r['acctid'])) for r in self.rows()})
        self.assertEqual({name_key('TimingPlayer'), name_key('NoSuchTimingPlayer')}, {r['id'] for r in self.rows()})
        # The time: medians within a third of each other. Before task 18 an unknown name skipped the password check
        # and answered in a small part of a wrong password's time.
        wrong = statistics.median(result[4] for result in answers['wrong'])
        unknown = statistics.median(result[4] for result in answers['unknown'])
        self.assertGreaterEqual(min(wrong, unknown) / max(wrong, unknown), 0.67, f'wrong {wrong:.3f} s, unknown {unknown:.3f} s')
        # The headers of the 303 are the same, less the date.
        client = Client(self.port)
        _, _, home = client.request('home.php')
        token = CSRF.search(home).group(1)
        heads = []
        for login in ('TimingPlayer', 'NoSuchTimingPlayer'):
            _, headers, _ = client.request('login.php', {'csrf_token': token, 'name': login, 'password': 'Not the password at all'})
            heads.append(sorted((k, v) for k, v in headers.items() if k.lower() not in {'date'}))
        self.assertEqual(heads[0], heads[1])


if __name__ == '__main__':
    unittest.main()
