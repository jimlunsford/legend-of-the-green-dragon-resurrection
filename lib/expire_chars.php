<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready

require_once("lib/char_expiration.php");
resurrection_expire_characters(time());
?>
