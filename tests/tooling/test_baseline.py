"""Negative tests protect the integrity gate from false green results."""
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('baseline', ROOT/'scripts/verify-historical-baseline.py')
baseline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(baseline)

class BaselineTests(unittest.TestCase):
    def test_exact_historical_objects(self):
        self.assertEqual(baseline.verify(ROOT)['files_verified'],417)

    def altered_manifest(self, change, message):
        original = (ROOT/'docs/provenance/historical-core-manifest.sha256').read_text()
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)/'manifest.sha256'
            path.write_text(change(original))
            with self.assertRaisesRegex(ValueError,message):
                baseline.verify(ROOT,path)

    def test_changed_hash_rejected(self):
        self.altered_manifest(lambda s: '0'*64+s[64:], 'hash mismatch')

    def test_missing_path_rejected(self):
        self.altered_manifest(lambda s: '\n'.join(s.splitlines()[1:])+'\n', 'paths differ')

    def test_duplicate_path_rejected(self):
        self.altered_manifest(lambda s: s+s.splitlines()[0]+'\n', 'duplicate')

if __name__ == '__main__':
    unittest.main()
