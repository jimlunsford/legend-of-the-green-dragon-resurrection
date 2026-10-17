<?php
require_once("lib/restore_authority.php");
output("`)`b`cThe Mausoleum`c`b");
// Only the protected POST restores. GET (the Mausoleum link, a restored page) asks.
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$price = resurrection_restore_price((int)$session['user']['level'], (int)$session['user']['soulpoints']);
$max = $price['max'];
$favortoheal = $price['cost'];
if ($posted){
	try { resurrection_haunt_fields(); } catch (InvalidArgumentException $error) { resurrection_restore_failure($error); }
	resurrection_consume_action('graveyard-restore', resurrection_restore_context());
	try { $outcome = resurrection_restore(); } catch (Throwable $error) { resurrection_restore_failure($error); }
}elseif ($session['user']['soulpoints']>=$max){
	$outcome = 'full';
}elseif ($session['user']['deathpower']>=$favortoheal){
	$outcome = 'ask';
}else{
	$outcome = 'cursed';
}
if ($outcome=='granted'){
	output("`\$%s`) calls you weak for needing restoration, but as you have enough favor with him, he grants your request at the cost of `4%s`) favor.",$deathoverlord, $favortoheal);
}elseif ($outcome=='ask'){
	// 1.1.2 restored on this GET; the restoration is now this form's protected POST.
	resurrection_restore_form((int)$favortoheal);
}elseif ($outcome=='cursed'){
	output("`\$%s`) curses you and throws you from the Mausoleum, you must gain more favor with him before he will grant restoration.",$deathoverlord);
}else{
	output("`\$%s`) sighs and mumbles something about, \"`7just 'cause they're dead, does that mean they don't have to think?`)\"`n`n",$deathoverlord);
	output("Perhaps you'd like to actually `ineed`i restoration before you ask for it.");
}
addnav(array("Question `\$%s`0 about the worth of your soul",$deathoverlord),"graveyard.php?op=question");
addnav("Places");
addnav("S?Land of the Shades","shades.php");
addnav("G?Return to the Graveyard","graveyard.php");
?>
