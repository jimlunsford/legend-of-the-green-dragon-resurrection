<?php
		require_once("lib/clan_authority.php");
		addnav("Clan Hall","clan.php");
		addnav("Clan Options");
		// The legacy setrank/whoacctid/remove GET links only display; changes are the protected POST below.
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
			try {
				resurrection_clan_fields(['clanaction']);
				$target = \Resurrection\Http\Input::integer($_GET, 'member', 0, 1);
				$action = \Resurrection\Http\Input::choice($_POST, 'clanaction', ['promote','demote','stepdown','remove'], '');
				if ($target === 0 || $action === '') throw new InvalidArgumentException('Invalid member action.');
				$actor = resurrection_clan_account((int)$session['user']['acctid']);
				$member = resurrection_clan_account($target);
				if ($actor === null || $member === null) throw new DomainException('Member unavailable.');
				resurrection_consume_action('clan-member-'.$target, resurrection_clan_context($ranks, $actor, $member));
				resurrection_clan_mutation(function () use ($target, $action, $ranks, $apply_short, $claninfo) {
					global $session;
					// Authority comes only from the locked actor and target rows.
					$actor = resurrection_clan_account((int)$session['user']['acctid'], true);
					$member = resurrection_clan_account($target, true);
					if ($actor === null || $member === null) throw new DomainException('Member unavailable.');
					$allowed = resurrection_clan_rank_actions($ranks, $actor, $member);
					if (!isset($allowed[$action])) throw new DomainException('Not permitted.');
					$setrank = $allowed[$action];
					$args = modulehook("clan-setrank", array("setrank"=>$setrank, "login"=>$member['login'], "name"=>$member['name'], "acctid"=>$target, "clanid"=>$actor['clanid'], "oldrank"=>$member['clanrank']));
					if ($action == 'remove') {
						db_query('UPDATE '.db_prefix('accounts').' SET clanrank=?,clanid=0,clanjoindate=NULL WHERE acctid=? AND clanid=? AND clanrank<=?', true,
							[CLAN_APPLICANT, $target, $actor['clanid'], $actor['clanrank']]);
						debuglog("Player {$session['user']['name']} removed player {$member['login']} from {$claninfo['clanname']}.", $target);
						//delete unread application emails from this user.
						//breaks if the applicant has had their name changed via
						//dragon kill, superuser edit, or lodge color change
						resurrection_clan_delete_notices(array($apply_short, $member['name']));
					} elseif (!(isset($args['handled']) && $args['handled'])) {
						if ($target === (int)$session['user']['acctid']) $session['user']['clanrank'] = $setrank;
						else db_query('UPDATE '.db_prefix('accounts').' SET clanrank=? WHERE acctid=? AND clanid=?', true, [$setrank, $target, $actor['clanid']]);
						debuglog("Player {$session['user']['name']} changed rank of {$member['name']} to {$setrank}.", $target);
					}
				}, [$target]);
			} catch (Throwable $error) { resurrection_clan_failure($error); }
		}
		output("This is your current clan membership:`n");
		$result = db_query("SELECT name,login,acctid,clanid,clanrank,laston,clanjoindate,dragonkills,level FROM " . db_prefix("accounts") . " WHERE clanid=? ORDER BY clanrank DESC ,dragonkills DESC,level DESC,clanjoindate", true, [(int)$claninfo['clanid']]);
		rawoutput("<table border='0' cellpadding='2' cellspacing='0'>");
		$rank = translate_inline("Rank");
		$name = translate_inline("Name");
		$lev = translate_inline("Level");
		$dk = translate_inline("Dragon Kills");
		$jd = translate_inline("Join Date");
		$lo = translate_inline("Last On");
		$ops = translate_inline("Operations");
		$promote = translate_inline("Promote");
		$demote = translate_inline("Demote");
		$stepdown = translate_inline("`\$Step down as founder");
		$remove = translate_inline("Remove From Clan");
		$confirm = translate_inline("Are you sure you wish to remove this member from your clan?");
		rawoutput("<tr class='trhead'><td>$rank</td><td>$name</td><td>$lev</td><td>$dk</td><td>$jd</td><td>$lo</td>".($session['user']['clanrank']>CLAN_MEMBER?"<td>$ops</td>":"")."</tr>",true);
		$i=0;
		$tot = 0;
		require_once("lib/clan/func.php");
		$actor = resurrection_clan_account((int)$session['user']['acctid']);
		while ($row=db_fetch_assoc($result)){
			$i++;
			$tot += $row['dragonkills'];
			rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
			rawoutput("<td>");
			output_notl($ranks[$row['clanrank']]);
			rawoutput("</td><td>");
			$link = "bio.php?char=".$row['acctid']."&ret=".urlencode($_SERVER['REQUEST_URI']);
			rawoutput("<a href='".htmlspecialchars($link, ENT_QUOTES, 'UTF-8')."'>", true);
			addnav("", $link);
			output_notl("`&%s`0", $row['name']);
			rawoutput("</a>");
			rawoutput("</td><td align='center'>");
			output_notl("`^%s`0",$row['level']);
			rawoutput("</td><td align='center'>");
			output_notl("`\$%s`0",$row['dragonkills']);
			rawoutput("</td><td>");
			output_notl("`3%s`0",(string)$row['clanjoindate']);
			rawoutput("</td><td>");
			output_notl("`#%s`0",reltime(strtotime($row['laston'])));
			rawoutput("</td>");
			if ($session['user']['clanrank']>CLAN_MEMBER){
				rawoutput("<td>");
				// Exactly the 1.1.2 operations for this row, as one protected form bound to both ranks.
				$member = ['acctid'=>(int)$row['acctid'], 'clanid'=>(int)$row['clanid'], 'clanrank'=>(int)$row['clanrank']];
				$allowed = $actor === null ? [] : resurrection_clan_rank_actions($ranks, $actor, $member);
				$buttons = "[ ";
				$buttons .= isset($allowed['promote']) ? resurrection_clan_button($promote, 'clanaction', 'promote') : htmlspecialchars(full_sanitize($promote), ENT_QUOTES, 'UTF-8');
				$buttons .= " | ";
				if (isset($allowed['demote'])) $buttons .= resurrection_clan_button($demote, 'clanaction', 'demote');
				elseif (isset($allowed['stepdown'])) $buttons .= resurrection_clan_button($stepdown, 'clanaction', 'stepdown');
				else $buttons .= htmlspecialchars(full_sanitize($demote), ENT_QUOTES, 'UTF-8');
				$buttons .= " | ";
				$buttons .= isset($allowed['remove'])
					? '<button class="button" name="clanaction" value="remove" onclick="return confirm('.htmlspecialchars(json_encode(full_sanitize($confirm)), ENT_QUOTES, 'UTF-8').');">'.htmlspecialchars(full_sanitize($remove), ENT_QUOTES, 'UTF-8').'</button>'
					: htmlspecialchars(full_sanitize($remove), ENT_QUOTES, 'UTF-8');
				$buttons .= " ]";
				if ($allowed !== [] && $actor !== null) {
					resurrection_clan_form("clan.php?op=membership&member=".(int)$row['acctid'], 'clan-member-'.(int)$row['acctid'],
						resurrection_clan_context($ranks, $actor, $member), $buttons, resurrection_presentation('inline-form'));
				} else rawoutput($buttons);
				rawoutput("</td>");
			}
			rawoutput("</tr>");
		}
		rawoutput("</table>");
		output("`n`n`^This clan has a total of `\$%s`^ dragon kills.",$tot);
?>
