<?php
function gamelog($message,$category="general",$filed=false){
	global $session;
	// Bound: 1.1.2 pasted the message and category in with addslashes(); a message can carry player text (logins).
	$sql = "INSERT INTO ".db_prefix("gamelog")." (message,category,filed,date,who) VALUES (?,?,?,?,?)";
	db_query($sql, true, [(string)$message, (string)$category, $filed ? "1" : "0", date("Y-m-d H:i:s"), (int)($session['user']['acctid'] ?? 0)]);
}
?>
