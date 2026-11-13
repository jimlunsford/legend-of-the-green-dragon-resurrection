"""The game's icons, the dragon eye (Phase 6 task 17, R5; docs/PHASE6-TASK17-20261113.md).

scripts/art/build_favicon.py draws assets/brand/resurrection-eye.svg with Chromium and writes favicon.ico (16, 32 and
48 px), favicon.png (32 px), favicon.svg (a copy of the source) and apple-touch-icon.png (180 px) into the game folder.
These tests check the four committed files: their sizes, that the ICO holds the three images and favicon.png is its 32 px
one, that favicon.svg is the source byte for byte and the source is a plain drawing, that the script's own encoder gives
the committed bytes again from their pixels, and that every frame's head gets the three icon links. The full rebuild,
Chromium included, compares a fresh build with the committed files; it runs where the recorded Chromium and Playwright
are installed and is skipped elsewhere (CI has no browser), and says so. Plain Python: no PHP, database or network.
"""
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import shutil
import struct
import subprocess
import tempfile
import unittest

import test_art

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'art' / 'build_favicon.py'
SOURCE = ROOT / 'assets' / 'brand' / 'resurrection-eye.svg'
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

    def test_a_rebuild_gives_the_committed_bytes(self):
        """Chromium draws the source again; every file equals the committed one. Needs the recorded rasterizer."""
        if shutil.which('node') is None:
            self.skipTest('No Node here: the rebuild needs Chromium through Playwright for Node (not in CI).')
        probe = subprocess.run(['node', '-e', "const {chromium}=require('playwright');"
                                "chromium.launch().then(async b=>{console.log(JSON.stringify([b.version(),"
                                "require('playwright/package.json').version]));await b.close();})"
                                ".catch(()=>process.exit(3));"],
                               capture_output=True, text=True, timeout=120, env=self.build.node_environment())
        if probe.returncode != 0:
            self.skipTest('Playwright or its Chromium is not installed here (not in CI).')
        found = json.loads(probe.stdout)
        if found != [self.build.CHROMIUM, self.build.PLAYWRIGHT]:
            self.skipTest(f'The rebuild is recorded for Chromium {self.build.CHROMIUM} with Playwright '
                          f'{self.build.PLAYWRIGHT}; this machine has {found[0]} with {found[1]}.')
        with tempfile.TemporaryDirectory() as folder:
            files, versions = self.build.build(folder)
            self.assertEqual(versions, [self.build.CHROMIUM, self.build.PLAYWRIGHT])
            for name in self.build.OUTPUTS:
                self.assertEqual((Path(folder) / name).read_bytes(), (ROOT / name).read_bytes(), name)


if __name__ == '__main__':
    unittest.main()
