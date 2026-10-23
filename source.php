<?php
// Disabled Resurrection endpoint. Exact historical implementation remains at
// historical-source-1.1.2. This endpoint does not bootstrap the application.
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
exit("This legacy feature is disabled in Resurrection.\n");
