<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
// The page an operator sees when the database configuration file is there but cannot be used (Phase 6 task 3, S4;
// docs/PHASE6-TASK3-HARDENING-20261106.md). Until then an unreadable dbconnect.php was a blank 500 and a damaged one
// a PHP fatal error.

/**
 * Why the configuration cannot be used, as the page and the log say it: unreadable, damaged, or LOGD_DBCONNECT not
 * an absolute path. No reason names a value from the file.
 */
const RESURRECTION_CONFIG_FAILURES = [
	'unreadable' => 'The file exists, but PHP cannot read it.',
	'broken' => 'The file could not be loaded: PHP stopped with an error inside it. It is damaged, or it was changed by hand.',
	'relative' => 'LOGD_DBCONNECT is set, but it is not an absolute path.',
];

/**
 * Sends the configuration page and stops: status 503, what failed and which path, what to check, the full footer
 * credits (the template's, or the safe frame's). The reason goes to the PHP error log once. Nothing the file holds is
 * shown or logged: not its contents, not a credential, not PHP's own message (a parse error can quote the file).
 *
 * @param string $path The configuration file's path, as the game looked for it
 * @param string $reason A key of RESURRECTION_CONFIG_FAILURES
 * @param int|null $line For a damaged file, the line PHP stopped at
 */
function resurrection_config_failure(string $path, string $reason, ?int $line = null): never {
	$explained = RESURRECTION_CONFIG_FAILURES[$reason] ?? RESURRECTION_CONFIG_FAILURES['broken'];
	// The path is the operator's, never a secret; control characters are dropped from the log line.
	$logged = preg_replace('/[\x00-\x1f\x7f]/', '?', $path);
	error_log('Resurrection: the database configuration file cannot be used (' . $reason . ($line !== null ? ', line ' . $line : '') . '): ' . $logged);
	// Anything the file printed before it failed is dropped, never sent (common.php's buffer around the file).
	if (ob_get_level() > 0) ob_clean();
	http_response_code(503);
	header('Retry-After: 300');
	header('Cache-Control: no-store');
	if (!defined('DB_NODB')) define('DB_NODB', true);
	resurrection_operator_frame();
	page_header("The game cannot read its configuration");
	output("`#This game is installed, but it cannot use its database configuration file, so no page can be shown until the operator fixes it.`n`n");
	output("`^What failed: `#%s`n", $explained . ($line !== null ? " (line $line)" : ""));
	output("`^Which file: `#%s`n`n", $path);
	output("`^What to check:`n");
	output("`#- The owner: the file belongs to the user PHP runs as for this site, or to that user's group.`n");
	output("`#- The permissions: that user can read it. The installer writes it as 0600 (owner only); 0640 with the web server's group also works.`n");
	output("`#- The directories: PHP can enter each directory on the path.`n");
	output("`#- The file itself: it is the file the installer wrote, unchanged. If it is damaged, restore it from your backup.`n");
	output("`#- LOGD_DBCONNECT: when it is set, it must hold the file's absolute path.`n`n");
	output("`#The reason is also in the PHP error log. This page never shows what the file contains.");
	page_footer();
	exit;
}
