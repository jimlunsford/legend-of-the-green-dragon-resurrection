"""The PHP binary for tooling tests that run the application's PHP without a database.

Baseline integrity runs every tooling test with the runner's system PHP, which can be older than the PHP this project
supports (composer.json). There such tests skip, with the reason. Modern core installs a supported PHP and sets
RESURRECTION_REQUIRE_PHP=1, which turns any skip here into a failure, so a skip cannot hide a real failure where the
tests matter.
"""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
REQUIRE = 'RESURRECTION_REQUIRE_PHP'


def minimum(constraint=None):
    """The lowest PHP (major, minor) that composer.json's "php" constraint allows, such as "~8.4.0 || ~8.5.0"."""
    if constraint is None:
        constraint = json.loads((ROOT / 'composer.json').read_text())['require']['php']
    parts = [re.fullmatch(r'\s*[~^]?(\d+)\.(\d+)(?:\.\d+)?\s*', part) for part in constraint.split('||')]
    if not all(parts):
        raise ValueError(f'Unrecognized PHP constraint in composer.json: {constraint!r}')
    return min(tuple(map(int, part.groups())) for part in parts)


def version(php):
    """The (major, minor) of a PHP binary; a binary that does not answer is an error, never a skip."""
    result = subprocess.run([php, '-n', '-r', 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;'],
                            capture_output=True, text=True, timeout=30)
    match = re.fullmatch(r'(\d+)\.(\d+)', result.stdout.strip())
    if result.returncode != 0 or not match:
        raise RuntimeError(f'Could not read the PHP version of {php}: {result.stdout!r} {result.stderr!r}')
    return tuple(map(int, match.groups()))


def supported_php():
    """The PHP binary to test with. Raises SkipTest when there is none or it is older than the supported minimum,
    unless RESURRECTION_REQUIRE_PHP=1, which makes that a failure instead."""
    php = os.environ.get('PHP_BINARY') or shutil.which('php')
    lowest = minimum()
    reason = None
    if not php:
        reason = 'PHP required; mandatory in Modern core CI'
    else:
        found = version(php)
        if found < lowest:
            reason = (f'PHP {found[0]}.{found[1]} at {php} is older than the supported minimum, PHP {lowest[0]}.{lowest[1]} '
                      '(composer.json); these tests run in Modern core')
    if reason is None:
        return php
    if os.environ.get(REQUIRE) == '1':
        raise RuntimeError(reason + f'; {REQUIRE}=1 requires them to run')
    raise unittest.SkipTest(reason)
