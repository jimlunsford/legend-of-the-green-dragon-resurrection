#!/usr/bin/env python3
"""Build the release package: the zip a host downloads, with the upload in upload/ and the documents beside it.

    python3 scripts/build-release-package.py --out /absolute/folder/outside/the/tree

The zip holds one folder, `legend-of-the-green-dragon-resurrection-<version>/` (Phase 6 task 16, R1), and in it:

- `upload/`: exactly the host guide's "Upload these" list, what goes into the site's folder;
- `INSTALL.txt`: how to check the download, what to upload and where to read on (from `docs/package/INSTALL.txt`);
- `HOST-GUIDE.md`, `RELEASE-NOTES-<version>.md` (when the tree has one), `THIRD-PARTY-LICENSES.md`, and `hosting/` with
  the three sample server rule files, from `docs/`: the documents never go in the docroot, so they travel beside it;
- `LICENSE.txt`, `README.txt` and `NOTICE`, byte for byte the tree's, so the terms are read before anything is uploaded
  (upload/ holds them too).

The upload list comes from `docs/HOST-GUIDE.md`, section 2, "Upload these", read the way
`tests/tooling/test_host_guide.py` reads it: each list item's code spans before its first colon. A folder entry (`lib/`)
takes every file under it, a file entry takes that file, and a pattern entry (`*.php`) takes the files in the top
folder whose names match it. A file the guide's "Do not upload" list names is refused, never left out quietly: the two
lists must not overlap. The files are the ones Git lists (`git ls-files -co --exclude-standard`: tracked, and new files
not ignored), so nothing the game makes or Git ignores (`dbconnect.php`, the setup token file, `vendor/`) can get in.

The zip is deterministic: the same tree gives the same bytes. Entries are in sorted order, each dated 1980-01-01 00:00
(the earliest date a zip can hold), mode 0644, stored without compression (so the bytes do not depend on the zlib that
built them), with no comment and no extra fields.

It writes `<name>.zip` and `<name>.zip.sha256` (the hash, two spaces, the file name) into the `--out` folder, which must
be outside the repository. `<name>` is `legend-of-the-green-dragon-resurrection-<version>`, the version from
`src/Version.php`. It prints one line of JSON (the zip's path, file count, size and SHA-256). It never uploads, tags or
publishes anything (`docs/RC-CUT-CHECKLIST.md`).
"""
import argparse
import fnmatch
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
GUIDE = ROOT / 'docs' / 'HOST-GUIDE.md'
HEADING = '### Upload these'
DATE = (1980, 1, 1, 0, 0, 0)
NAME = 'legend-of-the-green-dragon-resurrection'
REPOSITORY = 'https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection'
INSTALL = 'docs/package/INSTALL.txt'
# Beside upload/: the package's name for each document, and where it is in the tree.
BESIDE = {
    'HOST-GUIDE.md': 'docs/HOST-GUIDE.md',
    'THIRD-PARTY-LICENSES.md': 'docs/THIRD-PARTY-LICENSES.md',
    'hosting/apache.conf': 'docs/hosting/apache.conf',
    'hosting/htaccess': 'docs/hosting/htaccess',
    'hosting/nginx.conf': 'docs/hosting/nginx.conf',
    'LICENSE.txt': 'LICENSE.txt',
    'README.txt': 'README.txt',
    'NOTICE': 'NOTICE',
}


class Refused(Exception):
    pass


def list_entries(text, heading):
    """The entries of one of the guide's lists: each item's code spans before its first colon."""
    lines = text.splitlines()
    if heading not in lines:
        raise Refused(f'The host guide has no "{heading.lstrip("# ")}" list.')
    start = lines.index(heading) + 1
    items = []
    for line in lines[start:]:
        if line.startswith('#'):
            break
        if line.startswith('- '):
            items.append(line[2:])
        elif items and line.startswith('  ') and line.strip():
            items[-1] += ' ' + line.strip()
    entries = []
    for item in items:
        outside = re.sub(r'`[^`]*`', lambda m: 'x' * len(m.group(0)), item)
        head = item[:outside.index(': ')] if ': ' in outside else item
        entries += re.findall(r'`([^`]+)`', head)
    if not entries:
        raise Refused(f'The host guide\'s "{heading.lstrip("# ")}" list is empty.')
    return entries


def selected(entry, path):
    """Whether an upload entry takes a file: a folder its files, a pattern the top folder's files it matches."""
    if entry.endswith('/'):
        return path.startswith(entry)
    if '*' in entry:
        return '/' not in path and fnmatch.fnmatchcase(path, entry)
    return path == entry


def repository_files(root):
    result = subprocess.run(['git', 'ls-files', '-co', '--exclude-standard', '-z'], cwd=root, check=True, capture_output=True)
    return sorted(name for name in result.stdout.decode('utf-8').split('\0') if name)


def package_files(root=ROOT):
    """The files the package holds, sorted. Every entry must take at least one file."""
    text = (root / 'docs' / 'HOST-GUIDE.md').read_text(encoding='utf-8')
    entries = list_entries(text, HEADING)
    excluded = list_entries(text, '### Do not upload')
    files = [path for path in repository_files(root) if (root / path).is_file()]
    chosen = sorted({path for path in files for entry in entries if selected(entry, path)})
    clash = [path for path in chosen for entry in excluded if selected(entry, path)]
    if clash:
        raise Refused('The upload list takes files the "Do not upload" list names: ' + ', '.join(sorted(set(clash))[:5]))
    for entry in entries:
        if not any(selected(entry, path) for path in chosen):
            raise Refused(f'The upload list names {entry}, which is not in the tree.')
    return chosen


def version(root=ROOT):
    match = re.search(r"const string CURRENT = '([^']+)';", (root / 'src' / 'Version.php').read_text(encoding='utf-8'))
    if not match:
        raise Refused('src/Version.php does not state the version.')
    return match.group(1)


def install_text(root, current):
    """INSTALL.txt: docs/package/INSTALL.txt with this version's names and the guide's address at its tag."""
    text = (root / INSTALL).read_text(encoding='utf-8')
    test_build = '-rc.' in current
    build = ('A test build: a candidate for hosts and players to try. It is not the release, it has had no independent\n'
             'security review, and it is not certified for public game hosting.') if test_build else \
            'A release of Legend of the Green Dragon - Resurrection.'
    notes = f'RELEASE-NOTES-{current}.md'
    listed = f'  {notes}\n      what changed, and the known issues\n' if (root / 'docs' / notes).is_file() else ''
    for marker, value in [('@VERSION@', current), ('@BUILD@', build), ('@ZIP@', f'{NAME}-{current}.zip'),
                          ('@GUIDE_URL@', f'{REPOSITORY}/blob/v{current}/docs/HOST-GUIDE.md'), ('@NOTES@', listed)]:
        text = text.replace(marker, value)
    if re.search(r'@[A-Z_]+@', text):
        raise Refused(f'{INSTALL} holds a marker the script does not fill.')
    return text.encode('utf-8')


def package_entries(root=ROOT):
    """The zip's entries, sorted: (name in the zip, bytes). One top folder; upload/ is the upload list."""
    current = version(root)
    top = f'{NAME}-{current}/'
    entries = {top + 'upload/' + path: (root / path).read_bytes() for path in package_files(root)}
    beside = dict(BESIDE)
    notes = f'docs/RELEASE-NOTES-{current}.md'
    if (root / notes).is_file():
        beside[f'RELEASE-NOTES-{current}.md'] = notes
    for name, source in beside.items():
        if not (root / source).is_file():
            raise Refused(f'The package needs {source}, which is not in the tree.')
        entries[top + name] = (root / source).read_bytes()
    entries[top + 'INSTALL.txt'] = install_text(root, current)
    return sorted(entries.items())


def write_zip(entries, target):
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_STORED) as archive:
        for name, data in entries:
            info = zipfile.ZipInfo(name, date_time=DATE)
            info.compress_type = zipfile.ZIP_STORED
            info.create_system = 3
            info.external_attr = (0o100644 << 16)
            archive.writestr(info, data)


def build(out, root=ROOT):
    out = Path(out)
    if not out.is_absolute():
        raise Refused('--out must be an absolute path.')
    resolved = out.resolve()
    if resolved == root.resolve() or root.resolve() in resolved.parents:
        raise Refused('--out must be outside the repository.')
    resolved.mkdir(parents=True, exist_ok=True)
    entries = package_entries(root)
    name = f'{NAME}-{version(root)}.zip'
    target = resolved / name
    with tempfile.NamedTemporaryFile(dir=resolved, prefix='.package-', suffix='.zip', delete=False) as handle:
        temporary = Path(handle.name)
    try:
        write_zip(entries, temporary)
        temporary.replace(target)
    finally:
        temporary.unlink(missing_ok=True)
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    (resolved / (name + '.sha256')).write_text(f'{digest}  {name}\n', encoding='utf-8')
    return {'zip': str(target), 'files': len(entries), 'bytes': target.stat().st_size, 'sha256': digest}


def main(argv=None):
    parser = argparse.ArgumentParser(description='Build the release package (upload/ and the documents beside it).')
    parser.add_argument('--out', required=True, help='absolute folder outside the repository for the zip and its SHA-256')
    args = parser.parse_args(argv)
    try:
        print(json.dumps(build(args.out)))
    except Refused as refusal:
        print('Release package refused. ' + str(refusal), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
