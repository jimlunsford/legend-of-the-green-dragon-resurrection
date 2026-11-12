"""Mail capture for the test servers (Phase 6 task 13a, S11).

Tests and CI never send real mail. Every `php -S` test server runs with PHP's sendmail_path pointing at a capture
program instead of the host's mail program: it writes each message PHP's mail() hands it to a file and sends nothing.
A test that reads mail (the account mail tests) makes its own MailCapture and reads the message and its link from it;
every other server takes `default_args()`, a capture shared by the test process. test_mail_capture.py fails if any
test starts a `php -S` server without one of them, and loopback_server.start_php_server() refuses to start one.

The capture program's exit code is mail()'s answer: 0 is "accepted for delivery", anything else a refusal. The shared
capture refuses (exit 1), as a host without a mail program does, so the servers that existed before it see what they saw
before; it still records what it was handed.
"""
import atexit
import email
import email.policy
import json
from pathlib import Path
import sys
import tempfile

SETTING = 'sendmail_path='


class MailCapture:
    """A capture program and its file. exit_code is what the program answers PHP: 0 accepted, else refused."""

    def __init__(self, exit_code=0):
        self._directory = tempfile.TemporaryDirectory(prefix='resurrection-mail-capture-')
        directory = Path(self._directory.name)
        # A server may run as another user (test_operator_pages.py runs one as nobody): it must run the program and
        # append to the file. The directory is a throwaway temporary one.
        directory.chmod(0o1777)
        self.path = directory / 'capture.jsonl'
        self.program = directory / 'sendmail'
        self.program.write_text('#!' + sys.executable + ' -I\n' + (
            'import json, sys\n'
            'message = sys.stdin.buffer.read().decode("utf-8", "replace")\n'
            'with open(' + json.dumps(str(self.path)) + ', "a", encoding="utf-8") as sink:\n'
            '    sink.write(json.dumps({"argv": sys.argv[1:], "message": message}) + "\\n")\n'
            'sys.exit(' + str(int(exit_code)) + ')\n'))
        self.program.chmod(0o755)

    def args(self):
        """The php options that point mail() at this capture."""
        return ['-d', SETTING + str(self.program)]

    def raw(self):
        """Each captured message as PHP handed it to the program."""
        if not self.path.exists():
            return []
        return [json.loads(line)['message'] for line in self.path.read_text(encoding='utf-8').splitlines() if line.strip()]

    def messages(self):
        """Each captured message parsed: an email.message.EmailMessage."""
        return [email.message_from_string(text, policy=email.policy.default) for text in self.raw()]

    def clear(self):
        if self.path.exists():
            self.path.unlink()

    def cleanup(self):
        self._directory.cleanup()


_shared = None


def default_args():
    """The php options for a server that does not read mail: the process's shared, refusing capture."""
    global _shared
    if _shared is None:
        _shared = MailCapture(exit_code=1)
        atexit.register(_shared.cleanup)
    return _shared.args()


def has_capture(arguments):
    """Whether a php command line sets sendmail_path (to a capture: the tests set it to nothing else)."""
    arguments = [str(argument) for argument in arguments]
    return any(argument.startswith(SETTING) and len(argument) > len(SETTING) for argument in arguments)

