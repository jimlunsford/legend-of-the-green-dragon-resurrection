<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
//save module settings.
resurrection_user_module_save((string)httpget('module'), (int)$userid);
$op = "edit";
httpset("op", "edit");
httpset("subop", "module", true);
?>
