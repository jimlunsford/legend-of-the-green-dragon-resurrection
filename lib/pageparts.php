<?php
require_once __DIR__ . '/../src/Compatibility/array_cursor.php';
/**
 * Library (supporting) functions for page output
 *		addnews ready
 *		translator ready
 *		mail ready
 *
 * @author core_module
 * @package defaultPackage
 *
 */

$nopopups = array();
$runheaders = array();
/**
 * Starts page output.  Inits the template and translator modules.
 *
 * @param array|string $title
 * Hooks provided:
 *		everyheader
 *		header-{scriptname}
 */
function page_header(){
	global $header,$SCRIPT_NAME,$session,$template, $runheaders, $nopopups;
	$nopopups["login.php"]=1;
	$nopopups["motd.php"]=1;
	$nopopups["index.php"]=1;
	$nopopups["create.php"]=1;
	$nopopups["about.php"]=1;
	$nopopups["mail.php"]=1;

	//in case this didn't already get called (such as on a database error)
	translator_setup();
	prepare_template();
	$script = substr($SCRIPT_NAME,0,strrpos($SCRIPT_NAME,"."));
	if ($script) {
		if (!array_key_exists($script,$runheaders))
			$runheaders[$script] = false;
		if (!$runheaders[$script]) {
			modulehook("everyheader", array('script'=>$script));
			if ($session['user']['loggedin']) {
				modulehook("everyheader-loggedin", array('script'=>$script));
			}
			$runheaders[$script] = true;
			// Torment owns its gameplay header hook inside the protected mutation.
            if ($script!=='graveyard' || (!defined('RESURRECTION_GRAVEYARD_COMBAT') && !defined('RESURRECTION_RAMIUS_PRESENTATION'))) modulehook("header-$script");
		}
	}

	$arguments = func_get_args();
	if (!$arguments || count($arguments) == 0) {
		$arguments = array("Legend of the Green Dragon");
	}
	$title = call_user_func_array("sprintf_translate", $arguments);
	$title = holidayize($title,'title');
	$title = sanitize($title);
	calculate_buff_fields();

	$header = $template['header'];
	$header=str_replace("{title}",htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8"),$header);
	$header.=tlbutton_pop();
}

/**
 * Returns an output formatted popup link based on JavaScript
 *
 * @param string $page The URL to open
 * @param string $size The size of the popup window (Default: 550x300)
 * @return string
 */
function popup($page,$size="550x300"){
	//$s = split("x",$size);
	$s = explode("x",$size);
  return "window.open('$page','".preg_replace("([^[:alnum:]])","",$page)."','scrollbars=yes,resizable=yes,width={$s[0]},height={$s[1]}').focus()";
}

/**
 * Brings all the output elements together and terminates the rendering of the page.  Saves the current user info and updates the rendering statistics
 * Hooks provided:
 *	footer-{$script name}
 *	everyfooter
 *
 */
function page_footer($saveuser=true){
	global $output,$nestedtags,$header,$nav,$session,$REMOTE_ADDR,
		$REQUEST_URI,$pagestarttime,$quickkeys,$template,$y2,$z2,
		$logd_version,$copyright,$SCRIPT_NAME, $footer,
		$dbinfo;
	$z = $y2^$z2;
	$footer = $template['footer'];
	//page footer module hooks
	$script = substr($SCRIPT_NAME,0,strpos($SCRIPT_NAME,"."));
	$replacementbits = array();
	$replacementbits = modulehook("footer-$script",$replacementbits);
	if ($script == "runmodule" && (($module = httpget('module'))) > "") {
		// This modulehook allows you to hook directly into any module without
		// the need to hook into footer-runmodule and then checking for the
		// required module.
		modulehook("footer-$module",$replacementbits);
	}
	// Pass the script file down into the footer so we can do something if
	// we need to on certain pages (much like we do on the header.
	// Problem is 'script' is a valid replacement token, so.. use an
	// invalid one which we can then blow away.
	$replacementbits['__scriptfile__'] = $script;
	$replacementbits = modulehook("everyfooter",$replacementbits);
	if ($session['user']['loggedin']) {
		$replacementbits = modulehook("everyfooter-loggedin", $replacementbits);
	}
	unset($replacementbits['__scriptfile__']);
	//output any template part replacements that above hooks need (eg,
	//advertising)
	reset($replacementbits);
	while (list($key,$val)=resurrection_array_next($replacementbits)){
		$header = str_replace("{".$key."}","{".$key."}".join("",$val),$header);
		$footer = str_replace("{".$key."}","{".$key."}".join("",$val),$footer);
	}

	$builtnavs = buildnavs();

	restore_buff_fields();
	calculate_buff_fields();

	tlschema("common");

	$charstats = charstats();

	restore_buff_fields();

	$sql = "SELECT motddate FROM " . db_prefix("motd") . " ORDER BY motditem DESC LIMIT 1";
	$result = db_query($sql);
	$row = db_fetch_assoc($result);
	db_free_result($result);
	$headscript = "";
	// 1.1.2 opened an unread MoTD in a popup window on every page load (from page_footer()). Popup blockers stop that,
	// and a phone has no popups: an unread MoTD now marks the header's MoTD link instead, on every page (Phase 5 task 4).
	if ($row && isset($session['user']['lastmotd']) &&
			($row['motddate']>$session['user']['lastmotd']) &&
			$session['user']['loggedin']){
		$session['needtoviewmotd']=true;
	}else{
		$session['needtoviewmotd']=false;
	}
	$pre_headscript = "<LINK REL=\"shortcut icon\" HREF=\"favicon.ico\" TYPE=\"image/x-icon\"/>";
	if ($headscript>""){
		$header=str_replace("{headscript}",$pre_headscript."<script language='JavaScript'>".$headscript."</script>",$header);
	}else{
		$header = str_replace("{headscript}",$pre_headscript,$header);
	}

	$script = "";

	if (!isset($session['user']['name'])) $session['user']['name']="";
	if (!isset($session['user']['login'])) $session['user']['login']="";

	//clean up unclosed output tags.
	while (list($key,$val)=resurrection_array_next($nestedtags)){
		if ($nestedtags[$key] === true) $output.="</$key>";

		unset($nestedtags[$key]);
	}
	//output keypress script
	$script.="<script language='JavaScript'>
	<!--
	document.onkeypress=keyevent;
	function keyevent(e){
		var c;
		var target;
		var altKey;
		var ctrlKey;
		if (window.event != null) {
			c=String.fromCharCode(window.event.keyCode).toUpperCase();
			altKey=window.event.altKey;
			ctrlKey=window.event.ctrlKey;
		}else{
			c=String.fromCharCode(e.charCode).toUpperCase();
			altKey=e.altKey;
			ctrlKey=e.ctrlKey;
		}
		if (window.event != null)
			target=window.event.srcElement;
		else
			target=e.originalTarget;
		if (target.nodeName.toUpperCase()=='INPUT' || target.nodeName.toUpperCase()=='TEXTAREA' || altKey || ctrlKey){
		}else{";
	reset($quickkeys);
	while (list($key,$val)=resurrection_array_next($quickkeys)){
		$script.="\n			if (c == '".strtoupper($key)."') { $val; return false; }";
	}
	$script.="
		}
	}
	//-->
	</script>";

	// 1.1.2's PayPal buttons and LoGDnet registration are gone (payments and LoGDnet are disabled). The skins
	// still carry {paypal}, so it is emptied.
	$footer = str_replace('{paypal}', '', $footer);
	$header = str_replace('{paypal}', '', $header);

	//output the nav
	$footer = str_replace("{".($z)."}",$$z,$footer);
	$header=str_replace("{nav}",$builtnavs,$header);
	$footer=str_replace("{nav}",$builtnavs,$footer);
	//output the motd

	$header = str_replace("{motd}", motdlink(), $header);
	$footer = str_replace("{motd}", motdlink(), $footer);
	//output the mail link
	if (isset($session['user']['acctid']) && $session['user']['acctid']>0 && $session['user']['loggedin']) {
		$header=str_replace("{mail}",maillink(),$header);
		$footer=str_replace("{mail}",maillink(),$footer);
	}else{
		$header=str_replace("{mail}","",$header);
		$footer=str_replace("{mail}","",$footer);
	}
	//output petition count

	$petitionlink = petitionlink();
	$header=str_replace("{petition}",$petitionlink,$header);
	$footer=str_replace("{petition}",$petitionlink,$footer);
	if ($session['user']['superuser'] & SU_EDIT_PETITIONS){
		$sql = "SELECT count(petitionid) AS c,status FROM " . db_prefix("petitions") . " GROUP BY status";
		$result = db_query_cached($sql,"petition_counts");
		$petitions=array(0=>0,1=>0,2=>0,3=>0,4=>0,5=>0,6=>0,7=>0);
		while ($row = db_fetch_assoc($result)) {
			$petitions[(int)$row['status']] = $row['c'];
		}
		$pet = translate_inline("`0`bPetitions:`b");
		$ued = translate_inline("`0`bUser Editor`b");
		db_free_result($result);
		if ($session['user']['superuser'] & SU_EDIT_USERS){
			$p = "<a href='user.php'>$ued</a>|<a href='viewpetition.php'>$pet</a>";
			addnav("", "user.php");
			addnav("", "viewpetition.php");
		} else {
			$p = "<a href='viewpetition.php'>$pet</a>";
			addnav("", "viewpetition.php");
		}
		$p .= " `\${$petitions[5]}`0|`^{$petitions[4]}`0|`b{$petitions[0]}`b|{$petitions[1]}|`!{$petitions[3]}`0|`#{$petitions[7]}`0|`%{$petitions[6]}`0|`i{$petitions[2]}`i";
		$pcount = templatereplace("petitioncount", array("petitioncount"=>appoencode($p, true)));
		$footer = str_replace("{petitiondisplay}", $pcount, $footer);
		$header = str_replace("{petitiondisplay}", $pcount, $header);
	} else {
		$footer = str_replace("{petitiondisplay}", "", $footer);
		$header = str_replace("{petitiondisplay}", "", $header);
	}
	//output character stats
	$footer=str_replace("{stats}",$charstats,$footer);
	$header=str_replace("{stats}",$charstats,$header);
	//do something -- I don't know what
	$header=str_replace("{script}",$script,$header);
	//output view PHP source link
	// 1.1.2 linked its source viewer, source.php, in a popup; it now links the public source (lib/source_link.php).
	require_once("lib/source_link.php");
	$sourcelink = resurrection_source_link();
	$footer=str_replace("{source}",$sourcelink,$footer);
	$header=str_replace("{source}",$sourcelink,$header);
	//output version
	// 1.1.2 showed "Version: $logd_version" here. Resurrection shows its own version and credits that base
	// (src/Version.php; $logd_version stays the base, as the about page shows it).
	require_once __DIR__ . '/../src/Version.php';
	$footer=str_replace("{version}", "Version: " . \Resurrection\Version::display(), $footer);
	// The footer's Credits link, only where about.php is a nav the page allows, and the time to the next new day
	// (Phase 5 task 4).
	$footer=str_replace("{credits}", creditslink(), $footer);
	$footer=str_replace("{newday}", resurrection_newday_text(), $footer);
	//output page generation time
	$gentime = getmicrotime()-$pagestarttime;
	$session['user']['gentime']+=$gentime;
	$session['user']['gentimecount']++;
	$footer=str_replace("{pagegen}","Page gen: ".round($gentime,3)."s / ".$dbinfo['queriesthishit']." queries (".round($dbinfo['querytime'],3)."s), Ave: ".round($session['user']['gentime']/$session['user']['gentimecount'],3)."s - ".round($session['user']['gentime'],3)."/".round($session['user']['gentimecount'],3)."",$footer);

	tlschema();

	//clean up spare {fields}s from header and footer (in case they're not used)
	$footer = preg_replace("/{[^} \t\n\r]*}/i","",$footer);
	$header = preg_replace("/{[^} \t\n\r]*}/i","",$header);

	//finalize output
	$output=$header.$output.$footer;
	$session['user']['gensize']+=strlen($output);
	$session['output']=$output;
	if ($saveuser === true) {
		saveuser();
	}
	unset($session['output']);
	//this somehow allows some frames to load before the user's navs say it can
	//session_write_close();
	echo $output;
	exit();
}

/**
 * Page header for popup windows.
 *
 * @param string $title The title of the popup window
 */
function popup_header($title="Legend of the Green Dragon"){
	global $header, $template;

	translator_setup();
	prepare_template();

	modulehook("header-popup");

	$arguments = func_get_args();
	if (!$arguments || count($arguments) == 0) {
		$arguments = array("Legend of the Green Dragon");
	}
	$title = call_user_func_array("sprintf_translate", $arguments);
	$title = holidayize($title,'title');

	$header = $template['popuphead'];
	$header = str_replace("{title}", $title, $header);
}

/**
 * Ends page generation for popup windows.  Saves the user account info - doesn't update page generation stats
 *
 */
function popup_footer(){
	global $output,$nestedtags,$header,$session,$y2,$z2,$copyright, $template, $baseaccount;

	while (list($key,$val)=resurrection_array_next($nestedtags)){
		if ($nestedtags[$key] === true) $output.="</$key>";
		unset($nestedtags[$key]);
	}

	$footer = $template['popupfoot'];

	// Pass the script file down into the footer so we can do something if
	// we need to on certain pages (much like we do on the header.
	// Problem is 'script' is a valid replacement token, so.. use an
	// invalid one which we can then blow away.
	$replacementbits = modulehook("footer-popup",array());
	//output any template part replacements that above hooks need
	reset($replacementbits);
	while (list($key,$val)=resurrection_array_next($replacementbits)){
		$header = str_replace("{".$key."}","{".$key."}".join("",$val),$header);
		$footer = str_replace("{".$key."}","{".$key."}".join("",$val),$footer);
	}

	$z = $y2^$z2;
	$footer = str_replace("{".($z)."}",$$z, $footer);

	//clean up spare {fields}s from header and footer (in case they're not used)
	$footer = preg_replace("/{[^} \t\n\r]*}/i","",$footer);
	$header = preg_replace("/{[^} \t\n\r]*}/i","",$header);

	$output=$header.$output.$footer;
	// A popup page (mail.php, motd.php, petition.php, translatortool.php) runs outside forced navigation and needs no
	// navs of its own, so it keeps the navs the game page allowed exactly as they were: opening one from the header
	// never changes what the game page allows. 1.1.2 saved the navs a popup added (the MoTD commentary's refresh
	// links) into the game page's allowed navs (Phase 5 task 4).
	if (OVERRIDE_FORCED_NAV && isset($baseaccount['allowednavs'])) {
		$session['allowednavs'] = \Resurrection\Security\NavigationState::read($baseaccount['allowednavs']);
	}
	saveuser();
	session_write_close();
	echo $output;
	exit();
}

$charstat_info = array();
$last_charstat_label = "";
/**
 * Resets the character stats array
 *
 */
function wipe_charstats(){
	global $charstat_info, $last_charstat_label;
	$last_charstat_label = "";
	$charstat_info = array();
}

/**
 * Add a attribute and/or value to the character stats display
 *
 * @param string $label The label to use
 * @param mixed $value (optional) value to display
 */
function addcharstat($label, $value=false) {
	global $charstat_info, $last_charstat_label;
	if ($value === false) {
		if (!isset($charstat_info[$label]))
			$charstat_info[$label] = array();
		$last_charstat_label=$label;
	} else {
		if ($last_charstat_label=="") {
			$last_charstat_label = "Other Info";
			addcharstat($last_charstat_label);
		}
		$charstat_info[$last_charstat_label][$label]=$value;
	}
}

/**
 * Returns the character stat related to the category ($cat) and the label
 *
 * @param string $cat The relavent category for the stat
 * @param string $label The label of the character stat
 * @return mixed The value associated with the stat
 */
function getcharstat($cat, $label) {
	global $charstat_info;
	return $charstat_info[$cat][$label];
}

/**
 * Sets a value to the passed category & label for character stats
 *
 * @param string $cat The category for the char stat
 * @param string $label The label associated with the value
 * @param mixed $val The value of the attribute
 */
function setcharstat($cat, $label, $val) {
	global $charstat_info, $last_charstat_label;
	if (!isset($charstat_info[$cat][$label])) {
		$oldlabel = $last_charstat_label;
		addcharstat($cat);
		addcharstat($label, $val);
		$last_charstat_label = $oldlabel;
	} else {
		$charstat_info[$cat][$label] = $val;
	}
}

/**
 * Returns output formatted character stats
 *
 * @param array $buffs
 * @return string
 */
function getcharstats($buffs){
	//returns output formatted character statistics.
	global $charstat_info;
	$charstat_str = templatereplace("statstart");
	reset($charstat_info);
	foreach ($charstat_info as $label=>$section) {
		if (count($section)) {
			$arr = array("title"=>translate_inline($label));
			$sectionhead = templatereplace("stathead", $arr);
			reset($section);
			foreach ($section as $name=>$val) {
				if ($name==$label){
					// when the section and stat name are equal, use
					// 'statbuff' template piece.
					$a2 = array("title"=>translate_inline("`0$name"),
							"value"=>"`^$val`0");
					$charstat_str .= templatereplace("statbuff", $a2);
				}else{
					$a2 = array("title"=>translate_inline("`&$name`0"),
							"value"=>"`^$val`0");
					$charstat_str .= $sectionhead.templatereplace("statrow", $a2);
					$sectionhead = "";
				}
			}
		}
	}
	$charstat_str .= templatereplace("statbuff",
			array("title"=>translate_inline("`0Buffs"),"value"=>$buffs));
	$charstat_str .= templatereplace("statend");
	return appoencode($charstat_str,true);
}

/**
 * Returns the value associated with the section & label.  Returns an empty string if the stat isn't set
 *
 * @param string $section The character stat section
 * @param string $title The stat display label
 * @return mixed The value associated with the stat
 */
function getcharstat_value($section,$title){
	global $charstat_info;
	if (isset($charstat_info[$section][$title])){
		return $charstat_info[$section][$title];
	}else{
		return "";
	}
}

/**
 * Returns the current character stats or (if the character isn't logged in) the currently online players
 * Hooks provided:
 *		charstats
 *
 * @return array The current stats for this character or the list of online players
 */
function charstats(){
	global $session, $playermount, $companions;

	wipe_charstats();

	$u =& $session['user'];

	if ($session['loggedin']){
		$u['hitpoints']=round($u['hitpoints'],0);
		$u['experience']=round($u['experience'],0);
		$u['maxhitpoints']=round($u['maxhitpoints'],0);
		$spirits=array(-6=>"Resurrected",-2=>"Very Low",-1=>"Low","0"=>"Normal",1=>"High",2=>"Very High");
		if ($u['alive']){ }else{ $spirits[(int)$u['spirits']] = "DEAD"; }
		//calculate_buff_fields();
		reset($session['bufflist']);
		$atk=$u['attack'];
		$def=$u['defense'];
		$buffcount = 0;
		$buffs = "";
		while (list($key,$val)=resurrection_array_next($session['bufflist'])){
			if (isset($val['suspended']) && $val['suspended']) continue;
			if (isset($val['atkmod'])) {
				$atk *= $val['atkmod'];
			}
			if (isset($val['defmod'])) {
				$def *= $val['defmod'];
			}
			// Short circuit if the name is blank
			if ($val['name'] > "" || $session['user']['superuser'] & SU_DEBUG_OUTPUT){
				tlschema($val['schema']);
				if ($val['name']=="")
					$val['name'] = "DEBUG: {$key}";
				if (is_array($val['name'])) {
					$val['name'][0] = str_replace("`%","`%%",$val['name'][0]);
					$val['name']=call_user_func_array("sprintf_translate", $val['name']);
				} else { //in case it's a string
					$val['name']=translate_inline($val['name']);
				}
				if ($val['rounds']>=0){
					// We're about to sprintf, so, let's makes sure that
					// `% is handled.
					//$n = translate_inline(str_replace("`%","`%%",$val['name']));
					$b = translate_inline("`#%s `7(%s rounds left)`n","buffs");
					$b = sprintf($b, $val['name'], $val['rounds']);
					$buffs.=appoencode($b, true);
				}else{
					$buffs.= appoencode("`#{$val['name']}`n",true);
				}
				tlschema();
				$buffcount++;
			}
		}
		if ($buffcount==0){
			$buffs.=appoencode(translate_inline("`^None`0"),true);
		}

		$atk = round($atk, 2);
		$def = round($def, 2);
		if ($atk < $u['attack']){
			$atk = round($u['attack'],1)."`\$".round($atk-$u['attack'],1);
		}else if($atk > $u['attack']){
			$atk = round($u['attack'],1)."`@+".round($atk-$u['attack'],1);
		} else {
			// They are equal, display in the 1 signifigant digit format.
			$atk = round($atk,1);
		}
		if ($def < $u['defense']){
			$def = round($u['defense'],1)."`\$".round($def-$u['defense'],1);
		}else if($def > $u['defense']){
			$def = round($u['defense'],1)."`@+".round($def-$u['defense'],1);
		} else {
			// They are equal, display in the 1 signifigant digit format.
			$def = round($def, 1);
		}

		addcharstat("Vital Info");
		addcharstat("Name", $u['name']);
		addcharstat("Level", "`b".$u['level'].check_temp_stat("level",1)."`b");
		if ($u['alive']) {
			addcharstat("Hitpoints", $u['hitpoints'].check_temp_stat("hitpoints",1).
									"`0/".$u['maxhitpoints'].check_temp_stat("maxhitpoints",1));
			addcharstat("Turns", $u['turns'].check_temp_stat("turns",1));
			addcharstat("Attack", $atk.check_temp_stat("attack",1));
			addcharstat("Defense", $def.check_temp_stat("defense",1));
		} else {
			$maxsoul = $u['level'] * 5 + 50;
			addcharstat("Soulpoints", $u['soulpoints'].check_temp_stat("soulpoints",1)."`0/".$maxsoul);
			addcharstat("Torments", $u['gravefights'].check_temp_stat("gravefights",1));
			addcharstat("Psyche", 10+round(($u['level']-1)*1.5));
			addcharstat("Spirit", 10+round(($u['level']-1)*1.5));
		}
		addcharstat("Spirits", translate_inline("`b".$spirits[(int)$u['spirits']]."`b"));
		if ($u['race'] != RACE_UNKNOWN) {
			addcharstat("Race", translate_inline($u['race'],"race"));
		}else {
			addcharstat("Race", translate_inline(RACE_UNKNOWN,"race"));
		}
		if (count($companions)>0) {
			addcharstat("Companions");
			foreach ($companions as $name=>$companion) {
				if ($companion['hitpoints'] > 0 ||(isset($companion['cannotdie']) && $companion['cannotdie'] == true)) {
					if ($companion['hitpoints']<0) {
						$companion['hitpoints'] = 0;
					}
					if($companion['hitpoints']<$companion['maxhitpoints']) {
						$color = "`\$";
					}else{
						$color = "`@";
					}
					if (isset($companion['suspended']) && $companion['suspended'] == true) {
						$suspcode = "`7 *";
					} else {
						$suspcode = "";
					}
					addcharstat($companion['name'], $color.($companion['hitpoints'])."`7/`&".($companion['maxhitpoints'])."$suspcode`0");
				}
			}
		}
		addcharstat("Personal Info");
		if ($u['alive']) {
			addcharstat("Gold", $u['gold'].check_temp_stat("gold",1));
		} else {
			addcharstat("Favor", $u['deathpower'].check_temp_stat("deathpower",1));
		}
		addcharstat("Gems", $u['gems'].check_temp_stat("gems",1));
		addcharstat("Experience", $u['experience'].check_temp_stat("experience",1));
		addcharstat("Equipment Info");
		addcharstat("Weapon", $u['weapon']);
		addcharstat("Armor", $u['armor']);
		if ($u['hashorse'])
			addcharstat("Creature", $playermount['mountname'] . "`0");

		modulehook("charstats");

		$charstat = getcharstats($buffs);

		if (!is_array($session['bufflist'])) $session['bufflist']=array();
		return $charstat;
	}else{
		$ret = "";
		if ($ret = datacache("charlisthomepage")){

		}else{
			$onlinecount=0;
			// If a module wants to do it's own display of the online chars,
			// let it.
			$list = modulehook("onlinecharlist", array());
			if (isset($list['handled']) && $list['handled']) {
				$onlinecount = $list['count'];
				$ret = $list['list'];
			} else {
				$sql="SELECT name,alive,location,sex,level,laston,loggedin,lastip,uniqueid FROM " . db_prefix("accounts") . " WHERE locked=0 AND loggedin=1 AND laston>'".date("Y-m-d H:i:s",strtotime("-".getsetting("LOGINTIMEOUT",900)." seconds"))."' ORDER BY level DESC";
				$result = db_query($sql);
				$ret.=appoencode(sprintf(translate_inline("`bOnline Characters (%s players):`b`n"),db_num_rows($result)));
				while ($row = db_fetch_assoc($result)) {
					$ret.=appoencode("`^{$row['name']}`n");
					$onlinecount++;
				}
				db_free_result($result);
				if ($onlinecount==0)
					$ret.=appoencode(translate_inline("`iNone`i"));
			}
			savesetting("OnlineCount",$onlinecount);
			savesetting("OnlineCountLast",strtotime("now"));
			updatedatacache("charlisthomepage",$ret);
		}
		return $ret;
	}
}
/**
 * Loads the template into the current session.  If the template doesn't
 * exist - uses the default (admin-defined) template, and then falls back
 * to resurrection.htm (1.1.2: jade.htm)
 *
 * @param string $templatename The template name (minus the path)
 * @return array The template split into the sections defined by <!--!
 * @see Templates
 * @todo Template Help
 */
function loadtemplate($templatename){
	if ($templatename=="" || !file_exists("templates/$templatename") || substr($templatename, -4) != '.htm')
		$templatename=getsetting("defaultskin", "resurrection.htm");
	if ($templatename=="" || !file_exists("templates/$templatename"))
		$templatename="resurrection.htm";
	$fulltemplate = file_get_contents("templates/$templatename");
	$fulltemplate = explode("<!--!",$fulltemplate);
	while (list($key,$val)=resurrection_array_next($fulltemplate)){
		$fieldname=substr($val,0,strpos($val,"-->"));
		if ($fieldname!=""){
			$template[$fieldname]=substr($val,strpos($val,"-->")+3);
			modulehook("template-{$fieldname}",
					array("content"=>$template[$fieldname]));
		}
	}
	return $template;
}

/**
 * The player's mail as data: unread ("new") and read ("old") message counts. maillink() builds the header link from
 * them, so the count never has to be read back out of the translated sentence (Phase 5 task 4).
 *
 * @return array{new: int, old: int}
 */
function maillink_counts(){
	global $session;
	$sql = "SELECT sum(if(seen=1,1,0)) AS seencount, sum(if(seen=0,1,0)) AS notseen FROM " . db_prefix("mail") . " WHERE msgto=\"".$session['user']['acctid']."\"";
	$result = db_query_cached($sql,"mail-{$session['user']['acctid']}",86400);
	$row = db_fetch_assoc($result);
	db_free_result($result);
	return array('new'=>(int)($row['notseen'] ?? 0), 'old'=>(int)($row['seencount'] ?? 0));
}

/**
 * Returns the header's mail link: an envelope icon, an unread-count badge (none at zero) and the full "N new, M old"
 * sentence as its accessible name. Highlighted (hotmotd) while there is unread mail.
 *
 * 1.1.2 printed the sentence as the link text. The sentence keeps its translation key ("Ye Olde Mail: %s new, %s old",
 * common); the short visible label "Mail" is a new key in common.
 *
 * @return string The formatted mail link
 */
function maillink(){
	$counts = maillink_counts();
	$label = sprintf(translate_inline("Ye Olde Mail: %s new, %s old", 'common'), $counts['new'], $counts['old']);
	return resurrection_header_control('mail.php', 'mail', translate_inline("Mail", 'common'), $label,
		$counts['new'] > 0 ? (string)$counts['new'] : null, $counts['new'] > 0);
}

/**
 * Returns the header's MoTD link: a scroll icon, and while an MoTD item is unread a dot badge and the hotmotd class.
 * The unread mark replaces 1.1.2's automatic MoTD popup (Phase 5 task 4).
 *
 * @return string The formatted MOTD link
 */
function motdlink(){
	global $session;
	$unread = !empty($session['needtoviewmotd']);
	$label = translate_inline("MoTD");
	return resurrection_header_control('motd.php', 'motd', $label, $unread ? translate_inline("MoTD (unread)") : $label,
		$unread ? '' : null, $unread);
}

/**
 * Returns the header's petition link: a help icon and the label "Petition for Help" (1.1.2's text and key).
 *
 * @return string The formatted petition link
 */
function petitionlink(){
	$label = translate_inline("Petition for Help");
	return resurrection_header_control('petition.php', 'petition', $label, $label, null, false);
}

/**
 * One header control: a real link to a popup page (mail.php, motd.php or petition.php) that works without JavaScript and
 * on a phone. It opens in a new tab or window (target="_blank"), so the game page and the navigation it allows stay as
 * they were: those pages are outside forced navigation (OVERRIDE_FORCED_NAV) and keep no restore page. On a desktop
 * with a mouse, the click opens 1.1.2's small popup window instead; if a blocker stops it, the link opens as usual.
 *
 * @param string $page The popup page
 * @param string $kind mail, motd or petition: the icon and the CSS class
 * @param string $text The visible label (hidden on a phone)
 * @param string $label The accessible name: aria-label and title
 * @param string|null $badge The badge: a count, '' for a dot, null for none
 * @param bool $hot Highlighted (1.1.2's hotmotd class)
 * @return string
 */
function resurrection_header_control($page, $kind, $text, $label, $badge, $hot){
	static $icons = array(
		'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5 12 13l8.5-6.5"/>',
		'motd'=>'<path d="M7 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6"/><path d="M7 4a2 2 0 0 0-2 2v12a2 2 0 0 1-2 2h3"/><path d="M9 9h7M9 13h7M9 17h4"/>',
		'petition'=>'<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6"/><path d="M12 17.2v.1"/>',
	);
	$charset = getsetting("charset", "ISO-8859-1");
	$label = htmlentities(full_sanitize($label), ENT_QUOTES | ENT_SUBSTITUTE, $charset);
	$text = htmlentities(full_sanitize($text), ENT_QUOTES | ENT_SUBSTITUTE, $charset);
	$out = "<a href='$page' target='_blank' rel='noopener' class='rz-control rz-control-$kind ".($hot ? "hotmotd" : "motd")."'"
		." aria-label='$label' title='$label' onclick=\"".resurrection_popup_enhancement($page)."\">"
		."<svg class='rz-icon' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'"
		." stroke-linecap='round' stroke-linejoin='round' aria-hidden='true' focusable='false'>{$icons[$kind]}</svg>"
		."<span class='rz-control-label'>$text</span>";
	if ($badge !== null) {
		$out .= $badge === '' ? "<span class='rz-badge rz-badge-dot' aria-hidden='true'></span>"
			: "<span class='rz-badge' aria-hidden='true'>".(int)$badge."</span>";
	}
	return $out."</a>";
}

/**
 * The click handler of a header control: on a desktop with a mouse (at least 900px wide, hover and a fine pointer) it
 * opens 1.1.2's popup window (popup()'s name and size) and cancels the link; anywhere else, or if the window was
 * blocked, the link is followed as a plain link.
 *
 * @param string $page The popup page
 * @param string $size The popup window size (Default: 550x300, as popup())
 * @return string
 */
function resurrection_popup_enhancement($page, $size="550x300"){
	$s = explode("x",$size);
	$name = preg_replace("([^[:alnum:]])","",$page);
	return "if(window.matchMedia&&window.matchMedia('(min-width: 900px) and (hover: hover) and (pointer: fine)').matches){"
		."var w=window.open(this.href,'$name','scrollbars=yes,resizable=yes,width={$s[0]},height={$s[1]}');"
		."if(w){w.focus();return false;}}return true;";
}

/**
 * The footer's Credits link to about.php, only where following it is allowed: always for a visitor who is not signed
 * in (about.php allows anonymous visits), and for a signed-in player only on a page that allows about.php as a nav
 * (home and news do). Elsewhere it is left out, so it never causes a bad nav or lets a player leave a fight or a
 * forced page. The link uses the exact nav the page allowed. The 1.1.2 credit line itself ({copyright}) is on every
 * page regardless (Phase 5 task 4).
 *
 * @return string
 */
function creditslink(){
	global $session;
	$href = null;
	if (empty($session['loggedin'])) {
		$href = 'about.php';
	} else {
		foreach (array_keys(is_array($session['allowednavs'] ?? null) ? $session['allowednavs'] : array()) as $nav) {
			if ($nav === 'about.php' || preg_match('/\Aabout\.php\?c=[0-9]+-[0-9]{6}\z/', (string)$nav)) {
				$href = (string)$nav;
				break;
			}
		}
	}
	if ($href === null) return "";
	return "<a href=\"".htmlspecialchars($href, ENT_QUOTES)."\" class=\"rz-credits\">".translate_inline("Credits")."</a>"
		." <span class=\"rz-footer-sep\" aria-hidden=\"true\">&middot;</span> ";
}

/**
 * The time to the next game day, as footer text ("Next new day in 3h 13m"), read from secondstonextgameday(). Plain
 * text: no script is needed to read it (Phase 5 task 4).
 *
 * @param array<string, mixed>|false $details gametimedetails(), or false to read the clock now
 * @return string
 */
function resurrection_newday_text($details=false){
	if ($details === false) $details = gametimedetails();
	$seconds = max(0, (int)secondstonextgameday($details) - (int)strtotime($details['now']));
	return sprintf(translate_inline("Next new day in %sh %sm", 'common'), intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
}
?>
