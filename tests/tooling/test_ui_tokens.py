"""The UI design tokens, the color codes, the bundled fonts and the no-third-party-request rule (Phase 5 task 2).

docs/UI-DESIGN-SPEC.md is the design contract and assets/ui/tokens.css its CSS form: they must hold the same values.
Every text color must stay readable (WCAG 2.x contrast, computed here), every color class appoencode() can write must
be styled, every bundled font must be the file docs/THIRD-PARTY-LICENSES.md records, with its license beside it, and
no page resource may come from another server.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too).
"""
import colorsys
import hashlib
import math
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = ROOT / 'docs' / 'UI-DESIGN-SPEC.md'
TOKENS = ROOT / 'assets' / 'ui' / 'tokens.css'
FONTS_CSS = ROOT / 'assets' / 'ui' / 'fonts.css'
FONTS = ROOT / 'assets' / 'fonts'
LICENSES = ROOT / 'docs' / 'THIRD-PARTY-LICENSES.md'
OUTPUT = ROOT / 'lib' / 'output.php'
TEXT_MINIMUM = 4.5   # WCAG 2.x, normal text
UI_MINIMUM = 3.0     # WCAG 2.x, user interface components and graphics

SPEC_TOKEN = re.compile(r'^\| `(--[a-z0-9-]+)` \| `([^`]+)` \|(.*)$', re.M)
SPEC_CODE = re.compile(r"^\| `` `(.) `` \| `(col\w+)` \| `(#[0-9A-Fa-f]{6})` \|", re.M)
CSS_PROPERTY = re.compile(r'(--[A-Za-z0-9-]+)\s*:\s*([^;]+);')
CSS_CLASS_RULE = re.compile(r'\.(col\w+)\s*\{\s*color\s*:\s*var\((--[A-Za-z0-9-]+)\)\s*;\s*\}')


def normalized(value):
    return re.sub(r'\s+', ' ', value.strip())


def without_comments(css):
    return re.sub(r'/\*.*?\*/', '', css, flags=re.S)


def root_properties(css):
    """The custom properties declared in the stylesheet's :root block, in order."""
    blocks = re.findall(r':root\s*\{(.*?)\}', without_comments(css), re.S)
    found = {}
    for block in blocks:
        for name, value in CSS_PROPERTY.findall(block):
            if name in found:
                raise AssertionError(f'{name} is declared twice')
            found[name] = normalized(value)
    return found


def spec_tokens(spec):
    """Every `--token` row of the spec's tables: {token: (value, the rest of the row)}."""
    found = {}
    for name, value, rest in SPEC_TOKEN.findall(spec):
        if name in found:
            raise AssertionError(f'The spec lists {name} twice')
        found[name] = (normalized(value), rest)
    return found


def spec_codes(spec):
    """The color code table: [(code character, class, value)]."""
    return SPEC_CODE.findall(spec)


def appoencode_classes(source):
    """The color classes appoencode() writes, read from its $colors table: {code character: class}."""
    table = re.search(r'static \$colors = array\((.*?)\);', source, re.S)
    if table is None:
        raise AssertionError('appoencode() color table not found in lib/output.php')
    entries = re.findall(r'"((?:\\.|[^"\\])+)"\s*=>\s*"(\w+)"', table.group(1))
    return {code.replace('\\$', '$'): name for code, name in entries}


def luminance(color):
    channels = [int(color[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    linear = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in channels]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]


def contrast(first, second):
    light, dark = sorted([luminance(first), luminance(second)], reverse=True)
    return (light + 0.05) / (dark + 0.05)


def font_records(licenses):
    """The checksum table of the licenses doc: {path: sha256}."""
    section = licenses.split('Every bundled font file and its SHA-256', 1)
    if len(section) != 2:
        raise AssertionError('docs/THIRD-PARTY-LICENSES.md has no bundled font checksum table')
    return dict(re.findall(r'^\| `(assets/fonts/[^`]+)` \| `([0-9a-f]{64})` \|', section[1], re.M))


# A resource the browser fetches by itself: src/srcset/background/poster/data attributes, a <link href>, CSS url()
# and @import. A plain <a href> is a link the player chooses, not a request, and is allowed.
RESOURCE_ATTRIBUTE = re.compile(r'''\b(?:src|srcset|background|poster|data|xlink:href)\s*=\s*(['"]?)\s*([^'"\s>]+)''', re.I)
LINK_TAG = re.compile(r'<link\b[^>]*>', re.I)
LINK_HREF = re.compile(r'''\bhref\s*=\s*(['"]?)\s*([^'"\s>]+)''', re.I)
CSS_URL = re.compile(r'''url\(\s*(['"]?)\s*([^'")\s]+)''', re.I)
CSS_IMPORT = re.compile(r'''@import\s+(?:url\(\s*)?(['"]?)\s*([^'")\s;]+)''', re.I)
EXTERNAL = re.compile(r'^(?:[a-z][a-z0-9+.-]*:|//|\\\\)', re.I)
ALLOWED_SCHEME = re.compile(r'^(?:data|blob):', re.I)
SCANNED = {'.css', '.htm', '.html', '.svg', '.js', '.xml'}


def external_requests(name, text):
    """Every resource request in a file that would leave the game: [(file, kind, URL)]."""
    found = []
    candidates = [('attribute', m.group(2)) for m in RESOURCE_ATTRIBUTE.finditer(text)]
    for tag in LINK_TAG.findall(text):
        candidates += [('<link href>', m.group(2)) for m in LINK_HREF.finditer(tag)]
    candidates += [('url()', m.group(2)) for m in CSS_URL.finditer(text)]
    candidates += [('@import', m.group(2)) for m in CSS_IMPORT.finditer(text)]
    for kind, url in candidates:
        if EXTERNAL.match(url) and not ALLOWED_SCHEME.match(url) and url not in [f[2] for f in found]:
            found.append((name, kind, url))
    return found


class UiTokenTests(unittest.TestCase):
    def setUp(self):
        self.spec = SPEC.read_text()
        self.css = TOKENS.read_text()
        self.properties = root_properties(self.css)

    def test_tokens_equal_the_spec(self):
        spec = {name: value for name, (value, _) in spec_tokens(self.spec).items()}
        self.assertGreater(len(spec), 40, 'The spec tables were not read')
        css = {name: value for name, value in self.properties.items() if not name.startswith('--code-')}
        self.assertEqual(spec, css)
        codes = spec_codes(self.spec)
        expected = {f'--code-{name}': value for _, name, value in codes}
        self.assertEqual(expected, {n: v for n, v in self.properties.items() if n.startswith('--code-')})
        # Every class rule reads its own property.
        rules = dict(CSS_CLASS_RULE.findall(without_comments(self.css)))
        for _, name, _ in codes:
            self.assertEqual(f'--code-{name}', rules.get(name), name)

    def test_text_tokens_and_color_codes_are_readable(self):
        tokens = spec_tokens(self.spec)
        text = [name for name, (_, rest) in tokens.items() if rest.strip().endswith('| yes |')]
        self.assertGreaterEqual(len(text), 8, 'The spec marks no text tokens')
        grounds = {name: self.properties[name] for name in ('--panel', '--raised')}
        colors = [(name, self.properties[name]) for name in text]
        colors += [(name, value) for name, value in self.properties.items() if name.startswith('--code-')]
        self.assertGreaterEqual(len(colors), 48)
        failing = [f'{name} {value} on {ground} {background}: {contrast(value, background):.2f}'
                   for name, value in colors for ground, background in grounds.items()
                   if contrast(value, background) < TEXT_MINIMUM]
        self.assertEqual([], failing)
        # The controls' outline against the panel they sit on, and an enemy's bar against its empty track (Jim,
        # 2026-10-30): the non-text minimum.
        self.assertGreaterEqual(contrast(self.properties['--input-border'], self.properties['--panel']), UI_MINIMUM)
        self.assertGreaterEqual(contrast(self.properties['--foe'], self.properties['--track']), UI_MINIMUM)
        self.assertGreaterEqual(contrast(self.properties['--health'], self.properties['--track']), UI_MINIMUM)

    def test_every_color_class_appoencode_writes_is_styled(self):
        classes = appoencode_classes(OUTPUT.read_text())
        self.assertGreaterEqual(len(classes), 41, 'The appoencode() color table was not read')
        rules = dict(CSS_CLASS_RULE.findall(without_comments(self.css)))
        for code, name in classes.items():
            self.assertIn(name, rules, f'`{code} writes class {name}, which tokens.css does not style')
            self.assertIn(rules[name], self.properties, name)
        # The spec lists each code with the class appoencode() writes for it.
        self.assertEqual(classes, {code: name for code, name, _ in spec_codes(self.spec)})

    def test_bundled_fonts_are_the_recorded_files_with_their_licenses(self):
        records = font_records(LICENSES.read_text())
        self.assertGreaterEqual(len(records), 16)
        on_disk = sorted(str(p.relative_to(ROOT)) for p in FONTS.rglob('*') if p.is_file() and p.name != 'OFL.txt')
        self.assertEqual(sorted(records), on_disk, 'Every file under assets/fonts/ is recorded, and nothing else')
        for path, digest in records.items():
            self.assertTrue(path.endswith('.woff2'), path)
            self.assertEqual(digest, hashlib.sha256((ROOT / path).read_bytes()).hexdigest(), path)
        families = sorted(p for p in FONTS.iterdir() if p.is_dir())
        self.assertEqual(['alegreya', 'alegreya-sans', 'cinzel'], [p.name for p in families])
        for family in families:
            license_file = family / 'OFL.txt'
            self.assertTrue(license_file.is_file(), f'{family.name} has no OFL.txt')
            self.assertIn('SIL OPEN FONT LICENSE Version 1.1', license_file.read_text())
        # fonts.css loads exactly the recorded files, by relative URL, each face with font-display: swap.
        fonts_css = without_comments(FONTS_CSS.read_text())
        faces = re.findall(r'@font-face\s*\{(.*?)\}', fonts_css, re.S)
        self.assertEqual(len(records), len(faces))
        used = []
        for face in faces:
            self.assertRegex(face, r'font-display\s*:\s*swap\s*;')
            urls = [m.group(2) for m in CSS_URL.finditer(face)]
            self.assertEqual(1, len(urls), face)
            self.assertTrue(urls[0].startswith('../fonts/'), urls[0])
            used.append(str((FONTS_CSS.parent / urls[0]).resolve().relative_to(ROOT)))
        self.assertEqual(sorted(records), sorted(used))

    def test_no_resource_request_leaves_the_game(self):
        found = []
        scanned = []
        for base in ('assets', 'templates'):
            for path in sorted((ROOT / base).rglob('*')):
                if path.is_file() and path.suffix.lower() in SCANNED:
                    scanned.append(str(path.relative_to(ROOT)))
                    found += external_requests(str(path.relative_to(ROOT)), path.read_text(errors='replace'))
        self.assertGreater(len(scanned), 30)
        # The Resurrection UI's own files (Phase 5 task 3) are among them.
        for name in ('templates/resurrection.htm', 'assets/ui/resurrection.css', 'assets/ui/tokens.css', 'assets/ui/fonts.css'):
            self.assertIn(name, scanned)
        self.assertEqual([], found)

    def test_the_mockup_values(self):
        # The values Jim approved in the mockup, corrected in Phase 5 task 3 (docs/PHASE5-TASK3-TEMPLATE-20261030.md).
        # Changing one needs Jim; the spec and tokens.css must change together.
        expected = {
            '--radius-panel': '12px', '--radius-control': '8px', '--radius-badge': '5px', '--radius-bar': '999px',
            '--size-title': '34px', '--size-title-phone': '24px', '--tracking-title': '0.03em',
            '--size-body': '19px', '--leading-body': '1.65', '--size-body-phone': '17px', '--leading-body-phone': '1.6',
            '--key-badge-size': '24px', '--bar-height': '8px', '--bar-height-fight': '10px',
            '--size-ui': '16px', '--width-container': '1440px', '--space-side': '32px', '--space-side-phone': '16px',
            '--space-gutter': '24px', '--space-header-y': '14px', '--space-card-y': '32px', '--space-card-x': '36px',
            '--space-card-phone': '20px', '--space-panel': '20px', '--size-wordmark': '22px',
            '--tracking-wordmark': '0.06em', '--size-wordmark-sub': '13px', '--tracking-wordmark-sub': '0.2em',
            '--tracking-nav-heading': '0.16em', '--size-label': '13px',
        }
        spec = {name: value for name, (value, _) in spec_tokens(self.spec).items()}
        for name, value in expected.items():
            self.assertEqual(value, self.properties.get(name), name)
            self.assertEqual(value, spec.get(name), name)
        # Controls never sit on a --raised fill: the spec says so, and the outline clears 3:1 only on darker grounds.
        self.assertIn('Inputs and outlined controls never sit on a `--raised` fill', self.spec)
        for ground in ('--panel', '--header', '--ground'):
            self.assertGreaterEqual(contrast(self.properties['--input-border'], self.properties[ground]), UI_MINIMUM, ground)
        self.assertLess(contrast(self.properties['--input-border'], self.properties['--raised']), UI_MINIMUM)

    def test_the_quieter_palette(self):
        # Jim's approved values (2026-10-30, Phase 5 task 4): same codes and meanings, lower saturation, toward parchment.
        approved = {'1': '#8FA3C4', '!': '#A9B8D4', '2': '#86A877', '@': '#9CCB8A', '3': '#7FAAA3', '#': '#A3C4BE',
                    '4': '#D9775E', '$': '#E8826A', '5': '#B49AB8', '%': '#CDB0C2', '6': '#C9B47A', '^': '#E3CF9A',
                    '7': '#C9C0AE', '&': '#F4EEE2', ')': '#9A917F', 'q': '#C4936A', 'Q': '#DDA878'}
        codes = {code: (name, value) for code, name, value in spec_codes(self.spec)}
        self.assertEqual(41, len(codes))
        for code, value in approved.items():
            self.assertEqual(value, codes[code][1], code)
            self.assertEqual(value, self.properties[f'--code-{codes[code][0]}'], code)
        # The other 24 codes, retuned by the same rule: no more saturated than task 2's values, and each still distinct
        # from every other code (CIE76 distance 5 or more; only Jim's 4 and $ pair sits closer, at 4.66).
        task2 = {'~': '#958C7E', 'e': '#B98680', 'E': '#D4A39C', 'g': '#B5E3A6', 'j': '#D6D0C4', 'J': '#8FA6E0',
                 'k': '#8CE0C6', 'K': '#9DBF9A', 'l': '#6FA3D6', 'L': '#7FBCF0', 'm': '#EAD8B5', 'M': '#CDB08C',
                 'p': '#EBA486', 'P': '#E58C7C', 'R': '#E6BDE0', 'T': '#A88B6C', 't': '#E9CF8C', 'V': '#A98BE6',
                 'v': '#B3C1EA', 'x': '#D9B88F', 'X': '#EFE8D0', 'y': '#E6D99A', 'Y': '#BDB474'}
        self.assertEqual(set(codes) - set(approved) - {'G'}, set(task2))
        for code, before in task2.items():
            self.assertLess(saturation(codes[code][1]), saturation(before), code)
            self.assertNotEqual(before, codes[code][1], code)
        values = {code: value for code, (_, value) in codes.items() if code != 'G'}
        close = [(a, b, round(distance(values[a], values[b]), 2)) for a in values for b in values
                 if a < b and {a, b} != {'4', '$'} and distance(values[a], values[b]) < 5]
        self.assertEqual([], close)

    def test_character_panel_values_are_text_not_gold(self):
        # Jim, 2026-10-30 (task 4): values in --text, bold, by default; getcharstats() wraps each in `^ (colLtYellow),
        # so the template's CSS, scoped to the character panel, reads that wrapper as --text. No PHP change.
        css = without_comments((ROOT / 'assets' / 'ui' / 'resurrection.css').read_text())
        rule = re.search(r'\.rz-statvalue \.colLtYellow, \.rz-statbuff \.colLtYellow \{\s*color: var\(--text\);\s*\}', css)
        self.assertIsNotNone(rule, 'The character panel rule is missing')
        self.assertEqual(2, css.count('.colLtYellow'), 'colLtYellow is restyled only by that rule, inside the character panel')
        self.assertRegex(css, r'\.rz-statvalue \{[^}]*font-weight: var\(--weight-ui-bold\);')
        source = (ROOT / 'lib' / 'pageparts.php').read_text()
        self.assertIn('"value"=>"`^$val`0"', source)
        # The bonus (`@) and penalty (`$) codes charstats() uses stay distinct from the value color and each other.
        self.assertIn('`@+', source)
        self.assertIn('`\\$', source)
        bonus, penalty, text = self.properties['--code-colLtGreen'], self.properties['--code-colLtRed'], self.properties['--text']
        for a, b in ((bonus, text), (penalty, text), (bonus, penalty)):
            self.assertGreater(distance(a, b), 20, (a, b))


def saturation(color):
    channels = [int(color[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    return colorsys.rgb_to_hls(*channels)[2]


def lab(color):
    linear = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4
              for c in (int(color[i:i + 2], 16) / 255 for i in (1, 3, 5))]
    x = (0.4124 * linear[0] + 0.3576 * linear[1] + 0.1805 * linear[2]) / 0.95047
    y = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]
    z = (0.0193 * linear[0] + 0.1192 * linear[1] + 0.9505 * linear[2]) / 1.08883
    f = [t ** (1 / 3) if t > 0.008856 else 7.787 * t + 16 / 116 for t in (x, y, z)]
    return 116 * f[1] - 16, 500 * (f[0] - f[1]), 200 * (f[1] - f[2])


def distance(first, second):
    """CIE76 colour difference."""
    return math.dist(lab(first), lab(second))


class RequestScannerTests(unittest.TestCase):
    """The scanner itself: what counts as a request that leaves the game, and what does not."""

    def test_requests_that_leave_the_game_are_found(self):
        for text in ["<img src='http://example.com/a.png'>", '<script src="https://cdn.example/x.js"></script>',
                     "<link rel='stylesheet' href='//fonts.example/css'>", 'a { background: url(https://x.example/b.png) }',
                     "@import url('http://x.example/a.css');", '@import "https://x.example/a.css";',
                     "<td background='http://x.example/bg.gif'>", '<img srcset="https://x.example/a.png 2x">']:
            self.assertEqual(1, len(external_requests('fixture', text)), text)

    def test_local_resources_and_plain_links_are_allowed(self):
        for text in ["<a href='http://hfs.cjb.net/' target='_blank'>HFS</a>", "<img src='templates/jade/logo.jpg'>",
                     "src: url('../fonts/cinzel/cinzel-latin-500-normal.woff2')", 'url(data:image/png;base64,AAAA)',
                     "<link href='templates/jade.css' rel='stylesheet'>"]:
            self.assertEqual([], external_requests('fixture', text), text)


if __name__ == '__main__':
    unittest.main()
