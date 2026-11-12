"""Tests and CI never send real mail (Phase 6 task 13a, S11).

Every `php -S` test server runs with sendmail_path pointing at a capture (tests/tooling/mail_capture.py). The guard
reads every Python file under tests/ and scripts/ and fails on a `php -S` launch that sets no sendmail_path and takes
no capture. loopback_server.start_php_server() refuses one at run time. The capture itself is tested with PHP's real
mail(): what PHP hands the program is what the test reads, and nothing leaves the machine.
"""
import ast
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(Path(__file__).resolve().parent))
import mail_capture  # noqa: E402
from loopback_server import start_php_server  # noqa: E402
from php_runtime import supported_php, version  # noqa: E402


def _launches(source):
    """Each call in `source` that starts the PHP built-in server (a literal '-S' among its list arguments), with whether
    it sets sendmail_path or takes a capture, in the call itself or in a function that checks for one."""
    tree = ast.parse(source)
    parents = {}
    for node in ast.walk(tree):
        for child in ast.iter_child_nodes(node):
            parents[child] = node
    found = []
    for node in ast.walk(tree):
        if not isinstance(node, ast.Call):
            continue
        lists = [argument for argument in node.args if isinstance(argument, (ast.List, ast.Tuple, ast.BinOp))]
        if not any(isinstance(item, ast.Constant) and item.value == '-S' for argument in lists for item in ast.walk(argument)):
            continue
        text = ast.get_source_segment(source, node) or ''
        covered = 'sendmail_path=' in text or 'mail_capture.' in text or '.args()' in text
        scope = parents.get(node)
        while scope is not None and not isinstance(scope, (ast.FunctionDef, ast.AsyncFunctionDef)):
            scope = parents.get(scope)
        if scope is not None and 'mail_capture.has_capture(' in (ast.get_source_segment(source, scope) or ''):
            covered = True
        found.append((node.lineno, covered))
    return found


class MailCaptureGuardTest(unittest.TestCase):
    def test_every_test_server_runs_with_a_mail_capture(self):
        uncovered, total = [], 0
        for path in sorted(list((ROOT / 'tests').rglob('*.py')) + list((ROOT / 'scripts').rglob('*.py'))):
            for line, covered in _launches(path.read_text(encoding='utf-8')):
                total += 1
                if not covered:
                    uncovered.append(f'{path.relative_to(ROOT)}:{line}')
        self.assertEqual([], uncovered, 'A php -S test server without a mail capture: add mail_capture.default_args() or a MailCapture.')
        # The scan finds the servers that exist (13 at Phase 6 task 13a); none found would mean it reads nothing.
        self.assertGreaterEqual(total, 13)

    def test_the_guard_finds_a_server_without_a_capture(self):
        bad = "import subprocess\nsubprocess.Popen(['php', '-d', 'display_errors=1', '-S', '127.0.0.1:8000', '-t', '.'])\n"
        good = "import subprocess\nsubprocess.Popen(['php', *mail_capture.default_args(), '-S', '127.0.0.1:8000', '-t', '.'])\n"
        self.assertEqual([(2, False)], _launches(bad))
        self.assertEqual([(2, True)], _launches(good))

    def test_start_php_server_refuses_a_server_without_a_capture(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(ValueError, 'mail capture'):
                start_php_server([supported_php()], directory, directory, None, Path(directory) / 'out.txt')
        self.assertTrue(mail_capture.has_capture(['php', *mail_capture.default_args()]))
        self.assertFalse(mail_capture.has_capture(['php', '-d', 'sendmail_path=']))

    def _mail(self, capture, *arguments):
        code = ("var_export(mail('player@example.org', 'Subject', \"Line one\\nhttps://game.example.org/account.php?op=open&t=x\","
                " \"From: game@example.org\")); echo \"\\n\";")
        return subprocess.run([supported_php(), '-n', *capture.args(), *arguments, '-r', code], capture_output=True, text=True, timeout=30)

    def test_the_capture_records_what_mail_hands_it_and_answers_as_told(self):
        accepting = mail_capture.MailCapture()
        refusing = mail_capture.MailCapture(exit_code=1)
        try:
            result = self._mail(accepting)
            self.assertEqual(('true\n', ''), (result.stdout, result.stderr))
            [message] = accepting.messages()
            self.assertEqual('player@example.org', message['To'])
            self.assertEqual('game@example.org', message['From'])
            self.assertIn('https://game.example.org/account.php?op=open&t=x', message.get_content())
            # A refusal: what both supported versions guarantee is that mail() returns false and the capture holds the
            # one message. PHP 8.5's mail() also warns "Sendmail exited with non-zero exit code 1"; PHP 8.4's warns
            # nothing (Phase 6 task 13a: Modern core 43294075991 failed here on 8.4). Each version's text is pinned, so
            # a change in either shows up.
            result = self._mail(refusing)
            self.assertTrue(result.stdout.endswith('false\n'), result.stdout)
            self.assertEqual(1, len(refusing.raw()))
            if version(supported_php()) >= (8, 5):
                self.assertIn('Sendmail exited with non-zero exit code 1', result.stdout)
            else:
                self.assertEqual(('false\n', ''), (result.stdout, result.stderr))
            accepting.clear()
            self.assertEqual([], accepting.raw())
        finally:
            accepting.cleanup()
            refusing.cleanup()


if __name__ == '__main__':
    unittest.main()
