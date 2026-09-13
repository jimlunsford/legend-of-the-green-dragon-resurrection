#!/usr/bin/env python3
"""Prepare exact historical bytes; fail closed without an isolated runtime.

This scaffold does not run an installer or claim a working historical baseline.
"""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
RUNTIME = HERE / '.runtime'
spec = importlib.util.spec_from_file_location('baseline', ROOT/'scripts/verify-historical-baseline.py')
baseline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(baseline)

def git(*args):
    return subprocess.check_output(['git','-C',str(ROOT),*args])

def preflight():
    baseline.verify(ROOT)
    blockers = []
    if not shutil.which('docker'):
        blockers.append('Docker CLI/container engine unavailable')
    else:
        for command, description in [(['docker','info'],'Docker engine unavailable'),(['docker','compose','version'],'Docker Compose unavailable')]:
            try:
                subprocess.run(command,check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=15)
            except (subprocess.SubprocessError,OSError):
                blockers.append(description)
    return {'status':'BLOCKED' if blockers else 'PREREQUISITES_AVAILABLE',
            'blockers':blockers,'runtime_tested':False,
            'note':'Prerequisites are not proof of installation or network isolation.'}

def validate_lock(path):
    data = json.loads(path.read_text())
    if data.get('schema_version') != 1:
        raise ValueError('Unsupported lock schema')
    for field in ('php_image','database_image'):
        value = data.get(field)
        if not isinstance(value,str) or not re.fullmatch(r'[a-z0-9][a-z0-9._/:\-]*@sha256:[0-9a-f]{64}',value):
            raise ValueError(field+' must be a reviewed image@sha256 digest, not a floating tag')
    return data

def prepare(lock):
    baseline.verify(ROOT)
    data = validate_lock(lock) if lock else None
    if RUNTIME.exists():
        raise ValueError('Runtime directory exists; preserve observations externally and clean up explicitly first')
    RUNTIME.mkdir(mode=0o700)
    source = RUNTIME/'source'
    source.mkdir(mode=0o755)
    # Parent is owner-only; containers need traverse permission on the bind source,
    # which is mounted directly by the engine, not via the parent path in-container.
    for line in (ROOT/'docs/provenance/historical-core-manifest.sha256').read_text().splitlines():
        digest, name = line.split('  ',1)
        path = source/name
        if Path(name).is_absolute() or '..' in Path(name).parts:
            raise ValueError('Unsafe baseline path')
        blob = git('show',baseline.COMMIT+':'+name)
        if hashlib.sha256(blob).hexdigest() != digest:
            raise ValueError('Export hash mismatch: '+name)
        path.parent.mkdir(parents=True,exist_ok=True)
        path.write_bytes(blob)
        path.chmod(0o644)
    if data:
        env = RUNTIME/'lab.env'
        # Synthetic, disposable values. Never print or upload this file.
        env.write_text('PHP_BASE='+data['php_image']+'\nDB_IMAGE='+data['database_image']+
                       '\nLAB_DB_PASSWORD='+secrets.token_hex(24)+
                       '\nLAB_DB_ROOT_PASSWORD='+secrets.token_hex(24)+'\n')
        env.chmod(0o600)
        shutil.copyfile(lock,RUNTIME/'runtime-lock.json')
    return {'status':'PREPARED','historical_files':417,'application_executed':False,
            'runtime_configuration_created':bool(data)}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command',choices=['preflight','prepare'])
    parser.add_argument('--lock',type=Path,help='Reviewed runtime lock; omit to export source only')
    args = parser.parse_args()
    try:
        result = preflight() if args.command == 'preflight' else prepare(args.lock)
        print(json.dumps(result,indent=2))
        sys.exit(2 if result['status']=='BLOCKED' else 0)
    except (ValueError,OSError,subprocess.SubprocessError) as exc:
        print('BLOCKED: '+str(exc),file=sys.stderr)
        sys.exit(2)
