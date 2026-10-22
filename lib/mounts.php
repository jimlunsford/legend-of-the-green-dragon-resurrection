<?php
// translator ready
// addnews ready
// mail ready
/**
 * The mount row for a stored mount ID (accounts.hashorse), cached as 1.1.2 cached it under "mountdata-<id>".
 * The ID is bound: 1.1.2 pasted it into the cached query. Only a whole number in the mountid range is looked up;
 * anything else is no mount, as an unknown ID always was.
 */
function getmount($horse=0) {
	$id = filter_var($horse, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0, 'max_range'=>4294967295]]);
	if ($id === false) return array();
	$sql = "SELECT * FROM " . db_prefix("mounts") . " WHERE mountid=?";
	$result = db_query_cached($sql, "mountdata-$id", 3600, [$id]);
	if (db_num_rows($result)>0){
		return db_fetch_assoc($result);
	}else{
		return array();
	}
}
?>
