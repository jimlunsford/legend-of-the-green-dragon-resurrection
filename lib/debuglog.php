<?php
// translator ready
// addnews ready
// mail ready
function debuglog($message,$target=false,$user=false,$field=false,$value=false,$consolidate=true){
	if ($target===false) $target=0;
	static $needsdebuglogdelete = true;
	global $session;
	$args = func_get_args();
	if ($user === false) $user = $session['user']['acctid'];
	$corevalue = $value;
	$id=0;
	if ($field !== false && $value !==false && $consolidate){
        $result=db_query('SELECT * FROM ' . db_prefix('debuglog') . ' WHERE actor=? AND field=? AND date>?',true,[(int)$user,$field,date('Y-m-d 00:00:00')]);
		if (db_num_rows($result)>0){
			$row = db_fetch_assoc($result);
			$value = $row['value']+$value;
			$message = $row['message'];
			$id = $row['id'];
		}
	}
	if ($corevalue!==false) $message.=" ($corevalue)";
	if ($field===false) $field="";
	if ($value===false) $value=0;
    if ($id>0) {
        db_query('UPDATE ' . db_prefix('debuglog') . ' SET date=?,actor=?,target=?,message=?,field=?,value=? WHERE id=?',true,
            [date('Y-m-d H:i:s'),(int)$user,(int)$target,$message,$field,$value,(int)$id]);
    } else {
        db_query('INSERT INTO ' . db_prefix('debuglog') . ' (date,actor,target,message,field,value) VALUES (?,?,?,?,?,?)',true,
            [date('Y-m-d H:i:s'),(int)$user,(int)$target,$message,$field,$value]);
    }
}

?>
