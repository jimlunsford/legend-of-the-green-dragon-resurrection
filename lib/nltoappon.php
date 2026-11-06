<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready
function nltoappon($in){
	$out = str_replace("\r\n","\n",$in);
	$out = str_replace("\r","\n",$out);
	$out = str_replace("\n","`n",$out);
	return $out;
}
?>
