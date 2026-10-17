#!/usr/bin/env python3
"""Lint every shipped PHP file, excluding dependencies and disposable artifacts."""
import os
from pathlib import Path
import subprocess
import sys
ROOT = Path(__file__).resolve().parents[1]
EXCLUDED = {'.git', 'vendor', '.runtime', '.phpunit.cache', '.phpstan-cache'}
files = sorted(p for p in ROOT.rglob('*.php') if not EXCLUDED.intersection(p.relative_to(ROOT).parts))
failed = []
for path in files:
    result = subprocess.run([os.environ.get('PHP_BINARY', 'php'), '-n', '-d', 'error_reporting=-1', '-l', str(path)], capture_output=True, text=True)
    # Deprecations are failures even where the CLI lint exit status is zero.
    if result.returncode or result.stderr or 'Deprecated:' in result.stdout:
        failed.append(path)
        print(result.stdout + result.stderr)
print(f'PHP lint: {len(files)} files, {len(failed)} failures')
sys.exit(bool(failed))
