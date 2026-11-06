<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready

// A slightly higher level SQL error reporting function.
function sql_error($sql){
	global $session;
	return output_array($session)."SQL = <pre>$sql</pre>".db_error(LINK);
}

?>
