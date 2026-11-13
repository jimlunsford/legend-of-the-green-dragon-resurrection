"""Email verification and the email change over HTTP (Phase 6 task 13c, S11 part 3; docs/PHASE6-TASK12-ACCOUNT-MAIL-DESIGN-20261112.md,
sections 4, 6 and 10, with decisions 4, 5 and 7 and the verify prompt): sign-up with requirevalidemail, the sign-in refusal
and its resend, account.php's confirm page for verify and change links, Preferences' verify and change forms (F2), the
verify prompt and its Dismiss, requirevalidemail in Game Settings, and cleanup of unconfirmed sign-ups by maintenance.

As test_account_recovery_http.py, the server runs on a symlinked copy of the tree with its own configuration file, on the
installed test database the shard's install left, with mail going to a capture (mail_capture.py): nothing leaves the
machine. Every account the class makes is removed, and every setting it changes is put back; account mail's two tables
end empty, as the install left them.
"""
import hashlib
import html
import json
import os
from pathlib import Path
import re
import shutil
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
from test_account_mail_http import (ADMIN, ADMIN_PASSWORD, ATTRIBUTION, COPYRIGHT, CSP, ERROR, EXPIRED, INLINE, LINK, SENDER, SERVER_URL,  # noqa: E402
                                    Client, text)

PASSWORD = 'Synthetic verification password'
SETTINGS = ('serverurl', 'gameadminemail', 'account_mail', 'account_mail_proof', 'account_mail_pending', 'account_mail_hourly',
            'requirevalidemail', 'requireemail', 'blockdupeemail', 'allowcreation', 'expiretrashacct', 'expireoldacct', 'expirenewacct',
            'last_char_expire')
CSRF = re.compile(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})')
PROMPT = 'Password recovery by email works only for a verified address, and yours is not verified.'
OFF = ('Password recovery by email is not available on this game. Use Petition for Help to ask an administrator to reset '
       'your password.')
NO_ACCOUNT_EXPIRATION = 2097152


def said(body):
    """A page's text as a reader sees it: text() less the space it leaves before punctuation after a tag."""
    return re.sub(r'\s+([.,;:])', r'\1', text(body))


def fields(body, action):
    """The hidden fields of the form that posts to `action`: {name: value}."""
    form = re.search(r'<form[^>]*action=[\'"]' + re.escape(action) + r'[\'"][^>]*>(.*?)</form>', body, re.S)
    assert form, 'no form posting to ' + action
    return dict(re.findall(r'<input type="hidden" name="([a-z_]+)" value="([^"]*)"', form.group(1)))


class AccountVerificationHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.php = supported_php()
        cls.work = tempfile.TemporaryDirectory(prefix='resurrection-account-verification-')
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
        environment['PHP_CLI_SERVER_WORKERS'] = '2'
        cls.server, cls.port = start_php_server(arguments, cls.tree, cls.tree, environment, work / 'server.out')
        cls.admin = int(cls.query('SELECT acctid FROM accounts WHERE login=?', [ADMIN])[0]['acctid'])
        cls.saved_settings = {row['setting']: row['value'] for row in cls.query(
            'SELECT setting,value FROM settings WHERE setting IN (' + ','.join('?' * len(SETTINGS)) + ')', list(SETTINGS))}
        cls.saved_admin = cls.query('SELECT emailaddress,emailverified,restorepage,allowednavs,superuser FROM accounts WHERE acctid=?', [cls.admin])[0]
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
            cls.query('UPDATE accounts SET emailaddress=?,emailverified=?,restorepage=?,allowednavs=?,superuser=? WHERE acctid=?',
                      [cls.saved_admin['emailaddress'], cls.saved_admin['emailverified'], cls.saved_admin['restorepage'], cls.saved_admin['allowednavs'],
                       cls.saved_admin['superuser'], cls.admin])
            cls.query('DELETE FROM mail WHERE messageid>?', [cls.first_mail])
            cls.query("DELETE FROM gamelog WHERE logid>? AND category IN ('account mail','settings','char expiration','maintenance')", [cls.first_log])
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
        logins = self.query("SELECT acctid FROM accounts WHERE login LIKE 'Verify%'")
        ids = sorted(set(self.made) | {int(r['acctid']) for r in logins})
        if ids:
            joined = ','.join(str(i) for i in ids)
            self.query(f'DELETE FROM mail WHERE msgto IN ({joined}) OR msgfrom IN ({joined})')
            self.query(f'DELETE FROM accounts_output WHERE acctid IN ({joined})')
            self.query(f'DELETE FROM accounts WHERE acctid IN ({joined})')
        self.made.clear()

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

    def assertNoSecret(self, tokens):
        """No token is stored anywhere (every table, the kept pages, referers, restorepage), in a session file or the error log."""
        self.assertEqual({}, self.query_search(tokens))
        for path in list(self.sessions.iterdir()) + [self.error_log]:
            content = path.read_text(errors='replace')
            for token in tokens:
                self.assertNotIn(token, content, path.name)

    def wait_for_mail(self, count):
        """Mail sent after the page (sign-up's verify mail, the notice after a confirmation) may land a moment after the
        response: wait up to 5 seconds for `count` captured messages."""
        for _ in range(50):
            if len(self.mail.raw()) >= count:
                break
            time.sleep(0.1)

    def setting(self, name, value):
        self.query('DELETE FROM settings WHERE setting=?', [name])
        if value is not None:
            self.query('INSERT INTO settings (setting,value) VALUES (?,?)', [name, str(value)])

    def on(self):
        """Account mail in effect: an https serverurl, a real sender, a confirmed test mail for both, the switch on."""
        proof = json.dumps({'time': '2026-11-12 10:00:00', 'acctid': self.admin,
                            'fp': hashlib.sha256((SERVER_URL + '\n' + SENDER.lower()).encode()).hexdigest()})
        for name, value in [('serverurl', SERVER_URL), ('gameadminemail', SENDER), ('account_mail', '1'), ('account_mail_proof', proof),
                            ('account_mail_pending', None), ('account_mail_hourly', '100'), ('requirevalidemail', '0'), ('allowcreation', '1'),
                            ('blockdupeemail', '0'), ('requireemail', '0')]:
            self.setting(name, value)
        self.query('DELETE FROM account_tokens')
        self.query('DELETE FROM account_mail_log')

    def account(self, login, **values_):
        """A new account with PASSWORD and an unverified address unless told otherwise; removed in tearDown()."""
        address = login.lower() + '@example.org'
        values = {'emailaddress': address, 'emailverified': '', 'superuser': 0, 'locked': 0, 'emailvalidation': '', **values_}
        values.update({'login': login, 'name': 'Farmboy ' + login, 'password': self.hash, 'laston': '2026-11-12 00:00:00', 'regdate': '2026-11-12 00:00:00',
                       'restorepage': 'village.php', 'badguy': '', 'companions': 'a:0:{}', 'allowednavs': 'a:0:{}', 'specialmisc': '', 'bufflist': 'a:0:{}',
                       'dragonpoints': 'a:0:{}', 'prefs': 'a:0:{}', 'donationconfig': ''})
        self.query('INSERT INTO accounts (' + ','.join('`' + k + '`' for k in values) + ') VALUES (' + ','.join('?' * len(values)) + ')', list(values.values()))
        acctid = int(self.query('SELECT acctid FROM accounts WHERE login=?', [login])[0]['acctid'])
        self.query("INSERT INTO accounts_output (acctid,output) VALUES (?,'')", [acctid])
        self.made.append(acctid)
        return acctid

    def address(self, acctid):
        row = self.query('SELECT emailaddress,emailverified,emailvalidation FROM accounts WHERE acctid=?', [acctid])[0]
        return row['emailaddress'], row['emailverified'], row['emailvalidation']

    def allow(self, acctid, url):
        self.query('UPDATE accounts SET allowednavs=? WHERE acctid=?', ['a:1:{s:' + str(len(url)) + ':"' + url + '";b:1;}', acctid])

    def sign_in(self, login, password, client=None):
        client = client or Client(self.port)
        _, _, body = client.request('home.php')
        status, headers, _ = client.request('login.php', {'csrf_token': CSRF.search(body).group(1), 'name': login, 'password': password})
        return client, status, headers.get('Location')

    def page(self, client, acctid, url):
        """A signed-in game page the account is allowed to open."""
        self.query('UPDATE accounts SET lasthit=NOW() WHERE acctid=?', [acctid])
        self.allow(acctid, url)
        return client.request(url)

    def open_link(self, client, token):
        status, headers, body = client.request('account.php?op=open&t=' + token)
        self.assertEqual((303, ''), (status, body), text(body)[:500])
        return headers['Location']

    def assertPage(self, status, headers, body, expected=200):
        """A game page: the status, the enforced policy, no inline script or style, no PHP error, the credits."""
        self.assertEqual(expected, status, text(body)[:1500])
        self.assertEqual(CSP, headers['Content-Security-Policy'])
        self.assertNotRegex(body, ERROR)
        self.assertIsNone(INLINE.search(body), INLINE.search(body) and body[INLINE.search(body).start() - 200:][:400])
        self.assertEqual(1, body.count(COPYRIGHT))
        self.assertEqual(1, body.count(ATTRIBUTION))

    def assertExpired(self, answer, label):
        status, headers, body = answer
        self.assertPage(status, headers, body, 400)
        self.assertIn(EXPIRED, text(body), label)

    def tokens(self):
        return [m for raw in self.mail.raw() for m in LINK.findall(raw)]

    # Tests ----------------------------------------------------------------------------------------------------------

    def test_signup_verification_and_resend(self):
        self.setting('requirevalidemail', '1')
        visitor = Client(self.port)
        status, headers, form = visitor.request('create.php')
        self.assertPage(status, headers, form)
        self.assertRegex(form, r'<input type="email" name="email" id="create-email" maxlength="128" required>')
        self.assertIn('A link to confirm your address is mailed to it. You can sign in once it is confirmed.', said(form))
        csrf = CSRF.search(form).group(1)
        base = {'csrf_token': csrf, 'name': 'VerifySignup', 'pass1': PASSWORD, 'pass2': PASSWORD, 'sex': '0'}
        # The address is required, and must pass the account mail address rules.
        for bad in ['', 'x a@bb.cc y']:
            self.assertEqual(400, visitor.request('create.php?op=create', {**base, 'email': bad})[0], bad)
        self.assertEqual([], self.query("SELECT acctid FROM accounts WHERE login='VerifySignup'"))
        status, headers, body = visitor.request('create.php?op=create', {**base, 'email': 'verifysignup@example.org'})
        self.assertPage(status, headers, body)
        self.assertIn('Check your inbox for a link to confirm your address. You can sign in once it is confirmed. Unconfirmed accounts are removed after 1 day.', said(body))
        acctid = int(self.query("SELECT acctid FROM accounts WHERE login='VerifySignup'")[0]['acctid'])
        self.made.append(acctid)
        self.assertEqual(('verifysignup@example.org', '', 'rz-pending'), self.address(acctid))
        self.wait_for_mail(1)
        messages = self.mail.messages()
        self.assertEqual(1, len(messages))
        self.assertEqual(('verifysignup@example.org', SENDER, 'Confirm your email address'), (messages[0]['To'], messages[0]['From'], messages[0]['Subject']))
        self.assertIn('An account named VerifySignup was made with this address at https://game.example.org/.', self.mail.raw()[0])
        first = LINK.search(self.mail.raw()[0]).group(1)
        # A wrong password gets the usual refusal; the right one, "Confirm your email address first".
        client, status, location = self.sign_in('VerifySignup', 'Not the synthetic password')
        self.assertEqual((303, 'index.php'), (status, location))
        self.assertExpired(client.request('account.php?op=resend'), 'no grant after a wrong password')
        client, status, location = self.sign_in('VerifySignup', PASSWORD)
        self.assertEqual((303, 'account.php?op=resend'), (status, location))
        status, headers, page = client.request('account.php?op=resend')
        self.assertPage(status, headers, page)
        self.assertIn('Confirm your email address first', said(page))
        self.assertIn('The account VerifySignup can sign in once its email address is confirmed.', said(page))
        self.assertEqual('0', self.query('SELECT loggedin FROM accounts WHERE acctid=?', [acctid])[0]['loggedin'])
        # Without the grant (another browser), nothing; with it and CSRF, a new link replaces the first.
        self.assertExpired(Client(self.port).request('account.php?op=resend'), 'another browser')
        self.assertEqual(403, client.request('account.php?op=resend', {})[0])
        status, headers, page = client.request('account.php?op=resend', {'csrf_token': CSRF.search(page).group(1)})
        self.assertPage(status, headers, page)
        self.assertIn('A new link is on its way to the address you signed up with.', said(page))
        self.assertEqual(2, len(self.mail.raw()))
        second = LINK.search(self.mail.raw()[1]).group(1)
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + first), 'the replaced link')
        # The link: a 303 to the confirm page, which names the account and the address; opening it changes nothing.
        mailbox = Client(self.port)
        self.assertEqual('account.php?op=confirm', self.open_link(mailbox, second))
        status, headers, page = mailbox.request('account.php?op=confirm')
        self.assertPage(status, headers, page)
        self.assertIn('Confirm verifysignup@example.org as the email address of the account VerifySignup. Nothing changes until you press Confirm.', said(page))
        self.assertNotIn(second, page)
        self.assertEqual(('verifysignup@example.org', '', 'rz-pending'), self.address(acctid))
        self.assertEqual(403, mailbox.request('account.php?op=confirm', {})[0])
        status, headers, page = mailbox.request('account.php?op=confirm', {'csrf_token': CSRF.search(page).group(1)})
        self.assertPage(status, headers, page)
        self.assertIn('The address verifysignup@example.org is now verified for the account VerifySignup.', said(page))
        self.assertIn('The account can sign in now.', said(page))
        self.assertEqual(('verifysignup@example.org', 'verifysignup@example.org', ''), self.address(acctid))
        self.assertExpired(mailbox.request('account.php?op=confirm'), 'the grant is gone once confirmed')
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + second), 'the used link')
        # Now it signs in.
        _, status, location = self.sign_in('VerifySignup', PASSWORD)
        self.assertEqual((303, 'village.php'), (status, location))
        self.assertNoSecret([first, second])
        # T16: sign-ups are limited per requester (5 an hour): the sixth from this address gets 429 and no account.
        self.query('DELETE FROM account_mail_log')
        for _ in range(5):
            self.query("INSERT INTO account_mail_log (date,purpose,acctid,addresskey,requesterkey,result) VALUES (NOW(),'signup',0,'','127.0.0.1','counted')")
        _, _, form = visitor.request('create.php')
        status, _, page = visitor.request('create.php?op=create', {'csrf_token': CSRF.search(form).group(1), 'name': 'VerifySignupSix', 'pass1': PASSWORD,
                                                                   'pass2': PASSWORD, 'email': 'verifysignupsix@example.org', 'sex': '0'})
        self.assertEqual(429, status)
        self.assertIn('Too many accounts were made from here. Try again later.', said(page))
        self.assertEqual([], self.query("SELECT acctid FROM accounts WHERE login='VerifySignupSix'"))
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_account_mail_off_leaves_today_behavior(self):
        # requirevalidemail stored as 1 reads as off while account mail is off: sign-up makes an ordinary account.
        self.setting('requirevalidemail', '1')
        self.setting('account_mail', '0')
        visitor = Client(self.port)
        _, _, form = visitor.request('create.php')
        self.assertIn('<input type="email" name="email" id="create-email" maxlength="128"></td>', form)
        status, headers, body = visitor.request('create.php?op=create', {'csrf_token': CSRF.search(form).group(1), 'name': 'VerifyOffSignup', 'pass1': PASSWORD,
                                                                        'pass2': PASSWORD, 'email': '', 'sex': '0'})
        self.assertPage(status, headers, body)
        self.assertIn('Your account was created.', said(body))
        acctid = int(self.query("SELECT acctid FROM accounts WHERE login='VerifyOffSignup'")[0]['acctid'])
        self.made.append(acctid)
        self.assertEqual(('', '', ''), self.address(acctid))
        self.assertEqual([], self.mail.raw())
        # A pending sign-up can sign in while account mail is off.
        pending = self.account('VerifyOffPending', emailvalidation='rz-pending')
        client, status, location = self.sign_in('VerifyOffPending', PASSWORD)
        self.assertEqual((303, 'village.php'), (status, location))
        # No prompt, no Verify button; Preferences says recovery is not available, and a change takes effect at once,
        # with the current password only.
        status, headers, village = self.page(client, pending, 'village.php')
        self.assertPage(status, headers, village)
        self.assertNotIn(PROMPT, said(village))
        self.assertNotIn('rz-verify-prompt', village)
        nav = re.search(r'href="(prefs\.php\?c=\d+-\d{6})"', village).group(1)
        status, headers, prefs = client.request(nav)
        self.assertPage(status, headers, prefs)
        self.assertIn(OFF, said(prefs))
        self.assertNotIn('Verify my address', prefs)
        self.assertNotIn('name="email"', prefs)
        hidden = fields(prefs, 'prefs.php?op=changeemail')
        status, headers, page = client.request('prefs.php?op=changeemail', {**hidden, 'newemail': 'verifyoff-new@example.org', 'emailpassword': 'Not the synthetic password'})
        self.assertPage(status, headers, page)
        self.assertIn('Address change rejected. Check your current password.', said(page))
        self.assertEqual(('verifyoffpending@example.org', '', 'rz-pending'), self.address(pending))
        # The old form's email field, posted as 1.1.2's form did, is never read (F2).
        _, _, prefs = self.page(client, pending, 'prefs.php')
        status, _, _ = client.request('prefs.php?op=save', {'csrf_token': CSRF.search(prefs).group(1), 'email': 'verifyoff-stolen@example.org'})
        self.assertEqual(200, status)
        self.assertEqual(('verifyoffpending@example.org', '', 'rz-pending'), self.address(pending))
        _, _, prefs = self.page(client, pending, 'prefs.php')
        hidden = fields(prefs, 'prefs.php?op=changeemail')
        status, headers, page = client.request('prefs.php?op=changeemail', {**hidden, 'newemail': 'verifyoff-new@example.org', 'emailpassword': PASSWORD})
        self.assertPage(status, headers, page)
        self.assertIn('Your email address has been changed. It is not verified.', said(page))
        self.assertEqual(('verifyoff-new@example.org', '', 'rz-pending'), self.address(pending))
        self.assertEqual([], self.mail.raw())
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_preferences_verify_and_the_prompt(self):
        acctid = self.account('VerifyPrefs')
        client, status, _ = self.sign_in('VerifyPrefs', PASSWORD)
        self.assertEqual(303, status)
        status, headers, village = self.page(client, acctid, 'village.php')
        self.assertPage(status, headers, village)
        self.assertIn(PROMPT, said(village))
        nav = re.search(r'href="(prefs\.php\?c=\d+-\d{6})"', village).group(1)
        self.assertIn('<a href="' + nav + '">Verify it in Preferences</a>', village)
        # Preferences: the address, not verified, and the Verify button (POST, CSRF, action token).
        status, headers, prefs = client.request(nav)
        self.assertPage(status, headers, prefs)
        self.assertIn('Your address is verifyprefs@example.org, not verified.', said(prefs))
        hidden = fields(prefs, 'prefs.php?op=verifyemail')
        self.assertEqual({'csrf_token', 'action_token'}, set(hidden))
        self.assertEqual(403, client.request('prefs.php?op=verifyemail', {'action_token': hidden['action_token']})[0])
        status, headers, page = client.request('prefs.php?op=verifyemail', hidden)
        self.assertPage(status, headers, page)
        self.assertIn('A link to confirm your address is on its way to it.', said(page))
        self.assertEqual(409, client.request('prefs.php?op=verifyemail', hidden)[0], 'the action token is used once')
        self.assertEqual(1, len(self.mail.raw()))
        token = LINK.search(self.mail.raw()[0]).group(1)
        # Opened in the same browser, signed in as the account: the confirm page, then the address is verified.
        self.assertEqual('account.php?op=confirm', self.open_link(client, token))
        status, headers, page = client.request('account.php?op=confirm')
        self.assertPage(status, headers, page)
        status, headers, page = client.request('account.php?op=confirm', {'csrf_token': CSRF.search(page).group(1)})
        self.assertPage(status, headers, page)
        self.assertIn('Password recovery by email works for it.', said(page))
        self.assertNotIn('The account can sign in now.', said(page))
        self.assertEqual(('verifyprefs@example.org', 'verifyprefs@example.org', ''), self.address(acctid))
        # Verified: no prompt.
        status, headers, village = self.page(client, acctid, 'village.php')
        self.assertPage(status, headers, village)
        self.assertNotIn(PROMPT, said(village))
        # Another account, signed in elsewhere, gets "Sign out first" for a link of this one.
        other = self.account('VerifyPrefsOther')
        client2, _, _ = self.sign_in('VerifyPrefsOther', PASSWORD)
        self.query("UPDATE accounts SET emailverified='' WHERE acctid=?", [acctid])
        status, _, prefs = self.page(client, acctid, 'prefs.php')
        status, _, page = client.request('prefs.php?op=verifyemail', fields(prefs, 'prefs.php?op=verifyemail'))
        token2 = LINK.search(self.mail.raw()[-1]).group(1)
        self.assertEqual('account.php?op=confirm', self.open_link(client2, token2))
        status, headers, page = client2.request('account.php?op=confirm')
        self.assertPage(status, headers, page)
        self.assertIn('Sign out first', said(page))
        self.assertEqual(('verifyprefs@example.org', '', ''), self.address(acctid))
        # Dismiss: POST and CSRF; it stays dismissed for that address, and shows again after the address changes.
        status, headers, village = self.page(client2, other, 'village.php')
        self.assertIn(PROMPT, said(village))
        dismiss = fields(village, 'prefs.php?op=dismissverify')
        self.assertEqual({'csrf_token'}, set(dismiss))
        self.assertEqual(403, client2.request('prefs.php?op=dismissverify', {})[0])
        self.assertEqual(403, client2.request('prefs.php?op=dismissverify')[0], 'GET')
        status, headers, page = client2.request('prefs.php?op=dismissverify', dismiss)
        self.assertPage(status, headers, page)
        self.assertIn('The reminder is hidden until your address changes.', said(page))
        for _ in range(2):
            status, headers, village = self.page(client2, other, 'village.php')
            self.assertPage(status, headers, village)
            self.assertNotIn(PROMPT, said(village))
        self.query("UPDATE accounts SET emailaddress='verifyprefsother-new@example.org' WHERE acctid=?", [other])
        status, headers, village = self.page(client2, other, 'village.php')
        self.assertIn(PROMPT, said(village))
        # Never while account mail is off.
        self.setting('account_mail', '0')
        status, headers, village = self.page(client2, other, 'village.php')
        self.assertPage(status, headers, village)
        self.assertNotIn(PROMPT, said(village))
        self.assertNoSecret([token, token2])
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_email_change_needs_password_and_confirmation(self):
        acctid = self.account('VerifyChange', emailverified='verifychange@example.org')
        client, status, _ = self.sign_in('VerifyChange', PASSWORD)
        self.assertEqual(303, status)
        # F2 through 1.1.2's route: the preferences save, with an `email` field and no current password, changes
        # nothing, with account mail off and on.
        for mode in ['0', '1']:
            with self.subTest(account_mail=mode):
                self.setting('account_mail', mode)
                _, _, prefs = self.page(client, acctid, 'prefs.php')
                client.request('prefs.php?op=save', {'csrf_token': CSRF.search(prefs).group(1), 'email': 'verifychange-f2@example.org'})
                self.assertEqual(('verifychange@example.org', 'verifychange@example.org', ''), self.address(acctid))
        self.assertEqual([], self.mail.raw())
        status, headers, prefs = self.page(client, acctid, 'prefs.php')
        self.assertPage(status, headers, prefs)
        self.assertIn('Your address is verifychange@example.org, verified.', said(prefs))
        self.assertNotIn('Verify my address', prefs)
        hidden = fields(prefs, 'prefs.php?op=changeemail')
        # F2: without the current password, nothing is sent and nothing changes.
        for wrong in ['', 'Not the synthetic password']:
            status, headers, page = client.request('prefs.php?op=changeemail', {**hidden, 'newemail': 'verifychange-new@example.org', 'emailpassword': wrong})
            self.assertPage(status, headers, page)
            self.assertIn('Address change rejected. Check your current password.', said(page))
            hidden = fields(page, 'prefs.php?op=changeemail')
        self.assertEqual([], self.mail.raw())
        status, headers, page = client.request('prefs.php?op=changeemail', {**hidden, 'newemail': 'verifychange-new@example.org', 'emailpassword': PASSWORD})
        self.assertPage(status, headers, page)
        self.assertIn('A link to confirm the new address is on its way to it. Your address changes only when you open the link and confirm it.', said(page))
        self.assertIn('A change of address is waiting: open the link mailed to the new address to confirm it.', said(page))
        self.assertEqual(('verifychange@example.org', 'verifychange@example.org', ''), self.address(acctid), 'nothing changes before the confirmation')
        messages = {m['To']: m for m in self.mail.messages()}
        raws = {m['To']: r for m, r in zip(self.mail.messages(), self.mail.raw())}
        self.assertEqual({'verifychange-new@example.org', 'verifychange@example.org'}, set(messages))
        self.assertEqual('Confirm your new email address', messages['verifychange-new@example.org']['Subject'])
        self.assertEqual('A change of your email address was requested', messages['verifychange@example.org']['Subject'])
        token = LINK.search(raws['verifychange-new@example.org']).group(1)
        # The confirmation, from the new address's mailbox in another browser.
        mailbox = Client(self.port)
        self.assertEqual('account.php?op=confirm', self.open_link(mailbox, token))
        status, headers, page = mailbox.request('account.php?op=confirm')
        self.assertPage(status, headers, page)
        self.assertIn('Make verifychange-new@example.org the email address of the account VerifyChange. The address changes only when you press Confirm.', said(page))
        self.mail.clear()
        status, headers, page = mailbox.request('account.php?op=confirm', {'csrf_token': CSRF.search(page).group(1)})
        self.assertPage(status, headers, page)
        self.assertIn('The email address of the account VerifyChange is now verifychange-new@example.org, and it is verified.', said(page))
        self.assertEqual(('verifychange-new@example.org', 'verifychange-new@example.org', ''), self.address(acctid))
        # The second notice goes to the old address after the page.
        self.wait_for_mail(1)
        messages = self.mail.messages()
        self.assertEqual([('verifychange@example.org', 'Your email address was changed')], [(m['To'], m['Subject']) for m in messages])
        notices = [raws['verifychange@example.org'], self.mail.raw()[0]]
        for notice in notices:
            body = notice.split('\n\n', 1)[1] if '\n\n' in notice else notice.split('\r\n\r\n', 1)[1]
            self.assertNotIn('verifychange-new', body)
            self.assertNotIn('http', body)
            self.assertNotIn('op=open', body)
        # A stale authversion kills a change link: a new request, then a sign-in, then the link.
        status, _, prefs = self.page(client, acctid, 'prefs.php')
        self.mail.clear()
        client.request('prefs.php?op=changeemail', {**fields(prefs, 'prefs.php?op=changeemail'), 'newemail': 'verifychange-third@example.org', 'emailpassword': PASSWORD})
        token2 = LINK.search([r for m, r in zip(self.mail.messages(), self.mail.raw()) if m['To'] == 'verifychange-third@example.org'][0]).group(1)
        self.sign_in('VerifyChange', PASSWORD)
        self.assertExpired(Client(self.port).request('account.php?op=open&t=' + token2), 'after a sign-in')
        self.assertEqual(('verifychange-new@example.org', 'verifychange-new@example.org', ''), self.address(acctid))
        self.assertNoSecret([token, token2, PASSWORD])  # and the current password the change needed
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_requirevalidemail_in_game_settings(self):
        self.query('UPDATE accounts SET superuser=superuser|? WHERE acctid=?', [1 | 128, self.admin])
        self.setting('account_mail', '0')
        admin, status, _ = self.sign_in(ADMIN, ADMIN_PASSWORD)
        self.assertEqual(303, status)
        status, headers, body = self.page(admin, self.admin, 'configuration.php')
        self.assertPage(status, headers, body)
        locked = re.search(r'<p class="rz-setting-locked"><label>Require users to validate their email address <select disabled[^>]*><option selected>No</option></select></label>'
                           r'<span class="rz-setting-locked-note" id="[^"]+">([^<]+)</span></p>', body)
        self.assertIsNotNone(locked, 'requirevalidemail is not shown locked while account mail is off')
        self.assertIn('Turn on account mail first', locked.group(1))
        self.assertNotIn('name="requirevalidemail"', body)
        # With account mail on: a setting like any other, which saves.
        self.setting('account_mail', '1')
        status, headers, body = self.page(admin, self.admin, 'configuration.php')
        self.assertPage(status, headers, body)
        self.assertRegex(body, r'<select name="requirevalidemail">')
        self.assertNotIn('rz-locked-requirevalidemail', body)
        form = re.search(r'<form method="POST" action="configuration\.php\?op=save">(.*?)</form>', body, re.S).group(1)
        data = dict(re.findall(r'<input type="hidden" name="([a-z_]+)" value="([^"]*)"', form))
        for name, value in re.findall(r'<input name="([^"]+)" value="([^"]*)"', form):
            data[name] = html.unescape(value)
        for select in re.finditer(r'<select name="([^"]+)">(.*?)</select>', form, re.S):
            chosen = re.search(r'<option value="([^"]*)" selected>', select.group(2)) or re.search(r'<option value="([^"]*)"', select.group(2))
            data[select.group(1)] = html.unescape(chosen.group(1))
        data['requirevalidemail'] = '1'
        self.allow(self.admin, 'configuration.php?op=save')
        status, headers, page = admin.request('configuration.php?op=save', data)
        self.assertPage(status, headers, page)
        self.assertEqual([{'value': '1'}], self.query("SELECT value FROM settings WHERE setting='requirevalidemail'"))
        _, _, form = Client(self.port).request('create.php')
        self.assertIn('A link to confirm your address is mailed to it.', said(form))
        # A new serverurl turns account mail off in effect: requirevalidemail reads off and is locked again.
        self.setting('serverurl', 'https://other.example.org/')
        _, _, form = Client(self.port).request('create.php')
        self.assertNotIn('A link to confirm your address is mailed to it.', said(form))
        status, headers, body = self.page(admin, self.admin, 'configuration.php')
        self.assertRegex(body, r'Require users to validate their email address <select disabled[^>]*><option selected>No</option>')
        self.assertEqual('', self.error_log.read_text(errors='replace').strip())

    def test_unconfirmed_signup_is_removed_by_maintenance(self):
        # Cleanup goes through expiretrashacct: a sign-up never confirmed has never signed in (laston = regdate). The
        # pending account is made the way a player makes one, through the sign-up form with requirevalidemail on.
        self.setting('requirevalidemail', '1')
        visitor = Client(self.port)
        _, _, form = visitor.request('create.php')
        status, headers, body = visitor.request('create.php?op=create', {'csrf_token': CSRF.search(form).group(1), 'name': 'VerifyTrash', 'pass1': PASSWORD,
                                                                        'pass2': PASSWORD, 'email': 'verifytrash@example.org', 'sex': '0'})
        self.assertPage(status, headers, body)
        self.assertIn('Check your inbox', said(body))
        pending = int(self.query("SELECT acctid FROM accounts WHERE login='VerifyTrash'")[0]['acctid'])
        self.made.append(pending)
        self.assertEqual('rz-pending', self.address(pending)[2])
        played = self.account('VerifyTrashPlayed', emailvalidation='rz-pending')
        self.query('UPDATE accounts SET regdate=DATE_SUB(NOW(),INTERVAL 2 DAY), laston=regdate WHERE acctid=?', [pending])
        self.query('UPDATE accounts SET regdate=DATE_SUB(NOW(),INTERVAL 2 DAY), laston=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE acctid=?', [played])
        protected = self.query('SELECT acctid,superuser FROM accounts WHERE acctid NOT IN (?,?)', [pending, played])
        try:
            self.query('UPDATE accounts SET superuser=superuser|? WHERE acctid NOT IN (?,?)', [NO_ACCOUNT_EXPIRATION, pending, played])
            for name, value in [('last_char_expire', '2000-01-01 00:00:00'), ('expireoldacct', '45'), ('expirenewacct', '10'), ('expiretrashacct', '1')]:
                self.setting(name, value)
            self.query("DELETE FROM settings WHERE setting='maintenance_day' OR setting LIKE 'mh:%'")
            result = subprocess.run([self.php, *self.mail.args(), 'cron.php'], cwd=self.tree, capture_output=True, text=True, timeout=120,
                                    env=dict(os.environ, LOGD_DBCONNECT=str(self.config)))
            self.assertEqual(0, result.returncode, result.stdout[-1000:] + result.stderr[-1000:])
            self.assertEqual([], self.query('SELECT acctid FROM accounts WHERE acctid=?', [pending]))
            self.assertEqual(1, len(self.query('SELECT acctid FROM accounts WHERE acctid=?', [played])))
        finally:
            for row in protected:
                self.query('UPDATE accounts SET superuser=? WHERE acctid=?', [row['superuser'], row['acctid']])
            self.query("DELETE FROM settings WHERE setting='maintenance_day' OR setting LIKE 'mh:%'")


if __name__ == '__main__':
    unittest.main()
