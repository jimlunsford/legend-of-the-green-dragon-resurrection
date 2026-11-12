"""Account mail over HTTP (Phase 6 task 13a, S11 part 1; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, sections
2.5, 3.3 and 10): Game Settings' Account mail section and account.php, the page a link in an account mail opens.

The server runs on a symlinked copy of the tree with its own configuration file (as test_web_ops_http.py does), on the
installed test database the shard's install left, with mail going to a capture that accepts it (mail_capture.py):
nothing leaves the machine, and the test reads each message and its link from the capture. The class saves every
setting and account field it changes and puts them back at the end; account mail's two tables end empty, as the
install left them.
"""
from contextlib import contextmanager
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_runtime import supported_php  # noqa: E402
from loopback_server import start_php_server  # noqa: E402
import mail_capture  # noqa: E402

CSP = ("default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; "
       "base-uri 'self'; form-action 'self'; frame-ancestors 'self'")
COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team'
ATTRIBUTION = ('A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by '
               '<a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a>')
ERROR = re.compile(r'(?i)fatal error|runtime error|warning:|notice:|deprecated:|uncaught')
INLINE = re.compile(r'<script(?![^>]*\bsrc=)|<style|\sstyle=|\son[a-z]+=|javascript:', re.I)
ADMIN, ADMIN_PASSWORD = 'FixtureAdmin', 'Synthetic administrator password'
SERVER_URL, SENDER, ADDRESS = 'https://game.example.org/', 'game@example.org', 'fixtureadmin@example.org'
EXPIRED = 'This link has expired or was already used. Ask for a new one.'
LINK = re.compile(r'https://game\.example\.org/account\.php\?op=open&t=([A-Za-z0-9_-]{43})')
SETTINGS = ('serverurl', 'gameadminemail', 'account_mail', 'account_mail_proof', 'account_mail_pending', 'account_mail_hourly')


def text(body):
    body = re.sub(r'(?s)<!--.*?-->|<script.*?</script>', '', body)
    return html.unescape(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', body)))


class Client:
    """One browser: its own cookies; requests return (status, headers, body text). Redirects are not followed."""

    def __init__(self, port):
        self.port = port
        self.jar = http.cookiejar.CookieJar()
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect)

    def request(self, path, data=None, headers=None):
        request = urllib.request.Request(f'http://127.0.0.1:{self.port}/{path}', headers=dict(headers or {}),
                                         data=None if data is None else urllib.parse.urlencode(data).encode())
        try:
            response = self.opener.open(request, timeout=60)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.headers, response.read().decode('utf-8', errors='replace')


class AccountMailHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.php = supported_php()
        cls.work = tempfile.TemporaryDirectory(prefix='resurrection-account-mail-')
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
        cls.admin = int(cls.query('SELECT acctid FROM accounts WHERE login=?', [ADMIN])[0]['acctid'])
        cls.saved_settings = {row['setting']: row['value'] for row in cls.query(
            'SELECT setting,value FROM settings WHERE setting IN (' + ','.join('?' * len(SETTINGS)) + ')', list(SETTINGS))}
        cls.saved_account = cls.query('SELECT emailaddress,emailverified FROM accounts WHERE acctid=?', [cls.admin])[0]
        if cls.query('SELECT tokenid FROM account_tokens') or cls.query('SELECT eventid FROM account_mail_log'):
            raise RuntimeError('Fixture invalid: account mail tables are not empty')

    @classmethod
    def tearDownClass(cls):
        try:
            for name in SETTINGS:
                cls.query('DELETE FROM settings WHERE setting=?', [name])
                if name in cls.saved_settings:
                    cls.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, cls.saved_settings[name]])
            cls.query('UPDATE accounts SET emailaddress=?,emailverified=? WHERE acctid=?',
                      [cls.saved_account['emailaddress'], cls.saved_account['emailverified'], cls.admin])
            cls.query('DELETE FROM account_tokens')
            cls.query('DELETE FROM account_mail_log')
        finally:
            cls.server.terminate()
            cls.server.wait(timeout=10)
            cls.mail.cleanup()
            cls.work.cleanup()

    def setUp(self):
        self.ready()
        self.mail.clear()

    # Fixtures -------------------------------------------------------------------------------------------------------

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
        self.query('DELETE FROM settings WHERE setting=?', [name])
        if value is not None:
            self.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, str(value)])

    def ready(self):
        """A game that can send a test mail and has none confirmed: https serverurl, a real sender, the admin's address."""
        for name, value in [('serverurl', SERVER_URL), ('gameadminemail', SENDER), ('account_mail', '0'), ('account_mail_proof', None),
                            ('account_mail_pending', None), ('account_mail_hourly', '100')]:
            self.setting(name, value)
        self.query("UPDATE accounts SET emailaddress=?,emailverified='' WHERE acctid=?", [ADDRESS, self.admin])
        self.query('DELETE FROM account_tokens')
        self.query('DELETE FROM account_mail_log')

    def allow(self, url):
        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?', ['a:1:{s:' + str(len(url)) + ':"' + url + '";b:1;}', self.admin])

    def signed_in(self):
        client = Client(self.port)
        _, _, body = client.request('home.php')
        csrf = re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})', body).group(1)
        status, _, _ = client.request('login.php', {'csrf_token': csrf, 'name': ADMIN, 'password': ADMIN_PASSWORD})
        self.assertEqual(303, status)
        return client

    def section(self, client, notice_from=None):
        """The Account mail section: (status, headers, body)."""
        self.allow('configuration.php?op=accountmail')
        return client.request('configuration.php?op=accountmail')

    def post(self, client, op, body, headers=None, fields=None):
        """POST one of the section's forms with the tokens from `body`, the page that showed it."""
        form = re.search(r'<form class="rz-inline" action="configuration\.php\?op=[a-z_]+" method="POST">(.*?)</form>', body, re.S).group(1)
        data = {name: re.search(r'name="' + name + r'" value="([a-f0-9]{64})"', form).group(1) for name in ('csrf_token', 'action_token')}
        data.update(fields or {})
        self.allow('configuration.php?op=' + op)
        return client.request('configuration.php?op=' + op, data, headers)

    def assertPage(self, status, headers, body, expected=200):
        """A game page: the status, the enforced policy, no inline script or style, no PHP error, the credits."""
        self.assertEqual(expected, status, text(body)[:1500])
        self.assertEqual(CSP, headers['Content-Security-Policy'])
        self.assertNotRegex(body, ERROR)
        self.assertIsNone(INLINE.search(body), INLINE.search(body) and body[INLINE.search(body).start() - 200:][:400])
        self.assertEqual(1, body.count(COPYRIGHT))
        self.assertEqual(1, body.count(ATTRIBUTION))

    def assertAccountHeaders(self, headers):
        self.assertEqual(['no-referrer'], headers.get_all('Referrer-Policy'))
        self.assertEqual(['no-store'], headers.get_all('Cache-Control'))
        self.assertEqual('noindex', headers['X-Robots-Tag'])

    def assertExpired(self, answer, label):
        status, headers, body = answer
        self.assertPage(status, headers, body, 400)
        self.assertAccountHeaders(headers)
        self.assertIn(EXPIRED, text(body), label)
        # The page as it is, less the footer's clock and timings (the query count is kept).
        body = re.sub(r'Next new day in [^<]*', 'Next new day in N', body)
        body = re.sub(r'\?c=\d+-\d+', '?c=N', body)
        return re.sub(r'Page gen: [0-9.]+s / (\d+) queries \([0-9.]+s\), Ave: [0-9.]+s - [0-9.]+/\d+', r'\1 queries', body)

    def send_test_mail(self, client, headers=None):
        """Send a test mail from the section; returns its token, read from the captured message."""
        _, _, body = self.section(client)
        before = len(self.mail.raw())
        status, headers_, page = self.post(client, 'accountmail_test', body, headers)
        self.assertPage(status, headers_, page)
        self.assertIn('The test mail was handed to the mail transport.', text(page))
        messages = self.mail.raw()
        self.assertEqual(before + 1, len(messages))
        return LINK.search(messages[-1]).group(1), page

    # Tests ----------------------------------------------------------------------------------------------------------

    def test_test_mail_round_trip_turns_account_mail_on(self):
        admin = self.signed_in()
        status, headers, body = self.section(admin)
        self.assertPage(status, headers, body)
        self.assertEqual(['no-store'], headers.get_all('Cache-Control'))
        page = text(body)
        for line in ['Account mail Off', 'Server URL https://game.example.org/ (usable for account mail)', 'Sender (Admin Email) game@example.org (usable)',
                     'Your address ' + ADDRESS, 'Last test mail none', 'Confirmed test mail none', 'No test mail has been confirmed yet.']:
            self.assertIn(line, page)
        self.assertNotIn('Turn account mail on', page)
        # The switch is refused until a test mail is confirmed, even posted by hand.
        status, headers, refused = self.post(admin, 'accountmail_on', body)
        self.assertPage(status, headers, refused)
        self.assertIn('Account mail was not turned on: see the conditions below.', text(refused))
        self.assertEqual([{'value': '0'}], self.query("SELECT value FROM settings WHERE setting='account_mail'"))
        # Without the CSRF token nothing is sent.
        _, _, body = self.section(admin)
        self.allow('configuration.php?op=accountmail_test')
        self.assertEqual(403, admin.request('configuration.php?op=accountmail_test', {'action_token': 'x'})[0])
        self.assertEqual([], self.mail.raw())
        token, sent = self.send_test_mail(admin)
        # The same form posted again: its action token is used, so nothing more is sent.
        _, _, body = self.section(admin)
        self.assertEqual(200, self.post(admin, 'accountmail_test', body)[0])
        self.assertEqual(409, self.post(admin, 'accountmail_test', body)[0])
        self.assertEqual(2, len(self.mail.raw()))
        token = LINK.search(self.mail.raw()[-1]).group(1)
        # Its message: to the admin's own address, from the sender, the link from serverurl only.
        message = self.mail.messages()[-1]
        self.assertEqual((ADDRESS, SENDER, 'Test mail from the game', 'auto-generated'), (message['To'], message['From'], message['Subject'], message['Auto-Submitted']))
        self.assertEqual(1, message.get_content().count('https://game.example.org/account.php?op=open&t=' + token))
        self.assertIn('Last test mail ', text(sent))
        self.assertIn('handed to the mail transport', text(sent))
        # The link, opened in another browser: a 303 to the clean address with no body, then the confirm page.
        reader = Client(self.port)
        status, headers, opened = reader.request('account.php?op=open&t=' + token)
        self.assertEqual((303, 'account.php?op=confirm', ''), (status, headers['Location'], opened))
        self.assertAccountHeaders(headers)
        status, headers, confirm = reader.request('account.php?op=confirm')
        self.assertPage(status, headers, confirm)
        self.assertAccountHeaders(headers)
        self.assertIn('Confirm the test mail', text(confirm))
        self.assertIn('the account ' + ADMIN, text(confirm))
        self.assertNotIn(token, confirm)
        self.assertEqual([{'emailverified': ''}], self.query('SELECT emailverified FROM accounts WHERE acctid=?', [self.admin]))
        # Confirm is a POST with the CSRF token; without it nothing changes.
        self.assertEqual(403, reader.request('account.php?op=confirm', {})[0])
        csrf = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', confirm).group(1)
        status, headers, done = reader.request('account.php?op=confirm', {'csrf_token': csrf})
        self.assertPage(status, headers, done)
        self.assertAccountHeaders(headers)
        self.assertIn('Test mail confirmed', text(done))
        self.assertEqual([{'emailverified': ADDRESS}], self.query('SELECT emailverified FROM accounts WHERE acctid=?', [self.admin]))
        # Used up: the link and the grant are dead.
        self.assertExpired(reader.request('account.php?op=open&t=' + token), 'used link')
        self.assertExpired(reader.request('account.php?op=confirm'), 'used grant')
        # Now the switch is offered and goes on; changing serverurl turns it off in effect; off is always allowed.
        _, _, body = self.section(admin)
        self.assertIn('Confirmed test mail ', text(body))
        self.assertIn('by ' + ADMIN, text(body))
        self.assertIn('Turn account mail on', text(body))
        status, headers, on = self.post(admin, 'accountmail_on', body)
        self.assertPage(status, headers, on)
        self.assertIn('Account mail is on.', text(on))
        self.assertIn('Account mail On', text(on))
        self.setting('serverurl', 'https://other.example.org/')
        _, _, body = self.section(admin)
        self.assertIn('Switched on, but not in effect (see below)', text(body))
        self.assertIn('The Server URL or Admin Email changed since the last confirmed test mail.', text(body))
        status, headers, off = self.post(admin, 'accountmail_off', body)
        self.assertIn('Account mail is off.', text(off))
        self.assertEqual([{'value': '0'}], self.query("SELECT value FROM settings WHERE setting='account_mail'"))
        self.assertEqual(['Turned on account mail', 'Turned off account mail'],
                         [row['message'] for row in self.query("SELECT message FROM gamelog WHERE category='settings' AND message LIKE 'Turned % account mail' ORDER BY logid DESC LIMIT 2")][::-1])
        # The token is kept nowhere: no table holds it (only its hash, in account_tokens), nor any session file, the PHP
        # error log, a kept page or the referer record.
        found = self.query_search([token])
        self.assertEqual({}, found)
        self.assertEqual(1, len(self.query('SELECT tokenid FROM account_tokens WHERE tokenhash=SHA2(?,256)', [token])))
        for path in self.sessions.iterdir():
            self.assertNotIn(token, path.read_text(errors='replace'), path.name)
        self.assertNotIn(token, self.error_log.read_text(errors='replace'))
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def query_search(self, needles):
        """Every table and column whose stored text holds one of the needles: {needle: ['table.column', ...]}."""
        code = ("chdir(getenv('ROOT')); define('RESURRECTION_ENTRY', true); require 'lib/dbwrapper_pdo.php'; require getenv('CONFIG');"
                " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); $needles=json_decode(stream_get_contents(STDIN),true); $found=[];"
                " foreach (db_query('SHOW TABLES') as $t) { $t=(string)array_values($t)[0]; foreach (db_query('SELECT * FROM '.db_identifier($t)) as $row) {"
                " foreach ($row as $column=>$value) { foreach ($needles as $n) { if (is_string($value) && str_contains($value,$n)) $found[$n][]=$t.'.'.$column; } } } }"
                " echo json_encode((object)$found);")
        result = subprocess.run([self.php, '-r', code], input=json.dumps(needles), capture_output=True, text=True, timeout=120,
                                env=dict(os.environ, ROOT=str(ROOT), CONFIG=str(self.config)))
        self.assertEqual(0, result.returncode, result.stderr[-2000:])
        return json.loads(result.stdout or '{}')

    def test_forged_host_never_reaches_a_mail(self):
        admin = self.signed_in()
        forged = {'Host': 'evil.example.net:8443', 'X-Forwarded-Host': 'evil.example.net', 'X-Forwarded-Proto': 'https',
                  'X-Forwarded-For': '203.0.113.9'}
        token, _ = self.send_test_mail(admin, forged)
        message = self.mail.raw()[-1]
        self.assertNotIn('evil', message)
        self.assertNotIn('8443', message)
        for link in re.findall(r'[a-z]+://[^\s,]+', message, re.I):
            self.assertTrue(link.startswith(SERVER_URL), link)
        # The link opened with a forged Host still goes to the clean, relative address.
        status, headers, _ = Client(self.port).request('account.php?op=open&t=' + token, headers=forged)
        self.assertEqual((303, 'account.php?op=confirm'), (status, headers['Location']))
        # With serverurl plain http, nothing is sent at all.
        self.setting('serverurl', 'http://game.example.org/')
        _, _, body = self.section(admin)
        self.assertIn('not usable: it must be a valid https:// address', text(body))
        before = len(self.mail.raw())
        status, headers, page = self.post(admin, 'accountmail_test', body, forged)
        self.assertPage(status, headers, page)
        self.assertIn('No test mail was sent: the Server URL must be a valid https:// address', text(page))
        self.assertEqual(before, len(self.mail.raw()))

    def test_every_unusable_link_gets_the_same_page(self):
        admin = self.signed_in()
        used, _ = self.send_test_mail(admin)
        self.query('UPDATE account_tokens SET used=NOW() WHERE tokenhash=SHA2(?,256)', [used])
        expired, _ = self.send_test_mail(admin)
        self.query('UPDATE account_tokens SET expires=? WHERE tokenhash=SHA2(?,256)', ['2000-01-01 00:00:00', expired])
        opened, _ = self.send_test_mail(admin)
        self.query('UPDATE account_tokens SET opened=5 WHERE tokenhash=SHA2(?,256)', [opened])
        moved, _ = self.send_test_mail(admin)
        # As if the account signed in since the mail: its authversion is no longer the token's.
        self.query('UPDATE account_tokens SET authversion=authversion+1 WHERE tokenhash=SHA2(?,256)', [moved])
        live, _ = self.send_test_mail(admin)
        cases = {'no token': 'account.php?op=open', 'empty token': 'account.php?op=open&t=', 'short token': 'account.php?op=open&t=abc',
                 'long token': 'account.php?op=open&t=' + 'A' * 44, 'token of the right shape': 'account.php?op=open&t=' + 'A' * 43,
                 'an array': 'account.php?op=open&t[]=' + 'A' * 43, 'the stored hash': 'account.php?op=open&t=' + 'a' * 64,
                 'used': 'account.php?op=open&t=' + used, 'expired': 'account.php?op=open&t=' + expired, 'opened five times': 'account.php?op=open&t=' + opened,
                 'signed in since': 'account.php?op=open&t=' + moved, 'no op': 'account.php', 'another op': 'account.php?op=reset&t=' + live,
                 'confirm without a grant': 'account.php?op=confirm'}
        bodies = {label: self.assertExpired(Client(self.port).request(path), label) for label, path in cases.items()}
        # A POST to op=open is refused too.
        bodies['POST open'] = self.assertExpired(Client(self.port).request('account.php?op=open&t=' + live, {'x': '1'}), 'POST open')
        # One page for every reason. A link of a token's shape is looked up and refused the same way whatever is wrong
        # with it, to the footer's query count; the others differ from it only in that count.
        shaped = {label: bodies[label] for label in ['token of the right shape', 'used', 'expired', 'opened five times', 'signed in since']}
        self.assertEqual(1, len(set(shaped.values())), {label: re.findall(r'\d+ queries', body) for label, body in shaped.items()})
        uncounted = {re.sub(r'\d+ queries', 'N queries', body) for body in bodies.values()}
        self.assertEqual(1, len(uncounted), {label: text(body)[:300] for label, body in bodies.items()})
        # Every opening counted, none of these opened a token, and no grant was made.
        self.assertEqual(len(cases) - 3 + 1, len(self.query("SELECT eventid FROM account_mail_log WHERE purpose='open'")))
        self.assertEqual(['0', '0', '5', '0'], [row['opened'] for row in self.query(
            'SELECT opened FROM account_tokens WHERE tokenhash IN (SHA2(?,256),SHA2(?,256),SHA2(?,256),SHA2(?,256)) ORDER BY tokenid', [used, expired, opened, moved])])
        # The opening limit: 30 an hour from one address, then every link gets the same page, the live one included.
        counted = len(self.query("SELECT eventid FROM account_mail_log WHERE purpose='open'"))
        self.query('INSERT INTO account_mail_log (date,purpose,acctid,addresskey,requesterkey,result) VALUES '
                   + ','.join(["(NOW(),'open',0,'','127.0.0.1','counted')"] * (30 - counted)))
        limited = self.assertExpired(Client(self.port).request('account.php?op=open&t=' + live), 'limited')
        self.assertEqual(uncounted, {re.sub(r'\d+ queries', 'N queries', limited)})
        self.assertEqual(['limited'], [row['result'] for row in self.query("SELECT result FROM account_mail_log WHERE purpose='open' AND result<>'counted'")])
        # The live link was never used up by any of this: with the limit's hour gone, it opens.
        self.query("DELETE FROM account_mail_log WHERE purpose='open'")
        status, headers, _ = Client(self.port).request('account.php?op=open&t=' + live)
        self.assertEqual((303, 'account.php?op=confirm'), (status, headers['Location']))


if __name__ == '__main__':
    unittest.main()
