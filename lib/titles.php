<?php
// translator ready
// addnews ready
// mail ready

require_once("lib/dbwrapper.php");
require_once("lib/e_rand.php");

function valid_dk_title($title, $dks, $gender)
{
	// Bound here and below: 1.1.2 pasted the kill count and the caller's ref in.
	$sql = "SELECT dk,male,female FROM " . db_prefix("titles") .
		" WHERE dk <= ? ORDER by dk DESC";
	$res = db_query($sql, true, [(int)$dks]);
	$d = -1;
	while ($row = db_fetch_assoc($res)) {
		if ($d == -1) $d = $row['dk'];
		// Only care about best dk rank for this person
		if ($row['dk'] != $d) break;
		if ($gender && ($row['female'] == $title)) return true;
		if (!$gender && ($row['male'] == $title)) return true;
	}
	return false;
}

function get_dk_title($dks, $gender, $ref=false)
{
	// $ref is an arbitrary string value.  The title picker will try to
	// give the next highest title in the same 'ref', but if it cannot it'll
	// default to a random one of the ones available for the required DK.

	// Figure out which dk value is the right one to use.. The one to use
	// is the closest one below or equal to the players dk number.
	// We will prefer the dk level from the same $ref if we can, but if there
	// is a closer 'any' match, we will use that!
	$refdk = -1;
	if ($ref !== false) {
		$sql = "SELECT max(dk) as dk FROM " . db_prefix("titles") .
			" WHERE dk<=? and ref=?";
		$res = db_query($sql, true, [(int)$dks, (string)$ref]);
		$row = db_fetch_assoc($res);
		$refdk = $row['dk'];
	}

	$sql = "SELECT max(dk) as dk FROM " . db_prefix("titles") .
		" WHERE dk<=?";
	$res = db_query($sql, true, [(int)$dks]);
	$row = db_fetch_assoc($res);
	$anydk = $row['dk'];

	$useref = "";
	$parameters = [];
	$targetdk = $anydk;
	if ($refdk >= $anydk) {
		$useref = "AND ref=?";
		$targetdk = $refdk;
	}
	array_unshift($parameters, (string)$targetdk);
	if ($useref !== "") $parameters[] = (string)$ref;

	// Okay, we now have the right dk target to use, so select a title from
	// any titles available at that level.  We will prefer titles that
	// match the ref if possible.
	$sql = "SELECT male,female FROM " . db_prefix("titles") .
		" WHERE dk=? $useref ORDER BY RAND(" .
		e_rand() . ") LIMIT 1";
	$res = db_query($sql, true, $parameters);
	$row = array('male'=>'God', 'female'=>'Goddess');
	if (db_num_rows($res) != 0) {
		$row = db_fetch_assoc($res);
	}
	if ($gender == SEX_MALE)
		return $row['male'];
	else
		return $row['female'];
}

?>
