"""The release upgrade check (scripts/release-upgrade-check.py) picks the release to upgrade from, or says there is none.

Each test builds a throwaway git repository in a temporary directory, with its own tags, and never touches the
project's tags. No database is needed: --plan stops before the install.
"""
import importlib.util
import io
from contextlib import redirect_stdout
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('release_upgrade_check', ROOT / 'scripts' / 'release-upgrade-check.py')
check = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(check)


class ReleaseUpgradeCheckTests(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='resurrection-release-tags-')
        self.repo = Path(self.scratch.name)
        self.git('init', '-q')

    def tearDown(self):
        self.scratch.cleanup()

    def git(self, *arguments):
        return subprocess.run(['git', '-C', str(self.repo), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
                               '-c', 'tag.gpgSign=false', '-c', 'commit.gpgSign=false', *arguments],
                              check=True, capture_output=True, text=True).stdout.strip()

    def commit(self, name):
        (self.repo / 'file.txt').write_text(name)
        self.git('add', 'file.txt')
        self.git('commit', '-q', '-m', name)
        return self.git('rev-parse', 'HEAD')

    def plan(self):
        out = io.StringIO()
        with redirect_stdout(out):
            self.assertEqual(0, check.main(['--plan', '--repo', str(self.repo)]))
        return out.getvalue()

    def test_no_release_tag_means_no_release_yet(self):
        first = self.commit('first')
        # The preservation marker, and tags that are not the versioning rule's release tags.
        self.git('tag', '-a', 'historical-source-1.1.2', '-m', 'Preserved 1.1.2 source', first)
        for tag in ['v1.0', '1.0.0', 'v1.0.0-beta', 'v1.0.0-rc.0', 'v01.0.0', 'v1.0.0-dev', 'release-1.0.0']:
            self.git('tag', tag, first)
        self.commit('second')
        self.assertIsNone(check.choose_release(self.repo))
        self.assertEqual(check.NO_RELEASE + '\n', self.plan())
        self.assertIn('no release yet', check.NO_RELEASE)

    def test_a_release_tag_switches_the_check_on(self):
        first = self.commit('first')
        self.git('tag', '-a', 'historical-source-1.1.2', '-m', 'Preserved 1.1.2 source', first)
        self.git('tag', 'v1.0.0-rc.1', first)
        self.commit('second')
        self.assertEqual('v1.0.0-rc.1', check.choose_release(self.repo))
        head = self.git('rev-parse', '--short', 'HEAD')
        self.assertEqual(f'Release upgrade check: would install v1.0.0-rc.1, upgrade it to {head} with scripts/upgrade.php, '
                         f'and compare it with a fresh install of {head}.\n', self.plan())
        # The highest release among the ancestors, by semantic version: rc.10 after rc.2, the release after its rcs.
        self.git('tag', 'v1.0.0-rc.10', first)
        self.git('tag', 'v1.0.0-rc.2', first)
        self.assertEqual('v1.0.0-rc.10', check.choose_release(self.repo))
        second = self.git('rev-parse', 'HEAD')
        self.git('tag', '-a', 'v1.0.0', '-m', 'Release', second)
        self.commit('third')
        self.assertEqual('v1.0.0', check.choose_release(self.repo))
        # A tag on the commit under test is not a release before it, and a tag off its history is not either.
        self.git('tag', 'v1.0.1', 'HEAD')
        self.assertEqual('v1.0.0', check.choose_release(self.repo))
        self.git('checkout', '-q', '-b', 'other', first)
        self.commit('elsewhere')
        self.git('tag', 'v9.0.0', 'HEAD')
        self.git('checkout', '-q', '-')
        self.assertEqual('v1.0.0', check.choose_release(self.repo))

    def test_the_project_check_matches_its_tags(self):
        # The project itself: no release tag yet means "no release yet"; once one exists the plan names it.
        release = check.choose_release(ROOT)
        out = io.StringIO()
        with redirect_stdout(out):
            self.assertEqual(0, check.main(['--plan']))
        if release is None:
            self.assertEqual(check.NO_RELEASE + '\n', out.getvalue())
        else:
            self.assertIn(f'would install {release}, upgrade it to ', out.getvalue())

    def test_modern_core_runs_the_check_before_the_php_tests(self):
        workflow = (ROOT / '.github' / 'workflows' / 'modern-core-leg.yml').read_text()
        checks = workflow[workflow.index('  checks:'):workflow.index('  http:')]
        self.assertLess(checks.index('python3 scripts/release-upgrade-check.py\n'), checks.index('- run: composer test'))
        self.assertLess(checks.index('python3 scripts/release-upgrade-check.py --release-ref HEAD'), checks.index('- run: composer test'))


if __name__ == '__main__':
    unittest.main()
