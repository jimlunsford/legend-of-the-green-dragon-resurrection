"""Resurrection's one version number: src/Version.php sets it, project.json and docs/VERSIONING.md must say the same.

Static: runs wherever the suite runs, with or without a supported PHP (Baseline integrity too).
"""
import json
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
# The SemVer 2.0.0 grammar (semver.org).
SEMVER = re.compile(r'^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
                    r'(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'
                    r'(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$')
# This project's forms of it (docs/VERSIONING.md): a release, development toward it, or a test build.
PROJECT = re.compile(r'^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-dev|-rc\.[1-9]\d*)?$')
CODE = re.compile(r"const string CURRENT = '([^']*)';")
DOC = re.compile(r'^Current version: `([^`]*)`', re.M)


def problems(code, project, doc):
    """What is wrong with the three texts' version: an empty list when they agree on a valid version."""
    found = []
    versions = {}
    for name, text, pattern in [('src/Version.php', code, CODE), ('docs/VERSIONING.md', doc, DOC)]:
        matches = pattern.findall(text)
        if len(matches) != 1:
            found.append(f'{name} must state the version exactly once')
        else:
            versions[name] = matches[0]
    versions['project.json'] = json.loads(project).get('version')
    if len(set(map(str, versions.values()))) != 1:
        found.append('The versions disagree: ' + ', '.join(f'{name} {value!r}' for name, value in versions.items()))
    for name, value in versions.items():
        if not isinstance(value, str) or not SEMVER.match(value):
            found.append(f'{name}: {value!r} is not a valid semantic version')
        elif not PROJECT.match(value):
            found.append(f'{name}: {value!r} is not MAJOR.MINOR.PATCH, -dev or -rc.N')
    return found


def current():
    return [(ROOT / 'src' / 'Version.php').read_text(), (ROOT / 'project.json').read_text(), (ROOT / 'docs' / 'VERSIONING.md').read_text()]


class VersionTests(unittest.TestCase):
    def test_code_project_and_docs_agree_on_a_valid_version(self):
        self.assertEqual([], problems(*current()))
        self.assertEqual('1.0.0-dev', CODE.search(current()[0]).group(1))

    def test_a_disagreement_or_an_invalid_version_fails(self):
        code, project, doc = current()
        version = CODE.search(code).group(1)
        meta = json.loads(project)
        def with_project(value):
            return json.dumps({**meta, 'version': value})
        cases = {
            'project.json differs': (code, with_project('1.0.1'), doc),
            'project.json unset': (code, with_project(None), doc),
            'the doc differs': (code, project, doc.replace(f'Current version: `{version}`', 'Current version: `1.0.0-rc.1`')),
            'the doc does not say': (code, project, doc.replace(f'Current version: `{version}`', 'The version is in src/Version.php.')),
            'the code differs': (code.replace(f"'{version}'", "'1.1.0'"), project, doc),
        }
        for bad in ['1.0', 'v1.0.0', '01.0.0', '1.0.0-rc.0', '1.0.0-beta', '1.0.0+build.1', '1.1.2 Dragonprime Edition']:
            cases['invalid ' + bad] = (code.replace(f"'{version}'", f"'{bad}'"), with_project(bad), doc.replace(f'Current version: `{version}`', f'Current version: `{bad}`'))
        for name, texts in cases.items():
            with self.subTest(name):
                self.assertNotEqual([], problems(*texts))
        # The project's forms all pass, and only those.
        for good in ['1.0.0', '1.0.0-dev', '1.0.0-rc.1', '1.0.0-rc.12', '2.3.4']:
            with self.subTest(good):
                self.assertEqual([], problems(code.replace(f"'{version}'", f"'{good}'"), with_project(good), doc.replace(f'Current version: `{version}`', f'Current version: `{good}`')))

    def test_the_base_version_stays_1_1_2(self):
        # The about page's "LoGD version" line and the footer's credit read it; 1.1.2's common.php set this string.
        self.assertIn("const string BASE = '1.1.2 Dragonprime Edition';", current()[0])
        common = (ROOT / 'common.php').read_text()
        self.assertIn('$logd_version = \\Resurrection\\Version::BASE;', common)
        # The version shown is no longer the install marker: common.php does not compare it with a stored setting.
        self.assertNotRegex(common, r'getsetting\(\s*["\']installer_version')
        self.assertNotRegex(common, r'\$logd_version\s*[!=]=')


if __name__ == '__main__':
    unittest.main()
