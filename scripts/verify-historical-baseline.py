#!/usr/bin/env python3
"""Verify historical Git objects, not the evolving working tree. Python 3 stdlib."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import sys

TAG = 'historical-source-1.1.2'
TAG_OBJECT = '51cab4fbe58a234651a3177a56289b18bc152b4d'
COMMIT = 'bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2'
TREE = '4013a0ccc5227e87cd7a22de00b7c322d7aa237c'
COUNT = 417

def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.PIPE)

def verify(repo, manifest=None):
    repo = Path(repo)
    ref = 'refs/tags/' + TAG
    if git(repo, 'cat-file', '-t', ref).strip() != b'tag':
        raise ValueError('Historical reference must be an annotated tag')
    if git(repo, 'rev-parse', ref).decode().strip() != TAG_OBJECT:
        raise ValueError('Historical annotated tag object differs from expected object')
    git(repo, 'merge-base', '--is-ancestor', COMMIT, 'HEAD')
    if git(repo, 'rev-parse', ref + '^{commit}').decode().strip() != COMMIT:
        raise ValueError('Historical tag target differs from expected commit')
    if git(repo, 'rev-parse', COMMIT + '^{tree}').decode().strip() != TREE:
        raise ValueError('Historical root tree differs from expected tree')
    entries = {}
    for raw in git(repo, 'ls-tree', '-r', '-z', COMMIT).split(b'\0'):
        if not raw:
            continue
        info, name = raw.split(b'\t', 1)
        mode, kind, oid = info.split()
        if kind != b'blob' or mode not in (b'100644', b'100755'):
            raise ValueError('Unexpected historical entry type')
        entries[name.decode('utf-8')] = oid.decode()
    if len(entries) != COUNT:
        raise ValueError('Historical tracked-file count differs from 417')
    path = Path(manifest) if manifest else repo / 'docs/provenance/historical-core-manifest.sha256'
    expected = {}
    for line in path.read_text(encoding='utf-8').splitlines():
        digest, name = line.split('  ', 1)
        if name in expected or len(digest) != 64 or any(c not in '0123456789abcdef' for c in digest):
            raise ValueError('Malformed or duplicate manifest entry')
        expected[name] = digest
    if set(expected) != set(entries):
        raise ValueError('Manifest paths differ from historical tree')
    for name, oid in entries.items():
        actual = hashlib.sha256(git(repo, 'cat-file', 'blob', oid)).hexdigest()
        if actual != expected[name]:
            raise ValueError('Historical manifest hash mismatch: ' + name)
    ancestors = git(repo, 'rev-list', COMMIT).decode().splitlines()
    if len(ancestors) != 11:
        raise ValueError('Historical ancestry count differs from 11')
    # cat-file proves ancestry objects are available, not just names in a shallow boundary.
    for ancestor in ancestors:
        git(repo, 'cat-file', 'commit', ancestor)
    if git(repo, 'rev-parse', '--is-shallow-repository').strip() != b'false':
        raise ValueError('Full history is required; fetch complete ancestry and tags')
    return {'status': 'PASS', 'tag': TAG, 'tag_object': TAG_OBJECT, 'commit': COMMIT, 'tree': TREE,
            'files_verified': len(entries), 'historical_commits': len(ancestors)}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--manifest', type=Path)
    args = parser.parse_args()
    try:
        print(json.dumps(verify(args.repo, args.manifest), indent=2))
    except (ValueError, OSError, subprocess.CalledProcessError) as exc:
        print('FAIL: ' + str(exc), file=sys.stderr)
        return 1
    return 0

if __name__ == '__main__':
    sys.exit(main())
