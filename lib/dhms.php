<?php
// addnews ready
// translator ready
// mail ready
function dhms($secs,$dec=false){
	if ($dec===false) $secs=round($secs,0);
	// PHP 5 truncated both sides of `%` to integers; PHP 8 refuses a float with a fraction there, so the truncation is
	// written out. The output is 1.1.2's.
	$whole=(int)$secs;
	return (int)($secs/86400).translate_inline("d","datetime").((int)($secs/3600)%24).translate_inline("h","datetime").((int)($secs/60)%60).translate_inline("m","datetime").($whole%60).($dec?substr($secs-(int)$secs,1):"").translate_inline("s","datetime");
	//use multiple translate_inlines as this function is not called too often...if you deactive stats...
}
?>
