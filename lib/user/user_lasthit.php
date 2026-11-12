<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
$output="";
$sql = "SELECT output FROM " . db_prefix("accounts_output") . " WHERE acctid=?";
$result = db_query($sql, true, [$userid]);
$row = db_fetch_assoc($result);
// The player's stored page is shown as it was, in a sandbox: no script runs in the editor's origin. The sandbox is
// added to the security headers (src/Http/SecurityHeaders.php) and enforced whatever the csp_mode setting says.
\Resurrection\Http\SecurityHeaders::send(\Resurrection\Http\SecurityHeaders::mode(getsetting('csp_mode', \Resurrection\Http\SecurityHeaders::DEFAULT_MODE)), true);
// Its form tokens are kept as markers (Phase 6 task 9); they are shown empty here.
echo str_replace(".focus();",".blur();",str_replace("<iframe src=","<iframe Xsrc=",\Resurrection\Security\KeptPage::view($row['output'] ?? '')));
exit();
?>
