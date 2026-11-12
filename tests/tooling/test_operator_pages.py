"""The pages common.php shows before a game can run: "not yet installed" and "Database Connection Error".

Each must render its text and the normal footer, with the copyright line and the license. Until 2026-10-24 both
stopped with a runtime error first: the footer's copyright values were set after them (UNNECESSARY-FILES-20261024.md,
"Installer pages"). No database is needed: the server runs on a copy of the tree made of symlinks, without
dbconnect.php, or with one that points at a closed port.
"""
from contextlib import contextmanager
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
from loopback_server import wait_until_ready  # noqa: E402
import mail_capture  # noqa: E402

COPYRIGHT = 'Game Design and Code: Copyright &copy; 2002-2005, Eric Stevens & JT Traub, &copy; 2006-2007, Dragonprime Development Team'
LICENSE = "This work is licensed under a <a rel='license' href='http://creativecommons.org/licenses/by-nc-sa/2.0/' target='_blank'>Creative Commons License</a>."
NOBODY = 65534
ATTRIBUTION = ('A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by '
               '<a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a>')
CSP = ("default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; object-src 'none'; "
       "base-uri 'self'; form-action 'self'; frame-ancestors 'self'")
ERROR = re.compile(r'(?i)fatal error|runtime error|warning:|notice:|deprecated:|uncaught')
# The footer shows Resurrection's version and credits the base (src/Version.php; 1.1.2 showed "Version: 1.1.2
# Dragonprime Edition").
VERSION = re.search(r"const string CURRENT = '([^']+)';", (ROOT / 'src' / 'Version.php').read_text()).group(1)


def text(body):
    body = re.sub(r'(?s)<!--.*?-->|<script.*?</script>', '', body)
    return html.unescape(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', body)))


class OperatorPageTests(unittest.TestCase):
    @contextmanager
    def tree(self, config=None, env=None, files=None, replace=None):
        """A fresh server on a symlinked copy of the tree; config is the dbconnect.php to use, env is added to the
        server's environment, files ({name: text}) are written into the copy, replace ({name: directory}) swaps an entry
        for another directory. Yields (port, tree, error_log). Run as root (a local run), the server runs as nobody, so
        a file's permissions mean what they mean on a host (Phase 6 task 3)."""
        # The pages run the application's PHP: a supported PHP, or a skip (a failure in Modern core).
        php = supported_php()
        with tempfile.TemporaryDirectory(prefix='resurrection-operator-') as tree:
            skip = {'dbconnect.php', '.git', *(files or {}), *(replace or {})}
            for entry in ROOT.iterdir():
                if entry.name not in skip:
                    os.symlink(entry, Path(tree) / entry.name)
            for name, target in (replace or {}).items():
                os.symlink(target, Path(tree) / name)
            if config is not None:
                (Path(tree) / 'dbconnect.php').write_text(config)
            for name, text in (files or {}).items():
                (Path(tree) / name).write_text(text)
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            sessions = Path(tree) / '.sessions'
            sessions.mkdir()
            error_log = Path(tree) / '.php-errors.log'
            error_log.touch()
            demote = None
            if os.geteuid() == 0:
                os.chmod(tree, 0o755)
                os.chmod(sessions, 0o777)
                os.chmod(error_log, 0o666)
                for name in ['dbconnect.php', *(files or {})]:
                    if (Path(tree) / name).exists():
                        os.chown(Path(tree) / name, NOBODY, NOBODY)
                demote = lambda: (os.setgid(NOBODY), os.setuid(NOBODY))
            environment = {k: v for k, v in os.environ.items() if k != 'LOGD_DBCONNECT'}
            server = subprocess.Popen([php, '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'error_log=' + str(error_log),
                                       '-d', 'session.save_path=' + str(sessions), *mail_capture.default_args(), '-S', f'127.0.0.1:{port}', '-t', tree], cwd=tree,
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=dict(environment, **(env or {})),
                                      preexec_fn=demote)
            try:
                wait_until_ready(server, port)
                yield port, Path(tree), error_log
            finally:
                server.terminate()
                server.wait(timeout=5)

    @staticmethod
    def get(port, path='home.php'):
        """One GET, redirects not followed: (status, headers, body as bytes)."""
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args): return None
        try:
            response = urllib.request.build_opener(NoRedirect).open(f'http://127.0.0.1:{port}/{path}', timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.headers, response.read()

    def serve(self, config=None, **options):
        """GET home.php from a fresh server on a symlinked copy of the tree; config is the dbconnect.php to use."""
        with self.tree(config, **options) as (port, _, _):
            status, _, body = self.get(port)
            return status, body.decode('utf-8', errors='replace')

    def assertRendered(self, status, body, title):
        self.assertEqual(200, status, body[:2000])
        self.assertNotRegex(body, ERROR, body[:2000])
        self.assertIn('<title>' + title, body)
        # The normal footer: the license and the copyright line, each once.
        self.assertEqual(1, body.count(COPYRIGHT), body[-3000:])
        self.assertEqual(1, body.count(LICENSE), body[-3000:])
        self.assertIn(f'Version: Resurrection {VERSION}, based on LoGD 1.1.2 Dragonprime Edition', body)
        # No phone tab bar on an operator page (Phase 5 task 8): the page is not part of the game.
        self.assertNotIn('rz-tabbar', body)
        self.assertNotIn('assets/ui/tabs.js', body)

    def test_not_installed_page_shows_the_cli_instruction_and_footer(self):
        status, body = self.serve()
        self.assertRendered(status, body, 'The game has not yet been installed')
        shown = text(body)
        self.assertIn('Welcome to Legend of the Green Dragon , a game by Eric Stevens & JT Traub.', shown)
        # Phase 6 task 5b: the page names both paths, the command and setup.php.
        self.assertIn("The game must be installed before it can be played: the operator runs php scripts/install.php on the "
                      "server's command line, or, on a host without a shell, opens setup.php in the browser and follows its "
                      'steps, as docs/MODERN-INSTALLATION.md describes.', shown)
        self.assertNotIn('There is no web installer.', shown)
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


    # Phase 6 task 3 (S4): a configuration file that is there but cannot be used gets an operator page with status 503,
    # not a blank 500: what failed, which file, what to check, and the full footer credits; never the file's contents
    # or a credential. The reason goes to the PHP error log once.
    SECRET = 'Synthetic-config-secret-must-never-show'

    def assertConfigPage(self, status, headers, body, error_log, reason, path):
        text_body = body.decode('utf-8', errors='replace')
        self.assertEqual(503, status, text_body[:2000])
        self.assertNotRegex(text_body, ERROR, text_body[:2000])
        self.assertIn('<title>The game cannot read its configuration', text_body)
        shown = text(text_body)
        self.assertIn('cannot use its database configuration file', shown)
        self.assertIn('What failed: ' + reason, shown)
        self.assertIn('Which file: ' + path, shown)
        for check in ['The owner:', 'The permissions:', 'The directories:', 'The file itself:', 'LOGD_DBCONNECT:']:
            self.assertIn(check, shown)
        # The full footer: the 1.1.2 credit line, the license block and the Resurrection attribution line, once each.
        self.assertEqual(1, text_body.count(COPYRIGHT), text_body[-3000:])
        self.assertEqual(1, text_body.count(LICENSE), text_body[-3000:])
        self.assertEqual(1, text_body.count(ATTRIBUTION), text_body[-3000:])
        for secret in [self.SECRET, 'closed-port-only', '$DB_PASS', 'DB_PASS']:
            self.assertNotIn(secret, text_body)
        self.assertEqual('300', headers['Retry-After'])
        self.assertEqual(CSP, headers['Content-Security-Policy'])
        logged = [line for line in error_log.read_text().splitlines() if line.strip()]
        self.assertEqual(1, len(logged), logged)
        self.assertIn('the database configuration file cannot be used', logged[0])
        self.assertNotIn(self.SECRET, logged[0])

    def config_text(self):
        return ("<?php $DB_HOST='127.0.0.1:1'; $DB_USER='nobody'; $DB_PASS='" + self.SECRET + "'; $DB_NAME='none';"
                " $DB_PREFIX=''; $DB_USEDATACACHE=0; $DB_DATACACHEPATH='';\n")

    def test_an_unreadable_config_file_gets_the_operator_page(self):
        with self.tree(self.config_text()) as (port, tree, error_log):
            (tree / 'dbconnect.php').chmod(0)
            status, headers, body = self.get(port)
            self.assertConfigPage(status, headers, body, error_log, 'The file exists, but PHP cannot read it.', 'dbconnect.php')

    def test_a_damaged_config_file_gets_the_operator_page(self):
        # A parse error whose message would quote the file, and a file that throws: neither message is shown or logged.
        broken = {'parse': "<?php $DB_PASS = '" + self.SECRET + "' '" + self.SECRET + "';\n",
                  'throws': "<?php $DB_PASS='" + self.SECRET + "'; echo 'printed " + self.SECRET + "'; throw new RuntimeException('" + self.SECRET + "');\n",
                  'empty': '', 'incomplete': "<?php $DB_HOST='127.0.0.1:1';\n"}
        for name, config in broken.items():
            with self.subTest(name), self.tree(config) as (port, _, error_log):
                status, headers, body = self.get(port)
                self.assertConfigPage(status, headers, body, error_log, 'The file could not be loaded: PHP stopped with an error inside it.', 'dbconnect.php')

    def test_logd_dbconnect_uses_the_same_page(self):
        # Phase 6 task 3 (S12 with S4): through LOGD_DBCONNECT, an unreadable file, a damaged one and a relative path;
        # a missing file is the not-installed page, as for a missing dbconnect.php.
        with tempfile.TemporaryDirectory(prefix='resurrection-private-') as private:
            os.chmod(private, 0o755)
            unreadable = Path(private) / 'unreadable.php'
            unreadable.write_text(self.config_text())
            unreadable.chmod(0)
            damaged = Path(private) / 'damaged.php'
            damaged.write_text("<?php $DB_PASS = '" + self.SECRET + "' x;\n")
            damaged.chmod(0o644)
            cases = [(str(unreadable), 'The file exists, but PHP cannot read it.'),
                     (str(damaged), 'The file could not be loaded: PHP stopped with an error inside it.'),
                     ('relative/game.php', 'LOGD_DBCONNECT is set, but it is not an absolute path.'),
                     ('dbconnect.php', 'LOGD_DBCONNECT is set, but it is not an absolute path.')]
            for path, reason in cases:
                # A readable dbconnect.php in the docroot is ignored while LOGD_DBCONNECT is set.
                with self.subTest(path), self.tree(self.config_text(), env={'LOGD_DBCONNECT': path}) as (port, _, error_log):
                    status, headers, body = self.get(port)
                    self.assertConfigPage(status, headers, body, error_log, reason, path)
            with self.tree(env={'LOGD_DBCONNECT': str(Path(private) / 'missing.php')}) as (port, _, _):
                status, headers, body = self.get(port)
                self.assertRendered(status, body.decode(), 'The game has not yet been installed')
            # A file under a directory PHP cannot enter, directly or further down, is unreadable, not missing.
            closed = Path(private) / 'closed'
            (closed / 'inner').mkdir(parents=True)
            for path in [closed / 'game.php', closed / 'inner' / 'game.php']:
                path.write_text(self.config_text())
            closed.chmod(0)
            try:
                for path in [closed / 'game.php', closed / 'inner' / 'game.php']:
                    with self.subTest(str(path)), self.tree(env={'LOGD_DBCONNECT': str(path)}) as (port, _, error_log):
                        status, headers, body = self.get(port)
                        self.assertConfigPage(status, headers, body, error_log, 'The file exists, but PHP cannot read it.', str(path))
            finally:
                closed.chmod(0o755)

    def test_operator_frame_has_no_game_panels_and_keeps_the_credits(self):
        # Phase 6 task 5a: the not-installed page and the S4 configuration page are drawn in the operator frame. No MoTD,
        # Petition for Help, mail or Online Characters (none of them can work before the game can read its database);
        # the 1.1.2 credit line, the license block and the Resurrection attribution line once each, in the template that
        # prepare_template() checked. The pages name the command path only.
        with self.tree(self.config_text()) as (port, tree, _):
            (tree / 'dbconnect.php').chmod(0)
            pages = {'S4 configuration page': self.get(port)}
        with self.tree() as (port, _, _):
            pages['not installed'] = self.get(port)
        for label, (status, _, raw) in pages.items():
            with self.subTest(label):
                body = raw.decode('utf-8', errors='replace')
                self.assertIn(status, (200, 503), body[:2000])
                for panel in ['motd.php', 'petition.php', 'mail.php', 'Online Characters', 'rz-petitioncount', 'rz-control']:
                    self.assertNotIn(panel, body, panel)
                for credit in [COPYRIGHT, LICENSE, ATTRIBUTION]:
                    self.assertEqual(1, body.count(credit), body[-3000:])
                self.assertNotIn('rz-safe-frame', body)
                self.assertIn('scripts/install.php' if label == 'not installed' else 'LOGD_DBCONNECT', text(body))
                # Phase 6 task 5b: the not-installed page names setup.php beside the command; the others do not.
                if label == 'not installed':
                    self.assertIn('setup.php', text(body))
                else:
                    self.assertNotIn('setup.php', body)
                self.assertNotIn('webcron', body)

    def test_operator_pages_send_the_security_headers(self):
        # Phase 6 task 3 (S1): the pages that run before the game's settings can be read (not installed, the database
        # error, the safe frame) and the installer's 403 carry all four headers, the policy enforced.
        expected = {'X-Frame-Options': 'SAMEORIGIN', 'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'same-origin',
                    'Content-Security-Policy': CSP}
        closed = ("<?php $DB_HOST='127.0.0.1:1'; $DB_USER='nobody'; $DB_PASS='closed-port-only'; $DB_NAME='none';"
                  " $DB_PREFIX=''; $DB_USEDATACACHE=0; $DB_DATACACHEPATH='';\n")
        with tempfile.TemporaryDirectory(prefix='resurrection-templates-') as templates:
            # A templates/ with no template: every page is drawn in the safe frame (src/Ui/SafeFrame.php).
            for label, options, path, status, needle in [
                    ('not installed', {}, 'home.php', 200, 'The game has not yet been installed'),
                    ('database error', {'config': closed}, 'home.php', 200, 'Database Connection Error'),
                    ('installer', {}, 'installer.php', 403, 'Web installation is unavailable'),
                    ('safe frame', {'replace': {'templates': templates}}, 'home.php', 200, 'rz-safe-frame')]:
                with self.subTest(label), self.tree(**options) as (port, _, _):
                    code, headers, body = self.get(port, path)
                    self.assertEqual(status, code, body[:1500])
                    self.assertIn(needle, body.decode())
                    if label == 'installer':
                        # Phase 6 task 11: the 403 names the two real ways to install, and no migration (none exists;
                        # installs are fresh only).
                        self.assertIn('php scripts/install.php', body.decode())
                        self.assertIn('setup.php', body.decode().replace('installer.php', ''))
                        self.assertNotIn('migration', body.decode().lower())
                    for name, value in expected.items():
                        self.assertEqual([value], headers.get_all(name), f'{label}: {name}')
                    self.assertIsNone(headers['Content-Security-Policy-Report-Only'])
                    if status == 200:
                        page = body.decode()
                        self.assertIn('<script src="assets/ui/behaviors.js" defer></script>', page)
                        self.assertIsNone(re.search(r'<script(?![^>]*\bsrc=)|<style|\sstyle=|\son[a-z]+=', page), label)

    def test_internal_files_answer_a_direct_request_with_an_empty_404(self):
        # Phase 6 task 3 (S2): every file under lib/, modules/ and src/ (and common.php and battle.php) checks
        # RESURRECTION_ENTRY first; requested directly it answers 404 with an empty body, before any bootstrap, database,
        # session or log line. Every entry point still runs (here, without a configuration, each shows the
        # not-installed page or answers as it should).
        listed = subprocess.run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard', '--', '*.php'],
                                cwd=ROOT, capture_output=True, check=True).stdout.decode().split('\0')
        internal = sorted(f for f in listed if f and (f.split('/')[0] in ('lib', 'modules', 'src') or f in ('common.php', 'battle.php')))
        entries = sorted(f for f in listed if f and '/' not in f and f not in ('common.php', 'battle.php', 'dbconnect.php'))
        self.assertGreater(len(internal), 270)
        # Phase 6 task 5a: webcron.php is the 61st; without a configuration it answers its empty 404. Phase 6 task 5b:
        # setup.php is the 62nd; without a configuration it answers with its install checks. Phase 6 task 13a: account.php
        # is the 63rd; without a configuration it shows the not-installed page.
        self.assertEqual(63, len(entries))
        with self.tree() as (port, tree, error_log):
            wrong = []
            for path in internal:
                status, headers, body = self.get(port, path)
                if status != 404 or body != b'' or headers['Set-Cookie']:
                    wrong.append(f'{path}: {status} {len(body)} bytes')
            self.assertEqual([], wrong)
            self.assertEqual('', error_log.read_text())
            self.assertEqual([], [p.name for p in (tree / '.sessions').iterdir()])
            answers = {'installer.php': 403, 'index.php': 302, 'cron.php': 404, 'runmodule.php': 400, 'webcron.php': 404}
            wrong = []
            for path in entries:
                status, _, body = self.get(port, path)
                page = body.decode('utf-8', errors='replace')
                expected = answers.get(path, 200)
                shows = 'Install the game' if path == 'setup.php' else 'The game has not yet been installed'
                if status != expected or ERROR.search(page) or (expected == 200 and shows not in page):
                    wrong.append(f'{path}: {status} {page[:200]!r}')
            self.assertEqual([], wrong)


if __name__ == '__main__':
    unittest.main()
