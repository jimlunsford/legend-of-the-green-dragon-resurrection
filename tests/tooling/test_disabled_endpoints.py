"""Exercise real HTTP entrypoints on loopback without bootstrapping a game."""
import http.client
import os
from pathlib import Path
import shutil
import socket
import subprocess
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
        cls.server = subprocess.Popen([php, '-n', '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT)],
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
        raise RuntimeError('Loopback PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)

    def test_disabled_features_reject_get_and_post_before_bootstrap(self):
        for path in ('rawsql.php', 'logdnet.php', 'images/logdnet.php', 'payment.php', 'source.php'):
            for method in ('GET', 'POST'):
                with self.subTest(path=path, method=method):
                    conn = http.client.HTTPConnection('127.0.0.1', self.port, timeout=3)
                    conn.request(method, '/' + path + '?op=register&admin=1', 'synthetic=fixture')
                    response = conn.getresponse()
                    self.assertEqual(response.status, 410)
                    self.assertEqual(response.getheader('Cache-Control'), 'no-store')
                    self.assertEqual(response.read(), b'This legacy feature is disabled in Resurrection.\n')
                    conn.close()

    def test_web_cron_rejected_on_repeated_requests(self):
        for method in ('GET', 'POST', 'GET'):
            conn = http.client.HTTPConnection('127.0.0.1', self.port, timeout=3)
            conn.request(method, '/cron.php')
            response = conn.getresponse()
            self.assertEqual(response.status, 404)
            self.assertEqual(response.read(), b'Not found.\n')
            conn.close()
