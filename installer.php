<?php
define('RESURRECTION_ENTRY', true);
// 1.1.2's web installer is disabled: it cannot safely distinguish upgrade
// authorization from anonymous first use. Installs are fresh only, through
// scripts/install.php from a shell or setup.php on a host without one.
require_once __DIR__ . '/src/Http/SecurityHeaders.php';
\Resurrection\Http\SecurityHeaders::send();
http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
exit("Web installation is unavailable through installer.php. Install with php scripts/install.php from a shell, or with setup.php on a host without one (docs/HOST-GUIDE.md).\n");
