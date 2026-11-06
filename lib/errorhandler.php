<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// Central development/production error boundary is installed before bootstrap.
require_once __DIR__ . '/errorhandling.php';
