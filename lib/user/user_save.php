<?php
require_once __DIR__ . '/../../src/Compatibility/array_cursor.php';
$sql = "";
$updates=0;
resurrection_require_post();
require_once 'lib/accounts.php';
$rows = db_query('SELECT * FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [(int)$userid]);
$oldvalues = db_fetch_assoc($rows);
if (!$oldvalues) { http_response_code(404); exit('Account not found.'); }
$parameters = [];
	$post = httpallpost();
reset($post);
while (list($key,$val)=resurrection_array_next($post)){
	if (isset($userinfo[$key])){
        if ($key !== 'superuser' && !is_string($val)) { http_response_code(400); exit('Invalid account field.'); }
		if ($key=="newpassword" ){
			if ($val>"") {
				$sql.='password=?,authversion=authversion+1,';
                $parameters[] = \Resurrection\Security\Passwords::hash($val);
				$updates++;
				output("Password value has been updated.`n");
				debuglog("Administrator changed account password", $userid);
				if ($session['user']['acctid']==$userid) {
					resurrection_rotate_session();
				}
			}
		}elseif ($key=="superuser"){
			if (!is_array($val)) { http_response_code(400); exit('Invalid privileges.'); }
            $value = 0;
			while (list($k,$v)=resurrection_array_next($val)){
				if ($v) $value += (int)$k;
			}
				//strip off an attempt to set privs that the user doesn't
			//have authority to set.
			$stripfield = ((int)$oldvalues['superuser'] | $session['user']['superuser'] | SU_ANYONE_CAN_SET | ($session['user']['superuser'] & SU_MEGAUSER ? 0xFFFFFFFF : 0));
			$value = $value & $stripfield;
				//put back on privs that the user used to have but the
			//current user can't set.
			$unremovable = ~ ((int)$session['user']['superuser'] | SU_ANYONE_CAN_SET | ($session['user']['superuser'] & SU_MEGAUSER ? 0xFFFFFFFF : 0));
			$filteredunremovable = (int)$oldvalues['superuser'] & $unremovable;
			$value = $value | $filteredunremovable;
			if ((int)$value != (int)$oldvalues['superuser']){
				$sql.=db_identifier($key) . '=?,'; $parameters[]=$value;
				$updates++;
				output("Superuser values have changed.`n");
				if ($session['user']['acctid']==$userid) {
					$session['user']['superuser']=$value;
				}
				debuglog($session['user']['name']."`0 changed superuser to ".show_bitfield($value),$userid);
				debug("superuser has changed to $value");
			}
		} elseif ($key=="name" && $val!=$oldvalues[$key]) {
			$updates++;
			$tmp = sanitize_colorname(getsetting("spaceinname", 0),
					$val, true);
			$tmp = preg_replace("/[`][cHw]/", "", $tmp);
			$tmp = sanitize_html($tmp);
			if ($tmp != $val) {
				output("`\$Illegal characters removed from player name!`0`n");
			}
			if (soap($tmp) != ($tmp)) {
				output("`^The new name doesn't pass the bad word filter!`0");
			}
				$newname = change_player_name($tmp, $oldvalues);
			$sql.=db_identifier($key) . '=?,'; $parameters[]=$newname;
			output("Changed player name to %s`0`n", $newname);
			debuglog($session['user']['name'] . "`0 changed player name to $newname`0", $userid);
			$oldvalues['name']=$newname;
			if ($session['user']['acctid']==$userid) {
				$session['user']['name'] = $newname;
			}
		} elseif ($key=="title" && $val!=$oldvalues[$key]) {
			$updates++;
			$tmp = sanitize_colorname(true, $val, true);
			$tmp = preg_replace("/[`][cHw]/", "", $tmp);
			$tmp = sanitize_html($tmp);
			if ($tmp != $val) {
				output("`\$Illegal characters removed from player title!`0`n");
			}
			if (soap($tmp) != ($tmp)) {
				output("`^The new title doesn't pass the bad word filter!`0");
			}
				$newname = change_player_title($tmp, $oldvalues);
			$sql.=db_identifier($key) . '=?,'; $parameters[]=$val;
			output("Changed player title from %s`0 to %s`0`n", $oldvalues['title'], $tmp);
			$oldvalues[$key]=$tmp;
			if ($newname != $oldvalues['name']) {
				$sql.='name=?,'; $parameters[]=$newname;
				output("Changed player name to %s`0 due to changed dragonkill title`n", $newname);
				debuglog($session['user']['name'] . "`0 changed player name to $newname`0 due to changed dragonkill title", $userid);
				$oldvalues['name']=$newname;
				if ($session['user']['acctid']==$userid) {
					$session['user']['name'] = $newname;
				}
			}
			if ($session['user']['acctid']==$userid) {
				$session['user']['title'] = $tmp;
			}
		} elseif ($key=="ctitle" && $val!=$oldvalues[$key]) {
			$updates++;
			$tmp = sanitize_colorname(true, $val, true);
			$tmp = preg_replace("/[`][cHw]/", "", $tmp);
			$tmp = sanitize_html($tmp);
			if ($tmp != $val) {
				output("`\$Illegal characters removed from custom title!`0`n");
			}
			if (soap($tmp) != ($tmp)) {
				output("`^The new custom title doesn't pass the bad word filter!`0");
			}
			$newname = change_player_ctitle($tmp, $oldvalues);
			$sql.=db_identifier($key) . '=?,'; $parameters[]=$val;
			output("Changed player ctitle from %s`0 to %s`0`n", $oldvalues['ctitle'], $tmp);
			$oldvalues[$key]=$tmp;
			if ($newname != $oldvalues['name']) {
				$sql.='name=?,'; $parameters[]=$newname;
				output("Changed player name to %s`0 due to changed custom title`n", $newname);
				debuglog($session['user']['name'] . "`0 changed player name to $newname`0 due to changed custom title", $userid);
				$oldvalues['name']=$newname;
				if ($session['user']['acctid']==$userid) {
					$session['user']['name'] = $newname;
				}
			}
			if ($session['user']['acctid']==$userid) {
				$session['user']['ctitle'] = $tmp;
			}
		}elseif ($key=="oldvalues"){
			//donothing.
		}elseif (isset($oldvalues[$key]) && $oldvalues[$key]!=$val){
			$sql.=db_identifier($key) . '=?,'; $parameters[]=$val;
			$updates++;
			output("%s has changed to %s.`n", $key, $val);
			debuglog($session['user']['name']."`0 changed $key to $val",$userid);
			if ($session['user']['acctid']==$userid) {
				$session['user'][$key]=$val;
			}
		}
	}
}
	$sql=substr($sql,0,strlen($sql)-1);
$sql = "UPDATE " . db_prefix("accounts") . " SET " . $sql . " WHERE acctid=?";
$parameters[]=(int)$userid;
	$petition = httpget("returnpetition");
if ($petition!="")
	addnav("","viewpetition.php?op=view&id=$petition");
addnav("","user.php");
	if ($updates>0){
	db_query($sql, true, $parameters);
    if ((int)$session['user']['acctid'] === (int)$userid) {
        $rows = db_query('SELECT authversion FROM ' . db_prefix('accounts') . ' WHERE acctid=?', true, [(int)$userid]);
        $_SESSION['auth_version'] = (int)db_fetch_assoc($rows)['authversion'];
        resurrection_rotate_session();
    }
	
	output("%s fields in the user's record were updated.", $updates);
}else{
	output("No fields were changed in the user's record.");
}
$op = "edit";
httpset($op, "edit");
?>