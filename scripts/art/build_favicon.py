#!/usr/bin/env python3
"""Build the game's icons from the dragon eye (Phase 6 task 17, R5; Jim's choice of 2026-11-13, option A).

    python3 scripts/art/build_favicon.py [--out FOLDER]
    python3 scripts/art/build_favicon.py --check

From the source, assets/brand/resurrection-eye.svg, it writes into the game's folder (or FOLDER):

- favicon.ico: the eye at 16, 32 and 48 px, each a PNG inside the icon file;
- favicon.png: the eye at 32 px (the same bytes as the 32 px image in favicon.ico);
- favicon.svg: a byte-for-byte copy of the source;
- apple-touch-icon.png: the eye at 180 px;

and the manifest, scripts/art/favicon-manifest.json (or FOLDER/favicon-manifest.json): the source's SHA-256, the
rasterizer's versions, and each icon file's name, size in bytes, pixel dimensions and SHA-256. tests/tooling/
test_favicon.py checks the committed files against the manifest; it needs no browser, so it runs in CI.

--check is the redraw check, a local check and not a test, because it needs the recorded rasterizer and CI has none
(Phase 6 task 17's CI fix): it draws the source again into a temporary folder, writes nothing in the tree, and compares
every icon file and the manifest with the committed ones byte for byte. It prints one line per file and exits 0 when
all match, 1 on any difference, and 2 when it cannot draw (no Node, Playwright or Chromium, or another version).

The rasterizer is Chromium, driven by Playwright for Node, the browser the project's sessions already use for their
UI crawls and screenshots. This build used Chromium 153.0.8010.12 (Google Chrome for Testing, Playwright's
chromium-1243) with Playwright 1.63.0. Chromium draws the source at each size on a transparent page and hands back a
PNG; this script reads its pixels and writes every file itself: 8-bit RGBA, each row's PNG filter chosen by the same
fixed rule, zlib level 9, no text, time or color chunks, and the ICO directory in size order. So the same rasterizer
gives the same bytes, and a rebuild can be compared with the files in the tree. The script refuses another Chromium or
Playwright version unless --allow-other-rasterizer is given, so the committed icons always match the versions recorded
here. Nothing here runs in the game: the four files are static files the web server sends.

Art: Phoenix 2:33 LLC, CC BY-SA 4.0 (assets/LICENSE).
"""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import struct
import subprocess
import sys
import tempfile
import zlib

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'assets' / 'brand' / 'resurrection-eye.svg'
CHROMIUM = '153.0.8010.12'
PLAYWRIGHT = '1.63.0'
ICO_SIZES = (16, 32, 48)
PNG_SIZE = 32
TOUCH_SIZE = 180
OUTPUTS = ('favicon.ico', 'favicon.png', 'favicon.svg', 'apple-touch-icon.png')
MANIFEST = ROOT / 'scripts' / 'art' / 'favicon-manifest.json'

# Draws the source on a transparent page at each size and prints {"versions": [...], "images": {size: base64 PNG}}.
RASTERIZE = r"""
const { chromium } = require('playwright');
const input = JSON.parse(require('fs').readFileSync(0, 'utf8'));
(async () => {
  const browser = await chromium.launch();
  const images = {};
  for (const size of input.sizes) {
    const page = await browser.newPage({ viewport: { width: size, height: size }, deviceScaleFactor: 1 });
    await page.setContent('<!DOCTYPE html><html><head><style>html,body{margin:0;padding:0;background:transparent}img{display:block}</style></head>'
      + '<body><img width="' + size + '" height="' + size + '" src="data:image/svg+xml;base64,' + input.svg + '"></body></html>');
    await page.waitForFunction(() => document.images[0].complete && document.images[0].naturalWidth > 0);
    images[size] = (await page.screenshot({ omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } })).toString('base64');
    await page.close();
  }
  const versions = [browser.version(), require('playwright/package.json').version];
  await browser.close();
  process.stdout.write(JSON.stringify({ versions, images }));
})().catch((error) => { console.error(String(error)); process.exit(1); });
"""


def node_environment():
    environment = dict(os.environ)
    try:
        root = subprocess.run(['npm', 'root', '-g'], capture_output=True, text=True, timeout=60).stdout.strip()
    except OSError:
        root = ''
    if root:
        environment['NODE_PATH'] = root + (os.pathsep + environment['NODE_PATH'] if environment.get('NODE_PATH') else '')
    return environment


def rasterize(svg, sizes):
    """{size: (versions, RGBA rows)} from Chromium. Raises RuntimeError when Node, Playwright or Chromium is missing."""
    if shutil.which('node') is None:
        raise RuntimeError('Node is not installed: the rasterizer is Chromium through Playwright for Node.')
    request = json.dumps({'svg': base64.b64encode(svg).decode('ascii'), 'sizes': list(sizes)})
    result = subprocess.run(['node', '-e', RASTERIZE], input=request, capture_output=True, text=True, timeout=300,
                            env=node_environment())
    if result.returncode != 0:
        raise RuntimeError('Chromium could not draw the icon: ' + result.stderr.strip()[-1000:])
    answer = json.loads(result.stdout)
    images = {}
    for size in sizes:
        width, height, rows = read_png(base64.b64decode(answer['images'][str(size)]))
        if (width, height) != (size, size):
            raise RuntimeError(f'Chromium drew {width}x{height} for {size} px.')
        images[size] = rows
    return answer['versions'], images


def chunks(data):
    if data[:8] != b'\x89PNG\r\n\x1a\n':
        raise ValueError('Not a PNG.')
    offset = 8
    while offset < len(data):
        length, kind = struct.unpack('>I4s', data[offset:offset + 8])
        yield kind, data[offset + 8:offset + 8 + length]
        offset += 12 + length


def read_png(data):
    """(width, height, rows of RGBA bytes) from an 8-bit RGBA PNG without interlacing (what Chromium writes)."""
    header, compressed = None, b''
    for kind, body in chunks(data):
        if kind == b'IHDR':
            header = struct.unpack('>IIBBBBB', body)
        elif kind == b'IDAT':
            compressed += body
    width, height, depth, color, _, _, interlace = header
    if depth != 8 or color != 6 or interlace != 0:
        raise ValueError(f'Unsupported PNG: depth {depth}, color type {color}, interlace {interlace}.')
    stride = width * 4
    raw = zlib.decompress(compressed)
    rows, previous = [], bytes(stride)
    for y in range(height):
        kind = raw[y * (stride + 1)]
        line = bytearray(raw[y * (stride + 1) + 1:(y + 1) * (stride + 1)])
        for x in range(stride):
            left = line[x - 4] if x >= 4 else 0
            up = previous[x]
            corner = previous[x - 4] if x >= 4 else 0
            if kind == 1:
                line[x] = (line[x] + left) & 255
            elif kind == 2:
                line[x] = (line[x] + up) & 255
            elif kind == 3:
                line[x] = (line[x] + (left + up) // 2) & 255
            elif kind == 4:
                p = left + up - corner
                pa, pb, pc = abs(p - left), abs(p - up), abs(p - corner)
                line[x] = (line[x] + (left if pa <= pb and pa <= pc else up if pb <= pc else corner)) & 255
            elif kind != 0:
                raise ValueError(f'Bad PNG filter {kind}.')
        rows.append(bytes(line))
        previous = rows[-1]
    return width, height, rows


def filtered(rows, width):
    """Each row with the PNG filter (0 to 4) whose output has the smallest sum of absolute values; ties take the lowest."""
    out, previous, stride = bytearray(), bytes(width * 4), width * 4
    for row in rows:
        best = None
        for kind in range(5):
            line = bytearray(stride)
            for x in range(stride):
                left = row[x - 4] if x >= 4 else 0
                up = previous[x]
                corner = previous[x - 4] if x >= 4 else 0
                if kind == 0:
                    predictor = 0
                elif kind == 1:
                    predictor = left
                elif kind == 2:
                    predictor = up
                elif kind == 3:
                    predictor = (left + up) // 2
                else:
                    p = left + up - corner
                    pa, pb, pc = abs(p - left), abs(p - up), abs(p - corner)
                    predictor = left if pa <= pb and pa <= pc else up if pb <= pc else corner
                line[x] = (row[x] - predictor) & 255
            score = sum(value if value < 128 else 256 - value for value in line)
            if best is None or score < best[0]:
                best = (score, kind, line)
        out.append(best[1])
        out += best[2]
        previous = row
    return bytes(out)


def write_png(rows):
    """An 8-bit RGBA PNG with only IHDR, IDAT and IEND: the same pixels always give the same bytes."""
    width, height = len(rows[0]) // 4, len(rows)
    def chunk(kind, body):
        return struct.pack('>I', len(body)) + kind + body + struct.pack('>I', zlib.crc32(kind + body) & 0xffffffff)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 6, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(filtered(rows, width), 9)) + chunk(b'IEND', b''))


def write_ico(pngs):
    """An ICO holding each PNG, in size order: {size: PNG bytes}."""
    sizes = sorted(pngs)
    out = struct.pack('<HHH', 0, 1, len(sizes))
    offset = 6 + 16 * len(sizes)
    for size in sizes:
        out += struct.pack('<BBBBHHII', size % 256, size % 256, 0, 0, 1, 32, len(pngs[size]), offset)
        offset += len(pngs[size])
    return out + b''.join(pngs[size] for size in sizes)


def ico_sizes(data):
    """[(width, height)] of each image an ICO holds, in directory order."""
    reserved, kind, count = struct.unpack('<HHH', data[:6])
    if (reserved, kind) != (0, 1):
        raise ValueError('Not an icon file.')
    sizes = []
    for index in range(count):
        width, height = struct.unpack('<BB', data[6 + 16 * index:8 + 16 * index])
        sizes.append((width or 256, height or 256))
    return sizes


def dimensions(name, data):
    """Each image's [width, height] in pixels: the ICO's three, a PNG's one, the SVG's viewBox."""
    if name.endswith('.ico'):
        return [list(size) for size in ico_sizes(data)]
    if name.endswith('.png'):
        if data[:8] != b'\x89PNG\r\n\x1a\n' or data[12:16] != b'IHDR':
            raise ValueError(f'{name} is not a PNG with IHDR first.')
        return [list(struct.unpack('>II', data[16:24]))]
    view = re.search(rb'viewBox="0 0 ([0-9]+) ([0-9]+)"', data)
    if view is None:
        raise ValueError(f'{name} has no viewBox "0 0 W H".')
    return [[int(view.group(1)), int(view.group(2))]]


def manifest(files, versions):
    """The manifest's text for {name: bytes} drawn by `versions`: the same files always give the same text."""
    source = {'name': SOURCE.relative_to(ROOT).as_posix(), 'sha256': hashlib.sha256(SOURCE.read_bytes()).hexdigest()}
    rows = [json.dumps({'name': name, 'bytes': len(files[name]), 'dimensions': dimensions(name, files[name]),
                        'sha256': hashlib.sha256(files[name]).hexdigest()}) for name in OUTPUTS]
    return ('{\n  "source": ' + json.dumps(source) + ',\n  "rasterizer": '
            + json.dumps({'chromium': versions[0], 'playwright': versions[1]}) + ',\n  "files": [\n    '
            + ',\n    '.join(rows) + '\n  ]\n}\n')


def build(out=ROOT, allow_other=False, manifest_path=None):
    """Writes the four files and the manifest into `out`. Returns {name: bytes} and the rasterizer's versions.

    The manifest goes to `manifest_path`, else to scripts/art/ when `out` is the game folder, else into `out`."""
    svg = SOURCE.read_bytes()
    versions, images = rasterize(svg, sorted({*ICO_SIZES, PNG_SIZE, TOUCH_SIZE}))
    if versions != [CHROMIUM, PLAYWRIGHT] and not allow_other:
        raise RuntimeError(f'This build is recorded for Chromium {CHROMIUM} with Playwright {PLAYWRIGHT}; found Chromium '
                           f'{versions[0]} with Playwright {versions[1]}. Use --allow-other-rasterizer to build anyway, '
                           'and record the new versions here.')
    pngs = {size: write_png(images[size]) for size in images}
    files = {'favicon.ico': write_ico({size: pngs[size] for size in ICO_SIZES}), 'favicon.png': pngs[PNG_SIZE],
             'favicon.svg': svg, 'apple-touch-icon.png': pngs[TOUCH_SIZE]}
    out = Path(out)
    out.mkdir(parents=True, exist_ok=True)
    for name in OUTPUTS:
        (out / name).write_bytes(files[name])
    if manifest_path is None:
        manifest_path = MANIFEST if out.resolve() == ROOT.resolve() else out / MANIFEST.name
    Path(manifest_path).write_text(manifest(files, versions))
    return files, versions


def differences(built, committed):
    """[(name, problem)] between a build folder's icon files and manifest and the committed ones ({name: path}), byte
    for byte; empty when all match."""
    found = []
    for name in (*OUTPUTS, MANIFEST.name):
        left, right = Path(built) / name, Path(committed[name])
        if not right.is_file():
            found.append((name, 'the committed file is missing'))
            continue
        a, b = left.read_bytes(), right.read_bytes()
        if a != b:
            at = next((i for i, (x, y) in enumerate(zip(a, b)) if x != y), min(len(a), len(b)))
            found.append((name, f'differs: redrawn {len(a)} bytes, sha256 {hashlib.sha256(a).hexdigest()}; committed '
                                f'{len(b)} bytes, sha256 {hashlib.sha256(b).hexdigest()}; first difference at byte {at}'))
    return found


def committed_paths():
    """Where the committed icon files and the manifest are."""
    return {**{name: ROOT / name for name in OUTPUTS}, MANIFEST.name: MANIFEST}


def check():
    """The redraw check: 0 when every redrawn file equals the committed one, 1 on a difference, 2 when it cannot draw."""
    with tempfile.TemporaryDirectory() as folder:
        try:
            files, versions = build(folder)
        except RuntimeError as error:
            print(f'build_favicon --check: cannot redraw: {error}', file=sys.stderr)
            return 2
        print(f'build_favicon --check: redrawn with Chromium {versions[0]}, Playwright {versions[1]}')
        found = dict(differences(folder, committed_paths()))
        for name in (*OUTPUTS, MANIFEST.name):
            data = (Path(folder) / name).read_bytes()
            state = 'DIFFERS: ' + found[name] if name in found else 'same'
            print(f'  {name}: {len(data)} bytes, sha256 {hashlib.sha256(data).hexdigest()}: {state}')
    print('build_favicon --check: ' + ('FAILED, ' + str(len(found)) + ' differ' if found else 'OK, every file matches'))
    return 1 if found else 0


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--out', default=str(ROOT), help='the folder to write into (default: the game folder)')
    parser.add_argument('--allow-other-rasterizer', action='store_true', help='build with another Chromium or Playwright')
    parser.add_argument('--check', action='store_true',
                        help='redraw into a temporary folder and compare with the committed files; writes nothing')
    arguments = parser.parse_args(argv)
    if arguments.check:
        if arguments.allow_other_rasterizer or arguments.out != str(ROOT):
            parser.error('--check takes no other option: it always uses the recorded rasterizer and the committed files')
        return check()
    try:
        files, versions = build(arguments.out, arguments.allow_other_rasterizer)
    except RuntimeError as error:
        print(str(error), file=sys.stderr)
        return 1
    print(json.dumps({'chromium': versions[0], 'playwright': versions[1],
                      'files': {name: {'bytes': len(files[name]), 'sha256': hashlib.sha256(files[name]).hexdigest()} for name in OUTPUTS}}))
    return 0


if __name__ == '__main__':
    sys.exit(main())
