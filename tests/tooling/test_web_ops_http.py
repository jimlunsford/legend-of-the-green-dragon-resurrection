"""The web setup page, setup.php (Phase 6 task 5b; docs/PHASE6-TASK4-WEB-OPS-DESIGN-20261106.md, sections 3, 5 and 6).

Each test runs PHP's server on a symlinked copy of the tree (as test_operator_pages.py does), so a test can give the
game a configuration file, a token file or none, without touching the checkout. They need an empty database, so the
class saves the whole disposable test database first and puts it back exactly at the end (tests/support/database_dump.php):
the tests after them find it as the suite's install left it. Fixture upgrade steps go in upgrades/, as the PHPUnit
upgrade tests put them, and are removed after each test.

A fixture-only prepend file lets a test set REMOTE_ADDR and HTTPS through two test headers, so the plain-HTTP refusal
can be tested from a loopback server; nothing in the application reads those headers.
"""
from contextlib import contextmanager
import hashlib
import html
import http.client
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2]
ICON_LINKS = ['<link rel="icon" href="favicon.ico" sizes="any">', '<link rel="icon" href="favicon.svg" type="image/svg+xml">',
              '<link rel="apple-touch-icon" href="apple-touch-icon.png">']  # Phase 6 task 17 (R5): in every frame's head
sys.path.insert(0, str(Path(__file__).resolve().parent))
from asset_urls import asset_url  # noqa: E402
from php_runtime import supported_php  # noqa: E402
from loopback_server import start_php_server  # noqa: E402
import mail_capture  # noqa: E402

COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team'
LICENSE = "This work is licensed under a <a rel='license' href='http://creativecommons.org/licenses/by-nc-sa/2.0/' target='_blank'>Creative Commons License</a>."
ATTRIBUTION = ('A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by '
               '<a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a>')
CSP = ("default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; "
       "base-uri 'self'; form-action 'self'; frame-ancestors 'self'")
GUARD = '<?php http_response_code(404); exit; ?>'
ERROR = re.compile(r'(?i)fatal error|runtime error|warning:|notice:|deprecated:|uncaught')
INLINE = re.compile(r'<script(?![^>]*\bsrc=)|<style|\sstyle=|\son[a-z]+=|javascript:', re.I)
ADMIN_PASSWORD = 'Synthetic setup admin password 7'
# Fixture-only: the test headers set REMOTE_ADDR and HTTPS before the page runs (see the module docstring).
PREPEND = """<?php
if (isset($_SERVER['HTTP_X_TEST_REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_TEST_REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_TEST_HTTPS'])) $_SERVER['HTTPS'] = $_SERVER['HTTP_X_TEST_HTTPS'];
"""


def text(body):
    body = re.sub(r'(?s)<!--.*?-->|<script.*?</script>', '', body)
    return html.unescape(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', body)))


def token_value(seed):
    """A well-formed token of the test's own choosing: rzsetup- and 32 base32 characters from a seed."""
    alphabet = 'abcdefghijklmnopqrstuvwxyz234567'
    digest = hashlib.sha256(seed.encode()).digest()
    return 'rzsetup-' + ''.join(alphabet[b % 32] for b in digest[:32])


class Client:
    """One browser: its own cookies; requests return (status, headers, body text)."""

    def __init__(self, port, extra=None):
        self.port, self.extra = port, dict(extra or {})
        self.jar = http.cookiejar.CookieJar()
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect)
        self.pages = []

    def request(self, path='setup.php', data=None, headers=None, method=None):
        request = urllib.request.Request(f'http://127.0.0.1:{self.port}/{path}', method=method,
                                         data=None if data is None else urllib.parse.urlencode(data).encode(),
                                         headers={**self.extra, **(headers or {})})
        try:
            response = self.opener.open(request, timeout=120)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode('utf-8', errors='replace')
        # The test talks plain HTTP to a page that believes it is behind HTTPS (the test headers), so it marks its
        # session cookie Secure; the jar would never send that cookie back over HTTP, so the test keeps it sendable.
        for cookie in self.jar:
            cookie.secure = False
        self.pages.append((response.status, response.headers, body))
        return response.status, response.headers, body

    def csrf(self, body):
        return re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body).group(1)


class SetupPageTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.php = supported_php()
        cls.work = tempfile.TemporaryDirectory(prefix='resurrection-setup-tests-')
        cls.work_path = Path(cls.work.name)
        cls.db = {'DB_HOST': host, 'DB_USER': os.environ['RESURRECTION_TEST_DB_USER'],
                  'DB_PASS': os.environ['RESURRECTION_TEST_DB_PASSWORD'], 'DB_NAME': 'resurrection_test'}
        cls.db_config_text = cls.config_text(cls.db)
        cls.db_config = cls.work_path / 'test-db.php'
        cls.db_config.write_text(cls.db_config_text)
        cls.prepend = cls.work_path / 'prepend.php'
        cls.prepend.write_text(PREPEND)
        cls.saved = cls.work_path / 'saved-database.php'
        cls.dump('save')

    @classmethod
    def tearDownClass(cls):
        try:
            cls.dump('restore')
        finally:
            cls.work.cleanup()

    def tearDown(self):
        for step in (ROOT / 'upgrades').glob('*-setup-fixture-*.php'):
            step.unlink()

    # Fixtures -------------------------------------------------------------------------------------------------------

    @staticmethod
    def config_text(values, prefix=''):
        return ("<?php\n$DB_HOST = " + json.dumps(values['DB_HOST']) + ";\n$DB_USER = " + json.dumps(values['DB_USER']) +
                ";\n$DB_PASS = " + json.dumps(values['DB_PASS']) + ";\n$DB_NAME = " + json.dumps(values['DB_NAME']) +
                ";\n$DB_PREFIX = " + json.dumps(prefix) + ";\n$DB_USEDATACACHE = 0;\n$DB_DATACACHEPATH = '';\n")

    @classmethod
    def dump(cls, action):
        result = subprocess.run([cls.php, 'tests/support/database_dump.php', action, str(cls.saved), str(cls.db_config)],
                                cwd=ROOT, capture_output=True, text=True, timeout=300)
        if result.returncode != 0:
            raise AssertionError('Database ' + action + ' failed: ' + result.stderr[-2000:])

    def empty_db(self):
        self.dump('empty')

    def query(self, sql, parameters=()):
        code = ("chdir(getenv('ROOT')); define('RESURRECTION_ENTRY', true); require 'lib/dbwrapper_pdo.php'; require getenv('CONFIG');"
                " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME);"
                " $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);"
                " $rows=db_query($input[0],true,$input[1]); echo json_encode(is_array($rows)?$rows:[],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);")
        result = subprocess.run([self.php, '-r', code], input=json.dumps([sql, list(parameters)]), capture_output=True, text=True,
                                timeout=60, env=dict(os.environ, ROOT=str(ROOT), CONFIG=str(self.db_config)))
        if result.returncode != 0:
            raise AssertionError('Fixture query failed: ' + result.stderr[-1000:] + result.stdout[-1000:])
        return json.loads(result.stdout)

    def tables(self):
        return sorted(list(row.values())[0] for row in self.query('SHOW TABLES'))

    def search_db(self, needles):
        """Every table and column whose stored text holds one of the needles: {needle: ['table.column', ...]}."""
        code = ("chdir(getenv('ROOT')); define('RESURRECTION_ENTRY', true); require 'lib/dbwrapper_pdo.php'; require getenv('CONFIG');"
                " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); $needles=json_decode(stream_get_contents(STDIN),true); $found=[];"
                " foreach (db_query('SHOW TABLES') as $t) { $t=(string)array_values($t)[0]; foreach (db_query('SELECT * FROM '.db_identifier($t)) as $row) {"
                " foreach ($row as $column=>$value) { foreach ($needles as $n) { if (is_string($value) && str_contains($value,$n)) $found[$n][]=$t.'.'.$column; } } } }"
                " echo json_encode($found);")
        result = subprocess.run([self.php, '-r', code], input=json.dumps(needles), capture_output=True, text=True, timeout=120,
                                env=dict(os.environ, ROOT=str(ROOT), CONFIG=str(self.db_config)))
        self.assertEqual(0, result.returncode, result.stderr[-2000:])
        return json.loads(result.stdout or '{}') or {}

    def install_with_command(self, modules='none'):
        """A completed install through the command, with Admin as the first administrator (SU_MEGAUSER)."""
        self.empty_db()
        result = subprocess.run([self.php, 'scripts/install.php', '--modules=' + modules, str(self.db_config)], cwd=ROOT,
                                capture_output=True, text=True, timeout=300,
                                env=dict({k: v for k, v in os.environ.items() if k != 'LOGD_DBCONNECT'},
                                         RESURRECTION_ADMIN_LOGIN='Admin', RESURRECTION_ADMIN_PASSWORD=ADMIN_PASSWORD))
        self.assertEqual(0, result.returncode, result.stderr)

    def fixture_step(self, number, body, description='Setup fixture step'):
        """An upgrade step in upgrades/ (removed in tearDown); body is the PHP of its apply function."""
        path = ROOT / 'upgrades' / f'{number:04d}-setup-fixture-{number}.php'
        path.write_text("<?php\nreturn ['description' => " + json.dumps(description) + ", 'apply' => static function (): void {\n" + body + "\n}];\n")
        return path.stem

    @contextmanager
    def tree(self, config=None, env=None, workers=None, ini=None, source=None):
        """A server on a symlinked copy of the tree, or, given a source folder, on a real copy of that folder alone (the
        release package's upload/). Yields an object with port, path, log (the PHP error log) and sessions (the session
        folder)."""
        with tempfile.TemporaryDirectory(prefix='resurrection-setup-tree-') as tree:
            tree = Path(tree)
            if source is not None:
                shutil.copytree(source, tree, dirs_exist_ok=True)
            else:
                for entry in ROOT.iterdir():
                    if entry.name not in {'dbconnect.php', '.git', 'resurrection-setup.php', 'resurrection-setup-failures.php'}:
                        os.symlink(entry, tree / entry.name)
            if config is not None:
                (tree / 'dbconnect.php').write_text(config)
            sessions = tree / '.sessions'
            sessions.mkdir()
            log = tree / '.php-errors.log'
            log.touch()
            served = type('Served', (), {})()
            served.path, served.log, served.sessions = tree, log, sessions
            with self.server(served, env=env, workers=workers, ini=ini) as port:
                served.port = port
                yield served

    @contextmanager
    def server(self, served, env=None, workers=None, ini=None):
        """A PHP server for a tree() copy, on a port the OS chose: the error log, sessions and test prepend file of
        `served`, the given ini settings and environment. Yields its port once it answers; fails with a clear message
        (and the server's output) if it exits or never answers. tree() and a test's second server both use it."""
        arguments = [self.php, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' + str(served.log),
                     '-d', 'session.save_path=' + str(served.sessions), '-d', 'auto_prepend_file=' + str(self.prepend),
                     *mail_capture.default_args()]
        for key, value in (ini or {}).items():
            arguments += ['-d', f'{key}={value}']
        environment = {k: v for k, v in os.environ.items() if k != 'LOGD_DBCONNECT'}
        environment.update(env or {})
        if workers:
            environment['PHP_CLI_SERVER_WORKERS'] = str(workers)
        handle, output = tempfile.mkstemp(prefix='.server-', suffix='.out', dir=served.path)
        os.close(handle)
        process, port = start_php_server(arguments, served.path, served.path, environment, output)
        try:
            yield port
        finally:
            process.terminate()
            process.wait(timeout=10)

    def token_file(self, directory, value, upstream=False, lines=None, age=0):
        path = Path(directory) / 'resurrection-setup.php'
        path.write_text(lines if lines is not None else GUARD + '\n' + value + '\n' + ('https-terminated-upstream\n' if upstream else ''))
        if age:
            stamp = time.time() - age
            os.utime(path, (stamp, stamp))
        return path

    def assertScreen(self, status, headers, body, expected=200):
        """A setup screen: the status, the headers, no inline script or style, and the operator frame with the full
        credits once each and none of the game's panels."""
        self.assertEqual(expected, status, text(body)[:1500])
        self.assertEqual(CSP, headers['Content-Security-Policy'])
        self.assertEqual(['no-store'], headers.get_all('Cache-Control'))
        self.assertEqual('noindex', headers['X-Robots-Tag'])
        self.assertEqual('SAMEORIGIN', headers['X-Frame-Options'])
        self.assertNotRegex(body, ERROR)
        self.assertIsNone(INLINE.search(body), INLINE.search(body) and body[INLINE.search(body).start() - 200:][:400])
        for credit in [COPYRIGHT, LICENSE, ATTRIBUTION]:
            self.assertEqual(1, body.count(credit), credit)
        for panel in ['motd.php', 'petition.php', 'mail.php', 'Online Characters', 'rz-tabbar']:
            self.assertNotIn(panel, body, panel)
        self.assertIn('<script src="' + asset_url('assets/ui/behaviors.js') + '" defer></script>', body)
        head = body[:body.index('</head>')]
        for link in ICON_LINKS:
            self.assertEqual(1, head.count(link), link)

    def assertEmpty404(self, answer, label='', setup=True):
        """The S2 guard's empty 404 with no cookie; setup.php's own also carries its fixed headers."""
        status, headers, body = answer
        self.assertEqual(404, status, label + ' ' + body[:500])
        self.assertEqual('', body, label)
        self.assertIsNone(headers['Set-Cookie'], label)
        if setup:
            self.assertEqual(CSP, headers['Content-Security-Policy'], label)
            self.assertEqual('no-store', headers['Cache-Control'], label)
            self.assertEqual('noindex', headers['X-Robots-Tag'], label)

    def start(self, client):
        """GET the preflight, then the token step. Returns the token step's body."""
        status, headers, body = client.request()
        self.assertScreen(status, headers, body)
        status, headers, body = client.request(data={'csrf_token': client.csrf(body), 'step': 'token'})
        self.assertScreen(status, headers, body)
        return body

    def verify(self, client, body, value, expected=200):
        status, headers, body = client.request(data={'csrf_token': client.csrf(body), 'step': 'verify', 'token': value})
        self.assertScreen(status, headers, body, expected)
        return body

    def install_form(self, client, body, login='Admin', password=ADMIN_PASSWORD, database=True, extra=None):
        data = {'csrf_token': client.csrf(body), 'step': 'install', 'admin_login': login, 'admin_password': password,
                'admin_password2': password, 'admin_email': '', 'modules': 'none'}
        if database:
            data.update(db_host=self.db['DB_HOST'], db_name=self.db['DB_NAME'], db_user=self.db['DB_USER'],
                        db_pass=self.db['DB_PASS'], db_prefix='')
        data.update(extra or {})
        return client.request(data=data)

    def upgrade_form(self, client, body, value, login='Admin', password=ADMIN_PASSWORD, backup=True):
        data = {'csrf_token': client.csrf(body), 'step': 'upgrade', 'token': value, 'admin_login': login, 'admin_password': password}
        if backup:
            data['backup'] = '1'
        return client.request(data=data)

    def ledger(self):
        return [row['step'] for row in self.query('SELECT step FROM upgrade_steps ORDER BY step')]

    # Tests ----------------------------------------------------------------------------------------------------------

    def test_setup_is_an_empty_404_in_every_non_answering_state(self):
        # Section 3.2: every state but install and upgrade is the S2 guard's empty 404, with no cookie, on GET and POST;
        # an internal file's 404 is the same.
        self.install_with_command()
        config = self.db_config_text
        cases = {
            'installed and current': (lambda: None, config, {}),
            'ahead': (lambda: self.query("INSERT INTO upgrade_steps (step,applied) VALUES ('9999-from-a-newer-version',NOW())"), config, {}),
            'populated': (lambda: self.query("DELETE FROM settings WHERE setting='resurrection_install'"), config, {}),
            'unsupported 1.1.2': (lambda: self.query("INSERT INTO settings (setting,value) VALUES ('installer_version','1.1.2 Dragonprime Edition')"), config, {}),
            'damaged config': (lambda: None, "<?php $DB_PASS = 'x' 'y';\n", {}),
            'incomplete config': (lambda: None, "<?php $DB_HOST = '127.0.0.1:1';\n", {}),
            'database unreachable': (lambda: None, self.config_text(dict(self.db, DB_HOST='127.0.0.1:1')), {}),
            'LOGD_DBCONNECT relative': (lambda: None, None, {'LOGD_DBCONNECT': 'relative/dbconnect.php'}),
        }
        for label, (prepare, config_text, env) in cases.items():
            with self.subTest(label):
                prepare()
                with self.tree(config_text, env=env) as served:
                    client = Client(served.port)
                    self.assertEmpty404(client.request(), label)
                    self.assertEmpty404(client.request(data={'step': 'token'}), label)
                    self.assertEmpty404(client.request(data={'step': 'maintenance', 'maintenance': 'web'}, headers={'Cookie': 'rzsetup=' + 'a' * 32}), label)
                    self.assertEmpty404(client.request('lib/setup/token.php'), label + ' internal file', setup=False)
                    self.assertEqual([], list(served.sessions.iterdir()), label)
        # Out of order: a later step is recorded and an earlier one is not.
        self.query("DELETE FROM upgrade_steps WHERE step='9999-from-a-newer-version'")
        self.query("INSERT INTO settings (setting,value) VALUES ('resurrection_install','complete')")
        self.query("DELETE FROM settings WHERE setting='installer_version'")
        self.fixture_step(9001, '')
        later = self.fixture_step(9002, '')
        self.query('INSERT INTO upgrade_steps (step,applied) VALUES (?,NOW())', [later])
        with self.tree(config) as served:
            self.assertEmpty404(Client(served.port).request(), 'out of order')
        # And the same database with the earlier step pending is the upgrade mode.
        self.query('DELETE FROM upgrade_steps WHERE step=?', [later])
        with self.tree(config) as served:
            status, headers, body = Client(served.port).request()
            self.assertScreen(status, headers, body)
            self.assertIn('This game\'s database is behind its code.', text(body))

    def test_setup_token_file_requested_directly_is_an_empty_404(self):
        # T9: the token file and the failure counter start with a line that ends PHP with an empty 404.
        self.empty_db()
        value = token_value('direct')
        with self.tree() as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            for _ in range(2):
                body = self.verify(client, body, token_value('wrong'), 403)
            self.assertTrue((served.path / 'resurrection-setup-failures.php').read_text().startswith(GUARD + '\n'))
            for name in ['resurrection-setup.php', 'resurrection-setup-failures.php']:
                status, headers, raw = client.request(name)
                self.assertEqual(404, status, name)
                self.assertEqual('', raw, name)
                self.assertNotIn(value, raw)

    def test_setup_install_refusals(self):
        # T1, T5, expiry and format: each refusal is fixed text, changes nothing, and shows the token step again.
        self.empty_db()
        value = token_value('refusals')
        cases = [
            ('missing', None, 'There is no resurrection-setup.php in'),
            ('wrong value', dict(), 'The value you typed is not the one in the file.'),
            ('expired', dict(age=3700), 'is older than 60 minutes'),
            ('ahead', dict(age=-900), 'more than 5 minutes in the future'),
            ('no guard line', dict(lines=value + '\n'), 'does not hold the expected lines'),
            ('other third line', dict(lines=GUARD + '\n' + value + '\nhttps\n'), 'does not hold the expected lines'),
            ('oversize', dict(lines=GUARD + '\n' + value + '\n' + 'x' * 100), 'does not hold the expected lines'),
            ('low diversity', dict(lines=GUARD + '\nrzsetup-' + 'ab' * 16 + '\n'), 'too few different characters'),
        ]
        with self.tree() as served:
            for label, options, message in cases:
                with self.subTest(label):
                    (served.path / 'resurrection-setup.php').unlink(missing_ok=True)
                    (served.path / 'resurrection-setup-failures.php').unlink(missing_ok=True)
                    if options is not None:
                        self.token_file(served.path, value, **options)
                    client = Client(served.port)
                    body = self.start(client)
                    typed = token_value('other') if label == 'wrong value' else ('rzsetup-' + 'ab' * 16 if label == 'low diversity' else value)
                    body = self.verify(client, body, typed, 403)
                    self.assertIn(message, text(body))
                    self.assertIn('Prove you control the site\'s files', text(body))
                    self.assertNotIn('admin_password', body)
            self.assertEqual([], self.tables())
            self.assertFalse((served.path / 'dbconnect.php').exists())
            # A database that is not empty: refused by the service, nothing written.
            self.query('CREATE TABLE fixture_not_empty (id int)')
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.verify(client, self.start(client), value)
            status, headers, body = self.install_form(client, body)
            self.assertScreen(status, headers, body, 409)
            self.assertIn('Installation refused. The database is not empty. A fresh install needs an empty database. Nothing was changed.', text(body))
            self.assertEqual(['fixture_not_empty'], self.tables())
            self.assertFalse((served.path / 'dbconnect.php').exists())
            self.assertTrue((served.path / 'resurrection-setup.php').exists())
            # The passwords differ: refused before the service, the form again without any password.
            status, headers, body = self.install_form(client, body, extra={'admin_password2': 'Another password 99'})
            self.assertScreen(status, headers, body, 400)
            self.assertIn('The two passwords differ. Nothing was changed.', text(body))
            self.assertNotIn(ADMIN_PASSWORD, body)
            self.assertNotIn(self.db['DB_PASS'], body)
            self.assertIn('value="' + self.db['DB_HOST'] + '"', body)

    def test_setup_get_changes_nothing_and_post_needs_csrf(self):
        # Section 3.8, T14: a GET only shows; every change is a POST with the session's CSRF token.
        self.empty_db()
        value = token_value('csrf')
        with self.tree() as served:
            self.token_file(served.path, value)
            client, other = Client(served.port), Client(served.port)
            for _ in range(3):
                status, headers, body = client.request()
                self.assertScreen(status, headers, body)
            self.assertEqual(['resurrection-setup.php'], sorted(p.name for p in served.path.iterdir() if not p.is_symlink() and not p.name.startswith('.')))
            status, headers, body = client.request(data={'step': 'verify', 'token': value})
            self.assertScreen(status, headers, body, 403)
            self.assertIn('Invalid form submission', text(body))
            _, _, foreign = other.request()
            status, headers, body = client.request(data={'csrf_token': other.csrf(foreign), 'step': 'verify', 'token': value})
            self.assertScreen(status, headers, body, 403)
            # The install step without a grant (the token step skipped): refused, nothing changed.
            _, _, body = client.request()
            status, headers, body = self.install_form(client, body)
            self.assertScreen(status, headers, body, 403)
            self.assertIn('The setup session ended', text(body))
            self.assertEqual([], self.tables())
            self.assertFalse((served.path / 'dbconnect.php').exists())
            # A query string never matters: the forms post to plain setup.php.
            self.assertNotIn('action="setup.php?', ''.join(page[2] for page in client.pages))

    def test_setup_install_end_to_end(self):
        # B and single use: the config written 0600, the game installed and playable, the token file deleted and its
        # hash recorded, then setup.php is the empty 404. The last screen offers maintenance; the command path is kept.
        self.empty_db()
        value = token_value('end to end')
        with self.tree() as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            cookie_before = [c.value for c in client.jar if c.name == 'rzsetup']
            body = self.verify(client, body, value)
            self.assertIn('name="db_pass"', body)
            status, headers, body = self.install_form(client, body)
            self.assertScreen(status, headers, body)
            shown = text(body)
            self.assertIn('The game is installed.', shown)
            self.assertIn('The first administrator is Admin .', shown)
            self.assertIn('The token file was deleted', shown)
            config = served.path / 'dbconnect.php'
            self.assertEqual(0o600, config.stat().st_mode & 0o777)
            self.assertFalse((served.path / 'resurrection-setup.php').exists())
            # The first 96 bits of its SHA-256 (a setting holds 255 characters; ten are kept).
            used = self.query("SELECT value FROM settings WHERE setting='setup_tokens_used'")[0]['value']
            self.assertEqual(hashlib.sha256(value.encode()).hexdigest()[:24], used)
            self.assertEqual('complete', self.query("SELECT value FROM settings WHERE setting='resurrection_install'")[0]['value'])
            self.assertEqual(1, int(self.query("SELECT superuser & 1 AS m FROM accounts WHERE login='Admin'")[0]['m']))
            # The session id was rotated when the token was accepted (T16).
            self.assertNotEqual(cookie_before, [c.value for c in client.jar if c.name == 'rzsetup'])
            # The maintenance choice: the command path keeps web maintenance off.
            status, headers, chosen = client.request(data={'csrf_token': client.csrf(body), 'step': 'maintenance', 'maintenance': 'cron'})
            self.assertScreen(status, headers, chosen)
            self.assertIn('Web maintenance stays off.', text(chosen))
            self.assertEqual([], self.query("SELECT value FROM settings WHERE setting='webcron_enabled' AND value='1'"))
            # Used once: the same choice again, and any setup request, is the empty 404 now.
            self.assertEmpty404(client.request(data={'csrf_token': client.csrf(body), 'step': 'maintenance', 'maintenance': 'web'}))
            self.assertEmpty404(client.request())
            self.assertEmpty404(Client(served.port).request())
            # The game plays: its home page, and the administrator signs in.
            game = Client(served.port)
            status, _, home = game.request('home.php')
            self.assertEqual(200, status, home[:1000])
            self.assertNotIn('has not yet been installed', home)
            status, _, _ = game.request('login.php', {'csrf_token': game.csrf(home), 'name': 'Admin', 'password': ADMIN_PASSWORD})
            self.assertEqual(303, status)

    def test_setup_install_from_the_release_package(self):
        # Phase 6 task 16 (R1): what a host does with the package. Build it, unzip it, copy only the contents of upload/
        # into an empty site folder, install through setup.php, and play to the village. Nothing from the checkout is
        # served, so a file the game needs and the package leaves out fails here.
        self.empty_db()
        version = re.search(r"CURRENT = '([^']+)'", (ROOT / 'src' / 'Version.php').read_text()).group(1)
        top = f'legend-of-the-green-dragon-resurrection-{version}'
        with tempfile.TemporaryDirectory(prefix='resurrection-package-') as scratch:
            scratch = Path(scratch)
            built = subprocess.run([sys.executable, str(ROOT / 'scripts' / 'build-release-package.py'), '--out', str(scratch / 'out')],
                                   cwd=ROOT, capture_output=True, text=True, timeout=300)
            self.assertEqual(0, built.returncode, built.stderr)
            with zipfile.ZipFile(scratch / 'out' / f'{top}.zip') as archive:
                archive.extractall(scratch / 'unzipped')
            upload = scratch / 'unzipped' / top / 'upload'
            self.assertTrue((upload / 'setup.php').is_file(), sorted(p.name for p in (scratch / 'unzipped').rglob('*'))[:20])
            value = token_value('from the package')
            with self.tree(source=upload) as served:
                self.assertFalse((served.path / 'dbconnect.php').exists())
                self.assertEqual([], sorted(p.name for p in served.path.glob('*.md')))
                self.token_file(served.path, value)
                client = Client(served.port)
                body = self.verify(client, self.start(client), value)
                # The recommended modules, as a host installs by default: they bring the races a new day asks for.
                status, headers, body = self.install_form(client, body, extra={'modules': 'recommended'})
                self.assertScreen(status, headers, body)
                self.assertIn('The game is installed.', text(body))
                self.assertFalse((served.path / 'resurrection-setup.php').exists())
                self.assertEmpty404(Client(served.port).request())
                game = Client(served.port)
                status, _, home = game.request('home.php')
                self.assertEqual(200, status, home[:1000])
                status, headers, _ = game.request('login.php', {'csrf_token': game.csrf(home), 'name': 'Admin', 'password': ADMIN_PASSWORD})
                self.assertEqual(303, status)
                path, visited = headers['Location'], []
                for _ in range(12):
                    status, headers, body = game.request(path.lstrip('/'))
                    visited.append((path, status))
                    if status in (302, 303):
                        path = headers['Location']
                        continue
                    self.assertEqual(200, status, body[:1000])
                    if path.startswith('village.php') or path.startswith('/village.php'):
                        break
                    path = self.next_nav(game, body, ['newday.php', 'village.php'])
                    self.assertIsNotNone(path, (visited, text(body)[:1500]))
                self.assertTrue(visited[-1][0].lstrip('/').startswith('village.php'), visited)
                self.assertNotRegex(body, ERROR)
                self.assertIn('forest.php', body)
                self.assertIn('Version: Resurrection ' + version + ',', text(body))
                for credit in [COPYRIGHT, ATTRIBUTION]:
                    self.assertIn(credit, body)
                self.assertEqual('', served.log.read_text())

    @staticmethod
    def next_nav(client, body, targets):
        """Follows the first navigation to one of the targets on a game page, a link or a button form (a POST with the
        form's fields). Returns the path a link points to, or, for a form, the redirect it answered with."""
        for target in targets:
            for form in re.finditer(r'(?s)<form[^>]*action="([^"]*)"[^>]*>(.*?)</form>', body):
                action = html.unescape(form.group(1))
                if target in action:
                    fields = {html.unescape(m.group(1)): html.unescape(m.group(2)) for m in
                              re.finditer(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', form.group(2))}
                    status, headers, _ = client.request(action.lstrip('/'), fields)
                    return headers['Location'] if status in (302, 303) else action
            link = re.search(r'href="([^"]*' + re.escape(target) + r'[^"]*)"', body)
            if link:
                return html.unescape(link.group(1))
        return None

    def test_setup_install_with_existing_config_and_empty_database(self):
        # State 2: a configuration file made by hand and an empty database. The form has no database fields and the
        # install uses the file as it is; the page never writes over it.
        self.empty_db()
        value = token_value('existing')
        with self.tree(self.db_config_text) as served:
            before = (served.path / 'dbconnect.php').read_bytes()
            self.token_file(served.path, value)
            client = Client(served.port)
            status, headers, body = client.request()
            self.assertIn('Configuration file', text(body))
            body = self.verify(client, self.start(client), value)
            self.assertNotIn('name="db_pass"', body)
            self.assertIn('its database is empty, so the install uses it', text(body))
            status, headers, body = self.install_form(client, body, database=False)
            self.assertScreen(status, headers, body)
            self.assertIn('The existing configuration file was used.', text(body))
            self.assertEqual(before, (served.path / 'dbconnect.php').read_bytes())
            self.assertEmpty404(Client(served.port).request())

    def test_setup_concurrent_installs_one_wins(self):
        # T4: two servers, two browsers, both past the token step with the same file; both press Install at once. One
        # install wins; the other is refused, and nothing is done twice.
        self.empty_db()
        value = token_value('race')
        with self.tree() as served:
            self.token_file(served.path, value)
            with self.server(served) as second:
                clients = [Client(served.port), Client(second)]
                forms = [self.verify(c, self.start(c), value) for c in clients]
                results = [None, None]
                barrier = threading.Barrier(2)
                def press(index):
                    barrier.wait()
                    results[index] = self.install_form(clients[index], forms[index], login=['Alpha', 'Bravo'][index])
                threads = [threading.Thread(target=press, args=(i,)) for i in range(2)]
                for thread in threads:
                    thread.start()
                for thread in threads:
                    thread.join(timeout=180)
            winners = [r for r in results if r[0] == 200]
            self.assertEqual(1, len(winners), [(r[0], text(r[2])[:300]) for r in results])
            loser = [r for r in results if r[0] != 200][0]
            self.assertEqual(409, loser[0], text(loser[2])[:500])
            self.assertIn('Installation refused', text(loser[2]))
            admins = [row['login'] for row in self.query('SELECT login FROM accounts ORDER BY acctid')]
            self.assertEqual(1, len(admins), admins)
            self.assertIn(admins[0], winners[0][2])
            self.assertEqual(1, len(self.query("SELECT value FROM settings WHERE setting='resurrection_install'")))

    def test_setup_upgrade_needs_token_megauser_and_backup_box(self):
        # Decision 1, section 3.7, T3, T13: the token, an SU_MEGAUSER login and password, and the backup box. Each
        # refusal changes nothing; failures are faillog rows with fixed words. A used token is refused after.
        self.install_with_command()
        code = ("chdir(getenv('ROOT')); define('RESURRECTION_ENTRY', true); ob_start(); require 'lib/dbwrapper_pdo.php'; require 'lib/constants.php';"
                " require 'lib/datacache.php'; require 'lib/settings.php'; require 'lib/accounts.php'; ob_end_clean(); require getenv('CONFIG');"
                " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); $id=resurrection_create_account('Staff', getenv('PASS'));"
                " db_query('UPDATE accounts SET superuser=? WHERE acctid=?', true, [SU_HIDE_FROM_LEADERBOARD & ~SU_MEGAUSER, $id]);")
        result = subprocess.run([self.php, '-r', code], capture_output=True, text=True, timeout=60,
                                env=dict(os.environ, ROOT=str(ROOT), CONFIG=str(self.db_config), PASS=ADMIN_PASSWORD))
        self.assertEqual(0, result.returncode, result.stderr + result.stdout)
        step = self.fixture_step(9101, "db_query('CREATE TABLE IF NOT EXISTS fixture_setup_upgrade (id int)');")
        value = token_value('upgrade')
        with self.tree(self.db_config_text) as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            shown = text(body)
            self.assertIn('Pending: - step ' + step + ': Setup fixture step', shown)
            self.assertIn('I have taken a backup of the database just now.', shown)
            cases = [('no backup box', dict(backup=False), 400, 'Take a fresh backup of the database first', 0),
                     ('wrong token', dict(value=token_value('nope')), 403, 'The value you typed is not the one in the file.', 1),
                     ('wrong password', dict(password='Not the password 1'), 403, 'The administrator login or password is wrong', 1),
                     ('unknown login', dict(login='Nobody'), 403, 'The administrator login or password is wrong', 1),
                     ('not SU_MEGAUSER', dict(login='Staff'), 403, 'The administrator login or password is wrong', 1)]
            for label, options, expected, message, logged in cases:
                with self.subTest(label):
                    before = int(self.query('SELECT COUNT(*) AS n FROM faillog')[0]['n'])
                    status, headers, body = self.upgrade_form(client, body, options.get('value', value), options.get('login', 'Admin'),
                                                              options.get('password', ADMIN_PASSWORD), options.get('backup', True))
                    self.assertScreen(status, headers, body, expected)
                    self.assertIn(message, text(body))
                    self.assertNotIn('fixture_setup_upgrade', self.tables())
                    self.assertNotIn(step, self.ledger())
                    rows = self.query('SELECT post,ip,acctid,id FROM faillog ORDER BY eventid DESC LIMIT 1') if logged else []
                    self.assertEqual(before + logged, int(self.query('SELECT COUNT(*) AS n FROM faillog')[0]['n']))
                    if logged:
                        self.assertIn(rows[0]['post'], ['setup_invalid_token', 'setup_invalid_credentials'])
                        self.assertEqual('127.0.0.1', rows[0]['ip'])
                        self.assertRegex(rows[0]['id'], r'\A[0-9a-f]{16}\Z')
                    self.assertNotIn(ADMIN_PASSWORD, body)
            status, headers, body = self.upgrade_form(client, body, value)
            self.assertScreen(status, headers, body)
            self.assertIn('The database is current. Applied 1 of 1.', text(body))
            self.assertIn(step, self.ledger())
            self.assertFalse((served.path / 'resurrection-setup.php').exists())
            self.assertIn(hashlib.sha256(value.encode()).hexdigest()[:24], self.query("SELECT value FROM settings WHERE setting='setup_tokens_used'")[0]['value'].split(' '))
            self.assertEmpty404(client.request())
            # T3: a new pending step and the used token put back: refused as used.
            self.fixture_step(9102, '')
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            status, headers, body = self.upgrade_form(client, body, value)
            self.assertScreen(status, headers, body, 403)
            self.assertIn('This token has been used already.', text(body))

    def test_setup_upgrade_in_parts_and_after_a_killed_step(self):
        # Section 3.6, T7: with max_execution_time 4 the budget is 2 seconds, so the upgrade runs in parts and stops
        # only between steps. A step cut off by the host's time limit is not recorded; the next Continue runs it again,
        # and it checks for its own change first.
        self.install_with_command()
        marker = self.work_path / 'killed-once'
        marker.unlink(missing_ok=True)
        steps = [self.fixture_step(9201, 'usleep(1200000);'), self.fixture_step(9202, 'usleep(1200000);'),
                 self.fixture_step(9203, "db_query('CREATE TABLE IF NOT EXISTS fixture_setup_killed (id int)');\n"
                                         "if (!file_exists(" + json.dumps(str(marker)) + ")) { touch(" + json.dumps(str(marker)) + "); set_time_limit(1); for (;;) {} }"),
                 self.fixture_step(9204, '')]
        value = token_value('parts')
        with self.tree(self.db_config_text, ini={'max_execution_time': 4}) as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            status, headers, body = self.upgrade_form(client, body, value)
            self.assertScreen(status, headers, body)
            self.assertIn('Applied 2 of 4. Continue.', text(body))
            self.assertEqual(steps[:2], [s for s in self.ledger() if s in steps])
            # The killed step: the request dies (the host's limit), the step is not recorded, its change stays.
            status, _, _ = client.request(data={'csrf_token': client.csrf(body), 'step': 'continue'})
            self.assertEqual(500, status)
            self.assertTrue(marker.exists())
            self.assertEqual(steps[:2], [s for s in self.ledger() if s in steps])
            self.assertIn('fixture_setup_killed', self.tables())
            self.assertIn('Maximum execution time', served.log.read_text())
            # Opening setup.php again shows what remains; Continue finishes.
            status, headers, body = client.request()
            self.assertScreen(status, headers, body)
            self.assertIn('Applied 2 of 4. Continue.', text(body))
            status, headers, body = client.request(data={'csrf_token': client.csrf(body), 'step': 'continue'})
            self.assertScreen(status, headers, body)
            self.assertIn('The database is current. Applied 4 of 4.', text(body))
            self.assertEqual(steps, [s for s in self.ledger() if s in steps])
            self.assertEmpty404(client.request())

    def test_setup_never_echoes_or_logs_secrets(self):
        # Section 3.3, T17, step 0: after an install, the web maintenance address, and an upgrade, the token, the
        # database password, the administrator's password and the maintenance secret are in no response (but the one
        # page that shows the address once), no PHP error log line, no session file and no database table.
        self.empty_db()
        install_token, upgrade_token = token_value('secret install'), token_value('secret upgrade')
        with self.tree() as served:
            self.token_file(served.path, install_token)
            client = Client(served.port)
            body = self.verify(client, self.start(client), token_value('typo'), 403)
            body = self.verify(client, body, install_token)
            self.install_form(client, body, extra={'admin_password2': 'Different password 42'})
            status, headers, body = self.install_form(client, client.pages[-1][2])
            self.assertEqual(200, status, text(body)[:1000])
            status, headers, shown = client.request(data={'csrf_token': client.csrf(body), 'step': 'maintenance', 'maintenance': 'web'})
            self.assertScreen(status, headers, shown)
            secret = re.search(r'id="rz-webcron-key" class="rz-webcron-value" value="([A-Za-z0-9_-]{43})"', shown).group(1)
            self.assertIn('webcron.php?key=' + secret, shown)
            self.assertEqual(hashlib.sha256(secret.encode()).hexdigest(), self.query("SELECT value FROM settings WHERE setting='webcron_secret_hash'")[0]['value'])
            # The address works.
            status, _, ok = Client(served.port).request('webcron.php', headers={'X-Resurrection-Maintenance-Key': secret})
            self.assertEqual((200, 'OK\n'), (status, ok))
            # An upgrade with its own token and the administrator's password.
            self.fixture_step(9301, '')
            self.token_file(served.path, upgrade_token)
            upgrade = Client(served.port)
            body = self.start(upgrade)
            self.upgrade_form(upgrade, body, upgrade_token, password='Wrong password 1234')
            status, headers, body = self.upgrade_form(upgrade, upgrade.pages[-1][2], upgrade_token)
            self.assertIn('The database is current.', text(body))
            secrets = {'install token': install_token, 'upgrade token': upgrade_token, 'database password': self.db['DB_PASS'],
                       'administrator password': ADMIN_PASSWORD, 'maintenance secret': secret}
            responses = [((who, page), str(headers) + body) for who, pages in (('install', client.pages), ('upgrade', upgrade.pages))
                         for page, (status, headers, body) in enumerate(pages)]
            for label, needle in secrets.items():
                with self.subTest(label):
                    hits = [where for where, page in responses if needle in page]
                    self.assertEqual([('install', len(client.pages) - 1)] if label == 'maintenance secret' else [], hits)
                    self.assertNotIn(needle, served.log.read_text())
                    for session in served.sessions.iterdir():
                        self.assertNotIn(needle, session.read_text(errors='replace'), session.name)
            self.assertEqual({}, self.search_db(list(secrets.values())))

    def test_setup_headers_policy_and_credits_on_every_screen(self):
        # Section 3.8, T15: every screen (assertScreen, on each page of the install and its refusals here and in the
        # other tests) has the enforced policy, no-store, noindex, no inline script or style, and the full credits.
        self.empty_db()
        value = token_value('screens')
        with self.tree() as served:
            client = Client(served.port)
            body = self.start(client)
            body = self.verify(client, body, value, 403)
            self.token_file(served.path, value)
            body = self.verify(client, body, value)
            status, headers, body = self.install_form(client, body, password='short')
            self.assertScreen(status, headers, body, 400)
            self.assertIn('Password must contain 12 to 72 bytes', text(body))
            status, headers, body = self.install_form(client, body)
            self.assertScreen(status, headers, body)
            for status, headers, page in client.pages:
                if status != 404:
                    self.assertScreen(status, headers, page, status)
            # The copy button and the busy note are data attributes for behaviors.js, hidden without it.
            self.assertIn('data-rz-copy="rz-setup-file"', client.pages[1][2])
            self.assertIn('data-rz-busy="rz-setup-busy"', client.pages[3][2])
            self.assertIn('<p class="rz-setup-busy" id="rz-setup-busy" role="status" hidden>', client.pages[3][2])

    def test_setup_refuses_plain_http(self):
        # Decision 5: plain HTTP is refused unless REMOTE_ADDR is this machine; the Host header never counts.
        self.empty_db()
        with self.tree() as served:
            for label, headers, expected in [('remote', {'X-Test-Remote-Addr': '203.0.113.9'}, 403),
                                             ('remote naming localhost', {'X-Test-Remote-Addr': '203.0.113.9', 'Host': 'localhost'}, 403),
                                             ('remote, forwarded header without the line', {'X-Test-Remote-Addr': '203.0.113.9', 'X-Forwarded-Proto': 'https'}, 403),
                                             ('remote over HTTPS', {'X-Test-Remote-Addr': '203.0.113.9', 'X-Test-Https': 'on'}, 200),
                                             ('HTTPS off', {'X-Test-Remote-Addr': '203.0.113.9', 'X-Test-Https': 'off'}, 403),
                                             ('IPv6 loopback', {'X-Test-Remote-Addr': '::1'}, 200),
                                             ('loopback', {}, 200)]:
                with self.subTest(label):
                    client = Client(served.port, headers)
                    status, answer, body = client.request()
                    self.assertScreen(status, answer, body, expected)
                    if expected == 403:
                        self.assertIn('This page needs HTTPS', text(body))
                        self.assertIn('https-terminated-upstream', text(body))
                        self.assertIsNone(answer['Set-Cookie'])
                    # A POST without the form's CSRF token is refused either way.
                    status, answer, body = client.request(data={'step': 'token'})
                    self.assertEqual(403, status)

    def test_setup_forwarded_proto_trusted_only_with_the_third_line(self):
        # T23: X-Forwarded-Proto: https counts only while the live token file carries the exact third line.
        self.empty_db()
        value = token_value('upstream')
        remote = {'X-Test-Remote-Addr': '203.0.113.9', 'X-Forwarded-Proto': 'https'}
        with self.tree() as served:
            for label, options, expected in [('two lines', {}, 403), ('third line', {'upstream': True}, 200),
                                             ('third line, expired', {'upstream': True, 'age': 3700}, 403),
                                             ('another third line', {'lines': GUARD + '\n' + value + '\nhttps-terminated\n'}, 403)]:
                with self.subTest(label):
                    self.token_file(served.path, value, **options)
                    status, headers, body = Client(served.port, remote).request()
                    self.assertScreen(status, headers, body, expected)
            self.token_file(served.path, value, upstream=True)
            client = Client(served.port, remote)
            body = self.verify(client, self.start(client), value)
            self.assertIn('name="db_pass"', body)
            self.assertIn('; secure;', client.pages[0][1]['Set-Cookie'].lower())
            # The same header without it is refused at once.
            (served.path / 'resurrection-setup.php').write_text(GUARD + '\n' + value + '\n')
            status, headers, body = client.request()
            self.assertScreen(status, headers, body, 403)

    def test_setup_upgrade_login_check_runs_before_any_step(self):
        # Decision 1's amendment, T22: the login check runs once, before any step, on the oldest layout an upgrade
        # starts from: a completed install without the upgrade ledger (before the upgrade system), with a pending step
        # that changes the accounts table. A refused login applies nothing; an accepted one upgrades.
        self.install_with_command()
        self.query('DROP TABLE upgrade_steps')
        step = self.fixture_step(9401, "$c = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');"
                                       " if (!in_array('fixture_setup_column', $c, true)) db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . ' ADD COLUMN fixture_setup_column int NOT NULL DEFAULT 0');")
        value = token_value('layout')
        accounts = lambda: [row['Field'] for row in self.query('SHOW COLUMNS FROM accounts')]
        with self.tree(self.db_config_text) as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            self.assertIn('create the upgrade ledger', text(body))
            status, headers, body = self.upgrade_form(client, body, value, password='Wrong password 1234')
            self.assertScreen(status, headers, body, 403)
            self.assertNotIn('upgrade_steps', self.tables())
            self.assertNotIn('fixture_setup_column', accounts())
            status, headers, body = self.upgrade_form(client, body, value)
            self.assertScreen(status, headers, body)
            self.assertIn('The database is current.', text(body))
            self.assertIn('fixture_setup_column', accounts())
            self.assertIn(step, self.ledger())

    def test_setup_failure_limit(self):
        # Section 3.9: 10 failures in 15 minutes against one token file, then every attempt against it is refused;
        # a new token file starts a new count. Install mode keeps the count in the guarded counter file, upgrade mode
        # in faillog.
        self.empty_db()
        value = token_value('limit')
        with self.tree() as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            for _ in range(10):
                body = self.verify(client, body, token_value('guess'), 403)
            status, headers, body = client.request(data={'csrf_token': client.csrf(body), 'step': 'verify', 'token': value})
            self.assertScreen(status, headers, body, 429)
            self.assertIn('Too many failed attempts against this token file.', text(body))
            counter = (served.path / 'resurrection-setup-failures.php').read_text().splitlines()
            self.assertEqual(GUARD, counter[0])
            self.assertRegex(counter[1], r'\A[0-9a-f]{16} \d+ 10\Z')
            fresh = token_value('limit, new file')
            self.token_file(served.path, fresh)
            body = self.verify(client, self.start(client), fresh)
            self.assertIn('The token is accepted.', text(body))
        self.install_with_command()
        self.fixture_step(9501, '')
        with self.tree(self.db_config_text) as served:
            self.token_file(served.path, value)
            client = Client(served.port)
            body = self.start(client)
            for _ in range(10):
                status, _, body = self.upgrade_form(client, body, value, password='Wrong password 1234')
                self.assertEqual(403, status)
            status, headers, body = self.upgrade_form(client, body, value)
            self.assertScreen(status, headers, body, 429)
            rows = self.query("SELECT post,id FROM faillog WHERE post LIKE 'setup_%'")
            self.assertEqual(10, len(rows))
            self.assertEqual({'setup_invalid_credentials'}, {row['post'] for row in rows})
            self.assertFalse((served.path / 'resurrection-setup-failures.php').exists())


if __name__ == '__main__':
    unittest.main()
