<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready

function saveuser(){
	global $session,$dbqueriesthishit,$baseaccount,$companions;
	if (defined("NO_SAVE_USER")) return false;

	if ($session['loggedin'] && $session['user']['acctid']!=""){
		// Any time we go to save a user, make SURE that any tempstat changes
		// are undone.
		restore_buff_fields();

		$session['user']['allowednavs']=serialize($session['allowednavs']);
		$session['user']['bufflist']=serialize($session['bufflist']);
		if (isset($companions) && is_array($companions)) $session['user']['companions']=serialize($companions);
        $assignments = [];
        $parameters = [];
        foreach ($session['user'] as $key => $value) {
            if ($key === 'password' || !array_key_exists($key, $baseaccount)) { continue; }
            if (is_array($value)) { $value = serialize($value); }
            if ($baseaccount[$key] != $value) {
                $assignments[] = db_identifier($key) . '=?';
                $parameters[] = $value;
            }
        }
        $assignments[] = 'laston=?';
        $parameters[] = date('Y-m-d H:i:s');
        $parameters[] = (int)$session['user']['acctid'];
        db_query('UPDATE ' . db_prefix('accounts') . ' SET ' . implode(',', $assignments) . ' WHERE acctid=?', true, $parameters);
        if (!empty($session['output'])) {
            db_query('INSERT INTO ' . db_prefix('accounts_output') . ' (acctid,output) VALUES (?,?) ON DUPLICATE KEY UPDATE output=?', true,
                [(int)$session['user']['acctid'], $session['output'], $session['output']]);
        }
		unset($session['bufflist']);
		$session['user'] = array(
			"acctid"=>$session['user']['acctid'],
			"login"=>$session['user']['login'],
		);
	}
}

?>
