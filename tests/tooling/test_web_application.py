"""Real loopback application requests against the preceding clean CI install."""
import base64
import html
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

    def test_cli_maintenance_is_once_per_game_day(self):
        first = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, first.returncode, first.stderr)
        self.assertEqual('complete', json.loads(first.stdout)['maintenance'])
        second = subprocess.run([shutil.which('php'), 'cron.php'], cwd=ROOT, capture_output=True, text=True, timeout=60)
        self.assertEqual(0, second.returncode, second.stderr)
        self.assertEqual('already-complete', json.loads(second.stdout)['maintenance'])

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
