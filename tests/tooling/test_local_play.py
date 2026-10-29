"""The one-command local game (Phase 4 task 3) is safe by default, read from its files without running Docker or PHP.

`python3 scripts/local-play.py up` brings up the game from `local/compose.yaml`. These checks hold the setup to the
task's rules: the game is published only on 127.0.0.1, the database publishes no port and sits on an internal
network, the install goes only through `scripts/install.php` (and an existing game only through `scripts/upgrade.php`),
the generated credentials live in ignored files that are created owner-only, and the wipe removes the data volumes.
"""
import importlib.util
from pathlib import Path
import re
import stat
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
LOCAL = ROOT / 'local'
COMPOSE = LOCAL / 'compose.yaml'
SETUP = LOCAL / 'setup.sh'
WRAPPER = ROOT / 'scripts' / 'local-play.py'


def load_wrapper():
    spec = importlib.util.spec_from_file_location('local_play', WRAPPER)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def blocks(text, parent):
    """The keys directly under a top-level key of a block-style YAML file, each with its own lines.

    local/compose.yaml is written in plain block style (two-space indents, no flow mappings for services or ports),
    which test_compose_is_plain_block_style holds, so indentation alone finds each service's lines.
    """
    result = {}
    current = None
    inside = False
    for line in text.splitlines():
        if not line.strip() or line.lstrip().startswith('#'):
            continue
        if not line.startswith(' '):
            inside = line.rstrip() == parent + ':'
            current = None
            continue
        if not inside:
            continue
        match = re.fullmatch(r'  ([A-Za-z0-9_-]+):\s*', line)
        if match:
            current = match.group(1)
            result[current] = []
        elif current is not None:
            result[current].append(line)
    return result


def ports(lines):
    """The entries of a service's `ports:` list."""
    entries = []
    for index, line in enumerate(lines):
        if re.fullmatch(r'\s+ports:\s*', line):
            indent = len(line) - len(line.lstrip())
            for entry in lines[index + 1:]:
                if len(entry) - len(entry.lstrip()) <= indent:
                    break
                entries.append(entry.strip())
    return entries


def ignored(path):
    return subprocess.run(['git', '-C', str(ROOT), 'check-ignore', '-q', '--no-index', path]).returncode == 0


class LocalPlayTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.compose = COMPOSE.read_text()
        cls.services = blocks(cls.compose, 'services')
        cls.networks = blocks(cls.compose, 'networks')
        cls.setup = SETUP.read_text()
        cls.wrapper = WRAPPER.read_text()

    def test_compose_is_plain_block_style(self):
        # The checks below read the file by indentation; flow style would hide a port from them.
        self.assertEqual({'db', 'setup', 'app', 'cron'}, set(self.services))
        self.assertNotRegex(self.compose, r'(?m)^\s*(?:ports|expose|services|networks)\s*:\s*[\[{]')
        self.assertNotRegex(self.compose, r'(?m)^\s*-?\s*["\']?\d+(?::\d+)?["\']?\s*$', 'a bare port publishes on every address')

    def test_game_is_published_only_on_loopback(self):
        published = {name: ports(lines) for name, lines in self.services.items()}
        self.assertEqual(['- "127.0.0.1:${RESURRECTION_LOCAL_PORT:-8080}:8080"'], published['app'])
        for name, entries in published.items():
            for entry in entries:
                self.assertRegex(entry, r'^- "127\.0\.0\.1:', name)
        self.assertNotIn('network_mode', self.compose)
        self.assertNotRegex(self.compose, r'(?m)^\s*-\s*host\s*$')

    def test_database_publishes_no_port_and_is_internal(self):
        for name in ('db', 'setup', 'cron'):
            self.assertEqual([], ports(self.services[name]), name)
        self.assertNotRegex('\n'.join(self.services['db']), r'\b(?:ports|expose)\s*:')
        self.assertIn('    internal: true', self.networks['database'])
        for name in ('db', 'setup', 'cron'):
            networks = '\n'.join(self.services[name])
            self.assertRegex(networks, r'networks:\s*\n\s+- database\s*(?:\n|$)', name)
            self.assertNotIn('- web', networks, name)

    def test_install_goes_through_the_install_command(self):
        # The only install: scripts/install.php writing the config (0600) and activating the recommended modules.
        self.assertEqual(1, self.setup.count('php scripts/install.php'))
        self.assertRegex(self.setup, r'php scripts/install\.php --write-config "\$CONFIG"\n')
        self.assertNotIn('--modules=none', self.setup)
        self.assertNotIn('--allow-served-path', self.setup)
        # An existing game is never installed again: the config file decides, then the upgrade command reports.
        self.assertRegex(self.setup, r'if \[ ! -e "\$CONFIG" \]; then')
        self.assertIn('php scripts/upgrade.php --dry-run "$CONFIG"', self.setup)
        self.assertNotIn('--backup-confirmed', self.setup)
        # No second install path anywhere in the local setup.
        for path in [COMPOSE, SETUP, WRAPPER, *sorted(LOCAL.iterdir())]:
            text = path.read_text()
            for forbidden in ('installer.php', 'resurrection_fresh_install', 'CREATE TABLE', 'install_state'):
                self.assertNotIn(forbidden, text, f'{path.name}: {forbidden}')
        # setup.sh tells an up-to-date game apart by the upgrade command's own words.
        self.assertIn("'The database is current: '", (ROOT / 'lib' / 'upgrade.php').read_text())
        self.assertIn("The database is current: ", self.setup)

    def test_runtime_is_a_supported_pair_configured_as_ci(self):
        dockerfile = (LOCAL / 'Dockerfile').read_text()
        self.assertRegex(dockerfile, r'(?m)^FROM php:8\.4-cli$')
        self.assertIn('image: mariadb:11.4\n', self.compose)
        # CI's setup-php runs PHP with php.ini-production, and the image ships no php.ini: local play uses CI's settings.
        self.assertIn('cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"', dockerfile)
        # The served tree has no history, tests, local state or configuration of its own.
        ignore = (LOCAL / 'Dockerfile.dockerignore').read_text().split()
        for entry in ('.git', '.local-play', 'tests', 'vendor', 'dbconnect.php', '.env'):
            self.assertIn(entry, ignore)

    def test_upgrade_is_never_automatic(self):
        wrapper = load_wrapper()
        self.assertEqual(['php', 'scripts/upgrade.php', '--dry-run', '/config/dbconnect.php'], wrapper.upgrade_command(['--dry-run']))
        self.assertEqual(['php', 'scripts/upgrade.php', '--backup-confirmed', '/config/dbconnect.php'], wrapper.upgrade_command(['--backup-confirmed']))
        for arguments in ([], ['--backup-confirmed', '--dry-run'], ['--force'], ['/other/config.php']):
            with self.assertRaises(SystemExit, msg=arguments):
                wrapper.upgrade_command(arguments)
        # `up` runs the upgrade command only as a dry run (inside setup.sh); applying it is its own command.
        self.assertEqual(1, self.wrapper.count("'--backup-confirmed'"))

    def test_credentials_are_generated_owner_only_and_ignored(self):
        wrapper = load_wrapper()
        self.assertEqual(ROOT / '.local-play', wrapper.STATE)
        for name in ('database.env', 'admin.env', 'backups/resurrection-20261029-120000.sql'):
            self.assertTrue(ignored('.local-play/' + name), name)
        tracked = subprocess.run(['git', '-C', str(ROOT), 'ls-files', '.local-play'], capture_output=True, text=True, check=True).stdout
        self.assertEqual('', tracked)
        with tempfile.TemporaryDirectory() as temp:
            state = Path(temp) / '.local-play'
            self.assertTrue(wrapper.ensure_credentials(state))
            self.assertEqual(0o700, stat.S_IMODE(state.stat().st_mode))
            first = {}
            for name in ('database.env', 'admin.env'):
                path = state / name
                self.assertEqual(0o600, stat.S_IMODE(path.stat().st_mode), name)
                first[name] = path.read_text()
            database = wrapper.read_env(state / 'database.env')
            self.assertEqual({'MARIADB_DATABASE', 'MARIADB_USER', 'MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'}, set(database))
            self.assertNotEqual(database['MARIADB_PASSWORD'], database['MARIADB_ROOT_PASSWORD'])
            admin = wrapper.read_env(state / 'admin.env')
            self.assertEqual({'RESURRECTION_ADMIN_LOGIN', 'RESURRECTION_ADMIN_PASSWORD'}, set(admin))
            self.assertRegex(admin['RESURRECTION_ADMIN_LOGIN'], r'\A[A-Za-z][A-Za-z0-9]{2,24}\Z')
            self.assertTrue(12 <= len(admin['RESURRECTION_ADMIN_PASSWORD']) <= 72)
            for value in [*database.values(), *admin.values()]:
                # Plain letters and digits: nothing Compose or a shell would interpolate.
                self.assertRegex(value, r'\A[A-Za-z0-9_]+\Z')
            for secret in (database['MARIADB_PASSWORD'], database['MARIADB_ROOT_PASSWORD'], admin['RESURRECTION_ADMIN_PASSWORD']):
                self.assertGreaterEqual(len(secret), 20)
            # A second run keeps the game's credentials.
            self.assertFalse(wrapper.ensure_credentials(state))
            for name, text in first.items():
                self.assertEqual(text, (state / name).read_text(), name)
        # The database password is only ever handed to Compose as a file, never printed or put in an argument: the
        # wrapper names it only to generate it and, inside the database container, to give it to the backup's client.
        self.assertIn('MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump ', wrapper.DUMP)
        self.assertTrue(wrapper.RESTORE.startswith('export MYSQL_PWD="$MARIADB_PASSWORD"; '))
        self.assertNotIn('MARIADB_PASSWORD', self.wrapper.replace("'MARIADB_PASSWORD'", '', 1).replace('MYSQL_PWD="$MARIADB_PASSWORD"', ''))
        for command in (wrapper.DUMP, wrapper.RESTORE):
            self.assertNotRegex(command, r'\s-p', 'a password option would put it on a command line')
        self.assertNotIn('MARIADB_PASSWORD', self.setup.replace('DB_PASS="$MARIADB_PASSWORD"', '', 1).replace('unset MARIADB_PASSWORD', ''))
        self.assertIn('env_file:', self.compose)
        self.assertNotRegex(self.compose, r'(?m)^\s*(?:MARIADB|MYSQL)_[A-Z_]*PASSWORD\s*:')

    def test_wipe_removes_the_data_volumes(self):
        wrapper = load_wrapper()
        self.assertEqual(['down', '--volumes', '--remove-orphans'], wrapper.WIPE)
        self.assertEqual(['down', '--remove-orphans'], wrapper.STOP)
        self.assertRegex(self.compose, r'(?m)^  database:\s*$')
        self.assertIn('db:/var/lib/mysql', self.compose)

    def test_labelled_local_use_only(self):
        for text in (self.compose, self.wrapper):
            self.assertIn('LOCAL USE ONLY', text)
            self.assertIn('not for hosting', text)


if __name__ == '__main__':
    unittest.main()
