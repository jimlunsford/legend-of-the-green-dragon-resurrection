"""Exercise real HTTP entrypoints on loopback without bootstrapping a game."""
import http.client
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]

class DisabledEndpointTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        php = os.environ.get('PHP_BINARY') or shutil.which('php')
        if not php:
            raise unittest.SkipTest('PHP required; mandatory in Modern core CI')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        # PHP's built-in server answers a missing top-level script with the root index.php. This router makes it
        # behave like an ordinary web server instead: an existing file is served (and run) as usual, anything else is
        # a 404 from the router itself.
        cls.router = tempfile.NamedTemporaryFile(mode='w', suffix='.php')
        cls.router.write("<?php $file = realpath($_SERVER['DOCUMENT_ROOT'] . rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));\n"
                         "if ($file !== false && is_file($file)) return false;\n"
                         "http_response_code(404); echo \"No such file.\\n\";\n")
        cls.router.flush()
        cls.server = subprocess.Popen([php, '-n', '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT), cls.router.name],
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.1):
                    return
            except OSError:
                if cls.server.poll() is not None:
                    raise RuntimeError('Loopback PHP test server exited')
                time.sleep(.05)
        cls.server.terminate()
        cls.router.close()
        raise RuntimeError('Loopback PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.router.close()

    def test_removed_disabled_features_are_not_found(self):
        # 1.1.2's raw SQL console, LoGDnet, PayPal intake, source viewer and payment log. Their 410 stubs (and paylog.php)
        # were removed (UNNECESSARY-FILES-20261024.md, "License and dead weight"): no file answers, so a request is a
        # plain 404 that never reaches the application. The features stay disabled and stay at historical-source-1.1.2.
        for path in ('rawsql.php', 'logdnet.php', 'images/logdnet.php', 'payment.php', 'source.php', 'paylog.php'):
            self.assertFalse((ROOT / path).exists(), path)
            for method in ('GET', 'POST'):
                with self.subTest(path=path, method=method):
                    conn = http.client.HTTPConnection('127.0.0.1', self.port, timeout=3)
                    conn.request(method, '/' + path + '?op=register&admin=1', 'synthetic=fixture')
                    response = conn.getresponse()
                    body = response.read()
                    self.assertEqual(response.status, 404)
                    self.assertEqual(body, b'No such file.\n')
                    self.assertIsNone(response.getheader('Set-Cookie'))
                    conn.close()

    def test_no_shipped_file_links_a_removed_file(self):
        # The removed endpoints and 1.1.2's PayPal buttons: no page, library, module or skin links them (a quoted name,
        # path or attribute value); comments that tell their history may still name them.
        removed = re.compile(r'''(?<=["'/=])(?:rawsql|logdnet|payment|source|paylog)\.php\b|(?<=["'/=])paypal[123]\.gif\b''', re.I)
        shipped = [p for p in ROOT.rglob('*') if p.is_file() and p.suffix.lower() in {'.php', '.htm', '.html', '.css', '.js'}
                   and not set(p.relative_to(ROOT).parts[:1]) & {'tests', 'lab', 'docs', 'vendor', 'scripts', '.git'}]
        self.assertGreater(len(shipped), 300)
        found = [f'{p.relative_to(ROOT)}: {m.group(0)}' for p in shipped for m in removed.finditer(p.read_text(errors='replace'))]
        self.assertEqual([], found)

    def test_web_cron_rejected_on_repeated_requests(self):
        for method in ('GET', 'POST', 'GET'):
            conn = http.client.HTTPConnection('127.0.0.1', self.port, timeout=3)
            conn.request(method, '/cron.php')
            response = conn.getresponse()
            self.assertEqual(response.status, 404)
            self.assertEqual(response.read(), b'Not found.\n')
            conn.close()
