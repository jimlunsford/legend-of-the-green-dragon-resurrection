<?php
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/charcleanup.php';
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/systemmail.php';
require_once __DIR__ . '/gamelog.php';

/**
 * 1.1.2's three expiration rules as one bound condition, each only while its setting is above 0: not logged on for
 * `expireoldacct` days; level 1 with no dragon kills and not logged on for `expirenewacct` days; registered more than
 * `expiretrashacct` days ago and never logged on. Accounts with a NO_ACCOUNT_EXPIRATION flag never match.
 *
 * "Never logged on" is `laston <= regdate`. 1.1.2's create.php wrote laston one day before regdate and tested
 * `laston < regdate`; account creation now writes laston equal to regdate, which `<` never matches.
 * @return array{0: string, 1: list<int|string>}
 */
function resurrection_expire_rules(int $old, int $new, int $trash, int $now): array {
    $rules = '';
    $parameters = [NO_ACCOUNT_EXPIRATION];
    if ($old > 0) {
        $rules .= ' OR (laston < ?)';
        $parameters[] = date('Y-m-d H:i:s', strtotime("-$old days", $now));
    }
    if ($new > 0) {
        $rules .= ' OR (laston < ? AND level=1 AND dragonkills=0)';
        $parameters[] = date('Y-m-d H:i:s', strtotime("-$new days", $now));
    }
    if ($trash > 0) {
        $rules .= ' OR (regdate < ? AND laston <= regdate)';
        $parameters[] = date('Y-m-d H:i:s', strtotime("-$trash days", $now));
    }
    return ['(superuser&?)=0 AND (1=0' . $rules . ')', $parameters];
}

/**
 * The warning email's cutoff: five days before the old-account rule. 1.1.2 subtracted 5 from the setting and wrote
 * `1=0 OR (laston < cutoff) AND ...`, which SQL reads as `1=0 OR (laston < cutoff AND ...)`: accounts past the
 * cutoff when the result is above 0, and nobody otherwise.
 */
function resurrection_expire_warning_cutoff(int $old, int $now): ?string {
    $days = $old - 5;
    return $days > 0 ? date('Y-m-d H:i:s', strtotime("-$days days", $now)) : null;
}

/**
 * Delete one selected account in its own transaction, rechecking the rules under the account lock (a player who
 * logged on since the selection is kept). Hook mail is queued and delivered only after commit.
 * @param list<int|string> $parameters
 * @return array{0: string, 1: array<string, mixed>|null} 'deleted' with the row, or 'vetoed', 'skipped', 'failed'.
 */
function resurrection_expire_account(int $id, string $rules, array $parameters): array {
    $connection = $GLOBALS['dbinfo']['connection'];
    if ($connection->inTransaction() || isset($GLOBALS['mail_notifications'])) throw new LogicException('Nested character expiration.');
    $GLOBALS['mail_notifications'] = [];
    $deleted = null;
    $connection->beginTransaction();
    try {
        $rows = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE acctid=? AND ' . $rules . ' FOR UPDATE', true, [$id, ...$parameters]);
        if (count($rows) === 1) $deleted = resurrection_char_delete($id, CHAR_DELETE_AUTO);
        $connection->commit();
        $status = $deleted === null ? 'skipped' : 'deleted';
    } catch (ResurrectionCharacterDeletionVetoed $error) {
        resurrection_rollback_player_transaction($connection);
        $status = 'vetoed';
    } catch (Throwable $error) {
        resurrection_rollback_player_transaction($connection);
        error_log('Character expiration failed for one account; it was left unchanged.');
        $status = 'failed';
    } finally {
        $notifications = $GLOBALS['mail_notifications'];
        unset($GLOBALS['mail_notifications']);
    }
    if ($status !== 'deleted') {
        $GLOBALS['module_prefs'] = []; $GLOBALS['module_settings'] = [];
        return [$status, null];
    }
    foreach ($notifications as $notification) resurrection_systemmail_notification(...$notification);
    return [$status, $deleted];
}

/**
 * lib/expire_chars.php: at most once per 23 hours, under the maintenance lock. Each account is deleted all or
 * nothing; a vetoed or failed account is skipped and counted and the rest continue. The run is recorded in
 * `last_char_expire` only when no account failed, so failures are retried at the next maintenance run. Warning
 * emails mark `sentnotice` only when the mail transport accepted them.
 */
function resurrection_expire_characters(int $now): void {
    if (!resurrection_maintenance_lock_held()) throw new LogicException('Character expiration runs only under the maintenance lock.');
    $lastexpire = strtotime(getsetting("last_char_expire","0000-00-00 00:00:00"));
    $needtoexpire = strtotime("-23 hours", $now);
    if ($lastexpire >= $needtoexpire) return;
    $old = (int)getsetting("expireoldacct",45);
    $new = (int)getsetting("expirenewacct",10);
    $trash = (int)getsetting("expiretrashacct",1);
    [$rules, $parameters] = resurrection_expire_rules($old, $new, $trash, $now);

    $selected = db_query('SELECT login,acctid,dragonkills,level FROM ' . db_prefix('accounts') . ' WHERE ' . $rules . ' ORDER BY acctid', true, $parameters);
    $pinfo = array();
    $dk0lvl = 0;
    $dk0ct = 0;
    $dk1lvl = 0;
    $dk1ct = 0;
    $dks = 0;
    $outcomes = ['vetoed' => [], 'failed' => [], 'skipped' => []];
    foreach ($selected as $row1) {
        [$status, $row] = resurrection_expire_account((int)$row1['acctid'], $rules, $parameters);
        if ($row === null) {
            $outcomes[$status][] = $row1['login'];
            continue;
        }
        array_push($pinfo,"{$row['login']}:dk{$row['dragonkills']}-lv{$row['level']}");
        if ($row['dragonkills']==0) {
            $dk0lvl += $row['level'];
            $dk0ct++;
        }else if($row['dragonkills']==1){
            $dk1lvl += $row['level'];
            $dk1ct++;
        }
        $dks += $row['dragonkills'];
    }

    //Log which accounts were deleted, and any that were not.
    $msg = "[{$dk0ct}] with 0 dk avg lvl [".round($dk0lvl/max(1,$dk0ct),2)."]\n";
    $msg .= "[{$dk1ct}] with 1 dk avg lvl [".round($dk1lvl/max(1,$dk1ct),2)."]\n";
    $msg .= "Avg DK: [".round($dks/max(1,count($pinfo)),2)."]\n";
    $msg .= "Accounts: ".join(", ",$pinfo);
    $labels = ['vetoed' => 'Kept by a module', 'failed' => 'Failed, left unchanged', 'skipped' => 'No longer eligible'];
    foreach ($outcomes as $status => $logins) {
        if ($logins) $msg .= "\n" . $labels[$status] . ": " . count($logins) . " (" . join(", ", $logins) . ")";
    }
    gamelog("Deleted ".count($pinfo)." accounts:\n$msg","char expiration");
    if (!$outcomes['failed']) savesetting("last_char_expire", date("Y-m-d H:i:s", $now));

    $cutoff = resurrection_expire_warning_cutoff($old, $now);
    if ($cutoff === null) return;
    $rows = db_query('SELECT acctid,emailaddress FROM ' . db_prefix('accounts') .
        " WHERE laston < ? AND emailaddress!='' AND sentnotice=0 AND (superuser&?)=0 ORDER BY acctid", true, [$cutoff, NO_ACCOUNT_EXPIRATION]);
    if (!$rows) return;
    $subject = translate_inline("LoGD Character Expiration");
    $body = sprintf_translate("One or more of your characters in Legend of the Green Dragon at %s is about to expire.  If you wish to keep this character, you should log on to him or her soon!",getsetting("serverurl","http://".$_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] == 80?"":":".$_SERVER['SERVER_PORT']).dirname($_SERVER['REQUEST_URI'])));
    $from = getsetting("gameadminemail","postmaster@localhost.com");
    $sent = 0;
    foreach ($rows as $row) {
        if (!resurrection_send_email((string)$row['emailaddress'], $subject, $body, $from)) continue;
        db_query('UPDATE ' . db_prefix('accounts') . ' SET sentnotice=1 WHERE acctid=? AND sentnotice=0', true, [(int)$row['acctid']]);
        $sent++;
    }
    $unsent = count($rows) - $sent;
    gamelog("Sent $sent expiration warning emails." . ($unsent ? " $unsent could not be sent and will be retried." : ""), "char expiration");
}
