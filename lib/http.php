<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
function httpget($var){ return $_GET[$var] ?? false; }

function httpallget() {
	return $_GET;
}

function httpset($var, $val,$force=false){
	if (isset($_GET[$var]) || $force) $_GET[$var] = $val;
}

function httppost($var){ return $_POST[$var] ?? false; }

function httppostisset($var) { return isset($_POST[$var]) ? 1 : 0; }

function httppostset($var, $val, $sub=false){
	if ($sub === false) {
		if (isset($_POST[$var])) $_POST[$var] = $val;
	} else {
		if (isset($_POST[$var]) && isset($_POST[$var][$sub]))
			$_POST[$var][$sub]=$val;
	}
}

function httpallpost(){
	return $_POST;
}

function postparse($verify=false, $subval=false){
	if ($subval) $var = $_POST[$subval];
	else $var = $_POST;

	reset($var);
	$sql = "";
	$keys = "";
	$vals = "";
	$i = 0;
	while(list($key, $val) = resurrection_array_next($var)) {
		if ($verify === false || isset($verify[$key])) {
			if (is_array($val)) $val = serialize($val);
            $key = db_identifier($key);
            $val = db_escape((string)$val);
			$sql .= (($i > 0) ? "," : "") . "$key='$val'";
			$keys .= (($i > 0) ? "," : "") . "$key";
			$vals .= (($i > 0) ? "," : "") . "'$val'";
			$i++;
		}
	}
	return array($sql, $keys, $vals);
}
?>
