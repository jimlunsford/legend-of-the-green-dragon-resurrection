"""The release package (Phase 6 task 7, docs/PHASE6-TASK7-RC-MATERIALS-20261107.md): scripts/build-release-package.py.

The zip a host uploads holds exactly the host guide's "Upload these" list, as tests/tooling/test_host_guide.py reads it
(upload_files()), so the guide, its test and the package cannot drift apart; nothing on the guide's "Do not upload" list
or that the game makes is in it; the license and credit files are byte for byte the tree's; and the same tree gives the
same bytes. Plain Python and Git, no PHP or database: it runs in every shard and in Baseline integrity.
"""
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

import test_host_guide

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'build-release-package.py'
CREDITS = ['LICENSE.txt', 'README.txt', 'NOTICE', 'assets/LICENSE']


def load():
    spec = importlib.util.spec_from_file_location('build_release_package', SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ReleasePackageTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.package = load()
        cls.scratch = tempfile.TemporaryDirectory(prefix='resurrection-package-test-')
        cls.first = cls.package.build(Path(cls.scratch.name) / 'one')
        cls.second = cls.package.build(Path(cls.scratch.name) / 'two')

    @classmethod
    def tearDownClass(cls):
        cls.scratch.cleanup()

    def names(self):
        with zipfile.ZipFile(self.first['zip']) as archive:
            return archive.namelist()

    def test_the_package_holds_exactly_the_upload_list(self):
        expected = test_host_guide.upload_files()
        self.assertEqual(expected, self.names())
        self.assertEqual(len(expected), self.first['files'])
        # The list's own entries are all there: the two shell commands, the pages, the folders and the credits.
        for name in ['scripts/install.php', 'scripts/upgrade.php', 'index.php', 'setup.php', 'common.php', 'lib/output.php',
                     'src/Version.php', 'templates/resurrection.htm', 'favicon.ico', *CREDITS]:
            self.assertIn(name, self.names())

    def test_nothing_denied_or_generated_is_inside(self):
        text = test_host_guide.guide()
        denied = test_host_guide.entries(text, '### Do not upload') + test_host_guide.GENERATED + test_host_guide.SERVER_MADE
        # A pattern on the "Do not upload" list (`*.md`) names the top folder's files, as on the upload list; the notes in
        # assets/ and upgrades/ travel with their folders (the game needs upgrades/ to exist).
        def named(item, name):
            if '*' in item:
                return '/' not in name and test_host_guide.fnmatch.fnmatchcase(name, item)
            return test_host_guide.matches(item, name)
        inside = [name for name in self.names() for item in denied if named(item, name)]
        self.assertEqual([], inside)
        for name in self.names():
            self.assertFalse(name.startswith(('/', '../')) or '/../' in name or name.startswith('.'), name)
            self.assertNotIn(name.split('/')[0], ['tests', 'docs', 'vendor', 'lab', 'local', '.git', '.github'])
        # The upgrade step folder is there even with no step in it: StepCatalog::ids() refuses a missing one.
        self.assertIn('upgrades/README.md', self.names())

    def test_license_and_credit_files_are_byte_identical_to_the_tree(self):
        with zipfile.ZipFile(self.first['zip']) as archive:
            for name in CREDITS:
                self.assertEqual((ROOT / name).read_bytes(), archive.read(name), name)
            for name in archive.namelist():
                self.assertEqual((ROOT / name).read_bytes(), archive.read(name), name)

    def test_two_builds_give_the_same_bytes(self):
        one, two = Path(self.first['zip']).read_bytes(), Path(self.second['zip']).read_bytes()
        self.assertEqual(hashlib.sha256(one).hexdigest(), hashlib.sha256(two).hexdigest())
        self.assertEqual(one, two)
        self.assertEqual(self.first['sha256'], hashlib.sha256(one).hexdigest())
        name = Path(self.first['zip']).name
        self.assertEqual(f"{self.first['sha256']}  {name}\n", (Path(self.first['zip']).parent / (name + '.sha256')).read_text())
        with zipfile.ZipFile(self.first['zip']) as archive:
            for info in archive.infolist():
                self.assertEqual((1980, 1, 1, 0, 0, 0), info.date_time, info.filename)
                self.assertEqual(zipfile.ZIP_STORED, info.compress_type, info.filename)
                self.assertEqual(0o100644, info.external_attr >> 16, info.filename)
            self.assertEqual(b'', archive.comment)
            self.assertEqual(sorted(archive.namelist()), archive.namelist())

    def test_the_name_carries_the_version_and_the_output_stays_outside_the_tree(self):
        version = test_host_guide.re.search(r"const string CURRENT = '([^']+)';", (ROOT / 'src' / 'Version.php').read_text()).group(1)
        self.assertEqual(f'legend-of-the-green-dragon-resurrection-{version}.zip', Path(self.first['zip']).name)
        for bad in [ROOT, ROOT / 'build', Path('relative/folder')]:
            with self.subTest(out=str(bad)):
                with self.assertRaises(self.package.Refused):
                    self.package.build(bad)
        self.assertFalse((ROOT / 'build').exists())

    def test_the_script_never_publishes(self):
        source = SCRIPT.read_text(encoding='utf-8')
        for word in ['urllib', 'http.client', 'requests', 'socket', "'push'", "'tag'", 'gh ', 'upload(']:
            self.assertNotIn(word, source)
        # The command prints one line of JSON with the zip's path, file count, size and SHA-256.
        self.assertEqual({'zip', 'files', 'bytes', 'sha256'}, set(json.loads(json.dumps(self.first))))
