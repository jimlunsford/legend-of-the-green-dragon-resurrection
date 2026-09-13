<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/commentary.php");
require_once("lib/sanitize.php");
require_once("lib/http.php");

tlschema("moderate");

addcommentary();

check_su_access(SU_EDIT_COMMENTS);

require_once("lib/superusernav.php");
superusernav();

addnav("Other");
addnav("Commentary Overview","moderate.php");

addnav("B?Player Bios","bios.php");
if ($session['user']['superuser'] & SU_AUDIT_MODERATION){
	addnav("Audit Moderation","moderate.php?op=audit");
}
addnav("Review by Moderator");
addnav("Commentary");
addnav("Sections");
addnav("Modules");
addnav("Clan Halls");

$op = httpget("op");
if ($op=="commentdelete"){
    resurrection_require_post();
    try {
        $ids = resurrection_comment_ids($_POST['comment'] ?? []);
        if (!empty($_POST['delnban'])) {
            check_su_access(SU_EDIT_USERS);
            $reason = \Resurrection\Http\Input::string($_POST, 'reason', 'Banned for comments you posted.');
            if (strlen($reason) > 255) { throw new InvalidArgumentException('Invalid reason.'); }
            foreach ($ids as $id) {
                $rows = db_query('SELECT a.uniqueid,a.acctid FROM ' . db_prefix('commentary') . ' c JOIN ' . db_prefix('accounts') . ' a ON a.acctid=c.author WHERE c.commentid=?', true, [$id]);
                $account = db_fetch_assoc($rows);
                if ($account && $account['uniqueid'] !== '') {
                    db_query('INSERT INTO ' . db_prefix('bans') . ' (uniqueid,banexpire,banreason,banner) VALUES (?,?,?,?)', true,
                        [$account['uniqueid'], date('Y-m-d H:i:s', strtotime('+3 days')), $reason, $session['user']['name']]);
                    db_query('UPDATE ' . db_prefix('accounts') . ' SET loggedin=0 WHERE acctid=?', true, [(int)$account['acctid']]);
                }
            }
        }
        foreach ($ids as $id) {
            resurrection_delete_comment($session, $_SESSION, 'POST', ['removecomment' => (string)$id, 'csrf_token' => $_POST['csrf_token']]);
        }
    } catch (InvalidArgumentException | DomainException $error) { http_response_code(400); exit('Invalid moderation request.'); }
    redirect('moderate.php');
}

page_header("Comment Moderation");


if ($op==""){
	$area = \Resurrection\Http\Input::string($_GET, 'area');
    if ($area !== '' && !preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $area)) { http_response_code(400); exit('Invalid section.'); }
	$link = "moderate.php" . ($area ? "?area=$area" : "");
	$refresh = translate_inline("Refresh");
	rawoutput("<form action='$link' method='POST'>");
	rawoutput("<input type='submit' class='button' value='$refresh'>");
	rawoutput("</form>");
	addnav("", "$link");
	if ($area==""){
		talkform("X","says");
		commentdisplay("", "__all__","X",100);
	}else{
		commentdisplay("", $area,"X",100);
		talkform($area,"says");
	}
}elseif ($op=="audit"){
	check_su_access(SU_AUDIT_MODERATION);
	$subop = httpget("subop");
    if ($subop=="undelete") {
        resurrection_require_post();
        try {
            foreach (resurrection_comment_ids($_POST['mod'] ?? []) as $id) {
                resurrection_restore_comment($session, $_SESSION, 'POST', ['modid' => (string)$id, 'csrf_token' => $_POST['csrf_token']]);
            }
        } catch (InvalidArgumentException | DomainException $error) { http_response_code(400); exit('Invalid moderation request.'); }
    }

	$sql = "SELECT DISTINCT acctid, name FROM ".db_prefix("accounts").
		" INNER JOIN ".db_prefix("moderatedcomments").
		" ON acctid=moderator ORDER BY name";
	$result = db_query($sql);
	addnav("Commentary");
	addnav("Sections");
	addnav("Modules");
	addnav("Clan Halls");
	addnav("Review by Moderator");
	tlschema("notranslate");
	while ($row = db_fetch_assoc($result)){
		addnav(" ?".$row['name'],"moderate.php?op=audit&moderator={$row['acctid']}");
	}
	tlschema();
	addnav("Commentary");
	output("`c`bComment Auditing`b`c");
	$ops = translate_inline("Ops");
	$mod = translate_inline("Moderator");
	$when = translate_inline("When");
	$com = translate_inline("Comment");
	$unmod = translate_inline("Unmoderate");
	rawoutput("<form action='moderate.php?op=audit&subop=undelete' method='POST'>" . resurrection_csrf_field());
	addnav("","moderate.php?op=audit&subop=undelete");
	rawoutput("<table border='0' cellpadding='2' cellspacing='0'>");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$mod</td><td>$when</td><td>$com</td></tr>");
	$limit = "75";
	$where = "1=1 ";
	$moderator = \Resurrection\Http\Input::integer($_GET, 'moderator');
	if ($moderator > 0) $where.='AND moderator=? ';
	$sql = "SELECT name, ".db_prefix("moderatedcomments").
		".* FROM ".db_prefix("moderatedcomments")." LEFT JOIN ".
		db_prefix("accounts").
		" ON acctid=moderator WHERE $where ORDER BY moddate DESC LIMIT $limit";
	$result = db_query($sql, true, $moderator > 0 ? [$moderator] : []);
	$i=0;
	$clanrankcolors=array("`!","`#","`^","`&");
	while ($row = db_fetch_assoc($result)){
		$i++;
		rawoutput("<tr class='".($i%2?'trlight':'trdark')."'>");
		rawoutput("<td><input type='checkbox' name='mod[{$row['modid']}]' value='1'></td>");
		rawoutput("<td>");
		output_notl("%s", $row['name']);
		rawoutput("</td>");
		rawoutput("<td>");
		output_notl("%s", $row['moddate']);
		rawoutput("</td>");
		rawoutput("<td>");
		$comment = unserialize($row['comment'], ['allowed_classes' => false]);
		output_notl("`0(%s)", $comment['section']);

		if (($comment['clanrank'] ?? 0)>0)
			output_notl("%s<%s%s>`0", $clanrankcolors[ceil($comment['clanrank']/10)],
					$comment['clanshort'],
					$clanrankcolors[ceil($comment['clanrank']/10)]);
		output_notl("%s", $comment['name'] ?? 'Unknown');
		output_notl("-");
		output_notl("%s", comment_sanitize($comment['comment']));
		rawoutput("</td>");
		rawoutput("</tr>");
	}
	rawoutput("</table>");
	rawoutput("<input type='submit' class='button' value='$unmod'>");
	rawoutput("</form>");
}


addnav("Sections");
tlschema("commentary");
$vname = getsetting("villagename", LOCATION_FIELDS);
addnav(array("%s Square", $vname), "moderate.php?area=village");

if ($session['user']['superuser'] & ~SU_DOESNT_GIVE_GROTTO) {
	addnav("Grotto","moderate.php?area=superuser");
}

addnav("Land of the Shades","moderate.php?area=shade");
addnav("Grassy Field","moderate.php?area=grassyfield");

$iname = getsetting("innname", LOCATION_INN);
// the inn name is a proper name and shouldn't be translated.
tlschema("notranslate");
addnav($iname,"moderate.php?area=inn");
tlschema();

addnav("MotD","moderate.php?area=motd");
addnav("Veterans Club","moderate.php?area=veterans");
addnav("Hunter's Lodge","moderate.php?area=hunterlodge");
addnav("Gardens","moderate.php?area=gardens");
addnav("Clan Hall Waiting Area","moderate.php?area=waiting");

if (getsetting("betaperplayer", 1) == 1 && @file_exists("pavilion.php")) {
	addnav("Beta Pavilion","moderate.php?area=beta");
}
tlschema();

if ($session['user']['superuser'] & SU_MODERATE_CLANS){
	addnav("Clan Halls");
	$sql = "SELECT clanid,clanname,clanshort FROM " . db_prefix("clans") . " ORDER BY clanid";
	$result = db_query($sql);
	// these are proper names and shouldn't be translated.
	tlschema("notranslate");
	while ($row=db_fetch_assoc($result)){
		addnav(array("<%s> %s", $row['clanshort'], $row['clanname']),
				"moderate.php?area=clan-{$row['clanid']}");
	}
	tlschema();
} elseif ($session['user']['superuser'] & SU_EDIT_COMMENTS &&
		getsetting("officermoderate", 0)) {
	// the CLAN_OFFICER requirement was chosen so that moderators couldn't
	// just get accepted as a member to any random clan and then proceed to
	// wreak havoc.
	// although this isn't really a big deal on most servers, the choice was
	// made so that staff won't have to have another issue to take into
	// consideration when choosing moderators.  the issue is moot in most
	// cases, as players that are trusted with moderator powers are also
	// often trusted with at least the rank of officer in their respective
	// clans.
	if (($session['user']['clanid'] != 0) &&
			($session['user']['clanrank'] >= CLAN_OFFICER)) {
		addnav("Clan Halls");
		$sql = "SELECT clanid,clanname,clanshort FROM " . db_prefix("clans") . " WHERE clanid='" . $session['user']['clanid'] . "'";
		$result = db_query($sql);
		// these are proper names and shouldn't be translated.
		tlschema("notranslate");
		if ($row=db_fetch_assoc($result)){
			addnav(array("<%s> %s", $row['clanshort'], $row['clanname']),
					"moderate.php?area=clan-{$row['clanid']}");
		} else {
			debug ("There was an error while trying to access your clan.");
		}
		tlschema();
	}
}
addnav("Modules");
$mods = array();
$mods = modulehook("moderate", $mods);
reset($mods);

// These are already translated in the module.
tlschema("notranslate");
foreach ($mods as $area=>$name) {
	addnav($name, "moderate.php?area=$area");
}
tlschema();

page_footer();
?>