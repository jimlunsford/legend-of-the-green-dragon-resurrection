"""Phase 6 task 20 (R9): every text file the release package ships is valid UTF-8, with no U+FFFD.

Jim's rc.1 server showed master 5 as "Un?lith": 1.1.2's installer carried U+FFFD, the replacement character, where its
Latin-1 letters had been lost, and prefs.php had the same in its default language list. lib/censor.php held 1.1.2's raw
Latin-1 bytes. Task 20 wrote the UTF-8 text 1.1.2 meant ("Unélith", "Français", "Español", "kyrpä*") and the censor's
bytes as PCRE octal escapes (the same patterns, byte for byte). This test reads the package's own file list
(scripts/build-release-package.py) and fails on any text file that is not valid UTF-8 or holds U+FFFD, naming the file
and line. Static: runs wherever the suite runs.
"""
import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
BINARY = {'.png', '.ico', '.jpg', '.jpeg', '.gif', '.webp', '.woff', '.woff2', '.ttf', '.otf', '.zip', '.gz'}


def package_files():
    spec = importlib.util.spec_from_file_location('build_release_package', ROOT / 'scripts' / 'build-release-package.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module.package_files(ROOT)


def problems(path, data):
    """Each (file:line, what) where the bytes are not valid UTF-8 or hold U+FFFD."""
    found = []
    for number, line in enumerate(data.split(b'\n'), 1):
        try:
            text = line.decode('utf-8')
        except UnicodeDecodeError as error:
            found.append((f'{path}:{number}', f'not UTF-8 at byte {error.start}: {line[max(0, error.start - 20):error.start + 20]!r}'))
            continue
        if '\ufffd' in text:
            found.append((f'{path}:{number}', 'U+FFFD, a lost character'))
    return found


class ShippedTextIsUtf8Tests(unittest.TestCase):

    def test_every_shipped_text_file_is_valid_utf8_without_replacement_characters(self):
        files = package_files()
        self.assertGreater(len(files), 600)
        text = [name for name in files if Path(name).suffix.lower() not in BINARY]
        self.assertIn('lib/installer/installer_sqlstatements.php', text)
        self.assertIn('lib/censor.php', text)
        found = []
        for name in text:
            data = (ROOT / name).read_bytes()
            if b'\0' in data:
                self.fail(f'{name}: a NUL byte in a file not listed as binary')
            found += problems(name, data)
        self.assertEqual([], found)

    def test_the_check_finds_what_it_must(self):
        self.assertEqual([('a.php:2', 'U+FFFD, a lost character')], problems('a.php', "ok\nUn\ufffdlith\n".encode()))
        self.assertEqual('a.php:1', problems('a.php', b'Fran\xe7ais')[0][0])
        self.assertEqual([], problems('a.php', 'Unélith Français Español kyrpä'.encode()))

    def test_the_seed_text_is_what_1_1_2_meant(self):
        installer = (ROOT / 'lib' / 'installer' / 'installer_sqlstatements.php').read_text(encoding='utf-8')
        for text in ["VALUES (5,'Unélith',5,", "fr,Français,", "es,Español,", " kyrpä* "]:
            self.assertIn(text, installer)
        prefs = (ROOT / 'prefs.php').read_text(encoding='utf-8')
        self.assertIn('fr,Français,dk,Danish,es,Español,', prefs)
        censor = (ROOT / 'lib' / 'censor.php').read_bytes()
        self.assertTrue(censor.isascii())
        self.assertIn(rb'''str_replace("a",'[a4@\252\300\301\302\303\304\305\340\341\342\343\344\345]',$search);''', censor)


if __name__ == '__main__':
    unittest.main()
