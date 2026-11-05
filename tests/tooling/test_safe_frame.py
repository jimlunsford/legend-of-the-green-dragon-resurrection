"""The safe frame and the template checks (Phase 5 close-out, docs/PHASE5-CLOSEOUT-20261105.md).

src/Ui/SafeFrame.php holds a built-in template that prepare_template() renders when templates/resurrection.htm is
missing, unreadable, or fails its checks, so a host cannot drop the original credits, the license block or the
Resurrection attribution line by editing one file. This test reads the frame statically: it has every part the template
has, the tags the checks require, {copyright} and the attribution line in its footer and popup footer, and markup that
survives the page's own tag cleanup. It pins 1.1.2's checks (the required tags, the XOR copyright tag, the copyright line
and common.php's $copyright / $license checks) byte for byte as they were at 5dfc363, and checks that the 7 images
task 15 found unused are gone and named nowhere.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too). The rendered pages (the
safe frame for each kind of refusal, the notice for a superuser only, the error log) are in
tests/unit/TemplateRenderTest.php.
"""
from pathlib import Path
import hashlib
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
ATTRIBUTION = ('A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a> Project by '
               '<a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a>')
COPYRIGHT_TAG = '{copyright}'
# 1.1.2's required tags: the header's own, and those that may be in the header or the footer.
HEADER_TAGS = ['title', 'headscript', 'script']
ANYWHERE_TAGS = ['nav', 'stats', 'petition', 'motd', 'mail', 'paypal', 'source', 'version', 'copyright']
# The parts the safe frame changes from the template's: the page frame, less the phone's pieces.
FRAME_PARTS = {'header', 'footer', 'popuphead'}
PHONE_TAGS = {'phonehead', 'tabbar'}
# page_header() and page_footer() strip every {...} that has no space or tab inside it as an unused tag.
CLEANUP = re.compile(r'{[^} \t\n\r]*}')
# The 7 images task 15 found unreferenced (docs/PHASE5-TASK15-REMOVE-SKINS-20261105.md), deleted in the close-out.
DELETED_IMAGES = ['images/headbkg.GIF', 'images/logindragon.gif', 'images/lscroll.GIF', 'images/oldscroll.GIF',
                  'images/rule.gif', 'images/title.gif', 'images/uscroll.GIF']
# SHA-256 of 1.1.2's checks as they stand at 5dfc363 (the last commit before the safe frame), each block below.
PINNED = {
    'common.php: the $copyright and $license checks':
        '9671fad6c052979dd8d92d6885426f246f08db81a4bb5ddd6d3a903b1bbfffb6',
    'lib/template.php: the required tags and the XOR copyright tag':
        'ab511a4951ed02614dcb8dc9c1e0b05b526ad148fa2c84c3a741e1eab7694eb6',
    'lib/template.php: the copyright line':
        '1f70300871049d5b6ff7aa31e9c15d30d9eabb31b2f41bf928e5eb9485cebebe',
}


def split(text):
    """A template's parts, split at each "<!--!name-->" marker as loadtemplate() splits them."""
    parts = {}
    for piece in text.split('<!--!'):
        end = piece.find('-->')
        if end > 0:
            parts[piece[:end]] = piece[end + 3:]
    return parts


def frame_source():
    return (ROOT / 'src/Ui/SafeFrame.php').read_text()


def frame():
    """The safe frame's parts, from the nowdoc in src/Ui/SafeFrame.php."""
    m = re.search(r"private const string TEMPLATE = <<<'HTML'\n(.*?)\nHTML;\n", frame_source(), re.S)
    assert m, 'the TEMPLATE nowdoc'
    return split(m.group(1))


def template():
    return split((ROOT / 'templates/resurrection.htm').read_text())


def tags(text):
    return set(re.findall(r'{([a-z_]+)}', text))


def block(text, start, end):
    i = text.index(start)
    return text[i:text.index(end, i) + len(end)]


def pinned_blocks():
    common = (ROOT / 'common.php').read_text(encoding='latin-1')
    tpl = (ROOT / 'lib/template.php').read_text(encoding='latin-1')
    return {
        'common.php: the $copyright and $license checks':
            block(common, "if ($session['user']['superuser']==0){", '\nprepare_template();'),
        'lib/template.php: the required tags and the XOR copyright tag':
            block(tpl, '\t\t//tags that must appear in the header', 'defined in either your header or footer\\n";\n\t\t}\n'),
        'lib/template.php: the copyright line':
            block(tpl, '\t\t$y = 0;\n', '$$z = $lc . $$z . "<br />";\n'),
    }


class SafeFrameTests(unittest.TestCase):
    def test_the_frame_has_every_part_of_the_template(self):
        mine, theirs = frame(), template()
        self.assertEqual(list(theirs), list(mine))
        for name, text in theirs.items():
            if name in FRAME_PARTS:
                self.assertLessEqual(tags(mine[name]), tags(text), name)
            else:
                self.assertEqual(tags(text), tags(mine[name]), name)
        # The frame drops the phone's pieces (the compact header, the tab bar, the More section's MoTD and Petition
        # links, which the header has too); every other tag of the page frame is still there.
        def frame_tags(parts):
            return set().union(*(tags(parts[name]) for name in FRAME_PARTS))
        self.assertEqual(frame_tags(theirs) - PHONE_TAGS, frame_tags(mine))

    def test_the_frame_passes_the_checks_it_stands_in_for(self):
        parts = frame()
        for tag in HEADER_TAGS:
            self.assertIn('{' + tag + '}', parts['header'], tag)
        for tag in ANYWHERE_TAGS:
            self.assertTrue('{' + tag + '}' in parts['header'] or '{' + tag + '}' in parts['footer'], tag)
        for name in ('footer', 'popupfoot'):
            self.assertEqual(1, parts[name].count(COPYRIGHT_TAG), name)
            self.assertEqual(1, parts[name].count(ATTRIBUTION), name)
            self.assertLess(parts[name].index(COPYRIGHT_TAG), parts[name].index(ATTRIBUTION), name)
        self.assertIn("public const string ATTRIBUTION = '" + ATTRIBUTION + "';", frame_source())

    def test_the_frame_stands_without_its_stylesheet(self):
        parts = frame()
        for name in ('header', 'popuphead'):
            text = parts[name]
            self.assertTrue(text.startswith('<!DOCTYPE html>\n'), name)
            self.assertIn('<link rel="stylesheet" href="assets/ui/resurrection.css">', text, name)
            self.assertRegex(text, r'<body class="rz-page (rz-popup )?rz-safe-frame">', name)
        for name in ('footer', 'popupfoot'):
            self.assertTrue(parts[name].endswith('</html>\n'), name)
        for name, text in parts.items():
            # Every {...} the page's tag cleanup would strip is a tag, never markup or a style rule.
            self.assertEqual(set(), {m for m in CLEANUP.findall(text) if not re.fullmatch(r'{[a-z_]+}', m)}, name)
            self.assertNotRegex(text, r'\b(src|href)=["\']?(https?:)?//(?!phoenix233\.com|jimlunsford\.com)', name)
        frame_source().encode('ascii')

    def test_the_frame_is_wired_in(self):
        tpl = (ROOT / 'lib/template.php').read_text()
        pageparts = (ROOT / 'lib/pageparts.php').read_text()
        self.assertIn('$template=\\Resurrection\\Ui\\SafeFrame::parts();', tpl)
        self.assertIn('if (!\\Resurrection\\Ui\\SafeFrame::hasAttribution($template))', tpl)
        self.assertNotIn('echo "<b>You have one or more errors in your template page!', tpl)
        self.assertIn('return \\Resurrection\\Ui\\SafeFrame::parts();', pageparts)
        self.assertEqual(2, pageparts.count('resurrection_template_notice()'))
        # The check still runs whenever the template file changes: the session caches its name and mtime.
        self.assertIn('$session[\'templatemtime\']==filemtime("templates/$templatename")', tpl)

    def test_the_1_1_2_checks_are_unchanged(self):
        for name, text in pinned_blocks().items():
            self.assertEqual(PINNED[name], hashlib.sha256(text.encode('latin-1')).hexdigest(), name)
        tpl = (ROOT / 'lib/template.php').read_text()
        self.assertEqual(1, tpl.count('if (!$key) array_push($templatetags,$y2^$z2);'))
        self.assertEqual(1, tpl.count('$z = $y2^$z2;'))

    def test_the_unused_images_are_gone(self):
        listed = subprocess.run(['git', 'ls-files', '-z', '--', 'images'], cwd=ROOT, capture_output=True,
                                check=True).stdout.decode().split('\0')
        for name in DELETED_IMAGES:
            self.assertNotIn(name, listed, name)
            self.assertFalse((ROOT / name).exists(), name)
        names = [Path(n).name.lower() for n in DELETED_IMAGES]
        out = subprocess.run(['git', 'ls-files', '-z'], cwd=ROOT, capture_output=True, check=True).stdout.decode()
        found = []
        for name in out.split('\0'):
            if not name or name.startswith(('docs/', 'tests/', 'vendor/', 'images/', 'assets/fonts/')):
                continue
            path = ROOT / name
            if not path.is_file() or path.suffix.lower() in {'.png', '.gif', '.jpg', '.ico', '.woff2', '.woff', '.zip'}:
                continue
            text = path.read_bytes().decode('latin-1').lower()
            found += [f'{name}: {image}' for image in names if image in text]
        self.assertEqual([], found)

    def test_the_scans_find_what_they_look_for(self):
        self.assertEqual({'{max-width:100%;height:auto}'}, set(CLEANUP.findall('img{max-width:100%;height:auto}')))
        self.assertEqual([], CLEANUP.findall('img { max-width: 100%; height: auto }'))
        self.assertEqual({'header': 'a', 'footer': 'b'}, split('<!--!header-->a<!--!footer-->b'))


if __name__ == '__main__':
    unittest.main()
