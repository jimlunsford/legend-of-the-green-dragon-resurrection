<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/autoload.php';
// translator ready
// addnews ready
// mail ready
// A refusal while common.php loads answers in the game's frame when the page got as far as forced navigation, with
// PvP's way out (Phase 6 task 27, no dead ends, as train.php since task 26); before that, the plain text, as before.
$resurrection_pvp_refusal = null;
try { require_once 'common.php'; }
catch (DomainException) { $resurrection_pvp_refusal = [409, 'Invalid stored PvP state. Nothing was changed.']; }
catch (Throwable $error) { \Resurrection\Http\FailureLog::record($error); $resurrection_pvp_refusal = [500, 'PvP could not be loaded. Nothing was changed.']; }
if ($resurrection_pvp_refusal !== null) {
    if (function_exists('resurrection_refusal_framed') && resurrection_refusal_framed()) {
        require_once __DIR__ . '/lib/pvp_combat.php';
        resurrection_pvp_refuse(...$resurrection_pvp_refusal);
    }
    http_response_code($resurrection_pvp_refusal[0]); exit($resurrection_pvp_refusal[1]);
}
require_once("lib/fightnav.php");
require_once("lib/battle_view.php");
require_once("lib/pvpwarning.php");
require_once("lib/pvplist.php");
require_once("lib/pvpsupport.php");
require_once("lib/http.php");
require_once("lib/taunt.php");
require_once("lib/villagenav.php");

tlschema("pvp");

$iname = getsetting("innname", LOCATION_INN);
$battle = false;

page_header("PvP Combat!");
$op = '';
$act = '';

require_once 'lib/player_mutation.php';
require_once 'lib/pvp_combat.php';
try { resurrection_pvp_participants(false); }
catch (DomainException) { resurrection_pvp_refuse(409, 'Invalid stored PvP participants. Nothing was changed.'); }
try {
    $act = \Resurrection\Http\Input::choice($_GET,'act',['','attack'],'');
    $op = \Resurrection\Http\Input::choice($_GET,'op',['','fight','run'],'');
    \Resurrection\Http\Input::choice($_GET,'inn',['','1'],'');
    $automatic = \Resurrection\Http\Input::choice($_GET,'auto',['','five','ten','full'],'');
    if ($automatic !== '' && (!getsetting('autofight',0) || ($automatic === 'full' && !getsetting('autofightfull',0)))) throw new InvalidArgumentException();
    foreach (['skill','l','newtarget','type'] as $key) {
        if (\Resurrection\Http\Input::string($_GET,$key) !== '') throw new InvalidArgumentException();
    }
    if (array_diff(array_keys($_GET),['act','op','inn','auto','name','c']) !== []) throw new InvalidArgumentException();
    $targetId = $act === 'attack' ? \Resurrection\Http\Input::integer($_GET,'name',0,1) : 0;
    if ($act === 'attack' && ($targetId < 1 || $targetId > 2147483647 || $op !== '')) throw new InvalidArgumentException();
} catch (InvalidArgumentException $error) { resurrection_pvp_refuse(400, 'Invalid PvP action.'); }

if ($op === '' && $act === '') {
    if ($session['user']['badguy'] !== '') {
        try { \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']); }
        catch (DomainException $error) { resurrection_pvp_refuse(409, 'A different or invalid combat is pending.'); }
        resurrection_pvp_form('pvp.php?op=fight','pvp-round',resurrection_pvp_context(),'Fight','main',true);
    } else {
        checkday(); pvpwarning();
        $args = modulehook('pvpstart',['atkmsg'=>'`4You head out to the fields. You have `^%s`4 PvP fights left today.`n','schemas'=>['atkmsg'=>'pvp']]);
        output($args['atkmsg'],$session['user']['playerfights']);
        pvplist(); villagenav();
    }
    page_footer();
}
$url = 'pvp.php?' . http_build_query($_GET);
$scope = $act === 'attack' ? 'pvp-enter' : 'pvp-round';
try { $context = resurrection_pvp_context($targetId); }
catch (DomainException) { resurrection_pvp_refuse(409, 'Invalid stored PvP state. Nothing was changed.'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if ($act !== 'attack') {
        try { \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']); }
        catch (DomainException $error) { resurrection_pvp_refuse(409, 'No active PvP combat.'); }
    }
    if ($act === 'attack') resurrection_pvp_form($url,$scope,$context,'Attack');
    else resurrection_pvp_form($url,$scope,$context,'Fight','main',true);
    page_footer();
}
resurrection_consume_action($scope,$context);
$GLOBALS['mail_notifications'] = [];
try {
    resurrection_player_mutation(function () use ($act,$targetId,$iname,$op,$context) {
        global $session,$badguy,$options,$battle,$victory,$defeat,$attackstack;
        if (resurrection_pvp_context($targetId) !== $context) throw new DomainException('Stale PvP action.');
        resurrection_pvp_participants();
        if (empty($session['user']['alive']) || $session['user']['hitpoints'] <= 0) throw new DomainException('Invalid PvP actor.');
        if ($act === 'attack') {
            if ($session['user']['badguy'] !== '') throw new DomainException('Combat already pending.');
            $badguy = setup_target($targetId);
            if ($badguy === false) throw new DomainException('Target unavailable.');
            $badguy['pvpmaxhp']=$badguy['creaturehealth'];
            if ($badguy['location'] === $iname) $badguy['bodyguardlevel'] = $badguy['boughtroomtoday'];
            $options = ['type'=>'pvp','owner'=>(int)$session['user']['acctid'],'target'=>$targetId,
                'encounter'=>bin2hex(random_bytes(16)),'reservation'=>$badguy['pvpflag'],
                'victimhash'=>resurrection_pvp_account_hash($targetId),
                'enemyhash'=>\Resurrection\Security\PvpState::enemyHash($badguy)];
            $session['user']['badguy'] = createstring(['enemies'=>[$badguy],'options'=>$options]);
            $session['user']['playerfights']--;
        } else {
            $attackstack = \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']);
            $options = $attackstack['options'];
            if (resurrection_pvp_account_hash($options['target']) !== $options['victimhash']) throw new DomainException('PvP target changed.');
        }
        // Inn/bodyguard behavior comes from the recorded opponent, never a round parameter.
        $recorded = \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']);
        $_GET['inn'] = $recorded['enemies'][0]['location'] === $iname ? '1' : '';
        resurrection_pvp_bodyguard($recorded);
        $battle = true;
        if ($op=="run"){
          output("Your pride prevents you from running");
          $op="fight";
          httpset('op', $op);
        }

        $skill = httpget('skill');
        if ($skill!=""){
          output("Your honor prevents you from using any special ability");
          $skill="";
          httpset('skill', $skill);
        }
        if ($op=="fight" || $op=="run"){
        	$battle=true;
        }
        if ($battle){

        	require("battle.php");

        	if ($victory){
        		$killedin = $badguy['location'];
        		$handled = pvpvictory($badguy, $killedin, $options);

        		// Handled will be true if a module has already done the addnews or
        		// whatever was needed.
        		if (!$handled) {
        			if ($killedin==$iname){
        				addnews("`4%s`3 defeated `4%s`3 by sneaking into their room in the inn!",$session['user']['name'],$badguy['creaturename']);
        			}else{
        				addnews("`4%s`3 defeated `4%s`3 in fair combat in the fields of %s.", $session['user']['name'],$badguy['creaturename'], $killedin);
        			}
        		}

        		$op = "";
        		httpset('op', $op);
        		if ($killedin==$iname){
        			addnav("Return to the inn","inn.php");
        		} else {
        			villagenav();
        		}
        		if ($session['user']['hitpoints'] <= 0) {
        			output("`n`n`&Using a bit of cloth nearby, you manage to staunch your wounds so that you do not die as well.");
        			$session['user']['hitpoints'] = 1;
        		}
        	}elseif($defeat){
        		$killedin = $badguy['location'];
        		$taunt = select_taunt_array();
        		// This is okay because system mail which is all it's used for is
        		// not translated
        		$handled = pvpdefeat($badguy, $killedin, $taunt, $options);
        		// Handled will be true if a module has already done the addnews or
        		// whatever was needed.
        		if (!$handled) {
        			if ($killedin == $iname) {
        				addnews("`%%s`5 has been slain while breaking into the inn room of `^%s`5 in order to attack them.`n%s`0", $session['user']['name'], $badguy['creaturename'], $taunt);
        			}else {
        				addnews("`%%s`5 has been slain while attacking `^%s`5 in the fields of `&%s`5.`n%s`0", $session['user']['name'], $badguy['creaturename'], $killedin, $taunt);
        			}
        		}
            }
            if ($victory || $defeat) $session['user']['badguy'] = '';
            resurrection_pvp_balances((int)$options['target']);
            if (!$victory && !$defeat) \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid']);
        }
    }, [$act === 'attack' ? $targetId : \Resurrection\Security\PvpState::read($session['user']['badguy'],(int)$session['user']['acctid'])['options']['target']]);
} catch (DomainException $error) {
    unset($GLOBALS['mail_notifications']);
    resurrection_pvp_refuse(409, 'PvP action no longer available. Nothing was changed.');
} catch (Throwable $error) {
    error_log('PvP transaction failed: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine());
    unset($GLOBALS['mail_notifications']);
    resurrection_pvp_refuse(500, 'PvP action failed. Nothing was changed.');
}
$notifications = $GLOBALS['mail_notifications'];
unset($GLOBALS['mail_notifications']);
foreach ($notifications as $notification) {
    try { resurrection_systemmail_notification(...$notification); }
    catch (Throwable $error) { error_log('PvP notification delivery failed after commit.'); }
}
if (!$victory && !$defeat) {
    $extra = httpget('inn') === '1' ? '&inn=1' : '';
    $context = resurrection_pvp_context();
    resurrection_pvp_form('pvp.php?op=fight'.$extra,'pvp-round',$context,'Fight','main',true);
    if (getsetting('autofight',0)) {
        foreach (['five'=>'For 5 Rounds','ten'=>'For 10 Rounds'] as $auto=>$label) {
            resurrection_pvp_form('pvp.php?op=fight'.$extra.'&auto='.$auto,'pvp-round',$context,$label,'auto');
        }
        if (getsetting('autofightfull',0)) resurrection_pvp_form('pvp.php?op=fight'.$extra.'&auto=full','pvp-round',$context,'Until End','auto');
    }
}
page_footer();

/** A PvP form; a fight's action joins the battle screen's $group (Phase 5 task 10), the same form either way. */
function resurrection_pvp_form(string $url, string $scope, string $context, string $label, string $group = '', bool $primary = false): void {
    addnav('',$url);
    $html = '<form method="POST" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.
        resurrection_action_fields($scope,$context).'<button class="'.resurrection_button_class($primary).'">'.$label.'</button></form>';
    if ($group === '') rawoutput($html); else resurrection_battle_action($group, $html);
}
