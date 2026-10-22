<?php
// translator ready
// addnews ready
// mail ready

// Okay, someone wants to use this outside of normal game flow.. no real harm
define("OVERRIDE_FORCED_NAV",true);

// Translate Untranslated Strings
// Originally Written by Christian Rutsch
// Slightly modified by JT Traub
require_once("common.php");
require_once("lib/http.php");

check_su_access(SU_IS_TRANSLATOR);

tlschema("untranslated");

require_once("lib/admin_tools.php");

$op = httpget('op');
// Bound below: 1.1.2 pasted the language preference, the namespace and the texts into its SQL.
$language = (string)($session['user']['prefs']['language'] ?? '');
$charset = getsetting("charset", "ISO-8859-1");

/** The collected text a translation form is for, or null when it is no longer untranslated. */
function resurrection_untranslated_row(string $language, string $namespace, string $intext, bool $lock = false): ?array {
	$rows = db_query("SELECT intext,language,namespace FROM " . db_prefix("untranslated") . " WHERE intext=? AND language=? AND namespace=?" . ($lock ? " FOR UPDATE" : ""),
		true, [$intext, $language, $namespace]);
	return $rows[0] ?? null;
}

/**
 * Save a translation of a collected text and remove it from the untranslated list, in one transaction. 1.1.2 ran the
 * two statements separately from the posted values; the text must still be untranslated (otherwise 409).
 */
function resurrection_untranslated_save(string $scope, string $language, string $namespace, string $intext, string $outtext): void {
	resurrection_consume_action($scope, resurrection_tool_context([$language, $namespace, $intext]));
	resurrection_tool_transaction(function () use ($language, $namespace, $intext, $outtext) {
		global $session, $logd_version;
		if (resurrection_untranslated_row($language, $namespace, $intext, true) === null) throw new DomainException('Already translated.');
		db_query("INSERT INTO " . db_prefix("translations") . " (language,uri,intext,outtext,author,version) VALUES (?,?,?,?,?,?)", true,
			[$language, $namespace, $intext, $outtext, (string)$session['user']['login'], $logd_version]);
		db_query("DELETE FROM " . db_prefix("untranslated") . " WHERE intext = ? AND language = ? AND namespace = ?", true, [$intext, $language, $namespace]);
	}, 'the untranslated texts');
	invalidatedatacache("translations-".$namespace."-".$language);
}

if (!is_string($op) && $op !== false) { http_response_code(400); exit('Invalid request.'); }
page_header("Untranslated Texts");

if ($op == "list") {
	$mode = resurrection_tool_text($_GET, 'mode', 10);
	$namespace = resurrection_tool_text($_GET, 'ns', 255);

	if ($mode == "save") {
		// 1.1.2 saved from any request without CSRF. The edit form's POST carries an intent bound to the text it showed.
		resurrection_tool_post(['op', 'ns', 'intext', 'outtext']);
		$intext = resurrection_tool_text($_POST, 'intext', 65535);
		$outtext = resurrection_tool_text($_POST, 'outtext', 65535);
		resurrection_tool_text($_POST, 'op', 10);
		resurrection_tool_text($_POST, 'ns', 255);
		resurrection_require_post();
		if ($outtext <> "") {
			resurrection_untranslated_save('untranslated-' . substr(hash('sha256', $namespace . "\0" . $intext), 0, 32), $language, $namespace, $intext, $outtext);
		}
	}

	if ($mode == "edit") {
		$intext = resurrection_tool_text($_GET, 'intext', 65535);
		// The form translates a collected text of this namespace; 1.1.2 offered a form for any text in the URL.
		if (resurrection_untranslated_row($language, $namespace, $intext) === null) { http_response_code(404); exit('This text is not in the untranslated list.'); }
		rawoutput("<form action='untranslated.php?op=list&mode=save&ns=".rawurlencode($namespace)."' method='post'>");
		rawoutput(resurrection_action_fields('untranslated-' . substr(hash('sha256', $namespace . "\0" . $intext), 0, 32), resurrection_tool_context([$language, $namespace, $intext])));
		addnav("", "untranslated.php?op=list&mode=save&ns=".rawurlencode($namespace));
	} else {
		rawoutput("<form action='untranslated.php?op=list' method='get'>");
		addnav("", "untranslated.php?op=list");
	}

	$sql = "SELECT namespace,count(*) AS c FROM " . db_prefix("untranslated") . " WHERE language=? GROUP BY namespace ORDER BY namespace ASC";
	$result = db_query($sql, true, [$language]);
	rawoutput("<input type='hidden' name='op' value='list'>");
	output("Known Namespaces:");
	rawoutput("<select name='ns'>");
	while ($row = db_fetch_assoc($result)){
		rawoutput("<option value=\"".htmlentities($row['namespace'], ENT_QUOTES, $charset)."\"".(($row['namespace'] == $namespace) ? "selected" : "").">".htmlentities($row['namespace'], ENT_QUOTES, $charset)." ({$row['c']})</option>");
	}
	rawoutput("</select>");
	rawoutput("<input type='submit' class='button' value='". htmlentities(translate_inline("Show"), ENT_QUOTES, $charset) ."'>");
	rawoutput("<br>");

	if ($mode == "edit") {
		rawoutput(translate_inline("Text:"). "<br>");
		rawoutput("<textarea name='intext' cols='60' rows='5' readonly>".htmlentities($intext, ENT_QUOTES, $charset)."</textarea><br/>");
		rawoutput(translate_inline("Translation:"). "<br>");
		rawoutput("<textarea name='outtext' cols='60' rows='5'></textarea><br/>");
		rawoutput("<input type='submit' value='". htmlentities(translate_inline("Save"), ENT_QUOTES, $charset) ."' class='button'>");
	} else {
		rawoutput("<table border='0' cellpadding='2' cellspacing='0'>");
		rawoutput("<tr class='trhead'><td>". translate_inline("Ops") ."</td><td>". translate_inline("Text") ."</td></tr>");
		$sql = "SELECT * FROM " . db_prefix("untranslated") . " WHERE language=? AND namespace=?";
		$result = db_query($sql, true, [$language, $namespace]);
		if (db_num_rows($result)>0){
			$i = 0;
			while ($row = db_fetch_assoc($result)){
				$i++;
				rawoutput("<tr class='".($i%2?"trlight":"trdark")."'><td>");
				rawoutput("<a href='untranslated.php?op=list&mode=edit&ns=". rawurlencode($row['namespace']) ."&intext=". rawurlencode($row['intext']) ."'>". translate_inline("Edit") ."</a>");
				addnav("", "untranslated.php?op=list&mode=edit&ns=". rawurlencode($row['namespace']) ."&intext=". rawurlencode($row['intext']));
				rawoutput("</td><td>");
				rawoutput(htmlentities($row['intext'], ENT_QUOTES, $charset));
				rawoutput("</td></tr>");
			}
		}else{
			rawoutput("<tr><td colspan='2'>". translate_inline("No rows found") ."</td></tr>");
		}
		rawoutput("</table>");
	}

	rawoutput("</form>");

} else {
	if ($op == "step2") {
		// 1.1.2 took the language, namespace and text from the posted form. They must be the text the form showed.
		resurrection_tool_post(['intext', 'outtext', 'language', 'namespace']);
		$intext = resurrection_tool_text($_POST, 'intext', 65535);
		$outtext = resurrection_tool_text($_POST, 'outtext', 65535);
		$namespace = resurrection_tool_text($_POST, 'namespace', 255);
		$postedlanguage = resurrection_tool_text($_POST, 'language', 10);
		resurrection_require_post();
		if ($outtext <> "") {
			resurrection_untranslated_save('untranslated-step2', $postedlanguage, $namespace, $intext, $outtext);
		}
	}

	$sql = "SELECT count(intext) AS count FROM " . db_prefix("untranslated");
	$singleRowResult = db_query($sql);
	$count = db_fetch_assoc($singleRowResult);
	if ($count['count'] > 0) {
		$sql = "SELECT * FROM " . db_prefix("untranslated") . " WHERE language = ? ORDER BY rand(?) LIMIT 1";
		$result = db_query($sql, true, [$language, (int)e_rand()]);
		if (db_num_rows($result) == 1) {
			$row = db_fetch_assoc($result);
			// Stored raw (1.1.2 stripped slashes here that magic quotes had added to the request, not to the row).
			$submit = htmlentities(translate_inline("Save Translation"), ENT_QUOTES, $charset);
			$skip = htmlentities(translate_inline("Skip Translation"), ENT_QUOTES, $charset);
			rawoutput("<form action='untranslated.php?op=step2' method='post'>");
			rawoutput(resurrection_action_fields('untranslated-step2', resurrection_tool_context([(string)$row['language'], (string)$row['namespace'], (string)$row['intext']])));
			output("`^`cThere are `&%s`^ untranslated texts in the database.`c`n`n", $count['count']);
			rawoutput("<table width='80%'>");
			rawoutput("<tr><td width='30%'>");
			output("Target Language: %s", $row['language']);
			rawoutput("</td><td></td></tr>");
			rawoutput("<tr><td width='30%'>");
			output("Namespace: %s", $row['namespace']);
			rawoutput("</td><td></td></tr>");
			// Escaped, and read-only: the translation is saved for the text shown. 1.1.2 printed the stored text as HTML
			// and its hidden `id` (a column the table does not have) was an undefined value.
			rawoutput("<tr><td width='30%'><textarea cols='35' rows='4' name='intext' readonly>".htmlentities($row['intext'], ENT_QUOTES, $charset)."</textarea></td>");
			rawoutput("<td width='30%'><textarea cols='25' rows='4' name='outtext'></textarea></td></tr></table>");
			rawoutput("<input type='hidden' name='language' value=\"".htmlentities($row['language'], ENT_QUOTES, $charset)."\">");
			rawoutput("<input type='hidden' name='namespace' value=\"".htmlentities($row['namespace'], ENT_QUOTES, $charset)."\">");
			rawoutput("<input type='submit' value='$submit' class='button'>");
			rawoutput("</form>");
			rawoutput("<form action='untranslated.php' method='post'>");
			rawoutput("<input type='submit' value='$skip' class='button'>");
			rawoutput("</form>");
			addnav("", "untranslated.php?op=step2");
			addnav("", "untranslated.php");
		} else {
			output("There are `&%s`^ untranslated texts in the database, but none for your selected language.", $count['count']);
			output("Please change your language to translate these texts.");
		}
	} else {
		output("There are no untranslated texts in the database!");
		output("Congratulations!!!");
	} // end if
} // end list if
addnav("R?Restart Translator", "untranslated.php");
addnav("N?Translate by Namespace", "untranslated.php?op=list");
require_once("lib/superusernav.php");
superusernav();
page_footer();

?>