<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
	page_header("Clan Hall for %s",  full_sanitize($claninfo['clanname']));
	addnav("Clan Options");
	if ($op==""){
		require_once("lib/clan/clan_default.php");
	}elseif ($op=="motd"){
		require_once("lib/clan/clan_motd.php");
	}elseif ($op=="membership"){
		require_once("lib/clan/clan_membership.php");
	}elseif ($op=="withdrawconfirm" || $op=="withdraw"){
		// The confirmation is a protected form; only its POST withdraws.
		require_once("lib/clan/clan_withdraw.php");
	}

?>