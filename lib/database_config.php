<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
/**
 * The database configuration file, read for the services that run without common.php (Phase 6 task 5a): the install
 * (resurrection_install_run()) and the upgrade (resurrection_upgrade_run()). The commands read it at their top level
 * until then; the services read it here, inside a function, so the credentials never become globals.
 *
 * The file is loaded as the game loads it (it is PHP). DB_PREFIX, DB_USEDATACACHE and DB_DATACACHEPATH are set as
 * globals when the file sets them, as a top-level require did, because the database wrapper and the settings read them
 * from there. The credentials are returned to the caller, which connects and drops them.
 *
 * @return array{DB_HOST: string, DB_USER: string, DB_PASS: string, DB_NAME: string}
 */
function resurrection_database_config(string $path): array {
    $values = (static function (string $__path): array {
        require $__path;
        unset($__path);
        return get_defined_vars();
    })($path);
    foreach (['DB_PREFIX', 'DB_USEDATACACHE', 'DB_DATACACHEPATH'] as $global) {
        if (array_key_exists($global, $values)) { $GLOBALS[$global] = $values[$global]; }
    }
    if (!isset($values['DB_HOST'], $values['DB_USER'], $values['DB_PASS'], $values['DB_NAME']) ||
        !is_string($values['DB_HOST']) || !is_string($values['DB_USER']) || !is_string($values['DB_PASS']) || !is_string($values['DB_NAME'])) {
        throw new RuntimeException('Database configuration is incomplete.');
    }
    return ['DB_HOST' => $values['DB_HOST'], 'DB_USER' => $values['DB_USER'], 'DB_PASS' => $values['DB_PASS'], 'DB_NAME' => $values['DB_NAME']];
}

/**
 * Connect and select the configured database, or throw the commands' fixed message.
 *
 * @param array{DB_HOST: string, DB_USER: string, DB_PASS: string, DB_NAME: string} $database
 */
function resurrection_database_open(#[\SensitiveParameter] array $database): void {
    if (!db_connect($database['DB_HOST'], $database['DB_USER'], $database['DB_PASS']) || !db_select_db($database['DB_NAME'])) {
        throw new RuntimeException('Database connection failed.');
    }
}
