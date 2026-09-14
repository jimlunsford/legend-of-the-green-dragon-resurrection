<?php
require_once __DIR__ . '/specialty_onboarding.php';
require_once __DIR__ . '/../src/Game/SpecialtyCombatState.php';

/** @return array{module:string,skill:int,uses:int} */
function resurrection_combat_specialty(bool $lock = false): array {
    global $session;
    if (empty($session['loggedin']) || empty($session['user']['alive']) || $session['user']['hitpoints'] <= 0 ||
        $session['user']['specialinc'] !== '') throw new DomainException('Specialty unavailable.');
    \Resurrection\Game\SpecialtyCombatState::read($session['user']['badguy']);
    $choices = resurrection_specialty_choices($lock);
    $selected = $session['user']['specialty'];
    if (!isset($choices[$selected])) throw new DomainException('Specialty unavailable.');
    $module = $choices[$selected];
    $prefs = resurrection_specialty_preferences([$selected=>$module],$lock)[$module];
    return ['module'=>$module,'skill'=>$prefs['skill'],'uses'=>$prefs['uses']];
}

/** @param array{module:string,skill:int,uses:int} $specialty */
function resurrection_combat_context(array $specialty): string {
    global $session;
    restore_buff_fields();
    $session['user']['bufflist'] = serialize($session['bufflist']);
    $state = [];
    foreach (['acctid','specialty','badguy','companions','bufflist','hitpoints','maxhitpoints','attack','defense','level','alive','lasthit','specialinc','location'] as $key) $state[$key]=(string)$session['user'][$key];
    $context = hash('sha256',json_encode([$state,$specialty], JSON_THROW_ON_ERROR));
    calculate_buff_fields();
    return $context;
}

function resurrection_combat_forms(): void {
    global $session;
    try { $specialty = resurrection_combat_specialty(); }
    catch (DomainException) { return; }
    $labels = [
        'DA'=>[1=>'Skeleton Crew',2=>'Voodoo',3=>'Curse Spirit',5=>'Wither Soul'],
        'MP'=>[1=>'Regeneration',2=>'Earth Fist',3=>'Siphon Life',5=>'Lightning Aura'],
        'TS'=>[1=>'Insult',2=>'Poison Attack',3=>'Hidden Attack',5=>'Backstab'],
    ];
    // Bind the committed-form state, after battle has serialized buffs/companions.
    $context = resurrection_combat_context($specialty);
    $url = 'forest.php?op=specialty'; addnav('',$url);
    foreach ($labels[$session['user']['specialty']] as $level=>$label) {
        if ($specialty['skill'] < $level || $specialty['uses'] < $level) continue;
        rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('forest-specialty',$context).
            '<input type="hidden" name="level" value="'.$level.'"><button class="button">'.
            htmlspecialchars(translate_inline($label),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' ('.$level.')</button></form>');
    }
}

/** Forest owns the round AND its outcome; do not nest this inside PvP's transaction. */
function resurrection_forest_specialty(): never {
    global $session;
    page_header('Special Abilities');
    try {
        $specialty = resurrection_combat_specialty();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            resurrection_combat_forms(); page_footer(); exit;
        }
        $context = resurrection_combat_context($specialty);
        resurrection_consume_action('forest-specialty',$context);
        if (array_diff(array_keys($_POST),['csrf_token','action_token','level']) !== [] ||
            array_diff(array_keys($_GET),['op','c']) !== []) throw new InvalidArgumentException();
        $level = \Resurrection\Http\Input::choice($_POST,'level',['1','2','3','5'],'');
        if ($level === '') throw new InvalidArgumentException();
        resurrection_player_mutation(function () use ($context,$level) {
            global $session, $options, $newenemies;
            $locked = resurrection_combat_specialty(true);
            if (resurrection_combat_context($locked) !== $context || $locked['skill'] < (int)$level || $locked['uses'] < (int)$level) throw new DomainException();
            // Legacy handlers receive only server-selected identity and typed level.
            $_GET = ['op'=>'fight','skill'=>$session['user']['specialty'],'l'=>$level];
            $GLOBALS['module_prefs'] = [];
            $victory = false; $defeat = false;
            require __DIR__ . '/../battle.php';
            // battle.php assigns these include-scope result flags.
            /** @var bool $victory */
            /** @var bool $defeat */
            require_once __DIR__ . '/forestoutcomes.php';
            if ($victory) {
                forestvictory($newenemies,$options['denyflawless'] ?? false);
                $session['user']['badguy']='';
            } elseif ($defeat) {
                forestdefeat($newenemies,'in the forest',false);
                $session['user']['badguy']='';
            }
            restore_buff_fields();
        });
        addnav('Continue','forest.php?op=specialty');
        if ($session['user']['badguy'] !== '') resurrection_combat_forms();
        else { addnav('Return to the village','village.php'); }
    } catch (InvalidArgumentException) { http_response_code(400); exit('Invalid specialty action.'); }
    catch (DomainException) { http_response_code(409); exit('Specialty combat is unavailable. Request a fresh form.'); }
    catch (Throwable) { http_response_code(500); exit('Specialty combat was not completed. Request a fresh form.'); }
    page_footer(); exit;
}
