# Test-only upgrade steps

Step directories that `tests/unit/UpgradeTest.php` passes to the upgrade runner. They are never in `upgrades/`, so no real database ever records them.

- `equivalence/`: three steps that each make a change a fresh install at HEAD already has (an index, a column, a seed row). The test undoes them on an installed database, upgrades it, and compares the whole database with how it was.
- `order/`: steps that log their numbers, to prove the order they run in.
- `partial/`: a step that fails between its two changes while a test setting asks it to, to prove a re-run after a partial failure.
- `out-of-order/`: a catalog whose second step a test records without the first.
