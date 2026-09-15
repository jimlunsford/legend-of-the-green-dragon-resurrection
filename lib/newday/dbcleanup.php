<?php
require_once __DIR__ . '/../../src/Compatibility/array_cursor.php';
//db cleanup
savesetting("lastdboptimize",date("Y-m-d H:i:s"));
$result = db_query("SHOW TABLES");
$tables = array();
$start = getmicrotime();
for ($i=0;$i<db_num_rows($result);$i++){
	$row = db_fetch_assoc($result);
	list($key,$val)=resurrection_array_next($row);
	db_query("OPTIMIZE TABLE " . db_identifier($val));
	array_push($tables,$val);
}
$time = round(getmicrotime() - $start,2);
require_once("lib/gamelog.php");
gamelog("Optimized tables: ".join(", ",$tables)." in $time seconds.","maintenance");
?>
