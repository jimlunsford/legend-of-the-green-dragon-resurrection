<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
resurrection_user_special((int)$userid);
$op = "edit";
httpset("op", "edit");
?>
