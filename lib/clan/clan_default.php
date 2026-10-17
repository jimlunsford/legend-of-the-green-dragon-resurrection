<?php
		modulehook("collapse{", array("name"=>"clanentry"));
		output("Having pressed the secret levers and turned the secret knobs on the lock of the door to your clan's hall, you gain entrance and chat with your clan mates.`n`n");
		modulehook("}collapse");

		require_once("lib/clan_authority.php");
		$result = db_query("SELECT name FROM " . db_prefix("accounts")  . " WHERE acctid=?", true, [(int)$claninfo['motdauthor']]);
		$row = db_fetch_assoc($result);
		$motdauthname = $row['name'] ?? '';

		$result = db_query("SELECT name FROM " . db_prefix("accounts") . " WHERE acctid=?", true, [(int)$claninfo['descauthor']]);
		$row = db_fetch_assoc($result);
		$descauthname = $row['name'] ?? '';

		if ($claninfo['clanmotd'] != '') {
			rawoutput("<div style='margin-left: 15px; padding-left: 15px;'>");
			output("`&`bCurrent MoTD:`b `#by %s`2`n",$motdauthname);
			output_notl(nltoappon($claninfo['clanmotd'])."`n");
			rawoutput("</div>");
			output_notl("`n");
		}

		commentdisplay("", "clan-{$claninfo['clanid']}","Speak",25,($claninfo['customsay']>''?$claninfo['customsay']:"says"));

		modulehook("clanhall");

		if ($claninfo['clandesc'] != '') {
			modulehook("collapse{", array("name"=>"collapsedesc"));
			output("`n`n`&`bCurrent Description:`b `#by %s`2`n",$descauthname);
			output_notl(nltoappon($claninfo['clandesc']));
			modulehook("}collapse");
		}
		$result = db_query("SELECT count(*) AS c, clanrank FROM " . db_prefix("accounts") . " WHERE clanid=? GROUP BY clanrank ORDER BY clanrank DESC", true, [(int)$claninfo['clanid']]);
		// begin collapse
		modulehook("collapse{", array("name"=>"clanmemberdet"));
		output("`n`n`bMembership Details:`b`n");
		$leaders = 0;
		while ($row = db_fetch_assoc($result)){
			output_notl($ranks[$row['clanrank']].": `0".$row['c']."`n");
			if ($row['clanrank']>CLAN_OFFICER) $leaders += $row['c'];
		}
		output("`n");
		$noleader = translate_inline("`^There is currently no leader!  Promoting %s`^ to leader as they are the highest ranking member (or oldest member in the event of a tie).`n`n");
		if ($leaders==0){
			//There's no leader here, probably because the leader's account
			//expired.
			// Historical housekeeping on view: it takes no request input and is idempotent. The clan is
			// locked and rechecked so two viewers promote at most one member.
			try {
				resurrection_clan_mutation(function () use ($noleader) {
					global $session;
					$clanid = (int)$session['user']['clanid'];
					$members = db_query("SELECT name,acctid,clanrank FROM " . db_prefix("accounts") . " WHERE clanid=? ORDER BY acctid FOR UPDATE", true, [$clanid]);
					foreach ($members as $member) if ($member['clanrank'] > CLAN_OFFICER) return;
					$rows = db_query("SELECT name,acctid,clanrank FROM " . db_prefix("accounts") . " WHERE clanid=? AND clanrank > ? ORDER BY clanrank DESC, clanjoindate, acctid LIMIT 1", true, [$clanid, CLAN_APPLICANT]);
					if ($rows) {
						$row = $rows[0];
						if ($row['acctid']==$session['user']['acctid']){
							//if it's the current user, we'll need to update their
							//session in order for the db write to take effect.
							$session['user']['clanrank']=CLAN_LEADER;
						} else {
							db_query("UPDATE " . db_prefix("accounts") . " SET clanrank=? WHERE acctid=? AND clanid=?", true, [CLAN_LEADER, (int)$row['acctid'], $clanid]);
						}
						output_notl($noleader,$row['name']);
					} else {
						// There are no viable leaders.  But we cannot disband the clan
						// here.
					}
				});
			} catch (DomainException $error) {
				// Another request changed this account first; the next view repeats the check.
			}
		}
		// end collapse
		modulehook("}collapse");

		if ($session['user']['clanrank']>CLAN_MEMBER){
			addnav("Update MoTD / Clan Desc","clan.php?op=motd");
		}
		addnav("M?View Membership","clan.php?op=membership");
		addnav("Online Members","list.php?op=clan");
		addnav("Your Clan's Waiting Area","clan.php?op=waiting");
		addnav("Withdraw From Your Clan","clan.php?op=withdrawconfirm");
?>