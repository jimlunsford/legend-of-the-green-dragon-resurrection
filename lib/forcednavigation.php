<?php
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Game/BuffState.php';
require_once __DIR__ . '/../src/Game/CompanionState.php';
require_once __DIR__ . '/../src/Security/NavigationState.php';
require_once __DIR__ . '/../src/Security/PreferenceState.php';
// translator ready
// addnews ready
// mail ready

$baseaccount = array();
function do_forced_nav($anonymous,$overrideforced){
	global $baseaccount, $session,$REQUEST_URI;
	rawoutput("<!--\nAllowAnonymous: ".($anonymous?"True":"False")."\nOverride Forced Nav: ".($overrideforced?"True":"False")."\n-->");
	if (isset($session['loggedin']) && $session['loggedin']){
		$sql = "SELECT *  FROM ".db_prefix("accounts")." WHERE acctid = ?";
		$result = db_query($sql, true, [(int)$session['user']['acctid']]);
		if (db_num_rows($result)==1){
			$session['user']=db_fetch_assoc($result);
			$baseaccount = $session['user'];
            unset($session['user']['password']);
            if (isset($_SESSION['auth_privileges']) && $_SESSION['auth_privileges'] !== (int)$session['user']['superuser']) {
                resurrection_rotate_session();
            }
            $_SESSION['auth_privileges'] = (int)$session['user']['superuser'];
            // Preserve the existing potion validator's controlled 400 response.
            // Validate it before the shared map check can classify a null entry.
            $storedbuffs = in_array($session['user']['bufflist'], [null, ''], true) ? [] : \Resurrection\Security\ScalarState::read($session['user']['bufflist']);
            if (is_array($storedbuffs) && array_key_exists('transmute',$storedbuffs)) {
                require_once __DIR__ . '/../src/Game/TransmutationState.php';
                try { \Resurrection\Game\TransmutationState::read($storedbuffs['transmute']); }
                catch (DomainException $error) { http_response_code(400); exit('Invalid stored potion state.'); }
            }
            try {
                $session['bufflist']=\Resurrection\Game\BuffState::read($session['user']['bufflist'], $session['user']);
            } catch (DomainException $error) {
                http_response_code(409); exit('Invalid stored buff state. No game action was completed.');
            }
            // Checked at session start as the buffs are; common.php hydrates the normalized list.
            try {
                \Resurrection\Game\CompanionState::read($session['user']['companions']);
            } catch (DomainException $error) {
                http_response_code(409); exit('Invalid stored companion state. No game action was completed.');
            }
			$session['user']['dragonpoints']=\Resurrection\Security\ScalarState::read($session['user']['dragonpoints']);
			if (!is_array($session['user']['dragonpoints'])) $session['user']['dragonpoints']=array();
            try {
                $session['user']['prefs']=\Resurrection\Security\PreferenceState::read($session['user']['prefs']);
            } catch (DomainException $error) {
                http_response_code(409); exit('Invalid stored preference state. No game action was completed.');
            }
            try {
                $session['allowednavs']=\Resurrection\Security\NavigationState::read($session['user']['allowednavs']);
                \Resurrection\Security\NavigationState::restorePage($session['user']['restorepage']);
            } catch (DomainException $error) {
                http_response_code(409); exit('Invalid stored navigation state. No game action was completed.');
            }
			if (!isset($_SESSION['auth_version']) || $_SESSION['auth_version'] !== (int)$session['user']['authversion'] || $session['user']['locked'] || !$session['user']['loggedin'] || ( (date("U") - strtotime($session['user']['laston'])) > getsetting("LOGINTIMEOUT",900)) ){
				resurrection_end_session();
                header('Location: index.php?op=timeout', true, 303);
                exit();
			}
		}else{
			resurrection_end_session();
            header('Location: index.php', true, 303);
            exit();
		}
		db_free_result($result);
		if (isset($session['allowednavs'][$REQUEST_URI]) && $session['allowednavs'][$REQUEST_URI] && $overrideforced!==true){
			$session['allowednavs']=array();
		}else{
			if ($overrideforced!==true){
				redirect("badnav.php","Navigation not allowed to $REQUEST_URI");
			}
		}
	}else{
		if (!$anonymous){
            translator_setup();
			$session['message']=translate_inline("You are not logged in, this may be because your session timed out.","login");
			redirect("index.php?op=timeout","Not logged in: $REQUEST_URI");
		}
	}
}
?>
