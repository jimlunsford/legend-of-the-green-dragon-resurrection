<?php
require_once __DIR__ . '/../src/Security/PvpState.php';
require_once __DIR__ . '/player_mutation.php';
require_once __DIR__ . '/../src/Game/ForestBuffState.php';
require_once __DIR__ . '/../src/Game/ForestCompanionState.php';
require_once __DIR__ . '/../src/Game/Expression.php';

/** Stable gameplay snapshot, excluding navigation, transport and performance bookkeeping. */
function resurrection_pvp_account_hash(int $id, bool $actor=false): string {
    global $session,$companions;
    $rows=db_query('SELECT * FROM '.db_prefix('accounts').' WHERE acctid=?',true,[$id]);
    if (count($rows)!==1) return hash('sha256','missing:'.$id);
    $row=$rows[0];
    foreach (['laston','gentime','gentimecount','gensize','allowednavs','restorepage','lastip','uniqueid','loggedin','lastmotd','lastnews'] as $key) unset($row[$key]);
    // Availability is historical loggedin + timeout, not the loggedin bit alone.
    $row['online']=!empty($rows[0]['loggedin']) && strtotime($rows[0]['laston'])>strtotime('-'.getsetting('LOGINTIMEOUT',900).' seconds');
    if ($actor) {
        unset($row['bufflist'],$row['companions']);
        $buffs=[]; foreach ($session['bufflist'] as $name=>$buff) {
            if (is_array($buff)) unset($buff['fields_calculated'],$buff['tempstats_calculated']);
            $buffs[]=[$name,$buff];
        }
        $row['buffs']=$buffs; $row['participants']=$companions;
    }
    ksort($row);
    return hash('sha256',serialize($row));
}

/** Bind both actors, the encounter, settings and module configuration to a one-use form. */
function resurrection_pvp_context(int $target=0): string {
    global $session;
    $selection=$target>0 ? $target.':'.httpget('inn') : '';
    if ($target===0 && $session['user']['badguy']!=='') {
        $combat=\Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']);
        $target=$combat['options']['target'];
    }
    $settings=db_query('SELECT setting,value FROM '.db_prefix('settings')." WHERE setting IN ('pvp','pvpimmunity','pvpminexp','LOGINTIMEOUT','innname','pvpattgain','pvpdeflose','pvpdefgain','pvpattlose','autofight','autofightfull','maxattacks','enablecompanions') ORDER BY setting");
    $modules=db_query('SELECT modulename,active FROM '.db_prefix('modules').' ORDER BY modulename');
    return hash('sha256',serialize([resurrection_pvp_account_hash((int)$session['user']['acctid'],true),resurrection_pvp_account_hash($target),$settings,$modules,$selection]));
}

/** Validate the actual battle consumer vocabulary; unrelated producers remain uncertified. */
function resurrection_pvp_participants(bool $requireActor=true): void {
    global $session,$baseaccount,$companions;
    if ($requireActor && (empty($baseaccount['alive']) || $session['user']['specialinc']!=='')) throw new DomainException('PvP actor unavailable.');
    $raw=\Resurrection\Security\ScalarState::read($baseaccount['bufflist']);
    \Resurrection\Game\ForestBuffState::validate($raw,$session['user']);
    \Resurrection\Game\ForestBuffState::validate($session['bufflist'],$session['user']);
    \Resurrection\Game\ForestCompanionState::validate($companions);
    foreach ($session['bufflist'] as $buff) {
        if (!is_string($buff['name'] ?? null) || !is_string($buff['schema'] ?? null)) throw new DomainException('Invalid PvP buff.');
        foreach (['regen','minioncount','lifetap','damageshield'] as $effect) {
            if (isset($buff[$effect])) foreach (['effectmsg','effectnodmgmsg'] as $key) if (!is_string($buff[$key] ?? null)) throw new DomainException('Invalid PvP effect.');
        }
    }
}

/** The historical bodyguard is a room-level buff, never another combat target.
 * @param array<string,mixed> $state
 */
function resurrection_pvp_bodyguard(array $state): void {
    global $session;
    $enemy=$state['enemies'][0]; $buff=$session['bufflist']['bodyguard'] ?? null;
    if ($buff===null) return;
    $level=(int)($enemy['bodyguardlevel'] ?? 0);
    $mods=[1=>[1.05,.95],2=>[1.1,.9],3=>[1.2,.8],4=>[1.3,.7],5=>[1.4,.6]];
    if (!isset($mods[$level]) || $enemy['location']!==getsetting('innname',LOCATION_INN) ||
        ($buff['badguyatkmod'] ?? null)!=$mods[$level][0] || ($buff['defmod'] ?? null)!=$mods[$level][1] ||
        ($buff['rounds'] ?? null)!==-1 || ($buff['schema'] ?? null)!=='pvp' ||
        ($buff['allowinpvp'] ?? null)!==1 || ($buff['expireafterfight'] ?? null)!==1 || !empty($buff['suspended']) ||
        array_diff(array_keys($buff),['name','startmsg','wearoff','badguyatkmod','defmod','rounds','allowinpvp','expireafterfight','schema','used','fields_calculated','tempstats_calculated'])!==[]) throw new DomainException('Invalid bodyguard state.');
}

/** Reject overflow/negative rewards without changing historical formulas. */
function resurrection_pvp_balances(int $victim): void {
    global $session;
    $rows=db_query('SELECT gold,experience FROM '.db_prefix('accounts').' WHERE acctid=?',true,[$victim]);
    foreach ([$session['user'],...$rows] as $row) foreach (['gold','experience'] as $key) {
        if (filter_var((string)$row[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]])===false) throw new DomainException('PvP balance out of range.');
    }
}
