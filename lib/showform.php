<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
// translator ready
// addnews ready
// mail ready
require_once("lib/dump_item.php");

/**
 * The id of a showform() control, for its <label for> (Phase 5 task 9): unique per form, letters, digits, - and _.
 *
 * @param int $form the showform() call's number on the page
 * @param string $key the field's name
 * @return string
 */
function showform_control_id($form, $key){
	return "showform$form-" . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$key);
}

/**
 * The form's sections as tabs in the Resurrection template (Phase 5 task 9): a tab list of buttons, the active one
 * aria-selected, each controlling its section's table. assets/ui/showform.js shows it and switches the sections; without
 * the script every section stays shown and the tab list hidden.
 *
 * @param int $form the showform() call's number on the page
 * @param array<int, string> $sections section number => title
 * @param int $active the section shown first
 * @return string
 */
function showform_tabs($form, $sections, $active){
	if (!isset($sections[$active])) $active = (int)array_key_first($sections);
	$tabs = "<div class='rz-tabs' role='tablist' id='showFormSection$form' data-rz-tabs='$form' hidden>";
	foreach ($sections as $x=>$title) {
		$selected = $x == $active ? "true" : "false";
		$tabs .= "<button type='button' class='rz-tab-button' role='tab' id='showFormButton$x' aria-controls='showFormTable$x' aria-selected='$selected'"
			. ($x == $active ? "" : " tabindex='-1'") . " data-rz-tab='$x'>" . appoencode(full_sanitize($title)) . "</button>";
	}
	// The section to come back to after a save (showFormTabIndex) is added to the form by the script, as 1.1.2's script
	// added it: the form's fields are the same as before.
	return $tabs . "</div>";
}

function showform($layout,$row,$nosave=false,$keypref=false,$primary=false){
	global $session, $output;
 	static $showform_id=0;
 	static $title_id=0;
 	$showform_id++;
 	$formSections = array();
	$returnvalues = array();
	$extensions = array();
	$extensions = modulehook("showformextensions",$extensions);
	// The Resurrection template (Phase 5 task 9) gets tab buttons from the server and assets/ui/showform.js, no inline
	// script; a 1.1.2 skin keeps 1.1.2's script and markup.
	$rz = resurrection_ui();
	$tabsAt = 0;
	$tabbed = !(isset($session['user']['prefs']['tabconfig']) && $session['user']['prefs']['tabconfig'] == 0);
	$startIndex = (int)httppost("showFormTabIndex");
	if ($startIndex == 0) $startIndex = 1;
	rawoutput("<table width='100%' cellpadding='0' cellspacing='0'".($rz ? " class='rz-showform'" : "")."><tr><td>");
	if ($rz) {
		// The tab list goes here once the sections are known.
		$tabsAt = strlen((string)$output);
	} else {
		rawoutput("<div id='showFormSection$showform_id'></div>");
	}
	rawoutput("</td></tr><tr><td>&nbsp;</td></tr><tr><td>");
	rawoutput("<table cellpadding='2' cellspacing='0'".($rz ? " class='rz-form-section'" : "").">");
	$i = 0;
	while(list($key,$val)=resurrection_array_next($layout)){
		$pretrans = 0;
		if ($keypref !== false) $keyout = sprintf($keypref, $key);
		else $keyout = $key;
		if (is_array($val)) {
			$v = $val[0];
			//$info = split(",", $v);
			$info = explode(",", $v);
			$val[0] = $info[0];
			$info[0] = $val;
		} else {
			//$info = split(",",$val);
			$info = explode(",",$val);
		}
		if (is_array($info[0])) {
			$info[0] = call_user_func_array("sprintf_translate", $info[0]);
		} else {
			$info[0] = translate($info[0]);
		}
		if (isset($info[1])) $info[1] = trim($info[1]);
		else $info[1] = "";

		if ($info[1]=="title"){
		 	$title_id++;
		 	rawoutput("</table>");
		 	$formSections[$title_id] = $info[0];
			rawoutput("<table id='showFormTable$title_id' cellpadding='2' cellspacing='0'".($rz ? " class='rz-form-section'" : "").($rz && $tabbed ? " role='tabpanel' aria-labelledby='showFormButton$title_id'" : "").">");
			rawoutput("<tr><td colspan='2' class='trhead'>");
			output_notl("`b%s`b", $info[0], true);
			rawoutput("</td></tr>",true);
			$i=0;
		}elseif ($info[1]=="note"){
			rawoutput("<tr class='".($i%2?'trlight':'trdark')."'><td colspan='2'>");
			output_notl("`i%s`i", $info[0], true);
			$i++;
		}elseif($info[1]=="invisible"){
			// Don't show
		}else{
			if (isset($row[$key]))
				$returnvalues[$key] = $row[$key];
			// One control: its label is a <label for>. A group (checkboxes, radios) labels each choice instead.
			$single = !in_array($info[1], array("checklist", "checkpretrans", "radio", "radiopretrans", "bitfield", "bitfieldpretrans", "hidden", "viewonly"), true)
				&& !array_key_exists($info[1], $extensions);
			$id = $info[1] == "textarearesizeable" ? "textarea$key" : showform_control_id($showform_id, $keyout);
			rawoutput("<tr class='".($i%2?'trlight':'trdark')."'><td valign='top'>".($single ? "<label for='$id'>" : ""));
			output_notl("%s", $info[0],true);
			rawoutput(($single ? "</label>" : "")."</td><td valign='top'>");
			$i++;
		}
		switch ($info[1]){
		case "title":
		case "note":
		case "invisible":
			break;
		case "theme":
			// A generic way of allowing a theme to be selected.
			$skins = array();
		    $handle = @opendir("templates");
			// Template directory open failed
			if (!$handle) {
				output("None available");
				break;
			}
			while (false != ($file = @readdir($handle))) {
				if (strpos($file,".htm") > 0) {
					array_push($skins, $file);
				}
			}
			// No templates installed!
			if (count($skins) == 0) {
				output("None available");
				break;
			}
			natcasesort($skins); //sort them in natural order
			rawoutput("<select name='$keyout' id='$id'>");
			foreach($skins as $skin) {
				if ($skin == $row[$key]) {
					rawoutput("<option value='$skin' selected>".htmlentities(substr($skin, 0, strpos($skin, ".htm")), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
				} else {
					rawoutput("<option value='$skin'>".htmlentities(substr($skin, 0, strpos($skin, ".htm")), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
				}
			}
			rawoutput("</select>");
			break;
		case "location":
			// A generic way of allowing the location to be specified for
			// things which only want to be in one place.  There are other
			// things which would be good to do as well of course, such
			// as making sure to handle village name changes in the module
			// that cares about this or what not, but this at least gives
			// some support.
			$vloc = array();
			$vname = getsetting("villagename", LOCATION_FIELDS);
			$vloc[$vname]="village";
			$vloc['all'] = 1;
			$vloc = modulehook("validlocation", $vloc);
			unset($vloc['all']);
			reset($vloc);
			rawoutput("<select name='$keyout' id='$id'>");
			foreach($vloc as $loc=>$val) {
				if ($loc == $row[$key]) {
					rawoutput("<option value='$loc' selected>".htmlentities($loc, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
				} else {
					rawoutput("<option value='$loc'>".htmlentities($loc, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
				}

			}
			rawoutput("</select>");
			break;
		case "checkpretrans":
			$pretrans = 1;
			// FALLTHROUGH
		case "checklist":
			reset($info);
			list($k,$v)=resurrection_array_next($info);
			list($k,$v)=resurrection_array_next($info);
			$select="";
			while (list($k,$v)=resurrection_array_next($info)){
				$optval = $v;
				list($k,$v)=resurrection_array_next($info);
				$optdis = $v;
				if (!$pretrans) $optdis = translate_inline($optdis);
				if (is_array($row[$key])){
					if ($row[$key][$optval]) {
						$checked=true;
					}else{
						$checked=false;
					}
				}else{
					//any other ways to represent this?
					debug("You must pass an array as the value when using a checklist.");
					$checked=false;
				}
				$select.="<label><input type='checkbox' name='{$keyout}[{$optval}]' value='1'".($checked==$optval?" checked":"").">&nbsp;".("$optdis")."</label><br>";
			}
			rawoutput($select);
			break;
		case "radiopretrans":
			$pretrans = 1;
			// FALLTHROUGH
		case "radio":
			reset($info);
			list($k,$v)=resurrection_array_next($info);
			list($k,$v)=resurrection_array_next($info);
			$select="";
			while (list($k,$v)=resurrection_array_next($info)){
				$optval = $v;
				list($k,$v)=resurrection_array_next($info);
				$optdis = $v;
				if (!$pretrans) $optdis = translate_inline($optdis);
				$select.=("<label><input type='radio' name='$keyout' value='$optval'".($row[$key]==$optval?" checked":"").">&nbsp;".("$optdis")."</label><br>");
			}
			rawoutput($select);
			break;
		case "dayrange":
			$start = strtotime(date("Y-m-d", strtotime("now")));
			$end = strtotime($info[2]);
			$step = $info[3];
			// we should really try to avoid an infinite loop here if
			// they define a time string which equates to 0 :/
			$cur = $row[$key];
			rawoutput("<select name='$keyout' id='$id'>");
			if ($cur && $cur < date("Y-m-d H:i:s", $start))
				rawoutput("<option value='$cur' selected>".htmlentities($cur, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			for($j = $start; $j < $end; $j = strtotime($step, $j)) {
				$d = date("Y-m-d H:i:s", $j);
				rawoutput("<option value='$d'".($cur==$d?" selected":"").">".HTMLEntities("$d", ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			}
			if ($cur && $cur > date("Y-m-d H:i:s", $end))
				rawoutput("<option value='$cur' selected>".htmlentities($cur, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			rawoutput("</select>");
			break;

		case "range":
			$min = (int)$info[2];
			$max = (int)$info[3];
			$step = (int)(isset($info[4])?$info[4]:false);
			if ($step == 0) $step = 1;
			rawoutput("<select name='$keyout' id='$id'>");
			if ($min<$max && ($max-$min)/$step>300)
				$step=max(1,(int)(($max-$min)/300));
			for($j = $min; $j <= $max; $j += $step) {
				rawoutput("<option value='$j'".($row[$key]==$j?" selected":"").">".HTMLEntities("$j", ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			}
			rawoutput("</select>");
			break;
		case "floatrange":
			$min = round((float)$info[2],2);
			$max = round((float)$info[3],2);
			$step = round((float)$info[4],2);
			if ($step==0) $step=1;
			rawoutput("<select name='$keyout' id='$id'>", true);
			$val = round((float)$row[$key], 2);
			for($j = $min; $j <= $max; $j = round($j+$step,2)) {
				rawoutput("<option value='$j'".($val==$j?" selected":"").">".HTMLEntities("$j", ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>", true);
			}
			rawoutput("</select>", true);
			break;
		case "bitfieldpretrans":
			$pretrans = 1;
			// FALLTHROUGH
		case "bitfield":
			//format:
			//DisplayName,bitfield,disablemask,(highbit,display)+
			//1-26-03 added disablemask so this field type can be used
			// on bitfields other than superuser.
			reset($info);
			list($k,$v)=resurrection_array_next($info);
			list($k,$v)=resurrection_array_next($info);
			list($k,$disablemask)=resurrection_array_next($info);
			rawoutput("<input type='hidden' name='$keyout"."[0]' value='1'>", true);
			while (list($k,$v)=resurrection_array_next($info)){
				rawoutput("<label><input type='checkbox' name='$keyout"."[$v]'"
					.(isset($row[$key]) && (int)$row[$key] & (int)$v?" checked":"")
					.($disablemask & (int)$v?"":" disabled")
					." value='1'> ");
				list($k,$v)=resurrection_array_next($info);
				if (!$pretrans) $v = translate_inline($v);
				output_notl("%s",$v,true);
				rawoutput("</label>");
				output_notl("`n");
			}
			break;
		case "datelength":
			// However, there was a bug with your translation code wiping
			// the key name for the actual form.  It's now fixed.
			// ok, I see that, but 24 hours and 1 day are the same
			// aren't they?
			$vals = array(
				"1 hour", "2 hours", "3 hours", "4 hours",
				"5 hours", "6 hours", "8 hours", "10 hours",
				"12 hours", "16 hours", "18 hours", "24 hours",
				"1 day", "2 days", "3 days", "4 days", "5 days",
				"6 days", "7 days",
				"1 week", "2 weeks", "3 weeks", "4 weeks",
				"1 month", "2 months", "3 months", "4 months",
				"6 months", "9 months", "12 months",
				"1 year"
			);
			tlschema("showform");
			while (list($k,$v)=resurrection_array_next($vals)){
				$vals[$k]=translate($v);
				rawoutput(tlbutton_pop());
			}
			tlschema();
			reset($vals);
			rawoutput("<select name='$keyout' id='$id'>");
			while(list($k,$v)=resurrection_array_next($vals)) {
				rawoutput("<option value=\"".htmlentities($v, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\"".($row[$key]==$v?" selected":"").">".htmlentities($v, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			}
			rawoutput("</select>");
			break;
		case "enumpretrans":
			$pretrans = 1;
		    // FALLTHROUGH
		case "enum":
			reset($info);
			list($k,$v)=resurrection_array_next($info);
			list($k,$v)=resurrection_array_next($info);
			$select="";
			$select.=("<select name='$keyout' id='$id'>");
			while (list($k,$v)=resurrection_array_next($info)){
				$optval = $v;
				list($k,$v)=resurrection_array_next($info);
				$optdis = $v;
				if (!$pretrans) {
					$optdis = translate_inline($optdis);
				}
				$selected = 0;
				if (isset($row[$key]) && $row[$key] == $optval)
					$selected = 1;

				$select.=("<option value='$optval'".($selected?" selected":"").">".HTMLEntities("$optdis", ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</option>");
			}
			$select.="</select>";
			rawoutput($select);
			break;
		case "password":
			if (array_key_exists($key, $row)) $out = $row[$key];
			else $out = "";
			rawoutput("<input type='password' name='$keyout' id='$id' value='".HTMLEntities($out, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."'>");
			break;
		case "bool":
			tlschema("showform");
			$yes = translate_inline("Yes");
			$no = translate_inline("No");
			tlschema();
			// A missing value (a new account's preferences) read as null in 1.1.2, under its notice-free
			// error level: "No" is selected. PHP 8 warns, and the error handler makes warnings fatal.
			$current = $row[$key] ?? null;
			rawoutput("<select name='$keyout' id='$id'>");
			rawoutput("<option value='0'".($current==0?" selected":"").">$no</option>");
			rawoutput("<option value='1'".($current==1?" selected":"").">$yes</option>");
			rawoutput("</select>", true);
			break;
		case "hidden":
			if(isset($row[$key])) rawoutput("<input type='hidden' name='$keyout' value=\"".HTMLEntities($row[$key], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">".HTMLEntities($row[$key], ENT_COMPAT, getsetting("charset", "ISO-8859-1")));
			break;
		case "viewonly":
			unset($returnvalues[$key]);
			if (isset($row[$key]))
				output_notl(dump_item($row[$key], true),true);
			break;
		case "textarearesizeable":
			$resize=true;
			//FALLTHROUGH
		case "textarea":
			$cols = 0;
			if (isset($info[2])) $cols = $info[2];
		    if (!$cols) $cols = 70;
			$text = "";
			if (isset($row[$key])) {
				$text = $row[$key];
			}
			if (isset($resize) && $resize) {
				if (!$rz) rawoutput("<script type=\"text/javascript\">function increase(target, value){  if (target.rows + value > 3 && target.rows + value < 50) target.rows = target.rows + value;}</script>");
				rawoutput("<textarea id='textarea$key' class='input' name='$keyout' cols='$cols' rows='5'>".htmlentities(str_replace("`n", "\n", $text), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea>");
				if ($rz) {
					// The same buttons with no inline handler: assets/ui/showform.js resizes the box.
					rawoutput("<input type='button' class='button' data-rz-resize='textarea$key' data-rz-step='1' value='+' accesskey='+'><input type='button' class='button' data-rz-resize='textarea$key' data-rz-step='-1' value='-' accesskey='-'>");
				} else {
					rawoutput("<input type='button' onClick=\"increase(textarea$key,1);\" value='+' accesskey='+'><input type='button' onClick=\"increase(textarea$key,-1);\" value='-' accesskey='-'>");
				}
			} else {
				rawoutput("<textarea class='input' name='$keyout' id='$id' cols='$cols' rows='5'>".htmlentities(str_replace("`n", "\n", $text), ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."</textarea>");
			}
			break;
		case "int":
			if (array_key_exists($key, $row)) $out = $row[$key];
			else $out = 0;
			rawoutput("<input name='$keyout' id='$id' value=\"".HTMLEntities($out, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" size='5'>");
			break;
		case "float":
			rawoutput("<input name='$keyout' id='$id' value=\"".htmlentities($row[$key], ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\" size='8'>");
			break;
		case "string":
			$len = 50;
			$minlen = 50;
			if (isset($info[2])) $len = (int)$info[2];
			if ($len < $minlen) $minlen = $len;
			if ($len > $minlen) $minlen = $len/2;
			if ($minlen > 70) $minlen = 70;
			if (array_key_exists($key, $row)) $val = $row[$key];
			else $val = "";
			rawoutput("<input size='$minlen' maxlength='$len' name='$keyout' id='$id' value=\"".HTMLEntities($val, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
			break;
		default:
			if (array_key_exists($info[1],$extensions)){
				$func=$extensions[$info[1]];
				if (array_key_exists($key, $row)) $val = $row[$key];
				else $val = "";
				call_user_func($func, $keyout, $val, $info);
			}else{
				if (array_key_exists($key, $row)) $val = $row[$key];
				else $val = "";
				rawoutput("<input size='50' name='$keyout' id='$id' value=\"".HTMLEntities($val, ENT_COMPAT, getsetting("charset", "ISO-8859-1"))."\">");
			}
		}
		rawoutput("</td></tr>",true);
	}
	rawoutput("</table><br>",true);
	if ($rz) {
		$sections = array();
		foreach ($formSections as $x=>$title) $sections[(int)$x] = (string)$title;
		if ($tabbed && $sections !== array()) {
			$output = substr_replace((string)$output, showform_tabs($showform_id, $sections, $startIndex), $tabsAt, 0);
		}
		rawoutput(resurrection_form_script());
	} elseif ($showform_id==1){
		if (isset($session['user']['prefs']['tabconfig']) &&
				$session['user']['prefs']['tabconfig'] == 0) {
		} else {
		 	rawoutput("
		 	<script language='JavaScript'>
		 	function prepare_form(id){
		 		var theTable;
		 		var theDivs='';
		 		var x=0;
		 		var weight='';
		 		for (x in formSections[id]){
		 			theTable = document.getElementById('showFormTable'+x);
		 			if (x != $startIndex ){
			 			theTable.style.visibility='hidden';
			 			theTable.style.display='none';
			 			weight='';
			 		}else{
			 			theTable.style.visibility='visible';
			 			theTable.style.display='inline';
			 			weight='color: yellow;';
			 		}
			 		theDivs += \"<div id='showFormButton\"+x+\"' class='trhead' style='\"+weight+\"float: left; cursor: pointer; cursor: hand; padding: 5px; border: 1px solid #000000;' onClick='showFormTabClick(\"+id+\",\"+x+\");'>\"+formSections[id][x]+\"</div>\";
		 		}
		 		theDivs += \"<div style='display: block;'>&nbsp;</div>\";
				theDivs += \"<input type='hidden' name='showFormTabIndex' value='$startIndex' id='showFormTabIndex'>\";
		 		document.getElementById('showFormSection'+id).innerHTML = theDivs;
		 	}
		 	function showFormTabClick(formid,sectionid){
		 		var theTable;
		 		var theButton;
		 		for (x in formSections[formid]){
		 			theTable = document.getElementById('showFormTable'+x);
		 			theButton = document.getElementById('showFormButton'+x);
		 			if (x == sectionid){
		 				theTable.style.visibility='visible';
		 				theTable.style.display='inline';
		 				theButton.style.fontWeight='normal';
		 				theButton.style.color='yellow';
						document.getElementById('showFormTabIndex').value = sectionid;
		 			}else{
		 				theTable.style.visibility='hidden';
		 				theTable.style.display='none';
		 				theButton.style.fontWeight='normal';
		 				theButton.style.color='';
		 			}
		 		}
		 	}
		 	formSections = new Array();
			</script>");
		}
	}
	if ($rz || (isset($session['user']['prefs']['tabconfig']) &&
			$session['user']['prefs']['tabconfig'] == 0)) {
	} else {
		rawoutput("<script language='JavaScript'>");
		rawoutput("formSections[$showform_id] = new Array();");
		reset($formSections);
		while (list($key,$val)=resurrection_array_next($formSections)){
			rawoutput("formSections[$showform_id][$key] = '".addslashes($val)."';");
		}
		rawoutput("
		prepare_form($showform_id);
		</script>");
	}
	rawoutput("</td></tr></table>");
	tlschema("showform");
	$save = translate_inline("Save");
	tlschema();
	if ($nosave) {}
	else rawoutput("<input type='submit' class='".resurrection_button_class($primary)."' value='$save'>");
	return $returnvalues;
}
?>
