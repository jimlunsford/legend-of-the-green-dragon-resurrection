<?php
resurrection_require_post();
use Resurrection\Http\Input;
$type = Input::choice($_POST, 'type', ['ip', 'id'], 'id');
$column = $type === 'ip' ? 'ipfilter' : 'uniqueid';
$value = Input::string($_POST, $type === 'ip' ? 'ip' : 'id');
$days = Input::integer($_POST, 'duration');
$reason = Input::string($_POST, 'reason');
if ($days > 36500 || $value === '' || strlen($value) > 40 || strlen($reason) > 255) { http_response_code(400); exit('Invalid ban.'); }
if (($type === 'ip' && str_starts_with($_SERVER['REMOTE_ADDR'], $value)) ||
    ($type === 'id' && $value === ($_COOKIE['lgi'] ?? ''))) { output('You cannot ban yourself.'); }
else {
    db_query('INSERT INTO ' . db_prefix('bans') . ' (banner,' . db_identifier($column) . ',banexpire,banreason) VALUES (?,?,?,?)', true,
        [$session['user']['name'], $value, $days === 0 ? null : date('Y-m-d', strtotime('+' . $days . ' days')), $reason]);
    output('Ban entered.');
}
