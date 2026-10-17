<?php
require_once __DIR__ . '/constants.php';

/** A delete_character hook set dodel=false: the deletion's transaction is rolled back, so nothing changes. */
final class ResurrectionCharacterDeletionVetoed extends DomainException {}

/**
 * prefs.php's Delete Character form is bound to the account it was shown to.
 * @param array<string, mixed> $user
 */
function resurrection_suicide_context(array $user): string {
    return hash('sha256', json_encode(['suicide', (string)$user['acctid'], (string)$user['login']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
}

/**
 * charcleanup's 1.1.2 clan succession for a deleted account. Only a deleted leader (exactly CLAN_LEADER, as in
 * 1.1.2) hands on the clan. $successor is the first remaining member above applicant, by rank (highest first),
 * then join date (earliest first), then account ID. 1.1.2 promotes that member unless their rank already is
 * CLAN_LEADER, and deletes the clan when nobody above applicant remains.
 * @param array{acctid: int, clanrank: int}|null $successor
 * @return array{0: 'promote', 1: int}|array{0: 'keep'}|array{0: 'disband'}|null null when the clan is not affected.
 */
function resurrection_char_cleanup_succession(int $clanid, int $clanrank, ?array $successor): ?array {
    if ($clanid === 0 || $clanrank !== CLAN_LEADER) return null;
    if ($successor === null) return ['disband'];
    return $successor['clanrank'] !== CLAN_LEADER ? ['promote', $successor['acctid']] : ['keep'];
}

/**
 * The 1.1.2 cleanup for one character, run inside the caller's deletion transaction so it commits or rolls
 * back with the account row: the delete_character hook (and whatever it writes), the account's saved output,
 * commentary, clan succession, module preferences, mail and news. Returns false when a hook vetoed; the caller
 * must then roll back, so a veto never leaves a half-cleaned account.
 */
function char_cleanup(int $id, int $type): bool
{
    $connection = $GLOBALS['dbinfo']['connection'];
    if (!$connection->inTransaction()) throw new LogicException('Character cleanup runs inside the deletion transaction.');
    $account = db_query('SELECT clanrank,clanid FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$id])[0] ?? null;
    $clanid = (int)($account['clanid'] ?? 0);
    $clanrank = (int)($account['clanrank'] ?? 0);
    $leader = resurrection_char_cleanup_succession($clanid, $clanrank, null) !== null;
    if ($leader) {
        // Lock every member, then the clan, as the accepted clan membership code does, so succession sees one clan.
        db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE clanid=? ORDER BY acctid FOR UPDATE', true, [$clanid]);
        db_query('SELECT clanid FROM ' . db_prefix('clans') . ' WHERE clanid=? FOR UPDATE', true, [$clanid]);
    }

    // Run any modules hooks who want to deal with character deletion, or stop it
    $return = modulehook("delete_character", array("acctid"=>$id, "deltype"=>$type, "dodel"=>true));
    if (!is_array($return) || !($return['dodel'] ?? false)) return false;

    // delete the output field from the accounts_output table introduced in 1.1.1
    db_query('DELETE FROM ' . db_prefix('accounts_output') . ' WHERE acctid=?', true, [$id]);
    // delete the comments the user posted, necessary to have the systemcomments with acctid 0 working
    db_query('DELETE FROM ' . db_prefix('commentary') . ' WHERE author=?', true, [$id]);

    if ($leader) {
        $rows = db_query('SELECT name,acctid,clanrank FROM ' . db_prefix('accounts') .
            ' WHERE clanid=? AND clanrank>? AND acctid<>? ORDER BY clanrank DESC, clanjoindate, acctid LIMIT 1', true,
            [$clanid, CLAN_APPLICANT, $id]);
        $successor = $rows ? ['acctid' => (int)$rows[0]['acctid'], 'clanrank' => (int)$rows[0]['clanrank']] : null;
        $succession = resurrection_char_cleanup_succession($clanid, $clanrank, $successor);
        if ($succession !== null && $succession[0] === 'promote') {
            db_query('UPDATE ' . db_prefix('accounts') . ' SET clanrank=? WHERE acctid=?', true, [CLAN_LEADER, $succession[1]]);
        } elseif ($succession !== null && $succession[0] === 'disband') {
            db_query('DELETE FROM ' . db_prefix('clans') . ' WHERE clanid=?', true, [$clanid]);
            // 1.1.2 wrote clanjoindate='0000-00-00 00:00;00', which strict SQL refuses; NULL is the accepted "no clan".
            db_query('UPDATE ' . db_prefix('accounts') . ' SET clanid=0,clanrank=?,clanjoindate=NULL WHERE clanid=?', true,
                [CLAN_APPLICANT, $clanid]);
        }
    }

    // Delete any module user prefs
    db_query('DELETE FROM ' . db_prefix('module_userprefs') . ' WHERE userid=?', true, [$id]);
    // Delete any mail to or from the user
    db_query('DELETE FROM ' . db_prefix('mail') . ' WHERE msgto=? OR msgfrom=?', true, [$id, (string)$id]);
    // Delete any news from the user
    db_query('DELETE FROM ' . db_prefix('news') . ' WHERE accountid=?', true, [$id]);
    return true;
}

/**
 * Delete one account and everything char_cleanup removes, inside the caller's open transaction, which must
 * hold or take the account lock first. Returns the deleted row (login, name, level, dragon kills).
 * @return array<string, mixed>
 * @throws OutOfBoundsException when the account does not exist (already deleted).
 * @throws ResurrectionCharacterDeletionVetoed when a delete_character hook vetoed; the caller rolls back.
 */
function resurrection_char_delete(int $id, int $type): array {
    $rows = db_query('SELECT acctid,login,name,level,dragonkills FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$id]);
    if (count($rows) !== 1) throw new OutOfBoundsException('Account not found.');
    if (!char_cleanup($id, $type)) throw new ResurrectionCharacterDeletionVetoed('Character deletion vetoed.');
    db_query('DELETE FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [$id]);
    return $rows[0];
}
