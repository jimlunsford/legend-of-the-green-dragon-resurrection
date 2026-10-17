<?php
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/gamelog.php';

/** The status texts the servers return when OPTIMIZE TABLE finished (MySQL 8.4 and MariaDB 11.4). */
const RESURRECTION_OPTIMIZE_OK = ['ok', 'table is already up to date'];

/**
 * One table's OPTIMIZE TABLE result set. The server reports a failure as rows, not as an exception: an `Error` row
 * and a final `status` of "Operation failed". InnoDB answers with a note ("Table does not support optimize, doing
 * recreate + analyze instead") and status OK; an engine without OPTIMIZE answers with a note and no status row.
 * @param array<int, array<string, mixed>> $rows
 * @return array{0: 'done'|'note'|'failed', 1: list<string>} the outcome and the server's messages
 */
function resurrection_optimize_classify(array $rows): array {
    if ($rows === []) return ['failed', ['The server returned no result.']];
    $status = null;
    $errors = [];
    $notes = [];
    foreach ($rows as $row) {
        $type = strtolower(trim((string)($row['Msg_type'] ?? '')));
        $text = trim((string)($row['Msg_text'] ?? ''));
        if ($type === 'status') $status = $text;
        elseif ($type === 'error') $errors[] = $text;
        elseif (in_array($type, ['note', 'info', 'warning'], true)) $notes[] = $text;
        else $errors[] = 'Unrecognized result row.';
    }
    if ($status !== null && !in_array(strtolower($status), RESURRECTION_OPTIMIZE_OK, true)) $errors[] = $status;
    if ($errors) return ['failed', $errors];
    if ($status === null && $notes === []) return ['failed', ['The server returned no status.']];
    return $notes ? ['note', $notes] : ['done', []];
}

/**
 * The maintenance log line: how many tables completed, which carried a note (grouped by the note), and each failed
 * table with the server's messages. 1.1.2 logged "Optimized tables: <every table> in N seconds." whatever happened.
 * @param array<string, array{0: string, 1: list<string>}> $results
 */
function resurrection_optimize_report(array $results, float $seconds): string {
    $done = [];
    $noted = [];
    $failed = [];
    foreach ($results as $table => [$status, $messages]) {
        $table = (string)$table;
        if ($status === 'done') $done[] = $table;
        elseif ($status === 'note') $noted[implode('; ', $messages)][] = $table;
        else $failed[] = $table . ' (' . implode('; ', $messages) . ')';
    }
    $completed = count($results) - count($failed);
    $message = "Optimized tables: $completed of " . count($results) . " completed in $seconds seconds.";
    if ($done) $message .= "\nCompleted: " . join(', ', $done);
    foreach ($noted as $note => $tables) $message .= "\nCompleted with the note \"$note\": " . join(', ', $tables);
    if ($failed) {
        $message .= "\nFailed: " . count($failed) . "\n" . join("\n", $failed);
        $message .= "\nThe optimization was not recorded as run, so the next maintenance run tries again.";
    }
    return $message;
}

/**
 * lib/newday/dbcleanup.php: OPTIMIZE TABLE on every table SHOW TABLES lists, as 1.1.2, under the maintenance lock.
 * Each result set is read and classified. `lastdboptimize` records the start time, as 1.1.2 did, but only when no
 * table failed; a failed table keeps the schedule due, so the next maintenance run retries.
 * @return array<string, array{0: string, 1: list<string>}>
 */
function resurrection_optimize_tables(): array {
    if (!resurrection_maintenance_lock_held()) throw new LogicException('Table optimization runs only under the maintenance lock.');
    $started = date("Y-m-d H:i:s");
    $start = getmicrotime();
    $results = [];
    foreach (db_query("SHOW TABLES") as $row) {
        $table = (string)reset($row);
        try { $sql = "OPTIMIZE TABLE " . db_identifier($table); }
        catch (InvalidArgumentException $error) { $results[$table] = ['failed', ['The table name is not supported.']]; continue; }
        $rows = db_query($sql, false);
        $results[$table] = is_array($rows) ? resurrection_optimize_classify($rows)
            : ['failed', ['The statement failed: ' . ($GLOBALS['dbinfo']['error_context'] ?? 'no detail') . '.']];
    }
    $seconds = round(getmicrotime() - $start, 2);
    gamelog(resurrection_optimize_report($results, $seconds), "maintenance");
    if (!in_array('failed', array_column($results, 0), true)) savesetting("lastdboptimize", $started);
    return $results;
}
