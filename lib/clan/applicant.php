<?php
	page_header("Clan Halls");
	$registrar=getsetting('clanregistrar','`%Karissa');
	addnav("Clan Options");
	output("`b`c`&Clan Halls`c`b");
	if ($op=="apply"){
		require_once("lib/clan/applicant_apply.php");
	}elseif ($op=="new"){
		require_once("lib/clan/applicant_new.php");
	}else{
		output("`7You stand in the center of a great marble lobby filled with pillars.");
		output("All around the walls of the lobby are various doors which lead to various clan halls.");
		output("The doors each possess a variety of intricate mechanisms which are obviously elaborate locks designed to be opened only by those who have been educated on how to operate them.");
		output("Nearby, you watch another warrior glance about nervously to make sure no one is watching before touching various levers and knobs on the door.");
		output("With a large metallic \"Chunk\" the lock on the door disengages, and the door swings silently open, admitting the warrior before slamming shut.`n`n");
		output("In the center of the lobby sits a highly polished desk, behind which sits `%%s`7, the clan registrar.",$registrar);
		output("She can take your filing for a new clan, or accept your application to an existing clan.`n`n");
/*//*/	modulehook("clan-enter");
		if ($op=="withdraw" && isset($claninfo['clanid']) && $claninfo['clanid']>0 && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
			// GET only asks; the protected POST tears up the application.
			require_once("lib/clan_authority.php");
			output("`7Withdraw your application to %s?`n`n", $claninfo['clanname']);
			$actor = resurrection_clan_account((int)$session['user']['acctid']);
			if ($actor !== null) resurrection_clan_form("clan.php?op=withdraw", 'clan-unapply', resurrection_clan_context($ranks, $actor), resurrection_clan_button(translate_inline("Withdraw Application")));
			addnav("Waiting Area","clan.php?op=waiting");
		}elseif ($op=="withdraw" && isset($claninfo['clanid']) && $claninfo['clanid']>0){
			require_once("lib/clan_authority.php");
			try {
				resurrection_clan_fields();
				$actor = resurrection_clan_account((int)$session['user']['acctid']);
				if ($actor === null) throw new DomainException('Applicant unavailable.');
				resurrection_consume_action('clan-unapply', resurrection_clan_context($ranks, $actor));
				resurrection_clan_mutation(function () use ($apply_subj) {
					global $session;
					if ((int)$session['user']['clanid'] < 1 || (int)$session['user']['clanrank'] !== CLAN_APPLICANT) throw new DomainException('No application.');
					$session['user']['clanid']=0;
					$session['user']['clanrank']=CLAN_APPLICANT;
					$session['user']['clanjoindate']=null;
					resurrection_clan_delete_notices($apply_subj);
				});
			} catch (Throwable $error) { resurrection_clan_failure($error); }
			output("`7You tell `%%s`7 that you're no longer interested in joining %s.",$registrar, $claninfo['clanname']);
			output("She reaches into her desk, withdraws your application, and tears it up.  \"`5You wouldn't have been happy there anyhow, I don't think,`7\" as she tosses the shreds in her trash can.");
			$claninfo = array();
			output("You are not a member of any clan.");
			addnav("Apply for Membership to a Clan","clan.php?op=apply");
			addnav("Apply for a New Clan","clan.php?op=new");
		}else{
			if (isset($claninfo['clanid']) && $claninfo["clanid"]>0){
				//applied for membership to a clan
				output("`7You approach `%%s`7 who smiles at you, but lets you know that your application to %s hasn't yet been accepted.",$registrar,$claninfo['clanname']);
				output("Perhaps you'd like to take a seat in the waiting area, she suggests.");
				addnav("Waiting Area","clan.php?op=waiting");
				addnav("Withdraw Application","clan.php?op=withdraw");
			}else{
				//hasn't applied for membership to any clan.
				output("You are not a member of any clan.");
				addnav("Apply for Membership to a Clan","clan.php?op=apply");
				addnav("Apply for a New Clan","clan.php?op=new");
			}
		}
	}
?>