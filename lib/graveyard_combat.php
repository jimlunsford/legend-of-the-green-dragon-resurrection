<?php
require_once __DIR__.'/forest_combat.php';
require_once __DIR__.'/extended-battle.php';
require_once __DIR__.'/taunt.php';
require_once __DIR__.'/../src/Game/GraveyardCombatState.php';

/** @return array<string,mixed> */
function resurrection_graveyard_state(): array {
    global $session,$baseaccount,$companions;
    if (empty($session['loggedin']) || $session['user']['specialinc']!=='') throw new DomainException('Torment unavailable.');
    \Resurrection\Game\GraveyardCombatState::player($baseaccount);
    \Resurrection\Game\GraveyardCombatState::player($session['user']);
    \Resurrection\Game\ForestBuffState::validate(\Resurrection\Security\ScalarState::read($baseaccount['bufflist']),$session['user']);
    \Resurrection\Game\ForestBuffState::validate($session['bufflist'],$session['user']);
    \Resurrection\Game\ForestCompanionState::validate(\Resurrection\Security\ScalarState::read($baseaccount['companions']));
    \Resurrection\Game\ForestCompanionState::validate($companions);
    if ($session['user']['badguy']==='') return [];
    $state=\Resurrection\Game\GraveyardCombatState::read($session['user']['badguy'],$session['user']);
    $rows=db_query('SELECT creatureid,creaturename,creatureweapon,creaturelose,creaturewin,creatureaiscript FROM '.db_prefix('creatures').' WHERE graveyard=1 AND creatureid=?',true,[$state['enemies'][0]['creatureid']]);
    if (count($rows)!==1 || (string)$rows[0]['creatureaiscript']!=='') throw new DomainException('Invalid torment identity.');
    unset($rows[0]['creatureaiscript']);
    foreach ($companions as $companion) if (empty($companion['allowinshades']) && empty($companion['suspended'])) throw new DomainException('Invalid Shades participation.');
    foreach ($rows[0] as $key=>$value) if ((string)$state['enemies'][0][$key] !== (string)$value) throw new DomainException('Torment identity changed.');
    return $state;
}
function resurrection_graveyard_context(): string {
    global $session,$companions;
    restore_buff_fields();
    try {
        $state=resurrection_graveyard_state(); $player=[];
        foreach (['acctid','alive','hitpoints','soulpoints','deathpower','gravefights','level','attack','defense','maxhitpoints',
            'specialinc','specialmisc','authversion','lasthit','name','sex','dragonkills','dragonpoints'] as $key) $player[$key]=is_array($session['user'][$key])?$session['user'][$key]:(string)$session['user'][$key];
        $buffs=[]; foreach ($session['bufflist'] as $id=>$buff) { unset($buff['fields_calculated'],$buff['tempstats_calculated']); $buffs[]=[$id,$buff]; }
        $ordered=[]; foreach ($companions as $id=>$companion) $ordered[]=[$id,$companion];
        $settings=[]; foreach (['gravechance'=>0,'enablecompanions'=>true,'maxattacks'=>4,'autofight'=>0,'autofightfull'=>0] as $key=>$default) $settings[$key]=(string)getsetting($key,$default);
        $prefs=db_query('SELECT modulename,setting,value FROM '.db_prefix('module_userprefs').' WHERE userid=? ORDER BY modulename,setting',true,[(int)$session['user']['acctid']]);
        return hash('sha256',json_encode(resurrection_forest_canonical([$state,$player,$buffs,$ordered,$settings,$prefs]),JSON_THROW_ON_ERROR));
    } finally { calculate_buff_fields(); }
}
function resurrection_graveyard_form(string $op,string $label,string $context,string $rounds=''): void {
    $url='graveyard.php?op='.$op; addnav('',$url);
    rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('graveyard-'.$op,$context).
        ($rounds!==''?'<input type="hidden" name="rounds" value="'.$rounds.'">':'').
        '<button class="button">'.htmlspecialchars(translate_inline($label),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</button></form>');
}
/** Combat GETs render only. Noncombat mausoleum and event routes retain their ownership. */
function resurrection_graveyard_combat(): never {
    global $session,$companions;
    try {
        $op=\Resurrection\Http\Input::choice($_GET,'op',['','search','fight','run'],'');
        if (array_diff(array_keys($_GET),['op','c'])!==[] || array_diff(array_keys($_POST),['csrf_token','action_token','rounds'])!==[]) throw new InvalidArgumentException();
        define('RESURRECTION_GRAVEYARD_COMBAT',true);
        if (($_SERVER['REQUEST_METHOD'] ?? '')==='GET') define('RESURRECTION_GRAVEYARD_READ_ONLY',true);
        page_header('The Graveyard');
        $context=resurrection_graveyard_context();
        restore_buff_fields(); $state=resurrection_graveyard_state(); calculate_buff_fields();
        if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') {
            if ($op==='') throw new InvalidArgumentException();
            resurrection_consume_action('graveyard-'.$op,$context);
            $rounds=\Resurrection\Http\Input::choice($_POST,'rounds',['','five','ten','full'],'');
            if ($rounds!=='' && ($op!=='fight' || !getsetting('autofight',0) || ($rounds==='full' && !getsetting('autofightfull',0)))) throw new InvalidArgumentException();
            resurrection_player_mutation(function () use ($op,$context,$rounds) {
                global $session,$companions,$newenemies;
                if (resurrection_graveyard_context()!==$context) throw new DomainException('Stale torment.');
                restore_buff_fields(); $state=resurrection_graveyard_state();
                $deathoverlord=getsetting('deathoverlord','`$Ramius');
                if ($op==='search') {
                    if ($state!==[] || $session['user']['gravefights']<=0 || is_new_day()) throw new DomainException('Search unavailable.');
                } elseif ($state===[]) throw new DomainException('No torment.');
                // The shipped drinks header hook sobers the dead player, also transaction-owned.
                modulehook('header-graveyard');
                // Historical stripping belongs to the committed combat transition, never rendering.
                strip_all_buffs();
                $_GET=['op'=>$op,'auto'=>$rounds];
                if ($op==='search') {
                    suspend_companions('allowinshades',true);
                    if (module_events('graveyard',getsetting('gravechance',0),'graveyard.php?',true)!==0) return;
                    $session['user']['gravefights']--;
                    $rows=db_query('SELECT creatureid,creaturename,creatureweapon,creaturelose,creaturewin,creatureaiscript FROM '.db_prefix('creatures').' WHERE graveyard=1 ORDER BY rand('.e_rand().') LIMIT 1');
                    if (count($rows)!==1 || (string)$rows[0]['creatureaiscript']!=='') throw new DomainException('Unsupported torment enemy.');
                    unset($rows[0]['creatureaiscript']);
                    $enemy=$rows[0];
                    foreach (['creaturename','creatureweapon','creaturelose','creaturewin'] as $key) $enemy[$key]=(string)$enemy[$key];
                    $level=(int)$session['user']['level'];
                    $enemy['creatureattack']=9+($level<5?-1:0)+(int)(($level-1)*1.5);
                    $enemy['creaturedefense']=$enemy['creatureattack']*.7;
                    $enemy['creaturehealth']=$level*5+50;
                    $enemy['creatureexp']=e_rand(10+round($level/3),20+round($level/3));
                    $enemy['creaturelevel']=$level; $enemy['playerstarthp']=(int)$session['user']['soulpoints'];
                    $enemy['dead']=false; $enemy['istarget']=true; $enemy['diddamage']=0;
                    $session['user']['badguy']=serialize(['enemies'=>[$enemy],'options'=>['type'=>'graveyard','encounter'=>bin2hex(random_bytes(16))]]);
                } elseif ($op==='run' && e_rand(0,2)==1) {
                    $favor=min(5+e_rand(0,$session['user']['level']),$session['user']['deathpower']);
                    $session['user']['deathpower']-=$favor;
                    output('`$%s`) curses you for your cowardice. You have LOST %s favor.',$deathoverlord,$favor);
                    // Historical return navigation ended the encounter; discard the orphaned live blob.
                    $session['user']['badguy']='';
                    return;
                } elseif ($op==='run') output('As you try to flee, you are summoned back to the fight!');
                $original=[]; foreach (['hitpoints','attack','defense'] as $key) $original[$key]=$session['user'][$key];
                $session['user']['hitpoints']=$session['user']['soulpoints'];
                $session['user']['attack']=$session['user']['defense']=10+round(($session['user']['level']-1)*1.5);
                try {
                    $victory=false; $defeat=false;
                    require __DIR__.'/../battle.php';
                    /** @var bool $victory */
                    /** @var bool $defeat */
                    $session['user']['soulpoints']=$session['user']['hitpoints'];
                } finally { foreach ($original as $key=>$value) $session['user'][$key]=$value; }
                if ($victory) {
                    $enemy=$newenemies[0];
                    \Resurrection\Game\GraveyardCombatState::integer($session['user']['deathpower']+$enemy['creatureexp'],0,4294967295);
                    $session['user']['deathpower']+=$enemy['creatureexp'];
                    output_notl('`b`&%s`0`b`n',translate_inline($enemy['creaturelose']));
                    output('You have tormented %s! You receive %s favor with %s.',$enemy['creaturename'],$enemy['creatureexp'],$deathoverlord);
                    $session['user']['badguy']='';
                } elseif ($defeat) {
                    addnews('`)%s`) has been defeated in the graveyard by %s.`n%s',$session['user']['name'],$newenemies[0]['creaturename'],select_taunt_array());
                    output('You have been defeated by %s. You may not torment any more souls today.',$newenemies[0]['creaturename']);
                    $session['user']['gravefights']=0; $session['user']['badguy']='';
                }
                \Resurrection\Game\GraveyardCombatState::player($session['user']);
                if ($session['user']['badguy']!=='') \Resurrection\Game\GraveyardCombatState::read($session['user']['badguy'],$session['user']);
                \Resurrection\Game\ForestCompanionState::validate($companions);
            });
            if ($session['user']['specialinc']!=='') { page_footer(); exit; }
            $context=resurrection_graveyard_context();
            restore_buff_fields(); $state=resurrection_graveyard_state(); calculate_buff_fields();
        }
        if ($state!==[]) {
            $GLOBALS['enemycounter']=1; show_enemies($state['enemies']);
            resurrection_graveyard_form('fight','Fight',$context); resurrection_graveyard_form('run','Run',$context);
            if (getsetting('autofight',0)) {
                foreach (['five'=>'For 5 Rounds','ten'=>'For 10 Rounds'] as $rounds=>$label) resurrection_graveyard_form('fight',$label,$context,$rounds);
                if (getsetting('autofightfull',0)) resurrection_graveyard_form('fight','Until End',$context,'full');
            }
        } else {
            $skipgraveyardtext=false; $deathoverlord=getsetting('deathoverlord','`$Ramius');
            $graveyardCombatContext=$context;
            require __DIR__.'/graveyard/case_default.php';
            if ($session['user']['gravefights']<=0) output('Your soul can bear no more torment in this afterlife.');
        }
        page_footer(); exit;
    } catch (InvalidArgumentException) { http_response_code(400); exit('Invalid Graveyard action.'); }
    catch (DomainException) { http_response_code(409); exit('Graveyard combat unavailable. Stored state was preserved. Reload for a fresh form, or request operator repair if state remains invalid.'); }
    catch (Throwable) { http_response_code(500); exit('Graveyard action was not completed. Request a fresh form.'); }
}
