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

if __name__ == '__main__':
    unittest.main()
