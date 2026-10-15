<?php
require_once __DIR__ . '/player_mutation.php';

const RESURRECTION_PETITION_MAX_ID = 4294967295;
const RESURRECTION_PETITION_ZERO_DATE = '0000-00-00 00:00:00';

/** The one-use status intent is bound to the petition and the status it had when the form was shown. */
function resurrection_petition_status_fields(int $id, int $status): string {
    return resurrection_action_fields('petition-status-' . $id, $id . ':' . $status);
}

/**
 * GET never reaches this. Invalid input is rejected before the intent is consumed; the consumed
 * intent cannot be reused after a failure, so a retry needs a freshly displayed form.
 * Setting the current status again is the historical no-op: no write, no cache invalidation.
 */
function resurrection_petition_set_status(int $id, int $target): bool {
    global $session, $dbinfo;
    $rows = db_query('SELECT status FROM ' . db_prefix('petitions') . ' WHERE petitionid=?', true, [$id]);
    if (count($rows) !== 1) throw new OutOfBoundsException('Petition not found.');
    $current = (int)$rows[0]['status'];
    resurrection_consume_action('petition-status-' . $id, $id . ':' . $current);
    if ($target === $current) return false;
    $connection = $dbinfo['connection'];
    if ($connection->inTransaction()) throw new LogicException('Nested petition status change.');
    $connection->beginTransaction();
    try {
        $locked = db_query('SELECT status FROM ' . db_prefix('petitions') . ' WHERE petitionid=? FOR UPDATE', true, [$id]);
        if (count($locked) !== 1 || (int)$locked[0]['status'] !== $current) throw new DomainException('Petition changed.');
        db_query('UPDATE ' . db_prefix('petitions') . ' SET status=?,closeuserid=?,closedate=? WHERE petitionid=?', true,
            [$target, (int)$session['user']['acctid'], date('Y-m-d H:i:s'), $id]);
        $connection->commit();
    } catch (Throwable $error) {
        resurrection_rollback_player_transaction($connection);
        throw $error;
    }
    // The counter cache is invalidated only for a committed change.
    invalidatedatacache('petition_counts');
    return true;
}

/**
 * Historical retention: closed petitions are deleted once closed for 7 days. Each petition's
 * pet-<id> commentary is deleted with it, as 1.1.2's disabled manual delete did. All or nothing;
 * repeating it removes nothing new.
 * @return int Petitions removed.
 */
function resurrection_petition_cleanup(): int {
    global $dbinfo;
    $connection = $dbinfo['connection'];
    if ($connection->inTransaction()) throw new LogicException('Nested petition cleanup.');
    $connection->beginTransaction();
    try {
        $rows = db_query('SELECT petitionid FROM ' . db_prefix('petitions') . ' WHERE status=2 AND closedate<? ORDER BY petitionid FOR UPDATE', true,
            [date('Y-m-d H:i:s', strtotime('-7 days'))]);
        $ids = array_map(static fn($row) => (int)$row['petitionid'], $rows);
        foreach ($ids as $id) {
            db_query('DELETE FROM ' . db_prefix('commentary') . ' WHERE section=?', true, ['pet-' . $id]);
            db_query('DELETE FROM ' . db_prefix('petitions') . ' WHERE petitionid=?', true, [$id]);
        }
        $connection->commit();
    } catch (Throwable $error) {
        resurrection_rollback_player_transaction($connection);
        throw $error;
    }
    if ($ids !== []) {
        invalidatedatacache('petition_counts');
        foreach ($ids as $id) invalidatedatacache('comments-pet-' . $id);
    }
    return count($ids);
}
