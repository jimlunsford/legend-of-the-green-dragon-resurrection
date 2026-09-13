<?php
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
			$session['bufflist']=unserialize($session['user']['bufflist'], ['allowed_classes' => false]);
			if (!is_array($session['bufflist'])) $session['bufflist']=array();
			$session['user']['dragonpoints']=unserialize($session['user']['dragonpoints'], ['allowed_classes' => false]);
			$session['user']['prefs']=unserialize($session['user']['prefs'], ['allowed_classes' => false]);
			if (!is_array($session['user']['dragonpoints'])) $session['user']['dragonpoints']=array();
			if (is_array(unserialize($session['user']['allowednavs'], ['allowed_classes' => false]))){
				$session['allowednavs']=unserialize($session['user']['allowednavs'], ['allowed_classes' => false]);
			}else{
				$session['allowednavs']=array($session['user']['allowednavs']);
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
