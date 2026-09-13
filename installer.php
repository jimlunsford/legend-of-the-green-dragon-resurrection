<?php
// Installation is an operator-only CLI action. The historical web installer
// cannot safely distinguish upgrade authorization from anonymous first use.
http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
exit("Web installation is unavailable. Use the documented local installation command. Existing databases require an explicitly verified migration.\n");
