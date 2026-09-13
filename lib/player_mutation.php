<?php
require_once __DIR__ . '/web_security.php';
require_once __DIR__ . '/../src/Security/ActionToken.php';

/** Issue an intent for an explicitly named action and authoritative state. */
function resurrection_action_fields(string $scope, string $context): string {
    return resurrection_csrf_field() . '<input type="hidden" name="action_token" value="' .
        \Resurrection\Security\ActionToken::issue($_SESSION, $scope, $context) . '">';
}

function resurrection_consume_action(string $scope, string $context): void {
    resurrection_require_post();
    try {
        \Resurrection\Security\ActionToken::consume($_SESSION, $scope, $context, $_POST['action_token'] ?? null);
    } catch (DomainException $error) {
        http_response_code(409);
        exit('Expired or repeated action.');
    }
}

/**
 * Enclose trusted DML-only gameplay callbacks and their player writes in one transaction.
 * Lock/recheck the hydrated account before calling gameplay code, preventing stale balances.
 * No DDL, network effects, rendering, saveuser(), or exit is allowed inside a callback.
 */
function resurrection_player_mutation(callable $action): mixed {
    global $session, $baseaccount, $dbinfo, $companions;
    if (empty($session['loggedin']) || empty($session['user']['acctid'])) {
        throw new DomainException('Authentication required.');
    }
    $connection = $dbinfo['connection'];
    if ($connection->inTransaction()) { throw new LogicException('Nested player mutation.'); }
    restore_buff_fields();
    $before = $session;
    $beforeCompanions = $companions;
    $beforeBase = $baseaccount;
    $connection->beginTransaction();
    try {
        $rows = db_query('SELECT * FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true,
            [(int)$session['user']['acctid']]);
        if (count($rows) !== 1) { throw new DomainException('Player no longer exists.'); }
        foreach ($baseaccount as $key => $value) {
            // Metrics and access timestamps do not authorize or fund an action.
            if (in_array($key, ['laston','gentime','gentimecount','gensize','allowednavs','restorepage'], true)) { continue; }
            if ($rows[0][$key] !== $value) { throw new DomainException('Player state changed. Reload before acting.'); }
        }
        $result = $action();
        $values = $session['user'];
        $values['bufflist'] = serialize($session['bufflist']);
        if (is_array($companions)) { $values['companions'] = serialize($companions); }
        $sets = []; $parameters = [];
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $baseaccount) || in_array($key, ['acctid','password','allowednavs'], true)) { continue; }
            if (is_array($value)) { $value = serialize($value); }
            if ($value != $baseaccount[$key]) {
                $sets[] = db_identifier($key) . '=?'; $parameters[] = $value;
            }
        }
        if ($sets !== []) {
            $parameters[] = (int)$session['user']['acctid'];
            db_query('UPDATE ' . db_prefix('accounts') . ' SET ' . implode(',', $sets) . ' WHERE acctid=?', true, $parameters);
        }
        $connection->commit();
        // The normal page save must not write stale pre-transaction values back.
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $baseaccount) && !in_array($key, ['acctid','password','allowednavs'], true)) {
                $baseaccount[$key] = is_array($value) ? serialize($value) : $value;
            }
        }
        return $result;
    } catch (Throwable $error) {
        if ($connection->inTransaction()) { $connection->rollBack(); }
        $session = $before; $companions = $beforeCompanions; $baseaccount = $beforeBase;
        $GLOBALS['module_prefs'] = []; $GLOBALS['module_settings'] = [];
        throw $error;
    }
}
