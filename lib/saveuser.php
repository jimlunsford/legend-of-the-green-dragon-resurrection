<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
require_once __DIR__ . '/../src/Security/NavigationState.php';
require_once __DIR__ . '/../src/Security/PreferenceState.php';
require_once __DIR__ . '/../src/Game/BuffState.php';
require_once __DIR__ . '/../src/Game/CompanionState.php';
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

		// Store only navigation and preference state the readers accept; a failure writes nothing.
		$session['user']['allowednavs']=serialize(\Resurrection\Security\NavigationState::map($session['allowednavs']));
		if (array_key_exists('restorepage', $session['user'])) \Resurrection\Security\NavigationState::restorePage($session['user']['restorepage']);
		if (array_key_exists('prefs', $session['user'])) $session['user']['prefs']=\Resurrection\Security\PreferenceState::encode($session['user']['prefs']);
		// Torment presentation may calculate display fields but cannot persist buff changes. Only a valid list is stored.
        $session['user']['bufflist']=defined('RESURRECTION_GRAVEYARD_READ_ONLY')
            ? $baseaccount['bufflist'] : \Resurrection\Game\BuffState::encode($session['bufflist'], $session['user']);
		// Only a valid companion list is stored; a refused one throws and nothing is written.
		if (isset($companions) && is_array($companions)) $session['user']['companions']=\Resurrection\Game\CompanionState::encode($companions);
        $assignments = [];
        $parameters = [];
        foreach ($session['user'] as $key => $value) {
            if (defined('RESURRECTION_NEWDAY_PRESENTATION') && !in_array($key, ['allowednavs','restorepage','gentime','gentimecount','gensize'], true)) { continue; }
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
