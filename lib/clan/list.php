<?php
	page_header("Clan Listing");
	$registrar=getsetting('clanregistrar','`%Karissa');
	addnav("Clan Options");
	$sql = "SELECT MAX(" . db_prefix("clans") . ".clanid) AS clanid, MAX(clanshort) AS clanshort, MAX(clanname) AS clanname,count(" . db_prefix("accounts") . ".acctid) AS c FROM " . db_prefix("clans") . " LEFT JOIN " . db_prefix("accounts") . " ON " . db_prefix("clans") . ".clanid=" . db_prefix("accounts") . ".clanid AND clanrank>".CLAN_APPLICANT." GROUP BY " . db_prefix("clans") . ".clanid ORDER BY c DESC";
	$result = db_query($sql);
	if (db_num_rows($result)>0){
		output("`7You ask %s`7 for the clan listings.  She points you toward a marquee board near the entrance of the lobby that lists the clans.`0`n`n",$registrar);
		$v = 0;
		$memb_n = translate_inline("(%s members)");
		$memb_1 = translate_inline("(%s member)");
		rawoutput('<table cellspacing="0" cellpadding="2" align="left">');
		while ($row = db_fetch_assoc($result)){
			if ($row['c']==0){
				// Historical housekeeping on view: a clan with no member above applicant is removed. It takes
				// no request input; the recheck under lock keeps it from racing a new member.
				$clanid = (int)$row['clanid'];
				$db = $GLOBALS['dbinfo']['connection'];
				$db->beginTransaction();
				try {
					db_query("SELECT clanid FROM " . db_prefix("clans") . " WHERE clanid=? FOR UPDATE", true, [$clanid]);
					$members = db_query("SELECT count(*) AS c FROM " . db_prefix("accounts") . " WHERE clanid=? AND clanrank>? FOR UPDATE", true, [$clanid, CLAN_APPLICANT]);
					if ((int)$members[0]['c'] === 0) db_query("DELETE FROM " . db_prefix("clans") . " WHERE clanid=?", true, [$clanid]);
					$db->commit();
				} catch (Throwable $error) {
					if ($db->inTransaction()) $db->rollBack();
					throw $error;
				}
				invalidatedatacache("clandata-$clanid");
			}else{
				rawoutput('<tr class="' . ($v%2?"trlight":"trdark").'"><td>', true);
				if ($row['c'] == 1) {
					$memb = sprintf($memb_1, $row['c']);
				} else {
					$memb = sprintf($memb_n, $row['c']);
				}
				output_notl("&#149; &#60;%s&#62; <a href='clan.php?detail=%s'>%s</a> %s`n",
						$row['clanshort'],
						$row['clanid'],
						full_sanitize(htmlentities($row['clanname']), ENT_COMPAT, getsetting("charset", "ISO-8859-1")),
						$memb, true);
				rawoutput('</td></tr>');
				addnav("","clan.php?detail={$row['clanid']}");
				$v++;
			}
		}
		rawoutput("</table>", true);
		addnav("Return to the Lobby","clan.php");
	}else{
		output("`7You ask %s`7 for the clan listings.  She stares at you blankly for a few moments, then says, \"`5Sorry pal, no one has had enough gumption to start up a clan yet.  Maybe that should be you, eh?`7\"",$registrar);
		addnav("Apply for a New Clan","clan.php?op=new");
		addnav("Return to the Lobby","clan.php");
	}

	page_footer();
?>