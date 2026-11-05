"""The game's original art in assets/ (Phase 5 task 5, docs/PHASE5-TASK5-IMAGES-20261030.md).

The art comes from the command chat with its SHA-256 checksums and is committed byte for byte: every file under
assets/scenes/, assets/portraits/ and assets/icons/ must be one of the files below, unchanged (batch 4's portraits:
the ones assets/portraits/SHA256SUMS lists, Phase 5 task 14). Every SVG under assets/ must stay a
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

# Batch 1, as the command chat supplied it (Jim, 2026-10-30), then batches 2 and 3 (batch 4 in PORTRAIT_KIT, below). A change to the art is a new file from the
# command chat, added here with its checksum.
CHECKSUMS = {
    'portraits/race/dwarf.svg': '93373dcde21447afd1780e1bb999e6c88d729bf24535f205f354e63a759568d3',
    'portraits/race/elf.svg': 'c19dcaaf65be923458b48bc12329bef80ba12f26d013237f22e584c4ddeb8927',
    'portraits/race/human.svg': 'd619bfc4791001d00cf844e28ae493043a589b8912f1dd5f5c41bf67c6337740',
    'portraits/race/troll.svg': '873766832593ceab7c59f5c4fa62f798c3136f5241f57577a7ad91245000591e',
    'portraits/race/unknown.svg': '6814704b837cc59c26b20fd80a3e30e81077ee93202b68b15661142d6416a7b8',
    'scenes/fallback.svg': '9869c11fa1588d16db2e5c05774b824e3402409e1f820fa4a891d91a2a6541d5',
    'scenes/forest.svg': '26c9d2b6e12e37030d18de04cf0ff6fb2ecf16dda54df51353c6ca34739389e3',
    'scenes/village.svg': '5113b4c2d7256e9466f72742d1bd41630757f5904b3dd653eb9db1073eda2e06',
    # Batch 2, the location scenes (Jim, approved 2026-11-01; Phase 5 task 12, docs/PHASE5-TASK12-SCENES-20261105.md).
    'scenes/armor.svg': '115ead5d1ababe762134c2cb6c653b54a00c7e287f628d8b6209897ef7d96f38',
    'scenes/bank.svg': '256923ca0f8cc396c610b6eb825d9a1e2d4be8caaff8bfc7d925afef2573e9d6',
    'scenes/clan.svg': 'b2368ffeb7e15003d7d649094e30ed67bbfc0593419313ac399c8252c890b0ef',
    'scenes/dragon.svg': '5d71224de2d40dcd9bf74a262832906e4aaab992391ce11f503ccb7dce5a2770',
    'scenes/fields.svg': 'fe0528561d41f815efb3caa61db3aae4acd2e6ef896ccf1cfee2e45f3313987e',
    'scenes/gardens.svg': '0c854fbac31e7b3890e205f635b8acd5fe32d4c75f4dc6a3fbf1d127e014a5f1',
    'scenes/graveyard.svg': '2fc1c01c44c5c28b8b20d69bdd6babc9e5f0fceec0db357484b7195d08f08914',
    'scenes/gypsy.svg': '9316f3b8e37847acb090fddde8cf7cd15b8f06fbd5a5c90722be0500d84dec25',
    'scenes/healer.svg': '53b7fa5bd9b6ec97103880443c553bf682f0f12052d69c9c49b3f19624aafc26',
    'scenes/inn.svg': '326d401485cad52a2962f9f3c53baa39cf7f66577242a2e3d766535eea3e665d',
    'scenes/lodge.svg': '8ad19edc6e51efd9e9516e2d2cb8a9f9d2aaf1b27d001e1492a6baa44ab8e29b',
    'scenes/mercenarycamp.svg': '2ec7e4d4b7b95b67afb02cf4717815f434c5f3e9e3da59faa614547cc0980a3b',
    'scenes/rock.svg': '57e8a5c154725409741de15373a9f4a0972b3a4319d97182aee4bfd85729e280',
    'scenes/shades.svg': 'aeec36eeeb740d4f6cb2c22fd3df46806710926024f0e1670f548926c54ebcdc',
    'scenes/stables.svg': 'e316875b62d063d0ea7b708a7a38e82aa8bc29a6bb3c43b9d684bf6be8bbe48e',
    'scenes/train.svg': '78c2371f80af364d3e1ed01d6edfa0d9c51841fa6e8c28734e98885524b82133',
    'scenes/weapons.svg': '97417d9d10db40dea471030074a08e6d60b719e44c96b32d00129d040fb1d1cf',
    # Batch 3, the character panel's icons (Jim, approved 2026-11-05; Phase 5 task 13, docs/PHASE5-TASK13-ICONS-20261105.md).
    'icons/armor.svg': 'c185a72bb8fb4268a65e700dcc74affdf85c0fbfa562c6805bc1300a5bb4c808',
    'icons/attack.svg': '143c5b73a3d993ef7741f152ac1a67eb410a85e85fee554bfff78f9ceb058c4d',
    'icons/buffs.svg': '20bbc7c6ae06ae8ae6520ac1008880dbcc45ac43fed4059816beb6d8e0d1c1b1',
    'icons/companions.svg': '415177aa071d92f674801a3f035b48981691f0fea74c24b2ae404048d7522c04',
    'icons/defense.svg': '4b63a78b89728b2918dc97f00cc41d28c441472380229395558537b1e7a8f381',
    'icons/experience.svg': '3630c79f29eba85261575ca4dad73f5174ab9638b106cae9d9e746cfe18a60a6',
    'icons/favor.svg': '80f70941091987e73f3f03db092103ea0b6947a616c8dd3be2587038a0dd250a',
    'icons/gems.svg': '7176f2f7a69577bb7c4e63e173950490c3e3301456d33c66f684b735b230944d',
    'icons/gold.svg': '70969655290bf4b09913e31b54de49787531be7587ff2ed7ee482fc6e505298b',
    'icons/hitpoints.svg': 'da8e73153f8905e2be37c9529f2544a920eb72b8c51e0c7b0d84440ab67eec87',
    'icons/mount.svg': '80c954cc51cc27baa3ccef0125852b4c9d677f4908fdac7d097ec7fa6922dd4a',
    'icons/psyche.svg': '1bbe230727083ea5fba32fd9544d1959c950ec1f1282c8fe4ce55bbf4b53a01e',
    'icons/soulpoints.svg': '05311d11d00dac8f7bbed7d2ae21e4018738f33c0f7208b67c2467158ca64d40',
    'icons/spirit.svg': '49a33aa3757f9093e8471bbc2171c4ac2bdbc2c258987ebe384b2d7dd1b55cf3',
    'icons/spirits.svg': 'f149f22778c999abf8c8d21b5c17123599de42cb2cfd7a47d1929f651c49140e',
    'icons/torments.svg': '5c987dd7745f9d34077adb2baaa01bc3f2d36daa72f859f8df27a7084b7e1653',
    'icons/turns.svg': '261ba7b4b15f476a47bb7f28b1f89900ae42328b456b40daa9cf7e4e0c83fc13',
    'icons/weapon.svg': 'c5535706a49bf59fd0be6f29f4d634c9d91ee918fb791adca490676f034eeadc',
}
# Batch 4, the foe portraits (Jim, approved 2026-11-05; Phase 5 task 14, docs/PHASE5-TASK14-PORTRAITS-20261105.md): 313
# SVGs drawn from the parts kit, too many to list here. Their checksums are assets/portraits/SHA256SUMS, itself pinned
# here with the kit; tests/tooling/test_portraits.py checks the files against it, the kit and the generator.
PORTRAIT_KIT = {
    'portraits/SHA256SUMS': '1662a4cfa1d7b8ef5218fe4cd991908a1138fedd448f850028b25a27ce714687',
    'portraits/creature/kit.json': 'a3234d759c889721ff96b1dda8d7877781993ef33ee9e96133ce1ddc52f32fc1',
}
SVG_NAMESPACE = 'http://www.w3.org/2000/svg'


def portrait_sums():
    """assets/portraits/SHA256SUMS as {path under assets/: checksum}."""
    sums = {}
    for line in (ASSETS / 'portraits' / 'SHA256SUMS').read_text().splitlines():
        found = re.fullmatch(r'([0-9a-f]{64})  \./(portraits/[a-z0-9/-]+\.svg)', line)
        if found is None:
            raise AssertionError('Not a SHA256SUMS line: ' + line)
        sums[found.group(2)] = found.group(1)
    return sums
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
        on_disk = sorted(str(p.relative_to(ASSETS)) for folder in ('scenes', 'portraits', 'icons')
                         for p in (ASSETS / folder).rglob('*') if p.is_file())
        # Batch 4's portraits are listed in assets/portraits/SHA256SUMS, which is pinned with the kit (PORTRAIT_KIT).
        for name, expected in PORTRAIT_KIT.items():
            self.assertEqual(expected, hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), name)
        sums = portrait_sums()
        self.assertEqual(313, len(sums))
        self.assertEqual([], sorted(set(sums) & set(CHECKSUMS)))
        self.assertEqual(sorted({**CHECKSUMS, **sums, **PORTRAIT_KIT}), on_disk,
                         'Every file under assets/scenes, assets/portraits and assets/icons is listed')
        self.assertEqual(18, sum(1 for name in CHECKSUMS if name.startswith('icons/')))
        for name, expected in {**CHECKSUMS, **sums}.items():
            self.assertEqual(expected, hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), name)
        # The README's table is the same list, with the kit and its checksums beside it.
        readme = (ASSETS / 'README.md').read_text()
        self.assertEqual(CHECKSUMS, dict(re.findall(r'^\| `([a-z/]+\.svg)` \| `([0-9a-f]{64})` \|', readme, re.M)))
        self.assertEqual(PORTRAIT_KIT, dict(re.findall(r'^\| `(portraits/(?:SHA256SUMS|creature/kit\.json))` \| `([0-9a-f]{64})` \|', readme, re.M)))

    def test_every_icon_the_code_names_exists(self):
        # Phase 5 task 13: the names in Art::ICONS, and every label the panel and the phone header pass to
        # resurrection_icon_html() as a literal, lowercased, are files in assets/icons/; every file there is named.
        art = (ROOT / 'src' / 'Ui' / 'Art.php').read_text()
        block = re.search(r'public const array ICONS = \[(.*?)\];', art, re.S)
        self.assertIsNotNone(block)
        names = re.findall(r"'([a-z]+)'", block.group(1))
        self.assertEqual(18, len(names))
        self.assertEqual(len(names), len(set(names)))
        files = sorted(p.stem for p in (ASSETS / 'icons').glob('*.svg'))
        self.assertEqual(sorted(names), files)
        pageparts = (ROOT / 'lib' / 'pageparts.php').read_text()
        literal = re.findall(r'resurrection_icon_html\("([^"$]+)"\)', pageparts)
        self.assertEqual(['Experience', 'Buffs'], sorted(set(literal), key=literal.index))
        # The labels the panel and the phone header build their rows from: each names an icon.
        labels = re.findall(r'array\("(?:Vital|Personal) Info", "([A-Za-z]+)"', pageparts)
        labels += re.findall(r'array\("(?:Weapon|Armor|Creature)", "([A-Za-z]+)"', pageparts)
        labels += re.findall(r'\$label = "([A-Za-z]+)";', pageparts)
        labels += re.findall(r'array\("(?:health|soul|experience)", "([A-Za-z]+)", translate_inline', pageparts)
        self.assertEqual(sorted(set(names) - {'buffs', 'companions'}), sorted({label.lower() for label in labels}))
        for label in literal + labels + ['Companions']:
            self.assertIn(label.lower(), names, label)
            self.assertTrue((ASSETS / 'icons' / f'{label.lower()}.svg').is_file(), label)
        # The template draws an icon only through its part, decorative, at the label size.
        template = (ROOT / 'templates' / 'resurrection.htm').read_text()
        self.assertIn('<!--!labelicon--><img class="rz-labelicon" src="{src}" alt="" aria-hidden="true" width="{width}" height="{height}">\n', template)
        self.assertNotIn('assets/icons/', template)

    def test_every_svg_is_a_plain_drawing(self):
        # Every SVG under assets/, batch 4's 313 portraits included (Phase 5 task 14).
        self.assertGreaterEqual(len(self.svgs), len(CHECKSUMS) + 313)
        self.assertTrue(set(portrait_sums()) <= {str(p.relative_to(ASSETS)) for p in self.svgs})
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
