#!/usr/bin/env python3
"""The release upgrade check: install the latest release, upgrade it to the commit under test, compare with a fresh install.

Every release must upgrade cleanly from the one before (docs/VERSIONING.md). Modern core runs this on its empty test
database before the PHP tests. It picks the release to upgrade from as the highest tag matching vMAJOR.MINOR.PATCH or
vMAJOR.MINOR.PATCH-rc.N that is an ancestor of the commit under test and not that commit itself. Then it:

1. installs that release (its own scripts/install.php, from `git archive` of the tag) into the empty database,
2. upgrades it with this commit's scripts/upgrade.php --backup-confirmed,
3. snapshots the database (tests/support/database_snapshot.php --normalized), clears it and installs this commit fresh,
4. fails unless the two snapshots are the same, and clears the database again.

With no such tag it says there is no release yet and passes. `--plan` only prints what it would do. `--release-ref REF`
treats REF as the release: Modern core runs it with HEAD, so the install, upgrade and comparison above run on every
commit even before a release exists.

    python3 scripts/release-upgrade-check.py [--plan] [--release-ref REF]
"""
import argparse
import base64
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[1]
RELEASE_TAG = re.compile(r'^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-rc\.([1-9][0-9]*))?$')
NO_RELEASE = ('Release upgrade check: no release yet. No tag matches vMAJOR.MINOR.PATCH or vMAJOR.MINOR.PATCH-rc.N '
              '(historical-source-1.1.2 is a preservation marker, not a release), so there is nothing to upgrade from. Passing.')
ADMIN = {'RESURRECTION_ADMIN_LOGIN': 'ReleaseCheckAdmin', 'RESURRECTION_ADMIN_PASSWORD': 'Synthetic release check password'}


def git(repo, *arguments):
    return subprocess.run(['git', '-C', str(repo), *arguments], check=True, capture_output=True, text=True).stdout.strip()


def precedence(tag):
    """Semantic version order: a release sorts after its own test builds."""
    major, minor, patch, rc = RELEASE_TAG.match(tag).groups()
    return (int(major), int(minor), int(patch), 1 if rc is None else 0, int(rc or 0))


def choose_release(repo, head='HEAD'):
    """The release to upgrade from, or None: the highest release tag that is an ancestor of head, not head itself."""
    head_commit = git(repo, 'rev-parse', head + '^{commit}')
    candidates = []
    for tag in git(repo, 'tag', '--list').splitlines():
        if not RELEASE_TAG.match(tag):
            continue
        commit = git(repo, 'rev-parse', tag + '^{commit}')
        ancestor = subprocess.run(['git', '-C', str(repo), 'merge-base', '--is-ancestor', commit, head_commit]).returncode == 0
        if ancestor and commit != head_commit:
            candidates.append(tag)
    return max(candidates, key=precedence) if candidates else None


def php():
    found = shutil.which('php')
    if not found:
        raise SystemExit('Release upgrade check: php is required.')
    return found


def database():
    """The disposable test database's settings; refuses any other database."""
    if not os.environ.get('RESURRECTION_TEST_DB_HOST'):
        raise SystemExit('Release upgrade check: RESURRECTION_TEST_DB_HOST is required (the disposable CI database).')
    if os.environ.get('RESURRECTION_TEST_DB_NAME') != 'resurrection_test':
        raise SystemExit('Release upgrade check: refusing a database other than resurrection_test.')
    return {'DB_HOST': os.environ['RESURRECTION_TEST_DB_HOST'], 'DB_USER': os.environ['RESURRECTION_TEST_DB_USER'],
            'DB_PASS': os.environ['RESURRECTION_TEST_DB_PASSWORD'], 'DB_NAME': 'resurrection_test', 'DB_PREFIX': '',
            'DB_USEDATACACHE': 0, 'DB_DATACACHEPATH': ''}


def run(command, cwd, what, environment=None):
    result = subprocess.run(command, cwd=cwd, capture_output=True, text=True, timeout=600,
                            env={**os.environ, **(environment or {})})
    if result.returncode != 0:
        raise SystemExit(f'Release upgrade check failed: {what} exited {result.returncode}.\n{result.stdout}{result.stderr}')
    return result.stdout


def tables(config):
    code = ("require 'lib/dbwrapper_pdo.php'; require $argv[1]; db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME);"
            " echo json_encode(array_map(static fn ($r) => array_values($r)[0], db_query('SHOW TABLES')));")
    return json.loads(run([php(), '-r', code, str(config)], ROOT, 'listing the tables'))


def clear(config):
    """Drop every table of the disposable resurrection_test database (database() refused any other)."""
    code = ("require 'lib/dbwrapper_pdo.php'; require $argv[1]; if ($DB_NAME !== 'resurrection_test') exit(2);"
            " db_connect($DB_HOST,$DB_USER,$DB_PASS); db_select_db($DB_NAME); db_query('SET FOREIGN_KEY_CHECKS=0');"
            " foreach (db_query('SHOW TABLES') as $r) db_query('DROP TABLE ' . db_identifier(array_values($r)[0]));")
    run([php(), '-r', code, str(config)], ROOT, 'clearing the test database')


def snapshot(config):
    return json.loads(run([php(), 'tests/support/database_snapshot.php', '--normalized', str(config)], ROOT, 'the snapshot'))


def differences(upgraded, fresh):
    found = []
    for table in sorted(set(upgraded['tables']) | set(fresh['tables'])):
        if upgraded['tables'].get(table) != fresh['tables'].get(table):
            found.append(f'table {table}: definition differs' if table in upgraded['tables'] and table in fresh['tables']
                         else f'table {table}: only in the {"upgraded" if table in upgraded["tables"] else "fresh"} database')
        elif upgraded['rows'].get(table) != fresh['rows'].get(table):
            only_upgraded = sorted(set(upgraded['rows'][table]) - set(fresh['rows'][table]))
            only_fresh = sorted(set(fresh['rows'][table]) - set(upgraded['rows'][table]))
            found.append(f'table {table}: rows differ; only upgraded: {only_upgraded[:3]}; only fresh: {only_fresh[:3]}')
    return found


def check(release, head):
    """Install release, upgrade it to the working tree at head, and compare with a fresh install of it."""
    settings = database()
    with tempfile.TemporaryDirectory(prefix='resurrection-release-check-') as scratch:
        scratch = Path(scratch)
        config = scratch / 'dbconnect.php'
        encoded = base64.b64encode(json.dumps(settings).encode()).decode()
        config.write_text("<?php extract(json_decode(base64_decode('" + encoded + "'),true), EXTR_SKIP);\n")
        config.chmod(0o600)
        if tables(config):
            raise SystemExit('Release upgrade check: the test database must start empty.')
        tree = scratch / 'release'
        tree.mkdir()
        archive = scratch / 'release.tar'
        with archive.open('wb') as out:
            subprocess.run(['git', '-C', str(ROOT), 'archive', '--format=tar', release], stdout=out, check=True)
        with tarfile.open(archive) as bundle:
            bundle.extractall(tree, filter='data')
        try:
            run([php(), 'scripts/install.php', str(config)], tree, f'installing {release}', ADMIN)
            report = run([php(), 'scripts/upgrade.php', '--backup-confirmed', str(config)], ROOT, f'upgrading {release} to {head}')
            if not report.rstrip('\n').endswith('The database is current.') and not report.startswith('The database is current:'):
                raise SystemExit('Release upgrade check failed: the upgrade did not report a current database.\n' + report)
            upgraded = snapshot(config)
            clear(config)
            run([php(), 'scripts/install.php', str(config)], ROOT, f'installing {head} fresh', ADMIN)
            fresh = snapshot(config)
        finally:
            clear(config)
        found = differences(upgraded, fresh)
        if found:
            raise SystemExit(f'Release upgrade check failed: {release} upgraded to {head} differs from a fresh install of {head}:\n- ' + '\n- '.join(found))
        print(f'Release upgrade check: {release} installed, upgraded to {head}, and the same schema and data as a fresh install '
              f'({len(fresh["tables"])} tables). Upgrade report:\n{report}', end='')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('--plan', action='store_true', help='print what the check would do and change nothing')
    parser.add_argument('--release-ref', help='treat this ref as the release (the self-test; no tag is involved)')
    parser.add_argument('--repo', default=str(ROOT), help=argparse.SUPPRESS)
    options = parser.parse_args(argv)
    head = git(options.repo, 'rev-parse', '--short', 'HEAD')
    release = options.release_ref or choose_release(options.repo)
    if release is None:
        print(NO_RELEASE)
        return 0
    if options.plan:
        print(f'Release upgrade check: would install {release}, upgrade it to {head} with scripts/upgrade.php, and compare it with a fresh install of {head}.')
        return 0
    check(release, head)
    return 0


if __name__ == '__main__':
    sys.exit(main())
