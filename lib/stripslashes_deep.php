<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
function stripslashes_deep($input){
	if (!is_array($input)) return stripslashes($input);
	reset($input);
	while (list($key,$val)=resurrection_array_next($input)){
		$input[$key] = stripslashes_deep($val);
	}
	return $input;
}
?>
