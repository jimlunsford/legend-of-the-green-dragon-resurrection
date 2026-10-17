#!/usr/bin/env python3
"""Deterministically split the tooling suite into CI shards that together run every test once."""
import argparse
import json
from pathlib import Path
import statistics
import sys
import time
import unittest

ROOT = Path(__file__).resolve().parents[1]
TOOLING = ROOT / 'tests' / 'tooling'
# Per-test seconds from PHP 8.5 / MySQL 8.4 shard runs; refresh from the shards' printed durations.
DURATIONS = TOOLING / 'shard-durations.json'
WEB_MODULE = 'test_web_application'
# Creates the WebPlayer account that every later web test logs in as.
ACCOUNT_SETUP = WEB_MODULE + '.WebApplicationTests.test_create_login_rotate_render_logout'
DEFAULT_SHARDS = 4

def discover():
    """Test ids in exactly the order `python3 -m unittest discover -s tests/tooling` runs them."""
    def flatten(suite):
        for item in suite:
            if isinstance(item, unittest.TestSuite):
                yield from flatten(item)
            else:
                yield item
    sys.path.insert(0, str(TOOLING))
    tests = list(flatten(unittest.defaultTestLoader.discover(str(TOOLING), top_level_dir=str(TOOLING))))
    broken = [test.id() for test in tests if test.id().startswith('unittest.loader.')]
    if broken:
        raise SystemExit('Tooling discovery failed: ' + ', '.join(broken))
    return tests

def weights(ids):
    """Measured seconds per test (shard-durations.json); a test missing from it costs the median.

    The table only balances shards: a stale or missing entry can never drop or duplicate a test.
    """
    measured = json.loads(DURATIONS.read_text())
    default = statistics.median(measured.values())
    return {test: measured.get(test, default) for test in ids}

def assign(ids, shards, cost=None):
    """Every id in exactly one shard, each shard in discovery order.

    Account setup is assigned to shard 1; every other shard with web tests replays it as a
    prerequisite, and run_order() runs it before every other web test. The rest are placed
    heaviest first on the least-loaded shard (ties go to the lower shard) so the split is
    reproducible.
    """
    if shards < 1:
        raise ValueError('At least one shard is required')
    cost = cost or weights(ids)
    order = {test: index for index, test in enumerate(ids)}
    pinned = [ACCOUNT_SETUP] if ACCOUNT_SETUP in ids else []
    plan = [[] for _ in range(shards)]
    load = [0] * shards
    plan[0] += pinned
    load[0] += sum(cost[test] for test in pinned)
    for test in sorted((t for t in ids if t not in pinned), key=lambda t: (-cost[t], order[t])):
        target = min(range(shards), key=lambda index: (load[index], index))
        plan[target].append(test)
        load[target] += cost[test]
    return [sorted(shard, key=order.__getitem__) for shard in plan]

def validate(plan, ids):
    """Fail loudly unless the shards partition the discovered list exactly."""
    seen = {}
    problems = []
    for index, shard in enumerate(plan, 1):
        for test in shard:
            if test in seen:
                problems.append(f'{test} assigned to shards {seen[test]} and {index}')
            seen[test] = index
    problems += [f'{test} not discovered' for test in seen if test not in set(ids)]
    problems += [f'{test} unassigned' for test in ids if test not in seen]
    if problems:
        raise ValueError('Invalid shard assignment: ' + '; '.join(problems))

def run_order(shard, ids):
    """The exact order a shard runs: its tests plus prerequisites.

    Discovery sorts by name, so web tests that log in as WebPlayer can sort before the test that
    creates it. Non-web tests run first, then account setup, then every other web test, each in
    discovery order.
    """
    order = {test: index for index, test in enumerate(ids)}
    def rank(test):
        if not test.startswith(WEB_MODULE + '.'): return 0
        return 1 if test == ACCOUNT_SETUP else 2
    return sorted(prerequisites(shard) + shard, key=lambda test: (rank(test), order[test]))

def prerequisites(shard):
    """Account setup for a shard that runs web tests after it but does not own it."""
    needs = any(test.startswith(WEB_MODULE + '.') and test != ACCOUNT_SETUP for test in shard)
    return [ACCOUNT_SETUP] if needs and ACCOUNT_SETUP not in shard else []

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--shards', type=int, default=DEFAULT_SHARDS)
    parser.add_argument('--shard', type=int, help='1-based shard to run; omit to list every shard')
    args = parser.parse_args()
    tests = discover()
    ids = [test.id() for test in tests]
    cost = weights(ids)
    plan = assign(ids, args.shards, cost)
    validate(plan, ids)
    if args.shard is None:
        for index, shard in enumerate(plan, 1):
            print(f'Shard {index}/{args.shards}: {len(shard)} tests, weight {sum(cost[t] for t in shard):.1f}s, prerequisites {prerequisites(shard)}')
            for test in run_order(shard, ids):
                print('  ' + test + ('' if test in shard else '  (prerequisite)'))
        print(f'Total: {sum(map(len, plan))} assigned of {len(ids)} discovered')
        return 0
    if not 1 <= args.shard <= args.shards:
        raise SystemExit(f'--shard must be between 1 and {args.shards}')
    shard = plan[args.shard - 1]
    setup = prerequisites(shard)
    by_id = {test.id(): test for test in tests}
    suite = unittest.TestSuite(by_id[test] for test in run_order(shard, ids))
    print(f'Shard {args.shard}/{args.shards}: {len(shard)} assigned tests, prerequisites {setup}', flush=True)
    started = time.monotonic()
    runner = unittest.TextTestRunner(verbosity=2, failfast=True, **({'durations': 0} if sys.version_info >= (3, 12) else {}))
    result = runner.run(suite)
    elapsed = time.monotonic() - started
    print(f'Shard {args.shard}/{args.shards}: ran {result.testsRun} ({len(shard)} assigned + {len(setup)} prerequisite), '
          f'failures {len(result.failures)}, errors {len(result.errors)}, skipped {len(result.skipped)}, '
          f'Python duration {elapsed:.1f}s ({elapsed / 60:.1f} min)', flush=True)
    # A skipped class would silently drop coverage; every assigned test must actually run.
    complete = result.testsRun == suite.countTestCases() and not result.skipped
    return 0 if result.wasSuccessful() and complete else 1

if __name__ == '__main__':
    sys.exit(main())
