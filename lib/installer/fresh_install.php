<?php
/** The supported fresh-install path. Never modifies an existing database. */
require_once __DIR__ . '/../../src/Security/Passwords.php';
require_once __DIR__ . '/../all_tables.php';
require_once __DIR__ . '/../tabledescriptor.php';
require_once __DIR__ . '/../accounts.php';

function resurrection_install_state(): string {
    $tables = db_query('SHOW TABLES');
    if (db_num_rows($tables) === 0) { return 'empty'; }
    if (db_table_exists(db_prefix('settings'))) {
        $result = db_query('SELECT value FROM ' . db_identifier(db_prefix('settings')) . ' WHERE setting=?', false, ['resurrection_install']);
        $row = db_fetch_assoc($result);
        if ($row && $row['value'] === 'complete') { return 'installed'; }
    }
    return 'populated';
}

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
    $tables['accounts']['key-login']['type'] = 'unique key';
    $tables['accounts']['superuser']['default'] = '0';
    return $tables;
}

function resurrection_fresh_install(string $login, #[\SensitiveParameter] string $password, string $email = ''): array {
    // Validate credentials before any DDL, without logging them.
    resurrection_validate_account($login, $email);
    $hash = \Resurrection\Security\Passwords::hash($password);
    $db = $GLOBALS['dbinfo']['connection'];
    $database = $db->query('SELECT DATABASE()')->fetchColumn();
    $lock = 'resurrection-install-' . substr(hash('sha256', (string)$database), 0, 32);
    $result = db_query('SELECT GET_LOCK(?, 0) AS acquired', true, [$lock]);
    if (db_fetch_assoc($result)['acquired'] !== '1') { throw new RuntimeException('Another installation is in progress.'); }
    try {
        $state = resurrection_install_state();
        if ($state !== 'empty') {
            throw new RuntimeException($state === 'installed'
                ? 'Installation is complete. Fresh installation is locked.'
                : 'Database is populated. Fresh installation refused; upgrades require a separately verified migration.');
        }
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
        require __DIR__ . '/installer_sqlstatements.php';
        foreach ($sql_upgrade_statements as $version => $statements) {
            foreach ($statements as $index => $sql) {
                if (str_starts_with($sql, '1|')) { continue; }
                $GLOBALS['fresh_install_phase'] = 'seed:' . $version . ':' . $index;
                db_query($sql);
            }
        }
        require __DIR__ . '/installer_default_settings.php';
        $GLOBALS['fresh_install_phase'] = 'settings';
        foreach ($default_settings as $name => $value) {
            db_query('INSERT IGNORE INTO ' . db_prefix('settings') . ' (setting,value) VALUES (?,?)', true, [$name, (string)$value]);
        }
        $GLOBALS['settings'] = null;
        savesetting('charset', 'UTF-8');
        savesetting('newdaycron', 1);
        savesetting('serverlanguages', 'en,English,fr,Français,dk,Danish,de,Deutsch,es,Español,it,Italian');
        // No external mail or payments are required by a fresh installation.
        $db->beginTransaction();
        $GLOBALS['fresh_install_phase'] = 'administrator';
        $admin = resurrection_insert_account($login, $hash, $email, SU_MEGAUSER | SU_EDIT_USERS | SU_EDIT_CONFIG | SU_MANAGE_MODULES | SU_EDIT_COMMENTS);
        savesetting('installer_version', '1.1.2 Dragonprime Edition');
        savesetting('resurrection_install', 'complete');
        $db->commit();
        return ['tables' => db_num_rows(db_query('SHOW TABLES')), 'administrator' => $admin, 'state' => resurrection_install_state()];
    } finally {
        if ($db->inTransaction()) { $db->rollBack(); }
        db_query('SELECT RELEASE_LOCK(?)', false, [$lock]);
    }
}
