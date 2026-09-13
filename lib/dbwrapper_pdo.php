<?php
/** PDO transport behind the procedural LoGD database contract. */
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';

function db_connect($host, $user, $pass) {
    global $dbinfo;
    // Do not reuse a prior connection after an unsuccessful reconnect.
    $dbinfo['connection'] = null;
    $dbinfo['error'] = '';
    $dbinfo['affected_rows'] = 0;
    if (!is_string($host) || !preg_match('/\A([a-zA-Z0-9_.-]+)(?::([0-9]{1,5}))?\z/', $host, $match)) {
        $dbinfo['error'] = 'Invalid database host. Use a hostname with an optional TCP port.';
        return false;
    }
    $port = isset($match[2]) ? (int) $match[2] : 3306;
    if ($port < 1 || $port > 65535) {
        $dbinfo['error'] = 'Invalid database port.';
        return false;
    }
    try {
        $dbinfo['connection'] = new PDO('mysql:host=' . $match[1] . ';port=' . $port . ';charset=utf8mb4', $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            \Pdo\Mysql::ATTR_MULTI_STATEMENTS => false,
        ]);
        return true; // LINK is a legacy boolean marker, never the PDO object.
    } catch (PDOException $error) {
        $dbinfo['error'] = 'Database connection failed.';
        return false;
    }
}

function db_pconnect($host, $user, $pass) {
    // Persistent sessions can retain transaction/SQL-mode state. Use a fresh one.
    return db_connect($host, $user, $pass);
}

function db_identifier($name) {
    if (!is_string($name) || !preg_match('/\A[A-Za-z0-9_]+\z/', $name)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    return '`' . $name . '`';
}

function db_select_db($dbname) {
    return db_query('USE ' . db_identifier($dbname), false) !== false;
}

/** Values belong in $parameters. Identifiers must be separately validated. */
function db_query($sql, $die = true, array $parameters = []) {
    global $dbinfo;
    $dbinfo['error'] = '';
    $dbinfo['affected_rows'] = 0;
    if (!isset($dbinfo['connection'])) {
        if (defined('DB_NODB') && DB_NODB) { return []; }
        $dbinfo['error'] = 'Database connection is unavailable.';
        if (!$die) { return false; }
        throw new RuntimeException($dbinfo['error']);
    }
    $start = microtime(true);
    $dbinfo['queriesthishit'] = ($dbinfo['queriesthishit'] ?? 0) + 1;
    try {
        $statement = $dbinfo['connection']->prepare($sql);
        foreach ($parameters as $key => $value) {
            $position = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($position, $value, $type);
        }
        $statement->execute();
        $dbinfo['affected_rows'] = $statement->rowCount();
        if ($statement->columnCount() > 0) {
            // Buffered arrays preserve row counts and the cached-result API.
            return $statement->fetchAll();
        }
        return true;
    } catch (PDOException $error) {
        // Never render/log SQL, bound values, connection details, or the driver trace.
        $dbinfo['error'] = 'Database operation failed.';
        if (!$die) { return false; }
        throw new RuntimeException($dbinfo['error']);
    } finally {
        $dbinfo['querytime'] = ($dbinfo['querytime'] ?? 0) + microtime(true) - $start;
    }
}

function &db_query_cached($sql, $name, $duration = 900) {
    global $dbinfo;
    $data = datacache($name, $duration);
    if (is_array($data)) {
        reset($data);
        $dbinfo['affected_rows'] = -1;
        return $data;
    }
    $data = db_query($sql);
    if (!is_array($data)) { throw new LogicException('Only row queries may be cached.'); }
    updatedatacache($name, $data);
    reset($data);
    return $data;
}

function db_fetch_assoc(&$result) {
    if (!is_array($result)) { return false; }
    $item = resurrection_array_next($result);
    return $item === false ? false : $item[1];
}

function db_num_rows($result) { return is_array($result) ? count($result) : 0; }
function db_affected_rows($link = false) { return $GLOBALS['dbinfo']['affected_rows'] ?? 0; }
function db_insert_id() {
    return isset($GLOBALS['dbinfo']['connection']) ? (int) $GLOBALS['dbinfo']['connection']->lastInsertId() : -1;
}
function db_error($link = false) { return $GLOBALS['dbinfo']['error'] ?? ''; }
function db_get_server_version() {
    return isset($GLOBALS['dbinfo']['connection']) ? $GLOBALS['dbinfo']['connection']->getAttribute(PDO::ATTR_SERVER_VERSION) : '';
}
function db_free_result($result) { return true; } // Buffered arrays are released by their owners.
function db_table_exists($tablename) {
    return db_query('SELECT 1 FROM ' . db_identifier($tablename) . ' LIMIT 0', false) !== false;
}
function db_escape($value) {
    if (!isset($GLOBALS['dbinfo']['connection'])) { throw new RuntimeException('Database connection is unavailable.'); }
    return substr($GLOBALS['dbinfo']['connection']->quote($value), 1, -1);
}
function db_prefix($tablename, $force = false) {
    global $DB_PREFIX;
    $prefix = $force;
    if ($force === false) {
        $special_prefixes = [];
        if (file_exists('prefixes.php')) { require 'prefixes.php'; }
        $prefix = $special_prefixes[$tablename] ?? $DB_PREFIX ?? '';
    }
    $name = $prefix . $tablename;
    db_identifier($name);
    return $name;
}
