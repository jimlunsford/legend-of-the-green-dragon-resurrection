"""Check fail-closed lab inputs and byte-preserving exports without executing PHP."""
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('lab',ROOT/'lab/historical-1.1.2/lab.py')
lab = importlib.util.module_from_spec(spec)
spec.loader.exec_module(lab)

class LabTests(unittest.TestCase):
    def test_unselected_runtime_rejected(self):
        with self.assertRaisesRegex(ValueError,'reviewed image'):
            lab.validate_lock(ROOT/'lab/historical-1.1.2/runtime-lock.example.json')

    def test_floating_tag_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            p=Path(temp)/'lock.json'
            p.write_text(json.dumps({'schema_version':1,'php_image':'php:5.6-cli','database_image':'mariadb:latest'}))
            with self.assertRaisesRegex(ValueError,'reviewed image'):
                lab.validate_lock(p)

    def test_export_exact_bytes_without_runtime_or_credentials(self):
        original=lab.RUNTIME
        try:
            with tempfile.TemporaryDirectory() as temp:
                lab.RUNTIME=Path(temp)/'runtime'
                result=lab.prepare(None)
                self.assertFalse(result['application_executed'])
                self.assertFalse((lab.RUNTIME/'lab.env').exists())
                for line in (ROOT/'docs/provenance/historical-core-manifest.sha256').read_text().splitlines():
                    digest,name=line.split('  ',1)
                    self.assertEqual(hashlib.sha256((lab.RUNTIME/'source'/name).read_bytes()).hexdigest(),digest,name)
                with self.assertRaisesRegex(ValueError,'exists'):
                    lab.prepare(None)
        finally:
            lab.RUNTIME=original

if __name__=='__main__':
    unittest.main()
