<?php
/**
 * Renders the character panel with the game's own code (charstats(), addcharstat(), getcharstats() and the template
 * parts in lib/pageparts.php, appoencode() in lib/output.php, check_temp_stat() in lib/tempstat.php) and none of its
 * database: the functions that read the database, the settings, the modules or the translation tables are replaced by
 * the stand-ins below. Used by tests/unit/CharacterPanelTest.php (Phase 5 task 7), in its own PHP process, from the
 * root of the tree under test.
 *
 * Reads JSON on stdin: {"template": "jade.htm", "user": {...account fields...}, "bufflist": {...}, "companions": {...},
 * "mount": {"mountname": ...}, "tempstats": {"attack": 2}, "translations": {"Text": "Translated"},
 * "specialties": {"MP": "Mystical Powers"}, "module": [["addcharstat", "Label"], ["addcharstat", "Label", "value"],
 * ["setcharstat", "Section", "Label", "value"]]}. The "module" calls run in the charstats hook, as a module's would.
 * Prints JSON: the panel's HTML, anything printed while rendering it, and the hooks the panel called (after the template's).
 */
$input = json_decode((string)stream_get_contents(STDIN), true);

$translations = $input['translations'] ?? [];
$hooks = [];

function translate($indata, $namespace = false) { global $translations; return is_string($indata) && isset($translations[$indata]) ? $translations[$indata] : $indata; }
function translate_inline($in, $namespace = false) { return translate($in, $namespace); }
function sprintf_translate() {
    $args = func_get_args();
    if (is_array($args[0])) { $args[0] = call_user_func_array('sprintf_translate', $args[0]); }
    $format = translate(str_replace('`%', '`%%', (string)array_shift($args)));
    return vsprintf($format, $args);
}
function tlschema($schema = false) {}
function tlbutton_pop() { return ''; }
function holidayize($text, $type = 'unknown') { return $text; }
function getsetting($name, $default) { return $default; }
function modulehook($name, $args = [], $allowinactive = false, $only = false) {
    global $input, $hooks;
    $hooks[] = $name;
    if ($name === 'charstats') {
        foreach ($input['module'] ?? [] as $call) {
            call_user_func_array((string)array_shift($call), $call);
        }
    }
    if ($name === 'specialtynames') {
        foreach ($input['specialties'] ?? [] as $code => $specialty) { $args[$code] = translate_inline($specialty); }
    }
    return $args;
}

require 'lib/constants.php';
require 'lib/output.php';
require 'lib/sanitize.php';
require 'lib/tempstat.php';
require 'lib/template.php';
require 'lib/pageparts.php';

$session = ['loggedin' => true, 'user' => $input['user'], 'bufflist' => $input['bufflist'] ?? []];
$companions = $input['companions'] ?? [];
$playermount = $input['mount'] ?? [];
foreach ($input['tempstats'] ?? [] as $name => $value) {
    apply_temp_stat($name, $value);
}

$template = loadtemplate((string)$input['template']);
$hooks = [];
ob_start();
$html = charstats();
$printed = (string)ob_get_clean();
echo json_encode(['html' => $html, 'output' => $printed . ($output ?? ''), 'hooks' => $hooks],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
