<?php
require_once __DIR__ . '/specialty_combat.php';
require_once __DIR__ . '/forest_encounter.php';
require_once __DIR__ . '/battle-skills.php';
require_once __DIR__ . '/../src/Game/ForestCompanionState.php';
require_once __DIR__ . '/../src/Game/ForestCombatState.php';
require_once __DIR__ . '/../src/Game/ForestBuffState.php';

/** @return array<string,mixed> */
function resurrection_forest_state(bool $entry = false): array {
    global $session, $companions;
    if (empty($session['loggedin']) || empty($session['user']['alive']) || $session['user']['hitpoints'] <= 0 ||
        $session['user']['specialinc'] !== '') throw new DomainException('Forest unavailable.');
    \Resurrection\Game\ForestBuffState::validate($session['bufflist'],$session['user']);
    \Resurrection\Game\ForestCompanionState::validate($companions);
    if ($entry) {
        if ($session['user']['badguy'] !== '') throw new DomainException('Encounter already active.');
        return [];
    }
    return \Resurrection\Game\ForestCombatState::read($session['user']['badguy']);
}

/** Sort maps recursively, preserving enemy indices. Serialization insertion order is not authority. */
function resurrection_forest_canonical(mixed $value): mixed {
    if (!is_array($value)) return $value;
    ksort($value);
    foreach ($value as $key=>$item) $value[$key]=resurrection_forest_canonical($item);
    return $value;
}

function resurrection_forest_context(bool $entry = false): string {
    global $session, $companions;
    $combat=resurrection_forest_state($entry);
    restore_buff_fields();
    $buffs=$session['bufflist'];
    foreach ($buffs as &$buff) unset($buff['fields_calculated'],$buff['tempstats_calculated']);
    unset($buff);
    $player=[];
    foreach (['acctid','hitpoints','maxhitpoints','attack','defense','level','alive','specialinc','location',
        'gold','gems','experience','turns','dragonkills','dragonpoints','seendragon','specialty','lasthit'] as $key) $player[$key]=is_array($session['user'][$key]) ? $session['user'][$key] : (string)$session['user'][$key];
    $settings=[];
    foreach (['enablecompanions'=>true,'instantexp'=>false,'dropmingold'=>0,'forestgemchance'=>25,'forestexploss'=>10,
        'addexp'=>5,'maxattacks'=>4,'autofight'=>0,'autofightfull'=>0,'suicide'=>0,'suicidedk'=>10] as $key=>$default) $settings[$key]=(string)getsetting($key,$default);
    // Effect collection order is gameplay order; only each record's field order is immaterial.
    $orderedBuffs=[]; foreach ($buffs as $id=>$buff) $orderedBuffs[]=[$id,$buff];
    $orderedCompanions=[]; foreach ($companions as $id=>$companion) $orderedCompanions[]=[$id,$companion];
    $context=hash('sha256',json_encode(resurrection_forest_canonical([$combat,$player,$orderedBuffs,$orderedCompanions,$settings]),JSON_THROW_ON_ERROR));
    calculate_buff_fields();
    return $context;
}

function resurrection_forest_form(string $op,string $label,string $context,string $fields=''): void {
    $url='forest.php?op='.$op; addnav('',$url);
    rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('forest-'.$op,$context).$fields.
        '<button class="button">'.htmlspecialchars(translate_inline($label),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</button></form>');
}

function resurrection_forest_forms(): void {
    $context=resurrection_forest_context();
    resurrection_forest_form('fight','Fight',$context);
    resurrection_forest_form('run','Run',$context);
    if (getsetting('autofight',0)) {
        foreach (['five'=>'For 5 Rounds','ten'=>'For 10 Rounds'] as $rounds=>$label) resurrection_forest_form('fight',$label,$context,'<input type="hidden" name="rounds" value="'.$rounds.'">');
        if (getsetting('autofightfull',0)==1) resurrection_forest_form('fight','Until current enemy dies',$context,'<input type="hidden" name="rounds" value="full">');
    }
    resurrection_combat_forms();
}

/** GET is presentation only. Invalid state is preserved for explicit operator repair. */
function resurrection_forest_combat(): never {
    global $session, $companions;
    $op=httpget('op');
    if ($op === '') $op='fight';
    try {
        if (!is_string($op) || !in_array($op,['search','fight','run','newtarget','dragon'],true)) throw new InvalidArgumentException();
        if (array_diff(array_keys($_GET),['op','c','type','auto','newtarget']) !== []) throw new InvalidArgumentException();
        $entry=in_array($op,['search','dragon'],true);
        $state=resurrection_forest_state($entry);
        page_header('The Forest');
        $context=resurrection_forest_context($entry);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            if ($entry) {
                $type=\Resurrection\Http\Input::choice($_GET,'type',['','slum','thrill','suicide'],'');
                resurrection_forest_form($op,$op==='search'?'Look for Something to Kill':'Seek Out the Green Dragon',$context,
                    $op==='search'?'<input type="hidden" name="type" value="'.$type.'">':'');
            } else {
                require_once __DIR__ . '/extended-battle.php';
                $GLOBALS['enemycounter']=count($state['enemies']);
                show_enemies(autosettarget($state['enemies']));
                resurrection_forest_forms();
            }
            page_footer(); exit;
        }
        resurrection_consume_action('forest-'.$op,$context);
        if ($op==='newtarget' || array_diff(array_keys($_GET),['op','c']) !== [] ||
            array_diff(array_keys($_POST),['csrf_token','action_token','type','rounds']) !== []) throw new InvalidArgumentException();
        $type=\Resurrection\Http\Input::choice($_POST,'type',['','slum','thrill','suicide'],'');
        $rounds=\Resurrection\Http\Input::choice($_POST,'rounds',['','five','ten','full'],'');
        if (($type!=='' && $op!=='search') || ($rounds!=='' && ($op!=='fight' || !getsetting('autofight',0))) ||
            ($rounds==='full' && getsetting('autofightfull',0)!=1)) throw new InvalidArgumentException();
        if ($op==='search' && is_new_day()) throw new DomainException('Begin the new day first.');
        resurrection_player_mutation(function () use ($context,$entry,$op,$type,$rounds) {
            global $session,$companions,$options,$newenemies;
            if (resurrection_forest_context($entry)!==$context) throw new DomainException();
            try {
            $_GET=['op'=>$op,'type'=>$type,'auto'=>$rounds];
            if ($op==='dragon') {
                if ($session['user']['level']<15 || $session['user']['seendragon']) throw new DomainException();
                $session['user']['seendragon']=1;
	require_once("lib/partner.php");
	addnav("Enter the cave","dragon.php");
	addnav("Run away like a baby","inn.php?op=fleedragon");
	output("`\$You approach the blackened entrance of a cave deep in the forest, though the trees are scorched to stumps for a hundred yards all around.");
	output("A thin tendril of smoke escapes the roof of the cave's entrance, and is whisked away by a suddenly cold and brisk wind.");
	output("The mouth of the cave lies up a dozen feet from the forest floor, set in the side of a cliff, with debris making a conical ramp to the opening.");
	output("Stalactites and stalagmites near the entrance trigger your imagination to inspire thoughts that the opening is really the mouth of a great leech.`n`n");
	output("You cautiously approach the entrance of the cave, and as you do, you hear, or perhaps feel a deep rumble that lasts thirty seconds or so, before silencing to a breeze of sulfur-air which wafts out of the cave.");
	output("The sound starts again, and stops again in a regular rhythm.`n`n");
	output("You clamber up the debris pile leading to the mouth of the cave, your feet crunching on the apparent remains of previous heroes, or perhaps hors d'oeuvres.`n`n");
	output("Every instinct in your body wants to run, and run quickly, back to the warm inn, and the even warmer %s`\$.", get_partner());
	output("What do you do?`0");
                return;
            }
            if ($op==='search') {
                if ($session['user']['turns']<=0 || ($type==='slum' && $session['user']['level']<=1) ||
                    ($type==='suicide' && (!getsetting('suicide',0) || $session['user']['dragonkills']<getsetting('suicidedk',10)))) throw new DomainException();
                if (!resurrection_forest_encounter()) return;
                resurrection_forest_state();
            }
            if ($op==='run' && e_rand()%3===0) {
                unsuspend_buffs();
                foreach ($companions as $key=>$companion) if (!empty($companion['expireafterfight'])) unset($companions[$key]);
                $session['user']['badguy']='';
                output('`c`b`&You have successfully fled your opponent!`0`b`c`n');
                return;
            }
            if ($op==='run') output('`c`b`\$You failed to flee your opponent!`0`b`c');
            $victory=false; $defeat=false;
            require __DIR__ . '/../battle.php';
            /** @var bool $victory */
            /** @var bool $defeat */
            require_once __DIR__ . '/forestoutcomes.php';
            if ($victory) { forestvictory($newenemies,$options['denyflawless'] ?? false); $session['user']['badguy']=''; }
            elseif ($defeat) { forestdefeat($newenemies,'in the forest',false); $session['user']['badguy']=''; }
            else resurrection_forest_state();
            } finally { restore_buff_fields(); }
        });
        if ($session['user']['badguy']!=='' && $session['user']['specialinc']==='') resurrection_forest_forms();
        elseif ($session['user']['specialinc']==='') { addnav('The Forest','forest.php'); addnav('Return to the village','village.php'); }
    } catch (InvalidArgumentException) { http_response_code(400); exit('Invalid Forest action.'); }
    catch (DomainException) { http_response_code(409); exit('Forest combat unavailable. Stored state was preserved; reload for a fresh form, or request operator repair if it remains invalid.'); }
    catch (Throwable) { http_response_code(500); exit('Forest action was not completed. Request a fresh form.'); }
    page_footer(); exit;
}
