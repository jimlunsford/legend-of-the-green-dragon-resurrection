<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// addnews ready
// translator ready
// mail ready
function sanitize_uri(){
	global $PATH_INFO,$SCRIPT_NAME,$REQUEST_URI;
	if (isset($PATH_INFO) && $PATH_INFO!="") {
		$SCRIPT_NAME=$PATH_INFO;
		$REQUEST_URI="";
	}
	if ($REQUEST_URI==""){
		//necessary for some IIS installations (CGI in particular)
        $query = http_build_query(httpallget(), '', '&', PHP_QUERY_RFC3986);
        $REQUEST_URI = $SCRIPT_NAME . ($query !== '' ? '?' . $query : '');
		$_SERVER['REQUEST_URI'] = $REQUEST_URI;
	}
	$SCRIPT_NAME=substr($SCRIPT_NAME,strrpos($SCRIPT_NAME,"/")+1);
	if (strpos($REQUEST_URI,"?")){
		$REQUEST_URI=$SCRIPT_NAME.substr($REQUEST_URI,strpos($REQUEST_URI,"?"));
	}else{
		$REQUEST_URI=$SCRIPT_NAME;
	}
}
function php_generic_environment(){
    // Explicit server metadata bridge only. Never export GET/POST/cookies into globals.
    foreach (['SCRIPT_NAME', 'REQUEST_URI', 'PATH_INFO', 'REMOTE_ADDR', 'SERVER_NAME', 'SERVER_PORT', 'QUERY_STRING', 'REQUEST_METHOD', 'HTTP_HOST', 'HTTP_REFERER', 'HTTP_USER_AGENT'] as $name) {
        $GLOBALS[$name] = $_SERVER[$name] ?? '';
    }
    sanitize_uri();
}
