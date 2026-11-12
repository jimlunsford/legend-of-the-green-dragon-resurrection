<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// translator ready
// addnews ready
// mail ready

function savesetting($settingname,$value){
	global $settings;
		loadsettings();
	// if (!isset($settings[$settingname]) && $value){
	if ($settingname == "showFormTabIndex") return true;
	db_query('INSERT INTO ' . db_prefix('settings') . ' (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?', true,
        [$settingname, (string)$value, (string)$value]);
	$settings[$settingname]=$value;
	invalidatedatacache("game-settings");
	if (db_affected_rows()>0) {
		return true;
	}else{
		return false;
	}
}

function loadsettings(){
	global $settings;
	// as this seems to be a common complaint, examine the execution path
	// of this function, it will only load the settings once per page hit,
	// in subsequent calls to this function, $settings will be an array,
	// thus this function will do nothing.
	// slight change in 1.1.1 ... let's store a serialized array instead of a cached query
	// we need it too often and the for/while construct necessary is just too much for it.
	if (!is_array($settings)){
		$settings=datacache("game-settings");
		if (!is_array($settings)){
			$settings=array();
			$sql = "SELECT * FROM " . db_prefix("settings");
			$result = db_query($sql);//db_query_cached($sql,"game-settings");
			while ($row = db_fetch_assoc($result)) {
				$settings[$row['setting']] = $row['value'];
			}
			db_free_result($result);
			updatedatacache("game-settings",$settings);
		}
	}
}

function clearsettings(){
	//scraps the loadsettings() data to force it to reload.
	global $settings;
	unset($settings);
}

/**
 * Settings the game holds at one value whatever the settings table stores (Phase 6 task 7): getsetting() returns this
 * value, and Game Settings shows the setting locked and refuses a change (lib/configuration_descriptors.php declares it
 * "locked"). requirevalidemail: with it on, create.php makes no account, and the email verification it stands for does
 * not exist (S11, a known limitation of rc.1). The lock goes when S11 ships.
 *
 * @return array<string,string> setting => the value it is held at
 */
function resurrection_locked_settings(): array {
	return ['requirevalidemail' => '0'];
}

/**
 * Settings the game reads only inside a range (Phase 6 task 8): getsetting() returns a stored value outside it as the
 * nearest bound, and Game Settings refuses a value outside it (lib/configuration_descriptors.php declares the bounds
 * from here). LOGINTIMEOUT: with 0, or a few seconds, every player is signed out on their next page load; above a day,
 * a closed browser stays signed in for days.
 *
 * @return array<string,array{0:int,1:int}> setting => [lowest, highest]
 */
function resurrection_bounded_settings(): array {
	return ['LOGINTIMEOUT' => [300, 86400]];
}

/** A bounded setting's value held inside its range (an integer); any other setting's value unchanged. */
function resurrection_bounded_setting(string $settingname, mixed $value): mixed {
	$bounds = resurrection_bounded_settings()[$settingname] ?? null;
	if ($bounds === null) return $value;
	$number = is_numeric($value) ? (int)$value : $bounds[0];
	return max($bounds[0], min($bounds[1], $number));
}

function getsetting($settingname,$default){
	global $settings;
	global $DB_USEDATACACHE,$DB_DATACACHEPATH;
	$locked = resurrection_locked_settings();
	if (array_key_exists((string)$settingname, $locked)) return $locked[(string)$settingname];
	if ($settingname=="usedatacache") return $DB_USEDATACACHE;
		elseif ($settingname=="datacachepath") return $DB_DATACACHEPATH;
	if (!isset($settings[$settingname])) {
		loadsettings();
	}else {
		return resurrection_bounded_setting((string)$settingname, $settings[$settingname]);
	}
	if (!isset($settings[$settingname])){
		savesetting($settingname,$default);
		return resurrection_bounded_setting((string)$settingname, $default);
	}else{
		return resurrection_bounded_setting((string)$settingname, $settings[$settingname]);
	}
}
?>
