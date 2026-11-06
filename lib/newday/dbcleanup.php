<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
//db cleanup
require_once __DIR__ . '/../db_optimize.php';
resurrection_optimize_tables();
?>
