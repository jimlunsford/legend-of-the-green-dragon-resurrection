<?php
require_once __DIR__ . '/src/Compatibility/array_cursor.php';
// addnews ready
// translator ready
// mail ready
require_once("common.php");
require_once("lib/http.php");
require_once("lib/sanitize.php");
check_su_access(SU_MANAGE_MODULES);
tlschema("modulemanage");

page_header("Module Manager");

require_once("lib/superusernav.php");
superusernav();

addnav("Module Categories");

addnav("",$REQUEST_URI);
require_once("lib/module_manager.php");
try {
    $op = \Resurrection\Http\Input::choice($_GET, 'op', ['', 'mass', ...RESURRECTION_MODULE_OPS], '');
    $module = \Resurrection\Http\Input::string($_GET, 'module');
    if ($op !== '') {
        // The form posts back only to the URL built from these; nothing else rides along.
        resurrection_module_query($op === 'mass' ? ['op', 'cat'] : ['op', 'module', 'cat']);
        if ($op !== 'mass') resurrection_module_name($module);
    }
} catch (InvalidArgumentException $error) { http_response_code(400); exit('Invalid module request.'); }
$modules = [];
if ($op !== '') {
    $formcat = \Resurrection\Http\Input::string($_GET, 'cat');
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if ($op === 'mass' && !$post) { http_response_code(405); exit('Use the form on the module manager.'); }
    if (!$post) {
        // GET only shows the confirmation form, bound to the module's state as shown.
        try {
            $state = resurrection_module_state($module);
            resurrection_module_allowed($op, $module, $state);
        } catch (Throwable $error) { resurrection_module_failure($error); }
        $url = resurrection_module_confirm_url($op, $module, $formcat);
        addnav('', $url);
        output("`2Confirm `^%s`2 of `%%s`0`n", translate_inline($op), $module);
        rawoutput("<form action='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' method='POST'>" .
            resurrection_action_fields("module-$op-$module", resurrection_module_context($op, $module, [$module => $state])) .
            "<input type='submit' class='button' value='" . htmlspecialchars(translate_inline("Confirm module operation"), ENT_QUOTES, 'UTF-8') . "'></form>");
        page_footer();
    }
    resurrection_require_post();
    try {
        resurrection_module_lock();
        if ($op === 'mass') {
            // A stale list is a conflict; a box the list did not offer is a bad request. Both before any change.
            [$op, $modules] = resurrection_module_mass_request($formcat);
            $shown = resurrection_module_list($formcat);
            $scope = 'module-mass-' . $formcat;
            $context = resurrection_module_context('mass', $formcat, $shown);
            resurrection_consume_action($scope, $context);
            resurrection_module_mass_allowed($op, $modules, $shown);
        } else {
            if (array_diff(array_keys($_POST), ['csrf_token', 'action_token']) !== []) throw new InvalidArgumentException('Unexpected field.');
            $state = resurrection_module_state($module);
            resurrection_module_allowed($op, $module, $state);
            resurrection_consume_action("module-$op-$module", resurrection_module_context($op, $module, [$module => $state]));
            $modules = [$module];
        }
    } catch (Throwable $error) { resurrection_module_failure($error); }
}
foreach ($modules as $module) {
    if (!resurrection_module_run($op, $module)) break;
}
if ($op !== '') {
    resurrection_module_unlock();
    $op = '';
    httpset('op', '');
}

$install_status = get_module_install_status();
$uninstmodules = $install_status['uninstalledmodules'];
$seencats = $install_status['installedcategories'];
$ucount = $install_status['uninstcount'];

ksort($seencats);
$h = static fn ($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// 1.1.2 printed authors as raw HTML for their <br> line breaks; they are now escaped, with <br> kept as a line break.
$author = static fn ($value) => str_ireplace(['<br>', '<br/>', '<br />'], '`n', (string)$value);
addnav(array(" ?Uninstalled - (%s modules)", $ucount), "modules.php");
reset($seencats);
foreach ($seencats as $cat=>$count) {
	addnav(array(" ?%s - (%s modules)", $cat, $count), "modules.php?cat=" . rawurlencode((string)$cat));
}

$cat = \Resurrection\Http\Input::string($_GET, 'cat');
if ($cat !== '' && !isset($seencats[$cat])) { $cat = ''; }
$catUrl = rawurlencode($cat);
$massurl = "modules.php?op=mass&cat=$catUrl";

if ($op==""){
	if ($cat) {
		$sortby=\Resurrection\Http\Input::choice($_GET, 'sortby', ['active', 'formalname', 'moduleauthor', 'installdate'], 'installdate');
		if (!$sortby) $sortby="installdate";
		$order=httpget('order');
		$tcat = translate_inline($cat);
		output("`n`b%s Modules`b`n", $tcat);
		$deactivate = translate_inline("Deactivate");
		$activate = translate_inline("Activate");
		$uninstall = translate_inline("Uninstall");
		$reinstall = translate_inline("Reinstall");
		$strsettings = translate_inline("Settings");
		$strnosettings = translate_inline("`\$No Settings`0");
		$uninstallconfirm = translate_inline("Are you sure you wish to uninstall this module?  All user preferences and module settings will be lost.  If you wish to temporarily remove access to the module, you may simply deactivate it.");
		$uninstallconfirm = $h(json_encode($uninstallconfirm, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
		$status = translate_inline("Status");
		$mname = translate_inline("Module Name");
		$ops = translate_inline("Ops");
		$mauth = translate_inline("Module Author");
		$inon = translate_inline("Installed On");
		$installstr = translate_inline("by %s");
		$active = translate_inline("`@Active`0");
		$inactive = translate_inline("`\$Inactive`0");
		addnav("", $massurl);
		rawoutput("<form action='" . $h($massurl) . "' method='POST'>" . resurrection_action_fields("module-mass-$cat", resurrection_module_context('mass', $cat, resurrection_module_list($cat))));
		rawoutput("<table border='0' cellpadding='2' cellspacing='1'".resurrection_presentation("grid").">",true);
		$sorts = [];
		foreach (['active' => [$status, 1], 'formalname' => [$mname, 1], 'moduleauthor' => [$mauth, 1], 'installdate' => [$inon, 0]] as $column => [$label, $default]) {
			$sorturl = "modules.php?cat=$catUrl&sortby=$column&order=" . ($sortby == $column ? !$order : $default);
			addnav("", $sorturl);
			$sorts[] = "<td><a href='" . $h($sorturl) . "'>" . $h($label) . "</a></td>";
		}
		rawoutput("<tr class='trhead'><td>&nbsp;</td><td>" . $h($ops) . "</td>" . implode('', $sorts) . "</tr>");
		$sql = "SELECT * FROM " . db_prefix("modules") . " WHERE category=? ORDER BY ".db_identifier($sortby)." ".($order?"ASC":"DESC");
		$result = db_query($sql, true, [$cat]);
		if (db_num_rows($result)==0){
			rawoutput("<tr class='trlight'><td colspan='6' align='center'>");
			output("`i-- No Modules Installed--`i");
			rawoutput("</td></tr>");
		}
		$number=db_num_rows($result);
		$link = static function (string $url, string $label, string $attributes = '') use ($h) {
			addnav("", $url);
			rawoutput("<a href='" . $h($url) . "'$attributes>");
			output_notl($label);
			rawoutput("</a>");
		};
		for ($i=0;$i<$number;$i++){
			$row = db_fetch_assoc($result);
			$name = (string)$row['modulename'];
			$bundled = resurrection_module_bundled($name);
			rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>",true);
			rawoutput("<td nowrap valign='top'>");
			rawoutput("<input type='checkbox' name='module[]' value='" . $h($name) . "'>");
			rawoutput("</td><td valign='top' nowrap>[ ");
			if ($row['active']){
				$link(resurrection_module_confirm_url('deactivate', $name, $cat), $deactivate);
			}elseif ($bundled){
				$link(resurrection_module_confirm_url('activate', $name, $cat), $activate);
			}
			// A module that is not bundled can only be deactivated: running its code to uninstall or reinstall it is refused.
			if ($bundled) {
				rawoutput(" |");
				$link(resurrection_module_confirm_url('uninstall', $name, $cat), $uninstall, " onClick='return confirm($uninstallconfirm);'");
				rawoutput(" | ");
				$link(resurrection_module_confirm_url('reinstall', $name, $cat), $reinstall);
			}

			if ($session['user']['superuser'] & SU_EDIT_CONFIG) {
				if (strstr($row['infokeys'], "|settings|")) {
					rawoutput(" | ");
					$link("configuration.php?op=modulesettings&module=" . rawurlencode($name), $strsettings);
				} else {
					output_notl(" | %s", $strnosettings);
				}
			}

			rawoutput(" ]</td><td valign='top'>");
			output_notl($row['active']?$active:$inactive);
			require_once("lib/sanitize.php");
			rawoutput("</td><td nowrap valign='top'><span title=\"" . $h(
					(isset($row['description'])&&$row['description']?
					 $row['description']:sanitize($row['formalname'])))."\">");
			output_notl("%s %s", $row['formalname'], $row['version']);
			rawoutput("<br>");
			output_notl("(%s) ", $row['modulename'], $row['version']);
			rawoutput("</span></td><td valign='top'>");
			output_notl("`#%s`0", $author($row['moduleauthor']));
			rawoutput("</td><td nowrap valign='top'>");
			$line = sprintf($installstr, $row['installedby']);
			output_notl("%s", $row['installdate']);
			rawoutput("<br>");
			output_notl("%s", $line);
			rawoutput("</td></tr>");
		}
		rawoutput("</table><br />");
		$activate = translate_inline("Activate");
		$deactivate = translate_inline("Deactivate");
		$reinstall = translate_inline("Reinstall");
		$uninstall = translate_inline("Uninstall");
		rawoutput("<input type='submit' name='activate' class='button' value='" . $h($activate) . "'>");
		rawoutput("<input type='submit' name='deactivate' class='button' value='" . $h($deactivate) . "'>");
		rawoutput("<input type='submit' name='reinstall' class='button' value='" . $h($reinstall) . "'>");
		rawoutput("<input type='submit' name='uninstall' class='button' value='" . $h($uninstall) . "'>");
		rawoutput("</form>");
	} else {
		$sorting=\Resurrection\Http\Input::choice($_GET, 'sorting', ['name', 'author', 'category', 'shortname'], 'shortname');
		if (!$sorting) $sorting="shortname";
		$order=httpget('order');
		output("`bUninstalled Modules`b`n");
		$install = translate_inline("Install");
		$mname = translate_inline("Module Name");
		$ops = translate_inline("Ops");
		$mauth = translate_inline("Module Author");
		$categ = translate_inline("Category");
		$fname = translate_inline("Filename");
		addnav("", $massurl);
		rawoutput("<form action='" . $h($massurl) . "' method='POST'>" . resurrection_action_fields("module-mass-", resurrection_module_context('mass', '', resurrection_module_list(''))));
		rawoutput("<table border='0' cellpadding='2' cellspacing='1'".resurrection_presentation("grid").">",true);
		$sorts = [];
		foreach (['name' => $mname, 'author' => $mauth, 'category' => $categ, 'shortname' => $fname] as $column => $label) {
			$sorturl = "modules.php?sorting=$column&order=" . ($sorting == $column ? !$order : 0);
			addnav("", $sorturl);
			$sorts[] = "<td><a href='" . $h($sorturl) . "'>" . $h($label) . "</a></td>";
		}
		rawoutput("<tr class='trhead'><td>&nbsp;</td><td>" . $h($ops) . "</td>" . implode('', $sorts) . "</tr>");
		if (count($uninstmodules) > 0) {
			$count = 0;
			$moduleinfo=array();
			$sortby=array();
			$numberarray=array();
			$invalidmodule = array(
				"version"=>"",
				"author"=>"",
				"category"=>"",
				"download"=>"",
				"invalid"=>true,
			);
			foreach($uninstmodules as $key=>$shortname) {
				// Only bundled modules are ever loaded. Any other file in modules/ (archive-only code, a stray library)
				// is listed as not installable without running it; 1.1.2 ran every file here just to list it.
				if (!resurrection_module_bundled($shortname)) {
					$temp=array_merge($invalidmodule,array("name"=>$shortname.".php","note"=>"(`\$Not a bundled module`0)"));
				} else {
				//test if the file is a valid module or a lib file/whatever that got in, maybe even malcode that does not have module form
				$shortnamelower = strtolower($shortname);
				$file = strtolower(file_get_contents("modules/$shortname.php"));
				if (strpos($file,$shortnamelower."_getmoduleinfo")===false ||
					//strpos($file,$shortname."_dohook")===false ||
					//do_hook is not a necessity
					strpos($file,$shortnamelower."_install")===false ||
					strpos($file,$shortnamelower."_uninstall")===false) {
						//here the files has neither do_hook nor getinfo, which means it won't execute as a module here --> block it + notify the admin who is the manage modules section
						$temp=array_merge($invalidmodule,array("name"=>$shortname.".php","note"=>"(`\$Invalid Module! Contact Author or check file!`0)"));
				} else {
					$temp= get_module_info($shortname);
				}
				}
				//end of testing
				if (!$temp || empty($temp)) continue;
				$temp['shortname']=$shortname;
				array_push($moduleinfo,$temp);
				array_push($sortby,full_sanitize($temp[$sorting]));
				array_push($numberarray,$count);
				$count++;
			}
			array_multisort($sortby,($order?SORT_DESC:SORT_ASC),$numberarray,($order?SORT_DESC:SORT_ASC));
			for ($a=0;$a<count($moduleinfo);$a++) {
				$i=$numberarray[$a];
				rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
				if (isset($moduleinfo[$i]['invalid']) && $moduleinfo[$i]['invalid']===true) {
					rawoutput("<td></td><td nowrap valign='top'>");
						output("Not installable");
						rawoutput("</td>");
				} else {
					rawoutput("<td><input type='checkbox' name='module[]' value='" . $h($moduleinfo[$i]['shortname']) . "'></td>");
					rawoutput("<td nowrap valign='top'>");
					rawoutput("[ ");
					$installurl = resurrection_module_confirm_url('install', $moduleinfo[$i]['shortname'], (string)$moduleinfo[$i]['category']);
					addnav("", $installurl);
					rawoutput("<a href='" . $h($installurl) . "'>");
					output_notl($install);
					rawoutput("</a>]</td>");
				}
			    rawoutput("<td nowrap valign='top'><span title=\"" . $h(
					(isset($moduleinfo[$i]['description'])&&
					     $moduleinfo[$i]['description'] ?
					 $moduleinfo[$i]['description'] :
					 sanitize($moduleinfo[$i]['name'])))."\">");
				rawoutput($h($moduleinfo[$i]['name']." ".$moduleinfo[$i]['version']));
				if (isset($moduleinfo[$i]['note'])) {
					rawoutput(" ");
					output($moduleinfo[$i]['note']);
				}
				rawoutput("</span></td><td valign='top'>");
				output_notl("`#%s`0", $author($moduleinfo[$i]['author']));
				rawoutput("</td><td valign='top'>");
				rawoutput($h($moduleinfo[$i]['category']));
				rawoutput("</td><td valign='top'>");
				rawoutput($h($moduleinfo[$i]['shortname'] . ".php"));
				rawoutput("</td>");
				rawoutput("</tr>");
				if (isset($moduleinfo[$i]['requires']) && count($moduleinfo[$i]['requires'])){
					rawoutput("<tr class='".($i%2?"trlight":"trdark")."'>");
					rawoutput("<td>&nbsp;</td>");
					rawoutput("<td colspan='6'>");
					output("`bRequires:`b`n");
					reset($moduleinfo[$i]['requires']);
					while (list($key,$val)=resurrection_array_next($moduleinfo[$i]['requires'])){
						$info = explode("|",$val);
						if (module_check_requirements(array($key=>$val))){
							output_notl("`@");
						}else{
							output_notl("`\$");
						}
						if(isset($info[1])) output_notl("%s %s -- %s`n", $key, $info[0], $info[1]);
						else output_notl("%s %s`n", $key, $info[0]);
					}
					rawoutput("</td>");
					rawoutput("</tr>");
				}
				$count++;
			}
		} else {
			rawoutput("<tr class='trlight'><td colspan='6' align='center'>");
			output("`i--No uninstalled modules were found--`i");
			rawoutput("</td></tr>");
		}
		rawoutput("</table><br />");
		$install = translate_inline("Install");
		rawoutput("<input type='submit' name='install' class='button' value='" . $h($install) . "'>");
		rawoutput("</form>");
	}
}

page_footer();
?>
