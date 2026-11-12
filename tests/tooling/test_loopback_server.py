"""The loopback PHP server helper (tests/tooling/loopback_server.py; Phase 6 task 5b fix): a server that exits, never
answers, or loses its port to another program fails at once with a clear message, never a later "connection refused"
in the test that uses it; a server that starts answers on a port the OS chose."""
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import unittest
import urllib.request

sys.path.insert(0, str(Path(__file__).resolve().parent))
from loopback_server import ServerStartError, start_php_server, wait_until_ready  # noqa: E402
from php_runtime import supported_php  # noqa: E402
import mail_capture  # noqa: E402


class LoopbackServerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.php = supported_php()

    def setUp(self):
        self.work = tempfile.TemporaryDirectory(prefix='resurrection-loopback-')
        self.path = Path(self.work.name)
        self.output = self.path / 'server.out'

    def tearDown(self):
        self.work.cleanup()

    def test_a_server_that_starts_answers_on_a_port_the_os_chose(self):
        (self.path / 'index.php').write_text('<?php echo "answered";')
        server, port = start_php_server([self.php, *mail_capture.default_args()], self.path, self.path, None, self.output)
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/index.php', timeout=10) as response:
                self.assertEqual(b'answered', response.read())
        finally:
            server.terminate()
            server.wait(timeout=10)

    def test_a_server_that_exits_fails_with_its_output(self):
        # A document root that does not exist: PHP exits at once.
        with self.assertRaises(ServerStartError) as caught:
            start_php_server([self.php, *mail_capture.default_args()], self.path / 'missing', self.path, None, self.output)
        message = str(caught.exception)
        self.assertRegex(message, r'^Loopback PHP test server on 127\.0\.0\.1:\d+ exited with code \d+ before it answered\.')
        self.assertIn('Server output:', message)
        self.assertIn('missing', message)

    def test_another_program_on_the_port_is_not_taken_for_the_server(self):
        # The CI failure's shape: the port is not free. A program listening there answers a bare connection, but PHP
        # exits and its "started" line never comes, so the wait fails instead of handing the test the wrong server.
        with socket.socket() as holder:
            holder.bind(('127.0.0.1', 0))
            holder.listen()
            port = holder.getsockname()[1]
            with self.output.open('w') as sink:
                server = subprocess.Popen([self.php, *mail_capture.default_args(), '-S', f'127.0.0.1:{port}', '-t', str(self.path)], cwd=self.path,
                                          stdout=sink, stderr=subprocess.STDOUT)
            try:
                with self.assertRaises(ServerStartError) as caught:
                    wait_until_ready(server, port, output=self.output, started=f'(http://127.0.0.1:{port}) started')
            finally:
                server.kill()
                server.wait(timeout=10)
        self.assertIn('exited with code', str(caught.exception))
        self.assertIn('Address already in use', str(caught.exception))

    def test_a_server_that_never_answers_fails_after_the_time_allowed(self):
        port = socket.socket()
        port.bind(('127.0.0.1', 0))
        number = port.getsockname()[1]
        server = subprocess.Popen([self.php, '-r', 'sleep(30);'])
        try:
            with self.assertRaises(ServerStartError) as caught:
                wait_until_ready(server, number, 'Silent server', timeout=.5)
        finally:
            port.close()
            server.kill()
            server.wait(timeout=10)
        self.assertEqual(f'Silent server on 127.0.0.1:{number} did not answer within 0.5 s.', str(caught.exception))


if __name__ == '__main__':
    unittest.main()
