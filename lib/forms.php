<?php
if (!\defined('RESURRECTION_ENTRY') && \PHP_SAPI !== 'cli') { \http_response_code(404); exit; }
function previewfield($name, $startdiv=false, $talkline="says", $showcharsleft=true, $info=false, $default=false) {
	global $schema,$session;
	$talkline = translate_inline($talkline, $schema);
	$youhave = translate_inline("You have ");
	$charsleft = translate_inline(" characters left.");

	if ($startdiv === false)
		$startdiv = "";
	// The live preview (Phase 6 task 3): assets/ui/behaviors.js does what 1.1.2's inline previewtext$name() did, from
	// these attributes on the field (its onKeyUp handler is gone too). The same markup, colors and counts.
	$attribute = static fn ($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$preview = " data-rz-preview='" . $attribute($name) . "'"
		. " data-rz-preview-start='" . $attribute(appoencode($startdiv)) . "'"
		. " data-rz-preview-talk='" . ($talkline !== false ? "1" : "0") . "'"
		. ($talkline !== false ? " data-rz-preview-talkline='" . $attribute(appoencode($talkline)) . "'" : "")
		. " data-rz-preview-gm='" . ($talkline !== false && ($session['user']['superuser']&SU_IS_GAMEMASTER) ? "1" : "0") . "'"
		. " data-rz-preview-chars='" . ($showcharsleft == true ? "1" : "0") . "'"
		. " data-rz-preview-youhave='" . $attribute($youhave) . "' data-rz-preview-charsleft='" . $attribute($charsleft) . "'";
	if ($charsleft == true) {
		rawoutput("<span id='charsleft$name'></span>");
	}
	if (!is_array($info)) {
		if ($default) {
			rawoutput("<input name='$name' id='input$name' maxlength='255'$preview data-rz-preview-limit='200' value='$default'>");
		} else {
			rawoutput("<input name='$name' id='input$name' maxlength='255'$preview data-rz-preview-limit='200'>");
		}
	} else {
		if (isset($info['maxlength'])) {
			$l = $info['maxlength'];
		} else {
			$l=200;
		}
		if (isset($info['type']) && $info['type'] == 'textarea') {
			rawoutput("<textarea name='$name' id='input$name'$preview data-rz-preview-limit='" . (int)$l . "' ");
		} else {
			rawoutput("<input name='$name' id='input$name'$preview data-rz-preview-limit='" . (int)$l . "' ");
		}
		foreach ($info as $key=>$val){
			rawoutput("$key='$val'");
		}
		if (isset($info['type']) && $info['type'] == 'textarea') {
			rawoutput(">");
			if ($default) {
				rawoutput($default);
			}
			rawoutput("</textarea>");
		} else {
			if ($default) {
				rawoutput(" value='$default'>");
			} else {
				rawoutput(">");
			}
		}
	}
	rawoutput("<div id='previewtext$name'></div>");
}
?>