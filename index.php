<?php
define('RESURRECTION_ENTRY', true);
// translator ready
// addnews ready
// mail ready
require_once __DIR__ . '/src/Http/SecurityHeaders.php';
\Resurrection\Http\SecurityHeaders::send();
header("Location: home.php?".$_SERVER['QUERY_STRING']);
?>