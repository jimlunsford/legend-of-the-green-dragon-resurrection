<?php
resurrection_require_post();
try {
    $ip = \Resurrection\Http\Input::string($_POST, 'ipfilter');
    $id = \Resurrection\Http\Input::string($_POST, 'uniqueid');
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid request.'); }
db_query('DELETE FROM ' . db_prefix('bans') . ' WHERE ipfilter=? AND uniqueid=?', true, [$ip, $id]);
redirect('user.php?op=removeban');
