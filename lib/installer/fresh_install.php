<?php
/** The supported fresh-install path. Never modifies an existing database. */
require_once __DIR__ . '/../../src/Security/Passwords.php';
require_once __DIR__ . '/../../src/Installation/InstallRefused.php';
require_once __DIR__ . '/../all_tables.php';
require_once __DIR__ . '/../tabledescriptor.php';
require_once __DIR__ . '/../accounts.php';
require_once __DIR__ . '/bundled_modules.php';
require_once __DIR__ . '/../upgrade.php';

/** The install's advisory lock for this database. The upgrade command takes it too (lib/upgrade.php). */
function resurrection_install_lock_name(): string {
    $database = $GLOBALS['dbinfo']['connection']->query('SELECT DATABASE()')->fetchColumn();
    return 'resurrection-install-' . substr(hash('sha256', (string)$database), 0, 32);
}

function resurrection_install_state(): string {
    $tables = db_query('SHOW TABLES');
    if (db_num_rows($tables) === 0) { return 'empty'; }
    if (db_table_exists(db_prefix('settings'))) {
        $columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('settings'))), 'Field');
        if (!in_array('setting', $columns, true) || !in_array('value', $columns, true)) { return 'populated'; }
        $result = db_query('SELECT setting,value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting IN (?,?)', false, ['resurrection_install', 'installer_version']);
        $state = array_column($result, 'value', 'setting');
        if (($state['resurrection_install'] ?? '') === 'complete') { return 'installed'; }
        if (isset($state['installer_version']) && str_starts_with($state['installer_version'], '1.') && db_table_exists(db_prefix('accounts'))) {
            $columns = array_column(db_query('SHOW COLUMNS FROM ' . db_identifier(db_prefix('accounts'))), 'Field');
            if (!array_diff(['acctid', 'login', 'password'], $columns)) { return 'unsupported-1.1.2'; }
        }
    }
    return 'populated';
}

/** Refuse an install into a database that is not empty, with the reason; nothing has been changed. */
function resurrection_refuse_install_state(string $state): void {
    if ($state === 'empty') { return; }
    throw new \Resurrection\Installation\InstallRefused(match ($state) {
        'installed' => 'Installation is complete. Fresh installation is locked. Nothing was changed.',
        // No 1.1.2 game is upgraded or migrated (ROADMAP.md, Phase 4 decisions).
        'unsupported-1.1.2' => 'This database holds a Legend of the Green Dragon 1.1.2 game, which is unsupported: Resurrection does not upgrade or migrate a 1.1.2 database. The only path is a fresh install into an empty database. Nothing was changed.',
        default => 'The database is not empty. A fresh install needs an empty database. Nothing was changed.',
    });
}

/**
 * The modules an install choice activates: "recommended" (the default of scripts/install.php) or "none".
 * @return list<string>
 */
function resurrection_install_module_plan(string $choice): array {
    return match ($choice) {
        'recommended' => resurrection_recommended_modules(),
        'none' => [],
        default => throw new \Resurrection\Installation\InstallRefused('Unknown module choice. Use --modules=recommended or --modules=none.'),
    };
}

/** @return array<string, array<string, array<string, mixed>>> */
function resurrection_fresh_schema(): array {
    $tables = get_all_tables();
    foreach ($tables as $table => &$columns) {
        unset($columns['RequireMyISAM']);
        foreach ($columns as $name => &$column) {
            if (str_contains($column['type'], 'key')) { continue; }
            if (in_array($column['type'], ['date', 'datetime'], true)) {
                // Dates mean "not yet" when NULL. News dates are part of a PK,
                // so every news insertion must supply its actual publication date.
                if (str_starts_with($column['default'] ?? '', '0000-')) {
                    unset($column['default']);
                    if ($table !== 'news' || $name !== 'newsdate') { $column['null'] = true; }
                }
            } elseif (!isset($column['default']) && !($column['null'] ?? false) && !isset($column['extra'])) {
                // Explicitly retain the historical empty-string/zero value semantics,
                // without depending on the database's permissive implicit defaults.
                $column['default'] = preg_match('/(?:int|double|float|decimal)/', $column['type']) ? '0' : '';
            }
        }
        unset($column);
    }
    unset($columns);
    $tables['accounts']['password']['type'] = 'varchar(255)';
    $tables['accounts']['authversion'] = ['name' => 'authversion', 'type' => 'int unsigned', 'default' => '0'];
    $tables['accounts']['lastnewday'] = ['name' => 'lastnewday', 'type' => 'varchar(10)', 'default' => ''];
    $tables['accounts']['key-login']['type'] = 'unique key';
    $tables['accounts']['superuser']['default'] = '0';
    // Resurrection's applied upgrade steps (lib/upgrade.php).
    $tables['upgrade_steps'] = resurrection_upgrade_ledger_descriptor();
    return $tables;
}

/**
 * @param list<string> $activate installed bundled modules to activate (resurrection_install_module_plan())
 * @return array{tables: int, modules_installed: int, modules_active: int, administrator: int, state: string}
 */
function resurrection_fresh_install(string $login, #[\SensitiveParameter] string $password, string $email, array $activate): array {
    // Validate credentials and the module plan before any DDL, without logging them.
    resurrection_validate_account($login, $email);
    if (array_diff($activate, resurrection_bundled_modules()) || count(array_unique($activate)) !== count($activate)) {
        throw new InvalidArgumentException('Only bundled modules, each once, can be activated.');
    }
    $hash = \Resurrection\Security\Passwords::hash($password);
    // The steps that exist now are part of this install's schema; a step file that cannot be read refuses it first.
    $steps = \Resurrection\Upgrade\StepCatalog::ids(resurrection_upgrade_steps_directory());
    $db = $GLOBALS['dbinfo']['connection'];
    $lock = resurrection_install_lock_name();
    $result = db_query('SELECT GET_LOCK(?, 0) AS acquired', true, [$lock]);
    if (db_fetch_assoc($result)['acquired'] !== '1') { throw new RuntimeException('Another installation is in progress.'); }
    try {
        resurrection_refuse_install_state(resurrection_install_state());
        $result = db_query('SELECT @@sql_mode AS modes');
        $modes = db_fetch_assoc($result)['modes'];
        if (!str_contains($modes, 'STRICT_TRANS_TABLES') && !str_contains($modes, 'STRICT_ALL_TABLES')) {
            throw new RuntimeException('Strict SQL mode is required.');
        }
        foreach (resurrection_fresh_schema() as $table => $descriptor) {
            $GLOBALS['fresh_install_phase'] = 'schema:' . $table;
            db_query(table_create_from_descriptor(db_prefix($table), $descriptor));
        }
        // Execute the preserved chronological fresh seed path, including its
        // final oldcreatureexp cleanup. Never use it as an upgrade mechanism.
        $sql_upgrade_statements = require __DIR__ . '/installer_sqlstatements.php';
        if (!$sql_upgrade_statements) { throw new RuntimeException('Fresh seed data is missing.'); }
        foreach ($sql_upgrade_statements as $version => $statements) {
            foreach ($statements as $index => $sql) {
                if (str_starts_with($sql, '1|')) { continue; }
                $GLOBALS['fresh_install_phase'] = 'seed:' . $version . ':' . $index;
                db_query($sql);
            }
        }
        $default_settings = require __DIR__ . '/installer_default_settings.php';
        if (!$default_settings) { throw new RuntimeException('Default settings are missing.'); }
        $GLOBALS['fresh_install_phase'] = 'settings';
        foreach ($default_settings as $name => $value) {
            db_query('INSERT IGNORE INTO ' . db_prefix('settings') . ' (setting,value) VALUES (?,?)', true, [$name, (string)$value]);
        }
        $GLOBALS['settings'] = null;
        savesetting('charset', 'UTF-8');
        savesetting('newdaycron', 1);
        savesetting('serverlanguages', 'en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian');
        // Install only the fixed 24 shipped modules, all inactive until the final transaction activates the plan.
        resurrection_install_bundled_modules();
        // No external mail or payments are required by a fresh installation.
        // The activations, the administrator and the completion marker commit together or not at all: a failure
        // leaves every module inactive and the install not complete.
        $db->beginTransaction();
        resurrection_activate_installed_modules($activate);
        $GLOBALS['fresh_install_phase'] = 'administrator';
        $admin = resurrection_insert_account($login, $hash, $email, SU_MEGAUSER | SU_EDIT_USERS | SU_EDIT_CONFIG | SU_MANAGE_MODULES | SU_EDIT_COMMENTS);
        // 1.1.2's installer_version setting is no longer written: it was the version shown, used as the install
        // marker. The completion marker and the applied steps are the database's state (lib/upgrade.php).
        foreach ($steps as $step) { resurrection_upgrade_record($step); }
        savesetting('resurrection_install', 'complete');
        $db->commit();
        $active = db_query('SELECT COUNT(*) AS n FROM ' . db_prefix('modules') . ' WHERE active=1');
        return ['tables' => db_num_rows(db_query('SHOW TABLES')), 'modules_installed' => count(resurrection_bundled_modules()), 'modules_active' => (int)db_fetch_assoc($active)['n'], 'administrator' => $admin, 'state' => resurrection_install_state()];
    } finally {
        if ($db->inTransaction()) { $db->rollBack(); }
        db_query('SELECT RELEASE_LOCK(?)', false, [$lock]);
    }
}
