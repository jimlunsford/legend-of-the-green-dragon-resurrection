<?php
require_once __DIR__ . '/../../lib/player_mutation.php';
require_once __DIR__ . '/../../lib/e_rand.php';

/** Trusted game service. HTTP callers must separately consume their one-use form. */
function dag_place_bounty(int $targetId, int $amount, bool $administrator = false): int {
    global $session;
    if (!is_module_active('dag') || !module_check_requirements(get_module_info('dag')['requires'] ?? [])) {
        throw new DomainException('Dag is unavailable.');
    }
    if ($administrator && (empty($session['loggedin']) || !((int)$session['user']['superuser'] & SU_EDIT_USERS))) {
        throw new DomainException('Bounty administration is not authorized.');
    }
    if ($targetId < 1 || $amount < 1 || $amount > 2147483647) { throw new DomainException('Invalid bounty.'); }
    return resurrection_player_mutation(function () use ($targetId, $amount, $administrator): int {
        global $session;
        $target = db_query('SELECT acctid,name,level,locked,age,dragonkills,pk,experience FROM ' . db_prefix('accounts') . ' WHERE acctid=? FOR UPDATE', true, [$targetId]);
        if (count($target) !== 1 || $target[0]['locked']) { throw new DomainException('Invalid bounty target.'); }
        $target = $target[0];
        $cost = 0;
        if (!$administrator) {
            if ($targetId === (int)$session['user']['acctid'] || $target['level'] < get_module_setting('bountylevel','dag') ||
                ($target['age'] < getsetting('pvpimmunity',5) && $target['dragonkills'] == 0 && $target['pk'] == 0 && $target['experience'] < getsetting('pvpminexp',1500))) {
                throw new DomainException('Ineligible bounty target.');
            }
            unset($GLOBALS['module_prefs'][(int)$session['user']['acctid']]['dag']);
            $count = (int)get_module_pref('bounties','dag');
            if ($count >= (int)get_module_setting('maxbounties','dag')) { throw new DomainException('Daily bounty limit reached.'); }
            $min = (int)get_module_setting('bountymin','dag') * (int)$target['level'];
            $max = (int)get_module_setting('bountymax','dag') * (int)$target['level'];
            $fee = (int)get_module_setting('bountyfee','dag');
            if ($fee < 0 || $fee > 100) $fee = 10;
            $existing = db_query('SELECT COALESCE(SUM(amount),0) AS total FROM ' . db_prefix('bounty') . ' WHERE status=0 AND target=?',true,[$targetId]);
            $cost = (int)round($amount * ((100 + $fee) / 100),0);
            if ($amount < $min || $amount + (int)$existing[0]['total'] > $max || $cost > (int)$session['user']['gold']) {
                throw new DomainException('Bounty amount or funds are invalid.');
            }
            $session['user']['gold'] -= $cost;
            set_module_pref('bounties',$count+1,'dag');
        }
        $date = date('Y-m-d H:i:s', time() + ($administrator ? 0 : e_rand(0,14400)));
        db_query('INSERT INTO ' . db_prefix('bounty') . ' (amount,target,setter,setdate) VALUES (?,?,?,?)',true,
            [$amount,$targetId,$administrator ? 0 : (int)$session['user']['acctid'],$date]);
        return $cost;
    });
}

/** Internal pvpwin hook only. Open bounty rows and the player credit commit together.
 * @return array{int,int}
 */
function dag_claim_bounties(int $targetId): array {
    if ($targetId < 1) { throw new DomainException('Invalid bounty target.'); }
    $claim = function () use ($targetId): array {
        global $session;
        if ($targetId === (int)$session['user']['acctid']) { throw new DomainException('Invalid bounty target.'); }
        $rows = db_query('SELECT bountyid,amount,setter FROM ' . db_prefix('bounty') . ' WHERE status=0 AND setdate<=? AND target=? FOR UPDATE', true, [date('Y-m-d H:i:s'),$targetId]);
        $good=0; $own=0;
        foreach ($rows as $row) {
            $amount=(int)$row['amount'];
            if ($amount < 1) { throw new DomainException('Invalid stored bounty.'); }
            if ((int)$row['setter'] === (int)$session['user']['acctid']) { $own += $amount; continue; }
            $good += $amount;
            if ($good > 2147483647 - (int)$session['user']['gold']) { throw new DomainException('Bounty balance limit exceeded.'); }
            db_query('UPDATE ' . db_prefix('bounty') . ' SET status=1,winner=?,windate=? WHERE bountyid=? AND status=0',true,
                [(int)$session['user']['acctid'],date('Y-m-d H:i:s'),(int)$row['bountyid']]);
        }
        $session['user']['gold'] += $good;
        return [$good,$own];
    };
    // The real PvP route owns the larger combat/result transaction. Standalone
    // internal callers retain the existing account transaction contract.
    return $GLOBALS['dbinfo']['connection']->inTransaction() ? $claim() : resurrection_player_mutation($claim);
}
