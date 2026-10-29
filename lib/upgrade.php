<?php
/**
 * The upgrade system (docs/MODERN-INSTALLATION.md, "Upgrading"; docs/VERSIONING.md).
 *
 * A database's schema state is the list of upgrade steps applied to it, kept in its upgrade_steps table, plus the
 * stored version of each installed bundled module. The code's state is the step files in upgrades/ and each bundled
 * module's version. The two decide whether the game can run (common.php) and what scripts/upgrade.php does. The
 * version the game shows (src/Version.php) plays no part.
 *
 * Callers load lib/dbwrapper_pdo.php, lib/settings.php and lib/modules.php first (common.php or scripts/upgrade.php).
 */
require_once __DIR__ . '/../src/Upgrade/StepCatalog.php';
require_once __DIR__ . '/../src/Upgrade/UpgradeRefused.php';
require_once __DIR__ . '/../src/Upgrade/UpgradeStopped.php';
require_once __DIR__ . '/installer/bundled_modules.php';
require_once __DIR__ . '/maintenance.php';

use Resurrection\Upgrade\StepCatalog;
use Resurrection\Upgrade\UpgradeRefused;
use Resurrection\Upgrade\UpgradeStopped;

/** The game's upgrade steps. */
function resurrection_upgrade_steps_directory(): string {
    return dirname(__DIR__) . '/upgrades';
}

/**
 * The applied-steps ledger: one row per applied step, its ID and when it was recorded (UTC). A fresh install creates it
 * with every step that exists then; scripts/upgrade.php adds a row after each step succeeds.
 * @return array<string, array<string, string>>
 */
function resurrection_upgrade_ledger_descriptor(): array {
    return [
        'step' => ['name' => 'step', 'type' => 'varchar(64)'],
        'applied' => ['name' => 'applied', 'type' => 'datetime'],
        'key-PRIMARY' => ['name' => 'PRIMARY', 'type' => 'primary key', 'unique' => '1', 'columns' => 'step'],
    ];
}

/** Record a step as applied. Only after it fully succeeded, or for a fresh install, which already has it. */
function resurrection_upgrade_record(string $step): void {
    db_query('INSERT INTO ' . db_identifier(db_prefix('upgrade_steps')) . ' (step,applied) VALUES (?,?)', true, [$step, gmdate('Y-m-d H:i:s')]);
}

/**
 * For common.php: whether the settings mark a completed Resurrection install, a 1.1.2 game, or neither. It reads the
 * loaded settings directly, because getsetting() would write a setting it does not find.
 */
function resurrection_web_install_state(): string {
    loadsettings();
    $settings = is_array($GLOBALS['settings'] ?? null) ? $GLOBALS['settings'] : [];
    if (($settings['resurrection_install'] ?? '') === 'complete') { return 'installed'; }
    // A 1.1.2 game: its installer's version marker, which a Resurrection install does not write (task 2 of Phase 4).
    if (str_starts_with((string)($settings['installer_version'] ?? ''), '1.')) { return 'unsupported-1.1.2'; }
    return 'not-installed';
}

/**
 * Where a completed install stands against the code.
 *
 * - current: every step is applied and every installed bundled module is at its version.
 * - behind: steps or module upgrades are pending, or the ledger does not exist yet (a development database installed
 *   before the upgrade system). scripts/upgrade.php brings it to current.
 * - ahead: the ledger has a step this code does not have, or an installed bundled module is newer than this code's.
 *   A newer version changed this database; this one refuses to run on it or change it (a downgrade).
 * - out-of-order: a step is not applied although a later one is. Steps are written to run in order, so this is
 *   refused rather than repaired.
 *
 * @param list<string> $catalog the code's step IDs, in order (StepCatalog::ids())
 * @return array{state: string, ledger: bool, applied: list<string>, pending: list<string>, unknown: list<string>, modules: array<string, string>, newer: array<string, string>}
 */
function resurrection_upgrade_state(array $catalog): array {
    $ledger = db_table_exists(db_prefix('upgrade_steps'));
    $applied = [];
    if ($ledger) {
        foreach (db_query('SELECT step FROM ' . db_identifier(db_prefix('upgrade_steps')) . ' ORDER BY step') as $row) { $applied[] = (string)$row['step']; }
    }
    $unknown = array_values(array_diff($applied, $catalog));
    $pending = array_values(array_diff($catalog, $applied));
    $known = array_values(array_intersect($catalog, $applied));
    $outOfOrder = $pending && $known && strcmp($pending[0], $known[count($known) - 1]) < 0;
    // Bundled modules: the stored version of each installed one against its file's (a module the operator uninstalled
    // has no row and nothing to upgrade).
    $versions = resurrection_bundled_module_versions();
    $older = $newer = [];
    $rows = db_query('SELECT modulename,version FROM ' . db_identifier(db_prefix('modules')) . ' WHERE modulename IN (' . implode(',', array_fill(0, count($versions), '?')) . ') ORDER BY modulename', true, array_keys($versions));
    foreach ($rows as $row) {
        $name = (string)$row['modulename'];
        $order = module_compare_versions((string)$row['version'], $versions[$name]);
        if ($order < 0) { $older[$name] = (string)$row['version']; }
        elseif ($order > 0) { $newer[$name] = (string)$row['version']; }
    }
    $state = match (true) {
        $unknown !== [] || $newer !== [] => 'ahead',
        $outOfOrder => 'out-of-order',
        !$ledger || $pending !== [] || $older !== [] => 'behind',
        default => 'current',
    };
    return ['state' => $state, 'ledger' => $ledger, 'applied' => $applied, 'pending' => $pending, 'unknown' => $unknown, 'modules' => $older, 'newer' => $newer];
}

/** For common.php: 'current', or why the game cannot run on this database. */
function resurrection_database_state(): string {
    $install = resurrection_web_install_state();
    if ($install !== 'installed') { return $install; }
    return resurrection_upgrade_state(StepCatalog::ids(resurrection_upgrade_steps_directory()))['state'];
}

/**
 * Take the install's and maintenance's advisory locks together, without waiting, for the whole upgrade: while it holds
 * them no second upgrade, no install and no cron.php run can start, and it cannot start while one of them runs. False
 * when either is taken; then it holds neither.
 */
function resurrection_upgrade_lock(): bool {
    $held = [];
    foreach ([resurrection_install_lock_name(), resurrection_maintenance_lock_name()] as $lock) {
        $rows = db_query('SELECT GET_LOCK(?, 0) AS acquired', true, [$lock]);
        if ((string)($rows[0]['acquired'] ?? '') !== '1') {
            foreach ($held as $name) { db_query('SELECT RELEASE_LOCK(?)', false, [$name]); }
            return false;
        }
        $held[] = $lock;
    }
    return true;
}

function resurrection_upgrade_unlock(): void {
    foreach ([resurrection_install_lock_name(), resurrection_maintenance_lock_name()] as $lock) { db_query('SELECT RELEASE_LOCK(?)', false, [$lock]); }
}

/**
 * Bring a completed install to the code's state, or say why not. The caller holds the upgrade lock.
 *
 * Every step file is read and checked before anything changes. With $apply false (a dry run) it reports what is
 * pending and changes nothing. Otherwise it creates the ledger if it is missing, applies each pending step in order and
 * records it once it succeeded, then runs each pending bundled module upgrade through the module lifecycle
 * (resurrection_upgrade_module()). The first failure stops it with UpgradeStopped: the database stays at the last good
 * step, and a new run continues from there. A refusal (UpgradeRefused) changes nothing.
 *
 * @param callable(string): void $report receives each line of the report
 * @return array{steps: list<string>, modules: list<string>, state: string}
 */
function resurrection_upgrade(string $directory, bool $apply, callable $report): array {
    $install = resurrection_install_state();
    if ($install === 'unsupported-1.1.2') {
        throw new UpgradeRefused('This database holds a Legend of the Green Dragon 1.1.2 game, which is unsupported: Resurrection does not upgrade or migrate a 1.1.2 database. The only path is a fresh install into an empty database. Nothing was changed.');
    }
    if ($install !== 'installed') {
        throw new UpgradeRefused('This database is not a completed Resurrection install, so there is nothing to upgrade. A new game is installed with php scripts/install.php into an empty database. Nothing was changed.');
    }
    $catalog = StepCatalog::ids($directory);
    $state = resurrection_upgrade_state($catalog);
    if ($state['state'] === 'ahead') {
        $newer = array_merge(
            $state['unknown'] ? ['upgrade steps this version does not have (' . implode(', ', $state['unknown']) . ')'] : [],
            array_map(static fn (string $module, string $stored): string => 'module ' . $module . ' ' . $stored . ', newer than this version\'s ' . resurrection_bundled_module_versions()[$module], array_keys($state['newer']), $state['newer']));
        throw new UpgradeRefused('This database is newer than this version of Resurrection: it has ' . implode('; ', $newer) . '. A downgrade is refused: run the newer version again, or restore the backup taken before it upgraded this database. Nothing was changed.');
    }
    if ($state['state'] === 'out-of-order') {
        $known = array_values(array_intersect($catalog, $state['applied']));
        throw new UpgradeRefused('The database\'s upgrade record is out of order: step ' . $state['pending'][0] . ' is not applied, but the later step ' . $known[count($known) - 1] . ' is. Steps only run in order, so this is not repaired: restore the backup taken before the last upgrade. Nothing was changed.');
    }
    if ($state['state'] === 'current') {
        $report('The database is current: ' . count($catalog) . ' of ' . count($catalog) . ' upgrade steps applied, and every installed bundled module is at its version. Nothing was changed.');
        return ['steps' => [], 'modules' => [], 'state' => 'current'];
    }
    // Read and check every pending step before anything changes.
    $steps = [];
    foreach ($state['pending'] as $id) { $steps[$id] = StepCatalog::load($directory, $id); }
    $versions = resurrection_bundled_module_versions();
    $report('Pending:');
    if (!$state['ledger']) { $report('- create the upgrade ledger (the upgrade_steps table)'); }
    foreach ($steps as $id => $step) { $report('- step ' . $id . ': ' . $step['description']); }
    foreach ($state['modules'] as $module => $stored) { $report('- module ' . $module . ': upgrade from ' . $stored . ' to ' . $versions[$module]); }
    if (!$apply) {
        $report('Dry run: nothing was changed.');
        return ['steps' => [], 'modules' => [], 'state' => 'behind'];
    }
    $done = $upgraded = [];
    $last = $state['applied'] ? $state['applied'][count($state['applied']) - 1] : null;
    $stop = static function (string $what, ?Throwable $error) use (&$last): never {
        // A fixed source location and the database error's code, never SQL, values or configuration.
        $context = (string)($GLOBALS['dbinfo']['error_context'] ?? '');
        $at = $error ? ' (' . basename($error->getFile()) . ':' . $error->getLine() . ($context !== '' ? ', ' . $context : '') . ')' : '';
        throw new UpgradeStopped('Upgrade stopped: ' . $what . ' failed' . $at . ' and was not recorded. The database is at the last good step: '
            . ($last ?? 'none applied yet') . '. Nothing after the failure ran. Fix the cause and run the upgrade again: recorded steps are skipped, and every step checks for its own changes before it makes them. If the cause cannot be fixed, restore the backup taken before this upgrade.');
    };
    $report('Applying:');
    if (!$state['ledger']) {
        $GLOBALS['dbinfo']['error_context'] = '';
        try { db_query(table_create_from_descriptor(db_prefix('upgrade_steps'), resurrection_upgrade_ledger_descriptor())); }
        catch (Throwable $error) { $stop('creating the upgrade ledger', $error); }
        $report('- created the upgrade ledger (the upgrade_steps table)');
    }
    foreach ($steps as $id => $step) {
        $GLOBALS['dbinfo']['error_context'] = '';
        try {
            ($step['apply'])();
            resurrection_upgrade_record($id);
        } catch (Throwable $error) { $stop('step ' . $id, $error); }
        $last = $id;
        $done[] = $id;
        $report('- applied and recorded step ' . $id);
    }
    // Module upgrades run after the steps, so a step a module needs is already there.
    global $session;
    $previous = $session ?? null;
    $session = ['loggedin' => false, 'user' => ['acctid' => 0, 'name' => 'Upgrade', 'superuser' => 0, 'loggedin' => false, 'prefs' => []]];
    try {
        foreach ($state['modules'] as $module => $stored) {
            $GLOBALS['dbinfo']['error_context'] = '';
            try { $ok = resurrection_upgrade_module($module); }
            catch (Throwable $error) { $stop('the upgrade of module ' . $module, $error); }
            if (!$ok) { $stop('the upgrade of module ' . $module, null); }
            $upgraded[] = $module;
            $report('- upgraded module ' . $module . ' from ' . $stored . ' to ' . $versions[$module]);
        }
    } finally { $session = $previous; }
    $GLOBALS['settings'] = null;
    $after = resurrection_upgrade_state($catalog)['state'];
    if ($after !== 'current') { throw new UpgradeStopped('Upgrade finished its steps, but the database is still ' . $after . '. Run php scripts/upgrade.php --dry-run to see what remains.'); }
    $report('Upgrade complete: applied ' . count($done) . ' upgrade step(s) and ' . count($upgraded) . ' module upgrade(s). The database is current.');
    return ['steps' => $done, 'modules' => $upgraded, 'state' => $after];
}
