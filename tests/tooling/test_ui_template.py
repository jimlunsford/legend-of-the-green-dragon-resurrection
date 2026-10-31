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
    'navhead': {'title'}, 'navhelp': {'text'},
    # The nav (Phase 5 task 6): private_addnav() passes {key} only to a navitem part that has it (the key badge); the
    # nav column's wrapping parts, resurrection_nav_column() in lib/pageparts.php, take no keys.
    'navitem': {'text', 'link', 'accesskey', 'popup', 'key'}, 'navstart': set(), 'navend': set(),
    'petitioncount': {'petitioncount'}, 'login': {'username', 'password', 'button'}, 'loginfull': set(),
    'adwrapper': {'content'},
    # The art (Phase 5 task 5): resurrection_scene_html() and resurrection_portrait_html() in lib/pageparts.php.
    'scene': {'src', 'alt', 'width', 'height'}, 'portrait': {'src', 'alt', 'width', 'height', 'name', 'race'},
}
# The tags page_header(), page_footer(), popup_header() and popup_footer() fill in the frame parts, and the four ad
# slots modules fill through the footer hooks (1.1.2's {headerad}, {navad}, {verticalad}, {bodyad}).
FRAME_TAGS = {'title', 'headscript', 'script', 'nav', 'stats', 'petition', 'petitiondisplay', 'motd', 'mail', 'paypal',
              'source', 'version', 'pagegen', 'copyright', 'credits', 'newday', 'scene', 'portrait', 'navstart', 'navend'}
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


def contrast(a, b):
    """The WCAG 2.x contrast ratio of two #RRGGBB colors."""
    def luminance(color):
        channels = [int(color[i:i + 2], 16) / 255 for i in (1, 3, 5)]
        r, g, b = (c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4 for c in channels)
        return 0.2126 * r + 0.7152 * g + 0.0722 * b
    high, low = sorted((luminance(a), luminance(b)), reverse=True)
    return (high + 0.05) / (low + 0.05)


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

    def test_the_scene_and_portrait_slots(self):
        # Phase 5 task 5: the scene banner first in the content card; the portrait at the top of the character panel and
        # in the header (shown there on a phone); neither in a popup. Both are <img> with alt text and their size.
        header, footer = self.parts['header'], self.parts['footer']
        self.assertIn('<div class="rz-card rz-content">{scene}', header)
        self.assertIn('<div class="rz-header-portrait">{portrait}</div>', header)
        # Phase 5 task 7: the character panel's portrait is in its header card (the statheader part, through the
        # statportrait part), so the panel's column holds {stats} alone.
        self.assertIn('<div class="rz-character-inner">{stats}</div>', footer)
        self.assertIn('{portrait}', self.parts['statheader'])
        self.assertEqual((1, 0), (header.count('{scene}'), footer.count('{scene}')))
        self.assertEqual((1, 0), (header.count('{portrait}'), footer.count('{portrait}')))
        for part in ('popuphead', 'popupfoot'):
            self.assertNotIn('{scene}', self.parts[part], part)
            self.assertNotIn('{portrait}', self.parts[part], part)
        for part in ('scene', 'portrait', 'statportrait'):
            image = re.findall(r'<img\b[^>]*>', self.parts[part])
            self.assertEqual(1, len(image), part)
            for attribute in ('src="{src}"', 'alt="{alt}"', 'width="{width}"', 'height="{height}"'):
                self.assertIn(attribute, image[0], part)
        self.assertIn('<div class="rz-scene">', self.parts['scene'])
        self.assertIn('<span class="rz-portrait-name">{name}</span><span class="rz-portrait-race">{race}</span>', self.parts['portrait'])

    def test_the_nav_and_its_key_script(self):
        # Phase 5 task 6: the nav column is wrapped in the navstart and navend parts, which page_footer() leaves out
        # when the page has no nav to show; each item is the key badge, hidden from assistive technology, then the label.
        header = self.parts['header']
        self.assertIn('<div class="rz-columns">\n  {navstart}{navad}{nav}{navend}\n  <main class="rz-main"', header)
        self.assertNotIn('<nav', header)
        self.assertEqual('<nav class="rz-nav" aria-label="Navigation"><div class="rz-card rz-nav-panel">\n', self.parts['navstart'])
        self.assertEqual('</div></nav>\n', self.parts['navend'])
        self.assertEqual('<a href="{link}" {accesskey} class="rz-navitem nav" {popup}><span class="rz-key" aria-hidden="true">{key}</span>'
                         '<span class="rz-navlabel">{text}</span></a>\n', self.parts['navitem'])
        # No inline script in the template or from the code that fills {script}: the key map is data, the script static.
        self.assertNotIn('<script', self.text)
        pageparts = (ROOT / 'lib' / 'pageparts.php').read_text(errors='replace')
        output = (ROOT / 'lib' / 'output.php').read_text(errors='replace')
        for inline in ('onkeypress', 'keyevent', '$quickkeys[$key]="'):
            self.assertNotIn(inline, pageparts, inline)
            self.assertNotIn(inline, output, inline)
        self.assertIn('<script type=\\"application/json\\" id=\\"rz-keys\\">', pageparts)
        self.assertIn('<script src=\\"assets/ui/keys.js\\" defer></script>', pageparts)
        keys = (ROOT / 'assets' / 'ui' / 'keys.js').read_text()
        self.assertTrue(keys.isascii())
        self.assertIn("document.getElementById('rz-keys')", keys)
        self.assertIn("document.addEventListener('keypress'", keys)
        # 1.1.2's rule: no key while typing in an input or a textarea, or with Alt or Ctrl held.
        self.assertIn("if (name === 'INPUT' || name === 'TEXTAREA' || e.altKey || e.ctrlKey) { return; }", keys)
        for action in ("'go'", "'open'", "'popup'"):
            self.assertIn(action, keys)
        for unsafe in ('eval(', 'innerHTML', 'document.write', 'new Function', 'http:', 'https:', 'import '):
            self.assertNotIn(unsafe, keys, unsafe)

    def test_the_nav_row_and_badge_styles(self):
        # Phase 5 task 6: 44px rows; hover and focus darken the row, so the badge outline keeps 3:1 or more; the badge
        # sits on the nav's own background, never --raised.
        css = (ROOT / 'assets' / 'ui' / 'resurrection.css').read_text()
        tokens = dict(re.findall(r'(--[a-z-]+):\s*(#[0-9A-Fa-f]{6});', (ROOT / 'assets' / 'ui' / 'tokens.css').read_text()))

        def rule(selector):
            found = re.search(r'(?m)^' + re.escape(selector) + r'\s*\{([^}]*)\}', css)
            self.assertIsNotNone(found, selector)
            return found.group(1)

        row = rule('.rz-navitem')
        self.assertIn('min-height: var(--touch-target);', row)
        self.assertIn('width: 100%;', row)
        self.assertIn('border-radius: var(--radius-control);', row)
        fill = re.search(r'background: var\((--[a-z-]+)\)', rule('.rz-navitem:hover, .rz-navitem:focus-visible')).group(1)
        badge = rule('.rz-key')
        for value in ('width: var(--key-badge-size);', 'height: var(--key-badge-size);', 'border: 1px solid var(--input-border);',
                      'border-radius: var(--radius-badge);', 'background: transparent;', 'color: var(--accent);',
                      'font-family: var(--font-ui);', 'font-weight: var(--weight-ui-bold);', 'font-size: var(--size-small);'):
            self.assertIn(value, badge)
        self.assertNotIn('--raised', badge)
        self.assertNotIn('--raised', rule('.rz-navitem:hover, .rz-navitem:focus-visible'))
        self.assertIn('border-color: transparent;', rule('.rz-key:empty'))
        # The badge outline against every fill it sits on: the nav card and the hovered or focused row.
        self.assertEqual('--ground', fill)
        for behind, expected in (('--panel', 3.04), ('--ground', 3.26)):
            ratio = contrast(tokens['--input-border'], tokens[behind])
            self.assertGreaterEqual(ratio, 3.0, behind)
            self.assertEqual(expected, round(ratio, 2), behind)
        # The key and the label stay readable on the row fill.
        self.assertGreaterEqual(contrast(tokens['--accent'], tokens[fill]), 4.5)
        self.assertGreaterEqual(contrast(tokens['--text'], tokens[fill]), 4.5)

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

    def test_contrast(self):
        self.assertEqual(21.0, round(contrast('#FFFFFF', '#000000'), 2))
        self.assertEqual(13.99, round(contrast('#ECE4D4', '#1B1814'), 2))

    def test_tags_match_the_clean_up(self):
        self.assertEqual(['a', 'b'], tags('{a} { not a tag } {b}\n{\nno}'))


if __name__ == '__main__':
    unittest.main()
