"""The pages common.php shows before a game can run: "not yet installed" and "Database Connection Error".

Each must render its text and the normal footer, with the copyright line and the license. Until 2026-10-24 both
stopped with a runtime error first: the footer's copyright values were set after them (UNNECESSARY-FILES-20261024.md,
"Installer pages"). No database is needed: the server runs on a copy of the tree made of symlinks, without
dbconnect.php, or with one that points at a closed port.
"""
import html
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import tempfile
import time
import unittest
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_runtime import supported_php  # noqa: E402

COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team'
LICENSE = "This work is licensed under a <a rel='license' href='http://creativecommons.org/licenses/by-nc-sa/2.0/' target='_blank'>Creative Commons License</a>."
ERROR = re.compile(r'(?i)fatal error|runtime error|warning:|notice:|deprecated:|uncaught')


def text(body):
    body = re.sub(r'(?s)<!--.*?-->|<script.*?</script>', '', body)
    return html.unescape(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', body)))


class OperatorPageTests(unittest.TestCase):
    def serve(self, config=None):
        """GET home.php from a fresh server on a symlinked copy of the tree; config is the dbconnect.php to use."""
        # The pages run the application's PHP: a supported PHP, or a skip (a failure in Modern core).
        php = supported_php()
        with tempfile.TemporaryDirectory(prefix='resurrection-operator-') as tree:
            for entry in ROOT.iterdir():
                if entry.name not in ('dbconnect.php', '.git'):
                    os.symlink(entry, Path(tree) / entry.name)
            if config is not None:
                (Path(tree) / 'dbconnect.php').write_text(config)
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            sessions = Path(tree) / '.sessions'
            sessions.mkdir()
            server = subprocess.Popen([php, '-d', 'display_errors=1', '-d', 'session.save_path=' + str(sessions),
                                       '-S', f'127.0.0.1:{port}', '-t', tree], cwd=tree,
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                for _ in range(100):
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=.1):
                            break
                    except OSError:
                        self.assertIsNone(server.poll(), 'Loopback PHP test server exited')
                        time.sleep(.05)
                try:
                    response = urllib.request.urlopen(f'http://127.0.0.1:{port}/home.php', timeout=20)
                except urllib.error.HTTPError as error:
                    response = error
                return response.status, response.read().decode('utf-8', errors='replace')
            finally:
                server.terminate()
                server.wait(timeout=5)

    def assertRendered(self, status, body, title):
        self.assertEqual(200, status, body[:2000])
        self.assertNotRegex(body, ERROR, body[:2000])
        self.assertIn('<title>' + title, body)
        # The normal footer: the license and the copyright line, each once.
        self.assertEqual(1, body.count(COPYRIGHT), body[-3000:])
        self.assertEqual(1, body.count(LICENSE), body[-3000:])
        self.assertIn('Version: 1.1.2 Dragonprime Edition', body)

    def test_not_installed_page_shows_the_cli_instruction_and_footer(self):
        status, body = self.serve()
        self.assertRendered(status, body, 'The game has not yet been installed')
        shown = text(body)
        self.assertIn('Welcome to Legend of the Green Dragon , a game by Eric Stevens & JT Traub.', shown)
        self.assertIn("The game must be installed from the server's command line before it can be played: the operator "
                      'runs php scripts/install.php , as docs/MODERN-INSTALLATION.md describes. There is no web installer.', shown)
        self.assertIn('restore that file from your backup', shown)
        self.assertNotIn('installer.php', body)

    def test_database_connection_error_page_shows_its_text_and_footer(self):
        config = ("<?php $DB_HOST='127.0.0.1:1'; $DB_USER='nobody'; $DB_PASS='closed-port-only'; $DB_NAME='none';"
                  " $DB_PREFIX=''; $DB_USEDATACACHE=0; $DB_DATACACHEPATH='';\n")
        status, body = self.serve(config)
        self.assertRendered(status, body, 'Database Connection Error')
        self.assertIn('Unable to connect to the database server.', text(body))
        self.assertNotIn('closed-port-only', body)

    def test_footer_values_come_before_the_first_page(self):
        # Static guard for the order: the values the footer reads are set before common.php's first page_header(), and
        # the 1.1.2 tamper check that recomputes $x and $lc for every other page still runs once, before
        # prepare_template(). The copyright and license text themselves are covered by test_license_text.py.
        source = (ROOT / 'common.php').read_text()
        first_page = re.search(r'^\s+page_header\(', source, re.M).start()
        for value in ['$cp = $copyright;', '$l = $license;', '$y2 = ', '$z2 = ', '$x = 0;', '$lc = $l;']:
            with self.subTest(value=value):
                self.assertLess(source.index(value), first_page)
        check = source.index("if ($session['user']['superuser']==0){")
        self.assertEqual(1, source.count("if ($session['user']['superuser']==0){"))
        self.assertLess(source.index('Upgrade Needed'), check)
        self.assertLess(check, source.index('\nprepare_template();'))
        self.assertEqual(1, source.count('$y2 = '))


if __name__ == '__main__':
    unittest.main()
