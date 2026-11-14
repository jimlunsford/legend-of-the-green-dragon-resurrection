<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready

function templatereplace($itemname,$vals=false){
	global $template;
	if (!isset($template[$itemname]))
		output("`bWarning:`b The `i%s`i template part was not found!`n", $itemname);
	$out = $template[$itemname];
	if (!is_array($vals)) return $out;
	@reset($vals);
	while (list($key,$val)=resurrection_array_next($vals)){
		if (strpos($out,"{".$key."}")===false){
			output("`bWarning:`b the `i%s`i piece was not found in the `i%s`i te".
					"mplate part! (%s)`n", $key, $itemname, $out);
			$out .= $val;
		}else{
			$out = str_replace("{"."$key"."}",$val,$out);
		}
	}
	return $out;
}

function prepare_template($force=false){
	if (!$force) {
		if (defined("TEMPLATE_IS_PREPARED")) return;
		define("TEMPLATE_IS_PREPARED",true);
	}

 	global $templatename, $templatemessage, $template, $session, $y, $z, $y2, $z2, $copyright, $lc, $x, $templatetags;
	require_once __DIR__ . '/../src/Ui/SafeFrame.php';
	// The one template (Phase 5 task 15). 1.1.2 chose a skin by the template cookie, then the defaultskin setting, then
	// jade.htm; the skins and skin selection are gone, so neither the cookie nor the setting is read.
	$templatename="resurrection.htm";
	$templatemessage="";
	// A template that is missing or unreadable, or that fails the checks below, is replaced by the safe frame
	// (\Resurrection\Ui\SafeFrame, Phase 5 close-out): 1.1.2 fell back to jade.htm, and with one template there is no
	// other file to fall back to. The checks themselves are 1.1.2's, unchanged, plus the attribution line's.
	$readable = is_file("templates/$templatename") && is_readable("templates/$templatename");
	$template = $readable ? loadtemplate($templatename) : array();
	if (!$readable) {
		$templatemessage .= "The template file templates/$templatename is missing or cannot be read\n";
	}
	// A part the checks read that the file lacks counts as empty, so the checks name what is missing.
	foreach (array("header", "footer", "popupfoot") as $part) {
		if ($readable && !isset($template[$part])) {
			$template[$part] = "";
			$templatemessage .= "You do not have a $part part\n";
		}
	}
	if (!$readable) {
		//Nothing to check: the safe frame is used.
	}elseif ($session['templatename'] == $templatename &&
			$session['templatemtime']==filemtime("templates/$templatename") && $templatemessage==""){
		//We do not have to check that the template is valid since it has
		//not changed.
	}else{
		//We need to double check that the template is valid since the name
		// or file mod time have changed.

		//tags that must appear in the header
		$templatetags=array("title","headscript","script");
		while (list($key,$val)=resurrection_array_next($templatetags)){
			if (strpos($template['header'],"{".$val."}")===false && $val)
				$templatemessage .=
					"You do not have {".$val."} defined in your header\n";
		}
		//tags that must appear in the footer
		$templatetags=array();
		while (list($key,$val)=resurrection_array_next($templatetags)){
			if (strpos($template['footer'],"{".$val."}")===false && $val)
				$templatemessage .=
					"You do not have {".$val."} defined in your footer\n";
		}

		//tags that may appear anywhere but must appear
		$templatetags=array("nav","stats","petition","motd","mail",
				"paypal","source","version", "copyright");
		while (list($key,$val)=resurrection_array_next($templatetags)){
			if (!$key) array_push($templatetags,$y2^$z2);
			if (strpos($template['header'],"{".$val."}")===false &&
					strpos($template['footer'],"{".$val."}")===false && $val)
				$templatemessage .=
					"You do not have {".$val."} defined in either your header or footer\n";
		}
		//the Resurrection attribution line, both links exact, in the footer and the popup footer (Phase 5 close-out)
		if (!\Resurrection\Ui\SafeFrame::hasAttribution($template))
			$templatemessage .=
				"You do not have the Resurrection attribution line, with both its links, in both your footer and popupfoot\n";
		if ($templatemessage==""){
			$session['templatename'] = $templatename;
			$session['templatemtime'] = filemtime("templates/$templatename");
		}
	}
	if ($templatemessage!=""){
		// 1.1.2 echoed the notice to everyone, before the page, and rendered its fallback skin. The safe frame is
		// rendered instead; the notice goes to the error log and, inside the page, to superusers only.
		resurrection_template_refused($templatemessage);
		$template=\Resurrection\Ui\SafeFrame::parts();
	}
	// The stylesheet and script URLs the frame prints carry the file's version (Phase 6 task 18, N1): after the checks,
	// which read the template as written.
	require_once __DIR__ . '/../src/Ui/AssetUrl.php';
	foreach ($template as $part => $text) {
		if (is_string($text)) $template[$part] = \Resurrection\Ui\AssetUrl::rewrite($text);
	}
	// The copyright line, as in 1.1.2. In 1.1.2 a refused skin skipped this (its fallback showed the credit line without
	// the license block); the safe frame carries both.
	{
		$y = 0;
		$z = $y2^$z2;
		// $x is 0, or the tamper message string. PHP 8 counts 0 > '' as true (PHP 5 and 7 did not), which put "0" in
		// place of the copyright line on every logged-in page; only a message replaces it, as in 1.1.2.
		if ($session['user']['loggedin'] && is_string($x) && $x > ''){
			$$z = $x;
		}
		$$z = $lc . $$z . "<br />";
	}

}

/**
 * Records why templates/resurrection.htm was refused (Phase 5 close-out): to the error log, and as the notice that
 * page_header() and popup_header() show to a superuser inside the safe frame. 1.1.2 echoed the same notice, "You have
 * one or more errors in your template page!" and each reason, to every visitor.
 * The notice's braces are entities, or page_header() would strip "{copyright}" from it as an unused tag.
 *
 * @param string $message the reasons, one a line
 */
function resurrection_template_refused($message){
	global $resurrection_template_notice;
	error_log("Resurrection: templates/resurrection.htm was refused; the safe frame is used: " . str_replace("\n", "; ", trim($message)));
	$resurrection_template_notice = "<div class=\"rz-template-notice\" role=\"alert\"><b>You have one or more errors in your template page!</b><br>"
		. nl2br(str_replace(array("{", "}"), array("&#123;", "&#125;"), htmlspecialchars($message, ENT_QUOTES, "UTF-8"))) . "</div>";
}

/**
 * The template notice for this page: the reasons the template was refused, for a superuser (an account with the
 * Grotto, as village.php decides it); nothing for anyone else, or when the template was used.
 */
function resurrection_template_notice(){
	global $resurrection_template_notice, $session;
	if (empty($resurrection_template_notice)) return "";
	if (empty($session['user']['loggedin']) || !((int)($session['user']['superuser'] ?? 0) & ~SU_DOESNT_GIVE_GROTTO)) return "";
	return $resurrection_template_notice;
}
?>
