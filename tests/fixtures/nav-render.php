<?php
/**
 * Renders a page's navs with the game's own nav code (addnav(), buildnavs() and private_addnav() in lib/output.php,
 * templatereplace() and loadtemplate()) and none of its database: the functions that read the database or the
 * translation tables are replaced by the stand-ins below. Used by tests/unit/NavigationTest.php (Phase 5 task 6), in
 * its own PHP process, from the root of the tree under test.
 *
 * Reads JSON on stdin: {"template": "jade.htm", "translator": false, "translations": {"Text": "Translated"},
 * "navs": [["Heading"], ["Label", "link.php"], ["Label", "link.php", false, true, "550x300"], ...]}; each entry is the
 * arguments of one addnav() call. Prints JSON: the built navs, the access keys used, the key map the page emits (when
 * the code has one) with the {script} that carries it, the nav column's wrapping parts, and the allowed navs.
 */
$input = json_decode((string)stream_get_contents(STDIN), true);

$session = ['loggedin' => false, 'counter' => 7, 'allowednavs' => [], 'user' => ['superuser' => 0]];
$translations = $input['translations'] ?? [];
$translator = (bool)($input['translator'] ?? false);

function translate($indata, $namespace = false) { global $translations; return is_string($indata) && isset($translations[$indata]) ? $translations[$indata] : $indata; }
function translate_inline($in, $namespace = false) { return translate($in, $namespace); }
function tlschema($schema = false) {}
/** The translator's button, as tlbutton_pop() returns it for a translator: one per translated text. */
function tlbutton_pop() { global $translator; return $translator ? "<a href='translatortool.php' class='t-button'>T</a>" : ''; }
function holidayize($text, $type = 'unknown') { return $text; }
function getsetting($name, $default) { return $default; }
function modulehook($name, $args = [], $allowinactive = false, $only = false) { return $args; }

require 'lib/output.php';
require 'lib/template.php';
require 'lib/pageparts.php';

$template = loadtemplate((string)$input['template']);
foreach ($input['navs'] as $args) {
    call_user_func_array('addnav', $args);
}
$html = buildnavs();
echo json_encode([
    'html' => $html,
    'accesskeys' => array_map('strval', array_keys($accesskeys)),
    'keys' => function_exists('resurrection_nav_keys') ? resurrection_nav_keys() : null,
    'script' => function_exists('resurrection_nav_script') ? resurrection_nav_script() : null,
    'column' => function_exists('resurrection_nav_column') ? resurrection_nav_column($html, (string)($input['navad'] ?? '')) : null,
    'allowednavs' => array_keys($session['allowednavs']),
    'output' => $output ?? '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
