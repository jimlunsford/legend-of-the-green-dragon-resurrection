<?php
// translator ready
// addnews ready
// mail ready
require_once("common.php");
require_once("lib/commentary.php");
require_once("lib/sanitize.php");
require_once("lib/http.php");

check_su_access(0xFFFFFFFF &~ SU_DOESNT_GIVE_GROTTO);
addcommentary();
tlschema("superuser");

require_once("lib/superusernav.php");
superusernav();

$op = httpget('op');
if ($op=="keepalive"){
	// A session keepalive. It writes only the admin's own laston, a server value that every page view writes in
	// saveuser() (1.1.2 too), so it stays a GET without CSRF. The query is bound, and the page refreshes itself:
	// 1.1.2 echoed the raw request URI into the refresh tag.
	db_query("UPDATE " . db_prefix("accounts") . " SET laston=? WHERE acctid=?", true, [date("Y-m-d H:i:s"), (int)$session['user']['acctid']]);
	echo '<html><meta http-equiv="Refresh" content="30;url=superuser.php?op=keepalive"></html><body>'.date("Y-m-d H:i:s")."</body></html>";
	exit();
}elseif ($op=="newsdelete"){
	// 1.1.2 deleted on a GET link with newsid pasted into SQL, for any staff flag that enters the grotto. news.php shows
	// the Del button only to comment moderators, so that flag guards the delete. It is a POST with CSRF and a one-use
	// intent bound to the news line as it was shown, and it returns only to the news page.
	require_once("lib/admin_tools.php");
	require_once("lib/return_links.php");
	check_su_access(SU_EDIT_COMMENTS);
	resurrection_tool_post();
	$newsid = filter_var(httpget('newsid'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>4294967295]]);
	if ($newsid === false) { http_response_code(400); exit('Invalid request.'); }
	$rows = db_query("SELECT * FROM " . db_prefix("news") . " WHERE newsid=?", true, [$newsid]);
	if (count($rows) !== 1) { http_response_code(404); exit('This news line no longer exists.'); }
	resurrection_consume_action('news-delete-' . $newsid, resurrection_news_delete_context($rows[0]));
	resurrection_tool_transaction(function () use ($newsid, $rows) {
		$locked = db_query("SELECT * FROM " . db_prefix("news") . " WHERE newsid=? FOR UPDATE", true, [$newsid]);
		if (count($locked) !== 1 || resurrection_news_delete_context($locked[0]) !== resurrection_news_delete_context($rows[0])) throw new DomainException('News changed.');
		db_query("DELETE FROM " . db_prefix("news") . " WHERE newsid=?", true, [$newsid]);
	}, 'the news');
	redirect(resurrection_news_return(httpget('return')));
}

page_header("Superuser Grotto");

output("`^You duck into a secret cave that few know about. ");
if ($session['user']['sex']){
  	output("Inside you are greeted by the sight of numerous muscular bare-chested men who wave palm fronds at you and offer to feed you grapes as you lounge on Greco-Roman couches draped with silk.`n`n");
}else{
	output("Inside you are greeted by the sight of numerous scantily clad buxom women who wave palm fronds at you and offer to feed you grapes as you lounge on Greco-Roman couches draped with silk.`n`n");
}
commentdisplay("", "superuser","Engage in idle conversation with other gods:",25);
addnav("Actions");
if ($session['user']['superuser'] & SU_EDIT_PETITIONS) addnav("Petition Viewer","viewpetition.php");
if ($session['user']['superuser'] & SU_EDIT_COMMENTS) addnav("C?Recent Commentary","moderate.php");
if ($session['user']['superuser'] & SU_EDIT_COMMENTS) addnav("B?Player Bios","bios.php");
if ($session['user']['superuser'] & SU_EDIT_DONATIONS) addnav("Donator Page","donators.php");
if ($session['user']['superuser'] & SU_IS_TRANSLATOR) addnav("U?Untranslated Texts", "untranslated.php");

addnav("Editors");
if ($session['user']['superuser'] & SU_EDIT_USERS) addnav("User Editor","user.php");
if ($session['user']['superuser'] & SU_EDIT_USERS) addnav("Title Editor","titleedit.php");
if ($session['user']['superuser'] & SU_EDIT_CREATURES) addnav("E?Creature Editor","creatures.php");
if ($session['user']['superuser'] & SU_EDIT_MOUNTS) addnav("Mount Editor","mounts.php");
if ($session['user']['superuser'] & SU_EDIT_MOUNTS) addnav("Companion Editor","companions.php");
if ($session['user']['superuser'] & SU_EDIT_CREATURES) addnav("Taunt Editor","taunt.php");
if ($session['user']['superuser'] & SU_EDIT_CREATURES) addnav("Master Editor","masters.php");
if (file_exists("looteditor.php") &&
		$session['user']['superuser'] & SU_EDIT_ITEMS) {
	addnav("Loot Editor","looteditor.php");
}
if ($session['user']['superuser'] & SU_EDIT_EQUIPMENT) addnav("Weapon Editor","weaponeditor.php");
if ($session['user']['superuser'] & SU_EDIT_EQUIPMENT) addnav("Armor Editor","armoreditor.php");
if ($session['user']['superuser'] & SU_EDIT_COMMENTS) addnav("Nasty Word Editor","badword.php");
if ($session['user']['superuser'] & SU_MANAGE_MODULES) addnav("Manage Modules","modules.php");

if ($session['user']['superuser'] & SU_EDIT_CONFIG) addnav("Mechanics");
if ($session['user']['superuser'] & SU_EDIT_CONFIG) addnav("Game Settings","configuration.php");
if ($session['user']['superuser'] & SU_EDIT_CONFIG) addnav("Referring URLs","referers.php");
if ($session['user']['superuser'] & SU_EDIT_CONFIG) addnav("Stats","stats.php");
/*//*/if (file_exists("gamelog.php") &&
/*//*/		$session['user']['superuser'] & SU_EDIT_CONFIG) {
/*//*/	addnav("Gamelog Viewer","gamelog.php");
/*//*/}

addnav("Module Configurations");

modulehook("superuser", array(), true);

page_footer();
?>