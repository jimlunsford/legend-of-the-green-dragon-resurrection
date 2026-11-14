"""The game's icons, the dragon eye (Phase 6 task 17, R5; docs/PHASE6-TASK17-20261113.md).

scripts/art/build_favicon.py draws assets/brand/resurrection-eye.svg with Chromium and writes favicon.ico (16, 32 and
48 px), favicon.png (32 px), favicon.svg (a copy of the source) and apple-touch-icon.png (180 px) into the game folder,
and records each file's name, size in bytes, pixel dimensions and SHA-256 in scripts/art/favicon-manifest.json.
These tests check the four committed files: that they match the manifest exactly and the manifest is the script's own
record of them, their sizes, that the ICO holds the three images and favicon.png is its 32 px one, that favicon.svg is
the source byte for byte and the source is a plain drawing (no script, no external reference), that the script's own
encoder gives the committed bytes again from their pixels, that its redraw check compares every byte and never passes
without the rasterizer, and that every frame's head gets the three icon links. Plain Python: no PHP, database, network
or browser, so every test runs in CI. The redraw through Chromium is not a test: it needs the recorded rasterizer, which
CI does not have, so it is the local check `python3 scripts/art/build_favicon.py --check` (task 17's CI fix).
"""
import hashlib
import importlib.util
import json
from pathlib import Path
import os
import re
import shutil
import struct
import subprocess
import sys
import tempfile
import unittest

import test_art

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'art' / 'build_favicon.py'
SOURCE = ROOT / 'assets' / 'brand' / 'resurrection-eye.svg'
MANIFEST = ROOT / 'scripts' / 'art' / 'favicon-manifest.json'
# The SVG Jim chose on 2026-11-13 (option A), as given in the task 17 brief.
SOURCE_SHA256 = '22ba6aa87ec9f198499f48399d47f14105f47333516731aeca2879748111b7b6'
ICON_LINKS = ('<link rel="icon" href="favicon.ico" sizes="any">',
              '<link rel="icon" href="favicon.svg" type="image/svg+xml">',
              '<link rel="apple-touch-icon" href="apple-touch-icon.png">')


def load():
    spec = importlib.util.spec_from_file_location('build_favicon', SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def png_size(data):
    """(width, height, bit depth, color type) from a PNG's IHDR."""
    if data[:8] != b'\x89PNG\r\n\x1a\n' or data[12:16] != b'IHDR':
        raise AssertionError('Not a PNG with IHDR first')
    width, height, depth, color = struct.unpack('>IIBB', data[16:26])
    return width, height, depth, color


def ico_images(data):
    """[(directory width, directory height, image bytes)] from an ICO."""
    reserved, kind, count = struct.unpack('<HHH', data[:6])
    if (reserved, kind) != (0, 1):
        raise AssertionError('Not an icon file')
    images = []
    for index in range(count):
        width, height, _, _, _, _, length, offset = struct.unpack('<BBBBHHII', data[6 + 16 * index:22 + 16 * index])
        images.append((width or 256, height or 256, data[offset:offset + length]))
    return images


class FaviconTests(unittest.TestCase):

    def setUp(self):
        self.build = load()

    def test_the_four_files_exist_with_their_sizes(self):
        for name in self.build.OUTPUTS:
            self.assertTrue((ROOT / name).is_file(), name)
        self.assertEqual(png_size((ROOT / 'favicon.png').read_bytes()), (32, 32, 8, 6))
        self.assertEqual(png_size((ROOT / 'apple-touch-icon.png').read_bytes()), (180, 180, 8, 6))

    def test_the_ico_holds_16_32_and_48_and_favicon_png_is_its_32(self):
        images = ico_images((ROOT / 'favicon.ico').read_bytes())
        self.assertEqual([(w, h) for w, h, _ in images], [(16, 16), (32, 32), (48, 48)])
        for width, height, image in images:
            self.assertEqual(png_size(image), (width, height, 8, 6))
        self.assertEqual(images[1][2], (ROOT / 'favicon.png').read_bytes())

    def test_favicon_svg_is_the_source_and_a_plain_drawing(self):
        source = SOURCE.read_bytes()
        self.assertEqual(hashlib.sha256(source).hexdigest(), SOURCE_SHA256)
        self.assertEqual((ROOT / 'favicon.svg').read_bytes(), source)
        self.assertEqual(test_art.svg_problems('favicon.svg', source.decode('utf-8')), [])

    def test_the_encoder_gives_the_committed_bytes_again(self):
        """The script's PNG and ICO writer, fed the committed images' pixels, writes the committed files byte for byte."""
        pngs = {}
        for name, size in (('favicon.png', 32), ('apple-touch-icon.png', 180)):
            data = (ROOT / name).read_bytes()
            width, height, rows = self.build.read_png(data)
            self.assertEqual((width, height), (size, size), name)
            self.assertEqual(self.build.write_png(rows), data, name)
        for width, _, image in ico_images((ROOT / 'favicon.ico').read_bytes()):
            pngs[width] = self.build.write_png(self.build.read_png(image)[2])
            self.assertEqual(pngs[width], image, f'favicon.ico, {width} px')
        self.assertEqual(self.build.write_ico(pngs), (ROOT / 'favicon.ico').read_bytes())

    def test_every_frame_head_gets_the_three_links(self):
        output = (ROOT / 'lib' / 'output.php').read_text()
        helper = re.search(r'function resurrection_icon_links\(\)\{(.*?)\n\}', output, re.S)
        self.assertIsNotNone(helper)
        for link in ICON_LINKS:
            self.assertIn(link.replace('"', '\\"'), helper.group(1))
        pageparts = (ROOT / 'lib' / 'pageparts.php').read_text()
        # page_footer (pages, the safe frame, the operator frame, setup.php) and popup_header fill {headscript}.
        fills = re.findall(r'str_replace\(\s*"\{headscript\}"\s*,\s*([^,]+),', pageparts)
        self.assertEqual(len(fills), 2, fills)
        for fill in fills:
            self.assertTrue(fill.strip().startswith('resurrection_icon_links()'), fill)
        self.assertNotRegex(pageparts, r'(?i)<link\b[^>]*shortcut icon')

    def test_the_committed_files_match_the_manifest(self):
        """Each icon file's name, size in bytes, pixel dimensions and SHA-256 equal the recorded ones, exactly."""
        record = json.loads(MANIFEST.read_text())
        self.assertEqual(sorted(record), ['files', 'rasterizer', 'source'])
        self.assertEqual(record['source'], {'name': 'assets/brand/resurrection-eye.svg', 'sha256': SOURCE_SHA256})
        self.assertEqual(record['rasterizer'], {'chromium': self.build.CHROMIUM, 'playwright': self.build.PLAYWRIGHT})
        self.assertEqual([entry['name'] for entry in record['files']], list(self.build.OUTPUTS))
        expected = {'favicon.ico': [[16, 16], [32, 32], [48, 48]], 'favicon.png': [[32, 32]], 'favicon.svg': [[64, 64]],
                    'apple-touch-icon.png': [[180, 180]]}
        for entry in record['files']:
            name = entry['name']
            self.assertEqual(sorted(entry), ['bytes', 'dimensions', 'name', 'sha256'], name)
            data = (ROOT / name).read_bytes()
            self.assertEqual(len(data), entry['bytes'], name)
            self.assertEqual(hashlib.sha256(data).hexdigest(), entry['sha256'], name)
            self.assertEqual(self.build.dimensions(name, data), entry['dimensions'], name)
            self.assertEqual(entry['dimensions'], expected[name], name)
        # The pixel dimensions read from the files themselves, not only the script's reader.
        self.assertEqual([[w, h] for w, h, _ in ico_images((ROOT / 'favicon.ico').read_bytes())], expected['favicon.ico'])
        for name in ('favicon.png', 'apple-touch-icon.png'):
            self.assertEqual([list(png_size((ROOT / name).read_bytes())[:2])], expected[name], name)

    def test_the_manifest_is_the_scripts_own_record_of_the_committed_files(self):
        """The script, given the committed files, writes the committed manifest byte for byte, so --check can confirm it."""
        files = {name: (ROOT / name).read_bytes() for name in self.build.OUTPUTS}
        self.assertEqual(self.build.manifest(files, [self.build.CHROMIUM, self.build.PLAYWRIGHT]), MANIFEST.read_text())
        self.assertEqual(self.build.MANIFEST, MANIFEST)

    def test_the_svg_check_finds_a_script_and_an_external_reference(self):
        """The plain-drawing check is not empty: a script or an external reference added to the icon is caught."""
        source = SOURCE.read_text()
        self.assertNotRegex(source, r'(?i)<\s*script\b')
        self.assertNotRegex(source, r'(?i)\bhref\s*=|url\(|[a-z][a-z0-9+.-]*://(?!www\.w3\.org/2000/svg")')
        planted = {
            'script': source.replace('</svg>', '<script>alert(1)</script></svg>'),
            'external image': source.replace('</svg>', '<image href="https://example.invalid/x.png"/></svg>'),
            'external url()': source.replace('fill="#D9A84E"', 'fill="url(https://example.invalid/p#a)"'),
        }
        for label, text in planted.items():
            self.assertNotEqual(text, source, label)
            self.assertNotEqual(test_art.svg_problems('favicon.svg', text), [], label)

    def test_the_redraw_check_compares_every_byte(self):
        """--check's comparison reports any one changed byte in any file or the manifest, and a missing file."""
        committed = self.build.committed_paths()
        self.assertEqual(sorted(committed), sorted([*self.build.OUTPUTS, MANIFEST.name]))
        with tempfile.TemporaryDirectory() as folder:
            for name, path in committed.items():
                shutil.copyfile(path, Path(folder) / name)
            self.assertEqual(self.build.differences(folder, committed), [])
            for name in committed:
                target = Path(folder) / name
                original = target.read_bytes()
                changed = bytearray(original)
                changed[len(changed) // 2] ^= 1
                target.write_bytes(bytes(changed))
                found = self.build.differences(folder, committed)
                self.assertEqual([entry[0] for entry in found], [name], name)
                self.assertIn(f'first difference at byte {len(changed) // 2}', found[0][1], name)
                target.write_bytes(original)
            missing = dict(committed, **{'favicon.png': Path(folder) / 'no-such-file.png'})
            self.assertEqual(self.build.differences(folder, missing), [('favicon.png', 'the committed file is missing')])

    def test_the_redraw_check_never_passes_without_the_rasterizer(self):
        """With no browser to draw with, --check exits 2 and says so; it writes nothing in the tree."""
        before = {path: path.read_bytes() for path in self.build.committed_paths().values()}
        with tempfile.TemporaryDirectory() as empty:
            environment = dict(os.environ, PLAYWRIGHT_BROWSERS_PATH=empty)
            result = subprocess.run([sys.executable, str(SCRIPT), '--check'], capture_output=True, text=True,
                                    timeout=180, env=environment)
        self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
        self.assertIn('build_favicon --check: cannot redraw', result.stderr)
        self.assertNotIn('OK', result.stdout)
        self.assertEqual({path: path.read_bytes() for path in before}, before)


if __name__ == '__main__':
    unittest.main()
