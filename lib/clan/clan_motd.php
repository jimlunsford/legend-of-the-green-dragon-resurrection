<?php
		page_header("Update Clan Description / MoTD");
		addnav("Clan Options");
		require_once("lib/clan_authority.php");
		// Authority is the actor's own clan and rank: read from the account row each request, rechecked under lock.
		$clanid = (int)$session['user']['clanid'];
		if ($session['user']['clanrank']>=CLAN_OFFICER){
			$leader = $session['user']['clanrank']>=CLAN_LEADER;
			if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
				// One protected POST, bound to the clan's stored text; values are stored as entered and bound.
				try {
					resurrection_clan_fields(['clanmotd','clandesc', ...($leader ? ['customsay'] : [])]);
					$text = [];
					foreach (['clanmotd'=>4096, 'clandesc'=>4096, 'customsay'=>15] as $key=>$limit) {
						if (array_key_exists($key, $_POST)) $text[$key] = resurrection_clan_text(\Resurrection\Http\Input::string($_POST, $key), $limit, $key === 'customsay');
					}
				} catch (InvalidArgumentException $error) { resurrection_clan_failure($error); }
				$stored = resurrection_clan_text_row($clanid);
				if ($stored === null) resurrection_clan_failure(new DomainException('Clan unavailable.'));
				$context = resurrection_clan_text_context($stored, $session['user']);
				resurrection_consume_action('clan-motd', $context);
				try {
					$updated = resurrection_clan_mutation(function () use ($clanid, $text, $context) {
						global $session;
						if ((int)$session['user']['clanid'] !== $clanid || $session['user']['clanrank'] < CLAN_OFFICER) throw new DomainException('Not an officer of this clan.');
						$clan = resurrection_clan_text_row($clanid, true);
						if ($clan === null || resurrection_clan_text_context($clan, $session['user']) !== $context) throw new DomainException('Clan text changed.');
						$actor = (int)$session['user']['acctid'];
						$updated = [];
						if (isset($text['clanmotd']) && $text['clanmotd'] !== (string)$clan['clanmotd']) {
							db_query("UPDATE " . db_prefix("clans") . " SET clanmotd=?,motdauthor=? WHERE clanid=?", true, [$text['clanmotd'], $actor, $clanid]);
							$updated[] = 'motd';
						}
						if (isset($text['clandesc'])) {
							// A blocked description stays blocked until a moderator unblocks it.
							if ((int)$clan['descauthor'] === RESURRECTION_CLAN_DESC_BLOCKED) throw new InvalidArgumentException('Description blocked.');
							if ($text['clandesc'] !== (string)$clan['clandesc']) {
								db_query("UPDATE " . db_prefix("clans") . " SET clandesc=?,descauthor=? WHERE clanid=?", true, [$text['clandesc'], $actor, $clanid]);
								$updated[] = 'desc';
							}
						}
						if (isset($text['customsay']) && $session['user']['clanrank'] >= CLAN_LEADER && $text['customsay'] !== (string)$clan['customsay']) {
							db_query("UPDATE " . db_prefix("clans") . " SET customsay=? WHERE clanid=?", true, [$text['customsay'], $clanid]);
							$updated[] = 'say';
						}
						return $updated;
					});
				} catch (Throwable $error) { resurrection_clan_failure($error); }
				invalidatedatacache("clandata-$clanid");
				if (in_array('motd', $updated, true)) output("Updating MoTD`n");
				if (in_array('desc', $updated, true)) output("Updating description`n");
				if (in_array('say', $updated, true)) output("Updating custom say line`n");
			}
			$claninfo = array_merge($claninfo, resurrection_clan_text_row($clanid) ?? []);
			$motdauthname = resurrection_clan_author($claninfo['motdauthor']);
			$descauthname = resurrection_clan_author($claninfo['descauthor']);

			output("`&`bCurrent MoTD:`b `#by %s`2`n",$motdauthname);
			output_notl(nltoappon((string)$claninfo['clanmotd'])."`n");
			output("`&`bCurrent Description:`b `#by %s`2`n",$descauthname);
			output_notl(nltoappon((string)$claninfo['clandesc'])."`n");

			addnav("","clan.php?op=motd");
			rawoutput("<form action='clan.php?op=motd' method='POST'>".resurrection_action_fields('clan-motd', resurrection_clan_text_context($claninfo, $session['user'])));
			output("`&`bMoTD:`b `7(4096 chars)`n");
			rawoutput("<textarea name='clanmotd' cols='50' rows='10' class='input' style='width: 66%'>".resurrection_clan_attribute($claninfo['clanmotd'])."</textarea><br>");
			output("`n`&`bDescription:`b `7(4096 chars)`n");
			$blocked = translate_inline("Your clan has been blocked from posting a description.`n");
			if ((int)$claninfo['descauthor']===RESURRECTION_CLAN_DESC_BLOCKED){
				output_notl($blocked);
			}else{
				rawoutput("<textarea name='clandesc' cols='50' rows='10' class='input' style='width: 66%'>".resurrection_clan_attribute($claninfo['clandesc'])."</textarea><br>");
			}
			if ($leader){
				output("`n`&`bCustom Talk Line`b `7(blank means \"says\" -- 15 chars max)`n");
				rawoutput("<input name='customsay' value=\"".resurrection_clan_attribute($claninfo['customsay'])."\" class='input' maxlength=\"15\"><br/>");
			}
			$save = resurrection_clan_attribute(translate_inline("Save"));
			rawoutput("<input type='submit' class='button' value='$save'>");
			rawoutput("</form>");
		}else{
			if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') http_response_code(403);
			output("You do not have authority to change your clan's motd or description.");
		}
		addnav("Return to your clan hall","clan.php");
?>
