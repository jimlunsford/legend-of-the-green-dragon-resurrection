"""The versions inside the stylesheets (Phase 6 task 18, N1; scripts/asset-versions.py, src/Ui/AssetUrl.php).

The game prints each stylesheet and script URL with ?v= and its file's version (AssetUrlTest and the page renders check
that). The URLs the stylesheets print themselves, resurrection.css's @import lines and fonts.css's font files, carry the
same version, written by scripts/asset-versions.py. This test fails when one is missing or stale, so a changed font or
stylesheet always reaches the browser after an upgrade. Plain Python, no PHP: it runs in Baseline integrity too.
"""
import hashlib
import importlib.util
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'asset-versions.py'
REFERENCE = re.compile(r"""url\((['"])([^'")]+)\1\)""")


def load(root):
    """The script as a module, pointed at another tree's assets/ui."""
    spec = importlib.util.spec_from_file_location('asset_versions', SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    module.ROOT = root
    module.UI = root / 'assets' / 'ui'
    return module


class AssetVersionTests(unittest.TestCase):
    def test_every_stylesheet_and_font_reference_carries_its_files_version(self):
        seen = 0
        for stylesheet in sorted((ROOT / 'assets' / 'ui').glob('*.css')):
            for _, reference in REFERENCE.findall(stylesheet.read_text(encoding='utf-8')):
                path, _, query = reference.partition('?')
                if not re.search(r'\.(css|woff2)\Z', path):
                    # Images keep plain URLs (src/Ui/AssetUrl.php says why).
                    self.assertEqual('', query, f'{stylesheet.name}: {reference}')
                    continue
                target = (stylesheet.parent / path).resolve()
                self.assertTrue(target.is_file(), f'{stylesheet.name}: {path}')
                self.assertEqual('v=' + hashlib.sha256(target.read_bytes()).hexdigest()[:12], query, f'{stylesheet.name}: {reference}')
                seen += 1
        # resurrection.css's two @import lines and fonts.css's sixteen font files.
        self.assertEqual(18, seen)
        result = subprocess.run([sys.executable, str(SCRIPT), '--check'], capture_output=True, text=True, timeout=60)
        self.assertEqual((0, '', ''), (result.returncode, result.stdout, result.stderr))

    def test_a_changed_file_changes_the_versions_that_name_it(self):
        with tempfile.TemporaryDirectory(prefix='resurrection-asset-versions-') as work:
            root = Path(work)
            shutil.copytree(ROOT / 'assets' / 'ui', root / 'assets' / 'ui')
            shutil.copytree(ROOT / 'assets' / 'fonts', root / 'assets' / 'fonts')
            module = load(root)
            self.assertEqual([], module.stale())
            before = {name: (root / 'assets' / 'ui' / name).read_text(encoding='utf-8') for name in ('fonts.css', 'resurrection.css')}
            # One byte more in a font: fonts.css is stale, and once written, resurrection.css is too.
            font = root / 'assets' / 'fonts' / 'cinzel' / 'cinzel-latin-500-normal.woff2'
            font.write_bytes(font.read_bytes() + b'\0')
            self.assertEqual(['fonts.css'], module.stale())
            module.write()
            self.assertEqual([], module.stale())
            after = {name: (root / 'assets' / 'ui' / name).read_text(encoding='utf-8') for name in ('fonts.css', 'resurrection.css')}
            new = hashlib.sha256(font.read_bytes()).hexdigest()[:12]
            self.assertIn(f"cinzel-latin-500-normal.woff2?v={new}'", after['fonts.css'])
            self.assertNotEqual(before['resurrection.css'], after['resurrection.css'])
            fonts = hashlib.sha256((root / 'assets' / 'ui' / 'fonts.css').read_bytes()).hexdigest()[:12]
            self.assertIn(f"@import url('fonts.css?v={fonts}');", after['resurrection.css'])
            # Only the one font's line changed in fonts.css, and only the fonts.css import in resurrection.css.
            changed = [a for a, b in zip(before['fonts.css'].splitlines(), after['fonts.css'].splitlines()) if a != b]
            self.assertEqual(1, len(changed))
            changed = [a for a, b in zip(before['resurrection.css'].splitlines(), after['resurrection.css'].splitlines()) if a != b]
            self.assertEqual(["@import url('fonts.css?v=" + before['resurrection.css'].split("fonts.css?v=")[1][:12] + "');"], changed)
            # --check names a stale file and exits 1.
            (root / 'assets' / 'ui' / 'tokens.css').write_text((root / 'assets' / 'ui' / 'tokens.css').read_text(encoding='utf-8') + '\n', encoding='utf-8')
            self.assertEqual(['resurrection.css'], module.stale())
            self.assertEqual(1, module.main(['--check']))


if __name__ == '__main__':
    unittest.main()
