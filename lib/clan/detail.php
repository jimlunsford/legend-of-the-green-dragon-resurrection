<?php
	require_once("lib/clan_authority.php");
	// The clan id comes from the URL: it must be a clan id, and every query binds it.
	try {
		$detail = \Resurrection\Http\Input::integer($_GET, 'detail', 0, 1);
	} catch (InvalidArgumentException $error) { resurrection_clan_failure($error); }
	$moderator = ($session['user']['superuser'] & SU_EDIT_COMMENTS) == SU_EDIT_COMMENTS;
	$row1 = resurrection_clan_text_row($detail);
	if ($row1 === null) { http_response_code(404); exit('No such clan.'); }
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
		if (!$moderator) { http_response_code(403); exit('Only moderators may change clan details.'); }
		try {
			resurrection_clan_fields(['clanname','clanshort','block','unblock']);
			$clanname = \Resurrection\Http\Input::string($_POST, 'clanname');
			$clanshort = \Resurrection\Http\Input::string($_POST, 'clanshort');
			$block = \Resurrection\Http\Input::string($_POST, 'block') !== '';
			$unblock = \Resurrection\Http\Input::string($_POST, 'unblock') !== '';
			if ($block && $unblock) throw new InvalidArgumentException('Block or unblock, not both.');
		} catch (InvalidArgumentException $error) { resurrection_clan_failure($error); }
		resurrection_require_post();
		$registrar = getsetting('clanregistrar','`%Karissa');
		$e = array (translate_inline("%s`7 looks over your form but informs you that your clan name must consist only of letters, spaces, apostrophes, or dashes.  Also, your short name can consist only of letters. She hands you a blank form."),
			translate_inline("%s`7 looks over your form but informs you that you must have at least 5 and no more than 50 characters in your clan's name (and they must consist only of letters, spaces, apostrophes, or dashes), then hands you a blank form."),
			translate_inline("%s`7 looks over your form but informs you that you must have at least 2 and no more than 5 characters in your clan's short name (and they must all be letters), then hands you a blank form."),
			translate_inline("%s`7 looks over your form but informs you that the clan name %s is already taken, and hands you a blank form."),
			translate_inline("%s`7 looks over your form but informs you that the short name %s is already taken, and hands you a blank form."),
		);
		// 1.1.2 renamed only when both names were given. A changed name now meets the clan-creation rules.
		$rename = $clanname !== '' && $clanshort !== '';
		$changed = $rename && ($clanname !== (string)$row1['clanname'] || $clanshort !== (string)$row1['clanshort']);
		$rule = $changed ? resurrection_clan_name_rule($clanname, $clanshort) : null;
		if ($rule !== null){
			output_notl($e[$rule], $registrar);
			output_notl("`n");
		}else{
			$context = resurrection_clan_text_context($row1, $session['user']);
			resurrection_consume_action('clan-detail', $context);
			$blockdesc = translate_inline("Description blocked for inappropriate usage.");
			try {
				$result = resurrection_clan_mutation(function () use ($detail, $context, $changed, $clanname, $clanshort, $block, $unblock, $blockdesc) {
					global $session;
					if (($session['user']['superuser'] & SU_EDIT_COMMENTS) != SU_EDIT_COMMENTS) throw new DomainException('Not a moderator.');
					$clan = resurrection_clan_text_row($detail, true);
					if ($clan === null || resurrection_clan_text_context($clan, $session['user']) !== $context) throw new DomainException('Clan changed.');
					if ($changed) {
						// The same uniqueness as clan creation, excluding this clan.
						if (db_query("SELECT clanid FROM " . db_prefix("clans") . " WHERE clanname=? AND clanid<>? FOR UPDATE", true, [$clanname, $detail])) return ['error'=>3];
						if (db_query("SELECT clanid FROM " . db_prefix("clans") . " WHERE clanshort=? AND clanid<>? FOR UPDATE", true, [$clanshort, $detail])) return ['error'=>4];
						db_query("UPDATE " . db_prefix("clans") . " SET clanname=?,clanshort=? WHERE clanid=?", true, [$clanname, $clanshort, $detail]);
					}
					$blocked = (int)$clan['descauthor'] === RESURRECTION_CLAN_DESC_BLOCKED;
					if ($block){
						if ($blocked) throw new DomainException('Already blocked.');
						db_query("UPDATE " . db_prefix("clans") . " SET descauthor=?, clandesc=? WHERE clanid=?", true, [RESURRECTION_CLAN_DESC_BLOCKED, $blockdesc, $detail]);
					}elseif ($unblock){
						if (!$blocked) throw new DomainException('Not blocked.');
						db_query("UPDATE " . db_prefix("clans") . " SET descauthor=0, clandesc='' WHERE clanid=?", true, [$detail]);
					}
					return [];
				});
			} catch (Throwable $error) { resurrection_clan_failure($error); }
			invalidatedatacache("clandata-$detail");
			if (isset($result['error'])){
				output_notl($e[$result['error']], $registrar, $result['error'] === 3 ? $clanname : $clanshort);
				output_notl("`n");
			}else{
				if ($rename) output("Updating clan names`n");
				if ($block) output("Blocking public description`n");
				elseif ($unblock) output("UNblocking public description`n");
			}
			$row1 = resurrection_clan_text_row($detail) ?? $row1;
		}
	}
	if ($moderator){
		$url = "clan.php?detail=$detail";
		rawoutput("<div id='hidearea'>");
		rawoutput("<form action='$url' method='POST'>".resurrection_action_fields('clan-detail', resurrection_clan_text_context($row1, $session['user'])));
		addnav("",$url);
		output("Superuser / Moderator renaming:`n");
		output("Long Name: ");
		rawoutput("<input name='clanname' value=\"".resurrection_clan_attribute($row1['clanname'])."\" maxlength=50 size=50>");
		output("`nShort Name: ");
		rawoutput("<input name='clanshort' value=\"".resurrection_clan_attribute($row1['clanshort'])."\" maxlength=5 size=5>");
		output_notl("`n");
		$save = resurrection_clan_attribute(translate_inline("Save"));
		rawoutput("<input type='submit' class='button' value=\"$save\">");
		$snu = resurrection_clan_attribute(translate_inline("Save & UNblock public description"));
		$snb = resurrection_clan_attribute(translate_inline("Save & Block public description"));
		if ((int)$row1['descauthor']===RESURRECTION_CLAN_DESC_BLOCKED)
			rawoutput("<input type='submit' name='unblock' value=\"$snu\" class='button'>");
		else
			rawoutput("<input type='submit' name='block' value=\"$snb\" class='button'>");
		rawoutput("</form>");
		rawoutput("</div>");
		rawoutput("<script language='JavaScript'>var hidearea = document.getElementById('hidearea');hidearea.style.visibility='hidden';hidearea.style.display='none';</script>",true);
		$e = translate_inline("Edit Clan Info");
		rawoutput("<a href='#' onClick='hidearea.style.visibility=\"visible\"; hidearea.style.display=\"inline\"; return false;'>$e</a>",true);
		output_notl("`n");
	}

	$clandesc = nltoappon((string)$row1['clandesc']);
	output_notl($clandesc);
	if ($clandesc != "") output ("`n`n");
	output("`0This is the current clan membership of %s < %s >:`n",$row1['clanname'],$row1['clanshort']);
	page_header("Clan Membership for %s &lt;%s&gt;", full_sanitize($row1['clanname']), full_sanitize($row1['clanshort']));
	addnav("Clan Options");
	$rank = translate_inline("Rank");
	$name = translate_inline("Name");
	$dk = translate_inline("Dragon Kills");
	$jd = translate_inline("Join Date");
	rawoutput("<table border='0' cellpadding='2' cellspacing='0'>");
	rawoutput("<tr class='trhead'><td>$rank</td><td>$name</td><td>$dk</td><td>$jd</td></tr>");
	$i=0;
	$result = db_query("SELECT acctid,name,login,clanrank,clanjoindate,dragonkills FROM " . db_prefix("accounts") . " WHERE clanid=? ORDER BY clanrank DESC,clanjoindate", true, [$detail]);
	$tot = 0;
	//little hack with the hook...can't think of any other way
	$ranks = array(CLAN_APPLICANT=>"`!Applicant`0",CLAN_MEMBER=>"`#Member`0",CLAN_OFFICER=>"`^Officer`0",CLAN_LEADER=>"`&Leader`0", CLAN_FOUNDER=>"`\$Founder");
	$args = modulehook("clanranks", array("ranks"=>$ranks, "clanid"=>$detail));
	$ranks = translate_inline($args['ranks']);
	//end
	while ($row=db_fetch_assoc($result)){
		$i++;
		$tot += $row['dragonkills'];
		rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
		rawoutput("<td>");
		output_notl($ranks[$row['clanrank']]); //translated earlier
		rawoutput("</td><td>");
		$link = "bio.php?char=".$row['acctid']."&ret=".urlencode($_SERVER['REQUEST_URI']);
		rawoutput("<a href='$link'>");
		addnav("", $link);
		output_notl("`&%s`0", $row['name']);
		rawoutput("</a>");
		rawoutput("</td><td align='center'>");
		output_notl("`\$%s`0", $row['dragonkills']);
		rawoutput("</td><td>");
		output_notl("`3%s`0", $row['clanjoindate']);
		rawoutput("</td></tr>");
	}
	rawoutput("</table>");
	output("`n`n`^This clan has a total of `\$%s`^ dragon kills.",$tot);
?>
