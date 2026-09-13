<?php
require_once __DIR__ . '/../../lib/player_mutation.php';
require_once __DIR__ . '/../../lib/e_rand.php';

/** @return array{drink: array<string,mixed>, hp: int, turns: int} */
function drinks_purchase(int $id): array {
    if ($id < 1 || !is_module_active('drinks')) { throw new DomainException('Drink unavailable.'); }
    return resurrection_player_mutation(function () use ($id): array {
        global $session;
        $rows=db_query('SELECT * FROM ' . db_prefix('drinks') . ' WHERE drinkid=? AND active=1 FOR UPDATE',true,[$id]);
        if (count($rows)!==1) { throw new DomainException('Drink unavailable.'); }
        $row=$rows[0];
        unset($GLOBALS['module_prefs'][(int)$session['user']['acctid']]['drinks']);
        $drunk=(int)get_module_pref('drunkeness','drinks');
        $hard=(int)get_module_pref('harddrinks','drinks');
        if ($drunk > (int)get_module_setting('maxdrunk','drinks') ||
            ($row['harddrink'] && $hard >= (int)get_module_setting('hardlimit','drinks'))) {
            throw new DomainException('Drink limit reached.');
        }
        $row['allowdrink']=1;
        $checked=modulehook('drinks-check',$row);
        if (empty($checked['allowdrink'])) { throw new DomainException('Drink unavailable.'); }
        $cost=(int)$session['user']['level']*(int)$row['costperlevel'];
        if ($cost < 0 || $cost > 2147483647 || $cost > (int)$session['user']['gold']) { throw new DomainException('Insufficient funds.'); }
        if ($row['drunkeness'] < 0 || $row['drunkeness'] > 100 || $drunk < 0 || $hard < 0) { throw new DomainException('Invalid drink state.'); }
        $session['user']['gold']-=$cost;
        set_module_pref('drunkeness',min(100,$drunk+(int)$row['drunkeness']),'drinks');
        if ($row['harddrink']) set_module_pref('harddrinks',$hard+1,'drinks');
        $givehp=false; $giveturn=false;
        if ($row['hpchance']>0 || $row['turnchance']>0) {
            $roll=e_rand(1,(int)$row['hpchance']+(int)$row['turnchance']);
            if ($roll <= $row['hpchance'] && $row['hpchance']>0) $givehp=true;
            else $giveturn=true;
        }
        $givehp=$givehp || $row['alwayshp']; $giveturn=$giveturn || $row['alwaysturn'];
        $oldhp=(int)$session['user']['hitpoints']; $oldturns=(int)$session['user']['turns'];
        if ($giveturn) $session['user']['turns']=max(0,$oldturns+e_rand((int)$row['turnmin'],(int)$row['turnmax']));
        if ($givehp) {
            $hp=$row['hppercent'] != 0 ? (int)round($session['user']['maxhitpoints']*($row['hppercent']/100),0) : e_rand((int)$row['hpmin'],(int)$row['hpmax']);
            $session['user']['hitpoints']=max(1,$oldhp+$hp);
        }
        $buff=['name'=>$row['buffname'],'rounds'=>$row['buffrounds'],'schema'=>'module-drinks'];
        foreach (['wearoff'=>'buffwearoff','atkmod'=>'buffatkmod','defmod'=>'buffdefmod','dmgmod'=>'buffdmgmod','damageshield'=>'buffdmgshield','roundmsg'=>'buffroundmsg','effectmsg'=>'buffeffectmsg','effectnodmgmsg'=>'buffeffectnodmgmsg','effectfailmsg'=>'buffeffectfailmsg'] as $key=>$field) {
            if ($row[$field]) $buff[$key]=$row[$field];
        }
        apply_buff('buzz',$buff);
        return ['drink'=>$row,'hp'=>(int)$session['user']['hitpoints']-$oldhp,'turns'=>(int)$session['user']['turns']-$oldturns];
    });
}
