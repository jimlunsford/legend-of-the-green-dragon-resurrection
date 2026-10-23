<?php
// addnews ready
// translator ready
// mail ready
define("OVERRIDE_FORCED_NAV",true);
require_once("common.php");
require_once("lib/http.php");
require_once("lib/admin_tools.php");
tlschema("translatortool");

check_su_access(SU_IS_TRANSLATOR);

/**
 * The translation rows a save for this namespace and text would touch, as 1.1.2 selected them: this language, the
 * same text (MySQL compares it case-insensitively), and the namespace or its page.
 * @return list<array<string, mixed>>
 */
function resurrection_translatortool_rows(string $uri, string $text, bool $lock = false): array {
	$page = $uri;
	if (strpos($page,"?")!==false) $page = substr($page,0,strpos($page,"?"));
	return db_query("SELECT * FROM ".db_prefix("translations")." WHERE language=? AND intext=? AND (uri=? OR uri=?) ORDER BY tid".($lock ? " FOR UPDATE" : ""),
		true, [LANGUAGE, $text, $page, $uri]);
}

/** A save form is bound to its namespace, its text and the translations that existed when it was shown. */
function resurrection_translatortool_context(string $uri, string $text, array $rows): string {
	return resurrection_tool_context([LANGUAGE, $uri, $text, array_map(fn($row) => [(string)$row['tid'], (string)$row['uri'], (string)$row['intext'], (string)$row['outtext']], $rows)]);
}

/** One intent per namespace and text, so several translator popups can be open at once. */
function resurrection_translatortool_scope(string $uri, string $text): string {
	return 'translatortool-' . substr(hash('sha256', $uri . "\0" . $text), 0, 32);
}

$charset = getsetting("charset", "ISO-8859-1");
$op=httpget("op");
if ($op===false) $op = "";
if (!is_string($op)) { http_response_code(400); exit('Invalid request.'); }
if ($op==""){
	popup_header("Translator Tool");
	// `u` and `t` are checked text (namespace column 255, text column 64 KiB). 1.1.2 decoded them a second time and
	// stripped slashes added by magic quotes; the translator button encodes them once, so they are used as received.
	$uri = resurrection_tool_text($_GET, 'u', 255);
	$text = resurrection_tool_text($_GET, 't', 65535);

	$translation = translate_loadnamespace($uri);
	if (isset($translation[$text]))
		$trans = $translation[$text];
	else
		$trans = "";
	$namespace = translate_inline("Namespace:");
	$texta = translate_inline("Text:");
	$translation = translate_inline("Translation:");
	$saveclose = htmlentities(translate_inline("Save & Close"), ENT_QUOTES, $charset);
	$savenotclose = htmlentities(translate_inline("Save No Close"), ENT_QUOTES, $charset);
	rawoutput("<form action='translatortool.php?op=save' method='POST'>");
	rawoutput(resurrection_action_fields(resurrection_translatortool_scope($uri, $text), resurrection_translatortool_context($uri, $text, resurrection_translatortool_rows($uri, $text))));
	rawoutput("$namespace <input name='uri' value=\"".htmlentities($uri, ENT_QUOTES, $charset)."\" readonly><br/>");
	rawoutput("$texta<br>");
	rawoutput("<textarea name='text' cols='60' rows='5' readonly>".htmlentities($text, ENT_QUOTES, $charset)."</textarea><br/>");
	rawoutput("$translation<br>");
	rawoutput("<textarea name='trans' cols='60' rows='5'>".htmlentities($trans, ENT_QUOTES, $charset)."</textarea><br/>");
	rawoutput("<input type='submit' value=\"$saveclose\" class='button'>");
	rawoutput("<input type='submit' value=\"$savenotclose\" class='button' name='savenotclose'>");
	rawoutput("</form>");
	popup_footer();
}elseif ($op=='save'){
	// 1.1.2 took any POST, without CSRF, and pasted uri, text and trans into its SQL. The save is now bound to the form
	// that was shown for this namespace and text, and its reads and writes are one transaction.
	resurrection_tool_post(['uri', 'text', 'trans', 'savenotclose']);
	$uri = resurrection_tool_text($_POST, 'uri', 255);
	$text = resurrection_tool_text($_POST, 'text', 65535);
	$trans = resurrection_tool_text($_POST, 'trans', 65535);
	resurrection_tool_text($_POST, 'savenotclose', 255);

	$page = $uri;
	if (strpos($page,"?")!==false) $page = substr($page,0,strpos($page,"?"));
	$shown = resurrection_translatortool_rows($uri, $text);
	resurrection_consume_action(resurrection_translatortool_scope($uri, $text), resurrection_translatortool_context($uri, $text, $shown));
	resurrection_tool_transaction(function () use ($uri, $text, $trans, $page, $shown) {
		global $session, $logd_version;
		$rows = resurrection_translatortool_rows($uri, $text, true);
		if (resurrection_translatortool_context($uri, $text, $rows) !== resurrection_translatortool_context($uri, $text, $shown)) throw new DomainException('Translations changed.');
		$author = (string)$session['user']['login'];
		$insert = function () use ($uri, $text, $trans, $author, $logd_version) {
			// 1.1.2's version value on insert, trailing space included.
			db_query("INSERT INTO ".db_prefix("translations")." (language,uri,intext,outtext,author,version) VALUES (?,?,?,?,?,?)",
				true, [LANGUAGE, $uri, $text, $trans, $author, $logd_version." "]);
			// 1.1.2 matched the untranslated text against an undefined variable, so it never removed it; the text was
			// collected under this namespace.
			db_query("DELETE FROM " . db_prefix("untranslated") . " WHERE intext=? AND language=? AND namespace=?", true, [$text, LANGUAGE, $uri]);
		};
		if ($trans==""){
			db_query("DELETE FROM ".db_prefix("translations")." WHERE language=? AND intext=? AND (uri=? OR uri=?)", true, [LANGUAGE, $text, $page, $uri]);
			return;
		}
		// MySQL is case insensitive so we need to do it here.
		$exact = array_values(array_filter($rows, fn($row) => $row['intext'] == $text));
		if (count($rows)==0){
			$insert();
		}elseif(count($rows)==1){
			if ($exact !== []){
				db_query("UPDATE ".db_prefix("translations")." SET author=?, version=?, uri=?, outtext=? WHERE tid=?", true, [$author, $logd_version, $uri, $trans, (int)$exact[0]['tid']]);
			}else{
				$insert();
			}
		}else{
			// 1.1.2 meant to update every exact match but kept only the last one, and with none it ran "IN ()", a
			// database error. Every exact match is updated; with none the text is added, as for a single row.
			if ($exact === []) { $insert(); return; }
			foreach ($exact as $row) {
				db_query("UPDATE ".db_prefix("translations")." SET author=?, version=?, uri=?, outtext=? WHERE tid=?", true, [$author, $logd_version, $page, $trans, (int)$row['tid']]);
			}
		}
	}, 'the translator tool');
	invalidatedatacache("translations-".$uri."-".LANGUAGE);
	if (httppost("savenotclose")>""){
		// Always this tool's own list; 1.1.2 put the request's namespace into the Location unencoded.
		header("Location: translatortool.php?op=list&u=".rawurlencode($page));
		exit();
	}else{
		popup_header("Updated");
		rawoutput("<script language='javascript'>window.close();</script>");
		popup_footer();
	}
}elseif($op=="list"){
	popup_header("Translation List");
	$u = resurrection_tool_text($_GET, 'u', 255);
	$sql = "SELECT uri,count(*) AS c FROM " . db_prefix("translations") . " WHERE language=? GROUP BY uri ORDER BY uri ASC";
	$result = db_query($sql, true, [LANGUAGE]);
	rawoutput("<form action='translatortool.php' method='GET'>");
	rawoutput("<input type='hidden' name='op' value='list'>");
	output("Known Namespaces:");
	rawoutput("<select name='u'>");
	while ($row = db_fetch_assoc($result)){
		// The option sends the namespace itself (1.1.2 sent it URL-encoded, which then matched no row).
		rawoutput("<option value=\"".htmlentities($row['uri'], ENT_QUOTES, $charset)."\">".htmlentities($row['uri'], ENT_QUOTES, $charset)." ({$row['c']})</option>",true);
	}
	rawoutput("</select>");
	$show = htmlentities(translate_inline("Show"), ENT_QUOTES, $charset);
	rawoutput("<input type='submit' class='button' value=\"$show\">");
	rawoutput("</form>");
	$ops = translate_inline("Ops");
	$from = translate_inline("From");
	$to = translate_inline("To");
	$version = translate_inline("Version");
	$author = translate_inline("Author");
	$norows = translate_inline("No rows found");
	rawoutput("<table border='0' cellpadding='2' cellspacing='0'>");
	rawoutput("<tr class='trhead'><td>$ops</td><td>$from</td><td>$to</td><td>$version</td><td>$author</td></tr>");
	$sql = "SELECT * FROM " . db_prefix("translations") . " WHERE language=? AND uri=?";
	$result = db_query($sql, true, [LANGUAGE, $u]);
	if (db_num_rows($result)>0){
		$i=0;
		while ($row = db_fetch_assoc($result)){
			$i++;
			rawoutput("<tr class='".($i%2?"trlight":"trdark")."'><td>");
			$edit = translate_inline("Edit");
			// The namespace and text, encoded once (1.1.2 HTML-encoded them first, so the form looked up other text).
			rawoutput("<a href='translatortool.php?u=".rawurlencode($row['uri'])."&t=".rawurlencode($row['intext'])."'>$edit</a>");
			rawoutput("</td><td>");
			rawoutput(htmlentities($row['intext'], ENT_QUOTES, $charset));
			rawoutput("</td><td>");
			rawoutput(htmlentities($row['outtext'], ENT_QUOTES, $charset));
			rawoutput("</td><td>");
			rawoutput(htmlentities((string)$row['version'], ENT_QUOTES, $charset));
			rawoutput("</td><td>");
			rawoutput(htmlentities((string)$row['author'], ENT_QUOTES, $charset));
			rawoutput("</td></tr>");
		}
	}else{
		rawoutput("<tr><td colspan='5'>$norows</td></tr>");
	}
	rawoutput("</table>");
	popup_footer();
}else{
	http_response_code(400);
	exit('Invalid request.');
}
?>
