<?php
require_once __DIR__ . '/../src/Security/ScalarState.php';
require_once __DIR__ . '/../src/Game/Expression.php';
require_once __DIR__ . '/../src/Game/SpecialtyBuffState.php';
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// addnews ready
// translator ready
// mail ready

$buffreplacements = array();
$debuggedbuffs = array();
function calculate_buff_fields(){
	global $session, $badguy, $buffreplacements, $debuggedbuffs;
	\Resurrection\Game\SpecialtyBuffState::collection($session['bufflist']);
	if (!$session['bufflist']) return;

	//run temp stats
	reset($session['bufflist']);
	while (list($buffname,$buff)=resurrection_array_next($session['bufflist'])){
		if (!isset($buff['tempstats_calculated'])){
			while (list($property,$value)=resurrection_array_next($buff)){
				if (substr($property,0,9)=='tempstat-'){
					apply_temp_stat(substr($property,9),$value);
				}
			}//end while
			$session['bufflist'][$buffname]['tempstats_calculated']=true;
		}//end if
	}//end while

	// Only the fixed bundled numeric vocabulary is interpreted. Text and
    // scalar metadata retain their type and never pass through PHP execution.
    foreach ($session['bufflist'] as $buffname => $buff) {
        if (isset($buff['fields_calculated'])) continue;
        foreach ($buff as $property => $value) {
            if (!is_string($value) || !preg_match('/<[A-Za-z][A-Za-z0-9_|]*>/', $value)) continue;
            $calculated = \Resurrection\Game\Expression::evaluate($value, $session['user']);
            $buffreplacements[$buffname][$property] = $value;
            $session['bufflist'][$buffname][$property] = $calculated;
        }
        $session['bufflist'][$buffname]['fields_calculated'] = true;
    }

}//end function

function restore_buff_fields(){
	global $session, $buffreplacements;
	if (is_array($buffreplacements)){
		reset($buffreplacements);
		while (list($buffname,$val)=resurrection_array_next($buffreplacements)){
			reset($val);
			while (list($property,$value)=resurrection_array_next($val)){
				if (isset($session['bufflist'][$buffname])){
					$session['bufflist'][$buffname][$property] = $value;
					unset($session['bufflist'][$buffname]['fields_calculated']);
				}//end if
			}//end while
			unset($buffreplacements[$buffname]);
		}//end while
	}//end if

	//restore temp stats
	if (!is_array($session['bufflist'])) $session['bufflist'] = array();
	reset($session['bufflist']);
	while (list($buffname,$buff)=resurrection_array_next($session['bufflist'])){
		if (array_key_exists("tempstats_calculated",$buff) && $buff['tempstats_calculated']){
			reset($buff);
			while (list($property,$value)=resurrection_array_next($buff)){
				if (substr($property,0,9)=='tempstat-'){
					apply_temp_stat(substr($property,9),-$value);
				}
			}//end while
			unset($session['bufflist'][$buffname]['tempstats_calculated']);
		}//end if
	}//end while
}//end function

function apply_buff($name,$buff){
	global $session,$buffreplacements, $translation_namespace;

	if (!isset($buff['schema']) || $buff['schema'] == "") {
		$buff['schema'] = $translation_namespace;
	}

	if (isset($buffreplacements[$name])) unset($buffreplacements[$name]);
	if (isset($session['bufflist'][$name])){
		//we'll need to unapply buff fields before applying this buff since
		//it's already set.
		restore_buff_fields();
	}
	$buff = modulehook("modify-buff", array("name"=>$name, "buff"=>$buff));
	\Resurrection\Game\SpecialtyBuffState::collection([$name=>$buff['buff']]);
	$session['bufflist'][$name] = $buff['buff'];
	calculate_buff_fields();
}

function apply_companion($name,$companion,$ignorelimit=false){
    require_once __DIR__ . '/../src/Game/SkeletonCompanionState.php';
    if ($name === 'skeleton_warrior') { \Resurrection\Game\SkeletonCompanionState::validate($companion); }
	global $session, $companions;
	if (!is_array($companions)) {
		$companions = \Resurrection\Game\SkeletonCompanionState::companions(\Resurrection\Security\ScalarState::read($session['user']['companions']));
	}
	$companionsallowed = getsetting("companionsallowed", 1);
	$args = modulehook("companionsallowed", array("maxallowed"=>$companionsallowed));
	$companionsallowed = $args['maxallowed'];
	$current = 0;
	foreach ($companions as $thisname=>$thiscompanion) {
		if (isset($companion['ignorelimit']) && $companion['ignorelimit'] == true) {
		} else {
			if ($thisname != $name)
			++$current;
		}
	}
	if ($current < $companionsallowed || $ignorelimit == true) {
		if (isset($companions[$name])) {
			unset($companions[$name]);
		}
		if (!isset($companion['ignorelimit']) && $ignorelimit == true) {
			$companion['ignorelimit'] = true;
		}
		$companions[$name] = $companion;
		$session['user']['companions'] = createstring($companions);
		return true; // success!
	} else {
		debug("Failed to add companion due to restrictions regarding the maximum amount of companions allowed.");
		return false;
	}
}


function strip_buff($name){
	global $session, $buffreplacements;
	restore_buff_fields();
	if (isset($session['bufflist'][$name]))
		unset($session['bufflist'][$name]);
	if (isset($buffreplacements[$name]))
		unset($buffreplacements[$name]);
	calculate_buff_fields();
}

function strip_all_buffs(){
	global $session;
	$thebuffs = $session['bufflist'];
	reset($thebuffs);
	while (list($buffname,$buff)=resurrection_array_next($thebuffs)){
		strip_buff($buffname);
	}
}

function has_buff($name){
	global $session;
	if (isset($session['bufflist'][$name])) return true;
	return false;
}

?>
