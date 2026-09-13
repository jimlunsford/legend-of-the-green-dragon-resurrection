<?php
function dag_sortbounties($x, $y) {
	if ($x['Amount'] == $y['Amount']) {
		return 0;
	} elseif ($x['Amount'] > $y['Amount']) {
		return -1;
	} elseif ($x['Amount'] < $y['Amount']) {
		return 1;
	}
}

function dag_sortbountieslevel($x, $y) {
	if ($x['Level'] == $y['Level']) {
		return dag_sortbounties($x, $y);
	} elseif ($x['Level'] > $y['Level']) {
		return -1;
	} elseif ($x['Level'] < $y['Level']) {
		return 1;
	}
}

function dag_manage(){
    global $session;
    require_once 'modules/dag/security.php';
    check_su_access(SU_EDIT_USERS);
    try {
        \Resurrection\Http\Input::choice($_GET,'type',['','1','2','3','search'],'');
        \Resurrection\Http\Input::choice($_GET,'sort',['','1','2','3','4','5','6','7','8'],'');
        \Resurrection\Http\Input::choice($_GET,'dir',['','1','2'],'');
        \Resurrection\Http\Input::choice($_POST,'s',['','1','2','3','4','5','6','7','8'],'');
        \Resurrection\Http\Input::choice($_POST,'d',['','1','2'],'');
        foreach (['setter','getter','target'] as $field) {
            if (strlen(\Resurrection\Http\Input::string($_POST,$field)) > 100) throw new InvalidArgumentException();
        }
    } catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bounty filter.'); }
    if (httpget('op') === 'addbounty') resurrection_consume_action('dag-admin-place',(string)$session['user']['acctid']);
    if (in_array(httpget('op'), ['cleanup','closebounty'],true)) resurrection_require_post();
	page_header("Dag's Bounty Lists");
	require_once("lib/superusernav.php");
	superusernav();

	// Add some bounty expiration for closed bounties
    if (httpget('op') === 'cleanup') {
        db_query('DELETE FROM ' . db_prefix('bounty') . ' WHERE status=1 AND windate<?',true,
            [date('Y-m-d H:i:s',strtotime('-'.((int)getsetting('expirecontent',180)/10).' days'))]);
    }
    $cleanup = 'runmodule.php?module=dag&manage=true&op=cleanup';
    addnav('',$cleanup);
    rawoutput('<form method="POST" action="'.$cleanup.'">'.resurrection_csrf_field().'<button class="button">Clean up closed bounties</button></form>');

	addnav("Actions");
	addnav("A?View All Bounties","runmodule.php?module=dag&manage=true&op=viewbounties&type=1&sort=1&dir=1&admin=true");
	addnav("O?View Open Bounties","runmodule.php?module=dag&manage=true&op=viewbounties&type=2&sort=1&dir=1&admin=true");
	addnav("C?View Closed Bounties","runmodule.php?module=dag&manage=true&op=viewbounties&type=3&sort=1&dir=1&admin=true");
	addnav("R?Refresh List","runmodule.php?module=dag&manage=true&admin=true");

	rawoutput("<form action='runmodule.php?module=dag&manage=true&op=viewbounties&type=search&admin=true' method='POST'>");
	addnav("","runmodule.php?module=dag&manage=true&op=viewbounties&type=search&admin=true");
	output("Setter: ");
	rawoutput("<input name='setter' value=\"".htmlentities(stripslashes(httppost('setter')))."\">");
	output(" Winner: ");
	rawoutput("<input name='getter' value=\"".htmlentities(stripslashes(httppost('getter')))."\">");
	output(" Target: ");
	rawoutput("<input name='target' value=\"".htmlentities(stripslashes(httppost('target')))."\">");
	output_notl("`n");
	output("Order by: ");
	$id = translate_inline("ID");
	$amt = translate_inline("Amount");
	$targ = translate_inline("Target");
	$set = translate_inline("Setter");
	$sdate = translate_inline("Set Date");
	$stat = translate_inline("Status");
	$win = translate_inline("Winner");
	$wdate = translate_inline("Win Date");
	$desc = translate_inline("Descending");
	$asc = translate_inline("Ascending");
	$search = translate_inline("Search");
	rawoutput("<select name='s'>
		<option value='1'".(httppost('s')=='1'?" selected":"").">$id</option>
		<option value='2'".(httppost('s')=='2'?" selected":"").">$amt</option>
		<option value='3'".(httppost('s')=='3'?" selected":"").">$targ</option>
		<option value='4'".(httppost('s')=='4'?" selected":"").">$set</option>
		<option value='5'".(httppost('s')=='5'?" selected":"").">$sdate</option>
		<option value='6'".(httppost('s')=='6'?" selected":"").">$stat</option>
		<option value='7'".(httppost('s')=='7'?" selected":"").">$win</option>
		<option value='8'".(httppost('s')=='8'?" selected":"").">$wdate</option>
		</select>");
	rawoutput("<input type='radio' name='d' value='1'".(httppost('d')==1?" checked":"")."> $desc");
	rawoutput("<input type='radio' name='d' value='2'".(httppost('d')==1?"":" checked")."> $asc");
	output_notl("`n");
	rawoutput("<input type='submit' class='button' value='$search'>");
	rawoutput("</form>");

	$op = httpget('op');
	if ($op == "") {
		// ***ADDED***
		// By Andrew Senger
		// Adding for new Bounty Code
		output_notl("`n`n");
		output("`c`bThe Bounty List`b`c`n");
		$sql = "SELECT bountyid,amount,target,setter,setdate FROM " . db_prefix("bounty") . " WHERE status=0 ORDER BY bountyid ASC";
		$result = db_query($sql);
		rawoutput("<table border=0 cellpadding=2 cellspacing=1 bgcolor='#999999'>");
		$amt = translate_inline("Amount");
		$lev = translate_inline("Level");
		$name = translate_inline("Name");
		$loc = translate_inline("Location");
		$sex = translate_inline("Sex");
		$alive = translate_inline("Alive");
		$last = translate_inline("Last On");
		rawoutput("<tr class='trhead'><td><b>$amt</b></td><td><b>$lev</b></td><td><b>$name</b></td><td><b>$loc</b></td><td><b>$sex</b></td><td><b>$alive</b></td><td><b>$last</b></td>");
		$listing = array();
		$totlist = 0;
		for($i=0;$i<db_num_rows($result);$i++){
			$row = db_fetch_assoc($result);
				$amount = (int)$row['amount'];
				$result2 = db_query('SELECT name,alive,sex,level,laston,loggedin,lastip,uniqueid FROM ' . db_prefix('accounts') . ' WHERE acctid=?',true,[(int)$row['target']]);
				if (db_num_rows($result2) == 0) {
					/* this person has been deleted, clear bounties */

					continue;
				}
				$row2 = db_fetch_assoc($result2);
				$yesno = 0;
				for($j=0;$j<=$i;$j++){
					if($listing[$j]['Name'] == $row2['name']) {
						$listing[$j]['Amount'] = $listing[$j]['Amount'] + $amount;
						$yesno = 1;
					}
				}
				if ($yesno==0) {
					$listing[] = array('Amount'=>$amount,'Level'=>$row2['level'],'Name'=>$row2['name'],'Location'=>$row2['location'],'Sex'=>$row2['sex'],'Alive'=>$row2['alive'],'LastOn'=>$row2['laston']);
					$totlist = $totlist + 1;
				}
		}
		usort($listing, 'dag_sortbounties');
		for($i=0;$i<$totlist;$i++) {
			rawoutput("<tr class='".($i%2?"trdark":"trlight")."'><td>");
			output_notl("`^%s`0", $listing[$i]['Amount']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $listing[$i]['Level']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $listing[$i]['Name']);
			rawoutput("</td><td>");
			output($loggedin ? "`#Online`0" : $listing[$i]['Location']);
			rawoutput("</td><td>");
			output($listing[$i]['Sex']?"`!Female`0":"`!Male`0");
			rawoutput("</td><td>");
			output($listing[$i]['Alive']?"`1Yes`0":"`4No`0");
			rawoutput("</td><td>");
			$laston= relativedate($listing[$i]['LastOn']);
			if ($loggedin) $laston=translate_inline("Now");
			output_notl("%s", $laston);
			rawoutput("</td></tr>");
		}
		rawoutput("</table>");
		output("`n`n`c`bAdd Bounty`b`c`n");
		rawoutput("<form action='runmodule.php?module=dag&manage=true&op=addbounty&admin=true' method='POST'>");
        rawoutput(resurrection_action_fields('dag-admin-place',(string)$session['user']['acctid']));
		output("`2Target: ");
		rawoutput("<input name='contractname'>");
		output_notl("`n");
		output("`2Amount to Place: ");
		rawoutput("<input name='amount' id='amount' width='5'>");
		output_notl("`n`n");
		$final = translate_inline("Finalize Contract");
		rawoutput("<input type='submit' class='button' value='$final'>");
		rawoutput("</form>");
		addnav("","runmodule.php?module=dag&manage=true&op=addbounty&admin=true");
	}else if ($op == "addbounty") {
        try {
            $contractname = \Resurrection\Http\Input::string($_POST,'contractname');
            $amount = \Resurrection\Http\Input::integer($_POST,'amount',0,1);
            if ($amount < 1 || $amount > 2147483647 || strlen($contractname) > 100) throw new InvalidArgumentException();
        } catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bounty input.'); }
        $exact = httpget('subfinal') === '1';
        $name = $exact ? $contractname : '%' . implode('%', preg_split('//u',$contractname,-1,PREG_SPLIT_NO_EMPTY) ?: []) . '%';
        $result = db_query('SELECT acctid,name,locked FROM ' . db_prefix('accounts') . ($exact ? ' WHERE name=?' : ' WHERE name LIKE ?') . ' AND locked=0 LIMIT 101',true,[$name]);
		if (db_num_rows($result) == 0) {
			output("No one by that name!");
		} elseif(db_num_rows($result) > 100) {
			output("Too many names!");
		} elseif(db_num_rows($result) > 1) {
			output("Select the correct name:`n");
			rawoutput("<form action='runmodule.php?module=dag&manage=true&op=addbounty&subfinal=1&admin=true' method='POST'>");
        rawoutput(resurrection_action_fields('dag-admin-place',(string)$session['user']['acctid']));
			output("`2Target: ");
			rawoutput("<select name='contractname'>");
			for ($i=0;$i<db_num_rows($result);$i++){
				$row = db_fetch_assoc($result);
				rawoutput("<option value=\"".htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8')."\">".full_sanitize($row['name'])."</option>");
			}
			rawoutput("</select>");
			output_notl("`n`n");
			// Amount is already typed.
			output("`2Amount to Place: ");
			rawoutput("<input name='amount' id='amount' width='5' value='$amount'>");
			output_notl("`n`n");
			$final = translate_inline("Finalize Contract");
			rawoutput("<input type='submit' class='button' value='$final'>");
			rawoutput("</form>");
			addnav("","runmodule.php?module=dag&manage=true&op=addbounty&subfinal=1");
		} else {
			// Now, we have just the one, so check it.
			$row  = db_fetch_assoc($result);
			if ($row['locked']) {
				output("Target is a locked user.");
			}
			$amt = $amount;
			if ($amt <= 0) {
				output("That bounty value make no sense.");
			} else {
				// All good!
				try { dag_place_bounty((int)$row['acctid'],$amt,true); }
                catch (DomainException $error) { http_response_code(400); exit('Bounty cannot be placed.'); }
				output("Bounty added!");
			}
		}
	} else if ($op == "viewbounties") {
		$type = httpget('type');
        $sort = httpget('sort');
        $dir = httpget('dir');
        $t=''; $s=' ORDER BY bountyid'; $d=' DESC';
		output("`c`bThe Bounty List`b`c`n");
		if ($type == 1) {
			output("`c`bViewing: `3All Bounties`b`c");
		}elseif ($type == 2) {
			output("`c`bViewing: `3Open Bounties`b`c");
		}elseif ($type == 3) {
			output("`c`bViewing: `3Closed Bounties`b`c");
		}
		addnav("Sorting");

		if (($sort == 1) && ($dir == 1)) {
			addnav("1?By BountyID - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=1&dir=2&admin=true");
			output("`c`bSorting By: `3BountyID - Desc`b`c`n`n");
		}elseif (($sort == 1) && ($dir == 2)) {
			addnav("1?By BountyID - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=1&dir=1&admin=true");
			output("`c`bSorting By: `3BountyID - Asc`b`c`n`n");
		}else {
			addnav("1?By BountyID - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=1&dir=1&admin=true");
		}
		if (($sort == 2) && ($dir == 1)) {
			addnav("2?By Amount - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=2&dir=2&admin=true");
			output("`c`bSorting By: `3Amount - Desc`b`c`n`n");
		}elseif (($sort == 2) && ($dir == 2)) {
			addnav("2?By Amount - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=2&dir=1&admin=true");
			output("`c`bSorting By: `3Amount - Asc`b`c`n`n");
		}else {
			addnav("2?By Amount - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=2&dir=1&admin=true");
		}
		if (($sort == 3) && ($dir == 1)) {
			addnav("3?By Target - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=3&dir=2&admin=true");
			output("`c`bSorting By: `3Target - Desc`b`c`n`n");
		}elseif (($sort == 3) && ($dir == 2)) {
			addnav("3?By Target - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=3&dir=1&admin=true");
			output("`c`bSorting By: `3Target - Asc`b`c`n`n");
		}else {
			addnav("3?By Target - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=3&dir=1&admin=true");
		}
		if (($sort == 4) && ($dir == 1)) {
			addnav("4?By Setter - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=4&dir=2&admin=true");
			output("`c`bSorting By: `3Setter - Desc`b`c`n`n");
		}elseif (($sort == 4) && ($dir == 2)) {
			addnav("4?By Setter - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=4&dir=1&admin=true");
			output("`c`bSorting By: `3Setter - Asc`b`c`n`n");
		}else {
			addnav("4?By Setter - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=4&dir=1&admin=true");
		}
		if (($sort == 5) && ($dir == 1)) {
			addnav("5?By Set Date - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=5&dir=2&admin=true");
			output("`c`bSorting By: `3Set Date - Desc`b`c`n`n");
		}elseif (($sort == 5) && ($dir == 2)) {
			addnav("5?By Set Date - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=5&dir=1&admin=true");
			output("`c`bSorting By: `3Set Date - Asc`b`c`n`n");
		}else {
			addnav("5?By Set Date - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=5&dir=1&admin=true");
		}
		if ($type == 1) {
			if (($sort == 6) && ($dir == 1)) {
				addnav("6?By Status - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=6&dir=2&admin=true");
				output("`c`bSorting By: `3Status - Desc`b`c`n`n");
			}elseif (($sort == 6) && ($dir == 2)) {
				addnav("6?By Status - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=6&dir=1&admin=true");
				output("`c`bSorting By: `3Status - Asc`b`c`n`n");
			}else {
				addnav("6?By Status - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=6&dir=1&admin=true");
			}
		}
		if (($type == 1) || ($type == 3)) {
			if (($sort == 7) && ($dir == 1)) {
				addnav("7?By Winner - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=7&dir=2&admin=true");
				output("`c`bSorting By: `3Winner - Desc`b`c`n`n");
			}elseif (($sort == 7) && ($dir == 2)) {
				addnav("7?By Winner - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=7&dir=1&admin=true");
				output("`c`bSorting By: `3Winner - Asc`b`c`n`n");
			}else {
				addnav("7?By Winner - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=7&dir=1&admin=true");
			}

			if (($sort == 8) && ($dir == 1)) {
				addnav("8?By Win Date - Asc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=8&dir=2&admin=true");
				output("`c`bSorting By: `3Win Date - Desc`b`c`n`n");
			}elseif (($sort == 8) && ($dir == 2)) {
				addnav("8?By Win Date - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=8&dir=1&admin=true");
				output("`c`bSorting By: `3Win Date - Asc`b`c`n`n");
			}else {
				addnav("8?By Win Date - Desc","runmodule.php?module=dag&manage=true&op=viewbounties&type=".$type."&sort=8&dir=1&admin=true");
			}
		}
		addnav("Return to Bounty Home","runmodule.php?module=dag&manage=true&op=bounties&admin=true");
		switch ($type) {
			case 1:
				$t = "";
				break;
			case 2:
				$t = " WHERE status=0";
				break;
			case 3:
				$t = " WHERE status=1";
				break;
		}
		switch  ($sort) {
			case 1:
				$s = " ORDER BY bountyid";
				break;
			case 2:
				$s = " ORDER BY amount";
				break;
			case 3:
				$s = " ORDER BY target";
				break;
			case 4:
				$s = " ORDER BY setter";
				break;
			case 5:
				$s = " ORDER BY setdate";
				break;
			case 6:
				$s = " ORDER BY status";
				break;
			case 7:
				$s = " ORDER BY winner";
				break;
			case 8:
				$s = " ORDER BY windate";
				break;
		}
		switch ($dir) {
			case 1:
				$d = " DESC";
				break;
			case 2:
				$d = " ASC";
				break;
		}
		//override those options in favor of the search form if it exists
		if ($type=='search'){
			switch(httppost('s')){
				case 1: $s = " ORDER BY bountyid"; break;
				case 2: $s = " ORDER BY amount"; break;
				case 3: $s = " ORDER BY target"; break;
				case 4: $s = " ORDER BY setter"; break;
				case 5: $s = " ORDER BY setdate"; break;
				case 6: $s = " ORDER BY status"; break;
				case 7: $s = " ORDER BY winner"; break;
				case 8: $s = " ORDER BY windate"; break;
			}
			switch(httppost('d')){
				case 1: $d = " DESC"; break;
				case 2: $d = " ASC"; break;
			}
			$t = "";
			if (httppost('setter')>'') {
				if ($t>"") $t.=" AND";
				$a = httppost('setter');
				$setter = "%";
				for ($i=0;$i<strlen($a);$i++){
					$setter.=$a[$i]."%";
				}
				$result = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE name LIKE ?',true,[$setter]);
				$ids = array();
				while ($row = db_fetch_assoc($result)){
					array_push($ids,(int)$row['acctid']);
				}
				if (count($ids)==0) $ids[0]=0;
				$t .= " setter IN (".join(",",$ids).")";
			}
			if (httppost('getter')>'') {
				if ($t>"") $t.=" AND";
				$a = httppost('getter');
				$getter = "%";
				for ($i=0;$i<strlen($a);$i++){
					$getter.=$a[$i]."%";
				}
				$result = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE name LIKE ?',true,[$getter]);
				$ids = array();
				while ($row = db_fetch_assoc($result)){
					array_push($ids,(int)$row['acctid']);
				}
				if (count($ids)==0) $ids[0]=0;
				$t .= " winner IN (".join(",",$ids).")";
			}
			if (httppost('target')>'') {
				if ($t>"") $t.=" AND";
				$a = httppost('target');
				$target = "%";
				for ($i=0;$i<strlen($a);$i++){
					$target.=$a[$i]."%";
				}
				$result = db_query('SELECT acctid FROM ' . db_prefix('accounts') . ' WHERE name LIKE ?',true,[$target]);
				$ids = array();
				while ($row = db_fetch_assoc($result)){
					array_push($ids,(int)$row['acctid']);
				}
				if (count($ids)==0) $ids[0]=0;
				$t .= " target IN (".join(",",$ids).")";
			}
			if ($t>"") $t = " WHERE".$t;
		}
		$sql = "SELECT bountyid,amount,target,setter,setdate,status,winner,windate FROM " . db_prefix("bounty").$t.$s.$d;
		$result = db_query($sql);
		rawoutput("<table border=0 cellpadding=2 cellspacing=1 bgcolor='#999999'>");
		$id = translate_inline("ID");
		$amt = translate_inline("Amt");
		$targ = translate_inline("Target");
		$set = translate_inline("Setter");
		$sdate = translate_inline("Set Date/Time");
		$stat = translate_inline("Status");
		$win = translate_inline("Winner");
		$wdate = translate_inline("Win Date/Time");
		$ops = translate_inline("Ops");

		rawoutput("<tr class='trhead'><td><b>$id</b></td><td><b>$amt</b></td><td><b>$targ</b></td><td><b>$set</b></td><td><b>$sdate</b></td><td><b>$stat</b></td><td><b>$win</b></td><td><b>$wdate</b></td><td>$ops</td></tr>");
		for($i=0;$i<db_num_rows($result);$i++){
			$row = db_fetch_assoc($result);
			if ($row['target']==0) {
				$target['name'] = translate_inline("`2Green Dragon");
			} else {
				$result2 = db_query('SELECT name FROM ' . db_prefix('accounts') . ' WHERE acctid=?',true,[(int)$row['target']]);
				if (db_num_rows($result2) == 0) {
					$target['name'] = translate_inline("`4Deleted Character");
				} else {
					$target = db_fetch_assoc($result2);
				}
			}
			if ($row['setter']==0) {
				$setter['name'] = translate_inline("`2Green Dragon");
			}else {
				$result3 = db_query('SELECT name FROM ' . db_prefix('accounts') . ' WHERE acctid=?',true,[(int)$row['setter']]);
				if (db_num_rows($result3) == 0) {
					$setter['name'] = translate_inline("`4Deleted Character");
				} else {
					$setter = db_fetch_assoc($result3);
				}
			}
			$winner['name'] = "";
			if (($row['winner']==0) && $row['status'] == 1) {
				$winner['name'] = translate_inline("`2Green Dragon");
			} elseif ($row['status'] == 1) {
				$result4 = db_query('SELECT name FROM ' . db_prefix('accounts') . ' WHERE acctid=?',true,[(int)$row['winner']]);
				if (db_num_rows($result4) == 0) {
					$winner['name'] = translate_inline("`2Deleted Character");
				} else {
					$winner = db_fetch_assoc($result4);
				}
			}
			rawoutput("<tr class='".($i%2?"trdark":"trlight")."'><td>");
			output_notl("`^%s`0", $row['bountyid']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $row['amount']);
			rawoutput("</td><td>");
			output_notl("`&%s`0", $target['name']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $setter['name']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $row['setdate']);
			rawoutput("</td><td>");
			output($row['status']==0?"`^Open`0":"`^Closed`0");
			rawoutput("</td><td>");
			output_notl("`^%s`0", $winner['name']);
			rawoutput("</td><td>");
			output_notl("`^%s`0", $row['status']?$row['windate']:"");
			rawoutput("</td><td>");
			if ($row['status'] == 0) {
				$link = "runmodule.php?module=dag&manage=true&op=closebounty&id={$row['bountyid']}&admin=true";
				$close = translate_inline("Close");
				rawoutput('<form method="POST" action="'.htmlspecialchars($link,ENT_QUOTES,'UTF-8').'">'.resurrection_csrf_field().'<button class="button">'.htmlspecialchars($close,ENT_QUOTES,'UTF-8').'</button></form>');
				addnav("",$link);
			} else {
				rawoutput("&nbsp;");
			}
			rawoutput("</td></tr>");
		}
		rawoutput("</table>");
	} else if ($op == "closebounty") {
		$windate = date("Y-m-d H:i:s");
		try { $bountyid = \Resurrection\Http\Input::integer($_GET,'id',0,1); }
        catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid bounty ID.'); }
        if ($bountyid < 1) { http_response_code(400); exit('Invalid bounty ID.'); }
        db_query('UPDATE ' . db_prefix('bounty') . ' SET status=1,winner=0,windate=? WHERE bountyid=? AND status=0',true,[$windate,$bountyid]);
		output("Bounty closed.");
	// ***END ADD***
	}
	page_footer();
}

function dag_pvpwin($args){
	global $badguy,$session;
	// ***ADDED***
	// By Andrew Senger
	// Added for Bounty Code
	// Bounty Check - Andrew Senger
	// Check for Bounty
    $totgoodamt = 0;
    $totbadamt = 0;
    $result = db_query('SELECT bountyid,amount,setter FROM ' . db_prefix('bounty') . ' WHERE status=0 AND setdate<=? AND target=?', true, [date('Y-m-d H:i:s'), (int)$badguy['acctid']]);
	if (db_num_rows($result) > 0) {
		$totgoodamt = 0;
		$totbadamt = 0;
        require_once 'modules/dag/security.php';
        [$totgoodamt,$totbadamt] = dag_claim_bounties((int)$badguy['acctid']);
		if ($totgoodamt > 0) {
			output("`@When you turn around, Dag Durnick is standing there.");
			output("\"%s`# had a bounty of `^%s`# on th' head`@\", he says as he tosses you a leather purse which clinks with the sounds of your new fortune.`n`n", $badguy['creaturename'], $totgoodamt);
		}
		if ($totbadamt > 0) {
			output("\"`#I'm keeping `^%s`# of the total bounty on this soul's head, as ye' be the one that set it.`@\"", $totbadamt);
		}
	}
	// End Check for Bounty
	// Add Bounty Gold
	if ($totgoodamt > 0) {
        // Credit already committed atomically with bounty closure.
		debuglog("gained ".$totgoodamt." gold bounty for killing ", $badguy['acctid']);
	}
	// End Add Bounty Gold
	// Add Bounty Kill to News
	if ($totgoodamt > 0) {
		addnews("`4%s`3 collected `4%s`3 gold bounty by turning in `4%s`3's head!",$session['user']['name'],$totgoodamt,$badguy['creaturename']);
	}
	// End Add Bounty Kill to News
	// End Bounty Check - Andrew
	// ***END ADD***
	if ($totgoodamt > 0) {
		$args['pvpmessageadd'] .= sprintf_translate("`nThey also received `^%s`2 in bounty gold.`n", $totgoodamt);
		rawoutput(tlbutton_clear());
	}
	return $args;
}
?>
