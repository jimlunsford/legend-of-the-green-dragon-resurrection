"""The Modern core HTTP shards together run every discovered tooling test exactly once."""
import importlib.util
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('tooling_shards', ROOT / 'scripts' / 'tooling-shards.py')
shards = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(shards)

class ToolingShardTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.ids = [test.id() for test in shards.discover()]

    def test_shards_partition_the_discovered_suite(self):
        self.assertIn(shards.ACCOUNT_SETUP, self.ids)
        self.assertIn(__name__.rsplit('.', 1)[-1] + '.ToolingShardTests.test_shards_partition_the_discovered_suite', self.ids)
        order = {test: index for index, test in enumerate(self.ids)}
        for count in range(1, 7):
            plan = shards.assign(self.ids, count)
            shards.validate(plan, self.ids)
            assigned = [test for shard in plan for test in shard]
            self.assertEqual(len(self.ids), len(assigned))
            self.assertEqual(set(self.ids), set(assigned))
            self.assertEqual(plan, [sorted(shard, key=order.__getitem__) for shard in plan])
            self.assertIn(shards.ACCOUNT_SETUP, plan[0])
            for shard in plan:
                web = [test for test in shard if test.startswith(shards.WEB_MODULE + '.')]
                after_setup = [test for test in web if order[test] > order[shards.ACCOUNT_SETUP]]
                # Web tests that log in as WebPlayer always follow its creation in their own shard.
                if after_setup:
                    self.assertTrue(shards.ACCOUNT_SETUP in shard or shards.prerequisites(shard) == [shards.ACCOUNT_SETUP])
        self.assertEqual(shards.assign(self.ids, 4), shards.assign(list(self.ids), 4))
        # Timing weights only balance; any weights still partition the suite.
        shards.validate(shards.assign(self.ids, 4, {test: 1 for test in self.ids}), self.ids)

    def test_account_setup_runs_before_every_other_web_test(self):
        # unittest discovery runs by name; web tests that log in as WebPlayer may sort before its creation.
        web = shards.WEB_MODULE + '.WebApplicationTests.'
        for count in range(1, 7):
            for index, shard in enumerate(shards.assign(self.ids, count), 1):
                order = shards.run_order(shard, self.ids)
                others = [test for test in order if test.startswith(web) and test != shards.ACCOUNT_SETUP]
                if others:
                    self.assertEqual(1, order.count(shards.ACCOUNT_SETUP), (count, index))
                    self.assertLess(order.index(shards.ACCOUNT_SETUP), min(order.index(test) for test in others), (count, index, order[:6]))

    def test_dropped_or_duplicated_tests_are_rejected(self):
        plan = shards.assign(self.ids, 4)
        dropped = [shard[:] for shard in plan]
        missing = dropped[-1].pop()
        with self.assertRaisesRegex(ValueError, re.escape(missing) + ' unassigned'):
            shards.validate(dropped, self.ids)
        duplicated = [shard[:] for shard in plan]
        duplicated[1].append(plan[0][0])
        with self.assertRaisesRegex(ValueError, 'assigned to shards 1 and 2'):
            shards.validate(duplicated, self.ids)
        with self.assertRaisesRegex(ValueError, 'not discovered'):
            shards.validate([plan[0] + ['test_missing.Missing.test_missing']] + plan[1:], self.ids)

    def test_workflow_runs_every_shard(self):
        workflow = (ROOT / '.github' / 'workflows' / 'modern-core-leg.yml').read_text()
        count = int(re.search(r'tooling-shards\.py --shards (\d+) --shard \$\{\{ matrix\.shard \}\}', workflow).group(1))
        listed = [int(n) for n in re.search(r'shard: \[([\d, ]+)\]', workflow).group(1).split(',')]
        self.assertEqual(shards.DEFAULT_SHARDS, count)
        self.assertEqual(list(range(1, count + 1)), listed)

    def test_http_jobs_install_and_run_their_shard_only(self):
        # Phase 6 task 9: the shards need only the installed game FreshInstallTest leaves (proved in
        # docs/PHASE6-TASK9-20261112.md), so an http job no longer runs the whole PHPUnit suite; checks still does.
        workflow = (ROOT / '.github' / 'workflows' / 'modern-core-leg.yml').read_text()
        jobs = dict(re.findall(r'(?ms)^  ([a-z]+):\n(.*?)(?=^  [a-z]+:\n|\Z)', workflow[workflow.index('\njobs:\n'):]))
        self.assertEqual(['checks', 'http', 'result'], list(jobs))
        runs = {name: re.findall(r'(?m)^      - run: (.+?) *$', job) for name, job in jobs.items()}
        self.assertIn('composer test', runs['checks'])
        self.assertNotIn('composer test', runs['http'])
        self.assertEqual(['composer install --no-interaction --prefer-dist --no-progress', 'vendor/bin/phpunit tests/unit/FreshInstallTest.php',
                          'python3 scripts/tooling-shards.py --shards 5 --shard ${{ matrix.shard }}'], runs['http'])
        self.assertIn('needs: [checks, http]', jobs['result'])

    def test_the_durations_table_is_each_tests_largest_time(self):
        # Phase 6 task 9: one run's times did not predict the next (each job's runner sets its speed), so the table is
        # the largest time each test took over every log it is refreshed from, a prerequisite's included.
        logs = ['2026-11-12T03:39:43.5568313Z 322.362s   test_slow (test_web_application.WebApplicationTests.test_slow)\n'
                '2026-11-12T03:39:43.5568313Z 0.04s      test_quick (test_a.A.test_quick)\n'
                '2026-11-12T03:39:43.5568313Z test_slow (test_web_application.WebApplicationTests.test_slow) ... ok\n',
                '9.412s     test_create_login_rotate_render_logout (test_web_application.WebApplicationTests.test_create_login_rotate_render_logout)\n'
                '292.7s   test_slow (test_web_application.WebApplicationTests.test_slow)\n',
                '2026-11-12T03:19:02Z 10.31s   test_create_login_rotate_render_logout (test_web_application.WebApplicationTests.test_create_login_rotate_render_logout)\n']
        self.assertEqual({'test_a.A.test_quick': 0.0, 'test_web_application.WebApplicationTests.test_create_login_rotate_render_logout': 10.3,
                          'test_web_application.WebApplicationTests.test_slow': 322.4}, shards.durations_from_logs(logs))
        with self.assertRaisesRegex(ValueError, 'No test durations'):
            shards.durations_from_logs(['Shard 1/5: ran 0'])

    def test_every_workflow_job_pins_its_runner_and_actions(self):
        # Phase 6 task 2 (S8): ubuntu-latest was about to move to Ubuntu 26, and the node20 actions were deprecated.
        # Every job names ubuntu-24.04 (or calls a local workflow whose jobs do), and every action is pinned by its full
        # commit SHA with the version in a comment.
        pinned = {'actions/checkout': '3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1',
                  'shivammathur/setup-php': 'eb7c497e18156a6bbabfef1d3a82760b9eda3962 # 2.40.0'}
        seen = set()
        workflows = sorted((ROOT / '.github' / 'workflows').glob('*.yml'))
        # Phase 6 task 15: release-cut.yml, run by hand to cut a test build (docs/RC-CUT-CHECKLIST.md).
        self.assertEqual(['baseline-integrity.yml', 'modern-core-leg.yml', 'modern-core.yml', 'release-cut.yml'],
                         [w.name for w in workflows])
        for path in workflows:
            text = path.read_text()
            jobs = re.split(r'(?m)^  (?=[a-z][a-z0-9_-]*:\s*$)', text[text.index('\njobs:\n'):])[1:]
            self.assertTrue(jobs, path.name)
            for job in jobs:
                name = job.split(':', 1)[0]
                runner = re.findall(r'(?m)^    runs-on: *(.+?) *$', job)
                local = re.findall(r'(?m)^    uses: \./\.github/workflows/[a-z-]+\.yml *$', job)
                self.assertTrue(runner == ['ubuntu-24.04'] or (not runner and len(local) == 1), f'{path.name} {name}: {runner}')
            self.assertNotIn('ubuntu-latest', text, path.name)
            for action, ref in re.findall(r'(?m)^ *(?:- )?uses: *([^.\s][^@\s]*)@(.+?) *$', text):
                self.assertRegex(ref, r'\A[0-9a-f]{40} # \S', f'{path.name}: {action}')
                self.assertIn(action, pinned, path.name)
                self.assertTrue(ref.startswith(pinned[action]), f'{path.name}: {action}@{ref}')
                seen.add(action)
        self.assertEqual(set(pinned), seen)

if __name__ == '__main__':
    unittest.main()
