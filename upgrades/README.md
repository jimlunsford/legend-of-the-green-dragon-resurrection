# Upgrade steps

Each file here is one numbered database upgrade step. `php scripts/upgrade.php` applies the steps a database does not have yet, in number order, and records each in the database's `upgrade_steps` table once it has fully succeeded. A fresh install records every step in this directory as applied, because its schema already has them. The design and its reasons are in `docs/PHASE4-TASK2-UPGRADE-SYSTEM-20261029.md`; operators follow `docs/MODERN-INSTALLATION.md`, "Upgrading".

There are no steps yet: no release exists, so no database needs one.

## Writing a step

- Name it `NNNN-what-it-does.php`: the next free four-digit number, then lower-case words joined by hyphens (at most 64 characters without `.php`). The name is the step's ID and is never changed or reused once the step is on `main`.
- The file only returns its definition. Including it, or requesting it over HTTP, changes nothing:

  ```php
  <?php
  return [
      'description' => 'Add the accounts.example column',
      'apply' => static function (): void {
          $columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');
          if (!in_array('example', $columns, true)) {
              db_query('ALTER TABLE ' . db_identifier(db_prefix('accounts')) . " ADD COLUMN example int NOT NULL DEFAULT '0'");
          }
      },
  ];
  ```

- **Check before change.** MySQL and MariaDB commit every table change (`CREATE`, `ALTER`, `DROP`, `RENAME`) the moment it runs, so a step that stops partway cannot be rolled back. The next upgrade runs the step again from the start, so each change must first check whether it is already there, as above. A step that only changes rows may use a transaction of its own.
- Change the fresh install in the same commit, so that a fresh install and an upgraded database end up the same (`lib/installer/fresh_install.php`, its seed data, or a bundled module). The equivalence test in `tests/unit/UpgradeTest.php` and the release upgrade check (`scripts/release-upgrade-check.py`) hold the two to that.
- A step throws to fail. It is then not recorded, nothing after it runs, and the command reports the last good step.
- Never edit a step that is on `main`; add a new one. A database that already ran a step will not run it again.
- A bundled module's own changes belong in its install function, as in 1.1.2: raising the version in the module's `getmoduleinfo()` makes the upgrade command run its install function again through the module lifecycle (`lib/upgrade.php`). Raise the same module's entry in `resurrection_bundled_module_versions()` (`lib/installer/bundled_modules.php`) in that commit; `tests/unit/UpgradeTest.php` fails if the two differ.
