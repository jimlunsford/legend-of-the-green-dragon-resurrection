"""Password recovery over HTTP (Phase 6 task 13b, S11 part 2; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md, sections
3.1 to 3.5, 5, 6 and 10, with decisions 2 and 3): account.php's forgot, open and reset, the sign-in page's link, and the
staff switch in Game Settings.

As test_account_mail_http.py, the server runs on a symlinked copy of the tree with its own configuration file, on the
installed test database the shard's install left, with mail going to a capture (mail_capture.py): nothing leaves the
machine. The built-in server has no fastcgi_finish_request(), so every forgot-password response here is held to the
1.5-second floor (the early-finish path is MailRecoveryTest's). Every account the class makes is removed, and every
setting it changes is put back; account mail's two tables end empty, as the install left them.
"""
import hashlib
import html
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import threading
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_runtime import supported_php  # noqa: E402
from loopback_server import start_php_server  # noqa: E402
import mail_capture  # noqa: E402
from test_account_mail_http import (ADMIN, ADMIN_PASSWORD, ATTRIBUTION, COPYRIGHT, CSP, ERROR, EXPIRED, INLINE, LINK, SENDER, SERVER_URL,  # noqa: E402
                                    Client, text)

PASSWORD = 'Synthetic recovery password'
ANSWER = ('If an account matches and can receive mail here, a message with a link is on its way. It works for 30 minutes. '
          'Check your spam folder too.')
OFF = ('Password recovery by email is not available on this game. Use Petition for Help to ask an administrator to reset '
       'your password.')
FLOOR = 1.5
SETTINGS = ('serverurl', 'gameadminemail', 'account_mail', 'account_mail_proof', 'account_mail_pending', 'account_mail_hourly',
            'account_mail_staff')
CSRF = re.compile(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})')


def same_page(body):
    """A forgot-password response less what differs by session and clock: the CSRF value and the links' c=N-HHMMSS."""
    body = re.sub(r'(name=[\'"]csrf_token[\'"] value=[\'"])[a-f0-9]{64}', r'\1CSRF', body)
    return re.sub(r'([?&](?:amp;)?c=)\d+-\d{6}', r'\1N', body)


def address_key(address):
    return hashlib.sha256(address.strip().lower().encode()).hexdigest()


class Forms(HTMLParser):
    """The fields a browser submits for each form: checked, enabled boxes; each select's chosen option."""

    def __init__(self):
        super().__init__()
        self.forms, self.current, self.select, self.textarea = [], None, None, None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.current = [html.unescape(a.get('action', '')), []]
            return
        if self.current is None or 'disabled' in a:
            return
        if tag == 'input' and 'name' in a and a.get('type', 'text').lower() not in ('submit', 'button'):
            if a.get('type', '').lower() == 'checkbox' and 'checked' not in a:
                return
            self.current[1].append((a['name'], a.get('value') or ''))
        elif tag == 'select':
            self.select = [a['name'], None, None]
        elif tag == 'option' and self.select is not None:
            if self.select[1] is None:
                self.select[1] = a.get('value', '')
            if 'selected' in a:
                self.select[2] = a.get('value', '')
        elif tag == 'textarea':
            self.textarea = [a['name'], '']

    def handle_data(self, data):
        if self.textarea is not None:
            self.textarea[1] += data

    def handle_endtag(self, tag):
        if tag == 'select' and self.select is not None and self.current is not None:
            self.current[1].append((self.select[0], self.select[2] if self.select[2] is not None else self.select[1]))
            self.select = None
        elif tag == 'textarea' and self.textarea is not None and self.current is not None:
            self.current[1].append(tuple(self.textarea))
            self.textarea = None
        elif tag == 'form' and self.current is not None:
            self.forms.append(self.current)
            self.current = None


class AccountRecoveryHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.php = supported_php()
        cls.work = tempfile.TemporaryDirectory(prefix='resurrection-account-recovery-')
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
        # Two workers, so that two requests can run at once (the two resets), and a held response does not stop the next.
        environment = {k: v for k, v in os.environ.items() if k != 'LOGD_DBCONNECT'}
        environment['PHP_CLI_SERVER_WORKERS'] = '2'
        cls.server, cls.port = start_php_server(arguments, cls.tree, cls.tree, environment, work / 'server.out')
        cls.admin = int(cls.query('SELECT acctid FROM accounts WHERE login=?', [ADMIN])[0]['acctid'])
        cls.saved_settings = {row['setting']: row['value'] for row in cls.query(
            'SELECT setting,value FROM settings WHERE setting IN (' + ','.join('?' * len(SETTINGS)) + ')', list(SETTINGS))}
        cls.saved_admin = cls.query('SELECT emailaddress,emailverified,restorepage,allowednavs FROM accounts WHERE acctid=?', [cls.admin])[0]
        cls.first_log = int(cls.query('SELECT COALESCE(MAX(logid),0) AS n FROM gamelog')[0]['n'])
        cls.first_mail = int(cls.query('SELECT COALESCE(MAX(messageid),0) AS n FROM mail')[0]['n'])
        cls.hash = cls.php_eval("echo password_hash(" + json.dumps(PASSWORD) + ", PASSWORD_BCRYPT, ['cost' => 4]);")
        if cls.query('SELECT tokenid FROM account_tokens') or cls.query('SELECT eventid FROM account_mail_log'):
            raise RuntimeError('Fixture invalid: account mail tables are not empty')
        cls.made = []

    @classmethod
    def tearDownClass(cls):
        try:
            for name in SETTINGS:
                cls.query('DELETE FROM settings WHERE setting=?', [name])
                if name in cls.saved_settings:
                    cls.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, cls.saved_settings[name]])
            cls.query('UPDATE accounts SET emailaddress=?,emailverified=?,restorepage=?,allowednavs=? WHERE acctid=?',
                      [cls.saved_admin['emailaddress'], cls.saved_admin['emailverified'], cls.saved_admin['restorepage'], cls.saved_admin['allowednavs'], cls.admin])
            cls.query('DELETE FROM mail WHERE messageid>?', [cls.first_mail])
            cls.query("DELETE FROM gamelog WHERE logid>? AND category IN ('account mail','settings')", [cls.first_log])
            cls.query('DELETE FROM account_tokens')
            cls.query('DELETE FROM account_mail_log')
        finally:
            cls.server.terminate()
            cls.server.wait(timeout=10)
            cls.mail.cleanup()
            cls.work.cleanup()

    def setUp(self):
        self.on()
        self.mail.clear()
        self.mail.answer(0)

    def tearDown(self):
        if self.made:
            ids = ','.join(str(i) for i in self.made)
            self.query(f'DELETE FROM mail WHERE msgto IN ({ids}) OR msgfrom IN ({ids})')
            self.query(f'DELETE FROM accounts_output WHERE acctid IN ({ids})')
            self.query(f'DELETE FROM accounts WHERE acctid IN ({ids})')
            self.made.clear()
        self.query("DELETE FROM bans WHERE banner='test_account_recovery_http'")

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

    def setting(self, name, value):
        self.query('DELETE FROM settings WHERE setting=?', [name])
        if value is not None:
            self.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, str(value)])

    def on(self):
        """Account mail in effect: an https serverurl, a real sender, a confirmed test mail for both, the switch on."""
        proof = json.dumps({'time': '2026-11-12 10:00:00', 'acctid': self.admin,
                            'fp': hashlib.sha256((SERVER_URL + '\n' + SENDER.lower()).encode()).hexdigest()})
        for name, value in [('serverurl', SERVER_URL), ('gameadminemail', SENDER), ('account_mail', '1'), ('account_mail_proof', proof),
                            ('account_mail_pending', None), ('account_mail_hourly', '100'), ('account_mail_staff', '1')]:
            self.setting(name, value)
        self.query('DELETE FROM account_tokens')
        self.query('DELETE FROM account_mail_log')

    def account(self, login, **fields):
        """A new account with a verified address unless told otherwise, and PASSWORD; removed in tearDown()."""
        address = login.lower() + '@example.org'
        values = {'emailaddress': address, 'emailverified': address, 'superuser': 0, 'locked': 0, 'emailvalidation': '',
                  'lastip': '192.0.2.10', 'uniqueid': hashlib.md5(login.encode()).hexdigest(), **fields}
        values.update({'login': login, 'name': 'Farmboy ' + login, 'password': self.hash, 'laston': '2026-11-12 00:00:00', 'regdate': '2026-11-12 00:00:00',
                       'restorepage': 'village.php', 'badguy': '', 'companions': 'a:0:{}', 'allowednavs': 'a:0:{}', 'specialmisc': '', 'bufflist': 'a:0:{}',
                       'dragonpoints': 'a:0:{}', 'prefs': 'a:0:{}', 'donationconfig': ''})
        self.query('INSERT INTO accounts (' + ','.join('`' + k + '`' for k in values) + ') VALUES (' + ','.join('?' * len(values)) + ')', list(values.values()))
        acctid = int(self.query('SELECT acctid FROM accounts WHERE login=?', [login])[0]['acctid'])
        self.query("INSERT INTO accounts_output (acctid,output) VALUES (?,'')", [acctid])
        self.made.append(acctid)
        return acctid

    def row(self, acctid):
        return self.query('SELECT password,authversion,loggedin,restorepage FROM accounts WHERE acctid=?', [acctid])[0]

    def allow(self, acctid, url):
        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?', ['a:1:{s:' + str(len(url)) + ':"' + url + '";b:1;}', acctid])

    def sign_in(self, login, password):
        client = Client(self.port)
        _, _, body = client.request('home.php')
        status, headers, _ = client.request('login.php', {'csrf_token': CSRF.search(body).group(1), 'name': login, 'password': password})
        return client, status, headers.get('Location')

    def forgot(self, client, who, headers=None, between=None):
        """The form, then its POST: (status, headers, body, seconds the POST took). `who` None leaves the field out."""
        status, _, form = client.request('account.php?op=forgot')
        self.assertEqual(200, status, text(form)[:500])
        if between:
            between()
        data = [('csrf_token', CSRF.search(form).group(1))] + ([] if who is None else list(who) if isinstance(who, list) else [('who', who)])
        started = time.monotonic()
        status, headers_, body = client.request('account.php?op=forgot', data, headers)
        return status, headers_, body, time.monotonic() - started

    def link(self, login_or_address, headers=None):
        """Ask for a link and read its token from the captured mail."""
        before = len(self.mail.raw())
        self.query("DELETE FROM account_mail_log WHERE purpose='recover-request'")
        status, _, body, _ = self.forgot(Client(self.port), login_or_address, headers)
        self.assertEqual(200, status, text(body)[:500])
        messages = self.mail.raw()
        self.assertEqual(before + 1, len(messages), 'no recovery mail for ' + login_or_address)
        return LINK.search(messages[-1]).group(1)

    def reset_form(self, client):
        status, headers, body = client.request('account.php?op=reset')
        self.assertPage(status, headers, body)
        return CSRF.search(body).group(1), body

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

    # Tests ----------------------------------------------------------------------------------------------------------

    def test_recovery_request_answers_the_same_for_every_account_state(self):
        ok_login = self.account('AnswerByLogin')
        self.account('AnswerByAddress')
        self.account('AnswerNoAddress', emailaddress='', emailverified='')
        self.account('AnswerUnverified', emailverified='')
        self.account('AnswerLocked', locked=1)
        self.account('AnswerBanned', lastip='198.51.100.7')
        self.query("INSERT INTO bans (ipfilter,uniqueid,banexpire,banreason,banner) VALUES ('198.51.100.7','',NULL,'test','test_account_recovery_http')")
        self.account('AnswerStaff', superuser=64)
        self.account('AnswerPending', emailvalidation='a' * 32)
        account_limited = self.account('AnswerAccountLimit')
        self.account('AnswerAddressLimit')
        for name in ['AnswerRequesterLimit', 'AnswerGlobalLimit', 'AnswerOffBetween', 'AnswerRefused']:
            self.account(name)

        def log(purpose, result, n, acctid=0, address='', requester='127.0.0.1'):
            self.query('INSERT INTO account_mail_log (date,purpose,acctid,addresskey,requesterkey,result) VALUES '
                       + ','.join(['(NOW() - INTERVAL 1 MINUTE,?,?,?,?,?)'] * n), [purpose, acctid, address_key(address) if address else '', requester, result] * n)

        cases = [
            # (label, who, what to do before the POST, what to do after it, mails captured, the recover-request result)
            ('no match', 'NobodyByThatName', None, None, 0, 'counted'),
            ('no address', 'AnswerNoAddress', None, None, 0, 'counted'),
            ('unverified address', 'AnswerUnverified', None, None, 0, 'counted'),
            ('unverified address, by address', 'answerunverified@example.org', None, None, 0, 'counted'),
            ('locked', 'AnswerLocked', None, None, 0, 'counted'),
            ('banned', 'AnswerBanned', None, None, 0, 'counted'),
            ('excluded staff', 'AnswerStaff', lambda: self.setting('account_mail_staff', '0'), lambda: self.setting('account_mail_staff', '1'), 0, 'counted'),
            ('pending sign-up', 'AnswerPending', None, None, 0, 'counted'),
            ('account limit reached', 'AnswerAccountLimit', lambda: log('recover', 'sent', 3, account_limited), None, 0, 'counted'),
            ('address limit reached', 'answeraddresslimit@example.org', lambda: log('notice', 'sent', 5, 0, 'answeraddresslimit@example.org'), None, 0, 'counted'),
            ('requester limit reached', 'AnswerRequesterLimit', lambda: log('recover-request', 'counted', 10), None, 0, 'limited'),
            ('global limit reached', 'AnswerGlobalLimit', lambda: (self.setting('account_mail_hourly', '10'), log('mailtest', 'sent', 10, self.admin, 'other@example.org')),
             lambda: self.setting('account_mail_hourly', '100'), 0, 'counted'),
            ('account mail off mid-request', 'AnswerOffBetween', 'between-off', lambda: self.setting('account_mail', '1'), 0, 'counted'),
            ('the transport refusing', 'AnswerRefused', lambda: self.mail.answer(1), lambda: self.mail.answer(0), 1, 'counted'),
            ('over 128 bytes', 'A' * 129, None, None, 0, 'counted'),
            ('a control character', 'AnswerByLogin\n', None, None, 0, 'counted'),
            ('an array', [('who[]', 'AnswerByLogin')], None, None, 0, 'counted'),
            ('no field', None, None, None, 0, 'counted'),
            ('a real match by login', 'AnswerByLogin', None, None, 1, 'counted'),
            ('a real match by address', 'ANSWERBYADDRESS@example.org', None, None, 1, 'counted'),
        ]
        pages, results = {}, {}
        for label, who, before, after, mails, logged in cases:
            self.query("DELETE FROM account_mail_log WHERE purpose='recover-request'")
            self.mail.clear()
            between = None
            if before == 'between-off':
                between = lambda: self.setting('account_mail', '0')  # noqa: E731
            elif before:
                before()
            status, headers, body, seconds = self.forgot(Client(self.port), who, between=between)
            if after:
                after()
            self.assertPage(status, headers, body)
            self.assertAccountHeaders(headers)
            self.assertIn(ANSWER, text(body), label)
            self.assertNotIn('Set-Cookie', headers, label)
            # The floor: every answer takes at least 1.5 seconds, whatever was done.
            self.assertGreaterEqual(seconds, FLOOR, label)
            self.assertLess(seconds, FLOOR + 1.5, label)
            pages[label] = same_page(body)
            results[label] = (status, sorted((k, v) for k, v in headers.items() if k.lower() != 'date'))
            self.assertEqual(mails, len(self.mail.raw()), label)
            self.assertEqual([logged], [r['result'] for r in self.query("SELECT result FROM account_mail_log WHERE purpose='recover-request' AND result<>'counted' OR eventid=(SELECT MAX(eventid) FROM account_mail_log WHERE purpose='recover-request')")], label)
            if 'limit reached' in label:
                self.query("DELETE FROM account_mail_log WHERE result<>'counted' OR purpose<>'recover-request'")
        # One page, byte for byte less the CSRF value and the clock in its links; the same status and headers.
        self.assertEqual(1, len(set(pages.values())), {label: text(page)[:200] for label, page in pages.items()})
        self.assertEqual(1, len({json.dumps(value) for value in results.values()}), results)
        self.assertEqual([['AnswerByLogin', 'AnswerByAddress']], [[row['login'] for row in self.query(
            "SELECT a.login FROM account_tokens t JOIN accounts a ON a.acctid=t.acctid WHERE t.used IS NULL AND a.login IN ('AnswerByLogin','AnswerByAddress') ORDER BY t.tokenid")]])
        self.assertEqual(1, len(self.query("SELECT tokenid FROM account_tokens t JOIN accounts a ON a.acctid=t.acctid WHERE a.login='AnswerRefused'")))
        self.assertEqual([], self.query("SELECT tokenid FROM account_tokens WHERE acctid NOT IN (SELECT acctid FROM accounts WHERE login IN ('AnswerByLogin','AnswerByAddress','AnswerRefused'))"))
        self.assertGreater(ok_login, 0)
        # Only a missing or wrong CSRF token gets something else: the 403, and nothing is counted.
        self.query('DELETE FROM account_mail_log')
        client = Client(self.port)
        client.request('account.php?op=forgot')
        self.assertEqual(403, client.request('account.php?op=forgot', {'who': 'AnswerByLogin'})[0])
        self.assertEqual(403, client.request('account.php?op=forgot', {'who': 'AnswerByLogin', 'csrf_token': '0' * 64})[0])
        self.assertEqual([], self.query('SELECT eventid FROM account_mail_log'))
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_recovery_link_opens_resets_and_leaves_no_secret(self):
        acctid = self.account('RoundTripPlayer')
        other, status, _ = self.sign_in('RoundTripPlayer', PASSWORD)
        self.assertEqual(303, status)
        before = self.row(acctid)
        self.assertIn('Return to the game', text(other.request('account.php?op=forgot')[2]))
        # A request with a forged Host: the link still comes from serverurl.
        forged = {'Host': 'evil.example.net:8443', 'X-Forwarded-Host': 'evil.example.net', 'X-Forwarded-Proto': 'https', 'X-Forwarded-For': '203.0.113.9'}
        token = self.link('RoundTripPlayer', forged)
        message = self.mail.messages()[-1]
        self.assertEqual(('roundtripplayer@example.org', SENDER, 'Choose a new password'), (message['To'], message['From'], message['Subject']))
        raw = self.mail.raw()[-1]
        for bad in ['evil', '8443', '203.0.113.9', '127.0.0.1']:
            self.assertNotIn(bad, raw)
        for link in re.findall(r'[a-z]+://[^\s,]+', raw, re.I):
            self.assertTrue(link.startswith(SERVER_URL), link)
        # Opening: a 303 to the clean address with no body. Scanners (four other browsers) open it first; the player's
        # is the fifth opening; a sixth is refused. None of it uses the token up or changes the account.
        scanners = [Client(self.port) for _ in range(4)]
        player = Client(self.port)
        for client in scanners + [player]:
            status, headers, body = client.request('account.php?op=open&t=' + token, headers={'Referer': 'http://elsewhere.example.net/inbox'})
            self.assertEqual((303, 'account.php?op=reset', ''), (status, headers['Location'], body))
            self.assertAccountHeaders(headers)
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + token), 'sixth opening')
        self.assertEqual([{'opened': '5', 'used': None}], self.query('SELECT opened,used FROM account_tokens WHERE tokenhash=SHA2(?,256)', [token]))
        self.assertEqual(before, self.row(acctid))
        # A browser that did not open the link has no grant: the expired page.
        self.assertExpired(Client(self.port).request('account.php?op=reset'), 'no grant')
        # The form: the account's login, never the token.
        csrf, form = self.reset_form(player)
        self.assertIn('Choose a new password for the account RoundTripPlayer', text(form))
        self.assertNotIn(token, form)
        # Without CSRF, nothing; two passwords that differ, or one too short: the form again, nothing changed.
        self.assertEqual(403, player.request('account.php?op=reset', {'pass1': 'New recovered password', 'pass2': 'New recovered password'})[0])
        for one, two, why in [('New recovered password', 'Other recovered password', 'The two passwords are not the same.'), ('short', 'short', 'A password has 12 to 72 bytes.')]:
            status, headers, body = player.request('account.php?op=reset', {'csrf_token': csrf, 'pass1': one, 'pass2': two})
            self.assertPage(status, headers, body)
            self.assertIn(why, text(body))
            self.assertEqual(before, self.row(acctid))
        self.mail.clear()
        status, headers, done = player.request('account.php?op=reset', {'csrf_token': csrf, 'pass1': 'New recovered password', 'pass2': 'New recovered password'})
        self.assertPage(status, headers, done)
        self.assertAccountHeaders(headers)
        self.assertIn('Your password was changed. Sign in with it now.', text(done))
        self.assertIn('Set-Cookie', headers, 'the session id is rotated')
        after = self.row(acctid)
        self.assertEqual((int(before['authversion']) + 1, '0'), (int(after['authversion']), after['loggedin']))
        self.assertNotEqual(before['password'], after['password'])
        self.assertEqual([], self.query("SELECT tokenid FROM account_tokens WHERE acctid=? AND used IS NULL", [acctid]))
        # The notice, after the page: to the address, with no link and no requester address.
        for _ in range(50):
            if self.mail.raw():
                break
            time.sleep(0.1)
        notice = self.mail.messages()
        self.assertEqual(1, len(notice))
        self.assertEqual(('roundtripplayer@example.org', 'Your password was changed'), (notice[0]['To'], notice[0]['Subject']))
        body = notice[0].get_content()
        self.assertIn('The password for RoundTripPlayer at https://game.example.org/ was changed by a recovery link at ', body)
        for bad in ['account.php', '127.0.0.1', token]:
            self.assertNotIn(bad, body)
        # Used up: the scanner's grant, the link and the grant itself are dead; the other session was signed out.
        csrf_scanner = CSRF.search(scanners[0].request('account.php?op=forgot')[2])
        self.assertExpired(scanners[0].request('account.php?op=reset'), 'scanner grant after the reset')
        self.assertExpired(player.request('account.php?op=reset'), 'grant after the reset')
        self.assertIsNotNone(csrf_scanner)
        status, headers, _ = other.request('account.php?op=forgot')
        self.assertEqual((303, 'index.php?op=timeout'), (status, headers['Location']))
        self.assertIn('Login page', text(other.request('account.php?op=forgot')[2]))
        # The old password no longer signs in; the new one does.
        self.assertEqual((303, 'index.php'), self.sign_in('RoundTripPlayer', PASSWORD)[1:])
        self.assertEqual((303, 'village.php'), self.sign_in('RoundTripPlayer', 'New recovered password')[1:])
        self.assertEqual(['Password reset through a recovery link for account ' + str(acctid)],
                         [r['message'] for r in self.query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE 'Password reset%' AND logid>?", [self.first_log])])
        # The secret search: the token is in no table (only its hash, in account_tokens), no referer record, no restore
        # page, no kept page, no session file and not the PHP error log.
        self.assertEqual({}, self.query_search([token]))
        self.assertEqual(1, len(self.query('SELECT tokenid FROM account_tokens WHERE tokenhash=SHA2(?,256)', [token])))
        self.assertEqual([], self.query("SELECT refererid FROM referers WHERE dest LIKE '%account.php%' OR uri LIKE '%account.php%'"))
        for path in self.sessions.iterdir():
            self.assertNotIn(token, path.read_text(errors='replace'), path.name)
        self.assertNotIn(token, self.error_log.read_text(errors='replace'))
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_two_resets_at_once_one_wins(self):
        acctid = self.account('RaceReset')
        before = self.row(acctid)
        token = self.link('RaceReset')
        tabs = [Client(self.port), Client(self.port)]
        forms = []
        for tab in tabs:
            self.assertEqual(303, tab.request('account.php?op=open&t=' + token)[0])
            forms.append(self.reset_form(tab)[0])
        answers = [None, None]
        start = threading.Barrier(2)

        def post(i):
            start.wait()
            answers[i] = tabs[i].request('account.php?op=reset', {'csrf_token': forms[i], 'pass1': f'Racing new password {i}', 'pass2': f'Racing new password {i}'})
        threads = [threading.Thread(target=post, args=(i,)) for i in range(2)]
        for thread in threads:
            thread.start()
        for thread in threads:
            thread.join(timeout=60)
        self.assertEqual([200, 400], sorted(answer[0] for answer in answers))
        winner = [i for i in range(2) if answers[i][0] == 200][0]
        self.assertIn('Your password was changed.', text(answers[winner][2]))
        self.assertExpired(answers[1 - winner], 'the second reset')
        self.assertEqual(int(before['authversion']) + 1, int(self.row(acctid)['authversion']))
        self.assertEqual((303, 'village.php'), self.sign_in('RaceReset', f'Racing new password {winner}')[1:])

    def test_links_die_after_sign_in_sign_out_and_an_admin_reset(self):
        # A sign-in: whoever signed in had the password.
        self.account('DiesSignIn')
        token = self.link('DiesSignIn')
        self.assertEqual(303, self.sign_in('DiesSignIn', PASSWORD)[1])
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + token), 'after a sign-in')
        # A sign-out, with a grant already held from the link: the grant's form is gone too.
        self.account('DiesSignOut')
        session, status, _ = self.sign_in('DiesSignOut', PASSWORD)
        self.assertEqual(303, status)
        token = self.link('DiesSignOut')
        holder = Client(self.port)
        self.assertEqual(303, holder.request('account.php?op=open&t=' + token)[0])
        self.reset_form(holder)
        _, _, page = session.request('login.php?op=logout')
        self.assertEqual(303, session.request('login.php?op=logout', {'csrf_token': CSRF.search(page).group(1)})[0])
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + token), 'after a sign-out')
        self.assertExpired(holder.request('account.php?op=reset'), 'a grant after a sign-out')
        # The administrator's reset in the user editor.
        target = self.account('DiesAdminReset')
        token = self.link('DiesAdminReset')
        admin, status, _ = self.sign_in(ADMIN, ADMIN_PASSWORD)
        self.assertEqual(303, status)
        edit, save = f'user.php?op=edit&userid={target}', f'user.php?op=save&userid={target}'
        self.allow(self.admin, edit)
        status, _, body = admin.request(edit)
        self.assertEqual(200, status, text(body)[:1000])
        parser = Forms()
        parser.feed(body)
        fields = [fields for action, fields in parser.forms if action == save]
        self.assertEqual(1, len(fields), [action for action, _ in parser.forms])
        fields = [(k, 'Administrator set password' if k == 'newpassword' else v) for k, v in fields[0]]
        version = int(self.row(target)['authversion'])
        self.allow(self.admin, save)
        status, _, body = admin.request(save, fields)
        self.assertEqual(200, status, text(body)[:1000])
        self.assertEqual(version + 1, int(self.row(target)['authversion']))
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + token), 'after an admin reset')

    def test_a_visitor_signed_in_as_another_account_must_sign_out_first(self):
        acctid = self.account('VisitorTarget')
        before = self.row(acctid)
        token = self.link('VisitorTarget')
        admin, status, _ = self.sign_in(ADMIN, ADMIN_PASSWORD)
        self.assertEqual(303, status)
        status, headers, _ = admin.request('account.php?op=open&t=' + token)
        self.assertEqual((303, 'account.php?op=reset'), (status, headers['Location']))
        status, headers, page = admin.request('account.php?op=reset')
        self.assertPage(status, headers, page)
        self.assertAccountHeaders(headers)
        self.assertIn('Sign out first', text(page))
        self.assertIn('This link is for another account than the one you are signed in as.', text(page))
        self.assertNotIn('name="pass1"', page)
        # The session's CSRF token, from a page that has a form; a POST changes nothing either.
        csrf = CSRF.search(admin.request('account.php?op=forgot')[2]).group(1)
        status, headers, page = admin.request('account.php?op=reset', {'csrf_token': csrf, 'pass1': 'Visitor new password', 'pass2': 'Visitor new password'})
        self.assertPage(status, headers, page)
        self.assertIn('Sign out first', text(page))
        self.assertEqual(before, self.row(acctid))
        self.assertEqual([{'used': None}], self.query('SELECT used FROM account_tokens WHERE tokenhash=SHA2(?,256)', [token]))
        restore = self.query('SELECT restorepage FROM accounts WHERE acctid=?', [self.admin])[0]['restorepage'] or ''
        self.assertNotIn(token, restore)
        self.assertEqual({}, self.query_search([token]))

    def test_staff_recovery_sends_notices_and_the_switch_excludes_staff(self):
        staff = self.account('StaffRecover', superuser=1 | 64)
        mega = self.account('StaffOtherMega', superuser=1)
        token = self.link('StaffRecover')
        client = Client(self.port)
        self.assertEqual(303, client.request('account.php?op=open&t=' + token)[0])
        csrf, _ = self.reset_form(client)
        status, headers, done = client.request('account.php?op=reset', {'csrf_token': csrf, 'pass1': 'Staff new password', 'pass2': 'Staff new password'})
        self.assertPage(status, headers, done)
        self.assertIn('Your password was changed.', text(done))
        for _ in range(50):
            if len(self.query("SELECT messageid FROM mail WHERE subject='Staff password reset by email'")) == 2:
                break
            time.sleep(0.1)
        notices = self.query("SELECT msgto,body FROM mail WHERE subject='Staff password reset by email' ORDER BY msgto")
        self.assertEqual(sorted([str(self.admin), str(mega)]), sorted(str(row['msgto']) for row in notices))
        self.assertNotIn(str(staff), [str(row['msgto']) for row in notices])
        self.assertIn('The password of the staff account StaffRecover was reset through a recovery link', notices[0]['body'])
        self.assertEqual(['Staff account password reset through a recovery link (account ' + str(staff) + ')'],
                         [r['message'] for r in self.query("SELECT message FROM gamelog WHERE category='account mail' AND message LIKE 'Staff account%' AND logid>?", [self.first_log])])
        # The switch, in Game Settings' Account mail section.
        admin, status, _ = self.sign_in(ADMIN, ADMIN_PASSWORD)
        self.assertEqual(303, status)
        self.allow(self.admin, 'configuration.php?op=accountmail')
        status, headers, section = admin.request('configuration.php?op=accountmail')
        self.assertPage(status, headers, section)
        self.assertIn('Staff accounts May recover their password by mail; every other megauser gets a game mail after each staff reset', text(section))
        self.assertIn('Exclude staff accounts from recovery', text(section))
        live = self.link('StaffRecover')
        form = re.search(r'<form class="rz-inline" action="configuration\.php\?op=accountmail_staff_off" method="POST">(.*?)</form>', section, re.S).group(1)
        data = {name: re.search(r'name="' + name + r'" value="([a-f0-9]{64})"', form).group(1) for name in ('csrf_token', 'action_token')}
        self.allow(self.admin, 'configuration.php?op=accountmail_staff_off')
        status, headers, page = admin.request('configuration.php?op=accountmail_staff_off', data)
        self.assertPage(status, headers, page)
        self.assertIn('Staff accounts are excluded from password recovery by mail.', text(page))
        self.assertIn('Staff accounts Excluded from password recovery by mail', text(page))
        self.assertEqual([{'value': '0'}], self.query("SELECT value FROM settings WHERE setting='account_mail_staff'"))
        # Excluded: the same answer and no mail; a link sent before the switch no longer changes the password.
        self.mail.clear()
        self.query("DELETE FROM account_mail_log WHERE purpose='recover-request'")
        status, _, body, _ = self.forgot(Client(self.port), 'StaffRecover')
        self.assertEqual(200, status)
        self.assertIn(ANSWER, text(body))
        self.assertEqual([], self.mail.raw())
        version = int(self.row(staff)['authversion'])
        client = Client(self.port)
        self.assertEqual(303, client.request('account.php?op=open&t=' + live)[0])
        csrf, _ = self.reset_form(client)
        self.assertExpired(client.request('account.php?op=reset', {'csrf_token': csrf, 'pass1': 'Second staff password', 'pass2': 'Second staff password'}), 'staff excluded')
        self.assertEqual(version, int(self.row(staff)['authversion']))
        # And back on, from the section.
        form = re.search(r'<form class="rz-inline" action="configuration\.php\?op=accountmail_staff_on" method="POST">(.*?)</form>', page, re.S).group(1)
        data = {name: re.search(r'name="' + name + r'" value="([a-f0-9]{64})"', form).group(1) for name in ('csrf_token', 'action_token')}
        self.allow(self.admin, 'configuration.php?op=accountmail_staff_on')
        self.assertIn('Staff accounts may recover their password by mail.', text(admin.request('configuration.php?op=accountmail_staff_on', data)[2]))
        self.assertEqual([{'value': '1'}], self.query("SELECT value FROM settings WHERE setting='account_mail_staff'"))

    def test_the_sign_in_page_links_to_recovery_only_while_account_mail_is_on(self):
        self.account('LinkPlayer')
        for state in ['off', 'on']:
            self.setting('account_mail', '1' if state == 'on' else '0')
            client = Client(self.port)
            status, headers, home = client.request('home.php')
            self.assertPage(status, headers, home)
            status, headers, page = client.request('account.php?op=forgot')
            self.assertPage(status, headers, page)
            self.assertAccountHeaders(headers)
            # A failed sign-in: the "login incorrect" message, with the link beside it while account mail is on.
            status, _, _ = client.request('login.php', {'csrf_token': CSRF.search(home).group(1), 'name': 'LinkPlayer', 'password': 'Not the password at all'})
            self.assertEqual(303, status)
            _, _, failed = client.request('home.php')
            self.assertIn('Error, your login was incorrect', text(failed))
            if state == 'off':
                self.assertNotIn('account.php?op=forgot', home)
                self.assertNotIn('account.php?op=forgot', failed)
                self.assertIn(OFF, text(page))
                self.assertNotIn('name="who"', page)
            else:
                self.assertEqual(1, home.count("<a href='account.php?op=forgot'>Forgot your password?</a>"))
                self.assertIn('Forgot your password? Ask for a link by email', text(failed))
                self.assertEqual(1, failed.count('account.php?op=forgot'))
                self.assertIn('Login or email address', text(page))
                self.assertIn('name="who"', page)
                # The message, and its link, show once.
                _, _, again = client.request('home.php')
                self.assertNotIn('Error, your login was incorrect', text(again))
                self.assertIn('Forgot your password?', text(again))


if __name__ == '__main__':
    unittest.main()
