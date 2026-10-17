<?php
		require_once("lib/clan_authority.php");
		// GET only asks; the protected POST withdraws, with succession or deletion, in one transaction.
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			output("Are you sure you want to withdraw from your clan?");
			addnav("Withdraw?");
			addnav("No","clan.php");
			$actor = resurrection_clan_account((int)$session['user']['acctid']);
			if ($actor !== null) resurrection_clan_form("clan.php?op=withdraw", 'clan-withdraw', resurrection_clan_context($ranks, $actor), resurrection_clan_button(translate_inline("Yes")));
		}else{
			try {
				resurrection_clan_fields();
				$actor = resurrection_clan_account((int)$session['user']['acctid']);
				if ($actor === null) throw new DomainException('Member unavailable.');
				resurrection_consume_action('clan-withdraw', resurrection_clan_context($ranks, $actor));
				$withdrawn = resurrection_clan_mutation(function () use ($ranks, $actor) {
					global $session;
					$clanid = (int)$session['user']['clanid'];
					if ($clanid < 1 || $session['user']['clanrank'] <= CLAN_APPLICANT) throw new DomainException('Not a clan member.');
					// Lock every member so succession and deletion see one consistent clan.
					db_query('SELECT acctid FROM '.db_prefix('accounts').' WHERE clanid=? ORDER BY acctid FOR UPDATE', true, [$clanid]);
					db_query('SELECT clanid FROM '.db_prefix('clans').' WHERE clanid=? FOR UPDATE', true, [$clanid]);
					modulehook("clan-withdraw", array('clanid'=>$session['user']['clanid'], 'clanrank'=>$session['user']['clanrank'], 'acctid'=>$session['user']['acctid']));
					if ($session['user']['clanrank']>=CLAN_LEADER){
						//first test to see if we were the leader.
						$row = db_query("SELECT count(*) AS c FROM " . db_prefix("accounts") . " WHERE clanid=? AND clanrank>=? AND acctid<>?", true, [$clanid, CLAN_LEADER, (int)$session['user']['acctid']])[0];
						if ($row['c']==0){
							//we were the solitary leader.
							$rows = db_query("SELECT name,acctid,clanrank FROM " . db_prefix("accounts") . " WHERE clanid=? AND clanrank > ? AND acctid<>? ORDER BY clanrank DESC, clanjoindate, acctid LIMIT 1", true, [$clanid, CLAN_APPLICANT, (int)$session['user']['acctid']]);
							if ($rows){
								//there is no alternate leader, let's promote the
								//highest ranking member (or oldest member in the
								//event of a tie).  This will capture even people
								//who applied for membership.
								db_query("UPDATE " . db_prefix("accounts") . " SET clanrank=? WHERE acctid=?", true, [CLAN_LEADER, (int)$rows[0]['acctid']]);
								output("`^Promoting %s`^ to leader as they are the highest ranking member (or oldest member in the event of a tie).`n`n",$rows[0]['name']);
							}else{
								//There are no other members, we need to delete the clan.
								modulehook("clan-delete", array("clanid"=>$clanid));
								db_query("DELETE FROM " . db_prefix("clans") . " WHERE clanid=?", true, [$clanid]);
								//just in case we goofed, we don't want to have to worry
								//about people being associated with a deleted clan.
								db_query("UPDATE " . db_prefix("accounts") . " SET clanid=0,clanrank=?,clanjoindate=NULL WHERE clanid=? AND acctid<>?", true, [CLAN_APPLICANT, $clanid, (int)$session['user']['acctid']]);
								output("`^As you were the last member of this clan, it has been deleted.");
							}
						}else{
							//we don't have to do anything special with this clan as
							//although we were leader, there is another leader already
							//to take our place.
						}
					}else{
						//we don't have to do anything special with this clan as we were
						//not the leader, and so there should still be other members.
					}
					$withdraw_subj = array("`\$Clan Withdraw: `&%s`0",$session['user']['name']);
					$msg = array("`^One of your clan members has resigned their membership.  `&%s`^ has surrendered their position within your clan!",$session['user']['name']);
					resurrection_clan_delete_notices($withdraw_subj);
					resurrection_clan_notify_officers($clanid, (int)$session['user']['acctid'], $withdraw_subj, $msg);
					$session['user']['clanid']=0;
					$session['user']['clanrank']=CLAN_APPLICANT;
					$session['user']['clanjoindate']=null;
					return $clanid;
				});
			} catch (Throwable $error) { resurrection_clan_failure($error); }
			invalidatedatacache("clandata-$withdrawn");
			output("`&You have withdrawn from your clan.");
			addnav("Clan Options");
			addnav("Return to the Lobby","clan.php");
		}
?>
