"""Real loopback application requests against the preceding clean CI install."""
import base64
import html
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]

class WebApplicationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        host = os.environ.get('RESURRECTION_TEST_DB_HOST')
        if not host:
            raise unittest.SkipTest('Requires the disposable installed CI database')
        if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
            raise RuntimeError('Refusing non-test database')
        cls.config = ROOT / 'dbconnect.php'
        values = {'DB_HOST': host, 'DB_USER': os.environ['RESURRECTION_TEST_DB_USER'],
                  'DB_PASS': os.environ['RESURRECTION_TEST_DB_PASSWORD'],
                  'DB_NAME': 'resurrection_test', 'DB_PREFIX': '',
                  'DB_USEDATACACHE': 0, 'DB_DATACACHEPATH': ''}
        encoded = base64.b64encode(json.dumps(values).encode()).decode()
        with cls.config.open('x') as file:
            file.write("<?php extract(json_decode(base64_decode('" + encoded + "'),true), EXTR_SKIP);\n")
        cls.config.chmod(0o600)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        cls.server = subprocess.Popen([shutil.which('php'), '-d', 'display_errors=1', '-d', 'error_reporting=-1',
                                       '-d', 'zend.exception_ignore_args=1', '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT)],
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, cwd=ROOT)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.1):
                    return
            except OSError:
                if cls.server.poll() is not None:
                    cls.config.unlink()
                    raise RuntimeError('Application test server exited')
                time.sleep(.05)
        cls.server.terminate()
        cls.config.unlink()
        raise RuntimeError('Application test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.config.unlink()

    @staticmethod
    def query(sql, parameters=()):
        code = "require 'dbconnect.php'; require 'lib/dbwrapper_pdo.php'; db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); echo json_encode(db_query($input[0],true,$input[1]),JSON_THROW_ON_ERROR);"
        result = subprocess.run([shutil.which('php'), '-r', code], input=json.dumps([sql, parameters]), cwd=ROOT,
                                capture_output=True, text=True, timeout=20)
        if result.returncode != 0:
            raise AssertionError('Fixture database operation failed')
        return json.loads(result.stdout)

    def test_active_maintenance_failures_retries_and_concurrency(self):
        path = ROOT / 'modules' / 'resurrectionmaintenancefixture.php'
        signal = ROOT / 'tests' / 'fixtures' / 'maintenance-running'
        module = 'resurrectionmaintenancefixture'
        with path.open('x') as file:
            file.write('''<?php
function resurrectionmaintenancefixture_getmoduleinfo() {
    return ['name'=>'Maintenance fixture','version'=>'1.0','author'=>'Synthetic','category'=>'Tests'];
}
function resurrectionmaintenancefixture_dohook($hook, $args) {
    $mode = getsetting('fixture_maintenance_mode', 'ok');
    db_query("UPDATE settings SET value=value+1 WHERE setting='fixture_maintenance_count'");
    if ($mode === 'throw') throw new RuntimeException('SYNTHETIC-SECRET-MUST-NOT-LOG');
    if ($mode === 'malformed') return 'invalid';
    if ($mode === 'database') db_query('INSERT INTO nonexistent_fixture_table VALUES (1)');
    if ($mode === 'slow') {
        file_put_contents('tests/fixtures/maintenance-running', 'running');
        usleep(2000000);
    }
    return $args;
}
''')
        def setting(name, value):
            self.query('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [name, str(value)])
        def run():
            return subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        try:
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ?', ['maintenance_day', 'maintenance-hook-%'])
            self.query('UPDATE modules SET active=1')  # Lifecycle independently exercised by PHPUnit.
            self.query('INSERT INTO modules (modulename,active,version) VALUES (?,1,?)', [module, '1.0'])
            self.query('INSERT INTO module_hooks (modulename,location,`function`,whenactive,priority) VALUES (?,?,?,?,?)',
                       [module, 'newday-runonce', module + '_dohook', '', 100])
            self.query('UPDATE module_settings SET value=3 WHERE modulename=? AND setting=?', ['crazyaudrey', 'gamedaysremaining'])
            setting('fixture_maintenance_count', 0)
            for mode in ['throw', 'malformed', 'database']:
                setting('fixture_maintenance_mode', mode)
                failed = run()
                self.assertEqual(1, failed.returncode, failed.stdout)
                self.assertNotIn('SYNTHETIC-SECRET', failed.stdout + failed.stderr)
                self.assertNotIn('nonexistent_fixture_table', failed.stdout + failed.stderr)
                self.assertEqual([], self.query('SELECT value FROM settings WHERE setting=?', ['maintenance_day']))
                self.assertEqual('0', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maintenance_count'])[0]['value'])
                # Earlier committed active module is not repeated when a later one fails.
                self.assertEqual('2', self.query('SELECT value FROM module_settings WHERE modulename=? AND setting=?', ['crazyaudrey', 'gamedaysremaining'])[0]['value'])
            setting('fixture_maintenance_mode', 'ok')
            finished = run()
            self.assertEqual(0, finished.returncode, finished.stderr)
            self.assertEqual('complete', json.loads(finished.stdout)['maintenance'])
            self.assertEqual('1', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maintenance_count'])[0]['value'])
            duplicate = run()
            self.assertEqual('already-complete', json.loads(duplicate.stdout)['maintenance'])
            self.assertEqual('1', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maintenance_count'])[0]['value'])
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ?', ['maintenance_day', 'maintenance-hook-%'])
            setting('fixture_maintenance_mode', 'slow')
            first = subprocess.Popen([shutil.which('php'), 'cron.php'], cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            try:
                for _ in range(100):
                    if signal.exists() or first.poll() is not None:
                        break
                    time.sleep(.05)
                self.assertTrue(signal.exists(), 'First scheduler did not enter its locked active hook')
                second = run()
                self.assertEqual(1, second.returncode)
                out, err = first.communicate(timeout=60)
                self.assertEqual(0, first.returncode, err)
                self.assertEqual('complete', json.loads(out)['maintenance'])
                self.assertEqual('2', self.query('SELECT value FROM settings WHERE setting=?', ['fixture_maintenance_count'])[0]['value'])
            finally:
                if first.poll() is None:
                    first.terminate()
                    first.wait(timeout=5)
        finally:
            path.unlink()
            signal.unlink(missing_ok=True)
            self.query('DELETE FROM module_hooks WHERE modulename=?', [module])
            self.query('DELETE FROM modules WHERE modulename=?', [module])
            self.query('UPDATE modules SET active=0')
            self.query('DELETE FROM settings WHERE setting=? OR setting LIKE ? OR setting LIKE ?',
                       ['maintenance_day', 'maintenance-hook-%', 'fixture_maintenance_%'])

    def test_cli_maintenance_is_once_per_game_day(self):
        first = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, first.returncode, first.stderr)
        self.assertEqual('complete', json.loads(first.stdout)['maintenance'])
        second = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, second.returncode, second.stderr)
        self.assertEqual('already-complete', json.loads(second.stdout)['maintenance'])

    def test_dispatcher_enforces_module_state(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args):
                return None
        client = urllib.request.build_opener(NoRedirect)
        path = ROOT / 'modules' / 'resurrectionhttpfixture.php'
        module = 'resurrectionhttpfixture'
        with path.open('x') as file:
            file.write('''<?php
function resurrectionhttpfixture_getmoduleinfo() {
    return ['name'=>'HTTP fixture','version'=>'1.0','author'=>'Synthetic','category'=>'Tests',
        'allowanonymous'=>true,'override_forced_nav'=>true,
        'requires'=>getsetting('fixture_dependency',0) ? ['missingdependency'=>'1.0|Missing'] : []];
}
function resurrectionhttpfixture_run() { echo 'fixture-executed'; exit; }
''')
        def request(expected_execution=False):
            try:
                response = client.open(f'http://127.0.0.1:{self.port}/runmodule.php?module={module}&admin=1&force=1', timeout=15)
            except urllib.error.HTTPError as error:
                response = error
            body = response.read().decode()
            self.assertNotRegex(body, r'(?i)(fatal error|warning:|deprecated:|notice:|runtime error in)')
            if expected_execution:
                self.assertEqual(200, response.status)
                self.assertEqual('fixture-executed', body.strip())  # historical includes emit a leading newline
            else:
                self.assertIn(response.status, (302, 303, 403, 404))
                self.assertNotIn('fixture-executed', body)
        try:
            request()  # uninstalled, despite both forged force parameters
            self.query('INSERT INTO modules (modulename,active,version) VALUES (?,0,?)', [module, '1.0'])
            request()  # installed but inactive
            self.query('UPDATE modules SET active=1 WHERE modulename=?', [module])
            request(True)
            self.query('UPDATE settings SET value=? WHERE setting=?', ['1', 'fixture_dependency'])  # getsetting persisted its default
            request()  # active but missing a dependency
        finally:
            path.unlink()
            self.query('DELETE FROM modules WHERE modulename=?', [module])
            self.query('DELETE FROM settings WHERE setting=?', ['fixture_dependency'])

    def test_create_login_rotate_render_logout(self):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args):
                return None
        jar = http.cookiejar.CookieJar()
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)
        def request(path, fields=None, extra_headers=None):
            data = None if fields is None else urllib.parse.urlencode(fields).encode()
            req = urllib.request.Request(f'http://127.0.0.1:{self.port}/' + path, data=data, headers=extra_headers or {})
            try:
                response = client.open(req, timeout=15)
            except urllib.error.HTTPError as response_error:
                response = response_error
            body = response.read().decode('utf-8', errors='replace')
            self.assertNotRegex(body, r'(?i)(fatal error|warning:|deprecated:|notice:|runtime error in)', body[:2500])
            return response.status, response.headers, body
        def token(body):
            match = re.search(r'name=[\'"]csrf_token[\'"] value=[\'"]([a-f0-9]{64})', body)
            self.assertIsNotNone(match, body[:500])
            return match.group(1)
        def comment_form(body):
            class Forms(HTMLParser):
                def __init__(self):
                    super().__init__()
                    self.forms = []
                    self.current = None
                def handle_starttag(self, tag, attrs):
                    attributes = dict(attrs)
                    if tag == 'form':
                        self.current = [attributes.get('action', ''), {}]
                    elif tag == 'input' and self.current is not None and 'name' in attributes:
                        self.current[1][attributes['name']] = attributes.get('value', '')
                def handle_endtag(self, tag):
                    if tag == 'form' and self.current is not None:
                        self.forms.append(self.current)
                        self.current = None
            forms = Forms()
            forms.feed(body)
            return next(form for form in forms.forms if 'insertcommentary' in form[1])
        def issued_link(body, prefix):
            links = re.findall(r'href=[\'"]([^\'"]+)', body)
            found = next((html.unescape(link) for link in links if link.startswith(prefix)), None)
            self.assertIsNotNone(found, 'Missing issued navigation: ' + prefix)
            return found
        def session_id():
            return next(c.value for c in jar if c.name == 'PHPSESSID')

        status, headers, body = request('home.php', extra_headers={'Cookie': 'PHPSESSID=attackerchosenid1234567890123456'})
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        initial_id = session_id()
        self.assertNotEqual('attackerchosenid1234567890123456', initial_id)
        self.assertIn('HttpOnly', headers.get('Set-Cookie', ''))
        self.assertIn('SameSite=Lax', headers.get('Set-Cookie', ''))
        status, _, body = request('create.php')
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        csrf = token(body)
        password = "Synthetic web O'Reilly \\ password"
        status, _, body = request('create.php?op=create', {'csrf_token': csrf, 'name': 'WebPlayer',
                                    'pass1': password, 'pass2': password, 'email': 'web@example.invalid', 'sex': '0'})
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        self.assertIn('Your account was created', body)
        self.assertNotIn(password, body)
        stored_hash = self.query('SELECT password FROM accounts WHERE login=?', ['WebPlayer'])[0]['password']
        for rejected in ['wrong password', stored_hash]:
            status, headers, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password': rejected})
            self.assertEqual(303, status)
            self.assertEqual('index.php', headers['Location'])
            self.assertEqual(initial_id, session_id())
        status, _, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password[]': password})
        self.assertEqual(400, status)
        logs = self.query('SELECT post,id FROM faillog')
        self.assertTrue(logs)
        for record in logs:
            self.assertEqual('invalid_credentials', record['post'])
            self.assertEqual('', record['id'])
        for secret in [password, stored_hash, initial_id, csrf]:
            self.assertNotIn(secret, json.dumps(logs))
        status, headers, _ = request('login.php', {'csrf_token': csrf, 'name': 'WebPlayer', 'password': password})
        self.assertEqual(303, status)
        self.assertEqual('village.php', headers['Location'])
        self.assertNotEqual(initial_id, session_id())
        authenticated_id = session_id()
        status, headers, body = request('village.php')
        # First login follows the game's existing character onboarding, including
        # fallback choices when no races or specialties have been activated.
        if status in (302, 303) and headers['Location'] == 'newday.php':
            status, _, body = request('newday.php')
            self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
            for _ in range(3):
                if 'No Races Installed' not in body and 'No Specialties Installed' not in body:
                    break
                status, headers, body = request(issued_link(body, 'newday.php?continue=1'))
                self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
            status, headers, body = request(issued_link(body, 'village.php'))
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        self.assertIn('WebPlayer', body)
        action, fields = comment_form(body)
        comment = "O'Reilly \\ <script>alert(8675309)</script>"
        fields['insertcommentary'] = comment
        status, _, body = request(action, fields)
        self.assertEqual(200, status)
        self.assertNotIn('<script>alert(8675309)</script>', body)
        rows = self.query('SELECT commentid,comment FROM commentary WHERE author=(SELECT acctid FROM accounts WHERE login=?) ORDER BY commentid DESC LIMIT 1', ['WebPlayer'])
        self.assertEqual(comment, rows[0]['comment'])
        action, fields = comment_form(body)
        status, _, _ = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(403, status)
        self.assertEqual(1, len(self.query('SELECT commentid FROM commentary WHERE commentid=?', [rows[0]['commentid']])))
        # Grant only the documented moderator bit in this disposable fixture.
        self.query('UPDATE accounts SET superuser=16 WHERE login=?', ['WebPlayer'])
        before_privilege_change = session_id()
        status, _, _ = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(403, status)  # privilege refresh rotated the old CSRF token
        self.assertNotEqual(before_privilege_change, session_id())
        status, _, body = request(action)
        self.assertEqual(200, status)
        action, fields = comment_form(body)
        status, _, body = request(action, {'csrf_token': fields['csrf_token'], 'removecomment': rows[0]['commentid']})
        self.assertEqual(200, status)
        self.assertEqual([], self.query('SELECT commentid FROM commentary WHERE commentid=?', [rows[0]['commentid']]))
        authenticated_id = session_id()


        status, _, body = request('login.php?op=logout')
        self.assertEqual(200, status, headers.get('Location', 'Unexpected HTTP status'))
        status, headers, _ = request('login.php?op=logout', {'csrf_token': token(body)})
        self.assertEqual(303, status)
        self.assertFalse(any(c.name == 'PHPSESSID' for c in jar))
        status, headers, _ = request('village.php')
        self.assertIn(status, (302, 303))
        self.assertIn('index.php', headers['Location'])
        status, headers, _ = request('village.php', extra_headers={'Cookie': 'PHPSESSID=' + authenticated_id})
        self.assertIn(status, (302, 303))
        self.assertIn('index.php', headers['Location'])
        status, _, _ = request('create.php?op=forgot')
        self.assertEqual(410, status)
        status, _, _ = request('runmodule.php?module[]=drinks&admin=true')
        self.assertEqual(400, status)
        status, _, _ = request('installer.php?stage=9')
        self.assertEqual(403, status)
