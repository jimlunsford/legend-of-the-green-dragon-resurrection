<?php
// translator ready
// addnews ready
// mail ready
function checkban($login=false){
	global $session;
	if (isset($session['banoverride']) && $session['banoverride'])
		return false;
	if ($login===false){
		$ip=$_SERVER['REMOTE_ADDR'] ?? ''; 
		$id=is_string($_COOKIE['lgi'] ?? null) ? $_COOKIE['lgi'] : ''; 
	}else{
		$sql = "SELECT lastip,uniqueid,banoverride,superuser FROM " . db_prefix("accounts") . " WHERE login=?";
		$result = db_query($sql, true, [$login]);
        if (db_num_rows($result) === 0) { return false; }
		$row = db_fetch_assoc($result);
		if ($row['banoverride'] || ($row['superuser'] &~ SU_DOESNT_GIVE_GROTTO)){
			$session['banoverride']=true;
			return false;
		}
		db_free_result($result);
		$ip=$row['lastip'];
		$id=$row['uniqueid'];
	}
    $sql = 'SELECT * FROM ' . db_prefix('bans') . " WHERE ((substring(?,1,length(ipfilter))=ipfilter AND ipfilter<>'') OR (uniqueid=? AND uniqueid<>'')) AND (banexpire IS NULL OR banexpire>=?)";
    $result = db_query($sql, true, [$ip, $id, date('Y-m-d')]);
	if (db_num_rows($result)>0){
		$session=array();
		tlschema("ban");
		$session['message']=translate_inline("`n`4You fall under a ban currently in place on this website:`n");
		while ($row = db_fetch_assoc($result)) {
			$session['message'].=$row['banreason']."`n";
			if ($row['banexpire'] === null)
				$session['message'].=translate_inline("  `\$This ban is permanent!`0");
				else
				$session['message'].=sprintf_translate("  `^This ban will be removed `\$after`^ %s.`0",date("M d, Y",strtotime($row['banexpire'])));
            db_query('UPDATE ' . db_prefix('bans') . ' SET lasthit=? WHERE ipfilter=? AND uniqueid=?', true, [date('Y-m-d H:i:s'), $row['ipfilter'], $row['uniqueid']]);
			$session['message'].="`n";
		}
		$session['message'].=translate_inline("`4If you wish, you may appeal your ban with the petition link.");
		tlschema();
		header("Location: index.php");
		exit();
	}
	db_free_result($result);
}

?>
