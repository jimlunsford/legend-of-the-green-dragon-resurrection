"""The text the LoGD 1.1.2 license and about page require Resurrection to keep.

1.1.2's about page says: "Users of the source are free to view and modify the source, but original copyright
information, and original text from the about page must be preserved, though they may be added to." LICENSE.txt holds
the authors' terms and README.txt their credits.

Compared to the historical tag, the about page's files (about.php and every lib/about/*.php, one per op) must still
hold every original copyright line and every sentence of the original displayed text, and LICENSE.txt and README.txt
must be byte for byte the tag's copies. Added text passes; removed or altered original text fails.
"""
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
TAG = 'historical-source-1.1.2'
TAG_COMMIT = 'bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2'
BYTE_IDENTICAL = ('LICENSE.txt', 'README.txt')
# The functions whose string arguments the about page shows: page text, translated labels, the page title and the
# navigation labels. The setup page's labels are array values ("pvp"=>"Enable Slay Other Players,viewonly").
SHOWN = r'(?:output|output_notl|rawoutput|translate_inline|addnav|page_header)'
STRING = r"""'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*\""""
COPYRIGHT = re.compile(r'copyright|©|\(c\)', re.I)


def historical(path):
    return subprocess.run(['git', '-C', str(ROOT), 'show', f'{TAG}:{path}'], capture_output=True, check=True).stdout


def historical_paths():
    listing = subprocess.run(['git', '-C', str(ROOT), 'ls-tree', '-r', '--name-only', TAG, '--', 'about.php', 'lib/about/'],
                             capture_output=True, check=True, text=True).stdout
    return sorted(listing.split())


def shown_strings(source):
    """Every string literal passed to a function that shows text, or given as an array value, as written."""
    found = [literal.group(1)[1:-1] for literal in re.finditer(r'=>\s*(' + STRING + ')', source)]
    for call in re.finditer(r'\b' + SHOWN + r'\s*\(', source):
        depth, index = 1, call.end()
        while index < len(source) and depth:
            literal = re.compile(STRING).match(source, index)
            if literal:
                found.append(literal.group(0)[1:-1])
                index = literal.end()
                continue
            depth += {'(': 1, ')': -1}.get(source[index], 0)
            index += 1
    return found


def sentences(text):
    """A shown string split after each sentence end that is followed by whitespace."""
    return [part for part in re.split(r'(?<=[.!?])\s+', text) if part.strip()]


def copyright_lines(source):
    return [line.strip() for line in source.splitlines() if COPYRIGHT.search(line)]


def missing(original, current):
    """The original copyright lines and shown sentences that the current source no longer holds."""
    lines = {line.strip() for line in current.splitlines()}
    shown = '\n'.join(shown_strings(current))
    gone = [('copyright line', line) for line in copyright_lines(original) if line not in lines]
    gone += [('sentence', sentence) for text in shown_strings(original) for sentence in sentences(text) if sentence not in shown]
    return gone


class LicenseTextTests(unittest.TestCase):
    def test_tag_is_the_historical_source(self):
        commit = subprocess.run(['git', '-C', str(ROOT), 'rev-parse', TAG + '^{commit}'], capture_output=True, check=True, text=True)
        self.assertEqual(TAG_COMMIT, commit.stdout.strip())

    def test_license_and_credits_are_byte_identical_to_the_tag(self):
        for path in BYTE_IDENTICAL:
            with self.subTest(path=path):
                self.assertEqual(historical(path), (ROOT / path).read_bytes(), path + ' differs from ' + TAG)

    def test_about_page_keeps_every_original_copyright_line_and_sentence(self):
        paths = historical_paths()
        # about.php and its four ops: default, setup, listmodules, license.
        self.assertEqual(['about.php', 'lib/about/about_default.php', 'lib/about/about_license.php',
                          'lib/about/about_listmodules.php', 'lib/about/about_setup.php'], paths)
        checked = 0
        for path in paths:
            with self.subTest(path=path):
                self.assertTrue((ROOT / path).is_file(), path + ' was removed')
                original = historical(path).decode('utf-8')
                self.assertEqual([], missing(original, (ROOT / path).read_text(encoding='utf-8')), path)
                checked += len(copyright_lines(original)) + sum(len(sentences(text)) for text in shown_strings(original))
        # The comparison is not vacuous: the 1.1.2 files hold this many copyright lines and shown sentences.
        self.assertGreater(checked, 100)

    def test_about_page_still_serves_every_op(self):
        about = (ROOT / 'about.php').read_text(encoding='utf-8')
        self.assertIn('case "setup": case "listmodules": case "license":', about)
        self.assertIn('require("lib/about/about_$op.php");', about)
        self.assertIn('require("lib/about/about_default.php");', about)

    def test_the_check_fails_when_original_text_is_removed_or_altered(self):
        original = historical('lib/about/about_default.php').decode('utf-8')
        line = 'output("`@\\"`2Users of the source are free to view and modify the source, but original copyright information, and original text from the about page must be preserved, though they may be added to.`@\\"`n`n");'
        self.assertIn(line, original)
        self.assertEqual([], missing(original, original + '\noutput("Added by an operator.");\n'))
        removed = missing(original, original.replace(line, ''))
        self.assertEqual(['copyright line', 'sentence'], [kind for kind, _ in removed])
        self.assertTrue(all('original text from the about page must be preserved' in text for _, text in removed))
        self.assertNotEqual([], missing(original, original.replace('Eric Stevens & JT Traub`n', 'Somebody Else`n')))
        notice = ' * @copyright Copyright © 2002-2005, Eric Stevens & JT Traub, © 2006-2009, Dragonprime Development Team'
        self.assertIn(notice, original)
        self.assertEqual([('copyright line', notice.strip())], missing(original, original.replace(notice, ' *')))


if __name__ == '__main__':
    unittest.main()
