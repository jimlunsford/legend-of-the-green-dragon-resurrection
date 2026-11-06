<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
$id = resurrection_mail_send();
$mailSent = true;
$op = $id > 0 ? 'read' : '';
