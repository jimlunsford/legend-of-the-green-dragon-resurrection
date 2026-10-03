<?php
require_once __DIR__ . '/forest_combat.php';
require_once __DIR__ . '/experience.php';
require_once __DIR__ . '/increment_specialty.php';
require_once __DIR__ . '/systemmail.php';
require_once __DIR__ . '/substitute.php';
require_once __DIR__ . '/taunt.php';
require_once __DIR__ . '/villagenav.php';
require_once __DIR__ . '/extended-battle.php';
require_once __DIR__ . '/../src/Game/TrainingCombatState.php';

/** @return list<array<string,mixed>> */
function resurrection_training_masters(): array {
    global $session;
    $table=db_prefix('masters');
    $rows=db_query("SELECT * FROM $table WHERE creaturelevel=(SELECT MAX(creaturelevel) FROM $table WHERE creaturelevel<=?) ORDER BY creatureid",true,[(int)$session['user']['level']]);
    $masters=[];
    foreach ($rows as $master) {
        foreach (['creaturename','creaturewin','creaturelose','creatureweapon'] as $key) $master[$key]=stripslashes((string)$master[$key]);
        if ($master['creaturename']==='Gadriel the Elven Ranger' && $session['user']['race']==='Elf') {
            $master['creaturewin']='You call yourself an Elf?? Maybe Half-Elf! Come back when you\'ve been better trained.';
            $master['creaturelose']='It is only fitting that another Elf should best me.  You make good progress.';
        }
        \Resurrection\Game\TrainingCombatState::master($master);
        $masters[]=$master;
    }
    return $masters;
}

/** @return array{masters:list<array<string,mixed>>,combat:array<string,mixed>} */
function resurrection_training_state(): array {
    global $session,$companions,$baseaccount;
    if (empty($session['loggedin']) || empty($baseaccount['alive']) || empty($session['user']['alive']) ||
        $session['user']['hitpoints']<=0 || $session['user']['level']<1 || $session['user']['level']>14 ||
        $session['user']['specialinc']!=='') throw new DomainException('Training unavailable.');
    // Check persisted roots before bootstrap normalization can conceal a corrupt collection.
    $raw=\Resurrection\Security\ScalarState::read($baseaccount['bufflist']);
    \Resurrection\Game\ForestBuffState::validate($raw,$session['user']);
    \Resurrection\Game\ForestBuffState::validate($session['bufflist'],$session['user']);
    foreach ($session['bufflist'] as $buff) {
        foreach (['regen','minioncount','lifetap','damageshield'] as $effect) {
            if (isset($buff[$effect])) foreach (['effectmsg','effectnodmgmsg'] as $key) if (!is_string($buff[$key] ?? null)) throw new DomainException('Invalid training effect message.');
        }
        if (!is_string($buff['name'] ?? null) || !is_string($buff['schema'] ?? null)) throw new DomainException('Invalid training buff presentation.');
    }
    \Resurrection\Game\ForestCompanionState::validate($companions);
    \Resurrection\Game\TrainingCombatState::budget($session['user']);
    $masters=resurrection_training_masters();
    if ($masters===[]) throw new DomainException('No master.');
    $combat=[];
    if ($session['user']['badguy']!=='') {
        $combat=\Resurrection\Security\ScalarState::read($session['user']['badguy']);
        $id=$combat['enemies'][0]['creatureid'] ?? null;
        if (!is_int($id) && !is_string($id)) throw new DomainException('Invalid master identity.');
        $selected=null;
        foreach ($masters as $master) if ((string)$master['creatureid']===(string)$id) $selected=$master;
        if ($selected===null || !$session['user']['seenmaster'] ||
            $session['user']['experience']<exp_for_next_level($session['user']['level'],$session['user']['dragonkills'])) throw new DomainException('Invalid master.');
        $combat=\Resurrection\Game\TrainingCombatState::read($session['user']['badguy'],$session['user'],$selected);
    }
    return ['masters'=>$masters,'combat'=>$combat];
}

function resurrection_training_context(): string {
    global $session,$companions;
    restore_buff_fields();
    try {
        $state=resurrection_training_state(); $player=[];
        foreach (['acctid','level','experience','hitpoints','maxhitpoints','attack','defense','soulpoints','alive','seenmaster',
            'race','specialty','dragonkills','dragonpoints','gold','gems','turns','location','specialinc','age','sex',
            'name','referer','refererawarded','lasthit','authversion'] as $key) $player[$key]=is_array($session['user'][$key])?$session['user'][$key]:(string)$session['user'][$key];
        $buffs=[]; foreach ($session['bufflist'] as $id=>$buff) { unset($buff['fields_calculated'],$buff['tempstats_calculated']); $buffs[]=[$id,$buff]; }
        $ordered=[]; foreach ($companions as $id=>$companion) $ordered[]=[$id,$companion];
        $settings=[]; foreach (['multimaster'=>1,'automaster'=>1,'companionslevelup'=>1,'enablecompanions'=>true,
            'referminlevel'=>4,'refereraward'=>25,'displaymasternews'=>1,'autofight'=>0,'autofightfull'=>0,'maxattacks'=>4] as $key=>$default) $settings[$key]=(string)getsetting($key,$default);
        $prefs=db_query('SELECT modulename,setting,value FROM '.db_prefix('module_userprefs').' WHERE userid=? ORDER BY modulename,setting',true,[(int)$session['user']['acctid']]);
        return hash('sha256',json_encode(resurrection_forest_canonical([$state,$player,$buffs,$ordered,$settings,$prefs]),JSON_THROW_ON_ERROR));
    } finally { calculate_buff_fields(); }
}

function resurrection_training_form(string $op,string $label,string $context,string $rounds=''): void {
    $url='train.php?op='.$op; addnav('',$url);
    rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('training-'.$op,$context).
        ($rounds!==''?'<input type="hidden" name="rounds" value="'.$rounds.'">':'').
        '<button class="button">'.htmlspecialchars(translate_inline($label),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</button></form>');
}

/** GET never heals, starts a fight, spends a daily challenge or settles advancement. */
function resurrection_training(): never {
    global $session,$companions;
    try {
        $op=\Resurrection\Http\Input::choice($_GET,'op',['','question','challenge','autochallenge','fight','run'],'');
        page_header("Bluspring's Warrior Training");
        if (($_SERVER['REQUEST_METHOD'] ?? '')==='GET' && $session['user']['level']>=15 && $session['user']['badguy']==='') {
            output('There is nothing left for you here but memories.'); villagenav(); page_footer(); exit;
        }
        $context=resurrection_training_context();
        restore_buff_fields(); $state=resurrection_training_state(); calculate_buff_fields();
        if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') {
            if (!in_array($op,['challenge','autochallenge','fight','run'],true)) throw new InvalidArgumentException();
            resurrection_consume_action('training-'.$op,$context);
            $rounds=\Resurrection\Http\Input::choice($_POST,'rounds',['','five','ten','full'],'');
            if ($rounds!=='' && (!in_array($op,['fight','run'],true) || !getsetting('autofight',0) ||
                ($rounds==='full' && !getsetting('autofightfull',0)))) throw new InvalidArgumentException();
            $GLOBALS['pvp_mail_notifications']=[];
            try {
                resurrection_player_mutation(function () use ($op,$context,$rounds) {
                    global $session,$companions,$badguy;
                    if (resurrection_training_context()!==$context) throw new DomainException('Stale training.');
                    restore_buff_fields(); $state=resurrection_training_state(); calculate_buff_fields();
                    try {
                        if (in_array($op,['challenge','autochallenge'],true)) {
                            if ($state['combat']!==[] || $session['user']['seenmaster'] || is_new_day()) throw new DomainException('Challenge unavailable.');
                            $truant=getsetting('automaster',1) && $session['user']['experience']>exp_for_next_level($session['user']['level']+1,$session['user']['dragonkills']);
                            if ($op==='autochallenge' && !$truant) throw new DomainException('Not truant.');
                            $master=$state['masters'][e_rand(0,count($state['masters'])-1)];
                            if ($op==='autochallenge') {
                                if ($session['user']['hitpoints']<$session['user']['maxhitpoints']) $session['user']['hitpoints']=$session['user']['maxhitpoints'];
                                modulehook('master-autochallenge');
                                if (getsetting('displaymasternews',1)) addnews('`3%s`3 was hunted down by their master, `^%s`3, for being truant.',$session['user']['name'],$master['creaturename']);
                            }
                            $session['user']['seenmaster']=1;
                            debuglog('Challenged master, setting seenmaster to 1');
                            if ($session['user']['experience']<exp_for_next_level($session['user']['level'],$session['user']['dragonkills'])) {
				output("You ready your %s and %s and approach `^%s`0.`n`n",$session['user']['weapon'],$session['user']['armor'],$master['creaturename']);
				output("A small crowd of onlookers has gathered, and you briefly notice the smiles on their faces, but you feel confident. ");
				output("You bow before `^%s`0, and execute a perfect spin-attack, only to realize that you are holding NOTHING!", $master['creaturename']);
				output("`^%s`0 stands before you holding your weapon.",$master['creaturename']);
				output("Meekly you retrieve your %s, and slink out of the training grounds to the sound of boisterous guffaws.",$session['user']['weapon']);
                                return;
                            }
                            restore_buff_fields(); $budget=\Resurrection\Game\TrainingCombatState::budget($session['user']); calculate_buff_fields();
                            $attack=min(e_rand(0,$budget),round($budget*.25));
                            $defense=min(e_rand(0,$budget-$attack),round($budget*.25));
                            $master['creatureattack']+=$attack; $master['creaturedefense']+=$defense;
                            $master['creaturehealth']+=($budget-$attack-$defense)*5;
                            $master['trainingmaxhp']=$master['creaturehealth']; $master['playerstarthp']=$session['user']['hitpoints'];
                            $master['dead']=false; $master['istarget']=true; $master['diddamage']=0;
                            $session['user']['badguy']=serialize(['enemies'=>[$master],'options'=>['type'=>'train',
                                'traininglevel'=>(int)$session['user']['level'],'encounter'=>bin2hex(random_bytes(16))]]);
                        } elseif ($state['combat']===[]) throw new DomainException('No master combat.');
                        $_GET=['op'=>in_array($op,['challenge','autochallenge'],true)?'challenge':'fight','auto'=>$rounds];
                        if ($op==='run') output('Your pride prevents you from running from this conflict!');
                        suspend_buffs('allowintrain'); suspend_companions('allowintrain');
                        $victory=false; $defeat=false;
                        require __DIR__ . '/../battle.php';
                        /** @var bool $victory */
                        /** @var bool $defeat */
                        require __DIR__ . '/training_outcomes.php';
                        if ($victory || $defeat) {
                            unsuspend_buffs('allowintrain'); unsuspend_companions('allowintrain');
                            $session['user']['badguy']='';
                        } else {
                            restore_buff_fields(); resurrection_training_state(); calculate_buff_fields();
                        }
                    } finally { restore_buff_fields(); }
                });
                $notifications=$GLOBALS['pvp_mail_notifications'];
            } finally { unset($GLOBALS['pvp_mail_notifications']); }
            foreach ($notifications as $notification) {
                try { resurrection_systemmail_notification(...$notification); } catch (Throwable) { /* Committed game state is authoritative. */ }
            }
            if ($session['user']['badguy']==='') {
                addnav('Question Master','train.php?op=question'); addnav('Challenge Master','train.php?op=challenge'); villagenav(); page_footer(); exit;
            }
            $context=resurrection_training_context();
            restore_buff_fields(); $state=resurrection_training_state(); calculate_buff_fields();
        }
        if ($state['combat']!==[]) {
            $GLOBALS['enemycounter']=1; show_enemies($state['combat']['enemies']);
            resurrection_training_form('fight','Fight',$context);
            if (getsetting('autofight',0)) {
                foreach (['five'=>'For 5 Rounds','ten'=>'For 10 Rounds'] as $rounds=>$label) resurrection_training_form('fight',$label,$context,$rounds);
                if (getsetting('autofightfull',0)) resurrection_training_form('fight','Until End',$context,'full');
            }
        } else {
            $required=exp_for_next_level($session['user']['level'],$session['user']['dragonkills']);
            $master=$state['masters'][0];
            if ($op==='question') {
                output("You approach `^%s`0 timidly and inquire as to your standing in the class.",$master['creaturename']);
                if ($session['user']['experience']>=$required) output("`n`n`^%s`0 says, \"Gee, your muscles are getting bigger than mine...\"",$master['creaturename']);
                else output("`n`n`^%s`0 states that you will need `%%s`0 more experience before you are ready to challenge him in battle.",$master['creaturename'],$required-$session['user']['experience']);
            } else {
                output("The sound of conflict surrounds you. The clang of weapons in grisly battle inspires your warrior heart. ");
                output("`n`n`^%s stands ready to evaluate you.`0",$master['creaturename']);
            }
            addnav('Question Master','train.php?op=question');
            if (!$session['user']['seenmaster']) resurrection_training_form($op==='autochallenge'?'autochallenge':'challenge','Challenge Master',$context);
            else output('You have seen enough of your master for today.');
            villagenav();
        }
        page_footer(); exit;
    } catch (InvalidArgumentException) { http_response_code(400); exit('Invalid training action.'); }
    catch (DomainException) { http_response_code(409); exit('Training unavailable. Stored state was preserved. Reload for a fresh form, or request operator repair if state remains invalid.'); }
    catch (Throwable) { http_response_code(500); exit('Training action was not completed. Request a fresh form.'); }
}
