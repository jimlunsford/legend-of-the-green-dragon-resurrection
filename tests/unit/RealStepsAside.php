<?php
declare(strict_types=1);

namespace Resurrection\Tests;

/**
 * For a test that runs a fixture step directory in place of upgrades/ (Phase 6 task 13a, when upgrades/ got its first
 * real step). The installed test database records the real steps, which a fixture directory does not have, so it would
 * read as ahead of the fixture. setAsideRealSteps() takes the real steps' ledger rows out for the test, as a database
 * of the fixture's version would be, and tearDown() puts them back exactly as they were.
 */
trait RealStepsAside
{
    /** @var list<array<string, mixed>>|null */
    private ?array $realSteps = null;

    private function setAsideRealSteps(): void
    {
        $this->realSteps = db_query('SELECT step,applied FROM upgrade_steps ORDER BY step');
        foreach ($this->realSteps as $row) { db_query('DELETE FROM upgrade_steps WHERE step=?', true, [$row['step']]); }
        $GLOBALS['settings'] = null;
    }

    protected function tearDown(): void
    {
        $this->restoreRealSteps();
        parent::tearDown();
    }

    private function restoreRealSteps(): void
    {
        if ($this->realSteps !== null) {
            foreach ($this->realSteps as $row) {
                db_query('DELETE FROM upgrade_steps WHERE step=?', true, [$row['step']]);
                db_query('INSERT INTO upgrade_steps (step,applied) VALUES (?,?)', true, [$row['step'], $row['applied']]);
            }
            $this->realSteps = null;
            $GLOBALS['settings'] = null;
        }
    }
}
