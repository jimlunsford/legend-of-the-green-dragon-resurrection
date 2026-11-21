"""The address the game's pages link a project document at (lib/source_link.php, Phase 6 task 34), for the web tests.

A release or a test build links its own tag, `v` and src/Version.php's version; a -dev version, which is never tagged
(docs/VERSIONING.md), links `main`.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
REPOSITORY = 'https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection'


def version():
    return re.search(r"CURRENT = '([^']+)'", (ROOT / 'src' / 'Version.php').read_text(encoding='utf-8')).group(1)


def doc_ref(current=None):
    current = version() if current is None else current
    return 'main' if current.endswith('-dev') else 'v' + current


def host_guide_url():
    return f'{REPOSITORY}/blob/{doc_ref()}/docs/HOST-GUIDE.md'
