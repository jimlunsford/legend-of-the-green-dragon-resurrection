#!/usr/bin/env python3
"""Write, or check, the versions inside the game's stylesheets (Phase 6 task 18, N1; docs/PHASE6-TASK18-20261114.md).

The game prints every stylesheet and script URL with ?v= and the first 12 hex characters of the file's SHA-256
(src/Ui/AssetUrl.php). Two kinds of URL are not printed by the game but by the stylesheets themselves, which the server
sends as they are: the @import lines of assets/ui/resurrection.css (fonts.css and tokens.css) and the font files'
url()s in assets/ui/fonts.css. This script writes the same query into those, in order: the fonts into fonts.css first,
then fonts.css and tokens.css into resurrection.css. A changed font therefore changes fonts.css, which changes
resurrection.css, whose new version every page then links. Images keep plain URLs (see AssetUrl).

  python3 scripts/asset-versions.py          write the versions
  python3 scripts/asset-versions.py --check  exit 0 when every version is current, 1 (and name each) when one is not
"""
import hashlib
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
UI = ROOT / 'assets' / 'ui'
# The stylesheets that reference versioned files, in the order they must be written: a file's own version must be
# final before another file names it.
ORDER = ('fonts.css', 'tokens.css', 'safeframe.css', 'resurrection.css')
# A url() or @import of a stylesheet or a font, relative, with or without a version.
REFERENCE = re.compile(r"""url\((['"])([^'"?#)]+\.(?:css|woff2))(?:\?v=[0-9a-f]*)?\1\)""")


def version(path):
    """The version of a file: the first 12 hex characters of its SHA-256."""
    return hashlib.sha256(path.read_bytes()).hexdigest()[:12]


def versioned(stylesheet):
    """The stylesheet's text with every stylesheet and font reference carrying its file's current version."""
    def replace(match):
        target = (stylesheet.parent / match.group(2)).resolve()
        if not target.is_file():
            raise SystemExit(f'{stylesheet.relative_to(ROOT)}: {match.group(2)} does not exist')
        return f'url({match.group(1)}{match.group(2)}?v={version(target)}{match.group(1)})'
    return REFERENCE.sub(replace, stylesheet.read_text(encoding='utf-8'))


def stale():
    """The stylesheets whose versions are not current, checked in ORDER as if each earlier one had been written."""
    found = []
    for name in ORDER:
        path = UI / name
        if path.read_text(encoding='utf-8') != versioned(path):
            found.append(name)
    return found


def write():
    for name in ORDER:
        path = UI / name
        text = versioned(path)
        if text != path.read_text(encoding='utf-8'):
            path.write_text(text, encoding='utf-8')
            print(f'wrote assets/ui/{name}')


def main(arguments):
    if arguments == ['--check']:
        names = stale()
        for name in names:
            print(f'assets/ui/{name}: a version is not current; run python3 scripts/asset-versions.py', file=sys.stderr)
        return 1 if names else 0
    if arguments:
        print(__doc__, file=sys.stderr)
        return 2
    write()
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
