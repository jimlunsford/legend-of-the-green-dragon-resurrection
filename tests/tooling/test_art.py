"""The game's original art in assets/ (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md).

The art comes from the command chat with its SHA-256 checksums and is committed byte for byte: every file under
assets/scenes/ and assets/portraits/ must be one of the files below, unchanged. Every SVG under assets/ must stay a
plain drawing (no script, no event attribute, no foreignObject, no href or other external reference), use only the art
palette in docs/UI-DESIGN-SPEC.md, and stay under 16 KB. assets/LICENSE licenses exactly three folders under
CC BY-SA 4.0.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too).
"""
import hashlib
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / 'assets'
SPEC = ROOT / 'docs' / 'UI-DESIGN-SPEC.md'
SIZE_LIMIT = 16 * 1024

# Batch 1, as the command chat supplied it (Jim, 2026-10-30). A change to the art is a new file from the command chat,
# added here with its checksum.
CHECKSUMS = {
    'portraits/race/dwarf.svg': '93373dcde21447afd1780e1bb999e6c88d729bf24535f205f354e63a759568d3',
    'portraits/race/elf.svg': 'c19dcaaf65be923458b48bc12329bef80ba12f26d013237f22e584c4ddeb8927',
    'portraits/race/human.svg': 'd619bfc4791001d00cf844e28ae493043a589b8912f1dd5f5c41bf67c6337740',
    'portraits/race/troll.svg': '873766832593ceab7c59f5c4fa62f798c3136f5241f57577a7ad91245000591e',
    'portraits/race/unknown.svg': '6814704b837cc59c26b20fd80a3e30e81077ee93202b68b15661142d6416a7b8',
    'scenes/fallback.svg': '9869c11fa1588d16db2e5c05774b824e3402409e1f820fa4a891d91a2a6541d5',
    'scenes/forest.svg': '26c9d2b6e12e37030d18de04cf0ff6fb2ecf16dda54df51353c6ca34739389e3',
    'scenes/village.svg': '5113b4c2d7256e9466f72742d1bd41630757f5904b3dd653eb9db1073eda2e06',
}
SVG_NAMESPACE = 'http://www.w3.org/2000/svg'
# Attributes that carry a color; their values must be a palette color or none.
COLOR_ATTRIBUTE = re.compile(r'''(?<![\w-])(fill|stroke|stop-color|flood-color|lighting-color|color)\s*=\s*(["'])(.*?)\2''', re.I | re.S)
COLOR_PROPERTY = re.compile(r'''(?<![\w-])(fill|stroke|stop-color|flood-color|lighting-color|color)\s*:\s*([^;"'>]+)''', re.I)


def art_palette(spec):
    """The art palette table of the spec: the colors, uppercase."""
    section = re.search(r'^### Art palette.*?(?=^#{2,3} )', spec, re.M | re.S)
    if section is None:
        raise AssertionError('The spec has no "Art palette" section')
    return [c.upper() for c in re.findall(r'^\| `(#[0-9A-Fa-f]{6})` \|', section.group(0), re.M)]


def svg_problems(name, text):
    """Everything in an SVG that is not a plain drawing: [(file, problem)]."""
    found = []
    checks = [
        (r'<\s*script\b', 'a script element'),
        (r'<\s*foreignObject\b', 'a foreignObject element'),
        (r'''\son[a-z]+\s*=''', 'an event attribute'),
        (r'''\b(?:xlink:)?href\s*=''', 'an href'),
        (r'<\s*(?:image|use|style|iframe|object|embed|a|feImage)\b', 'an element that can load or link'),
        (r'<!(?:DOCTYPE|ENTITY)', 'a DOCTYPE or entity'),
        (r'<\?xml-stylesheet', 'a stylesheet instruction'),
        (r'@import', 'an @import'),
        (r'javascript:', 'a javascript: URL'),
        (r'''url\(\s*['"]?(?!#)''', 'a url() that is not a local fragment'),
    ]
    for pattern, problem in checks:
        if re.search(pattern, text, re.I):
            found.append((name, problem))
    # The one URL allowed is the SVG namespace itself.
    rest = text.replace(f'xmlns="{SVG_NAMESPACE}"', '')
    if re.search(r'(?i)[a-z][a-z0-9+.-]*://|//[a-z0-9]', rest):
        found.append((name, 'an external reference'))
    if 'xmlns:' in rest:
        found.append((name, 'another namespace'))
    return found


def colors(text):
    """Every color value an SVG uses: [(attribute, value)]."""
    used = [(attr.lower(), value.strip()) for attr, _, value in COLOR_ATTRIBUTE.findall(text)]
    used += [(prop.lower(), value.strip()) for prop, value in COLOR_PROPERTY.findall(text)]
    return used


class ArtTests(unittest.TestCase):

    def setUp(self):
        self.svgs = sorted(p for p in ASSETS.rglob('*') if p.is_file() and p.suffix.lower() == '.svg')

    def test_every_file_is_the_one_supplied(self):
        on_disk = sorted(str(p.relative_to(ASSETS)) for folder in ('scenes', 'portraits')
                         for p in (ASSETS / folder).rglob('*') if p.is_file())
        self.assertEqual(sorted(CHECKSUMS), on_disk, 'Every file under assets/scenes and assets/portraits is listed')
        for name, expected in CHECKSUMS.items():
            self.assertEqual(expected, hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), name)
        # The README's table is the same list.
        readme = (ASSETS / 'README.md').read_text()
        self.assertEqual(CHECKSUMS, dict(re.findall(r'^\| `([a-z/]+\.svg)` \| `([0-9a-f]{64})` \|', readme, re.M)))

    def test_every_svg_is_a_plain_drawing(self):
        self.assertGreaterEqual(len(self.svgs), len(CHECKSUMS))
        found = []
        for path in self.svgs:
            text = path.read_text(encoding='utf-8')
            name = str(path.relative_to(ROOT))
            found += svg_problems(name, text)
            self.assertTrue(text.lstrip().startswith(f'<svg xmlns="{SVG_NAMESPACE}"'), name)
            self.assertRegex(text, r'<title>[^<]+</title>', name)
            self.assertLess(path.stat().st_size, SIZE_LIMIT, name)
        self.assertEqual([], found)

    def test_every_color_is_in_the_art_palette(self):
        palette = art_palette(SPEC.read_text())
        self.assertGreaterEqual(len(palette), 10)
        self.assertEqual(len(palette), len(set(palette)), 'The art palette lists a color twice')
        outside = []
        used = set()
        for path in self.svgs:
            for attribute, value in colors(path.read_text(encoding='utf-8')):
                if value.lower() == 'none':
                    continue
                if not re.fullmatch(r'#[0-9A-Fa-f]{6}', value) or value.upper() not in palette:
                    outside.append((str(path.relative_to(ROOT)), attribute, value))
                else:
                    used.add(value.upper())
        self.assertEqual([], outside)
        # The palette is the colors the art uses: none listed that no file uses.
        self.assertEqual(sorted(palette), sorted(used))

    def test_the_checks_find_what_they_must(self):
        bad = {
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>': 'a script element',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="x()"></svg>': 'an event attribute',
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject/></svg>': 'a foreignObject element',
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="#x"/></svg>': 'an href',
            '<svg xmlns="http://www.w3.org/2000/svg"><image xlink:href="x.png"/></svg>': 'an href',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://x.test/p.svg#g)"/></svg>': 'a url() that is not a local fragment',
            '<svg xmlns="http://www.w3.org/2000/svg"><style>@import "x.css";</style></svg>': 'an @import',
            '<svg xmlns="http://www.w3.org/2000/svg"><desc>see https://x.test</desc></svg>': 'an external reference',
        }
        for text, problem in bad.items():
            self.assertIn(('t.svg', problem), svg_problems('t.svg', text), text)
        self.assertEqual([], svg_problems('t.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(#g)"/></svg>'))
        self.assertEqual([('fill', 'red'), ('stroke', '#ABCDEF'), ('stop-color', 'blue'), ('fill', 'rgb(1,2,3)')],
                         colors('<rect fill="red" stroke="#ABCDEF" stop-color="blue" style="fill: rgb(1,2,3)"/>'))

    def test_the_license_names_exactly_its_three_folders(self):
        license_text = (ASSETS / 'LICENSE').read_text(encoding='utf-8')
        self.assertIn('Copyright (c) 2026 Phoenix 2:33 LLC', license_text)
        self.assertIn('Creative Commons Attribution-ShareAlike 4.0 International', license_text)
        self.assertIn('Art by Phoenix 2:33 LLC, CC BY-SA 4.0', license_text)
        # The scope: the folders listed as covered, before "nothing else", are exactly these three.
        scope, _, excluded = license_text.partition('nothing else')
        self.assertEqual(['assets/icons/', 'assets/portraits/', 'assets/scenes/'],
                         sorted(set(re.findall(r'assets/[a-z]+/', scope))))
        self.assertEqual(['assets/fonts/', 'assets/ui/'], sorted(set(re.findall(r'^\s+(assets/[a-z]+/)', excluded, re.M))))
        # The license's own text follows, from section 1 to the end.
        self.assertIn('Section 1 – Definitions.', license_text)
        self.assertIn('Section 8 – Interpretation.', license_text)
        for name in ('NOTICE', 'assets/README.md'):
            text = re.sub(r'\s+', ' ', (ROOT / name).read_text())
            self.assertIn('Art by Phoenix 2:33 LLC, CC BY-SA 4.0', text, name)
            for folder in ('scenes/', 'portraits/', 'icons/'):
                self.assertIn(folder, text, name)


if __name__ == '__main__':
    unittest.main()
