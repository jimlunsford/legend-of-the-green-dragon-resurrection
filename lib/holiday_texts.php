<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// addnews ready
// translator ready
// mail ready
require_once("lib/modules.php");

function holidayize($text,$type='unknown'){
	global $session;
	if (!isset($session['user']['prefs']['ihavenocheer']))
		$session['user']['prefs']['ihavenocheer'] = 0;
	if ($session['user']['prefs']['ihavenocheer']) {
		return $text;
	}

	$args = array('text'=>$text,'type'=>$type);
	$args = modulehook("holiday", $args);
	$text = $args['text'];

	return $text;
}

?>
