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
// The host guide, online at this version's tag: the documents are not on the server (Phase 6 task 16, R2).
require_once __DIR__ . '/lib/source_link.php';
exit("Web installation is unavailable through installer.php. Install with php scripts/install.php from a shell, or with setup.php on a host without one (the host guide: " . resurrection_doc_url('docs/HOST-GUIDE.md', '5-install') . ").\n");
