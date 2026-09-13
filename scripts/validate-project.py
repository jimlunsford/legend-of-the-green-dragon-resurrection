#!/usr/bin/env python3
"""Validate foundation metadata and tracked-file hygiene without running PHP."""
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]

def require(condition, message):
    if not condition:
        raise ValueError(message)

def validate():
    meta = json.loads((ROOT / 'project.json').read_text())
    require(meta['schema_version'] == 1, 'Unsupported project metadata schema')
    require(meta['name'] == 'Legend of the Green Dragon - Resurrection', 'Project name mismatch')
    require(meta['repository'] == 'jimlunsford/legend-of-the-green-dragon-resurrection', 'Repository mismatch')
    require(meta['intended_visibility'] == 'public' and meta['default_branch'] == 'main', 'Repository policy mismatch')
    require(meta['version'] is None, 'Foundation does not assign a release version')
    require(meta['public_game_hosting_approved'] is False and meta['commercial_use_approved'] is False, 'Foundation approval flags must remain false')
    require(meta['archive_modules_imported'] == 0, 'Foundation excludes extra archive modules')
    base = json.loads((ROOT / 'docs/provenance/baseline.json').read_text())
    require(base['commit'] == 'bdc29df9bc344774b41e0ad7cae7f6ed2f7512e2', 'Core source SHA mismatch')
    require(base['tree'] == '4013a0ccc5227e87cd7a22de00b7c322d7aa237c' and base['files'] == 417, 'Baseline metadata mismatch')
    require(base['tag'] == meta['baseline_tag'] == 'historical-source-1.1.2', 'Baseline tag mismatch')
    required = ['README.md','PROVENANCE.md','SECURITY.md','CONTRIBUTING.md','LICENSE.txt']
    required += ['docs/'+n+'.md' for n in ['VERSIONING','LICENSING-AND-PROVENANCE','MODULE-POLICY','SECURITY-STATUS','COMPATIBILITY-STATUS','MODERNIZATION-PRINCIPLES','CHARACTERIZATION-PLAN','DESCENDANT-RESEARCH']]
    for name in required:
        require((ROOT/name).is_file(), 'Required document missing: '+name)
    tracked = subprocess.check_output(['git','-C',str(ROOT),'ls-files','-z']).split(b'\0')
    checked = 0
    for raw in tracked:
        if not raw:
            continue
        name = raw.decode(); p = PurePosixPath(name); b = (ROOT/name).read_bytes()
        require(p.name not in {'dbconnect.php','.env','prefixes.php'}, 'Generated configuration tracked: '+name)
        require(not p.name.startswith('sess_') and '.runtime' not in p.parts, 'Runtime state tracked: '+name)
        require(not re.search(r'\.(?:key|pem|sqlite3?|dump|sql\.(?:gz|bz2|xz))$',name,re.I), 'Sensitive data artifact tracked: '+name)
        require(not (p.name.startswith('.env.') and p.name != '.env.example'), 'Environment secrets tracked: '+name)
        # Conservative credential signatures. This is a guardrail, not a secret-scanner certification.
        require(not re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',b), 'Private key signature: '+name)
        require(not re.search(rb'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b',b), 'GitHub token signature: '+name)
        require(not re.search(rb'^\s*\$DB_PASS\s*=\s*[\'\"][^\'\"\r\n]+[\'\"]\s*;',b,re.M), 'Literal database credential: '+name)
        if name.endswith('.json'):
            json.loads(b)
        checked += 1
    return {'status':'PASS','tracked_files_checked':checked,'metadata_schema':1,'scope':'foundation integrity and hygiene, not application compatibility or security approval'}

if __name__ == '__main__':
    try:
        print(json.dumps(validate(),indent=2))
    except (ValueError,KeyError,OSError,subprocess.CalledProcessError) as exc:
        print('FAIL: '+str(exc),file=sys.stderr)
        sys.exit(1)
