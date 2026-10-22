<?php
// translator ready
// addnews ready
// mail ready
require_once("lib/dbwrapper.php");

function get_player_title($old=false) {
	global $session;
	$title = "";
	if ($old === false) {
		$title = $session['user']['title'];
		if ($session['user']['ctitle']) $title = $session['user']['ctitle'];
	} else {
		$title = $old['title'];
		if ($old['ctitle']) $title = $old['ctitle'];
	}
	return $title;
}

function get_player_basename($old=false) {
	global $session;
	$name = "";
	$title = get_player_title($old);
	if ($old===false) {
		$name = $session['user']['name'];
	} else {
		$name = $old['name'];
	}
	if ($title) {
		$x = strpos($name, $title);
		if ($x !== false)
			$name = trim(substr($name,$x+strlen($title)));
	}

	return str_replace("`0", "", $name);
}

function change_player_name($newname, $old=false) {
	if ($newname == "")
		$newname = get_player_basename($old);

	$newname = str_replace("`0", "", $newname);

	$title = get_player_title($old);
	if ($title) {
		$x = strpos($newname, $title);
		if ($x === 0)
			$newname = trim(substr($newname, $x+strlen($title)));
		$newname =  $title . " " . $newname . "`0";
	}
	return $newname;
}

/**
 * Whether a name and title fit accounts.name and accounts.title, measured in characters as the user editor and the
 * title editor's rebuild measure them (the column sizes are read from the schema: 60 and 25 in a fresh install).
 */
function resurrection_name_fits(string $name, string $title): bool {
	static $limits = null;
	if ($limits === null) {
		$limits = ['name'=>60, 'title'=>25];
		$rows = db_query('SELECT COLUMN_NAME AS col, CHARACTER_MAXIMUM_LENGTH AS length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME IN (?,?)',
			true, [db_prefix('accounts'), 'name', 'title']);
		foreach ($rows as $row) $limits[strtolower((string)$row['col'])] = (int)$row['length'];
	}
	return mb_strlen($name, 'UTF-8') <= $limits['name'] && mb_strlen($title, 'UTF-8') <= $limits['title'];
}

function change_player_ctitle($nctitle,$old=false) {
	global $session;
	if ($nctitle == "") {
		if ($old == false) {
			$nctitle = $session['user']['title'];
		} else {
			$nctitle = $old['title'];
		}
	}
	$newname = get_player_basename($old) . "`0";
	if ($nctitle) {
		$newname = $nctitle." ".$newname;
	}
	return $newname;
}

function change_player_title($ntitle, $old=false) {
	global $session;
	if ($old===false) {
		$ctitle = $session['user']['ctitle'];
	} else {
		$ctitle = $old['ctitle'];
	}

	$newname = get_player_basename($old) . "`0";
	if ($ctitle == "") {
		if ($ntitle != "") {
			$newname = $ntitle." ".$newname;
		}
	} else {
		$newname = $ctitle." ".$newname;
	}
	return $newname;
}

?>
