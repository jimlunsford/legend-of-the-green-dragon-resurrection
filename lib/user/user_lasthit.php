<?php
$output="";
$sql = "SELECT output FROM " . db_prefix("accounts_output") . " WHERE acctid=?";
$result = db_query($sql, true, [$userid]);
$row = db_fetch_assoc($result);
// The player's stored page is shown as it was, in a sandbox: no script runs in the editor's origin.
header("Content-Security-Policy: sandbox");
echo str_replace(".focus();",".blur();",str_replace("<iframe src=","<iframe Xsrc=",$row['output'] ?? ''));
exit();
?>
