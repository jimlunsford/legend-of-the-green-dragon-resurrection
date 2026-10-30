"""The Resurrection UI's template, templates/resurrection.htm, and its place as the default skin (Phase 5 task 3).

loadtemplate() (lib/pageparts.php) splits a template into parts at each "<!--!name-->" marker, and templatereplace()
(lib/template.php) prints a warning into the page when the code asks for a part, or fills a key, that the template
lacks. prepare_template() rejects a template without its required tags, and the copyright line rides on the
{copyright} tag. So this test reads, from the code itself, every part the game asks for and every required tag, and
checks the new template has them, with {copyright} exactly once in the page footer and once in the popup footer.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too). The PHP-CLI renders
of real pages are in tests/unit/TemplateRenderTest.php.
"""
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
TEMPLATE = ROOT / 'templates' / 'resurrection.htm'

# The keys each part is filled with, as the code passes them (templatereplace() calls in lib/output.php,
# lib/pageparts.php and home.php). A key missing from its part prints a warning into the page.
PART_KEYS = {
    'statstart': set(), 'statend': set(),
    'stathead': {'title'}, 'statrow': {'title', 'value'}, 'statbuff': {'title', 'value'},
    'navhead': {'title'}, 'navhelp': {'text'}, 'navitem': {'text', 'link', 'accesskey', 'popup'},
    'petitioncount': {'petitioncount'}, 'login': {'username', 'password', 'button'}, 'loginfull': set(),
    'adwrapper': {'content'},
}
# The tags page_header(), page_footer(), popup_header() and popup_footer() fill in the frame parts, and the four ad
# slots modules fill through the footer hooks (1.1.2's {headerad}, {navad}, {verticalad}, {bodyad}).
FRAME_TAGS = {'title', 'headscript', 'script', 'nav', 'stats', 'petition', 'petitiondisplay', 'motd', 'mail', 'paypal',
              'source', 'version', 'pagegen', 'copyright', 'credits', 'newday'}
AD_SLOTS = {'headerad', 'navad', 'verticalad', 'bodyad'}
# page_footer() fills these in the footer part only; popup_footer() fills only {copyright}, in popupfoot.
FOOTER_ONLY = {'copyright', 'version', 'pagegen', 'credits', 'newday'}
# The Resurrection attribution (Phase 5 task 4): its own line, with both links, in the page footer and the popup footer.
ATTRIBUTION = ('<p class="rz-attribution">A <a href="https://phoenix233.com" target="_blank" rel="noopener">Phoenix 2:33 LLC</a>'
               ' Project by <a href="https://jimlunsford.com" target="_blank" rel="noopener">Jim Lunsford</a></p>')


def parts(text):
    """The template's parts, split as loadtemplate() splits them: {name: content}."""
    found = {}
    for piece in text.split('<!--!'):
        end = piece.find('-->')
        name = piece[:end] if end >= 0 else ''
        if name != '':
            if name in found:
                raise AssertionError(f'Part {name} is defined twice')
            found[name] = piece[end + 3:]
    return found


def tracked_php():
    listed = subprocess.run(['git', 'ls-files', '-z', '--', '*.php'], cwd=ROOT, capture_output=True, check=True).stdout
    names = [name for name in listed.decode().split('\0') if name and not name.startswith(('tests/', 'vendor/'))]
    return {name: (ROOT / name).read_text(errors='replace') for name in names if (ROOT / name).is_file()}


def parts_the_code_uses(sources):
    """Every part named by a templatereplace() call or a $template[...] read, and the template-<part> hooks common.php
    loads: {part: [files]}."""
    used = {}
    for name, text in sources.items():
        for part in re.findall(r'''templatereplace\(\s*["']([A-Za-z0-9_]+)["']''', text):
            used.setdefault(part, set()).add(name)
        for part in re.findall(r'''\$template\[\s*["']([A-Za-z0-9_]+)["']\s*\]''', text):
            used.setdefault(part, set()).add(name)
        for part in re.findall(r'''['"]template-([A-Za-z0-9_]+)['"]''', text):
            used.setdefault(part, set()).add(name)
    return {part: sorted(files) for part, files in used.items()}


def required_tags(source):
    """prepare_template()'s required tags (lib/template.php): (header tags, header-or-footer tags)."""
    lists = re.findall(r'\$templatetags\s*=\s*array\(([^)]*)\)', source)
    if len(lists) != 3:
        raise AssertionError('prepare_template() tag lists not found')
    header, footer, anywhere = ([tag for tag in re.findall(r'"([a-z]+)"', block)] for block in lists)
    if footer:
        raise AssertionError('prepare_template() now requires footer tags; read them here too')
    return header, anywhere


def xor_tag(common):
    """The extra required tag prepare_template() pushes at run time, $y2 ^ $z2 (common.php)."""
    def bytes_of(name):
        literal = re.search(r'\$' + name + r'\s*=\s*"([^"]*)";', common).group(1)
        return bytes(int(h, 16) for h in re.findall(r'\\x([0-9a-fA-F]{1,2})', literal))
    return bytes(a ^ b for a, b in zip(bytes_of('y2'), bytes_of('z2'))).decode()


def tags(text):
    """The {tags} the clean-up in page_footer() and popup_footer() would remove: {[^} \\t\\n\\r]*}."""
    return re.findall(r'\{([^} \t\n\r]*)\}', text)


class TemplateTests(unittest.TestCase):

    def setUp(self):
        self.text = TEMPLATE.read_text()
        self.parts = parts(self.text)

    def test_every_part_the_code_uses_exists(self):
        used = parts_the_code_uses(tracked_php())
        self.assertGreaterEqual(len(used), 16, used)
        for part, files in sorted(used.items()):
            self.assertIn(part, self.parts, f'{part} is used by {", ".join(files)}')
        # The keys the code fills each part with.
        self.assertTrue(set(PART_KEYS) <= set(used), set(PART_KEYS) - set(used))
        for part, keys in PART_KEYS.items():
            self.assertEqual(keys, set(tags(self.parts[part])), part)

    def test_required_tags_and_the_copyright_line(self):
        header, anywhere = required_tags((ROOT / 'lib' / 'template.php').read_text())
        extra = xor_tag((ROOT / 'common.php').read_text())
        self.assertEqual(['title', 'headscript', 'script'], header)
        for tag in header:
            self.assertIn('{' + tag + '}', self.parts['header'], tag)
        for tag in anywhere + [extra]:
            self.assertTrue('{' + tag + '}' in self.parts['header'] or '{' + tag + '}' in self.parts['footer'], tag)
        # The copyright line: once in the page footer and once in the popup footer, nowhere else.
        self.assertEqual('copyright', extra)
        for part in self.parts:
            expected = 1 if part in ('footer', 'popupfoot') else 0
            self.assertEqual(expected, self.parts[part].count('{copyright}'), part)
        for tag in FOOTER_ONLY:
            self.assertEqual(1, self.parts['footer'].count('{' + tag + '}'), tag)
            self.assertNotIn('{' + tag + '}', self.parts['header'], tag)
        # The footer's Credits and Source links. Credits is {credits}: page_footer() writes the link only where about.php
        # is a nav the page allows (Phase 5 task 4), so the template has no fixed about.php link that could cause a bad nav.
        self.assertIn('{copyright}{credits}{source}', self.parts['footer'])
        self.assertNotIn('about.php', self.text)

    def test_frame_tags_are_ones_the_code_fills(self):
        pageparts = (ROOT / 'lib' / 'pageparts.php').read_text()
        for tag in FRAME_TAGS - {'copyright'}:
            self.assertIn('{' + tag + '}', pageparts, tag)
        for part in ('header', 'footer', 'popuphead', 'popupfoot'):
            for tag in tags(self.parts[part]):
                self.assertIn(tag, FRAME_TAGS | AD_SLOTS, f'{{{tag}}} in {part} is filled by nothing, and removed')
        for tag in AD_SLOTS:
            self.assertEqual(1, self.text.count('{' + tag + '}'), tag)

    def test_the_attribution_line(self):
        # On every page and popup, its own line after the 1.1.2 credit line, never inside it; exactly once per part.
        for part in self.parts:
            expected = 1 if part in ('footer', 'popupfoot') else 0
            self.assertEqual(expected, self.parts[part].count(ATTRIBUTION), part)
            self.assertEqual(expected, self.parts[part].count('Phoenix 2:33 LLC'), part)
        for part in ('footer', 'popupfoot'):
            text = self.parts[part]
            credit = text.index('<div class="rz-footer-credits">{copyright}')
            line = text.index(ATTRIBUTION)
            self.assertLess(credit, line, part)
            # The credit line's own element closes before the attribution starts.
            self.assertLess(text.index('</div>', credit), line, part)
            self.assertLess(line, text.index('</footer>'), part)
        # Plain links: nothing is requested until a link is clicked.
        for url in ('https://phoenix233.com', 'https://jimlunsford.com'):
            self.assertEqual(2, self.text.count(f'href="{url}"'), url)
        css = (ROOT / 'assets' / 'ui' / 'resurrection.css').read_text()
        rule = re.search(r'\.rz-attribution\s*\{([^}]*)\}', css)
        self.assertIsNotNone(rule)
        for hidden in ('display: none', 'visibility', 'opacity', 'font-size: 0', 'clip', 'position: absolute', 'height: 0'):
            self.assertNotIn(hidden, rule.group(1))
        self.assertIn('font-size: var(--size-small)', rule.group(1))

    def test_page_and_popup_heads(self):
        for part in ('header', 'popuphead'):
            head = self.parts[part]
            # The page starts with the doctype: nothing, not even a newline, before it.
            self.assertTrue(head.startswith('<!DOCTYPE html>\n<html lang="en">\n<head>\n'), part)
            self.assertIn('<meta name="viewport" content="width=device-width, initial-scale=1">', head)
            self.assertIn('<link rel="stylesheet" href="assets/ui/resurrection.css">', head)
            self.assertEqual(2, head.count('{title}'), part)
        for part in ('footer', 'popupfoot'):
            self.assertTrue(self.parts[part].endswith('</body>\n</html>\n'), part)

    def test_ascii_only(self):
        # Pages are sent in the game's charset setting (iso-8859-1 by default): the template stays ASCII, with entities.
        self.assertTrue(TEMPLATE.read_bytes().isascii())

    def test_the_stylesheet_and_what_it_loads(self):
        for href in re.findall(r'<link[^>]*href="([^"]+)"', self.text):
            self.assertTrue((ROOT / href).is_file(), href)
        css = (ROOT / 'assets' / 'ui' / 'resurrection.css').read_text()
        self.assertEqual(['fonts.css', 'tokens.css'], re.findall(r"@import url\('([^']+)'\);", css))
        for name in ('fonts.css', 'tokens.css'):
            self.assertTrue((ROOT / 'assets' / 'ui' / name).is_file(), name)
        self.assertTrue(css.isascii())
        # The breakpoints are literals equal to their tokens (a custom property cannot be used in a media query).
        tokens = (ROOT / 'assets' / 'ui' / 'tokens.css').read_text()
        for token, width in (('--breakpoint-stack', '900px'), ('--breakpoint-phone', '600px')):
            self.assertIn(f'{token}: {width};', tokens)
            self.assertIn(f'@media (max-width: {width})', css)

    def test_it_is_the_default_skin(self):
        defaults = (ROOT / 'lib' / 'installer' / 'installer_default_settings.php').read_text()
        self.assertRegex(defaults, r'"defaultskin"=>"resurrection\.htm",')
        for name, count in (('lib/template.php', 1), ('lib/pageparts.php', 1), ('home.php', 1), ('prefs.php', 1)):
            source = (ROOT / name).read_text()
            self.assertEqual(count, source.count('getsetting("defaultskin", "resurrection.htm")'), name)
            self.assertNotIn('"jade.htm"', source, name)
        for name in ('lib/template.php', 'lib/pageparts.php'):
            source = (ROOT / name).read_text()
            self.assertIn('$templatename="resurrection.htm";', source, name)
        self.assertIn('$template=loadtemplate("resurrection.htm");', (ROOT / 'lib' / 'template.php').read_text())
        # The old skins stay selectable until Phase 5 task 15.
        self.assertTrue((ROOT / 'templates' / 'jade.htm').is_file())


class TemplatePartsReaderTests(unittest.TestCase):
    """The readers themselves."""

    def test_parts_split_like_loadtemplate(self):
        self.assertEqual({'a': 'one\n', 'b': '<p>{x}</p>'}, parts('<!--!a-->one\n<!--!b--><p>{x}</p>'))

    def test_the_xor_tag_is_copyright(self):
        self.assertEqual('copyright', xor_tag((ROOT / 'common.php').read_text()))

    def test_tags_match_the_clean_up(self):
        self.assertEqual(['a', 'b'], tags('{a} { not a tag } {b}\n{\nno}'))


if __name__ == '__main__':
    unittest.main()
