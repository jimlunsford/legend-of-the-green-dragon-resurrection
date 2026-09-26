<?php
require_once __DIR__ . '/specialty_combat.php';
require_once __DIR__ . '/dragon_outcomes.php';

/** @param array{module:string,skill:int,uses:int}|null $specialty */
function resurrection_dragon_context(?array $specialty = null): string {
    global $session;
    $combat = resurrection_combat_context($specialty ?? ['module'=>'','skill'=>0,'uses'=>0]);
    $state = [];
    foreach (['dragonkills','dragonpoints','slaydragon','gold','gems','experience','charm','age','race','specialmisc','superuser'] as $key) {
        $state[$key] = $session['user'][$key];
    }
    return hash('sha256', json_encode([$combat,$state], JSON_THROW_ON_ERROR));
}

function resurrection_dragon_authority(string $op): void {
    global $session;
    if (empty($session['loggedin']) || empty($session['user']['alive']) ||
        $session['user']['level'] < 15 || $session['user']['specialinc'] !== '') throw new DomainException();
    if (in_array($op,['restart','godmode'],true) && !($session['user']['superuser'] & SU_DEVELOPER)) throw new DomainException();
    if ($op === 'prologue1') {
        \Resurrection\Game\DragonCombatState::victory($session['user']['badguy'], (int)$session['user']['dragonkills']);
    } elseif ($op === 'begin') {
        if ($session['user']['hitpoints'] <= 0 || $session['user']['badguy'] !== '') throw new DomainException();
    } else {
        if ($session['user']['hitpoints'] <= 0) throw new DomainException();
        \Resurrection\Game\DragonCombatState::read($session['user']['badguy']);
    }
}

function resurrection_dragon_form(string $op, string $label, string $extra = ''): void {
    $url = 'dragon.php?op='.$op;
    addnav('', $url);
    rawoutput('<form method="POST" action="'.$url.'">'.resurrection_action_fields('dragon-'.$op,resurrection_dragon_context()).
        $extra.'<button class="button">'.htmlspecialchars(translate_inline($label), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</button></form>');
}

function resurrection_dragon_forms(): void {
    global $session;
    if (empty($session['user']['alive'])) { addnav('Daily news','news.php'); return; }
    try {
        resurrection_dragon_authority('prologue1');
        addnav('Continue','dragon.php?op=prologue1');
        resurrection_dragon_form('prologue1','Continue');
        return;
    } catch (DomainException) { /* Live combat is a different validated state. */ }
    resurrection_dragon_authority('fight');
    resurrection_dragon_form('fight','Fight');
    if (getsetting('autofight',0)) {
        foreach (['five'=>'For 5 Rounds','ten'=>'For 10 Rounds'] as $auto=>$label) {
            resurrection_dragon_form('fight',$label,'<input type="hidden" name="auto" value="'.$auto.'">');
        }
        if (getsetting('autofightfull',0)) resurrection_dragon_form('fight','Until End','<input type="hidden" name="auto" value="full">');
    }
    resurrection_combat_forms('dragon');
    if ($session['user']['superuser'] & SU_DEVELOPER) {
        resurrection_dragon_form('godmode','GOD MODE');
        resurrection_dragon_form('restart','Restart encounter');
    }
}

/** Round, terminal message/news and durable outcome are one player transaction. */
function resurrection_dragon_round(string $op, string $level, string $auto): void {
    global $session, $badguy, $newenemies;
    if (in_array($op,['begin','restart'],true)) resurrection_dragon_create();
    $state = \Resurrection\Game\DragonCombatState::read($session['user']['badguy']);
    $state['options']['dragonEncounter'] ??= bin2hex(random_bytes(16));
    $encounter = $state['options']['dragonEncounter'];
    $session['user']['badguy'] = serialize($state);
    $_GET = ['op'=>in_array($op,['begin','restart'],true) ? '' : 'fight'];
    if ($op === 'specialty') $_GET += ['skill'=>$session['user']['specialty'],'l'=>$level];
    if ($op === 'godmode') $_GET['skill'] = 'godmode';
    if ($auto !== '') $_GET['auto'] = $auto;
    if ($op === 'run') output("The creature's tail blocks the only exit to its lair!");
    $GLOBALS['module_prefs'] = [];
    $victory = false; $defeat = false;
    require __DIR__ . '/../battle.php';
    /** @var bool $victory */
    /** @var bool $defeat */
    if ($victory) {
        $flawless = $newenemies[0]['diddamage'] != 1;
        output('`&With a mighty final blow, `@The Green Dragon`& lets out a tremendous bellow and falls at your feet, dead at last.');
        addnews('`&%s has slain the hideous creature known as `@The Green Dragon`&.  All across the land, people rejoice!', $session['user']['name']);
        $session['user']['badguy'] = serialize(['dragonVictory'=>$flawless,'dragonkills'=>(int)$session['user']['dragonkills'],'dragonEncounter'=>$encounter]);
    } elseif ($defeat) {
        $taunt = select_taunt_array();
        if ($session['user']['sex']) {
            addnews('`%%s`5 has been slain when she encountered `@The Green Dragon`5!!!  Her bones now litter the cave entrance, just like the bones of those who came before.`n%s',$session['user']['name'],$taunt);
        } else {
            addnews('`%%s`5 has been slain when he encountered `@The Green Dragon`5!!!  His bones now litter the cave entrance, just like the bones of those who came before.`n%s',$session['user']['name'],$taunt);
        }
        debuglog("lost {$session['user']['gold']} gold when they were slain");
        $session['user']['alive'] = false;
        $session['user']['gold'] = 0;
        $session['user']['hitpoints'] = 0;
        $session['user']['badguy'] = '';
        output('`b`&You have been slain by `@The Green Dragon`&!!!`n');
        output('`4All gold on hand has been lost!`n');
        output('You may begin fighting again tomorrow.');
    }
    restore_buff_fields();
}

function resurrection_dragon_controller(): void {
    global $session;
    try {
        $op = \Resurrection\Http\Input::choice($_GET,'op',['','begin','fight','run','specialty','prologue1','godmode','restart'],'');
        if (array_diff(array_keys($_GET),['op','c','nointro']) !== [] ||
            (isset($_GET['nointro']) && !in_array($_GET['nointro'],['0','1'],true))) throw new InvalidArgumentException();
        if ($op === '') $op = $session['user']['badguy'] === '' ? 'begin' : 'fight';
        resurrection_dragon_authority($op);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            if ($op === 'begin') {
                if (empty($_GET['nointro'])) {
                    output('`$Fighting down every urge to flee, you cautiously enter the cave entrance, intent on catching the great green dragon sleeping, so that you might slay it with a minimum of pain.');
                    output('Sadly, this is not to be the case, for as you round a corner within the cave you discover the great beast sitting on its haunches on a huge pile of gold, picking its teeth with a rib.');
                }
                resurrection_dragon_form('begin','Confront the Green Dragon');
            } else {
                if ($op === 'specialty') resurrection_combat_specialty(false,'dragon');
                resurrection_dragon_forms();
            }
            return;
        }
        $specialty = $op === 'specialty' ? resurrection_combat_specialty(false,'dragon') : null;
        $context = resurrection_dragon_context($specialty);
        resurrection_consume_action('dragon-'.$op,$context);
        $allowed = ['csrf_token','action_token'];
        if ($op === 'specialty') $allowed[] = 'level';
        if ($op === 'fight') $allowed[] = 'auto';
        if (array_diff(array_keys($_POST),$allowed) !== []) throw new InvalidArgumentException();
        $level = $op === 'specialty' ? \Resurrection\Http\Input::choice($_POST,'level',['1','2','3','5'],'') : '';
        if ($op === 'specialty' && $level === '') throw new InvalidArgumentException();
        $auto = $op === 'fight' ? \Resurrection\Http\Input::choice($_POST,'auto',['','five','ten','full'],'') : '';
        if ($auto !== '' && (!getsetting('autofight',0) || ($auto === 'full' && !getsetting('autofightfull',0)))) throw new DomainException();
        resurrection_player_mutation(function () use ($op,$level,$auto,$context) {
            global $session;
            resurrection_dragon_authority($op);
            $locked = $op === 'specialty' ? resurrection_combat_specialty(true,'dragon') : null;
            if (resurrection_dragon_context($locked) !== $context || ($locked !== null &&
                ($locked['skill'] < (int)$level || $locked['uses'] < (int)$level))) throw new DomainException();
            if ($op === 'prologue1') {
                $outcome = \Resurrection\Game\DragonCombatState::victory($session['user']['badguy'],(int)$session['user']['dragonkills']);
                resurrection_dragon_complete($outcome['dragonVictory']);
                restore_buff_fields();
            } else resurrection_dragon_round($op,$level,$auto);
        });
        if ($op !== 'prologue1') resurrection_dragon_forms();
    } catch (InvalidArgumentException) { http_response_code(400); exit('Invalid Dragon action.'); }
    catch (DomainException) { http_response_code(409); exit('Dragon state changed or is unavailable. Request a fresh form.'); }
    catch (Throwable) { http_response_code(500); exit('Dragon action was not completed. Request a fresh form.'); }
}
