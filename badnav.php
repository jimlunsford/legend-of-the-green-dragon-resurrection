<?php
define('RESURRECTION_ENTRY', true);
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
define("OVERRIDE_FORCED_NAV",true);
require_once("common.php");
require_once("lib/villagenav.php");

tlschema("badnav");

if ($session['user']['loggedin'] && $session['loggedin']){
	// page_footer() clears the session copy of the last page, so it is usually absent here (1.1.2 read it as "").
	if (strpos($session['output'] ?? '',"<!--CheckNewDay()-->")){
		checkday();
	}
	while (list($key,$val)=resurrection_array_next($session['allowednavs'])){
		//hack-tastic.
		if (
			trim($key)=="" ||
			$key===0 ||
			substr($key,0,8)=="motd.php" ||
			substr($key,0,8)=="mail.php"
		) unset($session['allowednavs'][$key]);
	}
	$sql="SELECT output FROM ".db_prefix("accounts_output")." WHERE acctid={$session['user']['acctid']};";
	$result=db_query($sql);
	$row=db_fetch_assoc($result);
	if (!is_array($session['allowednavs']) ||
			count($session['allowednavs'])==0 || $row['output']=="") {
		$session['allowednavs']=array();
		page_header("Your Navs Are Corrupted");
		if ($session['user']['alive']) {
			villagenav();
			output("Your navs are corrupted, please return to %s.",
					$session['user']['location']);
		} else {
			addnav("Return to Shades", "shades.php");
			output("Your navs are corrupted, please return to the Shades.");
		}
		page_footer();
	}
	// The kept page with this session's form tokens (Phase 6 task 9). Its actions are issued afresh only on the first
	// replay after signing in (login.php), when the session is new; the earlier session's tokens are never accepted.
	echo \Resurrection\Security\KeptPage::replay($row['output'], $_SESSION, !empty($_SESSION['kept_page_reissue']));
	unset($_SESSION['kept_page_reissue']);
	$session['debug']="";
	$session['user']['allowednavs']=$session['allowednavs'];
	saveuser();
}else{
	$session=array();
	translator_setup();
	redirect("index.php");
}

?>