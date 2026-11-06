<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready
function is_email($email){
	return preg_match("/[[:alnum:]_.-]+[@][[:alnum:]_.-]{2,}\\.[[:alnum:]_.-]{2,}/",$email);
}
?>
