<?php
// addnews ready
// translator ready
// mail ready
require_once("lib/constants.php");
require_once("lib/http.php");
require_once "lib/event_security.php";

// This file encapsulates all the special event handling for most locations

// Returns whether or not the description should be skipped
function handle_event($location, $baseLink=false, $needHeader=false)
{
	if ($baseLink === false){
		global $PHP_SELF;
		$script = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseLink = basename($script) . '?';
	}else{
		//debug("Base link was specified as $baseLink");
		//debug(debug_backtrace());
	}
	global $session, $playermount, $badguy;
	$skipdesc = false;

	tlschema("events");
	$allowinactive = false;
	$eventhandler = httpget('eventhandler');
	if (($session['user']['superuser'] & SU_DEVELOPER) && $eventhandler!=""){
        resurrection_require_post();
        $allowinactive = true;
		$array = preg_split("/[:-]/", $eventhandler);
		if ($array[0] == "module") {
			$session['user']['specialinc'] = "module:" . $array[1];
		} else {
			$session['user']['specialinc'] = "";
		}
	}

	$_POST['i_am_a_hack'] = 'true';

	if ($session['user']['specialinc']!=""){
		$specialinc = $session['user']['specialinc'];
		$session['user']['specialinc'] = "";
		if ($needHeader !== false) {
			page_header($needHeader);
		}

		output("`^`c`bSomething Special!`c`b`0");
		if (strchr($specialinc, ":")) {
			//$array = split(":", $specialinc);
			$array = explode(":", $specialinc);
            if (resurrection_secured_event($array[1])) {
                $context=$_SESSION['event_action'] ?? [];
                if (($context['module'] ?? '')!==$array[1] || ($context['type'] ?? '')!==$location) {
                    // Persisted event recovery issues a fresh intent; POST must first obtain its form.
                    resurrection_event_context($array[1],$location);
                }
            }
            $starttime = getmicrotime();
			module_do_event($location, $array[1], $allowinactive,$baseLink);
			$endtime = getmicrotime();
			if (($endtime - $starttime >= 1.00 && ($session['user']['superuser'] & SU_DEBUG_OUTPUT))){
				debug("Slow Event (".round($endtime-$starttime,2)."s): $hookname - {$row['modulename']}`n");
			}
		}
		if (checknavs()) {
			// The page rendered some linkage, so we just want to exit.
			page_footer();
		} else {
			$skipdesc=true;
			$session['user']['specialinc'] = "";
			$session['user']['specialmisc'] = "";
			httpset("op", "");
		}
	}
	tlschema();
	return $skipdesc;
}
?>
