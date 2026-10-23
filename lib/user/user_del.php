<?php
require_once("lib/charcleanup.php");
require_once("lib/admin_authority.php");
require_once("lib/user_editor.php");
// The list's Del button: POST, CSRF and a one-use intent bound to the account it was shown for. The cleanup, the
// account row, the news line and the debug log commit together or not at all. An account that is already gone
// (deleted since the list was shown, or a repeated form) reports 1.1.2's "0 user deleted." and changes nothing.
try { resurrection_admin_fields(); }
catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
$row = resurrection_admin_account($userid, 'acctid,login');
$deleted = 0;
if ($row !== null) {
	resurrection_consume_action('user-delete-' . $userid, resurrection_user_delete_context($row));
	try {
		$deleted = resurrection_admin_mutation(function () use ($userid) {
			try { $row = resurrection_char_delete($userid, CHAR_DELETE_MANUAL); }
			catch (OutOfBoundsException $error) { return 0; }
			addnews("`#%s was unmade by the gods.", $row['name'], true);
			debuglog("deleted user" . $row['name'] . "'0");
			return 1;
		}, $userid);
	} catch (ResurrectionCharacterDeletionVetoed $error) {
		// A module kept the account: nothing was deleted, and the page says so.
		$deleted = 0;
	} catch (Throwable $error) { resurrection_admin_failure($error, 'the user editor'); }
	// An editor who deleted their own account keeps 1.1.2's result page; the next request ends the session.
	// The page save must not write output for the deleted account.
	if ($deleted && $userid === (int)$session['user']['acctid'] && !defined('NO_SAVE_USER')) define('NO_SAVE_USER', true);
}
output($deleted." user deleted.");
