<?php
require_once __DIR__ . '/../../src/Compatibility/array_cursor.php';
//save module settings.
$userid = \Resurrection\Http\Input::integer($_GET, 'userid');
$module = httpget('module');
$post = httpallpost();
unset($post['csrf_token']);
$post = modulehook("validateprefs", $post, true, $module);
if (isset($post['validation_error']) && $post['validation_error']) {
	tlschema("module-$module");
	$post['validation_error'] =
		translate_inline($post['validation_error']);
	tlschema();
	output("Unable to change settings: `\$%s`0", $post['validation_error']);
} else {
	reset($post);
	while (list($key,$val)=resurrection_array_next($post)){
        if (!is_string($val)) { http_response_code(400); exit('Invalid module preference.'); }
        db_query('REPLACE INTO ' . db_prefix('module_userprefs') . ' (modulename,userid,setting,value) VALUES (?,?,?,?)', true, [$module, $userid, $key, $val]);
        output('Preference %s updated.`n', $key);
	}
	output("`^Preferences for module %s saved.`n", $module);
}
$op = "edit";
httpset("op", "edit");
httpset("subop", "module", true);
?>