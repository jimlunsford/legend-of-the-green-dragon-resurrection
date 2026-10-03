<?php
resurrection_require_post();
$ip = \Resurrection\Http\Input::string($_POST, 'ipfilter');
$id = \Resurrection\Http\Input::string($_POST, 'uniqueid');
db_query('DELETE FROM ' . db_prefix('bans') . ' WHERE ipfilter=? AND uniqueid=?', true, [$ip, $id]);
redirect('user.php?op=removeban');
