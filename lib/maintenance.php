<?php
/** The scheduler's lock for this database: one maintenance run at a time (cron.php). */
function resurrection_maintenance_lock_name(): string {
    $rows = db_query('SELECT DATABASE() AS db', true, []);
    return 'resurrection-maintenance-' . substr(hash('sha256', (string)($rows[0]['db'] ?? '')), 0, 32);
}

/** Whether this connection holds the maintenance lock, so work that must never overlap can refuse to run without it. */
function resurrection_maintenance_lock_held(): bool {
    $rows = db_query('SELECT IS_USED_LOCK(?) = CONNECTION_ID() AS held', true, [resurrection_maintenance_lock_name()]);
    return (string)($rows[0]['held'] ?? '') === '1';
}
