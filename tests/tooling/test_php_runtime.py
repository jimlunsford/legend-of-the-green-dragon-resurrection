"""Tooling tests that run PHP skip on a PHP older than the supported minimum, and only where that is allowed.

Baseline integrity runs the tooling suite on the runner's system PHP (8.3 on ubuntu-latest in 2026-10), which this
project does not support; Modern core installs PHP 8.4 or 8.5 and must never skip them (UNNECESSARY-FILES-20261024.md,
"Baseline on the runner's PHP").
"""
import os
from pathlib import Path
import re
import sys
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[2]
TOOLING = Path(__file__).resolve().parent
sys.path.insert(0, str(TOOLING))
import php_runtime  # noqa: E402


class PhpRuntimeTests(unittest.TestCase):
    def fake_php(self, directory, output, status=0):
        path = Path(directory) / f'php-{len(list(Path(directory).iterdir()))}'
        path.write_text(f'#!/bin/sh\nprintf %s {output!r}\nexit {status}\n')
        path.chmod(0o755)
        return str(path)

    def test_minimum_comes_from_composer_json(self):
        self.assertEqual((8, 4), php_runtime.minimum())
        self.assertEqual((8, 4), php_runtime.minimum('~8.4.0 || ~8.5.0'))
        self.assertEqual((8, 5), php_runtime.minimum('^8.5'))
        self.assertEqual((8, 3), php_runtime.minimum('~8.5.0 || ~8.3.0'))
        for constraint in ('>=8.4', '', '8', '~8.4.0 || dev-main'):
            with self.subTest(constraint=constraint), self.assertRaises(ValueError):
                php_runtime.minimum(constraint)

    def test_older_php_skips_with_the_reason_unless_required(self):
        with tempfile.TemporaryDirectory() as directory:
            old = self.fake_php(directory, '8.3')
            with mock.patch.dict(os.environ, {'PHP_BINARY': old}):
                os.environ.pop(php_runtime.REQUIRE, None)
                with self.assertRaises(unittest.SkipTest) as skipped:
                    php_runtime.supported_php()
                self.assertEqual(f'PHP 8.3 at {old} is older than the supported minimum, PHP 8.4 (composer.json); '
                                 'these tests run in Modern core', str(skipped.exception))
                for value in ('0', '', 'yes'):
                    os.environ[php_runtime.REQUIRE] = value
                    self.assertRaises(unittest.SkipTest, php_runtime.supported_php)
                os.environ[php_runtime.REQUIRE] = '1'
                with self.assertRaises(RuntimeError) as failed:
                    php_runtime.supported_php()
                self.assertIn('older than the supported minimum', str(failed.exception))
                self.assertIn('RESURRECTION_REQUIRE_PHP=1 requires them to run', str(failed.exception))

    def test_supported_php_runs_with_or_without_the_requirement(self):
        with tempfile.TemporaryDirectory() as directory:
            for output in ('8.4', '8.5', '9.0'):
                php = self.fake_php(directory, output)
                for required in ('', '1'):
                    with self.subTest(version=output, required=required), \
                            mock.patch.dict(os.environ, {'PHP_BINARY': php, php_runtime.REQUIRE: required}):
                        self.assertEqual(php, php_runtime.supported_php())

    def test_missing_or_broken_php(self):
        with tempfile.TemporaryDirectory() as directory, \
                mock.patch.dict(os.environ, {'PATH': directory, php_runtime.REQUIRE: ''}):
            os.environ.pop('PHP_BINARY', None)
            with self.assertRaises(unittest.SkipTest) as skipped:
                php_runtime.supported_php()
            self.assertEqual('PHP required; mandatory in Modern core CI', str(skipped.exception))
            os.environ[php_runtime.REQUIRE] = '1'
            self.assertRaises(RuntimeError, php_runtime.supported_php)
            # A binary whose version cannot be read is an error either way, never a skip.
            for output, status in (('', 0), ('PHP 8.3.6', 0), ('8.4', 1)):
                os.environ['PHP_BINARY'] = self.fake_php(directory, output, status)
                for required in ('', '1'):
                    with self.subTest(output=output, status=status, required=required):
                        os.environ[php_runtime.REQUIRE] = required
                        self.assertRaises(RuntimeError, php_runtime.supported_php)

    def test_modern_core_requires_php_and_baseline_does_not(self):
        leg = (ROOT / '.github' / 'workflows' / 'modern-core-leg.yml').read_text()
        jobs = {m.group(1): m.start() for m in re.finditer(r'^  (\w+):\n', leg, re.M)}
        http = leg[jobs['http']:jobs['result']]
        # The job that runs the tooling shards on PHP 8.4 or 8.5 turns every skip of these tests into a failure.
        self.assertIn('tooling-shards.py', http)
        self.assertEqual(1, len(re.findall(r'^      RESURRECTION_REQUIRE_PHP: \'1\'\n', http, re.M)))
        self.assertIn('php-version: ${{ inputs.php }}', http)
        modern = (ROOT / '.github' / 'workflows' / 'modern-core.yml').read_text()
        self.assertEqual({'8.4', '8.5'}, set(re.findall(r"php: '?(8\.\d)'?", modern)))
        # Baseline stays a provenance check on the runner's own PHP: it neither installs PHP nor requires it.
        baseline = (ROOT / '.github' / 'workflows' / 'baseline-integrity.yml').read_text()
        self.assertNotIn('setup-php', baseline)
        self.assertNotIn(php_runtime.REQUIRE, baseline)
        self.assertIn('python3 -m unittest discover -s tests/tooling -v', baseline)

    def test_tooling_tests_find_php_only_through_the_guard(self):
        # Web application tests need the installed CI database and skip without it, so Baseline never runs their PHP.
        allowed = {'test_web_application.py'}
        direct = re.compile(r"""shutil\.which\(\s*['"]php['"]\s*\)|PHP_BINARY""")
        found = sorted(p.name for p in TOOLING.glob('test_*.py') if p.name not in allowed and p.name != Path(__file__).name
                       and direct.search(p.read_text()))
        self.assertEqual([], found)
        for name in ('test_operator_pages.py', 'test_disabled_endpoints.py'):
            with self.subTest(name=name):
                self.assertIn('supported_php()', (TOOLING / name).read_text())


if __name__ == '__main__':
    unittest.main()
